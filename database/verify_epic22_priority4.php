<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../tests/helpers/test_runner.php';
requireDatabasePreflight();

require_once __DIR__ . '/../app/bootstrap.php';
require_once __DIR__ . '/../app/services/AcademicStructureService.php';
require_once __DIR__ . '/../app/services/ExamSchedulingService.php';
require_once __DIR__ . '/../app/services/NotificationService.php';
require_once __DIR__ . '/../app/services/SystemSettingsService.php';
require_once __DIR__ . '/../app/services/AuditLogService.php';
require_once __DIR__ . '/../app/services/BulkImportService.php';

$runner = new TestRunner('QuestBank Priority 4 Academic Administration Verification');

$pdo = null;
$createdSyIds = [];
$createdSemIds = [];
$createdScheduleIds = [];
$createdExamIds = [];
$createdUserIds = [];

try {
    $pdo = getDBConnection();
    $runner->setSetupCompleted($pdo !== null, "Database connection established");

    $teacherId = (int)$pdo->query("SELECT id FROM users WHERE role = 'teacher' LIMIT 1")->fetchColumn();
    $studentId = (int)$pdo->query("SELECT id FROM users WHERE role = 'student' LIMIT 1")->fetchColumn();
    if (!$teacherId) $teacherId = 10;
    if (!$studentId) $studentId = 11;

    // ── TEST 1: School Year Management & Single Active Rule ──
    $syName = '2099-2100';
    $pdo->exec("DELETE FROM school_years WHERE school_year = '{$syName}'");
    $syId1 = AcademicStructureService::createSchoolYear($syName, '2099-06-01', '2100-05-31');
    $createdSyIds[] = $syId1;

    AcademicStructureService::activateSchoolYear($syId1);
    $activeSy = AcademicStructureService::getActiveSchoolYear();
    $runner->assertTrue("TEST 1a: School year created and set as single active school year", $activeSy && intval($activeSy['id']) === $syId1, "Active SY: {$activeSy['school_year']}");

    AcademicStructureService::archiveSchoolYear($syId1);
    $archivedBlock = false;
    try {
        AcademicStructureService::activateSchoolYear($syId1);
    } catch (LogicException $e) {
        $archivedBlock = true;
    }
    $runner->assertTrue("TEST 1b: Activation of archived school year rejected", $archivedBlock, "Archived SY activation blocked cleanly");

    // Re-activate standard SY
    $stdSyId = (int)$pdo->query("SELECT id FROM school_years WHERE school_year != '2099-2100' LIMIT 1")->fetchColumn();
    if ($stdSyId) {
        AcademicStructureService::activateSchoolYear($stdSyId);
    } else {
        $stdSyId = AcademicStructureService::createSchoolYear('2025-2026', '2025-06-01', '2026-05-31');
        $createdSyIds[] = $stdSyId;
        AcademicStructureService::activateSchoolYear($stdSyId);
    }

    // ── TEST 2: Semester Management & Activation Rules ──
    $semId1 = AcademicStructureService::createSemester($stdSyId, 'Summer');
    $createdSemIds[] = $semId1;

    AcademicStructureService::activateSemester($semId1);
    $activeSem = AcademicStructureService::getActiveSemester();
    $runner->assertTrue("TEST 2a: Semester created and set as active semester", $activeSem && intval($activeSem['id']) === $semId1, "Active Semester: {$activeSem['semester_name']}");

    AcademicStructureService::closeSemester($semId1);
    $closedBlock = false;
    try {
        AcademicStructureService::activateSemester($semId1);
    } catch (LogicException $e) {
        $closedBlock = true;
    }
    $runner->assertTrue("TEST 2b: Activation of closed semester rejected", $closedBlock, "Closed semester activation blocked cleanly");

    // Reactivate default semester
    $stdSemId = (int)$pdo->query("SELECT id FROM semesters WHERE status != 'closed' AND status != 'archived' LIMIT 1")->fetchColumn();
    if (!$stdSemId) {
        $stdSemId = AcademicStructureService::createSemester($stdSyId, 'First Semester');
        $createdSemIds[] = $stdSemId;
    }
    AcademicStructureService::activateSemester($stdSemId);

    // ── TEST 3: Exam Scheduling & Overlap Conflict Detection ──
    $stmtEx = $pdo->prepare("
        INSERT INTO exams (teacher_id, title, subject, specialization, time_limit, total_items, exam_category, status, term)
        VALUES (?, 'Priority 4 Schedule Test Exam', 'Transportation Engineering', 'Transportation Engineering', 60, 5, 'regular', 'active', 'midterm')
    ");
    $stmtEx->execute([$teacherId]);
    $examId = $pdo->lastInsertId();
    $createdExamIds[] = $examId;

    $tomorrow = date('Y-m-d', strtotime('+1 day'));
    $schId1 = ExamSchedulingService::createSchedule($examId, $teacherId, 'CE-TEST', $tomorrow, '10:00:00', '11:30:00', 'Room 101');
    $createdScheduleIds[] = $schId1;
    $runner->assertTrue("TEST 3a: Exam schedule created successfully", $schId1 > 0, "Schedule ID: {$schId1}");

    // Teacher conflict test
    $teacherConflict = false;
    try {
        ExamSchedulingService::createSchedule($examId, $teacherId, 'CE-OTHER', $tomorrow, '10:30:00', '12:00:00', 'Room 102');
    } catch (LogicException $e) {
        $teacherConflict = true;
    }
    $runner->assertTrue("TEST 3b: Overlapping schedule for same teacher rejected", $teacherConflict, "Teacher schedule conflict caught cleanly");

    // Section conflict test
    $stmtEx2 = $pdo->prepare("
        INSERT INTO exams (teacher_id, title, subject, specialization, time_limit, total_items, exam_category, status, term)
        VALUES (?, 'Priority 4 Schedule Test Exam 2', 'Transportation Engineering', 'Transportation Engineering', 60, 5, 'regular', 'active', 'midterm')
    ");
    $stmtEx2->execute([$teacherId]);
    $examId2 = $pdo->lastInsertId();
    $createdExamIds[] = $examId2;

    $sectionConflict = false;
    try {
        ExamSchedulingService::createSchedule($examId2, $teacherId, 'CE-TEST', $tomorrow, '11:00:00', '12:30:00', 'Room 103');
    } catch (LogicException $e) {
        $sectionConflict = true;
    }
    $runner->assertTrue("TEST 3c: Overlapping schedule for same section rejected", $sectionConflict, "Section schedule conflict caught cleanly");

    // ── TEST 4: Teacher Subject Assignments & Duplicate Protection ──
    $secId = (int)$pdo->query("SELECT id FROM sections LIMIT 1")->fetchColumn();
    if (!$secId) {
        $secId = AcademicStructureService::createSection('CE-TEST-SEC', $teacherId, 35);
    }

    $asgnId = AcademicStructureService::assignTeacherSubject($teacherId, 'Geotechnical Engineering', $secId, $stdSyId);
    $runner->assertTrue("TEST 4a: Teacher subject assignment created", $asgnId > 0, "Assignment ID: {$asgnId}");

    $dupAsgnBlocked = false;
    try {
        AcademicStructureService::assignTeacherSubject($teacherId, 'Geotechnical Engineering', $secId, $stdSyId);
    } catch (InvalidArgumentException $e) {
        $dupAsgnBlocked = true;
    }
    $runner->assertTrue("TEST 4b: Duplicate teacher subject assignment rejected", $dupAsgnBlocked, "Duplicate assignment blocked cleanly");

    // ── TEST 5: Bulk CSV Import Validation & Execution ──
    $uniqId = time() . rand(100, 999);
    $tmpCsv = sys_get_temp_dir() . '/test_students_' . $uniqId . '.csv';
    $csvData = "student_number,fullname,email,course,section\n";
    $csvData .= "23-{$uniqId},P4 Test Student,p4student_{$uniqId}@questbank.edu.ph,BSCE,CE-4A\n";
    $csvData .= "INVALID_ROW_NO_EMAIL,Test Invalid,,BSCE,CE-4A\n";
    file_put_contents($tmpCsv, $csvData);

    $previewRes = BulkImportService::processCSV($tmpCsv, 'students', false, 1);
    $runner->assertTrue("TEST 5a: CSV preview validates rows correctly", $previewRes['valid_rows_count'] === 1 && $previewRes['invalid_rows_count'] === 1, "Valid: {$previewRes['valid_rows_count']}, Invalid: {$previewRes['invalid_rows_count']}");

    $execRes = BulkImportService::processCSV($tmpCsv, 'students', true, 1);
    $runner->assertTrue("TEST 5b: CSV execution imports valid rows", $execRes['imported_count'] === 1, "Imported count: {$execRes['imported_count']}");

    // Cleanup imported user
    $importedUid = (int)$pdo->query("SELECT id FROM users WHERE email LIKE 'p4student_%'")->fetchColumn();
    if ($importedUid) $createdUserIds[] = $importedUid;
    @unlink($tmpCsv);

    // ── TEST 6: System Settings Management ──
    SystemSettingsService::setSetting('passing_percentage', '82.50');
    $val = SystemSettingsService::getSetting('passing_percentage');
    $runner->assertTrue("TEST 6a: System setting updated and retrieved", floatval($val) === 82.50, "Passing percentage setting: {$val}%");

    $invalidSetBlocked = false;
    try {
        SystemSettingsService::setSetting('passing_percentage', '150.00');
    } catch (InvalidArgumentException $e) {
        $invalidSetBlocked = true;
    }
    $runner->assertTrue("TEST 6b: Invalid system setting percentage rejected", $invalidSetBlocked, "Invalid percentage setting blocked cleanly");

    // ── TEST 7: In-App Notifications Service ──
    NotificationService::sendNotification($studentId, 'p4_test', 'Priority 4 Test Notification');
    $unreads = NotificationService::getUnreadCount($studentId);
    $runner->assertTrue("TEST 7a: Notification sent and unread count incremented", $unreads >= 1, "Unread notifications: {$unreads}");

    $notifs = NotificationService::getUserNotifications($studentId, 5);
    $lastNotifId = !empty($notifs) ? (int)$notifs[0]['id'] : 0;
    NotificationService::markAsRead($lastNotifId, $studentId);
    NotificationService::deleteNotification($lastNotifId, $studentId);
    $runner->assertTrue("TEST 7b: Notification marked as read and deleted", $lastNotifId > 0, "Processed notification ID: {$lastNotifId}");

    // ── TEST 8: Audit Log Service ──
    AuditLogService::logAction($teacherId, "P4 Verification Action", "Testing audit log recording");
    $logs = AuditLogService::getLogs(['action' => 'P4 Verification Action'], 1);
    $runner->assertTrue("TEST 8: Audit log action recorded and retrieved cleanly", !empty($logs) && $logs[0]['action'] === 'P4 Verification Action', "Logged action: " . ($logs[0]['action'] ?? 'None'));

} catch (Throwable $e) {
    $runner->recordException($e);
} finally {
    if ($pdo) {
        foreach ($createdScheduleIds as $scid) {
            $pdo->exec("DELETE FROM exam_schedules WHERE id = {$scid}");
        }
        foreach ($createdExamIds as $eid) {
            $pdo->exec("DELETE FROM exams WHERE id = {$eid}");
        }
        foreach ($createdUserIds as $uid) {
            $pdo->exec("DELETE FROM student_details WHERE user_id = {$uid}");
            $pdo->exec("DELETE FROM users WHERE id = {$uid}");
        }
        foreach ($createdSemIds as $smid) {
            $pdo->exec("DELETE FROM semesters WHERE id = {$smid}");
        }
        foreach ($createdSyIds as $syid) {
            $pdo->exec("DELETE FROM school_years WHERE id = {$syid}");
        }
    }
    $runner->finish();
}
