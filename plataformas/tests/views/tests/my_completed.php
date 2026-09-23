<?php
$completedActivities = is_array($completedActivities ?? null) ? $completedActivities : [];
?>
<section class="page-header">
    <div>
        <p class="text-uppercase text-primary fw-bold small mb-1">Mis Evaluaciones</p>
        <h1 class="fw-bold mb-1">Evaluaciones Realizadas</h1>
        <p class="text-muted mb-0">Revisa tus evaluaciones completadas y la cantidad de respuestas guardadas. <?= status_help_button('Estados de la actividad', "• Completada: se envió explícitamente, incluso si no contiene respuestas.\n• Con respuestas: existe al menos una respuesta no vacía guardada; esta métrica es distinta del estado.\n• Expirada: venció sin envío y no se considera completada.") ?></p>
    </div>
    <a class="btn btn-outline-primary" href="<?= e(route_url('my-tests')) ?>"><i class="bi bi-hourglass-split me-1"></i>Ver pendientes</a>
</section>

<section class="card content-panel">
    <div class="card-body">
        <?php if (!$completedActivities): ?>
            <div class="text-center py-5">
                <i class="bi bi-clipboard-check display-5 text-muted" aria-hidden="true"></i>
                <h2 class="h5 fw-bold mt-3">Aún no tienes evaluaciones realizadas</h2>
                <p class="text-muted mb-0">Las evaluaciones aparecerán aquí cuando las hayas completado.</p>
            </div>
        <?php else: ?>
                <table class="table table-hover align-middle app-table app-data-table" data-export-title="Evaluaciones Realizadas" data-export-excel="false" data-export-pdf="false">
                    <caption class="visually-hidden">Evaluaciones y pruebas que ya fueron completadas.</caption>
                    <thead>
                        <tr>
                            <th scope="col">Evaluación</th>
                            <th scope="col">Tipo</th>
                            <th scope="col">Proceso</th>
                            <th scope="col">Preguntas</th>
                            <th scope="col">Respuestas guardadas</th>
                            <th scope="col">Fecha de término</th>
                            <th scope="col">Estado</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($completedActivities as $activity): ?>
                            <?php $completedAt = strtotime((string) ($activity['completed_at'] ?? '')); ?>
                            <tr>
                                <td class="fw-semibold"><?= e((string) ($activity['activity_name'] ?? 'Evaluación')) ?></td>
                                <td><?= e((string) ($activity['activity_type'] ?? 'Evaluación')) ?></td>
                                <td><?= e((string) ($activity['process_name'] ?? '—') ?: '—') ?></td>
                                <td><?= (int) ($activity['question_count'] ?? 0) ?></td>
                                <td><?= (int) ($activity['answered_count'] ?? 0) ?></td>
                                <td><?= $completedAt ? e(date('d/m/Y H:i', $completedAt)) : '—' ?></td>
                                <td><span class="badge text-bg-success">Completada</span></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
        <?php endif; ?>
    </div>
</section>
