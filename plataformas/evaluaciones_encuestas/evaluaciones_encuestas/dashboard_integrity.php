<?php
$summary = is_array($summary ?? null) ? $summary : [];
$rows = is_array($rows ?? null) ? $rows : [];
$filterOptions = is_array($filterOptions ?? null) ? $filterOptions : ['forms' => [], 'tests' => [], 'processes' => []];
$selectedFormIds = array_values(array_filter(array_map('intval', (array) ($formIds ?? [])), static fn(int $id): bool => $id > 0));
$selectedProcessIds = array_values(array_filter(array_map('intval', (array) ($processIds ?? [])), static fn(int $id): bool => $id > 0));
$selectedTestIds = array_values(array_filter(array_map('intval', (array) ($testIds ?? [])), static fn(int $id): bool => $id > 0));
$reportDomain = (string) ($reportDomain ?? 'both');
$filterQuery = http_build_query(array_filter(['domain' => $reportDomain, 'date_from' => $dateFrom ?? '', 'date_to' => $dateTo ?? '', 'process_id' => $selectedProcessIds, 'form_id' => $selectedFormIds, 'test_id' => $selectedTestIds]));
$number = static fn($value): string => number_format((float) $value, 0, ',', '.');
$signalLabels = [
    'tab_hidden' => 'Cambio de pestaña', 'window_blurred' => 'Pérdida de foco', 'fullscreen_exited' => 'Salida de pantalla completa',
    'fullscreen_denied' => 'Pantalla completa denegada', 'fullscreen_failed' => 'Pantalla completa fallida', 'suspicious_key_printscreen' => 'Captura de pantalla',
    'suspicious_key_print' => 'Intento de impresión', 'suspicious_key_copy' => 'Intento de copia', 'suspicious_key_save' => 'Intento de guardado',
    'suspicious_key_devtools' => 'Herramientas de desarrollo', 'fullscreen_unavailable' => 'Pantalla completa no disponible', 'inactive_detected' => 'Inactividad detectada',
    'copy_blocked' => 'Copia bloqueada', 'cut_blocked' => 'Corte bloqueado', 'paste_blocked' => 'Pegado bloqueado', 'print_blocked' => 'Impresión bloqueada',
    'context_menu_blocked' => 'Menú contextual bloqueado', 'drag_blocked' => 'Arrastre bloqueado', 'audio_visual_upload_failed' => 'Falla de carga audiovisual',
    'multiple_voice_possible' => 'Posibles voces múltiples', 'audio_visual_risk' => 'Riesgo audiovisual', 'audio_visual_recording_interrupted' => 'Interrupción de grabación',
];
$level = static function (string $value): array {
    return $value === 'high' ? ['Alerta alta', 'danger', 'bi-shield-exclamation'] : ($value === 'review' ? ['En revisión', 'warning', 'bi-eye'] : ['Sin alerta', 'success', 'bi-shield-check']);
};
$cases = array_values(array_filter($rows, static fn(array $row): bool => ($row['alert_level'] ?? 'none') !== 'none'));
$showAllCases = false;
$priorityPerPage = 25;
$priorityPage = max(1, (int) ($_GET['priority_page'] ?? 1));
$priorityPageUrl = static function (int $page) use ($reportDomain, $dateFrom, $dateTo, $selectedProcessIds, $selectedFormIds, $selectedTestIds): string {
    $params = array_filter([
        'domain' => $reportDomain, 'date_from' => $dateFrom, 'date_to' => $dateTo,
        'process_id' => $selectedProcessIds, 'form_id' => $selectedFormIds, 'test_id' => $selectedTestIds,
        'priority_page' => max(1, $page),
    ], static fn($value): bool => $value !== null && $value !== '' && $value !== []);
    return route_url($reportDomain === 'tests' ? 'client-admin.test-incidents' : 'evaluation-surveys.dashboard.integrity') . '?' . http_build_query($params);
};
$assigned = max(0, (int) ($summary['assigned_people'] ?? 0));
$incidents = max(0, (int) ($summary['people_with_incidents'] ?? 0));
$totalCases = max(count($cases), $incidents);
$priorityPages = max(1, (int) ceil($totalCases / $priorityPerPage));
$priorityPage = min($priorityPage, $priorityPages);
$priorityCases = $showAllCases ? $cases : array_slice($cases, 0, $priorityPerPage);
$highAlerts = max(0, (int) ($summary['high_alert_cases'] ?? 0));
$reviewCases = max(0, $incidents - $highAlerts);
$withoutIncidents = max(0, $assigned - $incidents);
$incidentRate = $assigned > 0 ? ($incidents / $assigned) * 100 : 0;
$reviewRate = $incidents > 0 ? ($reviewCases / $incidents) * 100 : 0;
$highRate = $incidents > 0 ? ($highAlerts / $incidents) * 100 : 0;
$safeIncidentRate = min(100, max(0, $incidentRate));
$safeHighRate = min(100, max(0, $highRate));
$trend = is_array($analysis['trend'] ?? null) ? $analysis['trend'] : [];
if (!$trend) foreach ($cases as $case) {
    $date = substr((string) ($case['attempt_started_at'] ?? $case['updated_at'] ?? ''), 0, 10);
    if ($date !== '') $trend[$date] = ($trend[$date] ?? 0) + 1;
}
ksort($trend);
$trend = array_slice($trend, -8, 8, true);
$trendMax = max(1, ...array_values($trend ?: [1]));
$trendPoints = [];
$trendLabels = [];
$trendCount = max(1, count($trend));
foreach (array_values($trend) as $index => $value) {
    $x = $trendCount === 1 ? 150 : 10 + (($index / ($trendCount - 1)) * 280);
    $y = 92 - (($value / $trendMax) * 72);
    $trendPoints[] = number_format($x, 2, '.', '') . ',' . number_format($y, 2, '.', '');
}
foreach (array_keys($trend) as $date) $trendLabels[] = date('d M', strtotime($date));
?>
<style>
.integrity-proposal{--ip-blue:#2451d6;--ip-ink:#152b63;--ip-muted:#7180a0;--ip-line:#e4eaf5;background:#f4f7fc;margin:-1.5rem;padding:1.5rem;color:var(--ip-ink)}
.integrity-proposal .ip-panel{background:#fff;border:1px solid var(--ip-line);border-radius:14px;box-shadow:0 4px 18px rgba(35,65,130,.04)}
.integrity-proposal .ip-title{font-size:2rem;letter-spacing:-.035em;color:var(--ip-ink)}
.integrity-proposal .ip-kicker{font-size:.78rem;text-transform:uppercase;letter-spacing:.08em;font-weight:800;color:var(--ip-blue)}
.integrity-proposal .ip-subtitle{color:var(--ip-muted);max-width:920px}
.integrity-proposal .ip-status{border-radius:14px;padding:1rem 1.15rem;height:100%;border:1px solid transparent}
.integrity-proposal .ip-status strong{font-size:1.7rem;line-height:1.1}.integrity-proposal .ip-status small{color:#667693}
.integrity-proposal .ip-status-danger{background:#fff1f1;border-color:#ffd4d4}.integrity-proposal .ip-status-warning{background:#fff9e8;border-color:#ffe7a4}.integrity-proposal .ip-status-blue{background:#eef3ff;border-color:#cfdbff}
.integrity-proposal .ip-icon{width:42px;height:42px;display:grid;place-items:center;border-radius:50%;font-size:1.3rem}.integrity-proposal .ip-status-danger .ip-icon{background:#ffdede;color:#dc2626}.integrity-proposal .ip-status-warning .ip-icon{background:#ffe9a8;color:#b77900}.integrity-proposal .ip-status-blue .ip-icon{background:#dbe5ff;color:var(--ip-blue)}
.integrity-proposal .ip-filter{padding:1rem 1.1rem}.integrity-proposal .form-label{font-size:.76rem;font-weight:800;color:#667693;margin-bottom:.35rem}.integrity-proposal .form-control,.integrity-proposal .form-select{border-color:#dbe3f0;border-radius:9px;color:#35466d;min-height:42px}
.integrity-proposal .ip-filter form{--bs-gutter-x:1.25rem;--bs-gutter-y:1rem}.integrity-proposal .ip-filter .btn{min-height:42px;white-space:nowrap}
.integrity-proposal .ip-card{padding:1rem 1.1rem;height:100%}.integrity-proposal .ip-card h2{font-size:1.05rem;margin-bottom:.2rem}.integrity-proposal .ip-card .ip-card-subtitle{font-size:.78rem;color:var(--ip-muted);min-height:2.2rem}
.integrity-proposal .ip-donut{width:132px;height:132px;border-radius:50%;display:grid;place-items:center;position:relative;flex:0 0 auto}.integrity-proposal .ip-donut:after{content:"";position:absolute;inset:23px;background:#fff;border-radius:50%}.integrity-proposal .ip-donut-content{z-index:1;text-align:center;line-height:1.05}.integrity-proposal .ip-donut-content strong{display:block;font-size:1.35rem}.integrity-proposal .ip-donut-content span{font-size:.68rem;color:#667693}.integrity-proposal .ip-legend{font-size:.78rem}.integrity-proposal .ip-legend-row{display:flex;align-items:center;justify-content:space-between;gap:.7rem;margin:.55rem 0}.integrity-proposal .ip-dot{width:10px;height:10px;border-radius:50%;display:inline-block;margin-right:.35rem}.integrity-proposal .ip-chart{width:100%;height:150px;display:block}.integrity-proposal .ip-gridline{stroke:#e7edf7;stroke-width:1}.integrity-proposal .ip-axis{font-size:10px;fill:#8491aa}.integrity-proposal .ip-trend-review{fill:none;stroke:#efaa14;stroke-width:3;stroke-linecap:round;stroke-linejoin:round}.integrity-proposal .ip-trend-fill{fill:rgba(255,188,45,.17)}
.integrity-proposal .ip-table thead th{font-size:.7rem;text-transform:uppercase;letter-spacing:.03em;color:#7180a0;background:#f7f9fd;border-bottom:1px solid var(--ip-line);white-space:nowrap}.integrity-proposal .ip-table td{font-size:.78rem;color:#405174;border-color:#edf1f7}.integrity-proposal .ip-table .badge{font-weight:700}.integrity-proposal .ip-table-wrap{overflow-x:auto}.integrity-proposal .ip-pagination{font-size:.78rem;color:#7180a0}
@media (max-width:767.98px){.integrity-proposal{margin:-1rem;padding:1rem}.integrity-proposal .ip-title{font-size:1.5rem}.integrity-proposal .ip-donut{width:110px;height:110px}.integrity-proposal .ip-donut:after{inset:19px}}
</style>
<div class="integrity-proposal">
    <section class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4"><div><p class="ip-kicker mb-1">Reportes</p><h1 class="ip-title fw-bold mb-1"><?= $reportDomain === 'tests' ? 'Reporte Incidencia Test Psicolaborales' : 'Reporte Incidencia Evaluaciones' ?></h1><p class="ip-subtitle mb-0">Señales registradas durante <?= $reportDomain === 'tests' ? 'tests psicolaborales' : 'evaluaciones' ?> asociadas a procesos. Requieren revisión autorizada y no constituyen por sí solas una conclusión de fraude.</p></div><div class="d-flex flex-wrap gap-2"><span class="btn btn-outline-primary"><i class="bi bi-shield-check me-1"></i><?= e((string) ((current_user() ?: [])['company_name'] ?? 'Empresa')) ?></span><a class="btn btn-outline-secondary" href="<?= e(route_url($reportDomain === 'tests' ? 'client-admin.test-progress' : 'evaluation-surveys.dashboard')) ?>"><i class="bi bi-arrow-left me-1"></i>Volver</a></div></section>
    <section class="row g-3 mb-3" aria-label="Indicadores prioritarios">
        <?php foreach ([['Casos con incidencias', $incidents, 'Total: ' . $number($incidents) . ' de ' . $number($assigned) . ' asignaciones', 'ip-status-danger', 'bi-exclamation-triangle'], ['Casos que requieren revisión', $reviewCases, $number($reviewCases) . ' de ' . $number($incidents) . ' casos con incidencias (' . number_format($reviewRate, 1, ',', '.') . '%)', 'ip-status-warning', 'bi-eye'], ['Alertas altas', $highAlerts, $number($highAlerts) . ' de ' . $number($incidents) . ' casos con incidencias (' . number_format($highRate, 1, ',', '.') . '%)', 'ip-status-blue', 'bi-shield-exclamation']] as [$label, $value, $detail, $tone, $icon]): ?>
            <div class="col-12 col-md-4"><div class="ip-panel ip-status <?= e($tone) ?>"><div class="d-flex align-items-center gap-3"><span class="ip-icon"><i class="bi <?= e($icon) ?>" aria-hidden="true"></i></span><div><span class="d-block fw-semibold small"><?= e($label) ?></span><strong class="d-block"><?= $number($value) ?></strong><small><?= e($detail) ?></small></div></div></div></div>
        <?php endforeach; ?>
    </section>
    <section class="ip-panel ip-filter mb-3" aria-label="Filtros del reporte">
        <?= status_help_button('Estados e integridad del intento', "• Asignada: aún no se abre.\n• En curso: intento abierto; puede no tener respuestas.\n• Completada: enviado explícitamente.\n• Expirada: plazo vencido sin envío y no equivale a completada.\n• Las señales de integridad orientan una revisión autorizada; no son una conclusión automática.") ?>
        <form method="get" class="row g-2 align-items-end">
            <?php if ($reportDomain !== 'tests'): ?><div class="col-12 col-lg-3"><label class="form-label" for="integrity-form-filter">Evaluación</label><select class="form-select js-integrity-select2" id="integrity-form-filter" name="form_id[]" multiple data-placeholder="Todas las evaluaciones"><?php foreach ((array) ($filterOptions['forms'] ?? []) as $optionId => $optionTitle): ?><option value="<?= (int) $optionId ?>" <?= in_array((int) $optionId, $selectedFormIds, true) ? 'selected' : '' ?>><?= e((string) $optionTitle) ?></option><?php endforeach; ?></select></div><?php endif; ?>
            <?php if ($reportDomain !== 'evaluations'): ?><div class="col-12 col-lg-3"><label class="form-label" for="integrity-test-filter">Test psicolaboral</label><select class="form-select js-integrity-select2" id="integrity-test-filter" name="test_id[]" multiple data-placeholder="Todos los tests"><?php foreach ((array) ($filterOptions['tests'] ?? []) as $optionId => $optionTitle): ?><option value="<?= (int) $optionId ?>" <?= in_array((int) $optionId, $selectedTestIds, true) ? 'selected' : '' ?>><?= e((string) $optionTitle) ?></option><?php endforeach; ?></select></div><?php endif; ?>
            <div class="col-12 col-lg-3"><label class="form-label" for="integrity-process-filter">Proceso</label><select class="form-select js-integrity-select2" id="integrity-process-filter" name="process_id[]" multiple data-placeholder="Todos los procesos"><?php foreach ((array) ($filterOptions['processes'] ?? []) as $optionId => $optionName): ?><option value="<?= (int) $optionId ?>" <?= in_array((int) $optionId, $selectedProcessIds, true) ? 'selected' : '' ?>><?= e((string) $optionName) ?></option><?php endforeach; ?></select></div>
            <div class="col-6 col-lg-2"><label class="form-label" for="integrity-date-from">Desde</label><input class="form-control" type="date" id="integrity-date-from" name="date_from" value="<?= e((string) ($dateFrom ?? '')) ?>"></div><div class="col-6 col-lg-2"><label class="form-label" for="integrity-date-to">Hasta</label><input class="form-control" type="date" id="integrity-date-to" name="date_to" value="<?= e((string) ($dateTo ?? '')) ?>"></div><div class="col-12 col-lg-1 d-grid"><button class="btn btn-primary" type="submit">Filtrar</button></div>
        </form>
    </section>
    <section class="row g-3 mb-3" aria-label="Análisis visual de incidencias">
        <div class="col-12 col-xl-4"><div class="ip-panel ip-card"><h2 class="fw-bold">Panorama de riesgo</h2><p class="ip-card-subtitle">De las <?= $number($assigned) ?> asignaciones del período seleccionado.</p><div class="d-flex align-items-center gap-3"><div class="ip-donut" style="background:conic-gradient(#20ae65 0 <?= number_format(100 - $safeIncidentRate, 2, '.', '') ?>%, #fbb52a <?= number_format(100 - $safeIncidentRate, 2, '.', '') ?>% <?= number_format(100 - $safeHighRate, 2, '.', '') ?>%, #e84a4a <?= number_format(100 - $safeHighRate, 2, '.', '') ?>% 100%)"><div class="ip-donut-content"><strong><?= $number($assigned) ?></strong><span>Asignaciones</span></div></div><div class="ip-legend flex-grow-1"><div class="ip-legend-row"><span><i class="ip-dot" style="background:#e84a4a"></i>Alertas altas</span><strong><?= $number($highAlerts) ?></strong></div><div class="ip-legend-row"><span><i class="ip-dot" style="background:#fbb52a"></i>Requieren revisión</span><strong><?= $number($reviewCases) ?></strong></div><div class="ip-legend-row"><span><i class="ip-dot" style="background:#20ae65"></i>Sin incidencias</span><strong><?= $number($withoutIncidents) ?></strong></div></div></div></div></div>
        <div class="col-12 col-xl-4"><div class="ip-panel ip-card"><h2 class="fw-bold">Distribución de casos con incidencias</h2><p class="ip-card-subtitle"><?= $number($incidents) ?> casos con incidencias en total. Se dividen entre revisión y alertas altas.</p><div class="d-flex align-items-center gap-3"><div class="ip-donut" style="background:conic-gradient(#fbb52a 0 <?= number_format(100 - $safeHighRate, 2, '.', '') ?>%, #e84a4a <?= number_format(100 - $safeHighRate, 2, '.', '') ?>% 100%)"><div class="ip-donut-content"><strong><?= $number($incidents) ?></strong><span>Con incidencias</span></div></div><div class="ip-legend flex-grow-1"><div class="ip-legend-row"><span><i class="ip-dot" style="background:#e84a4a"></i>Alertas altas</span><strong><?= $number($highAlerts) ?> <small class="text-muted"><?= number_format($highRate, 1, ',', '.') ?>%</small></strong></div><div class="ip-legend-row"><span><i class="ip-dot" style="background:#fbb52a"></i>Requieren revisión</span><strong><?= $number($reviewCases) ?> <small class="text-muted"><?= number_format($reviewRate, 1, ',', '.') ?>%</small></strong></div></div></div></div></div>
        <div class="col-12 col-xl-4"><div class="ip-panel ip-card"><h2 class="fw-bold">Evolución de incidencias</h2><p class="ip-card-subtitle">Casos con señales por fecha del período seleccionado.</p><svg class="ip-chart" viewBox="0 0 300 150" role="img" aria-label="Evolución de casos con incidencias"><line class="ip-gridline" x1="10" y1="20" x2="290" y2="20"></line><line class="ip-gridline" x1="10" y1="56" x2="290" y2="56"></line><line class="ip-gridline" x1="10" y1="92" x2="290" y2="92"></line><polyline class="ip-trend-fill" points="10,92 <?= e(implode(' ', $trendPoints)) ?> 290,92"></polyline><polyline class="ip-trend-review" points="<?= e(implode(' ', $trendPoints ?: ['0,92', '100,92'])) ?>"></polyline><?php if ($trendLabels): ?><text class="ip-axis" x="10" y="112"><?= e($trendLabels[0]) ?></text><text class="ip-axis" x="245" y="112"><?= e($trendLabels[count($trendLabels) - 1]) ?></text><?php endif; ?><circle cx="18" cy="132" r="4" fill="#fbb52a"></circle><text class="ip-axis" x="28" y="136">Casos con incidencias</text></svg></div></div>
    </section>
    <section class="ip-panel ip-card mb-3" aria-label="Casos prioritarios"><div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-2"><div><h2 class="fw-bold mb-1">Casos prioritarios</h2><p class="ip-card-subtitle mb-0">Últimos casos con mayor nivel de alerta. Requieren revisión por parte del equipo autorizado.</p></div><div class="d-flex flex-wrap gap-2"><span class="btn btn-sm btn-outline-primary"><i class="bi bi-list-ul me-1"></i>25 por página</span><a class="btn btn-sm btn-danger" href="<?= e(route_url('evaluation-surveys.dashboard.integrity.pdf') . ($filterQuery !== '' ? '?' . $filterQuery : '')) ?>"><i class="bi bi-file-earmark-pdf me-1"></i>PDF ejecutivo</a><a class="btn btn-sm btn-success" href="<?= e(route_url('evaluation-surveys.dashboard.integrity.xlsx') . ($filterQuery !== '' ? '?' . $filterQuery : '')) ?>"><i class="bi bi-file-earmark-excel me-1"></i>Excel ejecutivo</a></div></div>
        <?php if (!$cases): ?><div class="alert alert-success mb-0"><i class="bi bi-shield-check me-1"></i>No se encontraron señales que requieran revisión en el alcance seleccionado.</div><?php else: ?><div class="ip-table-wrap"><table class="table ip-table align-middle mb-2"><thead><tr><th>Nivel</th><th>Fecha</th><th>Persona</th><th>Proceso</th><th>Test psicolaboral</th><th>Señales registradas</th><th>Última actividad</th><th>Acciones</th></tr></thead><tbody>
        <?php foreach ($priorityCases as $row): [$label, $color, $icon] = $level((string) ($row['alert_level'] ?? 'none')); $signals = array_values(array_filter(array_map(static fn(string $signal): string => $signalLabels[$signal] ?? ucwords(str_replace('_', ' ', $signal)), (array) ($row['signal_types'] ?? [])))); ?>
            <tr><td><span class="badge text-bg-<?= e($color) ?>"><?= e($label) ?></span></td><td><?= e(substr((string) ($row['attempt_started_at'] ?? $row['updated_at'] ?? ''), 0, 10)) ?></td><td><strong><?= e((string) ($row['user_name'] ?? 'Persona')) ?></strong><div class="small text-muted"><?= e((string) ($row['user_email'] ?? '')) ?></div></td><td><?= e((string) ($row['process_name'] ?? 'Proceso')) ?></td><td><?= e((string) ($row['form_title'] ?? 'Test psicolaboral')) ?></td><td><?= e(implode(', ', array_slice($signals, 0, 2))) ?><?php if (count($signals) > 2): ?> <span class="text-muted">+<?= count($signals) - 2 ?></span><?php endif; ?></td><td><?= e((string) ($row['updated_at'] ?? $row['attempt_started_at'] ?? '')) ?></td><td><?php if (!empty($row['session_id'])): $drawerUrl = route_url('test-session.result', (int) $row['session_id']) . '?drawer=1'; ?><a class="btn btn-sm btn-outline-primary" href="<?= e(route_url('test-session.result', (int) $row['session_id'])) ?>" data-drawer-url="<?= e($drawerUrl) ?>" data-drawer-title="Detalle del caso" data-drawer-size="lg">Ver detalle</a><?php elseif (!empty($row['attempt_id'])): ?><a class="btn btn-sm btn-outline-primary" href="<?= e(route_url('evaluation-surveys.attempt.result', (int) $row['attempt_id'])) ?>">Revisar</a><?php else: ?><span class="text-muted">—</span><?php endif; ?></td></tr>
        <?php endforeach; ?></tbody></table></div><div class="d-flex flex-wrap justify-content-between align-items-center gap-2" style="font-size:.78rem;color:#7180a0"><span>Mostrando <?= $number(count($priorityCases)) ?> de <?= $number($totalCases) ?> casos priorizados<?= $priorityPages > 1 ? ' · Página ' . $priorityPage . ' de ' . $priorityPages : '' ?></span><?php if ($priorityPages > 1): ?><nav class="d-flex align-items-center gap-1" aria-label="Paginación de casos prioritarios"><?php if ($priorityPage > 1): ?><a class="btn btn-sm btn-outline-secondary" href="<?= e($priorityPageUrl($priorityPage - 1)) ?>">Anterior</a><?php endif; ?><?php if ($priorityPage < $priorityPages): ?><a class="btn btn-sm btn-outline-secondary" href="<?= e($priorityPageUrl($priorityPage + 1)) ?>">Siguiente</a><?php endif; ?></nav><?php endif; ?><span class="badge text-bg-light border">Data real del sistema</span></div><?php endif; ?>
    </section>
    <section class="ip-panel ip-card"><h2 class="h5 fw-bold mb-2">Criterio de lectura</h2><p class="text-muted mb-0">“Casos con incidencias” es el total de asignaciones con alguna señal. Ese total se divide en “Casos que requieren revisión” y “Alertas altas”; por eso no deben sumarse como universos independientes. La clasificación se replica en pantalla, PDF y Excel. Ninguna señal automática constituye por sí sola una conclusión de fraude.</p></section>
</div>
<?php if (!empty($useSelect2)): ?><script>
document.addEventListener('DOMContentLoaded', function () { if (window.jQuery && jQuery.fn.select2) { jQuery('.js-integrity-select2').select2({width:'100%', closeOnSelect:false, allowClear:true, placeholder:function(){return jQuery(this).data('placeholder');}}); } });
</script><?php endif; ?>
