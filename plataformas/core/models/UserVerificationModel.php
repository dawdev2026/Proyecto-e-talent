<?php
declare(strict_types=1);

final class UserVerificationModel
{
    private Database $core;
    private string $coreSchema;

    public function __construct(?Database $core = null)
    {
        $this->core = $core ?: database('core');
        $this->coreSchema = database_identifier('core');
    }

    public function findForCompany(string $email, int $companyId): ?array
    {
        $user = $this->core->fetch(
            'SELECT id, name, email, is_active FROM users WHERE company_id = ? AND email = ? AND role = \'usuario\' LIMIT 1',
            [$companyId, mb_strtolower(trim($email))]
        );
        if (!$user) {
            return null;
        }

        if ((int) ($user['is_active'] ?? 0) !== 1) {
            return ['user' => $user, 'active' => false, 'processes' => []];
        }

        return ['user' => $user, 'active' => true, 'processes' => $this->processesForUser((int) $user['id'], $companyId)];
    }

    public function recordSuccessfulVerification(int $userId, int $companyId): void
    {
        $this->core->insert(
            'INSERT INTO user_verification_checks (company_id, user_id) VALUES (?, ?)',
            [$companyId, $userId]
        );
    }

    public function countChecksForCompany(int $companyId): int
    {
        $row = $this->core->fetch(
            'SELECT COUNT(*) AS total FROM user_verification_checks c
             JOIN users u ON u.id = c.user_id AND u.company_id = c.company_id
             WHERE c.company_id = ? AND u.role = \'usuario\'',
            [$companyId]
        );

        return max(0, (int) ($row['total'] ?? 0));
    }

    public function recentChecksForCompany(int $companyId, int $limit = 50, int $offset = 0): array
    {
        $limit = max(1, min(100, $limit));
        $offset = max(0, $offset);

        return $this->core->fetchAll(
            'SELECT c.id, u.name, u.rut, u.email, c.created_at AS verified_at
             FROM user_verification_checks c
             JOIN users u ON u.id = c.user_id AND u.company_id = c.company_id
             WHERE c.company_id = ? AND u.role = \'usuario\'
             ORDER BY c.created_at DESC, c.id DESC
             LIMIT ' . $limit . ' OFFSET ' . $offset,
            [$companyId]
        );
    }

    private function processesForUser(int $userId, int $companyId): array
    {
        $processes = [];
        $tests = database('tests')->fetchAll(
            'SELECT DISTINCT p.id AS sort_id, p.name, p.starts_at, p.created_at AS sort_date, \'Evaluación\' AS type
             FROM test_process_users pu
             JOIN test_processes p ON p.id = pu.process_id
             WHERE pu.user_id = ? AND pu.status <> \'cancelled\' AND p.company_id = ? AND p.status <> \'cancelled\'
             ORDER BY p.created_at DESC, p.id DESC',
            [$userId, $companyId]
        );
        $interviews = database('interviews')->fetchAll(
            'SELECT DISTINCT p.id AS sort_id, p.name, p.starts_at, p.interview_date AS sort_date, \'Entrevista\' AS type
             FROM interview_appointments a
             JOIN interview_processes p ON p.id = a.process_id
             WHERE a.candidate_user_id = ? AND a.meeting_status <> \'cancelled\'
               AND p.company_id = ? AND p.status <> \'cancelled\'
             ORDER BY p.interview_date DESC, p.id DESC',
            [$userId, $companyId]
        );

        foreach (array_merge($tests, $interviews) as $process) {
            $processes[] = [
                'name' => (string) ($process['name'] ?? 'Proceso'),
                'type' => (string) ($process['type'] ?? 'Proceso'),
                'starts_at' => (string) ($process['starts_at'] ?? ''),
            ];
        }

        return $processes;
    }

}
