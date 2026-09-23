<?php $answeredItems = $answeredItems ?? []; ?>
<section class="page-header">
    <div>
        <p class="text-uppercase text-primary fw-bold small mb-1">Resultado evaluacion</p>
        <h1 class="fw-bold mb-1"><?= e($session['instrument_name']) ?></h1>
        <p class="text-muted mb-0">
            <?= $session['status'] === 'expired'
                ? 'Evaluacion finalizada por tiempo. El resumen considera las respuestas guardadas hasta ese momento.'
                : 'Resumen calculado con la configuracion de scoring registrada para esta evaluacion.' ?>
        </p>
    </div>
</section>

<?php
$resultContext = $resultContext ?? [];
$scaleMetrics = $resultContext['scale_metrics'] ?? [];
$supportsCorrectAnswers = !empty($resultContext['supports_correct_answers']);
$age = trim((string) ($resultContext['age'] ?? ''));
$activityEvents = $activityEvents ?? [];
$mediaEvidence = is_array($mediaEvidence ?? null) ? $mediaEvidence : null;
$mediaEvidences = is_array($mediaEvidences ?? null) ? $mediaEvidences : ($mediaEvidence ? [$mediaEvidence] : []);
$audioVisualRisks = is_array($audioVisualRisks ?? null) ? $audioVisualRisks : [];
$screenCaptures = is_array($screenCaptures ?? null) ? $screenCaptures : [];
$riasecResult = $riasecResult ?? null;

if (!function_exists('test_activity_label')) {
    function test_activity_label(string $eventType): string
    {
        $labels = [
            'evaluation_opened' => 'Evaluacion abierta',
            'device_snapshot' => 'Dispositivo detectado',
            'evaluation_started' => 'Evaluacion iniciada',
            'evaluation_reopened' => 'Evaluacion reabierta',
            'evaluation_reopened_by_admin' => 'Evaluacion reabierta por administrador',
            'answer_saved' => 'Respuestas guardadas',
            'answer_changed' => 'Respuestas modificadas',
            'draft_saved' => 'Borrador guardado',
            'block_saved' => 'Bloque guardado',
            'evaluation_paused' => 'Evaluacion pausada',
            'evaluation_submitted' => 'Evaluacion enviada',
            'evaluation_expired' => 'Evaluacion expirada',
            'tab_hidden' => 'Pestana oculta',
            'tab_visible' => 'Pestana visible',
            'window_blurred' => 'Ventana sin foco',
            'window_focused' => 'Ventana con foco',
            'inactive_detected' => 'Inactividad detectada',
            'activity_resumed' => 'Actividad retomada',
            'fullscreen_entered' => 'Pantalla completa iniciada',
            'fullscreen_reentered' => 'Pantalla completa retomada',
            'fullscreen_denied' => 'Pantalla completa rechazada',
            'fullscreen_failed' => 'Pantalla completa no aplicada',
            'fullscreen_unavailable' => 'Pantalla completa no disponible',
            'fullscreen_exited' => 'Salida de pantalla completa',
            'suspicious_key_printscreen' => 'Tecla de captura detectada',
            'suspicious_key_print' => 'Intento de imprimir',
            'suspicious_key_save' => 'Intento de guardar pagina',
            'suspicious_key_copy' => 'Intento de copiar',
            'suspicious_key_devtools' => 'Intento de herramientas de desarrollo',
            'context_menu_blocked' => 'Menu contextual bloqueado',
            'copy_blocked' => 'Copia bloqueada',
            'cut_blocked' => 'Corte bloqueado',
            'paste_blocked' => 'Pegado bloqueado',
            'drag_blocked' => 'Arrastre bloqueado',
            'print_blocked' => 'Impresion bloqueada',
            'audio_visual_recording_started' => 'Control audiovisual iniciado',
            'audio_visual_consent_accepted' => 'Consentimiento audiovisual aceptado',
            'audio_visual_recording_interrupted' => 'Grabacion audiovisual interrumpida',
            'audio_visual_recording_recovered' => 'Grabacion audiovisual recuperada',
            'audio_visual_upload_started' => 'Carga audiovisual iniciada',
            'audio_visual_upload_completed' => 'Evidencia audiovisual guardada',
            'audio_visual_upload_failed' => 'Fallo al guardar evidencia audiovisual',
            'audio_visual_risk' => 'Riesgo audiovisual registrado',
        ];

        return $labels[$eventType] ?? $eventType;
    }
}

if (!function_exists('test_activity_help')) {
    function test_activity_help(string $eventType): string
    {
        $help = [
            'attention_events' => 'Total de senales donde la persona pudo haber dejado de atender la evaluacion: pestana oculta, ventana sin foco, inactividad detectada o salida de pantalla completa.',
            'evaluation_opened' => 'La persona abrio o cargo la pantalla de la evaluacion. Puede ocurrir antes de responder o al volver a entrar a la pagina.',
            'device_snapshot' => 'Datos tecnicos aproximados del navegador y dispositivo usado al abrir la evaluacion.',
            'evaluation_started' => 'La evaluacion paso desde asignada a iniciada por primera vez para esta sesion.',
            'evaluation_reopened' => 'La persona volvio a abrir una evaluacion que ya estaba iniciada y aun no finalizaba.',
            'evaluation_reopened_by_admin' => 'Un usuario autorizado reabrio una evaluacion expirada y reasigno un nuevo tiempo de respuesta.',
            'answer_saved' => 'Se guardaron respuestas sin detectar cambios sobre respuestas previamente almacenadas.',
            'answer_changed' => 'Se guardaron respuestas y al menos una era distinta a lo registrado anteriormente.',
            'draft_saved' => 'Se guardo un borrador de las respuestas para conservar el avance de la evaluacion.',
            'block_saved' => 'Se guardo un bloque de preguntas en una evaluacion configurada por bloques.',
            'evaluation_paused' => 'La evaluacion se guardo y quedo pausada para continuarla posteriormente.',
            'evaluation_submitted' => 'La persona envio la evaluacion para finalizarla.',
            'evaluation_expired' => 'La evaluacion finalizo porque se agoto el tiempo disponible.',
            'tab_hidden' => 'La pestana de la evaluacion quedo oculta. Suele ocurrir al cambiar de pestana, minimizar el navegador o enviar la app al fondo.',
            'tab_visible' => 'La pestana de la evaluacion volvio a estar visible para la persona.',
            'window_blurred' => 'La ventana del navegador perdio foco. Puede ocurrir al cambiar a otra ventana, otra app, la barra de direcciones o un dialogo del sistema.',
            'window_focused' => 'La ventana del navegador recupero foco despues de haberlo perdido.',
            'inactive_detected' => 'No se detecto interaccion durante un periodo relevante dentro de la evaluacion.',
            'activity_resumed' => 'Se detecto actividad nuevamente despues de un periodo de inactividad.',
            'fullscreen_entered' => 'La evaluacion entro a pantalla completa al iniciar el modo supervisado.',
            'fullscreen_reentered' => 'La persona volvio a pantalla completa despues de una salida o advertencia.',
            'fullscreen_denied' => 'El navegador o la persona rechazaron la solicitud de pantalla completa.',
            'fullscreen_failed' => 'No se pudo aplicar pantalla completa o la persona continuo con advertencia registrada.',
            'fullscreen_unavailable' => 'El dispositivo o navegador no declaro soporte para pantalla completa.',
            'fullscreen_exited' => 'La persona salio de pantalla completa durante la evaluacion.',
            'suspicious_key_printscreen' => 'Se detecto la tecla PrintScreen. Los navegadores no siempre permiten bloquearla.',
            'suspicious_key_print' => 'Se intento abrir impresion desde el teclado.',
            'suspicious_key_save' => 'Se intento guardar la pagina desde el teclado.',
            'suspicious_key_copy' => 'Se intento copiar contenido desde el teclado.',
            'suspicious_key_devtools' => 'Se intento abrir herramientas de desarrollo o inspeccion.',
            'context_menu_blocked' => 'Se bloqueo la apertura del menu contextual dentro de la evaluacion.',
            'copy_blocked' => 'Se bloqueo una accion de copiado dentro de la evaluacion.',
            'cut_blocked' => 'Se bloqueo una accion de corte dentro de la evaluacion.',
            'paste_blocked' => 'Se bloqueo una accion de pegado dentro de la evaluacion.',
            'drag_blocked' => 'Se bloqueo el arrastre de contenido dentro de la evaluacion.',
            'print_blocked' => 'Se detecto o bloqueo un intento de imprimir la evaluacion.',
            'audio_visual_recording_started' => 'Se inicio la captura de camara y microfono con consentimiento del usuario.',
            'audio_visual_recording_interrupted' => 'La captura audiovisual perdio una pista o dejo de entregar datos.',
            'audio_visual_recording_recovered' => 'La captura audiovisual recupero una pista.',
            'audio_visual_upload_started' => 'Se inicio la carga de un fragmento de evidencia audiovisual.',
            'audio_visual_upload_completed' => 'La evidencia audiovisual fue ensamblada y almacenada correctamente.',
            'audio_visual_upload_failed' => 'No fue posible guardar toda la evidencia audiovisual; revise el motivo registrado.',
            'audio_visual_risk' => 'Se registro una señal audiovisual para revision posterior.',
        ];

        return $help[$eventType] ?? 'Evento de actividad registrado durante la rendicion de la evaluacion.';
    }
}

if (!function_exists('test_activity_help_button')) {
    function test_activity_help_button(string $eventType, ?string $title = null): string
    {
        $label = $title ?: test_activity_label($eventType);

        return '<button class="btn btn-link btn-sm p-0 ms-1 activity-help-toggle" type="button" aria-label="Ayuda sobre ' . e($label) . '" data-bs-toggle="popover" data-bs-trigger="focus" data-bs-placement="top" data-bs-title="' . e($label) . '" data-bs-content="' . e(test_activity_help($eventType)) . '"><i class="bi bi-info-circle" aria-hidden="true"></i></button>';
    }
}

if (!function_exists('test_result_audio_visual_risk_label')) {
    function test_result_audio_visual_risk_label(?string $eventType): string
    {
        $labels = [
            'multiple_voice_possible' => 'Posible multiplicidad de voces',
            'audio_visual_reconnected' => 'Control audiovisual reconectado',
            'recording_upload_failed' => 'Fallo en carga de grabación',
            'camera_muted' => 'Cámara silenciada',
            'microphone_muted' => 'Micrófono silenciado',
            'camera_track_ended' => 'Cámara desconectada',
            'microphone_track_ended' => 'Micrófono desconectado',
            'voice_analysis_unavailable' => 'Detección de voces no disponible',
        ];

        return $labels[(string) $eventType] ?? 'Señal audiovisual registrada';
    }
}

if (!function_exists('test_activity_metadata_label')) {
    function test_activity_metadata_label(?string $metadata): string
    {
        $data = $metadata ? json_decode($metadata, true) : null;
        if (!is_array($data) || !$data) {
            return '';
        }

        $parts = [];
        if (isset($data['block'])) {
            $parts[] = 'Bloque ' . (int) $data['block'];
        }
        if (isset($data['answered_count'])) {
            $parts[] = (int) $data['answered_count'] . ' respuestas';
        }
        if (isset($data['changed_count']) && (int) $data['changed_count'] > 0) {
            $parts[] = (int) $data['changed_count'] . ' cambios';
        }
        if (isset($data['hidden_seconds'])) {
            $parts[] = (int) $data['hidden_seconds'] . ' s fuera';
        }
        if (isset($data['inactive_seconds'])) {
            $parts[] = (int) $data['inactive_seconds'] . ' s inactivo';
        }
        if (isset($data['remaining_seconds']) && $data['remaining_seconds'] !== '') {
            $parts[] = (int) $data['remaining_seconds'] . ' s restantes';
        }
        if (!empty($data['device_type']) || !empty($data['os']) || !empty($data['browser'])) {
            $device = trim(implode(' ', array_filter([
                !empty($data['device_type']) ? ucfirst((string) $data['device_type']) : '',
                !empty($data['os']) ? (string) $data['os'] : '',
            ])));
            $parts[] = trim($device . (!empty($data['browser']) ? ' · ' . (string) $data['browser'] : ''));
        }
        if (!empty($data['screen'])) {
            $parts[] = 'Pantalla ' . (string) $data['screen'];
        }
        if (!empty($data['viewport'])) {
            $parts[] = 'Ventana ' . (string) $data['viewport'];
        }
        if (!empty($data['timezone'])) {
            $parts[] = 'Zona ' . (string) $data['timezone'];
        }
        if (!empty($data['connection'])) {
            $parts[] = 'Conexion ' . (string) $data['connection'];
        }
        if (isset($data['duration_minutes'])) {
            $parts[] = 'Nuevo tiempo ' . (int) $data['duration_minutes'] . ' min';
        }
        if (!empty($data['authorized_by_name'])) {
            $parts[] = 'Autorizado por ' . (string) $data['authorized_by_name'];
        }
        if (!empty($data['authorized_by_profile'])) {
            $parts[] = 'Perfil autorizador ' . (string) $data['authorized_by_profile'];
        }
        if (!empty($data['authorized_by_email'])) {
            $parts[] = 'Correo autorizador ' . (string) $data['authorized_by_email'];
        }
        if (!empty($data['combo'])) {
            $parts[] = 'Combo ' . (string) $data['combo'];
        }
        if (!empty($data['reason'])) {
            $parts[] = 'Motivo ' . (string) $data['reason'];
        }

        return implode(' · ', $parts);
    }
}

if (!function_exists('test_result_option_pairs')) {
    function test_result_option_pairs(?string $options): array
    {
        $pairs = [];
        foreach (explode(';', (string) $options) as $option) {
            [$value, $label] = array_pad(array_map('trim', explode('=', $option, 2)), 2, null);
            if ($value !== null && $value !== '') {
                $pairs[$value] = $label ?: $value;
            }
        }

        return $pairs;
    }
}

if (!function_exists('test_result_choice_marker')) {
    function test_result_choice_marker(int $index): string
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

if (!function_exists('test_result_selected_values')) {
    function test_result_selected_values(array $item): array
    {
        $answer = trim((string) ($item['answer_value'] ?? ''));
        if ($answer === '') {
            return [];
        }

        if (($item['item_type'] ?? '') === 'multiple_choice') {
            return array_values(array_filter(array_map('trim', explode(',', $answer)), static fn(string $value): bool => $value !== ''));
        }

        return [$answer];
    }
}

if (!function_exists('test_result_normalize_choice_value')) {
    function test_result_normalize_choice_value(string $value): string
    {
        $value = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        return trim((string) (preg_replace('/\s+/u', ' ', $value) ?? $value));
    }
}

if (!function_exists('test_result_uses_scale_columns')) {
    function test_result_uses_scale_columns(array $session): bool
    {
        return in_array((string) ($session['instrument_code'] ?? ''), ['ticl_barratt', 'ipip_16pf', 'riasec'], true);
    }
}

if (!function_exists('test_result_scale_value')) {
    function test_result_scale_value(array $session, array $row): string
    {
        $fields = (string) ($session['instrument_code'] ?? '') === 'ticl_barratt'
            ? ['raw_score', 'score', 'adjusted_score', 'transformed_score']
            : ['transformed_score', 'adjusted_score', 'raw_score', 'score'];

        foreach ($fields as $field) {
            if (!array_key_exists($field, $row) || $row[$field] === null || $row[$field] === '') {
                continue;
            }

            return is_numeric($row[$field])
                ? (string) round((float) $row[$field], 2)
                : (string) $row[$field];
        }

        return '-';
    }
}

if (!function_exists('test_result_prompt_html')) {
    function test_result_prompt_html(string $prompt, ?string $legacyImageUrl = null): string
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

        return test_result_sanitize_prompt_html($html);
    }
}

if (!function_exists('test_result_sanitize_prompt_html')) {
    function test_result_sanitize_prompt_html(string $html): string
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

if (!function_exists('test_result_answer_detail_html')) {
    function test_result_answer_detail_html(array $items): void
    {
        if (!$items) {
            echo '<div class="alert alert-light border mb-0" data-inline-alert>No hay preguntas directas asociadas a este resultado. Puede tratarse de una escala derivada o calculada desde otras escalas.</div>';
            return;
        }
        ?>
        <div class="answered-review-list mt-3">
            <?php foreach ($items as $index => $item): ?>
                <?php
                $options = test_result_option_pairs($item['options'] ?? '');
                $selectedValues = test_result_selected_values($item);
                if (($item['item_type'] ?? '') === 'multiple_choice' && count($selectedValues) > 1) {
                    $normalizedAnswer = test_result_normalize_choice_value((string) ($item['answer_value'] ?? ''));
                    foreach ($options as $value => $label) {
                        if ($normalizedAnswer === test_result_normalize_choice_value((string) $value)
                            || $normalizedAnswer === test_result_normalize_choice_value((string) $label)) {
                            $selectedValues = [(string) ($item['answer_value'] ?? '')];
                            break;
                        }
                    }
                }
                $selectedSet = array_fill_keys(array_map('test_result_normalize_choice_value', $selectedValues), true);
                $itemType = (string) ($item['item_type'] ?? '');
                ?>
                <article class="test-question-card">
                    <div class="test-question-head">
                        <span class="test-question-number"><?= str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT) ?></span>
                        <div>
                            <?php if (!empty($item['scale_name'])): ?>
                                <div class="test-question-scale"><?= e((string) $item['scale_name']) ?></div>
                            <?php endif; ?>
                            <div class="test-question-content">
                                <?= test_result_prompt_html((string) ($item['prompt'] ?? ''), $item['image_url'] ?? null) ?>
                            </div>
                        </div>
                    </div>

                    <?php if (in_array($itemType, ['likert', 'single_choice', 'multiple_choice'], true) && $options): ?>
                        <div class="test-choice-grid">
                            <?php $choiceIndex = 0; ?>
                            <?php $hasVisibleSelection = false; ?>
                            <?php foreach ($options as $value => $label): ?>
                                <?php
                                $isSelected = isset($selectedSet[test_result_normalize_choice_value((string) $value)])
                                    || isset($selectedSet[test_result_normalize_choice_value((string) $label)]);
                                $hasVisibleSelection = $hasVisibleSelection || $isSelected;
                                $choiceMarker = test_result_choice_marker($choiceIndex++);
                                ?>
                                <label class="test-choice-card <?= $isSelected ? 'is-selected' : '' ?>">
                                    <input type="<?= $itemType === 'multiple_choice' ? 'checkbox' : 'radio' ?>" disabled <?= $isSelected ? 'checked' : '' ?>>
                                    <span class="test-choice-mark"><?= e($choiceMarker) ?></span>
                                    <span><?= e((string) $label) ?></span>
                                </label>
                            <?php endforeach; ?>
                            <?php if (!$selectedValues): ?>
                                <div class="answered-free-response">
                                    <span class="text-muted small">Respuesta</span>
                                    <strong>Sin respuesta</strong>
                                </div>
                            <?php elseif (!$hasVisibleSelection): ?>
                                <div class="answered-free-response">
                                    <span class="text-muted small">Respuesta registrada</span>
                                    <strong><?= e(implode(', ', $selectedValues)) ?></strong>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <div class="answered-free-response">
                            <span class="text-muted small">Respuesta</span>
                            <strong><?= $selectedValues ? e(implode(', ', $selectedValues)) : 'Sin respuesta' ?></strong>
                        </div>
                    <?php endif; ?>
                </article>
            <?php endforeach; ?>
        </div>
        <?php
    }
}
?>

<?php
$summary = $summary ?? [];
$answerDetailAvailable = !empty($answeredItems);
$usesScaleColumns = test_result_uses_scale_columns($session);
?>

<?php if (is_array($riasecResult) && !empty($riasecResult['scales'])): ?>
    <section class="card content-panel mb-4">
        <div class="d-flex flex-wrap align-items-start justify-content-between gap-3 mb-3">
            <div>
                <p class="text-uppercase text-primary fw-bold small mb-1">Perfil RIASEC</p>
                <h2 class="h5 fw-bold mb-1">Codigo dominante <?= e((string) ($riasecResult['code'] ?? '')) ?></h2>
                <p class="text-muted mb-0"><?= e((string) ($riasecResult['disclaimer'] ?? '')) ?></p>
            </div>
            <span class="badge text-bg-light border fs-6"><?= e((string) ($riasecResult['dominant_code'] ?? '')) ?></span>
        </div>

        <div class="row g-3">
            <div class="col-12 col-lg-7">
                <div class="vstack gap-2">
                    <?php foreach ($riasecResult['scales'] as $scale): ?>
                        <?php $percentage = max(0, min(100, (float) ($scale['percentage'] ?? 0))); ?>
                        <div>
                            <div class="d-flex justify-content-between gap-2 small mb-1">
                                <span><strong><?= e((string) ($scale['code'] ?? '')) ?></strong> <?= e((string) ($scale['name'] ?? '')) ?></span>
                                <span><?= e((string) round((float) ($scale['score'] ?? 0), 2)) ?> / <?= e((string) ($scale['max_score'] ?? 7)) ?></span>
                            </div>
                            <div class="progress" role="progressbar" aria-valuenow="<?= e((string) $percentage) ?>" aria-valuemin="0" aria-valuemax="100" style="height: 0.65rem;">
                                <div class="progress-bar" style="width: <?= e((string) $percentage) ?>%;"></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="col-12 col-lg-5">
                <?php if (!empty($riasecResult['recommendations'])): ?>
                    <div class="list-group list-group-flush border rounded">
                        <?php foreach ($riasecResult['recommendations'] as $recommendation): ?>
                            <div class="list-group-item">
                                <div class="d-flex align-items-center gap-2 mb-1">
                                    <span class="badge text-bg-primary"><?= e((string) ($recommendation['category_code'] ?? '')) ?></span>
                                    <strong><?= e((string) ($recommendation['title'] ?? '')) ?></strong>
                                </div>
                                <p class="text-muted small mb-1"><?= e((string) ($recommendation['description'] ?? '')) ?></p>
                                <?php if (!empty($recommendation['pathways'])): ?>
                                    <div class="small"><?= e((string) $recommendation['pathways']) ?></div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="alert alert-light border mb-0">No hay recomendaciones cargadas para este perfil. El puntaje RIASEC queda disponible para interpretacion manual.</div>
                <?php endif; ?>
            </div>
        </div>
    </section>
<?php endif; ?>

<?php if ($answerDetailAvailable): ?>
    <template id="answeredResponsesDrawer">
        <div class="drawer-detail-heading">
            <p class="text-uppercase text-primary fw-bold small mb-1">Respuestas registradas</p>
            <h3 class="h5 fw-bold mb-1"><?= e((string) $session['instrument_name']) ?></h3>
            <p class="text-muted mb-0">Preguntas de la evaluacion y respuesta seleccionada o ingresada por el usuario.</p>
        </div>

        <?php test_result_answer_detail_html($answeredItems); ?>

        <div class="import-drawer-actions">
            <button class="btn btn-outline-secondary" type="button" data-app-drawer-close>Cerrar</button>
        </div>
    </template>
<?php endif; ?>

<section class="card content-panel">
    <?php if (!$summary): ?>
        <p class="text-muted mb-0">Aun no hay resultado disponible para esta evaluacion.</p>
    <?php else: ?>
        <?php if ($answerDetailAvailable): ?>
            <div class="d-flex justify-content-end mb-3">
                <button
                    class="btn btn-outline-primary"
                    type="button"
                    data-import-row-drawer="#answeredResponsesDrawer"
                    data-import-row-title="Detalle - <?= e((string) $session['instrument_name']) ?>"
                >
                    <i class="bi bi-ui-checks-grid me-1"></i> Ver Respuestas
                </button>
            </div>
        <?php endif; ?>
        <?php if ($age !== ''): ?>
            <div class="d-flex flex-wrap gap-2 mb-3">
                <span class="badge text-bg-light border">Edad: <?= e($age) ?></span>
            </div>
        <?php endif; ?>
        <?php if ($usesScaleColumns): ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle app-table">
                    <thead>
                        <tr>
                            <?php foreach ($summary as $row): ?>
                                <th>
                                    <div><?= e((string) ($row['name'] ?? $row['scale'] ?? 'Escala')) ?></div>
                                    <div class="text-muted small"><code><?= e((string) ($row['scale'] ?? 'general')) ?></code></div>
                                    <?php if ((string) ($session['instrument_code'] ?? '') === 'ticl_barratt'): ?>
                                        <div class="text-muted small">Puntaje bruto</div>
                                    <?php endif; ?>
                                </th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <?php foreach ($summary as $row): ?>
                                <td class="fw-semibold"><?= e(test_result_scale_value($session, $row)) ?></td>
                            <?php endforeach; ?>
                        </tr>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle app-table">
                    <thead>
                        <tr>
                            <th>Escala</th>
                            <th>Respuestas Correctas</th>
                            <th>Respondidos</th>
                            <th>Puntaje bruto</th>
                            <th>Puntaje ajustado</th>
                            <th>Transformado</th>
                            <th>Percentil</th>
                            <th>Clasificacion</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($summary as $row): ?>
                            <?php
                            $metricKey = !empty($row['scale_id']) ? 'id:' . (int) $row['scale_id'] : 'scale:' . (string) ($row['scale'] ?? 'general');
                            $rowMetrics = $scaleMetrics[$metricKey] ?? $scaleMetrics['scale:' . (string) ($row['scale'] ?? 'general')] ?? [];
                            $answeredCount = (int) ($rowMetrics['answered_items'] ?? $row['answered'] ?? 0);
                            $totalItems = (int) ($rowMetrics['total_items'] ?? $answeredCount);
                            $correctItems = $rowMetrics['correct_items'] ?? null;
                            ?>
                            <tr>
                                <td>
                                    <div class="fw-semibold"><?= e($row['name'] ?? $row['scale']) ?></div>
                                    <div class="text-muted small"><code><?= e($row['scale']) ?></code></div>
                                    <?php if (!empty($row['adjustments']) && is_array($row['adjustments'])): ?>
                                        <div class="text-muted small mt-1">
                                            <?php foreach ($row['adjustments'] as $adjustment): ?>
                                                <div>
                                                    <?= e($adjustment['label'] ?? 'Ajuste') ?>:
                                                    <?= e((string) ($adjustment['source_key'] ?? 'campo')) ?>
                                                    <?= e((string) ($adjustment['source_value'] ?? '')) ?>,
                                                    <?= e((string) ($adjustment['operation'] ?? 'add')) ?>
                                                    <?= e((string) ($adjustment['value'] ?? 0)) ?>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td><?= $supportsCorrectAnswers && $correctItems !== null ? (int) $correctItems : '-' ?></td>
                                <td><?= (int) $answeredCount ?> / <?= (int) $totalItems ?></td>
                                <td><?= e((string) round((float) ($row['raw_score'] ?? $row['score'] ?? 0), 2)) ?></td>
                                <td><?= array_key_exists('adjusted_score', $row) && $row['adjusted_score'] !== null ? e((string) round((float) $row['adjusted_score'], 2)) : '-' ?></td>
                                <td><?= e((string) ($row['transformed_score'] ?? '-')) ?></td>
                                <td><?= e((string) ($row['percentile'] ?? '-')) ?></td>
                                <td><?= e((string) ($row['classification'] ?? '-')) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</section>

<?php if (has_permission('view_test_results') && ((int) ($session['track_activity_enabled'] ?? 0) === 1 || $activityEvents)): ?>
    <section class="card content-panel mt-4">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
            <div>
                <h2 class="h5 fw-bold mb-1">Actividad durante la evaluacion</h2>
                <p class="text-muted mb-0">Eventos de foco, visibilidad, inactividad, guardado y envio registrados durante la rendicion.</p>
            </div>
            <span class="badge text-bg-light border"><?= count($activityEvents) ?> eventos</span>
        </div>
        <?php if (!$activityEvents): ?>
            <p class="text-muted mb-0">Aun no hay eventos registrados para esta sesion.</p>
        <?php else: ?>
            <div class="activity-list test-activity-timeline">
                <?php foreach ($activityEvents as $event): ?>
                    <?php
                    $eventType = (string) ($event['event_type'] ?? '');
                    $metadataLabel = test_activity_metadata_label($event['metadata'] ?? null);
                    ?>
                    <div class="activity-item">
                        <span class="activity-dot"></span>
                        <div>
                            <div class="d-flex flex-wrap align-items-center gap-2">
                                <strong><?= e(test_activity_label($eventType)) ?><?= test_activity_help_button($eventType) ?></strong>
                                <span class="text-muted small"><?= e((string) $event['created_at']) ?></span>
                            </div>
                            <?php if ($metadataLabel !== ''): ?>
                                <div class="text-muted small"><?= e($metadataLabel) ?></div>
                            <?php endif; ?>
                            <?php if (!empty($event['ip_address'])): ?>
                                <div class="text-muted small">IP <?= e((string) $event['ip_address']) ?></div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>
<?php endif; ?>

<?php if (has_permission('view_test_results') && $mediaEvidences): ?>
    <?php foreach (array_slice($mediaEvidences, 0, -1) as $mediaSegment): $segmentCaptures = array_values(array_filter($screenCaptures, static fn(array $capture): bool => (int) ($capture['evidence_id'] ?? 0) === (int) ($mediaSegment['id'] ?? 0))); ?>
        <section class="card content-panel mt-4"><div class="d-flex justify-content-between gap-2"><h2 class="h5 fw-bold mb-1">Control audiovisual · reapertura <?= (int) ($mediaSegment['segment_number'] ?? 1) ?></h2><span class="badge text-bg-secondary"><?= e(['saved' => 'Video guardado', 'partial' => 'Video parcial', 'failed' => 'Video no guardado', 'uploading' => 'Carga no finalizada'][$mediaSegment['status'] ?? ''] ?? 'Sin estado') ?></span></div><?php if (in_array((string) ($mediaSegment['status'] ?? ''), ['saved', 'partial'], true)): ?><video class="app-evidence-video rounded border mt-2" controls preload="metadata" src="<?= e(route_url('test-session.media-evidence', (int) $session['id']) . '?evidence_id=' . (int) $mediaSegment['id']) ?>"></video><?php endif; ?><?php if ($segmentCaptures): ?><div class="row g-2 mt-2"><?php foreach ($segmentCaptures as $capture): $captureUrl = route_url('test-session.media-screenshot-file', (int) $session['id']) . '?capture_id=' . (int) $capture['id']; ?><div class="col-6 col-md-4"><img class="img-fluid rounded border" loading="lazy" src="<?= e($captureUrl) ?>" alt="Captura <?= (int) $capture['capture_number'] ?>"></div><?php endforeach; ?></div><?php endif; ?></section>
    <?php endforeach; ?>
    <?php $mediaEvidence = end($mediaEvidences); $segmentCaptures = array_values(array_filter($screenCaptures, static fn(array $capture): bool => (int) ($capture['evidence_id'] ?? 0) === (int) ($mediaEvidence['id'] ?? 0))); ?>
    <section class="card content-panel mt-4">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
            <div>
                <h2 class="h5 fw-bold mb-1">Control audiovisual</h2>
                <p class="text-muted mb-0">Evidencia y señales audiovisuales asociadas a la rendicion.</p>
            </div>
            <?php $mediaStatus = (string) ($mediaEvidence['status'] ?? ''); ?>
            <span class="badge <?= $mediaStatus === 'saved' ? 'text-bg-success' : 'text-bg-warning' ?>"><?= e(['saved' => 'Video guardado', 'partial' => 'Video parcial', 'failed' => 'Video no guardado', 'uploading' => 'Carga no finalizada'][$mediaStatus] ?? 'Sin estado') ?></span>
        </div>
        <div class="row g-3 align-items-start">
            <div class="col-12 col-lg-8">
                <?php if (in_array(($mediaEvidence['status'] ?? ''), ['saved', 'partial'], true)): ?>
                    <div class="small fw-semibold mb-2">Grabación <?= (int) ($mediaEvidence['segment_number'] ?? 1) ?></div><video class="app-evidence-video rounded border" controls preload="metadata" src="<?= e(route_url('test-session.media-evidence', (int) $session['id']) . '?evidence_id=' . (int) $mediaEvidence['id']) ?>"></video>
                    <?php if ($segmentCaptures): ?>
                        <h3 class="h6 fw-bold mt-3">Capturas de pantalla (<?= count($segmentCaptures) ?>)</h3>
                        <div class="row g-2">
                            <?php foreach ($segmentCaptures as $capture): $captureUrl = route_url('test-session.media-screenshot-file', (int) $session['id']) . '?capture_id=' . (int) $capture['id']; ?><div class="col-6 col-md-4"><a href="<?= e($captureUrl) ?>" data-screen-capture-view><img class="img-fluid rounded border" loading="lazy" src="<?= e($captureUrl) ?>" alt="Captura <?= (int) $capture['capture_number'] ?>"><span class="small text-muted d-block mt-1"><?= e((string) ($capture['capture_source'] ?? '')) ?> · <?= e((string) ($capture['captured_at'] ?? '')) ?></span></a></div><?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                    <div class="text-muted small mt-2">Tamaño: <?= e(number_format(((int) ($mediaEvidence['file_size'] ?? 0)) / 1048576, 2, ',', '.')) ?> MB · Duracion: <?= (int) ($mediaEvidence['duration_seconds'] ?? 0) ?> s</div>
                    <?php if ($mediaStatus === 'partial'): ?><div class="alert alert-warning mt-2 mb-0">Evidencia parcial: solo se unieron los fragmentos disponibles hasta el primer faltante.</div><?php endif; ?>
                <?php else: ?>
                    <p class="mb-1">La evidencia no quedo almacenada completamente porque la carga no recibio confirmacion final.</p>
                    <?php if (!empty($mediaEvidence['failure_reason'])): ?><div class="text-muted small">Motivo: <?= e((string) $mediaEvidence['failure_reason']) ?></div><?php endif; ?>
                    <?php if ($segmentCaptures): ?>
                        <h3 class="h6 fw-bold mt-3">Capturas de pantalla (<?= count($segmentCaptures) ?>)</h3>
                        <div class="row g-2">
                            <?php foreach ($screenCaptures as $capture): $captureUrl = route_url('test-session.media-screenshot-file', (int) $session['id']) . '?capture_id=' . (int) $capture['id']; ?><div class="col-6 col-md-4"><a href="<?= e($captureUrl) ?>" data-screen-capture-view><img class="img-fluid rounded border" loading="lazy" src="<?= e($captureUrl) ?>" alt="Captura <?= (int) $capture['capture_number'] ?>"><span class="small text-muted d-block mt-1"><?= e((string) ($capture['capture_source'] ?? '')) ?> · <?= e((string) ($capture['captured_at'] ?? '')) ?></span></a></div><?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                    <?php if (in_array($mediaStatus, ['uploading', 'failed'], true)): ?><form method="post" action="<?= e(route_url('test-session.media-partial', (int) $session['id'])) ?>" class="mt-2" onsubmit="return window.confirm('¿Deseas unir los fragmentos audiovisuales disponibles y ver la evidencia parcial?');"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><button class="btn btn-sm btn-outline-warning" type="submit">Unir fragmentos disponibles y ver video parcial</button></form><?php endif; ?>
                <?php endif; ?>
            </div>
            <div class="col-12 col-lg-4">
                <?php if ($audioVisualRisks): ?>
                    <h3 class="h6 fw-bold">Señales para revision</h3>
                    <div class="activity-list test-activity-timeline">
                        <?php foreach ($audioVisualRisks as $risk): ?>
                            <div class="activity-item"><span class="activity-dot"></span><div><strong><?= e(test_result_audio_visual_risk_label($risk['event_type'] ?? null)) ?></strong><div class="text-muted small"><?= e((string) ($risk['created_at'] ?? '')) ?> · <?= e(['risk' => 'Riesgo', 'attention' => 'Atención', 'info' => 'Información'][(string) ($risk['severity'] ?? '')] ?? 'Atención') ?></div></div></div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="text-muted small">No se registraron señales audiovisuales.</div>
                <?php endif; ?>
            </div>
        </div>
    </section>
<?php endif; ?>
