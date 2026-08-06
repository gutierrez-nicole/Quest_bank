<?php
require_once __DIR__ . '/../app/bootstrap.php';

AuthService::enforceRole('admin');

$docPath = __DIR__ . '/../DOCUMENTATION.md';
$docContent = file_exists($docPath) ? file_get_contents($docPath) : 'Documentation file not found.';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Administrator Documentation - QuestBank Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="h3 font-weight-bold"><i class="fas fa-book-reader text-primary me-2"></i>Administrator Documentation</h2>
            <p class="text-muted mb-0">Operations manual, backup/restore procedures, & deployment guidelines</p>
        </div>
        <a href="dashboard.php" class="btn btn-outline-secondary"><i class="fas fa-arrow-left me-1"></i>Dashboard</a>
    </div>

    <div class="card shadow-sm">
        <div class="card-header bg-white"><h5 class="card-title mb-0">System Operations Manual (DOCUMENTATION.md)</h5></div>
        <div class="card-body">
            <pre class="bg-dark text-light p-4 rounded small mb-0" style="white-space: pre-wrap; font-family: monospace; max-height: 600px; overflow-y: auto;"><?= htmlspecialchars($docContent) ?></pre>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
