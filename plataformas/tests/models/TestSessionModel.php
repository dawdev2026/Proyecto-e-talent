<?php
declare(strict_types=1);

final class TestSessionModel
{
    private Database $db;
    private string $coreSchema;
    private array $normsByInstrument = [];
    private ?bool $hasUserResultVisibilityColumn = null;
    private ?bool $hasQuestionOrderModeColumn = null;
    private ?bool $hasActivityTrackingColumn = null;
    private ?bool $hasSupervisedModeColumn = null;
    private ?bool $hasShowQuestionNumbersColumn = null;
    private ?bool $hasAutoStartColumns = null;
    private ?bool $hasReopenedDurationColumn = null;
    private ?bool $hasPausedRemainingColumn = null;
    private ?bool $hasProcessAvailabilityStatusColumn = null;
    private ?bool $hasActivityEventsTable = null;

    public function __construct(?Database $db = null)
    {
        $this->db = $db ?: database('tests');
        $this->coreSchema = database_identifier('core');
    }

    public function assignableUsers(): array
    {
        return $this->db->fetchAll("
            SELECT u.id, u.name, u.email, u.role, c.name AS company_name
            FROM {$this->coreSchema}.users u
            LEFT JOIN {$this->coreSchema}.companies c ON c.id = u.company_id
            WHERE u.is_active = 1
            ORDER BY u.role, c.name, u.name
        ");
    }

    public function assignableUserFields(): array
    {
        $fields = $this->db->fetchAll("
            SELECT id, field_key, label, field_type, options, show_in_list, sort_order
            FROM {$this->coreSchema}.user_field_definitions
            WHERE is_active = 1
              AND scope_type = 'platform'
              AND scope_key = 'tests'
              AND field_key NOT IN ('edad', 'age')
            ORDER BY sort_order ASC, id ASC
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

        usort($fields, static fn(array $a, array $b): int => ((int) $a['sort_order'] <=> (int) $b['sort_order']) ?: strcmp((string) $a['label'], (string) $b['label']));

        return $fields;
    }

    public function assignableUsersForDemo(): array
    {
        $users = $this->db->fetchAll("
            SELECT u.id, u.name, u.email, u.age, c.name AS company_name
            FROM {$this->coreSchema}.users u
            LEFT JOIN {$this->coreSchema}.companies c ON c.id = u.company_id
            WHERE u.is_active = 1
            ORDER BY c.name, u.name
        ");

        if (!$users) {
            return [];
        }

        $userIds = array_map(static fn(array $user): int => (int) $user['id'], $users);
        $placeholders = implode(',', array_fill(0, count($userIds), '?'));
        $fieldRows = $this->db->fetchAll("
            SELECT v.user_id, f.field_key, v.value
            FROM {$this->coreSchema}.user_field_values v
            JOIN {$this->coreSchema}.user_field_definitions f ON f.id = v.field_id
            WHERE v.user_id IN ({$placeholders})
              AND f.scope_type = 'platform'
              AND f.scope_key = 'tests'
              AND f.field_key NOT IN ('edad', 'age')
        ", $userIds);
        $sessionRows = $this->db->fetchAll('
            SELECT ts.user_id, ts.instrument_id, ts.status, i.name AS instrument_name
            FROM test_sessions ts
            JOIN test_instruments i ON i.id = ts.instrument_id
            WHERE ts.user_id IN (' . $placeholders . ')
              AND ts.status IN ("assigned", "in_progress", "completed", "expired")
            ORDER BY ts.id DESC
        ', $userIds);

        $fieldsByUser = [];
        foreach ($fieldRows as $row) {
            $fieldsByUser[(int) $row['user_id']][(string) $row['field_key']] = trim((string) ($row['value'] ?? ''));
        }

        $sessionsByUser = [];
        $assignmentNamesByUser = [];
        foreach ($sessionRows as $row) {
            $userId = (int) $row['user_id'];
            $instrumentId = (int) $row['instrument_id'];
            if (!isset($sessionsByUser[$userId][$instrumentId])) {
                $sessionsByUser[$userId][$instrumentId] = (string) $row['status'];
                $assignmentNamesByUser[$userId][$instrumentId] = (string) $row['instrument_name'];
            }
        }

        foreach ($users as &$user) {
            $userId = (int) $user['id'];
            $user['dynamic_fields'] = $fieldsByUser[$userId] ?? [];
            $user['dynamic_fields']['edad'] = trim((string) ($user['age'] ?? ''));
            $user['test_statuses'] = $sessionsByUser[$userId] ?? [];
            $user['active_assignment_names'] = array_values($assignmentNamesByUser[$userId] ?? []);
        }
        unset($user);

        return $users;
    }

    public function instruments(): array
    {
        return $this->db->fetchAll('
            SELECT id, code, name, category, duration_minutes, status
            FROM test_instruments
            ORDER BY FIELD(status, "active", "draft", "inactive"), name
        ');
    }

    public function activeInstruments(): array
    {
        return $this->db->fetchAll('
            SELECT id, code, name, category, duration_minutes, status
            FROM test_instruments
            WHERE status = "active"
            ORDER BY name
        ');
    }

    public function dashboardUserFields(): array
    {
        $fields = $this->db->fetchAll("
            SELECT id, scope_type, scope_key, field_key, label, field_type, options, show_in_list, sort_order
            FROM {$this->coreSchema}.user_field_definitions
            WHERE is_active = 1
              AND field_key NOT IN ('rut', 'nombres', 'apellidos', 'correo', 'email', 'sexo', 'fecha_nacimiento', 'edad', 'age', 'password', 'contrasena', 'clave')
            ORDER BY scope_type ASC, scope_key ASC, sort_order ASC, label ASC
        ");

        $fields[] = [
            'id' => 0,
            'scope_type' => 'core',
            'scope_key' => 'core',
            'field_key' => 'edad',
            'label' => 'Edad',
            'field_type' => 'number',
            'options' => '',
            'show_in_list' => 1,
            'sort_order' => 40,
        ];

        return $fields;
    }

    public function dashboardFilterOptions(array $fields): array
    {
        $sessions = $this->dashboardSessions([], $fields);
        $companies = [];
        $fieldOptions = [];

        foreach ($sessions as $session) {
            $company = trim((string) ($session['company_name'] ?? ''));
            if ($company !== '') {
                $companies[$company] = $company;
            }

            foreach (($session['dynamic_fields'] ?? []) as $key => $value) {
                $value = trim((string) $value);
                if ($value !== '') {
                    $fieldOptions[$key][$value] = $value;
                }
            }
        }

        ksort($companies);
        foreach ($fieldOptions as &$values) {
            ksort($values);
        }
        unset($values);

        return [
            'companies' => $companies,
            'field_options' => $fieldOptions,
        ];
    }

    public function dashboardSessions(array $filters = [], ?array $fields = null): array
    {
        $fields ??= $this->dashboardUserFields();
        $rows = $this->db->fetchAll("
            SELECT
                ts.id,
                ts.process_id,
                ts.instrument_id,
                ts.user_id,
                ts.assigned_by,
                ts.status,
                ts.started_at,
                ts.completed_at,
                ts.expires_at,
                ts.created_at,
                ts.updated_at,
                i.name AS instrument_name,
                i.code AS instrument_code,
                u.rut AS user_rut,
                u.name AS user_name,
                u.email AS user_email,
                u.age,
                c.name AS company_name,
                p.name AS process_name,
                p.starts_at AS process_starts_at,
                p.ends_at AS process_ends_at
            FROM test_sessions ts
            JOIN test_instruments i ON i.id = ts.instrument_id
            JOIN {$this->coreSchema}.users u ON u.id = ts.user_id
            LEFT JOIN {$this->coreSchema}.companies c ON c.id = u.company_id
            LEFT JOIN test_processes p ON p.id = ts.process_id
            WHERE ts.status IN ('assigned', 'in_progress', 'completed', 'expired')
              AND i.status = 'active'
            ORDER BY FIELD(ts.status, 'assigned', 'in_progress', 'completed', 'expired'), ts.created_at DESC
        ");

        if (!$rows) {
            return [];
        }

        $userIds = array_values(array_unique(array_map(static fn(array $row): int => (int) $row['user_id'], $rows)));
        $fieldValues = $this->dashboardFieldValuesForUsers($userIds, $fields);

        foreach ($rows as &$row) {
            $userId = (int) $row['user_id'];
            $row['dynamic_fields'] = $fieldValues[$userId] ?? [];
            if (trim((string) ($row['age'] ?? '')) !== '') {
                $row['dynamic_fields']['edad'] = trim((string) $row['age']);
            }
        }
        unset($row);

        return array_values(array_filter($rows, fn(array $row): bool => $this->dashboardSessionMatchesFilters($row, $filters)));
    }

    public function finishedDashboardSessionsForInstrument(int $instrumentId, ?array $fields = null): array
    {
        if ($instrumentId <= 0) {
            return [];
        }

        $fields ??= $this->dashboardUserFields();
        $rows = $this->db->fetchAll("
            SELECT
                ts.id,
                ts.process_id,
                ts.instrument_id,
                ts.user_id,
                ts.assigned_by,
                ts.status,
                ts.started_at,
                ts.completed_at,
                ts.expires_at,
                ts.created_at,
                ts.updated_at,
                i.name AS instrument_name,
                i.code AS instrument_code,
                u.rut AS user_rut,
                u.name AS user_name,
                u.email AS user_email,
                u.age,
                c.name AS company_name,
                p.name AS process_name,
                p.starts_at AS process_starts_at,
                p.ends_at AS process_ends_at
            FROM test_sessions ts
            JOIN test_instruments i ON i.id = ts.instrument_id
            JOIN {$this->coreSchema}.users u ON u.id = ts.user_id
            LEFT JOIN {$this->coreSchema}.companies c ON c.id = u.company_id
            LEFT JOIN test_processes p ON p.id = ts.process_id
            WHERE ts.instrument_id = ?
              AND ts.status IN ('completed', 'expired')
            ORDER BY COALESCE(ts.completed_at, ts.updated_at, ts.created_at) DESC, ts.id DESC
        ", [$instrumentId]);

        if (!$rows) {
            return [];
        }

        $userIds = array_values(array_unique(array_map(static fn(array $row): int => (int) $row['user_id'], $rows)));
        $fieldValues = $this->dashboardFieldValuesForUsers($userIds, $fields);

        foreach ($rows as &$row) {
            $userId = (int) $row['user_id'];
            $row['dynamic_fields'] = $fieldValues[$userId] ?? [];
            if (trim((string) ($row['age'] ?? '')) !== '') {
                $row['dynamic_fields']['edad'] = trim((string) $row['age']);
            }
        }
        unset($row);

        return $rows;
    }

    public function dashboardSummary(array $sessions): array
    {
        $users = [
            'total' => [],
            'assigned' => [],
            'in_progress' => [],
            'finished' => [],
            'completed' => [],
            'expired' => [],
        ];

        foreach ($sessions as $session) {
            $userId = (int) $session['user_id'];
            $status = (string) $session['status'];
            $users['total'][$userId] = true;

            if (isset($users[$status])) {
                $users[$status][$userId] = true;
            }
            if (in_array($status, ['completed', 'expired'], true)) {
                $users['finished'][$userId] = true;
            }
        }

        return [
            'total_people' => count($users['total']),
            'not_started_people' => count($users['assigned']),
            'in_progress_people' => count($users['in_progress']),
            'finished_people' => count($users['finished']),
            'completed_people' => count($users['completed']),
            'expired_people' => count($users['expired']),
            'assignments_total' => count($sessions),
        ];
    }

    public function sessionsForUser(int $userId): array
    {
        $this->expireEndedProcessSessionsForUser($userId);

        $resultVisibilitySelect = $this->hasUserResultVisibilityColumn() ? 'COALESCE(ca.user_can_view_results, i.user_can_view_results) AS user_can_view_results' : '1 AS user_can_view_results';
        $activityTrackingSelect = $this->hasActivityTrackingColumn() ? 'i.track_activity_enabled' : '0 AS track_activity_enabled';
        $supervisedModeSelect = $this->hasSupervisedModeColumn() ? 'i.supervised_mode_enabled' : '0 AS supervised_mode_enabled';
        $controlModeSelect = $this->hasControlModeColumn() ? 'COALESCE(ca.control_mode, i.control_mode) AS control_mode' : 'ts.control_mode AS control_mode';
        $autoStartSelect = $this->hasAutoStartColumns()
            ? 'COALESCE(ca.auto_start_enabled, i.auto_start_enabled) AS auto_start_enabled, COALESCE(ca.auto_start_order, i.auto_start_order) AS auto_start_order'
            : '0 AS auto_start_enabled, 100 AS auto_start_order';
        $processAvailabilitySelect = $this->hasProcessAvailabilityStatusColumn() ? 'COALESCE(p.availability_status, "scheduled") AS process_availability_status' : '"scheduled" AS process_availability_status';

        $sessions = $this->db->fetchAll('
            SELECT ts.*, i.name AS instrument_name, i.code AS instrument_code, i.category,
                   COALESCE(ca.duration_minutes, i.duration_minutes) AS duration_minutes, i.instructions,
                   p.name AS process_name,
                   p.status AS process_status,
                   p.starts_at AS process_starts_at,
                   p.ends_at AS process_ends_at,
                   ' . $processAvailabilitySelect . ',
                   ' . $activityTrackingSelect . ', ' . $supervisedModeSelect . ', ' . $controlModeSelect . ', ' . $autoStartSelect . ', ' . $resultVisibilitySelect . ', COALESCE(ic.items_count, 0) AS items_count
            FROM test_sessions ts
            JOIN test_instruments i ON i.id = ts.instrument_id
            JOIN test_processes p ON p.id = ts.process_id
            JOIN ' . $this->coreSchema . '.users target_user ON target_user.id = ts.user_id
            LEFT JOIN company_test_instruments ca ON ca.company_id = p.company_id AND ca.instrument_id = i.id
            LEFT JOIN (
                SELECT instrument_id, COUNT(*) AS items_count
                FROM test_items
                WHERE is_active = 1
                GROUP BY instrument_id
            ) ic ON ic.instrument_id = i.id
            WHERE ts.user_id = ?
              AND p.company_id = target_user.company_id
            ORDER BY FIELD(ts.status, "assigned", "in_progress", "completed", "expired", "cancelled"), ts.created_at DESC
        ', [$userId]);

        foreach ($sessions as &$session) {
            $session['control_mode'] = $this->effectiveControlMode($session);
            $session['process_availability'] = $this->availabilityForSession($session);
        }
        unset($session);

        return $sessions;
    }

    public function expireEndedProcessSessionsForUser(int $userId): int
    {
        return $this->db->execute('
            UPDATE test_sessions ts
            JOIN test_processes p ON p.id = ts.process_id
            JOIN ' . $this->coreSchema . '.users target_user ON target_user.id = ts.user_id
            SET ts.status = "expired",
                ts.completed_at = COALESCE(ts.completed_at, NOW())
            WHERE ts.user_id = ?
              AND ts.status IN ("assigned", "in_progress")
              AND p.status = "active"
              AND p.company_id = target_user.company_id
              AND p.ends_at IS NOT NULL
              AND NOW() > p.ends_at
        ', [$userId]);
    }

    public function allSessions(): array
    {
        return $this->db->fetchAll("
            SELECT ts.*, i.name AS instrument_name, i.code AS instrument_code, u.rut AS user_rut, u.name AS user_name, u.email AS user_email
            FROM test_sessions ts
            JOIN test_instruments i ON i.id = ts.instrument_id
            JOIN {$this->coreSchema}.users u ON u.id = ts.user_id
            WHERE ts.status IN (\"assigned\", \"in_progress\", \"completed\", \"expired\")
            ORDER BY FIELD(ts.status, \"assigned\", \"in_progress\", \"completed\", \"expired\"), ts.created_at DESC
            LIMIT 200
        ");
    }

    public function hasFinishedActiveSessions(): bool
    {
        $row = $this->db->fetch('
            SELECT COUNT(*) AS total
            FROM test_sessions ts
            JOIN test_instruments i ON i.id = ts.instrument_id
            WHERE ts.status IN ("completed", "expired")
              AND i.status = "active"
        ');

        return (int) ($row['total'] ?? 0) > 0;
    }

    public function finishedSessionsForUserResults(int $userId, int $processId = 0): array
    {
        $processSql = $processId > 0 ? ' AND ts.process_id = ?' : '';
        $params = [$userId];
        if ($processId > 0) {
            $params[] = $processId;
        }

        return $this->db->fetchAll("
            SELECT ts.*, i.name AS instrument_name, i.code AS instrument_code, i.category,
                   u.name AS user_name, u.email AS user_email, u.age,
                   c.name AS company_name
            FROM test_sessions ts
            JOIN test_instruments i ON i.id = ts.instrument_id
            JOIN {$this->coreSchema}.users u ON u.id = ts.user_id
            LEFT JOIN {$this->coreSchema}.companies c ON c.id = u.company_id
            WHERE ts.user_id = ? {$processSql} AND " . $this->resultCompanyScopeSql('u') . "
              AND (ts.status IN ('completed', 'expired')
                   OR EXISTS (SELECT 1 FROM test_answers ta WHERE ta.session_id = ts.id))
            ORDER BY COALESCE(ts.completed_at, ts.updated_at, ts.created_at) DESC, ts.id DESC
        ", array_merge($params, $this->resultCompanyScopeParams()));
    }

    public function assign(int $instrumentId, int $userId, ?int $assignedBy): int
    {
        $existing = $this->db->fetch('
            SELECT id
            FROM test_sessions
            WHERE instrument_id = ? AND user_id = ? AND status IN ("assigned", "in_progress", "completed", "expired")
            ORDER BY id DESC
            LIMIT 1
        ', [$instrumentId, $userId]);

        if ($existing) {
            return (int) $existing['id'];
        }

        if ($this->hasControlModeColumn()) {
            return $this->db->insert('
                INSERT INTO test_sessions (instrument_id, user_id, assigned_by, status, control_mode, audio_visual_upload_failure_policy, audio_visual_interruption_policy, audio_visual_voice_policy, audio_visual_permission_policy, audio_visual_quality_profile)
                SELECT ?, ?, ?, "assigned", control_mode, audio_visual_upload_failure_policy, audio_visual_interruption_policy, audio_visual_voice_policy, audio_visual_permission_policy, audio_visual_quality_profile
                FROM test_instruments
                WHERE id = ?
            ', [$instrumentId, $userId, $assignedBy, $instrumentId]);
        }

        return $this->db->insert('
            INSERT INTO test_sessions (instrument_id, user_id, assigned_by, status)
            VALUES (?, ?, ?, "assigned")
        ', [$instrumentId, $userId, $assignedBy]);
    }

    public function assignMany(array $instrumentIds, array $userIds, ?int $assignedBy): array
    {
        $instrumentIds = array_values(array_unique(array_filter(array_map('intval', $instrumentIds), static fn(int $id): bool => $id > 0)));
        $userIds = array_values(array_unique(array_filter(array_map('intval', $userIds), static fn(int $id): bool => $id > 0)));

        if (!$instrumentIds || !$userIds) {
            return ['created' => 0, 'existing' => 0, 'requested' => 0];
        }

        $activePlaceholders = implode(',', array_fill(0, count($instrumentIds), '?'));
        $activeRows = $this->db->fetchAll("
            SELECT id
            FROM test_instruments
            WHERE status = \"active\" AND id IN ({$activePlaceholders})
        ", $instrumentIds);
        $activeInstrumentIds = array_map(static fn(array $row): int => (int) $row['id'], $activeRows);

        if (!$activeInstrumentIds) {
            return ['created' => 0, 'existing' => 0, 'requested' => 0];
        }

        $userPlaceholders = implode(',', array_fill(0, count($userIds), '?'));
        $activeUserRows = $this->db->fetchAll("
            SELECT id
            FROM {$this->coreSchema}.users
            WHERE is_active = 1 AND id IN ({$userPlaceholders})
        ", $userIds);
        $activeUserIds = array_map(static fn(array $row): int => (int) $row['id'], $activeUserRows);

        if (!$activeUserIds) {
            return ['created' => 0, 'existing' => 0, 'requested' => 0];
        }

        return $this->db->transaction(function (Database $db) use ($activeInstrumentIds, $activeUserIds, $assignedBy): array {
            $instrumentPlaceholders = implode(',', array_fill(0, count($activeInstrumentIds), '?'));
            $userPlaceholders = implode(',', array_fill(0, count($activeUserIds), '?'));
            $existingRows = $db->fetchAll("
                SELECT instrument_id, user_id
                FROM test_sessions
                WHERE instrument_id IN ({$instrumentPlaceholders})
                  AND user_id IN ({$userPlaceholders})
                  AND status IN (\"assigned\", \"in_progress\", \"completed\", \"expired\")
            ", array_merge($activeInstrumentIds, $activeUserIds));
            $existingPairs = [];
            foreach ($existingRows as $row) {
                $existingPairs[(int) $row['instrument_id'] . ':' . (int) $row['user_id']] = true;
            }

            $requested = count($activeInstrumentIds) * count($activeUserIds);
            $existing = 0;
            foreach ($activeInstrumentIds as $instrumentId) {
                foreach ($activeUserIds as $userId) {
                    if (isset($existingPairs[$instrumentId . ':' . $userId])) {
                        $existing++;
                    }
                }
            }
            $created = $requested - $existing;
            $instrumentPlaceholders = implode(',', array_fill(0, count($activeInstrumentIds), '?'));
            $userPlaceholders = implode(',', array_fill(0, count($activeUserIds), '?'));
            if ($created > 0 && $this->hasControlModeColumn()) {
                $db->execute('
                    INSERT INTO test_sessions (instrument_id, user_id, assigned_by, status, control_mode, audio_visual_upload_failure_policy, audio_visual_interruption_policy, audio_visual_voice_policy, audio_visual_permission_policy, audio_visual_quality_profile)
                    SELECT i.id, u.id, ?, "assigned", i.control_mode, i.audio_visual_upload_failure_policy, i.audio_visual_interruption_policy, i.audio_visual_voice_policy, i.audio_visual_permission_policy, i.audio_visual_quality_profile
                    FROM test_instruments i
                    CROSS JOIN ' . $this->coreSchema . '.users u
                    WHERE i.id IN (' . $instrumentPlaceholders . ') AND u.id IN (' . $userPlaceholders . ')
                      AND NOT EXISTS (
                          SELECT 1 FROM test_sessions existing
                          WHERE existing.instrument_id = i.id AND existing.user_id = u.id
                            AND existing.status IN ("assigned", "in_progress", "completed", "expired")
                      )
                ', array_merge([$assignedBy], $activeInstrumentIds, $activeUserIds));
            } elseif ($created > 0) {
                $db->execute('
                    INSERT INTO test_sessions (instrument_id, user_id, assigned_by, status)
                    SELECT i.id, u.id, ?, "assigned"
                    FROM test_instruments i
                    CROSS JOIN ' . $this->coreSchema . '.users u
                    WHERE i.id IN (' . $instrumentPlaceholders . ') AND u.id IN (' . $userPlaceholders . ')
                      AND NOT EXISTS (
                          SELECT 1 FROM test_sessions existing
                          WHERE existing.instrument_id = i.id AND existing.user_id = u.id
                            AND existing.status IN ("assigned", "in_progress", "completed", "expired")
                      )
                ', array_merge([$assignedBy], $activeInstrumentIds, $activeUserIds));
            }

            return [
                'created' => $created,
                'existing' => $existing,
                'requested' => count($activeInstrumentIds) * count($activeUserIds),
            ];
        });
    }

    public function cancelAssignment(int $sessionId): bool
    {
        return $this->db->execute('
            UPDATE test_sessions
            SET status = "cancelled"
            WHERE id = ? AND status IN ("assigned", "in_progress")
        ', [$sessionId]) > 0;
    }

    public function expireAssignment(int $sessionId): void
    {
        $this->db->execute('
            UPDATE test_sessions
            SET status = "expired"
            WHERE id = ? AND status IN ("assigned", "in_progress")
        ', [$sessionId]);
    }

    public function cancelAllAssignments(): int
    {
        return $this->db->execute('
            UPDATE test_sessions
            SET status = "cancelled"
            WHERE status IN ("assigned", "in_progress")
        ');
    }

    public function clearAllResultsAndAssignments(): int
    {
        return $this->db->execute('DELETE FROM test_sessions');
    }

    public function findForUser(int $sessionId, int $userId): ?array
    {
        $resultVisibilitySelect = $this->hasUserResultVisibilityColumn() ? 'COALESCE(ca.user_can_view_results, i.user_can_view_results) AS user_can_view_results' : '1 AS user_can_view_results';
        $questionOrderModeSelect = $this->hasQuestionOrderModeColumn() ? 'COALESCE(ca.question_order_mode, i.question_order_mode) AS question_order_mode' : '"ordered" AS question_order_mode';
        $showQuestionNumbersSelect = $this->hasShowQuestionNumbersColumn() ? 'COALESCE(ca.show_question_numbers, i.show_question_numbers) AS show_question_numbers' : '1 AS show_question_numbers';
        $activityTrackingSelect = $this->hasActivityTrackingColumn() ? 'i.track_activity_enabled' : '0 AS track_activity_enabled';
        $supervisedModeSelect = $this->hasSupervisedModeColumn() ? 'i.supervised_mode_enabled' : '0 AS supervised_mode_enabled';
        $controlModeSelect = $this->hasControlModeColumn() ? 'i.control_mode AS control_mode' : 'ts.control_mode AS control_mode';
        $autoStartSelect = $this->hasAutoStartColumns()
            ? 'COALESCE(ca.auto_start_enabled, i.auto_start_enabled) AS auto_start_enabled, COALESCE(ca.auto_start_order, i.auto_start_order) AS auto_start_order'
            : '0 AS auto_start_enabled, 100 AS auto_start_order';
        $processAvailabilitySelect = $this->hasProcessAvailabilityStatusColumn() ? 'COALESCE(p.availability_status, "scheduled") AS process_availability_status' : '"scheduled" AS process_availability_status';
        $durationSelect = $this->hasReopenedDurationColumn()
            ? 'COALESCE(ts.reopened_duration_minutes, ca.duration_minutes, i.duration_minutes) AS duration_minutes'
            : 'i.duration_minutes';

        $session = $this->db->fetch('
            SELECT ts.*, i.name AS instrument_name, i.code AS instrument_code, i.category,
                   i.description, ' . $durationSelect . ', i.instructions,
                   COALESCE(ca.use_blocks, i.use_blocks) AS use_blocks,
                   COALESCE(ca.block_size, i.block_size) AS block_size,
                   COALESCE(ca.require_block_completion, i.require_block_completion) AS require_block_completion,
                   p.name AS process_name,
                   p.status AS process_status,
                   p.starts_at AS process_starts_at,
                   p.ends_at AS process_ends_at,
                   ' . $processAvailabilitySelect . ',
                   ' . $questionOrderModeSelect . ', ' . $showQuestionNumbersSelect . ', ' . $activityTrackingSelect . ', ' . $supervisedModeSelect . ', ' . $controlModeSelect . ', ' . $autoStartSelect . ', ' . $resultVisibilitySelect . '
            FROM test_sessions ts
            JOIN test_instruments i ON i.id = ts.instrument_id
            JOIN test_processes p ON p.id = ts.process_id
            JOIN ' . $this->coreSchema . '.users target_user ON target_user.id = ts.user_id
            LEFT JOIN company_test_instruments ca ON ca.company_id = p.company_id AND ca.instrument_id = i.id
            WHERE ts.id = ? AND ts.user_id = ?
              AND p.company_id = target_user.company_id
            LIMIT 1
        ', [$sessionId, $userId]);
        if ($session) {
            $session['control_mode'] = $this->effectiveControlMode($session);
            $session['process_availability'] = $this->availabilityForSession($session);
        }

        return $session;
    }

    public function availabilitySessionForUser(int $sessionId, int $userId): ?array
    {
        $processAvailabilitySelect = $this->hasProcessAvailabilityStatusColumn() ? 'COALESCE(p.availability_status, "scheduled") AS process_availability_status' : '"scheduled" AS process_availability_status';

        $session = $this->db->fetch('
            SELECT ts.id, ts.process_id, ts.status,
                   p.status AS process_status,
                   p.starts_at AS process_starts_at,
                   p.ends_at AS process_ends_at,
                   ' . $processAvailabilitySelect . '
            FROM test_sessions ts
            JOIN test_processes p ON p.id = ts.process_id
            JOIN ' . $this->coreSchema . '.users target_user ON target_user.id = ts.user_id
            WHERE ts.id = ? AND ts.user_id = ?
              AND p.company_id = target_user.company_id
            LIMIT 1
        ', [$sessionId, $userId]);

        if ($session) {
            $session['process_availability'] = $this->availabilityForSession($session);
        }

        return $session;
    }

    public function canSaveDraftForUser(int $sessionId, int $userId): bool
    {
        return (bool) $this->db->fetch('
            SELECT ts.id
            FROM test_sessions ts
            JOIN test_processes p ON p.id = ts.process_id
            JOIN ' . $this->coreSchema . '.users target_user ON target_user.id = ts.user_id
            WHERE ts.id = ?
              AND ts.user_id = ?
              AND ts.status IN ("assigned", "in_progress")
              AND p.company_id = target_user.company_id
            LIMIT 1
        ', [$sessionId, $userId]);
    }

    public function findForAdmin(int $sessionId): ?array
    {
        $activityTrackingSelect = $this->hasActivityTrackingColumn() ? 'i.track_activity_enabled' : '0 AS track_activity_enabled';
        $supervisedModeSelect = $this->hasSupervisedModeColumn() ? 'i.supervised_mode_enabled' : '0 AS supervised_mode_enabled';
        $controlModeSelect = $this->hasControlModeColumn() ? 'ts.control_mode AS control_mode' : 'NULL AS control_mode';

        $session = $this->db->fetch("
            SELECT ts.*, i.name AS instrument_name, i.code AS instrument_code, i.category,
                   {$activityTrackingSelect}, {$supervisedModeSelect}, {$controlModeSelect},
                   u.name AS user_name, u.email AS user_email, u.rut AS user_rut,
                   p.name AS process_name, p.starts_at AS process_starts_at, p.ends_at AS process_ends_at
            FROM test_sessions ts
            JOIN test_instruments i ON i.id = ts.instrument_id
            JOIN {$this->coreSchema}.users u ON u.id = ts.user_id
            LEFT JOIN test_processes p ON p.id = ts.process_id
            WHERE ts.id = ? AND " . $this->resultCompanyScopeSql('u') . "
            LIMIT 1
        ", array_merge([$sessionId], $this->resultCompanyScopeParams()));
        if ($session) {
            $session['control_mode'] = $this->effectiveControlMode($session);
        }

        return $session;
    }

    public function availabilityForSession(array $session): array
    {
        $processId = (int) ($session['process_id'] ?? 0);
        if ($processId <= 0) {
            return [
                'allowed' => true,
                'reason' => 'no_process',
                'label' => 'Disponible',
                'remaining_seconds' => null,
                'starts_in_seconds' => null,
            ];
        }

        $now = time();
        $processStatus = (string) ($session['process_status'] ?? '');
        $availabilityStatus = (string) ($session['process_availability_status'] ?? 'scheduled');
        $startsAt = $this->timestampOrNull($session['process_starts_at'] ?? null);
        $endsAt = $this->timestampOrNull($session['process_ends_at'] ?? null);
        $remainingSeconds = $endsAt !== null ? max(0, $endsAt - $now) : null;
        $startsInSeconds = $startsAt !== null ? max(0, $startsAt - $now) : null;

        if ($processStatus !== 'active') {
            return [
                'allowed' => false,
                'reason' => 'process_not_active',
                'label' => 'Proceso no activo',
                'message' => 'El proceso no se encuentra activo.',
                'remaining_seconds' => $remainingSeconds,
                'starts_in_seconds' => $startsInSeconds,
            ];
        }

        if ($availabilityStatus === 'closed_now') {
            return [
                'allowed' => false,
                'reason' => 'closed_now',
                'label' => 'Cerrado por encargado',
                'message' => 'El proceso fue cerrado por el encargado.',
                'remaining_seconds' => $remainingSeconds,
                'starts_in_seconds' => $startsInSeconds,
            ];
        }

        if ($endsAt !== null && $now > $endsAt) {
            return [
                'allowed' => false,
                'reason' => 'process_ended',
                'label' => 'Plazo finalizado',
                'message' => 'El proceso ha finalizado. Se guardarán tus respuestas y la evidencia audiovisual antes de cerrar.',
                'remaining_seconds' => 0,
                'starts_in_seconds' => $startsInSeconds,
            ];
        }

        if ($availabilityStatus !== 'open_now' && $startsAt !== null && $now < $startsAt) {
            return [
                'allowed' => false,
                'reason' => 'not_started',
                'label' => 'Disponible desde ' . date('d/m/Y H:i', $startsAt),
                'message' => 'La evaluacion estara disponible desde ' . date('d/m/Y H:i', $startsAt) . '.',
                'remaining_seconds' => $remainingSeconds,
                'starts_in_seconds' => $startsInSeconds,
            ];
        }

        $prefix = $availabilityStatus === 'open_now' ? 'Abierto anticipadamente' : 'Disponible';
        $label = $remainingSeconds !== null
            ? $prefix . ' · quedan ' . $this->humanDuration($remainingSeconds)
            : $prefix . ' · sin cierre programado';

        return [
            'allowed' => true,
            'reason' => $availabilityStatus === 'open_now' ? 'open_now' : 'open',
            'label' => $label,
            'message' => $label,
            'remaining_seconds' => $remainingSeconds,
            'starts_in_seconds' => $startsInSeconds,
        ];
    }

    public function availabilityForProcess(int $processId): array
    {
        if ($processId <= 0) {
            return $this->availabilityForSession(['process_id' => 0]);
        }

        $process = $this->db->fetch(
            'SELECT id AS process_id, status AS process_status, starts_at AS process_starts_at, ends_at AS process_ends_at, '
            . ($this->hasProcessAvailabilityStatusColumn() ? 'COALESCE(availability_status, "scheduled")' : '"scheduled"')
            . ' AS process_availability_status FROM test_processes WHERE id = ? LIMIT 1',
            [$processId]
        );

        if (!$process) {
            return [
                'allowed' => false,
                'reason' => 'process_not_found',
                'label' => 'Proceso no disponible',
                'message' => 'El proceso no se encuentra disponible.',
                'remaining_seconds' => null,
                'starts_in_seconds' => null,
            ];
        }

        return $this->availabilityForSession($process);
    }

    public function itemsForSession(int $sessionId): array
    {
        $questionOrderModeSelect = $this->hasQuestionOrderModeColumn() ? 'COALESCE(ca.question_order_mode, i.question_order_mode) AS question_order_mode' : '"ordered" AS question_order_mode';
        $rows = $this->db->fetchAll('
            SELECT it.*, s.scale_key, s.name AS scale_name, a.answer_value, ' . $questionOrderModeSelect . '
            FROM test_sessions ts
            JOIN test_instruments i ON i.id = ts.instrument_id
            LEFT JOIN test_processes p ON p.id = ts.process_id
            LEFT JOIN company_test_instruments ca ON ca.company_id = p.company_id AND ca.instrument_id = i.id
            JOIN test_items it ON it.instrument_id = ts.instrument_id AND it.is_active = 1
            LEFT JOIN test_scales s ON s.id = it.scale_id
            LEFT JOIN test_answers a ON a.session_id = ts.id AND a.item_id = it.id
            WHERE ts.id = ?
            ORDER BY it.sort_order ASC, it.id ASC
        ', [$sessionId]);

        if (($rows[0]['question_order_mode'] ?? 'ordered') === 'random') {
            $rows = $this->randomizedSessionOrder($rows, $sessionId);
        }

        return $rows;
    }

    public function answersForSessions(array $sessionIds): array
    {
        $sessionIds = array_values(array_unique(array_filter(array_map('intval', $sessionIds), static fn(int $id): bool => $id > 0)));
        if (!$sessionIds) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($sessionIds), '?'));
        $rows = $this->db->fetchAll('
            SELECT session_id, item_id, answer_value
            FROM test_answers
            WHERE session_id IN (' . $placeholders . ')
            ORDER BY session_id ASC, item_id ASC
        ', $sessionIds);

        $answers = [];
        foreach ($rows as $row) {
            $answers[(int) $row['session_id']][(int) $row['item_id']] = (string) ($row['answer_value'] ?? '');
        }

        return $answers;
    }

    public function resultAnswerItemsForSession(int $sessionId): array
    {
        $items = $this->itemsForSession($sessionId);
        if (!$items) {
            return [];
        }

        $scoreRows = $this->db->fetchAll('
            SELECT tas.item_id, tas.scale_id, s.scale_key
            FROM test_answer_scores tas
            JOIN test_scales s ON s.id = tas.scale_id
            WHERE tas.session_id = ?
            ORDER BY tas.item_id ASC, tas.scale_id ASC
        ', [$sessionId]);
        $scalesByItem = [];

        foreach ($scoreRows as $row) {
            $itemId = (int) ($row['item_id'] ?? 0);
            if ($itemId <= 0) {
                continue;
            }

            $scaleId = (int) ($row['scale_id'] ?? 0);
            if ($scaleId > 0) {
                $scalesByItem[$itemId]['ids'][$scaleId] = $scaleId;
            }

            $scaleKey = trim((string) ($row['scale_key'] ?? ''));
            if ($scaleKey !== '') {
                $scalesByItem[$itemId]['keys'][$scaleKey] = $scaleKey;
            }
        }

        foreach ($items as &$item) {
            $itemId = (int) ($item['id'] ?? 0);
            $item['result_scale_ids'] = array_values($scalesByItem[$itemId]['ids'] ?? []);
            $item['result_scale_keys'] = array_values($scalesByItem[$itemId]['keys'] ?? []);
        }
        unset($item);

        return $items;
    }

    public function changedAnswerItemIds(int $sessionId, array $answers, array $items): array
    {
        $changed = [];
        foreach ($items as $item) {
            $itemId = (int) $item['id'];
            if (!array_key_exists($itemId, $answers)) {
                continue;
            }

            $previous = trim((string) ($item['answer_value'] ?? ''));
            $current = $this->answerValueFromPayload($answers[$itemId]);
            if ($previous !== '' && $current !== '' && $previous !== $current) {
                $changed[] = $itemId;
            }
        }

        return $changed;
    }

    public function logActivity(int $sessionId, int $userId, string $eventType, array $metadata = [], ?int $itemId = null, ?int $blockNumber = null): bool
    {
        if (!$this->hasActivityEventsTable(true)) {
            return false;
        }

        $eventType = preg_replace('/[^a-z0-9_]/', '', strtolower($eventType));
        $allowed = [
            'evaluation_opened',
            'device_snapshot',
            'evaluation_started',
            'evaluation_reopened',
            'answer_saved',
            'answer_changed',
            'draft_saved',
            'block_saved',
            'evaluation_paused',
            'evaluation_submitted',
            'evaluation_expired',
            'tab_hidden',
            'tab_visible',
            'window_blurred',
            'window_focused',
            'inactive_detected',
            'activity_resumed',
            'fullscreen_entered',
            'fullscreen_reentered',
            'fullscreen_denied',
            'fullscreen_failed',
            'fullscreen_unavailable',
            'fullscreen_exited',
            'suspicious_key_printscreen',
            'suspicious_key_print',
            'suspicious_key_save',
            'suspicious_key_copy',
            'suspicious_key_devtools',
            'context_menu_blocked',
            'copy_blocked',
            'cut_blocked',
            'paste_blocked',
            'drag_blocked',
            'print_blocked',
            'audio_visual_recording_started',
            'audio_visual_recording_interrupted',
            'audio_visual_recording_recovered',
            'audio_visual_upload_started',
            'audio_visual_upload_completed',
            'audio_visual_upload_failed',
            'audio_visual_risk',
            'audio_visual_screen_capture_completed',
            'multiple_voice_possible',
        ];
        if (!in_array($eventType, $allowed, true)) {
            return false;
        }

        $trackActivitySelect = $this->hasActivityTrackingColumn() ? 'i.track_activity_enabled' : '0 AS track_activity_enabled';
        $controlModeSelect = $this->hasControlModeColumn() ? 'ts.control_mode AS control_mode' : 'NULL AS control_mode';
        $session = $this->db->fetch('
            SELECT ts.instrument_id, ' . $trackActivitySelect . ', ' . $controlModeSelect . '
            FROM test_sessions ts
            JOIN test_instruments i ON i.id = ts.instrument_id
            WHERE ts.id = ? AND ts.user_id = ?
            LIMIT 1
        ', [$sessionId, $userId]);
        if (!$session || $this->effectiveControlMode($session) === 'off') {
            return false;
        }

        $metadataJson = null;
        if ($metadata) {
            $metadataJson = json_encode($this->sanitizeActivityMetadata($metadata), JSON_UNESCAPED_UNICODE);
            if ($metadataJson !== false && strlen($metadataJson) > 5000) {
                $metadataJson = substr($metadataJson, 0, 5000);
            }
        }

        $ipAddress = substr((string) ($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45) ?: null;
        $userAgent = substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255) ?: null;

        $this->db->execute('
            INSERT INTO test_activity_events
                (session_id, instrument_id, user_id, event_type, item_id, block_number, metadata, ip_address, user_agent)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ', [
            $sessionId,
            (int) $session['instrument_id'],
            $userId,
            $eventType,
            $itemId && $itemId > 0 ? $itemId : null,
            $blockNumber && $blockNumber > 0 ? $blockNumber : null,
            $metadataJson,
            $ipAddress,
            $userAgent,
        ]);

        return true;
    }

    public function activityForSession(int $sessionId): array
    {
        if (!$this->hasActivityEventsTable()) {
            return [];
        }

        return $this->db->fetchAll('
            SELECT *
            FROM test_activity_events
            WHERE session_id = ?
            ORDER BY created_at ASC, id ASC
        ', [$sessionId]);
    }

    public function saveAnswersForItems(int $sessionId, array $answers, array $items): void
    {
        if (!$items) {
            return;
        }

        $allowed = [];
        foreach ($items as $item) {
            $allowed[(int) $item['id']] = true;
        }

        $this->db->transaction(function (Database $db) use ($sessionId, $answers, $allowed): void {
            foreach ($allowed as $itemId => $_) {
                if (!array_key_exists($itemId, $answers)) {
                    continue;
                }

                $answer = $this->answerValueFromPayload($answers[$itemId]);
                $db->execute('
                    INSERT INTO test_answers (session_id, item_id, answer_value)
                    VALUES (?, ?, ?)
                    ON DUPLICATE KEY UPDATE answer_value = VALUES(answer_value)
                ', [$sessionId, $itemId, $answer !== '' ? $answer : null]);
            }
        });
    }

    public function saveAnswersPayloadForSession(int $sessionId, array $answers): array
    {
        $allowed = $this->answerableItemIdsForSession($sessionId);
        if (!$allowed) {
            return ['received_item_ids' => [], 'saved_item_ids' => []];
        }

        $receivedItemIds = [];
        $savedItemIds = [];
        $this->db->transaction(function (Database $db) use ($sessionId, $answers, $allowed, &$receivedItemIds, &$savedItemIds): void {
            foreach ($answers as $rawItemId => $rawAnswer) {
                $itemId = (int) $rawItemId;
                if ($itemId <= 0 || !isset($allowed[$itemId])) {
                    continue;
                }

                $answer = $this->answerValueFromPayload($rawAnswer);
                if ($answer === '') {
                    continue;
                }

                $receivedItemIds[] = $itemId;
                $db->execute('
                    INSERT INTO test_answers (session_id, item_id, answer_value)
                    VALUES (?, ?, ?)
                    ON DUPLICATE KEY UPDATE answer_value = VALUES(answer_value)
                ', [$sessionId, $itemId, $answer]);
                $savedItemIds[] = $itemId;
            }
        });

        sort($receivedItemIds);
        sort($savedItemIds);

        return [
            'received_item_ids' => array_values(array_unique($receivedItemIds)),
            'saved_item_ids' => array_values(array_unique($savedItemIds)),
        ];
    }

    private function answerableItemIdsForSession(int $sessionId): array
    {
        $rows = $this->db->fetchAll('
            SELECT it.id
            FROM test_sessions ts
            JOIN test_items it ON it.instrument_id = ts.instrument_id AND it.is_active = 1
            WHERE ts.id = ?
        ', [$sessionId]);

        $allowed = [];
        foreach ($rows as $row) {
            $itemId = (int) ($row['id'] ?? 0);
            if ($itemId > 0) {
                $allowed[$itemId] = true;
            }
        }

        return $allowed;
    }

    public function missingRequiredAnswers(array $items, array $answers = []): array
    {
        $missing = [];
        foreach ($items as $item) {
            $itemId = (int) $item['id'];
            $answer = array_key_exists($itemId, $answers)
                ? $this->answerValueFromPayload($answers[$itemId])
                : trim((string) ($item['answer_value'] ?? ''));

            if ($answer === '') {
                $missing[] = $itemId;
            }
        }

        return $missing;
    }

    public function start(int $sessionId, int $durationMinutes = 0): void
    {
        $fields = [
            'status = "in_progress"',
            'started_at = COALESCE(started_at, NOW())',
            'last_seen_at = NOW()',
        ];
        if ($this->hasPausedRemainingColumn()) {
            $fields[] = 'expires_at = CASE
                WHEN paused_remaining_seconds IS NOT NULL AND paused_remaining_seconds > 0 THEN DATE_ADD(NOW(), INTERVAL paused_remaining_seconds SECOND)
                WHEN expires_at IS NULL AND ? > 0 THEN DATE_ADD(COALESCE(started_at, NOW()), INTERVAL ? MINUTE)
                ELSE expires_at
            END';
            $fields[] = 'paused_remaining_seconds = NULL';
        } else {
            $fields[] = 'expires_at = CASE WHEN expires_at IS NULL AND ? > 0 THEN DATE_ADD(COALESCE(started_at, NOW()), INTERVAL ? MINUTE) ELSE expires_at END';
        }
        $this->db->execute('
            UPDATE test_sessions
            SET ' . implode(', ', $fields) . '
            WHERE id = ? AND status IN ("assigned", "in_progress")
        ', [$durationMinutes, $durationMinutes, $sessionId]);
    }

    public function pause(int $sessionId, int $userId, ?int $remainingSeconds): bool
    {
        $fields = ['last_seen_at = NOW()'];
        $params = [];
        if ($this->hasPausedRemainingColumn() && $remainingSeconds !== null) {
            $fields[] = 'paused_remaining_seconds = ?';
            $fields[] = 'expires_at = NULL';
            $params[] = max(0, $remainingSeconds);
        }

        $params[] = $sessionId;
        $params[] = $userId;

        return $this->db->execute('
            UPDATE test_sessions
            SET ' . implode(', ', $fields) . '
            WHERE id = ? AND user_id = ? AND status = "in_progress"
        ', $params) > 0;
    }

    public function touchPresence(int $sessionId, int $userId): bool
    {
        return $this->db->execute('
            UPDATE test_sessions
            SET last_seen_at = NOW()
            WHERE id = ? AND user_id = ? AND status = "in_progress"
        ', [$sessionId, $userId]) > 0;
    }

    public function complete(int $sessionId, array $answers, string $finalStatus = 'completed'): array
    {
        $finalStatus = in_array($finalStatus, ['completed', 'expired'], true) ? $finalStatus : 'completed';
        $items = $this->itemsForSession($sessionId);
        $instrumentId = $items ? (int) $items[0]['instrument_id'] : 0;
        $rulesByItem = $instrumentId > 0 ? $this->scoreRulesForInstrument($instrumentId) : [];
        $scalesById = $instrumentId > 0 ? $this->scalesById($instrumentId) : [];
        $formulaTerms = $instrumentId > 0 ? $this->formulaTermsForInstrument($instrumentId) : [];
        $adjustments = $instrumentId > 0 ? $this->scoreAdjustmentsForInstrument($instrumentId) : [];
        $contextValues = $adjustments ? $this->contextValuesForSession($sessionId) : [];
        $norms = $instrumentId > 0 ? $this->normsForInstrument($instrumentId) : [];
        $summary = [];

        $this->db->transaction(function (Database $db) use ($sessionId, $answers, $items, $instrumentId, $rulesByItem, $scalesById, $formulaTerms, $adjustments, $contextValues, $norms, $finalStatus, &$summary): void {
            $db->execute('DELETE FROM test_answer_scores WHERE session_id = ?', [$sessionId]);
            $scoreRows = [];
            $answerRows = [];

            foreach ($items as $item) {
                $itemId = (int) $item['id'];
                $answer = array_key_exists($itemId, $answers)
                    ? $this->answerValueFromPayload($answers[$itemId])
                    : trim((string) ($item['answer_value'] ?? ''));
                $rules = $rulesByItem[$itemId] ?? [];
                $legacyScore = $this->scoreAnswer($item, $answer);
                $answerScore = $legacyScore;

                if ($rules) {
                    $answerScore = null;
                    foreach ($rules as $rule) {
                        $score = $this->scoreRule($rule, $answer);
                        if ($score === null) {
                            continue;
                        }

                        $answerScore = ($answerScore ?? 0.0) + $score;
                        $scaleId = (int) $rule['scale_id'];
                        $scale = $scalesById[$scaleId] ?? null;
                        $scaleKey = $scale['scale_key'] ?? ('scale_' . $scaleId);

                        if (!isset($summary[$scaleKey])) {
                            $summary[$scaleKey] = $this->emptySummaryRow($scaleKey, $scale, $scaleId);
                        }

                        $summary[$scaleKey]['raw_score'] += $score;
                        $summary[$scaleKey]['score'] = $summary[$scaleKey]['raw_score'];

                        $scoreRows[] = [$sessionId, $itemId, $scaleId, (int) $rule['id'], $score];
                    }
                } else {
                    $score = $legacyScore;
                    $scaleId = !empty($item['scale_id']) ? (int) $item['scale_id'] : null;
                    $scaleKey = $item['scale_key'] ?: 'general';

                    if (!isset($summary[$scaleKey])) {
                        $summary[$scaleKey] = [
                            'scale_id' => $scaleId,
                            'scale' => $scaleKey,
                            'name' => $item['scale_name'] ?: 'General',
                            'scale_type' => 'primary',
                            'score' => 0.0,
                            'raw_score' => 0.0,
                            'adjusted_score' => null,
                            'adjustments' => [],
                            'transformed_score' => null,
                            'classification' => null,
                            'interpretation' => null,
                            'answered' => 0,
                        ];
                    }

                    if ($score !== null) {
                        $summary[$scaleKey]['raw_score'] += (float) $score;
                        $summary[$scaleKey]['score'] = $summary[$scaleKey]['raw_score'];
                    }
                }

                $answerRows[] = [$sessionId, $itemId, $answer !== '' ? $answer : null, $answerScore];

                if ($answer !== '') {
                    $this->markAnswered($summary, $rules, $item);
                }
            }

            foreach (array_chunk($scoreRows, 100) as $chunk) {
                $params = [];
                foreach ($chunk as $row) array_push($params, ...$row);
                $db->execute(
                    'INSERT INTO test_answer_scores (session_id, item_id, scale_id, rule_id, score_value) VALUES ' . implode(', ', array_fill(0, count($chunk), '(?, ?, ?, ?, ?)')),
                    $params
                );
            }
            foreach (array_chunk($answerRows, 100) as $chunk) {
                $params = [];
                foreach ($chunk as $row) array_push($params, ...$row);
                $db->execute(
                    'INSERT INTO test_answers (session_id, item_id, answer_value, score_value) VALUES ' . implode(', ', array_fill(0, count($chunk), '(?, ?, ?, ?)')) . ' ON DUPLICATE KEY UPDATE answer_value = VALUES(answer_value), score_value = VALUES(score_value)',
                    $params
                );
            }

            $summary = $this->applyNorms($instrumentId, $summary, $norms);
            $summary = $this->applyFormulaTerms($instrumentId, $summary, $formulaTerms, $scalesById);
            $summary = $this->applyScoreAdjustments($instrumentId, $summary, $adjustments, $contextValues, $scalesById);
            $summary = $this->applyNorms($instrumentId, $summary, $norms);
            $summary = array_values($summary);
            $db->execute('
                UPDATE test_sessions
                SET status = ?, completed_at = COALESCE(completed_at, NOW()), score_summary = ?
                WHERE id = ?
            ', [$finalStatus, json_encode($summary, JSON_UNESCAPED_UNICODE), $sessionId]);
        });

        return $summary;
    }

    private function scoreRulesForInstrument(int $instrumentId): array
    {
        $rules = $this->db->fetchAll('
            SELECT r.*
            FROM test_item_score_rules r
            JOIN test_items it ON it.id = r.item_id AND it.instrument_id = r.instrument_id
            JOIN test_scales s ON s.id = r.scale_id AND s.instrument_id = r.instrument_id
            WHERE r.instrument_id = ? AND r.is_active = 1
            ORDER BY r.item_id ASC, r.sort_order ASC, r.id ASC
        ', [$instrumentId]);

        $byItem = [];
        foreach ($rules as $rule) {
            $byItem[(int) $rule['item_id']][] = $rule;
        }

        return $byItem;
    }

    private function answerValueFromPayload($rawAnswer): string
    {
        return is_array($rawAnswer)
            ? implode(',', array_filter(array_map('trim', array_map('strval', $rawAnswer)), static fn(string $value): bool => $value !== ''))
            : trim((string) $rawAnswer);
    }

    private function randomizedSessionOrder(array $items, int $sessionId): array
    {
        if (count($items) <= 1) {
            return $items;
        }

        $originalIds = array_map(static fn(array $item): int => (int) $item['id'], $items);

        usort($items, static function (array $a, array $b) use ($sessionId): int {
            $left = hash('sha256', $sessionId . ':' . (int) $a['id']);
            $right = hash('sha256', $sessionId . ':' . (int) $b['id']);
            return $left <=> $right;
        });

        $randomizedIds = array_map(static fn(array $item): int => (int) $item['id'], $items);
        if ($randomizedIds === $originalIds) {
            $first = array_shift($items);
            if ($first !== null) {
                $items[] = $first;
            }
        }

        return $items;
    }

    private function scalesById(int $instrumentId): array
    {
        $rows = $this->db->fetchAll('
            SELECT id, scale_key, name, description, scale_type, scoring_method, sort_order
            FROM test_scales
            WHERE instrument_id = ?
            ORDER BY sort_order ASC, id ASC
        ', [$instrumentId]);

        $scales = [];
        foreach ($rows as $row) {
            $scales[(int) $row['id']] = $row;
        }

        return $scales;
    }

    private function formulaTermsForInstrument(int $instrumentId): array
    {
        $rows = $this->db->fetchAll('
            SELECT *
            FROM test_scale_formula_terms
            WHERE instrument_id = ?
            ORDER BY target_scale_id ASC, sort_order ASC, id ASC
        ', [$instrumentId]);

        $terms = [];
        foreach ($rows as $row) {
            $terms[(int) $row['target_scale_id']][] = $row;
        }

        return $terms;
    }

    private function scoreAdjustmentsForInstrument(int $instrumentId): array
    {
        return $this->db->fetchAll('
            SELECT *
            FROM test_score_adjustments
            WHERE instrument_id = ? AND is_active = 1
            ORDER BY sort_order ASC, id ASC
        ', [$instrumentId]);
    }

    private function contextValuesForSession(int $sessionId): array
    {
        $rows = $this->db->fetchAll("
            SELECT f.field_key, v.value
            FROM test_sessions ts
            JOIN {$this->coreSchema}.user_field_values v ON v.user_id = ts.user_id
            JOIN {$this->coreSchema}.user_field_definitions f ON f.id = v.field_id
            WHERE ts.id = ?
              AND f.scope_type = 'platform'
              AND f.scope_key = 'tests'
              AND f.field_key NOT IN ('edad', 'age')
        ", [$sessionId]);

        $values = [];
        foreach ($rows as $row) {
            $values[(string) $row['field_key']] = trim((string) ($row['value'] ?? ''));
        }

        $coreRow = $this->db->fetch("
            SELECT u.age
            FROM test_sessions ts
            JOIN {$this->coreSchema}.users u ON u.id = ts.user_id
            WHERE ts.id = ?
            LIMIT 1
        ", [$sessionId]);
        if ($coreRow && trim((string) ($coreRow['age'] ?? '')) !== '') {
            $values['edad'] = trim((string) $coreRow['age']);
        }

        return $values;
    }

    private function emptySummaryRow(string $scaleKey, ?array $scale, ?int $scaleId): array
    {
        return [
            'scale_id' => $scaleId,
            'scale' => $scaleKey,
            'name' => $scale['name'] ?? 'General',
            'scale_type' => $scale['scale_type'] ?? 'primary',
            'score' => 0.0,
            'raw_score' => 0.0,
            'adjusted_score' => null,
            'adjustments' => [],
            'transformed_score' => null,
            'classification' => null,
            'interpretation' => null,
            'answered' => 0,
        ];
    }

    private function scoreRule(array $rule, string $answer): ?float
    {
        if ($answer === '') {
            return null;
        }

        $answers = array_filter(array_map('trim', explode(',', $answer)), static fn(string $value): bool => $value !== '');
        if (!$answers) {
            return null;
        }

        $config = json_decode((string) ($rule['rule_config'] ?? ''), true);
        $config = is_array($config) ? $config : [];
        $weight = (float) ($rule['weight'] ?? 1);
        $type = (string) ($rule['rule_type'] ?? 'mapped');
        $ruleAnswer = trim((string) ($rule['answer_value'] ?? ''));
        $firstAnswer = reset($answers);

        if ($type === 'direct' && is_numeric($firstAnswer)) {
            $subtract = isset($config['subtract']) && is_numeric($config['subtract']) ? (float) $config['subtract'] : 0.0;
            return ((float) $firstAnswer - $subtract) * $weight;
        }

        if ($type === 'reverse' && is_numeric($firstAnswer)) {
            $anchor = isset($config['anchor']) && is_numeric($config['anchor']) ? (float) $config['anchor'] : 0.0;
            return ($anchor - (float) $firstAnswer) * $weight;
        }

        if ($type === 'keyed') {
            $matches = $ruleAnswer !== '' && $this->answerListContains($answers, $ruleAnswer);
            $score = $matches ? (float) ($rule['score_value'] ?? 1) : 0.0;
            return $score * $weight;
        }

        if ($type === 'mapped') {
            if ($ruleAnswer !== '' && !$this->answerListContains($answers, $ruleAnswer)) {
                return null;
            }

            if ($rule['score_value'] !== null) {
                return (float) $rule['score_value'] * $weight;
            }
        }

        return is_numeric($answer) ? (float) $answer * $weight : null;
    }

    private function markAnswered(array &$summary, array $rules, array $item): void
    {
        if (!$rules) {
            $scaleKey = $item['scale_key'] ?: 'general';
            if (isset($summary[$scaleKey])) {
                $summary[$scaleKey]['answered']++;
            }
            return;
        }

        $seen = [];
        foreach ($rules as $rule) {
            $scaleId = (int) $rule['scale_id'];
            if (isset($seen[$scaleId])) {
                continue;
            }
            $seen[$scaleId] = true;

            foreach ($summary as &$row) {
                if ((int) ($row['scale_id'] ?? 0) === $scaleId) {
                    $row['answered']++;
                    break;
                }
            }
            unset($row);
        }
    }

    private function applyFormulaTerms(int $instrumentId, array $summary, array $formulaTerms, array $scalesById): array
    {
        if ($instrumentId <= 0 || !$formulaTerms) {
            return $summary;
        }

        $summaryByScaleId = [];
        foreach ($summary as $key => $row) {
            if (!empty($row['scale_id'])) {
                $summaryByScaleId[(int) $row['scale_id']] = $key;
            }
        }

        foreach ($formulaTerms as $targetScaleId => $terms) {
            $scale = $scalesById[$targetScaleId] ?? null;
            if (!$scale) {
                continue;
            }

            $total = 0.0;
            foreach ($terms as $term) {
                if (($term['term_type'] ?? 'scale') === 'constant') {
                    $value = (float) ($term['constant_value'] ?? 0);
                } else {
                    $sourceId = (int) ($term['source_scale_id'] ?? 0);
                    $sourceKey = $summaryByScaleId[$sourceId] ?? null;
                    $sourceRow = $sourceKey !== null ? ($summary[$sourceKey] ?? null) : null;
                    $sourceField = ($term['source_score'] ?? 'raw') === 'transformed' ? 'transformed_score' : 'raw_score';
                    $value = is_array($sourceRow) && is_numeric($sourceRow[$sourceField] ?? null) ? (float) $sourceRow[$sourceField] : 0.0;
                }

                $value *= (float) ($term['weight'] ?? 1);
                $total += ($term['operation'] ?? 'add') === 'subtract' ? -$value : $value;
            }

            $total = round($total, 4);
            $scaleKey = $scale['scale_key'];
            $summary[$scaleKey] = $this->emptySummaryRow($scaleKey, $scale, $targetScaleId);
            $summary[$scaleKey]['raw_score'] = $total;
            $summary[$scaleKey]['score'] = $total;
            if (in_array($scale['scale_type'] ?? '', ['derived', 'global'], true)) {
                $summary[$scaleKey]['transformed_score'] = (string) round($total, 4);
                $summary[$scaleKey]['classification'] = $this->classificationForTransformed($summary[$scaleKey]['transformed_score']);
            }
        }

        return $summary;
    }

    private function applyScoreAdjustments(int $instrumentId, array $summary, array $adjustments, array $contextValues, array $scalesById): array
    {
        if ($instrumentId <= 0 || !$summary || !$adjustments) {
            return $summary;
        }

        foreach ($adjustments as $adjustment) {
            $scaleId = (int) ($adjustment['target_scale_id'] ?? 0);
            $scale = $scalesById[$scaleId] ?? null;
            if (!$scale) {
                continue;
            }

            $scaleKey = $scale['scale_key'];
            if (!isset($summary[$scaleKey])) {
                $summary[$scaleKey] = $this->emptySummaryRow($scaleKey, $scale, $scaleId);
            }

            $sourceKey = (string) ($adjustment['source_key'] ?? '');
            $sourceValue = $contextValues[$sourceKey] ?? null;
            if ($sourceValue === null || !$this->adjustmentMatches($adjustment, $sourceValue)) {
                continue;
            }

            $targetScore = in_array(($adjustment['target_score'] ?? 'adjusted'), ['adjusted', 'raw', 'transformed'], true)
                ? $adjustment['target_score']
                : 'adjusted';
            $operation = in_array(($adjustment['operation'] ?? 'add'), ['add', 'subtract', 'set'], true)
                ? $adjustment['operation']
                : 'add';
            $value = (float) ($adjustment['adjustment_value'] ?? 0);

            if ($targetScore === 'raw') {
                $base = (float) ($summary[$scaleKey]['raw_score'] ?? $summary[$scaleKey]['score'] ?? 0);
                $result = $this->applyAdjustmentOperation($base, $value, $operation);
                $summary[$scaleKey]['raw_score'] = $result;
                $summary[$scaleKey]['score'] = $result;
            } elseif ($targetScore === 'transformed') {
                $base = is_numeric($summary[$scaleKey]['transformed_score'] ?? null) ? (float) $summary[$scaleKey]['transformed_score'] : 0.0;
                $summary[$scaleKey]['transformed_score'] = (string) $this->applyAdjustmentOperation($base, $value, $operation);
            } else {
                $base = $summary[$scaleKey]['adjusted_score'] ?? null;
                if ($base === null) {
                    $base = (float) ($summary[$scaleKey]['raw_score'] ?? $summary[$scaleKey]['score'] ?? 0);
                }

                $result = $this->applyAdjustmentOperation((float) $base, $value, $operation);
                $summary[$scaleKey]['adjusted_score'] = $result;
                $summary[$scaleKey]['score'] = $result;
            }

            $summary[$scaleKey]['adjustments'][] = [
                'label' => (string) ($adjustment['label'] ?? 'Ajuste'),
                'source_key' => $sourceKey,
                'source_value' => $sourceValue,
                'operation' => $operation,
                'value' => $value,
                'target_score' => $targetScore,
            ];
        }

        return $summary;
    }

    private function adjustmentMatches(array $adjustment, string $sourceValue): bool
    {
        $text = trim($sourceValue);
        if ($text === '') {
            return false;
        }

        $expectedText = trim((string) ($adjustment['source_value_text'] ?? ''));
        if ($expectedText !== '' && $this->lowerText($expectedText) !== $this->lowerText($text)) {
            return false;
        }

        $numeric = is_numeric(str_replace(',', '.', $text)) ? (float) str_replace(',', '.', $text) : null;
        if ($adjustment['source_value_min'] !== null) {
            if ($numeric === null || $numeric < (float) $adjustment['source_value_min']) {
                return false;
            }
        }

        if ($adjustment['source_value_max'] !== null) {
            if ($numeric === null || $numeric > (float) $adjustment['source_value_max']) {
                return false;
            }
        }

        return true;
    }

    private function applyAdjustmentOperation(float $base, float $value, string $operation): float
    {
        if ($operation === 'subtract') {
            return round($base - $value, 4);
        }

        if ($operation === 'set') {
            return round($value, 4);
        }

        return round($base + $value, 4);
    }

    private function lowerText(string $value): string
    {
        return function_exists('mb_strtolower')
            ? mb_strtolower($value, 'UTF-8')
            : strtolower($value);
    }

    private function answerListContains(array $answers, string $expected): bool
    {
        foreach ($answers as $answer) {
            if ($this->answerValueMatches((string) $answer, $expected)) {
                return true;
            }
        }

        return false;
    }

    private function answerValueMatches(string $answer, string $expected): bool
    {
        return $this->normalizeAnswerValue($answer) === $this->normalizeAnswerValue($expected);
    }

    private function normalizeAnswerValue(string $value): string
    {
        $value = trim($this->lowerText($value));
        $value = strtr($value, [
            'á' => 'a',
            'é' => 'e',
            'í' => 'i',
            'ó' => 'o',
            'ú' => 'u',
            'ü' => 'u',
            'ñ' => 'n',
        ]);
        $value = preg_replace('/\s+/', ' ', $value) ?? $value;
        if (preg_match('/^-?\d+,\d+$/', $value)) {
            $value = str_replace(',', '.', $value);
        }

        return $value;
    }

    private function applyNorms(int $instrumentId, array $summary, array $norms): array
    {
        if ($instrumentId <= 0 || !$summary || !$norms) {
            return $summary;
        }

        foreach ($summary as &$row) {
            $scaleId = !empty($row['scale_id']) ? (int) $row['scale_id'] : null;
            $norm = $this->matchNorm($norms, $scaleId, $row);

            if (!$norm) {
                continue;
            }

            $row['transformed_score'] = $norm['transformed_score'];
            $row['percentile'] = $norm['percentile'];
            $row['classification'] = $norm['norm_group'] ?: $this->classificationForTransformed($norm['transformed_score']);
            $row['interpretation'] = $norm['interpretation'];
        }
        unset($row);

        return $summary;
    }

    private function normsForInstrument(int $instrumentId): array
    {
        if (isset($this->normsByInstrument[$instrumentId])) {
            return $this->normsByInstrument[$instrumentId];
        }

        $this->normsByInstrument[$instrumentId] = $this->db->fetchAll('
            SELECT scale_id, norm_group, score_source, raw_min, raw_max, transformed_score, percentile, interpretation
            FROM test_norms
            WHERE instrument_id = ?
            ORDER BY norm_group ASC, id ASC
        ', [$instrumentId]);

        return $this->normsByInstrument[$instrumentId];
    }

    private function matchNorm(array $norms, ?int $scaleId, array $row): ?array
    {
        foreach ($norms as $norm) {
            $normScaleId = $norm['scale_id'] !== null ? (int) $norm['scale_id'] : null;
            if ($normScaleId !== $scaleId) {
                continue;
            }

            $scoreSource = $norm['score_source'] ?? 'raw';
            $score = $row['raw_score'] ?? $row['score'] ?? 0;
            if ($scoreSource === 'adjusted') {
                $score = $row['adjusted_score'] ?? $row['score'] ?? $row['raw_score'] ?? 0;
            } elseif ($scoreSource === 'transformed') {
                $score = $row['transformed_score'] ?? 0;
            }

            if (!is_numeric($score)) {
                continue;
            }

            $numericScore = (float) $score;
            if ($norm['raw_min'] !== null && (float) $norm['raw_min'] > $numericScore) {
                continue;
            }

            if ($norm['raw_max'] !== null && (float) $norm['raw_max'] < $numericScore) {
                continue;
            }

            return $norm;
        }

        return null;
    }

    private function classificationForTransformed(?string $transformedScore): ?string
    {
        if ($transformedScore === null || !is_numeric($transformedScore)) {
            return null;
        }

        $score = (float) $transformedScore;
        if ($score < 4.1) {
            return 'Bajo';
        }
        if ($score >= 7.5) {
            return 'Alto';
        }

        return 'Promedio';
    }

    public function scoreAnswer(array $item, string $answer): ?float
    {
        if ($answer === '') {
            return null;
        }

        $key = trim((string) ($item['scoring_key'] ?? ''));
        if ($key === '') {
            return null;
        }

        $total = 0.0;
        $matched = false;
        $answerValues = array_filter(array_map('trim', explode(',', $answer)), static fn(string $value): bool => $value !== '');

        foreach (explode(';', $key) as $pair) {
            [$value, $score] = array_pad(array_map('trim', explode('=', $pair, 2)), 2, null);
            if (
                $value !== null
                && $score !== null
                && ($this->answerValueMatches($answer, $value) || $this->answerListContains($answerValues, $value))
            ) {
                $total += (float) $score;
                $matched = true;
            }
        }

        if ($matched) {
            return $total;
        }

        return 0.0;
    }

    public function summaryForSession(int $sessionId): array
    {
        $session = $this->db->fetch('SELECT score_summary FROM test_sessions WHERE id = ? LIMIT 1', [$sessionId]);
        return json_decode($session['score_summary'] ?? '[]', true) ?: [];
    }

    public function summariesForSessions(array $sessionIds): array
    {
        $sessionIds = array_values(array_unique(array_filter(array_map('intval', $sessionIds), static fn(int $id): bool => $id > 0)));
        if (!$sessionIds) {
            return [];
        }

        $summaries = [];
        foreach (array_chunk($sessionIds, 25) as $chunk) {
            $placeholders = implode(',', array_fill(0, count($chunk), '?'));
            $rows = $this->db->fetchAll("SELECT id, score_summary FROM test_sessions WHERE id IN ({$placeholders})", $chunk);
            foreach ($rows as $row) {
                $id = (int) ($row['id'] ?? 0);
                $decoded = json_decode((string) ($row['score_summary'] ?? '[]'), true);
                $summaries[$id] = is_array($decoded) ? $decoded : [];
            }
        }

        return $summaries;
    }

    public function resultContext(int $sessionId): array
    {
        $session = $this->db->fetch('
            SELECT i.category
            FROM test_sessions ts
            JOIN test_instruments i ON i.id = ts.instrument_id
            WHERE ts.id = ?
            LIMIT 1
        ', [$sessionId]) ?: [];

        $age = $this->db->fetch("
            SELECT u.age AS value
            FROM test_sessions ts
            JOIN {$this->coreSchema}.users u ON u.id = ts.user_id
            WHERE ts.id = ?
            LIMIT 1
        ", [$sessionId]);

        $category = (string) ($session['category'] ?? '');
        $supportsCorrectAnswers = in_array($category, ['cognitive', 'verbal'], true);

        return [
            'age' => trim((string) ($age['value'] ?? '')),
            'supports_correct_answers' => $supportsCorrectAnswers,
            'scale_metrics' => $this->resultScaleMetrics($sessionId, $supportsCorrectAnswers),
        ];
    }

    private function resultScaleMetrics(int $sessionId, bool $supportsCorrectAnswers): array
    {
        $metrics = [];
        $ruleRows = $this->db->fetchAll('
            SELECT
                s.id AS scale_id,
                s.scale_key,
                COUNT(DISTINCT r.item_id) AS total_items,
                COUNT(DISTINCT CASE WHEN a.answer_value IS NOT NULL AND a.answer_value <> "" THEN r.item_id END) AS answered_items,
                COUNT(DISTINCT CASE WHEN tas.score_value > 0 THEN r.item_id END) AS correct_items
            FROM test_sessions ts
            JOIN test_scales s ON s.instrument_id = ts.instrument_id
            JOIN test_item_score_rules r ON r.instrument_id = ts.instrument_id AND r.scale_id = s.id AND r.is_active = 1
            LEFT JOIN test_answers a ON a.session_id = ts.id AND a.item_id = r.item_id
            LEFT JOIN test_answer_scores tas ON tas.session_id = ts.id AND tas.item_id = r.item_id AND tas.scale_id = s.id AND tas.rule_id = r.id
            WHERE ts.id = ?
            GROUP BY s.id, s.scale_key
        ', [$sessionId]);

        foreach ($ruleRows as $row) {
            $this->mergeResultScaleMetric($metrics, $row, $supportsCorrectAnswers);
        }

        $legacyRows = $this->db->fetchAll('
            SELECT
                COALESCE(s.id, 0) AS scale_id,
                COALESCE(s.scale_key, "general") AS scale_key,
                COUNT(DISTINCT it.id) AS total_items,
                COUNT(DISTINCT CASE WHEN a.answer_value IS NOT NULL AND a.answer_value <> "" THEN it.id END) AS answered_items,
                COUNT(DISTINCT CASE WHEN a.score_value > 0 THEN it.id END) AS correct_items
            FROM test_sessions ts
            JOIN test_items it ON it.instrument_id = ts.instrument_id AND it.is_active = 1
            LEFT JOIN test_scales s ON s.id = it.scale_id
            LEFT JOIN test_answers a ON a.session_id = ts.id AND a.item_id = it.id
            WHERE ts.id = ?
              AND NOT EXISTS (
                  SELECT 1
                  FROM test_item_score_rules r
                  WHERE r.item_id = it.id AND r.instrument_id = ts.instrument_id AND r.is_active = 1
              )
            GROUP BY COALESCE(s.id, 0), COALESCE(s.scale_key, "general")
        ', [$sessionId]);

        foreach ($legacyRows as $row) {
            $this->mergeResultScaleMetric($metrics, $row, $supportsCorrectAnswers);
        }

        return $metrics;
    }

    private function mergeResultScaleMetric(array &$metrics, array $row, bool $supportsCorrectAnswers): void
    {
        $scaleId = (int) ($row['scale_id'] ?? 0);
        $scaleKey = (string) ($row['scale_key'] ?? 'general');
        $metric = [
            'total_items' => (int) ($row['total_items'] ?? 0),
            'answered_items' => (int) ($row['answered_items'] ?? 0),
            'correct_items' => $supportsCorrectAnswers ? (int) ($row['correct_items'] ?? 0) : null,
        ];

        $metrics['scale:' . $scaleKey] = $metric;
        if ($scaleId > 0) {
            $metrics['id:' . $scaleId] = $metric;
        }
    }

    public function optionPairs(?string $options): array
    {
        $pairs = [];
        foreach (explode(';', (string) $options) as $option) {
            [$value, $label] = array_pad(array_map('trim', explode('=', $option, 2)), 2, null);
            if ($value !== null && $value !== '') {
                $pairs[$value] = $label ?: $value;
            }
        }

        return $pairs;
    }

    private function hasUserResultVisibilityColumn(): bool
    {
        if ($this->hasUserResultVisibilityColumn !== null) {
            return $this->hasUserResultVisibilityColumn;
        }

        try {
            $row = $this->db->fetch("
                SELECT COUNT(*) AS total
                FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE()
                  AND TABLE_NAME = 'test_instruments'
                  AND COLUMN_NAME = 'user_can_view_results'
            ");
            $this->hasUserResultVisibilityColumn = (int) ($row['total'] ?? 0) > 0;
        } catch (Throwable $exception) {
            error_log('Test schema check error: ' . $exception->getMessage());
            $this->hasUserResultVisibilityColumn = false;
        }

        return $this->hasUserResultVisibilityColumn;
    }

    private function hasQuestionOrderModeColumn(): bool
    {
        if ($this->hasQuestionOrderModeColumn !== null) {
            return $this->hasQuestionOrderModeColumn;
        }

        try {
            $row = $this->db->fetch("
                SELECT COUNT(*) AS total
                FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE()
                  AND TABLE_NAME = 'test_instruments'
                  AND COLUMN_NAME = 'question_order_mode'
            ");
            $this->hasQuestionOrderModeColumn = (int) ($row['total'] ?? 0) > 0;
        } catch (Throwable $exception) {
            error_log('Test schema check error: ' . $exception->getMessage());
            $this->hasQuestionOrderModeColumn = false;
        }

        return $this->hasQuestionOrderModeColumn;
    }

    private function hasActivityTrackingColumn(): bool
    {
        if ($this->hasActivityTrackingColumn !== null) {
            return $this->hasActivityTrackingColumn;
        }

        try {
            $row = $this->db->fetch("
                SELECT COUNT(*) AS total
                FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE()
                  AND TABLE_NAME = 'test_instruments'
                  AND COLUMN_NAME = 'track_activity_enabled'
            ");
            $this->hasActivityTrackingColumn = (int) ($row['total'] ?? 0) > 0;
        } catch (Throwable $exception) {
            error_log('Test schema check error: ' . $exception->getMessage());
            $this->hasActivityTrackingColumn = false;
        }

        return $this->hasActivityTrackingColumn;
    }

    private function hasSupervisedModeColumn(): bool
    {
        if ($this->hasSupervisedModeColumn !== null) {
            return $this->hasSupervisedModeColumn;
        }

        try {
            $row = $this->db->fetch("
                SELECT COUNT(*) AS total
                FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE()
                  AND TABLE_NAME = 'test_instruments'
                  AND COLUMN_NAME = 'supervised_mode_enabled'
            ");
            $this->hasSupervisedModeColumn = (int) ($row['total'] ?? 0) > 0;
        } catch (Throwable $exception) {
            error_log('Test schema check error: ' . $exception->getMessage());
            $this->hasSupervisedModeColumn = false;
        }

        return $this->hasSupervisedModeColumn;
    }

    private function hasControlModeColumn(): bool
    {
        try {
            $row = $this->db->fetch("
                SELECT COUNT(*) AS total
                FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE()
                  AND TABLE_NAME = 'test_sessions'
                  AND COLUMN_NAME = 'control_mode'
            ");

            return (int) ($row['total'] ?? 0) > 0;
        } catch (Throwable $exception) {
            error_log('Test schema check error: ' . $exception->getMessage());
            return false;
        }
    }

    private function effectiveControlMode(array $row): string
    {
        $mode = (string) ($row['control_mode'] ?? '');
        if (in_array($mode, ['off', 'activity', 'supervised', 'supervised_audio_visual'], true)) {
            return $mode;
        }

        if ((int) ($row['supervised_mode_enabled'] ?? 0) === 1) {
            return 'supervised';
        }

        return (int) ($row['track_activity_enabled'] ?? 0) === 1 ? 'activity' : 'off';
    }

    private function hasShowQuestionNumbersColumn(): bool
    {
        if ($this->hasShowQuestionNumbersColumn !== null) {
            return $this->hasShowQuestionNumbersColumn;
        }

        try {
            $row = $this->db->fetch("
                SELECT COUNT(*) AS total
                FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE()
                  AND TABLE_NAME = 'test_instruments'
                  AND COLUMN_NAME = 'show_question_numbers'
            ");
            $this->hasShowQuestionNumbersColumn = (int) ($row['total'] ?? 0) > 0;
        } catch (Throwable $exception) {
            error_log('Test schema check error: ' . $exception->getMessage());
            $this->hasShowQuestionNumbersColumn = false;
        }

        return $this->hasShowQuestionNumbersColumn;
    }

    private function hasAutoStartColumns(): bool
    {
        if ($this->hasAutoStartColumns !== null) {
            return $this->hasAutoStartColumns;
        }

        try {
            $row = $this->db->fetch("
                SELECT COUNT(*) AS total
                FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE()
                  AND TABLE_NAME = 'test_instruments'
                  AND COLUMN_NAME IN ('auto_start_enabled', 'auto_start_order')
            ");
            $this->hasAutoStartColumns = (int) ($row['total'] ?? 0) === 2;
        } catch (Throwable $exception) {
            error_log('Test session schema check error: ' . $exception->getMessage());
            $this->hasAutoStartColumns = false;
        }

        return $this->hasAutoStartColumns;
    }

    private function hasReopenedDurationColumn(): bool
    {
        if ($this->hasReopenedDurationColumn !== null) {
            return $this->hasReopenedDurationColumn;
        }

        try {
            $row = $this->db->fetch("
                SELECT COUNT(*) AS total
                FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE()
                  AND TABLE_NAME = 'test_sessions'
                  AND COLUMN_NAME = 'reopened_duration_minutes'
            ");
            $this->hasReopenedDurationColumn = (int) ($row['total'] ?? 0) > 0;
        } catch (Throwable $exception) {
            error_log('Test schema check error: ' . $exception->getMessage());
            $this->hasReopenedDurationColumn = false;
        }

        return $this->hasReopenedDurationColumn;
    }

    private function hasPausedRemainingColumn(): bool
    {
        if ($this->hasPausedRemainingColumn !== null) {
            return $this->hasPausedRemainingColumn;
        }

        try {
            $row = $this->db->fetch("
                SELECT COUNT(*) AS total
                FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE()
                  AND TABLE_NAME = 'test_sessions'
                  AND COLUMN_NAME = 'paused_remaining_seconds'
            ");
            $this->hasPausedRemainingColumn = (int) ($row['total'] ?? 0) > 0;
        } catch (Throwable $exception) {
            error_log('Test session schema check error: ' . $exception->getMessage());
            $this->hasPausedRemainingColumn = false;
        }

        return $this->hasPausedRemainingColumn;
    }

    private function hasProcessAvailabilityStatusColumn(): bool
    {
        if ($this->hasProcessAvailabilityStatusColumn !== null) {
            return $this->hasProcessAvailabilityStatusColumn;
        }

        try {
            $row = $this->db->fetch("
                SELECT COUNT(*) AS total
                FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE()
                  AND TABLE_NAME = 'test_processes'
                  AND COLUMN_NAME = 'availability_status'
            ");
            $this->hasProcessAvailabilityStatusColumn = (int) ($row['total'] ?? 0) > 0;
        } catch (Throwable $exception) {
            error_log('Test schema check error: ' . $exception->getMessage());
            $this->hasProcessAvailabilityStatusColumn = false;
        }

        return $this->hasProcessAvailabilityStatusColumn;
    }

    private function timestampOrNull($value): ?int
    {
        $value = trim((string) ($value ?? ''));
        if ($value === '') {
            return null;
        }

        $timestamp = strtotime($value);
        return $timestamp === false ? null : $timestamp;
    }

    private function humanDuration(int $seconds): string
    {
        $seconds = max(0, $seconds);
        $days = intdiv($seconds, 86400);
        $seconds %= 86400;
        $hours = intdiv($seconds, 3600);
        $seconds %= 3600;
        $minutes = intdiv($seconds, 60);

        if ($days > 0) {
            return $days . 'd ' . $hours . 'h';
        }
        if ($hours > 0) {
            return $hours . 'h ' . $minutes . 'm';
        }

        return max(1, $minutes) . 'm';
    }

    private function hasActivityEventsTable(bool $createIfMissing = false): bool
    {
        if ($this->hasActivityEventsTable !== null) {
            return $this->hasActivityEventsTable;
        }

        try {
            $row = $this->db->fetch("
                SELECT COUNT(*) AS total
                FROM information_schema.TABLES
                WHERE TABLE_SCHEMA = DATABASE()
                  AND TABLE_NAME = 'test_activity_events'
            ");
            $this->hasActivityEventsTable = (int) ($row['total'] ?? 0) > 0;
            if (!$this->hasActivityEventsTable && $createIfMissing) {
                $this->db->execute('
                    CREATE TABLE IF NOT EXISTS test_activity_events (
                        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                        session_id INT UNSIGNED NOT NULL,
                        instrument_id INT UNSIGNED NOT NULL,
                        user_id INT UNSIGNED NOT NULL,
                        event_type VARCHAR(60) NOT NULL,
                        item_id INT UNSIGNED NULL,
                        block_number INT UNSIGNED NULL,
                        metadata TEXT NULL,
                        ip_address VARCHAR(45) NULL,
                        user_agent VARCHAR(255) NULL,
                        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                        INDEX idx_test_activity_session (session_id, created_at),
                        INDEX idx_test_activity_user (user_id, created_at),
                        INDEX idx_test_activity_event (event_type, created_at),
                        INDEX idx_test_activity_item (item_id),
                        CONSTRAINT fk_test_activity_session_id FOREIGN KEY (session_id) REFERENCES test_sessions(id) ON DELETE CASCADE,
                        CONSTRAINT fk_test_activity_instrument_id FOREIGN KEY (instrument_id) REFERENCES test_instruments(id) ON DELETE CASCADE,
                        CONSTRAINT fk_test_activity_item_id FOREIGN KEY (item_id) REFERENCES test_items(id) ON DELETE SET NULL
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                ');
                $this->hasActivityEventsTable = true;
            }
        } catch (Throwable $exception) {
            error_log('Test activity schema check error: ' . $exception->getMessage());
            $this->hasActivityEventsTable = false;
        }

        return $this->hasActivityEventsTable;
    }

    private function sanitizeActivityMetadata(array $metadata): array
    {
        $safe = [];
        $allowedKeys = [
            'block',
            'visible',
            'hidden_seconds',
            'inactive_seconds',
            'remaining_seconds',
            'answered_count',
            'changed_count',
            'changed_item_ids',
            'item_ids',
            'reason',
            'source',
            'url_path',
            'device_type',
            'os',
            'browser',
            'browser_language',
            'timezone',
            'screen',
            'viewport',
            'pixel_ratio',
            'touch_points',
            'hardware_concurrency',
            'device_memory',
            'connection',
        ];

        foreach ($metadata as $key => $value) {
            $key = preg_replace('/[^a-zA-Z0-9_]/', '', (string) $key);
            if ($key === '' || !in_array($key, $allowedKeys, true)) {
                continue;
            }

            if (is_array($value)) {
                $safe[$key] = array_slice(array_map('intval', $value), 0, 50);
            } elseif (is_bool($value)) {
                $safe[$key] = $value;
            } elseif (is_numeric($value)) {
                $safe[$key] = (float) $value;
            } else {
                $safe[$key] = substr(trim((string) $value), 0, 180);
            }
        }

        return $safe;
    }

    private function dashboardFieldValuesForUsers(array $userIds, array $fields): array
    {
        $fieldIds = array_values(array_filter(array_map(static fn(array $field): int => (int) ($field['id'] ?? 0), $fields)));
        if (!$userIds || !$fieldIds) {
            return [];
        }

        $userPlaceholders = implode(',', array_fill(0, count($userIds), '?'));
        $fieldPlaceholders = implode(',', array_fill(0, count($fieldIds), '?'));
        $rows = $this->db->fetchAll("
            SELECT v.user_id, f.field_key, v.value
            FROM {$this->coreSchema}.user_field_values v
            JOIN {$this->coreSchema}.user_field_definitions f ON f.id = v.field_id
            WHERE v.user_id IN ({$userPlaceholders})
              AND v.field_id IN ({$fieldPlaceholders})
        ", array_merge($userIds, $fieldIds));

        $values = [];
        foreach ($rows as $row) {
            $values[(int) $row['user_id']][(string) $row['field_key']] = trim((string) ($row['value'] ?? ''));
        }

        return $values;
    }

    private function dashboardSessionMatchesFilters(array $row, array $filters): bool
    {
        $instrumentId = (int) ($filters['instrument_id'] ?? 0);
        if ($instrumentId > 0 && (int) $row['instrument_id'] !== $instrumentId) {
            return false;
        }

        $company = trim((string) ($filters['company'] ?? ''));
        if ($company !== '' && trim((string) ($row['company_name'] ?? '')) !== $company) {
            return false;
        }

        $status = trim((string) ($filters['status'] ?? ''));
        if ($status !== '') {
            $rowStatus = (string) $row['status'];
            if ($status === 'finished') {
                if (!in_array($rowStatus, ['completed', 'expired'], true)) {
                    return false;
                }
            } elseif ($rowStatus !== $status) {
                return false;
            }
        }

        $fieldFilters = is_array($filters['fields'] ?? null) ? $filters['fields'] : [];
        foreach ($fieldFilters as $key => $value) {
            $key = preg_replace('/[^a-zA-Z0-9_]/', '', (string) $key);
            $value = trim((string) $value);
            if ($key === '' || $value === '') {
                continue;
            }

            $fieldValue = trim((string) (($row['dynamic_fields'] ?? [])[$key] ?? ''));
            if ($fieldValue === '' || stripos($fieldValue, $value) === false) {
                return false;
            }
        }

        $search = strtolower(trim((string) ($filters['search'] ?? '')));
        if ($search !== '') {
            $dynamicFields = implode(' ', array_values($row['dynamic_fields'] ?? []));
            $haystack = strtolower(trim(implode(' ', [
                $row['user_name'] ?? '',
                $row['user_email'] ?? '',
                $row['company_name'] ?? '',
                $row['instrument_name'] ?? '',
                $row['instrument_code'] ?? '',
                $dynamicFields,
            ])));

            if (strpos($haystack, $search) === false) {
                return false;
            }
        }

        return true;
    }

    private function resultCompanyScopeSql(string $alias): string
    {
        $user = current_user();
        if (!$user || has_permission('view_test_results')) {
            return '1 = 1';
        }

        if (has_permission('view_company_results') && (int) ($user['company_id'] ?? 0) > 0) {
            return $alias . '.company_id = ?';
        }

        return '1 = 0';
    }

    private function resultCompanyScopeParams(): array
    {
        $user = current_user();
        if ($user && !has_permission('view_test_results') && has_permission('view_company_results') && (int) ($user['company_id'] ?? 0) > 0) {
            return [(int) $user['company_id']];
        }

        return [];
    }
}
