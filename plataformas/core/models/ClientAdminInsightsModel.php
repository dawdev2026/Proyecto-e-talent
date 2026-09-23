<?php
declare(strict_types=1);

final class ClientAdminInsightsModel
{
    private Database $core;
    private Database $tests;
    private string $testsSchema;
    private string $evaluationSchema;

    public function __construct(?Database $db = null)
    {
        $this->core = $db ?: database('core');
        $this->tests = database('tests');
        $this->testsSchema = database_identifier('tests');
        $this->evaluationSchema = database_identifier('evaluaciones_encuestas');
    }

    public function evaluationProgress(int $companyId): array
    {
        $rows = $this->tests->fetchAll('
            SELECT p.id AS process_id, p.name AS process_name, p.code AS process_code,
                   COUNT(*) AS assigned,
                   SUM(CASE WHEN COALESCE(a.status, "assigned") = "in_progress" THEN 1 ELSE 0 END) AS in_progress,
                   SUM(CASE WHEN COALESCE(answer_stats.answers_count, 0) > 0 THEN 1 ELSE 0 END) AS answered,
                   SUM(CASE WHEN COALESCE(a.status, "assigned") = "completed" THEN 1 ELSE 0 END) AS finished,
                   SUM(CASE WHEN COALESCE(a.status, "assigned") = "expired" THEN 1 ELSE 0 END) AS expired,
                   SUM(CASE WHEN COALESCE(a.status, "assigned") = "assigned" THEN 1 ELSE 0 END) AS pending
            FROM ' . $this->testsSchema . '.test_process_evaluation_assignments pea
            JOIN ' . $this->testsSchema . '.test_processes p ON p.id = pea.process_id AND p.company_id = ?
            JOIN ' . $this->coreSchema() . '.users u ON u.id = pea.user_id AND u.company_id = p.company_id
            JOIN ' . $this->evaluationSchema . '.evaluation_survey_forms f ON f.id = pea.form_id AND f.form_type = "assessment"
            LEFT JOIN ' . $this->evaluationSchema . '.evaluation_survey_attempts a
              ON a.id = (SELECT a2.id FROM ' . $this->evaluationSchema . '.evaluation_survey_attempts a2
                         WHERE a2.process_id = pea.process_id AND a2.form_id = pea.form_id AND a2.user_id = pea.user_id
                         ORDER BY a2.attempt_number DESC, a2.id DESC LIMIT 1)
            LEFT JOIN (
                SELECT ea.attempt_id, SUM(CASE WHEN ea.answer_value IS NOT NULL AND TRIM(ea.answer_value) <> "" THEN 1 ELSE 0 END) AS answers_count
                FROM ' . $this->evaluationSchema . '.evaluation_survey_answers ea
                GROUP BY ea.attempt_id
            ) answer_stats ON answer_stats.attempt_id = a.id
            WHERE pea.status <> "cancelled"
            GROUP BY p.id, p.name, p.code
            ORDER BY p.name ASC
            LIMIT 500
        ', [$companyId]);
        return $rows;
    }

    public function testProgress(int $companyId): array
    {
        return $this->tests->fetchAll('
            SELECT p.id AS process_id, p.name AS process_name, p.code AS process_code,
                   COUNT(s.id) AS assigned,
                   SUM(s.status = "in_progress") AS in_progress,
                   SUM(COALESCE(answer_stats.answers_count, 0) > 0) AS answered,
                   SUM(s.status = "completed") AS finished,
                   SUM(s.status = "expired") AS expired,
                   SUM(s.status = "assigned") AS pending
            FROM ' . $this->testsSchema . '.test_processes p
            JOIN ' . $this->testsSchema . '.test_sessions s ON s.process_id = p.id AND s.status <> "cancelled"
            LEFT JOIN (
                SELECT session_id, SUM(CASE WHEN answer_value IS NOT NULL AND TRIM(answer_value) <> "" THEN 1 ELSE 0 END) AS answers_count
                FROM ' . $this->testsSchema . '.test_answers
                GROUP BY session_id
            ) answer_stats ON answer_stats.session_id = s.id
            JOIN ' . $this->coreSchema() . '.users u ON u.id = s.user_id AND u.company_id = p.company_id
            WHERE p.company_id = ?
            GROUP BY p.id, p.name, p.code
            ORDER BY p.name ASC
            LIMIT 500
        ', [$companyId]);
    }

    public function homeSummary(int $companyId): array
    {
        if ($companyId <= 0) {
            return [
                'processes_generated' => 0, 'processes_finished' => 0,
                'processes_in_progress' => 0, 'processes_not_started' => 0,
                'users_registered' => 0, 'users_enrolled' => 0, 'users_pending_enrollment' => 0,
            ];
        }

        $processes = $this->tests->fetch('
            SELECT COUNT(CASE WHEN p.status <> "cancelled" THEN 1 END) AS processes_generated,
                   SUM(CASE WHEN p.status = "closed"
                         OR (p.status = "active" AND (p.starts_at IS NULL OR p.starts_at <= NOW())
                             AND p.ends_at IS NOT NULL AND p.ends_at < NOW())
                       THEN 1 ELSE 0 END) AS processes_finished,
                   SUM(CASE WHEN p.status = "active"
                         AND (p.starts_at IS NULL OR p.starts_at <= NOW())
                         AND (p.ends_at IS NULL OR p.ends_at >= NOW())
                       THEN 1 ELSE 0 END) AS processes_in_progress,
                   SUM(CASE WHEN p.status = "draft"
                         OR (p.status = "active" AND p.starts_at > NOW())
                       THEN 1 ELSE 0 END) AS processes_not_started
            FROM ' . $this->testsSchema . '.test_processes p
            WHERE p.company_id = ?
        ', [$companyId]) ?: [];

        $users = $this->core->fetch('
            SELECT COUNT(u.id) AS users_registered
            FROM users u
            WHERE u.company_id = ? AND u.role = "usuario" AND u.is_active = 1
        ', [$companyId]) ?: [];

        // Enrolados y pendientes usan la misma población de participantes
        // asignados a procesos de tests o evaluaciones, deduplicada por usuario.
        $processUsers = $this->tests->fetch('
            SELECT COUNT(DISTINCT assigned.user_id) AS process_users_total,
                   COUNT(DISTINCT CASE WHEN e.status = "active" THEN assigned.user_id END) AS users_enrolled
            FROM (
                SELECT pu.user_id
                FROM ' . $this->testsSchema . '.test_process_users pu
                JOIN ' . $this->testsSchema . '.test_processes p ON p.id = pu.process_id AND p.company_id = ?
                JOIN ' . $this->coreSchema() . '.users u ON u.id = pu.user_id AND u.company_id = p.company_id
                WHERE pu.status <> "cancelled" AND p.status <> "cancelled"
                  AND u.role = "usuario" AND u.is_active = 1
                UNION
                SELECT pea.user_id
                FROM ' . $this->testsSchema . '.test_process_evaluation_assignments pea
                JOIN ' . $this->testsSchema . '.test_processes p ON p.id = pea.process_id AND p.company_id = ?
                JOIN ' . $this->coreSchema() . '.users u ON u.id = pea.user_id AND u.company_id = p.company_id
                WHERE pea.status <> "cancelled" AND p.status <> "cancelled"
                  AND u.role = "usuario" AND u.is_active = 1
            ) assigned
            LEFT JOIN ' . $this->coreSchema() . '.facial_recognition_enrollments e
              ON e.company_id = ? AND e.user_id = assigned.user_id
        ', [$companyId, $companyId, $companyId]) ?: [];

        return [
            'processes_generated' => (int) ($processes['processes_generated'] ?? 0),
            'processes_finished' => (int) ($processes['processes_finished'] ?? 0),
            'processes_in_progress' => (int) ($processes['processes_in_progress'] ?? 0),
            'processes_not_started' => (int) ($processes['processes_not_started'] ?? 0),
            'users_registered' => (int) ($users['users_registered'] ?? 0),
            'users_enrolled' => (int) ($processUsers['users_enrolled'] ?? 0),
            'users_pending_enrollment' => max(0, (int) ($processUsers['process_users_total'] ?? 0) - (int) ($processUsers['users_enrolled'] ?? 0)),
        ];
    }

    public function userByRut(string $rut, int $companyId): ?array
    {
        return $this->core->fetch('SELECT id, name, rut, email, is_active FROM users WHERE rut = ? AND company_id = ? LIMIT 1', [$rut, $companyId]);
    }

    public function userAssignments(int $userId, int $companyId): array
    {
        $tests = $this->tests->fetchAll('
            SELECT p.id AS process_id, p.name AS process_name, p.code AS process_code,
                   i.name AS activity_name, "test" AS activity_type, s.status,
                   s.id AS record_id, s.started_at, s.completed_at
            FROM ' . $this->testsSchema . '.test_sessions s
            JOIN ' . $this->testsSchema . '.test_processes p ON p.id = s.process_id AND p.company_id = ?
            JOIN ' . $this->coreSchema() . '.users u ON u.id = s.user_id AND u.company_id = p.company_id
            JOIN ' . $this->testsSchema . '.test_instruments i ON i.id = s.instrument_id
            WHERE s.user_id = ? AND s.status <> "cancelled"
            ORDER BY p.name, i.name, s.id DESC LIMIT 500
        ', [$companyId, $userId]);
        $evaluations = $this->tests->fetchAll('
            SELECT p.id AS process_id, p.name AS process_name, p.code AS process_code,
                   f.title AS activity_name, "evaluation" AS activity_type,
                   COALESCE(a.status, "assigned") AS status, a.id AS record_id,
                   a.created_at AS started_at, a.completed_at
            FROM ' . $this->testsSchema . '.test_process_evaluation_assignments pea
            JOIN ' . $this->testsSchema . '.test_processes p ON p.id = pea.process_id AND p.company_id = ?
            JOIN ' . $this->coreSchema() . '.users u ON u.id = pea.user_id AND u.company_id = p.company_id
            JOIN ' . $this->evaluationSchema . '.evaluation_survey_forms f ON f.id = pea.form_id AND f.form_type = "assessment"
            LEFT JOIN ' . $this->evaluationSchema . '.evaluation_survey_attempts a
              ON a.id = (SELECT a2.id FROM ' . $this->evaluationSchema . '.evaluation_survey_attempts a2
                         WHERE a2.process_id = pea.process_id AND a2.form_id = pea.form_id AND a2.user_id = pea.user_id
                         ORDER BY a2.attempt_number DESC, a2.id DESC LIMIT 1)
            WHERE pea.user_id = ? AND pea.status <> "cancelled"
            ORDER BY p.name, f.title LIMIT 500
        ', [$companyId, $userId]);
        return array_merge($tests, $evaluations);
    }

    public function answersForActivity(int $userId, int $companyId, int $recordId, string $type): array
    {
        if ($userId <= 0 || $companyId <= 0 || $recordId <= 0) return [];
        if ($type === 'test') {
            $rows = $this->tests->fetchAll('
                SELECT i.item_key, i.prompt, i.item_type, i.options, a.answer_value
                FROM ' . $this->testsSchema . '.test_answers a
                JOIN ' . $this->testsSchema . '.test_sessions s ON s.id = a.session_id AND s.user_id = ?
                JOIN ' . $this->testsSchema . '.test_processes p ON p.id = s.process_id AND p.company_id = ?
                JOIN ' . $this->coreSchema() . '.users u ON u.id = s.user_id AND u.company_id = p.company_id
                JOIN ' . $this->testsSchema . '.test_items i ON i.id = a.item_id
                WHERE s.id = ? AND s.status <> "cancelled" ORDER BY i.sort_order, i.id LIMIT 500
            ', [$userId, $companyId, $recordId]);
            foreach ($rows as &$row) {
                $pairs = [];
                foreach (explode(';', (string) ($row['options'] ?? '')) as $option) {
                    [$value, $label] = array_pad(array_map('trim', explode('=', $option, 2)), 2, '');
                    if ($value !== '') $pairs[$value] = $label !== '' ? $label : $value;
                }
                $answer = trim((string) ($row['answer_value'] ?? ''));
                $values = (string) ($row['item_type'] ?? '') === 'multiple_choice'
                    ? array_values(array_filter(array_map('trim', explode(',', $answer)), static fn(string $value): bool => $value !== ''))
                    : [$answer];
                $row['answer_value'] = implode(', ', array_map(static fn(string $value): string => $pairs[$value] ?? $value, $values));
                unset($row['options'], $row['item_type']);
            }
            unset($row);
            return $rows;
        }

        if ($type !== 'evaluation') return [];
        return $this->tests->fetchAll('
            SELECT q.question_text AS prompt, COALESCE(o.option_label, a.answer_value) AS answer_value
            FROM ' . $this->evaluationSchema . '.evaluation_survey_answers a
            JOIN ' . $this->evaluationSchema . '.evaluation_survey_attempts att ON att.id = a.attempt_id AND att.user_id = ?
            JOIN ' . $this->testsSchema . '.test_process_evaluation_assignments pea
              ON pea.process_id = att.process_id AND pea.form_id = att.form_id AND pea.user_id = att.user_id AND pea.status <> "cancelled"
            JOIN ' . $this->testsSchema . '.test_processes p ON p.id = pea.process_id AND p.company_id = ?
            JOIN ' . $this->coreSchema() . '.users u ON u.id = att.user_id AND u.company_id = p.company_id
            JOIN ' . $this->evaluationSchema . '.evaluation_survey_questions q ON q.id = a.question_id
            LEFT JOIN ' . $this->evaluationSchema . '.evaluation_survey_question_options o
              ON o.question_id = q.id AND o.option_value = a.answer_value
            WHERE att.id = ? ORDER BY q.sort_order, q.id LIMIT 500
        ', [$userId, $companyId, $recordId]);
    }

    private function coreSchema(): string
    {
        return database_identifier('core');
    }
}
