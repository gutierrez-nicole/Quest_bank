<?php
/**
 * Automated Test Suite for PROMPT 4 — Server-Side Answer Evaluation & Score Computation
 */

require_once __DIR__ . '/../app/bootstrap.php';
require_once __DIR__ . '/../app/services/EvaluationService.php';

$pdo = getDBConnection();
$teacherStmt = $pdo->query("SELECT id FROM users WHERE role = 'teacher' LIMIT 1");
$teacherId = $teacherStmt->fetchColumn() ?: 1;

$passed = 0;
$failed = 0;

function runTestCase($name, $callable) {
    global $passed, $failed;
    echo "[TEST] {$name}... ";
    try {
        $result = $callable();
        if ($result === true) {
            echo "PASSED\n";
            $passed++;
        } else {
            echo "FAILED: " . (is_string($result) ? $result : 'Assertion failed') . "\n";
            $failed++;
        }
    } catch (Throwable $e) {
        echo "FAILED Exception: " . $e->getMessage() . "\n";
        $failed++;
    }
}

// -------------------------------------------------------------
// Setup a temporary test exam with 4 known questions in DB
// -------------------------------------------------------------
$pdo->beginTransaction();
$stmtExam = $pdo->prepare("
    INSERT INTO exams (teacher_id, title, subject, specialization, difficulty, time_limit, total_items, passing_percentage) 
    VALUES (?, 'P4 Test Exam', 'Structural Engineering', 'Structural Engineering', 'medium', 60, 4, 75.00)
");
$stmtExam->execute([$teacherId]);
$testExamId = $pdo->lastInsertId();

$qStmt = $pdo->prepare("
    INSERT INTO exam_questions 
    (exam_id, question_text, question_type, option_a, option_b, option_c, option_d, correct_answer, points) 
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
");

// Question 1: Multiple choice (Key: A)
$qStmt->execute([$testExamId, 'What is concrete flexural strength?', 'multiple_choice', 'Modulus of rupture', 'Compressive strength', 'Tensile yield', 'Shear capacity', 'A', 1]);
$q1Id = $pdo->lastInsertId();

// Question 2: True/False (Key: True)
$qStmt->execute([$testExamId, 'Steel provides tensile resistance in RC beams.', 'true_false', null, null, null, null, 'True', 1]);
$q2Id = $pdo->lastInsertId();

// Question 3: Identification (Key: Shear Wall)
$qStmt->execute([$testExamId, 'Name the structural wall that resists lateral wind/seismic loads.', 'identification', null, null, null, null, 'Shear Wall', 1]);
$q3Id = $pdo->lastInsertId();

// Question 4: Multiple choice (Key: C)
$qStmt->execute([$testExamId, 'What is Hooke law constant?', 'multiple_choice', 'Viscosity', 'Density', 'Modulus of Elasticity', 'Thermal Expansion', 'C', 1]);
$q4Id = $pdo->lastInsertId();

$pdo->commit();

// 1. All Correct Test
runTestCase("All Correct Answers Scoring", function() use ($testExamId, $q1Id, $q2Id, $q3Id, $q4Id) {
    $answers = [
        $q1Id => 'A',
        $q2Id => 'true',
        $q3Id => 'shear wall',
        $q4Id => 'C'
    ];

    $res = EvaluationService::evaluateAndSaveSubmission($testExamId, 0, $answers, 'online');
    if (!$res['success']) return "Evaluation failed: " . ($res['error'] ?? '');
    if ((int)$res['correct_count'] !== 4) return "Expected 4 correct, got " . $res['correct_count'];
    if ((float)$res['percentage'] !== 100.00) return "Expected 100%, got " . $res['percentage'];
    if ($res['status'] !== 'Pass') return "Expected status Pass, got " . $res['status'];

    return true;
});

// 2. All Incorrect Test
runTestCase("All Incorrect Answers Scoring", function() use ($testExamId, $q1Id, $q2Id, $q3Id, $q4Id) {
    $answers = [
        $q1Id => 'B',
        $q2Id => 'false',
        $q3Id => 'wrong answer text',
        $q4Id => 'D'
    ];

    $res = EvaluationService::evaluateAndSaveSubmission($testExamId, 0, $answers, 'online');
    if (!$res['success']) return "Evaluation failed";
    if ((int)$res['correct_count'] !== 0) return "Expected 0 correct";
    if ((float)$res['percentage'] !== 0.00) return "Expected 0%";
    if ($res['status'] !== 'Fail') return "Expected status Fail";

    return true;
});

// 3. Blank / Unanswered Items Test
runTestCase("Blank/Unanswered Items Handling", function() use ($testExamId, $q1Id) {
    $answers = [
        $q1Id => 'A' // Only 1 answered out of 4
    ];

    $res = EvaluationService::evaluateAndSaveSubmission($testExamId, 0, $answers, 'online');
    if ((int)$res['correct_count'] !== 1) return "Expected 1 correct";
    if ((int)$res['wrong_count'] !== 3) return "Expected 3 wrong/unanswered";
    if ((float)$res['percentage'] !== 25.00) return "Expected 25.00%";
    if ($res['status'] !== 'Fail') return "Expected status Fail";

    return true;
});

// 4. Capitalization and Whitespace Tolerance Test
runTestCase("Capitalization and Whitespace Tolerance", function() use ($testExamId, $q1Id, $q2Id, $q3Id, $q4Id) {
    $answers = [
        $q1Id => ' a ',           // lowercase + padded space
        $q2Id => ' TRUE ',        // uppercase + space
        $q3Id => '  shear wall ', // extra spaces
        $q4Id => 'c'              // lowercase
    ];

    $res = EvaluationService::evaluateAndSaveSubmission($testExamId, 0, $answers, 'online');
    if ((int)$res['correct_count'] !== 4) return "Expected 4 correct despite case/space variance";
    if ((float)$res['percentage'] !== 100.00) return "Expected 100%";

    return true;
});

// 5. Invalid & Duplicated Question ID Protection Test
runTestCase("Invalid & Duplicated Question ID Protection", function() use ($testExamId, $q1Id, $q2Id, $q3Id, $q4Id) {
    $answers = [
        $q1Id => 'A',
        999999 => 'A', // Unknown question ID
        $q2Id => 'True',
        $q3Id => 'Shear Wall',
        $q4Id => 'C'
    ];

    $res = EvaluationService::evaluateAndSaveSubmission($testExamId, 0, $answers, 'online');
    if ((int)$res['correct_count'] !== 4) return "Expected 4 correct items";
    if ((float)$res['percentage'] !== 100.00) return "Manipulated/unknown question ID should not inflate score";

    return true;
});

// 6. Server-Side Score Recalculation (Rejection of Manipulated POST Values) Test
runTestCase("Server-Side Score Recalculation", function() use ($testExamId, $q1Id) {
    // Submit only 1 correct answer (25%)
    $answers = [$q1Id => 'A'];

    $res = EvaluationService::evaluateAndSaveSubmission($testExamId, 0, $answers, 'online');

    if ((float)$res['percentage'] === 100.00) return "Server trusted manipulated score!";
    if ((float)$res['percentage'] !== 25.00) return "Expected recomputed percentage 25.00%";

    return true;
});

// 7. Exact Passing Score vs Below Passing Score Test
runTestCase("Exact Passing Score (75%) vs Below (50%)", function() use ($testExamId, $q1Id, $q2Id, $q3Id) {
    // 3 out of 4 correct = 75% -> Exact Pass
    $answersPass = [$q1Id => 'A', $q2Id => 'True', $q3Id => 'Shear Wall'];
    $resPass = EvaluationService::evaluateAndSaveSubmission($testExamId, 0, $answersPass, 'online');
    if ($resPass['status'] !== 'Pass') return "Expected 75% to pass";

    // 2 out of 4 correct = 50% -> Fail
    $answersFail = [$q1Id => 'A', $q2Id => 'True'];
    $resFail = EvaluationService::evaluateAndSaveSubmission($testExamId, 0, $answersFail, 'online');
    if ($resFail['status'] !== 'Fail') return "Expected 50% to fail";

    return true;
});

// Summary
echo "\n=========================================\n";
echo "PROMPT 4 TEST RESULTS: Passed {$passed}, Failed {$failed}\n";
echo "=========================================\n";

if ($failed > 0) {
    exit(1);
} else {
    exit(0);
}
