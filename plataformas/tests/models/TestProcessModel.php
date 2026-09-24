<?php
declare(strict_types=1);

final class TestProcessModel
{
    private const RANKING_SNAPSHOT_VERSION = 'ticl-valid-session-v2';

    public const SUPERVISOR_SESSION_PERMISSION = 'manage_process_session_actions';

    public const STATUSES = [
        'draft' => 'Borrador',
        'active' => 'Activo',
        'closed' => 'Cerrado',
        'cancelled' => 'Cancelado',
    ];

    public const PROCESS_PERMISSIONS = [
        'view_process' => 'Ver proceso',
    ];

    private Database $db;
    private string $coreSchema;
    private ?bool $hasSessionProcessColumn = null;
    private ?bool $hasSessionPresenceColumn = null;
    private ?bool $hasPausedRemainingColumn = null;
    private ?bool $hasSessionScoreSummaryColumn = null;
    private ?bool $hasProcessAdminModeColumn = null;
    private ?bool $hasProcessAvailabilityColumns = null;
    private ?bool $hasProcessFacialEnrollmentColumn = null;
    private ?bool $hasProcessActivityPolicyColumns = null;
    private ?bool $hasUserLoginEventsTable = null;
    private array $policySchemaColumnCache = [];
    private array $policySchemaTablesLoaded = [];

    public function __construct(?Database $db = null)
    {
        $this->db = $db ?: database('tests');
        $this->coreSchema = database_identifier('core');
    }

    public function allForUser(array $user): array
    {
        if ((string) ($user['role'] ?? '') === 'company_admin' && (int) ($user['company_id'] ?? 0) <= 0) {
            return [];
        }
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
        $row = $this->db->fetch("\n            SELECT\n                COUNT(DISTINCT CASE WHEN p.status = 'closed' THEN p.id END) AS processes_completed,\n                COUNT(DISTINCT CASE WHEN COALESCE(ans.answers_count, 0) > 0 THEN s.id END) AS evaluations_answered,\n                COUNT(DISTINCT s.id) AS evaluations_total\n            FROM test_processes p\n            LEFT JOIN test_sessions s ON s.process_id = p.id AND s.status <> 'cancelled'\n            LEFT JOIN (\n                SELECT session_id, SUM(CASE WHEN answer_value IS NOT NULL AND TRIM(answer_value) <> '' THEN 1 ELSE 0 END) AS answers_count\n                FROM test_answers\n                GROUP BY session_id\n            ) ans ON ans.session_id = s.id\n            WHERE p.id IN ({$placeholders})\n        ", $ids) ?: [];

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
        $dayStart = date('Y-m-d 00:00:00');
        $dayEnd = date('Y-m-d 00:00:00', strtotime('+1 day'));
        $row = $this->db->fetch("
            SELECT
                COALESCE(SUM(CASE WHEN overview.starts_at IS NOT NULL
                    AND overview.ends_at IS NOT NULL
                    AND overview.starts_at < ?
                    AND overview.ends_at >= ?
                    AND overview.status NOT IN ('cancelled', 'closed')
                    THEN 1 ELSE 0 END), 0) AS today_processes,
                COALESCE(SUM(CASE WHEN overview.starts_at IS NOT NULL
                    AND overview.ends_at IS NOT NULL
                    AND overview.starts_at < ?
                    AND overview.ends_at >= ?
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
                           COUNT(DISTINCT CASE WHEN ts.status = 'completed' THEN ts.instrument_id END) AS finished_instruments,
                           COUNT(DISTINCT CASE WHEN ts.status = 'completed' OR COALESCE(answer_stats.answers_count, 0) > 0 THEN ts.instrument_id END) AS started_instruments
                    FROM test_sessions ts
                    LEFT JOIN (
                        SELECT session_id, SUM(CASE WHEN answer_value IS NOT NULL AND TRIM(answer_value) <> '' THEN 1 ELSE 0 END) AS answers_count
                        FROM test_answers
                        GROUP BY session_id
                    ) answer_stats ON answer_stats.session_id = ts.id
                    WHERE ts.status <> 'cancelled'
                    GROUP BY ts.process_id, ts.user_id
                ) progress ON progress.process_id = p.id AND progress.user_id = pu.user_id
                WHERE p.id IN ({$placeholders})
                GROUP BY p.id, p.status, p.starts_at, p.ends_at
            ) overview
        ", array_merge([$dayEnd, $dayStart, $dayEnd, $dayStart], $ids)) ?: [];

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
                self::SUPERVISOR_SESSION_PERMISSION,
            ];
            if ($process && (int) ($process['company_id'] ?? 0) === (int) $user['company_id']
                && has_permission('manage_company_processes')
                && in_array($permission, $companyPermissions, true)) {
                return true;
            }
            if ($this->isCompanySupervisor($user) && (!$process || (int) ($process['company_id'] ?? 0) !== (int) $user['company_id'])) {
                return false;
            }
            // Un Supervisor solo puede consultar el proceso que le fue
            // asignado. Nunca puede heredar permisos de resultados,
            // dashboard, usuarios, asignaciones o configuracion.
            if ($this->isCompanySupervisor($user)
                && !in_array($permission, ['view_process', self::SUPERVISOR_SESSION_PERMISSION], true)) {
                return false;
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

            if ($row && ($permission === self::SUPERVISOR_SESSION_PERMISSION && $this->isCompanySupervisor($user)
                ? $this->permissionsContain($row['permissions'] ?? '[]', 'view_process')
                : $this->permissionsContain($row['permissions'] ?? '[]', $permission))) {
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
        $companyScoped = has_permission('manage_company_processes') && !has_permission('manage_test_processes') && !has_permission('manage_tests');
        $companyId = (int) (current_user()['company_id'] ?? 0);
        $join = $companyScoped && $companyId > 0
            ? 'LEFT JOIN company_test_instruments ca ON ca.instrument_id = i.id AND ca.company_id = ' . $companyId
            : '';
        $where = $companyScoped && $companyId > 0 ? ' AND ca.id IS NOT NULL' : '';

        return $this->db->fetchAll('
            SELECT i.id, i.code, i.name, i.category,
                   ' . ($companyScoped && $companyId > 0 ? 'COALESCE(ca.duration_minutes, i.duration_minutes)' : 'i.duration_minutes') . ' AS duration_minutes
            FROM test_instruments i
            ' . $join . '
            WHERE i.status = "active"' . $where . '
            ORDER BY i.name
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
              AND p.role_key = 'supervisor_sede'
            ORDER BY p.name, u.name
        ", $this->companyUserScopeParams());
    }

    public function userFields(): array
    {
        $companyScoped = has_permission('manage_company_users') && !has_permission('manage_users');
        $companyId = (int) (current_user()['company_id'] ?? 0);
        $companySql = $companyScoped && $companyId > 0 ? ' AND company_id = ?' : '';
        $companyParams = $companyScoped && $companyId > 0 ? [$companyId] : [];
        $fields = $this->db->fetchAll("
            SELECT id, field_key, label, field_type, options, is_required, show_in_list, sort_order
            FROM {$this->coreSchema}.user_field_definitions
            WHERE is_active = 1" . $companySql . "
            ORDER BY scope_type ASC, scope_key ASC, sort_order ASC, label ASC
        ", $companyParams);

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

    public function rankingSnapshot(int $processId, string $configHash): ?array
    {
        if ($processId <= 0 || $configHash === '' || !$this->tableExists('test_ranking_snapshots')) {
            return null;
        }
        $fingerprint = $this->rankingSourceFingerprint($processId);
        $row = $this->db->fetch('SELECT rows_json, warnings_json, users_total, sessions_total FROM test_ranking_snapshots WHERE process_id = ? AND config_hash = ? AND source_fingerprint = ? LIMIT 1', [$processId, $configHash, $fingerprint]);
        if (!$row) {
            return null;
        }
        $rows = json_decode((string) ($row['rows_json'] ?? '[]'), true);
        $warnings = json_decode((string) ($row['warnings_json'] ?? '[]'), true);
        return [
            'rows' => is_array($rows) ? $rows : [],
            'warnings' => is_array($warnings) ? $warnings : [],
            'users_total' => (int) ($row['users_total'] ?? 0),
            'sessions_total' => (int) ($row['sessions_total'] ?? 0),
        ];
    }

    public function saveRankingSnapshot(int $processId, string $configHash, array $ranking, int $usersTotal, int $sessionsTotal): void
    {
        if ($processId <= 0 || $configHash === '' || !$this->tableExists('test_ranking_snapshots')) {
            return;
        }
        $rows = [];
        foreach (is_array($ranking['rows'] ?? null) ? $ranking['rows'] : [] as $row) {
            if (!is_array($row)) {
                continue;
            }
            unset($row['Ranking']);
            $rows[] = $row;
        }
        $this->db->execute('INSERT INTO test_ranking_snapshots (process_id, config_hash, source_fingerprint, rows_json, warnings_json, users_total, sessions_total) VALUES (?, ?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE source_fingerprint=VALUES(source_fingerprint), rows_json=VALUES(rows_json), warnings_json=VALUES(warnings_json), users_total=VALUES(users_total), sessions_total=VALUES(sessions_total), generated_at=CURRENT_TIMESTAMP', [
            $processId,
            $configHash,
            $this->rankingSourceFingerprint($processId),
            json_encode($rows, JSON_UNESCAPED_UNICODE),
            json_encode($ranking['warnings'] ?? [], JSON_UNESCAPED_UNICODE),
            max(0, $usersTotal),
            max(0, $sessionsTotal),
        ]);
    }

    private function rankingSourceFingerprint(int $processId): string
    {
        $sessions = $this->db->fetch('SELECT COUNT(*) AS total, COALESCE(MAX(updated_at), "") AS last_updated FROM test_sessions WHERE process_id = ?', [$processId]) ?: [];
        $users = $this->db->fetch('SELECT COUNT(*) AS total, COALESCE(MAX(updated_at), "") AS last_updated FROM test_process_users WHERE process_id = ?', [$processId]) ?: [];
        $instruments = $this->db->fetch('SELECT COUNT(*) AS total, COALESCE(GROUP_CONCAT(CONCAT(instrument_id, ":", sort_order, ":", is_required) ORDER BY instrument_id SEPARATOR ","), "") AS definition FROM test_process_instruments WHERE process_id = ?', [$processId]) ?: [];

        return hash('sha256', implode('|', [
            self::RANKING_SNAPSHOT_VERSION,
            $processId,
            (int) ($sessions['total'] ?? 0),
            (string) ($sessions['last_updated'] ?? ''),
            (int) ($users['total'] ?? 0),
            (string) ($users['last_updated'] ?? ''),
            (int) ($instruments['total'] ?? 0),
            (string) ($instruments['definition'] ?? ''),
        ]));
    }

    public function activeEvaluationForms(): array
    {
        $schema = database_identifier('evaluaciones_encuestas');
        $companyScoped = has_permission('manage_company_users') && !has_permission('manage_users');
        $companyId = (int) (current_user()['company_id'] ?? 0);
        $scopeSql = $companyScoped && $companyId > 0 ? ' AND f.company_id = ?' : '';
        $params = $companyScoped && $companyId > 0 ? [$companyId] : [];

        return $this->db->fetchAll('
            SELECT f.id, f.company_id, f.form_type, f.title, f.duration_minutes, f.status
            FROM ' . $schema . '.evaluation_survey_forms f
            WHERE f.status = "active"' . $scopeSql . '
            ORDER BY f.form_type ASC, f.title ASC
        ', $params);
    }

    public function selectedEvaluationFormIds(int $processId): array
    {
        if (!$this->tableExists('test_process_evaluation_forms')) {
            return [];
        }
        return array_map('intval', array_column($this->db->fetchAll('
            SELECT form_id FROM test_process_evaluation_forms WHERE process_id = ? ORDER BY sort_order ASC, id ASC
        ', [$processId]), 'form_id'));
    }

    public function selectedEvaluationForms(int $processId): array
    {
        $ids = $this->selectedEvaluationFormIds($processId);
        if (!$ids) {
            return [];
        }

        $schema = database_identifier('evaluaciones_encuestas');
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        return $this->db->fetchAll('
            SELECT f.id, f.form_type, f.title, f.duration_minutes, f.status,
                   (SELECT COUNT(*) FROM ' . $schema . '.evaluation_survey_questions q
                    WHERE q.form_id = f.id AND q.is_active = 1) AS question_count
            FROM ' . $schema . '.evaluation_survey_forms f
            WHERE f.id IN (' . $placeholders . ')
            ORDER BY FIELD(f.id, ' . $placeholders . ')
        ', array_merge($ids, $ids));
    }

    public function processEvaluationAssignments(int $processId): array
    {
        if (!$this->tableExists('test_process_evaluation_assignments')) {
            return [];
        }

        $schema = database_identifier('evaluaciones_encuestas');
        return $this->db->fetchAll('
            SELECT a.process_id, a.form_id, a.user_id, a.status AS assignment_status,
                   f.title AS form_title, f.form_type,
                   (SELECT COUNT(*) FROM ' . $schema . '.evaluation_survey_questions q
                    WHERE q.form_id = f.id AND q.is_active = 1) AS question_count,
                   CASE WHEN COALESCE(latest.status, "assigned") = "in_progress"
                             AND latest.control_mode = "supervised_audio_visual"
                             AND latest.supervised_started_at IS NULL
                             AND latest.recording_started_at IS NULL
                        THEN "assigned"
                        ELSE COALESCE(latest.status, "assigned") END AS evaluation_status,
                   latest.id AS attempt_id,
                   latest.final_score, latest.completed_at,
                   COALESCE(latest.answers_count, 0) AS answers_count,
                   COALESCE((SELECT COUNT(*) FROM ' . $schema . '.evaluation_survey_activity_events ae WHERE ae.attempt_id = latest.id), 0) AS activity_events_total,
                   COALESCE((SELECT COUNT(*) FROM ' . $schema . '.evaluation_survey_activity_events ae WHERE ae.attempt_id = latest.id AND ae.event_type IN ("tab_hidden", "window_blurred", "inactive_detected", "fullscreen_exited", "fullscreen_denied", "fullscreen_failed", "fullscreen_unavailable", "suspicious_key_printscreen", "suspicious_key_print", "suspicious_key_save", "suspicious_key_copy", "suspicious_key_devtools", "context_menu_blocked", "copy_blocked", "cut_blocked", "paste_blocked", "drag_blocked", "print_blocked")), 0) AS activity_attention_total,
                   COALESCE((SELECT COUNT(*) FROM ' . $schema . '.evaluation_survey_media_risk_events ar WHERE ar.attempt_id = latest.id), 0) AS activity_risk_total,
                   (SELECT MAX(ae.created_at) FROM ' . $schema . '.evaluation_survey_activity_events ae WHERE ae.attempt_id = latest.id) AS last_activity_event_at
            FROM test_process_evaluation_assignments a
            JOIN ' . $schema . '.evaluation_survey_forms f ON f.id = a.form_id
            LEFT JOIN (
                SELECT ea.id, ea.process_id, ea.form_id, ea.user_id, ea.status, ea.control_mode, ea.final_score, ea.completed_at,
                       eme.recording_started_at,
                       (SELECT MIN(ae.created_at) FROM ' . $schema . '.evaluation_survey_activity_events ae WHERE ae.attempt_id = ea.id AND ae.event_type = "supervised_started") AS supervised_started_at,
                       COALESCE(answer_counts.answers_count, 0) AS answers_count
                FROM ' . $schema . '.evaluation_survey_attempts ea
                LEFT JOIN ' . $schema . '.evaluation_survey_media_evidence eme ON eme.attempt_id = ea.id
                    AND eme.segment_number = (SELECT MAX(eme2.segment_number) FROM ' . $schema . '.evaluation_survey_media_evidence eme2 WHERE eme2.attempt_id = ea.id)
                JOIN (
                    SELECT process_id, form_id, user_id, MAX(attempt_number) AS attempt_number
                    FROM ' . $schema . '.evaluation_survey_attempts
                    WHERE status <> "preparing"
                    GROUP BY process_id, form_id, user_id
                ) latest_attempt ON latest_attempt.form_id = ea.form_id
                    AND latest_attempt.user_id = ea.user_id
                    AND (latest_attempt.process_id <=> ea.process_id)
                    AND latest_attempt.attempt_number = ea.attempt_number
                LEFT JOIN (
                    SELECT attempt_id, SUM(CASE WHEN answer_value IS NOT NULL AND TRIM(answer_value) <> "" THEN 1 ELSE 0 END) AS answers_count
                    FROM ' . $schema . '.evaluation_survey_answers
                    GROUP BY attempt_id
                ) answer_counts ON answer_counts.attempt_id = ea.id
            ) latest ON latest.process_id = a.process_id AND latest.form_id = a.form_id AND latest.user_id = a.user_id
            WHERE a.process_id = ?
            ORDER BY a.user_id, f.sort_order, f.id
        ', [$processId]);
    }

    public function evaluationAssignmentsForUser(int $userId, ?int $companyId = null, bool $includeInactiveForms = false): array
    {
        if (!$this->tableExists('test_process_evaluation_assignments')) {
            return [];
        }

        $schema = database_identifier('evaluaciones_encuestas');
        $companyId = $companyId && $companyId > 0 ? $companyId : null;
        $companyScope = $companyId
            ? ' AND p.company_id = ? AND u.company_id = ? AND (f.company_id IS NULL OR f.company_id = ?)'
            : '';
        $formStatusScope = $includeInactiveForms ? '' : ' AND f.status = "active"';
        $processPolicySelect = $this->processPolicySelect();
        $evaluationSnapshotSelect = $this->evaluationSnapshotSelect('latest');
        // Los primeros cuatro valores acotan los agregados al usuario antes
        // de aplicar el scope de empresa de la consulta principal.
        $params = [$userId, $userId, $userId, $userId];
        if ($companyId) array_push($params, $companyId, $companyId, $companyId);
        $rows = $this->db->fetchAll('
            SELECT a.process_id, a.form_id, a.user_id, a.status AS assignment_status,
                   f.title AS form_title, f.form_type, f.duration_minutes, f.show_result_to_user,
                   p.name AS process_name, p.starts_at AS process_starts_at, p.ends_at AS process_ends_at,
                   ' . ($this->hasProcessFacialEnrollmentColumn() ? 'COALESCE(p.require_facial_enrollment, 0)' : '0') . ' AS require_facial_enrollment,
                   CASE WHEN latest.status = "in_progress" THEN latest.control_mode ELSE COALESCE(f.control_mode, "off") END AS control_mode,
                   ' . $processPolicySelect . ', ' . $evaluationSnapshotSelect . ',
                   latest.status AS latest_attempt_status,
                   CASE WHEN COALESCE(latest.status, "assigned") = "in_progress"
                             AND latest.control_mode = "supervised_audio_visual"
                             AND latest.supervised_started_at IS NULL
                             AND latest.recording_started_at IS NULL
                        THEN "assigned"
                        ELSE COALESCE(latest.status, "assigned") END AS status,
                   latest.id AS attempt_id, latest.final_score, latest.completed_at,
                   COALESCE(latest.answers_count, 0) AS answers_count,
                   COALESCE(q.questions_count, 0) AS items_count
            FROM test_process_evaluation_assignments a
            JOIN test_processes p ON p.id = a.process_id
            JOIN ' . $this->coreSchema . '.users u ON u.id = a.user_id
            JOIN ' . $schema . '.evaluation_survey_forms f ON f.id = a.form_id
            LEFT JOIN (
                SELECT ea.id, ea.process_id, ea.form_id, ea.user_id, ea.status, ea.control_mode, ea.final_score, ea.completed_at,
                       eme.recording_started_at,
                       ' . $this->evaluationSnapshotSelect('ea', false) . ',
                       (SELECT MIN(ae.created_at) FROM ' . $schema . '.evaluation_survey_activity_events ae WHERE ae.attempt_id = ea.id AND ae.event_type = "supervised_started") AS supervised_started_at,
                       COALESCE(answer_counts.answers_count, 0) AS answers_count
                FROM ' . $schema . '.evaluation_survey_attempts ea
                LEFT JOIN ' . $schema . '.evaluation_survey_media_evidence eme ON eme.attempt_id = ea.id
                    AND eme.segment_number = (SELECT MAX(eme2.segment_number) FROM ' . $schema . '.evaluation_survey_media_evidence eme2 WHERE eme2.attempt_id = ea.id)
                JOIN (
                    SELECT process_id, form_id, user_id, MAX(attempt_number) AS attempt_number
                    FROM ' . $schema . '.evaluation_survey_attempts
                    WHERE user_id = ? AND status <> "preparing"
                    GROUP BY process_id, form_id, user_id
                ) latest_attempt ON latest_attempt.form_id = ea.form_id
                    AND latest_attempt.user_id = ea.user_id
                    AND (latest_attempt.process_id <=> ea.process_id)
                    AND latest_attempt.attempt_number = ea.attempt_number
                LEFT JOIN (
                    SELECT attempt_id, SUM(CASE WHEN answer_value IS NOT NULL AND TRIM(answer_value) <> "" THEN 1 ELSE 0 END) AS answers_count
                    FROM ' . $schema . '.evaluation_survey_answers
                    WHERE attempt_id IN (
                        SELECT scoped_attempt.id
                        FROM ' . $schema . '.evaluation_survey_attempts scoped_attempt
                        WHERE scoped_attempt.user_id = ?
                    )
                      AND answer_value IS NOT NULL AND TRIM(answer_value) <> ""
                    GROUP BY attempt_id
                ) answer_counts ON answer_counts.attempt_id = ea.id
                WHERE ea.user_id = ?
            ) latest ON latest.process_id = a.process_id AND latest.form_id = a.form_id AND latest.user_id = a.user_id
            LEFT JOIN (
                SELECT form_id, COUNT(*) AS questions_count
                FROM ' . $schema . '.evaluation_survey_questions
                WHERE is_active = 1
                GROUP BY form_id
            ) q ON q.form_id = a.form_id
            WHERE a.user_id = ? AND a.status <> "cancelled"' . $formStatusScope . $companyScope . '
            ORDER BY FIELD(COALESCE(latest.status, "assigned"), "assigned", "in_progress", "completed", "expired"), a.assigned_at DESC
        ', $params);
        foreach ($rows as &$row) {
            if (!empty($row['process_policy_snapshot_at']) && (string) ($row['latest_attempt_status'] ?? '') === 'in_progress') {
                $row['require_facial_enrollment'] = (int) ($row['process_facial_enrollment_required'] ?? 0);
                $row['component_validation_required'] = (int) ($row['process_component_validation_required'] ?? 0);
                $row['record_audio_visual'] = (int) ($row['process_record_audio_visual'] ?? 0);
                $row['record_activity_actions'] = (int) ($row['process_record_actions'] ?? 0);
            } elseif ((string) ($row['status'] ?? 'assigned') === 'assigned') {
                $row = ProcessPrerequisiteService::resolve($row);
            }
        }
        unset($row);
        return $rows;
    }

    public function evaluationAssignmentForUser(int $formId, int $userId, int $companyId, ?int $processId = null): ?array
    {
        if ($formId <= 0 || $userId <= 0 || $companyId <= 0) return null;
        $schema = database_identifier('evaluaciones_encuestas');
        $processScope = $processId && $processId > 0 ? ' AND a.process_id = ?' : '';
        $params = [$formId, $userId, $companyId, $companyId];
        if ($processId && $processId > 0) $params[] = $processId;

        $processPolicySelect = $this->processPolicySelect();
        $evaluationSnapshotSelect = $this->evaluationSnapshotSelect('latest');
        $rows = $this->db->fetchAll('
            SELECT a.process_id, a.form_id, a.user_id, a.status AS assignment_status,
                   ' . ($this->hasProcessFacialEnrollmentColumn() ? 'COALESCE(p.require_facial_enrollment, 0)' : '0') . ' AS require_facial_enrollment,
                   CASE WHEN latest.status = "in_progress" THEN latest.control_mode ELSE COALESCE(f.control_mode, "off") END AS control_mode,
                   latest.status AS latest_attempt_status,
                   ' . $processPolicySelect . ', ' . $evaluationSnapshotSelect . '
            FROM test_process_evaluation_assignments a
            JOIN test_processes p ON p.id = a.process_id
            JOIN ' . $this->coreSchema . '.users u ON u.id = a.user_id
            JOIN ' . $schema . '.evaluation_survey_forms f ON f.id = a.form_id
            LEFT JOIN ' . $schema . '.evaluation_survey_attempts latest
              ON latest.id = (SELECT ea.id FROM ' . $schema . '.evaluation_survey_attempts ea
                              WHERE ea.form_id = a.form_id AND ea.user_id = a.user_id AND ea.process_id = a.process_id AND ea.status <> "preparing"
                              ORDER BY ea.attempt_number DESC, ea.id DESC LIMIT 1)
            WHERE a.form_id = ? AND a.user_id = ? AND a.status = "assigned"
              AND p.company_id = u.company_id AND u.company_id = ?
              AND (f.company_id IS NULL OR f.company_id = ?) AND f.status = "active"
              AND p.status = "active"' . $processScope . '
            ORDER BY a.process_id ASC
            LIMIT 2
        ', $params);

        if (count($rows) !== 1) return null;
        $row = $rows[0];
        if (!empty($row['process_policy_snapshot_at']) && (string) ($row['latest_attempt_status'] ?? '') === 'in_progress') {
            $row['require_facial_enrollment'] = (int) ($row['process_facial_enrollment_required'] ?? 0);
            $row['component_validation_required'] = (int) ($row['process_component_validation_required'] ?? 0);
            $row['record_audio_visual'] = (int) ($row['process_record_audio_visual'] ?? 0);
            $row['record_activity_actions'] = (int) ($row['process_record_actions'] ?? 0);
        } elseif ((string) ($row['latest_attempt_status'] ?? '') !== 'in_progress') {
            $row = ProcessPrerequisiteService::resolve($row);
        }
        return $row;
    }

    public function syncEvaluationForms(int $processId, array $formIds): void
    {
        if (!$this->tableExists('test_process_evaluation_forms')) {
            return;
        }
        $formIds = array_values(array_unique(array_filter(array_map('intval', $formIds), static fn(int $id): bool => $id > 0)));
        $available = array_flip(array_map(static fn(array $form): int => (int) $form['id'], $this->activeEvaluationForms()));
        $formIds = array_values(array_filter($formIds, static fn(int $id): bool => isset($available[$id])));

        $this->db->transaction(function (Database $db) use ($processId, $formIds): void {
            $db->execute('DELETE FROM test_process_evaluation_forms WHERE process_id = ?', [$processId]);
            foreach ($formIds as $index => $formId) {
                $db->execute('INSERT INTO test_process_evaluation_forms (process_id, form_id, sort_order, is_required) VALUES (?, ?, ?, 0)', [$processId, $formId, ($index + 1) * 10]);
            }
        });
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
        // La grilla de asignación de participantes siempre trabaja con el
        // perfil funcional Usuario. El alcance por empresa se aplica en las
        // consultas de usuarios, no mediante perfiles configurables por proceso.
        return array_map('intval', array_column($this->db->fetchAll('
            SELECT id
            FROM ' . $this->coreSchema . '.role_profiles
            WHERE role_key = "usuario" AND is_active = 1
            ORDER BY id ASC
        '), 'id'));
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
        $policies = [];
        foreach (['facial_enrollment_policy', 'component_validation_policy', 'audio_visual_recording_policy', 'action_logging_policy'] as $policyField) {
            $policies[$policyField] = ProcessPrerequisiteService::policy($data[$policyField] ?? 'inherit');
        }
        $requireFacialEnrollment = $policies['facial_enrollment_policy'] === 'required' ? 1
            : ($policies['facial_enrollment_policy'] === 'disabled' ? 0
                : (isset($data['require_facial_enrollment']) ? 1 : ($processId > 0 ? null : 0)));

        return (int) $this->db->transaction(function (Database $db) use ($processId, $code, $name, $description, $status, $adminAssignmentMode, $availabilityStatus, $startsAt, $endsAt, $allowExpiredReopen, $requireFacialEnrollment, $policies, $createdBy, $companyId): int {
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
                if ($this->hasProcessFacialEnrollmentColumn() && $requireFacialEnrollment !== null) {
                    $fields[] = 'require_facial_enrollment = ?';
                    array_splice($params, count($params) - 1, 0, [$requireFacialEnrollment]);
                }
                foreach ($policies as $field => $value) {
                    if ($this->columnExists('test_processes', $field)) {
                        $fields[] = $field . ' = ?';
                        array_splice($params, count($params) - 1, 0, [$value]);
                    }
                }
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
                if ($this->hasProcessFacialEnrollmentColumn()) {
                    $columns[] = 'require_facial_enrollment';
                    $params[] = $requireFacialEnrollment ?? 0;
                }
                foreach ($policies as $field => $value) {
                    if ($this->columnExists('test_processes', $field)) {
                        $columns[] = $field;
                        $params[] = $value;
                    }
                }
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
            if ($instrumentIds) {
                $params = [];
                foreach ($instrumentIds as $index => $instrumentId) array_push($params, $processId, $instrumentId, ($index + 1) * 10);
                $db->execute(
                    'INSERT INTO test_process_instruments (process_id, instrument_id, sort_order, is_required) VALUES ' . implode(', ', array_fill(0, count($instrumentIds), '(?, ?, ?, 1)')),
                    $params
                );
            }
        });
    }

    public function syncFields(int $processId, array $fieldIds, array $requiredIds = []): void
    {
        $fieldIds = array_values(array_unique(array_filter(array_map('intval', $fieldIds), static fn(int $id): bool => $id >= 0)));
        $requiredSet = array_flip(array_map('intval', $requiredIds));

        $this->db->transaction(function (Database $db) use ($processId, $fieldIds, $requiredSet): void {
            $db->execute('DELETE FROM test_process_user_fields WHERE process_id = ?', [$processId]);
            if ($fieldIds) {
                $params = [];
                foreach ($fieldIds as $index => $fieldId) array_push($params, $processId, $fieldId, isset($requiredSet[$fieldId]) ? 1 : 0, ($index + 1) * 10);
                $db->execute(
                    'INSERT INTO test_process_user_fields (process_id, field_id, is_required, show_in_process, sort_order) VALUES ' . implode(', ', array_fill(0, count($fieldIds), '(?, ?, ?, 1, ?)')),
                    $params
                );
            }
        });
    }

    public function syncAssignableProfiles(int $processId, array $profileIds): void
    {
        $profileIds = array_values(array_unique(array_filter(array_map('intval', $profileIds), static fn(int $id): bool => $id > 0)));

        $this->db->transaction(function (Database $db) use ($processId, $profileIds): void {
            $db->execute('DELETE FROM test_process_assignable_profiles WHERE process_id = ?', [$processId]);
            if ($profileIds) {
                $params = [];
                foreach ($profileIds as $profileId) array_push($params, $processId, $profileId);
                $db->execute(
                    'INSERT INTO test_process_assignable_profiles (process_id, profile_id) VALUES ' . implode(', ', array_fill(0, count($profileIds), '(?, ?)')),
                    $params
                );
            }
        });
    }

    public function syncProfileAdmins(int $processId, array $profilePermissions): void
    {
        $allowed = array_keys(self::PROCESS_PERMISSIONS);

        $this->db->transaction(function (Database $db) use ($processId, $profilePermissions, $allowed): void {
            $db->execute('DELETE FROM test_process_profile_admins WHERE process_id = ?', [$processId]);
            $rows = [];
            foreach ($profilePermissions as $profileId => $permissions) {
                $profileId = (int) $profileId;
                if ($profileId <= 0 || !is_array($permissions)) {
                    continue;
                }

                $permissions = array_values(array_intersect($allowed, array_map('strval', $permissions)));
                if (!$permissions) {
                    continue;
                }

                $rows[] = [$processId, $profileId, json_encode(array_values(array_unique($permissions)), JSON_UNESCAPED_UNICODE)];
            }
            if ($rows) {
                $params = [];
                foreach ($rows as $row) array_push($params, ...$row);
                $db->execute(
                    'INSERT INTO test_process_profile_admins (process_id, profile_id, permissions) VALUES ' . implode(', ', array_fill(0, count($rows), '(?, ?, ?)')),
                    $params
                );
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
        $allowedUsers = array_map('intval', array_column($this->db->fetchAll("
            SELECT u.id
            FROM {$this->coreSchema}.users u
            JOIN {$this->coreSchema}.role_profiles p ON p.id = u.profile_id
            WHERE u.is_active = 1
              AND p.is_active = 1
              AND p.role_key = 'supervisor_sede'
              " . $this->companyUserScopeSql('u') . "
        ", $this->companyUserScopeParams()), 'id'));
        $allowedUserSet = array_fill_keys($allowedUsers, true);

        $this->db->transaction(function (Database $db) use ($processId, $userPermissions, $allowed, $allowedUserSet): void {
            $db->execute('DELETE FROM test_process_user_admins WHERE process_id = ?', [$processId]);
            $rows = [];
            foreach ($userPermissions as $userId => $permissions) {
                $userId = (int) $userId;
                if ($userId <= 0 || !isset($allowedUserSet[$userId]) || !is_array($permissions)) {
                    continue;
                }

                $permissions = array_values(array_intersect($allowed, array_map('strval', $permissions)));
                if (!$permissions) {
                    continue;
                }

                $rows[] = [$processId, $userId, json_encode(array_values(array_unique($permissions)), JSON_UNESCAPED_UNICODE)];
            }
            if ($rows) {
                $params = [];
                foreach ($rows as $row) array_push($params, ...$row);
                $db->execute(
                    'INSERT INTO test_process_user_admins (process_id, user_id, permissions) VALUES ' . implode(', ', array_fill(0, count($rows), '(?, ?, ?)')),
                    $params
                );
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

    public function assignmentReviewByRut(string $rut, int $companyId = 0): array
    {
        $formattedRut = UserModel::formatRut($rut);
        $companySql = $companyId > 0 ? ' AND u.company_id = ?' : '';
        $user = $this->db->fetch("
            SELECT u.id, u.rut, u.name, u.email, u.profile_id, u.is_active, c.name AS company_name
            FROM {$this->coreSchema}.users u
            LEFT JOIN {$this->coreSchema}.companies c ON c.id = u.company_id
            WHERE u.rut = ? {$companySql}
            LIMIT 1
        ", array_merge([$formattedRut], $companyId > 0 ? [$companyId] : []));

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
        $assignments = $this->assignmentReviewRows($userId, $companyId);
        $sessions = $this->assignmentReviewSessions($userId, $companyId);
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
            'candidate_processes' => $this->runningProcessesForReassignment($profileId, $companyId),
        ];
    }

    public function reassignUserProcess(string $rut, int $sourceProcessId, int $targetProcessId, int $assignedBy, bool $confirmDeleteAnswers = false, int $companyId = 0): array
    {
        $formattedRut = UserModel::formatRut($rut);
        if ($sourceProcessId <= 0 || $targetProcessId <= 0 || $sourceProcessId === $targetProcessId) {
            return ['ok' => false, 'message' => 'Selecciona un proceso origen y un proceso destino distintos.'];
        }

        return $this->db->transaction(function (Database $db) use ($formattedRut, $sourceProcessId, $targetProcessId, $assignedBy, $confirmDeleteAnswers, $companyId): array {
            $dayStart = date('Y-m-d 00:00:00');
            $dayEnd = date('Y-m-d 00:00:00', strtotime('+1 day'));
            $companySql = $companyId > 0 ? ' AND u.company_id = ?' : '';
            $user = $db->fetch("
                SELECT id, rut, name, profile_id, company_id
                FROM {$this->coreSchema}.users
                WHERE rut = ? {$companySql}
                LIMIT 1
            ", array_merge([$formattedRut], $companyId > 0 ? [$companyId] : []));

            if (!$user) {
                return ['ok' => false, 'message' => 'No se encontro un usuario con el RUT indicado.'];
            }

            $userId = (int) ($user['id'] ?? 0);
            $source = $db->fetch('
                SELECT pu.process_id, p.name, p.code
                FROM test_process_users pu
                JOIN test_processes p ON p.id = pu.process_id
                WHERE pu.process_id = ? AND pu.user_id = ? AND pu.status <> "cancelled"
                  AND (? = 0 OR p.company_id = ?)
                LIMIT 1
                FOR UPDATE
            ', [$sourceProcessId, $userId, $companyId, $companyId]);

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
                      (p.starts_at >= ? AND p.starts_at < ?)
                      OR (p.ends_at >= ? AND p.ends_at < ?)
                  )
                  AND (? = 0 OR p.company_id = ?)
                GROUP BY p.id
                LIMIT 1
            ', [$targetProcessId, $dayStart, $dayEnd, $dayStart, $dayEnd, $companyId, $companyId]);

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
                JOIN test_answers a ON a.session_id = ts.id AND a.answer_value IS NOT NULL AND TRIM(a.answer_value) <> ""
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

    private function assignmentReviewRows(int $userId, int $companyId = 0): array
    {
        $companySql = $companyId > 0 ? ' AND p.company_id = ?' : '';
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
                SELECT session_id, SUM(CASE WHEN answer_value IS NOT NULL AND TRIM(answer_value) <> "" THEN 1 ELSE 0 END) AS answers_count
                FROM test_answers
                GROUP BY session_id
            ) answer_stats ON answer_stats.session_id = ts.id
            WHERE pu.user_id = ? AND pu.status <> "cancelled"' . $companySql . '
            GROUP BY pu.process_id, pu.status, p.name, p.code, p.status, p.starts_at, p.ends_at
            ORDER BY p.starts_at DESC, p.id DESC
        ', array_merge([$userId], $companyId > 0 ? [$companyId] : []));
    }

    private function assignmentReviewSessions(int $userId, int $companyId = 0): array
    {
        $companySql = $companyId > 0 ? ' AND p.company_id = ?' : '';
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
            JOIN test_processes p ON p.id = ts.process_id
            LEFT JOIN (
                SELECT session_id, SUM(CASE WHEN answer_value IS NOT NULL AND TRIM(answer_value) <> "" THEN 1 ELSE 0 END) AS answers_count
                FROM test_answers
                GROUP BY session_id
            ) answer_stats ON answer_stats.session_id = ts.id
            WHERE ts.user_id = ? AND ts.process_id IS NOT NULL' . $companySql . '
            ORDER BY ts.process_id DESC, i.name ASC
        ', array_merge([$userId], $companyId > 0 ? [$companyId] : []));

        $byProcess = [];
        foreach ($rows as $row) {
            $byProcess[(int) ($row['process_id'] ?? 0)][] = $row;
        }

        return $byProcess;
    }

    private function runningProcessesForReassignment(int $profileId, int $companyId = 0): array
    {
        if ($profileId <= 0 || !$this->tableExists('test_process_assignable_profiles')) {
            return [];
        }

        $dayStart = date('Y-m-d 00:00:00');
        $dayEnd = date('Y-m-d 00:00:00', strtotime('+1 day'));
        $companySql = $companyId > 0 ? ' AND p.company_id = ?' : '';
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
                  (p.starts_at >= ? AND p.starts_at < ?)
                  OR (p.ends_at >= ? AND p.ends_at < ?)
              )' . $companySql . '
            GROUP BY p.id, p.name, p.code, p.status, p.starts_at, p.ends_at
            ORDER BY p.starts_at ASC, p.name ASC
        ', array_merge([$profileId, $dayStart, $dayEnd, $dayStart, $dayEnd], $companyId > 0 ? [$companyId] : []));
    }

    public function assignUsers(int $processId, array $userIds, ?int $assignedBy = null): array
    {
        $userIds = array_values(array_unique(array_filter(array_map('intval', $userIds), static fn(int $id): bool => $id > 0)));
        if (!$userIds) {
            return ['created' => 0, 'existing' => 0, 'sessions_created' => 0, 'sessions_existing' => 0, 'sessions_requested' => 0];
        }

        return $this->db->transaction(function (Database $db) use ($processId, $userIds, $assignedBy): array {
            $existingUserIds = [];
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

            // Una persona puede participar en más de un proceso. La clave
            // única del proceso evita duplicarla dentro del mismo proceso y
            // el UPSERT reactiva una asignación previamente cancelada.
            $assignableUserIds = $userIds;
            foreach ($assignableUserIds as $userId) {
                $db->execute('
                    INSERT INTO test_process_users (process_id, user_id, status)
                    VALUES (?, ?, "assigned")
                    ON DUPLICATE KEY UPDATE status = "assigned"
                ', [$processId, $userId]);
            }

            $sessionResult = $this->createMissingSessionsForUsers($db, $processId, $assignableUserIds, $assignedBy);
            $evaluationResult = $this->createMissingEvaluationAssignments($db, $processId, $assignableUserIds);
            $existing = count($existingUserIds);

            return [
                'created' => max(0, count($assignableUserIds) - $existing),
                'existing' => $existing,
                'sessions_created' => (int) $sessionResult['created'],
                'sessions_existing' => (int) $sessionResult['existing'],
                'sessions_requested' => (int) $sessionResult['requested'],
                'evaluations_created' => (int) $evaluationResult['created'],
                'evaluations_existing' => (int) $evaluationResult['existing'],
                'evaluations_requested' => (int) $evaluationResult['requested'],
            ];
        });
    }

    public function removeUser(int $processId, int $userId): void
    {
        $this->removeEvaluationAttemptsForProcessUser($processId, $userId);
        $media = new TestMediaEvidenceModel($this->db);
        $sessions = $this->db->fetchAll('SELECT id FROM test_sessions WHERE process_id = ? AND user_id = ?', [$processId, $userId]);
        foreach ($sessions as $session) {
            $media->deleteForSession((int) ($session['id'] ?? 0));
        }
        $this->db->transaction(function (Database $db) use ($processId, $userId): void {
            $db->execute('
                DELETE FROM test_sessions
                WHERE process_id = ? AND user_id = ?
            ', [$processId, $userId]);
            if ($this->tableExists('test_process_evaluation_assignments')) {
                $db->execute('DELETE FROM test_process_evaluation_assignments WHERE process_id = ? AND user_id = ?', [$processId, $userId]);
            }
            $db->execute('DELETE FROM test_process_users WHERE process_id = ? AND user_id = ?', [$processId, $userId]);
        });
    }

    public function removeAllUsers(int $processId): int
    {
        $userRows = $this->db->fetchAll('SELECT user_id FROM test_process_users WHERE process_id = ? AND status <> "cancelled"', [$processId]);
        foreach ($userRows as $userRow) {
            $this->removeEvaluationAttemptsForProcessUser($processId, (int) ($userRow['user_id'] ?? 0));
        }

        $media = new TestMediaEvidenceModel($this->db);
        $sessions = $this->db->fetchAll('SELECT id FROM test_sessions WHERE process_id = ?', [$processId]);
        foreach ($sessions as $session) {
            $media->deleteForSession((int) ($session['id'] ?? 0));
        }

        return (int) $this->db->transaction(function (Database $db) use ($processId): int {
            $countRow = $db->fetch('SELECT COUNT(*) AS total FROM test_process_users WHERE process_id = ?', [$processId]);
            $total = (int) ($countRow['total'] ?? 0);

            $db->execute('DELETE FROM test_sessions WHERE process_id = ?', [$processId]);
            if ($this->tableExists('test_process_evaluation_assignments')) {
                $db->execute('DELETE FROM test_process_evaluation_assignments WHERE process_id = ?', [$processId]);
            }
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
        $resultConditions = ["ts.status IN ('completed', 'expired')"];

        if ($this->hasSessionScoreSummaryColumn()) {
            $resultConditions[] = "(ts.score_summary IS NOT NULL AND ts.score_summary <> '' AND ts.score_summary <> '[]')";
        }

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
        $unassignedSessionIds = [];
        foreach ($instrumentIds as $instrumentId) {
            foreach ($userIds as $userId) {
                $row = $existingByPair[$instrumentId . ':' . $userId] ?? null;
                if ($row) {
                    $existing++;
                    if ($row['process_id'] === null) {
                        $unassignedSessionIds[] = (int) $row['id'];
                    }
                } else {
                    $created++;
                }
            }
        }
        if ($unassignedSessionIds) {
            $placeholders = implode(',', array_fill(0, count($unassignedSessionIds), '?'));
            $db->execute('UPDATE test_sessions SET process_id = ? WHERE id IN (' . $placeholders . ') AND process_id IS NULL', array_merge([$processId], $unassignedSessionIds));
        }
        if ($created > 0) {
            $instrumentPlaceholders = implode(',', array_fill(0, count($instrumentIds), '?'));
            $userPlaceholders = implode(',', array_fill(0, count($userIds), '?'));
            $db->execute('
                INSERT INTO test_sessions (process_id, instrument_id, user_id, assigned_by, status, control_mode)
                SELECT ?, i.id, u.id, ?, "assigned", i.control_mode
                FROM test_instruments i
                CROSS JOIN ' . $this->coreSchema . '.users u
                WHERE i.id IN (' . $instrumentPlaceholders . ') AND u.id IN (' . $userPlaceholders . ')
                  AND NOT EXISTS (
                      SELECT 1 FROM test_sessions existing
                      WHERE existing.instrument_id = i.id AND existing.user_id = u.id
                        AND existing.status IN ("assigned", "in_progress", "completed", "expired")
                        AND (existing.process_id = ? OR existing.process_id IS NULL)
                  )
            ', array_merge([$processId, $assignedBy], $instrumentIds, $userIds, [$processId]));
        }

        return ['created' => $created, 'existing' => $existing, 'requested' => count($instrumentIds) * count($userIds)];
    }

    private function createMissingEvaluationAssignments(Database $db, int $processId, array $userIds): array
    {
        if (!$this->tableExists('test_process_evaluation_assignments')) {
            return ['created' => 0, 'existing' => 0, 'requested' => 0];
        }

        $formIds = $this->selectedEvaluationFormIds($processId);
        if (!$formIds || !$userIds) {
            return ['created' => 0, 'existing' => 0, 'requested' => 0];
        }

        $formPlaceholders = implode(',', array_fill(0, count($formIds), '?'));
        $userPlaceholders = implode(',', array_fill(0, count($userIds), '?'));
        $existingRows = $db->fetchAll(
            'SELECT form_id, user_id FROM test_process_evaluation_assignments WHERE process_id = ? AND form_id IN (' . $formPlaceholders . ') AND user_id IN (' . $userPlaceholders . ')',
            array_merge([$processId], $formIds, $userIds)
        );
        $existingPairs = [];
        foreach ($existingRows as $row) {
            $existingPairs[(int) $row['form_id'] . ':' . (int) $row['user_id']] = true;
        }
        $missingRows = [];
        $existing = 0;
        foreach ($formIds as $formId) {
            $existingUserIds = [];
            foreach ($userIds as $userId) {
                if (isset($existingPairs[$formId . ':' . $userId])) {
                    $existing++;
                    $existingUserIds[] = $userId;
                } else {
                    $missingRows[] = [$processId, $formId, $userId];
                }
            }
            if ($existingUserIds) {
                $placeholders = implode(',', array_fill(0, count($existingUserIds), '?'));
                $db->execute(
                    'UPDATE test_process_evaluation_assignments SET status = "assigned" WHERE process_id = ? AND form_id = ? AND user_id IN (' . $placeholders . ')',
                    array_merge([$processId, $formId], $existingUserIds)
                );
            }
        }
        $created = count($missingRows);
        foreach (array_chunk($missingRows, 500) as $chunk) {
            $params = [];
            foreach ($chunk as $row) array_push($params, ...$row);
            $db->execute(
                'INSERT INTO test_process_evaluation_assignments (process_id, form_id, user_id, status) VALUES ' . implode(', ', array_fill(0, count($chunk), '(?, ?, ?, "assigned")')) . ' ON DUPLICATE KEY UPDATE status = "assigned"',
                $params
            );
        }

        return ['created' => $created, 'existing' => $existing, 'requested' => count($formIds) * count($userIds)];
    }

    private function removeEvaluationAttemptsForProcessUser(int $processId, int $userId): void
    {
        if (!$this->tableExists('test_process_evaluation_assignments') || $userId <= 0) {
            return;
        }

        $rows = $this->db->fetchAll('
            SELECT form_id
            FROM test_process_evaluation_assignments
            WHERE process_id = ? AND user_id = ?
        ', [$processId, $userId]);
        if (!$rows) {
            return;
        }

        $evaluationDb = database('evaluaciones_encuestas');
        $media = new EvaluationSurveyMediaEvidenceModel($evaluationDb);
        foreach ($rows as $row) {
            $formId = (int) ($row['form_id'] ?? 0);
            if ($formId <= 0) {
                continue;
            }

            $attempts = $evaluationDb->fetchAll('SELECT id FROM evaluation_survey_attempts WHERE process_id = ? AND form_id = ? AND user_id = ?', [$processId, $formId, $userId]);
            foreach ($attempts as $attempt) {
                $media->deleteForAttempt((int) ($attempt['id'] ?? 0));
            }

            // Las tablas de respuestas, actividad y evidencia audiovisual
            // dependen del intento y se eliminan por las FK ON DELETE CASCADE.
            $evaluationDb->execute('DELETE FROM evaluation_survey_attempts WHERE form_id = ? AND user_id = ?', [$formId, $userId]);
        }
    }

    public function cancelSession(int $processId, int $sessionId): bool
    {
        return $this->db->execute('
            UPDATE test_sessions
            SET status = "cancelled"
            WHERE id = ? AND process_id = ? AND status IN ("assigned", "in_progress")
        ', [$sessionId, $processId]) > 0;
    }

    public function cancelEvaluationAssignment(int $processId, int $formId, int $userId): bool
    {
        if (!$this->tableExists('test_process_evaluation_assignments')) {
            return false;
        }

        return $this->db->execute('
            UPDATE test_process_evaluation_assignments
            SET status = "cancelled"
            WHERE process_id = ? AND form_id = ? AND user_id = ? AND status = "assigned"
        ', [$processId, $formId, $userId]) > 0;
    }

    public function resetEvaluationAssignment(int $processId, int $formId, int $userId): bool
    {
        if (!$this->tableExists('test_process_evaluation_assignments') || $formId <= 0 || $userId <= 0) {
            return false;
        }

        $assignment = $this->db->fetch('
            SELECT id, status
            FROM test_process_evaluation_assignments
            WHERE process_id = ? AND form_id = ? AND user_id = ? AND status <> "cancelled"
            LIMIT 1
        ', [$processId, $formId, $userId]);
        if (!$assignment || (string) ($assignment['status'] ?? '') === 'cancelled') {
            return false;
        }

        $otherAssignment = $this->db->fetch('
            SELECT id
            FROM test_process_evaluation_assignments
            WHERE form_id = ? AND user_id = ? AND process_id <> ? AND status <> "cancelled"
            LIMIT 1
        ', [$formId, $userId, $processId]);
        if ($otherAssignment) {
            return false;
        }

        $evaluationDb = database('evaluaciones_encuestas');
        $media = new EvaluationSurveyMediaEvidenceModel($evaluationDb);
        $attempts = $evaluationDb->fetchAll('SELECT id FROM evaluation_survey_attempts WHERE form_id = ? AND user_id = ?', [$formId, $userId]);
        foreach ($attempts as $attempt) {
            $media->deleteForAttempt((int) ($attempt['id'] ?? 0));
        }
        $evaluationDb->execute(
            'DELETE FROM evaluation_survey_attempts WHERE form_id = ? AND user_id = ?',
            [$formId, $userId]
        );

        $this->db->execute('
            UPDATE test_process_evaluation_assignments
            SET status = "assigned"
            WHERE process_id = ? AND form_id = ? AND user_id = ? AND status <> "cancelled"
        ', [$processId, $formId, $userId]) > 0;

        // MySQL reports 0 affected rows when the assignment was already
        // "assigned". The reset is still successful because the attempt and
        // its answers/evidence were removed above.
        return (bool) $this->db->fetch('
            SELECT id
            FROM test_process_evaluation_assignments
            WHERE process_id = ? AND form_id = ? AND user_id = ? AND status = "assigned"
            LIMIT 1
        ', [$processId, $formId, $userId]);
    }

    public function reopenEvaluationAssignment(int $processId, int $formId, int $userId, int $durationMinutes, array $authorizedBy = []): bool
    {
        if (!$this->tableExists('test_process_evaluation_assignments') || $processId <= 0 || $formId <= 0 || $userId <= 0) {
            return false;
        }

        $durationMinutes = max(1, min(120, $durationMinutes));
        $assignment = $this->db->fetch('
            SELECT a.id
            FROM test_process_evaluation_assignments a
            JOIN test_processes p ON p.id = a.process_id
            WHERE a.process_id = ? AND a.form_id = ? AND a.user_id = ? AND a.status <> "cancelled"
              AND p.allow_expired_reopen = 1
              AND p.status = "active"
              AND (p.starts_at IS NULL OR p.starts_at <= NOW())
              AND (p.ends_at IS NULL OR p.ends_at >= NOW())
              AND (p.availability_status IS NULL OR p.availability_status <> "closed_now")
            LIMIT 1
        ', [$processId, $formId, $userId]);
        if (!$assignment) {
            return false;
        }

        $evaluationDb = database('evaluaciones_encuestas');
        return (bool) $evaluationDb->transaction(function (Database $db) use ($processId, $formId, $userId, $durationMinutes, $authorizedBy): bool {
            $attempt = $db->fetch('
                SELECT id, form_id, user_id, attempt_number
                FROM evaluation_survey_attempts
                WHERE process_id = ? AND form_id = ? AND user_id = ? AND status IN ("in_progress", "completed", "expired")
                ORDER BY attempt_number DESC, id DESC
                LIMIT 1
                FOR UPDATE
            ', [$processId, $formId, $userId]);
            if (!$attempt) {
                return false;
            }

            $expiresAt = date('Y-m-d H:i:s', time() + ($durationMinutes * 60));
            $updated = $db->execute('
                UPDATE evaluation_survey_attempts
                SET status = "in_progress", expires_at = ?, completed_at = NULL,
                    raw_score = NULL, final_score = NULL, passed = NULL, last_seen_at = NOW()
                WHERE id = ? AND status IN ("in_progress", "completed", "expired")
            ', [$expiresAt, (int) $attempt['id']]);
            if ($updated <= 0) {
                return false;
            }

            $authorizedByName = trim((string) ($authorizedBy['name'] ?? ''));
            if ($authorizedByName === '' && (int) ($authorizedBy['id'] ?? 0) > 0) {
                $authorizedByName = 'Usuario #' . (int) $authorizedBy['id'];
            }
            $metadata = [
                'duration_minutes' => $durationMinutes,
                'expires_at' => $expiresAt,
                'authorized_by_id' => (int) ($authorizedBy['id'] ?? 0),
                'authorized_by_name' => $authorizedByName,
                'authorized_by_email' => trim((string) ($authorizedBy['email'] ?? '')),
                'authorized_by_profile' => trim((string) ($authorizedBy['profile_name'] ?? '')),
                'source' => 'process_admin',
            ];
            $db->execute('
                INSERT INTO evaluation_survey_activity_events (attempt_id, form_id, user_id, event_type, metadata)
                VALUES (?, ?, ?, "evaluation_reopened_by_admin", ?)
            ', [(int) $attempt['id'], $formId, $userId, json_encode($metadata, JSON_UNESCAPED_UNICODE)]);

            return true;
        });
    }

    public function reopenExpiredEvaluationAssignment(int $processId, int $formId, int $userId, int $durationMinutes, array $authorizedBy = []): bool
    {
        return $this->reopenEvaluationAssignment($processId, $formId, $userId, $durationMinutes, $authorizedBy);
    }

    public function reopenExpiredEvaluationAssignments(int $processId, int $formId, int $durationMinutes, array $authorizedBy = []): array
    {
        if (!$this->tableExists('test_process_evaluation_assignments') || $processId <= 0 || $formId <= 0) {
            return ['reopened' => 0, 'eligible' => 0, 'allowed' => false, 'target_found' => false];
        }

        $durationMinutes = max(1, min(120, $durationMinutes));
        $process = $this->db->fetch('SELECT id, allow_expired_reopen, status, starts_at, ends_at FROM test_processes WHERE id = ? LIMIT 1', [$processId]);
        if (!$process || (int) ($process['allow_expired_reopen'] ?? 0) !== 1 || (string) ($process['status'] ?? '') !== 'active') {
            return ['reopened' => 0, 'eligible' => 0, 'allowed' => false, 'target_found' => false];
        }

        $target = $this->db->fetch('SELECT id FROM test_process_evaluation_forms WHERE process_id = ? AND form_id = ? LIMIT 1', [$processId, $formId]);
        if (!$target) {
            return ['reopened' => 0, 'eligible' => 0, 'allowed' => true, 'target_found' => false];
        }

        $evaluationDb = database('evaluaciones_encuestas');
        return $evaluationDb->transaction(function (Database $db) use ($processId, $formId, $durationMinutes, $authorizedBy): array {
            $attempts = $db->fetchAll('
                SELECT id, user_id
                FROM evaluation_survey_attempts
                WHERE process_id = ? AND form_id = ? AND status = "expired"
                ORDER BY id ASC
                FOR UPDATE
            ', [$processId, $formId]);
            if (!$attempts) {
                return ['reopened' => 0, 'eligible' => 0, 'allowed' => true, 'target_found' => true];
            }

            $expiresAt = date('Y-m-d H:i:s', time() + ($durationMinutes * 60));
            $ids = array_map(static fn(array $attempt): int => (int) ($attempt['id'] ?? 0), $attempts);
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $updated = $db->execute(
                'UPDATE evaluation_survey_attempts SET status = "in_progress", expires_at = ?, completed_at = NULL, raw_score = NULL, final_score = NULL, passed = NULL, last_seen_at = NOW() WHERE id IN (' . $placeholders . ') AND status = "expired"',
                array_merge([$expiresAt], $ids)
            );

            $authorizedByName = trim((string) ($authorizedBy['name'] ?? ''));
            if ($authorizedByName === '' && (int) ($authorizedBy['id'] ?? 0) > 0) {
                $authorizedByName = 'Usuario #' . (int) $authorizedBy['id'];
            }
            $metadata = json_encode([
                'duration_minutes' => $durationMinutes,
                'expires_at' => $expiresAt,
                'authorized_by_id' => (int) ($authorizedBy['id'] ?? 0),
                'authorized_by_name' => $authorizedByName,
                'authorized_by_email' => trim((string) ($authorizedBy['email'] ?? '')),
                'authorized_by_profile' => trim((string) ($authorizedBy['profile_name'] ?? '')),
                'bulk_reopen' => true,
                'process_id' => $processId,
                'form_id' => $formId,
            ], JSON_UNESCAPED_UNICODE);
            foreach ($attempts as $attempt) {
                $db->execute(
                    'INSERT INTO evaluation_survey_activity_events (attempt_id, form_id, user_id, event_type, metadata) VALUES (?, ?, ?, "evaluation_reopened_by_admin", ?)',
                    [(int) ($attempt['id'] ?? 0), $formId, (int) ($attempt['user_id'] ?? 0), $metadata]
                );
            }

            return ['reopened' => (int) $updated, 'eligible' => count($attempts), 'allowed' => true, 'target_found' => true];
        });
    }

    public function resetSession(int $processId, int $sessionId): bool
    {
        $session = $this->db->fetch('
            SELECT id
            FROM test_sessions
            WHERE id = ? AND process_id = ? AND status IN ("completed", "expired")
            LIMIT 1
        ', [$sessionId, $processId]);
        if (!$session) {
            return false;
        }

        (new TestMediaEvidenceModel($this->db))->deleteForSession($sessionId);
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

    public function reopenSession(int $processId, int $sessionId, int $durationMinutes, array $authorizedBy = []): bool
    {
        $durationMinutes = max(1, min(120, $durationMinutes));

        return (bool) $this->db->transaction(function (Database $db) use ($processId, $sessionId, $durationMinutes, $authorizedBy): bool {
            $session = $db->fetch('
                SELECT ts.id
                FROM test_sessions ts
                JOIN test_processes p ON p.id = ts.process_id
                WHERE ts.id = ?
                  AND ts.process_id = ?
                  AND ts.status IN ("in_progress", "completed", "expired")
                  AND p.allow_expired_reopen = 1
                  AND p.status = "active"
                  AND (p.starts_at IS NULL OR p.starts_at <= NOW())
                  AND (p.ends_at IS NULL OR p.ends_at >= NOW())
                  AND (p.availability_status IS NULL OR p.availability_status <> "closed_now")
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
            if ($this->hasPausedRemainingColumn()) {
                $fields[] = 'paused_remaining_seconds = NULL';
            }

            $db->execute('
                UPDATE test_sessions
                SET ' . implode(', ', $fields) . '
                WHERE id = ? AND process_id = ? AND status IN ("in_progress", "completed", "expired")
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

    public function reopenExpiredSession(int $processId, int $sessionId, int $durationMinutes, array $authorizedBy = []): bool
    {
        return $this->reopenSession($processId, $sessionId, $durationMinutes, $authorizedBy);
    }

    public function reopenSavedSession(int $processId, int $sessionId, int $durationMinutes, array $authorizedBy = []): bool
    {
        return $this->reopenSession($processId, $sessionId, $durationMinutes, $authorizedBy);
    }

    public function reopenExpiredSessionsForInstrument(int $processId, int $instrumentId, int $durationMinutes, array $authorizedBy = []): array
    {
        $durationMinutes = max(1, min(120, $durationMinutes));

        return $this->db->transaction(function (Database $db) use ($processId, $instrumentId, $durationMinutes, $authorizedBy): array {
            $process = $db->fetch('
                SELECT id, allow_expired_reopen
                FROM test_processes
                WHERE id = ?
                LIMIT 1
            ', [$processId]);

            if (!$process || (int) ($process['allow_expired_reopen'] ?? 0) !== 1) {
                return ['reopened' => 0, 'eligible' => 0, 'skipped' => 0, 'allowed' => false, 'target_found' => false];
            }

            $instrumentInProcess = $db->fetch('
                SELECT 1
                FROM test_process_instruments
                WHERE process_id = ? AND instrument_id = ?
                LIMIT 1
            ', [$processId, $instrumentId]);

            if (!$instrumentInProcess) {
                return ['reopened' => 0, 'eligible' => 0, 'skipped' => 0, 'allowed' => true, 'instrument_found' => false, 'target_found' => false];
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
                return ['reopened' => 0, 'eligible' => 0, 'skipped' => 0, 'allowed' => true, 'instrument_found' => true, 'target_found' => true];
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
                'target_found' => true,
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
        $assignableProfileIds = $this->selectedAssignableProfileIds($processId);
        if (!$assignableProfileIds) {
            return [];
        }

        $profileFilterSql = 'u.profile_id IN (' . implode(',', array_fill(0, count($assignableProfileIds), '?')) . ')';
        $companySql = $this->companyUserScopeSql('u');
        $rows = $this->db->fetchAll("
            SELECT u.id, u.name, u.email, u.rut, u.age, c.name AS company_name
            FROM {$this->coreSchema}.users u
            LEFT JOIN {$this->coreSchema}.companies c ON c.id = u.company_id
            WHERE u.is_active = 1
              AND {$profileFilterSql}
              {$companySql}
              AND NOT EXISTS (
                  SELECT 1 FROM test_process_users pu
                  WHERE pu.process_id = ? AND pu.user_id = u.id AND pu.status <> 'cancelled'
              )
            ORDER BY c.name, u.name
        ", array_merge($assignableProfileIds, $this->companyUserScopeParams(), [$processId]));

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
        $companySql = $this->companyUserScopeSql('u');
        $companyParams = $this->companyUserScopeParams();

        $companies = array_values(array_filter(array_map(
            static fn(array $row): string => trim((string) ($row['company_name'] ?? '')),
            $this->db->fetchAll("
                SELECT DISTINCT c.name AS company_name
                FROM {$this->coreSchema}.users u
                LEFT JOIN {$this->coreSchema}.companies c ON c.id = u.company_id
                WHERE u.is_active = 1
                  AND {$profileFilterSql}
                  {$companySql}
                  AND c.name IS NOT NULL
                  AND c.name <> ''
                ORDER BY c.name
            ", array_merge($assignableProfileIds, $companyParams))
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
                      {$companySql}
                      AND u.age IS NOT NULL
                      AND u.age <> ''
                    ORDER BY CAST(u.age AS UNSIGNED), u.age
                ", array_merge($assignableProfileIds, $companyParams));
            } else {
                $rows = $this->db->fetchAll("
                    SELECT DISTINCT v.value
                    FROM {$this->coreSchema}.user_field_values v
                    JOIN {$this->coreSchema}.users u ON u.id = v.user_id AND u.is_active = 1
                    WHERE v.field_id = ?
                      AND {$profileFilterSql}
                      {$companySql}
                      AND v.value IS NOT NULL
                      AND v.value <> ''
                    ORDER BY v.value
                ", array_merge([$fieldId], $assignableProfileIds, $companyParams));
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
        $rollupAvailable = $this->tableExists('test_session_rollups');
        $answersSelect = $rollupAvailable ? 'COALESCE(sr.answered_count, 0) AS answers_count' : 'COALESCE(ac.answers_count, 0) AS answers_count';
        $params = [];

        if (!$rollupAvailable && $this->tableExists('test_activity_events')) {
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

        $answersJoin = $rollupAvailable
            ? 'LEFT JOIN test_session_rollups sr ON sr.session_id = ts.id'
            : 'LEFT JOIN (
                SELECT ta.session_id, COUNT(DISTINCT ta.item_id) AS answers_count
                FROM test_sessions ats
                STRAIGHT_JOIN test_answers ta ON ta.session_id = ats.id
                JOIN test_items ti ON ti.id = ta.item_id AND ti.is_active = 1
                WHERE ats.process_id = ?
                  AND ta.answer_value IS NOT NULL
                  AND TRIM(ta.answer_value) <> ""
                GROUP BY ta.session_id
            ) ac ON ac.session_id = ts.id';
        if (!$rollupAvailable) {
            $params[] = $processId;
        }
        $params[] = $processId;

        return $this->db->fetchAll("
            SELECT ts.*, i.name AS instrument_name, i.code AS instrument_code, i.duration_minutes, u.name AS user_name, u.email AS user_email, u.rut AS user_rut,
                   COALESCE(ic.items_count, 0) AS items_count,
                   {$answersSelect},
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
            {$answersJoin}
            {$activityJoin}
            WHERE ts.process_id = ?
            ORDER BY u.name, i.name
        ", $params);
    }

    public function processDashboardSessions(int $processId): array
    {
        $activitySelect = '0 AS activity_events_total, 0 AS activity_attention_total, 0 AS activity_risk_total, NULL AS last_activity_event_at';
        $activityJoin = '';
        $rollupAvailable = $this->tableExists('test_session_rollups');
        $params = [$processId];

        if ($rollupAvailable) {
            $activitySelect = 'COALESCE(sr.activity_events_total, 0) AS activity_events_total, COALESCE(sr.activity_attention_total, 0) AS activity_attention_total, COALESCE(sr.activity_risk_total, 0) AS activity_risk_total, sr.last_activity_event_at AS last_activity_event_at';
            $activityJoin = 'LEFT JOIN test_session_rollups sr ON sr.session_id = ts.id';
        } elseif ($this->tableExists('test_activity_events')) {
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

        $summary = array_map('intval', $row ?: []);
        if ($this->tableExists('test_process_evaluation_forms')) {
            $summary['instruments_total'] += (int) ($this->db->fetch(
                'SELECT COUNT(*) AS total FROM test_process_evaluation_forms WHERE process_id = ?',
                [$processId]
            )['total'] ?? 0);
        }
        if ($this->tableExists('test_process_evaluation_assignments')) {
            foreach ($this->processEvaluationAssignments($processId) as $assignment) {
                if ((string) ($assignment['assignment_status'] ?? '') === 'cancelled') {
                    continue;
                }
                $status = (string) ($assignment['evaluation_status'] ?? 'assigned');
                if ($status === 'assigned') {
                    $summary['assigned_total']++;
                } elseif ($status === 'in_progress') {
                    $summary['in_progress_total']++;
                } elseif ($status === 'completed') {
                    $summary['completed_total']++;
                } elseif ($status === 'expired') {
                    $summary['expired_total']++;
                }
            }
        }

        return $summary;
    }

    public function exportResults(int $processId): array
    {
        $process = $this->find($processId);
        if (!$process) {
            throw new RuntimeException('Proceso no encontrado.');
        }

        $answerCountsSql = '
            SELECT session_id, SUM(CASE WHEN answer_value IS NOT NULL AND TRIM(answer_value) <> "" THEN 1 ELSE 0 END) AS answers_count
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
                SELECT session_id, SUM(CASE WHEN answer_value IS NOT NULL AND TRIM(answer_value) <> "" THEN 1 ELSE 0 END) AS answers_count
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
        $sessionCountsSql = '0 AS completed_sessions, 0 AS answered_sessions, 0 AS in_progress_sessions, 0 AS expired_sessions, 0 AS sessions_count';
        $onlineUsersSql = '0 AS online_users_count';
        $onlineSurveySql = '';
        $evaluationProgressJoinSql = '';
        $evaluationCountsSql = '
            0 AS evaluation_forms_count,
            0 AS evaluation_assignments_count,
            0 AS completed_evaluation_assignments,
            0 AS answered_evaluation_assignments,
            0 AS in_progress_evaluation_assignments,
            0 AS expired_evaluation_assignments
        ';
        $evaluationsCountSql = '(SELECT COUNT(*) FROM test_process_instruments pi WHERE pi.process_id = p.id)';

        if ($this->hasSessionProcessColumn()) {
            $sessionCountsSql = '
                (SELECT COUNT(*) FROM test_sessions ts WHERE ts.process_id = p.id AND ts.status = "completed") AS completed_sessions,
                (SELECT COUNT(DISTINCT ts.id) FROM test_sessions ts JOIN test_answers ta ON ta.session_id = ts.id AND ta.answer_value IS NOT NULL AND TRIM(ta.answer_value) <> "" WHERE ts.process_id = p.id AND ts.status <> "cancelled") AS answered_sessions,
                (SELECT COUNT(*) FROM test_sessions ts WHERE ts.process_id = p.id AND ts.status = "in_progress") AS in_progress_sessions,
                (SELECT COUNT(*) FROM test_sessions ts WHERE ts.process_id = p.id AND ts.status = "expired") AS expired_sessions,
                (SELECT COUNT(*) FROM test_sessions ts WHERE ts.process_id = p.id AND ts.status <> "cancelled") AS sessions_count
            ';

            if ($this->tableExists('test_process_evaluation_forms') && $this->tableExists('test_process_evaluation_assignments')) {
                $evaluationSchema = database_identifier('evaluaciones_encuestas');
                $onlineSurveySql = '
                                OR EXISTS (
                                    SELECT 1
                                    FROM ' . $evaluationSchema . '.evaluation_survey_attempts ea
                                    WHERE ea.process_id = p.id
                                      AND ea.user_id = pu.user_id
                                      AND ea.status = "in_progress"
                                      AND ea.last_seen_at >= DATE_SUB(NOW(), INTERVAL 90 SECOND)
                                )
';
                $evaluationsCountSql .= ' + (SELECT COUNT(*) FROM test_process_evaluation_forms pef WHERE pef.process_id = p.id)';
                $evaluationCountsSql = '
                    (SELECT COUNT(*)
                     FROM test_process_evaluation_forms pef
                     WHERE pef.process_id = p.id) AS evaluation_forms_count,
                    COALESCE(evaluation_progress.evaluation_assignments_count, 0) AS evaluation_assignments_count,
                    COALESCE(evaluation_progress.completed_evaluation_assignments, 0) AS completed_evaluation_assignments,
                    COALESCE(evaluation_progress.answered_evaluation_assignments, 0) AS answered_evaluation_assignments,
                    COALESCE(evaluation_progress.in_progress_evaluation_assignments, 0) AS in_progress_evaluation_assignments,
                    COALESCE(evaluation_progress.expired_evaluation_assignments, 0) AS expired_evaluation_assignments
                ';
                $evaluationProgressJoinSql = '
                    LEFT JOIN (
                        SELECT pea.process_id,
                               COUNT(*) AS evaluation_assignments_count,
                               SUM(CASE WHEN latest.status = "completed" THEN 1 ELSE 0 END) AS completed_evaluation_assignments,
                               SUM(CASE WHEN latest.id IS NOT NULL AND EXISTS (
                                   SELECT 1 FROM ' . $evaluationSchema . '.evaluation_survey_answers answer_row
                                   WHERE answer_row.attempt_id = latest.id
                                     AND answer_row.answer_value IS NOT NULL
                                     AND TRIM(answer_row.answer_value) <> ""
                               ) THEN 1 ELSE 0 END) AS answered_evaluation_assignments,
                               SUM(CASE WHEN latest.status = "in_progress" THEN 1 ELSE 0 END) AS in_progress_evaluation_assignments,
                               SUM(CASE WHEN latest.status = "expired" THEN 1 ELSE 0 END) AS expired_evaluation_assignments
                        FROM test_process_evaluation_assignments pea
                        LEFT JOIN ' . $evaluationSchema . '.evaluation_survey_attempts latest
                          ON latest.id = (
                              SELECT ea2.id
                              FROM ' . $evaluationSchema . '.evaluation_survey_attempts ea2
                              WHERE ea2.process_id = pea.process_id
                                AND ea2.form_id = pea.form_id
                                AND ea2.user_id = pea.user_id
                                AND ea2.status <> "preparing"
                              ORDER BY ea2.attempt_number DESC, ea2.id DESC
                              LIMIT 1
                          )
                        WHERE pea.status <> "cancelled"
                        GROUP BY pea.process_id
                    ) evaluation_progress ON evaluation_progress.process_id = p.id
                ';
            }

            if ($this->hasSessionPresenceColumn()) {
                $onlineUsersSql = '
                    (
                        SELECT COUNT(DISTINCT pu.user_id)
                        FROM test_process_users pu
                        WHERE pu.process_id = p.id
                          AND pu.status <> "cancelled"
                          AND (
                                EXISTS (
                                    SELECT 1
                                    FROM test_sessions ts
                                    JOIN test_process_instruments pi ON pi.instrument_id = ts.instrument_id
                                    WHERE ts.user_id = pu.user_id
                                      AND ts.status = "in_progress"
                                      AND pi.process_id = p.id
                                      AND (ts.process_id = p.id OR ts.process_id IS NULL)
                                      AND ts.last_seen_at >= DATE_SUB(NOW(), INTERVAL 90 SECOND)
                                )
                                ' . $onlineSurveySql . '
                          )
                    ) AS online_users_count
                ';
            }
        }

        return '
            SELECT p.*,
                (SELECT COUNT(*) FROM test_process_users pu WHERE pu.process_id = p.id AND pu.status <> "cancelled") AS users_count,
                (SELECT COUNT(*) FROM test_process_instruments pi WHERE pi.process_id = p.id) AS instruments_count,
                ' . $evaluationsCountSql . ' AS evaluations_count,
                ' . $sessionCountsSql . ',
                ' . $evaluationCountsSql . ',
                ' . $onlineUsersSql . '
            FROM test_processes p
            ' . $evaluationProgressJoinSql . '
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

    private function hasPausedRemainingColumn(): bool
    {
        if ($this->hasPausedRemainingColumn !== null) {
            return $this->hasPausedRemainingColumn;
        }

        $this->hasPausedRemainingColumn = $this->columnExists('test_sessions', 'paused_remaining_seconds');
        return $this->hasPausedRemainingColumn;
    }

    private function hasSessionScoreSummaryColumn(): bool
    {
        if ($this->hasSessionScoreSummaryColumn !== null) {
            return $this->hasSessionScoreSummaryColumn;
        }

        $this->hasSessionScoreSummaryColumn = $this->columnExists('test_sessions', 'score_summary');
        return $this->hasSessionScoreSummaryColumn;
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
        if ($user && (string) ($user['role'] ?? '') === 'company_admin') {
            return (int) ($user['company_id'] ?? 0) > 0 ? 'AND ' . $alias . '.company_id = ?' : 'AND 1 = 0';
        }
        if ($user && (has_permission('manage_company_processes') || $this->isCompanySupervisor($user)) && !has_permission('manage_test_processes') && (int) ($user['company_id'] ?? 0) > 0) {
            return 'AND ' . $alias . '.company_id = ?';
        }

        return '';
    }

    private function companyUserScopeParams(): array
    {
        $user = current_user();
        if ($user && (string) ($user['role'] ?? '') === 'company_admin') {
            return (int) ($user['company_id'] ?? 0) > 0 ? [(int) $user['company_id']] : [];
        }
        if ($user && (has_permission('manage_company_processes') || $this->isCompanySupervisor($user)) && !has_permission('manage_test_processes') && (int) ($user['company_id'] ?? 0) > 0) {
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

    public function supportsFacialEnrollmentRequirement(): bool
    {
        if ($this->hasProcessFacialEnrollmentColumn !== null) {
            return $this->hasProcessFacialEnrollmentColumn;
        }

        $this->hasProcessFacialEnrollmentColumn = $this->columnExists('test_processes', 'require_facial_enrollment');
        return $this->hasProcessFacialEnrollmentColumn;
    }

    public function supportsProcessActivityPolicies(): bool
    {
        if ($this->hasProcessActivityPolicyColumns !== null) {
            return $this->hasProcessActivityPolicyColumns;
        }
        try {
            $row = $this->db->fetch('SELECT COUNT(*) AS total FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = "test_processes" AND COLUMN_NAME IN ("facial_enrollment_policy", "component_validation_policy", "audio_visual_recording_policy", "action_logging_policy")');
            $this->hasProcessActivityPolicyColumns = (int) ($row['total'] ?? 0) === 4;
        } catch (Throwable $exception) {
            error_log('Process activity policy schema check error: ' . $exception->getMessage());
            $this->hasProcessActivityPolicyColumns = false;
        }
        return $this->hasProcessActivityPolicyColumns;
    }

    private function hasProcessFacialEnrollmentColumn(): bool
    {
        return $this->supportsFacialEnrollmentRequirement();
    }

    private function processPolicySelect(): string
    {
        $columns = ['facial_enrollment_policy', 'component_validation_policy', 'audio_visual_recording_policy', 'action_logging_policy'];
        // information_schema recibe el nombre sin delimitadores; los JOIN SQL
        // siguen usando database_identifier() donde corresponde.
        $testsSchema = database_name('tests');
        $selects = [];
        foreach ($columns as $column) {
            $selects[] = $this->policySchemaColumnExists($testsSchema, 'test_processes', $column)
                ? 'p.' . $column . ' AS ' . $column
                : '"inherit" AS ' . $column;
        }
        return implode(', ', $selects);
    }

    private function evaluationSnapshotSelect(string $alias, bool $qualified = true): string
    {
        $columns = [
            'process_facial_enrollment_required', 'process_component_validation_required',
            'process_record_audio_visual', 'process_record_actions', 'process_policy_snapshot_at',
        ];
        $evaluationSchema = database_name('evaluaciones_encuestas');
        $selects = [];
        foreach ($columns as $column) {
            $exists = $this->policySchemaColumnExists($evaluationSchema, 'evaluation_survey_attempts', $column);
            if (!$exists) {
                $selects[] = 'NULL AS ' . $column;
            } else {
                $selects[] = $alias . '.' . $column . ' AS ' . $column;
            }
        }
        return implode(', ', $selects);
    }

    private function policySchemaColumnExists(string $schema, string $table, string $column): bool
    {
        $key = $schema . '.' . $table . '.' . $column;
        if (array_key_exists($key, $this->policySchemaColumnCache)) return $this->policySchemaColumnCache[$key];
        $tableKey = $schema . '.' . $table;
        if (empty($this->policySchemaTablesLoaded[$tableKey])) {
            $this->policySchemaTablesLoaded[$tableKey] = true;
            try {
                $rows = $this->db->fetchAll('SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?', [$schema, $table]);
                foreach ($rows as $row) {
                    $this->policySchemaColumnCache[$tableKey . '.' . (string) $row['COLUMN_NAME']] = true;
                }
            } catch (Throwable $exception) {
                error_log('Process activity policy schema check error: ' . $exception->getMessage());
            }
        }
        if (array_key_exists($key, $this->policySchemaColumnCache)) return true;
        return $this->policySchemaColumnCache[$key] = false;
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
        return !is_company_admin_user() && (has_permission('manage_tests') || has_permission('manage_test_processes'));
    }

    private function isCompanySupervisor(array $user): bool
    {
        return (string) ($user['profile_key'] ?? '') === 'supervisor_sede';
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
                WHERE pu.process_id = ? AND pu.user_id = u.id AND pu.status <> 'cancelled'
            )",
        ];
        $params = [$processId];

        $user = current_user();
        if ($user && (has_permission('manage_company_processes') || $this->isCompanySupervisor($user)) && !has_permission('manage_test_processes') && (int) ($user['company_id'] ?? 0) > 0) {
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
