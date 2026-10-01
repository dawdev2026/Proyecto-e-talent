-- Amplía entrevistas para soportar perfil del cargo, antecedentes reutilizables
-- y evaluación estructurada sin perder documentos históricos.
USE `e_talent_interviews`;

CREATE TABLE IF NOT EXISTS `interview_job_profiles` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `process_id` int(10) unsigned NOT NULL,
  `title` varchar(180) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `technical_requirements` text COLLATE utf8mb4_unicode_ci,
  `behavioral_requirements` text COLLATE utf8mb4_unicode_ci,
  `evaluation_criteria_json` json DEFAULT NULL,
  `created_by` int(10) unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_interview_job_profiles_process` (`process_id`),
  CONSTRAINT `fk_interview_job_profiles_process` FOREIGN KEY (`process_id`) REFERENCES `interview_processes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE `interview_documents`
  ADD COLUMN `process_id` int(10) unsigned DEFAULT NULL AFTER `id`;

ALTER TABLE `interview_documents`
  ADD COLUMN `candidate_user_id` int(10) unsigned DEFAULT NULL AFTER `process_id`;

ALTER TABLE `interview_documents`
  MODIFY COLUMN `appointment_id` int(10) unsigned DEFAULT NULL;

ALTER TABLE `interview_documents`
  ADD INDEX `idx_interview_documents_process_scope` (`process_id`,`candidate_user_id`,`created_at`);

CREATE TABLE IF NOT EXISTS `interview_evaluations` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `appointment_id` int(10) unsigned NOT NULL,
  `evaluator_user_id` int(10) unsigned NOT NULL,
  `status` enum('draft','submitted') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'draft',
  `technical_score` tinyint(3) unsigned DEFAULT NULL,
  `behavioral_score` tinyint(3) unsigned DEFAULT NULL,
  `overall_score` tinyint(3) unsigned DEFAULT NULL,
  `recommendation` enum('pending','recommended','not_recommended','hold') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `strengths` text COLLATE utf8mb4_unicode_ci,
  `risks` text COLLATE utf8mb4_unicode_ci,
  `comments` text COLLATE utf8mb4_unicode_ci,
  `criteria_json` json DEFAULT NULL,
  `submitted_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_interview_evaluations_appointment_evaluator` (`appointment_id`,`evaluator_user_id`),
  KEY `idx_interview_evaluations_status` (`status`,`recommendation`,`updated_at`),
  CONSTRAINT `fk_interview_evaluations_appointment` FOREIGN KEY (`appointment_id`) REFERENCES `interview_appointments` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
