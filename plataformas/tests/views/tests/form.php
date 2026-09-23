<section class="page-header">
    <div>
        <p class="text-uppercase text-primary fw-bold small mb-1">Evaluaciones</p>
        <h1 class="fw-bold mb-1"><?= $id ? 'Editar evaluacion' : 'Nueva evaluacion' ?></h1>
        <p class="text-muted mb-0">Registra metadatos del instrumento sin exponer items, claves ni baremos protegidos.</p>
    </div>
</section>

<section class="card content-panel">
    <form method="post" class="row g-4 needs-validation" novalidate data-test-instrument-form>
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
        <div class="col-12 col-lg-4">
            <label class="form-label" for="code">Codigo</label>
            <input id="code" class="form-control form-control-lg" name="code" value="<?= e($values['code']) ?>" maxlength="60">
            <div class="form-text">Se completa desde el nombre. Puedes ajustarlo antes de guardar.</div>
        </div>
        <div class="col-12 col-lg-8">
            <label class="form-label" for="name">Nombre</label>
            <input id="name" class="form-control form-control-lg" name="name" value="<?= e($values['name']) ?>" maxlength="160" required>
        </div>
        <div class="col-12 col-lg-4">
            <label class="form-label" for="category">Categoria</label>
            <select id="category" class="form-select form-select-lg" name="category" required>
                <?php foreach ($categories as $key => $label): ?>
                    <option value="<?= e($key) ?>" <?= $values['category'] === $key ? 'selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-12 col-lg-4">
            <label class="form-label" for="duration_minutes">Duracion en minutos</label>
            <input id="duration_minutes" class="form-control form-control-lg" type="number" min="0" name="duration_minutes" value="<?= (int) $values['duration_minutes'] ?>">
            <div class="form-text">Usa 0 si no hay limite definido.</div>
        </div>
        <div class="col-12 col-lg-4">
            <label class="form-label d-inline-flex align-items-center gap-1" for="status">Estado <?= status_help_button('Estados del test', "• Borrador: en configuración y no asignable.\n• Activo: disponible para nuevas asignaciones.\n• Inactivo: no se asigna, pero se conserva su historial.") ?></label>
            <select id="status" class="form-select form-select-lg" name="status" required>
                <?php foreach ($statuses as $key => $label): ?>
                    <option value="<?= e($key) ?>" <?= $values['status'] === $key ? 'selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-12">
            <label class="form-label" for="description">Descripcion funcional</label>
            <textarea id="description" class="form-control" name="description" rows="3"><?= e($values['description'] ?? '') ?></textarea>
        </div>
        <div class="col-12">
            <label class="form-label" for="source_reference">Referencia, manual o condicion de uso</label>
            <textarea id="source_reference" class="form-control" name="source_reference" rows="2"><?= e($values['source_reference'] ?? '') ?></textarea>
        </div>
        <div class="col-12">
            <label class="form-label" for="instructions">Instrucciones para aplicacion</label>
            <textarea id="instructions" class="form-control" name="instructions" rows="4"><?= e($values['instructions'] ?? '') ?></textarea>
        </div>
        <div class="col-12">
            <section class="test-builder test-delivery-settings">
                <div class="test-builder-header">
                    <div>
                        <h2 class="h5 fw-bold mb-1">Visualizacion del test</h2>
                        <p class="text-muted mb-0">Define si el evaluado respondera todas las preguntas juntas o por bloques progresivos.</p>
                    </div>
                </div>
                <div class="row g-3 align-items-end">
                    <div class="col-12 col-lg-4">
                        <label class="form-label" for="question_order_mode">Orden de preguntas</label>
                        <select id="question_order_mode" class="form-select" name="question_order_mode">
                            <?php foreach ($questionOrderModes as $key => $label): ?>
                                <option value="<?= e($key) ?>" <?= ($values['question_order_mode'] ?? 'ordered') === $key ? 'selected' : '' ?>><?= e($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-text">Aleatorias usa un orden distinto y estable para cada asignacion.</div>
                    </div>
                    <div class="col-12 col-lg-4">
                        <div class="form-check form-switch">
                            <input id="use_blocks" class="form-check-input" type="checkbox" name="use_blocks" data-toggle-test-blocks <?= (int) ($values['use_blocks'] ?? 0) === 1 ? 'checked' : '' ?>>
                            <label class="form-check-label" for="use_blocks">Mostrar en modo bloques</label>
                        </div>
                    </div>
                    <div class="col-12 col-lg-4" data-test-block-option>
                        <label class="form-label" for="block_size">Preguntas por bloque</label>
                        <input id="block_size" class="form-control" type="number" min="1" max="200" name="block_size" value="<?= max(1, (int) (($values['block_size'] ?? 0) ?: 10)) ?>">
                    </div>
                    <div class="col-12 col-lg-4" data-test-block-option>
                        <label class="form-label">Configurar modo bloque</label>
                        <div class="form-check">
                            <input id="require_block_completion_yes" class="form-check-input" type="radio" name="require_block_completion" value="1" <?= (int) ($values['require_block_completion'] ?? 1) === 1 ? 'checked' : '' ?>>
                            <label class="form-check-label" for="require_block_completion_yes">Debe completar todas las preguntas del bloque para avanzar</label>
                        </div>
                        <div class="form-check">
                            <input id="require_block_completion_no" class="form-check-input" type="radio" name="require_block_completion" value="0" <?= (int) ($values['require_block_completion'] ?? 1) === 0 ? 'checked' : '' ?>>
                            <label class="form-check-label" for="require_block_completion_no">Puede avanzar entre bloques sin tener el bloque completo respondido</label>
                        </div>
                        <div class="form-text">Al intentar finalizar siempre se revisaran las preguntas pendientes y el usuario decidira si responderlas o finalizar sin responder.</div>
                    </div>
                    <div class="col-12 col-lg-4">
                        <div class="form-check form-switch">
                            <input id="user_can_view_results" class="form-check-input" type="checkbox" name="user_can_view_results" <?= (int) ($values['user_can_view_results'] ?? 1) === 1 ? 'checked' : '' ?>>
                            <label class="form-check-label" for="user_can_view_results">Usuario puede ver sus resultados</label>
                        </div>
                    </div>
                    <div class="col-12 col-lg-4">
                        <div class="form-check form-switch">
                            <input id="show_question_numbers" class="form-check-input" type="checkbox" name="show_question_numbers" <?= (int) ($values['show_question_numbers'] ?? 1) === 1 ? 'checked' : '' ?>>
                            <label class="form-check-label" for="show_question_numbers">Mostrar numero de pregunta al responder</label>
                        </div>
                    </div>
                    <div class="col-12 col-lg-4">
                        <div class="form-check form-switch">
                            <input id="auto_start_enabled" class="form-check-input" type="checkbox" name="auto_start_enabled" <?= (int) ($values['auto_start_enabled'] ?? 0) === 1 ? 'checked' : '' ?>>
                            <label class="form-check-label" for="auto_start_enabled">Inicio automatico obligatorio</label>
                        </div>
                        <div class="form-text">Si esta activo, el usuario debera completar esta evaluacion antes de responder otras no obligatorias.</div>
                    </div>
                    <div class="col-12 col-lg-4">
                        <label class="form-label" for="auto_start_order">Orden de inicio automatico</label>
                        <input id="auto_start_order" class="form-control" type="number" min="1" name="auto_start_order" value="<?= max(1, (int) ($values['auto_start_order'] ?? 100)) ?>">
                    </div>
                    <div class="col-12">
                        <?php
                        $legacyControlMode = (int) ($values['supervised_mode_enabled'] ?? 0) === 1
                            ? 'supervised'
                            : ((int) ($values['track_activity_enabled'] ?? 0) === 1 ? 'activity' : 'off');
                        $controlMode = (string) ($values['control_mode'] ?? $legacyControlMode);
                        if (!isset(TestInstrumentModel::CONTROL_MODES[$controlMode])) {
                            $controlMode = $legacyControlMode;
                        }
                        $controlModeHelp = [
                            'off' => 'No se almacenan eventos de actividad de la evaluacion. Se mantiene solo el control tecnico de presencia necesario para el funcionamiento de la sesion.',
                            'activity' => 'Registra apertura, inicio, reapertura, dispositivo, respuestas guardadas o modificadas, borradores, bloques, pausas, envio, expiracion, pestaña visible u oculta, perdida o recuperacion de foco e inactividad.',
                            'supervised' => 'Incluye todo el registro de actividad y agrega pantalla completa, entradas y salidas de pantalla completa, intentos de copiar, cortar, pegar, imprimir, abrir el menu contextual, arrastrar contenido y señales de riesgo. Si pantalla completa no funciona, la persona puede continuar con la advertencia registrada.',
                            'supervised_audio_visual' => 'Incluye todo lo anterior y solicita camara, microfono y captura visual para la rendicion. Registra interrupciones, posibles multiples voces y fallas de carga. La accion ante cada incidencia usa la configuracion vigente.',
                        ];
                        ?>
                        <div class="d-flex align-items-center gap-2">
                            <label class="form-label mb-0" for="control_mode">Modo de control durante la evaluacion</label>
                            <button
                                class="btn btn-link btn-sm p-0 activity-help-toggle"
                                type="button"
                                aria-label="Ayuda sobre el modo de control"
                                data-control-mode-help
                                data-bs-toggle="popover"
                                data-bs-trigger="focus"
                                data-bs-placement="top"
                                data-bs-title="<?= e(TestInstrumentModel::CONTROL_MODES[$controlMode]) ?>"
                                data-bs-content="<?= e($controlModeHelp[$controlMode]) ?>"
                            ><i class="bi bi-info-circle" aria-hidden="true"></i></button>
                        </div>
                        <select id="control_mode" class="form-select" name="control_mode" data-control-mode-select>
                            <?php foreach (TestInstrumentModel::CONTROL_MODES as $key => $label): ?>
                                <option value="<?= e($key) ?>" <?= $controlMode === $key ? 'selected' : '' ?>><?= e($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-text">La rendicion supervisada incluye todo el registro de actividad. Si pantalla completa no funciona, la persona puede continuar con la advertencia registrada.</div>
                        <div class="mt-3 p-3 border rounded d-none" data-audio-visual-rules>
                            <?php $av = static function (string $key, string $default = '') use ($values): string { return (string) ($values[$key] ?? $default); }; ?>
                            <p class="fw-semibold mb-3">Reglas del control audio visual para esta evaluacion</p>
                            <div class="row g-3">
                                <div class="col-12 col-lg-4">
                                    <label class="form-label" for="audio_visual_interruption_policy">1. Interrupción de cámara, micrófono o captura de pantalla <button class="btn btn-link btn-sm p-0 activity-help-toggle" type="button" aria-label="Ayuda sobre interrupciones" data-bs-toggle="popover" data-bs-trigger="focus" data-bs-title="Interrupción audiovisual" data-bs-content="Define si la persona puede continuar cuando la cámara, el micrófono o la captura de pantalla se interrumpen. El evento siempre queda registrado como Control audio visual."><i class="bi bi-info-circle" aria-hidden="true"></i></button></label>
                                    <select id="audio_visual_interruption_policy" class="form-select" name="audio_visual_interruption_policy">
                                        <option value="continue" <?= $av('audio_visual_interruption_policy', 'pause') === 'continue' ? 'selected' : '' ?>>Registrar y continuar</option>
                                        <option value="pause" <?= $av('audio_visual_interruption_policy', 'pause') === 'pause' ? 'selected' : '' ?>>Registrar y pausar</option>
                                        <option value="block" <?= $av('audio_visual_interruption_policy', 'pause') === 'block' ? 'selected' : '' ?>>Registrar y bloquear</option>
                                    </select>
                                </div>
                                <div class="col-12 col-lg-4">
                                    <label class="form-label" for="audio_visual_voice_policy">2. Deteccion de multiples voces <button class="btn btn-link btn-sm p-0 activity-help-toggle" type="button" aria-label="Ayuda sobre multiples voces" data-bs-toggle="popover" data-bs-trigger="focus" data-bs-title="Multiples voces" data-bs-content="La deteccion del navegador es una señal preliminar. Toda señal se registra como Control audio visual y no constituye por si sola una conclusion de copia."><i class="bi bi-info-circle" aria-hidden="true"></i></button></label>
                                    <select id="audio_visual_voice_policy" class="form-select" name="audio_visual_voice_policy">
                                        <option value="log" <?= $av('audio_visual_voice_policy', 'warn') === 'log' ? 'selected' : '' ?>>Registrar solamente</option>
                                        <option value="warn" <?= $av('audio_visual_voice_policy', 'warn') === 'warn' ? 'selected' : '' ?>>Registrar y mostrar advertencia</option>
                                        <option value="pause" <?= $av('audio_visual_voice_policy', 'warn') === 'pause' ? 'selected' : '' ?>>Registrar y pausar</option>
                                    </select>
                                </div>
                                <div class="col-12 col-lg-4">
                                    <label class="form-label" for="audio_visual_permission_policy">3. Perdida o desactivacion de permisos <button class="btn btn-link btn-sm p-0 activity-help-toggle" type="button" aria-label="Ayuda sobre permisos" data-bs-toggle="popover" data-bs-trigger="focus" data-bs-title="Permisos de camara y microfono" data-bs-content="Aplica cuando el usuario revoca permisos o el navegador deja de entregar audio o video. La accion elegida se registra como Control audio visual."><i class="bi bi-info-circle" aria-hidden="true"></i></button></label>
                                    <select id="audio_visual_permission_policy" class="form-select" name="audio_visual_permission_policy">
                                        <option value="continue" <?= $av('audio_visual_permission_policy', 'pause') === 'continue' ? 'selected' : '' ?>>Registrar y continuar</option>
                                        <option value="pause" <?= $av('audio_visual_permission_policy', 'pause') === 'pause' ? 'selected' : '' ?>>Registrar y pausar</option>
                                        <option value="block" <?= $av('audio_visual_permission_policy', 'pause') === 'block' ? 'selected' : '' ?>>Registrar y bloquear</option>
                                    </select>
                                </div>
                                <div class="col-12 col-lg-4">
                                    <label class="form-label" for="audio_visual_quality_profile">4. Calidad de grabacion <button class="btn btn-link btn-sm p-0 activity-help-toggle" type="button" aria-label="Ayuda sobre calidad" data-bs-toggle="popover" data-bs-trigger="focus" data-bs-title="Calidad audiovisual" data-bs-content="Define el equilibrio entre nitidez y peso del archivo. La plataforma mantendra formatos compatibles con el navegador y validara la evidencia antes de guardarla."><i class="bi bi-info-circle" aria-hidden="true"></i></button></label>
                                    <select id="audio_visual_quality_profile" class="form-select" name="audio_visual_quality_profile">
                                        <option value="economical" <?= $av('audio_visual_quality_profile', 'standard') === 'economical' ? 'selected' : '' ?>>Economica: menor peso</option>
                                        <option value="standard" <?= $av('audio_visual_quality_profile', 'standard') === 'standard' ? 'selected' : '' ?>>Estandar: equilibrio entre calidad y peso</option>
                                        <option value="high" <?= $av('audio_visual_quality_profile', 'standard') === 'high' ? 'selected' : '' ?>>Alta: mayor calidad y peso</option>
                                    </select>
                                </div>
                                <div class="col-12 col-lg-4">
                                    <label class="form-label" for="audio_visual_upload_failure_policy">5. Fallo en el envio del video <button class="btn btn-link btn-sm p-0 activity-help-toggle" type="button" aria-label="Ayuda sobre fallos de envio audiovisual" data-bs-toggle="popover" data-bs-trigger="focus" data-bs-placement="top" data-bs-title="Reintento de evidencia audiovisual" data-bs-content="Si se solicita reintento, se permitiran hasta 2 intentos. Si ambos fallan, la evaluacion podra finalizar y quedara registrada como evidencia audiovisual no guardada, junto con los motivos de error."><i class="bi bi-info-circle" aria-hidden="true"></i></button></label>
                                    <select id="audio_visual_upload_failure_policy" class="form-select" name="audio_visual_upload_failure_policy">
                                        <option value="continue" <?= $av('audio_visual_upload_failure_policy', 'continue') === 'continue' ? 'selected' : '' ?>>Registrar el fallo y permitir finalizar</option>
                                        <option value="retry_once" <?= $av('audio_visual_upload_failure_policy', 'continue') === 'retry_once' ? 'selected' : '' ?>>Registrar el fallo y solicitar reintento — maximo 2 intentos</option>
                                        <option value="block" <?= $av('audio_visual_upload_failure_policy', 'continue') === 'block' ? 'selected' : '' ?>>Registrar el fallo y bloquear el cierre</option>
                                    </select>
                                </div>
                            </div>
                            <div class="form-text mt-3">Todos los eventos se registran y se tipifican como <strong>Control audio visual</strong>.</div>
                        </div>
                    </div>
                </div>
            </section>
        </div>
        <div class="col-12">
            <section class="test-builder">
                <div class="test-builder-header">
                    <div>
                        <h2 class="h5 fw-bold mb-1">Contenido del test</h2>
                        <p class="text-muted mb-0">
                            Las escalas, preguntas, alternativas, valorizaciones, baremos y formulas se administran desde el mantenedor de contenido por bloques.
                        </p>
                    </div>
                    <?php if ($id): ?>
                        <a class="btn btn-outline-primary" href="<?= e(route_url('test.content', (int) $id)) ?>"><i class="bi bi-list-check me-1"></i> Editar contenido</a>
                    <?php else: ?>
                        <span class="badge text-bg-secondary">Disponible despues de crear</span>
                    <?php endif; ?>
                </div>
                <div class="row g-3">
                    <div class="col-6 col-lg-3"><div class="card metric-card"><span>Escalas</span><strong><?= (int) ($contentStats['scales_count'] ?? 0) ?></strong></div></div>
                    <div class="col-6 col-lg-3"><div class="card metric-card"><span>Items</span><strong><?= (int) ($contentStats['items_count'] ?? 0) ?></strong></div></div>
                    <div class="col-6 col-lg-3"><div class="card metric-card"><span>Reglas</span><strong><?= (int) ($contentStats['score_rules_count'] ?? 0) ?></strong></div></div>
                    <div class="col-6 col-lg-3"><div class="card metric-card"><span>Baremos</span><strong><?= (int) ($contentStats['norms_count'] ?? 0) ?></strong></div></div>
                </div>
            </section>
        </div>
        <div class="col-12">
            <div class="form-check form-switch">
                <input id="requires_manual_review" class="form-check-input" type="checkbox" name="requires_manual_review" <?= (int) $values['requires_manual_review'] === 1 ? 'checked' : '' ?>>
                <label class="form-check-label" for="requires_manual_review">Requiere revision de manual/licencia antes de cargar items o claves</label>
            </div>
        </div>
        <div class="col-12 d-flex flex-wrap gap-2">
            <button class="btn btn-primary px-4" type="submit"><i class="bi bi-check2 me-1"></i> <?= $id ? 'Guardar cambios' : 'Crear evaluacion' ?></button>
            <a class="btn btn-outline-secondary" href="<?= e(route_url('tests')) ?>">Cancelar</a>
        </div>
    </form>
</section>
