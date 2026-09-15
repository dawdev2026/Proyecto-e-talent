<?php
$loginSettings = $loginSettings ?? PlatformSettingsModel::LOGIN_DEFAULTS;
$twoStepPending = !empty($twoStepPending);
$passwordRecoveryEnabled = !empty($loginSettings['login_password_recovery_enabled']) && in_array((string) $loginSettings['login_password_recovery_enabled'], ['1', 'true', 'on', 'yes'], true);
$loginIdentifier = ($loginSettings['login_identifier'] ?? 'email') === 'rut' ? 'rut' : 'email';
$identifierLabel = $loginIdentifier === 'rut' ? 'RUT' : 'Correo';
$identifierPlaceholder = $loginIdentifier === 'rut' ? '12.345.678-5' : 'Correo';
$identifierType = $loginIdentifier === 'rut' ? 'text' : 'email';
$companyId = function_exists('current_company_context_id') ? current_company_context_id() : 0;
$companyClass = $companyId === 4 ? ' auth-company-4' : '';
$companyClass .= $companyId === 4 && !empty($loginSettings['login_background_path']) ? ' auth-company-4-has-background' : '';
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
<div class="auth-branding auth-position-<?= e($loginSettings['login_form_position']) ?><?= e($companyClass) ?>" style="<?= $authStyle ?><?= $loginSettings['login_background_path'] ? '--login-bg-image:url(' . e(url($loginSettings['login_background_path'])) . ');' : '' ?>">
<div class="card auth-card mx-auto shadow-sm border-0">
    <div class="card-body p-0">
    <div class="text-center mb-4">
        <?php if ($loginSettings['login_logo_path']): ?>
            <img class="auth-logo-image mx-auto mb-3" src="<?= e(url($loginSettings['login_logo_path'])) ?>" alt="">
        <?php else: ?>
            <div class="auth-logo mx-auto mb-3"><i class="bi bi-shield-check"></i></div>
            <h1 class="auth-title fw-bold mb-1"><?= e(trim((string) $loginSettings['login_title']) ?: 'e-talent') ?></h1>
        <?php endif; ?>
        <p class="auth-subtitle mb-0"><?= e($loginSettings['login_subtitle']) ?></p>
    </div>

    <?php if ($error): ?>
        <div data-app-message data-type="danger" hidden><?= e($error) ?></div>
    <?php endif; ?>

    <form method="post" class="needs-validation" autocomplete="off" novalidate>
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
        <?php if ($twoStepPending): ?>
        <p class="visually-hidden" role="status" aria-live="polite">Te enviamos un código de 6 dígitos a tu correo. Revisa tu bandeja de Entrada o Spam y luego, escríbelo en la plataforma para continuar.</p>
        <div class="mb-4">
            <div class="form-floating position-relative">
                <span class="auth-field-icon"><i class="bi bi-shield-lock"></i></span>
                <input id="verification_code" class="form-control" type="text" name="verification_code" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" placeholder="Código de verificación" autocomplete="one-time-code" required autofocus>
                <label for="verification_code">Código de verificación</label>
            </div>
            <div class="form-text">El código tiene una vigencia limitada y se invalida después de usarlo.</div>
        </div>
        <button class="btn btn-primary btn-lg w-100" type="submit"><i class="bi bi-check2-circle me-1"></i>Verificar e ingresar</button>
        <button class="btn btn-outline-secondary w-100 mt-2" type="submit" name="restart_two_step" value="1" formnovalidate>
            <i class="bi bi-arrow-left me-1" aria-hidden="true"></i>
            Volver a ingresar usuario y clave
        </button>
        <?php else: ?>
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
                <button class="auth-password-toggle" type="button" data-password-toggle="password" aria-label="Mostrar clave" aria-pressed="false">
                    <i class="bi bi-eye" aria-hidden="true"></i>
                </button>
                <label for="password">Clave</label>
            </div>
        </div>
        <button class="btn btn-primary btn-lg w-100" type="submit">
            <i class="bi bi-box-arrow-in-right me-1"></i>
            Ingresar
        </button>
        <?php endif; ?>
    </form>
    <?php if ($twoStepPending): ?>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            if (!window.Swal) {
                return;
            }

            Swal.fire({
                title: 'Verificación de dos pasos',
                text: 'Te enviamos un código de 6 dígitos a tu correo. Revisa tu bandeja de Entrada o Spam y luego, escríbelo en la plataforma para continuar.',
                icon: 'info',
                timer: 5000,
                timerProgressBar: true,
                showConfirmButton: false,
                allowOutsideClick: true,
                allowEscapeKey: true
            });
        });
    </script>
    <?php endif; ?>
    <?php if (!$twoStepPending && $passwordRecoveryEnabled): ?>
    <div class="auth-recovery-link-wrap text-center mt-3">
    <a
        class="auth-recovery-link"
        href="<?= e(route_url('password.forgot')) ?>"
        data-drawer-url="<?= e(route_url('password.forgot') . '?drawer=1') ?>"
        data-drawer-title="Recuperar clave"
        data-drawer-size="sm"
    ><i class="bi bi-key me-1" aria-hidden="true"></i>¿Olvidaste tu clave?</a>
    </div>
    <?php endif; ?>
</div>
</div>
</div>
