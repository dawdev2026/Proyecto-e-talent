-- Permite registrar usuarios cuyo sexo no fue informado.
-- Aplicar por ambiente después de respaldar la base de datos correspondiente.

ALTER TABLE e_talent_core.users
    MODIFY COLUMN sex ENUM('masculino', 'femenino', 'no_informado') NULL;
