<?php
$formValues = is_array($formValues ?? null) ? $formValues : ['form_type' => 'assessment', 'operation' => 'import', 'question_count' => 10, 'difficulty' => 'medium'];
$isSurvey = $formValues['form_type'] === 'survey';
$operation = in_array((string) ($formValues['operation'] ?? 'import'), ['import', 'generate'], true) ? (string) $formValues['operation'] : 'import';
?>
<section class="page-header">
    <div><p class="dashboard-kicker mb-2">Encuestas / Evaluaciones</p><h1>Crear <?= $isSurvey ? 'encuesta' : 'evaluación' ?> con IA</h1><p class="text-muted mb-0">Carga un PDF para importar o generar preguntas.</p></div>
</section>
<form method="post" enctype="multipart/form-data" class="content-panel needs-validation" novalidate data-ai-pdf-form>
    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
    <div class="row g-3">
        <div class="col-12">
            <label class="form-label">¿Qué quieres hacer?</label>
            <div class="row g-2">
                <div class="col-md-6">
                    <label class="border rounded p-3 d-block h-100" data-ai-operation-card>
                        <input class="form-check-input me-2" type="radio" name="operation" value="import" <?= $operation === 'import' ? 'checked' : '' ?> data-ai-operation>
                        <strong>Traspasar una evaluación desde PDF</strong>
                        <span class="d-block text-muted small mt-1">Conserva las preguntas y alternativas existentes. El total se obtiene desde el documento.</span>
                    </label>
                </div>
                <div class="col-md-6">
                    <label class="border rounded p-3 d-block h-100" data-ai-operation-card>
                        <input class="form-check-input me-2" type="radio" name="operation" value="generate" <?= $operation === 'generate' ? 'checked' : '' ?> data-ai-operation>
                        <strong>Generar una evaluación desde contenido PDF</strong>
                        <span class="d-block text-muted small mt-1">Crea preguntas nuevas usando el PDF como material de referencia.</span>
                    </label>
                </div>
            </div>
        </div>
        <div class="col-12"><label class="form-label" for="aiPdf">PDF fuente</label><input class="form-control" id="aiPdf" name="pdf" type="file" accept="application/pdf,.pdf" required data-ai-pdf-input><div class="form-text">Máximo <?= (int) $settings['max_pdf_mb'] ?> MB y <?= (int) $settings['max_pdf_pages'] ?> páginas.</div></div>
        <div class="col-md-5"><label class="form-label">Tipo de formulario</label><input class="form-control" value="<?= $isSurvey ? 'Encuesta de satisfacción' : 'Evaluación con nota' ?>" readonly><input type="hidden" name="form_type" value="<?= e($formValues['form_type']) ?>"></div>
        <div class="col-md-3" data-ai-generate-field><label class="form-label" for="questionCount">Preguntas</label><input class="form-control" id="questionCount" type="number" name="question_count" min="1" max="<?= (int) $settings['max_questions'] ?>" value="<?= (int) $formValues['question_count'] ?>"></div>
        <div class="col-md-4" data-ai-generate-field><label class="form-label" for="difficulty">Dificultad</label><select class="form-select" id="difficulty" name="difficulty"><option value="easy" <?= $formValues['difficulty'] === 'easy' ? 'selected' : '' ?>>Fácil</option><option value="medium" <?= $formValues['difficulty'] === 'medium' ? 'selected' : '' ?>>Media</option><option value="hard" <?= $formValues['difficulty'] === 'hard' ? 'selected' : '' ?>>Difícil</option></select></div>
        <div class="col-12 alert alert-info small mb-0" data-ai-import-help>El sistema leerá todas las preguntas disponibles en el PDF. No se solicitará dificultad ni cantidad de preguntas.</div>
        <div class="col-12 alert alert-warning small mb-0">El resultado se guardará como borrador y requerirá revisión humana.</div>
        <div class="col-12 d-flex flex-wrap gap-2"><button class="btn btn-primary" type="submit"><i class="bi bi-stars me-1"></i>Analizar y generar borrador</button><a class="btn btn-outline-secondary" href="<?= e(route_url('evaluation-surveys.assessments')) ?>">Cancelar</a></div>
    </div>
</form>
<script>document.addEventListener('DOMContentLoaded',function(){const f=document.querySelector('[data-ai-pdf-form]'),i=document.querySelector('[data-ai-pdf-input]'),ops=[...document.querySelectorAll('[data-ai-operation]')],fields=[...document.querySelectorAll('[data-ai-generate-field]')],help=document.querySelector('[data-ai-import-help]');if(!f||!i)return;function sync(){const importing=document.querySelector('[data-ai-operation]:checked')?.value==='import';fields.forEach(function(field){field.classList.toggle('d-none',importing);field.querySelectorAll('input,select').forEach(function(control){control.disabled=importing;control.required=!importing;});});if(help)help.classList.toggle('d-none',!importing);}ops.forEach(function(op){op.addEventListener('change',sync);});sync();f.addEventListener('submit',function(e){if(!i.files||!i.files.length){e.preventDefault();i.classList.add('is-invalid');i.focus();}});});</script>
