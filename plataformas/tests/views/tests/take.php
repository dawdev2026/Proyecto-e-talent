<?php
$progressItems = $progressItems ?? $items;
$totalItems = count($progressItems);
$useBlocks = !empty($useBlocks);
$currentBlock = max(1, (int) ($currentBlock ?? 1));
$totalBlocks = max(1, (int) ($totalBlocks ?? 1));
$blockStart = max(0, (int) ($blockStart ?? 0));
$requireBlockCompletion = !empty($requireBlockCompletion);
$remainingSeconds = isset($remainingSeconds) ? (int) $remainingSeconds : null;
$processAvailability = is_array($session['process_availability'] ?? null) ? $session['process_availability'] : [];
$processRemainingSeconds = array_key_exists('remaining_seconds', $processAvailability) && $processAvailability['remaining_seconds'] !== null
    ? max(0, (int) $processAvailability['remaining_seconds'])
    : null;
$evaluationMessages = array_merge(TestSettingsModel::DEFAULTS, $evaluationMessages ?? []);
$audioVisualMode = (string) ($session['control_mode'] ?? '') === 'supervised_audio_visual';
$supervisedMode = $audioVisualMode || (int) ($session['supervised_mode_enabled'] ?? 0) === 1 || (string) ($session['control_mode'] ?? '') === 'supervised';
$audioVisualInterruptionPolicy = (string) ($session['audio_visual_interruption_policy'] ?? 'pause');
$audioVisualVoicePolicy = (string) ($session['audio_visual_voice_policy'] ?? 'warn');
$audioVisualPermissionPolicy = (string) ($session['audio_visual_permission_policy'] ?? 'pause');
$audioVisualQualityProfile = (string) ($session['audio_visual_quality_profile'] ?? 'standard');
$showQuestionNumbers = (int) ($session['show_question_numbers'] ?? 1) === 1;
$answeredTotal = 0;
$currentAnswered = 0;
foreach ($progressItems as $progressIndex => $progressItem) {
    if (trim((string) ($progressItem['answer_value'] ?? '')) !== '') {
        $answeredTotal++;
        if ($progressIndex >= $blockStart && $progressIndex < ($blockStart + count($items))) {
            $currentAnswered++;
        }
    }
}
$progressPercent = $totalItems > 0 ? (int) round(($answeredTotal / $totalItems) * 100) : 0;

if (!function_exists('test_prompt_html')) {
    function test_prompt_html(string $prompt, ?string $legacyImageUrl = null): string
    {
        $htmlPrefix = '__html64__:';
        $blocksPrefix = '__blocks64__:';
        $html = '';

        if (strpos($prompt, $htmlPrefix) === 0) {
            $payload = substr($prompt, strlen($htmlPrefix));
            $decoded = base64_decode($payload, true);
            $html = $decoded !== false ? $decoded : '';
        } elseif (strpos($prompt, $blocksPrefix) === 0) {
            $payload = substr($prompt, strlen($blocksPrefix));
            $decoded = base64_decode($payload, true);
            $blocks = $decoded !== false ? json_decode($decoded, true) : null;
            if (is_array($blocks)) {
                foreach ($blocks as $block) {
                    if (($block['type'] ?? '') === 'image' && !empty($block['url'])) {
                        $html .= '<p><img src="' . e((string) $block['url']) . '" alt=""></p>';
                    } elseif (($block['type'] ?? '') === 'text' && trim((string) ($block['text'] ?? '')) !== '') {
                        $html .= '<p>' . nl2br(e((string) $block['text'])) . '</p>';
                    }
                }
            }
        } elseif (trim($prompt) !== '') {
            if (preg_match('/<(p|br|img|strong|b|em|i|u|ul|ol|li)\b/i', $prompt)) {
                $html = html_entity_decode($prompt, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            } else {
                $html = '<p>' . nl2br(e($prompt)) . '</p>';
            }
        }

        if ($legacyImageUrl) {
            $html .= '<p><img src="' . e($legacyImageUrl) . '" alt=""></p>';
        }

        return sanitize_test_prompt_html($html);
    }
}

if (!function_exists('sanitize_test_prompt_html')) {
    function sanitize_test_prompt_html(string $html): string
    {
        $clean = strip_tags($html, '<p><br><img><strong><b><em><i><u><ul><ol><li>');
        $clean = preg_replace('/\s+on[a-z]+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $clean) ?? '';
        $clean = preg_replace('/\s+(class|id|srcset|sizes|title|loading)\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $clean) ?? '';
        $clean = preg_replace_callback('/<(p|li)\b([^>]*)>/i', static function (array $match): string {
            $tag = strtolower($match[1]);
            $attrs = $match[2] ?? '';
            $safeStyles = [];

            if (preg_match('/\sstyle\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', $attrs, $styleMatch)) {
                $style = trim($styleMatch[1], '"\'');
                if (preg_match('/(?:^|;)\s*text-align\s*:\s*(left|center|right|justify)/i', $style, $alignMatch)) {
                    $safeStyles[] = 'text-align: ' . strtolower($alignMatch[1]);
                }
                if (preg_match('/(?:^|;)\s*margin-left\s*:\s*([0-9]+)px/i', $style, $marginMatch)) {
                    $marginLeft = min((int) $marginMatch[1], 240);
                    if ($marginLeft > 0) {
                        $safeStyles[] = 'margin-left: ' . $marginLeft . 'px';
                    }
                }
            }

            return '<' . $tag . ($safeStyles ? ' style="' . e(implode('; ', $safeStyles)) . '"' : '') . '>';
        }, $clean) ?? '';
        $clean = preg_replace('/<(strong|b|em|i|u|ul|ol|br)\b[^>]*>/i', '<$1>', $clean) ?? '';
        $clean = preg_replace_callback('/<img\b[^>]*>/i', static function (array $match): string {
            $tag = $match[0];
            if (!preg_match('/\ssrc\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', $tag, $srcMatch)) {
                return '';
            }

            $src = trim($srcMatch[1], '"\'');
            if (!preg_match('/^(https?:\/\/|\/|data:image\/)/i', $src)) {
                return '';
            }

            $dimensions = '';
            foreach (['width', 'height'] as $attribute) {
                $value = '';
                if (preg_match('/\s' . $attribute . '\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', $tag, $dimensionMatch)) {
                    $value = preg_replace('/[^0-9]/', '', trim($dimensionMatch[1], '"\''));
                } elseif (preg_match('/\sstyle\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', $tag, $styleMatch)) {
                    $style = trim($styleMatch[1], '"\'');
                    $property = preg_quote($attribute, '/');
                    if (preg_match('/(?:^|;)\s*' . $property . '\s*:\s*([0-9]+)px/i', $style, $styleValueMatch)) {
                        $value = $styleValueMatch[1];
                    }
                }

                if ($value !== '' && (int) $value > 0 && (int) $value <= 2000) {
                    $dimensions .= ' ' . $attribute . '="' . e($value) . '"';
                }
            }

            return '<img src="' . e($src) . '" alt=""' . $dimensions . '>';
        }, $clean) ?? '';

        return $clean;
    }
}

if (!function_exists('test_choice_marker')) {
    function test_choice_marker(int $index): string
    {
        $marker = '';
        $index++;

        while ($index > 0) {
            $index--;
            $marker = chr(65 + ($index % 26)) . $marker;
            $index = intdiv($index, 26);
        }

        return $marker;
    }
}
?>
<section class="test-taking-shell">
    <div class="test-taking-header">
        <div>
            <p class="text-uppercase text-primary fw-bold small mb-1">Responder</p>
            <h1 class="fw-bold mb-1"><?= e($session['instrument_name']) ?></h1>
            <p class="text-muted mb-0">Avanza por la evaluacion y revisa tus respuestas antes de finalizar.</p>
        </div>
        <div class="test-taking-side">
            <div class="test-taking-meta">
                <span><i class="bi bi-list-check"></i> <?= (int) $totalItems ?> items</span>
                <?php if ($useBlocks): ?>
                    <span><i class="bi bi-grid-3x3-gap"></i> Bloque <?= (int) $currentBlock ?> de <?= (int) $totalBlocks ?></span>
                <?php endif; ?>
                <span><i class="bi bi-clock"></i> <?= (int) $session['duration_minutes'] > 0 ? (int) $session['duration_minutes'] . ' min' : 'Sin limite' ?></span>
            </div>
            <a class="btn btn-sm btn-back test-taking-back" href="<?= e(route_url('my-tests')) ?>">
                <i class="bi bi-arrow-left me-1"></i> Volver
            </a>
        </div>
    </div>
    <div class="test-fullscreen-stage" data-test-fullscreen-stage>
        <form
            method="post"
            class="needs-validation"
            novalidate
            data-test-taking-form
            data-supervised-mode="<?= $supervisedMode ? '1' : '0' ?>"
            data-audio-visual-mode="<?= $audioVisualMode ? '1' : '0' ?>"
            data-audio-visual-upload-failure-policy="<?= e((string) ($session['audio_visual_upload_failure_policy'] ?? 'continue')) ?>"
            data-audio-visual-interruption-policy="<?= e($audioVisualInterruptionPolicy) ?>"
            data-audio-visual-voice-policy="<?= e($audioVisualVoicePolicy) ?>"
            data-audio-visual-permission-policy="<?= e($audioVisualPermissionPolicy) ?>"
            data-audio-visual-quality-profile="<?= e($audioVisualQualityProfile) ?>"
            data-block-ajax="<?= $useBlocks ? '1' : '0' ?>"
            data-require-block-completion="<?= $requireBlockCompletion ? '1' : '0' ?>"
            data-my-tests-url="<?= e(route_url('my-tests')) ?>"
            data-activity-tracking="<?= (int) ($session['track_activity_enabled'] ?? 0) === 1 ? '1' : '0' ?>"
            data-activity-url="<?= e(route_url('test-session.activity', (int) $session['id'])) ?>"
            data-media-init-url="<?= e(route_url('test-session.media-init', (int) $session['id'])) ?>"
            data-media-status-url="<?= e(route_url('test-session.media-status', (int) $session['id'])) ?>"
            data-media-chunk-url="<?= e(route_url('test-session.media-chunk', (int) $session['id'])) ?>"
            data-media-finalize-url="<?= e(route_url('test-session.media-finalize', (int) $session['id'])) ?>"
            data-media-risk-url="<?= e(route_url('test-session.media-risk', (int) $session['id'])) ?>"
            data-media-failure-url="<?= e(route_url('test-session.media-failure', (int) $session['id'])) ?>"
            data-media-screenshot-url="<?= e(route_url('test-session.media-screenshot', (int) $session['id'])) ?>"
            data-draft-url="<?= e(route_url('test-session.draft', (int) $session['id'])) ?>"
            data-process-availability-url="<?= e(route_url('test-session.availability', (int) $session['id'])) ?>"
            data-current-block="<?= (int) $currentBlock ?>"
            data-remaining-seconds="<?= $remainingSeconds !== null ? (int) $remainingSeconds : '' ?>"
            data-process-remaining-seconds="<?= $processRemainingSeconds !== null ? (int) $processRemainingSeconds : '' ?>"
            data-test-exit-title="<?= e($evaluationMessages['exit_confirm_title']) ?>"
            data-test-exit-message="<?= e($evaluationMessages['exit_confirm_message']) ?>"
            data-test-exit-continue-button="<?= e($evaluationMessages['exit_continue_button']) ?>"
            data-test-exit-save-button="<?= e($evaluationMessages['exit_save_exit_button']) ?>"
            data-incomplete-confirm-title="<?= e($evaluationMessages['incomplete_confirm_title']) ?>"
            data-incomplete-confirm-message="<?= e($evaluationMessages['incomplete_confirm_message']) ?>"
            data-incomplete-confirm-button="<?= e($evaluationMessages['incomplete_confirm_button']) ?>"
            data-incomplete-cancel-button="<?= e($evaluationMessages['incomplete_cancel_button']) ?>"
            data-expired-message="<?= e($evaluationMessages['expired_message']) ?>"
        >
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
        <?php if ($supervisedMode): ?>
            <div class="supervised-gate" data-supervised-gate role="dialog" aria-modal="true" aria-labelledby="supervisedGateTitle">
                <div class="supervised-gate-panel">
                    <span class="supervised-gate-icon"><i class="bi bi-shield-lock"></i></span>
                    <h2 id="supervisedGateTitle">Modo de rendicion supervisada</h2>
                    <p>Para comenzar, intenta usar pantalla completa. Si tu dispositivo o navegador no lo permite, la evaluacion continuara registrando otras senales de actividad.</p>
                    <?php if ($audioVisualMode): ?>
                        <div class="border rounded p-3 mb-3 text-start" data-audio-visual-setup>
                            <strong class="d-block mb-2">Control audiovisual</strong>
                            <span class="form-label d-block">Regla ante interrupcion</span>
                            <input type="hidden" data-audio-visual-policy value="<?= e($audioVisualInterruptionPolicy) ?>">
                            <div class="form-text mb-2"><?php
                                echo e(['continue' => 'Registrar y continuar', 'pause' => 'Registrar y pausar', 'block' => 'Registrar y bloquear'][$audioVisualInterruptionPolicy] ?? 'Registrar y pausar');
                            ?></div>
                            <div class="row g-2 mb-2" data-audio-visual-checks>
                                <div class="col-12 col-md-4"><div class="border rounded p-2 small" data-audio-visual-check="camera"><i class="bi bi-hourglass-split me-1" data-audio-visual-check-icon aria-hidden="true"></i><span data-audio-visual-check-label>Cámara: pendiente</span><span class="d-block text-muted" data-audio-visual-check-message></span></div></div>
                                <div class="col-12 col-md-4"><div class="border rounded p-2 small" data-audio-visual-check="microphone"><i class="bi bi-hourglass-split me-1" data-audio-visual-check-icon aria-hidden="true"></i><span data-audio-visual-check-label>Micrófono: pendiente</span><span class="d-block text-muted" data-audio-visual-check-message></span></div></div>
                                <div class="col-12 col-md-4"><div class="border rounded p-2 small" data-audio-visual-check="screen"><i class="bi bi-hourglass-split me-1" data-audio-visual-check-icon aria-hidden="true"></i><span data-audio-visual-check-label>Captura: pendiente</span><span class="d-block text-muted" data-audio-visual-check-message></span></div></div>
                            </div>
                            <video class="w-100 rounded border d-none mb-2 audio-visual-camera-preview" muted playsinline autoplay data-audio-visual-preview aria-label="Vista previa de cámara"></video>
                            <div class="alert alert-secondary py-2 mb-2 d-none" role="status" aria-live="polite" data-audio-visual-status></div>
                            <div class="audio-visual-consent-box" role="group" aria-labelledby="audioVisualConsentTitle">
                                <div id="audioVisualConsentTitle" class="audio-visual-consent-title"><i class="bi bi-hand-index-thumb me-1" aria-hidden="true"></i>Paso 1: acepta para continuar</div>
                                <div class="form-check">
                                    <input id="audio_visual_consent" class="form-check-input" type="checkbox" data-audio-visual-consent>
                                    <label class="form-check-label small" for="audio_visual_consent">Acepto la captura de camara, microfono y pantalla o contenido visible durante la evaluacion y su revision por administradores autorizados.</label>
                                </div>
                            </div>
                            <div class="form-text">La camara, el microfono y la captura visual dependen de los permisos del navegador y del dispositivo. Toda interrupcion quedara registrada.</div>
                        </div>
                    <?php endif; ?>
                    <button class="btn btn-primary" type="button" data-supervised-start>
                        <i class="bi bi-fullscreen me-1"></i> Iniciar evaluacion
                    </button>
                    <button class="btn btn-outline-secondary d-none" type="button" data-supervised-continue>
                        Continuar sin pantalla completa
                    </button>
                </div>
            </div>
            <div class="supervised-gate is-exit-warning d-none" data-supervised-exit-warning role="dialog" aria-modal="true" aria-labelledby="supervisedExitTitle">
                <div class="supervised-gate-panel">
                    <span class="supervised-gate-icon"><i class="bi bi-fullscreen-exit"></i></span>
                    <h2 id="supervisedExitTitle">Saliste de pantalla completa</h2>
                    <p>Para continuar con el modo supervisado, vuelve a pantalla completa. Si tu dispositivo no lo permite, puedes continuar con advertencia registrada.</p>
                    <?php if ($remainingSeconds !== null): ?>
                        <div class="test-countdown supervised-gate-countdown">
                            <span>Tiempo restante</span>
                            <strong data-test-countdown-mirror>--:--</strong>
                        </div>
                    <?php endif; ?>
                    <button class="btn btn-primary" type="button" data-supervised-reenter>
                        <i class="bi bi-fullscreen me-1"></i> Volver a pantalla completa
                    </button>
                    <button class="btn btn-outline-secondary" type="button" data-supervised-dismiss-warning>
                        Continuar con advertencia
                    </button>
                </div>
            </div>
            <?php if ($audioVisualMode): ?>
                <div class="supervised-gate is-audio-visual-reconnect d-none" data-audio-visual-reconnect-panel role="dialog" aria-modal="true" aria-labelledby="audioVisualReconnectTitle">
                    <div class="supervised-gate-panel">
                        <span class="supervised-gate-icon"><i class="bi bi-camera-video-off"></i></span>
                        <h2 id="audioVisualReconnectTitle">Control audiovisual interrumpido</h2>
                        <p>La evaluación está pausada y las preguntas permanecen ocultas. Permite nuevamente la cámara y el micrófono para continuar.</p>
                        <div class="alert alert-danger text-start small" data-inline-alert data-audio-visual-reconnect-indicator><i class="bi bi-exclamation-circle-fill me-1" data-audio-visual-reconnect-icon aria-hidden="true"></i><span data-audio-visual-reconnect-component>Componente audiovisual no disponible</span></div>
                        <div class="small text-muted" data-audio-visual-reconnect-status>La acción quedará registrada.</div>
                        <button class="btn btn-primary" type="button" data-audio-visual-reconnect>Reintentar conexión audiovisual</button>
                    </div>
                </div>
            <?php endif; ?>
        <?php endif; ?>
        <div class="test-taking-scroll" data-test-fullscreen-scroll tabindex="-1" aria-label="Contenido de la evaluacion">
        <div class="test-block-saving d-none" data-test-block-saving role="status" aria-live="polite">
            <span class="spinner-border spinner-border-sm" aria-hidden="true"></span>
            <span>Guardando datos...</span>
        </div>
        <?php if ($useBlocks): ?>
            <input type="hidden" name="block" value="<?= (int) $currentBlock ?>" data-test-current-block-input>
            <div class="test-block-status" data-test-block-status>
                <div>
                    <strong>Bloque <?= (int) $currentBlock ?> de <?= (int) $totalBlocks ?></strong>
                    <span>Preguntas <?= (int) ($blockStart + 1) ?> a <?= (int) min($totalItems, $blockStart + count($items)) ?></span>
                </div>
                <span class="badge text-bg-secondary">Puedes avanzar y volver despues</span>
            </div>
        <?php endif; ?>
        <div
            class="test-progress-bar"
            data-test-progress
            data-total="<?= (int) $totalItems ?>"
            data-saved-outside="<?= max(0, (int) ($answeredTotal - $currentAnswered)) ?>"
        >
            <div class="test-progress-bar-head">
                <strong>Avance de la evaluacion</strong>
                <span data-test-progress-label><?= (int) $progressPercent ?>%</span>
            </div>
            <div class="test-progress-track" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="<?= (int) $progressPercent ?>">
                <span data-test-progress-fill style="width: <?= (int) $progressPercent ?>%"></span>
            </div>
            <div class="test-progress-caption">
                <span data-test-progress-count><?= (int) $answeredTotal ?></span> de <?= (int) $totalItems ?> preguntas respondidas
            </div>
        </div>

        <div class="test-item-stack" data-test-item-stack>
            <?php foreach ($items as $index => $item): ?>
                <?php $options = $sessionModel->optionPairs($item['options'] ?? ''); ?>
                <?php $promptHtml = test_prompt_html((string) $item['prompt'], $item['image_url'] ?? null); ?>
                <?php $questionNumber = $blockStart + $index + 1; ?>
                <?php $fieldRequired = !$useBlocks; ?>
                <fieldset class="test-question-card" data-question-index="<?= (int) ($questionNumber - 1) ?>" data-was-answered="<?= trim((string) ($item['answer_value'] ?? '')) !== '' ? '1' : '0' ?>">
                    <div class="test-question-head <?= $showQuestionNumbers ? '' : 'is-question-number-hidden' ?>">
                        <?php if ($showQuestionNumbers): ?>
                            <span class="test-question-number"><?= str_pad((string) $questionNumber, 2, '0', STR_PAD_LEFT) ?></span>
                        <?php endif; ?>
                        <div>
                            <legend class="visually-hidden">Pregunta <?= $questionNumber ?></legend>
                            <div class="test-question-content">
                                <?= $promptHtml ?>
                            </div>
                        </div>
                    </div>

                    <?php if (in_array($item['item_type'], ['likert', 'single_choice'], true)): ?>
                        <div class="test-choice-grid">
                            <?php $choiceIndex = 0; ?>
                            <?php foreach ($options as $value => $label): ?>
                                <?php $choiceMarker = test_choice_marker($choiceIndex++); ?>
                                <label class="test-choice-card">
                                    <input type="radio" name="answers[<?= (int) $item['id'] ?>]" value="<?= e($value) ?>" <?= (string) $item['answer_value'] === (string) $value ? 'checked' : '' ?> <?= $fieldRequired ? 'required' : '' ?>>
                                    <span class="test-choice-mark"><?= e($choiceMarker) ?></span>
                                    <span><?= e($label) ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    <?php elseif ($item['item_type'] === 'multiple_choice'): ?>
                        <div class="test-choice-grid">
                            <?php $selectedValues = array_filter(array_map('trim', explode(',', (string) ($item['answer_value'] ?? '')))); ?>
                            <?php $choiceIndex = 0; ?>
                            <?php foreach ($options as $value => $label): ?>
                                <?php $choiceMarker = test_choice_marker($choiceIndex++); ?>
                                <label class="test-choice-card">
                                    <input type="checkbox" name="answers[<?= (int) $item['id'] ?>][]" value="<?= e($value) ?>" <?= in_array((string) $value, $selectedValues, true) ? 'checked' : '' ?>>
                                    <span class="test-choice-mark"><?= e($choiceMarker) ?></span>
                                    <span><?= e($label) ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    <?php elseif ($item['item_type'] === 'numeric'): ?>
                        <input class="form-control form-control-lg" type="number" step="any" name="answers[<?= (int) $item['id'] ?>]" value="<?= e($item['answer_value'] ?? '') ?>" <?= $fieldRequired ? 'required' : '' ?>>
                    <?php else: ?>
                        <textarea class="form-control" name="answers[<?= (int) $item['id'] ?>]" rows="4" <?= $fieldRequired ? 'required' : '' ?>><?= e($item['answer_value'] ?? '') ?></textarea>
                    <?php endif; ?>
                </fieldset>
            <?php endforeach; ?>
        </div>
        </div>
        <div class="test-submit-bar">
            <?php if ($remainingSeconds !== null): ?>
                <div class="test-countdown" data-test-countdown="<?= max(0, (int) $remainingSeconds) ?>">
                    <span>Tiempo restante</span>
                    <strong data-test-countdown-display>--:--</strong>
                </div>
            <?php endif; ?>
            <div class="test-submit-actions" data-test-submit-actions>
                <?php if ($useBlocks): ?>
                    <?php if ($currentBlock > 1): ?>
                        <button class="btn btn-outline-secondary px-4" type="submit" name="test_action" value="save_block_prev"><i class="bi bi-arrow-left-circle me-1"></i> Atras</button>
                    <?php endif; ?>
                    <?php if ($currentBlock < $totalBlocks): ?>
                        <button class="btn btn-primary px-4" type="submit" name="test_action" value="save_block_next"><i class="bi bi-arrow-right-circle me-1"></i> Guardar y Continuar</button>
                    <?php else: ?>
                        <button class="btn btn-primary px-4" type="submit" name="test_action" value="save_block_finish"><i class="bi bi-check2-circle me-1"></i> Guardar y Finalizar</button>
                    <?php endif; ?>
                <?php else: ?>
                    <button class="btn btn-primary px-4" type="submit" name="test_action" value="complete"><i class="bi bi-check2-circle me-1"></i> Guardar y Finalizar</button>
                <?php endif; ?>
            </div>
        </div>
        </form>
        <div class="test-fullscreen-scroll-tools">
            <button type="button" data-test-scroll-step="-1" aria-label="Subir contenido">
                <i class="bi bi-chevron-up"></i>
            </button>
            <button type="button" data-test-scroll-step="1" aria-label="Bajar contenido">
                <i class="bi bi-chevron-down"></i>
            </button>
        </div>
    </div>
</section>
