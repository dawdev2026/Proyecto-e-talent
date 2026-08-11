<div class="auth-branding auth-position-center">
<div class="auth-card mx-auto shadow-sm">
    <div class="text-center mb-4"><h1 class="auth-title fw-bold mb-1">Recuperar clave</h1><p class="auth-subtitle mb-0">Te enviaremos instrucciones si el correo está registrado.</p></div>
    <?php if ($message): ?><div class="alert alert-success"><?= e($message) ?></div><?php endif; ?>
    <form method="post" class="needs-validation" novalidate>
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
        <div class="form-floating mb-4"><input id="email" class="form-control" type="email" name="email" placeholder="Correo" required><label for="email">Correo</label></div>
        <button class="btn btn-primary btn-lg w-100" type="submit">Solicitar recuperación</button>
    </form>
    <a class="btn btn-link w-100 mt-2" href="<?= e(route_url('login')) ?>">Volver al ingreso</a>
</div>
</div>
