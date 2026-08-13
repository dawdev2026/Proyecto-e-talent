import { chromium } from 'playwright';
import AxeBuilder from '@axe-core/playwright';

const baseUrl = (process.env.QA_BASE_URL || 'https://metrictest.dawchile.cl').replace(/\/$/, '');
const email = process.env.QA_TEST_EMAIL || '';
const password = process.env.QA_TEST_PASSWORD || '';
const maxPages = Number(process.env.QA_MAX_PAGES || 80);
const browser = await chromium.launch({ headless: true });
const context = await browser.newContext({ ignoreHTTPSErrors: false });
const page = await context.newPage();
const visited = new Set();
const queue = ['/login'];
const failures = [];
const axeViolations = [];

function absoluteUrl(href) {
  try {
    const url = new URL(href, baseUrl);
    if (url.origin !== new URL(baseUrl).origin) return null;
    url.hash = '';
    return `${url.pathname}${url.search}`;
  } catch {
    return null;
  }
}

function isSafeGetRoute(path) {
  return !/(logout|delete|remove|destroy|clear|cancel|save|create|update|assign|submit|import|upload|reset)/i.test(path);
}

async function checkPage(path) {
  const response = await page.goto(`${baseUrl}${path}`, { waitUntil: 'domcontentloaded', timeout: 30000 });
  const status = response?.status() ?? 0;
  if (status >= 400) failures.push(`${path}: HTTP ${status}`);
  if (page.url().includes('/login') && path !== '/login' && email) {
    failures.push(`${path}: la sesión no se mantuvo; redirigió a login`);
    return;
  }

  const result = await new AxeBuilder({ page }).analyze();
  for (const violation of result.violations) {
    axeViolations.push({ path, id: violation.id, impact: violation.impact, nodes: violation.nodes.length });
  }

  for (const href of await page.locator('a[href]').evaluateAll((links) => links.map((link) => link.href))) {
    const next = absoluteUrl(href);
    if (next && isSafeGetRoute(next) && !visited.has(next) && !queue.includes(next)) queue.push(next);
  }
}

try {
  await checkPage('/login');
  if (email || password) {
    if (!email || !password) throw new Error('QA_TEST_EMAIL y QA_TEST_PASSWORD deben configurarse juntos.');
    await page.locator('input[name="identifier"], input[name="email"]').first().fill(email);
    await page.locator('input[name="password"]').fill(password);
    await page.locator('button[type="submit"], input[type="submit"]').first().click();
    await page.waitForLoadState('domcontentloaded');
    if (page.url().includes('/login')) throw new Error('El login QA no fue exitoso.');
  } else {
    console.warn('QA_TEST_EMAIL/QA_TEST_PASSWORD no configurados: se ejecutará solo el alcance público.');
  }

  while (queue.length && visited.size < maxPages) {
    const path = queue.shift();
    if (visited.has(path)) continue;
    visited.add(path);
    await checkPage(path);
  }
} catch (error) {
  failures.push(error.message);
} finally {
  await browser.close();
}

console.log(`QA pages checked: ${visited.size}`);
console.log(`Accessibility violations: ${axeViolations.length}`);
for (const violation of axeViolations) console.log(`AXE ${violation.impact || 'unknown'} ${violation.id} ${violation.path} (${violation.nodes} nodes)`);
for (const failure of failures) console.log(`FUNCTIONAL ${failure}`);

if (failures.length || axeViolations.length) process.exit(1);
