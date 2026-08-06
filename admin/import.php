<?php
require_once __DIR__ . '/../app/bootstrap.php';
require_once __DIR__ . '/../app/services/BulkImportService.php';

AuthService::enforceRole('admin');
$adminId = $_SESSION['user_id'];

$result = null;
$msg = '';
$msgType = 'info';
$activePreviewBatch = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validateCSRFToken();
    $action = $_POST['action'] ?? 'preview';

    if ($action === 'preview') {
        $importType = $_POST['import_type'] ?? 'students';
        if (isset($_FILES['csv_file']) && $_FILES['csv_file']['error'] === UPLOAD_ERR_OK) {
            try {
                $tmpPath = $_FILES['csv_file']['tmp_name'];
                
                // Store temporary copy for execution phase
                $batchId = bin2hex(random_bytes(16));
                $targetFile = sys_get_temp_dir() . "/qb_batch_{$batchId}.csv";
                copy($tmpPath, $targetFile);

                $result = BulkImportService::processCSV($targetFile, $importType, false, $adminId);
                
                $_SESSION['bulk_import_batch'] = [
                    'batch_id' => $batchId,
                    'file_path' => $targetFile,
                    'type' => $importType,
                    'valid_count' => $result['valid_rows_count'],
                    'invalid_count' => $result['invalid_rows_count']
                ];
                $activePreviewBatch = $_SESSION['bulk_import_batch'];

                $msg = "CSV Validation Preview Complete: {$result['valid_rows_count']} valid rows, {$result['invalid_rows_count']} invalid/duplicate rows.";
                $msgType = $result['invalid_rows_count'] > 0 ? 'warning' : 'success';
            } catch (Exception $e) {
                $msg = $e->getMessage();
                $msgType = 'danger';
            }
        } else {
            $msg = "Please upload a valid CSV file.";
            $msgType = 'danger';
        }
    } elseif ($action === 'execute') {
        $batch = $_SESSION['bulk_import_batch'] ?? null;
        $postedBatchId = $_POST['batch_id'] ?? '';

        if (!$batch || $batch['batch_id'] !== $postedBatchId || !file_exists($batch['file_path'])) {
            $msg = "Import session expired or invalid batch ID. Please re-upload your CSV.";
            $msgType = 'danger';
        } else {
            try {
                $result = BulkImportService::processCSV($batch['file_path'], $batch['type'], true, $adminId);
                @unlink($batch['file_path']);
                unset($_SESSION['bulk_import_batch']);

                $msg = "Successfully executed import: {$result['imported_count']} {$result['type']} records created!";
                $msgType = 'success';
            } catch (Exception $e) {
                $msg = $e->getMessage();
                $msgType = 'danger';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Bulk CSV Import - QuestBank Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="h3 font-weight-bold"><i class="fas fa-file-import text-primary me-2"></i>Bulk CSV Data Import</h2>
            <p class="text-muted mb-0">Import Students, Teachers, Sections, or Subjects with full validation & preview</p>
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
        <div class="col-md-4">
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-primary text-white"><h5 class="card-title mb-0">Upload CSV File for Preview</h5></div>
                <div class="card-body">
                    <form method="POST" enctype="multipart/form-data">
                        <?= csrfInputField() ?>
                        <input type="hidden" name="action" value="preview">
                        <div class="mb-3">
                            <label class="form-label">Select Import Entity</label>
                            <select name="import_type" class="form-select" required>
                                <option value="students">Students</option>
                                <option value="teachers">Teachers</option>
                                <option value="sections">Sections</option>
                                <option value="subjects">Subjects</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">CSV File (Max 5MB)</label>
                            <input type="file" name="csv_file" class="form-control" accept=".csv" required>
                        </div>
                        <button type="submit" class="btn btn-primary w-100"><i class="fas fa-search me-1"></i>Validate & Preview CSV</button>
                    </form>
                </div>
            </div>

            <div class="card shadow-sm">
                <div class="card-header bg-light"><h6 class="card-title mb-0">CSV Format Instructions</h6></div>
                <div class="card-body small">
                    <p class="mb-1"><strong>Students CSV:</strong> <code>student_number, fullname, email, course, section</code></p>
                    <p class="mb-1"><strong>Teachers CSV:</strong> <code>fullname, email, username</code></p>
                    <p class="mb-1"><strong>Sections CSV:</strong> <code>section_code, capacity, course</code></p>
                    <p class="mb-0"><strong>Subjects CSV:</strong> <code>code, title</code></p>
                </div>
            </div>
        </div>

        <div class="col-md-8">
            <?php if ($result): ?>
                <div class="card shadow-sm mb-4">
                    <div class="card-header bg-white d-flex justify-content-between align-items-center">
                        <h5 class="card-title mb-0">Validation & Preview Summary</h5>
                        <div>
                            <span class="badge bg-success me-2">Valid: <?= $result['valid_rows_count'] ?></span>
                            <span class="badge bg-danger">Invalid/Duplicate: <?= $result['invalid_rows_count'] ?></span>
                        </div>
                    </div>
                    <div class="card-body p-0">
                        <?php if (!empty($result['invalid_rows'])): ?>
                            <div class="p-3 bg-light border-bottom">
                                <h6 class="text-danger mb-2"><i class="fas fa-exclamation-triangle me-1"></i>Invalid / Duplicate Rows Report (<?= count($result['invalid_rows']) ?> errors)</h6>
                                <ul class="list-group list-group-flush small">
                                    <?php foreach ($result['invalid_rows'] as $inv): ?>
                                        <li class="list-group-item bg-transparent text-danger py-1">
                                            <strong>Row <?= $inv['row'] ?>:</strong> <?= htmlspecialchars($inv['error']) ?>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endif; ?>

                        <?php if ($activePreviewBatch && $result['valid_rows_count'] > 0): ?>
                            <div class="p-3 bg-primary bg-opacity-10 border-bottom d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="mb-0 text-primary fw-bold"><i class="fas fa-check-circle me-1"></i>Ready for Execution</h6>
                                    <small class="text-muted">Review the valid rows below before confirming database insertion.</small>
                                </div>
                                <form method="POST" class="d-inline">
                                    <?= csrfInputField() ?>
                                    <input type="hidden" name="action" value="execute">
                                    <input type="hidden" name="batch_id" value="<?= htmlspecialchars($activePreviewBatch['batch_id']) ?>">
                                    <button type="submit" class="btn btn-success fw-bold"><i class="fas fa-play me-1"></i>Confirm & Execute Import (<?= $result['valid_rows_count'] ?> Rows)</button>
                                </form>
                            </div>
                        <?php endif; ?>

                        <?php if (!empty($result['credentials'])): ?>
                            <div class="p-3 bg-success bg-opacity-10 border-bottom">
                                <h6 class="text-success fw-bold mb-2"><i class="fas fa-key me-1"></i>Generated Temporary Credentials (Save or Share with Users)</h6>
                                <div class="table-responsive">
                                    <table class="table table-sm table-bordered bg-white mb-0">
                                        <thead>
                                            <tr><th>User Name</th><th>Email</th><th>Role</th><th>Temporary Password</th></tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($result['credentials'] as $cred): ?>
                                                <tr>
                                                    <td><code><?= htmlspecialchars($cred['username']) ?></code></td>
                                                    <td><?= htmlspecialchars($cred['email']) ?></td>
                                                    <td><span class="badge bg-secondary"><?= ucfirst($cred['role']) ?></span></td>
                                                    <td><code class="text-primary fw-bold"><?= htmlspecialchars($cred['temp_password']) ?></code></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        <?php endif; ?>

                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>#</th>
                                        <th>Valid Row Preview Data</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($result['valid_rows'] as $idx => $vr): ?>
                                        <tr>
                                            <td><?= $idx + 1 ?></td>
                                            <td class="small font-monospace"><?= htmlspecialchars(json_encode($vr)) ?></td>
                                            <td><span class="badge bg-success">Ready to Import</span></td>
                                        </tr>
                                    <?php endforeach; ?>
                                    <?php if (empty($result['valid_rows'])): ?>
                                        <tr><td colspan="3" class="text-center text-muted py-4">No valid rows to display.</td></tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <div class="card shadow-sm text-center py-5 text-muted">
                    <div class="card-body">
                        <i class="fas fa-file-csv fa-4x text-secondary mb-3"></i>
                        <h5>No CSV Processed Yet</h5>
                        <p class="mb-0">Select an entity type and upload a CSV file to validate and preview rows before importing.</p>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
