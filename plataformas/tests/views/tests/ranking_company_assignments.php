<?php
$companies = is_array($companies ?? null) ? $companies : [];
$presets = is_array($presets ?? null) ? $presets : [];
$assignmentMap = is_array($assignmentMap ?? null) ? $assignmentMap : [];
$selectedCompanyId = (int) ($selectedCompanyId ?? 0);
$selectedAssignment = $assignmentMap[$selectedCompanyId] ?? null;
?>

<section class="page-header">
    <div>
        <p class="text-uppercase text-primary fw-bold small mb-1">Evaluaciones</p>
        <h1 class="fw-bold mb-1">Asignar ranking por empresa</h1>
        <p class="text-muted mb-0">Define qué matriz de ranking utilizará cada empresa en sus dashboards.</p>
    </div>
    <div class="d-flex flex-wrap gap-2">
        <a class="btn btn-outline-secondary" href="<?= e(route_url('tests.progress-ranking')) ?>"><i class="bi bi-sliders me-1"></i> Configurar matrices</a>
        <a class="btn btn-outline-secondary" href="<?= e(route_url('tests')) ?>"><i class="bi bi-arrow-left me-1"></i> Volver</a>
    </div>
</section>

<section class="content-panel mb-4">
    <div class="alert alert-info border mb-4">
        <strong>Regla de aplicación:</strong>
        el administrador general define las matrices. El administrador cliente y el supervisor solo visualizan los resultados calculados para su empresa.
        Si una empresa no tiene una matriz personalizada, se utiliza la matriz oficial.
    </div>

    <form method="post" action="<?= e(route_url('tests.ranking-company-assignments')) ?>" class="row g-3 align-items-end">
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
        <div class="col-12 col-lg-5">
            <label class="form-label" for="ranking_company_id">Empresa</label>
            <select id="ranking_company_id" class="form-select" name="company_id" onchange="this.form.method='get'; this.form.submit();">
                <?php foreach ($companies as $company): ?>
                    <?php $companyId = (int) ($company['id'] ?? 0); ?>
                    <option value="<?= $companyId ?>" <?= $companyId === $selectedCompanyId ? 'selected' : '' ?>><?= e((string) ($company['name'] ?? 'Empresa')) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-12 col-lg-5">
            <label class="form-label" for="ranking_preset_id">Matriz asignada</label>
            <select id="ranking_preset_id" class="form-select" name="preset_id">
                <option value="0">Matriz oficial (predeterminada)</option>
                <?php foreach ($presets as $preset): ?>
                    <?php $presetId = (int) ($preset['id'] ?? 0); if ($presetId <= 0 || !empty($preset['is_official'])) { continue; } ?>
                    <option value="<?= $presetId ?>" <?= (int) ($selectedAssignment['preset_id'] ?? 0) === $presetId ? 'selected' : '' ?>><?= e((string) ($preset['name'] ?? 'Configuración')) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-12 col-lg-2">
            <button class="btn btn-primary w-100" type="submit"><i class="bi bi-check2-circle me-1"></i> Guardar</button>
        </div>
    </form>
</section>

<section class="content-panel">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <div>
            <h2 class="h5 fw-bold mb-1">Asignaciones vigentes</h2>
            <p class="text-muted mb-0">La matriz se aplica automáticamente al dashboard de avance de cada empresa.</p>
        </div>
        <span class="badge text-bg-light border">Matriz oficial como respaldo</span>
    </div>
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead>
                <tr>
                    <th>Empresa</th>
                    <th>Configuración aplicada</th>
                    <th>Estado</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($companies as $company): ?>
                    <?php
                        $companyId = (int) ($company['id'] ?? 0);
                        $assignment = $assignmentMap[$companyId] ?? null;
                    ?>
                    <tr>
                        <td class="fw-semibold"><?= e((string) ($company['name'] ?? 'Empresa')) ?></td>
                        <td><?= e((string) ($assignment['preset_name'] ?? 'Matriz oficial')) ?></td>
                        <td><span class="badge <?= $assignment ? 'text-bg-success' : 'text-bg-secondary' ?>"><?= $assignment ? 'Personalizada' : 'Predeterminada' ?></span></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$companies): ?>
                    <tr><td colspan="3" class="text-muted">No hay empresas activas disponibles.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>
