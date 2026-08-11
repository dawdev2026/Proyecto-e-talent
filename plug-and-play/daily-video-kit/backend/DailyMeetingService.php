<?php
declare(strict_types=1);

final class DailyMeetingService
{
    private array $config;

    public function __construct(array $config)
    {
        $this->config = $config;
    }

    public function meetingPayload(array $options): array
    {
        try {
            $status = $this->configStatus();
            if (!$status['ok']) {
                return [
                    'ok' => false,
                    'error' => $status['message'],
                    'api_url' => $this->dailyJsUrl(),
                ];
            }

            $roomName = $this->roomName((string) ($options['room_slug'] ?? 'daily-room'));
            $domain = $this->domain();
            $roomResult = $this->ensureRoom($roomName);
            if (empty($roomResult['ok'])) {
                return [
                    'ok' => false,
                    'error' => (string) ($roomResult['error'] ?? 'No se pudo preparar la sala Daily.'),
                    'domain' => $domain,
                    'room_name' => $roomName,
                    'api_url' => $this->dailyJsUrl(),
                ];
            }

            $userName = trim((string) ($options['user_name'] ?? 'Participante'));
            $userId = trim((string) ($options['user_id'] ?? 'guest-' . bin2hex(random_bytes(4))));
            $isOwner = !empty($options['is_owner']);
            $transcriptionEnabled = !empty($options['transcription_enabled']) || !empty($this->config['transcription_enabled']);
            $autoStartTranscription = $isOwner && $transcriptionEnabled && !empty($options['transcription_auto_start']);
            $tokenResult = $this->createMeetingToken($roomName, $userName, $userId, $isOwner, $autoStartTranscription);
            $baseUrl = 'https://' . $domain . '/' . rawurlencode($roomName);
            $token = (string) ($tokenResult['token'] ?? '');

            return [
                'ok' => !empty($tokenResult['ok']),
                'error' => (string) ($tokenResult['error'] ?? ''),
                'provider' => 'daily',
                'domain' => $domain,
                'room_name' => $roomName,
                'url' => $token !== '' ? $baseUrl . '?t=' . rawurlencode($token) : $baseUrl,
                'token' => $token,
                'user_name' => $userName !== '' ? $userName : 'Participante',
                'is_owner' => $isOwner,
                'api_url' => $this->dailyJsUrl(),
                'transcription_enabled' => $isOwner && $transcriptionEnabled,
                'transcription_auto_start' => $autoStartTranscription,
                'transcription_url' => (string) ($options['transcription_url'] ?? ''),
                'transcription_snapshot_interval_seconds' => max(5, min(300, (int) ($options['transcription_snapshot_interval_seconds'] ?? $this->config['transcription_snapshot_interval_seconds'] ?? 20))),
                'csrf_token' => (string) ($options['csrf_token'] ?? ''),
                'minutes_pool' => is_array($options['minutes_pool'] ?? null) ? $options['minutes_pool'] : [],
                'room' => $roomResult['room'] ?? [],
            ];
        } catch (Throwable $exception) {
            return [
                'ok' => false,
                'error' => 'No se pudo preparar la sala Daily. Revisa la configuracion del proveedor.',
                'api_url' => $this->dailyJsUrl(),
            ];
        }
    }

    public function configStatus(): array
    {
        if (empty($this->config['enabled'])) {
            return ['ok' => false, 'message' => 'Daily esta inactivo.'];
        }
        if ($this->apiKey() === '') {
            return ['ok' => false, 'message' => 'Falta configurar la API Key de Daily.'];
        }
        if ($this->domain() === '') {
            return ['ok' => false, 'message' => 'Falta configurar un dominio Daily valido.'];
        }

        return ['ok' => true, 'message' => ''];
    }

    public function ensureRoom(string $roomName): array
    {
        $roomName = $this->roomName($roomName);
        $existing = $this->request('GET', '/rooms/' . rawurlencode($roomName));
        if (!empty($existing['ok'])) {
            $update = $this->request('POST', '/rooms/' . rawurlencode($roomName), [
                'properties' => $this->roomProperties(),
            ]);

            return [
                'ok' => true,
                'status' => !empty($update['ok']) ? $update['status'] : $existing['status'],
                'message' => !empty($update['ok']) ? 'Room Daily sincronizada.' : 'Room Daily encontrada.',
                'room' => !empty($update['ok']) ? $update['data'] : $existing['data'],
            ];
        }

        $create = $this->request('POST', '/rooms', [
            'name' => $roomName,
            'privacy' => 'private',
            'properties' => $this->roomProperties(),
        ]);

        if (!empty($create['ok'])) {
            return [
                'ok' => true,
                'status' => $create['status'],
                'message' => 'Room Daily creada.',
                'room' => $create['data'],
            ];
        }

        return [
            'ok' => false,
            'status' => $create['status'] ?? $existing['status'] ?? 0,
            'error' => $create['error'] ?? $existing['error'] ?? 'Daily no entrego detalle del error.',
            'room' => [],
        ];
    }

    public function createMeetingToken(string $roomName, string $userName, string $userId, bool $owner, bool $autoStartTranscription = false): array
    {
        $ttl = max(300, (int) ($this->config['token_ttl_seconds'] ?? 21600));
        $result = $this->request('POST', '/meeting-tokens', [
            'properties' => [
                'room_name' => $this->roomName($roomName),
                'is_owner' => $owner,
                'user_name' => trim($userName) !== '' ? trim($userName) : 'Participante',
                'user_id' => trim($userId) !== '' ? trim($userId) : 'guest',
                'enable_screenshare' => true,
                'enable_live_captions_ui' => true,
                'enable_recording_ui' => $owner,
                'auto_start_transcription' => $owner && $autoStartTranscription,
                'start_audio_off' => true,
                'start_video_off' => false,
                'eject_at_token_exp' => true,
                'exp' => time() + $ttl,
                'lang' => $this->lang(),
                'permissions' => [
                    'hasPresence' => true,
                    'canSend' => true,
                    'canReceive' => (object) [],
                    'canAdmin' => $owner,
                ],
            ],
        ]);

        if (!empty($result['ok']) && isset($result['data']['token'])) {
            return ['ok' => true, 'token' => (string) $result['data']['token'], 'error' => ''];
        }

        return ['ok' => false, 'token' => '', 'error' => $result['error'] ?? 'Daily no genero el token.'];
    }

    public function meetingUsageSummary(int $timeframeStart, int $timeframeEnd): array
    {
        $participantSeconds = 0;
        $meetingSeconds = 0;
        $meetings = 0;
        $participants = [];
        $startingAfter = '';

        do {
            $query = http_build_query(array_filter([
                'timeframe_start' => $timeframeStart,
                'timeframe_end' => $timeframeEnd,
                'limit' => 100,
                'starting_after' => $startingAfter,
            ], static fn($value): bool => $value !== '' && $value !== null));
            $result = $this->request('GET', '/meetings?' . $query);
            if (empty($result['ok'])) {
                return [
                    'ok' => false,
                    'error' => (string) ($result['error'] ?? 'No se pudo consultar reuniones Daily.'),
                    'participant_minutes' => (int) ceil($participantSeconds / 60),
                    'meeting_minutes' => (int) ceil($meetingSeconds / 60),
                    'meetings' => $meetings,
                    'participants' => count($participants),
                ];
            }

            $data = is_array($result['data']['data'] ?? null) ? $result['data']['data'] : [];
            foreach ($data as $meeting) {
                if (!is_array($meeting)) {
                    continue;
                }
                $meetings++;
                $meetingSeconds += max(0, (int) ($meeting['duration'] ?? 0));
                foreach ((array) ($meeting['participants'] ?? []) as $participant) {
                    if (!is_array($participant)) {
                        continue;
                    }
                    $participantSeconds += max(0, (int) ($participant['duration'] ?? 0));
                    $participantKey = (string) ($participant['user_id'] ?? $participant['participant_id'] ?? '');
                    if ($participantKey !== '') {
                        $participants[$participantKey] = true;
                    }
                }
            }

            $last = end($data);
            $startingAfter = is_array($last) ? (string) ($last['id'] ?? '') : '';
            $totalCount = (int) ($result['data']['total_count'] ?? $meetings);
        } while ($startingAfter !== '' && $meetings < $totalCount);

        return [
            'ok' => true,
            'error' => '',
            'participant_minutes' => (int) ceil($participantSeconds / 60),
            'meeting_minutes' => (int) ceil($meetingSeconds / 60),
            'meetings' => $meetings,
            'participants' => count($participants),
        ];
    }

    public static function futureMinutesProjection(array $subEvents): array
    {
        $items = [];
        $required = 0;
        $quantifiable = 0;
        $unquantifiable = 0;

        foreach ($subEvents as $subEvent) {
            if (!is_array($subEvent)) {
                continue;
            }
            $duration = max(0, (int) ($subEvent['duration_minutes'] ?? 0));
            $participants = max(0, (int) ($subEvent['participant_count'] ?? 0));
            $isQuantifiable = $duration > 0 && $participants > 0;
            $minutes = $isQuantifiable ? $duration * $participants : 0;
            if ($isQuantifiable) {
                $quantifiable++;
                $required += $minutes;
            } else {
                $unquantifiable++;
            }
            $subEvent['required_minutes'] = $minutes;
            $subEvent['quantifiable'] = $isQuantifiable;
            $items[] = $subEvent;
        }

        return [
            'items' => $items,
            'required_minutes' => $required,
            'quantifiable_count' => $quantifiable,
            'unquantifiable_count' => $unquantifiable,
        ];
    }

    private function roomProperties(): array
    {
        return [
            'enable_chat' => true,
            'enable_screenshare' => true,
            'enable_prejoin_ui' => false,
            'enable_live_captions_ui' => true,
            'enable_transcription_storage' => true,
            'start_audio_off' => true,
            'start_video_off' => false,
            'lang' => $this->lang(),
        ];
    }

    private function request(string $method, string $path, ?array $payload = null): array
    {
        $body = $payload === null ? '' : json_encode($payload, JSON_UNESCAPED_SLASHES);
        if ($body === false) {
            return ['ok' => false, 'status' => 0, 'data' => [], 'error' => 'No se pudo serializar la solicitud Daily.'];
        }

        $url = rtrim((string) ($this->config['api_base_url'] ?? 'https://api.daily.co/v1'), '/') . $path;
        $headers = [
            'Authorization: Bearer ' . $this->apiKey(),
            'Content-Type: application/json',
        ];

        if (function_exists('curl_init')) {
            $curl = curl_init($url);
            if ($curl === false) {
                return ['ok' => false, 'status' => 0, 'data' => [], 'error' => 'No se pudo iniciar cURL.'];
            }

            curl_setopt_array($curl, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CUSTOMREQUEST => $method,
                CURLOPT_HTTPHEADER => $headers,
                CURLOPT_TIMEOUT => 15,
            ]);
            if ($payload !== null) {
                curl_setopt($curl, CURLOPT_POSTFIELDS, $body);
            }

            $response = curl_exec($curl);
            $status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
            $curlError = curl_error($curl);
            curl_close($curl);

            return $this->parseResponse($response === false ? '' : (string) $response, $status, $curlError);
        }

        $context = stream_context_create([
            'http' => [
                'method' => $method,
                'header' => implode("\r\n", $headers),
                'content' => $payload !== null ? $body : '',
                'timeout' => 15,
                'ignore_errors' => true,
            ],
        ]);
        $response = @file_get_contents($url, false, $context);
        $status = $this->statusFromHeaders($http_response_header ?? []);

        return $this->parseResponse($response === false ? '' : (string) $response, $status, $response === false ? 'No se pudo conectar con Daily.' : '');
    }

    private function parseResponse(string $response, int $status, string $transportError = ''): array
    {
        $data = $response !== '' ? json_decode($response, true) : [];
        if (!is_array($data)) {
            $data = [];
        }

        $error = $transportError !== ''
            ? $transportError
            : (string) ($data['error'] ?? $data['info'] ?? $data['message'] ?? '');
        if ($error === '' && $status === 0) {
            $error = 'No se pudo conectar con Daily.';
        }
        if ($error === '' && ($status < 200 || $status >= 300)) {
            $error = 'Daily respondio con codigo HTTP ' . $status . '.';
        }

        return [
            'ok' => $status >= 200 && $status < 300,
            'status' => $status,
            'data' => $data,
            'error' => $error,
        ];
    }

    private function statusFromHeaders(array $headers): int
    {
        foreach ($headers as $header) {
            if (preg_match('/^HTTP\/\S+\s+(\d{3})/', (string) $header, $matches)) {
                return (int) $matches[1];
            }
        }

        return 0;
    }

    private function roomName(string $slug): string
    {
        $slug = strtolower(trim($slug));
        $slug = preg_replace('/[^a-z0-9_-]+/', '-', $slug) ?? '';
        $slug = trim($slug, '-_');

        return substr($slug !== '' ? $slug : 'daily-room', 0, 120);
    }

    private function domain(): string
    {
        $domain = strtolower(trim((string) ($this->config['domain'] ?? '')));
        $domain = preg_replace('/^https?:\/\//', '', $domain) ?? '';
        $domain = trim($domain, "/ \t\n\r\0\x0B");
        if (in_array($domain, ['tu-dominio.daily.co', 'your-domain.daily.co', 'example.daily.co'], true)) {
            return '';
        }

        return preg_match('/^[a-z0-9.-]+$/', $domain) ? $domain : '';
    }

    private function apiKey(): string
    {
        return trim((string) ($this->config['api_key'] ?? ''));
    }

    private function dailyJsUrl(): string
    {
        $url = trim((string) ($this->config['daily_js_url'] ?? ''));
        return $url !== '' ? $url : 'https://cdn.jsdelivr.net/npm/@daily-co/daily-js/dist/daily-iframe.js';
    }

    private function lang(): string
    {
        $lang = strtolower(trim((string) ($this->config['default_lang'] ?? 'es')));
        return preg_match('/^[a-z]{2}$/', $lang) ? $lang : 'es';
    }
}
