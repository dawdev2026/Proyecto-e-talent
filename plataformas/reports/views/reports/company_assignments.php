<section class="page-header">
    <div>
        <p class="text-uppercase text-primary fw-bold small mb-1">Informes</p>
        <h1 class="fw-bold mb-1">Asignar Informes por Empresas</h1>
        <p class="text-muted mb-0">Define qué informes activos estarán disponibles para cada empresa.</p>
    </div>
</section>

<section class="card content-panel">
    <form method="get" class="row g-3 align-items-end mb-4">
        <div class="col-12 col-lg-8">
            <label class="form-label" for="company_id">Empresa</label>
            <select class="form-select" id="company_id" name="company_id" required>
                <option value="">Selecciona una empresa</option>
                <?php foreach ($companies as $company): ?>
                    <option value="<?= e((string) $company['id']) ?>" <?= $selectedCompany && (int) $selectedCompany['id'] === (int) $company['id'] ? 'selected' : '' ?>><?= e($company['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-12 col-lg-4"><button class="btn btn-outline-primary w-100" type="submit"><i class="bi bi-search me-1"></i> Revisar asignaciones</button></div>
    </form>

    <?php if ($selectedCompany): ?>
        <form method="post">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="company_id" value="<?= e((string) $selectedCompany['id']) ?>">
            <div class="d-flex justify-content-between align-items-center mb-3"><div><h2 class="h5 fw-bold mb-1"><?= e($selectedCompany['name']) ?></h2><p class="text-muted mb-0">Selecciona los informes que la empresa podrá utilizar.</p></div><button class="btn btn-primary" type="submit"><i class="bi bi-check2 me-1"></i> Guardar asignaciones</button></div>
            <div class="row g-3">
                <?php foreach ($reportAssignments as $report): ?>
                    <div class="col-12 col-lg-6"><label class="border rounded p-3 d-flex gap-3 align-items-start h-100"><input class="form-check-input mt-1" type="checkbox" name="report_ids[]" value="<?= e((string) $report['id']) ?>" <?= (int) $report['is_assigned'] === 1 ? 'checked' : '' ?> <?= $report['status'] !== 'active' ? 'disabled' : '' ?>><span><strong><?= e($report['name']) ?></strong><small class="d-block text-muted">v<?= e((string) $report['version']) ?> · <?= e(ucfirst($report['status'])) ?></small></span></label></div>
                <?php endforeach; ?>
            </div>
            <?php if (!$reportAssignments): ?><p class="text-muted mt-3 mb-0">No hay informes registrados todavía.</p><?php endif; ?>
        </form>
    <?php else: ?>
        <div class="alert alert-light border mb-0"><i class="bi bi-info-circle me-2"></i>Selecciona una empresa para administrar sus asignaciones.</div>
    <?php endif; ?>
</section>
