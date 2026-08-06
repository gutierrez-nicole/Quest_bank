<?php
require_once __DIR__ . '/../app/bootstrap.php';
require_once __DIR__ . '/../app/services/SecurityAuditService.php';
require_once __DIR__ . '/../app/services/AuditLogService.php';

AuthService::enforceRole('admin');
$adminId = $_SESSION['user_id'];

$msg = '';
$msgType = 'success';
$pdo = getDBConnection();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validateCSRFToken();
    $action = $_POST['action'] ?? '';

    try {
        if ($action === 'force_password_reset') {
            $targetUserId = intval($_POST['target_user_id'] ?? 0);
            $stmtReset = $pdo->prepare("UPDATE users SET force_password_reset = 1 WHERE id = ?");
            $stmtReset->execute([$targetUserId]);

            AuditLogService::logAction($adminId, "Admin Flagged Password Reset", "Target User ID: {$targetUserId}");
            $msg = "User ID #{$targetUserId} has been flagged for mandatory password reset on next login.";
            $msgType = 'warning';
        } elseif ($action === 'change_own_password') {
            $currentPass = $_POST['current_password'] ?? '';
            $newPass = $_POST['new_password'] ?? '';
            $confirmPass = $_POST['confirm_password'] ?? '';

            if (empty($currentPass) || empty($newPass) || strlen($newPass) < 8) {
                throw new InvalidArgumentException("Password must be at least 8 characters long.");
            }
            if ($newPass !== $confirmPass) {
                throw new InvalidArgumentException("New password and confirmation do not match.");
            }

            $stmtCheck = $pdo->prepare("SELECT password FROM users WHERE id = ?");
            $stmtCheck->execute([$adminId]);
            $hash = $stmtCheck->fetchColumn();

            if (!password_verify($currentPass, $hash)) {
                throw new InvalidArgumentException("Current password verification failed.");
            }

            $newHash = password_hash($newPass, PASSWORD_DEFAULT);
            $stmtUpd = $pdo->prepare("UPDATE users SET password = ?, force_password_reset = 0, password_changed_at = NOW() WHERE id = ?");
            $stmtUpd->execute([$newHash, $adminId]);

            AuditLogService::logAction($adminId, "Changed Self Password", "Password updated successfully.");
            $msg = "Your password has been changed successfully!";
            $msgType = 'success';
        }
    } catch (Exception $e) {
        $msg = $e->getMessage();
        $msgType = 'danger';
    }
}

$securityChecks = SecurityAuditService::runSecurityAudit();
$users = $pdo->query("SELECT id, username, fullname, email, role, force_password_reset, password_changed_at FROM users ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Security Review & Password Management - QuestBank Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="h3 font-weight-bold"><i class="fas fa-user-shield text-danger me-2"></i>Security Review & Password Management</h2>
            <p class="text-muted mb-0">System security audit compliance, password policy controls, & self-password updates</p>
        </div>
        <a href="dashboard.php" class="btn btn-outline-secondary"><i class="fas fa-arrow-left me-1"></i>Dashboard</a>
    </div>

    <?php if (!empty($msg)): ?>
        <div class="alert alert-<?= $msgType ?> alert-dismissible fade show" role="alert">
            <?= htmlspecialchars($msg) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Consolidated Security Audit Table -->
    <div class="card shadow-sm mb-4">
        <div class="card-header bg-white"><h5 class="card-title mb-0"><i class="fas fa-shield-alt text-primary me-2"></i>Consolidated Security Validation Audit</h5></div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Security Category</th>
                            <th>Status</th>
                            <th>Validation Summary</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($securityChecks as $s): ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($s['category']) ?></strong></td>
                                <td><span class="badge bg-<?= $s['status'] === 'PASS' ? 'success' : 'warning' ?>"><?= $s['status'] ?></span></td>
                                <td><?= htmlspecialchars($s['description']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Password Administration -->
        <div class="col-md-7 mb-4">
            <div class="card shadow-sm">
                <div class="card-header bg-white"><h5 class="card-title mb-0"><i class="fas fa-key text-warning me-2"></i>User Password Administration</h5></div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>User</th>
                                    <th>Role</th>
                                    <th>Last Password Change</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($users as $u): ?>
                                    <tr>
                                        <td>
                                            <strong><?= htmlspecialchars($u['fullname']) ?></strong><br>
                                            <small class="text-muted">@<?= htmlspecialchars($u['username']) ?></small>
                                        </td>
                                        <td><span class="badge bg-secondary"><?= ucfirst($u['role']) ?></span></td>
                                        <td><small><?= $u['password_changed_at'] ?: 'Not recorded' ?></small></td>
                                        <td>
                                            <?php if ($u['force_password_reset']): ?>
                                                <span class="badge bg-warning text-dark">Reset Flagged</span>
                                            <?php else: ?>
                                                <form method="POST" class="d-inline" onsubmit="return confirm('Require user to reset password on next login?');">
                                                    <?= csrfInputField() ?>
                                                    <input type="hidden" name="action" value="force_password_reset">
                                                    <input type="hidden" name="target_user_id" value="<?= $u['id'] ?>">
                                                    <button type="submit" class="btn btn-sm btn-outline-warning"><i class="fas fa-exclamation-triangle me-1"></i>Force Reset</button>
                                                </form>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Change Own Password -->
        <div class="col-md-5 mb-4">
            <div class="card shadow-sm">
                <div class="card-header bg-primary text-white"><h5 class="card-title mb-0"><i class="fas fa-lock me-2"></i>Change Administrator Password</h5></div>
                <div class="card-body">
                    <form method="POST">
                        <?= csrfInputField() ?>
                        <input type="hidden" name="action" value="change_own_password">
                        <div class="mb-3">
                            <label class="form-label">Current Password</label>
                            <input type="password" name="current_password" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">New Password (Min 8 chars)</label>
                            <input type="password" name="new_password" class="form-control" minlength="8" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Confirm New Password</label>
                            <input type="password" name="confirm_password" class="form-control" minlength="8" required>
                        </div>
                        <button type="submit" class="btn btn-primary w-100"><i class="fas fa-save me-1"></i>Update Password</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
