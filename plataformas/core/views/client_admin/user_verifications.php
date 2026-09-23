<?php
declare(strict_types=1);
$rows = is_array($rows ?? null) ? $rows : [];
$totalRows = max(0, (int) ($totalRows ?? 0));
$page = max(1, (int) ($page ?? 1));
$totalPages = max(1, (int) ($totalPages ?? 1));
$pageSize = max(1, (int) ($pageSize ?? 50));
$formatDate = static function (string $value, string $format): string {
    $date = DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $value);
    return $date instanceof DateTimeImmutable ? $date->format($format) : ($value !== '' ? $value : 'Sin fecha');
};
?>
<div class="app-page-shell">
    <section class="app-page-header border-bottom bg-white rounded-top p-4">
        <p class="text-uppercase small fw-bold text-primary mb-1">Procesos</p>
        <h1 class="h2 fw-bold mb-2">Verificación de usuarios</h1>
        <p class="text-muted mb-0">Consultas realizadas por personas de <?= e((string) ($companyName ?? 'tu empresa')) ?> en la página de verificación de usuarios.</p>
    </section>
    <section class="app-page-content bg-white rounded-bottom p-4">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
            <div><h2 class="h5 fw-bold mb-1">Historial de verificaciones</h2><p class="text-muted mb-0">Cada fila corresponde a una consulta válida de procesos y evaluaciones asignadas.</p></div>
            <span class="badge text-bg-light border"><?= e((string) $totalRows) ?> verificación(es)</span>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle app-table" aria-describedby="user-verifications-help">
                <caption id="user-verifications-help" class="visually-hidden">Historial de consultas válidas de verificación de usuarios.</caption>
                <thead><tr><th>Persona</th><th>RUT</th><th>Correo</th><th>Fecha</th><th>Hora</th><th>Estado</th></tr></thead>
                <tbody>
                <?php if (!$rows): ?><tr><td colspan="6" class="text-center text-muted py-4">Todavía no hay verificaciones registradas.</td></tr>
                <?php else: foreach ($rows as $row): $verifiedAt = (string) ($row['verified_at'] ?? ''); ?>
                    <tr>
                        <td><strong><?= e((string) ($row['name'] ?? 'Usuario')) ?></strong></td>
                        <td><?= e((string) ($row['rut'] ?? '')) ?></td>
                        <td><?= e((string) ($row['email'] ?? '')) ?></td>
                        <td><?= e($formatDate($verifiedAt, 'd/m/Y')) ?></td>
                        <td><?= e($formatDate($verifiedAt, 'H:i')) ?></td>
                        <td><span class="badge text-bg-success">Verificada</span></td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
        <?php if ($totalPages > 1): ?>
            <nav class="d-flex justify-content-between align-items-center mt-3" aria-label="Paginación de verificaciones">
                <span class="small text-muted">Mostrando <?= e((string) (($page - 1) * $pageSize + ($rows ? 1 : 0))) ?>–<?= e((string) min($totalRows, $page * $pageSize)) ?> de <?= e((string) $totalRows) ?></span>
                <div class="btn-group" role="group">
                    <?php if ($page > 1): ?><a class="btn btn-outline-secondary" href="<?= e(route_url('client-admin.user-verifications')) ?>?page=<?= e((string) ($page - 1)) ?>">Anterior</a><?php else: ?><span class="btn btn-outline-secondary disabled" aria-disabled="true">Anterior</span><?php endif; ?>
                    <?php if ($page < $totalPages): ?><a class="btn btn-outline-secondary" href="<?= e(route_url('client-admin.user-verifications')) ?>?page=<?= e((string) ($page + 1)) ?>">Siguiente</a><?php else: ?><span class="btn btn-outline-secondary disabled" aria-disabled="true">Siguiente</span><?php endif; ?>
                </div>
            </nav>
        <?php endif; ?>
    </section>
</div>
