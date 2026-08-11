<?php
declare(strict_types=1);

function current_user(): ?array
{
    if (empty($_SESSION['user_id'])) {
        return null;
    }

    static $user = null;
    if ($user !== null) {
        return $user;
    }

    $lastLoginSelect = auth_column_exists('users', 'last_login_at') ? 'u.last_login_at' : 'NULL AS last_login_at';
    $profileHomeSelect = auth_column_exists('role_profiles', 'home_route') ? 'p.home_route AS profile_home_route' : "'dashboard' AS profile_home_route";

    $stmt = db()->prepare('
        SELECT u.id, u.name, u.email, u.role, u.profile_id, u.company_id, u.is_active, ' . $lastLoginSelect . ',
               c.name AS company_name,
               p.name AS profile_name,
               p.role_key AS profile_key,
               p.permissions AS profile_permissions,
               ' . $profileHomeSelect . '
        FROM users u
        LEFT JOIN companies c ON c.id = u.company_id
        LEFT JOIN role_profiles p ON p.id = u.profile_id
        WHERE u.id = ?
        LIMIT 1
    ');
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch() ?: null;

    if (!$user || (int) $user['is_active'] !== 1) {
        logout();
        return null;
    }

    return $user;
}

function auth_column_exists(string $table, string $column): bool
{
    static $cache = [];
    $key = $table . '.' . $column;
    if (array_key_exists($key, $cache)) {
        return $cache[$key];
    }

    try {
        $stmt = db()->prepare('
            SELECT COUNT(*) AS total
            FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = ?
              AND COLUMN_NAME = ?
        ');
        $stmt->execute([$table, $column]);
        $row = $stmt->fetch() ?: [];
        $cache[$key] = (int) ($row['total'] ?? 0) > 0;
    } catch (Throwable $exception) {
        error_log('Auth schema check error: ' . $exception->getMessage());
        $cache[$key] = false;
    }

    return $cache[$key];
}

function profile_home_route(?array $user = null): string
{
    $user = $user ?: current_user();
    if (!$user) {
        return 'dashboard';
    }

    $route = (string) ($user['profile_home_route'] ?? 'dashboard');
    if ((string) ($user['profile_key'] ?? '') === 'usuario' && $route === 'dashboard') {
        $route = 'my-tests';
    }
    $permissions = json_decode($user['profile_permissions'] ?? '[]', true);
    $permissions = is_array($permissions) ? $permissions : [];

    return ProfileModel::homeRouteIsAllowed($route, $permissions) ? $route : 'dashboard';
}

function profile_home_url(?array $user = null): string
{
    return route_url(profile_home_route($user));
}

function current_permissions(): array
{
    $user = current_user();
    if (!$user) {
        return [];
    }

    $permissions = json_decode($user['profile_permissions'] ?? '[]', true);
    if (is_array($permissions) && $permissions) {
        return $permissions;
    }

    $fallback = [
        'admin' => ['manage_profiles', 'manage_platform_settings', 'manage_user_fields', 'manage_users', 'manage_companies'],
        'agente' => ['manage_users', 'manage_companies'],
        'company_admin' => ['manage_company_users', 'manage_company_processes', 'manage_company_interviews', 'view_company_results'],
        'usuario' => [],
    ];

    return $fallback[$user['role']] ?? [];
}

function has_permission(string $permission): bool
{
    return in_array($permission, current_permissions(), true);
}

function has_result_access(): bool
{
    return has_permission('view_test_results') || has_permission('view_company_results');
}

function require_result_access(): void
{
    require_auth();
    if (!has_result_access()) {
        platform_error(403, 'No tienes permisos para consultar resultados.', [
            'detailRows' => ['Permiso requerido' => 'view_test_results o view_company_results'],
        ]);
    }
}

function require_permission(string $permission): void
{
    require_auth();
    if (!has_permission($permission)) {
        platform_error(403, 'No tienes permisos para acceder a esta seccion.', [
            'detailRows' => [
                'Permiso requerido' => $permission,
            ],
        ]);
    }
}

function require_company_user_management(): void
{
    require_auth();
    if (!has_permission('manage_users') && !has_permission('manage_company_users')) {
        platform_error(403, 'No tienes permisos para gestionar usuarios.', [
            'detailRows' => ['Permiso requerido' => 'manage_users o manage_company_users'],
        ]);
    }
}

function require_company_interview_management(): void
{
    require_auth();
    if (!has_permission('manage_interview_processes') && !has_permission('manage_company_interviews')) {
        platform_error(403, 'No tienes permisos para administrar entrevistas.', [
            'detailRows' => ['Permiso requerido' => 'manage_interview_processes o manage_company_interviews'],
        ]);
    }
}

function login(string $identifier, string $password, string $identifierType = 'email'): bool
{
    $identifierType = $identifierType === 'rut' ? 'rut' : 'email';
    $identifier = trim($identifier);

    if ($identifierType === 'rut') {
        $identifier = UserModel::formatRut($identifier);
        if (!UserModel::isValidRut($identifier)) {
            return false;
        }
        $stmt = db()->prepare('SELECT * FROM users WHERE rut = ? AND is_active = 1 LIMIT 1');
    } else {
        $identifier = mb_strtolower($identifier);
        if (!filter_var($identifier, FILTER_VALIDATE_EMAIL)) {
            return false;
        }
        $stmt = db()->prepare('SELECT * FROM users WHERE email = ? AND is_active = 1 LIMIT 1');
    }

    $stmt->execute([$identifier]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($password, $user['password_hash'])) {
        return false;
    }

    session_regenerate_id(true);
    $_SESSION['user_id'] = (int) $user['id'];
    $_SESSION['last_login_at'] = $user['last_login_at'] ?? null;

    try {
        db()->prepare('UPDATE users SET last_login_at = NOW() WHERE id = ?')->execute([(int) $user['id']]);
        db()->prepare('
            INSERT INTO user_login_events (user_id, logged_at, ip_address, user_agent)
            VALUES (?, NOW(), ?, ?)
        ')->execute([
            (int) $user['id'],
            substr((string) ($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45) ?: null,
            substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255) ?: null,
        ]);
    } catch (Throwable $exception) {
        // Older local databases may not have the login audit objects until migrations are applied.
    }

    return true;
}

function logout(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }
    session_destroy();
}

function require_auth(): void
{
    if (!current_user()) {
        redirect(route_url('login'));
    }
}

function require_role(array $roles): void
{
    require_auth();
    if (!in_array(current_user()['role'], $roles, true)) {
        platform_error(403, 'No tienes permisos para acceder a esta seccion.', [
            'detailRows' => [
                'Roles permitidos' => implode(', ', $roles),
            ],
        ]);
    }
}

function can_manage_users(): bool
{
    return has_permission('manage_users');
}

function is_requester(): bool
{
    $user = current_user();
    return $user && $user['role'] === 'usuario';
}

function login_attempt_key(string $identifier): string
{
    return hash('sha256', strtolower(trim($identifier)) . '|' . ($_SERVER['REMOTE_ADDR'] ?? 'local'));
}

function login_rate_limited(string $identifier): bool
{
    $key = login_attempt_key($identifier);
    $attempt = $_SESSION['login_attempts'][$key] ?? ['count' => 0, 'last' => 0];

    if ((time() - (int) $attempt['last']) > 300) {
        return false;
    }

    return (int) $attempt['count'] >= 5;
}

function register_login_attempt(string $identifier): void
{
    $key = login_attempt_key($identifier);
    $attempt = $_SESSION['login_attempts'][$key] ?? ['count' => 0, 'last' => 0];

    if ((time() - (int) $attempt['last']) > 300) {
        $attempt = ['count' => 0, 'last' => time()];
    }

    $attempt['count']++;
    $attempt['last'] = time();
    $_SESSION['login_attempts'][$key] = $attempt;
}

function clear_login_attempts(string $identifier): void
{
    unset($_SESSION['login_attempts'][login_attempt_key($identifier)]);
}
