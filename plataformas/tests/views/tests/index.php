<?php
$sessionStatusLabels = [
    'assigned' => 'Asignada',
    'in_progress' => 'En curso',
    'completed' => 'Completada',
    'cancelled' => 'Cancelada',
    'expired' => 'Expirada',
];
?>
<section class="page-header">
    <div>
        <p class="text-uppercase text-primary fw-bold small mb-1">Evaluaciones</p>
        <h1 class="fw-bold mb-1">Instrumentos</h1>
        <p class="text-muted mb-0">Administra el catalogo base de pruebas antes de cargar items y baremos autorizados.</p>
    </div>
    <div class="d-flex flex-wrap gap-2">
        <a class="btn btn-outline-primary" href="<?= e(route_url('tests.assign')) ?>"><i class="bi bi-send-check me-1"></i> Asignar</a>
        <form method="post" action="<?= e(route_url('tests.clear-results')) ?>" data-confirm-submit="Esta accion eliminara todas las asignaciones, respuestas, puntajes y reportes generados. Los instrumentos, preguntas y configuraciones se conservaran. Deseas limpiar todo y empezar desde cero?">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <button class="btn btn-outline-danger" type="submit"><i class="bi bi-trash3 me-1"></i> Limpiar resultados</button>
        </form>
        <a class="btn btn-primary" href="<?= e(route_url('test.new')) ?>"><i class="bi bi-clipboard-plus me-1"></i> Nueva evaluacion</a>
    </div>
</section>

<section class="card content-panel">
    <div class="table-responsive">
        <table class="table table-hover align-middle app-table app-data-table" data-export-title="Evaluaciones">
            <thead>
                <tr>
                    <th>Instrumento</th>
                    <th>Categoria</th>
                    <th>Duracion</th>
                    <th>Escalas</th>
                    <th>Items</th>
                    <th>Estado</th>
                    <th class="text-end no-sort no-export">Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($tests as $test): ?>
                    <tr>
                        <td>
                            <div class="fw-semibold"><?= e($test['name']) ?></div>
                            <div class="text-muted small"><code><?= e($test['code']) ?></code><?= $test['requires_manual_review'] ? ' · requiere revision de manual' : '' ?></div>
                        </td>
                        <td><?= e($categories[$test['category']] ?? $test['category']) ?></td>
                        <td><?= (int) $test['duration_minutes'] > 0 ? (int) $test['duration_minutes'] . ' min' : 'Sin limite' ?></td>
                        <td><?= (int) $test['scales_count'] ?></td>
                        <td><?= (int) $test['items_count'] ?></td>
                        <td><span class="badge <?= $test['status'] === 'active' ? 'text-bg-success' : ($test['status'] === 'draft' ? 'text-bg-warning' : 'text-bg-secondary') ?>"><?= e($statuses[$test['status']] ?? $test['status']) ?></span></td>
                        <td class="text-end">
                            <div class="btn-group">
                                <button class="btn btn-sm btn-outline-primary dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                    Acciones
                                </button>
                                <ul class="dropdown-menu dropdown-menu-end">
                                    <li><h6 class="dropdown-header">Exportaciones</h6></li>
                                    <li><a class="dropdown-item" href="<?= e(route_url('test.result-export', (int) $test['id'])) ?>"><i class="bi bi-file-earmark-excel me-2"></i> Resultados</a></li>
                                    <li><a class="dropdown-item" href="<?= e(route_url('test.answers-export', (int) $test['id'])) ?>"><i class="bi bi-file-earmark-spreadsheet me-2"></i> Preguntas y respuestas</a></li>
                                    <li><hr class="dropdown-divider"></li>
                                    <li><a class="dropdown-item" href="<?= e(route_url('test.content', (int) $test['id'])) ?>"><i class="bi bi-list-check me-2"></i> Contenido</a></li>
                                    <li><a class="dropdown-item" href="<?= e(route_url('test.edit', (int) $test['id'])) ?>"><i class="bi bi-pencil me-2"></i> Editar</a></li>
                                </ul>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<section class="card content-panel mt-4">
    <?php $hasCancelableSessions = (bool) array_filter($sessions, static fn(array $session): bool => in_array($session['status'], ['assigned', 'in_progress'], true)); ?>
    <?php $hasFinishedSessions = (bool) ($hasFinishedSessions ?? false); ?>
    <?php
    $sessionInstruments = [];
    $sessionUsers = [];
    foreach ($sessions as $session) {
        $instrumentId = (int) $session['instrument_id'];
        $userId = (int) $session['user_id'];
        $createdAt = strtotime((string) ($session['created_at'] ?? '')) ?: 0;

        if (!isset($sessionInstruments[$instrumentId])) {
            $sessionInstruments[$instrumentId] = [
                'id' => $instrumentId,
                'name' => (string) $session['instrument_name'],
                'code' => (string) ($session['instrument_code'] ?? ''),
            ];
        }

        if (!isset($sessionUsers[$userId])) {
            $sessionUsers[$userId] = [
                'id' => $userId,
                'name' => (string) $session['user_name'],
                'email' => (string) $session['user_email'],
                'sessions' => [],
                'finished_count' => 0,
                'latest_finished_at' => '',
            ];
        }

        $existing = $sessionUsers[$userId]['sessions'][$instrumentId] ?? null;
        $existingCreatedAt = $existing ? (strtotime((string) ($existing['created_at'] ?? '')) ?: 0) : 0;
        if (!$existing || $createdAt >= $existingCreatedAt) {
            $sessionUsers[$userId]['sessions'][$instrumentId] = $session;
        }

        if (in_array($session['status'], ['completed', 'expired'], true)) {
            $sessionUsers[$userId]['finished_count']++;
            $finishedAt = (string) ($session['completed_at'] ?? $session['updated_at'] ?? $session['created_at'] ?? '');
            if ($finishedAt !== '' && ($sessionUsers[$userId]['latest_finished_at'] === '' || strtotime($finishedAt) > strtotime($sessionUsers[$userId]['latest_finished_at']))) {
                $sessionUsers[$userId]['latest_finished_at'] = $finishedAt;
            }
        }
    }
    uasort($sessionInstruments, static fn(array $a, array $b): int => strcmp($a['name'], $b['name']));
    uasort($sessionUsers, static fn(array $a, array $b): int => strcmp($a['name'], $b['name']));
    ?>
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <div>
            <h2 class="h5 fw-bold mb-1">Asignaciones y resultados recientes</h2>
            <p class="text-muted mb-0">Una fila por persona, con el estado de cada evaluacion asignada.</p>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <a
                class="btn btn-success <?= $hasFinishedSessions ? '' : 'disabled' ?>"
                href="<?= $hasFinishedSessions ? e(route_url('tests.progress-export')) : '#' ?>"
                data-confirm-link="El Resumen General incluira la hoja Ranking Resumen. Los usuarios sin resultados completos de IPIP-16PF y CAG no entraran al ranking y quedaran informados como advertencia en el Excel. Deseas generar el archivo?"
                data-confirm-title="Generar Resumen General"
                data-confirm-button="Generar Excel"
                <?= $hasFinishedSessions ? '' : 'aria-disabled="true" tabindex="-1" title="No hay evaluaciones terminadas para exportar"' ?>
            >
                <i class="bi bi-file-earmark-excel me-1"></i> Resumen General
            </a>
            <form method="post" action="<?= e(route_url('tests.cancel-all')) ?>" data-confirm-submit="Cancelar todas las asignaciones activas impedira que todos los usuarios pendientes continuen respondiendo. Deseas continuar?">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <button class="btn btn-outline-danger" type="submit" <?= $hasCancelableSessions ? '' : 'disabled' ?>><i class="bi bi-x-octagon me-1"></i> Cancelar todo</button>
            </form>
        </div>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle app-table app-data-table assignment-matrix-table" data-export-title="Asignaciones">
            <thead>
                <tr>
                    <th>Usuario</th>
                    <?php foreach ($sessionInstruments as $instrument): ?>
                        <th><?= e($instrument['name']) ?></th>
                    <?php endforeach; ?>
                    <th>Terminadas</th>
                    <th class="text-end no-sort no-export">Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($sessionUsers as $sessionUser): ?>
                    <tr>
                        <td>
                            <div class="fw-semibold"><?= e($sessionUser['name']) ?></div>
                            <div class="text-muted small"><?= e($sessionUser['email']) ?></div>
                        </td>
                        <?php foreach ($sessionInstruments as $instrument): ?>
                            <?php $session = $sessionUser['sessions'][(int) $instrument['id']] ?? null; ?>
                            <td>
                                <?php if ($session): ?>
                                    <?php $sessionStatusLabel = $sessionStatusLabels[(string) $session['status']] ?? labelize((string) $session['status']); ?>
                                    <span class="badge <?= $session['status'] === 'completed' ? 'text-bg-success' : ($session['status'] === 'in_progress' ? 'text-bg-warning' : (in_array($session['status'], ['cancelled', 'expired'], true) ? 'text-bg-secondary' : 'text-bg-primary')) ?>"><?= e($sessionStatusLabel) ?></span>
                                    <div class="text-muted small mt-1"><?= e((string) ($session['completed_at'] ?? $session['created_at'] ?? '')) ?></div>
                                <?php else: ?>
                                    <span class="text-muted small">Sin asignacion</span>
                                <?php endif; ?>
                            </td>
                        <?php endforeach; ?>
                        <td>
                            <span class="badge <?= $sessionUser['finished_count'] > 0 ? 'text-bg-success' : 'text-bg-light border' ?>"><?= (int) $sessionUser['finished_count'] ?></span>
                        </td>
                        <td class="text-end">
                            <?php if ($sessionUser['finished_count'] > 0): ?>
                                <a class="btn btn-sm btn-outline-primary" href="<?= e(route_url('test-user.results', (int) $sessionUser['id'])) ?>"><i class="bi bi-bar-chart me-1"></i> Resultado</a>
                            <?php else: ?>
                                <span class="text-muted small">No disponible</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
