<?php
declare(strict_types=1);

final class InterviewFinalReportPdfService
{
    private array $primary;
    private array $text;
    private array $muted;
    private array $surface;
    private string $brandName;

    public function __construct(?PlatformSettingsModel $settings = null)
    {
        $design = ($settings ?: new PlatformSettingsModel())->designSettings();
        $this->primary = $this->hexToRgb((string) ($design['app_primary_color'] ?? ''), [0, 40, 60]);
        $this->text = $this->hexToRgb((string) ($design['portal_text_color'] ?? ''), [46, 46, 46]);
        $this->muted = $this->mix($this->text, [255, 255, 255], 0.58);
        $this->surface = $this->hexToRgb((string) ($design['card_content_background_color'] ?? ''), [255, 255, 255]);
        $this->brandName = trim((string) ($design['topbar_name'] ?? 'e-talent')) ?: 'e-talent';
    }

    public function render(array $appointment, string $html): string
    {
        $pdf = new PostulantSimplePdf();
        $pdf->addPage();
        $pdf->text(36, 44, 11, $this->brandName, $this->muted, true);
        $pdf->text(36, 76, 24, 'Reporte final de entrevista', $this->primary, true);
        $pdf->text(36, 101, 12, (string) ($appointment['candidate_name'] ?? 'Postulante'), $this->text, true);
        $pdf->text(36, 119, 10, 'Proceso: ' . (string) ($appointment['process_name'] ?? '-'), $this->muted);
        $pdf->line(36, 137, 559, 137, $this->primary, 1.8);

        $plain = $this->plainText($html);
        $chunks = $this->paragraphs($plain);
        $y = 165.0;
        foreach ($chunks as $paragraph) {
            if ($y > 770) {
                $pdf->addPage();
                $y = 50.0;
            }
            $pdf->wrappedText(36, $y, 10.2, $paragraph, 520, 14, $this->text);
            $y += max(24, ceil(strlen($paragraph) / 92) * 14 + 8);
        }

        return $pdf->output();
    }

    public function filename(array $appointment): string
    {
        $candidate = $this->slug((string) ($appointment['candidate_name'] ?? 'postulante'));

        return 'reporte_final_entrevista_' . $candidate . '.pdf';
    }

    private function paragraphs(string $text): array
    {
        $parts = preg_split('/\n{2,}/', trim($text)) ?: [];
        $parts = array_values(array_filter(array_map('trim', $parts)));

        return $parts ?: ['Reporte final sin contenido disponible.'];
    }

    private function plainText(string $html): string
    {
        $html = preg_replace('/<\/(p|div|section|article|dl|h[1-6]|li)>/i', "\n\n", $html) ?? $html;
        $html = preg_replace('/<\/(dt|dd)>/i', "\n", $html) ?? $html;
        $html = preg_replace('/<br\s*\/?>/i', "\n", $html) ?? $html;

        return html_entity_decode(trim(strip_tags($html)), ENT_QUOTES, 'UTF-8');
    }

    private function hexToRgb(string $hex, array $fallback): array
    {
        $hex = ltrim(trim($hex), '#');
        if (strlen($hex) === 3) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }
        if (!preg_match('/^[0-9a-fA-F]{6}$/', $hex)) {
            return $fallback;
        }

        return [hexdec(substr($hex, 0, 2)), hexdec(substr($hex, 2, 2)), hexdec(substr($hex, 4, 2))];
    }

    private function mix(array $a, array $b, float $ratio): array
    {
        return [
            (int) round($a[0] * (1 - $ratio) + $b[0] * $ratio),
            (int) round($a[1] * (1 - $ratio) + $b[1] * $ratio),
            (int) round($a[2] * (1 - $ratio) + $b[2] * $ratio),
        ];
    }

    private function slug(string $value): string
    {
        $value = strtolower(trim(iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value) ?: $value));
        $value = preg_replace('/[^a-z0-9]+/', '_', $value) ?? '';

        return trim($value, '_') ?: 'postulante';
    }
}
