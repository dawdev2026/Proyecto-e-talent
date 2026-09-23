<?php
declare(strict_types=1);

final class MoodleXmlEvaluationImportService
{
    private const QUIZZES = [
        1298 => ['title' => 'Test 1: Listening', 'kind' => 'listening'],
        1299 => ['title' => 'Test 2: Reading', 'kind' => 'reading'],
        1300 => ['title' => 'Test 3: Writing', 'kind' => 'writing'],
    ];

    public function importDrafts(string $xmlPath, ?int $userId, ?int $companyId = null): array
    {
        if (!class_exists('XMLReader')) throw new RuntimeException('La extensión XMLReader de PHP es obligatoria para importar este archivo.');
        if (!is_file($xmlPath) || !is_readable($xmlPath)) throw new InvalidArgumentException('No se encontró el XML de Moodle.');

        $grouped = [1298 => [], 1299 => [], 1300 => []];
        $category = '';
        $reader = new XMLReader();
        if (!$reader->open($xmlPath, null, LIBXML_NONET | LIBXML_NOCDATA | LIBXML_PARSEHUGE)) throw new RuntimeException('No se pudo abrir el XML de Moodle.');
        try {
            while ($reader->read()) {
                if ($reader->nodeType !== XMLReader::ELEMENT || $reader->name !== 'question') continue;
                $outer = $reader->readOuterXml();
                $node = simplexml_load_string($outer, 'SimpleXMLElement', LIBXML_NONET | LIBXML_NOCDATA | LIBXML_PARSEHUGE);
                if (!$node) continue;
                $type = strtolower((string) ($node['type'] ?? ''));
                if ($type === 'category') { $category = trim((string) ($node->category->text ?? '')); continue; }
                $question = $this->parseQuestion($node, $category);
                foreach ($this->targetQuizIds($category, $question) as $quizId) $grouped[$quizId][] = $question;
            }
        } finally { $reader->close(); }

        $model = new EvaluationSurveyFormModel();
        $created = [];
        foreach (self::QUIZZES as $quizId => $definition) {
            $questions = $grouped[$quizId];
            if (!$questions) throw new RuntimeException('No se encontraron preguntas XML para ' . $definition['title'] . '.');
            $total = 0.0;
            foreach ($questions as $question) $total += (float) ($question['points'] ?? 0);
            $payload = [
                'title' => $definition['title'] . ' [Moodle ' . $quizId . ']',
                'description' => 'Borrador importado desde Moodle. Curso 332; cuestionario ' . $quizId . '.',
                'instructions' => 'Revisar la asociación de categorías, escala de aprobación, orden aleatorio y contenido antes de activar. La asociación Listening/Reading se determinó por categorías del XML y requiere validación funcional.',
                'max_score' => max(1, $total),
                'passing_score' => null,
                'question_display_limit' => 0,
                'questions' => array_values($questions),
                'source' => ['provider' => 'moodle_xml', 'course_id' => 332, 'quiz_id' => $quizId],
            ];
            if ($companyId !== null) $payload['company_id'] = max(0, $companyId);
            $created[$quizId] = ['form_id' => $model->createFromMoodle($payload, $userId), 'questions' => count($questions), 'media' => array_sum(array_map(static fn(array $q): int => count((array) ($q['media'] ?? [])), $questions))];
        }
        return $created;
    }

    private function targetQuizIds(string $category, array $question): array
    {
        $category = strtoupper(trim($category));
        if (strpos($category, '/') !== false) $category = strtoupper(trim((string) substr($category, strrpos($category, '/') + 1)));
        if ($category === 'TAE WRITING TOPICS') return [1300];
        if ($category !== '' && preg_match('/^TAE\s+(CAA|CAB|CAC)\d+[ELF]$/', $category) && !empty($question['media'])) return [1298];
        if (in_array($category, ['TAE CLC1P', 'TAE CLC2P'], true)) return [1299];
        return [];
    }

    private function parseQuestion(SimpleXMLElement $node, string $category): array
    {
        $type = strtolower((string) ($node['type'] ?? 'multichoice'));
        $name = trim((string) ($node->name->text ?? 'Pregunta Moodle'));
        $text = trim((string) ($node->questiontext->text ?? ''));
        $media = [];
        foreach ($node->questiontext->file as $file) {
            $nameFile = trim((string) ($file['name'] ?? ''));
            $encoded = trim((string) $file);
            if ($nameFile === '' || $encoded === '') continue;
            $safe = preg_replace('/[^A-Za-z0-9._-]/', '_', basename($nameFile)) ?: 'audio.mp3';
            $tmp = tempnam(sys_get_temp_dir(), 'moodle-audio-');
            $decoded = base64_decode($encoded, true);
            if ($tmp === false || $decoded === false || file_put_contents($tmp, $decoded, LOCK_EX) === false) { if ($tmp && is_file($tmp)) @unlink($tmp); continue; }
            $media[] = ['source_path' => $tmp, 'original_name' => $safe, 'media_type' => 'audio', 'mime_type' => 'audio/mpeg'];
            $text = str_replace(['@@PLUGINFILE@@/' . $nameFile, '@@PLUGINFILE@@/' . $safe], '', $text);
        }
        $text = $this->cleanQuestionText($text, $media, $name);
        $points = is_numeric((string) ($node->defaultgrade ?? '')) ? (float) $node->defaultgrade : 1.0;
        $mappedType = $type === 'essay' ? 'text' : ($type === 'truefalse' ? 'true_false' : 'single_choice');
        $options = [];
        $correctCount = 0;
        foreach ($node->answer as $index => $answer) {
            $fractionValue = trim((string) ($answer['fraction'] ?? ''));
            $fraction = is_numeric($fractionValue) ? (float) $fractionValue : 0.0;
            if ($fraction > 0) $correctCount++;
            $options[] = ['label' => $this->cleanOptionText((string) ($answer->text ?? '')), 'value' => (string) ((int) $index + 1), 'score' => $fraction > 0 ? $points : 0];
        }
        if ($mappedType === 'single_choice' && $correctCount > 1) $mappedType = 'multiple_choice';
        return ['question_text' => $text, 'question_type' => $mappedType, 'points' => max(0, $points), 'is_required' => 1, 'is_active' => 1, 'needs_review' => 1, 'evidence' => 'Moodle XML; categoría ' . $category . '; pregunta ' . $name, 'options' => $options, 'media' => $media];
    }

    private function cleanQuestionText(string $value, array $media, string $fallback): string
    {
        $html = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $html = preg_replace('/<a\b[^>]*>(.*?)<\/a>/is', '$1', $html) ?? $html;
        foreach ($media as $item) {
            $name = trim((string) ($item['original_name'] ?? ''));
            if ($name !== '') $html = preg_replace('/' . preg_quote($name, '/') . '/i', '', $html) ?? $html;
        }
        $plain = trim(preg_replace('/\s+/u', ' ', strip_tags($html)) ?? '');
        if ($media && $plain === '') return 'Escucha el audio y responde.';
        return trim($html) !== '' ? trim($html) : $fallback;
    }

    private function cleanOptionText(string $value): string
    {
        $text = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = strip_tags($text);
        $text = str_replace("\xC2\xA0", ' ', $text);
        return trim(preg_replace('/\s+/u', ' ', $text) ?? '');
    }
}
