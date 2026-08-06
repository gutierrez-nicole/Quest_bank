<?php
require_once __DIR__ . '/../app/bootstrap.php';
require_once __DIR__ . '/../app/services/ExamSchedulingService.php';
require_once __DIR__ . '/../app/services/AcademicStructureService.php';
require_once __DIR__ . '/../app/services/AuditLogService.php';

AuthService::enforceRole('admin');
$pdo = getDBConnection();
$adminId = $_SESSION['user_id'];

$msg = '';
$msgType = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validateCSRFToken();
    $action = $_POST['action'] ?? '';
    try {
        if ($action === 'create_schedule') {
            ExamSchedulingService::createSchedule(
                $_POST['exam_id'],
                $_POST['teacher_id'],
                $_POST['section'],
                $_POST['exam_date'],
                $_POST['start_time'],
                $_POST['end_time'],
                $_POST['room'] ?? '',
                $_POST['remarks'] ?? ''
            );
            $msg = "Exam schedule created and students notified successfully.";
            AuditLogService::logAction($adminId, "Created Exam Schedule", "Exam ID: {$_POST['exam_id']}, Section: {$_POST['section']}");
        }
    } catch (Exception $e) {
        $msg = $e->getMessage();
        $msgType = 'danger';
    }
}

$filters = [
    'section' => $_GET['section'] ?? 'all',
    'teacher_id' => $_GET['teacher_id'] ?? 'all',
    'status' => $_GET['status'] ?? 'all'
];

$schedules = ExamSchedulingService::getAllSchedules($filters);
$exams = $pdo->query("SELECT e.id, e.title, e.subject, u.fullname as teacher_name, e.teacher_id FROM exams e JOIN users u ON e.teacher_id = u.id ORDER BY e.id DESC")->fetchAll(PDO::FETCH_ASSOC);
$sections = AcademicStructureService::getSections();
$teachers = $pdo->query("SELECT id, fullname FROM users WHERE role = 'teacher' AND status = 'active' ORDER BY fullname ASC")->fetchAll(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Exam Scheduling Management - QuestBank Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="h3 font-weight-bold"><i class="fas fa-calendar-alt text-primary me-2"></i>Exam Schedules Management</h2>
            <p class="text-muted mb-0">Schedule exams, resolve timing conflicts, and view upcoming timetable</p>
        </div>
        <div>
            <a href="export.php?type=schedules&format=pdf" class="btn btn-danger me-2"><i class="fas fa-file-pdf me-1"></i>Export PDF</a>
            <a href="export.php?type=schedules&format=csv" class="btn btn-success me-2"><i class="fas fa-file-csv me-1"></i>Export CSV</a>
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
        <div class="col-md-4">
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-primary text-white"><h5 class="card-title mb-0">Schedule New Exam</h5></div>
                <div class="card-body">
                    <form method="POST">
                        <?= csrfInputField() ?>
                        <input type="hidden" name="action" value="create_schedule">
                        <div class="mb-3">
                            <label class="form-label">Select Exam</label>
                            <select name="exam_id" id="exam_select" class="form-select" required onchange="updateTeacherId()">
                                <option value="">-- Select Exam --</option>
                                <?php foreach ($exams as $ex): ?>
                                    <option value="<?= $ex['id'] ?>" data-teacher="<?= $ex['teacher_id'] ?>"><?= htmlspecialchars($ex['title']) ?> (<?= htmlspecialchars($ex['teacher_name']) ?>)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <input type="hidden" name="teacher_id" id="teacher_id">
                        <div class="mb-3">
                            <label class="form-label">Section</label>
                            <select name="section" class="form-select" required>
                                <?php foreach ($sections as $sec): ?>
                                    <option value="<?= $sec['section_code'] ?>"><?= htmlspecialchars($sec['section_code']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Exam Date</label>
                            <input type="date" name="exam_date" class="form-control" required min="<?= date('Y-m-d') ?>">
                        </div>
                        <div class="row mb-3">
                            <div class="col-6">
                                <label class="form-label">Start Time</label>
                                <input type="time" name="start_time" class="form-control" required>
                            </div>
                            <div class="col-6">
                                <label class="form-label">End Time</label>
                                <input type="time" name="end_time" class="form-control" required>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Room / Building</label>
                            <input type="text" name="room" class="form-control" placeholder="Room 304, CE Building">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Remarks (Optional)</label>
                            <textarea name="remarks" class="form-control" rows="2" placeholder="Bring scientific calculator"></textarea>
                        </div>
                        <button type="submit" class="btn btn-primary w-100"><i class="fas fa-clock me-1"></i>Schedule Exam</button>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-md-8">
            <div class="card shadow-sm">
                <div class="card-header bg-white">
                    <form method="GET" class="row g-2 align-items-center">
                        <div class="col-md-4">
                            <select name="section" class="form-select" onchange="this.form.submit()">
                                <option value="all">All Sections</option>
                                <?php foreach ($sections as $sec): ?>
                                    <option value="<?= $sec['section_code'] ?>" <?= $filters['section'] === $sec['section_code'] ? 'selected' : '' ?>><?= htmlspecialchars($sec['section_code']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <select name="teacher_id" class="form-select" onchange="this.form.submit()">
                                <option value="all">All Teachers</option>
                                <?php foreach ($teachers as $t): ?>
                                    <option value="<?= $t['id'] ?>" <?= intval($filters['teacher_id']) === intval($t['id']) ? 'selected' : '' ?>><?= htmlspecialchars($t['fullname']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <select name="status" class="form-select" onchange="this.form.submit()">
                                <option value="all">All Statuses</option>
                                <option value="scheduled" <?= $filters['status'] === 'scheduled' ? 'selected' : '' ?>>Scheduled</option>
                                <option value="cancelled" <?= $filters['status'] === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                            </select>
                        </div>
                    </form>
                </div>
                <div class="card-body p-0">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Exam Title</th>
                                <th>Teacher</th>
                                <th>Section</th>
                                <th>Date & Time</th>
                                <th>Room</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($schedules as $sch): ?>
                                <tr>
                                    <td>
                                        <div class="fw-bold"><?= htmlspecialchars($sch['exam_title']) ?></div>
                                        <small class="text-muted"><?= htmlspecialchars($sch['subject']) ?></small>
                                    </td>
                                    <td><?= htmlspecialchars($sch['teacher_name']) ?></td>
                                    <td><span class="badge bg-info text-dark"><?= htmlspecialchars($sch['section']) ?></span></td>
                                    <td>
                                        <div><i class="far fa-calendar-alt text-primary me-1"></i><?= htmlspecialchars($sch['exam_date']) ?></div>
                                        <small class="text-muted"><?= substr($sch['start_time'], 0, 5) ?> - <?= substr($sch['end_time'], 0, 5) ?> (<?= $sch['duration_minutes'] ?>m)</small>
                                    </td>
                                    <td><?= htmlspecialchars($sch['room'] ?: 'TBA') ?></td>
                                    <td><span class="badge bg-<?= $sch['status'] === 'scheduled' ? 'success' : 'secondary' ?>"><?= ucfirst($sch['status']) ?></span></td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (empty($schedules)): ?>
                                <tr><td colspan="6" class="text-center text-muted py-4">No exam schedules found.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
<script>
function updateTeacherId() {
    var select = document.getElementById('exam_select');
    var selectedOption = select.options[select.selectedIndex];
    document.getElementById('teacher_id').value = selectedOption.getAttribute('data-teacher') || '';
}
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
