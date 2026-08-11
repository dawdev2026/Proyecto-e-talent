<?php
declare(strict_types=1);

final class InterviewDocumentTextExtractor
{
    public function extract(string $path, string $mime): array
    {
        if (in_array($mime, ['text/plain', 'text/csv', 'text/markdown', 'application/json'], true)) {
            return ['status' => 'ready', 'text' => mb_substr((string) file_get_contents($path), 0, 100000), 'error' => ''];
        }

        if ($mime === 'application/pdf') {
            return $this->extractPdf($path);
        }

        if (!class_exists('ZipArchive') || !in_array($mime, [
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ], true)) {
            return ['status' => 'unsupported', 'text' => '', 'error' => 'Formato registrado; falta extractor disponible para análisis automático.'];
        }

        $zip = new ZipArchive();
        if ($zip->open($path) !== true) {
            return ['status' => 'failed', 'text' => '', 'error' => 'No se pudo abrir el documento Office.'];
        }

        $xml = $mime === 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
            ? $zip->getFromName('word/document.xml')
            : $this->spreadsheetXml($zip);
        $zip->close();
        if (!is_string($xml) || trim($xml) === '') {
            return ['status' => 'failed', 'text' => '', 'error' => 'El documento no contiene texto extraíble.'];
        }

        $dom = new DOMDocument();
        $dom->loadXML($xml, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
        $text = trim(preg_replace('/\s+/u', ' ', (string) $dom->textContent));
        return $text === ''
            ? ['status' => 'failed', 'text' => '', 'error' => 'El documento no contiene texto extraíble.']
            : ['status' => 'ready', 'text' => mb_substr($text, 0, 100000), 'error' => ''];
    }

    private function extractPdf(string $path): array
    {
        if (class_exists('Smalot\\PdfParser\\Parser')) {
            try {
                $pdf = (new \Smalot\PdfParser\Parser())->parseFile($path);
                $text = trim((string) $pdf->getText());
                if ($text !== '') {
                    return ['status' => 'ready', 'text' => mb_substr($text, 0, 100000), 'error' => ''];
                }
            } catch (Throwable $exception) {
                $directError = $exception->getMessage();
            }
        }

        if (!function_exists('exec') || !is_executable((string) trim((string) shell_exec('command -v pdftoppm 2>/dev/null')))) {
            return ['status' => 'unsupported', 'text' => '', 'error' => 'PDF sin texto y OCR no disponible en el contenedor.'];
        }

        $workDir = rtrim(sys_get_temp_dir(), '/') . '/metricatest-ocr-' . bin2hex(random_bytes(8));
        if (!mkdir($workDir, 0700, true)) {
            return ['status' => 'failed', 'text' => '', 'error' => 'No se pudo preparar el procesamiento OCR.'];
        }

        $prefix = $workDir . '/page';
        $command = 'pdftoppm -f 1 -l 10 -png -r 150 ' . escapeshellarg($path) . ' ' . escapeshellarg($prefix) . ' 2>&1';
        exec($command, $output, $exitCode);
        $images = glob($prefix . '-*.png') ?: [];
        $parts = [];
        if ($exitCode === 0 && class_exists('\\thiagoalessio\\TesseractOCR\\TesseractOCR')) {
            foreach ($images as $image) {
                try {
                    $parts[] = (new \thiagoalessio\TesseractOCR\TesseractOCR($image))
                        ->lang('spa', 'eng')
                        ->psm(6)
                        ->run(120);
                } catch (Throwable $exception) {
                    $parts[] = '';
                }
                @unlink($image);
            }
        }
        @rmdir($workDir);
        $text = trim(implode("\n", $parts));
        if ($text !== '') {
            return ['status' => 'ready', 'text' => mb_substr($text, 0, 100000), 'error' => ''];
        }

        return ['status' => 'failed', 'text' => '', 'error' => $directError ?? 'No se pudo extraer texto del PDF mediante OCR.'];
    }

    private function spreadsheetXml(ZipArchive $zip): string
    {
        $shared = (string) $zip->getFromName('xl/sharedStrings.xml');
        $sheet = (string) $zip->getFromName('xl/worksheets/sheet1.xml');
        return '<root>' . preg_replace('/<\?xml[^>]*>/', '', $shared) . preg_replace('/<\?xml[^>]*>/', '', $sheet) . '</root>';
    }
}
