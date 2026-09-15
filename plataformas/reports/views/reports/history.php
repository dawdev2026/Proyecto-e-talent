<section class="page-header">
    <div>
        <p class="text-uppercase text-primary fw-bold small mb-1">Informes</p>
        <h1 class="fw-bold mb-1">Historial de Informes</h1>
        <p class="text-muted mb-0">Registro de ejecuciones, versiones, empresa, usuario y resultado de cada generación.</p>
    </div>
</section>

<section class="content-panel">
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead><tr><th>Fecha</th><th>Informe</th><th>Empresa</th><th>Usuario</th><th>Formato</th><th>Estado</th><th>Duración</th></tr></thead>
            <tbody>
            <?php if (!$history): ?><tr><td colspan="7" class="text-center text-muted py-5">No hay ejecuciones registradas.</td></tr><?php endif; ?>
            <?php foreach ($history as $entry): ?>
                <tr>
                    <td><?= e((string) $entry['started_at']) ?></td>
                    <td><?= e((string) $entry['report_name']) ?><br><small class="text-muted">v<?= e((string) ($entry['report_version_id'] ?? '-')) ?></small></td>
                    <td><?= e((string) $entry['company_name']) ?></td>
                    <td><?= e((string) $entry['user_name']) ?></td>
                    <td><?= e(strtoupper((string) $entry['format'])) ?></td>
                    <td><span class="badge text-bg-<?= $entry['status'] === 'completed' ? 'success' : ($entry['status'] === 'failed' ? 'danger' : 'warning') ?>"><?= e(ucfirst((string) $entry['status'])) ?></span></td>
                    <td><?= $entry['duration_ms'] !== null ? e((string) $entry['duration_ms']) . ' ms' : '—' ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
