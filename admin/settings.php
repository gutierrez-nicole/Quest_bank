<?php
require_once __DIR__ . '/../app/bootstrap.php';
require_once __DIR__ . '/../app/services/SystemSettingsService.php';
require_once __DIR__ . '/../app/services/AuditLogService.php';

AuthService::enforceRole('admin');
$adminId = $_SESSION['user_id'];

$msg = '';
$msgType = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        SystemSettingsService::setSetting('school_name', $_POST['school_name'] ?? '');
        SystemSettingsService::setSetting('passing_percentage', $_POST['passing_percentage'] ?? '75.00');
        SystemSettingsService::setSetting('ocr_threshold', $_POST['ocr_threshold'] ?? '75.00');
        SystemSettingsService::setSetting('timezone', $_POST['timezone'] ?? 'Asia/Manila');
        SystemSettingsService::setSetting('maintenance_mode', $_POST['maintenance_mode'] ?? 'off');

        $msg = "System Settings updated successfully.";
        AuditLogService::logAction($adminId, "Updated System Settings", "Passing: {$_POST['passing_percentage']}%, OCR: {$_POST['ocr_threshold']}%");
    } catch (Exception $e) {
        $msg = $e->getMessage();
        $msgType = 'danger';
    }
}

$settings = SystemSettingsService::getAllSettings();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>System Settings - QuestBank Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="h3 font-weight-bold"><i class="fas fa-cog text-primary me-2"></i>System Configuration Settings</h2>
            <p class="text-muted mb-0">Manage global thresholds, institution info, and maintenance mode</p>
        </div>
        <a href="dashboard.php" class="btn btn-outline-secondary"><i class="fas fa-arrow-left me-1"></i>Dashboard</a>
    </div>

    <?php if (!empty($msg)): ?>
        <div class="alert alert-<?= $msgType ?> alert-dismissible fade show" role="alert">
            <?= htmlspecialchars($msg) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="row">
        <div class="col-md-8 mx-auto">
            <div class="card shadow-sm">
                <div class="card-header bg-white"><h5 class="card-title mb-0">Global Configuration Parameters</h5></div>
                <div class="card-body">
                    <form method="POST">
                        <div class="mb-3">
                            <label class="form-label font-weight-bold">Institution / School Name</label>
                            <input type="text" name="school_name" class="form-control" value="<?= htmlspecialchars($settings['school_name']) ?>" required>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label font-weight-bold">Passing Grade Percentage (%)</label>
                                <input type="number" step="0.1" name="passing_percentage" class="form-control" value="<?= htmlspecialchars($settings['passing_percentage']) ?>" min="0" max="100" required>
                                <small class="text-muted">Standard passing grade required for exams.</small>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label font-weight-bold">OCR Confidence Threshold (%)</label>
                                <input type="number" step="0.1" name="ocr_threshold" class="form-control" value="<?= htmlspecialchars($settings['ocr_threshold']) ?>" min="0" max="100" required>
                                <small class="text-muted">Minimum confidence required before flagging for manual review.</small>
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label font-weight-bold">System Timezone</label>
                                <select name="timezone" class="form-select">
                                    <option value="Asia/Manila" <?= $settings['timezone'] === 'Asia/Manila' ? 'selected' : '' ?>>Asia/Manila (PHT)</option>
                                    <option value="UTC" <?= $settings['timezone'] === 'UTC' ? 'selected' : '' ?>>UTC</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label font-weight-bold">Maintenance Mode</label>
                                <select name="maintenance_mode" class="form-select">
                                    <option value="off" <?= $settings['maintenance_mode'] === 'off' ? 'selected' : '' ?>>Off (System Active)</option>
                                    <option value="on" <?= $settings['maintenance_mode'] === 'on' ? 'selected' : '' ?>>On (Maintenance Mode)</option>
                                </select>
                            </div>
                        </div>
                        <button type="submit" class="btn btn-primary"><i class="fas fa-save me-1"></i>Save Configuration</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
</body>
</html>
