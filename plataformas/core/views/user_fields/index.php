<section class="page-header">
    <div>
        <p class="text-uppercase text-primary fw-bold small mb-1">Administracion</p>
        <h1 class="fw-bold mb-1">Campos de usuario</h1>
        <p class="text-muted mb-0">Define datos adicionales por ambito: core, evaluaciones o futuras sub-plataformas.</p>
    </div>
    <a class="btn btn-primary" href="<?= e(route_url('user-field.new')) ?>"><i class="bi bi-ui-checks-grid me-1"></i> Nuevo campo</a>
</section>

<section class="content-panel">
    <div class="table-responsive">
        <table class="table align-middle app-table app-data-table" data-export-title="Campos de usuario">
            <thead>
                <tr>
                    <th>Dato</th>
                    <th>Ambito</th>
                    <th>Tipo</th>
                    <th>Revision</th>
                    <th>Obligatorio</th>
                    <th>Listado</th>
                    <th>Orden</th>
                    <th>Estado</th>
                    <th class="text-end no-sort no-export">Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($fields as $field): ?>
                    <tr>
                        <td>
                            <div class="fw-semibold"><?= e($field['label']) ?></div>
                            <div class="text-muted small"><?= e($field['help_text'] ?? '') ?></div>
                        </td>
                        <td><?= e($scopes[($field['scope_type'] ?? 'core') . ':' . ($field['scope_key'] ?? 'core')] ?? labelize(($field['scope_type'] ?? 'core') . ' ' . ($field['scope_key'] ?? 'core'))) ?></td>
                        <td><?= e($types[$field['field_type']] ?? $field['field_type']) ?></td>
                        <td>
                            <div><?= e($validationRules[$field['validation_rule'] ?? 'by_type'] ?? ($field['validation_rule'] ?? '')) ?></div>
                            <?php if (($field['validation_rule'] ?? '') === 'regex' && !empty($field['validation_pattern'])): ?>
                                <code class="small"><?= e($field['validation_pattern']) ?></code>
                            <?php endif; ?>
                        </td>
                        <td><?= (int) $field['is_required'] === 1 ? 'Si' : 'No' ?></td>
                        <td><?= (int) $field['show_in_list'] === 1 ? 'Si' : 'No' ?></td>
                        <td><?= (int) $field['sort_order'] ?></td>
                        <td><span class="badge <?= (int) $field['is_active'] === 1 ? 'text-bg-success' : 'text-bg-secondary' ?>"><?= (int) $field['is_active'] === 1 ? 'Activo' : 'Inactivo' ?></span></td>
                        <td class="text-end">
                            <div class="d-inline-flex gap-2">
                                <a class="btn btn-sm btn-outline-primary" href="<?= e(route_url('user-field.edit', (int) $field['id'])) ?>"><i class="bi bi-pencil me-1"></i> Editar</a>
                                <form method="post" action="<?= e(route_url('user-field.delete', (int) $field['id'])) ?>" data-confirm-submit="Eliminar este campo tambien eliminara los datos guardados para todos los usuarios. Deseas continuar?">
                                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                    <button class="btn btn-sm btn-outline-danger" type="submit"><i class="bi bi-trash me-1"></i> Eliminar</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
