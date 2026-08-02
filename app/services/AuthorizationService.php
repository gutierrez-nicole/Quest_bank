<?php

require_once __DIR__ . '/../database.php';
require_once __DIR__ . '/../../includes/security.php';

class AuthorizationService {

    /**
     * Check if a teacher/admin can manage an exam
     */
    public static function canManageExam($userId, $examId) {
        $pdo = getDBConnection();

        $userStmt = $pdo->prepare("SELECT role FROM users WHERE id = ?");
        $userStmt->execute([$userId]);
        $role = $userStmt->fetchColumn();

        if ($role === 'admin') return true;
        if ($role !== 'teacher') return false;

        $stmt = $pdo->prepare("SELECT id FROM exams WHERE id = ? AND (teacher_id = ? OR created_by = ?)");
        $stmt->execute([$examId, $userId, $userId]);
        return ($stmt->fetchColumn() !== false);
    }

    /**
     * Check if a teacher/admin can review or update a submission
     */
    public static function canReviewSubmission($userId, $submissionId) {
        $pdo = getDBConnection();

        $userStmt = $pdo->prepare("SELECT role FROM users WHERE id = ?");
        $userStmt->execute([$userId]);
        $role = $userStmt->fetchColumn();

        if ($role === 'admin') return true;
        if ($role !== 'teacher') return false;

        $stmt = $pdo->prepare("
            SELECT s.id 
            FROM exam_submissions s
            LEFT JOIN exams e ON s.exam_id = e.id
            WHERE s.id = ? AND (s.teacher_id = ? OR e.teacher_id = ? OR e.created_by = ?)
        ");
        $stmt->execute([$submissionId, $userId, $userId, $userId]);
        return ($stmt->fetchColumn() !== false);
    }

    /**
     * Check if a teacher/admin can override a score
     */
    public static function canOverrideScore($userId, $submissionId) {
        return self::canReviewSubmission($userId, $submissionId);
    }

    /**
     * Check if a teacher/admin can publish a submission result
     */
    public static function canPublishSubmission($userId, $submissionId) {
        return self::canReviewSubmission($userId, $submissionId);
    }

    /**
     * Check if a user (teacher/admin/student) can view a submission
     */
    public static function canViewSubmission($userId, $submissionId) {
        $pdo = getDBConnection();

        $userStmt = $pdo->prepare("SELECT role FROM users WHERE id = ?");
        $userStmt->execute([$userId]);
        $role = $userStmt->fetchColumn();

        if ($role === 'admin') return true;

        if ($role === 'teacher') {
            return self::canReviewSubmission($userId, $submissionId);
        }

        if ($role === 'student') {
            $stmt = $pdo->prepare("SELECT id FROM exam_submissions WHERE id = ? AND student_id = ? AND review_status = 'published'");
            $stmt->execute([$submissionId, $userId]);
            return ($stmt->fetchColumn() !== false);
        }

        return false;
    }

    /**
     * Check if a teacher/admin can view or manage a student record
     */
    public static function canViewStudentRecord($userId, $studentId) {
        $pdo = getDBConnection();

        $userStmt = $pdo->prepare("SELECT role FROM users WHERE id = ?");
        $userStmt->execute([$userId]);
        $role = $userStmt->fetchColumn();

        if ($role === 'admin') return true;
        if ($role !== 'teacher') return false;

        // Check if student exists and is enrolled or has submitted to any exam owned by this teacher
        $stmt = $pdo->prepare("
            SELECT s.id 
            FROM exam_submissions s
            WHERE s.student_id = ? AND s.teacher_id = ?
            LIMIT 1
        ");
        $stmt->execute([$studentId, $userId]);
        $hasSubmission = ($stmt->fetchColumn() !== false);

        if ($hasSubmission) return true;

        // Or student details exist in DB and user is a registered teacher
        $studentStmt = $pdo->prepare("SELECT id FROM users WHERE id = ? AND role = 'student'");
        $studentStmt->execute([$studentId]);
        return ($studentStmt->fetchColumn() !== false);
    }

    /**
     * Enforce access control or terminate with HTTP 403 Forbidden
     */
    public static function enforceSubmissionAccess($userId, $submissionId) {
        if (!self::canReviewSubmission($userId, $submissionId)) {
            http_response_code(403);
            die("<div style='font-family:sans-serif;text-align:center;padding:50px;'><h2>403 Forbidden - Access Denied</h2><p>Unauthorized: You do not have permission to access or modify this examination record.</p></div>");
        }
    }

    /**
     * Enforce exam ownership or terminate with HTTP 403 Forbidden
     */
    public static function enforceExamAccess($userId, $examId) {
        if (!self::canManageExam($userId, $examId)) {
            http_response_code(403);
            die("<div style='font-family:sans-serif;text-align:center;padding:50px;'><h2>403 Forbidden - Access Denied</h2><p>Unauthorized: You do not have permission to manage this exam paper.</p></div>");
        }
    }
}
