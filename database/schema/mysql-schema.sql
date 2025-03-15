/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;
DROP TABLE IF EXISTS `agent_prompt_directives`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `agent_prompt_directives` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `prompt_directive_id` bigint unsigned NOT NULL,
  `agent_id` bigint unsigned NOT NULL,
  `section` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'top',
  `position` int unsigned NOT NULL DEFAULT '0',
  `created_at` timestamp(3) NULL DEFAULT NULL,
  `updated_at` timestamp(3) NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `agent_prompt_directives_prompt_directive_id_foreign` (`prompt_directive_id`),
  KEY `agent_prompt_directives_agent_id_foreign` (`agent_id`),
  CONSTRAINT `agent_prompt_directives_agent_id_foreign` FOREIGN KEY (`agent_id`) REFERENCES `agents` (`id`) ON DELETE CASCADE,
  CONSTRAINT `agent_prompt_directives_prompt_directive_id_foreign` FOREIGN KEY (`prompt_directive_id`) REFERENCES `prompt_directives` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `agent_thread_messageables`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `agent_thread_messageables` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `agent_thread_message_id` bigint unsigned NOT NULL,
  `messageable_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `messageable_id` bigint unsigned NOT NULL,
  `created_at` timestamp(3) NULL DEFAULT NULL,
  `updated_at` timestamp(3) NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `messageables_message_id_foreign` (`agent_thread_message_id`),
  KEY `messageables_messageable_type_messageable_id_index` (`messageable_type`,`messageable_id`),
  CONSTRAINT `messageables_message_id_foreign` FOREIGN KEY (`agent_thread_message_id`) REFERENCES `agent_thread_messages` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `agent_thread_messages`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `agent_thread_messages` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `agent_thread_id` bigint unsigned NOT NULL,
  `role` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `title` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `summary` text COLLATE utf8mb4_unicode_ci,
  `summarizer_offset` int NOT NULL DEFAULT '0',
  `summarizer_total` int NOT NULL DEFAULT '0',
  `content` text COLLATE utf8mb4_unicode_ci,
  `data` json DEFAULT NULL,
  `created_at` timestamp(3) NULL DEFAULT NULL,
  `updated_at` timestamp(3) NULL DEFAULT NULL,
  `deleted_at` timestamp(3) NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `messages_thread_id_foreign` (`agent_thread_id`),
  CONSTRAINT `messages_thread_id_foreign` FOREIGN KEY (`agent_thread_id`) REFERENCES `agent_threads` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `agent_thread_runs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `agent_thread_runs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `agent_thread_id` bigint unsigned NOT NULL,
  `last_message_id` bigint unsigned DEFAULT NULL,
  `job_dispatch_id` bigint unsigned DEFAULT NULL,
  `status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `temperature` decimal(5,2) DEFAULT NULL,
  `tools` json DEFAULT NULL,
  `tool_choice` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'auto',
  `response_format` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'text',
  `response_schema_id` bigint unsigned DEFAULT NULL,
  `response_fragment_id` bigint unsigned DEFAULT NULL,
  `seed` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `started_at` datetime DEFAULT NULL,
  `completed_at` datetime DEFAULT NULL,
  `failed_at` datetime DEFAULT NULL,
  `refreshed_at` datetime DEFAULT NULL,
  `agent_model` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `total_cost` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `input_tokens` bigint unsigned NOT NULL DEFAULT '0',
  `output_tokens` bigint unsigned NOT NULL DEFAULT '0',
  `created_at` timestamp(3) NULL DEFAULT NULL,
  `updated_at` timestamp(3) NULL DEFAULT NULL,
  `deleted_at` timestamp(3) NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `thread_runs_thread_id_foreign` (`agent_thread_id`),
  KEY `thread_runs_last_message_id_foreign` (`last_message_id`),
  KEY `thread_runs_job_dispatch_id_foreign` (`job_dispatch_id`),
  CONSTRAINT `thread_runs_job_dispatch_id_foreign` FOREIGN KEY (`job_dispatch_id`) REFERENCES `job_dispatch` (`id`),
  CONSTRAINT `thread_runs_last_message_id_foreign` FOREIGN KEY (`last_message_id`) REFERENCES `agent_thread_messages` (`id`),
  CONSTRAINT `thread_runs_thread_id_foreign` FOREIGN KEY (`agent_thread_id`) REFERENCES `agent_threads` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `agent_threads`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `agent_threads` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `team_id` bigint unsigned NOT NULL,
  `user_id` bigint unsigned NOT NULL,
  `agent_id` bigint unsigned NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `summary` varchar(512) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `created_at` timestamp(3) NULL DEFAULT NULL,
  `updated_at` timestamp(3) NULL DEFAULT NULL,
  `deleted_at` timestamp(3) NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `threads_team_id_foreign` (`team_id`),
  KEY `threads_user_id_foreign` (`user_id`),
  KEY `threads_agent_id_foreign` (`agent_id`),
  CONSTRAINT `threads_agent_id_foreign` FOREIGN KEY (`agent_id`) REFERENCES `agents` (`id`),
  CONSTRAINT `threads_team_id_foreign` FOREIGN KEY (`team_id`) REFERENCES `teams` (`id`),
  CONSTRAINT `threads_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `agents`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `agents` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `team_id` bigint unsigned NOT NULL,
  `knowledge_id` bigint unsigned DEFAULT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` varchar(1024) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `api` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `model` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `temperature` decimal(5,2) NOT NULL DEFAULT '0.00',
  `tools` json DEFAULT NULL,
  `response_format` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'text',
  `response_schema_id` bigint unsigned DEFAULT NULL,
  `response_schema_fragment_id` bigint unsigned DEFAULT NULL,
  `retry_count` int unsigned NOT NULL DEFAULT '0',
  `threads_count` int unsigned NOT NULL DEFAULT '0',
  `created_at` timestamp(3) NULL DEFAULT NULL,
  `updated_at` timestamp(3) NULL DEFAULT NULL,
  `deleted_at` timestamp(3) NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `agents_knowledge_id_foreign` (`knowledge_id`),
  KEY `agents_team_id_foreign` (`team_id`),
  KEY `agents_response_schema_id_foreign` (`response_schema_id`),
  KEY `agents_response_schema_fragment_id_foreign` (`response_schema_fragment_id`),
  CONSTRAINT `agents_knowledge_id_foreign` FOREIGN KEY (`knowledge_id`) REFERENCES `knowledge` (`id`),
  CONSTRAINT `agents_response_schema_fragment_id_foreign` FOREIGN KEY (`response_schema_fragment_id`) REFERENCES `schema_fragments` (`id`),
  CONSTRAINT `agents_response_schema_id_foreign` FOREIGN KEY (`response_schema_id`) REFERENCES `schema_definitions` (`id`),
  CONSTRAINT `agents_team_id_foreign` FOREIGN KEY (`team_id`) REFERENCES `teams` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `api_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `api_logs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `audit_request_id` int unsigned DEFAULT NULL,
  `user_id` int unsigned DEFAULT NULL,
  `api_class` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `service_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `status_code` int unsigned NOT NULL,
  `method` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `url` varchar(512) COLLATE utf8mb4_unicode_ci NOT NULL,
  `full_url` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `request` json DEFAULT NULL,
  `response` json DEFAULT NULL,
  `request_headers` json DEFAULT NULL,
  `response_headers` json DEFAULT NULL,
  `stack_trace` json DEFAULT NULL,
  `created_at` timestamp(3) NULL DEFAULT NULL,
  `updated_at` timestamp(3) NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `api_logs_api_class_status_code_method_index` (`api_class`,`status_code`,`method`),
  KEY `api_logs_service_name_status_code_method_index` (`service_name`,`status_code`,`method`),
  KEY `api_logs_user_id_index` (`user_id`),
  KEY `api_logs_url_index` (`url`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `artifactables`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `artifactables` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `artifact_id` bigint unsigned NOT NULL,
  `artifactable_id` int unsigned NOT NULL,
  `artifactable_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `category` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `created_at` timestamp(3) NULL DEFAULT NULL,
  `updated_at` timestamp(3) NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `artifactables_artifact_id_foreign` (`artifact_id`),
  KEY `artifactables_artifactable_index` (`artifactable_id`,`artifactable_type`),
  CONSTRAINT `artifactables_artifact_id_foreign` FOREIGN KEY (`artifact_id`) REFERENCES `artifacts` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `artifacts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `artifacts` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `model` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `text_content` text COLLATE utf8mb4_unicode_ci,
  `json_content` json DEFAULT NULL,
  `meta` json DEFAULT NULL,
  `created_at` timestamp(3) NULL DEFAULT NULL,
  `updated_at` timestamp(3) NULL DEFAULT NULL,
  `deleted_at` timestamp(3) NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `audit_request`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `audit_request` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `session_id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` int unsigned DEFAULT NULL,
  `environment` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `url` varchar(512) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `request` json NOT NULL,
  `response` json DEFAULT NULL,
  `logs` text COLLATE utf8mb4_unicode_ci,
  `profile` text COLLATE utf8mb4_unicode_ci,
  `time` double NOT NULL,
  `created_at` timestamp(3) NULL DEFAULT NULL,
  `updated_at` timestamp(3) NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `audit_request_session_id_index` (`session_id`),
  KEY `audit_request_user_id_index` (`user_id`),
  KEY `audit_request_environment_index` (`environment`),
  KEY `audit_request_url_index` (`url`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `audits`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `audits` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `audit_request_id` bigint unsigned NOT NULL,
  `user_id` bigint unsigned DEFAULT NULL,
  `event` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `auditable_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `auditable_id` char(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `old_values` json NOT NULL,
  `new_values` json NOT NULL,
  `tags` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp(3) NULL DEFAULT NULL,
  `updated_at` timestamp(3) NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `audits_auditable_type_auditable_id_index` (`auditable_type`,`auditable_id`),
  KEY `audits_user_id_index` (`user_id`),
  KEY `audits_audit_request_id_foreign` (`audit_request_id`),
  CONSTRAINT `audits_audit_request_id_foreign` FOREIGN KEY (`audit_request_id`) REFERENCES `audit_request` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `cache`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `cache` (
  `key` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `value` mediumtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `expiration` int NOT NULL,
  PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `cache_locks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `cache_locks` (
  `key` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `owner` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `expiration` int NOT NULL,
  PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `content_sources`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `content_sources` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `team_id` bigint unsigned NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `url` varchar(2048) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `config` json DEFAULT NULL,
  `polling_interval` int unsigned NOT NULL DEFAULT '60' COMMENT 'in minutes',
  `last_checkpoint` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'The most recent ordered identifier / timestamp / indicator from the source',
  `fetched_at` timestamp NULL DEFAULT NULL,
  `workflow_inputs_count` int unsigned NOT NULL DEFAULT '0',
  `created_at` timestamp(3) NULL DEFAULT NULL,
  `updated_at` timestamp(3) NULL DEFAULT NULL,
  `deleted_at` timestamp(3) NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `content_sources_team_id_name_unique` (`team_id`,`name`),
  CONSTRAINT `content_sources_team_id_foreign` FOREIGN KEY (`team_id`) REFERENCES `teams` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `error_log_entry`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `error_log_entry` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `error_log_id` bigint unsigned NOT NULL,
  `audit_request_id` bigint unsigned DEFAULT NULL,
  `user_id` int unsigned DEFAULT NULL,
  `message` varchar(512) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `full_message` longtext COLLATE utf8mb4_unicode_ci,
  `data` json DEFAULT NULL,
  `created_at` timestamp(3) NULL DEFAULT NULL,
  `updated_at` timestamp(3) NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `error_log_entry_user_id_created_at_index` (`user_id`,`created_at`),
  KEY `error_log_entry_created_at_index` (`created_at`),
  KEY `error_log_entry_audit_request_id_foreign` (`audit_request_id`),
  KEY `error_log_entry_error_log_id_foreign` (`error_log_id`),
  CONSTRAINT `error_log_entry_audit_request_id_foreign` FOREIGN KEY (`audit_request_id`) REFERENCES `audit_request` (`id`),
  CONSTRAINT `error_log_entry_error_log_id_foreign` FOREIGN KEY (`error_log_id`) REFERENCES `error_logs` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `error_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `error_logs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `root_id` bigint unsigned DEFAULT NULL,
  `parent_id` bigint unsigned DEFAULT NULL,
  `hash` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `error_class` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `code` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `level` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `message` varchar(512) COLLATE utf8mb4_unicode_ci NOT NULL,
  `file` varchar(512) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `line` int unsigned DEFAULT NULL,
  `count` int unsigned NOT NULL,
  `last_seen_at` datetime NOT NULL,
  `last_notified_at` datetime DEFAULT NULL,
  `send_notifications` tinyint(1) NOT NULL DEFAULT '1',
  `stack_trace` json DEFAULT NULL,
  `created_at` timestamp(3) NULL DEFAULT NULL,
  `updated_at` timestamp(3) NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `error_logs_hash_unique` (`hash`),
  KEY `error_logs_level_code_error_class_index` (`level`,`code`,`error_class`),
  KEY `error_logs_error_class_index` (`error_class`),
  KEY `error_logs_message_index` (`message`),
  KEY `error_logs_parent_id_foreign` (`parent_id`),
  KEY `error_logs_root_id_foreign` (`root_id`),
  CONSTRAINT `error_logs_parent_id_foreign` FOREIGN KEY (`parent_id`) REFERENCES `error_logs` (`id`),
  CONSTRAINT `error_logs_root_id_foreign` FOREIGN KEY (`root_id`) REFERENCES `error_logs` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `job_batches`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `job_batches` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `total_jobs` int NOT NULL,
  `pending_jobs` int NOT NULL,
  `failed_jobs` int NOT NULL,
  `failed_job_ids` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `options` mediumtext COLLATE utf8mb4_unicode_ci,
  `on_complete` text COLLATE utf8mb4_unicode_ci,
  `cancelled_at` int DEFAULT NULL,
  `created_at` timestamp(3) NULL DEFAULT NULL,
  `finished_at` int DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `job_dispatch`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `job_dispatch` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned DEFAULT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ref` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `job_batch_id` bigint unsigned DEFAULT NULL,
  `running_audit_request_id` int unsigned DEFAULT NULL,
  `dispatch_audit_request_id` int unsigned DEFAULT NULL,
  `status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `ran_at` datetime DEFAULT NULL,
  `completed_at` datetime DEFAULT NULL,
  `timeout_at` datetime DEFAULT NULL,
  `run_time` int GENERATED ALWAYS AS (timestampdiff(SECOND,`ran_at`,`completed_at`)) STORED,
  `count` int unsigned NOT NULL,
  `data` json DEFAULT NULL,
  `created_at` timestamp(3) NULL DEFAULT NULL,
  `updated_at` timestamp(3) NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `category` (`name`),
  KEY `job_id` (`ref`),
  KEY `job_dispatch_job_batch_id_foreign` (`job_batch_id`),
  CONSTRAINT `job_dispatch_job_batch_id_foreign` FOREIGN KEY (`job_batch_id`) REFERENCES `job_batches` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `job_dispatchables`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `job_dispatchables` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `category` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `job_dispatch_id` bigint unsigned NOT NULL,
  `model_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `model_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp(3) NULL DEFAULT NULL,
  `updated_at` timestamp(3) NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `job_dispatchables_job_dispatch_id_foreign` (`job_dispatch_id`),
  KEY `job_dispatchables_model_type_model_id_index` (`model_type`,`model_id`),
  CONSTRAINT `job_dispatchables_job_dispatch_id_foreign` FOREIGN KEY (`job_dispatch_id`) REFERENCES `job_dispatch` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `knowledge`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `knowledge` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `team_id` bigint unsigned NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` varchar(1024) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp(3) NULL DEFAULT NULL,
  `updated_at` timestamp(3) NULL DEFAULT NULL,
  `deleted_at` timestamp(3) NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `knowledge_team_id_name_unique` (`team_id`,`name`),
  CONSTRAINT `knowledge_team_id_foreign` FOREIGN KEY (`team_id`) REFERENCES `teams` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `migrations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `migrations` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `migration` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `batch` int NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `object_tag_taggables`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `object_tag_taggables` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `object_tag_id` bigint unsigned NOT NULL,
  `taggable_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `taggable_id` bigint unsigned NOT NULL,
  `created_at` timestamp(3) NULL DEFAULT NULL,
  `updated_at` timestamp(3) NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `object_tag_taggables_unique` (`object_tag_id`,`taggable_id`,`taggable_type`),
  KEY `object_tag_taggables_taggable_type_taggable_id_index` (`taggable_type`,`taggable_id`),
  CONSTRAINT `object_tag_taggables_object_tag_id_foreign` FOREIGN KEY (`object_tag_id`) REFERENCES `object_tags` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `object_tags`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `object_tags` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `category` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp(3) NULL DEFAULT NULL,
  `updated_at` timestamp(3) NULL DEFAULT NULL,
  `deleted_at` timestamp(3) NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `object_tags_category_name_unique` (`category`,`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `on-demands__object_attribute_sources`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `on-demands__object_attribute_sources` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `object_attribute_id` bigint unsigned NOT NULL,
  `source_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `source_id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `explanation` text COLLATE utf8mb4_unicode_ci,
  `stored_file_id` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `agent_thread_message_id` bigint unsigned DEFAULT NULL,
  `created_at` timestamp(3) NULL DEFAULT NULL,
  `updated_at` timestamp(3) NULL DEFAULT NULL,
  `deleted_at` timestamp(3) NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_on-demands__object_attribute_sources_object_attribute_id` (`object_attribute_id`),
  KEY `fk_on-demands__object_attribute_sources_stored_file_id` (`stored_file_id`),
  KEY `fk_on-demands__object_attribute_sources_agent_thread_message_id` (`agent_thread_message_id`),
  CONSTRAINT `fk_on-demands__object_attribute_sources_agent_thread_message_id` FOREIGN KEY (`agent_thread_message_id`) REFERENCES `agent_thread_messages` (`id`),
  CONSTRAINT `fk_on-demands__object_attribute_sources_object_attribute_id` FOREIGN KEY (`object_attribute_id`) REFERENCES `on-demands__object_attributes` (`id`),
  CONSTRAINT `fk_on-demands__object_attribute_sources_stored_file_id` FOREIGN KEY (`stored_file_id`) REFERENCES `stored_files` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `on-demands__object_attributes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `on-demands__object_attributes` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `object_id` bigint unsigned NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `date` datetime DEFAULT NULL,
  `text_value` text COLLATE utf8mb4_unicode_ci,
  `json_value` json DEFAULT NULL,
  `reason` text COLLATE utf8mb4_unicode_ci,
  `confidence` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `agent_thread_run_id` bigint unsigned DEFAULT NULL,
  `created_at` timestamp(3) NULL DEFAULT NULL,
  `updated_at` timestamp(3) NULL DEFAULT NULL,
  `deleted_at` timestamp(3) NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_object_attribute_name_field` (`object_id`,`name`,`date`),
  KEY `fk_on-demands__object_attributes_agent_thread_run_id` (`agent_thread_run_id`),
  CONSTRAINT `fk_on-demands__object_attributes_agent_thread_run_id` FOREIGN KEY (`agent_thread_run_id`) REFERENCES `agent_thread_runs` (`id`),
  CONSTRAINT `fk_on-demands__object_attributes_object_id` FOREIGN KEY (`object_id`) REFERENCES `on-demands__objects` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `on-demands__object_relationships`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `on-demands__object_relationships` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `relationship_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `object_id` bigint unsigned NOT NULL,
  `related_object_id` bigint unsigned NOT NULL,
  `created_at` timestamp(3) NULL DEFAULT NULL,
  `updated_at` timestamp(3) NULL DEFAULT NULL,
  `deleted_at` timestamp(3) NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_related_object_field` (`object_id`,`relationship_name`,`related_object_id`),
  KEY `fk_on-demands__object_relationships_related_object_id` (`related_object_id`),
  CONSTRAINT `fk_on-demands__object_relationships_object_id` FOREIGN KEY (`object_id`) REFERENCES `on-demands__objects` (`id`),
  CONSTRAINT `fk_on-demands__object_relationships_related_object_id` FOREIGN KEY (`related_object_id`) REFERENCES `on-demands__objects` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `on-demands__objects`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `on-demands__objects` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `schema_definition_id` bigint unsigned DEFAULT NULL,
  `root_object_id` bigint unsigned DEFAULT NULL,
  `type` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `date` datetime DEFAULT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `url` varchar(2048) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `meta` json DEFAULT NULL,
  `created_at` timestamp(3) NULL DEFAULT NULL,
  `updated_at` timestamp(3) NULL DEFAULT NULL,
  `deleted_at` timestamp(3) NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_on-demands__objects_root_object_id` (`root_object_id`),
  KEY `index_object_type_name` (`type`,`name`),
  KEY `fk_on-demands__objects_schema_definition_id` (`schema_definition_id`),
  CONSTRAINT `fk_on-demands__objects_root_object_id` FOREIGN KEY (`root_object_id`) REFERENCES `on-demands__objects` (`id`),
  CONSTRAINT `fk_on-demands__objects_schema_definition_id` FOREIGN KEY (`schema_definition_id`) REFERENCES `schema_definitions` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `password_reset_tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `password_reset_tokens` (
  `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `token` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp(3) NULL DEFAULT NULL,
  PRIMARY KEY (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `personal_access_tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `personal_access_tokens` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `tokenable_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `tokenable_id` bigint unsigned NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `token` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `abilities` text COLLATE utf8mb4_unicode_ci,
  `last_used_at` timestamp NULL DEFAULT NULL,
  `expires_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp(3) NULL DEFAULT NULL,
  `updated_at` timestamp(3) NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `personal_access_tokens_token_unique` (`token`),
  KEY `personal_access_tokens_tokenable_type_tokenable_id_index` (`tokenable_type`,`tokenable_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `prompt_directives`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `prompt_directives` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `team_id` bigint unsigned NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `directive_text` text COLLATE utf8mb4_unicode_ci,
  `agents_count` int unsigned NOT NULL DEFAULT '0',
  `created_at` timestamp(3) NULL DEFAULT NULL,
  `updated_at` timestamp(3) NULL DEFAULT NULL,
  `deleted_at` timestamp(3) NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `prompt_directives_team_id_foreign` (`team_id`),
  CONSTRAINT `prompt_directives_team_id_foreign` FOREIGN KEY (`team_id`) REFERENCES `teams` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `schema_associations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `schema_associations` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `schema_definition_id` bigint unsigned NOT NULL,
  `schema_fragment_id` bigint unsigned DEFAULT NULL,
  `object_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `object_id` bigint unsigned NOT NULL,
  `category` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `created_at` timestamp(3) NULL DEFAULT NULL,
  `updated_at` timestamp(3) NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `schema_associations_schema_definition_id_foreign` (`schema_definition_id`),
  KEY `schema_associations_schema_fragment_id_foreign` (`schema_fragment_id`),
  KEY `schema_associations_object_type_object_id_index` (`object_type`,`object_id`),
  CONSTRAINT `schema_associations_schema_definition_id_foreign` FOREIGN KEY (`schema_definition_id`) REFERENCES `schema_definitions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `schema_associations_schema_fragment_id_foreign` FOREIGN KEY (`schema_fragment_id`) REFERENCES `schema_fragments` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `schema_definitions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `schema_definitions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `team_id` bigint unsigned NOT NULL,
  `type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `schema_format` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `schema` json DEFAULT NULL,
  `response_example` json DEFAULT NULL,
  `agents_count` int unsigned NOT NULL DEFAULT '0',
  `fragments_count` int unsigned NOT NULL DEFAULT '0',
  `associations_count` int unsigned NOT NULL DEFAULT '0',
  `created_at` timestamp(3) NULL DEFAULT NULL,
  `updated_at` timestamp(3) NULL DEFAULT NULL,
  `deleted_at` timestamp(3) NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `prompt_schemas_team_id_foreign` (`team_id`),
  CONSTRAINT `prompt_schemas_team_id_foreign` FOREIGN KEY (`team_id`) REFERENCES `teams` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `schema_fragments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `schema_fragments` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `schema_definition_id` bigint unsigned NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `fragment_selector` json NOT NULL,
  `associations_count` int unsigned NOT NULL DEFAULT '0',
  `created_at` timestamp(3) NULL DEFAULT NULL,
  `updated_at` timestamp(3) NULL DEFAULT NULL,
  `deleted_at` timestamp(3) NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `prompt_schema_fragments_prompt_schema_id_foreign` (`schema_definition_id`),
  CONSTRAINT `prompt_schema_fragments_prompt_schema_id_foreign` FOREIGN KEY (`schema_definition_id`) REFERENCES `schema_definitions` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `schema_history`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `schema_history` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `schema_definition_id` bigint unsigned NOT NULL,
  `user_id` bigint unsigned NOT NULL,
  `schema` json NOT NULL,
  `created_at` timestamp(3) NULL DEFAULT NULL,
  `updated_at` timestamp(3) NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `prompt_schema_history_prompt_schema_id_foreign` (`schema_definition_id`),
  KEY `prompt_schema_history_user_id_foreign` (`user_id`),
  CONSTRAINT `prompt_schema_history_prompt_schema_id_foreign` FOREIGN KEY (`schema_definition_id`) REFERENCES `schema_definitions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `prompt_schema_history_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `sessions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `sessions` (
  `id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` bigint unsigned DEFAULT NULL,
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent` text COLLATE utf8mb4_unicode_ci,
  `payload` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `last_activity` int NOT NULL,
  PRIMARY KEY (`id`),
  KEY `sessions_user_id_index` (`user_id`),
  KEY `sessions_last_activity_index` (`last_activity`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `stored_file_storables`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `stored_file_storables` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `stored_file_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `storable_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `storable_id` bigint unsigned NOT NULL,
  `category` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp(3) NULL DEFAULT NULL,
  `updated_at` timestamp(3) NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `stored_file_storables_unique` (`storable_id`,`storable_type`,`stored_file_id`),
  KEY `stored_file_storables_storable_type_storable_id_index` (`storable_type`,`storable_id`),
  KEY `stored_file_storables_stored_file_id_foreign` (`stored_file_id`),
  CONSTRAINT `stored_file_storables_stored_file_id_foreign` FOREIGN KEY (`stored_file_id`) REFERENCES `stored_files` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `stored_files`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `stored_files` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `disk` varchar(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `filepath` varchar(768) COLLATE utf8mb4_unicode_ci NOT NULL,
  `filename` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `url` varchar(512) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `mime` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `size` int unsigned NOT NULL DEFAULT '0',
  `exif` json DEFAULT NULL,
  `meta` json DEFAULT NULL,
  `location` json DEFAULT NULL,
  `page_number` int unsigned DEFAULT NULL,
  `transcode_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `original_stored_file_id` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp(3) NULL DEFAULT NULL,
  `updated_at` timestamp(3) NULL DEFAULT NULL,
  `deleted_at` timestamp(3) NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `stored_files_original_stored_file_id_foreign` (`original_stored_file_id`),
  KEY `file_filepath_index` (`filepath`),
  CONSTRAINT `stored_files_original_stored_file_id_foreign` FOREIGN KEY (`original_stored_file_id`) REFERENCES `stored_files` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `task_definition_agents`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `task_definition_agents` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `task_definition_id` bigint unsigned NOT NULL,
  `agent_id` bigint unsigned NOT NULL,
  `include_text` tinyint(1) NOT NULL DEFAULT '0',
  `include_files` tinyint(1) NOT NULL DEFAULT '0',
  `include_data` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp(3) NULL DEFAULT NULL,
  `updated_at` timestamp(3) NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `task_definition_agents_task_definition_id_foreign` (`task_definition_id`),
  KEY `task_definition_agents_agent_id_foreign` (`agent_id`),
  CONSTRAINT `task_definition_agents_agent_id_foreign` FOREIGN KEY (`agent_id`) REFERENCES `agents` (`id`),
  CONSTRAINT `task_definition_agents_task_definition_id_foreign` FOREIGN KEY (`task_definition_id`) REFERENCES `task_definitions` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `task_definitions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `task_definitions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `team_id` bigint unsigned NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `task_runner_class` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `task_runner_config` json DEFAULT NULL,
  `artifact_split_mode` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `timeout_after_seconds` int unsigned NOT NULL DEFAULT '300',
  `task_run_count` int unsigned NOT NULL DEFAULT '0',
  `task_agent_count` int unsigned NOT NULL DEFAULT '0',
  `created_at` timestamp(3) NULL DEFAULT NULL,
  `updated_at` timestamp(3) NULL DEFAULT NULL,
  `deleted_at` timestamp(3) NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `task_definitions_team_id_foreign` (`team_id`),
  CONSTRAINT `task_definitions_team_id_foreign` FOREIGN KEY (`team_id`) REFERENCES `teams` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `task_inputs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `task_inputs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `task_definition_id` bigint unsigned NOT NULL,
  `workflow_input_id` bigint unsigned NOT NULL,
  `task_run_count` int unsigned NOT NULL DEFAULT '0',
  `created_at` timestamp(3) NULL DEFAULT NULL,
  `updated_at` timestamp(3) NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `task_inputs_task_definition_id_workflow_input_id_unique` (`task_definition_id`,`workflow_input_id`),
  KEY `task_inputs_workflow_input_id_foreign` (`workflow_input_id`),
  CONSTRAINT `task_inputs_task_definition_id_foreign` FOREIGN KEY (`task_definition_id`) REFERENCES `task_definitions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `task_inputs_workflow_input_id_foreign` FOREIGN KEY (`workflow_input_id`) REFERENCES `workflow_inputs` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `task_process_listeners`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `task_process_listeners` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `task_process_id` bigint unsigned NOT NULL,
  `event_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `event_id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `event_fired_at` datetime NOT NULL,
  `event_handled_at` datetime NOT NULL,
  `created_at` timestamp(3) NULL DEFAULT NULL,
  `updated_at` timestamp(3) NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `task_process_listeners_task_process_id_foreign` (`task_process_id`),
  CONSTRAINT `task_process_listeners_task_process_id_foreign` FOREIGN KEY (`task_process_id`) REFERENCES `task_processes` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `task_processes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `task_processes` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `task_run_id` bigint unsigned NOT NULL,
  `task_definition_agent_id` bigint unsigned DEFAULT NULL,
  `agent_thread_id` bigint unsigned DEFAULT NULL,
  `last_job_dispatch_id` bigint unsigned DEFAULT NULL,
  `status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Pending',
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `activity` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Initializing',
  `percent_complete` decimal(5,2) NOT NULL DEFAULT '0.00',
  `started_at` datetime DEFAULT NULL,
  `stopped_at` datetime DEFAULT NULL,
  `completed_at` datetime DEFAULT NULL,
  `failed_at` datetime DEFAULT NULL,
  `timeout_at` datetime DEFAULT NULL,
  `job_dispatch_count` int unsigned NOT NULL DEFAULT '0',
  `input_artifact_count` int unsigned NOT NULL DEFAULT '0',
  `output_artifact_count` int unsigned NOT NULL DEFAULT '0',
  `created_at` timestamp(3) NULL DEFAULT NULL,
  `updated_at` timestamp(3) NULL DEFAULT NULL,
  `deleted_at` timestamp(3) NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `task_processes_task_run_id_foreign` (`task_run_id`),
  KEY `task_processes_task_definition_agent_id_foreign` (`task_definition_agent_id`),
  KEY `task_processes_thread_id_foreign` (`agent_thread_id`),
  KEY `task_processes_last_job_dispatch_id_foreign` (`last_job_dispatch_id`),
  CONSTRAINT `task_processes_last_job_dispatch_id_foreign` FOREIGN KEY (`last_job_dispatch_id`) REFERENCES `job_dispatch` (`id`) ON DELETE SET NULL,
  CONSTRAINT `task_processes_task_definition_agent_id_foreign` FOREIGN KEY (`task_definition_agent_id`) REFERENCES `task_definition_agents` (`id`) ON DELETE SET NULL,
  CONSTRAINT `task_processes_task_run_id_foreign` FOREIGN KEY (`task_run_id`) REFERENCES `task_runs` (`id`) ON DELETE CASCADE,
  CONSTRAINT `task_processes_thread_id_foreign` FOREIGN KEY (`agent_thread_id`) REFERENCES `agent_threads` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `task_runs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `task_runs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `task_definition_id` bigint unsigned NOT NULL,
  `task_workflow_run_id` bigint unsigned DEFAULT NULL,
  `task_workflow_node_id` bigint unsigned DEFAULT NULL,
  `status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Pending',
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `step` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Initial',
  `percent_complete` decimal(5,2) NOT NULL DEFAULT '0.00',
  `started_at` datetime DEFAULT NULL,
  `stopped_at` datetime DEFAULT NULL,
  `completed_at` datetime DEFAULT NULL,
  `failed_at` datetime DEFAULT NULL,
  `process_count` int unsigned NOT NULL DEFAULT '0',
  `input_artifacts_count` int unsigned NOT NULL DEFAULT '0',
  `output_artifacts_count` int unsigned NOT NULL DEFAULT '0',
  `created_at` timestamp(3) NULL DEFAULT NULL,
  `updated_at` timestamp(3) NULL DEFAULT NULL,
  `deleted_at` timestamp(3) NULL DEFAULT NULL,
  `task_input_id` bigint unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `task_runs_task_definition_id_foreign` (`task_definition_id`),
  KEY `task_runs_task_input_id_foreign` (`task_input_id`),
  KEY `task_runs_task_workflow_run_id_foreign` (`task_workflow_run_id`),
  KEY `task_runs_task_workflow_node_id_foreign` (`task_workflow_node_id`),
  CONSTRAINT `task_runs_task_definition_id_foreign` FOREIGN KEY (`task_definition_id`) REFERENCES `task_definitions` (`id`),
  CONSTRAINT `task_runs_task_input_id_foreign` FOREIGN KEY (`task_input_id`) REFERENCES `task_inputs` (`id`) ON DELETE CASCADE,
  CONSTRAINT `task_runs_task_workflow_node_id_foreign` FOREIGN KEY (`task_workflow_node_id`) REFERENCES `task_workflow_nodes` (`id`) ON DELETE SET NULL,
  CONSTRAINT `task_runs_task_workflow_run_id_foreign` FOREIGN KEY (`task_workflow_run_id`) REFERENCES `task_workflow_runs` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `task_workflow_connections`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `task_workflow_connections` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `task_workflow_id` bigint unsigned NOT NULL,
  `source_node_id` bigint unsigned NOT NULL,
  `target_node_id` bigint unsigned NOT NULL,
  `source_output_port` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `target_input_port` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `created_at` timestamp(3) NULL DEFAULT NULL,
  `updated_at` timestamp(3) NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `task_workflow_connections_task_workflow_id_foreign` (`task_workflow_id`),
  KEY `task_workflow_connections_source_node_id_foreign` (`source_node_id`),
  KEY `task_workflow_connections_target_node_id_foreign` (`target_node_id`),
  CONSTRAINT `task_workflow_connections_source_node_id_foreign` FOREIGN KEY (`source_node_id`) REFERENCES `task_workflow_nodes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `task_workflow_connections_target_node_id_foreign` FOREIGN KEY (`target_node_id`) REFERENCES `task_workflow_nodes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `task_workflow_connections_task_workflow_id_foreign` FOREIGN KEY (`task_workflow_id`) REFERENCES `task_workflows` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `task_workflow_nodes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `task_workflow_nodes` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `task_workflow_id` bigint unsigned NOT NULL,
  `task_definition_id` bigint unsigned NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `settings` json DEFAULT NULL,
  `params` json DEFAULT NULL,
  `created_at` timestamp(3) NULL DEFAULT NULL,
  `updated_at` timestamp(3) NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `task_workflow_nodes_task_workflow_id_foreign` (`task_workflow_id`),
  KEY `task_workflow_nodes_task_definition_id_foreign` (`task_definition_id`),
  CONSTRAINT `task_workflow_nodes_task_definition_id_foreign` FOREIGN KEY (`task_definition_id`) REFERENCES `task_definitions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `task_workflow_nodes_task_workflow_id_foreign` FOREIGN KEY (`task_workflow_id`) REFERENCES `task_workflows` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `task_workflow_runs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `task_workflow_runs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `task_workflow_id` bigint unsigned NOT NULL,
  `status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Pending',
  `started_at` timestamp(3) NULL DEFAULT NULL,
  `stopped_at` timestamp(3) NULL DEFAULT NULL,
  `completed_at` timestamp(3) NULL DEFAULT NULL,
  `failed_at` timestamp(3) NULL DEFAULT NULL,
  `has_run_all_tasks` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp(3) NULL DEFAULT NULL,
  `updated_at` timestamp(3) NULL DEFAULT NULL,
  `deleted_at` timestamp(3) NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `task_workflow_runs_task_workflow_id_foreign` (`task_workflow_id`),
  CONSTRAINT `task_workflow_runs_task_workflow_id_foreign` FOREIGN KEY (`task_workflow_id`) REFERENCES `task_workflows` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `task_workflows`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `task_workflows` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `team_id` bigint unsigned NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `workflow_runs_count` int unsigned NOT NULL DEFAULT '0',
  `created_at` timestamp(3) NULL DEFAULT NULL,
  `updated_at` timestamp(3) NULL DEFAULT NULL,
  `deleted_at` timestamp(3) NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `task_workflows_team_id_foreign` (`team_id`),
  CONSTRAINT `task_workflows_team_id_foreign` FOREIGN KEY (`team_id`) REFERENCES `teams` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `team_user`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `team_user` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `team_id` bigint unsigned NOT NULL,
  `created_at` timestamp(3) NULL DEFAULT NULL,
  `updated_at` timestamp(3) NULL DEFAULT NULL,
  `deleted_at` timestamp(3) NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `team_user_user_id_foreign` (`user_id`),
  KEY `team_user_team_id_foreign` (`team_id`),
  CONSTRAINT `team_user_team_id_foreign` FOREIGN KEY (`team_id`) REFERENCES `teams` (`id`) ON DELETE CASCADE,
  CONSTRAINT `team_user_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `teams`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `teams` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `namespace` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `logo` varchar(2048) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp(3) NULL DEFAULT NULL,
  `updated_at` timestamp(3) NULL DEFAULT NULL,
  `deleted_at` timestamp(3) NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `teams_name_unique` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `usage_events`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `usage_events` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `team_id` bigint unsigned NOT NULL,
  `user_id` bigint unsigned NOT NULL,
  `object_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `object_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `event_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `run_time_ms` int unsigned NOT NULL DEFAULT '0',
  `input_tokens` int unsigned NOT NULL DEFAULT '0',
  `output_tokens` int unsigned NOT NULL DEFAULT '0',
  `input_cost` decimal(12,4) NOT NULL DEFAULT '0.0000',
  `output_cost` decimal(12,4) NOT NULL DEFAULT '0.0000',
  `created_at` timestamp(3) NULL DEFAULT NULL,
  `updated_at` timestamp(3) NULL DEFAULT NULL,
  `deleted_at` timestamp(3) NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `usage_events_user_id_foreign` (`user_id`),
  KEY `usage_events_team_id_object_type_object_id_index` (`team_id`,`object_type`,`object_id`),
  KEY `usage_events_event_type_object_type_object_id_index` (`event_type`,`object_type`,`object_id`),
  KEY `usage_events_object_id_object_type_index` (`object_id`,`object_type`),
  CONSTRAINT `usage_events_team_id_foreign` FOREIGN KEY (`team_id`) REFERENCES `teams` (`id`),
  CONSTRAINT `usage_events_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `usage_summaries`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `usage_summaries` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `object_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `object_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `count` int unsigned NOT NULL DEFAULT '1',
  `run_time_ms` int unsigned NOT NULL DEFAULT '0',
  `input_tokens` int unsigned NOT NULL DEFAULT '0',
  `output_tokens` int unsigned NOT NULL DEFAULT '0',
  `input_cost` decimal(12,4) NOT NULL DEFAULT '0.0000',
  `output_cost` decimal(12,4) NOT NULL DEFAULT '0.0000',
  `total_cost` decimal(12,4) GENERATED ALWAYS AS ((`input_cost` + `output_cost`)) STORED,
  `created_at` timestamp(3) NULL DEFAULT NULL,
  `updated_at` timestamp(3) NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `usage_summaries_object_id_object_type_index` (`object_id`,`object_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `users` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `password` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `remember_token` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp(3) NULL DEFAULT NULL,
  `updated_at` timestamp(3) NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `users_email_unique` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `workflow_inputs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `workflow_inputs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `content_source_id` bigint unsigned DEFAULT NULL,
  `team_id` bigint unsigned NOT NULL,
  `user_id` bigint unsigned NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `content` text COLLATE utf8mb4_unicode_ci,
  `data` json DEFAULT NULL,
  `tokens` int unsigned NOT NULL DEFAULT '0',
  `is_url` tinyint(1) NOT NULL DEFAULT '0',
  `team_object_id` bigint unsigned DEFAULT NULL,
  `team_object_type` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp(3) NULL DEFAULT NULL,
  `updated_at` timestamp(3) NULL DEFAULT NULL,
  `deleted_at` timestamp(3) NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `input_sources_team_id_foreign` (`team_id`),
  KEY `input_sources_user_id_foreign` (`user_id`),
  KEY `workflow_inputs_content_source_id_foreign` (`content_source_id`),
  CONSTRAINT `input_sources_team_id_foreign` FOREIGN KEY (`team_id`) REFERENCES `teams` (`id`) ON DELETE CASCADE,
  CONSTRAINT `input_sources_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`),
  CONSTRAINT `workflow_inputs_content_source_id_foreign` FOREIGN KEY (`content_source_id`) REFERENCES `content_sources` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (1,'0000_danx_auditing_tables',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (2,'0000_danx_files_tables',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (3,'0001_01_01_000000_create_users_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (4,'0001_01_01_000001_create_cache_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (5,'0001_danx_job_dispatch_data_field',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (6,'2024_04_21_014518_create_teams_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (7,'2024_04_21_014520_create_knowledge_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (8,'2024_04_21_014524_create_agents_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (9,'2024_04_21_015904_create_threads_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (10,'2024_04_21_015910_create_messages_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (11,'2024_04_21_041443_create_thread_runs_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (12,'2024_04_21_041459_create_artifacts_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (13,'2024_04_21_045209_add_team_id_to_users',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (14,'2024_05_09_041448_create_input_sources',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (15,'2024_05_09_042721_create_workflows',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (16,'2024_05_09_042843_create_workflow_jobs',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (17,'2024_05_09_042844_create_workflow_job_dependencies',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (18,'2024_05_09_043131_create_workflow_runs',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (19,'2024_05_09_043132_create_workflow_assignments',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (20,'2024_05_09_043134_create_workflow_job_runs',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (21,'2024_05_09_043135_create_workflow_tasks',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (22,'2024_05_16_033638_create_personal_access_tokens_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (23,'2024_05_26_021708_add_team_user_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (24,'2024_05_30_033435_refactor_input_sources_to_workflow_inputs',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (25,'2024_05_30_221634_create_object_tags_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (26,'2024_05_30_225721_create_content_source_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (27,'2024_06_07_074914_add_logo_to_teams',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (28,'2024_06_07_141934_add_schema_file_to_teams',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (29,'2024_06_25_051901_add_job_dispatch_id_to_thread_runs',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (30,'2024_06_26_220600_add_assignments_count_to_agents',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (31,'2024_07_04_152615_change_unique_includes_deleted_to_agents',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (32,'2024_07_04_203751_add_response_fields_to_agents',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (33,'2024_07_10_201134_nullable_team_fields',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (34,'2024_07_13_160123_add_response_sample_to_agents',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (35,'2024_07_15_062329_group_by_json_field_to_workflow_job_dependencies',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (36,'2024_07_15_070643_add_include_fields_to_workflow_job_dependencies',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (37,'2024_07_25_081507_add_summarizer_offset_to_messages',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (38,'2024_07_26_004653_drop_is_transcoded_to_workflow_inputs',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (39,'2024_07_29_185243_ai_model_to_threads',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (40,'2024_08_02_204226_config_to_workflow_job_dependencies',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (41,'2024_08_05_033533_counts_to_workflow_runs',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (42,'2024_08_11_043921_add_schema_format_to_agents',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (43,'2024_08_12_075632_add_team_objects_to_workflow_inputs',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (44,'2024_08_12_173000_add_timeout_after_to_workflow_jobs',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (45,'2024_08_12_211631_add_response_schema_to_workflow_jobs',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (46,'2024_08_14_005809_add_enable_artifact_sources_to_agents',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (47,'2024_08_14_054311_create_messageables_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (48,'2024_08_15_084124_add_retry_count_to_agents',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (49,'2024_08_25_204941_create_agent_schemas',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (50,'2024_08_25_204946_create_agent_directives',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (51,'2024_08_30_004155_migrate_agent_prompts',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (52,'2024_08_30_005137_cleanup_old_prompting_fields',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (53,'2024_08_30_155828_migrate_response_schema_to_workflow_jobs',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (54,'2024_08_31_033742_add_runs_count_to_workflow_jobs',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (55,'2024_08_31_034913_drop_schema_file_to_teams',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (56,'2024_11_23_192758_prompt_schema_history',2);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (58,'2024_12_16_220320_drop_artifact_id_from_worklfow_tasks',3);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (59,'2024_12_18_210629_add_save_response_to_db_to_agents',4);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (61,'2024_12_19_171419_add_schema_sub_selection_to_agents',5);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (110,'0002_danx_create_job_dispatchable_table',6);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (111,'2025_01_19_204402_create_task_definition_table',6);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (123,'2025_01_18_223623_create_prompt_schema_fragments_table',7);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (124,'2025_01_19_204408_create_task_definition_agent_table',7);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (125,'2025_01_19_224024_create_task_runs_table',8);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (126,'2025_01_19_224035_create_task_processes_table',8);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (127,'2025_01_20_224806_create_task_process_listeners',8);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (128,'2025_01_25_233222_replace_sub_selection_with_fragments_to_agents',8);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (129,'2025_01_27_230935_rename_content_fields_to_artifacts',8);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (130,'2025_01_27_234426_rename_thread_to_agent_threads',8);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (131,'2025_01_30_045107_artifact_model_not_required',8);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (132,'2025_02_04_005654_add_token_costs_to_thread_runs',9);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (138,'2025_02_05_034132_create_task_inputs_table',10);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (141,'2025_02_05_160239_create_usage_events_table',11);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (142,'2025_02_05_162941_create_usage_summaries_table',11);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (143,'2025_02_05_164152_drop_usage_columns_from_tasks',12);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (145,'2025_02_05_180636_add_counts_to_task_processes',13);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (147,'2025_02_05_202531_add_step_and_activity_to_task_processes',14);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (152,'2025_02_07_045720_add_grouping_mode_and_key_to_task_definitions',15);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (155,'2025_02_07_191309_create_schema_associations_table',16);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (157,'2025_02_09_220452_rename_prompt_schema_to_schema_definitions',17);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (158,'2025_02_08_010357_remove_input_output_schemas_from_task_definition_agents',18);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (159,'2025_02_10_004055_allow_null_schemea_fragment_id_to_schema_associations',19);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (160,'2025_02_12_205006_create_task_workflows_table',20);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (161,'2025_02_13_023819_create_task_workflow_nodes',20);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (162,'2025_02_13_023847_create_task_workflow_connections',20);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (169,'2025_02_13_025038_create_task_workflow_runs',21);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (170,'2025_02_13_034949_add_task_workflow_run_id_to_task_runs',21);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (171,'2025_02_14_205440_add_team_to_task_workflows',22);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (172,'2025_02_20_184509_add_page_number_to_stored_files',23);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (175,'2025_03_04_015332_convert_to_ms_timestamps',24);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (176,'2025_03_06_041709_add_input_output_artifacts_count_to_task_runs',25);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (181,'2025_03_07_032835_add_config_to_task_definitions',26);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (182,'2025_03_07_032850_remove_obsolete_workflow_fields',26);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (183,'2025_03_08_054142_remove_save_response_to_db_from_agents',27);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (184,'2025_03_08_060506_add_meta_to_artifacts',28);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (185,'2025_03_13_002736_add_response_schema_to_agent_thread_runs',29);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (186,'2025_03_14_064408_add_has_run_all_tasks_to_task_workflow_run',30);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (187,'2025_03_14_231724_drop_old_workflow_tables',31);
