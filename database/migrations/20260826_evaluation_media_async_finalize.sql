ALTER TABLE e_talent_evaluaciones_encuestas.evaluation_survey_media_evidence
    MODIFY COLUMN status ENUM('pending','recording','uploading','processing','saved','partial','failed','not_supported','not_consented') NOT NULL DEFAULT 'pending';
