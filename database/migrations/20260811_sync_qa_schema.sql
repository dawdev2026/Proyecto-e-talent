-- Sincronizacion de estructura entre desarrollo local y QA.
--
-- Esta migracion es solo estructural: no crea usuarios ni carga datos demo.
-- Detecta automaticamente el prefijo del ambiente:
--   desarrollo: e_talent_core / e_talent_interviews / e_talent_tests
--   QA/produccion con prefijo: dawchile_e_talent_core / ...
--
-- No ejecutar directamente desde la aplicacion. Antes de aplicarla:
-- 1) respaldar las tres bases del ambiente destino;
-- 2) revisar registros huerfanos para las FK nuevas;
-- 3) ejecutar en una ventana controlada y validar el esquema posterior.

SET @current_db := DATABASE();
SET @qa_prefix := IF(
    @current_db LIKE 'dawchile_e_talent_%'
    OR (@current_db IS NULL AND EXISTS (SELECT 1 FROM information_schema.schemata WHERE schema_name = 'dawchile_e_talent_core')),
    'dawchile_',
    ''
);
SET @core_db := CONCAT(@qa_prefix, 'e_talent_core');
SET @interviews_db := CONCAT(@qa_prefix, 'e_talent_interviews');
SET @tests_db := CONCAT(@qa_prefix, 'e_talent_tests');

-- Core: rol company_admin y FK de users.company_id.
SET @statement := IF(
    (SELECT COUNT(*)
     FROM information_schema.columns
     WHERE table_schema = @core_db
       AND table_name = 'users'
       AND column_name = 'role'
       AND column_type LIKE '%company_admin%') = 0,
    CONCAT('ALTER TABLE `', @core_db, '`.`users` MODIFY COLUMN role ENUM(''admin'', ''agente'', ''usuario'', ''company_admin'') NOT NULL DEFAULT ''usuario'''),
    'SELECT 1'
);
PREPARE stmt FROM @statement; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @statement := CONCAT(
    'CREATE TABLE IF NOT EXISTS `', @core_db, '`.`password_reset_tokens` (',
    'id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, ',
    'user_id INT UNSIGNED NOT NULL, ',
    'token_hash CHAR(64) NOT NULL, ',
    'expires_at DATETIME NOT NULL, ',
    'used_at DATETIME NULL, ',
    'requested_ip VARCHAR(45) NULL, ',
    'created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, ',
    'PRIMARY KEY (id), ',
    'UNIQUE KEY uq_password_reset_token_hash (token_hash), ',
    'INDEX idx_password_reset_user_expires (user_id, expires_at), ',
    'CONSTRAINT fk_password_reset_user FOREIGN KEY (user_id) REFERENCES `', @core_db, '`.`users`(id) ON DELETE CASCADE',
    ') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
);
PREPARE stmt FROM @statement; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @statement := IF(
    (SELECT COUNT(*) FROM information_schema.table_constraints
     WHERE constraint_schema = @core_db AND table_name = 'users'
       AND constraint_name = 'fk_users_company_id') = 0,
    CONCAT('ALTER TABLE `', @core_db, '`.`users` ADD CONSTRAINT fk_users_company_id FOREIGN KEY (company_id) REFERENCES `', @core_db, '`.`companies`(id) ON DELETE SET NULL'),
    'SELECT 1'
);
PREPARE stmt FROM @statement; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @statement := CONCAT(
    'INSERT INTO `', @core_db, '`.`role_profiles` ',
    '(name, role_key, scope_type, scope_key, description, permissions, home_route, is_system, is_default_requester, is_active) VALUES ',
    '(''Administrador de empresa'', ''company_admin'', ''platform'', ''tests'', ',
    '''Administra usuarios, procesos y entrevistas exclusivamente dentro de su empresa.'', ',
    '''["manage_company_users","manage_company_processes","manage_company_interviews","view_company_results","view_test_process_progress","view_test_process_dashboard","view_test_process_results"]'', ',
    '''test-processes'', 1, 0, 1) ',
    'ON DUPLICATE KEY UPDATE description = VALUES(description), permissions = VALUES(permissions), ',
    'home_route = VALUES(home_route), is_active = 1'
);
PREPARE stmt FROM @statement; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Multiempresa: columnas, indices, datos heredables y FK hacia core.companies.
SET @statement := IF(
    (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @tests_db AND table_name = 'test_processes' AND column_name = 'company_id') = 0,
    CONCAT('ALTER TABLE `', @tests_db, '`.`test_processes` ADD COLUMN company_id INT UNSIGNED NULL AFTER id'),
    'SELECT 1'
);
PREPARE stmt FROM @statement; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @statement := IF(
    (SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema = @tests_db AND table_name = 'test_processes' AND index_name = 'idx_test_processes_company_status') = 0,
    CONCAT('CREATE INDEX idx_test_processes_company_status ON `', @tests_db, '`.`test_processes` (company_id, status)'),
    'SELECT 1'
);
PREPARE stmt FROM @statement; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @statement := CONCAT('UPDATE `', @tests_db, '`.`test_processes` p JOIN `', @core_db, '`.`users` u ON u.id = p.created_by SET p.company_id = u.company_id WHERE p.company_id IS NULL AND u.company_id IS NOT NULL');
PREPARE stmt FROM @statement; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @statement := IF(
    (SELECT COUNT(*) FROM information_schema.table_constraints WHERE constraint_schema = @tests_db AND table_name = 'test_processes' AND constraint_name = 'fk_test_processes_company') = 0,
    CONCAT('ALTER TABLE `', @tests_db, '`.`test_processes` ADD CONSTRAINT fk_test_processes_company FOREIGN KEY (company_id) REFERENCES `', @core_db, '`.`companies`(id) ON DELETE SET NULL'),
    'SELECT 1'
);
PREPARE stmt FROM @statement; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @statement := IF(
    (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @interviews_db AND table_name = 'interview_processes' AND column_name = 'company_id') = 0,
    CONCAT('ALTER TABLE `', @interviews_db, '`.`interview_processes` ADD COLUMN company_id INT UNSIGNED NULL AFTER id'),
    'SELECT 1'
);
PREPARE stmt FROM @statement; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @statement := IF(
    (SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema = @interviews_db AND table_name = 'interview_processes' AND index_name = 'idx_interview_processes_company_date') = 0,
    CONCAT('CREATE INDEX idx_interview_processes_company_date ON `', @interviews_db, '`.`interview_processes` (company_id, interview_date)'),
    'SELECT 1'
);
PREPARE stmt FROM @statement; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @statement := CONCAT('UPDATE `', @interviews_db, '`.`interview_processes` p JOIN `', @core_db, '`.`users` u ON u.id = p.created_by SET p.company_id = u.company_id WHERE p.company_id IS NULL AND u.company_id IS NOT NULL');
PREPARE stmt FROM @statement; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @statement := IF(
    (SELECT COUNT(*) FROM information_schema.table_constraints WHERE constraint_schema = @interviews_db AND table_name = 'interview_processes' AND constraint_name = 'fk_interview_processes_company') = 0,
    CONCAT('ALTER TABLE `', @interviews_db, '`.`interview_processes` ADD CONSTRAINT fk_interview_processes_company FOREIGN KEY (company_id) REFERENCES `', @core_db, '`.`companies`(id) ON DELETE SET NULL'),
    'SELECT 1'
);
PREPARE stmt FROM @statement; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Evaluaciones: columnas de control audiovisual y politicas de sesion.
SET @statement := IF((SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @tests_db AND table_name = 'test_instruments' AND column_name = 'control_mode') = 0, CONCAT('ALTER TABLE `', @tests_db, '`.`test_instruments` ADD COLUMN control_mode ENUM(''off'', ''activity'', ''supervised'', ''supervised_audio_visual'') NOT NULL DEFAULT ''off'' AFTER supervised_mode_enabled'), 'SELECT 1');
PREPARE stmt FROM @statement; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @statement := IF((SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @tests_db AND table_name = 'test_instruments' AND column_name = 'audio_visual_upload_failure_policy') = 0, CONCAT('ALTER TABLE `', @tests_db, '`.`test_instruments` ADD COLUMN audio_visual_upload_failure_policy ENUM(''continue'', ''retry_once'', ''block'') NOT NULL DEFAULT ''continue'' AFTER control_mode'), 'SELECT 1');
PREPARE stmt FROM @statement; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @statement := IF((SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @tests_db AND table_name = 'test_instruments' AND column_name = 'audio_visual_interruption_policy') = 0, CONCAT('ALTER TABLE `', @tests_db, '`.`test_instruments` ADD COLUMN audio_visual_interruption_policy ENUM(''continue'', ''pause'', ''block'') NOT NULL DEFAULT ''pause'' AFTER audio_visual_upload_failure_policy'), 'SELECT 1');
PREPARE stmt FROM @statement; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @statement := IF((SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @tests_db AND table_name = 'test_instruments' AND column_name = 'audio_visual_voice_policy') = 0, CONCAT('ALTER TABLE `', @tests_db, '`.`test_instruments` ADD COLUMN audio_visual_voice_policy ENUM(''log'', ''warn'', ''pause'') NOT NULL DEFAULT ''warn'' AFTER audio_visual_interruption_policy'), 'SELECT 1');
PREPARE stmt FROM @statement; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @statement := IF((SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @tests_db AND table_name = 'test_instruments' AND column_name = 'audio_visual_permission_policy') = 0, CONCAT('ALTER TABLE `', @tests_db, '`.`test_instruments` ADD COLUMN audio_visual_permission_policy ENUM(''continue'', ''pause'', ''block'') NOT NULL DEFAULT ''pause'' AFTER audio_visual_voice_policy'), 'SELECT 1');
PREPARE stmt FROM @statement; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @statement := IF((SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @tests_db AND table_name = 'test_instruments' AND column_name = 'audio_visual_quality_profile') = 0, CONCAT('ALTER TABLE `', @tests_db, '`.`test_instruments` ADD COLUMN audio_visual_quality_profile ENUM(''economical'', ''standard'', ''high'') NOT NULL DEFAULT ''standard'' AFTER audio_visual_permission_policy'), 'SELECT 1');
PREPARE stmt FROM @statement; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @statement := CONCAT('UPDATE `', @tests_db, '`.`test_instruments` SET control_mode = CASE WHEN supervised_mode_enabled = 1 THEN ''supervised'' WHEN track_activity_enabled = 1 THEN ''activity'' ELSE ''off'' END');
PREPARE stmt FROM @statement; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @statement := IF((SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @tests_db AND table_name = 'test_sessions' AND column_name = 'control_mode') = 0, CONCAT('ALTER TABLE `', @tests_db, '`.`test_sessions` ADD COLUMN control_mode ENUM(''off'', ''activity'', ''supervised'', ''supervised_audio_visual'') NULL AFTER instrument_id'), 'SELECT 1');
PREPARE stmt FROM @statement; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @statement := IF((SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @tests_db AND table_name = 'test_sessions' AND column_name = 'audio_visual_policy') = 0, CONCAT('ALTER TABLE `', @tests_db, '`.`test_sessions` ADD COLUMN audio_visual_policy ENUM(''pause'', ''continue'', ''block'') NULL AFTER control_mode'), 'SELECT 1');
PREPARE stmt FROM @statement; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @statement := IF((SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @tests_db AND table_name = 'test_sessions' AND column_name = 'audio_visual_upload_failure_policy') = 0, CONCAT('ALTER TABLE `', @tests_db, '`.`test_sessions` ADD COLUMN audio_visual_upload_failure_policy ENUM(''continue'', ''retry_once'', ''block'') NULL AFTER audio_visual_policy'), 'SELECT 1');
PREPARE stmt FROM @statement; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @statement := IF((SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @tests_db AND table_name = 'test_sessions' AND column_name = 'audio_visual_interruption_policy') = 0, CONCAT('ALTER TABLE `', @tests_db, '`.`test_sessions` ADD COLUMN audio_visual_interruption_policy ENUM(''continue'', ''pause'', ''block'') NULL AFTER audio_visual_upload_failure_policy'), 'SELECT 1');
PREPARE stmt FROM @statement; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @statement := IF((SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @tests_db AND table_name = 'test_sessions' AND column_name = 'audio_visual_voice_policy') = 0, CONCAT('ALTER TABLE `', @tests_db, '`.`test_sessions` ADD COLUMN audio_visual_voice_policy ENUM(''log'', ''warn'', ''pause'') NULL AFTER audio_visual_interruption_policy'), 'SELECT 1');
PREPARE stmt FROM @statement; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @statement := IF((SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @tests_db AND table_name = 'test_sessions' AND column_name = 'audio_visual_permission_policy') = 0, CONCAT('ALTER TABLE `', @tests_db, '`.`test_sessions` ADD COLUMN audio_visual_permission_policy ENUM(''continue'', ''pause'', ''block'') NULL AFTER audio_visual_voice_policy'), 'SELECT 1');
PREPARE stmt FROM @statement; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @statement := IF((SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @tests_db AND table_name = 'test_sessions' AND column_name = 'audio_visual_quality_profile') = 0, CONCAT('ALTER TABLE `', @tests_db, '`.`test_sessions` ADD COLUMN audio_visual_quality_profile ENUM(''economical'', ''standard'', ''high'') NULL AFTER audio_visual_permission_policy'), 'SELECT 1');
PREPARE stmt FROM @statement; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @statement := CONCAT('UPDATE `', @tests_db, '`.`test_sessions` s JOIN `', @tests_db, '`.`test_instruments` i ON i.id = s.instrument_id SET s.control_mode = i.control_mode WHERE s.control_mode IS NULL');
PREPARE stmt FROM @statement; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Evidencia audiovisual y auditoria: las FK usan siempre el nombre detectado del ambiente.
SET @statement := CONCAT(
    'CREATE TABLE IF NOT EXISTS `', @tests_db, '`.`test_media_evidence` (',
    'id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, session_id INT UNSIGNED NOT NULL, instrument_id INT UNSIGNED NOT NULL, user_id INT UNSIGNED NOT NULL, ',
    'status ENUM(''pending'', ''recording'', ''uploading'', ''saved'', ''partial'', ''failed'', ''not_supported'', ''not_consented'') NOT NULL DEFAULT ''pending'', ',
    'storage_key VARCHAR(255) NULL, mime_type VARCHAR(120) NULL, file_size BIGINT UNSIGNED NULL, duration_seconds INT UNSIGNED NULL, sha256 CHAR(64) NULL, ',
    'recording_started_at DATETIME NULL, recording_finished_at DATETIME NULL, upload_started_at DATETIME NULL, upload_finished_at DATETIME NULL, ',
    'failure_code VARCHAR(80) NULL, failure_reason TEXT NULL, created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, ',
    'PRIMARY KEY (id), UNIQUE KEY uq_test_media_evidence_session (session_id), INDEX idx_test_media_evidence_status (status, updated_at), ',
    'CONSTRAINT fk_test_media_evidence_session FOREIGN KEY (session_id) REFERENCES `', @tests_db, '`.`test_sessions`(id) ON DELETE CASCADE, ',
    'CONSTRAINT fk_test_media_evidence_instrument FOREIGN KEY (instrument_id) REFERENCES `', @tests_db, '`.`test_instruments`(id) ON DELETE CASCADE',
    ') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
);
PREPARE stmt FROM @statement; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @statement := CONCAT(
    'CREATE TABLE IF NOT EXISTS `', @tests_db, '`.`test_media_chunks` (',
    'id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, evidence_id BIGINT UNSIGNED NOT NULL, chunk_number INT UNSIGNED NOT NULL, storage_key VARCHAR(255) NOT NULL, size_bytes INT UNSIGNED NOT NULL, sha256 CHAR(64) NULL, uploaded_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, ',
    'PRIMARY KEY (id), UNIQUE KEY uq_test_media_chunk (evidence_id, chunk_number), INDEX idx_test_media_chunks_evidence (evidence_id, chunk_number), ',
    'CONSTRAINT fk_test_media_chunks_evidence FOREIGN KEY (evidence_id) REFERENCES `', @tests_db, '`.`test_media_evidence`(id) ON DELETE CASCADE',
    ') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
);
PREPARE stmt FROM @statement; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @statement := CONCAT(
    'CREATE TABLE IF NOT EXISTS `', @tests_db, '`.`test_audio_visual_risk_events` (',
    'id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, session_id INT UNSIGNED NOT NULL, evidence_id BIGINT UNSIGNED NULL, event_type VARCHAR(80) NOT NULL, ',
    'severity ENUM(''info'', ''attention'', ''risk'') NOT NULL DEFAULT ''attention'', confidence DECIMAL(5,4) NULL, started_at DATETIME NULL, ended_at DATETIME NULL, metadata TEXT NULL, created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, ',
    'PRIMARY KEY (id), INDEX idx_test_av_risk_session (session_id, created_at), INDEX idx_test_av_risk_type (event_type, created_at), ',
    'CONSTRAINT fk_test_av_risk_session FOREIGN KEY (session_id) REFERENCES `', @tests_db, '`.`test_sessions`(id) ON DELETE CASCADE, ',
    'CONSTRAINT fk_test_av_risk_evidence FOREIGN KEY (evidence_id) REFERENCES `', @tests_db, '`.`test_media_evidence`(id) ON DELETE SET NULL',
    ') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
);
PREPARE stmt FROM @statement; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @statement := CONCAT(
    'CREATE TABLE IF NOT EXISTS `', @tests_db, '`.`test_media_access_audit` (',
    'id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, session_id INT UNSIGNED NOT NULL, evidence_id BIGINT UNSIGNED NULL, actor_user_id INT UNSIGNED NOT NULL, ',
    'action ENUM(''result_viewed'', ''video_viewed'', ''video_downloaded'') NOT NULL, ip_address VARCHAR(45) NULL, user_agent VARCHAR(255) NULL, created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, ',
    'PRIMARY KEY (id), INDEX idx_media_access_session_created (session_id, created_at), INDEX idx_media_access_actor_created (actor_user_id, created_at), ',
    'CONSTRAINT fk_media_access_session FOREIGN KEY (session_id) REFERENCES `', @tests_db, '`.`test_sessions`(id) ON DELETE CASCADE, ',
    'CONSTRAINT fk_media_access_evidence FOREIGN KEY (evidence_id) REFERENCES `', @tests_db, '`.`test_media_evidence`(id) ON DELETE SET NULL',
    ') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
);
PREPARE stmt FROM @statement; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Verificacion final informativa (no modifica datos).
SELECT @core_db AS core_database, @interviews_db AS interviews_database, @tests_db AS tests_database;
