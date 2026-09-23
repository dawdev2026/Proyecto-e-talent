<?php
$evaluationMessages = array_merge(TestSettingsModel::DEFAULTS, $evaluationMessages ?? []);
$pendingAutoStartSession = is_array($pendingAutoStartSession ?? null) ? $pendingAutoStartSession : null;
$pendingAutoStartSessionId = $pendingAutoStartSession ? (int) ($pendingAutoStartSession['id'] ?? 0) : 0;
$assignedActivitiesCompleted = (bool) ($assignedActivitiesCompleted ?? false);
$interviewAppointments = is_array($interviewAppointments ?? null) ? $interviewAppointments : [];
$evaluationAssignments = is_array($evaluationAssignments ?? null) ? $evaluationAssignments : [];
$componentValidationPassed = (bool) ($componentValidationPassed ?? false);
$facialEnrollmentActive = (bool) ($facialEnrollmentActive ?? false);
$activityPrerequisitesReady = static function (array $activity) use ($componentValidationPassed, $facialEnrollmentActive): bool {
    return ProcessPrerequisiteService::isReady($activity, $componentValidationPassed, $facialEnrollmentActive);
};
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

$renderProcessPrerequisites = static function (array $activity) use ($componentValidationPassed, $facialEnrollmentActive): void {
    $requirements = ProcessPrerequisiteService::requirements($activity);
    $componentRequired = $requirements['component_required'];
    $facialRequired = $requirements['facial_required'];
    if (!$componentRequired && !$facialRequired) return;
    ?>
    <div class="d-flex flex-wrap gap-2 mt-2">
        <?php if ($componentRequired): ?>
            <?php if ($componentValidationPassed): ?>
                <span class="badge text-bg-success"><i class="bi bi-check-circle me-1" aria-hidden="true"></i>Última revisión registrada: aprobada</span>
            <?php else: ?>
                <span class="badge text-bg-warning"><i class="bi bi-pc-display me-1" aria-hidden="true"></i>Revisión de componentes pendiente</span>
            <?php endif; ?>
            <a class="badge text-bg-light border text-decoration-none" href="<?= e(route_url('component-validation.review')) ?>"><i class="bi bi-arrow-repeat me-1" aria-hidden="true"></i>Revisar este equipo</a>
        <?php endif; ?>
        <?php if ($facialRequired): ?>
            <?php if ($facialEnrollmentActive): ?>
                <span class="badge text-bg-success"><i class="bi bi-person-check me-1" aria-hidden="true"></i>Usuario enrolado</span>
            <?php else: ?>
                <a class="badge text-bg-warning text-decoration-none" href="<?= e(route_url('facial-recognition.enroll')) ?>"><i class="bi bi-person-plus me-1" aria-hidden="true"></i>Enrolar mi identidad</a>
            <?php endif; ?>
        <?php endif; ?>
    </div>
    <?php
};
?>
<section class="page-header">
    <div>
        <p class="text-uppercase text-primary fw-bold small mb-1">Evaluaciones</p>
        <h1 class="fw-bold mb-1">Evaluaciones Pendientes a Realizar</h1>
        <p class="text-muted mb-0">Pruebas asignadas que están disponibles o en curso. <?= status_help_button('Estados de la actividad', "• Asignada / Pendiente: fue asignada y aún no se ha abierto.\n• En curso: hay un intento abierto; puede tener cero respuestas y no representa por sí solo avance respondido.\n• Con respuestas: existe al menos una respuesta no vacía guardada; es una métrica, no un estado del intento.\n• Completada: se envió explícitamente, incluso si no contiene respuestas.\n• Expirada: venció el plazo sin entrega; se informa aparte y no cuenta como completada.\n• Cancelada: la asignación se retiró y se excluye de los totales activos.") ?></p>
    </div>
    <div class="server-clock-card" data-server-clock="<?= $serverNow ?>" data-server-offset="<?= (int) date('Z', $serverNow) ?>">
        <span><i class="bi bi-clock-history"></i> Hora servidor</span>
        <strong data-server-clock-display><?= e(date('d/m/Y H:i:s', $serverNow)) ?></strong>
    </div>
</section>

<?php
$pendingPrerequisites = [];
$hasComponentRequirement = false;
$hasFacialRequirement = false;
foreach (array_merge($sessions, $evaluationAssignments) as $assignedActivity) {
    $requirements = ProcessPrerequisiteService::requirements($assignedActivity);
    $hasComponentRequirement = $hasComponentRequirement || ($requirements['component_required'] && !$componentValidationPassed);
    $hasFacialRequirement = $hasFacialRequirement || ($requirements['facial_required'] && !$facialEnrollmentActive);
}
if ($hasComponentRequirement) {
    $pendingPrerequisites[] = [
        'title' => 'Revisar y verificar los componentes',
        'description' => 'Comprueba que este equipo cumple las condiciones técnicas requeridas por el proceso.',
        'route' => route_url('component-validation.review'),
        'label' => 'Revisar componentes',
        'icon' => 'bi-pc-display',
    ];
}
if ($hasFacialRequirement) {
    $pendingPrerequisites[] = [
        'title' => 'Enrolar mi identidad facial',
        'description' => 'Registra tu identidad facial para que el proceso pueda verificarte antes de comenzar.',
        'route' => route_url('facial-recognition.enroll'),
        'label' => 'Enrolar mi identidad',
        'icon' => 'bi-person-plus',
    ];
}
?>
<?php if ($pendingPrerequisites): ?>
    <section class="card content-panel border-warning mb-4" role="alert" aria-labelledby="pending-prerequisites-title">
        <div class="d-flex flex-wrap align-items-start gap-3">
            <div class="rounded-circle bg-warning-subtle text-warning-emphasis p-3" aria-hidden="true"><i class="bi bi-shield-exclamation fs-4"></i></div>
            <div class="flex-grow-1">
                <p class="text-uppercase text-warning-emphasis fw-bold small mb-1">Antes de responder</p>
                <h2 id="pending-prerequisites-title" class="h4 fw-bold mb-2">Completa estos pasos para acceder a tus evaluaciones</h2>
                <p class="text-muted mb-3">El proceso seleccionó requisitos de seguridad para estas actividades. Cuando termines, podrás responder la evaluación y el test asignados.</p>
                <div class="row g-3">
                    <?php foreach ($pendingPrerequisites as $index => $prerequisite): ?>
                        <div class="col-12 col-lg-6">
                            <div class="border rounded-3 p-3 h-100 bg-light-subtle">
                                <div class="d-flex gap-2 align-items-start">
                                    <span class="badge text-bg-warning rounded-pill"><?= (int) $index + 1 ?></span>
                                    <div>
                                        <h3 class="h6 fw-bold mb-1"><i class="<?= e($prerequisite['icon']) ?> me-1" aria-hidden="true"></i><?= e($prerequisite['title']) ?></h3>
                                        <p class="text-muted small mb-2"><?= e($prerequisite['description']) ?></p>
                                        <a class="btn btn-sm btn-outline-primary" href="<?= e($prerequisite['route']) ?>"><i class="bi bi-arrow-right me-1" aria-hidden="true"></i><?= e($prerequisite['label']) ?></a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </section>
<?php endif; ?>

<?php
$pendingAutoStartCanEnter = $pendingAutoStartSession
    && ((string) ($pendingAutoStartSession['status'] ?? '') === 'in_progress' || $activityPrerequisitesReady($pendingAutoStartSession));
?>
<?php if ($pendingAutoStartSession): ?>
    <?php if ($pendingAutoStartCanEnter): ?>
    <div
        data-auto-start-required
        data-test-name="<?= e((string) ($pendingAutoStartSession['instrument_name'] ?? 'Evaluacion')) ?>"
        data-test-url="<?= e(route_url('test-session.take', $pendingAutoStartSessionId)) ?>"
        data-test-entry-target="#auto-start-test-entry-<?= (int) $pendingAutoStartSessionId ?>"
        hidden
    ></div>
    <?php endif; ?>
    <section class="card content-panel">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
            <div>
                <p class="text-uppercase text-primary fw-bold small mb-1">Evaluacion obligatoria</p>
                <h2 class="h5 fw-bold mb-1"><?= e((string) ($pendingAutoStartSession['instrument_name'] ?? 'Evaluacion')) ?></h2>
                <p class="text-muted mb-0">Debes continuar esta evaluacion antes de responder las demas.</p>
                <?php $renderProcessPrerequisites($pendingAutoStartSession); ?>
            </div>
            <?php if ($pendingAutoStartCanEnter): ?>
            <a
                id="auto-start-test-entry-<?= (int) $pendingAutoStartSessionId ?>"
                class="btn btn-primary"
                href="<?= e(route_url('test-session.take', $pendingAutoStartSessionId)) ?>"
                data-test-entry-confirm
                data-assessment-sequenced-entry
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
            <?php else: ?>
            <button class="btn btn-outline-secondary" type="button" disabled aria-describedby="auto-start-prerequisite-note-<?= (int) $pendingAutoStartSessionId ?>">
                <i class="bi bi-lock me-1"></i> Continuar evaluación
            </button>
            <div id="auto-start-prerequisite-note-<?= (int) $pendingAutoStartSessionId ?>" class="text-muted small">Completa los requisitos indicados antes de iniciar esta evaluación.</div>
            <?php endif; ?>
            <template id="test-entry-instructions-auto-<?= (int) $pendingAutoStartSessionId ?>">
                <?= test_entry_instructions_html((string) ($pendingAutoStartSession['instructions'] ?? '')) ?>
            </template>
        </div>
    </section>
<?php endif; ?>

<?php if ($interviewAppointments): ?>
    <section class="card content-panel">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-3">
            <div>
                <p class="text-uppercase text-primary fw-bold small mb-1">Entrevistas seleccion</p>
                <h2 class="h5 fw-bold mb-1">Mis entrevistas</h2>
                <p class="text-muted mb-0">Procesos de entrevista en los que estas inscrito.</p>
            </div>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle app-table app-data-table" data-export-title="Mis entrevistas" data-export-excel="false" data-export-pdf="false" data-page-length="5">
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
    class="card content-panel"
    data-my-tests-panel
    data-my-tests-status-url="<?= e(app_url('my-tests/status')) ?>"
    data-assigned-activities-completed="<?= $assignedActivitiesCompleted ? '1' : '0' ?>"
>
    <div
        class="alert alert-success my-tests-finalized-alert d-flex align-items-center gap-2 <?= $assignedActivitiesCompleted ? '' : 'd-none' ?>"
        role="status"
        data-inline-alert
        data-assigned-activities-completed-message
    >
        <i class="bi bi-check-circle-fill"></i>
        <strong>Has completado todas las evaluaciones y tests asignados. Ahora puedes salir de la plataforma.</strong>
    </div>
        <table class="table table-hover align-middle app-table app-data-table" data-export-title="Mis evaluaciones" data-export-excel="false" data-export-pdf="false">
            <caption class="visually-hidden">Evaluaciones y pruebas pendientes a realizar o con plazo vencido.</caption>
            <thead>
                <tr>
                    <th scope="col">Evaluacion</th>
                    <th scope="col">Items</th>
                    <th scope="col">Duracion</th>
                    <th scope="col">Disponibilidad</th>
                    <th scope="col">Estado</th>
                    <th scope="col" class="text-end no-sort no-export">Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($sessions as $session): ?>
                    <?php
                    $statusKey = (string) $session['status'];
                    $answersCount = max(0, (int) ($session['answers_count'] ?? 0));
                    $hasAnswers = $answersCount > 0;
                    $statusLabel = $statusKey === 'completed'
                        ? 'Completada: ' . $answersCount . ' respuestas guardadas'
                        : ($statusKey === 'expired' && $hasAnswers
                            ? 'Expirada: ' . $answersCount . ' respuestas guardadas'
                            : ($statusLabels[$statusKey] ?? labelize($statusKey)));
                    ?>
                    <?php $blockedByAutoStart = $pendingAutoStartSessionId > 0 && (int) ($session['id'] ?? 0) !== $pendingAutoStartSessionId && !in_array((string) ($session['status'] ?? ''), ['completed', 'expired', 'cancelled'], true); ?>
                    <?php $prerequisitesReady = (string) ($session['status'] ?? '') === 'in_progress' || $activityPrerequisitesReady($session); ?>
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
                            : ($statusKey === 'expired' ? 'Plazo finalizado' : $statusLabel);
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
                            <?php $renderProcessPrerequisites($session); ?>
                        </td>
                        <td><span class="badge <?= $statusKey === 'completed' ? 'text-bg-success' : ($statusKey === 'in_progress' ? 'text-bg-warning' : (in_array($statusKey, ['cancelled', 'expired'], true) ? 'text-bg-secondary' : 'text-bg-primary')) ?>"><?= e($statusLabel) ?></span></td>
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
                            <?php elseif (!$prerequisitesReady): ?>
                                <button class="btn btn-sm btn-outline-secondary" type="button" disabled aria-describedby="test-prerequisite-note-<?= (int) $session['id'] ?>">
                                    <i class="bi bi-lock me-1"></i> Responder
                                </button>
                                <div id="test-prerequisite-note-<?= (int) $session['id'] ?>" class="text-muted small mt-1">Completa los requisitos indicados antes de responder.</div>
                            <?php else: ?>
                                <a
                                    class="btn btn-sm btn-primary"
                                    href="<?= e(route_url('test-session.take', (int) $session['id'])) ?>"
                                    data-test-entry-confirm
                                    data-assessment-sequenced-entry
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
                                <?php if ($statusKey === 'in_progress' && !$activityPrerequisitesReady($session)): ?>
                                    <div class="text-muted small mt-1">Actividad ya iniciada: puedes continuarla.</div>
                                <?php endif; ?>
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
                    $assignmentStatusLabel = $assignmentStatus === 'completed'
                        ? 'Completada: ' . $assignmentAnswersCount . ' respuestas guardadas'
                        : ($assignmentStatus === 'expired' && $assignmentHasAnswers
                            ? 'Expirada: ' . $assignmentAnswersCount . ' respuestas guardadas'
                            : ($statusLabels[$assignmentStatus] ?? labelize($assignmentStatus)));
                    $canAnswerAssignment = in_array($assignmentStatus, ['assigned', 'in_progress'], true);
                    $assignmentAvailability = is_array($assignment['process_availability'] ?? null) ? $assignment['process_availability'] : ['allowed' => true, 'label' => 'Disponible'];
                    $assignmentCanAnswer = $canAnswerAssignment && !empty($assignmentAvailability['allowed']);
                    $assignmentPrerequisitesReady = $assignmentStatus === 'in_progress' || $activityPrerequisitesReady($assignment);
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
                            <?php $renderProcessPrerequisites($assignment); ?>
                        </td>
                        <td><span class="badge <?= $assignmentStatus === 'completed' ? 'text-bg-success' : ($assignmentStatus === 'in_progress' ? 'text-bg-warning' : (in_array($assignmentStatus, ['expired', 'cancelled'], true) ? 'text-bg-secondary' : 'text-bg-primary')) ?>"><?= e($assignmentStatusLabel) ?></span></td>
                        <td class="text-end">
                            <?php if ($assignmentCanAnswer && $assignmentPrerequisitesReady): ?>
                                <a class="btn btn-sm btn-primary" href="<?= e(route_url('evaluation-surveys.form.take', (int) $assignment['form_id']) . '?process_id=' . (int) ($assignment['process_id'] ?? 0)) ?>"><i class="bi bi-play-circle me-1"></i> Responder</a>
                                <?php if ($assignmentStatus === 'in_progress' && !$activityPrerequisitesReady($assignment)): ?>
                                    <div class="text-muted small mt-1">Actividad ya iniciada: puedes continuarla.</div>
                                <?php endif; ?>
                            <?php elseif ($assignmentCanAnswer && !$assignmentPrerequisitesReady): ?>
                                <button class="btn btn-sm btn-outline-secondary" type="button" disabled aria-describedby="evaluation-prerequisite-note-<?= (int) $assignment['process_id'] ?>-<?= (int) $assignment['form_id'] ?>"><i class="bi bi-lock me-1"></i> Responder</button>
                                <div id="evaluation-prerequisite-note-<?= (int) $assignment['process_id'] ?>-<?= (int) $assignment['form_id'] ?>" class="text-muted small mt-1">Completa los requisitos indicados antes de responder.</div>
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
</section>

<?php if ($interviewAppointments): ?>
    <script src="<?= e(url('assets/js/interviews/interviews.js')) ?>"></script>
<?php endif; ?>
