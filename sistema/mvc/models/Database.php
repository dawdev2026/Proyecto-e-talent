<?php
declare(strict_types=1);

final class Database
{
    private PDO $pdo;
    /** @var array<string, PDOStatement> */
    private array $statements = [];
    private int $transactionDepth = 0;
    private bool $queryProfilingEnabled;
    private float $slowQueryThresholdMs;
    private float $querySampleRate;
    private string $queryProfileLogPath;

    public function __construct(array $config)
    {
        $dsn = !empty($config['socket'])
            ? sprintf(
                'mysql:unix_socket=%s;dbname=%s;charset=%s',
                $config['socket'],
                $config['database'],
                $config['charset']
            )
            : sprintf(
                'mysql:host=%s;port=%s;dbname=%s;charset=%s',
                $config['host'],
                $config['port'],
                $config['database'],
                $config['charset']
            );

        $persistent = filter_var($config['persistent'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $this->queryProfilingEnabled = filter_var($config['query_profiling'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $this->slowQueryThresholdMs = max(0.0, (float) ($config['slow_query_ms'] ?? 300));
        $this->querySampleRate = max(0.0, min(1.0, (float) ($config['query_sample_rate'] ?? 0)));
        $this->queryProfileLogPath = trim((string) ($config['query_profile_log'] ?? ''));
        if ($this->queryProfilingEnabled && $this->queryProfileLogPath === '') {
            $this->queryProfileLogPath = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'e_talent-slow-queries.log';
        }
        $this->pdo = new PDO($dsn, $config['username'], $config['password'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            // La reutilización entre peticiones es opt-in. La conexión se
            // reutiliza siempre durante la petición mediante database(), y
            // solo se permite persistencia del driver cuando el ambiente la
            // habilita explícitamente y ha sido validada bajo carga.
            PDO::ATTR_PERSISTENT => $persistent,
        ]);

        // Las fechas de negocio se ingresan y se muestran en la zona horaria
        // configurada por la aplicación. Alinear la sesión MySQL evita que
        // NOW(), DATE(), CURDATE() y DATE_ADD() comparen contra UTC mientras
        // los procesos están almacenados como hora local de Chile.
        $localTimezone = new DateTimeZone(date_default_timezone_get());
        $utcOffset = (new DateTimeImmutable('now', $localTimezone))->format('P');
        $this->pdo->prepare('SET time_zone = ?')->execute([$utcOffset]);
    }

    public function pdo(): PDO
    {
        return $this->pdo;
    }

    public function query(string $sql, array $params = []): PDOStatement
    {
        $startedAt = $this->queryProfilingEnabled ? hrtime(true) : 0;
        $stmt = $this->statements[$sql] ??= $this->pdo->prepare($sql);
        $stmt->closeCursor();
        try {
            $stmt->execute($params);
        } finally {
            if ($this->queryProfilingEnabled) {
                $this->profileQuery($sql, $stmt, $startedAt);
            }
        }

        return $stmt;
    }

    private function profileQuery(string $sql, PDOStatement $statement, int $startedAt): void
    {
        if ($startedAt <= 0 || $this->queryProfileLogPath === '') {
            return;
        }

        $durationMs = (hrtime(true) - $startedAt) / 1000000;
        $sampled = $this->querySampleRate > 0
            && (mt_rand() / mt_getrandmax()) <= $this->querySampleRate;
        if ($durationMs < $this->slowQueryThresholdMs && !$sampled) {
            return;
        }

        $normalizedSql = preg_replace('/\s+/', ' ', trim($sql)) ?: trim($sql);
        $normalizedSql = preg_replace("/'(?:''|[^'])*'/", "'?'", $normalizedSql) ?: $normalizedSql;
        $record = [
            'at' => date('c'),
            'duration_ms' => round($durationMs, 3),
            'slow' => $durationMs >= $this->slowQueryThresholdMs,
            'rows' => $statement->rowCount(),
            'sql_hash' => hash('sha256', $normalizedSql),
            'sql' => $normalizedSql,
        ];

        // El perfilador nunca debe interrumpir la consulta ni revelar los
        // parámetros; un fallo de escritura se ignora deliberadamente.
        @file_put_contents(
            $this->queryProfileLogPath,
            json_encode($record, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL,
            FILE_APPEND | LOCK_EX
        );
    }

    public function fetch(string $sql, array $params = []): ?array
    {
        $row = $this->query($sql, $params)->fetch();
        return $row ?: null;
    }

    public function fetchAll(string $sql, array $params = []): array
    {
        return $this->query($sql, $params)->fetchAll();
    }

    public function insert(string $sql, array $params = []): int
    {
        $this->query($sql, $params);
        return (int) $this->pdo->lastInsertId();
    }

    public function execute(string $sql, array $params = []): int
    {
        return $this->query($sql, $params)->rowCount();
    }

    public function transaction(callable $callback)
    {
        $isRootTransaction = $this->transactionDepth === 0;
        $savepoint = 'e_talent_sp_' . $this->transactionDepth;

        if ($isRootTransaction) {
            $this->pdo->beginTransaction();
        } else {
            $this->pdo->exec('SAVEPOINT ' . $savepoint);
        }

        $this->transactionDepth++;

        try {
            $result = $callback($this);
            $this->transactionDepth--;

            if ($isRootTransaction) {
                $this->pdo->commit();
            } else {
                $this->pdo->exec('RELEASE SAVEPOINT ' . $savepoint);
            }

            return $result;
        } catch (Throwable $exception) {
            $this->transactionDepth = max(0, $this->transactionDepth - 1);

            if ($this->pdo->inTransaction()) {
                if ($isRootTransaction) {
                    $this->pdo->rollBack();
                } else {
                    $this->pdo->exec('ROLLBACK TO SAVEPOINT ' . $savepoint);
                    $this->pdo->exec('RELEASE SAVEPOINT ' . $savepoint);
                }
            }

            throw $exception;
        }
    }
}
