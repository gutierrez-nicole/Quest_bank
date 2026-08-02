<?php

require_once __DIR__ . '/../database.php';
require_once __DIR__ . '/../../includes/security.php';

class ResultWorkflowService {

    const ALLOWED_TRANSITIONS = [
        'pending_review' => ['reviewed'],
        'reviewed'       => ['finalized', 'pending_review'],
        'finalized'      => ['published', 'reviewed'],
        'published'      => ['archived', 'finalized'],
        'archived'       => ['published']
    ];

    /**
     * Validate and transition submission review status server-side
     */
    public static function transitionStatus($submissionId, $targetStatus, $reviewerId, $remarks = '', $actorRole = 'teacher') {
        $pdo = getDBConnection();

        $stmt = $pdo->prepare("SELECT * FROM exam_submissions WHERE id = ?");
        $stmt->execute([$submissionId]);
        $sub = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$sub) {
            throw new Exception("Submission #{$submissionId} not found.");
        }

        $currentStatus = $sub['review_status'] ?? 'pending_review';
        $targetStatus = strtolower(trim($targetStatus));

        // Backward transition check: Only administrators may reopen results
        $backwardTransitions = ['reviewed' => 'pending_review', 'finalized' => 'reviewed', 'published' => 'finalized'];
        if (isset($backwardTransitions[$currentStatus]) && $backwardTransitions[$currentStatus] === $targetStatus) {
            if ($actorRole !== 'admin') {
                throw new SecurityException("Unauthorized: Only administrators may reopen or perform backward transitions on finalized or published results.");
            }
        }

        // Check if transition is valid
        if (!isset(self::ALLOWED_TRANSITIONS[$currentStatus]) || !in_array($targetStatus, self::ALLOWED_TRANSITIONS[$currentStatus])) {
            throw new InvalidArgumentException("Illegal status transition from '{$currentStatus}' to '{$targetStatus}'. Direct skipped transitions are strictly rejected.");
        }

        // Validate finalization blockers
        if ($targetStatus === 'finalized') {
            $ocrConf = floatval($sub['ocr_confidence'] ?? 100.00);
            $manualRev = intval($sub['suggested_manual_review'] ?? 0);

            // Check item-level review flags in submission_answers
            $stmtAns = $pdo->prepare("SELECT COUNT(*) FROM submission_answers WHERE submission_id = ? AND requires_review = 1");
            $stmtAns->execute([$submissionId]);
            $unresolvedItems = intval($stmtAns->fetchColumn());

            if ($ocrConf < 75.00 || $manualRev === 1 || $unresolvedItems > 0) {
                throw new LogicException("Cannot finalize submission: Contains unresolved low-confidence OCR flags or item review requirements.");
            }
        }

        // Validate publication requirements
        if ($targetStatus === 'published') {
            if ($currentStatus !== 'finalized' && $currentStatus !== 'archived') {
                throw new LogicException("Publication rejected: Submission must be in 'finalized' status before publishing.");
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

            // Audit status transition in activity_logs or dedicated table
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

    /**
     * Override item score or answer by teacher with full audit logging and total score recalculation
     */
    public static function overrideScore($submissionId, $questionId, $newPoints, $teacherId, $reason = '', $newAnswer = null) {
        $pdo = getDBConnection();

        $stmtSub = $pdo->prepare("SELECT * FROM exam_submissions WHERE id = ?");
        $stmtSub->execute([$submissionId]);
        $sub = $stmtSub->fetch(PDO::FETCH_ASSOC);

        if (!$sub) {
            throw new Exception("Submission #{$submissionId} not found.");
        }

        // Authorization check: Verify teacher is authorized
        $teacherStmt = $pdo->prepare("SELECT role FROM users WHERE id = ?");
        $teacherStmt->execute([$teacherId]);
        $teacherRole = $teacherStmt->fetchColumn();

        if ($teacherRole !== 'teacher' && $teacherRole !== 'admin') {
            throw new SecurityException("Unauthorized: Only faculty teachers or administrators may override scores.");
        }

        // Fetch original answer record
        $stmtAns = $pdo->prepare("SELECT * FROM submission_answers WHERE submission_id = ? AND question_id = ?");
        $stmtAns->execute([$submissionId, $questionId]);
        $ans = $stmtAns->fetch(PDO::FETCH_ASSOC);

        if (!$ans) {
            throw new Exception("Answer record for submission #{$submissionId}, question #{$questionId} not found.");
        }

        $oldPoints = floatval($ans['awarded_points']);
        $newPoints = floatval($newPoints);
        $oldAnswer = $ans['student_answer'];
        $updatedAnswer = ($newAnswer !== null) ? trim($newAnswer) : $oldAnswer;
        $evalStatus = ($newPoints > 0) ? 'correct' : 'incorrect';

        $pdo->beginTransaction();

        try {
            // Log override in submission_score_overrides
            $stmtLog = $pdo->prepare("
                INSERT INTO submission_score_overrides (
                    submission_id, old_score, new_score, reviewer_id, reason, created_at
                ) VALUES (?, ?, ?, ?, ?, NOW())
            ");
            $stmtLog->execute([$submissionId, $oldPoints, $newPoints, $teacherId, $reason]);

            // Update item answer row
            $stmtUpdAns = $pdo->prepare("
                UPDATE submission_answers 
                SET awarded_points = ?, student_answer = ?, evaluation_status = ?, evaluation_reason = ?, requires_review = 0 
                WHERE submission_id = ? AND question_id = ?
            ");
            $stmtUpdAns->execute([$newPoints, $updatedAnswer, $evalStatus, 'Teacher score override: ' . $reason, $submissionId, $questionId]);

            // Recalculate total submission score server-side
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

            $stmtExam = $pdo->prepare("SELECT passing_percentage FROM exams WHERE id = ?");
            $stmtExam->execute([$sub['exam_id']]);
            $passPct = floatval($stmtExam->fetchColumn() ?: 75.00);

            $newStatus = ($newPercentage >= $passPct) ? 'Pass' : 'Fail';

            $stmtUpdSub = $pdo->prepare("
                UPDATE exam_submissions 
                SET total_score = ?, percentage = ?, status = ?, correct_count = ?, wrong_count = ? 
                WHERE id = ?
            ");
            $stmtUpdSub->execute([$newTotalAwarded, $newPercentage, $newStatus, intval($tot['correct_cnt']), intval($tot['wrong_cnt']), $submissionId]);

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

    /**
     * Enforce student privacy check: Ensures current user is authorized to view submission
     */
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

        if (!in_array($sub['review_status'], ['published', 'finalized'])) {
            return ['allowed' => false, 'error' => 'Exam result is pending teacher review and is not yet available.'];
        }

        return ['allowed' => true, 'submission' => $sub];
    }
}
