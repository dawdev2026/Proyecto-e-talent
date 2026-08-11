<?php
$fieldErrors = $row['field_errors'] ?? [];
$data = $row['data'] ?? [];
?>
<form class="import-row-drawer-content" method="post" action="<?= e($saveUrl) ?>" data-import-row-correction-form>
    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
    <input type="hidden" name="row_index" value="<?= (int) $index ?>">
    <div class="drawer-detail-heading">
        <p class="text-uppercase text-primary fw-bold small mb-1">Correccion de fila</p>
        <h3 class="h5 fw-bold mb-0">Fila <?= (int) $row['row_number'] ?></h3>
    </div>
    <?php if (!empty($row['errors'])): ?>
        <div class="alert alert-warning" data-inline-alert>
            <div class="fw-semibold mb-1">Problemas detectados</div>
            <ul class="mb-0">
                <?php foreach ($row['errors'] as $error): ?>
                    <li><?= e($error) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>
    <div class="row g-3">
        <div class="col-12 col-lg-4">
            <label class="form-label">RUT</label>
            <input class="form-control <?= isset($fieldErrors['rut']) ? 'is-invalid' : '' ?>" name="data[rut]" value="<?= e($data['rut'] ?? '') ?>" maxlength="12" data-rut-input>
            <?php foreach (($fieldErrors['rut'] ?? []) as $error): ?><div class="invalid-feedback d-block"><?= e($error) ?></div><?php endforeach; ?>
        </div>
        <div class="col-12 col-lg-4">
            <label class="form-label">Nombres</label>
            <input class="form-control <?= isset($fieldErrors['first_names']) ? 'is-invalid' : '' ?>" name="data[first_names]" value="<?= e($data['first_names'] ?? '') ?>">
            <?php foreach (($fieldErrors['first_names'] ?? []) as $error): ?><div class="invalid-feedback d-block"><?= e($error) ?></div><?php endforeach; ?>
        </div>
        <div class="col-12 col-lg-4">
            <label class="form-label">Apellidos</label>
            <input class="form-control <?= isset($fieldErrors['last_names']) ? 'is-invalid' : '' ?>" name="data[last_names]" value="<?= e($data['last_names'] ?? '') ?>">
            <?php foreach (($fieldErrors['last_names'] ?? []) as $error): ?><div class="invalid-feedback d-block"><?= e($error) ?></div><?php endforeach; ?>
        </div>
        <div class="col-12 col-lg-6">
            <label class="form-label">Correo</label>
            <input class="form-control <?= isset($fieldErrors['email']) ? 'is-invalid' : '' ?>" name="data[email]" value="<?= e($data['email'] ?? '') ?>">
            <?php foreach (($fieldErrors['email'] ?? []) as $error): ?><div class="invalid-feedback d-block"><?= e($error) ?></div><?php endforeach; ?>
        </div>
        <div class="col-12 col-lg-3">
            <label class="form-label">Sexo</label>
            <select class="form-select <?= isset($fieldErrors['sex']) ? 'is-invalid' : '' ?>" name="data[sex]">
                <option value="masculino" <?= ($data['sex'] ?? '') === 'masculino' ? 'selected' : '' ?>>Masculino</option>
                <option value="femenino" <?= ($data['sex'] ?? '') === 'femenino' ? 'selected' : '' ?>>Femenino</option>
            </select>
            <?php foreach (($fieldErrors['sex'] ?? []) as $error): ?><div class="invalid-feedback d-block"><?= e($error) ?></div><?php endforeach; ?>
        </div>
        <div class="col-12 col-lg-3">
            <label class="form-label">Fecha nacimiento</label>
            <input class="form-control <?= isset($fieldErrors['birth_date']) ? 'is-invalid' : '' ?>" type="date" max="<?= e(date('Y-m-d')) ?>" name="data[birth_date]" value="<?= e($data['birth_date'] ?? '') ?>">
            <?php foreach (($fieldErrors['birth_date'] ?? []) as $error): ?><div class="invalid-feedback d-block"><?= e($error) ?></div><?php endforeach; ?>
        </div>
        <div class="col-12 col-lg-3">
            <label class="form-label">Edad</label>
            <input class="form-control <?= isset($fieldErrors['age']) ? 'is-invalid' : '' ?>" type="number" min="0" max="120" name="data[age]" value="<?= e((string) ($data['age'] ?? '')) ?>" data-age-input>
            <?php foreach (($fieldErrors['age'] ?? []) as $error): ?><div class="invalid-feedback d-block"><?= e($error) ?></div><?php endforeach; ?>
        </div>
        <div class="col-12 col-lg-6">
            <label class="form-label">Empresa</label>
            <input class="form-control <?= isset($fieldErrors['company_name']) ? 'is-invalid' : '' ?>" name="data[company_name]" value="<?= e($data['company_name'] ?? '') ?>">
            <?php foreach (($fieldErrors['company_name'] ?? []) as $error): ?><div class="invalid-feedback d-block"><?= e($error) ?></div><?php endforeach; ?>
        </div>
        <div class="col-12 col-lg-3">
            <label class="form-label">Contrasena</label>
            <input class="form-control <?= isset($fieldErrors['password']) ? 'is-invalid' : '' ?>" name="data[password]" value="<?= e($data['password'] ?? '') ?>">
            <div class="form-text">Si queda vacia, se usaran los ultimos 4 digitos del RUT.</div>
            <?php foreach (($fieldErrors['password'] ?? []) as $error): ?><div class="invalid-feedback d-block"><?= e($error) ?></div><?php endforeach; ?>
        </div>
        <div class="col-12 col-lg-3">
            <label class="form-label">Activo</label>
            <select class="form-select" name="data[is_active]">
                <option value="1" <?= (int) ($data['is_active'] ?? 1) === 1 ? 'selected' : '' ?>>Si</option>
                <option value="0" <?= (int) ($data['is_active'] ?? 1) === 0 ? 'selected' : '' ?>>No</option>
            </select>
        </div>
        <?php foreach ($fieldDefinitions as $field): ?>
            <?php
            $fieldId = (int) $field['id'];
            $errorKey = 'field_' . $fieldId;
            $value = $row['field_values'][$fieldId] ?? '';
            ?>
            <div class="col-12 col-lg-6">
                <label class="form-label"><?= e($field['label']) ?></label>
                <?php if ($field['field_type'] === 'checkbox'): ?>
                    <select class="form-select <?= isset($fieldErrors[$errorKey]) ? 'is-invalid' : '' ?>" name="field_values[<?= $fieldId ?>]">
                        <option value="1" <?= $value === '1' ? 'selected' : '' ?>>Si</option>
                        <option value="0" <?= $value !== '1' ? 'selected' : '' ?>>No</option>
                    </select>
                <?php else: ?>
                    <input class="form-control <?= isset($fieldErrors[$errorKey]) ? 'is-invalid' : '' ?>" name="field_values[<?= $fieldId ?>]" value="<?= e($value) ?>">
                <?php endif; ?>
                <?php foreach (($fieldErrors[$errorKey] ?? []) as $error): ?><div class="invalid-feedback d-block"><?= e($error) ?></div><?php endforeach; ?>
            </div>
        <?php endforeach; ?>
    </div>
    <div class="import-drawer-actions">
        <button class="btn btn-outline-secondary" type="button" data-app-drawer-close>Cerrar</button>
        <button class="btn btn-primary" type="submit"><i class="bi bi-arrow-repeat me-1"></i> Guardar y revalidar</button>
    </div>
</form>
