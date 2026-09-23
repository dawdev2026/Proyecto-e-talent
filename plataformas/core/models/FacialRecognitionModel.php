<?php
declare(strict_types=1);

final class FacialRecognitionModel
{
    private Database $db;

    public function __construct(?Database $db = null)
    {
        $this->db = $db ?: database('core');
    }

    public function enrollmentForUser(int $userId, ?int $companyId): ?array
    {
        return $this->db->fetch('SELECT * FROM facial_recognition_enrollments WHERE user_id = ? AND ' . $this->companyClause($companyId) . ' ORDER BY id DESC LIMIT 1', array_merge([$userId], $this->companyParams($companyId)));
    }

    public function enrollmentStatusForUser(int $userId, ?int $companyId): ?string
    {
        $row = $this->db->fetch(
            'SELECT status FROM facial_recognition_enrollments WHERE user_id = ? AND ' . $this->companyClause($companyId) . ' ORDER BY id DESC LIMIT 1',
            array_merge([$userId], $this->companyParams($companyId))
        );

        return isset($row['status']) ? (string) $row['status'] : null;
    }

    public function enrolledUsersForCompany(int $companyId): array
    {
        return $this->db->fetchAll('
            SELECT u.id, u.name, u.rut, u.email, e.status, e.enrolled_at, e.revoked_at
            FROM facial_recognition_enrollments e
            JOIN users u ON u.id = e.user_id AND u.company_id = e.company_id
            WHERE e.company_id = ?
            ORDER BY e.status = "active" DESC, u.name ASC
            LIMIT 1000
        ', [$companyId]);
    }

    public function enroll(int $userId, ?int $companyId, array $embedding, string $modelVersion, string $consentVersion, int $createdBy): int
    {
        $existing = $this->enrollmentForUser($userId, $companyId);
        $embeddingJson = json_encode($embedding, JSON_UNESCAPED_SLASHES);
        if ($embeddingJson === false) throw new RuntimeException('No se pudo guardar la huella facial.');
        if ($existing) {
            $this->db->execute('UPDATE facial_recognition_enrollments SET provider = \'human\', face_embedding = ?, model_version = ?, status = \'active\', consent_version = ?, consented_at = NOW(), enrolled_at = NOW(), revoked_at = NULL, created_by = ? WHERE id = ?', [$embeddingJson, $modelVersion, $consentVersion, $createdBy, (int) $existing['id']]);
            return (int) $existing['id'];
        }

        return $this->db->insert('INSERT INTO facial_recognition_enrollments (company_id, user_id, provider, face_embedding, model_version, consent_version, consented_at, enrolled_at, created_by) VALUES (?, ?, \'human\', ?, ?, ?, NOW(), NOW(), ?)', [$companyId, $userId, $embeddingJson, $modelVersion, $consentVersion, $createdBy]);
    }

    public function revoke(int $userId, ?int $companyId): void
    {
        $this->db->execute('UPDATE facial_recognition_enrollments SET status = \'revoked\', revoked_at = NOW() WHERE user_id = ? AND ' . $this->companyClause($companyId), array_merge([$userId], $this->companyParams($companyId)));
    }

    public function attempt(array $data): int
    {
        return $this->db->insert('INSERT INTO facial_recognition_attempts (enrollment_id, company_id, user_id, context, result, similarity, liveness, provider, reason, request_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)', [
            $data['enrollment_id'] ?? null,
            $data['company_id'] ?? null,
            $data['user_id'] ?? null,
            $data['context'] ?? 'identity_validation',
            $data['result'],
            $data['similarity'] ?? null,
            $data['liveness'] ?? null,
            $data['provider'] ?? 'human',
            $data['reason'] ?? null,
            $data['request_id'] ?? null,
        ]);
    }

    private function companyClause(?int $companyId): string
    {
        return $companyId && $companyId > 0 ? 'company_id = ?' : 'company_id IS NULL';
    }

    private function companyParams(?int $companyId): array
    {
        return $companyId && $companyId > 0 ? [$companyId] : [];
    }
}
