-- MySQL dump 10.13  Distrib 5.7.44, for Linux (x86_64)
--
-- Host: localhost    Database: e_talent_core
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
-- Current Database: `e_talent_core`
--

CREATE DATABASE /*!32312 IF NOT EXISTS*/ `e_talent_core` /*!40100 DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci */;

USE `e_talent_core`;

--
-- Table structure for table `companies`
--

DROP TABLE IF EXISTS `companies`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `companies` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(140) COLLATE utf8mb4_unicode_ci NOT NULL,
  `tax_id` varchar(40) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `url_prefix` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL,
  `verification_enabled` tinyint(1) NOT NULL DEFAULT '0',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `uq_companies_name` (`name`) USING BTREE,
  UNIQUE KEY `uq_companies_url_prefix` (`url_prefix`) USING BTREE
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `company_branding`
--

DROP TABLE IF EXISTS `company_branding`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `company_branding` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int(10) unsigned NOT NULL,
  `setting_key` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL,
  `setting_value` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `uq_company_branding_setting` (`company_id`,`setting_key`) USING BTREE,
  KEY `idx_company_branding_company` (`company_id`) USING BTREE,
  CONSTRAINT `fk_company_branding_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=116 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `company_mail_settings`
--

DROP TABLE IF EXISTS `company_mail_settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `company_mail_settings` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int(10) unsigned NOT NULL,
  `enabled` tinyint(1) NOT NULL DEFAULT '0',
  `host` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `port` smallint(5) unsigned NOT NULL DEFAULT '587',
  `encryption` varchar(16) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'starttls',
  `smtp_auth` tinyint(1) NOT NULL DEFAULT '1',
  `auth_type` varchar(16) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `username` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `password_ciphertext` text COLLATE utf8mb4_unicode_ci,
  `from_email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `from_name` varchar(160) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Metricatest',
  `reply_to_email` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `reply_to_name` varchar(160) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `timeout` smallint(5) unsigned NOT NULL DEFAULT '30',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `uq_company_mail_settings_company` (`company_id`) USING BTREE,
  CONSTRAINT `fk_company_mail_settings_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `login_verification_codes`
--

DROP TABLE IF EXISTS `login_verification_codes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `login_verification_codes` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned NOT NULL,
  `code_hash` char(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `expires_at` datetime NOT NULL,
  `attempts` tinyint(3) unsigned NOT NULL DEFAULT '0',
  `created_ip` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `used_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`) USING BTREE,
  KEY `idx_login_verification_user_latest` (`user_id`,`id`) USING BTREE,
  KEY `idx_login_verification_expiry` (`expires_at`) USING BTREE,
  CONSTRAINT `fk_login_verification_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=5253 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `password_reset_tokens`
--

DROP TABLE IF EXISTS `password_reset_tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `password_reset_tokens` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned NOT NULL,
  `token_hash` char(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `expires_at` datetime NOT NULL,
  `used_at` datetime DEFAULT NULL,
  `requested_ip` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `uq_password_reset_token_hash` (`token_hash`) USING BTREE,
  KEY `idx_password_reset_user_expires` (`user_id`,`expires_at`) USING BTREE,
  CONSTRAINT `fk_password_reset_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `platform_settings`
--

DROP TABLE IF EXISTS `platform_settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `platform_settings` (
  `setting_key` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `setting_value` text COLLATE utf8mb4_unicode_ci,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`setting_key`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `report_company_assignments`
--

DROP TABLE IF EXISTS `report_company_assignments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `report_company_assignments` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `report_id` bigint(20) unsigned NOT NULL,
  `company_id` int(10) unsigned NOT NULL,
  `assigned_by` int(10) unsigned DEFAULT NULL,
  `assigned_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `removed_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `uq_report_company_assignment` (`report_id`,`company_id`) USING BTREE,
  KEY `idx_report_company_assignments_company` (`company_id`,`removed_at`) USING BTREE,
  KEY `idx_report_company_assignments_report` (`report_id`,`removed_at`) USING BTREE,
  KEY `fk_report_company_assignments_assigned_by` (`assigned_by`) USING BTREE,
  CONSTRAINT `fk_report_company_assignments_assigned_by` FOREIGN KEY (`assigned_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_report_company_assignments_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_report_company_assignments_report` FOREIGN KEY (`report_id`) REFERENCES `report_definitions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `report_definition_version_functionalities`
--

DROP TABLE IF EXISTS `report_definition_version_functionalities`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `report_definition_version_functionalities` (
  `version_id` bigint(20) unsigned NOT NULL,
  `functionality_id` smallint(5) unsigned NOT NULL,
  PRIMARY KEY (`version_id`,`functionality_id`) USING BTREE,
  KEY `fk_report_version_functionality_functionality` (`functionality_id`) USING BTREE,
  CONSTRAINT `fk_report_version_functionality_functionality` FOREIGN KEY (`functionality_id`) REFERENCES `report_functionalities` (`id`),
  CONSTRAINT `fk_report_version_functionality_version` FOREIGN KEY (`version_id`) REFERENCES `report_definition_versions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `report_definition_versions`
--

DROP TABLE IF EXISTS `report_definition_versions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `report_definition_versions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `report_id` bigint(20) unsigned NOT NULL,
  `version` int(10) unsigned NOT NULL,
  `name` varchar(160) COLLATE utf8mb4_unicode_ci NOT NULL,
  `markdown_content` mediumtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `design_markdown_content` mediumtext COLLATE utf8mb4_unicode_ci,
  `source_filename` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `design_source_filename` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `content_sha256` char(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `design_content_sha256` char(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `interpreter_version` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '1.0',
  `design_interpreter_version` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `change_summary` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_by` int(10) unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `uq_report_definition_versions` (`report_id`,`version`) USING BTREE,
  KEY `idx_report_definition_versions_hash` (`content_sha256`) USING BTREE,
  KEY `fk_report_definition_versions_created_by` (`created_by`) USING BTREE,
  CONSTRAINT `fk_report_definition_versions_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_report_definition_versions_report` FOREIGN KEY (`report_id`) REFERENCES `report_definitions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `report_definitions`
--

DROP TABLE IF EXISTS `report_definitions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `report_definitions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `type_id` smallint(5) unsigned DEFAULT NULL,
  `name` varchar(160) COLLATE utf8mb4_unicode_ci NOT NULL,
  `slug` varchar(180) COLLATE utf8mb4_unicode_ci NOT NULL,
  `markdown_content` mediumtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `design_markdown_content` mediumtext COLLATE utf8mb4_unicode_ci,
  `source_filename` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `design_source_filename` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `content_sha256` char(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `design_content_sha256` char(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `version` int(10) unsigned NOT NULL DEFAULT '1',
  `interpreter_version` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '1.0',
  `design_interpreter_version` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` enum('draft','active','inactive') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'draft',
  `approval_status` enum('draft','review','approved','published','obsolete','inactive') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'draft',
  `created_by` int(10) unsigned DEFAULT NULL,
  `updated_by` int(10) unsigned DEFAULT NULL,
  `approved_by` int(10) unsigned DEFAULT NULL,
  `approved_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `uq_report_definitions_slug` (`slug`) USING BTREE,
  KEY `idx_report_definitions_status_name` (`status`,`name`) USING BTREE,
  KEY `idx_report_definitions_created_by` (`created_by`) USING BTREE,
  KEY `fk_report_definitions_updated_by` (`updated_by`) USING BTREE,
  KEY `idx_report_definitions_type_status` (`type_id`,`approval_status`) USING BTREE,
  KEY `fk_report_definitions_approved_by` (`approved_by`) USING BTREE,
  CONSTRAINT `fk_report_definitions_approved_by` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_report_definitions_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_report_definitions_type` FOREIGN KEY (`type_id`) REFERENCES `report_types` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_report_definitions_updated_by` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `report_execution_logs`
--

DROP TABLE IF EXISTS `report_execution_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `report_execution_logs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `report_id` bigint(20) unsigned NOT NULL,
  `report_version_id` bigint(20) unsigned DEFAULT NULL,
  `company_id` int(10) unsigned NOT NULL,
  `process_id` bigint(20) unsigned NOT NULL,
  `user_id` int(10) unsigned NOT NULL,
  `executed_by` int(10) unsigned DEFAULT NULL,
  `format` enum('html','pdf','screen','xlsx') COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` enum('started','completed','failed') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'started',
  `request_hash` char(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `error_code` varchar(80) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `error_message` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `started_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `completed_at` timestamp NULL DEFAULT NULL,
  `duration_ms` int(10) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`) USING BTREE,
  KEY `idx_report_execution_company_date` (`company_id`,`started_at`) USING BTREE,
  KEY `idx_report_execution_report_date` (`report_id`,`started_at`) USING BTREE,
  KEY `idx_report_execution_process_user` (`process_id`,`user_id`) USING BTREE,
  KEY `fk_report_execution_version` (`report_version_id`) USING BTREE,
  KEY `fk_report_execution_user` (`user_id`) USING BTREE,
  KEY `fk_report_execution_executed_by` (`executed_by`) USING BTREE,
  CONSTRAINT `fk_report_execution_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`),
  CONSTRAINT `fk_report_execution_executed_by` FOREIGN KEY (`executed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_report_execution_report` FOREIGN KEY (`report_id`) REFERENCES `report_definitions` (`id`),
  CONSTRAINT `fk_report_execution_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`),
  CONSTRAINT `fk_report_execution_version` FOREIGN KEY (`report_version_id`) REFERENCES `report_definition_versions` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `report_functionalities`
--

DROP TABLE IF EXISTS `report_functionalities`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `report_functionalities` (
  `id` smallint(5) unsigned NOT NULL AUTO_INCREMENT,
  `functionality_key` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `uq_report_functionalities_key` (`functionality_key`) USING BTREE,
  KEY `idx_report_functionalities_active` (`is_active`,`functionality_key`) USING BTREE
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `report_functionality_assignments`
--

DROP TABLE IF EXISTS `report_functionality_assignments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `report_functionality_assignments` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `report_id` bigint(20) unsigned NOT NULL,
  `functionality_id` smallint(5) unsigned NOT NULL,
  `assigned_by` int(10) unsigned DEFAULT NULL,
  `assigned_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `removed_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `uq_report_functionality_assignment` (`report_id`,`functionality_id`) USING BTREE,
  KEY `idx_report_functionality_lookup` (`functionality_id`,`removed_at`,`report_id`) USING BTREE,
  KEY `fk_report_functionality_user` (`assigned_by`) USING BTREE,
  CONSTRAINT `fk_report_functionality_functionality` FOREIGN KEY (`functionality_id`) REFERENCES `report_functionalities` (`id`),
  CONSTRAINT `fk_report_functionality_report` FOREIGN KEY (`report_id`) REFERENCES `report_definitions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_report_functionality_user` FOREIGN KEY (`assigned_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `report_generation_batch_items`
--

DROP TABLE IF EXISTS `report_generation_batch_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `report_generation_batch_items` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `batch_id` bigint(20) unsigned NOT NULL,
  `user_id` int(10) unsigned NOT NULL,
  `execution_id` bigint(20) unsigned DEFAULT NULL,
  `storage_key` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `mime_type` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `file_size` bigint(20) unsigned DEFAULT NULL,
  `status` enum('queued','processing','completed','failed') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'queued',
  `attempts` tinyint(3) unsigned NOT NULL DEFAULT '0',
  `locked_at` timestamp NULL DEFAULT NULL,
  `started_at` timestamp NULL DEFAULT NULL,
  `error_message` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `completed_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `uq_report_batch_user` (`batch_id`,`user_id`) USING BTREE,
  KEY `idx_report_batch_items_status` (`batch_id`,`status`) USING BTREE,
  KEY `fk_report_batch_items_user` (`user_id`) USING BTREE,
  KEY `fk_report_batch_items_execution` (`execution_id`) USING BTREE,
  KEY `idx_report_batch_items_queue` (`status`,`locked_at`,`id`) USING BTREE,
  CONSTRAINT `fk_report_batch_items_batch` FOREIGN KEY (`batch_id`) REFERENCES `report_generation_batches` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_report_batch_items_execution` FOREIGN KEY (`execution_id`) REFERENCES `report_execution_logs` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_report_batch_items_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `report_generation_batches`
--

DROP TABLE IF EXISTS `report_generation_batches`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `report_generation_batches` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `report_id` bigint(20) unsigned NOT NULL,
  `company_id` int(10) unsigned NOT NULL,
  `process_id` bigint(20) unsigned DEFAULT NULL,
  `requested_by` int(10) unsigned DEFAULT NULL,
  `format` enum('html','pdf','xlsx') COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` enum('queued','running','completed','failed','cancelled') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'queued',
  `total_items` int(10) unsigned NOT NULL DEFAULT '0',
  `completed_items` int(10) unsigned NOT NULL DEFAULT '0',
  `failed_items` int(10) unsigned NOT NULL DEFAULT '0',
  `storage_key` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `zip_filename` varchar(180) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `download_token` char(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `expires_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `started_at` timestamp NULL DEFAULT NULL,
  `completed_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `uq_report_batch_download_token` (`download_token`) USING BTREE,
  KEY `idx_report_batches_company_status` (`company_id`,`status`,`created_at`) USING BTREE,
  KEY `fk_report_batches_report` (`report_id`) USING BTREE,
  KEY `fk_report_batches_requested_by` (`requested_by`) USING BTREE,
  CONSTRAINT `fk_report_batches_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`),
  CONSTRAINT `fk_report_batches_report` FOREIGN KEY (`report_id`) REFERENCES `report_definitions` (`id`),
  CONSTRAINT `fk_report_batches_requested_by` FOREIGN KEY (`requested_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `report_source_catalog`
--

DROP TABLE IF EXISTS `report_source_catalog`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `report_source_catalog` (
  `id` smallint(5) unsigned NOT NULL AUTO_INCREMENT,
  `source_key` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `provider` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `method` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `sensitivity` enum('normal','personal','psychometric','restricted') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'personal',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `uq_report_source_catalog_key` (`source_key`) USING BTREE,
  KEY `idx_report_source_catalog_active` (`is_active`,`source_key`) USING BTREE
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `report_types`
--

DROP TABLE IF EXISTS `report_types`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `report_types` (
  `id` smallint(5) unsigned NOT NULL AUTO_INCREMENT,
  `type_key` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `uq_report_types_key` (`type_key`) USING BTREE,
  KEY `idx_report_types_active_name` (`is_active`,`name`) USING BTREE
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `role_profile_scopes`
--

DROP TABLE IF EXISTS `role_profile_scopes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `role_profile_scopes` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `profile_id` int(10) unsigned NOT NULL,
  `scope_type` enum('core','platform') COLLATE utf8mb4_unicode_ci NOT NULL,
  `scope_key` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL,
  `permissions` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `uq_role_profile_scopes_profile_scope` (`profile_id`,`scope_type`,`scope_key`) USING BTREE,
  KEY `idx_role_profile_scopes_scope` (`scope_type`,`scope_key`) USING BTREE,
  CONSTRAINT `fk_role_profile_scopes_profile_id` FOREIGN KEY (`profile_id`) REFERENCES `role_profiles` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=15 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `role_profiles`
--

DROP TABLE IF EXISTS `role_profiles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `role_profiles` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `role_key` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL,
  `scope_type` enum('core','platform') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'platform',
  `scope_key` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'core',
  `description` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `permissions` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `home_route` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'dashboard',
  `is_system` tinyint(1) NOT NULL DEFAULT '0',
  `is_default_requester` tinyint(1) NOT NULL DEFAULT '0',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `uq_role_profiles_name` (`name`) USING BTREE,
  UNIQUE KEY `uq_role_profiles_key` (`role_key`) USING BTREE,
  KEY `idx_role_profiles_scope` (`scope_type`,`scope_key`) USING BTREE,
  KEY `idx_role_profiles_default_requester` (`role_key`,`is_active`,`is_default_requester`) USING BTREE
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `user_field_definitions`
--

DROP TABLE IF EXISTS `user_field_definitions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `user_field_definitions` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int(10) unsigned NOT NULL,
  `scope_type` enum('core','platform') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'core',
  `scope_key` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'core',
  `field_key` varchar(60) COLLATE utf8mb4_unicode_ci NOT NULL,
  `label` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `field_type` enum('text','email','phone','number','date','select','textarea','checkbox') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'text',
  `validation_rule` enum('by_type','none','email','phone','integer','decimal','date','options','regex') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'by_type',
  `validation_pattern` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `validation_message` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `options` text COLLATE utf8mb4_unicode_ci,
  `help_text` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_required` tinyint(1) NOT NULL DEFAULT '0',
  `show_in_list` tinyint(1) NOT NULL DEFAULT '0',
  `sort_order` int(11) NOT NULL DEFAULT '100',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `uq_user_field_definitions_company_scope` (`company_id`,`scope_type`,`scope_key`,`field_key`) USING BTREE,
  KEY `idx_user_field_definitions_active` (`is_active`,`sort_order`) USING BTREE,
  KEY `idx_user_field_definitions_scope` (`scope_type`,`scope_key`,`is_active`,`sort_order`) USING BTREE,
  KEY `idx_user_fields_company_scope` (`company_id`,`scope_type`,`scope_key`,`is_active`) USING BTREE,
  CONSTRAINT `fk_user_fields_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=45 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `user_field_values`
--

DROP TABLE IF EXISTS `user_field_values`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `user_field_values` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned NOT NULL,
  `field_id` int(10) unsigned NOT NULL,
  `value` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `uq_user_field_values_user_field` (`user_id`,`field_id`) USING BTREE,
  KEY `idx_user_field_values_user_id` (`user_id`) USING BTREE,
  KEY `idx_user_field_values_field_id` (`field_id`) USING BTREE,
  CONSTRAINT `fk_user_field_values_field_id` FOREIGN KEY (`field_id`) REFERENCES `user_field_definitions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_user_field_values_user_id` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=33940 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `user_login_events`
--

DROP TABLE IF EXISTS `user_login_events`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `user_login_events` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned NOT NULL,
  `logged_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`) USING BTREE,
  KEY `idx_user_login_events_user_logged` (`user_id`,`logged_at`) USING BTREE,
  KEY `idx_user_login_events_logged_at` (`logged_at`) USING BTREE,
  CONSTRAINT `fk_user_login_events_user_id` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE NO ACTION
) ENGINE=InnoDB AUTO_INCREMENT=11451 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `users` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `rut` varchar(12) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `first_names` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `last_names` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `sex` enum('masculino','femenino','no_informado') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `birth_date` date DEFAULT NULL,
  `age` tinyint(3) unsigned NOT NULL,
  `name` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(160) COLLATE utf8mb4_unicode_ci NOT NULL,
  `password_hash` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `role` enum('admin','agente','usuario','company_admin') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'usuario',
  `profile_id` int(10) unsigned DEFAULT NULL,
  `company_id` int(10) unsigned DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `last_login_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `uq_users_company_rut` (`company_id`,`rut`) USING BTREE,
  UNIQUE KEY `uq_users_company_email` (`company_id`,`email`) USING BTREE,
  KEY `idx_users_profile_id` (`profile_id`) USING BTREE,
  KEY `idx_users_company_id` (`company_id`) USING BTREE,
  KEY `idx_users_active_role_name` (`is_active`,`role`,`name`) USING BTREE,
  KEY `idx_users_age` (`age`) USING BTREE,
  KEY `idx_users_last_login_at` (`last_login_at`) USING BTREE,
  KEY `idx_users_company_email_active` (`company_id`,`email`,`is_active`) USING BTREE,
  CONSTRAINT `fk_users_company_id` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_users_profile_id` FOREIGN KEY (`profile_id`) REFERENCES `role_profiles` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=8997 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping events for database 'e_talent_core'
--

--
-- Dumping routines for database 'e_talent_core'
--
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-09-15  1:40:34
