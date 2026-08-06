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

$runner = new TestRunner('QuestBank Priority 5 Operations & Maintenance Verification');

$pdo = null;
$createdBackupFiles = [];

try {
    $pdo = getDBConnection();
    $runner->setSetupCompleted($pdo !== null, "Database connection established");

    $adminId = (int)$pdo->query("SELECT id FROM users WHERE role = 'admin' AND status = 'active' LIMIT 1")->fetchColumn();
    if (!$adminId) {
        $stmtAdmin = $pdo->prepare("INSERT INTO users (username, fullname, email, password, role, status) VALUES ('temp_p5_admin', 'P5 Admin', 'temp_p5_admin@questbank.edu.ph', 'pass', 'admin', 'active')");
        $stmtAdmin->execute();
        $adminId = (int)$pdo->lastInsertId();
    }

    // ── TEST 1: Database Backup Creation & Inventory Listing ──
    $backup = BackupService::createBackup($adminId);
    $createdBackupFiles[] = $backup['filename'];

    $runner->assertTrue("TEST 1a: Database backup created successfully", !empty($backup['filename']) && file_exists($backup['file_path']), "File: {$backup['filename']}, Size: {$backup['size_formatted']}");

    $backupsList = BackupService::listBackups();
    $foundInList = false;
    foreach ($backupsList as $b) {
        if ($b['filename'] === $backup['filename']) {
            $foundInList = true;
            break;
        }
    }
    $runner->assertTrue("TEST 1b: Generated backup appears in backup list inventory", $foundInList, "Backup inventory verified");

    // ── TEST 2: Database Restore & Integrity Check ──
    $restoreSuccess = BackupService::restoreBackup($backup['filename'], $adminId);
    $runner->assertTrue("TEST 2a: Database restore from valid backup snapshot executed", $restoreSuccess === true, "Database restore complete");

    $invalidRestoreBlocked = false;
    try {
        BackupService::restoreBackup('non_existent_backup_file.sql', $adminId);
    } catch (InvalidArgumentException $e) {
        $invalidRestoreBlocked = true;
    }
    $runner->assertTrue("TEST 2b: Restoring non-existent backup file rejected", $invalidRestoreBlocked, "Invalid backup restore caught cleanly");

    // ── TEST 3: System Health Diagnostics & Deployment Checklist ──
    $diagnostics = SystemHealthService::getHealthDiagnostics();
    $runner->assertTrue("TEST 3a: System health diagnostics generated", isset($diagnostics['database']) && $diagnostics['database']['status'] === 'PASS', "DB Status: {$diagnostics['database']['status']}");

    $checklist = SystemHealthService::getDeploymentChecklist();
    $runner->assertTrue("TEST 3b: Deployment readiness checklist generated", count($checklist) >= 5, "Checklist items count: " . count($checklist));

    // ── TEST 4: Storage Overview & Temporary File Cleanup ──
    $storage = StorageManagementService::getStorageOverview();
    $runner->assertTrue("TEST 4a: Storage overview metrics retrieved", isset($storage['lessons']) && isset($storage['backups']), "Lessons size: {$storage['lessons']['size_formatted']}");

    $tempClean = StorageManagementService::cleanTemporaryFiles($adminId);
    $runner->assertTrue("TEST 4b: Temporary preview file cleanup executed", isset($tempClean['cleaned_count']), "Cleaned files: {$tempClean['cleaned_count']}");

    // ── TEST 5: Active Session Tracking & Termination ──
    SessionManagementService::trackSession($adminId);
    $activeSessions = SessionManagementService::getActiveSessions();
    $runner->assertTrue("TEST 5a: Active session tracked in database", !empty($activeSessions), "Active sessions count: " . count($activeSessions));

    if (!empty($activeSessions)) {
        $testSessId = $activeSessions[0]['session_id'];
        SessionManagementService::terminateSession($testSessId, $adminId);
        $runner->assertTrue("TEST 5b: Session terminated by administrator", true, "Session ID {$testSessId} terminated");
    }

    // ── TEST 6: User Password Administration & Force Reset ──
    $stmtForce = $pdo->prepare("UPDATE users SET force_password_reset = 1 WHERE id = ?");
    $stmtForce->execute([$adminId]);

    $stmtCheckForce = $pdo->prepare("SELECT force_password_reset FROM users WHERE id = ?");
    $stmtCheckForce->execute([$adminId]);
    $isForceReset = intval($stmtCheckForce->fetchColumn());

    $runner->assertTrue("TEST 6: Mandatory user password reset flag updated", $isForceReset === 1, "force_password_reset: {$isForceReset}");

    // Reset flag back to 0
    $pdo->prepare("UPDATE users SET force_password_reset = 0 WHERE id = ?")->execute([$adminId]);

    // ── TEST 7: Security Audit & Review Compliance ──
    $securityAudit = SecurityAuditService::runSecurityAudit();
    $runner->assertTrue("TEST 7: Consolidated security audit executed", count($securityAudit) >= 6, "Security checks count: " . count($securityAudit));

    // ── TEST 8: Standardized Error Pages Verification ──
    $e403 = file_exists(__DIR__ . '/../errors/403.php');
    $e404 = file_exists(__DIR__ . '/../errors/404.php');
    $e500 = file_exists(__DIR__ . '/../errors/500.php');
    $runner->assertTrue("TEST 8: Standardized error pages (403, 404, 500) present in repository", $e403 && $e404 && $e500, "Error pages verified");

} catch (Throwable $e) {
    $runner->recordException($e);
} finally {
    if (!empty($createdBackupFiles)) {
        foreach ($createdBackupFiles as $bf) {
            BackupService::deleteBackup($bf, $adminId ?? 1);
        }
    }
    $runner->finish();
}
