USE e_talent_core;

CREATE TABLE IF NOT EXISTS report_types (
    id SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
    type_key VARCHAR(80) NOT NULL,
    name VARCHAR(120) NOT NULL,
    description VARCHAR(255) NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_report_types_key (type_key),
    KEY idx_report_types_active_name (is_active, name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO report_types (type_key, name, description)
VALUES
    ('psychometric', 'Psicométrico', 'Informe basado en evaluaciones psicométricas.'),
    ('process', 'Proceso', 'Informe de avance y estado de un proceso.'),
    ('survey', 'Encuesta', 'Informe basado en encuestas y evaluaciones con nota.'),
    ('interview', 'Entrevista', 'Informe asociado a procesos de entrevistas.'),
    ('custom', 'Personalizado', 'Informe configurable mediante fuentes autorizadas.')
ON DUPLICATE KEY UPDATE name = VALUES(name), description = VALUES(description);

ALTER TABLE report_definitions
    ADD COLUMN type_id SMALLINT UNSIGNED NULL AFTER id,
    ADD COLUMN interpreter_version VARCHAR(30) NOT NULL DEFAULT '1.0' AFTER version,
    ADD COLUMN approval_status ENUM('draft', 'review', 'approved', 'published', 'obsolete', 'inactive') NOT NULL DEFAULT 'draft' AFTER status,
    ADD COLUMN approved_by INT UNSIGNED NULL AFTER updated_by,
    ADD COLUMN approved_at TIMESTAMP NULL DEFAULT NULL AFTER approved_by,
    ADD KEY idx_report_definitions_type_status (type_id, approval_status),
    ADD CONSTRAINT fk_report_definitions_type FOREIGN KEY (type_id) REFERENCES report_types(id) ON DELETE SET NULL,
    ADD CONSTRAINT fk_report_definitions_approved_by FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE SET NULL;

UPDATE report_definitions
SET approval_status = CASE status
    WHEN 'active' THEN 'published'
    WHEN 'inactive' THEN 'inactive'
    ELSE 'draft'
END
WHERE approval_status = 'draft';

CREATE TABLE IF NOT EXISTS report_definition_versions (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    report_id BIGINT UNSIGNED NOT NULL,
    version INT UNSIGNED NOT NULL,
    name VARCHAR(160) NOT NULL,
    markdown_content MEDIUMTEXT NOT NULL,
    source_filename VARCHAR(255) NOT NULL,
    content_sha256 CHAR(64) NOT NULL,
    interpreter_version VARCHAR(30) NOT NULL DEFAULT '1.0',
    change_summary VARCHAR(255) NULL,
    created_by INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_report_definition_versions (report_id, version),
    KEY idx_report_definition_versions_hash (content_sha256),
    CONSTRAINT fk_report_definition_versions_report FOREIGN KEY (report_id) REFERENCES report_definitions(id) ON DELETE CASCADE,
    CONSTRAINT fk_report_definition_versions_created_by FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO report_definition_versions
    (report_id, version, name, markdown_content, source_filename, content_sha256, interpreter_version, change_summary, created_by, created_at)
SELECT id, version, name, markdown_content, source_filename, content_sha256, interpreter_version, 'Migración inicial del registro de informes', created_by, created_at
FROM report_definitions;

CREATE TABLE IF NOT EXISTS report_source_catalog (
    id SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
    source_key VARCHAR(120) NOT NULL,
    provider VARCHAR(120) NOT NULL,
    method VARCHAR(120) NOT NULL,
    description VARCHAR(255) NULL,
    sensitivity ENUM('normal', 'personal', 'psychometric', 'restricted') NOT NULL DEFAULT 'personal',
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_report_source_catalog_key (source_key),
    KEY idx_report_source_catalog_active (is_active, source_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO report_source_catalog (source_key, provider, method, description, sensitivity)
VALUES
 ('source.process_user', 'TestProcessModel', 'processUser', 'Datos del usuario dentro del proceso.', 'personal'),
 ('source.process', 'TestProcessModel', 'find', 'Datos generales del proceso.', 'personal'),
 ('source.sessions', 'TestProcessModel', 'processDashboardSessionsForUser', 'Sesiones y avance del usuario.', 'psychometric'),
 ('source.ranking', 'ProgressRankingSummaryService', 'build', 'Resumen de ranking permitido.', 'psychometric'),
 ('source.instrument_summaries', 'TestSessionModel', 'summaryForSession', 'Resúmenes por instrumento.', 'psychometric')
ON DUPLICATE KEY UPDATE provider = VALUES(provider), method = VALUES(method), description = VALUES(description), sensitivity = VALUES(sensitivity);

UPDATE role_profiles
SET permissions = JSON_ARRAY_APPEND(
    JSON_ARRAY_APPEND(
        JSON_ARRAY_APPEND(
            JSON_ARRAY_APPEND(
                JSON_ARRAY_APPEND(COALESCE(permissions, JSON_ARRAY()), '$', 'approve_reports'),
                '$', 'generate_reports'
            ),
            '$', 'download_reports'
        ),
        '$', 'view_report_history'
    ),
    '$', 'run_report_batches'
)
WHERE role_key = 'admin'
  AND JSON_CONTAINS(COALESCE(permissions, JSON_ARRAY()), JSON_QUOTE('approve_reports')) = 0;

CREATE TABLE IF NOT EXISTS report_execution_logs (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    report_id BIGINT UNSIGNED NOT NULL,
    report_version_id BIGINT UNSIGNED NULL,
    company_id INT UNSIGNED NOT NULL,
    process_id BIGINT UNSIGNED NOT NULL,
    user_id INT UNSIGNED NOT NULL,
    executed_by INT UNSIGNED NULL,
    format ENUM('html', 'pdf', 'screen', 'xlsx') NOT NULL,
    status ENUM('started', 'completed', 'failed') NOT NULL DEFAULT 'started',
    request_hash CHAR(64) NULL,
    error_code VARCHAR(80) NULL,
    error_message VARCHAR(500) NULL,
    started_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    completed_at TIMESTAMP NULL DEFAULT NULL,
    duration_ms INT UNSIGNED NULL,
    PRIMARY KEY (id),
    KEY idx_report_execution_company_date (company_id, started_at),
    KEY idx_report_execution_report_date (report_id, started_at),
    KEY idx_report_execution_process_user (process_id, user_id),
    CONSTRAINT fk_report_execution_report FOREIGN KEY (report_id) REFERENCES report_definitions(id) ON DELETE RESTRICT,
    CONSTRAINT fk_report_execution_version FOREIGN KEY (report_version_id) REFERENCES report_definition_versions(id) ON DELETE SET NULL,
    CONSTRAINT fk_report_execution_company FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE RESTRICT,
    CONSTRAINT fk_report_execution_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT,
    CONSTRAINT fk_report_execution_executed_by FOREIGN KEY (executed_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS report_generation_batches (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    report_id BIGINT UNSIGNED NOT NULL,
    company_id INT UNSIGNED NOT NULL,
    process_id BIGINT UNSIGNED NULL,
    requested_by INT UNSIGNED NULL,
    format ENUM('html', 'pdf', 'xlsx') NOT NULL,
    status ENUM('queued', 'running', 'completed', 'failed', 'cancelled') NOT NULL DEFAULT 'queued',
    total_items INT UNSIGNED NOT NULL DEFAULT 0,
    completed_items INT UNSIGNED NOT NULL DEFAULT 0,
    failed_items INT UNSIGNED NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    started_at TIMESTAMP NULL DEFAULT NULL,
    completed_at TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (id),
    KEY idx_report_batches_company_status (company_id, status, created_at),
    CONSTRAINT fk_report_batches_report FOREIGN KEY (report_id) REFERENCES report_definitions(id) ON DELETE RESTRICT,
    CONSTRAINT fk_report_batches_company FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE RESTRICT,
    CONSTRAINT fk_report_batches_requested_by FOREIGN KEY (requested_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS report_generation_batch_items (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    batch_id BIGINT UNSIGNED NOT NULL,
    user_id INT UNSIGNED NOT NULL,
    execution_id BIGINT UNSIGNED NULL,
    status ENUM('queued', 'completed', 'failed') NOT NULL DEFAULT 'queued',
    error_message VARCHAR(500) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    completed_at TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_report_batch_user (batch_id, user_id),
    KEY idx_report_batch_items_status (batch_id, status),
    CONSTRAINT fk_report_batch_items_batch FOREIGN KEY (batch_id) REFERENCES report_generation_batches(id) ON DELETE CASCADE,
    CONSTRAINT fk_report_batch_items_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT,
    CONSTRAINT fk_report_batch_items_execution FOREIGN KEY (execution_id) REFERENCES report_execution_logs(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
