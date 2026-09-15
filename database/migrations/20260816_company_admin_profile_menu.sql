-- Prepara el perfil Administrador Clientes y el aislamiento de campos por empresa.
-- No ejecutar desde la aplicacion. Respaldar y validar el esquema antes de aplicar.

SET @core_db := DATABASE();

SET @statement := IF(
    (SELECT COUNT(*) FROM information_schema.columns
     WHERE table_schema = @core_db AND table_name = 'user_field_definitions' AND column_name = 'company_id') = 0,
    CONCAT('ALTER TABLE `', @core_db, '`.`user_field_definitions` ADD COLUMN company_id INT UNSIGNED NULL AFTER id'),
    'SELECT 1'
);
PREPARE stmt FROM @statement; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @statement := IF(
    (SELECT COUNT(*) FROM information_schema.statistics
     WHERE table_schema = @core_db AND table_name = 'user_field_definitions' AND index_name = 'idx_user_fields_company_scope') = 0,
    CONCAT('CREATE INDEX idx_user_fields_company_scope ON `', @core_db, '`.`user_field_definitions` (company_id, scope_type, scope_key, is_active)'),
    'SELECT 1'
);
PREPARE stmt FROM @statement; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @statement := IF(
    (SELECT COUNT(*) FROM information_schema.table_constraints
     WHERE constraint_schema = @core_db AND table_name = 'user_field_definitions' AND constraint_name = 'fk_user_fields_company') = 0,
    CONCAT('ALTER TABLE `', @core_db, '`.`user_field_definitions` ADD CONSTRAINT fk_user_fields_company FOREIGN KEY (company_id) REFERENCES `', @core_db, '`.`companies`(id) ON DELETE CASCADE'),
    'SELECT 1'
);
PREPARE stmt FROM @statement; EXECUTE stmt; DEALLOCATE PREPARE stmt;

UPDATE role_profiles
SET name = 'Administrador Clientes',
    description = 'Administra usuarios, campos y procesos exclusivamente dentro de su empresa.',
    permissions = '["manage_company_users","manage_company_user_fields","manage_company_processes","manage_company_interviews","view_company_results","view_test_process_progress","view_test_process_dashboard","view_test_process_results","manage_evaluation_surveys"]',
    home_route = 'test-processes',
    is_active = 1
WHERE role_key = 'company_admin';
