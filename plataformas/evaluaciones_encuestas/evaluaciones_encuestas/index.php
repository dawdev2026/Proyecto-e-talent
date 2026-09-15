<?php
$isSurvey = $type === 'survey';
$titleLabel = $isSurvey ? 'Encuestas de satisfacción' : 'Evaluaciones con nota';
$newRoute = 'evaluation-surveys.form.new';
?>
<section class="page-header" data-page-back="disabled">
    <div>
        <p class="dashboard-kicker mb-2">Encuestas / Evaluaciones</p>
        <h1 class="fw-bold mb-1"><?= e($titleLabel) ?></h1>
        <p class="text-muted mb-0"><?= $isSurvey ? 'Administra encuestas reutilizables.' : 'Administra evaluaciones calificadas reutilizables.' ?></p>
    </div>
    <div class="page-header-actions">
        <a class="btn btn-sm btn-outline-primary" href="<?= e(route_url('evaluation-surveys.ai.generate') . '?form_type=' . rawurlencode($type)) ?>"><i class="bi bi-stars me-1"></i>Crear con IA</a>
        <a class="btn btn-sm btn-primary" href="<?= e(route_url($newRoute) . '?type=' . rawurlencode($type)) ?>">
            <i class="bi bi-plus-lg me-1"></i> <?= $isSurvey ? 'Nueva encuesta' : 'Nueva evaluación' ?>
        </a>
    </div>
</section>

<section class="content-panel">
    <div class="table-responsive">
        <table class="table align-middle app-table app-data-table" data-export-title="<?= e($titleLabel) ?>">
            <thead>
                <tr>
                    <th>Formulario</th>
                    <th>Disponible</th>
                    <th>Estado</th>
                    <th>Preguntas</th>
                    <th>Personas</th>
                    <th><?= $isSurvey ? 'Respuestas' : 'Intentos' ?></th>
                    <th class="no-sort no-export text-end">Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($forms as $form): ?>
                    <tr>
                        <td>
                            <strong><?= e($form['title']) ?></strong>
                            <div class="text-muted small"><?= e(trim(html_entity_decode(strip_tags((string) ($form['description'] ?? '')), ENT_QUOTES | ENT_HTML5, 'UTF-8'))) ?></div>
                        </td>
                        <td>
                            <span class="badge text-bg-<?= (int) ($form['is_required'] ?? 0) === 1 ? 'success' : 'secondary' ?>">
                                <?= (int) ($form['is_required'] ?? 0) === 1 ? 'Si' : 'No' ?>
                            </span>
                        </td>
                        <td><span class="badge text-bg-<?= $form['status'] === 'active' ? 'success' : ($form['status'] === 'draft' ? 'warning' : 'secondary') ?>"><?= e($statuses[(string) $form['status']] ?? (string) $form['status']) ?></span></td>
                        <td><?= (int) ($form['questions_count'] ?? 0) ?></td>
                        <td><?= (int) ($form['respondents_count'] ?? 0) ?></td>
                        <td><?= (int) ($form['attempts_count'] ?? 0) ?></td>
                        <td class="text-end">
                            <?= admin_action_menu([
                                ['label' => 'Gestion', 'items' => [
                                    ['label' => 'Vista previa', 'url' => route_url('evaluation-surveys.form.preview', (int) $form['id']), 'icon' => 'bi-eye'],
                                    ['label' => 'Editar', 'url' => route_url('evaluation-surveys.form.edit', (int) $form['id']), 'icon' => 'bi-pencil'],
                                    ['label' => 'Duplicar', 'url' => route_url('evaluation-surveys.form.duplicate', (int) $form['id']), 'icon' => 'bi-files', 'method' => 'post', 'confirm' => $isSurvey ? 'Se creara una copia de la encuesta, sus preguntas y alternativas, sin respuestas ni resultados. Deseas continuar?' : 'Se creara una copia de la evaluación, sus preguntas y alternativas, sin intentos ni resultados. Deseas continuar?'],
                                ]],
                                ['label' => 'Eliminación', 'items' => [
                                    ['label' => 'Eliminar', 'url' => route_url('evaluation-surveys.form.delete', (int) $form['id']), 'icon' => 'bi-trash', 'method' => 'post', 'confirm' => $isSurvey ? 'Esta acción eliminara la encuesta, preguntas y respuestas asociadas. Deseas continuar?' : 'Esta acción eliminara el formulario, preguntas, intentos y respuestas asociadas. Deseas continuar?'],
                                ]],
                            ]) ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
