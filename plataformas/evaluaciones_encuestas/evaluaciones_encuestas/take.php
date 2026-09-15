<?php
$returnUrl = route_url('my-tests');
$isDrawer = (string) ($_GET['drawer'] ?? '') === '1' || (string) ($_GET['partial'] ?? '') === 'drawer';
$qrMode = (string) ($_GET['qr'] ?? '') === '1';
$previewMode = (bool) ($previewMode ?? false);
$formInstructions = (string) ($form['instructions'] ?: $form['description'] ?: 'Responde el formulario y envia tus respuestas al finalizar.');
$formAction = route_url('evaluation-surveys.form.take', (int) $form['id']) . '?' . http_build_query(array_filter([
    'drawer' => $isDrawer ? '1' : null,
    'qr' => $qrMode ? '1' : null,
    'process_id' => !empty($processId) ? (int) $processId : null,
]));
$isSurvey = (string) ($form['form_type'] ?? '') === 'survey';
$controlModes = EvaluationSurveyFormModel::CONTROL_MODES;
$controlMode = (string) ($controlMode ?? $attempt['control_mode'] ?? $form['control_mode'] ?? 'off');
$controlMode = isset($controlModes[$controlMode]) ? $controlMode : 'off';
$audioVisualMode = $controlMode === 'supervised_audio_visual';
$evaluationMessages = array_merge(TestSettingsModel::DEFAULTS, $evaluationMessages ?? []);
$activityUrl = (string) ($activityUrl ?? '');
$mediaInitUrl = (string) ($mediaInitUrl ?? '');
$mediaChunkUrl = (string) ($mediaChunkUrl ?? '');
$mediaFinalizeUrl = (string) ($mediaFinalizeUrl ?? '');
$mediaRiskUrl = (string) ($mediaRiskUrl ?? '');
$mediaFailureUrl = (string) ($mediaFailureUrl ?? '');
$returnUrl = $previewMode ? ($backUrl ?? route_url($isSurvey ? 'evaluation-surveys.surveys' : 'evaluation-surveys.assessments')) : $returnUrl;
$formAction = $previewMode ? '#' : $formAction;
$draftAction = $previewMode ? '' : $formAction;
$displaySeconds = $remainingSeconds !== null ? (int) $remainingSeconds : max(0, (int) ($form['duration_minutes'] ?? 0) * 60);
$processAvailability = is_array($processAvailability ?? null) ? $processAvailability : [];
$processRemainingSeconds = array_key_exists('remaining_seconds', $processAvailability) && $processAvailability['remaining_seconds'] !== null
    ? max(0, (int) $processAvailability['remaining_seconds'])
    : null;
?>
<?php if ($qrMode): ?>
    <section class="landing-my-courses-main">
        <div class="landing-my-courses-hero"><div><p class="dashboard-kicker mb-2"><?= $isSurvey ? 'Encuesta' : 'Evaluación' ?></p><h1><?= e($form['title']) ?></h1><p>Responde este formulario.</p></div></div>
    </section>
<?php elseif (!$isDrawer): ?>
    <section class="page-header<?= $audioVisualMode || $controlMode === 'supervised' ? ' evaluation-supervised-page-header' : '' ?>" data-page-back-url="<?= e($returnUrl) ?>">
        <div>
            <p class="dashboard-kicker mb-2"><?= $previewMode ? 'Vista previa' : ($form['form_type'] === 'survey' ? 'Encuesta' : 'Evaluación') ?></p>
            <h1 class="fw-bold mb-0"><?= e($form['title']) ?></h1>
        </div>
    </section>
<?php else: ?>
    <div class="drawer-detail-heading mb-3">
        <p class="dashboard-kicker mb-2"><?= $form['form_type'] === 'survey' ? 'Encuesta' : 'Evaluación' ?></p>
        <h2 class="h4 fw-bold mb-0"><?= e($form['title']) ?></h2>
    </div>
<?php endif; ?>

<section class="card content-panel evaluation-take-panel">
    <?php if ($previewMode): ?>
        <div class="evaluation-take-intro mb-3">
            <h2 class="h5 fw-bold mb-2">Vista previa administrativa</h2>
            <p class="mb-0">Puedes revisar como se vera el formulario. Esta pantalla no guarda respuestas, intentos ni notas.</p>
        </div>
    <?php endif; ?>
    <form method="post" action="<?= e($formAction) ?>" class="needs-validation evaluation-take-form" novalidate data-evaluation-control-form data-draft-url="<?= e($draftAction) ?>" data-draft-action-name="evaluation_survey_action" data-draft-action-value="draft" data-evaluation-control-mode="<?= e($controlMode) ?>" data-evaluation-remaining-seconds="<?= $displaySeconds > 0 ? $displaySeconds : '' ?>" data-process-remaining-seconds="<?= $processRemainingSeconds !== null ? $processRemainingSeconds : '' ?>" data-process-expired-message="El proceso ha finalizado. Se guardarán tus respuestas y la evidencia audiovisual antes de cerrar." data-evaluation-preview="<?= $previewMode ? '1' : '0' ?>" data-incomplete-confirm-title="<?= e($evaluationMessages['incomplete_confirm_title']) ?>" data-incomplete-confirm-message="<?= e($evaluationMessages['incomplete_confirm_message']) ?>" data-incomplete-confirm-button="<?= e($evaluationMessages['incomplete_confirm_button']) ?>" data-incomplete-cancel-button="<?= e($evaluationMessages['incomplete_cancel_button']) ?>" data-expired-message="<?= e($evaluationMessages['expired_message']) ?>" data-evaluation-activity-url="<?= e($activityUrl) ?>" data-audio-visual-mode="<?= $audioVisualMode ? '1' : '0' ?>" data-media-init-url="<?= e($mediaInitUrl) ?>" data-media-chunk-url="<?= e($mediaChunkUrl) ?>" data-media-finalize-url="<?= e($mediaFinalizeUrl) ?>" data-media-risk-url="<?= e($mediaRiskUrl) ?>" data-media-failure-url="<?= e($mediaFailureUrl) ?>" data-media-screenshot-url="<?= e($audioVisualMode ? route_url('evaluation-surveys.attempt.media.screenshot', (int) $attempt['id']) : '') ?>" data-audio-visual-upload-failure-policy="<?= e((string) ($form['audio_visual_upload_failure_policy'] ?? 'continue')) ?>" data-audio-visual-interruption-policy="<?= e((string) ($form['audio_visual_interruption_policy'] ?? 'pause')) ?>" data-audio-visual-voice-policy="<?= e((string) ($form['audio_visual_voice_policy'] ?? 'warn')) ?>" data-audio-visual-permission-policy="<?= e((string) ($form['audio_visual_permission_policy'] ?? 'pause')) ?>" data-audio-visual-quality-profile="<?= e((string) ($form['audio_visual_quality_profile'] ?? 'economical')) ?>">
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
        <?php if (!$previewMode && (!$isSurvey || $audioVisualMode) && in_array($controlMode, ['supervised', 'supervised_audio_visual'], true)): ?>
            <div class="evaluation-supervised-gate" data-evaluation-supervised-gate role="dialog" aria-modal="true" aria-labelledby="evaluation-supervised-title">
                <div class="evaluation-supervised-gate-panel">
                    <div class="evaluation-supervised-gate-icon" aria-hidden="true"><i class="bi bi-shield-check"></i></div>
                    <h2 id="evaluation-supervised-title" class="h4 fw-bold mb-2">Rendición supervisada</h2>
                    <?php if ($audioVisualMode): ?>
                        <p class="text-muted mb-3">Para iniciar, debes aceptar el consentimiento y autorizar cámara, micrófono y captura de pantalla. La evidencia se guardará de forma restringida para revisión autorizada.</p>
                        <div class="alert alert-warning small text-start mb-3">Una vez iniciada la evaluación, no podrás volver atrás, recargar ni salir. Si abandonas, el intento quedará cerrado y no podrás realizarlo nuevamente.</div>
                        <div class="audio-visual-consent-box text-start mb-3" role="group" aria-labelledby="evaluationAudioVisualConsentTitle">
                            <div id="evaluationAudioVisualConsentTitle" class="audio-visual-consent-title"><i class="bi bi-hand-index-thumb me-1" aria-hidden="true"></i>Paso 1: acepta para continuar</div>
                            <label class="form-check"><input class="form-check-input" type="checkbox" data-audio-visual-consent> <span class="form-check-label">Acepto la captura de cámara, micrófono y pantalla o contenido visible durante esta evaluación y el uso de la evidencia para revisión autorizada.</span></label>
                        </div>
                        <div class="row g-2 text-start mb-3" data-audio-visual-checks>
                            <div class="col-12 col-md-4"><div class="border rounded p-2 small" data-audio-visual-check="camera"><i class="bi bi-hourglass-split me-1" data-audio-visual-check-icon aria-hidden="true"></i><span data-audio-visual-check-label>Cámara: pendiente</span><span class="d-block text-muted" data-audio-visual-check-message></span></div></div>
                            <div class="col-12 col-md-4"><div class="border rounded p-2 small" data-audio-visual-check="microphone"><i class="bi bi-hourglass-split me-1" data-audio-visual-check-icon aria-hidden="true"></i><span data-audio-visual-check-label>Micrófono: pendiente</span><span class="d-block text-muted" data-audio-visual-check-message></span></div></div>
                            <div class="col-12 col-md-4"><div class="border rounded p-2 small" data-audio-visual-check="screen"><i class="bi bi-hourglass-split me-1" data-audio-visual-check-icon aria-hidden="true"></i><span data-audio-visual-check-label>Captura: pendiente</span><span class="d-block text-muted" data-audio-visual-check-message></span></div></div>
                        </div>
                        <video class="w-100 rounded border d-none mb-2" muted playsinline autoplay data-audio-visual-preview aria-label="Vista previa de cámara"></video>
                        <div class="alert alert-secondary small text-start" data-inline-alert data-audio-visual-status>La cámara, el micrófono y la captura aún no están activos.</div>
                    <?php else: ?>
                        <p class="text-muted mb-3">Para iniciar, debes autorizar pantalla completa. La plataforma registrará eventos de control, como cambios de pestaña o salida de pantalla completa.</p>
                        <div class="alert alert-warning small text-start mb-3">Una vez iniciada la evaluación, no podrás volver atrás, recargar ni salir. Si abandonas, el intento quedará cerrado y no podrás realizarlo nuevamente.</div>
                    <?php endif; ?>
                    <button class="btn btn-primary" type="button" data-evaluation-supervised-start><i class="bi bi-arrows-fullscreen me-1"></i> Iniciar evaluación</button>
                    <button class="btn btn-outline-secondary d-none" type="button" data-evaluation-supervised-continue>Continuar con advertencia</button>
                    <p class="small text-muted mt-3 mb-0" data-evaluation-supervised-status aria-live="polite"></p>
                </div>
            </div>
        <?php endif; ?>
        <?php if (!$previewMode && $audioVisualMode): ?>
            <div class="evaluation-supervised-gate d-none" data-audio-visual-reconnect-panel role="dialog" aria-modal="true" aria-labelledby="evaluationAudioVisualReconnectTitle">
                <div class="evaluation-supervised-gate-panel">
                    <div class="evaluation-supervised-gate-icon" aria-hidden="true"><i class="bi bi-camera-video-off"></i></div>
                    <h2 id="evaluationAudioVisualReconnectTitle" class="h4 fw-bold mb-2">Control audiovisual interrumpido</h2>
                    <p class="text-muted mb-3">La captura audiovisual se interrumpió. La evaluación permanece pausada hasta recuperar cámara, micrófono y captura.</p>
                    <div class="alert alert-danger text-start small" data-inline-alert data-audio-visual-reconnect-indicator><i class="bi bi-exclamation-circle-fill me-1" data-audio-visual-reconnect-icon aria-hidden="true"></i><span data-audio-visual-reconnect-component>Componente audiovisual no disponible</span></div>
                    <button class="btn btn-primary" type="button" data-audio-visual-reconnect>Reintentar conexión audiovisual</button>
                    <p class="small text-muted mt-3 mb-0" data-audio-visual-reconnect-status>La incidencia quedará registrada.</p>
                </div>
            </div>
        <?php endif; ?>
        <?php if ($formInstructions !== ''): ?>
            <div class="evaluation-take-intro">
                <h2 class="h5 fw-bold mb-2">Indicaciones</h2>
                <div class="mb-0 evaluation-rich-text"><?= evaluation_rich_text_html($formInstructions) ?></div>
            </div>
        <?php endif; ?>
        <div class="d-flex flex-column flex-md-row justify-content-between gap-3 mb-4">
            <div class="d-flex flex-wrap gap-2">
                <?php if (!$isSurvey): ?>
                    <span class="badge text-bg-secondary">Intento <?= (int) $attempt['attempt_number'] ?></span>
                <?php endif; ?>
                <span class="badge text-bg-secondary"><?= count($questions) ?> preguntas</span>
                <?php if ($displaySeconds > 0): ?>
                    <span class="badge text-bg-warning">Tiempo restante: <span data-evaluation-countdown><?= gmdate('H:i:s', $displaySeconds) ?></span></span>
                <?php endif; ?>
            </div>
        </div>

        <div class="d-flex flex-column gap-3">
            <?php foreach ($questions as $index => $question): ?>
                <?php
                $questionId = (int) $question['id'];
                $answerValue = trim((string) ($question['answer_value'] ?? ''));
                $selectedValues = array_values(array_filter(array_map('trim', explode(',', $answerValue))));
                $matrixValues = [];
                if ((string) ($question['question_type'] ?? '') === 'matrix' && $answerValue !== '') {
                    $decodedMatrix = json_decode($answerValue, true);
                    $matrixValues = is_array($decodedMatrix) ? array_map('strval', $decodedMatrix) : [];
                }
                $required = false;
                ?>
                <input type="hidden" name="visible_question_ids[]" value="<?= $questionId ?>">
                <fieldset class="test-question-card evaluation-question-card" data-question-required="<?= $required ? '1' : '0' ?>">
                    <legend class="test-question-head">
                        <span class="test-question-number"><?= str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT) ?></span>
                        <span class="test-question-content">
                            <?php if ($required): ?><span class="text-danger evaluation-question-required" aria-label="Pregunta obligatoria">*</span><?php endif; ?>
                            <?= evaluation_rich_text_html((string) $question['question_text']) ?>
                        </span>
                    </legend>

                    <?php if (in_array($question['question_type'], ['single_choice', 'true_false'], true)): ?>
                        <div class="test-choice-grid">
                            <?php foreach (($question['options'] ?? []) as $optionIndex => $option): ?>
                                <label class="test-choice-card">
                                    <input class="form-check-input" type="radio" name="answers[<?= $questionId ?>]" value="<?= e($option['option_value']) ?>" <?= $answerValue === (string) $option['option_value'] ? 'checked' : '' ?> <?= $required ? 'required' : '' ?>>
                                    <span class="test-choice-mark"><?= e(chr(65 + ((int) $optionIndex % 26))) ?></span>
                                    <span><?= e($option['option_label']) ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    <?php elseif ($question['question_type'] === 'likert'): ?>
                        <?php
                        $likertOptions = array_values($question['options'] ?? []);
                        $firstLikertLabel = (string) ($likertOptions[0]['option_label'] ?? '');
                        $lastLikertLabel = (string) ($likertOptions[count($likertOptions) - 1]['option_label'] ?? '');
                        ?>
                        <div class="test-choice-grid evaluation-scale evaluation-scale-likert">
                            <?php foreach ($likertOptions as $optionIndex => $option): ?>
                                    <label class="test-choice-card evaluation-scale-option">
                                        <input class="form-check-input" type="radio" name="answers[<?= $questionId ?>]" value="<?= e($option['option_value']) ?>" <?= $answerValue === (string) $option['option_value'] ? 'checked' : '' ?> <?= $required ? 'required' : '' ?>>
                                        <span class="test-choice-mark"><?= e((string) $option['option_value']) ?></span>
                                        <span class="evaluation-scale-label"><?= e($option['option_label']) ?></span>
                                    </label>
                            <?php endforeach; ?>
                            <?php if ($firstLikertLabel !== '' || $lastLikertLabel !== ''): ?>
                                <div class="evaluation-scale-caption">
                                    <span><?= e($firstLikertLabel) ?></span>
                                    <span><?= e($lastLikertLabel) ?></span>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php elseif ($question['question_type'] === 'nps'): ?>
                        <div class="test-choice-grid evaluation-scale evaluation-scale-nps">
                                <?php foreach (($question['options'] ?? []) as $option): ?>
                                    <label class="test-choice-card evaluation-nps-option">
                                        <input class="form-check-input" type="radio" name="answers[<?= $questionId ?>]" value="<?= e($option['option_value']) ?>" <?= $answerValue === (string) $option['option_value'] ? 'checked' : '' ?> <?= $required ? 'required' : '' ?>>
                                        <span class="test-choice-mark"><?= e((string) $option['option_value']) ?></span>
                                        <span><?= e($option['option_label']) ?></span>
                                    </label>
                                <?php endforeach; ?>
                            <div class="evaluation-scale-caption">
                                <span>Nada probable</span>
                                <span>Muy probable</span>
                            </div>
                        </div>
                    <?php elseif ($question['question_type'] === 'rating'): ?>
                        <div class="test-choice-grid evaluation-rating-options" aria-label="<?= e(html_entity_decode(strip_tags((string) $question['question_text']), ENT_QUOTES | ENT_HTML5, 'UTF-8')) ?>">
                            <?php foreach (($question['options'] ?? []) as $option): ?>
                                <label class="test-choice-card evaluation-rating-option">
                                    <input class="form-check-input" type="radio" name="answers[<?= $questionId ?>]" value="<?= e($option['option_value']) ?>" <?= $answerValue === (string) $option['option_value'] ? 'checked' : '' ?> <?= $required ? 'required' : '' ?>>
                                    <span class="test-choice-mark"><?= e((string) $option['option_value']) ?></span>
                                    <span class="evaluation-rating-stars" aria-hidden="true">
                                        <?php for ($star = 1; $star <= (int) $option['option_value']; $star++): ?><i class="bi bi-star-fill"></i><?php endfor; ?>
                                    </span>
                                    <span class="visually-hidden"><?= e($option['option_label']) ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    <?php elseif ($question['question_type'] === 'multiple_choice'): ?>
                        <div class="test-choice-grid">
                            <?php foreach (($question['options'] ?? []) as $optionIndex => $option): ?>
                                <label class="test-choice-card">
                                    <input class="form-check-input" type="checkbox" name="answers[<?= $questionId ?>][]" value="<?= e($option['option_value']) ?>" <?= in_array((string) $option['option_value'], $selectedValues, true) ? 'checked' : '' ?>>
                                    <span class="test-choice-mark"><?= e(chr(65 + ((int) $optionIndex % 26))) ?></span>
                                    <span><?= e($option['option_label']) ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    <?php elseif ($question['question_type'] === 'matrix'): ?>
                        <div class="table-responsive">
                            <table class="table align-middle mb-0">
                                <thead>
                                    <tr>
                                        <th>Criterio</th>
                                        <?php for ($scale = 1; $scale <= 5; $scale++): ?>
                                            <th class="text-center"><?= $scale ?></th>
                                        <?php endfor; ?>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach (($question['options'] ?? []) as $option): ?>
                                        <?php $optionValue = (string) ($option['option_value'] ?? ''); ?>
                                        <tr>
                                            <td><?= e($option['option_label']) ?></td>
                                            <?php for ($scale = 1; $scale <= 5; $scale++): ?>
                                                <?php $scaleValue = (string) $scale; ?>
                                                <td class="text-center">
                                                    <input
                                                        class="form-check-input"
                                                        type="radio"
                                                        name="answers[<?= $questionId ?>][<?= e($optionValue) ?>]"
                                                        value="<?= $scale ?>"
                                                        <?= ($matrixValues[$optionValue] ?? '') === $scaleValue ? 'checked' : '' ?>
                                                        <?= $required ? 'required' : '' ?>
                                                        aria-label="<?= e($option['option_label']) ?> <?= $scale ?>">
                                                </td>
                                            <?php endfor; ?>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <textarea class="form-control" name="answers[<?= $questionId ?>]" rows="4" <?= $required ? 'required' : '' ?>><?= e($answerValue) ?></textarea>
                    <?php endif; ?>
                </fieldset>
            <?php endforeach; ?>
        </div>

        <div class="d-flex flex-wrap justify-content-end gap-2 mt-4">
            <?php if ($isDrawer): ?>
                <button class="btn btn-sm btn-outline-secondary" type="button" data-app-drawer-close>Cancelar</button>
            <?php else: ?>
                <a class="btn btn-sm btn-outline-secondary" href="<?= e($returnUrl) ?>">Cancelar</a>
            <?php endif; ?>
            <button class="btn btn-sm btn-primary px-4" type="submit" name="evaluation_survey_action" value="complete" <?= $previewMode ? 'disabled' : '' ?>>
                <i class="bi bi-save me-1"></i> Guardar y finalizar
            </button>
        </div>
    </form>
</section>
