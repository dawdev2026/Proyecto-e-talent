<?php
$review = $review ?? null;
$rut = (string) ($rut ?? '');
$error = (string) ($error ?? '');
$companyScoped = (bool) ($companyScoped ?? false);
$companyName = (string) ($companyName ?? '');
$user = is_array($review ?? null) ? ($review['user'] ?? null) : null;
$assignments = is_array($review ?? null) ? ($review['assignments'] ?? []) : [];
$candidateProcesses = is_array($review ?? null) ? ($review['candidate_processes'] ?? []) : [];
$sessionStatuses = [
    'assigned' => 'Asignada',
    'in_progress' => 'En progreso',
    'completed' => 'Completada',
    'expired' => 'Expirada',
    'cancelled' => 'Cancelada',
];
$assignmentStatuses = [
    'assigned' => 'Asignado',
    'in_progress' => 'En progreso',
    'completed' => 'Completado',
    'cancelled' => 'Cancelado',
];
$formatDateTime = static function ($value): string {
    $raw = trim((string) $value);
    if ($raw === '') {
        return 'Sin fecha';
    }

    try {
        return (new DateTimeImmutable($raw))->format('d/m/Y H:i');
    } catch (Throwable $exception) {
        return $raw;
    }
};
?>

<section class="page-header">
    <div>
        <p class="text-uppercase text-primary fw-bold small mb-1">Evaluaciones</p>
        <h1 class="fw-bold mb-1">Revisar asignacion</h1>
        <p class="text-muted mb-0">Consulta el proceso actual de un usuario y reasignalo a un proceso activo del dia.</p>
    </div>
    <div class="d-flex flex-wrap gap-2">
        <a class="btn btn-outline-secondary" href="<?= e(route_url('test-processes')) ?>"><i class="bi bi-kanban me-1"></i> Procesos</a>
    </div>
</section>

<section class="card content-panel">
    <form class="row g-3 align-items-end" method="get" action="<?= e(route_url('test-process.review-assignment')) ?>">
        <div class="col-12 col-md-5 col-lg-4">
            <label class="form-label" for="assignment_review_rut">RUT usuario</label>
            <input id="assignment_review_rut" class="form-control form-control-lg" type="text" name="rut" value="<?= e($rut) ?>" placeholder="12.345.678-5" maxlength="12" data-rut-input required>
        </div>
        <div class="col-12 col-md-auto">
            <button class="btn btn-primary btn-lg" type="submit"><i class="bi bi-search me-1"></i> Revisar asignacion</button>
        </div>
    </form>
    <?php if ($companyScoped): ?>
        <div class="alert alert-info mt-3 mb-0">
            <i class="bi bi-building me-1"></i>
            Solo puedes revisar y reasignar usuarios de <?= e($companyName !== '' ? $companyName : 'tu empresa') ?>.
            El RUT ingresado se valida contra esa empresa.
        </div>
    <?php endif; ?>
    <?php if ($error !== ''): ?>
        <div class="alert alert-warning mt-3 mb-0"><?= e($error) ?></div>
    <?php endif; ?>
</section>

<?php if (is_array($review)): ?>
    <?php if (!$user): ?>
        <section class="card content-panel">
            <div class="alert alert-light border mb-0">
                <?= $companyScoped
                    ? 'No se encontro un usuario de ' . e($companyName !== '' ? $companyName : 'tu empresa') . ' con el RUT indicado.'
                    : 'No se encontro un usuario con el RUT ' . e((string) ($review['rut'] ?? $rut)) . '.' ?>
            </div>
        </section>
    <?php else: ?>
        <section class="card content-panel">
            <div class="d-flex flex-wrap justify-content-between gap-3">
                <div>
                    <p class="text-uppercase text-primary fw-bold small mb-1">Usuario</p>
                    <h2 class="h4 fw-bold mb-1"><?= e((string) ($user['name'] ?? 'Usuario')) ?></h2>
                    <div class="text-muted"><?= e((string) ($user['rut'] ?? '')) ?><?= !empty($user['email']) ? ' · ' . e((string) $user['email']) : '' ?></div>
                    <?php if (!empty($user['company_name'])): ?>
                        <div class="text-muted small"><?= e((string) $user['company_name']) ?></div>
                    <?php endif; ?>
                </div>
                <span class="badge <?= (int) ($user['is_active'] ?? 0) === 1 ? 'text-bg-success' : 'text-bg-secondary' ?>">
                    <?= (int) ($user['is_active'] ?? 0) === 1 ? 'Activo' : 'Inactivo' ?>
                </span>
            </div>
        </section>

        <section class="card content-panel">
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
                <div>
                    <h2 class="h5 fw-bold mb-1">Asignaciones actuales</h2>
                    <p class="text-muted mb-0">Estado del usuario dentro de cada proceso y sus evaluaciones asociadas.</p>
                </div>
                <span class="badge text-bg-light border"><?= count($assignments) ?> proceso(s)</span>
            </div>

            <?php if (!$assignments): ?>
                <div class="alert alert-light border mb-0">El usuario no tiene procesos asignados actualmente.</div>
            <?php else: ?>
                <div class="assignment-review-stack">
                    <?php foreach ($assignments as $assignment): ?>
                        <?php
                        $processId = (int) ($assignment['process_id'] ?? 0);
                        $answersCount = (int) ($assignment['answers_count'] ?? 0);
                        $hasAnswers = $answersCount > 0;
                        $confirmMessage = 'El usuario tiene respuestas guardadas en este proceso. Para re-asignarlo se eliminaran las respuestas de los test del proceso origen. Deseas continuar?';
                        ?>
                        <div class="assignment-review-item">
                            <div class="assignment-review-heading">
                                <div>
                                    <h3 class="h6 fw-bold mb-1"><?= e((string) ($assignment['process_name'] ?? 'Proceso')) ?></h3>
                                    <div class="text-muted small">
                                        <code><?= e((string) ($assignment['process_code'] ?? '')) ?></code>
                                        · <?= e($formatDateTime($assignment['starts_at'] ?? null)) ?> al <?= e($formatDateTime($assignment['ends_at'] ?? null)) ?>
                                    </div>
                                </div>
                                <div class="d-flex flex-wrap gap-2">
                                    <span class="badge text-bg-light border"><?= e($assignmentStatuses[$assignment['assignment_status'] ?? ''] ?? labelize((string) ($assignment['assignment_status'] ?? 'asignado'))) ?></span>
                                    <span class="badge <?= $hasAnswers ? 'text-bg-warning' : 'text-bg-success' ?>"><?= $answersCount ?> respuesta(s)</span>
                                </div>
                            </div>

                            <?php if (!empty($assignment['sessions'])): ?>
                                <div class="table-responsive mt-3">
                                    <table class="table table-hover align-middle app-table mb-0">
                                        <thead>
                                            <tr>
                                                <th>Test</th>
                                                <th>Estado</th>
                                                <th>Respuestas</th>
                                                <th>Inicio</th>
                                                <th>Termino</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($assignment['sessions'] as $session): ?>
                                                <tr>
                                                    <td>
                                                        <div class="fw-semibold"><?= e((string) ($session['instrument_name'] ?? 'Test')) ?></div>
                                                        <code class="small"><?= e((string) ($session['instrument_code'] ?? '')) ?></code>
                                                    </td>
                                                    <td><span class="badge text-bg-light border"><?= e($sessionStatuses[$session['status'] ?? ''] ?? labelize((string) ($session['status'] ?? ''))) ?></span></td>
                                                    <td><?= (int) ($session['answers_count'] ?? 0) ?></td>
                                                    <td class="small"><?= e($formatDateTime($session['started_at'] ?? null)) ?></td>
                                                    <td class="small"><?= e($formatDateTime($session['completed_at'] ?? null)) ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>

                            <form
                                class="assignment-review-reassign"
                                method="post"
                                action="<?= e(route_url('test-process.review-assignment')) ?>"
                                <?= $hasAnswers ? 'data-confirm-submit="' . e($confirmMessage) . '"' : '' ?>
                            >
                                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                <input type="hidden" name="rut" value="<?= e((string) ($user['rut'] ?? $rut)) ?>">
                                <input type="hidden" name="source_process_id" value="<?= (int) $processId ?>">
                                <input type="hidden" name="confirm_delete_answers" value="<?= $hasAnswers ? '1' : '0' ?>">
                                <div class="row g-2 align-items-end">
                                    <div class="col-12 col-lg">
                                        <label class="form-label small text-muted" for="target_process_<?= (int) $processId ?>">Reasignar a proceso del dia</label>
                                        <select id="target_process_<?= (int) $processId ?>" class="form-select" name="target_process_id" required <?= !$candidateProcesses ? 'disabled' : '' ?>>
                                            <option value="">Selecciona proceso destino</option>
                                            <?php foreach ($candidateProcesses as $candidate): ?>
                                                <?php $sameProcess = (int) ($candidate['id'] ?? 0) === $processId; ?>
                                                <option value="<?= (int) ($candidate['id'] ?? 0) ?>" <?= $sameProcess ? 'disabled' : '' ?>>
                                                    <?= e((string) ($candidate['name'] ?? 'Proceso')) ?> · <?= e((string) ($candidate['code'] ?? '')) ?> · <?= e($formatDateTime($candidate['starts_at'] ?? null)) ?> al <?= e($formatDateTime($candidate['ends_at'] ?? null)) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-12 col-lg-auto">
                                        <button class="btn btn-primary w-100" type="submit" <?= !$candidateProcesses ? 'disabled' : '' ?>>
                                            <i class="bi bi-arrow-left-right me-1"></i> Reasignar
                                        </button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>

        <section class="card content-panel">
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
                <div>
                    <h2 class="h5 fw-bold mb-1">Procesos del dia</h2>
                    <p class="text-muted mb-0">Procesos activos disponibles como destino con inicio o termino durante el dia actual.</p>
                </div>
                <span class="badge text-bg-light border"><?= count($candidateProcesses) ?> disponible(s)</span>
            </div>

            <?php if (!$candidateProcesses): ?>
                <div class="alert alert-light border mb-0">No existen procesos activos del dia con evaluaciones configuradas.</div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle app-table mb-0">
                        <thead>
                            <tr>
                                <th>Proceso</th>
                                <th>Codigo</th>
                                <th>Inicio</th>
                                <th>Termino</th>
                                <th>Evaluaciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($candidateProcesses as $process): ?>
                                <tr>
                                    <td class="fw-semibold"><?= e((string) ($process['name'] ?? '')) ?></td>
                                    <td><code><?= e((string) ($process['code'] ?? '')) ?></code></td>
                                    <td><?= e($formatDateTime($process['starts_at'] ?? null)) ?></td>
                                    <td><?= e($formatDateTime($process['ends_at'] ?? null)) ?></td>
                                    <td><?= (int) ($process['instruments_count'] ?? 0) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>
    <?php endif; ?>
<?php endif; ?>
