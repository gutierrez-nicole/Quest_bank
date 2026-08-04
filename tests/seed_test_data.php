<?php
/**
 * QuestBank Deterministic Test Data Seeder
 * Creates deterministic test fixtures and outputs tests/fixtures/test_state.json
 */

require_once __DIR__ . '/../app/bootstrap.php';

$pdo = getDBConnection();
$defaultPassHash = password_hash('Password123!', PASSWORD_DEFAULT);

echo "=== Seeding Deterministic Test Fixtures ===\n";

// 1. Seed Users
$qaUsers = [
    [1, 'qa_admin', 'QA Test Administrator', 'qa_admin@questbank.test', 'admin'],
    [2, 'qa_teacher_a', 'QA Test Professor Alpha', 'qa_teacher_a@questbank.test', 'teacher'],
    [3, 'qa_teacher_b', 'QA Test Professor Beta', 'qa_teacher_b@questbank.test', 'teacher'],
    [4, 'qa_student_a', 'QA Test Student Alpha', 'qa_student_a@questbank.test', 'student'],
    [5, 'qa_student_b', 'QA Test Student Beta', 'qa_student_b@questbank.test', 'student']
];

foreach ($qaUsers as $u) {
    $stmt = $pdo->prepare("
        INSERT INTO users (id, username, fullname, email, password, role, status) 
        VALUES (?, ?, ?, ?, ?, ?, 'active') 
        ON DUPLICATE KEY UPDATE password = ?, fullname = VALUES(fullname), email = VALUES(email), role = VALUES(role), status = 'active'
    ");
    $stmt->execute([$u[0], $u[1], $u[2], $u[3], $defaultPassHash, $u[4], $defaultPassHash]);
}

// 2. Student Details
$stmtSdA = $pdo->prepare("
    INSERT INTO student_details (user_id, student_number, course, year_level, section) 
    VALUES (4, '23-QA-1001', 'BSCE', '4th Year', 'Section A') 
    ON DUPLICATE KEY UPDATE course = 'BSCE', year_level = '4th Year', section = 'Section A'
");
$stmtSdA->execute();

$stmtSdB = $pdo->prepare("
    INSERT INTO student_details (user_id, student_number, course, year_level, section) 
    VALUES (5, '23-QA-1002', 'BSCE', '4th Year', 'Section B') 
    ON DUPLICATE KEY UPDATE course = 'BSCE', year_level = '4th Year', section = 'Section B'
");
$stmtSdB->execute();

// 3. Subject & Exam
$stmtSubj = $pdo->prepare("
    INSERT INTO subjects (id, code, title) VALUES (1, 'CE-401', 'Structural Engineering')
    ON DUPLICATE KEY UPDATE title = VALUES(title)
");
$stmtSubj->execute();

$stmtExam = $pdo->prepare("
    INSERT INTO exams (id, teacher_id, created_by, title, subject, specialization, difficulty, time_limit, total_items, passing_percentage, status, created_at)
    VALUES (1, 2, 2, 'QA Civil Engineering Fundamentals Exam', 'Structural Engineering', 'Structural Engineering', 'medium', 45, 2, 75.00, 'active', NOW())
    ON DUPLICATE KEY UPDATE title = VALUES(title), total_items = VALUES(total_items), passing_percentage = VALUES(passing_percentage)
");
$stmtExam->execute();

// 4. Questions for Exam #1
$stmtQ1 = $pdo->prepare("
    INSERT INTO exam_questions (id, exam_id, question_text, question_type, option_a, option_b, option_c, option_d, correct_answer, points)
    VALUES (1, 1, 'What is the formula for Stopping Sight Distance (SSD)?', 'multiple_choice', 'a', 'b', 'c', 'd', 'a', 1.00)
    ON DUPLICATE KEY UPDATE question_text = VALUES(question_text), points = 1.00
");
$stmtQ1->execute();

$stmtQ2 = $pdo->prepare("
    INSERT INTO exam_questions (id, exam_id, question_text, question_type, option_a, option_b, option_c, option_d, correct_answer, points)
    VALUES (2, 1, 'Flexible pavement design uses CBR structural number.', 'true_false', 'true', 'false', NULL, NULL, 'true', 1.00)
    ON DUPLICATE KEY UPDATE question_text = VALUES(question_text), points = 1.00
");
$stmtQ2->execute();

// 5. Seed Core Submissions for Workflows
// Submission #100: Student A - Published (100% Pass)
$stmtSub100 = $pdo->prepare("
    INSERT INTO exam_submissions (id, exam_id, student_id, teacher_id, student_name, exam_title, upload_type, correct_count, wrong_count, total_score, total_possible_score, total_items, percentage, status, review_status, created_at, published_at)
    VALUES (100, 1, 4, 2, 'QA Test Student Alpha', 'QA Civil Engineering Fundamentals Exam', 'online', 2, 0, 2.00, 2.00, 2, 100.00, 'Pass', 'published', NOW(), NOW())
    ON DUPLICATE KEY UPDATE student_id = 4, student_name = 'QA Test Student Alpha', exam_title = 'QA Civil Engineering Fundamentals Exam', exam_id = 1, teacher_id = 2, review_status = 'published', percentage = 100.00, correct_count = 2, total_score = 2.00, total_possible_score = 2.00, status = 'Pass', published_at = NOW()
");
$stmtSub100->execute();

// Submission #101: Student B - Published (50% Fail)
$stmtSub101 = $pdo->prepare("
    INSERT INTO exam_submissions (id, exam_id, student_id, teacher_id, student_name, exam_title, upload_type, correct_count, wrong_count, total_score, total_possible_score, total_items, percentage, status, review_status, created_at, published_at)
    VALUES (101, 1, 5, 2, 'QA Test Student Beta', 'QA Civil Engineering Fundamentals Exam', 'online', 1, 1, 1.00, 2.00, 2, 50.00, 'Fail', 'published', NOW(), NOW())
    ON DUPLICATE KEY UPDATE student_id = 5, student_name = 'QA Test Student Beta', exam_title = 'QA Civil Engineering Fundamentals Exam', exam_id = 1, teacher_id = 2, review_status = 'published', percentage = 50.00, correct_count = 1, total_score = 1.00, total_possible_score = 2.00, status = 'Fail', published_at = NOW()
");
$stmtSub101->execute();

// Submission #102: Student A - Pending Review (50% Fail)
$stmtSub102 = $pdo->prepare("
    INSERT INTO exam_submissions (id, exam_id, student_id, teacher_id, student_name, exam_title, upload_type, correct_count, wrong_count, total_score, total_possible_score, total_items, percentage, status, review_status, created_at)
    VALUES (102, 1, 4, 2, 'QA Test Student Alpha', 'QA Civil Engineering Fundamentals Exam', 'online', 1, 1, 1.00, 2.00, 2, 50.00, 'Fail', 'pending_review', NOW())
    ON DUPLICATE KEY UPDATE student_id = 4, student_name = 'QA Test Student Alpha', exam_title = 'QA Civil Engineering Fundamentals Exam', exam_id = 1, teacher_id = 2, review_status = 'pending_review', percentage = 50.00, correct_count = 1, wrong_count = 1, total_score = 1.00, total_possible_score = 2.00, status = 'Fail', published_at = NULL
");
$stmtSub102->execute();

// Answers for Submissions #100, #101, #102 — DELETE stale rows first to avoid contamination
$pdo->exec("DELETE FROM submission_answers WHERE submission_id IN (100, 101, 102)");
$pdo->exec("
    INSERT INTO submission_answers (submission_id, exam_id, student_id, question_id, student_answer, correct_answer, awarded_points, max_points, evaluation_status)
    VALUES 
    (100, 1, 4, 1, 'a', 'a', 1.00, 1.00, 'correct'),
    (100, 1, 4, 2, 'true', 'true', 1.00, 1.00, 'correct'),
    (101, 1, 5, 1, 'a', 'a', 1.00, 1.00, 'correct'),
    (101, 1, 5, 2, 'false', 'true', 0.00, 1.00, 'incorrect'),
    (102, 1, 4, 1, 'a', 'a', 1.00, 1.00, 'correct'),
    (102, 1, 4, 2, 'false', 'true', 0.00, 1.00, 'incorrect')
    ON DUPLICATE KEY UPDATE awarded_points = VALUES(awarded_points), evaluation_status = VALUES(evaluation_status)
");

// 6. Seed Deterministic Analytics Exam & Submissions (Scores: 90, 80, 70, 60; Threshold 75)
$stmtAnalyticsExam = $pdo->prepare("
    INSERT INTO exams (id, teacher_id, created_by, title, subject, specialization, difficulty, time_limit, total_items, passing_percentage, status, created_at)
    VALUES (2, 2, 2, 'QA Analytics Benchmark Exam', 'Analytics Verification', 'Analytics Verification', 'medium', 60, 10, 75.00, 'active', NOW())
    ON DUPLICATE KEY UPDATE title = VALUES(title), passing_percentage = 75.00
");
$stmtAnalyticsExam->execute();

$analyticsScores = [
    ['sub_id' => 201, 'student_id' => 4, 'name' => 'QA Student 90', 'score' => 9.00, 'pct' => 90.00, 'status' => 'Pass'],
    ['sub_id' => 202, 'student_id' => 5, 'name' => 'QA Student 80', 'score' => 8.00, 'pct' => 80.00, 'status' => 'Pass'],
    ['sub_id' => 203, 'student_id' => 4, 'name' => 'QA Student 70', 'score' => 7.00, 'pct' => 70.00, 'status' => 'Fail'],
    ['sub_id' => 204, 'student_id' => 5, 'name' => 'QA Student 60', 'score' => 6.00, 'pct' => 60.00, 'status' => 'Fail']
];

foreach ($analyticsScores as $an) {
    $stmtAn = $pdo->prepare("
        INSERT INTO exam_submissions (id, exam_id, student_id, teacher_id, student_name, exam_title, upload_type, correct_count, wrong_count, total_score, total_possible_score, total_items, percentage, status, review_status, created_at, published_at)
        VALUES (?, 2, ?, 2, ?, 'QA Analytics Benchmark Exam', 'online', ?, ?, ?, 10.00, 10, ?, ?, 'published', NOW(), NOW())
        ON DUPLICATE KEY UPDATE teacher_id = 2, exam_title = 'QA Analytics Benchmark Exam', review_status = 'published', percentage = VALUES(percentage), status = VALUES(status), total_score = VALUES(total_score)
    ");
    $stmtAn->execute([$an['sub_id'], $an['student_id'], $an['name'], intval($an['score']), 10 - intval($an['score']), $an['score'], $an['pct'], $an['status']]);
}

// 7. Write test_state.json fixture file
$fixturesDir = __DIR__ . '/fixtures';
if (!is_dir($fixturesDir)) {
    mkdir($fixturesDir, 0777, true);
}

$stateData = [
    'admin' => ['id' => 1, 'username' => 'qa_admin', 'email' => 'qa_admin@questbank.test', 'password' => 'Password123!'],
    'teacher_a' => ['id' => 2, 'username' => 'qa_teacher_a', 'email' => 'qa_teacher_a@questbank.test', 'password' => 'Password123!'],
    'teacher_b' => ['id' => 3, 'username' => 'qa_teacher_b', 'email' => 'qa_teacher_b@questbank.test', 'password' => 'Password123!'],
    'student_a' => ['id' => 4, 'username' => 'qa_student_a', 'email' => 'qa_student_a@questbank.test', 'password' => 'Password123!'],
    'student_b' => ['id' => 5, 'username' => 'qa_student_b', 'email' => 'qa_student_b@questbank.test', 'password' => 'Password123!'],
    'exam' => ['id' => 1, 'title' => 'QA Civil Engineering Fundamentals Exam', 'subject' => 'Structural Engineering', 'passing_percentage' => 75.00, 'expected_total_points' => 2, 'expected_percentage' => 100, 'expected_pass_fail' => 'Pass'],
    'questions' => [
        ['id' => 1, 'text' => 'What is the formula for Stopping Sight Distance (SSD)?', 'type' => 'multiple_choice', 'correct_answer' => 'a', 'points' => 1.00],
        ['id' => 2, 'text' => 'Flexible pavement design uses CBR structural number.', 'type' => 'true_false', 'correct_answer' => 'true', 'points' => 1.00]
    ],
    'submissions' => [
        'student_a_published' => 100,
        'student_b_published' => 101,
        'student_a_pending' => 102
    ],
    'analytics_exam' => [
        'id' => 2,
        'title' => 'QA Analytics Benchmark Exam',
        'expected_total' => 4,
        'expected_passed' => 2,
        'expected_failed' => 2,
        'expected_pass_rate' => 50.0,
        'expected_avg_percentage' => 75.0,
        'expected_highest' => 90.0
    ]
];

file_put_contents($fixturesDir . '/test_state.json', json_encode($stateData, JSON_PRETTY_PRINT));
echo "Successfully generated tests/fixtures/test_state.json\n";
echo "=== Deterministic Test Data Seeding Complete ===\n";
