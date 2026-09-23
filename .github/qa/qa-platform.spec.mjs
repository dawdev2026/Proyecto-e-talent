import { chromium } from 'playwright';
import AxeBuilder from '@axe-core/playwright';

const baseUrl = (process.env.QA_BASE_URL || 'https://metrictest.dawchile.cl').replace(/\/$/, '');
const email = process.env.QA_TEST_EMAIL || '';
const password = process.env.QA_TEST_PASSWORD || '';
const maxPages = Math.min(Number(process.env.QA_MAX_PAGES || 35), 35);
const globalTimeoutMs = Number(process.env.QA_GLOBAL_TIMEOUT_MS || 240000);
const startedAt = Date.now();
const browser = await chromium.launch({ headless: true });
const context = await browser.newContext({ ignoreHTTPSErrors: false });
let page = await context.newPage();
const visited = new Set();
const queue = ['/login'];
const downloadQueue = new Map();
const failures = [];
const axeViolations = [];
const axeWarnings = [];
const blockingImpacts = new Set(['critical', 'serious']);

function absoluteUrl(href) {
  try {
    const url = new URL(href, baseUrl);
    if (url.origin !== new URL(baseUrl).origin) return null;
    url.hash = '';
    return isDownloadRoute(url.pathname) ? `${url.pathname}${url.search}` : url.pathname;
  } catch {
    return null;
  }
}

function isSafeGetRoute(path) {
  return !/(logout|delete|remove|destroy|clear|cancel|save|create|update|assign|submit|import|upload|reset|export|download)/i.test(path);
}

function isDownloadRoute(path) {
  return /(export|download)/i.test(path);
}

function ensureBudget(label) {
  if (Date.now() - startedAt >= globalTimeoutMs) {
    throw new Error(`presupuesto global agotado durante ${label} (${globalTimeoutMs} ms)`);
  }
}

async function resetPage() {
  await page.close().catch(() => {});
  page = await context.newPage();
}

async function checkPage(path) {
  ensureBudget(path);
  let response;
  try {
    response = await page.goto(`${baseUrl}${path}`, { waitUntil: 'domcontentloaded', timeout: 15000 });
  } catch (error) {
    failures.push(`${path}: ${error.message}`);
    await resetPage();
    return;
  }
  const status = response?.status() ?? 0;
  if (status >= 400) failures.push(`${path}: HTTP ${status}`);
  if (page.url().includes('/login') && path !== '/login' && email) {
    failures.push(`${path}: la sesión no se mantuvo; redirigió a login`);
    return;
  }

  let result;
  let axeTimer;
  try {
    result = await Promise.race([
      new AxeBuilder({ page }).analyze(),
      new Promise((resolve) => {
        axeTimer = setTimeout(() => resolve({ timeout: true, violations: [] }), 10000);
      }),
    ]);
  } catch (error) {
    clearTimeout(axeTimer);
    failures.push(`${path}: Axe no pudo completar la auditoría (${error.message})`);
    await resetPage();
    return;
  }
  clearTimeout(axeTimer);
  if (result.timeout) {
    failures.push(`${path}: Axe excedió el tiempo máximo de auditoría`);
    await resetPage();
    return;
  }
  for (const violation of result.violations) {
    const item = {
      path,
      id: violation.id,
      impact: violation.impact || 'unknown',
      nodes: violation.nodes.length,
      targets: violation.nodes.slice(0, 5).map((node) => node.target.join(' ')),
    };
    if (blockingImpacts.has(item.impact) || item.impact === 'unknown') {
      axeViolations.push(item);
    } else {
      axeWarnings.push(item);
    }
  }

  for (const href of await page.locator('a[href]').evaluateAll((links) => links.map((link) => link.href))) {
    const next = absoluteUrl(href);
    if (!next || visited.has(next)) continue;
    if (isDownloadRoute(next)) {
      const kind = next.match(/\/(result-export|answers-export)$/i)?.[1]?.toLowerCase();
      const key = kind ? `/${kind}` : next;
      if (!downloadQueue.has(key)) downloadQueue.set(key, next);
    } else if (isSafeGetRoute(next) && !queue.includes(next)) {
      queue.push(next);
    }
  }
}

async function checkDownload(path) {
  ensureBudget(path);
  const result = await Promise.race([
    page.evaluate(async (url) => {
      const controller = new AbortController();
      const timer = setTimeout(() => controller.abort(), 30000);
      try {
        const response = await fetch(url, {
          method: 'GET',
          credentials: 'include',
          signal: controller.signal,
        });
        const headers = Object.fromEntries(response.headers.entries());
        await response.body?.cancel();
        return { status: response.status, headers };
      } catch (error) {
        return { error: error.message };
      } finally {
        clearTimeout(timer);
      }
    }, `${baseUrl}${path}`),
    new Promise((resolve) => setTimeout(() => resolve({ error: 'timeout de validación' }), 35000)),
  ]);
  if (result.error) {
    failures.push(`${path}: no se pudo validar la descarga (${result.error})`);
    return;
  }
  const disposition = result.headers['content-disposition'] || '';
  if (result.status >= 400) failures.push(`${path}: descarga HTTP ${result.status}`);
  if (!disposition) failures.push(`${path}: la respuesta no declara Content-Disposition`);
  const contentLength = Number(result.headers['content-length'] || 0);
  if (result.headers['content-length'] !== undefined && contentLength <= 0) failures.push(`${path}: la descarga está vacía`);
}

try {
  await checkPage('/login');
  if (email || password) {
    if (!email || !password) throw new Error('QA_TEST_EMAIL y QA_TEST_PASSWORD deben configurarse juntos.');
    await page.locator('input[name="identifier"], input[name="email"]').first().fill(email);
    await page.locator('input[name="password"]').fill(password);
    await page.locator('button[type="submit"], input[type="submit"]').first().click();
    await page.waitForLoadState('domcontentloaded', { timeout: 15000 });
    if (page.url().includes('/login')) throw new Error('El login QA no fue exitoso.');
  } else {
    console.warn('QA_TEST_EMAIL/QA_TEST_PASSWORD no configurados: se ejecutará solo el alcance público.');
  }

  while (queue.length && visited.size < maxPages) {
    ensureBudget('recorrido de páginas');
    const path = queue.shift();
    if (visited.has(path)) continue;
    visited.add(path);
    await checkPage(path);
  }
  for (const path of downloadQueue.values()) {
    ensureBudget('validación de descargas');
    await checkDownload(path);
  }
} catch (error) {
  failures.push(error.message);
} finally {
  await browser.close();
}

console.log(`QA pages checked: ${visited.size}`);
console.log(`Downloads checked: ${downloadQueue.size}`);
console.log(`Suite duration: ${Date.now() - startedAt} ms`);
console.log(`Accessibility blocking violations: ${axeViolations.length}`);
console.log(`Accessibility warnings: ${axeWarnings.length}`);
for (const violation of [...axeViolations, ...axeWarnings]) {
  console.log(`AXE ${violation.impact} ${violation.id} ${violation.path} (${violation.nodes} nodes; targets: ${violation.targets.join(' | ')})`);
}
for (const failure of failures) console.log(`FUNCTIONAL ${failure}`);

if (failures.length || axeViolations.length) process.exit(1);
