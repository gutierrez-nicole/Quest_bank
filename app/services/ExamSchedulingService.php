<?php

require_once __DIR__ . '/../database.php';
require_once __DIR__ . '/../../includes/security.php';
require_once __DIR__ . '/AcademicStructureService.php';
require_once __DIR__ . '/NotificationService.php';

class ExamSchedulingService {

    public static function createSchedule($examId, $actorId, $section, $examDate, $startTime, $endTime, $room = '', $remarks = '') {
        $section = strtoupper(trim($section));
        $room = trim($room);
        $remarks = trim($remarks);

        if (empty($examId) || empty($actorId) || empty($section) || empty($examDate) || empty($startTime) || empty($endTime)) {
            throw new InvalidArgumentException("Exam ID, actor ID, section, exam date, start time, and end time are required.");
        }

        if (strtotime($endTime) <= strtotime($startTime)) {
            throw new InvalidArgumentException("End time must be later than start time.");
        }

        $activeSem = AcademicStructureService::getActiveSemester();
        if (!$activeSem) {
            throw new LogicException("Cannot schedule exam: No active semester configured.");
        }

        $activeSy = AcademicStructureService::getActiveSchoolYear();
        if (!$activeSy) {
            throw new LogicException("Cannot schedule exam: No active school year configured.");
        }

        // Enforce alignment between active semester and active school year
        if (intval($activeSem['school_year_id']) !== intval($activeSy['id'])) {
            throw new LogicException("Academic Configuration Misalignment: Active semester #{$activeSem['id']} belongs to school year #{$activeSem['school_year_id']}, but active school year is #{$activeSy['id']}.");
        }

        if (!empty($activeSy['start_date']) && !empty($activeSy['end_date'])) {
            if ($examDate < $activeSy['start_date'] || $examDate > $activeSy['end_date']) {
                throw new InvalidArgumentException("Exam date '{$examDate}' falls outside the active school year date range ({$activeSy['start_date']} to {$activeSy['end_date']}).");
            }
        }

        $pdo = getDBConnection();

        // 1. Verify exam existence & derive authoritative exam owner
        $stmtEx = $pdo->prepare("SELECT id, title, teacher_id FROM exams WHERE id = ?");
        $stmtEx->execute([$examId]);
        $exam = $stmtEx->fetch(PDO::FETCH_ASSOC);

        if (!$exam) {
            throw new Exception("Exam #{$examId} not found.");
        }

        $examOwnerId = intval($exam['teacher_id']);

        $userStmt = $pdo->prepare("SELECT role, status FROM users WHERE id = ?");
        $userStmt->execute([$actorId]);
        $user = $userStmt->fetch(PDO::FETCH_ASSOC);

        if (!$user || $user['status'] !== 'active') {
            throw new SecurityException("Scheduling actor must be an active user.");
        }

        $role = $user['role'];

        // If actor is a teacher, they can schedule ONLY their own exam
        if ($role !== 'admin' && $examOwnerId !== intval($actorId)) {
            throw new SecurityException("Unauthorized: Cannot schedule an exam owned by another teacher.");
        }

        // 2. Verify section assignment for teacher actor
        if ($role === 'teacher') {
            $syId = $activeSy ? $activeSy['id'] : 0;
            $stmtAsgn = $pdo->prepare("
                SELECT tsa.id 
                FROM teacher_subject_assignments tsa
                JOIN sections s ON tsa.section_id = s.id
                WHERE tsa.teacher_id = ? 
                  AND (s.section_code = ? OR s.section_name = ?)
                  AND tsa.school_year_id = ? 
                  AND tsa.status = 'active'
            ");
            $stmtAsgn->execute([$actorId, $section, $section, $syId]);
            if (!$stmtAsgn->fetchColumn()) {
                throw new SecurityException("Teacher is not assigned to Section '{$section}' for the active school year.");
            }
        }

        // 3. Check overlapping schedule for the exam owner teacher on same date
        $stmtCheckTeacher = $pdo->prepare("
            SELECT id, exam_date, start_time, end_time, section 
            FROM exam_schedules
            WHERE teacher_id = ? AND exam_date = ? AND status != 'cancelled'
              AND ((start_time < ? AND end_time > ?) OR (start_time < ? AND end_time > ?) OR (start_time >= ? AND end_time <= ?))
        ");
        $stmtCheckTeacher->execute([$examOwnerId, $examDate, $endTime, $startTime, $endTime, $startTime, $startTime, $endTime]);
        $teacherConflict = $stmtCheckTeacher->fetch(PDO::FETCH_ASSOC);

        if ($teacherConflict) {
            throw new LogicException("Teacher scheduling conflict: Exam owner already has an exam scheduled for Section '{$teacherConflict['section']}' on {$examDate} between {$teacherConflict['start_time']} and {$teacherConflict['end_time']}.");
        }

        // 4. Check overlapping schedule for same section on same date
        $stmtCheckSection = $pdo->prepare("
            SELECT id, exam_date, start_time, end_time, teacher_id 
            FROM exam_schedules
            WHERE section = ? AND exam_date = ? AND status != 'cancelled'
              AND ((start_time < ? AND end_time > ?) OR (start_time < ? AND end_time > ?) OR (start_time >= ? AND end_time <= ?))
        ");
        $stmtCheckSection->execute([$section, $examDate, $endTime, $startTime, $endTime, $startTime, $startTime, $endTime]);
        $sectionConflict = $stmtCheckSection->fetch(PDO::FETCH_ASSOC);

        if ($sectionConflict) {
            throw new LogicException("Section scheduling conflict: Section '{$section}' already has an exam scheduled on {$examDate} between {$sectionConflict['start_time']} and {$sectionConflict['end_time']}.");
        }

        $durationMinutes = max(15, round((strtotime($endTime) - strtotime($startTime)) / 60));

        // Always store authoritative $examOwnerId as schedule's teacher_id
        $stmtIns = $pdo->prepare("
            INSERT INTO exam_schedules (exam_id, teacher_id, section, exam_date, start_time, end_time, duration_minutes, room, remarks, semester_id, status)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'scheduled')
        ");
        $stmtIns->execute([$examId, $examOwnerId, $section, $examDate, $startTime, $endTime, $durationMinutes, $room, $remarks, $activeSem['id']]);
        $scheduleId = (int)$pdo->lastInsertId();

        // 5. Notify students of the scheduled section
        $stmtStud = $pdo->prepare("
            SELECT user_id FROM student_details WHERE section = ?
        ");
        $stmtStud->execute([$section]);
        $students = $stmtStud->fetchAll(PDO::FETCH_COLUMN);

        $notifMsg = "Exam Scheduled: '{$exam['title']}' for Section {$section} on {$examDate} from {$startTime} to {$endTime} (Room: " . ($room ?: 'TBA') . ").";
        foreach ($students as $stId) {
            NotificationService::sendNotification($stId, 'exam_scheduled', $notifMsg);
        }

        logActivity("Scheduled Exam #{$examId} ('{$exam['title']}') for Section {$section} on {$examDate} {$startTime}-{$endTime}; Exam Owner ID: {$examOwnerId}", $actorId);

        return $scheduleId;
    }

    public static function getUpcomingSchedulesForSection($section) {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("
            SELECT es.*, e.title as exam_title, e.subject, u.fullname as teacher_name
            FROM exam_schedules es
            JOIN exams e ON es.exam_id = e.id
            JOIN users u ON es.teacher_id = u.id
            WHERE es.section = ? AND es.exam_date >= CURRENT_DATE() AND es.status = 'scheduled'
            ORDER BY es.exam_date ASC, es.start_time ASC
        ");
        $stmt->execute([$section]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function getUpcomingSchedulesForTeacher($teacherId) {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("
            SELECT es.*, e.title as exam_title, e.subject
            FROM exam_schedules es
            JOIN exams e ON es.exam_id = e.id
            WHERE es.teacher_id = ? AND es.exam_date >= CURRENT_DATE() AND es.status = 'scheduled'
            ORDER BY es.exam_date ASC, es.start_time ASC
        ");
        $stmt->execute([$teacherId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function getAllSchedules($filters = []) {
        $pdo = getDBConnection();
        $where = "WHERE 1=1";
        $params = [];

        if (!empty($filters['section']) && $filters['section'] !== 'all') {
            $where .= " AND es.section = ?";
            $params[] = $filters['section'];
        }
        if (!empty($filters['teacher_id']) && $filters['teacher_id'] !== 'all') {
            $where .= " AND es.teacher_id = ?";
            $params[] = $filters['teacher_id'];
        }
        if (!empty($filters['status']) && $filters['status'] !== 'all') {
            $where .= " AND es.status = ?";
            $params[] = $filters['status'];
        }

        $stmt = $pdo->prepare("
            SELECT es.*, e.title as exam_title, e.subject, u.fullname as teacher_name
            FROM exam_schedules es
            JOIN exams e ON es.exam_id = e.id
            JOIN users u ON es.teacher_id = u.id
            {$where}
            ORDER BY es.exam_date DESC, es.start_time ASC
        ");
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
