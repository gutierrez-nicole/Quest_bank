<?php

require_once __DIR__ . '/../database.php';
require_once __DIR__ . '/../../includes/security.php';
require_once __DIR__ . '/AuthorizationService.php';

class ResultWorkflowService {

    
    const ALLOWED_TRANSITIONS = [
        'pending_review' => ['reviewed'],
        'reviewed'       => ['finalized', 'published'],
        'finalized'      => ['published'],
        'published'      => ['archived'],
        'archived'       => ['published']
    ];

    

    public static function transitionStatus($submissionId, $targetStatus, $reviewerId, $remarks = '') {
        $pdo = getDBConnection();

        
        $stmtUser = $pdo->prepare("SELECT id, role, status FROM users WHERE id = ?");
        $stmtUser->execute([$reviewerId]);
        $actor = $stmtUser->fetch(PDO::FETCH_ASSOC);

        if (!$actor || ($actor['status'] ?? 'active') !== 'active') {
            throw new SecurityException("Unauthorized or inactive user #{$reviewerId}.");
        }

        $actorRole = strtolower(trim($actor['role'] ?? ''));

        $stmt = $pdo->prepare("SELECT * FROM exam_submissions WHERE id = ?");
        $stmt->execute([$submissionId]);
        $sub = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$sub) {
            throw new Exception("Submission #{$submissionId} not found.");
        }

        
        if (!AuthorizationService::canReviewSubmission($reviewerId, $submissionId)) {
            throw new SecurityException("Unauthorized: User #{$reviewerId} is not authorized to review submission #{$submissionId}.");
        }

        $currentStatus = $sub['review_status'] ?? 'pending_review';
        $targetStatus = strtolower(trim($targetStatus));

        if ($targetStatus === 'published' && !AuthorizationService::canPublishSubmission($reviewerId, $submissionId)) {
            throw new SecurityException("Unauthorized: User #{$reviewerId} is not authorized to publish submission #{$submissionId}.");
        }

        
        $backwardTransitions = ['reviewed' => 'pending_review', 'finalized' => 'reviewed', 'published' => 'finalized', 'archived' => 'published'];
        if (isset($backwardTransitions[$currentStatus]) && $backwardTransitions[$currentStatus] === $targetStatus) {
            throw new SecurityException("Unauthorized: Teachers cannot perform backward transitions or reopen results. Use the administrative reopen workflow instead.");
        }

        
        if (!isset(self::ALLOWED_TRANSITIONS[$currentStatus]) || !in_array($targetStatus, self::ALLOWED_TRANSITIONS[$currentStatus])) {
            throw new InvalidArgumentException("Illegal status transition from '{$currentStatus}' to '{$targetStatus}'. Skipped or backward transitions are strictly rejected.");
        }

        
        if ($targetStatus === 'finalized') {
            $ocrConf = floatval($sub['ocr_confidence'] ?? 100.00);
            $manualRev = intval($sub['suggested_manual_review'] ?? 0);

            
            $stmtAns = $pdo->prepare("SELECT COUNT(*) FROM submission_answers WHERE submission_id = ? AND requires_review = 1");
            $stmtAns->execute([$submissionId]);
            $unresolvedItems = intval($stmtAns->fetchColumn());

            if ($ocrConf < 75.00 || $manualRev === 1 || $unresolvedItems > 0) {
                throw new LogicException("Cannot finalize submission: Contains unresolved low-confidence OCR flags or item review requirements.");
            }
        }

        
        if ($targetStatus === 'published') {
            if (!in_array($currentStatus, ['reviewed', 'finalized', 'archived'], true)) {
                throw new LogicException("Publication rejected: Submission must be reviewed or finalized before publishing.");
            }

            if (empty($reviewerId)) {
                throw new LogicException("Publication rejected: Valid reviewer ID is required.");
            }
        }

        $publishedAt = ($targetStatus === 'published') ? date('Y-m-d H:i:s') : $sub['published_at'];
        $reviewedAt = date('Y-m-d H:i:s');

        $pdo->beginTransaction();

        try {
            $stmtUpd = $pdo->prepare("
                UPDATE exam_submissions 
                SET review_status = ?, reviewed_by = ?, teacher_remarks = ?, reviewed_at = ?, published_at = ?
                WHERE id = ?
            ");
            $stmtUpd->execute([$targetStatus, $reviewerId, $remarks, $reviewedAt, $publishedAt, $submissionId]);

            
            $stmtHist = $pdo->prepare("
                INSERT INTO submission_status_history (submission_id, previous_status, new_status, actor_id, remarks, created_at)
                VALUES (?, ?, ?, ?, ?, NOW())
            ");
            $stmtHist->execute([$submissionId, $currentStatus, $targetStatus, $reviewerId, $remarks]);

            logActivity("Workflow Transition: Submission #{$submissionId} moved from '{$currentStatus}' to '{$targetStatus}' by User #{$reviewerId} ({$actorRole}). Remarks: '{$remarks}'", $reviewerId);

            $pdo->commit();

            return [
                'success' => true,
                'submission_id' => $submissionId,
                'previous_status' => $currentStatus,
                'new_status' => $targetStatus,
                'reviewed_by' => $reviewerId,
                'reviewed_at' => $reviewedAt,
                'published_at' => $publishedAt
            ];

        } catch (Exception $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    

    public static function reopenSubmission($submissionId, $adminId, $targetStatus, $reason) {
        $pdo = getDBConnection();

        if (empty(trim($reason))) {
            throw new InvalidArgumentException("Reopen reason is mandatory.");
        }

        $stmtUser = $pdo->prepare("SELECT id, role, status FROM users WHERE id = ?");
        $stmtUser->execute([$adminId]);
        $actor = $stmtUser->fetch(PDO::FETCH_ASSOC);

        if (!$actor || strtolower(trim($actor['role'] ?? '')) !== 'admin' || ($actor['status'] ?? 'active') !== 'active') {
            throw new SecurityException("Unauthorized: Only active administrators may reopen submissions.");
        }

        $stmt = $pdo->prepare("SELECT * FROM exam_submissions WHERE id = ?");
        $stmt->execute([$submissionId]);
        $sub = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$sub) {
            throw new Exception("Submission #{$submissionId} not found.");
        }

        $currentStatus = $sub['review_status'] ?? 'pending_review';
        $targetStatus = strtolower(trim($targetStatus));
        $allowedReopenTargets = ['pending_review', 'reviewed', 'finalized'];

        if (!in_array($targetStatus, $allowedReopenTargets)) {
            throw new InvalidArgumentException("Invalid reopen target status '{$targetStatus}'. Allowed reopen targets: pending_review, reviewed, finalized.");
        }

        
        $stmtAns = $pdo->prepare("SELECT question_id, student_answer, correct_answer, awarded_points, max_points, evaluation_status, evaluation_reason FROM submission_answers WHERE submission_id = ?");
        $stmtAns->execute([$submissionId]);
        $itemAnswers = $stmtAns->fetchAll(PDO::FETCH_ASSOC);

        $pdo->beginTransaction();

        try {
            
            $stmtSnap = $pdo->prepare("
                INSERT INTO submission_snapshots (
                    submission_id, review_status, status, published_at, reviewed_at,
                    total_score, percentage, correct_count, wrong_count, ocr_text,
                    corrected_ocr_text, evaluation_result, item_answers, reopened_by, reason, created_at
                ) VALUES (
                    ?, ?, ?, ?, ?,
                    ?, ?, ?, ?, ?,
                    ?, ?, ?, ?, ?, NOW()
                )
            ");
            $stmtSnap->execute([
                $submissionId,
                $sub['review_status'],
                $sub['status'],
                $sub['published_at'],
                $sub['reviewed_at'],
                $sub['total_score'],
                $sub['percentage'],
                $sub['correct_count'],
                $sub['wrong_count'],
                $sub['ocr_text'],
                $sub['corrected_ocr_text'],
                $sub['evaluation_result'],
                json_encode($itemAnswers),
                $adminId,
                $reason
            ]);

            $stmtUpd = $pdo->prepare("
                UPDATE exam_submissions 
                SET review_status = ?, published_at = NULL, teacher_remarks = ?
                WHERE id = ?
            ");
            $stmtUpd->execute([$targetStatus, "Reopened by Admin #{$adminId}: {$reason}", $submissionId]);

            logActivity("Administrative Reopen: Submission #{$submissionId} reopened from '{$currentStatus}' to '{$targetStatus}' by Admin #{$adminId}. Reason: '{$reason}'", $adminId);

            $pdo->commit();

            return [
                'success' => true,
                'submission_id' => $submissionId,
                'previous_status' => $currentStatus,
                'new_status' => $targetStatus,
                'reopened_by' => $adminId,
                'reason' => $reason
            ];

        } catch (Exception $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    

    public static function overrideScore($submissionId, $questionId, $newPoints, $teacherId, $reason = '', $newAnswer = null) {
        $pdo = getDBConnection();

        if (empty(trim($reason))) {
            throw new InvalidArgumentException("Override reason is required.");
        }

        $newPoints = floatval($newPoints);
        if ($newPoints < 0) {
            throw new InvalidArgumentException("Awarded points cannot be negative.");
        }

        $stmtSub = $pdo->prepare("SELECT * FROM exam_submissions WHERE id = ?");
        $stmtSub->execute([$submissionId]);
        $sub = $stmtSub->fetch(PDO::FETCH_ASSOC);

        if (!$sub) {
            throw new Exception("Submission #{$submissionId} not found.");
        }

        if (in_array($sub['review_status'], ['finalized', 'published'])) {
            throw new LogicException("Cannot override scores on finalized or published results without an administrative reopen workflow.");
        }

        
        if (!AuthorizationService::canOverrideScore($teacherId, $submissionId)) {
            throw new SecurityException("Unauthorized: You do not have ownership permission to override scores for submission #{$submissionId}.");
        }

        
        $stmtAns = $pdo->prepare("SELECT * FROM submission_answers WHERE submission_id = ? AND question_id = ?");
        $stmtAns->execute([$submissionId, $questionId]);
        $ans = $stmtAns->fetch(PDO::FETCH_ASSOC);

        if (!$ans) {
            throw new Exception("Answer record for submission #{$submissionId}, question #{$questionId} not found.");
        }

        $maxPoints = floatval($ans['max_points'] ?? 1.0);
        if ($newPoints > $maxPoints) {
            throw new InvalidArgumentException("Awarded points ({$newPoints}) cannot exceed question maximum points ({$maxPoints}).");
        }

        $oldPoints = floatval($ans['awarded_points']);
        $oldAnswer = $ans['student_answer'];
        $updatedAnswer = ($newAnswer !== null) ? trim($newAnswer) : $oldAnswer;
        $evalStatus = ($newPoints > 0) ? 'correct' : 'incorrect';

        $pdo->beginTransaction();

        try {
            
            $oldCorrectAnswer = $ans['correct_answer'] ?? null;
            $newCorrectAnswer = $oldCorrectAnswer;
            $stmtLog = $pdo->prepare("
                INSERT INTO submission_score_overrides (
                    submission_id, question_id, old_student_answer, new_student_answer,
                    old_points, new_points, old_score, new_score,
                    old_correct_answer, new_correct_answer, reviewer_id, reason, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            $stmtLog->execute([
                $submissionId,
                $questionId,
                $oldAnswer,
                $updatedAnswer,
                $oldPoints,
                $newPoints,
                $sub['total_score'],
                $newPoints, 
                $oldCorrectAnswer,
                $newCorrectAnswer,
                $teacherId,
                $reason
            ]);

            
            $stmtUpdAns = $pdo->prepare("
                UPDATE submission_answers 
                SET awarded_points = ?, student_answer = ?, evaluation_status = ?, evaluation_reason = ?, requires_review = 0 
                WHERE submission_id = ? AND question_id = ?
            ");
            $stmtUpdAns->execute([$newPoints, $updatedAnswer, $evalStatus, 'Teacher score override: ' . $reason, $submissionId, $questionId]);

            
            $stmtSum = $pdo->prepare("
                SELECT SUM(awarded_points) as total_awarded, SUM(max_points) as total_possible,
                       SUM(CASE WHEN evaluation_status = 'correct' THEN 1 ELSE 0 END) as correct_cnt,
                       SUM(CASE WHEN evaluation_status = 'incorrect' OR evaluation_status = 'unanswered' THEN 1 ELSE 0 END) as wrong_cnt
                FROM submission_answers WHERE submission_id = ?
            ");
            $stmtSum->execute([$submissionId]);
            $tot = $stmtSum->fetch(PDO::FETCH_ASSOC);

            $newTotalAwarded = floatval($tot['total_awarded']);
            $newTotalPossible = floatval($tot['total_possible']) ?: 1.0;
            $newPercentage = round(($newTotalAwarded / $newTotalPossible) * 100, 2);

            $stmtExam = $pdo->prepare("SELECT passing_percentage, exam_category, qualifying_passing_percentage FROM exams WHERE id = ?");
            $stmtExam->execute([$sub['exam_id']]);
            $examInfo = $stmtExam->fetch(PDO::FETCH_ASSOC);
            $passPct = floatval($examInfo['passing_percentage'] ?? 75.00);

            $newStatus = ($newPercentage >= $passPct) ? 'Pass' : 'Fail';

            $newQualStatus = 'pending';
            if (($examInfo['exam_category'] ?? 'regular') === 'qualifying') {
                $qualPct = floatval($examInfo['qualifying_passing_percentage'] ?? 75.00);
                $newQualStatus = ($newPercentage >= $qualPct) ? 'qualified' : 'not_qualified';
            }

            $stmtUpdSub = $pdo->prepare("
                UPDATE exam_submissions 
                SET total_score = ?, percentage = ?, status = ?, qualification_status = ?, correct_count = ?, wrong_count = ? 
                WHERE id = ?
            ");
            $stmtUpdSub->execute([$newTotalAwarded, $newPercentage, $newStatus, $newQualStatus, intval($tot['correct_cnt']), intval($tot['wrong_cnt']), $submissionId]);

            $pdo->commit();

            logActivity("Teacher #{$teacherId} overridden score for submission #{$submissionId}, Q#{$questionId}: {$oldPoints} -> {$newPoints}", $teacherId);

            return [
                'success' => true,
                'submission_id' => $submissionId,
                'question_id' => $questionId,
                'old_points' => $oldPoints,
                'new_points' => $newPoints,
                'recalculated_total_score' => $newTotalAwarded,
                'recalculated_percentage' => $newPercentage,
                'recalculated_status' => $newStatus
            ];

        } catch (Exception $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    

    public static function reprocessOcr($submissionId, $actorId, $reason = 'OCR Re-run') {
        $pdo = getDBConnection();

        if (empty(trim($reason))) {
            throw new InvalidArgumentException("Reprocessing reason is required.");
        }

        
        $stmtSub = $pdo->prepare("SELECT * FROM exam_submissions WHERE id = ?");
        $stmtSub->execute([$submissionId]);
        $sub = $stmtSub->fetch(PDO::FETCH_ASSOC);

        if (!$sub) {
            throw new Exception("Submission #{$submissionId} not found.");
        }

        
        if (!AuthorizationService::canReviewSubmission($actorId, $submissionId)) {
            throw new SecurityException("Unauthorized: User #{$actorId} is not authorized to reprocess submission #{$submissionId}.");
        }

        
        if (in_array($sub['review_status'], ['finalized', 'published', 'archived'])) {
            throw new LogicException("Cannot re-run OCR on finalized, published, or archived results without an administrative reopen workflow.");
        }

        $examId = intval($sub['exam_id']);
        $studentId = intval($sub['student_id']);

        
        $stmtQ = $pdo->prepare("SELECT * FROM exam_questions WHERE exam_id = ? ORDER BY id ASC");
        $stmtQ->execute([$examId]);
        $questions = $stmtQ->fetchAll(PDO::FETCH_ASSOC);

        if (empty($questions)) {
            throw new Exception("Exam #{$examId} has no questions configured for scoring.");
        }

        
        $filePath = $sub['file_path'] ?? null;
        $ocrText = $sub['ocr_text'] ?? '';
        $submittedAnswers = [];

        if (!empty($filePath)) {
            $baseDir = defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__, 2);
            $absPath = (strpos($filePath, '/') === 0) ? $filePath : $baseDir . '/' . ltrim($filePath, '/');
            if (!file_exists($absPath)) {
                throw new Exception("Original answer sheet file not found at '{$filePath}'. Cannot re-run OCR without original file.");
            }
            if (file_exists(__DIR__ . '/OcrService.php')) {
                require_once __DIR__ . '/OcrService.php';
                if (method_exists('OcrService', 'processAnswerSheet')) {
                    $ocrResult = OcrService::processAnswerSheet($absPath, count($questions));
                    if (isset($ocrResult['success']) && !empty($ocrResult['raw_text'])) {
                        $ocrText = $ocrResult['raw_text'];
                        if (isset($ocrResult['answers']) && is_array($ocrResult['answers'])) {
                            $qIdx = 0;
                            foreach ($questions as $q) {
                                $qId = $q['id'];
                                if (isset($ocrResult['answers'][$qIdx + 1])) {
                                    $submittedAnswers[$qId] = $ocrResult['answers'][$qIdx + 1];
                                }
                                $qIdx++;
                            }
                        }
                    }
                }
            }
        }

        
        $stmtOldAns = $pdo->prepare("SELECT question_id, student_answer, awarded_points, max_points, evaluation_status FROM submission_answers WHERE submission_id = ?");
        $stmtOldAns->execute([$submissionId]);
        $previousItemScoresList = $stmtOldAns->fetchAll(PDO::FETCH_ASSOC);

        foreach ($previousItemScoresList as $oldAns) {
            if (!isset($submittedAnswers[$oldAns['question_id']])) {
                $submittedAnswers[$oldAns['question_id']] = $oldAns['student_answer'];
            }
        }

        
        if (!empty($ocrText)) {
            if (file_exists(__DIR__ . '/AnswerSheetParser.php')) {
                require_once __DIR__ . '/AnswerSheetParser.php';
                if (method_exists('AnswerSheetParser', 'extractAnswersFromText')) {
                    $parsedFromText = AnswerSheetParser::extractAnswersFromText($ocrText, count($questions));
                    $qIdx = 0;
                    foreach ($questions as $q) {
                        $qId = $q['id'];
                        if (!isset($submittedAnswers[$qId]) || $submittedAnswers[$qId] === '') {
                            if (isset($parsedFromText[$qIdx + 1])) {
                                $submittedAnswers[$qId] = $parsedFromText[$qIdx + 1];
                            }
                        }
                        $qIdx++;
                    }
                }
            }
        }

        
        require_once __DIR__ . '/ExamScoringService.php';

        $totalAwardedPoints = 0.00;
        $totalPossiblePoints = 0.00;
        $correctCount = 0;
        $wrongCount = 0;
        $reviewRequiredCount = 0;
        $newItemScores = [];

        foreach ($questions as $q) {
            $qId = $q['id'];
            $maxPoints = floatval($q['points'] ?? 1.00);
            $totalPossiblePoints += $maxPoints;

            $studentAnswerRaw = $submittedAnswers[$qId] ?? null;
            $itemEval = ExamScoringService::evaluateSingleAnswer($q, $studentAnswerRaw);

            if ($itemEval['evaluation_status'] === 'correct') {
                $correctCount++;
            } elseif ($itemEval['requires_review']) {
                $reviewRequiredCount++;
            } else {
                $wrongCount++;
            }

            $totalAwardedPoints += $itemEval['awarded_points'];
            $newItemScores[] = $itemEval;
        }

        $stmtExam = $pdo->prepare("SELECT passing_percentage FROM exams WHERE id = ?");
        $stmtExam->execute([$examId]);
        $passPct = floatval($stmtExam->fetchColumn() ?: 75.00);

        $newPercentage = ($totalPossiblePoints > 0) ? round(($totalAwardedPoints / $totalPossiblePoints) * 100, 2) : 0.00;
        $newPassOrFail = ($newPercentage >= $passPct) ? 'Pass' : 'Fail';

        $pdo->beginTransaction();

        try {
            
            $stmtHist = $pdo->prepare("
                INSERT INTO submission_reprocessing_history (
                    submission_id, previous_ocr_text, new_ocr_text, previous_item_scores,
                    new_item_scores, previous_total, new_total, actor_id, reason, created_at
                ) VALUES (
                    ?, ?, ?, ?,
                    ?, ?, ?, ?, ?, NOW()
                )
            ");
            $stmtHist->execute([
                $submissionId,
                $sub['ocr_text'],
                $ocrText,
                json_encode($previousItemScoresList),
                json_encode($newItemScores),
                $sub['total_score'],
                $totalAwardedPoints,
                $actorId,
                $reason
            ]);

            
            $stmtAnswer = $pdo->prepare("
                INSERT INTO submission_answers (
                    submission_id, exam_id, student_id, question_id, student_answer,
                    correct_answer, awarded_points, max_points, evaluation_status, evaluation_reason,
                    confidence, requires_review, created_at
                ) VALUES (
                    ?, ?, ?, ?, ?,
                    ?, ?, ?, ?, ?,
                    ?, ?, NOW()
                )
                ON DUPLICATE KEY UPDATE
                    student_answer = VALUES(student_answer),
                    correct_answer = VALUES(correct_answer),
                    awarded_points = VALUES(awarded_points),
                    max_points = VALUES(max_points),
                    confidence = VALUES(confidence),
                    requires_review = VALUES(requires_review),
                    evaluation_status = VALUES(evaluation_status),
                    evaluation_reason = VALUES(evaluation_reason)
            ");

            foreach ($newItemScores as $item) {
                $stmtAnswer->execute([
                    $submissionId,
                    $examId,
                    $studentId,
                    $item['question_id'],
                    $item['student_answer'],
                    $item['stored_correct_answer'],
                    $item['awarded_points'],
                    $item['maximum_points'],
                    $item['evaluation_status'],
                    $item['evaluation_reason'],
                    $item['confidence'],
                    $item['requires_review'] ? 1 : 0
                ]);
            }

            
            $stmtUpdSub = $pdo->prepare("
                UPDATE exam_submissions 
                SET correct_count = ?, wrong_count = ?, total_score = ?, total_possible_score = ?,
                    percentage = ?, status = ?, evaluation_result = ?
                WHERE id = ?
            ");
            $stmtUpdSub->execute([
                $correctCount,
                $wrongCount,
                $totalAwardedPoints,
                $totalPossiblePoints,
                $newPercentage,
                $newPassOrFail,
                json_encode($newItemScores),
                $submissionId
            ]);

            $pdo->commit();

            logActivity("OCR Reprocessed for Submission #{$submissionId} by User #{$actorId}. Score: {$sub['total_score']} -> {$totalAwardedPoints} ({$newPercentage}%)", $actorId);

            return [
                'success' => true,
                'submission_id' => $submissionId,
                'previous_total' => floatval($sub['total_score']),
                'new_total' => $totalAwardedPoints,
                'total_possible' => $totalPossiblePoints,
                'percentage' => $newPercentage,
                'status' => $newPassOrFail,
                'correct_count' => $correctCount,
                'wrong_count' => $wrongCount,
                'item_scores' => $newItemScores
            ];

        } catch (Exception $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    

    public static function enforceStudentPrivacy($submissionId, $currentStudentId) {
        $pdo = getDBConnection();

        $stmt = $pdo->prepare("SELECT * FROM exam_submissions WHERE id = ?");
        $stmt->execute([$submissionId]);
        $sub = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$sub) {
            return ['allowed' => false, 'error' => 'Submission record not found.'];
        }

        if (intval($sub['student_id']) !== intval($currentStudentId)) {
            return ['allowed' => false, 'error' => 'Unauthorized: Cannot access another student\'s exam result.'];
        }

        if ($sub['review_status'] !== 'published') {
            return ['allowed' => false, 'error' => 'Exam result is pending teacher review and is not yet available.'];
        }

        return ['allowed' => true, 'submission' => $sub];
    }

    public static function bulkPublishSubmissions(array $submissionIds, $teacherId, $remarks = '') {
        $publishedCount = 0;
        $errors = [];
        foreach ($submissionIds as $sId) {
            try {
                self::transitionStatus($sId, 'published', $teacherId, $remarks);
                $publishedCount++;
            } catch (Exception $e) {
                $errors[] = "Submission #{$sId}: " . $e->getMessage();
            }
        }
        return [
            'success' => true,
            'published_count' => $publishedCount,
            'errors' => $errors
        ];
    }

    public static function publishEntireExam($examId, $teacherId, $remarks = '') {
        $pdo = getDBConnection();
        $stmtEx = $pdo->prepare("SELECT teacher_id FROM exams WHERE id = ?");
        $stmtEx->execute([$examId]);
        $exTeacher = $stmtEx->fetchColumn();

        if (!$exTeacher) {
            throw new Exception("Exam #{$examId} not found.");
        }

        $stmtUser = $pdo->prepare("SELECT role FROM users WHERE id = ?");
        $stmtUser->execute([$teacherId]);
        $role = strtolower(trim($stmtUser->fetchColumn() ?: ''));

        if ($role !== 'admin' && intval($exTeacher) !== intval($teacherId)) {
            throw new SecurityException("Unauthorized: Cannot publish results for exam #{$examId} owned by another teacher.");
        }

        $stmtSubs = $pdo->prepare("
            SELECT id FROM exam_submissions 
            WHERE exam_id = ? AND review_status IN ('reviewed', 'finalized', 'draft', 'pending_review')
        ");
        $stmtSubs->execute([$examId]);
        $subIds = $stmtSubs->fetchAll(PDO::FETCH_COLUMN);

        $publishedCount = 0;
        $errors = [];

        foreach ($subIds as $sId) {
            try {
                $stmtCheck = $pdo->prepare("SELECT review_status FROM exam_submissions WHERE id = ?");
                $stmtCheck->execute([$sId]);
                $cStatus = $stmtCheck->fetchColumn();

                if (in_array($cStatus, ['draft', 'pending_review'], true)) {
                    $pdo->exec("UPDATE exam_submissions SET review_status = 'finalized' WHERE id = {$sId}");
                }
                self::transitionStatus($sId, 'published', $teacherId, $remarks);
                $publishedCount++;
            } catch (Exception $e) {
                $errors[] = "Submission #{$sId}: " . $e->getMessage();
            }
        }

        return [
            'success' => true,
            'exam_id' => $examId,
            'published_count' => $publishedCount,
            'errors' => $errors
        ];
    }
}

