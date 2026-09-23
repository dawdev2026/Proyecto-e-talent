<?php
declare(strict_types=1);

final class ComponentValidationModel
{
    private Database $db;

    public function __construct(?Database $db = null)
    {
        $this->db = $db ?: database('core');
    }

    public function record(int $userId, int $companyId, array $payload): int
    {
        $deviceType = $this->limitedChoice($payload['device_type'] ?? '', ['desktop', 'tablet', 'mobile', 'unknown'], 'unknown');
        $os = $this->limitedText($payload['os'] ?? '', 40, 'Desconocido');
        $browser = $this->limitedText($payload['browser'] ?? '', 40, 'Desconocido');
        $version = $this->limitedText($payload['browser_version'] ?? '', 24, '');
        $agent = $this->limitedText($_SERVER['HTTP_USER_AGENT'] ?? '', 255, '');
        $statuses = [];
        $componentMessages = [];
        foreach (['camera', 'microphone', 'media_recorder', 'screen_capture'] as $key) {
            $statuses[$key] = $this->limitedChoice(
                is_array($payload['components'] ?? null) ? ($payload['components'][$key] ?? '') : '',
                ['passed', 'warning', 'failed', 'not_supported', 'not_checked'],
                'not_checked'
            );
            $rawMessages = is_array($payload['component_messages'] ?? null) ? $payload['component_messages'] : [];
            $componentMessages[$key] = $this->knownComponentMessage($key, $statuses[$key], $rawMessages[$key] ?? '');
        }
        $passed = count(array_filter($statuses, static fn(string $value): bool => $value === 'passed'));
        $failed = count(array_filter($statuses, static fn(string $value): bool => in_array($value, ['failed', 'not_supported'], true)));
        $outcome = $failed > 0 ? 'failed' : ($passed === count($statuses) ? 'passed' : 'partial');
        $metadata = [
            'schema_version' => 2,
            'components' => $statuses,
            'component_messages' => $componentMessages,
            'capture_source' => $this->limitedChoice($payload['capture_source'] ?? '', ['screen', 'canvas', 'unavailable'], 'unavailable'),
        ];
        $json = json_encode($metadata, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false) throw new RuntimeException('No se pudieron guardar los resultados de la validación.');

        return $this->db->transaction(function (Database $db) use ($userId, $companyId, $deviceType, $os, $browser, $version, $outcome, $json, $agent): int {
            $owner = $db->fetch('SELECT id FROM users WHERE id = ? AND company_id = ? AND role = "usuario" AND is_active = 1 LIMIT 1 FOR UPDATE', [$userId, $companyId]);
            if (!$owner) throw new RuntimeException('El usuario ya no pertenece a esta empresa o está inactivo.');
            return $db->insert(
                'INSERT INTO component_validation_events (company_id,user_id,device_type,os_name,browser_name,browser_version,outcome,metadata,user_agent) VALUES (?,?,?,?,?,?,?,?,?)',
                [$companyId, $userId, $deviceType, $os, $browser, $version !== '' ? $version : null, $outcome, $json, $agent !== '' ? $agent : null]
            );
        });
    }

    public function countForCompany(int $companyId): int
    {
        if ($companyId <= 0) return 0;
        $row = $this->db->fetch(
            'SELECT COUNT(*) AS total
             FROM component_validation_events v
             JOIN users u ON u.id = v.user_id AND u.company_id = v.company_id
             WHERE v.company_id = ?',
            [$companyId]
        );
        return (int) ($row['total'] ?? 0);
    }

    public function recentForCompany(int $companyId, int $limit = 50, int $offset = 0): array
    {
        if ($companyId <= 0) return [];
        $limit = max(1, min(100, $limit));
        $offset = max(0, $offset);
        return $this->db->fetchAll(
            'SELECT v.id, v.user_id, u.name AS user_name, u.rut, v.device_type, v.os_name, v.browser_name, v.browser_version, v.outcome, v.metadata, v.created_at
             FROM component_validation_events v
             JOIN users u ON u.id = v.user_id AND u.company_id = v.company_id
             WHERE v.company_id = ?
             ORDER BY v.created_at DESC, v.id DESC
             LIMIT ' . $limit . ' OFFSET ' . $offset,
            [$companyId]
        );
    }

    public function attemptCountsForUsers(int $companyId, array $userIds): array
    {
        if ($companyId <= 0) return [];
        $userIds = array_values(array_unique(array_filter(array_map('intval', $userIds), static fn(int $id): bool => $id > 0)));
        if (!$userIds) return [];
        $placeholders = implode(',', array_fill(0, count($userIds), '?'));
        $rows = $this->db->fetchAll(
            'SELECT user_id, COUNT(*) AS attempt_count
             FROM component_validation_events
             WHERE company_id = ? AND user_id IN (' . $placeholders . ')
             GROUP BY user_id',
            array_merge([$companyId], $userIds)
        );
        $counts = [];
        foreach ($rows as $row) $counts[(int) $row['user_id']] = (int) $row['attempt_count'];
        return $counts;
    }

    public function latestForUser(int $userId, int $companyId): ?array
    {
        if ($userId <= 0 || $companyId <= 0) return null;
        return $this->db->fetch(
            'SELECT device_type, os_name, browser_name, browser_version, outcome, metadata, created_at
             FROM component_validation_events
             WHERE user_id = ? AND company_id = ?
             ORDER BY created_at DESC, id DESC
             LIMIT 1',
            [$userId, $companyId]
        );
    }

    private function limitedChoice($value, array $allowed, string $fallback): string
    {
        $value = strtolower(trim((string) $value));
        return in_array($value, $allowed, true) ? $value : $fallback;
    }

    private function limitedText($value, int $maxLength, string $fallback): string
    {
        $value = trim((string) $value);
        $value = preg_replace('/[\x00-\x1F\x7F]/u', '', $value) ?? '';
        return mb_substr($value !== '' ? $value : $fallback, 0, $maxLength);
    }

    private function knownComponentMessage(string $component, string $status, $message): string
    {
        $allowed = [
            'camera' => [
                'passed' => ['Permiso concedido y la cámara entregó un fotograma de video.'],
                'warning' => ['No se concedió el permiso de cámara.'],
                'failed' => ['No se encontró una cámara.', 'No fue posible activar la cámara.', 'No se recibió una pista de video de la cámara.', 'La cámara se activó, pero no entregó un fotograma de video.'],
                'not_supported' => ['Este navegador o conexión no permite acceder a la cámara.'],
            ],
            'microphone' => [
                'passed' => ['Permiso concedido y pista de audio activa; esta revisión no mide el volumen.'],
                'warning' => ['No se concedió el permiso de micrófono.'],
                'failed' => ['No se encontró un micrófono.', 'No fue posible activar el micrófono.', 'El navegador no entregó audio de micrófono.'],
                'not_supported' => ['Este navegador o conexión no permite acceder al micrófono.'],
            ],
            'media_recorder' => [
                'passed' => ['MediaRecorder operó con cámara y micrófono; los datos temporales se descartaron.'],
                'warning' => ['Se probó solo la pista disponible; se requiere cámara y micrófono para comprobar el registro audiovisual completo.'],
                'failed' => ['No hubo una pista disponible para probar la grabación.', 'El navegador no pudo iniciar MediaRecorder.', 'Falló la grabación local de prueba. No se guardó ningún video.', 'La grabación no generó datos.'],
                'not_supported' => ['Este navegador no incluye MediaRecorder.'],
            ],
            'screen_capture' => [
                'passed' => ['Se confirmó que compartiste la pantalla completa. No se guardó contenido.', 'Canvas pudo renderizar una muestra local de la interfaz. La imagen temporal se descartó y no se envió.'],
                'warning' => ['No se concedió el permiso o se canceló la selección de pantalla.', 'El navegador compartió la pantalla, pero no confirmó que fuera la pantalla completa.'],
                'failed' => ['El modo de pantalla completa dejó de estar disponible. Reintenta desde Chrome o Edge de escritorio, o Safari de escritorio en macOS.', 'Canvas no pudo generar una imagen de prueba. Revisa la compatibilidad del navegador.', 'No se pudo cargar html2canvas. Revisa la conexión e inténtalo nuevamente.', 'html2canvas no pudo renderizar la muestra de prueba en este navegador.', 'Seleccionaste una ventana o pestaña. Para esta evaluación, selecciona la pantalla completa.', 'El navegador no entregó una pista de pantalla.', 'Se seleccionó la pantalla, pero el navegador no entregó un fotograma dentro del tiempo esperado.', 'Se seleccionó la pantalla, pero el navegador no pudo reproducir el flujo de video.', 'Se seleccionó la pantalla, pero no se recibió video de ella.', 'Se seleccionó la pantalla, pero no se recibió un fotograma a tiempo.', 'No fue posible activar la captura de pantalla.'],
                'not_supported' => ['Este navegador o conexión no permite compartir la pantalla desde la página.'],
            ],
        ];

        $message = trim((string) $message);
        return in_array($message, $allowed[$component][$status] ?? [], true) ? $message : '';
    }
}
