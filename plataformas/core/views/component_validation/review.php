<?php
declare(strict_types=1);
$componentLabels = [
    'camera' => 'Cámara frontal',
    'microphone' => 'Micrófono',
    'media_recorder' => 'Grabación del navegador',
    'screen_capture' => 'Captura de pantalla',
];
$latestMetadata = json_decode((string) ($latestValidation['metadata'] ?? ''), true);
$latestComponents = is_array($latestMetadata['components'] ?? null) ? $latestMetadata['components'] : [];
$componentStateLabels = ['passed' => 'disponible', 'warning' => 'parcial / requiere atención', 'failed' => 'no disponible', 'not_supported' => 'no compatible', 'not_checked' => 'sin comprobar'];
$componentMessages = [
    'camera' => ['passed' => 'El navegador entregó un fotograma de video.', 'warning' => 'Revisa el permiso y la imagen de la cámara.', 'failed' => 'No se recibió un fotograma; revisa el permiso y la cámara.', 'not_supported' => 'Este navegador o conexión no permite acceder a la cámara.'],
    'microphone' => ['passed' => 'La pista de audio está activa; no se midió el volumen.', 'warning' => 'Revisa el permiso del micrófono.', 'failed' => 'No se recibió audio del micrófono; revisa el permiso y el dispositivo.', 'not_supported' => 'Este navegador o conexión no permite acceder al micrófono.'],
    'media_recorder' => ['passed' => 'MediaRecorder operó con cámara y micrófono; los datos temporales se descartaron.', 'warning' => 'Se probó solo una pista. Se necesitan cámara y micrófono para la grabación completa.', 'failed' => 'La grabación no generó datos.', 'not_supported' => 'Este navegador no incluye MediaRecorder.'],
    'screen_capture' => ['passed' => 'Se confirmó que compartiste la pantalla completa. No se guardó contenido.', 'warning' => 'El navegador compartió la pantalla, pero no confirmó que fuera la pantalla completa.', 'failed' => 'No se recibió el fotograma de pantalla completa. Reintenta y elige la pantalla completa en el diálogo.', 'not_supported' => 'Este navegador o conexión no permite compartir la pantalla desde la página.'],
];
?>
<div class="app-page-shell" data-component-validation-page data-save-url="<?= e(route_url('component-validation.review')) ?>">
    <section class="app-page-header border-bottom bg-white rounded-top p-4">
        <p class="text-uppercase small fw-bold text-primary mb-1">Preparación de evaluación</p>
        <h1 class="h2 fw-bold mb-2">Revisión Componentes</h1>
        <p class="text-muted mb-0">Revisión informativa previa de permisos y compatibilidad del navegador. Al iniciar cada evaluación o test se volverán a solicitar los permisos necesarios.</p>
    </section>
    <section class="app-page-content bg-white rounded-bottom p-4">
        <?php if (!empty($message)): ?><div class="alert alert-<?= e($messageType ?? 'success') ?>" role="status"><?= e($message) ?></div><?php endif; ?>
        <?php if (!empty($error)): ?><div class="alert alert-danger" role="alert"><?= e($error) ?></div><?php endif; ?>
        <div class="alert alert-info" role="note">
            <strong>Privacidad:</strong> esta revisión no guarda ni envía video, audio ni contenido de pantalla. Las pruebas funcionan temporalmente en el navegador y solo se envían sus resultados junto con datos generales del equipo, sistema operativo y navegador. Estos datos no identifican de forma única el equipo.
        </div>
        <div class="alert alert-secondary" role="status" data-component-capture-guidance data-component-canvas-probe>Preparando las instrucciones de compatibilidad…</div>
        <p class="small text-muted">La comprobación Canvas renderiza una muestra temporal de esta pantalla para verificar que la biblioteca funciona. Durante una evaluación o test, Canvas se aplicará a la interfaz de esa actividad. La muestra de esta revisión se descarta y nunca se envía.</p>
        <?php if (!empty($latestValidation)): ?>
            <p class="small text-muted">Última revisión registrada: <?= e((string) $latestValidation['created_at']) ?> · <?= e((string) ($latestValidation['browser_name'] ?? 'Navegador desconocido')) ?> · <?= e((string) ($latestValidation['os_name'] ?? 'Sistema desconocido')) ?>.</p>
        <?php endif; ?>
        <div class="row g-3 mb-4">
            <?php foreach ($componentLabels as $key => $label): $state = (string) ($latestComponents[$key] ?? 'not_checked'); $stateText = $latestValidation ? ($componentStateLabels[$state] ?? 'sin comprobar') : 'pendiente'; $stateClass = ['passed' => 'border-success', 'warning' => 'border-warning', 'failed' => 'border-danger', 'not_supported' => 'border-danger'][$state] ?? ''; $componentMessage = $latestValidation ? ($componentMessages[$key][$state] ?? 'Repite la revisión para actualizar el estado.') : 'Inicia la revisión para comprobar este componente.'; $reportedMessage = trim((string) ($latestMetadata['component_messages'][$key] ?? '')); if ($reportedMessage !== '') $componentMessage = $reportedMessage; if ($key === 'screen_capture' && $latestValidation && $reportedMessage === '' && (string) ($latestMetadata['capture_source'] ?? '') === 'canvas') { $componentMessage = $state === 'passed' ? 'La validación Canvas anterior no guardó la imagen de prueba.' : 'El navegador anterior no permitió comprobar la captura Canvas.'; } elseif ($key === 'screen_capture' && $state === 'warning' && $latestValidation && $reportedMessage === '') { $componentMessage = 'No se concedió el permiso o se canceló la selección. Repite la revisión y elige una pantalla completa en el diálogo.'; } ?>
                <div class="col-12 col-md-6"><div class="border rounded p-3 h-100 <?= e($stateClass) ?>" data-component-status="<?= e($key) ?>" aria-live="polite">
                    <div class="fw-semibold" data-component-label><?= e($label) ?>: <?= e($stateText) ?></div>
                    <div class="small text-muted mt-1" data-component-message><?= e($componentMessage) ?></div>
                </div></div>
            <?php endforeach; ?>
        </div>
        <div class="d-flex flex-wrap gap-3 align-items-center">
            <button type="button" class="btn btn-primary" data-component-start><i class="bi bi-play-circle me-1" aria-hidden="true"></i> Iniciar revisión</button>
            <span class="small text-muted" data-component-device-summary></span>
        </div>
        <div class="mb-3"><video class="d-none rounded border" style="width:100%;max-width:360px;aspect-ratio:16/9;object-fit:cover" muted playsinline autoplay data-component-camera-preview aria-label="Vista previa local de la cámara"></video><div class="small text-muted">La vista previa de cámara se muestra solo durante la revisión y no se envía al servidor.</div></div>
        <form method="post" action="<?= e(route_url('component-validation.review')) ?>" data-component-save-form class="d-none">
            <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
            <input type="hidden" name="validation" value="" data-component-validation-payload>
        </form>
    </section>
</div>
<script src="<?= e(url('assets/js/component-validation.js?v=' . (string) filemtime(PUBLIC_PATH . '/assets/js/component-validation.js'))) ?>" defer></script>
