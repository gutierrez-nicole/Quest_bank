<?php

require_once __DIR__ . '/../database.php';

class StudentService {

    public static function getEnrolledStudents($teacherId, $searchQuery = '') {
        $pdo = getDBConnection();
        if (!empty($searchQuery)) {
            $stmt = $pdo->prepare("
                SELECT s.*, sec.section_name, sec.course_name 
                FROM students s 
                JOIN sections sec ON s.section_id = sec.id 
                WHERE s.teacher_id = ? AND (s.student_number LIKE ? OR s.fullname LIKE ?)
                ORDER BY s.id DESC
            ");
            $stmt->execute([$teacherId, "%{$searchQuery}%", "%{$searchQuery}%"]);
        } else {
            $stmt = $pdo->prepare("
                SELECT s.*, sec.section_name, sec.course_name 
                FROM students s 
                JOIN sections sec ON s.section_id = sec.id 
                WHERE s.teacher_id = ? 
                ORDER BY s.id DESC
            ");
            $stmt->execute([$teacherId]);
        }
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function getAtRiskStudents($teacherId) {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("
            SELECT student_name, exam_title, percentage, correct_count, total_items, created_at 
            FROM exam_submissions 
            WHERE teacher_id = ? AND percentage < 75 
            ORDER BY id DESC
        ");
        $stmt->execute([$teacherId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function getPendingJoinRequests($teacherId) {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("SELECT * FROM student_requests WHERE teacher_id = ? AND status = 'pending' ORDER BY id DESC");
        $stmt->execute([$teacherId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
