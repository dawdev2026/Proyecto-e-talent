<?php
$previewRows = $preview['rows'] ?? [];
$hasPreview = (bool) $preview;
$hasErrors = $hasPreview && empty($preview['valid']);
$summary = $preview['summary'] ?? [
    'processed' => count($previewRows),
    'enabled' => 0,
    'disabled' => 0,
    'with_errors' => 0,
    'without_errors' => 0,
    'total_errors' => 0,
    'age_errors' => 0,
];
$errorSummary = $preview['error_summary'] ?? [
    'total_errors' => (int) ($summary['total_errors'] ?? 0),
    'age_errors' => (int) ($summary['age_errors'] ?? 0),
    'by_field' => [],
    'by_message' => [],
];
?>
<section class="page-header import-page-header">
    <div>
        <p class="text-uppercase text-primary fw-bold small mb-1">Administracion</p>
        <h1 class="fw-bold mb-1">Carga masiva de usuarios</h1>
        <p class="text-muted mb-0">Trabaja siempre sobre la data Excel vigente de la plataforma.</p>
    </div>
    <div class="d-flex flex-wrap gap-2">
        <a class="btn btn-primary" href="<?= e(route_url('user.import-template')) ?>"><i class="bi bi-file-earmark-arrow-down me-1"></i> Descargar data existente</a>
    </div>
</section>

<section class="import-workflow">
    <div class="import-step">
        <span class="import-step-number">1</span>
        <div>
            <h2>Descarga</h2>
            <p>Obtiene un Excel con usuarios actuales, empresas y campos extra activos.</p>
        </div>
    </div>
    <div class="import-step">
        <span class="import-step-number">2</span>
        <div>
            <h2>Edita</h2>
            <p>Modifica filas existentes o agrega usuarios nuevos manteniendo los encabezados.</p>
        </div>
    </div>
    <div class="import-step">
        <span class="import-step-number">3</span>
        <div>
            <h2>Valida</h2>
            <p>Corrige los problemas en pantalla antes de confirmar la actualizacion.</p>
        </div>
    </div>
</section>

<section class="content-panel import-panel">
    <div class="row g-4 align-items-stretch">
        <div class="col-12 col-xl-5">
            <form method="post" enctype="multipart/form-data" class="import-upload-box needs-validation" novalidate>
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="import_action" value="validate">
                <div class="import-upload-icon"><i class="bi bi-file-earmark-excel"></i></div>
                <label class="form-label" for="import_file">Archivo Excel actualizado</label>
                <input id="import_file" class="form-control form-control-lg" type="file" name="import_file" accept=".xlsx" required>
                <div class="form-text">Solo se acepta `.xlsx`. Usa el archivo descargado desde esta pantalla como base.</div>
                <button class="btn btn-primary px-4 mt-3" type="submit"><i class="bi bi-search me-1"></i> Validar Excel</button>
            </form>
        </div>
        <div class="col-12 col-xl-7">
            <div class="import-context-grid">
                <div class="import-context-item">
                    <span>Perfil por defecto</span>
                    <?php if ($defaultProfile): ?>
                        <strong><?= e($defaultProfile['name']) ?></strong>
                    <?php else: ?>
                        <strong class="text-danger">Sin configurar</strong>
                    <?php endif; ?>
                </div>
                <div class="import-context-item">
                    <span>Campos extra incluidos</span>
                    <div class="permission-chip-list">
                        <?php foreach ($fieldDefinitions as $field): ?>
                            <span class="badge text-bg-info"><?= e($field['field_key']) ?></span>
                        <?php endforeach; ?>
                        <?php if (!$fieldDefinitions): ?>
                            <strong>No hay campos extra activos</strong>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="import-context-item import-context-wide">
                    <span>Regla de actualizacion</span>
                    <strong>El RUT identifica al usuario. Si existe se actualiza; si no existe se revisa el correo y luego se crea con el perfil por defecto.</strong>
                </div>
            </div>
        </div>
    </div>
</section>

<?php if ($hasPreview): ?>
    <section class="content-panel import-preview-panel">
        <div class="import-preview-header">
            <div>
                <p class="text-uppercase text-primary fw-bold small mb-1">Previsualizacion editable</p>
                <h2 class="h4 fw-bold mb-1"><?= e($preview['uploaded_name'] ?? 'Archivo validado') ?></h2>
                <p class="text-muted mb-0"><span data-import-total-rows><?= count($previewRows) ?></span> filas · <span data-import-created><?= (int) ($preview['created'] ?? 0) ?></span> nuevas · <span data-import-updated><?= (int) ($preview['updated'] ?? 0) ?></span> actualizaciones</p>
            </div>
        </div>

        <div class="import-summary-grid">
            <div class="import-summary-item">
                <span>Filas procesadas</span>
                <strong data-import-summary="processed"><?= (int) $summary['processed'] ?></strong>
            </div>
            <div class="import-summary-item">
                <span>Habilitadas</span>
                <strong data-import-summary="enabled"><?= (int) $summary['enabled'] ?></strong>
            </div>
            <div class="import-summary-item">
                <span>Deshabilitadas</span>
                <strong data-import-summary="disabled"><?= (int) $summary['disabled'] ?></strong>
            </div>
            <div class="import-summary-item <?= (int) $summary['with_errors'] > 0 ? 'has-errors' : '' ?>" data-import-summary-card="with_errors">
                <span>Filas con errores</span>
                <strong data-import-summary="with_errors"><?= (int) $summary['with_errors'] ?></strong>
            </div>
            <div class="import-summary-item">
                <span>Filas sin errores</span>
                <strong data-import-summary="without_errors"><?= (int) $summary['without_errors'] ?></strong>
            </div>
        </div>

        <div class="import-error-summary <?= (int) ($errorSummary['total_errors'] ?? 0) > 0 ? '' : 'd-none' ?>" data-import-error-summary>
            <div class="import-error-summary-heading">
                <div>
                    <p class="text-uppercase text-primary fw-bold small mb-1">Resumen de errores encontrados</p>
                    <h3 class="h5 fw-bold mb-0"><span data-import-error-total><?= (int) ($errorSummary['total_errors'] ?? 0) ?></span> errores detectados</h3>
                </div>
                <form method="post" class="m-0 <?= (int) ($errorSummary['age_errors'] ?? 0) > 0 ? '' : 'd-none' ?>" data-import-age-recalculate-action data-processing-message="Recalculando edades desde fecha de nacimiento...">
                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                    <input type="hidden" name="import_action" value="recalculate_age">
                    <button class="btn btn-outline-primary" type="submit">
                        <i class="bi bi-arrow-repeat me-1"></i> Recalcular edades
                    </button>
                </form>
            </div>
            <div class="import-error-summary-body">
                <div>
                    <span class="import-error-summary-label">Por campo</span>
                    <div class="import-error-chip-list" data-import-error-fields>
                        <?php foreach (($errorSummary['by_field'] ?? []) as $item): ?>
                            <span class="import-error-chip"><?= e($item['label'] ?? '') ?> <strong><?= (int) ($item['count'] ?? 0) ?></strong></span>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div>
                    <span class="import-error-summary-label">Errores frecuentes</span>
                    <ul class="import-error-message-list" data-import-error-messages>
                        <?php foreach (($errorSummary['by_message'] ?? []) as $item): ?>
                            <li><strong><?= (int) ($item['count'] ?? 0) ?></strong> <?= e($item['message'] ?? '') ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
            <p class="text-muted small mb-0 <?= (int) ($errorSummary['age_errors'] ?? 0) > 0 ? '' : 'd-none' ?>" data-import-age-error-hint>
                Hay <?= (int) $errorSummary['age_errors'] ?> fila(s) con error de edad. El recalculo solo modifica la edad cuando la fecha de nacimiento es valida y despues vuelve a validar toda la carga.
            </p>
        </div>

        <?php if (!empty($preview['global_errors'])): ?>
            <div class="alert alert-danger" data-inline-alert data-import-status-alert>
                <div class="fw-semibold mb-2">Problemas generales</div>
                <ul class="mb-0">
                    <?php foreach ($preview['global_errors'] as $error): ?>
                        <li><?= e($error) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php elseif (!empty($preview['valid'])): ?>
            <div class="alert alert-success" data-inline-alert data-import-status-alert>Validacion correcta. Asi se cargaran los datos al confirmar.</div>
        <?php else: ?>
            <div class="alert alert-warning" data-inline-alert data-import-status-alert>Hay problemas pendientes. Revisa la columna final de cada fila para ver que corregir.</div>
        <?php endif; ?>

        <?php if ($previewRows): ?>
            <div class="import-preview-scroll">
                <table class="table import-preview-table app-data-table"
                    data-server-url="<?= e(app_url('users/import/preview-data')) ?>"
                    data-page-length="25"
                    data-export-excel="false"
                    data-export-pdf="false">
                    <thead>
                        <tr>
                            <th>Fila</th>
                            <th>Accion</th>
                            <th>RUT</th>
                            <th>Nombres</th>
                            <th>Apellidos</th>
                            <th>Correo</th>
                            <th>Sexo</th>
                            <th>Fecha nacimiento</th>
                            <th>Edad</th>
                            <th>Empresa</th>
                            <th>Contrasena</th>
                            <th>Activo</th>
                            <?php foreach ($fieldDefinitions as $field): ?>
                                <th><?= e($field['label']) ?></th>
                            <?php endforeach; ?>
                            <th class="no-sort">Descripcion del error</th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
            <div class="import-preview-actions">
                <?php if ($hasErrors): ?>
                    <span class="text-muted small">Puedes corregir una fila desde su boton o ajustar el Excel original y volver a cargarlo.</span>
                <?php else: ?>
                    <span class="text-muted small">La previsualizacion esta lista para cargarse al sistema.</span>
                <?php endif; ?>
            </div>
            <form method="post" class="import-apply-footer">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="import_action" value="apply">
                <button class="btn btn-success px-4" type="submit" data-import-apply-button <?= !empty($preview['valid']) ? '' : 'disabled' ?>>
                    <i class="bi bi-cloud-upload me-1"></i> Cargar al sistema
                </button>
                <span class="text-muted small" data-import-apply-hint><?= empty($preview['valid']) ? 'Disponible solo cuando la previsualizacion no tenga errores.' : 'Aplicara las filas validadas en esta previsualizacion.' ?></span>
            </form>
        <?php endif; ?>

        <?php if (false && $previewRows): ?>
            <form id="userImportCorrectionForm" method="post" class="import-correction-form">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="import_action" value="correct">
                <div class="import-preview-scroll">
                    <table class="table import-preview-table">
                        <thead>
                            <tr>
                                <th>Fila</th>
                                <th>Accion</th>
                                <th>RUT</th>
                                <th>Nombres</th>
                                <th>Apellidos</th>
                                <th>Correo</th>
                                <th>Sexo</th>
                                <th>Fecha nacimiento</th>
                                <th>Edad</th>
                                <th>Empresa</th>
                                <th>Contrasena</th>
                                <th>Activo</th>
                                <?php foreach ($fieldDefinitions as $field): ?>
                                    <th><?= e($field['label']) ?></th>
                                <?php endforeach; ?>
                                <th>Descripcion del error</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($previewRows as $index => $row): ?>
                                <?php $fieldErrors = $row['field_errors'] ?? []; $data = $row['data'] ?? []; ?>
                                <tr class="<?= $row['errors'] ? 'has-errors' : 'is-clean' ?>">
                                    <td>
                                        <span class="import-row-number"><?= (int) $row['row_number'] ?></span>
                                        <input type="hidden" name="rows[<?= (int) $index ?>][row_number]" value="<?= (int) $row['row_number'] ?>">
                                    </td>
                                    <td><span class="badge <?= $row['action'] === 'update' ? 'text-bg-info' : 'text-bg-success' ?>"><?= $row['action'] === 'update' ? 'Actualizar' : 'Crear' ?></span></td>
                                    <td class="<?= isset($fieldErrors['rut']) ? 'import-preview-error-value' : '' ?>"><?= e($data['rut'] ?? '') ?></td>
                                    <td class="<?= isset($fieldErrors['first_names']) ? 'import-preview-error-value' : '' ?>"><?= e($data['first_names'] ?? '') ?></td>
                                    <td class="<?= isset($fieldErrors['last_names']) ? 'import-preview-error-value' : '' ?>"><?= e($data['last_names'] ?? '') ?></td>
                                    <td class="<?= isset($fieldErrors['email']) ? 'import-preview-error-value' : '' ?>"><?= e($data['email'] ?? '') ?></td>
                                    <td class="<?= isset($fieldErrors['sex']) ? 'import-preview-error-value' : '' ?>"><?= e(labelize($data['sex'] ?? '')) ?></td>
                                    <td class="<?= isset($fieldErrors['birth_date']) ? 'import-preview-error-value' : '' ?>"><?= e($data['birth_date'] ?? '') ?></td>
                                    <td class="<?= isset($fieldErrors['age']) ? 'import-preview-error-value' : '' ?>"><?= e((string) ($data['age'] ?? '')) ?></td>
                                    <td class="<?= isset($fieldErrors['company_name']) ? 'import-preview-error-value' : '' ?>"><?= e($data['company_name'] ?? '') ?></td>
                                    <td><?= !empty($row['password_from_rut']) ? 'Desde RUT' : (($data['password'] ?? '') !== '' ? 'Definida' : 'Desde RUT') ?></td>
                                    <td><?= (int) ($data['is_active'] ?? 1) === 1 ? 'Si' : 'No' ?></td>
                                    <?php foreach ($fieldDefinitions as $field): ?>
                                        <?php
                                        $fieldId = (int) $field['id'];
                                        $errorKey = 'field_' . $fieldId;
                                        $value = $row['field_values'][$fieldId] ?? '';
                                        ?>
                                        <td class="<?= isset($fieldErrors[$errorKey]) ? 'import-preview-error-value' : '' ?>"><?= e($field['field_type'] === 'checkbox' ? ($value === '1' ? 'Si' : 'No') : $value) ?></td>
                                    <?php endforeach; ?>
                                    <td class="import-problem-cell">
                                        <?php if ($row['errors']): ?>
                                            <ul>
                                                <?php foreach ($row['errors'] as $error): ?>
                                                    <li><?= e($error) ?></li>
                                                <?php endforeach; ?>
                                            </ul>
                                            <button class="btn btn-sm btn-outline-primary mt-2" type="button" data-import-row-drawer="#import-row-drawer-<?= (int) $index ?>" data-import-row-title="Corregir fila <?= (int) $row['row_number'] ?>">
                                                <i class="bi bi-pencil-square me-1"></i> Corregir fila
                                            </button>
                                            <input type="hidden" name="rows[<?= (int) $index ?>][data][rut]" value="<?= e($data['rut'] ?? '') ?>">
                                            <input type="hidden" name="rows[<?= (int) $index ?>][data][first_names]" value="<?= e($data['first_names'] ?? '') ?>">
                                            <input type="hidden" name="rows[<?= (int) $index ?>][data][last_names]" value="<?= e($data['last_names'] ?? '') ?>">
                                            <input type="hidden" name="rows[<?= (int) $index ?>][data][email]" value="<?= e($data['email'] ?? '') ?>">
                                            <input type="hidden" name="rows[<?= (int) $index ?>][data][sex]" value="<?= e($data['sex'] ?? '') ?>">
                                            <input type="hidden" name="rows[<?= (int) $index ?>][data][birth_date]" value="<?= e($data['birth_date'] ?? '') ?>">
                                            <input type="hidden" name="rows[<?= (int) $index ?>][data][age]" value="<?= e((string) ($data['age'] ?? '')) ?>">
                                            <input type="hidden" name="rows[<?= (int) $index ?>][data][company_name]" value="<?= e($data['company_name'] ?? '') ?>">
                                            <input type="hidden" name="rows[<?= (int) $index ?>][data][password]" value="<?= e($data['password'] ?? '') ?>">
                                            <input type="hidden" name="rows[<?= (int) $index ?>][data][is_active]" value="<?= (int) ($data['is_active'] ?? 1) ?>">
                                            <?php foreach ($fieldDefinitions as $field): ?>
                                                <?php $fieldId = (int) $field['id']; ?>
                                                <input type="hidden" name="rows[<?= (int) $index ?>][field_values][<?= $fieldId ?>]" value="<?= e($row['field_values'][$fieldId] ?? '') ?>">
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <span class="import-ok"><i class="bi bi-check2-circle"></i> Sin problemas. La fila esta lista para cargar.</span>
                                            <input type="hidden" name="rows[<?= (int) $index ?>][data][rut]" value="<?= e($data['rut'] ?? '') ?>">
                                            <input type="hidden" name="rows[<?= (int) $index ?>][data][first_names]" value="<?= e($data['first_names'] ?? '') ?>">
                                            <input type="hidden" name="rows[<?= (int) $index ?>][data][last_names]" value="<?= e($data['last_names'] ?? '') ?>">
                                            <input type="hidden" name="rows[<?= (int) $index ?>][data][email]" value="<?= e($data['email'] ?? '') ?>">
                                            <input type="hidden" name="rows[<?= (int) $index ?>][data][sex]" value="<?= e($data['sex'] ?? '') ?>">
                                            <input type="hidden" name="rows[<?= (int) $index ?>][data][birth_date]" value="<?= e($data['birth_date'] ?? '') ?>">
                                            <input type="hidden" name="rows[<?= (int) $index ?>][data][age]" value="<?= e((string) ($data['age'] ?? '')) ?>">
                                            <input type="hidden" name="rows[<?= (int) $index ?>][data][company_name]" value="<?= e($data['company_name'] ?? '') ?>">
                                            <input type="hidden" name="rows[<?= (int) $index ?>][data][password]" value="<?= e($data['password'] ?? '') ?>">
                                            <input type="hidden" name="rows[<?= (int) $index ?>][data][is_active]" value="<?= (int) ($data['is_active'] ?? 1) ?>">
                                            <?php foreach ($fieldDefinitions as $field): ?>
                                                <?php $fieldId = (int) $field['id']; ?>
                                                <input type="hidden" name="rows[<?= (int) $index ?>][field_values][<?= $fieldId ?>]" value="<?= e($row['field_values'][$fieldId] ?? '') ?>">
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php foreach ($previewRows as $index => $row): ?>
                    <?php if (!$row['errors']) { continue; } ?>
                    <?php $fieldErrors = $row['field_errors'] ?? []; $data = $row['data'] ?? []; ?>
                    <template id="import-row-drawer-<?= (int) $index ?>">
                        <div class="import-row-drawer-content">
                            <div class="drawer-detail-heading">
                                <p class="text-uppercase text-primary fw-bold small mb-1">Correccion de fila</p>
                                <h3 class="h5 fw-bold mb-0">Fila <?= (int) $row['row_number'] ?></h3>
                            </div>
                                    <div class="alert alert-warning" data-inline-alert>
                                        <div class="fw-semibold mb-1">Problemas detectados</div>
                                        <ul class="mb-0">
                                            <?php foreach ($row['errors'] as $error): ?>
                                                <li><?= e($error) ?></li>
                                            <?php endforeach; ?>
                                        </ul>
                                    </div>
                                    <div class="row g-3">
                                        <div class="col-12 col-lg-4">
                                            <label class="form-label">RUT</label>
                                            <input class="form-control <?= isset($fieldErrors['rut']) ? 'is-invalid' : '' ?>" form="userImportCorrectionForm" name="rows[<?= (int) $index ?>][data][rut]" value="<?= e($data['rut'] ?? '') ?>" maxlength="12" data-rut-input>
                                            <?php foreach (($fieldErrors['rut'] ?? []) as $error): ?><div class="invalid-feedback d-block"><?= e($error) ?></div><?php endforeach; ?>
                                        </div>
                                        <div class="col-12 col-lg-4">
                                            <label class="form-label">Nombres</label>
                                            <input class="form-control <?= isset($fieldErrors['first_names']) ? 'is-invalid' : '' ?>" form="userImportCorrectionForm" name="rows[<?= (int) $index ?>][data][first_names]" value="<?= e($data['first_names'] ?? '') ?>">
                                            <?php foreach (($fieldErrors['first_names'] ?? []) as $error): ?><div class="invalid-feedback d-block"><?= e($error) ?></div><?php endforeach; ?>
                                        </div>
                                        <div class="col-12 col-lg-4">
                                            <label class="form-label">Apellidos</label>
                                            <input class="form-control <?= isset($fieldErrors['last_names']) ? 'is-invalid' : '' ?>" form="userImportCorrectionForm" name="rows[<?= (int) $index ?>][data][last_names]" value="<?= e($data['last_names'] ?? '') ?>">
                                            <?php foreach (($fieldErrors['last_names'] ?? []) as $error): ?><div class="invalid-feedback d-block"><?= e($error) ?></div><?php endforeach; ?>
                                        </div>
                                        <div class="col-12 col-lg-6">
                                            <label class="form-label">Correo</label>
                                            <input class="form-control <?= isset($fieldErrors['email']) ? 'is-invalid' : '' ?>" form="userImportCorrectionForm" name="rows[<?= (int) $index ?>][data][email]" value="<?= e($data['email'] ?? '') ?>">
                                            <?php foreach (($fieldErrors['email'] ?? []) as $error): ?><div class="invalid-feedback d-block"><?= e($error) ?></div><?php endforeach; ?>
                                        </div>
                                        <div class="col-12 col-lg-3">
                                            <label class="form-label">Sexo</label>
                                            <select class="form-select <?= isset($fieldErrors['sex']) ? 'is-invalid' : '' ?>" form="userImportCorrectionForm" name="rows[<?= (int) $index ?>][data][sex]">
                                                <option value="masculino" <?= ($data['sex'] ?? '') === 'masculino' ? 'selected' : '' ?>>Masculino</option>
                                                <option value="femenino" <?= ($data['sex'] ?? '') === 'femenino' ? 'selected' : '' ?>>Femenino</option>
                                            </select>
                                            <?php foreach (($fieldErrors['sex'] ?? []) as $error): ?><div class="invalid-feedback d-block"><?= e($error) ?></div><?php endforeach; ?>
                                        </div>
                                        <div class="col-12 col-lg-3">
                                            <label class="form-label">Fecha nacimiento</label>
                                            <input class="form-control <?= isset($fieldErrors['birth_date']) ? 'is-invalid' : '' ?>" form="userImportCorrectionForm" type="date" max="<?= e(date('Y-m-d')) ?>" name="rows[<?= (int) $index ?>][data][birth_date]" value="<?= e($data['birth_date'] ?? '') ?>">
                                            <?php foreach (($fieldErrors['birth_date'] ?? []) as $error): ?><div class="invalid-feedback d-block"><?= e($error) ?></div><?php endforeach; ?>
                                        </div>
                                        <div class="col-12 col-lg-3">
                                            <label class="form-label">Edad</label>
                                            <input class="form-control <?= isset($fieldErrors['age']) ? 'is-invalid' : '' ?>" form="userImportCorrectionForm" type="number" min="0" max="120" name="rows[<?= (int) $index ?>][data][age]" value="<?= e((string) ($data['age'] ?? '')) ?>" data-age-input>
                                            <?php foreach (($fieldErrors['age'] ?? []) as $error): ?><div class="invalid-feedback d-block"><?= e($error) ?></div><?php endforeach; ?>
                                        </div>
                                        <div class="col-12 col-lg-6">
                                            <label class="form-label">Empresa</label>
                                            <input class="form-control <?= isset($fieldErrors['company_name']) ? 'is-invalid' : '' ?>" form="userImportCorrectionForm" name="rows[<?= (int) $index ?>][data][company_name]" value="<?= e($data['company_name'] ?? '') ?>">
                                            <?php foreach (($fieldErrors['company_name'] ?? []) as $error): ?><div class="invalid-feedback d-block"><?= e($error) ?></div><?php endforeach; ?>
                                        </div>
                                        <div class="col-12 col-lg-3">
                                            <label class="form-label">Contrasena</label>
                                            <input class="form-control <?= isset($fieldErrors['password']) ? 'is-invalid' : '' ?>" form="userImportCorrectionForm" name="rows[<?= (int) $index ?>][data][password]" value="<?= e($data['password'] ?? '') ?>">
                                            <div class="form-text">Si queda vacia, se usaran los ultimos 4 digitos del RUT.</div>
                                            <?php foreach (($fieldErrors['password'] ?? []) as $error): ?><div class="invalid-feedback d-block"><?= e($error) ?></div><?php endforeach; ?>
                                        </div>
                                        <div class="col-12 col-lg-3">
                                            <label class="form-label">Activo</label>
                                            <select class="form-select" form="userImportCorrectionForm" name="rows[<?= (int) $index ?>][data][is_active]">
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
                                                    <select class="form-select <?= isset($fieldErrors[$errorKey]) ? 'is-invalid' : '' ?>" form="userImportCorrectionForm" name="rows[<?= (int) $index ?>][field_values][<?= $fieldId ?>]">
                                                        <option value="1" <?= $value === '1' ? 'selected' : '' ?>>Si</option>
                                                        <option value="0" <?= $value !== '1' ? 'selected' : '' ?>>No</option>
                                                    </select>
                                                <?php else: ?>
                                                    <input class="form-control <?= isset($fieldErrors[$errorKey]) ? 'is-invalid' : '' ?>" form="userImportCorrectionForm" name="rows[<?= (int) $index ?>][field_values][<?= $fieldId ?>]" value="<?= e($value) ?>">
                                                <?php endif; ?>
                                                <?php foreach (($fieldErrors[$errorKey] ?? []) as $error): ?><div class="invalid-feedback d-block"><?= e($error) ?></div><?php endforeach; ?>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                            <div class="import-drawer-actions">
                                <button class="btn btn-outline-secondary" type="button" data-app-drawer-close>Cerrar</button>
                                <button class="btn btn-primary" type="submit" form="userImportCorrectionForm"><i class="bi bi-arrow-repeat me-1"></i> Guardar y revalidar</button>
                            </div>
                        </div>
                    </template>
                <?php endforeach; ?>
                <div class="import-preview-actions">
                    <button class="btn btn-outline-primary px-4" type="submit"><i class="bi bi-arrow-repeat me-1"></i> Revalidar correcciones</button>
                    <?php if ($hasErrors): ?>
                        <span class="text-muted small">Tambien puedes corregir el Excel original y volver a cargarlo.</span>
                    <?php endif; ?>
                </div>
            </form>
            <form method="post" class="import-apply-footer">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="import_action" value="apply">
                <button class="btn btn-success px-4" type="submit" <?= !empty($preview['valid']) ? '' : 'disabled' ?>>
                    <i class="bi bi-cloud-upload me-1"></i> Cargar al sistema
                </button>
                <?php if (empty($preview['valid'])): ?>
                    <span class="text-muted small">Disponible solo cuando la previsualizacion no tenga errores.</span>
                <?php endif; ?>
            </form>
        <?php endif; ?>
    </section>
<?php else: ?>
    <section class="content-panel import-preview-panel">
        <div class="import-empty-state">
            <i class="bi bi-file-earmark-spreadsheet"></i>
            <div>
                <h2 class="h5 fw-bold mb-1">Sin archivo validado</h2>
                <p class="text-muted mb-0">Descarga la data existente, edita el Excel y subelo para ver la previsualizacion real.</p>
            </div>
        </div>
    </section>
<?php endif; ?>
