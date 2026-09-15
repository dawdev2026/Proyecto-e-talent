-- MySQL dump 10.13  Distrib 5.7.44, for Linux (x86_64)
--
-- Host: localhost    Database: e_talent_interviews
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
-- Current Database: `e_talent_interviews`
--

CREATE DATABASE /*!32312 IF NOT EXISTS*/ `e_talent_interviews` /*!40100 DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci */;

USE `e_talent_interviews`;

--
-- Table structure for table `interview_appointments`
--

DROP TABLE IF EXISTS `interview_appointments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `interview_appointments` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `process_id` int(10) unsigned NOT NULL,
  `candidate_user_id` int(10) unsigned NOT NULL,
  `moderator_user_id` int(10) unsigned NOT NULL,
  `test_process_id` int(10) unsigned DEFAULT NULL,
  `test_session_id` int(10) unsigned DEFAULT NULL,
  `scheduled_start_at` datetime NOT NULL,
  `scheduled_end_at` datetime NOT NULL,
  `manual_override` tinyint(1) NOT NULL DEFAULT '0',
  `daily_room_name` varchar(140) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `daily_room_url` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `meeting_status` enum('scheduled','waiting','in_progress','finished','cancelled') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'scheduled',
  `transcript_status` enum('idle','recording','processing','available','failed') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'idle',
  `transcript_text` mediumtext COLLATE utf8mb4_unicode_ci,
  `transcript_last_snapshot_at` datetime DEFAULT NULL,
  `moderator_brief_status` enum('pending','processing','ready','failed') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `moderator_brief_json` json DEFAULT NULL,
  `moderator_brief_error` text COLLATE utf8mb4_unicode_ci,
  `final_report_status` enum('not_started','pending','processing','ready','failed') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'not_started',
  `final_report_html` mediumtext COLLATE utf8mb4_unicode_ci,
  `final_report_json` json DEFAULT NULL,
  `final_report_error` text COLLATE utf8mb4_unicode_ci,
  `final_report_generated_at` datetime DEFAULT NULL,
  `finished_at` datetime DEFAULT NULL,
  `created_by` int(10) unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `uq_interview_appointment_process_candidate` (`process_id`,`candidate_user_id`) USING BTREE,
  KEY `idx_interview_appointments_process_start` (`process_id`,`scheduled_start_at`) USING BTREE,
  KEY `idx_interview_appointments_moderator_time` (`moderator_user_id`,`scheduled_start_at`,`scheduled_end_at`,`meeting_status`) USING BTREE,
  KEY `idx_interview_appointments_candidate_time` (`candidate_user_id`,`scheduled_start_at`,`scheduled_end_at`,`meeting_status`) USING BTREE,
  KEY `idx_interview_appointments_test_refs` (`test_process_id`,`test_session_id`) USING BTREE,
  CONSTRAINT `fk_interview_appointments_process` FOREIGN KEY (`process_id`) REFERENCES `interview_processes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `interview_documents`
--

DROP TABLE IF EXISTS `interview_documents`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `interview_documents` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `appointment_id` int(10) unsigned NOT NULL,
  `document_type` enum('performance','psychological','job_profile','resume','reference','other') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'other',
  `original_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `stored_name` varchar(180) COLLATE utf8mb4_unicode_ci NOT NULL,
  `storage_path` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL,
  `mime_type` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `file_size` bigint(20) unsigned NOT NULL,
  `sha256` char(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `extracted_text` mediumtext COLLATE utf8mb4_unicode_ci,
  `processing_status` enum('pending','ready','unsupported','failed') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `processing_error` text COLLATE utf8mb4_unicode_ci,
  `uploaded_by` int(10) unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`) USING BTREE,
  KEY `idx_interview_documents_appointment` (`appointment_id`,`created_at`) USING BTREE,
  KEY `idx_interview_documents_status` (`processing_status`,`created_at`) USING BTREE,
  KEY `idx_interview_documents_hash` (`sha256`) USING BTREE,
  CONSTRAINT `fk_interview_documents_appointment` FOREIGN KEY (`appointment_id`) REFERENCES `interview_appointments` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `interview_notes`
--

DROP TABLE IF EXISTS `interview_notes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `interview_notes` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `appointment_id` int(10) unsigned NOT NULL,
  `author_user_id` int(10) unsigned NOT NULL,
  `notes` mediumtext COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `uq_interview_notes_author` (`appointment_id`,`author_user_id`) USING BTREE,
  CONSTRAINT `fk_interview_notes_appointment` FOREIGN KEY (`appointment_id`) REFERENCES `interview_appointments` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `interview_processes`
--

DROP TABLE IF EXISTS `interview_processes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `interview_processes` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int(10) unsigned DEFAULT NULL,
  `name` varchar(180) COLLATE utf8mb4_unicode_ci NOT NULL,
  `interview_date` date NOT NULL,
  `starts_at` time NOT NULL DEFAULT '09:00:00',
  `slot_duration_minutes` smallint(5) unsigned NOT NULL DEFAULT '45',
  `break_minutes` smallint(5) unsigned NOT NULL DEFAULT '10',
  `moderator_user_id` int(10) unsigned NOT NULL,
  `status` enum('draft','scheduled','in_progress','closed','cancelled') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'draft',
  `created_by` int(10) unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`) USING BTREE,
  KEY `idx_interview_processes_date_status` (`interview_date`,`status`) USING BTREE,
  KEY `idx_interview_processes_moderator_date` (`moderator_user_id`,`interview_date`) USING BTREE,
  KEY `idx_interview_processes_company_date` (`company_id`,`interview_date`) USING BTREE,
  CONSTRAINT `fk_interview_processes_company` FOREIGN KEY (`company_id`) REFERENCES `e_talent_core`.`companies` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `interview_report_jobs`
--

DROP TABLE IF EXISTS `interview_report_jobs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `interview_report_jobs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `appointment_id` int(10) unsigned NOT NULL,
  `job_type` enum('moderator_brief','final_report') COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` enum('pending','processing','done','failed') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `attempts` smallint(5) unsigned NOT NULL DEFAULT '0',
  `locked_at` datetime DEFAULT NULL,
  `last_error` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`) USING BTREE,
  KEY `idx_interview_report_jobs_status` (`status`,`job_type`,`created_at`) USING BTREE,
  KEY `idx_interview_report_jobs_appointment` (`appointment_id`,`job_type`) USING BTREE,
  CONSTRAINT `fk_interview_report_jobs_appointment` FOREIGN KEY (`appointment_id`) REFERENCES `interview_appointments` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `interview_transcription_events`
--

DROP TABLE IF EXISTS `interview_transcription_events`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `interview_transcription_events` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `appointment_id` int(10) unsigned NOT NULL,
  `action` enum('start','snapshot','stop','failed') COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` enum('idle','recording','processing','available','failed') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'idle',
  `transcript_text` mediumtext COLLATE utf8mb4_unicode_ci,
  `message` text COLLATE utf8mb4_unicode_ci,
  `created_by` int(10) unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`) USING BTREE,
  KEY `idx_interview_transcription_events_appointment` (`appointment_id`,`created_at`) USING BTREE,
  CONSTRAINT `fk_interview_transcription_events_appointment` FOREIGN KEY (`appointment_id`) REFERENCES `interview_appointments` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping events for database 'e_talent_interviews'
--

--
-- Dumping routines for database 'e_talent_interviews'
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
