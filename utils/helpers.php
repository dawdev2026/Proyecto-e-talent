<?php
declare(strict_types=1);

function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function redirect(string $path): void
{
    if (preg_match('#^https?://#i', $path) || strpos($path, '//') === 0) {
        header('Location: ' . $path);
        exit;
    }

    $base = app_base_path();
    if ($base !== '' && ($path === $base || strpos($path, $base . '/') === 0)) {
        header('Location: ' . $path);
        exit;
    }

    header('Location: ' . app_url($path));
    exit;
}

function url(string $path = ''): string
{
    if (is_protected_asset_path($path)) {
        return protected_asset_url($path);
    }

    return app_url($path);
}

function app_url(string $path = ''): string
{
    $base = app_base_path();
    $path = ltrim($path, '/');

    if ($base !== '' && ($path === ltrim($base, '/') || strpos($path, ltrim($base, '/') . '/') === 0)) {
        return '/' . $path;
    }

    return ($base === '' ? '' : $base) . '/' . $path;
}

function app_base_path(): string
{
    $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';

    if ($scriptName !== '' && substr($scriptName, -4) === '.php') {
        $base = rtrim(str_replace('\\', '/', dirname($scriptName)), '/');

        return $base === '/' || $base === '.' ? '' : $base;
    }

    return '';
}

function route_url(string $route, ?int $id = null): string
{
    $routes = [
        'dashboard' => '',
        'login' => 'login',
        'logout' => 'logout',
        'my-tests' => 'my-tests',
        'tests' => 'tests',
        'tests.progress' => 'tests/progress',
        'tests.progress-ranking' => 'tests/progress/ranking',
        'tests.progress-export' => 'tests/progress/export',
        'tests.ranking-presets' => 'tests/ranking-presets',
        'tests.settings' => 'settings/tests',
        'tests.assign' => 'tests/assign',
        'test-process.dashboard' => 'tests/processes/dashboard',
        'test-process.dashboard-warnings' => 'tests/processes/dashboard-warnings',
        'test-process.ranking-all' => 'tests/processes/ranking',
        'test-process.ranking-all-reports-zip' => 'tests/processes/ranking/reports-zip',
        'test-process.review-assignment' => 'tests/processes/review-assignment',
        'test-processes' => 'tests/processes',
        'test-process.new' => 'tests/processes/new',
        'interviews' => 'interviews',
        'interview-process.new' => 'interviews/processes/new',
        'interviews.settings' => 'interviews/settings',
        'interviews.ai-test' => 'interviews/settings/ai-test',
        'interviews.process-job' => 'interviews/jobs/process',
        'tests.cancel-all' => 'tests/assignments/cancel',
        'tests.clear-results' => 'tests/results/clear',
        'test.new' => 'tests/new',
        'users' => 'users',
        'user.new' => 'users/new',
        'user.import' => 'users/import',
        'user.import-template' => 'users/import/template',
        'companies' => 'companies',
        'company.new' => 'companies/new',
        'profiles' => 'profiles',
        'profile.new' => 'profiles/new',
        'user-fields' => 'user-fields',
        'user-field.new' => 'user-fields/new',
        'settings' => 'settings',
        'password.forgot' => 'password/forgot',
        'password.reset' => 'password/reset',
    ];

    if (isset($routes[$route])) {
        return app_url($routes[$route]);
    }

    $secureRoutes = [
        'user.edit' => ['users/%s/edit', 'user'],
        'test.edit' => ['tests/%s/edit', 'test'],
        'test.content' => ['tests/%s/content', 'test'],
        'test.result-export' => ['tests/%s/result-export', 'test'],
        'test.answers-export' => ['tests/%s/answers-export', 'test'],
        'test-session.take' => ['tests/session/%s', 'test_session'],
        'test-session.result' => ['tests/session/%s/result', 'test_session'],
        'test-session.cancel' => ['tests/session/%s/cancel', 'test_session'],
        'test-session.activity' => ['tests/session/%s/activity', 'test_session'],
        'test-session.media-init' => ['tests/session/%s/media/init', 'test_session'],
        'test-session.media-status' => ['tests/session/%s/media/status', 'test_session'],
        'test-session.media-chunk' => ['tests/session/%s/media/chunk', 'test_session'],
        'test-session.media-finalize' => ['tests/session/%s/media/finalize', 'test_session'],
        'test-session.media-risk' => ['tests/session/%s/media/risk', 'test_session'],
        'test-session.media-evidence' => ['tests/session/%s/media/evidence', 'test_session'],
        'test-session.draft' => ['tests/session/%s/draft', 'test_session'],
        'test-session.availability' => ['tests/session/%s/availability', 'test_session'],
        'test-session.result-export' => ['tests/session/%s/result-export', 'test_session'],
        'test-user.results' => ['tests/results/user/%s', 'user'],
        'test-process.show' => ['tests/processes/%s', 'test_process'],
        'test-process.ranking' => ['tests/processes/%s/ranking', 'test_process'],
        'test-process.ranking-report' => ['tests/processes/%s/ranking-report', 'test_process'],
        'test-process.results-export' => ['tests/processes/%s/results-export', 'test_process'],
        'test-process.results-import' => ['tests/processes/%s/results-import', 'test_process'],
        'test-process.edit' => ['tests/processes/%s/edit', 'test_process'],
        'test-process.delete' => ['tests/processes/%s/delete', 'test_process'],
        'interview-process.show' => ['interviews/processes/%s', 'interview_process'],
        'interview-process.edit' => ['interviews/processes/%s/edit', 'interview_process'],
        'interview-appointment.room' => ['interviews/appointments/%s/room', 'interview_appointment'],
        'interview-appointment.schedule' => ['interviews/appointments/%s/schedule', 'interview_appointment'],
        'interview-appointment.notes' => ['interviews/appointments/%s/notes', 'interview_appointment'],
        'interview-appointment.transcription' => ['interviews/appointments/%s/transcription', 'interview_appointment'],
        'interview-appointment.finish' => ['interviews/appointments/%s/finish', 'interview_appointment'],
        'interview-appointment.report' => ['interviews/appointments/%s/report', 'interview_appointment'],
        'interview-document.download' => ['interviews/documents/%s', 'interview_document'],
        'company.edit' => ['companies/%s/edit', 'company'],
        'profile.edit' => ['profiles/%s/edit', 'profile'],
        'user-field.edit' => ['user-fields/%s/edit', 'user_field'],
        'user-field.delete' => ['user-fields/%s/delete', 'user_field'],
    ];

    if ($id && isset($secureRoutes[$route])) {
        [$pattern, $purpose] = $secureRoutes[$route];
        return app_url(sprintf($pattern, secure_url_token($id, $purpose)));
    }

    return app_url('');
}

function is_protected_asset_path(string $path): bool
{
    $cleanPath = ltrim((string) parse_url($path, PHP_URL_PATH), '/');
    if ($cleanPath === '') {
        return false;
    }

    if (!preg_match('/\.(css|js|png|jpe?g|webp|gif|ico|svg|mp4|webm|mov|woff2?|ttf|eot|pdf)$/i', $cleanPath)) {
        return false;
    }

    return strpos($cleanPath, 'assets/') === 0 || strpos($cleanPath, 'uploads/') === 0;
}

function protected_asset_token(string $path, ?string $day = null): string
{
    $cleanPath = ltrim((string) parse_url($path, PHP_URL_PATH), '/');
    $day = $day ?: date('Y-m-d');

    return base64url_encode(hash_hmac('sha256', $cleanPath . '|' . $day, master_key(), true));
}

function protected_asset_url(string $path): string
{
    $pathOnly = (string) (parse_url($path, PHP_URL_PATH) ?: $path);
    $query = (string) (parse_url($path, PHP_URL_QUERY) ?: '');
    $url = app_url($pathOnly) . ($query !== '' ? '?' . $query : '');
    $separator = strpos($url, '?') === false ? '?' : '&';
    $cleanPath = ltrim($pathOnly, '/');
    $file = PUBLIC_PATH . '/' . $cleanPath;
    $version = is_file($file)
        ? substr((string) hash_file('sha256', $file), 0, 16)
        : (string) time();

    return $url . $separator . '_asset=' . protected_asset_token($pathOnly) . '&_v=' . rawurlencode($version);
}

function active(string $page, string $current): string
{
    return $page === $current ? 'active' : '';
}

function navigation_normalized_path(string $url): string
{
    $path = parse_url($url, PHP_URL_PATH) ?: '/';
    $base = app_base_path();
    if ($base !== '' && ($path === $base || strpos($path, $base . '/') === 0)) {
        $path = substr($path, strlen($base)) ?: '/';
    }

    if ($base === '' && defined('BASE_PATH')) {
        $projectSlug = strtolower((string) preg_replace('/[^a-z0-9]+/i', '-', basename(BASE_PATH)));
        $projectSlug = trim($projectSlug, '-');
        $projectBase = $projectSlug !== '' ? '/' . $projectSlug : '';
        if ($projectBase !== '' && ($path === $projectBase || strpos($path, $projectBase . '/') === 0)) {
            $path = substr($path, strlen($projectBase)) ?: '/';
        }
    }

    return $path;
}

function navigation_request_is_async(): bool
{
    $requestedWith = strtolower((string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? ''));
    if ($requestedWith === 'xmlhttprequest') {
        return true;
    }

    $accept = strtolower((string) ($_SERVER['HTTP_ACCEPT'] ?? ''));

    return strpos($accept, 'application/json') !== false;
}

function navigation_url_is_page(string $url): bool
{
    $query = [];
    parse_str((string) (parse_url($url, PHP_URL_QUERY) ?: ''), $query);
    foreach (['partial', 'drawer', 'ajax', 'draw', 'start', 'length'] as $key) {
        if (array_key_exists($key, $query)) {
            return false;
        }
    }

    $path = navigation_normalized_path($url);
    if (in_array($path, ['/logout'], true) || preg_match('/\.(css|js|png|jpe?g|webp|ico|pdf|xlsx?)$/i', $path)) {
        return false;
    }

    $segments = array_values(array_filter(explode('/', trim($path, '/'))));
    $first = $segments[0] ?? '';
    $second = $segments[1] ?? '';
    $third = $segments[2] ?? '';
    $fourth = $segments[3] ?? '';

    if ($first === 'users') {
        if ($second === 'data') {
            return false;
        }

        if ($second === 'import' && in_array($third, ['preview-data', 'row', 'row-save', 'template'], true)) {
            return false;
        }
    }

    if ($first === 'tests') {
        if ($second === 'progress' && $third === 'export') {
            return false;
        }

        if ($second === 'session' && in_array($fourth, ['activity', 'cancel'], true)) {
            return false;
        }

        if ($second === 'processes' && count($segments) >= 4) {
            if (in_array($fourth, [
                'available-users',
                'assign-sessions',
                'remove-user',
                'edit-user',
                'remove-all-users',
                'cancel-session',
                'reset-session',
                'reopen-session',
            ], true)) {
                return false;
            }
        }

        if (count($segments) >= 4 && $third === 'content') {
            return false;
        }

        if ($second === 'results' && $third === 'clear') {
            return false;
        }
    }

    if ($first === 'interviews') {
        if ($second === 'jobs') {
            return false;
        }

        if ($second === 'settings' && $third === 'ai-test') {
            return false;
        }

        if ($second === 'appointments' && in_array($fourth, ['notes', 'transcription', 'finish', 'schedule'], true)) {
            return false;
        }
    }

    return true;
}

function remember_navigation(): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
        return;
    }

    if (($_GET['partial'] ?? '') !== '' || navigation_request_is_async()) {
        return;
    }

    $uri = $_SERVER['REQUEST_URI'] ?? '/';
    if (!navigation_url_is_page($uri)) {
        return;
    }

    $current = $_SESSION['nav_current'] ?? '';
    if ($current !== $uri) {
        if ($current !== '') {
            $_SESSION['nav_previous'] = $current;
        }
        $_SESSION['nav_current'] = $uri;
    }
}

function back_url(string $fallback = ''): string
{
    $previous = $_SESSION['nav_previous'] ?? '';
    $current = $_SERVER['REQUEST_URI'] ?? '';
    $fallbackUrl = $fallback !== '' ? route_url($fallback) : route_url('dashboard');

    if ($previous !== '' && $previous !== $current) {
        if (!navigation_url_is_page($previous) || !navigation_url_is_accessible($previous)) {
            return $fallbackUrl;
        }

        return app_url($previous);
    }

    return $fallbackUrl;
}

function navigation_url_is_accessible(string $url): bool
{
    if (!function_exists('current_user') || !current_user()) {
        return false;
    }

    $path = navigation_normalized_path($url);

    $segments = array_values(array_filter(explode('/', trim($path, '/'))));
    $first = $segments[0] ?? '';
    $second = $segments[1] ?? '';
    $third = $segments[2] ?? '';

    if ($segments === [] || $first === 'my-tests') {
        return true;
    }

    if ($first === 'login' || $first === 'logout') {
        return false;
    }

    $permission = null;
    switch ($first) {
        case 'users':
            $permission = 'manage_users';
            break;
        case 'companies':
            $permission = 'manage_companies';
            break;
        case 'profiles':
            $permission = 'manage_profiles';
            break;
        case 'user-fields':
            $permission = 'manage_user_fields';
            break;
        case 'settings':
            $permission = 'manage_platform_settings';
            break;
    }

    if ($permission !== null) {
        return has_permission($permission);
    }

    if ($first === 'tests') {
        if ($second === 'session') {
            return true;
        }

        if ($second === 'results' && $third === 'user') {
            return has_permission('view_test_results');
        }

        if ($second === 'assign' || $second === 'assignments') {
            return has_permission('assign_tests');
        }

        if ($second === 'settings') {
            return has_permission('manage_platform_settings');
        }

        if ($second === 'results' || $second === 'new' || $third === 'edit' || $third === 'content' || $second === '') {
            return has_permission('manage_tests');
        }

        return has_permission('manage_tests');
    }

    return false;
}

function flash(string $type, string $message): void
{
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

function flashes(): array
{
    $messages = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);

    return $messages;
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function verify_csrf(): void
{
    $token = $_POST['csrf_token'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
        platform_error(419, 'Token CSRF invalido.', [
            'heading' => 'La sesion del formulario expiro.',
            'message' => 'Vuelve a la pantalla anterior y envia nuevamente el formulario.',
            'chips' => ['Formulario', 'Sesion'],
        ]);
    }
}

function platform_error(int $statusCode, string $rawMessage, array $options = []): void
{
    $statusCode = $statusCode > 0 ? $statusCode : 500;
    http_response_code($statusCode);

    $defaults = platform_error_defaults($statusCode, $rawMessage);
    $data = array_merge($defaults, $options);
    $data['statusCode'] = $statusCode;
    $data['requestedPath'] = $data['requestedPath'] ?? (parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/');
    $data['occurredAt'] = $data['occurredAt'] ?? date('d/m/Y H:i');
    $data['supportReference'] = $data['supportReference'] ?? '';

    if ($statusCode >= 500 || !empty($options['log'])) {
        $data['supportReference'] = $data['supportReference'] ?: platform_error_reference($statusCode);
        security_log(sprintf(
            '%s %s :: %s :: %s',
            $_SERVER['REQUEST_METHOD'] ?? 'GET',
            $data['requestedPath'],
            $data['supportReference'],
            $rawMessage
        ));
    }

    $data['actions'] = $data['actions'] ?? platform_error_actions($statusCode);
    $data['title'] = ($data['pageHeading'] ?? $data['heading']) . ' | Metricatest';
    $data['currentPage'] = 'error';

    try {
        echo template()->render('errors/platform', $data, 'app');
    } catch (Throwable $exception) {
        error_log('Platform error render failed: ' . $exception->getMessage());
        header('Content-Type: text/plain; charset=utf-8');
        echo $rawMessage;
    }

    exit;
}

function platform_error_defaults(int $statusCode, string $rawMessage): array
{
    $defaults = [
        'eyebrow' => 'Incidente de plataforma',
        'pageHeading' => 'Error interno',
        'pageLead' => 'Una respuesta para errores de plataforma con informacion accionable sin exponer datos tecnicos sensibles.',
        'heading' => $rawMessage,
        'message' => 'La plataforma no pudo completar la solicitud. El incidente fue registrado para revision tecnica.',
        'chips' => ['Soporte tecnico'],
    ];

    $map = [
        403 => [
            'eyebrow' => 'Control de acceso',
            'pageHeading' => 'Acceso restringido',
            'pageLead' => 'El recurso solicitado requiere permisos adicionales dentro de la plataforma.',
            'heading' => $rawMessage,
            'message' => 'Tu perfil no tiene permisos para abrir este recurso. Si crees que corresponde, solicita una revision al administrador.',
            'chips' => ['Permisos', 'Acceso restringido'],
        ],
        404 => [
            'eyebrow' => 'Ruta no disponible',
            'pageHeading' => 'Pagina no encontrada',
            'pageLead' => 'La ruta solicitada no existe o ya no esta disponible.',
            'heading' => $rawMessage,
            'message' => 'Revisa la direccion o vuelve al inicio para continuar operando en la plataforma.',
            'chips' => ['Ruta', 'No encontrado'],
        ],
        405 => [
            'eyebrow' => 'Metodo no permitido',
            'pageHeading' => 'Solicitud no permitida',
            'pageLead' => 'La accion solicitada debe ejecutarse desde el flujo correspondiente.',
            'heading' => $rawMessage,
            'message' => 'Vuelve a la pantalla anterior e intenta nuevamente desde los controles de la plataforma.',
            'chips' => ['Metodo', 'Flujo invalido'],
        ],
        410 => [
            'eyebrow' => 'Enlace seguro',
            'pageHeading' => 'Enlace no disponible',
            'pageLead' => 'Para errores recuperables como enlace vencido, pagina inexistente o formulario expirado.',
            'heading' => $rawMessage,
            'message' => 'Por seguridad, algunos enlaces solo funcionan durante el dia en que fueron generados.',
            'chips' => ['Enlace seguro', 'Requiere nuevo acceso'],
        ],
        419 => [
            'eyebrow' => 'Sesion expirada',
            'pageHeading' => 'Formulario expirado',
            'pageLead' => 'La validacion de seguridad del formulario no pudo completarse.',
            'heading' => $rawMessage,
            'message' => 'Vuelve a la pantalla anterior y envia nuevamente el formulario.',
            'chips' => ['CSRF', 'Formulario'],
        ],
        500 => $defaults,
    ];

    return $map[$statusCode] ?? $defaults;
}

function platform_error_actions(int $statusCode): array
{
    $actions = [];
    $user = platform_error_current_user();

    if (function_exists('back_url') && $user) {
        $actions[] = ['label' => 'Volver', 'url' => back_url(), 'icon' => 'bi-arrow-left'];
    }

    if (function_exists('route_url')) {
        $actions[] = [
            'label' => $user ? 'Ir al inicio' : 'Iniciar sesion',
            'url' => $user ? route_url('dashboard') : route_url('login'),
            'icon' => $user ? 'bi-house-door' : 'bi-box-arrow-in-right',
            'primary' => true,
        ];
    }

    if ($statusCode >= 500) {
        array_unshift($actions, [
            'label' => 'Reintentar',
            'url' => $_SERVER['REQUEST_URI'] ?? route_url('dashboard'),
            'icon' => 'bi-arrow-clockwise',
            'primary' => true,
        ]);
    }

    return $actions;
}

function platform_error_current_user(): ?array
{
    if (!function_exists('current_user')) {
        return null;
    }

    try {
        return current_user();
    } catch (Throwable $exception) {
        error_log('Platform error current_user failed: ' . $exception->getMessage());
        return null;
    }
}

function platform_error_reference(int $statusCode): string
{
    return sprintf('LOG-%s-%d', date('Ymd-His'), $statusCode);
}

function badge_class(string $value): string
{
    $map = [
        'abierto' => 'text-bg-primary',
        'en_proceso' => 'text-bg-warning',
        'resuelto' => 'text-bg-success',
        'cerrado' => 'text-bg-secondary',
        'baja' => 'text-bg-info',
        'media' => 'text-bg-primary',
        'alta' => 'text-bg-warning',
        'critica' => 'text-bg-danger',
    ];

    return $map[$value] ?? 'text-bg-light';
}

function labelize(string $value): string
{
    return ucfirst(str_replace('_', ' ', $value));
}

function template(): Template
{
    static $template = null;

    if ($template instanceof Template) {
        return $template;
    }

    $template = new Template(
        [
            PLATAFORMAS_PATH . '/core/views',
            PLATAFORMAS_PATH . '/tests/views',
            PLATAFORMAS_PATH . '/interviews/views',
        ],
        SISTEMA_PATH . '/layouts',
        [
            'appConfig' => load_config('app'),
        ]
    );

    return $template;
}
