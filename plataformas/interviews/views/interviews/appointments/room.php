<link href="<?= e(url('assets/css/interviews/interviews.css')) ?>" rel="stylesheet">

<section class="page-header">
    <div>
        <p class="text-uppercase text-primary fw-bold small mb-1">Sala de entrevista</p>
        <h1 class="fw-bold mb-1"><?= e($appointment['candidate_name'] ?? 'Postulante') ?></h1>
        <p class="text-muted mb-0"><?= e($appointment['process_name'] ?? '') ?> · <?= e(date('d/m/Y H:i', strtotime((string) $appointment['scheduled_start_at']))) ?></p>
    </div>
    <?php if ($isModerator): ?>
        <button class="btn btn-primary" type="button" data-interview-finish data-url="<?= e(route_url('interview-appointment.finish', (int) $appointment['id'])) ?>" data-csrf="<?= e(csrf_token()) ?>">
            <i class="bi bi-stop-circle me-1"></i> Finalizar entrevista
        </button>
    <?php endif; ?>
</section>

<div class="interview-room-grid">
    <section class="content-panel">
        <?php if ($roomBlocked): ?>
            <div class="interview-preflight-card" data-interview-preflight>
                <div class="d-flex flex-wrap align-items-start justify-content-between gap-3 mb-4">
                    <div>
                        <p class="text-uppercase text-primary fw-bold small mb-1">Validación previa</p>
                        <h2 class="h4 fw-bold mb-2">Revisa la sala antes de ingresar</h2>
                        <p class="text-muted mb-0">Corrige los elementos críticos para habilitar la videollamada.</p>
                    </div>
                    <a class="btn btn-outline-secondary" href="<?= e(route_url('interview-process.show', (int) $appointment['process_id'])) ?>">Volver al proceso</a>
                </div>
                <div class="list-group">
                    <?php foreach (($preflight['checks'] ?? []) as $check): ?>
                        <?php $isError = (string) ($check['status'] ?? '') === 'error'; ?>
                        <div class="list-group-item d-flex gap-3 align-items-start">
                            <span class="badge rounded-pill text-bg-<?= $isError ? 'danger' : ((string) ($check['status'] ?? '') === 'ok' ? 'success' : 'warning') ?> mt-1">
                                <?= $isError ? 'Error' : ((string) ($check['status'] ?? '') === 'ok' ? 'OK' : 'Aviso') ?>
                            </span>
                            <div>
                                <div class="fw-semibold"><?= e((string) ($check['label'] ?? 'Validación')) ?></div>
                                <div class="small text-muted"><?= e((string) ($check['detail'] ?? '')) ?></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php else: ?>
            <?php $meeting = $dailyPayload; require BASE_PATH . '/plug-and-play/daily-video-kit/views/daily-meeting.partial.php'; ?>
        <?php endif; ?>
    </section>

    <?php if ($isModerator): ?>
        <aside class="interview-panel-stack">
            <section class="content-panel interview-tabs-panel">
                <div class="interview-tabs-scroll">
                    <ul class="nav nav-tabs interview-room-tabs" id="interviewRoomTabs" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" id="interview-documents-tab" data-bs-toggle="tab" data-bs-target="#interview-documents-pane" type="button" role="tab" aria-controls="interview-documents-pane" aria-selected="true">
                                <i class="bi bi-folder2-open me-1"></i> Documentos
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="interview-brief-tab" data-bs-toggle="tab" data-bs-target="#interview-brief-pane" type="button" role="tab" aria-controls="interview-brief-pane" aria-selected="false">
                                <i class="bi bi-list-check me-1"></i> Resumen y preguntas claves
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="interview-notes-tab" data-bs-toggle="tab" data-bs-target="#interview-notes-pane" type="button" role="tab" aria-controls="interview-notes-pane" aria-selected="false">
                                <i class="bi bi-pencil-square me-1"></i> Apuntes del moderador
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="interview-final-report-tab" data-bs-toggle="tab" data-bs-target="#interview-final-report-pane" type="button" role="tab" aria-controls="interview-final-report-pane" aria-selected="false">
                                <i class="bi bi-file-earmark-text me-1"></i> Reporte final
                            </button>
                        </li>
                    </ul>
                </div>

                <div class="tab-content interview-room-tab-content" id="interviewRoomTabsContent">
                    <div class="tab-pane fade show active" id="interview-documents-pane" role="tabpanel" aria-labelledby="interview-documents-tab" tabindex="0">
                        <h2 class="h5 fw-bold mb-3">Informe del postulante</h2>
                        <?php if ($reportUrl): ?>
                            <button
                                class="btn btn-outline-primary w-100"
                                type="button"
                                data-interview-document-open
                                data-bs-toggle="modal"
                                data-bs-target="#interviewDocumentModal"
                                data-title="Informe del postulante"
                                data-view-url="<?= e($reportViewUrl) ?>"
                                data-download-url="<?= e($reportUrl) ?>">
                                <i class="bi bi-filetype-pdf me-1"></i> Ver informe PDF
                            </button>
                        <?php else: ?>
                            <p class="text-muted mb-0">No hay informe asociado a esta entrevista.</p>
                        <?php endif; ?>
                        <hr class="my-4">
                        <h3 class="h6 fw-bold">Antecedentes adicionales</h3>
                        <form method="post" enctype="multipart/form-data" action="<?= e(route_url('interview-appointment.room', (int) $appointment['id'])) ?>" class="row g-2 mb-3">
                            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                            <input type="hidden" name="upload_document" value="1">
                            <div class="col-12">
                                <select class="form-select" name="document_type" aria-label="Tipo de documento">
                                    <option value="performance">Informe de desempeño</option>
                                    <option value="psychological">Informe psicológico</option>
                                    <option value="job_profile">Perfil de cargo</option>
                                    <option value="resume">Currículum</option>
                                    <option value="reference">Referencia laboral</option>
                                    <option value="other">Otro antecedente</option>
                                </select>
                            </div>
                            <div class="col-12"><input class="form-control" type="file" name="interview_document" required accept=".pdf,.doc,.docx,.xls,.xlsx,.csv,.txt,.md,.json"></div>
                            <div class="col-12"><button class="btn btn-outline-primary w-100" type="submit"><i class="bi bi-upload me-1"></i> Asociar documento</button></div>
                        </form>
                        <?php if (!empty($documents)): ?>
                            <div class="list-group small">
                                <?php foreach ($documents as $document): ?>
                                    <a class="list-group-item list-group-item-action d-flex justify-content-between align-items-center" href="<?= e(route_url('interview-document.download', (int) $document['id'])) ?>" target="_blank" rel="noopener">
                                        <span><i class="bi bi-file-earmark-text me-1"></i><?= e((string) $document['original_name']) ?><br><small class="text-muted"><?= e(labelize((string) $document['document_type'])) ?> · <?= e(labelize((string) $document['processing_status'])) ?></small></span>
                                        <i class="bi bi-box-arrow-up-right"></i>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="tab-pane fade" id="interview-brief-pane" role="tabpanel" aria-labelledby="interview-brief-tab" tabindex="0">
                        <h2 class="h5 fw-bold mb-2">Resumen y preguntas claves</h2>
                        <p class="text-muted"><?= e((string) ($brief['summary'] ?? $appointment['moderator_brief_error'] ?? 'Brief pendiente de generacion.')) ?></p>
                        <?php $questions = is_array($brief['questions'] ?? null) ? $brief['questions'] : []; ?>
                        <?php if ($questions): ?>
                            <ol class="interview-brief-list">
                                <?php foreach ($questions as $question): ?>
                                    <li><?= e((string) $question) ?></li>
                                <?php endforeach; ?>
                            </ol>
                        <?php endif; ?>
                    </div>

                    <div class="tab-pane fade" id="interview-notes-pane" role="tabpanel" aria-labelledby="interview-notes-tab" tabindex="0">
                        <h2 class="h5 fw-bold mb-3">Apuntes del moderador</h2>
                        <form data-interview-notes action="<?= e(route_url('interview-appointment.notes', (int) $appointment['id'])) ?>" method="post">
                            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                            <textarea class="form-control" name="notes" rows="8" placeholder="Registra observaciones, evidencia y puntos a profundizar."><?= e($notes) ?></textarea>
                            <button class="btn btn-outline-primary mt-3" type="submit"><i class="bi bi-save me-1"></i> Guardar apuntes</button>
                        </form>
                    </div>

                    <div class="tab-pane fade" id="interview-final-report-pane" role="tabpanel" aria-labelledby="interview-final-report-tab" tabindex="0">
                        <h2 class="h5 fw-bold mb-2">Reporte final</h2>
                        <p class="text-muted mb-3">Estado: <?= e(labelize((string) $appointment['final_report_status'])) ?></p>
                        <?php if ((string) $appointment['final_report_status'] === 'ready'): ?>
                            <div class="interview-report-preview mb-3"><?= $finalReportHtml ?></div>
                            <div class="d-grid gap-2">
                                <button
                                    class="btn btn-outline-primary"
                                    type="button"
                                    data-interview-document-open
                                    data-bs-toggle="modal"
                                    data-bs-target="#interviewDocumentModal"
                                    data-title="Reporte final de entrevista"
                                    data-view-url="<?= e($finalReportViewUrl) ?>"
                                    data-download-url="<?= e($finalReportUrl) ?>">
                                    <i class="bi bi-eye me-1"></i> Ver PDF reporte final
                                </button>
                                <a class="btn btn-outline-primary" href="<?= e($finalReportUrl) ?>"><i class="bi bi-download me-1"></i> Descargar PDF</a>
                            </div>
                        <?php elseif (!empty($appointment['final_report_error'])): ?>
                            <p class="text-muted mb-0"><?= e($appointment['final_report_error']) ?></p>
                        <?php endif; ?>
                    </div>
                </div>
            </section>
        </aside>

        <?php if ($reportUrl || $finalReportUrl): ?>
            <div class="modal fade interview-pdf-modal" id="interviewDocumentModal" tabindex="-1" aria-labelledby="interviewDocumentModalLabel" aria-hidden="true">
                <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
                    <div class="modal-content">
                        <div class="modal-header">
                            <div>
                                <p class="text-uppercase text-primary fw-bold small mb-1">Documentos</p>
                                <h2 class="modal-title h5 fw-bold" id="interviewDocumentModalLabel" data-interview-document-title>Documento</h2>
                            </div>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                        </div>
                        <div class="modal-toolbar interview-document-toolbar">
                            <a class="btn btn-outline-primary" href="#" data-interview-document-download>
                                <i class="bi bi-download me-1"></i> Descargar PDF
                            </a>
                            <a class="btn btn-outline-secondary" href="#" target="_blank" rel="noopener" data-interview-document-open-tab>
                                <i class="bi bi-box-arrow-up-right me-1"></i> Abrir en pestaña
                            </a>
                        </div>
                        <div class="modal-body">
                            <iframe class="interview-pdf-frame" src="about:blank" title="Visualizador PDF" data-interview-document-frame></iframe>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<script src="<?= e(url('assets/js/interviews/daily-meeting.js')) ?>"></script>
<script src="<?= e(url('assets/js/interviews/interviews.js')) ?>"></script>
