<?php
$agenda = is_array($agenda ?? null) ? $agenda : [];
$macro = is_array($macro ?? null) ? $macro : [];
?>
<section class="dashboard-hero">
    <div class="dashboard-hero-copy">
        <p class="dashboard-kicker mb-2">Core de plataforma</p>
        <h1 class="mb-2">Panel operativo</h1>
        <p class="text-muted mb-0">Revisa tus procesos, entrevistas y avance desde un solo lugar.</p>
    </div>
</section>

<section class="dashboard-agenda-layout" aria-labelledby="agenda-title">
    <div class="card content-panel dashboard-agenda-panel">
        <div class="dashboard-panel-header">
            <div>
                <p class="dashboard-kicker mb-1">Hoy · <?= e(date('d/m/Y')) ?></p>
                <h2 id="agenda-title" class="h5 fw-bold mb-0">Agenda de hoy</h2>
            </div>
            <a class="btn btn-outline-secondary btn-sm" href="<?= e(route_url('test-processes')) ?>">Ver agenda completa</a>
        </div>

        <?php if ($agenda): ?>
            <div class="dashboard-agenda-list">
                <?php foreach ($agenda as $item): ?>
                    <article class="dashboard-agenda-row">
                        <span class="dashboard-agenda-icon <?= $item['kind'] === 'interview' ? 'is-interview' : '' ?>" aria-hidden="true">
                            <i class="bi <?= e($item['icon']) ?>"></i>
                        </span>
                        <div class="dashboard-agenda-time">
                            <strong><?= e(date('H:i', strtotime((string) $item['start_at']))) ?></strong>
                            <span><?= e(date('H:i', strtotime((string) $item['end_at']))) ?></span>
                        </div>
                        <div class="dashboard-agenda-info">
                            <strong><?= e($item['title']) ?></strong>
                            <span><?= e($item['category']) ?> · <?= e($item['date']) ?></span>
                        </div>
                        <span class="dashboard-agenda-status"><?= e($item['status_label']) ?></span>
                        <a class="btn btn-sm btn-outline-primary dashboard-agenda-action" href="<?= e(route_url($item['action_route'], (int) $item['action_id'])) ?>">
                            <?= e($item['action_label']) ?>
                        </a>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="dashboard-empty-state">
                <span class="dashboard-empty-icon"><i class="bi bi-calendar2-check"></i></span>
                <strong>No hay procesos ni entrevistas para hoy.</strong>
                <span>La agenda se actualizará cuando existan actividades programadas.</span>
            </div>
        <?php endif; ?>
    </div>

    <div class="dashboard-agenda-side">
        <section class="card content-panel dashboard-process-summary" aria-labelledby="process-summary-title">
            <div class="dashboard-panel-header">
                <div>
                    <p class="dashboard-kicker mb-1">Evaluaciones</p>
                    <h2 id="process-summary-title" class="h5 fw-bold mb-0">Procesos de evaluación</h2>
                </div>
            </div>
            <div class="dashboard-summary-metrics">
                <div class="dashboard-summary-metric">
                    <strong><?= (int) ($processOverview['today_processes'] ?? 0) ?></strong>
                    <span>Procesos de hoy</span>
                </div>
                <div class="dashboard-summary-metric">
                    <strong><?= (int) ($processOverview['today_assigned_people'] ?? 0) ?></strong>
                    <span>Personas asignadas hoy</span>
                </div>
            </div>
            <div class="dashboard-summary-section">
                <div class="dashboard-realized-header">
                    <div>
                        <p class="dashboard-summary-section-title mb-1">Procesos realizados</p>
                        <small>Procesos con fecha de término cumplida.</small>
                    </div>
                    <div class="dashboard-realized-total">
                        <strong><?= (int) ($processOverview['completed_processes'] ?? 0) ?></strong>
                        <span>procesos realizados</span>
                    </div>
                </div>
                <div class="dashboard-summary-metrics dashboard-summary-metrics-four">
                    <div class="dashboard-summary-metric is-neutral">
                        <span class="dashboard-summary-metric-icon" aria-hidden="true"><i class="bi bi-people"></i></span>
                        <div>
                            <strong><?= (int) ($processOverview['completed_assigned_people'] ?? 0) ?></strong>
                            <span>Personas asignadas</span>
                            <small>Total de personas incluidas.</small>
                        </div>
                    </div>
                    <div class="dashboard-summary-metric is-success">
                        <span class="dashboard-summary-metric-icon" aria-hidden="true"><i class="bi bi-check-circle"></i></span>
                        <div>
                            <strong><?= (int) ($processOverview['completed_finished_people'] ?? 0) ?></strong>
                            <span>Personas que completaron todos los tests</span>
                            <small>Finalizaron el proceso completo.</small>
                        </div>
                    </div>
                    <div class="dashboard-summary-metric is-warning">
                        <span class="dashboard-summary-metric-icon" aria-hidden="true"><i class="bi bi-hourglass-split"></i></span>
                        <div>
                            <strong><?= (int) ($peoplePartial ?? 0) ?></strong>
                            <span>Personas con avance parcial</span>
                            <small>Iniciaron, pero no terminaron todos.</small>
                        </div>
                    </div>
                    <div class="dashboard-summary-metric is-muted">
                        <span class="dashboard-summary-metric-icon" aria-hidden="true"><i class="bi bi-circle"></i></span>
                        <div>
                            <strong><?= (int) ($processOverview['completed_not_started_people'] ?? 0) ?></strong>
                            <span>Personas sin respuestas</span>
                            <small>Aún no tienen respuestas guardadas ni tests completados.</small>
                        </div>
                    </div>
                </div>
            </div>
            <div class="dashboard-summary-actions">
                <a class="btn btn-primary btn-sm" href="<?= e(route_url('test-processes')) ?>">Ver procesos de hoy</a>
                <a class="btn btn-outline-secondary btn-sm" href="<?= e(route_url('test-processes')) ?>">Ver todos</a>
            </div>
        </section>

    </div>
</section>

<section class="card content-panel dashboard-progress-panel" aria-labelledby="progress-title">
    <div class="dashboard-progress-heading">
        <span class="dashboard-progress-icon" aria-hidden="true"><i class="bi bi-graph-up-arrow"></i></span>
        <div>
            <p class="dashboard-kicker mb-1">Seguimiento general</p>
            <h2 id="progress-title" class="h5 fw-bold mb-1">Dashboard de avance</h2>
            <p class="text-muted mb-0">Seguimiento general del progreso de tus procesos</p>
        </div>
    </div>
    <div class="dashboard-progress-content">
        <div class="dashboard-progress-bar-block">
            <strong>Avance global</strong>
            <div class="dashboard-progress-track" role="progressbar" aria-label="Avance global" aria-valuenow="<?= (int) ($macroProgress ?? 0) ?>" aria-valuemin="0" aria-valuemax="100">
                <span style="width: <?= (int) ($macroProgress ?? 0) ?>%"><b><?= (int) ($macroProgress ?? 0) ?>%</b></span>
            </div>
        </div>
        <div class="dashboard-progress-metrics">
            <div class="dashboard-progress-metric">
                <span class="dashboard-progress-metric-icon" aria-hidden="true"><i class="bi bi-check-circle"></i></span>
                <div>
                    <span>Personas que completaron el proceso</span>
                    <strong><?= (int) ($peopleCompleted ?? 0) ?></strong>
                    <small>de <?= (int) ($peopleAssigned ?? 0) ?></small>
                    <p class="dashboard-progress-metric-help">Finalizaron todos los tests. <?= (int) ($peoplePartial ?? 0) ?> tienen avance parcial.</p>
                </div>
            </div>
            <div class="dashboard-progress-metric">
                <span class="dashboard-progress-metric-icon" aria-hidden="true"><i class="bi bi-person"></i></span>
                <div>
                    <span>Evaluaciones respondidas</span>
                    <strong><?= (int) ($macro['evaluations_answered'] ?? 0) ?></strong>
                    <small>de <?= (int) ($macro['evaluations_total'] ?? 0) ?></small>
                    <p class="dashboard-progress-metric-help">Tests con al menos una respuesta no vacía guardada. Abrir un test no cuenta como avance.</p>
                </div>
            </div>
            <div class="dashboard-progress-metric">
                <span class="dashboard-progress-metric-icon" aria-hidden="true"><i class="bi bi-graph-up-arrow"></i></span>
                <div>
                    <span>Avance global</span>
                    <strong><?= (int) ($macroProgress ?? 0) ?>%</strong>
                    <small>del total</small>
                    <p class="dashboard-progress-metric-help">Relación entre tests con respuestas guardadas y asignaciones no canceladas.</p>
                </div>
            </div>
        </div>
    </div>
</section>
