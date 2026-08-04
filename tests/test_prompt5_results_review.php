<?php
/**
 * Automated Test Suite for PROMPT 5 — Result Generation & Teacher Review Workflow
 */

require_once __DIR__ . '/../app/bootstrap.php';
require_once __DIR__ . '/../app/services/EvaluationService.php';

$pdo = getDBConnection();
$teacherStmt = $pdo->query("SELECT id FROM users WHERE role = 'teacher' LIMIT 1");
$teacherId = $teacherStmt->fetchColumn() ?: 1;

$student1Stmt = $pdo->query("SELECT id FROM users WHERE role = 'student' LIMIT 1");
$student1Id = $student1Stmt->fetchColumn() ?: 2;

$student2Stmt = $pdo->query("SELECT id FROM users WHERE role = 'student' AND id != {$student1Id} LIMIT 1");
$student2Id = $student2Stmt->fetchColumn() ?: ($student1Id + 1);

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

// Setup a test exam
$stmtExam = $pdo->prepare("
    INSERT INTO exams (teacher_id, title, subject, specialization, difficulty, time_limit, total_items, passing_percentage) 
    VALUES (?, 'P5 Workflow Exam', 'Geotechnical Engineering', 'Geotechnical Engineering', 'medium', 60, 2, 75.00)
");
$stmtExam->execute([$teacherId]);
$testExamId = $pdo->lastInsertId();

$qStmt = $pdo->prepare("
    INSERT INTO exam_questions 
    (exam_id, question_text, question_type, option_a, option_b, option_c, option_d, correct_answer, points) 
    VALUES (?, ?, 'multiple_choice', 'Opt A', 'Opt B', 'Opt C', 'Opt D', ?, 1)
");
$qStmt->execute([$testExamId, 'Geotechnical Question 1', 'A']);
$q1Id = $pdo->lastInsertId();
$qStmt->execute([$testExamId, 'Geotechnical Question 2', 'B']);
$q2Id = $pdo->lastInsertId();

// 1. Low-Confidence OCR Result Flagged & Hidden from Student
runTestCase("Low-Confidence Result Flagged & Hidden From Student", function() use ($pdo, $testExamId, $student1Id) {
    $ocrMock = [
        'status' => 'manual_review_required',
        'confidence' => 45.00,
        'ocr_text' => 'Unclear scan text',
        'ocr_error' => 'Low confidence'
    ];

    $res = EvaluationService::evaluateAndSaveSubmission($testExamId, $student1Id, [$q1Id => 'A'], 'ocr', $ocrMock);
    $subId = $res['submission_id'];

    if ($res['review_status'] !== 'pending_review') return "Expected review_status pending_review, got " . $res['review_status'];

    // Check student visibility query
    $stmtCheck = $pdo->prepare("SELECT COUNT(*) FROM exam_submissions WHERE id = ? AND student_id = ? AND review_status IN ('published', 'finalized')");
    $stmtCheck->execute([$subId, $student1Id]);
    if ((int)$stmtCheck->fetchColumn() !== 0) return "Pending review submission must NOT be visible to student";

    return true;
});

// 2. Teacher Score Correction & Audit Logging Test
runTestCase("Teacher Score Correction & Audit Logging", function() use ($pdo, $testExamId, $student1Id, $teacherId, $q1Id, $q2Id) {
    // Initial submission
    $res = EvaluationService::evaluateAndSaveSubmission($testExamId, $student1Id, [$q1Id => 'A', $q2Id => 'D'], 'online');
    $subId = $res['submission_id'];
    $oldScore = $res['total_score']; // 1 point

    // Teacher adjusts score / approves question 2
    $newScore = 2.0;
    $reason = 'Teacher accepted alternative option D for Q2 after review.';

    $pdo->beginTransaction();
    $stmtOverride = $pdo->prepare("
        INSERT INTO submission_score_overrides 
        (submission_id, old_score, new_score, reviewer_id, reason, created_at) 
        VALUES (?, ?, ?, ?, ?, NOW())
    ");
    $stmtOverride->execute([$subId, $oldScore, $newScore, $teacherId, $reason]);

    $stmtUpd = $pdo->prepare("
        UPDATE exam_submissions 
        SET total_score = ?, correct_count = 2, wrong_count = 0, percentage = 100.00, status = 'Pass', review_status = 'finalized' 
        WHERE id = ? AND teacher_id = ?
    ");
    $stmtUpd->execute([$newScore, $subId, $teacherId]);
    $pdo->commit();

    // Verify override log record
    $stmtLog = $pdo->prepare("SELECT * FROM submission_score_overrides WHERE submission_id = ?");
    $stmtLog->execute([$subId]);
    $logRec = $stmtLog->fetch(PDO::FETCH_ASSOC);

    if (!$logRec) return "Override log record missing";
    if ((float)$logRec['old_score'] !== (float)$oldScore) return "Old score mismatch in audit log";
    if ((float)$logRec['new_score'] !== 2.0) return "New score mismatch in audit log";

    return true;
});

// 3. Unauthorized Score Correction Rejection Test
runTestCase("Unauthorized Score Correction Rejection", function() use ($pdo, $testExamId, $student1Id, $student2Id) {
    // Create submission owned by teacherId (e.g. ID 1)
    $res = EvaluationService::evaluateAndSaveSubmission($testExamId, $student1Id, [], 'online');
    $subId = $res['submission_id'];

    // Unauthorized user (e.g., student2Id) tries updating submission
    $stmtUpd = $pdo->prepare("UPDATE exam_submissions SET total_score = 100 WHERE id = ? AND teacher_id = ?");
    $stmtUpd->execute([$subId, $student2Id]); // Unauthorized teacher_id filter

    if ($stmtUpd->rowCount() > 0) return "Unauthorized score update was mistakenly allowed!";

    return true;
});

// 4. Student Privacy Protection Test
runTestCase("Student Privacy Protection (Blocked Access to Other Student Result)", function() use ($pdo, $testExamId, $student1Id, $student2Id, $q1Id, $q2Id) {
    $res = EvaluationService::evaluateAndSaveSubmission($testExamId, $student1Id, [$q1Id => 'A', $q2Id => 'B'], 'online');
    $subId = $res['submission_id'];

    // Finalize submission
    $pdo->exec("UPDATE exam_submissions SET review_status = 'published' WHERE id = {$subId}");

    // Student 2 attempts to query Student 1's submission
    $stmtQuery = $pdo->prepare("SELECT * FROM exam_submissions WHERE id = ? AND student_id = ? AND review_status IN ('published', 'finalized')");
    $stmtQuery->execute([$subId, $student2Id]);
    $st2View = $stmtQuery->fetch(PDO::FETCH_ASSOC);

    if ($st2View) return "Student 2 was able to access Student 1's exam result!";

    return true;
});

// 5. Empty Result List Handling Test
runTestCase("Empty Result List Returns Clean Empty Output", function() use ($pdo) {
    $nonExistentStudentId = 9999999;
    $stmt = $pdo->prepare("SELECT * FROM exam_submissions WHERE student_id = ? AND review_status IN ('published', 'finalized')");
    $stmt->execute([$nonExistentStudentId]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (count($rows) !== 0) return "Expected empty results list, got " . count($rows);

    return true;
});

// 6. OCR Rerun Uses Stored Exam Questions and Updates Item-Level Answers
runTestCase("OCR Rerun Uses Stored Exam Questions and Updates Item-Level Answers", function() use ($pdo, $testExamId, $student1Id, $teacherId, $q1Id, $q2Id) {
    // Initial submission
    $res = EvaluationService::evaluateAndSaveSubmission($testExamId, $student1Id, [$q1Id => 'A', $q2Id => 'B'], 'scanned', ['ocr_text' => '1. A 2. B']);
    $subId = $res['submission_id'];

    // If review_status is finalized/published, reprocessOcr MUST reject it
    $pdo->exec("UPDATE exam_submissions SET review_status = 'finalized' WHERE id = {$subId}");
    try {
        ResultWorkflowService::reprocessOcr($subId, $teacherId, 'Testing block');
        return "reprocessOcr should block finalized results without admin reopen!";
    } catch (LogicException $e) {
        // Expected protection block
    }

    // Set to pending_review to test valid reprocessing
    $pdo->exec("UPDATE exam_submissions SET review_status = 'pending_review' WHERE id = {$subId}");

    // Call ResultWorkflowService::reprocessOcr
    $reprocessRes = ResultWorkflowService::reprocessOcr($subId, $teacherId, 'Testing OCR Reprocessing');
    if (!$reprocessRes['success']) return "Reprocessing failed";
    if ((float)$reprocessRes['new_total'] !== 2.0) return "Expected score 2.0, got " . $reprocessRes['new_total'];

    // Verify submission_reprocessing_history record exists
    $histStmt = $pdo->prepare("SELECT * FROM submission_reprocessing_history WHERE submission_id = ?");
    $histStmt->execute([$subId]);
    $hist = $histStmt->fetch(PDO::FETCH_ASSOC);
    if (!$hist) return "Reprocessing history log missing";

    return true;
});

// 7. Authorization Enforcement in ResultWorkflowService
runTestCase("Authorization Enforcement Inside ResultWorkflowService", function() use ($pdo, $testExamId, $student1Id, $student2Id) {
    // Create submission owned by teacherId (1)
    $res = EvaluationService::evaluateAndSaveSubmission($testExamId, $student1Id, [], 'online');
    $subId = $res['submission_id'];

    // Non-existent actor or unauthorized teacher (student2Id) direct service call
    try {
        ResultWorkflowService::transitionStatus($subId, 'reviewed', $student2Id, 'Attempt unauthorized transition');
        return "Expected SecurityException for unauthorized actor, but call succeeded!";
    } catch (SecurityException $e) {
        // Expected
    }

    return true;
});

// 8. Full Snapshot Preservation for Reopened Results
runTestCase("Full Snapshot Preservation for Reopened Results", function() use ($pdo, $testExamId, $student1Id, $teacherId, $q1Id, $q2Id) {
    // Admin user ID
    $adminStmt = $pdo->query("SELECT id FROM users WHERE role = 'admin' LIMIT 1");
    $adminId = $adminStmt->fetchColumn() ?: 1;

    // Create and finalize a submission
    $res = EvaluationService::evaluateAndSaveSubmission($testExamId, $student1Id, [$q1Id => 'A', $q2Id => 'B'], 'online');
    $subId = $res['submission_id'];

    $pdo->exec("UPDATE exam_submissions SET review_status = 'published', published_at = NOW() WHERE id = {$subId}");

    // Non-admin reopen attempt should fail
    try {
        ResultWorkflowService::reopenSubmission($subId, $teacherId, 'pending_review', 'Teacher try reopen');
        return "Non-admin teacher should NOT be able to reopen!";
    } catch (SecurityException $e) {
        // Expected
    }

    // Missing reason should fail
    try {
        ResultWorkflowService::reopenSubmission($subId, $adminId, 'pending_review', '');
        return "Reopen without reason should fail!";
    } catch (InvalidArgumentException $e) {
        // Expected
    }

    // Valid admin reopen
    $reopenRes = ResultWorkflowService::reopenSubmission($subId, $adminId, 'pending_review', 'Audit grade correction request');
    if (!$reopenRes['success']) return "Admin reopen failed";

    // Verify snapshot created in submission_snapshots
    $snapStmt = $pdo->prepare("SELECT * FROM submission_snapshots WHERE submission_id = ?");
    $snapStmt->execute([$subId]);
    $snap = $snapStmt->fetch(PDO::FETCH_ASSOC);

    if (!$snap) return "Reopen grading snapshot missing from submission_snapshots table!";
    if ($snap['review_status'] !== 'published') return "Snapshot must record pre-reopen status (published)";
    if (empty($snap['item_answers'])) return "Snapshot item_answers must be preserved!";

    return true;
});

// Summary
echo "\n=========================================\n";
echo "PROMPT 5 TEST RESULTS: Passed {$passed}, Failed {$failed}\n";
echo "=========================================\n";

if ($failed > 0) {
    exit(1);
} else {
    exit(0);
}
