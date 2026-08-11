<?php
$dashboardFields = $dashboardFields ?? [];
$dashboardFilters = $dashboardFilters ?? ['fields' => []];
$dashboardOptions = $dashboardOptions ?? ['field_options' => []];
$dashboardFieldFilters = is_array($dashboardFilters['fields'] ?? null) ? $dashboardFilters['fields'] : [];
$tests = $tests ?? [];
$sessions = $sessions ?? [];
$hasFinishedSessions = (bool) ($hasFinishedSessions ?? false);
$sessionStatusLabels = [
    'assigned' => 'Asignada',
    'in_progress' => 'En curso',
    'completed' => 'Completada',
    'cancelled' => 'Cancelada',
    'expired' => 'Expirada',
];
$progressRankingUrl = route_url('tests.progress-ranking');
$progressExportUrl = route_url('tests.progress-export');
$progressQuery = http_build_query(['fields' => $dashboardFieldFilters]);
if ($progressQuery !== '') {
    $progressRankingUrl .= '?' . $progressQuery;
    $progressExportUrl .= '?' . $progressQuery;
}

$sessionInstruments = [];
foreach ($tests as $test) {
    $sessionInstruments[(int) $test['id']] = [
        'id' => (int) $test['id'],
        'name' => (string) $test['name'],
        'code' => (string) ($test['code'] ?? ''),
    ];
}
uasort($sessionInstruments, static fn(array $a, array $b): int => strcmp($a['name'], $b['name']));

$sessionUsers = [];
foreach ($sessions as $session) {
    $instrumentId = (int) $session['instrument_id'];
    if (!isset($sessionInstruments[$instrumentId])) {
        continue;
    }

    $userId = (int) $session['user_id'];
    $createdAt = strtotime((string) ($session['created_at'] ?? '')) ?: 0;

    if (!isset($sessionUsers[$userId])) {
        $sessionUsers[$userId] = [
            'id' => $userId,
            'name' => (string) $session['user_name'],
            'email' => (string) $session['user_email'],
            'rut' => (string) $session['user_rut'],
            'sessions' => [],
            'finished_count' => 0,
        ];
    }

    $existing = $sessionUsers[$userId]['sessions'][$instrumentId] ?? null;
    $existingCreatedAt = $existing ? (strtotime((string) ($existing['created_at'] ?? '')) ?: 0) : 0;
    if (!$existing || $createdAt >= $existingCreatedAt) {
        $sessionUsers[$userId]['sessions'][$instrumentId] = $session;
    }

    if (in_array($session['status'], ['completed', 'expired'], true)) {
        $sessionUsers[$userId]['finished_count']++;
    }
}
uasort($sessionUsers, static fn(array $a, array $b): int => strcmp($a['name'], $b['name']));
?>

<section class="page-header">
    <div>
        <p class="text-uppercase text-primary fw-bold small mb-1">Evaluaciones</p>
        <h1 class="fw-bold mb-1">Estado Avance</h1>
        <p class="text-muted mb-0">Seguimiento por persona de las evaluaciones activas asignadas.</p>
    </div>
    <div class="d-flex flex-wrap gap-2">
        <a class="btn btn-outline-primary" href="<?= e(route_url('tests.assign')) ?>"><i class="bi bi-send-check me-1"></i> Asignar</a>
        <a class="btn btn-outline-secondary" href="<?= e(route_url('tests')) ?>"><i class="bi bi-list-check me-1"></i> Evaluaciones</a>
    </div>
</section>

<section class="content-panel evaluation-dashboard mb-4">
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-3">
        <div>
            <h2 class="h5 fw-bold mb-1">Filtros</h2>
            <p class="text-muted mb-0">Filtra solo por campos extra de usuario para ver personas con evaluaciones activas asignadas.</p>
        </div>
        <span class="badge text-bg-primary"><?= count($sessionUsers) ?> personas</span>
    </div>

    <form class="evaluation-dashboard-filters" method="get">
        <div class="row g-3 align-items-end">
            <?php foreach ($dashboardFields as $field): ?>
                <?php
                    $fieldKey = (string) $field['field_key'];
                    $fieldValue = (string) ($dashboardFieldFilters[$fieldKey] ?? '');
                    $fieldOptions = $dashboardOptions['field_options'][$fieldKey] ?? [];
                ?>
                <div class="col-12 col-md-6 col-lg-3">
                    <label class="form-label" for="dashboard_field_<?= e($fieldKey) ?>"><?= e($field['label']) ?></label>
                    <?php if ($fieldOptions): ?>
                        <select id="dashboard_field_<?= e($fieldKey) ?>" class="form-select" name="fields[<?= e($fieldKey) ?>]">
                            <option value="">Todos</option>
                            <?php foreach ($fieldOptions as $option): ?>
                                <option value="<?= e($option) ?>" <?= $fieldValue === (string) $option ? 'selected' : '' ?>><?= e($option) ?></option>
                            <?php endforeach; ?>
                        </select>
                    <?php else: ?>
                        <input id="dashboard_field_<?= e($fieldKey) ?>" class="form-control" name="fields[<?= e($fieldKey) ?>]" value="<?= e($fieldValue) ?>" placeholder="Filtrar <?= e(strtolower((string) $field['label'])) ?>">
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>

            <div class="col-12 col-md-6 col-lg-3 d-flex gap-2">
                <button class="btn btn-primary flex-fill" type="submit"><i class="bi bi-funnel me-1"></i> Filtrar</button>
                <a class="btn btn-outline-secondary" href="<?= e(route_url('tests.progress')) ?>" aria-label="Limpiar filtros"><i class="bi bi-eraser"></i></a>
            </div>
        </div>

        <?php if (!$dashboardFields): ?>
            <p class="text-muted small mb-0 mt-3">No hay campos extra activos para filtrar.</p>
        <?php endif; ?>
    </form>
</section>

<section class="content-panel mt-4">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <div>
            <h2 class="h5 fw-bold mb-1">Asignaciones y resultados recientes</h2>
            <p class="text-muted mb-0">Una fila por persona, con el estado de cada evaluacion activa asignada.</p>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <a
                class="btn btn-outline-primary <?= $hasFinishedSessions ? '' : 'disabled' ?>"
                href="<?= $hasFinishedSessions ? e($progressRankingUrl) : '#' ?>"
                <?= $hasFinishedSessions ? '' : 'aria-disabled="true" tabindex="-1" title="No hay evaluaciones terminadas para mostrar ranking"' ?>
            >
                <i class="bi bi-trophy me-1"></i> Ver Ranking Resumen
            </a>
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
    </div>
    <div class="table-responsive">
        <table
            class="table align-middle app-table app-data-table assignment-matrix-table"
            data-export-title="Estado Avance"
        >
            <thead>
                <tr>
                    <th>Usuario</th>
                    <?php foreach ($sessionInstruments as $instrument): ?>
                        <th><?= e($instrument['name']) ?></th>
                    <?php endforeach; ?>
                    <th>Terminadas</th>
                    <th class="text-end no-sort no-export">Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($sessionUsers as $sessionUser): ?>
                    <tr>
                        <td>
                            <div class="fw-semibold"><?= e($sessionUser['name']) ?></div>
                            <div class="text-muted small"><?= e($sessionUser['rut']) ?></div>
                        </td>
                        <?php foreach ($sessionInstruments as $instrument): ?>
                            <?php $session = $sessionUser['sessions'][(int) $instrument['id']] ?? null; ?>
                            <td>
                                <?php if ($session): ?>
                                    <?php $sessionStatusLabel = $sessionStatusLabels[(string) $session['status']] ?? labelize((string) $session['status']); ?>
                                    <span class="badge <?= $session['status'] === 'completed' ? 'text-bg-success' : ($session['status'] === 'in_progress' ? 'text-bg-warning' : (in_array($session['status'], ['cancelled', 'expired'], true) ? 'text-bg-secondary' : 'text-bg-primary')) ?>"><?= e($sessionStatusLabel) ?></span>
                                    <div class="text-muted small mt-1"><?= e((string) ($session['completed_at'] ?? $session['created_at'] ?? '')) ?></div>
                                <?php else: ?>
                                    <span class="text-muted small">Sin asignacion</span>
                                <?php endif; ?>
                            </td>
                        <?php endforeach; ?>
                        <td>
                            <span class="badge <?= $sessionUser['finished_count'] > 0 ? 'text-bg-success' : 'text-bg-light border' ?>"><?= (int) $sessionUser['finished_count'] ?></span>
                        </td>
                        <td class="text-end">
                            <?php if ($sessionUser['finished_count'] > 0): ?>
                                <a class="btn btn-sm btn-outline-primary" href="<?= e(route_url('test-user.results', (int) $sessionUser['id'])) ?>"><i class="bi bi-bar-chart me-1"></i> Resultado</a>
                            <?php else: ?>
                                <span class="text-muted small">No disponible</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
