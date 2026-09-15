<?php $companyName = (string) ($company['name'] ?? 'Empresa'); ?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($title ?? 'Verificar usuario') ?></title>
    <style>
        :root { color-scheme: light; font-family: system-ui, -apple-system, sans-serif; background:#f4f7fb; color:#172033; }
        body { margin:0; min-height:100vh; display:grid; place-items:center; padding:24px; box-sizing:border-box; }
        main { width:min(100%, 620px); background:#fff; border:1px solid #dfe5ee; border-radius:18px; padding:clamp(24px,5vw,48px); box-shadow:0 14px 40px rgba(22,39,70,.08); }
        h1 { margin:0 0 8px; font-size:clamp(1.6rem,4vw,2.2rem); } p { color:#5d687b; line-height:1.55; }
        label { display:block; font-weight:650; margin:24px 0 8px; } input { width:100%; box-sizing:border-box; border:1px solid #bfc9d8; border-radius:10px; padding:13px 14px; font:inherit; }
        button { margin-top:16px; width:100%; border:0; border-radius:10px; padding:13px 16px; color:#fff; background:#2857c5; font:inherit; font-weight:700; cursor:pointer; }
        .alert { margin-top:22px; padding:14px 16px; border-radius:10px; background:#fff0f0; color:#a12424; } .success { background:#edf8f1; color:#176b39; }
        table { width:100%; border-collapse:collapse; margin-top:16px; } th,td { text-align:left; padding:11px 8px; border-bottom:1px solid #e5e9f0; } th { color:#5d687b; font-size:.85rem; }
        .muted { font-size:.9rem; color:#6e7888; } .sr-only { position:absolute; width:1px; height:1px; overflow:hidden; clip:rect(0,0,0,0); }
    </style>
</head>
<body><main>
    <p class="muted">Verificación de usuarios · <?= e($companyName) ?></p>
    <h1>¿En qué procesos está el usuario?</h1>
    <p>Ingresa el correo electrónico para consultar su estado y los procesos asociados a esta empresa.</p>
    <form method="post" novalidate>
        <label for="email">Correo electrónico</label>
        <input id="email" name="email" type="email" value="<?= e($email ?? '') ?>" autocomplete="email" required aria-describedby="email-help">
        <span id="email-help" class="sr-only">Debe ser un correo electrónico válido.</span>
        <button type="submit">Verificar usuario</button>
    </form>
    <?php if ($error): ?><div class="alert" role="alert"><?= e($error) ?></div><?php endif; ?>
    <?php if ($result && !empty($result['active'])): ?>
        <div class="alert success" role="status"><strong><?= e($result['user']['name'] ?? 'El usuario') ?></strong> está activo en esta empresa.</div>
        <?php if (!empty($result['processes'])): ?>
            <table><caption class="sr-only">Procesos asociados</caption><thead><tr><th>Tipo</th><th>Proceso</th><th>Fecha de inicio</th><th>Hora de inicio</th></tr></thead><tbody>
            <?php foreach ($result['processes'] as $process): ?>
                <?php $startsAt = trim((string) ($process['starts_at'] ?? '')); $startDate = $startTime = 'Sin definir'; if ($startsAt !== ''): try { $start = new DateTimeImmutable($startsAt); $startDate = $start->format('d/m/Y'); $startTime = $start->format('H:i'); } catch (Throwable $exception) { $startDate = $startsAt; } endif; ?>
                <tr><td><?= e($process['type']) ?></td><td><?= e($process['name']) ?></td><td><?= e($startDate) ?></td><td><?= e($startTime) ?></td></tr>
            <?php endforeach; ?>
            </tbody></table>
        <?php else: ?><p class="muted">El usuario está activo, pero no tiene procesos asociados actualmente.</p><?php endif; ?>
    <?php endif; ?>
</main></body></html>
