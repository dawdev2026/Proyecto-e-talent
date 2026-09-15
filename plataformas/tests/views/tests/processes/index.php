<section class="page-header">
    <div>
        <p class="text-uppercase text-primary fw-bold small mb-1">Evaluaciones</p>
        <h1 class="fw-bold mb-1">Procesos</h1>
        <p class="text-muted mb-0">Agrupa evaluaciones, usuarios, administradores y campos de seguimiento en un flujo controlado.</p>
    </div>
    <div class="d-flex flex-wrap gap-2">
        <?php if (!empty($canViewDashboard)): ?>
            <a class="btn btn-outline-primary" href="<?= e(route_url('test-process.dashboard')) ?>"><i class="bi bi-bar-chart-line me-1"></i> Dashboard Avance</a>
        <?php endif; ?>
        <?php if (has_permission('manage_tests') || has_permission('manage_test_processes') || has_permission('manage_company_processes')): ?>
            <a class="btn btn-primary" href="<?= e(route_url('test-process.new')) ?>"><i class="bi bi-plus-lg me-1"></i> Nuevo proceso</a>
        <?php endif; ?>
    </div>
</section>

<?php
$dateGroups = $dateGroups ?? [];
$selectedDateGroup = (string) ($selectedDateGroup ?? '');
$totalProcesses = (int) ($totalProcesses ?? count($processes ?? []));
$selectedGroup = $selectedDateGroup !== '' ? ($dateGroups[$selectedDateGroup] ?? null) : null;
?>

<section class="content-panel">
    <?php if (!$processes): ?>
        <?php if ($totalProcesses > 0): ?>
            <div class="d-flex flex-wrap align-items-end justify-content-between gap-3">
                <form class="process-date-filter" method="get" action="<?= e(route_url('test-processes')) ?>">
                    <label class="form-label small text-muted mb-1" for="process_date_group_empty">Agrupar por inicio y cierre</label>
                    <select id="process_date_group_empty" class="form-select" name="date_group" onchange="this.form.submit()">
                        <option value="">Todos los procesos (<?= (int) $totalProcesses ?>)</option>
                        <?php foreach ($dateGroups as $group): ?>
                            <option value="<?= e((string) $group['key']) ?>" <?= $selectedDateGroup === (string) $group['key'] ? 'selected' : '' ?>>
                                <?= e((string) $group['label']) ?> (<?= (int) $group['count'] ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </form>
                <?php if ($selectedDateGroup !== ''): ?>
                    <a class="btn btn-outline-secondary" href="<?= e(route_url('test-processes')) ?>"><i class="bi bi-x-lg me-1"></i> Limpiar filtro</a>
                <?php endif; ?>
            </div>
            <div class="alert alert-light border mb-0">No hay procesos en la agrupacion seleccionada.</div>
        <?php else: ?>
            <div class="alert alert-light border mb-0">Aun no hay procesos disponibles para tu perfil.</div>
        <?php endif; ?>
    <?php else: ?>
        <div class="d-flex flex-wrap align-items-end justify-content-between gap-3">
            <form class="process-date-filter" method="get" action="<?= e(route_url('test-processes')) ?>">
                <label class="form-label small text-muted mb-1" for="process_date_group">Agrupar por inicio y cierre</label>
                <select id="process_date_group" class="form-select" name="date_group" onchange="this.form.submit()">
                    <option value="">Todos los procesos (<?= (int) $totalProcesses ?>)</option>
                    <?php foreach ($dateGroups as $group): ?>
                        <option value="<?= e((string) $group['key']) ?>" <?= $selectedDateGroup === (string) $group['key'] ? 'selected' : '' ?>>
                            <?= e((string) $group['label']) ?> (<?= (int) $group['count'] ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </form>
            <div class="d-flex flex-wrap align-items-center gap-2">
                <span class="badge text-bg-light border">
                    <?= $selectedGroup ? e((string) $selectedGroup['label']) : 'Todos los grupos' ?>
                </span>
                <span class="text-muted small">
                    <?= count($processes) ?> de <?= (int) $totalProcesses ?> procesos
                </span>
                <?php if ($selectedDateGroup !== ''): ?>
                    <a class="btn btn-sm btn-outline-secondary" href="<?= e(route_url('test-processes')) ?>"><i class="bi bi-x-lg me-1"></i> Limpiar</a>
                <?php endif; ?>
            </div>
        </div>
        <div class="table-responsive">
            <table class="table align-middle app-table app-data-table" data-export-title="Procesos">
                <thead>
                    <tr>
                        <th>Proceso</th>
                        <th>Estado</th>
                        <th>Evaluaciones</th>
                        <th>Usuarios</th>
                        <th>Online rindiendo</th>
                        <th>
                            Avance sesiones
                            <button
                                class="btn btn-link btn-sm p-0 ms-1"
                                type="button"
                                aria-label="Ayuda sobre avance sesiones"
                                data-bs-toggle="popover"
                                data-bs-trigger="focus"
                                data-bs-placement="top"
                                data-bs-title="Avance sesiones"
                                data-bs-content="Porcentaje de evaluaciones con avance sobre el total de asignaciones generadas dentro del proceso. Considera estados Completada, Expirada y En curso. No representa usuarios que hayan terminado todas sus evaluaciones."
                            >
                                <i class="bi bi-info-circle" aria-hidden="true"></i>
                            </button>
                        </th>
                        <th>Fechas</th>
                        <th class="no-sort no-export">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($processes as $processIndex => $process): ?>
                        <?php
                        $sessionsCount = (int) ($process['sessions_count'] ?? 0) + (int) ($process['evaluation_assignments_count'] ?? 0);
                        $completedSessions = (int) ($process['completed_sessions'] ?? 0) + (int) ($process['completed_evaluation_assignments'] ?? 0);
                        $percent = $sessionsCount > 0 ? round(($completedSessions / $sessionsCount) * 100) : 0;
                        $processCode = trim((string) ($process['code'] ?? ''));
                        $processNumber = preg_match('/^p0*(\d+)$/i', $processCode, $processCodeMatches)
                            ? (int) $processCodeMatches[1]
                            : ((int) $processIndex + 1);
                        $processName = trim((string) ($process['name'] ?? ''));
                        $processLabel = $processName !== '' ? $processName : 'Proceso ' . $processNumber;
                        ?>
                        <tr>
                            <td>
                                <div class="fw-semibold"><?= e($processLabel) ?></div>
                                <div class="text-muted small"><code><?= e((string) $process['code']) ?></code></div>
                            </td>
                            <td><span class="badge text-bg-light border"><?= e($statuses[$process['status']] ?? labelize((string) $process['status'])) ?></span></td>
                            <td><?= (int) ($process['evaluations_count'] ?? $process['instruments_count'] ?? 0) ?></td>
                            <td><?= (int) ($process['users_count'] ?? 0) ?></td>
                            <td>
                                <?php $onlineUsers = (int) ($process['online_users_count'] ?? 0); ?>
                                <span class="badge <?= $onlineUsers > 0 ? 'text-bg-success' : 'text-bg-light border' ?>">
                                    <?= $onlineUsers ?>
                                </span>
                                <div class="text-muted small">ultimos 90 s</div>
                            </td>
                            <td>
                                <div class="d-flex align-items-center gap-2">
                                    <div class="progress flex-grow-1" role="progressbar" aria-label="Avance del proceso" aria-valuenow="<?= (int) $percent ?>" aria-valuemin="0" aria-valuemax="100" style="height: .6rem; min-width: 120px;">
                                        <div class="progress-bar" style="width: <?= (int) $percent ?>%;"></div>
                                    </div>
                                    <span class="small text-muted"><?= (int) $completedSessions ?> / <?= (int) $sessionsCount ?></span>
                                </div>
                            </td>
                            <td class="small">
                                <div><?= !empty($process['starts_at']) ? e((string) $process['starts_at']) : 'Sin inicio' ?></div>
                                <div><?= !empty($process['ends_at']) ? e((string) $process['ends_at']) : 'Sin cierre' ?></div>
                            </td>
                            <td>
                                <div class="d-flex flex-wrap gap-2">
                                    <?php if (!empty($process['can_view_process'])): ?>
                                        <a class="btn btn-sm btn-outline-primary" href="<?= e(route_url('test-process.show', (int) $process['id'])) ?>"><i class="bi bi-kanban me-1"></i> Ver</a>
                                    <?php endif; ?>
                                    <?php if (!empty($process['can_view_ranking'])): ?>
                                        <a class="btn btn-sm btn-outline-primary" href="<?= e(route_url('test-process.ranking', (int) $process['id'])) ?>"><i class="bi bi-trophy me-1"></i> Ranking Resumen</a>
                                    <?php endif; ?>
                                    <?php if (!empty($process['can_manage_settings'])): ?>
                                        <a class="btn btn-sm btn-outline-secondary" href="<?= e(route_url('test-process.edit', (int) $process['id'])) ?>"><i class="bi bi-pencil me-1"></i> Editar</a>
                                        <?php if (!empty($process['has_results'])): ?>
                                            <button class="btn btn-sm btn-outline-danger" type="button" disabled title="No se puede eliminar porque existen evaluaciones con resultados.">
                                                <i class="bi bi-trash me-1"></i> Eliminar
                                            </button>
                                        <?php else: ?>
                                            <form
                                                method="post"
                                                action="<?= e(route_url('test-process.delete', (int) $process['id'])) ?>"
                                                data-confirm-submit="Esta accion eliminara el proceso completo junto con sus usuarios asignados, asignaciones pendientes y configuracion asociada. Solo se permite si no existen evaluaciones con resultados. Deseas continuar?"
                                            >
                                                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                                <button class="btn btn-sm btn-outline-danger" type="submit"><i class="bi bi-trash me-1"></i> Eliminar</button>
                                            </form>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>
