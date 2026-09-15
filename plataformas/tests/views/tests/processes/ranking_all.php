<?php
$ranking = $ranking ?? ['rows' => [], 'warnings' => []];
$rows = is_array($ranking['rows'] ?? null) ? $ranking['rows'] : [];
$warnings = is_array($ranking['warnings'] ?? null) ? $ranking['warnings'] : [];
$processesTotal = (int) ($processesTotal ?? 0);
$usersTotal = (int) ($usersTotal ?? 0);
$sessionsTotal = (int) ($sessionsTotal ?? 0);
$classificationCounts = is_array($classificationCounts ?? null) ? $classificationCounts : ['R' => 0, 'RO' => 0, 'NR' => 0];
$rankingUsesOfficialConfig = (bool) ($rankingUsesOfficialConfig ?? false);
$dashboardQueryString = (string) ($dashboardQueryString ?? '');
$dashboardBackUrl = route_url('test-process.dashboard') . ($dashboardQueryString !== '' ? '?' . $dashboardQueryString : '');
$reportsZipUrl = route_url('test-process.ranking-all-reports-zip') . ($dashboardQueryString !== '' ? '?' . $dashboardQueryString : '');
$rankingReportsByCompany = is_array($rankingReportsByCompany ?? null) ? $rankingReportsByCompany : [];

$rankingHeaders = [
    'Proceso',
    'Ranking',
    'RUT',
    'Nombre completo',
    'Sten Wonderlic',
    'Sten Calma',
    'D1 Estabilidad',
    'D2 Sociales',
    'D3 Rendimiento',
    'D4 Vocacion',
    'D5 Ajuste',
    'Puntaje Total',
    'Knockout Activado',
    'Detalle Knockout',
    'Puntaje Final',
    'Clasificacion',
    'Nomenclatura Reporte',
    'Impulsividad Total TICL',
    'Acciones',
];
?>

<section class="page-header" data-page-back-url="<?= e($dashboardBackUrl) ?>">
    <div>
        <p class="text-uppercase text-primary fw-bold small mb-1">Procesos</p>
        <h1 class="fw-bold mb-1">Ranking Completo</h1>
        <p class="text-muted mb-0">Consolida todos los procesos con permiso de ranking y ordena por Puntaje Final.</p>
    </div>
    <div class="d-flex flex-wrap gap-2">
        <a class="btn btn-outline-secondary" href="<?= e($dashboardBackUrl) ?>"><i class="bi bi-arrow-left me-1"></i> Volver al dashboard</a>
        <a class="btn btn-outline-primary" href="<?= e(route_url('test-processes')) ?>"><i class="bi bi-kanban me-1"></i> Procesos</a>
    </div>
</section>

<section class="row g-3 mb-4">
    <div class="col-12 col-md-3">
        <div class="card content-panel h-100">
            <p class="text-muted small fw-bold text-uppercase mb-1">Procesos</p>
            <p class="display-6 fw-bold mb-0"><?= $processesTotal ?></p>
        </div>
    </div>
    <div class="col-12 col-md-3">
        <div class="card content-panel h-100">
            <p class="text-muted small fw-bold text-uppercase mb-1">Usuarios</p>
            <p class="display-6 fw-bold mb-0"><?= $usersTotal ?></p>
        </div>
    </div>
    <div class="col-12 col-md-3">
        <div class="card content-panel h-100">
            <p class="text-muted small fw-bold text-uppercase mb-1">Sesiones</p>
            <p class="display-6 fw-bold mb-0"><?= $sessionsTotal ?></p>
        </div>
    </div>
    <div class="col-12 col-md-3">
        <div class="card content-panel h-100">
            <p class="text-muted small fw-bold text-uppercase mb-1">Ranqueados</p>
            <p class="display-6 fw-bold mb-0"><?= count($rows) ?></p>
        </div>
    </div>
</section>

<section class="row g-3 mb-4">
    <div class="col-12 col-md-4">
        <div class="card content-panel h-100">
            <p class="text-muted small fw-bold text-uppercase mb-1">Recomendado</p>
            <p class="h2 fw-bold mb-0"><?= (int) ($classificationCounts['R'] ?? 0) ?></p>
        </div>
    </div>
    <div class="col-12 col-md-4">
        <div class="card content-panel h-100">
            <p class="text-muted small fw-bold text-uppercase mb-1">Observacion</p>
            <p class="h2 fw-bold mb-0"><?= (int) ($classificationCounts['RO'] ?? 0) ?></p>
        </div>
    </div>
    <div class="col-12 col-md-4">
        <div class="card content-panel h-100">
            <p class="text-muted small fw-bold text-uppercase mb-1">No Recomendado</p>
            <p class="h2 fw-bold mb-0"><?= (int) ($classificationCounts['NR'] ?? 0) ?></p>
        </div>
    </div>
</section>

<?php if ($warnings): ?>
    <div class="alert alert-warning">
        <strong>Datos insuficientes:</strong> las personas sin IPIP-16PF o CAG completo no entran al ranking. Quedan listadas al final como advertencia.
    </div>
<?php endif; ?>

<section class="card content-panel">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <div>
            <h2 class="h5 fw-bold mb-1">Ranking completo de procesos</h2>
            <p class="text-muted mb-0">La primera columna indica el proceso al que pertenece cada candidato.</p>
        </div>
        <div class="d-flex flex-wrap justify-content-end align-items-center gap-2">
            <span class="badge <?= $rankingUsesOfficialConfig ? 'text-bg-success' : 'text-bg-warning' ?>">
                <?= $rankingUsesOfficialConfig ? 'Matriz oficial' : 'Ranking personalizado' ?>
            </span>
            <?php if ($rows): ?>
                <a
                    class="btn btn-outline-primary"
                    href="<?= e($reportsZipUrl . (strpos($reportsZipUrl, '?') === false ? '?' : '&') . 'prepare=1') ?>"
                    data-download-processing
                    data-processing-message="Generando informes ZIP..."
                    data-processing-detail="Preparando PDFs y comprimiendo el archivo.">
                    <i class="bi bi-file-earmark-zip me-1"></i> Descargar informes ZIP
                </a>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($rows): ?>
        <div class="table-responsive">
            <table class="table table-hover align-middle app-table app-data-table" data-export-title="Ranking Completo Procesos">
                <thead>
                    <tr>
                        <?php foreach ($rankingHeaders as $header): ?>
                            <th class="<?= $header === 'Acciones' ? 'no-sort no-export text-end' : '' ?>"><?= e($header) ?></th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rows as $row): ?>
                        <tr>
                            <?php foreach ($rankingHeaders as $header): ?>
                                <td>
                                    <?php if ($header === 'Proceso'): ?>
                                        <div class="fw-semibold"><?= e((string) ($row[$header] ?? '')) ?></div>
                                        <?php if ((string) ($row['Codigo proceso'] ?? '') !== ''): ?>
                                            <div class="text-muted small"><code><?= e((string) $row['Codigo proceso']) ?></code></div>
                                        <?php endif; ?>
                                    <?php elseif ($header === 'Clasificacion'): ?>
                                        <?php
                                            $classification = (string) ($row[$header] ?? '');
                                            $badgeClass = strpos($classification, 'Recomendado con') !== false
                                                ? 'text-bg-warning'
                                                : (strpos($classification, 'Recomendado') !== false
                                                ? 'text-bg-success'
                                                : 'text-bg-secondary');
                                        ?>
                                        <span class="badge <?= e($badgeClass) ?>"><?= e($classification) ?></span>
                                    <?php elseif ($header === 'Knockout Activado'): ?>
                                        <?php $knockout = (string) ($row[$header] ?? 'NO'); ?>
                                        <span class="badge <?= $knockout === 'SI' ? 'text-bg-danger' : 'text-bg-light border' ?>"><?= e($knockout) ?></span>
                                    <?php elseif ($header === 'Acciones'): ?>
                                        <?php
                                            $reportProcessId = (int) ($row['_process_id'] ?? 0);
                                            $reportSessionId = (int) ($row['_report_session_id'] ?? 0);
                                            $reportUrl = $reportProcessId > 0 && $reportSessionId > 0
                                                ? route_url('test-process.ranking-report', $reportProcessId) . '?session=' . rawurlencode(secure_url_token($reportSessionId, 'test_session'))
                                                : '';
                                            $reportUserId = (int) ($row['_user_id'] ?? 0);
                                            $reportCompanyId = (int) ($row['_company_id'] ?? 0);
                                            $registeredReports = $rankingReportsByCompany[$reportCompanyId] ?? [];
                                        ?>
                                        <div class="text-end">
                                            <?php if ($reportUrl !== ''): ?>
                                                <?php if (count($registeredReports) === 1 && $reportUserId > 0): ?>
                                                    <?php $registeredReport = $registeredReports[0]; ?>
                                                    <a class="btn btn-sm btn-outline-primary" href="<?= e(route_url('reports.run', (int) $registeredReport['id']) . '?company_id=' . $reportCompanyId . '&process_id=' . $reportProcessId . '&user_id=' . $reportUserId . '&format=pdf') ?>" title="Descargar informe registrado"><i class="bi bi-file-earmark-pdf me-1"></i> <?= e((string) $registeredReport['name']) ?></a>
                                                <?php elseif (count($registeredReports) > 1 && $reportUserId > 0): ?>
                                                    <div class="dropdown"><button class="btn btn-sm btn-outline-primary dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false"><i class="bi bi-file-earmark-pdf me-1"></i> Informes</button><ul class="dropdown-menu dropdown-menu-end"><?php foreach ($registeredReports as $registeredReport): ?><li><a class="dropdown-item" href="<?= e(route_url('reports.run', (int) $registeredReport['id']) . '?company_id=' . $reportCompanyId . '&process_id=' . $reportProcessId . '&user_id=' . $reportUserId . '&format=pdf') ?>"><i class="bi bi-file-earmark-pdf me-2"></i><?= e((string) $registeredReport['name']) ?> <span class="text-muted small">v<?= e((string) $registeredReport['version']) ?></span></a></li><?php endforeach; ?></ul></div>
                                                <?php else: ?>
                                                    <a class="btn btn-sm btn-outline-primary" href="<?= e($reportUrl) ?>" title="Descargar informe estándar"><i class="bi bi-file-earmark-pdf me-1"></i> Informe estándar</a>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <span class="text-muted small">Sin informe</span>
                                            <?php endif; ?>
                                        </div>
                                    <?php else: ?>
                                        <?= e((string) ($row[$header] ?? '')) ?>
                                    <?php endif; ?>
                                </td>
                            <?php endforeach; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <div class="alert alert-info mb-0">No hay usuarios con IPIP-16PF y CAG completos para mostrar en el ranking completo.</div>
    <?php endif; ?>
</section>

<?php if ($warnings): ?>
    <section class="card content-panel mt-4">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
            <div>
                <h2 class="h5 fw-bold mb-1">Advertencias</h2>
                <p class="text-muted mb-0">Personas excluidas del ranking por falta de datos requeridos.</p>
            </div>
            <span class="badge text-bg-warning"><?= count($warnings) ?> advertencias</span>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle app-table app-data-table" data-export-title="Advertencias Ranking Completo">
                <thead>
                    <tr>
                        <th>Proceso</th>
                        <th>RUT</th>
                        <th>Nombre completo</th>
                        <th>Observacion</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($warnings as $warning): ?>
                        <tr>
                            <td>
                                <div class="fw-semibold"><?= e((string) ($warning['Proceso'] ?? '')) ?></div>
                                <?php if ((string) ($warning['Codigo proceso'] ?? '') !== ''): ?>
                                    <div class="text-muted small"><code><?= e((string) $warning['Codigo proceso']) ?></code></div>
                                <?php endif; ?>
                            </td>
                            <td><?= e((string) ($warning['rut'] ?? '')) ?></td>
                            <td><?= e((string) ($warning['name'] ?? '')) ?></td>
                            <td><?= e((string) ($warning['warning'] ?? '')) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>
<?php endif; ?>
