<?php
require_once __DIR__ . '/../app/bootstrap.php';
require_once __DIR__ . '/../app/services/StorageManagementService.php';

AuthService::enforceRole('admin');
$adminId = $_SESSION['user_id'];

$msg = '';
$msgType = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validateCSRFToken();
    $action = $_POST['action'] ?? '';

    try {
        if ($action === 'delete_orphaned') {
            $filesToDelete = $_POST['orphaned_files'] ?? [];
            if (!empty($filesToDelete)) {
                $res = StorageManagementService::deleteOrphanedFiles($filesToDelete, $adminId);
                $msg = "Successfully deleted {$res['deleted_count']} unreferenced files ({$res['freed_formatted']}).";
                $msgType = 'success';
            } else {
                $msg = "No files selected for deletion.";
                $msgType = 'warning';
            }
        } elseif ($action === 'clean_temp') {
            $res = StorageManagementService::cleanTemporaryFiles($adminId);
            $msg = "Cleaned {$res['cleaned_count']} temporary files ({$res['freed_formatted']}).";
            $msgType = 'success';
        }
    } catch (Exception $e) {
        $msg = $e->getMessage();
        $msgType = 'danger';
    }
}

$storage = StorageManagementService::getStorageOverview();
$orphaned = StorageManagementService::listOrphanedFiles();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Lightweight File Manager - QuestBank Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="h3 font-weight-bold"><i class="fas fa-folder-open text-primary me-2"></i>Lightweight File Manager</h2>
            <p class="text-muted mb-0">Manage system storage, clean temporary preview files, & remove unreferenced orphaned files</p>
        </div>
        <div class="d-flex gap-2">
            <form method="POST" class="d-inline">
                <?= csrfInputField() ?>
                <input type="hidden" name="action" value="clean_temp">
                <button type="submit" class="btn btn-warning fw-bold"><i class="fas fa-broom me-1"></i>Clean Temp Files</button>
            </form>
            <a href="health.php" class="btn btn-outline-secondary"><i class="fas fa-heartbeat me-1"></i>Health Dashboard</a>
        </div>
    </div>

    <?php if (!empty($msg)): ?>
        <div class="alert alert-<?= $msgType ?> alert-dismissible fade show" role="alert">
            <?= htmlspecialchars($msg) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Storage Summary Cards -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card shadow-sm text-center">
                <div class="card-body">
                    <h6 class="text-muted">Lessons Storage</h6>
                    <h3 class="fw-bold text-primary mb-0"><?= $storage['lessons']['size_formatted'] ?></h3>
                    <small class="text-muted"><?= $storage['lessons']['count'] ?> active files</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card shadow-sm text-center">
                <div class="card-body">
                    <h6 class="text-muted">OCR Submissions</h6>
                    <h3 class="fw-bold text-secondary mb-0"><?= $storage['submissions']['size_formatted'] ?></h3>
                    <small class="text-muted"><?= $storage['submissions']['count'] ?> files</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card shadow-sm text-center">
                <div class="card-body">
                    <h6 class="text-muted">Database Backups</h6>
                    <h3 class="fw-bold text-success mb-0"><?= $storage['backups']['size_formatted'] ?></h3>
                    <small class="text-muted"><?= $storage['backups']['count'] ?> files</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card shadow-sm text-center">
                <div class="card-body">
                    <h6 class="text-muted">Temporary Files</h6>
                    <h3 class="fw-bold text-warning mb-0"><?= $storage['temporary']['size_formatted'] ?></h3>
                    <small class="text-muted"><?= $storage['temporary']['count'] ?> files</small>
                </div>
            </div>
        </div>
    </div>

    <!-- Orphaned Files Table -->
    <div class="card shadow-sm mb-4">
        <div class="card-header bg-white d-flex justify-content-between align-items-center">
            <h5 class="card-title mb-0"><i class="fas fa-trash-alt text-danger me-2"></i>Unreferenced Orphaned Files (<?= count($orphaned) ?>)</h5>
            <small class="text-muted">Files present on disk but absent from database metadata</small>
        </div>
        <div class="card-body p-0">
            <form method="POST" onsubmit="return confirm('Are you sure you want to permanently delete selected unreferenced files?');">
                <?= csrfInputField() ?>
                <input type="hidden" name="action" value="delete_orphaned">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th style="width: 40px;"><input type="checkbox" id="selectAll"></th>
                                <th>Filename</th>
                                <th>Disk Path</th>
                                <th>Size</th>
                                <th>Last Modified</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($orphaned as $o): ?>
                                <tr>
                                    <td><input type="checkbox" name="orphaned_files[]" value="<?= htmlspecialchars($o['file_path']) ?>" class="file-chk"></td>
                                    <td><code><?= htmlspecialchars($o['filename']) ?></code></td>
                                    <td class="small text-muted"><?= htmlspecialchars($o['file_path']) ?></td>
                                    <td><span class="badge bg-secondary"><?= $o['size_formatted'] ?></span></td>
                                    <td><?= $o['modified_at'] ?></td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (empty($orphaned)): ?>
                                <tr><td colspan="5" class="text-center text-muted py-4"><i class="fas fa-check-circle text-success me-1"></i>No orphaned unreferenced files found. Storage is clean!</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                <?php if (!empty($orphaned)): ?>
                    <div class="p-3 bg-light border-top text-end">
                        <button type="submit" class="btn btn-danger font-weight-bold"><i class="fas fa-trash me-1"></i>Delete Selected Orphaned Files</button>
                    </div>
                <?php endif; ?>
            </form>
        </div>
    </div>
</div>
<script>
document.getElementById('selectAll')?.addEventListener('change', function() {
    document.querySelectorAll('.file-chk').forEach(c => c.checked = this.checked);
});
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
