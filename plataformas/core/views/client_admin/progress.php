<?php
$rows = is_array($rows ?? null) ? $rows : [];
$kind = (string) ($kind ?? 'Avance');
$peopleMetrics = !empty($peopleMetrics);
?>
<section class="page-header"><div><p class="dashboard-kicker mb-1">Avance</p><h1 class="fw-bold mb-1"><?= e((string) ($heading ?? 'Avance por proceso')) ?></h1><p class="text-muted mb-0">Estado de <?= e($kind) ?> en los procesos de tu empresa.</p></div></section>
<section class="card content-panel">
    <?php if (!$rows): ?><div class="alert alert-info mb-0">No hay procesos con asignaciones disponibles para esta empresa.</div><?php else: ?>
    <?php if ($peopleMetrics): ?>
        <?= status_help_button('Estados y avance por personas', "• Personas asignadas: personas con al menos un test asignado en el proceso.\n• No iniciaron: no han iniciado ninguno de sus tests.\n• En proceso: iniciaron al menos un test, pero aún no han finalizado todos.\n• Finalizaron: todos sus tests terminaron, ya sea completados o expirados.\n• Avance: personas que finalizaron / personas asignadas.") ?>
    <?php else: ?>
        <?= status_help_button('Estados y contadores del avance', "• En curso: intento abierto; puede no tener respuestas.\n• Con respuestas: existe al menos una respuesta no vacía guardada; el avance porcentual se calcula con este contador, no con intentos abiertos.\n• Completadas: entregas explícitas, aunque no tengan respuestas.\n• Expiradas: vencieron sin envío; se informan aparte y no se suman a completadas.\n• Pendientes: asignaciones aún no abiertas. Las canceladas se excluyen del total activo.") ?>
    <?php endif; ?>
    <table class="table table-hover align-middle app-table app-data-table" data-export-title="<?= e((string) ($heading ?? 'Avance')) ?>">
        <thead><tr><th>Proceso</th><?php if ($peopleMetrics): ?><th>Personas asignadas</th><th>No iniciaron</th><th>En proceso</th><th>Finalizaron</th><?php else: ?><th>Asignadas</th><th>En curso</th><th>Con respuestas</th><th>Completadas</th><th>Expiradas</th><th>Pendientes</th><?php endif; ?><th>Avance</th></tr></thead><tbody>
        <?php foreach ($rows as $row): $assigned = max(0, (int) ($row['assigned'] ?? 0)); $finished = max(0, (int) ($row['finished'] ?? 0)); $answered = max(0, (int) ($row['answered'] ?? 0)); $percent = $peopleMetrics ? min(100, max(0, (int) ($row['progress_percent'] ?? 0))) : ($assigned > 0 ? min(100, (int) round($answered * 100 / $assigned)) : 0); ?>
            <tr><td><strong><?= e((string) ($row['process_name'] ?? 'Proceso')) ?></strong><?php if (!empty($row['process_code'])): ?><div class="small text-muted"><?= e((string) $row['process_code']) ?></div><?php endif; ?></td><td><?= $assigned ?></td><?php if ($peopleMetrics): ?><td><span class="badge text-bg-secondary"><?= (int) ($row['pending'] ?? 0) ?></span></td><td><span class="badge text-bg-primary"><?= (int) ($row['in_progress'] ?? 0) ?></span></td><td><span class="badge text-bg-success"><?= $finished ?></span></td><?php else: ?><td><span class="badge text-bg-primary"><?= (int) ($row['in_progress'] ?? 0) ?></span></td><td><span class="badge text-bg-info"><?= $answered ?></span></td><td><span class="badge text-bg-success"><?= $finished ?></span></td><td><span class="badge text-bg-warning"><?= (int) ($row['expired'] ?? 0) ?></span></td><td><span class="badge text-bg-secondary"><?= (int) ($row['pending'] ?? 0) ?></span></td><?php endif; ?><td style="min-width:150px"><div class="d-flex justify-content-between small"><span><?= $percent ?>%</span><span><?= $finished ?>/<?= $assigned ?></span></div><div class="progress" role="progressbar" aria-label="Avance de <?= e((string) ($row['process_name'] ?? 'proceso')) ?>" aria-valuenow="<?= $percent ?>" aria-valuemin="0" aria-valuemax="100"><div class="progress-bar" style="width:<?= $percent ?>%"></div></div><small class="text-muted">Finalizaron: <?= $finished ?></small></td></tr>
        <?php endforeach; ?></tbody>
    </table><?php endif; ?>
</section>
