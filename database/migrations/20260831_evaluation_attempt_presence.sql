SET @sql = IF(
    (SELECT COUNT(*) FROM information_schema.columns
     WHERE table_schema = 'e_talent_evaluaciones_encuestas'
       AND table_name = 'evaluation_survey_attempts'
       AND column_name = 'last_seen_at') = 0,
    'ALTER TABLE e_talent_evaluaciones_encuestas.evaluation_survey_attempts ADD COLUMN last_seen_at DATETIME NULL AFTER updated_at, ADD KEY idx_esa_status_presence (status, last_seen_at, process_id, user_id)',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
