USE `e_talent_evaluaciones_encuestas`;

CREATE TABLE IF NOT EXISTS `evaluation_survey_question_media` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `question_id` int(10) unsigned NOT NULL,
  `media_type` enum('audio','image','video','file') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'audio',
  `original_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `storage_key` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `mime_type` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `file_size` int(10) unsigned NOT NULL,
  `sha256` char(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `sort_order` int(11) NOT NULL DEFAULT '10',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_esqm_question_hash` (`question_id`,`sha256`),
  KEY `idx_esqm_question_order` (`question_id`,`sort_order`,`id`),
  CONSTRAINT `fk_esqm_question` FOREIGN KEY (`question_id`) REFERENCES `evaluation_survey_questions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
