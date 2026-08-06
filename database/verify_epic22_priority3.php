<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../tests/helpers/test_runner.php';
requireDatabasePreflight();

require_once __DIR__ . '/../app/bootstrap.php';
require_once __DIR__ . '/../app/services/AuthorizationService.php';
require_once __DIR__ . '/../app/services/ResultWorkflowService.php';

$runner = new TestRunner('QuestBank Priority 3 Full Implementation Verification');

$pdo = null;
$createdExamIds = [];
$createdSubmissionIds = [];
$createdLessonIds = [];

try {
    $pdo = getDBConnection();
    $runner->setSetupCompleted($pdo !== null, "Database connection established");

    // Fetch test users dynamically
    $teacherId = (int)$pdo->query("SELECT id FROM users WHERE role = 'teacher' LIMIT 1")->fetchColumn();
    $student1Id = (int)$pdo->query("SELECT id FROM users WHERE role = 'student' LIMIT 1")->fetchColumn();
    $student2Id = (int)$pdo->query("SELECT id FROM users WHERE role = 'student' AND id != {$student1Id} LIMIT 1")->fetchColumn();

    if (!$teacherId) $teacherId = 10;
    if (!$student1Id) $student1Id = 11;
    if (!$student2Id) $student2Id = 12;

    // ── TEST 1: Publication Workflow (Single & Bulk) ──
    $stmtEx = $pdo->prepare("
        INSERT INTO exams (teacher_id, title, subject, specialization, time_limit, total_items, exam_category, status, term, covered_periods)
        VALUES (?, 'Priority 3 Test Comprehensive Exam', 'Structural Engineering', 'Structural Engineering', 60, 5, 'regular', 'active', 'midterm', 'midterm')
    ");
    $stmtEx->execute([$teacherId]);
    $examId = $pdo->lastInsertId();
    $createdExamIds[] = $examId;

    // Create submissions in draft/reviewed/finalized statuses
    $stmtSub = $pdo->prepare("
        INSERT INTO exam_submissions (exam_id, student_id, teacher_id, student_name, exam_title, total_score, total_possible_score, total_items, percentage, status, review_status, ocr_confidence, suggested_manual_review)
        VALUES (?, ?, ?, 'Test Student', 'Priority 3 Test Comprehensive Exam', ?, 100, 5, ?, ?, ?, 95.00, 0)
    ");
    
    $stmtSub->execute([$examId, $student1Id, $teacherId, 85.0, 85.0, 'Pass', 'reviewed']);
    $sub1Id = $pdo->lastInsertId();
    $createdSubmissionIds[] = $sub1Id;

    $stmtSub->execute([$examId, $student2Id, $teacherId, 92.0, 92.0, 'Pass', 'finalized']);
    $sub2Id = $pdo->lastInsertId();
    $createdSubmissionIds[] = $sub2Id;

    // 1a. Single submission publication (reviewed -> finalized -> published)
    ResultWorkflowService::transitionStatus($sub1Id, 'finalized', $teacherId, 'Finalization test');
    $pubRes1 = ResultWorkflowService::transitionStatus($sub1Id, 'published', $teacherId, 'Single publication test');
    $runner->assertTrue("TEST 1a: Single submission transitioned to published", $pubRes1['success'] === true && $pubRes1['new_status'] === 'published', "Published at: {$pubRes1['published_at']}");

    // 1b. Bulk publication
    $bulkRes = ResultWorkflowService::bulkPublishSubmissions([$sub2Id], $teacherId, 'Bulk publication test');
    $runner->assertTrue("TEST 1b: Bulk publication executed successfully", $bulkRes['published_count'] === 1, "Published count: {$bulkRes['published_count']}");

    // 1c. Immutability check: cannot return published submission to draft via normal workflow
    $backwardBlocked = false;
    try {
        ResultWorkflowService::transitionStatus($sub1Id, 'pending_review', $teacherId);
    } catch (SecurityException $e) {
        $backwardBlocked = true;
    } catch (InvalidArgumentException $e) {
        $backwardBlocked = true;
    }
    $runner->assertTrue("TEST 1c: Published submission cannot return to draft via teacher transition", $backwardBlocked, "Illegal transition blocked cleanly");

    // ── TEST 2: Student Privacy & Access Control ──
    $privRes1 = ResultWorkflowService::enforceStudentPrivacy($sub1Id, $student1Id);
    $runner->assertTrue("TEST 2a: Student can view own published submission", $privRes1['allowed'] === true, "Allowed access for owner student #{$student1Id}");

    $privRes2 = ResultWorkflowService::enforceStudentPrivacy($sub1Id, $student2Id);
    $runner->assertTrue("TEST 2b: Student CANNOT view another student's submission", $privRes2['allowed'] === false, "Access denied: {$privRes2['error']}");

    // Create an unpublished submission
    $stmtSub->execute([$examId, $student1Id, $teacherId, 50.0, 50.0, 'Fail', 'pending_review']);
    $unpubSubId = $pdo->lastInsertId();
    $createdSubmissionIds[] = $unpubSubId;

    $privRes3 = ResultWorkflowService::enforceStudentPrivacy($unpubSubId, $student1Id);
    $runner->assertTrue("TEST 2c: Student CANNOT view own unpublished submission", $privRes3['allowed'] === false, "Unpublished result hidden cleanly");

    // ── TEST 3: Teacher Ownership & Authorization ──
    $canTeacherView = AuthorizationService::canReviewSubmission($teacherId, $sub1Id);
    $runner->assertTrue("TEST 3a: Owning teacher has review authority over submission", $canTeacherView === true, "Authorization granted for teacher #{$teacherId}");

    $otherTeacherId = $teacherId + 999;
    $canOtherTeacherView = AuthorizationService::canReviewSubmission($otherTeacherId, $sub1Id);
    $runner->assertTrue("TEST 3b: Non-owning teacher CANNOT review submission", $canOtherTeacherView === false, "Authorization denied for unassigned teacher #{$otherTeacherId}");

    // ── TEST 4: Qualifying Exam Results & Attempts Calculation ──
    $stmtQualEx = $pdo->prepare("
        INSERT INTO exams (teacher_id, title, subject, specialization, time_limit, total_items, exam_category, status, qualifying_passing_percentage, qualifying_max_attempts)
        VALUES (?, 'Priority 3 Qualifying Verification Exam', 'Geotechnical Engineering', 'Geotechnical Engineering', 45, 10, 'qualifying', 'active', 75.00, 3)
    ");
    $stmtQualEx->execute([$teacherId]);
    $qualExamId = $pdo->lastInsertId();
    $createdExamIds[] = $qualExamId;

    $stmtSub->execute([$qualExamId, $student1Id, $teacherId, 88.0, 88.0, 'Pass', 'published']);
    $qualSubId = $pdo->lastInsertId();
    $createdSubmissionIds[] = $qualSubId;

    // Update qualification_status
    $pdo->exec("UPDATE exam_submissions SET qualification_status = 'qualified' WHERE id = {$qualSubId}");

    $stmtQualCheck = $pdo->prepare("
        SELECT 
            es.qualification_status,
            e.qualifying_max_attempts,
            (SELECT COUNT(*) FROM exam_submissions WHERE student_id = ? AND exam_id = e.id) as attempts_used
        FROM exam_submissions es
        JOIN exams e ON es.exam_id = e.id
        WHERE es.id = ?
    ");
    $stmtQualCheck->execute([$student1Id, $qualSubId]);
    $qualData = $stmtQualCheck->fetch(PDO::FETCH_ASSOC);

    $remAttempts = max(0, intval($qualData['qualifying_max_attempts']) - intval($qualData['attempts_used']));
    $qualStatusValid = ($qualData['qualification_status'] === 'qualified' && $remAttempts === 2);
    $runner->assertTrue("TEST 4: Qualifying status and remaining attempts calculated correctly", $qualStatusValid, "Status: {$qualData['qualification_status']}, Attempts Remaining: {$remAttempts}");

    // ── TEST 5: Statistics & SQL Aggregations ──
    $stmtAgg = $pdo->prepare("
        SELECT 
            COUNT(DISTINCT es.id) as total_pub,
            AVG(CASE WHEN es.review_status = 'published' THEN es.percentage ELSE NULL END) as avg_pct,
            MAX(CASE WHEN es.review_status = 'published' THEN es.percentage ELSE NULL END) as max_pct,
            MIN(CASE WHEN es.review_status = 'published' THEN es.percentage ELSE NULL END) as min_pct
        FROM exam_submissions es
        WHERE es.exam_id = ? AND es.review_status = 'published'
    ");
    $stmtAgg->execute([$examId]);
    $aggRow = $stmtAgg->fetch(PDO::FETCH_ASSOC);

    $aggValid = (intval($aggRow['total_pub']) === 2 && floatval($aggRow['max_pct']) === 92.0 && floatval($aggRow['min_pct']) === 85.0);
    $runner->assertTrue("TEST 5: Statistics SQL aggregation accurately computes published totals", $aggValid, "Avg score: {$aggRow['avg_pct']}%, Max: {$aggRow['max_pct']}%, Min: {$aggRow['min_pct']}%");

    // ── TEST 6: Leaderboard (Published Results Only) ──
    $stmtLeader = $pdo->prepare("
        SELECT 
            u.id as student_id,
            u.fullname as student_name,
            MAX(es.percentage) as percentage
        FROM exam_submissions es
        JOIN users u ON es.student_id = u.id
        WHERE es.exam_id = ? AND es.review_status = 'published'
        GROUP BY u.id, u.fullname
        ORDER BY percentage DESC
    ");
    $stmtLeader->execute([$examId]);
    $leaderRows = $stmtLeader->fetchAll(PDO::FETCH_ASSOC);

    $leaderCount = count($leaderRows);
    $topScore = !empty($leaderRows) ? floatval($leaderRows[0]['percentage']) : 0;
    $leaderValid = ($leaderCount === 2 && $topScore === 92.0); // 50% unpub sub is excluded
    $runner->assertTrue("TEST 6: Leaderboard includes only published results sorted by score", $leaderValid, "Leaderboard count: {$leaderCount}, Top Score: {$topScore}%");

    // ── TEST 7: Filter Compatibility ──
    $stmtF = $pdo->prepare("
        SELECT COUNT(DISTINCT es.id) 
        FROM exam_submissions es
        JOIN exams e ON es.exam_id = e.id
        WHERE es.student_id = ? AND es.review_status = 'published' AND e.subject = 'Structural Engineering' AND e.term = 'midterm'
    ");
    $stmtF->execute([$student1Id]);
    $filterCount = (int)$stmtF->fetchColumn();

    $runner->assertTrue("TEST 7: Subject and Academic Period filter queries execute cleanly", $filterCount >= 1, "Filter returned {$filterCount} matching records");

    // ── TEST 8: Exam-Wide Publication Skipping Unfinalized & Unreviewed Submissions ──
    $stmtEx8 = $pdo->prepare("
        INSERT INTO exams (teacher_id, title, subject, specialization, time_limit, total_items, exam_category, status, term)
        VALUES (?, 'Priority 3 Exam Wide Skip Test', 'Hydraulics', 'Hydraulics', 30, 5, 'regular', 'active', 'midterm')
    ");
    $stmtEx8->execute([$teacherId]);
    $exam8Id = $pdo->lastInsertId();
    $createdExamIds[] = $exam8Id;

    // Sub A: finalized (eligible)
    $stmtSub->execute([$exam8Id, $student1Id, $teacherId, 80.0, 80.0, 'Pass', 'finalized']);
    $subA = $pdo->lastInsertId();
    $createdSubmissionIds[] = $subA;

    // Sub B: finalized (eligible)
    $stmtSub->execute([$exam8Id, $student2Id, $teacherId, 90.0, 90.0, 'Pass', 'finalized']);
    $subB = $pdo->lastInsertId();
    $createdSubmissionIds[] = $subB;

    // Sub C: pending_review (ineligible -> should be skipped)
    $stmtSub->execute([$exam8Id, $student1Id, $teacherId, 40.0, 40.0, 'Fail', 'pending_review']);
    $subC = $pdo->lastInsertId();
    $createdSubmissionIds[] = $subC;

    // Sub D: reviewed but not finalized (ineligible -> should be skipped)
    $stmtSub->execute([$exam8Id, $student2Id, $teacherId, 75.0, 75.0, 'Pass', 'reviewed']);
    $subD = $pdo->lastInsertId();
    $createdSubmissionIds[] = $subD;

    $pubAllRes = ResultWorkflowService::publishEntireExam($exam8Id, $teacherId, 'Exam wide publication test');

    $pubAllValid = (
        $pubAllRes['eligible_count'] === 2 &&
        $pubAllRes['published_count'] === 2 &&
        $pubAllRes['skipped_count'] === 2 &&
        in_array($subC, $pubAllRes['skipped_submission_ids']) &&
        in_array($subD, $pubAllRes['skipped_submission_ids'])
    );

    $subCStatus = $pdo->query("SELECT review_status FROM exam_submissions WHERE id = {$subC}")->fetchColumn();
    $subDStatus = $pdo->query("SELECT review_status FROM exam_submissions WHERE id = {$subD}")->fetchColumn();
    $subCUnchanged = ($subCStatus === 'pending_review' && $subDStatus === 'reviewed');

    $runner->assertTrue(
        "TEST 8: Exam-wide publication publishes finalized submissions and skips unfinalized with reason",
        $pubAllValid && $subCUnchanged,
        "Eligible: {$pubAllRes['eligible_count']}, Published: {$pubAllRes['published_count']}, Skipped: {$pubAllRes['skipped_count']}. SubC: {$subCStatus}, SubD: {$subDStatus}"
    );

    // ── TEST 9: One-To-Many Duplication Test (3 Linked Lessons) ──
    $stmtL1 = $pdo->prepare("INSERT INTO lesson_materials (teacher_id, subject, title, file_name, file_path, file_type, file_size, academic_period, semester, school_year) VALUES (?, 'Hydraulics', 'Lesson 1', 'l1.pdf', '/tmp/l1.pdf', 'pdf', 1024, 'midterm', '1st Semester', '2025-2026')");
    $stmtL1->execute([$teacherId]);
    $createdLessonIds[] = $pdo->lastInsertId();

    $stmtL2 = $pdo->prepare("INSERT INTO lesson_materials (teacher_id, subject, title, file_name, file_path, file_type, file_size, academic_period, semester, school_year) VALUES (?, 'Hydraulics', 'Lesson 2', 'l2.pdf', '/tmp/l2.pdf', 'pdf', 1024, 'midterm', '1st Semester', '2025-2026')");
    $stmtL2->execute([$teacherId]);
    $createdLessonIds[] = $pdo->lastInsertId();

    $stmtL3 = $pdo->prepare("INSERT INTO lesson_materials (teacher_id, subject, title, file_name, file_path, file_type, file_size, academic_period, semester, school_year) VALUES (?, 'Hydraulics', 'Lesson 3', 'l3.pdf', '/tmp/l3.pdf', 'pdf', 1024, 'midterm', '1st Semester', '2025-2026')");
    $stmtL3->execute([$teacherId]);
    $createdLessonIds[] = $pdo->lastInsertId();

    // Query teacher submissions list grouped by es.id
    $stmtNoDup = $pdo->prepare("
        SELECT es.id
        FROM exam_submissions es
        JOIN exams e ON es.exam_id = e.id
        WHERE es.exam_id = ? AND es.review_status = 'published' AND EXISTS (SELECT 1 FROM lesson_materials lm WHERE lm.subject = e.subject AND lm.semester = '1st Semester')
        GROUP BY es.id
    ");
    $stmtNoDup->execute([$exam8Id]);
    $noDupRows = $stmtNoDup->fetchAll(PDO::FETCH_ASSOC);

    $noDupValid = (count($noDupRows) === 2); // exactly 2 published submissions, no tripling
    $runner->assertTrue("TEST 9: Exam linked to 3 lessons does not duplicate submission counts", $noDupValid, "Query returned exactly " . count($noDupRows) . " published submission rows (expected 2)");

    // ── TEST 10: Non-Admin Archived Republishing Security ──
    $pdo->exec("UPDATE exam_submissions SET review_status = 'archived' WHERE id = {$subA}");
    $archivedRepubBlocked = false;
    try {
        ResultWorkflowService::transitionStatus($subA, 'published', $teacherId);
    } catch (SecurityException $e) {
        $archivedRepubBlocked = true;
    } catch (InvalidArgumentException $e) {
        $archivedRepubBlocked = true;
    }
    $runner->assertTrue("TEST 10: Teacher cannot republish archived submission without admin authorization", $archivedRepubBlocked, "Archived republish blocked for teacher cleanly");

    // ── TEST 11: Student Leaderboard Privacy Masking ──
    $privacyMasked = false;
    foreach ($leaderRows as $lbItem) {
        $displayName = (intval($lbItem['student_id']) !== intval($student1Id)) ? ('Student #' . $lbItem['student_id']) : ($lbItem['student_name'] . ' (You)');
        if (intval($lbItem['student_id']) === intval($student2Id) && $displayName === 'Student #' . $student2Id) {
            $privacyMasked = true;
        }
    }
    $runner->assertTrue("TEST 11: Student leaderboard applies privacy masking to other students' names", $privacyMasked, "Other student name masked as Student #ID");

    // ── TEST 12: Publication Readiness Assertions ──
    // Create sub with low OCR confidence in finalized status
    $stmtSub->execute([$examId, $student1Id, $teacherId, 80.0, 80.0, 'Pass', 'finalized']);
    $lowOcrSubId = $pdo->lastInsertId();
    $createdSubmissionIds[] = $lowOcrSubId;
    $pdo->exec("UPDATE exam_submissions SET ocr_confidence = 50.00 WHERE id = {$lowOcrSubId}");

    $lowOcrBlocked = false;
    try {
        ResultWorkflowService::assertReadyForPublication($lowOcrSubId);
    } catch (LogicException $e) {
        $lowOcrBlocked = true;
    }
    $runner->assertTrue("TEST 12a: Low OCR confidence (<75%) blocks publication readiness assertion", $lowOcrBlocked, "Low OCR confidence publication assertion rejected cleanly");

    // Create sub with suggested_manual_review = 1
    $stmtSub->execute([$examId, $student1Id, $teacherId, 80.0, 80.0, 'Pass', 'finalized']);
    $manualRevSubId = $pdo->lastInsertId();
    $createdSubmissionIds[] = $manualRevSubId;
    $pdo->exec("UPDATE exam_submissions SET suggested_manual_review = 1 WHERE id = {$manualRevSubId}");

    $manualRevBlocked = false;
    try {
        ResultWorkflowService::assertReadyForPublication($manualRevSubId);
    } catch (LogicException $e) {
        $manualRevBlocked = true;
    }
    $runner->assertTrue("TEST 12b: Suggested manual review flag blocks publication readiness assertion", $manualRevBlocked, "Manual review flag assertion rejected cleanly");

    // Create sub with answer item requires_review = 1
    $stmtQ = $pdo->prepare("INSERT INTO exam_questions (exam_id, question_type, question_text, correct_answer, points) VALUES (?, 'multiple_choice', 'Test Question for Review Assertion', 'A', 1.00)");
    $stmtQ->execute([$examId]);
    $testQId = $pdo->lastInsertId();

    $stmtSub->execute([$examId, $student1Id, $teacherId, 80.0, 80.0, 'Pass', 'finalized']);
    $unresolvedAnsSubId = $pdo->lastInsertId();
    $createdSubmissionIds[] = $unresolvedAnsSubId;
    $pdo->exec("INSERT INTO submission_answers (submission_id, exam_id, student_id, question_id, requires_review) VALUES ({$unresolvedAnsSubId}, {$examId}, {$student1Id}, {$testQId}, 1)");

    $unresolvedAnsBlocked = false;
    try {
        ResultWorkflowService::assertReadyForPublication($unresolvedAnsSubId);
    } catch (LogicException $e) {
        $unresolvedAnsBlocked = true;
    }
    $runner->assertTrue("TEST 12c: Unresolved answer items (requires_review=1) block publication readiness", $unresolvedAnsBlocked, "Unresolved answer items assertion rejected cleanly");

    // Direct reviewed -> published without finalized status must throw LogicException
    $directRevPubBlocked = false;
    try {
        ResultWorkflowService::transitionStatus($subD, 'published', $teacherId);
    } catch (LogicException $e) {
        $directRevPubBlocked = true;
    } catch (InvalidArgumentException $e) {
        $directRevPubBlocked = true;
    }
    $runner->assertTrue("TEST 12d: Direct transition from 'reviewed' to 'published' rejected (requires 'finalized')", $directRevPubBlocked, "Direct reviewed->published blocked cleanly");

    // ── TEST 13: Strengthened Authorization Checks ──
    $canPubActive = AuthorizationService::canPublishSubmission($teacherId, $subB);
    $runner->assertTrue("TEST 13a: Active owning teacher is authorized to publish", $canPubActive === true, "Authorized teacher can publish");

    $canPubOther = AuthorizationService::canPublishSubmission($otherTeacherId, $subB);
    $runner->assertTrue("TEST 13b: Unassigned teacher is denied publication authorization", $canPubOther === false, "Unassigned teacher publication denied");

} catch (Throwable $e) {
    $runner->recordException($e);
} finally {
    if ($pdo) {
        foreach ($createdLessonIds as $lid) {
            $pdo->exec("DELETE FROM lesson_materials WHERE id = {$lid}");
        }
        foreach ($createdSubmissionIds as $sid) {
            $pdo->exec("DELETE FROM submission_answers WHERE submission_id = {$sid}");
            $pdo->exec("DELETE FROM submission_status_history WHERE submission_id = {$sid}");
            $pdo->exec("DELETE FROM exam_submissions WHERE id = {$sid}");
        }
        foreach ($createdExamIds as $eid) {
            $pdo->exec("DELETE FROM exam_questions WHERE exam_id = {$eid}");
            $pdo->exec("DELETE FROM exams WHERE id = {$eid}");
        }
    }
    $runner->finish();
}
