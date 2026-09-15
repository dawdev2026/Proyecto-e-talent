<?php
$ranking = $ranking ?? ['rows' => [], 'warnings' => []];
$rows = is_array($ranking['rows'] ?? null) ? $ranking['rows'] : [];
$warnings = is_array($ranking['warnings'] ?? null) ? $ranking['warnings'] : [];
$dashboardFields = $dashboardFields ?? [];
$dashboardFilters = $dashboardFilters ?? ['fields' => []];
$dashboardOptions = $dashboardOptions ?? ['field_options' => []];
$dashboardFieldFilters = is_array($dashboardFilters['fields'] ?? null) ? $dashboardFilters['fields'] : [];
$hasFinishedSessions = (bool) ($hasFinishedSessions ?? false);
$progressUrl = route_url('tests.progress');
$progressExportUrl = route_url('tests.progress-export');
$progressQuery = http_build_query(['fields' => $dashboardFieldFilters]);
if ($progressQuery !== '') {
    $progressUrl .= '?' . $progressQuery;
    $progressExportUrl .= '?' . $progressQuery;
}

$classificationCounts = is_array($classificationCounts ?? null) ? $classificationCounts : ['R' => 0, 'RO' => 0, 'NR' => 0];
$rankingConfig = is_array($rankingConfig ?? null) ? $rankingConfig : [];
$rankingUsesOfficialConfig = (bool) ($rankingUsesOfficialConfig ?? false);

$rankingHeaders = [
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
];
?>

<section class="page-header">
    <div>
        <p class="text-uppercase text-primary fw-bold small mb-1">Evaluaciones</p>
        <h1 class="fw-bold mb-1">Ranking Resumen</h1>
        <p class="text-muted mb-0">Vista calculada con las mismas reglas de la hoja Ranking Resumen del Excel.</p>
    </div>
    <div class="d-flex flex-wrap gap-2">
        <a class="btn btn-outline-secondary" href="<?= e($progressUrl) ?>"><i class="bi bi-arrow-left me-1"></i> Estado Avance</a>
        <a
            class="btn btn-success <?= $hasFinishedSessions ? '' : 'disabled' ?>"
            href="<?= $hasFinishedSessions ? e($progressExportUrl) : '#' ?>"
            data-confirm-link="El Resumen General incluira la hoja Ranking Resumen. Los usuarios sin resultados completos de IPIP-16PF y CAG no entraran al ranking y quedaran informados como advertencia en el Excel. Deseas generar el archivo?"
            data-confirm-title="Generar Resumen General"
            data-confirm-button="Generar Excel"
            <?= $hasFinishedSessions ? '' : 'aria-disabled="true" tabindex="-1" title="No hay evaluaciones terminadas para exportar"' ?>
        >
            <i class="bi bi-file-earmark-excel me-1"></i> Resumen General
        </a>
    </div>
</section>

<section class="card content-panel evaluation-dashboard mb-4">
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-3">
        <div>
            <h2 class="h5 fw-bold mb-1">Filtros</h2>
            <p class="text-muted mb-0">Aplica los mismos filtros de Estado Avance al ranking visible.</p>
        </div>
        <span class="badge text-bg-primary"><?= count($rows) ?> rankeados</span>
    </div>

    <form class="evaluation-dashboard-filters" method="get" action="<?= e(route_url('tests.progress-ranking')) ?>">
        <div class="row g-3 align-items-end">
            <?php foreach ($dashboardFields as $field): ?>
                <?php
                    $fieldKey = (string) $field['field_key'];
                    $fieldValue = (string) ($dashboardFieldFilters[$fieldKey] ?? '');
                    $fieldOptions = $dashboardOptions['field_options'][$fieldKey] ?? [];
                ?>
                <div class="col-12 col-md-6 col-lg-3">
                    <label class="form-label" for="ranking_field_<?= e($fieldKey) ?>"><?= e($field['label']) ?></label>
                    <?php if ($fieldOptions): ?>
                        <select id="ranking_field_<?= e($fieldKey) ?>" class="form-select" name="fields[<?= e($fieldKey) ?>]">
                            <option value="">Todos</option>
                            <?php foreach ($fieldOptions as $option): ?>
                                <option value="<?= e($option) ?>" <?= $fieldValue === (string) $option ? 'selected' : '' ?>><?= e($option) ?></option>
                            <?php endforeach; ?>
                        </select>
                    <?php else: ?>
                        <input id="ranking_field_<?= e($fieldKey) ?>" class="form-control" name="fields[<?= e($fieldKey) ?>]" value="<?= e($fieldValue) ?>" placeholder="Filtrar <?= e(strtolower((string) $field['label'])) ?>">
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>

            <div class="col-12 col-md-6 col-lg-3 d-flex gap-2">
                <button class="btn btn-primary flex-fill" type="submit"><i class="bi bi-funnel me-1"></i> Filtrar</button>
                <a class="btn btn-outline-secondary" href="<?= e(route_url('tests.progress-ranking')) ?>" aria-label="Limpiar filtros"><i class="bi bi-eraser"></i></a>
            </div>
        </div>

        <?php if (!$dashboardFields): ?>
            <p class="text-muted small mb-0 mt-3">No hay campos extra activos para filtrar.</p>
        <?php endif; ?>
    </form>
</section>

<?php
$rankingFormAction = route_url('tests.progress-ranking');
require __DIR__ . '/partials/ranking_config_form.php';
?>

<section class="row g-3 mb-4">
    <div class="col-12 col-md-6 col-xl">
        <div class="card content-panel h-100">
            <p class="text-muted small fw-bold text-uppercase mb-1">Ranking</p>
            <p class="display-6 fw-bold mb-0"><?= count($rows) ?></p>
        </div>
    </div>
    <div class="col-12 col-md-6 col-xl">
        <div class="card content-panel h-100">
            <p class="text-muted small fw-bold text-uppercase mb-1">Recomendado</p>
            <p class="display-6 fw-bold mb-0"><?= (int) ($classificationCounts['R'] ?? 0) ?></p>
        </div>
    </div>
    <div class="col-12 col-md-6 col-xl">
        <div class="card content-panel h-100">
            <p class="text-muted small fw-bold text-uppercase mb-1">Observacion</p>
            <p class="display-6 fw-bold mb-0"><?= (int) ($classificationCounts['RO'] ?? 0) ?></p>
        </div>
    </div>
    <div class="col-12 col-md-6 col-xl">
        <div class="card content-panel h-100">
            <p class="text-muted small fw-bold text-uppercase mb-1">No Recomendado</p>
            <p class="display-6 fw-bold mb-0"><?= (int) ($classificationCounts['NR'] ?? 0) ?></p>
        </div>
    </div>
    <div class="col-12 col-md-6 col-xl">
        <div class="card content-panel h-100">
            <p class="text-muted small fw-bold text-uppercase mb-1">Advertencias</p>
            <p class="display-6 fw-bold mb-0"><?= count($warnings) ?></p>
        </div>
    </div>
</section>

<?php if ($warnings): ?>
    <div class="alert alert-warning">
        <strong>Datos insuficientes:</strong> los usuarios sin IPIP-16PF o CAG completo no entran al ranking. Quedan listados al final como advertencia.
    </div>
<?php endif; ?>

<section class="card content-panel">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <div>
            <h2 class="h5 fw-bold mb-1">Ranking Resumen</h2>
            <p class="text-muted mb-0">Ordenado por Puntaje Final, de mayor a menor.</p>
        </div>
        <span class="badge text-bg-light border"><?= count($rows) ?> registros</span>
    </div>

    <?php if ($rows): ?>
        <div class="table-responsive">
            <table class="table table-hover align-middle app-table app-data-table" data-export-title="Ranking Resumen">
                <thead>
                    <tr>
                        <?php foreach ($rankingHeaders as $header): ?>
                            <th><?= e($header) ?></th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rows as $row): ?>
                        <tr>
                            <?php foreach ($rankingHeaders as $header): ?>
                                <td>
                                    <?php if ($header === 'Clasificacion'): ?>
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
        <div class="alert alert-info mb-0">No hay usuarios con IPIP-16PF y CAG completos para mostrar en el ranking.</div>
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
            <table class="table table-hover align-middle app-table app-data-table" data-export-title="Advertencias Ranking">
                <thead>
                    <tr>
                        <th>RUT</th>
                        <th>Nombre completo</th>
                        <th>Observacion</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($warnings as $warning): ?>
                        <tr>
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
