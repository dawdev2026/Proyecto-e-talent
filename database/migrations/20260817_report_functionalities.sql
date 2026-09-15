-- Catálogo y asignación versionada de funcionalidades de informes.
-- Requiere las migraciones de la plataforma de informes ya aplicadas.
USE e_talent_core;

CREATE TABLE IF NOT EXISTS report_functionalities (
    id SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
    functionality_key VARCHAR(80) NOT NULL,
    name VARCHAR(120) NOT NULL,
    description VARCHAR(255) NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_report_functionalities_key (functionality_key),
    KEY idx_report_functionalities_active (is_active, functionality_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO report_functionalities (functionality_key, name, description)
VALUES
    ('ranking', 'Ranking de procesos', 'Informes generados desde el ranking de procesos.'),
    ('evaluation_results', 'Resultados de evaluaciones', 'Informes basados en resultados de evaluaciones.'),
    ('process_summary', 'Resumen de proceso', 'Informes de avance y contexto de un proceso.'),
    ('interviews', 'Entrevistas', 'Informes asociados a entrevistas y sus resultados.'),
    ('surveys', 'Encuestas', 'Informes asociados a encuestas y evaluaciones.'),
    ('custom', 'Personalizado', 'Informes configurados para una funcionalidad propia.')
ON DUPLICATE KEY UPDATE name = VALUES(name), description = VALUES(description);

CREATE TABLE IF NOT EXISTS report_functionality_assignments (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    report_id BIGINT UNSIGNED NOT NULL,
    functionality_id SMALLINT UNSIGNED NOT NULL,
    assigned_by INT UNSIGNED NULL,
    assigned_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    removed_at TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_report_functionality_assignment (report_id, functionality_id),
    KEY idx_report_functionality_lookup (functionality_id, removed_at, report_id),
    CONSTRAINT fk_report_functionality_report FOREIGN KEY (report_id) REFERENCES report_definitions(id) ON DELETE CASCADE,
    CONSTRAINT fk_report_functionality_functionality FOREIGN KEY (functionality_id) REFERENCES report_functionalities(id) ON DELETE RESTRICT,
    CONSTRAINT fk_report_functionality_user FOREIGN KEY (assigned_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS report_definition_version_functionalities (
    version_id BIGINT UNSIGNED NOT NULL,
    functionality_id SMALLINT UNSIGNED NOT NULL,
    PRIMARY KEY (version_id, functionality_id),
    CONSTRAINT fk_report_version_functionality_version FOREIGN KEY (version_id) REFERENCES report_definition_versions(id) ON DELETE CASCADE,
    CONSTRAINT fk_report_version_functionality_functionality FOREIGN KEY (functionality_id) REFERENCES report_functionalities(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Compatibilidad inicial: los informes anteriores que declaraban source.ranking
-- se registran como informes de ranking sin modificar su contenido.
INSERT IGNORE INTO report_functionality_assignments (report_id, functionality_id)
SELECT r.id, f.id
FROM report_definitions r
INNER JOIN report_functionalities f ON f.functionality_key = 'ranking'
WHERE r.markdown_content LIKE '%## source.ranking%';

INSERT IGNORE INTO report_definition_version_functionalities (version_id, functionality_id)
SELECT v.id, f.id
FROM report_definition_versions v
INNER JOIN report_functionalities f ON f.functionality_key = 'ranking'
WHERE v.markdown_content LIKE '%## source.ranking%';
