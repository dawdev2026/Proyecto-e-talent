<?php
$processUsers = $processUsers ?? [];
$sessions = $sessions ?? [];
$instruments = $instruments ?? [];
$evaluationAssignments = $evaluationAssignments ?? [];
$evaluationForms = $evaluationForms ?? [];
$fields = $fields ?? [];
$availableUserFilters = $availableUserFilters ?? ['companies' => [], 'fields' => []];
$processId = (int) ($process['id'] ?? 0);
$processToken = secure_url_token($processId, 'test_process');
$availableUsersUrl = app_url('tests/processes/' . $processToken . '/available-users');
$selectedInstrumentSet = array_flip(array_map('intval', $selectedInstrumentIds ?? []));
$evaluationAssignmentsByUser = [];
foreach ($evaluationAssignments as $assignment) {
    $evaluationAssignmentsByUser[(int) ($assignment['user_id'] ?? 0)][(int) ($assignment['form_id'] ?? 0)] = $assignment;
}
$evaluationFormIds = array_values(array_unique(array_merge(
    array_map(static fn(array $form): int => (int) ($form['id'] ?? 0), $evaluationForms),
    array_map(static fn(array $assignment): int => (int) ($assignment['form_id'] ?? 0), $evaluationAssignments)
)));
$evaluationFormTitles = [];
foreach ($evaluationForms as $form) {
    $evaluationFormTitles[(int) ($form['id'] ?? 0)] = (string) ($form['title'] ?? 'Evaluación');
}
foreach ($evaluationAssignments as $assignment) {
    $evaluationFormTitles[(int) ($assignment['form_id'] ?? 0)] = (string) ($assignment['form_title'] ?? 'Evaluación');
}
$sessionsByUserInstrument = [];
$answeredSessionsByUser = [];
$answeredEvaluationByUser = [];
foreach ($sessions as $session) {
    $sessionUserId = (int) $session['user_id'];
    $sessionInstrumentId = (int) $session['instrument_id'];
    $currentSession = $sessionsByUserInstrument[$sessionUserId][$sessionInstrumentId] ?? null;
    if (!$currentSession || (int) ($session['answers_count'] ?? 0) > (int) ($currentSession['answers_count'] ?? 0)) {
        $sessionsByUserInstrument[$sessionUserId][$sessionInstrumentId] = $session;
    }
    if ((int) ($session['answers_count'] ?? 0) > 0) {
        $answeredSessionsByUser[$sessionUserId] = ($answeredSessionsByUser[$sessionUserId] ?? 0) + 1;
    }
}
foreach ($evaluationAssignments as $assignment) {
    if ((int) ($assignment['answers_count'] ?? 0) > 0) {
        $evaluationUserId = (int) ($assignment['user_id'] ?? 0);
        $answeredEvaluationByUser[$evaluationUserId] = ($answeredEvaluationByUser[$evaluationUserId] ?? 0) + 1;
    }
}
$statusLabels = [
    'assigned' => 'Asignada',
    'in_progress' => 'En curso',
    'completed' => 'Completada',
    'expired' => 'Expirada',
    'cancelled' => 'Cancelada',
];
$companies = $availableUserFilters['companies'] ?? [];
$fieldOptions = $availableUserFilters['fields'] ?? [];
$fieldClientDefinitions = array_map(static function (array $field): array {
    return [
        'id' => (int) ($field['id'] ?? 0),
        'key' => (string) ($field['field_key'] ?? ''),
    ];
}, $fields);
$allowExpiredReopen = (int) ($process['allow_expired_reopen'] ?? 0) === 1;
$canManageSessionActions = !empty($canManageSessionActions);
$showProcessUserActions = $canManageUsers || $canManageSessionActions || $canViewResults || $canEditProcessUserData;
$processHasResults = !empty($processHasResults);
$availabilityStatus = (string) ($process['availability_status'] ?? 'scheduled');
$availabilityLabels = [
    'scheduled' => 'Segun calendario',
    'open_now' => 'Abierto anticipadamente',
    'closed_now' => 'Cerrado anticipadamente',
];
$assignedProcessUsers = array_values(array_filter(
    $processUsers,
    static fn(array $user): bool => (string) ($user['status'] ?? '') !== 'cancelled'
));
$selectedInstrumentCount = count($selectedInstrumentSet);
$selectedEvaluationCount = count($evaluationFormIds);
$selectedEvaluationTotal = $selectedInstrumentCount + $selectedEvaluationCount;
$generalSummary = [
    'assigned_total' => count($assignedProcessUsers),
    'platform_attendance_total' => 0,
    'completed_all_total' => 0,
    'not_started_total' => 0,
    'partial_completed_total' => 0,
];
$generalSummaryHelp = [
    'attendance' => 'Usuarios con login registrado en la ventana del proceso o con evidencia de una evaluación iniciada.',
    'completed_all' => 'Usuarios que tienen todas las evaluaciones del proceso en estado Completada, Expirada o En curso.',
    'not_started' => 'Usuarios que tienen todas las evaluaciones del proceso en estado Asignada.',
    'partial' => 'Usuarios que tienen al menos una evaluación en estado Completada, Expirada o En curso, pero aún no cumplen con todas las evaluaciones del proceso.',
];
$selectedInstruments = [];
foreach ($instruments as $instrument) {
    $instrumentId = (int) ($instrument['id'] ?? 0);
    if ($instrumentId > 0 && isset($selectedInstrumentSet[$instrumentId])) {
        $selectedInstruments[$instrumentId] = $instrument;
    }
}
$testSummaryRows = [];
foreach ($selectedInstruments as $instrumentId => $instrument) {
    $testSummaryRows[$instrumentId] = [
        'instrument_name' => (string) ($instrument['name'] ?? ''),
        'instrument_code' => (string) ($instrument['code'] ?? ''),
        'items_count' => 0,
        'advanced_total' => 0,
        'expired_total' => 0,
        'assigned_total' => 0,
        'answered_percent_sum' => 0.0,
        'unanswered_percent_sum' => 0.0,
        'percent_samples' => 0,
        'users_total' => count($assignedProcessUsers),
    ];
}
$evaluationFormsById = [];
foreach ($evaluationForms as $form) {
    $formId = (int) ($form['id'] ?? 0);
    if ($formId > 0) {
        $evaluationFormsById[$formId] = $form;
    }
}
foreach ($evaluationAssignments as $assignment) {
    $formId = (int) ($assignment['form_id'] ?? 0);
    if ($formId > 0 && !isset($evaluationFormsById[$formId])) {
        $evaluationFormsById[$formId] = $assignment;
    }
}
$evaluationSummaryRows = [];
foreach ($evaluationFormIds as $formId) {
    $form = $evaluationFormsById[(int) $formId] ?? [];
    $evaluationSummaryRows[(int) $formId] = [
        'instrument_name' => (string) ($form['title'] ?? 'Evaluación'),
        'instrument_code' => strtoupper((string) ($form['form_type'] ?? 'evaluación')),
        'items_count' => max(0, (int) ($form['question_count'] ?? 0)),
        'advanced_total' => 0,
        'expired_total' => 0,
        'assigned_total' => 0,
        'answered_percent_sum' => 0.0,
        'unanswered_percent_sum' => 0.0,
        'percent_samples' => 0,
        'users_total' => count($assignedProcessUsers),
        'kind' => 'evaluation',
    ];
}
$evaluationAssignmentsByUserForm = [];
foreach ($evaluationAssignments as $assignment) {
    $evaluationAssignmentsByUserForm[(int) ($assignment['user_id'] ?? 0)][(int) ($assignment['form_id'] ?? 0)] = $assignment;
}
$summaryRows = [];
foreach ($testSummaryRows as $instrumentId => $row) {
    $row['kind'] = 'test';
    $summaryRows['test_' . $instrumentId] = $row;
}
foreach ($evaluationSummaryRows as $formId => $row) {
    $summaryRows['evaluation_' . $formId] = $row;
}
$processStartsAtTs = trim((string) ($process['starts_at'] ?? '')) !== '' ? strtotime((string) $process['starts_at']) : null;
$platformAttendanceWindowStart = $processStartsAtTs !== null && $processStartsAtTs !== false ? $processStartsAtTs - 1800 : null;
$processEndsAtTs = trim((string) ($process['ends_at'] ?? '')) !== '' ? strtotime((string) $process['ends_at']) : null;
$platformAttendanceWindowEnd = $processEndsAtTs !== null && $processEndsAtTs !== false ? $processEndsAtTs + 1800 : null;
foreach ($assignedProcessUsers as $user) {
    $userId = (int) ($user['user_id'] ?? 0);
    $userSessions = $sessionsByUserInstrument[$userId] ?? [];
    $advancedCount = 0;
    $assignedCount = 0;
    $lastLoginAt = trim((string) ($user['process_window_login_at'] ?? ''));
    if ($lastLoginAt === '') {
        $lastLoginAt = trim((string) ($user['last_login_at'] ?? ''));
    }
    $lastLoginTs = $lastLoginAt !== '' ? strtotime($lastLoginAt) : false;

    foreach ($selectedInstrumentSet as $instrumentId => $_selected) {
        $session = $userSessions[(int) $instrumentId] ?? null;
        $status = (string) ($session['status'] ?? '');
        if (!$session) {
            if (isset($testSummaryRows[(int) $instrumentId])) {
                $testSummaryRows[(int) $instrumentId]['assigned_total']++;
            }
            continue;
        }
        if (isset($testSummaryRows[(int) $instrumentId])) {
            $itemsCount = max(0, (int) ($session['items_count'] ?? 0));
            if ($itemsCount > 0 && (int) $testSummaryRows[(int) $instrumentId]['items_count'] <= 0) {
                $testSummaryRows[(int) $instrumentId]['items_count'] = $itemsCount;
            }
        }

        if (in_array($status, ['completed', 'expired', 'in_progress'], true)) {
            $advancedCount++;
            if (isset($testSummaryRows[(int) $instrumentId])) {
                $itemsCount = max(0, (int) ($session['items_count'] ?? 0));
                $answersCount = max(0, (int) ($session['answers_count'] ?? 0));
                if ($itemsCount > 0 && (int) $testSummaryRows[(int) $instrumentId]['items_count'] <= 0) {
                    $testSummaryRows[(int) $instrumentId]['items_count'] = $itemsCount;
                }
                $testSummaryRows[(int) $instrumentId]['advanced_total']++;
                if ($itemsCount > 0) {
                    $answeredPercent = min(100, ($answersCount / $itemsCount) * 100);
                    $testSummaryRows[(int) $instrumentId]['answered_percent_sum'] += $answeredPercent;
                    $testSummaryRows[(int) $instrumentId]['unanswered_percent_sum'] += max(0, 100 - $answeredPercent);
                    $testSummaryRows[(int) $instrumentId]['percent_samples']++;
                }
            }
        }
        if ($status === 'expired' && isset($testSummaryRows[(int) $instrumentId])) {
            $testSummaryRows[(int) $instrumentId]['expired_total']++;
        }
        if ($status === 'assigned') {
            $assignedCount++;
            if (isset($testSummaryRows[(int) $instrumentId])) {
                $testSummaryRows[(int) $instrumentId]['assigned_total']++;
            }
        }
    }

    foreach ($evaluationFormIds as $formId) {
        $formId = (int) $formId;
        $assignment = $evaluationAssignmentsByUserForm[$userId][$formId] ?? null;
        $rowKey = 'evaluation_' . $formId;
        $status = (string) ($assignment['evaluation_status'] ?? 'assigned');
        if (!$assignment) {
            $assignedCount++;
            if (isset($summaryRows[$rowKey])) {
                $summaryRows[$rowKey]['assigned_total']++;
            }
            continue;
        }

        $itemsCount = max(0, (int) ($assignment['question_count'] ?? 0));
        if (isset($summaryRows[$rowKey]) && $itemsCount > 0 && (int) $summaryRows[$rowKey]['items_count'] <= 0) {
            $summaryRows[$rowKey]['items_count'] = $itemsCount;
        }
        if (in_array($status, ['completed', 'expired', 'in_progress'], true)) {
            $advancedCount++;
            if (isset($summaryRows[$rowKey])) {
                $summaryRows[$rowKey]['advanced_total']++;
                if ($status === 'expired') {
                    $summaryRows[$rowKey]['expired_total']++;
                }
                $answersCount = max(0, (int) ($assignment['answers_count'] ?? 0));
                if ($itemsCount > 0) {
                    $answeredPercent = min(100, ($answersCount / $itemsCount) * 100);
                    $summaryRows[$rowKey]['answered_percent_sum'] += $answeredPercent;
                    $summaryRows[$rowKey]['unanswered_percent_sum'] += max(0, 100 - $answeredPercent);
                    $summaryRows[$rowKey]['percent_samples']++;
                }
            }
        } elseif ($status === 'assigned') {
            $assignedCount++;
            if (isset($summaryRows[$rowKey])) {
                $summaryRows[$rowKey]['assigned_total']++;
            }
        }
    }

    $hasLoginEvidence = $lastLoginTs !== false
        && ($platformAttendanceWindowStart === null || $lastLoginTs >= $platformAttendanceWindowStart)
        && ($platformAttendanceWindowEnd === null || $lastLoginTs <= $platformAttendanceWindowEnd);
    $hasEvaluationEvidence = $advancedCount > 0;
    if ($hasLoginEvidence || $hasEvaluationEvidence) {
        $generalSummary['platform_attendance_total']++;
    }

    if ($selectedEvaluationTotal > 0 && $advancedCount >= $selectedEvaluationTotal) {
        $generalSummary['completed_all_total']++;
    } elseif ($selectedEvaluationTotal > 0 && $assignedCount >= $selectedEvaluationTotal) {
        $generalSummary['not_started_total']++;
    } elseif ($advancedCount > 0) {
        $generalSummary['partial_completed_total']++;
    }
}
foreach ($summaryRows as &$summaryRow) {
    $samples = (int) ($summaryRow['percent_samples'] ?? 0);
    $summaryRow['answered_percent_avg'] = $samples > 0 ? round(((float) $summaryRow['answered_percent_sum']) / $samples, 1) : null;
    $summaryRow['unanswered_percent_avg'] = $samples > 0 ? round(((float) $summaryRow['unanswered_percent_sum']) / $samples, 1) : null;
}
unset($summaryRow);
?>

<section class="card content-panel page-header process-detail-header" data-page-back-url="<?= e(route_url('test-processes')) ?>">
    <div class="process-detail-heading">
        <div class="process-detail-eyebrow">
            <span>Proceso</span>
            <span class="badge <?= $availabilityStatus === 'closed_now' ? 'text-bg-secondary' : ($availabilityStatus === 'open_now' ? 'text-bg-success' : 'text-bg-info') ?>">
                <?= e($availabilityLabels[$availabilityStatus] ?? 'Segun calendario') ?>
            </span>
        </div>
        <h1><?= e((string) $process['name']) ?></h1>
        <p><?= e((string) ($process['description'] ?: 'Seguimiento operativo de evaluaciones asignadas por proceso.')) ?></p>
    </div>
    <div class="process-detail-actions">
        <div class="process-detail-actions-row is-primary">
            <a class="btn btn-outline-secondary" href="<?= e(route_url('test-processes')) ?>" data-page-back="1"><i class="bi bi-arrow-left me-1"></i> Volver</a>
            <?php if ($canManageAssignments || $canViewResults || $canViewRanking || $canManageSettings): ?>
                <div class="dropdown process-detail-menu">
                    <button class="btn btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="bi bi-sliders me-1"></i> Acciones
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end process-detail-dropdown">
                        <?php if ($canManageAssignments): ?>
                            <li><h6 class="dropdown-header">Asignaciones del proceso</h6></li>
                            <li>
                                <form method="post" action="<?= e(app_url('tests/processes/' . $processToken . '/assign-sessions')) ?>">
                                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                    <button class="dropdown-item" type="submit"><i class="bi bi-send-check"></i> Generar asignaciones</button>
                                </form>
                            </li>
                        <?php endif; ?>
                        <?php if ($canViewRanking): ?>
                            <?php if ($canManageAssignments): ?>
                                <li><hr class="dropdown-divider"></li>
                            <?php endif; ?>
                            <li><h6 class="dropdown-header">Reportes del proceso</h6></li>
                            <li><a class="dropdown-item" href="<?= e(route_url('test-process.ranking', $processId)) ?>"><i class="bi bi-trophy"></i> Ver Ranking Resumen</a></li>
                        <?php endif; ?>
                        <?php if ($canViewResults || $canManageAssignments): ?>
                            <?php if ($canManageAssignments || $canViewRanking): ?>
                                <li><hr class="dropdown-divider"></li>
                            <?php endif; ?>
                            <li><h6 class="dropdown-header">Respaldo de resultados</h6></li>
                            <?php if ($canViewResults): ?>
                                <li><a class="dropdown-item" href="<?= e(route_url('test-process.results-export', $processId)) ?>"><i class="bi bi-download"></i> Exportar resultados</a></li>
                            <?php endif; ?>
                            <?php if ($canManageAssignments): ?>
                                <li>
                                    <form
                                        class="px-3 py-2"
                                        method="post"
                                        action="<?= e(route_url('test-process.results-import', $processId)) ?>"
                                        enctype="multipart/form-data"
                                        data-confirm-submit="Esta accion importara resultados en este proceso y reemplazara las respuestas, puntajes, reportes y eventos de las sesiones incluidas en el archivo. Deseas continuar?"
                                    >
                                        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                        <label class="form-label small mb-1" for="process-results-file-<?= (int) $processId ?>">Importar resultados</label>
                                        <input id="process-results-file-<?= (int) $processId ?>" class="form-control form-control-sm mb-2" type="file" name="results_file" accept="application/json,application/gzip,.json,.json.gz,.gz" required>
                                        <button class="btn btn-sm btn-outline-primary w-100" type="submit"><i class="bi bi-upload me-1"></i> Importar archivo</button>
                                    </form>
                                </li>
                            <?php endif; ?>
                        <?php endif; ?>
                        <?php if ($canManageSettings): ?>
                            <?php if ($canManageAssignments || $canViewRanking || $canViewResults): ?>
                                <li><hr class="dropdown-divider"></li>
                            <?php endif; ?>
                            <li><h6 class="dropdown-header">Tiempo del proceso</h6></li>
                            <li>
                                <form method="post" action="<?= e(app_url('tests/processes/' . $processToken . '/availability')) ?>">
                                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                    <input type="hidden" name="availability_status" value="open_now">
                                    <button class="dropdown-item" type="submit" <?= $availabilityStatus === 'open_now' ? 'disabled' : '' ?>>
                                        <i class="bi bi-unlock"></i> Abrir ahora
                                    </button>
                                </form>
                            </li>
                            <li>
                                <form method="post" action="<?= e(app_url('tests/processes/' . $processToken . '/availability')) ?>">
                                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                    <input type="hidden" name="availability_status" value="closed_now">
                                    <button class="dropdown-item" type="submit" <?= $availabilityStatus === 'closed_now' ? 'disabled' : '' ?>>
                                        <i class="bi bi-lock"></i> Cerrar ahora
                                    </button>
                                </form>
                            </li>
                            <li>
                                <form method="post" action="<?= e(app_url('tests/processes/' . $processToken . '/availability')) ?>">
                                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                    <input type="hidden" name="availability_status" value="scheduled">
                                    <button class="dropdown-item" type="submit" <?= $availabilityStatus === 'scheduled' ? 'disabled' : '' ?>>
                                        <i class="bi bi-calendar-event"></i> Volver a calendario
                                    </button>
                                </form>
                            </li>
                            <li><hr class="dropdown-divider"></li>
                            <li><h6 class="dropdown-header">Administracion del proceso</h6></li>
                            <li><a class="dropdown-item" href="<?= e(route_url('test-process.edit', $processId)) ?>"><i class="bi bi-pencil"></i> Editar</a></li>
                            <?php if ($processHasResults): ?>
                                <li><button class="dropdown-item text-danger" type="button" disabled title="No se puede eliminar porque existen evaluaciones con resultados."><i class="bi bi-trash"></i> Eliminar</button></li>
                            <?php else: ?>
                                <li>
                                    <form
                                        method="post"
                                        action="<?= e(route_url('test-process.delete', $processId)) ?>"
                                        data-confirm-submit="Esta accion eliminara el proceso completo junto con sus usuarios asignados, asignaciones pendientes y configuracion asociada. Solo se permite si no existen evaluaciones con resultados. Deseas continuar?"
                                    >
                                        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                        <button class="dropdown-item text-danger" type="submit"><i class="bi bi-trash"></i> Eliminar</button>
                                    </form>
                                </li>
                            <?php endif; ?>
                        <?php endif; ?>
                    </ul>
                </div>
            <?php endif; ?>
        </div>
    </div>
</section>

<script>
document.addEventListener('DOMContentLoaded', function () {
    var root = document.querySelector('[data-process-user-assignment]');
    if (!root) {
        return;
    }

    var search = root.querySelector('[data-demo-search]');
    var filters = Array.prototype.slice.call(root.querySelectorAll('[data-demo-filter]'));
    var fieldFilters = Array.prototype.slice.call(root.querySelectorAll('[data-demo-field]'));
    var checkAll = root.querySelector('[data-demo-check-all]');
    var tbody = root.querySelector('[data-demo-user-body]');
    var selectedInputs = root.querySelector('[data-demo-selected-inputs]');
    var perPageSelect = root.querySelector('[data-demo-per-page]');
    var pagination = root.querySelector('[data-demo-pagination]');
    var rangeLabel = root.querySelector('[data-demo-range]');
    var emptyState = root.querySelector('[data-demo-empty]');
    var loadingState = root.querySelector('[data-demo-loading]');
    var perPage = parseInt(perPageSelect ? perPageSelect.value : (root.dataset.perPage || '25'), 10) || 25;
    var endpoint = root.dataset.availableUsersUrl || '';
    var fieldDefinitions = JSON.parse(root.dataset.fields || '[]');
    var currentPage = 1;
    var currentUsers = [];
    var selectedUsers = new Map();
    var debounceTimer = null;

    function escapeHtml(value) {
        return String(value || '').replace(/[&<>"']/g, function (character) {
            return {'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;'}[character];
        });
    }

    function buildParams(mode) {
        var params = new URLSearchParams();
        params.set('page', String(currentPage));
        params.set('per_page', String(perPage));
        if (mode) {
            params.set('mode', mode);
        }
        if (search && search.value.trim() !== '') {
            params.set('search', search.value.trim());
        }
        filters.forEach(function (filter) {
            if (filter.dataset.demoFilter === 'company' && filter.value) {
                params.set('company', filter.value);
            }
        });
        fieldFilters.forEach(function (fieldFilter) {
            if (fieldFilter.value) {
                params.set('fields[' + fieldFilter.dataset.demoField + ']', fieldFilter.value);
            }
        });
        return params;
    }

    function userLabel(user) {
        return user && user.name ? user.name : 'Usuario ' + (user && user.id ? user.id : '');
    }

    function fieldValue(user, fieldKey, fieldId) {
        var fields = user.fields || {};
        return fields[fieldId] || fields[fieldKey] || '-';
    }

    function renderSelectedList() {
        var list = root.querySelector('[data-demo-selected-list]');
        var selected = Array.from(selectedUsers.values());

        if (!selected.length) {
            list.innerHTML = '<span class="text-muted small">Aun no hay usuarios seleccionados.</span>';
            return;
        }

        list.innerHTML = selected.slice(0, 10).map(function (user) {
            return '<span>' + escapeHtml(userLabel(user)) + '</span>';
        }).join('');

        if (selected.length > 10) {
            list.insertAdjacentHTML('beforeend', '<span>+' + (selected.length - 10) + ' mas</span>');
        }
    }

    function renderSelectedInputs() {
        selectedInputs.value = JSON.stringify(Array.from(selectedUsers.keys()));
    }

    function renderSummary(total) {
        var selectedCount = selectedUsers.size;
        if (checkAll) {
            checkAll.checked = currentUsers.length > 0 && currentUsers.every(function (user) {
                return selectedUsers.has(String(user.id));
            });
            checkAll.indeterminate = currentUsers.some(function (user) {
                return selectedUsers.has(String(user.id));
            }) && !checkAll.checked;
        }

        root.querySelector('[data-demo-selected-count]').textContent = selectedCount;
        root.querySelector('[data-demo-visible-count]').textContent = currentUsers.length;
        root.querySelector('[data-demo-total-count]').textContent = total;
        root.querySelector('[data-demo-summary-selected]').textContent = selectedCount;
        root.querySelector('[data-demo-summary-visible]').textContent = currentUsers.length;
        root.querySelector('[data-demo-run]').disabled = selectedCount === 0;
        renderSelectedList();
        renderSelectedInputs();
    }

    function renderRows() {
        if (!currentUsers.length) {
            tbody.innerHTML = '';
            emptyState.classList.remove('d-none');
            return;
        }

        emptyState.classList.add('d-none');
        tbody.innerHTML = currentUsers.map(function (user) {
            var id = String(user.id);
            var cells = fieldDefinitions.map(function (field) {
                return '<td>' + escapeHtml(fieldValue(user, field.key, field.id)) + '</td>';
            }).join('');

            return '<tr>' +
                '<td><input class="form-check-input" type="checkbox" data-demo-user-check value="' + escapeHtml(id) + '"' + (selectedUsers.has(id) ? ' checked' : '') + ' aria-label="Seleccionar ' + escapeHtml(user.name) + '"></td>' +
                '<td><div class="fw-semibold">' + escapeHtml(user.name) + '</div><div class="text-muted small">' + escapeHtml(user.email) + '</div></td>' +
                '<td>' + escapeHtml(user.company_name || 'Sin empresa') + '</td>' +
                cells +
            '</tr>';
        }).join('');
    }

    function renderPagination(page, totalPages) {
        pagination.innerHTML = '';
        var group = document.createElement('div');
        group.className = 'assignment-pagination-group';

        var previous = document.createElement('button');
        previous.type = 'button';
        previous.className = 'btn btn-sm btn-outline-secondary assignment-page-button';
        previous.textContent = 'Anterior';
        previous.disabled = page <= 1;
        previous.addEventListener('click', function () {
            currentPage = Math.max(1, currentPage - 1);
            loadUsers();
        });

        var next = document.createElement('button');
        next.type = 'button';
        next.className = 'btn btn-sm btn-outline-secondary assignment-page-button';
        next.textContent = 'Siguiente';
        next.disabled = page >= totalPages;
        next.addEventListener('click', function () {
            currentPage = Math.min(totalPages, currentPage + 1);
            loadUsers();
        });

        function addPageButton(pageNumber, label, active) {
            var button = document.createElement('button');
            button.type = 'button';
            button.className = 'btn btn-sm ' + (active ? 'btn-primary' : 'btn-outline-secondary') + ' assignment-page-button';
            button.textContent = label || String(pageNumber);
            button.disabled = active || pageNumber < 1 || pageNumber > totalPages;
            button.addEventListener('click', function () {
                currentPage = pageNumber;
                loadUsers();
            });
            group.appendChild(button);
        }

        function addEllipsis() {
            var span = document.createElement('span');
            span.className = 'assignment-page-ellipsis';
            span.textContent = '...';
            group.appendChild(span);
        }

        group.appendChild(previous);
        addPageButton(1, '1', page === 1);
        if (page > 3) {
            addEllipsis();
        }
        for (var pageNumber = Math.max(2, page - 1); pageNumber <= Math.min(totalPages - 1, page + 1); pageNumber++) {
            addPageButton(pageNumber, String(pageNumber), pageNumber === page);
        }
        if (page < totalPages - 2) {
            addEllipsis();
        }
        if (totalPages > 1) {
            addPageButton(totalPages, String(totalPages), page === totalPages);
        }
        group.appendChild(next);
        pagination.appendChild(group);
    }

    function loadUsers() {
        if (!endpoint) {
            return;
        }

        loadingState.classList.remove('d-none');
        window.fetch(endpoint + '?' + buildParams().toString(), {
            headers: {'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest'},
            credentials: 'same-origin'
        }).then(function (response) {
            return response.json().then(function (payload) {
                if (!response.ok || !payload.ok) {
                    throw payload;
                }
                return payload;
            });
        }).then(function (payload) {
            currentUsers = payload.users || [];
            currentPage = payload.page || 1;
            renderRows();
            renderPagination(payload.page || 1, payload.total_pages || 1);
            var start = payload.total > 0 ? ((payload.page - 1) * payload.per_page) + 1 : 0;
            var end = Math.min(payload.total || 0, (payload.page || 1) * (payload.per_page || perPage));
            rangeLabel.textContent = 'Mostrando ' + start + '-' + end + ' de ' + (payload.total || 0);
            renderSummary(payload.total || 0);
        }).catch(function (error) {
            if (window.AppNotify) {
                window.AppNotify.error(error && error.message ? error.message : 'No se pudieron cargar los usuarios disponibles.');
            }
        }).finally(function () {
            loadingState.classList.add('d-none');
        });
    }

    function scheduleLoad() {
        currentPage = 1;
        window.clearTimeout(debounceTimer);
        debounceTimer = window.setTimeout(loadUsers, 300);
    }

    if (checkAll) {
        checkAll.addEventListener('change', function () {
            currentUsers.forEach(function (user) {
                var id = String(user.id);
                if (checkAll.checked) {
                    selectedUsers.set(id, user);
                } else {
                    selectedUsers.delete(id);
                }
            });
            renderRows();
            renderSummary(parseInt(root.querySelector('[data-demo-total-count]').textContent, 10) || currentUsers.length);
        });
    }

    root.addEventListener('change', function (event) {
        if (!event.target.matches('[data-demo-user-check]')) {
            return;
        }
        var userId = String(event.target.value);
        var user = currentUsers.find(function (currentUser) {
            return String(currentUser.id) === userId;
        });
        if (event.target.checked && user) {
            selectedUsers.set(userId, user);
        } else {
            selectedUsers.delete(userId);
        }
        renderSummary(parseInt(root.querySelector('[data-demo-total-count]').textContent, 10) || currentUsers.length);
    });

    root.querySelector('[data-demo-select-visible]').addEventListener('click', function () {
        currentUsers.forEach(function (user) {
            selectedUsers.set(String(user.id), user);
        });
        renderRows();
        renderSummary(parseInt(root.querySelector('[data-demo-total-count]').textContent, 10) || currentUsers.length);
    });

    root.querySelector('[data-demo-select-filtered]').addEventListener('click', function () {
        loadingState.classList.remove('d-none');
        window.fetch(endpoint + '?' + buildParams('ids').toString(), {
            headers: {'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest'},
            credentials: 'same-origin'
        }).then(function (response) {
            return response.json().then(function (payload) {
                if (!response.ok || !payload.ok) {
                    throw payload;
                }
                return payload;
            });
        }).then(function (payload) {
            (payload.ids || []).forEach(function (id) {
                selectedUsers.set(String(id), {id: id, name: 'Usuario ' + id, email: ''});
            });
            currentUsers.forEach(function (user) {
                selectedUsers.set(String(user.id), user);
            });
            renderRows();
            renderSummary(parseInt(root.querySelector('[data-demo-total-count]').textContent, 10) || currentUsers.length);
        }).catch(function (error) {
            if (window.AppNotify) {
                window.AppNotify.error(error && error.message ? error.message : 'No se pudo seleccionar el filtro completo.');
            }
        }).finally(function () {
            loadingState.classList.add('d-none');
        });
    });

    root.querySelector('[data-demo-clear-selection]').addEventListener('click', function () {
        selectedUsers.clear();
        renderRows();
        renderSummary(parseInt(root.querySelector('[data-demo-total-count]').textContent, 10) || currentUsers.length);
    });

    root.querySelector('[data-demo-clear-filters]').addEventListener('click', function () {
        search.value = '';
        filters.forEach(function (filter) {
            filter.value = '';
        });
        fieldFilters.forEach(function (filter) {
            filter.value = '';
        });
        scheduleLoad();
    });

    [search].concat(filters, fieldFilters).forEach(function (control) {
        control.addEventListener('input', scheduleLoad);
        control.addEventListener('change', scheduleLoad);
    });

    if (perPageSelect) {
        perPageSelect.addEventListener('change', function () {
            perPage = parseInt(perPageSelect.value || '25', 10) || 25;
            currentPage = 1;
            loadUsers();
        });
    }

    loadUsers();
});
</script>

<section class="card content-panel">
    <div class="row g-3">
        <div class="col"><div class="result-metric"><span class="result-metric-label">Usuarios</span><strong><?= (int) ($summary['users_total'] ?? 0) ?></strong></div></div>
        <div class="col"><div class="result-metric"><span class="result-metric-label">Evaluaciones</span><strong><?= (int) ($summary['instruments_total'] ?? 0) ?></strong></div></div>
        <div class="col"><div class="result-metric"><span class="result-metric-label">Completadas</span><strong><?= (int) ($summary['completed_total'] ?? 0) ?></strong></div></div>
        <div class="col"><div class="result-metric"><span class="result-metric-label">Expiradas</span><strong><?= (int) ($summary['expired_total'] ?? 0) ?></strong></div></div>
        <div class="col"><div class="result-metric"><span class="result-metric-label">En curso/pendientes</span><strong><?= (int) ($summary['assigned_total'] ?? 0) + (int) ($summary['in_progress_total'] ?? 0) ?></strong></div></div>
    </div>
</section>

<section class="process-detail-tabs mt-4">
    <ul class="nav nav-tabs settings-tabs process-tabs" id="processDetailTabs" role="tablist">
        <li class="nav-item" role="presentation">
            <button
                class="nav-link active"
                id="process-progress-tab"
                type="button"
                role="tab"
                data-bs-toggle="tab"
                data-bs-target="#process-progress-pane"
                aria-controls="process-progress-pane"
                aria-selected="true"
            >
                <i class="bi bi-graph-up-arrow me-1"></i> Avance del proceso
            </button>
        </li>
        <?php if ($canManageUsers): ?>
            <li class="nav-item" role="presentation">
                <button
                    class="nav-link"
                    id="process-assignments-tab"
                    type="button"
                    role="tab"
                    data-bs-toggle="tab"
                    data-bs-target="#process-assignments-pane"
                    aria-controls="process-assignments-pane"
                    aria-selected="false"
                >
                    <i class="bi bi-person-plus me-1"></i> Asignaciones
                </button>
            </li>
        <?php endif; ?>
        <li class="nav-item" role="presentation">
            <button
                class="nav-link"
                id="process-summary-tab"
                type="button"
                role="tab"
                data-bs-toggle="tab"
                data-bs-target="#process-summary-pane"
                aria-controls="process-summary-pane"
                aria-selected="false"
            >
                <i class="bi bi-clipboard-data me-1"></i> Resumen general
            </button>
        </li>
    </ul>

    <div class="tab-content process-tab-content">
        <div
            class="tab-pane fade"
            id="process-summary-pane"
            role="tabpanel"
            aria-labelledby="process-summary-tab"
            tabindex="0"
        >
            <section class="card content-panel">
                <div class="d-flex flex-wrap align-items-start justify-content-between gap-3 mb-3">
                    <div>
                        <h2 class="h5 fw-bold mb-1">Resumen general</h2>
                        <p class="text-muted mb-0"><?= (int) $generalSummary['assigned_total'] ?> personas asignadas · <?= (int) $selectedEvaluationTotal ?> evaluaciones del proceso</p>
                    </div>
                </div>
                <div class="row g-3">
                    <div class="col-md-6 col-xl-3">
                        <div class="result-metric h-100">
                            <span class="result-metric-label d-inline-flex align-items-center gap-1">
                                Entraron a plataforma
                                <button class="btn btn-sm btn-link p-0 text-muted" type="button" data-bs-toggle="popover" data-bs-trigger="focus" data-bs-placement="bottom" data-bs-title="Entraron a plataforma" data-bs-content="<?= e($generalSummaryHelp['attendance']) ?>" aria-label="Ver explicacion de Entraron a plataforma">
                                    <i class="bi bi-info-circle"></i>
                                </button>
                            </span>
                            <span class="text-muted small">Usuarios con ingreso registrado o evidencia por test iniciado</span>
                            <strong><?= (int) $generalSummary['platform_attendance_total'] ?></strong>
                        </div>
                    </div>
                    <div class="col-md-6 col-xl-3">
                        <div class="result-metric h-100">
                            <span class="result-metric-label d-inline-flex align-items-center gap-1">
                                Terminaron todas las evaluaciones
                                <button class="btn btn-sm btn-link p-0 text-muted" type="button" data-bs-toggle="popover" data-bs-trigger="focus" data-bs-placement="bottom" data-bs-title="Terminaron todas las evaluaciones" data-bs-content="<?= e($generalSummaryHelp['completed_all']) ?>" aria-label="Ver explicación de Terminaron todas las evaluaciones">
                                    <i class="bi bi-info-circle"></i>
                                </button>
                            </span>
                            <span class="text-muted small">Usuarios</span>
                            <strong><?= (int) $generalSummary['completed_all_total'] ?></strong>
                        </div>
                    </div>
                    <div class="col-md-6 col-xl-3">
                        <div class="result-metric h-100">
                            <span class="result-metric-label d-inline-flex align-items-center gap-1">
                                No iniciaron ninguna evaluación
                                <button class="btn btn-sm btn-link p-0 text-muted" type="button" data-bs-toggle="popover" data-bs-trigger="focus" data-bs-placement="bottom" data-bs-title="No iniciaron ninguna evaluación" data-bs-content="<?= e($generalSummaryHelp['not_started']) ?>" aria-label="Ver explicación de No iniciaron ninguna evaluación">
                                    <i class="bi bi-info-circle"></i>
                                </button>
                            </span>
                            <span class="text-muted small">Usuarios</span>
                            <strong><?= (int) $generalSummary['not_started_total'] ?></strong>
                        </div>
                    </div>
                    <div class="col-md-6 col-xl-3">
                        <div class="result-metric h-100">
                            <span class="result-metric-label d-inline-flex align-items-center gap-1">
                                Completaron parcialmente
                                <button class="btn btn-sm btn-link p-0 text-muted" type="button" data-bs-toggle="popover" data-bs-trigger="focus" data-bs-placement="bottom" data-bs-title="Completaron parcialmente" data-bs-content="<?= e($generalSummaryHelp['partial']) ?>" aria-label="Ver explicación de Completaron parcialmente">
                                    <i class="bi bi-info-circle"></i>
                                </button>
                            </span>
                            <span class="text-muted small">Usuarios</span>
                            <strong><?= (int) $generalSummary['partial_completed_total'] ?></strong>
                        </div>
                    </div>
                </div>
                <div class="mt-4">
                    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-2">
                        <div>
                            <h3 class="h6 fw-bold mb-1">Resumen por evaluación</h3>
                            <p class="text-muted small mb-0">Conteo de usuarios por estado operativo de cada evaluación del proceso.</p>
                        </div>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>Evaluación</th>
                                    <th class="text-end">Preguntas</th>
                                    <th class="text-end">Terminadas / en curso</th>
                                    <th class="text-end">Expiradas</th>
                                    <th class="text-end">Sin iniciar / asignadas</th>
                                    <th class="text-end">% preguntas respondidas (promedio)</th>
                                    <th class="text-end">% preguntas sin responder (promedio)</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!$testSummaryRows): ?>
                                    <tr>
                                        <td colspan="7" class="text-center text-muted py-4">No hay evaluaciones configuradas para este proceso.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($summaryRows as $row): ?>
                                        <tr>
                                            <td>
                                                <div class="fw-semibold"><?= e((string) $row['instrument_name']) ?></div>
                                                <?php if (trim((string) $row['instrument_code']) !== ''): ?>
                                                    <div class="text-muted small"><?= e((string) $row['instrument_code']) ?></div>
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-end">
                                                <span class="badge text-bg-light border"><?= (int) $row['items_count'] ?></span>
                                            </td>
                                            <td class="text-end">
                                                <span class="badge text-bg-success"><?= (int) $row['advanced_total'] ?></span>
                                            </td>
                                            <td class="text-end">
                                                <span class="badge text-bg-warning"><?= (int) $row['expired_total'] ?></span>
                                            </td>
                                            <td class="text-end">
                                                <span class="badge text-bg-light border"><?= (int) $row['assigned_total'] ?></span>
                                            </td>
                                            <td class="text-end">
                                                <?php if ($row['answered_percent_avg'] === null): ?>
                                                    <span class="text-muted small">-</span>
                                                <?php else: ?>
                                                    <span class="badge text-bg-success"><?= e(number_format((float) $row['answered_percent_avg'], 1, ',', '.')) ?>%</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-end">
                                                <?php if ($row['unanswered_percent_avg'] === null): ?>
                                                    <span class="text-muted small">-</span>
                                                <?php else: ?>
                                                    <span class="badge text-bg-secondary"><?= e(number_format((float) $row['unanswered_percent_avg'], 1, ',', '.')) ?>%</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </section>
        </div>
<?php if ($canManageUsers): ?>
        <form
            class="bulk-assignment-demo tab-pane fade"
            id="process-assignments-pane"
            method="post"
            role="tabpanel"
            aria-labelledby="process-assignments-tab"
            tabindex="0"
            data-process-user-assignment
            data-available-users-url="<?= e($availableUsersUrl) ?>"
            data-per-page="25"
            data-fields="<?= e(json_encode($fieldClientDefinitions, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) ?>"
        >
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="selected_user_ids_json" value="[]" data-demo-selected-inputs>
            <section class="card content-panel">
                <div class="assignment-demo-alert">
                    <div>
                        <strong>Agregar usuarios al proceso</strong>
                        <span>Filtra y selecciona usuarios. Las evaluaciones se toman desde la configuracion del proceso.</span>
                    </div>
                    <span class="badge text-bg-primary"><?= (int) ($summary['instruments_total'] ?? 0) ?> evaluaciones</span>
                </div>
            </section>

            <section class="assignment-demo-layout mt-4">
                <aside class="card content-panel assignment-filter-panel">
                    <div class="d-flex align-items-center justify-content-between gap-2 mb-3">
                        <div>
                            <h2 class="h5 fw-bold mb-1">Filtros</h2>
                            <p class="text-muted mb-0">Combina filtros por empresa y campos del proceso.</p>
                        </div>
                        <button class="btn btn-sm btn-outline-secondary" type="button" data-demo-clear-filters>
                            <i class="bi bi-eraser me-1"></i> Limpiar
                        </button>
                    </div>

                    <div class="assignment-filter-stack">
                        <div>
                            <label class="form-label" for="process_user_search">Buscar</label>
                            <input id="process_user_search" class="form-control" data-demo-search placeholder="Nombre, email, empresa, campos...">
                        </div>
                        <div>
                            <label class="form-label" for="process_user_company">Empresa</label>
                            <select id="process_user_company" class="form-select" data-demo-filter="company">
                                <option value="">Todas</option>
                                <?php foreach ($companies as $company): ?>
                                    <option value="<?= e($company) ?>"><?= e($company) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <?php foreach ($fields as $field): ?>
                            <?php
                            $fieldKey = (string) $field['field_key'];
                            $options = ($fieldOptions[$fieldKey] ?? []) + ($fieldOptions[(string) ((int) $field['id'])] ?? []);
                            ?>
                            <div>
                                <label class="form-label" for="process_field_<?= e($fieldKey) ?>"><?= e((string) $field['label']) ?><?= (int) ($field['is_required'] ?? 0) === 1 ? ' *' : '' ?></label>
                                <?php if ($options): ?>
                                    <select id="process_field_<?= e($fieldKey) ?>" class="form-select" data-demo-field="<?= e($fieldKey) ?>">
                                        <option value="">Todos</option>
                                        <?php foreach ($options as $option): ?>
                                            <option value="<?= e($option) ?>"><?= e($option) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                <?php else: ?>
                                    <input id="process_field_<?= e($fieldKey) ?>" class="form-control" data-demo-field="<?= e($fieldKey) ?>" placeholder="Filtrar <?= e(strtolower((string) $field['label'])) ?>">
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </aside>

                <section class="card content-panel assignment-user-panel">
                    <div class="assignment-panel-toolbar">
                        <div>
                            <h2 class="h5 fw-bold mb-1">Usuarios disponibles</h2>
                            <p class="text-muted mb-0"><span data-demo-visible-count>0</span> en pagina · <span data-demo-total-count>0</span> filtrados · <span data-demo-selected-count>0</span> seleccionados</p>
                        </div>
                        <div class="d-flex flex-wrap gap-2">
                            <button class="btn btn-outline-primary btn-sm" type="button" data-demo-select-visible>
                                <i class="bi bi-check2-square me-1"></i> Seleccionar pagina
                            </button>
                            <button class="btn btn-outline-primary btn-sm" type="button" data-demo-select-filtered>
                                <i class="bi bi-ui-checks-grid me-1"></i> Seleccionar todos filtrados
                            </button>
                            <button class="btn btn-outline-secondary btn-sm" type="button" data-demo-clear-selection>
                                <i class="bi bi-x-square me-1"></i> Limpiar seleccion
                            </button>
                        </div>
                    </div>

                    <div class="assignment-table-controls">
                        <label class="assignment-length-control">
                            <span>Mostrar</span>
                            <select class="form-select form-select-sm" data-demo-per-page>
                                <option value="25">25</option>
                                <option value="50">50</option>
                                <option value="100">100</option>
                            </select>
                        </label>
                        <span class="text-muted small d-none" data-demo-loading>Cargando usuarios...</span>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-hover align-middle app-table assignment-user-table">
                            <thead>
                                <tr>
                                    <th class="no-sort no-export">
                                        <input class="form-check-input" type="checkbox" data-demo-check-all aria-label="Seleccionar todos los usuarios filtrados">
                                    </th>
                                    <th>Usuario</th>
                                    <th>Empresa</th>
                                    <?php foreach ($fields as $field): ?>
                                        <th><?= e((string) $field['label']) ?><?= (int) ($field['is_required'] ?? 0) === 1 ? ' *' : '' ?></th>
                                    <?php endforeach; ?>
                                </tr>
                            </thead>
                            <tbody data-demo-user-body></tbody>
                        </table>
                    </div>
                    <div class="alert alert-light border mb-0 mt-3 d-none" data-demo-empty>No hay usuarios disponibles con los filtros aplicados.</div>
                    <div class="assignment-table-footer">
                        <span class="text-muted small" data-demo-range>Mostrando 0-0 de 0</span>
                        <div data-demo-pagination></div>
                    </div>
                </section>

                <aside class="card content-panel assignment-summary-panel">
                    <h2 class="h5 fw-bold mb-3">Resumen</h2>
                    <div class="assignment-summary-metrics">
                        <div><span>Seleccionados</span><strong data-demo-summary-selected>0</strong></div>
                        <div><span>Visibles</span><strong data-demo-summary-visible>0</strong></div>
                    </div>

                    <div class="assignment-preview-box mt-3">
                        <span>Evaluaciones del proceso</span>
                        <strong><?= (int) ($summary['instruments_total'] ?? 0) ?></strong>
                    </div>

                    <div class="mt-3">
                        <label class="form-label">Usuarios seleccionados</label>
                        <div class="assignment-selected-list" data-demo-selected-list>
                            <span class="text-muted small">Aun no hay usuarios seleccionados.</span>
                        </div>
                    </div>

                    <button class="btn btn-primary w-100 mt-3" type="submit" data-demo-run disabled>
                        <i class="bi bi-person-plus me-1"></i> Agregar al proceso
                    </button>
                </aside>
            </section>
        </form>
<?php endif; ?>

<div
    class="tab-pane fade show active"
    id="process-progress-pane"
    role="tabpanel"
    aria-labelledby="process-progress-tab"
    tabindex="0"
>
<section class="card content-panel">
    <div class="d-flex flex-wrap align-items-start justify-content-between gap-3 mb-3">
        <div>
            <h2 class="h5 fw-bold mb-1">Avance del proceso</h2>
            <p class="text-muted mb-0">Estado por usuario y evaluacion incluida en el proceso.</p>
        </div>
        <div class="d-flex flex-wrap align-items-center gap-2">
            <span class="badge text-bg-light border"><?= count($processUsers) ?> usuarios</span>
            <?php if (($canManageAssignments || $canManageSessionActions) && $processUsers): ?>
                <form
                    class="d-flex flex-wrap align-items-center gap-2"
                    method="post"
                    action="<?= e(app_url('tests/processes/' . $processToken . '/reopen-expired-instrument')) ?>"
                    data-confirm-submit="Esta accion reabrira para todos los usuarios del proceso las evaluaciones expiradas del test seleccionado, usando el tiempo indicado. Solo se aplicara a sesiones expiradas. Deseas continuar?"
                >
                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                    <select class="form-select form-select-sm w-auto" name="instrument_id" required aria-label="Test a reabrir" <?= !$allowExpiredReopen ? 'disabled' : '' ?>>
                        <option value="">Test a reabrir</option>
                        <?php foreach ($instruments as $instrument): ?>
                            <?php if (!isset($selectedInstrumentSet[(int) $instrument['id']])) { continue; } ?>
                            <option value="<?= (int) $instrument['id'] ?>"><?= e((string) $instrument['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <input class="form-control form-control-sm w-auto" type="number" name="duration_minutes" min="1" max="1440" value="30" required aria-label="Minutos de reapertura" <?= !$allowExpiredReopen ? 'disabled' : '' ?>>
                    <button class="btn btn-sm btn-outline-primary" type="submit" <?= !$allowExpiredReopen ? 'disabled title="La reapertura de expiradas debe habilitarse en la configuracion del proceso."' : '' ?>>
                        <i class="bi bi-unlock me-1"></i> Reabrir expirados
                    </button>
                    <?php if (!$allowExpiredReopen): ?>
                        <span class="text-muted small">Habilita reapertura de expiradas en la configuracion del proceso.</span>
                    <?php endif; ?>
                </form>
            <?php endif; ?>
            <?php if (($canManageUsers || $canManageSessionActions) && $processUsers): ?>
                <form
                    method="post"
                    action="<?= e(app_url('tests/processes/' . $processToken . '/remove-all-users')) ?>"
                    data-confirm-submit="Esta accion quitara todos los usuarios del proceso y eliminara sus asignaciones, respuestas, puntajes y resultados asociados a este proceso. Deseas continuar?"
                >
                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                    <button class="btn btn-sm btn-outline-danger" type="submit"><i class="bi bi-trash me-1"></i> Remover todos</button>
                </form>
            <?php endif; ?>
        </div>
    </div>

    <?php if (!$processUsers): ?>
        <div class="alert alert-light border mb-0">Aun no hay usuarios en este proceso.</div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover align-middle app-table app-data-table" data-page-length="25" data-export-title="Avance <?= e((string) $process['name']) ?>">
                <thead>
                    <tr>
                        <th>Usuario</th>
                        <th>Estado proceso</th>
                        <?php foreach ($instruments as $instrument): ?>
                            <?php if (isset($selectedInstrumentSet[(int) $instrument['id']])): ?>
                                <th><?= e((string) $instrument['name']) ?></th>
                            <?php endif; ?>
                        <?php endforeach; ?>
                        <?php foreach ($evaluationFormIds as $evaluationFormId): ?>
                            <th><?= e($evaluationFormTitles[$evaluationFormId] ?? 'Evaluación') ?></th>
                        <?php endforeach; ?>
                        <?php if ($showProcessUserActions): ?>
                            <th class="no-sort no-export">Acciones</th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($processUsers as $user): ?>
                        <tr>
                            <td class="process-user-cell">
                                <div class="process-user-name"><?= e((string) $user['name']) ?></div>
                                <div class="process-user-rut"><?= e((string) $user['rut']) ?></div>
                                <?php if (!empty($user['company_name'])): ?>
                                    <span class="process-user-company"><?= e((string) $user['company_name']) ?></span>
                                <?php endif; ?>
                            </td>
                            <td><span class="badge text-bg-light border"><?= e($statusLabels[$user['status']] ?? labelize((string) $user['status'])) ?></span></td>
                            <?php foreach ($instruments as $instrument): ?>
                                <?php if (!isset($selectedInstrumentSet[(int) $instrument['id']])) { continue; } ?>
                                <?php $session = $sessionsByUserInstrument[(int) $user['user_id']][(int) $instrument['id']] ?? null; ?>
                                <td>
                                    <?php if (!$session): ?>
                                        <div class="process-session-card is-empty">
                                            <span class="text-muted small">Sin asignar</span>
                                        </div>
                                    <?php else: ?>
                                        <div class="process-session-card">
                                            <div class="process-session-topline">
                                                <span class="badge text-bg-light border"><?= e($statusLabels[$session['status']] ?? labelize((string) $session['status'])) ?></span>
                                                <?php if (($canManageAssignments || $canManageSessionActions) && in_array((string) $session['status'], ['assigned', 'in_progress'], true)): ?>
                                                    <form method="post" action="<?= e(app_url('tests/processes/' . $processToken . '/cancel-session')) ?>" class="process-session-inline-form">
                                                        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                                        <input type="hidden" name="session_id" value="<?= (int) $session['id'] ?>">
                                                        <button class="btn btn-sm btn-outline-danger process-session-action" type="submit">Cancelar</button>
                                                    </form>
                                                <?php endif; ?>
                                            </div>
                                            <?php if (($canManageAssignments || $canManageSessionActions) && in_array((string) $session['status'], ['completed', 'expired'], true)): ?>
                                                <form
                                                    method="post"
                                                    action="<?= e(app_url('tests/processes/' . $processToken . '/reset-session')) ?>"
                                                    class="process-session-danger-form"
                                                    data-confirm-submit="Esta accion eliminara las respuestas, puntajes, actividad y resultado de esta evaluacion. El usuario podra responderla nuevamente. Deseas continuar?"
                                                >
                                                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                                    <input type="hidden" name="session_id" value="<?= (int) $session['id'] ?>">
                                                    <button class="btn btn-sm btn-outline-danger process-session-reset" type="submit">Eliminar respuestas</button>
                                                </form>
                                            <?php endif; ?>
                                            <?php if (($canManageAssignments || $canManageSessionActions) && $allowExpiredReopen && in_array((string) ($session['status'] ?? ''), ['completed', 'in_progress', 'expired'], true)): ?>
                                                <form
                                                    method="post"
                                                    action="<?= e(app_url('tests/processes/' . $processToken . '/reopen-session')) ?>"
                                                    class="process-session-reopen-form"
                                                    data-confirm-submit="Esta accion reabrira la evaluacion con el nuevo tiempo indicado, conservara sus respuestas y recalculara el resultado cuando finalice. Deseas continuar?"
                                                >
                                                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                                    <input type="hidden" name="session_id" value="<?= (int) $session['id'] ?>">
                                                    <label class="process-session-reopen-field">
                                                        <span>Nuevo tiempo</span>
                                                        <input class="form-control form-control-sm" type="number" name="duration_minutes" min="1" max="1440" value="<?= max(1, (int) ($session['duration_minutes'] ?? 30)) ?>" required>
                                                    </label>
                                                    <button class="btn btn-sm btn-outline-primary process-session-action" type="submit">Reabrir</button>
                                                </form>
                                            <?php endif; ?>
                                            <?php if (($canViewResults || $canManageSessionActions) && (int) ($session['activity_events_total'] ?? 0) > 0): ?>
                                                <div class="supervised-process-summary">
                                                    <span class="badge text-bg-light border">Eventos: <?= (int) $session['activity_events_total'] ?></span>
                                                    <?php if ((int) ($session['activity_attention_total'] ?? 0) > 0): ?>
                                                        <span class="badge text-bg-warning">Atencion: <?= (int) $session['activity_attention_total'] ?></span>
                                                    <?php endif; ?>
                                                    <?php if ((int) ($session['activity_risk_total'] ?? 0) > 0): ?>
                                                        <span class="badge text-bg-danger">Riesgo: <?= (int) $session['activity_risk_total'] ?></span>
                                                    <?php endif; ?>
                                                </div>
                                            <?php endif; ?>
                                            <?php if (($canViewResults || $canManageSessionActions) && !empty($session['last_activity_event_at'])): ?>
                                                <div class="process-session-last">Ultimo: <?= e((string) $session['last_activity_event_at']) ?></div>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                            <?php endforeach; ?>
                            <?php foreach ($evaluationFormIds as $evaluationFormId): ?>
                                <?php $evaluationAssignment = $evaluationAssignmentsByUser[(int) $user['user_id']][$evaluationFormId] ?? null; ?>
                                <td>
                                    <div class="process-session-card <?= $evaluationAssignment ? '' : 'is-empty' ?>">
                                        <?php if (!$evaluationAssignment): ?>
                                            <span class="text-muted small">Sin asignar</span>
                                        <?php else: ?>
                                            <div class="process-session-topline">
                                                <span class="badge text-bg-light border"><?= e($statusLabels[$evaluationAssignment['evaluation_status']] ?? labelize((string) $evaluationAssignment['evaluation_status'])) ?></span>
                                                <?php if (($canManageAssignments || $canManageSessionActions) && in_array((string) ($evaluationAssignment['evaluation_status'] ?? ''), ['assigned', 'in_progress'], true)): ?>
                                                    <form method="post" action="<?= e(app_url('tests/processes/' . $processToken . '/cancel-evaluation-assignment')) ?>" class="process-session-inline-form">
                                                        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                                        <input type="hidden" name="form_id" value="<?= (int) $evaluationFormId ?>">
                                                        <input type="hidden" name="user_id" value="<?= (int) $user['user_id'] ?>">
                                                        <button class="btn btn-sm btn-outline-danger process-session-action" type="submit">Cancelar</button>
                                                    </form>
                                                <?php endif; ?>
                                            </div>
                                            <?php if (($canManageAssignments || $canManageSessionActions) && in_array((string) ($evaluationAssignment['evaluation_status'] ?? ''), ['completed', 'expired'], true)): ?>
                                                <form method="post" action="<?= e(app_url('tests/processes/' . $processToken . '/reset-evaluation-assignment')) ?>" class="process-session-danger-form" data-confirm-submit="Esta accion eliminara las respuestas, puntaje, actividad y resultado de esta evaluacion. El usuario podra responderla nuevamente. Deseas continuar?">
                                                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                                    <input type="hidden" name="form_id" value="<?= (int) $evaluationFormId ?>">
                                                    <input type="hidden" name="user_id" value="<?= (int) $user['user_id'] ?>">
                                                    <button class="btn btn-sm btn-outline-danger process-session-reset" type="submit">Eliminar respuestas</button>
                                                </form>
                                            <?php endif; ?>
                                            <?php if (($canManageAssignments || $canManageSessionActions) && $allowExpiredReopen && in_array((string) ($evaluationAssignment['evaluation_status'] ?? ''), ['completed', 'in_progress', 'expired'], true)): ?>
                                                <form method="post" action="<?= e(app_url('tests/processes/' . $processToken . '/reopen-expired-evaluation-assignment')) ?>" class="process-session-reopen-form" data-confirm-submit="Esta accion reabrira la evaluacion, conservara sus respuestas y asignara un nuevo tiempo. Deseas continuar?">
                                                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                                    <input type="hidden" name="form_id" value="<?= (int) $evaluationFormId ?>">
                                                    <input type="hidden" name="user_id" value="<?= (int) $user['user_id'] ?>">
                                                    <label class="process-session-reopen-field">
                                                        <span>Nuevo tiempo</span>
                                                        <input class="form-control form-control-sm" type="number" name="duration_minutes" min="1" max="1440" value="30" required>
                                                    </label>
                                                    <button class="btn btn-sm btn-outline-primary process-session-action" type="submit">Reabrir</button>
                                                </form>
                                            <?php endif; ?>
                                            <?php if (($canViewResults || $canManageSessionActions) && (int) ($evaluationAssignment['activity_events_total'] ?? 0) > 0): ?>
                                                <div class="supervised-process-summary">
                                                    <span class="badge text-bg-light border">Eventos: <?= (int) $evaluationAssignment['activity_events_total'] ?></span>
                                                    <?php if ((int) ($evaluationAssignment['activity_attention_total'] ?? 0) > 0): ?><span class="badge text-bg-warning">Atencion: <?= (int) $evaluationAssignment['activity_attention_total'] ?></span><?php endif; ?>
                                                    <?php if ((int) ($evaluationAssignment['activity_risk_total'] ?? 0) > 0): ?><span class="badge text-bg-danger">Riesgo: <?= (int) $evaluationAssignment['activity_risk_total'] ?></span><?php endif; ?>
                                                </div>
                                            <?php endif; ?>
                                            <?php if (($canViewResults || $canManageSessionActions) && !empty($evaluationAssignment['last_activity_event_at'])): ?><div class="process-session-last">Ultimo: <?= e((string) $evaluationAssignment['last_activity_event_at']) ?></div><?php endif; ?>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            <?php endforeach; ?>
                            <?php if ($showProcessUserActions): ?>
                                <?php
                                $processUserId = (int) ($user['user_id'] ?? 0);
                                $answeredCount = (int) ($answeredSessionsByUser[$processUserId] ?? 0) + (int) ($answeredEvaluationByUser[$processUserId] ?? 0);
                                $resultsUrl = route_url('test-user.results', $processUserId) . '?process_sid=' . rawurlencode(secure_url_token($processId, 'test_process'));
                                ?>
                                <td class="process-user-actions">
                                    <?php if ($canViewResults && $answeredCount > 0): ?>
                                        <a class="btn btn-sm btn-outline-primary process-session-action" href="<?= e($resultsUrl) ?>">
                                            <i class="bi bi-bar-chart me-1"></i> Resultados
                                        </a>
                                    <?php endif; ?>
                                    <?php if ($canEditProcessUserData && ($user['status'] ?? '') !== 'cancelled'): ?>
                                        <?php $processUserToken = secure_url_token($processUserId, 'user'); ?>
                                        <a
                                            class="btn btn-sm btn-outline-secondary process-session-action"
                                            href="<?= e(app_url('tests/processes/' . $processToken . '/edit-user/' . $processUserToken)) ?>"
                                            data-drawer-url="<?= e(app_url('tests/processes/' . $processToken . '/edit-user/' . $processUserToken . '?drawer=1')) ?>"
                                            data-drawer-title="Editar datos usuario"
                                            data-drawer-size="lg"
                                        >
                                            <i class="bi bi-pencil me-1"></i> Editar datos
                                        </a>
                                    <?php endif; ?>
                                    <?php if (($canManageUsers || $canManageSessionActions) && ($user['status'] ?? '') !== 'cancelled'): ?>
                                        <form
                                            method="post"
                                            action="<?= e(app_url('tests/processes/' . $processToken . '/remove-user')) ?>"
                                            data-confirm-submit="Esta accion quitara al usuario del proceso y eliminara sus asignaciones, respuestas, puntajes y resultados asociados a este proceso. Deseas continuar?"
                                        >
                                            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                            <input type="hidden" name="user_id" value="<?= (int) $user['user_id'] ?>">
                                            <button class="btn btn-sm btn-outline-danger process-session-action" type="submit">Remover</button>
                                        </form>
                                    <?php elseif ($canManageUsers || $canManageSessionActions): ?>
                                        <span class="text-muted small">Removido</span>
                                    <?php endif; ?>
                                    <?php if ((!$canViewResults || $answeredCount <= 0) && !$canManageUsers && !$canManageSessionActions): ?>
                                        <span class="text-muted small">No disponible</span>
                                    <?php endif; ?>
                                </td>
                            <?php endif; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>
</div>
    </div>
</section>
