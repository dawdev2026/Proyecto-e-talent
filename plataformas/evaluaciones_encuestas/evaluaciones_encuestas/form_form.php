<?php
$isSurvey = ($form['form_type'] ?? 'assessment') === 'survey';
$isCompanyAdmin = (bool) ($isCompanyAdmin ?? false);
$lockedFormType = $lockedFormType ?? ($form['form_type'] ?? 'assessment');
$backRoute = $isSurvey ? 'evaluation-surveys.surveys' : 'evaluation-surveys.assessments';
$helpIcon = static function (string $message): string {
    return '<span class="app-help-popover" role="button" tabindex="0" data-app-help-popover data-bs-toggle="popover" data-bs-trigger="hover focus" data-bs-placement="top" data-bs-content="' . e($message) . '" aria-label="Ayuda"><i class="bi bi-question-circle"></i></span>';
};
$questionPreviewText = static function ($value): string {
    $text = html_entity_decode(strip_tags((string) $value), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $text = preg_replace('/\s+/u', ' ', trim($text));
    return $text !== '' ? $text : 'Sin enunciado';
};
$nonScoredQuestionTypes = ['text', 'likert', 'nps', 'rating', 'matrix'];
?>
<section class="page-header" data-page-back-url="<?= e(route_url($backRoute)) ?>">
    <div>
        <p class="dashboard-kicker mb-2">Evaluaciones y encuestas</p>
        <h1 class="fw-bold mb-1"><?= $formId > 0 ? 'Editar formulario' : 'Nuevo formulario' ?></h1>
        <p class="text-muted mb-0">Configura un formulario reutilizable e independiente.</p>
    </div>
    <?php if ($formId > 0): ?>
        <div class="page-header-actions">
            <a class="btn btn-sm btn-outline-secondary" href="<?= e(route_url('evaluation-surveys.form.preview', (int) $formId)) ?>">
                <i class="bi bi-eye me-1"></i> Vista previa
            </a>
        </div>
    <?php endif; ?>
</section>

<section class="card content-panel">
    <form method="post" class="row g-4 app-form-stack needs-validation" novalidate>
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="form_type" value="<?= e($lockedFormType) ?>">
        <div class="col-12"><div class="evaluation-form-section-heading"><span class="evaluation-form-section-number">1</span><div><h2>Identificación del formulario</h2><p>Define el nombre, objetivo e instrucciones que verá la persona evaluada.</p></div></div></div>
        <div class="col-12 col-lg-4">
            <label class="form-label d-inline-flex align-items-center gap-1">
                Tipo
                <?= $helpIcon('Define si el formulario calcula nota de aprobación o si registra respuestas de encuesta.') ?>
            </label>
            <div class="form-control form-control-lg bg-body-tertiary"><?= e($formTypes[$lockedFormType] ?? $lockedFormType) ?></div>
        </div>
        <div class="col-12 col-lg-8">
            <label class="form-label d-inline-flex align-items-center gap-1" for="title">
                Título
                <?= $helpIcon('Nombre visible para administradores y usuarios cuando deban responder o revisar el formulario.') ?>
            </label>
            <input id="title" class="form-control form-control-lg" name="title" value="<?= e($form['title'] ?? '') ?>" maxlength="180" required>
        </div>
        <div class="col-12">
            <label class="form-label d-inline-flex align-items-center gap-1" for="description">
                Descripción
                <?= $helpIcon('Resumen interno o contextual del formulario. Sirve para identificar su objetivo en listados y administración.') ?>
            </label>
            <textarea id="description" class="form-control" name="description" rows="4" data-evaluation-richtext-editor><?= e($form['description'] ?? '') ?></textarea>
        </div>
        <div class="col-12">
            <label class="form-label d-inline-flex align-items-center gap-1" for="instructions">
                Instrucciones
                <?= $helpIcon($isSurvey ? 'Texto que vera el usuario antes de responder. Usa este campo para explicar objetivo y confidencialidad de la encuesta.' : 'Texto que vera el usuario antes de responder. Usa este campo para explicar reglas, tiempo, intentos o condiciones.') ?>
            </label>
            <textarea id="instructions" class="form-control" name="instructions" rows="5" data-evaluation-richtext-editor><?= e($form['instructions'] ?? '') ?></textarea>
        </div>
        <div class="col-12"><div class="evaluation-form-section-heading"><span class="evaluation-form-section-number">2</span><div><h2>Disponibilidad y tiempo</h2><p>Configura cuándo puede responderse, el temporizador y el orden de presentación.</p></div></div></div>
        <div class="col-12 col-md-4">
            <label class="form-label d-inline-flex align-items-center gap-1" for="status">
                Estado
                <?= status_help_button('Estados del formulario', "• Borrador: está en configuración.\n• Activo: habilitado para asignaciones, sujeto al proceso.\n• Inactivo: no admite nuevas asignaciones, pero conserva los intentos y el historial existentes.") ?>
            </label>
            <select id="status" class="form-select" name="status" required>
                <?php foreach ($statuses as $key => $label): ?>
                    <option value="<?= e($key) ?>" <?= ($form['status'] ?? 'draft') === $key ? 'selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <?php if (!$isSurvey && !$isCompanyAdmin): ?>
            <div class="col-12"><div class="evaluation-form-section-heading evaluation-form-section-heading-control"><span class="evaluation-form-section-number">3</span><div><h2>Control de la rendición</h2><p>Selecciona el nivel de supervisión. Las reglas audiovisuales aparecerán solo cuando correspondan.</p></div></div></div>
            <div class="col-12 col-md-4">
                <label class="form-label d-inline-flex align-items-center gap-1" for="control_mode">
                    Modo de control durante la evaluación
                    <?= $helpIcon('Registra actividad, solicita pantalla completa o activa cámara y micrófono con consentimiento explícito durante la rendición.') ?>
                </label>
                <select id="control_mode" class="form-select" name="control_mode">
                    <?php foreach (EvaluationSurveyFormModel::CONTROL_MODES as $key => $label): ?>
                        <option value="<?= e($key) ?>" <?= ($form['control_mode'] ?? 'off') === $key ? 'selected' : '' ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
                <div class="form-text">El modo audiovisual solicita cámara, micrófono y captura visual con consentimiento explícito.</div>
            </div>
            <div class="col-12 col-md-4 evaluation-audio-visual-field">
                <label class="form-label d-inline-flex align-items-center gap-1" for="audio_visual_upload_failure_policy">Falla de carga audiovisual <?= $helpIcon('Define qué debe ocurrir si un fragmento audiovisual no puede enviarse: continuar registrando la incidencia, reintentar una vez o bloquear la entrega.') ?></label>
                <select id="audio_visual_upload_failure_policy" class="form-select" name="audio_visual_upload_failure_policy">
                    <?php foreach (EvaluationSurveyFormModel::AUDIO_VISUAL_UPLOAD_FAILURE_POLICIES as $key => $label): ?><option value="<?= e($key) ?>" <?= ($form['audio_visual_upload_failure_policy'] ?? 'continue') === $key ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?>
                </select>
            </div>
            <div class="col-12 col-md-4 evaluation-audio-visual-field">
                <label class="form-label d-inline-flex align-items-center gap-1" for="audio_visual_interruption_policy">Interrupción de cámara/micrófono/captura <?= $helpIcon('Define la respuesta cuando la cámara, el micrófono o la captura visual se silencian, pierden permisos o dejan de estar disponibles durante la evaluación.') ?></label>
                <select id="audio_visual_interruption_policy" class="form-select" name="audio_visual_interruption_policy">
                    <?php foreach (EvaluationSurveyFormModel::AUDIO_VISUAL_INTERRUPTION_POLICIES as $key => $label): ?><option value="<?= e($key) ?>" <?= ($form['audio_visual_interruption_policy'] ?? 'pause') === $key ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?>
                </select>
            </div>
            <div class="col-12 col-md-4 evaluation-audio-visual-field">
                <label class="form-label d-inline-flex align-items-center gap-1" for="audio_visual_permission_policy">Permisos de dispositivos <?= $helpIcon('Define qué ocurre si la persona rechaza o revoca el permiso de cámara, micrófono o captura visual antes o durante la evaluación.') ?></label>
                <select id="audio_visual_permission_policy" class="form-select" name="audio_visual_permission_policy">
                    <?php foreach (EvaluationSurveyFormModel::AUDIO_VISUAL_PERMISSION_POLICIES as $key => $label): ?><option value="<?= e($key) ?>" <?= ($form['audio_visual_permission_policy'] ?? 'pause') === $key ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?>
                </select>
            </div>
            <div class="col-12 col-md-4 evaluation-audio-visual-field">
                <label class="form-label d-inline-flex align-items-center gap-1" for="audio_visual_voice_policy">Detección preliminar de voz <?= $helpIcon('Analiza señales de audio en el navegador para identificar posibles voces adicionales. Es una alerta técnica y no constituye una conclusión por sí sola.') ?></label>
                <select id="audio_visual_voice_policy" class="form-select" name="audio_visual_voice_policy">
                    <?php foreach (EvaluationSurveyFormModel::AUDIO_VISUAL_VOICE_POLICIES as $key => $label): ?><option value="<?= e($key) ?>" <?= ($form['audio_visual_voice_policy'] ?? 'warn') === $key ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?>
                </select>
            </div>
            <div class="col-12 col-md-4 evaluation-audio-visual-field">
                <label class="form-label d-inline-flex align-items-center gap-1" for="audio_visual_quality_profile">Calidad de captura <?= $helpIcon('Define el equilibrio entre nitidez audiovisual, consumo de datos y tamaño final de la evidencia.') ?></label>
                <select id="audio_visual_quality_profile" class="form-select" name="audio_visual_quality_profile">
                    <?php foreach (EvaluationSurveyFormModel::AUDIO_VISUAL_QUALITY_PROFILES as $key => $label): ?><option value="<?= e($key) ?>" <?= ($form['audio_visual_quality_profile'] ?? 'economical') === $key ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?>
                </select>
            </div>
            <div class="col-12 evaluation-audio-visual-field">
                <div class="evaluation-audio-visual-rules" data-evaluation-audio-rules>
                    <div class="d-flex align-items-start gap-2 mb-3"><i class="bi bi-shield-check fs-4" aria-hidden="true"></i><div><h3>Reglas del control audiovisual</h3><p>Se aplicarán durante toda la evaluación y las incidencias quedarán registradas para revisión autorizada.</p></div></div>
                    <div class="row g-3">
                        <div class="col-12 col-md-6 col-xl-4"><div class="evaluation-audio-rule"><strong>1. Cámara y micrófono <?= $helpIcon('La captura requiere consentimiento explícito y permisos activos del navegador antes de iniciar.') ?></strong><span>Se solicitará consentimiento y permisos antes de iniciar.</span></div></div>
                        <div class="col-12 col-md-6 col-xl-4"><div class="evaluation-audio-rule"><strong>2. Interrupciones <?= $helpIcon('Las interrupciones quedan registradas y se aplica la política configurada para pausar, continuar o bloquear.') ?></strong><span>Se aplicará la política seleccionada si un dispositivo se interrumpe.</span></div></div>
                        <div class="col-12 col-md-6 col-xl-4"><div class="evaluation-audio-rule"><strong>3. Múltiples voces <?= $helpIcon('La detección es preliminar, puede generar falsos positivos y debe ser revisada junto con el resto de la evidencia.') ?></strong><span>La detección es preliminar y genera eventos de atención.</span></div></div>
                        <div class="col-12 col-md-6 col-xl-4"><div class="evaluation-audio-rule"><strong>4. Carga por fragmentos <?= $helpIcon('La evidencia se divide y transmite progresivamente para disminuir el riesgo de pérdida ante problemas de conexión.') ?></strong><span>La evidencia se transmite progresivamente para reducir pérdidas.</span></div></div>
                        <div class="col-12 col-md-6 col-xl-4"><div class="evaluation-audio-rule"><strong>5. Retención y acceso <?= $helpIcon('La evidencia se conserva durante el plazo operativo configurado y solo puede ser consultada por perfiles autorizados.') ?></strong><span>La evidencia queda restringida y se elimina según la política operativa.</span></div></div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
        <div class="col-12"><div class="evaluation-form-section-heading"><span class="evaluation-form-section-number"><?= $isCompanyAdmin ? '3' : '4' ?></span><div><h2>Tiempo y presentación</h2><p>Configura el temporizador y la forma en que se mostrarán las preguntas.</p></div></div></div>
        <div class="col-12 col-md-4">
            <label class="form-label d-inline-flex align-items-center gap-1" for="duration_minutes">
                Temporizador
                <?= $helpIcon('Limite de tiempo en minutos para responder. Si queda en 0, el formulario no tendra temporizador.') ?>
            </label>
            <input id="duration_minutes" class="form-control" type="number" min="0" name="duration_minutes" value="<?= (int) ($form['duration_minutes'] ?? 0) ?>">
        </div>
        <div class="col-12 col-md-4">
            <label class="form-label d-inline-flex align-items-center gap-1" for="question_order_mode">
                Orden de preguntas
                <?= $helpIcon($isSurvey ? 'Ordenadas respeta la grilla de preguntas. Aleatorias mezcla las preguntas al responder la encuesta.' : 'Ordenadas respeta la grilla de preguntas. Aleatorias mezcla las preguntas por intento para reducir respuestas repetidas.') ?>
            </label>
            <select id="question_order_mode" class="form-select" name="question_order_mode">
                <?php foreach ($questionOrderModes as $key => $label): ?>
                    <option value="<?= e($key) ?>" <?= ($form['question_order_mode'] ?? 'ordered') === $key ? 'selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <?php if (!$isSurvey): ?>
            <div class="col-12 col-md-3">
                <label class="form-label d-inline-flex align-items-center gap-1" for="max_attempts">
                    Intentos
                <?= $helpIcon('Cantidad máxima de veces que un usuario puede responder. Cuando exista más de un intento, se conserva la mejor nota.') ?>
                </label>
                <input id="max_attempts" class="form-control" type="number" min="1" name="max_attempts" value="<?= max(1, (int) ($form['max_attempts'] ?? 1)) ?>">
            </div>
            <div class="col-12 col-md-3">
                <label class="form-label d-inline-flex align-items-center gap-1" for="max_score">
                    Nota maxima
                    <?= $helpIcon('Escala maxima de la nota final. La plataforma transforma el puntaje obtenido a esta escala.') ?>
                </label>
                <input id="max_score" class="form-control" type="number" min="1" step="0.01" name="max_score" value="<?= e((string) ($form['max_score'] ?? 100)) ?>">
            </div>
            <div class="col-12 col-md-3">
                <label class="form-label d-inline-flex align-items-center gap-1" for="passing_score">
                    Nota aprobación
                    <?= $helpIcon('Nota minima para marcar la evaluación como aprobada.') ?>
                </label>
                <input id="passing_score" class="form-control" type="number" min="0" step="0.01" name="passing_score" value="<?= e((string) ($form['passing_score'] ?? 70)) ?>">
            </div>
            <div class="col-12 col-md-3">
                <label class="form-label d-inline-flex align-items-center gap-1" for="question_display_limit">
                    Preguntas visibles
                    <?= $helpIcon('Define cuantas preguntas activas se mostraran al responder. Usa 0 para mostrar toda la batería. Si el orden es Ordenadas, se toman las primeras según la grilla; si es Aleatorias, cada intento toma una muestra estable mezclada.') ?>
                </label>
                <input id="question_display_limit" class="form-control" type="number" min="0" name="question_display_limit" value="<?= max(0, (int) ($form['question_display_limit'] ?? 0)) ?>">
            </div>
        <?php endif; ?>
        <div class="col-12"><div class="evaluation-form-section-heading"><span class="evaluation-form-section-number"><?= $isCompanyAdmin ? '4' : '5' ?></span><div><h2>Resultados y disponibilidad</h2><p>Define qué información podrá consultar la persona y cómo queda disponible el formulario.</p></div></div></div>
        <div class="col-12 d-flex flex-wrap gap-4 evaluation-form-switches">
            <?php if (!$isSurvey && !$isCompanyAdmin): ?>
                <div class="form-check form-switch">
                    <input id="show_result_to_user" class="form-check-input" type="checkbox" name="show_result_to_user" <?= (int) ($form['show_result_to_user'] ?? 1) === 1 ? 'checked' : '' ?> data-result-visibility-toggle>
                    <label class="form-check-label d-inline-flex align-items-center gap-1" for="show_result_to_user">
                        Mostrar resultado al usuario
                        <?= $helpIcon('Si esta activo, el usuario podrá ver su nota y estado. Si esta apagado, se ocultan los accesos a resultado.') ?>
                    </label>
                </div>
                <div class="form-check form-switch">
                    <input id="show_correction_to_user" class="form-check-input" type="checkbox" name="show_correction_to_user" <?= (int) ($form['show_correction_to_user'] ?? 0) === 1 ? 'checked' : '' ?>>
                    <label class="form-check-label d-inline-flex align-items-center gap-1" for="show_correction_to_user">
                        Mostrar correccion de la evaluación
                        <?= $helpIcon('Si esta activo, el resultado marcara preguntas correctas e incorrectas y mostrara la respuesta correcta cuando corresponda.') ?>
                    </label>
                </div>
            <?php endif; ?>
            <div class="form-check form-switch">
                <input id="is_required" class="form-check-input" type="checkbox" name="is_required" <?= (int) ($form['is_required'] ?? 0) === 1 ? 'checked' : '' ?>>
                <label class="form-check-label d-inline-flex align-items-center gap-1" for="is_required">
                    Disponible para responder
                    <?= $helpIcon('Si está activo, este formulario queda disponible para responder.') ?>
                </label>
            </div>
        </div>
        <?php if (!$isSurvey && !$isCompanyAdmin): ?>
            <div class="col-12" data-result-display-options>
                <label class="form-label d-inline-flex align-items-center gap-1" for="result_display_mode">
                    Como mostrar resultados
                    <?= $helpIcon('Solo mejor resultado muestra unicamente el intento con mejor nota. Intentos colapsables muestra todos los intentos registrados con el detalle de cada pregunta.') ?>
                </label>
                <select id="result_display_mode" class="form-select" name="result_display_mode">
                    <option value="best_only" <?= ($form['result_display_mode'] ?? 'best_only') === 'best_only' ? 'selected' : '' ?>>
                        Mostrar solo el mejor resultado
                    </option>
                    <option value="collapsible_attempts" <?= ($form['result_display_mode'] ?? 'best_only') === 'collapsible_attempts' ? 'selected' : '' ?>>
                        Intentos colapsables
                    </option>
                </select>
            </div>
        <?php endif; ?>
        <div class="col-12 d-flex flex-wrap gap-2">
            <button class="btn btn-sm btn-primary px-4" type="submit"><i class="bi bi-check2 me-1"></i> Guardar</button>
            <a class="btn btn-sm btn-outline-secondary" href="<?= e(route_url($backRoute)) ?>">Volver</a>
        </div>
    </form>
</section>

<?php if ($formId > 0): ?>
<section class="card content-panel">
    <div class="d-flex flex-column flex-md-row justify-content-between gap-3 mb-3">
        <div>
            <h2 class="h5 fw-bold mb-1">Preguntas</h2>
            <p class="text-muted mb-0"><?= $isSurvey ? 'Define preguntas y criterios para recoger satisfacción del usuario.' : 'Define alternativas y puntajes de la evaluación.' ?></p>
        </div>
        <?php $newQuestionUrl = route_url('evaluation-surveys.question.new', (int) $formId); ?>
        <a
            class="btn btn-sm btn-primary align-self-md-start"
            href="<?= e($newQuestionUrl) ?>"
            data-drawer-url="<?= e($newQuestionUrl . '?drawer=1') ?>"
            data-drawer-title="Nueva pregunta"
            data-drawer-size="lg">
            <i class="bi bi-plus-lg me-1"></i> Nueva pregunta
        </a>
    </div>
    <div class="table-responsive">
        <table
            class="table table-hover align-middle app-table"
            data-question-reorder-table
            data-reorder-url="<?= e(route_url('evaluation-surveys.questions.reorder', (int) $formId)) ?>"
            data-csrf-token="<?= e(csrf_token()) ?>">
            <thead>
                <tr>
                    <th class="text-center text-nowrap"></th>
                    <th>Pregunta</th>
                    <th class="d-none d-md-table-cell">Tipo</th>
                    <th class="text-center text-nowrap">Alternativas</th>
                    <th class="d-none d-lg-table-cell">Correcta</th>
                    <th class="text-end">Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!$questions): ?>
                    <tr><td colspan="6" class="text-muted text-center py-4">Aún no hay preguntas.</td></tr>
                <?php endif; ?>
                <?php foreach ($questions as $question): ?>
                    <?php
                    $questionOptions = array_values($question['options'] ?? []);
                    $correctOptions = array_values(array_filter($questionOptions, static fn(array $option): bool => (float) ($option['score_value'] ?? 0) > 0));
                    $correctLabels = array_map(static fn(array $option): string => trim((string) ($option['option_label'] ?? $option['option_value'] ?? '')), $correctOptions);
                    $questionType = (string) ($question['question_type'] ?? '');
                    $correctAnswer = in_array($questionType, $nonScoredQuestionTypes, true) || $isSurvey
                        ? 'No aplica'
                        : ($correctLabels ? implode(', ', $correctLabels) : 'Sin definir');
                    ?>
                    <tr draggable="false" data-question-row data-question-id="<?= (int) $question['id'] ?>">
                        <td class="text-center">
                            <button class="btn btn-sm btn-outline-secondary" type="button" data-question-drag-handle aria-label="Ordenar pregunta" title="Ordenar pregunta">
                                <i class="bi bi-grip-vertical"></i>
                            </button>
                        </td>
                        <td>
                            <strong><?= e(mb_strimwidth($questionPreviewText($question['question_text'] ?? ''), 0, 100, '...')) ?></strong>
                        </td>
                        <td class="d-none d-md-table-cell"><?= e($questionTypes[$questionType] ?? $questionType) ?></td>
                        <td class="text-center"><?= count($questionOptions) ?></td>
                        <td class="d-none d-lg-table-cell"><span class="small"><?= e($correctAnswer) ?></span></td>
                        <td class="text-end">
                            <div class="btn-group btn-group-sm">
                                <?php $editQuestionUrl = route_url('evaluation-surveys.question.edit', (int) $question['id']); ?>
                                <a
                                    class="btn btn-outline-secondary"
                                    href="<?= e($editQuestionUrl) ?>"
                                    data-drawer-url="<?= e($editQuestionUrl . '?drawer=1') ?>"
                                    data-drawer-title="Editar pregunta"
                                    data-drawer-size="lg">
                                    <i class="bi bi-pencil"></i>
                                </a>
                                <form method="post" action="<?= e(route_url('evaluation-surveys.question.delete', (int) $question['id'])) ?>" data-confirm-submit="Esta acción eliminara la pregunta y sus respuestas asociadas. Deseas continuar?">
                                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                    <button class="btn btn-outline-secondary" type="submit"><i class="bi bi-trash"></i></button>
                                </form>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
<script>
function initEvaluationSurveyQuestionReorder(scope) {
    (scope || document).querySelectorAll('[data-question-reorder-table]').forEach(function (table) {
        if (table.dataset.questionReorderReady === '1') {
            return;
        }
        table.dataset.questionReorderReady = '1';
        var tbody = table.querySelector('tbody');
        var draggingRow = null;
        var originalOrder = [];

        function rows() {
            return Array.prototype.slice.call(tbody.querySelectorAll('[data-question-row]'));
        }

        function currentOrder() {
            return rows().map(function (row) {
                return row.getAttribute('data-question-id');
            }).filter(Boolean);
        }

        function sameOrder(a, b) {
            return a.length === b.length && a.every(function (value, index) {
                return value === b[index];
            });
        }

        function notify(type, message) {
            if (window.AppNotify && typeof window.AppNotify[type] === 'function') {
                window.AppNotify[type](message);
            }
        }

        function saveOrder() {
            var order = currentOrder();
            if (sameOrder(order, originalOrder)) {
                return;
            }

            var payload = new FormData();
            payload.append('csrf_token', table.getAttribute('data-csrf-token') || '');
            order.forEach(function (questionId) {
                payload.append('order[]', questionId);
            });

            fetch(table.getAttribute('data-reorder-url'), {
                method: 'POST',
                body: payload,
                credentials: 'same-origin',
                headers: {'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json'}
            })
                .then(function (response) {
                    return response.json().then(function (data) {
                        if (!response.ok || !data.ok) {
                            throw new Error(data.message || 'No se pudo guardar el orden.');
                        }
                        return data;
                    });
                })
                .then(function (data) {
                    originalOrder = order;
                    notify('success', data.message || 'Orden actualizado.');
                })
                .catch(function (error) {
                    notify('error', error.message || 'No se pudo guardar el orden.');
                    window.setTimeout(function () {
                        window.location.reload();
                    }, 900);
                });
        }

        table.addEventListener('pointerdown', function (event) {
            var handle = event.target.closest('[data-question-drag-handle]');
            if (!handle) {
                return;
            }
            var row = handle.closest('[data-question-row]');
            if (row) {
                row.draggable = true;
            }
        });

        table.addEventListener('dragstart', function (event) {
            var row = event.target.closest('[data-question-row]');
            if (!row) {
                return;
            }
            draggingRow = row;
            originalOrder = currentOrder();
            row.classList.add('table-active');
            event.dataTransfer.effectAllowed = 'move';
            event.dataTransfer.setData('text/plain', row.getAttribute('data-question-id') || '');
        });

        table.addEventListener('dragover', function (event) {
            if (!draggingRow) {
                return;
            }
            var targetRow = event.target.closest('[data-question-row]');
            if (!targetRow || targetRow === draggingRow) {
                return;
            }
            event.preventDefault();
            var rect = targetRow.getBoundingClientRect();
            var afterTarget = event.clientY > rect.top + (rect.height / 2);
            tbody.insertBefore(draggingRow, afterTarget ? targetRow.nextSibling : targetRow);
        });

        table.addEventListener('drop', function (event) {
            if (draggingRow) {
                event.preventDefault();
                saveOrder();
            }
        });

        table.addEventListener('dragend', function () {
            if (draggingRow) {
                draggingRow.classList.remove('table-active');
                draggingRow.draggable = false;
            }
            draggingRow = null;
            rows().forEach(function (row) {
                row.draggable = false;
            });
        });
    });
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function () {
        initEvaluationSurveyQuestionReorder(document);
    });
} else {
    initEvaluationSurveyQuestionReorder(document);
}
</script>
<script>
function initEvaluationSurveyResultOptions(scope) {
    (scope || document).querySelectorAll('[data-result-visibility-toggle]').forEach(function (toggle) {
        if (toggle.dataset.resultVisibilityReady === '1') {
            return;
        }
        toggle.dataset.resultVisibilityReady = '1';
        var form = toggle.closest('form');
        var options = form ? form.querySelector('[data-result-display-options]') : null;

        function syncResultOptions() {
            if (!options) {
                return;
            }
            options.hidden = !toggle.checked;
        }

        toggle.addEventListener('change', syncResultOptions);
        syncResultOptions();
    });
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function () {
        initEvaluationSurveyResultOptions(document);
    });
} else {
    initEvaluationSurveyResultOptions(document);
}
</script>
<?php endif; ?>
