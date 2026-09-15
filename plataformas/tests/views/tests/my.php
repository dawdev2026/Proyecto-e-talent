<?php
$evaluationMessages = array_merge(TestSettingsModel::DEFAULTS, $evaluationMessages ?? []);
$pendingAutoStartSession = is_array($pendingAutoStartSession ?? null) ? $pendingAutoStartSession : null;
$pendingAutoStartSessionId = $pendingAutoStartSession ? (int) ($pendingAutoStartSession['id'] ?? 0) : 0;
$assignedTestsFinalized = (bool) ($assignedTestsFinalized ?? false);
$interviewAppointments = is_array($interviewAppointments ?? null) ? $interviewAppointments : [];
$evaluationAssignments = is_array($evaluationAssignments ?? null) ? $evaluationAssignments : [];
$statusLabels = [
    'assigned' => 'Asignada',
    'in_progress' => 'En curso',
    'completed' => 'Completada',
    'cancelled' => 'Cancelada',
    'expired' => 'Expirada',
];
$serverNow = (int) ($serverNow ?? time());

if (!function_exists('test_entry_instructions_html')) {
    function test_entry_instructions_html(string $instructions): string
    {
        $instructions = trim($instructions);
        if ($instructions === '') {
            return '';
        }

        if (preg_match('/<(p|br|strong|b|em|i|u|ul|ol|li)\b/i', $instructions)) {
            $clean = strip_tags($instructions, '<p><br><strong><b><em><i><u><ul><ol><li>');
            $clean = preg_replace('/\s+on[a-z]+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $clean) ?? '';
            $clean = preg_replace('/<(p|strong|b|em|i|u|ul|ol|li|br)\b[^>]*>/i', '<$1>', $clean) ?? '';

            return $clean;
        }

        return '<p>' . nl2br(e($instructions)) . '</p>';
    }
}
?>
<section class="page-header">
    <div>
        <p class="text-uppercase text-primary fw-bold small mb-1">Evaluaciones</p>
        <h1 class="fw-bold mb-1">Mis evaluaciones</h1>
        <p class="text-muted mb-0">Pruebas asignadas para responder o revisar resultados demo.</p>
    </div>
    <div class="server-clock-card" data-server-clock="<?= $serverNow ?>" data-server-offset="<?= (int) date('Z', $serverNow) ?>">
        <span><i class="bi bi-clock-history"></i> Hora servidor</span>
        <strong data-server-clock-display><?= e(date('d/m/Y H:i:s', $serverNow)) ?></strong>
    </div>
</section>

<?php if ($pendingAutoStartSession): ?>
    <div
        data-auto-start-required
        data-test-name="<?= e((string) ($pendingAutoStartSession['instrument_name'] ?? 'Evaluacion')) ?>"
        data-test-url="<?= e(route_url('test-session.take', $pendingAutoStartSessionId)) ?>"
        data-test-entry-target="#auto-start-test-entry-<?= (int) $pendingAutoStartSessionId ?>"
        hidden
    ></div>
    <section class="content-panel">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
            <div>
                <p class="text-uppercase text-primary fw-bold small mb-1">Evaluacion obligatoria</p>
                <h2 class="h5 fw-bold mb-1"><?= e((string) ($pendingAutoStartSession['instrument_name'] ?? 'Evaluacion')) ?></h2>
                <p class="text-muted mb-0">Debes continuar esta evaluacion antes de responder las demas.</p>
            </div>
            <a
                id="auto-start-test-entry-<?= (int) $pendingAutoStartSessionId ?>"
                class="btn btn-primary"
                href="<?= e(route_url('test-session.take', $pendingAutoStartSessionId)) ?>"
                data-test-entry-confirm
                data-test-entry-required="1"
                data-test-name="<?= e((string) ($pendingAutoStartSession['instrument_name'] ?? 'Evaluacion')) ?>"
                data-test-instructions-template="test-entry-instructions-auto-<?= (int) $pendingAutoStartSessionId ?>"
                data-test-activity-tracking="<?= (int) ($pendingAutoStartSession['track_activity_enabled'] ?? 0) === 1 ? '1' : '0' ?>"
                data-test-supervised-mode="<?= (int) ($pendingAutoStartSession['supervised_mode_enabled'] ?? 0) === 1 ? '1' : '0' ?>"
                data-test-entry-title="<?= e($evaluationMessages['entry_confirm_title']) ?>"
                data-test-entry-message="<?= e($evaluationMessages['entry_confirm_message']) ?>"
                data-test-entry-confirm-button="<?= e($evaluationMessages['entry_confirm_button']) ?>"
                data-test-entry-cancel-button="<?= e($evaluationMessages['entry_cancel_button']) ?>"
                data-test-activity-notice="<?= e($evaluationMessages['activity_tracking_notice']) ?>"
                data-test-activity-disabled-notice="<?= e($evaluationMessages['activity_tracking_disabled_notice']) ?>"
            ><i class="bi bi-play-circle me-1"></i> Continuar evaluacion</a>
            <template id="test-entry-instructions-auto-<?= (int) $pendingAutoStartSessionId ?>">
                <?= test_entry_instructions_html((string) ($pendingAutoStartSession['instructions'] ?? '')) ?>
            </template>
        </div>
    </section>
<?php endif; ?>

<?php if ($interviewAppointments): ?>
    <section class="content-panel">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-3">
            <div>
                <p class="text-uppercase text-primary fw-bold small mb-1">Entrevistas seleccion</p>
                <h2 class="h5 fw-bold mb-1">Mis entrevistas</h2>
                <p class="text-muted mb-0">Procesos de entrevista en los que estas inscrito.</p>
            </div>
        </div>
        <div class="table-responsive">
            <table class="table align-middle app-table app-data-table" data-export-title="Mis entrevistas" data-export-excel="false" data-export-pdf="false" data-page-length="5">
                <thead>
                    <tr>
                        <th>Proceso</th>
                        <th>Fecha</th>
                        <th>Hora</th>
                        <th>Moderador</th>
                        <th>Estado</th>
                        <th class="text-end no-sort no-export">Ingreso</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($interviewAppointments as $appointment): ?>
                        <?php
                        $startTimestamp = strtotime((string) ($appointment['scheduled_start_at'] ?? ''));
                        $endTimestamp = strtotime((string) ($appointment['scheduled_end_at'] ?? ''));
                        $meetingStatus = (string) ($appointment['meeting_status'] ?? 'scheduled');
                        $canEnterInterview = $meetingStatus === 'in_progress';
                        $statusLabel = [
                            'scheduled' => 'Programada',
                            'waiting' => 'En espera',
                            'in_progress' => 'Sala abierta',
                            'finished' => 'Finalizada',
                            'cancelled' => 'Cancelada',
                        ][$meetingStatus] ?? labelize($meetingStatus);
                        ?>
                        <tr>
                            <td>
                                <div class="fw-semibold"><?= e((string) ($appointment['process_name'] ?? 'Entrevista')) ?></div>
                            </td>
                            <td><?= $startTimestamp ? e(date('d/m/Y', $startTimestamp)) : '-' ?></td>
                            <td>
                                <?= $startTimestamp ? e(date('H:i', $startTimestamp)) : '-' ?>
                                <?php if ($endTimestamp): ?>
                                    <span class="text-muted small">- <?= e(date('H:i', $endTimestamp)) ?></span>
                                <?php endif; ?>
                            </td>
                            <td><?= e((string) ($appointment['moderator_name'] ?? '-')) ?></td>
                            <td><span class="badge <?= $canEnterInterview ? 'text-bg-success' : 'text-bg-secondary' ?>"><?= e($statusLabel) ?></span></td>
                            <td class="text-end">
                                <?php if ($canEnterInterview): ?>
                                    <a class="btn btn-sm btn-primary" href="<?= e(route_url('interview-appointment.room', (int) $appointment['id'])) ?>">
                                        <i class="bi bi-camera-video me-1"></i> Ingresar
                                    </a>
                                <?php else: ?>
                                    <button
                                        class="btn btn-sm btn-outline-secondary"
                                        type="button"
                                        data-interview-waiting
                                        data-message="La sala aun no esta disponible. Espera a que el moderador ingrese para habilitar la videollamada."
                                    >
                                        <i class="bi bi-lock me-1"></i> No disponible
                                    </button>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>
<?php endif; ?>

<section
    class="content-panel"
    data-my-tests-panel
    data-my-tests-status-url="<?= e(app_url('my-tests/status')) ?>"
    data-assigned-tests-finalized="<?= $assignedTestsFinalized ? '1' : '0' ?>"
>
    <div
        class="alert alert-success my-tests-finalized-alert d-flex align-items-center gap-2 <?= $assignedTestsFinalized ? '' : 'd-none' ?>"
        role="status"
        data-inline-alert
        data-assigned-tests-finalized-message
    >
        <i class="bi bi-check-circle-fill"></i>
        <strong>Has finalizado los Test asignados, ahora podrás salir de plataforma.</strong>
    </div>
    <div class="table-responsive">
        <table class="table align-middle app-table app-data-table" data-export-title="Mis evaluaciones" data-export-excel="false" data-export-pdf="false">
            <thead>
                <tr>
                    <th>Evaluacion</th>
                    <th>Items</th>
                    <th>Duracion</th>
                    <th>Disponibilidad</th>
                    <th>Estado</th>
                    <th class="text-end no-sort no-export">Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($sessions as $session): ?>
                    <?php
                    $statusKey = (string) $session['status'];
                    $answersCount = max(0, (int) ($session['answers_count'] ?? 0));
                    $hasAnswers = $answersCount > 0;
                    $statusLabel = $hasAnswers ? 'Completada: ' . $answersCount . ' respuestas enviadas' : ($statusLabels[$statusKey] ?? labelize($statusKey));
                    ?>
                    <?php $blockedByAutoStart = $pendingAutoStartSessionId > 0 && (int) ($session['id'] ?? 0) !== $pendingAutoStartSessionId && !in_array((string) ($session['status'] ?? ''), ['completed', 'expired', 'cancelled'], true); ?>
                    <tr>
                        <td>
                            <div class="fw-semibold"><?= e($session['instrument_name']) ?></div>
                            <?php if ((int) ($session['auto_start_enabled'] ?? 0) === 1): ?>
                                <div class="text-muted small">Inicio automatico · orden <?= (int) ($session['auto_start_order'] ?? 100) ?></div>
                            <?php endif; ?>
                        </td>
                        <td><?= (int) $session['items_count'] ?></td>
                        <td><?= (int) $session['duration_minutes'] > 0 ? (int) $session['duration_minutes'] . ' min' : 'Sin limite' ?></td>
                        <?php
                        $availability = $session['process_availability'] ?? ['allowed' => true, 'label' => 'Disponible'];
                        $availabilityReason = (string) ($availability['reason'] ?? '');
                        $remainingSeconds = $availability['remaining_seconds'] ?? null;
                        $startsInSeconds = $availability['starts_in_seconds'] ?? null;
                        $sessionIsOpen = in_array((string) $session['status'], ['assigned', 'in_progress'], true);
                        $timerMode = '';
                        if ($sessionIsOpen && $availabilityReason === 'not_started' && $startsInSeconds !== null) {
                            $timerMode = 'starts';
                        } elseif ($sessionIsOpen && !empty($availability['allowed']) && $remainingSeconds !== null) {
                            $timerMode = 'remaining';
                        }
                        $availabilityLabel = $sessionIsOpen
                            ? (string) ($availability['label'] ?? 'Disponible')
                            : ($statusLabel === 'Expirada' ? 'Plazo finalizado' : $statusLabel);
                        ?>
                        <td>
                            <span
                                class="badge <?= !empty($availability['allowed']) ? 'text-bg-info' : 'text-bg-secondary' ?>"
                                data-process-availability
                                data-mode="<?= e($timerMode) ?>"
                                data-static-label="<?= e($availabilityLabel) ?>"
                                data-remaining-seconds="<?= $remainingSeconds !== null ? (int) $remainingSeconds : '' ?>"
                                data-starts-in-seconds="<?= $startsInSeconds !== null ? (int) $startsInSeconds : '' ?>"
                            >
                                <span data-process-availability-label><?= e($availabilityLabel) ?></span>
                            </span>
                            <?php if (!empty($session['process_name'])): ?>
                                <div class="text-muted small mt-1"><?= e((string) $session['process_name']) ?></div>
                            <?php endif; ?>
                        </td>
                        <td><span class="badge <?= $hasAnswers ? 'text-bg-success' : ($statusKey === 'in_progress' ? 'text-bg-warning' : (in_array($statusKey, ['cancelled', 'expired'], true) ? 'text-bg-secondary' : 'text-bg-primary')) ?>"><?= e($statusLabel) ?></span></td>
                        <td class="text-end">
                            <?php if (in_array($session['status'], ['completed', 'expired'], true)): ?>
                                <span class="text-muted small">Resultado no disponible para usuarios</span>
                            <?php elseif ($session['status'] === 'cancelled'): ?>
                                <span class="text-muted small">No disponible</span>
                            <?php elseif ($blockedByAutoStart): ?>
                                <button
                                    class="btn btn-sm btn-outline-secondary"
                                    type="button"
                                    disabled
                                    title="Debes continuar con la evaluacion <?= e((string) ($pendingAutoStartSession['instrument_name'] ?? 'obligatoria')) ?> antes de responder otras evaluaciones."
                                >
                                    <i class="bi bi-lock me-1"></i> Bloqueada
                                </button>
                                <div class="text-muted small mt-1">Continua primero la evaluacion obligatoria.</div>
                            <?php elseif (empty($availability['allowed'])): ?>
                                <button class="btn btn-sm btn-outline-secondary" type="button" disabled title="<?= e((string) ($availability['message'] ?? $availability['label'] ?? 'No disponible')) ?>">
                                    <i class="bi bi-lock me-1"></i> No disponible
                                </button>
                            <?php else: ?>
                                <a
                                    class="btn btn-sm btn-primary"
                                    href="<?= e(route_url('test-session.take', (int) $session['id'])) ?>"
                                    data-test-entry-confirm
                                    data-test-name="<?= e($session['instrument_name']) ?>"
                                    data-test-instructions-template="test-entry-instructions-<?= (int) $session['id'] ?>"
                                    data-test-activity-tracking="<?= (int) ($session['track_activity_enabled'] ?? 0) === 1 ? '1' : '0' ?>"
                                    data-test-supervised-mode="<?= (int) ($session['supervised_mode_enabled'] ?? 0) === 1 ? '1' : '0' ?>"
                                    data-test-entry-title="<?= e($evaluationMessages['entry_confirm_title']) ?>"
                                    data-test-entry-message="<?= e($evaluationMessages['entry_confirm_message']) ?>"
                                    data-test-entry-confirm-button="<?= e($evaluationMessages['entry_confirm_button']) ?>"
                                    data-test-entry-cancel-button="<?= e($evaluationMessages['entry_cancel_button']) ?>"
                                    data-test-activity-notice="<?= e($evaluationMessages['activity_tracking_notice']) ?>"
                                    data-test-activity-disabled-notice="<?= e($evaluationMessages['activity_tracking_disabled_notice']) ?>"
                                ><i class="bi bi-play-circle me-1"></i> Responder</a>
                                <template id="test-entry-instructions-<?= (int) $session['id'] ?>">
                                    <?= test_entry_instructions_html((string) ($session['instructions'] ?? '')) ?>
                                </template>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php foreach ($evaluationAssignments as $assignment): ?>
                    <?php
                    $assignmentStatus = (string) ($assignment['status'] ?? 'assigned');
                    $assignmentAnswersCount = max(0, (int) ($assignment['answers_count'] ?? 0));
                    $assignmentHasAnswers = $assignmentAnswersCount > 0;
                    $assignmentStatusLabel = $assignmentHasAnswers ? 'Completada: ' . $assignmentAnswersCount . ' respuestas enviadas' : ($statusLabels[$assignmentStatus] ?? labelize($assignmentStatus));
                    $canAnswerAssignment = in_array($assignmentStatus, ['assigned', 'in_progress'], true);
                    $assignmentAvailability = is_array($assignment['process_availability'] ?? null) ? $assignment['process_availability'] : ['allowed' => true, 'label' => 'Disponible'];
                    $assignmentCanAnswer = $canAnswerAssignment && !empty($assignmentAvailability['allowed']);
                    ?>
                    <tr>
                        <td>
                            <div class="fw-semibold"><?= e((string) ($assignment['form_title'] ?? 'Evaluación')) ?></div>
                            <div class="text-muted small"><?= e(($assignment['form_type'] ?? 'assessment') === 'survey' ? 'Encuesta' : 'Evaluación con nota') ?></div>
                        </td>
                        <td><?= (int) ($assignment['items_count'] ?? 0) ?></td>
                        <td><?= (int) ($assignment['duration_minutes'] ?? 0) > 0 ? (int) $assignment['duration_minutes'] . ' min' : 'Sin límite' ?></td>
                        <td>
                            <span class="badge <?= !empty($assignmentAvailability['allowed']) ? 'text-bg-info' : 'text-bg-secondary' ?>"><?= e((string) ($assignmentAvailability['label'] ?? 'Según proceso')) ?></span>
                            <?php if (!empty($assignment['process_name'])): ?>
                                <div class="text-muted small mt-1"><?= e((string) $assignment['process_name']) ?></div>
                            <?php endif; ?>
                        </td>
                        <td><span class="badge <?= $assignmentHasAnswers ? 'text-bg-success' : ($assignmentStatus === 'in_progress' ? 'text-bg-warning' : (in_array($assignmentStatus, ['expired', 'cancelled'], true) ? 'text-bg-secondary' : 'text-bg-primary')) ?>"><?= e($assignmentStatusLabel) ?></span></td>
                        <td class="text-end">
                            <?php if ($assignmentCanAnswer): ?>
                                <a class="btn btn-sm btn-primary" href="<?= e(route_url('evaluation-surveys.form.take', (int) $assignment['form_id']) . '?process_id=' . (int) ($assignment['process_id'] ?? 0)) ?>"><i class="bi bi-play-circle me-1"></i> Responder</a>
                            <?php elseif ($canAnswerAssignment && empty($assignmentAvailability['allowed'])): ?>
                                <button class="btn btn-sm btn-outline-secondary" type="button" disabled title="<?= e((string) ($assignmentAvailability['message'] ?? $assignmentAvailability['label'] ?? 'No disponible')) ?>"><i class="bi bi-lock me-1"></i> No disponible</button>
                            <?php elseif ($assignmentStatus === 'completed'): ?>
                                <span class="text-muted small">Resultado no disponible para usuarios</span>
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

<?php if ($interviewAppointments): ?>
    <script src="<?= e(url('assets/js/interviews/interviews.js')) ?>"></script>
<?php endif; ?>
