<?php
declare(strict_types=1);

final class LoginVerificationModel
{
    private Database $db;

    public function __construct(?Database $db = null)
    {
        $this->db = $db ?: database();
    }

    public function issue(int $userId, int $validMinutes): array
    {
        $code = (string) random_int(100000, 999999);
        $expiresAt = (new DateTimeImmutable('now'))
            ->modify('+' . max(1, min(15, $validMinutes)) . ' minutes')
            ->format('Y-m-d H:i:s');

        $this->db->transaction(function (Database $db) use ($userId, $code, $expiresAt): void {
            $db->execute(
                'UPDATE login_verification_codes SET used_at = NOW() WHERE user_id = ? AND used_at IS NULL',
                [$userId]
            );
            $db->execute(
                'INSERT INTO login_verification_codes (user_id, code_hash, expires_at, attempts, created_ip, user_agent)
                 VALUES (?, ?, ?, 0, ?, ?)',
                [
                    $userId,
                    hash('sha256', $code),
                    $expiresAt,
                    substr((string) ($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45) ?: null,
                    substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255) ?: null,
                ]
            );
        });

        return ['code' => $code, 'expires_at' => $expiresAt];
    }

    public function verifyLatest(int $userId, string $code, int $maxAttempts): bool
    {
        return (bool) $this->db->transaction(function (Database $db) use ($userId, $code, $maxAttempts): bool {
            $challenge = $db->fetch(
                'SELECT id, code_hash, expires_at, attempts
                 FROM login_verification_codes
                 WHERE user_id = ? AND used_at IS NULL
                 ORDER BY id DESC LIMIT 1 FOR UPDATE',
                [$userId]
            );

            if (!$challenge || (int) $challenge['attempts'] >= max(1, min(10, $maxAttempts))) {
                return false;
            }

            $matches = preg_match('/^\d{6}$/', $code) === 1
                && hash_equals((string) $challenge['code_hash'], hash('sha256', $code));

            if (!$matches || strtotime((string) $challenge['expires_at']) < time()) {
                $db->execute(
                    'UPDATE login_verification_codes SET attempts = attempts + 1 WHERE id = ?',
                    [(int) $challenge['id']]
                );
                return false;
            }

            $db->execute(
                'UPDATE login_verification_codes SET used_at = NOW() WHERE id = ? AND used_at IS NULL',
                [(int) $challenge['id']]
            );
            return true;
        });
    }
}
