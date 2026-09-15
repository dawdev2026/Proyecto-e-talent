<?php
declare(strict_types=1);

final class CompanyBrandingModel
{
    private Database $db;

    public function __construct(?Database $db = null)
    {
        $this->db = $db ?: database();
    }

    public function allForCompany(int $companyId): array
    {
        if ($companyId <= 0) {
            return [];
        }

        try {
            $rows = $this->db->fetchAll(
                'SELECT setting_key, setting_value FROM company_branding WHERE company_id = ? ORDER BY setting_key',
                [$companyId]
            );
        } catch (PDOException $exception) {
            // Allows the application to preserve the global branding while the
            // optional multi-company branding migration is still pending.
            if (stripos($exception->getMessage(), 'company_branding') === false) {
                throw $exception;
            }
            return [];
        }

        $settings = [];
        foreach ($rows as $row) {
            $settings[(string) $row['setting_key']] = (string) $row['setting_value'];
        }

        return $settings;
    }

    public function setMany(int $companyId, array $settings): void
    {
        if ($companyId <= 0 || !$settings) {
            return;
        }

        $this->db->transaction(function (Database $db) use ($companyId, $settings): void {
            foreach ($settings as $key => $value) {
                $db->execute('
                    INSERT INTO company_branding (company_id, setting_key, setting_value)
                    VALUES (?, ?, ?)
                    ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_at = CURRENT_TIMESTAMP
                ', [$companyId, (string) $key, (string) $value]);
            }
        });
    }

    public function deleteMany(int $companyId, array $keys): void
    {
        if ($companyId <= 0 || !$keys) {
            return;
        }

        $placeholders = implode(',', array_fill(0, count($keys), '?'));
        $this->db->execute(
            'DELETE FROM company_branding WHERE company_id = ? AND setting_key IN (' . $placeholders . ')',
            array_merge([$companyId], array_values($keys))
        );
    }
}
