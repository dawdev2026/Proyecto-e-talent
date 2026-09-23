# Migraciones de base de datos

Las migraciones SQL están en `database/migrations/` y se controlan en la base
`e_talent_core`, tabla `schema_migrations`. El registro guarda nombre, SHA-256,
estado, método (DDL ejecutado o esquema preexistente verificado), inicio y fecha
de aplicación. `status` revalida en solo lectura las tablas, columnas, índices,
claves primarias y foráneas de cada migración. Un checksum diferente detiene la
ejecución para evitar cambiar silenciosamente una migración ya registrada.

En el Docker local de Desarrollo:

```bash
docker exec e-talent-app php /var/www/html/scripts/db-migrate.php status
docker exec e-talent-app php /var/www/html/scripts/db-migrate.php migrate
```

`migrate` solo admite `APP_ENV=local` y los nombres de bases locales declarados
por Compose. Usa un bloqueo MySQL con nombre para evitar ejecuciones
concurrentes. Las columnas `ADD COLUMN` existentes se verifican en tipo,
nulabilidad, default y collation; `ADD INDEX` comprueba sus columnas y unicidad.
Un desajuste falla con diagnóstico. Las sentencias `CREATE TABLE IF NOT EXISTS`
verifican columnas, tipos, índices, clave primaria y claves foráneas. Como MySQL
5.7 hace commit implícito de DDL, una interrupción puede dejar el registro
`failed`; el runner vuelve a inspeccionar el esquema y continúa solo con
operaciones compatibles. Cualquier tipo de sentencia no reconocido exige
revisión explícita antes de ampliar el runner.

Para bases preexistentes, el runner compara las operaciones de cada archivo con
el esquema: los cambios ya presentes se registran como `verified_existing`; los
que falten se ejecutan y quedan como `executed`. Los archivos aplicados no se
editan: los cambios posteriores deben agregarse como una nueva migración.

Este runner es exclusivamente para Desarrollo local. QA y Producción requieren
su método autorizado separado; no ejecutar este comando en esos ambientes.
