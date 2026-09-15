<?php
declare(strict_types=1);

final class UserModel
{
    public const ALLOWED_ROLES = ['admin', 'agente', 'usuario', 'company_admin'];
    public const ALLOWED_SEXES = ['masculino', 'femenino', 'no_informado'];

    private Database $db;

    public function __construct(?Database $db = null)
    {
        $this->db = $db ?: database();
    }

    public function findActiveByEmail(string $email): ?array
    {
        return $this->db->fetch('SELECT * FROM users WHERE email = ? AND is_active = 1 LIMIT 1', [$email]);
    }

    public static function cleanRut(string $rut): string
    {
        return strtoupper(preg_replace('/[^0-9Kk]/', '', $rut) ?? '');
    }

    public static function formatRut(string $rut): string
    {
        $clean = self::cleanRut($rut);
        if (strlen($clean) < 2) {
            return $clean;
        }

        $body = substr($clean, 0, -1);
        $dv = substr($clean, -1);
        $formattedBody = '';
        while (strlen($body) > 3) {
            $formattedBody = '.' . substr($body, -3) . $formattedBody;
            $body = substr($body, 0, -3);
        }

        return $body . $formattedBody . '-' . $dv;
    }

    public static function isValidRut(string $rut): bool
    {
        $clean = self::cleanRut($rut);
        if (strlen(self::formatRut($clean)) > 12) {
            return false;
        }

        if (!preg_match('/^\d{7,8}[0-9K]$/', $clean)) {
            return false;
        }

        $body = substr($clean, 0, -1);
        $dv = substr($clean, -1);
        $factor = 2;
        $sum = 0;
        for ($i = strlen($body) - 1; $i >= 0; $i--) {
            $sum += (int) $body[$i] * $factor;
            $factor = $factor === 7 ? 2 : $factor + 1;
        }

        $expected = 11 - ($sum % 11);
        $expectedDv = $expected === 11 ? '0' : ($expected === 10 ? 'K' : (string) $expected);

        return $dv === $expectedDv;
    }

    public static function rutDefaultPassword(string $rut): string
    {
        $clean = self::cleanRut($rut);
        $body = substr($clean, 0, -1);

        return substr($body, -4);
    }

    public static function calculateAge(string $birthDate, ?DateTimeInterface $today = null): ?int
    {
        $birthDate = trim($birthDate);
        $date = DateTime::createFromFormat('Y-m-d', $birthDate);
        if (!$date || $date->format('Y-m-d') !== $birthDate) {
            return null;
        }

        $today = $today ?: new DateTimeImmutable('today');
        if ($date > $today) {
            return null;
        }

        return (int) $date->diff($today)->y;
    }

    public static function displayName(array $data): string
    {
        return trim(trim((string) ($data['first_names'] ?? '')) . ' ' . trim((string) ($data['last_names'] ?? '')));
    }

    public function all(): array
    {
        return $this->db->fetchAll('
            SELECT u.id, u.rut, u.first_names, u.last_names, u.sex, u.birth_date, u.age, u.name, u.email, u.role, u.profile_id, u.company_id, u.is_active,
                   c.name AS company_name, p.name AS profile_name
            FROM users u
            LEFT JOIN companies c ON c.id = u.company_id
            LEFT JOIN role_profiles p ON p.id = u.profile_id
            WHERE ' . $this->companyScopeSql('u') . '
            ORDER BY u.role, c.name, u.name
        ', $this->companyScopeParams());
    }

    public function findActiveById(int $id): ?array
    {
        return $this->db->fetch('
            SELECT u.id, u.rut, u.first_names, u.last_names, u.sex, u.birth_date, u.age, u.name, u.email, u.role, u.profile_id, u.company_id, u.is_active, c.name AS company_name
            FROM users u
            LEFT JOIN companies c ON c.id = u.company_id
            WHERE u.id = ? AND u.is_active = 1 AND ' . $this->companyScopeSql('u') . '
            LIMIT 1
        ', array_merge([$id], $this->companyScopeParams()));
    }

    public function findRequester(int $id): ?array
    {
        return $this->db->fetch('
            SELECT u.id, u.rut, u.first_names, u.last_names, u.sex, u.birth_date, u.age, u.name, u.email, u.role, u.profile_id, u.company_id, u.is_active, c.name AS company_name
            FROM users u
            LEFT JOIN companies c ON c.id = u.company_id
            WHERE u.id = ? AND u.role = \'usuario\' AND ' . $this->companyScopeSql('u') . '
            LIMIT 1
        ', array_merge([$id], $this->companyScopeParams()));
    }

    public function findUser(int $id): ?array
    {
        return $this->db->fetch('
            SELECT u.id, u.rut, u.first_names, u.last_names, u.sex, u.birth_date, u.age, u.name, u.email, u.role, u.profile_id, u.company_id, u.is_active, c.name AS company_name
            FROM users u
            LEFT JOIN companies c ON c.id = u.company_id
            WHERE u.id = ? AND ' . $this->companyScopeSql('u') . '
            LIMIT 1
        ', array_merge([$id], $this->companyScopeParams()));
    }

    public function findRequesterByEmail(string $email): ?array
    {
        return $this->db->fetch("
            SELECT u.id, u.rut, u.first_names, u.last_names, u.sex, u.birth_date, u.age, u.name, u.email, u.role, u.profile_id, u.company_id, u.is_active, c.name AS company_name
            FROM users u
            LEFT JOIN companies c ON c.id = u.company_id
            WHERE LOWER(u.email) = LOWER(?) AND u.role = 'usuario' AND " . $this->companyScopeSql('u') . "
            LIMIT 1
        ", array_merge([$email], $this->companyScopeParams()));
    }

    public function findUserByEmail(string $email): ?array
    {
        return $this->db->fetch("
            SELECT u.id, u.rut, u.first_names, u.last_names, u.sex, u.birth_date, u.age, u.name, u.email, u.role, u.profile_id, u.company_id, u.is_active, c.name AS company_name
            FROM users u
            LEFT JOIN companies c ON c.id = u.company_id
            WHERE LOWER(u.email) = LOWER(?) AND " . $this->companyScopeSql('u') . "
            LIMIT 1
        ", array_merge([$email], $this->companyScopeParams()));
    }

    public function findRequesterByRut(string $rut): ?array
    {
        return $this->db->fetch("
            SELECT u.id, u.rut, u.first_names, u.last_names, u.sex, u.birth_date, u.age, u.name, u.email, u.role, u.profile_id, u.company_id, u.is_active, c.name AS company_name
            FROM users u
            LEFT JOIN companies c ON c.id = u.company_id
            WHERE u.rut = ? AND u.role = 'usuario' AND " . $this->companyScopeSql('u') . "
            LIMIT 1
        ", array_merge([self::formatRut($rut)], $this->companyScopeParams()));
    }

    public function findUserByRut(string $rut): ?array
    {
        return $this->db->fetch("
            SELECT u.id, u.rut, u.first_names, u.last_names, u.sex, u.birth_date, u.age, u.name, u.email, u.role, u.profile_id, u.company_id, u.is_active, c.name AS company_name
            FROM users u
            LEFT JOIN companies c ON c.id = u.company_id
            WHERE u.rut = ? AND " . $this->companyScopeSql('u') . "
            LIMIT 1
        ", array_merge([self::formatRut($rut)], $this->companyScopeParams()));
    }

    /** @return array<int, array<string, mixed>> */
    public function findUsersByRutsAndEmails(array $ruts, array $emails): array
    {
        $ruts = array_values(array_unique(array_filter(array_map([self::class, 'formatRut'], $ruts))));
        $emails = array_values(array_unique(array_filter(array_map(static fn($email): string => mb_strtolower(trim((string) $email)), $emails))));
        if (!$ruts && !$emails) {
            return [];
        }
        $conditions = [];
        $params = [];
        if ($ruts) {
            $conditions[] = 'u.rut IN (' . implode(',', array_fill(0, count($ruts), '?')) . ')';
            array_push($params, ...$ruts);
        }
        if ($emails) {
            $conditions[] = 'LOWER(u.email) IN (' . implode(',', array_fill(0, count($emails), '?')) . ')';
            array_push($params, ...$emails);
        }
        return $this->db->fetchAll(
            'SELECT u.id, u.rut, u.first_names, u.last_names, u.sex, u.birth_date, u.age, u.name, u.email, u.role, u.profile_id, u.company_id, u.is_active, c.name AS company_name
             FROM users u LEFT JOIN companies c ON c.id = u.company_id
             WHERE (' . implode(' OR ', $conditions) . ') AND ' . $this->companyScopeSql('u'),
            array_merge($params, $this->companyScopeParams())
        );
    }

    public function emailExists(string $email, ?int $excludeId = null): bool
    {
        $params = [$email];
        $sql = 'SELECT u.id FROM users u WHERE LOWER(u.email) = LOWER(?) AND ' . $this->companyScopeSql();
        $params = array_merge($params, $this->companyScopeParams());
        if ($excludeId !== null) {
            $sql .= ' AND id <> ?';
            $params[] = $excludeId;
        }

        return (bool) $this->db->fetch($sql . ' LIMIT 1', $params);
    }

    public function rutExists(string $rut, ?int $excludeId = null): bool
    {
        $params = [self::formatRut($rut)];
        $sql = 'SELECT u.id FROM users u WHERE u.rut = ? AND ' . $this->companyScopeSql();
        $params = array_merge($params, $this->companyScopeParams());
        if ($excludeId !== null) {
            $sql .= ' AND id <> ?';
            $params[] = $excludeId;
        }

        return (bool) $this->db->fetch($sql . ' LIMIT 1', $params);
    }

    public function assignable(): array
    {
        return $this->db->fetchAll("SELECT u.id, u.name FROM users u WHERE u.role IN ('admin', 'agente', 'company_admin') AND u.is_active = 1 AND " . $this->companyScopeSql() . " ORDER BY u.name", $this->companyScopeParams());
    }

    public function companies(): array
    {
        $user = current_user();
        if ($user && has_permission('manage_company_users') && (int) ($user['company_id'] ?? 0) > 0) {
            return $this->db->fetchAll('SELECT id, name FROM companies WHERE id = ? AND is_active = 1 ORDER BY name', [(int) $user['company_id']]);
        }
        return $this->db->fetchAll('SELECT id, name FROM companies WHERE is_active = 1 ORDER BY name');
    }

    public function companiesByName(): array
    {
        $companies = [];
        foreach ($this->companies() as $company) {
            $companies[mb_strtolower(trim($company['name']))] = $company;
        }

        return $companies;
    }

    private function usersDataWhere(string $search): array
    {
        $search = trim($search);
        if ($search === '') {
            return ['1 = 1', []];
        }

        $like = '%' . $search . '%';
        return ["
            (
                u.rut LIKE ?
                OR u.first_names LIKE ?
                OR u.last_names LIKE ?
                OR u.name LIKE ?
                OR u.email LIKE ?
                OR u.sex LIKE ?
                OR u.birth_date LIKE ?
                OR CAST(u.age AS CHAR) LIKE ?
                OR p.name LIKE ?
                OR c.name LIKE ?
                OR EXISTS (
                    SELECT 1
                    FROM user_field_values fv
                    WHERE fv.user_id = u.id AND fv.value LIKE ?
                )
            )
        ", [$like, $like, $like, $like, $like, $like, $like, $like, $like, $like, $like]];
    }

    private function usersDataOrderSql(int $column, string $direction): string
    {
        $columns = [
            0 => 'u.rut',
            1 => 'u.first_names',
            2 => 'u.last_names',
            3 => 'u.email',
            4 => 'u.sex',
            5 => 'u.birth_date',
            6 => 'u.age',
            7 => 'p.name',
            8 => 'c.name',
        ];

        $columnSql = $columns[$column] ?? 'u.name';
        return $columnSql . ' ' . $direction . ', u.id DESC';
    }

    public function requesters(): array
    {
        return $this->db->fetchAll("
            SELECT u.id, u.rut, u.first_names, u.last_names, u.sex, u.birth_date, u.age, u.name, u.email, u.role, u.profile_id, u.is_active, c.name AS company_name, p.name AS profile_name
            FROM users u
            LEFT JOIN companies c ON c.id = u.company_id
            LEFT JOIN role_profiles p ON p.id = u.profile_id
            WHERE u.role = 'usuario' AND " . $this->companyScopeSql('u') . "
            ORDER BY c.name, u.name
        ", $this->companyScopeParams());
    }

    public function users(): array
    {
        return $this->all();
    }

    public function usersDataPage(string $search, int $start, int $length, int $orderColumn, string $orderDir): array
    {
        $start = max(0, $start);
        $length = max(10, min(100, $length));
        $orderDir = strtolower($orderDir) === 'desc' ? 'DESC' : 'ASC';
        [$whereSql, $params] = $this->usersDataWhere($search);
        $scopeSql = $this->companyScopeSql('u');
        $scopeParams = $this->companyScopeParams();
        $whereSql = "({$whereSql}) AND {$scopeSql}";
        $params = array_merge($params, $scopeParams);
        $orderSql = $this->usersDataOrderSql($orderColumn, $orderDir);

        $totalRow = $this->db->fetch('SELECT COUNT(*) AS total FROM users u WHERE ' . $this->companyScopeSql('u'), $scopeParams);
        $filteredRow = $this->db->fetch("
            SELECT COUNT(*) AS total
            FROM users u
            LEFT JOIN companies c ON c.id = u.company_id
            LEFT JOIN role_profiles p ON p.id = u.profile_id
            WHERE {$whereSql}
        ", $params);

        $rows = $this->db->fetchAll("
            SELECT u.id, u.rut, u.first_names, u.last_names, u.sex, u.birth_date, u.age, u.name, u.email, u.role, u.profile_id, u.company_id, u.is_active,
                   c.name AS company_name, p.name AS profile_name
            FROM users u
            LEFT JOIN companies c ON c.id = u.company_id
            LEFT JOIN role_profiles p ON p.id = u.profile_id
            WHERE {$whereSql}
            ORDER BY {$orderSql}
            LIMIT {$length} OFFSET {$start}
        ", $params);

        return [
            'rows' => $rows,
            'total' => (int) ($totalRow['total'] ?? 0),
            'filtered' => (int) ($filteredRow['total'] ?? 0),
        ];
    }

    public function createRequester(array $data): int
    {
        $data['rut'] = self::formatRut($data['rut'] ?? '');
        $data['name'] = self::displayName($data);
        $data['age'] = (int) ($data['age'] ?? self::calculateAge((string) ($data['birth_date'] ?? '')));
        $role = in_array($data['role'] ?? '', self::ALLOWED_ROLES, true) ? $data['role'] : 'usuario';
        return $this->db->insert("
            INSERT INTO users (rut, first_names, last_names, sex, birth_date, age, name, email, password_hash, role, profile_id, company_id, is_active)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ", [
            $data['rut'],
            $data['first_names'],
            $data['last_names'],
            $data['sex'],
            $data['birth_date'],
            $data['age'],
            $data['name'],
            $data['email'],
            password_hash($data['password'], PASSWORD_BCRYPT),
            $role,
            (int) $data['profile_id'],
            $data['company_id'] ? (int) $data['company_id'] : null,
            (int) $data['is_active'],
        ]);
    }

    public function updateRequester(int $id, array $data): void
    {
        $data['rut'] = self::formatRut($data['rut'] ?? '');
        $data['name'] = self::displayName($data);
        $data['age'] = (int) ($data['age'] ?? self::calculateAge((string) ($data['birth_date'] ?? '')));
        $role = in_array($data['role'] ?? '', self::ALLOWED_ROLES, true) ? $data['role'] : 'usuario';
        $params = [
            $data['rut'],
            $data['first_names'],
            $data['last_names'],
            $data['sex'],
            $data['birth_date'],
            $data['age'],
            $data['name'],
            $data['email'],
            $role,
            (int) $data['profile_id'],
            $data['company_id'] ? (int) $data['company_id'] : null,
            (int) $data['is_active'],
        ];
        $passwordSql = '';

        if (($data['password'] ?? '') !== '') {
            $passwordSql = ', password_hash = ?';
            $params[] = password_hash($data['password'], PASSWORD_BCRYPT);
        }

        $params[] = $id;

        $this->db->execute("
            UPDATE users
            SET rut = ?, first_names = ?, last_names = ?, sex = ?, birth_date = ?, age = ?, name = ?, email = ?, role = ?, profile_id = ?, company_id = ?, is_active = ? {$passwordSql}
            WHERE id = ?
        ", $params);
    }

    public function createUser(array $data): int
    {
        return $this->createRequester($data);
    }

    public function updateUser(int $id, array $data): void
    {
        $this->updateRequester($id, $data);
    }

    public function upsertRequesterFromImport(array $data): int
    {
        $existing = $this->findUserByRut($data['rut']) ?: $this->findUserByEmail($data['email']);

        if ($existing) {
            $this->updateUser((int) $existing['id'], $data);
            return (int) $existing['id'];
        }

        return $this->createUser($data);
    }

    public function upsertUserFromImport(array $data): int
    {
        return $this->upsertRequesterFromImport($data);
    }

    public function importRequesters(array $rows, array $fields): array
    {
        return $this->db->transaction(function () use ($rows, $fields): array {
            $created = 0;
            $updated = 0;
            $userIds = [];
            $pdo = $this->db->pdo();
            $insertUser = $pdo->prepare('
                INSERT INTO users (rut, first_names, last_names, sex, birth_date, age, name, email, password_hash, role, profile_id, company_id, is_active)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ');
            $updateUser = $pdo->prepare('
                UPDATE users
                SET rut = ?, first_names = ?, last_names = ?, sex = ?, birth_date = ?, age = ?, name = ?, email = ?, role = ?, profile_id = ?, company_id = ?, is_active = ?
                WHERE id = ? AND ' . $this->companyScopeSql('') . '
            ');
            $updateUserWithPassword = $pdo->prepare('
                UPDATE users
                SET rut = ?, first_names = ?, last_names = ?, sex = ?, birth_date = ?, age = ?, name = ?, email = ?, role = ?, profile_id = ?, company_id = ?, is_active = ?, password_hash = ?
                WHERE id = ? AND ' . $this->companyScopeSql('') . '
            ');
            $upsertFieldValue = $pdo->prepare('
                INSERT INTO user_field_values (user_id, field_id, value)
                VALUES (?, ?, ?)
                ON DUPLICATE KEY UPDATE value = VALUES(value)
            ');

            foreach ($rows as $row) {
                $existingId = (int) ($row['existing_id'] ?? 0);
                if ($existingId <= 0) {
                    $existing = $this->findUserByRut($row['data']['rut']) ?: $this->findUserByEmail($row['data']['email']);
                    $existingId = $existing ? (int) $existing['id'] : 0;
                }

                $data = $this->prepareImportUserData($row['data']);
                if ($existingId > 0) {
                    $params = [
                        $data['rut'],
                        $data['first_names'],
                        $data['last_names'],
                        $data['sex'],
                        $data['birth_date'],
                        $data['age'],
                        $data['name'],
                        $data['email'],
                        $data['role'],
                        $data['profile_id'],
                        $data['company_id'],
                        $data['is_active'],
                    ];

                    if (($data['password'] ?? '') !== '') {
                        $params[] = password_hash($data['password'], PASSWORD_BCRYPT);
                        array_push($params, $existingId, ...$this->companyScopeParams());
                        $updateUserWithPassword->execute($params);
                    } else {
                        array_push($params, $existingId, ...$this->companyScopeParams());
                        $updateUser->execute($params);
                    }

                    $userId = $existingId;
                    $updated++;
                } else {
                    $insertUser->execute([
                        $data['rut'],
                        $data['first_names'],
                        $data['last_names'],
                        $data['sex'],
                        $data['birth_date'],
                        $data['age'],
                        $data['name'],
                        $data['email'],
                        password_hash($data['password'], PASSWORD_BCRYPT),
                        $data['role'],
                        $data['profile_id'],
                        $data['company_id'],
                        $data['is_active'],
                    ]);
                    $userId = (int) $pdo->lastInsertId();
                    $created++;
                }

                $this->saveImportFieldValues($upsertFieldValue, $userId, $fields, $row['field_values']);
                $userIds[] = $userId;
            }

            return [
                'created' => $created,
                'updated' => $updated,
                'user_ids' => $userIds,
            ];
        });
    }

    private function prepareImportUserData(array $data): array
    {
        $data['rut'] = self::formatRut($data['rut'] ?? '');
        $data['name'] = self::displayName($data);
        $data['age'] = (int) ($data['age'] ?? self::calculateAge((string) ($data['birth_date'] ?? '')));
        $data['role'] = in_array($data['role'] ?? '', self::ALLOWED_ROLES, true) ? $data['role'] : 'usuario';
        $data['profile_id'] = (int) ($data['profile_id'] ?? 0);
        $data['company_id'] = !empty($data['company_id']) ? (int) $data['company_id'] : null;
        $data['is_active'] = (int) ($data['is_active'] ?? 1);

        return $data;
    }

    private function saveImportFieldValues(PDOStatement $statement, int $userId, array $fields, array $values): void
    {
        foreach ($fields as $field) {
            $fieldId = (int) $field['id'];
            $value = $field['field_type'] === 'checkbox'
                ? (in_array($values[$fieldId] ?? null, ['1', 1, true], true) ? '1' : '0')
                : trim((string) ($values[$fieldId] ?? ''));

            $statement->execute([$userId, $fieldId, $value]);
        }
    }

    public function importUsers(array $rows, array $fields): array
    {
        return $this->importRequesters($rows, $fields);
    }

    private function companyScopeSql(string $alias = 'u'): string
    {
        $user = current_user();
        if (!$user || has_permission('manage_users')) {
            return '1 = 1';
        }

        if (has_permission('manage_company_users') && (int) ($user['company_id'] ?? 0) > 0) {
            $column = $alias !== '' ? $alias . '.company_id' : 'company_id';
            return $column . ' = ?';
        }

        return '1 = 0';
    }

    private function companyScopeParams(): array
    {
        $user = current_user();
        if ($user && !has_permission('manage_users') && has_permission('manage_company_users') && (int) ($user['company_id'] ?? 0) > 0) {
            return [(int) $user['company_id']];
        }

        return [];
    }
}
