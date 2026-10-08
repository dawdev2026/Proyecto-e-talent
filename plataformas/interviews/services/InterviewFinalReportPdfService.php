<?php
declare(strict_types=1);

final class InterviewFinalReportPdfService
{
    private array $primary;
    private array $text;
    private array $muted;
    private array $surface;
    private string $brandName;
    private string $platformBrandName;
    private string $brandLogoPath = '';

    public function __construct(?PlatformSettingsModel $settings = null)
    {
        $settingsModel = $settings ?: new PlatformSettingsModel();
        $design = $settingsModel->designSettings();
        $login = $settingsModel->loginSettings();
        $this->primary = $this->hexToRgb((string) ($design['app_primary_color'] ?? ''), [0, 40, 60]);
        $this->text = $this->hexToRgb((string) ($design['portal_text_color'] ?? ''), [46, 46, 46]);
        $this->muted = $this->mix($this->text, [255, 255, 255], 0.58);
        $this->surface = $this->hexToRgb((string) ($design['card_content_background_color'] ?? ''), [255, 255, 255]);
        $this->brandName = trim((string) ($design['topbar_name'] ?? 'e-talent')) ?: 'e-talent';
        $this->platformBrandName = $this->brandName;
        foreach ([(string) ($login['login_logo_path'] ?? ''), (string) ($design['topbar_icon_path'] ?? '')] as $path) {
            $resolved = $this->publicPath($path);
            if ($resolved !== '' && is_file($resolved) && in_array(strtolower(pathinfo($resolved, PATHINFO_EXTENSION)), ['png', 'jpg', 'jpeg'], true)) {
                $this->brandLogoPath = $resolved;
                break;
            }
        }
        if ($this->brandLogoPath === '') {
            $fallbackLogo = (defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__, 3)) . '/public/assets/img/branding/metricatest_logo_propuesta1_20260915.png';
            if (is_file($fallbackLogo)) {
                $this->brandLogoPath = $fallbackLogo;
            }
        }
    }

    public function render(array $appointment, string $html, ?array $design = null, ?array $execution = null): string
    {
        if ($execution !== null && !empty($execution['available'])) {
            return $this->renderColaboralMockup($appointment, $execution, $design);
        }

        return $this->renderLegacy($appointment, $html, $design);
    }

    private function renderLegacy(array $appointment, string $html, ?array $design = null): string
    {
        $designMetadata = is_array($design['metadata'] ?? null) ? $design['metadata'] : [];
        $configuredBrand = trim((string) ($designMetadata['branding']['name'] ?? ''));
        if ($configuredBrand !== '') {
            $this->brandName = $configuredBrand;
        }

        $pdf = new PostulantSimplePdf();
        $sections = $this->sectionsFromHtml($html);
        $title = 'Informe final de entrevista';
        $profileTitle = $this->lineValue($sections, 'Título del perfil');
        $classification = $this->lineValue($sections, 'Clasificación psicométrica');
        $coverTitle = trim((string) ($designMetadata['cover_title'] ?? $title));
        $this->renderCover($pdf, $appointment, $profileTitle !== '' ? $profileTitle : $title, $coverTitle);

        $page = 2;
        $y = $this->startContentPage($pdf, $page, $title);
        $sectionNumber = 1;
        foreach ($sections as $section) {
            $heading = (string) ($section['heading'] ?? '');
            if ($heading === 'Identificación y alcance') {
                continue;
            }

            $paragraphs = $this->paragraphs((string) ($section['text'] ?? ''));
            if ($heading === 'Indicadores psicométricos disponibles' || $heading === 'Evaluaciones del entrevistador') {
                $metrics = $this->numericLines($paragraphs);
                if (count($metrics) >= 2) {
                    $paragraphs = $this->removeNumericLines($paragraphs);
                }
            } else {
                $metrics = [];
            }

            $needed = 66 + (count($paragraphs) * 18) + (count($metrics) > 0 ? 130 : 0);
            if ($y + min($needed, 160) > 760) {
                $this->drawFooter($pdf, $page);
                $pdf->addPage();
                $page++;
                $y = $this->startContentPage($pdf, $page, $title);
            }

            $this->drawSectionHeading($pdf, $y, $sectionNumber, $heading);
            $y += 43;
            if ($metrics) {
                $y = $this->drawMetricCards($pdf, $y, $metrics);
            }
            foreach ($paragraphs as $paragraph) {
                $paragraph = trim($paragraph);
                if ($paragraph === '') {
                    continue;
                }
                $lineCount = max(1, (int) ceil(mb_strlen($paragraph) / 96));
                if ($y + ($lineCount * 14) + 18 > 790) {
                    $this->drawFooter($pdf, $page);
                    $pdf->addPage();
                    $page++;
                    $y = $this->startContentPage($pdf, $page, $title);
                }
                $pdf->wrappedText(64, $y, 10.2, $paragraph, 495, 14, $this->text);
                $y += ($lineCount * 14) + 12;
            }

            if ($heading === 'Recomendación final' || $heading === 'Conclusiones y sugerencias') {
                $y = $this->drawRecommendationCard($pdf, $y, $classification);
            }
            $sectionNumber++;
        }

        $this->drawFooter($pdf, $page);

        return $pdf->output();
    }

    private function renderColaboralMockup(array $appointment, array $execution, ?array $design): string
    {
        $metadata = is_array($design['metadata'] ?? null) ? $design['metadata'] : [];
        $configuredBrand = trim((string) ($metadata['branding']['name'] ?? ''));
        if ($configuredBrand !== '') {
            $this->brandName = $configuredBrand;
        }

        $payload = is_array($execution['payload'] ?? null) ? $execution['payload'] : [];
        $candidate = is_array($payload['candidate'] ?? null) ? $payload['candidate'] : [];
        $process = is_array($payload['process'] ?? null) ? $payload['process'] : [];
        $interview = is_array($payload['interview'] ?? null) ? $payload['interview'] : [];
        $jobProfile = is_array($interview['process']['job_profile'] ?? null) ? $interview['process']['job_profile'] : [];
        $summary = is_array($payload['structured_summary'] ?? null) ? $payload['structured_summary'] : [];
        $criteria = is_array($jobProfile['evaluation_criteria'] ?? null) ? $jobProfile['evaluation_criteria'] : [];
        $evaluation = is_array($interview['evaluations'][0] ?? null) ? $interview['evaluations'][0] : [];
        $observed = [];
        foreach (($evaluation['criteria'] ?? []) as $criterion) {
            if (is_array($criterion) && trim((string) ($criterion['name'] ?? '')) !== '') {
                $observed[(string) $criterion['name']] = $criterion;
            }
        }

        $pdf = new PostulantSimplePdf();
        $profileTitle = (string) ($jobProfile['title'] ?? 'Perfil de competencias');
        $coverTitle = 'Informe individual de perfil de competencias';
        $coverData = [
            'evaluator' => (string) ($interview['note_metadata']['author_name'] ?? 'No confirmado'),
            'date' => (string) ($interview['appointment']['finished_at'] ?? 'Pendiente'),
            'subtitle' => 'Análisis de ajuste al cargo, motivación y proyecciones de carrera, sustentado en evidencia conductual.',
        ];
        $this->renderCover($pdf, $appointment, $profileTitle, $coverTitle, $coverData);

        $page = 2;
        $title = 'Informe individual de perfil de competencias';
        $this->startContentPage($pdf, $page, $title);
        $this->drawSectionHeading($pdf, 112, 1, 'Datos de Identificación');
        $identificationEnd = $this->drawIdentificationTable($pdf, 150, $appointment, $candidate, $process, $interview, $profileTitle, $jobProfile, $summary);
        $this->drawSectionHeading($pdf, $identificationEnd + 34, 2, 'Perfil de Competencias');
        $this->drawCompetencyChart($pdf, $identificationEnd + 72, $criteria, $observed);
        $this->drawFooter($pdf, $page);

        $pdf->addPage();
        $page = 3;
        $this->startContentPage($pdf, $page, $title);
        $summaryText = $this->summaryText($summary, $evaluation);
        $pdf->roundedBox(48, 108, 500, 95, [242, 242, 242], [242, 242, 242], 0.5);
        $pdf->text(66, 136, 9, 'SÍNTESIS TRANSVERSAL', [93, 113, 48], true);
        $pdf->wrappedText(66, 161, 10, $summaryText, 455, 14, [75, 75, 75]);
        $this->drawSectionHeading($pdf, 258, 3, 'Motivación y proyecciones de carrera');
        $motivation = $this->findEvaluationText($evaluation, 'comments', 'La motivación y la proyección de carrera no fueron registradas.');
        $pdf->wrappedText(64, 298, 10.2, $motivation, 485, 15, $this->text);
        $this->drawSectionHeading($pdf, 390, 4, 'Análisis de competencias requeridas para el cargo');
        $y = 435;
        foreach ($criteria as $criterion) {
            $needed = $this->competencyBlockHeight($criterion, $observed[(string) ($criterion['name'] ?? '')] ?? []);
            if ($y + $needed > 770) {
                $this->drawFooter($pdf, $page);
                $pdf->addPage();
                $page++;
                $this->startContentPage($pdf, $page, $title);
                $this->drawSectionHeading($pdf, 112, 4, 'Análisis de competencias requeridas para el cargo');
                $y = 157;
            }
            $y = $this->drawCompetencyBlock($pdf, $y, $criterion, $observed[(string) ($criterion['name'] ?? '')] ?? []);
        }
        $this->drawFooter($pdf, $page);

        $pdf->addPage();
        $page++;
        $this->startContentPage($pdf, $page, $title);
        $this->drawConclusionPage($pdf, $page, $summary, $evaluation, $interview);
        $this->drawFooter($pdf, $page);

        return $pdf->output();
    }

    private function drawIdentificationTable(PostulantSimplePdf $pdf, float $y, array $appointment, array $candidate, array $process, array $interview, string $profileTitle, array $jobProfile, array $summary): float
    {
        $rows = [
            ['Nombre del postulante', (string) ($candidate['name'] ?? $appointment['candidate_name'] ?? 'Sin dato')],
            ['Profesión / formación relevante', (string) ($candidate['profession'] ?? $candidate['education'] ?? 'Antecedente del CV no registrado')],
            ['Cargo al cual postula', $profileTitle],
            ['Cliente / institución', (string) ($candidate['company'] ?? 'Sin dato')],
            ['Proceso de selección', (string) ($interview['process']['name'] ?? $process['name'] ?? 'Sin dato')],
            ['Psicólogo evaluador', (string) ($interview['note_metadata']['author_name'] ?? 'No confirmado')],
            ['Fecha de evaluación', (string) ($interview['appointment']['finished_at'] ?? 'Pendiente')],
            ['Metodología y antecedentes', 'Perfil, entrevista, notas y antecedentes psicométricos disponibles'],
            ['Pruebas aplicadas', ((string) ($summary['missing_data'][0] ?? '') !== '' ? 'Resultados psicométricos disponibles con limitaciones declaradas' : 'No se aportaron resultados psicométricos')],
        ];
        $rowHeight = 24;
        foreach ($rows as $index => $row) {
            $rowY = $y + ($index * $rowHeight);
            $pdf->fillRect(48, $rowY, 500, $rowHeight, $index % 2 === 0 ? [242, 242, 242] : [255, 255, 255]);
            $pdf->text(58, $rowY + 16, 8, $row[0], [93, 113, 48], true);
            $pdf->wrappedText(225, $rowY + 16, 8.5, $row[1], 310, 10, $this->text);
        }
        $end = $y + (count($rows) * $rowHeight);
        $pdf->wrappedText(48, $end + 18, 8.5, 'Alcance: las conclusiones se circunscriben a las fuentes disponibles y deben diferenciar evidencia confirmada de antecedentes pendientes.', 500, 12, [75, 75, 75]);
        return $end + 34;
    }

    private function drawCompetencyChart(PostulantSimplePdf $pdf, float $y, array $criteria, array $observed): void
    {
        $olive = [93, 113, 48];
        $coral = [232, 130, 122];
        $pdf->roundedBox(48, $y, 500, 270, [242, 242, 242], [242, 242, 242], 0.5);
        $pdf->text(66, $y + 28, 9, 'NIVEL OBSERVADO VS. NIVEL REQUERIDO', $olive, true);
        $pdf->roundedBox(390, $y + 14, 140, 20, [245, 218, 35], [245, 218, 35], 0.5);
        $pdf->text(460, $y + 28, 7.5, 'Escala de valoración 1-5', $this->text, true, 'regular', 'center');
        foreach (array_slice($criteria, 0, 6) as $index => $criterion) {
            $name = (string) ($criterion['name'] ?? 'Competencia');
            $rowY = $y + 60 + ($index * 31);
            $pdf->text(66, $rowY + 8, 8.5, $this->truncate($name, 27), $this->text);
            $pdf->roundedBox(230, $rowY, 285, 12, [255, 255, 255], [255, 255, 255], 0.2);
            $item = $observed[$name] ?? [];
            $score = is_numeric($item['score'] ?? null) ? $this->scoreOnFive((float) $item['score']) : null;
            $requiredValue = $criterion['required_level'] ?? ($item['required_level'] ?? null);
            $required = is_numeric($requiredValue) ? (float) $requiredValue : null;
            if ($score !== null) {
                $width = max(4, min(285, 285 * ($score / 5)));
                $pdf->roundedBox(230, $rowY, $width, 12, $score >= 3.5 ? $olive : $coral, $score >= 3.5 ? $olive : $coral, 0.2);
                if ($required !== null) {
                    $markerX = 230 + (285 * ($required / 5));
                    $pdf->line($markerX, $rowY - 3, $markerX, $rowY + 15, $olive, 1.2);
                }
                $pdf->text(528, $rowY + 9, 8.5, number_format($score, 1, ',', ''), $this->text, true, 'regular', 'right');
            } else {
                $pdf->text(528, $rowY + 9, 8, 'Sin dato', $this->muted, false, 'regular', 'right');
            }
        }
        $pdf->roundedBox(66, $y + 240, 10, 10, $olive, $olive, 0.2);
        $pdf->text(82, $y + 249, 8, 'Observado', $this->text);
        $pdf->roundedBox(160, $y + 240, 10, 10, $coral, $coral, 0.2);
        $pdf->text(176, $y + 249, 8, 'Oportunidad de desarrollo', $this->text);
        $pdf->line(360, $y + 239, 360, $y + 251, $olive, 1.2);
        $pdf->text(370, $y + 249, 8, 'Nivel requerido', $this->text);
    }

    private function drawCompetencyBlock(PostulantSimplePdf $pdf, float $y, array $criterion, array $observation): float
    {
        $olive = [93, 113, 48];
        $surface = [242, 242, 242];
        $name = (string) ($criterion['name'] ?? 'Competencia');
        $score = is_numeric($observation['score'] ?? null) ? $this->scoreOnFive((float) $observation['score']) : null;
        $requiredValue = $criterion['required_level'] ?? ($observation['required_level'] ?? null);
        $required = is_numeric($requiredValue) ? number_format((float) $requiredValue, 1, ',', '') : 'No definido';
        $height = $this->competencyBlockHeight($criterion, $observation);
        $pdf->roundedBox(48, $y, 500, $height - 8, $surface, $surface, 0.5);
        $pdf->text(66, $y + 27, 11.5, $name, $olive, true);
        $pdf->roundedBox(310, $y + 10, 100, 22, [255, 255, 255], [255, 255, 255], 0.5);
        $pdf->text(360, $y + 25, 7.2, 'Requerido · ' . $required, $this->text, true, 'regular', 'center');
        $observedLabel = $score !== null ? 'Observado · ' . number_format($score, 1, ',', '') : 'Evidencia insuficiente';
        $observedColor = $score !== null ? [145, 166, 92] : [245, 218, 35];
        $pdf->roundedBox(420, $y + 10, 115, 22, $observedColor, $observedColor, 0.5);
        $pdf->text(477, $y + 25, 7.2, $observedLabel, $this->text, true, 'regular', 'center');
        $evidence = (string) ($observation['evidence'] ?? 'No existe evidencia suficiente para valorar esta competencia; debe explorarse y registrarse en una entrevista real.');
        $pdf->wrappedText(66, $y + 56, 9.3, $evidence, 455, 13, [75, 75, 75]);
        $meterY = $y + $height - 28;
        $pdf->roundedBox(66, $meterY, 455, 8, [255, 255, 255], [255, 255, 255], 0.2);
        if ($score !== null) {
            $fillWidth = max(4, min(455, 455 * ($score / 5)));
            $pdf->roundedBox(66, $meterY, $fillWidth, 8, $score >= 3.5 ? $olive : [232, 130, 122], $score >= 3.5 ? $olive : [232, 130, 122], 0.2);
        }
        if (is_numeric($requiredValue)) {
            $markerX = 66 + (455 * ((float) $requiredValue / 5));
            $pdf->line($markerX, $meterY - 4, $markerX, $meterY + 12, $olive, 1.2);
        }
        return $y + $height;
    }

    private function competencyBlockHeight(array $criterion, array $observation): float
    {
        $evidence = (string) ($observation['evidence'] ?? 'No existe evidencia suficiente para valorar esta competencia; debe explorarse y registrarse en una entrevista real.');
        return 86 + (max(1, (int) ceil(mb_strlen($evidence) / 92)) * 13) + 11;
    }

    private function scoreOnFive(float $score): float
    {
        return $score > 5 ? round(max(0, min(100, $score)) / 20, 1) : round(max(0, min(5, $score)), 1);
    }

    private function normalizeClassification(string $classification): string
    {
        $classification = trim($classification);
        $classification = str_replace('Recomendado con Observacion', 'Recomendable con Observaciones', $classification);
        $classification = str_replace('Recomendado', 'Recomendable', $classification);

        return $classification !== '' ? $classification : 'Evaluación pendiente';
    }

    private function drawConclusionPage(PostulantSimplePdf $pdf, int $page, array $summary, array $evaluation, array $interview): void
    {
        $this->drawSectionHeading($pdf, 112, 5, 'Conclusiones y sugerencias');
        $classification = $this->normalizeClassification((string) ($summary['classification']['label'] ?? 'Evaluación pendiente'));
        $pdf->roundedBox(48, 145, 500, 160, [242, 242, 242], [242, 242, 242], 0.5);
        $pdf->text(66, 172, 9, 'CATEGORÍA', [93, 113, 48], true);
        $pdf->roundedBox(66, 184, 290, 34, [93, 113, 48], [93, 113, 48], 1);
        $pdf->text(211, 206, 12, $classification, [255, 255, 255], true, 'regular', 'center');
        $strength = (string) ($summary['strengths']['personality'][0]['label'] ?? 'Fortalezas no disponibles');
        $risk = trim((string) ($evaluation['risks'] ?? 'La entrevista no contiene riesgos registrados.'));
        $pdf->text(66, 242, 8.5, 'Resultado psicométrico:', [93, 113, 48], true);
        $pdf->wrappedText(178, 242, 9.3, $classification, 360, 12, [75, 75, 75]);
        $pdf->text(66, 258, 8.5, 'Fortaleza de referencia:', [93, 113, 48], true);
        $pdf->wrappedText(178, 258, 9.3, $strength, 360, 12, [75, 75, 75]);
        $pdf->text(66, 274, 8.5, 'Aspecto a validar:', [93, 113, 48], true);
        $pdf->wrappedText(178, 274, 9.3, $risk, 360, 12, [75, 75, 75]);
        foreach ([['Recomendable', [145, 166, 92]], ['Recomendable con Observaciones', [245, 218, 35]], ['No Recomendable', [232, 130, 122]]] as $index => $option) {
            $x = 66 + ($index * 162);
            $width = $index === 1 ? 150 : 125;
            $pdf->roundedBox($x, 315, $width, 20, $option[1], $option[1], 0.5);
            $pdf->text($x + ($width / 2), 329, 7.3, $option[0], $this->text, true, 'regular', 'center');
        }
        $this->drawSuggestionsTable($pdf, 400, $evaluation);
        $pdf->wrappedText(48, 744, 8.5, 'Confidencialidad: Informe destinado exclusivamente al proceso y cargo indicados. Sus conclusiones se circunscriben a los antecedentes disponibles.', 500, 12, $this->muted);
    }

    private function drawSuggestionsTable(PostulantSimplePdf $pdf, float $y, array $evaluation): void
    {
        $olive = [93, 113, 48];
        $pdf->text(48, $y - 18, 13, 'Sugerencias para la incorporación y el desarrollo', $olive, true);
        $pdf->fillRect(48, $y, 500, 40, $olive);
        $headers = ['Aspecto a fortalecer', 'Acción concreta', 'Responsable sugerido', 'Plazo o hito', 'Indicador'];
        $widths = [105, 125, 95, 85, 90];
        $x = 56;
        foreach ($headers as $index => $header) {
            $pdf->wrappedText($x, $y + 16, 8, $header, $widths[$index] - 10, 10, [255, 255, 255], true);
            $x += $widths[$index];
        }
        $risk = trim((string) ($evaluation['risks'] ?? 'Evidencia conductual pendiente de validación.'));
        $cells = [$risk, 'Inducción y acompañamiento inicial.', 'Jefatura / RR.HH.', 'Por definir', 'Conducta observable'];
        $maxLines = 1;
        foreach ($cells as $index => $cell) {
            $maxChars = max(8, (int) floor(($widths[$index] - 10) / (8.2 * 0.52)));
            $maxLines = max($maxLines, (int) ceil(mb_strlen($cell) / $maxChars));
        }
        $rowHeight = max(72, ($maxLines * 11) + 38);
        $pdf->fillRect(48, $y + 40, 500, $rowHeight, [242, 242, 242]);
        $x = 56;
        foreach ($cells as $index => $cell) {
            $pdf->wrappedText($x, $y + 59, 8.2, $cell, $widths[$index] - 10, 11, [75, 75, 75]);
            $x += $widths[$index];
        }
    }

    private function summaryText(array $summary, array $evaluation): string
    {
        $classification = $this->normalizeClassification((string) ($summary['classification']['label'] ?? 'Evaluación pendiente'));
        $strength = (string) ($summary['strengths']['personality'][0]['label'] ?? 'fortalezas no disponibles');
        $risk = trim((string) ($evaluation['risks'] ?? 'La entrevista no contiene riesgos registrados.'));
        return 'Resultado psicométrico: ' . $classification . '. Fortaleza de referencia: ' . $strength . '. Aspecto a validar: ' . $risk;
    }

    private function findEvaluationText(array $evaluation, string $key, string $fallback): string
    {
        $value = trim((string) ($evaluation[$key] ?? ''));
        return $value !== '' ? $value : $fallback;
    }

    private function renderCover(PostulantSimplePdf $pdf, array $appointment, string $profileTitle, string $coverTitle, array $coverData = []): void
    {
        $pdf->addPage();
        $olive = [93, 113, 48];
        $coral = [232, 130, 122];
        $light = [242, 242, 242];
        $pdf->roundedBox(48, 60, 145, 36, [255, 255, 255], [163, 183, 105], 1.1);
        if ($this->brandLogoPath !== '') {
            $pdf->image($this->brandLogoPath, 60, 66, 24, 24);
        }
        $pdf->text(92, 82, 8.5, strtoupper($this->platformBrandName), $olive, true);
        $this->drawConcentricCircles($pdf, 555, 54);
        $pdf->roundedBox(360, 61, 190, 26, [255, 255, 255], $coral, 1);
        $pdf->text(455, 78, 8, 'INFORMACIÓN CONFIDENCIAL', $this->text, true, 'regular', 'center');
        $pdf->text(48, 215, 9.5, 'EVALUACIÓN DE ENTREVISTA', $olive, true);
        $titleLines = $this->titleLines($coverTitle);
        $pdf->text(48, 270, 31, $titleLines[0], $olive, true);
        $pdf->text(48, 310, 31, $titleLines[1], $olive, true);
        $pdf->line(48, 336, 104, 336, $coral, 3);
        $pdf->line(104, 336, 188, 336, [145, 166, 92], 3);
        $subtitle = (string) ($coverData['subtitle'] ?? 'Análisis integrado de evidencia conductual, perfil de competencias y antecedentes psicométricos.');
        $pdf->wrappedText(48, 372, 11.5, $subtitle, 430, 17, [75, 75, 75]);

        $pdf->roundedBox(48, 395, 500, 180, $light, $light, 0.5);
        $this->coverField($pdf, 70, 430, 'POSTULANTE', (string) ($appointment['candidate_name'] ?? 'Sin dato'));
        $this->coverField($pdf, 315, 430, 'CARGO AL QUE POSTULA', $profileTitle);
        $this->coverField($pdf, 70, 485, 'CLIENTE / INSTITUCIÓN', (string) ($appointment['company_name'] ?? 'CARABINEROS'));
        $this->coverField($pdf, 315, 485, 'PROCESO DE SELECCIÓN', (string) ($appointment['process_name'] ?? 'Sin dato'));
        $this->coverField($pdf, 70, 525, 'PSICÓLOGO EVALUADOR', (string) ($coverData['evaluator'] ?? 'No confirmado'));
        $this->coverField($pdf, 315, 525, 'FECHA DE EVALUACIÓN', (string) ($coverData['date'] ?? 'Pendiente'));
        $this->drawWave($pdf, 0, 635, $olive, 2.2);
        $this->drawWave($pdf, 0, 663, [145, 166, 92], 1.2);
        $this->drawWave($pdf, 0, 691, [211, 216, 182], 1.1);
        $this->drawWave($pdf, 0, 719, $coral, 1.8);
        $pdf->text(48, 824, 8.5, $this->platformBrandName . ' · ' . (string) ($appointment['process_name'] ?? 'Proceso'), $this->muted);
        $pdf->text(548, 824, 8.5, 'Borrador para revisión', $this->muted, false, 'regular', 'right');
    }

    private function coverField(PostulantSimplePdf $pdf, float $x, float $y, string $label, string $value): void
    {
        $pdf->text($x, $y, 8.5, $label, [93, 113, 48], true);
        $pdf->wrappedText($x, $y + 18, 10.5, $value !== '' ? $value : 'Sin dato', 210, 13, $this->text);
    }

    private function startContentPage(PostulantSimplePdf $pdf, int $page, string $title): float
    {
        $pdf->addPage();
        $olive = [93, 113, 48];
        $coral = [232, 130, 122];
        $pdf->roundedBox(48, 35, 105, 28, [255, 255, 255], [163, 183, 105], 1);
        if ($this->brandLogoPath !== '') {
            $pdf->image($this->brandLogoPath, 56, 40, 18, 18);
        }
        $pdf->text(80, 53, 7.5, strtoupper($this->platformBrandName), $olive, true);
        $pdf->text(548, 48, 9, strtoupper($title), $olive, true, 'regular', 'right');
        $pdf->text(548, 61, 7.5, 'Información confidencial', $this->muted, false, 'regular', 'right');
        $pdf->line(48, 78, 548, 78, $olive, 2.2);
        $pdf->line(48, 78, 95, 78, $coral, 2.2);
        return 116;
    }

    private function drawSectionHeading(PostulantSimplePdf $pdf, float $y, int $number, string $heading): void
    {
        $olive = [93, 113, 48];
        $pdf->roundedBox(48, $y - 20, 28, 28, $olive, $olive, 0.5);
        $pdf->text(62, $y - 1, 10, $this->roman($number), [255, 255, 255], true, 'regular', 'center');
        $pdf->text(88, $y, 17, $heading, $olive, true);
    }

    private function drawMetricCards(PostulantSimplePdf $pdf, float $y, array $metrics): float
    {
        $olive = [93, 113, 48];
        $light = [242, 242, 242];
        $cardWidth = 158;
        foreach (array_values($metrics) as $index => $metric) {
            $column = $index % 3;
            $row = intdiv($index, 3);
            $x = 48 + ($column * 171);
            $cardY = $y + ($row * 76);
            $pdf->roundedBox($x, $cardY, $cardWidth, 60, $light, $light, 0.5);
            $pdf->text($x + 10, $cardY + 18, 8.5, $this->truncate((string) $metric['label'], 24), $olive, true);
            $pdf->text($x + 10, $cardY + 42, 16, (string) $metric['value'], $olive, true);
        }
        return $y + (ceil(count($metrics) / 3) * 76) + 10;
    }

    private function drawRecommendationCard(PostulantSimplePdf $pdf, float $y, string $classification): float
    {
        $olive = [93, 113, 48];
        $yellow = [245, 218, 35];
        $cardY = min($y + 8, 680);
        $pdf->roundedBox(48, $cardY, 500, 118, [242, 242, 242], [242, 242, 242], 0.5);
        $pdf->text(66, $cardY + 26, 9, 'CATEGORÍA', $olive, true);
        $pdf->roundedBox(66, $cardY + 38, 290, 38, $olive, $olive, 1);
        $pdf->text(82, $cardY + 63, 15, $classification !== '' ? $classification : 'Pendiente de validación', [255, 255, 255], true);
        $pdf->roundedBox(66, $cardY + 88, 170, 20, $yellow, $yellow, 0.5);
        $pdf->text(151, $cardY + 102, 8, 'Revisión profesional requerida', $this->text, true, 'regular', 'center');
        return $cardY + 132;
    }

    private function drawFooter(PostulantSimplePdf $pdf, int $page): void
    {
        $pdf->line(48, 796, 548, 796, [205, 205, 205], 0.7);
        $pdf->text(48, 818, 8, $this->platformBrandName . ' · Informe final de entrevista', $this->muted);
        $pdf->text(548, 818, 8, (string) $page, $this->muted, false, 'regular', 'right');
    }

    private function drawWave(PostulantSimplePdf $pdf, float $x, float $y, array $color, float $width): void
    {
        $points = [];
        $segments = 16;
        $span = 595;
        for ($index = 0; $index <= $segments; $index++) {
            $offset = ($span / $segments) * $index;
            $points[] = [$x + $offset, $y + (sin($index / 2.05) * 28)];
        }

        $last = count($points) - 1;
        for ($index = 0; $index < $last; $index++) {
            [$x1, $y1] = $points[$index];
            [$x2, $y2] = $points[$index + 1];
            [$x0, $y0] = $points[max(0, $index - 1)];
            [$x3, $y3] = $points[min($last, $index + 2)];
            $control1X = $x1 + (($x2 - $x0) / 6);
            $control1Y = $y1 + (($y2 - $y0) / 6);
            $control2X = $x2 - (($x3 - $x1) / 6);
            $control2Y = $y2 - (($y3 - $y1) / 6);
            $pdf->curve($x1, $y1, $control1X, $control1Y, $control2X, $control2Y, $x2, $y2, $color, $width);
        }
    }

    private function drawConcentricCircles(PostulantSimplePdf $pdf, float $centerX, float $centerY): void
    {
        $circles = [
            [95, [211, 216, 182], 1.0],
            [75, [181, 193, 139], 1.0],
            [55, [145, 166, 92], 1.2],
        ];
        foreach ($circles as [$radius, $color, $lineWidth]) {
            $k = 0.5522847498;
            $pdf->curve($centerX + $radius, $centerY, $centerX + $radius, $centerY + ($radius * $k), $centerX + ($radius * $k), $centerY + $radius, $centerX, $centerY + $radius, $color, $lineWidth);
            $pdf->curve($centerX, $centerY + $radius, $centerX - ($radius * $k), $centerY + $radius, $centerX - $radius, $centerY + ($radius * $k), $centerX - $radius, $centerY, $color, $lineWidth);
            $pdf->curve($centerX - $radius, $centerY, $centerX - $radius, $centerY - ($radius * $k), $centerX - ($radius * $k), $centerY - $radius, $centerX, $centerY - $radius, $color, $lineWidth);
            $pdf->curve($centerX, $centerY - $radius, $centerX + ($radius * $k), $centerY - $radius, $centerX + $radius, $centerY - ($radius * $k), $centerX + $radius, $centerY, $color, $lineWidth);
        }
    }

    private function sectionsFromHtml(string $html): array
    {
        $sections = [];
        preg_match_all('/<section>\s*<h2>(.*?)<\/h2>(.*?)<\/section>/is', $html, $matches, PREG_SET_ORDER);
        foreach ($matches as $match) {
            $sections[] = [
                'heading' => trim(html_entity_decode(strip_tags((string) $match[1]), ENT_QUOTES, 'UTF-8')),
                'text' => $this->plainText((string) $match[2]),
            ];
        }
        return $sections;
    }

    private function lineValue(array $sections, string $label): string
    {
        foreach ($sections as $section) {
            foreach ($this->paragraphs((string) ($section['text'] ?? '')) as $paragraph) {
                if (strpos($paragraph, $label . ':') === 0) {
                    return trim(substr($paragraph, strlen($label) + 1));
                }
            }
        }
        return '';
    }

    private function numericLines(array $paragraphs): array
    {
        $metrics = [];
        foreach ($paragraphs as $paragraph) {
            if (preg_match('/^([^:]{2,60}):\s*(-?[0-9]+(?:[.,][0-9]+)?)$/u', trim($paragraph), $match)) {
                $metrics[] = ['label' => trim($match[1]), 'value' => str_replace(',', '.', $match[2])];
            }
        }
        return $metrics;
    }

    private function removeNumericLines(array $paragraphs): array
    {
        return array_values(array_filter($paragraphs, static function (string $paragraph): bool {
            return !preg_match('/^([^:]{2,60}):\s*(-?[0-9]+(?:[.,][0-9]+)?)$/u', trim($paragraph));
        }));
    }

    private function roman(int $number): string
    {
        $map = [1 => 'I', 2 => 'II', 3 => 'III', 4 => 'IV', 5 => 'V', 6 => 'VI', 7 => 'VII', 8 => 'VIII', 9 => 'IX', 10 => 'X'];
        return $map[$number] ?? (string) $number;
    }

    private function truncate(string $value, int $length): string
    {
        return mb_strlen($value) > $length ? mb_substr($value, 0, $length - 1) . '…' : $value;
    }

    private function titleLines(string $title): array
    {
        $title = trim($title);
        if ($title === '') {
            return ['Informe final de', 'entrevista'];
        }
        $words = preg_split('/\s+/u', $title) ?: [];
        $middle = max(1, (int) ceil(count($words) / 2));
        $first = trim(implode(' ', array_slice($words, 0, $middle)));
        $second = trim(implode(' ', array_slice($words, $middle)));
        return [$first !== '' ? $first : 'Informe final de', $second !== '' ? $second : 'entrevista'];
    }

    public function filename(array $appointment): string
    {
        $candidate = $this->slug((string) ($appointment['candidate_name'] ?? 'postulante'));

        return 'reporte_final_entrevista_' . $candidate . '.pdf';
    }

    private function paragraphs(string $text): array
    {
        $parts = preg_split('/\n{2,}/', trim($text)) ?: [];
        $parts = array_values(array_filter(array_map('trim', $parts)));

        return $parts ?: ['Reporte final sin contenido disponible.'];
    }

    private function plainText(string $html): string
    {
        // El HTML generado por los informes incluye estilos embebidos para la
        // vista web. El contenido de <style> no debe terminar como texto del
        // PDF al eliminar las etiquetas HTML.
        $html = preg_replace('/<style\b[^>]*>.*?<\/style>/is', '', $html) ?? $html;
        $html = preg_replace('/<script\b[^>]*>.*?<\/script>/is', '', $html) ?? $html;
        $html = preg_replace('/<!doctype\b[^>]*>/i', '', $html) ?? $html;
        $html = preg_replace('/<\/(p|div|section|article|dl|h[1-6]|li)>/i', "\n\n", $html) ?? $html;
        $html = preg_replace('/<\/(dt|dd)>/i', "\n", $html) ?? $html;
        $html = preg_replace('/<br\s*\/?>/i', "\n", $html) ?? $html;

        return html_entity_decode(trim(strip_tags($html)), ENT_QUOTES, 'UTF-8');
    }

    private function hexToRgb(string $hex, array $fallback): array
    {
        $hex = ltrim(trim($hex), '#');
        if (strlen($hex) === 3) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }
        if (!preg_match('/^[0-9a-fA-F]{6}$/', $hex)) {
            return $fallback;
        }

        return [hexdec(substr($hex, 0, 2)), hexdec(substr($hex, 2, 2)), hexdec(substr($hex, 4, 2))];
    }

    private function mix(array $a, array $b, float $ratio): array
    {
        return [
            (int) round($a[0] * (1 - $ratio) + $b[0] * $ratio),
            (int) round($a[1] * (1 - $ratio) + $b[1] * $ratio),
            (int) round($a[2] * (1 - $ratio) + $b[2] * $ratio),
        ];
    }

    private function slug(string $value): string
    {
        $value = strtolower(trim(iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value) ?: $value));
        $value = preg_replace('/[^a-z0-9]+/', '_', $value) ?? '';

        return trim($value, '_') ?: 'postulante';
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
}
