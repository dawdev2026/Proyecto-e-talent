-- Activa la pantalla pública de verificación por empresa.
SET @sql := IF(
    (SELECT COUNT(*) FROM information_schema.columns
     WHERE table_schema = 'e_talent_core' AND table_name = 'companies'
       AND column_name = 'verification_enabled') = 0,
    'ALTER TABLE e_talent_core.companies ADD COLUMN verification_enabled TINYINT(1) NOT NULL DEFAULT 0 AFTER url_prefix',
    'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF(
    (SELECT COUNT(*) FROM information_schema.statistics
     WHERE table_schema = 'e_talent_core' AND table_name = 'users'
       AND index_name = 'idx_users_company_email_active') = 0,
    'CREATE INDEX idx_users_company_email_active ON e_talent_core.users (company_id, email, is_active)',
    'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
