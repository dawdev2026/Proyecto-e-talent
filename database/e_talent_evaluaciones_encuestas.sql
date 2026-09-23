-- MySQL dump 10.13  Distrib 5.7.44, for Linux (x86_64)
--
-- Host: localhost    Database: e_talent_evaluaciones_encuestas
-- ------------------------------------------------------
-- Server version	5.7.44

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Current Database: `e_talent_evaluaciones_encuestas`
--

CREATE DATABASE /*!32312 IF NOT EXISTS*/ `e_talent_evaluaciones_encuestas` /*!40100 DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci */;

USE `e_talent_evaluaciones_encuestas`;

--
-- Table structure for table `evaluation_survey_activity_events`
--

DROP TABLE IF EXISTS `evaluation_survey_activity_events`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `evaluation_survey_activity_events` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `attempt_id` int(10) unsigned NOT NULL,
  `form_id` int(10) unsigned NOT NULL,
  `user_id` int(10) unsigned NOT NULL,
  `event_type` varchar(60) COLLATE utf8mb4_unicode_ci NOT NULL,
  `question_id` int(10) unsigned DEFAULT NULL,
  `metadata` json DEFAULT NULL,
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`) USING BTREE,
  KEY `idx_esaee_attempt_created` (`attempt_id`,`created_at`) USING BTREE,
  KEY `idx_esaee_form_created` (`form_id`,`created_at`) USING BTREE,
  KEY `idx_esaee_user_created` (`user_id`,`created_at`) USING BTREE,
  KEY `idx_esaee_event_created` (`event_type`,`created_at`) USING BTREE,
  CONSTRAINT `fk_esaee_attempt` FOREIGN KEY (`attempt_id`) REFERENCES `evaluation_survey_attempts` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_esaee_form` FOREIGN KEY (`form_id`) REFERENCES `evaluation_survey_forms` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=163416 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `evaluation_survey_answers`
--

DROP TABLE IF EXISTS `evaluation_survey_answers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `evaluation_survey_answers` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `attempt_id` int(10) unsigned NOT NULL,
  `question_id` int(10) unsigned NOT NULL,
  `answer_value` longtext COLLATE utf8mb4_unicode_ci,
  `score_value` decimal(10,2) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `uq_esa_answer` (`attempt_id`,`question_id`) USING BTREE,
  KEY `idx_esans_question` (`question_id`) USING BTREE,
  CONSTRAINT `fk_esans_attempt` FOREIGN KEY (`attempt_id`) REFERENCES `evaluation_survey_attempts` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_esans_question` FOREIGN KEY (`question_id`) REFERENCES `evaluation_survey_questions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=186407 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `evaluation_survey_attempts`
--

DROP TABLE IF EXISTS `evaluation_survey_attempts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `evaluation_survey_attempts` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `form_id` int(10) unsigned NOT NULL,
  `user_id` int(10) unsigned NOT NULL,
  `process_id` int(10) unsigned DEFAULT NULL,
  `control_mode` enum('off','activity','supervised','supervised_audio_visual') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'off',
  `audio_visual_upload_failure_policy` enum('continue','retry_once','block') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'continue',
  `audio_visual_interruption_policy` enum('continue','pause','block') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pause',
  `audio_visual_voice_policy` enum('log','warn','pause') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'warn',
  `audio_visual_permission_policy` enum('continue','pause','block') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pause',
  `audio_visual_quality_profile` enum('economical','standard','high') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'standard',
  `process_facial_enrollment_required` tinyint(1) DEFAULT NULL,
  `process_component_validation_required` tinyint(1) DEFAULT NULL,
  `process_record_audio_visual` tinyint(1) DEFAULT NULL,
  `process_record_actions` tinyint(1) DEFAULT NULL,
  `process_policy_snapshot_at` datetime DEFAULT NULL,
  `attempt_number` int(10) unsigned NOT NULL DEFAULT '1',
  `status` enum('preparing','in_progress','completed','expired') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'in_progress',
  `expires_at` datetime DEFAULT NULL,
  `completed_at` datetime DEFAULT NULL,
  `max_score` decimal(10,2) DEFAULT NULL,
  `question_set_json` longtext COLLATE utf8mb4_unicode_ci,
  `raw_score` decimal(10,2) DEFAULT NULL,
  `final_score` decimal(10,2) DEFAULT NULL,
  `passed` tinyint(1) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `last_seen_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `uq_esa_process_form_user_attempt` (`process_id`,`form_id`,`user_id`,`attempt_number`) USING BTREE,
  KEY `idx_esa_form_status` (`form_id`,`status`) USING BTREE,
  KEY `idx_esa_user_status` (`user_id`,`status`) USING BTREE,
  KEY `idx_esa_process_form_user` (`process_id`,`form_id`,`user_id`) USING BTREE,
  KEY `idx_esa_status_presence` (`status`,`last_seen_at`,`process_id`,`user_id`) USING BTREE,
  CONSTRAINT `fk_esa_form` FOREIGN KEY (`form_id`) REFERENCES `evaluation_survey_forms` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=759 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `evaluation_survey_forms`
--

DROP TABLE IF EXISTS `evaluation_survey_forms`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `evaluation_survey_forms` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int(10) unsigned DEFAULT NULL,
  `form_type` enum('assessment','survey') COLLATE utf8mb4_unicode_ci NOT NULL,
  `title` varchar(180) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `instructions` text COLLATE utf8mb4_unicode_ci,
  `status` enum('draft','active','inactive') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'draft',
  `duration_minutes` int(10) unsigned NOT NULL DEFAULT '0',
  `control_mode` enum('off','activity','supervised','supervised_audio_visual') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'off',
  `audio_visual_upload_failure_policy` enum('continue','retry_once','block') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'continue',
  `audio_visual_interruption_policy` enum('continue','pause','block') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pause',
  `audio_visual_voice_policy` enum('log','warn','pause') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'warn',
  `audio_visual_permission_policy` enum('continue','pause','block') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pause',
  `audio_visual_quality_profile` enum('economical','standard','high') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'standard',
  `question_order_mode` enum('ordered','random') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'ordered',
  `question_display_limit` int(10) unsigned NOT NULL DEFAULT '0',
  `max_attempts` int(10) unsigned NOT NULL DEFAULT '1',
  `max_score` decimal(10,2) NOT NULL DEFAULT '100.00',
  `passing_score` decimal(10,2) DEFAULT NULL,
  `show_result_to_user` tinyint(1) NOT NULL DEFAULT '1',
  `result_display_mode` enum('best_only','collapsible_attempts') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'best_only',
  `show_correction_to_user` tinyint(1) NOT NULL DEFAULT '0',
  `is_required` tinyint(1) NOT NULL DEFAULT '0',
  `sort_order` int(11) NOT NULL DEFAULT '100',
  `created_by` int(10) unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`) USING BTREE,
  KEY `idx_esf_type_status` (`form_type`,`status`) USING BTREE,
  KEY `idx_esf_created_by` (`created_by`) USING BTREE,
  KEY `idx_esf_company_type_status` (`company_id`,`form_type`,`status`) USING BTREE
) ENGINE=InnoDB AUTO_INCREMENT=42 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `evaluation_survey_media_access_audit`
--

DROP TABLE IF EXISTS `evaluation_survey_media_access_audit`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `evaluation_survey_media_access_audit` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `attempt_id` int(10) unsigned NOT NULL,
  `evidence_id` bigint(20) unsigned DEFAULT NULL,
  `actor_user_id` int(10) unsigned NOT NULL,
  `action` enum('result_viewed','video_viewed','video_downloaded','screen_capture_viewed') COLLATE utf8mb4_unicode_ci NOT NULL,
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`) USING BTREE,
  KEY `idx_esmvaa_attempt_created` (`attempt_id`,`created_at`) USING BTREE,
  KEY `idx_esmvaa_actor_created` (`actor_user_id`,`created_at`) USING BTREE,
  KEY `fk_esmvaa_evidence` (`evidence_id`) USING BTREE,
  CONSTRAINT `fk_esmvaa_attempt` FOREIGN KEY (`attempt_id`) REFERENCES `evaluation_survey_attempts` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_esmvaa_evidence` FOREIGN KEY (`evidence_id`) REFERENCES `evaluation_survey_media_evidence` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=2904 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `evaluation_survey_media_chunks`
--

DROP TABLE IF EXISTS `evaluation_survey_media_chunks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `evaluation_survey_media_chunks` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `evidence_id` bigint(20) unsigned NOT NULL,
  `chunk_number` int(10) unsigned NOT NULL,
  `storage_key` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `size_bytes` int(10) unsigned NOT NULL,
  `sha256` char(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `uploaded_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `uq_esmc_chunk` (`evidence_id`,`chunk_number`) USING BTREE,
  KEY `idx_esmc_evidence_chunk` (`evidence_id`,`chunk_number`) USING BTREE,
  CONSTRAINT `fk_esmc_evidence` FOREIGN KEY (`evidence_id`) REFERENCES `evaluation_survey_media_evidence` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=69303 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `evaluation_survey_media_evidence`
--

DROP TABLE IF EXISTS `evaluation_survey_media_evidence`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `evaluation_survey_media_evidence` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `attempt_id` int(10) unsigned NOT NULL,
  `segment_number` int(10) unsigned NOT NULL DEFAULT '1',
  `form_id` int(10) unsigned NOT NULL,
  `user_id` int(10) unsigned NOT NULL,
  `status` enum('pending','recording','uploading','processing','saved','partial','failed','not_supported','not_consented') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `processing_status` enum('not_queued','queued','processing','completed','failed') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'not_queued',
  `storage_key` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `mime_type` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `file_size` bigint(20) unsigned DEFAULT NULL,
  `uploaded_bytes` bigint(20) unsigned NOT NULL DEFAULT '0',
  `duration_seconds` int(10) unsigned DEFAULT NULL,
  `sha256` char(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `consented_at` datetime DEFAULT NULL,
  `recording_started_at` datetime DEFAULT NULL,
  `recording_finished_at` datetime DEFAULT NULL,
  `upload_started_at` datetime DEFAULT NULL,
  `upload_finished_at` datetime DEFAULT NULL,
  `failure_code` varchar(80) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `failure_reason` text COLLATE utf8mb4_unicode_ci,
  `processing_error` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `processing_summary_json` longtext COLLATE utf8mb4_unicode_ci,
  `processed_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `uq_esme_attempt_segment` (`attempt_id`,`segment_number`) USING BTREE,
  KEY `idx_esme_status_updated` (`status`,`updated_at`) USING BTREE,
  KEY `idx_esme_user_created` (`user_id`,`created_at`) USING BTREE,
  KEY `fk_esme_form` (`form_id`) USING BTREE,
  KEY `idx_esme_attempt_segment` (`attempt_id`,`segment_number`) USING BTREE,
  CONSTRAINT `fk_esme_attempt` FOREIGN KEY (`attempt_id`) REFERENCES `evaluation_survey_attempts` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_esme_form` FOREIGN KEY (`form_id`) REFERENCES `evaluation_survey_forms` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=1180 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `evaluation_survey_media_processing_jobs`
--

DROP TABLE IF EXISTS `evaluation_survey_media_processing_jobs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `evaluation_survey_media_processing_jobs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `evidence_id` bigint(20) unsigned NOT NULL,
  `status` enum('queued','processing','completed','failed') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'queued',
  `attempts` tinyint(3) unsigned NOT NULL DEFAULT '0',
  `locked_at` datetime DEFAULT NULL,
  `started_at` datetime DEFAULT NULL,
  `completed_at` datetime DEFAULT NULL,
  `last_error` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `uq_esmpj_evidence` (`evidence_id`) USING BTREE,
  KEY `idx_esmpj_queue` (`status`,`attempts`,`locked_at`,`id`) USING BTREE,
  CONSTRAINT `fk_esmpj_evidence` FOREIGN KEY (`evidence_id`) REFERENCES `evaluation_survey_media_evidence` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=720 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `evaluation_survey_media_risk_events`
--

DROP TABLE IF EXISTS `evaluation_survey_media_risk_events`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `evaluation_survey_media_risk_events` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `attempt_id` int(10) unsigned NOT NULL,
  `evidence_id` bigint(20) unsigned DEFAULT NULL,
  `event_type` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL,
  `severity` enum('info','attention','risk') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'attention',
  `confidence` decimal(5,4) DEFAULT NULL,
  `metadata` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`) USING BTREE,
  KEY `idx_esmre_attempt_created` (`attempt_id`,`created_at`) USING BTREE,
  KEY `idx_esmre_type_created` (`event_type`,`created_at`) USING BTREE,
  KEY `fk_esmre_evidence` (`evidence_id`) USING BTREE,
  CONSTRAINT `fk_esmre_attempt` FOREIGN KEY (`attempt_id`) REFERENCES `evaluation_survey_attempts` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_esmre_evidence` FOREIGN KEY (`evidence_id`) REFERENCES `evaluation_survey_media_evidence` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=58646 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `evaluation_survey_question_options`
--

DROP TABLE IF EXISTS `evaluation_survey_question_options`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `evaluation_survey_question_options` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `question_id` int(10) unsigned NOT NULL,
  `option_label` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL,
  `option_value` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL,
  `score_value` decimal(10,2) DEFAULT NULL,
  `sort_order` int(11) NOT NULL DEFAULT '100',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`) USING BTREE,
  KEY `idx_esqo_question_order` (`question_id`,`is_active`,`sort_order`) USING BTREE,
  CONSTRAINT `fk_esqo_question` FOREIGN KEY (`question_id`) REFERENCES `evaluation_survey_questions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3195 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `evaluation_survey_questions`
--

DROP TABLE IF EXISTS `evaluation_survey_questions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `evaluation_survey_questions` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `form_id` int(10) unsigned NOT NULL,
  `question_text` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `question_type` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_required` tinyint(1) NOT NULL DEFAULT '0',
  `points` decimal(10,2) NOT NULL DEFAULT '0.00',
  `sort_order` int(11) NOT NULL DEFAULT '100',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `source_pages` json DEFAULT NULL,
  `evidence` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `needs_review` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`) USING BTREE,
  KEY `idx_esq_form_order` (`form_id`,`is_active`,`sort_order`) USING BTREE,
  CONSTRAINT `fk_esq_form` FOREIGN KEY (`form_id`) REFERENCES `evaluation_survey_forms` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=801 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `evaluation_survey_screen_captures`
--

DROP TABLE IF EXISTS `evaluation_survey_screen_captures`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `evaluation_survey_screen_captures` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `attempt_id` int(10) unsigned NOT NULL,
  `evidence_id` bigint(20) unsigned DEFAULT NULL,
  `capture_source` enum('screen','canvas') COLLATE utf8mb4_unicode_ci NOT NULL,
  `capture_number` int(10) unsigned NOT NULL,
  `question_id` int(10) unsigned DEFAULT NULL,
  `event_type` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'periodic',
  `mime_type` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `storage_key` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `file_size` int(10) unsigned NOT NULL,
  `sha256` char(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `captured_at` datetime NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `uq_essc_attempt_number` (`attempt_id`,`capture_number`) USING BTREE,
  KEY `idx_essc_attempt_created` (`attempt_id`,`created_at`) USING BTREE,
  KEY `idx_essc_evidence_created` (`evidence_id`,`created_at`) USING BTREE,
  CONSTRAINT `fk_essc_attempt` FOREIGN KEY (`attempt_id`) REFERENCES `evaluation_survey_attempts` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_essc_evidence` FOREIGN KEY (`evidence_id`) REFERENCES `evaluation_survey_media_evidence` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=53345 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `evaluation_survey_settings`
--

DROP TABLE IF EXISTS `evaluation_survey_settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `evaluation_survey_settings` (
  `setting_key` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `setting_value` text COLLATE utf8mb4_unicode_ci,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`setting_key`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping events for database 'e_talent_evaluaciones_encuestas'
--

--
-- Dumping routines for database 'e_talent_evaluaciones_encuestas'
--
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-09-15  1:40:35
