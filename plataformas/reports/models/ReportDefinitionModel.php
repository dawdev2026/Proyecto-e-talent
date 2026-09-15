<?php
declare(strict_types=1);

final class ReportDefinitionModel
{
    private Database $db;

    public function __construct(?Database $db = null)
    {
        $this->db = $db ?: database('core');
    }

    public function all(bool $activeOnly = false): array
    {
        $where = $activeOnly ? "WHERE r.status = 'active'" : "WHERE r.status <> 'inactive'";

        return $this->db->fetchAll("\n            SELECT r.*,\n                   COUNT(DISTINCT CASE WHEN a.removed_at IS NULL THEN a.company_id END) AS companies_count\n            FROM report_definitions r\n            LEFT JOIN report_company_assignments a ON a.report_id = r.id\n            {$where}\n            GROUP BY r.id\n            ORDER BY r.status ASC, r.name ASC, r.id ASC\n        ");
    }

    public function find(int $id): ?array
    {
        return $this->db->fetch('SELECT * FROM report_definitions WHERE id = ? LIMIT 1', [$id]);
    }

    public function currentVersion(int $reportId): ?array
    {
        return $this->db->fetch(
            'SELECT * FROM report_definition_versions WHERE report_id = ? ORDER BY version DESC, id DESC LIMIT 1',
            [$reportId]
        );
    }

    public function types(bool $activeOnly = true): array
    {
        return $this->db->fetchAll(
            'SELECT * FROM report_types ' . ($activeOnly ? 'WHERE is_active = 1 ' : '') . 'ORDER BY name ASC, id ASC'
        );
    }

    public function functionalities(bool $activeOnly = true): array
    {
        return $this->db->fetchAll(
            'SELECT * FROM report_functionalities ' . ($activeOnly ? 'WHERE is_active = 1 ' : '') . 'ORDER BY name ASC, id ASC'
        );
    }

    public function functionalityKeysForReport(int $reportId, bool $activeOnly = true): array
    {
        if ($reportId <= 0) {
            return [];
        }

        $rows = $this->db->fetchAll(
            'SELECT f.functionality_key
             FROM report_functionality_assignments a
             INNER JOIN report_functionalities f ON f.id = a.functionality_id
             WHERE a.report_id = ? AND a.removed_at IS NULL' . ($activeOnly ? ' AND f.is_active = 1' : '') . '
             ORDER BY f.name ASC, f.id ASC',
            [$reportId]
        );

        return array_values(array_map(static fn(array $row): string => (string) $row['functionality_key'], $rows));
    }

    public function syncFunctionalities(int $reportId, array $keys, int $userId, int $versionId): void
    {
        $keys = array_values(array_unique(array_filter(array_map('strval', $keys), static fn(string $key): bool => $key !== '')));
        $rows = [];
        if ($keys) {
            $placeholders = implode(',', array_fill(0, count($keys), '?'));
            $rows = $this->db->fetchAll(
                "SELECT id, functionality_key FROM report_functionalities WHERE is_active = 1 AND functionality_key IN ({$placeholders})",
                $keys
            );
        }
        $ids = array_map(static fn(array $row): int => (int) $row['id'], $rows);
        if (count($ids) !== count($keys)) {
            throw new InvalidArgumentException('Una o más funcionalidades seleccionadas no son válidas.');
        }

        $this->db->transaction(function () use ($reportId, $ids, $userId, $versionId): void {
            $existing = $this->db->fetchAll(
                'SELECT functionality_id FROM report_functionality_assignments WHERE report_id = ?',
                [$reportId]
            );
            $existingIds = array_map(static fn(array $row): int => (int) $row['functionality_id'], $existing);
            $removedIds = array_values(array_diff($existingIds, $ids));
            if ($removedIds) {
                $placeholders = implode(',', array_fill(0, count($removedIds), '?'));
                $this->db->execute(
                    'UPDATE report_functionality_assignments SET removed_at = CURRENT_TIMESTAMP, assigned_by = ? WHERE report_id = ? AND functionality_id IN (' . $placeholders . ') AND removed_at IS NULL',
                    array_merge([$userId, $reportId], $removedIds)
                );
            }
            if ($ids) {
                $params = [];
                foreach ($ids as $functionalityId) array_push($params, $reportId, $functionalityId, $userId);
                $this->db->execute(
                    'INSERT INTO report_functionality_assignments (report_id, functionality_id, assigned_by, removed_at) VALUES ' . implode(', ', array_fill(0, count($ids), '(?, ?, ?, NULL)')) . ' ON DUPLICATE KEY UPDATE assigned_by = VALUES(assigned_by), assigned_at = CURRENT_TIMESTAMP, removed_at = NULL',
                    $params
                );
                $versionParams = [];
                foreach ($ids as $functionalityId) array_push($versionParams, $versionId, $functionalityId);
                $this->db->execute(
                    'INSERT IGNORE INTO report_definition_version_functionalities (version_id, functionality_id) VALUES ' . implode(', ', array_fill(0, count($ids), '(?, ?)')),
                    $versionParams
                );
            }
        });
    }

    public function createVersion(int $reportId, array $data, int $version, ?string $summary = null): int
    {
        return $this->db->insert(
            'INSERT INTO report_definition_versions
                (report_id, version, name, markdown_content, design_markdown_content, source_filename, design_source_filename,
                 content_sha256, design_content_sha256, interpreter_version, design_interpreter_version, change_summary, created_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [$reportId, $version, $data['name'], $data['markdown_content'], $data['design_markdown_content'] ?? null,
                $data['source_filename'], $data['design_source_filename'] ?? null, $data['content_sha256'],
                $data['design_content_sha256'] ?? null, $data['interpreter_version'] ?? '1.0',
                $data['design_interpreter_version'] ?? null, $summary, $data['user_id'] ?? null]
        );
    }

    public function findBySlug(string $slug, ?int $exceptId = null): ?array
    {
        $sql = 'SELECT id, slug FROM report_definitions WHERE slug = ?';
        $params = [$slug];
        if ($exceptId) {
            $sql .= ' AND id <> ?';
            $params[] = $exceptId;
        }
        $sql .= ' LIMIT 1';

        return $this->db->fetch($sql, $params);
    }

    public function create(array $data): int
    {
        return $this->db->insert(
            'INSERT INTO report_definitions
                (type_id, name, slug, markdown_content, design_markdown_content, source_filename, design_source_filename,
                 content_sha256, design_content_sha256, version, interpreter_version, design_interpreter_version,
                 status, approval_status, created_by, updated_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1, ?, ?, ?, ?, ?, ?)',
            [
                !empty($data['type_id']) ? (int) $data['type_id'] : null, $data['name'], $data['slug'],
                $data['markdown_content'], $data['design_markdown_content'] ?? null, $data['source_filename'],
                $data['design_source_filename'] ?? null, $data['content_sha256'], $data['design_content_sha256'] ?? null,
                $data['interpreter_version'] ?? '1.0', $data['design_interpreter_version'] ?? null, $data['status'],
                $data['approval_status'] ?? ($data['status'] === 'active' ? 'review' : 'draft'),
                $data['user_id'], $data['user_id'],
            ]
        );
    }

    public function update(int $id, array $data): void
    {
        $this->db->execute(
            'UPDATE report_definitions
             SET type_id = ?, name = ?, slug = ?, markdown_content = ?, source_filename = ?, content_sha256 = ?,
                 design_markdown_content = ?, design_source_filename = ?, design_content_sha256 = ?,
                 version = version + 1, interpreter_version = ?, design_interpreter_version = ?, status = ?,
                 approval_status = ?, approved_by = NULL, approved_at = NULL, updated_by = ?
             WHERE id = ?',
            [
                !empty($data['type_id']) ? (int) $data['type_id'] : null,
                $data['name'], $data['slug'], $data['markdown_content'], $data['source_filename'], $data['content_sha256'],
                $data['design_markdown_content'] ?? null, $data['design_source_filename'] ?? null,
                $data['design_content_sha256'] ?? null, $data['interpreter_version'] ?? '1.0',
                $data['design_interpreter_version'] ?? null, $data['status'],
                $data['approval_status'] ?? ($data['status'] === 'active' ? 'review' : 'draft'),
                $data['user_id'], $id,
            ]
        );
    }

    public function softDelete(int $id, int $userId): void
    {
        $this->db->transaction(function () use ($id, $userId): void {
            $this->db->execute(
                "UPDATE report_definitions
                 SET status = 'inactive', approval_status = 'inactive', approved_by = NULL, approved_at = NULL, updated_by = ?
                 WHERE id = ? AND status <> 'inactive'",
                [$userId, $id]
            );

            // Conserva el registro histórico, pero retira su disponibilidad empresarial.
            $this->db->execute(
                'UPDATE report_company_assignments
                 SET removed_at = CURRENT_TIMESTAMP, assigned_by = ?
                 WHERE report_id = ? AND removed_at IS NULL',
                [$userId, $id]
            );
        });
    }

    public function assignmentsForCompany(int $companyId): array
    {
        return $this->db->fetchAll("\n            SELECT r.id, r.name, r.slug, r.version, r.status, r.source_filename,
                   CASE WHEN a.id IS NOT NULL AND a.removed_at IS NULL THEN 1 ELSE 0 END AS is_assigned
            FROM report_definitions r
            LEFT JOIN report_company_assignments a
              ON a.report_id = r.id AND a.company_id = ?
            WHERE r.status = 'active'
            ORDER BY r.status ASC, r.name ASC, r.id ASC
        ", [$companyId]);
    }

    public function isAssignedToCompany(int $reportId, int $companyId): bool
    {
        $row = $this->db->fetch(
            'SELECT id FROM report_company_assignments WHERE report_id = ? AND company_id = ? AND removed_at IS NULL LIMIT 1',
            [$reportId, $companyId]
        );

        return $row !== null;
    }

    /**
     * Informes publicados asignados a una empresa para resolver acciones
     * disponibles dentro de un contexto funcional.
     */
    public function publishedAssignmentsForCompany(int $companyId): array
    {
        if ($companyId <= 0) {
            return [];
        }

        return $this->db->fetchAll(
            "SELECT r.id, r.name, r.slug, r.version, r.markdown_content, r.source_filename
             FROM report_definitions r
             INNER JOIN report_company_assignments a
               ON a.report_id = r.id
              AND a.company_id = ?
              AND a.removed_at IS NULL
             WHERE r.status = 'active'
               AND r.approval_status = 'published'
             ORDER BY r.name ASC, r.id ASC",
            [$companyId]
        );
    }

    public function publishedAssignmentsForCompanyAndFunctionality(int $companyId, string $functionalityKey): array
    {
        if ($companyId <= 0 || trim($functionalityKey) === '') {
            return [];
        }

        return $this->db->fetchAll(
            "SELECT DISTINCT r.id, r.name, r.slug, r.version, r.markdown_content, r.source_filename
             FROM report_definitions r
             INNER JOIN report_company_assignments ca
               ON ca.report_id = r.id AND ca.company_id = ? AND ca.removed_at IS NULL
             INNER JOIN report_functionality_assignments fa
               ON fa.report_id = r.id AND fa.removed_at IS NULL
             INNER JOIN report_functionalities f
               ON f.id = fa.functionality_id AND f.functionality_key = ? AND f.is_active = 1
             WHERE r.status = 'active' AND r.approval_status = 'published'
             ORDER BY r.name ASC, r.id ASC",
            [$companyId, $functionalityKey]
        );
    }

    public function syncCompanyAssignments(int $companyId, array $reportIds, int $userId): void
    {
        $reportIds = array_values(array_unique(array_filter(array_map('intval', $reportIds), static fn (int $id): bool => $id > 0)));
        if ($reportIds) {
            $placeholders = implode(',', array_fill(0, count($reportIds), '?'));
            $activeRows = $this->db->fetchAll(
                "SELECT id FROM report_definitions WHERE status = 'active' AND id IN ({$placeholders})",
                $reportIds
            );
            $reportIds = array_map(static fn (array $row): int => (int) $row['id'], $activeRows);
        }

        $this->db->transaction(function () use ($companyId, $reportIds, $userId): void {
            $existing = $this->db->fetchAll('SELECT report_id FROM report_company_assignments WHERE company_id = ?', [$companyId]);
            $existingIds = array_map(static fn (array $row): int => (int) $row['report_id'], $existing);

            $removedIds = array_values(array_diff($existingIds, $reportIds));
            if ($removedIds) {
                $placeholders = implode(',', array_fill(0, count($removedIds), '?'));
                $this->db->execute(
                    'UPDATE report_company_assignments SET removed_at = CURRENT_TIMESTAMP, assigned_by = ? WHERE report_id IN (' . $placeholders . ') AND company_id = ? AND removed_at IS NULL',
                    array_merge([$userId], $removedIds, [$companyId])
                );
            }
            if ($reportIds) {
                $params = [];
                foreach ($reportIds as $reportId) array_push($params, $reportId, $companyId, $userId);
                $this->db->execute(
                    'INSERT INTO report_company_assignments (report_id, company_id, assigned_by, removed_at) VALUES ' . implode(', ', array_fill(0, count($reportIds), '(?, ?, ?, NULL)')) . ' ON DUPLICATE KEY UPDATE assigned_by = VALUES(assigned_by), assigned_at = CURRENT_TIMESTAMP, removed_at = NULL',
                    $params
                );
            }
        });
    }

    public static function slugify(string $value): string
    {
        $value = trim(mb_strtolower($value));
        $value = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value) ?: $value;
        $value = preg_replace('/[^a-z0-9]+/', '-', $value) ?? '';
        return trim($value, '-') ?: 'informe';
    }
}
