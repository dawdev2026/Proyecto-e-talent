-- La unicidad de identidad de usuarios pertenece a cada empresa.
-- Desarrollo: aplicar después de verificar el respaldo de la base.

ALTER TABLE e_talent_core.users
    DROP INDEX uq_users_rut,
    DROP INDEX email,
    ADD UNIQUE KEY uq_users_company_rut (company_id, rut),
    ADD UNIQUE KEY uq_users_company_email (company_id, email);
