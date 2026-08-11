<?php
$userResult = $userResult ?? [];
$results = $results ?? [];
$sessionStatusLabels = [
    'assigned' => 'Asignada',
    'in_progress' => 'En curso',
    'completed' => 'Completada',
    'cancelled' => 'Cancelada',
    'expired' => 'Expirada',
];

if (!function_exists('test_result_supports_correct_answers')) {
    function test_result_supports_correct_answers(array $result): bool
    {
        return !empty(($result['resultContext'] ?? [])['supports_correct_answers']);
    }
}

if (!function_exists('test_user_results_uses_scale_columns')) {
    function test_user_results_uses_scale_columns(array $session): bool
    {
        return in_array((string) ($session['instrument_code'] ?? ''), ['ticl_barratt', 'ipip_16pf', 'riasec'], true);
    }
}

if (!function_exists('test_user_results_scale_value')) {
    function test_user_results_scale_value(array $session, array $row): string
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

if (!function_exists('test_user_results_option_pairs')) {
    function test_user_results_option_pairs(?string $options): array
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

if (!function_exists('test_user_results_choice_marker')) {
    function test_user_results_choice_marker(int $index): string
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

if (!function_exists('test_user_results_selected_values')) {
    function test_user_results_selected_values(array $item): array
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

if (!function_exists('test_user_results_prompt_html')) {
    function test_user_results_prompt_html(string $prompt, ?string $legacyImageUrl = null): string
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

        return test_user_results_sanitize_prompt_html($html);
    }
}

if (!function_exists('test_user_results_sanitize_prompt_html')) {
    function test_user_results_sanitize_prompt_html(string $html): string
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

            return '<img src="' . e($src) . '" alt="">';
        }, $clean) ?? '';

        return $clean;
    }
}

if (!function_exists('test_user_results_answer_detail_html')) {
    function test_user_results_answer_detail_html(array $items): void
    {
        if (!$items) {
            echo '<div class="alert alert-light border mb-0" data-inline-alert>No hay preguntas directas asociadas a este resultado. Puede tratarse de una escala derivada o calculada desde otras escalas.</div>';
            return;
        }
        ?>
        <div class="answered-review-list mt-3">
            <?php foreach ($items as $index => $item): ?>
                <?php
                $options = test_user_results_option_pairs($item['options'] ?? '');
                $selectedValues = test_user_results_selected_values($item);
                $selectedSet = array_flip($selectedValues);
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
                                <?= test_user_results_prompt_html((string) ($item['prompt'] ?? ''), $item['image_url'] ?? null) ?>
                            </div>
                        </div>
                    </div>

                    <?php if (in_array($itemType, ['likert', 'single_choice', 'multiple_choice'], true) && $options): ?>
                        <div class="test-choice-grid">
                            <?php $choiceIndex = 0; ?>
                            <?php $hasVisibleSelection = false; ?>
                            <?php foreach ($options as $value => $label): ?>
                                <?php
                                $isSelected = isset($selectedSet[(string) $value]);
                                $hasVisibleSelection = $hasVisibleSelection || $isSelected;
                                ?>
                                <label class="test-choice-card">
                                    <input type="<?= $itemType === 'multiple_choice' ? 'checkbox' : 'radio' ?>" disabled <?= $isSelected ? 'checked' : '' ?>>
                                    <span class="test-choice-mark"><?= e(test_user_results_choice_marker($choiceIndex++)) ?></span>
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
            'block_saved' => 'Bloque guardado',
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
            'audio_visual_recording_interrupted' => 'Control audiovisual interrumpido',
            'audio_visual_recording_recovered' => 'Control audiovisual recuperado',
            'audio_visual_upload_started' => 'Carga de evidencia audiovisual iniciada',
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
            'block_saved' => 'Se guardo un bloque de preguntas en una evaluacion configurada por bloques.',
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

if (!function_exists('test_audio_visual_risk_label')) {
    function test_audio_visual_risk_label(?string $eventType): string
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

if (!function_exists('test_user_results_attention_event_types')) {
    function test_user_results_attention_event_types(): array
    {
        return [
            'tab_hidden',
            'window_blurred',
            'inactive_detected',
            'fullscreen_exited',
            'fullscreen_denied',
            'fullscreen_failed',
            'fullscreen_unavailable',
            'suspicious_key_printscreen',
            'suspicious_key_print',
            'suspicious_key_save',
            'suspicious_key_copy',
            'suspicious_key_devtools',
            'context_menu_blocked',
            'copy_blocked',
            'cut_blocked',
            'paste_blocked',
            'drag_blocked',
            'print_blocked',
        ];
    }
}

if (!function_exists('test_user_results_is_attention_event')) {
    function test_user_results_is_attention_event(string $eventType): bool
    {
        return in_array($eventType, test_user_results_attention_event_types(), true);
    }
}

if (!function_exists('test_user_results_activity_summary')) {
    function test_user_results_activity_summary(array $results): array
    {
        $summary = [
            'total' => 0,
            'evaluations_with_activity' => 0,
            'attention_events' => 0,
            'save_events' => 0,
            'first_at' => '',
            'last_at' => '',
            'by_event' => [],
            'by_instrument' => [],
        ];
        $saveTypes = ['answer_saved', 'answer_changed', 'block_saved'];

        foreach ($results as $result) {
            $session = $result['session'] ?? [];
            $events = $result['activityEvents'] ?? [];
            if ($events) {
                $summary['evaluations_with_activity']++;
            }

            $instrumentName = (string) ($session['instrument_name'] ?? 'Evaluacion');
            foreach ($events as $event) {
                $eventType = (string) ($event['event_type'] ?? '');
                $createdAt = (string) ($event['created_at'] ?? '');

                $summary['total']++;
                $summary['by_event'][$eventType] = ($summary['by_event'][$eventType] ?? 0) + 1;
                $summary['by_instrument'][$instrumentName] = ($summary['by_instrument'][$instrumentName] ?? 0) + 1;

                if (test_user_results_is_attention_event($eventType)) {
                    $summary['attention_events']++;
                }
                if (in_array($eventType, $saveTypes, true)) {
                    $summary['save_events']++;
                }
                if ($createdAt !== '' && ($summary['first_at'] === '' || $createdAt < $summary['first_at'])) {
                    $summary['first_at'] = $createdAt;
                }
                if ($createdAt !== '' && ($summary['last_at'] === '' || $createdAt > $summary['last_at'])) {
                    $summary['last_at'] = $createdAt;
                }
            }
        }

        arsort($summary['by_event']);
        arsort($summary['by_instrument']);

        return $summary;
    }
}

if (!function_exists('test_user_results_activity_rows')) {
    function test_user_results_activity_rows(array $results): array
    {
        $rows = [];

        foreach ($results as $result) {
            $session = $result['session'] ?? [];
            foreach (($result['activityEvents'] ?? []) as $event) {
                $event['instrument_name'] = (string) ($session['instrument_name'] ?? 'Evaluacion');
                $event['instrument_code'] = (string) ($session['instrument_code'] ?? '');
                $rows[] = $event;
            }
        }

        usort($rows, static function (array $a, array $b): int {
            return strcmp((string) ($b['created_at'] ?? ''), (string) ($a['created_at'] ?? ''));
        });

        return $rows;
    }
}

?>

<section class="page-header">
    <div>
        <p class="text-uppercase text-primary fw-bold small mb-1">Resultados usuario</p>
        <h1 class="fw-bold mb-1"><?= e((string) ($userResult['name'] ?? 'Usuario')) ?></h1>
        <p class="text-muted mb-0">
            <?= e((string) ($userResult['email'] ?? '')) ?>
            <?= !empty($userResult['company_name']) ? ' · ' . e((string) $userResult['company_name']) : '' ?>
            <?= trim((string) ($userResult['age'] ?? '')) !== '' ? ' · Edad: ' . e((string) $userResult['age']) : '' ?>
        </p>
    </div>
    <a class="btn btn-back" data-page-back="1" href="<?= e(back_url(has_permission('manage_tests') ? 'tests' : 'dashboard')) ?>"><i class="bi bi-arrow-left me-1"></i> Volver</a>
</section>

<section class="content-panel">
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
        <div>
            <h2 class="h5 fw-bold mb-1">Evaluaciones terminadas</h2>
            <p class="text-muted mb-0">Resultados separados por evaluacion, con resumen de actividad y detalle de acciones por instrumento.</p>
        </div>
        <span class="badge text-bg-success"><?= count($results) ?> terminadas</span>
    </div>

    <div class="user-results-stack">
        <?php foreach ($results as $resultIndex => $result): ?>
            <?php
            $session = $result['session'] ?? [];
            $summary = $result['summary'] ?? [];
            $resultContext = $result['resultContext'] ?? [];
            $answeredItemsForResult = $result['answeredItems'] ?? [];
            $scaleMetrics = $resultContext['scale_metrics'] ?? [];
            $supportsCorrectAnswers = test_result_supports_correct_answers($result);
            $usesScaleColumns = test_user_results_uses_scale_columns($session);
            $activitySummary = test_user_results_activity_summary([$result]);
            $activityRows = test_user_results_activity_rows([$result]);
            $mediaEvidence = is_array($result['mediaEvidence'] ?? null) ? $result['mediaEvidence'] : null;
            $audioVisualRisks = is_array($result['audioVisualRisks'] ?? null) ? $result['audioVisualRisks'] : [];
            $audioVisualRiskCounts = [];
            foreach ($audioVisualRisks as $risk) {
                $riskLabel = test_audio_visual_risk_label($risk['event_type'] ?? null);
                $audioVisualRiskCounts[$riskLabel] = ($audioVisualRiskCounts[$riskLabel] ?? 0) + 1;
            }
            $drawerId = 'user-activity-detail-drawer-' . ((int) ($session['id'] ?? 0) ?: ((int) $resultIndex + 1));
            $answerDrawerId = 'user-answer-detail-drawer-' . ((int) ($session['id'] ?? 0) ?: ((int) $resultIndex + 1));
            $resultExportUrl = !empty($session['id']) ? route_url('test-session.result-export', (int) $session['id']) : '#';
            ?>
            <article class="user-result-card">
                <div class="user-result-card-header">
                    <div>
                        <h3 class="h6 fw-bold mb-1"><?= e((string) ($session['instrument_name'] ?? 'Evaluacion')) ?></h3>
                        <p class="text-muted small mb-0">
                            <?= e((string) ($session['instrument_code'] ?? '')) ?>
                            <?= !empty($session['completed_at']) ? ' · Finalizada: ' . e((string) $session['completed_at']) : '' ?>
                        </p>
                    </div>
                    <span class="badge <?= ($session['status'] ?? '') === 'completed' ? 'text-bg-success' : 'text-bg-secondary' ?>">
                        <?= e($sessionStatusLabels[(string) ($session['status'] ?? 'completed')] ?? labelize((string) ($session['status'] ?? 'completed'))) ?>
                    </span>
                </div>

                <div class="row g-3">
                    <div class="col-md-3">
                        <div class="result-metric">
                            <span class="result-metric-label">Eventos registrados</span>
                            <strong><?= (int) $activitySummary['total'] ?></strong>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="result-metric">
                            <span class="result-metric-label">Evaluaciones con actividad</span>
                            <strong><?= (int) $activitySummary['evaluations_with_activity'] ?></strong>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="result-metric">
                            <span class="result-metric-label">
                                Eventos de <span class="badge text-bg-warning activity-attention-badge">Atencion</span><?= test_activity_help_button('attention_events', 'Eventos de atencion') ?>
                            </span>
                            <strong><?= (int) $activitySummary['attention_events'] ?></strong>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="result-metric">
                            <span class="result-metric-label">Guardados/cambios</span>
                            <strong><?= (int) $activitySummary['save_events'] ?></strong>
                        </div>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table align-middle app-table">
                        <thead>
                            <tr>
                                <th>Primer evento</th>
                                <th>Ultimo evento</th>
                                <th>Eventos por tipo <span class="text-muted small fw-normal">(nombre y cantidad)</span></th>
                                <th>Ver detalle de acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td><?= e((string) ($activitySummary['first_at'] ?: '-')) ?></td>
                                <td><?= e((string) ($activitySummary['last_at'] ?: '-')) ?></td>
                                <td>
                                    <?php if (!$activitySummary['by_event']): ?>
                                        -
                                    <?php else: ?>
                                        <?php foreach (array_slice($activitySummary['by_event'], 0, 5, true) as $eventType => $count): ?>
                                            <span class="badge text-bg-light border me-1 mb-1"><?= e(test_activity_label((string) $eventType)) ?>: <?= (int) $count ?><?= test_activity_help_button((string) $eventType) ?></span>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <button
                                        class="btn btn-sm btn-outline-primary"
                                        type="button"
                                        data-import-row-drawer="#<?= e($drawerId) ?>"
                                        data-import-row-title="Detalle de acciones - <?= e((string) ($session['instrument_name'] ?? 'Evaluacion')) ?>"
                                    >
                                        <i class="bi bi-list-check me-1"></i> Ver detalle
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <?php if ($mediaEvidence): ?>
                    <section class="border rounded p-3 mt-3">
                        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-2">
                            <div>
                                <h4 class="h6 fw-bold mb-1">Control audiovisual</h4>
                                <p class="text-muted small mb-0">Evidencia audiovisual y señales registradas para revisión administrativa.</p>
                            </div>
                            <?php $mediaStatus = (string) ($mediaEvidence['status'] ?? ''); ?>
                            <span class="badge <?= $mediaStatus === 'saved' ? 'text-bg-success' : 'text-bg-warning' ?>">
                                <?= e(['saved' => 'Video guardado', 'failed' => 'Video no guardado', 'uploading' => 'Carga no finalizada'][$mediaStatus] ?? 'Sin estado') ?>
                            </span>
                        </div>
                        <div class="row g-3 align-items-start">
                            <div class="col-12 col-lg-8">
                                <?php if ($mediaStatus === 'saved'): ?>
                                    <video class="w-100 rounded border" controls preload="metadata" src="<?= e(route_url('test-session.media-evidence', (int) ($session['id'] ?? 0))) ?>"></video>
                                    <div class="text-muted small mt-2">Tamaño: <?= e(number_format(((int) ($mediaEvidence['file_size'] ?? 0)) / 1048576, 2, ',', '.')) ?> MB · Duración: <?= (int) ($mediaEvidence['duration_seconds'] ?? 0) ?> s</div>
                                <?php else: ?>
                                    <div class="text-muted small">El video no quedó disponible para reproducción porque la carga no recibió confirmación final.</div>
                                    <?php if (!empty($mediaEvidence['failure_reason'])): ?>
                                        <div class="text-muted small">Motivo: <?= e((string) $mediaEvidence['failure_reason']) ?></div>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </div>
                            <div class="col-12 col-lg-4">
                                <?php if ($audioVisualRisks): ?>
                                    <strong class="small">Señales audiovisuales para revisión</strong>
                                    <div class="text-muted small">Cada etiqueta agrupa la cantidad de veces que se registró esa señal durante la evaluación.</div>
                                    <div class="text-muted small mt-1">
                                        <?php foreach ($audioVisualRiskCounts as $riskLabel => $riskCount): ?>
                                            <span class="badge text-bg-light border me-1 mb-1"><?= e($riskLabel) ?>: <?= (int) $riskCount ?></span>
                                        <?php endforeach; ?>
                                    </div>
                                <?php else: ?>
                                    <div class="text-muted small">No se registraron señales audiovisuales.</div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </section>
                <?php endif; ?>

                <template id="<?= e($drawerId) ?>">
                    <div class="drawer-detail-heading">
                        <p class="text-uppercase text-primary fw-bold small mb-1">Registro de actividades</p>
                        <h3 class="h5 fw-bold mb-1">Detalle de acciones</h3>
                        <p class="text-muted mb-0"><?= e((string) ($session['instrument_name'] ?? 'Evaluacion')) ?></p>
                    </div>

                    <?php if (!$activityRows): ?>
                        <p class="text-muted mb-0">Sin actividad registrada para esta evaluacion.</p>
                    <?php else: ?>
                        <div class="table-responsive mt-3">
                            <table class="table align-middle app-table">
                                <thead>
                                    <tr>
                                        <th>Evento</th>
                                        <th>Fecha</th>
                                        <th>Detalle</th>
                                        <th>IP</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($activityRows as $event): ?>
                                        <?php
                                        $eventType = (string) ($event['event_type'] ?? '');
                                        $metadataLabel = test_activity_metadata_label($event['metadata'] ?? null);
                                        $isAttentionEvent = test_user_results_is_attention_event($eventType);
                                        ?>
                                        <tr>
                                            <td class="fw-semibold">
                                                <div class="activity-event-title">
                                                    <span><?= e(test_activity_label($eventType)) ?><?= test_activity_help_button($eventType) ?></span>
                                                    <?php if ($isAttentionEvent): ?>
                                                        <span class="badge text-bg-warning activity-attention-badge">Atencion</span>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                            <td><?= e((string) ($event['created_at'] ?? '')) ?></td>
                                            <td><?= $metadataLabel !== '' ? e($metadataLabel) : '-' ?></td>
                                            <td><?= e((string) ($event['ip_address'] ?? '-')) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>

                    <div class="import-drawer-actions">
                        <button class="btn btn-outline-secondary" type="button" data-app-drawer-close>Cerrar</button>
                    </div>
                </template>

                <template id="<?= e($answerDrawerId) ?>">
                    <div class="drawer-detail-heading">
                        <p class="text-uppercase text-primary fw-bold small mb-1">Detalle de respuestas</p>
                        <h3 class="h5 fw-bold mb-1"><?= e((string) ($session['instrument_name'] ?? 'Evaluacion')) ?></h3>
                        <p class="text-muted mb-0">Evaluacion completa con la respuesta seleccionada o ingresada por el usuario.</p>
                    </div>

                    <?php test_user_results_answer_detail_html($answeredItemsForResult); ?>

                    <div class="import-drawer-actions">
                        <button class="btn btn-outline-secondary" type="button" data-app-drawer-close>Cerrar</button>
                    </div>
                </template>

                <?php if (!$summary): ?>
                    <p class="text-muted mb-0">No hay resumen calculado para esta evaluacion.</p>
                <?php else: ?>
                    <div class="d-flex flex-wrap justify-content-end gap-2 mb-3">
                        <a class="btn btn-outline-success" href="<?= e($resultExportUrl) ?>">
                            <i class="bi bi-file-earmark-excel me-1"></i> Exportar Excel
                        </a>
                        <button
                            class="btn btn-outline-primary"
                            type="button"
                            data-import-row-drawer="#<?= e($answerDrawerId) ?>"
                            data-import-row-title="Detalle - <?= e((string) ($session['instrument_name'] ?? 'Evaluacion')) ?>"
                        >
                            <i class="bi bi-ui-checks-grid me-1"></i> Ver Respuestas
                        </button>
                    </div>
                <?php endif; ?>

                <?php if ($summary && $usesScaleColumns): ?>
                    <div class="table-responsive">
                        <table class="table align-middle app-table">
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
                                        <td class="fw-semibold"><?= e(test_user_results_scale_value($session, $row)) ?></td>
                                    <?php endforeach; ?>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                <?php elseif ($summary): ?>
                    <div class="table-responsive">
                        <table class="table align-middle app-table">
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
                                            <div class="text-muted small"><code><?= e((string) ($row['scale'] ?? 'general')) ?></code></div>
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
            </article>
        <?php endforeach; ?>
    </div>
</section>
