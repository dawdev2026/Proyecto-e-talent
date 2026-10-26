<?php
$summary = is_array($summary ?? null) ? $summary : [];
$rows = is_array($rows ?? null) ? $rows : [];
$reportDomain = (string) ($reportDomain ?? 'tests');
$selectedProcessIds = array_values(array_filter(array_map('intval', (array) ($processIds ?? [])), static fn(int $id): bool => $id > 0));
$selectedFormIds = array_values(array_filter(array_map('intval', (array) ($formIds ?? [])), static fn(int $id): bool => $id > 0));
$selectedTestIds = array_values(array_filter(array_map('intval', (array) ($testIds ?? [])), static fn(int $id): bool => $id > 0));
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
    return $value === 'high' ? ['Alerta alta', 'danger'] : ($value === 'review' ? ['En revisión', 'warning'] : ['Sin alerta', 'success']);
};
$cases = array_values(array_filter($rows, static fn(array $row): bool => ($row['alert_level'] ?? 'none') !== 'none'));
$priorityPerPage = 25;
$priorityPage = max(1, (int) ($_GET['priority_page'] ?? 1));
$totalCases = max(count($cases), (int) ($summary['people_with_incidents'] ?? 0));
$priorityPages = max(1, (int) ceil($totalCases / $priorityPerPage));
$priorityPage = min($priorityPage, $priorityPages);
$priorityPageUrl = static function (int $page) use ($reportDomain, $dateFrom, $dateTo, $selectedProcessIds, $selectedFormIds, $selectedTestIds): string {
    $params = array_filter(['domain' => $reportDomain, 'date_from' => $dateFrom, 'date_to' => $dateTo, 'process_id' => $selectedProcessIds, 'form_id' => $selectedFormIds, 'test_id' => $selectedTestIds, 'priority_page' => max(1, $page)], static fn($value): bool => $value !== null && $value !== '' && $value !== []);
    return route_url('client-admin.test-incidents') . '?' . http_build_query($params);
};
?>
<section id="integrity-priority-grid" class="ip-panel ip-card mb-3" aria-label="Casos prioritarios" aria-live="polite">
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-2"><div><h2 class="fw-bold mb-1">Casos prioritarios</h2><p class="ip-card-subtitle mb-0">Últimos casos con mayor nivel de alerta. Requieren revisión por parte del equipo autorizado.</p></div><div class="d-flex flex-wrap gap-2"><span class="btn btn-sm btn-outline-primary"><i class="bi bi-list-ul me-1"></i>25 por página</span><a class="btn btn-sm btn-danger" href="<?= e(route_url('evaluation-surveys.dashboard.integrity.pdf')) ?>"><i class="bi bi-file-earmark-pdf me-1"></i>PDF ejecutivo</a><a class="btn btn-sm btn-success" href="<?= e(route_url('evaluation-surveys.dashboard.integrity.xlsx')) ?>"><i class="bi bi-file-earmark-excel me-1"></i>Excel ejecutivo</a></div></div>
    <?php if (!$cases): ?><div class="alert alert-success mb-0"><i class="bi bi-shield-check me-1"></i>No se encontraron señales que requieran revisión en el alcance seleccionado.</div><?php else: ?><div class="ip-table-wrap"><table class="table ip-table align-middle mb-2"><thead><tr><th>Nivel</th><th>Fecha</th><th>Persona</th><th>Proceso</th><th>Test psicolaboral</th><th>Señales registradas</th><th>Última actividad</th><th>Acciones</th></tr></thead><tbody>
    <?php foreach ($cases as $row): [$label, $color] = $level((string) ($row['alert_level'] ?? 'none')); $signals = array_values(array_filter(array_map(static fn(string $signal): string => $signalLabels[$signal] ?? ucwords(str_replace('_', ' ', $signal)), (array) ($row['signal_types'] ?? [])))); ?>
        <tr><td><span class="badge text-bg-<?= e($color) ?>"><?= e($label) ?></span></td><td><?= e(substr((string) ($row['attempt_started_at'] ?? $row['updated_at'] ?? ''), 0, 10)) ?></td><td><strong><?= e((string) ($row['user_name'] ?? 'Persona')) ?></strong><div class="small text-muted"><?= e((string) ($row['user_email'] ?? '')) ?></div></td><td><?= e((string) ($row['process_name'] ?? 'Proceso')) ?></td><td><?= e((string) ($row['form_title'] ?? 'Test psicolaboral')) ?></td><td><?= e(implode(', ', array_slice($signals, 0, 2))) ?><?php if (count($signals) > 2): ?> <span class="text-muted">+<?= count($signals) - 2 ?></span><?php endif; ?></td><td><?= e((string) ($row['updated_at'] ?? $row['attempt_started_at'] ?? '')) ?></td><td><?php if (!empty($row['session_id'])): $drawerUrl = route_url('test-session.result', (int) $row['session_id']) . '?drawer=1'; ?><a class="btn btn-sm btn-outline-primary" href="<?= e(route_url('test-session.result', (int) $row['session_id'])) ?>" data-drawer-url="<?= e($drawerUrl) ?>" data-drawer-title="Detalle del caso" data-drawer-size="lg">Ver detalle</a><?php else: ?><span class="text-muted">—</span><?php endif; ?></td></tr>
    <?php endforeach; ?></tbody></table></div><div class="d-flex flex-wrap justify-content-between align-items-center gap-2" style="font-size:.78rem;color:#7180a0"><span>Mostrando <?= $number(count($cases)) ?> de <?= $number($totalCases) ?> casos priorizados · Página <?= $priorityPage ?> de <?= $priorityPages ?></span><nav class="d-flex align-items-center gap-1" aria-label="Paginación de casos prioritarios"><?php if ($priorityPage > 1): ?><a class="btn btn-sm btn-outline-secondary integrity-priority-link" data-integrity-priority-link="1" href="<?= e($priorityPageUrl($priorityPage - 1)) ?>">Anterior</a><?php endif; ?><?php if ($priorityPage < $priorityPages): ?><a class="btn btn-sm btn-outline-secondary integrity-priority-link" data-integrity-priority-link="1" href="<?= e($priorityPageUrl($priorityPage + 1)) ?>">Siguiente</a><?php endif; ?></nav><span class="badge text-bg-light border">Data real del sistema</span></div><?php endif; ?>
</section>
