<section class="page-header">
    <div>
        <p class="text-uppercase text-primary fw-bold small mb-1">Informes</p>
        <h1 class="fw-bold mb-1">Generar informe</h1>
        <p class="text-muted mb-0"><?= e($report['name']) ?> · versión <?= e((string) $report['version']) ?></p>
    </div>
    <a class="btn btn-outline-secondary" href="<?= e(route_url('reports.generate')) ?>">Volver</a>
</section>

<section class="content-panel">
    <form method="get" action="<?= e(route_url('reports.run', (int) $report['id'])) ?>" class="row g-3">
        <div class="col-12 col-md-4">
            <label class="form-label" for="company_id">Empresa</label>
            <select class="form-select" id="company_id" name="company_id" required onchange="this.form.submit()">
                <option value="">Selecciona una empresa</option>
                <?php foreach ($companies as $company): ?>
                    <option value="<?= e((string) $company['id']) ?>" <?= (int) $company['id'] === $selectedCompanyId ? 'selected' : '' ?>><?= e($company['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-12 col-md-4">
            <label class="form-label" for="process_id">Proceso</label>
            <select class="form-select" id="process_id" name="process_id" required onchange="this.form.submit()">
                <option value="">Selecciona un proceso</option>
                <?php foreach ($processes as $process): ?>
                    <option value="<?= e((string) $process['id']) ?>" <?= (int) $process['id'] === $selectedProcessId ? 'selected' : '' ?>><?= e($process['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-12 col-md-4">
            <label class="form-label" for="user_id">Usuario</label>
            <select class="form-select" id="user_id" name="user_id" required>
                <option value="">Selecciona un usuario</option>
                <?php foreach ($users as $user): ?>
                    <option value="<?= e((string) $user['user_id']) ?>" <?= (int) $user['user_id'] === $selectedUserId ? 'selected' : '' ?>><?= e($user['name'] ?? $user['user_name'] ?? 'Usuario') ?><?= !empty($user['rut']) ? ' · ' . e($user['rut']) : '' ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-12 d-flex flex-wrap gap-2">
            <button class="btn btn-primary" type="submit" name="format" value="html"><i class="bi bi-eye me-1"></i> Vista HTML</button>
            <button class="btn btn-success" type="submit" name="format" value="pdf"><i class="bi bi-file-earmark-pdf me-1"></i> Descargar PDF</button>
        </div>
    </form>
    <?php if ($selectedCompanyId > 0 && $selectedProcessId > 0 && $users && has_permission('run_report_batches')): ?>
        <hr class="my-4">
        <form method="post" action="<?= e(route_url('reports.batch-run', (int) $report['id'])) ?>" class="row g-3" data-block-ajax="1">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="company_id" value="<?= (int) $selectedCompanyId ?>">
            <input type="hidden" name="process_id" value="<?= (int) $selectedProcessId ?>">
            <div class="col-12"><h2 class="h6 fw-bold mb-1">Generación masiva</h2><p class="text-muted mb-2">Selecciona los postulantes y genera sus PDFs en segundo plano.</p></div>
            <div class="col-12"><div class="row g-2">
                <?php foreach ($users as $user): ?>
                    <div class="col-12 col-md-6"><label class="form-check"><input class="form-check-input" type="checkbox" name="user_ids[]" value="<?= (int) ($user['user_id'] ?? 0) ?>" checked><span class="form-check-label"><?= e((string) ($user['name'] ?? 'Usuario')) ?><?= !empty($user['rut']) ? ' · ' . e((string) $user['rut']) : '' ?></span></label></div>
                <?php endforeach; ?>
            </div></div>
            <div class="col-12"><button class="btn btn-success" type="submit"><i class="bi bi-file-earmark-zip me-1"></i> Generar lote PDF y ZIP</button></div>
        </form>
    <?php endif; ?>
</section>
