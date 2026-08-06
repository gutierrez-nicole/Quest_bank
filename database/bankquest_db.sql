-- QuestBank Database Dump & Backup
-- Generated: 2026-08-06 08:25:03
-- Database: bankquest_db
-- Application Version: v2.2-RC1

SET FOREIGN_KEY_CHECKS=0;
SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;

-- --------------------------------------------------------
-- Table structure for `academic_calendar`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `academic_calendar`;
CREATE TABLE `academic_calendar` (
  `id` int NOT NULL AUTO_INCREMENT,
  `event_title` varchar(150) NOT NULL,
  `event_type` varchar(50) NOT NULL DEFAULT 'school_activity',
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `description` text,
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------
-- Table structure for `activity_logs`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `activity_logs`;
CREATE TABLE `activity_logs` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `action_description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_activity_user` (`user_id`),
  CONSTRAINT `fk_activity_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Dumping data for `activity_logs`

INSERT INTO `activity_logs` (`id`, `user_id`, `action_description`, `created_at`) VALUES 
('1', '10', 'Published exam #10 results for Structural Engineering', '2026-08-06 16:24:57');

-- --------------------------------------------------------
-- Table structure for `ai_generation_batches`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `ai_generation_batches`;
CREATE TABLE `ai_generation_batches` (
  `id` int NOT NULL AUTO_INCREMENT,
  `generation_batch_id` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `teacher_id` int NOT NULL,
  `selected_lesson_ids` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `selected_lesson_titles` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `selected_periods` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `selected_subject` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `semester` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `school_year` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `year_level` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `program` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `total_selected_words` int DEFAULT '0',
  `estimated_tokens` int DEFAULT '0',
  `ai_model` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `generation_duration` float DEFAULT '0',
  `requested_question_count` int DEFAULT '0',
  `generated_question_count` int DEFAULT '0',
  `failed_question_count` int DEFAULT '0',
  `warnings` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `batch_status` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT 'completed',
  `failed_chunk_count` int DEFAULT '0',
  `affected_lesson_ids` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `failure_messages` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `teacher_acknowledged_at` timestamp NULL DEFAULT NULL,
  `teacher_acknowledged_by` int DEFAULT NULL,
  `acknowledgement_reason` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `acknowledgement_token_hash` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `batch_consumed_at` timestamp NULL DEFAULT NULL,
  `batch_consumed_by` int DEFAULT NULL,
  `saved_exam_id` int DEFAULT NULL,
  `chunk_generation_results` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `questions_per_lesson` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `questions_per_period` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `uncovered_lesson_ids` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `uncovered_periods` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `refill_attempt_count` int DEFAULT '0',
  `refill_warnings` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `simulated_scenario` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `failed_chunk_index` int DEFAULT NULL,
  `refill_target_chunk_index` int DEFAULT NULL,
  `refill_target_lesson_ids` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `refill_target_periods` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `refill_generated_count` int DEFAULT '0',
  `initial_questions_per_lesson` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `initial_questions_per_period` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `initial_uncovered_lesson_ids` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `initial_uncovered_periods` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `affected_periods` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `failed_chunk_indexes` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `failed_chunks` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `period_weighting_mode` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT 'equal',
  `requested_period_distribution` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `actual_period_distribution` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `requested_question_blueprint` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `actual_question_distribution` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `requested_difficulty_distribution` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `actual_difficulty_distribution` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `duplicate_count` int DEFAULT '0',
  `replacement_attempt_count` int DEFAULT '0',
  `duplicate_warnings` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `replacement_success_count` int DEFAULT '0',
  `unresolved_duplicate_count` int DEFAULT '0',
  `period_distribution_mismatch` tinyint(1) DEFAULT '0',
  `question_blueprint_mismatch` tinyint(1) DEFAULT '0',
  `difficulty_distribution_mismatch` tinyint(1) DEFAULT '0',
  `unresolved_differences` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_batch_id` (`generation_batch_id`),
  KEY `teacher_id` (`teacher_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table structure for `audit_logs`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `audit_logs`;
CREATE TABLE `audit_logs` (
  `id` int NOT NULL AUTO_INCREMENT,
  `actor_id` int DEFAULT NULL,
  `action` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `entity_type` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `entity_id` int NOT NULL,
  `old_values` json DEFAULT NULL,
  `new_values` json DEFAULT NULL,
  `reason` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `user_id` int DEFAULT NULL,
  `details` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `ip_address` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_audit_actor` (`actor_id`),
  KEY `idx_audit_entity` (`entity_type`,`entity_id`),
  CONSTRAINT `fk_audit_actor` FOREIGN KEY (`actor_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Dumping data for `audit_logs`

INSERT INTO `audit_logs` (`id`, `actor_id`, `action`, `entity_type`, `entity_id`, `old_values`, `new_values`, `reason`, `created_at`, `user_id`, `details`, `ip_address`) VALUES 
('1', '10', 'EXAM_PUBLISHED', 'exam', '10', NULL, NULL, NULL, '2026-08-06 16:24:57', '10', 'Published exam results for Ashley Nicole Gutierrez', NULL);

-- --------------------------------------------------------
-- Table structure for `department_objectives`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `department_objectives`;
CREATE TABLE `department_objectives` (
  `id` int NOT NULL AUTO_INCREMENT,
  `dept_code` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'BSCE',
  `dept_name` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'Department of Civil Engineering',
  `vision` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `mission` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `quality_policy` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `target_pass_rate` decimal(5,2) NOT NULL DEFAULT '75.00',
  `target_ocr_accuracy` decimal(5,2) NOT NULL DEFAULT '90.00',
  `target_iso_compliance` decimal(5,2) NOT NULL DEFAULT '85.00',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table structure for `departments`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `departments`;
CREATE TABLE `departments` (
  `id` int NOT NULL AUTO_INCREMENT,
  `dept_code` varchar(50) NOT NULL,
  `dept_name` varchar(255) NOT NULL,
  `programs` varchar(255) NOT NULL,
  `faculty_head` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_dept_code` (`dept_code`)
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Dumping data for `departments`

INSERT INTO `departments` (`id`, `dept_code`, `dept_name`, `programs`, `faculty_head`, `created_at`) VALUES 
('1', 'COE', 'College of Engineering', 'BSCE', 'Prof. Russel Gregorio', '2026-08-06 16:24:57');

-- --------------------------------------------------------
-- Table structure for `exam_assignments`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `exam_assignments`;
CREATE TABLE `exam_assignments` (
  `id` int NOT NULL AUTO_INCREMENT,
  `exam_id` int NOT NULL,
  `student_id` int DEFAULT NULL,
  `section_id` int DEFAULT NULL,
  `assigned_by` int NOT NULL,
  `available_from` datetime DEFAULT NULL,
  `available_until` datetime DEFAULT NULL,
  `max_attempts` int NOT NULL DEFAULT '1',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_assignments_exam` (`exam_id`),
  KEY `idx_assignments_student` (`student_id`),
  KEY `idx_assignments_section` (`section_id`),
  KEY `idx_assignments_assigned_by` (`assigned_by`),
  CONSTRAINT `fk_assignments_assigned_by` FOREIGN KEY (`assigned_by`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_assignments_exam` FOREIGN KEY (`exam_id`) REFERENCES `exams` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_assignments_section` FOREIGN KEY (`section_id`) REFERENCES `sections` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_assignments_student` FOREIGN KEY (`student_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table structure for `exam_questions`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `exam_questions`;
CREATE TABLE `exam_questions` (
  `id` int NOT NULL AUTO_INCREMENT,
  `exam_id` int NOT NULL,
  `question_text` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `question_type` varchar(50) COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'multiple_choice',
  `option_a` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `option_b` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `option_c` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `option_d` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `options_json` json DEFAULT NULL,
  `matching_pairs` json DEFAULT NULL,
  `formula_latex` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `rubric_json` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `tolerance` decimal(10,4) DEFAULT '0.0500',
  `expected_unit` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `points` int NOT NULL DEFAULT '1',
  `partial_credit_enabled` tinyint(1) NOT NULL DEFAULT '0',
  `correct_answer` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `explanation` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `difficulty` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT 'medium',
  `topic` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `lesson_id` int DEFAULT NULL,
  `source_review_required` tinyint(1) DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `idx_questions_exam` (`exam_id`),
  CONSTRAINT `fk_questions_exam` FOREIGN KEY (`exam_id`) REFERENCES `exams` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=120 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Dumping data for `exam_questions`

INSERT INTO `exam_questions` (`id`, `exam_id`, `question_text`, `question_type`, `option_a`, `option_b`, `option_c`, `option_d`, `options_json`, `matching_pairs`, `formula_latex`, `rubric_json`, `tolerance`, `expected_unit`, `points`, `partial_credit_enabled`, `correct_answer`, `explanation`, `difficulty`, `topic`, `lesson_id`, `source_review_required`) VALUES 
('101', '10', 'What is the standard minimum concrete cover for reinforced concrete beams exposed to soil?', 'multiple_choice', '75 mm', '50 mm', '40 mm', '25 mm', NULL, NULL, NULL, NULL, '0.0500', NULL, '1', '0', '75 mm', NULL, 'medium', NULL, NULL, '0'),
('102', '10', 'Under the National Structural Code of the Philippines (NSCP 2015), flexural strength reduction factor phi for tension-controlled sections is 0.90.', 'true_false', 'true', 'false', NULL, NULL, NULL, NULL, NULL, NULL, '0.0500', NULL, '1', '0', 'true', NULL, 'medium', NULL, NULL, '0'),
('103', '10', 'Calculate the nominal shear capacity of a rectangular concrete beam with b = 250mm, d = 400mm, fc\' = 28 MPa.', 'multiple_choice', '88.36 kN', '95.20 kN', '102.50 kN', '74.10 kN', NULL, NULL, NULL, NULL, '0.0500', NULL, '1', '0', '88.36 kN', NULL, 'medium', NULL, NULL, '0');

-- --------------------------------------------------------
-- Table structure for `exam_schedules`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `exam_schedules`;
CREATE TABLE `exam_schedules` (
  `id` int NOT NULL AUTO_INCREMENT,
  `exam_id` int NOT NULL,
  `teacher_id` int NOT NULL,
  `section` varchar(50) NOT NULL,
  `exam_date` date NOT NULL,
  `start_time` time NOT NULL,
  `end_time` time NOT NULL,
  `duration_minutes` int NOT NULL DEFAULT '60',
  `room` varchar(100) DEFAULT NULL,
  `remarks` text,
  `semester_id` int DEFAULT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'scheduled',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------
-- Table structure for `exam_submissions`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `exam_submissions`;
CREATE TABLE `exam_submissions` (
  `id` int NOT NULL AUTO_INCREMENT,
  `teacher_id` int NOT NULL,
  `student_id` int DEFAULT NULL,
  `exam_id` int DEFAULT NULL,
  `student_name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `exam_title` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `upload_type` varchar(30) COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'scanned',
  `correct_count` int NOT NULL DEFAULT '0',
  `wrong_count` int NOT NULL DEFAULT '0',
  `total_score` decimal(10,2) NOT NULL DEFAULT '0.00',
  `total_possible_score` decimal(10,2) NOT NULL DEFAULT '0.00',
  `total_items` int NOT NULL DEFAULT '0',
  `percentage` decimal(5,2) NOT NULL DEFAULT '0.00',
  `status` varchar(20) COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'Fail',
  `raw_ocr_data` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `ocr_text` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `ocr_confidence` decimal(5,2) NOT NULL DEFAULT '0.00',
  `ocr_status` enum('pending','processing','completed','manual_review_required','failed') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'completed',
  `ocr_error` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `suggested_manual_review` tinyint(1) NOT NULL DEFAULT '0',
  `page_count` int NOT NULL DEFAULT '1',
  `evaluation_result` json DEFAULT NULL,
  `teacher_override_log` json DEFAULT NULL,
  `review_status` varchar(30) COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'pending_review',
  `reviewed_by` int DEFAULT NULL,
  `teacher_remarks` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `reviewed_at` timestamp NULL DEFAULT NULL,
  `published_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `file_path` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `original_filename` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `uploaded_file_hash` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `original_ocr_text` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `corrected_ocr_text` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `processed_at` datetime DEFAULT NULL,
  `extraction_mode` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT 'image_ocr',
  `per_page_ocr_metadata` json DEFAULT NULL,
  `processing_duration` decimal(8,2) DEFAULT '0.00',
  `is_demo` tinyint(1) NOT NULL DEFAULT '0',
  `qualification_status` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'pending',
  `attempt_number` int NOT NULL DEFAULT '1',
  PRIMARY KEY (`id`),
  KEY `idx_submissions_teacher` (`teacher_id`),
  KEY `idx_submissions_student` (`student_id`),
  KEY `idx_submissions_exam` (`exam_id`),
  KEY `idx_submissions_review_status` (`review_status`),
  KEY `idx_submissions_student_status` (`student_id`,`review_status`),
  CONSTRAINT `fk_submissions_exam` FOREIGN KEY (`exam_id`) REFERENCES `exams` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_submissions_student` FOREIGN KEY (`student_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_submissions_teacher` FOREIGN KEY (`teacher_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=600 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Dumping data for `exam_submissions`

INSERT INTO `exam_submissions` (`id`, `teacher_id`, `student_id`, `exam_id`, `student_name`, `exam_title`, `upload_type`, `correct_count`, `wrong_count`, `total_score`, `total_possible_score`, `total_items`, `percentage`, `status`, `raw_ocr_data`, `ocr_text`, `ocr_confidence`, `ocr_status`, `ocr_error`, `suggested_manual_review`, `page_count`, `evaluation_result`, `teacher_override_log`, `review_status`, `reviewed_by`, `teacher_remarks`, `reviewed_at`, `published_at`, `created_at`, `file_path`, `original_filename`, `uploaded_file_hash`, `original_ocr_text`, `corrected_ocr_text`, `processed_at`, `extraction_mode`, `per_page_ocr_metadata`, `processing_duration`, `is_demo`, `qualification_status`, `attempt_number`) VALUES 
('500', '10', '11', '10', 'Ashley Nicole Gutierrez', 'Civil Engineering Board Exam Review - Structural Design & Construction', 'online', '3', '0', '3.00', '3.00', '3', '100.00', 'Pass', NULL, NULL, '0.00', 'completed', NULL, '0', '1', NULL, NULL, 'published', NULL, NULL, NULL, '2026-08-06 16:24:57', '2026-08-06 16:24:57', NULL, NULL, NULL, NULL, NULL, NULL, 'image_ocr', NULL, '0.00', '1', 'pending', '1'),
('501', '10', '20', '10', 'John Mark Santos', 'Civil Engineering Board Exam Review - Structural Design & Construction', 'scanned', '2', '1', '2.00', '3.00', '3', '66.67', 'Fail', NULL, NULL, '0.00', 'completed', NULL, '0', '1', NULL, NULL, 'pending_review', NULL, NULL, NULL, NULL, '2026-08-06 16:24:57', NULL, NULL, NULL, NULL, NULL, NULL, 'image_ocr', NULL, '0.00', '1', 'pending', '1'),
('502', '10', '21', '11', 'Maria Angelica Reyes', 'Civil Engineering Comprehensive Qualifying Exam', 'online', '3', '0', '3.00', '3.00', '3', '100.00', 'Pass', NULL, NULL, '0.00', 'completed', NULL, '0', '1', NULL, NULL, 'finalized', NULL, NULL, NULL, NULL, '2026-08-06 16:24:57', NULL, NULL, NULL, NULL, NULL, NULL, 'image_ocr', NULL, '0.00', '1', 'pending', '1');

-- --------------------------------------------------------
-- Table structure for `exams`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `exams`;
CREATE TABLE `exams` (
  `id` int NOT NULL AUTO_INCREMENT,
  `teacher_id` int NOT NULL,
  `title` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `subject` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `specialization` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT 'Structural Engineering',
  `term` enum('Prelim','Midterm','Finals') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'Prelim',
  `difficulty` enum('easy','medium','hard') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'medium',
  `time_limit` int DEFAULT '60',
  `total_items` int DEFAULT '0',
  `passing_percentage` decimal(5,2) NOT NULL DEFAULT '75.00',
  `status` enum('draft','active','closed','archived') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'active',
  `available_from` datetime DEFAULT NULL,
  `available_until` datetime DEFAULT NULL,
  `max_attempts` int NOT NULL DEFAULT '1',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  `ai_metadata` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `lesson_ids` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `generation_status` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT 'completed',
  `generation_error` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `prompt_version` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT 'v1.0',
  `ai_model` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `is_demo` tinyint(1) NOT NULL DEFAULT '0',
  `exam_category` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'regular',
  `qualifying_passing_percentage` decimal(5,2) DEFAULT '75.00',
  `qualifying_max_attempts` int DEFAULT '1',
  `qualifying_year_level` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT 'All Year Levels',
  `qualifying_program` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT 'All Programs',
  `qualifying_is_required` tinyint(1) DEFAULT '1',
  `qualifying_unlock_date` datetime DEFAULT NULL,
  `qualifying_deadline` datetime DEFAULT NULL,
  `covered_periods` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `source_lesson_count` int DEFAULT '0',
  `generation_source_type` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `generation_batch_id` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_exams_teacher` (`teacher_id`),
  KEY `idx_exams_status` (`status`),
  CONSTRAINT `fk_exams_teacher` FOREIGN KEY (`teacher_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=20 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Dumping data for `exams`

INSERT INTO `exams` (`id`, `teacher_id`, `title`, `subject`, `specialization`, `term`, `difficulty`, `time_limit`, `total_items`, `passing_percentage`, `status`, `available_from`, `available_until`, `max_attempts`, `created_at`, `updated_at`, `ai_metadata`, `lesson_ids`, `generation_status`, `generation_error`, `prompt_version`, `ai_model`, `created_by`, `is_demo`, `exam_category`, `qualifying_passing_percentage`, `qualifying_max_attempts`, `qualifying_year_level`, `qualifying_program`, `qualifying_is_required`, `qualifying_unlock_date`, `qualifying_deadline`, `covered_periods`, `source_lesson_count`, `generation_source_type`, `generation_batch_id`) VALUES 
('10', '10', 'Civil Engineering Board Exam Review - Structural Design & Construction', 'Structural Engineering', 'Structural Engineering', 'Prelim', 'medium', '60', '3', '75.00', 'active', NULL, NULL, '1', '2026-08-06 16:24:57', NULL, NULL, NULL, 'completed', NULL, 'v1.0', NULL, '10', '1', 'regular', '75.00', '1', 'All Year Levels', 'All Programs', '1', NULL, NULL, NULL, '0', NULL, NULL),
('11', '10', 'Civil Engineering Comprehensive Qualifying Exam', 'Structural Engineering', 'Structural Engineering', 'Prelim', 'hard', '120', '3', '75.00', 'active', NULL, NULL, '1', '2026-08-06 16:24:57', NULL, NULL, NULL, 'completed', NULL, 'v1.0', NULL, '10', '1', 'qualifying', '75.00', '1', 'All Year Levels', 'All Programs', '1', NULL, NULL, NULL, '0', NULL, NULL);

-- --------------------------------------------------------
-- Table structure for `generated_question_sources`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `generated_question_sources`;
CREATE TABLE `generated_question_sources` (
  `id` int NOT NULL AUTO_INCREMENT,
  `question_id` int NOT NULL,
  `lesson_id` int NOT NULL,
  `academic_period` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `source_topic` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `source_confidence` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT 'high',
  `source_review_required` tinyint(1) DEFAULT '0',
  `source_verified_by` int DEFAULT NULL,
  `source_verified_at` timestamp NULL DEFAULT NULL,
  `source_verification_note` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_question_lesson` (`question_id`,`lesson_id`),
  KEY `question_id` (`question_id`),
  KEY `lesson_id` (`lesson_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table structure for `iso_evaluations`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `iso_evaluations`;
CREATE TABLE `iso_evaluations` (
  `id` int NOT NULL AUTO_INCREMENT,
  `evaluation_date` date NOT NULL,
  `evaluated_by` int NOT NULL,
  `section_id` int DEFAULT NULL,
  `total_students` int NOT NULL DEFAULT '0',
  `passed_students` int NOT NULL DEFAULT '0',
  `pass_rate` decimal(5,2) NOT NULL DEFAULT '0.00',
  `iso_compliant` tinyint(1) NOT NULL DEFAULT '0',
  `audit_status` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'completed',
  `remarks` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_iso_evaluated_by` (`evaluated_by`),
  CONSTRAINT `fk_iso_evaluated_by` FOREIGN KEY (`evaluated_by`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table structure for `lesson_materials`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `lesson_materials`;
CREATE TABLE `lesson_materials` (
  `id` int NOT NULL AUTO_INCREMENT,
  `teacher_id` int NOT NULL,
  `subject` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `title` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `file_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `file_path` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `file_type` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `file_size` int NOT NULL,
  `lesson_text` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `processing_status` enum('pending','processing','completed','failed') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'completed',
  `processing_error` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `word_count` int NOT NULL DEFAULT '0',
  `page_count` int NOT NULL DEFAULT '1',
  `extracted_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `mime_type` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `original_filename` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `stored_filename` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `is_demo` tinyint(1) NOT NULL DEFAULT '0',
  `academic_period` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'general',
  `semester` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `school_year` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `year_level` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `program` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_lessons_teacher` (`teacher_id`),
  CONSTRAINT `fk_lessons_teacher` FOREIGN KEY (`teacher_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=20 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Dumping data for `lesson_materials`

INSERT INTO `lesson_materials` (`id`, `teacher_id`, `subject`, `title`, `file_name`, `file_path`, `file_type`, `file_size`, `lesson_text`, `processing_status`, `processing_error`, `word_count`, `page_count`, `extracted_at`, `created_at`, `mime_type`, `original_filename`, `stored_filename`, `is_demo`, `academic_period`, `semester`, `school_year`, `year_level`, `program`) VALUES 
('10', '10', 'Structural Engineering', 'Structural Steel & Reinforced Concrete Design Fundamentals', 'demo_structural_steel.txt', 'teacher/uploads/demo_structural_steel.txt', 'txt', '1024', 'Reinforced concrete flexural design relies on ultimate limit state analysis and steel tensile reinforcement capacity.', 'completed', NULL, '150', '1', NULL, '2026-08-06 16:24:57', NULL, 'demo_structural_steel.txt', 'demo_structural_steel.txt', '1', 'general', NULL, NULL, NULL, NULL);

-- --------------------------------------------------------
-- Table structure for `notifications`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `notifications`;
CREATE TABLE `notifications` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `type` varchar(50) NOT NULL,
  `message` text NOT NULL,
  `is_read` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------
-- Table structure for `school_years`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `school_years`;
CREATE TABLE `school_years` (
  `id` int NOT NULL AUTO_INCREMENT,
  `school_year` varchar(20) NOT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'inactive',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `school_year` (`school_year`)
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Dumping data for `school_years`

INSERT INTO `school_years` (`id`, `school_year`, `start_date`, `end_date`, `status`, `created_at`) VALUES 
('1', '2025-2026', '2025-06-01', '2026-05-31', 'active', '2026-08-06 16:24:57');

-- --------------------------------------------------------
-- Table structure for `sections`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `sections`;
CREATE TABLE `sections` (
  `id` int NOT NULL AUTO_INCREMENT,
  `teacher_id` int DEFAULT NULL,
  `section_name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `course_name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `academic_year` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  `section_code` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `adviser_id` int DEFAULT NULL,
  `capacity` int NOT NULL DEFAULT '40',
  `status` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'active',
  `course` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `school_year_id` int DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_sections_teacher` (`teacher_id`),
  CONSTRAINT `fk_sections_teacher` FOREIGN KEY (`teacher_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Dumping data for `sections`

INSERT INTO `sections` (`id`, `teacher_id`, `section_name`, `course_name`, `academic_year`, `created_at`, `updated_at`, `section_code`, `adviser_id`, `capacity`, `status`, `course`, `school_year_id`) VALUES 
('1', NULL, 'Section 4A', 'BSCE', '2025-2026', '2026-08-06 16:24:57', NULL, 'BSCE-4A', NULL, '40', 'active', 'BSCE', '1');

-- --------------------------------------------------------
-- Table structure for `semesters`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `semesters`;
CREATE TABLE `semesters` (
  `id` int NOT NULL AUTO_INCREMENT,
  `school_year_id` int NOT NULL,
  `semester_name` varchar(50) NOT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'inactive',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_sy_semester` (`school_year_id`,`semester_name`)
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Dumping data for `semesters`

INSERT INTO `semesters` (`id`, `school_year_id`, `semester_name`, `status`, `created_at`) VALUES 
('1', '1', 'First Semester', 'active', '2026-08-06 16:24:57');

-- --------------------------------------------------------
-- Table structure for `student_details`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `student_details`;
CREATE TABLE `student_details` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `student_number` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `course` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'BS Civil Engineering',
  `section` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'BSCE-4A',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `year_level` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT '3rd Year',
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_student_details_user` (`user_id`),
  CONSTRAINT `fk_student_details_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=30 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Dumping data for `student_details`

INSERT INTO `student_details` (`id`, `user_id`, `student_number`, `course`, `section`, `created_at`, `year_level`) VALUES 
('1', '11', '23-2149184', 'BSCE', 'Section A', '2026-08-06 16:24:57', '4'),
('2', '20', '23-2149800', 'BSCE', 'Section A', '2026-08-06 16:24:57', '4'),
('3', '21', '23-2149805', 'BSCE', 'Section B', '2026-08-06 16:24:57', '4');

-- --------------------------------------------------------
-- Table structure for `student_requests`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `student_requests`;
CREATE TABLE `student_requests` (
  `id` int NOT NULL AUTO_INCREMENT,
  `student_id` int NOT NULL,
  `teacher_id` int NOT NULL,
  `section_id` int DEFAULT NULL,
  `student_number` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `student_name` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `subject_name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `status` enum('pending','accepted','rejected') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'pending',
  `requested_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_requests_teacher_status` (`teacher_id`,`status`),
  KEY `idx_requests_student` (`student_id`),
  CONSTRAINT `fk_requests_student` FOREIGN KEY (`student_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_requests_teacher` FOREIGN KEY (`teacher_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table structure for `students`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `students`;
CREATE TABLE `students` (
  `id` int NOT NULL AUTO_INCREMENT,
  `teacher_id` int NOT NULL,
  `section_id` int NOT NULL,
  `student_number` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `fullname` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `email` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_students_teacher` (`teacher_id`),
  KEY `idx_students_section` (`section_id`),
  CONSTRAINT `fk_students_section` FOREIGN KEY (`section_id`) REFERENCES `sections` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_students_teacher` FOREIGN KEY (`teacher_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table structure for `subjects`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `subjects`;
CREATE TABLE `subjects` (
  `id` int NOT NULL AUTO_INCREMENT,
  `code` varchar(50) NOT NULL,
  `title` varchar(255) NOT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Dumping data for `subjects`

INSERT INTO `subjects` (`id`, `code`, `title`, `created_at`) VALUES 
('1', 'CE-401', 'Structural Engineering', '2026-08-06 16:24:57'),
('2', 'CE-402', 'Geotechnical Engineering & Foundation Design', '2026-08-06 16:24:57');

-- --------------------------------------------------------
-- Table structure for `submission_answers`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `submission_answers`;
CREATE TABLE `submission_answers` (
  `id` int NOT NULL AUTO_INCREMENT,
  `submission_id` int NOT NULL,
  `question_id` int NOT NULL,
  `student_answer` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `is_correct` tinyint(1) NOT NULL DEFAULT '0',
  `points_awarded` decimal(10,2) NOT NULL DEFAULT '0.00',
  `points_possible` decimal(10,2) NOT NULL DEFAULT '1.00',
  `feedback` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `exam_id` int DEFAULT NULL,
  `student_id` int DEFAULT NULL,
  `correct_answer` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `awarded_points` decimal(5,2) DEFAULT '0.00',
  `max_points` decimal(5,2) DEFAULT '1.00',
  `evaluation_status` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'unanswered',
  `evaluation_reason` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `confidence` decimal(5,2) DEFAULT '100.00',
  `requires_review` tinyint(1) DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_unique_sub_question` (`submission_id`,`question_id`),
  KEY `fk_sub_answers_question` (`question_id`),
  CONSTRAINT `fk_sub_answers_question` FOREIGN KEY (`question_id`) REFERENCES `exam_questions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_sub_answers_submission` FOREIGN KEY (`submission_id`) REFERENCES `exam_submissions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=1000 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Dumping data for `submission_answers`

INSERT INTO `submission_answers` (`id`, `submission_id`, `question_id`, `student_answer`, `is_correct`, `points_awarded`, `points_possible`, `feedback`, `created_at`, `exam_id`, `student_id`, `correct_answer`, `awarded_points`, `max_points`, `evaluation_status`, `evaluation_reason`, `confidence`, `requires_review`) VALUES 
('1', '500', '101', '75 mm', '0', '0.00', '1.00', NULL, '2026-08-06 16:24:57', '10', '11', '75 mm', '1.00', '1.00', 'correct', NULL, '100.00', '0'),
('2', '500', '102', 'true', '0', '0.00', '1.00', NULL, '2026-08-06 16:24:57', '10', '11', 'true', '1.00', '1.00', 'correct', NULL, '100.00', '0'),
('3', '500', '103', '88.36 kN', '0', '0.00', '1.00', NULL, '2026-08-06 16:24:57', '10', '11', '88.36 kN', '1.00', '1.00', 'correct', NULL, '100.00', '0'),
('4', '501', '101', '75 mm', '0', '0.00', '1.00', NULL, '2026-08-06 16:24:57', '10', '20', '75 mm', '1.00', '1.00', 'correct', NULL, '100.00', '0'),
('5', '501', '102', 'true', '0', '0.00', '1.00', NULL, '2026-08-06 16:24:57', '10', '20', 'true', '1.00', '1.00', 'correct', NULL, '100.00', '0'),
('6', '501', '103', '95.20 kN', '0', '0.00', '1.00', NULL, '2026-08-06 16:24:57', '10', '20', '88.36 kN', '0.00', '1.00', 'incorrect', NULL, '100.00', '0');

-- --------------------------------------------------------
-- Table structure for `submission_reprocessing_history`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `submission_reprocessing_history`;
CREATE TABLE `submission_reprocessing_history` (
  `id` int NOT NULL AUTO_INCREMENT,
  `submission_id` int NOT NULL,
  `previous_ocr_text` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `new_ocr_text` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `previous_item_scores` json DEFAULT NULL,
  `new_item_scores` json DEFAULT NULL,
  `previous_total` decimal(5,2) DEFAULT '0.00',
  `new_total` decimal(5,2) DEFAULT '0.00',
  `actor_id` int NOT NULL,
  `reason` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `submission_id` (`submission_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table structure for `submission_score_overrides`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `submission_score_overrides`;
CREATE TABLE `submission_score_overrides` (
  `id` int NOT NULL AUTO_INCREMENT,
  `submission_id` int NOT NULL,
  `old_score` decimal(10,2) NOT NULL,
  `new_score` decimal(10,2) NOT NULL,
  `reviewer_id` int NOT NULL,
  `reason` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `question_id` int DEFAULT NULL,
  `old_student_answer` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `new_student_answer` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `old_points` decimal(5,2) DEFAULT '0.00',
  `new_points` decimal(5,2) DEFAULT '0.00',
  `old_correct_answer` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `new_correct_answer` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_overrides_submission` (`submission_id`),
  KEY `idx_overrides_reviewer` (`reviewer_id`),
  CONSTRAINT `fk_overrides_reviewer` FOREIGN KEY (`reviewer_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_overrides_submission` FOREIGN KEY (`submission_id`) REFERENCES `exam_submissions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table structure for `submission_snapshots`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `submission_snapshots`;
CREATE TABLE `submission_snapshots` (
  `id` int NOT NULL AUTO_INCREMENT,
  `submission_id` int NOT NULL,
  `review_status` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `status` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `published_at` datetime DEFAULT NULL,
  `reviewed_at` datetime DEFAULT NULL,
  `total_score` decimal(5,2) DEFAULT '0.00',
  `percentage` decimal(5,2) DEFAULT '0.00',
  `correct_count` int DEFAULT '0',
  `wrong_count` int DEFAULT '0',
  `ocr_text` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `corrected_ocr_text` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `evaluation_result` json DEFAULT NULL,
  `item_answers` json DEFAULT NULL,
  `reopened_by` int NOT NULL,
  `reason` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `submission_id` (`submission_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table structure for `submission_status_history`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `submission_status_history`;
CREATE TABLE `submission_status_history` (
  `id` int NOT NULL AUTO_INCREMENT,
  `submission_id` int NOT NULL,
  `previous_status` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `new_status` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `actor_id` int NOT NULL,
  `remarks` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `submission_id` (`submission_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table structure for `system_settings`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `system_settings`;
CREATE TABLE `system_settings` (
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Dumping data for `system_settings`

INSERT INTO `system_settings` (`setting_key`, `setting_value`, `updated_at`) VALUES 
('maintenance_mode', 'off', '2026-08-06 16:13:34'),
('passing_percentage', '82.50', '2026-08-06 13:43:31');

-- --------------------------------------------------------
-- Table structure for `teacher_subject_assignments`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `teacher_subject_assignments`;
CREATE TABLE `teacher_subject_assignments` (
  `id` int NOT NULL AUTO_INCREMENT,
  `teacher_id` int NOT NULL,
  `subject` varchar(150) NOT NULL,
  `section_id` int NOT NULL,
  `school_year_id` int NOT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_assignment` (`teacher_id`,`subject`,`section_id`,`school_year_id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Dumping data for `teacher_subject_assignments`

INSERT INTO `teacher_subject_assignments` (`id`, `teacher_id`, `subject`, `section_id`, `school_year_id`, `status`, `created_at`) VALUES 
('1', '10', 'Structural Engineering', '1', '1', 'active', '2026-08-06 16:24:57'),
('2', '12', 'Geotechnical Engineering & Foundation Design', '1', '1', 'active', '2026-08-06 16:24:57');

-- --------------------------------------------------------
-- Table structure for `used_confirmation_tokens`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `used_confirmation_tokens`;
CREATE TABLE `used_confirmation_tokens` (
  `token_hash` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `teacher_id` int NOT NULL,
  `used_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `expires_at` timestamp NOT NULL,
  PRIMARY KEY (`token_hash`),
  KEY `teacher_id` (`teacher_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table structure for `user_sessions`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `user_sessions`;
CREATE TABLE `user_sessions` (
  `id` int NOT NULL AUTO_INCREMENT,
  `session_id` varchar(128) NOT NULL,
  `user_id` int NOT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text,
  `login_time` datetime NOT NULL,
  `last_activity` datetime NOT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'active',
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_session_id` (`session_id`),
  KEY `idx_user_id` (`user_id`),
  CONSTRAINT `fk_user_sessions_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------
-- Table structure for `users`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
  `id` int NOT NULL AUTO_INCREMENT,
  `fullname` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `username` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `email` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `password` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `role` enum('admin','teacher','student') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  `status` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'active',
  `is_demo` tinyint(1) NOT NULL DEFAULT '0',
  `force_password_reset` tinyint(1) NOT NULL DEFAULT '0',
  `password_changed_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_users_username` (`username`),
  UNIQUE KEY `idx_users_email` (`email`),
  KEY `idx_users_role` (`role`)
) ENGINE=InnoDB AUTO_INCREMENT=30 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Dumping data for `users`

INSERT INTO `users` (`id`, `fullname`, `username`, `email`, `password`, `role`, `created_at`, `updated_at`, `status`, `is_demo`, `force_password_reset`, `password_changed_at`) VALUES 
('1', 'System Administrator', 'admin', 'admin@questbank.edu.ph', '$2y$12$a1RtaRxLeVik7gg5PLOzL.PAqhfVjv1cLSqIzb0EWm3gxMP.g2Yiu', 'admin', '2026-08-06 16:24:57', NULL, 'active', '1', '1', NULL),
('10', 'Russel Gregorio', 'Russel', 'russel@questbank.edu.ph', '$2y$12$a1RtaRxLeVik7gg5PLOzL.PAqhfVjv1cLSqIzb0EWm3gxMP.g2Yiu', 'teacher', '2026-08-06 16:24:57', NULL, 'active', '1', '1', NULL),
('11', 'Ashley Nicole Gutierrez', 'Nicole', 'nikol@gmail.com', '$2y$12$a1RtaRxLeVik7gg5PLOzL.PAqhfVjv1cLSqIzb0EWm3gxMP.g2Yiu', 'student', '2026-08-06 16:24:57', NULL, 'active', '1', '1', NULL),
('12', 'Professor Smith', 'prof_smith', 'smith@questbank.edu.ph', '$2y$12$a1RtaRxLeVik7gg5PLOzL.PAqhfVjv1cLSqIzb0EWm3gxMP.g2Yiu', 'teacher', '2026-08-06 16:24:57', NULL, 'active', '1', '1', NULL),
('20', 'John Mark Santos', 'jmsantos', 'jmsantos@holycross.edu.ph', '$2y$12$a1RtaRxLeVik7gg5PLOzL.PAqhfVjv1cLSqIzb0EWm3gxMP.g2Yiu', 'student', '2026-08-06 16:24:57', NULL, 'active', '1', '1', NULL),
('21', 'Maria Angelica Reyes', 'm_reyes', 'mreyes@holycross.edu.ph', '$2y$12$a1RtaRxLeVik7gg5PLOzL.PAqhfVjv1cLSqIzb0EWm3gxMP.g2Yiu', 'student', '2026-08-06 16:24:57', NULL, 'active', '1', '1', NULL);

SET FOREIGN_KEY_CHECKS=1;
COMMIT;
