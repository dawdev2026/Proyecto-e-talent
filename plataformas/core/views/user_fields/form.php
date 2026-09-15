<?php
$validationChoices = [
    'by_type' => 'Usar la revision recomendada',
    'none' => 'Aceptar cualquier texto',
    'email' => 'Debe ser un correo',
    'phone' => 'Debe ser un telefono',
    'integer' => 'Debe ser un numero entero',
    'decimal' => 'Debe ser un numero',
    'date' => 'Debe ser una fecha',
    'options' => 'Debe coincidir con una opcion',
];
$currentValidation = $values['validation_rule'] ?? 'by_type';
$isAdvanced = $currentValidation === 'regex';
$currentScope = ($values['scope_type'] ?? 'core') . ':' . ($values['scope_key'] ?? 'core');
?>
<section class="page-header">
    <div>
        <p class="text-uppercase text-primary fw-bold small mb-1">Administracion</p>
        <h1 class="fw-bold mb-1"><?= $id ? 'Editar dato de usuario' : 'Nuevo dato de usuario' ?></h1>
        <p class="text-muted mb-0">Agrega la informacion que se pedira en el registro y en la carga masiva.</p>
    </div>
</section>

<section class="card content-panel user-field-builder">
    <form method="post" class="needs-validation" novalidate>
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
        <input id="field_key" type="hidden" name="field_key" value="<?= e($values['field_key']) ?>">

        <div class="field-builder-section">
            <div class="field-builder-heading">
                <span class="field-builder-step">1</span>
                <div>
                    <h2>Donde se usara</h2>
                    <p>Elige si este dato adicional pertenece al core de usuarios o a una sub-plataforma.</p>
                </div>
            </div>
            <div class="row g-3">
                <div class="col-12 col-lg-5">
                    <label class="form-label" for="scope">Ambito del dato</label>
                    <select id="scope" class="form-select form-select-lg" name="scope" required>
                        <?php foreach ($scopes as $scope => $label): ?>
                            <option value="<?= e($scope) ?>" <?= $currentScope === $scope ? 'selected' : '' ?>><?= e($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <div class="form-text">Todos se completan en usuarios y carga masiva; el ambito indica que modulo los consume.</div>
                </div>
                <div class="col-12 col-lg-7">
                    <label class="form-label" for="label">Nombre del dato</label>
                    <input id="label" class="form-control form-control-lg" name="label" value="<?= e($values['label']) ?>" maxlength="120" placeholder="Ejemplo: Telefono de contacto" required>
                </div>
                <div class="col-12">
                    <label class="form-label" for="help_text">Ayuda para quien lo completa</label>
                    <input id="help_text" class="form-control form-control-lg" name="help_text" value="<?= e($values['help_text'] ?? '') ?>" maxlength="255" placeholder="Ejemplo: Incluye codigo de pais">
                </div>
            </div>
        </div>

        <div class="field-builder-section">
            <div class="field-builder-heading">
                <span class="field-builder-step">2</span>
                <div>
                    <h2>Como se respondera</h2>
                    <p>Elige el formato de respuesta. La plataforma usara esto en formularios y archivos Excel.</p>
                </div>
            </div>
            <div class="field-type-grid">
                <?php foreach ($types as $key => $label): ?>
                    <?php
                    $icons = [
                        'text' => 'bi-fonts',
                        'email' => 'bi-envelope',
                        'phone' => 'bi-telephone',
                        'number' => 'bi-123',
                        'date' => 'bi-calendar3',
                        'select' => 'bi-list-check',
                        'textarea' => 'bi-text-paragraph',
                        'checkbox' => 'bi-toggle-on',
                    ];
                    ?>
                    <label class="field-type-option <?= $values['field_type'] === $key ? 'is-selected' : '' ?>">
                        <input type="radio" name="field_type" value="<?= e($key) ?>" <?= $values['field_type'] === $key ? 'checked' : '' ?> required>
                        <span><i class="bi <?= e($icons[$key] ?? 'bi-input-cursor-text') ?>"></i></span>
                        <strong><?= e($label) ?></strong>
                    </label>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="field-builder-section">
            <div class="field-builder-heading">
                <span class="field-builder-step">3</span>
                <div>
                    <h2>Como revisar el dato</h2>
                    <p>Para la mayoria de los casos deja la revision recomendada.</p>
                </div>
            </div>
            <div class="row g-3">
                <div class="col-12 col-lg-6">
                    <label class="form-label" for="validation_rule">Revision del dato</label>
                    <select id="validation_rule" class="form-select form-select-lg" name="validation_rule" required>
                        <?php foreach ($validationChoices as $key => $label): ?>
                            <option value="<?= e($key) ?>" <?= $currentValidation === $key ? 'selected' : '' ?>><?= e($label) ?></option>
                        <?php endforeach; ?>
                        <option value="regex" <?= $isAdvanced ? 'selected' : '' ?>>Regla avanzada</option>
                    </select>
                </div>
                <div class="col-12 col-lg-6">
                    <label class="form-label" for="validation_message">Mensaje si esta mal</label>
                    <input id="validation_message" class="form-control form-control-lg" name="validation_message" value="<?= e($values['validation_message'] ?? '') ?>" maxlength="255" placeholder="Ejemplo: Ingresa un telefono valido">
                </div>
                <div class="col-12 field-options-area">
                    <label class="form-label" for="options">Opciones disponibles</label>
                    <textarea id="options" class="form-control" name="options" rows="5" placeholder="Una opcion por linea"><?= e($values['options'] ?? '') ?></textarea>
                    <div class="form-text">Usalo cuando la respuesta sea una lista, por ejemplo: Ventas, Operaciones, Finanzas.</div>
                </div>
            </div>
        </div>

        <div class="field-builder-section field-advanced-section">
            <button class="btn btn-outline-secondary" type="button" data-bs-toggle="collapse" data-bs-target="#fieldAdvancedOptions" aria-expanded="<?= $isAdvanced || $id ? 'true' : 'false' ?>">
                <i class="bi bi-sliders me-1"></i> Opciones avanzadas
            </button>
            <div id="fieldAdvancedOptions" class="collapse <?= $isAdvanced || $id ? 'show' : '' ?> mt-3">
                <div class="row g-3">
                    <div class="col-12 col-lg-6">
                        <label class="form-label" for="validation_pattern">Regla avanzada</label>
                        <input id="validation_pattern" class="form-control" name="validation_pattern" value="<?= e($values['validation_pattern'] ?? '') ?>" maxlength="255" placeholder="/^[A-Z0-9-]+$/">
                        <div class="form-text">Solo usar con apoyo tecnico. No es necesario para correos, telefonos, numeros, fechas o listas.</div>
                    </div>
                    <div class="col-12 col-lg-3">
                        <label class="form-label" for="sort_order">Orden de aparicion</label>
                        <input id="sort_order" class="form-control" type="number" name="sort_order" value="<?= (int) $values['sort_order'] ?>" required>
                    </div>
                    <div class="col-12 col-lg-3">
                        <label class="form-label">Identificador interno</label>
                        <div class="field-key-preview"><?= e($values['field_key'] ?: 'Se generara automaticamente') ?></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="field-builder-section">
            <div class="field-builder-heading">
                <span class="field-builder-step">4</span>
                <div>
                    <h2>Reglas de uso</h2>
                    <p>Define si es obligatorio, visible en listados y disponible para capturar datos.</p>
                </div>
            </div>
            <div class="field-switch-grid">
                <label class="field-switch-option">
                    <input class="form-check-input" type="checkbox" name="is_required" <?= (int) $values['is_required'] === 1 ? 'checked' : '' ?>>
                    <span>
                        <strong>Obligatorio</strong>
                        <small>No permitira guardar usuarios sin este dato.</small>
                    </span>
                </label>
                <label class="field-switch-option">
                    <input class="form-check-input" type="checkbox" name="show_in_list" <?= (int) $values['show_in_list'] === 1 ? 'checked' : '' ?>>
                    <span>
                        <strong>Mostrar en listado</strong>
                        <small>Aparecera como columna en la lista de usuarios.</small>
                    </span>
                </label>
                <label class="field-switch-option">
                    <input class="form-check-input" type="checkbox" name="is_active" <?= (int) $values['is_active'] === 1 ? 'checked' : '' ?>>
                    <span>
                        <strong>Disponible</strong>
                        <small>Se incluira en formularios y carga masiva.</small>
                    </span>
                </label>
            </div>
        </div>

        <div class="field-builder-actions">
            <button class="btn btn-primary px-4" type="submit"><i class="bi bi-check2 me-1"></i> <?= $id ? 'Guardar cambios' : 'Crear dato' ?></button>
            <a class="btn btn-outline-secondary" href="<?= e(route_url('user-fields')) ?>">Cancelar</a>
        </div>
    </form>
</section>

<script>
document.addEventListener('DOMContentLoaded', function () {
    var labelInput = document.getElementById('label');
    var keyInput = document.getElementById('field_key');
    var keyPreview = document.querySelector('.field-key-preview');
    var touchedKey = Boolean(keyInput && keyInput.value);
    var slugify = function (value) {
        return value.toString().normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase().replace(/[^a-z0-9]+/g, '_').replace(/^_+|_+$/g, '').slice(0, 60);
    };

    if (labelInput && keyInput) {
        labelInput.addEventListener('input', function () {
            if (touchedKey) {
                return;
            }
            var key = slugify(labelInput.value);
            keyInput.value = key;
            if (keyPreview) {
                keyPreview.textContent = key || 'Se generara automaticamente';
            }
        });
    }

    document.querySelectorAll('.field-type-option input').forEach(function (input) {
        input.addEventListener('change', function () {
            document.querySelectorAll('.field-type-option').forEach(function (option) {
                option.classList.toggle('is-selected', option.contains(input) && input.checked);
            });
            updateOptionsArea();
        });
    });

    var validationRule = document.getElementById('validation_rule');
    var optionsArea = document.querySelector('.field-options-area');
    var updateOptionsArea = function () {
        var selectedType = document.querySelector('.field-type-option input:checked');
        var show = (selectedType && selectedType.value === 'select') || (validationRule && validationRule.value === 'options');
        if (optionsArea) {
            optionsArea.hidden = !show;
        }
    };
    if (validationRule) {
        validationRule.addEventListener('change', updateOptionsArea);
    }
    updateOptionsArea();
});
</script>
