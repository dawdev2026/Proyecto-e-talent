<div class="auth-branding auth-position-center<?= !empty($isDrawer) ? ' auth-drawer-content' : '' ?>">
<div class="auth-card mx-auto shadow-sm">
    <div class="text-center mb-4">
        <?php if (empty($isDrawer)): ?><h1 class="auth-title fw-bold mb-1">Recuperar clave</h1><?php endif; ?>
        <p class="auth-subtitle mb-0">Te enviaremos instrucciones si el correo está registrado.</p>
    </div>
    <?php if ($message): ?><div class="alert alert-<?= !empty($recoveryEnabled) ? 'success' : 'warning' ?>"><?= e($message) ?></div><?php endif; ?>
    <?php if (!empty($recoveryEnabled)): ?><form method="post" action="<?= e(route_url('password.forgot') . (!empty($isDrawer) ? '?drawer=1' : '')) ?>" class="needs-validation<?= !empty($isDrawer) ? ' password-recovery-form' : '' ?>"<?= !empty($isDrawer) ? ' data-password-recovery-form data-no-processing' : '' ?> novalidate>
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
        <div class="form-floating mb-4"><input id="email" class="form-control" type="email" name="email" placeholder="Correo" required><label for="email">Correo</label></div>
        <button class="btn btn-primary btn-lg w-100" type="submit">Solicitar recuperación</button>
    </form><?php endif; ?>
</div>
</div>
