<?php
declare(strict_types=1);

/**
 * Local MySQL migration runner. DDL is applied statement-by-statement because
 * MySQL 5.7 implicitly commits DDL; the ledger records partial failures so a
 * subsequent run can safely resume supported CREATE/ADD/MODIFY statements.
 */

const MIGRATION_LEDGER = '`e_talent_core`.`schema_migrations`';
const MIGRATION_LOCK = 'e_talent_schema_migrations';

function migrationFail(string $message, int $exitCode = 1): void
{
    fwrite(STDERR, $message . PHP_EOL);
    exit($exitCode);
}

function migrationPdo(): PDO
{
    $required = [
        'APP_ENV' => 'local',
        'DB_HOST' => 'db',
        'DB_CORE_DATABASE' => 'e_talent_core',
        'DB_TESTS_DATABASE' => 'e_talent_tests',
        'DB_EVALUACIONES_ENCUESTAS_DATABASE' => 'e_talent_evaluaciones_encuestas',
    ];
    foreach ($required as $key => $expected) {
        if (getenv($key) !== $expected) {
            migrationFail("Entorno no autorizado para migrar localmente: {$key} debe ser {$expected}.");
        }
    }

    $host = (string) getenv('DB_HOST');
    $port = (string) (getenv('DB_PORT') ?: '3306');
    $username = (string) getenv('DB_USERNAME');
    $password = (string) getenv('DB_PASSWORD');
    if ($username === '' || $password === '') {
        migrationFail('Faltan credenciales DB_USERNAME/DB_PASSWORD del entorno local.');
    }

    return new PDO(
        "mysql:host={$host};port={$port};charset=utf8mb4",
        $username,
        $password,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );
}

function migrationFiles(): array
{
    $files = glob(dirname(__DIR__) . '/database/migrations/*.sql') ?: [];
    sort($files, SORT_STRING);
    return $files;
}

function ensureLedger(PDO $pdo): void
{
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS ' . MIGRATION_LEDGER . ' (' .
        '`migration` varchar(190) NOT NULL,' .
        '`checksum` char(64) NOT NULL,' .
        '`status` enum(\'running\',\'applied\',\'failed\') NOT NULL,' .
        '`application_method` enum(\'executed\',\'verified_existing\') DEFAULT NULL,' .
        '`started_at` datetime NOT NULL,' .
        '`applied_at` datetime DEFAULT NULL,' .
        '`error_message` varchar(1000) DEFAULT NULL,' .
        'PRIMARY KEY (`migration`),' .
        'KEY `idx_schema_migrations_status` (`status`,`migration`)' .
        ') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );
}

function migrationLedgerExists(PDO $pdo): bool
{
    $stmt = $pdo->query(
        "SELECT 1 FROM information_schema.tables " .
        "WHERE table_schema = 'e_talent_core' AND table_name = 'schema_migrations'"
    );
    return (bool) $stmt->fetchColumn();
}

function migrationRows(PDO $pdo): array
{
    if (!migrationLedgerExists($pdo)) {
        return [];
    }
    $stmt = $pdo->query('SELECT migration, checksum, status, application_method, applied_at, error_message FROM ' . MIGRATION_LEDGER);
    $rows = [];
    foreach ($stmt->fetchAll() as $row) {
        $rows[$row['migration']] = $row;
    }
    return $rows;
}

function migrationStatements(string $source): array
{
    $statements = [];
    $buffer = '';
    foreach (preg_split('/\R/', $source) ?: [] as $line) {
        if (preg_match('/^\s*--/', $line)) {
            continue;
        }
        $buffer .= $line . "\n";
        while (($semicolon = strpos($buffer, ';')) !== false) {
            $statement = trim(substr($buffer, 0, $semicolon));
            $buffer = substr($buffer, $semicolon + 1);
            if ($statement !== '') {
                $statements[] = $statement;
            }
        }
    }
    if (trim($buffer) !== '') {
        $statements[] = trim($buffer);
    }
    return $statements;
}

function quoteIdentifier(string $identifier): string
{
    if (!preg_match('/^[A-Za-z0-9_]+$/', $identifier)) {
        throw new RuntimeException('Identificador SQL no permitido en migración.');
    }
    return '`' . $identifier . '`';
}

function currentColumn(PDO $pdo, string $table, string $column): ?array
{
    $stmt = $pdo->prepare(
        'SELECT COLUMN_TYPE, IS_NULLABLE, COLUMN_DEFAULT, COLLATION_NAME, EXTRA ' .
        'FROM information_schema.columns ' .
        'WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?'
    );
    $stmt->execute([$table, $column]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function normalizeColumnType(string $type): string
{
    $type = strtolower(preg_replace('/\s+/', ' ', trim($type)) ?: '');
    $type = preg_replace('/\s*,\s*/', ',', $type) ?: $type;
    return preg_replace('/\b(tinyint|smallint|mediumint|int|bigint)\(\d+\)/', '$1', $type) ?: $type;
}

function columnDefinitionMatches(PDO $pdo, string $table, string $column, string $definition): bool
{
    $current = currentColumn($pdo, $table, $column);
    if ($current === null) {
        return false;
    }

    if (!preg_match('/^\s*' . preg_quote(quoteIdentifier($column), '/') . '\s+(.+)$/is', $definition, $matches)) {
        throw new RuntimeException('No se pudo interpretar ADD COLUMN ' . $column . '.');
    }
    $desired = trim($matches[1]);
    $desiredType = preg_split('/\s+(?:NOT\s+NULL|NULL|DEFAULT|COLLATE|CHARACTER\s+SET|COMMENT|AFTER|FIRST)\b/i', $desired, 2)[0];
    $desiredType = normalizeColumnType($desiredType);
    $actualType = normalizeColumnType((string) $current['COLUMN_TYPE']);
    if ($desiredType === '' || $desiredType !== $actualType) {
        return false;
    }
    if (preg_match('/\bNOT\s+NULL\b/i', $definition) && $current['IS_NULLABLE'] !== 'NO') {
        return false;
    }
    if (preg_match('/(?<!NOT\s)\bNULL\b/i', $definition) && $current['IS_NULLABLE'] !== 'YES') {
        return false;
    }
    if (preg_match('/\bDEFAULT\s+((?:\'[^\']*\')|(?:"[^"]*")|[^\s,]+)/i', $definition, $defaultMatch)) {
        $expectedDefault = trim($defaultMatch[1], "'\"");
        $actualDefault = $current['COLUMN_DEFAULT'];
        if (strcasecmp($expectedDefault, 'NULL') === 0) {
            if ($actualDefault !== null) {
                return false;
            }
        } elseif ($actualDefault === null || strcasecmp((string) $actualDefault, $expectedDefault) !== 0) {
            return false;
        }
    }
    if (preg_match('/\bCOLLATE\s+([A-Za-z0-9_]+)/i', $definition, $collationMatch)
        && strcasecmp((string) $current['COLLATION_NAME'], $collationMatch[1]) !== 0) {
        return false;
    }
    if ((bool) preg_match('/\bAUTO_INCREMENT\b/i', $definition) !== (stripos((string) $current['EXTRA'], 'auto_increment') !== false)) {
        return false;
    }
    return true;
}

function applyAlter(PDO $pdo, string $statement, bool $verifyOnly = false): bool
{
    if (!preg_match('/^ALTER\s+TABLE\s+(`?[A-Za-z0-9_]+`?)\s+(.+)$/is', trim($statement), $matches)) {
        throw new RuntimeException('ALTER TABLE no reconocido; requiere revisión manual.');
    }
    $table = trim($matches[1], '`');
    $actions = trim($matches[2]);

    if (!preg_match_all('/(?:^|,\s*)(ADD\s+COLUMN\s+(`?)([A-Za-z0-9_]+)\2\s+.*?)(?=,\s*ADD\s+COLUMN\s+|$)/is', $actions, $addMatches, PREG_SET_ORDER)) {
        if (preg_match('/^ADD\s+(UNIQUE\s+)?(?:KEY|INDEX)\s+(`?)([A-Za-z0-9_]+)\2\s*\(([^)]+)\)$/i', $actions, $indexMatch)) {
            $unique = !empty($indexMatch[1]);
            $indexName = $indexMatch[3];
            $expectedColumns = array_map(
                static function (string $name): string { return trim($name, " `\t\r\n"); },
                explode(',', $indexMatch[4])
            );
            $indexStmt = $pdo->prepare(
                'SELECT column_name, non_unique FROM information_schema.statistics ' .
                'WHERE table_schema = DATABASE() AND table_name = ? AND index_name = ? ORDER BY seq_in_index'
            );
            $indexStmt->execute([$table, $indexName]);
            $existingIndex = $indexStmt->fetchAll();
            if ($existingIndex !== []) {
                $existingColumns = array_map(static function (array $row): string { return (string) $row['column_name']; }, $existingIndex);
                if ($existingColumns !== $expectedColumns || (int) $existingIndex[0]['non_unique'] !== ($unique ? 0 : 1)) {
                    throw new RuntimeException("El índice {$table}.{$indexName} existe con definición distinta a la migración.");
                }
                return false;
            }
            if ($verifyOnly) {
                throw new RuntimeException("Falta el índice {$table}.{$indexName} registrado como aplicado.");
            }
            $pdo->exec('ALTER TABLE ' . quoteIdentifier($table) . ' ' . trim($actions));
            return true;
        }
        if (!preg_match('/^MODIFY\s+(?:COLUMN\s+)?(`?)([A-Za-z0-9_]+)\1\s+(.+)$/is', $actions, $modify)) {
            throw new RuntimeException('ALTER TABLE solo admite ADD COLUMN, ADD INDEX o MODIFY COLUMN seguro.');
        }
        $column = $modify[2];
        $desiredType = trim($modify[3]);
        $existing = currentColumn($pdo, $table, $column);
        if ($existing !== null && columnDefinitionMatches(
            $pdo,
            $table,
            $column,
            quoteIdentifier($column) . ' ' . $desiredType
        )) {
            return false;
        }
        if ($verifyOnly) {
            throw new RuntimeException("La columna {$table}.{$column} no coincide con la migración registrada.");
        }
        $pdo->exec('ALTER TABLE ' . quoteIdentifier($table) . ' MODIFY COLUMN ' . quoteIdentifier($column) . ' ' . $desiredType);
        return true;
    }

    $changed = false;
    foreach ($addMatches as $add) {
        $definition = trim($add[1]);
        $column = $add[3];
        if (currentColumn($pdo, $table, $column) !== null) {
            if (!columnDefinitionMatches($pdo, $table, $column, preg_replace('/^ADD\s+COLUMN\s+/i', '', $definition) ?: $definition)) {
                throw new RuntimeException("La columna {$table}.{$column} existe con tipo distinto al definido en la migración.");
            }
            continue;
        }
        if ($verifyOnly) {
            throw new RuntimeException("Falta la columna {$table}.{$column} registrada como aplicada.");
        }
        $pdo->exec('ALTER TABLE ' . quoteIdentifier($table) . ' ' . $definition);
        $changed = true;
    }
    return $changed;
}

function applyMigrationFile(PDO $pdo, string $file, bool $verifyOnly = false): string
{
    $changed = false;
    foreach (migrationStatements((string) file_get_contents($file)) as $statement) {
        if (preg_match('/^USE\s+(`?)([A-Za-z0-9_]+)\1$/i', trim($statement), $use)) {
            $allowed = ['e_talent_core', 'e_talent_tests', 'e_talent_evaluaciones_encuestas', 'e_talent_interviews'];
            if (!in_array($use[2], $allowed, true)) {
                throw new RuntimeException('La migración intenta cambiar a un esquema no permitido.');
            }
            $pdo->exec('USE ' . quoteIdentifier($use[2]));
            continue;
        }
        if (preg_match('/^CREATE\s+TABLE\s+IF\s+NOT\s+EXISTS\s+(`?[A-Za-z0-9_]+`?)(.*)$/is', trim($statement), $create)) {
            $table = trim($create[1], '`');
            $before = (bool) $pdo->query(
                'SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ' . $pdo->quote($table)
            )->fetchColumn();
            if ($verifyOnly && !$before) {
                throw new RuntimeException("Falta la tabla {$table} registrada como aplicada.");
            }
            if (!$verifyOnly) {
                $pdo->exec($statement);
            }
            if (preg_match_all('/^\s*(?:`([A-Za-z0-9_]+)`|([A-Za-z0-9_]+))\s+((?:TINYINT|SMALLINT|MEDIUMINT|INT|BIGINT|VARCHAR|CHAR|TEXT|MEDIUMTEXT|LONGTEXT|DECIMAL|DATETIME|DATE|TIMESTAMP|ENUM|JSON|BOOL|BOOLEAN)\b.*?)(?:,)?\s*$/im', $create[2], $columnMatches, PREG_SET_ORDER)) {
                foreach ($columnMatches as $columnMatch) {
                    $column = $columnMatch[1] !== '' ? $columnMatch[1] : $columnMatch[2];
                    $definition = quoteIdentifier($column) . ' ' . trim($columnMatch[3]);
                    if (!columnDefinitionMatches($pdo, $table, $column, $definition)) {
                        throw new RuntimeException("La tabla {$table}.{$column} no coincide con la definición de la migración.");
                    }
                }
            }
            if (preg_match('/^\s*PRIMARY\s+KEY\b/im', $create[2])) {
                $primary = $pdo->prepare(
                    "SELECT 1 FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = ? AND index_name = 'PRIMARY'"
                );
                $primary->execute([$table]);
                if (!$primary->fetchColumn()) {
                    throw new RuntimeException("La tabla {$table} no tiene la PRIMARY KEY declarada por la migración.");
                }
            }
            if (preg_match_all('/^\s*(?:UNIQUE\s+)?KEY\s+`?([A-Za-z0-9_]+)`?\s*\(/im', $create[2], $indexMatches)) {
                foreach ($indexMatches[1] as $indexName) {
                    $index = $pdo->prepare(
                        'SELECT 1 FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = ? AND index_name = ?'
                    );
                    $index->execute([$table, $indexName]);
                    if (!$index->fetchColumn()) {
                        throw new RuntimeException("La tabla {$table} no tiene el índice {$indexName} declarado por la migración.");
                    }
                }
            }
            if (preg_match_all('/^\s*CONSTRAINT\s+`?([A-Za-z0-9_]+)`?\s+FOREIGN\s+KEY/im', $create[2], $constraintMatches)) {
                foreach ($constraintMatches[1] as $constraintName) {
                    $constraint = $pdo->prepare(
                        "SELECT 1 FROM information_schema.table_constraints WHERE constraint_schema = DATABASE() AND table_name = ? AND constraint_name = ? AND constraint_type = 'FOREIGN KEY'"
                    );
                    $constraint->execute([$table, $constraintName]);
                    if (!$constraint->fetchColumn()) {
                        throw new RuntimeException("La tabla {$table} no tiene la FK {$constraintName} declarada por la migración.");
                    }
                }
            }
            $changed = $changed || !$before;
            continue;
        }
        if (preg_match('/^ALTER\s+TABLE\b/i', trim($statement))) {
            $changed = applyAlter($pdo, $statement, $verifyOnly) || $changed;
            continue;
        }
        throw new RuntimeException('Sentencia de migración no permitida por el runner: ' . strtoupper(strtok(trim($statement), " \t\r\n")));
    }
    $pdo->exec('USE `e_talent_core`');
    return $changed ? 'executed' : 'verified_existing';
}

function migrationStatus(PDO $pdo): int
{
    $rows = migrationRows($pdo);
    $pending = 0;
    foreach (migrationFiles() as $file) {
        $name = basename($file);
        $row = $rows[$name] ?? null;
        $checksum = hash_file('sha256', $file);
        if ($row === null) {
            echo "PENDIENTE  {$name}\n";
            $pending++;
        } elseif (!hash_equals((string) $row['checksum'], (string) $checksum)) {
            echo "CAMBIO    {$name} (checksum distinto; requiere revisión)\n";
            $pending++;
        } elseif ($row['status'] !== 'applied') {
            echo strtoupper((string) $row['status']) . "     {$name}\n";
            $pending++;
        } else {
            if ($row['status'] === 'applied') {
                try {
                    applyMigrationFile($pdo, $file, true);
                } catch (Throwable $error) {
                    echo "DERIVA     {$name}: {$error->getMessage()}\n";
                    $pending++;
                    continue;
                }
            }
            echo strtoupper((string) $row['status']) . '     ' . $name . ' [' . ($row['application_method'] ?? 'sin método') . ']';
            if (!empty($row['applied_at'])) {
                echo ' ' . $row['applied_at'];
            }
            echo "\n";
        }
    }
    echo 'Pendientes o revisión requerida: ' . $pending . "\n";
    return $pending === 0 ? 0 : 2;
}

function runMigrations(PDO $pdo): int
{
    ensureLedger($pdo);
    $lock = $pdo->query("SELECT GET_LOCK(" . $pdo->quote(MIGRATION_LOCK) . ', 10)')->fetchColumn();
    if ((int) $lock !== 1) {
        migrationFail('Otro proceso mantiene el bloqueo de migraciones; no se aplicó ningún archivo.');
    }

    try {
        foreach (migrationFiles() as $file) {
            $name = basename($file);
            $checksum = hash_file('sha256', $file);
            $existing = migrationRows($pdo)[$name] ?? null;
            if ($existing !== null && !hash_equals((string) $existing['checksum'], (string) $checksum)) {
                migrationFail("Checksum cambió para {$name}; se requiere revisión manual.");
            }
            if ($existing !== null && $existing['status'] === 'applied') {
                echo "YA REGISTRADA  {$name}\n";
                continue;
            }

            $upsert = $pdo->prepare(
                'INSERT INTO ' . MIGRATION_LEDGER . ' (migration, checksum, status, application_method, started_at, applied_at, error_message) ' .
                'VALUES (?, ?, \'running\', NULL, NOW(), NULL, NULL) ' .
                'ON DUPLICATE KEY UPDATE checksum = VALUES(checksum), status = \'running\', application_method = NULL, started_at = NOW(), applied_at = NULL, error_message = NULL'
            );
            $upsert->execute([$name, $checksum]);

            try {
                $method = applyMigrationFile($pdo, $file);
                $done = $pdo->prepare(
                    'UPDATE ' . MIGRATION_LEDGER . ' SET status = \'applied\', application_method = ?, applied_at = NOW(), error_message = NULL WHERE migration = ?'
                );
                $done->execute([$method, $name]);
                echo strtoupper($method) . "  {$name}\n";
            } catch (Throwable $error) {
                $failed = $pdo->prepare(
                    'UPDATE ' . MIGRATION_LEDGER . ' SET status = \'failed\', error_message = ? WHERE migration = ?'
                );
                $failed->execute([substr($error->getMessage(), 0, 1000), $name]);
                migrationFail("Falló {$name}: {$error->getMessage()} (el registro quedó en estado failed).");
            }
        }
    } finally {
        $pdo->query("SELECT RELEASE_LOCK(" . $pdo->quote(MIGRATION_LOCK) . ')');
    }
    return 0;
}

$command = $argv[1] ?? 'status';
if (!in_array($command, ['status', 'migrate'], true)) {
    migrationFail('Uso: php scripts/db-migrate.php [status|migrate]', 2);
}

try {
    $pdo = migrationPdo();
    if ($command === 'status') {
        exit(migrationStatus($pdo));
    }
    exit(runMigrations($pdo));
} catch (Throwable $error) {
    migrationFail('Error del runner: ' . $error->getMessage());
}
