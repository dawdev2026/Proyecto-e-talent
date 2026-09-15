<?php
$companies = [];
$fieldOptions = [];

foreach ($users as $user) {
    $company = trim((string) ($user['company_name'] ?? ''));
    if ($company !== '') {
        $companies[$company] = $company;
    }

    foreach (($user['dynamic_fields'] ?? []) as $key => $value) {
        $value = trim((string) $value);
        if ($value !== '') {
            $fieldOptions[$key][$value] = $value;
        }
    }
}

ksort($companies);
foreach ($fieldOptions as &$values) {
    ksort($values);
}
unset($values);
?>

<section class="page-header">
    <div>
        <p class="text-uppercase text-primary fw-bold small mb-1">Evaluaciones</p>
        <h1 class="fw-bold mb-1">Asignacion masiva de evaluaciones</h1>
        <p class="text-muted mb-0">Demo de seleccion masiva con filtros por empresa y campos adicionales del usuario.</p>
    </div>
</section>

<form class="bulk-assignment-demo" method="post" data-bulk-assignment-demo>
    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
    <section class="card content-panel">
        <div class="assignment-demo-alert">
            <div>
                <strong>Asignacion masiva</strong>
                <span>Selecciona una o mas evaluaciones activas y los usuarios que deben responderlas.</span>
            </div>
            <span class="badge text-bg-primary">Activo</span>
        </div>

        <div class="mt-3">
            <div class="d-flex align-items-center justify-content-between gap-3 mb-2">
                <label class="form-label mb-0">Evaluaciones activas</label>
                <span class="text-muted small"><span data-demo-instrument-count>0</span> seleccionadas</span>
            </div>
            <?php if ($instruments): ?>
                <div class="assignment-evaluation-grid">
                    <?php foreach ($instruments as $instrument): ?>
                        <label class="assignment-evaluation-option">
                            <input
                                class="form-check-input"
                                type="checkbox"
                                name="instrument_ids[]"
                                value="<?= (int) $instrument['id'] ?>"
                                data-demo-instrument-check
                                data-name="<?= e($instrument['name']) ?>"
                            >
                            <span>
                                <strong><?= e($instrument['name']) ?></strong>
                                <small><?= e(labelize($instrument['category'])) ?><?= $instrument['duration_minutes'] ? ' · ' . (int) $instrument['duration_minutes'] . ' min' : '' ?></small>
                            </span>
                        </label>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="alert alert-warning mb-0">No hay evaluaciones activas disponibles para asignar.</div>
            <?php endif; ?>
        </div>
    </section>

    <section class="assignment-demo-layout mt-4">
        <aside class="card content-panel assignment-filter-panel">
            <div class="d-flex align-items-center justify-content-between gap-2 mb-3">
                <div>
                    <h2 class="h5 fw-bold mb-1">Filtros</h2>
                    <p class="text-muted mb-0">Combina filtros por empresa y campos adicionales.</p>
                </div>
                <button class="btn btn-sm btn-outline-secondary" type="button" data-demo-clear-filters>
                    <i class="bi bi-eraser me-1"></i> Limpiar
                </button>
            </div>

            <div class="assignment-filter-stack">
                <div>
                    <label class="form-label" for="demo_search">Buscar</label>
                    <input id="demo_search" class="form-control" data-demo-search placeholder="Nombre, email, cargo, area...">
                </div>
                <div>
                    <label class="form-label" for="demo_company">Empresa</label>
                    <select id="demo_company" class="form-select" data-demo-filter="company">
                        <option value="">Todas</option>
                        <?php foreach ($companies as $company): ?>
                            <option value="<?= e($company) ?>"><?= e($company) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php foreach ($userFields as $field): ?>
                    <?php
                        $fieldKey = (string) $field['field_key'];
                        $options = $fieldOptions[$fieldKey] ?? [];
                    ?>
                    <div>
                        <label class="form-label" for="demo_field_<?= e($fieldKey) ?>"><?= e($field['label']) ?></label>
                        <?php if ($options): ?>
                            <select id="demo_field_<?= e($fieldKey) ?>" class="form-select" data-demo-field="<?= e($fieldKey) ?>">
                                <option value="">Todos</option>
                                <?php foreach ($options as $option): ?>
                                    <option value="<?= e($option) ?>"><?= e($option) ?></option>
                                <?php endforeach; ?>
                            </select>
                        <?php else: ?>
                            <input id="demo_field_<?= e($fieldKey) ?>" class="form-control" data-demo-field="<?= e($fieldKey) ?>" placeholder="Filtrar <?= e(strtolower((string) $field['label'])) ?>">
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </aside>

        <section class="card content-panel assignment-user-panel">
            <div class="assignment-panel-toolbar">
                <div>
                    <h2 class="h5 fw-bold mb-1">Usuarios disponibles</h2>
                    <p class="text-muted mb-0"><span data-demo-visible-count>0</span> visibles · <span data-demo-selected-count>0</span> seleccionados</p>
                </div>
                <div class="d-flex flex-wrap gap-2">
                    <button class="btn btn-outline-primary btn-sm" type="button" data-demo-select-visible>
                        <i class="bi bi-check2-square me-1"></i> Seleccionar visibles
                    </button>
                    <button class="btn btn-outline-secondary btn-sm" type="button" data-demo-clear-selection>
                        <i class="bi bi-x-square me-1"></i> Limpiar seleccion
                    </button>
                </div>
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
                            <?php foreach ($userFields as $field): ?>
                                <?php if ((int) ($field['show_in_list'] ?? 0) === 1): ?>
                                    <th><?= e($field['label']) ?></th>
                                <?php endif; ?>
                            <?php endforeach; ?>
                            <th>Evaluaciones asignadas</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $user): ?>
                            <?php
                                $dynamicFields = $user['dynamic_fields'] ?? [];
                                $searchBlob = strtolower(trim(implode(' ', array_filter([
                                    $user['name'] ?? '',
                                    $user['email'] ?? '',
                                    $user['company_name'] ?? '',
                                    implode(' ', array_values($dynamicFields)),
                                ]))));
                            ?>
                            <tr
                                data-demo-user-row
                                data-user-id="<?= (int) $user['id'] ?>"
                                data-user-name="<?= e($user['name']) ?>"
                                data-company="<?= e($user['company_name'] ?? '') ?>"
                                data-statuses="<?= e(json_encode($user['test_statuses'] ?? [], JSON_UNESCAPED_UNICODE)) ?>"
                                data-assignment-names="<?= e(json_encode($user['active_assignment_names'] ?? [], JSON_UNESCAPED_UNICODE)) ?>"
                                data-fields="<?= e(json_encode($dynamicFields, JSON_UNESCAPED_UNICODE)) ?>"
                                data-search="<?= e($searchBlob) ?>"
                            >
                                <td>
                                    <input class="form-check-input" type="checkbox" name="user_ids[]" data-demo-user-check value="<?= (int) $user['id'] ?>" aria-label="Seleccionar <?= e($user['name']) ?>">
                                </td>
                                <td>
                                    <div class="fw-semibold"><?= e($user['name']) ?></div>
                                    <div class="text-muted small"><?= e($user['email']) ?></div>
                                </td>
                                <td>
                                    <div><?= e($user['company_name'] ?: 'Sin empresa') ?></div>
                                </td>
                                <?php foreach ($userFields as $field): ?>
                                    <?php if ((int) ($field['show_in_list'] ?? 0) === 1): ?>
                                        <td><?= e($dynamicFields[(string) $field['field_key']] ?? '-') ?></td>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                                <td><div class="assignment-status-list" data-demo-user-status>Sin asignaciones</div></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <aside class="card content-panel assignment-summary-panel">
            <h2 class="h5 fw-bold mb-3">Resumen</h2>
            <div class="assignment-summary-metrics">
                <div><span>Seleccionados</span><strong data-demo-summary-selected>0</strong></div>
                <div><span>Visibles</span><strong data-demo-summary-visible>0</strong></div>
                <div><span>Ya asignados</span><strong data-demo-summary-existing>0</strong></div>
            </div>

            <div class="assignment-preview-box mt-3">
                <span>Evaluaciones</span>
                <strong data-demo-summary-instrument>Sin seleccionar</strong>
            </div>

            <div class="mt-3">
                <label class="form-label">Usuarios seleccionados</label>
                <div class="assignment-selected-list" data-demo-selected-list>
                    <span class="text-muted small">Aun no hay usuarios seleccionados.</span>
                </div>
            </div>

            <button class="btn btn-primary w-100 mt-3" type="submit" data-demo-run disabled>
                <i class="bi bi-send-check me-1"></i> Asignar seleccionados
            </button>
            <a class="btn btn-outline-secondary w-100 mt-2" href="<?= e(has_permission('manage_tests') ? route_url('tests') : route_url('dashboard')) ?>">Volver</a>
        </aside>
    </section>
</form>

<script>
document.addEventListener('DOMContentLoaded', function () {
    var root = document.querySelector('[data-bulk-assignment-demo]');
    if (!root) {
        return;
    }

    var rows = Array.prototype.slice.call(root.querySelectorAll('[data-demo-user-row]'));
    var instrumentChecks = Array.prototype.slice.call(root.querySelectorAll('[data-demo-instrument-check]'));
    var search = root.querySelector('[data-demo-search]');
    var filters = Array.prototype.slice.call(root.querySelectorAll('[data-demo-filter]'));
    var fieldFilters = Array.prototype.slice.call(root.querySelectorAll('[data-demo-field]'));
    var checks = Array.prototype.slice.call(root.querySelectorAll('[data-demo-user-check]'));
    var checkAll = root.querySelector('[data-demo-check-all]');
    var selectedIds = new Set();

    function selectedInstruments() {
        return instrumentChecks.filter(function (check) {
            return check.checked;
        }).map(function (check) {
            return {
                id: check.value,
                name: check.dataset.name || ''
            };
        });
    }

    function escapeHtml(value) {
        return String(value).replace(/[&<>"']/g, function (character) {
            return {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            }[character];
        });
    }

    function rowStatuses(row) {
        var instruments = selectedInstruments();
        if (!instruments.length) {
            return [];
        }

        try {
            var statuses = JSON.parse(row.dataset.statuses || '{}');
            return instruments.map(function (currentInstrument) {
                return statuses[currentInstrument.id] || 'none';
            }).filter(function (status) {
                return status !== 'none';
            });
        } catch (error) {
            return [];
        }
    }

    function rowFields(row) {
        try {
            return JSON.parse(row.dataset.fields || '{}');
        } catch (error) {
            return {};
        }
    }

    function rowAssignmentNames(row) {
        try {
            return JSON.parse(row.dataset.assignmentNames || '[]');
        } catch (error) {
            return [];
        }
    }

    function matchesFilters(row) {
        var term = (search.value || '').trim().toLowerCase();
        if (term && !(row.dataset.search || '').includes(term)) {
            return false;
        }

        for (var i = 0; i < filters.length; i++) {
            var filter = filters[i];
            var value = filter.value || '';
            if (!value) {
                continue;
            }

            if ((row.dataset[filter.dataset.demoFilter] || '') !== value) {
                return false;
            }
        }

        var fields = rowFields(row);
        for (var j = 0; j < fieldFilters.length; j++) {
            var fieldFilter = fieldFilters[j];
            var fieldValue = (fieldFilter.value || '').trim().toLowerCase();
            if (!fieldValue) {
                continue;
            }

            var currentValue = String(fields[fieldFilter.dataset.demoField] || '').toLowerCase();
            if (!currentValue.includes(fieldValue)) {
                return false;
            }
        }

        return true;
    }

    function updateStatus(row) {
        var selected = selectedInstruments();
        var statuses = rowStatuses(row);
        var statusList = row.querySelector('[data-demo-user-status]');
        if (!statusList) {
            return;
        }
        var assignedNames = rowAssignmentNames(row);

        if (!assignedNames.length) {
            statusList.innerHTML = '<span class="badge text-bg-secondary">Sin asignaciones</span>';
            return;
        }

        var selectedNames = selected.map(function (currentInstrument) {
            return currentInstrument.name;
        });
        var allStatuses = {};
        try {
            allStatuses = JSON.parse(row.dataset.statuses || '{}');
        } catch (error) {
            allStatuses = {};
        }
        statusList.innerHTML = assignedNames.map(function (name) {
            var isSelected = selectedNames.includes(name);
            var instrument = instrumentChecks.find(function (check) {
                return (check.dataset.name || '') === name;
            });
            var status = instrument ? allStatuses[instrument.value] || '' : '';
            var badgeClass = isSelected ? 'text-bg-warning' : 'text-bg-primary';
            if (status === 'completed') {
                badgeClass = 'text-bg-success';
            } else if (status === 'expired') {
                badgeClass = 'text-bg-secondary';
            } else if (status === 'in_progress') {
                badgeClass = 'text-bg-warning';
            }
            return '<span class="badge ' + badgeClass + '">' + escapeHtml(name) + '</span>';
        }).join('');
    }

    function renderSelectedList() {
        var list = root.querySelector('[data-demo-selected-list]');
        var selectedRows = rows.filter(function (row) {
            return selectedIds.has(row.dataset.userId);
        });

        if (!selectedRows.length) {
            list.innerHTML = '<span class="text-muted small">Aun no hay usuarios seleccionados.</span>';
            return;
        }

        list.innerHTML = selectedRows.slice(0, 10).map(function (row) {
            return '<span>' + row.dataset.userName + '</span>';
        }).join('');

        if (selectedRows.length > 10) {
            list.insertAdjacentHTML('beforeend', '<span>+' + (selectedRows.length - 10) + ' mas</span>');
        }
    }

    function refresh() {
        var visibleRows = [];
        var selectedExisting = 0;
        var selectedCount = 0;
        var currentInstruments = selectedInstruments();

        rows.forEach(function (row) {
            updateStatus(row);
            var visible = matchesFilters(row);
            row.hidden = !visible;
            if (visible) {
                visibleRows.push(row);
            }

            var check = row.querySelector('[data-demo-user-check]');
            check.checked = selectedIds.has(row.dataset.userId);
            if (check.checked) {
                selectedCount++;
                selectedExisting += rowStatuses(row).length;
            }
        });

        if (checkAll) {
            checkAll.checked = visibleRows.length > 0 && visibleRows.every(function (row) {
                return selectedIds.has(row.dataset.userId);
            });
            checkAll.indeterminate = visibleRows.some(function (row) {
                return selectedIds.has(row.dataset.userId);
            }) && !checkAll.checked;
        }

        var selectedVisible = root.querySelector('[data-demo-selected-count]');
        var visibleCount = root.querySelector('[data-demo-visible-count]');
        var summarySelected = root.querySelector('[data-demo-summary-selected]');
        var summaryVisible = root.querySelector('[data-demo-summary-visible]');
        var summaryExisting = root.querySelector('[data-demo-summary-existing]');
        selectedVisible.textContent = selectedCount;
        visibleCount.textContent = visibleRows.length;
        summarySelected.textContent = selectedCount;
        summaryVisible.textContent = visibleRows.length;
        summaryExisting.textContent = selectedExisting;
        root.querySelector('[data-demo-instrument-count]').textContent = currentInstruments.length;
        root.querySelector('[data-demo-summary-instrument]').textContent = currentInstruments.length ? currentInstruments.map(function (currentInstrument) {
            return currentInstrument.name;
        }).join(', ') : 'Sin seleccionar';
        root.querySelector('[data-demo-run]').disabled = selectedCount === 0 || currentInstruments.length === 0;
        renderSelectedList();
    }

    checks.forEach(function (check) {
        check.addEventListener('change', function () {
            if (check.checked) {
                selectedIds.add(check.value);
            } else {
                selectedIds.delete(check.value);
            }
            refresh();
        });
    });

    if (checkAll) {
        checkAll.addEventListener('change', function () {
            rows.forEach(function (row) {
                if (!row.hidden) {
                    if (checkAll.checked) {
                        selectedIds.add(row.dataset.userId);
                    } else {
                        selectedIds.delete(row.dataset.userId);
                    }
                }
            });
            refresh();
        });
    }

    root.querySelector('[data-demo-select-visible]').addEventListener('click', function () {
        rows.forEach(function (row) {
            if (!row.hidden) {
                selectedIds.add(row.dataset.userId);
            }
        });
        refresh();
    });

    root.querySelector('[data-demo-clear-selection]').addEventListener('click', function () {
        selectedIds.clear();
        refresh();
    });

    root.querySelector('[data-demo-clear-filters]').addEventListener('click', function () {
        search.value = '';
        filters.forEach(function (filter) {
            filter.value = '';
        });
        fieldFilters.forEach(function (filter) {
            filter.value = '';
        });
        refresh();
    });

    [search].concat(instrumentChecks, filters, fieldFilters).forEach(function (control) {
        control.addEventListener('input', refresh);
        control.addEventListener('change', refresh);
    });

    refresh();
});
</script>
