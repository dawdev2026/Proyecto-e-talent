<?php
declare(strict_types=1);

final class ProgressRankingSummaryService
{
    public const SHEET_TITLE = 'Ranking Resumen';

    public function build(array $sessions, callable $summaryResolver, ?array $config = null): array
    {
        $config = $this->normalizeConfig($config ?? $this->officialConfig());
        $people = [];
        foreach ($sessions as $session) {
            $rut = trim((string) ($session['user_rut'] ?? ''));
            if ($rut === '') {
                continue;
            }

            $code = (string) ($session['instrument_code'] ?? '');
            if (!$this->isRankingInstrumentCode($code)) {
                continue;
            }

            $summary = $summaryResolver((int) $session['id'], $session);
            $person = $people[$rut] ?? [
                'rut' => $rut,
                'name' => (string) ($session['user_name'] ?? ''),
                'user_id' => (int) ($session['user_id'] ?? 0),
                'process_id' => (int) ($session['process_id'] ?? 0),
                'session_ids' => [],
                'ipip' => [],
                'cag_percentile' => null,
                'cag_raw' => null,
                'ticl_total' => null,
                'missing' => [],
            ];
            $person['user_id'] = (int) ($person['user_id'] ?: ($session['user_id'] ?? 0));
            $person['process_id'] = (int) ($person['process_id'] ?: ($session['process_id'] ?? 0));

            if ($code === 'ipip_16pf') {
                $person['ipip'] = $this->summaryByName($summary);
                $person['session_ids']['ipip_16pf'] = (int) ($session['id'] ?? 0);
            } elseif (in_array($code, ['cag_wonderlic', 'cag'], true)) {
                $person['cag_percentile'] = $this->cagPercentile($summary);
                $person['cag_raw'] = $this->cagRawScore($summary);
                $person['session_ids']['cag'] = (int) ($session['id'] ?? 0);
            } elseif ($code === 'ticl_barratt') {
                $person['ticl_total'] = $this->validTiclTotal(
                    $this->summaryValueByNames($summary, ['Impulsividad total'], ['raw_score', 'score', 'adjusted_score', 'transformed_score'])
                );
                $person['session_ids']['ticl_barratt'] = (int) ($session['id'] ?? 0);
            }

            $people[$rut] = $person;
        }

        $ranked = [];
        $warnings = [];
        foreach ($people as $person) {
            $row = $this->rankingRow($person, $config);
            if ($row === null) {
                $missing = $this->missingRequirements($person, $config);
                $warnings[] = [
                    'rut' => $person['rut'],
                    'name' => $person['name'],
                    'warning' => 'Datos insuficientes: falta ' . implode(', ', $missing) . '. No incluido en ranking.',
                ];
                continue;
            }

            $ranked[] = $row;
        }

        usort($ranked, static function (array $a, array $b): int {
            $score = ($b['Puntaje Final'] <=> $a['Puntaje Final']);
            return $score !== 0 ? $score : strcmp((string) $a['Nombre completo'], (string) $b['Nombre completo']);
        });

        $rank = 0;
        $previousScore = null;
        foreach ($ranked as $index => &$row) {
            if ($previousScore === null || (float) $row['Puntaje Final'] !== $previousScore) {
                $rank = $index + 1;
                $previousScore = (float) $row['Puntaje Final'];
            }
            $row = ['Ranking' => $rank] + $row;
        }
        unset($row);

        return [
            'rows' => $ranked,
            'warnings' => $warnings,
            'config' => $config,
            'summary' => $this->dashboardSummary(['rows' => $ranked, 'warnings' => $warnings]),
        ];
    }

    public function officialConfig(): array
    {
        return [
            'version' => 1,
            'preset' => 'official_matrix_16pf_ipip_2026',
            'name' => 'Matriz oficial 16PF-IPIP 2026',
            'description' => 'Matriz oficial sin RIASEC. Usa IPIP-16PF y CAG/Wonderlic propio.',
            'renormalize_active_weights' => true,
            'classification' => [
                'recommended_min' => 70.0,
                'observation_min' => 40.0,
                'knockout_forces_not_recommended' => true,
                'knockout_score_cap_enabled' => false,
                'knockout_score_cap' => 64.0,
            ],
            'dimensions' => [
                'D1' => [
                    'label' => 'Estabilidad Emocional',
                    'enabled' => true,
                    'weight' => 25.0,
                    'components' => [
                        ['key' => 'C', 'label' => 'C - Estabilidad emocional', 'source' => 'ipip', 'transform' => 'direct', 'weight' => 40.0, 'enabled' => true],
                        ['key' => 'Q4', 'label' => 'Q4 - Tension', 'source' => 'ipip', 'transform' => 'inverse', 'weight' => 30.0, 'enabled' => true],
                        ['key' => 'O', 'label' => 'O - Aprension', 'source' => 'ipip', 'transform' => 'inverse', 'weight' => 20.0, 'enabled' => true],
                        ['key' => 'F', 'label' => 'F - Animacion', 'source' => 'ipip', 'transform' => 'direct', 'weight' => 10.0, 'enabled' => true],
                    ],
                ],
                'D2' => [
                    'label' => 'Competencias Sociales',
                    'enabled' => true,
                    'weight' => 20.0,
                    'components' => [
                        ['key' => 'E', 'label' => 'E - Dominancia', 'source' => 'ipip', 'transform' => 'direct', 'weight' => 25.0, 'enabled' => true],
                        ['key' => 'H', 'label' => 'H - Atrevimiento', 'source' => 'ipip', 'transform' => 'direct', 'weight' => 25.0, 'enabled' => true],
                        ['key' => 'A', 'label' => 'A - Afabilidad', 'source' => 'ipip', 'transform' => 'direct', 'weight' => 20.0, 'enabled' => true],
                        ['key' => 'G', 'label' => 'G - Atencion a normas', 'source' => 'ipip', 'transform' => 'direct', 'weight' => 15.0, 'enabled' => true],
                        ['key' => 'L', 'label' => 'L - Vigilancia', 'source' => 'ipip', 'transform' => 'inverse', 'weight' => 15.0, 'enabled' => true],
                    ],
                ],
                'D3' => [
                    'label' => 'Rendimiento / Adaptacion',
                    'enabled' => true,
                    'weight' => 20.0,
                    'components' => [
                        ['key' => 'CAG', 'label' => 'CAG - Aptitud cognitiva', 'source' => 'cag', 'transform' => 'direct', 'weight' => 40.0, 'enabled' => true],
                        ['key' => 'Q3', 'label' => 'Q3 - Perfeccionismo', 'source' => 'ipip', 'transform' => 'direct', 'weight' => 20.0, 'enabled' => true],
                        ['key' => 'G', 'label' => 'G - Atencion a normas', 'source' => 'ipip', 'transform' => 'direct', 'weight' => 15.0, 'enabled' => true],
                        ['key' => 'Q1', 'label' => 'Q1 - Apertura al cambio', 'source' => 'ipip', 'transform' => 'direct', 'weight' => 15.0, 'enabled' => true],
                        ['key' => 'M', 'label' => 'M - Abstraccion', 'source' => 'ipip', 'transform' => 'direct', 'weight' => 10.0, 'enabled' => true],
                    ],
                ],
                'D4' => [
                    'label' => 'Vocacion Militar',
                    'enabled' => true,
                    'weight' => 20.0,
                    'components' => [
                        ['key' => 'G', 'label' => 'G - Atencion a normas', 'source' => 'ipip', 'transform' => 'direct', 'weight' => 45.0, 'enabled' => true],
                        ['key' => 'O', 'label' => 'O - Aprension', 'source' => 'ipip', 'transform' => 'inverse', 'weight' => 20.0, 'enabled' => true],
                        ['key' => 'EG', 'label' => 'Combinatoria E+G', 'source' => 'ipip_combo', 'transform' => 'direct', 'weight' => 35.0, 'enabled' => true],
                    ],
                ],
                'D5' => [
                    'label' => 'Ajuste Interpersonal',
                    'enabled' => true,
                    'weight' => 15.0,
                    'components' => [
                        ['key' => 'Q2', 'label' => 'Q2 - Autosuficiencia', 'source' => 'ipip', 'transform' => 'inverse', 'weight' => 30.0, 'enabled' => true],
                        ['key' => 'N', 'label' => 'N - Privacidad', 'source' => 'ipip', 'transform' => 'inverse', 'weight' => 25.0, 'enabled' => true],
                        ['key' => 'O', 'label' => 'O - Aprension', 'source' => 'ipip', 'transform' => 'inverse', 'weight' => 25.0, 'enabled' => true],
                        ['key' => 'F', 'label' => 'F - Animacion', 'source' => 'ipip', 'transform' => 'direct', 'weight' => 20.0, 'enabled' => true],
                    ],
                ],
            ],
            'knockouts' => [
                'stability' => ['label' => 'Estabilidad', 'enabled' => true, 'metric' => 'C', 'operator' => '<', 'threshold' => 5.5],
                'calm' => ['label' => 'Calma', 'enabled' => true, 'metric' => 'CALM', 'operator' => '<', 'threshold' => 4.0],
                'cognitive_sten' => ['label' => 'Cognitivo Sten', 'enabled' => true, 'metric' => 'CAG_STEN', 'operator' => '<', 'threshold' => 3.5],
                'cognitive_raw' => ['label' => 'Cognitivo bruto', 'enabled' => true, 'metric' => 'CAG_RAW', 'operator' => '<=', 'threshold' => 7.0],
                'impulsivity' => ['label' => 'Impulsividad', 'enabled' => false, 'metric' => 'TICL_TOTAL', 'operator' => '>', 'threshold' => 0.0],
            ],
        ];
    }

    public function sheetRows(array $ranking): array
    {
        $headers = [
            'Ranking',
            'RUT',
            'Nombre completo',
            'Sten Wonderlic',
            'Sten Calma',
            'D1 Estabilidad',
            'D2 Sociales',
            'D3 Rendimiento',
            'D4 Vocacion',
            'D5 Ajuste',
            'Puntaje Total',
            'Knockout Activado',
            'Detalle Knockout',
            'Puntaje Final',
            'Clasificacion',
            'Nomenclatura Reporte',
            'Impulsividad Total TICL',
        ];

        $rows = [$headers];
        foreach ($ranking['rows'] ?? [] as $row) {
            $rows[] = array_map(static fn(string $header) => $row[$header] ?? '', $headers);
        }

        if (!empty($ranking['warnings'])) {
            $rows[] = array_fill(0, count($headers), '');
            $rows[] = ['Advertencias', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', ''];
            foreach ($ranking['warnings'] as $warning) {
                $rows[] = [
                    '',
                    $warning['rut'] ?? '',
                    $warning['name'] ?? '',
                    $warning['warning'] ?? '',
                    '',
                    '',
                    '',
                    '',
                    '',
                    '',
                    '',
                    '',
                    '',
                    '',
                    '',
                    '',
                    '',
                ];
            }
        }

        return $rows;
    }

    public function baseRulesRows(): array
    {
        $config = $this->officialConfig();
        $rows = [
            ['DOCUMENTACION DEL ALGORITMO DE RANKING - MATRIZ OFICIAL', ''],
            ['', ''],
            ['Preset protegido', (string) $config['name']],
            ['Escala base', 'Factores IPIP-16PF en Sten 1-10 transformados a 0-100. CAG ingresa como Sten derivado de percentil cuando no exista Sten bruto disponible.'],
            ['RIASEC', 'No interviene en R / RO / NR.'],
            ['', ''],
            ['1. DIMENSIONES Y PESOS', ''],
        ];

        foreach ($config['dimensions'] as $dimensionKey => $dimension) {
            $rows[] = [
                $dimensionKey . ' - ' . (string) $dimension['label'] . ' (' . $this->round((float) $dimension['weight']) . '%)',
                $this->dimensionFormulaText($dimension),
            ];
        }

        $rows[] = ['Puntaje Total', 'Suma ponderada de dimensiones activas. Si se desactiva una dimension o criterio, los pesos activos se renormalizan para conservar escala 0-100.'];
        $rows[] = ['', ''];
        $rows[] = ['2. KNOCKOUT', ''];
        foreach ($config['knockouts'] as $knockout) {
            $rows[] = [
                (string) $knockout['label'],
                ((bool) $knockout['enabled'] ? 'Activo: ' : 'Inactivo: ') . (string) $knockout['metric'] . ' ' . (string) $knockout['operator'] . ' ' . (string) $knockout['threshold'],
            ];
        }
        $rows[] = ['', ''];
        $rows[] = ['3. CLASIFICACION', ''];
        $rows[] = ['Puntaje >= 70 y sin knockout', 'Recomendado: R'];
        $rows[] = ['Puntaje 40-69,99 y sin knockout', 'Recomendado con Observacion: RO'];
        $rows[] = ['Puntaje < 40 o knockout', 'No Recomendado: NR'];
        $rows[] = ['', ''];
        $rows[] = ['4. NOTAS', ''];
        $rows[] = ['Preset oficial', 'La matriz oficial no se borra; la configuracion personalizada puede restaurarse a estos valores.'];
        $rows[] = ['Impulsividad TICL', 'La matriz oficial la contempla como knockout pendiente de homologacion; queda inactiva por defecto hasta definir cutoff definitivo.'];

        return $rows;
    }

    public function activeConfig(array $storedConfig = []): array
    {
        return $this->normalizeConfig($storedConfig ?: $this->officialConfig());
    }

    public function configFromRequest(array $source): array
    {
        $base = $this->officialConfig();

        foreach ($base['dimensions'] as $dimensionKey => &$dimension) {
            $dimensionInput = is_array($source['dimensions'][$dimensionKey] ?? null) ? $source['dimensions'][$dimensionKey] : [];
            $dimension['enabled'] = isset($dimensionInput['enabled']);
            $dimension['weight'] = $this->floatFromInput($dimensionInput['weight'] ?? $dimension['weight'], (float) $dimension['weight'], 0.0, 100.0);

            foreach ($dimension['components'] as $componentIndex => &$component) {
                $componentInput = is_array($dimensionInput['components'][$componentIndex] ?? null) ? $dimensionInput['components'][$componentIndex] : [];
                $component['enabled'] = isset($componentInput['enabled']);
                $component['weight'] = $this->floatFromInput($componentInput['weight'] ?? $component['weight'], (float) $component['weight'], 0.0, 100.0);
            }
            unset($component);
        }
        unset($dimension);

        $classificationInput = is_array($source['classification'] ?? null) ? $source['classification'] : [];
        $base['classification']['recommended_min'] = $this->floatFromInput($classificationInput['recommended_min'] ?? 70, 70.0, 0.0, 100.0);
        $base['classification']['observation_min'] = $this->floatFromInput($classificationInput['observation_min'] ?? 40, 40.0, 0.0, 100.0);
        $base['classification']['knockout_forces_not_recommended'] = isset($classificationInput['knockout_forces_not_recommended']);
        $base['classification']['knockout_score_cap_enabled'] = isset($classificationInput['knockout_score_cap_enabled']);
        $base['classification']['knockout_score_cap'] = $this->floatFromInput($classificationInput['knockout_score_cap'] ?? 64, 64.0, 0.0, 100.0);

        foreach ($base['knockouts'] as $knockoutKey => &$knockout) {
            $knockoutInput = is_array($source['knockouts'][$knockoutKey] ?? null) ? $source['knockouts'][$knockoutKey] : [];
            $knockout['enabled'] = isset($knockoutInput['enabled']);
            $knockout['operator'] = $this->operatorFromInput($knockoutInput['operator'] ?? $knockout['operator']);
            $knockout['threshold'] = $this->floatFromInput($knockoutInput['threshold'] ?? $knockout['threshold'], (float) $knockout['threshold'], -9999.0, 9999.0);
        }
        unset($knockout);

        return $this->normalizeConfig($base);
    }

    public function isOfficialConfig(array $config): bool
    {
        return $this->configFingerprint($this->normalizeConfig($config)) === $this->configFingerprint($this->officialConfig());
    }

    public function dashboardSummary(array $ranking): array
    {
        $rows = is_array($ranking['rows'] ?? null) ? $ranking['rows'] : [];
        $warnings = is_array($ranking['warnings'] ?? null) ? $ranking['warnings'] : [];
        $summary = [
            'ranked' => count($rows),
            'warnings' => count($warnings),
            'recommended' => 0,
            'observation' => 0,
            'not_recommended' => 0,
            'knockouts' => 0,
            'score_min' => null,
            'score_avg' => null,
            'score_max' => null,
        ];
        $scores = [];
        foreach ($rows as $row) {
            $report = (string) ($row['Nomenclatura Reporte'] ?? '');
            if (strpos($report, 'R:') === 0) {
                $summary['recommended']++;
            } elseif (strpos($report, 'RO:') === 0) {
                $summary['observation']++;
            } else {
                $summary['not_recommended']++;
            }

            if ((string) ($row['Knockout Activado'] ?? 'NO') === 'SI') {
                $summary['knockouts']++;
            }

            if (isset($row['Puntaje Final']) && is_numeric($row['Puntaje Final'])) {
                $scores[] = (float) $row['Puntaje Final'];
            }
        }

        if ($scores) {
            $summary['score_min'] = $this->round(min($scores));
            $summary['score_avg'] = $this->round(array_sum($scores) / count($scores));
            $summary['score_max'] = $this->round(max($scores));
        }

        return $summary;
    }

    public function classificationCounts(array $ranking): array
    {
        $summary = $this->dashboardSummary($ranking);
        return [
            'R' => $summary['recommended'],
            'RO' => $summary['observation'],
            'NR' => $summary['not_recommended'],
        ];
    }

    private function rankingRow(array $person, array $config): ?array
    {
        $missing = $this->missingRequirements($person, $config);
        if ($missing) {
            return null;
        }

        $ipip = $person['ipip'];
        $stenWonderlic = $this->cagSten($person);
        $stenCalma = isset($ipip['Q4']) ? 11.0 - (float) $ipip['Q4'] : null;

        $dimensionScores = [];
        $activeDimensions = array_filter($config['dimensions'], static fn(array $dimension): bool => !empty($dimension['enabled']) && (float) ($dimension['weight'] ?? 0) > 0);
        $dimensionWeightTotal = array_sum(array_map(static fn(array $dimension): float => (float) ($dimension['weight'] ?? 0), $activeDimensions));
        $total = 0.0;

        foreach ($config['dimensions'] as $dimensionKey => $dimension) {
            if (empty($dimension['enabled'])) {
                $dimensionScores[$dimensionKey] = null;
                continue;
            }

            $score = $this->dimensionScore($person, $dimension);
            $dimensionScores[$dimensionKey] = $score;
            if ($score === null || $dimensionWeightTotal <= 0) {
                continue;
            }

            $weight = (float) ($dimension['weight'] ?? 0);
            $effectiveWeight = !empty($config['renormalize_active_weights'])
                ? ($weight / $dimensionWeightTotal)
                : ($weight / 100.0);
            $total += $score * $effectiveWeight;
        }

        $knockoutReasons = [];
        foreach ($config['knockouts'] as $knockout) {
            if (empty($knockout['enabled'])) {
                continue;
            }

            $metricValue = $this->knockoutMetricValue($person, (string) ($knockout['metric'] ?? ''));
            if ($metricValue === null) {
                continue;
            }

            $threshold = (float) ($knockout['threshold'] ?? 0);
            $operator = (string) ($knockout['operator'] ?? '<');
            $triggered = $this->compareMetric($metricValue, $operator, $threshold);
            if ($triggered) {
                $knockoutReasons[] = (string) ($knockout['label'] ?? $knockout['metric']) . '(' . (string) ($knockout['metric'] ?? '') . $operator . $this->round($threshold) . ')';
            }
        }

        $knockout = $knockoutReasons !== [];
        $classificationConfig = $config['classification'];
        $finalScore = $knockout && !empty($classificationConfig['knockout_score_cap_enabled'])
            ? min($total, (float) ($classificationConfig['knockout_score_cap'] ?? 64.0))
            : $total;

        if ($knockout && !empty($classificationConfig['knockout_forces_not_recommended'])) {
            $classification = 'No Recomendado';
            $report = 'NR: No recomendado';
        } elseif ($finalScore >= (float) ($classificationConfig['recommended_min'] ?? 70.0)) {
            $classification = 'Recomendado';
            $report = 'R: Recomendado';
        } elseif ($finalScore >= (float) ($classificationConfig['observation_min'] ?? 40.0)) {
            $classification = 'Recomendado con Observacion';
            $report = 'RO: Recomendado con observacion';
        } else {
            $classification = 'No Recomendado';
            $report = 'NR: No recomendado';
        }

        return [
            'RUT' => $person['rut'],
            'Nombre completo' => $person['name'],
            'Sten Wonderlic' => $stenWonderlic !== null ? $this->round($stenWonderlic) : '',
            'Sten Calma' => $stenCalma !== null ? $this->round($stenCalma) : '',
            'D1 Estabilidad' => $dimensionScores['D1'] !== null ? $this->round($dimensionScores['D1']) : 'No considerado',
            'D2 Sociales' => $dimensionScores['D2'] !== null ? $this->round($dimensionScores['D2']) : 'No considerado',
            'D3 Rendimiento' => $dimensionScores['D3'] !== null ? $this->round($dimensionScores['D3']) : 'No considerado',
            'D4 Vocacion' => $dimensionScores['D4'] !== null ? $this->round($dimensionScores['D4']) : 'No considerado',
            'D5 Ajuste' => $dimensionScores['D5'] !== null ? $this->round($dimensionScores['D5']) : 'No considerado',
            'Puntaje Total' => $this->round($total),
            'Knockout Activado' => $knockout ? 'SI' : 'NO',
            'Detalle Knockout' => $knockout ? implode(' ', $knockoutReasons) : '-',
            'Puntaje Final' => $this->round($finalScore),
            'Clasificacion' => $classification,
            'Nomenclatura Reporte' => $report,
            'Impulsividad Total TICL' => $person['ticl_total'] !== null ? $this->round((float) $person['ticl_total']) : '-',
            '_process_id' => (int) ($person['process_id'] ?? 0),
            '_user_id' => (int) ($person['user_id'] ?? 0),
            '_company_id' => (int) ($person['company_id'] ?? 0),
            '_report_session_id' => (int) (($person['session_ids']['ipip_16pf'] ?? 0) ?: ($person['session_ids']['cag'] ?? 0) ?: ($person['session_ids']['ticl_barratt'] ?? 0)),
        ];
    }

    private function missingRequirements(array $person, array $config): array
    {
        $missing = [];
        foreach ($this->requiredIpipKeys($config) as $key) {
            if (!isset($person['ipip'][$key]) || !is_numeric($person['ipip'][$key])) {
                $missing['IPIP-16PF'] = 'IPIP-16PF';
                break;
            }
        }

        if ($this->requiresCag($config) && (!isset($person['cag_percentile']) || !is_numeric($person['cag_percentile']))) {
            $missing['CAG'] = 'CAG Percentil';
        }

        return array_values($missing);
    }

    private function normalizeConfig(array $config): array
    {
        $base = $this->officialConfig();
        if (!$config) {
            return $base;
        }

        $base['classification'] = array_merge($base['classification'], is_array($config['classification'] ?? null) ? $config['classification'] : []);
        foreach ($base['dimensions'] as $dimensionKey => &$dimension) {
            $incomingDimension = is_array($config['dimensions'][$dimensionKey] ?? null) ? $config['dimensions'][$dimensionKey] : [];
            $dimension['enabled'] = array_key_exists('enabled', $incomingDimension) ? (bool) $incomingDimension['enabled'] : (bool) $dimension['enabled'];
            $dimension['weight'] = $this->floatFromInput($incomingDimension['weight'] ?? $dimension['weight'], (float) $dimension['weight'], 0.0, 100.0);

            foreach ($dimension['components'] as $componentIndex => &$component) {
                $incomingComponent = is_array($incomingDimension['components'][$componentIndex] ?? null) ? $incomingDimension['components'][$componentIndex] : [];
                $component['enabled'] = array_key_exists('enabled', $incomingComponent) ? (bool) $incomingComponent['enabled'] : (bool) $component['enabled'];
                $component['weight'] = $this->floatFromInput($incomingComponent['weight'] ?? $component['weight'], (float) $component['weight'], 0.0, 100.0);
            }
            unset($component);
        }
        unset($dimension);

        foreach ($base['knockouts'] as $knockoutKey => &$knockout) {
            $incomingKnockout = is_array($config['knockouts'][$knockoutKey] ?? null) ? $config['knockouts'][$knockoutKey] : [];
            $knockout['enabled'] = array_key_exists('enabled', $incomingKnockout) ? (bool) $incomingKnockout['enabled'] : (bool) $knockout['enabled'];
            $knockout['operator'] = $this->operatorFromInput($incomingKnockout['operator'] ?? $knockout['operator']);
            $knockout['threshold'] = $this->floatFromInput($incomingKnockout['threshold'] ?? $knockout['threshold'], (float) $knockout['threshold'], -9999.0, 9999.0);
        }
        unset($knockout);

        $base['classification']['recommended_min'] = $this->floatFromInput($base['classification']['recommended_min'] ?? 70, 70.0, 0.0, 100.0);
        $base['classification']['observation_min'] = $this->floatFromInput($base['classification']['observation_min'] ?? 40, 40.0, 0.0, 100.0);
        $base['classification']['knockout_forces_not_recommended'] = !empty($base['classification']['knockout_forces_not_recommended']);
        $base['classification']['knockout_score_cap_enabled'] = !empty($base['classification']['knockout_score_cap_enabled']);
        $base['classification']['knockout_score_cap'] = $this->floatFromInput($base['classification']['knockout_score_cap'] ?? 64, 64.0, 0.0, 100.0);

        return $base;
    }

    private function dimensionScore(array $person, array $dimension): ?float
    {
        $components = array_values(array_filter(
            $dimension['components'] ?? [],
            static fn(array $component): bool => !empty($component['enabled']) && (float) ($component['weight'] ?? 0) > 0
        ));
        if (!$components) {
            return null;
        }

        $weightTotal = array_sum(array_map(static fn(array $component): float => (float) ($component['weight'] ?? 0), $components));
        if ($weightTotal <= 0) {
            return null;
        }

        $score = 0.0;
        foreach ($components as $component) {
            $value = $this->componentValue100($person, $component);
            if ($value === null) {
                return null;
            }

            $score += $value * ((float) ($component['weight'] ?? 0) / $weightTotal);
        }

        return $score;
    }

    private function componentValue100(array $person, array $component): ?float
    {
        $key = (string) ($component['key'] ?? '');
        $source = (string) ($component['source'] ?? 'ipip');
        $transform = (string) ($component['transform'] ?? 'direct');

        if ($source === 'cag') {
            $sten = $this->cagSten($person);
            return $sten !== null ? $this->stenTo100($sten) : null;
        }

        if ($source === 'ipip_combo' && $key === 'EG') {
            if (!isset($person['ipip']['E'], $person['ipip']['G']) || !is_numeric($person['ipip']['E']) || !is_numeric($person['ipip']['G'])) {
                return null;
            }
            return $this->stenTo100(((float) $person['ipip']['E'] + (float) $person['ipip']['G']) / 2);
        }

        if (!isset($person['ipip'][$key]) || !is_numeric($person['ipip'][$key])) {
            return null;
        }

        $sten = (float) $person['ipip'][$key];
        if ($transform === 'inverse') {
            $sten = 11.0 - $sten;
        }

        return $this->stenTo100($sten);
    }

    private function stenTo100(float $sten): float
    {
        return (min(10.0, max(1.0, $sten)) - 1.0) / 9.0 * 100.0;
    }

    private function cagSten(array $person): ?float
    {
        if (!isset($person['cag_percentile']) || !is_numeric($person['cag_percentile'])) {
            return null;
        }

        return min(10.0, max(1.0, (float) $person['cag_percentile'] / 10.0));
    }

    private function knockoutMetricValue(array $person, string $metric): ?float
    {
        $ipip = $person['ipip'] ?? [];
        if ($metric === 'CALM') {
            return isset($ipip['Q4']) && is_numeric($ipip['Q4']) ? 11.0 - (float) $ipip['Q4'] : null;
        }
        if ($metric === 'CAG_STEN') {
            return $this->cagSten($person);
        }
        if ($metric === 'CAG_RAW') {
            return isset($person['cag_raw']) && is_numeric($person['cag_raw']) ? (float) $person['cag_raw'] : null;
        }
        if ($metric === 'TICL_TOTAL') {
            return isset($person['ticl_total']) && is_numeric($person['ticl_total']) ? (float) $person['ticl_total'] : null;
        }

        return isset($ipip[$metric]) && is_numeric($ipip[$metric]) ? (float) $ipip[$metric] : null;
    }

    private function validTiclTotal($value): ?float
    {
        if (!is_numeric($value)) {
            return null;
        }

        $total = (float) $value;
        return $total >= 30.0 && $total <= 120.0 ? $total : null;
    }

    private function requiredIpipKeys(array $config): array
    {
        $keys = [];
        foreach ($config['dimensions'] as $dimension) {
            if (empty($dimension['enabled'])) {
                continue;
            }
            foreach ($dimension['components'] ?? [] as $component) {
                if (empty($component['enabled'])) {
                    continue;
                }
                $source = (string) ($component['source'] ?? 'ipip');
                $key = (string) ($component['key'] ?? '');
                if ($source === 'ipip' && $key !== '') {
                    $keys[$key] = $key;
                } elseif ($source === 'ipip_combo' && $key === 'EG') {
                    $keys['E'] = 'E';
                    $keys['G'] = 'G';
                }
            }
        }

        foreach ($config['knockouts'] as $knockout) {
            if (empty($knockout['enabled'])) {
                continue;
            }
            $metric = (string) ($knockout['metric'] ?? '');
            if (preg_match('/^[A-Z][0-9]?$/', $metric)) {
                $keys[$metric] = $metric;
            } elseif ($metric === 'CALM') {
                $keys['Q4'] = 'Q4';
            }
        }

        return array_values($keys);
    }

    private function requiresCag(array $config): bool
    {
        foreach ($config['dimensions'] as $dimension) {
            if (empty($dimension['enabled'])) {
                continue;
            }
            foreach ($dimension['components'] ?? [] as $component) {
                if (!empty($component['enabled']) && (string) ($component['source'] ?? '') === 'cag') {
                    return true;
                }
            }
        }

        foreach ($config['knockouts'] as $knockout) {
            if (!empty($knockout['enabled']) && in_array((string) ($knockout['metric'] ?? ''), ['CAG_STEN', 'CAG_RAW'], true)) {
                return true;
            }
        }

        return false;
    }

    private function dimensionFormulaText(array $dimension): string
    {
        $parts = [];
        foreach ($dimension['components'] as $component) {
            $parts[] = ((bool) ($component['enabled'] ?? false) ? '' : '[inactivo] ')
                . (string) ($component['label'] ?? $component['key'])
                . ' '
                . (string) ($component['transform'] ?? 'direct')
                . ' '
                . $this->round((float) ($component['weight'] ?? 0))
                . '%';
        }

        return implode('; ', $parts);
    }

    private function configFingerprint(array $config): string
    {
        return md5((string) json_encode($config, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    private function floatFromInput($value, float $default, float $min, float $max): float
    {
        if (is_string($value)) {
            $value = str_replace(',', '.', $value);
        }
        if (!is_numeric($value)) {
            return $default;
        }

        return min($max, max($min, (float) $value));
    }

    private function operatorFromInput($operator): string
    {
        $operator = trim((string) $operator);
        return in_array($operator, ['<', '<=', '>', '>='], true) ? $operator : '<';
    }

    private function isRankingInstrumentCode(string $code): bool
    {
        return in_array($code, ['ipip_16pf', 'cag_wonderlic', 'cag', 'ticl_barratt'], true);
    }

    private function compareMetric(float $metricValue, string $operator, float $threshold): bool
    {
        if ($operator === '<=') {
            return $metricValue <= $threshold;
        }
        if ($operator === '>') {
            return $metricValue > $threshold;
        }
        if ($operator === '>=') {
            return $metricValue >= $threshold;
        }

        return $metricValue < $threshold;
    }

    private function summaryByName(array $summary): array
    {
        $values = [];
        foreach ($summary as $row) {
            $name = (string) ($row['name'] ?? $row['scale'] ?? '');
            $code = $this->ipipCodeFromName($name);
            if ($code === null) {
                continue;
            }

            $value = $this->numericSummaryValue($row);
            if ($value !== null) {
                $values[$code] = $value;
            }
        }

        return $values;
    }

    private function ipipCodeFromName(string $name): ?string
    {
        if (preg_match('/^(AQ|IN|MI|EXT|ANS|DUR|IND|AUC|Q1|Q2|Q3|Q4|[A-Z])\\s*-/', trim($name), $matches)) {
            return $matches[1];
        }

        return null;
    }

    private function cagPercentile(array $summary): ?float
    {
        foreach ($summary as $row) {
            if (array_key_exists('percentile', $row) && is_numeric($row['percentile'])) {
                return (float) $row['percentile'];
            }
        }

        return null;
    }

    private function cagRawScore(array $summary): ?float
    {
        foreach ($summary as $row) {
            foreach (['raw_score', 'score'] as $field) {
                if (isset($row[$field]) && is_numeric($row[$field])) {
                    return (float) $row[$field];
                }
            }
        }

        return null;
    }

    private function summaryValueByNames(array $summary, array $names, ?array $fields = null): ?float
    {
        $normalizedNames = array_map([$this, 'normalize'], $names);
        foreach ($summary as $row) {
            $name = $this->normalize((string) ($row['name'] ?? $row['scale'] ?? ''));
            if (!in_array($name, $normalizedNames, true)) {
                continue;
            }

            return $this->numericSummaryValue($row, $fields);
        }

        return null;
    }

    private function numericSummaryValue(array $row, ?array $fields = null): ?float
    {
        foreach (($fields ?: ['transformed_score', 'adjusted_score', 'raw_score', 'score']) as $field) {
            if (isset($row[$field]) && is_numeric($row[$field])) {
                return (float) $row[$field];
            }
        }

        return null;
    }

    private function normalize(string $value): string
    {
        $value = iconv('UTF-8', 'ASCII//TRANSLIT', $value) ?: $value;
        return trim(strtolower(preg_replace('/\\s+/', ' ', $value) ?? $value));
    }

    private function round(float $value): float
    {
        return round($value, 2);
    }
}
