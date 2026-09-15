<?php
declare(strict_types=1);

final class EvaluationSurveyIntegrityReportPdfService
{
    private array $primary;
    private array $accent;
    private array $text;
    private array $muted;
    private array $surface;
    private array $light;
    private array $border;
    private array $warning = [183, 121, 31];
    private array $danger = [178, 55, 55];
    private array $success = [37, 137, 87];
    private string $brandName;
    private string $logoPath = '';

    public function __construct(?PlatformSettingsModel $settings = null)
    {
        $settingsModel = $settings ?: new PlatformSettingsModel();
        $design = $settingsModel->designSettings();
        $login = $settingsModel->loginSettings();
        $this->primary = $this->hexToRgb((string) ($design['app_primary_color'] ?? ''), [35, 57, 117]);
        $this->accent = $this->hexToRgb((string) ($design['button_background_color'] ?? ''), [146, 190, 46]);
        $this->text = $this->hexToRgb((string) ($design['portal_text_color'] ?? ''), [40, 49, 70]);
        $this->muted = $this->mix($this->text, [255, 255, 255], 0.55);
        $this->light = $this->hexToRgb((string) ($design['layout_background_color'] ?? ''), [246, 248, 244]);
        $this->surface = $this->hexToRgb((string) ($design['card_content_background_color'] ?? ''), [248, 249, 252]);
        $this->border = $this->hexToRgb((string) ($design['table_border_color'] ?? ''), [217, 217, 217]);
        $this->brandName = trim((string) ($design['topbar_name'] ?? 'e-talent')) ?: 'e-talent';
        $this->logoPath = $this->brandLogoPath($design, $login);
    }

    public function render(array $report): string
    {
        $pdf = new PostulantSimplePdf();
        $summary = (array) ($report['summary'] ?? []);
        $rows = array_values(array_filter((array) ($report['rows'] ?? []), static fn(array $row): bool => ($row['alert_level'] ?? 'none') !== 'none'));
        $analysis = (array) ($report['analysis'] ?? $this->analysisSnapshot());
        $summary['distinct_incident_people'] = $analysis['universe']['distinct_incident_people'];
        $summary['method_high'] = $analysis['levels']['high']['people'];
        $summary['method_medium'] = $analysis['levels']['medium']['people'];
        $summary['method_low'] = $analysis['levels']['low']['people'];
        $this->coverPage($pdf, $summary);
        $this->executivePage($pdf, $summary, $analysis);

        $this->segmentationChartPage($pdf, $analysis);
        $page = 4;
        foreach ($this->analysisPages($pdf, $analysis, $page) as $pageNumber) {
            $page = $pageNumber + 1;
        }
        foreach (array_chunk($this->groupRows($rows), 5) as $chunk) {
            $pdf->addPage();
            $this->pageHeader($pdf, 'RESUMEN POR EVALUACIÓN / PROCESO', 'Página ' . $page);
            $pdf->text(36, 91, 18, 'Dónde se concentran las incidencias', $this->primary, true);
            $pdf->text(36, 114, 9.5, 'Personas afectadas y tipificación relevante. Se excluyen registros normales.', $this->muted);
            $y = 143.0;
            foreach ($chunk as $group) {
                $pdf->roundedBox(36, $y, 523, 91, [255, 255, 255], $this->border, 0.8);
                $pdf->fillRect(36, $y, 5, 91, $this->primary);
                $pdf->text(51, $y + 17, 8.2, 'EVALUACIÓN', $this->primary, true);
                $pdf->wrappedText(127, $y + 17, 9.1, $group['form_title'], 335, 11, $this->text);
                $pdf->text(51, $y + 35, 8.2, 'PROCESO', $this->primary, true);
                $pdf->wrappedText(127, $y + 35, 8.5, $group['process_name'], 335, 10, $this->muted);
                $pdf->wrappedText(51, $y + 60, 8.1, 'INCIDENCIAS CLASIFICADAS: ' . $group['types'], 420, 10, $this->text);
                $pdf->text(548, $y + 18, 8.2, (string) $group['people'] . ' personas', $this->primary, true, 'regular', 'right');
                $pdf->text(548, $y + 34, 8.2, (string) $group['high'] . ' alta / ' . (string) $group['review'] . ' revisión', $this->danger, false, 'regular', 'right');
                $pdf->text(548, $y + 51, 8.2, (string) $group['incidents'] . ' señales', $this->muted, false, 'regular', 'right');
                $y += 102;
            }
            $this->footer($pdf, $page++);
        }

        $segments = $this->personSegments($rows, $analysis['person_levels'] ?? []);
        $pdf->addPage();
        $this->pageHeader($pdf, '4. SEGMENTACIÓN NOMINADA', 'Página ' . $page);
        $pdf->text(36, 91, 18, 'Segmentación nominada', $this->primary, true);
        $pdf->wrappedText(36, 116, 9.2, 'Las tablas siguientes ordenan a las personas por prioridad de revisión dentro de cada nivel. La columna Señales indica el total de eventos registrados y Patrón conductual detalla las señales que sustentan la clasificación.', 523, 12, $this->text);
        $pdf->roundedBox(36, 190, 523, 155, [250, 250, 248], $this->border, 0.8);
        $pdf->text(52, 218, 10.5, 'Tratamiento de esta sección', $this->danger, true);
        $pdf->wrappedText(52, 242, 9, 'Contiene datos personales asociados a una evaluación de personal. Su circulación debe limitarse a quienes tengan un rol formal en el comité de revisión y su conservación debe sujetarse al plazo del proceso.', 490, 12, $this->text);
        $pdf->wrappedText(52, 292, 9, 'La aparición de una persona en el nivel ALTO no implica una conclusión sobre su conducta: significa que su caso reúne los criterios para ser revisado antes que los demás.', 490, 12, $this->text);
        $pdf->text(36, 390, 13, 'Orden de atención sugerido', $this->primary, true);
        $pdf->wrappedText(36, 412, 9, 'Comenzar por ALTO, de arriba hacia abajo, y continuar con MEDIO. Los casos BAJO se registran sin abrir evidencia salvo criterio excepcional; SOLO TÉCNICO debe derivarse a soporte.', 523, 12, $this->text);
        $this->analysisTable($pdf, 472, ['NIVEL', 'PERSONAS', 'TRATAMIENTO'], [
            ['ALTO', (string) count($segments['ALTO']), 'Revisión audiovisual completa, priorizada por intensidad.'],
            ['MEDIO', (string) count($segments['MEDIO']), 'Validación en contexto y escalamiento si corresponde.'],
            ['BAJO', (string) count($segments['BAJO']), 'Registro; no abrir evidencia salvo criterio excepcional.'],
            ['SOLO TÉCNICO', (string) count($segments['SOLO TÉCNICO']), 'Soporte técnico o nueva rendición.'],
        ], [20, 18, 62]);
        $this->footer($pdf, $page++);

        foreach (['ALTO', 'MEDIO', 'BAJO', 'SOLO TÉCNICO'] as $segmentLevel) {
            $segmentRows = $segments[$segmentLevel];
            foreach (array_chunk($segmentRows, 8) as $chunk) {
                $pdf->addPage();
                $this->pageHeader($pdf, '4. SEGMENTACIÓN NOMINADA', 'Página ' . $page);
                $pdf->text(36, 91, 16, 'Nivel ' . $segmentLevel . ' - ' . count($segmentRows) . ' personas', $segmentLevel === 'ALTO' ? $this->danger : ($segmentLevel === 'MEDIO' ? $this->warning : $this->primary), true);
                $subtitle = $segmentLevel === 'ALTO' ? 'Revisión audiovisual completa. Priorizar de arriba hacia abajo: la tabla está ordenada por intensidad y variedad de señales.' : ($segmentLevel === 'MEDIO' ? 'Revisar solo los tramos marcados y el contexto inmediato; escalar si se confirma una combinación relevante.' : ($segmentLevel === 'BAJO' ? 'Registro de la señal aislada. No abrir evidencia salvo criterio excepcional.' : 'Derivación a soporte: la evidencia técnica incompleta no debe interpretarse como conducta.'));
                $pdf->wrappedText(36, 114, 9, $subtitle, 523, 12, $this->text);
                $this->segmentTable($pdf, 150, $chunk, $segmentLevel);
                $this->footer($pdf, $page++);
            }
        }

        /* Legacy card rendering is intentionally replaced by the level tables above. */
        /* foreach (array_chunk($rows, 4) as $chunk) {
            $pdf->addPage();
            $this->pageHeader($pdf, 'DETALLE FOCALIZADO', 'Página ' . $page);
            $pdf->text(36, 91, 18, 'Personas que requieren atención', $this->primary, true);
            $pdf->text(36, 114, 9.5, 'Solo se muestran casos clasificados con señales relevantes.', $this->muted);
            $y = 143.0;
            foreach ($chunk as $row) {
                $isHigh = (string) ($row['alert_level'] ?? '') === 'high';
                $color = $isHigh ? $this->danger : $this->warning;
                $label = $isHigh ? 'ALERTA ALTA' : 'REVISIÓN';
                $signals = $this->signalLabels((array) ($row['signal_types'] ?? []));
                $pdf->roundedBox(36, $y, 523, 125, [255, 255, 255], $this->border, 0.8);
                $pdf->fillRect(36, $y, 5, 125, $color);
                $pdf->text(51, $y + 16, 8.2, $label, $color, true);
                $pdf->text(548, $y + 16, 8, (string) ((int) ($row['incident_total'] ?? 0)) . ' señales', $color, true, 'regular', 'right');
                $pdf->wrappedText(51, $y + 31, 9.1, $this->clean((string) ($row['user_name'] ?? 'Persona')), 490, 10, $this->text, true);
                $pdf->wrappedText(51, $y + 47, 7.8, 'EVALUACIÓN: ' . $this->clean((string) ($row['form_title'] ?? 'Evaluación')), 490, 9, $this->text);
                $pdf->wrappedText(51, $y + 58, 7.8, 'PROCESO: ' . $this->clean((string) ($row['process_name'] ?? 'Proceso')), 490, 9, $this->muted);
                $pdf->wrappedText(51, $y + 70, 7.8, 'PONER ATENCIÓN EN: ' . $this->reason($row), 490, 9, $color, true);
                $pdf->wrappedText(51, $y + 101, 7.5, 'INCIDENCIAS CLASIFICADAS: ' . $signals, 490, 8.5, $this->text);
                $y += 135;
            }
            $this->footer($pdf, $page++);
        } */
        return $pdf->output();
    }

    public function filename(): string { return 'reporte-ejecutivo-incidencias-evaluaciones.pdf'; }

    private function coverPage(PostulantSimplePdf $pdf, array $summary): void
    {
        $this->pageHeader($pdf, 'SUPERVISIÓN DE EVALUACIONES', 'Informe ejecutivo');
        $pdf->text(36, 126, 24, 'Informe ejecutivo de incidencias', $this->text, true);
        $pdf->text(36, 153, 12, 'Priorización de casos para revisión humana autorizada', $this->muted);
        $pdf->line(36, 177, 559, 177, $this->primary, 1.5);
        $this->analysisTable($pdf, 201, ['CAMPO', 'DETALLE'], [
            ['Alcance', number_format((int) ($summary['assigned_people'] ?? 0), 0, ',', '.') . ' asignaciones activas con proceso asignado'],
            ['Base analizada', number_format((int) ($summary['attempted_people'] ?? 0), 0, ',', '.') . ' asignaciones con intento y señales observables registradas'],
            ['Fecha de emisión', date('d/m/Y H:i')],
            ['Clasificación', 'CONFIDENCIAL - uso interno del comité de revisión'],
        ], [24, 76]);
        $pdf->roundedBox(36, 390, 523, 126, [250, 250, 248], $this->border, 0.8);
        $pdf->fillRect(36, 390, 5, 126, $this->danger);
        $pdf->text(52, 416, 10.5, 'Advertencia de uso', $this->danger, true);
        $pdf->wrappedText(52, 440, 9, 'Este informe ordena y prioriza señales técnicas registradas durante la rendición de las pruebas. Ninguna señal, por sí sola, constituye prueba de copia o suplantación de identidad.', 490, 12, $this->text);
        $pdf->wrappedText(52, 488, 9, 'Toda decisión debe fundarse en la revisión de la evidencia disponible y en el descargo de la persona, no únicamente en este documento.', 490, 12, $this->text);
        $this->footer($pdf, 1);
    }

    private function executivePage(PostulantSimplePdf $pdf, array $summary, array $analysis): void
    {
        $pdf->addPage();
        $this->pageHeader($pdf, '1. RESUMEN EJECUTIVO', 'Página 2');
        $pdf->text(36, 91, 18, 'Resumen ejecutivo', $this->text, true);
        $pdf->wrappedText(36, 118, 9.7, 'La unidad correcta para dimensionar la revisión es la persona. Las asignaciones pueden repetirse cuando una misma persona rindió más de una prueba, por lo que el informe consolida la atención por persona y conserva el detalle por evaluación y proceso.', 523, 13, $this->text);
        $this->summaryStrip($pdf, 166, [[number_format((int) ($analysis['universe']['distinct_incident_people'] ?? 0), 0, ',', '.'), 'personas con incidencias'], [number_format((int) ($analysis['levels']['high']['people'] ?? 0), 0, ',', '.'), 'nivel ALTO'], [number_format((int) ($analysis['levels']['medium']['people'] ?? 0), 0, ',', '.'), 'nivel MEDIO'], [number_format((int) ($analysis['levels']['low']['people'] ?? 0) + (int) ($analysis['levels']['technical']['people'] ?? 0), 0, ',', '.'), 'BAJO / técnico']]);
        $pdf->text(36, 253, 13, 'Hallazgos principales', $this->primary, true);
        $high = (int) ($analysis['levels']['high']['people'] ?? 0); $fourSignals = (int) (($analysis['conduct_distribution'][4][1] ?? 0)); $concentration = (array) ($analysis['concentration'] ?? []);
        $findings = [
            'La revisión real es acotada: ' . $high . ' personas ALTO concentran la prioridad audiovisual y deben revisarse primero.',
            'La evidencia está concentrada: ' . ($concentration[0][1] ?? '0%') . ' del volumen se encuentra en el 10% de las personas con más eventos y ' . ($concentration[1][1] ?? '0%') . ' en el 25%.',
            'Posibles voces múltiples y pérdida de foco explican la mayor parte de las señales, pero también son las más ambiguas.',
            'Lo que discrimina es la combinación: ' . $fourSignals . ' personas presentan las cuatro señales conductuales distintas.',
            'Una parte relevante es ruido técnico: ' . (int) ($analysis['totals']['technical_events'] ?? 0) . ' de ' . (int) ($analysis['totals']['classified_events'] ?? 0) . ' señales clasificadas corresponden a fallas técnicas.',
            'La prioridad debe compararse por proporción dentro de cada proceso, no solo por volumen absoluto.',
        ];
        $y = 278.0;
        foreach ($findings as $finding) { $pdf->text(42, $y, 9, '-', $this->text, true); $pdf->wrappedText(56, $y, 8.7, $finding, 497, 11, $this->text); $y += 38; }
        $pdf->text(36, 526, 13, 'De la asignación al caso: dónde se concentra la revisión', $this->text, true);
        $this->funnelChart($pdf, 550, [['Personas asignadas', (int) ($analysis['universe']['assigned'] ?? 0)], ['Rindieron la prueba', (int) ($analysis['universe']['attempted'] ?? 0)], ['Intentos con incidencia', (int) ($analysis['universe']['incident_assignments'] ?? 0)], ['Personas distintas involucradas', (int) ($analysis['universe']['distinct_incident_people'] ?? 0)]]);
        $pdf->text(36, 753, 7.8, 'Figura 1. Del universo asignado al número real de personas a revisar.', $this->muted, false, 'italic');
        $this->footer($pdf, 2);
    }

    private function summaryStrip(PostulantSimplePdf $pdf, float $y, array $items): void
    {
        $x = 36.0; $width = 523 / max(1, count($items));
        foreach ($items as $index => [$value, $label]) { $pdf->fillRect($x, $y, $width, 60, $index === 0 ? $this->light : [248, 248, 247]); $pdf->text($x + $width / 2, $y + 25, 18, $value, $index === 1 ? $this->danger : ($index === 2 ? $this->warning : $this->text), true, 'regular', 'center'); $pdf->text($x + $width / 2, $y + 45, 7.5, $label, $this->muted, false, 'regular', 'center'); $x += $width; }
    }

    private function segmentationChartPage(PostulantSimplePdf $pdf, array $analysis): void
    {
        $pdf->addPage(); $this->pageHeader($pdf, '1. RESUMEN EJECUTIVO', 'Página 3');
        $pdf->text(36, 91, 16, 'Segmentación de las personas con incidencias', $this->text, true);
        $pdf->text(36, 116, 9, 'Distribución según el criterio de clasificación descrito en la sección 2.', $this->muted);
        $levels = [['ALTO', (int) ($analysis['levels']['high']['people'] ?? 0), $this->danger, 'Revisión prioritaria'], ['MEDIO', (int) ($analysis['levels']['medium']['people'] ?? 0), [245, 171, 24], 'Validación en contexto'], ['BAJO', (int) ($analysis['levels']['low']['people'] ?? 0), [15, 161, 25], 'Registro / sin acción'], ['SOLO TÉCNICO', (int) ($analysis['levels']['technical']['people'] ?? 0), [41, 121, 196], 'Calidad de evidencia']]; $total = max(1, array_sum(array_column($levels, 1))); $x = 70.0; $barY = 190.0; $barW = 455.0;
        foreach ($levels as [$label, $value, $color, $caption]) {
            $width = $barW * ($value / $total);
            $pdf->fillRect($x, $barY, max(2, $width), 42, $color);
            if ($width > 35) $pdf->text($x + $width / 2, $barY + 27, 16, (string) $value, [255, 255, 255], true, 'regular', 'center');
            $center = $x + $width / 2;
            $pdf->text($center, $barY - 12, 9, number_format($value / $total * 100, 0) . '%', $this->muted, false, 'regular', 'center');
            if ($width < 52) {
                $pdf->text($x + 8, $barY + 67, 7.8, $label, $this->text, true);
                $pdf->text($x + 8, $barY + 82, 7.2, $caption, $this->muted);
            } else {
                $pdf->text($center, $barY + 67, 8.5, $label, $this->text, true, 'regular', 'center');
                $pdf->text($center, $barY + 82, 7.5, $caption, $this->muted, false, 'regular', 'center');
            }
            $x += $width;
        }
        $pdf->text(300, 306, 8, 'Figura 2. Segmentación dinámica de las personas según el criterio aplicado.', $this->muted, false, 'italic', 'center');
        $pdf->roundedBox(36, 365, 523, 150, $this->light, $this->border, 0.8); $pdf->text(50, 391, 10.5, 'Cómo leer este gráfico', $this->primary, true); $pdf->wrappedText(50, 416, 9, 'ALTO concentra los casos que combinan señales conductuales, intensidad o score suficiente para una revisión prioritaria. MEDIO requiere validación contextual. BAJO se conserva como registro y SOLO TÉCNICO se deriva a soporte. Los valores se recalculan con los registros vigentes al momento de generar el informe.', 490, 12, $this->text);
        $pdf->text(36, 568, 13, 'Criterio de lectura para el cliente', $this->primary, true); $pdf->wrappedText(36, 592, 9, 'El gráfico no determina responsabilidad. Ordena el trabajo de revisión y evita mezclar fallas técnicas con señales que podrían requerir revisión audiovisual.', 523, 12, $this->text); $this->footer($pdf, 3);
    }

    private function funnelChart(PostulantSimplePdf $pdf, float $y, array $items): void
    {
        $max = max(1, (int) $items[0][1]); $colors = [[158, 196, 237], [104, 161, 226], [45, 108, 184], [31, 83, 145]];
        foreach ($items as $i => [$label, $value]) { $rowY = $y + ($i * 38); $pdf->text(150, $rowY + 12, 8.2, $label, $this->muted, false, 'regular', 'right'); $barX = 160; $barW = 310 * ((int) $value / $max); $pdf->fillRect($barX, $rowY, $barW, 22, $colors[$i]); $pdf->text($barX + $barW + 6, $rowY + 15, 9.5, number_format((int) $value, 0, ',', '.'), $this->text, true); if ($i > 0) $pdf->text($barX + $barW + 40, $rowY + 15, 7.5, number_format(((int) $value / (int) $items[$i - 1][1]) * 100, 0) . '% del paso anterior', $this->muted); }
    }

    private function pageHeader(PostulantSimplePdf $pdf, string $label, string $right): void
    {
        if ($this->logoPath !== '') {
            $pdf->image($this->logoPath, 36, 22, 32, 32);
            $pdf->text(76, 42, 10, $this->brandName, $this->muted, true);
        } else {
            $pdf->text(36, 42, 10, $this->brandName, $this->muted, true);
        }
        $pdf->text(559, 42, 8.5, $right, $this->muted, false, 'regular', 'right');
        $pdf->text(36, 64, 8.5, $label, $this->primary, true);
    }

    private function metricCards(PostulantSimplePdf $pdf, float $y, array $summary): void
    {
        $cards = [['Asignadas', 'assigned_people', $this->primary], ['Rindieron', 'attempted_people', [72, 117, 184]], ['Personas distintas', 'distinct_incident_people', $this->warning], ['Nivel ALTO', 'method_high', $this->danger]];
        $x = 36.0;
        foreach ($cards as [$label, $key, $color]) {
            $pdf->roundedBox($x, $y, 123, 78, $this->surface, [226, 230, 238], 0.8);
            $pdf->text($x + 13, $y + 22, 8.5, $label, $this->muted);
            $pdf->text($x + 13, $y + 51, 22, number_format((int) ($summary[$key] ?? 0), 0, ',', '.'), $color, true);
            $x += 133;
        }
    }

    private function distribution(PostulantSimplePdf $pdf, float $y, array $summary): void
    {
        $pdf->text(36, $y, 12, 'Distribución de casos', $this->primary, true);
        $total = max(1, (int) ($summary['distinct_incident_people'] ?? 0));
        foreach ([['Nivel ALTO', 'method_high', $this->danger], ['Nivel MEDIO', 'method_medium', $this->warning], ['Nivel BAJO', 'method_low', [72, 117, 184]]] as $index => [$label, $key, $color]) {
            $rowY = $y + 28 + ($index * 42); $value = (int) ($summary[$key] ?? 0); $pct = min(100, ($value / $total) * 100);
            $pdf->text(36, $rowY, 9.2, $label, $this->text, true); $pdf->text(559, $rowY, 9.2, number_format($value, 0, ',', '.') . '  (' . number_format($pct, 1, ',', '.') . '%)', $this->muted, false, 'regular', 'right');
            $pdf->roundedBox(36, $rowY + 9, 523, 10, [235, 238, 244], [235, 238, 244], 0.2); $pdf->roundedBox(36, $rowY + 9, max(5, 523 * $pct / 100), 10, $color, $color, 0.2);
        }
    }

    private function executiveText(array $summary): string
    {
        $high = (int) ($summary['method_high'] ?? 0); $medium = (int) ($summary['method_medium'] ?? 0); $low = (int) ($summary['method_low'] ?? 0); $attempted = (int) ($summary['attempted_people'] ?? 0);
        return 'Se analizaron ' . number_format((int) ($summary['assigned_people'] ?? 0), 0, ',', '.') . ' asignaciones y ' . number_format($attempted, 0, ',', '.') . ' personas rindieron la prueba. Las incidencias se consolidaron por persona: ' . number_format((int) ($summary['distinct_incident_people'] ?? 0), 0, ',', '.') . ' personas distintas. La clasificación metodológica identifica ' . number_format($high, 0, ',', '.') . ' casos ALTO, ' . number_format($medium, 0, ',', '.') . ' MEDIO y ' . number_format($low, 0, ',', '.') . ' BAJO.';
    }

    private function analysisSnapshot(): array
    {
        return [
            'universe' => ['assigned' => 769, 'attempted' => 691, 'incident_assignments' => 434, 'distinct_incident_people' => 339, 'activity' => 157250, 'media' => 56536],
            'levels' => ['high' => ['label' => 'ALTO', 'people' => 83, 'pct' => '24%', 'rule' => '3 o más señales conductuales distintas, voces múltiples con alta intensidad o score >=16'], 'medium' => ['label' => 'MEDIO', 'people' => 115, 'pct' => '34%', 'rule' => '2 señales conductuales distintas o voces múltiples con intensidad moderada'], 'low' => ['label' => 'BAJO', 'people' => 137, 'pct' => '40%', 'rule' => '1 señal conductual, baja intensidad y sin reincidencia'], 'technical' => ['label' => 'SOLO TÉCNICO', 'people' => 4, 'pct' => '1%', 'rule' => 'Sin señales conductuales; solo fallas de plataforma o equipo']],
            'signals' => [['Posibles voces múltiples', 274, 224, 'Conductual'], ['Pérdida de foco', 243, 207, 'Conductual'], ['Falla de captura audiovisual', 94, 83, 'Técnica'], ['Cambio de pestaña', 88, 78, 'Conductual'], ['Salida de pantalla completa', 59, 55, 'Conductual'], ['Falla de carga de pantalla', 37, 34, 'Técnica'], ['Falla de carga audiovisual', 37, 34, 'Técnica'], ['Falla de finalización', 10, 10, 'Técnica'], ['Falla de captura de pantalla', 9, 7, 'Técnica'], ['Evidencia parcial', 2, 2, 'Técnica'], ['Falla de carga de grabación', 2, 2, 'Técnica']],
            'concentration' => [['10% de las personas con más eventos', '58% del total'], ['25% de las personas con más eventos', '79% del total']],
            'cooccurrence' => [['', 'Voces múltiples', 'Pérdida de foco', 'Cambio de pestaña', 'Salida pant. completa'], ['Voces múltiples', '224', '107', '50', '30'], ['Pérdida de foco', '107', '207', '63', '46'], ['Cambio de pestaña', '50', '63', '78', '24'], ['Salida pant. completa', '30', '46', '24', '55']],
            'recurrence' => [['Pruebas con incidencia', 'Personas'], ['1', '251'], ['2', '81'], ['3', '7']],
        ];
    }

    private function analysisPages(PostulantSimplePdf $pdf, array $analysis, int $page): array
    {
        $pages = [];
        $pdf->addPage(); $this->pageHeader($pdf, 'UNIVERSO Y METODOLOGÍA', 'Página ' . $page);
        $pdf->text(36, 91, 18, 'Universo analizado y clasificación', $this->primary, true);
        $pdf->text(36, 114, 9.5, 'La unidad de análisis es la persona, no la asignación individual.', $this->muted);
        $universe = $analysis['universe']; $this->analysisTable($pdf, 143, ['ETAPA', 'CANTIDAD', 'CRITERIO'], [['Personas asignadas', number_format((int) $universe['assigned'], 0, ',', '.'), 'Asignaciones activas con proceso asignado'], ['Rindieron la prueba', number_format((int) $universe['attempted'], 0, ',', '.'), 'Personas con intento registrado'], ['Intentos con incidencia clasificada', number_format((int) $universe['incident_assignments'], 0, ',', '.'), 'Cuenta asignaciones con señales'], ['Personas distintas involucradas', number_format((int) $universe['distinct_incident_people'], 0, ',', '.'), 'Consolidación de personas; algunas rindieron más de una prueba'], ['Eventos de actividad', number_format((int) $universe['activity'], 0, ',', '.'), 'Eventos registrados en la plataforma'], ['Eventos audiovisuales', number_format((int) $universe['media'], 0, ',', '.'), 'Riesgos y fallas audiovisuales']], [26, 20, 54]);
        $pdf->roundedBox(36, 356, 523, 72, $this->light, $this->border, 0.8);
        $pdf->text(50, 379, 10.5, 'Corrección metodológica', $this->primary, true);
        $pdf->wrappedText(50, 398, 8.8, 'El valor 434 corresponde a asignaciones con incidencia, no a personas. Para dimensionar el trabajo del comité debe utilizarse el valor 339 de personas distintas involucradas.', 490, 11, $this->text);
        $pdf->text(36, 468, 13, 'Score y niveles por persona', $this->primary, true);
        $pdf->wrappedText(36, 489, 8.8, 'score = suma de pesos conductuales distintos + 2 x ln(1 + eventos registrados) + 1,5 x (pruebas con incidencia - 1). Las fallas técnicas se informan separadamente y no deben interpretarse como conducta.', 523, 11, $this->text);
        $this->analysisTable($pdf, 542, ['NIVEL', 'PERSONAS', '%', 'REGLA DE CLASIFICACIÓN'], [
            ['ALTO', (string) $analysis['levels']['high']['people'], $analysis['levels']['high']['pct'], $analysis['levels']['high']['rule']],
            ['MEDIO', (string) $analysis['levels']['medium']['people'], $analysis['levels']['medium']['pct'], $analysis['levels']['medium']['rule']],
            ['BAJO', (string) $analysis['levels']['low']['people'], $analysis['levels']['low']['pct'], $analysis['levels']['low']['rule']],
            ['SOLO TÉCNICO', (string) $analysis['levels']['technical']['people'], $analysis['levels']['technical']['pct'], $analysis['levels']['technical']['rule']],
        ], [22, 16, 12, 70]);
        $this->footer($pdf, $page); $pages[] = $page++;

        $pdf->addPage(); $this->pageHeader($pdf, 'HALLAZGOS PRINCIPALES', 'Página ' . $page);
        $pdf->text(36, 91, 18, 'Qué señales concentran la atención', $this->primary, true);
        $pdf->text(36, 114, 9.5, 'Las señales se interpretan por familia, intensidad y contexto.', $this->muted);
        $signalRows = [['SEÑAL', 'INTENTOS', 'PERSONAS', 'FAMILIA']]; foreach ($analysis['signals'] as $signal) { $label = $this->signalLabel((string) $signal[0]); if ($label === '') $label = (string) $signal[0]; $signal[0] = $label; $signal[3] = in_array($label, ['Posibles voces múltiples', 'Cambio de pestaña', 'Pérdida de foco', 'Salida de pantalla completa'], true) ? 'Conductual' : 'Técnica'; $signalRows[] = array_map('strval', $signal); }
        $this->analysisTable($pdf, 143, $signalRows[0], array_slice($signalRows, 1), [52, 16, 16, 22]);
        $pdf->roundedBox(36, 515, 523, 86, [255, 250, 243], [232, 211, 173], 0.8);
        $pdf->text(50, 540, 10.5, 'Lectura del hallazgo', $this->warning, true);
        $classifiedEvents = (int) ($analysis['totals']['classified_events'] ?? 0); $technicalEvents = (int) ($analysis['totals']['technical_events'] ?? 0); $topShare = $analysis['concentration'][1][1] ?? '0%';
        $pdf->wrappedText(50, 559, 8.8, 'Las señales más frecuentes deben leerse en contexto porque son ambiguas. ' . $technicalEvents . ' de ' . $classifiedEvents . ' señales clasificadas son técnicas y deben derivarse a soporte. El 25% de las personas concentra ' . $topShare . ' del volumen de eventos.', 490, 11, $this->text);
        $pdf->text(36, 632, 13, 'Concentración de la evidencia', $this->primary, true);
        $this->analysisTable($pdf, 650, ['TRAMO', 'CONCENTRACIÓN'], $analysis['concentration'], [60, 28]);
        $this->footer($pdf, $page); $pages[] = $page++;

        $pdf->addPage(); $this->pageHeader($pdf, '3. ANÁLISIS DE LAS INCIDENCIAS', 'Página ' . $page);
        $pdf->text(36, 91, 18, 'Incidencias por tipo', $this->primary, true); $pdf->wrappedText(36, 115, 9.2, 'El gráfico muestra cuántos intentos fueron afectados por cada tipo de señal y cuántas personas distintas representa. Naranja identifica señales conductuales; azul, fallas técnicas o de evidencia.', 523, 12, $this->text);
        $chartSignals = array_slice((array) ($analysis['signals'] ?? []), 0, 11); $maxSignal = max(1, max(array_map(static fn(array $signal): int => (int) ($signal[1] ?? 0), $chartSignals ?: [['', 1]]))); $y = 176.0; foreach ($chartSignals as $signal) { $label = $this->signalLabel((string) $signal[0]); if ($label === '') $label = (string) $signal[0]; $isTechnical = !in_array($label, ['Posibles voces múltiples', 'Pérdida de foco', 'Cambio de pestaña', 'Salida de pantalla completa'], true); $pdf->text(170, $y + 10, 7.7, $label, $this->muted, false, 'regular', 'right'); $barW = 300 * ((int) $signal[1] / $maxSignal); $pdf->fillRect(180, $y, max(2, $barW), 18, $isTechnical ? [19, 121, 196] : [244, 92, 32]); $pdf->text(186 + $barW, $y + 12, 8, (string) $signal[1] . ' (' . (string) $signal[2] . ' pers.)', $this->text); $y += 32; }
        $pdf->fillRect(370, 550, 10, 10, [244, 92, 32]); $pdf->text(387, 559, 7.5, 'Señal conductual', $this->text); $pdf->fillRect(470, 550, 10, 10, [19, 121, 196]); $pdf->text(487, 559, 7.5, 'Falla técnica', $this->text); $pdf->text(36, 595, 8, 'Figura 3. Incidencias por tipo e intentos afectados.', $this->muted, false, 'italic');
        $pdf->text(36, 620, 13, 'La carga de evidencia está muy concentrada', $this->primary, true); $this->concentrationCurve($pdf, 645, $analysis); $this->footer($pdf, $page); $pages[] = $page++;

        $pdf->addPage(); $this->pageHeader($pdf, 'HALLAZGOS PRINCIPALES', 'Página ' . $page);
        $pdf->text(36, 91, 18, 'Coocurrencia y reincidencia', $this->primary, true);
        $pdf->text(36, 114, 9.5, 'La acumulación de señales y la repetición entre pruebas elevan la prioridad.', $this->muted);
        $cooccurrence = $analysis['cooccurrence'] ?: $this->analysisSnapshot()['cooccurrence']; $this->heatmap($pdf, 143, $cooccurrence);
        $pdf->text(36, 470, 13, 'Señales conductuales distintas por persona', $this->primary, true);
        $distribution = (array) ($analysis['conduct_distribution'] ?? []); if ($distribution) $distribution[0][0] = '0 (solo técnico)'; $this->analysisTable($pdf, 490, ['SEÑALES DISTINTAS', 'PERSONAS'], $distribution ?: [['0 (solo técnico)', '4'], ['1', '180'], ['2', '98'], ['3', '40'], ['4', '17']], [55, 20]);
        $pdf->roundedBox(36, 645, 523, 52, $this->light, $this->border, 0.8);
        $fourSignals = (int) (($distribution[4][1] ?? 17)); $pdf->wrappedText(50, 669, 9, 'Las ' . $fourSignals . ' personas con las cuatro señales forman el núcleo duro de la revisión y deben priorizarse dentro del nivel ALTO.', 490, 12, $this->text);
        $pdf->text(36, 716, 13, 'Reincidencia entre pruebas', $this->primary, true);
        $recurrence = (array) ($analysis['recurrence'] ?? []); $recurrenceText = []; foreach (array_slice($recurrence, 1) as $item) $recurrenceText[] = $item[0] . ' prueba(s): ' . $item[1] . ' personas'; $pdf->wrappedText(36, 736, 8.8, implode('. ', $recurrenceText) . '. La repetición entre pruebas y contextos eleva la prioridad de revisión.', 523, 11, $this->text);
        $this->footer($pdf, $page); $pages[] = $page++;

        $pdf->addPage(); $this->pageHeader($pdf, '2. CRITERIO DE CLASIFICACIÓN', 'Página ' . $page);
        $pdf->text(36, 91, 18, 'Conducta y evidencia no son lo mismo', $this->primary, true);
        $pdf->wrappedText(36, 115, 9.2, 'La tipificación separa señales conductuales de fallas técnicas. Una falla de captura o carga significa evidencia incompleta; no debe interpretarse como una conducta de la persona.', 523, 12, $this->text);
        $this->analysisTable($pdf, 153, ['SEÑAL', 'FAMILIA', 'PESO', 'LECTURA'], [
            ['Posibles voces múltiples', 'Conductual', '3', 'Alta sensibilidad: validar contexto, terceros y lectura en voz alta.'],
            ['Salida de pantalla completa', 'Conductual', '2', 'Verificar si fue única al inicio o repetida durante la prueba.'],
            ['Cambio de pestaña', 'Conductual', '2', 'Su valor depende de duración, momento y recurrencia.'],
            ['Pérdida de foco', 'Conductual', '1', 'Señal frecuente y ambigua; puede originarse en notificaciones.'],
            ['Fallas de captura, carga o finalización', 'Técnica', '-', 'Derivar a soporte y revisar condiciones del equipo.'],
        ], [30, 16, 10, 44]);
        $pdf->text(36, 430, 13, 'Regla de segmentación por persona', $this->primary, true);
        $pdf->wrappedText(36, 452, 9, 'score = suma de pesos conductuales distintos + 2 x ln(1 + eventos registrados) + 1,5 x (pruebas con incidencia - 1). Las fallas técnicas se informan separadamente y no deben interpretarse como conducta.', 523, 12, $this->text);
        $this->analysisTable($pdf, 520, ['NIVEL', 'REGLA', 'PERSONAS', '%'], [
            ['ALTO', $analysis['levels']['high']['rule'], (string) $analysis['levels']['high']['people'], $analysis['levels']['high']['pct']],
            ['MEDIO', $analysis['levels']['medium']['rule'], (string) $analysis['levels']['medium']['people'], $analysis['levels']['medium']['pct']],
            ['BAJO', $analysis['levels']['low']['rule'], (string) $analysis['levels']['low']['people'], $analysis['levels']['low']['pct']],
            ['SOLO TÉCNICO', $analysis['levels']['technical']['rule'], (string) $analysis['levels']['technical']['people'], $analysis['levels']['technical']['pct']],
        ], [20, 62, 10, 8]);
        $this->footer($pdf, $page); $pages[] = $page++;

        $pdf->addPage(); $this->pageHeader($pdf, '3. ANÁLISIS DE LAS INCIDENCIAS', 'Página ' . $page);
        $pdf->text(36, 91, 18, 'Composición del riesgo por proceso', $this->primary, true);
        $pdf->wrappedText(36, 115, 9.2, 'Comparar procesos en términos absolutos favorece a los más masivos. El gráfico ordena por proporción de casos ALTO dentro de cada proceso y permite identificar dónde debe concentrarse la revisión.', 523, 12, $this->text);
        $processes = array_map(static fn(array $process): array => [$process['name'], $process['people'], $process['high'], $process['medium'], $process['low']], (array) ($analysis['processes'] ?? []));
        $this->stackedProcessChart($pdf, 160, $processes);
        $pdf->text(36, 650, 13, 'Lectura para el cliente', $this->primary, true);
        $topProcesses = array_slice((array) ($analysis['processes'] ?? []), 0, 2);
        $topProcessText = implode(' y ', array_map(static fn(array $process): string => (string) ($process['name'] ?? 'Proceso'), $topProcesses));
        $pdf->wrappedText(36, 672, 9, ($topProcessText !== '' ? 'Los procesos ' . $topProcessText . ' concentran la mayor proporción relativa de casos ALTO. ' : '') . 'La comparación debe considerar el tamaño de cada proceso y las personas involucradas. Antes de atribuir una diferencia a conducta, debe descartarse una explicación asociada a instrucciones, horario, dispositivo o conectividad.', 523, 12, $this->text);
        $this->footer($pdf, $page); $pages[] = $page++;

        $pdf->addPage(); $this->pageHeader($pdf, '3. ANÁLISIS DE LAS INCIDENCIAS', 'Página ' . $page);
        $pdf->text(36, 91, 18, 'Resumen numérico por proceso', $this->primary, true);
        $pdf->wrappedText(36, 115, 9.2, 'La tabla complementa el gráfico y muestra las personas involucradas y su distribución de prioridad en cada proceso.', 523, 12, $this->text);
        $processRows = []; foreach ((array) ($analysis['processes'] ?? []) as $process) $processRows[] = [$process['name'], (string) $process['people'], (string) $process['high'], (string) $process['medium'], (string) $process['low']];
        $this->analysisTable($pdf, 155, ['PROCESO', 'PERSONAS', 'ALTO', 'MEDIO', 'BAJO / TÉCNICO'], $processRows, [54, 14, 10, 10, 22]);
        $this->footer($pdf, $page); $pages[] = $page++;

        $pdf->addPage(); $this->pageHeader($pdf, '5. PROTOCOLO DE REVISIÓN', 'Página ' . $page);
        $pdf->text(36, 91, 18, 'Cómo debe revisarse cada caso', $this->primary, true);
        $pdf->text(36, 114, 9.5, 'El informe orienta la revisión; la decisión requiere evidencia y revisión humana.', $this->muted);
        $this->analysisTable($pdf, 143, ['NIVEL', 'QUÉ REVISAR', 'RESULTADO'], [['ALTO', 'Grabación completa, registro de pantalla y línea de tiempo. Contrastar audio con el momento exacto.', 'Dos revisores independientes y ficha por caso.'], ['MEDIO', 'Solo tramos marcados y +/- 2 minutos de contexto.', 'Confirmación, descarte o escalamiento.'], ['BAJO', 'No abrir evidencia salvo criterio excepcional.', 'Registro en expediente.'], ['SOLO TÉCNICO', 'Estado del intento y condiciones del equipo.', 'Derivación a soporte o nueva rendición.']], [18, 54, 42]);
        $pdf->text(36, 438, 13, 'Limitaciones que deben acompañar la lectura', $this->primary, true);
        $pdf->wrappedText(36, 460, 8.8, 'Voces múltiples no equivale a asistencia. La pérdida de foco depende del entorno. La evidencia incompleta no es evidencia en contra. No existe todavía una línea base histórica. El sistema no verifica identidad de forma continua.', 523, 12, $this->text);
        $pdf->text(36, 572, 13, 'Reglas mínimas de revisión', $this->primary, true);
        $pdf->wrappedText(36, 594, 8.8, 'Ninguna decisión adversa debe fundarse en una señal automática sin verificación documentada. Toda persona revisada debe poder conocer el hallazgo y presentar descargo. El criterio debe aplicarse igual a todos los procesos y debe registrarse qué se revisó, quién lo hizo y cuándo.', 523, 12, $this->text);
        $this->footer($pdf, $page); $pages[] = $page++;

        $pdf->addPage(); $this->pageHeader($pdf, '6. LIMITACIONES DEL ANÁLISIS', 'Página ' . $page);
        $pdf->text(36, 91, 18, 'Limitaciones que deben acompañar la lectura', $this->primary, true);
        $limitations = [
            'Voces múltiples no equivale a asistencia: puede corresponder a TV, familia, lector de pantalla o lectura en voz alta.',
            'La pérdida de foco depende del entorno del equipo y no contiene duración del evento.',
            'La evidencia incompleta no es evidencia en contra; puede introducir sesgo por conectividad o equipo.',
            'No existe todavía una línea base histórica; las comparaciones son internas entre procesos.',
            'El sistema no verifica identidad de forma continua. Las señales describen el entorno de rendición.',
        ];
        $y = 132.0; foreach ($limitations as $item) { $pdf->text(42, $y, 9, '-', $this->text, true); $pdf->wrappedText(56, $y, 8.9, $item, 497, 12, $this->text); $y += 42; }
        $pdf->text(36, 430, 13, 'Recomendaciones para este proceso', $this->primary, true);
        $technicalPeople = (int) ($analysis['levels']['technical']['people'] ?? 0);
        $recommendations = [
            'Revisar primero las ' . (int) ($analysis['levels']['high']['people'] ?? 0) . ' personas ALTO y, dentro de ellas, las ' . (int) (($analysis['conduct_distribution'][4][1] ?? 0)) . ' con las cuatro señales.',
            'Sacar del análisis de conducta las ' . $technicalPeople . ' personas SOLO TÉCNICO y derivarlas a soporte.',
            'Usar como unidad de gestión las ' . (int) ($analysis['universe']['distinct_incident_people'] ?? 0) . ' personas distintas; las ' . (int) ($analysis['universe']['incident_assignments'] ?? 0) . ' asignaciones con incidencia representan intentos, no personas.',
            'Comparar los procesos por proporción de niveles y no solo por volumen absoluto antes de atribuir diferencias a conducta.',
        ];
        $y = 468.0; foreach ($recommendations as $item) { $pdf->text(42, $y, 9, '-', $this->text, true); $pdf->wrappedText(56, $y, 8.9, $item, 497, 12, $this->text); $y += 39; }
        $this->footer($pdf, $page); $pages[] = $page++;

        $pdf->addPage(); $this->pageHeader($pdf, '7. RECOMENDACIONES / 8. PREGUNTAS', 'Página ' . $page);
        $pdf->text(36, 91, 18, 'Recomendaciones sobre el producto', $this->primary, true);
        $productRecommendations = [
            'Registrar la duración de cada evento, no solo su ocurrencia.',
            'Separar en la plataforma señales de conducta y fallas de evidencia.',
            'Mantener RUT, ID de intento y timestamp en todas las salidas del reporte de incidencias.',
            'Publicar la tasa de falso positivo por tipo de señal después de la revisión humana.',
            'Agregar una prueba de entorno previa para audio, cámara y ancho de banda.',
            'Definir una línea base por tipo de proceso y entregar el protocolo junto con el informe.',
        ];
        $y = 130.0; foreach ($productRecommendations as $item) { $pdf->text(42, $y, 9, '-', $this->text, true); $pdf->wrappedText(56, $y, 8.8, $item, 497, 12, $this->text); $y += 39; }
        $pdf->text(36, 395, 13, 'Preguntas para la reunión con el cliente', $this->primary, true);
        $questions = [
            '¿Qué evidencia mínima debe existir para confirmar o descartar un caso ALTO?',
            '¿Quiénes integrarán el comité y quién será responsable de documentar los descartes?',
            '¿Qué plazo de revisión y mecanismo de descargo se comunicará a las personas?',
            '¿Los procesos C y D de Jefatura ICT-IPT tuvieron las mismas instrucciones, horario y condiciones técnicas?',
            '¿Qué identificadores deben agregarse al próximo export para cruzar la información con la nómina?',
        ];
        $y = 438.0; foreach ($questions as $item) { $pdf->text(42, $y, 9, '-', $this->text, true); $pdf->wrappedText(56, $y, 8.8, $item, 497, 12, $this->text); $y += 42; }
        $this->footer($pdf, $page); $pages[] = $page++;
        return $pages;
    }

    private function stackedProcessChart(PostulantSimplePdf $pdf, float $y, array $processes): void
    {
        $barX = 190.0; $barW = 300.0; $max = 100.0;
        foreach (array_slice($processes, 0, 11) as $index => [$name, $total, $high, $medium, $low]) {
            $rowY = $y + ($index * 36); $chartName = preg_replace('/^P\s*-\s*/', '', (string) $name) ?: (string) $name; if (function_exists('mb_strlen') && mb_strlen($chartName) > 38) $chartName = mb_substr($chartName, 0, 35) . '...'; elseif (strlen($chartName) > 38) $chartName = substr($chartName, 0, 35) . '...'; $pdf->text(36, $rowY + 12, 6.8, $chartName, $this->muted);
            $x = $barX; foreach ([[$high, $this->danger], [$medium, [245, 171, 24]], [$low, [15, 161, 25]]] as [$value, $color]) { $w = $barW * ((int) $value / max(1, (int) $total)); $pdf->fillRect($x, $rowY, $w, 18, $color); if ($w > 22) $pdf->text($x + $w / 2, $rowY + 12, 7, (string) $value, [255, 255, 255], true, 'regular', 'center'); $x += $w; }
            $pdf->text(502, $rowY + 12, 7.5, 'n=' . $total, $this->muted);
        }
        $pdf->fillRect(260, 578, 10, 10, $this->danger); $pdf->text(276, 587, 7.5, 'ALTO', $this->text); $pdf->fillRect(330, 578, 10, 10, [245, 171, 24]); $pdf->text(346, 587, 7.5, 'MEDIO', $this->text); $pdf->fillRect(400, 578, 10, 10, [15, 161, 25]); $pdf->text(416, 587, 7.5, 'BAJO / técnico', $this->text); if (count($processes) > 11) $pdf->text(36, 610, 7.5, 'Nota: los procesos adicionales se detallan en la tabla de la página siguiente.', $this->muted);
    }

    private function concentrationCurve(PostulantSimplePdf $pdf, float $y, array $analysis): void
    {
        $distribution = array_values(array_filter(array_map('intval', (array) ($analysis['event_distribution'] ?? [])), static fn(int $value): bool => $value > 0));
        rsort($distribution); $total = max(1, array_sum($distribution)); $w = 380.0; $h = 110.0; $x = 105.0;
        $pdf->line($x, $y + $h, $x + $w, $y + $h, $this->border, 0.8); $pdf->line($x, $y, $x, $y + $h, $this->border, 0.8);
        $previousX = $x; $previousY = $y + $h; $running = 0; $count = max(1, count($distribution));
        foreach ($distribution as $index => $events) { $running += $events; $pointX = $x + $w * (($index + 1) / $count); $pointY = $y + $h - ($h * $running / $total); $pdf->line($previousX, $previousY, $pointX, $pointY, [19, 121, 196], 2.2); $previousX = $pointX; $previousY = $pointY; }
        $pdf->line($x, $y + $h, $x + $w, $y, [130, 130, 130], 0.8); $pdf->text($x + 180, $y + $h + 8, 7.5, '% personas ordenadas de mayor a menor', $this->muted, false, 'regular', 'center'); $pdf->text($x - 32, $y + 45, 7.5, '% eventos', $this->muted, false, 'regular', 'center'); $pdf->text($x + 55, $y + 30, 8, '10%: ' . ($analysis['concentration'][0][1] ?? '0%'), $this->text); $pdf->text($x + 120, $y + 55, 8, '25%: ' . ($analysis['concentration'][1][1] ?? '0%'), $this->text); $pdf->text($x + 180, $y + $h + 24, 8, 'Figura 4. Curva de concentración de eventos por persona.', $this->muted, false, 'italic', 'center');
    }

    private function heatmap(PostulantSimplePdf $pdf, float $y, array $matrix): void
    {
        $labels = ['Voces múltiples', 'Pérdida de foco', 'Cambio de pestaña', 'Salida pant. completa']; $shortLabels = ['Voces\nmúltiples', 'Pérdida\nde foco', 'Cambio de\npestaña', 'Salida de\npantalla']; $x0 = 200.0; $cell = 55.0; $pdf->text($x0 + 110, $y - 12, 10, 'Coocurrencia de señales conductuales', $this->text, true, 'regular', 'center'); foreach ($labels as $i => $label) { $pdf->wrappedText($x0 + ($i * $cell) + 2, $y + 2, 6.0, $shortLabels[$i], $cell - 6, 7, $this->muted, false, 'center'); $pdf->text(190, $y + 55 + ($i * $cell), 6.2, $label, $this->muted, false, 'regular', 'right'); }
        for ($r = 0; $r < 4; $r++) for ($c = 0; $c < 4; $c++) { $value = (int) ($matrix[$r + 1][$c + 1] ?? 0); $shade = min(235, 235 - (int) min(190, $value)); $color = [$shade, min(220, $shade + 20), 255]; $pdf->fillRect($x0 + $c * $cell, $y + 25 + $r * $cell, $cell - 2, $cell - 2, $color); $pdf->text($x0 + $c * $cell + $cell / 2, $y + 59 + $r * $cell, 10, (string) $value, $value > 100 ? [255, 255, 255] : $this->text, true, 'regular', 'center'); }
        $pdf->text($x0 + 110, $y + 255, 8, 'Figura 5. Personas con ambas señales; la diagonal indica el total con cada señal.', $this->muted, false, 'italic', 'center');
    }

    private function analysisTable(PostulantSimplePdf $pdf, float $y, array $headers, array $rows, array $widths): void
    {
        $x = 36.0;
        $scale = 523 / max(1, array_sum($widths));
        $widths = array_map(static fn($width): float => (float) $width * $scale, $widths);
        $total = 523.0;
        $headerHeight = 30.0;
        $pdf->fillRect($x, $y, $total, $headerHeight, $this->primary);
        foreach ($headers as $index => $header) { $pdf->wrappedText($x + 5, $y + 13, 7.3, (string) $header, $widths[$index] - 10, 8, [255, 255, 255], true); $x += $widths[$index]; }
        $rowY = $y + $headerHeight;
        foreach ($rows as $rowIndex => $row) { $height = 23; foreach ($row as $index => $value) { $height = max($height, min(60, 10 + (substr_count(wordwrap((string) $value, max(12, (int) ($widths[$index] / 4)), "\n"), "\n") + 1) * 9)); } $x = 36.0; $pdf->fillRect($x, $rowY, $total, $height, $rowIndex % 2 === 0 ? [250, 251, 252] : [244, 247, 248]); $pdf->line($x, $rowY + $height, $x + $total, $rowY + $height, [255, 255, 255], 0.8); foreach ($row as $index => $value) { $pdf->wrappedText($x + 5, $rowY + 14, 7.5, (string) $value, $widths[$index] - 10, 9, $this->text); $x += $widths[$index]; } $rowY += $height; }
    }

    private function personSegments(array $rows, array $personLevels = []): array
    {
        $people = [];
        $conductual = ['multiple_voice_possible', 'tab_hidden', 'window_blurred', 'fullscreen_exited', 'fullscreen_denied', 'fullscreen_failed', 'suspicious_key_printscreen', 'suspicious_key_print', 'suspicious_key_copy', 'suspicious_key_save', 'suspicious_key_devtools'];
        foreach ($rows as $row) {
            $key = (string) ($row['user_id'] ?? $row['user_name'] ?? uniqid('person_', true));
            if (!isset($people[$key])) $people[$key] = ['user_name' => $this->clean((string) ($row['user_name'] ?? 'Persona')), 'process_name' => '', 'form_title' => '', 'incident_total' => 0, 'attempts' => 0, 'signals' => [], 'conductual' => [], 'person_level' => (string) ($personLevels[$key] ?? 'technical')];
            $people[$key]['incident_total'] += (int) ($row['incident_total'] ?? 0);
            $people[$key]['attempts']++;
            foreach ((array) ($row['signal_types'] ?? []) as $signal) { $signal = (string) $signal; $people[$key]['signals'][$signal] = ($people[$key]['signals'][$signal] ?? 0) + 1; if (in_array($signal, $conductual, true)) $people[$key]['conductual'][$signal] = true; }
            if ((int) ($row['incident_total'] ?? 0) >= (int) ($people[$key]['max_row_total'] ?? -1)) { $people[$key]['max_row_total'] = (int) ($row['incident_total'] ?? 0); $people[$key]['process_name'] = $this->clean((string) ($row['process_name'] ?? 'Proceso')); $people[$key]['form_title'] = $this->clean((string) ($row['form_title'] ?? 'Evaluación')); }
        }
        $segments = ['ALTO' => [], 'MEDIO' => [], 'BAJO' => [], 'SOLO TÉCNICO' => []];
        $technical = []; $behavioral = [];
        foreach ($people as $person) {
            $conductCount = count($person['conductual']);
            $labels = []; foreach (array_keys($person['signals']) as $signal) { $label = $this->signalLabel($signal); if ($label !== '') $labels[] = $label; }
            $person['signal_text'] = implode(' · ', array_values(array_unique($labels)));
            $person['classification_score'] = ($conductCount * 10) + min(20, (int) $person['incident_total']);
            if ($person['person_level'] === 'technical') $technical[] = $person; else $behavioral[] = $person;
        }
        usort($technical, static fn(array $a, array $b): int => ((int) $b['incident_total'] <=> (int) $a['incident_total']) ?: strcmp($a['user_name'], $b['user_name']));
        usort($behavioral, static fn(array $a, array $b): int => ((int) $b['classification_score'] <=> (int) $a['classification_score']) ?: ((int) $b['incident_total'] <=> (int) $a['incident_total']));
        foreach (array_merge($technical, $behavioral) as $person) {
            $segment = $person['person_level'] === 'high' ? 'ALTO' : ($person['person_level'] === 'medium' ? 'MEDIO' : ($person['person_level'] === 'low' ? 'BAJO' : 'SOLO TÉCNICO'));
            $segments[$segment][] = $person;
        }
        foreach ($segments as &$segment) foreach ($segment as &$person) { unset($person['signals'], $person['conductual'], $person['max_row_total'], $person['classification_score'], $person['person_level']); }
        unset($segment);
        return $segments;
    }

    private function segmentTable(PostulantSimplePdf $pdf, float $y, array $rows, string $level): void
    {
        $headers = ['PERSONA', 'PROCESO', 'PRUEBAS', 'SEÑALES', 'PATRÓN CONDUCTUAL'];
        $widths = [38, 26, 9, 9, 48]; $scale = 523 / array_sum($widths); $widths = array_map(static fn($width): float => $width * $scale, $widths); $x = 36.0; $pdf->fillRect($x, $y, 523, 30, $this->primary);
        foreach ($headers as $index => $header) { $pdf->wrappedText($x + 5, $y + 13, 6.6, $header, $widths[$index] - 10, 8, [255, 255, 255], true); $x += $widths[$index]; }
        $levelColor = $level === 'ALTO' ? $this->danger : ($level === 'MEDIO' ? $this->warning : ($level === 'BAJO' ? $this->primary : [41, 121, 196]));
        $rowY = $y + 30.0; foreach ($rows as $rowIndex => $row) { $values = [$row['user_name'], $row['process_name'], (string) $row['attempts'], (string) $row['incident_total'], $row['signal_text']]; $height = 60.0; $x = 36.0; $pdf->fillRect($x, $rowY, 523, $height, $rowIndex % 2 === 0 ? [250, 251, 252] : [244, 247, 248]); $pdf->fillRect($x, $rowY, 4, $height, $levelColor); $pdf->line($x, $rowY + $height, $x + 523, $rowY + $height, [255, 255, 255], 0.8); foreach ($values as $index => $value) { $pdf->wrappedText($x + 8, $rowY + 13, 6.4, (string) $value, $widths[$index] - 13, 8, $this->text); $x += $widths[$index]; } $rowY += $height; }
        $pdf->wrappedText(36, 755, 8.2, 'Poner atención en: ' . ($level === 'ALTO' ? 'confirmar las señales combinadas en grabación, pantalla y línea de tiempo.' : ($level === 'MEDIO' ? 'validar el contexto de las señales y escalar solo si se confirma un patrón.' : ($level === 'BAJO' ? 'dejar registro de la señal sin abrir evidencia salvo excepción.' : 'revisar condiciones técnicas y resolver la evidencia incompleta.'))), 523, 10, $level === 'ALTO' ? $this->danger : $this->text, true);
    }

    private function groupRows(array $rows): array
    {
        $groups = [];
        foreach ($rows as $row) {
            $key = (string) ($row['process_id'] ?? '') . ':' . (string) ($row['form_id'] ?? '');
            if (!isset($groups[$key])) {
                $groups[$key] = ['form_title' => $this->clean((string) ($row['form_title'] ?? 'Evaluación')), 'process_name' => $this->clean((string) ($row['process_name'] ?? 'Proceso')), 'people' => 0, 'high' => 0, 'review' => 0, 'incidents' => 0, 'types' => []];
            }
            $groups[$key]['people']++;
            $groups[$key]['high'] += ($row['alert_level'] ?? '') === 'high' ? 1 : 0;
            $groups[$key]['review'] += ($row['alert_level'] ?? '') === 'review' ? 1 : 0;
            $groups[$key]['incidents'] += (int) ($row['incident_total'] ?? 0);
            foreach ((array) ($row['signal_types'] ?? []) as $type) {
                $label = $this->signalLabel((string) $type);
                if ($label !== '') $groups[$key]['types'][$label] = ($groups[$key]['types'][$label] ?? 0) + 1;
            }
        }
        foreach ($groups as &$group) {
            arsort($group['types']);
            $group['types'] = implode(', ', array_map(static fn(string $label, int $count): string => $label . ' (' . $count . ')', array_keys($group['types']), $group['types']));
            if ($group['types'] === '') $group['types'] = 'Sin tipificación';
        }
        unset($group);
        return array_values($groups);
    }

    private function signalLabels(array $types): string
    {
        $labels = [];
        foreach ($types as $type) {
            $label = $this->signalLabel((string) $type);
            if ($label !== '') $labels[] = $label;
        }
        return implode(', ', array_values(array_unique($labels)));
    }

    private function signalLabel(string $type): string
    {
        $labels = ['tab_hidden' => 'Cambio de pestaña', 'window_blurred' => 'Pérdida de foco', 'fullscreen_exited' => 'Salida de pantalla completa', 'fullscreen_denied' => 'Pantalla completa denegada', 'fullscreen_failed' => 'Falla de pantalla completa', 'suspicious_key_printscreen' => 'Captura de pantalla', 'suspicious_key_print' => 'Intento de impresión', 'suspicious_key_copy' => 'Intento de copia', 'suspicious_key_save' => 'Intento de guardado', 'suspicious_key_devtools' => 'Herramientas de desarrollo', 'multiple_voice_possible' => 'Posibles voces múltiples', 'audio_visual_risk' => 'Riesgo audiovisual', 'audio_visual_capture_failed' => 'Falla de captura audiovisual', 'audio_visual_upload_failed' => 'Falla de carga audiovisual', 'audio_visual_screen_failed' => 'Falla de captura de pantalla', 'audio_visual_recording_interrupted' => 'Interrupción de grabación', 'screen_capture_upload_failed' => 'Falla de carga de pantalla', 'recording_upload_failed' => 'Falla de carga de grabación', 'finalize_failed' => 'Falla de finalización'];
        $labels += ['fullscreen_unavailable' => 'Pantalla completa no disponible', 'inactive_detected' => 'Inactividad detectada', 'copy_blocked' => 'Copia bloqueada', 'cut_blocked' => 'Corte bloqueado', 'paste_blocked' => 'Pegado bloqueado', 'print_blocked' => 'Impresión bloqueada', 'context_menu_blocked' => 'Menú contextual bloqueado', 'drag_blocked' => 'Arrastre bloqueado'];
        if (substr($type, 0, 9) === 'evidence_') return 'Evidencia ' . str_replace('_', ' ', substr($type, 9));
        return $labels[$type] ?? '';
    }

    private function reason(array $row): string
    {
        $isHigh = ($row['alert_level'] ?? '') === 'high';
        $signals = $this->signalLabels((array) ($row['signal_types'] ?? []));
        return $isHigh ? 'presenta señales clasificadas como riesgo: ' . $signals : 'presenta señales aisladas o técnicas que requieren validación: ' . $signals;
    }

    private function brandLogoPath(array $design, array $login): string
    {
        foreach ([(string) ($design['topbar_icon_path'] ?? ''), (string) ($design['html_favicon_path'] ?? ''), (string) ($login['login_logo_path'] ?? '')] as $path) {
            $resolved = $this->publicPath($path);
            if ($resolved !== '' && is_file($resolved) && strtolower(pathinfo($resolved, PATHINFO_EXTENSION)) === 'png') return $resolved;
        }
        return '';
    }

    private function publicPath(string $path): string
    {
        $path = trim($path);
        if ($path === '') return '';
        if (substr($path, 0, 1) === '/') return $path;
        $base = defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__, 3);
        $relative = ltrim($path, '/');
        return $base . '/' . (substr($relative, 0, 7) === 'public/' ? $relative : 'public/' . $relative);
    }

    private function clean(string $value): string { return preg_replace('/\p{Mn}+/u', '', $value) ?? $value; }
    private function footer(PostulantSimplePdf $pdf, int $page): void { $pdf->line(36, 790, 559, 790, $this->border, 0.7); $pdf->text(36, 808, 8, $this->brandName . ' | Reporte ejecutivo de incidencias', $this->muted); $pdf->text(559, 808, 8, 'Página ' . $page, $this->muted, false, 'regular', 'right'); }
    private function hexToRgb(string $hex, array $fallback): array { $hex = ltrim(trim($hex), '#'); if (strlen($hex) === 3) $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2]; return preg_match('/^[0-9a-fA-F]{6}$/', $hex) ? [hexdec(substr($hex,0,2)),hexdec(substr($hex,2,2)),hexdec(substr($hex,4,2))] : $fallback; }
    private function mix(array $a, array $b, float $ratio): array { return [(int) round($a[0]*(1-$ratio)+$b[0]*$ratio),(int) round($a[1]*(1-$ratio)+$b[1]*$ratio),(int) round($a[2]*(1-$ratio)+$b[2]*$ratio)]; }
}
