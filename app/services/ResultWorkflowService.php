<?php

require_once __DIR__ . '/../database.php';
require_once __DIR__ . '/../../includes/security.php';

class ResultWorkflowService {

    // Normal forward workflow transitions for teachers
    const ALLOWED_TRANSITIONS = [
        'pending_review' => ['reviewed'],
        'reviewed'       => ['finalized'],
        'finalized'      => ['published'],
        'published'      => ['archived'],
        'archived'       => []
    ];

    /**
     * Validate and transition submission review status server-side.
     * Role authorization is strictly retrieved from the database using $reviewerId.
     */
    public static function transitionStatus($submissionId, $targetStatus, $reviewerId, $remarks = '') {
        $pdo = getDBConnection();

        // Load actor from database to prevent role parameter spoofing
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

        $currentStatus = $sub['review_status'] ?? 'pending_review';
        $targetStatus = strtolower(trim($targetStatus));

        // Ordinary teachers are strictly prohibited from backward transitions
        $backwardTransitions = ['reviewed' => 'pending_review', 'finalized' => 'reviewed', 'published' => 'finalized', 'archived' => 'published'];
        if (isset($backwardTransitions[$currentStatus]) && $backwardTransitions[$currentStatus] === $targetStatus) {
            throw new SecurityException("Unauthorized: Teachers cannot perform backward transitions or reopen results. Use the administrative reopen workflow instead.");
        }

        // Check if transition is valid in the forward workflow map
        if (!isset(self::ALLOWED_TRANSITIONS[$currentStatus]) || !in_array($targetStatus, self::ALLOWED_TRANSITIONS[$currentStatus])) {
            throw new InvalidArgumentException("Illegal status transition from '{$currentStatus}' to '{$targetStatus}'. Skipped or backward transitions are strictly rejected.");
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
     * Separate explicit Administrative Reopen Workflow for backward transitions.
     * Requires DB-verified admin user and mandatory reason.
     */
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

        $pdo->beginTransaction();

        try {
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

    /**
     * Override item score or answer by teacher with full audit logging, validation, and total score recalculation
     */
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

        // Authorization check: Verify ownership via AuthorizationService
        if (!AuthorizationService::canOverrideScore($teacherId, $submissionId)) {
            throw new SecurityException("Unauthorized: You do not have ownership permission to override scores for submission #{$submissionId}.");
        }

        // Fetch original answer record
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

        if ($sub['review_status'] !== 'published') {
            return ['allowed' => false, 'error' => 'Exam result is pending teacher review and is not yet available.'];
        }

        return ['allowed' => true, 'submission' => $sub];
    }
}
