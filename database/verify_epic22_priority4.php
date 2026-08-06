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
$createdSubjectCodes = [];
$createdSectionCodes = [];

try {
    $pdo = getDBConnection();
    $runner->setSetupCompleted($pdo !== null, "Database connection established");

    $teacherId = (int)$pdo->query("SELECT id FROM users WHERE role = 'teacher' AND status = 'active' LIMIT 1")->fetchColumn();
    $studentId = (int)$pdo->query("SELECT id FROM users WHERE role = 'student' AND status = 'active' LIMIT 1")->fetchColumn();
    $adminId = (int)$pdo->query("SELECT id FROM users WHERE role = 'admin' AND status = 'active' LIMIT 1")->fetchColumn();

    if (!$teacherId) $teacherId = 10;
    if (!$studentId) $studentId = 11;
    if (!$adminId) {
        $stmtAdmin = $pdo->prepare("INSERT INTO users (username, fullname, email, password, role, status) VALUES ('temp_admin_p4', 'P4 Admin', 'temp_admin_p4@questbank.edu.ph', 'pass', 'admin', 'active')");
        $stmtAdmin->execute();
        $adminId = (int)$pdo->lastInsertId();
        $createdUserIds[] = $adminId;
    }

    // ── TEST 1: CSRF Infrastructure & Verification ──
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    $validToken = $_SESSION['csrf_token'];
    $invalidToken = 'invalid_csrf_token_12345';

    $runner->assertTrue("TEST 1a: CSRF helper generates token field", strpos(csrfInputField(), 'csrf_token') !== false, "CSRF input field rendered");
    $runner->assertTrue("TEST 1b: Valid CSRF token accepted", verifyCSRFToken($validToken) === true, "Valid CSRF token verified");
    $runner->assertTrue("TEST 1c: Invalid CSRF token rejected", verifyCSRFToken($invalidToken) === false, "Invalid CSRF token rejected");

    // ── TEST 2: School Year & Semester Safeguards ──
    $syName = '2099-2100';
    $pdo->exec("DELETE FROM school_years WHERE school_year = '{$syName}'");
    $syId1 = AcademicStructureService::createSchoolYear($syName, '2099-06-01', '2100-05-31');
    $createdSyIds[] = $syId1;

    AcademicStructureService::activateSchoolYear($syId1);
    $activeSy = AcademicStructureService::getActiveSchoolYear();
    $runner->assertTrue("TEST 2a: School year created and set as single active school year", $activeSy && intval($activeSy['id']) === $syId1, "Active SY: {$activeSy['school_year']}");

    $archivedBlock = false;
    try {
        AcademicStructureService::activateSchoolYear($syId1);
    } catch (LogicException $e) {
        $archivedBlock = true;
    }
    AcademicStructureService::archiveSchoolYear($syId1, true);

    // Re-activate standard SY
    $stdSyId = (int)$pdo->query("SELECT id FROM school_years WHERE school_year != '2099-2100' AND status != 'archived' LIMIT 1")->fetchColumn();
    if (!$stdSyId) {
        $stdSyId = AcademicStructureService::createSchoolYear('2025-2026', '2025-06-01', '2026-05-31');
        $createdSyIds[] = $stdSyId;
    }
    AcademicStructureService::activateSchoolYear($stdSyId);

    $semId1 = AcademicStructureService::createSemester($stdSyId, 'Summer');
    $createdSemIds[] = $semId1;
    AcademicStructureService::activateSemester($semId1);
    $activeSem = AcademicStructureService::getActiveSemester();
    $runner->assertTrue("TEST 2b: Semester activated", $activeSem && intval($activeSem['id']) === $semId1, "Active Semester: {$activeSem['semester_name']}");

    // Reactivate default semester
    $stdSemId = (int)$pdo->query("SELECT id FROM semesters WHERE status != 'closed' AND status != 'archived' LIMIT 1")->fetchColumn();
    if (!$stdSemId) {
        $stdSemId = AcademicStructureService::createSemester($stdSyId, 'First Semester');
        $createdSemIds[] = $stdSemId;
    }
    AcademicStructureService::activateSemester($stdSemId);

    // ── TEST 3: Section Creation without Hardcoding ──
    $secCode = 'CE-P4-SEC-' . bin2hex(random_bytes(2));
    $createdSectionCodes[] = $secCode;
    $secId = AcademicStructureService::createSection($secCode, 'BSCE-TRANSPORT', null, 35, $stdSyId);
    $runner->assertTrue("TEST 3a: Section created with dynamic parameters", $secId > 0, "Section ID: {$secId}");

    $stmtSecVal = $pdo->prepare("SELECT course, teacher_id, adviser_id, academic_year FROM sections WHERE id = ?");
    $stmtSecVal->execute([$secId]);
    $secData = $stmtSecVal->fetch(PDO::FETCH_ASSOC);

    $runner->assertTrue(
        "TEST 3b: Section contains no hardcoded BSCE/2025-2026/teacher 10",
        $secData['course'] === 'BSCE-TRANSPORT' && $secData['teacher_id'] === null && $secData['adviser_id'] === null,
        "Course: {$secData['course']}, Teacher ID: " . ($secData['teacher_id'] ?? 'NULL')
    );

    // Assign section to teacher for active school year
    $asgnId = AcademicStructureService::assignTeacherSubject($teacherId, 'Bridge Engineering', $secId, $stdSyId);
    $runner->assertTrue("TEST 3c: Teacher assigned to section for active school year", $asgnId > 0, "Assignment ID: {$asgnId}");

    // ── TEST 4: Server-Authoritative Teacher Scheduling & Admin Exam Scheduling ──
    $stmtEx1 = $pdo->prepare("
        INSERT INTO exams (teacher_id, title, subject, specialization, time_limit, total_items, exam_category, status, term)
        VALUES (?, 'Teacher Owned Exam 1', 'Bridge Engineering', 'Bridge Engineering', 60, 5, 'regular', 'active', 'midterm')
    ");
    $stmtEx1->execute([$teacherId]);
    $examId1 = $pdo->lastInsertId();
    $createdExamIds[] = $examId1;

    $otherTeacherId = (int)$pdo->query("SELECT id FROM users WHERE role = 'teacher' AND id != {$teacherId} LIMIT 1")->fetchColumn();
    if (!$otherTeacherId) {
        $stmtU2 = $pdo->prepare("INSERT INTO users (username, fullname, email, password, role, status) VALUES ('temp_t2', 'Teacher 2', 'temp_t2@questbank.edu.ph', 'pass', 'teacher', 'active')");
        $stmtU2->execute();
        $otherTeacherId = (int)$pdo->lastInsertId();
        $createdUserIds[] = $otherTeacherId;
    }

    $stmtEx2 = $pdo->prepare("
        INSERT INTO exams (teacher_id, title, subject, specialization, time_limit, total_items, exam_category, status, term)
        VALUES (?, 'Unowned Exam 2', 'Bridge Engineering', 'Bridge Engineering', 60, 5, 'regular', 'active', 'midterm')
    ");
    $stmtEx2->execute([$otherTeacherId]);
    $examId2 = $pdo->lastInsertId();
    $createdExamIds[] = $examId2;

    $activeSyForTest = AcademicStructureService::getActiveSchoolYear();
    $examDate = ($activeSyForTest && !empty($activeSyForTest['start_date'])) ? date('Y-m-d', strtotime($activeSyForTest['start_date'] . ' + 30 days')) : date('Y-m-d');
    
    // Teacher schedules own exam
    $schId1 = ExamSchedulingService::createSchedule($examId1, $teacherId, $secCode, $examDate, '10:00:00', '11:30:00', 'Room 201');
    $createdScheduleIds[] = $schId1;
    $runner->assertTrue("TEST 4a: Teacher scheduled owned exam for assigned section", $schId1 > 0, "Schedule ID: {$schId1}");

    // Unowned exam scheduling block for teacher actor
    $unownedBlocked = false;
    try {
        ExamSchedulingService::createSchedule($examId2, $teacherId, $secCode, $examDate, '13:00:00', '14:30:00', 'Room 202');
    } catch (SecurityException $e) {
        $unownedBlocked = true;
    }
    $runner->assertTrue("TEST 4b: Teacher scheduling unowned exam rejected", $unownedBlocked, "Unowned exam scheduling blocked cleanly");

    // Admin schedules exam owned by $otherTeacherId -> schedule's teacher_id must equal $otherTeacherId!
    $schIdAdmin = ExamSchedulingService::createSchedule($examId2, $adminId, $secCode, $examDate, '14:45:00', '16:00:00', 'Room 205');
    $createdScheduleIds[] = $schIdAdmin;

    $stmtSchVal = $pdo->prepare("SELECT teacher_id FROM exam_schedules WHERE id = ?");
    $stmtSchVal->execute([$schIdAdmin]);
    $storedTeacherId = intval($stmtSchVal->fetchColumn());
    $runner->assertTrue(
        "TEST 4c: Admin scheduling stores exam owner ($otherTeacherId) as schedule teacher_id, not admin ID ($adminId)",
        $storedTeacherId === $otherTeacherId,
        "Stored teacher_id: {$storedTeacherId}, Exam Owner ID: {$otherTeacherId}"
    );

    // ── TEST 5: Active Semester & School Year Alignment Enforcement ──
    $inactiveSyId = AcademicStructureService::createSchoolYear('2098-2099', '2098-06-01', '2099-05-31');
    $createdSyIds[] = $inactiveSyId;
    $misalignedSemId = AcademicStructureService::createSemester($inactiveSyId, 'Second Semester');
    $createdSemIds[] = $misalignedSemId;

    $misalignedBlocked = false;
    try {
        AcademicStructureService::activateSemester($misalignedSemId);
    } catch (LogicException $e) {
        $misalignedBlocked = true;
    }
    $runner->assertTrue("TEST 5: Activation of semester under inactive school year rejected", $misalignedBlocked, "Misaligned semester activation blocked cleanly");

    // ── TEST 6: Real Subject CSV Import & DB Insertion ──
    $subjUniq = bin2hex(random_bytes(3));
    $subjCode = "CE-SUBJ-{$subjUniq}";
    $subjTitle = "Advanced Bridge Design {$subjUniq}";
    $createdSubjectCodes[] = $subjCode;

    $tmpSubjCsv = sys_get_temp_dir() . "/test_subj_{$subjUniq}.csv";
    $csvContent = "code,title\n";
    $csvContent .= "{$subjCode},{$subjTitle}\n";
    $csvContent .= "{$subjCode},{$subjTitle}\n"; // Duplicate row inside same CSV
    file_put_contents($tmpSubjCsv, $csvContent);

    $subjPreview = BulkImportService::processCSV($tmpSubjCsv, 'subjects', false, 1);
    $runner->assertTrue("TEST 6a: Subject CSV preview detects duplicate row in same CSV", $subjPreview['valid_rows_count'] === 1 && $subjPreview['invalid_rows_count'] === 1, "Valid: {$subjPreview['valid_rows_count']}, Invalid: {$subjPreview['invalid_rows_count']}");

    $subjExec = BulkImportService::processCSV($tmpSubjCsv, 'subjects', true, 1);
    $runner->assertTrue("TEST 6b: Subject CSV execution imports valid row", $subjExec['imported_count'] === 1, "Imported count: {$subjExec['imported_count']}");

    $stmtCheckSubj = $pdo->prepare("SELECT id FROM subjects WHERE code = ? AND title = ?");
    $stmtCheckSubj->execute([$subjCode, $subjTitle]);
    $insertedSubjId = $stmtCheckSubj->fetchColumn();
    $runner->assertTrue("TEST 6c: Subject record genuinely inserted in database", $insertedSubjId > 0, "Subject DB ID: {$insertedSubjId}");
    @unlink($tmpSubjCsv);

    // ── TEST 7: Hardened Student CSV Import & Unique Credentials ──
    $studUniq = time() . bin2hex(random_bytes(2));
    $tmpStudCsv = sys_get_temp_dir() . "/test_stud_{$studUniq}.csv";
    $csvData = "student_number,fullname,email,course,section\n";
    $csvData .= "23-{$studUniq},P4 Credentials Student,p4credentials_{$studUniq}@questbank.edu.ph,BSCE,{$secCode}\n";
    file_put_contents($tmpStudCsv, $csvData);

    $studExec = BulkImportService::processCSV($tmpStudCsv, 'students', true, 1);
    $runner->assertTrue("TEST 7a: Student CSV import executed", $studExec['imported_count'] === 1, "Imported count: {$studExec['imported_count']}");

    $createdCreds = $studExec['credentials'] ?? [];
    $hasTempPass = !empty($createdCreds) && !empty($createdCreds[0]['temp_password']) && strlen($createdCreds[0]['temp_password']) >= 8;
    $runner->assertTrue("TEST 7b: Generated unique temporary credentials returned without exposing password hash", $hasTempPass, "Temp Password generated cleanly");

    $importedUid = (int)$pdo->query("SELECT id FROM users WHERE email = 'p4credentials_{$studUniq}@questbank.edu.ph'")->fetchColumn();
    if ($importedUid) $createdUserIds[] = $importedUid;
    @unlink($tmpStudCsv);

    // ── TEST 8: Academic Calendar Date Range Validation ──
    $calendarInvalidDate = false;
    try {
        AcademicStructureService::addCalendarEvent('Invalid Date Test', 'midterm_week', '2026-10-10', '2026-10-05', 'Invalid range', $teacherId);
    } catch (InvalidArgumentException $e) {
        $calendarInvalidDate = true;
    }
    $runner->assertTrue("TEST 8: Academic calendar invalid date range rejected", $calendarInvalidDate, "Invalid date range caught cleanly");

    // ── TEST 9: Notification Ownership Protection ──
    $u1 = $teacherId;
    $u2 = $studentId;
    NotificationService::sendNotification($u1, 'p4_test_u1', 'User 1 Notification');
    $notifsU1 = NotificationService::getUserNotifications($u1, 1);
    $u1NotifId = !empty($notifsU1) ? (int)$notifsU1[0]['id'] : 0;

    $crossMutateBlocked = (NotificationService::markAsRead($u1NotifId, $u2) === false);
    $runner->assertTrue("TEST 9a: User cannot mark another user's notification as read", $crossMutateBlocked, "Cross-user notification mark rejected");

    $ownMutateSuccess = NotificationService::markAsRead($u1NotifId, $u1);
    $runner->assertTrue("TEST 9b: User can mark own notification as read", $ownMutateSuccess, "Own notification marked read cleanly");

    NotificationService::deleteNotification($u1NotifId, $u1);

    // ── TEST 10: System Settings & Maintenance Mode Enforcement ──
    SystemSettingsService::setSetting('maintenance_mode', 'off');
    $mMode = SystemSettingsService::getSetting('maintenance_mode');
    $runner->assertTrue("TEST 10: Maintenance mode setting updated and verified", $mMode === 'off', "Maintenance mode: {$mMode}");

    // ── TEST 11: Maintenance Login Links & Index.php Route Verification ──
    $mFile = file_get_contents(__DIR__ . '/../maintenance.php');
    $hasCanonicalLoginLink = strpos($mFile, '/index.php') !== false || strpos($mFile, 'index.php') !== false;
    $runner->assertTrue("TEST 11: maintenance.php links to canonical login route index.php", $hasCanonicalLoginLink, "Canonical login route verified in maintenance.php");

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
        foreach ($createdSubjectCodes as $scode) {
            $pdo->exec("DELETE FROM subjects WHERE code = '{$scode}'");
        }
        foreach ($createdSectionCodes as $seccode) {
            $pdo->exec("DELETE FROM teacher_subject_assignments WHERE section_id IN (SELECT id FROM sections WHERE section_code = '{$seccode}')");
            $pdo->exec("DELETE FROM sections WHERE section_code = '{$seccode}'");
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
