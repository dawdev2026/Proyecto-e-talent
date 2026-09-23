<?php
$assessmentIdentityVerified = !empty($assessmentIdentityVerified);
$assessmentUserId = (int) ($assessmentUserId ?? (current_user()['id'] ?? 0));
$assessmentReturnTo = (string) ($assessmentReturnTo ?? '');
$assessmentFaceSettings = is_array($assessmentFaceSettings ?? null) ? $assessmentFaceSettings : [];
?>
<section class="assessment-flow-stage <?= $assessmentIdentityVerified ? 'd-none' : '' ?>" data-assessment-stage="identity" aria-labelledby="assessmentFaceTitle" <?= $assessmentIdentityVerified ? 'hidden inert' : '' ?>>
    <h2 id="assessmentFaceTitle" class="h4 fw-bold assessment-stage-title">Paso 1: confirma tu identidad</h2>
    <p class="text-muted">Realiza la validación facial antes de autorizar los componentes de esta actividad.</p>
    <?php if (empty($assessmentFaceSettings['enabled'])): ?>
        <div class="alert alert-warning">El servicio de reconocimiento facial no está disponible. Contacta al administrador.</div>
    <?php endif; ?>
    <div class="face-camera" data-face-camera>
        <div class="face-camera-preview">
            <video data-face-video autoplay muted playsinline aria-label="Vista previa de la cámara"></video>
            <div class="face-camera-placeholder" data-face-placeholder><i class="bi bi-camera-video" aria-hidden="true"></i><span>Activa la cámara para iniciar la prueba de vida</span></div>
            <img data-face-photo class="d-none" alt="Fotografía facial capturada">
        </div>
        <div class="face-camera-actions">
            <button type="button" class="btn btn-outline-primary" data-face-start><i class="bi bi-camera-video me-1"></i>Activar cámara</button>
            <button type="button" class="btn btn-outline-secondary d-none" data-face-retake>Repetir</button>
        </div>
        <div class="face-camera-status" data-face-status role="status">La prueba de vida guiará la captura automáticamente.</div>
        <label class="visually-hidden" for="assessment_face_image">Imagen temporal generada por la cámara</label>
        <input class="d-none" id="assessment_face_image" name="face_image" type="file" accept="image/jpeg,image/png,image/webp" aria-hidden="true" tabindex="-1">
        <p class="form-text">La captura facial se procesa temporalmente y no se conserva como evidencia.</p>
    </div>
    <div class="alert alert-danger d-none" data-assessment-face-error role="alert"></div>
    <button class="btn btn-primary" type="button" data-assessment-face-submit <?= empty($assessmentFaceSettings['enabled']) ? 'disabled' : '' ?>>
        <i class="bi bi-person-check me-1"></i>Verificar identidad y continuar
    </button>
</section>
