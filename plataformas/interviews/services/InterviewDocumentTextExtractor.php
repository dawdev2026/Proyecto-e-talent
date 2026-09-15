<?php
declare(strict_types=1);

final class InterviewDocumentTextExtractor
{
    public function extract(string $path, string $mime): array
    {
        if (in_array($mime, ['text/plain', 'text/csv', 'text/markdown', 'application/json'], true)) {
            return ['status' => 'ready', 'text' => mb_substr((string) file_get_contents($path), 0, 100000), 'error' => ''];
        }
        if ($mime === 'application/pdf') return $this->extractPdf($path);
        if (!class_exists('ZipArchive') || !in_array($mime, ['application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'], true)) {
            return ['status' => 'unsupported', 'text' => '', 'error' => 'Formato registrado; falta extractor disponible para análisis automático.'];
        }
        $zip = new ZipArchive();
        if ($zip->open($path) !== true) return ['status' => 'failed', 'text' => '', 'error' => 'No se pudo abrir el documento Office.'];
        $xml = $mime === 'application/vnd.openxmlformats-officedocument.wordprocessingml.document' ? $zip->getFromName('word/document.xml') : $this->spreadsheetXml($zip);
        $zip->close();
        if (!is_string($xml) || trim($xml) === '') return ['status' => 'failed', 'text' => '', 'error' => 'El documento no contiene texto extraíble.'];
        $dom = new DOMDocument();
        $dom->loadXML($xml, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
        $text = trim(preg_replace('/\s+/u', ' ', (string) $dom->textContent));
        return $text === '' ? ['status' => 'failed', 'text' => '', 'error' => 'El documento no contiene texto extraíble.'] : ['status' => 'ready', 'text' => mb_substr($text, 0, 100000), 'error' => ''];
    }

    private function extractPdf(string $path): array
    {
        if (class_exists('Smalot\\PdfParser\\Parser')) {
            try {
                $pdf = (new \Smalot\PdfParser\Parser())->parseFile($path);
                $text = trim((string) $pdf->getText());
                if ($text !== '') return ['status' => 'ready', 'text' => mb_substr($text, 0, 100000), 'error' => ''];
            } catch (Throwable $exception) {
                return ['status' => 'failed', 'text' => '', 'error' => $exception->getMessage()];
            }
        }
        return ['status' => 'unsupported', 'text' => '', 'error' => 'PDF sin texto extraíble; OCR no disponible sin ejecutar procesos del sistema.'];
    }

    private function spreadsheetXml(ZipArchive $zip): string
    {
        $shared = (string) $zip->getFromName('xl/sharedStrings.xml');
        $sheet = (string) $zip->getFromName('xl/worksheets/sheet1.xml');
        return '<root>' . preg_replace('/<\?xml[^>]*>/', '', $shared) . preg_replace('/<\?xml[^>]*>/', '', $sheet) . '</root>';
    }
}
