<?php
declare(strict_types=1);

final class ReportAuditModel
{
    private Database $db;

    public function __construct(?Database $db = null)
    {
        $this->db = $db ?: database('core');
    }

    public function start(array $data): int
    {
        return $this->db->insert(
            'INSERT INTO report_execution_logs
                (report_id, report_version_id, company_id, process_id, user_id, executed_by, format, status, request_hash)
             VALUES (?, ?, ?, ?, ?, ?, ?, \'started\', ?)',
            [
                (int) $data['report_id'],
                !empty($data['report_version_id']) ? (int) $data['report_version_id'] : null,
                (int) $data['company_id'],
                (int) $data['process_id'],
                (int) $data['user_id'],
                !empty($data['executed_by']) ? (int) $data['executed_by'] : null,
                (string) $data['format'],
                $data['request_hash'] ?? null,
            ]
        );
    }

    public function complete(int $id, float $startedAt, bool $success, ?string $errorCode = null, ?string $errorMessage = null): void
    {
        $duration = max(0, (int) round((microtime(true) - $startedAt) * 1000));
        $this->db->execute(
            'UPDATE report_execution_logs
             SET status = ?, error_code = ?, error_message = ?, completed_at = CURRENT_TIMESTAMP, duration_ms = ?
             WHERE id = ?',
            [$success ? 'completed' : 'failed', $errorCode, $errorMessage ? mb_substr($errorMessage, 0, 500) : null, $duration, $id]
        );
    }

    public function history(array $filters = [], int $limit = 100): array
    {
        $where = [];
        $params = [];
        if (!empty($filters['report_id'])) {
            $where[] = 'l.report_id = ?';
            $params[] = (int) $filters['report_id'];
        }
        if (!empty($filters['company_id'])) {
            $where[] = 'l.company_id = ?';
            $params[] = (int) $filters['company_id'];
        }
        $limit = max(1, min($limit, 500));
        $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';
        return $this->db->fetchAll(
            "SELECT l.*, r.name AS report_name, c.name AS company_name,
                    u.name AS user_name
             FROM report_execution_logs l
             INNER JOIN report_definitions r ON r.id = l.report_id
             INNER JOIN companies c ON c.id = l.company_id
             INNER JOIN users u ON u.id = l.user_id
             {$whereSql}
             ORDER BY l.started_at DESC, l.id DESC
             LIMIT {$limit}",
            $params
        );
    }
}
