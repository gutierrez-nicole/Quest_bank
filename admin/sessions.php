<?php
require_once __DIR__ . '/../app/bootstrap.php';
require_once __DIR__ . '/../app/services/SessionManagementService.php';

AuthService::enforceRole('admin');
$adminId = $_SESSION['user_id'];

$msg = '';
$msgType = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validateCSRFToken();
    $action = $_POST['action'] ?? '';

    try {
        if ($action === 'terminate_session') {
            $sessId = $_POST['session_id'] ?? '';
            SessionManagementService::terminateSession($sessId, $adminId);
            $msg = "Session terminated successfully.";
            $msgType = 'warning';
        } elseif ($action === 'terminate_user') {
            $targetUserId = intval($_POST['user_id'] ?? 0);
            SessionManagementService::terminateUserSessions($targetUserId, $adminId);
            $msg = "All sessions for User ID #{$targetUserId} terminated.";
            $msgType = 'warning';
        }
    } catch (Exception $e) {
        $msg = $e->getMessage();
        $msgType = 'danger';
    }
}

// Track current admin session
SessionManagementService::trackSession($adminId);

$sessions = SessionManagementService::getActiveSessions();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Active Sessions & User Logout - QuestBank Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="h3 font-weight-bold"><i class="fas fa-users-cog text-primary me-2"></i>Active Session Manager</h2>
            <p class="text-muted mb-0">Monitor active user sessions and force logout users if necessary</p>
        </div>
        <a href="dashboard.php" class="btn btn-outline-secondary"><i class="fas fa-arrow-left me-1"></i>Dashboard</a>
    </div>

    <?php if (!empty($msg)): ?>
        <div class="alert alert-<?= $msgType ?> alert-dismissible fade show" role="alert">
            <?= htmlspecialchars($msg) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="card shadow-sm mb-4">
        <div class="card-header bg-white d-flex justify-content-between align-items-center">
            <h5 class="card-title mb-0">Active User Sessions (<?= count($sessions) ?>)</h5>
            <small class="text-muted"><i class="fas fa-user-shield text-success me-1"></i>Session Tokens Omitted</small>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>User</th>
                            <th>Role</th>
                            <th>IP Address</th>
                            <th>Login Time</th>
                            <th>Last Activity</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($sessions as $s): ?>
                            <tr>
                                <td>
                                    <strong><?= htmlspecialchars($s['fullname']) ?></strong><br>
                                    <small class="text-muted">@<?= htmlspecialchars($s['username']) ?> (ID: #<?= $s['user_id'] ?>)</small>
                                </td>
                                <td><span class="badge bg-<?= $s['role'] === 'admin' ? 'danger' : ($s['role'] === 'teacher' ? 'primary' : 'secondary') ?>"><?= ucfirst($s['role']) ?></span></td>
                                <td><code><?= htmlspecialchars($s['ip_address']) ?></code></td>
                                <td><?= $s['login_time'] ?></td>
                                <td><?= $s['last_activity'] ?></td>
                                <td class="text-end">
                                    <form method="POST" class="d-inline" onsubmit="return confirm('Force logout this user session?');">
                                        <?= csrfInputField() ?>
                                        <input type="hidden" name="action" value="terminate_session">
                                        <input type="hidden" name="session_id" value="<?= htmlspecialchars($s['session_id']) ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-danger me-1"><i class="fas fa-power-off me-1"></i>End Session</button>
                                    </form>
                                    <form method="POST" class="d-inline" onsubmit="return confirm('Force logout ALL active sessions for this user?');">
                                        <?= csrfInputField() ?>
                                        <input type="hidden" name="action" value="terminate_user">
                                        <input type="hidden" name="user_id" value="<?= $s['user_id'] ?>">
                                        <button type="submit" class="btn btn-sm btn-danger"><i class="fas fa-user-slash me-1"></i>Logout User</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (empty($sessions)): ?>
                            <tr><td colspan="6" class="text-center text-muted py-4">No active user sessions recorded.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
