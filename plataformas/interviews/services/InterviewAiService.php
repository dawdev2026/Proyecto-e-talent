<?php
declare(strict_types=1);

final class InterviewAiService
{
    private array $settings;

    public function __construct(?array $settings = null)
    {
        $this->settings = $settings ?: InterviewSettings::ai();
    }

    public function isConfigured(): bool
    {
        return !empty($this->settings['enabled'])
            && trim((string) ($this->settings['api_key'] ?? '')) !== ''
            && trim((string) ($this->settings['model'] ?? '')) !== '';
    }

    public function moderatorBrief(array $context): array
    {
        $fallback = $this->fallbackBrief($context);
        if (!$this->isConfigured()) {
            return ['ok' => false, 'pending' => true, 'data' => $fallback, 'error' => 'IA no configurada.'];
        }

        $prompt = 'Genera un resumen ejecutivo y 10 preguntas de entrevista laboral basadas en report.structured_summary y documents cuando existan. '
            . 'No inventes puntajes, instrumentos ni rasgos ausentes; si hay missing_data o consistency_alerts, indicalo como cautela para el moderador. '
            . 'Usa los documentos como antecedentes, identifica su tipo y no trates un documento no procesado como evidencia. '
            . 'Usa lenguaje prudente: indicadores con contexto, sin diagnosticos clinicos ni conclusiones absolutas. '
            . 'Devuelve JSON con keys summary y questions.';

        $result = $this->completeJson($prompt, $context, $fallback);
        if (!empty($result['data']) && is_array($result['data'])) {
            $result['data'] = $this->normalizeModeratorBrief($result['data']);
        }

        return $result;
    }

    public function normalizeModeratorBrief(array $brief): array
    {
        $summary = $brief['summary'] ?? '';
        $structuredSummary = [];
        if (is_array($summary)) {
            $structuredSummary = $summary;
        } elseif (is_string($summary)) {
            $decoded = json_decode(trim($summary), true);
            if (is_array($decoded)) {
                $structuredSummary = $decoded;
            }
        }

        if ($structuredSummary) {
            $summaryParts = [];
            $executiveSummary = trim((string) ($structuredSummary['executive_summary'] ?? ''));
            $caution = trim((string) ($structuredSummary['caution'] ?? ''));
            if ($executiveSummary !== '') {
                $summaryParts[] = $executiveSummary;
            }
            if ($caution !== '' && $caution !== $executiveSummary) {
                $summaryParts[] = 'Cautela: ' . $caution;
            }
            if (!$summaryParts && isset($structuredSummary['observations']) && is_array($structuredSummary['observations'])) {
                $summaryParts[] = implode(' ', array_filter(array_map('strval', $structuredSummary['observations'])));
            }
            $brief['summary'] = trim(implode(' ', $summaryParts));
        } else {
            $brief['summary'] = trim((string) $summary);
        }

        $questions = $brief['questions'] ?? [];
        if (is_string($questions)) {
            $decodedQuestions = json_decode(trim($questions), true);
            $questions = is_array($decodedQuestions) ? $decodedQuestions : [$questions];
        }
        $brief['questions'] = is_array($questions)
            ? array_values(array_filter(array_map('strval', $questions), static fn(string $question): bool => trim($question) !== ''))
            : [];

        return $brief;
    }

    public function finalReport(array $context): array
    {
        $fallback = $this->fallbackFinalReport($context);
        if (!$this->isConfigured()) {
            return ['ok' => false, 'pending' => true, 'data' => $fallback, 'error' => 'IA no configurada.'];
        }

        $prompt = 'Genera un reporte final de entrevista de seleccion combinando report.structured_summary, documents, transcripcion y apuntes. '
            . 'Usa el informe psicometrico solo como indicador contextual y cita unicamente datos presentes en report.structured_summary. '
            . 'Considera el texto extraido de documents solo cuando su estado sea ready y cita el nombre del documento como fuente. '
            . 'Diferencia evidencia observada en entrevista, apuntes, transcripcion e indicadores psicometricos. '
            . 'La key evidence debe ser un array de objetos con keys source, finding, support, interpretation y follow_up. '
            . 'source debe ser una de: entrevista, apuntes, transcripcion, psicometria, mixto. '
            . 'Cada objeto debe ser breve, entendible para no especialistas y con interpretacion prudente. '
            . 'Si existen consistency_alerts, no emitas recomendacion cerrada: pide revision tecnica antes de usar el reporte. '
            . 'No emitas diagnosticos clinicos ni decisiones absolutas. Devuelve JSON con keys title, summary, evidence, risks, recommendation y next_steps.';

        return $this->completeJson($prompt, $context, $fallback);
    }

    public function testConnection(): array
    {
        if (!$this->isConfigured()) {
            return [
                'ok' => false,
                'message' => 'Completa API Key y modelo antes de probar la conexion IA.',
            ];
        }

        $response = $this->request('/chat/completions', [
            'model' => $this->settings['model'],
            'messages' => [
                [
                    'role' => 'system',
                    'content' => 'Responde exclusivamente con JSON valido.',
                ],
                [
                    'role' => 'user',
                    'content' => 'Devuelve {"test":"ok","message":"conexion IA validada"}',
                ],
            ],
            'temperature' => 0,
            'response_format' => ['type' => 'json_object'],
        ]);

        if (!$response['ok']) {
            return [
                'ok' => false,
                'message' => $this->connectionErrorMessage((int) ($response['status'] ?? 0), (string) ($response['error'] ?? '')),
            ];
        }

        $content = (string) ($response['data']['choices'][0]['message']['content'] ?? '');
        $decoded = json_decode($content, true);
        if (!is_array($decoded) || (string) ($decoded['test'] ?? '') !== 'ok') {
            return [
                'ok' => false,
                'message' => 'La conexion respondio, pero el modelo no devolvio el JSON esperado.',
            ];
        }

        return [
            'ok' => true,
            'message' => 'Conexion IA validada correctamente. API Key, modelo y salida JSON estan operativos.',
        ];
    }

    private function completeJson(string $systemPrompt, array $context, array $fallback): array
    {
        $body = [
            'model' => $this->settings['model'],
            'messages' => [
                ['role' => 'system', 'content' => $systemPrompt],
                ['role' => 'user', 'content' => json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)],
            ],
            'temperature' => 0.2,
            'response_format' => ['type' => 'json_object'],
        ];

        $response = $this->request('/chat/completions', $body);
        if (!$response['ok']) {
            return ['ok' => false, 'pending' => false, 'data' => $fallback, 'error' => $response['error']];
        }

        $content = (string) ($response['data']['choices'][0]['message']['content'] ?? '');
        $decoded = json_decode($content, true);
        if (!is_array($decoded)) {
            return ['ok' => false, 'pending' => false, 'data' => $fallback, 'error' => 'La IA no devolvio JSON valido.'];
        }

        return ['ok' => true, 'pending' => false, 'data' => $decoded, 'error' => ''];
    }

    private function request(string $path, array $payload): array
    {
        $body = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($body === false) {
            return ['ok' => false, 'status' => 0, 'data' => [], 'error' => 'No se pudo preparar la solicitud IA.'];
        }

        $url = rtrim((string) $this->settings['base_url'], '/') . $path;
        $headers = [
            'Authorization: Bearer ' . $this->settings['api_key'],
            'Content-Type: application/json',
        ];

        $curl = curl_init($url);
        if ($curl === false) {
            return ['ok' => false, 'status' => 0, 'data' => [], 'error' => 'No se pudo iniciar cURL para IA.'];
        }

        curl_setopt_array($curl, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => (int) $this->settings['timeout_seconds'],
        ]);

        $raw = curl_exec($curl);
        $status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        $error = curl_error($curl);
        curl_close($curl);

        $data = is_string($raw) && $raw !== '' ? json_decode($raw, true) : [];
        $data = is_array($data) ? $data : [];

        return [
            'ok' => $status >= 200 && $status < 300,
            'status' => $status,
            'data' => $data,
            'error' => $error !== '' ? $error : (string) ($data['error']['message'] ?? $data['error'] ?? $data['message'] ?? 'No se pudo generar con IA.'),
        ];
    }

    private function connectionErrorMessage(int $status, string $detail): string
    {
        $detail = trim($detail);
        if ($status === 401 || $status === 403) {
            return 'No se pudo validar la API Key de IA. Revisa que la clave sea correcta y tenga permisos.';
        }
        if ($status === 404) {
            return 'No se encontro el endpoint o el modelo configurado. Revisa Base URL y nombre del modelo.';
        }
        if ($status === 400) {
            return 'La solicitud fue rechazada. Revisa que el modelo soporte salida JSON y que el nombre este bien escrito.';
        }
        if ($status === 429) {
            return 'La cuenta de IA respondio con limite de uso. Intenta mas tarde o revisa cuota/facturacion.';
        }
        if ($status === 0) {
            return $detail !== '' ? 'No se pudo conectar con el proveedor IA: ' . $detail : 'No se pudo conectar con el proveedor IA. Revisa Base URL, red y timeout.';
        }

        return $detail !== '' ? 'La prueba IA fallo: ' . $detail : 'La prueba IA fallo con codigo HTTP ' . $status . '.';
    }

    private function fallbackBrief(array $context): array
    {
        $candidate = (string) ($context['candidate']['name'] ?? 'Postulante');

        return [
            'summary' => 'Resumen pendiente de generacion automatica para ' . $candidate . '. Revisa el informe disponible antes de iniciar la entrevista.',
            'questions' => [
                'Que aspectos de tu experiencia reciente se relacionan mas con este cargo?',
                'Que condiciones te ayudan a rendir de forma consistente?',
                'Cuentame una situacion donde tuviste que aprender rapidamente.',
                'Como sueles organizar prioridades cuando hay presion?',
                'Que tipo de retroalimentacion te resulta mas util?',
                'Describe una decision dificil y como la abordaste.',
                'Que tareas te motivan mas y cuales te demandan mas energia?',
                'Como manejas diferencias de criterio con otras personas?',
                'Que evidencias concretas muestran tus principales fortalezas?',
                'Que apoyo necesitarias para adaptarte bien al rol?',
            ],
        ];
    }

    private function fallbackFinalReport(array $context): array
    {
        return [
            'title' => 'Reporte final pendiente',
            'summary' => 'La generacion automatica requiere configurar IA. Se conserva la transcripcion, los apuntes y el enlace al informe del postulante.',
            'evidence' => [],
            'risks' => [],
            'recommendation' => 'Revisar manualmente la evidencia antes de tomar decisiones de seleccion.',
            'next_steps' => ['Configurar IA o completar el reporte de forma manual.'],
        ];
    }
}
