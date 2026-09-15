SET @sql := IF(
    (SELECT COUNT(*) FROM information_schema.columns
     WHERE table_schema = 'e_talent_evaluaciones_encuestas'
       AND table_name = 'evaluation_survey_forms'
       AND column_name = 'company_id') = 0,
    'ALTER TABLE e_talent_evaluaciones_encuestas.evaluation_survey_forms ADD COLUMN company_id INT UNSIGNED NULL AFTER id',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql := IF(
    (SELECT COUNT(*) FROM information_schema.statistics
     WHERE table_schema = 'e_talent_evaluaciones_encuestas'
       AND table_name = 'evaluation_survey_forms'
       AND index_name = 'idx_esf_company_type_status') = 0,
    'ALTER TABLE e_talent_evaluaciones_encuestas.evaluation_survey_forms ADD KEY idx_esf_company_type_status (company_id, form_type, status)',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

CREATE TABLE IF NOT EXISTS e_talent_tests.test_process_evaluation_forms (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    process_id INT UNSIGNED NOT NULL,
    form_id INT UNSIGNED NOT NULL,
    sort_order INT NOT NULL DEFAULT 10,
    is_required TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_process_evaluation_form (process_id, form_id),
    KEY idx_process_evaluation_forms_process (process_id, sort_order),
    KEY idx_process_evaluation_forms_form (form_id),
    CONSTRAINT fk_process_evaluation_forms_process FOREIGN KEY (process_id) REFERENCES e_talent_tests.test_processes(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
