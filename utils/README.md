# Utils

Funciones transversales para evitar duplicacion entre sub-plataformas.

Archivos actuales:

- `helpers.php`: URLs, rutas, CSRF, flash messages, formato, navegacion y factory de plantillas.
- `uploads.php`: validacion transversal de cargas de imagenes.

Reglas:

- No agregar reglas especificas de una sub-plataforma.
- No duplicar helpers dentro de controladores o vistas.
- Si una funcion pertenece a un modulo especifico, debe vivir en `plataformas/{modulo}/services` o en su modelo.

