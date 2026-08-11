<?php
declare(strict_types=1);

final class CompanyModel
{
    private Database $db;

    public function __construct(?Database $db = null)
    {
        $this->db = $db ?: database();
    }

    public function all(): array
    {
        return $this->db->fetchAll("
            SELECT c.*, COUNT(DISTINCT CASE WHEN u.role = 'usuario' THEN u.id END) AS users_count,
                   COUNT(DISTINCT CASE WHEN u.role = 'company_admin' AND u.is_active = 1 THEN u.id END) AS admins_count
            FROM companies c
            LEFT JOIN users u ON u.company_id = c.id
            GROUP BY c.id
            ORDER BY c.is_active DESC, c.name ASC
        ");
    }

    public function active(): array
    {
        return $this->db->fetchAll('SELECT id, name FROM companies WHERE is_active = 1 ORDER BY name');
    }

    public function find(int $id): ?array
    {
        return $this->db->fetch('SELECT * FROM companies WHERE id = ? LIMIT 1', [$id]);
    }

    public function activeAdmins(int $companyId): array
    {
        return $this->db->fetchAll("\n            SELECT id, name, email, is_active\n            FROM users\n            WHERE company_id = ? AND role = 'company_admin'\n            ORDER BY is_active DESC, name ASC, id ASC\n        ", [$companyId]);
    }

    public function activeAdminCount(int $companyId): int
    {
        $row = $this->db->fetch("SELECT COUNT(*) AS total FROM users WHERE company_id = ? AND role = 'company_admin' AND is_active = 1", [$companyId]);
        return (int) ($row['total'] ?? 0);
    }

    public function create(array $data): int
    {
        return (int) $this->db->transaction(function () use ($data): int {
            $companyId = $this->db->insert('
                INSERT INTO companies (name, tax_id, is_active)
                VALUES (?, ?, ?)
            ', [
                $data['name'],
                $data['tax_id'] ?: null,
                (int) $data['is_active'],
            ]);
            $profile = $this->db->fetch("SELECT id FROM role_profiles WHERE role_key = 'company_admin' AND is_active = 1 LIMIT 1");
            if (!$profile) {
                throw new RuntimeException('No existe el perfil de administrador de empresa. Ejecuta la migracion multiempresa.');
            }

            $this->db->insert('
                INSERT INTO users (first_names, last_names, age, name, email, password_hash, role, profile_id, company_id, is_active)
                VALUES (?, ?, 0, ?, ?, ?, \'company_admin\', ?, ?, 1)
            ', [
                $data['admin_first_names'],
                $data['admin_last_names'],
                trim($data['admin_first_names'] . ' ' . $data['admin_last_names']),
                strtolower($data['admin_email']),
                password_hash($data['admin_password'], PASSWORD_BCRYPT),
                (int) $profile['id'],
                $companyId,
            ]);

            return $companyId;
        });
    }

    public function update(int $id, array $data): void
    {
        $this->db->execute('
            UPDATE companies
            SET name = ?, tax_id = ?, is_active = ?
            WHERE id = ?
        ', [
            $data['name'],
            $data['tax_id'] ?: null,
            (int) $data['is_active'],
            $id,
        ]);
    }

    public function provisionAdmin(int $companyId, array $data): int
    {
        return (int) $this->db->transaction(function () use ($companyId, $data): int {
            $profile = $this->db->fetch("SELECT id FROM role_profiles WHERE role_key = 'company_admin' AND is_active = 1 LIMIT 1");
            if (!$profile) {
                throw new RuntimeException('No existe el perfil de administrador de empresa. Ejecuta la migracion multiempresa.');
            }

            return $this->db->insert('
                INSERT INTO users (first_names, last_names, age, name, email, password_hash, role, profile_id, company_id, is_active)
                VALUES (?, ?, 0, ?, ?, ?, \'company_admin\', ?, ?, 1)
            ', [
                $data['admin_first_names'],
                $data['admin_last_names'],
                trim($data['admin_first_names'] . ' ' . $data['admin_last_names']),
                strtolower($data['admin_email']),
                password_hash($data['admin_password'], PASSWORD_BCRYPT),
                (int) $profile['id'],
                $companyId,
            ]);
        });
    }
}
