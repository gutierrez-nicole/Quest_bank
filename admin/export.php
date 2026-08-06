<?php
require_once __DIR__ . '/../app/bootstrap.php';
require_once __DIR__ . '/../app/services/BulkExportService.php';

AuthService::enforceRole('admin');
$adminId = $_SESSION['user_id'];

$type = $_GET['type'] ?? '';
$format = $_GET['format'] ?? 'csv';

if (!empty($type)) {
    if ($type === 'schedules' && $format === 'pdf') {
        BulkExportService::exportSchedulesPDF($adminId);
    } else {
        BulkExportService::exportCSV($type, $adminId);
    }
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Bulk Export Center - QuestBank Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="h3 font-weight-bold"><i class="fas fa-file-export text-success me-2"></i>Bulk Export Center</h2>
            <p class="text-muted mb-0">Export system data in CSV and PDF formats</p>
        </div>
        <a href="dashboard.php" class="btn btn-outline-secondary"><i class="fas fa-arrow-left me-1"></i>Dashboard</a>
    </div>

    <div class="row g-4">
        <div class="col-md-4">
            <div class="card shadow-sm h-100">
                <div class="card-body text-center p-4">
                    <i class="fas fa-user-graduate fa-3x text-primary mb-3"></i>
                    <h4>Student Masterlist</h4>
                    <p class="text-muted">Export all registered student records with course & section details.</p>
                    <a href="export.php?type=students&format=csv" class="btn btn-primary w-100"><i class="fas fa-download me-1"></i>Export CSV</a>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card shadow-sm h-100">
                <div class="card-body text-center p-4">
                    <i class="fas fa-chalkboard-teacher fa-3x text-success mb-3"></i>
                    <h4>Teacher Masterlist</h4>
                    <p class="text-muted">Export active teacher accounts, emails, and registration timestamps.</p>
                    <a href="export.php?type=teachers&format=csv" class="btn btn-success w-100"><i class="fas fa-download me-1"></i>Export CSV</a>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card shadow-sm h-100">
                <div class="card-body text-center p-4">
                    <i class="fas fa-users-class fa-3x text-warning mb-3"></i>
                    <h4>Sections & Capacity</h4>
                    <p class="text-muted">Export section codes, advisers, student capacity, and status.</p>
                    <a href="export.php?type=sections&format=csv" class="btn btn-warning w-100"><i class="fas fa-download me-1"></i>Export CSV</a>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card shadow-sm h-100">
                <div class="card-body text-center p-4">
                    <i class="fas fa-file-alt fa-3x text-info mb-3"></i>
                    <h4>Exam Catalog</h4>
                    <p class="text-muted">Export exam metadata, subjects, teacher assignments, and time limits.</p>
                    <a href="export.php?type=exams&format=csv" class="btn btn-info text-white w-100"><i class="fas fa-download me-1"></i>Export CSV</a>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card shadow-sm h-100">
                <div class="card-body text-center p-4">
                    <i class="fas fa-calendar-alt fa-3x text-danger mb-3"></i>
                    <h4>Exam Timetable & Schedules</h4>
                    <p class="text-muted">Export upcoming scheduled exams with dates, rooms, and sections.</p>
                    <div class="d-flex gap-2">
                        <a href="export.php?type=schedules&format=csv" class="btn btn-outline-success w-50"><i class="fas fa-file-csv me-1"></i>CSV</a>
                        <a href="export.php?type=schedules&format=pdf" target="_blank" class="btn btn-danger w-50"><i class="fas fa-file-pdf me-1"></i>PDF</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
</body>
</html>
