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
// USERS — Status Column
// ============================================
echo "\n--- users ---\n";
addColumn($pdo, 'users', 'status', "VARCHAR(20) NOT NULL DEFAULT 'active'");

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
// EXAMS — Status, Passing Score, Difficulty, AI Metadata
// ============================================
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

// ============================================
// EXAM_SUBMISSIONS — OCR, Review, File Storage, Upload Type
// ============================================
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

// ============================================
// SUBJECTS & DEPARTMENTS TABLES
// ============================================
$pdo->exec("
    CREATE TABLE IF NOT EXISTS `subjects` (
        `id` INT(11) AUTO_INCREMENT PRIMARY KEY,
        `code` VARCHAR(50) NOT NULL,
        `title` VARCHAR(255) NOT NULL,
        `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
");

// ============================================
// NEW TABLES
// ============================================
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

// ============================================
// SEED DEFAULT CREDENTIALS (Russel & Nicole)
// ============================================
$defaultPassHash = password_hash('Password123!', PASSWORD_DEFAULT);

// Seed QA Accounts for automated test suites
$qaUsers = [
    [1, 'qa_admin', 'QA Test Administrator', 'qa_admin@questbank.test', 'admin'],
    [2, 'qa_teacher_a', 'QA Test Professor Alpha', 'qa_teacher_a@questbank.test', 'teacher'],
    [3, 'qa_teacher_b', 'QA Test Professor Beta', 'qa_teacher_b@questbank.test', 'teacher'],
    [4, 'qa_student_a', 'QA Test Student Alpha', 'qa_student_a@questbank.test', 'student'],
    [5, 'qa_student_b', 'QA Test Student Beta', 'qa_student_b@questbank.test', 'student']
];

foreach ($qaUsers as $u) {
    $stmtUsr = $pdo->prepare("
        INSERT INTO users (id, username, fullname, email, password, role) 
        VALUES (?, ?, ?, ?, ?, ?) 
        ON DUPLICATE KEY UPDATE password = ?, fullname = VALUES(fullname), email = VALUES(email), role = VALUES(role)
    ");
    $stmtUsr->execute([$u[0], $u[1], $u[2], $u[3], $defaultPassHash, $u[4], $defaultPassHash]);
}

// Seed Admin: Russel
$stmtUsr = $pdo->prepare("
    INSERT INTO users (id, username, fullname, email, password, role) 
    VALUES (10, 'Russel', 'Russel Gregorio', 'russel@gmail.com', ?, 'admin') 
    ON DUPLICATE KEY UPDATE password = ?, fullname = 'Russel Gregorio', email = 'russel@gmail.com'
");
$stmtUsr->execute([$defaultPassHash, $defaultPassHash]);

// Seed Student: Nicole
$stmtUsr = $pdo->prepare("
    INSERT INTO users (id, username, fullname, email, password, role) 
    VALUES (11, 'Nicole', 'Ashley Nicole Gutierrez', 'nikol@gmail.com', ?, 'student') 
    ON DUPLICATE KEY UPDATE password = ?, fullname = 'Ashley Nicole Gutierrez', email = 'nikol@gmail.com'
");
$stmtUsr->execute([$defaultPassHash, $defaultPassHash]);

// Seed Student details for Nicole
$stmtSd = $pdo->prepare("
    INSERT INTO student_details (user_id, student_number, course, year_level, section) 
    VALUES (11, '23-2149184', 'BSCE', 4, 'A') 
    ON DUPLICATE KEY UPDATE course = 'BSCE', year_level = 4, section = 'A'
");
$stmtSd->execute();

// Seed Teacher: lasjo (jolas)
$stmtUsr = $pdo->prepare("
    INSERT INTO users (id, username, fullname, email, password, role) 
    VALUES (12, 'lasjo', 'jolas', 'lasjo@gmail.com', ?, 'teacher') 
    ON DUPLICATE KEY UPDATE password = ?, fullname = 'jolas', email = 'lasjo@gmail.com'
");
$stmtUsr->execute([$defaultPassHash, $defaultPassHash]);

// Seed Deterministic Test Exam (ID #1) for Automated E2E verification
$stmtExamSeed = $pdo->prepare("
    INSERT INTO exams (id, teacher_id, title, subject, specialization, difficulty, time_limit, total_items, passing_percentage, status, created_at)
    VALUES (1, 2, 'QA Civil Engineering Fundamentals Exam', 'Structural Engineering', 'Structural Engineering', 'medium', 45, 2, 75.00, 'active', NOW())
    ON DUPLICATE KEY UPDATE title = VALUES(title), total_items = VALUES(total_items), passing_percentage = VALUES(passing_percentage)
");
$stmtExamSeed->execute();

$stmtQ1Seed = $pdo->prepare("
    INSERT INTO exam_questions (id, exam_id, question_text, question_type, option_a, option_b, option_c, option_d, correct_answer, points)
    VALUES (1, 1, 'What is the formula for Stopping Sight Distance (SSD)?', 'multiple_choice', 'a', 'b', 'c', 'd', 'a', 1.00)
    ON DUPLICATE KEY UPDATE question_text = VALUES(question_text)
");
$stmtQ1Seed->execute();

$stmtQ2Seed = $pdo->prepare("
    INSERT INTO exam_questions (id, exam_id, question_text, question_type, option_a, option_b, option_c, option_d, correct_answer, points)
    VALUES (2, 1, 'Flexible pavement design uses CBR structural number.', 'true_false', 'true', 'false', NULL, NULL, 'true', 1.00)
    ON DUPLICATE KEY UPDATE question_text = VALUES(question_text)
");
$stmtQ2Seed->execute();

// Seed Student details for QA Student A & B
$stmtSdA = $pdo->prepare("
    INSERT INTO student_details (user_id, student_number, course, year_level, section) 
    VALUES (4, '23-QA-1001', 'BSCE', 4, 'A') 
    ON DUPLICATE KEY UPDATE course = 'BSCE', year_level = 4, section = 'A'
");
$stmtSdA->execute();

$stmtSdB = $pdo->prepare("
    INSERT INTO student_details (user_id, student_number, course, year_level, section) 
    VALUES (5, '23-QA-1002', 'BSCE', 4, 'B') 
    ON DUPLICATE KEY UPDATE course = 'BSCE', year_level = 4, section = 'B'
");
$stmtSdB->execute();

// Seed Deterministic Submissions for IDOR & Review E2E Verification
// Submission #100: Student A - Published
$stmtSub100 = $pdo->prepare("
    INSERT INTO exam_submissions (id, exam_id, student_id, teacher_id, student_name, exam_title, upload_type, correct_count, wrong_count, total_score, total_possible_score, percentage, status, review_status, created_at, published_at)
    VALUES (100, 1, 4, 2, 'QA Test Student Alpha', 'QA Civil Engineering Fundamentals Exam', 'online', 2, 0, 2.00, 2.00, 100.00, 'Pass', 'published', NOW(), NOW())
    ON DUPLICATE KEY UPDATE student_id = 4, student_name = 'QA Test Student Alpha', review_status = 'published', percentage = 100.00, correct_count = 2
");
$stmtSub100->execute();

// Submission #101: Student B - Published
$stmtSub101 = $pdo->prepare("
    INSERT INTO exam_submissions (id, exam_id, student_id, teacher_id, student_name, exam_title, upload_type, correct_count, wrong_count, total_score, total_possible_score, percentage, status, review_status, created_at, published_at)
    VALUES (101, 1, 5, 2, 'QA Test Student Beta', 'QA Civil Engineering Fundamentals Exam', 'online', 1, 1, 1.00, 2.00, 50.00, 'Fail', 'published', NOW(), NOW())
    ON DUPLICATE KEY UPDATE student_id = 5, student_name = 'QA Test Student Beta', review_status = 'published', percentage = 50.00, correct_count = 1
");
$stmtSub101->execute();

// Submission #102: Student A - Pending Review
$stmtSub102 = $pdo->prepare("
    INSERT INTO exam_submissions (id, exam_id, student_id, teacher_id, student_name, exam_title, upload_type, correct_count, wrong_count, total_score, total_possible_score, percentage, status, review_status, created_at)
    VALUES (102, 1, 4, 2, 'QA Test Student Alpha', 'QA Civil Engineering Fundamentals Exam', 'online', 1, 1, 1.00, 2.00, 50.00, 'Fail', 'pending_review', NOW())
    ON DUPLICATE KEY UPDATE review_status = 'pending_review', percentage = 50.00, correct_count = 1
");
$stmtSub102->execute();

// Seed item answers for Submission #100
$stmtAns100_1 = $pdo->prepare("
    INSERT INTO submission_answers (submission_id, exam_id, student_id, question_id, student_answer, correct_answer, awarded_points, max_points, evaluation_status)
    VALUES (100, 1, 4, 1, 'a', 'a', 1.00, 1.00, 'correct')
    ON DUPLICATE KEY UPDATE awarded_points = 1.00, evaluation_status = 'correct'
");
$stmtAns100_1->execute();

$stmtAns100_2 = $pdo->prepare("
    INSERT INTO submission_answers (submission_id, exam_id, student_id, question_id, student_answer, correct_answer, awarded_points, max_points, evaluation_status)
    VALUES (100, 1, 4, 2, 'true', 'true', 1.00, 1.00, 'correct')
    ON DUPLICATE KEY UPDATE awarded_points = 1.00, evaluation_status = 'correct'
");
$stmtAns100_2->execute();

// Seed item answers for Submission #102 (Pending Review)
$stmtAns102_1 = $pdo->prepare("
    INSERT INTO submission_answers (submission_id, exam_id, student_id, question_id, student_answer, correct_answer, awarded_points, max_points, evaluation_status)
    VALUES (102, 1, 4, 1, 'a', 'a', 1.00, 1.00, 'correct')
    ON DUPLICATE KEY UPDATE awarded_points = 1.00, evaluation_status = 'correct'
");
$stmtAns102_1->execute();

$stmtAns102_2 = $pdo->prepare("
    INSERT INTO submission_answers (submission_id, exam_id, student_id, question_id, student_answer, correct_answer, awarded_points, max_points, evaluation_status)
    VALUES (102, 1, 4, 2, 'false', 'true', 0.00, 1.00, 'incorrect')
    ON DUPLICATE KEY UPDATE awarded_points = 0.00, evaluation_status = 'incorrect'
");
$stmtAns102_2->execute();

echo "\n=== Migration Complete ===\n";
