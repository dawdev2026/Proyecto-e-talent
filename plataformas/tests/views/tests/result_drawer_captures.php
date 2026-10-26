<?php
$screenCaptures = is_array($screenCaptures ?? null) ? $screenCaptures : [];
$screenCaptureTotal = max(count($screenCaptures), (int) ($screenCaptureTotal ?? 0));
$capturesPage = max(1, (int) ($capturesPage ?? 1));
$drawerPerPage = max(1, (int) ($drawerPerPage ?? 10));
$drawerUrl = route_url('test-session.result', (int) ($session['id'] ?? 0));
$pageUrl = static function (int $page) use ($drawerUrl, $activityPage, $mediaPage, $capturesPage): string {
    return $drawerUrl . '?' . http_build_query([
        'drawer' => 1, 'drawer_fragment' => 'captures',
        'activity_page' => $activityPage, 'media_page' => $mediaPage, 'captures_page' => max(1, $page),
    ]);
};
$pages = max(1, (int) ceil($screenCaptureTotal / $drawerPerPage));
if ($screenCaptures): ?>
    <div class="list-group list-group-flush border rounded-3"><?php foreach ($screenCaptures as $capture): $captureUrl = route_url('test-session.media-screenshot-file', (int) ($session['id'] ?? 0)) . '?capture_id=' . (int) ($capture['id'] ?? 0); ?><a class="list-group-item list-group-item-action d-flex justify-content-between align-items-center gap-2" href="<?= e($captureUrl) ?>" data-screen-capture-view><span><strong class="small d-block">Captura <?= (int) ($capture['capture_number'] ?? 0) ?></strong><span class="small text-muted"><?= e((string) ($capture['capture_source'] ?? '')) ?> · <?= e((string) ($capture['captured_at'] ?? '')) ?></span></span><i class="bi bi-image" aria-hidden="true"></i></a><?php endforeach; ?></div>
<?php else: ?><p class="text-muted small mb-0">No hay capturas almacenadas para esta sesión.</p><?php endif; ?>
<?php if ($pages > 1): $previousUrl = $pageUrl($capturesPage - 1); $nextUrl = $pageUrl($capturesPage + 1); ?>
    <div class="d-flex justify-content-between align-items-center gap-2 mt-2"><span class="small text-muted">Página <?= $capturesPage ?> de <?= $pages ?> · <?= $screenCaptureTotal ?> registros</span><div class="d-flex gap-2"><?php if ($capturesPage > 1): ?><a class="btn btn-sm btn-outline-secondary" href="<?= e($previousUrl) ?>" data-drawer-partial-url="<?= e($previousUrl) ?>" data-drawer-partial-target="drawer-captures-grid" aria-label="Página anterior">Anterior</a><?php else: ?><span class="btn btn-sm btn-outline-secondary disabled">Anterior</span><?php endif; ?><?php if ($capturesPage < $pages): ?><a class="btn btn-sm btn-outline-secondary" href="<?= e($nextUrl) ?>" data-drawer-partial-url="<?= e($nextUrl) ?>" data-drawer-partial-target="drawer-captures-grid" aria-label="Página siguiente">Siguiente</a><?php else: ?><span class="btn btn-sm btn-outline-secondary disabled">Siguiente</span><?php endif; ?></div></div>
<?php endif; ?>
