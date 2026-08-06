<?php
$startTime = microtime(true);

require_once __DIR__ . '/../app/bootstrap.php';
require_once __DIR__ . '/../app/services/SystemHealthService.php';
require_once __DIR__ . '/../app/services/StorageManagementService.php';

AuthService::enforceRole('admin');
$adminId = $_SESSION['user_id'];

$msg = '';
$msgType = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validateCSRFToken();
    $action = $_POST['action'] ?? '';

    try {
        if ($action === 'clean_temp') {
            $res = StorageManagementService::cleanTemporaryFiles($adminId);
            $msg = "Cleaned {$res['cleaned_count']} temporary files, freed {$res['freed_formatted']}.";
            $msgType = 'success';
        }
    } catch (Exception $e) {
        $msg = $e->getMessage();
        $msgType = 'danger';
    }
}

$diagnostics = SystemHealthService::getHealthDiagnostics();
$storage = StorageManagementService::getStorageOverview();
$checklistData = SystemHealthService::getDeploymentChecklist();
$checklist = $checklistData['items'];
$overallStatus = $checklistData['overall_status'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>System Health & Operations - QuestBank Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="h3 font-weight-bold"><i class="fas fa-heartbeat text-danger me-2"></i>System Health & Operations</h2>
            <p class="text-muted mb-0">Operational diagnostics, storage management, performance review, & deployment readiness</p>
        </div>
        <a href="dashboard.php" class="btn btn-outline-secondary"><i class="fas fa-arrow-left me-1"></i>Dashboard</a>
    </div>

    <?php if (!empty($msg)): ?>
        <div class="alert alert-<?= $msgType ?> alert-dismissible fade show" role="alert">
            <?= htmlspecialchars($msg) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- System Diagnostics Grid -->
    <div class="row mb-4">
        <?php foreach ($diagnostics as $key => $d): ?>
            <div class="col-md-4 mb-3">
                <div class="card h-100 shadow-sm border-0">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <h6 class="card-title font-weight-bold mb-0"><?= htmlspecialchars($d['label']) ?></h6>
                            <span class="badge bg-<?= $d['status'] === 'PASS' ? 'success' : ($d['status'] === 'WARNING' ? 'warning' : 'danger') ?>">
                                <?= $d['status'] ?>
                            </span>
                        </div>
                        <p class="card-text text-muted small mb-0"><?= htmlspecialchars($d['details']) ?></p>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <div class="row">
        <!-- Storage Overview & Maintenance -->
        <div class="col-md-6 mb-4">
            <div class="card shadow-sm h-100">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0"><i class="fas fa-hdd text-primary me-2"></i>Storage Management</h5>
                    <form method="POST" class="d-inline">
                        <?= csrfInputField() ?>
                        <input type="hidden" name="action" value="clean_temp">
                        <button type="submit" class="btn btn-sm btn-outline-danger"><i class="fas fa-broom me-1"></i>Clean Temp Files</button>
                    </form>
                </div>
                <div class="card-body">
                    <ul class="list-group list-group-flush mb-3">
                        <li class="list-group-item d-flex justify-content-between align-items-center py-2">
                            <span><i class="fas fa-book text-info me-2"></i>Active Lesson Materials</span>
                            <span class="badge bg-primary rounded-pill"><?= $storage['lessons']['count'] ?> files (<?= $storage['lessons']['size_formatted'] ?>)</span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-center py-2">
                            <span><i class="fas fa-file-signature text-secondary me-2"></i>OCR Exam Submissions</span>
                            <span class="badge bg-secondary rounded-pill"><?= $storage['submissions']['count'] ?> files (<?= $storage['submissions']['size_formatted'] ?>)</span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-center py-2">
                            <span><i class="fas fa-database text-success me-2"></i>Database Backups</span>
                            <span class="badge bg-success rounded-pill"><?= $storage['backups']['count'] ?> files (<?= $storage['backups']['size_formatted'] ?>)</span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-center py-2">
                            <span><i class="fas fa-broom text-warning me-2"></i>Temporary Preview Files</span>
                            <span class="badge bg-warning text-dark rounded-pill"><?= $storage['temporary']['count'] ?> files (<?= $storage['temporary']['size_formatted'] ?>)</span>
                        </li>
                    </ul>
                    <a href="files.php" class="btn btn-sm btn-outline-primary w-100"><i class="fas fa-folder-open me-1"></i>Open File Manager & Orphaned Cleanup</a>
                </div>
            </div>
        </div>

        <!-- Performance & Diagnostics -->
        <div class="col-md-6 mb-4">
            <div class="card shadow-sm h-100">
                <div class="card-header bg-white"><h5 class="card-title mb-0"><i class="fas fa-tachometer-alt text-success me-2"></i>Performance Review</h5></div>
                <div class="card-body">
                    <div class="row text-center mb-3">
                        <div class="col-4 border-end">
                            <div class="text-muted small">Page Generation</div>
                            <h4 class="fw-bold text-primary mb-0"><?= $pageGenTime ?> ms</h4>
                        </div>
                        <div class="col-4 border-end">
                            <div class="text-muted small">Memory Usage</div>
                            <h4 class="fw-bold text-info mb-0"><?= $memUsage ?> MB</h4>
                        </div>
                        <div class="col-4">
                            <div class="text-muted small">Peak Memory</div>
                            <h4 class="fw-bold text-secondary mb-0"><?= $peakMemUsage ?> MB</h4>
                        </div>
                    </div>
                    <hr>
                    <div class="small text-muted">
                        <div><strong>PHP Engine:</strong> PHP v<?= PHP_VERSION ?> (<?= PHP_SAPI ?>)</div>
                        <div><strong>Database Driver:</strong> PDO MySQL (Buffered Queries Enabled)</div>
                        <div><strong>Execution Time Limit:</strong> <?= ini_get('max_execution_time') ?>s</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Final Deployment Checklist Card -->
    <div class="row">
        <div class="col-md-12">
            <div class="card shadow-sm">
                <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0"><i class="fas fa-clipboard-check me-2"></i>Final Deployment Readiness Checklist</h5>
                    <span class="badge bg-<?= $overallStatus === 'PASS' ? 'success' : ($overallStatus === 'WARNING' ? 'warning' : 'danger') ?> fs-6">
                        OVERALL STATUS: <?= $overallStatus ?>
                    </span>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Checklist Item</th>
                                    <th>Status</th>
                                    <th>Verification Details</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($checklist as $c): ?>
                                    <tr>
                                        <td><strong><?= htmlspecialchars($c['label']) ?></strong></td>
                                        <td>
                                            <span class="badge bg-<?= $c['status'] === 'PASS' ? 'success' : ($c['status'] === 'WARNING' ? 'warning' : 'danger') ?>">
                                                <?= $c['status'] ?>
                                            </span>
                                        </td>
                                        <td><?= htmlspecialchars($c['details']) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
