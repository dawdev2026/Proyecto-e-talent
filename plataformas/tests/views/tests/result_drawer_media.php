<?php
$audioVisualRisks = is_array($audioVisualRisks ?? null) ? $audioVisualRisks : [];
$audioVisualRiskTotal = max(count($audioVisualRisks), (int) ($audioVisualRiskTotal ?? 0));
$mediaPage = max(1, (int) ($mediaPage ?? 1));
$drawerPerPage = max(1, (int) ($drawerPerPage ?? 10));
$drawerUrl = route_url('test-session.result', (int) ($session['id'] ?? 0));
$pageUrl = static function (int $page) use ($drawerUrl, $activityPage, $mediaPage, $capturesPage): string {
    return $drawerUrl . '?' . http_build_query([
        'drawer' => 1, 'drawer_fragment' => 'media',
        'activity_page' => $activityPage, 'media_page' => max(1, $page), 'captures_page' => $capturesPage,
    ]);
};
$pages = max(1, (int) ceil($audioVisualRiskTotal / $drawerPerPage));
$riskLabels = ['multiple_voice_possible' => 'Posibles voces múltiples', 'audio_visual_risk' => 'Riesgo audiovisual', 'recording_upload_failed' => 'Falla en carga de grabación', 'camera_muted' => 'Cámara silenciada', 'microphone_muted' => 'Micrófono silenciado', 'camera_track_ended' => 'Cámara desconectada'];
if ($audioVisualRisks): ?>
    <div class="list-group list-group-flush border rounded-3"><?php foreach ($audioVisualRisks as $risk): $riskType = (string) ($risk['event_type'] ?? ''); ?><div class="list-group-item px-3 py-2"><strong class="small"><?= e($riskLabels[$riskType] ?? ucwords(str_replace('_', ' ', $riskType))) ?></strong><div class="small text-muted"><?= e((string) ($risk['created_at'] ?? '')) ?> · <?= e((string) ($risk['severity'] ?? 'atención')) ?></div></div><?php endforeach; ?></div>
<?php else: ?><p class="text-muted small mb-0">No se registraron señales audiovisuales para esta sesión.</p><?php endif; ?>
<?php if ($pages > 1): $previousUrl = $pageUrl($mediaPage - 1); $nextUrl = $pageUrl($mediaPage + 1); ?>
    <div class="d-flex justify-content-between align-items-center gap-2 mt-2"><span class="small text-muted">Página <?= $mediaPage ?> de <?= $pages ?> · <?= $audioVisualRiskTotal ?> registros</span><div class="d-flex gap-2"><?php if ($mediaPage > 1): ?><a class="btn btn-sm btn-outline-secondary" href="<?= e($previousUrl) ?>" data-drawer-partial-url="<?= e($previousUrl) ?>" data-drawer-partial-target="drawer-media-grid" aria-label="Página anterior">Anterior</a><?php else: ?><span class="btn btn-sm btn-outline-secondary disabled">Anterior</span><?php endif; ?><?php if ($mediaPage < $pages): ?><a class="btn btn-sm btn-outline-secondary" href="<?= e($nextUrl) ?>" data-drawer-partial-url="<?= e($nextUrl) ?>" data-drawer-partial-target="drawer-media-grid" aria-label="Página siguiente">Siguiente</a><?php else: ?><span class="btn btn-sm btn-outline-secondary disabled">Siguiente</span><?php endif; ?></div></div>
<?php endif; ?>
