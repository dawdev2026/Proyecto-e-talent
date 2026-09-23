<?php
declare(strict_types=1);

function configure_secure_session(): void
{
    $secure = request_is_secure();

    ini_set('session.use_strict_mode', '1');
    ini_set('session.cookie_httponly', '1');
    ini_set('session.cookie_samesite', 'Lax');
    ini_set('session.gc_maxlifetime', '7200');

    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'domain' => '',
        'secure' => $secure,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}

function request_is_secure(): bool
{
    if (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off') {
        return true;
    }

    if ((string) ($_SERVER['SERVER_PORT'] ?? '') === '443') {
        return true;
    }

    $forwardedProto = strtolower(trim(explode(',', (string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''))[0]));
    if ($forwardedProto === 'https') {
        return true;
    }

    return strtolower((string) ($_SERVER['HTTP_X_FORWARDED_SSL'] ?? '')) === 'on';
}

function request_host_is_local(): bool
{
    $host = strtolower((string) ($_SERVER['HTTP_HOST'] ?? ''));
    $host = preg_replace('/:\d+$/', '', $host) ?: $host;
    $host = trim($host, '[]');

    return in_array($host, ['localhost', '127.0.0.1', '::1'], true);
}

function request_indicates_https_available(): bool
{
    $forwardedPort = trim(explode(',', (string) ($_SERVER['HTTP_X_FORWARDED_PORT'] ?? ''))[0]);
    $forwardedProto = strtolower(trim(explode(',', (string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''))[0]));

    return $forwardedProto === 'https'
        || $forwardedPort === '443'
        || strtolower((string) ($_SERVER['HTTP_X_FORWARDED_SSL'] ?? '')) === 'on';
}

function should_force_https(array $config): bool
{
    $mode = $config['force_https'] ?? false;

    if ($mode === true || $mode === 1 || $mode === '1') {
        return true;
    }

    if (is_string($mode) && strtolower($mode) === 'auto') {
        return !request_host_is_local() && request_indicates_https_available();
    }

    return false;
}

function current_https_url(): string
{
    $host = trim(str_replace(["\r", "\n"], '', (string) ($_SERVER['HTTP_HOST'] ?? '')));
    if ($host === '') {
        $host = trim(str_replace(["\r", "\n"], '', (string) ($_SERVER['SERVER_NAME'] ?? '')));
    }

    $requestUri = (string) ($_SERVER['REQUEST_URI'] ?? '/');
    $requestUri = str_replace(["\r", "\n"], '', $requestUri);

    if ($host === '') {
        return 'https://' . ($requestUri === '' ? '/' : $requestUri);
    }

    if (substr($host, -3) === ':80') {
        $host = substr($host, 0, -3);
    }

    return 'https://' . $host . ($requestUri === '' ? '/' : $requestUri);
}

function enforce_https_policy(array $config): void
{
    if (headers_sent() || request_is_secure() || !should_force_https($config)) {
        return;
    }

    header('Location: ' . current_https_url(), true, 308);
    exit;
}

function facial_script_eval_allowed_for_path(string $requestPath): bool
{
    return preg_match('#(?:^|/)reconocimiento-facial/(?:enrolar|enrolados|validar-identidad|ingreso-evaluacion)/?$#', $requestPath) === 1
        || preg_match('#(?:^|/)tests/session/[^/]+/?$#', $requestPath) === 1
        || preg_match('#(?:^|/)evaluaciones-encuestas/formularios/[^/]+/take/?$#', $requestPath) === 1;
}

function send_security_headers(): void
{
    if (headers_sent()) {
        return;
    }

    header('X-Frame-Options: SAMEORIGIN');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: camera=*, microphone=*, geolocation=()');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    header('Expires: Thu, 01 Jan 1970 00:00:00 GMT');
    $requestPath = parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?: '/';
    // FaceX/OpenCV necesitan evaluar código generado durante la inicialización.
    // Limita la excepción a las pantallas faciales y a las páginas exactas de
    // entrada a pruebas/evaluaciones que cargan esas bibliotecas; nunca la
    // habilites para el resto de rutas ni para endpoints de sesión/media.
    $facialScriptPolicy = facial_script_eval_allowed_for_path($requestPath) ? " 'unsafe-eval'" : '';
    header("Content-Security-Policy: default-src 'self'; connect-src 'self' data: https://api.daily.co https://*.daily.co wss://*.daily.co https://cdn.jsdelivr.net https://github.com https://release-assets.githubusercontent.com https://objects.githubusercontent.com; frame-src 'self' https://*.daily.co; img-src 'self' data: blob: https:; style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://fonts.googleapis.com; script-src 'self' 'unsafe-inline' 'wasm-unsafe-eval'{$facialScriptPolicy} https://code.jquery.com https://cdn.jsdelivr.net; font-src 'self' https://cdn.jsdelivr.net https://fonts.gstatic.com data:; object-src 'none'; base-uri 'self'; frame-ancestors 'self'; form-action 'self'");

    if (request_is_secure()) {
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    }
}

function master_key_path(): string
{
    return CONFIG_PATH . '/master.key';
}

function master_key(): string
{
    $path = master_key_path();

    if (!is_file($path)) {
        file_put_contents($path, base64_encode(random_bytes(32)) . PHP_EOL, LOCK_EX);
        @chmod($path, 0600);
    }

    $key = base64_decode(trim((string) file_get_contents($path)), true);
    if ($key === false || strlen($key) !== 32) {
        throw new RuntimeException('La llave maestra de seguridad no es valida.');
    }

    return $key;
}

function base64url_encode(string $value): string
{
    return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
}

function base64url_decode(string $value): string
{
    $decoded = base64_decode(strtr($value, '-_', '+/') . str_repeat('=', (4 - strlen($value) % 4) % 4), true);
    if ($decoded === false) {
        throw new RuntimeException('Token invalido.');
    }

    return $decoded;
}

function encrypt_payload(array $payload, string $aad = ''): string
{
    $iv = random_bytes(12);
    $tag = '';
    $json = json_encode($payload, JSON_UNESCAPED_UNICODE);
    if ($json === false) {
        throw new RuntimeException('No se pudo preparar el payload seguro.');
    }

    $ciphertext = openssl_encrypt($json, 'aes-256-gcm', master_key(), OPENSSL_RAW_DATA, $iv, $tag, $aad);
    if ($ciphertext === false) {
        throw new RuntimeException('No se pudo cifrar la informacion.');
    }

    return base64url_encode(json_encode([
        'v' => 1,
        'iv' => base64_encode($iv),
        'tag' => base64_encode($tag),
        'data' => base64_encode($ciphertext),
    ]));
}

function decrypt_payload(string $token, string $aad = ''): array
{
    $envelope = json_decode(base64url_decode($token), true);
    if (!is_array($envelope) || empty($envelope['iv']) || empty($envelope['tag']) || empty($envelope['data'])) {
        throw new RuntimeException('Token seguro invalido.');
    }

    $plain = openssl_decrypt(
        base64_decode($envelope['data'], true),
        'aes-256-gcm',
        master_key(),
        OPENSSL_RAW_DATA,
        base64_decode($envelope['iv'], true),
        base64_decode($envelope['tag'], true),
        $aad
    );

    if ($plain === false) {
        throw new RuntimeException('No se pudo descifrar la informacion.');
    }

    $payload = json_decode($plain, true);
    if (!is_array($payload)) {
        throw new RuntimeException('Payload seguro invalido.');
    }

    return $payload;
}

function secure_url_token(int $id, string $purpose): string
{
    return encrypt_payload([
        'id' => $id,
        'purpose' => $purpose,
        'day' => date('Y-m-d'),
    ], 'url:' . $purpose . ':' . date('Y-m-d'));
}

function secure_url_id(string $token, string $purpose): int
{
    $payload = decrypt_payload($token, 'url:' . $purpose . ':' . date('Y-m-d'));

    if (($payload['purpose'] ?? '') !== $purpose || ($payload['day'] ?? '') !== date('Y-m-d')) {
        throw new RuntimeException('El enlace seguro expiro.');
    }

    return (int) ($payload['id'] ?? 0);
}

function request_secure_id(string $purpose): int
{
    if (!empty($_GET['sid'])) {
        try {
            return secure_url_id((string) $_GET['sid'], $purpose);
        } catch (RuntimeException $exception) {
            platform_error(410, 'El enlace seguro expiro o no es valido.');
        }
    }

    return (int) ($_GET['id'] ?? 0);
}

function secure_config_path(string $name): string
{
    return CONFIG_PATH . '/' . $name . '.secure';
}

function load_config(string $name): array
{
    $securePath = secure_config_path($name);
    if (is_file($securePath)) {
        $payload = decrypt_payload(trim((string) file_get_contents($securePath)), 'config:' . $name);
        return $payload['config'] ?? [];
    }

    $plainPath = CONFIG_PATH . '/' . $name . '.php';
    if (!is_file($plainPath)) {
        return [];
    }

    return require $plainPath;
}

function write_secure_config(string $name, array $config): void
{
    file_put_contents(secure_config_path($name), encrypt_payload(['config' => $config], 'config:' . $name) . PHP_EOL, LOCK_EX);
    @chmod(secure_config_path($name), 0600);
}

function security_log(string $message): void
{
    $line = sprintf("[%s] %s\n", date('Y-m-d H:i:s'), $message);
    file_put_contents(TMP_PATH . '/security.log', $line, FILE_APPEND | LOCK_EX);
}
