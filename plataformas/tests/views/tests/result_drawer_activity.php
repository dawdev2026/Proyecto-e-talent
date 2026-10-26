<?php
$activityEvents = is_array($activityEvents ?? null) ? $activityEvents : [];
$activityTotal = max(count($activityEvents), (int) ($activityTotal ?? 0));
$activityPage = max(1, (int) ($activityPage ?? 1));
$drawerPerPage = max(1, (int) ($drawerPerPage ?? 10));
$drawerUrl = route_url('test-session.result', (int) ($session['id'] ?? 0));
$pageUrl = static function (int $page) use ($drawerUrl, $activityPage, $mediaPage, $capturesPage): string {
    return $drawerUrl . '?' . http_build_query([
        'drawer' => 1, 'drawer_fragment' => 'activity',
        'activity_page' => max(1, $page), 'media_page' => $mediaPage, 'captures_page' => $capturesPage,
    ]);
};
$pages = max(1, (int) ceil($activityTotal / $drawerPerPage));
$eventLabels = [
    'tab_hidden' => 'Cambio de pestaña', 'window_blurred' => 'Pérdida de foco',
    'fullscreen_exited' => 'Salida de pantalla completa', 'fullscreen_denied' => 'Pantalla completa denegada',
    'fullscreen_failed' => 'Pantalla completa fallida', 'inactive_detected' => 'Inactividad detectada',
    'copy_blocked' => 'Copia bloqueada', 'cut_blocked' => 'Corte bloqueado', 'paste_blocked' => 'Pegado bloqueado',
    'print_blocked' => 'Impresión bloqueada', 'context_menu_blocked' => 'Menú contextual bloqueado',
    'suspicious_key_printscreen' => 'Captura de pantalla', 'suspicious_key_print' => 'Intento de impresión',
    'suspicious_key_save' => 'Intento de guardado', 'suspicious_key_copy' => 'Intento de copia',
    'suspicious_key_devtools' => 'Herramientas de desarrollo', 'audio_visual_risk' => 'Riesgo audiovisual',
    'audio_visual_upload_failed' => 'Falla de carga audiovisual',
];
if (!$activityEvents): ?>
    <p class="text-muted small mb-0">No se registraron eventos de actividad para esta sesión.</p>
<?php else: ?>
    <div class="list-group list-group-flush border rounded-3">
        <?php foreach ($activityEvents as $event): $eventType = (string) ($event['event_type'] ?? ''); $eventLabel = $eventLabels[$eventType] ?? ucwords(str_replace('_', ' ', $eventType)); ?>
            <div class="list-group-item px-3 py-2"><div class="d-flex justify-content-between gap-2"><strong class="small"><?= e($eventLabel) ?></strong><span class="small text-muted text-nowrap"><?= e((string) ($event['created_at'] ?? '')) ?></span></div><?php if (!empty($event['ip_address'])): ?><div class="small text-muted">IP registrada: <?= e((string) $event['ip_address']) ?></div><?php endif; ?></div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
<?php if ($pages > 1): $previousUrl = $pageUrl($activityPage - 1); $nextUrl = $pageUrl($activityPage + 1); ?>
    <div class="d-flex justify-content-between align-items-center gap-2 mt-2"><span class="small text-muted">Página <?= $activityPage ?> de <?= $pages ?> · <?= $activityTotal ?> registros</span><div class="d-flex gap-2"><?php if ($activityPage > 1): ?><a class="btn btn-sm btn-outline-secondary" href="<?= e($previousUrl) ?>" data-drawer-partial-url="<?= e($previousUrl) ?>" data-drawer-partial-target="drawer-activity-grid" aria-label="Página anterior">Anterior</a><?php else: ?><span class="btn btn-sm btn-outline-secondary disabled">Anterior</span><?php endif; ?><?php if ($activityPage < $pages): ?><a class="btn btn-sm btn-outline-secondary" href="<?= e($nextUrl) ?>" data-drawer-partial-url="<?= e($nextUrl) ?>" data-drawer-partial-target="drawer-activity-grid" aria-label="Página siguiente">Siguiente</a><?php else: ?><span class="btn btn-sm btn-outline-secondary disabled">Siguiente</span><?php endif; ?></div></div>
<?php endif; ?>
