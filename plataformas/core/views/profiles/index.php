<section class="page-header">
    <div>
        <p class="text-uppercase text-primary fw-bold small mb-1">Administracion</p>
        <h1 class="fw-bold mb-1">Perfiles</h1>
        <p class="text-muted mb-0">Define permisos reutilizables para controlar accesos y acciones.</p>
    </div>
    <a class="btn btn-primary" href="<?= e(route_url('profile.new')) ?>"><i class="bi bi-shield-plus me-1"></i> Nuevo perfil</a>
</section>

<section class="card content-panel">
    <div class="table-responsive">
        <table class="table table-hover align-middle app-table app-data-table" data-export-title="Perfiles">
            <thead>
                <tr>
                    <th>Perfil</th>
                    <th>Clave</th>
                    <th>Alcance</th>
                    <th>Permisos</th>
                    <th>Usuarios</th>
                    <th>Estado</th>
                    <th>Carga masiva</th>
                    <th class="text-end no-sort no-export">Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($profiles as $profile): ?>
                    <?php $permissions = json_decode($profile['permissions'] ?? '[]', true) ?: []; ?>
                    <?php $permissionsByGroup = []; ?>
                    <?php foreach ($permissionGroups as $scope => $group): ?>
                        <?php foreach ($group['permissions'] as $key => $label): ?>
                            <?php if (in_array($key, $permissions, true)) { $permissionsByGroup[$scope][$key] = $label; } ?>
                        <?php endforeach; ?>
                    <?php endforeach; ?>
                    <tr>
                        <td>
                            <div class="fw-semibold"><?= e($profile['name']) ?></div>
                            <div class="text-muted small"><?= e($profile['description'] ?? '') ?></div>
                        </td>
                        <td><code><?= e($profile['role_key']) ?></code></td>
                        <td>
                            <div class="permission-chip-list">
                                <?php foreach (array_filter(explode(',', $profile['scopes'] ?? '')) as $scope): ?>
                                    <span class="badge text-bg-info"><?= e($scope === 'core:core' ? 'Core' : $scope) ?></span>
                                <?php endforeach; ?>
                            </div>
                        </td>
                        <td>
                            <div class="profile-permission-summary">
                                <?php foreach ($permissionsByGroup as $scope => $items): ?>
                                    <div class="profile-permission-group">
                                        <span class="permission-group-label"><?= e($permissionGroups[$scope]['label']) ?></span>
                                        <div class="permission-chip-list">
                                            <?php foreach (array_slice($items, 0, 3, true) as $permission => $label): ?>
                                                <span class="badge text-bg-info"><?= e($label) ?></span>
                                            <?php endforeach; ?>
                                            <?php if (count($items) > 3): ?>
                                                <span class="badge text-bg-secondary">+<?= count($items) - 3 ?></span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                                <?php if (!$permissionsByGroup): ?>
                                    <span class="text-muted small">Sin permisos</span>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td><?= (int) $profile['users_count'] ?></td>
                        <td><span class="badge <?= (int) $profile['is_active'] === 1 ? 'text-bg-success' : 'text-bg-secondary' ?>"><?= (int) $profile['is_active'] === 1 ? 'Activo' : 'Inactivo' ?></span></td>
                        <td>
                            <?php if ((int) ($profile['is_default_requester'] ?? 0) === 1): ?>
                                <span class="badge text-bg-primary">Por defecto</span>
                            <?php else: ?>
                                <span class="text-muted small">-</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-end">
                            <a class="btn btn-sm btn-outline-primary" href="<?= e(route_url('profile.edit', (int) $profile['id'])) ?>"><i class="bi bi-pencil me-1"></i> Editar</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
