-- QuestBank Schema Migration: Add missing lesson_materials columns
-- Run this after the base bankquest_db.sql schema is imported

-- Add missing columns to lesson_materials if they don't exist
ALTER TABLE `lesson_materials`
  ADD COLUMN IF NOT EXISTS `lesson_text` LONGTEXT DEFAULT NULL AFTER `file_size`,
  ADD COLUMN IF NOT EXISTS `processing_status` ENUM('pending','processing','completed','failed') NOT NULL DEFAULT 'pending' AFTER `lesson_text`,
  ADD COLUMN IF NOT EXISTS `processing_error` TEXT DEFAULT NULL AFTER `processing_status`,
  ADD COLUMN IF NOT EXISTS `word_count` INT(11) DEFAULT 0 AFTER `processing_error`,
  ADD COLUMN IF NOT EXISTS `page_count` INT(11) DEFAULT 1 AFTER `word_count`,
  ADD COLUMN IF NOT EXISTS `extracted_at` DATETIME DEFAULT NULL AFTER `page_count`,
  ADD COLUMN IF NOT EXISTS `mime_type` VARCHAR(100) DEFAULT NULL AFTER `extracted_at`,
  ADD COLUMN IF NOT EXISTS `original_filename` VARCHAR(255) DEFAULT NULL AFTER `mime_type`,
  ADD COLUMN IF NOT EXISTS `stored_filename` VARCHAR(255) DEFAULT NULL AFTER `original_filename`;

-- Ensure canonical question_type column format and data migration
ALTER TABLE `exam_questions` MODIFY COLUMN `question_type` VARCHAR(50) NOT NULL DEFAULT 'multiple_choice';
UPDATE `exam_questions` SET `question_type` = 'fill_blank' WHERE `question_type` IN ('fill_in_the_blank', 'fill_in_blank');
UPDATE `exam_questions` SET `question_type` = 'matching' WHERE `question_type` IN ('matching_type', 'matching_pairs');

-- Add missing columns to exam_questions if they don't exist
ALTER TABLE `exam_questions`
  ADD COLUMN IF NOT EXISTS `points` INT(11) NOT NULL DEFAULT 1 AFTER `correct_answer`,
  ADD COLUMN IF NOT EXISTS `formula_latex` TEXT DEFAULT NULL AFTER `points`,
  ADD COLUMN IF NOT EXISTS `matching_pairs` TEXT DEFAULT NULL AFTER `formula_latex`,
  ADD COLUMN IF NOT EXISTS `explanation` TEXT DEFAULT NULL AFTER `matching_pairs`,
  ADD COLUMN IF NOT EXISTS `difficulty` VARCHAR(20) DEFAULT 'medium' AFTER `explanation`,
  ADD COLUMN IF NOT EXISTS `topic` VARCHAR(150) DEFAULT NULL AFTER `difficulty`,
  ADD COLUMN IF NOT EXISTS `lesson_id` INT(11) DEFAULT NULL AFTER `topic`;

-- Add missing columns to exams if they don't exist
ALTER TABLE `exams`
  ADD COLUMN IF NOT EXISTS `status` ENUM('draft','active','closed') NOT NULL DEFAULT 'active' AFTER `total_items`,
  ADD COLUMN IF NOT EXISTS `passing_percentage` DECIMAL(5,2) NOT NULL DEFAULT 75.00 AFTER `status`,
  ADD COLUMN IF NOT EXISTS `difficulty` VARCHAR(20) DEFAULT 'medium' AFTER `passing_percentage`,
  ADD COLUMN IF NOT EXISTS `updated_at` TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP AFTER `created_at`;

-- Add missing columns to exam_submissions if they don't exist
ALTER TABLE `exam_submissions`
  ADD COLUMN IF NOT EXISTS `total_possible_score` INT(11) DEFAULT 0 AFTER `total_score`,
  ADD COLUMN IF NOT EXISTS `ocr_text` TEXT DEFAULT NULL AFTER `raw_ocr_data`,
  ADD COLUMN IF NOT EXISTS `ocr_confidence` DECIMAL(5,2) DEFAULT 0.00 AFTER `ocr_text`,
  ADD COLUMN IF NOT EXISTS `ocr_status` ENUM('pending','processing','completed','manual_review_required','failed') DEFAULT 'pending' AFTER `ocr_confidence`,
  ADD COLUMN IF NOT EXISTS `ocr_error` TEXT DEFAULT NULL AFTER `ocr_status`,
  ADD COLUMN IF NOT EXISTS `suggested_manual_review` TINYINT(1) DEFAULT 0 AFTER `ocr_error`,
  ADD COLUMN IF NOT EXISTS `page_count` INT(11) DEFAULT 1 AFTER `suggested_manual_review`,
  ADD COLUMN IF NOT EXISTS `evaluation_result` JSON DEFAULT NULL AFTER `page_count`,
  ADD COLUMN IF NOT EXISTS `teacher_override_log` JSON DEFAULT NULL AFTER `evaluation_result`,
  ADD COLUMN IF NOT EXISTS `review_status` ENUM('draft','pending_review','reviewed','published','archived') NOT NULL DEFAULT 'draft' AFTER `teacher_override_log`,
  ADD COLUMN IF NOT EXISTS `reviewed_by` INT(11) DEFAULT NULL AFTER `review_status`,
  ADD COLUMN IF NOT EXISTS `teacher_remarks` TEXT DEFAULT NULL AFTER `reviewed_by`,
  ADD COLUMN IF NOT EXISTS `reviewed_at` DATETIME DEFAULT NULL AFTER `teacher_remarks`,
  ADD COLUMN IF NOT EXISTS `published_at` DATETIME DEFAULT NULL AFTER `reviewed_at`,
  ADD COLUMN IF NOT EXISTS `file_path` VARCHAR(500) DEFAULT NULL AFTER `published_at`,
  ADD COLUMN IF NOT EXISTS `original_filename` VARCHAR(255) DEFAULT NULL AFTER `file_path`,
  ADD COLUMN IF NOT EXISTS `uploaded_file_hash` VARCHAR(64) DEFAULT NULL AFTER `original_filename`;

-- Create submission_answers table for item-level scoring
CREATE TABLE IF NOT EXISTS `submission_answers` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `submission_id` INT(11) NOT NULL,
  `exam_id` INT(11) NOT NULL,
  `student_id` INT(11) DEFAULT NULL,
  `question_id` INT(11) NOT NULL,
  `student_answer` TEXT DEFAULT NULL,
  `correct_answer` VARCHAR(255) DEFAULT NULL,
  `awarded_points` DECIMAL(5,2) DEFAULT 0.00,
  `max_points` DECIMAL(5,2) DEFAULT 1.00,
  `evaluation_status` ENUM('correct','incorrect','partial','unanswered','flagged') NOT NULL DEFAULT 'unanswered',
  `evaluation_reason` TEXT DEFAULT NULL,
  `confidence` DECIMAL(5,2) DEFAULT 100.00,
  `requires_review` TINYINT(1) DEFAULT 0,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `submission_id` (`submission_id`),
  KEY `question_id` (`question_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Create submission_score_overrides for teacher audit trail
CREATE TABLE IF NOT EXISTS `submission_score_overrides` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `submission_id` INT(11) NOT NULL,
  `old_score` DECIMAL(5,2) DEFAULT 0.00,
  `new_score` DECIMAL(5,2) DEFAULT 0.00,
  `reviewer_id` INT(11) NOT NULL,
  `reason` TEXT DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `submission_id` (`submission_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Create exam_assignments table for deny-by-default access
CREATE TABLE IF NOT EXISTS `exam_assignments` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `exam_id` INT(11) NOT NULL,
  `student_id` INT(11) DEFAULT NULL,
  `section_id` INT(11) DEFAULT NULL,
  `assigned_by` INT(11) NOT NULL,
  `available_from` DATETIME DEFAULT NULL,
  `available_until` DATETIME DEFAULT NULL,
  `max_attempts` INT(11) DEFAULT 1,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `exam_id` (`exam_id`),
  KEY `student_id` (`student_id`),
  KEY `section_id` (`section_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
