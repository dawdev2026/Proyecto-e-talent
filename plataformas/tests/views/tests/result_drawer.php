<?php
$session = is_array($session ?? null) ? $session : [];
$summary = is_array($summary ?? null) ? $summary : [];
$resultContext = is_array($resultContext ?? null) ? $resultContext : [];
$activityEvents = is_array($activityEvents ?? null) ? $activityEvents : [];
$audioVisualRisks = is_array($audioVisualRisks ?? null) ? $audioVisualRisks : [];
$mediaEvidences = is_array($mediaEvidences ?? null) ? $mediaEvidences : [];
$screenCaptures = is_array($screenCaptures ?? null) ? $screenCaptures : [];
$answeredItems = is_array($answeredItems ?? null) ? $answeredItems : [];
$activityTotal = max(count($activityEvents), (int) ($activityTotal ?? 0));
$audioVisualRiskTotal = max(count($audioVisualRisks), (int) ($audioVisualRiskTotal ?? 0));
$screenCaptureTotal = max(count($screenCaptures), (int) ($screenCaptureTotal ?? 0));
$activityPage = max(1, (int) ($activityPage ?? 1));
$mediaPage = max(1, (int) ($mediaPage ?? 1));
$capturesPage = max(1, (int) ($capturesPage ?? 1));
$drawerPerPage = max(1, (int) ($drawerPerPage ?? 10));
$statusLabels = ['assigned' => 'Asignada', 'in_progress' => 'En curso', 'completed' => 'Completada', 'expired' => 'Expirada'];
$status = (string) ($session['status'] ?? '');
$statusLabel = $statusLabels[$status] ?? ucfirst($status ?: 'Sin estado');
?>
<div class="test-result-drawer">
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
        <div>
            <p class="text-uppercase text-primary fw-bold small mb-1">Detalle del caso</p>
            <h3 class="h4 fw-bold mb-1"><?= e((string) ($session['instrument_name'] ?? 'Test psicolaboral')) ?></h3>
            <p class="text-muted mb-0">Revisión autorizada de la sesión y sus señales registradas.</p>
        </div>
            <span class="badge rounded-pill text-bg-<?= $audioVisualRisks || $activityEvents ? 'warning' : 'success' ?>"><?= $audioVisualRisks || $activityEvents ? 'Revisión contextual' : 'Sin señales adicionales' ?></span>
    </div>

    <div class="row g-2 mb-4">
        <?php foreach ([['Persona', $session['user_name'] ?? '—'], ['Proceso', $session['process_name'] ?? '—'], ['Estado', $statusLabel], ['Inicio', $session['started_at'] ?? $session['created_at'] ?? '—']] as [$label, $value]): ?>
            <div class="col-12 col-sm-6"><div class="border rounded-3 p-3 h-100 bg-light-subtle"><div class="small text-muted mb-1"><?= e($label) ?></div><strong><?= e((string) $value) ?></strong><?php if ($label === 'Persona' && !empty($session['user_email'])): ?><div class="small text-muted mt-1"><?= e((string) $session['user_email']) ?></div><?php endif; ?></div></div>
        <?php endforeach; ?>
    </div>

    <div class="row g-2 mb-4" aria-label="Resumen del caso">
        <div class="col-4"><div class="border rounded-3 p-3 text-center h-100"><strong class="d-block fs-4"><?= count($answeredItems) ?></strong><span class="small text-muted">respuestas registradas</span></div></div>
        <div class="col-4"><div class="border rounded-3 p-3 text-center h-100"><strong class="d-block fs-4"><?= $activityTotal ?></strong><span class="small text-muted">eventos de actividad</span></div></div>
        <div class="col-4"><div class="border rounded-3 p-3 text-center h-100"><strong class="d-block fs-4"><?= $audioVisualRiskTotal ?></strong><span class="small text-muted">eventos audiovisuales</span></div></div>
    </div>

    <section class="mb-4" aria-labelledby="drawer-signals-title">
        <div class="d-flex justify-content-between align-items-center gap-2 mb-2"><h4 id="drawer-signals-title" class="h6 fw-bold mb-0">Actividad registrada</h4><span class="small text-muted">Mostrando <?= count($activityEvents) ?> de <?= $activityTotal ?> eventos</span></div>
        <div id="drawer-activity-grid" aria-live="polite">
            <?php require __DIR__ . '/result_drawer_activity.php'; ?>
        </div>
    </section>

    <section class="mb-4" aria-labelledby="drawer-media-title">
        <div class="d-flex justify-content-between align-items-center gap-2 mb-2"><h4 id="drawer-media-title" class="h6 fw-bold mb-0">Control audiovisual</h4><span class="small text-muted"><?= $screenCaptureTotal ?> capturas almacenadas · <?= $audioVisualRiskTotal ?> eventos audiovisuales</span></div>
        <?php if (!$mediaEvidences && $audioVisualRiskTotal <= 0 && $screenCaptureTotal <= 0): ?><p class="text-muted small mb-0">No hay evidencia audiovisual ni señales audiovisuales asociadas.</p><?php else: ?>
            <?php if ($mediaEvidences): $latestEvidence = end($mediaEvidences); $mediaStatus = (string) ($latestEvidence['status'] ?? ''); ?><div class="alert alert-<?= in_array($mediaStatus, ['saved', 'partial'], true) ? 'success' : 'warning' ?> py-2 small mb-2">Evidencia: <?= e(['saved' => 'video guardado', 'partial' => 'video parcial', 'failed' => 'video no guardado', 'uploading' => 'carga no finalizada'][$mediaStatus] ?? 'sin estado') ?>.</div><?php endif; ?>
            <div id="drawer-media-grid" aria-live="polite">
                <?php require __DIR__ . '/result_drawer_media.php'; ?>
            </div>
            <?php if ($screenCaptureTotal > 0): ?><h5 class="h6 fw-bold mt-3 mb-2">Capturas almacenadas</h5><div id="drawer-captures-grid" aria-live="polite">
                <?php require __DIR__ . '/result_drawer_captures.php'; ?>
            </div><?php endif; ?>
        <?php endif; ?>
    </section>

    <div class="alert alert-info small mb-4">Estas señales requieren revisión contextual. Ninguna señal automática constituye por sí sola una conclusión de fraude.</div>
    <div class="d-flex justify-content-end"><button type="button" class="btn btn-outline-secondary" data-app-drawer-close>Cerrar detalle</button></div>
</div>
