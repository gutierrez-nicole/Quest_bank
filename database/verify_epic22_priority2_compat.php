<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../tests/helpers/test_runner.php';
requireDatabasePreflight();

require_once __DIR__ . '/../app/bootstrap.php';
require_once __DIR__ . '/../app/services/GroqService.php';
require_once __DIR__ . '/../app/services/ExamScoringService.php';
require_once __DIR__ . '/../app/services/EvaluationService.php';

$runner = new TestRunner('QuestBank Priority 2 Canonical 7-Type Compatibility Verification');

$pdo = null;
$createdExamIds = [];
$createdSubmissionIds = [];

try {
    $pdo = getDBConnection();
    $runner->setSetupCompleted($pdo !== null, "Database connection established");

    // ── STEP 1 & 2: Teacher saves mixed exam with all 7 canonical types ──
    $teacherId = (int)$pdo->query("SELECT id FROM users WHERE role = 'teacher' LIMIT 1")->fetchColumn();
    $studentId = (int)$pdo->query("SELECT id FROM users WHERE role = 'student' LIMIT 1")->fetchColumn();

    if (!$teacherId) $teacherId = 1;
    if (!$studentId) $studentId = 1;

    $mixedQuestions = [
        [
            'type' => 'multiple_choice',
            'text' => 'What is the standard unit of force in SI units?',
            'opt_a' => 'Newton (N)',
            'opt_b' => 'Pascal (Pa)',
            'opt_c' => 'Joule (J)',
            'opt_d' => 'Watt (W)',
            'correct' => 'A',
            'points' => 1
        ],
        [
            'type' => 'true_false',
            'text' => 'Concrete has strong tensile strength compared to compressive strength.',
            'correct' => 'False',
            'points' => 1
        ],
        [
            'type' => 'identification',
            'text' => 'Identify the law stating V = I * R.',
            'correct' => 'Ohms Law',
            'points' => 1
        ],
        [
            'type' => 'fill_blank',
            'text' => 'The ratio of stress to strain is known as Youngs ____.',
            'correct' => 'Modulus',
            'points' => 1
        ],
        [
            'type' => 'matching',
            'text' => 'Match each structural component with its primary action:',
            'correct' => '{"Beam":"Flexure","Column":"Compression"}',
            'matching_pairs' => ['Beam' => 'Flexure', 'Column' => 'Compression'],
            'points' => 2
        ],
        [
            'type' => 'problem_solving',
            'text' => 'Calculate the bending moment for a simply supported beam with 10 kN point load at center.',
            'correct' => '25 kN-m',
            'points' => 5
        ],
        [
            'type' => 'math_formula',
            'text' => 'State the quadratic formula expression.',
            'correct' => 'x = (-b +- sqrt(b^2 - 4ac)) / (2a)',
            'formula_latex' => 'x = \\frac{-b \\pm \\sqrt{b^2 - 4ac}}{2a}',
            'points' => 3
        ]
    ];

    // Validate all items before inserting
    $validatedCount = 0;
    foreach ($mixedQuestions as $qItem) {
        $val = GroqService::validateQuestionItem($qItem);
        if ($val['valid']) $validatedCount++;
    }
    $runner->assertTrue("STEP 1: Validate all 7 question structures before save", $validatedCount === 7, "All 7 question types passed validation");

    // Insert Mixed Exam
    $stmtEx = $pdo->prepare("
        INSERT INTO exams (teacher_id, title, subject, specialization, time_limit, total_items, exam_category, status)
        VALUES (?, 'Priority 2 All-Type Comprehensive Exam', 'Structural Engineering', 'Structural Engineering', 60, 7, 'regular', 'active')
    ");
    $stmtEx->execute([$teacherId]);
    $examId = $pdo->lastInsertId();
    $createdExamIds[] = $examId;

    $qStmt = $pdo->prepare("
        INSERT INTO exam_questions (exam_id, question_text, question_type, option_a, option_b, option_c, option_d, correct_answer, formula_latex, matching_pairs, points)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");

    foreach ($mixedQuestions as $mq) {
        $qStmt->execute([
            $examId,
            $mq['text'],
            $mq['type'],
            $mq['opt_a'] ?? null,
            $mq['opt_b'] ?? null,
            $mq['opt_c'] ?? null,
            $mq['opt_d'] ?? null,
            $mq['correct'],
            $mq['formula_latex'] ?? null,
            isset($mq['matching_pairs']) ? json_encode($mq['matching_pairs']) : null,
            $mq['points']
        ]);
    }

    // Verify persistence
    $stmtCheckQ = $pdo->prepare("SELECT id, question_type FROM exam_questions WHERE exam_id = ? ORDER BY id ASC");
    $stmtCheckQ->execute([$examId]);
    $savedRows = $stmtCheckQ->fetchAll(PDO::FETCH_ASSOC);

    $savedTypes = array_map(function($r) { return $r['question_type']; }, $savedRows);
    $expectedTypes = ['multiple_choice', 'true_false', 'identification', 'fill_blank', 'matching', 'problem_solving', 'math_formula'];
    
    $typesMatch = ($savedTypes === $expectedTypes);
    $runner->assertTrue("STEP 2: All 7 question rows persisted with canonical type names", $typesMatch, "Persisted types: " . implode(', ', $savedTypes));

    // ── STEP 3 & 4: Student fetches exam questions ──
    $stmtQFetch = $pdo->prepare("SELECT id, question_text, question_type, option_a, option_b, option_c, option_d, formula_latex, matching_pairs, points FROM exam_questions WHERE exam_id = ? ORDER BY id ASC");
    $stmtQFetch->execute([$examId]);
    $fetchedQuestions = $stmtQFetch->fetchAll(PDO::FETCH_ASSOC);

    $runner->assertTrue("STEP 3 & 4: Student fetches 7 active exam questions dynamically", count($fetchedQuestions) === 7, "Fetched " . count($fetchedQuestions) . " questions from DB");

    // Map question IDs
    $qMap = [];
    foreach ($fetchedQuestions as $fq) {
        $qMap[$fq['question_type']] = $fq['id'];
    }

    // ── STEP 5: Student submits answers for all 7 types ──
    $studentAnswers = [
        $qMap['multiple_choice'] => 'A',
        $qMap['true_false'] => 'False',
        $qMap['identification'] => 'Ohms Law',
        $qMap['fill_blank'] => 'Modulus',
        $qMap['matching'] => ['Beam' => 'Flexure', 'Column' => 'Compression'],
        $qMap['problem_solving'] => 'M_max = (W * L) / 4 = (10 * 10) / 4 = 25 kN-m. Detailed solution submitted.',
        $qMap['math_formula'] => 'x = (-b +- sqrt(b^2 - 4ac)) / (2a)'
    ];

    $evalRes = EvaluationService::evaluateAndSaveSubmission($examId, $studentId, $studentAnswers, 'online');
    $submissionId = $evalRes['submission_id'] ?? 0;
    if ($submissionId) $createdSubmissionIds[] = $submissionId;

    $runner->assertTrue("STEP 5: Submission created successfully", $evalRes['success'] === true && $submissionId > 0, "Submission ID: {$submissionId}");

    // ── STEP 6: Answers persist under correct question IDs ──
    $stmtAnswersDb = $pdo->prepare("SELECT question_id, student_answer, evaluation_status, requires_review FROM submission_answers WHERE submission_id = ?");
    $stmtAnswersDb->execute([$submissionId]);
    $persistedAnswers = $stmtAnswersDb->fetchAll(PDO::FETCH_ASSOC);

    $persistedQIds = array_map(function($a) { return (int)$a['question_id']; }, $persistedAnswers);
    $expectedQIds = array_values($qMap);
    sort($persistedQIds);
    sort($expectedQIds);

    $runner->assertTrue("STEP 6: Answers persisted under correct question IDs", $persistedQIds === $expectedQIds, "Persisted question IDs: " . implode(', ', $persistedQIds));

    // ── STEP 7: Objective items scored correctly ──
    $objStatuses = [];
    foreach ($persistedAnswers as $pa) {
        if (in_array((int)$pa['question_id'], [$qMap['multiple_choice'], $qMap['true_false'], $qMap['identification'], $qMap['fill_blank'], $qMap['matching']])) {
            $objStatuses[(int)$pa['question_id']] = $pa['evaluation_status'];
        }
    }
    $allObjectiveCorrect = true;
    foreach ($objStatuses as $status) {
        if ($status !== 'correct') { $allObjectiveCorrect = false; break; }
    }
    $runner->assertTrue("STEP 7: Objective items (MCQ, T/F, ID, Fill Blank, Matching) scored correct", $allObjectiveCorrect, "Objective statuses: " . json_encode($objStatuses));

    // ── STEP 8: Subjective / Manual-Review items enter review state ──
    $probSolvingAns = null;
    foreach ($persistedAnswers as $pa) {
        if ((int)$pa['question_id'] === $qMap['problem_solving']) {
            $probSolvingAns = $pa;
        }
    }
    $probSolvingNeedsReview = ($probSolvingAns && (int)$probSolvingAns['requires_review'] === 1);
    $submissionPendingReview = ($evalRes['review_status'] === 'pending_review');

    $runner->assertTrue("STEP 8: Subjective / manual-review items entered pending_review state", $probSolvingNeedsReview && $submissionPendingReview, "Submission review_status: {$evalRes['review_status']}");

    // ── STEP 9: Existing MCQ-only exams remain unaffected ──
    $stmtMcqEx = $pdo->prepare("
        INSERT INTO exams (teacher_id, title, subject, specialization, time_limit, total_items, exam_category, status)
        VALUES (?, 'Pure MCQ Control Exam', 'Surveying', 'Geotechnical Engineering', 30, 2, 'regular', 'active')
    ");
    $stmtMcqEx->execute([$teacherId]);
    $mcqExamId = $pdo->lastInsertId();
    $createdExamIds[] = $mcqExamId;

    $qStmt->execute([$mcqExamId, 'What is 2+2?', 'multiple_choice', '3', '4', '5', '6', 'B', null, null, 1]);
    $q1Id = $pdo->lastInsertId();
    $qStmt->execute([$mcqExamId, 'What is capital of France?', 'multiple_choice', 'London', 'Paris', 'Berlin', 'Rome', 'B', null, null, 1]);
    $q2Id = $pdo->lastInsertId();

    $mcqAnswers = [$q1Id => 'B', $q2Id => 'B'];
    $mcqEval = EvaluationService::evaluateAndSaveSubmission($mcqExamId, $studentId, $mcqAnswers, 'online');
    if (!empty($mcqEval['submission_id'])) $createdSubmissionIds[] = $mcqEval['submission_id'];

    $mcqUnaffected = ($mcqEval['success'] === true && $mcqEval['percentage'] == 100.00 && $mcqEval['review_status'] === 'finalized');
    $runner->assertTrue("STEP 9: Existing MCQ-only exams remain 100% unaffected and finalized", $mcqUnaffected, "MCQ exam grade: {$mcqEval['percentage']}%, status: {$mcqEval['review_status']}");

} catch (Throwable $e) {
    $runner->recordException($e);
} finally {
    // Clean up test rows
    if ($pdo) {
        foreach ($createdSubmissionIds as $sid) {
            $pdo->exec("DELETE FROM submission_answers WHERE submission_id = {$sid}");
            $pdo->exec("DELETE FROM exam_submissions WHERE id = {$sid}");
        }
        foreach ($createdExamIds as $eid) {
            $pdo->exec("DELETE FROM exam_questions WHERE exam_id = {$eid}");
            $pdo->exec("DELETE FROM exams WHERE id = {$eid}");
        }
    }
    $runner->finish();
}
