<?php
require_once __DIR__ . '/../app/bootstrap.php';
require_once __DIR__ . '/../app/services/AuditLogService.php';

AuthService::enforceRole('admin');

$filters = [
    'action' => $_GET['action'] ?? 'all',
    'user_id' => $_GET['user_id'] ?? 'all'
];

$logs = AuditLogService::getLogs($filters, 200);
$pdo = getDBConnection();
$users = $pdo->query("SELECT id, fullname, role FROM users ORDER BY fullname ASC")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>System Audit Trail Logs - QuestBank Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="h3 font-weight-bold"><i class="fas fa-history text-secondary me-2"></i>System Audit Trail Logs</h2>
            <p class="text-muted mb-0">Track logins, publication transitions, administrative actions, and data changes</p>
        </div>
        <a href="dashboard.php" class="btn btn-outline-secondary"><i class="fas fa-arrow-left me-1"></i>Dashboard</a>
    </div>

    <div class="card shadow-sm">
        <div class="card-header bg-white">
            <form method="GET" class="row g-2 align-items-center">
                <div class="col-md-5">
                    <input type="text" name="action" class="form-control" placeholder="Search by Action Keyword..." value="<?= htmlspecialchars($filters['action'] !== 'all' ? $filters['action'] : '') ?>">
                </div>
                <div class="col-md-5">
                    <select name="user_id" class="form-select" onchange="this.form.submit()">
                        <option value="all">All Actors / Users</option>
                        <?php foreach ($users as $u): ?>
                            <option value="<?= $u['id'] ?>" <?= intval($filters['user_id']) === intval($u['id']) ? 'selected' : '' ?>><?= htmlspecialchars($u['fullname']) ?> (<?= ucfirst($u['role']) ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-secondary w-100"><i class="fas fa-filter me-1"></i>Filter</button>
                </div>
            </form>
        </div>
        <div class="card-body p-0">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>#</th>
                        <th>Timestamp</th>
                        <th>Actor</th>
                        <th>Action</th>
                        <th>Details</th>
                        <th>IP Address</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($logs as $l): ?>
                        <tr>
                            <td><?= $l['id'] ?></td>
                            <td><small class="text-muted"><i class="far fa-clock me-1"></i><?= htmlspecialchars($l['created_at']) ?></small></td>
                            <td>
                                <div class="fw-bold"><?= htmlspecialchars($l['actor_name'] ?? 'System') ?></div>
                                <small class="badge bg-light text-dark"><?= ucfirst($l['actor_role'] ?? 'system') ?></small>
                            </td>
                            <td><span class="badge bg-primary"><?= htmlspecialchars($l['action']) ?></span></td>
                            <td class="small"><?= htmlspecialchars($l['details']) ?></td>
                            <td><code><?= htmlspecialchars($l['ip_address'] ?? '127.0.0.1') ?></code></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($logs)): ?>
                        <tr><td colspan="6" class="text-center text-muted py-4">No audit log records found.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
</body>
</html>
