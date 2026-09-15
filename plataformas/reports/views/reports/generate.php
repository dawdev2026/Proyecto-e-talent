<section class="page-header">
    <div>
        <p class="text-uppercase text-primary fw-bold small mb-1">Informes</p>
        <h1 class="fw-bold mb-1">Registrar Informes</h1>
        <p class="text-muted mb-0">Mantenedor de informes configurados mediante archivos Markdown. La generación operativa se realizará desde otro proceso.</p>
    </div>
    <a class="btn btn-primary" href="<?= e(route_url('reports.new')) ?>"><i class="bi bi-plus-lg me-1"></i> Nuevo informe</a>
</section>

<section class="card content-panel">
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead>
                <tr>
                    <th>Informe</th>
                    <th>Configuración</th>
                    <th>Versión</th>
                    <th>Estado</th>
                    <th>Empresas</th>
                    <th class="text-end">Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!$reports): ?>
                    <tr><td colspan="6" class="text-center text-muted py-5">No hay informes configurados.</td></tr>
                <?php else: ?>
                    <?php foreach ($reports as $report): ?>
                        <tr>
                            <td><strong><?= e($report['name']) ?></strong><br><small class="text-muted"><?= e($report['slug']) ?></small></td>
                            <td><?= e($report['source_filename']) ?><br><small class="text-muted font-monospace"><?= e(substr($report['content_sha256'], 0, 12)) ?>…</small></td>
                            <td>v<?= e((string) $report['version']) ?></td>
                            <td><span class="badge text-bg-<?= $report['status'] === 'active' ? 'success' : ($report['status'] === 'inactive' ? 'secondary' : 'warning') ?>"><?= e(ucfirst($report['status'])) ?></span></td>
                            <td><?= e((string) $report['companies_count']) ?></td>
                            <td class="text-end d-flex justify-content-end gap-2"><a class="btn btn-sm btn-primary" href="<?= e(route_url('reports.run', (int) $report['id'])) ?>"><i class="bi bi-file-earmark-play me-1"></i> Generar</a><a class="btn btn-sm btn-outline-primary" href="<?= e(route_url('reports.edit', (int) $report['id'])) ?>"><i class="bi bi-pencil me-1"></i> Editar</a><form method="post" action="<?= e(route_url('reports.delete', (int) $report['id'])) ?>" class="d-inline" data-confirm-submit="¿Eliminar este informe? Se conservarán sus versiones e historial, pero dejará de estar disponible."><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><button class="btn btn-sm btn-outline-danger" type="submit"><i class="bi bi-trash me-1"></i> Eliminar</button></form><?php if (($report['approval_status'] ?? '') === 'review' && has_permission('approve_reports')): ?><form method="post" action="<?= e(route_url('reports.approve', (int) $report['id'])) ?>" class="d-inline"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><button class="btn btn-sm btn-outline-success" type="submit">Publicar pendiente</button></form><?php endif; ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>
