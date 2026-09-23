<?php
$selectedPermissions = json_decode($values['permissions'] ?? '[]', true) ?: [];
$selectedScopes = $selectedScopes ?? [];
?>
<section class="page-header user-form-header">
    <div>
        <p class="dashboard-kicker mb-2">Administracion</p>
        <h1 class="fw-bold mb-1"><?= $id ? 'Editar perfil' : 'Nuevo perfil' ?></h1>
        <p class="text-muted mb-0">Configura permisos que luego se asignan a usuarios.</p>
    </div>
</section>

<form method="post" class="user-form-layout needs-validation" novalidate>
    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">

    <section class="card content-panel user-form-main">
        <div class="user-form-section">
            <div class="user-form-section-head">
                <span><i class="bi bi-shield-lock"></i></span>
                <div>
                    <h2>Identidad del perfil</h2>
                    <p>Define nombre, clave interna y descripcion operativa.</p>
                </div>
            </div>
            <div class="row g-3">
                <div class="col-12 col-lg-6">
                    <div class="form-floating">
                        <input id="name" class="form-control form-control-lg" name="name" value="<?= e($values['name']) ?>" placeholder="Nombre del perfil" required maxlength="120">
                        <label for="name">Nombre del perfil</label>
                    </div>
                </div>
                <div class="col-12 col-lg-6">
                    <div class="form-floating">
                        <input id="role_key" class="form-control form-control-lg" name="role_key" value="<?= e($values['role_key']) ?>" placeholder="Clave interna" <?= (int) ($values['is_system'] ?? 0) === 1 ? 'readonly' : '' ?> required maxlength="40">
                        <label for="role_key">Clave interna</label>
                    </div>
                    <div class="form-text"><?= (int) ($values['is_system'] ?? 0) === 1 ? 'Los perfiles base conservan su clave interna.' : 'Usa minusculas, numeros y guion bajo.' ?></div>
                </div>
                <div class="col-12">
                    <div class="form-floating">
                        <textarea id="description" class="form-control" name="description" placeholder="Descripcion" rows="3"><?= e($values['description'] ?? '') ?></textarea>
                        <label for="description">Descripcion</label>
                    </div>
                </div>
            </div>
        </div>

        <div class="user-form-section">
            <div class="user-form-section-head">
                <span><i class="bi bi-key"></i></span>
                <div>
                    <h2>Alcances y permisos</h2>
                    <p>Activa cada alcance y selecciona permisos disponibles por modulo.</p>
                </div>
            </div>
            <div class="permission-group-stack">
                <?php foreach ($permissionGroups as $permissionScope => $group): ?>
                    <?php $scopeEnabled = isset($selectedScopes[$permissionScope]) || (!$id && $permissionScope === 'core:core'); ?>
                    <?php $scopePermissions = $selectedScopes[$permissionScope] ?? $selectedPermissions; ?>
                    <section class="permission-group" data-permission-group="<?= e($permissionScope) ?>">
                        <div class="permission-group-header">
                            <input class="form-check-input permission-scope-toggle" type="checkbox" name="scopes[]" value="<?= e($permissionScope) ?>" <?= $scopeEnabled ? 'checked' : '' ?>>
                            <div>
                                <div class="d-flex flex-wrap align-items-center gap-2">
                                    <span class="badge text-bg-info"><?= e($group['type']) ?></span>
                                    <h3 class="h6 fw-bold mb-0"><?= e($group['label']) ?></h3>
                                </div>
                                <p class="text-muted small mb-0">Habilita este alcance para asignar permisos de <?= e($group['label']) ?>.</p>
                            </div>
                        </div>
                        <div class="permission-grid">
                            <?php foreach ($group['permissions'] as $key => $label): ?>
                                <label class="permission-option" data-permission-scope="<?= e($permissionScope) ?>">
                                    <input class="form-check-input" type="checkbox" name="permissions[<?= e($permissionScope) ?>][]" value="<?= e($key) ?>" <?= in_array($key, $scopePermissions, true) ? 'checked' : '' ?>>
                                    <span><?= e($label) ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </section>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="user-form-section">
            <div class="user-form-section-head">
                <span><i class="bi bi-toggle-on"></i></span>
                <div>
                    <h2>Reglas de uso</h2>
                    <p>Controla disponibilidad y comportamiento por defecto.</p>
                </div>
            </div>
            <div class="row g-3">
                <div class="col-12">
                    <label class="form-label" for="home_route">Pagina inicial</label>
                    <select id="home_route" class="form-select form-select-lg" name="home_route" data-profile-home-route>
                        <?php foreach ($homeRoutes as $route => $meta): ?>
                            <?php $requiredPermissions = $meta['permissions'] ?? []; ?>
                            <option
                                value="<?= e($route) ?>"
                                data-required-permissions="<?= e(json_encode($requiredPermissions, JSON_UNESCAPED_UNICODE)) ?>"
                                data-permission-match="<?= e($meta['match'] ?? 'all') ?>"
                                <?= ($values['home_route'] ?? 'dashboard') === $route ? 'selected' : '' ?>
                                <?= array_key_exists($route, $homeRouteOptions) ? '' : 'hidden disabled' ?>
                            >
                                <?= e($meta['label']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <div class="form-text">Solo se puede elegir una pagina cubierta por los permisos activos del perfil.</div>
                </div>
                <div class="col-12 col-lg-6">
                    <label class="user-form-switch">
                        <input id="is_active" class="form-check-input" type="checkbox" name="is_active" <?= (int) $values['is_active'] === 1 ? 'checked' : '' ?>>
                        <span>
                            <strong>Perfil activo</strong>
                            <small>Disponible para asignar a usuarios.</small>
                        </span>
                    </label>
                </div>
                <div class="col-12 col-lg-6">
                    <label class="user-form-switch">
                        <input id="is_default_requester" class="form-check-input" type="checkbox" name="is_default_requester" <?= (int) ($values['is_default_requester'] ?? 0) === 1 ? 'checked' : '' ?>>
                        <span>
                            <strong>Perfil por defecto para cargas masivas</strong>
                            <small>Debe estar activo y usar clave interna <code>usuario</code>.</small>
                        </span>
                    </label>
                    <div class="form-text">Al guardarlo, se desmarca cualquier otro perfil por defecto.</div>
                </div>
            </div>
        </div>

        <div class="user-form-actions">
            <button class="btn btn-primary px-4" type="submit"><i class="bi bi-check2 me-1"></i> <?= $id ? 'Guardar cambios' : 'Crear perfil' ?></button>
            <a class="btn btn-outline-secondary" href="<?= e(route_url('profiles')) ?>">Cancelar</a>
        </div>
    </section>

    <aside class="card content-panel user-form-aside">
        <div class="user-form-aside-header">
            <span><i class="bi bi-person-lock"></i></span>
            <div>
                <p class="dashboard-kicker mb-1">Resumen</p>
                <h2 class="h5 fw-bold mb-0"><?= $id ? 'Perfil existente' : 'Nuevo perfil' ?></h2>
            </div>
        </div>
        <div class="user-form-summary">
            <div>
                <span>Clave</span>
                <strong><?= e($values['role_key'] ?: 'Pendiente') ?></strong>
            </div>
            <div>
                <span>Estado</span>
                <strong><?= (int) $values['is_active'] === 1 ? 'Activo' : 'Inactivo' ?></strong>
            </div>
            <div>
                <span>Alcances</span>
                <strong><?= count($permissionGroups) ?></strong>
            </div>
        </div>
        <div class="user-form-guidance">
            <div>
                <i class="bi bi-shield-check"></i>
                <span>Activa solo los alcances que el perfil debe administrar o consumir.</span>
            </div>
            <div>
                <i class="bi bi-diagram-3"></i>
                <span>Los permisos se agrupan por plataforma y se conservan al guardar.</span>
            </div>
            <div>
                <i class="bi bi-upload"></i>
                <span>El perfil por defecto se usa en cargas masivas de usuarios solicitantes.</span>
            </div>
        </div>
    </aside>
</form>
