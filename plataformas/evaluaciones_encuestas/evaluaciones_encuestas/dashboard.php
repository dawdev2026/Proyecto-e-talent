<?php
$summary = is_array($summary ?? null) ? $summary : [];
$rows = is_array($rows ?? null) ? $rows : [];
$isCompanyScope = !empty($isCompanyScope);
$number = static fn($value): string => number_format((float) $value, 1, ',', '.');
$statusLabels = [
    'active' => 'Activo',
    'draft' => 'Borrador',
    'inactive' => 'Inactivo',
];
?>
<section class="page-header">
    <div>
        <p class="dashboard-kicker mb-1">Encuestas y Evaluaciones</p>
        <h1 class="fw-bold mb-1">Dashboard de evaluaciones</h1>
        <p class="text-muted mb-0">Resultados de evaluaciones calificadas con nota de aprobación.</p>
    </div>
    <div class="d-flex flex-wrap gap-2">
        <a class="btn btn-success" href="<?= e(route_url('evaluation-surveys.dashboard.summary.xlsx')) ?>"><i class="bi bi-file-earmark-excel me-1"></i>Resumen Excel</a>
        <a class="btn btn-outline-warning" href="<?= e(route_url('evaluation-surveys.dashboard.integrity')) ?>"><i class="bi bi-shield-exclamation me-1"></i>Reporte de incidencias</a>
        <a class="btn btn-outline-primary" href="<?= e(route_url('evaluation-surveys.assessments')) ?>"><i class="bi bi-clipboard2-check me-1"></i>Evaluaciones</a>
        <a class="btn btn-outline-secondary" href="<?= e(route_url('dashboard')) ?>"><i class="bi bi-house me-1"></i>Inicio</a>
    </div>
</section>

<section class="row g-3 mb-4" aria-label="Resumen de resultados">
    <?php foreach ([
        ['Evaluaciones', 'forms', 'bi-collection', 'primary'],
        ['Personas que contestaron', 'answered_people', 'bi-people', 'info'],
        ['Finalizadas', 'finished', 'bi-check2-circle', 'success'],
        ['Aprobadas', 'approved', 'bi-patch-check', 'success'],
        ['Reprobadas', 'failed', 'bi-x-circle', 'danger'],
    ] as [$label, $key, $icon, $color]): ?>
        <div class="col-12 col-sm-6 col-xl">
            <div class="content-panel h-100">
                <div class="d-flex align-items-center gap-3">
                    <span class="rounded-circle text-bg-<?= e($color) ?> p-2"><i class="bi <?= e($icon) ?>" aria-hidden="true"></i></span>
                    <div><span class="text-muted small d-block"><?= e($label) ?></span><strong class="fs-4"><?= (int) ($summary[$key] ?? 0) ?></strong></div>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
    <div class="col-12 col-sm-6 col-xl">
        <div class="content-panel h-100">
            <span class="text-muted small d-block">Tasa de aprobación</span>
            <strong class="fs-4"><?= $number($summary['approval_rate'] ?? 0) ?>%</strong>
            <div class="progress mt-2" role="progressbar" aria-label="Tasa de aprobación" aria-valuenow="<?= (float) ($summary['approval_rate'] ?? 0) ?>" aria-valuemin="0" aria-valuemax="100">
                <div class="progress-bar bg-success" style="width: <?= min(100, max(0, (float) ($summary['approval_rate'] ?? 0))) ?>%"></div>
            </div>
        </div>
    </div>
</section>

<section class="content-panel mb-4">
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-3">
        <div>
            <h2 class="h5 fw-bold mb-1">Resumen por evaluación</h2>
            <p class="text-muted mb-0">Cada fila representa una evaluación asignada a un proceso; las métricas corresponden a personas. El acceso está limitado a <?= $isCompanyScope ? 'la empresa del Administrador Cliente' : 'las evaluaciones visibles del Administrador General' ?>.</p>
        </div>
        <div class="text-end"><span class="text-muted small d-block">Nota promedio</span><strong><?= $number($summary['average_score'] ?? 0) ?></strong></div>
    </div>
    <?php if (!$rows): ?>
        <div class="alert alert-info mb-0">Todavía no hay evaluaciones calificadas disponibles para este alcance.</div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table align-middle app-table app-data-table" data-export-title="Dashboard de evaluaciones">
                <thead><tr><th>Evaluación</th><th>Proceso</th><th>Estado</th><th>Nota máxima</th><th>Aprobación</th><th>Asignadas</th><th>Contestaron</th><th>Finalizadas</th><th>Aprobadas</th><th>Reprobadas</th><th>Buenas</th><th>Malas</th><th>Omitidas</th><th>Nota promedio</th><th class="text-end no-sort no-export">Acciones</th></tr></thead>
                <tbody>
                <?php foreach ($rows as $row): ?>
                    <?php $finished = max(0, (int) ($row['finished_people'] ?? 0)); $approved = (int) ($row['approved_people'] ?? 0); $failed = (int) ($row['failed_people'] ?? 0); ?>
                    <tr>
                        <td><strong><?= e((string) ($row['title'] ?? 'Evaluación')) ?></strong><div class="text-muted small"><?= $finished > 0 ? $number($row['average_score'] ?? 0) . ' puntos promedio' : 'Sin resultados finalizados' ?></div></td>
                        <td><?= e((string) ($row['process_name'] ?? 'Sin proceso identificado')) ?><?php if (!empty($row['process_code'])): ?><div class="text-muted small"><?= e((string) $row['process_code']) ?></div><?php endif; ?></td>
                        <?php $formStatus = (string) ($row['status'] ?? ''); ?>
                        <td><span class="badge text-bg-<?= $formStatus === 'active' ? 'success' : ($formStatus === 'draft' ? 'warning' : 'secondary') ?>"><?= e($statusLabels[$formStatus] ?? 'Estado no disponible') ?></span></td>
                        <td><?= $number($row['max_score'] ?? 0) ?></td>
                        <td><?= $row['passing_score'] === null ? '—' : $number($row['passing_score']) . ' (' . $number($row['passing_percentage'] ?? 0) . '%)' ?></td>
                        <td><?= (int) ($row['assigned_people'] ?? 0) ?></td>
                        <td><?= (int) ($row['answered_people'] ?? 0) ?></td>
                        <td><?= $finished ?></td>
                        <td><span class="badge text-bg-success"><?= $approved ?></span></td>
                        <td><span class="badge text-bg-danger"><?= $failed ?></span></td>
                        <td><?= (int) ($row['correct_answers'] ?? 0) ?></td>
                        <td><?= (int) ($row['incorrect_answers'] ?? 0) ?></td>
                        <td><?= (int) ($row['unanswered_answers'] ?? 0) ?></td>
                        <td><?= $finished > 0 ? $number($row['average_score'] ?? 0) : '—' ?></td>
                        <?php $resultsUrl = route_url('evaluation-surveys.dashboard.results', (int) $row['id']) . (!empty($row['process_id']) ? '?process_id=' . (int) $row['process_id'] : ''); ?>
                        <td class="text-end">
                            <?php if ($finished > 0): ?>
                                <a class="btn btn-sm btn-outline-primary" href="<?= e($resultsUrl) ?>"><i class="bi bi-people me-1"></i>Detalle por persona</a>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>

<section class="content-panel">
    <h2 class="h5 fw-bold mb-2">Criterio de cálculo</h2>
    <p class="text-muted mb-0">APROBADO cuando la nota obtenida es mayor o igual a la nota de aprobación configurada; REPROBADO cuando es menor. Las evaluaciones sin nota de aprobación quedan visibles, pero no se cuentan como aprobadas ni reprobadas.</p>
</section>
