# Sistema

Contiene la capa tecnica transversal de la plataforma.

No debe contener funcionalidades de negocio propias de una sub-plataforma.

Contenido:

```text
bootstrap.php
core/        Seguridad, autenticacion base y conexion compartida
layouts/     Layouts compartidos
mvc/         Controller base, Database wrapper y Template
assets/      Recursos internos no servidos directamente
componentes/ Componentes reutilizables
librerias/   Librerias locales o adaptadores
vendor/      Dependencias tecnicas de terceros instaladas por Composer
```

Las funcionalidades viven en:

```text
plataformas/core/
```

Ver:

```text
docs/arquitectura.md
docs/estructura.md
```
