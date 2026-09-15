<section class="page-header">
    <div>
        <p class="text-uppercase text-primary fw-bold small mb-1">Informes</p>
        <h1 class="fw-bold mb-1">Procesos de informes</h1>
        <p class="text-muted mb-0">El avance se actualiza automáticamente. Los archivos se eliminan a medianoche.</p>
    </div>
    <a class="btn btn-outline-secondary" href="<?= e(route_url('reports.generate')) ?>">Volver</a>
</section>

<section class="content-panel" data-report-batch-monitor data-batch-id="<?= (int) $batchId ?>" data-status-url="<?= e($statusUrl) ?>">
    <?php if ($batchId <= 0): ?>
        <div class="alert alert-info mb-0">Selecciona un proceso de informes para consultar su avance.</div>
    <?php else: ?>
        <div data-batch-state class="text-muted">Consultando estado del lote...</div>
        <div class="progress mt-3" style="height: 22px"><div class="progress-bar" data-batch-progress style="width:0%">0%</div></div>
        <div data-batch-actions class="mt-3"></div>
    <?php endif; ?>
</section>

<?php if ($batchId > 0): ?>
<script>
(function () {
    var root = document.querySelector('[data-report-batch-monitor]');
    if (!root) return;
    var batchId = root.getAttribute('data-batch-id');
    var url = root.getAttribute('data-status-url');
    var state = root.querySelector('[data-batch-state]');
    var progress = root.querySelector('[data-batch-progress]');
    var actions = root.querySelector('[data-batch-actions]');
    function refresh() {
        fetch(url + '?batch_id=' + encodeURIComponent(batchId), {credentials:'same-origin', cache:'no-store'})
            .then(function (response) { return response.json(); })
            .then(function (data) {
                if (!data.ok) throw new Error(data.message || 'No se pudo consultar el lote.');
                var batch = data.batch || {};
                var total = Math.max(1, parseInt(batch.total_items || 0, 10));
                var completed = parseInt(batch.completed_items || 0, 10);
                var failed = parseInt(batch.failed_items || 0, 10);
                var percentage = Math.min(100, Math.round(((completed + failed) / total) * 100));
                progress.style.width = percentage + '%';
                progress.textContent = percentage + '%';
                state.textContent = 'Estado: ' + (batch.status || 'queued') + ' · Completados: ' + completed + ' · Fallidos: ' + failed + ' · Total: ' + total;
                if (data.download_url && !actions.querySelector('a')) {
                    actions.innerHTML = '<div class="alert alert-success">Proceso finalizado correctamente. Los archivos temporales se eliminarán a medianoche.</div><a class="btn btn-success" href="' + data.download_url + '"><i class="bi bi-download me-1"></i> Descargar ZIP</a>';
                }
                if (String(batch.status) !== 'completed' && String(batch.status) !== 'failed') window.setTimeout(refresh, 2000);
            })
            .catch(function (error) { state.textContent = error.message; window.setTimeout(refresh, 5000); });
    }
    refresh();
}());
</script>
<?php endif; ?>
