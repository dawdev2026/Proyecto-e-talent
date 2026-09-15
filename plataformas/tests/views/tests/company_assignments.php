<section class="page-header">
    <div>
        <p class="text-uppercase text-primary fw-bold small mb-1">Evaluaciones Psicométricas</p>
        <h1 class="fw-bold mb-1">Asignar evaluaciones Empresa</h1>
        <p class="text-muted mb-0">Define qué evaluaciones puede utilizar cada empresa y sus parámetros personalizados.</p>
    </div>
</section>

<section class="content-panel">
    <form method="get" class="row g-3 align-items-end mb-4">
        <div class="col-md-6 col-lg-4">
            <label class="form-label" for="company_id">Empresa</label>
            <select class="form-select" id="company_id" name="company_id" onchange="this.form.submit()">
                <?php foreach ($companies as $item): ?>
                    <option value="<?= (int) $item['id'] ?>" <?= (int) ($company['id'] ?? 0) === (int) $item['id'] ? 'selected' : '' ?>><?= e((string) $item['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
    </form>

    <?php if (!$company): ?>
        <div class="alert alert-light border mb-0">No existen empresas activas para configurar.</div>
    <?php else: ?>
        <form method="post">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="company_id" value="<?= (int) $company['id'] ?>">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                <div>
                    <h2 class="h5 fw-bold mb-1">Evaluaciones disponibles para <?= e((string) $company['name']) ?></h2>
                    <p class="text-muted small mb-0">Los campos en blanco utilizan la configuración general de la evaluación.</p>
                </div>
                <button class="btn btn-primary" type="submit"><i class="bi bi-save me-1"></i> Guardar configuración</button>
            </div>

            <?php foreach ($assignments as $instrument): ?>
                <?php $instrumentId = (int) $instrument['id']; $enabled = !empty($instrument['assignment_id']); ?>
                <div class="border rounded p-3 mb-3">
                    <label class="d-flex align-items-start gap-2 mb-3">
                        <input class="form-check-input mt-1" type="checkbox" name="instruments[<?= $instrumentId ?>][enabled]" value="1" <?= $enabled ? 'checked' : '' ?>>
                        <span><strong><?= e((string) $instrument['name']) ?></strong><small class="d-block text-muted"><?= e(labelize((string) $instrument['category'])) ?> · configuración base: <?= (int) $instrument['default_duration_minutes'] > 0 ? (int) $instrument['default_duration_minutes'] . ' min' : 'sin límite' ?></small></span>
                    </label>
                    <div class="row g-3">
                        <div class="col-sm-6 col-lg-3"><label class="form-label small">Duración (minutos)</label><input class="form-control" type="number" min="0" name="instruments[<?= $instrumentId ?>][duration_minutes]" value="<?= e((string) ($instrument['duration_minutes'] ?? '')) ?>" placeholder="Base"></div>
                        <div class="col-sm-6 col-lg-3"><label class="form-label small">Orden de preguntas</label><select class="form-select" name="instruments[<?= $instrumentId ?>][question_order_mode]"><option value="">Base</option><option value="ordered" <?= ($instrument['question_order_mode'] ?? '') === 'ordered' ? 'selected' : '' ?>>Ordenadas</option><option value="random" <?= ($instrument['question_order_mode'] ?? '') === 'random' ? 'selected' : '' ?>>Aleatorias</option></select></div>
                        <div class="col-sm-6 col-lg-3"><label class="form-label small">Modo de bloques</label><select class="form-select" name="instruments[<?= $instrumentId ?>][use_blocks]"><option value="">Base</option><option value="1" <?= (string) ($instrument['use_blocks'] ?? '') === '1' ? 'selected' : '' ?>>Activado</option><option value="0" <?= (string) ($instrument['use_blocks'] ?? '') === '0' && $instrument['use_blocks'] !== null ? 'selected' : '' ?>>Desactivado</option></select></div>
                        <div class="col-sm-6 col-lg-3"><label class="form-label small">Preguntas por bloque</label><input class="form-control" type="number" min="0" name="instruments[<?= $instrumentId ?>][block_size]" value="<?= e((string) ($instrument['block_size'] ?? '')) ?>" placeholder="Base"></div>
                        <div class="col-sm-6 col-lg-3"><label class="form-label small">Completar bloque</label><select class="form-select" name="instruments[<?= $instrumentId ?>][require_block_completion]"><option value="">Base</option><option value="1" <?= (string) ($instrument['require_block_completion'] ?? '') === '1' ? 'selected' : '' ?>>Sí</option><option value="0" <?= (string) ($instrument['require_block_completion'] ?? '') === '0' && $instrument['require_block_completion'] !== null ? 'selected' : '' ?>>No</option></select></div>
                        <div class="col-sm-6 col-lg-3"><label class="form-label small">Usuario ve resultados</label><select class="form-select" name="instruments[<?= $instrumentId ?>][user_can_view_results]"><option value="">Base</option><option value="1" <?= (string) ($instrument['user_can_view_results'] ?? '') === '1' ? 'selected' : '' ?>>Sí</option><option value="0" <?= (string) ($instrument['user_can_view_results'] ?? '') === '0' && $instrument['user_can_view_results'] !== null ? 'selected' : '' ?>>No</option></select></div>
                        <div class="col-sm-6 col-lg-3"><label class="form-label small">Mostrar número de pregunta</label><select class="form-select" name="instruments[<?= $instrumentId ?>][show_question_numbers]"><option value="">Base</option><option value="1" <?= (string) ($instrument['show_question_numbers'] ?? '') === '1' ? 'selected' : '' ?>>Sí</option><option value="0" <?= (string) ($instrument['show_question_numbers'] ?? '') === '0' && $instrument['show_question_numbers'] !== null ? 'selected' : '' ?>>No</option></select></div>
                        <div class="col-sm-6 col-lg-3"><label class="form-label small">Inicio automático</label><select class="form-select" name="instruments[<?= $instrumentId ?>][auto_start_enabled]"><option value="">Base</option><option value="1" <?= (string) ($instrument['auto_start_enabled'] ?? '') === '1' ? 'selected' : '' ?>>Activado</option><option value="0" <?= (string) ($instrument['auto_start_enabled'] ?? '') === '0' && $instrument['auto_start_enabled'] !== null ? 'selected' : '' ?>>Desactivado</option></select></div>
                        <div class="col-sm-6 col-lg-3"><label class="form-label small">Orden inicio automático</label><input class="form-control" type="number" min="1" name="instruments[<?= $instrumentId ?>][auto_start_order]" value="<?= e((string) ($instrument['auto_start_order'] ?? '')) ?>" placeholder="Base"></div>
                        <div class="col-sm-6 col-lg-9"><label class="form-label small">Modo de control durante la evaluación</label><select class="form-select" name="instruments[<?= $instrumentId ?>][control_mode]"><option value="">Base</option><option value="off" <?= ($instrument['control_mode'] ?? '') === 'off' ? 'selected' : '' ?>>Sin registro</option><option value="activity" <?= ($instrument['control_mode'] ?? '') === 'activity' ? 'selected' : '' ?>>Registro de actividad</option><option value="supervised" <?= ($instrument['control_mode'] ?? '') === 'supervised' ? 'selected' : '' ?>>Rendición supervisada</option><option value="supervised_audio_visual" <?= ($instrument['control_mode'] ?? '') === 'supervised_audio_visual' ? 'selected' : '' ?>>Rendición supervisada + control audiovisual</option></select></div>
                    </div>
                </div>
            <?php endforeach; ?>
            <?php if (!$assignments): ?><div class="alert alert-light border mb-0">No hay evaluaciones activas en el catálogo general.</div><?php endif; ?>
        </form>
    <?php endif; ?>
</section>
