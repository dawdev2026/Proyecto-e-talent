<?php
$user = current_user();
$currentPage = $currentPage ?? '';
$homeRoute = $user ? profile_home_route($user) : 'dashboard';
$homeUrl = route_url($homeRoute);
$designSettings = (new PlatformSettingsModel())->designSettings();
$designStyle = sprintf(
    '--app-primary:%s;--app-primary-dark:%s;--app-primary-soft:color-mix(in srgb, %s 14%%, %s);--app-bg:%s;--app-surface:%s;--app-surface-2:%s;--app-ink:%s;--app-text:%s;--app-muted:color-mix(in srgb, %s 58%%, %s);--app-border:%s;--app-border-soft:color-mix(in srgb, %s 58%%, %s);--app-font-family:%s;--app-font-size:%spx;--topbar-bg:%s;--topbar-menu-bg:%s;--topbar-menu-button:%s;--topbar-height:%spx;--topbar-logo-width:%spx;--card-header-bg:%s;--card-content-bg:%s;--card-text:%s;--button-bg:%s;--button-text:%s;--table-border:%s;--table-header-bg:%s;--table-header-text:%s;--table-body-bg:%s;--table-body-text:%s;',
    e($designSettings['app_primary_color']),
    e($designSettings['app_primary_color']),
    e($designSettings['button_background_color']),
    e($designSettings['layout_background_color']),
    e($designSettings['layout_background_color']),
    e($designSettings['card_content_background_color']),
    e($designSettings['card_header_background_color']),
    e($designSettings['portal_text_color']),
    e($designSettings['portal_text_color']),
    e($designSettings['portal_text_color']),
    e($designSettings['card_content_background_color']),
    e($designSettings['table_border_color']),
    e($designSettings['table_border_color']),
    e($designSettings['card_content_background_color']),
    e($designSettings['app_font_family']),
    e($designSettings['app_font_size']),
    e($designSettings['topbar_background_color']),
    e($designSettings['topbar_menu_background_color']),
    e($designSettings['topbar_menu_button_color']),
    e($designSettings['topbar_height']),
    e($designSettings['topbar_logo_width']),
    e($designSettings['card_header_background_color']),
    e($designSettings['card_content_background_color']),
    e($designSettings['card_text_color']),
    e($designSettings['button_background_color']),
    e($designSettings['button_text_color']),
    e($designSettings['table_border_color']),
    e($designSettings['table_header_background_color']),
    e($designSettings['table_header_text_color']),
    e($designSettings['table_body_background_color']),
    e($designSettings['table_body_text_color'])
);
$designStyle .= sprintf(
    '--font-heading-family:%s;--font-body-family:%s;--font-size-h1:%spx;--font-size-h2:%spx;--font-size-h3:%spx;--font-size-subtitle:%spx;--font-size-body:%spx;--font-size-small:%spx;--font-size-nav:%spx;--font-size-button:%spx;--font-size-table:%spx;',
    e($designSettings['font_heading_family']),
    e($designSettings['font_body_family']),
    e($designSettings['font_size_h1']),
    e($designSettings['font_size_h2']),
    e($designSettings['font_size_h3']),
    e($designSettings['font_size_subtitle']),
    e($designSettings['font_size_body']),
    e($designSettings['font_size_small']),
    e($designSettings['font_size_nav']),
    e($designSettings['font_size_button']),
    e($designSettings['font_size_table'])
);
$topbarLogoPosition = 'start';
$pageLabels = [
    'dashboard' => 'Home Portal',
    'tests' => 'Evaluaciones',
    'tests.assign' => 'Asignar evaluaciones',
    'my-tests' => 'Mis evaluaciones',
    'companies' => 'Empresas',
    'users' => 'Usuarios',
    'user-fields' => 'Campos usuario',
    'profiles' => 'Perfiles',
    'settings' => 'Configuracion',
    'tests.settings' => 'Configuracion Evaluaciones',
    'test-process.dashboard' => 'Dashboard Avance',
    'test-process.review-assignment' => 'Revisar asignacion',
    'test-processes' => 'Procesos',
    'interviews' => 'Entrevistas seleccion',
    'interviews.settings' => 'Configuracion Entrevistas',
];
$breadcrumbLabel = $pageLabels[$currentPage] ?? labelize($currentPage ?: 'Inicio');
$pageTitleLabel = trim((string) preg_replace('/\s*\|\s*Metricatest.*/', '', $title ?? ''));
$breadcrumbTrail = [
    ['label' => 'Inicio', 'route' => $homeRoute, 'icon' => 'bi-house-door'],
];
if ($currentPage && $currentPage !== 'dashboard') {
    $breadcrumbTrail[] = ['label' => $breadcrumbLabel, 'route' => $currentPage, 'icon' => null];
}
if ($pageTitleLabel && !in_array($pageTitleLabel, array_column($breadcrumbTrail, 'label'), true)) {
    $breadcrumbTrail[] = ['label' => $pageTitleLabel, 'route' => null, 'icon' => null];
}
$lastLoginRaw = $_SESSION['last_login_at'] ?? ($user['last_login_at'] ?? null);
$lastLoginLabel = 'Sin registro previo';
if ($lastLoginRaw) {
    try {
        $lastLoginLabel = (new DateTimeImmutable((string) $lastLoginRaw))->format('d/m/Y H:i');
    } catch (Throwable $exception) {
        $lastLoginLabel = (string) $lastLoginRaw;
    }
}
$userInitials = 'AD';
if ($user) {
    $nameParts = preg_split('/\s+/', trim((string) $user['name'])) ?: [];
    $firstInitial = $nameParts[0][0] ?? '';
    $secondInitial = $nameParts[1][0] ?? ($nameParts[0][1] ?? '');
    $userInitials = strtoupper(substr($firstInitial . $secondInitial, 0, 2)) ?: 'AD';
}
$coreMenuItems = [];
$testsMenuItems = [];
$interviewsMenuItems = [];

if ($user) {
    $coreMenuMap = [
        ['permission' => 'manage_companies', 'page' => 'companies', 'route' => 'companies', 'label' => 'Empresas', 'icon' => 'bi-buildings'],
        ['permission' => 'manage_users', 'page' => 'users', 'route' => 'users', 'label' => 'Usuarios', 'icon' => 'bi-people'],
        ['permission' => 'manage_user_fields', 'page' => 'user-fields', 'route' => 'user-fields', 'label' => 'Campos usuario', 'icon' => 'bi-ui-checks-grid'],
        ['permission' => 'manage_profiles', 'page' => 'profiles', 'route' => 'profiles', 'label' => 'Perfiles', 'icon' => 'bi-shield-lock'],
        ['permission' => 'manage_platform_settings', 'page' => 'settings', 'route' => 'settings', 'label' => 'Configuracion', 'icon' => 'bi-sliders'],
        ['permission' => 'manage_platform_settings', 'page' => 'tests.settings', 'route' => 'tests.settings', 'label' => 'Configuracion / Evaluaciones', 'icon' => 'bi-clipboard2-pulse'],
    ];

    foreach ($coreMenuMap as $item) {
        if (has_permission($item['permission'])) {
            $coreMenuItems[] = $item;
        }
    }
    if (has_permission('manage_company_users')) {
        $coreMenuItems[] = ['page' => 'users', 'route' => 'users', 'label' => 'Usuarios', 'icon' => 'bi-people'];
    }

    if (has_permission('take_tests') || has_permission('view_test_results')) {
        $testsMenuItems[] = ['page' => 'my-tests', 'route' => 'my-tests', 'label' => 'Mis evaluaciones', 'icon' => 'bi-clipboard-check'];
    }
    if (has_permission('manage_tests')) {
        $testsMenuItems[] = ['page' => 'tests', 'route' => 'tests', 'label' => 'Evaluaciones', 'icon' => 'bi-list-check'];
        $testsMenuItems[] = ['page' => 'tests.progress', 'route' => 'tests.progress', 'label' => 'Estado Avance', 'icon' => 'bi-graph-up-arrow'];
    }
    if (has_permission('view_test_process_dashboard')) {
        $testsMenuItems[] = ['page' => 'test-process.dashboard', 'route' => 'test-process.dashboard', 'label' => 'Dashboard Avance', 'icon' => 'bi-bar-chart-line'];
    }
    if (has_permission('manage_tests') || has_permission('manage_test_processes') || has_permission('manage_company_processes')) {
        $testsMenuItems[] = ['page' => 'test-process.review-assignment', 'route' => 'test-process.review-assignment', 'label' => 'Revisar asignacion', 'icon' => 'bi-person-lines-fill'];
    }
    if (has_permission('manage_tests') || has_permission('manage_test_processes') || has_permission('view_test_process_progress') || has_permission('view_test_process_dashboard') || has_permission('view_test_process_results')) {
        $testsMenuItems[] = ['page' => 'test-processes', 'route' => 'test-processes', 'label' => 'Procesos', 'icon' => 'bi-kanban'];
    }
    if (has_permission('assign_tests')) {
        $testsMenuItems[] = ['page' => 'tests.assign', 'route' => 'tests.assign', 'label' => 'Asignar evaluaciones', 'icon' => 'bi-send-check'];
    }
    if (has_permission('manage_interview_processes') || has_permission('manage_company_interviews') || has_permission('conduct_selection_interviews') || has_permission('view_interview_reports')) {
        $interviewsMenuItems[] = ['page' => 'interviews', 'route' => 'interviews', 'label' => 'Procesos', 'icon' => 'bi-camera-video'];
    }
    if (has_permission('manage_interview_settings')) {
        $interviewsMenuItems[] = ['page' => 'interviews.settings', 'route' => 'interviews.settings', 'label' => 'Configuracion', 'icon' => 'bi-sliders'];
    }
}
$activeCoreItem = null;
foreach ($coreMenuItems as $item) {
    if ($item['page'] === $currentPage) {
        $activeCoreItem = $item;
        break;
    }
}
$coreMenuLabel = $activeCoreItem['label'] ?? 'Administracion';
$coreMenuIcon = $activeCoreItem['icon'] ?? 'bi-gear';
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="<?= e($designSettings['html_meta_description']) ?>">
    <title><?= e($title ?? $designSettings['html_title']) ?></title>
    <?php if ($designSettings['html_favicon_path']): ?>
        <link rel="icon" href="<?= e(url($designSettings['html_favicon_path'])) ?>">
    <?php endif; ?>
    <script>
        document.documentElement.setAttribute('data-theme', localStorage.getItem('corePlatformTheme') || (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light'));
    </script>
    <?php if ($designSettings['app_font_url']): ?>
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="<?= e($designSettings['app_font_url']) ?>" rel="stylesheet">
    <?php endif; ?>
    <link href="<?= e(url('assets/vendor/bootstrap.min.css')) ?>" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/datatables.net-bs5@1.13.8/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/datatables.net-buttons-bs5@2.4.2/css/buttons.bootstrap5.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/notyf@3.10.0/notyf.min.css" rel="stylesheet">
    <link href="<?= e(url('assets/css/app.css')) ?>" rel="stylesheet">
    <?php if ($user): ?>
        <script>window.AppBackUrl = <?= json_encode(back_url(), JSON_UNESCAPED_SLASHES) ?>;</script>
    <?php endif; ?>
</head>
<body style="<?= $designStyle ?>">
<?php if ($user): ?>
<nav class="navbar navbar-expand-lg navbar-light app-navbar app-navbar-logo-<?= e($topbarLogoPosition) ?> sticky-top">
    <div class="container-fluid px-lg-4">
        <a class="navbar-brand fw-bold d-flex align-items-center gap-2" href="<?= e($homeUrl) ?>">
            <?php if ($designSettings['topbar_icon_path']): ?>
                <img class="brand-image" src="<?= e(url($designSettings['topbar_icon_path'])) ?>" alt="<?= e($designSettings['topbar_name']) ?>">
                <span><?= e($designSettings['topbar_name']) ?></span>
            <?php else: ?>
                <span class="brand-mark"><i class="bi bi-shield-check"></i></span>
                <?= e($designSettings['topbar_name']) ?>
            <?php endif; ?>
        </a>
        <button class="navbar-toggler app-navbar-toggler ms-auto" type="button" data-bs-toggle="collapse" data-bs-target="#appTopNav" aria-controls="appTopNav" aria-expanded="false" aria-label="Abrir menu">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div id="appTopNav" class="collapse navbar-collapse app-navbar-collapse">
            <ul class="navbar-nav portal-nav app-top-nav">
                <li class="nav-item">
                    <a class="nav-link <?= e(active($homeRoute, $currentPage)) ?>" href="<?= e($homeUrl) ?>">
                        <i class="bi bi-house-door"></i>
                        <span>Inicio</span>
                    </a>
                </li>
                <?php if ($testsMenuItems): ?>
                    <?php $testsActive = in_array($currentPage, array_column($testsMenuItems, 'page'), true); ?>
                    <li class="nav-item dropdown">
                        <button class="nav-link dropdown-toggle <?= $testsActive ? 'active' : '' ?>" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="bi bi-clipboard-check"></i>
                            <span>Evaluaciones</span>
                        </button>
                        <ul class="dropdown-menu app-nav-dropdown">
                            <?php foreach ($testsMenuItems as $item): ?>
                                <li>
                                    <a class="dropdown-item <?= e(active($item['page'], $currentPage)) ?>" href="<?= e(route_url($item['route'])) ?>">
                                        <i class="bi <?= e($item['icon']) ?>"></i>
                                        <span><?= e($item['label']) ?></span>
                                    </a>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </li>
                <?php endif; ?>
                <?php if ($interviewsMenuItems): ?>
                    <?php $interviewsActive = in_array($currentPage, array_column($interviewsMenuItems, 'page'), true); ?>
                    <li class="nav-item dropdown">
                        <button class="nav-link dropdown-toggle <?= $interviewsActive ? 'active' : '' ?>" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="bi bi-camera-video"></i>
                            <span>Entrevistas</span>
                        </button>
                        <ul class="dropdown-menu app-nav-dropdown">
                            <?php foreach ($interviewsMenuItems as $item): ?>
                                <li>
                                    <a class="dropdown-item <?= e(active($item['page'], $currentPage)) ?>" href="<?= e(route_url($item['route'])) ?>">
                                        <i class="bi <?= e($item['icon']) ?>"></i>
                                        <span><?= e($item['label']) ?></span>
                                    </a>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </li>
                <?php endif; ?>
                <?php if ($coreMenuItems): ?>
                    <?php $coreActive = in_array($currentPage, array_column($coreMenuItems, 'page'), true); ?>
                    <li class="nav-item dropdown">
                        <button class="nav-link dropdown-toggle <?= $coreActive ? 'active' : '' ?>" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="bi <?= e($coreMenuIcon) ?>"></i>
                            <span><?= e($coreMenuLabel) ?></span>
                        </button>
                        <ul class="dropdown-menu app-nav-dropdown">
                            <?php foreach ($coreMenuItems as $item): ?>
                                <li>
                                    <a class="dropdown-item <?= e(active($item['page'], $currentPage)) ?>" href="<?= e(route_url($item['route'])) ?>">
                                        <i class="bi <?= e($item['icon']) ?>"></i>
                                        <span><?= e($item['label']) ?></span>
                                    </a>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </li>
                <?php endif; ?>
            </ul>
        </div>
        <button class="btn btn-sm theme-toggle app-icon-button ms-2 me-2 d-lg-none" type="button" aria-label="Cambiar tema">
            <i class="bi bi-moon-stars"></i>
            <span class="theme-toggle-label visually-hidden">Oscuro</span>
        </button>
        <div class="app-navbar-actions d-flex align-items-center gap-2 ms-lg-auto">
            <button class="btn btn-sm theme-toggle app-icon-button d-none d-lg-inline-grid" type="button" aria-label="Cambiar tema">
                <i class="bi bi-moon-stars"></i>
                <span class="theme-toggle-label visually-hidden">Oscuro</span>
            </button>
            <button class="btn btn-sm app-icon-button app-help-button d-none d-lg-inline-grid" type="button" aria-label="Ayuda">
                <i class="bi bi-question-circle"></i>
            </button>
            <div class="dropdown user-menu">
                <button class="btn user-menu-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                    <span class="user-avatar"><?= e($userInitials) ?></span>
                    <span class="user-menu-name d-none d-md-inline"><?= e($user['name']) ?></span>
                    <i class="bi bi-chevron-down d-none d-md-inline"></i>
                </button>
                <ul class="dropdown-menu dropdown-menu-end app-nav-dropdown">
                    <li><span class="dropdown-item-text user-menu-meta"><?= e($user['profile_name'] ?: 'Sin perfil') ?><?= $user['company_name'] ? ' · ' . e($user['company_name']) : '' ?></span></li>
                </ul>
            </div>
            <a class="btn btn-sm app-icon-button app-logout-button" href="<?= e(route_url('logout')) ?>" aria-label="Cerrar sesion"><i class="bi bi-box-arrow-right"></i></a>
        </div>
    </div>
</nav>
<?php endif; ?>

<main class="<?= $user ? 'app-shell' : 'auth-shell' ?>">
    <?php if ($user): ?>
        <div class="app-breadcrumb-bar">
            <div class="container-fluid px-lg-4 app-breadcrumb-content">
                <nav class="app-breadcrumb-trail" aria-label="Breadcrumb">
                    <?php foreach ($breadcrumbTrail as $index => $crumb): ?>
                        <?php $isLast = $index === array_key_last($breadcrumbTrail); ?>
                        <?php if ($index > 0): ?><span class="app-breadcrumb-separator">/</span><?php endif; ?>
                        <?php if (!$isLast && $crumb['route']): ?>
                            <a href="<?= e(route_url($crumb['route'])) ?>">
                                <?php if ($crumb['icon']): ?><i class="bi <?= e($crumb['icon']) ?>"></i><?php endif; ?>
                                <span><?= e($crumb['label']) ?></span>
                            </a>
                        <?php else: ?>
                            <span class="<?= $isLast ? 'is-current' : '' ?>"><?= e($crumb['label']) ?></span>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </nav>
                <div class="app-breadcrumb-session d-none d-md-flex">
                    <span>Ultimo acceso: <?= e($lastLoginLabel) ?></span>
                    <span class="app-online-status"><i class="bi bi-circle-fill"></i> En linea</span>
                </div>
            </div>
        </div>
    <?php endif; ?>
    <div class="<?= $user ? 'container-fluid px-lg-4' : 'container' ?>">
        <?php foreach (flashes() as $message): ?>
            <div data-app-message data-type="<?= e($message['type']) ?>" hidden>
                <?= e($message['message']) ?>
            </div>
        <?php endforeach; ?>

        <?= $content ?>
    </div>
</main>

<div class="app-processing-overlay" data-app-processing-overlay hidden aria-live="polite" aria-busy="true">
    <div class="app-processing-card" role="status">
        <span class="spinner-border" aria-hidden="true"></span>
        <div class="app-processing-body">
            <strong>Procesando Informacion...</strong>
            <span data-app-processing-detail hidden></span>
            <div class="app-processing-progress" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuetext="Procesando">
                <span data-app-processing-progress></span>
            </div>
            <span class="app-processing-percent" data-app-processing-percent hidden>0%</span>
        </div>
    </div>
</div>

<div class="app-drawer-backdrop" data-app-drawer-close hidden></div>
<aside id="appDrawer" class="app-drawer app-drawer-md" aria-hidden="true" aria-modal="true" role="dialog">
    <header class="app-drawer-header">
        <div>
            <p class="app-drawer-eyebrow mb-1">Detalle</p>
            <h2 id="appDrawerTitle" class="app-drawer-title">Panel</h2>
        </div>
        <button class="btn btn-sm btn-outline-secondary app-drawer-close" type="button" data-app-drawer-close aria-label="Cerrar">
            <i class="bi bi-x-lg"></i>
        </button>
    </header>
    <div id="appDrawerBody" class="app-drawer-body">
        <div class="drawer-loading">
            <span class="spinner-border spinner-border-sm" aria-hidden="true"></span>
            <span>Cargando...</span>
        </div>
    </div>
</aside>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/datatables.net@1.13.8/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/datatables.net-bs5@1.13.8/js/dataTables.bootstrap5.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/datatables.net-buttons@2.4.2/js/dataTables.buttons.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/datatables.net-buttons-bs5@2.4.2/js/buttons.bootstrap5.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/jszip@3.10.1/dist/jszip.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/pdfmake@0.2.10/build/pdfmake.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/pdfmake@0.2.10/build/vfs_fonts.js"></script>
<script src="https://cdn.jsdelivr.net/npm/datatables.net-buttons@2.4.2/js/buttons.html5.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/notyf@3.10.0/notyf.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="https://cdn.jsdelivr.net/npm/tinymce@7/tinymce.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/@tinymce/tinymce-jquery@2/dist/tinymce-jquery.min.js"></script>
<script src="<?= e(url('assets/js/vendor/jquery.rut.local.js')) ?>"></script>
<script src="<?= e(url('assets/js/app.js')) ?>"></script>
</body>
</html>
