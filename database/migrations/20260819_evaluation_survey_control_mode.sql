-- MVP de control para evaluaciones con nota.
-- No incluye captura audiovisual; esa capacidad requiere una etapa separada
-- de consentimiento, retencion y almacenamiento de evidencia.

SET @sql = IF(
    (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = 'e_talent_evaluaciones_encuestas' AND table_name = 'evaluation_survey_forms' AND column_name = 'control_mode') = 0,
    'ALTER TABLE e_talent_evaluaciones_encuestas.evaluation_survey_forms ADD COLUMN control_mode ENUM(''off'',''activity'',''supervised'') NOT NULL DEFAULT ''off'' AFTER duration_minutes',
    'SELECT 1'
);
PREPARE add_form_control_mode FROM @sql;
EXECUTE add_form_control_mode;
DEALLOCATE PREPARE add_form_control_mode;

SET @sql = IF(
    (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = 'e_talent_evaluaciones_encuestas' AND table_name = 'evaluation_survey_attempts' AND column_name = 'control_mode') = 0,
    'ALTER TABLE e_talent_evaluaciones_encuestas.evaluation_survey_attempts ADD COLUMN control_mode ENUM(''off'',''activity'',''supervised'') NOT NULL DEFAULT ''off'' AFTER user_id',
    'SELECT 1'
);
PREPARE add_attempt_control_mode FROM @sql;
EXECUTE add_attempt_control_mode;
DEALLOCATE PREPARE add_attempt_control_mode;

ALTER TABLE e_talent_evaluaciones_encuestas.evaluation_survey_forms
    MODIFY COLUMN control_mode ENUM('off','activity','supervised','supervised_audio_visual') NOT NULL DEFAULT 'off';
ALTER TABLE e_talent_evaluaciones_encuestas.evaluation_survey_attempts
    MODIFY COLUMN control_mode ENUM('off','activity','supervised','supervised_audio_visual') NOT NULL DEFAULT 'off';

SET @sql = IF((SELECT COUNT(*) FROM information_schema.columns WHERE table_schema='e_talent_evaluaciones_encuestas' AND table_name='evaluation_survey_forms' AND column_name='audio_visual_upload_failure_policy')=0, 'ALTER TABLE e_talent_evaluaciones_encuestas.evaluation_survey_forms ADD COLUMN audio_visual_upload_failure_policy ENUM(''continue'',''retry_once'',''block'') NOT NULL DEFAULT ''continue'' AFTER control_mode', 'SELECT 1');
PREPARE add_form_av_upload FROM @sql; EXECUTE add_form_av_upload; DEALLOCATE PREPARE add_form_av_upload;
SET @sql = IF((SELECT COUNT(*) FROM information_schema.columns WHERE table_schema='e_talent_evaluaciones_encuestas' AND table_name='evaluation_survey_forms' AND column_name='audio_visual_interruption_policy')=0, 'ALTER TABLE e_talent_evaluaciones_encuestas.evaluation_survey_forms ADD COLUMN audio_visual_interruption_policy ENUM(''continue'',''pause'',''block'') NOT NULL DEFAULT ''pause'' AFTER audio_visual_upload_failure_policy', 'SELECT 1');
PREPARE add_form_av_interrupt FROM @sql; EXECUTE add_form_av_interrupt; DEALLOCATE PREPARE add_form_av_interrupt;
SET @sql = IF((SELECT COUNT(*) FROM information_schema.columns WHERE table_schema='e_talent_evaluaciones_encuestas' AND table_name='evaluation_survey_forms' AND column_name='audio_visual_voice_policy')=0, 'ALTER TABLE e_talent_evaluaciones_encuestas.evaluation_survey_forms ADD COLUMN audio_visual_voice_policy ENUM(''log'',''warn'',''pause'') NOT NULL DEFAULT ''warn'' AFTER audio_visual_interruption_policy', 'SELECT 1');
PREPARE add_form_av_voice FROM @sql; EXECUTE add_form_av_voice; DEALLOCATE PREPARE add_form_av_voice;
SET @sql = IF((SELECT COUNT(*) FROM information_schema.columns WHERE table_schema='e_talent_evaluaciones_encuestas' AND table_name='evaluation_survey_forms' AND column_name='audio_visual_permission_policy')=0, 'ALTER TABLE e_talent_evaluaciones_encuestas.evaluation_survey_forms ADD COLUMN audio_visual_permission_policy ENUM(''continue'',''pause'',''block'') NOT NULL DEFAULT ''pause'' AFTER audio_visual_voice_policy', 'SELECT 1');
PREPARE add_form_av_permission FROM @sql; EXECUTE add_form_av_permission; DEALLOCATE PREPARE add_form_av_permission;
SET @sql = IF((SELECT COUNT(*) FROM information_schema.columns WHERE table_schema='e_talent_evaluaciones_encuestas' AND table_name='evaluation_survey_forms' AND column_name='audio_visual_quality_profile')=0, 'ALTER TABLE e_talent_evaluaciones_encuestas.evaluation_survey_forms ADD COLUMN audio_visual_quality_profile ENUM(''economical'',''standard'',''high'') NOT NULL DEFAULT ''standard'' AFTER audio_visual_permission_policy', 'SELECT 1');
PREPARE add_form_av_quality FROM @sql; EXECUTE add_form_av_quality; DEALLOCATE PREPARE add_form_av_quality;

SET @sql = IF((SELECT COUNT(*) FROM information_schema.columns WHERE table_schema='e_talent_evaluaciones_encuestas' AND table_name='evaluation_survey_attempts' AND column_name='audio_visual_upload_failure_policy')=0, 'ALTER TABLE e_talent_evaluaciones_encuestas.evaluation_survey_attempts ADD COLUMN audio_visual_upload_failure_policy ENUM(''continue'',''retry_once'',''block'') NOT NULL DEFAULT ''continue'' AFTER control_mode', 'SELECT 1');
PREPARE add_attempt_av_upload FROM @sql; EXECUTE add_attempt_av_upload; DEALLOCATE PREPARE add_attempt_av_upload;
SET @sql = IF((SELECT COUNT(*) FROM information_schema.columns WHERE table_schema='e_talent_evaluaciones_encuestas' AND table_name='evaluation_survey_attempts' AND column_name='audio_visual_interruption_policy')=0, 'ALTER TABLE e_talent_evaluaciones_encuestas.evaluation_survey_attempts ADD COLUMN audio_visual_interruption_policy ENUM(''continue'',''pause'',''block'') NOT NULL DEFAULT ''pause'' AFTER audio_visual_upload_failure_policy', 'SELECT 1');
PREPARE add_attempt_av_interrupt FROM @sql; EXECUTE add_attempt_av_interrupt; DEALLOCATE PREPARE add_attempt_av_interrupt;
SET @sql = IF((SELECT COUNT(*) FROM information_schema.columns WHERE table_schema='e_talent_evaluaciones_encuestas' AND table_name='evaluation_survey_attempts' AND column_name='audio_visual_voice_policy')=0, 'ALTER TABLE e_talent_evaluaciones_encuestas.evaluation_survey_attempts ADD COLUMN audio_visual_voice_policy ENUM(''log'',''warn'',''pause'') NOT NULL DEFAULT ''warn'' AFTER audio_visual_interruption_policy', 'SELECT 1');
PREPARE add_attempt_av_voice FROM @sql; EXECUTE add_attempt_av_voice; DEALLOCATE PREPARE add_attempt_av_voice;
SET @sql = IF((SELECT COUNT(*) FROM information_schema.columns WHERE table_schema='e_talent_evaluaciones_encuestas' AND table_name='evaluation_survey_attempts' AND column_name='audio_visual_permission_policy')=0, 'ALTER TABLE e_talent_evaluaciones_encuestas.evaluation_survey_attempts ADD COLUMN audio_visual_permission_policy ENUM(''continue'',''pause'',''block'') NOT NULL DEFAULT ''pause'' AFTER audio_visual_voice_policy', 'SELECT 1');
PREPARE add_attempt_av_permission FROM @sql; EXECUTE add_attempt_av_permission; DEALLOCATE PREPARE add_attempt_av_permission;
SET @sql = IF((SELECT COUNT(*) FROM information_schema.columns WHERE table_schema='e_talent_evaluaciones_encuestas' AND table_name='evaluation_survey_attempts' AND column_name='audio_visual_quality_profile')=0, 'ALTER TABLE e_talent_evaluaciones_encuestas.evaluation_survey_attempts ADD COLUMN audio_visual_quality_profile ENUM(''economical'',''standard'',''high'') NOT NULL DEFAULT ''standard'' AFTER audio_visual_permission_policy', 'SELECT 1');
PREPARE add_attempt_av_quality FROM @sql; EXECUTE add_attempt_av_quality; DEALLOCATE PREPARE add_attempt_av_quality;

CREATE TABLE IF NOT EXISTS e_talent_evaluaciones_encuestas.evaluation_survey_activity_events (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    attempt_id INT UNSIGNED NOT NULL,
    form_id INT UNSIGNED NOT NULL,
    user_id INT UNSIGNED NOT NULL,
    event_type VARCHAR(60) NOT NULL,
    question_id INT UNSIGNED NULL,
    metadata JSON NULL,
    ip_address VARCHAR(45) NULL,
    user_agent VARCHAR(255) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_esaee_attempt_created (attempt_id, created_at),
    KEY idx_esaee_form_created (form_id, created_at),
    KEY idx_esaee_user_created (user_id, created_at),
    KEY idx_esaee_event_created (event_type, created_at),
    CONSTRAINT fk_esaee_attempt FOREIGN KEY (attempt_id) REFERENCES e_talent_evaluaciones_encuestas.evaluation_survey_attempts(id) ON DELETE CASCADE,
    CONSTRAINT fk_esaee_form FOREIGN KEY (form_id) REFERENCES e_talent_evaluaciones_encuestas.evaluation_survey_forms(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS e_talent_evaluaciones_encuestas.evaluation_survey_media_evidence (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    attempt_id INT UNSIGNED NOT NULL,
    form_id INT UNSIGNED NOT NULL,
    user_id INT UNSIGNED NOT NULL,
    status ENUM('pending','recording','uploading','saved','partial','failed','not_supported','not_consented') NOT NULL DEFAULT 'pending',
    storage_key VARCHAR(255) NULL,
    mime_type VARCHAR(120) NULL,
    file_size BIGINT UNSIGNED NULL,
    duration_seconds INT UNSIGNED NULL,
    sha256 CHAR(64) NULL,
    consented_at DATETIME NULL,
    recording_started_at DATETIME NULL,
    recording_finished_at DATETIME NULL,
    upload_started_at DATETIME NULL,
    upload_finished_at DATETIME NULL,
    failure_code VARCHAR(80) NULL,
    failure_reason TEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id), UNIQUE KEY uq_esme_attempt (attempt_id),
    KEY idx_esme_status_updated (status, updated_at), KEY idx_esme_user_created (user_id, created_at),
    CONSTRAINT fk_esme_attempt FOREIGN KEY (attempt_id) REFERENCES e_talent_evaluaciones_encuestas.evaluation_survey_attempts(id) ON DELETE CASCADE,
    CONSTRAINT fk_esme_form FOREIGN KEY (form_id) REFERENCES e_talent_evaluaciones_encuestas.evaluation_survey_forms(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS e_talent_evaluaciones_encuestas.evaluation_survey_media_chunks (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    evidence_id BIGINT UNSIGNED NOT NULL,
    chunk_number INT UNSIGNED NOT NULL,
    storage_key VARCHAR(255) NOT NULL,
    size_bytes INT UNSIGNED NOT NULL,
    sha256 CHAR(64) NULL,
    uploaded_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id), UNIQUE KEY uq_esmc_chunk (evidence_id, chunk_number),
    KEY idx_esmc_evidence_chunk (evidence_id, chunk_number),
    CONSTRAINT fk_esmc_evidence FOREIGN KEY (evidence_id) REFERENCES e_talent_evaluaciones_encuestas.evaluation_survey_media_evidence(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS e_talent_evaluaciones_encuestas.evaluation_survey_media_risk_events (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    attempt_id INT UNSIGNED NOT NULL,
    evidence_id BIGINT UNSIGNED NULL,
    event_type VARCHAR(80) NOT NULL,
    severity ENUM('info','attention','risk') NOT NULL DEFAULT 'attention',
    confidence DECIMAL(5,4) NULL,
    metadata TEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id), KEY idx_esmre_attempt_created (attempt_id, created_at), KEY idx_esmre_type_created (event_type, created_at),
    CONSTRAINT fk_esmre_attempt FOREIGN KEY (attempt_id) REFERENCES e_talent_evaluaciones_encuestas.evaluation_survey_attempts(id) ON DELETE CASCADE,
    CONSTRAINT fk_esmre_evidence FOREIGN KEY (evidence_id) REFERENCES e_talent_evaluaciones_encuestas.evaluation_survey_media_evidence(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS e_talent_evaluaciones_encuestas.evaluation_survey_media_access_audit (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    attempt_id INT UNSIGNED NOT NULL,
    evidence_id BIGINT UNSIGNED NULL,
    actor_user_id INT UNSIGNED NOT NULL,
    action ENUM('result_viewed','video_viewed','video_downloaded') NOT NULL,
    ip_address VARCHAR(45) NULL,
    user_agent VARCHAR(255) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id), KEY idx_esmvaa_attempt_created (attempt_id, created_at), KEY idx_esmvaa_actor_created (actor_user_id, created_at),
    CONSTRAINT fk_esmvaa_attempt FOREIGN KEY (attempt_id) REFERENCES e_talent_evaluaciones_encuestas.evaluation_survey_attempts(id) ON DELETE CASCADE,
    CONSTRAINT fk_esmvaa_evidence FOREIGN KEY (evidence_id) REFERENCES e_talent_evaluaciones_encuestas.evaluation_survey_media_evidence(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

UPDATE e_talent_evaluaciones_encuestas.evaluation_survey_forms
SET control_mode = 'off'
WHERE control_mode IS NULL;

UPDATE e_talent_evaluaciones_encuestas.evaluation_survey_attempts a
JOIN e_talent_evaluaciones_encuestas.evaluation_survey_forms f ON f.id = a.form_id
SET a.control_mode = f.control_mode
WHERE a.control_mode IS NULL OR a.control_mode = 'off';
