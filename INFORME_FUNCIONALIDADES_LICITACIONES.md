# Informe de funcionalidades de la plataforma e-talent

## 1. Propósito del informe

Este documento presenta el alcance funcional y técnico evidenciado en el código local de e-talent, redactado para apoyar propuestas comerciales y respuestas a licitaciones. La plataforma se posiciona como un ecosistema de evaluación, selección y generación de evidencia para decisiones de personas, con operación multiempresa, trazabilidad y capacidades de integración.

La clasificación utilizada es:

- **Implementado/evidenciado:** existe código funcional, controlador, vista, servicio o modelo que respalda la capacidad.
- **Configurable:** existe soporte en la plataforma, pero requiere parámetros, credenciales, contenidos o habilitación por ambiente.
- **Sujeto a validación:** requiere prueba de aceptación, capacidad, proveedor externo o definición contractual antes de ofrecerlo como compromiso cerrado.

## 2. Resumen ejecutivo comercial

e-talent permite centralizar y estandarizar procesos de evaluación y selección, desde la administración de empresas y postulantes hasta la aplicación de instrumentos, entrevistas, evidencias y reportes ejecutivos.

Su propuesta de valor para organismos públicos y empresas es:

1. **Evaluación integral:** combina antecedentes del postulante, instrumentos psicométricos y aptitudinales, encuestas/evaluaciones configurables, entrevista estructurada y evidencias audiovisuales.
2. **Decisiones basadas en evidencia:** conserva respuestas, puntajes, eventos de actividad, incidencias, notas, evaluaciones del entrevistador, documentos y fuentes de información.
3. **Operación multiempresa:** separa clientes, usuarios, roles, permisos, branding, configuraciones y asignaciones de informes.
4. **Estandarización y trazabilidad:** utiliza procesos, perfiles, criterios, reglas de puntuación, rankings, estados, auditoría y versiones de informes.
5. **Automatización:** ofrece importaciones masivas, integración con Moodle, generación de PDF/Excel/ZIP, procesamiento en segundo plano y apoyo de IA configurable.
6. **Experiencia digital controlada:** incluye progreso, recuperación de sesión, validaciones de componentes, controles de integridad, verificación facial configurable y registro de eventos.
7. **Adaptabilidad institucional:** admite campos personalizados, plantillas de informes, presets visuales, branding por empresa y reglas configurables sin alterar el núcleo de la plataforma.

## 3. Catálogo funcional

### 3.1 Administración central y operación multiempresa

**Capacidades evidenciadas**

- Administración de empresas/clientes.
- Alta, edición, consulta e importación de usuarios.
- Gestión de perfiles/roles y scopes de permisos.
- Campos personalizados para enriquecer el registro de personas.
- Asociación de usuarios a empresas, procesos, perfiles y actividades.
- Administración de configuraciones generales, operacionales, de acceso, correo y diseño.
- Branding institucional: logotipo, favicon, fondo de login, colores, tipografías y presets visuales.
- Limpieza controlada de usuarios y datos asociados a una empresa.
- Panel de administración para seguimiento de progreso, incidentes, verificaciones y revisiones de componentes.

**Valor para licitación:** permite operar múltiples clientes o unidades organizacionales con gobierno central, personalización institucional y segregación de acceso.

### 3.2 Seguridad, acceso y protección de información

**Capacidades evidenciadas**

- Autenticación por correo u otros identificadores configurables.
- Control de sesiones seguras y política HTTPS.
- Recuperación y cambio de contraseña mediante tokens.
- Segundo factor por código enviado por correo, configurable por plataforma/usuario.
- Limitación de intentos de inicio de sesión.
- Roles y permisos por funcionalidad, empresa y subplataforma.
- Tokens seguros para referencias de URL e identificadores sensibles.
- Encabezados de seguridad y configuración de sesión.
- Cifrado de payloads y configuración sensible mediante clave maestra fuera de la aplicación.
- Auditoría de accesos a evidencias audiovisuales y capturas.
- Separación de bases de datos por dominio funcional: core, tests, entrevistas y evaluaciones/encuestas.

**Posicionamiento recomendado:** “seguridad por diseño, control de acceso por mínimo privilegio y trazabilidad de operaciones”. Las políticas de retención, clasificación de datos personales, continuidad y cumplimiento normativo deben quedar definidas en las bases y en el diseño de cada proyecto.

### 3.3 Procesos de evaluación y selección

La plataforma utiliza procesos como unidad de trabajo para organizar postulantes, instrumentos, perfiles, administradores, campos y actividades.

**Capacidades evidenciadas**

- Creación y configuración de procesos.
- Asignación de personas, instrumentos y evaluaciones.
- Asociación de perfiles asignables, administradores y campos personalizados.
- Configuración de prerrequisitos y políticas de actividad.
- Estados de proceso, actividad y sesión.
- Seguimiento de avance individual y colectivo.
- Revisión de asignaciones y cumplimiento de actividades.
- Gestión de disponibilidad, expiración, cancelación y reinicio de sesiones.
- Registro de eventos relevantes para auditoría y control operativo.

**Valor comercial:** habilita flujos de selección repetibles y comparables, con reglas de avance y evidencia auditable.

### 3.4 Motor de tests psicométricos y aptitudinales

**Capacidades evidenciadas**

- Catálogo de instrumentos.
- Creación y edición de contenidos, escalas, ítems, alternativas y reglas de puntuación.
- Agrupación de escalas y fórmulas de cálculo.
- Normas y conversiones de resultados.
- Instrumentos de personalidad, cognitivos, impulso/autoregulación y orientación vocacional, según configuración disponible.
- Aplicación por bloques, progreso, temporizador y guardado de respuestas.
- Recuperación de sesión y manejo de expiración.
- Resultados individuales, resultados por usuario/proceso y resúmenes estructurados.
- Exportación de resultados y respuestas a Excel.
- Ranking por empresa/proceso.
- Configuraciones de ranking con dimensiones, umbrales, reglas knockout y clasificación.
- Presets oficiales y configuraciones parametrizables con huella de configuración.
- Recomendaciones vocacionales RIASEC cuando existe resultado y catálogo de recomendaciones.
- Conversión y presentación de puntajes normalizados, bandas y niveles interpretativos.
- Alertas de consistencia y reporte de datos faltantes.

**Valor comercial:** reduce trabajo manual de corrección, permite aplicar metodologías institucionales y entrega una lectura comparable para priorización de candidatos.

**Precaución contractual:** la validez psicométrica, normas, interpretación profesional, licencias de instrumentos y población objetivo deben ser declaradas por la entidad responsable y no se deben inferir únicamente desde el software.

### 3.5 Encuestas y evaluaciones configurables

**Capacidades evidenciadas**

- Creación de formularios de evaluación y encuestas.
- Preguntas, alternativas, tipos de respuesta, puntaje, obligatoriedad y ordenamiento.
- Duplicación, previsualización, edición y eliminación de formularios/preguntas.
- Aplicación por parte del usuario evaluado.
- Resultados, respuestas, estados de intento y actividad.
- Evaluaciones con nota y encuestas de satisfacción.
- Medios asociados a preguntas.
- Exportación de resultados y respuestas a Excel.
- Reporte de integridad con señales, filtros por fecha, PDF y Excel.
- Reprocesamiento y recuperación de evidencias de medios.
- Importación desde Moodle por API y desde XML de Moodle, con revisión de preguntas no soportadas.
- Generación asistida de evaluaciones mediante IA configurable.

**Valor comercial:** permite digitalizar evaluaciones institucionales, encuestas de experiencia, controles de aprendizaje y levantamientos de satisfacción en un mismo entorno.

### 3.6 Evaluación remota y evidencia audiovisual

**Capacidades evidenciadas**

- Captura de audio/video mediante navegador y MediaRecorder.
- Carga de evidencias por chunks.
- Inicialización, consulta de estado, finalización y reporte de fallos de carga.
- Reanudación/recuperación de evidencias parciales.
- Capturas periódicas de pantalla y acceso auditado.
- Registro de riesgos audiovisuales y eventos de integridad: salida de pantalla completa, cambios de foco, teclas sospechosas, bloqueo de menú contextual, múltiples voces posibles y otros eventos configurados.
- Configuración de permisos y estado de cámara/micrófono.
- Evidencias asociadas a sesión, intento, pregunta o bloque.
- Procesamiento diferido mediante jobs de medios definidos para ejecución asíncrona.

**Valor comercial:** entrega evidencia adicional para evaluaciones remotas y permite revisar incidencias sin depender exclusivamente de la declaración del usuario.

**Sujeto a validación:** compatibilidad de navegador/dispositivo, codecs, ancho de banda, capacidad de almacenamiento, consentimiento informado, retención y revisión humana de alertas.

### 3.7 Reconocimiento facial y prueba de presencia

**Capacidades evidenciadas**

- Registro/enrolamiento facial.
- Validación facial para actividades de evaluación.
- Integración de modelos de detección, alineamiento, reconocimiento y liveness ejecutados en el navegador.
- Configuración de proveedor/estrategia facial.
- Registro de intentos y verificaciones.
- Autorizaciones de corta duración para habilitar una actividad después de la validación.
- Vistas de orientación, enrolamiento, validación y estado del enrolamiento.
- Integración con prerrequisitos de procesos y evaluaciones.

**Valor comercial:** refuerza la autenticidad de la persona que inicia o continúa una evaluación remota.

**Sujeto a validación:** precisión por población y dispositivo, falsos positivos/negativos, accesibilidad, tratamiento de datos biométricos, consentimiento, retención, mecanismo de impugnación y cumplimiento normativo aplicable.

### 3.8 Entrevistas de selección

**Capacidades evidenciadas**

- Creación, edición, consulta y eliminación de procesos de entrevista.
- Definición de perfil de cargo, requisitos técnicos, requisitos conductuales y criterios de evaluación.
- Registro de postulante y vinculación con proceso psicométrico.
- Agenda de entrevistas y citas.
- Sala de entrevista integrada con Daily mediante rooms privados y tokens generados en backend.
- Participación de entrevistador/moderador y candidato.
- Notas del entrevistador.
- Evaluación técnica, conductual, general, recomendación, fortalezas, riesgos, comentarios y criterios evaluados.
- Carga, descarga y eliminación de documentos del candidato o del proceso.
- Extracción de texto desde TXT, DOCX, XLSX y PDF con texto embebido.
- Transcripción acumulada, estados de transcripción, snapshots y finalización.
- Procesamiento de trabajos de entrevista en segundo plano.
- Asistencia de IA para perfil de cargo, criterios o interpretación configurada.
- Vista previa de evaluación del candidato.
- Generación y descarga de informe final PDF.
- Integración con informe compuesto de entrevista + resultados psicométricos + documentos + evidencias.
- Control de consumo y proyección de minutos de Daily.

**Valor comercial:** estructura la entrevista, disminuye variabilidad entre evaluadores y consolida evidencia cuantitativa y cualitativa en un informe trazable.

### 3.9 Informes, reportes y documentos de decisión

**Capacidades evidenciadas**

- Catálogo de tipos de informe, fuentes y funcionalidades.
- Definición y versionado de informes.
- Asignación de informes por empresa.
- Plantillas funcionales declarativas en Markdown.
- Diseños visuales versionados con branding configurable.
- Interpretación de fuentes, bloques, métricas, fórmulas y transformaciones permitidas.
- Validación de referencias antes de generar.
- Informe individual de postulante.
- Informe de perfil de competencias.
- Informe final de entrevista.
- Generación HTML y PDF.
- Exportación Excel en módulos de resultados e integridad.
- Generación masiva por lotes y ZIP.
- Historial de ejecuciones y estado de lotes.
- Auditoría, limpieza de archivos temporales y trazabilidad de la versión utilizada.
- Presentación de datos faltantes como “Sin dato”, evitando convertir ausencia de información en resultado negativo.

**Valor comercial:** permite entregar productos documentales homogéneos, institucionales y escalables, con una salida lista para revisión, archivo o incorporación a un expediente.

### 3.10 Importaciones, exportaciones e interoperabilidad

**Capacidades evidenciadas**

- Importación masiva de usuarios desde Excel.
- Vista previa, validación por fila, corrección y aplicación de importación.
- Plantilla de importación.
- Exportación de resultados, respuestas, progreso, ranking e integridad a Excel.
- Exportación de reportes individuales, lotes y ZIP.
- Integración Moodle por Web Services REST con HTTPS y token.
- Importación Moodle XML como borradores sujetos a revisión.
- Servicios preparados para correo mediante PHPMailer.
- Integración Daily para videollamadas, consumo de minutos y transcripción.
- Integración IA configurable para evaluaciones e entrevistas.

**Valor comercial:** facilita la adopción progresiva, la integración con ecosistemas existentes y el intercambio de información con áreas de RR.HH., formación, control y auditoría.

## 4. Arquitectura y operación técnica

### 4.1 Arquitectura de aplicación

- Aplicación web PHP con separación MVC entre controladores, modelos, servicios y vistas.
- Apache como servidor web.
- MySQL como persistencia, con bases separadas por dominio funcional.
- Composer para dependencias PHP.
- Interfaz basada en CoreUI, JavaScript y componentes de interacción para formularios, dashboards, cargas y validaciones.
- Servicios especializados para correo, seguridad, reconocimiento facial, informes, entrevistas, medios, Moodle e IA.
- Docker Compose para reproducibilidad local y separación de servicios.

### 4.2 Procesamiento diferido

El Compose define workers independientes para entrevistas, informes y medios, además de un limpiador periódico de archivos. Esto permite separar las tareas largas de la petición web y establecer reintentos, estados y límites por tipo de proceso.

La disponibilidad real de cada worker, su dimensionamiento y su operación continua deben validarse por ambiente. En el levantamiento local se observaron activos la aplicación y el limpiador de reportes; los workers están definidos en la configuración y requieren una prueba operativa específica antes de comprometer niveles de servicio.

### 4.3 Observabilidad y control

- Perfilado de consultas y registro de consultas lentas configurable.
- Estados persistentes para procesos, sesiones, intentos, evidencias, jobs y lotes.
- Registro de eventos de login, actividad, integridad, acceso a medios y transcripción.
- Runner de migraciones con checksum, verificación de esquema y bloqueo contra ejecuciones concurrentes en Desarrollo local.
- Healthcheck de MySQL en Docker.

## 5. Diferenciadores de innovación

1. **Evaluación multimodal:** combina cuestionarios, psicometría, entrevista, documentos, audio/video y señales de integridad.
2. **IA con control humano:** la IA se utiliza como asistencia configurable; los informes advierten que la evidencia debe ser revisada por el responsable y no convierten automáticamente una inferencia en decisión.
3. **Informes declarativos y versionados:** permite evolucionar formatos y contenidos sin reescribir todo el motor de generación.
4. **Integridad verificable:** registra eventos y señales durante la evaluación remota para apoyar la revisión posterior.
5. **Arquitectura plug-and-play:** el kit de videollamadas Daily se documenta como componente reutilizable.
6. **Interoperabilidad educativa:** Moodle puede aportar definiciones de cuestionarios, preguntas, notas y resultados mediante API o XML.
7. **Gobierno de datos y aislamiento:** permisos, empresas, auditoría, tokens seguros y bases por dominio facilitan la operación institucional.

## 6. Matriz de beneficios para una licitación

| Necesidad habitual | Respuesta de e-talent | Evidencia técnica |
|---|---|---|
| Administrar múltiples organismos o clientes | Multiempresa, roles, scopes, branding y asignaciones por empresa | Core, perfiles, empresas, permisos e informes |
| Evaluar postulantes con criterios homogéneos | Procesos, instrumentos, escalas, reglas, prerrequisitos y rankings | Módulo Tests y modelos de procesos |
| Aplicar evaluaciones y encuestas | Formularios, preguntas, notas, resultados, integridad y exportación | Módulo Evaluaciones/Encuestas |
| Realizar entrevistas remotas | Agenda, sala Daily, documentos, notas, evaluación y transcripción | Módulo Entrevistas |
| Acreditar trazabilidad | Eventos, auditorías, estados, fuentes y versiones de informe | Tablas de actividad, auditoría y reportes |
| Entregar productos documentales | PDF, HTML, Excel, ZIP, plantillas y generación masiva | Módulo Reports y workers |
| Integrarse con sistemas existentes | Moodle, correo, Daily e IA configurable | Servicios de integración |
| Controlar evaluaciones remotas | Cámara, micrófono, pantalla, chunks, riesgos y rostro configurable | Evidencia audiovisual y facial |
| Personalizar la experiencia institucional | Branding, campos, presets y plantillas | Core y reportes |

## 7. Límites y condiciones para ofertar responsablemente

Las siguientes capacidades no deben presentarse como ilimitadas o automáticamente certificadas:

- **Escalabilidad:** se requiere prueba de carga con volúmenes de candidatos, concurrencia, generación masiva, multimedia y consultas representativas.
- **Reconocimiento facial:** requiere validación biométrica, legal, de accesibilidad y de precisión por población.
- **Audio/video y capturas:** requiere confirmar almacenamiento, retención, codecs, navegadores, consentimiento y capacidad de recuperación.
- **IA:** requiere credenciales, límites, costos, privacidad, timeout, manejo de errores y revisión humana.
- **Moodle:** requiere confirmar funciones Web Services disponibles, permisos del token, estructura del cuestionario y tipos de pregunta.
- **OCR:** el extractor de documentos soporta texto y formatos Office/PDF con texto embebido; el OCR de PDF escaneado no debe ofrecerse como operativo sin definir un componente compatible con la restricción de no ejecutar comandos del sistema desde PHP.
- **Transcripción:** depende del proveedor configurado y de la disponibilidad de la API; debe probarse el ciclo completo de estados, snapshots, errores y reintentos.
- **Cumplimiento normativo:** debe complementarse con política de privacidad, tratamiento de datos biométricos, retención, continuidad, respaldo, respuesta a incidentes y derechos de titulares.
- **Niveles de servicio:** deben acordarse por ambiente y dimensionarse con mediciones independientes para Desarrollo, QA y Producción.

## 8. Validaciones recomendadas antes de comprometer la propuesta

1. Ejecutar pruebas funcionales end-to-end de cada subplataforma.
2. Validar permisos por rol, empresa y tipo de dato.
3. Probar importación Excel con errores por fila, duplicados y volúmenes reales.
4. Probar aplicación de tests con pérdida de red, expiración, recuperación y concurrencia.
5. Validar captura audiovisual, chunks, reanudación, finalización y acceso auditado.
6. Validar reconocimiento facial en dispositivos y navegadores objetivo.
7. Validar entrevista Daily, transcripción, documentos y generación de informe.
8. Validar Moodle API/XML con cuestionarios representativos del mandante.
9. Ejecutar generación individual y masiva de PDF/Excel/ZIP, midiendo memoria, tiempo y recuperación de errores.
10. Ejecutar pruebas de carga y definir capacidad por ambiente.
11. Revisar consultas críticas, índices, planes de ejecución y consultas N+1.
12. Confirmar políticas de seguridad, privacidad, retención y respaldo con el área jurídica/mandante.

## 9. Fuentes técnicas revisadas

- `plataformas/*/plataforma.php`: definición de subplataformas y permisos.
- `plataformas/core/`: autenticación, empresas, usuarios, perfiles, branding, configuraciones, verificaciones y administración.
- `plataformas/tests/`: instrumentos, sesiones, corrección, rankings, exportaciones, evidencia audiovisual y reconocimiento facial.
- `plataformas/evaluaciones_encuestas/`: formularios, resultados, integridad, medios, IA, Moodle y exportaciones.
- `plataformas/interviews/`: procesos, agenda, Daily, documentos, notas, evaluaciones, transcripción, IA e informes.
- `plataformas/reports/`: catálogo, definiciones, plantillas, diseños, PDF, lotes e historial.
- `database/e_talent_*.sql` y `database/migrations/`: modelo de persistencia, auditoría, sesiones, evidencias y reportes.
- `docker-compose.yml`, `docker/php/Dockerfile` y documentación de `plug-and-play/`: arquitectura y operación local.

## 10. Conclusión comercial

e-talent cuenta con una base sólida para ofertar una solución integral de evaluación y selección digital, especialmente cuando la licitación requiere multiempresa, trazabilidad, evaluaciones configurables, entrevistas remotas, evidencia, reportabilidad e integración con plataformas existentes.

La recomendación comercial es ofertarla como una plataforma modular y configurable, con un núcleo ya implementado y un plan de habilitación por proyecto. La propuesta debe separar claramente las funcionalidades disponibles, las integraciones sujetas a configuración y las validaciones que dependen del ambiente, del proveedor externo o de políticas del mandante. Esto permite presentar una solución innovadora sin comprometer prestaciones que todavía requieren pruebas de capacidad, seguridad, biometría o cumplimiento.
