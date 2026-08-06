<?php
require_once __DIR__ . '/../app/bootstrap.php';
require_once __DIR__ . '/../app/services/ExamSchedulingService.php';
require_once __DIR__ . '/../app/services/AcademicStructureService.php';
require_once __DIR__ . '/../app/services/AuditLogService.php';

AuthService::enforceRole('teacher');
$teacherId = getCurrentUserId();
$pdo = getDBConnection();

$msg = '';
$msgType = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validateCSRFToken();
    $action = $_POST['action'] ?? '';
    try {
        if ($action === 'create_schedule') {
            ExamSchedulingService::createSchedule(
                $_POST['exam_id'],
                $teacherId,
                $_POST['section'],
                $_POST['exam_date'],
                $_POST['start_time'],
                $_POST['end_time'],
                $_POST['room'] ?? '',
                $_POST['remarks'] ?? ''
            );
            $msg = "Exam schedule created and section students notified successfully.";
            AuditLogService::logAction($teacherId, "Teacher Scheduled Exam", "Exam ID: {$_POST['exam_id']}, Section: {$_POST['section']}");
        }
    } catch (Exception $e) {
        $msg = $e->getMessage();
        $msgType = 'danger';
    }
}

$upcomingSchedules = ExamSchedulingService::getUpcomingSchedulesForTeacher($teacherId);

// Owned exams
$stmtOwned = $pdo->prepare("SELECT id, title, subject FROM exams WHERE teacher_id = ? AND status = 'active' ORDER BY id DESC");
$stmtOwned->execute([$teacherId]);
$ownedExams = $stmtOwned->fetchAll(PDO::FETCH_ASSOC);

// Assigned sections for active SY
$activeSy = AcademicStructureService::getActiveSchoolYear();
$syId = $activeSy ? $activeSy['id'] : 0;
$stmtAssigned = $pdo->prepare("
    SELECT DISTINCT sec.section_code 
    FROM teacher_subject_assignments tsa
    JOIN sections sec ON tsa.section_id = sec.id
    WHERE tsa.teacher_id = ? AND tsa.school_year_id = ? AND tsa.status = 'active'
");
$stmtAssigned->execute([$teacherId, $syId]);
$assignedSections = $stmtAssigned->fetchAll(PDO::FETCH_COLUMN);

// If no specific section assignments exist in table, fetch distinct student sections for convenience or show warning
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Exam Scheduling - QuestBank Teacher</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="h3 font-weight-bold"><i class="fas fa-calendar-alt text-primary me-2"></i>Schedule Owned Examination</h2>
            <p class="text-muted mb-0">Schedule exam timetables for your assigned sections</p>
        </div>
        <a href="dashboard.php" class="btn btn-outline-secondary"><i class="fas fa-arrow-left me-1"></i>Back to Dashboard</a>
    </div>

    <?php if (!empty($msg)): ?>
        <div class="alert alert-<?= $msgType ?> alert-dismissible fade show" role="alert">
            <?= htmlspecialchars($msg) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="row">
        <div class="col-md-5">
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-primary text-white"><h5 class="card-title mb-0">Create Exam Schedule</h5></div>
                <div class="card-body">
                    <form method="POST">
                        <?= csrfInputField() ?>
                        <input type="hidden" name="action" value="create_schedule">
                        
                        <div class="mb-3">
                            <label class="form-label font-weight-bold">Select Owned Exam</label>
                            <select name="exam_id" class="form-select" required>
                                <option value="">-- Select Exam --</option>
                                <?php foreach ($ownedExams as $ex): ?>
                                    <option value="<?= $ex['id'] ?>"><?= htmlspecialchars($ex['title']) ?> (<?= htmlspecialchars($ex['subject']) ?>)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label font-weight-bold">Select Assigned Section</label>
                            <select name="section" class="form-select" required>
                                <option value="">-- Select Section --</option>
                                <?php foreach ($assignedSections as $sec): ?>
                                    <option value="<?= htmlspecialchars($sec) ?>"><?= htmlspecialchars($sec) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <?php if (empty($assignedSections)): ?>
                                <small class="text-danger">Notice: No active section assignments found for the current school year. Contact Administrator.</small>
                            <?php endif; ?>
                        </div>

                        <div class="mb-3">
                            <label class="form-label font-weight-bold">Exam Date</label>
                            <input type="date" name="exam_date" class="form-control" required min="<?= date('Y-m-d') ?>">
                        </div>

                        <div class="row mb-3">
                            <div class="col-6">
                                <label class="form-label font-weight-bold">Start Time</label>
                                <input type="time" name="start_time" class="form-control" required>
                            </div>
                            <div class="col-6">
                                <label class="form-label font-weight-bold">End Time</label>
                                <input type="time" name="end_time" class="form-control" required>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label font-weight-bold">Room / Location</label>
                            <input type="text" name="room" class="form-control" placeholder="Room 304, CE Bldg">
                        </div>

                        <div class="mb-3">
                            <label class="form-label font-weight-bold">Remarks (Optional)</label>
                            <textarea name="remarks" class="form-control" rows="2" placeholder="Scientific calculators allowed"></textarea>
                        </div>

                        <button type="submit" class="btn btn-primary w-100" <?= empty($assignedSections) ? 'disabled' : '' ?>><i class="fas fa-clock me-1"></i>Submit Exam Schedule</button>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-md-7">
            <div class="card shadow-sm">
                <div class="card-header bg-white"><h5 class="card-title mb-0">Your Upcoming Scheduled Exams</h5></div>
                <div class="card-body p-0">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Exam Title</th>
                                <th>Section</th>
                                <th>Date & Time</th>
                                <th>Room</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($upcomingSchedules as $sch): ?>
                                <tr>
                                    <td>
                                        <div class="fw-bold"><?= htmlspecialchars($sch['exam_title']) ?></div>
                                        <small class="text-muted"><?= htmlspecialchars($sch['subject']) ?></small>
                                    </td>
                                    <td><span class="badge bg-info text-dark"><?= htmlspecialchars($sch['section']) ?></span></td>
                                    <td>
                                        <div><i class="far fa-calendar-alt text-primary me-1"></i><?= htmlspecialchars($sch['exam_date']) ?></div>
                                        <small class="text-muted"><?= substr($sch['start_time'], 0, 5) ?> - <?= substr($sch['end_time'], 0, 5) ?></small>
                                    </td>
                                    <td><?= htmlspecialchars($sch['room'] ?: 'TBA') ?></td>
                                    <td><span class="badge bg-success"><?= ucfirst($sch['status']) ?></span></td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (empty($upcomingSchedules)): ?>
                                <tr><td colspan="5" class="text-center text-muted py-4">No upcoming scheduled exams.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
</body>
</html>
