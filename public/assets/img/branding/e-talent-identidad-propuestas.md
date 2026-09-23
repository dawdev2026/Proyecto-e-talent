# e-talent · propuestas de identidad visual

Dos rutas gráficas para una plataforma B2B de evaluaciones, entrevistas e informes de conducta.

## Propuesta A — Insight humano (recomendada)

Una identidad cálida y tecnológica: convierte datos de personas en conversaciones y decisiones más claras. El símbolo conecta nodos y sugiere red, escucha y lectura de patrones.

- Fondo de login: `et-propuesta-a/login-background.png`
- Logo: `et-propuesta-a/logo.svg`
- Favicon: `et-propuesta-a/favicon.svg`
- Uso recomendado: login, entrevistas personalizadas, dashboards de candidatos y comunicación comercial.

### Paleta

| Token | HEX | Uso |
| --- | --- | --- |
| Ink | `#0B2533` | texto principal, navegación y fondos de alto contraste |
| Teal | `#20B8B0` | acción primaria, progreso, enlaces activos |
| Coral | `#F06C5B` | acento humano, estados destacados y llamadas a la acción |
| Mist | `#F4F7F6` | superficies y fondos claros |

## Propuesta B — Criterio claro

Una identidad más analítica y ejecutiva: ordena el proceso de evaluación en capas, rutas y evidencia. El símbolo modular funciona bien en informes, reportes y módulos de administración.

- Fondo de login: `et-propuesta-b/login-background.png`
- Logo: `et-propuesta-b/logo.svg`
- Favicon: `et-propuesta-b/favicon.svg`
- Uso recomendado: informes, paneles de métricas, administración y presentaciones corporativas.

### Paleta

| Token | HEX | Uso |
| --- | --- | --- |
| Indigo | `#243B7A` | marca principal, navegación y títulos |
| Turquoise | `#20B8B0` | acciones, progreso y datos destacados |
| Sand | `#E8DCC8` | fondos cálidos, apoyo editorial y acentos suaves |
| Snow | `#F8FAFC` | superficies y fondos de lectura |

## Tipografía y escala

La propuesta usa **Inter** como sans serif principal: legible en formularios y tablas, con suficiente carácter para titulares. Si se evita cargar una fuente externa, usar `Arial, sans-serif` como fallback.

| Rol | Tamaño desktop | Tamaño móvil | Peso | Interlineado |
| --- | ---: | ---: | ---: | ---: |
| Display de login | 40 px | 32 px | 750 | 1.05 |
| H1 de sección | 32 px | 26 px | 700 | 1.15 |
| H2 / tarjeta | 24 px | 20 px | 700 | 1.2 |
| Cuerpo | 16 px | 16 px | 400 | 1.5 |
| Label / navegación | 14 px | 14 px | 600 | 1.35 |
| Auxiliar / metadata | 12 px | 12 px | 500 | 1.35 |

## Reglas rápidas de uso

- Mantener el logotipo con un área libre mínima equivalente a la altura del punto de `e·talent`.
- Usar el favicon en 32×32 px y 48×48 px; el SVG escala sin pérdida.
- En login, reservar una superficie opaca para el formulario y no colocar texto sobre la imagen.
- No usar el coral y el turquesa simultáneamente como colores de botones primarios; uno debe quedar como acento.
- Mantener contraste WCAG AA: texto normal mínimo 4.5:1 y texto grande mínimo 3:1.
- Para una primera implementación de producto, seleccionar Propuesta A; seleccionar B si el posicionamiento buscado es más institucional y orientado a reporting.

## Producción de fondos

Los fondos se generaron con la skill `imagegen` en modo built-in, usando prompts de tipo `ui-mockup`, con espacio negativo reservado para el formulario, sin texto ni marcas de agua.
