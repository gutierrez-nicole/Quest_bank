<?php
/**
 * QuestBank Schema Migration Runner
 * Idempotent — safe to run multiple times.
 * Adds all columns and tables required for the full capstone system.
 */

require_once __DIR__ . '/../app/bootstrap.php';

$pdo = getDBConnection();
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

// ============================================
// LESSON_MATERIALS — Extraction Engine Columns
// ============================================
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

// ============================================
// EXAM_QUESTIONS — Extended Question Fields
// ============================================
echo "\n--- exam_questions ---\n";
addColumn($pdo, 'exam_questions', 'points', "INT(11) NOT NULL DEFAULT 1");
addColumn($pdo, 'exam_questions', 'formula_latex', "TEXT DEFAULT NULL");
addColumn($pdo, 'exam_questions', 'matching_pairs', "TEXT DEFAULT NULL");
addColumn($pdo, 'exam_questions', 'explanation', "TEXT DEFAULT NULL");
addColumn($pdo, 'exam_questions', 'difficulty', "VARCHAR(20) DEFAULT 'medium'");
addColumn($pdo, 'exam_questions', 'topic', "VARCHAR(150) DEFAULT NULL");
addColumn($pdo, 'exam_questions', 'lesson_id', "INT(11) DEFAULT NULL");

// ============================================
// EXAMS — Status, Passing Score, Difficulty
// ============================================
echo "\n--- exams ---\n";
addColumn($pdo, 'exams', 'status', "VARCHAR(20) NOT NULL DEFAULT 'active'");
addColumn($pdo, 'exams', 'passing_percentage', "DECIMAL(5,2) NOT NULL DEFAULT 75.00");
addColumn($pdo, 'exams', 'difficulty', "VARCHAR(20) DEFAULT 'medium'");
addColumn($pdo, 'exams', 'updated_at', "TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP");

// ============================================
// EXAM_SUBMISSIONS — OCR, Review, File Storage
// ============================================
echo "\n--- exam_submissions ---\n";
addColumn($pdo, 'exam_submissions', 'total_possible_score', "INT(11) DEFAULT 0");
addColumn($pdo, 'exam_submissions', 'ocr_text', "TEXT DEFAULT NULL");
addColumn($pdo, 'exam_submissions', 'ocr_confidence', "DECIMAL(5,2) DEFAULT 0.00");
addColumn($pdo, 'exam_submissions', 'ocr_status', "VARCHAR(30) DEFAULT 'pending'");
addColumn($pdo, 'exam_submissions', 'ocr_error', "TEXT DEFAULT NULL");
addColumn($pdo, 'exam_submissions', 'suggested_manual_review', "TINYINT(1) DEFAULT 0");
addColumn($pdo, 'exam_submissions', 'page_count', "INT(11) DEFAULT 1");
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

// ============================================
// NEW TABLES
// ============================================
echo "\n--- New tables ---\n";

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
    echo "  [=] Table submission_answers already exists\n";
}

if (!tableExists($pdo, 'submission_score_overrides')) {
    $pdo->exec("
        CREATE TABLE `submission_score_overrides` (
            `id` INT(11) NOT NULL AUTO_INCREMENT,
            `submission_id` INT(11) NOT NULL,
            `old_score` DECIMAL(5,2) DEFAULT 0.00,
            `new_score` DECIMAL(5,2) DEFAULT 0.00,
            `reviewer_id` INT(11) NOT NULL,
            `reason` TEXT DEFAULT NULL,
            `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `submission_id` (`submission_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
    ");
    echo "  [+] Created table submission_score_overrides\n";
} else {
    echo "  [=] Table submission_score_overrides already exists\n";
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

echo "\n=== Migration Complete ===\n";
