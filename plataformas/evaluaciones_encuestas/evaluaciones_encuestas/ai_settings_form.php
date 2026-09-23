<?php
$pdfPresets = EvaluationSurveySettingsModel::AI_PDF_PRESETS;
$currentPages = (int) ($settings['max_pdf_pages'] ?? 60);
?>
<section class="page-header"><div><p class="dashboard-kicker mb-2">Encuestas / Evaluaciones</p><h1>Configuración IA</h1><p class="text-muted mb-0">Configura la generación e importación asistida.</p></div></section>
<form method="post" class="card content-panel needs-validation" novalidate>
    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
    <div class="alert alert-info small">Estado transversal: <strong><?= !empty($globalAi['enabled']) && !empty($globalAi['has_api_key']) ? 'disponible' : 'no habilitado' ?></strong></div>
    <div class="row g-3">
        <div class="col-12"><div class="form-check form-switch"><input class="form-check-input" type="checkbox" name="ai_enabled" value="1" <?= $settings['enabled'] === '1' ? 'checked' : '' ?>><label class="form-check-label">Habilitar IA para esta sub-plataforma</label></div></div>
        <div class="col-md-6"><div class="form-check"><input class="form-check-input" type="checkbox" name="ai_import_enabled" value="1" <?= $settings['import_enabled'] === '1' ? 'checked' : '' ?>><label class="form-check-label">Importar preguntas desde PDF</label></div><div class="form-check"><input class="form-check-input" type="checkbox" name="ai_generation_enabled" value="1" <?= $settings['generation_enabled'] === '1' ? 'checked' : '' ?>><label class="form-check-label">Generar preguntas desde PDF</label></div><div class="form-check"><input class="form-check-input" type="checkbox" name="ai_require_review" value="1" <?= $settings['require_review'] === '1' ? 'checked' : '' ?>><label class="form-check-label">Exigir revisión humana</label></div></div>
        <div class="col-md-6"><label class="form-label">Dificultad predeterminada</label><select class="form-select" name="default_difficulty"><option value="easy" <?= $settings['default_difficulty'] === 'easy' ? 'selected' : '' ?>>Fácil</option><option value="medium" <?= $settings['default_difficulty'] === 'medium' ? 'selected' : '' ?>>Media</option><option value="hard" <?= $settings['default_difficulty'] === 'hard' ? 'selected' : '' ?>>Difícil</option></select></div>
        <div class="col-md-4"><label class="form-label">Máximo PDF (MB)</label><input class="form-control" type="number" min="1" max="200" name="max_pdf_mb" value="<?= (int) $settings['max_pdf_mb'] ?>"></div>
        <div class="col-md-4"><label class="form-label" for="max_pdf_pages">Perfil de procesamiento</label><select class="form-select" id="max_pdf_pages" name="max_pdf_pages"><?php foreach ($pdfPresets as $pages => $preset): ?><option value="<?= (int) $pages ?>" <?= $currentPages === (int) $pages ? 'selected' : '' ?>><?= e($preset['label']) ?></option><?php endforeach; ?></select></div>
        <div class="col-md-4"><label class="form-label">Máximo preguntas</label><input class="form-control" type="number" min="1" max="100" name="max_questions" value="<?= (int) $settings['max_questions'] ?>"></div>
        <div class="col-12 alert alert-warning small mb-0">Los borradores generados deben revisarse antes de activarse.</div>
        <div class="col-12 d-flex flex-wrap gap-2"><button class="btn btn-primary" type="submit">Guardar configuración</button><a class="btn btn-outline-secondary" href="<?= e(route_url('evaluation-surveys.assessments')) ?>">Cancelar</a></div>
    </div>
</form>
