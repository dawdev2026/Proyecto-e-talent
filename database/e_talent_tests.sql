-- MySQL dump 10.13  Distrib 5.7.44, for Linux (x86_64)
--
-- Host: localhost    Database: e_talent_tests
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
-- Current Database: `e_talent_tests`
--

CREATE DATABASE /*!32312 IF NOT EXISTS*/ `e_talent_tests` /*!40100 DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci */;

USE `e_talent_tests`;

--
-- Table structure for table `company_test_instruments`
--

DROP TABLE IF EXISTS `company_test_instruments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `company_test_instruments` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int(10) unsigned NOT NULL,
  `instrument_id` int(10) unsigned NOT NULL,
  `duration_minutes` int(10) unsigned DEFAULT NULL,
  `question_order_mode` enum('ordered','random') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `use_blocks` tinyint(1) DEFAULT NULL,
  `block_size` int(10) unsigned DEFAULT NULL,
  `require_block_completion` tinyint(1) DEFAULT NULL,
  `user_can_view_results` tinyint(1) DEFAULT NULL,
  `show_question_numbers` tinyint(1) DEFAULT NULL,
  `auto_start_enabled` tinyint(1) DEFAULT NULL,
  `auto_start_order` int(10) unsigned DEFAULT NULL,
  `control_mode` enum('off','activity','supervised','supervised_audio_visual') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `uq_company_test_instrument` (`company_id`,`instrument_id`) USING BTREE,
  KEY `idx_company_test_instrument_company` (`company_id`) USING BTREE,
  KEY `idx_company_test_instrument_instrument` (`instrument_id`) USING BTREE
) ENGINE=InnoDB AUTO_INCREMENT=18 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `riasec_career_recommendations`
--

DROP TABLE IF EXISTS `riasec_career_recommendations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `riasec_career_recommendations` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `category_code` char(1) COLLATE utf8mb4_unicode_ci NOT NULL,
  `profile_code` varchar(3) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `title` varchar(160) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `pathways` text COLLATE utf8mb4_unicode_ci,
  `sort_order` int(11) NOT NULL DEFAULT '100',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `uq_riasec_recommendation` (`category_code`,`title`) USING BTREE,
  KEY `idx_riasec_recommendations_category` (`category_code`,`sort_order`) USING BTREE,
  KEY `idx_riasec_recommendations_profile` (`profile_code`,`sort_order`) USING BTREE
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `test_activity_events`
--

DROP TABLE IF EXISTS `test_activity_events`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `test_activity_events` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `session_id` int(10) unsigned NOT NULL,
  `instrument_id` int(10) unsigned NOT NULL,
  `user_id` int(10) unsigned NOT NULL,
  `event_type` varchar(60) COLLATE utf8mb4_unicode_ci NOT NULL,
  `item_id` int(10) unsigned DEFAULT NULL,
  `block_number` int(10) unsigned DEFAULT NULL,
  `metadata` text COLLATE utf8mb4_unicode_ci,
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`) USING BTREE,
  KEY `idx_test_activity_session` (`session_id`,`created_at`) USING BTREE,
  KEY `idx_test_activity_user` (`user_id`,`created_at`) USING BTREE,
  KEY `idx_test_activity_event` (`event_type`,`created_at`) USING BTREE,
  KEY `idx_test_activity_item` (`item_id`) USING BTREE,
  KEY `fk_test_activity_instrument_id` (`instrument_id`) USING BTREE,
  CONSTRAINT `fk_test_activity_instrument_id` FOREIGN KEY (`instrument_id`) REFERENCES `test_instruments` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_test_activity_item_id` FOREIGN KEY (`item_id`) REFERENCES `test_items` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_test_activity_session_id` FOREIGN KEY (`session_id`) REFERENCES `test_sessions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3234181 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_general_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 DEFINER=`root`@`%`*/ /*!50003 TRIGGER `trg_test_activity_rollup_ai` AFTER INSERT ON `test_activity_events` FOR EACH ROW BEGIN
    INSERT INTO test_session_rollups (session_id, process_id, instrument_id, user_id, activity_events_total, activity_attention_total, activity_risk_total, last_activity_event_at)
    SELECT id, process_id, instrument_id, user_id, 1, IF(NEW.event_type IN ('tab_hidden','window_blurred','inactive_detected','fullscreen_exited'), 1, 0), IF(NEW.event_type IN ('fullscreen_denied','fullscreen_failed','fullscreen_unavailable','suspicious_key_printscreen','suspicious_key_print','suspicious_key_save','suspicious_key_copy','suspicious_key_devtools','context_menu_blocked','copy_blocked','cut_blocked','paste_blocked','drag_blocked','print_blocked'), 1, 0), NEW.created_at
    FROM test_sessions WHERE id = NEW.session_id
    ON DUPLICATE KEY UPDATE activity_events_total = activity_events_total + 1, activity_attention_total = activity_attention_total + IF(NEW.event_type IN ('tab_hidden','window_blurred','inactive_detected','fullscreen_exited'), 1, 0), activity_risk_total = activity_risk_total + IF(NEW.event_type IN ('fullscreen_denied','fullscreen_failed','fullscreen_unavailable','suspicious_key_printscreen','suspicious_key_print','suspicious_key_save','suspicious_key_copy','suspicious_key_devtools','context_menu_blocked','copy_blocked','cut_blocked','paste_blocked','drag_blocked','print_blocked'), 1, 0), last_activity_event_at = GREATEST(COALESCE(last_activity_event_at, NEW.created_at), NEW.created_at);
END */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;

--
-- Table structure for table `test_answer_scores`
--

DROP TABLE IF EXISTS `test_answer_scores`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `test_answer_scores` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `session_id` int(10) unsigned NOT NULL,
  `item_id` int(10) unsigned NOT NULL,
  `scale_id` int(10) unsigned NOT NULL,
  `rule_id` int(10) unsigned DEFAULT NULL,
  `score_value` decimal(10,4) NOT NULL DEFAULT '0.0000',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `uq_test_answer_scores_rule` (`session_id`,`item_id`,`scale_id`,`rule_id`) USING BTREE,
  KEY `idx_test_answer_scores_session` (`session_id`) USING BTREE,
  KEY `idx_test_answer_scores_scale` (`scale_id`) USING BTREE,
  KEY `fk_test_answer_scores_item_id` (`item_id`) USING BTREE,
  KEY `fk_test_answer_scores_rule_id` (`rule_id`) USING BTREE,
  CONSTRAINT `fk_test_answer_scores_item_id` FOREIGN KEY (`item_id`) REFERENCES `test_items` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_test_answer_scores_rule_id` FOREIGN KEY (`rule_id`) REFERENCES `test_item_score_rules` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_test_answer_scores_scale_id` FOREIGN KEY (`scale_id`) REFERENCES `test_scales` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_test_answer_scores_session_id` FOREIGN KEY (`session_id`) REFERENCES `test_sessions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=1801621 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `test_answers`
--

DROP TABLE IF EXISTS `test_answers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `test_answers` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `session_id` int(10) unsigned NOT NULL,
  `item_id` int(10) unsigned NOT NULL,
  `answer_value` text COLLATE utf8mb4_unicode_ci,
  `score_value` decimal(10,4) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `uq_test_answers_session_item` (`session_id`,`item_id`) USING BTREE,
  KEY `idx_test_answers_session` (`session_id`) USING BTREE,
  KEY `idx_test_answers_item` (`item_id`) USING BTREE,
  CONSTRAINT `fk_test_answers_item_id` FOREIGN KEY (`item_id`) REFERENCES `test_items` (`id`),
  CONSTRAINT `fk_test_answers_session_id` FOREIGN KEY (`session_id`) REFERENCES `test_sessions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=11292383 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_general_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 DEFINER=`root`@`%`*/ /*!50003 TRIGGER `trg_test_answers_rollup_ai` AFTER INSERT ON `test_answers` FOR EACH ROW BEGIN
    INSERT INTO test_session_rollups (session_id, process_id, instrument_id, user_id, answers_count, answered_count)
    SELECT id, process_id, instrument_id, user_id, 1, IF(NEW.answer_value IS NOT NULL AND TRIM(NEW.answer_value) <> '', 1, 0)
    FROM test_sessions WHERE id = NEW.session_id
    ON DUPLICATE KEY UPDATE answers_count = answers_count + 1, answered_count = answered_count + IF(NEW.answer_value IS NOT NULL AND TRIM(NEW.answer_value) <> '', 1, 0);
END */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_general_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 DEFINER=`root`@`%`*/ /*!50003 TRIGGER `trg_test_answers_rollup_au` AFTER UPDATE ON `test_answers` FOR EACH ROW BEGIN
    UPDATE test_session_rollups
    SET answered_count = GREATEST(0, answered_count - IF(OLD.answer_value IS NOT NULL AND TRIM(OLD.answer_value) <> '', 1, 0) + IF(NEW.answer_value IS NOT NULL AND TRIM(NEW.answer_value) <> '', 1, 0))
    WHERE session_id = NEW.session_id;
END */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_general_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 DEFINER=`root`@`%`*/ /*!50003 TRIGGER `trg_test_answers_rollup_ad` AFTER DELETE ON `test_answers` FOR EACH ROW BEGIN
    UPDATE test_session_rollups SET answers_count = GREATEST(0, answers_count - 1), answered_count = GREATEST(0, answered_count - IF(OLD.answer_value IS NOT NULL AND TRIM(OLD.answer_value) <> '', 1, 0)) WHERE session_id = OLD.session_id;
END */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;

--
-- Table structure for table `test_audio_visual_risk_events`
--

DROP TABLE IF EXISTS `test_audio_visual_risk_events`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `test_audio_visual_risk_events` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `session_id` int(10) unsigned NOT NULL,
  `evidence_id` bigint(20) unsigned DEFAULT NULL,
  `event_type` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL,
  `severity` enum('info','attention','risk') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'attention',
  `confidence` decimal(5,4) DEFAULT NULL,
  `started_at` datetime DEFAULT NULL,
  `ended_at` datetime DEFAULT NULL,
  `metadata` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`) USING BTREE,
  KEY `idx_test_av_risk_session` (`session_id`,`created_at`) USING BTREE,
  KEY `idx_test_av_risk_type` (`event_type`,`created_at`) USING BTREE,
  KEY `fk_test_av_risk_evidence` (`evidence_id`) USING BTREE,
  CONSTRAINT `fk_test_av_risk_evidence` FOREIGN KEY (`evidence_id`) REFERENCES `test_media_evidence` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_test_av_risk_session` FOREIGN KEY (`session_id`) REFERENCES `test_sessions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=845522 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `test_instruments`
--

DROP TABLE IF EXISTS `test_instruments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `test_instruments` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(60) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(160) COLLATE utf8mb4_unicode_ci NOT NULL,
  `category` enum('personality','behavior','cognitive','verbal') COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `source_reference` text COLLATE utf8mb4_unicode_ci,
  `duration_minutes` int(10) unsigned NOT NULL DEFAULT '0',
  `instructions` text COLLATE utf8mb4_unicode_ci,
  `use_blocks` tinyint(1) NOT NULL DEFAULT '0',
  `block_size` int(10) unsigned NOT NULL DEFAULT '0',
  `require_block_completion` tinyint(1) NOT NULL DEFAULT '1',
  `track_activity_enabled` tinyint(1) NOT NULL DEFAULT '0',
  `supervised_mode_enabled` tinyint(1) NOT NULL DEFAULT '0',
  `control_mode` enum('off','activity','supervised','supervised_audio_visual') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'off',
  `audio_visual_upload_failure_policy` enum('continue','retry_once','block') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'continue',
  `audio_visual_interruption_policy` enum('continue','pause','block') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pause',
  `audio_visual_voice_policy` enum('log','warn','pause') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'warn',
  `audio_visual_permission_policy` enum('continue','pause','block') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pause',
  `audio_visual_quality_profile` enum('economical','standard','high') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'standard',
  `auto_start_enabled` tinyint(1) NOT NULL DEFAULT '0',
  `auto_start_order` int(10) unsigned NOT NULL DEFAULT '100',
  `question_order_mode` enum('ordered','random') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'ordered',
  `show_question_numbers` tinyint(1) NOT NULL DEFAULT '1',
  `user_can_view_results` tinyint(1) NOT NULL DEFAULT '1',
  `status` enum('draft','active','inactive') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'draft',
  `requires_manual_review` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `uq_test_instruments_code` (`code`) USING BTREE,
  KEY `idx_test_instruments_status` (`status`) USING BTREE,
  KEY `idx_test_instruments_category` (`category`) USING BTREE
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `test_item_score_rules`
--

DROP TABLE IF EXISTS `test_item_score_rules`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `test_item_score_rules` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `instrument_id` int(10) unsigned NOT NULL,
  `item_id` int(10) unsigned NOT NULL,
  `scale_id` int(10) unsigned NOT NULL,
  `rule_type` enum('direct','reverse','keyed','mapped') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'mapped',
  `answer_value` varchar(80) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `score_value` decimal(10,4) DEFAULT NULL,
  `weight` decimal(10,4) NOT NULL DEFAULT '1.0000',
  `rule_config` text COLLATE utf8mb4_unicode_ci,
  `sort_order` int(11) NOT NULL DEFAULT '100',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`) USING BTREE,
  KEY `idx_test_score_rules_instrument` (`instrument_id`,`is_active`) USING BTREE,
  KEY `idx_test_score_rules_item` (`item_id`) USING BTREE,
  KEY `idx_test_score_rules_scale` (`scale_id`) USING BTREE,
  KEY `idx_test_score_rules_item_lookup` (`instrument_id`,`item_id`,`is_active`,`sort_order`) USING BTREE,
  CONSTRAINT `fk_test_score_rules_instrument_id` FOREIGN KEY (`instrument_id`) REFERENCES `test_instruments` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_test_score_rules_item_id` FOREIGN KEY (`item_id`) REFERENCES `test_items` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_test_score_rules_scale_id` FOREIGN KEY (`scale_id`) REFERENCES `test_scales` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3046 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `test_items`
--

DROP TABLE IF EXISTS `test_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `test_items` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `instrument_id` int(10) unsigned NOT NULL,
  `scale_id` int(10) unsigned DEFAULT NULL,
  `item_key` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL,
  `prompt` mediumtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `image_url` text COLLATE utf8mb4_unicode_ci,
  `item_type` enum('likert','single_choice','multiple_choice','open_text','numeric') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'likert',
  `options` text COLLATE utf8mb4_unicode_ci,
  `scoring_key` text COLLATE utf8mb4_unicode_ci,
  `reverse_scored` tinyint(1) NOT NULL DEFAULT '0',
  `sort_order` int(11) NOT NULL DEFAULT '100',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `uq_test_items_instrument_key` (`instrument_id`,`item_key`) USING BTREE,
  KEY `idx_test_items_instrument` (`instrument_id`,`sort_order`) USING BTREE,
  KEY `idx_test_items_scale` (`scale_id`) USING BTREE,
  CONSTRAINT `fk_test_items_instrument_id` FOREIGN KEY (`instrument_id`) REFERENCES `test_instruments` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_test_items_scale_id` FOREIGN KEY (`scale_id`) REFERENCES `test_scales` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=878 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `test_media_access_audit`
--

DROP TABLE IF EXISTS `test_media_access_audit`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `test_media_access_audit` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `session_id` int(10) unsigned NOT NULL,
  `evidence_id` bigint(20) unsigned DEFAULT NULL,
  `actor_user_id` int(10) unsigned NOT NULL,
  `action` enum('result_viewed','video_viewed','video_downloaded','screen_capture_viewed') COLLATE utf8mb4_unicode_ci NOT NULL,
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`) USING BTREE,
  KEY `idx_media_access_session_created` (`session_id`,`created_at`) USING BTREE,
  KEY `idx_media_access_actor_created` (`actor_user_id`,`created_at`) USING BTREE,
  KEY `fk_media_access_evidence` (`evidence_id`) USING BTREE,
  CONSTRAINT `fk_media_access_evidence` FOREIGN KEY (`evidence_id`) REFERENCES `test_media_evidence` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_media_access_session` FOREIGN KEY (`session_id`) REFERENCES `test_sessions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=9534 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `test_media_chunks`
--

DROP TABLE IF EXISTS `test_media_chunks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `test_media_chunks` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `evidence_id` bigint(20) unsigned NOT NULL,
  `chunk_number` int(10) unsigned NOT NULL,
  `storage_key` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `size_bytes` int(10) unsigned NOT NULL,
  `sha256` char(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `uploaded_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `uq_test_media_chunk` (`evidence_id`,`chunk_number`) USING BTREE,
  KEY `idx_test_media_chunks_evidence` (`evidence_id`,`chunk_number`) USING BTREE,
  CONSTRAINT `fk_test_media_chunks_evidence` FOREIGN KEY (`evidence_id`) REFERENCES `test_media_evidence` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=1028598 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `test_media_evidence`
--

DROP TABLE IF EXISTS `test_media_evidence`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `test_media_evidence` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `session_id` int(10) unsigned NOT NULL,
  `segment_number` int(10) unsigned NOT NULL DEFAULT '1',
  `instrument_id` int(10) unsigned NOT NULL,
  `user_id` int(10) unsigned NOT NULL,
  `status` enum('pending','recording','uploading','processing','saved','partial','failed','not_supported','not_consented') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `processing_status` enum('not_queued','queued','processing','completed','failed') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'not_queued',
  `storage_key` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `mime_type` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `file_size` bigint(20) unsigned DEFAULT NULL,
  `uploaded_bytes` bigint(20) unsigned NOT NULL DEFAULT '0',
  `duration_seconds` int(10) unsigned DEFAULT NULL,
  `sha256` char(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
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
  UNIQUE KEY `uq_test_media_evidence_session_segment` (`session_id`,`segment_number`) USING BTREE,
  KEY `idx_test_media_evidence_status` (`status`,`updated_at`) USING BTREE,
  KEY `fk_test_media_evidence_instrument` (`instrument_id`) USING BTREE,
  KEY `idx_test_media_evidence_session` (`session_id`) USING BTREE,
  CONSTRAINT `fk_test_media_evidence_instrument` FOREIGN KEY (`instrument_id`) REFERENCES `test_instruments` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_test_media_evidence_session` FOREIGN KEY (`session_id`) REFERENCES `test_sessions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=17498 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `test_media_processing_jobs`
--

DROP TABLE IF EXISTS `test_media_processing_jobs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `test_media_processing_jobs` (
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
  UNIQUE KEY `uq_test_media_processing_evidence` (`evidence_id`) USING BTREE,
  KEY `idx_test_media_processing_queue` (`status`,`attempts`,`locked_at`,`id`) USING BTREE,
  CONSTRAINT `fk_test_media_processing_evidence` FOREIGN KEY (`evidence_id`) REFERENCES `test_media_evidence` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=7645 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `test_norms`
--

DROP TABLE IF EXISTS `test_norms`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `test_norms` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `instrument_id` int(10) unsigned NOT NULL,
  `scale_id` int(10) unsigned DEFAULT NULL,
  `norm_group` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `score_source` enum('raw','adjusted','transformed') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'raw',
  `raw_min` decimal(10,4) DEFAULT NULL,
  `raw_max` decimal(10,4) DEFAULT NULL,
  `transformed_score` varchar(40) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `percentile` decimal(6,2) DEFAULT NULL,
  `interpretation` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`) USING BTREE,
  KEY `idx_test_norms_instrument` (`instrument_id`,`norm_group`) USING BTREE,
  KEY `idx_test_norms_scale` (`scale_id`) USING BTREE,
  KEY `idx_test_norms_lookup` (`instrument_id`,`scale_id`,`norm_group`) USING BTREE,
  CONSTRAINT `fk_test_norms_instrument_id` FOREIGN KEY (`instrument_id`) REFERENCES `test_instruments` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_test_norms_scale_id` FOREIGN KEY (`scale_id`) REFERENCES `test_scales` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=1506 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `test_process_assignable_profiles`
--

DROP TABLE IF EXISTS `test_process_assignable_profiles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `test_process_assignable_profiles` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `process_id` int(10) unsigned NOT NULL,
  `profile_id` int(10) unsigned NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `uq_test_process_assignable_profile` (`process_id`,`profile_id`) USING BTREE,
  KEY `idx_test_process_assignable_profiles_profile` (`profile_id`) USING BTREE,
  CONSTRAINT `fk_test_process_assignable_profiles_process_id` FOREIGN KEY (`process_id`) REFERENCES `test_processes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=258 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `test_process_evaluation_assignments`
--

DROP TABLE IF EXISTS `test_process_evaluation_assignments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `test_process_evaluation_assignments` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `process_id` int(10) unsigned NOT NULL,
  `form_id` int(10) unsigned NOT NULL,
  `user_id` int(10) unsigned NOT NULL,
  `status` enum('assigned','cancelled') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'assigned',
  `assigned_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `uq_process_evaluation_assignment` (`process_id`,`form_id`,`user_id`) USING BTREE,
  KEY `idx_process_evaluation_assignment_user` (`user_id`,`status`) USING BTREE,
  KEY `idx_process_evaluation_assignment_process` (`process_id`,`status`) USING BTREE,
  CONSTRAINT `fk_process_evaluation_assignment_process` FOREIGN KEY (`process_id`) REFERENCES `test_processes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=875 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `test_process_evaluation_forms`
--

DROP TABLE IF EXISTS `test_process_evaluation_forms`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `test_process_evaluation_forms` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `process_id` int(10) unsigned NOT NULL,
  `form_id` int(10) unsigned NOT NULL,
  `sort_order` int(11) NOT NULL DEFAULT '10',
  `is_required` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `uq_process_evaluation_form` (`process_id`,`form_id`) USING BTREE,
  KEY `idx_process_evaluation_forms_process` (`process_id`,`sort_order`) USING BTREE,
  KEY `idx_process_evaluation_forms_form` (`form_id`) USING BTREE,
  CONSTRAINT `fk_process_evaluation_forms_process` FOREIGN KEY (`process_id`) REFERENCES `test_processes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=65 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `test_process_instruments`
--

DROP TABLE IF EXISTS `test_process_instruments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `test_process_instruments` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `process_id` int(10) unsigned NOT NULL,
  `instrument_id` int(10) unsigned NOT NULL,
  `sort_order` int(11) NOT NULL DEFAULT '100',
  `is_required` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `uq_test_process_instrument` (`process_id`,`instrument_id`) USING BTREE,
  KEY `idx_test_process_instruments_instrument` (`instrument_id`) USING BTREE,
  CONSTRAINT `fk_test_process_instruments_instrument_id` FOREIGN KEY (`instrument_id`) REFERENCES `test_instruments` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_test_process_instruments_process_id` FOREIGN KEY (`process_id`) REFERENCES `test_processes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=757 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `test_process_profile_admins`
--

DROP TABLE IF EXISTS `test_process_profile_admins`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `test_process_profile_admins` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `process_id` int(10) unsigned NOT NULL,
  `profile_id` int(10) unsigned NOT NULL,
  `permissions` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `uq_test_process_profile` (`process_id`,`profile_id`) USING BTREE,
  KEY `idx_test_process_profiles_profile` (`profile_id`) USING BTREE,
  CONSTRAINT `fk_test_process_profiles_process_id` FOREIGN KEY (`process_id`) REFERENCES `test_processes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `test_process_user_admins`
--

DROP TABLE IF EXISTS `test_process_user_admins`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `test_process_user_admins` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `process_id` int(10) unsigned NOT NULL,
  `user_id` int(10) unsigned NOT NULL,
  `permissions` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `uq_test_process_user_admin` (`process_id`,`user_id`) USING BTREE,
  KEY `idx_test_process_user_admins_user` (`user_id`) USING BTREE,
  CONSTRAINT `fk_test_process_user_admins_process_id` FOREIGN KEY (`process_id`) REFERENCES `test_processes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=346 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `test_process_user_fields`
--

DROP TABLE IF EXISTS `test_process_user_fields`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `test_process_user_fields` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `process_id` int(10) unsigned NOT NULL,
  `field_id` int(10) unsigned NOT NULL,
  `is_required` tinyint(1) NOT NULL DEFAULT '0',
  `show_in_process` tinyint(1) NOT NULL DEFAULT '1',
  `sort_order` int(11) NOT NULL DEFAULT '100',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `uq_test_process_field` (`process_id`,`field_id`) USING BTREE,
  KEY `idx_test_process_fields_field` (`field_id`) USING BTREE,
  CONSTRAINT `fk_test_process_fields_process_id` FOREIGN KEY (`process_id`) REFERENCES `test_processes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `test_process_users`
--

DROP TABLE IF EXISTS `test_process_users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `test_process_users` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `process_id` int(10) unsigned NOT NULL,
  `user_id` int(10) unsigned NOT NULL,
  `status` enum('assigned','in_progress','completed','cancelled') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'assigned',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `uq_test_process_user` (`process_id`,`user_id`) USING BTREE,
  KEY `idx_test_process_users_user` (`user_id`,`status`) USING BTREE,
  KEY `idx_test_process_users_process_status_user` (`process_id`,`status`,`user_id`) USING BTREE,
  CONSTRAINT `fk_test_process_users_process_id` FOREIGN KEY (`process_id`) REFERENCES `test_processes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=10651 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `test_processes`
--

DROP TABLE IF EXISTS `test_processes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `test_processes` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int(10) unsigned DEFAULT NULL,
  `code` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(180) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `status` enum('draft','active','closed','cancelled') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'draft',
  `admin_assignment_mode` enum('user','profile') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'user',
  `availability_status` enum('scheduled','open_now','closed_now') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'scheduled',
  `availability_changed_at` datetime DEFAULT NULL,
  `availability_changed_by` int(10) unsigned DEFAULT NULL,
  `starts_at` datetime DEFAULT NULL,
  `ends_at` datetime DEFAULT NULL,
  `allow_expired_reopen` tinyint(1) NOT NULL DEFAULT '0',
  `created_by` int(10) unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `uq_test_processes_code` (`code`) USING BTREE,
  KEY `idx_test_processes_status` (`status`) USING BTREE,
  KEY `idx_test_processes_dates` (`starts_at`,`ends_at`) USING BTREE,
  KEY `idx_test_processes_company_status` (`company_id`,`status`) USING BTREE,
  CONSTRAINT `fk_test_processes_company` FOREIGN KEY (`company_id`) REFERENCES `e_talent_core`.`companies` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=111 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `test_ranking_company_assignments`
--

DROP TABLE IF EXISTS `test_ranking_company_assignments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `test_ranking_company_assignments` (
  `company_id` int(10) unsigned NOT NULL,
  `preset_id` bigint(20) unsigned NOT NULL,
  `assigned_by` int(10) unsigned DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `assigned_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`company_id`) USING BTREE,
  KEY `idx_trca_preset` (`preset_id`) USING BTREE,
  KEY `idx_trca_active_company` (`is_active`,`company_id`) USING BTREE,
  CONSTRAINT `fk_trca_preset` FOREIGN KEY (`preset_id`) REFERENCES `test_ranking_presets` (`id`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `test_ranking_presets`
--

DROP TABLE IF EXISTS `test_ranking_presets`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `test_ranking_presets` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `preset_key` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(160) COLLATE utf8mb4_unicode_ci NOT NULL,
  `config_json` mediumtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_official` tinyint(1) NOT NULL DEFAULT '0',
  `created_by` int(11) DEFAULT NULL,
  `updated_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `uq_test_ranking_presets_key` (`preset_key`) USING BTREE,
  KEY `idx_test_ranking_presets_official` (`is_official`) USING BTREE,
  KEY `idx_test_ranking_presets_name` (`name`) USING BTREE
) ENGINE=InnoDB AUTO_INCREMENT=15 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `test_ranking_snapshots`
--

DROP TABLE IF EXISTS `test_ranking_snapshots`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `test_ranking_snapshots` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `process_id` int(10) unsigned NOT NULL,
  `config_hash` char(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `source_fingerprint` char(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `rows_json` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `warnings_json` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `users_total` int(10) unsigned NOT NULL DEFAULT '0',
  `sessions_total` int(10) unsigned NOT NULL DEFAULT '0',
  `generated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `uq_test_ranking_snapshot` (`process_id`,`config_hash`) USING BTREE,
  KEY `idx_test_ranking_snapshot_fingerprint` (`process_id`,`source_fingerprint`) USING BTREE,
  CONSTRAINT `fk_test_ranking_snapshot_process` FOREIGN KEY (`process_id`) REFERENCES `test_processes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=269 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `test_reports`
--

DROP TABLE IF EXISTS `test_reports`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `test_reports` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `session_id` int(10) unsigned NOT NULL,
  `report_body` mediumtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `generated_by` int(10) unsigned DEFAULT NULL,
  `generated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `uq_test_reports_session` (`session_id`) USING BTREE,
  CONSTRAINT `fk_test_reports_session_id` FOREIGN KEY (`session_id`) REFERENCES `test_sessions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `test_scale_formula_terms`
--

DROP TABLE IF EXISTS `test_scale_formula_terms`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `test_scale_formula_terms` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `instrument_id` int(10) unsigned NOT NULL,
  `target_scale_id` int(10) unsigned NOT NULL,
  `source_scale_id` int(10) unsigned DEFAULT NULL,
  `term_type` enum('scale','constant') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'scale',
  `source_score` enum('raw','transformed') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'raw',
  `operation` enum('add','subtract') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'add',
  `weight` decimal(10,4) NOT NULL DEFAULT '1.0000',
  `constant_value` decimal(10,4) NOT NULL DEFAULT '0.0000',
  `sort_order` int(11) NOT NULL DEFAULT '100',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`) USING BTREE,
  KEY `idx_test_formula_terms_instrument` (`instrument_id`,`target_scale_id`) USING BTREE,
  KEY `idx_test_formula_terms_source` (`source_scale_id`) USING BTREE,
  KEY `fk_test_formula_terms_target_scale_id` (`target_scale_id`) USING BTREE,
  CONSTRAINT `fk_test_formula_terms_instrument_id` FOREIGN KEY (`instrument_id`) REFERENCES `test_instruments` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_test_formula_terms_source_scale_id` FOREIGN KEY (`source_scale_id`) REFERENCES `test_scales` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_test_formula_terms_target_scale_id` FOREIGN KEY (`target_scale_id`) REFERENCES `test_scales` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=229 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `test_scale_group_members`
--

DROP TABLE IF EXISTS `test_scale_group_members`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `test_scale_group_members` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `instrument_id` int(10) unsigned NOT NULL,
  `group_id` int(10) unsigned NOT NULL,
  `scale_id` int(10) unsigned NOT NULL,
  `sort_order` int(11) NOT NULL DEFAULT '100',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `uq_test_scale_group_member` (`group_id`,`scale_id`) USING BTREE,
  KEY `idx_test_scale_group_members_instrument` (`instrument_id`) USING BTREE,
  KEY `idx_test_scale_group_members_scale` (`scale_id`) USING BTREE,
  CONSTRAINT `fk_test_scale_group_members_group_id` FOREIGN KEY (`group_id`) REFERENCES `test_scale_groups` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_test_scale_group_members_instrument_id` FOREIGN KEY (`instrument_id`) REFERENCES `test_instruments` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_test_scale_group_members_scale_id` FOREIGN KEY (`scale_id`) REFERENCES `test_scales` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=83 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `test_scale_groups`
--

DROP TABLE IF EXISTS `test_scale_groups`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `test_scale_groups` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `instrument_id` int(10) unsigned NOT NULL,
  `group_key` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(160) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `sort_order` int(11) NOT NULL DEFAULT '100',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `uq_test_scale_groups_instrument_key` (`instrument_id`,`group_key`) USING BTREE,
  KEY `idx_test_scale_groups_instrument` (`instrument_id`,`sort_order`) USING BTREE,
  CONSTRAINT `fk_test_scale_groups_instrument_id` FOREIGN KEY (`instrument_id`) REFERENCES `test_instruments` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `test_scales`
--

DROP TABLE IF EXISTS `test_scales`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `test_scales` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `instrument_id` int(10) unsigned NOT NULL,
  `scale_key` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(160) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `scale_type` enum('primary','validity','derived','global') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'primary',
  `scoring_method` enum('sum','formula','manual') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'sum',
  `sort_order` int(11) NOT NULL DEFAULT '100',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `uq_test_scales_instrument_key` (`instrument_id`,`scale_key`) USING BTREE,
  KEY `idx_test_scales_type` (`instrument_id`,`scale_type`) USING BTREE,
  KEY `idx_test_scales_instrument` (`instrument_id`,`sort_order`) USING BTREE,
  CONSTRAINT `fk_test_scales_instrument_id` FOREIGN KEY (`instrument_id`) REFERENCES `test_instruments` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=203 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `test_score_adjustments`
--

DROP TABLE IF EXISTS `test_score_adjustments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `test_score_adjustments` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `instrument_id` int(10) unsigned NOT NULL,
  `target_scale_id` int(10) unsigned NOT NULL,
  `label` varchar(160) COLLATE utf8mb4_unicode_ci NOT NULL,
  `source_type` enum('user_field') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'user_field',
  `source_key` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL,
  `source_value_min` decimal(10,4) DEFAULT NULL,
  `source_value_max` decimal(10,4) DEFAULT NULL,
  `source_value_text` varchar(160) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `operation` enum('add','subtract','set') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'add',
  `adjustment_value` decimal(10,4) NOT NULL DEFAULT '0.0000',
  `target_score` enum('adjusted','raw','transformed') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'adjusted',
  `sort_order` int(11) NOT NULL DEFAULT '100',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`) USING BTREE,
  KEY `idx_test_score_adjustments_instrument` (`instrument_id`,`is_active`,`sort_order`) USING BTREE,
  KEY `idx_test_score_adjustments_scale` (`target_scale_id`) USING BTREE,
  CONSTRAINT `fk_test_score_adjustments_instrument_id` FOREIGN KEY (`instrument_id`) REFERENCES `test_instruments` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_test_score_adjustments_target_scale_id` FOREIGN KEY (`target_scale_id`) REFERENCES `test_scales` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=19 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `test_screen_captures`
--

DROP TABLE IF EXISTS `test_screen_captures`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `test_screen_captures` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `session_id` int(10) unsigned NOT NULL,
  `evidence_id` bigint(20) unsigned DEFAULT NULL,
  `capture_source` enum('screen','canvas') COLLATE utf8mb4_unicode_ci NOT NULL,
  `capture_number` int(10) unsigned NOT NULL,
  `item_id` int(10) unsigned DEFAULT NULL,
  `block_number` int(10) unsigned DEFAULT NULL,
  `event_type` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'periodic',
  `mime_type` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `storage_key` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `file_size` int(10) unsigned NOT NULL,
  `sha256` char(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `captured_at` datetime NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `uq_tsc_evidence_number` (`evidence_id`,`capture_number`) USING BTREE,
  KEY `idx_tsc_session_created` (`session_id`,`created_at`) USING BTREE,
  KEY `idx_tsc_evidence_created` (`evidence_id`,`created_at`) USING BTREE,
  CONSTRAINT `fk_tsc_evidence` FOREIGN KEY (`evidence_id`) REFERENCES `test_media_evidence` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_tsc_session` FOREIGN KEY (`session_id`) REFERENCES `test_sessions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=730681 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `test_session_rollups`
--

DROP TABLE IF EXISTS `test_session_rollups`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `test_session_rollups` (
  `session_id` int(10) unsigned NOT NULL,
  `process_id` int(10) unsigned DEFAULT NULL,
  `instrument_id` int(10) unsigned NOT NULL,
  `user_id` int(10) unsigned NOT NULL,
  `answers_count` int(10) unsigned NOT NULL DEFAULT '0',
  `answered_count` int(10) unsigned NOT NULL DEFAULT '0',
  `activity_events_total` int(10) unsigned NOT NULL DEFAULT '0',
  `activity_attention_total` int(10) unsigned NOT NULL DEFAULT '0',
  `activity_risk_total` int(10) unsigned NOT NULL DEFAULT '0',
  `last_activity_event_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`session_id`) USING BTREE,
  KEY `idx_test_session_rollups_process` (`process_id`,`session_id`) USING BTREE,
  KEY `idx_test_session_rollups_user` (`user_id`,`session_id`) USING BTREE,
  CONSTRAINT `fk_test_session_rollups_session` FOREIGN KEY (`session_id`) REFERENCES `test_sessions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `test_sessions`
--

DROP TABLE IF EXISTS `test_sessions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `test_sessions` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `process_id` int(10) unsigned DEFAULT NULL,
  `instrument_id` int(10) unsigned NOT NULL,
  `control_mode` enum('off','activity','supervised','supervised_audio_visual') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `audio_visual_policy` enum('pause','continue','block') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `audio_visual_upload_failure_policy` enum('continue','retry_once','block') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `audio_visual_interruption_policy` enum('continue','pause','block') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `audio_visual_voice_policy` enum('log','warn','pause') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `audio_visual_permission_policy` enum('continue','pause','block') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `audio_visual_quality_profile` enum('economical','standard','high') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_id` int(10) unsigned NOT NULL,
  `assigned_by` int(10) unsigned DEFAULT NULL,
  `status` enum('assigned','in_progress','completed','cancelled','expired') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'assigned',
  `started_at` datetime DEFAULT NULL,
  `completed_at` datetime DEFAULT NULL,
  `expires_at` datetime DEFAULT NULL,
  `paused_remaining_seconds` int(10) unsigned DEFAULT NULL,
  `reopened_duration_minutes` int(10) unsigned DEFAULT NULL,
  `last_seen_at` datetime DEFAULT NULL,
  `score_summary` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`) USING BTREE,
  KEY `idx_test_sessions_instrument` (`instrument_id`) USING BTREE,
  KEY `idx_test_sessions_user` (`user_id`,`status`) USING BTREE,
  KEY `idx_test_sessions_assigned_by` (`assigned_by`) USING BTREE,
  KEY `idx_test_sessions_user_created` (`user_id`,`status`,`created_at`) USING BTREE,
  KEY `idx_test_sessions_created` (`created_at`) USING BTREE,
  KEY `idx_test_sessions_process` (`process_id`,`status`) USING BTREE,
  KEY `idx_test_sessions_last_seen` (`status`,`last_seen_at`) USING BTREE,
  KEY `idx_test_sessions_assignment_lookup` (`instrument_id`,`user_id`,`status`,`process_id`) USING BTREE,
  KEY `idx_test_sessions_process_user_instrument` (`process_id`,`user_id`,`instrument_id`,`status`) USING BTREE,
  KEY `idx_test_sessions_control_mode` (`control_mode`,`status`) USING BTREE,
  CONSTRAINT `fk_test_sessions_instrument_id` FOREIGN KEY (`instrument_id`) REFERENCES `test_instruments` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=31898 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_general_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 DEFINER=`root`@`%`*/ /*!50003 TRIGGER `trg_test_sessions_rollup_ai` AFTER INSERT ON `test_sessions` FOR EACH ROW BEGIN
    INSERT INTO test_session_rollups (session_id, process_id, instrument_id, user_id)
    VALUES (NEW.id, NEW.process_id, NEW.instrument_id, NEW.user_id)
    ON DUPLICATE KEY UPDATE process_id = VALUES(process_id), instrument_id = VALUES(instrument_id), user_id = VALUES(user_id);
END */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_general_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 DEFINER=`root`@`%`*/ /*!50003 TRIGGER `trg_test_sessions_rollup_au` AFTER UPDATE ON `test_sessions` FOR EACH ROW BEGIN
    UPDATE test_session_rollups SET process_id = NEW.process_id, instrument_id = NEW.instrument_id, user_id = NEW.user_id WHERE session_id = NEW.id;
END */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;

--
-- Table structure for table `test_settings`
--

DROP TABLE IF EXISTS `test_settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `test_settings` (
  `setting_key` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `setting_value` text COLLATE utf8mb4_unicode_ci,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`setting_key`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping events for database 'e_talent_tests'
--

--
-- Dumping routines for database 'e_talent_tests'
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
