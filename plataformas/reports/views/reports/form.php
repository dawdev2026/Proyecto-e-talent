<section class="page-header">
    <div>
        <p class="text-uppercase text-primary fw-bold small mb-1">Informes</p>
        <h1 class="fw-bold mb-1"><?= $id ? 'Editar informe' : 'Nuevo informe' ?></h1>
        <p class="text-muted mb-0">Define el nombre y carga la configuración Markdown que utilizará el informe.</p>
    </div>
</section>

<form method="post" enctype="multipart/form-data" class="card content-panel needs-validation" novalidate>
    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
    <div class="row g-3">
        <div class="col-12 col-lg-6"><label class="form-label" for="name">Nombre del informe</label><input class="form-control" id="name" name="name" maxlength="160" value="<?= e($report['name']) ?>" required></div>
        <div class="col-12 col-lg-3"><label class="form-label" for="type_id">Tipo de informe</label><select class="form-select" id="type_id" name="type_id" required><option value="">Selecciona un tipo</option><?php foreach (($reportTypes ?? []) as $type): ?><option value="<?= (int) $type['id'] ?>" <?= (int) ($report['type_id'] ?? 0) === (int) $type['id'] ? 'selected' : '' ?>><?= e($type['name']) ?></option><?php endforeach; ?></select></div>
        <?php if ($id): ?>
            <div class="col-12 col-lg-3"><label class="form-label" for="status">Estado técnico</label><select class="form-select" id="status" name="status"><option value="draft" <?= $report['status'] === 'draft' ? 'selected' : '' ?>>Borrador</option><option value="active" <?= $report['status'] === 'active' ? 'selected' : '' ?>>Activo</option><option value="inactive" <?= $report['status'] === 'inactive' ? 'selected' : '' ?>>Inactivo</option></select><div class="form-text">Al guardar una versión activa, se publica automáticamente si la validación es correcta.</div></div>
        <?php else: ?>
            <input type="hidden" name="status" value="active">
            <div class="col-12 col-lg-3"><label class="form-label">Publicación</label><div class="form-control bg-body-secondary">Se publicará automáticamente al validar el Markdown.</div></div>
        <?php endif; ?>
        <div class="col-12"><label class="form-label" for="markdown_file">Archivo de configuración funcional (.md) <?= $id ? '(opcional para conservar el actual)' : '' ?></label><input class="form-control" id="markdown_file" type="file" name="markdown_file" accept=".md,text/markdown,text/plain" <?= $id ? '' : 'required' ?>><div class="form-text">Máximo 1 MB. Define fuentes, variables y contenido del informe.</div><?php if ($id): ?><div class="small text-muted mt-2">Actual: <?= e($report['source_filename']) ?> · versión <?= e((string) $report['version']) ?></div><?php endif; ?></div>
        <div class="col-12"><label class="form-label" for="functionalities">Funcionalidades del informe</label><div class="dropdown"><button class="btn btn-outline-secondary dropdown-toggle w-100 text-start" id="functionalities" type="button" data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false">Selecciona una o más funcionalidades</button><div class="dropdown-menu p-3 w-100" aria-labelledby="functionalities"><?php foreach (($reportFunctionalities ?? []) as $functionality): ?><label class="form-check d-flex gap-2 align-items-start mb-2"><input class="form-check-input mt-1" type="checkbox" name="functionalities[]" value="<?= e((string) $functionality['functionality_key']) ?>" <?= in_array((string) $functionality['functionality_key'], $selectedFunctionalities ?? [], true) ? 'checked' : '' ?>><span><strong><?= e((string) $functionality['name']) ?></strong><small class="d-block text-muted"><?= e((string) ($functionality['description'] ?? '')) ?></small></span></label><?php endforeach; ?></div></div><div class="form-text">Puedes seleccionar una o más funcionalidades. Si el MD declara funcionalidades, se cargarán inicialmente; la selección del formulario queda versionada junto con el informe.</div></div>
        <div class="col-12"><label class="form-label" for="design_markdown_file">MD gráfico (.md) <span class="text-muted">(opcional)</span></label><input class="form-control" id="design_markdown_file" type="file" name="design_markdown_file" accept=".md,text/markdown,text/plain"><div class="form-text">Define la plantilla visual declarativa: branding, formato, tarjetas y reglas de presentación. Máximo 1 MB.</div><?php if ($id && !empty($report['design_source_filename'])): ?><div class="small text-muted mt-2">Actual: <?= e($report['design_source_filename']) ?></div><?php endif; ?></div>
        <div class="col-12 d-flex flex-wrap gap-2"><button class="btn btn-primary" type="submit"><i class="bi bi-check2 me-1"></i> Guardar informe</button><a class="btn btn-outline-secondary" href="<?= e(route_url('reports.generate')) ?>">Cancelar</a></div>
    </div>
</form>
