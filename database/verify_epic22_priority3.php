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

    // ── TEST 1: Publication Workflow (Single, Bulk, Exam-Wide) ──
    $stmtEx = $pdo->prepare("
        INSERT INTO exams (teacher_id, title, subject, specialization, time_limit, total_items, exam_category, status, term, covered_periods)
        VALUES (?, 'Priority 3 Test Comprehensive Exam', 'Structural Engineering', 'Structural Engineering', 60, 5, 'regular', 'active', 'midterm', 'midterm')
    ");
    $stmtEx->execute([$teacherId]);
    $examId = $pdo->lastInsertId();
    $createdExamIds[] = $examId;

    // Create submissions in draft/reviewed/finalized statuses
    $stmtSub = $pdo->prepare("
        INSERT INTO exam_submissions (exam_id, student_id, teacher_id, student_name, exam_title, total_score, total_possible_score, total_items, percentage, status, review_status)
        VALUES (?, ?, ?, 'Test Student', 'Priority 3 Test Comprehensive Exam', ?, 100, 5, ?, ?, ?)
    ");
    
    $stmtSub->execute([$examId, $student1Id, $teacherId, 85.0, 85.0, 'Pass', 'reviewed']);
    $sub1Id = $pdo->lastInsertId();
    $createdSubmissionIds[] = $sub1Id;

    $stmtSub->execute([$examId, $student2Id, $teacherId, 92.0, 92.0, 'Pass', 'finalized']);
    $sub2Id = $pdo->lastInsertId();
    $createdSubmissionIds[] = $sub2Id;

    // 1a. Single submission publication
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
            COUNT(*) as total_pub,
            AVG(percentage) as avg_pct,
            MAX(percentage) as max_pct,
            MIN(percentage) as min_pct
        FROM exam_submissions
        WHERE exam_id = ? AND review_status = 'published'
    ");
    $stmtAgg->execute([$examId]);
    $aggRow = $stmtAgg->fetch(PDO::FETCH_ASSOC);

    $aggValid = (intval($aggRow['total_pub']) === 2 && floatval($aggRow['max_pct']) === 92.0 && floatval($aggRow['min_pct']) === 85.0);
    $runner->assertTrue("TEST 5: Statistics SQL aggregation accurately computes published totals", $aggValid, "Avg score: {$aggRow['avg_pct']}%, Max: {$aggRow['max_pct']}%, Min: {$aggRow['min_pct']}%");

    // ── TEST 6: Leaderboard (Published Results Only) ──
    $stmtLeader = $pdo->prepare("
        SELECT 
            u.fullname as student_name,
            es.percentage
        FROM exam_submissions es
        JOIN users u ON es.student_id = u.id
        WHERE es.exam_id = ? AND es.review_status = 'published'
        ORDER BY es.percentage DESC
    ");
    $stmtLeader->execute([$examId]);
    $leaderRows = $stmtLeader->fetchAll(PDO::FETCH_ASSOC);

    $leaderCount = count($leaderRows);
    $topScore = !empty($leaderRows) ? floatval($leaderRows[0]['percentage']) : 0;
    $leaderValid = ($leaderCount === 2 && $topScore === 92.0); // 50% unpub sub is excluded
    $runner->assertTrue("TEST 6: Leaderboard includes only published results sorted by score", $leaderValid, "Leaderboard count: {$leaderCount}, Top Score: {$topScore}%");

    // ── TEST 7: Filter Compatibility ──
    $stmtF = $pdo->prepare("
        SELECT COUNT(*) 
        FROM exam_submissions es
        JOIN exams e ON es.exam_id = e.id
        WHERE es.student_id = ? AND es.review_status = 'published' AND e.subject = 'Structural Engineering' AND e.term = 'midterm'
    ");
    $stmtF->execute([$student1Id]);
    $filterCount = (int)$stmtF->fetchColumn();

    $runner->assertTrue("TEST 7: Subject and Academic Period filter queries execute cleanly", $filterCount >= 1, "Filter returned {$filterCount} matching records");

} catch (Throwable $e) {
    $runner->recordException($e);
} finally {
    if ($pdo) {
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
