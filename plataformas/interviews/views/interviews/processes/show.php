<section class="page-header">
    <div>
        <p class="text-uppercase text-primary fw-bold small mb-1">Entrevistas seleccion</p>
        <h1 class="fw-bold mb-1"><?= e($process['name']) ?></h1>
        <p class="text-muted mb-0"><?= e(date('d/m/Y', strtotime((string) $process['interview_date']))) ?> · Moderador: <?= e($process['moderator_name'] ?? 'Sin moderador') ?></p>
    </div>
    <?php if (has_permission('manage_interview_processes') || has_permission('manage_company_interviews')): ?>
        <a class="btn btn-outline-primary" href="<?= e(route_url('interview-process.edit', (int) $process['id'])) ?>"><i class="bi bi-pencil me-1"></i> Editar proceso</a>
    <?php endif; ?>
</section>

<section class="content-panel">
    <div class="table-responsive">
        <table class="table align-middle app-table app-data-table" data-export-title="Agenda entrevistas">
            <thead>
                <tr>
                    <th>Horario</th>
                    <th>Postulante</th>
                    <th>Informe</th>
                    <th>Transcripcion</th>
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
                        <td><span class="badge text-bg-secondary"><?= e(labelize((string) $appointment['transcript_status'])) ?></span></td>
                        <td><span class="badge text-bg-secondary"><?= e(labelize((string) $appointment['final_report_status'])) ?></span></td>
                        <td class="text-end">
                            <a class="btn btn-sm btn-primary" href="<?= e(route_url('interview-appointment.room', (int) $appointment['id'])) ?>"><i class="bi bi-camera-video me-1"></i> Sala</a>
                            <?php if ((string) $appointment['final_report_status'] === 'ready'): ?>
                                <a class="btn btn-sm btn-outline-secondary" href="<?= e(route_url('interview-appointment.report', (int) $appointment['id'])) ?>"><i class="bi bi-filetype-pdf me-1"></i> PDF</a>
                            <?php endif; ?>
                            <?php if (has_permission('manage_interview_processes') || has_permission('manage_company_interviews')): ?>
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
                                            <label class="form-label small" for="moderator_<?= (int) $appointment['id'] ?>">Moderador</label>
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
