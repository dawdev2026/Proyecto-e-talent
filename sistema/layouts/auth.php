<?php
$authTitle = $title ?? 'e-talent';
$authTheme = $_COOKIE['corePlatformTheme'] ?? 'light';
if (!in_array($authTheme, ['light', 'dark'], true)) {
    $authTheme = 'light';
}
?>
<!doctype html>
<html lang="es" data-theme="<?= e($authTheme) ?>" data-coreui-theme="<?= e($authTheme) ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="e-talent">
    <title><?= e($authTitle) ?></title>
    <link href="<?= e(url('assets/coreui/css/style.min.css')) ?>" rel="stylesheet">
    <link href="<?= e(url('assets/coreui/css/vendors/simplebar.css')) ?>" rel="stylesheet">
    <link href="<?= e(url('assets/css/coreui-adapter.css')) ?>" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="<?= e(url('assets/css/app.css')) ?>" rel="stylesheet">
    <script>
        const savedTheme = localStorage.getItem('corePlatformTheme') || '<?= e($authTheme) ?>';
        document.documentElement.setAttribute('data-theme', savedTheme);
        document.documentElement.setAttribute('data-coreui-theme', savedTheme);
    </script>
</head>
<body class="auth-body">
<main class="auth-shell">
    <div class="container-fluid p-0">
        <?= $content ?>
    </div>
</main>
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="<?= e(url('assets/coreui/vendors/@coreui/coreui/js/coreui.bundle.min.js')) ?>"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="<?= e(url('assets/js/vendor/jquery.rut.local.js')) ?>"></script>
<script src="<?= e(url('assets/js/app.js')) ?>"></script>
</body>
</html>
