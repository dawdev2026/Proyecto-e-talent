ALTER TABLE e_talent_evaluaciones_encuestas.evaluation_survey_attempts
    ADD COLUMN process_id INT UNSIGNED NULL AFTER user_id,
    DROP INDEX uq_esa_form_user_attempt,
    ADD UNIQUE KEY uq_esa_process_form_user_attempt (process_id, form_id, user_id, attempt_number),
    ADD KEY idx_esa_process_form_user (process_id, form_id, user_id);

-- Los intentos históricos se conservan sin proceso cuando una persona tuvo
-- la misma evaluación asignada a más de un proceso y no es posible inferirlo.
