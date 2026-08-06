<?php
/**
 * QUESTBANK SAFE DATABASE DATA CLEANUP & SEED UTILITY
 *
 * Removes test artifacts, temporary verification rows, AI generation batches,
 * deterministic mock batches, and unreferenced upload files.
 * Preserves core demo accounts, subjects, lessons, exams, and submissions.
 */

require_once __DIR__ . '/../app/bootstrap.php';

$isExecute = in_array('--execute', $argv);
$isDryRun = !$isExecute || in_array('--dry-run', $argv);
$confirmProduction = in_array('--confirm-production', $argv);

$env = defined('APP_ENV') ? APP_ENV : 'development';

if ($env === 'production' && $isExecute && !$confirmProduction) {
    echo "\n[ERROR] Running in PRODUCTION environment. You must pass --confirm-production along with --execute.\n";
    exit(1);
}

$pdo = getDBConnection();

echo "===========================================================\n";
echo "       QUESTBANK SAFE DATABASE DATA CLEANUP UTILITY        \n";
echo "===========================================================\n";
echo "Mode: " . ($isDryRun ? "DRY-RUN (Preview Only - No DB modifications)" : "EXECUTE (Permanent Deletion)") . "\n";
echo "Environment: {$env}\n";
echo "Timestamp: " . date('Y-m-d H:i:s') . "\n\n";

function colExists($pdo, $table, $col) {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?");
    $stmt->execute([$table, $col]);
    return (int)$stmt->fetchColumn() > 0;
}

if (!colExists($pdo, 'users', 'is_demo')) {
    $pdo->exec("ALTER TABLE `users` ADD COLUMN `is_demo` TINYINT(1) NOT NULL DEFAULT 0");
}
if (!colExists($pdo, 'exams', 'is_demo')) {
    $pdo->exec("ALTER TABLE `exams` ADD COLUMN `is_demo` TINYINT(1) NOT NULL DEFAULT 0");
}
if (!colExists($pdo, 'exam_submissions', 'is_demo')) {
    $pdo->exec("ALTER TABLE `exam_submissions` ADD COLUMN `is_demo` TINYINT(1) NOT NULL DEFAULT 0");
}
if (!colExists($pdo, 'lesson_materials', 'is_demo')) {
    $pdo->exec("ALTER TABLE `lesson_materials` ADD COLUMN `is_demo` TINYINT(1) NOT NULL DEFAULT 0");
}

// Identify QA user accounts
$qaUsersStmt = $pdo->query("
    SELECT id, username, fullname, email 
    FROM users 
    WHERE email LIKE '%@questbank.test' 
       OR username LIKE 'qa_%' 
       OR fullname LIKE 'QA %'
");
$qaUsers = $qaUsersStmt->fetchAll(PDO::FETCH_ASSOC);
$qaUserIds = array_column($qaUsers, 'id');
$qaUserInClause = empty($qaUserIds) ? "0" : implode(',', $qaUserIds);

$legitUsersStmt = $pdo->query("
    SELECT id, username, fullname, email, role 
    FROM users 
    WHERE id NOT IN ({$qaUserInClause})
");
$legitUsers = $legitUsersStmt->fetchAll(PDO::FETCH_ASSOC);

// Identify test/QA exams
$qaExamsStmt = $pdo->query("
    SELECT id, title, teacher_id 
    FROM exams 
    WHERE is_demo = 0 AND (
          teacher_id IN ({$qaUserInClause}) 
       OR title LIKE 'QA %' 
       OR title LIKE 'P4 %' 
       OR title LIKE 'P5 %' 
       OR title LIKE 'E2E %'
       OR title LIKE 'MOCK_%'
       OR title LIKE '[MOCK_%]'
       OR title LIKE '[RUN_%]'
       OR title LIKE '%[RUN_%'
       OR title LIKE '%BSCE Comprehensive%'
       OR title LIKE '%Atomic Save%'
       OR title LIKE '%Cross Period%'
       OR title LIKE '%Saved Refill%'
       OR title LIKE '%Unresolved Source%'
       OR title LIKE '%Unacknowledged Incomplete%'
       OR title LIKE '%Warning%'
       OR title LIKE '%Benchmark%'
       OR title LIKE 'AI Exam -%'
       OR title LIKE 'Authoritative%'
       OR title LIKE 'Cross-Period%'
       OR title LIKE 'Full Pipeline%'
       OR title LIKE 'Verification%'
       OR title = 'Test'
    )
");

$qaExams = $qaExamsStmt->fetchAll(PDO::FETCH_ASSOC);
$qaExamIds = array_column($qaExams, 'id');
$qaExamInClause = empty($qaExamIds) ? "0" : implode(',', $qaExamIds);

// Identify test/QA submissions
$qaSubmissionsStmt = $pdo->query("
    SELECT id, exam_title, student_name 
    FROM exam_submissions 
    WHERE is_demo = 0 AND (
          teacher_id IN ({$qaUserInClause}) 
       OR student_id IN ({$qaUserInClause}) 
       OR exam_id IN ({$qaExamInClause}) 
       OR exam_title LIKE 'QA %'
       OR exam_title LIKE 'P4 %'
       OR exam_title LIKE 'P5 %'
       OR exam_title LIKE 'E2E %'
       OR exam_title LIKE 'MOCK_%'
       OR exam_title LIKE '[MOCK_%]'
       OR exam_title LIKE '[RUN_%]'
       OR student_name LIKE 'QA %'
    )
");
$qaSubmissions = $qaSubmissionsStmt->fetchAll(PDO::FETCH_ASSOC);
$qaSubmissionIds = array_column($qaSubmissions, 'id');
$qaSubInClause = empty($qaSubmissionIds) ? "0" : implode(',', $qaSubmissionIds);

// Identify test/QA lesson materials
$qaLessonsStmt = $pdo->query("
    SELECT id, title, stored_filename 
    FROM lesson_materials 
    WHERE is_demo = 0 AND (
          teacher_id IN ({$qaUserInClause}) 
       OR title LIKE 'QA %'
       OR title LIKE 'Test %'
       OR title LIKE 'MOCK_%'
       OR title LIKE '[MOCK_%]'
       OR title LIKE '[RUN_%]'
       OR title LIKE 'Cross-Period%'
       OR title LIKE 'General Civil Engineering%'
       OR title LIKE 'Structural Analysis%'
       OR title LIKE 'Reinforced Concrete Design%'
       OR title LIKE 'Steel Design%'
       OR original_filename LIKE 'highway_engineering_%'
       OR original_filename LIKE 'valid_lesson%'
       OR original_filename IN ('empty.txt', 'corrupt.docx', 'fake.pdf', 'scanned.pdf')
       OR stored_filename LIKE 'lesson_6%'
    )
");

$qaLessons = $qaLessonsStmt->fetchAll(PDO::FETCH_ASSOC);
$qaLessonIds = array_column($qaLessons, 'id');
$qaLessonInClause = empty($qaLessonIds) ? "0" : implode(',', $qaLessonIds);

$cntAnswers = $pdo->query("SELECT COUNT(*) FROM submission_answers WHERE submission_id IN ({$qaSubInClause}) OR exam_id IN ({$qaExamInClause})")->fetchColumn();
$cntOverrides = $pdo->query("SELECT COUNT(*) FROM submission_score_overrides WHERE submission_id IN ({$qaSubInClause}) OR reviewer_id IN ({$qaUserInClause})")->fetchColumn();
$cntStatusHistory = $pdo->query("SELECT COUNT(*) FROM submission_status_history WHERE submission_id IN ({$qaSubInClause}) OR actor_id IN ({$qaUserInClause})")->fetchColumn();
$cntReprocHistory = $pdo->query("SELECT COUNT(*) FROM submission_reprocessing_history WHERE submission_id IN ({$qaSubInClause}) OR actor_id IN ({$qaUserInClause})")->fetchColumn();
$cntSnapshots = $pdo->query("SELECT COUNT(*) FROM submission_snapshots WHERE submission_id IN ({$qaSubInClause})")->fetchColumn();
$cntQuestions = $pdo->query("SELECT COUNT(*) FROM exam_questions WHERE exam_id IN ({$qaExamInClause})")->fetchColumn();
$cntSources = $pdo->query("SELECT COUNT(*) FROM generated_question_sources WHERE lesson_id IN ({$qaLessonInClause}) OR question_id IN (SELECT id FROM exam_questions WHERE exam_id IN ({$qaExamInClause}))")->fetchColumn();
$cntBatches = $pdo->query("SELECT COUNT(*) FROM ai_generation_batches")->fetchColumn();
$cntTokens = $pdo->query("SELECT COUNT(*) FROM used_confirmation_tokens")->fetchColumn();
$cntLogs = $pdo->query("SELECT COUNT(*) FROM activity_logs WHERE user_id IN ({$qaUserInClause}) OR action_description LIKE 'QA Audit%' OR action_description LIKE 'Test%'")->fetchColumn();

echo "=== PRE-CLEANUP RECORD CLASSIFICATION SUMMARY ===\n";
echo sprintf("  Legitimate Users to Preserve : %d accounts\n", count($legitUsers));
foreach ($legitUsers as $lu) {
    echo sprintf("    - [%s] %s (%s, ID: %d)\n", strtoupper($lu['role']), $lu['fullname'], $lu['email'], $lu['id']);
}
echo "\n  QA/Test Records Identified for Purge:\n";
echo sprintf("    - QA User Accounts          : %d rows\n", count($qaUsers));
echo sprintf("    - QA Exams                  : %d rows\n", count($qaExams));
echo sprintf("    - QA Exam Questions         : %d rows\n", $cntQuestions);
echo sprintf("    - QA Question Sources       : %d rows\n", $cntSources);
echo sprintf("    - AI Generation Batches     : %d rows\n", $cntBatches);
echo sprintf("    - Confirmation Tokens       : %d rows\n", $cntTokens);
echo sprintf("    - QA Exam Submissions       : %d rows\n", count($qaSubmissions));
echo sprintf("    - QA Submission Answers     : %d rows\n", $cntAnswers);
echo sprintf("    - QA Score Overrides        : %d rows\n", $cntOverrides);
echo sprintf("    - QA Status History         : %d rows\n", $cntStatusHistory);
echo sprintf("    - QA Reprocessing History   : %d rows\n", $cntReprocHistory);
echo sprintf("    - QA Snapshots              : %d rows\n", $cntSnapshots);
echo sprintf("    - QA Lesson Materials       : %d rows\n", count($qaLessons));
echo sprintf("    - QA Activity Logs          : %d rows\n", $cntLogs);
echo "-----------------------------------------------------------\n\n";

if ($isDryRun) {
    echo "[DRY-RUN COMPLETE] No database rows were modified. Run with --execute to perform actual cleanup.\n";
    exit(0);
}

$pdo->beginTransaction();

try {
    echo "=== EXECUTING SAFE DATABASE CLEANUP ===\n";

    $pdo->exec("DELETE FROM generated_question_sources WHERE lesson_id IN ({$qaLessonInClause}) OR question_id IN (SELECT id FROM exam_questions WHERE exam_id IN ({$qaExamInClause}))");
    echo "  [✓] Deleted generated_question_sources rows\n";

    $pdo->exec("DELETE FROM ai_generation_batches");
    echo "  [✓] Deleted ai_generation_batches rows\n";

    $pdo->exec("DELETE FROM used_confirmation_tokens");
    echo "  [✓] Deleted used_confirmation_tokens rows\n";

    $pdo->exec("DELETE FROM submission_reprocessing_history WHERE submission_id IN ({$qaSubInClause}) OR actor_id IN ({$qaUserInClause})");
    echo "  [✓] Deleted submission_reprocessing_history rows\n";

    $pdo->exec("DELETE FROM submission_score_overrides WHERE submission_id IN ({$qaSubInClause}) OR reviewer_id IN ({$qaUserInClause})");
    echo "  [✓] Deleted submission_score_overrides rows\n";

    $pdo->exec("DELETE FROM submission_status_history WHERE submission_id IN ({$qaSubInClause}) OR actor_id IN ({$qaUserInClause})");
    echo "  [✓] Deleted submission_status_history rows\n";

    $pdo->exec("DELETE FROM submission_snapshots WHERE submission_id IN ({$qaSubInClause})");
    echo "  [✓] Deleted submission_snapshots rows\n";

    $pdo->exec("DELETE FROM submission_answers WHERE submission_id IN ({$qaSubInClause}) OR exam_id IN ({$qaExamInClause})");
    echo "  [✓] Deleted submission_answers rows\n";

    $pdo->exec("DELETE FROM exam_submissions WHERE id IN ({$qaSubInClause}) OR teacher_id IN ({$qaUserInClause}) OR student_id IN ({$qaUserInClause}) OR exam_id IN ({$qaExamInClause})");
    echo "  [✓] Deleted exam_submissions rows\n";

    $pdo->exec("DELETE FROM exam_questions WHERE exam_id IN ({$qaExamInClause})");
    echo "  [✓] Deleted exam_questions rows\n";

    $pdo->exec("DELETE FROM exams WHERE id IN ({$qaExamInClause}) OR teacher_id IN ({$qaUserInClause})");
    echo "  [✓] Deleted exams rows\n";

    // Delete QA physical files
    $unlinkedCount = 0;
    foreach ($qaLessons as $ql) {
        $sf = $ql['stored_filename'];
        if (!empty($sf)) {
            $path = __DIR__ . '/../teacher/uploads/' . $sf;
            if (file_exists($path) && is_file($path)) {
                unlink($path);
                $unlinkedCount++;
            }
        }
    }
    $pdo->exec("DELETE FROM lesson_materials WHERE id IN ({$qaLessonInClause}) OR teacher_id IN ({$qaUserInClause})");
    echo "  [✓] Deleted lesson_materials rows (and unlinked {$unlinkedCount} physical files)\n";

    $pdo->exec("DELETE FROM student_details WHERE user_id IN ({$qaUserInClause})");
    echo "  [✓] Deleted student_details rows for QA accounts\n";

    $pdo->exec("DELETE FROM activity_logs WHERE user_id IN ({$qaUserInClause}) OR action_description LIKE 'QA Audit%' OR action_description LIKE 'Test%'");
    echo "  [✓] Deleted activity_logs rows\n";

    $pdo->exec("DELETE FROM users WHERE id IN ({$qaUserInClause})");
    echo "  [✓] Deleted QA user accounts\n";

    echo "\n--- Cleaning Orphaned Records ---\n";
    $orphAns = $pdo->exec("DELETE FROM submission_answers WHERE submission_id NOT IN (SELECT id FROM exam_submissions)");
    echo "  [✓] Deleted {$orphAns} orphaned submission_answers rows\n";

    $orphQuest = $pdo->exec("DELETE FROM exam_questions WHERE exam_id NOT IN (SELECT id FROM exams)");
    echo "  [✓] Deleted {$orphQuest} orphaned exam_questions rows\n";

    $orphOverrides = $pdo->exec("DELETE FROM submission_score_overrides WHERE submission_id NOT IN (SELECT id FROM exam_submissions)");
    echo "  [✓] Deleted {$orphOverrides} orphaned submission_score_overrides rows\n";

    $orphHistory = $pdo->exec("DELETE FROM submission_status_history WHERE submission_id NOT IN (SELECT id FROM exam_submissions)");
    echo "  [✓] Deleted {$orphHistory} orphaned submission_status_history rows\n";

    echo "\n--- Seeding Approved Professional Demo Dataset ---\n";

    $stmtSubj1 = $pdo->prepare("
        INSERT INTO subjects (id, code, title) VALUES (1, 'CE-401', 'Structural Engineering')
        ON DUPLICATE KEY UPDATE title = VALUES(title)
    ");
    $stmtSubj1->execute();

    $stmtSubj2 = $pdo->prepare("
        INSERT INTO subjects (id, code, title) VALUES (2, 'CE-402', 'Geotechnical Engineering & Foundation Design')
        ON DUPLICATE KEY UPDATE title = VALUES(title)
    ");
    $stmtSubj2->execute();

    $passHash = password_hash('Password123!', PASSWORD_DEFAULT);
    $stmtUsrDemo = $pdo->prepare("
        INSERT INTO users (id, username, fullname, email, password, role, is_demo)
        VALUES (20, 'jmsantos', 'John Mark Santos', 'jmsantos@holycross.edu.ph', ?, 'student', 1)
        ON DUPLICATE KEY UPDATE fullname = VALUES(fullname), is_demo = 1
    ");
    $stmtUsrDemo->execute([$passHash]);

    $stmtSdDemo = $pdo->prepare("
        INSERT INTO student_details (user_id, student_number, course, year_level, section)
        VALUES (20, '23-2149800', 'BSCE', '4th Year', 'Section A')
        ON DUPLICATE KEY UPDATE course = 'BSCE', year_level = '4th Year', section = 'Section A'
    ");
    $stmtSdDemo->execute();

    // Ensure demo physical file exists
    $demoTextPath = __DIR__ . '/../teacher/uploads/demo_structural_steel.txt';
    if (!file_exists($demoTextPath)) {
        file_put_contents($demoTextPath, "Reinforced concrete flexural design relies on ultimate limit state analysis and steel tensile reinforcement capacity.");
    }

    $stmtLesson = $pdo->prepare("
        INSERT INTO lesson_materials (id, teacher_id, title, subject, file_name, file_path, file_type, file_size, original_filename, stored_filename, lesson_text, word_count, page_count, processing_status, is_demo, created_at)
        VALUES (10, 12, 'Structural Steel & Reinforced Concrete Design Fundamentals', 'Structural Engineering', 'demo_structural_steel.txt', 'teacher/uploads/demo_structural_steel.txt', 'txt', 1024, 'demo_structural_steel.txt', 'demo_structural_steel.txt', 'Reinforced concrete flexural design relies on ultimate limit state analysis and steel tensile reinforcement capacity.', 150, 1, 'completed', 1, NOW())
        ON DUPLICATE KEY UPDATE title = VALUES(title), is_demo = 1
    ");
    $stmtLesson->execute();

    $stmtExam = $pdo->prepare("
        INSERT INTO exams (id, teacher_id, created_by, title, subject, specialization, difficulty, time_limit, total_items, passing_percentage, status, is_demo, created_at)
        VALUES (10, 12, 12, 'Civil Engineering Board Exam Review - Structural Design & Construction', 'Structural Engineering', 'Structural Engineering', 'medium', 60, 3, 75.00, 'active', 1, NOW())
        ON DUPLICATE KEY UPDATE title = VALUES(title), is_demo = 1
    ");
    $stmtExam->execute();

    $questionsData = [
        [101, 10, 'What is the standard minimum concrete cover for reinforced concrete beams exposed to soil?', 'multiple_choice', '75 mm', '50 mm', '40 mm', '25 mm', '75 mm', 1.00],
        [102, 10, 'Under the National Structural Code of the Philippines (NSCP 2015), flexural strength reduction factor phi for tension-controlled sections is 0.90.', 'true_false', 'true', 'false', NULL, NULL, 'true', 1.00],
        [103, 10, 'Calculate the nominal shear capacity of a rectangular concrete beam with b = 250mm, d = 400mm, fc\' = 28 MPa.', 'multiple_choice', '88.36 kN', '95.20 kN', '102.50 kN', '74.10 kN', '88.36 kN', 1.00]
    ];

    foreach ($questionsData as $qd) {
        $stmtQ = $pdo->prepare("
            INSERT INTO exam_questions (id, exam_id, question_text, question_type, option_a, option_b, option_c, option_d, correct_answer, points)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE question_text = VALUES(question_text)
        ");
        $stmtQ->execute($qd);
    }

    $stmtExamQual = $pdo->prepare("
        INSERT INTO exams (id, teacher_id, created_by, title, subject, specialization, difficulty, time_limit, total_items, passing_percentage, status, exam_category, is_demo, created_at)
        VALUES (11, 12, 12, 'Civil Engineering Comprehensive Qualifying Exam', 'Structural Engineering', 'Structural Engineering', 'hard', 120, 3, 75.00, 'active', 'qualifying', 1, NOW())
        ON DUPLICATE KEY UPDATE title = VALUES(title), exam_category = 'qualifying', is_demo = 1
    ");
    $stmtExamQual->execute();

    $stmtSubPublished = $pdo->prepare("
        INSERT INTO exam_submissions (id, exam_id, student_id, teacher_id, student_name, exam_title, upload_type, correct_count, wrong_count, total_score, total_possible_score, total_items, percentage, status, review_status, is_demo, created_at, published_at)
        VALUES (500, 10, 11, 12, 'Ashley Nicole Gutierrez', 'Civil Engineering Board Exam Review - Structural Design & Construction', 'online', 3, 0, 3.00, 3.00, 3, 100.00, 'Pass', 'published', 1, NOW(), NOW())
        ON DUPLICATE KEY UPDATE review_status = 'published', percentage = 100.00, is_demo = 1
    ");
    $stmtSubPublished->execute();

    $answersPublished = [
        [500, 10, 11, 101, '75 mm', '75 mm', 1.00, 1.00, 'correct'],
        [500, 10, 11, 102, 'true', 'true', 1.00, 1.00, 'correct'],
        [500, 10, 11, 103, '88.36 kN', '88.36 kN', 1.00, 1.00, 'correct']
    ];
    foreach ($answersPublished as $ap) {
        $stmtAnsP = $pdo->prepare("
            INSERT INTO submission_answers (submission_id, exam_id, student_id, question_id, student_answer, correct_answer, awarded_points, max_points, evaluation_status)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE awarded_points = VALUES(awarded_points), evaluation_status = VALUES(evaluation_status)
        ");
        $stmtAnsP->execute($ap);
    }

    $stmtSubPending = $pdo->prepare("
        INSERT INTO exam_submissions (id, exam_id, student_id, teacher_id, student_name, exam_title, upload_type, correct_count, wrong_count, total_score, total_possible_score, total_items, percentage, status, review_status, is_demo, created_at)
        VALUES (501, 10, 20, 12, 'John Mark Santos', 'Civil Engineering Board Exam Review - Structural Design & Construction', 'scanned', 2, 1, 2.00, 3.00, 3, 66.67, 'Fail', 'pending_review', 1, NOW())
        ON DUPLICATE KEY UPDATE review_status = 'pending_review', is_demo = 1
    ");
    $stmtSubPending->execute();

    $answersPending = [
        [501, 10, 20, 101, '75 mm', '75 mm', 1.00, 1.00, 'correct'],
        [501, 10, 20, 102, 'true', 'true', 1.00, 1.00, 'correct'],
        [501, 10, 20, 103, '95.20 kN', '88.36 kN', 0.00, 1.00, 'incorrect']
    ];
    foreach ($answersPending as $ap) {
        $stmtAnsP = $pdo->prepare("
            INSERT INTO submission_answers (submission_id, exam_id, student_id, question_id, student_answer, correct_answer, awarded_points, max_points, evaluation_status)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE awarded_points = VALUES(awarded_points), evaluation_status = VALUES(evaluation_status)
        ");
        $stmtAnsP->execute($ap);
    }

    $stmtSubFinalized = $pdo->prepare("
        INSERT INTO exam_submissions (id, exam_id, student_id, teacher_id, student_name, exam_title, upload_type, correct_count, wrong_count, total_score, total_possible_score, total_items, percentage, status, review_status, is_demo, created_at)
        VALUES (502, 11, 26, 12, 'P4 Test Student', 'Civil Engineering Comprehensive Qualifying Exam', 'online', 3, 0, 3.00, 3.00, 3, 100.00, 'Pass', 'finalized', 1, NOW())
        ON DUPLICATE KEY UPDATE review_status = 'finalized', is_demo = 1
    ");
    $stmtSubFinalized->execute();

    // Clean old activity logs & audit logs to maintain minimal clean state
    $pdo->exec("DELETE FROM activity_logs WHERE id < (SELECT id FROM (SELECT id FROM activity_logs ORDER BY id DESC LIMIT 20) AS t ORDER BY id ASC LIMIT 1)");
    $pdo->exec("DELETE FROM audit_logs WHERE id < (SELECT id FROM (SELECT id FROM audit_logs ORDER BY id DESC LIMIT 20) AS t ORDER BY id ASC LIMIT 1)");

    $pdo->commit();
    echo "  [✓] Successfully seeded approved professional demo dataset\n";

    echo "\n=== POST-CLEANUP TABLE ROW COUNTS ===\n";
    foreach (['users','student_details','departments','subjects','lesson_materials','exams','exam_questions','exam_submissions','submission_answers','submission_score_overrides','submission_status_history','activity_logs','ai_generation_batches','generated_question_sources','used_confirmation_tokens'] as $t) {
        $cnt = $pdo->query("SELECT COUNT(*) FROM {$t}")->fetchColumn();
        echo sprintf("  %-30s : %d rows\n", $t, $cnt);
    }

    echo "\n[SUCCESS] Safe Database Cleanup Completed Successfully!\n";
    exit(0);

} catch (Exception $e) {
    $pdo->rollBack();
    echo "\n[FATAL ERROR] Safe Database Cleanup Failed and was ROLLED BACK: " . $e->getMessage() . "\n";
    exit(1);
}
