SET @sql = IF(
    (SELECT COUNT(*)
     FROM information_schema.columns
     WHERE table_schema = 'e_talent_evaluaciones_encuestas'
       AND table_name = 'evaluation_survey_attempts'
       AND column_name = 'question_set_json') = 0,
    'ALTER TABLE e_talent_evaluaciones_encuestas.evaluation_survey_attempts ADD COLUMN question_set_json LONGTEXT NULL AFTER max_score',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
