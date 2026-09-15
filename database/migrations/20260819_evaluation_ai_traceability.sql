-- Trazabilidad y revisión humana de preguntas generadas/importadas por IA.
-- Revisar y respaldar antes de ejecutar en Docker.

ALTER TABLE e_talent_evaluaciones_encuestas.evaluation_survey_questions
    ADD COLUMN source_pages JSON NULL AFTER is_active,
    ADD COLUMN evidence VARCHAR(500) NULL AFTER source_pages,
    ADD COLUMN needs_review TINYINT(1) NOT NULL DEFAULT 1 AFTER evidence;
