<?php
declare(strict_types=1);

final class TestProcessModel
{
    public const STATUSES = [
        'draft' => 'Borrador',
        'active' => 'Activo',
        'closed' => 'Cerrado',
        'cancelled' => 'Cancelado',
    ];

    public const PROCESS_PERMISSIONS = [
        'view_process' => 'Ver proceso',
        'view_process_results' => 'Ver resultados',
        'view_process_dashboard' => 'Ver Dashboard Avance',
        'view_process_ranking' => 'Ver Ranking Resumen',
        'manage_process_users' => 'Gestionar usuarios',
        'manage_process_user_data' => 'Editar datos usuario',
        'manage_process_assignments' => 'Gestionar asignaciones',
        'manage_process_settings' => 'Editar configuracion',
    ];

    private Database $db;
    private string $coreSchema;
    private ?bool $hasSessionProcessColumn = null;
    private ?bool $hasSessionPresenceColumn = null;
    private ?bool $hasProcessAdminModeColumn = null;
    private ?bool $hasProcessAvailabilityColumns = null;
    private ?bool $hasUserLoginEventsTable = null;

    public function __construct(?Database $db = null)
    {
        $this->db = $db ?: database('tests');
        $this->coreSchema = database_identifier('core');
    }

    public function allForUser(array $user): array
    {
        if ($this->isGlobalProcessAdmin()) {
            return $this->db->fetchAll($this->processListSql() . ' ORDER BY p.created_at DESC, p.id DESC');
        }

        $profileId = (int) ($user['profile_id'] ?? 0);
        $userId = (int) ($user['id'] ?? 0);
        if ($profileId <= 0 && $userId <= 0) {
            return [];
        }

        $conditions = [];
        $params = [];

        if ($this->hasProcessCompanyColumn() && (int) ($user['company_id'] ?? 0) > 0 && has_permission('manage_company_processes')) {
            return $this->db->fetchAll(
                $this->processListSql() . ' WHERE p.company_id = ? ORDER BY p.created_at DESC, p.id DESC',
                [(int) $user['company_id']]
            );
        }

        $modeSql = $this->hasProcessAdminModeColumn() ? "COALESCE(p.admin_assignment_mode, 'user')" : "'profile'";

        if ($profileId > 0) {
            $conditions[] = 'EXISTS (
                SELECT 1
                FROM test_process_profile_admins pa
                WHERE pa.process_id = p.id AND pa.profile_id = ?
            ) AND ' . $modeSql . " = 'profile'";
            $params[] = $profileId;
        }

        if ($userId > 0 && $this->tableExists('test_process_user_admins')) {
            $conditions[] = 'EXISTS (
                SELECT 1
                FROM test_process_user_admins ua
                WHERE ua.process_id = p.id AND ua.user_id = ?
            ) AND ' . $modeSql . " = 'user'";
            $params[] = $userId;
        }

        if (!$conditions) {
            return [];
        }

        return $this->db->fetchAll(
            $this->processListSql() . '
            WHERE ' . implode(' OR ', $conditions) . '
            ORDER BY p.created_at DESC, p.id DESC
        ',
            $params
        );
    }

    public function dashboardMacroForUser(array $user): array
    {
        $processes = $this->allForUser($user);
        $ids = array_values(array_filter(array_map(static fn(array $process): int => (int) ($process['id'] ?? 0), $processes)));
        if (!$ids) {
            return ['processes_total' => 0, 'processes_completed' => 0, 'evaluations_answered' => 0, 'evaluations_total' => 0];
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $row = $this->db->fetch("\n            SELECT\n                COUNT(DISTINCT CASE WHEN p.status = 'closed' THEN p.id END) AS processes_completed,\n                COUNT(CASE WHEN s.status = 'completed' THEN s.id END) AS evaluations_answered,\n                COUNT(s.id) AS evaluations_total\n            FROM test_processes p\n            LEFT JOIN test_sessions s ON s.process_id = p.id\n            WHERE p.id IN ({$placeholders})\n        ", $ids) ?: [];

        return [
            'processes_total' => count($processes),
            'processes_completed' => (int) ($row['processes_completed'] ?? 0),
            'evaluations_answered' => (int) ($row['evaluations_answered'] ?? 0),
            'evaluations_total' => (int) ($row['evaluations_total'] ?? 0),
        ];
    }

    public function dashboardProcessOverviewForUser(array $user): array
    {
        $processes = $this->allForUser($user);
        $ids = array_values(array_filter(array_map(static fn(array $process): int => (int) ($process['id'] ?? 0), $processes)));
        if (!$ids) {
            return [
                'today_processes' => 0,
                'today_assigned_people' => 0,
                'completed_processes' => 0,
                'completed_assigned_people' => 0,
                'completed_finished_people' => 0,
                'completed_not_started_people' => 0,
            ];
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $row = $this->db->fetch("
            SELECT
                COALESCE(SUM(CASE WHEN overview.starts_at IS NOT NULL
                    AND overview.ends_at IS NOT NULL
                    AND DATE(overview.starts_at) <= CURDATE()
                    AND DATE(overview.ends_at) >= CURDATE()
                    AND overview.status NOT IN ('cancelled', 'closed')
                    THEN 1 ELSE 0 END), 0) AS today_processes,
                COALESCE(SUM(CASE WHEN overview.starts_at IS NOT NULL
                    AND overview.ends_at IS NOT NULL
                    AND DATE(overview.starts_at) <= CURDATE()
                    AND DATE(overview.ends_at) >= CURDATE()
                    AND overview.status NOT IN ('cancelled', 'closed')
                    THEN overview.assigned_people ELSE 0 END), 0) AS today_assigned_people,
                COALESCE(SUM(CASE WHEN overview.status IN ('active', 'closed')
                    AND (overview.status = 'closed' OR (overview.ends_at IS NOT NULL AND overview.ends_at < NOW()))
                    THEN 1 ELSE 0 END), 0) AS completed_processes,
                COALESCE(SUM(CASE WHEN overview.status IN ('active', 'closed')
                    AND (overview.status = 'closed' OR (overview.ends_at IS NOT NULL AND overview.ends_at < NOW()))
                    THEN overview.assigned_people ELSE 0 END), 0) AS completed_assigned_people,
                COALESCE(SUM(CASE WHEN overview.status IN ('active', 'closed')
                    AND (overview.status = 'closed' OR (overview.ends_at IS NOT NULL AND overview.ends_at < NOW()))
                    THEN overview.finished_people ELSE 0 END), 0) AS completed_finished_people,
                COALESCE(SUM(CASE WHEN overview.status IN ('active', 'closed')
                    AND (overview.status = 'closed' OR (overview.ends_at IS NOT NULL AND overview.ends_at < NOW()))
                    THEN overview.not_started_people ELSE 0 END), 0) AS completed_not_started_people
            FROM (
                SELECT p.id, p.status, p.starts_at, p.ends_at,
                       COUNT(DISTINCT pu.user_id) AS assigned_people,
                       COUNT(DISTINCT CASE WHEN pi.instruments_total > 0
                           AND COALESCE(progress.finished_instruments, 0) >= pi.instruments_total
                           THEN pu.user_id END) AS finished_people,
                       COUNT(DISTINCT CASE WHEN COALESCE(progress.started_instruments, 0) = 0
                           THEN pu.user_id END) AS not_started_people
                FROM test_processes p
                LEFT JOIN test_process_users pu
                    ON pu.process_id = p.id AND pu.status <> 'cancelled'
                LEFT JOIN (
                    SELECT process_id, COUNT(*) AS instruments_total
                    FROM test_process_instruments
                    GROUP BY process_id
                ) pi ON pi.process_id = p.id
                LEFT JOIN (
                    SELECT process_id, user_id,
                           COUNT(DISTINCT CASE WHEN status IN ('completed', 'expired') THEN instrument_id END) AS finished_instruments,
                           COUNT(DISTINCT CASE WHEN status IN ('in_progress', 'completed', 'expired') THEN instrument_id END) AS started_instruments
                    FROM test_sessions
                    WHERE status <> 'cancelled'
                    GROUP BY process_id, user_id
                ) progress ON progress.process_id = p.id AND progress.user_id = pu.user_id
                WHERE p.id IN ({$placeholders})
                GROUP BY p.id, p.status, p.starts_at, p.ends_at
            ) overview
        ", $ids) ?: [];

        return [
            'today_processes' => (int) ($row['today_processes'] ?? 0),
            'today_assigned_people' => (int) ($row['today_assigned_people'] ?? 0),
            'completed_processes' => (int) ($row['completed_processes'] ?? 0),
            'completed_assigned_people' => (int) ($row['completed_assigned_people'] ?? 0),
            'completed_finished_people' => (int) ($row['completed_finished_people'] ?? 0),
            'completed_not_started_people' => (int) ($row['completed_not_started_people'] ?? 0),
        ];
    }

    public function find(int $id): ?array
    {
        return $this->db->fetch(
            'SELECT * FROM test_processes p WHERE p.id = ? ' . $this->companyUserScopeSql('p') . ' LIMIT 1',
            array_merge([$id], $this->companyUserScopeParams())
        );
    }

    public function can(array $user, int $processId, string $permission): bool
    {
        if ($this->isGlobalProcessAdmin()) {
            return true;
        }

        if ($this->hasProcessCompanyColumn() && (int) ($user['company_id'] ?? 0) > 0) {
            $process = $this->db->fetch('SELECT company_id FROM test_processes WHERE id = ? LIMIT 1', [$processId]);
            $companyPermissions = [
                'view_process', 'view_process_results', 'view_process_dashboard', 'view_process_ranking',
                'manage_process_users', 'manage_process_user_data', 'manage_process_assignments', 'manage_process_settings',
            ];
            if ($process && (int) ($process['company_id'] ?? 0) === (int) $user['company_id']
                && has_permission('manage_company_processes')
                && in_array($permission, $companyPermissions, true)) {
                return true;
            }
        }

        $profileId = (int) ($user['profile_id'] ?? 0);
        $userId = (int) ($user['id'] ?? 0);
        if (($profileId <= 0 && $userId <= 0) || $processId <= 0) {
            return false;
        }

        $processMode = $this->processAdminAssignmentMode($processId);

        if ($processMode === 'user' && $userId > 0 && $this->tableExists('test_process_user_admins')) {
            $row = $this->db->fetch('
                SELECT permissions
                FROM test_process_user_admins
                WHERE process_id = ? AND user_id = ?
                LIMIT 1
            ', [$processId, $userId]);

            if ($row && $this->permissionsContain($row['permissions'] ?? '[]', $permission)) {
                return true;
            }
        }

        if ($processMode !== 'profile' || $profileId <= 0) {
            return false;
        }

        $row = $this->db->fetch('
            SELECT permissions
            FROM test_process_profile_admins
            WHERE process_id = ? AND profile_id = ?
            LIMIT 1
        ', [$processId, $profileId]);

        return $row ? $this->permissionsContain($row['permissions'] ?? '[]', $permission) : false;
    }

    private function permissionsContain(string $encodedPermissions, string $permission): bool
    {
        $permissions = json_decode($encodedPermissions, true);
        $permissions = is_array($permissions) ? $permissions : [];

        if (in_array($permission, $permissions, true)) {
            return true;
        }

        return $permission === 'view_process' && in_array('view_process_progress', $permissions, true);
    }

    public function activeInstruments(): array
    {
        return $this->db->fetchAll('
            SELECT id, code, name, category, duration_minutes
            FROM test_instruments
            WHERE status = "active"
            ORDER BY name
        ');
    }

    public function activeProfiles(): array
    {
        $rows = $this->db->fetchAll("
            SELECT id, name, role_key, permissions
            FROM {$this->coreSchema}.role_profiles
            WHERE is_active = 1
            ORDER BY name
        ");

        if (has_permission('manage_company_processes') && !has_permission('manage_test_processes')) {
            return array_values(array_filter($rows, static fn(array $row): bool => (string) ($row['role_key'] ?? '') === 'usuario'));
        }

        return $rows;
    }

    public function activeProcessAdminUsers(): array
    {
        return $this->db->fetchAll("
            SELECT u.id, u.name, u.email, u.rut, p.name AS profile_name, p.role_key AS profile_key
            FROM {$this->coreSchema}.users u
            JOIN {$this->coreSchema}.role_profiles p ON p.id = u.profile_id
            WHERE u.is_active = 1
              " . $this->companyUserScopeSql('u') . "
              AND (
                  p.role_key <> 'usuario'
                  OR p.permissions LIKE '%test-processes%'
                  OR p.permissions LIKE '%test-process.dashboard%'
                  OR p.permissions LIKE '%manage_test_processes%'
                  OR p.permissions LIKE '%view_test_process%'
              )
            ORDER BY p.name, u.name
        ", $this->companyUserScopeParams());
    }

    public function userFields(): array
    {
        $fields = $this->db->fetchAll("
            SELECT id, field_key, label, field_type, options, show_in_list, sort_order
            FROM {$this->coreSchema}.user_field_definitions
            WHERE is_active = 1
            ORDER BY scope_type ASC, scope_key ASC, sort_order ASC, label ASC
        ");

        $fields[] = [
            'id' => 0,
            'field_key' => 'edad',
            'label' => 'Edad',
            'field_type' => 'number',
            'options' => '',
            'show_in_list' => 1,
            'sort_order' => 40,
        ];

        return $fields;
    }

    public function selectedInstrumentIds(int $processId): array
    {
        return array_map('intval', array_column($this->db->fetchAll('
            SELECT instrument_id FROM test_process_instruments WHERE process_id = ? ORDER BY sort_order ASC, id ASC
        ', [$processId]), 'instrument_id'));
    }

    public function selectedFields(int $processId): array
    {
        $rows = $this->db->fetchAll('
            SELECT field_id, is_required, show_in_process, sort_order
            FROM test_process_user_fields
            WHERE process_id = ?
            ORDER BY sort_order ASC, id ASC
        ', [$processId]);

        $fields = [];
        foreach ($rows as $row) {
            $fields[(int) $row['field_id']] = $row;
        }

        return $fields;
    }

    public function selectedAssignableProfileIds(int $processId): array
    {
        if (!$this->tableExists('test_process_assignable_profiles')) {
            return [];
        }

        return array_map('intval', array_column($this->db->fetchAll('
            SELECT profile_id
            FROM test_process_assignable_profiles
            WHERE process_id = ?
            ORDER BY profile_id ASC
        ', [$processId]), 'profile_id'));
    }

    public function selectedProfileAdmins(int $processId): array
    {
        $rows = $this->db->fetchAll('
            SELECT profile_id, permissions
            FROM test_process_profile_admins
            WHERE process_id = ?
            ORDER BY profile_id ASC
        ', [$processId]);

        $profiles = [];
        foreach ($rows as $row) {
            $permissions = json_decode($row['permissions'] ?? '[]', true);
            $profiles[(int) $row['profile_id']] = is_array($permissions) ? $permissions : [];
        }

        return $profiles;
    }

    public function selectedUserAdmins(int $processId): array
    {
        if (!$this->tableExists('test_process_user_admins')) {
            return [];
        }

        $rows = $this->db->fetchAll('
            SELECT user_id, permissions
            FROM test_process_user_admins
            WHERE process_id = ?
            ORDER BY user_id ASC
        ', [$processId]);

        $users = [];
        foreach ($rows as $row) {
            $permissions = json_decode($row['permissions'] ?? '[]', true);
            $users[(int) $row['user_id']] = is_array($permissions) ? $permissions : [];
        }

        return $users;
    }

    public function save(array $data, int $processId = 0, ?int $createdBy = null, ?int $companyId = null): int
    {
        $name = trim((string) ($data['name'] ?? ''));
        if ($name === '') {
            throw new InvalidArgumentException('El nombre del proceso es obligatorio.');
        }

        $code = preg_replace('/[^a-z0-9_]/', '', strtolower(trim((string) ($data['code'] ?? ''))));
        if ($code === '') {
            $code = $this->uniqueCodeFromName($name, $processId);
        }

        $status = (string) ($data['status'] ?? 'draft');
        if (!isset(self::STATUSES[$status])) {
            $status = 'draft';
        }

        $adminAssignmentMode = (string) ($data['admin_assignment_mode'] ?? 'user');
        if (!in_array($adminAssignmentMode, ['user', 'profile'], true)) {
            $adminAssignmentMode = 'user';
        }
        $availabilityStatus = (string) ($data['availability_status'] ?? 'scheduled');
        if (!in_array($availabilityStatus, ['scheduled', 'open_now', 'closed_now'], true)) {
            $availabilityStatus = 'scheduled';
        }

        $description = trim((string) ($data['description'] ?? '')) ?: null;
        $startsAt = $this->dateTimeOrNull((string) ($data['starts_at'] ?? ''));
        $endsAt = $this->dateTimeOrNull((string) ($data['ends_at'] ?? ''));
        $allowExpiredReopen = isset($data['allow_expired_reopen']) ? 1 : 0;

        return (int) $this->db->transaction(function (Database $db) use ($processId, $code, $name, $description, $status, $adminAssignmentMode, $availabilityStatus, $startsAt, $endsAt, $allowExpiredReopen, $createdBy, $companyId): int {
            if ($processId > 0) {
                $fields = ['code = ?', 'name = ?', 'description = ?', 'status = ?'];
                $params = [$code, $name, $description, $status];
                if ($this->hasProcessAdminModeColumn()) {
                    $fields[] = 'admin_assignment_mode = ?';
                    $params[] = $adminAssignmentMode;
                }
                if ($this->hasProcessAvailabilityColumns()) {
                    $fields[] = 'availability_status = ?';
                    $fields[] = 'availability_changed_at = IF(availability_status <> ?, NOW(), availability_changed_at)';
                    $fields[] = 'availability_changed_by = IF(availability_status <> ?, ?, availability_changed_by)';
                    array_push($params, $availabilityStatus, $availabilityStatus, $availabilityStatus, $createdBy);
                }
                $fields[] = 'starts_at = ?';
                $fields[] = 'ends_at = ?';
                $fields[] = 'allow_expired_reopen = ?';
                array_push($params, $startsAt, $endsAt, $allowExpiredReopen, $processId);
                $db->execute('
                    UPDATE test_processes
                    SET ' . implode(', ', $fields) . '
                    WHERE id = ?
                ', $params);
            } else {
                $columns = ['code', 'name', 'description', 'status'];
                $params = [$code, $name, $description, $status];
                if ($this->hasProcessAdminModeColumn()) {
                    $columns[] = 'admin_assignment_mode';
                    $params[] = $adminAssignmentMode;
                }
                if ($this->hasProcessAvailabilityColumns()) {
                    $columns[] = 'availability_status';
                    $columns[] = 'availability_changed_by';
                    array_push($params, $availabilityStatus, $createdBy);
                }
                array_push($columns, 'starts_at', 'ends_at', 'allow_expired_reopen', 'created_by');
                array_push($params, $startsAt, $endsAt, $allowExpiredReopen, $createdBy);
                if ($this->hasProcessCompanyColumn()) {
                    $columns[] = 'company_id';
                    $params[] = $companyId ?: null;
                }
                $placeholders = implode(', ', array_fill(0, count($columns), '?'));
                $processId = $db->insert('
                    INSERT INTO test_processes
                        (' . implode(', ', $columns) . ')
                    VALUES (' . $placeholders . ')
                ', $params);
            }

            return $processId;
        });
    }

    public function updateAvailabilityStatus(int $processId, string $status, ?int $changedBy = null): bool
    {
        if (!$this->hasProcessAvailabilityColumns()) {
            return false;
        }
        if (!in_array($status, ['scheduled', 'open_now', 'closed_now'], true)) {
            return false;
        }

        return $this->db->execute('
            UPDATE test_processes
            SET availability_status = ?,
                availability_changed_at = NOW(),
                availability_changed_by = ?
            WHERE id = ?
        ', [$status, $changedBy, $processId]) > 0;
    }

    public function syncInstruments(int $processId, array $instrumentIds): void
    {
        $instrumentIds = array_values(array_unique(array_filter(array_map('intval', $instrumentIds), static fn(int $id): bool => $id > 0)));

        $this->db->transaction(function (Database $db) use ($processId, $instrumentIds): void {
            $existing = $this->selectedInstrumentIds($processId);
            $removed = array_values(array_diff($existing, $instrumentIds));
            if ($removed) {
                $placeholders = implode(',', array_fill(0, count($removed), '?'));
                $db->execute("
                    UPDATE test_sessions
                    SET status = 'cancelled'
                    WHERE process_id = ?
                      AND instrument_id IN ({$placeholders})
                      AND status IN ('assigned', 'in_progress')
                ", array_merge([$processId], $removed));
            }

            $db->execute('DELETE FROM test_process_instruments WHERE process_id = ?', [$processId]);
            foreach ($instrumentIds as $index => $instrumentId) {
                $db->execute('
                    INSERT INTO test_process_instruments (process_id, instrument_id, sort_order, is_required)
                    VALUES (?, ?, ?, 1)
                ', [$processId, $instrumentId, ($index + 1) * 10]);
            }
        });
    }

    public function syncFields(int $processId, array $fieldIds, array $requiredIds = []): void
    {
        $fieldIds = array_values(array_unique(array_filter(array_map('intval', $fieldIds), static fn(int $id): bool => $id >= 0)));
        $requiredSet = array_flip(array_map('intval', $requiredIds));

        $this->db->transaction(function (Database $db) use ($processId, $fieldIds, $requiredSet): void {
            $db->execute('DELETE FROM test_process_user_fields WHERE process_id = ?', [$processId]);
            foreach ($fieldIds as $index => $fieldId) {
                $db->execute('
                    INSERT INTO test_process_user_fields (process_id, field_id, is_required, show_in_process, sort_order)
                    VALUES (?, ?, ?, 1, ?)
                ', [$processId, $fieldId, isset($requiredSet[$fieldId]) ? 1 : 0, ($index + 1) * 10]);
            }
        });
    }

    public function syncAssignableProfiles(int $processId, array $profileIds): void
    {
        $profileIds = array_values(array_unique(array_filter(array_map('intval', $profileIds), static fn(int $id): bool => $id > 0)));

        $this->db->transaction(function (Database $db) use ($processId, $profileIds): void {
            $db->execute('DELETE FROM test_process_assignable_profiles WHERE process_id = ?', [$processId]);
            foreach ($profileIds as $profileId) {
                $db->execute('
                    INSERT INTO test_process_assignable_profiles (process_id, profile_id)
                    VALUES (?, ?)
                ', [$processId, $profileId]);
            }
        });
    }

    public function syncProfileAdmins(int $processId, array $profilePermissions): void
    {
        $allowed = array_keys(self::PROCESS_PERMISSIONS);

        $this->db->transaction(function (Database $db) use ($processId, $profilePermissions, $allowed): void {
            $db->execute('DELETE FROM test_process_profile_admins WHERE process_id = ?', [$processId]);
            foreach ($profilePermissions as $profileId => $permissions) {
                $profileId = (int) $profileId;
                if ($profileId <= 0 || !is_array($permissions)) {
                    continue;
                }

                $permissions = array_values(array_intersect($allowed, array_map('strval', $permissions)));
                if (!$permissions) {
                    continue;
                }

                $db->execute('
                    INSERT INTO test_process_profile_admins (process_id, profile_id, permissions)
                    VALUES (?, ?, ?)
                ', [$processId, $profileId, json_encode(array_values(array_unique($permissions)), JSON_UNESCAPED_UNICODE)]);
            }
        });
    }

    public function clearProfileAdmins(int $processId): void
    {
        $this->db->execute('DELETE FROM test_process_profile_admins WHERE process_id = ?', [$processId]);
    }

    public function syncUserAdmins(int $processId, array $userPermissions): void
    {
        if (!$this->tableExists('test_process_user_admins')) {
            return;
        }

        $allowed = array_keys(self::PROCESS_PERMISSIONS);

        $this->db->transaction(function (Database $db) use ($processId, $userPermissions, $allowed): void {
            $db->execute('DELETE FROM test_process_user_admins WHERE process_id = ?', [$processId]);
            foreach ($userPermissions as $userId => $permissions) {
                $userId = (int) $userId;
                if ($userId <= 0 || !is_array($permissions)) {
                    continue;
                }

                $permissions = array_values(array_intersect($allowed, array_map('strval', $permissions)));
                if (!$permissions) {
                    continue;
                }

                $db->execute('
                    INSERT INTO test_process_user_admins (process_id, user_id, permissions)
                    VALUES (?, ?, ?)
                ', [$processId, $userId, json_encode(array_values(array_unique($permissions)), JSON_UNESCAPED_UNICODE)]);
            }
        });
    }

    public function clearUserAdmins(int $processId): void
    {
        if (!$this->tableExists('test_process_user_admins')) {
            return;
        }

        $this->db->execute('DELETE FROM test_process_user_admins WHERE process_id = ?', [$processId]);
    }

    public function supportsUserAdminAssignments(): bool
    {
        return $this->tableExists('test_process_user_admins');
    }

    public function assignmentReviewByRut(string $rut): array
    {
        $formattedRut = UserModel::formatRut($rut);
        $user = $this->db->fetch("
            SELECT u.id, u.rut, u.name, u.email, u.profile_id, u.is_active, c.name AS company_name
            FROM {$this->coreSchema}.users u
            LEFT JOIN {$this->coreSchema}.companies c ON c.id = u.company_id
            WHERE u.rut = ?
            LIMIT 1
        ", [$formattedRut]);

        if (!$user) {
            return [
                'rut' => $formattedRut,
                'user' => null,
                'assignments' => [],
                'candidate_processes' => [],
            ];
        }

        $userId = (int) ($user['id'] ?? 0);
        $profileId = (int) ($user['profile_id'] ?? 0);
        $assignments = $this->assignmentReviewRows($userId);
        $sessions = $this->assignmentReviewSessions($userId);
        foreach ($assignments as &$assignment) {
            $processId = (int) ($assignment['process_id'] ?? 0);
            $assignment['sessions'] = $sessions[$processId] ?? [];
            $assignment['has_answers'] = (int) ($assignment['answers_count'] ?? 0) > 0;
        }
        unset($assignment);

        return [
            'rut' => $formattedRut,
            'user' => $user,
            'assignments' => $assignments,
            'candidate_processes' => $this->runningProcessesForReassignment($profileId),
        ];
    }

    public function reassignUserProcess(string $rut, int $sourceProcessId, int $targetProcessId, int $assignedBy, bool $confirmDeleteAnswers = false): array
    {
        $formattedRut = UserModel::formatRut($rut);
        if ($sourceProcessId <= 0 || $targetProcessId <= 0 || $sourceProcessId === $targetProcessId) {
            return ['ok' => false, 'message' => 'Selecciona un proceso origen y un proceso destino distintos.'];
        }

        return $this->db->transaction(function (Database $db) use ($formattedRut, $sourceProcessId, $targetProcessId, $assignedBy, $confirmDeleteAnswers): array {
            $user = $db->fetch("
                SELECT id, rut, name, profile_id
                FROM {$this->coreSchema}.users
                WHERE rut = ?
                LIMIT 1
            ", [$formattedRut]);

            if (!$user) {
                return ['ok' => false, 'message' => 'No se encontro un usuario con el RUT indicado.'];
            }

            $userId = (int) ($user['id'] ?? 0);
            $source = $db->fetch('
                SELECT pu.process_id, p.name, p.code
                FROM test_process_users pu
                JOIN test_processes p ON p.id = pu.process_id
                WHERE pu.process_id = ? AND pu.user_id = ? AND pu.status <> "cancelled"
                LIMIT 1
                FOR UPDATE
            ', [$sourceProcessId, $userId]);

            if (!$source) {
                return ['ok' => false, 'message' => 'El usuario no se encuentra asignado al proceso origen seleccionado.'];
            }

            $target = $db->fetch('
                SELECT
                    p.id,
                    p.name,
                    p.code,
                    p.status,
                    p.starts_at,
                    p.ends_at,
                    COUNT(pi.instrument_id) AS instruments_count
                FROM test_processes p
                LEFT JOIN test_process_instruments pi ON pi.process_id = p.id
                WHERE p.id = ?
                  AND p.status = "active"
                  AND p.starts_at IS NOT NULL
                  AND p.ends_at IS NOT NULL
                  AND (
                      DATE(p.starts_at) = CURDATE()
                      OR DATE(p.ends_at) = CURDATE()
                  )
                GROUP BY p.id
                LIMIT 1
            ', [$targetProcessId]);

            if (!$target || (int) ($target['instruments_count'] ?? 0) <= 0) {
                return ['ok' => false, 'message' => 'El proceso destino no corresponde al dia actual o no tiene evaluaciones configuradas.'];
            }

            $assignableProfileIds = $this->selectedAssignableProfileIds($targetProcessId);
            if (!$assignableProfileIds || !in_array((int) ($user['profile_id'] ?? 0), $assignableProfileIds, true)) {
                return ['ok' => false, 'message' => 'El perfil del usuario no esta permitido para el proceso destino seleccionado.'];
            }

            $answersRow = $db->fetch('
                SELECT COUNT(a.id) AS answers_count
                FROM test_sessions ts
                JOIN test_answers a ON a.session_id = ts.id
                WHERE ts.process_id = ? AND ts.user_id = ?
            ', [$sourceProcessId, $userId]);
            $answersCount = (int) ($answersRow['answers_count'] ?? 0);

            if ($answersCount > 0 && !$confirmDeleteAnswers) {
                return [
                    'ok' => false,
                    'requires_confirmation' => true,
                    'answers_count' => $answersCount,
                    'message' => 'El usuario tiene respuestas guardadas en el proceso origen.',
                ];
            }

            $deletedSessionsRow = $db->fetch('
                SELECT COUNT(*) AS sessions_count
                FROM test_sessions
                WHERE process_id = ? AND user_id = ?
            ', [$sourceProcessId, $userId]);
            $deletedSessions = (int) ($deletedSessionsRow['sessions_count'] ?? 0);

            $db->execute('DELETE FROM test_sessions WHERE process_id = ? AND user_id = ?', [$sourceProcessId, $userId]);
            $db->execute('DELETE FROM test_process_users WHERE process_id = ? AND user_id = ?', [$sourceProcessId, $userId]);
            $db->execute('
                INSERT INTO test_process_users (process_id, user_id, status)
                VALUES (?, ?, "assigned")
                ON DUPLICATE KEY UPDATE status = "assigned"
            ', [$targetProcessId, $userId]);

            $sessionResult = $this->createMissingSessionsForUsers($db, $targetProcessId, [$userId], $assignedBy);

            return [
                'ok' => true,
                'user_name' => (string) ($user['name'] ?? ''),
                'source_process' => (string) ($source['name'] ?? ''),
                'target_process' => (string) ($target['name'] ?? ''),
                'answers_deleted' => $answersCount,
                'sessions_deleted' => $deletedSessions,
                'sessions_created' => (int) ($sessionResult['created'] ?? 0),
                'sessions_existing' => (int) ($sessionResult['existing'] ?? 0),
            ];
        });
    }

    private function assignmentReviewRows(int $userId): array
    {
        return $this->db->fetchAll('
            SELECT
                pu.process_id,
                pu.status AS assignment_status,
                p.name AS process_name,
                p.code AS process_code,
                p.status AS process_status,
                p.starts_at,
                p.ends_at,
                COUNT(DISTINCT ts.id) AS sessions_count,
                SUM(CASE WHEN ts.status = "assigned" THEN 1 ELSE 0 END) AS assigned_sessions,
                SUM(CASE WHEN ts.status = "in_progress" THEN 1 ELSE 0 END) AS in_progress_sessions,
                SUM(CASE WHEN ts.status = "completed" THEN 1 ELSE 0 END) AS completed_sessions,
                SUM(CASE WHEN ts.status = "expired" THEN 1 ELSE 0 END) AS expired_sessions,
                COALESCE(SUM(answer_stats.answers_count), 0) AS answers_count
            FROM test_process_users pu
            JOIN test_processes p ON p.id = pu.process_id
            LEFT JOIN test_sessions ts ON ts.process_id = pu.process_id AND ts.user_id = pu.user_id
            LEFT JOIN (
                SELECT session_id, COUNT(*) AS answers_count
                FROM test_answers
                GROUP BY session_id
            ) answer_stats ON answer_stats.session_id = ts.id
            WHERE pu.user_id = ? AND pu.status <> "cancelled"
            GROUP BY pu.process_id, pu.status, p.name, p.code, p.status, p.starts_at, p.ends_at
            ORDER BY p.starts_at DESC, p.id DESC
        ', [$userId]);
    }

    private function assignmentReviewSessions(int $userId): array
    {
        $rows = $this->db->fetchAll('
            SELECT
                ts.process_id,
                ts.id,
                ts.status,
                ts.started_at,
                ts.completed_at,
                ts.expires_at,
                i.name AS instrument_name,
                i.code AS instrument_code,
                COALESCE(answer_stats.answers_count, 0) AS answers_count
            FROM test_sessions ts
            JOIN test_instruments i ON i.id = ts.instrument_id
            LEFT JOIN (
                SELECT session_id, COUNT(*) AS answers_count
                FROM test_answers
                GROUP BY session_id
            ) answer_stats ON answer_stats.session_id = ts.id
            WHERE ts.user_id = ? AND ts.process_id IS NOT NULL
            ORDER BY ts.process_id DESC, i.name ASC
        ', [$userId]);

        $byProcess = [];
        foreach ($rows as $row) {
            $byProcess[(int) ($row['process_id'] ?? 0)][] = $row;
        }

        return $byProcess;
    }

    private function runningProcessesForReassignment(int $profileId): array
    {
        if ($profileId <= 0 || !$this->tableExists('test_process_assignable_profiles')) {
            return [];
        }

        return $this->db->fetchAll('
            SELECT
                p.id,
                p.name,
                p.code,
                p.status,
                p.starts_at,
                p.ends_at,
                COUNT(pi.instrument_id) AS instruments_count
            FROM test_processes p
            JOIN test_process_instruments pi ON pi.process_id = p.id
            JOIN test_process_assignable_profiles ap ON ap.process_id = p.id AND ap.profile_id = ?
            WHERE p.status = "active"
              AND p.starts_at IS NOT NULL
              AND p.ends_at IS NOT NULL
              AND (
                  DATE(p.starts_at) = CURDATE()
                  OR DATE(p.ends_at) = CURDATE()
              )
            GROUP BY p.id, p.name, p.code, p.status, p.starts_at, p.ends_at
            ORDER BY p.starts_at ASC, p.name ASC
        ', [$profileId]);
    }

    public function assignUsers(int $processId, array $userIds, ?int $assignedBy = null): array
    {
        $userIds = array_values(array_unique(array_filter(array_map('intval', $userIds), static fn(int $id): bool => $id > 0)));
        if (!$userIds) {
            return ['created' => 0, 'existing' => 0, 'sessions_created' => 0, 'sessions_existing' => 0, 'sessions_requested' => 0];
        }

        return $this->db->transaction(function (Database $db) use ($processId, $userIds, $assignedBy): array {
            $existingUserIds = [];
            $blockedUserIds = [];
            $placeholders = implode(',', array_fill(0, count($userIds), '?'));
            $rows = $db->fetchAll("
                SELECT user_id
                FROM test_process_users
                WHERE process_id = ?
                  AND user_id IN ({$placeholders})
            ", array_merge([$processId], $userIds));
            foreach ($rows as $row) {
                $existingUserIds[(int) ($row['user_id'] ?? 0)] = true;
            }

            $blockedRows = $db->fetchAll("
                SELECT DISTINCT user_id
                FROM test_process_users
                WHERE process_id <> ?
                  AND user_id IN ({$placeholders})
                  AND status <> 'cancelled'
            ", array_merge([$processId], $userIds));
            foreach ($blockedRows as $row) {
                $blockedUserIds[(int) ($row['user_id'] ?? 0)] = true;
            }

            $assignableUserIds = array_values(array_filter($userIds, static fn(int $userId): bool => !isset($blockedUserIds[$userId])));
            foreach ($assignableUserIds as $userId) {
                $db->execute('
                    INSERT INTO test_process_users (process_id, user_id, status)
                    VALUES (?, ?, "assigned")
                    ON DUPLICATE KEY UPDATE status = "assigned"
                ', [$processId, $userId]);
            }

            $sessionResult = $this->createMissingSessionsForUsers($db, $processId, $assignableUserIds, $assignedBy);
            $existing = count($existingUserIds);

            return [
                'created' => max(0, count($assignableUserIds) - $existing),
                'existing' => $existing,
                'blocked_existing_process' => count($blockedUserIds),
                'sessions_created' => (int) $sessionResult['created'],
                'sessions_existing' => (int) $sessionResult['existing'],
                'sessions_requested' => (int) $sessionResult['requested'],
            ];
        });
    }

    public function removeUser(int $processId, int $userId): void
    {
        $this->db->transaction(function (Database $db) use ($processId, $userId): void {
            $db->execute('
                DELETE FROM test_sessions
                WHERE process_id = ? AND user_id = ?
            ', [$processId, $userId]);
            $db->execute('DELETE FROM test_process_users WHERE process_id = ? AND user_id = ?', [$processId, $userId]);
        });
    }

    public function removeAllUsers(int $processId): int
    {
        return (int) $this->db->transaction(function (Database $db) use ($processId): int {
            $countRow = $db->fetch('SELECT COUNT(*) AS total FROM test_process_users WHERE process_id = ?', [$processId]);
            $total = (int) ($countRow['total'] ?? 0);

            $db->execute('DELETE FROM test_sessions WHERE process_id = ?', [$processId]);
            $db->execute('DELETE FROM test_process_users WHERE process_id = ?', [$processId]);

            return $total;
        });
    }

    public function processHasResults(int $processId): bool
    {
        return $this->processResultCount($this->db, $processId) > 0;
    }

    public function deleteIfNoResults(int $processId): bool
    {
        return (bool) $this->db->transaction(function (Database $db) use ($processId): bool {
            if ($this->processResultCount($db, $processId) > 0) {
                return false;
            }

            $db->execute('DELETE FROM test_sessions WHERE process_id = ?', [$processId]);
            $db->execute('DELETE FROM test_process_users WHERE process_id = ?', [$processId]);
            $db->execute('DELETE FROM test_process_instruments WHERE process_id = ?', [$processId]);
            $db->execute('DELETE FROM test_process_user_fields WHERE process_id = ?', [$processId]);
            $db->execute('DELETE FROM test_process_profile_admins WHERE process_id = ?', [$processId]);
            if ($this->tableExists('test_process_user_admins')) {
                $db->execute('DELETE FROM test_process_user_admins WHERE process_id = ?', [$processId]);
            }

            if ($this->tableExists('test_process_assignable_profiles')) {
                $db->execute('DELETE FROM test_process_assignable_profiles WHERE process_id = ?', [$processId]);
            }

            return $db->execute('DELETE FROM test_processes WHERE id = ?', [$processId]) > 0;
        });
    }

    private function processResultCount(Database $db, int $processId): int
    {
        $resultConditions = [
            "ts.status IN ('completed', 'expired')",
            "(ts.score_summary IS NOT NULL AND ts.score_summary <> '' AND ts.score_summary <> '[]')",
        ];

        if ($this->tableExists('test_reports')) {
            $resultConditions[] = 'EXISTS (SELECT 1 FROM test_reports tr WHERE tr.session_id = ts.id)';
        }

        if ($this->tableExists('test_answer_scores')) {
            $resultConditions[] = 'EXISTS (SELECT 1 FROM test_answer_scores tas WHERE tas.session_id = ts.id)';
        }

        $row = $db->fetch('
            SELECT COUNT(*) AS total
            FROM test_sessions ts
            WHERE ts.process_id = ?
              AND (' . implode(' OR ', $resultConditions) . ')
        ', [$processId]);

        return (int) ($row['total'] ?? 0);
    }

    public function createMissingSessions(int $processId, ?int $assignedBy): array
    {
        $userRows = $this->db->fetchAll('
            SELECT user_id FROM test_process_users WHERE process_id = ? AND status <> "cancelled"
        ', [$processId]);
        $userIds = array_map('intval', array_column($userRows, 'user_id'));

        return $this->db->transaction(function (Database $db) use ($processId, $userIds, $assignedBy): array {
            return $this->createMissingSessionsForUsers($db, $processId, $userIds, $assignedBy);
        });
    }

    private function createMissingSessionsForUsers(Database $db, int $processId, array $userIds, ?int $assignedBy): array
    {
        $instrumentIds = $this->selectedInstrumentIds($processId);
        $userIds = array_values(array_unique(array_filter(array_map('intval', $userIds), static fn(int $id): bool => $id > 0)));
        if (!$instrumentIds || !$userIds) {
            return ['created' => 0, 'existing' => 0, 'requested' => 0];
        }

        $instrumentPlaceholders = implode(',', array_fill(0, count($instrumentIds), '?'));
        $userPlaceholders = implode(',', array_fill(0, count($userIds), '?'));
        $rows = $db->fetchAll("
            SELECT id, process_id, instrument_id, user_id
            FROM test_sessions
            WHERE instrument_id IN ({$instrumentPlaceholders})
              AND user_id IN ({$userPlaceholders})
              AND status IN ('assigned', 'in_progress', 'completed', 'expired')
              AND (process_id = ? OR process_id IS NULL)
            ORDER BY CASE WHEN process_id = ? THEN 0 ELSE 1 END ASC, id DESC
        ", array_merge($instrumentIds, $userIds, [$processId, $processId]));

        $existingByPair = [];
        foreach ($rows as $row) {
            $instrumentId = (int) ($row['instrument_id'] ?? 0);
            $userId = (int) ($row['user_id'] ?? 0);
            if ($instrumentId <= 0 || $userId <= 0) {
                continue;
            }

            $key = $instrumentId . ':' . $userId;
            if (!isset($existingByPair[$key])) {
                $existingByPair[$key] = $row;
            }
        }

        $created = 0;
        $existing = 0;
        foreach ($instrumentIds as $instrumentId) {
            foreach ($userIds as $userId) {
                $row = $existingByPair[$instrumentId . ':' . $userId] ?? null;
                if ($row) {
                    if ($row['process_id'] === null) {
                        $db->execute('
                            UPDATE test_sessions
                            SET process_id = ?
                            WHERE id = ? AND process_id IS NULL
                        ', [$processId, (int) $row['id']]);
                    }
                    $existing++;
                    continue;
                }

                $db->execute('
                    INSERT INTO test_sessions (process_id, instrument_id, user_id, assigned_by, status, control_mode)
                    SELECT ?, ?, ?, ?, "assigned", control_mode
                    FROM test_instruments
                    WHERE id = ?
                ', [$processId, $instrumentId, $userId, $assignedBy, $instrumentId]);
                $created++;
            }
        }

        return ['created' => $created, 'existing' => $existing, 'requested' => count($instrumentIds) * count($userIds)];
    }

    public function cancelSession(int $processId, int $sessionId): bool
    {
        return $this->db->execute('
            UPDATE test_sessions
            SET status = "cancelled"
            WHERE id = ? AND process_id = ? AND status IN ("assigned", "in_progress")
        ', [$sessionId, $processId]) > 0;
    }

    public function resetSession(int $processId, int $sessionId): bool
    {
        return (bool) $this->db->transaction(function (Database $db) use ($processId, $sessionId): bool {
            $session = $db->fetch('
                SELECT id
                FROM test_sessions
                WHERE id = ? AND process_id = ? AND status IN ("completed", "expired")
                LIMIT 1
            ', [$sessionId, $processId]);

            if (!$session) {
                return false;
            }

            if ($this->tableExists('test_activity_events')) {
                $db->execute('DELETE FROM test_activity_events WHERE session_id = ?', [$sessionId]);
            }

            if ($this->tableExists('test_answer_scores')) {
                $db->execute('DELETE FROM test_answer_scores WHERE session_id = ?', [$sessionId]);
            }
            $db->execute('DELETE FROM test_answers WHERE session_id = ?', [$sessionId]);

            $fields = [
                'status = "assigned"',
                'started_at = NULL',
                'completed_at = NULL',
                'expires_at = NULL',
                'score_summary = NULL',
            ];
            if ($this->hasSessionPresenceColumn()) {
                $fields[] = 'last_seen_at = NULL';
            }

            $db->execute('
                UPDATE test_sessions
                SET ' . implode(', ', $fields) . '
                WHERE id = ? AND process_id = ?
            ', [$sessionId, $processId]);

            return true;
        });
    }

    public function reopenExpiredSession(int $processId, int $sessionId, int $durationMinutes, array $authorizedBy = []): bool
    {
        $durationMinutes = max(1, min(1440, $durationMinutes));

        return (bool) $this->db->transaction(function (Database $db) use ($processId, $sessionId, $durationMinutes, $authorizedBy): bool {
            $session = $db->fetch('
                SELECT ts.id
                FROM test_sessions ts
                JOIN test_processes p ON p.id = ts.process_id
                WHERE ts.id = ?
                  AND ts.process_id = ?
                  AND ts.status = "expired"
                  AND p.allow_expired_reopen = 1
                LIMIT 1
            ', [$sessionId, $processId]);

            if (!$session) {
                return false;
            }

            if ($this->tableExists('test_answer_scores')) {
                $db->execute('DELETE FROM test_answer_scores WHERE session_id = ?', [$sessionId]);
            }

            if ($this->tableExists('test_reports')) {
                $db->execute('DELETE FROM test_reports WHERE session_id = ?', [$sessionId]);
            }

            $fields = [
                'status = "assigned"',
                'started_at = NULL',
                'completed_at = NULL',
                'expires_at = NULL',
                'reopened_duration_minutes = ?',
                'score_summary = NULL',
            ];
            if ($this->hasSessionPresenceColumn()) {
                $fields[] = 'last_seen_at = NULL';
            }

            $db->execute('
                UPDATE test_sessions
                SET ' . implode(', ', $fields) . '
                WHERE id = ? AND process_id = ?
            ', [$durationMinutes, $sessionId, $processId]);

            if ($this->tableExists('test_activity_events')) {
                $authorizedByName = trim((string) ($authorizedBy['name'] ?? ''));
                if ($authorizedByName === '' && (int) ($authorizedBy['id'] ?? 0) > 0) {
                    $authorizedByName = 'Usuario #' . (int) $authorizedBy['id'];
                }

                $metadata = [
                    'duration_minutes' => $durationMinutes,
                    'authorized_by_id' => (int) ($authorizedBy['id'] ?? 0),
                    'authorized_by_name' => $authorizedByName,
                    'authorized_by_email' => trim((string) ($authorizedBy['email'] ?? '')),
                    'authorized_by_profile' => trim((string) ($authorizedBy['profile_name'] ?? '')),
                ];

                $db->execute('
                    INSERT INTO test_activity_events (session_id, instrument_id, user_id, event_type, metadata)
                    SELECT id, instrument_id, user_id, "evaluation_reopened_by_admin", ?
                    FROM test_sessions
                    WHERE id = ?
                ', [json_encode($metadata, JSON_UNESCAPED_UNICODE), $sessionId]);
            }

            return true;
        });
    }

    public function reopenExpiredSessionsForInstrument(int $processId, int $instrumentId, int $durationMinutes, array $authorizedBy = []): array
    {
        $durationMinutes = max(1, min(1440, $durationMinutes));

        return $this->db->transaction(function (Database $db) use ($processId, $instrumentId, $durationMinutes, $authorizedBy): array {
            $process = $db->fetch('
                SELECT id, allow_expired_reopen
                FROM test_processes
                WHERE id = ?
                LIMIT 1
            ', [$processId]);

            if (!$process || (int) ($process['allow_expired_reopen'] ?? 0) !== 1) {
                return ['reopened' => 0, 'eligible' => 0, 'skipped' => 0, 'allowed' => false];
            }

            $instrumentInProcess = $db->fetch('
                SELECT 1
                FROM test_process_instruments
                WHERE process_id = ? AND instrument_id = ?
                LIMIT 1
            ', [$processId, $instrumentId]);

            if (!$instrumentInProcess) {
                return ['reopened' => 0, 'eligible' => 0, 'skipped' => 0, 'allowed' => true, 'instrument_found' => false];
            }

            $sessions = $db->fetchAll('
                SELECT id
                FROM test_sessions
                WHERE process_id = ?
                  AND instrument_id = ?
                  AND status = "expired"
                ORDER BY id ASC
                FOR UPDATE
            ', [$processId, $instrumentId]);

            if (!$sessions) {
                return ['reopened' => 0, 'eligible' => 0, 'skipped' => 0, 'allowed' => true, 'instrument_found' => true];
            }

            $sessionIds = array_map('intval', array_column($sessions, 'id'));
            $placeholders = implode(',', array_fill(0, count($sessionIds), '?'));

            if ($this->tableExists('test_answer_scores')) {
                $db->execute("DELETE FROM test_answer_scores WHERE session_id IN ({$placeholders})", $sessionIds);
            }

            if ($this->tableExists('test_reports')) {
                $db->execute("DELETE FROM test_reports WHERE session_id IN ({$placeholders})", $sessionIds);
            }

            $fields = [
                'status = "assigned"',
                'started_at = NULL',
                'completed_at = NULL',
                'expires_at = NULL',
                'reopened_duration_minutes = ?',
                'score_summary = NULL',
            ];
            if ($this->hasSessionPresenceColumn()) {
                $fields[] = 'last_seen_at = NULL';
            }

            $reopened = $db->execute('
                UPDATE test_sessions
                SET ' . implode(', ', $fields) . "
                WHERE id IN ({$placeholders})
                  AND status = \"expired\"
            ", array_merge([$durationMinutes], $sessionIds));

            if ($reopened > 0 && $this->tableExists('test_activity_events')) {
                $authorizedByName = trim((string) ($authorizedBy['name'] ?? ''));
                if ($authorizedByName === '' && (int) ($authorizedBy['id'] ?? 0) > 0) {
                    $authorizedByName = 'Usuario #' . (int) $authorizedBy['id'];
                }

                $metadata = [
                    'duration_minutes' => $durationMinutes,
                    'authorized_by_id' => (int) ($authorizedBy['id'] ?? 0),
                    'authorized_by_name' => $authorizedByName,
                    'authorized_by_email' => trim((string) ($authorizedBy['email'] ?? '')),
                    'authorized_by_profile' => trim((string) ($authorizedBy['profile_name'] ?? '')),
                    'bulk_reopen' => true,
                    'process_id' => $processId,
                    'instrument_id' => $instrumentId,
                ];

                $db->execute("
                    INSERT INTO test_activity_events (session_id, instrument_id, user_id, event_type, metadata)
                    SELECT id, instrument_id, user_id, \"evaluation_reopened_by_admin\", ?
                    FROM test_sessions
                    WHERE id IN ({$placeholders})
                ", array_merge([json_encode($metadata, JSON_UNESCAPED_UNICODE)], $sessionIds));
            }

            return [
                'reopened' => $reopened,
                'eligible' => count($sessionIds),
                'skipped' => max(0, count($sessionIds) - $reopened),
                'allowed' => true,
                'instrument_found' => true,
            ];
        });
    }

    public function processUsers(int $processId): array
    {
        $processLoginSelect = $this->hasUserLoginEventsTable()
            ? ', (
                SELECT MAX(ule.logged_at)
                FROM ' . $this->coreSchema . '.user_login_events ule
                WHERE ule.user_id = u.id
                  AND (
                      p.starts_at IS NULL
                      OR ule.logged_at >= DATE_SUB(p.starts_at, INTERVAL 30 MINUTE)
                  )
                  AND (
                      p.ends_at IS NULL
                      OR ule.logged_at <= DATE_ADD(p.ends_at, INTERVAL 30 MINUTE)
                  )
            ) AS process_window_login_at'
            : ', NULL AS process_window_login_at';

        $rows = $this->db->fetchAll("
            SELECT pu.*, u.name, u.email, u.rut, u.age, u.last_login_at, c.name AS company_name
                   {$processLoginSelect}
            FROM test_process_users pu
            JOIN {$this->coreSchema}.users u ON u.id = pu.user_id
            JOIN test_processes p ON p.id = pu.process_id
            LEFT JOIN {$this->coreSchema}.companies c ON c.id = u.company_id
            WHERE pu.process_id = ?
            ORDER BY FIELD(pu.status, 'assigned', 'in_progress', 'completed', 'cancelled'), c.name, u.name
        ", [$processId]);

        return $this->attachDynamicFields($rows);
    }

    public function processUser(int $processId, int $userId): ?array
    {
        $rows = $this->db->fetchAll("
            SELECT pu.*, u.id, u.name, u.email, u.rut, u.first_names, u.last_names, u.sex, u.birth_date, u.age, u.role, u.profile_id, u.company_id, u.is_active, c.name AS company_name
            FROM test_process_users pu
            JOIN {$this->coreSchema}.users u ON u.id = pu.user_id
            LEFT JOIN {$this->coreSchema}.companies c ON c.id = u.company_id
            WHERE pu.process_id = ? AND pu.user_id = ?
            LIMIT 1
        ", [$processId, $userId]);

        return $this->attachDynamicFields($rows)[0] ?? null;
    }

    public function processSessionForReport(int $processId, int $sessionId): ?array
    {
        return $this->db->fetch("
            SELECT
                ts.id,
                ts.process_id,
                ts.instrument_id,
                ts.user_id,
                ts.status,
                ts.completed_at,
                i.code AS instrument_code,
                i.name AS instrument_name,
                p.name AS process_name,
                p.code AS process_code,
                u.name AS user_name,
                u.email AS user_email,
                u.rut AS user_rut
            FROM test_sessions ts
            JOIN test_instruments i ON i.id = ts.instrument_id
            JOIN test_processes p ON p.id = ts.process_id
            JOIN {$this->coreSchema}.users u ON u.id = ts.user_id
            WHERE ts.process_id = ? AND ts.id = ?
            LIMIT 1
        ", [$processId, $sessionId]);
    }

    public function processDashboardSessionsForUser(int $processId, int $userId): array
    {
        return $this->db->fetchAll("
            SELECT ts.id, ts.process_id, ts.instrument_id, ts.user_id, ts.status, ts.completed_at, ts.updated_at,
                   i.name AS instrument_name, i.code AS instrument_code, u.name AS user_name, u.email AS user_email, u.rut AS user_rut,
                   0 AS activity_events_total, 0 AS activity_attention_total, 0 AS activity_risk_total, NULL AS last_activity_event_at
            FROM test_sessions ts
            JOIN test_instruments i ON i.id = ts.instrument_id
            JOIN {$this->coreSchema}.users u ON u.id = ts.user_id
            WHERE ts.process_id = ? AND ts.user_id = ?
            ORDER BY ts.instrument_id
        ", [$processId, $userId]);
    }

    public function availableUsers(int $processId): array
    {
        $rows = $this->db->fetchAll("
            SELECT u.id, u.name, u.email, u.rut, u.age, c.name AS company_name
            FROM {$this->coreSchema}.users u
            LEFT JOIN {$this->coreSchema}.companies c ON c.id = u.company_id
            WHERE u.is_active = 1
              AND NOT EXISTS (
                  SELECT 1 FROM test_process_users pu
                  WHERE pu.user_id = u.id AND pu.status <> 'cancelled'
              )
            ORDER BY c.name, u.name
        ");

        return $this->attachDynamicFields($rows);
    }

    public function availableUsersPage(int $processId, array $filters = [], int $page = 1, int $perPage = 25): array
    {
        $page = max(1, $page);
        $perPage = max(10, min(100, $perPage));
        $offset = ($page - 1) * $perPage;
        [$whereSql, $params] = $this->availableUsersWhere($processId, $filters);

        $countRow = $this->db->fetch("
            SELECT COUNT(*) AS total
            FROM {$this->coreSchema}.users u
            LEFT JOIN {$this->coreSchema}.companies c ON c.id = u.company_id
            WHERE {$whereSql}
        ", $params);
        $total = (int) ($countRow['total'] ?? 0);

        $rows = $this->db->fetchAll("
            SELECT u.id, u.name, u.email, u.rut, u.age, c.name AS company_name
            FROM {$this->coreSchema}.users u
            LEFT JOIN {$this->coreSchema}.companies c ON c.id = u.company_id
            WHERE {$whereSql}
            ORDER BY c.name, u.name
            LIMIT {$perPage} OFFSET {$offset}
        ", $params);

        return [
            'rows' => $this->attachDynamicFields($rows),
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'total_pages' => max(1, (int) ceil($total / $perPage)),
        ];
    }

    public function availableUserIds(int $processId, array $filters = []): array
    {
        [$whereSql, $params] = $this->availableUsersWhere($processId, $filters);

        return array_map('intval', array_column($this->db->fetchAll("
            SELECT u.id
            FROM {$this->coreSchema}.users u
            LEFT JOIN {$this->coreSchema}.companies c ON c.id = u.company_id
            WHERE {$whereSql}
            ORDER BY c.name, u.name
        ", $params), 'id'));
    }

    public function availableUserFilterOptions(int $processId, array $fields): array
    {
        $assignableProfileIds = $this->selectedAssignableProfileIds($processId);
        if (!$assignableProfileIds) {
            return [
                'companies' => [],
                'fields' => [],
            ];
        }
        $profileFilterSql = 'u.profile_id IN (' . implode(',', array_fill(0, count($assignableProfileIds), '?')) . ')';

        $companies = array_values(array_filter(array_map(
            static fn(array $row): string => trim((string) ($row['company_name'] ?? '')),
            $this->db->fetchAll("
                SELECT DISTINCT c.name AS company_name
                FROM {$this->coreSchema}.users u
                LEFT JOIN {$this->coreSchema}.companies c ON c.id = u.company_id
                WHERE u.is_active = 1
                  AND {$profileFilterSql}
                  AND c.name IS NOT NULL
                  AND c.name <> ''
                  AND NOT EXISTS (
                      SELECT 1 FROM test_process_users pu
                      WHERE pu.user_id = u.id AND pu.status <> 'cancelled'
                  )
                ORDER BY c.name
            ", $assignableProfileIds)
        )));

        $fieldOptions = [];
        foreach ($fields as $field) {
            $fieldId = (int) ($field['id'] ?? 0);
            $fieldKey = (string) ($field['field_key'] ?? '');
            if ($fieldKey === '') {
                continue;
            }

            if ($fieldId === 0 && $fieldKey === 'edad') {
                $rows = $this->db->fetchAll("
                    SELECT DISTINCT u.age AS value
                    FROM {$this->coreSchema}.users u
                    WHERE u.is_active = 1
                      AND {$profileFilterSql}
                      AND u.age IS NOT NULL
                      AND u.age <> ''
                      AND NOT EXISTS (
                          SELECT 1 FROM test_process_users pu
                          WHERE pu.user_id = u.id AND pu.status <> 'cancelled'
                      )
                    ORDER BY CAST(u.age AS UNSIGNED), u.age
                ", $assignableProfileIds);
            } else {
                $rows = $this->db->fetchAll("
                    SELECT DISTINCT v.value
                    FROM {$this->coreSchema}.user_field_values v
                    JOIN {$this->coreSchema}.users u ON u.id = v.user_id AND u.is_active = 1
                    WHERE v.field_id = ?
                      AND {$profileFilterSql}
                      AND v.value IS NOT NULL
                      AND v.value <> ''
                      AND NOT EXISTS (
                          SELECT 1 FROM test_process_users pu
                          WHERE pu.user_id = u.id AND pu.status <> 'cancelled'
                      )
                    ORDER BY v.value
                ", array_merge([$fieldId], $assignableProfileIds));
            }

            $fieldOptions[$fieldKey] = array_values(array_filter(array_map(
                static fn(array $row): string => trim((string) ($row['value'] ?? '')),
                $rows
            )));
        }

        return [
            'companies' => $companies,
            'fields' => $fieldOptions,
        ];
    }

    public function processSessions(int $processId): array
    {
        $activitySelect = '0 AS activity_events_total, 0 AS activity_attention_total, 0 AS activity_risk_total, NULL AS last_activity_event_at';
        $activityJoin = '';
        $params = [$processId];

        if ($this->tableExists('test_activity_events')) {
            $attentionTypes = "'tab_hidden','window_blurred','inactive_detected','fullscreen_exited'";
            $riskTypes = "'fullscreen_denied','fullscreen_failed','fullscreen_unavailable','suspicious_key_printscreen','suspicious_key_print','suspicious_key_save','suspicious_key_copy','suspicious_key_devtools','context_menu_blocked','copy_blocked','cut_blocked','paste_blocked','drag_blocked','print_blocked'";
            $activitySelect = '
                COALESCE(ae.events_total, 0) AS activity_events_total,
                COALESCE(ae.attention_total, 0) AS activity_attention_total,
                COALESCE(ae.risk_total, 0) AS activity_risk_total,
                ae.last_event_at AS last_activity_event_at
            ';
            $activityJoin = "
                LEFT JOIN (
                    SELECT
                        session_id,
                        COUNT(*) AS events_total,
                        SUM(CASE WHEN event_type IN ({$attentionTypes}) THEN 1 ELSE 0 END) AS attention_total,
                        SUM(CASE WHEN event_type IN ({$riskTypes}) THEN 1 ELSE 0 END) AS risk_total,
                        MAX(ae.created_at) AS last_event_at
                    FROM test_sessions ats
                    STRAIGHT_JOIN test_activity_events ae ON ae.session_id = ats.id
                    WHERE ats.process_id = ?
                    GROUP BY ae.session_id
                ) ae ON ae.session_id = ts.id
            ";
            $params[] = $processId;
        }

        $params[] = $processId;

        return $this->db->fetchAll("
            SELECT ts.*, i.name AS instrument_name, i.code AS instrument_code, i.duration_minutes, u.name AS user_name, u.email AS user_email, u.rut AS user_rut,
                   COALESCE(ic.items_count, 0) AS items_count,
                   COALESCE(ac.answers_count, 0) AS answers_count,
                   {$activitySelect}
            FROM test_sessions ts
            JOIN test_instruments i ON i.id = ts.instrument_id
            JOIN {$this->coreSchema}.users u ON u.id = ts.user_id
            LEFT JOIN (
                SELECT instrument_id, COUNT(*) AS items_count
                FROM test_items
                WHERE is_active = 1
                GROUP BY instrument_id
            ) ic ON ic.instrument_id = ts.instrument_id
            LEFT JOIN (
                SELECT
                    ta.session_id,
                    COUNT(DISTINCT ta.item_id) AS answers_count
                FROM test_sessions ats
                STRAIGHT_JOIN test_answers ta ON ta.session_id = ats.id
                JOIN test_items ti ON ti.id = ta.item_id AND ti.is_active = 1
                WHERE ats.process_id = ?
                  AND ta.answer_value IS NOT NULL
                  AND TRIM(ta.answer_value) <> ''
                GROUP BY ta.session_id
            ) ac ON ac.session_id = ts.id
            {$activityJoin}
            WHERE ts.process_id = ?
            ORDER BY u.name, i.name
        ", $params);
    }

    public function processDashboardSessions(int $processId): array
    {
        $activitySelect = '0 AS activity_events_total, 0 AS activity_attention_total, 0 AS activity_risk_total, NULL AS last_activity_event_at';
        $activityJoin = '';
        $params = [$processId];

        if ($this->tableExists('test_activity_events')) {
            $attentionTypes = "'tab_hidden','window_blurred','inactive_detected','fullscreen_exited'";
            $riskTypes = "'fullscreen_denied','fullscreen_failed','fullscreen_unavailable','suspicious_key_printscreen','suspicious_key_print','suspicious_key_save','suspicious_key_copy','suspicious_key_devtools','context_menu_blocked','copy_blocked','cut_blocked','paste_blocked','drag_blocked','print_blocked'";
            $activitySelect = '
                COALESCE(ae.events_total, 0) AS activity_events_total,
                COALESCE(ae.attention_total, 0) AS activity_attention_total,
                COALESCE(ae.risk_total, 0) AS activity_risk_total,
                ae.last_event_at AS last_activity_event_at
            ';
            $activityJoin = "
                LEFT JOIN (
                    SELECT
                        session_id,
                        COUNT(*) AS events_total,
                        SUM(CASE WHEN event_type IN ({$attentionTypes}) THEN 1 ELSE 0 END) AS attention_total,
                        SUM(CASE WHEN event_type IN ({$riskTypes}) THEN 1 ELSE 0 END) AS risk_total,
                        MAX(ae.created_at) AS last_event_at
                    FROM test_sessions ats
                    STRAIGHT_JOIN test_activity_events ae ON ae.session_id = ats.id
                    WHERE ats.process_id = ?
                    GROUP BY ae.session_id
                ) ae ON ae.session_id = ts.id
            ";
            array_unshift($params, $processId);
        }

        return $this->db->fetchAll("
            SELECT ts.id, ts.process_id, ts.instrument_id, ts.user_id, ts.status, ts.completed_at, ts.updated_at,
                   i.name AS instrument_name, i.code AS instrument_code, u.name AS user_name, u.email AS user_email, u.rut AS user_rut,
                   {$activitySelect}
            FROM test_sessions ts
            JOIN test_instruments i ON i.id = ts.instrument_id
            JOIN {$this->coreSchema}.users u ON u.id = ts.user_id
            {$activityJoin}
            WHERE ts.process_id = ?
            ORDER BY ts.user_id, ts.instrument_id
        ", $params);
    }

    public function summary(int $processId): array
    {
        $row = $this->db->fetch('
            SELECT
                (SELECT COUNT(*) FROM test_process_users WHERE process_id = ? AND status <> "cancelled") AS users_total,
                (SELECT COUNT(*) FROM test_process_instruments WHERE process_id = ?) AS instruments_total,
                (SELECT COUNT(*) FROM test_sessions WHERE process_id = ?) AS sessions_total,
                (SELECT COUNT(*) FROM test_sessions WHERE process_id = ? AND status = "assigned") AS assigned_total,
                (SELECT COUNT(*) FROM test_sessions WHERE process_id = ? AND status = "in_progress") AS in_progress_total,
                (SELECT COUNT(*) FROM test_sessions WHERE process_id = ? AND status = "completed") AS completed_total,
                (SELECT COUNT(*) FROM test_sessions WHERE process_id = ? AND status = "expired") AS expired_total,
                (SELECT COUNT(*) FROM test_sessions WHERE process_id = ? AND status = "cancelled") AS cancelled_total
        ', [$processId, $processId, $processId, $processId, $processId, $processId, $processId, $processId]);

        return array_map('intval', $row ?: []);
    }

    public function exportResults(int $processId): array
    {
        $process = $this->find($processId);
        if (!$process) {
            throw new RuntimeException('Proceso no encontrado.');
        }

        $answerCountsSql = '
            SELECT session_id, COUNT(*) AS answers_count
            FROM test_answers
            GROUP BY session_id
        ';
        $sessionOrderSql = 'COALESCE(ts.completed_at, ts.updated_at, ts.created_at)';
        $competingSessionOrderSql = 'COALESCE(ts2.completed_at, ts2.updated_at, ts2.created_at)';
        $sessions = $this->db->fetchAll("
            SELECT ts.*, i.code AS instrument_code, i.name AS instrument_name, u.rut AS user_rut, u.email AS user_email, u.name AS user_name,
                   COALESCE(ac.answers_count, 0) AS answers_count
            FROM test_sessions ts
            LEFT JOIN ({$answerCountsSql}) ac ON ac.session_id = ts.id
            JOIN test_instruments i ON i.id = ts.instrument_id
            JOIN {$this->coreSchema}.users u ON u.id = ts.user_id
            WHERE ts.process_id = ?
              AND ts.status IN ('completed', 'expired')
              AND NOT EXISTS (
                  SELECT 1
                  FROM test_sessions ts2
                  LEFT JOIN ({$answerCountsSql}) ac2 ON ac2.session_id = ts2.id
                  WHERE ts2.process_id = ts.process_id
                    AND ts2.user_id = ts.user_id
                    AND ts2.instrument_id = ts.instrument_id
                    AND ts2.status IN ('completed', 'expired')
                    AND (
                        COALESCE(ac2.answers_count, 0) > COALESCE(ac.answers_count, 0)
                        OR (
                            COALESCE(ac2.answers_count, 0) = COALESCE(ac.answers_count, 0)
                            AND {$competingSessionOrderSql} > {$sessionOrderSql}
                        )
                        OR (
                            COALESCE(ac2.answers_count, 0) = COALESCE(ac.answers_count, 0)
                            AND {$competingSessionOrderSql} = {$sessionOrderSql}
                            AND ts2.id > ts.id
                        )
                    )
              )
            ORDER BY u.rut, i.code, ts.id
        ", [$processId]);

        $exportSessions = [];
        foreach ($sessions as $session) {
            $sessionId = (int) ($session['id'] ?? 0);
            $exportSessions[] = [
                'user' => [
                    'rut' => (string) ($session['user_rut'] ?? ''),
                    'email' => (string) ($session['user_email'] ?? ''),
                    'name' => (string) ($session['user_name'] ?? ''),
                ],
                'instrument' => [
                    'code' => (string) ($session['instrument_code'] ?? ''),
                    'name' => (string) ($session['instrument_name'] ?? ''),
                ],
                'session' => [
                    'status' => (string) ($session['status'] ?? ''),
                    'started_at' => $session['started_at'] ?? null,
                    'completed_at' => $session['completed_at'] ?? null,
                    'expires_at' => $session['expires_at'] ?? null,
                    'reopened_duration_minutes' => $session['reopened_duration_minutes'] ?? null,
                    'last_seen_at' => $session['last_seen_at'] ?? null,
                    'score_summary' => $session['score_summary'] ?? null,
                    'answers_count' => (int) ($session['answers_count'] ?? 0),
                    'created_at' => $session['created_at'] ?? null,
                    'updated_at' => $session['updated_at'] ?? null,
                ],
                'answers' => $this->exportSessionAnswers($sessionId),
                'answer_scores' => $this->exportSessionAnswerScores($sessionId),
                'reports' => $this->exportSessionReports($sessionId),
                'activity_events' => $this->exportSessionActivityEvents($sessionId),
            ];
        }

        return [
            'type' => 'test_process_results_export',
            'schema_version' => 1,
            'exported_at' => date('c'),
            'process' => [
                'code' => (string) ($process['code'] ?? ''),
                'name' => (string) ($process['name'] ?? ''),
            ],
            'counts' => [
                'sessions' => count($exportSessions),
                'answers' => array_sum(array_map(static fn(array $session): int => count($session['answers']), $exportSessions)),
                'answer_scores' => array_sum(array_map(static fn(array $session): int => count($session['answer_scores']), $exportSessions)),
                'reports' => array_sum(array_map(static fn(array $session): int => count($session['reports']), $exportSessions)),
                'activity_events' => array_sum(array_map(static fn(array $session): int => count($session['activity_events']), $exportSessions)),
            ],
            'sessions' => $exportSessions,
        ];
    }

    public function importResults(int $processId, array $payload, ?int $importedBy = null): array
    {
        if (($payload['type'] ?? '') !== 'test_process_results_export') {
            throw new InvalidArgumentException('El archivo no corresponde a una exportacion de resultados de proceso.');
        }

        $sessions = $payload['sessions'] ?? [];
        if (!is_array($sessions)) {
            throw new InvalidArgumentException('El archivo no contiene sesiones para importar.');
        }

        $selection = $this->selectBestImportSessions($sessions);
        return $this->db->transaction(function (Database $db) use ($processId, $selection, $importedBy): array {
            $created = 0;
            $replaced = 0;
            $skipped = $selection['skipped'];

            foreach ($selection['sessions'] as $index => $entry) {
                if (!is_array($entry)) {
                    $skipped[] = ['row' => $index + 1, 'reason' => 'Entrada invalida'];
                    continue;
                }

                $rut = trim((string) ($entry['user']['rut'] ?? ''));
                $instrumentCode = trim((string) ($entry['instrument']['code'] ?? ''));
                $user = $rut !== '' ? $this->findCoreUserByRut($db, $rut) : null;
                $instrument = $instrumentCode !== '' ? $this->findInstrumentByCode($db, $instrumentCode) : null;

                if (!$user) {
                    $skipped[] = ['rut' => $rut, 'instrument' => $instrumentCode, 'reason' => 'Usuario no existe en esta maquina'];
                    continue;
                }

                if (!$instrument) {
                    $skipped[] = ['rut' => $rut, 'instrument' => $instrumentCode, 'reason' => 'Instrumento no existe en esta maquina'];
                    continue;
                }

                $sessionData = is_array($entry['session'] ?? null) ? $entry['session'] : [];
                $existing = $this->bestExistingSession($db, $processId, (int) $user['id'], (int) $instrument['id']);
                if ($existing && !$this->isImportEntryBetterThanExisting($entry, $existing)) {
                    $skipped[] = [
                        'rut' => $rut,
                        'instrument' => $instrumentCode,
                        'reason' => 'Ya existe en respaldo un resultado con igual o mayor cantidad de respuestas',
                    ];
                    continue;
                }

                $sessionId = $existing
                    ? $this->updateImportedSession($db, (int) $existing['id'], $sessionData)
                    : $this->createImportedSession($db, $processId, (int) $user['id'], (int) $instrument['id'], $sessionData, $importedBy);

                $this->replaceImportedSessionChildren($db, $sessionId);
                $this->importSessionAnswers($db, $sessionId, (int) $instrument['id'], is_array($entry['answers'] ?? null) ? $entry['answers'] : []);
                $this->importSessionAnswerScores($db, $sessionId, (int) $instrument['id'], is_array($entry['answer_scores'] ?? null) ? $entry['answer_scores'] : []);
                $this->importSessionReports($db, $sessionId, is_array($entry['reports'] ?? null) ? $entry['reports'] : []);
                $this->importSessionActivityEvents(
                    $db,
                    $sessionId,
                    (int) $instrument['id'],
                    (int) $user['id'],
                    is_array($entry['activity_events'] ?? null) ? $entry['activity_events'] : []
                );

                $db->execute('
                    INSERT INTO test_process_users (process_id, user_id, status)
                    VALUES (?, ?, "completed")
                    ON DUPLICATE KEY UPDATE status = VALUES(status)
                ', [$processId, (int) $user['id']]);

                if ($existing) {
                    $replaced++;
                } else {
                    $created++;
                }
            }

            return [
                'imported_sessions' => $created + $replaced,
                'created_sessions' => $created,
                'replaced_sessions' => $replaced,
                'skipped' => $skipped,
            ];
        });
    }

    private function exportSessionAnswers(int $sessionId): array
    {
        return $this->db->fetchAll('
            SELECT i.item_key, a.answer_value, a.score_value, a.created_at, a.updated_at
            FROM test_answers a
            JOIN test_items i ON i.id = a.item_id
            WHERE a.session_id = ?
            ORDER BY i.sort_order, i.id
        ', [$sessionId]);
    }

    private function exportSessionAnswerScores(int $sessionId): array
    {
        if (!$this->tableExists('test_answer_scores')) {
            return [];
        }

        return $this->db->fetchAll('
            SELECT i.item_key, s.scale_key, IF(r.id IS NULL, 0, 1) AS has_rule,
                   r.rule_type, r.answer_value AS rule_answer_value, r.sort_order AS rule_sort_order,
                   tas.score_value, tas.created_at
            FROM test_answer_scores tas
            JOIN test_items i ON i.id = tas.item_id
            JOIN test_scales s ON s.id = tas.scale_id
            LEFT JOIN test_item_score_rules r ON r.id = tas.rule_id
            WHERE tas.session_id = ?
            ORDER BY i.sort_order, s.sort_order, tas.id
        ', [$sessionId]);
    }

    private function exportSessionReports(int $sessionId): array
    {
        if (!$this->tableExists('test_reports')) {
            return [];
        }

        return $this->db->fetchAll('
            SELECT report_body, generated_at
            FROM test_reports
            WHERE session_id = ?
            ORDER BY generated_at
        ', [$sessionId]);
    }

    private function exportSessionActivityEvents(int $sessionId): array
    {
        if (!$this->tableExists('test_activity_events')) {
            return [];
        }

        return $this->db->fetchAll('
            SELECT ae.event_type, i.item_key, ae.block_number, ae.metadata, ae.ip_address, ae.user_agent, ae.created_at
            FROM test_activity_events ae
            LEFT JOIN test_items i ON i.id = ae.item_id
            WHERE ae.session_id = ?
            ORDER BY ae.created_at, ae.id
        ', [$sessionId]);
    }

    private function findCoreUserByRut(Database $db, string $rut): ?array
    {
        return $db->fetch("
            SELECT id, rut
            FROM {$this->coreSchema}.users
            WHERE rut = ?
            LIMIT 1
        ", [$rut]);
    }

    private function findInstrumentByCode(Database $db, string $code): ?array
    {
        return $db->fetch('
            SELECT id, code
            FROM test_instruments
            WHERE code = ?
            LIMIT 1
        ', [$code]);
    }

    private function selectBestImportSessions(array $sessions): array
    {
        $selected = [];
        $skipped = [];

        foreach ($sessions as $index => $entry) {
            if (!is_array($entry)) {
                $skipped[] = ['row' => $index + 1, 'reason' => 'Entrada invalida'];
                continue;
            }

            $rut = trim((string) ($entry['user']['rut'] ?? ''));
            $instrumentCode = trim((string) ($entry['instrument']['code'] ?? ''));
            if ($rut === '' || $instrumentCode === '') {
                $skipped[] = ['row' => $index + 1, 'reason' => 'Entrada sin RUT o codigo de instrumento'];
                continue;
            }

            $key = $rut . '|' . $instrumentCode;
            if (!isset($selected[$key]) || $this->isBetterImportSession($entry, $selected[$key]['entry'])) {
                if (isset($selected[$key])) {
                    $skipped[] = [
                        'rut' => $rut,
                        'instrument' => $instrumentCode,
                        'reason' => 'Duplicada en archivo: se reemplazo por otra entrada con mas respuestas',
                    ];
                }
                $selected[$key] = ['entry' => $entry, 'index' => $index];
                continue;
            }

            $skipped[] = [
                'rut' => $rut,
                'instrument' => $instrumentCode,
                'reason' => 'Duplicada en archivo: se conservo otra entrada con mas respuestas',
            ];
        }

        uasort($selected, static fn(array $left, array $right): int => $left['index'] <=> $right['index']);

        return [
            'sessions' => array_map(static fn(array $row): array => $row['entry'], array_values($selected)),
            'skipped' => $skipped,
        ];
    }

    private function isBetterImportSession(array $candidate, array $current): bool
    {
        $candidateAnswers = $this->importSessionAnswerCount($candidate);
        $currentAnswers = $this->importSessionAnswerCount($current);
        if ($candidateAnswers !== $currentAnswers) {
            return $candidateAnswers > $currentAnswers;
        }

        $candidateTime = $this->importSessionComparableTime($candidate);
        $currentTime = $this->importSessionComparableTime($current);
        if ($candidateTime !== $currentTime) {
            return $candidateTime > $currentTime;
        }

        return false;
    }

    private function importSessionAnswerCount(array $entry): int
    {
        if (isset($entry['session']['answers_count']) && is_numeric($entry['session']['answers_count'])) {
            return max(0, (int) $entry['session']['answers_count']);
        }

        return is_array($entry['answers'] ?? null) ? count($entry['answers']) : 0;
    }

    private function importSessionComparableTime(array $entry): int
    {
        foreach (['completed_at', 'updated_at', 'created_at', 'started_at'] as $key) {
            $timestamp = strtotime((string) ($entry['session'][$key] ?? ''));
            if ($timestamp !== false) {
                return $timestamp;
            }
        }

        return 0;
    }

    private function existingSessionComparableTime(array $session): int
    {
        foreach (['completed_at', 'updated_at', 'created_at'] as $key) {
            $timestamp = strtotime((string) ($session[$key] ?? ''));
            if ($timestamp !== false) {
                return $timestamp;
            }
        }

        return 0;
    }

    private function bestExistingSession(Database $db, int $processId, int $userId, int $instrumentId): ?array
    {
        return $db->fetch('
            SELECT ts.id, ts.completed_at, ts.updated_at, ts.created_at, COALESCE(ac.answers_count, 0) AS answers_count
            FROM test_sessions ts
            LEFT JOIN (
                SELECT session_id, COUNT(*) AS answers_count
                FROM test_answers
                GROUP BY session_id
            ) ac ON ac.session_id = ts.id
            WHERE ts.process_id = ? AND ts.user_id = ? AND ts.instrument_id = ?
            ORDER BY COALESCE(ac.answers_count, 0) DESC,
                     COALESCE(ts.completed_at, ts.updated_at, ts.created_at) DESC,
                     ts.id DESC
            LIMIT 1
        ', [$processId, $userId, $instrumentId]);
    }

    private function isImportEntryBetterThanExisting(array $entry, array $existing): bool
    {
        $incomingAnswers = $this->importSessionAnswerCount($entry);
        $existingAnswers = max(0, (int) ($existing['answers_count'] ?? 0));
        if ($incomingAnswers !== $existingAnswers) {
            return $incomingAnswers > $existingAnswers;
        }

        return $this->importSessionComparableTime($entry) > $this->existingSessionComparableTime($existing);
    }

    private function updateImportedSession(Database $db, int $sessionId, array $data): int
    {
        $params = $this->importedSessionParams($data);
        $params[] = $sessionId;
        $db->execute('
            UPDATE test_sessions
            SET status = ?, started_at = ?, completed_at = ?, expires_at = ?,
                reopened_duration_minutes = ?, last_seen_at = ?, score_summary = ?
            WHERE id = ?
        ', $params);

        return $sessionId;
    }

    private function createImportedSession(Database $db, int $processId, int $userId, int $instrumentId, array $data, ?int $importedBy): int
    {
        return $db->insert('
            INSERT INTO test_sessions
                (process_id, instrument_id, user_id, assigned_by, status, started_at, completed_at, expires_at, reopened_duration_minutes, last_seen_at, score_summary)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ', array_merge([$processId, $instrumentId, $userId, $importedBy], $this->importedSessionParams($data)));
    }

    private function importedSessionParams(array $data): array
    {
        $status = (string) ($data['status'] ?? 'completed');
        if (!in_array($status, ['completed', 'expired'], true)) {
            $status = 'completed';
        }

        $params = [
            $status,
            $this->dateOrNull($data['started_at'] ?? null),
            $this->dateOrNull($data['completed_at'] ?? null),
            $this->dateOrNull($data['expires_at'] ?? null),
            $this->unsignedOrNull($data['reopened_duration_minutes'] ?? null),
            $this->dateOrNull($data['last_seen_at'] ?? null),
            $this->textOrNull($data['score_summary'] ?? null),
        ];

        return $params;
    }

    private function replaceImportedSessionChildren(Database $db, int $sessionId): void
    {
        if ($this->tableExists('test_activity_events')) {
            $db->execute('DELETE FROM test_activity_events WHERE session_id = ?', [$sessionId]);
        }
        if ($this->tableExists('test_reports')) {
            $db->execute('DELETE FROM test_reports WHERE session_id = ?', [$sessionId]);
        }
        if ($this->tableExists('test_answer_scores')) {
            $db->execute('DELETE FROM test_answer_scores WHERE session_id = ?', [$sessionId]);
        }
        $db->execute('DELETE FROM test_answers WHERE session_id = ?', [$sessionId]);
    }

    private function importSessionAnswers(Database $db, int $sessionId, int $instrumentId, array $answers): void
    {
        foreach ($answers as $answer) {
            if (!is_array($answer)) {
                continue;
            }

            $item = $this->findItemByKey($db, $instrumentId, (string) ($answer['item_key'] ?? ''));
            if (!$item) {
                continue;
            }

            $db->execute('
                INSERT INTO test_answers (session_id, item_id, answer_value, score_value)
                VALUES (?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE answer_value = VALUES(answer_value), score_value = VALUES(score_value)
            ', [
                $sessionId,
                (int) $item['id'],
                $this->textOrNull($answer['answer_value'] ?? null),
                $this->decimalOrNull($answer['score_value'] ?? null),
            ]);
        }
    }

    private function importSessionAnswerScores(Database $db, int $sessionId, int $instrumentId, array $scores): void
    {
        if (!$this->tableExists('test_answer_scores')) {
            return;
        }

        foreach ($scores as $score) {
            if (!is_array($score)) {
                continue;
            }

            $item = $this->findItemByKey($db, $instrumentId, (string) ($score['item_key'] ?? ''));
            $scale = $this->findScaleByKey($db, $instrumentId, (string) ($score['scale_key'] ?? ''));
            if (!$item || !$scale) {
                continue;
            }

            $rule = $this->findScoreRule($db, $instrumentId, (int) $item['id'], (int) $scale['id'], $score);
            $db->execute('
                INSERT INTO test_answer_scores (session_id, item_id, scale_id, rule_id, score_value)
                VALUES (?, ?, ?, ?, ?)
            ', [
                $sessionId,
                (int) $item['id'],
                (int) $scale['id'],
                $rule ? (int) $rule['id'] : null,
                $this->decimalOrNull($score['score_value'] ?? 0) ?? 0,
            ]);
        }
    }

    private function importSessionReports(Database $db, int $sessionId, array $reports): void
    {
        if (!$this->tableExists('test_reports')) {
            return;
        }

        foreach ($reports as $report) {
            if (!is_array($report)) {
                continue;
            }

            $body = (string) ($report['report_body'] ?? '');
            if ($body === '') {
                continue;
            }

            $db->execute('
                INSERT INTO test_reports (session_id, report_body, generated_at)
                VALUES (?, ?, COALESCE(?, NOW()))
                ON DUPLICATE KEY UPDATE report_body = VALUES(report_body), generated_at = VALUES(generated_at)
            ', [$sessionId, $body, $this->dateOrNull($report['generated_at'] ?? null)]);
        }
    }

    private function importSessionActivityEvents(Database $db, int $sessionId, int $instrumentId, int $userId, array $events): void
    {
        if (!$this->tableExists('test_activity_events')) {
            return;
        }

        foreach ($events as $event) {
            if (!is_array($event)) {
                continue;
            }

            $eventType = trim((string) ($event['event_type'] ?? ''));
            if ($eventType === '') {
                continue;
            }

            $item = $this->findItemByKey($db, $instrumentId, (string) ($event['item_key'] ?? ''));
            $db->execute('
                INSERT INTO test_activity_events
                    (session_id, instrument_id, user_id, event_type, item_id, block_number, metadata, ip_address, user_agent, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, COALESCE(?, NOW()))
            ', [
                $sessionId,
                $instrumentId,
                $userId,
                $eventType,
                $item ? (int) $item['id'] : null,
                $this->unsignedOrNull($event['block_number'] ?? null),
                $this->textOrNull($event['metadata'] ?? null),
                $this->textOrNull($event['ip_address'] ?? null),
                $this->textOrNull($event['user_agent'] ?? null),
                $this->dateOrNull($event['created_at'] ?? null),
            ]);
        }
    }

    private function findItemByKey(Database $db, int $instrumentId, string $itemKey): ?array
    {
        if ($itemKey === '') {
            return null;
        }

        return $db->fetch('
            SELECT id, item_key
            FROM test_items
            WHERE instrument_id = ? AND item_key = ?
            LIMIT 1
        ', [$instrumentId, $itemKey]);
    }

    private function findScaleByKey(Database $db, int $instrumentId, string $scaleKey): ?array
    {
        if ($scaleKey === '') {
            return null;
        }

        return $db->fetch('
            SELECT id, scale_key
            FROM test_scales
            WHERE instrument_id = ? AND scale_key = ?
            LIMIT 1
        ', [$instrumentId, $scaleKey]);
    }

    private function findScoreRule(Database $db, int $instrumentId, int $itemId, int $scaleId, array $score): ?array
    {
        if ((int) ($score['has_rule'] ?? 1) !== 1) {
            return null;
        }

        $ruleType = $this->textOrNull($score['rule_type'] ?? null);
        $ruleAnswerValue = $this->textOrNull($score['rule_answer_value'] ?? null);
        $ruleSortOrder = $this->unsignedOrNull($score['rule_sort_order'] ?? null);

        return $db->fetch('
            SELECT id
            FROM test_item_score_rules
            WHERE instrument_id = ?
              AND item_id = ?
              AND scale_id = ?
              AND (? IS NULL OR rule_type = ?)
              AND ((? IS NULL AND answer_value IS NULL) OR answer_value = ?)
              AND (? IS NULL OR sort_order = ?)
            ORDER BY id ASC
            LIMIT 1
        ', [
            $instrumentId,
            $itemId,
            $scaleId,
            $ruleType,
            $ruleType,
            $ruleAnswerValue,
            $ruleAnswerValue,
            $ruleSortOrder,
            $ruleSortOrder,
        ]);
    }

    private function dateOrNull($value): ?string
    {
        $text = trim((string) $value);
        if ($text === '') {
            return null;
        }

        $timestamp = strtotime($text);
        return $timestamp === false ? null : date('Y-m-d H:i:s', $timestamp);
    }

    private function textOrNull($value): ?string
    {
        if ($value === null) {
            return null;
        }

        return (string) $value;
    }

    private function decimalOrNull($value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return is_numeric($value) ? (string) $value : null;
    }

    private function unsignedOrNull($value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return max(0, (int) $value);
    }

    private function processListSql(): string
    {
        $sessionCountsSql = '0 AS completed_sessions, 0 AS sessions_count';
        $onlineUsersSql = '0 AS online_users_count';

        if ($this->hasSessionProcessColumn()) {
            $sessionCountsSql = '
                (SELECT COUNT(*) FROM test_sessions ts WHERE ts.process_id = p.id AND ts.status IN ("completed", "expired", "in_progress")) AS completed_sessions,
                (SELECT COUNT(*) FROM test_sessions ts WHERE ts.process_id = p.id) AS sessions_count
            ';

            if ($this->hasSessionPresenceColumn()) {
                $onlineUsersSql = '
                    (
                        SELECT COUNT(DISTINCT ts.user_id)
                        FROM test_sessions ts
                        JOIN test_process_users pu ON pu.user_id = ts.user_id
                            AND pu.status <> "cancelled"
                        JOIN test_process_instruments pi ON pi.instrument_id = ts.instrument_id
                        WHERE ts.status = "in_progress"
                          AND pu.process_id = p.id
                          AND pi.process_id = p.id
                          AND (ts.process_id = p.id OR ts.process_id IS NULL)
                          AND ts.last_seen_at >= DATE_SUB(NOW(), INTERVAL 90 SECOND)
                    ) AS online_users_count
                ';
            }
        }

        return '
            SELECT p.*,
                (SELECT COUNT(*) FROM test_process_users pu WHERE pu.process_id = p.id AND pu.status <> "cancelled") AS users_count,
                (SELECT COUNT(*) FROM test_process_instruments pi WHERE pi.process_id = p.id) AS instruments_count,
                ' . $sessionCountsSql . ',
                ' . $onlineUsersSql . '
            FROM test_processes p
        ';
    }

    private function hasSessionProcessColumn(): bool
    {
        if ($this->hasSessionProcessColumn !== null) {
            return $this->hasSessionProcessColumn;
        }

        $this->hasSessionProcessColumn = $this->columnExists('test_sessions', 'process_id');
        return $this->hasSessionProcessColumn;
    }

    private function hasSessionPresenceColumn(): bool
    {
        if ($this->hasSessionPresenceColumn !== null) {
            return $this->hasSessionPresenceColumn;
        }

        $this->hasSessionPresenceColumn = $this->columnExists('test_sessions', 'last_seen_at');
        return $this->hasSessionPresenceColumn;
    }

    private function hasProcessAdminModeColumn(): bool
    {
        if ($this->hasProcessAdminModeColumn !== null) {
            return $this->hasProcessAdminModeColumn;
        }

        $this->hasProcessAdminModeColumn = $this->columnExists('test_processes', 'admin_assignment_mode');
        return $this->hasProcessAdminModeColumn;
    }

    private function hasProcessCompanyColumn(): bool
    {
        return $this->columnExists('test_processes', 'company_id');
    }

    private function companyUserScopeSql(string $alias): string
    {
        $user = current_user();
        if ($user && has_permission('manage_company_processes') && !has_permission('manage_test_processes') && (int) ($user['company_id'] ?? 0) > 0) {
            return 'AND ' . $alias . '.company_id = ?';
        }

        return '';
    }

    private function companyUserScopeParams(): array
    {
        $user = current_user();
        if ($user && has_permission('manage_company_processes') && !has_permission('manage_test_processes') && (int) ($user['company_id'] ?? 0) > 0) {
            return [(int) $user['company_id']];
        }

        return [];
    }

    private function hasProcessAvailabilityColumns(): bool
    {
        if ($this->hasProcessAvailabilityColumns !== null) {
            return $this->hasProcessAvailabilityColumns;
        }

        $this->hasProcessAvailabilityColumns = $this->columnExists('test_processes', 'availability_status')
            && $this->columnExists('test_processes', 'availability_changed_at')
            && $this->columnExists('test_processes', 'availability_changed_by');

        return $this->hasProcessAvailabilityColumns;
    }

    private function hasUserLoginEventsTable(): bool
    {
        if ($this->hasUserLoginEventsTable !== null) {
            return $this->hasUserLoginEventsTable;
        }

        $this->hasUserLoginEventsTable = $this->coreTableExists('user_login_events');
        return $this->hasUserLoginEventsTable;
    }

    private function processAdminAssignmentMode(int $processId): string
    {
        if (!$this->hasProcessAdminModeColumn()) {
            return 'profile';
        }

        $row = $this->db->fetch('SELECT admin_assignment_mode FROM test_processes WHERE id = ? LIMIT 1', [$processId]);
        $mode = (string) ($row['admin_assignment_mode'] ?? 'user');

        return in_array($mode, ['user', 'profile'], true) ? $mode : 'user';
    }

    private function columnExists(string $table, string $column): bool
    {
        try {
            $row = $this->db->fetch('
                SELECT COUNT(*) AS total
                FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE()
                  AND TABLE_NAME = ?
                  AND COLUMN_NAME = ?
            ', [$table, $column]);

            return (int) ($row['total'] ?? 0) > 0;
        } catch (Throwable $exception) {
            error_log('Test process schema check error: ' . $exception->getMessage());
            return false;
        }
    }

    private function tableExists(string $table): bool
    {
        try {
            $row = $this->db->fetch('
                SELECT COUNT(*) AS total
                FROM information_schema.TABLES
                WHERE TABLE_SCHEMA = DATABASE()
                  AND TABLE_NAME = ?
            ', [$table]);

            return (int) ($row['total'] ?? 0) > 0;
        } catch (Throwable $exception) {
            error_log('Test process schema check error: ' . $exception->getMessage());
            return false;
        }
    }

    private function coreTableExists(string $table): bool
    {
        try {
            $row = $this->db->fetch('
                SELECT COUNT(*) AS total
                FROM information_schema.TABLES
                WHERE TABLE_SCHEMA = ?
                  AND TABLE_NAME = ?
            ', [database_name('core'), $table]);

            return (int) ($row['total'] ?? 0) > 0;
        } catch (Throwable $exception) {
            error_log('Test process core schema check error: ' . $exception->getMessage());
            return false;
        }
    }

    private function isGlobalProcessAdmin(): bool
    {
        return has_permission('manage_tests') || has_permission('manage_test_processes');
    }

    private function uniqueCodeFromName(string $name, int $ignoreId = 0): string
    {
        $base = preg_replace('/[^a-z0-9_]/', '_', strtolower(iconv('UTF-8', 'ASCII//TRANSLIT', $name) ?: $name)) ?: 'proceso';
        $base = trim(preg_replace('/_+/', '_', $base) ?? 'proceso', '_') ?: 'proceso';
        $code = $base;
        $suffix = 2;
        while (true) {
            $row = $this->db->fetch('SELECT id FROM test_processes WHERE code = ? LIMIT 1', [$code]);
            if (!$row || (int) $row['id'] === $ignoreId) {
                return $code;
            }
            $code = $base . '_' . $suffix++;
        }
    }

    private function dateTimeOrNull(string $value): ?string
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        $normalized = str_replace('T', ' ', $value);
        return preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}/', $normalized)
            ? substr($normalized, 0, 16) . ':00'
            : null;
    }

    private function availableUsersWhere(int $processId, array $filters): array
    {
        $assignableProfileIds = $this->selectedAssignableProfileIds($processId);
        $where = [
            'u.is_active = 1',
            "NOT EXISTS (
                SELECT 1 FROM test_process_users pu
                WHERE pu.user_id = u.id AND pu.status <> 'cancelled'
            )",
        ];
        $params = [];

        $user = current_user();
        if ($user && has_permission('manage_company_processes') && !has_permission('manage_test_processes') && (int) ($user['company_id'] ?? 0) > 0) {
            $where[] = 'u.company_id = ?';
            $params[] = (int) $user['company_id'];
        }

        if (!$assignableProfileIds) {
            $where[] = '1 = 0';
        } else {
            $where[] = 'u.profile_id IN (' . implode(',', array_fill(0, count($assignableProfileIds), '?')) . ')';
            array_push($params, ...$assignableProfileIds);
        }

        $search = trim((string) ($filters['search'] ?? ''));
        if ($search !== '') {
            $like = '%' . $search . '%';
            $where[] = "(
                u.name LIKE ?
                OR u.email LIKE ?
                OR u.rut LIKE ?
                OR c.name LIKE ?
                OR EXISTS (
                    SELECT 1 FROM {$this->coreSchema}.user_field_values sv
                    WHERE sv.user_id = u.id AND sv.value LIKE ?
                )
            )";
            array_push($params, $like, $like, $like, $like, $like);
        }

        $company = trim((string) ($filters['company'] ?? ''));
        if ($company !== '') {
            $where[] = 'c.name = ?';
            $params[] = $company;
        }

        $fields = is_array($filters['fields'] ?? null) ? $filters['fields'] : [];
        foreach ($fields as $fieldKey => $value) {
            $fieldKey = trim((string) $fieldKey);
            $value = trim((string) $value);
            if ($fieldKey === '' || $value === '') {
                continue;
            }

            if ($fieldKey === 'edad') {
                $where[] = 'CAST(u.age AS CHAR) LIKE ?';
                $params[] = '%' . $value . '%';
                continue;
            }

            $where[] = "EXISTS (
                SELECT 1
                FROM {$this->coreSchema}.user_field_values fv
                JOIN {$this->coreSchema}.user_field_definitions fd ON fd.id = fv.field_id
                WHERE fv.user_id = u.id
                  AND fd.field_key = ?
                  AND fv.value LIKE ?
            )";
            $params[] = $fieldKey;
            $params[] = '%' . $value . '%';
        }

        return [implode("\nAND ", $where), $params];
    }

    private function attachDynamicFields(array $users): array
    {
        if (!$users) {
            return [];
        }

        $userIds = array_values(array_unique(array_map(static fn(array $user): int => (int) ($user['user_id'] ?? $user['id']), $users)));
        $placeholders = implode(',', array_fill(0, count($userIds), '?'));
        $fieldRows = $this->db->fetchAll("
            SELECT v.user_id, f.id AS field_id, f.field_key, v.value
            FROM {$this->coreSchema}.user_field_values v
            JOIN {$this->coreSchema}.user_field_definitions f ON f.id = v.field_id
            WHERE v.user_id IN ({$placeholders})
        ", $userIds);

        $fieldsByUser = [];
        foreach ($fieldRows as $row) {
            $fieldsByUser[(int) $row['user_id']][(int) $row['field_id']] = trim((string) ($row['value'] ?? ''));
            $fieldsByUser[(int) $row['user_id']][(string) $row['field_key']] = trim((string) ($row['value'] ?? ''));
        }

        foreach ($users as &$user) {
            $userId = (int) ($user['user_id'] ?? $user['id']);
            $user['dynamic_fields'] = $fieldsByUser[$userId] ?? [];
            if (trim((string) ($user['age'] ?? '')) !== '') {
                $user['dynamic_fields'][0] = trim((string) $user['age']);
                $user['dynamic_fields']['edad'] = trim((string) $user['age']);
            }
        }
        unset($user);

        return $users;
    }
}
