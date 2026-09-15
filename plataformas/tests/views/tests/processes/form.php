<?php
$process = $process ?? [];
$selectedInstrumentSet = array_flip(array_map('intval', $selectedInstrumentIds ?? []));
$evaluationForms = $evaluationForms ?? [];
$selectedEvaluationFormSet = array_flip(array_map('intval', $selectedEvaluationFormIds ?? []));
$isCompanyAdmin = has_permission('manage_company_users') && !has_permission('manage_users');
$selectedFields = $selectedFields ?? [];
$selectedProfiles = $selectedProfiles ?? [];
$selectedUserAdmins = $selectedUserAdmins ?? [];
$adminUsers = $adminUsers ?? [];
$selectedAssignableProfileSet = array_flip(array_map('intval', $selectedAssignableProfileIds ?? []));
$processId = (int) ($process['id'] ?? 0);
$adminAssignmentMode = (string) ($process['admin_assignment_mode'] ?? '');
if (!in_array($adminAssignmentMode, ['user', 'profile'], true)) {
    $adminAssignmentMode = $selectedUserAdmins ? 'user' : ($selectedProfiles ? 'profile' : 'user');
}
$datetimeValue = static function (?string $value): string {
    $value = trim((string) $value);
    return $value !== '' ? str_replace(' ', 'T', substr($value, 0, 16)) : '';
};
$permissionShortLabels = [
    'view_process' => 'Ver',
];
$availabilityStatus = (string) ($process['availability_status'] ?? 'scheduled');
if (!in_array($availabilityStatus, ['scheduled', 'open_now', 'closed_now'], true)) {
    $availabilityStatus = 'scheduled';
}
?>

<section class="page-header" data-page-back-url="<?= e($processId ? route_url('test-process.show', $processId) : route_url('test-processes')) ?>">
    <div>
        <p class="text-uppercase text-primary fw-bold small mb-1">Procesos</p>
        <h1 class="fw-bold mb-1"><?= $processId ? 'Editar proceso' : 'Nuevo proceso' ?></h1>
        <p class="text-muted mb-0">Configura evaluaciones, campos visibles/requeridos y supervisores con acceso al proceso.</p>
    </div>
    <div class="page-header-actions">
        <a class="btn btn-outline-secondary" href="<?= e($processId ? route_url('test-process.show', $processId) : route_url('test-processes')) ?>" data-page-back="1">
            <i class="bi bi-arrow-left me-1"></i> <?= $processId ? 'Volver al proceso' : 'Volver a procesos' ?>
        </a>
    </div>
</section>

<form method="post">
    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">

    <section class="content-panel">
        <div class="row g-3">
            <div class="col-md-4">
                <label class="form-label" for="process_name">Nombre</label>
                <input id="process_name" class="form-control" name="name" required value="<?= e((string) ($process['name'] ?? '')) ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label" for="process_code">Codigo</label>
                <input id="process_code" class="form-control" name="code" value="<?= e((string) ($process['code'] ?? '')) ?>" placeholder="automatico">
            </div>
            <div class="col-md-2">
                <label class="form-label" for="process_status">Estado</label>
                <select id="process_status" class="form-select" name="status">
                    <?php foreach ($statuses as $key => $label): ?>
                        <option value="<?= e($key) ?>" <?= ($process['status'] ?? 'draft') === $key ? 'selected' : '' ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label" for="process_starts_at">Inicio</label>
                <input id="process_starts_at" class="form-control" type="datetime-local" name="starts_at" value="<?= e($datetimeValue($process['starts_at'] ?? null)) ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label" for="process_ends_at">Cierre</label>
                <input id="process_ends_at" class="form-control" type="datetime-local" name="ends_at" value="<?= e($datetimeValue($process['ends_at'] ?? null)) ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label" for="process_availability_status">Disponibilidad</label>
                <select id="process_availability_status" class="form-select" name="availability_status">
                    <option value="scheduled" <?= $availabilityStatus === 'scheduled' ? 'selected' : '' ?>>Segun calendario</option>
                    <option value="open_now" <?= $availabilityStatus === 'open_now' ? 'selected' : '' ?>>Abierto anticipadamente</option>
                    <option value="closed_now" <?= $availabilityStatus === 'closed_now' ? 'selected' : '' ?>>Cerrado anticipadamente</option>
                </select>
            </div>
            <div class="col-md-6">
                <label class="form-label" for="process_description">Descripcion</label>
                <textarea id="process_description" class="form-control" name="description" rows="2"><?= e((string) ($process['description'] ?? '')) ?></textarea>
            </div>
            <div class="col-12">
                <label class="user-form-switch user-form-switch-compact">
                    <input class="form-check-input" type="checkbox" name="allow_expired_reopen" <?= (int) ($process['allow_expired_reopen'] ?? 0) === 1 ? 'checked' : '' ?>>
                    <span>
                        <strong>Permitir reabrir evaluaciones</strong>
                        <small>Habilita en Avance del proceso acciones para asignar un nuevo tiempo a evaluaciones expiradas o guardadas.</small>
                    </span>
                </label>
            </div>
        </div>
    </section>

    <section class="content-panel mt-4">
        <h2 class="h5 fw-bold mb-3"><?= $isCompanyAdmin ? 'Evaluaciones Psicométricas Asignadas' : 'Evaluaciones del proceso' ?></h2>
        <div class="assignment-evaluation-grid">
            <?php foreach ($instruments as $instrument): ?>
                <label class="assignment-evaluation-option">
                    <input class="form-check-input" type="checkbox" name="instrument_ids[]" value="<?= (int) $instrument['id'] ?>" <?= isset($selectedInstrumentSet[(int) $instrument['id']]) ? 'checked' : '' ?>>
                    <span>
                        <strong><?= e((string) $instrument['name']) ?></strong>
                        <small><?= e(labelize((string) $instrument['category'])) ?><?= $instrument['duration_minutes'] ? ' · ' . (int) $instrument['duration_minutes'] . ' min' : '' ?></small>
                    </span>
                </label>
            <?php endforeach; ?>
        </div>
    </section>

    <section class="content-panel mt-4">
        <h2 class="h5 fw-bold mb-1">Evaluaciones y Encuestas</h2>
        <p class="text-muted mb-3">Selecciona las evaluaciones con nota y encuestas disponibles para este proceso.</p>
        <?php if (!$evaluationForms): ?>
            <div class="alert alert-light border mb-0">No hay evaluaciones ni encuestas activas disponibles para tu empresa.</div>
        <?php else: ?>
            <div class="assignment-evaluation-grid">
                <?php foreach ($evaluationForms as $evaluationForm): ?>
                    <label class="assignment-evaluation-option">
                        <input class="form-check-input" type="checkbox" name="evaluation_form_ids[]" value="<?= (int) $evaluationForm['id'] ?>" <?= isset($selectedEvaluationFormSet[(int) $evaluationForm['id']]) ? 'checked' : '' ?> >
                        <span>
                            <strong><?= e((string) $evaluationForm['title']) ?></strong>
                            <small><?= e(($evaluationForm['form_type'] ?? '') === 'survey' ? 'Encuesta de satisfacción' : 'Evaluación con nota') ?><?= (int) ($evaluationForm['duration_minutes'] ?? 0) > 0 ? ' · ' . (int) $evaluationForm['duration_minutes'] . ' min' : '' ?></small>
                        </span>
                    </label>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>

    <?php if (false): ?>
    <section class="content-panel mt-4">
        <h2 class="h5 fw-bold mb-1">Modo de acceso administrativo</h2>
        <p class="text-muted mb-3">Elige como se asignaran los permisos para revisar este proceso.</p>
        <div class="row g-3">
            <div class="col-md-6">
                <label class="assignment-evaluation-option h-100">
                    <input
                        class="form-check-input"
                        type="radio"
                        name="admin_assignment_mode"
                        value="user"
                        data-process-admin-mode-option
                        <?= $adminAssignmentMode === 'user' ? 'checked' : '' ?>
                    >
                    <span>
                        <strong>Por supervisor</strong>
                        <small>Seleccionar personas especificas para este proceso.</small>
                    </span>
                </label>
            </div>
            <div class="col-md-6">
                <label class="assignment-evaluation-option h-100">
                    <input
                        class="form-check-input"
                        type="radio"
                        name="admin_assignment_mode"
                        value="profile"
                        data-process-admin-mode-option
                        <?= $adminAssignmentMode === 'profile' ? 'checked' : '' ?>
                    >
                    <span>
                        <strong>Por perfil</strong>
                        <small>Todos los usuarios del perfil seleccionado tendran acceso.</small>
                    </span>
                </label>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <section class="content-panel mt-4" data-process-admin-mode-panel="user">
        <h2 class="h5 fw-bold mb-1">Supervisores con acceso al proceso</h2>
        <p class="text-muted mb-3">Asigna usuarios concretos para que puedan ver solo este proceso. El Supervisor no puede ver resultados ni modificar información.</p>
        <?php if (!$adminUsers): ?>
            <div class="alert alert-light border mb-0">No hay usuarios supervisores o administradores activos disponibles.</div>
        <?php else: ?>
            <div class="process-admin-table-wrap">
                <table
                    class="table align-middle app-table app-data-table process-admin-permissions-table"
                    data-page-length="10"
                    data-export-excel="false"
                    data-export-pdf="false"
                    data-scroll-x="true"
                    data-preserve-form-inputs="true"
                >
                    <thead>
                        <tr>
                            <th>Supervisor</th>
                            <?php foreach ($processPermissions as $permission => $label): ?>
                                <th class="no-sort no-export text-center process-permission-heading" title="<?= e($label) ?>">
                                    <span><?= e($permissionShortLabels[$permission] ?? $label) ?></span>
                                    <small><?= e($label) ?></small>
                                </th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($adminUsers as $adminUser): ?>
                            <?php
                            $adminUserId = (int) $adminUser['id'];
                            $userPermissions = $selectedUserAdmins[$adminUserId] ?? [];
                            ?>
                            <tr>
                                <td>
                                    <div class="fw-semibold"><?= e((string) $adminUser['name']) ?></div>
                                    <div class="text-muted small">
                                        <?= e((string) ($adminUser['email'] ?? '')) ?>
                                        <?php if (!empty($adminUser['profile_name'])): ?>
                                            · <?= e((string) $adminUser['profile_name']) ?>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <?php foreach ($processPermissions as $permission => $label): ?>
                                    <td class="text-center">
                                        <input
                                            class="form-check-input"
                                            type="checkbox"
                                            name="user_permissions[<?= $adminUserId ?>][]"
                                            value="<?= e($permission) ?>"
                                            title="<?= e($label . ' - ' . (string) $adminUser['name']) ?>"
                                            aria-label="<?= e($label . ' para ' . (string) $adminUser['name']) ?>"
                                            <?= in_array($permission, $userPermissions, true) ? 'checked' : '' ?>
                                        >
                                    </td>
                                <?php endforeach; ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>

    <?php if (false): ?>
    <section class="content-panel mt-4" data-process-admin-mode-panel="profile">
        <h2 class="h5 fw-bold mb-3">Administradores por perfil</h2>
        <p class="text-muted mb-3">Usa esta seccion solo para permisos amplios. Si marcas un perfil como Supervisor sede aqui, todos los usuarios con ese perfil podran acceder a este proceso.</p>
        <div class="process-admin-table-wrap">
            <table
                class="table align-middle app-table app-data-table process-admin-permissions-table"
                data-page-length="10"
                data-export-excel="false"
                data-export-pdf="false"
                data-scroll-x="true"
                data-preserve-form-inputs="true"
            >
                <thead>
                    <tr>
                        <th>Perfil</th>
                        <?php foreach ($processPermissions as $permission => $label): ?>
                                <th class="no-sort no-export text-center process-permission-heading" title="<?= e($label) ?>">
                                    <span><?= e($permissionShortLabels[$permission] ?? $label) ?></span>
                                    <small><?= e($label) ?></small>
                                </th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($profiles as $profile): ?>
                        <?php $profilePermissions = $selectedProfiles[(int) $profile['id']] ?? []; ?>
                        <tr>
                            <td>
                                <div class="fw-semibold"><?= e((string) $profile['name']) ?></div>
                                <div class="text-muted small"><code><?= e((string) $profile['role_key']) ?></code></div>
                            </td>
                            <?php foreach ($processPermissions as $permission => $label): ?>
                                <td class="text-center">
                                    <input
                                        class="form-check-input"
                                        type="checkbox"
                                        name="profile_permissions[<?= (int) $profile['id'] ?>][]"
                                        value="<?= e($permission) ?>"
                                        title="<?= e($label . ' - ' . (string) $profile['name']) ?>"
                                        aria-label="<?= e($label . ' para ' . (string) $profile['name']) ?>"
                                        <?= in_array($permission, $profilePermissions, true) ? 'checked' : '' ?>
                                    >
                                </td>
                            <?php endforeach; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>

    <?php endif; ?>

    <div class="d-flex justify-content-end gap-2 mt-4">
        <a class="btn btn-outline-secondary" href="<?= e($processId ? route_url('test-process.show', $processId) : route_url('test-processes')) ?>"><?= $processId ? 'Cancelar' : 'Volver a procesos' ?></a>
        <button class="btn btn-primary" type="submit"><i class="bi bi-check2 me-1"></i> Guardar proceso</button>
    </div>
</form>

<script>
document.addEventListener('DOMContentLoaded', function () {
    var options = Array.prototype.slice.call(document.querySelectorAll('[data-process-admin-mode-option]'));
    var panels = Array.prototype.slice.call(document.querySelectorAll('[data-process-admin-mode-panel]'));

    function syncMode() {
        var selected = options.find(function (option) { return option.checked; });
        var mode = selected ? selected.value : 'user';
        panels.forEach(function (panel) {
            var isActive = panel.getAttribute('data-process-admin-mode-panel') === mode;
            panel.classList.toggle('d-none', !isActive);
            if (isActive && window.jQuery && jQuery.fn.DataTable) {
                jQuery(panel).find('.app-data-table').each(function () {
                    if (jQuery.fn.DataTable.isDataTable(this)) {
                        jQuery(this).DataTable().columns.adjust();
                    }
                });
            }
        });
    }

    options.forEach(function (option) {
        option.addEventListener('change', syncMode);
    });
    syncMode();
});
</script>
