-- Agrega el MD de diseño visual como configuración versionada e independiente.
-- Requiere 20260816_reports_platform.sql y 20260816_reports_robust_registry.sql.
USE e_talent_core;

ALTER TABLE report_definitions
    ADD COLUMN design_markdown_content MEDIUMTEXT NULL AFTER markdown_content,
    ADD COLUMN design_source_filename VARCHAR(255) NULL AFTER source_filename,
    ADD COLUMN design_content_sha256 CHAR(64) NULL AFTER content_sha256,
    ADD COLUMN design_interpreter_version VARCHAR(30) NULL AFTER interpreter_version;

ALTER TABLE report_definition_versions
    ADD COLUMN design_markdown_content MEDIUMTEXT NULL AFTER markdown_content,
    ADD COLUMN design_source_filename VARCHAR(255) NULL AFTER source_filename,
    ADD COLUMN design_content_sha256 CHAR(64) NULL AFTER content_sha256,
    ADD COLUMN design_interpreter_version VARCHAR(30) NULL AFTER interpreter_version;
