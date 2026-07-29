<?php
require_once __DIR__ . '/../app/database.php';
require_once __DIR__ . '/../app/session.php';
require_once __DIR__ . '/../includes/security.php';

requireRole('teacher');
$pdo = getDBConnection();
$success_msg = "";
$error_msg = "";

$teacher_id = getCurrentUserId();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['handle_request'])) {
    validateCSRFToken();
    $request_id = intval($_POST['request_id'] ?? 0);
    $action_type = $_POST['action_type'] ?? '';

    if ($request_id > 0 && in_array($action_type, ['accept', 'reject'])) {
        $status = ($action_type === 'accept') ? 'accepted' : 'rejected';
        $stmt = $pdo->prepare("UPDATE student_requests SET status = ? WHERE id = ? AND teacher_id = ?");
        $stmt->execute([$status, $request_id, $teacher_id]);

        if ($action_type === 'accept') {
            $reqStmt = $pdo->prepare("SELECT * FROM student_requests WHERE id = ?");
            $reqStmt->execute([$request_id]);
            $req = $reqStmt->fetch();
            if ($req) {
                $secId = $req['section_id'] ?? null;
                if (!$secId) {
                    $secStmt = $pdo->prepare("SELECT id FROM sections WHERE teacher_id = ? LIMIT 1");
                    $secStmt->execute([$teacher_id]);
                    $secId = $secStmt->fetchColumn();
                    if (!$secId) {
                        $insSec = $pdo->prepare("INSERT INTO sections (teacher_id, section_name, course_name, academic_year) VALUES (?, ?, ?, ?)");
                        $insSec->execute([$teacher_id, "BSCE 4-A", "BS Civil Engineering", "2025-2026"]);
                        $secId = $pdo->lastInsertId();
                    }
                }

                $checkRoster = $pdo->prepare("SELECT COUNT(*) FROM students WHERE student_number = ? AND teacher_id = ?");
                $checkRoster->execute([$req['student_number'], $teacher_id]);
                if ($checkRoster->fetchColumn() == 0) {
                    $insRoster = $pdo->prepare("INSERT INTO students (teacher_id, section_id, student_number, fullname) VALUES (?, ?, ?, ?)");
                    $insRoster->execute([$teacher_id, $secId, $req['student_number'], $req['student_name']]);
                }
            }
        }
        $success_msg = "Student join request successfully " . ($action_type === 'accept' ? 'accepted and added to section roster' : 'rejected') . "!";
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_section'])) {
    validateCSRFToken();
    $section_name = trim($_POST['section_name']);
    $course_name = trim($_POST['course_name']);
    $academic_year = trim($_POST['academic_year']);

    if (!empty($section_name) && !empty($course_name)) {
        try {
            $stmt = $pdo->prepare("INSERT INTO sections (teacher_id, section_name, course_name, academic_year) VALUES (?, ?, ?, ?)");
            $stmt->execute([$teacher_id, $section_name, $course_name, $academic_year]);
            $success_msg = "New section successfully created!";
        } catch (PDOException $e) {
            $error_msg = "Failed to add section: " . $e->getMessage();
        }
    } else {
        $error_msg = "Please fill in all required section details.";
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_student'])) {
    validateCSRFToken();
    $student_number = trim($_POST['student_number']);
    $fullname = trim($_POST['fullname']);
    $email = trim($_POST['email']);
    $section_id = intval($_POST['section_id']);

    if (!empty($student_number) && !empty($fullname) && $section_id > 0) {
        try {
            $stmt = $pdo->prepare("INSERT INTO students (teacher_id, section_id, student_number, fullname, email) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$teacher_id, $section_id, $student_number, $fullname, $email]);
            $success_msg = "Student enrolled successfully!";
        } catch (PDOException $e) {
            $error_msg = "Error adding student (Student Number might already exist): " . $e->getMessage();
        }
    } else {
        $error_msg = "Please complete all student fields and select a valid section.";
    }
}

$stmtSec = $pdo->prepare("SELECT * FROM sections WHERE teacher_id = ? ORDER BY id DESC");
$stmtSec->execute([$teacher_id]);
$sections = $stmtSec->fetchAll(PDO::FETCH_ASSOC);

$stmtPending = $pdo->prepare("SELECT * FROM student_requests WHERE teacher_id = ? AND status = 'pending' ORDER BY id DESC");
$stmtPending->execute([$teacher_id]);
$pending_requests = $stmtPending->fetchAll(PDO::FETCH_ASSOC);

$search_query = trim($_GET['search_student'] ?? '');
if (!empty($search_query)) {
    $stmtStud = $pdo->prepare("
        SELECT s.*, sec.section_name, sec.course_name 
        FROM students s 
        JOIN sections sec ON s.section_id = sec.id 
        WHERE s.teacher_id = ? AND (s.student_number LIKE ? OR s.fullname LIKE ?)
        ORDER BY s.id DESC
    ");
    $stmtStud->execute([$teacher_id, "%{$search_query}%", "%{$search_query}%"]);
} else {
    $stmtStud = $pdo->prepare("
        SELECT s.*, sec.section_name, sec.course_name 
        FROM students s 
        JOIN sections sec ON s.section_id = sec.id 
        WHERE s.teacher_id = ? 
        ORDER BY s.id DESC
    ");
    $stmtStud->execute([$teacher_id]);
}
$students = $stmtStud->fetchAll(PDO::FETCH_ASSOC);

$at_risk_students = [];
try {
    $stmtRisk = $pdo->prepare("
        SELECT student_name, exam_title, percentage, correct_count, total_items, created_at 
        FROM exam_submissions 
        WHERE teacher_id = ? AND percentage < 75 
        ORDER BY id DESC
    ");
    $stmtRisk->execute([$teacher_id]);
    $at_risk_students = $stmtRisk->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $at_risk_students = [];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>QuestBank - Student Roster & Join Requests</title>
    
    <script src="https://cdn.tailwindcss.com"></script>
    
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet">
    <style> body { font-family: 'Plus Jakarta Sans', sans-serif; } </style>
</head>
<body class="bg-[#fffbf7] min-h-screen flex">

    
    <?php require_once __DIR__ . '/../includes/teacher_sidebar.php'; ?>

    
    <main class="flex-1 ml-16 lg:ml-64 p-6 md:p-12 overflow-y-auto min-h-screen">
        <div class="max-w-6xl mx-auto space-y-6">
            <div class="flex items-center justify-between">
                <div>
                    <a href="dashboard.php" class="text-xs font-bold text-orange-600 hover:underline"><i class="fa-solid fa-arrow-left mr-1"></i> Back to Dashboard</a>
                    <h1 class="text-2xl font-extrabold text-stone-800 mt-2"><i class="fa-solid fa-graduation-cap text-orange-600 mr-1"></i> Student Roster & Join Requests</h1>
                    <p class="text-xs text-stone-400">Organize class sections, review student join requests, and search student credentials.</p>
                </div>
            </div>

            <?php if (!empty($success_msg)): ?>
                <div class="bg-emerald-50 border-l-4 border-emerald-500 p-4 rounded-xl text-xs font-semibold text-emerald-700"><?php echo $success_msg; ?></div>
            <?php endif; ?>
            <?php if (!empty($error_msg)): ?>
                <div class="bg-red-50 border-l-4 border-red-500 p-4 rounded-xl text-xs font-semibold text-red-700"><?php echo $error_msg; ?></div>
            <?php endif; ?>

            
            <?php if (!empty($at_risk_students)): ?>
                <div class="bg-amber-50/80 border border-amber-200 p-5 rounded-2xl shadow-sm space-y-3">
                    <div class="flex items-center justify-between border-b border-amber-200 pb-2">
                        <h3 class="text-sm font-extrabold uppercase text-amber-900 flex items-center gap-2">
                            <i class="fa-solid fa-triangle-exclamation text-amber-600"></i> Early Warning: Academically At-Risk Students (< 75% Score)
                        </h3>
                        <span class="bg-amber-200 text-amber-900 text-[10px] font-black px-2.5 py-0.5 rounded-full uppercase">Action Required</span>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                        <?php foreach ($at_risk_students as $risk): ?>
                            <div class="p-3 bg-white border border-amber-200/80 rounded-xl space-y-1 text-xs">
                                <div class="flex items-center justify-between">
                                    <span class="font-extrabold text-stone-800"><?php echo htmlspecialchars($risk['student_name']); ?></span>
                                    <span class="bg-rose-100 text-rose-800 font-mono font-bold text-[10px] px-2 py-0.5 rounded"><?php echo number_format($risk['percentage'], 1); ?>% (AT-RISK)</span>
                                </div>
                                <p class="text-[11px] text-stone-500">Exam: <strong class="text-stone-700"><?php echo htmlspecialchars($risk['exam_title']); ?></strong> (Score: <?php echo $risk['correct_count'] . '/' . $risk['total_items']; ?>)</p>
                                <p class="text-[10px] text-amber-700 font-semibold italic">Recommendation: Schedule targeted tutorial in Civil Engineering fundamentals.</p>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <?php if (!empty($pending_requests)): ?>
                <div class="bg-white border border-stone-200 p-5 rounded-2xl shadow-sm space-y-4">
                    <h3 class="text-sm font-bold text-stone-800 border-b pb-2 flex items-center gap-2">
                        <i class="fa-solid fa-user-plus text-orange-600"></i> Pending Subject Join Applications
                        <span class="bg-orange-100 text-orange-800 text-[10px] font-black px-2 py-0.5 rounded-full"><?php echo count($pending_requests); ?> Pending</span>
                    </h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <?php foreach ($pending_requests as $req): ?>
                            <div class="p-4 border border-stone-200 rounded-xl bg-stone-50/60 flex items-center justify-between">
                                <div class="space-y-1">
                                    <div class="flex items-center gap-2">
                                        <span class="font-extrabold text-xs text-stone-800"><?php echo htmlspecialchars($req['student_name']); ?></span>
                                        <span class="text-[9px] bg-stone-200 text-stone-700 font-bold px-2 py-0.5 rounded"><?php echo htmlspecialchars($req['student_number']); ?></span>
                                    </div>
                                    <p class="text-[10px] text-stone-500">Subject: <strong class="text-stone-700"><?php echo htmlspecialchars($req['subject_name']); ?></strong></p>
                                </div>
                                <div class="flex items-center gap-2">
                                    <form action="manage_students.php" method="POST" class="inline">
                                        <?php echo csrfInputField(); ?>
                                        <input type="hidden" name="request_id" value="<?php echo $req['id']; ?>">
                                        <input type="hidden" name="action_type" value="accept">
                                        <button type="submit" name="handle_request" class="bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs px-3 py-1.5 rounded-xl shadow-sm">
                                            <i class="fa-solid fa-check mr-1"></i> Accept
                                        </button>
                                    </form>
                                    <form action="manage_students.php" method="POST" class="inline">
                                        <?php echo csrfInputField(); ?>
                                        <input type="hidden" name="request_id" value="<?php echo $req['id']; ?>">
                                        <input type="hidden" name="action_type" value="reject">
                                        <button type="submit" name="handle_request" class="bg-rose-100 hover:bg-rose-200 text-rose-700 font-bold text-xs px-3 py-1.5 rounded-xl">
                                            <i class="fa-solid fa-xmark mr-1"></i> Reject
                                        </button>
                                    </form>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                
                
                <div class="space-y-6">
                    
                    
                    <div class="bg-white border border-stone-200 rounded-2xl p-5 shadow-sm space-y-4">
                        <h3 class="text-sm font-bold uppercase tracking-wider text-stone-700 border-b pb-2"><i class="fa-solid fa-users-rectangle text-orange-500 mr-1"></i> Create New Section</h3>
                        
                        <form action="manage_students.php" method="POST" class="space-y-3">
                            <?php echo csrfInputField(); ?>
                            <div class="space-y-1">
                                <label class="text-xs font-bold text-stone-600">Section Name</label>
                                <input type="text" name="section_name" required placeholder="e.g. BSCE 4-A" class="w-full bg-stone-50 border border-stone-200 rounded-xl px-4 py-2 text-xs outline-none focus:border-orange-500">
                            </div>
                            <div class="space-y-1">
                                <label class="text-xs font-bold text-stone-600">Course / Program</label>
                                <input type="text" name="course_name" required placeholder="e.g. BS Civil Engineering" class="w-full bg-stone-50 border border-stone-200 rounded-xl px-4 py-2 text-xs outline-none focus:border-orange-500">
                            </div>
                            <div class="space-y-1">
                                <label class="text-xs font-bold text-stone-600">Academic Year</label>
                                <input type="text" name="academic_year" value="2025-2026" required class="w-full bg-stone-50 border border-stone-200 rounded-xl px-4 py-2 text-xs outline-none focus:border-orange-500">
                            </div>
                            <button type="submit" name="add_section" class="w-full bg-stone-900 hover:bg-orange-600 text-white font-bold text-xs py-2.5 rounded-xl transition-all shadow-sm">
                                <i class="fa-solid fa-plus mr-1"></i> Add Section
                            </button>
                        </form>
                    </div>

                    
                    <div class="bg-white border border-stone-200 rounded-2xl p-5 shadow-sm space-y-4">
                        <h3 class="text-sm font-bold uppercase tracking-wider text-stone-700 border-b pb-2"><i class="fa-solid fa-user-plus text-orange-500 mr-1"></i> Enroll Student Manually</h3>
                        
                        <form action="manage_students.php" method="POST" class="space-y-3">
                            <?php echo csrfInputField(); ?>
                            <div class="space-y-1">
                                <label class="text-xs font-bold text-stone-600">Student Number</label>
                                <input type="text" name="student_number" required placeholder="e.g. 23-2149184" class="w-full bg-stone-50 border border-stone-200 rounded-xl px-4 py-2 text-xs outline-none focus:border-orange-500">
                            </div>
                            <div class="space-y-1">
                                <label class="text-xs font-bold text-stone-600">Full Name</label>
                                <input type="text" name="fullname" required placeholder="e.g. Ashley Nicole Gutierrez" class="w-full bg-stone-50 border border-stone-200 rounded-xl px-4 py-2 text-xs outline-none focus:border-orange-500">
                            </div>
                            <div class="space-y-1">
                                <label class="text-xs font-bold text-stone-600">Email Address (Optional)</label>
                                <input type="email" name="email" placeholder="e.g. nikol@gmail.com" class="w-full bg-stone-50 border border-stone-200 rounded-xl px-4 py-2 text-xs outline-none focus:border-orange-500">
                            </div>
                            <div class="space-y-1">
                                <label class="text-xs font-bold text-stone-600">Assign Section</label>
                                <select name="section_id" required class="w-full bg-stone-50 border border-stone-200 rounded-xl px-4 py-2 text-xs outline-none focus:border-orange-500">
                                    <option value="">-- Select Section --</option>
                                    <?php foreach ($sections as $sec): ?>
                                        <option value="<?php echo $sec['id']; ?>"><?php echo htmlspecialchars($sec['section_name'] . ' (' . $sec['course_name'] . ')'); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <button type="submit" name="add_student" class="w-full bg-orange-600 hover:bg-orange-700 text-white font-bold text-xs py-2.5 rounded-xl transition-all shadow-sm">
                                <i class="fa-solid fa-user-check mr-1"></i> Enroll Student
                            </button>
                        </form>
                    </div>

                </div>

                
                <div class="lg:col-span-2 bg-white border border-stone-200 rounded-2xl p-6 shadow-sm space-y-4">
                    <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3 border-b pb-3">
                        <h3 class="text-sm font-bold uppercase tracking-wider text-stone-700"><i class="fa-solid fa-list text-orange-500 mr-1"></i> Enrolled Student Roster</h3>
                        
                        
                        <form action="manage_students.php" method="GET" class="flex items-center gap-2 w-full sm:w-auto">
                            <div class="relative w-full sm:w-48">
                                <i class="fa-solid fa-magnifying-glass absolute left-3 top-2.5 text-stone-400 text-xs"></i>
                                <input type="text" name="search_student" value="<?php echo htmlspecialchars($search_query); ?>" placeholder="Search Student #..." class="w-full bg-stone-50 border border-stone-200 rounded-xl pl-8 pr-3 py-1.5 text-xs outline-none focus:border-orange-500">
                            </div>
                            <button type="submit" class="bg-stone-900 text-white text-xs font-bold px-3 py-1.5 rounded-xl hover:bg-orange-600 transition-all">Search</button>
                        </form>
                    </div>

                    <?php if (!empty($students)): ?>
                        <div class="overflow-x-auto">
                            <table class="w-full text-left text-xs">
                                <thead class="bg-stone-50 text-stone-500 font-bold uppercase border-b">
                                    <tr>
                                        <th class="p-3">Student #</th>
                                        <th class="p-3">Full Name</th>
                                        <th class="p-3">Course & Section</th>
                                        <th class="p-3">Email</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-stone-100">
                                    <?php foreach ($students as $st): ?>
                                        <tr class="hover:bg-stone-50/60 transition-all">
                                            <td class="p-3 font-extrabold text-orange-600"><?php echo htmlspecialchars($st['student_number']); ?></td>
                                            <td class="p-3 font-bold text-stone-800"><?php echo htmlspecialchars($st['fullname']); ?></td>
                                            <td class="p-3">
                                                <span class="bg-stone-100 text-stone-700 font-bold text-[10px] px-2 py-0.5 rounded border border-stone-200">
                                                    <?php echo htmlspecialchars($st['section_name'] . ' - ' . $st['course_name']); ?>
                                                </span>
                                            </td>
                                            <td class="p-3 text-stone-400 font-medium"><?php echo htmlspecialchars($st['email'] ?? 'N/A'); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <p class="text-xs text-stone-400 text-center py-8">No students enrolled matching your criteria.</p>
                    <?php endif; ?>
                </div>

            </div>
        </div>
    </main>
</body>
</html>