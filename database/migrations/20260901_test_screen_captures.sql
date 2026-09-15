-- Tests psicolaborales: crea capturas en la base seleccionada por la conexión.
SET @tests_db := COALESCE(DATABASE(), 'e_talent_tests');
SET @statement := CONCAT(
    'CREATE TABLE IF NOT EXISTS `', @tests_db, '`.test_screen_captures (',
    'id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, ',
    'session_id INT UNSIGNED NOT NULL, ',
    'evidence_id BIGINT UNSIGNED NULL, ',
    'capture_source ENUM(''screen'',''canvas'') NOT NULL, ',
    'capture_number INT UNSIGNED NOT NULL, ',
    'item_id INT UNSIGNED NULL, ',
    'block_number INT UNSIGNED NULL, ',
    'event_type VARCHAR(80) NOT NULL DEFAULT ''periodic'', ',
    'mime_type VARCHAR(120) NOT NULL, ',
    'storage_key VARCHAR(255) NOT NULL, ',
    'file_size INT UNSIGNED NOT NULL, ',
    'sha256 CHAR(64) NOT NULL, ',
    'captured_at DATETIME NOT NULL, ',
    'created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, ',
    'PRIMARY KEY (id), ',
    'UNIQUE KEY uq_tsc_session_number (session_id, capture_number), ',
    'KEY idx_tsc_session_created (session_id, created_at), ',
    'KEY idx_tsc_evidence_created (evidence_id, created_at), ',
    'CONSTRAINT fk_tsc_session FOREIGN KEY (session_id) REFERENCES `', @tests_db, '`.test_sessions(id) ON DELETE CASCADE, ',
    'CONSTRAINT fk_tsc_evidence FOREIGN KEY (evidence_id) REFERENCES `', @tests_db, '`.test_media_evidence(id) ON DELETE SET NULL',
    ') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
);
PREPARE stmt FROM @statement; EXECUTE stmt; DEALLOCATE PREPARE stmt;
