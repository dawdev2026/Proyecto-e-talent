-- Mantiene los intentos de evaluaciones supervisadas en preparación hasta que
-- el participante acepte y complete la autorización audiovisual.
-- No convierte ni borra intentos históricos. Ejecutar mediante el proceso de
-- migraciones autorizado; esta migración no se ejecuta automáticamente.
USE `e_talent_evaluaciones_encuestas`;

ALTER TABLE `evaluation_survey_attempts`
  MODIFY `status` ENUM('preparing','in_progress','completed','expired')
  COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'in_progress';
