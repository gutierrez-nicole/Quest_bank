<?php
require_once __DIR__ . '/../app/bootstrap.php';
require_once __DIR__ . '/../app/services/AcademicStructureService.php';
require_once __DIR__ . '/../app/services/AuditLogService.php';

AuthService::enforceRole('admin');
$pdo = getDBConnection();
$adminId = $_SESSION['user_id'];

$msg = '';
$msgType = 'success';

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    try {
        if ($action === 'create_school_year') {
            AcademicStructureService::createSchoolYear($_POST['school_year'], $_POST['start_date'], $_POST['end_date']);
            $msg = "School Year created successfully.";
            AuditLogService::logAction($adminId, "Created School Year", $_POST['school_year']);
        } elseif ($action === 'activate_school_year') {
            AcademicStructureService::activateSchoolYear($_POST['sy_id']);
            $msg = "School Year activated successfully.";
            AuditLogService::logAction($adminId, "Activated School Year", "ID: " . $_POST['sy_id']);
        } elseif ($action === 'archive_school_year') {
            AcademicStructureService::archiveSchoolYear($_POST['sy_id']);
            $msg = "School Year archived successfully.";
            AuditLogService::logAction($adminId, "Archived School Year", "ID: " . $_POST['sy_id']);
        } elseif ($action === 'create_semester') {
            AcademicStructureService::createSemester($_POST['sy_id'], $_POST['semester_name']);
            $msg = "Semester created successfully.";
            AuditLogService::logAction($adminId, "Created Semester", $_POST['semester_name']);
        } elseif ($action === 'activate_semester') {
            AcademicStructureService::activateSemester($_POST['sem_id']);
            $msg = "Semester activated successfully.";
            AuditLogService::logAction($adminId, "Activated Semester", "ID: " . $_POST['sem_id']);
        } elseif ($action === 'close_semester') {
            AcademicStructureService::closeSemester($_POST['sem_id']);
            $msg = "Semester closed successfully.";
            AuditLogService::logAction($adminId, "Closed Semester", "ID: " . $_POST['sem_id']);
        } elseif ($action === 'add_event') {
            AcademicStructureService::addCalendarEvent($_POST['event_title'], $_POST['event_type'], $_POST['start_date'], $_POST['end_date'], $_POST['description'] ?? '', $adminId);
            $msg = "Academic calendar event added successfully.";
            AuditLogService::logAction($adminId, "Added Calendar Event", $_POST['event_title']);
        } elseif ($action === 'create_section') {
            AcademicStructureService::createSection($_POST['section_code'], $_POST['adviser_id'] ?: null, $_POST['capacity'] ?: 40);
            $msg = "Section created successfully.";
            AuditLogService::logAction($adminId, "Created Section", $_POST['section_code']);
        } elseif ($action === 'assign_teacher') {
            AcademicStructureService::assignTeacherSubject($_POST['teacher_id'], $_POST['subject'], $_POST['section_id'], $_POST['school_year_id']);
            $msg = "Teacher subject assignment saved successfully.";
            AuditLogService::logAction($adminId, "Assigned Teacher Subject", "Teacher ID: {$_POST['teacher_id']}, Subject: {$_POST['subject']}");
        }
    } catch (Exception $e) {
        $msg = $e->getMessage();
        $msgType = 'danger';
    }
}

$schoolYears = AcademicStructureService::getSchoolYears();
$semesters = AcademicStructureService::getSemesters();
$calendarEvents = AcademicStructureService::getAcademicCalendar();
$sections = AcademicStructureService::getSections();
$teacherAssignments = AcademicStructureService::getTeacherAssignments();

$teachers = $pdo->query("SELECT id, fullname FROM users WHERE role = 'teacher' AND status = 'active' ORDER BY fullname ASC")->fetchAll(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Academic Structure Management - QuestBank Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="h3 font-weight-bold"><i class="fas fa-university text-primary me-2"></i>Academic Structure & Administration</h2>
            <p class="text-muted mb-0">Manage School Years, Semesters, Calendar Events, Sections, and Teacher Subject Assignments</p>
        </div>
        <a href="dashboard.php" class="btn btn-outline-secondary"><i class="fas fa-arrow-left me-1"></i>Back to Dashboard</a>
    </div>

    <?php if (!empty($msg)): ?>
        <div class="alert alert-<?= $msgType ?> alert-dismissible fade show" role="alert">
            <?= htmlspecialchars($msg) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <ul class="nav nav-tabs mb-4" id="academicTabs" role="tablist">
        <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#schoolYearsTab"><i class="fas fa-calendar-alt me-2"></i>School Years</button></li>
        <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#semestersTab"><i class="fas fa-layer-group me-2"></i>Semesters</button></li>
        <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#calendarTab"><i class="fas fa-calendar-week me-2"></i>Academic Calendar</button></li>
        <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#sectionsTab"><i class="fas fa-users-class me-2"></i>Sections</button></li>
        <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#assignmentsTab"><i class="fas fa-chalkboard-teacher me-2"></i>Subject Assignments</button></li>
    </ul>

    <div class="tab-content" id="academicTabsContent">
        <!-- School Years Tab -->
        <div class="tab-pane fade show active" id="schoolYearsTab">
            <div class="row">
                <div class="col-md-4">
                    <div class="card shadow-sm mb-4">
                        <div class="card-header bg-primary text-white"><h5 class="card-title mb-0">Create School Year</h5></div>
                        <div class="card-body">
                            <form method="POST">
                                <input type="hidden" name="action" value="create_school_year">
                                <div class="mb-3">
                                    <label class="form-label">School Year (e.g. 2025-2026)</label>
                                    <input type="text" name="school_year" class="form-control" required placeholder="2025-2026">
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Start Date</label>
                                    <input type="date" name="start_date" class="form-control" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">End Date</label>
                                    <input type="date" name="end_date" class="form-control" required>
                                </div>
                                <button type="submit" class="btn btn-primary w-100"><i class="fas fa-plus me-1"></i>Create School Year</button>
                            </form>
                        </div>
                    </div>
                </div>
                <div class="col-md-8">
                    <div class="card shadow-sm">
                        <div class="card-header bg-white"><h5 class="card-title mb-0">School Years Overview</h5></div>
                        <div class="card-body p-0">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>School Year</th>
                                        <th>Start Date</th>
                                        <th>End Date</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($schoolYears as $sy): ?>
                                        <tr>
                                            <td class="fw-bold"><?= htmlspecialchars($sy['school_year']) ?></td>
                                            <td><?= htmlspecialchars($sy['start_date']) ?></td>
                                            <td><?= htmlspecialchars($sy['end_date']) ?></td>
                                            <td>
                                                <span class="badge bg-<?= $sy['status'] === 'active' ? 'success' : ($sy['status'] === 'archived' ? 'secondary' : 'warning') ?>">
                                                    <?= ucfirst($sy['status']) ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php if ($sy['status'] !== 'active' && $sy['status'] !== 'archived'): ?>
                                                    <form method="POST" class="d-inline">
                                                        <input type="hidden" name="action" value="activate_school_year">
                                                        <input type="hidden" name="sy_id" value="<?= $sy['id'] ?>">
                                                        <button class="btn btn-sm btn-outline-success">Activate</button>
                                                    </form>
                                                <?php endif; ?>
                                                <?php if ($sy['status'] !== 'archived'): ?>
                                                    <form method="POST" class="d-inline">
                                                        <input type="hidden" name="action" value="archive_school_year">
                                                        <input type="hidden" name="sy_id" value="<?= $sy['id'] ?>">
                                                        <button class="btn btn-sm btn-outline-secondary">Archive</button>
                                                    </form>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Semesters Tab -->
        <div class="tab-pane fade" id="semestersTab">
            <div class="row">
                <div class="col-md-4">
                    <div class="card shadow-sm mb-4">
                        <div class="card-header bg-success text-white"><h5 class="card-title mb-0">Create Semester</h5></div>
                        <div class="card-body">
                            <form method="POST">
                                <input type="hidden" name="action" value="create_semester">
                                <div class="mb-3">
                                    <label class="form-label">School Year</label>
                                    <select name="sy_id" class="form-select" required>
                                        <?php foreach ($schoolYears as $sy): ?>
                                            <option value="<?= $sy['id'] ?>"><?= htmlspecialchars($sy['school_year']) ?> (<?= ucfirst($sy['status']) ?>)</option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Semester Name</label>
                                    <select name="semester_name" class="form-select" required>
                                        <option value="First Semester">First Semester</option>
                                        <option value="Second Semester">Second Semester</option>
                                        <option value="Summer">Summer</option>
                                    </select>
                                </div>
                                <button type="submit" class="btn btn-success w-100"><i class="fas fa-plus me-1"></i>Create Semester</button>
                            </form>
                        </div>
                    </div>
                </div>
                <div class="col-md-8">
                    <div class="card shadow-sm">
                        <div class="card-header bg-white"><h5 class="card-title mb-0">Semesters Overview</h5></div>
                        <div class="card-body p-0">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>School Year</th>
                                        <th>Semester</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($semesters as $sem): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($sem['school_year']) ?></td>
                                            <td class="fw-bold"><?= htmlspecialchars($sem['semester_name']) ?></td>
                                            <td>
                                                <span class="badge bg-<?= $sem['status'] === 'active' ? 'success' : ($sem['status'] === 'closed' ? 'danger' : 'secondary') ?>">
                                                    <?= ucfirst($sem['status']) ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php if ($sem['status'] !== 'active' && !in_array($sem['status'], ['closed', 'archived'])): ?>
                                                    <form method="POST" class="d-inline">
                                                        <input type="hidden" name="action" value="activate_semester">
                                                        <input type="hidden" name="sem_id" value="<?= $sem['id'] ?>">
                                                        <button class="btn btn-sm btn-outline-success">Activate</button>
                                                    </form>
                                                <?php endif; ?>
                                                <?php if ($sem['status'] === 'active'): ?>
                                                    <form method="POST" class="d-inline">
                                                        <input type="hidden" name="action" value="close_semester">
                                                        <input type="hidden" name="sem_id" value="<?= $sem['id'] ?>">
                                                        <button class="btn btn-sm btn-outline-danger">Close</button>
                                                    </form>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Academic Calendar Tab -->
        <div class="tab-pane fade" id="calendarTab">
            <div class="row">
                <div class="col-md-4">
                    <div class="card shadow-sm mb-4">
                        <div class="card-header bg-info text-white"><h5 class="card-title mb-0">Add Calendar Event</h5></div>
                        <div class="card-body">
                            <form method="POST">
                                <input type="hidden" name="action" value="add_event">
                                <div class="mb-3">
                                    <label class="form-label">Event Title</label>
                                    <input type="text" name="event_title" class="form-control" required placeholder="Midterm Examination Week">
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Event Type</label>
                                    <select name="event_type" class="form-select" required>
                                        <option value="prelim_week">Prelim Week</option>
                                        <option value="midterm_week">Midterm Week</option>
                                        <option value="finals_week">Finals Week</option>
                                        <option value="qualifying_exam">Qualifying Exam</option>
                                        <option value="review_week">Review Week</option>
                                        <option value="holiday">Holiday</option>
                                        <option value="suspension">Suspension</option>
                                        <option value="school_activity">School Activity</option>
                                    </select>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Start Date</label>
                                    <input type="date" name="start_date" class="form-control" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">End Date</label>
                                    <input type="date" name="end_date" class="form-control" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Description (Optional)</label>
                                    <textarea name="description" class="form-control" rows="2"></textarea>
                                </div>
                                <button type="submit" class="btn btn-info text-white w-100"><i class="fas fa-plus me-1"></i>Add Event</button>
                            </form>
                        </div>
                    </div>
                </div>
                <div class="col-md-8">
                    <div class="card shadow-sm">
                        <div class="card-header bg-white"><h5 class="card-title mb-0">Academic Calendar Events</h5></div>
                        <div class="card-body p-0">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Event Title</th>
                                        <th>Type</th>
                                        <th>Dates</th>
                                        <th>Creator</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($calendarEvents as $evt): ?>
                                        <tr>
                                            <td class="fw-bold"><?= htmlspecialchars($evt['event_title']) ?></td>
                                            <td><span class="badge bg-secondary"><?= htmlspecialchars(ucwords(str_replace('_', ' ', $evt['event_type']))) ?></span></td>
                                            <td><?= htmlspecialchars($evt['start_date']) ?> to <?= htmlspecialchars($evt['end_date']) ?></td>
                                            <td><?= htmlspecialchars($evt['creator_name'] ?? 'System Admin') ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Sections Tab -->
        <div class="tab-pane fade" id="sectionsTab">
            <div class="row">
                <div class="col-md-4">
                    <div class="card shadow-sm mb-4">
                        <div class="card-header bg-warning text-dark"><h5 class="card-title mb-0">Create Section</h5></div>
                        <div class="card-body">
                            <form method="POST">
                                <input type="hidden" name="action" value="create_section">
                                <div class="mb-3">
                                    <label class="form-label">Section Code (e.g. CE-4A)</label>
                                    <input type="text" name="section_code" class="form-control" required placeholder="CE-4A">
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Adviser (Teacher)</label>
                                    <select name="adviser_id" class="form-select">
                                        <option value="">-- Select Adviser --</option>
                                        <?php foreach ($teachers as $t): ?>
                                            <option value="<?= $t['id'] ?>"><?= htmlspecialchars($t['fullname']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Capacity</label>
                                    <input type="number" name="capacity" class="form-control" value="40" min="1" required>
                                </div>
                                <button type="submit" class="btn btn-warning w-100"><i class="fas fa-plus me-1"></i>Create Section</button>
                            </form>
                        </div>
                    </div>
                </div>
                <div class="col-md-8">
                    <div class="card shadow-sm">
                        <div class="card-header bg-white"><h5 class="card-title mb-0">Sections List</h5></div>
                        <div class="card-body p-0">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Section Code</th>
                                        <th>Adviser</th>
                                        <th>Capacity</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($sections as $sec): ?>
                                        <tr>
                                            <td class="fw-bold"><?= htmlspecialchars($sec['section_code']) ?></td>
                                            <td><?= htmlspecialchars($sec['adviser_name'] ?? 'Unassigned') ?></td>
                                            <td><?= htmlspecialchars($sec['capacity']) ?> students</td>
                                            <td><span class="badge bg-success"><?= ucfirst($sec['status']) ?></span></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Subject Assignments Tab -->
        <div class="tab-pane fade" id="assignmentsTab">
            <div class="row">
                <div class="col-md-4">
                    <div class="card shadow-sm mb-4">
                        <div class="card-header bg-dark text-white"><h5 class="card-title mb-0">Assign Teacher Subject</h5></div>
                        <div class="card-body">
                            <form method="POST">
                                <input type="hidden" name="action" value="assign_teacher">
                                <div class="mb-3">
                                    <label class="form-label">Teacher</label>
                                    <select name="teacher_id" class="form-select" required>
                                        <?php foreach ($teachers as $t): ?>
                                            <option value="<?= $t['id'] ?>"><?= htmlspecialchars($t['fullname']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Subject</label>
                                    <input type="text" name="subject" class="form-control" required placeholder="Structural Engineering">
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Section</label>
                                    <select name="section_id" class="form-select" required>
                                        <?php foreach ($sections as $sec): ?>
                                            <option value="<?= $sec['id'] ?>"><?= htmlspecialchars($sec['section_code']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">School Year</label>
                                    <select name="school_year_id" class="form-select" required>
                                        <?php foreach ($schoolYears as $sy): ?>
                                            <option value="<?= $sy['id'] ?>"><?= htmlspecialchars($sy['school_year']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <button type="submit" class="btn btn-dark w-100"><i class="fas fa-plus me-1"></i>Save Assignment</button>
                            </form>
                        </div>
                    </div>
                </div>
                <div class="col-md-8">
                    <div class="card shadow-sm">
                        <div class="card-header bg-white"><h5 class="card-title mb-0">Teacher Subject Assignments</h5></div>
                        <div class="card-body p-0">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Teacher</th>
                                        <th>Subject</th>
                                        <th>Section</th>
                                        <th>School Year</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($teacherAssignments as $ta): ?>
                                        <tr>
                                            <td class="fw-bold"><?= htmlspecialchars($ta['teacher_name']) ?></td>
                                            <td><?= htmlspecialchars($ta['subject']) ?></td>
                                            <td><span class="badge bg-primary"><?= htmlspecialchars($ta['section_code']) ?></span></td>
                                            <td><?= htmlspecialchars($ta['school_year']) ?></td>
                                            <td><span class="badge bg-success"><?= ucfirst($ta['status']) ?></span></td>
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
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
