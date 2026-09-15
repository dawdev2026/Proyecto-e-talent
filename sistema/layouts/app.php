<?php
$user = current_user();
$currentPage = $currentPage ?? '';
$homeRoute = $user ? profile_home_route($user) : 'dashboard';
$homeUrl = route_url($homeRoute);
$companyBrandingId = function_exists('current_company_context_id') ? current_company_context_id() : 0;
$isDtBranding = $companyBrandingId === 4;
$designSettings = (new PlatformSettingsModel())->designSettings($companyBrandingId > 0 ? $companyBrandingId : null);
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
    'tests' => 'Evaluaciones Psicométricas',
    'tests.assign' => 'Asignar evaluaciones',
    'my-tests' => 'Mis evaluaciones',
    'companies' => 'Empresas',
    'users' => 'Usuarios',
    'user-fields' => 'Campos usuario',
    'profiles' => 'Perfiles',
    'settings' => 'Configuracion',
    'tests.settings' => 'Configuracion Evaluaciones',
    'tests.progress-ranking' => 'Configurar ranking',
    'tests.ranking-company-assignments' => 'Asignar ranking por empresa',
    'test-process.dashboard' => 'Dashboard Avance',
    'test-process.review-assignment' => 'Revisar asignacion',
    'test-processes' => 'Procesos',
    'interviews' => 'Entrevistas seleccion',
    'interviews.settings' => 'Configuracion Entrevistas',
    'evaluation-surveys.assessments' => 'Evaluaciones con nota',
    'evaluation-surveys.dashboard' => 'Dashboard de evaluaciones',
    'evaluation-surveys.dashboard.results' => 'Resultados de evaluación',
    'evaluation-surveys.surveys' => 'Encuestas de satisfacción',
    'evaluation-surveys.ai.settings' => 'Configuración IA',
    'reports.generate' => 'Registrar Informes',
    'reports.company-assignments' => 'Asignar Informes por Empresas',
    'reports.history' => 'Historial de Informes',
];
$breadcrumbLabel = $pageLabels[$currentPage] ?? labelize($currentPage ?: 'Inicio');
$pageTitleLabel = trim((string) preg_replace('/\s*\|\s*e-talent.*/', '', $title ?? ''));
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
$processMenuItems = [];
$interviewsMenuItems = [];
$evaluationSurveysMenuItems = [];
$reportsMenuItems = [];

if ($user) {
    $coreMenuMap = [
        ['permission' => 'manage_companies', 'page' => 'companies', 'route' => 'companies', 'label' => 'Empresas', 'icon' => 'bi-buildings'],
        ['permission' => 'manage_users', 'page' => 'users', 'route' => 'users', 'label' => 'Usuarios', 'icon' => 'bi-people'],
        ['permission' => 'manage_user_fields', 'page' => 'user-fields', 'route' => 'user-fields', 'label' => 'Campos usuario', 'icon' => 'bi-ui-checks-grid'],
        ['permission' => 'manage_profiles', 'page' => 'profiles', 'route' => 'profiles', 'label' => 'Perfiles', 'icon' => 'bi-shield-lock'],
        ['permission' => 'manage_platform_settings', 'page' => 'settings', 'route' => 'settings', 'label' => 'Configuracion', 'icon' => 'bi-sliders'],
        ['permission' => 'manage_company_branding', 'page' => 'settings', 'route' => 'settings', 'label' => 'Identidad visual', 'icon' => 'bi-palette'],
        ['permission' => 'manage_company_branding', 'page' => 'settings', 'route' => route_url('settings') . '?tab=verification', 'label' => 'Verificación de usuarios', 'icon' => 'bi-person-check'],
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
    if (has_permission('manage_company_user_fields')) {
        $coreMenuItems[] = ['page' => 'user-fields', 'route' => 'user-fields', 'label' => 'Campos usuario', 'icon' => 'bi-ui-checks-grid'];
    }

    if (has_permission('take_tests') || has_permission('view_test_results')) {
        $testsMenuItems[] = ['page' => 'my-tests', 'route' => 'my-tests', 'label' => 'Mis evaluaciones', 'icon' => 'bi-clipboard-check'];
    }
    if (has_permission('manage_tests')) {
        $testsMenuItems[] = ['page' => 'tests', 'route' => 'tests', 'label' => 'Evaluaciones', 'icon' => 'bi-list-check'];
        $testsMenuItems[] = ['page' => 'tests.progress', 'route' => 'tests.progress', 'label' => 'Estado Avance', 'icon' => 'bi-graph-up-arrow'];
    }
    if (has_permission('manage_ranking_presets')) {
        $testsMenuItems[] = ['page' => 'tests.progress-ranking', 'route' => 'tests.progress-ranking', 'label' => 'Configurar ranking', 'icon' => 'bi-sliders'];
        $testsMenuItems[] = ['page' => 'tests.ranking-company-assignments', 'route' => 'tests.ranking-company-assignments', 'label' => 'Asignar ranking por empresa', 'icon' => 'bi-diagram-3'];
    }
    if (has_permission('assign_tests')) {
        $testsMenuItems[] = ['page' => 'tests.assign', 'route' => 'tests.assign', 'label' => 'Asignar evaluaciones', 'icon' => 'bi-send-check'];
        $testsMenuItems[] = ['page' => 'tests.company-assignments', 'route' => 'tests.company-assignments', 'label' => 'Asignar evaluaciones Empresa', 'icon' => 'bi-buildings'];
    }
    if (has_permission('manage_evaluation_surveys')) {
        $evaluationSurveysMenuItems[] = ['page' => 'evaluation-surveys.assessments', 'route' => 'evaluation-surveys.assessments', 'label' => 'Evaluaciones con nota', 'icon' => 'bi-clipboard2-check'];
        if (is_general_admin()) {
            $evaluationSurveysMenuItems[] = ['page' => 'evaluation-surveys.surveys', 'route' => 'evaluation-surveys.surveys', 'label' => 'Encuestas de satisfacción', 'icon' => 'bi-bar-chart-line'];
        }
        $evaluationSurveysMenuItems[] = ['page' => 'evaluation-surveys.ai.settings', 'route' => 'evaluation-surveys.ai.settings', 'label' => 'Configuración IA', 'icon' => 'bi-stars'];
    }
    if (is_general_admin() || (string) ($user['role'] ?? '') === 'company_admin') {
        $evaluationSurveysMenuItems[] = ['page' => 'evaluation-surveys.dashboard', 'route' => 'evaluation-surveys.dashboard', 'label' => 'Dashboard de evaluaciones', 'icon' => 'bi-speedometer2'];
    }
    if (has_permission('manage_reports') || has_permission('manage_tests')) {
        $reportsMenuItems[] = ['page' => 'reports.generate', 'route' => 'reports.generate', 'label' => 'Registrar Informes', 'icon' => 'bi-file-earmark-bar-graph'];
        $reportsMenuItems[] = ['page' => 'reports.company-assignments', 'route' => 'reports.company-assignments', 'label' => 'Asignar Informes por Empresas', 'icon' => 'bi-buildings'];
        if (has_permission('view_report_history') || has_permission('manage_reports')) {
            $reportsMenuItems[] = ['page' => 'reports.history', 'route' => 'reports.history', 'label' => 'Historial de Informes', 'icon' => 'bi-clock-history'];
        }
    }
    $isCompanyAdminOrSupervisor = in_array((string) ($user['role'] ?? ''), ['company_admin', 'supervisor_sede'], true)
        || in_array((string) ($user['profile_key'] ?? ''), ['company_admin', 'supervisor_sede'], true);
    if (has_permission('view_test_process_dashboard') && !$isCompanyAdminOrSupervisor) {
        $processMenuItems[] = ['page' => 'test-process.dashboard', 'route' => 'test-process.dashboard', 'label' => 'Dashboard Avance', 'icon' => 'bi-bar-chart-line'];
    }
    if (has_permission('manage_tests') || has_permission('manage_test_processes') || has_permission('manage_company_processes')) {
        $processMenuItems[] = ['page' => 'test-process.review-assignment', 'route' => 'test-process.review-assignment', 'label' => 'Revisar asignación', 'icon' => 'bi-person-lines-fill'];
    }
    if (has_permission('manage_tests') || has_permission('manage_test_processes') || has_permission('view_test_process_progress') || has_permission('view_test_process_dashboard') || has_permission('view_test_process_results')) {
        $processMenuItems[] = ['page' => 'test-processes', 'route' => 'test-processes', 'label' => 'Procesos', 'icon' => 'bi-kanban'];
    }
    $isCompanyAdmin = in_array((string) ($user['role'] ?? ''), ['company_admin'], true)
        || in_array((string) ($user['profile_key'] ?? ''), ['company_admin'], true);
    if ($isCompanyAdmin) {
        $processMenuItems[] = ['page' => 'test-processes', 'route' => 'evaluation-surveys.dashboard.integrity', 'label' => 'Reporte Incidencias', 'icon' => 'bi-shield-exclamation'];
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
$coreMenuLabel = 'Administración';
$coreMenuIcon = 'bi-gear';
$renderSidebarGroup = static function (string $label, string $icon, array $items, string $groupKey) use ($currentPage): void {
    if (!$items) {
        return;
    }
    $isActive = in_array($currentPage, array_column($items, 'page'), true);
    $groupId = 'sidebar-group-' . preg_replace('/[^a-z0-9-]/i', '-', $groupKey);
    ?>
    <li class="nav-group <?= $isActive ? 'show' : '' ?>">
        <a class="nav-link nav-group-toggle <?= $isActive ? 'active' : '' ?>" href="#<?= e($groupId) ?>" aria-expanded="<?= $isActive ? 'true' : 'false' ?>">
            <i class="nav-icon bi <?= e($icon) ?>"></i><span><?= e($label) ?></span>
        </a>
        <ul class="nav-group-items" id="<?= e($groupId) ?>">
            <?php foreach ($items as $item): ?>
                <li class="nav-item"><a class="nav-link <?= e(active($item['page'], $currentPage)) ?>" href="<?= e(route_url($item['route'])) ?>"><span class="nav-icon"><span class="nav-icon-bullet"></span></span><span><?= e($item['label']) ?></span></a></li>
            <?php endforeach; ?>
        </ul>
    </li>
    <?php
};
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
        const initialTheme = localStorage.getItem('corePlatformTheme') || (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
        document.documentElement.setAttribute('data-theme', initialTheme);
        document.documentElement.setAttribute('data-coreui-theme', initialTheme);
    </script>
    <?php if ($designSettings['app_font_url']): ?>
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="<?= e($designSettings['app_font_url']) ?>" rel="stylesheet">
    <?php endif; ?>
    <?php if ($isDtBranding): ?>
        <style>
            @font-face { font-family: "DTGobCL"; src: url("<?= e(url('uploads/branding/company/4/gobcl-regular.woff')) ?>") format("woff"); font-weight: 500; font-style: normal; font-display: swap; }
            @font-face { font-family: "DTGobCL"; src: url("<?= e(url('uploads/branding/company/4/gobcl-bold.woff')) ?>") format("woff"); font-weight: 700 900; font-style: normal; font-display: swap; }
        </style>
    <?php endif; ?>
    <link href="<?= e(url('assets/coreui/css/style.min.css')) ?>" rel="stylesheet">
    <link href="<?= e(url('assets/coreui/css/vendors/simplebar.css')) ?>" rel="stylesheet">
    <link href="<?= e(url('assets/css/coreui-adapter.css')) ?>" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/datatables.net-bs5@1.13.8/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/datatables.net-buttons-bs5@2.4.2/css/buttons.bootstrap5.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/notyf@3.10.0/notyf.min.css" rel="stylesheet">
    <?php if (!empty($useSelect2)): ?>
        <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet">
    <?php endif; ?>
    <link href="<?= e(url('assets/css/app.css')) ?>" rel="stylesheet">
    <?php if ($user): ?>
        <script>window.AppBackUrl = <?= json_encode(back_url(), JSON_UNESCAPED_SLASHES) ?>;</script>
    <?php endif; ?>
</head>
<body style="<?= $designStyle ?>" class="<?= $user ? 'app-body' : 'auth-body' ?>">
<?php if ($user): ?>
<div class="sidebar sidebar-dark sidebar-fixed border-end" id="sidebar">
    <div class="sidebar-header border-bottom">
        <a class="sidebar-brand text-decoration-none" href="<?= e($homeUrl) ?>">
            <?php if ($topbarIconAvailable): ?><img class="sidebar-brand-full brand-image" src="<?= e(url($topbarIconPath)) ?>" alt="<?= e($designSettings['topbar_name']) ?>"><?php else: ?><span class="sidebar-brand-full d-flex align-items-center gap-2"><span class="brand-mark"><i class="bi bi-shield-check"></i></span><?= e($designSettings['topbar_name']) ?></span><?php endif; ?>
        </a>
        <button class="btn-close d-lg-none" type="button" data-coreui-theme="dark" aria-label="Cerrar menú" data-sidebar-close></button>
    </div>
    <ul class="sidebar-nav" data-coreui="navigation" data-simplebar>
        <li class="nav-item"><a class="nav-link <?= e(active($homeRoute, $currentPage)) ?>" href="<?= e($homeUrl) ?>"><i class="nav-icon bi bi-house-door"></i><span>Inicio</span></a></li>
        <?php $renderSidebarGroup($coreMenuLabel, $coreMenuIcon, $coreMenuItems, 'core'); ?>
        <?php $renderSidebarGroup('Evaluaciones Psicométricas', 'bi-clipboard-check', $testsMenuItems, 'tests'); ?>
        <?php $renderSidebarGroup('Encuestas y Evaluaciones', 'bi-ui-checks-grid', $evaluationSurveysMenuItems, 'surveys'); ?>
        <?php $renderSidebarGroup('Informes', 'bi-file-earmark-bar-graph', $reportsMenuItems, 'reports'); ?>
        <?php $renderSidebarGroup('Procesos', 'bi-kanban', $processMenuItems, 'processes'); ?>
        <?php $renderSidebarGroup('Entrevistas', 'bi-camera-video', $interviewsMenuItems, 'interviews'); ?>
    </ul>
</div>
<div class="wrapper d-flex flex-column min-vh-100">
<header class="header header-sticky p-0 mb-4">
    <div class="container-fluid border-bottom px-4"><button class="header-toggler" type="button" aria-label="Abrir menú" data-sidebar-toggle><i class="bi bi-list"></i></button><div class="ms-auto d-flex align-items-center gap-2"><button class="btn btn-link header-toggler theme-toggle" type="button" aria-label="Cambiar tema"><i class="bi bi-moon-stars"></i></button><div class="dropdown user-menu"><button class="btn btn-link d-flex align-items-center gap-2 text-decoration-none" type="button" data-coreui-toggle="dropdown" aria-expanded="false"><span class="user-avatar"><?= e($userInitials) ?></span><span class="user-menu-name d-none d-md-inline"><?= e($user['name']) ?></span><i class="bi bi-chevron-down"></i></button><ul class="dropdown-menu dropdown-menu-end app-nav-dropdown"><li><span class="dropdown-item-text user-menu-meta"><?= e($user['profile_name'] ?: 'Sin perfil') ?><?= $user['company_name'] ? ' · ' . e($user['company_name']) : '' ?></span></li><li><a class="dropdown-item" href="<?= e(route_url('logout')) ?>"><i class="bi bi-box-arrow-right me-2"></i>Cerrar sesión</a></li></ul></div></div></div>
    <div class="container-fluid px-4"><nav aria-label="breadcrumb"><ol class="breadcrumb my-0 py-3"><?php foreach ($breadcrumbTrail as $index => $crumb): ?><?php $isLast = $index === array_key_last($breadcrumbTrail); ?><li class="breadcrumb-item <?= $isLast ? 'active' : '' ?>" <?= $isLast ? 'aria-current="page"' : '' ?>><?php if (!$isLast && $crumb['route']): ?><a href="<?= e(route_url($crumb['route'])) ?>"><?= $crumb['icon'] ? '<i class="bi ' . e($crumb['icon']) . ' me-1"></i>' : '' ?><?= e($crumb['label']) ?></a><?php else: ?><?= e($crumb['label']) ?><?php endif; ?></li><?php endforeach; ?></ol></nav></div>
</header>
<?php endif; ?>

<main class="<?= $user ? 'body flex-grow-1 px-4 app-shell' : 'auth-shell' ?>">
    <div class="<?= $user ? 'container-fluid px-lg-4' : 'container' ?>">
        <?php foreach (flashes() as $message): ?>
            <div data-app-message data-type="<?= e($message['type']) ?>" hidden>
                <?= e($message['message']) ?>
            </div>
        <?php endforeach; ?>

        <?= $content ?>
    </div>
</main>
<?php if ($user): ?></div><?php endif; ?>

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
<script src="<?= e(url('assets/coreui/vendors/@coreui/coreui/js/coreui.bundle.min.js')) ?>"></script>
<script src="<?= e(url('assets/coreui/vendors/simplebar/js/simplebar.min.js')) ?>"></script>
<!-- Bootstrap se conserva para la API data-bs-* utilizada por las vistas existentes. -->
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
<?php if (!empty($useSelect2)): ?>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<?php endif; ?>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="https://cdn.jsdelivr.net/npm/tinymce@7/tinymce.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/@tinymce/tinymce-jquery@2/dist/tinymce-jquery.min.js"></script>
<script src="<?= e(url('assets/js/vendor/jquery.rut.local.js')) ?>"></script>
<script src="<?= e(url('assets/js/app.js')) ?>"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const sidebar = document.getElementById('sidebar');
    document.querySelectorAll('[data-sidebar-toggle], [data-sidebar-close]').forEach(function (button) {
        button.addEventListener('click', function () {
            if (sidebar && window.coreui && window.coreui.Sidebar) {
                window.coreui.Sidebar.getOrCreateInstance(sidebar).toggle();
            }
        });
    });
});
</script>
</body>
</html>
