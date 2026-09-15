<?php
$processUser = $processUser ?? [];
$fieldDefinitions = $fieldDefinitions ?? [];
$fieldValues = $fieldValues ?? [];
?>

<div class="drawer-detail-heading">
    <p class="dashboard-kicker mb-2">Proceso</p>
    <h3 class="h5 fw-bold mb-0">Editar datos del usuario</h3>
    <p class="text-muted mb-0"><?= e((string) ($process['name'] ?? 'Proceso')) ?></p>
</div>

<form method="post" action="<?= e($formAction ?? '') ?>" class="user-form-layout user-form-layout-drawer needs-validation" data-user-drawer-form novalidate>
    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">

    <section class="content-panel user-form-main">
        <div class="user-form-section">
            <div class="user-form-section-head">
                <span><i class="bi bi-person-vcard"></i></span>
                <div>
                    <h2>Identificacion</h2>
                    <p>Datos base usados para identificar al usuario en la plataforma.</p>
                </div>
            </div>
            <div class="row g-3">
                <div class="col-12 col-lg-4">
                    <div class="form-floating">
                        <input id="process_user_rut" class="form-control form-control-lg" name="rut" value="<?= e((string) ($processUser['rut'] ?? '')) ?>" placeholder="12.345.678-5" maxlength="12" data-rut-input required>
                        <label for="process_user_rut">RUT</label>
                    </div>
                </div>
                <div class="col-12 col-lg-4">
                    <div class="form-floating">
                        <input id="process_user_email" class="form-control form-control-lg" type="email" name="email" value="<?= e((string) ($processUser['email'] ?? '')) ?>" placeholder="correo@dominio.cl" required>
                        <label for="process_user_email">Correo</label>
                    </div>
                </div>
                <div class="col-12 col-lg-6">
                    <div class="form-floating">
                        <input id="process_user_first_names" class="form-control form-control-lg" name="first_names" value="<?= e((string) ($processUser['first_names'] ?? '')) ?>" placeholder="Nombres" required>
                        <label for="process_user_first_names">Nombres</label>
                    </div>
                </div>
                <div class="col-12 col-lg-6">
                    <div class="form-floating">
                        <input id="process_user_last_names" class="form-control form-control-lg" name="last_names" value="<?= e((string) ($processUser['last_names'] ?? '')) ?>" placeholder="Apellidos" required>
                        <label for="process_user_last_names">Apellidos</label>
                    </div>
                </div>
                <div class="col-12">
                    <div class="form-floating">
                        <input id="process_user_password" class="form-control form-control-lg" type="password" name="password" placeholder="Nueva contrasena">
                        <label for="process_user_password">Contraseña</label>
                    </div>
                    <div class="form-text">Si queda vacia, se conserva la contraseña actual.</div>
                </div>
            </div>
        </div>

        <div class="user-form-section">
            <div class="user-form-section-head">
                <span><i class="bi bi-calendar2-week"></i></span>
                <div>
                    <h2>Datos personales</h2>
                    <p>Informacion demografica registrada al ingresar al usuario.</p>
                </div>
            </div>
            <div class="row g-3">
                <div class="col-12 col-md-4">
                    <div class="form-floating">
                        <select id="process_user_sex" class="form-select form-select-lg" name="sex" required>
                            <option value="">Selecciona</option>
                            <option value="masculino" <?= ($processUser['sex'] ?? '') === 'masculino' ? 'selected' : '' ?>>Masculino</option>
                            <option value="femenino" <?= ($processUser['sex'] ?? '') === 'femenino' ? 'selected' : '' ?>>Femenino</option>
                            <option value="no_informado" <?= ($processUser['sex'] ?? '') === 'no_informado' ? 'selected' : '' ?>>No informado</option>
                        </select>
                        <label for="process_user_sex">Sexo</label>
                    </div>
                </div>
                <div class="col-12 col-md-4">
                    <div class="form-floating">
                        <input id="process_user_birth_date" class="form-control form-control-lg" type="date" name="birth_date" value="<?= e((string) ($processUser['birth_date'] ?? '')) ?>" max="<?= e(date('Y-m-d')) ?>" placeholder="Fecha de nacimiento" required>
                        <label for="process_user_birth_date">Fecha de nacimiento</label>
                    </div>
                </div>
                <div class="col-12 col-md-4">
                    <div class="form-floating">
                        <input id="process_user_age" class="form-control form-control-lg" type="number" name="age" value="<?= e((string) ($processUser['age'] ?? '')) ?>" min="0" max="120" placeholder="Edad" data-age-input required>
                        <label for="process_user_age">Edad</label>
                    </div>
                </div>
            </div>
        </div>

        <?php if ($fieldDefinitions): ?>
            <div class="user-form-section">
                <div class="user-form-section-head">
                    <span><i class="bi bi-ui-checks-grid"></i></span>
                    <div>
                        <h2>Campos definidos para usuarios</h2>
                        <p>Campos adicionales configurados para usuarios de la plataforma.</p>
                    </div>
                </div>
                <div class="row g-3">
                    <?php foreach ($fieldDefinitions as $field): ?>
                        <?php $fieldId = (int) $field['id']; $fieldValue = $fieldValues[$fieldId] ?? ''; ?>
                        <div class="col-12 <?= $field['field_type'] === 'textarea' ? '' : 'col-lg-6' ?>">
                            <?php if ($field['field_type'] === 'textarea'): ?>
                                <div class="form-floating">
                                    <textarea id="process_user_field_<?= $fieldId ?>" class="form-control" name="field_values[<?= $fieldId ?>]" placeholder="<?= e((string) $field['label']) ?>" rows="3" <?= (int) $field['is_required'] === 1 ? 'required' : '' ?>><?= e((string) $fieldValue) ?></textarea>
                                    <label for="process_user_field_<?= $fieldId ?>"><?= e((string) $field['label']) ?></label>
                                </div>
                            <?php elseif ($field['field_type'] === 'select'): ?>
                                <div class="form-floating">
                                    <select id="process_user_field_<?= $fieldId ?>" class="form-select" name="field_values[<?= $fieldId ?>]" <?= (int) $field['is_required'] === 1 ? 'required' : '' ?>>
                                        <option value="">Selecciona</option>
                                        <?php foreach ((new UserFieldModel())->optionList($field['options'] ?? '') as $option): ?>
                                            <option value="<?= e($option) ?>" <?= $fieldValue === $option ? 'selected' : '' ?>><?= e($option) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <label for="process_user_field_<?= $fieldId ?>"><?= e((string) $field['label']) ?></label>
                                </div>
                            <?php elseif ($field['field_type'] === 'checkbox'): ?>
                                <label class="user-form-switch user-form-switch-compact">
                                    <input id="process_user_field_<?= $fieldId ?>" class="form-check-input" type="checkbox" name="field_values[<?= $fieldId ?>]" value="1" <?= $fieldValue === '1' ? 'checked' : '' ?>>
                                    <span>
                                        <strong><?= e((string) $field['label']) ?></strong>
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
                                    <input id="process_user_field_<?= $fieldId ?>" class="form-control" type="<?= e($inputType) ?>" name="field_values[<?= $fieldId ?>]" value="<?= e((string) $fieldValue) ?>" placeholder="<?= e((string) $field['label']) ?>" <?= (int) $field['is_required'] === 1 ? 'required' : '' ?>>
                                    <label for="process_user_field_<?= $fieldId ?>"><?= e((string) $field['label']) ?></label>
                                </div>
                            <?php endif; ?>
                            <?php if (!empty($field['help_text'])): ?>
                                <div class="form-text"><?= e((string) $field['help_text']) ?></div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

        <div class="user-form-actions">
            <button class="btn btn-primary px-4" type="submit"><i class="bi bi-check2 me-1"></i> Guardar cambios</button>
            <button class="btn btn-outline-secondary" type="button" data-app-drawer-close>Cancelar</button>
        </div>
    </section>
</form>
