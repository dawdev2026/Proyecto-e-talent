<section class="page-header">
    <div>
        <p class="text-uppercase text-primary fw-bold small mb-1">Entrevistas seleccion</p>
        <h1 class="fw-bold mb-1">Procesos de entrevistas</h1>
        <p class="text-muted mb-0">Agenda entrevistas 1 a 1, transcripcion y reportes finales.</p>
    </div>
    <?php if (has_permission('manage_interview_processes') || has_permission('manage_company_interviews')): ?>
        <a class="btn btn-primary" href="<?= e(route_url('interview-process.new')) ?>"><i class="bi bi-calendar-plus me-1"></i> Nuevo proceso</a>
    <?php endif; ?>
</section>

<section class="card content-panel">
    <div class="table-responsive">
        <table class="table table-hover align-middle app-table app-data-table" data-export-title="Procesos entrevistas">
            <thead>
                <tr>
                    <th>Proceso</th>
                    <th>Fecha</th>
                    <th>Moderador</th>
                    <th>Entrevistas</th>
                    <th>Estado</th>
                    <th class="text-end no-sort no-export">Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($processes as $process): ?>
                    <tr>
                        <td class="fw-semibold"><?= e($process['name']) ?></td>
                        <td><?= e(date('d/m/Y', strtotime((string) $process['interview_date']))) ?></td>
                        <td><?= e($process['moderator_name'] ?? 'Sin moderador') ?></td>
                        <td><?= (int) $process['finished_count'] ?> / <?= (int) $process['appointments_count'] ?></td>
                        <td><span class="badge text-bg-secondary"><?= e(labelize((string) $process['status'])) ?></span></td>
                        <td class="text-end">
                            <a class="btn btn-sm btn-outline-primary" href="<?= e(route_url('interview-process.show', (int) $process['id'])) ?>"><i class="bi bi-kanban me-1"></i> Agenda</a>
                            <?php if (has_permission('manage_interview_processes') || has_permission('manage_company_interviews')): ?>
                                <a class="btn btn-sm btn-outline-secondary" href="<?= e(route_url('interview-process.edit', (int) $process['id'])) ?>"><i class="bi bi-pencil me-1"></i> Editar</a>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
