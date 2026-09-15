<?php if (empty($isDrawer)): ?>
    <section class="page-header user-form-header">
        <div>
            <p class="dashboard-kicker mb-2">Administracion</p>
            <h1 class="fw-bold mb-1"><?= $id ? 'Editar usuario' : 'Nuevo usuario' ?></h1>
            <p class="text-muted mb-0"><?= $id ? 'Actualiza la cuenta y su perfil de acceso.' : 'Crea una cuenta de administracion, operacion o usuario de empresa.' ?></p>
        </div>
    </section>
<?php else: ?>
    <div class="drawer-detail-heading">
        <p class="dashboard-kicker mb-2">Administracion</p>
        <h3 class="h5 fw-bold mb-0"><?= $id ? 'Editar usuario' : 'Nuevo usuario' ?></h3>
    </div>
<?php endif; ?>

<form method="post" action="<?= e($formAction ?? '') ?>" class="user-form-layout needs-validation <?= !empty($isDrawer) ? 'user-form-layout-drawer' : '' ?>" <?= !empty($isDrawer) ? 'data-user-drawer-form' : '' ?> novalidate>
    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">

    <section class="card content-panel user-form-main">
        <div class="user-form-section">
            <div class="user-form-section-head">
                <span><i class="bi bi-person-vcard"></i></span>
                <div>
                    <h2>Identificacion</h2>
                    <p>Datos base del usuario y contacto principal.</p>
                </div>
            </div>
            <div class="row g-3">
                <div class="col-12 col-xl-4">
                    <div class="form-floating">
                        <input id="rut" class="form-control form-control-lg" name="rut" value="<?= e($values['rut'] ?? '') ?>" placeholder="12.345.678-5" maxlength="12" data-rut-input required>
                        <label for="rut">RUT</label>
                    </div>
                    <div class="form-text">Debe ser valido y unico.</div>
                </div>
                <div class="col-12 col-xl-4">
                    <div class="form-floating">
                        <input id="email" class="form-control form-control-lg" type="email" name="email" value="<?= e($values['email']) ?>" placeholder="correo@dominio.cl" required>
                        <label for="email">Correo</label>
                    </div>
                    <div class="form-text">Debe ser valido y unico.</div>
                </div>
                <div class="col-12 col-lg-6">
                    <div class="form-floating">
                        <input id="first_names" class="form-control form-control-lg" name="first_names" value="<?= e($values['first_names'] ?? '') ?>" placeholder="Nombres" required>
                        <label for="first_names">Nombres</label>
                    </div>
                </div>
                <div class="col-12 col-lg-6">
                    <div class="form-floating">
                        <input id="last_names" class="form-control form-control-lg" name="last_names" value="<?= e($values['last_names'] ?? '') ?>" placeholder="Apellidos" required>
                        <label for="last_names">Apellidos</label>
                    </div>
                </div>
            </div>
        </div>

        <div class="user-form-section">
            <div class="user-form-section-head">
                <span><i class="bi bi-calendar2-week"></i></span>
                <div>
                    <h2>Datos personales</h2>
                    <p>Informacion demografica usada por evaluaciones y reportes.</p>
                </div>
            </div>
            <div class="row g-3">
                <div class="col-12 col-md-4">
                    <div class="form-floating">
                        <select id="sex" class="form-select form-select-lg" name="sex" required>
                            <option value="">Selecciona</option>
                            <option value="masculino" <?= ($values['sex'] ?? '') === 'masculino' ? 'selected' : '' ?>>Masculino</option>
                            <option value="femenino" <?= ($values['sex'] ?? '') === 'femenino' ? 'selected' : '' ?>>Femenino</option>
                            <option value="no_informado" <?= ($values['sex'] ?? '') === 'no_informado' ? 'selected' : '' ?>>No informado</option>
                        </select>
                        <label for="sex">Sexo</label>
                    </div>
                </div>
                <div class="col-12 col-md-4">
                    <div class="form-floating">
                        <input id="birth_date" class="form-control form-control-lg" type="date" name="birth_date" value="<?= e($values['birth_date'] ?? '') ?>" max="<?= e(date('Y-m-d')) ?>" placeholder="Fecha de nacimiento" required>
                        <label for="birth_date">Fecha de nacimiento</label>
                    </div>
                </div>
                <div class="col-12 col-md-4">
                    <div class="form-floating">
                        <input id="age" class="form-control form-control-lg" type="number" name="age" value="<?= e($values['age'] ?? '') ?>" min="0" max="120" placeholder="Edad" data-age-input required>
                        <label for="age">Edad</label>
                    </div>
                    <div class="form-text">Debe coincidir con la fecha de nacimiento.</div>
                </div>
            </div>
        </div>

        <div class="user-form-section">
            <div class="user-form-section-head">
                <span><i class="bi bi-shield-check"></i></span>
                <div>
                    <h2>Acceso y asignacion</h2>
                    <p>Define empresa, perfil de permisos y credenciales iniciales.</p>
                </div>
            </div>
            <div class="row g-3">
                <div class="col-12 col-lg-6">
                    <div class="form-floating">
                        <select id="company_id" class="form-select form-select-lg" name="company_id" required>
                            <option value="">Selecciona empresa</option>
                            <?php foreach ($companies as $company): ?>
                                <option value="<?= (int) $company['id'] ?>" <?= (int) $values['company_id'] === (int) $company['id'] ? 'selected' : '' ?>><?= e($company['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <label for="company_id">Empresa</label>
                    </div>
                </div>
                <div class="col-12 col-lg-6">
                    <div class="form-floating">
                        <select id="profile_id" class="form-select form-select-lg" name="profile_id" required>
                            <option value="">Selecciona perfil</option>
                            <?php foreach ($profiles as $profile): ?>
                                <option
                                    value="<?= (int) $profile['id'] ?>"
                                    data-base-role="<?= e((string) ($profile['base_role'] ?? 'usuario')) ?>"
                                    <?= (int) ($values['profile_id'] ?? 0) === (int) $profile['id'] ? 'selected' : '' ?>
                                ><?= e($profile['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <label for="profile_id">Perfil</label>
                    </div>
                </div>
                <div class="col-12 col-lg-8">
                    <div class="form-floating">
                        <input id="password" class="form-control form-control-lg" type="password" name="password" placeholder="Password o contrasena">
                        <label for="password">Password o contrasena</label>
                    </div>
                    <div class="form-text">Si queda vacia, se usaran los ultimos 4 digitos del RUT antes del guion.</div>
                </div>
                <div class="col-12 col-lg-4">
                    <label class="user-form-switch">
                        <input id="is_active" class="form-check-input" type="checkbox" name="is_active" <?= (int) $values['is_active'] === 1 ? 'checked' : '' ?>>
                        <span>
                            <strong>Usuario activo</strong>
                            <small>Permite el acceso a la plataforma.</small>
                        </span>
                    </label>
                </div>
            </div>
        </div>

        <?php if ($fieldDefinitions): ?>
            <div class="user-form-section">
                <div class="user-form-section-head">
                    <span><i class="bi bi-ui-checks-grid"></i></span>
                    <div>
                        <h2>Campos definidos para usuarios</h2>
                        <p>Estos campos se administran desde la configuracion de campos de usuario.</p>
                    </div>
                </div>
                <div class="row g-3">
                    <?php foreach ($fieldDefinitions as $field): ?>
                        <?php $fieldId = (int) $field['id']; $fieldValue = $fieldValues[$fieldId] ?? ''; ?>
                        <div class="col-12 <?= $field['field_type'] === 'textarea' ? '' : 'col-lg-6' ?>">
                            <?php if ($field['field_type'] === 'textarea'): ?>
                                <div class="form-floating">
                                    <textarea id="field_<?= $fieldId ?>" class="form-control" name="field_values[<?= $fieldId ?>]" placeholder="<?= e($field['label']) ?>" rows="3" <?= (int) $field['is_required'] === 1 ? 'required' : '' ?>><?= e($fieldValue) ?></textarea>
                                    <label for="field_<?= $fieldId ?>"><?= e($field['label']) ?></label>
                                </div>
                            <?php elseif ($field['field_type'] === 'select'): ?>
                                <div class="form-floating">
                                    <select id="field_<?= $fieldId ?>" class="form-select" name="field_values[<?= $fieldId ?>]" <?= (int) $field['is_required'] === 1 ? 'required' : '' ?>>
                                        <option value="">Selecciona</option>
                                        <?php foreach ((new UserFieldModel())->optionList($field['options'] ?? '') as $option): ?>
                                            <option value="<?= e($option) ?>" <?= $fieldValue === $option ? 'selected' : '' ?>><?= e($option) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <label for="field_<?= $fieldId ?>"><?= e($field['label']) ?></label>
                                </div>
                            <?php elseif ($field['field_type'] === 'checkbox'): ?>
                                <label class="user-form-switch user-form-switch-compact">
                                    <input id="field_<?= $fieldId ?>" class="form-check-input" type="checkbox" name="field_values[<?= $fieldId ?>]" value="1" <?= $fieldValue === '1' ? 'checked' : '' ?>>
                                    <span>
                                        <strong><?= e($field['label']) ?></strong>
                                        <small>Si</small>
                                    </span>
                                </label>
                            <?php else: ?>
                                <?php
                                $inputType = [
                                    'email' => 'email',
                                    'number' => 'number',
                                    'date' => 'date',
                                    'phone' => 'tel',
                                ][$field['field_type']] ?? 'text';
                                ?>
                                <div class="form-floating">
                                    <input id="field_<?= $fieldId ?>" class="form-control" type="<?= e($inputType) ?>" name="field_values[<?= $fieldId ?>]" value="<?= e($fieldValue) ?>" placeholder="<?= e($field['label']) ?>" <?= (int) $field['is_required'] === 1 ? 'required' : '' ?>>
                                    <label for="field_<?= $fieldId ?>"><?= e($field['label']) ?></label>
                                </div>
                            <?php endif; ?>
                            <?php if (!empty($field['help_text'])): ?>
                                <div class="form-text"><?= e($field['help_text']) ?></div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

        <div class="user-form-actions">
            <button class="btn btn-primary px-4" type="submit"><i class="bi bi-check2 me-1"></i> <?= $id ? 'Guardar cambios' : 'Crear usuario' ?></button>
            <?php if (!empty($isDrawer)): ?>
                <button class="btn btn-outline-secondary" type="button" data-app-drawer-close>Cancelar</button>
            <?php else: ?>
                <a class="btn btn-outline-secondary" href="<?= e(route_url('users')) ?>">Cancelar</a>
            <?php endif; ?>
        </div>
    </section>

    <aside class="card content-panel user-form-aside">
        <div class="user-form-aside-header">
            <span><i class="bi bi-person-badge"></i></span>
            <div>
                <p class="dashboard-kicker mb-1">Resumen</p>
                <h2 class="h5 fw-bold mb-0"><?= $id ? 'Usuario existente' : 'Nueva cuenta' ?></h2>
            </div>
        </div>
        <div class="user-form-summary">
            <div>
                <span>Estado</span>
                <strong><?= (int) $values['is_active'] === 1 ? 'Activo' : 'Inactivo' ?></strong>
            </div>
            <div>
                <span>Campos extra</span>
                <strong><?= count($fieldDefinitions) ?></strong>
            </div>
        </div>
        <div class="user-form-guidance">
            <div>
                <i class="bi bi-check2-circle"></i>
                <span>El perfil seleccionado define permisos, accesos y comportamiento dentro de la plataforma.</span>
            </div>
            <div>
                <i class="bi bi-calendar-check"></i>
                <span>La edad se calcula al cambiar la fecha de nacimiento.</span>
            </div>
            <div>
                <i class="bi bi-key"></i>
                <span>Si no defines password, se usara la regla automatica basada en el RUT.</span>
            </div>
        </div>
    </aside>
</form>
