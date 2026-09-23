-- Registro resumido de revisiones previas de cámara, micrófono y captura.
-- No almacena archivos audiovisuales ni resultados de evaluaciones.
USE `e_talent_core`;

CREATE TABLE IF NOT EXISTS `component_validation_events` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int(10) unsigned NOT NULL,
  `user_id` int(10) unsigned NOT NULL,
  `device_type` enum('desktop','tablet','mobile','unknown') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'unknown',
  `os_name` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Desconocido',
  `browser_name` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Desconocido',
  `browser_version` varchar(24) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `outcome` enum('passed','partial','failed') COLLATE utf8mb4_unicode_ci NOT NULL,
  `metadata` json NOT NULL,
  `user_agent` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_component_validation_company_date` (`company_id`,`created_at`,`id`),
  KEY `idx_component_validation_user_company_date` (`user_id`,`company_id`,`created_at`,`id`),
  CONSTRAINT `fk_component_validation_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_component_validation_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
