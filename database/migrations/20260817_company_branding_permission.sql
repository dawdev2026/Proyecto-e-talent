-- Permiso para que los administradores de empresa gestionen solo su identidad visual.
-- No ejecutar desde la aplicacion. Respaldar y validar el esquema antes de aplicar.

USE e_talent_core;

UPDATE role_profiles
SET permissions = JSON_ARRAY_APPEND(CAST(permissions AS JSON), '$', 'manage_company_branding')
WHERE role_key = 'company_admin'
  AND JSON_VALID(permissions)
  AND NOT JSON_CONTAINS(CAST(permissions AS JSON), JSON_QUOTE('manage_company_branding'));
