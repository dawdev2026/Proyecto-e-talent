<?php
$statusCode = (int) ($statusCode ?? 500);
$eyebrow = $eyebrow ?? 'Incidente de plataforma';
$heading = $heading ?? 'Ocurrio un error interno.';
$message = $message ?? 'La plataforma no pudo completar la solicitud.';
$supportReference = $supportReference ?? '';
$requestedPath = $requestedPath ?? (parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/');
$occurredAt = $occurredAt ?? date('d/m/Y H:i');
$detailRows = $detailRows ?? [];
$actions = $actions ?? [];
?>

<section class="platform-error-shell content-panel">
    <div class="platform-error-header">
        <div>
            <p class="text-uppercase text-primary fw-bold small mb-2"><?= e($eyebrow) ?></p>
            <h1 class="mb-2"><?= e($pageHeading ?? $heading) ?></h1>
            <p class="text-muted mb-0"><?= e($pageLead ?? 'Respuesta estandarizada segun el diseño configurado de la plataforma.') ?></p>
        </div>
    </div>

    <div class="platform-error-layout">
        <section class="platform-error-main">
            <div class="platform-error-icon" aria-hidden="true">
                <?= e((string) $statusCode) ?>
            </div>
            <div class="platform-error-copy">
                <h2 class="h4 mb-2"><?= e($heading) ?></h2>
                <p class="text-muted mb-0"><?= e($message) ?></p>

                <div class="platform-error-chips" aria-label="Detalle del error">
                    <span class="badge platform-error-chip">Codigo <?= e((string) $statusCode) ?></span>
                    <?php foreach (($chips ?? []) as $chip): ?>
                        <span class="badge platform-error-chip platform-error-chip-soft"><?= e((string) $chip) ?></span>
                    <?php endforeach; ?>
                </div>

                <div class="platform-error-actions">
                    <?php foreach ($actions as $action): ?>
                        <a class="btn <?= !empty($action['primary']) ? 'btn-primary' : 'btn-outline-secondary' ?>" href="<?= e((string) $action['url']) ?>">
                            <?php if (!empty($action['icon'])): ?><i class="bi <?= e((string) $action['icon']) ?> me-1"></i><?php endif; ?>
                            <?= e((string) $action['label']) ?>
                        </a>
                    <?php endforeach; ?>
                </div>

                <?php if ($supportReference): ?>
                    <div class="platform-error-note">
                        El detalle tecnico queda en el log interno; el usuario ve solo la referencia.
                    </div>
                <?php endif; ?>
            </div>
        </section>

        <aside class="platform-error-support">
            <h2 class="h5 mb-3">Detalle para soporte</h2>
            <dl class="platform-error-detail">
                <dt>Codigo</dt>
                <dd><?= e((string) $statusCode) ?></dd>
                <?php if ($supportReference): ?>
                    <dt>Referencia</dt>
                    <dd><?= e($supportReference) ?></dd>
                <?php endif; ?>
                <dt>Ruta solicitada</dt>
                <dd><?= e($requestedPath) ?></dd>
                <dt>Fecha</dt>
                <dd><?= e($occurredAt) ?></dd>
                <?php foreach ($detailRows as $label => $value): ?>
                    <dt><?= e((string) $label) ?></dt>
                    <dd><?= e((string) $value) ?></dd>
                <?php endforeach; ?>
            </dl>
            <div class="platform-error-support-note">
                No compartas datos sensibles en capturas.
            </div>
        </aside>
    </div>
</section>
