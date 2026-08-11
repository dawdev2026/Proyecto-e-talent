<?php
$loginSettings = $loginSettings ?? PlatformSettingsModel::LOGIN_DEFAULTS;
$loginIdentifier = ($loginSettings['login_identifier'] ?? 'email') === 'rut' ? 'rut' : 'email';
$identifierLabel = $loginIdentifier === 'rut' ? 'RUT' : 'Correo';
$identifierPlaceholder = $loginIdentifier === 'rut' ? '12.345.678-5' : 'Correo';
$identifierType = $loginIdentifier === 'rut' ? 'text' : 'email';
$authStyle = sprintf(
    '--login-bg:%s;--login-form-bg:%s;--login-title-color:%s;--login-subtitle-color:%s;--login-button-color:%s;--login-title-size:%spx;--login-subtitle-size:%spx;',
    e($loginSettings['login_background_color']),
    e($loginSettings['login_form_background_color']),
    e($loginSettings['login_title_color']),
    e($loginSettings['login_subtitle_color']),
    e($loginSettings['login_button_color']),
    e($loginSettings['login_title_font_size']),
    e($loginSettings['login_subtitle_font_size'])
);
?>
<div class="auth-branding auth-position-<?= e($loginSettings['login_form_position']) ?>" style="<?= $authStyle ?><?= $loginSettings['login_background_path'] ? '--login-bg-image:url(' . e(url($loginSettings['login_background_path'])) . ');' : '' ?>">
<div class="auth-card mx-auto shadow-sm">
    <div class="text-center mb-4">
        <?php if ($loginSettings['login_logo_path']): ?>
            <img class="auth-logo-image mx-auto mb-3" src="<?= e(url($loginSettings['login_logo_path'])) ?>" alt="<?= e($loginSettings['login_title']) ?>">
        <?php else: ?>
            <div class="auth-logo mx-auto mb-3"><i class="bi bi-shield-check"></i></div>
        <?php endif; ?>
        <?php if (trim((string) $loginSettings['login_title']) !== ''): ?>
            <h1 class="auth-title fw-bold mb-1"><?= e($loginSettings['login_title']) ?></h1>
        <?php endif; ?>
        <p class="auth-subtitle mb-0"><?= e($loginSettings['login_subtitle']) ?></p>
    </div>

    <?php if ($error): ?>
        <div data-app-message data-type="danger" hidden><?= e($error) ?></div>
    <?php endif; ?>

    <form method="post" class="needs-validation" autocomplete="off" novalidate>
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
        <div class="mb-3">
            <div class="form-floating position-relative">
                <span class="auth-field-icon"><i class="bi bi-person-circle"></i></span>
                <input
                    id="identifier"
                    class="form-control"
                    type="<?= e($identifierType) ?>"
                    name="identifier"
                    value=""
                    placeholder="<?= e($identifierPlaceholder) ?>"
                    autocomplete="off"
                    <?= $loginIdentifier === 'rut' ? 'maxlength="12" data-rut-input' : '' ?>
                    required
                    autofocus
                >
                <label for="identifier"><?= e($identifierLabel) ?></label>
            </div>
        </div>
        <div class="mb-4">
            <div class="form-floating position-relative">
                <span class="auth-field-icon"><i class="bi bi-lock"></i></span>
                <input id="password" class="form-control" type="password" name="password" value="" placeholder="Clave" autocomplete="new-password" required>
                <label for="password">Clave</label>
            </div>
        </div>
        <button class="btn btn-primary btn-lg w-100" type="submit">
            <i class="bi bi-box-arrow-in-right me-1"></i>
            Ingresar
        </button>
    </form>
    <a class="btn btn-link w-100 mt-2" href="<?= e(route_url('password.forgot')) ?>">¿Olvidaste tu clave?</a>
</div>
</div>
