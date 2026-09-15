USE e_talent_core;

CREATE TABLE IF NOT EXISTS report_definitions (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(160) NOT NULL,
    slug VARCHAR(180) NOT NULL,
    markdown_content MEDIUMTEXT NOT NULL,
    source_filename VARCHAR(255) NOT NULL,
    content_sha256 CHAR(64) NOT NULL,
    version INT UNSIGNED NOT NULL DEFAULT 1,
    status ENUM('draft', 'active', 'inactive') NOT NULL DEFAULT 'draft',
    created_by INT UNSIGNED NULL,
    updated_by INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_report_definitions_slug (slug),
    KEY idx_report_definitions_status_name (status, name),
    KEY idx_report_definitions_created_by (created_by),
    CONSTRAINT fk_report_definitions_created_by FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_report_definitions_updated_by FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS report_company_assignments (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    report_id BIGINT UNSIGNED NOT NULL,
    company_id INT UNSIGNED NOT NULL,
    assigned_by INT UNSIGNED NULL,
    assigned_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    removed_at TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_report_company_assignment (report_id, company_id),
    KEY idx_report_company_assignments_company (company_id, removed_at),
    KEY idx_report_company_assignments_report (report_id, removed_at),
    CONSTRAINT fk_report_company_assignments_report FOREIGN KEY (report_id) REFERENCES report_definitions(id) ON DELETE CASCADE,
    CONSTRAINT fk_report_company_assignments_company FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE,
    CONSTRAINT fk_report_company_assignments_assigned_by FOREIGN KEY (assigned_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
