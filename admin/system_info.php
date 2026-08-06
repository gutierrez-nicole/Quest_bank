<?php
require_once __DIR__ . '/../app/bootstrap.php';
require_once __DIR__ . '/../app/services/AcademicStructureService.php';

AuthService::enforceRole('admin');

$pdo = getDBConnection();
$mysqlVer = $pdo->query("SELECT VERSION()")->fetchColumn();
$activeSy = AcademicStructureService::getActiveSchoolYear();
$activeSem = AcademicStructureService::getActiveSemester();

$gitHash = @exec('git rev-parse --short HEAD 2>/dev/null') ?: '2.2-PROD-RELEASE';
$buildDate = date('Y-m-d H:i:s', filemtime(__DIR__ . '/../app/bootstrap.php'));

$modules = [
    ['name' => 'Priority 1: Core AI Question Generation', 'status' => 'Active & Certified', 'icon' => 'fa-robot', 'badge' => 'bg-success'],
    ['name' => 'Priority 2: Practical Question Types & Analytics', 'status' => 'Active & Certified', 'icon' => 'fa-tasks', 'badge' => 'bg-success'],
    ['name' => 'Priority 3: Publication Safeguards & OCR Audit', 'status' => 'Active & Certified', 'icon' => 'fa-shield-alt', 'badge' => 'bg-success'],
    ['name' => 'Priority 4: Academic Structure & Scheduling', 'status' => 'Active & Certified', 'icon' => 'fa-calendar-alt', 'badge' => 'bg-success'],
    ['name' => 'Priority 5: Operations, Health & Backup', 'status' => 'Active & Certified', 'icon' => 'fa-tools', 'badge' => 'bg-success'],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>System Information & About - QuestBank Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="h3 font-weight-bold"><i class="fas fa-info-circle text-info me-2"></i>System Information & About</h2>
            <p class="text-muted mb-0">System specifications, application version metadata, and active module inventory</p>
        </div>
        <a href="dashboard.php" class="btn btn-outline-secondary"><i class="fas fa-arrow-left me-1"></i>Dashboard</a>
    </div>

    <div class="row">
        <!-- Application Meta -->
        <div class="col-md-6 mb-4">
            <div class="card shadow-sm h-100">
                <div class="card-header bg-white"><h5 class="card-title mb-0">Application Specifications</h5></div>
                <div class="card-body">
                    <table class="table table-sm borderless mb-0">
                        <tbody>
                            <tr><th>Application Name:</th><td>QuestBank Capstone Portal</td></tr>
                            <tr><th>System Version:</th><td><span class="badge bg-primary">v2.2-PROD</span></td></tr>
                            <tr><th>Build Date:</th><td><?= $buildDate ?></td></tr>
                            <tr><th>Repository Commit:</th><td><code><?= htmlspecialchars($gitHash) ?></code></td></tr>
                            <tr><th>PHP Version:</th><td>v<?= PHP_VERSION ?></td></tr>
                            <tr><th>MySQL Version:</th><td>v<?= htmlspecialchars($mysqlVer) ?></td></tr>
                            <tr><th>Active School Year:</th><td><?= htmlspecialchars($activeSy['school_year'] ?? 'None Configured') ?></td></tr>
                            <tr><th>Active Semester:</th><td><?= htmlspecialchars($activeSem['semester_name'] ?? 'None Configured') ?></td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Enabled Modules -->
        <div class="col-md-6 mb-4">
            <div class="card shadow-sm h-100">
                <div class="card-header bg-white"><h5 class="card-title mb-0">Enabled System Modules</h5></div>
                <div class="card-body p-0">
                    <ul class="list-group list-group-flush">
                        <?php foreach ($modules as $m): ?>
                            <li class="list-group-item d-flex justify-content-between align-items-center py-3">
                                <div>
                                    <i class="fas <?= $m['icon'] ?> text-primary me-2"></i>
                                    <strong><?= htmlspecialchars($m['name']) ?></strong>
                                </div>
                                <span class="badge <?= $m['badge'] ?>"><?= $m['status'] ?></span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
