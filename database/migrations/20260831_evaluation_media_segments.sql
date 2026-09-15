-- Conserva la evidencia audiovisual por cada ciclo de rendición/reapertura.
ALTER TABLE e_talent_evaluaciones_encuestas.evaluation_survey_media_evidence
    ADD COLUMN segment_number INT UNSIGNED NOT NULL DEFAULT 1 AFTER attempt_id;

ALTER TABLE e_talent_evaluaciones_encuestas.evaluation_survey_media_evidence
    DROP INDEX uq_esme_attempt,
    ADD UNIQUE KEY uq_esme_attempt_segment (attempt_id, segment_number),
    ADD KEY idx_esme_attempt_segment (attempt_id, segment_number);
