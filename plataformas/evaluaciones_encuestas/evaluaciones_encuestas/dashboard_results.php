<?php
$form = is_array($form ?? null) ? $form : [];
$attempts = is_array($attempts ?? null) ? $attempts : [];
$mediaRecoveryCandidates = is_array($mediaRecoveryCandidates ?? null) ? $mediaRecoveryCandidates : [];
$number = static fn($value): string => number_format((float) $value, 1, ',', '.');
$attemptStatusLabels = [
    'assigned' => 'Asignada',
    'in_progress' => 'En curso',
    'completed' => 'Completada',
    'expired' => 'Expirada',
    'cancelled' => 'Cancelada',
];
$isDrawer = (string) ($_GET['drawer'] ?? '') === '1';
?>
<?php if (!$isDrawer): ?><section class="page-header">
    <div>
        <p class="dashboard-kicker mb-1">Dashboard de evaluaciones</p>
        <h1 class="fw-bold mb-1">Resultados individuales</h1>
        <p class="text-muted mb-0"><?= e((string) ($form['title'] ?? 'Evaluación')) ?><?php if (!empty($attempts[0]['process_name'])): ?> · Proceso: <?= e((string) $attempts[0]['process_name']) ?><?php endif; ?> · Nota máxima <?= $number($form['max_score'] ?? 0) ?> · Aprobación <?= $form['passing_score'] === null ? 'no configurada' : $number($form['passing_score']) ?></p>
    </div>
    <a class="btn btn-outline-secondary" href="<?= e(route_url('evaluation-surveys.dashboard')) ?>"><i class="bi bi-arrow-left me-1"></i>Volver al dashboard</a>
    <div class="d-flex flex-wrap gap-2 mt-3">
        <?php $exportBase = route_url('evaluation-surveys.dashboard.results.export', (int) ($form['id'] ?? 0)) . (!empty($_GET['process_id']) ? '?process_id=' . (int) $_GET['process_id'] . '&' : '?'); ?>
        <a class="btn btn-sm btn-success" href="<?= e($exportBase . 'type=detail') ?>"><i class="bi bi-file-earmark-excel me-1"></i>Respuestas por persona</a>
        <a class="btn btn-sm btn-outline-success" href="<?= e($exportBase . 'type=incorrect') ?>"><i class="bi bi-file-earmark-excel me-1"></i>Respuestas erróneas</a>
        <a class="btn btn-sm btn-outline-success" href="<?= e($exportBase . 'type=summary') ?>"><i class="bi bi-file-earmark-excel me-1"></i>Resumen</a>
        <?php if ($mediaRecoveryCandidates): ?><form method="post" action="<?= e(route_url('evaluation-surveys.dashboard.results.media-recovery', (int) ($form['id'] ?? 0))) ?>" onsubmit="return window.confirm('¿Confirmas recuperar las evidencias audiovisuales pendientes? Solo se procesarán casos con fragmentos almacenados.');"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="process_id" value="<?= (int) ($_GET['process_id'] ?? 0) ?>"><button class="btn btn-sm btn-outline-warning" type="submit"><i class="bi bi-arrow-repeat me-1"></i>Recuperar <?= count($mediaRecoveryCandidates) ?> evidencias</button></form><?php endif; ?>
    </div>
 </section><?php else: ?><div class="drawer-detail-heading mb-3">
    <p class="dashboard-kicker mb-1">Dashboard de evaluaciones</p>
    <h2 class="h4 fw-bold mb-1">Resultados individuales</h2>
    <p class="text-muted mb-0"><?= e((string) ($form['title'] ?? 'Evaluación')) ?></p>
</div><?php endif; ?>
<section class="content-panel">
    <?php if (!$attempts): ?>
        <div class="alert alert-info mb-0">No hay intentos registrados para esta evaluación en el alcance disponible.</div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table align-middle app-table app-data-table" data-export-title="Resultados individuales">
                <thead><tr><th>Persona</th><th>Proceso</th><th>Intento</th><th>Estado</th><th>Nota</th><th>Porcentaje</th><th>Finalizada</th><th class="text-end no-sort no-export">Detalle</th></tr></thead>
                <tbody>
                <?php foreach ($attempts as $attempt): ?>
                    <?php $max = max(0.01, (float) ($attempt['max_score'] ?? $form['max_score'] ?? 0)); $score = $attempt['final_score'] !== null ? (float) $attempt['final_score'] : null; $passed = $score !== null && $form['passing_score'] !== null ? $score >= (float) $form['passing_score'] : null; ?>
                    <tr>
                        <td><strong><?= e((string) ($attempt['user_name'] ?? 'Persona')) ?></strong><div class="text-muted small"><?= e((string) ($attempt['user_email'] ?? '')) ?></div></td>
                        <td><?= e((string) ($attempt['process_name'] ?? 'Sin proceso identificado')) ?></td>
                        <td><?= (int) ($attempt['attempt_number'] ?? 0) ?></td>
                        <td><?php if ($passed === true): ?><span class="badge text-bg-success">APROBADO</span><?php elseif ($passed === false): ?><span class="badge text-bg-danger">REPROBADO</span><?php else: ?><span class="badge text-bg-secondary"><?= e($attemptStatusLabels[(string) ($attempt['status'] ?? '')] ?? 'Estado no disponible') ?></span><?php endif; ?></td>
                        <td><?= $score === null ? '—' : $number($score) . ' / ' . $number($max) ?></td>
                        <td><?= $score === null ? '—' : $number(($score / $max) * 100) . '%' ?></td>
                        <td><?= e((string) ($attempt['completed_at'] ?? '—')) ?></td>
                        <?php $attemptResultUrl = route_url('evaluation-surveys.attempt.result', (int) $attempt['id']); ?>
                        <td class="text-end"><div class="d-inline-flex flex-wrap justify-content-end gap-1"><a class="btn btn-sm btn-outline-primary" href="<?= e($attemptResultUrl) ?>" data-drawer-url="<?= e($attemptResultUrl . '?drawer=1') ?>" data-drawer-title="Resultado · <?= e((string) ($attempt['user_name'] ?? 'Persona')) ?>" data-drawer-size="lg">Ver resultado</a><?php if (isset($mediaRecoveryCandidates[(int) $attempt['id']])): ?><form method="post" action="<?= e(route_url('evaluation-surveys.dashboard.results.media-recovery', (int) $form['id'])) ?>" onsubmit="return window.confirm('¿Confirmas recuperar la evidencia audiovisual de esta persona?');"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="attempt_sid" value="<?= e(secure_url_token((int) $attempt['id'], 'evaluation_survey_attempt')) ?>"><input type="hidden" name="process_id" value="<?= (int) ($_GET['process_id'] ?? 0) ?>"><button class="btn btn-sm btn-outline-warning" type="submit">Recuperar evidencia</button></form><?php endif; ?><?php if (($attempt['final_score'] ?? null) === null && ($attempt['passed'] ?? null) === null && (string) ($attempt['status'] ?? '') === 'in_progress' && (int) ($attempt['answer_count'] ?? 0) > 0): ?><a class="btn btn-sm btn-outline-warning" href="<?= e(route_url('evaluation-surveys.dashboard.results.reprocess', (int) $form['id']) . '?attempt_sid=' . urlencode(secure_url_token((int) $attempt['id'], 'evaluation_survey_attempt')) . '&process_id=' . (int) ($_GET['process_id'] ?? 0)) ?>">Reprocesar nota</a><?php endif; ?></div></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>
