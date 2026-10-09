<link href="<?= e(url('assets/css/interviews/interviews.css') . '?v=' . filemtime(BASE_PATH . '/public/assets/css/interviews/interviews.css')) ?>" rel="stylesheet">

<section class="page-header interview-room-header">
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
    <section class="card content-panel">
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
            <section class="card content-panel interview-tabs-panel">
                <div class="interview-tabs-scroll">
                    <ul class="nav nav-tabs interview-room-tabs" id="interviewRoomTabs" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" id="interview-documents-tab" data-bs-toggle="tab" data-bs-target="#interview-documents-pane" type="button" role="tab" aria-controls="interview-documents-pane" aria-label="Documentos" title="Documentos" aria-selected="true">
                                <i class="bi bi-folder2-open" aria-hidden="true"></i>
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="interview-brief-tab" data-bs-toggle="tab" data-bs-target="#interview-brief-pane" type="button" role="tab" aria-controls="interview-brief-pane" aria-label="Resumen y puntos a evaluar" title="Resumen y puntos a evaluar" aria-selected="false">
                                <i class="bi bi-list-check" aria-hidden="true"></i>
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="interview-notes-tab" data-bs-toggle="tab" data-bs-target="#interview-notes-pane" type="button" role="tab" aria-controls="interview-notes-pane" aria-label="Apuntes del entrevistador" title="Apuntes del entrevistador" aria-selected="false">
                                <i class="bi bi-pencil-square" aria-hidden="true"></i>
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="interview-evaluation-tab" data-bs-toggle="tab" data-bs-target="#interview-evaluation-pane" type="button" role="tab" aria-controls="interview-evaluation-pane" aria-selected="false" aria-label="Evaluación" title="Evaluación">
                                <i class="bi bi-clipboard2-check" aria-hidden="true"></i>
                            </button>
                        </li>
                    </ul>
                </div>

                <div class="tab-content interview-room-tab-content interview-tab-body-scroll" id="interviewRoomTabsContent">
                    <div class="tab-pane fade show active" id="interview-documents-pane" role="tabpanel" aria-labelledby="interview-documents-tab" tabindex="0">
                        <h2 class="h5 fw-bold mb-3">Documentos del postulante</h2>
                        <?php if ($reportUrl): ?>
                            <button
                                class="btn btn-outline-primary w-100"
                                type="button"
                                data-interview-document-open
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
                                    <?php $documentUrl = route_url('interview-document.download', (int) $document['id']); ?>
                                    <a class="list-group-item list-group-item-action d-flex justify-content-between align-items-center" href="<?= e($documentUrl) ?>" data-interview-document-open data-title="<?= e((string) $document['original_name']) ?>" data-view-url="<?= e($documentUrl) ?>" data-download-url="<?= e($documentUrl) ?>">
                                        <span><i class="bi bi-file-earmark-text me-1"></i><?= e((string) $document['original_name']) ?><br><small class="text-muted"><?= e(labelize((string) $document['document_type'])) ?> · <?= e(labelize((string) $document['processing_status'])) ?></small></span>
                                        <i class="bi bi-box-arrow-up-right"></i>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="tab-pane fade" id="interview-brief-pane" role="tabpanel" aria-labelledby="interview-brief-tab" tabindex="0">
                        <h2 class="h5 fw-bold mb-2">Resumen y preguntas claves</h2>
                        <p class="text-muted"><?= e((string) ($brief['summary'] ?? $appointment['moderator_brief_error'] ?? 'Preparación automática pendiente.')) ?></p>
                        <?php $questions = is_array($brief['questions'] ?? null) ? $brief['questions'] : []; ?>
                        <?php if ($questions): ?>
                            <ol class="interview-brief-list">
                                <?php foreach ($questions as $question): ?>
                                    <?php $questionText = is_array($question) ? (string) ($question['question'] ?? '') : (string) $question; ?>
                                    <li>
                                        <?= e($questionText) ?>
                                        <?php if (is_array($question) && trim((string) ($question['competency'] ?? '')) !== ''): ?><small class="d-block text-muted mt-1">Competencia: <?= e((string) $question['competency']) ?></small><?php endif; ?>
                                        <?php if (is_array($question) && trim((string) ($question['expected_evidence'] ?? '')) !== ''): ?><small class="d-block text-muted">Evidencia esperada: <?= e((string) $question['expected_evidence']) ?></small><?php endif; ?>
                                        <?php if (is_array($question) && !empty($question['follow_ups']) && is_array($question['follow_ups'])): ?><ul class="small text-muted mb-0 mt-1"><?php foreach ($question['follow_ups'] as $followUp): ?><li><?= e((string) $followUp) ?></li><?php endforeach; ?></ul><?php endif; ?>
                                    </li>
                                <?php endforeach; ?>
                            </ol>
                        <?php endif; ?>
                        <?php $competencies = is_array($brief['competencies'] ?? null) ? $brief['competencies'] : []; ?>
                        <?php if ($competencies): ?>
                            <hr class="my-4">
                            <h3 class="h6 fw-bold">Competencias detectadas por IA</h3>
                            <div class="row g-2">
                                <?php foreach ($competencies as $competency): ?>
                                    <?php if (!is_array($competency)) { continue; } ?>
                                    <div class="col-12 col-lg-6"><div class="border rounded p-2 h-100"><strong><?= e((string) ($competency['name'] ?? '')) ?></strong><small class="d-block text-muted"><?= e((string) ($competency['relevance'] ?? '')) ?></small><?php if (!empty($competency['evidence_to_validate'])): ?><small class="d-block mt-1">Validar: <?= e((string) $competency['evidence_to_validate']) ?></small><?php endif; ?></div></div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                        <?php $criteria = json_decode((string) ($jobProfile['evaluation_criteria_json'] ?? '[]'), true); $criteria = is_array($criteria) ? array_values(array_filter($criteria, static fn($criterion): bool => is_array($criterion) && trim((string) ($criterion['name'] ?? '')) !== '')) : []; ?>
                        <?php if ($criteria): ?>
                            <hr class="my-4">
                            <h3 class="h6 fw-bold">Puntos a evaluar</h3>
                            <ul class="interview-brief-list mb-0">
                                <?php foreach ($criteria as $criterion): ?>
                                    <li>
                                        <?= e(trim((string) ($criterion['name'] ?? ''))) ?>
                                        <?php if ((int) ($criterion['weight'] ?? 0) > 0): ?><span class="text-muted">(<?= (int) $criterion['weight'] ?>%)</span><?php endif; ?>
                                        <?php if (!empty($criterion['type'])): ?><span class="badge text-bg-light ms-1"><?= e(labelize((string) $criterion['type'])) ?></span><?php endif; ?>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </div>

                    <div class="tab-pane fade" id="interview-notes-pane" role="tabpanel" aria-labelledby="interview-notes-tab" tabindex="0">
                        <h2 class="h5 fw-bold mb-3">Apuntes del entrevistador</h2>
                        <form data-interview-notes action="<?= e(route_url('interview-appointment.notes', (int) $appointment['id'])) ?>" method="post">
                            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                            <textarea class="form-control" name="notes" rows="8" placeholder="Registra observaciones, evidencia y puntos a profundizar."><?= e($notes) ?></textarea>
                            <button class="btn btn-outline-primary mt-3" type="submit"><i class="bi bi-save me-1"></i> Guardar apuntes</button>
                        </form>
                    </div>

                    <div class="tab-pane fade" id="interview-evaluation-pane" role="tabpanel" aria-labelledby="interview-evaluation-tab" tabindex="0">
                        <h2 class="h5 fw-bold mb-2">Evaluación del entrevistador</h2>
                        <p class="small text-muted">Registra la evidencia observada y la recomendación. La IA no reemplaza esta evaluación.</p>
                        <form data-interview-evaluation action="<?= e(route_url('interview-appointment.evaluation', (int) $appointment['id'])) ?>" method="post">
                            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                            <div class="row g-2 mb-3">
                                <div class="col-4"><label class="form-label small" for="technical_score">Técnico</label><input id="technical_score" class="form-control" type="number" min="0" max="100" name="technical_score" value="<?= e((string) ($evaluation['technical_score'] ?? '')) ?>"></div>
                                <div class="col-4"><label class="form-label small" for="behavioral_score">Conductual</label><input id="behavioral_score" class="form-control" type="number" min="0" max="100" name="behavioral_score" value="<?= e((string) ($evaluation['behavioral_score'] ?? '')) ?>"></div>
                                <div class="col-4"><label class="form-label small" for="overall_score">Global</label><input id="overall_score" class="form-control" type="number" min="0" max="100" name="overall_score" value="<?= e((string) ($evaluation['overall_score'] ?? '')) ?>"></div>
                            </div>
                            <label class="form-label small" for="recommendation">Recomendación</label>
                            <select id="recommendation" class="form-select mb-3" name="recommendation"><option value="pending" <?= ($evaluation['recommendation'] ?? 'pending') === 'pending' ? 'selected' : '' ?>>Pendiente</option><option value="recommended" <?= ($evaluation['recommendation'] ?? '') === 'recommended' ? 'selected' : '' ?>>Recomendado</option><option value="hold" <?= ($evaluation['recommendation'] ?? '') === 'hold' ? 'selected' : '' ?>>En revisión</option><option value="not_recommended" <?= ($evaluation['recommendation'] ?? '') === 'not_recommended' ? 'selected' : '' ?>>No recomendado</option></select>
                            <label class="form-label small" for="strengths">Fortalezas observadas</label><textarea id="strengths" class="form-control mb-2" name="strengths" rows="3"><?= e((string) ($evaluation['strengths'] ?? '')) ?></textarea>
                            <label class="form-label small" for="risks">Riesgos o brechas</label><textarea id="risks" class="form-control mb-2" name="risks" rows="3"><?= e((string) ($evaluation['risks'] ?? '')) ?></textarea>
                            <label class="form-label small" for="comments">Comentarios y evidencia</label><textarea id="comments" class="form-control" name="comments" rows="4"><?= e((string) ($evaluation['comments'] ?? '')) ?></textarea>
                            <div class="d-flex gap-2 mt-3"><button class="btn btn-outline-primary" type="submit" name="status" value="draft"><i class="bi bi-save me-1"></i> Guardar borrador</button><button class="btn btn-primary" type="submit" name="status" value="submitted"><i class="bi bi-check2-circle me-1"></i> Marcar completada</button></div>
                        </form>
                    </div>

                </div>
            </section>
        </aside>

    <?php endif; ?>
</div>

<script src="<?= e(url('assets/js/interviews/daily-meeting.js')) ?>"></script>
<script src="<?= e(url('assets/js/interviews/interviews.js')) ?>"></script>
