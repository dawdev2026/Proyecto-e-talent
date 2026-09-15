<?php
$isAssessment = ($form['form_type'] ?? '') === 'assessment';
$showCorrection = $isAssessment && (int) ($form['show_correction_to_user'] ?? $attempt['show_correction_to_user'] ?? 0) === 1;
$passed = $attempt['passed'] ?? null;
$returnUrl = route_url('evaluation-surveys.assessments');
$isDrawer = (string) ($_GET['drawer'] ?? '') === '1' || (string) ($_GET['partial'] ?? '') === 'drawer';
$statusLabel = [
    'completed' => 'Completado',
    'expired' => 'Expirado',
    'in_progress' => 'En progreso',
][(string) ($attempt['status'] ?? '')] ?? (string) ($attempt['status'] ?? '');
$resultDisplayMode = (string) ($form['result_display_mode'] ?? $attempt['result_display_mode'] ?? 'best_only');
$resultAttemptDetails = array_values($resultAttemptDetails ?? []);
$controlEvents = array_values($controlEvents ?? []);
$mediaEvidence = array_values($mediaEvidence ?? []);
$audioVisualRisks = array_values($audioVisualRisks ?? []);
$audioVisualRisks = array_values(array_filter($audioVisualRisks, static fn(array $risk): bool => (string) ($risk['event_type'] ?? '') !== 'multiple_voice_possible'));
$screenCaptures = array_values($screenCaptures ?? []);
$resultsVisible = (bool) ($resultsVisible ?? true);
$controlEventLabels = [
    'audio_visual_screen_capture_completed' => 'Captura de pantalla guardada', 'audio_visual_risk' => 'Riesgo audiovisual registrado',
    'audio_visual_upload_completed' => 'Evidencia audiovisual guardada', 'audio_visual_upload_failed' => 'Fallo al guardar evidencia audiovisual',
    'attempt_opened' => 'Intento abierto', 'supervised_started' => 'Rendición supervisada iniciada',
    'fullscreen_entered' => 'Pantalla completa activada', 'fullscreen_exited' => 'Salida de pantalla completa',
    'fullscreen_denied' => 'Pantalla completa rechazada', 'fullscreen_unavailable' => 'Pantalla completa no disponible',
    'tab_hidden' => 'Pestaña oculta', 'tab_visible' => 'Pestaña visible', 'window_blurred' => 'Ventana sin foco',
    'window_focused' => 'Ventana con foco', 'answer_changed' => 'Respuesta modificada', 'draft_saved' => 'Borrador guardado',
    'attempt_completed' => 'Intento completado', 'attempt_expired' => 'Intento expirado', 'copy_blocked' => 'Copia bloqueada',
    'cut_blocked' => 'Corte bloqueado', 'paste_blocked' => 'Pegado bloqueado', 'print_blocked' => 'Impresión bloqueada',
    'context_menu_blocked' => 'Menú contextual bloqueado', 'drag_blocked' => 'Arrastre bloqueado',
    'suspicious_key_printscreen' => 'Tecla de captura detectada', 'suspicious_key_save' => 'Atajo de guardado detectado',
    'suspicious_key_copy' => 'Atajo de copia detectado', 'suspicious_key_devtools' => 'Atajo de herramientas detectado',
    'multiple_voice_possible' => 'Posible multiplicidad de voces', 'voice_analysis_unavailable' => 'Detección de voces no disponible',
];
$severityLabels = ['info' => 'Información', 'attention' => 'Atención', 'risk' => 'Riesgo'];
$activityMetadataLabel = static function ($metadata): string {
    if (is_string($metadata)) $metadata = json_decode($metadata, true);
    if (!is_array($metadata) || !$metadata) return '';
    $labels = ['responses_count' => 'respuestas', 'question_id' => 'pregunta', 'chunk_number' => 'fragmento', 'reason' => 'motivo', 'source' => 'origen', 'capture_number' => 'captura'];
    $parts = [];
    foreach ($metadata as $key => $value) {
        if (is_array($value)) $value = implode(', ', array_map('strval', $value));
        $value = trim((string) $value);
        if ($value !== '') $parts[] = ($labels[(string) $key] ?? (string) $key) . ': ' . $value;
    }
    return implode(' · ', $parts);
};
$isAttentionEvent = static function (string $eventType): bool {
    return in_array($eventType, ['multiple_voice_possible', 'tab_hidden', 'window_blurred', 'inactive_detected', 'fullscreen_exited', 'fullscreen_denied', 'fullscreen_failed', 'suspicious_key_printscreen', 'suspicious_key_print', 'suspicious_key_save', 'suspicious_key_copy', 'suspicious_key_devtools', 'copy_blocked', 'cut_blocked', 'paste_blocked', 'print_blocked', 'context_menu_blocked', 'drag_blocked'], true);
};
$attemptStatusLabel = static function (array $attemptRow): string {
    if (($attemptRow['passed'] ?? null) === null) {
        return 'Sin umbral';
    }

    return (int) $attemptRow['passed'] === 1 ? 'Aprobada' : 'Reprobada';
};
$normalizeChoiceValue = static function (string $value): string {
    $value = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    return trim((string) (preg_replace('/\s+/u', ' ', $value) ?? $value));
};
$renderResponses = static function (array $questionSet, array $answerSet) use ($showCorrection, $normalizeChoiceValue): void {
    ?>
    <div class="d-flex flex-column gap-3">
        <?php foreach ($questionSet as $index => $question): ?>
            <?php
            $questionId = (int) $question['id'];
            $answerRow = $answerSet[$questionId] ?? [];
            $answer = $answerRow['answer_value'] ?? '';
            $answerScore = $answerRow['score_value'] ?? null;
            $questionType = (string) ($question['question_type'] ?? '');
            $selectedValues = [];
            if ($questionType === 'multiple_choice') {
                $normalizedAnswer = $normalizeChoiceValue((string) $answer);
                foreach (($question['options'] ?? []) as $option) {
                    $optionValue = (string) ($option['option_value'] ?? '');
                    $optionLabel = (string) ($option['option_label'] ?? $optionValue);
                    if ($normalizedAnswer === $normalizeChoiceValue($optionValue)
                        || $normalizedAnswer === $normalizeChoiceValue($optionLabel)) {
                        // A single option can contain commas; compare it as a whole.
                        $selectedValues = [trim((string) $answer)];
                        break;
                    }
                }
                if (!$selectedValues) {
                    $decodedAnswer = json_decode((string) $answer, true);
                    if (is_array($decodedAnswer)) {
                        $selectedValues = array_filter(array_map('strval', $decodedAnswer), static fn(string $value): bool => trim($value) !== '');
                    } else {
                        // Compatibilidad con respuestas múltiples antiguas almacenadas como CSV.
                        $selectedValues = array_filter(array_map('trim', explode(',', (string) $answer)), static fn(string $value): bool => $value !== '');
                    }
                }
            } elseif (trim((string) $answer) !== '') {
                // Una respuesta única puede contener comas; nunca debe separarse por ese carácter.
                $selectedValues = [trim((string) $answer)];
            }
            $labels = [];
            $correctLabels = [];
            if ($questionType === 'matrix') {
                $matrixValues = json_decode((string) $answer, true);
                $matrixValues = is_array($matrixValues) ? array_map('strval', $matrixValues) : [];
                foreach (($question['options'] ?? []) as $option) {
                    $optionValue = (string) ($option['option_value'] ?? '');
                    if (($matrixValues[$optionValue] ?? '') !== '') {
                        $labels[] = (string) $option['option_label'] . ': ' . $matrixValues[$optionValue];
                    }
                }
            } else {
                foreach (($question['options'] ?? []) as $option) {
                    if (in_array((string) $option['option_value'], $selectedValues, true)) {
                        $labels[] = (string) $option['option_label'];
                    }
                    if ((float) ($option['score_value'] ?? 0) > 0) {
                        $correctLabels[] = (string) $option['option_label'];
                    }
                }
            }
            $hasAutomaticCorrection = $showCorrection && $correctLabels !== [] && !in_array($questionType, ['text', 'likert', 'nps', 'rating', 'matrix'], true);
            $isCorrectAnswer = $hasAutomaticCorrection && (float) ($answerScore ?? 0) > 0;
            ?>
            <article class="test-question-card">
                <div class="test-question-head">
                    <span class="test-question-number"><?= str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT) ?></span>
                    <div class="test-question-content"><?= evaluation_rich_text_html((string) $question['question_text']) ?></div>
                </div>
                <?php if ($questionType === 'matrix'): ?>
                    <?php $matrix = json_decode((string) $answer, true); $matrix = is_array($matrix) ? array_map('strval', $matrix) : []; ?>
                    <div class="test-choice-grid">
                        <?php foreach (($question['options'] ?? []) as $option): ?>
                            <?php $optionKey = (string) ($option['option_value'] ?? ''); $matrixValue = (string) ($matrix[$optionKey] ?? ''); ?>
                            <div class="test-choice-card <?= $matrixValue !== '' ? 'is-selected' : '' ?>"><span><?= e((string) ($option['option_label'] ?? $optionKey)) ?></span><strong><?= e($matrixValue !== '' ? $matrixValue : 'Sin respuesta') ?></strong></div>
                        <?php endforeach; ?>
                    </div>
                <?php elseif (!empty($question['options'])): ?>
                    <?php $selectedSet = array_fill_keys(array_map($normalizeChoiceValue, $selectedValues), true); $choiceIndex = 0; $hasVisibleSelection = false; ?>
                    <div class="test-choice-grid">
                        <?php foreach (($question['options'] ?? []) as $option): ?>
                            <?php $optionValue = (string) ($option['option_value'] ?? ''); $optionLabel = (string) ($option['option_label'] ?? $optionValue); $isSelected = isset($selectedSet[$normalizeChoiceValue($optionValue)]) || isset($selectedSet[$normalizeChoiceValue($optionLabel)]); $hasVisibleSelection = $hasVisibleSelection || $isSelected; ?>
                            <label class="test-choice-card <?= $isSelected ? 'is-selected' : '' ?>">
                                <input type="<?= $questionType === 'multiple_choice' ? 'checkbox' : 'radio' ?>" disabled <?= $isSelected ? 'checked' : '' ?>>
                                <span class="test-choice-mark"><?= e(chr(65 + ($choiceIndex++ % 26))) ?></span>
                                <span><?= e((string) ($option['option_label'] ?? $optionValue)) ?></span>
                            </label>
                        <?php endforeach; ?>
                        <?php if (!$selectedValues || !$hasVisibleSelection): ?><div class="answered-free-response"><span class="text-muted small">Respuesta registrada</span><strong><?= e($selectedValues ? implode(', ', $selectedValues) : 'Sin respuesta') ?></strong></div><?php endif; ?>
                    </div>
                <?php else: ?>
                    <div class="answered-free-response"><span class="text-muted small">Respuesta</span><strong><?= e($labels ? implode(', ', $labels) : ((string) $answer !== '' ? (string) $answer : 'Sin respuesta')) ?></strong></div>
                <?php endif; ?>
                <?php if ($hasAutomaticCorrection): ?>
                    <div class="mt-2"><span class="badge <?= $isCorrectAnswer ? 'text-bg-success' : 'text-bg-danger' ?>"><?= $isCorrectAnswer ? 'Correcta' : 'Incorrecta' ?></span></div>
                <?php endif; ?>
                <?php if ($hasAutomaticCorrection && !$isCorrectAnswer): ?>
                    <div class="mt-3">
                        <span class="d-block small text-uppercase text-muted">Respuesta correcta</span>
                        <strong><?= e(implode(', ', $correctLabels)) ?></strong>
                    </div>
                <?php endif; ?>
            </article>
        <?php endforeach; ?>
    </div>
    <?php
};
?>
<?php if (!$isDrawer): ?>
    <section class="page-header" data-page-back-url="<?= e($returnUrl) ?>">
        <div>
            <p class="dashboard-kicker mb-2"><?= $isAssessment ? 'Resultado evaluación' : 'Encuesta enviada' ?></p>
            <h1 class="fw-bold mb-1"><?= e($form['title'] ?? $attempt['form_title'] ?? 'Formulario') ?></h1>
            <p class="text-muted mb-0"><?= $isAssessment ? 'Intento ' . (int) ($attempt['attempt_number'] ?? 1) . ' · ' . e($statusLabel) : e($statusLabel) ?></p>
        </div>
    </section>
<?php else: ?>
    <div class="drawer-detail-heading mb-3">
        <h2 class="h4 fw-bold mb-1"><?= e($form['title'] ?? $attempt['form_title'] ?? 'Formulario') ?></h2>
        <p class="text-muted mb-0"><?= $isAssessment ? 'Intento ' . (int) ($attempt['attempt_number'] ?? 1) . ' · ' . e($statusLabel) : e($statusLabel) ?></p>
    </div>
<?php endif; ?>

<?php if (!$resultsVisible): ?>
    <section class="content-panel evaluation-result-panel">
        <div class="alert alert-info mb-0" role="status">
            <h2 class="h5 fw-bold mb-2">Evaluación finalizada</h2>
            <p class="mb-0">Tus respuestas fueron guardadas correctamente. El resultado no está disponible para consulta del usuario según la configuración de esta evaluación.</p>
        </div>
    </section>
<?php else: ?>
<section class="content-panel evaluation-result-panel">
    <div class="row g-3">
        <?php if ($isAssessment): ?>
            <div class="col-12 col-md-6">
                <div class="stat-card h-100">
                    <span>Nota</span>
                    <strong><?= number_format((float) ($attempt['final_score'] ?? 0), 2, ',', '.') ?></strong>
                </div>
            </div>
            <div class="col-12 col-md-6">
                <div class="stat-card h-100">
                    <span>Estado</span>
                    <strong>
                        <?php if ($passed === null): ?>
                            <span class="badge text-bg-secondary">Sin umbral</span>
                        <?php elseif ((int) $passed === 1): ?>
                            <span class="badge text-bg-success">Aprobada</span>
                        <?php else: ?>
                            <span class="badge text-bg-danger">Reprobada</span>
                        <?php endif; ?>
                    </strong>
                </div>
            </div>
        <?php else: ?>
            <div class="col-12">
                <div class="stat-card">
                    <span>Encuesta</span>
                    <strong>Respuestas registradas</strong>
                    <small>Este formulario registra el resultado de manera independiente.</small>
                </div>
            </div>
        <?php endif; ?>
    </div>
</section>

<section class="content-panel evaluation-result-panel">
    <?php if ($isAssessment && $resultDisplayMode === 'collapsible_attempts' && $resultAttemptDetails): ?>
        <h2 class="h5 fw-bold mb-2">Intentos</h2>
        <p class="text-muted mb-3">El resultado oficial corresponde a la mejor nota registrada.</p>
        <div class="accordion" id="evaluationResultAttempts">
            <?php foreach ($resultAttemptDetails as $index => $detail): ?>
                <?php
                $attemptRow = $detail['attempt'] ?? [];
                $attemptId = (int) ($attemptRow['id'] ?? 0);
                $collapseId = 'evaluationAttempt' . $attemptId;
                $isOpen = $index === 0;
                ?>
                <div class="accordion-item">
                    <h3 class="accordion-header" id="<?= e($collapseId) ?>Heading">
                        <button class="accordion-button <?= $isOpen ? '' : 'collapsed' ?>" type="button" data-bs-toggle="collapse" data-bs-target="#<?= e($collapseId) ?>" aria-expanded="<?= $isOpen ? 'true' : 'false' ?>" aria-controls="<?= e($collapseId) ?>">
                            <span class="d-flex flex-column flex-md-row gap-1 gap-md-3 align-items-md-center">
                                <strong>Intento <?= (int) ($attemptRow['attempt_number'] ?? ($index + 1)) ?></strong>
                                <span>Nota <?= number_format((float) ($attemptRow['final_score'] ?? 0), 2, ',', '.') ?></span>
                                <span><?= e($attemptStatusLabel($attemptRow)) ?></span>
                            </span>
                        </button>
                    </h3>
                    <div id="<?= e($collapseId) ?>" class="accordion-collapse collapse <?= $isOpen ? 'show' : '' ?>" aria-labelledby="<?= e($collapseId) ?>Heading" data-bs-parent="#evaluationResultAttempts">
                        <div class="accordion-body">
                            <?php $renderResponses($detail['questions'] ?? [], $detail['answers'] ?? []); ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <h2 class="h5 fw-bold mb-3">Respuestas</h2>
        <?php $renderResponses($questions, $answers); ?>
    <?php endif; ?>
    <?php if ($isAssessment && $controlEvents): ?>
        <section class="mt-4 pt-4 border-top" aria-labelledby="evaluation-control-events-title">
            <p class="text-uppercase text-primary fw-bold small mb-1">Registro de actividades</p>
            <h2 id="evaluation-control-events-title" class="h5 fw-bold mb-1">Detalle de acciones</h2>
            <p class="text-muted small mb-3">Registro técnico del modo <?= e(EvaluationSurveyFormModel::CONTROL_MODES[(string) ($attempt['control_mode'] ?? 'off')] ?? 'Sin registro') ?>.</p>
            <div class="table-responsive">
                <table class="table align-middle app-table">
                    <thead><tr><th>Evento</th><th>Fecha</th><th>Detalle</th><th>IP</th></tr></thead>
                    <tbody>
                        <?php foreach ($controlEvents as $event): ?>
                            <?php $eventType = (string) ($event['event_type'] ?? ''); $metadata = $activityMetadataLabel($event['metadata'] ?? null); ?>
                            <tr class="<?= $isAttentionEvent($eventType) ? 'activity-attention-row' : '' ?>">
                                <td class="fw-semibold"><div class="activity-event-title"><span><?= e($controlEventLabels[$eventType] ?? ($eventType !== '' ? $eventType : 'Evento')) ?></span><?= $isAttentionEvent($eventType) ? '<span class="badge text-bg-warning activity-attention-badge">Atención</span>' : '' ?></div></td>
                                <td><?= e((string) ($event['created_at'] ?? '')) ?></td>
                                <td><?= $metadata !== '' ? e($metadata) : '-' ?></td>
                                <td><?= e((string) ($event['ip_address'] ?? '-')) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>
    <?php endif; ?>
    <?php if ($isAssessment && $mediaEvidence): ?>
        <section class="mt-4 pt-4 border-top" aria-labelledby="evaluation-media-title">
            <h2 id="evaluation-media-title" class="h5 fw-bold mb-1">Evidencia audiovisual</h2>
            <?php foreach ($mediaEvidence as $segment): ?>
                <div class="border rounded p-3 mb-3">
                    <h3 class="h6 fw-bold mb-1">Grabación <?= (int) ($segment['segment_number'] ?? 1) ?></h3>
                    <p class="text-muted small mb-3">Estado: <?= e((string) ($segment['status'] ?? '')) ?><?= !empty($segment['duration_seconds']) ? ' · Duración: ' . (int) $segment['duration_seconds'] . ' segundos' : '' ?></p>
                    <?php if (in_array(($segment['status'] ?? ''), ['saved', 'partial'], true)): ?>
                        <video class="app-evidence-video rounded border" controls preload="metadata" src="<?= e(route_url('evaluation-surveys.attempt.media.evidence', (int) $attempt['id']) . '?evidence_id=' . (int) $segment['id']) ?>"></video>
                    <?php elseif (!empty($segment['failure_reason'])): ?>
                        <div class="alert alert-warning mb-0"><?= e((string) $segment['failure_reason']) ?></div>
                    <?php endif; ?>
                    <?php $segmentCaptures = array_values(array_filter($screenCaptures, static fn(array $capture): bool => (int) ($capture['evidence_id'] ?? 0) === (int) ($segment['id'] ?? 0))); ?>
                    <?php if ($segmentCaptures): ?>
                        <h4 class="h6 fw-bold mt-3">Capturas de pantalla (<?= count($segmentCaptures) ?>)</h4>
                    <div class="row g-2">
                        <?php foreach ($segmentCaptures as $capture): $captureUrl = route_url('evaluation-surveys.attempt.media.screenshot-file', (int) $attempt['id']) . '?capture_id=' . (int) $capture['id']; ?><div class="col-6 col-md-4"><a href="<?= e($captureUrl) ?>" data-screen-capture-view><img class="img-fluid rounded border" loading="lazy" src="<?= e($captureUrl) ?>" alt="Captura <?= (int) $capture['capture_number'] ?>"><span class="small text-muted d-block mt-1"><?= e((string) ($capture['capture_source'] ?? '')) ?> · <?= e((string) ($capture['captured_at'] ?? '')) ?></span></a></div><?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                    <?php if (($segment['status'] ?? '') === 'partial'): ?><div class="alert alert-warning mt-2 mb-0">Evidencia parcial: solo se unieron los fragmentos disponibles hasta el primer faltante.</div><?php endif; ?>
                </div>
            <?php endforeach; ?>
            <?php if ($audioVisualRisks): ?>
                <div class="table-responsive mt-3"><table class="table table-sm align-middle mb-0"><thead><tr><th>Evento audiovisual</th><th>Severidad</th><th>Fecha</th></tr></thead><tbody>
                    <?php foreach ($audioVisualRisks as $risk): ?><tr><td><?= e($controlEventLabels[(string) ($risk['event_type'] ?? '')] ?? (string) ($risk['event_type'] ?? 'Evento audiovisual')) ?></td><td><?= e($severityLabels[(string) ($risk['severity'] ?? '')] ?? (string) ($risk['severity'] ?? '')) ?></td><td><?= e((string) ($risk['created_at'] ?? '')) ?></td></tr><?php endforeach; ?>
                </tbody></table></div>
            <?php endif; ?>
        </section>
    <?php endif; ?>
    <div class="mt-4">
        <?php if ($isDrawer): ?>
            <button class="btn btn-sm btn-outline-secondary" type="button" data-app-drawer-close>Cerrar</button>
        <?php else: ?>
            <a class="btn btn-sm btn-outline-secondary" href="<?= e($returnUrl) ?>">Volver al listado</a>
        <?php endif; ?>
    </div>
</section>
<?php endif; ?>
