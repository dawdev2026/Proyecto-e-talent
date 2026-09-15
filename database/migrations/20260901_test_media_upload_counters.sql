-- Tests psicolaborales: contador atómico de bytes recibidos por evidencia.
-- No modifica ninguna tabla del módulo evaluaciones_encuestas.
SET @tests_db := 'e_talent_tests';

SET @statement := IF(
    (SELECT COUNT(*)
       FROM information_schema.columns
      WHERE table_schema = @tests_db
        AND table_name = 'test_media_evidence'
        AND column_name = 'uploaded_bytes') = 0,
    CONCAT('ALTER TABLE `', @tests_db, '`.test_media_evidence ADD COLUMN uploaded_bytes BIGINT UNSIGNED NOT NULL DEFAULT 0 AFTER file_size'),
    'SELECT 1'
);
PREPARE stmt FROM @statement;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

UPDATE e_talent_tests.test_media_evidence e
LEFT JOIN (
    SELECT evidence_id, COALESCE(SUM(size_bytes), 0) AS uploaded_bytes
      FROM e_talent_tests.test_media_chunks
     GROUP BY evidence_id
) c ON c.evidence_id = e.id
   SET e.uploaded_bytes = COALESCE(c.uploaded_bytes, 0);
