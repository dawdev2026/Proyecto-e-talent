<?php
declare(strict_types=1);

final class PostulantRankingReportPdfService
{
    private const DEFAULT_PRIMARY = [0, 40, 60];
    private const DEFAULT_ACCENT = [146, 190, 46];
    private const DEFAULT_TEXT = [46, 46, 46];
    private const DEFAULT_MUTED = [110, 110, 110];
    private const DEFAULT_LIGHT = [246, 248, 244];
    private const DEFAULT_BORDER = [217, 217, 217];

    private array $primary;
    private array $accent;
    private array $warning;
    private array $text;
    private array $muted;
    private array $light;
    private array $border;
    private string $brandName;
    private string $logoPath;

    public function __construct(?PlatformSettingsModel $settings = null)
    {
        $settingsModel = $settings ?: new PlatformSettingsModel();
        $design = $settingsModel->designSettings();
        $login = $settingsModel->loginSettings();

        $this->primary = $this->hexToRgb((string) ($design['app_primary_color'] ?? ''), self::DEFAULT_PRIMARY);
        $this->accent = $this->hexToRgb((string) ($design['button_background_color'] ?? ''), self::DEFAULT_ACCENT);
        $this->warning = $this->mix($this->accent, [255, 255, 255], 0.72);
        $this->text = $this->hexToRgb((string) ($design['portal_text_color'] ?? ''), self::DEFAULT_TEXT);
        $this->muted = $this->mix($this->text, [255, 255, 255], 0.58);
        $this->light = $this->hexToRgb((string) ($design['layout_background_color'] ?? ''), self::DEFAULT_LIGHT);
        $this->border = $this->hexToRgb((string) ($design['table_border_color'] ?? ''), self::DEFAULT_BORDER);
        $this->brandName = $this->brandName($design);
        $this->logoPath = $this->brandLogoPath($design, $login);
    }

    public function render(array $report): string
    {
        $pdf = new PostulantSimplePdf();
        $this->pageOne($pdf, $report);
        $this->pageTwo($pdf, $report);

        return $pdf->output();
    }

    public function filename(array $report): string
    {
        $rut = $this->slug((string) ($report['candidate']['rut'] ?? 'postulante'));
        $process = $this->slug((string) ($report['process']['code'] ?? $report['process']['name'] ?? 'proceso'));

        return sprintf('informe_postulante_%s_%s.pdf', $process, $rut);
    }

    private function pageOne(PostulantSimplePdf $pdf, array $report): void
    {
        $pdf->addPage();
        $candidate = $report['candidate'] ?? [];
        $process = $report['process'] ?? [];
        $row = $report['ranking_row'] ?? [];

        $this->header($pdf, 'EVALUACION PSICOMETRICA', $this->reportDate((string) ($report['generated_at'] ?? '')));
        $pdf->text(36, 118, 28, 'Informe del Postulante', $this->text, true);
        $this->candidateBox($pdf, $candidate, $process);

        $this->sectionTitle($pdf, 228, 'PERFIL DE PERSONALIDAD', 228);
        $this->infoBox($pdf, 36, 250, 523, 43, 'Este perfil muestra seis aspectos clave de tu personalidad. Las puntuaciones altas se presentan como fortalezas, mientras que las areas de desarrollo muestran oportunidades para seguir creciendo.');

        $cards = $this->personalityCards($report);
        $xPositions = [36, 218, 400];
        $yPositions = [305, 434];
        foreach ($cards as $index => $card) {
            $this->personalityCard($pdf, $xPositions[$index % 3], $yPositions[intdiv($index, 3)], $card);
        }

        $this->sectionTitle($pdf, 565, 'CAPACIDADES COGNITIVAS', 335);
        $this->infoBox($pdf, 36, 587, 523, 39, 'Esta seccion muestra tu capacidad para analizar, razonar y aprender. Conocer estas habilidades ayuda a identificar en que areas puedes destacar.');
        $cognitiveBars = $this->cognitiveBars($report);
        $this->cognitiveChart($pdf, 60, 646, $cognitiveBars);
        $this->infoBox($pdf, 36, 770, 523, 35, $this->cognitiveText($cognitiveBars));
        $this->footer($pdf, $this->brandName . ' | Informe de evaluaciones', '1');
    }

    private function pageTwo(PostulantSimplePdf $pdf, array $report): void
    {
        $pdf->addPage();
        $candidate = $report['candidate'] ?? [];
        $row = $report['ranking_row'] ?? [];

        $this->header($pdf, 'CONTINUACION', (string) ($candidate['name'] ?? ''), false);
        $this->sectionTitle($pdf, 96, 'CONTROL DE IMPULSOS Y AUTORREGULACION', 340);
        $this->infoBox($pdf, 36, 118, 523, 43, 'Esta seccion refleja tu capacidad para manejar tus emociones e impulsos. Un buen control en estas areas es clave para tomar decisiones acertadas bajo presion.');
        $radar = $this->impulseRadar($report);
        $pdf->radar(295, 276, 84, $radar, $this->primary, $this->mix($this->primary, [255, 255, 255], 0.18));
        $this->radarLabels($pdf, $radar);
        $this->infoBox($pdf, 36, 381, 523, 43, $this->impulseText($radar));

        $this->sectionTitle($pdf, 448, 'PERFIL VOCACIONAL Y MOTIVACION', 295);
        $this->infoBox($pdf, 36, 470, 523, 43, 'Tu perfil vocacional indica hacia donde se orienta tu motivacion natural. Conocerlo ayuda a entender en que tipo de entornos puedes desarrollarte mejor.');
        $vocational = $this->vocationalCards($report);
        $this->vocationalCard($pdf, 36, 526, $vocational[0]);
        $this->vocationalCard($pdf, 36, 588, $vocational[1]);
        if (!empty($vocational['_exploration'])) {
            $pdf->text(36, 649, 8.8, 'Perfil vocacional en exploracion', $this->muted, false);
        }

        $this->finalResult($pdf, 36, 652, $report);
        $this->footer($pdf, $this->brandName . ' | Instrumentos: Personalidad, Cognitivo, Impulsividad, Motivacion', '2');
    }

    private function header(PostulantSimplePdf $pdf, string $label, string $rightText, bool $evaluation = true): void
    {
        if ($this->logoPath !== '') {
            $pdf->image($this->logoPath, 36, 22, 42, 42);
        }
        $pdf->text(86, 50, 17, $this->brandName, $this->muted, true);
        $pdf->text(542, 34, 10, $label, $this->primary, true, 'regular', 'right');
        $pdf->text(542, 55, $this->headerRightTextSize($rightText), $rightText, $this->muted, false, 'regular', 'right');
        $pdf->line(34, 82, 560, 82, $this->primary, 2.2);
    }

    private function candidateBox(PostulantSimplePdf $pdf, array $candidate, array $process): void
    {
        $pdf->roundedBox(36, 145, 523, 72, [255, 255, 255], $this->primary, 2);
        $pdf->text(55, 178, 17, (string) ($candidate['name'] ?? 'Postulante'), [0, 0, 0], true);
        $pdf->text(55, 199, 11, 'RUT: ' . (string) ($candidate['rut'] ?? '-'), $this->muted);
        $pdf->text(531, 164, 10, 'PROCESO', $this->text, false, 'regular', 'right');
        $pdf->text(543, 186, 16, $this->processLabel($process), $this->accent, true, 'regular', 'right');
    }

    private function sectionTitle(PostulantSimplePdf $pdf, float $y, string $title, float $lineEnd): void
    {
        $pdf->diamond(39, $y - 1, 10, $this->primary);
        $pdf->text(49, $y + 4, 14, $title, $this->primary, true);
        $pdf->line(36, $y + 10, $lineEnd, $y + 10, $this->primary, 2);
    }

    private function infoBox(PostulantSimplePdf $pdf, float $x, float $y, float $w, float $h, string $text): void
    {
        $pdf->roundedBox($x, $y, $w, $h, $this->light, $this->light, 0.5);
        $pdf->roundedRect($x, $y + 5, 3, $h - 10, 1.5, $this->primary, $this->primary, 0);
        $fontSize = 9.1;
        $lineHeight = 11.4;
        $maxLines = max(1, (int) floor(($h - 10) / $lineHeight));
        $lines = $this->wrapTextLines($text, $w - 30, $fontSize, $maxLines);
        $textHeight = count($lines) * $lineHeight;
        $startY = $y + max(12.0, (($h - $textHeight) / 2.0) + 8.0);

        foreach ($lines as $index => $line) {
            $pdf->text($x + 15, $startY + ($index * $lineHeight), $fontSize, $line, $this->text);
        }
    }

    private function personalityCard(PostulantSimplePdf $pdf, float $x, float $y, array $card): void
    {
        $color = $card['score'] >= 7.0 ? $this->primary : $this->warning;
        $pdf->roundedBox($x, $y, 170, 118, [255, 255, 255], $this->border, 1);
        $pdf->roundedRect($x, $y, 6, 118, 3, $color, $color, 0);
        $pdf->text($x + 18, $y + 20, 11, (string) $card['label'], [0, 0, 0], true);
        $pdf->text($x + 18, $y + 46, 18, (string) round($card['score']) . '/10', $color, true);
        $pdf->bar($x + 18, $y + 56, 138, 7, $card['score'] * 10, $color, [226, 226, 226]);
        $pdf->wrappedText($x + 18, $y + 78, 8.7, (string) $card['text'], 135, 12, $this->text);
    }

    private function cognitiveChart(PostulantSimplePdf $pdf, float $x, float $y, array $bars): void
    {
        $chartHeight = 88;
        $chartRight = 540.0;
        $chartWidth = $chartRight - $x;
        $barWidth = 58.0;
        $count = max(1, count($bars));
        $slotWidth = $chartWidth / $count;

        $pdf->line($x, $y + $chartHeight, $chartRight, $y + $chartHeight, [20, 20, 20], 1);
        $pdf->line($x, $y, $x, $y + $chartHeight, [20, 20, 20], 1);
        foreach ([0, 25, 50, 75, 100] as $tick) {
            $ty = $y + $chartHeight - ($chartHeight * $tick / 100);
            $pdf->line($x - 4, $ty, $x, $ty, [20, 20, 20], 1);
            $pdf->text($x - 8, $ty + 3, 8, $tick . '%', $this->muted, false, 'regular', 'right');
        }

        foreach ($bars as $index => $bar) {
            $centerX = $x + ($slotWidth * $index) + ($slotWidth / 2);
            $bx = $centerX - ($barWidth / 2);
            $value = $this->percentValue($bar['value'] ?? null);
            $height = $chartHeight * ($value / 100);
            $color = $value >= 70 ? $this->primary : $this->accent;
            $pdf->fillRect($bx, $y + $chartHeight - $height, $barWidth, $height, $color);
            $display = (string) ($bar['display'] ?? (round($value) . '%'));
            $pdf->text($centerX, $y + $chartHeight - $height - 8, 11, $display, [0, 0, 0], true, 'regular', 'center');
            $pdf->wrappedText($centerX, $y + $chartHeight + 16, 8.5, (string) $bar['label'], 92, 11, [0, 0, 0], false, 'regular', 'center');
        }
    }

    private function radarLabels(PostulantSimplePdf $pdf, array $radar): void
    {
        $pdf->text(295, 189, 9, (string) ($radar[0]['label'] ?? 'Control de Impulsos'), [0, 0, 0], true, 'regular', 'center');
        $pdf->text(295, 207, 14, $this->radarDisplay($radar[0] ?? []), $this->accent, true, 'regular', 'center');
        $pdf->wrappedText(390, 268, 9, (string) ($radar[1]['label'] ?? 'Reflexividad'), 78, 12, [0, 0, 0], true);
        $pdf->text(426, 300, 14, $this->radarDisplay($radar[1] ?? []), $this->accent, true, 'regular', 'center');
        $pdf->text(295, 359, 9, (string) ($radar[2]['label'] ?? 'Autorregulacion'), [0, 0, 0], false, 'regular', 'center');
        $pdf->text(295, 377, 14, $this->radarDisplay($radar[2] ?? []), $this->accent, true, 'regular', 'center');
        $pdf->wrappedText(152, 268, 9, (string) ($radar[3]['label'] ?? 'Tolerancia a Frustracion'), 95, 12, [0, 0, 0], true);
        $pdf->text(191, 302, 14, $this->radarDisplay($radar[3] ?? []), $this->accent, true, 'regular', 'center');
    }

    private function vocationalCard(PostulantSimplePdf $pdf, float $x, float $y, array $card): void
    {
        $pdf->roundedBox($x, $y, 523, 55, [255, 255, 255], $this->border, 1);
        $pdf->roundedRect($x, $y, 6, 55, 3, $this->primary, $this->primary, 0);
        $pdf->text($x + 84, $y + 36, 25, round((float) $card['percentage']) . '%', $this->accent, true, 'regular', 'center');
        $pdf->line($x + 126, $y + 12, $x + 126, $y + 43, $this->border, 1);
        $pdf->text($x + 146, $y + 21, 11, (string) $card['title'], $this->primary, true);
        $pdf->wrappedText($x + 146, $y + 39, 9.2, (string) $card['text'], 340, 12, $this->text);
    }

    private function finalResult(PostulantSimplePdf $pdf, float $x, float $y, array $report): void
    {
        $row = $report['ranking_row'] ?? [];
        $candidate = $report['candidate'] ?? [];
        $classification = (string) ($row['Clasificacion'] ?? '');
        $pdf->roundedBox($x, $y, 523, 145, [255, 255, 255], $this->primary, 2);
        $badge = $this->classificationBadge($classification);
        $pdf->tag($x + 18, $y + 11, $this->classificationBadgeWidth($badge), 16, $badge, $this->classificationBadgeColor($classification));
        $pdf->text($x + 18, $y + 55, 25, 'Resultado Final del Postulante', $this->primary, true);
        $text = $this->finalText((string) ($candidate['name'] ?? 'El postulante'), $report);
        $this->finalParagraph($pdf, $x + 18, $y + 82, $text[0], 490, 4);
        $this->finalParagraph($pdf, $x + 18, $y + 122, $text[1], 490, 3);
    }

    private function footer(PostulantSimplePdf $pdf, string $left, string $page): void
    {
        $pdf->text(36, 822, 8, $left, [150, 150, 154]);
        $pdf->circle(546, 811, 11, $this->primary);
        $pdf->text(546, 816, 10, $page, $this->primary, false, 'regular', 'center');
    }

    private function personalityCards(array $report): array
    {
        $factors = $this->factorScores($report['ipip_factors'] ?? []);
        $cards = [
            ['key' => 'responsabilidad', 'label' => 'RESPONSABILIDAD', 'score' => $this->avg($factors, ['G', 'Q3'])],
            ['key' => 'estabilidad', 'label' => 'ESTABILIDAD EMOC.', 'score' => $this->avgDirect([$factors['C'] ?? null, $this->inverse($factors['Q4'] ?? null), $this->inverse($factors['O'] ?? null)])],
            ['key' => 'amabilidad', 'label' => 'AMABILIDAD', 'score' => $this->avgDirect([$factors['A'] ?? null, $this->inverse($factors['L'] ?? null)])],
            ['key' => 'apertura', 'label' => 'APERTURA MENTAL', 'score' => $this->avg($factors, ['Q1', 'M', 'I'])],
            ['key' => 'sociabilidad', 'label' => 'SOCIABILIDAD', 'score' => $this->avg($factors, ['A', 'F', 'H'])],
            ['key' => 'dinamismo', 'label' => 'DINAMISMO', 'score' => $this->avg($factors, ['E', 'H', 'Q1'])],
        ];

        return array_map(function (array $card): array {
            $score = max(1.0, min(10.0, (float) $card['score']));
            return $card + ['score' => $score, 'text' => $this->personalityText((string) $card['key'], $score)];
        }, $cards);
    }

    private function cognitiveBars(array $report): array
    {
        $row = $report['ranking_row'] ?? [];
        $factors = $this->factorScores($report['ipip_factors'] ?? []);
        $sten = $this->validSten($row['Sten Wonderlic'] ?? null);
        $aptitude = $sten !== null ? $this->stenPercent($sten) : null;
        $reasoning = isset($factors['B']) ? $this->stenPercent((float) $factors['B']) : null;
        $performance = is_numeric($row['D3 Rendimiento'] ?? null) ? (float) $row['D3 Rendimiento'] : null;

        return [
            ['label' => 'Aptitud Cognitiva', 'value' => $aptitude, 'display' => $aptitude === null ? 'Sin dato' : null],
            ['label' => 'Razonamiento', 'value' => $reasoning, 'display' => $reasoning === null ? 'Sin dato' : null],
            ['label' => 'Rendimiento y Adaptacion', 'value' => $performance, 'display' => $performance === null ? 'Sin dato' : null],
        ];
    }

    private function impulseRadar(array $report): array
    {
        $row = $report['ranking_row'] ?? [];
        $factors = $this->factorScores($report['ipip_factors'] ?? []);
        $ticl = is_numeric($row['Impulsividad Total TICL'] ?? null) ? (float) $row['Impulsividad Total TICL'] : null;
        $controlBase = $ticl !== null ? $this->clampPercent(((120.0 - $ticl) / 90.0) * 100.0) : null;
        $fInverse = $this->inverse($factors['F'] ?? null);
        $reflexivity = ($controlBase !== null && $fInverse !== null) ? $this->clampPercent((($controlBase / 10.0) + $fInverse) / 2.0 * 10.0) : null;
        $calm = $this->validSten($row['Sten Calma'] ?? null);
        if ($calm === null && isset($factors['Q4'])) {
            $calm = $this->inverse($factors['Q4']);
        }
        $selfRegulation = $calm !== null ? $this->stenPercent($calm) : null;
        $frustration = (isset($factors['C'], $factors['Q4']) && is_numeric($factors['C']) && is_numeric($factors['Q4']))
            ? $this->clampPercent((((float) $factors['C'] + (11.0 - (float) $factors['Q4'])) / 2.0) * 10.0)
            : null;

        return [
            ['label' => 'Control de Impulsos', 'value' => $controlBase, 'display' => $controlBase === null ? 'Sin dato' : null],
            ['label' => 'Reflexividad', 'value' => $reflexivity, 'display' => $reflexivity === null ? 'Sin dato' : null],
            ['label' => 'Autorregulacion', 'value' => $selfRegulation, 'display' => $selfRegulation === null ? 'Sin dato' : null],
            ['label' => 'Tolerancia a Frustracion', 'value' => $frustration, 'display' => $frustration === null ? 'Sin dato' : null],
        ];
    }

    private function vocationalCards(array $report): array
    {
        $riasec = is_array($report['riasec_result'] ?? null) ? $report['riasec_result'] : null;
        $scales = is_array($riasec['scales'] ?? null) ? array_slice($riasec['scales'], 0, 2) : [];
        if (!$scales) {
            return [
                ['percentage' => 0, 'title' => 'PERFIL VOCACIONAL', 'text' => 'Sin datos vocacionales disponibles para este postulante.'],
                ['percentage' => 0, 'title' => 'MOTIVACION', 'text' => 'Aplicar o revisar RIASEC para complementar esta seccion.'],
            ];
        }

        $cards = [];
        foreach ($scales as $scale) {
            $name = strtoupper((string) ($scale['name'] ?? 'Perfil'));
            $cards[] = [
                'percentage' => (float) ($scale['percentage'] ?? 0),
                'title' => 'PERFIL ' . $name,
                'text' => $this->riasecText((string) ($scale['code'] ?? ''), (string) ($scale['name'] ?? '')),
            ];
        }
        while (count($cards) < 2) {
            $cards[] = ['percentage' => 0, 'title' => 'PERFIL COMPLEMENTARIO', 'text' => 'No hay una segunda area vocacional destacada disponible.'];
        }
        if (($cards[0]['percentage'] ?? 0) < 40) {
            $cards['_exploration'] = true;
        }

        return $cards;
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

    private function avg(array $scores, array $keys): float
    {
        $values = [];
        foreach ($keys as $key) {
            if (isset($scores[$key]) && is_numeric($scores[$key])) {
                $values[] = (float) $scores[$key];
            }
        }

        return $this->avgDirect($values);
    }

    private function avgDirect(array $values): float
    {
        $values = array_values(array_filter($values, static fn($value): bool => is_numeric($value)));
        if (!$values) {
            return 5.0;
        }

        return array_sum($values) / count($values);
    }

    private function wrapTextLines(string $text, float $width, float $size, int $maxLines): array
    {
        $line = '';
        $lines = [];
        $maxChars = max(8, (int) floor($width / max(1.0, $size * 0.52)));
        foreach (preg_split('/\\s+/', trim($text)) ?: [] as $word) {
            $candidate = trim($line . ' ' . $word);
            if (strlen($candidate) > $maxChars && $line !== '') {
                $lines[] = $line;
                if (count($lines) >= $maxLines) {
                    return $this->truncateLastLine($lines, $maxChars);
                }
                $line = $word;
                continue;
            }
            $line = $candidate;
        }

        if ($line !== '' && count($lines) < $maxLines) {
            $lines[] = $line;
        }

        return $lines ?: [''];
    }

    private function finalParagraph(PostulantSimplePdf $pdf, float $x, float $y, string $text, float $width, int $maxLines): void
    {
        $fontSize = 8.3;
        $lineHeight = 10.2;
        foreach ($this->wrapTextLines($text, $width, $fontSize, $maxLines) as $index => $line) {
            $pdf->text($x, $y + ($index * $lineHeight), $fontSize, $line, $this->text);
        }
    }

    private function truncateLastLine(array $lines, int $maxChars): array
    {
        $last = count($lines) - 1;
        if ($last < 0) {
            return $lines;
        }

        $lines[$last] = rtrim(substr($lines[$last], 0, max(0, $maxChars - 3))) . '...';
        return $lines;
    }

    private function inverse($score): ?float
    {
        return is_numeric($score) ? 11.0 - (float) $score : null;
    }

    private function stenPercent(float $sten): float
    {
        $sten = min(10.0, max(1.0, $sten));
        return $sten * 10.0;
    }

    private function validSten($value): ?float
    {
        if (!is_numeric($value)) {
            return null;
        }

        $sten = (float) $value;
        if ($sten <= 0.0) {
            return null;
        }

        return min(10.0, max(1.0, $sten));
    }

    private function percentValue($value): float
    {
        return is_numeric($value) ? $this->clampPercent((float) $value) : 0.0;
    }

    private function clampPercent(float $value): float
    {
        return max(0.0, min(100.0, $value));
    }

    private function score($value, float $default): float
    {
        return is_numeric($value) ? (float) $value : $default;
    }

    private function cognitiveText(array $bars): string
    {
        $available = array_values(array_filter($bars, static fn(array $bar): bool => is_numeric($bar['value'] ?? null)));
        if (!$available) {
            return 'No hay datos cognitivos suficientes para interpretar esta seccion. Esta informacion debe revisarse junto a los antecedentes del proceso.';
        }

        usort($available, static fn(array $a, array $b): int => ((float) $b['value']) <=> ((float) $a['value']));
        $high = $available[0];
        $low = $available[count($available) - 1];

        return ucfirst(strtolower((string) $high['label'])) . ': nivel ' . $this->performanceBand((float) $high['value']) . '. ' . ucfirst(strtolower((string) $low['label'])) . ': nivel ' . $this->performanceBand((float) $low['value']) . '. Esta lectura orienta apoyos y exigencias del proceso.';
    }

    private function impulseText(array $radar): string
    {
        $values = array_values(array_map(static fn(array $item): float => (float) $item['value'], array_filter($radar, static fn(array $item): bool => is_numeric($item['value'] ?? null))));
        if (!$values) {
            return 'No hay datos suficientes para interpretar esta seccion (evaluacion de impulsividad no disponible).';
        }

        $avg = array_sum($values) / count($values);
        $range = count($values) > 1 ? ' (' . round(min($values)) . '-' . round(max($values)) . '%)' : ' (' . round($avg) . '%)';
        $missingNote = count($values) < count($radar) ? ' Evaluacion de impulsividad no disponible.' : '';
        if ($avg >= 70) {
            return 'Tu nivel de control emocional y autorregulacion es excelente' . $range . '. Esto favorece una respuesta estable ante situaciones de presion.' . $missingNote;
        }
        if ($avg >= 55) {
            return 'Tu control emocional se observa en un nivel adecuado' . $range . '. Puede responder bien con estructura clara y entrenamiento en situaciones de presion.' . $missingNote;
        }

        return 'Se observan oportunidades de fortalecimiento en autorregulacion e impulsos' . $range . '. Esta informacion debe contrastarse con entrevista y conducta observable.' . $missingNote;
    }

    private function riasecText(string $code, string $name): string
    {
        return [
            'R' => 'Preferencia por tareas practicas, operativas y orientadas a la accion concreta.',
            'I' => 'Interes por analizar informacion, resolver problemas y comprender causas.',
            'A' => 'Orientacion a la creatividad, expresion y busqueda de enfoques flexibles.',
            'S' => 'Orientacion natural hacia ayudar a otros, trabajo en equipo y colaboracion.',
            'E' => 'Interes por liderar, persuadir, organizar personas o impulsar objetivos.',
            'C' => 'Preferencia por ambientes con estructura clara, normas definidas y procedimientos.',
        ][$code] ?? ('Area vocacional destacada: ' . $name . '.');
    }

    private function finalText(string $name, array $report): array
    {
        $row = $report['ranking_row'] ?? [];
        $firstName = trim(explode(' ', $name)[0] ?? 'El postulante') ?: 'El postulante';
        $classification = (string) ($row['Clasificacion'] ?? '');
        $personality = $this->personalityCards($report);
        $cognitive = $this->cognitiveBars($report);
        $topPersonality = $this->topLabels($personality, 2);
        $lowPersonality = $this->lowLabels($personality);
        $topCognitive = $this->topCognitiveLabel($cognitive);
        $development = $lowPersonality ? implode(', ', $lowPersonality) : 'las areas de desarrollo identificadas';

        if (stripos($classification, 'No Recomendado') !== false) {
            return [
                $firstName . ', agradecemos tu participacion en este proceso. De acuerdo con los criterios definidos para esta etapa, tus resultados no alcanzan el nivel requerido para continuar en esta oportunidad; esto no constituye un juicio sobre ti como persona ni impide futuras postulaciones.',
                'Como areas de desarrollo, puedes seguir fortaleciendo ' . $development . '. Este resultado corresponde solo al presente proceso de seleccion y puede ser una referencia para tu crecimiento personal.',
            ];
        }
        if (stripos($classification, 'Observacion') !== false) {
            return [
                $firstName . ', tu perfil cumple el estandar del proceso y muestra fortalezas reales en ' . implode(' y ', $topPersonality ?: ['dimensiones personales relevantes']) . ($topCognitive !== '' ? ', junto con ' . strtolower($topCognitive) . '.' : '.'),
                'Conviene observar y seguir fortaleciendo ' . $development . '. Esta nueva etapa permitira complementar la evaluacion con entrevista y antecedentes del proceso.',
            ];
        }

        return [
            $firstName . ', tu evaluacion refleja un perfil solido, con fortalezas destacadas en ' . implode(' y ', $topPersonality ?: ['dimensiones personales relevantes']) . ($topCognitive !== '' ? ', ademas de ' . strtolower($topCognitive) . '.' : '.'),
            'Puedes seguir desarrollando ' . $development . ' como parte de tu crecimiento. Estos resultados te posicionan favorablemente dentro del proceso de seleccion ' . $this->processContext($report) . '.',
        ];
    }

    private function classificationBadge(string $classification): string
    {
        if (stripos($classification, 'Observacion') !== false) {
            return 'Recomendado con Observacion';
        }
        if (stripos($classification, 'No Recomendado') !== false) {
            return 'No Recomendado';
        }

        return 'Recomendado';
    }

    private function classificationBadgeColor(string $classification): array
    {
        if (stripos($classification, 'No Recomendado') !== false) {
            return [108, 122, 137];
        }

        return $this->accent;
    }

    private function classificationBadgeWidth(string $badge): float
    {
        return max(78.0, min(165.0, strlen($badge) * 4.8 + 18.0));
    }

    private function brandName(array $design): string
    {
        $name = trim((string) ($design['topbar_name'] ?? ''));
        if ($name === '') {
            $name = trim((string) ($design['html_title'] ?? ''));
        }

        return $name !== '' ? $name : 'e-talent';
    }

    private function brandLogoPath(array $design, array $login): string
    {
        $candidates = [
            (string) ($design['topbar_icon_path'] ?? ''),
            (string) ($design['html_favicon_path'] ?? ''),
            (string) ($login['login_logo_path'] ?? ''),
        ];

        foreach ($candidates as $path) {
            $resolved = $this->publicPath($path);
            if ($resolved !== '' && is_file($resolved)) {
                return $resolved;
            }
        }

        return '';
    }

    private function publicPath(string $path): string
    {
        $path = trim($path);
        if ($path === '') {
            return '';
        }

        if (substr($path, 0, 1) === '/') {
            return $path;
        }

        $base = defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__, 3);
        $relative = ltrim($path, '/');
        if (substr($relative, 0, 7) === 'public/') {
            return $base . '/' . $relative;
        }

        return $base . '/public/' . $relative;
    }

    private function processLabel(array $process): string
    {
        $name = trim((string) ($process['name'] ?? ''));
        if ($name !== '') {
            return mb_substr($name, 0, 34);
        }

        $code = trim((string) ($process['code'] ?? ''));
        return $code !== '' ? mb_substr($code, 0, 34) : 'Proceso de evaluacion';
    }

    private function processContext(array $report): string
    {
        $process = is_array($report['process'] ?? null) ? $report['process'] : [];
        $label = $this->processLabel($process);

        return $label !== '' ? $label : 'correspondiente';
    }

    private function hexToRgb(string $hex, array $fallback): array
    {
        $hex = trim($hex);
        if (!preg_match('/^#([0-9a-fA-F]{6})$/', $hex, $matches)) {
            return $fallback;
        }

        return [
            hexdec(substr($matches[1], 0, 2)),
            hexdec(substr($matches[1], 2, 2)),
            hexdec(substr($matches[1], 4, 2)),
        ];
    }

    private function mix(array $color, array $with, float $weight): array
    {
        $weight = max(0.0, min(1.0, $weight));
        return [
            (int) round(($color[0] * $weight) + ($with[0] * (1 - $weight))),
            (int) round(($color[1] * $weight) + ($with[1] * (1 - $weight))),
            (int) round(($color[2] * $weight) + ($with[2] * (1 - $weight))),
        ];
    }

    private function personalityText(string $key, float $score): string
    {
        $band = $score >= 8.0 ? 'high' : ($score >= 6.0 ? 'mid' : 'low');
        $texts = [
            'responsabilidad' => [
                'high' => 'Eres muy comprometido y cumples con tus responsabilidades de manera consistente.',
                'mid' => 'Muestras responsabilidad adecuada y puedes fortalecer la constancia cotidiana.',
                'low' => 'Puedes fortalecer la constancia, el orden y el seguimiento de procedimientos.',
            ],
            'estabilidad' => [
                'high' => 'Manejas bien el estres y mantienes la calma en situaciones dificiles.',
                'mid' => 'Cuentas con recursos para regularte y puedes reforzarlos ante presion.',
                'low' => 'Hay espacio para fortalecer regulacion emocional y manejo de presion.',
            ],
            'amabilidad' => [
                'high' => 'Tienes una actitud empatica y colaborativa que facilita el trabajo en equipo.',
                'mid' => 'Muestras disposicion al trato cordial y puedes ampliar la colaboracion cotidiana.',
                'low' => 'Puedes desarrollar mayor cercania, empatia aplicada y colaboracion cotidiana.',
            ],
            'apertura' => [
                'high' => 'Estas abierto a aprender cosas nuevas y adaptarte a los cambios.',
                'mid' => 'Tienes apertura funcional y puedes explorar nuevas formas de resolver problemas.',
                'low' => 'Puedes ampliar tu flexibilidad ante cambios y nuevas formas de trabajo.',
            ],
            'sociabilidad' => [
                'high' => 'Te relacionas con seguridad y participas activamente con otras personas.',
                'mid' => 'Puedes fortalecer tus habilidades para conectar mas facilmente con otros.',
                'low' => 'Puedes fortalecer tus habilidades para conectar mas facilmente con otros.',
            ],
            'dinamismo' => [
                'high' => 'Muestras energia, iniciativa y disposicion para actuar con rapidez.',
                'mid' => 'Presentas iniciativa adecuada y puedes potenciar tu proactividad diaria.',
                'low' => 'Hay espacio para desarrollar mayor iniciativa y proactividad en tu dia a dia.',
            ],
        ];

        return $texts[$key][$band] ?? 'Tu evaluacion en esta dimension forma parte del perfil general.';
    }

    private function performanceBand(float $value): string
    {
        if ($value >= 75.0) {
            return 'superior';
        }
        if ($value >= 55.0) {
            return 'bueno';
        }

        return 'en desarrollo';
    }

    private function radarDisplay(array $item): string
    {
        if (($item['display'] ?? '') !== '') {
            return (string) $item['display'];
        }

        return round($this->percentValue($item['value'] ?? null)) . '%';
    }

    private function topLabels(array $cards, int $limit): array
    {
        usort($cards, static fn(array $a, array $b): int => ((float) $b['score']) <=> ((float) $a['score']));
        return array_slice(array_map(static fn(array $card): string => strtolower((string) $card['label']), $cards), 0, $limit);
    }

    private function lowLabels(array $cards): array
    {
        $low = array_values(array_filter($cards, static fn(array $card): bool => (float) ($card['score'] ?? 0) <= 6.0));
        usort($low, static fn(array $a, array $b): int => ((float) $a['score']) <=> ((float) $b['score']));
        return array_slice(array_map(static fn(array $card): string => strtolower((string) $card['label']), $low), 0, 2);
    }

    private function topCognitiveLabel(array $bars): string
    {
        $available = array_values(array_filter($bars, static fn(array $bar): bool => is_numeric($bar['value'] ?? null)));
        if (!$available) {
            return '';
        }

        usort($available, static fn(array $a, array $b): int => ((float) $b['value']) <=> ((float) $a['value']));
        return (string) $available[0]['label'];
    }

    private function headerRightTextSize(string $text): float
    {
        $length = strlen($text);
        if ($length > 42) {
            return 7.2;
        }

        if ($length > 34) {
            return 8.0;
        }

        if ($length > 26) {
            return 8.8;
        }

        return 10.0;
    }

    private function reportDate(string $generatedAt): string
    {
        return $generatedAt !== '' ? substr($generatedAt, 0, 10) : date('d-m-Y');
    }

    private function slug(string $value): string
    {
        $value = strtolower(trim($value));
        $value = preg_replace('/[^a-z0-9]+/i', '_', $value) ?? 'reporte';
        return trim($value, '_') ?: 'reporte';
    }
}

final class PostulantSimplePdf
{
    private const HEIGHT = 841.89;

    /** @var string[] */
    private array $pages = [];
    private string $current = '';
    /** @var array<string, array{name:string,width:int,height:int,data:string}> */
    private array $images = [];

    public function addPage(): void
    {
        if ($this->current !== '') {
            $this->pages[] = $this->current;
        }
        $this->current = '';
    }

    public function output(): string
    {
        if ($this->current !== '') {
            $this->pages[] = $this->current;
            $this->current = '';
        }

        $objects = [];
        $fontId = $this->addObject($objects, '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>');
        $fontBoldId = $this->addObject($objects, '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>');
        $imageObjectIds = [];
        foreach ($this->images as $path => $image) {
            $imageObjectIds[$image['name']] = $this->addObject($objects, $this->imageObject($image));
        }
        $xObjectResources = '';
        if ($imageObjectIds) {
            $pairs = [];
            foreach ($imageObjectIds as $name => $objectId) {
                $pairs[] = '/' . $name . ' ' . $objectId . ' 0 R';
            }
            $xObjectResources = ' /XObject << ' . implode(' ', $pairs) . ' >>';
        }
        $pageIds = [];

        foreach ($this->pages as $page) {
            $contentId = $this->addObject($objects, $this->stream($page));
            $pageIds[] = $this->addObject($objects, '<< /Type /Page /Parent {PAGES} 0 R /MediaBox [0 0 595.28 841.89] /Resources << /Font << /F1 ' . $fontId . ' 0 R /F2 ' . $fontBoldId . ' 0 R >>' . $xObjectResources . ' >> /Contents ' . $contentId . ' 0 R >>');
        }

        $pagesId = count($objects) + 1;
        foreach ($pageIds as $pageId) {
            $objects[$pageId] = str_replace('{PAGES}', (string) $pagesId, $objects[$pageId]);
        }
        $this->addObject($objects, '<< /Type /Pages /Kids [' . implode(' ', array_map(static fn(int $id): string => $id . ' 0 R', $pageIds)) . '] /Count ' . count($pageIds) . ' >>');
        $catalogId = $this->addObject($objects, '<< /Type /Catalog /Pages ' . $pagesId . ' 0 R >>');

        return $this->build($objects, $catalogId);
    }

    public function text(float $x, float $y, float $size, string $text, array $color = [0, 0, 0], bool $bold = false, string $font = 'regular', string $align = 'left'): void
    {
        $fontName = ($bold || $font === 'serif') ? 'F2' : 'F1';
        $text = $this->escape($text);
        $width = $this->textWidth($this->unescapeForWidth($text), $size);
        if ($align === 'center') {
            $x -= $width / 2;
        } elseif ($align === 'right') {
            $x -= $width;
        }
        $this->current .= sprintf(
            "BT /%s %.2F Tf %.3F %.3F %.3F rg %.2F %.2F Td (%s) Tj ET\n",
            $fontName,
            $size,
            $color[0] / 255,
            $color[1] / 255,
            $color[2] / 255,
            $x,
            self::HEIGHT - $y,
            $text
        );
    }

    public function wrappedText(float $x, float $y, float $size, string $text, float $width, float $lineHeight, array $color = [0, 0, 0], bool $bold = false, string $font = 'regular', string $align = 'left'): void
    {
        $line = '';
        $maxChars = max(8, (int) floor($width / max(1.0, $size * 0.52)));
        foreach (preg_split('/\\s+/', trim($text)) ?: [] as $word) {
            $candidate = trim($line . ' ' . $word);
            if (strlen($candidate) > $maxChars && $line !== '') {
                $this->text($x, $y, $size, $line, $color, $bold, $font, $align);
                $y += $lineHeight;
                $line = $word;
                continue;
            }
            $line = $candidate;
        }
        if ($line !== '') {
            $this->text($x, $y, $size, $line, $color, $bold, $font, $align);
        }
    }

    public function roundedBox(float $x, float $y, float $w, float $h, array $fill, array $stroke, float $lineWidth = 1): void
    {
        $this->roundedRect($x, $y, $w, $h, min(8.0, $h / 3), $fill, $stroke, $lineWidth);
    }

    public function image(string $path, float $x, float $y, float $w, float $h): void
    {
        if (!is_file($path)) {
            return;
        }

        $key = realpath($path) ?: $path;
        if (!isset($this->images[$key])) {
            $image = $this->readPngRgb($path);
            if ($image === null) {
                return;
            }
            $image['name'] = 'Im' . (count($this->images) + 1);
            $this->images[$key] = $image;
        }

        $name = $this->images[$key]['name'];
        $this->current .= sprintf(
            "q %.2F 0 0 %.2F %.2F %.2F cm /%s Do Q\n",
            $w,
            $h,
            $x,
            self::HEIGHT - $y - $h,
            $name
        );
    }

    public function fillRect(float $x, float $y, float $w, float $h, array $color): void
    {
        $this->current .= sprintf("%.3F %.3F %.3F rg %.2F %.2F %.2F %.2F re f\n", $color[0] / 255, $color[1] / 255, $color[2] / 255, $x, self::HEIGHT - $y - $h, $w, $h);
    }

    public function rect(float $x, float $y, float $w, float $h, array $color, float $lineWidth = 1): void
    {
        $this->current .= sprintf("%.2F w %.3F %.3F %.3F RG %.2F %.2F %.2F %.2F re S\n", $lineWidth, $color[0] / 255, $color[1] / 255, $color[2] / 255, $x, self::HEIGHT - $y - $h, $w, $h);
    }

    public function roundedRect(float $x, float $y, float $w, float $h, float $r, array $fill, array $stroke, float $lineWidth = 1): void
    {
        $r = max(0.0, min($r, $w / 2, $h / 2));
        $k = 0.5522847498;
        $left = $x;
        $right = $x + $w;
        $top = self::HEIGHT - $y;
        $bottom = self::HEIGHT - $y - $h;
        $cmd = sprintf(
            "%.2F w %.3F %.3F %.3F rg %.3F %.3F %.3F RG " .
            "%.2F %.2F m %.2F %.2F l " .
            "%.2F %.2F %.2F %.2F %.2F %.2F c " .
            "%.2F %.2F l " .
            "%.2F %.2F %.2F %.2F %.2F %.2F c " .
            "%.2F %.2F l " .
            "%.2F %.2F %.2F %.2F %.2F %.2F c " .
            "%.2F %.2F l " .
            "%.2F %.2F %.2F %.2F %.2F %.2F c h B\n",
            $lineWidth,
            $fill[0] / 255,
            $fill[1] / 255,
            $fill[2] / 255,
            $stroke[0] / 255,
            $stroke[1] / 255,
            $stroke[2] / 255,
            $left + $r,
            $top,
            $right - $r,
            $top,
            $right - $r + ($r * $k),
            $top,
            $right,
            $top - $r + ($r * $k),
            $right,
            $top - $r,
            $right,
            $bottom + $r,
            $right,
            $bottom + $r - ($r * $k),
            $right - $r + ($r * $k),
            $bottom,
            $right - $r,
            $bottom,
            $left + $r,
            $bottom,
            $left + $r - ($r * $k),
            $bottom,
            $left,
            $bottom + $r - ($r * $k),
            $left,
            $bottom + $r,
            $left,
            $top - $r,
            $left,
            $top - $r + ($r * $k),
            $left + $r - ($r * $k),
            $top,
            $left + $r,
            $top
        );
        $this->current .= $cmd;
    }

    public function line(float $x1, float $y1, float $x2, float $y2, array $color, float $lineWidth = 1): void
    {
        $this->current .= sprintf("%.2F w %.3F %.3F %.3F RG %.2F %.2F m %.2F %.2F l S\n", $lineWidth, $color[0] / 255, $color[1] / 255, $color[2] / 255, $x1, self::HEIGHT - $y1, $x2, self::HEIGHT - $y2);
    }

    public function circle(float $x, float $y, float $r, array $stroke): void
    {
        $c = 0.5522847498 * $r;
        $cy = self::HEIGHT - $y;
        $this->current .= sprintf("%.3F %.3F %.3F RG %.2F %.2F m %.2F %.2F %.2F %.2F %.2F %.2F c %.2F %.2F %.2F %.2F %.2F %.2F c %.2F %.2F %.2F %.2F %.2F %.2F c %.2F %.2F %.2F %.2F %.2F %.2F c S\n",
            $stroke[0] / 255, $stroke[1] / 255, $stroke[2] / 255,
            $x + $r, $cy,
            $x + $r, $cy + $c, $x + $c, $cy + $r, $x, $cy + $r,
            $x - $c, $cy + $r, $x - $r, $cy + $c, $x - $r, $cy,
            $x - $r, $cy - $c, $x - $c, $cy - $r, $x, $cy - $r,
            $x + $c, $cy - $r, $x + $r, $cy - $c, $x + $r, $cy
        );
    }

    public function diamond(float $x, float $y, float $size, array $color): void
    {
        $r = $size / 2;
        $this->polygon([[$x, $y - $r], [$x + $r, $y], [$x, $y + $r], [$x - $r, $y]], $color, true);
    }

    public function bar(float $x, float $y, float $w, float $h, float $value, array $color, array $bg): void
    {
        $value = max(0.0, min(100.0, $value));
        $this->fillRect($x, $y, $w, $h, $bg);
        $this->fillRect($x, $y, $w * ($value / 100.0), $h, $color);
    }

    public function tag(float $x, float $y, float $w, float $h, string $label, array $color): void
    {
        $this->fillRect($x, $y, $w, $h, $color);
        $this->text($x + ($w / 2), $y + 11, 8.2, $label, [255, 255, 255], true, 'regular', 'center');
    }

    public function radar(float $cx, float $cy, float $r, array $items, array $stroke, array $fill): void
    {
        for ($i = 1; $i <= 4; $i++) {
            $this->polygon($this->radarPoints($cx, $cy, $r * $i / 4, [100, 100, 100, 100]), [205, 211, 219], false);
        }
        $this->line($cx, $cy - $r, $cx, $cy + $r, [205, 211, 219], 0.6);
        $this->line($cx - $r, $cy, $cx + $r, $cy, [205, 211, 219], 0.6);
        $values = array_map(static fn(array $item): float => (float) ($item['value'] ?? 0), $items);
        $points = $this->radarPoints($cx, $cy, $r, $values);
        $this->polygon($points, $fill, true, 0.45);
        $this->polygon($points, $stroke, false, 1.5);
        foreach ($points as $point) {
            $this->fillCircle($point[0], $point[1], 3, $stroke);
        }
    }

    private function fillCircle(float $x, float $y, float $r, array $color): void
    {
        $this->fillRect($x - $r, $y - $r, $r * 2, $r * 2, $color);
    }

    private function radarPoints(float $cx, float $cy, float $r, array $values): array
    {
        return [
            [$cx, $cy - ($r * (($values[0] ?? 0) / 100))],
            [$cx + ($r * (($values[1] ?? 0) / 100)), $cy],
            [$cx, $cy + ($r * (($values[2] ?? 0) / 100))],
            [$cx - ($r * (($values[3] ?? 0) / 100)), $cy],
        ];
    }

    private function polygon(array $points, array $color, bool $fill, float $alpha = 1.0): void
    {
        if (!$points) {
            return;
        }
        $cmd = sprintf("%.3F %.3F %.3F %s ", $color[0] / 255, $color[1] / 255, $color[2] / 255, $fill ? 'rg' : 'RG');
        $first = array_shift($points);
        $cmd .= sprintf("%.2F %.2F m ", $first[0], self::HEIGHT - $first[1]);
        foreach ($points as $point) {
            $cmd .= sprintf("%.2F %.2F l ", $point[0], self::HEIGHT - $point[1]);
        }
        $cmd .= $fill ? "h f\n" : "h S\n";
        $this->current .= $cmd;
    }

    private function addObject(array &$objects, string $content): int
    {
        $id = count($objects) + 1;
        $objects[$id] = $content;
        return $id;
    }

    private function stream(string $content): string
    {
        return "<< /Length " . strlen($content) . " >>\nstream\n" . $content . "endstream";
    }

    private function imageObject(array $image): string
    {
        return '<< /Type /XObject /Subtype /Image /Width ' . (int) $image['width']
            . ' /Height ' . (int) $image['height']
            . ' /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /FlateDecode /Length '
            . strlen($image['data']) . " >>\nstream\n" . $image['data'] . "\nendstream";
    }

    private function readPngRgb(string $path): ?array
    {
        $bytes = file_get_contents($path);
        if ($bytes === false || substr($bytes, 0, 8) !== "\x89PNG\r\n\x1a\n") {
            return null;
        }

        $offset = 8;
        $width = 0;
        $height = 0;
        $bitDepth = 0;
        $colorType = 0;
        $idat = '';

        while ($offset + 8 <= strlen($bytes)) {
            $length = unpack('N', substr($bytes, $offset, 4))[1] ?? 0;
            $type = substr($bytes, $offset + 4, 4);
            $data = substr($bytes, $offset + 8, $length);
            $offset += 12 + $length;

            if ($type === 'IHDR') {
                $parts = unpack('Nwidth/Nheight/CbitDepth/CcolorType/Ccompression/Cfilter/Cinterlace', $data);
                $width = (int) ($parts['width'] ?? 0);
                $height = (int) ($parts['height'] ?? 0);
                $bitDepth = (int) ($parts['bitDepth'] ?? 0);
                $colorType = (int) ($parts['colorType'] ?? 0);
            } elseif ($type === 'IDAT') {
                $idat .= $data;
            } elseif ($type === 'IEND') {
                break;
            }
        }

        if ($width <= 0 || $height <= 0 || $bitDepth !== 8 || !in_array($colorType, [2, 6], true)) {
            return null;
        }

        $raw = gzuncompress($idat);
        if ($raw === false) {
            return null;
        }

        $channels = $colorType === 6 ? 4 : 3;
        $stride = $width * $channels;
        $pos = 0;
        $previous = array_fill(0, $stride, 0);
        $rgb = '';

        for ($y = 0; $y < $height; $y++) {
            $filter = ord($raw[$pos] ?? "\0");
            $pos++;
            $scanline = array_values(unpack('C*', substr($raw, $pos, $stride)) ?: []);
            $pos += $stride;
            $row = $this->unfilterPngRow($filter, $scanline, $previous, $channels);
            for ($i = 0; $i < count($row); $i += $channels) {
                if ($colorType === 6) {
                    $alpha = ($row[$i + 3] ?? 255) / 255;
                    $rgb .= chr((int) round(($row[$i] ?? 255) * $alpha + 255 * (1 - $alpha)));
                    $rgb .= chr((int) round(($row[$i + 1] ?? 255) * $alpha + 255 * (1 - $alpha)));
                    $rgb .= chr((int) round(($row[$i + 2] ?? 255) * $alpha + 255 * (1 - $alpha)));
                } else {
                    $rgb .= chr($row[$i] ?? 255) . chr($row[$i + 1] ?? 255) . chr($row[$i + 2] ?? 255);
                }
            }
            $previous = $row;
        }

        return [
            'name' => '',
            'width' => $width,
            'height' => $height,
            'data' => gzcompress($rgb, 6),
        ];
    }

    private function unfilterPngRow(int $filter, array $scanline, array $previous, int $bpp): array
    {
        $row = [];
        $length = count($scanline);
        for ($i = 0; $i < $length; $i++) {
            $left = $i >= $bpp ? ($row[$i - $bpp] ?? 0) : 0;
            $up = $previous[$i] ?? 0;
            $upperLeft = $i >= $bpp ? ($previous[$i - $bpp] ?? 0) : 0;
            $value = $scanline[$i] ?? 0;
            if ($filter === 1) {
                $value += $left;
            } elseif ($filter === 2) {
                $value += $up;
            } elseif ($filter === 3) {
                $value += intdiv($left + $up, 2);
            } elseif ($filter === 4) {
                $value += $this->paeth($left, $up, $upperLeft);
            }
            $row[$i] = $value & 0xff;
        }

        return $row;
    }

    private function paeth(int $a, int $b, int $c): int
    {
        $p = $a + $b - $c;
        $pa = abs($p - $a);
        $pb = abs($p - $b);
        $pc = abs($p - $c);
        if ($pa <= $pb && $pa <= $pc) {
            return $a;
        }

        return $pb <= $pc ? $b : $c;
    }

    private function build(array $objects, int $catalogId): string
    {
        $pdf = "%PDF-1.4\n";
        $offsets = [0];
        foreach ($objects as $id => $content) {
            $offsets[$id] = strlen($pdf);
            $pdf .= $id . " 0 obj\n" . $content . "\nendobj\n";
        }
        $xref = strlen($pdf);
        $pdf .= "xref\n0 " . (count($objects) + 1) . "\n0000000000 65535 f \n";
        for ($i = 1; $i <= count($objects); $i++) {
            $pdf .= sprintf("%010d 00000 n \n", $offsets[$i]);
        }
        $pdf .= "trailer\n<< /Size " . (count($objects) + 1) . " /Root " . $catalogId . " 0 R >>\nstartxref\n" . $xref . "\n%%EOF";
        return $pdf;
    }

    private function textWidth(string $text, float $size): float
    {
        return strlen($text) * $size * 0.52;
    }

    private function escape(string $text): string
    {
        $text = $this->ascii($text);
        return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $text);
    }

    private function unescapeForWidth(string $text): string
    {
        return str_replace(['\\\\', '\\(', '\\)'], ['\\', '(', ')'], $text);
    }

    private function ascii(string $text): string
    {
        $converted = function_exists('iconv')
            ? iconv('UTF-8', 'Windows-1252//TRANSLIT//IGNORE', $text)
            : false;
        if (is_string($converted)) {
            return $converted;
        }
        return preg_replace('/[^\\x20-\\x7E]/', '', $text) ?? '';
    }
}
