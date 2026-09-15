<section class="page-header">
    <div>
        <p class="text-uppercase text-primary fw-bold small mb-1">Administracion</p>
        <h1 class="fw-bold mb-1">Empresas</h1>
        <p class="text-muted mb-0">Controla las organizaciones asociadas a usuarios solicitantes.</p>
    </div>
    <a class="btn btn-primary" href="<?= e(route_url('company.new')) ?>"><i class="bi bi-building-add me-1"></i> Nueva empresa</a>
</section>

<section class="card content-panel">
    <div class="table-responsive">
        <table class="table table-hover align-middle app-table app-data-table" data-export-title="Empresas">
            <thead>
                <tr>
                    <th>Empresa</th>
                    <th>Prefijo URL</th>
                    <th>RUT / Identificador</th>
                    <th>Usuarios</th>
                    <th>Administrador</th>
                    <th>Estado</th>
                    <th class="text-end no-sort no-export">Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($companies as $company): ?>
                    <tr>
                        <td class="fw-semibold"><?= e($company['name']) ?></td>
                        <td><code>/<?= e($company['url_prefix'] ?? 'pendiente') ?></code></td>
                        <td><?= e($company['tax_id'] ?? 'Sin dato') ?></td>
                        <td><?= (int) $company['users_count'] ?></td>
                        <td>
                            <?php if ((int) ($company['admins_count'] ?? 0) > 0): ?>
                                <span class="badge text-bg-success">Configurado</span>
                            <?php else: ?>
                                <span class="badge text-bg-warning">Pendiente</span>
                            <?php endif; ?>
                        </td>
                        <td><span class="badge <?= (int) $company['is_active'] === 1 ? 'text-bg-success' : 'text-bg-secondary' ?>"><?= (int) $company['is_active'] === 1 ? 'Activa' : 'Inactiva' ?></span></td>
                        <td class="text-end">
                            <a class="btn btn-sm btn-outline-primary" href="<?= e(route_url('company.edit', (int) $company['id'])) ?>"><i class="bi bi-pencil me-1"></i> Editar</a>
                            <a class="btn btn-sm btn-outline-warning" href="<?= e(route_url('company.cleanup-users', (int) $company['id'])) ?>"><i class="bi bi-person-x me-1"></i> Limpiar usuarios de prueba</a>
                            <form class="d-inline" method="post" action="<?= e(route_url('company.delete', (int) $company['id'])) ?>" data-confirm-submit="Eliminaras permanentemente esta empresa, sus usuarios, configuraciones, evaluaciones, procesos, reportes y archivos asociados. Esta accion no se puede deshacer. ¿Deseas continuar?">
                                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                <button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash me-1"></i> Eliminar</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
