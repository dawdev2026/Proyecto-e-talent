<?php
declare(strict_types=1);

final class MoodleEvaluationImportService
{
    public function import(array $input): array
    {
        $baseUrl = rtrim(trim((string) ($input['moodle_base_url'] ?? '')), '/');
        $token = trim((string) ($input['moodle_token'] ?? ''));
        $quizId = max(0, (int) ($input['moodle_quiz_id'] ?? 0));
        $courseId = max(0, (int) ($input['moodle_course_id'] ?? 0));
        $definitionFunction = trim((string) ($input['moodle_definition_function'] ?? 'local_etalent_get_quiz_definition'));
        $quizFunction = trim((string) ($input['moodle_quiz_function'] ?? 'mod_quiz_get_quizzes_by_courses'));
        $gradeFunction = trim((string) ($input['moodle_grade_function'] ?? 'mod_quiz_get_user_best_grade'));
        $technicalUserId = max(0, (int) ($input['moodle_user_id'] ?? 0));

        if ($baseUrl === '' || !filter_var($baseUrl, FILTER_VALIDATE_URL)) {
            throw new InvalidArgumentException('Ingresa una URL válida de Moodle.');
        }
        $scheme = strtolower((string) parse_url($baseUrl, PHP_URL_SCHEME));
        $host = strtolower((string) parse_url($baseUrl, PHP_URL_HOST));
        if ($scheme !== 'https' && !in_array($host, ['localhost', '127.0.0.1', '::1'], true)) {
            throw new InvalidArgumentException('La conexión a Moodle debe usar HTTPS.');
        }
        if ($token === '') throw new InvalidArgumentException('El token de Moodle es obligatorio.');
        if ($quizId < 1) throw new InvalidArgumentException('El ID del cuestionario es obligatorio.');
        foreach ([$definitionFunction, $quizFunction, $gradeFunction] as $function) {
            if ($function !== '' && !preg_match('/^[a-z][a-z0-9_]*$/', $function)) {
                throw new InvalidArgumentException('El nombre de una función Moodle no es válido.');
            }
        }

        $calls = [];
        $quizData = [];
        if ($courseId > 0 && $quizFunction !== '') {
            $quizResponse = $this->call($baseUrl, $token, $quizFunction, ['courseids[0]' => $courseId]);
            $calls[] = ['function' => $quizFunction, 'status' => $quizResponse['status'], 'ok' => $quizResponse['ok']];
            $quizData = $this->findQuiz((array) ($quizResponse['data']['quizzes'] ?? []), $quizId);
            if (!$quizData && !empty($quizResponse['data']['quizzes'])) {
                throw new RuntimeException('El cuestionario no fue encontrado dentro del curso indicado.');
            }
        }

        $definitionResponse = $this->call($baseUrl, $token, $definitionFunction, ['quizid' => $quizId]);
        $calls[] = ['function' => $definitionFunction, 'status' => $definitionResponse['status'], 'ok' => $definitionResponse['ok']];
        $definition = $definitionResponse['data'];
        if (!$definitionResponse['ok']) {
            throw new RuntimeException($this->moodleError($definitionResponse, 'No se pudo obtener la definición del cuestionario.'));
        }

        $gradeData = [];
        if ($gradeFunction !== '' && $technicalUserId > 0) {
            $gradeResponse = $this->call($baseUrl, $token, $gradeFunction, ['quizid' => $quizId, 'userid' => $technicalUserId]);
            $calls[] = ['function' => $gradeFunction, 'status' => $gradeResponse['status'], 'ok' => $gradeResponse['ok']];
            if ($gradeResponse['ok']) $gradeData = $gradeResponse['data'];
        }

        $payload = $this->normalize($definition, $quizData, $gradeData, $quizId);
        $payload['source'] = ['provider' => 'moodle', 'quiz_id' => $quizId, 'course_id' => $courseId, 'definition_function' => $definitionFunction];
        return ['payload' => $payload, 'calls' => $calls];
    }

    private function call(string $baseUrl, string $token, string $function, array $params): array
    {
        $endpoint = $baseUrl . '/webservice/rest/server.php';
        $params['wstoken'] = $token;
        $params['wsfunction'] = $function;
        $params['moodlewsrestformat'] = 'json';
        $ch = curl_init($endpoint);
        if ($ch === false) throw new RuntimeException('No se pudo iniciar el cliente HTTP para Moodle.');
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query($params),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_HTTPHEADER => ['Accept: application/json'],
        ]);
        $raw = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        if ($raw === false || $error !== '') return ['ok' => false, 'status' => $status, 'data' => [], 'error' => $error ?: 'Sin respuesta de Moodle.'];
        $data = json_decode((string) $raw, true);
        if (!is_array($data)) return ['ok' => false, 'status' => $status, 'data' => [], 'error' => 'Moodle devolvió una respuesta que no es JSON válido.'];
        return ['ok' => $status >= 200 && $status < 300 && !isset($data['exception']), 'status' => $status, 'data' => $data, 'error' => ''];
    }

    private function normalize(array $definition, array $quizData, array $gradeData, int $quizId): array
    {
        $quiz = (array) ($definition['quiz'] ?? $definition['quizdata'] ?? $quizData);
        $rawQuestions = $definition['questions'] ?? $definition['question'] ?? [];
        if (!is_array($rawQuestions)) $rawQuestions = [];
        if (isset($rawQuestions['items']) && is_array($rawQuestions['items'])) $rawQuestions = $rawQuestions['items'];
        if (!$rawQuestions) throw new RuntimeException('Moodle respondió correctamente, pero no entregó preguntas. Revisa el servicio de definición configurado.');

        $questions = [];
        $warnings = [];
        foreach (array_values($rawQuestions) as $index => $rawQuestion) {
            if (!is_array($rawQuestion)) continue;
            $question = $this->normalizeQuestion($rawQuestion, $index + 1);
            if ($question['warning'] !== '') $warnings[] = $question['warning'];
            unset($question['warning']);
            $questions[] = $question;
        }
        if (!$questions) throw new RuntimeException('No se encontraron preguntas importables en Moodle.');

        $maxScore = $this->number($quiz, ['max_score', 'maxgrade', 'grade', 'grademax', 'sumgrades'], 100);
        $passingScore = $this->number($gradeData, ['gradetopass', 'gradepass', 'passing_score'], null);
        if ($passingScore === null) $passingScore = $this->number($quiz, ['gradetopass', 'gradepass', 'passing_score'], null);
        return [
            'title' => (string) ($quiz['name'] ?? $quiz['title'] ?? 'Evaluación importada desde Moodle'),
            'description' => (string) ($quiz['intro'] ?? $quiz['description'] ?? 'Evaluación importada desde Moodle.'),
            'instructions' => (string) ($quiz['instructions'] ?? ''),
            'max_score' => max(1, $maxScore),
            'passing_score' => $passingScore === null ? null : max(0, min(max(1, $maxScore), $passingScore)),
            'question_display_limit' => min(7, count($questions)),
            'questions' => $questions,
            'warnings' => array_values(array_unique($warnings)),
            'source' => ['quiz_id' => $quizId],
        ];
    }

    private function normalizeQuestion(array $raw, int $position): array
    {
        $type = strtolower((string) ($raw['type'] ?? $raw['question_type'] ?? $raw['qtype'] ?? 'multichoice'));
        $options = $raw['options'] ?? $raw['answers'] ?? $raw['alternatives'] ?? $raw['choices'] ?? [];
        if (is_array($options) && isset($options['items'])) $options = $options['items'];
        if (in_array($type, ['truefalse', 'true_false', 'boolean'], true)) {
            $mappedType = 'true_false';
        } elseif (in_array($type, ['multichoice', 'multiplechoice', 'singlechoice', 'multiple_choice', 'single_choice'], true)) {
            $isMultiple = strpos($type, 'multiple') !== false || !empty($raw['multiple']) || (array_key_exists('single', $raw) && !$raw['single']);
            $mappedType = $isMultiple ? 'multiple_choice' : 'single_choice';
        } else {
            $mappedType = 'text';
        }
        $points = $this->number($raw, ['points', 'maxmark', 'mark', 'defaultmark'], 1);
        $normalizedOptions = [];
        foreach (array_values(is_array($options) ? $options : []) as $optionIndex => $option) {
            if (!is_array($option)) $option = ['text' => (string) $option];
            $fraction = $this->number($option, ['fraction', 'score', 'score_value'], 0);
            $correct = !empty($option['correct']) || !empty($option['is_correct']) || $fraction > 0;
            $normalizedOptions[] = ['label' => (string) ($option['text'] ?? $option['answer'] ?? $option['option_label'] ?? $option['content'] ?? ''), 'value' => (string) ($option['value'] ?? $option['id'] ?? ($optionIndex + 1)), 'score' => $correct ? $points : 0];
        }
        $warning = '';
        if ($mappedType === 'text') {
            $warning = 'La pregunta Moodle ' . (string) ($raw['id'] ?? $position) . ' usa un tipo no soportado como alternativa y fue importada como texto para revisión.';
        } elseif (!$normalizedOptions) {
            $warning = 'La pregunta Moodle ' . (string) ($raw['id'] ?? $position) . ' no entregó alternativas.';
        }
        return ['question_text' => (string) ($raw['text'] ?? $raw['questiontext'] ?? $raw['question_text'] ?? $raw['name'] ?? ''), 'question_type' => $mappedType, 'points' => $mappedType === 'text' ? 0 : max(0, $points), 'sort_order' => $position * 10, 'is_required' => 1, 'is_active' => 1, 'options' => $normalizedOptions, 'warning' => $warning];
    }

    private function findQuiz(array $quizzes, int $quizId): array
    {
        foreach ($quizzes as $quiz) if (is_array($quiz) && (int) ($quiz['id'] ?? 0) === $quizId) return $quiz;
        return [];
    }

    private function number(array $data, array $keys, ?float $default): ?float
    {
        foreach ($keys as $key) if (isset($data[$key]) && $data[$key] !== '' && is_numeric($data[$key])) return (float) $data[$key];
        return $default;
    }

    private function moodleError(array $response, string $fallback): string
    {
        $data = (array) ($response['data'] ?? []);
        $message = $data['message'] ?? $data['error'] ?? $response['error'] ?? $fallback;
        return is_scalar($message) ? (string) $message : $fallback;
    }
}
