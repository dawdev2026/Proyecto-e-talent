<?php
$summary = is_array($summary ?? null) ? $summary : [];
$rows = is_array($rows ?? null) ? $rows : [];
$filterOptions = is_array($filterOptions ?? null) ? $filterOptions : ['forms' => [], 'tests' => [], 'processes' => []];
$selectedFormIds = array_values(array_filter(array_map('intval', (array) ($formIds ?? [])), static fn(int $id): bool => $id > 0));
$selectedProcessIds = array_values(array_filter(array_map('intval', (array) ($processIds ?? [])), static fn(int $id): bool => $id > 0));
$selectedTestIds = array_values(array_filter(array_map('intval', (array) ($testIds ?? [])), static fn(int $id): bool => $id > 0));
$filterQuery = http_build_query(array_filter(['process_id' => $selectedProcessIds, 'form_id' => $selectedFormIds, 'test_id' => $selectedTestIds]));
$number = static fn($value): string => number_format((float) $value, 0, ',', '.');
$signalLabels = [
    'tab_hidden' => 'Cambio de pestaña', 'window_blurred' => 'Pérdida de foco',
    'fullscreen_exited' => 'Salida de pantalla completa', 'fullscreen_denied' => 'Pantalla completa denegada',
    'fullscreen_failed' => 'Pantalla completa fallida', 'suspicious_key_printscreen' => 'Captura de pantalla',
    'suspicious_key_print' => 'Intento de impresión', 'suspicious_key_copy' => 'Intento de copia',
    'suspicious_key_save' => 'Intento de guardado', 'suspicious_key_devtools' => 'Herramientas de desarrollo',
    'fullscreen_unavailable' => 'Pantalla completa no disponible', 'inactive_detected' => 'Inactividad detectada',
    'copy_blocked' => 'Copia bloqueada', 'cut_blocked' => 'Corte bloqueado', 'paste_blocked' => 'Pegado bloqueado',
    'print_blocked' => 'Impresión bloqueada', 'context_menu_blocked' => 'Menú contextual bloqueado', 'drag_blocked' => 'Arrastre bloqueado',
    'audio_visual_upload_failed' => 'Falla de carga audiovisual', 'multiple_voice_possible' => 'Posibles voces múltiples',
    'audio_visual_risk' => 'Riesgo audiovisual', 'audio_visual_recording_interrupted' => 'Interrupción de grabación',
];
$level = static function (string $value): array {
    return $value === 'high' ? ['Alerta alta', 'danger', 'bi-shield-exclamation'] : ($value === 'review' ? ['Requiere revisión', 'warning', 'bi-eye'] : ['Sin alerta', 'success', 'bi-shield-check']);
};
$cases = array_values(array_filter($rows, static fn(array $row): bool => ($row['alert_level'] ?? 'none') !== 'none'));
?>
<section class="page-header">
    <div><p class="dashboard-kicker mb-1">Encuestas, Evaluaciones y Tests psicométricos</p><h1 class="fw-bold mb-1">Reporte de incidencias</h1><p class="text-muted mb-0">Señales observables en evaluaciones y tests psicométricos ejecutados dentro de procesos. Requieren revisión autorizada y no constituyen por sí solas una conclusión de fraude.</p></div>
    <div class="d-flex flex-wrap gap-2"><a class="btn btn-outline-secondary" href="<?= e(route_url('evaluation-surveys.dashboard')) ?>"><i class="bi bi-arrow-left me-1"></i>Volver al dashboard</a></div>
</section>
<section class="content-panel mb-4" aria-label="Filtros del reporte">
    <form method="get" class="row g-2 align-items-end">
        <div class="col-12 col-md-4"><label class="form-label" for="integrity-form-filter">Evaluación</label><select class="form-select js-integrity-select2" id="integrity-form-filter" name="form_id[]" multiple data-placeholder="Todas las evaluaciones"><?php foreach ((array) ($filterOptions['forms'] ?? []) as $optionId => $optionTitle): ?><option value="<?= (int) $optionId ?>" <?= in_array((int) $optionId, $selectedFormIds, true) ? 'selected' : '' ?>><?= e((string) $optionTitle) ?></option><?php endforeach; ?></select></div>
        <div class="col-12 col-md-4"><label class="form-label" for="integrity-test-filter">Test psicométrico</label><select class="form-select js-integrity-select2" id="integrity-test-filter" name="test_id[]" multiple data-placeholder="Todos los tests"><?php foreach ((array) ($filterOptions['tests'] ?? []) as $optionId => $optionTitle): ?><option value="<?= (int) $optionId ?>" <?= in_array((int) $optionId, $selectedTestIds, true) ? 'selected' : '' ?>><?= e((string) $optionTitle) ?></option><?php endforeach; ?></select></div>
        <div class="col-12 col-md-4"><label class="form-label" for="integrity-process-filter">Proceso</label><select class="form-select js-integrity-select2" id="integrity-process-filter" name="process_id[]" multiple data-placeholder="Todos los procesos"><?php foreach ((array) ($filterOptions['processes'] ?? []) as $optionId => $optionName): ?><option value="<?= (int) $optionId ?>" <?= in_array((int) $optionId, $selectedProcessIds, true) ? 'selected' : '' ?>><?= e((string) $optionName) ?></option><?php endforeach; ?></select></div>
        <div class="col-12 d-flex justify-content-end gap-2"><button class="btn btn-primary" type="submit">Filtrar</button><a class="btn btn-outline-secondary" href="<?= e(route_url('evaluation-surveys.dashboard.integrity')) ?>" aria-label="Limpiar filtros">×</a></div>
    </form>
</section>
<section class="row g-3 mb-4" aria-label="Resumen ejecutivo de incidencias">
    <?php foreach ([['Asignaciones / sesiones','assigned_people','bi-people','primary'],['Personas distintas asignadas','distinct_assigned_people','bi-people-fill','info'],['Con intento / ejecución','attempted_people','bi-play-circle','info'],['Asignaciones / sesiones con incidencias','people_with_incidents','bi-flag','warning'],['Revisión','review_cases','bi-eye','warning'],['Alertas altas','high_alert_cases','bi-shield-exclamation','danger']] as [$label,$key,$icon,$color]): ?>
        <div class="col-12 col-sm-6 col-xl"><div class="content-panel h-100"><div class="d-flex align-items-center gap-3"><span class="rounded-circle text-bg-<?= e($color) ?> p-2"><i class="bi <?= e($icon) ?>" aria-hidden="true"></i></span><div><span class="text-muted small d-block"><?= e($label) ?></span><strong class="fs-4"><?= $number($summary[$key] ?? 0) ?></strong></div></div></div></div>
    <?php endforeach; ?>
</section>
<section class="content-panel mb-4">
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-3"><div><h2 class="h5 fw-bold mb-1">Resumen ejecutivo</h2><p class="text-muted mb-0">El análisis considera asignaciones de evaluaciones y sesiones de tests psicométricos vinculadas a procesos.</p></div><div class="text-end"><span class="text-muted small d-block">Eventos de actividad / audiovisuales</span><strong><?= $number($summary['activity_events'] ?? 0) ?> / <?= $number($summary['media_events'] ?? 0) ?></strong></div></div>
    <?php $totalAssignments = max(1, (int) ($summary['assigned_people'] ?? 0)); $incidentRate = ((int) ($summary['people_with_incidents'] ?? 0) / $totalAssignments) * 100; ?>
    <div class="row g-3"><div class="col-lg-8"><div class="d-flex justify-content-between small mb-1"><span>Asignaciones con alguna señal</span><strong><?= number_format($incidentRate, 1, ',', '.') ?>%</strong></div><div class="progress" style="height:10px"><div class="progress-bar bg-warning" style="width:<?= min(100, max(0, $incidentRate)) ?>%"></div></div></div><div class="col-lg-4"><div class="alert alert-light border mb-0 py-2"><i class="bi bi-info-circle me-1"></i>La clasificación es única por persona: prioriza primero las alertas altas y luego valida el contexto.</div></div></div>
</section>
<section class="content-panel">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3"><div><h2 class="h5 fw-bold mb-1">Casos priorizados</h2><p class="text-muted mb-0">Hay <?= $number(count($cases)) ?> caso(s) con señales que requieren atención.</p></div><div class="d-flex gap-2"><a class="btn btn-sm btn-danger" href="<?= e(route_url('evaluation-surveys.dashboard.integrity.pdf') . ($filterQuery !== '' ? '?' . $filterQuery : '')) ?>"><i class="bi bi-file-earmark-pdf me-1"></i>PDF ejecutivo</a><a class="btn btn-sm btn-success" href="<?= e(route_url('evaluation-surveys.dashboard.integrity.xlsx') . ($filterQuery !== '' ? '?' . $filterQuery : '')) ?>"><i class="bi bi-file-earmark-excel me-1"></i>Excel ejecutivo</a><span class="badge text-bg-light border align-self-center">Data real del sistema</span></div></div>
    <?php if (!$cases): ?><div class="alert alert-success mb-0"><i class="bi bi-shield-check me-1"></i>No se encontraron señales que requieran revisión en el alcance seleccionado.</div><?php else: ?>
    <div class="table-responsive"><table class="table align-middle app-table"><thead><tr><th>Nivel</th><th>Persona</th><th>Evaluación / proceso</th><th>Señales</th><th>Estado</th><th class="text-end">Acción</th></tr></thead><tbody>
    <?php foreach ($cases as $row): [$label,$color,$icon] = $level((string) ($row['alert_level'] ?? 'none')); $signals = array_values(array_filter(array_map(static fn(string $signal): string => $signalLabels[$signal] ?? ucwords(str_replace('_', ' ', $signal)), (array) ($row['signal_types'] ?? [])))); ?>
        <tr><td><span class="badge text-bg-<?= e($color) ?>"><i class="bi <?= e($icon) ?> me-1"></i><?= e($label) ?></span><div class="small text-muted mt-1"><?= $number($row['incident_total'] ?? 0) ?> evento(s)</div></td><td><strong><?= e((string) ($row['user_name'] ?? 'Persona')) ?></strong><div class="small text-muted"><?= e((string) ($row['user_email'] ?? '')) ?></div></td><td><strong><?= e((string) ($row['form_title'] ?? 'Evaluación')) ?></strong><div class="small text-muted"><?= e((string) ($row['process_name'] ?? 'Proceso')) ?></div></td><td><div class="d-flex flex-wrap gap-1"><?php foreach (array_slice($signals, 0, 5) as $signal): ?><span class="badge rounded-pill text-bg-light border"><?= e($signal) ?></span><?php endforeach; ?><?php if (count($signals) > 5): ?><span class="small text-muted">+<?= count($signals) - 5 ?> más</span><?php endif; ?></div></td><td><?= e((string) ($row['attempt_status'] ?? 'Sin intento')) ?></td><td class="text-end"><?php if (!empty($row['attempt_id'])): ?><a class="btn btn-sm btn-outline-primary" href="<?= e(route_url('evaluation-surveys.attempt.result', (int) $row['attempt_id'])) ?>"><i class="bi bi-search me-1"></i>Revisar</a><?php endif; ?></td></tr>
    <?php endforeach; ?></tbody></table></div><?php endif; ?>
</section>
<section class="content-panel mt-4"><h2 class="h5 fw-bold mb-2">Criterio de lectura</h2><p class="text-muted mb-0">La clasificación se calcula una vez por persona y se replica en pantalla, PDF y Excel. “Requiere revisión” corresponde a señales aisladas, técnicas o ambiguas. “Alerta alta” requiere acumulación de señales conductuales distintas o reincidencia. Ninguna señal automática constituye por sí sola una conclusión de fraude.</p></section>
<?php if (!empty($useSelect2)): ?>
<script>
document.addEventListener('DOMContentLoaded', function () {
    if (window.jQuery && jQuery.fn.select2) {
        jQuery('.js-integrity-select2').select2({
            width: '100%',
            closeOnSelect: false,
            allowClear: true,
            placeholder: function () { return jQuery(this).data('placeholder'); }
        });
    }
});
</script>
<?php endif; ?>
