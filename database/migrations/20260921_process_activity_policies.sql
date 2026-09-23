-- Configuración tri-state de controles por proceso y snapshot al inicio.
-- MySQL 5.7 no admite ADD COLUMN IF NOT EXISTS: verificar columnas y aplicar
-- una sola vez en cada esquema, registrando la migración en el control local.

USE `e_talent_tests`;
ALTER TABLE `test_processes`
    ADD COLUMN `facial_enrollment_policy` ENUM('inherit','required','disabled') NOT NULL DEFAULT 'inherit' AFTER `require_facial_enrollment`,
    ADD COLUMN `component_validation_policy` ENUM('inherit','required','disabled') NOT NULL DEFAULT 'inherit' AFTER `facial_enrollment_policy`,
    ADD COLUMN `audio_visual_recording_policy` ENUM('inherit','required','disabled') NOT NULL DEFAULT 'inherit' AFTER `component_validation_policy`,
    ADD COLUMN `action_logging_policy` ENUM('inherit','required','disabled') NOT NULL DEFAULT 'inherit' AFTER `audio_visual_recording_policy`;

ALTER TABLE `test_sessions`
    ADD COLUMN `process_facial_enrollment_required` TINYINT(1) NULL DEFAULT NULL AFTER `audio_visual_quality_profile`,
    ADD COLUMN `process_component_validation_required` TINYINT(1) NULL DEFAULT NULL AFTER `process_facial_enrollment_required`,
    ADD COLUMN `process_record_audio_visual` TINYINT(1) NULL DEFAULT NULL AFTER `process_component_validation_required`,
    ADD COLUMN `process_record_actions` TINYINT(1) NULL DEFAULT NULL AFTER `process_record_audio_visual`,
    ADD COLUMN `process_policy_snapshot_at` DATETIME NULL DEFAULT NULL AFTER `process_record_actions`,
    ADD COLUMN `track_activity_enabled` TINYINT(1) NULL DEFAULT NULL AFTER `process_policy_snapshot_at`;

USE `e_talent_evaluaciones_encuestas`;
ALTER TABLE `evaluation_survey_attempts`
    ADD COLUMN `process_facial_enrollment_required` TINYINT(1) NULL DEFAULT NULL AFTER `audio_visual_quality_profile`,
    ADD COLUMN `process_component_validation_required` TINYINT(1) NULL DEFAULT NULL AFTER `process_facial_enrollment_required`,
    ADD COLUMN `process_record_audio_visual` TINYINT(1) NULL DEFAULT NULL AFTER `process_component_validation_required`,
    ADD COLUMN `process_record_actions` TINYINT(1) NULL DEFAULT NULL AFTER `process_record_audio_visual`,
    ADD COLUMN `process_policy_snapshot_at` DATETIME NULL DEFAULT NULL AFTER `process_record_actions`;
