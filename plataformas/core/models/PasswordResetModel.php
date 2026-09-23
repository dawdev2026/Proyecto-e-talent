<?php
declare(strict_types=1);

final class PasswordResetModel
{
    private Database $db;

    public function __construct(?Database $db = null)
    {
        $this->db = $db ?: database();
    }

    public function issue(string $email, int $ttlMinutes = 60, ?int $companyId = null): ?array
    {
        $companySql = $companyId !== null && $companyId > 0 ? ' AND company_id = ?' : '';
        $params = [$email];
        if ($companySql !== '') {
            $params[] = $companyId;
        }
        $user = $this->db->fetch('SELECT id, email, name FROM users WHERE LOWER(email) = LOWER(?) AND is_active = 1' . $companySql . ' LIMIT 1', $params);
        if (!$user) {
            return null;
        }

        $token = bin2hex(random_bytes(32));
        $expiresAt = date('Y-m-d H:i:s', time() + (max(15, min(1440, $ttlMinutes)) * 60));
        $this->db->transaction(function (Database $db) use ($user, $token, $expiresAt): void {
            $db->execute('UPDATE password_reset_tokens SET used_at = NOW() WHERE user_id = ? AND used_at IS NULL', [(int) $user['id']]);
            $db->insert('
                INSERT INTO password_reset_tokens (user_id, token_hash, expires_at, requested_ip)
                VALUES (?, ?, ?, ?)
            ', [
                (int) $user['id'],
                hash('sha256', $token),
                $expiresAt,
                substr((string) ($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45) ?: null,
            ]);
        });

        return ['token' => $token, 'email' => (string) $user['email'], 'name' => (string) $user['name']];
    }

    public function findValid(string $token, ?int $companyId = null): ?array
    {
        $companySql = $companyId !== null && $companyId > 0 ? ' AND u.company_id = ?' : '';
        $params = [hash('sha256', $token)];
        if ($companySql !== '') {
            $params[] = $companyId;
        }
        return $this->db->fetch('
            SELECT t.id AS token_id, u.id AS user_id, u.email, u.name
            FROM password_reset_tokens t
            JOIN users u ON u.id = t.user_id
            WHERE t.token_hash = ? AND t.used_at IS NULL AND t.expires_at > NOW() AND u.is_active = 1' . $companySql . '
            LIMIT 1
        ', $params);
    }

    public function consume(int $tokenId, int $userId, string $password): void
    {
        $this->db->transaction(function () use ($tokenId, $userId, $password): void {
            $this->db->execute('UPDATE users SET password_hash = ? WHERE id = ? AND is_active = 1', [password_hash($password, PASSWORD_BCRYPT), $userId]);
            $this->db->execute('UPDATE password_reset_tokens SET used_at = NOW() WHERE id = ? AND user_id = ? AND used_at IS NULL', [$tokenId, $userId]);
        });
    }
}
