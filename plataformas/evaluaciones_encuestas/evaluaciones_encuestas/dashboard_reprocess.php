<?php
$form = is_array($form ?? null) ? $form : [];
$attempt = is_array($attempt ?? null) ? $attempt : [];
$number = static fn($value): string => number_format((float) $value, 2, ',', '.');
?>
<section class="page-header">
    <div>
        <p class="dashboard-kicker mb-1">Dashboard de evaluaciones</p>
        <h1 class="fw-bold mb-1">Reprocesar evaluación</h1>
        <p class="text-muted mb-0">Revisión previa obligatoria antes de calcular la nota.</p>
    </div>
    <a class="btn btn-outline-secondary" href="<?= e(route_url('evaluation-surveys.dashboard.results', (int) ($form['id'] ?? 0)) . (!empty($processId) ? '?process_id=' . (int) $processId : '')) ?>">Volver al detalle</a>
</section>
<section class="card content-panel">
    <h2 class="h5 fw-bold mb-3">Resumen del caso</h2>
    <div class="table-responsive"><table class="table align-middle mb-4"><tbody>
        <tr><th>Persona</th><td><?= e((string) ($attempt['user_name'] ?? '')) ?><div class="text-muted small"><?= e((string) ($attempt['user_email'] ?? '')) ?></div></td></tr>
        <tr><th>Evaluación</th><td><?= e((string) ($attempt['form_title'] ?? $form['title'] ?? '')) ?></td></tr>
        <tr><th>Proceso</th><td><?= e((string) ($attempt['process_name'] ?? 'Sin proceso identificado')) ?></td></tr>
        <tr><th>Estado actual</th><td><span class="badge text-bg-warning">En curso</span></td></tr>
        <tr><th>Nota actual</th><td>Sin nota</td></tr>
        <tr><th>Aprobación actual</th><td>Sin estado</td></tr>
        <tr><th>Respuestas registradas</th><td><?= (int) ($attempt['answered_count'] ?? 0) ?> de <?= (int) ($attempt['question_count'] ?? 0) ?><?php if ((int) ($attempt['pending_count'] ?? 0) > 0): ?> <span class="text-warning">(<?= (int) $attempt['pending_count'] ?> sin responder)</span><?php endif; ?></td></tr>
        <tr><th>Nota máxima / aprobación</th><td><?= $number($form['max_score'] ?? 0) ?> / <?= $form['passing_score'] === null ? 'No configurada' : $number($form['passing_score']) ?></td></tr>
    </tbody></table></div>
    <div class="alert alert-warning">Se recalcularán los puntajes de las respuestas existentes y el intento pasará a <strong>Completada</strong>. Las respuestas no serán modificadas. Si faltan respuestas, se considerarán sin puntaje.</div>
    <form method="post" class="d-flex flex-wrap gap-2" data-confirm-submit="¿Confirmas reprocesar esta evaluación y calcular su nota?"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="attempt_sid" value="<?= e((string) ($attempt['attempt_sid'] ?? '')) ?>"><input type="hidden" name="process_id" value="<?= (int) ($processId ?? 0) ?>"><input type="hidden" name="confirm_reprocess" value="1"><button class="btn btn-warning" type="submit">Confirmar y reprocesar</button><a class="btn btn-outline-secondary" href="<?= e(route_url('evaluation-surveys.dashboard.results', (int) ($form['id'] ?? 0)) . (!empty($processId) ? '?process_id=' . (int) $processId : '')) ?>">Cancelar</a></form>
</section>
