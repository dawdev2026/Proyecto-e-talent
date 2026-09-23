<?php
declare(strict_types=1);

use Smalot\PdfParser\Parser;

final class EvaluationSurveyAiService
{
    private EvaluationSurveySettingsModel $moduleSettings;
    private EvaluationSurveyFormModel $forms;
    private PlatformSettingsModel $platformSettings;

    public function __construct(?EvaluationSurveySettingsModel $moduleSettings = null, ?EvaluationSurveyFormModel $forms = null, ?PlatformSettingsModel $platformSettings = null)
    {
        $this->moduleSettings = $moduleSettings ?: new EvaluationSurveySettingsModel();
        $this->forms = $forms ?: new EvaluationSurveyFormModel();
        $this->platformSettings = $platformSettings ?: new PlatformSettingsModel();
    }

    public function generateFromPdf(string $path, string $mode, string $formType, array $options, ?int $userId): array
    {
        $startedAt = microtime(true);
        $settings = $this->moduleSettings->aiSettings();
        if ($settings['enabled'] !== '1') return ['ok' => false, 'message' => 'La función de IA está deshabilitada para esta sub-plataforma.'];
        if (!in_array($mode, ['import', 'generate'], true) || !isset(EvaluationSurveyFormModel::FORM_TYPES[$formType])) return ['ok' => false, 'message' => 'Parámetros de generación inválidos.'];
        $signature = is_file($path) ? (string) file_get_contents($path, false, null, 0, 5) : '';
        if (!is_file($path) || $signature !== '%PDF-') return ['ok' => false, 'message' => 'Debes cargar un archivo PDF válido.'];
        if (filesize($path) > ((int) $settings['max_pdf_mb'] * 1048576)) return ['ok' => false, 'message' => 'El PDF supera el tamaño máximo configurado.'];
        try { $pdf = $this->extractPdf($path, (int) $settings['max_pdf_text_chars'], $mode === 'import'); }
        catch (Throwable $exception) { return ['ok' => false, 'code' => $mode === 'import' ? 'pdf_import_error' : 'ai_error', 'message' => $exception->getMessage()]; }
        if ($pdf['pages'] > (int) $settings['max_pdf_pages']) return ['ok' => false, 'message' => 'El PDF contiene ' . $pdf['pages'] . ' páginas y supera el perfil configurado de ' . (int) $settings['max_pdf_pages'] . ' páginas.'];
        $source = $pdf['text'];
        $analysis = $this->analyzeSource($source);
        if (!empty($pdf['highlighted_choices'])) $analysis['has_correct_answers'] = true;
        if (($mode === 'import' ? $settings['import_enabled'] : $settings['generation_enabled']) !== '1') return ['ok' => false, 'message' => 'La operación detectada está deshabilitada para esta sub-plataforma.'];
        if ($formType === 'assessment' && $mode === 'import' && empty($analysis['has_correct_answers'])) {
            return ['ok' => false, 'code' => 'pdf_import_error', 'message' => 'No se identificaron respuestas correctas en el PDF. Carga un PDF válido con una clave textual o alternativas claramente destacadas.'];
        }
        if ($mode === 'import') {
            return $this->importPdfInternally($pdf, $source, $formType, $options, (int) $userId, $settings, $analysis, $startedAt);
        }
        $config = $this->platformSettings->aiSettings(load_config('integrations'), true);
        if (empty($config['enabled']) || empty($config['api_key'])) return ['ok' => false, 'message' => 'La IA transversal no está habilitada o no tiene credencial configurada.'];
        $maxQuestions = $mode === 'import'
            ? max(1, (int) $settings['max_questions'])
            : min((int) $settings['max_questions'], max(1, (int) ($options['question_count'] ?? 10)));
        $allowedTypes = $formType === 'survey'
            ? ['single_choice', 'multiple_choice', 'likert', 'nps', 'rating', 'matrix', 'text']
            : array_keys(EvaluationSurveyFormModel::QUESTION_TYPES);
        $modeInstruction = $mode === 'import'
            ? 'TRASPASO FIEL. Tu objetivo principal es entregar información exacta, verificable y fiel a los datos proporcionados. Reglas obligatorias: 1) No inventes datos, cifras, nombres, fechas, porcentajes, resultados ni antecedentes. 2) No completes información faltante mediante suposiciones. 3) Si un dato no está disponible, indica exactamente "Información no disponible". 4) Si existen datos contradictorios, informa la contradicción y no elijas arbitrariamente una versión. 5) Mantén exactamente cifras, unidades, fechas, nombres propios y categorías presentes en la fuente. 6) Distingue dato explícito, inferencia y estimación. 7) No presentes una inferencia como hecho. 8) Evita creatividad, adornos, interpretaciones o reformulaciones que alteren el significado. 9) Prioriza exactitud por sobre fluidez. 10) Comprueba internamente la consistencia y que los datos coincidan con la entrada. 11) Limita la respuesta exclusivamente a los datos encontrados. 12) No calcules ni transformes valores; conserva los proporcionados. 13) Si no puedes determinar una respuesta con suficiente certeza, indícalo en lugar de adivinar. 14) Respeta estrictamente el formato JSON solicitado. 15) No agregues información externa. Extrae todas las preguntas, alternativas y respuestas explícitas. No generes, completes, corrijas, resumas, traduzcas, reordenes ni reformules el contenido.'
            : 'Genera preguntas nuevas basadas únicamente en el contenido. Distribuye las preguntas entre las secciones disponibles y evita repetir el mismo concepto.';
        $questionInstruction = $mode === 'import' ? 'Devuelve todas las preguntas encontradas en el PDF; el total lo determina el documento.' : 'Genera hasta ' . $maxQuestions . ' preguntas.';
        $system = 'Eres un asistente experto en encuestas y evaluaciones. Devuelve exclusivamente JSON válido, sin markdown. '
            . 'Tipo de formulario: ' . $formType . '. Modo: ' . $mode . '. ' . $modeInstruction . ' '
            . $questionInstruction . ' ' . ($mode === 'generate' ? 'Dificultad: ' . ($options['difficulty'] ?? $settings['default_difficulty']) . '. ' : '')
            . ($mode === 'import' ? 'Si el documento no contiene un título explícito, devuelve title vacío; no inventes ni derives un título desde el contenido. ' : '')
            . 'Tipos permitidos: ' . implode(', ', $allowedTypes) . '. Para assessment, las alternativas correctas deben tener score igual a points y las incorrectas 0. Para survey, score debe ser null. '
            . 'Cada pregunta debe incluir source_pages con números de página y evidence de máximo 25 palabras, verificable en el documento. En traspaso conserva todas las alternativas explícitas; en generación usa entre 3 y 5 alternativas. En traspaso, si se entrega una evidencia de resaltado visual, úsala solo para identificar la alternativa marcada y verifica que coincida con el texto de la fuente. No inventes hechos, respuestas ni alternativas. '
            . 'Estructura exacta: {"title":"","description":"","instructions":"","questions":[{"question_text":"","question_type":"single_choice","is_required":1,"points":1,"needs_review":0,"source_pages":[1],"evidence":"","options":[{"label":"","value":"","score":0}]}]}';
        $aiSource = $source;
        if (!empty($pdf['highlighted_choices'])) {
            $aiSource .= "\n\n[RESALTADOS VISUALES DETECTADOS EN EL PDF — evidencia auxiliar, no inventar datos]\n";
            foreach ($pdf['highlighted_choices'] as $choice) {
                $aiSource .= '[PÁGINA ' . (int) ($choice['page'] ?? 0) . '] Alternativa ' . (string) ($choice['letter'] ?? '?') . ': ' . (string) ($choice['text'] ?? '') . "\n";
            }
        }
        $response = $this->request($config, $system, $aiSource, max(3500, min(16000, $maxQuestions * 450)), $mode === 'import');
        if (!$response['ok']) return $response;
        $draft = $this->decodeJson($response['text']);
        if (!is_array($draft) || !is_array($draft['questions'] ?? null) || !$draft['questions']) return ['ok' => false, 'message' => 'La IA no devolvió preguntas utilizables.'];
        $draftValidation = $this->validateDraft($draft, $formType, $mode, $maxQuestions, (int) $pdf['pages'], $analysis, $source);
        if (!$draftValidation['ok']) return $draftValidation;
        $questions = $draftValidation['questions'];
        if ($mode === 'import' && !empty($pdf['highlighted_choices'])) {
            $highlightValidation = $this->applyHighlightedAnswers($questions, $pdf['highlighted_choices']);
            if (!$highlightValidation['ok']) return $highlightValidation;
            $questions = $highlightValidation['questions'];
        }
        if ($formType === 'assessment' && $mode === 'import') {
            $answerValidation = $this->validateImportedAnswers($questions);
            if (!$answerValidation['ok']) return $answerValidation;
        }
        $title = trim((string) ($draft['title'] ?? ''));
        if ($title === '') $title = $mode === 'import' ? $this->pdfFilenameTitle((string) ($options['original_filename'] ?? '')) : 'Formulario generado desde PDF';
        $formData = ['form_type' => $formType, 'title' => mb_substr($title, 0, 180), 'description' => mb_substr(trim((string) ($draft['description'] ?? '')), 0, 12000), 'instructions' => mb_substr(trim((string) ($draft['instructions'] ?? '')), 0, 12000), 'status' => 'draft', 'max_score' => 100, 'passing_score' => 70, 'show_result_to_user' => 1, 'is_required' => 1];
        $formId = $this->forms->saveForm(0, $formData, $userId);
        $created = 0;
        $warnings = [];
        foreach ($questions as $index => $question) {
            $type = (string) $question['question_type'];
            $payload = ['question_text' => mb_substr(trim((string) ($question['question_text'] ?? '')), 0, 2000), 'question_type' => $type, 'is_required' => !empty($question['is_required']) ? 1 : 0, 'is_active' => 1, 'points' => max(0, (float) ($question['points'] ?? 1)), 'sort_order' => ($index + 1) * 10, 'source_pages' => (array) ($question['source_pages'] ?? []), 'evidence' => (string) ($question['evidence'] ?? ''), 'needs_review' => 1, 'option_label' => [], 'option_value' => [], 'option_correct' => []];
            $correctPositions = [];
            foreach ((array) ($question['options'] ?? []) as $position => $option) {
                $label = mb_substr(trim((string) ($option['label'] ?? '')), 0, 500);
                if ($label === '') continue;
                $payload['option_label'][(string) $position] = $label;
                $payload['option_value'][(string) $position] = mb_substr(trim((string) ($option['value'] ?? $label)), 0, 500);
                if ($formType === 'assessment' && (float) ($option['score'] ?? 0) > 0) $correctPositions[] = (string) $position;
            }
            if ($type === 'single_choice' && $correctPositions) $payload['option_correct_single'] = $correctPositions[0];
            if ($type === 'multiple_choice') $payload['option_correct'] = $correctPositions;
            if ($type === 'true_false') $payload['true_false_correct'] = $correctPositions[0] ?? 'true';
            if ($payload['question_text'] === '') continue;
            try { $this->forms->saveQuestion($formId, 0, $payload); $created++; }
            catch (Throwable $exception) {
                $this->forms->deleteForm($formId);
                return ['ok' => false, 'message' => 'No se pudo guardar la pregunta ' . ((int) $index + 1) . '. No se guardó el borrador para evitar un resultado incompleto. Inténtalo nuevamente.'];
            }
        }
        if ($created === 0) { $this->forms->deleteForm($formId); return ['ok' => false, 'message' => 'No se pudieron convertir preguntas del PDF.']; }
        return ['ok' => true, 'form_id' => $formId, 'questions' => $created, 'requires_review' => true, 'warnings' => $warnings, 'metrics' => [
            'pdf_hash' => $pdf['hash'], 'pages' => $pdf['pages'], 'source_chars' => $pdf['chars'],
            'questions_requested' => $maxQuestions, 'questions_created' => $created,
            'elapsed_ms' => (int) round((microtime(true) - $startedAt) * 1000),
        ]];
    }

    private function importPdfInternally(array $pdf, string $source, string $formType, array $options, int $userId, array $settings, array $analysis, float $startedAt): array
    {
        $parsed = $this->parseImportedQuestions($source, (int) $pdf['pages']);
        if (!$parsed['ok']) return ['ok' => false, 'code' => 'pdf_import_error', 'message' => $parsed['message']];
        $questions = $parsed['questions'];
        if (!empty($pdf['highlighted_choices'])) {
            $highlightValidation = $this->applyHighlightedAnswers($questions, $pdf['highlighted_choices']);
            if (!$highlightValidation['ok']) return ['ok' => false, 'code' => 'pdf_import_error', 'message' => $highlightValidation['message']];
            $questions = $highlightValidation['questions'];
        }
        $draft = ['title' => '', 'description' => '', 'instructions' => '', 'questions' => $questions];
        $maxQuestions = max(1, (int) $settings['max_questions']);
        $draftValidation = $this->validateDraft($draft, $formType, 'import', $maxQuestions, (int) $pdf['pages'], $analysis, $source);
        if (!$draftValidation['ok']) return ['ok' => false, 'code' => 'pdf_import_error', 'message' => $draftValidation['message']];
        $questions = $draftValidation['questions'];
        if ($formType === 'assessment') {
            $answerValidation = $this->validateImportedAnswers($questions);
            if (!$answerValidation['ok']) return ['ok' => false, 'code' => 'pdf_import_error', 'message' => $answerValidation['message'] . ' El PDF debe incluir una clave textual o una alternativa claramente destacada.'];
        }
        $title = $this->pdfFilenameTitle((string) ($options['original_filename'] ?? ''));
        $formId = $this->forms->saveForm(0, ['form_type' => $formType, 'title' => $title, 'description' => '', 'instructions' => '', 'status' => 'draft', 'max_score' => 100, 'passing_score' => 70, 'show_result_to_user' => 1, 'is_required' => 1], $userId);
        $created = 0;
        foreach ($questions as $index => $question) {
            $type = (string) ($question['question_type'] ?? 'single_choice');
            $payload = ['question_text' => mb_substr(trim((string) ($question['question_text'] ?? '')), 0, 2000), 'question_type' => $type, 'is_required' => !empty($question['is_required']) ? 1 : 0, 'is_active' => 1, 'points' => max(0, (float) ($question['points'] ?? 1)), 'sort_order' => ($index + 1) * 10, 'source_pages' => (array) ($question['source_pages'] ?? []), 'evidence' => (string) ($question['evidence'] ?? ''), 'needs_review' => 1, 'option_label' => [], 'option_value' => [], 'option_correct' => []];
            $correctPositions = [];
            foreach ((array) ($question['options'] ?? []) as $position => $option) {
                $label = mb_substr(trim((string) ($option['label'] ?? '')), 0, 500);
                if ($label === '') continue;
                $payload['option_label'][(string) $position] = $label;
                $payload['option_value'][(string) $position] = $label;
                if ($formType === 'assessment' && (float) ($option['score'] ?? 0) > 0) $correctPositions[] = (string) $position;
            }
            if ($type === 'single_choice' && $correctPositions) $payload['option_correct_single'] = $correctPositions[0];
            if ($type === 'multiple_choice') $payload['option_correct'] = $correctPositions;
            if ($payload['question_text'] === '') continue;
            try { $this->forms->saveQuestion($formId, 0, $payload); $created++; }
            catch (Throwable $exception) { $this->forms->deleteForm($formId); return ['ok' => false, 'code' => 'pdf_import_error', 'message' => 'No se pudo guardar la pregunta ' . ((int) $index + 1) . '.']; }
        }
        if ($created === 0) { $this->forms->deleteForm($formId); return ['ok' => false, 'code' => 'pdf_import_error', 'message' => 'No se encontraron preguntas legibles en el PDF.']; }
        return ['ok' => true, 'form_id' => $formId, 'questions' => $created, 'requires_review' => true, 'warnings' => [], 'metrics' => ['pdf_hash' => $pdf['hash'], 'pages' => $pdf['pages'], 'source_chars' => $pdf['chars'], 'questions_requested' => $maxQuestions, 'questions_created' => $created, 'elapsed_ms' => (int) round((microtime(true) - $startedAt) * 1000)]];
    }

    private function parseImportedQuestions(string $source, int $pageCount): array
    {
        $questions = [];
        $current = null;
        $currentOption = null;
        $page = 1;
        $flush = function () use (&$questions, &$current, &$currentOption): void {
            if (!is_array($current)) return;
            $text = $this->normalizeComparableText((string) ($current['question_text'] ?? ''));
            $options = [];
            foreach ((array) ($current['options'] ?? []) as $option) {
                $label = $this->normalizeComparableText((string) ($option['label'] ?? ''));
                if ($label !== '') $options[] = ['label' => $label, 'value' => $label, 'score' => 0];
            }
            if ($text !== '' && count($options) >= 2) {
                $correctLetter = strtoupper((string) ($current['correct_letter'] ?? ''));
                if ($correctLetter !== '' && ord($correctLetter) >= ord('A') && ord($correctLetter) <= ord('A') + count($options) - 1) $options[ord($correctLetter) - ord('A')]['score'] = 1;
                $questions[] = ['question_text' => $text, 'question_type' => 'single_choice', 'is_required' => 1, 'points' => 1, 'source_pages' => array_values(array_unique(array_map('intval', (array) ($current['source_pages'] ?? [$current['page'] ?? 1])))), 'evidence' => mb_substr($text, 0, 250), 'options' => $options];
            }
            $current = null; $currentOption = null;
        };
        foreach (preg_split('/\R/u', $source) ?: [] as $rawLine) {
            $line = trim((string) $rawLine);
            if ($line === '') continue;
            if (preg_match('/^\[PÁGINA\s+(\d+)\]$/u', $line, $match)) { $page = max(1, min($pageCount, (int) $match[1])); if (is_array($current)) $current['source_pages'][] = $page; continue; }
            if (preg_match('/^(?:ALTERNATIVA|RESPUESTA)\s+CORRECTA\s*[:=\-]?\s*([A-H])\b/iu', $line, $match)) { if (is_array($current)) $current['correct_letter'] = strtoupper($match[1]); continue; }
            if (preg_match('/^(\d{1,3})[\.)\:\-]\s*(\S.*)$/u', $line, $match)) { $flush(); $current = ['question_text' => $match[2], 'options' => [], 'page' => $page, 'source_pages' => [$page]]; $currentOption = null; continue; }
            if (preg_match('/^([A-Ha-h])[\.)]\s*(\S.*)$/u', $line, $match)) { if (!is_array($current)) continue; $current['options'][] = ['label' => $match[2]]; $currentOption = count($current['options']) - 1; continue; }
            if (is_array($current) && $currentOption !== null) $current['options'][$currentOption]['label'] .= ' ' . $line;
            elseif (is_array($current)) $current['question_text'] .= ' ' . $line;
        }
        $flush();
        if (!$questions) return ['ok' => false, 'message' => 'No se pudieron identificar preguntas y alternativas completas. Carga un PDF válido con texto seleccionable y una estructura de preguntas legible.'];
        return ['ok' => true, 'questions' => $questions];
    }

    private function extractPdf(string $path, int $maxChars, bool $detectHighlights = false): array
    {
        try {
            $document = (new Parser())->parseFile($path);
            $pages = $document->getPages();
        } catch (Throwable $exception) {
            throw new RuntimeException('No fue posible leer el PDF con la librería PHP configurada.', 0, $exception);
        }
        if (!$pages) throw new RuntimeException('El PDF no contiene páginas legibles.');
        $output = '';
        $pageTexts = [];
        $characters = 0;
        foreach ($pages as $index => $page) {
            $pageText = trim((string) $page->getText());
            $pageText = $this->normalizePageText($pageText);
            $pageTexts[$index + 1] = $pageText;
            if ($pageText !== '') {
                $pageBlock = '[PÁGINA ' . ($index + 1) . "]\n" . $pageText . "\n\n";
                $characters += mb_strlen($pageBlock);
                $output .= $pageBlock;
            }
            if ($characters > $maxChars) {
                throw new RuntimeException('El PDF contiene más de ' . number_format($maxChars, 0, ',', '.') . ' caracteres extraíbles. Selecciona un perfil mayor o divide el documento antes de generar.');
            }
        }
        $output = trim($output);
        if ($output === '') throw new RuntimeException('El PDF no contiene texto extraíble. Los PDF escaneados requieren OCR antes de importarlos.');
        return ['pages' => count($pages), 'text' => $output, 'page_texts' => $pageTexts, 'highlighted_choices' => $detectHighlights ? $this->detectHighlightedChoices($path, count($pages)) : [], 'chars' => mb_strlen($output), 'hash' => hash_file('sha256', $path)];
    }

    private function normalizePageText(string $text): string
    {
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        $text = preg_replace('/[ \t]+/u', ' ', $text) ?? $text;
        $text = preg_replace('/\n{3,}/u', "\n\n", $text) ?? $text;
        return trim($text);
    }

    private function validateDraft(array $draft, string $formType, string $mode, int $maxQuestions, int $pageCount, array $analysis, string $source): array
    {
        $questions = array_values($draft['questions']);
        if ($mode === 'import' && count($questions) > $maxQuestions) {
            return ['ok' => false, 'message' => 'El PDF contiene ' . count($questions) . ' preguntas y el límite seleccionado es ' . $maxQuestions . '. Aumenta el límite para importar todo el contenido sin pérdidas.'];
        }
        if (count($questions) > $maxQuestions) {
            return ['ok' => false, 'message' => 'La IA devolvió más preguntas que las permitidas. Ajusta la cantidad solicitada e inténtalo nuevamente.'];
        }
        if ($mode === 'import' && $analysis['question_markers'] >= 2 && count($questions) < min($analysis['question_markers'], $maxQuestions)) {
            return ['ok' => false, 'message' => 'La IA no pudo recuperar todas las preguntas detectadas en el PDF. No se guardó ningún borrador para evitar pérdida de información.'];
        }
        $allowedTypes = $formType === 'survey' ? ['single_choice', 'multiple_choice', 'likert', 'nps', 'rating', 'matrix', 'text'] : array_keys(EvaluationSurveyFormModel::QUESTION_TYPES);
        foreach ($questions as $index => &$question) {
            $number = $index + 1;
            $type = (string) ($question['question_type'] ?? '');
            if (!in_array($type, $allowedTypes, true)) return ['ok' => false, 'message' => 'La pregunta ' . $number . ' tiene un tipo no permitido. Se detuvo el proceso para evitar alterar el contenido.'];
            if (trim((string) ($question['question_text'] ?? '')) === '') return ['ok' => false, 'message' => 'La pregunta ' . $number . ' no tiene un enunciado legible. Corrige el PDF antes de continuar.'];
            if ($mode === 'import' && !$this->containsSourceText($source, (string) $question['question_text'])) {
                return ['ok' => false, 'message' => 'La pregunta ' . $number . ' no coincide literalmente con el texto extraído del PDF. No se guardó el borrador para evitar alterar el contenido.'];
            }
            $pages = array_values(array_unique(array_filter(array_map('intval', (array) ($question['source_pages'] ?? [])), static fn (int $page): bool => $page > 0)));
            foreach ($pages as $page) if ($page > $pageCount) return ['ok' => false, 'message' => 'La pregunta ' . $number . ' referencia una página inexistente. Se detuvo el proceso para proteger la trazabilidad.'];
            if (!$pages) return ['ok' => false, 'message' => 'La IA no indicó la página de origen de la pregunta ' . $number . '. No se guardó el borrador para evitar perder trazabilidad.'];
            $question['source_pages'] = $pages;
            $question['evidence'] = mb_substr(trim((string) ($question['evidence'] ?? '')), 0, 500);
            if (in_array($type, ['single_choice', 'multiple_choice', 'true_false'], true)) {
                $options = array_values(array_filter((array) ($question['options'] ?? []), static fn ($option): bool => is_array($option) && trim((string) ($option['label'] ?? '')) !== ''));
                $minimumOptions = $type === 'true_false' ? 2 : 3;
                if (count($options) < $minimumOptions) return ['ok' => false, 'message' => 'La pregunta ' . $number . ' no tiene suficientes alternativas legibles. Corrige el PDF antes de continuar.'];
                if ($mode === 'import') foreach ($options as $option) {
                    if (!$this->containsSourceText($source, (string) ($option['label'] ?? ''))) {
                        return ['ok' => false, 'message' => 'Una alternativa de la pregunta ' . $number . ' no coincide literalmente con el texto extraído del PDF. No se guardó el borrador para evitar alterar el contenido.'];
                    }
                }
                $question['options'] = $options;
            }
        }
        unset($question);
        return ['ok' => true, 'questions' => $questions];
    }

    private function containsSourceText(string $source, string $candidate): bool
    {
        $source = $this->normalizeComparableText($source);
        $candidate = $this->normalizeComparableText($candidate);
        return $candidate !== '' && mb_strpos($source, $candidate) !== false;
    }

    private function normalizeComparableText(string $text): string
    {
        return preg_replace('/\s+/u', ' ', trim($text)) ?? trim($text);
    }

    private function applyHighlightedAnswers(array $questions, array $highlightedChoices): array
    {
        foreach ($questions as $questionIndex => &$question) {
            $type = (string) ($question['question_type'] ?? '');
            if (!in_array($type, ['single_choice', 'multiple_choice', 'true_false'], true)) continue;
            $pages = array_map('intval', (array) ($question['source_pages'] ?? []));
            $anchoredScores = [];
            $unanchoredScores = [];
            foreach ($highlightedChoices as $choice) {
                if (!array_intersect($pages, [(int) ($choice['page'] ?? 0)])) continue;
                $letterIndex = $this->choiceLetterIndex((string) ($choice['letter'] ?? ''));
                foreach ((array) ($question['options'] ?? []) as $optionIndex => $option) {
                    if ($letterIndex !== null && (int) $optionIndex !== $letterIndex) continue;
                    $optionText = (string) ($option['label'] ?? '');
                    $highlightText = (string) ($choice['text'] ?? '');
                    $score = $this->highlightMatchScore($optionText, $highlightText);
                    if ($score > 0) {
                        if ($letterIndex !== null) $anchoredScores[(string) $optionIndex] = max($anchoredScores[(string) $optionIndex] ?? 0, $score);
                        else $unanchoredScores[(string) $optionIndex] = max($unanchoredScores[(string) $optionIndex] ?? 0, $score);
                    }
                }
            }
            $matchScores = $anchoredScores ?: $unanchoredScores;
            $bestScore = $matchScores ? max($matchScores) : 0;
            $matches = [];
            foreach ($matchScores as $optionIndex => $score) if ((int) $score === (int) $bestScore) $matches[] = (string) $optionIndex;
            if (count($matches) > 1) {
                return ['ok' => false, 'message' => 'La pregunta ' . ((int) $questionIndex + 1) . ' tiene más de una alternativa destacada compatible. No se asignó una respuesta automáticamente.'];
            }
            if (!$matches) continue;
            $positive = [];
            foreach ((array) ($question['options'] ?? []) as $optionIndex => $option) if ((float) ($option['score'] ?? 0) > 0) $positive[] = (string) $optionIndex;
            if ($positive && $positive !== $matches) {
                return ['ok' => false, 'message' => 'La pregunta ' . ((int) $questionIndex + 1) . ' tiene una contradicción entre la respuesta indicada y el resaltado. Revísala manualmente.'];
            }
            foreach ($question['options'] as $optionIndex => &$option) {
                $option['score'] = in_array((string) $optionIndex, $matches, true) ? max(1, (float) ($question['points'] ?? 1)) : 0;
            }
            unset($option);
        }
        unset($question);
        return ['ok' => true, 'questions' => $questions];
    }

    private function choiceLetterIndex(string $letter): ?int
    {
        $letter = strtoupper(trim($letter));
        if ($letter === '' || strlen($letter) !== 1 || ord($letter) < ord('A') || ord($letter) > ord('H')) return null;
        return ord($letter) - ord('A');
    }

    private function highlightMatchScore(string $optionText, string $highlightText): int
    {
        $optionText = $this->normalizeComparableText($optionText);
        $highlightText = $this->normalizeComparableText($highlightText);
        if ($optionText === '' || $highlightText === '') return 0;
        if (mb_strpos($optionText, $highlightText) !== false) return mb_strlen($highlightText);
        if (mb_strpos($highlightText, $optionText) !== false) return mb_strlen($optionText);
        // El texto de un mismo PDF puede venir con diferencias de extracción
        // entre servidores (saltos de línea, guiones, viñetas o acentos). Para
        // el vínculo visual usamos una comparación compacta, sin alterar el
        // texto que finalmente se importa.
        $compact = static function (string $value): string {
            $value = function_exists('transliterator_transliterate')
                ? (string) transliterator_transliterate('NFD; [:Nonspacing Mark:] Remove; NFC', $value)
                : $value;
            return preg_replace('/[^\p{L}\p{N}]+/u', '', mb_strtolower($value)) ?? '';
        };
        $compactOption = $compact($optionText);
        $compactHighlight = $compact($highlightText);
        if ($compactOption === '' || $compactHighlight === '') return 0;
        if (mb_strpos($compactOption, $compactHighlight) !== false) return mb_strlen($compactHighlight);
        if (mb_strpos($compactHighlight, $compactOption) !== false) return mb_strlen($compactOption);
        return 0;
    }

    private function detectHighlightedChoices(string $pdfPath, int $pageCount): array
    {
        try { $document = (new Parser())->parseFile($pdfPath); }
        catch (Throwable $exception) { return []; }
        $choices = [];
        foreach ($document->getPages() as $pageIndex => $page) {
            $pageNumber = $pageIndex + 1;
            if ($pageNumber > $pageCount) break;
            $raw = $this->pageContentStream($page);
            if ($raw === '') continue;
            preg_match_all('/((?:\d*\.?\d+\s+){3})(?:sc|scn|rg)\s+(-?\d*\.?\d+)\s+(-?\d*\.?\d+)\s+m\s+(-?\d*\.?\d+)\s+(-?\d*\.?\d+)\s+l\s+(-?\d*\.?\d+)\s+(-?\d*\.?\d+)\s+l\s+(-?\d*\.?\d+)\s+(-?\d*\.?\d+)\s+l\s+h\s+f/s', $raw, $rectangles, PREG_SET_ORDER);
            $textPositions = method_exists($page, 'getDataTm') ? $page->getDataTm() : [];
            foreach ($rectangles as $rectangle) {
                $color = array_map('floatval', preg_split('/\s+/', trim((string) $rectangle[1])));
                if (!$this->isHighlightColor($color)) continue;
                $xs = [(float) $rectangle[2], (float) $rectangle[4], (float) $rectangle[6], (float) $rectangle[8]];
                $ys = [(float) $rectangle[3], (float) $rectangle[5], (float) $rectangle[7], (float) $rectangle[9]];
                $x1 = min($xs); $x2 = max($xs); $y2 = max($ys);
                // El rectángulo se dibuja ligeramente debajo de la línea base del texto.
                $targetY = $y2 - 12;
                $line = [];
                foreach ($textPositions as $position) {
                    $x = (float) ($position[0][4] ?? 0); $y = (float) ($position[0][5] ?? 0);
                    $text = trim((string) ($position[1] ?? ''));
                    if ($text === '' || abs($y - $targetY) > 4 || $x > $x2 + 5 || $x < $x1 - 5) continue;
                    $line[] = ['x' => $x, 'text' => $text];
                }
                if (!$line) continue;
                usort($line, static fn (array $left, array $right): int => $left['x'] <=> $right['x']);
                $lineText = trim(implode(' ', array_column($line, 'text')));
                if (preg_match('/^\s*([A-Ha-h])\s*[\.\):\-]\s*(.*)$/u', $lineText, $match)) {
                    $choices[] = ['page' => $pageNumber, 'letter' => strtoupper($match[1]), 'text' => trim($match[2] !== '' ? $match[2] : $lineText), 'source' => 'visual_highlight'];
                } else {
                    $choices[] = ['page' => $pageNumber, 'letter' => '', 'text' => $lineText, 'source' => 'visual_highlight'];
                }
            }
        }
        return $choices;
    }

    private function pageContentStream($page): string
    {
        if (!method_exists($page, 'get')) return '';
        $contents = $page->get('Contents');
        if (!$contents || !method_exists($contents, 'getContent')) return '';
        $content = $contents->getContent();
        if (is_string($content)) return $content;
        if (!is_array($content)) return '';
        $raw = '';
        foreach ($content as $part) if (is_object($part) && method_exists($part, 'getContent')) $raw .= (string) $part->getContent();
        return $raw;
    }

    private function isHighlightColor(array $color): bool
    {
        if (count($color) < 3) return false;
        $max = max($color[0], $color[1], $color[2]); $min = min($color[0], $color[1], $color[2]);
        return $max >= 0.55 && ($max - $min) >= 0.12;
    }

    private function pdfFilenameTitle(string $filename): string
    {
        $filename = trim(basename($filename));
        $title = preg_replace('/\.pdf$/i', '', $filename) ?? $filename;
        $title = trim(preg_replace('/\s+/u', ' ', $title) ?? $title);
        return mb_substr($title !== '' ? $title : 'Evaluación traspasada desde PDF', 0, 180);
    }

    private function validateImportedAnswers(array $questions): array
    {
        foreach ($questions as $index => $question) {
            $number = (int) $index + 1;
            $text = trim((string) ($question['question_text'] ?? ''));
            if ($text === '') return ['ok' => false, 'message' => 'La pregunta ' . $number . ' no tiene enunciado legible. Corrige el PDF antes de importar.'];
            $type = (string) ($question['question_type'] ?? '');
            if (!in_array($type, ['single_choice', 'multiple_choice', 'true_false'], true)) continue;
            $correct = 0;
            foreach ((array) ($question['options'] ?? []) as $option) {
                if ((float) ($option['score'] ?? 0) > 0) $correct++;
            }
            $valid = $type === 'single_choice' ? $correct === 1 : $correct >= 1;
            if (!$valid) {
                return ['ok' => false, 'message' => 'No se pudo identificar una respuesta correcta válida en la pregunta ' . $number . '. Marca o indica la alternativa correcta en el PDF y vuelve a intentarlo.'];
            }
        }
        return ['ok' => true];
    }

    public function analyzePdf(string $path): array
    {
        if (!is_file($path) || (string) file_get_contents($path, false, null, 0, 5) !== '%PDF-') {
            return ['ok' => false, 'message' => 'Debes cargar un archivo PDF válido.'];
        }
        try {
            $pdf = $this->extractPdf($path, (int) $this->moduleSettings->aiSettings()['max_pdf_text_chars']);
        } catch (Throwable $exception) {
            return ['ok' => false, 'message' => $exception->getMessage()];
        }
        return ['ok' => true, 'pages' => $pdf['pages'], 'chars' => $pdf['chars'], 'hash' => $pdf['hash'], 'highlighted_choices' => $pdf['highlighted_choices'] ?? []] + $this->analyzeSource($pdf['text']);
    }

    private function analyzeSource(string $source): array
    {
        $questionMarkers = preg_match_all('/(?:^|\n)\s*(?:pregunta\s*)?\d{1,3}[\.\):\-]\s+\S/iu', $source, $matches) ?: 0;
        $optionMarkers = preg_match_all('/(?:^|\n)\s*(?:[A-Ha-h][\.\)]|\(\s*[A-Ha-h]\s*\))\s+\S/iu', $source, $matches) ?: 0;
        $answerMarkers = preg_match_all('/(?:respuesta\s+correcta|alternativa\s+correcta|clave\s+de\s+respuestas|solucionario|answer\s+key|correcta\s*[:=])|[☑✓✔]/iu', $source, $matches) ?: 0;
        $structured = $questionMarkers >= 1 && ($optionMarkers >= 2 || $answerMarkers >= 1);
        return [
            'mode' => $structured ? 'import' : 'generate',
            'structured' => $structured,
            'has_correct_answers' => $answerMarkers > 0,
            'question_markers' => $questionMarkers,
            'option_markers' => $optionMarkers,
            'answer_markers' => $answerMarkers,
        ];
    }

    private function request(array $config, string $system, string $source, int $tokens, bool $strictImport = false): array
    {
        $payloadData = ['model' => $config['model'], 'input' => [['role' => 'system', 'content' => [['type' => 'input_text', 'text' => $system]]], ['role' => 'user', 'content' => [['type' => 'input_text', 'text' => $source]]]], 'max_output_tokens' => min(16000, $tokens), 'store' => false];
        if ($strictImport) $payloadData['temperature'] = 0.1;
        $payload = json_encode($payloadData, JSON_UNESCAPED_UNICODE);
        $ch = curl_init(rtrim((string) $config['base_url'], '/') . '/responses');
        $timeout = max(60, min(120, (int) ($config['timeout_seconds'] ?? 60)));
        curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_RETURNTRANSFER => true, CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $config['api_key'], 'Content-Type: application/json'], CURLOPT_POSTFIELDS => $payload, CURLOPT_CONNECTTIMEOUT => 5, CURLOPT_TIMEOUT => $timeout, CURLOPT_SSL_VERIFYPEER => true]);
        $raw = curl_exec($ch); $http = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE); $error = curl_error($ch); curl_close($ch);
        $decoded = is_string($raw) ? json_decode($raw, true) : null; $text = is_array($decoded) ? (string) ($decoded['output_text'] ?? '') : '';
        if ($text === '' && is_array($decoded)) foreach (($decoded['output'] ?? []) as $item) foreach (($item['content'] ?? []) as $content) if (!empty($content['text'])) $text = (string) $content['text'];
        if ($http >= 200 && $http < 300 && trim($text) !== '') {
            if (($decoded['status'] ?? '') === 'incomplete') {
                error_log('Evaluation survey AI response incomplete: ' . json_encode($decoded['incomplete_details'] ?? [], JSON_UNESCAPED_UNICODE));
                return ['ok' => false, 'message' => 'La respuesta de la IA quedó incompleta. Reduce la cantidad de preguntas o inténtalo nuevamente.'];
            }
            return ['ok' => true, 'text' => trim($text)];
        }
        if ($error !== '') {
            error_log('Evaluation survey AI request failed: ' . $error);
            return ['ok' => false, 'message' => stripos($error, 'timed out') !== false ? 'La IA tardó demasiado en responder. Reduce la cantidad de preguntas o inténtalo nuevamente.' : 'No fue posible conectar con la IA.'];
        }
        error_log('Evaluation survey AI provider error HTTP ' . $http . ': ' . mb_substr((string) $raw, 0, 500));
        return ['ok' => false, 'message' => 'La IA no pudo generar el borrador.'];
    }

    private function decodeJson(string $text): ?array
    {
        $text = trim(preg_replace('/```(?:json)?/i', '', $text) ?? $text);
        $start = strpos($text, '{');
        $end = strrpos($text, '}');
        if ($start !== false && $end !== false && $end > $start) $text = substr($text, $start, $end - $start + 1);
        $decoded = json_decode(trim($text), true);
        return is_array($decoded) ? $decoded : null;
    }
}
