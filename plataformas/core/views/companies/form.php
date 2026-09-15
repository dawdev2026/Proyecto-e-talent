<section class="page-header user-form-header">
    <div>
        <p class="dashboard-kicker mb-2">Administracion</p>
        <h1 class="fw-bold mb-1"><?= $id ? 'Editar empresa' : 'Nueva empresa' ?></h1>
        <p class="text-muted mb-0">Define la empresa que agrupa usuarios solicitantes.</p>
    </div>
</section>

<form method="post" class="user-form-layout needs-validation" novalidate>
    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">

    <section class="content-panel user-form-main">
        <div class="user-form-section">
            <div class="user-form-section-head">
                <span><i class="bi bi-buildings"></i></span>
                <div>
                    <h2>Datos de empresa</h2>
                    <p>Informacion base para asociar usuarios, evaluaciones y reportes.</p>
                </div>
            </div>
            <div class="row g-3">
                <div class="col-12 col-lg-7">
                    <div class="form-floating">
                        <input id="name" class="form-control form-control-lg" name="name" value="<?= e($values['name']) ?>" maxlength="160" placeholder="Nombre de empresa" required>
                        <label for="name">Nombre de empresa</label>
                    </div>
                </div>
                <div class="col-12 col-lg-5">
                    <div class="form-floating">
                        <input id="tax_id" class="form-control form-control-lg" name="tax_id" value="<?= e($values['tax_id'] ?? '') ?>" maxlength="60" placeholder="RUT / Identificador">
                        <label for="tax_id">RUT / Identificador</label>
                    </div>
                </div>
                <div class="col-12 col-lg-5">
                    <div class="form-floating">
                        <input id="url_prefix" class="form-control form-control-lg" name="url_prefix" value="<?= e($values['url_prefix'] ?? '') ?>" maxlength="80" pattern="[a-z0-9]+(?:-[a-z0-9]+)*" placeholder="Prefijo de URL" required>
                        <label for="url_prefix">Prefijo de URL</label>
                    </div>
                    <small class="form-text text-muted">Ejemplo: <code>dt</code> → <code>/dt</code>. Usa letras, números y guiones.</small>
                </div>
                <div class="col-12 col-lg-5">
                    <label class="user-form-switch">
                        <input id="is_active" class="form-check-input" type="checkbox" name="is_active" <?= (int) $values['is_active'] === 1 ? 'checked' : '' ?>>
                        <span>
                            <strong>Empresa activa</strong>
                            <small>Disponible para asignar usuarios y operaciones.</small>
                        </span>
                    </label>
                </div>
            </div>
        </div>

        <?php if (!$id || empty($companyAdmins)): ?>
            <div class="user-form-section">
                <div class="user-form-section-head">
                    <span><i class="bi bi-person-badge"></i></span>
                    <div>
                        <h2><?= $id ? 'Regularizar administrador' : 'Administrador inicial' ?></h2>
                        <p><?= $id ? 'Esta empresa no tiene un administrador activo. Completa estos datos para habilitar su acceso.' : 'Se creara un administrador con acceso a usuarios, procesos y entrevistas de esta empresa.' ?></p>
                    </div>
                </div>
                <div class="row g-3">
                    <div class="col-12 col-lg-6">
                        <div class="form-floating">
                            <input id="admin_first_names" class="form-control form-control-lg" name="admin_first_names" value="<?= e($values['admin_first_names'] ?? '') ?>" maxlength="120" placeholder="Nombres" required>
                            <label for="admin_first_names">Nombres</label>
                        </div>
                    </div>
                    <div class="col-12 col-lg-6">
                        <div class="form-floating">
                            <input id="admin_last_names" class="form-control form-control-lg" name="admin_last_names" value="<?= e($values['admin_last_names'] ?? '') ?>" maxlength="120" placeholder="Apellidos" required>
                            <label for="admin_last_names">Apellidos</label>
                        </div>
                    </div>
                    <div class="col-12 col-lg-6">
                        <div class="form-floating">
                            <input id="admin_email" class="form-control form-control-lg" type="email" name="admin_email" value="<?= e($values['admin_email'] ?? '') ?>" maxlength="160" placeholder="Correo" required>
                            <label for="admin_email">Correo de acceso</label>
                        </div>
                    </div>
                    <div class="col-12 col-lg-6">
                        <div class="form-floating">
                            <input id="admin_password" class="form-control form-control-lg" type="password" name="admin_password" minlength="8" placeholder="Contraseña" required>
                            <label for="admin_password">Contraseña inicial</label>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <div class="user-form-actions">
            <button class="btn btn-primary px-4" type="submit"><i class="bi bi-check2 me-1"></i> <?= $id ? 'Guardar cambios' : 'Crear empresa' ?></button>
            <a class="btn btn-outline-secondary" href="<?= e(route_url('companies')) ?>">Cancelar</a>
        </div>
    </section>

    <aside class="content-panel user-form-aside">
        <div class="user-form-aside-header">
            <span><i class="bi bi-building-check"></i></span>
            <div>
                <p class="dashboard-kicker mb-1">Resumen</p>
                <h2 class="h5 fw-bold mb-0"><?= $id ? 'Empresa existente' : 'Nueva empresa' ?></h2>
            </div>
        </div>
        <div class="user-form-summary">
            <div>
                <span>Estado</span>
                <strong><?= (int) $values['is_active'] === 1 ? 'Activa' : 'Inactiva' ?></strong>
            </div>
            <div>
                <span>Identificador</span>
                <strong><?= e($values['tax_id'] ?: 'Pendiente') ?></strong>
            </div>
            <div>
                <span>Ruta base</span>
                <strong>/<?= e($values['url_prefix'] ?? 'pendiente') ?></strong>
            </div>
        </div>
        <div class="user-form-guidance">
            <div>
                <i class="bi bi-people"></i>
                <span>Las empresas permiten agrupar usuarios y controlar su contexto operativo.</span>
            </div>
            <div>
                <i class="bi bi-toggle-on"></i>
                <span>Una empresa inactiva queda registrada, pero no deberia usarse para nuevas asignaciones.</span>
            </div>
        </div>
    </aside>
</form>
