<?php
declare(strict_types=1);
$labels = ['passed' => 'Autoinforme completo', 'partial' => 'Autoinforme parcial', 'failed' => 'Autoinforme con fallas'];
$statusLabels = ['passed' => 'Permiso/comprobación disponible', 'warning' => 'Aviso', 'failed' => 'No disponible', 'not_supported' => 'No compatible', 'not_checked' => 'Sin comprobar'];
$captureLabels = ['screen' => 'Pantalla completa', 'canvas' => 'Canvas (interfaz de evaluación)', 'unavailable' => 'No disponible'];
?>
<div class="app-page-shell">
    <section class="app-page-header border-bottom bg-white rounded-top p-4">
        <p class="text-uppercase small fw-bold text-primary mb-1">Auditoría</p>
        <h1 class="h2 fw-bold mb-2">Historial de Validaciones</h1>
        <p class="text-muted mb-0">Intentos de validación de componentes reportados por los usuarios de <?= e($companyName) ?>, con su resultado y entorno.</p>
    </section>
    <section class="app-page-content bg-white rounded-bottom p-4">
        <div class="alert alert-info">Cada fila representa un intento y muestra el resultado informado por el navegador. El contador indica cuántos intentos históricos tiene esa persona en <?= e($companyName) ?>. Estos datos no certifican el equipo ni identifican un dispositivo de forma única.</div>
        <?= status_help_button('Estados de la revisión de componentes', "• Permiso/comprobación disponible: el navegador informó acceso y disponibilidad.\n• Aviso: la verificación requiere atención, pero no equivale por sí sola a una falla.\n• No disponible / No compatible: no se pudo usar o el entorno no ofrece el componente.\n• Sin comprobar: no hay resultado registrado para ese componente.\n• El resultado es autoinformado por el navegador y no certifica por sí solo un dispositivo.") ?>
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
            <strong><?= e((string) $totalRows) ?> intentos registrados</strong>
            <?php if ($totalPages > 1): ?><span class="small text-muted">Página <?= e((string) $page) ?> de <?= e((string) $totalPages) ?></span><?php endif; ?>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle app-table">
                <thead><tr><th>Usuario</th><th>Intentos de la persona</th><th>Equipo</th><th>Sistema operativo</th><th>Navegador</th><th>Resultado</th><th>Componentes</th><th>Fecha del intento</th></tr></thead>
                <tbody>
                <?php if (empty($rows)): ?><tr><td colspan="8" class="text-center text-muted py-4">Todavía no hay validaciones registradas para esta empresa.</td></tr>
                <?php else: foreach ($rows as $row): $meta = json_decode((string) ($row['metadata'] ?? ''), true); $checks = is_array($meta['components'] ?? null) ? $meta['components'] : []; $messages = is_array($meta['component_messages'] ?? null) ? $meta['component_messages'] : []; ?>
                    <tr>
                        <td><strong><?= e((string) ($row['user_name'] ?? 'Usuario')) ?></strong><div class="small text-muted"><?= e((string) ($row['rut'] ?? '')) ?></div></td>
                        <td><span class="badge text-bg-secondary"><?= e((string) ($row['attempt_count'] ?? 0)) ?></span></td>
                        <td><?= e(['desktop' => 'PC', 'tablet' => 'Tablet', 'mobile' => 'Teléfono', 'unknown' => 'No informado'][(string) ($row['device_type'] ?? 'unknown')] ?? 'No informado') ?></td>
                        <td><?= e((string) ($row['os_name'] ?? 'No informado')) ?></td>
                        <td><?= e(trim((string) ($row['browser_name'] ?? '') . ' ' . (string) ($row['browser_version'] ?? ''))) ?></td>
                        <td><span class="badge <?= ($row['outcome'] ?? '') === 'passed' ? 'text-bg-success' : (($row['outcome'] ?? '') === 'failed' ? 'text-bg-danger' : 'text-bg-warning') ?>"><?= e($labels[(string) ($row['outcome'] ?? '')] ?? 'Parcial') ?></span></td>
                        <td class="small"><?php foreach (['camera' => 'Cámara', 'microphone' => 'Micrófono', 'media_recorder' => 'Grabación', 'screen_capture' => 'Pantalla'] as $key => $label): ?><div><?= e($label) ?>: <?= e($statusLabels[(string) ($checks[$key] ?? 'not_checked')] ?? 'Sin comprobar') ?></div><?php if (!empty($messages[$key])): ?><div class="text-muted mb-1"><?= e((string) $messages[$key]) ?></div><?php endif; ?><?php endforeach; ?><div class="text-muted">Fuente: <?= e($captureLabels[(string) ($meta['capture_source'] ?? '')] ?? 'No informada') ?></div></td>
                        <td><?= e((string) ($row['created_at'] ?? '')) ?></td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
        <?php if ($totalPages > 1): ?>
            <nav class="d-flex justify-content-between align-items-center" aria-label="Paginación del historial de validaciones">
                <span class="small text-muted">Mostrando <?= e((string) min($totalRows, (($page - 1) * $pageSize) + 1)) ?>–<?= e((string) min($totalRows, $page * $pageSize)) ?> de <?= e((string) $totalRows) ?></span>
                <div class="btn-group" role="group">
                    <?php if ($page > 1): ?><a class="btn btn-outline-secondary" href="<?= e(route_url('client-admin.component-reviews')) ?>?page=<?= e((string) ($page - 1)) ?>">Anterior</a><?php else: ?><span class="btn btn-outline-secondary disabled" aria-disabled="true">Anterior</span><?php endif; ?>
                    <?php if ($page < $totalPages): ?><a class="btn btn-outline-secondary" href="<?= e(route_url('client-admin.component-reviews')) ?>?page=<?= e((string) ($page + 1)) ?>">Siguiente</a><?php else: ?><span class="btn btn-outline-secondary disabled" aria-disabled="true">Siguiente</span><?php endif; ?>
                </div>
            </nav>
        <?php endif; ?>
    </section>
</div>
