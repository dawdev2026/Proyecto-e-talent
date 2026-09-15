<?php
$warningRows = is_array($warningRows ?? null) ? $warningRows : [];
$rankingWarningInstrumentColumns = is_array($rankingWarningInstrumentColumns ?? null) ? $rankingWarningInstrumentColumns : [];
$overall = is_array($overall ?? null) ? $overall : [];
$rankingScope = (string) ($rankingScope ?? 'filtered');
$rankingWarnings = max(0, (int) ($overall['ranking_warnings'] ?? count($warningRows)));
$rankingRanked = max(0, (int) ($overall['ranking_ranked'] ?? 0));
$rankingEvaluableTotal = $rankingRanked + $rankingWarnings;
$rankingWarningsPercent = $rankingEvaluableTotal > 0 ? ($rankingWarnings / $rankingEvaluableTotal) * 100 : 0;
$dashboardQueryString = (string) ($dashboardQueryString ?? '');
$dashboardBackUrl = route_url('test-process.dashboard') . ($dashboardQueryString !== '' ? '?' . $dashboardQueryString : '');
?>

<section class="page-header">
    <div>
        <p class="text-uppercase text-primary fw-bold small mb-1">Evaluaciones</p>
        <h1 class="fw-bold mb-1">Advertencias de ranking</h1>
        <p class="text-muted mb-0">Personas excluidas de R/RO/NR porque tienen datos obligatorios incompletos para la configuracion activa.</p>
    </div>
    <div class="d-flex flex-wrap gap-2">
        <a class="btn btn-outline-secondary" href="<?= e($dashboardBackUrl) ?>"><i class="bi bi-arrow-left me-1"></i> Volver al dashboard</a>
    </div>
</section>

<section class="card content-panel mb-4">
    <div class="row g-3">
        <div class="col-12 col-md-4">
            <div class="border rounded p-3 h-100">
                <span class="text-muted small fw-bold text-uppercase">Advertencias</span>
                <strong class="d-block h3 mb-0"><?= $rankingWarnings ?></strong>
                <span class="text-muted small"><?= number_format($rankingWarningsPercent, 1, ',', '.') ?>% fuera del ranking</span>
            </div>
        </div>
        <div class="col-12 col-md-4">
            <div class="border rounded p-3 h-100">
                <span class="text-muted small fw-bold text-uppercase">Ranqueados</span>
                <strong class="d-block h3 mb-0"><?= $rankingRanked ?></strong>
                <span class="text-muted small">Base R / RO / NR</span>
            </div>
        </div>
        <div class="col-12 col-md-4">
            <div class="border rounded p-3 h-100">
                <span class="text-muted small fw-bold text-uppercase">Alcance</span>
                <strong class="d-block h5 mb-1"><?= $rankingScope === 'process' ? 'Proceso completo' : 'Valores filtrados' ?></strong>
                <span class="text-muted small">Misma muestra usada por el dashboard</span>
            </div>
        </div>
    </div>
</section>

<section class="card content-panel">
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
        <div>
            <h2 class="h5 fw-bold mb-1">Detalle de personas en advertencia</h2>
            <p class="text-muted mb-0">El motivo indica que informacion falta para incluir a la persona en el ranking.</p>
        </div>
        <span class="badge text-bg-light border"><?= count($warningRows) ?> registros</span>
    </div>

    <div class="table-responsive">
        <table class="table table-hover align-middle app-table app-data-table" data-export-title="Advertencias ranking">
            <thead>
                <tr>
                    <th>Proceso</th>
                    <th>RUT</th>
                    <th>Nombre</th>
                    <?php foreach ($rankingWarningInstrumentColumns as $column): ?>
                        <th><?= e((string) ($column['label'] ?? 'Test')) ?></th>
                    <?php endforeach; ?>
                    <th>Motivo</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($warningRows as $row): ?>
                    <tr>
                        <td>
                            <div class="fw-semibold"><?= e((string) ($row['process_name'] ?? 'Proceso')) ?></div>
                            <div class="text-muted small"><code><?= e((string) ($row['process_code'] ?? '')) ?></code></div>
                        </td>
                        <td><?= e((string) ($row['rut'] ?? '')) ?></td>
                        <td><?= e((string) ($row['name'] ?? '')) ?></td>
                        <?php foreach ($rankingWarningInstrumentColumns as $column): ?>
                            <?php
                                $columnKey = (string) ($column['key'] ?? '');
                                $statuses = is_array($row['instrument_statuses'] ?? null) ? $row['instrument_statuses'] : [];
                                $status = is_array($statuses[$columnKey] ?? null)
                                    ? $statuses[$columnKey]
                                    : ['label' => 'No requerido', 'class' => 'text-bg-light border'];
                            ?>
                            <td><span class="badge <?= e((string) ($status['class'] ?? 'text-bg-light border')) ?>"><?= e((string) ($status['label'] ?? 'No requerido')) ?></span></td>
                        <?php endforeach; ?>
                        <td><?= e((string) ($row['warning'] ?? 'Datos insuficientes.')) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
