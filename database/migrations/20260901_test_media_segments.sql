-- Tests psicolaborales: conserva evidencia audiovisual por cada reapertura.
-- No modifica ninguna tabla del módulo evaluaciones_encuestas.
SET @tests_db := COALESCE(DATABASE(), 'e_talent_tests');
SET @statement := IF((SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=@tests_db AND table_name='test_media_evidence' AND column_name='segment_number')=0, CONCAT('ALTER TABLE `', @tests_db, '`.test_media_evidence ADD COLUMN segment_number INT UNSIGNED NOT NULL DEFAULT 1 AFTER session_id'), 'SELECT 1');
PREPARE stmt FROM @statement; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @statement := IF((SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema=@tests_db AND table_name='test_media_evidence' AND index_name='idx_test_media_evidence_session')=0, CONCAT('ALTER TABLE `', @tests_db, '`.test_media_evidence ADD KEY idx_test_media_evidence_session (session_id)'), 'SELECT 1');
PREPARE stmt FROM @statement; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @statement := IF((SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema=@tests_db AND table_name='test_media_evidence' AND index_name='uq_test_media_evidence_session')>0, CONCAT('ALTER TABLE `', @tests_db, '`.test_media_evidence DROP INDEX uq_test_media_evidence_session'), 'SELECT 1');
PREPARE stmt FROM @statement; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @statement := IF((SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema=@tests_db AND table_name='test_media_evidence' AND index_name='uq_test_media_evidence_session_segment')=0, CONCAT('ALTER TABLE `', @tests_db, '`.test_media_evidence ADD UNIQUE KEY uq_test_media_evidence_session_segment (session_id, segment_number)'), 'SELECT 1');
PREPARE stmt FROM @statement; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @statement := IF((SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema=@tests_db AND table_name='test_screen_captures' AND index_name='uq_tsc_session_number')>0, CONCAT('ALTER TABLE `', @tests_db, '`.test_screen_captures DROP INDEX uq_tsc_session_number'), 'SELECT 1');
PREPARE stmt FROM @statement; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @statement := IF((SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema=@tests_db AND table_name='test_screen_captures' AND index_name='uq_tsc_evidence_number')=0, CONCAT('ALTER TABLE `', @tests_db, '`.test_screen_captures ADD UNIQUE KEY uq_tsc_evidence_number (evidence_id, capture_number)'), 'SELECT 1');
PREPARE stmt FROM @statement; EXECUTE stmt; DEALLOCATE PREPARE stmt;
