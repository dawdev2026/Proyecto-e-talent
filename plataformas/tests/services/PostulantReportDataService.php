<?php
declare(strict_types=1);

final class PostulantReportDataService
{
    private TestProcessModel $processes;
    private TestSessionModel $sessions;
    private TestSettingsModel $settings;
    private ProgressRankingSummaryService $ranking;

    public function __construct(
        ?TestProcessModel $processes = null,
        ?TestSessionModel $sessions = null,
        ?TestSettingsModel $settings = null,
        ?ProgressRankingSummaryService $ranking = null
    ) {
        $this->processes = $processes ?: new TestProcessModel();
        $this->sessions = $sessions ?: new TestSessionModel();
        $this->settings = $settings ?: new TestSettingsModel();
        $this->ranking = $ranking ?: new ProgressRankingSummaryService();
    }

    public function payloadForProcessUser(int $processId, int $userId): array
    {
        if ($processId <= 0 || $userId <= 0) {
            return ['available' => false, 'message' => 'No hay informe de evaluacion asociado.'];
        }

        $processUser = $this->processes->processUser($processId, $userId);
        if (!$processUser || (string) ($processUser['status'] ?? '') === 'cancelled') {
            return ['available' => false, 'message' => 'No se encontro el postulante activo en el proceso de evaluacion.'];
        }

        $process = $this->processes->find($processId) ?: [];
        $sessions = $this->processes->processDashboardSessionsForUser($processId, $userId);
        $selectedInstrumentIds = $this->processes->selectedInstrumentIds($processId);
        $rankingSessions = $this->rankingSessions([$processUser], $sessions, $this->processes->activeInstruments(), $selectedInstrumentIds);
        $officialRankingConfig = $this->ranking->officialConfig();
        $rankingConfig = $this->ranking->activeConfig($this->settings->rankingConfigForCompany(
            (int) ($process['company_id'] ?? ($processUser['company_id'] ?? 0)),
            $officialRankingConfig
        ));
        $ranking = $this->ranking->build($rankingSessions, function (int $sessionId, array $session): array {
            return $this->sessionSummary($sessionId, $session);
        }, $rankingConfig);

        $row = (is_array($ranking['rows'] ?? null) && isset($ranking['rows'][0])) ? $ranking['rows'][0] : null;
        if (!$row) {
            return [
                'available' => false,
                'message' => 'El postulante no tiene datos suficientes para generar el informe psicometrico.',
                'warnings' => $ranking['warnings'] ?? [],
            ];
        }

        $summariesByCode = $this->summariesByCode($sessions);
        $scaleMap = new PostulantReportScaleMapService();
        $payload = [
            'available' => true,
            'generated_at' => date('d-m-Y H:i'),
            'process' => [
                'id' => $processId,
                'name' => (string) ($process['name'] ?? 'Proceso'),
                'code' => (string) ($process['code'] ?? ''),
                'company_id' => (int) ($process['company_id'] ?? 0),
            ],
            'candidate' => [
                'id' => $userId,
                'name' => (string) ($processUser['name'] ?? ''),
                'rut' => (string) ($processUser['rut'] ?? ''),
                'email' => (string) ($processUser['email'] ?? ''),
                'age' => (string) ($processUser['age'] ?? ''),
                'company' => (string) ($processUser['company_name'] ?? ''),
                'company_id' => (int) ($processUser['company_id'] ?? 0),
            ],
            'ranking_row' => $row,
            'ipip_factors' => $scaleMap->mappedSummary($summariesByCode['ipip_16pf'] ?? []),
            'riasec_result' => $this->riasecResult($sessions, $summariesByCode),
            'summaries' => $summariesByCode,
        ];

        return $payload + ['structured_summary' => $this->structuredSummary($payload)];
    }

    public function structuredSummary(array $payload): array
    {
        if (empty($payload['available'])) {
            return ['available' => false, 'message' => (string) ($payload['message'] ?? 'Informe no disponible.')];
        }

        $personality = $this->personalityCards($payload);
        $cognitive = $this->cognitiveBars($payload);
        $impulse = $this->impulseRadar($payload);
        $vocational = $this->vocationalCards($payload);
        $row = is_array($payload['ranking_row'] ?? null) ? $payload['ranking_row'] : [];

        return [
            'available' => true,
            'classification' => [
                'label' => (string) ($row['Clasificacion'] ?? ''),
                'final_score' => $this->nullableNumber($row['Puntaje Final'] ?? null),
                'total_score' => $this->nullableNumber($row['Puntaje Total'] ?? null),
                'knockout' => (string) ($row['Knockout Activado'] ?? 'NO'),
                'knockout_detail' => (string) ($row['Detalle Knockout'] ?? '-'),
            ],
            'personality' => $personality,
            'cognitive' => $cognitive,
            'impulse_self_regulation' => $impulse,
            'vocational' => $vocational,
            'strengths' => [
                'personality' => $this->topLabels($personality, 'score', 2),
                'cognitive' => $this->topLabels($cognitive, 'value', 1),
            ],
            'development_areas' => $this->developmentAreas($personality, $cognitive, $impulse),
            'consistency_alerts' => $this->consistencyAlerts($row, $personality, $cognitive, $impulse),
            'missing_data' => $this->missingData($personality, $cognitive, $impulse, $vocational),
            'usage_rules' => [
                'Usar estos datos como indicadores con contexto, no como diagnosticos clinicos.',
                'No inventar puntajes ni citar dimensiones marcadas como Sin dato.',
                'Contrastar siempre con transcripcion, apuntes y conducta observable de entrevista.',
            ],
        ];
    }

    private function rankingSessions(array $processUsers, array $sessions, array $instruments, array $selectedInstrumentIds): array
    {
        $selected = [];
        foreach ($instruments as $instrument) {
            $id = (int) ($instrument['id'] ?? 0);
            if ($id > 0 && in_array($id, $selectedInstrumentIds, true)) {
                $selected[$id] = $instrument;
            }
        }

        $sessionsByUserInstrument = [];
        foreach ($sessions as $session) {
            $sessionsByUserInstrument[(int) ($session['user_id'] ?? 0)][(int) ($session['instrument_id'] ?? 0)] = $session;
        }

        $rankingSessions = [];
        foreach ($processUsers as $user) {
            if ((string) ($user['status'] ?? '') === 'cancelled') {
                continue;
            }

            foreach ($selected as $instrumentId => $instrument) {
                $session = $sessionsByUserInstrument[(int) ($user['user_id'] ?? $user['id'] ?? 0)][$instrumentId] ?? null;
                if ($session && (string) ($session['status'] ?? '') !== 'cancelled') {
                    $session['user_rut'] = (string) ($session['user_rut'] ?? $user['rut'] ?? '');
                    $session['user_name'] = (string) ($session['user_name'] ?? $user['name'] ?? '');
                    $rankingSessions[] = $session;
                    continue;
                }

                $rankingSessions[] = [
                    'id' => 0,
                    'user_id' => (int) ($user['user_id'] ?? $user['id'] ?? 0),
                    'user_rut' => (string) ($user['rut'] ?? ''),
                    'user_name' => (string) ($user['name'] ?? ''),
                    'instrument_id' => $instrumentId,
                    'instrument_name' => (string) ($instrument['name'] ?? ''),
                    'instrument_code' => (string) ($instrument['code'] ?? ''),
                    'status' => 'missing',
                ];
            }
        }

        return $rankingSessions;
    }

    private function sessionSummary(int $sessionId, array $session): array
    {
        if ($sessionId <= 0 || !in_array((string) ($session['status'] ?? ''), ['completed', 'expired'], true)) {
            return [];
        }

        $summary = $this->sessions->summaryForSession($sessionId);
        if (!$summary && (string) ($session['status'] ?? '') === 'expired') {
            $summary = $this->sessions->complete($sessionId, [], 'expired');
        }

        return $summary;
    }

    private function summariesByCode(array $sessions): array
    {
        $summaries = [];
        $sessionIds = [];
        $sessionCodes = [];
        $sessionsById = [];
        foreach ($sessions as $session) {
            $sessionId = (int) ($session['id'] ?? 0);
            $code = (string) ($session['instrument_code'] ?? '');
            if ($sessionId <= 0 || $code === '' || (string) ($session['status'] ?? '') === 'cancelled') {
                continue;
            }
            if (!in_array($code, ['ipip_16pf', 'cag_wonderlic', 'cag', 'ticl_barratt', 'riasec'], true)) {
                continue;
            }
            $sessionIds[] = $sessionId;
            $sessionCodes[$sessionId] = $code;
            $sessionsById[$sessionId] = $session;
        }

        $summaryRows = $this->sessions->summariesForSessions($sessionIds);
        foreach ($sessionCodes as $sessionId => $code) {
            $summary = $summaryRows[$sessionId] ?? [];
            if (!$summary && isset($sessionsById[$sessionId])) {
                $summary = $this->sessionSummary($sessionId, $sessionsById[$sessionId]);
            }
            if ($summary) {
                $summaries[$code] = $summary;
            }
        }

        return $summaries;
    }

    private function riasecResult(array $sessions, array $summariesByCode): ?array
    {
        if (empty($summariesByCode['riasec'])) {
            return null;
        }

        foreach ($sessions as $session) {
            if ((string) ($session['instrument_code'] ?? '') === 'riasec') {
                return (new RiasecRecommendationService())->buildForSession($session, $summariesByCode['riasec']);
            }
        }

        return null;
    }

    private function personalityCards(array $payload): array
    {
        $factors = $this->factorScores($payload['ipip_factors'] ?? []);
        $cards = [
            ['key' => 'responsabilidad', 'label' => 'Responsabilidad', 'score' => $this->avg($factors, ['G', 'Q3']), 'formula' => '(G + Q3) / 2'],
            ['key' => 'estabilidad', 'label' => 'Estabilidad Emocional', 'score' => $this->avgDirect([$factors['C'] ?? null, $this->inverse($factors['Q4'] ?? null), $this->inverse($factors['O'] ?? null)]), 'formula' => '(C + (11-Q4) + (11-O)) / 3'],
            ['key' => 'amabilidad', 'label' => 'Amabilidad', 'score' => $this->avgDirect([$factors['A'] ?? null, $this->inverse($factors['L'] ?? null)]), 'formula' => '(A + (11-L)) / 2'],
            ['key' => 'apertura', 'label' => 'Apertura Mental', 'score' => $this->avg($factors, ['Q1', 'M', 'I']), 'formula' => '(Q1 + M + I) / 3'],
            ['key' => 'sociabilidad', 'label' => 'Sociabilidad', 'score' => $this->avg($factors, ['A', 'F', 'H']), 'formula' => '(A + F + H) / 3'],
            ['key' => 'dinamismo', 'label' => 'Dinamismo', 'score' => $this->avg($factors, ['E', 'H', 'Q1']), 'formula' => '(E + H + Q1) / 3'],
        ];

        return array_map(function (array $card): array {
            $score = $card['score'];
            return $card + [
                'score' => $score !== null ? round(max(1.0, min(10.0, (float) $score)), 2) : null,
                'band' => $score !== null ? $this->stenBand((float) $score) : 'sin_dato',
            ];
        }, $cards);
    }

    private function cognitiveBars(array $payload): array
    {
        $row = is_array($payload['ranking_row'] ?? null) ? $payload['ranking_row'] : [];
        $factors = $this->factorScores($payload['ipip_factors'] ?? []);
        $sten = $this->validSten($row['Sten Wonderlic'] ?? null);
        $aptitude = $sten !== null ? $this->stenPercent($sten) : null;
        $reasoning = isset($factors['B']) ? $this->stenPercent((float) $factors['B']) : null;
        $performance = is_numeric($row['D3 Rendimiento'] ?? null) ? $this->clampPercent((float) $row['D3 Rendimiento']) : null;

        return [
            ['label' => 'Aptitud Cognitiva', 'value' => $aptitude, 'source' => 'ranking_row.Sten Wonderlic'],
            ['label' => 'Razonamiento', 'value' => $reasoning, 'source' => 'ipip_factors.B'],
            ['label' => 'Rendimiento y Adaptacion', 'value' => $performance, 'source' => 'ranking_row.D3 Rendimiento'],
        ];
    }

    private function impulseRadar(array $payload): array
    {
        $row = is_array($payload['ranking_row'] ?? null) ? $payload['ranking_row'] : [];
        $factors = $this->factorScores($payload['ipip_factors'] ?? []);
        $ticl = is_numeric($row['Impulsividad Total TICL'] ?? null) ? (float) $row['Impulsividad Total TICL'] : null;
        $control = $ticl !== null ? $this->clampPercent(((120.0 - $ticl) / 90.0) * 100.0) : null;
        $fInverse = $this->inverse($factors['F'] ?? null);
        $reflexivity = ($control !== null && $fInverse !== null) ? $this->clampPercent((($control / 10.0) + $fInverse) / 2.0 * 10.0) : null;
        $calm = $this->validSten($row['Sten Calma'] ?? null) ?? $this->inverse($factors['Q4'] ?? null);
        $selfRegulation = $calm !== null ? $this->stenPercent($calm) : null;
        $frustration = (isset($factors['C'], $factors['Q4']))
            ? $this->clampPercent((((float) $factors['C'] + (11.0 - (float) $factors['Q4'])) / 2.0) * 10.0)
            : null;

        return [
            ['label' => 'Control de Impulsos', 'value' => $control, 'source' => 'TICL total normalizado'],
            ['label' => 'Reflexividad', 'value' => $reflexivity, 'source' => 'Control de Impulsos + IPIP F invertido'],
            ['label' => 'Autorregulacion', 'value' => $selfRegulation, 'source' => 'Sten Calma o Q4 invertido'],
            ['label' => 'Tolerancia a Frustracion', 'value' => $frustration, 'source' => 'C + Q4 invertido'],
        ];
    }

    private function vocationalCards(array $payload): array
    {
        $riasec = is_array($payload['riasec_result'] ?? null) ? $payload['riasec_result'] : null;
        $scales = is_array($riasec['scales'] ?? null) ? array_slice($riasec['scales'], 0, 2) : [];
        if (!$scales) {
            return ['available' => false, 'message' => 'Sin datos vocacionales disponibles.'];
        }

        return [
            'available' => true,
            'profile_code' => (string) ($riasec['code'] ?? ''),
            'dominant_code' => (string) ($riasec['dominant_code'] ?? ''),
            'exploration' => (float) ($scales[0]['percentage'] ?? 0) < 40.0,
            'scales' => array_map(static fn(array $scale): array => [
                'code' => (string) ($scale['code'] ?? ''),
                'name' => (string) ($scale['name'] ?? ''),
                'percentage' => round((float) ($scale['percentage'] ?? 0), 2),
            ], $scales),
        ];
    }

    private function factorScores(array $factors): array
    {
        $scores = [];
        foreach ($factors as $factor) {
            $code = strtoupper((string) ($factor['code'] ?? ''));
            if ($code !== '' && is_numeric($factor['score'] ?? null)) {
                $scores[$code] = (float) $factor['score'];
            }
        }

        return $scores;
    }

    private function avg(array $scores, array $keys): ?float
    {
        $values = [];
        foreach ($keys as $key) {
            if (isset($scores[$key]) && is_numeric($scores[$key])) {
                $values[] = (float) $scores[$key];
            }
        }

        return $this->avgDirect($values);
    }

    private function avgDirect(array $values): ?float
    {
        $values = array_values(array_filter($values, static fn($value): bool => is_numeric($value)));
        return $values ? array_sum($values) / count($values) : null;
    }

    private function inverse($score): ?float
    {
        return is_numeric($score) ? 11.0 - (float) $score : null;
    }

    private function validSten($value): ?float
    {
        if (!is_numeric($value)) {
            return null;
        }

        $sten = (float) $value;
        return $sten > 0.0 ? min(10.0, max(1.0, $sten)) : null;
    }

    private function stenPercent(float $sten): float
    {
        return round($this->clampPercent(min(10.0, max(1.0, $sten)) * 10.0), 2);
    }

    private function clampPercent(float $value): float
    {
        return round(max(0.0, min(100.0, $value)), 2);
    }

    private function nullableNumber($value): ?float
    {
        return is_numeric($value) ? round((float) $value, 2) : null;
    }

    private function stenBand(float $score): string
    {
        if ($score >= 8.0) {
            return 'fortaleza_clara';
        }
        if ($score >= 6.0) {
            return 'adecuado_con_desarrollo';
        }

        return 'oportunidad_de_desarrollo';
    }

    private function percentBand(float $value): string
    {
        if ($value >= 75.0) {
            return 'superior';
        }
        if ($value >= 55.0) {
            return 'bueno';
        }

        return 'en_desarrollo';
    }

    private function topLabels(array $items, string $key, int $limit): array
    {
        $available = array_values(array_filter($items, static fn(array $item): bool => is_numeric($item[$key] ?? null)));
        usort($available, static fn(array $a, array $b): int => ((float) $b[$key]) <=> ((float) $a[$key]));

        return array_slice(array_map(static fn(array $item): array => [
            'label' => (string) ($item['label'] ?? ''),
            'value' => round((float) ($item[$key] ?? 0), 2),
        ], $available), 0, $limit);
    }

    private function developmentAreas(array $personality, array $cognitive, array $impulse): array
    {
        $areas = [];
        foreach ($personality as $item) {
            if (is_numeric($item['score'] ?? null) && (float) $item['score'] <= 6.0) {
                $areas[] = ['label' => (string) $item['label'], 'value' => (float) $item['score'], 'domain' => 'personalidad'];
            }
        }
        foreach (array_merge($cognitive, $impulse) as $item) {
            if (is_numeric($item['value'] ?? null) && (float) $item['value'] < 55.0) {
                $areas[] = ['label' => (string) $item['label'], 'value' => (float) $item['value'], 'domain' => 'desempeno'];
            }
        }
        usort($areas, static fn(array $a, array $b): int => ((float) $a['value']) <=> ((float) $b['value']));

        return array_slice($areas, 0, 4);
    }

    private function consistencyAlerts(array $row, array $personality, array $cognitive, array $impulse): array
    {
        $alerts = [];
        $knockoutDetail = strtolower((string) ($row['Detalle Knockout'] ?? ''));
        $personalityByKey = [];
        foreach ($personality as $item) {
            $personalityByKey[(string) ($item['key'] ?? '')] = $item;
        }
        $cognitiveByLabel = [];
        foreach ($cognitive as $item) {
            $cognitiveByLabel[(string) ($item['label'] ?? '')] = $item;
        }
        $impulseByLabel = [];
        foreach ($impulse as $item) {
            $impulseByLabel[(string) ($item['label'] ?? '')] = $item;
        }

        if ($this->contains($knockoutDetail, 'estabilidad') && is_numeric($personalityByKey['estabilidad']['score'] ?? null) && (float) $personalityByKey['estabilidad']['score'] >= 7.0) {
            $alerts[] = 'Knockout por estabilidad no coincide con tarjeta de Estabilidad Emocional >= 7.';
        }
        if (($this->contains($knockoutDetail, 'cognitivo') || $this->contains($knockoutDetail, 'wonderlic')) && is_numeric($cognitiveByLabel['Aptitud Cognitiva']['value'] ?? null) && (float) $cognitiveByLabel['Aptitud Cognitiva']['value'] >= 60.0) {
            $alerts[] = 'Knockout cognitivo no coincide con Aptitud Cognitiva >= 60%.';
        }
        if ($this->contains($knockoutDetail, 'impulsividad') && is_numeric($impulseByLabel['Control de Impulsos']['value'] ?? null) && (float) $impulseByLabel['Control de Impulsos']['value'] >= 70.0) {
            $alerts[] = 'Knockout por impulsividad no coincide con Control de Impulsos >= 70%.';
        }

        return $alerts;
    }

    private function missingData(array $personality, array $cognitive, array $impulse, array $vocational): array
    {
        $missing = [];
        foreach ($personality as $item) {
            if (!is_numeric($item['score'] ?? null)) {
                $missing[] = 'Personalidad: ' . (string) ($item['label'] ?? 'dimension');
            }
        }
        foreach (array_merge($cognitive, $impulse) as $item) {
            if (!is_numeric($item['value'] ?? null)) {
                $missing[] = (string) ($item['label'] ?? 'Indicador');
            }
        }
        if (empty($vocational['available'])) {
            $missing[] = 'RIASEC';
        }

        return $missing;
    }

    private function contains(string $haystack, string $needle): bool
    {
        return $needle === '' || strpos($haystack, $needle) !== false;
    }
}
