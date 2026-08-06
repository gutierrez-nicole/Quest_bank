<?php

require_once __DIR__ . '/../database.php';
require_once __DIR__ . '/../../includes/security.php';

class AcademicStructureService {

    // ── SCHOOL YEARS ──

    public static function getSchoolYears() {
        $pdo = getDBConnection();
        $stmt = $pdo->query("SELECT * FROM school_years ORDER BY id DESC");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function getActiveSchoolYear() {
        $pdo = getDBConnection();
        $stmt = $pdo->query("SELECT * FROM school_years WHERE status = 'active' LIMIT 1");
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public static function createSchoolYear($schoolYear, $startDate, $endDate) {
        $schoolYear = trim($schoolYear);
        if (empty($schoolYear) || empty($startDate) || empty($endDate)) {
            throw new InvalidArgumentException("School year, start date, and end date are required.");
        }
        if (strtotime($endDate) <= strtotime($startDate)) {
            throw new InvalidArgumentException("End date must be after start date.");
        }

        $pdo = getDBConnection();
        $stmtCheck = $pdo->prepare("SELECT id FROM school_years WHERE school_year = ?");
        $stmtCheck->execute([$schoolYear]);
        if ($stmtCheck->fetchColumn()) {
            throw new InvalidArgumentException("School year '{$schoolYear}' already exists.");
        }

        $stmt = $pdo->prepare("INSERT INTO school_years (school_year, start_date, end_date, status) VALUES (?, ?, ?, 'inactive')");
        $stmt->execute([$schoolYear, $startDate, $endDate]);
        return (int)$pdo->lastInsertId();
    }

    public static function activateSchoolYear($id) {
        $pdo = getDBConnection();
        $stmtCheck = $pdo->prepare("SELECT * FROM school_years WHERE id = ?");
        $stmtCheck->execute([$id]);
        $sy = $stmtCheck->fetch(PDO::FETCH_ASSOC);

        if (!$sy) {
            throw new Exception("School year #{$id} not found.");
        }
        if ($sy['status'] === 'archived') {
            throw new LogicException("Cannot activate an archived school year.");
        }

        $pdo->beginTransaction();
        try {
            $pdo->exec("UPDATE school_years SET status = 'inactive' WHERE status = 'active'");
            $stmtUpd = $pdo->prepare("UPDATE school_years SET status = 'active' WHERE id = ?");
            $stmtUpd->execute([$id]);
            $pdo->commit();
            return true;
        } catch (Exception $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    public static function archiveSchoolYear($id) {
        $pdo = getDBConnection();
        $stmtCheck = $pdo->prepare("SELECT * FROM school_years WHERE id = ?");
        $stmtCheck->execute([$id]);
        $sy = $stmtCheck->fetch(PDO::FETCH_ASSOC);

        if (!$sy) {
            throw new Exception("School year #{$id} not found.");
        }

        $stmtUpd = $pdo->prepare("UPDATE school_years SET status = 'archived' WHERE id = ?");
        $stmtUpd->execute([$id]);
        return true;
    }

    // ── SEMESTERS ──

    public static function getSemesters($schoolYearId = null) {
        $pdo = getDBConnection();
        if ($schoolYearId) {
            $stmt = $pdo->prepare("SELECT s.*, sy.school_year FROM semesters s JOIN school_years sy ON s.school_year_id = sy.id WHERE s.school_year_id = ? ORDER BY s.id DESC");
            $stmt->execute([$schoolYearId]);
        } else {
            $stmt = $pdo->query("SELECT s.*, sy.school_year FROM semesters s JOIN school_years sy ON s.school_year_id = sy.id ORDER BY s.id DESC");
        }
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function getActiveSemester() {
        $pdo = getDBConnection();
        $stmt = $pdo->query("SELECT s.*, sy.school_year FROM semesters s JOIN school_years sy ON s.school_year_id = sy.id WHERE s.status = 'active' LIMIT 1");
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public static function createSemester($schoolYearId, $semesterName) {
        $semesterName = trim($semesterName);
        $validSemesters = ['First Semester', 'Second Semester', 'Summer'];
        if (!in_array($semesterName, $validSemesters, true)) {
            throw new InvalidArgumentException("Invalid semester name. Allowed: First Semester, Second Semester, Summer.");
        }

        $pdo = getDBConnection();
        $stmtCheck = $pdo->prepare("SELECT id FROM semesters WHERE school_year_id = ? AND semester_name = ?");
        $stmtCheck->execute([$schoolYearId, $semesterName]);
        if ($stmtCheck->fetchColumn()) {
            throw new InvalidArgumentException("Semester '{$semesterName}' already exists for this school year.");
        }

        $stmt = $pdo->prepare("INSERT INTO semesters (school_year_id, semester_name, status) VALUES (?, ?, 'inactive')");
        $stmt->execute([$schoolYearId, $semesterName]);
        return (int)$pdo->lastInsertId();
    }

    public static function activateSemester($id) {
        $pdo = getDBConnection();
        $stmtCheck = $pdo->prepare("SELECT * FROM semesters WHERE id = ?");
        $stmtCheck->execute([$id]);
        $sem = $stmtCheck->fetch(PDO::FETCH_ASSOC);

        if (!$sem) {
            throw new Exception("Semester #{$id} not found.");
        }
        if (in_array($sem['status'], ['archived', 'closed'], true)) {
            throw new LogicException("Cannot activate a closed or archived semester.");
        }

        $pdo->beginTransaction();
        try {
            $pdo->exec("UPDATE semesters SET status = 'inactive' WHERE status = 'active'");
            $stmtUpd = $pdo->prepare("UPDATE semesters SET status = 'active' WHERE id = ?");
            $stmtUpd->execute([$id]);
            $pdo->commit();
            return true;
        } catch (Exception $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    public static function closeSemester($id) {
        $pdo = getDBConnection();
        $stmtUpd = $pdo->prepare("UPDATE semesters SET status = 'closed' WHERE id = ?");
        $stmtUpd->execute([$id]);
        return true;
    }

    public static function archiveSemester($id) {
        $pdo = getDBConnection();
        $stmtUpd = $pdo->prepare("UPDATE semesters SET status = 'archived' WHERE id = ?");
        $stmtUpd->execute([$id]);
        return true;
    }

    // ── ACADEMIC CALENDAR ──

    public static function getAcademicCalendar($eventType = null) {
        $pdo = getDBConnection();
        if ($eventType && $eventType !== 'all') {
            $stmt = $pdo->prepare("SELECT c.*, u.fullname as creator_name FROM academic_calendar c LEFT JOIN users u ON c.created_by = u.id WHERE c.event_type = ? ORDER BY c.start_date ASC");
            $stmt->execute([$eventType]);
        } else {
            $stmt = $pdo->query("SELECT c.*, u.fullname as creator_name FROM academic_calendar c LEFT JOIN users u ON c.created_by = u.id ORDER BY c.start_date ASC");
        }
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function addCalendarEvent($title, $type, $startDate, $endDate, $description = '', $createdBy = null) {
        $title = trim($title);
        $type = trim($type);
        if (empty($title) || empty($type) || empty($startDate) || empty($endDate)) {
            throw new InvalidArgumentException("Event title, type, start date, and end date are required.");
        }

        $pdo = getDBConnection();
        $stmt = $pdo->prepare("INSERT INTO academic_calendar (event_title, event_type, start_date, end_date, description, created_by) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$title, $type, $startDate, $endDate, $description, $createdBy]);
        return (int)$pdo->lastInsertId();
    }

    // ── SECTIONS ──

    public static function getSections() {
        $pdo = getDBConnection();
        $stmt = $pdo->query("
            SELECT s.*, COALESCE(s.section_code, s.section_name) as section_code, COALESCE(u.fullname, u2.fullname) as adviser_name 
            FROM sections s 
            LEFT JOIN users u ON s.adviser_id = u.id 
            LEFT JOIN users u2 ON s.teacher_id = u2.id
            ORDER BY s.id ASC
        ");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function createSection($sectionCode, $adviserId = null, $capacity = 40) {
        $sectionCode = strtoupper(trim($sectionCode));
        $capacity = intval($capacity);

        if (empty($sectionCode)) {
            throw new InvalidArgumentException("Section code is required.");
        }
        if ($capacity <= 0) {
            throw new InvalidArgumentException("Section capacity must be greater than 0.");
        }

        $pdo = getDBConnection();
        $stmtCheck = $pdo->prepare("SELECT id FROM sections WHERE section_code = ? OR section_name = ?");
        $stmtCheck->execute([$sectionCode, $sectionCode]);
        if ($stmtCheck->fetchColumn()) {
            throw new InvalidArgumentException("Section code '{$sectionCode}' already exists.");
        }

        if ($adviserId) {
            $stmtAdv = $pdo->prepare("SELECT role FROM users WHERE id = ?");
            $stmtAdv->execute([$adviserId]);
            $role = $stmtAdv->fetchColumn();
            if ($role !== 'teacher') {
                throw new InvalidArgumentException("Section adviser must be a valid teacher.");
            }
        }

        $stmt = $pdo->prepare("INSERT INTO sections (section_code, section_name, course_name, academic_year, teacher_id, adviser_id, capacity, status) VALUES (?, ?, 'BSCE', '2025-2026', ?, ?, ?, 'active')");
        $stmt->execute([$sectionCode, $sectionCode, $adviserId ?: 10, $adviserId, $capacity]);
        return (int)$pdo->lastInsertId();
    }

    // ── TEACHER SUBJECT ASSIGNMENTS ──

    public static function getTeacherAssignments($teacherId = null) {
        $pdo = getDBConnection();
        if ($teacherId) {
            $stmt = $pdo->prepare("
                SELECT tsa.*, u.fullname as teacher_name, sec.section_code, sy.school_year
                FROM teacher_subject_assignments tsa
                JOIN users u ON tsa.teacher_id = u.id
                JOIN sections sec ON tsa.section_id = sec.id
                JOIN school_years sy ON tsa.school_year_id = sy.id
                WHERE tsa.teacher_id = ?
                ORDER BY tsa.id DESC
            ");
            $stmt->execute([$teacherId]);
        } else {
            $stmt = $pdo->query("
                SELECT tsa.*, u.fullname as teacher_name, sec.section_code, sy.school_year
                FROM teacher_subject_assignments tsa
                JOIN users u ON tsa.teacher_id = u.id
                JOIN sections sec ON tsa.section_id = sec.id
                JOIN school_years sy ON tsa.school_year_id = sy.id
                ORDER BY tsa.id DESC
            ");
        }
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function assignTeacherSubject($teacherId, $subject, $sectionId, $schoolYearId) {
        $subject = trim($subject);
        if (empty($subject)) {
            throw new InvalidArgumentException("Subject name is required.");
        }

        $pdo = getDBConnection();
        $stmtUser = $pdo->prepare("SELECT role FROM users WHERE id = ?");
        $stmtUser->execute([$teacherId]);
        if ($stmtUser->fetchColumn() !== 'teacher') {
            throw new InvalidArgumentException("User #{$teacherId} must be a valid teacher.");
        }

        $stmtCheck = $pdo->prepare("
            SELECT id FROM teacher_subject_assignments 
            WHERE teacher_id = ? AND subject = ? AND section_id = ? AND school_year_id = ?
        ");
        $stmtCheck->execute([$teacherId, $subject, $sectionId, $schoolYearId]);
        if ($stmtCheck->fetchColumn()) {
            throw new InvalidArgumentException("Duplicate assignment: Teacher is already assigned to this subject, section, and school year.");
        }

        $stmt = $pdo->prepare("
            INSERT INTO teacher_subject_assignments (teacher_id, subject, section_id, school_year_id, status)
            VALUES (?, ?, ?, ?, 'active')
        ");
        $stmt->execute([$teacherId, $subject, $sectionId, $schoolYearId]);
        return (int)$pdo->lastInsertId();
    }
}
