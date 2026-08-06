<?php
require_once __DIR__ . '/../app/bootstrap.php';
require_once __DIR__ . '/../app/services/BackupService.php';

AuthService::enforceRole('admin');
$adminId = $_SESSION['user_id'];

$msg = '';
$msgType = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validateCSRFToken();
    $action = $_POST['action'] ?? '';

    try {
        if ($action === 'create_backup') {
            $backup = BackupService::createBackup($adminId);
            $msg = "Database Backup '{$backup['filename']}' created successfully ({$backup['size_formatted']}).";
            $msgType = 'success';
        } elseif ($action === 'restore_backup') {
            $filename = $_POST['filename'] ?? '';
            $phrase = $_POST['confirm_phrase'] ?? '';
            $res = BackupService::restoreBackup($filename, $adminId, $phrase);
            $msg = "Database successfully restored from backup '{$filename}'. Fresh safety backup '{$res['safety_backup']}' was generated before restore.";
            $msgType = 'success';
        } elseif ($action === 'delete_backup') {
            $filename = $_POST['filename'] ?? '';
            if (BackupService::deleteBackup($filename, $adminId)) {
                $msg = "Backup file '{$filename}' deleted.";
                $msgType = 'warning';
            }
        }
    } catch (Exception $e) {
        $msg = $e->getMessage();
        $msgType = 'danger';
    }
}

// Handle Direct Download Request
if (isset($_GET['action']) && $_GET['action'] === 'download') {
    $filename = $_GET['file'] ?? '';
    $backupDir = BackupService::getBackupDir();
    $filePath = $backupDir . '/' . basename($filename);
    $realPath = realpath($filePath);

    if (BackupService::isValidBackupFilename($filename) && $realPath && strpos($realPath, $backupDir) === 0 && file_exists($realPath)) {
        AuditLogService::logAction($adminId, "Downloaded Database Backup", "File: {$filename}");
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . basename($filename) . '"');
        header('Content-Length: ' . filesize($realPath));
        readfile($realPath);
        exit;
    } else {
        renderErrorPage(403, "Access Denied: Invalid backup file specified for download.");
    }
}

$backups = BackupService::listBackups();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Database Backup & Restore - QuestBank Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="h3 font-weight-bold"><i class="fas fa-database text-primary me-2"></i>Database Backup & Restore</h2>
            <p class="text-muted mb-0">Create, download, preview, and restore system database backups</p>
        </div>
        <div class="d-flex gap-2">
            <form method="POST" class="d-inline">
                <?= csrfInputField() ?>
                <input type="hidden" name="action" value="create_backup">
                <button type="submit" class="btn btn-primary fw-bold"><i class="fas fa-plus-circle me-1"></i>Create New Backup</button>
            </form>
            <a href="dashboard.php" class="btn btn-outline-secondary"><i class="fas fa-arrow-left me-1"></i>Dashboard</a>
        </div>
    </div>

    <?php if (!empty($msg)): ?>
        <div class="alert alert-<?= $msgType ?> alert-dismissible fade show" role="alert">
            <?= htmlspecialchars($msg) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="row">
        <div class="col-md-12">
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0">Available System Backups (<?= count($backups) ?>)</h5>
                    <small class="text-muted"><i class="fas fa-shield-alt me-1 text-success"></i>Protected Directory Access</small>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>#</th>
                                    <th>Backup Filename</th>
                                    <th>Size</th>
                                    <th>Created Timestamp</th>
                                    <th class="text-end">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($backups as $idx => $b): ?>
                                    <tr>
                                        <td><?= $idx + 1 ?></td>
                                        <td><code><?= htmlspecialchars($b['filename']) ?></code></td>
                                        <td><span class="badge bg-secondary"><?= $b['size_formatted'] ?></span></td>
                                        <td><?= $b['created_at'] ?></td>
                                        <td class="text-end">
                                            <a href="backup.php?action=download&file=<?= urlencode($b['filename']) ?>" class="btn btn-sm btn-outline-primary me-1" title="Download Backup">
                                                <i class="fas fa-download"></i> Download
                                            </a>
                                            <button type="button" class="btn btn-sm btn-outline-warning me-1" data-bs-toggle="modal" data-bs-target="#restoreModal<?= $idx ?>">
                                                <i class="fas fa-undo"></i> Restore
                                            </button>
                                            <form method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this backup file?');">
                                                <?= csrfInputField() ?>
                                                <input type="hidden" name="action" value="delete_backup">
                                                <input type="hidden" name="filename" value="<?= htmlspecialchars($b['filename']) ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-danger"><i class="fas fa-trash"></i></button>
                                            </form>
                                        </td>
                                    </tr>

                                    <!-- Restore Confirmation Modal -->
                                    <div class="modal fade" id="restoreModal<?= $idx ?>" tabindex="-1">
                                        <div class="modal-dialog">
                                            <div class="modal-content">
                                                <div class="modal-header bg-warning text-dark">
                                                    <h5 class="modal-title"><i class="fas fa-exclamation-triangle me-2"></i>Confirm Database Restore</h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                </div>
                                                <div class="modal-body">
                                                    <p>Are you sure you want to restore the database from this backup file?</p>
                                                    <div class="alert alert-light border small font-monospace">
                                                        <div><strong>Filename:</strong> <?= htmlspecialchars($b['filename']) ?></div>
                                                        <div><strong>Created:</strong> <?= $b['created_at'] ?></div>
                                                        <div><strong>Size:</strong> <?= $b['size_formatted'] ?></div>
                                                        <div><strong>SHA-256:</strong> <?= $b['sha256'] ?></div>
                                                        <div><strong>Tables:</strong> <?= $b['table_count'] ?> tables</div>
                                                    </div>
                                                    <p class="text-danger small mb-2"><strong>Warning:</strong> A safety backup will be created automatically before restoring.</p>
                                                    <form method="POST">
                                                        <?= csrfInputField() ?>
                                                        <input type="hidden" name="action" value="restore_backup">
                                                        <input type="hidden" name="filename" value="<?= htmlspecialchars($b['filename']) ?>">
                                                        <div class="mb-3">
                                                            <label class="form-label small fw-bold">Type "RESTORE" to confirm:</label>
                                                            <input type="text" name="confirm_phrase" class="form-control form-control-sm" placeholder="RESTORE" required>
                                                        </div>
                                                        <div class="d-flex justify-content-end gap-2">
                                                            <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                                                            <button type="submit" class="btn btn-warning btn-sm font-weight-bold">Confirm & Execute Restore</button>
                                                        </div>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                                <?php if (empty($backups)): ?>
                                    <tr><td colspan="5" class="text-center text-muted py-4">No database backups found. Click "Create New Backup" above.</td></tr>
                                <?php endif; ?>
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
