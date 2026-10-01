<link href="<?= e(url('assets/css/interviews/interviews.css')) ?>" rel="stylesheet">

<section class="page-header interview-new-header">
    <div>
        <p class="text-uppercase text-primary fw-bold small mb-1">Nueva interfaz de entrevistas</p>
        <h1 class="fw-bold mb-1">Preparar entrevista</h1>
        <p class="text-muted mb-0">La plataforma reúne los antecedentes, prepara la entrevista con IA y agenda la instancia cuando todo esté aprobado.</p>
    </div>
</section>

<form method="post" enctype="multipart/form-data" class="interview-registration-form interview-new-flow" data-assessment-preview-url="<?= e(route_url('interview-candidate.assessment-preview')) ?>" data-job-profile-parse-url="<?= e(route_url('interview-job-profile.parse')) ?>" data-preparation-preview-url="<?= e(route_url('interview-preparation.preview')) ?>" data-existing-preparation="<?= e(json_encode($existingModeratorBrief ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) ?>">
    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
    <input type="hidden" name="preparation_approved" value="0" data-preparation-approved>
    <input type="hidden" name="force_preparation" value="0" data-force-preparation>
    <input type="hidden" name="test_process_id" value="<?= (int) ($values['test_process_id'] ?? 0) ?>" data-test-process-id>

    <ol class="interview-stepper interview-new-stepper" data-interview-stepper aria-label="Progreso de preparación">
        <li class="active" data-step-indicator="1"><span>1</span><strong>Postulante</strong></li>
        <li data-step-indicator="2"><span>2</span><strong>Antecedentes</strong></li>
        <li data-step-indicator="3"><span>3</span><strong>Preparación IA</strong></li>
        <li data-step-indicator="4"><span>4</span><strong>Agenda</strong></li>
    </ol>
    <div class="interview-step-error" data-interview-step-error role="alert" hidden></div>

    <section class="card content-panel mb-3" data-interview-step="1">
        <div class="interview-section-heading"><div><span class="interview-step">1</span><div><h2 class="h5 fw-bold mb-1">Seleccionar postulante</h2><p class="text-muted mb-0">La entrevista se prepara para una sola persona y reutiliza sus evaluaciones finalizadas.</p></div></div></div>
        <label class="form-label" for="candidate_search">Buscar por nombre, RUT o correo</label>
        <div class="input-group mb-2"><span class="input-group-text"><i class="bi bi-search"></i></span><input id="candidate_search" class="form-control" type="search" placeholder="Escribe para buscar" autocomplete="off" data-candidate-search></div>
        <select id="candidate_user_id" class="form-select form-select-lg" name="candidate_user_id" required data-candidate-select><option value="">Selecciona un postulante...</option><?php foreach ($candidates as $candidate): ?><?php $candidateLabel = trim((string) ($candidate['name'] ?? '')) . ((string) ($candidate['rut'] ?? '') !== '' ? ' · ' . $candidate['rut'] : '') . ((string) ($candidate['email'] ?? '') !== '' ? ' · ' . $candidate['email'] : ''); ?><option value="<?= (int) $candidate['id'] ?>" data-search-text="<?= e($candidateLabel) ?>" <?= in_array((int) $candidate['id'], $selectedCandidates, true) ? 'selected' : '' ?>><?= e($candidateLabel) ?></option><?php endforeach; ?></select>
        <div class="form-text" data-candidate-search-status><?= count($candidates) ?> postulantes disponibles.</div>
        <div class="interview-context-note mt-3" data-candidate-assessment-summary><i class="bi bi-info-circle me-2"></i>Al seleccionar un postulante se cargarán automáticamente sus evaluaciones e informes disponibles.</div>
    </section>

    <section class="card content-panel mb-3" data-interview-step="2" hidden>
        <div class="interview-section-heading"><div><span class="interview-step">2</span><div><h2 class="h5 fw-bold mb-1">Perfil y antecedentes</h2><p class="text-muted mb-0">Carga el contexto que la IA utilizará para preparar la entrevista. Los datos se extraen automáticamente.</p></div></div></div>
        <div class="interview-automatic-panel mb-3" data-assessment-processes aria-live="polite"><i class="bi bi-hourglass-split me-2"></i>Selecciona primero un postulante.</div>
        <?php
        $documentsByType = [];
        foreach (($processDocuments ?? []) as $existingDocument) {
            $documentsByType[(string) ($existingDocument['document_type'] ?? 'other')][] = $existingDocument;
        }
        $renderExistingDocuments = static function (string $type) use ($documentsByType): void {
            $items = $documentsByType[$type] ?? [];
            if (!$items) {
                echo '<div class="interview-existing-documents is-empty"><i class="bi bi-info-circle me-1"></i>No hay un archivo cargado todavía.</div>';
                return;
            }
            echo '<div class="interview-existing-documents"><div class="fw-semibold"><i class="bi bi-check-circle me-1"></i>' . count($items) . ' archivo(s) registrado(s)</div><ul class="mb-0 mt-1">';
            foreach ($items as $item) {
                $documentUrl = route_url('interview-document.download', (int) ($item['id'] ?? 0));
                $deleteUrl = route_url('interview-document.delete', (int) ($item['id'] ?? 0));
                $documentName = (string) ($item['original_name'] ?? 'Documento');
                echo '<li><span>' . e($documentName) . ' <small>(' . e(labelize((string) ($item['processing_status'] ?? ''))) . ')</small></span><span class="interview-existing-document-actions"><a class="btn btn-sm btn-link p-1 interview-document-view" href="' . e($documentUrl) . '" data-interview-document-open data-title="' . e($documentName) . '" data-view-url="' . e($documentUrl) . '" data-download-url="' . e($documentUrl) . '" title="Ver archivo" aria-label="Ver archivo"><i class="bi bi-eye" aria-hidden="true"></i></a><button type="button" class="btn btn-sm btn-link p-1 interview-document-delete" data-interview-document-delete data-delete-url="' . e($deleteUrl) . '" data-document-name="' . e($documentName) . '" title="Eliminar archivo" aria-label="Eliminar archivo"><i class="bi bi-trash" aria-hidden="true"></i></button></span></li>';
            }
            echo '</ul></div>';
        };
        ?>
        <div class="row g-3">
            <div class="col-12 col-lg-6"><label class="form-label" for="document_job_profile">Perfil de cargo (PDF o texto)</label><input id="document_job_profile" class="form-control" type="file" name="interview_documents[job_profile]" accept=".pdf,.doc,.docx,.txt,.md"><textarea id="document_job_profile_text" class="form-control mt-2" name="interview_document_text[job_profile]" rows="5" maxlength="500000" placeholder="También puedes pegar aquí el perfil del cargo..."><?= e((string) ($_POST['interview_document_text']['job_profile'] ?? '')) ?></textarea><small class="text-muted" data-job-profile-status>La IA completará cargo, descripción, requisitos y criterios.</small><?php $renderExistingDocuments('job_profile'); ?></div>
            <div class="col-12 col-lg-6"><label class="form-label" for="document_resume">Currículum vitae (PDF o texto)</label><input id="document_resume" class="form-control" type="file" name="interview_documents[resume]" accept=".pdf,.doc,.docx,.txt,.md"><textarea id="document_resume_text" class="form-control mt-2" name="interview_document_text[resume]" rows="5" maxlength="500000" placeholder="También puedes pegar aquí el currículum..."><?= e((string) ($_POST['interview_document_text']['resume'] ?? '')) ?></textarea><?php $renderExistingDocuments('resume'); ?></div>
            <div class="col-12 col-md-6"><label class="form-label" for="document_performance">Informe de desempeño</label><input id="document_performance" class="form-control" type="file" name="interview_documents[performance]" accept=".pdf,.doc,.docx,.txt,.md"><textarea class="form-control mt-2" name="interview_document_text[performance]" rows="3" placeholder="Texto opcional del informe..."></textarea><?php $renderExistingDocuments('performance'); ?></div>
            <div class="col-12 col-md-6"><label class="form-label" for="document_psychological">Informe psicolaboral</label><input id="document_psychological" class="form-control" type="file" name="interview_documents[psychological]" accept=".pdf,.doc,.docx,.txt,.md"><textarea class="form-control mt-2" name="interview_document_text[psychological]" rows="3" placeholder="Texto opcional del informe..."></textarea><?php $renderExistingDocuments('psychological'); ?></div>
        </div>
        <div class="form-text mt-3">Los archivos se conservarán asociados a esta entrevista. Máximo 10 MB por archivo.</div>
    </section>

    <section class="card content-panel mb-3" data-interview-step="3" hidden>
        <div class="interview-section-heading"><div><span class="interview-step">3</span><div><h2 class="h5 fw-bold mb-1">Preparación automática con IA</h2><p class="text-muted mb-0">Revisa las competencias y preguntas que se generaron usando el perfil, los documentos y los resultados del postulante.</p></div></div></div>
        <div class="border rounded p-3 mt-3 bg-light-subtle">
            <div class="d-flex justify-content-between align-items-center mb-3"><div><h3 class="h6 fw-bold mb-1">Perfil detectado</h3><p class="small text-muted mb-0">Puedes corregir cualquier dato antes de generar la preparación.</p></div><span class="badge text-bg-light">Revisión humana</span></div>
            <div class="row g-3">
                <div class="col-12 col-lg-5"><label class="form-label" for="job_profile_title">Nombre del cargo</label><input id="job_profile_title" class="form-control" name="job_profile_title" maxlength="180" value="<?= e((string) ($values['job_profile_title'] ?? '')) ?>" placeholder="Se completará desde el perfil"></div>
                <div class="col-12 col-lg-7"><label class="form-label" for="job_profile_description">Descripción y responsabilidades</label><textarea id="job_profile_description" class="form-control" name="job_profile_description" rows="3" placeholder="Se completará desde el perfil"><?= e((string) ($values['job_profile_description'] ?? '')) ?></textarea></div>
                <div class="col-12 col-lg-6"><label class="form-label" for="technical_requirements">Requisitos técnicos</label><textarea id="technical_requirements" class="form-control" name="technical_requirements" rows="3" placeholder="Una competencia o requisito por línea"><?= e((string) ($values['technical_requirements'] ?? '')) ?></textarea></div>
                <div class="col-12 col-lg-6"><label class="form-label" for="behavioral_requirements">Requisitos conductuales</label><textarea id="behavioral_requirements" class="form-control" name="behavioral_requirements" rows="3" placeholder="Una competencia o requisito por línea"><?= e((string) ($values['behavioral_requirements'] ?? '')) ?></textarea></div>
                <div class="col-12"><label class="form-label">Criterios que la IA usará durante la entrevista</label><div class="row g-2"><?php for ($criterionIndex = 0; $criterionIndex < 6; $criterionIndex++): $criterion = is_array($values['evaluation_criteria'][$criterionIndex] ?? null) ? $values['evaluation_criteria'][$criterionIndex] : []; ?><div class="col-12 col-lg-6"><div class="input-group"><input class="form-control" name="evaluation_criteria[<?= $criterionIndex ?>][name]" value="<?= e((string) ($criterion['name'] ?? '')) ?>" placeholder="Criterio <?= $criterionIndex + 1 ?>"><select class="form-select" name="evaluation_criteria[<?= $criterionIndex ?>][type]"><option value="technical" <?= ($criterion['type'] ?? '') === 'technical' ? 'selected' : '' ?>>Técnico</option><option value="behavioral" <?= ($criterion['type'] ?? '') === 'behavioral' ? 'selected' : '' ?>>Conductual</option><option value="general" <?= ($criterion['type'] ?? '') === 'general' || empty($criterion['type']) ? 'selected' : '' ?>>General</option></select><input class="form-control" style="max-width:82px" type="number" min="0" max="100" name="evaluation_criteria[<?= $criterionIndex ?>][weight]" value="<?= e((string) ($criterion['weight'] ?? 0)) ?>" aria-label="Peso"></div></div><?php endfor; ?></div><small class="text-muted">Los pesos son orientativos y se pueden ajustar antes de guardar.</small></div>
            </div>
        </div>
        <div class="interview-automatic-panel mt-3" data-preparation-status><i class="bi bi-stars me-2"></i>Generaremos el resumen, los puntos a evaluar y las preguntas sugeridas.</div>
        <div class="row g-3 mt-1" data-preparation-result hidden><div class="col-12 col-lg-5"><h3 class="h6 fw-bold">Resumen</h3><p class="text-muted" data-preparation-summary></p><h3 class="h6 fw-bold mt-4">Competencias a validar</h3><div data-preparation-competencies></div></div><div class="col-12 col-lg-7"><h3 class="h6 fw-bold">Preguntas sugeridas</h3><ol class="interview-brief-list" data-preparation-questions></ol></div></div>
        <div class="mt-3" data-preparation-approval hidden><label class="form-check"><input class="form-check-input" type="checkbox" data-preparation-approval-check><span class="form-check-label">He revisado la preparación y autorizo avanzar a la agenda.</span></label></div>
        <button class="btn btn-primary mt-3" type="button" data-generate-preparation><i class="bi bi-stars me-1"></i> Generar preparación</button>
    </section>

    <section class="card content-panel mb-3" data-interview-step="4" hidden>
        <div class="interview-section-heading"><div><span class="interview-step">4</span><div><h2 class="h5 fw-bold mb-1">Agendar entrevista</h2><p class="text-muted mb-0">La agenda se habilita después de aprobar la preparación de IA.</p></div></div></div>
        <div class="row g-3"><div class="col-12 col-lg-6"><label class="form-label" for="name">Referencia de la entrevista</label><input id="name" class="form-control" name="name" value="<?= e($values['name']) ?>" maxlength="180" placeholder="Ej.: Entrevista Analista de operaciones" required></div><div class="col-12 col-sm-6 col-lg-3"><label class="form-label" for="interview_date">Fecha</label><input id="interview_date" class="form-control" type="date" name="interview_date" value="<?= e($values['interview_date']) ?>" required></div><div class="col-12 col-sm-6 col-lg-3"><label class="form-label" for="starts_at">Hora de inicio</label><input id="starts_at" class="form-control" type="time" name="starts_at" value="<?= e(substr((string) $values['starts_at'], 0, 5)) ?>" required></div><div class="col-12 col-md-4"><label class="form-label" for="slot_duration_minutes">Duración (minutos)</label><input id="slot_duration_minutes" class="form-control" type="number" min="5" max="240" name="slot_duration_minutes" value="<?= (int) $values['slot_duration_minutes'] ?>" required></div><div class="col-12 col-md-4"><label class="form-label" for="moderator_user_id">Entrevistador</label><select id="moderator_user_id" class="form-select" name="moderator_user_id" required><?php foreach ($moderators as $moderator): ?><option value="<?= (int) $moderator['id'] ?>" <?= (int) $values['moderator_user_id'] === (int) $moderator['id'] ? 'selected' : '' ?>><?= e($moderator['name']) ?><?= $moderator['profile_name'] ? ' · ' . e($moderator['profile_name']) : '' ?></option><?php endforeach; ?></select></div><div class="col-12 col-md-4"><label class="form-label" for="status">Estado</label><select id="status" class="form-select" name="status"><option value="draft">Borrador</option><option value="scheduled">Agendada</option></select></div></div>
    </section>

    <div class="interview-step-actions mb-4"><a class="btn btn-outline-secondary" href="<?= e(route_url('interviews')) ?>">Cancelar</a><button class="btn btn-outline-secondary" type="button" data-interview-prev hidden><i class="bi bi-arrow-left me-1"></i> Atrás</button><button class="btn btn-primary" type="button" data-interview-next>Continuar <i class="bi bi-arrow-right ms-1"></i></button><button class="btn btn-primary" type="submit" data-interview-submit hidden><i class="bi bi-calendar2-check me-1"></i> Confirmar y agendar</button></div>
</form>
<form id="interview-document-delete-form" method="post" hidden><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"></form>
<script src="<?= e(url('assets/js/interviews/interviews.js') . '?v=' . filemtime(BASE_PATH . '/public/assets/js/interviews/interviews.js')) ?>"></script>
<script defer src="<?= e(url('assets/js/interviews/interview-registration.js') . '?v=' . filemtime(BASE_PATH . '/public/assets/js/interviews/interview-registration.js')) ?>"></script>
