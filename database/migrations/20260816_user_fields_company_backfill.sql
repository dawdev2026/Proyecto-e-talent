-- Convierte los campos de usuario globales en definiciones por empresa y conserva sus valores.
-- No ejecutar desde la aplicacion. Respaldar e_talent_core antes de aplicar.

START TRANSACTION;

ALTER TABLE user_field_definitions
    DROP INDEX uq_user_field_definitions_scope_key;

SET @base_company_id := (SELECT MIN(id) FROM companies WHERE is_active = 1);

UPDATE user_field_definitions
SET company_id = @base_company_id
WHERE company_id IS NULL;

INSERT IGNORE INTO user_field_definitions
    (company_id, scope_type, scope_key, field_key, label, field_type, validation_rule, validation_pattern, validation_message, options, help_text, is_required, show_in_list, sort_order, is_active)
SELECT c.id, f.scope_type, f.scope_key, f.field_key, f.label, f.field_type, f.validation_rule, f.validation_pattern, f.validation_message, f.options, f.help_text, f.is_required, f.show_in_list, f.sort_order, f.is_active
FROM companies c
JOIN user_field_definitions f ON f.company_id = @base_company_id
WHERE c.is_active = 1
  AND c.id <> @base_company_id;

UPDATE user_field_values v
JOIN users u ON u.id = v.user_id
JOIN user_field_definitions old_field ON old_field.id = v.field_id
JOIN user_field_definitions company_field
  ON company_field.company_id = u.company_id
 AND company_field.scope_type = old_field.scope_type
 AND company_field.scope_key = old_field.scope_key
 AND company_field.field_key = old_field.field_key
SET v.field_id = company_field.id
WHERE old_field.company_id = @base_company_id
  AND u.company_id IS NOT NULL;

ALTER TABLE user_field_definitions
    ADD UNIQUE KEY uq_user_field_definitions_company_scope (company_id, scope_type, scope_key, field_key),
    MODIFY company_id INT UNSIGNED NOT NULL;

COMMIT;
