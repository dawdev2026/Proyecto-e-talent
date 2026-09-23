<?php
declare(strict_types=1);

function database(string $connection = 'core'): Database
{
    static $databases = [];

    if (isset($databases[$connection]) && $databases[$connection] instanceof Database) {
        return $databases[$connection];
    }

    $databases[$connection] = new Database(database_config($connection));
    return $databases[$connection];
}

function db(string $connection = 'core'): PDO
{
    return database($connection)->pdo();
}

function database_config(string $connection = 'core'): array
{
    static $resolved = [];

    if (isset($resolved[$connection])) {
        return $resolved[$connection];
    }

    $config = load_config('database');
    $connections = $config['connections'] ?? [];
    $base = $config;
    unset($base['connections']);

    if (isset($connections[$connection]) && is_array($connections[$connection])) {
        $base = array_replace($base, $connections[$connection]);
    } elseif ($connection !== 'core') {
        throw new InvalidArgumentException('Conexion de base de datos no configurada: ' . $connection);
    }

    $base = database_apply_environment_overrides($base, $connection);

    if (empty($base['database'])) {
        throw new RuntimeException('La conexion de base de datos no define database: ' . $connection);
    }

    $resolved[$connection] = $base;
    return $resolved[$connection];
}

function database_env(string $key, ?string $default = null): ?string
{
    $value = getenv($key);
    if ($value === false) {
        return $default;
    }

    return $value;
}

function database_apply_environment_overrides(array $config, string $connection): array
{
    $connectionKey = strtoupper($connection);
    $database = database_env('DB_' . $connectionKey . '_DATABASE');

    $overrides = [
        'host' => database_env('DB_HOST'),
        'port' => database_env('DB_PORT'),
        'username' => database_env('DB_USERNAME'),
        'password' => database_env('DB_PASSWORD'),
        'charset' => database_env('DB_CHARSET'),
        'socket' => database_env('DB_SOCKET'),
        'persistent' => database_env('DB_PERSISTENT'),
        'query_profiling' => database_env('DB_QUERY_PROFILING'),
        'slow_query_ms' => database_env('DB_SLOW_QUERY_MS'),
        'query_sample_rate' => database_env('DB_QUERY_SAMPLE_RATE'),
        'query_profile_log' => database_env('DB_QUERY_PROFILE_LOG'),
        'database' => $database ?: database_env('DB_DATABASE'),
    ];

    foreach ($overrides as $key => $value) {
        if ($value !== null) {
            $config[$key] = $value;
        }
    }

    return $config;
}

function database_name(string $connection = 'core'): string
{
    return (string) database_config($connection)['database'];
}

function database_identifier(string $connection = 'core'): string
{
    $database = database_name($connection);
    if (!preg_match('/^[A-Za-z0-9_]+$/', $database)) {
        throw new RuntimeException('Nombre de base de datos no permitido: ' . $database);
    }

    return '`' . $database . '`';
}
