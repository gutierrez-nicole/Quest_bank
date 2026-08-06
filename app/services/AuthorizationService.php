<?php

require_once __DIR__ . '/../database.php';
require_once __DIR__ . '/../../includes/security.php';

class AuthorizationService {

    

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

    

    public static function canOverrideScore($userId, $submissionId) {
        return self::canReviewSubmission($userId, $submissionId);
    }

    

    public static function canPublishSubmission($userId, $submissionId) {
        $pdo = getDBConnection();

        $userStmt = $pdo->prepare("SELECT role, status FROM users WHERE id = ?");
        $userStmt->execute([$userId]);
        $user = $userStmt->fetch(PDO::FETCH_ASSOC);

        if (!$user || ($user['status'] ?? 'active') !== 'active') {
            return false;
        }

        $role = strtolower(trim($user['role'] ?? ''));
        if ($role === 'admin') {
            return true;
        }
        if ($role !== 'teacher') {
            return false;
        }

        $stmt = $pdo->prepare("
            SELECT s.id 
            FROM exam_submissions s
            LEFT JOIN exams e ON s.exam_id = e.id
            WHERE s.id = ? 
              AND (s.teacher_id = ? OR e.teacher_id = ? OR e.created_by = ?)
              AND (e.id IS NULL OR e.status != 'archived')
        ");
        $stmt->execute([$submissionId, $userId, $userId, $userId]);
        return ($stmt->fetchColumn() !== false);
    }

    

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

    

    public static function canViewStudentRecord($userId, $studentId) {
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
            WHERE s.student_id = ? AND (s.teacher_id = ? OR e.teacher_id = ? OR e.created_by = ?)
            LIMIT 1
        ");
        $stmt->execute([$studentId, $userId, $userId, $userId]);
        $hasSubmission = ($stmt->fetchColumn() !== false);

        if ($hasSubmission) return true;

        
        $assignStmt = $pdo->prepare("
            SELECT ea.id
            FROM exam_assignments ea
            JOIN exams e ON ea.exam_id = e.id
            WHERE ea.student_id = ? AND (e.teacher_id = ? OR e.created_by = ?)
            LIMIT 1
        ");
        $assignStmt->execute([$studentId, $userId, $userId]);
        return ($assignStmt->fetchColumn() !== false);
    }

    

    public static function canDownloadSubmission($userId, $submissionId) {
        return self::canReviewSubmission($userId, $submissionId);
    }

    

    public static function enforceSubmissionAccess($userId, $submissionId) {
        if (!self::canReviewSubmission($userId, $submissionId)) {
            http_response_code(403);
            die("<div style='font-family:sans-serif;text-align:center;padding:50px;'><h2>403 Forbidden - Access Denied</h2><p>Unauthorized: You do not have permission to access or modify this examination record.</p></div>");
        }
    }

    

    public static function enforceExamAccess($userId, $examId) {
        if (!self::canManageExam($userId, $examId)) {
            http_response_code(403);
            die("<div style='font-family:sans-serif;text-align:center;padding:50px;'><h2>403 Forbidden - Access Denied</h2><p>Unauthorized: You do not have permission to manage this exam paper.</p></div>");
        }
    }
}
