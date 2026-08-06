<?php
require_once __DIR__ . '/app/database.php';
require_once __DIR__ . '/app/session.php';
require_once __DIR__ . '/includes/security.php';
require_once __DIR__ . '/app/services/SessionManagementService.php';
require_once __DIR__ . '/app/services/AuditLogService.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

$userId = $_SESSION['user_id'];
$pdo = getDBConnection();

$stmtUser = $pdo->prepare("SELECT id, fullname, username, email, role, password, force_password_reset FROM users WHERE id = ?");
$stmtUser->execute([$userId]);
$user = $stmtUser->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    session_unset();
    session_destroy();
    header("Location: index.php");
    exit();
}

// If password reset is not required, redirect to role dashboard
if (intval($user['force_password_reset']) !== 1) {
    if ($user['role'] === 'student') header("Location: student/dashboard.php");
    elseif ($user['role'] === 'teacher') header("Location: teacher/dashboard.php");
    elseif ($user['role'] === 'admin') header("Location: admin/dashboard.php");
    exit();
}

$msg = '';
$msgType = 'danger';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validateCSRFToken();
    $currentPass = $_POST['current_password'] ?? '';
    $newPass = $_POST['new_password'] ?? '';
    $confirmPass = $_POST['confirm_password'] ?? '';

    try {
        if (empty($currentPass) || empty($newPass) || empty($confirmPass)) {
            throw new InvalidArgumentException("All fields are required.");
        }
        if (strlen($newPass) < 8) {
            throw new InvalidArgumentException("New password must be at least 8 characters long.");
        }
        if ($newPass !== $confirmPass) {
            throw new InvalidArgumentException("New password and confirmation do not match.");
        }
        if (!password_verify($currentPass, $user['password'])) {
            throw new InvalidArgumentException("Current temporary or existing password verification failed.");
        }

        $newHash = password_hash($newPass, PASSWORD_DEFAULT);
        $stmtUpd = $pdo->prepare("UPDATE users SET password = ?, force_password_reset = 0, password_changed_at = NOW() WHERE id = ?");
        $stmtUpd->execute([$newHash, $userId]);

        regenerateSecureSession();
        SessionManagementService::trackSession($userId);
        AuditLogService::logAction($userId, "Completed Mandatory Password Reset", "Password reset successfully completed.");

        if ($user['role'] === 'student') header("Location: student/dashboard.php");
        elseif ($user['role'] === 'teacher') header("Location: teacher/dashboard.php");
        elseif ($user['role'] === 'admin') header("Location: admin/dashboard.php");
        exit();
    } catch (Exception $e) {
        $msg = $e->getMessage();
        $msgType = 'danger';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mandatory Password Reset - QuestBank</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        body { background-color: #f8f9fa; height: 100vh; display: flex; align-items: center; justify-content: center; }
        .reset-card { max-width: 450px; width: 100%; border: none; border-radius: 12px; box-shadow: 0 10px 30px rgba(0,0,0,0.08); }
    </style>
</head>
<body>
<div class="container py-5">
    <div class="card reset-card mx-auto">
        <div class="card-header bg-warning text-dark text-center py-3">
            <h5 class="card-title mb-0 font-weight-bold"><i class="fas fa-key me-2"></i>Mandatory Password Reset Required</h5>
        </div>
        <div class="card-body p-4">
            <p class="text-muted small mb-3">
                Hello <strong><?= htmlspecialchars($user['fullname']) ?></strong>, an administrator has flagged your account for a mandatory password reset. Please update your password to proceed.
            </p>

            <?php if (!empty($msg)): ?>
                <div class="alert alert-<?= $msgType ?> alert-dismissible fade show" role="alert">
                    <?= htmlspecialchars($msg) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <form method="POST">
                <?= csrfInputField() ?>
                <div class="mb-3">
                    <label class="form-label font-weight-bold">Current / Temporary Password</label>
                    <input type="password" name="current_password" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label font-weight-bold">New Password (Min 8 Characters)</label>
                    <input type="password" name="new_password" class="form-control" minlength="8" required>
                </div>
                <div class="mb-3">
                    <label class="form-label font-weight-bold">Confirm New Password</label>
                    <input type="password" name="confirm_password" class="form-control" minlength="8" required>
                </div>
                <button type="submit" class="btn btn-warning w-100 font-weight-bold mb-2"><i class="fas fa-lock me-1"></i>Update Password & Continue</button>
                <a href="/index.php?action=logout" class="btn btn-outline-secondary w-100 text-center text-muted border-0 small"><i class="fas fa-sign-out-alt me-1"></i>Cancel & Log Out</a>
            </form>
        </div>
    </div>
</div>
</body>
</html>
