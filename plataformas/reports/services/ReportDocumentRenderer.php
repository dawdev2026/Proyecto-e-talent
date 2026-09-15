<?php
declare(strict_types=1);

final class ReportDocumentRenderer
{
    private const DEFAULT_PRIMARY = [0, 40, 60];
    private const DEFAULT_ACCENT = [146, 190, 46];
    private const DEFAULT_TEXT = [46, 46, 46];
    private const DEFAULT_MUTED = [110, 110, 110];
    private const DEFAULT_LIGHT = [246, 248, 244];
    private const DEFAULT_BORDER = [217, 217, 217];

    private ReportTemplateCatalog $templates;
    private array $primary = self::DEFAULT_PRIMARY;
    private array $accent = self::DEFAULT_ACCENT;
    private array $text = self::DEFAULT_TEXT;
    private array $muted = self::DEFAULT_MUTED;
    private array $light = self::DEFAULT_LIGHT;
    private array $border = self::DEFAULT_BORDER;
    private string $brandName = 'e-talent';
    private string $logoPath = '';

    public function __construct(?ReportTemplateCatalog $templates = null, ?PlatformSettingsModel $settings = null)
    {
        $this->templates = $templates ?: new ReportTemplateCatalog();

        // La configuración visual se resuelve una vez por ejecución. La plantilla
        // no consulta datos de negocio ni empresas: solo aplica tokens de diseño.
        if (class_exists('PlatformSettingsModel')) {
            $settingsModel = $settings ?: new PlatformSettingsModel();
            $design = $settingsModel->designSettings();
            $login = $settingsModel->loginSettings();
            $this->primary = $this->hexToRgb((string) ($design['app_primary_color'] ?? ''), self::DEFAULT_PRIMARY);
            $this->accent = $this->hexToRgb((string) ($design['button_background_color'] ?? ''), self::DEFAULT_ACCENT);
            $this->text = $this->hexToRgb((string) ($design['portal_text_color'] ?? ''), self::DEFAULT_TEXT);
            $this->muted = $this->mix($this->text, [255, 255, 255], 0.58);
            $this->light = $this->hexToRgb((string) ($design['layout_background_color'] ?? ''), self::DEFAULT_LIGHT);
            $this->border = $this->hexToRgb((string) ($design['table_border_color'] ?? ''), self::DEFAULT_BORDER);
            $this->brandName = $this->brandName($design);
            $this->logoPath = $this->brandLogoPath($design, $login);
        }
    }

    public function renderHtml(array $execution): string
    {
        $spec = $execution['spec'] ?? [];
        $payload = $execution['payload'] ?? [];
        $title = (string) ($spec['metadata']['report_key'] ?? 'Informe');
        $candidate = $payload['candidate'] ?? [];
        $sections = $this->renderSections($spec['sections'] ?? [], $execution);

        return '<!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
            . '<title>' . $this->escape($title) . '</title><style>' . $this->styles() . '</style></head><body>'
            . '<main class="report">'
            . '<header class="report-header"><p class="eyebrow">Informe</p><h1>' . $this->escape($title) . '</h1>'
            . '<p class="meta">' . $this->escape((string) ($candidate['name'] ?? 'Evaluado')) . ' · '
            . $this->escape((string) ($candidate['company'] ?? 'Empresa')) . '</p></header>'
            . implode('', $sections)
            . '<footer>Generado el ' . $this->escape((string) ($execution['resolved_at'] ?? date('c'))) . '</footer>'
            . '</main></body></html>';
    }

    public function renderPdf(array $execution): string
    {
        if (!class_exists('PostulantSimplePdf')) {
            throw new RuntimeException('El motor PDF de la plataforma no está disponible.');
        }

        $metadata = $execution['spec']['metadata'] ?? [];
        $template = $this->templates->resolve((string) ($metadata['template_key'] ?? ''));
        if ($template['renderer'] === 'plain') {
            return $this->renderPlainPdf($execution);
        }

        // La plantilla institucional conserva la composición histórica del
        // informe del proceso: dos páginas, personalidad, cognición,
        // autorregulación, vocacional y resultado final. El MD gráfico decide
        // la plantilla y el branding; los datos siguen viniendo del payload.
        if ($template['key'] === ReportTemplateCatalog::DEFAULT_TEMPLATE && class_exists('PostulantRankingReportPdfService')) {
            $payload = is_array($execution['payload'] ?? null) ? $execution['payload'] : [];
            $payload['generated_at'] = (string) ($execution['resolved_at'] ?? ($payload['generated_at'] ?? date('d-m-Y H:i')));
            return (new PostulantRankingReportPdfService())->render($payload);
        }

        return $this->renderDesignedPdf($execution);
    }

    private function renderPlainPdf(array $execution): string
    {
        $pdf = new PostulantSimplePdf();
        $pdf->addPage();
        $title = (string) ($execution['spec']['metadata']['report_key'] ?? 'Informe');
        $candidate = $execution['payload']['candidate'] ?? [];
        $pdf->text(36, 54, 18, $title, [0, 40, 60], true);
        $pdf->text(36, 75, 10, (string) ($candidate['name'] ?? 'Evaluado'), [110, 110, 110]);
        $pdf->line(36, 88, 559, 88, [146, 190, 46], 2);

        $y = 118;
        foreach ($this->plainLines($execution['spec']['sections'] ?? [], $execution) as $line) {
            if ($y > 770) {
                $pdf->addPage();
                $y = 54;
            }
            $pdf->text(36, $y, 9.5, $line, [46, 46, 46]);
            $y += 15;
        }
        return $pdf->output();
    }

    /**
     * Renderiza una plantilla institucional genérica y extensible.
     *
     * El Markdown sigue siendo la fuente de contenido y reglas. Este método
     * solamente transforma sus bloques en componentes visuales, evitando que el
     * Markdown tenga que contener HTML, CSS o código ejecutable.
     */
    private function renderDesignedPdf(array $execution): string
    {
        $pdf = new PostulantSimplePdf();
        $pdf->addPage();
        $candidate = is_array($execution['payload']['candidate'] ?? null) ? $execution['payload']['candidate'] : [];
        $process = is_array($execution['payload']['process'] ?? null) ? $execution['payload']['process'] : [];
        $metadata = $execution['spec']['metadata'] ?? [];
        $title = $this->humanizeTitle((string) ($metadata['title'] ?? $metadata['report_key'] ?? 'Informe'));
        $date = $this->reportDate((string) ($execution['resolved_at'] ?? date('c')));
        $y = 112;
        $page = 1;

        $this->drawPageHeader($pdf, $title, $date);
        $pdf->text(36, $y, 25, $this->pdfText($title), $this->primary, true);
        $y += 35;
        $pdf->line(36, $y, 559, $y, $this->accent, 2);
        $y += 24;
        $y = $this->drawIdentityCard($pdf, $y, $candidate, $process);

        foreach (($execution['spec']['sections'] ?? []) as $section) {
            $heading = trim((string) ($section['heading'] ?? ''));
            $markdown = $this->replaceTemplates((string) ($section['markdown'] ?? ''), $execution);
            $blocks = $this->pdfBlocks($heading, $markdown);
            $metricBlocks = array_values(array_filter($blocks, fn(array $block): bool => ($block['type'] ?? '') === 'metric'));
            if (count($metricBlocks) >= 2) {
                $height = $this->metricGridHeight($metricBlocks);
                if ($y + $height > 785) {
                    $this->drawPageFooter($pdf, $page);
                    $pdf->addPage();
                    $page++;
                    $this->drawPageHeader($pdf, $title, 'Continuación');
                    $y = 104;
                }
                $y = $this->drawMetricGrid($pdf, $y, $metricBlocks);
                $blocks = array_values(array_filter($blocks, fn(array $block): bool => ($block['type'] ?? '') !== 'metric'));
            }
            foreach ($blocks as $block) {
                $height = $this->blockHeight($block);
                if ($y + $height > 785) {
                    $this->drawPageFooter($pdf, $page);
                    $pdf->addPage();
                    $page++;
                    $this->drawPageHeader($pdf, $title, 'Continuación');
                    $y = 104;
                }
                $y = $this->drawPdfBlock($pdf, $y, $block);
            }
        }

        $this->drawPageFooter($pdf, $page);
        return $pdf->output();
    }

    private function drawPageHeader(PostulantSimplePdf $pdf, string $label, string $rightText): void
    {
        if ($this->logoPath !== '') {
            $pdf->image($this->logoPath, 36, 22, 42, 42);
        }
        $pdf->text(86, 50, 17, $this->pdfText($this->brandName), $this->muted, true);
        $pdf->text(559, 34, 9.5, $this->pdfText('INFORME'), $this->primary, true, 'regular', 'right');
        $pdf->text(559, 54, 9.5, $this->pdfText($rightText), $this->muted, false, 'regular', 'right');
    }

    private function drawIdentityCard(PostulantSimplePdf $pdf, float $y, array $candidate, array $process): float
    {
        $height = 83;
        $pdf->roundedBox(36, $y, 523, $height, [255, 255, 255], $this->primary, 1.2);
        $pdf->text(52, $y + 29, 18, $this->pdfText($this->stringValue($candidate['name'] ?? 'Evaluado')), $this->text, true);
        $rut = 'RUT: ' . $this->stringValue($candidate['rut'] ?? 'Sin dato');
        $pdf->text(52, $y + 52, 10.5, $this->pdfText($rut), $this->muted);
        $processName = $this->stringValue($process['name'] ?? $process['code'] ?? 'Proceso');
        $pdf->text(542, $y + 27, 9, $this->pdfText('PROCESO'), $this->muted, true, 'regular', 'right');
        $pdf->text(542, $y + 53, 13, $this->pdfText($processName), $this->accent, true, 'regular', 'right');
        return $y + $height + 24;
    }

    private function pdfBlocks(string $heading, string $markdown): array
    {
        $blocks = [];
        if ($heading !== '') {
            $blocks[] = ['type' => 'heading', 'text' => mb_strtoupper($heading)];
        }
        $lines = explode("\n", trim($markdown));
        $paragraph = [];
        $flushParagraph = static function () use (&$paragraph, &$blocks): void {
            if ($paragraph !== []) {
                $blocks[] = ['type' => 'paragraph', 'text' => trim(implode(' ', $paragraph))];
                $paragraph = [];
            }
        };
        foreach ($lines as $rawLine) {
            $line = trim($rawLine);
            $line = preg_replace('/^\*\*(.+?):\*\*\s*/', '$1: ', $line) ?? $line;
            $line = preg_replace('/^\*\*(.+?)\*\*:\s*/', '$1: ', $line) ?? $line;
            if ($line === '') {
                $flushParagraph();
                continue;
            }
            if (preg_match('/^###\s+(.+)$/', $line, $matches)) {
                $flushParagraph();
                $blocks[] = ['type' => 'subheading', 'text' => $matches[1]];
                continue;
            }
            if (preg_match('/^-\s+(.+)$/', $line, $matches)) {
                $flushParagraph();
                $last = count($blocks) - 1;
                if ($last >= 0 && ($blocks[$last]['type'] ?? '') === 'list') {
                    $blocks[$last]['items'][] = $matches[1];
                } else {
                    $blocks[] = ['type' => 'list', 'items' => [$matches[1]]];
                }
                continue;
            }
            if (preg_match('/^([^:]{2,60}):\s*(.+)$/', $line, $matches)) {
                $flushParagraph();
                $label = trim($matches[1]);
                $value = trim($matches[2]);
                $numeric = $this->numericMetricValue($value);
                $blocks[] = $numeric !== null
                    ? ['type' => 'metric', 'label' => $label, 'value' => $numeric, 'display' => $value]
                    : ['type' => 'kv', 'label' => $label, 'value' => $value];
                continue;
            }
            $paragraph[] = preg_replace('/\*\*(.+?)\*\*/', '$1', $line) ?? $line;
        }
        $flushParagraph();
        return $blocks;
    }

    private function blockHeight(array $block): float
    {
        $type = $block['type'] ?? '';
        if ($type === 'heading') {
            return 38;
        }
        if ($type === 'subheading') {
            return 27;
        }
        if ($type === 'kv') {
            return 34;
        }
        if ($type === 'metric') {
            return 92;
        }
        if ($type === 'list') {
            return 24 + (count($block['items'] ?? []) * 17);
        }
        $lines = $this->wrappedLines((string) ($block['text'] ?? ''), 82);
        return 34 + (count($lines) * 13);
    }

    private function drawPdfBlock(PostulantSimplePdf $pdf, float $y, array $block): float
    {
        $type = $block['type'] ?? '';
        if ($type === 'heading') {
            $pdf->text(36, $y + 16, 13, $this->pdfText((string) $block['text']), $this->primary, true);
            $pdf->line(36, $y + 24, 205, $y + 24, $this->primary, 1.2);
            return $y + 38;
        }
        if ($type === 'subheading') {
            $pdf->text(42, $y + 17, 11.5, $this->pdfText((string) $block['text']), $this->text, true);
            return $y + 27;
        }
        if ($type === 'kv') {
            $pdf->roundedBox(36, $y, 523, 27, [255, 255, 255], $this->border, 0.7);
            $pdf->text(49, $y + 18, 9.5, $this->pdfText((string) $block['label']), $this->muted, true);
            $pdf->text(210, $y + 18, 9.5, $this->pdfText((string) $block['value']), $this->text);
            return $y + 34;
        }
        if ($type === 'metric') {
            return $this->drawMetricCard($pdf, 36, $y, 523, $block) + 10;
        }
        if ($type === 'list') {
            $items = $block['items'] ?? [];
            $height = 20 + count($items) * 17;
            $pdf->roundedBox(36, $y, 523, $height, $this->light, $this->border, 0.7);
            $itemY = $y + 17;
            foreach ($items as $item) {
                $pdf->text(50, $itemY, 9.5, $this->pdfText('• ' . $item), $this->text);
                $itemY += 17;
            }
            return $y + $height + 7;
        }

        $lines = $this->wrappedLines((string) ($block['text'] ?? ''), 82);
        $height = 20 + count($lines) * 13;
        $pdf->fillRect(36, $y, 4, $height, $this->accent);
        $pdf->roundedBox(40, $y, 519, $height, $this->light, $this->light, 0.5);
        $lineY = $y + 17;
        foreach ($lines as $line) {
            $pdf->text(55, $lineY, 9.5, $this->pdfText($line), $this->text);
            $lineY += 13;
        }
        return $y + $height + 9;
    }

    private function numericMetricValue(string $value): ?float
    {
        $normalized = trim(str_replace(',', '.', $value));
        if (!is_numeric($normalized)) {
            return null;
        }
        $number = (float) $normalized;
        return $number >= 0 && $number <= 100 ? $number : null;
    }

    private function metricGridHeight(array $metrics): float
    {
        return 92 + (ceil(count($metrics) / 3) * 10);
    }

    private function drawMetricGrid(PostulantSimplePdf $pdf, float $y, array $metrics): float
    {
        $width = 165;
        $gap = 14;
        foreach (array_values($metrics) as $index => $metric) {
            $column = $index % 3;
            $row = intdiv($index, 3);
            $x = 36 + ($column * ($width + $gap));
            $cardY = $y + ($row * 102);
            $this->drawMetricCard($pdf, $x, $cardY, $width, $metric);
        }
        return $y + (ceil(count($metrics) / 3) * 102);
    }

    private function drawMetricCard(PostulantSimplePdf $pdf, float $x, float $y, float $width, array $metric): float
    {
        $value = max(0.0, min(100.0, (float) ($metric['value'] ?? 0)));
        $pdf->roundedBox($x, $y, $width, 82, [255, 255, 255], $this->border, 0.8);
        $label = $this->truncateText((string) ($metric['label'] ?? 'Indicador'), 27);
        $pdf->text($x + 10, $y + 19, 8.5, $this->pdfText($label), $this->muted, true);
        $pdf->text($x + 10, $y + 43, 18, $this->pdfText((string) ($metric['display'] ?? $value)), $this->primary, true);
        $barX = $x + 10;
        $barY = $y + 59;
        $barWidth = $width - 20;
        $pdf->roundedBox($barX, $barY, $barWidth, 8, $this->light, $this->light, 0.2);
        if ($value > 0) {
            $pdf->roundedBox($barX, $barY, max(2, $barWidth * ($value / 100)), 8, $this->accent, $this->accent, 0.2);
        }
        return 82;
    }

    private function truncateText(string $text, int $length): string
    {
        return mb_strlen($text) > $length ? mb_substr($text, 0, $length - 1) . '…' : $text;
    }

    private function drawPageFooter(PostulantSimplePdf $pdf, int $page): void
    {
        $pdf->line(36, 803, 559, 803, $this->border, 0.7);
        $pdf->text(36, 820, 8, $this->pdfText($this->brandName . ' | Informe generado por la plataforma'), $this->muted);
        $pdf->text(559, 820, 8, $this->pdfText('Página ' . $page), $this->muted, false, 'regular', 'right');
    }

    private function renderSections(array $sections, array $execution): array
    {
        $html = [];
        foreach ($sections as $section) {
            $heading = $this->escape((string) ($section['heading'] ?? ''));
            $markdown = $this->replaceTemplates((string) ($section['markdown'] ?? ''), $execution);
            $lines = explode("\n", trim($markdown));
            $metrics = [];
            $remaining = [];
            foreach ($lines as $line) {
                $clean = preg_replace('/^\*\*(.+?):\*\*\s*/', '$1: ', trim($line)) ?? trim($line);
                $clean = preg_replace('/^\*\*(.+?)\*\*:\s*/', '$1: ', $clean) ?? $clean;
                if (preg_match('/^([^:]{2,60}):\s*(.+)$/', $clean, $matches) && ($value = $this->numericMetricValue(trim($matches[2]))) !== null) {
                    $metrics[] = ['label' => trim($matches[1]), 'value' => $value, 'display' => trim($matches[2])];
                } else {
                    $remaining[] = $line;
                }
            }
            $metricHtml = '';
            if (count($metrics) >= 2) {
                $metricHtml = '<div class="metric-grid">';
                foreach ($metrics as $metric) {
                    $metricHtml .= '<article class="metric-card"><div class="metric-label">' . $this->escape($metric['label']) . '</div>'
                        . '<div class="metric-value">' . $this->escape($metric['display']) . '</div>'
                        . '<div class="metric-track"><span style="width:' . $this->escape((string) $metric['value']) . '%"></span></div></article>';
                }
                $metricHtml .= '</div>';
            } else {
                $remaining = $lines;
            }
            $html[] = '<section><h2>' . $heading . '</h2>' . $metricHtml . $this->markdownToHtml(implode("\n", $remaining)) . '</section>';
        }
        return $html;
    }

    private function markdownToHtml(string $markdown): string
    {
        $lines = explode("\n", trim($markdown));
        $html = '';
        $inList = false;
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') {
                if ($inList) {
                    $html .= '</ul>';
                    $inList = false;
                }
                continue;
            }
            if (strpos($line, '- ') === 0) {
                if (!$inList) {
                    $html .= '<ul>';
                    $inList = true;
                }
                $html .= '<li>' . $this->inlineMarkdown(substr($line, 2)) . '</li>';
                continue;
            }
            if ($inList) {
                $html .= '</ul>';
                $inList = false;
            }
            if (preg_match('/^###\s+(.+)$/', $line, $matches)) {
                $html .= '<h3>' . $this->inlineMarkdown($matches[1]) . '</h3>';
            } else {
                $html .= '<p>' . $this->inlineMarkdown($line) . '</p>';
            }
        }
        if ($inList) {
            $html .= '</ul>';
        }
        return $html;
    }

    private function inlineMarkdown(string $text): string
    {
        $text = $this->escape($text);
        return preg_replace('/\*\*(.+?)\*\*/', '<strong>$1</strong>', $text) ?? $text;
    }

    private function replaceTemplates(string $text, array $execution): string
    {
        $text = preg_replace_callback('/\{\{#each\s+([^}]+)\}\}(.*?)\{\{\/each\}\}/s', function (array $matches) use ($execution): string {
            $items = $this->valueForPath(trim($matches[1]), $execution);
            if (!is_array($items)) {
                return '';
            }
            $result = '';
            foreach ($items as $item) {
                $result .= preg_replace_callback('/\{\{\s*([^}]+)\s*\}\}/', function (array $variable) use ($item, $execution): string {
                    $path = trim($variable[1]);
                    return $this->stringValue($path === 'this' ? $item : $this->valueForPath($path, $item, $execution));
                }, $matches[2]) ?? '';
            }
            return $result;
        }, $text) ?? $text;

        return preg_replace_callback('~\{\{\s*([^#/{][^}]*)\s*\}\}~', function (array $matches) use ($execution): string {
            return $this->stringValue($this->valueForPath(trim($matches[1]), $execution));
        }, $text) ?? $text;
    }

    private function valueForPath(string $path, array $context, ?array $fallback = null)
    {
        if ($fallback !== null && array_key_exists($path, $fallback)) {
            return $fallback[$path];
        }
        if (isset($context['payload']) && is_array($context['payload'])) {
            $context = array_merge($context['payload'], $context);
        }
        if (isset($context['variables'][$path]['value'])) {
            return $context['variables'][$path]['value'];
        }
        $value = $context;
        foreach (explode('.', $path) as $part) {
            if (!is_array($value) || !array_key_exists($part, $value)) {
                return null;
            }
            $value = $value[$part];
        }
        return $value;
    }

    private function stringValue($value): string
    {
        if ($value === null || $value === '') {
            return 'Sin dato';
        }
        if (is_bool($value)) {
            return $value ? 'Sí' : 'No';
        }
        return is_scalar($value) ? (string) $value : 'Sin dato';
    }

    private function wrappedLines(string $text, int $width): array
    {
        $text = trim($text);
        if ($text === '') {
            return [];
        }

        $wrapped = wordwrap($text, $width, "\n", true);
        return $wrapped === '' ? [] : explode("\n", $wrapped);
    }

    private function pdfText(string $value): string
    {
        $converted = iconv('UTF-8', 'Windows-1252//TRANSLIT//IGNORE', $value);
        if ($converted !== false) {
            return $converted;
        }

        return preg_replace('/[^\x20-\x7E]/', '', $value) ?? $value;
    }

    private function humanizeTitle(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return 'Informe';
        }

        $value = preg_replace('/[_-]+/', ' ', $value) ?? $value;
        return mb_convert_case($value, MB_CASE_TITLE, 'UTF-8');
    }

    private function reportDate(string $generatedAt): string
    {
        $timestamp = strtotime($generatedAt);
        return $timestamp !== false ? date('d-m-Y', $timestamp) : date('d-m-Y');
    }

    private function brandName(array $design): string
    {
        $name = trim((string) ($design['topbar_name'] ?? ''));
        if ($name === '') {
            $name = trim((string) ($design['html_title'] ?? ''));
        }

        return $name !== '' ? $name : 'e-talent';
    }

    private function brandLogoPath(array $design, array $login): string
    {
        $candidates = [
            (string) ($design['topbar_icon_path'] ?? ''),
            (string) ($design['html_favicon_path'] ?? ''),
            (string) ($login['login_logo_path'] ?? ''),
        ];

        foreach ($candidates as $path) {
            $resolved = $this->publicPath($path);
            $extension = strtolower(pathinfo($resolved, PATHINFO_EXTENSION));
            if ($resolved !== '' && is_file($resolved) && $extension === 'png') {
                return $resolved;
            }
        }

        return '';
    }

    private function publicPath(string $path): string
    {
        $path = trim($path);
        if ($path === '') {
            return '';
        }

        if (substr($path, 0, 1) === '/') {
            return $path;
        }

        $base = defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__, 3);
        $relative = ltrim($path, '/');
        if (substr($relative, 0, 7) === 'public/') {
            return $base . '/' . $relative;
        }

        return $base . '/public/' . $relative;
    }

    private function hexToRgb(string $hex, array $fallback): array
    {
        $hex = trim($hex);
        if (!preg_match('/^#([0-9a-fA-F]{6})$/', $hex, $matches)) {
            return $fallback;
        }

        return [
            hexdec(substr($matches[1], 0, 2)),
            hexdec(substr($matches[1], 2, 2)),
            hexdec(substr($matches[1], 4, 2)),
        ];
    }

    private function mix(array $color, array $with, float $weight): array
    {
        $weight = max(0.0, min(1.0, $weight));
        return [
            (int) round(($color[0] * $weight) + ($with[0] * (1 - $weight))),
            (int) round(($color[1] * $weight) + ($with[1] * (1 - $weight))),
            (int) round(($color[2] * $weight) + ($with[2] * (1 - $weight))),
        ];
    }

    private function plainLines(array $sections, array $execution): array
    {
        $lines = [];
        foreach ($sections as $section) {
            $lines[] = strtoupper((string) ($section['heading'] ?? ''));
            $text = $this->replaceTemplates((string) ($section['markdown'] ?? ''), $execution);
            foreach (explode("\n", trim($text)) as $line) {
                $line = trim(preg_replace('/[*#]/', '', $line) ?? $line);
                if ($line !== '') {
                    $lines[] = $this->wrap($line, 92);
                }
            }
            $lines[] = '';
        }
        return array_merge(...array_map(static fn(string $line): array => $line === '' ? [''] : [$line], $lines));
    }

    private function wrap(string $line, int $width): string
    {
        return wordwrap($line, $width, "\n", true);
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    private function styles(): string
    {
        return 'body{font-family:Arial,sans-serif;background:#f6f8f4;color:#2e2e2e;margin:0}.report{max-width:900px;margin:32px auto;background:#fff;padding:40px;border:1px solid #d9d9d9}.eyebrow{color:#0b3d52;text-transform:uppercase;font-weight:bold;letter-spacing:.08em}.report-header{border-bottom:3px solid #92be2e;padding-bottom:20px;margin-bottom:28px}.report-header h1{margin:0 0 8px;font-size:30px}.meta,footer{color:#6e6e6e}section{margin:26px 0}h2{color:#0b3d52;border-bottom:1px solid #d9d9d9;padding-bottom:8px}h3{font-size:18px}p,li{line-height:1.55}.metric-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:14px;margin:16px 0}.metric-card{border:1px solid #d9d9d9;border-radius:8px;padding:14px;background:#fff;break-inside:avoid}.metric-label{color:#6e6e6e;font-size:12px;font-weight:bold;min-height:30px}.metric-value{color:#0b3d52;font-size:22px;font-weight:bold;margin:7px 0 10px}.metric-track{height:8px;border-radius:8px;background:#f6f8f4;overflow:hidden}.metric-track span{display:block;height:100%;border-radius:8px;background:#92be2e}footer{border-top:1px solid #d9d9d9;margin-top:36px;padding-top:16px;font-size:12px}@media(max-width:768px){.report{margin:0;padding:28px}.metric-grid{grid-template-columns:repeat(2,minmax(0,1fr))}}@media(max-width:576px){.report{padding:22px;border:0}.metric-grid{grid-template-columns:1fr}}';
    }
}
