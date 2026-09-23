<?php
$loginSettings = $loginSettings ?? PlatformSettingsModel::LOGIN_DEFAULTS;
$authStyle = sprintf(
    '--login-bg:%s;--login-form-bg:%s;--login-title-color:%s;--login-subtitle-color:%s;--login-button-color:%s;--login-title-size:%spx;--login-subtitle-size:%spx;',
    e($loginSettings['login_background_color']), e($loginSettings['login_form_background_color']),
    e($loginSettings['login_title_color']), e($loginSettings['login_subtitle_color']),
    e($loginSettings['login_button_color']), e($loginSettings['login_title_font_size']),
    e($loginSettings['login_subtitle_font_size'])
);
?>
<div class="auth-branding auth-position-<?= e($loginSettings['login_form_position']) ?>" style="<?= $authStyle ?><?= $loginSettings['login_background_path'] ? '--login-bg-image:url(' . e(url($loginSettings['login_background_path'])) . ');' : '' ?>">
    <div class="card auth-card mx-auto shadow-sm border-0">
        <div class="card-body p-0">
        <div class="text-center mb-4">
            <?php if ($loginSettings['login_logo_path']): ?>
                <img class="auth-logo-image mx-auto mb-3" src="<?= e(url($loginSettings['login_logo_path'])) ?>" alt="<?= e(trim((string) ($loginSettings['login_title'] ?? 'Metricatest')) ?: 'Metricatest') ?>">
            <?php else: ?>
                <div class="auth-logo mx-auto mb-3"><i class="bi bi-shield-check"></i></div>
            <?php endif; ?>
            <h1 class="auth-title fw-bold mb-1">Restablecer clave</h1>
            <p class="auth-subtitle mb-0">Define una clave nueva para tu cuenta.</p>
        </div>
        <?php if ($error): ?><div data-app-message data-type="danger" hidden><?= e($error) ?></div><?php endif; ?>
        <?php if ($validToken): ?>
            <form method="post" class="needs-validation" novalidate>
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="token" value="<?= e($token) ?>">
                <div class="mb-3"><div class="form-floating position-relative">
                    <span class="auth-field-icon"><i class="bi bi-lock"></i></span>
                    <input id="password" class="form-control" type="password" name="password" minlength="8" placeholder="Nueva clave" autocomplete="new-password" required>
                    <label for="password">Nueva clave</label>
                </div></div>
                <div class="mb-4"><div class="form-floating position-relative">
                    <span class="auth-field-icon"><i class="bi bi-shield-lock"></i></span>
                    <input id="password_confirmation" class="form-control" type="password" name="password_confirmation" minlength="8" placeholder="Confirmar clave" autocomplete="new-password" required>
                    <label for="password_confirmation">Confirmar clave</label>
                </div></div>
                <button class="btn btn-primary btn-lg w-100" type="submit"><i class="bi bi-check2-circle me-1"></i> Guardar nueva clave</button>
            </form>
        <?php else: ?>
            <a class="btn btn-primary w-100" href="<?= e(route_url('password.forgot')) ?>">Solicitar nuevo enlace</a>
        <?php endif; ?>
    </div>
    </div>
</div>
