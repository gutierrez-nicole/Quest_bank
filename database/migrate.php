<?php

require_once __DIR__ . '/../app/bootstrap.php';

try {
    $pdo = getDBConnection();
    if (!$pdo) {
        throw new Exception("Database connection failed or returned null.");
    }
    $dbName = $pdo->query("SELECT DATABASE()")->fetchColumn();

    echo "=== QuestBank Schema Migration ===\n";
    echo "Database: {$dbName}\n";
    echo "Timestamp: " . date('Y-m-d H:i:s') . "\n\n";

function columnExists($pdo, $table, $column) {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?");
    $stmt->execute([$table, $column]);
    return (int)$stmt->fetchColumn() > 0;
}

function tableExists($pdo, $table) {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?");
    $stmt->execute([$table]);
    return (int)$stmt->fetchColumn() > 0;
}

function addColumn($pdo, $table, $column, $definition) {
    if (!columnExists($pdo, $table, $column)) {
        $pdo->exec("ALTER TABLE `{$table}` ADD COLUMN `{$column}` {$definition}");
        echo "  [+] Added column {$table}.{$column}\n";
    } else {
        echo "  [=] Column {$table}.{$column} already exists\n";
    }
}

echo "\n--- users ---\n";
addColumn($pdo, 'users', 'status', "VARCHAR(20) NOT NULL DEFAULT 'active'");

echo "\n--- lesson_materials ---\n";
addColumn($pdo, 'lesson_materials', 'lesson_text', "LONGTEXT DEFAULT NULL");
addColumn($pdo, 'lesson_materials', 'processing_status', "VARCHAR(20) NOT NULL DEFAULT 'pending'");
addColumn($pdo, 'lesson_materials', 'processing_error', "TEXT DEFAULT NULL");
addColumn($pdo, 'lesson_materials', 'word_count', "INT(11) DEFAULT 0");
addColumn($pdo, 'lesson_materials', 'page_count', "INT(11) DEFAULT 1");
addColumn($pdo, 'lesson_materials', 'extracted_at', "DATETIME DEFAULT NULL");
addColumn($pdo, 'lesson_materials', 'mime_type', "VARCHAR(100) DEFAULT NULL");
addColumn($pdo, 'lesson_materials', 'original_filename', "VARCHAR(255) DEFAULT NULL");
addColumn($pdo, 'lesson_materials', 'stored_filename', "VARCHAR(255) DEFAULT NULL");

echo "\n--- exam_questions ---\n";
addColumn($pdo, 'exam_questions', 'points', "INT(11) NOT NULL DEFAULT 1");
addColumn($pdo, 'exam_questions', 'formula_latex', "TEXT DEFAULT NULL");
addColumn($pdo, 'exam_questions', 'matching_pairs', "TEXT DEFAULT NULL");
addColumn($pdo, 'exam_questions', 'explanation', "TEXT DEFAULT NULL");
addColumn($pdo, 'exam_questions', 'difficulty', "VARCHAR(20) DEFAULT 'medium'");
addColumn($pdo, 'exam_questions', 'topic', "VARCHAR(150) DEFAULT NULL");
addColumn($pdo, 'exam_questions', 'lesson_id', "INT(11) DEFAULT NULL");

echo "\n--- exams ---\n";
addColumn($pdo, 'exams', 'status', "VARCHAR(20) NOT NULL DEFAULT 'active'");
addColumn($pdo, 'exams', 'passing_percentage', "DECIMAL(5,2) NOT NULL DEFAULT 75.00");
addColumn($pdo, 'exams', 'difficulty', "VARCHAR(20) DEFAULT 'medium'");
addColumn($pdo, 'exams', 'updated_at', "TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP");
addColumn($pdo, 'exams', 'ai_metadata', "TEXT DEFAULT NULL");
addColumn($pdo, 'exams', 'lesson_ids', "VARCHAR(255) DEFAULT NULL");
addColumn($pdo, 'exams', 'generation_status', "VARCHAR(30) DEFAULT 'completed'");
addColumn($pdo, 'exams', 'generation_error', "TEXT DEFAULT NULL");
addColumn($pdo, 'exams', 'prompt_version', "VARCHAR(20) DEFAULT 'v1.0'");
addColumn($pdo, 'exams', 'ai_model', "VARCHAR(100) DEFAULT NULL");
addColumn($pdo, 'exams', 'created_by', "INT(11) DEFAULT NULL");

addColumn($pdo, 'exams', 'exam_category', "VARCHAR(30) NOT NULL DEFAULT 'regular'");
addColumn($pdo, 'exams', 'qualifying_passing_percentage', "DECIMAL(5,2) DEFAULT 75.00");
addColumn($pdo, 'exams', 'qualifying_max_attempts', "INT(11) DEFAULT 1");
addColumn($pdo, 'exams', 'qualifying_year_level', "VARCHAR(50) DEFAULT 'All Year Levels'");
addColumn($pdo, 'exams', 'qualifying_program', "VARCHAR(100) DEFAULT 'All Programs'");
addColumn($pdo, 'exams', 'qualifying_is_required', "TINYINT(1) DEFAULT 1");
addColumn($pdo, 'exams', 'qualifying_unlock_date', "DATETIME DEFAULT NULL");
addColumn($pdo, 'exams', 'qualifying_deadline', "DATETIME DEFAULT NULL");

echo "\n--- exam_submissions ---\n";
$pdo->exec("ALTER TABLE `exam_submissions` MODIFY COLUMN `upload_type` VARCHAR(30) NOT NULL DEFAULT 'scanned'");
$pdo->exec("ALTER TABLE `exam_submissions` MODIFY COLUMN `review_status` VARCHAR(30) NOT NULL DEFAULT 'draft'");
$pdo->exec("ALTER TABLE `exam_submissions` MODIFY COLUMN `status` VARCHAR(20) NOT NULL DEFAULT 'Fail'");
echo "  [*] Updated exam_submissions.upload_type, review_status, and status column types\n";
addColumn($pdo, 'exam_submissions', 'exam_id', "INT(11) DEFAULT NULL");
addColumn($pdo, 'exam_submissions', 'student_id', "INT(11) DEFAULT NULL");
addColumn($pdo, 'exam_submissions', 'total_possible_score', "INT(11) DEFAULT 0");
addColumn($pdo, 'exam_submissions', 'ocr_text', "TEXT DEFAULT NULL");
addColumn($pdo, 'exam_submissions', 'original_ocr_text', "TEXT DEFAULT NULL");
addColumn($pdo, 'exam_submissions', 'corrected_ocr_text', "TEXT DEFAULT NULL");
addColumn($pdo, 'exam_submissions', 'extraction_mode', "VARCHAR(50) DEFAULT 'image_ocr'");
addColumn($pdo, 'exam_submissions', 'ocr_confidence', "DECIMAL(5,2) DEFAULT NULL");
addColumn($pdo, 'exam_submissions', 'ocr_status', "VARCHAR(30) DEFAULT 'pending'");
addColumn($pdo, 'exam_submissions', 'ocr_error', "TEXT DEFAULT NULL");
addColumn($pdo, 'exam_submissions', 'suggested_manual_review', "TINYINT(1) DEFAULT 0");
addColumn($pdo, 'exam_submissions', 'page_count', "INT(11) DEFAULT 1");
addColumn($pdo, 'exam_submissions', 'per_page_ocr_metadata', "JSON DEFAULT NULL");
addColumn($pdo, 'exam_submissions', 'processing_duration', "DECIMAL(8,2) DEFAULT 0.00");
addColumn($pdo, 'exam_submissions', 'processed_at', "DATETIME DEFAULT NULL");
addColumn($pdo, 'exam_submissions', 'evaluation_result', "JSON DEFAULT NULL");
addColumn($pdo, 'exam_submissions', 'teacher_override_log', "JSON DEFAULT NULL");
addColumn($pdo, 'exam_submissions', 'review_status', "VARCHAR(30) NOT NULL DEFAULT 'draft'");
addColumn($pdo, 'exam_submissions', 'reviewed_by', "INT(11) DEFAULT NULL");
addColumn($pdo, 'exam_submissions', 'teacher_remarks', "TEXT DEFAULT NULL");
addColumn($pdo, 'exam_submissions', 'reviewed_at', "DATETIME DEFAULT NULL");
addColumn($pdo, 'exam_submissions', 'published_at', "DATETIME DEFAULT NULL");
addColumn($pdo, 'exam_submissions', 'file_path', "VARCHAR(500) DEFAULT NULL");
addColumn($pdo, 'exam_submissions', 'original_filename', "VARCHAR(255) DEFAULT NULL");
addColumn($pdo, 'exam_submissions', 'uploaded_file_hash', "VARCHAR(64) DEFAULT NULL");

addColumn($pdo, 'exam_submissions', 'qualification_status', "VARCHAR(30) NOT NULL DEFAULT 'pending'");
addColumn($pdo, 'exam_submissions', 'attempt_number', "INT(11) NOT NULL DEFAULT 1");

$pdo->exec("
    CREATE TABLE IF NOT EXISTS `subjects` (
        `id` INT(11) AUTO_INCREMENT PRIMARY KEY,
        `code` VARCHAR(50) NOT NULL,
        `title` VARCHAR(255) NOT NULL,
        `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
");

echo "\n--- New tables ---\n";

if (!tableExists($pdo, 'student_details')) {
    $pdo->exec("
        CREATE TABLE `student_details` (
            `id` INT(11) NOT NULL AUTO_INCREMENT,
            `user_id` INT(11) NOT NULL,
            `student_number` VARCHAR(50) DEFAULT NULL,
            `course` VARCHAR(100) DEFAULT 'BSCE',
            `year_level` VARCHAR(20) DEFAULT '3rd Year',
            `section` VARCHAR(20) DEFAULT 'A',
            `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `user_id` (`user_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
    ");
    echo "  [+] Created table student_details\n";
} else {
    echo "  [=] Table student_details already exists, verifying columns...\n";
    addColumn($pdo, 'student_details', 'student_number', "VARCHAR(50) DEFAULT NULL");
    addColumn($pdo, 'student_details', 'course', "VARCHAR(100) DEFAULT 'BSCE'");
    addColumn($pdo, 'student_details', 'year_level', "VARCHAR(20) DEFAULT '3rd Year'");
    addColumn($pdo, 'student_details', 'section', "VARCHAR(20) DEFAULT 'A'");
}

if (!tableExists($pdo, 'submission_answers')) {
    $pdo->exec("
        CREATE TABLE `submission_answers` (
            `id` INT(11) NOT NULL AUTO_INCREMENT,
            `submission_id` INT(11) NOT NULL,
            `exam_id` INT(11) NOT NULL,
            `student_id` INT(11) DEFAULT NULL,
            `question_id` INT(11) NOT NULL,
            `student_answer` TEXT DEFAULT NULL,
            `correct_answer` VARCHAR(255) DEFAULT NULL,
            `awarded_points` DECIMAL(5,2) DEFAULT 0.00,
            `max_points` DECIMAL(5,2) DEFAULT 1.00,
            `evaluation_status` VARCHAR(20) NOT NULL DEFAULT 'unanswered',
            `evaluation_reason` TEXT DEFAULT NULL,
            `confidence` DECIMAL(5,2) DEFAULT 100.00,
            `requires_review` TINYINT(1) DEFAULT 0,
            `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `submission_id` (`submission_id`),
            KEY `question_id` (`question_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
    ");
    echo "  [+] Created table submission_answers\n";
} else {
    echo "  [=] Table submission_answers already exists, verifying columns...\n";
    addColumn($pdo, 'submission_answers', 'exam_id', "INT(11) DEFAULT NULL");
    addColumn($pdo, 'submission_answers', 'student_id', "INT(11) DEFAULT NULL");
    addColumn($pdo, 'submission_answers', 'correct_answer', "VARCHAR(255) DEFAULT NULL");
    addColumn($pdo, 'submission_answers', 'awarded_points', "DECIMAL(5,2) DEFAULT 0.00");
    addColumn($pdo, 'submission_answers', 'max_points', "DECIMAL(5,2) DEFAULT 1.00");
    addColumn($pdo, 'submission_answers', 'evaluation_status', "VARCHAR(20) NOT NULL DEFAULT 'unanswered'");
    addColumn($pdo, 'submission_answers', 'evaluation_reason', "TEXT DEFAULT NULL");
    addColumn($pdo, 'submission_answers', 'confidence', "DECIMAL(5,2) DEFAULT 100.00");
    addColumn($pdo, 'submission_answers', 'requires_review', "TINYINT(1) DEFAULT 0");
}

if (!tableExists($pdo, 'submission_score_overrides')) {
    $pdo->exec("
        CREATE TABLE `submission_score_overrides` (
            `id` INT(11) NOT NULL AUTO_INCREMENT,
            `submission_id` INT(11) NOT NULL,
            `question_id` INT(11) DEFAULT NULL,
            `old_student_answer` TEXT DEFAULT NULL,
            `new_student_answer` TEXT DEFAULT NULL,
            `old_points` DECIMAL(5,2) DEFAULT 0.00,
            `new_points` DECIMAL(5,2) DEFAULT 0.00,
            `old_score` DECIMAL(5,2) DEFAULT 0.00,
            `new_score` DECIMAL(5,2) DEFAULT 0.00,
            `old_correct_answer` VARCHAR(255) DEFAULT NULL,
            `new_correct_answer` VARCHAR(255) DEFAULT NULL,
            `reviewer_id` INT(11) NOT NULL,
            `reason` TEXT DEFAULT NULL,
            `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `submission_id` (`submission_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
    ");
    echo "  [+] Created table submission_score_overrides\n";
} else {
    echo "  [=] Table submission_score_overrides already exists, verifying columns...\n";
    addColumn($pdo, 'submission_score_overrides', 'question_id', "INT(11) DEFAULT NULL");
    addColumn($pdo, 'submission_score_overrides', 'old_student_answer', "TEXT DEFAULT NULL");
    addColumn($pdo, 'submission_score_overrides', 'new_student_answer', "TEXT DEFAULT NULL");
    addColumn($pdo, 'submission_score_overrides', 'old_points', "DECIMAL(5,2) DEFAULT 0.00");
    addColumn($pdo, 'submission_score_overrides', 'new_points', "DECIMAL(5,2) DEFAULT 0.00");
    addColumn($pdo, 'submission_score_overrides', 'old_correct_answer', "VARCHAR(255) DEFAULT NULL");
    addColumn($pdo, 'submission_score_overrides', 'new_correct_answer', "VARCHAR(255) DEFAULT NULL");
}

if (!tableExists($pdo, 'exam_assignments')) {
    $pdo->exec("
        CREATE TABLE `exam_assignments` (
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
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
    ");
    echo "  [+] Created table exam_assignments\n";
} else {
    echo "  [=] Table exam_assignments already exists\n";
}
if (!tableExists($pdo, 'submission_reprocessing_history')) {
    $pdo->exec("
        CREATE TABLE `submission_reprocessing_history` (
            `id` INT(11) NOT NULL AUTO_INCREMENT,
            `submission_id` INT(11) NOT NULL,
            `previous_ocr_text` LONGTEXT DEFAULT NULL,
            `new_ocr_text` LONGTEXT DEFAULT NULL,
            `previous_item_scores` JSON DEFAULT NULL,
            `new_item_scores` JSON DEFAULT NULL,
            `previous_total` DECIMAL(5,2) DEFAULT 0.00,
            `new_total` DECIMAL(5,2) DEFAULT 0.00,
            `actor_id` INT(11) NOT NULL,
            `reason` TEXT DEFAULT NULL,
            `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `submission_id` (`submission_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
    ");
    echo "  [+] Created table submission_reprocessing_history\n";
} else {
    echo "  [=] Table submission_reprocessing_history already exists\n";
}

if (!tableExists($pdo, 'submission_snapshots')) {
    $pdo->exec("
        CREATE TABLE `submission_snapshots` (
            `id` INT(11) NOT NULL AUTO_INCREMENT,
            `submission_id` INT(11) NOT NULL,
            `review_status` VARCHAR(30) NOT NULL,
            `status` VARCHAR(20) NOT NULL,
            `published_at` DATETIME DEFAULT NULL,
            `reviewed_at` DATETIME DEFAULT NULL,
            `total_score` DECIMAL(5,2) DEFAULT 0.00,
            `percentage` DECIMAL(5,2) DEFAULT 0.00,
            `correct_count` INT(11) DEFAULT 0,
            `wrong_count` INT(11) DEFAULT 0,
            `ocr_text` LONGTEXT DEFAULT NULL,
            `corrected_ocr_text` LONGTEXT DEFAULT NULL,
            `evaluation_result` JSON DEFAULT NULL,
            `item_answers` JSON DEFAULT NULL,
            `reopened_by` INT(11) NOT NULL,
            `reason` TEXT NOT NULL,
            `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `submission_id` (`submission_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
    ");
    echo "  [+] Created table submission_snapshots\n";
} else {
    echo "  [=] Table submission_snapshots already exists\n";
}

if (!tableExists($pdo, 'submission_status_history')) {
    $pdo->exec("
        CREATE TABLE `submission_status_history` (
            `id` INT(11) NOT NULL AUTO_INCREMENT,
            `submission_id` INT(11) NOT NULL,
            `previous_status` VARCHAR(30) DEFAULT NULL,
            `new_status` VARCHAR(30) NOT NULL,
            `actor_id` INT(11) NOT NULL,
            `remarks` TEXT DEFAULT NULL,
            `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `submission_id` (`submission_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
    ");
    echo "  [+] Created table submission_status_history\n";
} else {
    echo "  [=] Table submission_status_history already exists\n";
}
echo "\n--- Epic 2.2 Cross-Period Lesson Pool ---\n";
addColumn($pdo, 'lesson_materials', 'academic_period', "VARCHAR(20) NOT NULL DEFAULT 'general'");
addColumn($pdo, 'lesson_materials', 'semester', "VARCHAR(20) DEFAULT NULL");
addColumn($pdo, 'lesson_materials', 'school_year', "VARCHAR(20) DEFAULT NULL");
addColumn($pdo, 'lesson_materials', 'year_level', "VARCHAR(50) DEFAULT NULL");
addColumn($pdo, 'lesson_materials', 'program', "VARCHAR(100) DEFAULT NULL");

addColumn($pdo, 'exams', 'covered_periods', "VARCHAR(255) DEFAULT NULL");
addColumn($pdo, 'exams', 'source_lesson_count', "INT(11) DEFAULT 0");
addColumn($pdo, 'exams', 'generation_source_type', "VARCHAR(50) DEFAULT NULL");
addColumn($pdo, 'exams', 'generation_batch_id', "VARCHAR(64) DEFAULT NULL");

if (!tableExists($pdo, 'generated_question_sources')) {
    $pdo->exec("
        CREATE TABLE `generated_question_sources` (
            `id` INT(11) NOT NULL AUTO_INCREMENT,
            `question_id` INT(11) NOT NULL,
            `lesson_id` INT(11) NOT NULL,
            `academic_period` VARCHAR(20) DEFAULT NULL,
            `source_topic` VARCHAR(150) DEFAULT NULL,
            `source_confidence` VARCHAR(50) DEFAULT 'high',
            `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `question_id` (`question_id`),
            KEY `lesson_id` (`lesson_id`),
            UNIQUE KEY `uq_question_lesson` (`question_id`, `lesson_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
    ");
    echo "  [+] Created table generated_question_sources\n";
} else {
    echo "  [=] Table generated_question_sources already exists\n";
}
addColumn($pdo, 'generated_question_sources', 'source_topic', "VARCHAR(150) DEFAULT NULL");
addColumn($pdo, 'generated_question_sources', 'source_confidence', "VARCHAR(50) DEFAULT 'high'");
addColumn($pdo, 'generated_question_sources', 'source_review_required', "TINYINT(1) DEFAULT 0");
addColumn($pdo, 'generated_question_sources', 'source_verified_by', "INT(11) DEFAULT NULL");
addColumn($pdo, 'generated_question_sources', 'source_verified_at', "TIMESTAMP NULL DEFAULT NULL");
addColumn($pdo, 'generated_question_sources', 'source_verification_note', "TEXT DEFAULT NULL");

addColumn($pdo, 'exam_questions', 'source_review_required', "TINYINT(1) DEFAULT 0");

if (!tableExists($pdo, 'used_confirmation_tokens')) {
    $pdo->exec("
        CREATE TABLE `used_confirmation_tokens` (
            `token_hash` VARCHAR(64) NOT NULL,
            `teacher_id` INT(11) NOT NULL,
            `used_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `expires_at` TIMESTAMP NOT NULL,
            PRIMARY KEY (`token_hash`),
            KEY `teacher_id` (`teacher_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
    ");
    echo "  [+] Created table used_confirmation_tokens\n";
} else {
    echo "  [=] Table used_confirmation_tokens already exists\n";
}

if (!tableExists($pdo, 'ai_generation_batches')) {
    $pdo->exec("
        CREATE TABLE `ai_generation_batches` (
            `id` INT(11) NOT NULL AUTO_INCREMENT,
            `generation_batch_id` VARCHAR(64) NOT NULL,
            `teacher_id` INT(11) NOT NULL,
            `selected_lesson_ids` TEXT DEFAULT NULL,
            `selected_lesson_titles` TEXT DEFAULT NULL,
            `selected_periods` VARCHAR(255) DEFAULT NULL,
            `selected_subject` VARCHAR(150) DEFAULT NULL,
            `semester` VARCHAR(50) DEFAULT NULL,
            `school_year` VARCHAR(50) DEFAULT NULL,
            `year_level` VARCHAR(50) DEFAULT NULL,
            `program` VARCHAR(100) DEFAULT NULL,
            `total_selected_words` INT(11) DEFAULT 0,
            `estimated_tokens` INT(11) DEFAULT 0,
            `ai_model` VARCHAR(100) DEFAULT NULL,
            `generation_duration` FLOAT DEFAULT 0,
            `requested_question_count` INT(11) DEFAULT 0,
            `generated_question_count` INT(11) DEFAULT 0,
            `failed_question_count` INT(11) DEFAULT 0,
            `warnings` TEXT DEFAULT NULL,
            `batch_status` VARCHAR(30) DEFAULT 'completed',
            `failed_chunk_count` INT(11) DEFAULT 0,
            `affected_lesson_ids` TEXT DEFAULT NULL,
            `failure_messages` TEXT DEFAULT NULL,
            `teacher_acknowledged_at` TIMESTAMP NULL DEFAULT NULL,
            `teacher_acknowledged_by` INT(11) DEFAULT NULL,
            `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uq_batch_id` (`generation_batch_id`),
            KEY `teacher_id` (`teacher_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
    ");
    echo "  [+] Created table ai_generation_batches\n";
} else {
    echo "  [=] Table ai_generation_batches already exists\n";
}
addColumn($pdo, 'ai_generation_batches', 'semester', "VARCHAR(50) DEFAULT NULL");
addColumn($pdo, 'ai_generation_batches', 'school_year', "VARCHAR(50) DEFAULT NULL");
addColumn($pdo, 'ai_generation_batches', 'year_level', "VARCHAR(50) DEFAULT NULL");
addColumn($pdo, 'ai_generation_batches', 'program', "VARCHAR(100) DEFAULT NULL");
addColumn($pdo, 'ai_generation_batches', 'batch_status', "VARCHAR(30) DEFAULT 'completed'");
addColumn($pdo, 'ai_generation_batches', 'failed_chunk_count', "INT(11) DEFAULT 0");
addColumn($pdo, 'ai_generation_batches', 'affected_lesson_ids', "TEXT DEFAULT NULL");
addColumn($pdo, 'ai_generation_batches', 'failure_messages', "TEXT DEFAULT NULL");
addColumn($pdo, 'ai_generation_batches', 'teacher_acknowledged_at', "TIMESTAMP NULL DEFAULT NULL");
addColumn($pdo, 'ai_generation_batches', 'teacher_acknowledged_by', "INT(11) DEFAULT NULL");
addColumn($pdo, 'ai_generation_batches', 'acknowledgement_reason', "TEXT DEFAULT NULL");
addColumn($pdo, 'ai_generation_batches', 'acknowledgement_token_hash', "VARCHAR(64) DEFAULT NULL");
addColumn($pdo, 'ai_generation_batches', 'batch_consumed_at', "TIMESTAMP NULL DEFAULT NULL");
addColumn($pdo, 'ai_generation_batches', 'batch_consumed_by', "INT(11) DEFAULT NULL");
addColumn($pdo, 'ai_generation_batches', 'saved_exam_id', "INT(11) DEFAULT NULL");
addColumn($pdo, 'ai_generation_batches', 'chunk_generation_results', "LONGTEXT DEFAULT NULL");
addColumn($pdo, 'ai_generation_batches', 'questions_per_lesson', "LONGTEXT DEFAULT NULL");
addColumn($pdo, 'ai_generation_batches', 'questions_per_period', "LONGTEXT DEFAULT NULL");
addColumn($pdo, 'ai_generation_batches', 'uncovered_lesson_ids', "LONGTEXT DEFAULT NULL");
addColumn($pdo, 'ai_generation_batches', 'uncovered_periods', "LONGTEXT DEFAULT NULL");
addColumn($pdo, 'ai_generation_batches', 'refill_attempt_count', "INT(11) DEFAULT 0");
addColumn($pdo, 'ai_generation_batches', 'refill_warnings', "LONGTEXT DEFAULT NULL");
addColumn($pdo, 'ai_generation_batches', 'simulated_scenario', "VARCHAR(100) DEFAULT NULL");
addColumn($pdo, 'ai_generation_batches', 'failed_chunk_index', "INT(11) DEFAULT NULL");
addColumn($pdo, 'ai_generation_batches', 'refill_target_chunk_index', "INT(11) DEFAULT NULL");
addColumn($pdo, 'ai_generation_batches', 'refill_target_lesson_ids', "LONGTEXT DEFAULT NULL");
addColumn($pdo, 'ai_generation_batches', 'refill_target_periods', "LONGTEXT DEFAULT NULL");
addColumn($pdo, 'ai_generation_batches', 'refill_generated_count', "INT(11) DEFAULT 0");
addColumn($pdo, 'ai_generation_batches', 'initial_questions_per_lesson', "LONGTEXT DEFAULT NULL");
addColumn($pdo, 'ai_generation_batches', 'initial_questions_per_period', "LONGTEXT DEFAULT NULL");
addColumn($pdo, 'ai_generation_batches', 'initial_uncovered_lesson_ids', "LONGTEXT DEFAULT NULL");
addColumn($pdo, 'ai_generation_batches', 'initial_uncovered_periods', "LONGTEXT DEFAULT NULL");
addColumn($pdo, 'ai_generation_batches', 'affected_periods', "LONGTEXT DEFAULT NULL");
addColumn($pdo, 'ai_generation_batches', 'failed_chunk_indexes', "LONGTEXT DEFAULT NULL");
addColumn($pdo, 'ai_generation_batches', 'failed_chunks', "LONGTEXT DEFAULT NULL");
addColumn($pdo, 'ai_generation_batches', 'period_weighting_mode', "VARCHAR(50) DEFAULT 'equal'");
addColumn($pdo, 'ai_generation_batches', 'requested_period_distribution', "LONGTEXT DEFAULT NULL");
addColumn($pdo, 'ai_generation_batches', 'actual_period_distribution', "LONGTEXT DEFAULT NULL");
addColumn($pdo, 'ai_generation_batches', 'requested_question_blueprint', "LONGTEXT DEFAULT NULL");
addColumn($pdo, 'ai_generation_batches', 'actual_question_distribution', "LONGTEXT DEFAULT NULL");
addColumn($pdo, 'ai_generation_batches', 'requested_difficulty_distribution', "LONGTEXT DEFAULT NULL");
addColumn($pdo, 'ai_generation_batches', 'actual_difficulty_distribution', "LONGTEXT DEFAULT NULL");
addColumn($pdo, 'ai_generation_batches', 'duplicate_count', "INT(11) DEFAULT 0");
addColumn($pdo, 'ai_generation_batches', 'replacement_attempt_count', "INT(11) DEFAULT 0");
addColumn($pdo, 'ai_generation_batches', 'duplicate_warnings', "LONGTEXT DEFAULT NULL");


$defaultPassHash = password_hash('Password123!', PASSWORD_DEFAULT);

$stmtUsr = $pdo->prepare("
    INSERT INTO users (id, username, fullname, email, password, role) 
    VALUES (10, 'Russel', 'Russel Gregorio', 'russel@questbank.edu.ph', ?, 'teacher') 
    ON DUPLICATE KEY UPDATE password = ?, fullname = 'Russel Gregorio', email = 'russel@questbank.edu.ph', role = 'teacher'
");
$stmtUsr->execute([$defaultPassHash, $defaultPassHash]);

$stmtUsr = $pdo->prepare("
    INSERT INTO users (id, username, fullname, email, password, role) 
    VALUES (11, 'Nicole', 'Ashley Nicole Gutierrez', 'nikol@gmail.com', ?, 'student') 
    ON DUPLICATE KEY UPDATE password = ?, fullname = 'Ashley Nicole Gutierrez', email = 'nikol@gmail.com'
");
$stmtUsr->execute([$defaultPassHash, $defaultPassHash]);

$stmtSd = $pdo->prepare("
    INSERT INTO student_details (user_id, student_number, course, year_level, section) 
    VALUES (11, '23-2149184', 'BSCE', 4, 'A') 
    ON DUPLICATE KEY UPDATE course = 'BSCE', year_level = 4, section = 'A'
");
$stmtSd->execute();

$stmtUsr = $pdo->prepare("
    INSERT INTO users (id, username, fullname, email, password, role) 
    VALUES (12, 'lasjo', 'jolas', 'lasjo@gmail.com', ?, 'teacher') 
    ON DUPLICATE KEY UPDATE password = ?, fullname = 'jolas', email = 'lasjo@gmail.com'
");
$stmtUsr->execute([$defaultPassHash, $defaultPassHash]);

echo "\n=== Migration Complete ===\n";
exit(0);
} catch (Throwable $e) {
    echo "\n[CRITICAL FAILURE] Migration aborted: " . $e->getMessage() . "\n";
    exit(1);
}
