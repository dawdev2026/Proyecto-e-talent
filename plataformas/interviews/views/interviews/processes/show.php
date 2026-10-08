<section class="page-header">
    <div>
        <p class="text-uppercase text-primary fw-bold small mb-1">Entrevistas seleccion</p>
        <h1 class="fw-bold mb-1"><?= e($process['name']) ?></h1>
        <p class="text-muted mb-0"><?= e(date('d/m/Y', strtotime((string) $process['interview_date']))) ?> · Entrevistador: <?= e($process['moderator_name'] ?? 'Sin entrevistador') ?></p>
    </div>
    <?php if (is_company_admin_user() || has_permission('manage_interview_processes') || has_permission('manage_company_interviews')): ?>
        <a class="btn btn-outline-primary" href="<?= e(route_url('interview-process.edit', (int) $process['id'])) ?>"><i class="bi bi-pencil me-1"></i> Editar proceso</a>
    <?php endif; ?>
</section>

<section class="row g-3 mb-4">
    <div class="col-12 col-xl-7">
        <div class="card content-panel h-100">
            <h2 class="h5 fw-bold mb-2">Perfil del cargo</h2>
            <?php if (!empty($jobProfile)): ?>
                <p class="fw-semibold mb-1"><?= e((string) ($jobProfile['title'] ?? '')) ?></p>
                <p class="text-muted mb-3"><?= nl2br(e((string) ($jobProfile['description'] ?? ''))) ?></p>
                <div class="row g-3 small">
                    <div class="col-md-6"><strong>Requisitos técnicos</strong><div class="text-muted mt-1"><?= nl2br(e((string) ($jobProfile['technical_requirements'] ?? 'Sin definir'))) ?></div></div>
                    <div class="col-md-6"><strong>Requisitos conductuales</strong><div class="text-muted mt-1"><?= nl2br(e((string) ($jobProfile['behavioral_requirements'] ?? 'Sin definir'))) ?></div></div>
                </div>
            <?php else: ?>
                <p class="text-muted mb-0">Aún no se ha definido el perfil del cargo.</p>
            <?php endif; ?>
        </div>
    </div>
    <?php if (is_company_admin_user() || has_permission('manage_interview_processes') || has_permission('manage_company_interviews')): ?>
        <div class="col-12 col-xl-5">
            <div class="card content-panel h-100">
                <h2 class="h5 fw-bold mb-2">Antecedentes del proceso</h2>
                <p class="small text-muted">Carga documentos generales o asígnalos a un postulante específico. Quedarán disponibles para preparar todas sus entrevistas.</p>
                <form method="post" enctype="multipart/form-data" action="<?= e(route_url('interview-process.document', (int) $process['id'])) ?>" class="row g-2">
                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                    <div class="col-12"><select class="form-select" name="document_type" aria-label="Tipo de documento"><option value="job_profile">Perfil de cargo</option><option value="performance">Informe de desempeño</option><option value="psychological">Informe psicológico</option><option value="resume">Currículum</option><option value="reference">Referencia laboral</option><option value="other">Otro antecedente</option></select></div>
                    <div class="col-12"><select class="form-select" name="candidate_user_id" aria-label="Postulante opcional"><option value="0">General del proceso</option><?php foreach ($appointments as $appointment): ?><option value="<?= (int) $appointment['candidate_user_id'] ?>"><?= e((string) ($appointment['candidate_name'] ?? 'Postulante')) ?></option><?php endforeach; ?></select></div>
                    <div class="col-12"><input class="form-control" type="file" name="interview_document" required accept=".pdf,.doc,.docx,.xls,.xlsx,.csv,.txt,.md,.json"><div class="form-text">Máximo 10 MB.</div></div>
                    <div class="col-12"><button class="btn btn-outline-primary w-100" type="submit"><i class="bi bi-upload me-1"></i> Asociar antecedente</button></div>
                </form>
            </div>
        </div>
    <?php endif; ?>
</section>

<?php $primaryAppointment = $appointments[0] ?? null; $testReportUrl = ''; if ($primaryAppointment && !empty($primaryAppointment['test_process_id']) && !empty($primaryAppointment['test_session_id'])) { $testReportUrl = route_url('test-process.ranking-report', (int) $primaryAppointment['test_process_id']) . '?session=' . rawurlencode(secure_url_token((int) $primaryAppointment['test_session_id'], 'test_session')); } ?>
<section class="card content-panel mb-4">
    <div class="d-flex flex-wrap align-items-start justify-content-between gap-3">
        <div>
            <p class="text-uppercase text-primary fw-bold small mb-1">Antecedentes del postulante</p>
            <h2 class="h5 fw-bold mb-1"><?= e((string) ($primaryAppointment['candidate_name'] ?? 'Postulante')) ?></h2>
            <p class="text-muted mb-0">Aquí se concentran los informes de evaluación/test y los documentos que utilizará el entrevistador.</p>
        </div>
        <?php if ($testReportUrl): ?><a class="btn btn-outline-primary" href="<?= e($testReportUrl) ?>" target="_blank" rel="noopener"><i class="bi bi-file-earmark-bar-graph me-1"></i> Ver informe de evaluación</a><?php endif; ?>
    </div>
    <?php if (!$primaryAppointment || empty($primaryAppointment['test_process_id'])): ?><div class="interview-context-note mt-3"><i class="bi bi-info-circle me-2"></i>No hay un informe/test asociado a esta entrevista. Puedes editarla para vincular un proceso de evaluación.</div><?php elseif (!$testReportUrl): ?><div class="interview-context-note mt-3"><i class="bi bi-hourglass-split me-2"></i>El proceso de evaluación está asociado, pero el informe todavía no está disponible para este postulante.</div><?php endif; ?>
</section>

<?php if (!empty($processDocuments)): ?>
    <section class="card content-panel mb-4">
        <h2 class="h5 fw-bold mb-3">Documentos registrados</h2>
        <div class="table-responsive"><table class="table table-sm align-middle mb-0"><thead><tr><th>Documento</th><th>Alcance</th><th>Estado</th><th class="text-end">Acción</th></tr></thead><tbody>
            <?php foreach ($processDocuments as $document): ?><tr><td><?= e((string) $document['original_name']) ?><br><small class="text-muted"><?= e(labelize((string) $document['document_type'])) ?></small></td><td><?= e((string) ($document['candidate_name'] ?? 'General del proceso')) ?></td><td><?= e(labelize((string) $document['processing_status'])) ?></td><td class="text-end"><?php if (!is_company_admin_user()): ?><a class="btn btn-sm btn-outline-secondary" href="<?= e(route_url('interview-document.download', (int) $document['id'])) ?>" target="_blank" rel="noopener">Ver</a><?php else: ?><span class="text-muted small">Solo agenda</span><?php endif; ?></td></tr><?php endforeach; ?>
        </tbody></table></div>
    </section>
<?php endif; ?>

<section class="card content-panel">
    <div class="table-responsive">
        <table class="table table-hover align-middle app-table app-data-table" data-export-title="Agenda entrevistas">
            <thead>
                <tr>
                    <th>Horario</th>
                    <th>Postulante</th>
                    <th>Informe</th>
                    <th>Antecedentes</th>
                    <th>Transcripcion</th>
                    <th>Evaluación</th>
                    <th>Reporte</th>
                    <th class="text-end no-sort no-export">Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($appointments as $appointment): ?>
                    <tr>
                        <td>
                            <strong><?= e(date('H:i', strtotime((string) $appointment['scheduled_start_at']))) ?></strong>
                            <span class="text-muted">- <?= e(date('H:i', strtotime((string) $appointment['scheduled_end_at']))) ?></span>
                            <?php if ((int) $appointment['manual_override'] === 1): ?><span class="badge text-bg-info ms-1">Manual</span><?php endif; ?>
                        </td>
                        <td>
                            <div class="fw-semibold"><?= e($appointment['candidate_name'] ?? 'Postulante') ?></div>
                            <small class="text-muted"><?= e($appointment['candidate_email'] ?? '') ?></small>
                        </td>
                        <td><?= !empty($appointment['test_session_id']) ? '<span class="badge text-bg-success">Asociado</span>' : '<span class="badge text-bg-secondary">Sin informe</span>' ?></td>
                        <td><span class="badge text-bg-secondary"><?= (int) ($appointment['document_count'] ?? 0) ?></span></td>
                        <td><span class="badge text-bg-secondary"><?= e(labelize((string) $appointment['transcript_status'])) ?></span></td>
                        <td><span class="badge text-bg-secondary"><?= (int) ($appointment['evaluations_submitted'] ?? 0) ?> completa(s)</span></td>
                        <td><span class="badge text-bg-secondary"><?= e(labelize((string) $appointment['final_report_status'])) ?></span></td>
                        <td class="text-end">
                            <?php $currentUserId = (int) (current_user()['id'] ?? 0); $canEnterRoom = !is_company_admin_user() || $currentUserId === (int) ($appointment['moderator_user_id'] ?? 0); ?>
                            <?php if ($canEnterRoom): ?>
                                <a class="btn btn-sm btn-primary" href="<?= e(route_url('interview-appointment.room', (int) $appointment['id'])) ?>"><i class="bi bi-camera-video me-1"></i> Ingresar a entrevista</a>
                            <?php endif; ?>
                            <?php if ($canEnterRoom && (string) $appointment['final_report_status'] === 'ready'): ?>
                                <a class="btn btn-sm btn-outline-secondary" href="<?= e(route_url('interview-appointment.report', (int) $appointment['id'])) ?>"><i class="bi bi-filetype-pdf me-1"></i> PDF</a>
                            <?php endif; ?>
                            <?php
                                $reportStatus = (string) ($appointment['final_report_status'] ?? 'not_started');
                                $canGenerateReport = is_company_admin_user()
                                    || has_permission('view_interview_reports')
                                    || has_permission('manage_interview_processes')
                                    || has_permission('manage_company_interviews');
                            ?>
                            <?php if ($canGenerateReport && $reportStatus !== 'ready' && (string) ($appointment['meeting_status'] ?? '') === 'finished'): ?>
                                <?php $reportAction = route_url('interviews.process-job') . '?sid=' . rawurlencode(secure_url_token((int) $appointment['id'], 'interview_appointment')); ?>
                                <?php if (in_array($reportStatus, ['pending', 'processing'], true)): ?>
                                    <span class="badge text-bg-warning d-block mt-2">Informe en proceso</span>
                                <?php endif; ?>
                                <form method="post" class="mt-2" action="<?= e($reportAction) ?>">
                                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                    <button class="btn btn-sm btn-outline-primary w-100" type="submit"><i class="bi bi-file-earmark-text me-1"></i><?= $reportStatus === 'failed' ? 'Reintentar informe' : 'Generar informe ahora' ?></button>
                                </form>
                            <?php endif; ?>
                            <?php if (is_company_admin_user() || has_permission('manage_interview_processes') || has_permission('manage_company_interviews')): ?>
                                <details class="mt-2 text-start">
                                    <summary class="btn btn-sm btn-outline-secondary">Ajustar horario</summary>
                                    <form method="post" class="row g-2 align-items-end mt-2" action="<?= e(route_url('interview-appointment.schedule', (int) $appointment['id'])) ?>">
                                        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                        <div class="col-12">
                                            <label class="form-label small" for="start_<?= (int) $appointment['id'] ?>">Inicio manual</label>
                                            <input id="start_<?= (int) $appointment['id'] ?>" class="form-control form-control-sm" type="datetime-local" name="scheduled_start_at" value="<?= e(date('Y-m-d\TH:i', strtotime((string) $appointment['scheduled_start_at']))) ?>">
                                        </div>
                                        <div class="col-12">
                                            <label class="form-label small" for="end_<?= (int) $appointment['id'] ?>">Termino manual</label>
                                            <input id="end_<?= (int) $appointment['id'] ?>" class="form-control form-control-sm" type="datetime-local" name="scheduled_end_at" value="<?= e(date('Y-m-d\TH:i', strtotime((string) $appointment['scheduled_end_at']))) ?>">
                                        </div>
                                        <div class="col-12">
                                            <label class="form-label small" for="moderator_<?= (int) $appointment['id'] ?>">Entrevistador</label>
                                            <select id="moderator_<?= (int) $appointment['id'] ?>" class="form-select form-select-sm" name="moderator_user_id">
                                                <?php foreach ($moderators as $moderator): ?>
                                                    <option value="<?= (int) $moderator['id'] ?>" <?= (int) $appointment['moderator_user_id'] === (int) $moderator['id'] ? 'selected' : '' ?>><?= e($moderator['name']) ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="col-12">
                                            <button class="btn btn-sm btn-outline-primary w-100" type="submit"><i class="bi bi-check2 me-1"></i> Guardar</button>
                                        </div>
                                    </form>
                                </details>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
