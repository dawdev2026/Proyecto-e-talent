<div class="auth-branding auth-position-center">
<div class="auth-card mx-auto shadow-sm">
    <div class="text-center mb-4"><h1 class="auth-title fw-bold mb-1">Restablecer clave</h1><p class="auth-subtitle mb-0">Define una clave nueva para tu cuenta.</p></div>
    <?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>
    <?php if ($validToken): ?>
        <form method="post" class="needs-validation" novalidate>
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="token" value="<?= e($token) ?>">
            <div class="form-floating mb-3"><input id="password" class="form-control" type="password" name="password" minlength="8" placeholder="Nueva clave" required><label for="password">Nueva clave</label></div>
            <div class="form-floating mb-4"><input id="password_confirmation" class="form-control" type="password" name="password_confirmation" minlength="8" placeholder="Confirmar clave" required><label for="password_confirmation">Confirmar clave</label></div>
            <button class="btn btn-primary btn-lg w-100" type="submit">Guardar nueva clave</button>
        </form>
    <?php else: ?><a class="btn btn-primary w-100" href="<?= e(route_url('password.forgot')) ?>">Solicitar nuevo enlace</a><?php endif; ?>
</div>
</div>
