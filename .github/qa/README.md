# QA automatizado

## Contrato operativo obligatorio

Este archivo define el flujo y evita mezclar responsabilidades entre ambientes:

| Área | Método autorizado | No corresponde |
| --- | --- | --- |
| GitHub | Ramas, Pull Requests, promociones y CI | Conectarse directamente al servidor QA |
| Deploy key | Acceso GitHub/Git del repositorio | Usarla como contraseña SSH del servidor |
| QA | VPN local + `scripts/qa-deploy-rsync.sh` | Usar el `.htaccess` raíz de Producción |
| Producción | Procedimiento de Producción aprobado | Reutilizar el método de QA sin validarlo |

Flujo único: `feature → development → qa → produccion`.

Antes de cambiar el método, el destino o el ambiente se debe detener el proceso y solicitar confirmación explícita. No se deben ejecutar procesos alternativos por conveniencia ni fusionar una promoción con validaciones fallidas sin autorización registrada.

## Secretos del entorno `qa-validation`

Configurar en GitHub → Settings → Environments → `qa-validation`:

- `QA_TEST_EMAIL`: usuario de pruebas no productivo.
- `QA_TEST_PASSWORD`: contraseña de ese usuario.

La contraseña del servidor QA y las credenciales MySQL no se usan en este workflow. El despliegue se ejecuta desde la máquina local autorizada, con la VPN activa, mediante `scripts/qa-deploy-rsync.sh`.

El ejecutor conserva la propiedad `dawchile:dawchile` en toda la carpeta remota del proyecto. Además, nunca sobrescribe ni elimina archivos `.htaccess` o `.ini` existentes dentro de `public`; esos archivos pertenecen a la configuración local del servidor QA.

## Operación después de actualizar QA

1. El watcher local detecta cambios en `origin/qa`.
2. Despliega el commit exacto al servidor QA mediante la VPN.
3. Dispara `QA functional and accessibility validation` desde Actions.
4. Una validación correcta crea y fusiona automáticamente el PR `qa → produccion`.

La suite automática valida el código y el acceso funcional; no cambia el método de despliegue de QA. El `.htaccess` raíz que oculta `/sites/metricatalent/public` se valida y modifica únicamente en Producción.

La suite inicia en `/login`, autentica si existen los secretos y rastrea las rutas GET internas accesibles desde la sesión, excluyendo rutas destructivas. No sustituye pruebas manuales de operaciones POST o flujos que requieren datos específicos.

## Procedimiento de traspaso registrado

Este es el procedimiento operativo único comprobado para este proyecto. No se deben
improvisar conexiones directas, reutilizar llaves de GitHub como llaves de servidor,
ni ejecutar migraciones desde los scripts de copia de aplicación.

### QA: código

1. Confirmar el commit exacto de `origin/qa` y ejecutar el preflight:
   `scripts/qa-deploy-rsync.sh origin/qa 1`.
2. Desplegar ese mismo ref mediante:
   `scripts/qa-deploy-rsync.sh origin/qa`.
3. El script obtiene SSH, ruta y URL desde `load_config("qa")`; autentica por
   contraseña mediante `expect`, copia con `rsync` y conserva `dawchile:dawchile`
   y modo 755.
4. La copia excluye `database/`, `config/*.secure`, `config/database.php`,
   `public/uploads/`, `.htaccess`, archivos `.ini`, `storage/` y temporales.
5. El script valida el endpoint QA `/login` y no ejecuta SQL.

### QA: base de datos

1. Conectarse por SSH al servidor QA usando las credenciales de `config/qa.secure`.
2. La conexión MySQL se realiza dentro del servidor por `127.0.0.1:3306`, sobre
   la base `dawchile_e_talent_tests`.
3. Transferir temporalmente el SQL a `/tmp/`, ejecutar primero la migración que
   crea `test_screen_captures` y después la migración de segmentos.
4. Si la cuenta de aplicación no posee `CREATE`, ejecutar únicamente la creación
   estructural con la cuenta administrativa MySQL autorizada; la aplicación sigue
   usando su cuenta normal.
5. Validar `test_media_evidence.segment_number`,
   `uq_test_media_evidence_session_segment` y `uq_tsc_evidence_number`.
6. Eliminar los SQL temporales. No modificar `dawchile_e_talent_evaluaciones_encuestas`.

### Producción: código

1. Fusionar el PR `qa → produccion` con el commit exacto de QA.
2. Ejecutar el dry-run:
   `scripts/prod-deploy-rsync.sh origin/produccion 1`.
3. Si el preflight es correcto, ejecutar:
   `scripts/prod-deploy-rsync.sh origin/produccion`.
4. El script obtiene SSH, ruta, propietario y URL desde
   `load_config("production")`; usa `rsync` y valida `/login`.
5. La copia excluye `database/`, `config/*.secure`, configuraciones de BD,
   uploads, `.htaccess`, `.ini`, `storage/` y temporales. Por diseño, este paso
   no aplica migraciones.

### Producción: base de datos

1. El servidor web de Producción se encuentra separado del servidor MySQL. La
   ruta comprobada es: SSH al servidor web configurado y, desde allí, SSH al
   servidor MySQL autorizado.
2. En el servidor MySQL, ejecutar primero la creación de
   `test_screen_captures`, luego `test_media_segments` y finalmente
   `test_media_upload_counters`, siempre sobre `e_talent_tests`.
3. Usar la cuenta de aplicación solo si tiene los permisos requeridos; para
   crear estructura, usar la cuenta administrativa MySQL autorizada.
4. Validar tablas, columnas, índices y el endpoint de Producción después del
   cambio. Confirmar que `e_talent_evaluaciones_encuestas` no fue modificada.
5. Eliminar todos los archivos SQL temporales del servidor web y del servidor BD.

### Registro mínimo de cada ejecución

Cada traspaso debe registrar: ambiente, ref/commit, PR fusionado, script usado,
destino, resultado del endpoint, migraciones ejecutadas, usuario administrativo
solo por su rol (nunca su contraseña), validación de esquema y limpieza de
temporales. Las contraseñas, llaves privadas y archivos `*.secure` nunca se
publican ni se agregan al repositorio.

## Restricción de ejecución de comandos

Ningún desarrollo de la plataforma puede utilizar `shell_exec()` ni `exec()`. Esta regla aplica también a servicios, controladores, scripts y pruebas. Cualquier procesamiento que dependa de comandos del sistema debe reemplazarse por una solución compatible con PHP y disponible en QA y Producción antes de promover cambios.
