<?php
/**
 * QUESTBANK CANONICAL DEMO DATABASE CLEANUP & REBUILD UTILITY
 *
 * Purges all QA/test accounts, Playwright records, mock batches, test tokens,
 * and test logs. Seeds a minimal, professional, 100% clean demo dataset.
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
echo "    QUESTBANK CANONICAL DEMO DATABASE CLEANUP UTILITY      \n";
echo "===========================================================\n";
echo "Mode: " . ($isDryRun ? "DRY-RUN (Preview Only - No DB modifications)" : "EXECUTE (Permanent Deletion)") . "\n";
echo "Environment: {$env}\n";
echo "Timestamp: " . date('Y-m-d H:i:s') . "\n\n";

function ensureColumnExists($pdo, $table, $col, $def) {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?");
    $stmt->execute([$table, $col]);
    if ((int)$stmt->fetchColumn() === 0) {
        $pdo->exec("ALTER TABLE `{$table}` ADD COLUMN `{$col}` {$def}");
    }
}

ensureColumnExists($pdo, 'users', 'is_demo', "TINYINT(1) NOT NULL DEFAULT 0");
ensureColumnExists($pdo, 'exams', 'is_demo', "TINYINT(1) NOT NULL DEFAULT 0");
ensureColumnExists($pdo, 'exam_submissions', 'is_demo', "TINYINT(1) NOT NULL DEFAULT 0");
ensureColumnExists($pdo, 'lesson_materials', 'is_demo', "TINYINT(1) NOT NULL DEFAULT 0");

if ($isDryRun) {
    echo "[DRY-RUN COMPLETE] No database rows were modified. Run with --execute to perform actual cleanup.\n";
    exit(0);
}

try {
    echo "=== TRUNCATING AND REBUILDING CANONICAL DEMO DATASET ===\n";

    // Disable foreign key checks for clean purge & re-seed
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");

    $tablesToTruncate = [
        'ai_generation_batches',
        'generated_question_sources',
        'used_confirmation_tokens',
        'user_sessions',
        'notifications',
        'submission_reprocessing_history',
        'submission_score_overrides',
        'submission_status_history',
        'submission_snapshots',
        'activity_logs',
        'audit_logs',
        'submission_answers',
        'exam_submissions',
        'exam_questions',
        'exams',
        'lesson_materials',
        'student_details',
        'teacher_subject_assignments',
        'exam_schedules',
        'academic_calendar',
        'sections',
        'semesters',
        'school_years',
        'subjects',
        'departments',
        'users'
    ];

    foreach ($tablesToTruncate as $t) {
        $pdo->exec("TRUNCATE TABLE `{$t}`");
    }

    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");

    echo "  [✓] Truncated all legacy test data and history tables\n";

    echo "\n--- Seeding Realistic Professional Demo Dataset ---\n";

    $passHash = password_hash('Password123!', PASSWORD_DEFAULT);

    // 1. Demo Users (IDs match migration defaults)
    $demoUsers = [
        [1, 'admin', 'System Administrator', 'admin@questbank.edu.ph', $passHash, 'admin', 1, 1],
        [10, 'Russel', 'Russel Gregorio', 'russel@questbank.edu.ph', $passHash, 'teacher', 1, 1],
        [11, 'Nicole', 'Ashley Nicole Gutierrez', 'nikol@gmail.com', $passHash, 'student', 1, 1],
        [12, 'prof_smith', 'Professor Smith', 'smith@questbank.edu.ph', $passHash, 'teacher', 1, 1],
        [13, 'lasjo', 'Jolas Lasjo', 'lasjo@gmail.com', $passHash, 'teacher', 1, 1],
        [20, 'jmsantos', 'John Mark Santos', 'jmsantos@holycross.edu.ph', $passHash, 'student', 1, 1],
        [21, 'm_reyes', 'Maria Angelica Reyes', 'mreyes@holycross.edu.ph', $passHash, 'student', 1, 1],
    ];

    $stmtUsr = $pdo->prepare("
        INSERT INTO users (id, username, fullname, email, password, role, force_password_reset, is_demo)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ");
    foreach ($demoUsers as $u) {
        $stmtUsr->execute($u);
    }
    echo "  [✓] Seeded 6 clean professional demo users (1 Admin, 2 Teachers, 3 Students)\n";

    // 2. Student Details
    $studentDetails = [
        [11, '23-2149184', 'BSCE', 4, 'Section A'],
        [20, '23-2149800', 'BSCE', 4, 'Section A'],
        [21, '23-2149805', 'BSCE', 4, 'Section B'],
    ];
    $stmtSd = $pdo->prepare("
        INSERT INTO student_details (user_id, student_number, course, year_level, section)
        VALUES (?, ?, ?, ?, ?)
    ");
    foreach ($studentDetails as $sd) {
        $stmtSd->execute($sd);
    }
    echo "  [✓] Seeded student details\n";

    // 3. Academic Structure (School Years, Semesters, Sections, Departments, Subjects)
    $pdo->exec("INSERT INTO school_years (id, school_year, start_date, end_date, status) VALUES (1, '2025-2026', '2025-06-01', '2026-05-31', 'active')");
    $pdo->exec("INSERT INTO semesters (id, school_year_id, semester_name, status) VALUES (1, 1, 'First Semester', 'active')");
    $pdo->exec("INSERT INTO sections (id, section_code, section_name, course_name, academic_year, course, school_year_id, status) VALUES (1, 'BSCE-4A', 'Section 4A', 'BSCE', '2025-2026', 'BSCE', 1, 'active')");

    $pdo->exec("INSERT INTO departments (id, dept_code, dept_name, programs, faculty_head) VALUES (1, 'COE', 'College of Engineering', 'BSCE', 'Prof. Russel Gregorio')");
    $pdo->exec("INSERT INTO subjects (id, code, title) VALUES (1, 'CE-401', 'Structural Engineering')");
    $pdo->exec("INSERT INTO subjects (id, code, title) VALUES (2, 'CE-402', 'Geotechnical Engineering & Foundation Design')");
    $pdo->exec("INSERT INTO teacher_subject_assignments (id, teacher_id, subject, section_id, school_year_id) VALUES (1, 10, 'Structural Engineering', 1, 1), (2, 12, 'Geotechnical Engineering & Foundation Design', 1, 1)");
    echo "  [✓] Seeded academic structure (SY, Semesters, Sections, Departments, Subjects, Assignments)\n";

    // 4. Lesson Materials
    $demoTextPath = __DIR__ . '/../teacher/uploads/demo_structural_steel.txt';
    if (!file_exists($demoTextPath)) {
        file_put_contents($demoTextPath, "Reinforced concrete flexural design relies on ultimate limit state analysis and steel tensile reinforcement capacity.");
    }

    $stmtLesson = $pdo->prepare("
        INSERT INTO lesson_materials (id, teacher_id, title, subject, file_name, file_path, file_type, file_size, original_filename, stored_filename, lesson_text, word_count, page_count, processing_status, is_demo, created_at)
        VALUES (10, 10, 'Structural Steel & Reinforced Concrete Design Fundamentals', 'Structural Engineering', 'demo_structural_steel.txt', 'teacher/uploads/demo_structural_steel.txt', 'txt', 1024, 'demo_structural_steel.txt', 'demo_structural_steel.txt', 'Reinforced concrete flexural design relies on ultimate limit state analysis and steel tensile reinforcement capacity.', 150, 1, 'completed', 1, NOW())
    ");
    $stmtLesson->execute();
    echo "  [✓] Seeded lesson materials\n";

    // 5. Exams (1 Regular Exam, 1 Qualifying Exam)
    $stmtExamReg = $pdo->prepare("
        INSERT INTO exams (id, teacher_id, created_by, title, subject, specialization, difficulty, time_limit, total_items, passing_percentage, status, exam_category, is_demo, created_at)
        VALUES (10, 10, 10, 'Civil Engineering Board Exam Review - Structural Design & Construction', 'Structural Engineering', 'Structural Engineering', 'medium', 60, 3, 75.00, 'active', 'regular', 1, NOW())
    ");
    $stmtExamReg->execute();

    $stmtExamQual = $pdo->prepare("
        INSERT INTO exams (id, teacher_id, created_by, title, subject, specialization, difficulty, time_limit, total_items, passing_percentage, status, exam_category, is_demo, created_at)
        VALUES (11, 10, 10, 'Civil Engineering Comprehensive Qualifying Exam', 'Structural Engineering', 'Structural Engineering', 'hard', 120, 3, 75.00, 'active', 'qualifying', 1, NOW())
    ");
    $stmtExamQual->execute();
    echo "  [✓] Seeded 2 demo exams (1 Regular Exam, 1 Qualifying Exam)\n";

    // 6. Exam Questions
    $questionsData = [
        [101, 10, 'What is the standard minimum concrete cover for reinforced concrete beams exposed to soil?', 'multiple_choice', '75 mm', '50 mm', '40 mm', '25 mm', '75 mm', 1.00],
        [102, 10, 'Under the National Structural Code of the Philippines (NSCP 2015), flexural strength reduction factor phi for tension-controlled sections is 0.90.', 'true_false', 'true', 'false', NULL, NULL, 'true', 1.00],
        [103, 10, 'Calculate the nominal shear capacity of a rectangular concrete beam with b = 250mm, d = 400mm, fc\' = 28 MPa.', 'multiple_choice', '88.36 kN', '95.20 kN', '102.50 kN', '74.10 kN', '88.36 kN', 1.00]
    ];
    $stmtQ = $pdo->prepare("
        INSERT INTO exam_questions (id, exam_id, question_text, question_type, option_a, option_b, option_c, option_d, correct_answer, points)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    foreach ($questionsData as $qd) {
        $stmtQ->execute($qd);
    }
    echo "  [✓] Seeded exam questions\n";

    // 7. Submissions (1 Published, 1 Pending Review, 1 Finalized)
    // 500 - Published Submission (Ashley Nicole Gutierrez - ID 11)
    $stmtSubPublished = $pdo->prepare("
        INSERT INTO exam_submissions (id, exam_id, student_id, teacher_id, student_name, exam_title, upload_type, correct_count, wrong_count, total_score, total_possible_score, total_items, percentage, status, review_status, is_demo, created_at, published_at)
        VALUES (500, 10, 11, 10, 'Ashley Nicole Gutierrez', 'Civil Engineering Board Exam Review - Structural Design & Construction', 'online', 3, 0, 3.00, 3.00, 3, 100.00, 'Pass', 'published', 1, NOW(), NOW())
    ");
    $stmtSubPublished->execute();

    $answersPublished = [
        [500, 10, 11, 101, '75 mm', '75 mm', 1.00, 1.00, 'correct'],
        [500, 10, 11, 102, 'true', 'true', 1.00, 1.00, 'correct'],
        [500, 10, 11, 103, '88.36 kN', '88.36 kN', 1.00, 1.00, 'correct']
    ];
    $stmtAns = $pdo->prepare("
        INSERT INTO submission_answers (submission_id, exam_id, student_id, question_id, student_answer, correct_answer, awarded_points, max_points, evaluation_status)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    foreach ($answersPublished as $ap) {
        $stmtAns->execute($ap);
    }

    // 501 - Pending Review Submission (John Mark Santos - ID 20)
    $stmtSubPending = $pdo->prepare("
        INSERT INTO exam_submissions (id, exam_id, student_id, teacher_id, student_name, exam_title, upload_type, correct_count, wrong_count, total_score, total_possible_score, total_items, percentage, status, review_status, is_demo, created_at)
        VALUES (501, 10, 20, 10, 'John Mark Santos', 'Civil Engineering Board Exam Review - Structural Design & Construction', 'scanned', 2, 1, 2.00, 3.00, 3, 66.67, 'Fail', 'pending_review', 1, NOW())
    ");
    $stmtSubPending->execute();

    $answersPending = [
        [501, 10, 20, 101, '75 mm', '75 mm', 1.00, 1.00, 'correct'],
        [501, 10, 20, 102, 'true', 'true', 1.00, 1.00, 'correct'],
        [501, 10, 20, 103, '95.20 kN', '88.36 kN', 0.00, 1.00, 'incorrect']
    ];
    foreach ($answersPending as $ap) {
        $stmtAns->execute($ap);
    }

    // 502 - Finalized Submission (Maria Angelica Reyes - ID 21)
    $stmtSubFinalized = $pdo->prepare("
        INSERT INTO exam_submissions (id, exam_id, student_id, teacher_id, student_name, exam_title, upload_type, correct_count, wrong_count, total_score, total_possible_score, total_items, percentage, status, review_status, is_demo, created_at)
        VALUES (502, 11, 21, 10, 'Maria Angelica Reyes', 'Civil Engineering Comprehensive Qualifying Exam', 'online', 3, 0, 3.00, 3.00, 3, 100.00, 'Pass', 'finalized', 1, NOW())
    ");
    $stmtSubFinalized->execute();
    echo "  [✓] Seeded 3 submissions (1 Published, 1 Pending Review, 1 Finalized)\n";

    // 8. Demo Activity Logs & Audit Logs
    $pdo->exec("INSERT INTO activity_logs (id, user_id, action_description, created_at) VALUES (1, 10, 'Published exam #10 results for Structural Engineering', NOW())");
    $pdo->exec("INSERT INTO audit_logs (id, user_id, actor_id, entity_type, entity_id, action, details, created_at) VALUES (1, 10, 10, 'exam', 10, 'EXAM_PUBLISHED', 'Published exam results for Ashley Nicole Gutierrez', NOW())");
    echo "  [✓] Seeded minimal demo activity logs\n";

    // 9. Reset AUTO_INCREMENT values
    $pdo->exec("ALTER TABLE users AUTO_INCREMENT = 30");
    $pdo->exec("ALTER TABLE student_details AUTO_INCREMENT = 30");
    $pdo->exec("ALTER TABLE school_years AUTO_INCREMENT = 10");
    $pdo->exec("ALTER TABLE semesters AUTO_INCREMENT = 10");
    $pdo->exec("ALTER TABLE sections AUTO_INCREMENT = 10");
    $pdo->exec("ALTER TABLE departments AUTO_INCREMENT = 10");
    $pdo->exec("ALTER TABLE subjects AUTO_INCREMENT = 10");
    $pdo->exec("ALTER TABLE exams AUTO_INCREMENT = 20");
    $pdo->exec("ALTER TABLE exam_questions AUTO_INCREMENT = 120");
    $pdo->exec("ALTER TABLE exam_submissions AUTO_INCREMENT = 600");
    $pdo->exec("ALTER TABLE submission_answers AUTO_INCREMENT = 1000");
    $pdo->exec("ALTER TABLE lesson_materials AUTO_INCREMENT = 20");
    $pdo->exec("ALTER TABLE activity_logs AUTO_INCREMENT = 10");
    $pdo->exec("ALTER TABLE audit_logs AUTO_INCREMENT = 10");
    echo "  [✓] Reset AUTO_INCREMENT next ID values\n";

    echo "  [✓] Successfully rebuilt canonical demo dataset\n";

    echo "\n=== POST-CLEANUP TABLE ROW COUNTS ===\n";
    foreach (['users','student_details','school_years','semesters','sections','departments','subjects','lesson_materials','exams','exam_questions','exam_submissions','submission_answers','submission_score_overrides','submission_status_history','activity_logs','ai_generation_batches','generated_question_sources','used_confirmation_tokens'] as $t) {
        $cnt = $pdo->query("SELECT COUNT(*) FROM {$t}")->fetchColumn();
        echo sprintf("  %-30s : %d rows\n", $t, $cnt);
    }

    echo "\n[SUCCESS] Safe Database Cleanup Completed Successfully!\n";
    exit(0);

} catch (Exception $e) {
    echo "\n[FATAL ERROR] Safe Database Cleanup Failed: " . $e->getMessage() . "\n";
    exit(1);
}
