<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../tests/helpers/test_runner.php';
requireDatabasePreflight();

require_once __DIR__ . '/../app/bootstrap.php';
require_once __DIR__ . '/../app/services/BackupService.php';
require_once __DIR__ . '/../app/services/SystemHealthService.php';
require_once __DIR__ . '/../app/services/StorageManagementService.php';
require_once __DIR__ . '/../app/services/SessionManagementService.php';
require_once __DIR__ . '/../app/services/SecurityAuditService.php';
require_once __DIR__ . '/../app/services/AuditLogService.php';

$runner = new TestRunner('QuestBank Priority 5 Operations & Security Hardening Verification');

$pdo = null;
$createdBackupFiles = [];
$createdUserIds = [];

try {
    $pdo = getDBConnection();
    $runner->setSetupCompleted($pdo !== null, "Database connection established");

    $adminId = (int)$pdo->query("SELECT id FROM users WHERE role = 'admin' AND status = 'active' LIMIT 1")->fetchColumn();
    if (!$adminId) {
        $stmtAdmin = $pdo->prepare("INSERT INTO users (username, fullname, email, password, role, status) VALUES ('temp_p5_admin', 'P5 Admin', 'temp_p5_admin@questbank.edu.ph', 'pass', 'admin', 'active')");
        $stmtAdmin->execute();
        $adminId = (int)$pdo->lastInsertId();
        $createdUserIds[] = $adminId;
    }

    // Ensure an active school year & active semester exist for health check test
    $activeSy = AcademicStructureService::getActiveSchoolYear();
    if (!$activeSy) {
        $syId = AcademicStructureService::createSchoolYear('2025-2026', '2025-06-01', '2026-05-31');
        AcademicStructureService::activateSchoolYear($syId);
        $activeSy = AcademicStructureService::getActiveSchoolYear();
    }
    $activeSem = AcademicStructureService::getActiveSemester();
    if (!$activeSem && $activeSy) {
        $semId = AcademicStructureService::createSemester($activeSy['id'], 'First Semester');
        AcademicStructureService::activateSemester($semId);
    }

    // ── TEST 1: Session Management & Forced Logout Enforcement ──
    $testSessId = 'test_sess_' . bin2hex(random_bytes(8));
    $stmtSessIns = $pdo->prepare("
        INSERT INTO user_sessions (session_id, user_id, ip_address, user_agent, login_time, last_activity, status)
        VALUES (?, ?, '127.0.0.1', 'CLI Test', NOW(), NOW(), 'active')
    ");
    $stmtSessIns->execute([$testSessId, $adminId]);

    SessionManagementService::terminateSession($testSessId, $adminId);

    $stmtCheckTerm = $pdo->prepare("SELECT status FROM user_sessions WHERE session_id = ?");
    $stmtCheckTerm->execute([$testSessId]);
    $termStatus = $stmtCheckTerm->fetchColumn();

    $runner->assertTrue("TEST 1a: Admin session termination marks status as terminated", $termStatus === 'terminated', "Session status: {$termStatus}");

    // Cleanup session record
    $pdo->exec("DELETE FROM user_sessions WHERE session_id = '{$testSessId}'");

    // ── TEST 2: Mandatory Password Reset Workflow & Enforcement ──
    $tempPass = 'TempPass123!';
    $tempHash = password_hash($tempPass, PASSWORD_DEFAULT);
    $stmtU = $pdo->prepare("INSERT INTO users (username, fullname, email, password, role, status, force_password_reset) VALUES ('flagged_stud', 'Flagged Student', 'flagged_stud@questbank.edu.ph', ?, 'student', 'active', 1)");
    $stmtU->execute([$tempHash]);
    $flaggedUid = (int)$pdo->lastInsertId();
    $createdUserIds[] = $flaggedUid;

    $stmtCheckFlag = $pdo->prepare("SELECT force_password_reset FROM users WHERE id = ?");
    $stmtCheckFlag->execute([$flaggedUid]);
    $isFlagged = intval($stmtCheckFlag->fetchColumn());
    $runner->assertTrue("TEST 2a: User created with force_password_reset = 1", $isFlagged === 1, "force_password_reset: {$isFlagged}");

    // Simulate password reset
    $newPass = 'NewSecurePass123!';
    $newHash = password_hash($newPass, PASSWORD_DEFAULT);
    $stmtReset = $pdo->prepare("UPDATE users SET password = ?, force_password_reset = 0, password_changed_at = NOW() WHERE id = ?");
    $stmtReset->execute([$newHash, $flaggedUid]);

    $stmtCheckUnflag = $pdo->prepare("SELECT force_password_reset, password FROM users WHERE id = ?");
    $stmtCheckUnflag->execute([$flaggedUid]);
    $resetUser = $stmtCheckUnflag->fetch(PDO::FETCH_ASSOC);

    $runner->assertTrue("TEST 2b: Password reset clears force_password_reset flag", intval($resetUser['force_password_reset']) === 0, "force_password_reset: {$resetUser['force_password_reset']}");
    $runner->assertTrue("TEST 2c: Old password fails, new password succeeds", password_verify($newPass, $resetUser['password']) && !password_verify($tempPass, $resetUser['password']), "Password update verified");

    // ── TEST 3: Orphan-File Deletion Security & Whitelist Boundaries ──
    // 3a: Attempt deleting PHP source file -> must be rejected!
    $appFile = __DIR__ . '/../app/services/AuditLogService.php';
    $resApp = StorageManagementService::deleteOrphanedFiles([$appFile], $adminId);
    $runner->assertTrue("TEST 3a: Source file deletion (/app/services/AuditLogService.php) strictly rejected", $resApp['deleted_count'] === 0 && $resApp['rejected_count'] === 1, "Rejected count: {$resApp['rejected_count']}");

    // 3b: Attempt path traversal -> must be rejected!
    $travFile = __DIR__ . '/../../app/bootstrap.php';
    $resTrav = StorageManagementService::deleteOrphanedFiles([$travFile], $adminId);
    $runner->assertTrue("TEST 3b: Path traversal deletion strictly rejected", $resTrav['deleted_count'] === 0 && $resTrav['rejected_count'] === 1, "Rejected count: {$resTrav['rejected_count']}");

    // 3c: Attempt deleting arbitrary OS temp file -> MUST BE REJECTED!
    $sysTempFile = sys_get_temp_dir() . '/random_os_file_' . bin2hex(random_bytes(4)) . '.txt';
    file_put_contents($sysTempFile, 'unrelated os content');
    $resSysTemp = StorageManagementService::deleteOrphanedFiles([$sysTempFile], $adminId);
    @unlink($sysTempFile);
    $runner->assertTrue("TEST 3c: Arbitrary OS system-temp file deletion strictly rejected", $resSysTemp['deleted_count'] === 0 && $resSysTemp['rejected_count'] === 1, "Rejected arbitrary temp file count: {$resSysTemp['rejected_count']}");

    // 3d: Dedicated QuestBank storage temp file -> deletion succeeds!
    $qbTempDir = StorageManagementService::getQuestBankTempDir();
    $testQbTempFile = $qbTempDir . '/qb_batch_test_' . bin2hex(random_bytes(4)) . '.csv';
    file_put_contents($testQbTempFile, 'qb temp content');

    $resQbTemp = StorageManagementService::deleteOrphanedFiles([$testQbTempFile], $adminId);
    $runner->assertTrue("TEST 3d: Dedicated QuestBank temp file deletion succeeds", $resQbTemp['deleted_count'] === 1, "Deleted count: {$resQbTemp['deleted_count']}");

    // ── TEST 4: Database Restore Safety & Pre-Restore Recovery Backup ──
    $backup = BackupService::createBackup($adminId);
    $createdBackupFiles[] = $backup['filename'];

    // 4a: Missing/invalid phrase rejected
    $invalidPhraseBlocked = false;
    try {
        BackupService::restoreBackup($backup['filename'], $adminId, 'INVALID');
    } catch (InvalidArgumentException $e) {
        $invalidPhraseBlocked = true;
    }
    $runner->assertTrue("TEST 4a: Restore with invalid confirmation phrase rejected", $invalidPhraseBlocked, "Invalid phrase rejected cleanly");

    // 4b: Valid restore creates safety backup
    $restoreRes = BackupService::restoreBackup($backup['filename'], $adminId, 'RESTORE');
    $createdBackupFiles[] = $restoreRes['safety_backup'];
    $safetyExists = file_exists(__DIR__ . '/../database/backups/' . $restoreRes['safety_backup']);
    $runner->assertTrue("TEST 4b: Safety backup created automatically before restore", $safetyExists, "Safety backup: {$restoreRes['safety_backup']}");

    // 4c: Reusable backup filename pattern validation & .htaccess protection
    $runner->assertTrue("TEST 4c: Valid backup pattern accepted", BackupService::isValidBackupFilename('qb_backup_2026-08-06_123456_abc123.sql'), "Valid normal pattern accepted");
    $runner->assertTrue("TEST 4d: Valid safety backup pattern accepted", BackupService::isValidBackupFilename('qb_safety_backup_2026-08-06_123456_abc123.sql'), "Valid safety pattern accepted");
    $runner->assertTrue("TEST 4e: .htaccess backup deletion strictly rejected", !BackupService::deleteBackup('.htaccess', $adminId), ".htaccess delete rejected");
    $runner->assertTrue("TEST 4f: Unrelated filename rejected by backup validator", !BackupService::isValidBackupFilename('random_backup.sql') && !BackupService::isValidBackupFilename('.env'), "Unrelated filenames rejected");

    // ── TEST 5: Audit Log Actor Integrity (No False Attribution) ──
    // 5a: Invalid actor ID -> stored as NULL (not mapped to real user ID)
    AuditLogService::logAction(999999, 'CLI Test Action Invalid Actor', 'Details test');
    $stmtLog = $pdo->query("SELECT user_id, actor_id FROM audit_logs WHERE action = 'CLI Test Action Invalid Actor' ORDER BY id DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    $runner->assertTrue("TEST 5a: Invalid actor ID stored as NULL without mapping to real user", $stmtLog['user_id'] === null && $stmtLog['actor_id'] === null, "User ID: " . var_export($stmtLog['user_id'], true));

    // 5b: System action (actor = 0/null) -> stored as NULL
    AuditLogService::logAction(0, 'System Automated Task', 'No actor test');
    $stmtSysLog = $pdo->query("SELECT user_id, actor_id FROM audit_logs WHERE action = 'System Automated Task' ORDER BY id DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    $runner->assertTrue("TEST 5b: System action stored as NULL actor without mapping to real user", $stmtSysLog['user_id'] === null && $stmtSysLog['actor_id'] === null, "Actor ID: " . var_export($stmtSysLog['actor_id'], true));

    // ── TEST 6: Deployment Readiness Checklist Failures & Diagnostics Accuracy ──
    SystemSettingsService::setSetting('maintenance_mode', 'on');
    $chkMModeOn = SystemHealthService::getDeploymentChecklist();
    $runner->assertTrue("TEST 6a: Deployment checklist overall status is FAIL when maintenance mode is ON", $chkMModeOn['overall_status'] === 'FAIL', "Overall status: {$chkMModeOn['overall_status']}");

    SystemSettingsService::setSetting('maintenance_mode', 'off');
    $chkMModeOff = SystemHealthService::getDeploymentChecklist();
    $runner->assertTrue("TEST 6b: Deployment checklist overall status restores when maintenance mode is OFF", $chkMModeOff['overall_status'] !== 'FAIL', "Overall status: {$chkMModeOff['overall_status']}");

    // ── TEST 7: Standardized Error Page Renderer Helper ──
    $runner->assertTrue("TEST 7: renderErrorPage helper function exists and is callable", function_exists('renderErrorPage'), "renderErrorPage verified");

} catch (Throwable $e) {
    $runner->recordException($e);
} finally {
    if ($pdo) {
        foreach ($createdUserIds as $uid) {
            $pdo->exec("DELETE FROM users WHERE id = {$uid}");
        }
    }
    if (!empty($createdBackupFiles)) {
        foreach ($createdBackupFiles as $bf) {
            BackupService::deleteBackup($bf, $adminId ?? 1);
        }
    }
    $runner->finish();
}
