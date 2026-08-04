<?php

require_once __DIR__ . '/../app/bootstrap.php';

echo "===========================================================\n";
echo "       QUESTBANK EPIC 2.1 QUALIFYING EXAM VERIFICATION     \n";
echo "===========================================================\n";

$pdo = getDBConnection();

$testsPassed = 0;
$testsFailed = 0;

function assertCondition($condition, $testName, $details = '') {
    global $testsPassed, $testsFailed;
    if ($condition) {
        echo "  [PASS] {$testName}\n";
        if ($details) echo "         -> {$details}\n";
        $testsPassed++;
    } else {
        echo "  [FAIL] {$testName}\n";
        if ($details) echo "         -> FAIL DETAILS: {$details}\n";
        $testsFailed++;
    }
}

try {
    
    $questions = [
        ['text' => 'What is the minimum concrete cover for cast-in-place concrete beams?', 'type' => 'multiple_choice', 'correct' => 'A', 'points' => 1],
        ['text' => 'Nominal shear strength of concrete beam formula symbol is Vc.', 'type' => 'true_false', 'correct' => 'true', 'points' => 1],
        ['text' => 'Flexural reinforcement ratio limit symbol is rho.', 'type' => 'identification', 'correct' => 'rho', 'points' => 1]
    ];

    $qualOptions = [
        'exam_category' => 'qualifying',
        'qualifying_passing_percentage' => 80.00,
        'qualifying_max_attempts' => 2,
        'qualifying_year_level' => '4th Year',
        'qualifying_program' => 'BSCE',
        'qualifying_is_required' => 1,
        'qualifying_unlock_date' => date('Y-m-d H:i:s', strtotime('-1 hour')),
        'qualifying_deadline' => date('Y-m-d H:i:s', strtotime('+24 hours'))
    ];

    $res = ExamService::createExam(12, 'BSCE Comprehensive Qualifying Exam 2026', 'Structural Engineering', 'Structural Engineering', 60, $questions, $qualOptions);
    assertCondition($res['success'] === true, 'TEST 1: Create Qualifying Exam', 'Created exam ID: ' . ($res['exam_id'] ?? 'none'));

    $examId = $res['exam_id'];

    
    $stmtEx = $pdo->prepare("SELECT * FROM exams WHERE id = ?");
    $stmtEx->execute([$examId]);
    $examDb = $stmtEx->fetch(PDO::FETCH_ASSOC);

    assertCondition($examDb['exam_category'] === 'qualifying', 'TEST 2: Database Category Saved as qualifying', "category = {$examDb['exam_category']}");
    assertCondition(floatval($examDb['qualifying_passing_percentage']) === 80.00, 'TEST 3: Qualifying Passing Percentage Saved', "pass_pct = {$examDb['qualifying_passing_percentage']}%");
    assertCondition(intval($examDb['qualifying_max_attempts']) === 2, 'TEST 4: Max Attempts Saved', "max_attempts = {$examDb['qualifying_max_attempts']}");
    assertCondition($examDb['qualifying_year_level'] === '4th Year', 'TEST 5: Eligible Year Level Saved', "year_level = {$examDb['qualifying_year_level']}");
    assertCondition($examDb['qualifying_program'] === 'BSCE', 'TEST 6: Eligible Program Saved', "program = {$examDb['qualifying_program']}");

    
    
    $elig1 = ExamService::checkStudentEligibility(11, $examId);
    assertCondition($elig1['eligible'] === true, 'TEST 7: Eligible Student Access Allowed', 'BSCE 4th Year student allowed');

    
    
    $pdo->prepare("UPDATE exams SET qualifying_program = 'BSCS' WHERE id = ?")->execute([$examId]);
    $elig2 = ExamService::checkStudentEligibility(11, $examId);
    assertCondition($elig2['eligible'] === false, 'TEST 8: Wrong Program Rejection', "Reason: " . ($elig2['reason'] ?? ''));

    
    $pdo->prepare("UPDATE exams SET qualifying_program = 'BSCE', qualifying_year_level = '1st Year' WHERE id = ?")->execute([$examId]);
    $elig3 = ExamService::checkStudentEligibility(11, $examId);
    assertCondition($elig3['eligible'] === false, 'TEST 9: Wrong Year Level Rejection', "Reason: " . ($elig3['reason'] ?? ''));

    
    $stmtRevert = $pdo->prepare("UPDATE exams SET qualifying_program = 'BSCE', qualifying_year_level = '4th Year' WHERE id = ?");
    $stmtRevert->execute([$examId]);

    
    $stmtQs = $pdo->prepare("SELECT id, correct_answer FROM exam_questions WHERE exam_id = ? ORDER BY id ASC");
    $stmtQs->execute([$examId]);
    $qsDb = $stmtQs->fetchAll(PDO::FETCH_ASSOC);
    $qId1 = isset($qsDb[0]['id']) ? (int)$qsDb[0]['id'] : 0;
    $qId2 = isset($qsDb[1]['id']) ? (int)$qsDb[1]['id'] : 0;
    $qId3 = isset($qsDb[2]['id']) ? (int)$qsDb[2]['id'] : 0;

    
    
    try {
        $sub1 = ExamScoringService::evaluateAndSaveSubmission($examId, 11, [
            $qId1 => 'A',
            $qId2 => 'true',
            $qId3 => 'rho'
        ], 12, 'online');
        assertCondition(floatval($sub1['percentage'] ?? 0) == 100.00, 'TEST 10: Submission 1 Score Calculation', "score = " . ($sub1['percentage'] ?? 0) . "%");
    } catch (Throwable $e) {
        assertCondition(false, 'TEST 10: Submission 1 Score Calculation', "Error: " . $e->getMessage());
    }

    
    $stmtSub = $pdo->prepare("SELECT qualification_status, attempt_number FROM exam_submissions WHERE exam_id = ? AND student_id = ? ORDER BY id DESC LIMIT 1");
    $stmtSub->execute([$examId, 11]);
    $subDb1 = $stmtSub->fetch(PDO::FETCH_ASSOC);
    assertCondition(($subDb1['qualification_status'] ?? '') === 'qualified', 'TEST 11: High Score Qualification Status = qualified', "status = " . ($subDb1['qualification_status'] ?? 'none'));
    assertCondition(intval($subDb1['attempt_number'] ?? 0) === 1, 'TEST 12: Attempt Counter Incremented to 1', "attempt_number = " . ($subDb1['attempt_number'] ?? 0));

    
    try {
        $sub2 = ExamScoringService::evaluateAndSaveSubmission($examId, 11, [
            $qId1 => 'B',
            $qId2 => 'false',
            $qId3 => 'rho'
        ], 12, 'online');
        $stmtSub->execute([$examId, 11]);
        $subDb2 = $stmtSub->fetch(PDO::FETCH_ASSOC);
        assertCondition(($subDb2['qualification_status'] ?? '') === 'not_qualified', 'TEST 13: Low Score Qualification Status = not_qualified', "status = " . ($subDb2['qualification_status'] ?? 'none'));
        assertCondition(intval($subDb2['attempt_number'] ?? 0) === 2, 'TEST 14: Attempt Counter Incremented to 2', "attempt_number = " . ($subDb2['attempt_number'] ?? 0));
    } catch (Throwable $e) {
        assertCondition(false, 'TEST 13/14: Submission 2 Evaluation', "Error: " . $e->getMessage());
    }

    
    $elig4 = ExamService::checkStudentEligibility(11, $examId);
    assertCondition($elig4['eligible'] === false, 'TEST 15: Maximum Attempt Limit Reached Blocking', "Reason: " . ($elig4['reason'] ?? ''));

    
    $stmtDeadUpd = $pdo->prepare("UPDATE exams SET qualifying_deadline = ? WHERE id = ?");
    $stmtDeadUpd->execute([date('Y-m-d H:i:s', strtotime('-10 minutes')), $examId]);

    
    $resDead = ExamService::createExam(12, 'Expired Deadline Qualifying Exam', 'Geotechnical Eng', 'Geotechnical Engineering', 30, $questions, [
        'exam_category' => 'qualifying',
        'qualifying_deadline' => date('Y-m-d H:i:s', strtotime('-10 minutes'))
    ]);
    $eligDead = ExamService::checkStudentEligibility(11, $resDead['exam_id']);
    assertCondition($eligDead['eligible'] === false, 'TEST 16: Expired Deadline Blocking', "Reason: " . ($eligDead['reason'] ?? ''));

    
    $resReg = ExamService::createExam(12, 'Regular Final Examination', 'Hydraulics', 'Water Resources', 60, $questions, ['exam_category' => 'regular']);
    $stmtExReg = $pdo->prepare("SELECT exam_category FROM exams WHERE id = ?");
    $stmtExReg->execute([$resReg['exam_id']]);
    $catReg = $stmtExReg->fetchColumn();
    assertCondition($catReg === 'regular', 'TEST 17: Regular Exam Unaffected', "category = {$catReg}");

    
    $createdExamIds = array_filter([$examId, $resDead['exam_id'] ?? null, $resReg['exam_id'] ?? null]);
    if (!empty($createdExamIds)) {
        $inClause = implode(',', array_map('intval', $createdExamIds));
        $pdo->exec("DELETE FROM submission_answers WHERE exam_id IN ($inClause)");
        $pdo->exec("DELETE FROM exam_submissions WHERE exam_id IN ($inClause)");
        $pdo->exec("DELETE FROM exam_questions WHERE exam_id IN ($inClause)");
        $pdo->exec("DELETE FROM exams WHERE id IN ($inClause)");
    }
    echo "\n=== CLEANUP COMPLETE (Test records cleaned up) ===\n";

} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    echo "\n[ERROR] Verification Exception: " . $e->getMessage() . "\n" . $e->getTraceAsString() . "\n";
    $testsFailed++;
}

echo "\n-----------------------------------------------------------\n";
echo "VERIFICATION SUMMARY: {$testsPassed} PASSED, {$testsFailed} FAILED\n";
echo "-----------------------------------------------------------\n";

if ($testsFailed > 0) {
    exit(1);
} else {
    exit(0);
}
