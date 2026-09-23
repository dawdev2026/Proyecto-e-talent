---
design_key: informe_postulante_proceso_v1
design_version: 1
template_key: platform_postulant_v1
renderer: pdf
locale: es-CL
output:
  format: pdf
  disposition: attachment
  filename_pattern: "{{report_key}}_{{resolved_at:YYYYMMDD_HHmmss}}.pdf"
  one_file_per_candidate: true
page:
  size: A4
  orientation: portrait
  units: pt
  content:
    x: 36
    width: 523
    right: 559
  footer:
    line_y: 803
    text_y: 820
brand:
  name_source_order:
    - design.topbar_name
    - design.html_title
    - "e-talent"
  logo_source_order:
    - design.topbar_icon_path
    - design.html_favicon_path
    - design.login_logo_path
  accepted_logo_format: png
colors:
  primary_source: design.app_primary_color
  accent_source: design.button_background_color
  text_source: design.portal_text_color
  muted_source: derived(text, white, 58%)
  light_source: design.layout_background_color
  border_source: design.table_border_color
fallback:
  template_key: platform_plain_v1
  when:
    - design metadata is invalid
    - logo is unavailable
    - a block cannot be rendered safely
---

# Contrato de diseño visual

Este archivo define únicamente la presentación del PDF. La información y sus fuentes se obtienen desde el MD funcional del informe y desde la ejecución validada del proceso.

## Estructura visual

1. Encabezado institucional con logotipo, nombre de la plataforma, tipo de evaluación y fecha de emisión.
2. Título principal “Informe del Postulante”.
3. Ficha de identificación de la persona con nombre, RUT y proceso.
4. Bloques por dimensión, usando tarjetas en una grilla de tres columnas en desktop y una columna en pantallas pequeñas.
5. Cada tarjeta muestra nombre de dimensión, puntaje normalizado, barra proporcional y texto interpretativo.
6. Separadores de sección con color primario y jerarquía tipográfica consistente.
7. Pie de página con paginación y referencia de generación.

## Reglas de presentación

- Mantener contraste suficiente, saltos de página controlados y evitar cortes dentro de tarjetas.
- Mostrar valores ausentes como “Sin dato”, nunca como errores técnicos.
- No inventar textos interpretativos: se deben utilizar únicamente los valores resueltos por el MD funcional.
- El resultado se entrega como archivo PDF descargable, respetando el nombre generado por `output.filename_pattern`.
