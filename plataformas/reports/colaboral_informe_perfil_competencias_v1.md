---
report_key: colaboral_perfil_competencias
version: 2
interpreter_version: 1.1
scope: company
entity: process_user
output: pdf
template_key: platform_plain_v1
title: Informe final de entrevista y perfil de competencias
functionalities: [interviews]
---

## source.process_user
provider: TestProcessModel
method: processUser

## source.interview
provider: InterviewProcessModel
method: reportContext

## Identificación y alcance
Nombre del postulante: {{candidate.name}}
RUT: {{candidate.rut}}
Empresa o cliente: {{candidate.company}}
Proceso psicométrico: {{process.name}}
Proceso de entrevista: {{interview.process.name}}
Fecha programada: {{interview.appointment.scheduled_start_at}}
Fecha de finalización: {{interview.appointment.finished_at}}
Fecha de generación: {{resolved_at}}
Origen de la evidencia: {{interview.source_metadata.origin}}
Informe simulado: {{interview.source_metadata.is_simulated}}

Este informe integra antecedentes psicométricos, perfil del cargo y evidencia registrada durante la entrevista. Debe ser revisado por el evaluador responsable y no reemplaza las validaciones institucionales, médicas, físicas ni profesionales.

## Perfil del cargo y competencias a validar
Título del perfil: {{interview.process.job_profile.title}}
Descripción del perfil: {{interview.process.job_profile.description}}

### Requisitos técnicos
{{interview.process.job_profile.technical_requirements}}

### Requisitos conductuales
{{interview.process.job_profile.behavioral_requirements}}

### Criterios declarados
{{#each interview.process.job_profile.evaluation_criteria}}
Competencia: {{name}}
Tipo: {{type}}

{{/each}}

## Resumen ejecutivo
Clasificación psicométrica: {{structured_summary.classification.label}}
Puntaje final: {{structured_summary.classification.final_score}}
Resultado de knockout: {{structured_summary.classification.knockout}}

Fortaleza principal: {{structured_summary.strengths.personality.0.label}} ({{structured_summary.strengths.personality.0.value}})
Segunda fortaleza: {{structured_summary.strengths.personality.1.label}} ({{structured_summary.strengths.personality.1.value}})
Principal área de desarrollo: {{structured_summary.development_areas.0.label}} ({{structured_summary.development_areas.0.value}})
Datos psicométricos faltantes: {{structured_summary.missing_data.0}}

La conclusión final debe contrastar estos indicadores con la evidencia observable y las evaluaciones registradas en la entrevista.

## Indicadores psicométricos disponibles
Puntaje total: {{structured_summary.classification.total_score}}
Detalle de knockout: {{structured_summary.classification.knockout_detail}}

Responsabilidad: {{structured_summary.personality.0.score}}
Estabilidad emocional: {{structured_summary.personality.1.score}}
Amabilidad: {{structured_summary.personality.2.score}}
Apertura mental: {{structured_summary.personality.3.score}}

Aptitud cognitiva: {{structured_summary.cognitive.0.value}}
Razonamiento: {{structured_summary.cognitive.1.value}}
Rendimiento y adaptación: {{structured_summary.cognitive.2.value}}
Control de impulsos: {{structured_summary.impulse_self_regulation.0.value}}
Autorregulación: {{structured_summary.impulse_self_regulation.2.value}}

## Evidencia registrada de la entrevista
Estado de la entrevista: {{interview.appointment.meeting_status}}
Estado de transcripción: {{interview.transcript_metadata.status}}
Última captura de transcripción: {{interview.transcript_metadata.last_snapshot_at}}

### Transcripción
{{interview.transcript}}

### Notas del entrevistador
Autor de las notas: {{interview.note_metadata.author_name}}
Última actualización: {{interview.note_metadata.updated_at}}
{{interview.notes}}

## Evaluaciones del entrevistador
{{#each interview.evaluations}}
Evaluador: {{evaluator}}
Estado: {{status}}
Puntaje técnico: {{technical_score}}
Puntaje conductual: {{behavioral_score}}
Puntaje general: {{overall_score}}
Recomendación: {{recommendation}}
Fortalezas observadas: {{strengths}}
Riesgos o brechas observadas: {{risks}}
Comentarios: {{comments}}
Criterios evaluados: {{criteria}}
Fecha de envío: {{submitted_at}}

{{/each}}

## Motivación y foco de carrera
La motivación y la proyección de carrera solo deben incorporarse cuando hayan sido exploradas y registradas en la entrevista.

Datos vocacionales psicométricos: {{structured_summary.vocational.available}}
Mensaje de datos vocacionales: {{structured_summary.vocational.message}}

## Documentos y antecedentes adjuntos
{{#each interview.documents}}
Documento: {{name}}
Tipo: {{type}}
Estado de procesamiento: {{status}}
Tipo MIME: {{mime_type}}
Texto extraído disponible: {{status}}

{{/each}}

## Coherencias y aspectos a validar
El evaluador debe comparar la conducta observable, las respuestas, las notas, los documentos y los indicadores psicométricos. Las coincidencias pueden reforzar una hipótesis de trabajo; las contradicciones deben registrarse como aspectos a profundizar y no resolverse inventando información.

Alertas de consistencia psicométrica: {{structured_summary.consistency_alerts}}
Reglas de uso del reporte psicométrico: {{structured_summary.usage_rules}}

## Datos faltantes y limitaciones
La ausencia de transcripción, notas, evaluaciones, documentos, datos RIASEC o antecedentes médicos y físicos debe informarse explícitamente. Un dato faltante no equivale a un resultado negativo ni a cero.

## Recomendación final
Categoría psicométrica de referencia: {{structured_summary.classification.label}}
Recomendación del entrevistador: se requiere revisar las evaluaciones registradas y validar profesionalmente la evidencia antes de emitir una decisión.

Este documento no constituye por sí solo una recomendación de contratación, admisión, aptitud médica, aptitud física ni diagnóstico psicológico.

## Control de calidad y trazabilidad
- Identidad, empresa, proceso y cita deben coincidir.
- Toda evidencia debe conservar su origen y distinguirse entre declaración, observación, resultado psicométrico e inferencia.
- No deben inventarse puntajes, documentos, citas ni competencias.
- Los datos simulados deben marcarse mediante `interview.source_metadata.is_simulated`.
- La versión del informe y la fecha de generación deben quedar registradas.
