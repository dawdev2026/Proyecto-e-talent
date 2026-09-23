<?php
declare(strict_types=1);

final class CompanyMailSettingsModel
{
    private Database $db;

    public function __construct(?Database $db = null)
    {
        $this->db = $db ?: database();
    }

    public function defaults(): array
    {
        return [
            'enabled' => 0,
            'host' => '',
            'port' => 587,
            'encryption' => 'starttls',
            'smtp_auth' => 1,
            'auth_type' => '',
            'username' => '',
            'password' => '',
            'password_configured' => 0,
            'from_email' => '',
            'from_name' => 'e-talent',
            'reply_to_email' => '',
            'reply_to_name' => '',
            'timeout' => 30,
        ];
    }

    public function find(int $companyId): array
    {
        $settings = $this->defaults();
        if ($companyId <= 0) {
            return $settings;
        }

        try {
            $row = $this->db->fetch('SELECT * FROM company_mail_settings WHERE company_id = ? LIMIT 1', [$companyId]);
        } catch (PDOException $exception) {
            if (stripos($exception->getMessage(), 'company_mail_settings') === false) {
                throw $exception;
            }
            return $settings;
        }
        if (!$row) {
            return $settings;
        }

        foreach (['enabled', 'smtp_auth', 'host', 'port', 'encryption', 'auth_type', 'username', 'from_email', 'from_name', 'reply_to_email', 'reply_to_name', 'timeout'] as $key) {
            if (array_key_exists($key, $row)) {
                $settings[$key] = $row[$key];
            }
        }
        $settings['enabled'] = (int) $settings['enabled'];
        $settings['smtp_auth'] = (int) $settings['smtp_auth'];
        $settings['port'] = (int) $settings['port'];
        $settings['timeout'] = (int) $settings['timeout'];
        $settings['password'] = $this->decryptPassword((string) ($row['password_ciphertext'] ?? ''), $companyId);
        $settings['password_configured'] = $settings['password'] !== '' ? 1 : 0;

        return $settings;
    }

    public function save(int $companyId, array $data): void
    {
        if ($companyId <= 0) {
            throw new InvalidArgumentException('La empresa es obligatoria para guardar correo SMTP.');
        }

        $clearPassword = !empty($data['clear_password']);
        $password = (string) ($data['password'] ?? '');
        $current = $this->find($companyId);
        if ($clearPassword) {
            $password = '';
        } elseif ($password === '') {
            $password = (string) ($current['password'] ?? '');
        }
        $ciphertext = $password !== '' ? encrypt_payload(['password' => $password], 'company-mail:' . $companyId) : null;

        $this->db->execute('
            INSERT INTO company_mail_settings
                (company_id, enabled, host, port, encryption, smtp_auth, auth_type, username, password_ciphertext,
                 from_email, from_name, reply_to_email, reply_to_name, timeout)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                enabled = VALUES(enabled), host = VALUES(host), port = VALUES(port), encryption = VALUES(encryption),
                smtp_auth = VALUES(smtp_auth), auth_type = VALUES(auth_type), username = VALUES(username),
                password_ciphertext = VALUES(password_ciphertext), from_email = VALUES(from_email),
                from_name = VALUES(from_name), reply_to_email = VALUES(reply_to_email),
                reply_to_name = VALUES(reply_to_name), timeout = VALUES(timeout), updated_at = CURRENT_TIMESTAMP
        ', [
            $companyId,
            (int) !empty($data['enabled']),
            trim((string) ($data['host'] ?? '')),
            (int) ($data['port'] ?? 587),
            (string) ($data['encryption'] ?? 'starttls'),
            (int) !empty($data['smtp_auth']),
            trim((string) ($data['auth_type'] ?? '')) ?: null,
            trim((string) ($data['username'] ?? '')),
            $ciphertext,
            trim((string) ($data['from_email'] ?? '')),
            trim((string) ($data['from_name'] ?? '')),
            trim((string) ($data['reply_to_email'] ?? '')) ?: null,
            trim((string) ($data['reply_to_name'] ?? '')) ?: null,
            max(5, min(120, (int) ($data['timeout'] ?? 30))),
        ]);
    }

    private function decryptPassword(string $ciphertext, int $companyId): string
    {
        if ($ciphertext === '') {
            return '';
        }

        try {
            $payload = decrypt_payload($ciphertext, 'company-mail:' . $companyId);
            return (string) ($payload['password'] ?? '');
        } catch (Throwable $exception) {
            security_log('No se pudo descifrar la clave SMTP de la empresa ' . $companyId);
            return '';
        }
    }
}
