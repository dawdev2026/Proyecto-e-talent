ALTER TABLE e_talent_evaluaciones_encuestas.evaluation_survey_media_evidence
    ADD COLUMN uploaded_bytes BIGINT UNSIGNED NOT NULL DEFAULT 0 AFTER file_size;

UPDATE e_talent_evaluaciones_encuestas.evaluation_survey_media_evidence e
LEFT JOIN (
    SELECT evidence_id, COALESCE(SUM(size_bytes), 0) AS uploaded_bytes
    FROM e_talent_evaluaciones_encuestas.evaluation_survey_media_chunks
    GROUP BY evidence_id
) c ON c.evidence_id = e.id
SET e.uploaded_bytes = COALESCE(c.uploaded_bytes, 0);
