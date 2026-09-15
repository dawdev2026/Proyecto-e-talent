<?php
$processes = $processes ?? [];
$processRows = $processRows ?? [];
$overall = $overall ?? ['users_total' => 0, 'evaluated' => 0, 'in_progress' => 0, 'pending' => 0, 'sessions_total' => 0, 'sessions_finished' => 0];
$trend = $trend ?? [];
$filters = $filters ?? ['process_id' => 0, 'fields' => []];
$dashboardFields = $dashboardFields ?? [];
$dashboardFieldOptions = $dashboardFieldOptions ?? [];
$dashboardFieldFilters = is_array($filters['fields'] ?? null) ? $filters['fields'] : [];
$dashboardAsyncShell = !empty($dashboardAsyncShell);
$dashboardContentOnly = !empty($dashboardContentOnly);
$dashboardSkipScripts = !empty($dashboardSkipScripts);
$dashboardDataUrl = (string) ($dashboardDataUrl ?? '');
$dashboardIsGlobalAdmin = (bool) ($dashboardIsGlobalAdmin ?? false);
$dashboardCompanies = is_array($dashboardCompanies ?? null) ? $dashboardCompanies : [];
$dashboardAvailableProcesses = is_array($dashboardAvailableProcesses ?? null) ? $dashboardAvailableProcesses : [];
$dashboardCompanyId = (int) ($dashboardCompanyId ?? 0);
$dashboardSelectedProcessIds = array_values(array_filter(array_map('intval', is_array($dashboardSelectedProcessIds ?? null) ? $dashboardSelectedProcessIds : [])));
$duplicateAssignments = is_array($duplicateAssignments ?? null) ? $duplicateAssignments : ['total' => 0, 'examples' => []];
$duplicateAssignmentsTotal = (int) ($duplicateAssignments['total'] ?? 0);
$duplicateAssignmentExamples = is_array($duplicateAssignments['examples'] ?? null) ? $duplicateAssignments['examples'] : [];
$usersTotal = max(0, (int) ($overall['users_total'] ?? 0));
$evaluated = max(0, (int) ($overall['evaluated'] ?? 0));
$inProgress = max(0, (int) ($overall['in_progress'] ?? 0));
$pending = max(0, (int) ($overall['pending'] ?? 0));
$rankingRecommended = max(0, (int) ($overall['ranking_recommended'] ?? 0));
$rankingObservation = max(0, (int) ($overall['ranking_observation'] ?? 0));
$rankingNotRecommended = max(0, (int) ($overall['ranking_not_recommended'] ?? 0));
$rankingKnockouts = max(0, (int) ($overall['ranking_knockouts'] ?? 0));
$rankingWarnings = max(0, (int) ($overall['ranking_warnings'] ?? 0));
$rankingRanked = max(0, (int) ($overall['ranking_ranked'] ?? 0));
$rankingEvaluableTotal = $rankingRanked + $rankingWarnings;
$rankingRecommendedPercent = $rankingRanked > 0 ? ($rankingRecommended / $rankingRanked) * 100 : 0;
$rankingObservationPercent = $rankingRanked > 0 ? ($rankingObservation / $rankingRanked) * 100 : 0;
$rankingNotRecommendedPercent = $rankingRanked > 0 ? ($rankingNotRecommended / $rankingRanked) * 100 : 0;
$rankingWarningsPercent = $rankingEvaluableTotal > 0 ? ($rankingWarnings / $rankingEvaluableTotal) * 100 : 0;
$rankingUsesOfficialConfig = (bool) ($rankingUsesOfficialConfig ?? false);
$rankingIsPreviewConfig = (bool) ($rankingIsPreviewConfig ?? false);
$rankingScope = (string) ($rankingScope ?? 'filtered');
$rankingConfig = is_array($rankingConfig ?? null) ? $rankingConfig : [];
$rankingClassification = is_array($rankingConfig['classification'] ?? null) ? $rankingConfig['classification'] : [];
$rankingDimensions = is_array($rankingConfig['dimensions'] ?? null) ? $rankingConfig['dimensions'] : [];
$rankingKnockoutRules = is_array($rankingConfig['knockouts'] ?? null) ? $rankingConfig['knockouts'] : [];
$rankingPresets = is_array($rankingPresets ?? null) ? $rankingPresets : [];
$rankingSelectedPresetId = (int) ($rankingSelectedPresetId ?? -1);
$canManageRankingPresets = (bool) ($canManageRankingPresets ?? false);
$canConfigureRanking = (bool) ($canConfigureRanking ?? false);
$rankingCompanyAssignment = is_array($rankingCompanyAssignment ?? null) ? $rankingCompanyAssignment : null;
$rankingPresetsStorageReady = (bool) ($rankingPresetsStorageReady ?? false);
$canViewCompleteRanking = (bool) ($canViewCompleteRanking ?? false);
$rankingSelectedPresetName = '';
foreach ($rankingPresets as $rankingPresetOption) {
    if ((int) ($rankingPresetOption['id'] ?? -1) === $rankingSelectedPresetId) {
        $rankingSelectedPresetName = (string) ($rankingPresetOption['name'] ?? '');
        break;
    }
}
$dashboardQueryString = (string) ($_SERVER['QUERY_STRING'] ?? '');
$dashboardWarningsUrl = route_url('test-process.dashboard-warnings') . ($dashboardQueryString !== '' ? '?' . $dashboardQueryString : '');
$rankingCompleteQuery = $_GET;
unset($rankingCompleteQuery['process_id']);
$rankingCompleteQueryString = http_build_query($rankingCompleteQuery);
$rankingCompleteUrl = route_url('test-process.ranking-all') . ($rankingCompleteQueryString !== '' ? '?' . $rankingCompleteQueryString : '');
$rankingPresetRedirectTo = route_url('test-process.dashboard') . ($dashboardQueryString !== '' ? '?' . $dashboardQueryString : '');
$progressPercent = $usersTotal > 0 ? (int) round(($evaluated / $usersTotal) * 100) : 0;
$donutEvaluated = $usersTotal > 0 ? ($evaluated / $usersTotal) * 100 : 0;
$donutInProgress = $usersTotal > 0 ? ($inProgress / $usersTotal) * 100 : 0;
$donutPending = max(0, 100 - $donutEvaluated - $donutInProgress);
$trendMax = 1;
foreach ($trend as $point) {
    $trendMax = max($trendMax, (int) ($point['count'] ?? 0));
}
$chartData = $chartData ?? null;
if (!is_array($chartData)) {
    $chartRows = array_slice($processRows, 0, 8);
    $chartData = [
        'processLabels' => array_map(static fn(array $row): string => (string) ($row['name'] ?? 'Proceso'), $chartRows),
        'evaluated' => array_map(static fn(array $row): int => (int) ($row['evaluated'] ?? 0), $chartRows),
        'inProgress' => array_map(static fn(array $row): int => (int) ($row['in_progress'] ?? 0), $chartRows),
        'pending' => array_map(static fn(array $row): int => (int) ($row['pending'] ?? 0), $chartRows),
        'trendLabels' => array_map(static fn(array $point): string => (string) ($point['label'] ?? ''), $trend),
        'trendCounts' => array_map(static fn(array $point): int => (int) ($point['count'] ?? 0), $trend),
        'distribution' => [
            'evaluated' => $evaluated,
            'inProgress' => $inProgress,
            'pending' => $pending,
        ],
    ];
}
$dashboardMetricHelp = [
    'users_total' => 'Personas asignadas en los procesos visibles del dashboard. La regla esperada es que cada persona este activa en un solo proceso.',
    'evaluated' => 'Personas que tienen todos los test del proceso finalizados en estado Completada o Expirada.',
    'in_progress' => 'Personas con al menos un test en estado En curso, Completada o Expirada, pero que aun no tienen todos los test del proceso finalizados.',
    'pending' => 'Personas sin avance en los test del proceso; no tienen evaluaciones En curso, Completadas ni Expiradas.',
    'ranking' => 'Resultados generales calculados con la configuracion activa del ranking. R: Recomendado, RO: Recomendado con observacion, NR: No recomendado.',
    'supervision' => implode('<br>', [
        '<strong>Eventos:</strong> total de eventos registrados durante las evaluaciones supervisadas.',
        '<strong>Atencion:</strong> eventos que requieren revision por posibles distracciones o perdida de foco.',
        '<strong>Riesgo:</strong> eventos de mayor criticidad que pueden afectar la validez de la evaluacion.',
    ]),
];

if (!function_exists('process_dashboard_help_header')) {
    function process_dashboard_help_header(string $label, string $help, bool $html = false): string
    {
        $htmlAttr = $html ? ' data-bs-html="true"' : '';

        return '<span class="d-inline-flex align-items-center gap-1">'
            . e($label)
            . '<button class="btn btn-sm btn-link p-0 text-muted" type="button" data-bs-toggle="popover" data-bs-trigger="focus" data-bs-placement="bottom" data-bs-title="'
            . e($label)
            . '" data-bs-content="'
            . e($help)
            . '"'
            . $htmlAttr
            . ' aria-label="Ver explicacion de '
            . e($label)
            . '"><i class="bi bi-info-circle"></i></button>'
            . '</span>';
    }
}

if (!function_exists('process_dashboard_help_label')) {
    function process_dashboard_help_label(string $label, string $help): string
    {
        return '<span class="d-inline-flex align-items-center gap-1">'
            . e($label)
            . '<button class="btn btn-sm btn-link p-0 text-muted" type="button" data-bs-toggle="popover" data-bs-trigger="focus" data-bs-placement="bottom" data-bs-title="'
            . e($label)
            . '" data-bs-content="'
            . e($help)
            . '" aria-label="Ver explicacion de '
            . e($label)
            . '"><i class="bi bi-info-circle"></i></button>'
            . '</span>';
    }
}
?>

<?php if ($dashboardAsyncShell): ?>
<section class="page-header">
    <div>
        <p class="text-uppercase text-primary fw-bold small mb-1">Evaluaciones</p>
        <h1 class="fw-bold mb-1">Dashboard de Avance</h1>
        <p class="text-muted mb-0">Seguimiento general y por proceso de evaluaciones.</p>
    </div>
    <div class="d-flex flex-wrap gap-2">
        <a class="btn btn-outline-secondary" href="<?= e(route_url('test-processes')) ?>"><i class="bi bi-kanban me-1"></i> Procesos</a>
        <button class="btn btn-primary" type="button" data-process-dashboard-refresh><i class="bi bi-arrow-clockwise me-1"></i> Actualizar</button>
    </div>
</section>

<section class="card content-panel mb-4" data-process-dashboard-loading>
    <div class="d-flex align-items-start gap-3">
        <div class="spinner-border text-primary flex-shrink-0" role="status" aria-hidden="true"></div>
        <div>
            <h2 class="h5 fw-bold mb-1">Procesando informacion</h2>
            <p class="text-muted mb-0">Estamos calculando avance, filtros, graficos y eventos de supervision. La pagina ya esta cargada; los datos apareceran aqui apenas esten listos.</p>
        </div>
    </div>
</section>

<section class="card content-panel process-dashboard-filters mb-4" data-dashboard-selection-panel>
    <div class="d-flex flex-wrap align-items-start justify-content-between gap-3 mb-3">
        <div>
            <h2 class="h5 fw-bold mb-1">Selecciona qué quieres visualizar</h2>
            <p class="text-muted mb-0">Primero elige una empresa y uno o más procesos para cargar el dashboard.</p>
        </div>
        <span class="badge text-bg-light border" data-dashboard-selection-summary>Sin selección</span>
    </div>
    <form class="row g-3 align-items-end" method="get" action="<?= e(route_url('test-process.dashboard')) ?>" data-dashboard-filter-form>
        <?php if ($dashboardIsGlobalAdmin): ?>
            <div class="col-12 col-lg-4">
                <label class="form-label" for="dashboard_company_id">Empresa</label>
                <select id="dashboard_company_id" class="form-select" name="company_id" required data-dashboard-company>
                    <option value="0">Selecciona una empresa</option>
                    <?php foreach ($dashboardCompanies as $company): ?>
                        <?php $companyOptionId = (int) ($company['id'] ?? 0); ?>
                        <option value="<?= $companyOptionId ?>" <?= $dashboardCompanyId === $companyOptionId ? 'selected' : '' ?>><?= e((string) ($company['name'] ?? 'Empresa')) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        <?php else: ?>
            <input type="hidden" name="company_id" value="<?= $dashboardCompanyId ?>">
        <?php endif; ?>
        <div class="col-12 col-lg-6">
            <label class="form-label" for="dashboard_process_picker">Procesos</label>
            <div class="input-group">
                <button id="dashboard_process_picker" class="form-select text-start" type="button" data-dashboard-process-picker <?= $dashboardIsGlobalAdmin && $dashboardCompanyId <= 0 ? 'disabled' : '' ?>>Seleccionar procesos</button>
                <span class="input-group-text" data-dashboard-process-count>0 seleccionados</span>
            </div>
            <div class="small text-muted mt-1">Puedes seleccionar varios procesos en la grilla.</div>
        </div>
        <div class="col-12 col-lg-2">
            <button class="btn btn-primary w-100" type="submit" data-dashboard-apply>Aplicar</button>
        </div>
        <div data-dashboard-process-inputs>
            <?php foreach ($dashboardSelectedProcessIds as $selectedProcessId): ?>
                <input type="hidden" name="process_ids[]" value="<?= $selectedProcessId ?>">
            <?php endforeach; ?>
        </div>
    </form>
</section>

<template id="dashboardProcessPickerTemplate">
    <div class="mb-3">
        <p class="text-muted mb-0">Marca los procesos que quieres incluir en el cálculo.</p>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle app-table app-data-table" data-export-excel="false" data-export-pdf="false" data-page-length="10" data-searching="true">
            <thead><tr><th class="no-sort" style="width: 94px;"><span class="d-inline-flex align-items-center gap-2"><input class="form-check-input mt-0" type="checkbox" data-dashboard-process-select-all aria-label="Seleccionar todos los procesos"><span>Incluir</span></span></th><th>Proceso</th><th>Código</th><th>Estado</th></tr></thead>
            <tbody>
            <?php foreach ($dashboardAvailableProcesses as $process): ?>
                <?php $pickerProcessId = (int) ($process['id'] ?? 0); ?>
                <tr data-dashboard-process-row data-company-id="<?= (int) ($process['company_id'] ?? 0) ?>">
                    <td><input class="form-check-input" type="checkbox" value="<?= $pickerProcessId ?>" data-dashboard-process-checkbox <?= in_array($pickerProcessId, $dashboardSelectedProcessIds, true) ? 'checked' : '' ?> aria-label="Incluir proceso <?= e((string) ($process['name'] ?? 'Proceso')) ?>"></td>
                    <td class="fw-semibold"><?= e((string) ($process['name'] ?? 'Proceso')) ?></td>
                    <td><?= e((string) ($process['code'] ?? '')) ?></td>
                    <td><?= e((string) ($process['status'] ?? '')) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</template>

<section class="alert alert-danger d-none" role="alert" data-inline-alert data-process-dashboard-error>
    <div class="d-flex align-items-start justify-content-between gap-3">
        <div>
            <strong>No se pudo cargar la informacion del dashboard.</strong>
            <div class="small mt-1" data-process-dashboard-error-message>Intenta actualizar nuevamente.</div>
        </div>
        <button class="btn btn-sm btn-outline-danger" type="button" data-process-dashboard-refresh>Reintentar</button>
    </div>
</section>

<div data-process-dashboard-content></div>
<?php else: ?>

<?php if (!$dashboardContentOnly): ?>
<section class="page-header">
    <div>
        <p class="text-uppercase text-primary fw-bold small mb-1">Evaluaciones</p>
        <h1 class="fw-bold mb-1">Dashboard de Avance</h1>
        <p class="text-muted mb-0">
            Seguimiento general y por proceso de evaluaciones.
            <button
                class="btn btn-link btn-sm p-0 ms-1"
                type="button"
                aria-label="Ayuda sobre metricas de avance"
                data-bs-toggle="popover"
                data-bs-trigger="focus"
                data-bs-placement="bottom"
                data-bs-title="Metricas de avance"
                data-bs-content="Evaluadas: <?= e($dashboardMetricHelp['evaluated']) ?> En evaluacion: <?= e($dashboardMetricHelp['in_progress']) ?> Pendientes: <?= e($dashboardMetricHelp['pending']) ?>"
            >
                <i class="bi bi-info-circle" aria-hidden="true"></i>
            </button>
        </p>
    </div>
    <div class="d-flex flex-wrap gap-2">
        <a class="btn btn-outline-secondary" href="<?= e(route_url('test-processes')) ?>"><i class="bi bi-kanban me-1"></i> Procesos</a>
        <button class="btn btn-primary" type="button" onclick="window.location.reload()"><i class="bi bi-arrow-clockwise me-1"></i> Actualizar</button>
    </div>
</section>
<?php endif; ?>

<?php if ($canConfigureRanking): ?>
<section class="card content-panel process-dashboard-filters mb-4">
    <form method="get" action="<?= e(route_url('test-process.dashboard')) ?>" class="row g-3 align-items-end">
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>" disabled data-ranking-preset-post-field>
        <input type="hidden" name="redirect_to" value="<?= e($rankingPresetRedirectTo) ?>" disabled data-ranking-preset-post-field>
        <div class="col-12 d-flex flex-wrap align-items-start justify-content-between gap-2">
            <div>
                <h2 class="h5 fw-bold mb-1">Filtros y calculo del ranking</h2>
                <p class="text-muted mb-0">Cambia la muestra y simula criterios para recalcular los resultados visibles.</p>
            </div>
            <div class="d-flex flex-wrap gap-2">
                <span class="badge <?= $rankingUsesOfficialConfig ? 'text-bg-success' : 'text-bg-warning' ?>">
                    <?= $rankingUsesOfficialConfig ? 'Matriz oficial' : 'Matriz ajustada' ?>
                </span>
                <?php if ($rankingIsPreviewConfig): ?>
                    <span class="badge text-bg-info">Vista de prueba</span>
                <?php endif; ?>
            </div>
        </div>

        <div class="col-12 col-md-4 col-xl-3">
            <label class="form-label" for="process_id">Proceso</label>
            <select id="process_id" class="form-select" name="process_id">
                <option value="0">Todos</option>
                <?php foreach ($processes as $process): ?>
                    <?php $processId = (int) ($process['id'] ?? 0); ?>
                    <option value="<?= $processId ?>" <?= (int) ($filters['process_id'] ?? 0) === $processId ? 'selected' : '' ?>><?= e((string) ($process['name'] ?? 'Proceso')) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <?php foreach ($dashboardFields as $field): ?>
            <?php
                $fieldKey = (string) ($field['field_key'] ?? '');
                if ($fieldKey === '') {
                    continue;
                }
                $fieldValue = (string) ($dashboardFieldFilters[$fieldKey] ?? '');
                $fieldOptions = $dashboardFieldOptions[$fieldKey] ?? [];
                if (!$fieldOptions) {
                    continue;
                }
            ?>
            <div class="col-12 col-md-4 col-xl-3">
                <label class="form-label" for="process_dashboard_field_<?= e($fieldKey) ?>"><?= e((string) ($field['label'] ?? $fieldKey)) ?></label>
                <select id="process_dashboard_field_<?= e($fieldKey) ?>" class="form-select" name="fields[<?= e($fieldKey) ?>]">
                    <option value="">Todos</option>
                    <?php foreach ($fieldOptions as $option): ?>
                        <option value="<?= e((string) $option) ?>" <?= $fieldValue === (string) $option ? 'selected' : '' ?>><?= e((string) $option) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        <?php endforeach; ?>

        <div class="col-12 col-xl-3 d-flex gap-2">
            <button class="btn btn-primary flex-fill" type="submit"><i class="bi bi-funnel me-1"></i> Aplicar</button>
            <a class="btn btn-outline-secondary" href="<?= e(route_url('test-process.dashboard')) ?>" aria-label="Limpiar filtros"><i class="bi bi-eraser"></i></a>
        </div>

        <div class="col-12">
            <div class="collapse show" id="processDashboardRankingConfig">
                <div class="border rounded p-3">
                    <div class="row g-3 align-items-end mb-3">
                        <div class="col-12 col-lg-5">
                            <label class="form-label" for="dashboard_ranking_preset_id">Configuracion guardada</label>
                            <div class="input-group">
                                <select id="dashboard_ranking_preset_id" class="form-select" name="ranking_preset_id">
                                    <?php foreach ($rankingPresets as $preset): ?>
                                        <?php $presetId = (int) ($preset['id'] ?? 0); ?>
                                        <option value="<?= $presetId ?>" <?= $rankingSelectedPresetId === $presetId ? 'selected' : '' ?>>
                                            <?= e((string) ($preset['name'] ?? 'Configuracion')) ?><?= !empty($preset['is_official']) ? ' · Protegida' : '' ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <button class="btn btn-outline-primary" type="submit" name="ranking_load_preset" value="1"><i class="bi bi-box-arrow-in-down me-1"></i> Cargar</button>
                            </div>
                        </div>
                        <div class="col-12 col-lg-7">
                            <?php if ($canManageRankingPresets): ?>
                                <?php if (!$rankingPresetsStorageReady): ?>
                                    <div class="alert alert-warning mb-0">Para guardar configuraciones, primero aplica la migracion de presets de ranking.</div>
                                <?php else: ?>
                                    <label class="form-label" for="dashboard_ranking_preset_name">Nombre para guardar o actualizar</label>
                                    <input id="dashboard_ranking_preset_name" class="form-control" name="ranking_preset_name" value="<?= e($rankingSelectedPresetId > 0 ? $rankingSelectedPresetName : '') ?>" maxlength="150" placeholder="Ej: Escenario proceso filtrado CAG <= 7">
                                <?php endif; ?>
                            <?php else: ?>
                                <div class="alert alert-light border mb-0">Tu perfil puede cargar configuraciones, pero no guardarlas ni eliminarlas.</div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="row g-3 align-items-end mb-3">
                        <div class="col-12 col-lg-4">
                            <label class="form-label">Alcance del calculo</label>
                            <div class="d-flex flex-column gap-2">
                                <div class="form-check">
                                    <input id="ranking_scope_filtered" class="form-check-input" type="radio" name="ranking_scope" value="filtered" <?= $rankingScope === 'filtered' ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="ranking_scope_filtered">Valores filtrados previamente</label>
                                </div>
                                <div class="form-check">
                                    <input id="ranking_scope_process" class="form-check-input" type="radio" name="ranking_scope" value="process" <?= $rankingScope === 'process' ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="ranking_scope_process">Proceso completo</label>
                                </div>
                            </div>
                        </div>
                        <div class="col-12 col-md-4 col-lg-2">
                            <label class="form-label" for="dashboard_ranking_recommended_min">Umbral R</label>
                            <input id="dashboard_ranking_recommended_min" class="form-control" type="number" step="0.01" min="0" max="100" name="ranking_config[classification][recommended_min]" value="<?= e((string) ($rankingClassification['recommended_min'] ?? 70)) ?>">
                        </div>
                        <div class="col-12 col-md-4 col-lg-2">
                            <label class="form-label" for="dashboard_ranking_observation_min">Umbral RO</label>
                            <input id="dashboard_ranking_observation_min" class="form-control" type="number" step="0.01" min="0" max="100" name="ranking_config[classification][observation_min]" value="<?= e((string) ($rankingClassification['observation_min'] ?? 40)) ?>">
                        </div>
                        <div class="col-12 col-md-4 col-lg-2">
                            <label class="form-label" for="dashboard_ranking_knockout_cap">Tope KO</label>
                            <input id="dashboard_ranking_knockout_cap" class="form-control" type="number" step="0.01" min="0" max="100" name="ranking_config[classification][knockout_score_cap]" value="<?= e((string) ($rankingClassification['knockout_score_cap'] ?? 64)) ?>">
                        </div>
                        <div class="col-12 col-lg-2">
                            <div class="form-check form-switch mb-2">
                                <input id="dashboard_ranking_knockout_force" class="form-check-input" type="checkbox" name="ranking_config[classification][knockout_forces_not_recommended]" value="1" <?= !empty($rankingClassification['knockout_forces_not_recommended']) ? 'checked' : '' ?>>
                                <label class="form-check-label" for="dashboard_ranking_knockout_force">KO fuerza NR</label>
                            </div>
                            <div class="form-check form-switch">
                                <input id="dashboard_ranking_knockout_cap_enabled" class="form-check-input" type="checkbox" name="ranking_config[classification][knockout_score_cap_enabled]" value="1" <?= !empty($rankingClassification['knockout_score_cap_enabled']) ? 'checked' : '' ?>>
                                <label class="form-check-label" for="dashboard_ranking_knockout_cap_enabled">Aplicar tope KO</label>
                            </div>
                        </div>
                    </div>

                    <div class="accordion mb-3" id="dashboardRankingAccordion">
                        <?php foreach ($rankingDimensions as $dimensionKey => $dimension): ?>
                            <?php $components = is_array($dimension['components'] ?? null) ? $dimension['components'] : []; ?>
                            <div class="accordion-item">
                                <h3 class="accordion-header" id="dashboard_ranking_heading_<?= e((string) $dimensionKey) ?>">
                                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#dashboard_ranking_collapse_<?= e((string) $dimensionKey) ?>" aria-expanded="false" aria-controls="dashboard_ranking_collapse_<?= e((string) $dimensionKey) ?>">
                                        <?= e((string) $dimensionKey) ?> · <?= e((string) ($dimension['label'] ?? 'Dimension')) ?>
                                    </button>
                                </h3>
                                <div id="dashboard_ranking_collapse_<?= e((string) $dimensionKey) ?>" class="accordion-collapse collapse" aria-labelledby="dashboard_ranking_heading_<?= e((string) $dimensionKey) ?>" data-bs-parent="#dashboardRankingAccordion">
                                    <div class="accordion-body">
                                        <div class="row g-3 align-items-end mb-3">
                                            <div class="col-12 col-md-4">
                                                <div class="form-check form-switch">
                                                    <input id="dashboard_ranking_dimension_<?= e((string) $dimensionKey) ?>" class="form-check-input" type="checkbox" name="ranking_config[dimensions][<?= e((string) $dimensionKey) ?>][enabled]" value="1" <?= !empty($dimension['enabled']) ? 'checked' : '' ?>>
                                                    <label class="form-check-label" for="dashboard_ranking_dimension_<?= e((string) $dimensionKey) ?>">Considerar dimension</label>
                                                </div>
                                            </div>
                                            <div class="col-12 col-md-4">
                                                <label class="form-label" for="dashboard_ranking_dimension_weight_<?= e((string) $dimensionKey) ?>">Peso dimension</label>
                                                <input id="dashboard_ranking_dimension_weight_<?= e((string) $dimensionKey) ?>" class="form-control" type="number" step="0.01" min="0" max="100" name="ranking_config[dimensions][<?= e((string) $dimensionKey) ?>][weight]" value="<?= e((string) ($dimension['weight'] ?? 0)) ?>">
                                            </div>
                                        </div>

                                        <div class="table-responsive">
                                            <table class="table align-middle mb-0">
                                                <thead>
                                                    <tr>
                                                        <th>Criterio</th>
                                                        <th>Sentido</th>
                                                        <th style="width: 150px;">Peso</th>
                                                        <th class="text-center" style="width: 130px;">Considerar</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($components as $componentIndex => $component): ?>
                                                        <tr>
                                                            <td>
                                                                <div class="fw-semibold"><?= e((string) ($component['label'] ?? $component['key'] ?? 'Criterio')) ?></div>
                                                                <div class="text-muted small"><?= e((string) ($component['key'] ?? '')) ?></div>
                                                            </td>
                                                            <td><?= e((string) ($component['transform'] ?? 'direct')) ?></td>
                                                            <td>
                                                                <input class="form-control form-control-sm" type="number" step="0.01" min="0" max="100" name="ranking_config[dimensions][<?= e((string) $dimensionKey) ?>][components][<?= (int) $componentIndex ?>][weight]" value="<?= e((string) ($component['weight'] ?? 0)) ?>">
                                                            </td>
                                                            <td class="text-center">
                                                                <input class="form-check-input" type="checkbox" name="ranking_config[dimensions][<?= e((string) $dimensionKey) ?>][components][<?= (int) $componentIndex ?>][enabled]" value="1" <?= !empty($component['enabled']) ? 'checked' : '' ?> aria-label="Considerar <?= e((string) ($component['label'] ?? 'criterio')) ?>">
                                                            </td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <div class="row g-3 mb-3">
                        <?php foreach ($rankingKnockoutRules as $knockoutKey => $knockout): ?>
                            <div class="col-12 col-md-6 col-xl-3">
                                <div class="border rounded p-3 h-100">
                                    <div class="form-check form-switch mb-2">
                                        <input id="dashboard_ranking_knockout_<?= e((string) $knockoutKey) ?>" class="form-check-input" type="checkbox" name="ranking_config[knockouts][<?= e((string) $knockoutKey) ?>][enabled]" value="1" <?= !empty($knockout['enabled']) ? 'checked' : '' ?>>
                                        <label class="form-check-label fw-semibold" for="dashboard_ranking_knockout_<?= e((string) $knockoutKey) ?>"><?= e((string) ($knockout['label'] ?? $knockoutKey)) ?></label>
                                    </div>
                                    <label class="form-label small" for="dashboard_ranking_knockout_threshold_<?= e((string) $knockoutKey) ?>">
                                        <?= e((string) ($knockout['metric'] ?? 'Metrica')) ?>
                                    </label>
                                    <div class="input-group input-group-sm">
                                        <select class="form-select" name="ranking_config[knockouts][<?= e((string) $knockoutKey) ?>][operator]" aria-label="Operador <?= e((string) ($knockout['label'] ?? $knockoutKey)) ?>">
                                            <?php foreach (['<', '<=', '>', '>='] as $operatorOption): ?>
                                                <option value="<?= e($operatorOption) ?>" <?= (string) ($knockout['operator'] ?? '<') === $operatorOption ? 'selected' : '' ?>><?= e($operatorOption) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                        <input id="dashboard_ranking_knockout_threshold_<?= e((string) $knockoutKey) ?>" class="form-control" type="number" step="0.01" name="ranking_config[knockouts][<?= e((string) $knockoutKey) ?>][threshold]" value="<?= e((string) ($knockout['threshold'] ?? 0)) ?>">
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <div class="d-flex flex-wrap gap-2">
                        <button class="btn btn-primary" type="submit"><i class="bi bi-sliders me-1"></i> Recalcular grilla</button>
                        <button class="btn btn-outline-secondary" type="submit" name="ranking_preset" value="saved"><i class="bi bi-bookmark-check me-1"></i> Usar configuracion guardada</button>
                        <button class="btn btn-outline-secondary" type="submit" name="ranking_preset" value="official"><i class="bi bi-arrow-counterclockwise me-1"></i> Restaurar matriz oficial</button>
                        <?php if ($canManageRankingPresets): ?>
                            <button class="btn btn-outline-primary" type="submit" formmethod="post" formaction="<?= e(route_url('tests.ranking-presets')) ?>" name="ranking_preset_action" value="create" onclick="this.form.querySelectorAll('[data-ranking-preset-post-field]').forEach(function(field){field.disabled=false;});" <?= $rankingPresetsStorageReady ? '' : 'disabled' ?>>
                                <i class="bi bi-plus-circle me-1"></i> Guardar como nueva
                            </button>
                            <button class="btn btn-outline-secondary" type="submit" formmethod="post" formaction="<?= e(route_url('tests.ranking-presets')) ?>" name="ranking_preset_action" value="update" onclick="this.form.querySelectorAll('[data-ranking-preset-post-field]').forEach(function(field){field.disabled=false;});" <?= $rankingPresetsStorageReady && $rankingSelectedPresetId > 0 ? '' : 'disabled' ?>>
                                <i class="bi bi-save me-1"></i> Actualizar guardada
                            </button>
                            <button class="btn btn-outline-danger" type="submit" formmethod="post" formaction="<?= e(route_url('tests.ranking-presets')) ?>" name="ranking_preset_action" value="delete" onclick="this.form.querySelectorAll('[data-ranking-preset-post-field]').forEach(function(field){field.disabled=false;});" <?= $rankingPresetsStorageReady && $rankingSelectedPresetId > 0 ? '' : 'disabled' ?>>
                                <i class="bi bi-trash me-1"></i> Eliminar personalizada
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </form>
</section>
<?php else: ?>
<section class="card content-panel process-dashboard-filters mb-4">
    <div class="d-flex flex-wrap align-items-start justify-content-between gap-3">
        <div>
            <h2 class="h5 fw-bold mb-1">Configuración del ranking</h2>
            <p class="text-muted mb-0">La matriz de ranking es administrada globalmente y se aplica automáticamente a esta empresa.</p>
        </div>
        <span class="badge text-bg-light border">
            <?= e((string) ($rankingCompanyAssignment['preset_name'] ?? 'Matriz oficial')) ?>
        </span>
    </div>
</section>
<?php endif; ?>

<?php if ($duplicateAssignmentsTotal > 0): ?>
    <div class="alert alert-warning border d-flex align-items-start gap-2 mb-4" role="alert">
        <i class="bi bi-exclamation-triangle mt-1"></i>
        <div>
            <strong>Hay <?= $duplicateAssignmentsTotal ?> persona(s) asignadas a mas de un proceso visible.</strong>
            <div>La regla indica que una persona solo puede estar activa en un proceso. Revisa estas asignaciones porque pueden alterar la lectura del dashboard.</div>
            <?php if ($duplicateAssignmentExamples): ?>
                <ul class="mb-0 mt-2 ps-3">
                    <?php foreach ($duplicateAssignmentExamples as $duplicate): ?>
                        <?php
                            $duplicateUser = $duplicate['user'] ?? [];
                            $duplicateProcesses = array_map(
                                static fn(array $process): string => trim((string) ($process['name'] ?? 'Proceso') . ' ' . ((string) ($process['code'] ?? '') !== '' ? '(' . (string) $process['code'] . ')' : '')),
                                is_array($duplicate['processes'] ?? null) ? $duplicate['processes'] : []
                            );
                        ?>
                        <li>
                            <?= e((string) ($duplicateUser['name'] ?? 'Usuario')) ?>
                            <?= trim((string) ($duplicateUser['rut'] ?? '')) !== '' ? ' · ' . e((string) $duplicateUser['rut']) : '' ?>:
                            <?= e(implode(', ', $duplicateProcesses)) ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    </div>
<?php endif; ?>

<section class="process-dashboard-kpis mb-4">
    <div class="process-dashboard-kpi">
        <?= process_dashboard_help_label('Personas asignadas', $dashboardMetricHelp['users_total']) ?>
        <strong><?= $usersTotal ?></strong>
    </div>
    <div class="process-dashboard-kpi is-success">
        <?= process_dashboard_help_label('Evaluadas', $dashboardMetricHelp['evaluated']) ?>
        <strong><?= $evaluated ?></strong>
    </div>
    <div class="process-dashboard-kpi is-warning">
        <?= process_dashboard_help_label('En evaluacion', $dashboardMetricHelp['in_progress']) ?>
        <strong><?= $inProgress ?></strong>
    </div>
    <div class="process-dashboard-kpi is-muted">
        <?= process_dashboard_help_label('Pendientes', $dashboardMetricHelp['pending']) ?>
        <strong><?= $pending ?></strong>
    </div>
    <div class="process-dashboard-kpi is-primary">
        <span>Avance general</span>
        <strong><?= $progressPercent ?>%</strong>
    </div>
</section>

<section class="card content-panel mb-4">
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
        <div>
            <h2 class="h5 fw-bold mb-1">Resultados generales por procesos</h2>
            <p class="text-muted mb-0">
                Resumen R/RO/NR calculado con los criterios activos del ranking.
                Alcance: <?= $rankingScope === 'process' ? 'proceso completo' : 'valores filtrados' ?>.
            </p>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <span class="badge <?= $rankingUsesOfficialConfig ? 'text-bg-success' : 'text-bg-warning' ?>">
                <?= $rankingUsesOfficialConfig ? 'Matriz oficial' : 'Ranking personalizado' ?>
            </span>
            <?php if ($rankingIsPreviewConfig): ?>
                <span class="badge text-bg-info">Vista de prueba</span>
            <?php endif; ?>
        </div>
    </div>
    <div class="row g-3">
        <div class="col-12 col-md-6 col-xl">
            <div class="border rounded p-3 h-100">
                <span class="text-muted small fw-bold text-uppercase">Recomendados</span>
                <strong class="d-block h3 mb-0"><?= $rankingRecommended ?></strong>
                <span class="text-muted small"><?= number_format($rankingRecommendedPercent, 1, ',', '.') ?>% de ranqueados</span>
            </div>
        </div>
        <div class="col-12 col-md-6 col-xl">
            <div class="border rounded p-3 h-100">
                <span class="text-muted small fw-bold text-uppercase">Recomendados con observacion</span>
                <strong class="d-block h3 mb-0"><?= $rankingObservation ?></strong>
                <span class="text-muted small"><?= number_format($rankingObservationPercent, 1, ',', '.') ?>% de ranqueados</span>
            </div>
        </div>
        <div class="col-12 col-md-6 col-xl">
            <div class="border rounded p-3 h-100">
                <span class="text-muted small fw-bold text-uppercase">No recomendados</span>
                <strong class="d-block h3 mb-0"><?= $rankingNotRecommended ?></strong>
                <span class="text-muted small"><?= number_format($rankingNotRecommendedPercent, 1, ',', '.') ?>% de ranqueados</span>
            </div>
        </div>
        <div class="col-12 col-md-6 col-xl">
            <a class="border rounded p-3 h-100 d-block text-body text-decoration-none" href="<?= e($dashboardWarningsUrl) ?>" target="_blank" rel="noopener" aria-label="Ver detalle de advertencias de ranking en una pagina nueva">
                <span class="text-muted small fw-bold text-uppercase">Advertencias</span>
                <strong class="d-block h3 mb-0"><?= $rankingWarnings ?></strong>
                <span class="text-muted small"><?= number_format($rankingWarningsPercent, 1, ',', '.') ?>% fuera del ranking por datos incompletos</span>
            </a>
        </div>
        <div class="col-12 col-md-6 col-xl">
            <div class="border rounded p-3 h-100">
                <span class="text-muted small fw-bold text-uppercase">Ranqueados</span>
                <strong class="d-block h3 mb-0"><?= $rankingRanked ?></strong>
                <span class="text-muted small">Base para calcular R / RO / NR</span>
            </div>
        </div>
    </div>
</section>

<section class="process-dashboard-layout mb-4">
    <div class="card content-panel process-dashboard-main-chart">
        <div class="d-flex flex-wrap align-items-start justify-content-between gap-2 mb-3">
            <div>
                <h2 class="h5 fw-bold mb-1">Avance por proceso</h2>
                <p class="text-muted mb-0">Usuarios evaluados, en evaluacion y pendientes por proceso.</p>
            </div>
        </div>

        <?php if ($processRows): ?>
            <div class="process-dashboard-chart process-dashboard-chart-lg">
                <canvas id="processProgressChart" aria-label="Grafico de avance por proceso" role="img"></canvas>
            </div>
        <?php else: ?>
            <div class="alert alert-light border mb-0">No hay procesos disponibles para graficar.</div>
        <?php endif; ?>
    </div>

    <aside class="card content-panel process-dashboard-donut-panel">
        <h2 class="h5 fw-bold mb-1">Distribucion general</h2>
        <p class="text-muted mb-3">Estado de personas asignadas en procesos visibles.</p>
        <div class="process-dashboard-chart process-dashboard-chart-donut">
            <canvas id="processDistributionChart" aria-label="Grafico de distribucion general" role="img"></canvas>
        </div>
        <div class="process-dashboard-donut-list">
            <span><i class="is-success"></i> Evaluadas <strong><?= $evaluated ?></strong></span>
            <span><i class="is-warning"></i> En evaluacion <strong><?= $inProgress ?></strong></span>
            <span><i class="is-pending"></i> Pendientes <strong><?= $pending ?></strong></span>
        </div>
    </aside>
</section>

<section class="card content-panel mb-4">
    <div class="d-flex flex-wrap align-items-start justify-content-between gap-2 mb-3">
        <div>
            <h2 class="h5 fw-bold mb-1">Evolucion de evaluaciones finalizadas</h2>
            <p class="text-muted mb-0">Sesiones completadas o expiradas durante los ultimos 8 dias.</p>
        </div>
    </div>
    <div class="process-dashboard-chart process-dashboard-chart-md">
        <canvas id="processTrendChart" aria-label="Grafico de evolucion de evaluaciones finalizadas" role="img"></canvas>
    </div>
</section>

<section class="card content-panel">
    <div class="d-flex flex-wrap align-items-start justify-content-between gap-2 mb-3">
        <div>
            <h2 class="h5 fw-bold mb-1">Procesos con mayor pendiente</h2>
            <p class="text-muted mb-0">Priorizados por cantidad de usuarios pendientes.</p>
        </div>
        <div class="d-flex flex-wrap align-items-center gap-2">
            <?php if ($canViewCompleteRanking): ?>
                <a class="btn btn-outline-primary" href="<?= e($rankingCompleteUrl) ?>"><i class="bi bi-trophy me-1"></i> Ranking completo</a>
            <?php endif; ?>
            <span class="badge text-bg-light border"><?= count($processRows) ?> procesos</span>
        </div>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle app-table app-data-table" data-export-title="Dashboard Avance Procesos">
            <thead>
                <tr>
                    <th>Proceso</th>
                    <th>Usuarios</th>
                    <th><?= process_dashboard_help_header('Evaluadas', $dashboardMetricHelp['evaluated']) ?></th>
                    <th><?= process_dashboard_help_header('En evaluacion', $dashboardMetricHelp['in_progress']) ?></th>
                    <th><?= process_dashboard_help_header('Pendientes', $dashboardMetricHelp['pending']) ?></th>
                    <th><?= process_dashboard_help_header('Resultados', $dashboardMetricHelp['ranking']) ?></th>
                    <th>Promedio</th>
                    <th>Knockouts</th>
                    <th><?= process_dashboard_help_header('Supervision', $dashboardMetricHelp['supervision'], true) ?></th>
                    <th>Avance</th>
                    <th class="no-sort no-export">Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($processRows as $row): ?>
                    <?php $processId = (int) ($row['id'] ?? 0); ?>
                    <tr>
                        <td>
                            <div class="fw-semibold"><?= e((string) ($row['name'] ?? 'Proceso')) ?></div>
                            <div class="text-muted small"><code><?= e((string) ($row['code'] ?? '')) ?></code></div>
                        </td>
                        <td><?= (int) ($row['users_total'] ?? 0) ?></td>
                        <td><span class="badge text-bg-success"><?= (int) ($row['evaluated'] ?? 0) ?></span></td>
                        <td><span class="badge text-bg-warning"><?= (int) ($row['in_progress'] ?? 0) ?></span></td>
                        <td><span class="badge text-bg-light border"><?= (int) ($row['pending'] ?? 0) ?></span></td>
                        <td>
                            <?php $rankingSummary = is_array($row['ranking_summary'] ?? null) ? $row['ranking_summary'] : []; ?>
                            <div class="d-flex flex-wrap gap-1">
                                <span class="badge text-bg-success">R <?= (int) ($rankingSummary['recommended'] ?? 0) ?></span>
                                <span class="badge text-bg-warning">RO <?= (int) ($rankingSummary['observation'] ?? 0) ?></span>
                                <span class="badge text-bg-secondary">NR <?= (int) ($rankingSummary['not_recommended'] ?? 0) ?></span>
                            </div>
                            <?php if ((int) ($rankingSummary['warnings'] ?? 0) > 0): ?>
                                <div class="text-muted small mt-1"><?= (int) $rankingSummary['warnings'] ?> advertencias</div>
                            <?php endif; ?>
                        </td>
                        <td><?= array_key_exists('score_avg', $rankingSummary) && $rankingSummary['score_avg'] !== null ? e((string) $rankingSummary['score_avg']) : '-' ?></td>
                        <td><span class="badge <?= (int) ($rankingSummary['knockouts'] ?? 0) > 0 ? 'text-bg-danger' : 'text-bg-light border' ?>"><?= (int) ($rankingSummary['knockouts'] ?? 0) ?></span></td>
                        <td>
                            <?php if ((int) ($row['activity_events_total'] ?? 0) > 0): ?>
                                <div class="supervised-process-summary">
                                    <span class="badge text-bg-light border">Eventos: <?= (int) $row['activity_events_total'] ?></span>
                                    <?php if ((int) ($row['activity_attention_total'] ?? 0) > 0): ?>
                                        <span class="badge text-bg-warning">Atencion: <?= (int) $row['activity_attention_total'] ?></span>
                                    <?php endif; ?>
                                    <?php if ((int) ($row['activity_risk_total'] ?? 0) > 0): ?>
                                        <span class="badge text-bg-danger">Riesgo: <?= (int) $row['activity_risk_total'] ?></span>
                                    <?php endif; ?>
                                </div>
                            <?php else: ?>
                                <span class="text-muted small">Sin eventos</span>
                            <?php endif; ?>
                        </td>
                        <td style="min-width: 170px;">
                            <div class="d-flex align-items-center gap-2">
                                <div class="progress flex-grow-1" role="progressbar" aria-label="Avance del proceso" aria-valuenow="<?= (int) ($row['progress_percent'] ?? 0) ?>" aria-valuemin="0" aria-valuemax="100" style="height: .6rem;">
                                    <div class="progress-bar" style="width: <?= (int) ($row['progress_percent'] ?? 0) ?>%;"></div>
                                </div>
                                <span class="small text-muted"><?= (int) ($row['progress_percent'] ?? 0) ?>%</span>
                            </div>
                        </td>
                        <td>
                            <div class="d-flex flex-wrap gap-2">
                                <?php if (!empty($row['can_view_process'])): ?>
                                    <a class="btn btn-sm btn-outline-primary" href="<?= e(route_url('test-process.show', $processId)) ?>"><i class="bi bi-kanban me-1"></i> Ver</a>
                                <?php endif; ?>
                                <?php if (!empty($row['can_view_ranking'])): ?>
                                    <a class="btn btn-sm btn-outline-primary" href="<?= e(route_url('test-process.ranking', $processId)) ?>"><i class="bi bi-trophy me-1"></i> Ranking</a>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<?php endif; ?>

<?php if (!$dashboardSkipScripts): ?>
<script src="<?= e(url('assets/coreui/vendors/chart.js/js/chart.umd.js')) ?>"></script>
<script>
(function () {
    var embeddedDashboardData = <?= json_encode($chartData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
    var dashboardDataUrl = <?= json_encode($dashboardDataUrl, JSON_UNESCAPED_SLASHES) ?>;
    var isAsyncShell = <?= $dashboardAsyncShell ? 'true' : 'false' ?>;
    var isGlobalDashboardAdmin = <?= $dashboardIsGlobalAdmin ? 'true' : 'false' ?>;

    window.renderProcessDashboardCharts = function (dashboardData) {
    if (!window.Chart || !dashboardData) {
        return;
    }

    var styles = getComputedStyle(document.documentElement);
    var textColor = '#1f2937';
    var mutedColor = '#667085';
    var borderColor = 'rgba(152, 162, 179, .18)';
    var primaryColor = styles.getPropertyValue('--app-primary').trim() || '#2563eb';
    var successColor = styles.getPropertyValue('--app-success').trim() || '#188038';
    var warningColor = styles.getPropertyValue('--app-warning').trim() || '#f9ab00';
    var pendingColor = '#98a2b3';

    Chart.defaults.color = textColor;
    Chart.defaults.font.family = styles.getPropertyValue('--app-font-family').trim() || 'Inter, system-ui, sans-serif';
    Chart.defaults.plugins.legend.labels.boxWidth = 12;
    Chart.defaults.plugins.legend.labels.boxHeight = 12;

    function axisOptions(title) {
        return {
            ticks: {
                color: mutedColor,
                precision: 0
            },
            grid: {
                color: borderColor,
                drawBorder: false
            },
            title: {
                display: true,
                text: title,
                color: mutedColor,
                font: {
                    weight: '700'
                }
            }
        };
    }

    var barValueLabels = {
        id: 'barValueLabels',
        afterDatasetsDraw: function (chart) {
            var ctx = chart.ctx;
            ctx.save();
            ctx.textAlign = 'center';
            ctx.textBaseline = 'middle';
            ctx.font = '700 12px ' + Chart.defaults.font.family;

            chart.data.datasets.forEach(function (dataset, datasetIndex) {
                var meta = chart.getDatasetMeta(datasetIndex);
                meta.data.forEach(function (bar, index) {
                    var value = Number(dataset.data[index] || 0);
                    if (value <= 0) {
                        return;
                    }

                    var props = bar.getProps(['x', 'y', 'base'], true);
                    var barHeight = Math.abs(props.base - props.y);
                    var fitsInside = barHeight >= 20;
                    ctx.fillStyle = fitsInside
                        ? (datasetIndex === 0 ? '#fff' : '#111827')
                        : textColor;
                    ctx.fillText(String(value), props.x, fitsInside ? props.y + Math.min(14, barHeight / 2) : props.y - 8);
                });
            });

            ctx.restore();
        }
    };

    var doughnutValueLabels = {
        id: 'doughnutValueLabels',
        afterDatasetsDraw: function (chart) {
            var ctx = chart.ctx;
            ctx.save();
            ctx.textAlign = 'center';
            ctx.textBaseline = 'middle';
            ctx.font = '800 13px ' + Chart.defaults.font.family;

            chart.data.datasets.forEach(function (dataset, datasetIndex) {
                var meta = chart.getDatasetMeta(datasetIndex);
                meta.data.forEach(function (arc, index) {
                    var value = Number(dataset.data[index] || 0);
                    if (value <= 0) {
                        return;
                    }

                    var point = arc.tooltipPosition();
                    ctx.fillStyle = index === 1 ? '#111827' : '#fff';
                    ctx.fillText(String(value), point.x, point.y);
                });
            });

            ctx.restore();
        }
    };

    var lineValueLabels = {
        id: 'lineValueLabels',
        afterDatasetsDraw: function (chart) {
            var ctx = chart.ctx;
            ctx.save();
            ctx.textAlign = 'center';
            ctx.textBaseline = 'bottom';
            ctx.fillStyle = textColor;
            ctx.font = '800 12px ' + Chart.defaults.font.family;

            chart.data.datasets.forEach(function (dataset, datasetIndex) {
                var meta = chart.getDatasetMeta(datasetIndex);
                meta.data.forEach(function (point, index) {
                    var value = Number(dataset.data[index] || 0);
                    if (value <= 0) {
                        return;
                    }

                    var props = point.getProps(['x', 'y'], true);
                    ctx.fillText(String(value), props.x, props.y - 10);
                });
            });

            ctx.restore();
        }
    };

    var progressCanvas = document.getElementById('processProgressChart');
    if (progressCanvas) {
        new Chart(progressCanvas, {
            type: 'bar',
            plugins: [barValueLabels],
            data: {
                labels: dashboardData.processLabels,
                datasets: [
                    { label: 'Evaluadas', data: dashboardData.evaluated, backgroundColor: successColor, borderRadius: 5 },
                    { label: 'En evaluacion', data: dashboardData.inProgress, backgroundColor: warningColor, borderRadius: 5 },
                    { label: 'Pendientes', data: dashboardData.pending, backgroundColor: pendingColor, borderRadius: 5 }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'top',
                        labels: { color: textColor }
                    },
                    tooltip: { mode: 'index', intersect: false }
                },
                scales: {
                    x: {
                        ...axisOptions('Procesos'),
                        grid: {
                            display: false
                        }
                    },
                    y: {
                        beginAtZero: true,
                        ...axisOptions('Personas')
                    }
                }
            }
        });
    }

    var distributionCanvas = document.getElementById('processDistributionChart');
    if (distributionCanvas) {
        new Chart(distributionCanvas, {
            type: 'doughnut',
            plugins: [doughnutValueLabels],
            data: {
                labels: ['Evaluadas', 'En evaluacion', 'Pendientes'],
                datasets: [{
                    data: [
                        dashboardData.distribution.evaluated,
                        dashboardData.distribution.inProgress,
                        dashboardData.distribution.pending
                    ],
                    backgroundColor: [successColor, warningColor, pendingColor],
                    borderColor: styles.getPropertyValue('--card-content-bg').trim() || '#fff',
                    borderWidth: 3
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '58%',
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: { color: textColor }
                    }
                }
            }
        });
    }

    var trendCanvas = document.getElementById('processTrendChart');
    if (trendCanvas) {
        new Chart(trendCanvas, {
            type: 'line',
            plugins: [lineValueLabels],
            data: {
                labels: dashboardData.trendLabels,
                datasets: [{
                    label: 'Evaluaciones finalizadas',
                    data: dashboardData.trendCounts,
                    borderColor: primaryColor,
                    backgroundColor: primaryColor,
                    fill: false,
                    tension: .28,
                    pointRadius: 4,
                    pointHoverRadius: 6
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'top',
                        labels: { color: textColor }
                    }
                },
                scales: {
                    x: {
                        ...axisOptions('Fecha'),
                        grid: {
                            display: false
                        }
                    },
                    y: {
                        beginAtZero: true,
                        ...axisOptions('Evaluaciones')
                    }
                }
            }
        });
    }
    };

    function setDashboardLoading(isLoading) {
        var loading = document.querySelector('[data-process-dashboard-loading]');
        if (loading) {
            loading.classList.toggle('d-none', !isLoading);
        }
    }

    function setDashboardError(message) {
        var error = document.querySelector('[data-process-dashboard-error]');
        var errorMessage = document.querySelector('[data-process-dashboard-error-message]');
        if (!error) {
            return;
        }

        if (message) {
            if (errorMessage) {
                errorMessage.textContent = message;
            }
            error.classList.remove('d-none');
            return;
        }

        error.classList.add('d-none');
    }

    function loadDashboardData() {
        var content = document.querySelector('[data-process-dashboard-content]');
        if (!content || !dashboardDataUrl) {
            return;
        }

        if (isGlobalDashboardAdmin) {
            var company = document.querySelector('[data-dashboard-company]');
            var selected = document.querySelectorAll('[data-dashboard-process-inputs] input[name="process_ids[]"]');
            if (!company || Number(company.value) <= 0 || !selected.length) {
                setDashboardLoading(false);
                content.innerHTML = '<div class="alert alert-info">Selecciona una empresa y al menos un proceso para visualizar el dashboard.</div>';
                return;
            }
        }

        setDashboardError('');
        setDashboardLoading(true);
        content.innerHTML = '';

        window.fetch(dashboardDataUrl + window.location.search, {
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
            .then(function (response) {
                return response.json().then(function (payload) {
                    if (!response.ok || !payload.ok) {
                        throw new Error(payload.message || 'No se pudo cargar la informacion.');
                    }

                    return payload;
                });
            })
            .then(function (payload) {
                content.innerHTML = payload.html || '';
                setDashboardLoading(false);
                window.renderProcessDashboardCharts(payload.chartData || {});
            })
            .catch(function (error) {
                setDashboardLoading(false);
                setDashboardError(error.message || 'No se pudo cargar la informacion.');
            });
    }

    document.addEventListener('DOMContentLoaded', function () {
        var filterForm = document.querySelector('[data-dashboard-filter-form]');
        var companySelect = document.querySelector('[data-dashboard-company]');
        var pickerButton = document.querySelector('[data-dashboard-process-picker]');
        var pickerTemplate = document.getElementById('dashboardProcessPickerTemplate');
        var processInputs = document.querySelector('[data-dashboard-process-inputs]');
        var processCount = document.querySelector('[data-dashboard-process-count]');
        var processSummary = document.querySelector('[data-dashboard-selection-summary]');

        function syncProcessSelection() {
            if (!processInputs) return;
            var checked = document.querySelectorAll('#appDrawerBody [data-dashboard-process-checkbox]:checked');
            if (!checked.length && !document.querySelector('#appDrawerBody [data-dashboard-process-checkbox]')) {
                var existing = document.querySelectorAll('[data-dashboard-process-inputs] input[name="process_ids[]"]');
                if (processCount) processCount.textContent = existing.length + ' seleccionado' + (existing.length === 1 ? '' : 's');
                if (processSummary) processSummary.textContent = existing.length ? existing.length + ' proceso' + (existing.length === 1 ? '' : 's') : 'Sin selección';
                if (pickerButton) pickerButton.textContent = existing.length ? 'Modificar selección' : 'Seleccionar procesos';
                return;
            }
            var selectedSet = {};
            processInputs.querySelectorAll('input[name="process_ids[]"]').forEach(function (input) { selectedSet[input.value] = true; });
            var pickerTable = document.querySelector('#appDrawerBody table.app-data-table');
            var tableApi = pickerTable && window.jQuery && window.jQuery.fn.DataTable && window.jQuery.fn.DataTable.isDataTable(pickerTable)
                ? window.jQuery(pickerTable).DataTable()
                : null;
            var activeRows = tableApi
                ? tableApi.rows({ search: 'applied' }).nodes().toArray()
                : Array.prototype.slice.call(document.querySelectorAll('#appDrawerBody [data-dashboard-process-row]'));
            activeRows.filter(function (row) {
                return !row.classList.contains('d-none') && row.querySelector('[data-dashboard-process-checkbox]');
            }).forEach(function (row) {
                var checkbox = row.querySelector('[data-dashboard-process-checkbox]');
                if (checkbox.checked) selectedSet[checkbox.value] = true;
                else delete selectedSet[checkbox.value];
            });
            processInputs.innerHTML = '';
            Object.keys(selectedSet).forEach(function (value) {
                var input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'process_ids[]';
                input.value = value;
                processInputs.appendChild(input);
            });
            var count = Object.keys(selectedSet).length;
            if (processCount) processCount.textContent = count + ' seleccionado' + (count === 1 ? '' : 's');
            if (processSummary) processSummary.textContent = count ? count + ' proceso' + (count === 1 ? '' : 's') : 'Sin selección';
            if (pickerButton) pickerButton.textContent = count ? 'Modificar selección' : 'Seleccionar procesos';
        }

        function filterPickerRows(clearSelection) {
            var companyId = companySelect ? companySelect.value : '<?= (int) $dashboardCompanyId ?>';
            if (clearSelection && processInputs) processInputs.innerHTML = '';
            document.querySelectorAll('[data-dashboard-process-row]').forEach(function (row) {
                var matches = !isGlobalDashboardAdmin || (companyId !== '0' && row.getAttribute('data-company-id') === companyId);
                row.classList.toggle('d-none', !matches);
                if (!matches) row.querySelector('input[type="checkbox"]').checked = false;
            });
            syncProcessSelection();
            if (pickerButton) pickerButton.disabled = isGlobalDashboardAdmin && companyId === '0';
        }

        if (companySelect) companySelect.addEventListener('change', function () { filterPickerRows(true); });
        if (pickerButton && pickerTemplate) pickerButton.addEventListener('click', function () {
            window.AppDrawer.open({
                title: 'Seleccionar procesos',
                size: 'lg',
                html: pickerTemplate.innerHTML
            });
            window.setTimeout(function () {
                var drawer = document.getElementById('appDrawerBody');
                var companyId = companySelect ? companySelect.value : '<?= (int) $dashboardCompanyId ?>';
                var selectedValues = Array.prototype.map.call(processInputs.querySelectorAll('input[name="process_ids[]"]'), function (input) { return input.value; });
                var pickerTable = drawer.querySelector('table.app-data-table');
                var selectAll = drawer.querySelector('[data-dashboard-process-select-all]');

                function getSelectableProcessRows() {
                    if (!pickerTable) return [];
                    var tableApi = window.jQuery && window.jQuery.fn.DataTable && window.jQuery.fn.DataTable.isDataTable(pickerTable)
                        ? window.jQuery(pickerTable).DataTable()
                        : null;
                    var rows = tableApi ? tableApi.rows({ search: 'applied' }).nodes().toArray() : Array.prototype.slice.call(pickerTable.querySelectorAll('[data-dashboard-process-row]'));
                    return rows.filter(function (row) {
                        return !row.classList.contains('d-none') && row.querySelector('[data-dashboard-process-checkbox]');
                    });
                }

                function syncSelectAllState() {
                    if (!selectAll) return;
                    var rows = getSelectableProcessRows();
                    var checkedCount = rows.filter(function (row) { return row.querySelector('[data-dashboard-process-checkbox]').checked; }).length;
                    selectAll.checked = rows.length > 0 && checkedCount === rows.length;
                    selectAll.indeterminate = checkedCount > 0 && checkedCount < rows.length;
                    selectAll.disabled = rows.length === 0;
                }

                drawer.querySelectorAll('[data-dashboard-process-checkbox]').forEach(function (checkbox) {
                    var row = checkbox.closest('[data-dashboard-process-row]');
                    var visible = !isGlobalDashboardAdmin || (companyId !== '0' && row.getAttribute('data-company-id') === companyId);
                    row.classList.toggle('d-none', !visible);
                    checkbox.checked = visible && selectedValues.indexOf(checkbox.value) !== -1;
                    checkbox.addEventListener('change', function () {
                        document.querySelectorAll('[data-dashboard-process-checkbox][value="' + checkbox.value + '"]').forEach(function (other) { other.checked = checkbox.checked; });
                        syncProcessSelection();
                        syncSelectAllState();
                    });
                });
                if (selectAll) {
                    selectAll.addEventListener('change', function () {
                        getSelectableProcessRows().forEach(function (row) {
                            row.querySelector('[data-dashboard-process-checkbox]').checked = selectAll.checked;
                        });
                        syncProcessSelection();
                        syncSelectAllState();
                    });
                }
                if (pickerTable && window.jQuery && window.jQuery.fn.DataTable && window.jQuery.fn.DataTable.isDataTable(pickerTable)) {
                    window.jQuery(pickerTable).on('draw.dt.dashboardProcessPicker', syncSelectAllState);
                }
                syncSelectAllState();
            }, 0);
        });
        if (filterForm) filterForm.addEventListener('submit', function () {
            syncProcessSelection();
        });
        filterPickerRows(false);

        document.querySelectorAll('[data-process-dashboard-refresh]').forEach(function (button) {
            button.addEventListener('click', loadDashboardData);
        });

        if (isAsyncShell) {
            loadDashboardData();
            return;
        }

        window.renderProcessDashboardCharts(embeddedDashboardData);
    });
})();
</script>
<?php endif; ?>
