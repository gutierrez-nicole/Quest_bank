<?php
require_once __DIR__ . '/../app/bootstrap.php';

AuthService::enforceRole('teacher');
$pdo = getDBConnection();

$request_msg = "";

try {
    $teacher_id = getCurrentUserId();

    
    $stmt = $pdo->prepare("SELECT fullname, username, email FROM users WHERE id = ?");
    $stmt->execute([$teacher_id]);
    $teacher = $stmt->fetch();

    
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
            $request_msg = "Student join request successfully " . ($action_type === 'accept' ? 'accepted and enrolled to class section' : 'rejected') . "!";
        }
    }

    
    $stmtPending = $pdo->prepare("SELECT * FROM student_requests WHERE teacher_id = ? AND status = 'pending' ORDER BY id DESC");
    $stmtPending->execute([$teacher_id]);
    $pending_requests = $stmtPending->fetchAll(PDO::FETCH_ASSOC);

    
    $total_students = 0;
    try {
        $stmt = $pdo->prepare("
            SELECT COUNT(DISTINCT sd.user_id) 
            FROM student_details sd 
            JOIN users u ON sd.user_id = u.id 
            WHERE u.role = 'student'
        ");
        $stmt->execute();
        $total_students = (int)$stmt->fetchColumn();
        
        if ($total_students == 0) {
            $stmt = $pdo->prepare("
                SELECT COUNT(DISTINCT student_id) 
                FROM exam_submissions 
                WHERE teacher_id = ?
            ");
            $stmt->execute([$teacher_id]);
            $total_students = (int)$stmt->fetchColumn();
        }
        
        if ($total_students == 0) {
            $stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'student'");
            $total_students = (int)$stmt->fetchColumn();
        }
    } catch (PDOException $e) {
        $total_students = 0;
    }

    
    $total_exams = 0;
    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM exams WHERE teacher_id = ?");
        $stmt->execute([$teacher_id]);
        $total_exams = (int)$stmt->fetchColumn();
        
        if ($total_exams == 0) {
            $stmt = $pdo->query("SELECT COUNT(*) FROM exams");
            $total_exams = (int)$stmt->fetchColumn();
        }
    } catch (PDOException $e) {
        $total_exams = 0;
    }

    
    $total_checked = 0;
    $avg_percentage = 0;
    try {
        $stmt = $pdo->prepare("
            SELECT COUNT(*), AVG(percentage) 
            FROM exam_submissions 
            WHERE teacher_id = ?
        ");
        $stmt->execute([$teacher_id]);
        $row = $stmt->fetch(PDO::FETCH_NUM);
        $total_checked = (int)($row[0] ?? 0);
        $avg_percentage = round((float)($row[1] ?? 0), 1);
        
        if ($total_checked == 0) {
            $stmt = $pdo->query("SELECT COUNT(*), AVG(percentage) FROM exam_submissions");
            $row = $stmt->fetch(PDO::FETCH_NUM);
            $total_checked = (int)($row[0] ?? 0);
            $avg_percentage = round((float)($row[1] ?? 0), 1);
        }
    } catch (PDOException $e) {
        $total_checked = 0;
        $avg_percentage = 0;
    }

    
    $sections_labels = [];
    $sections_pass_rates = [];
    $sections_fail_rates = [];
    
    try {
        $stmt = $pdo->prepare("
            SELECT 
                sd.section AS section_name,
                SUM(CASE WHEN es.status = 'Pass' THEN 1 ELSE 0 END) AS pass_count,
                SUM(CASE WHEN es.status = 'Fail' THEN 1 ELSE 0 END) AS fail_count
            FROM exam_submissions es
            LEFT JOIN student_details sd ON es.student_id = sd.user_id
            WHERE es.teacher_id = ?
            GROUP BY sd.section
        ");
        $stmt->execute([$teacher_id]);
        $secData = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($secData)) {
            $stmt = $pdo->query("
                SELECT 
                    sd.section AS section_name,
                    SUM(CASE WHEN es.status = 'Pass' THEN 1 ELSE 0 END) AS pass_count,
                    SUM(CASE WHEN es.status = 'Fail' THEN 1 ELSE 0 END) AS fail_count
                FROM exam_submissions es
                LEFT JOIN student_details sd ON es.student_id = sd.user_id
                GROUP BY sd.section
            ");
            $secData = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
        
        if (!empty($secData)) {
            foreach ($secData as $row) {
                $secName = $row['section_name'] ?: 'BSCE 4-A';
                $sections_labels[] = $secName;
                $sections_pass_rates[] = (int)$row['pass_count'];
                $sections_fail_rates[] = (int)$row['fail_count'];
            }
        }
    } catch (PDOException $e) {
        
    }
    
    if (empty($sections_labels)) {
        $sections_labels = [];
        $sections_pass_rates = [];
        $sections_fail_rates = [];
    }

    
    $recent_submissions = [];
    try {
        $stmt = $pdo->prepare("
            SELECT student_name, exam_title, percentage, status, created_at 
            FROM exam_submissions 
            WHERE teacher_id = ? 
            ORDER BY created_at DESC 
            LIMIT 5
        ");
        $stmt->execute([$teacher_id]);
        $recent_submissions = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($recent_submissions)) {
            $stmt = $pdo->query("
                SELECT student_name, exam_title, percentage, status, created_at 
                FROM exam_submissions 
                ORDER BY created_at DESC 
                LIMIT 5
            ");
            $recent_submissions = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    } catch (PDOException $e) {
        $recent_submissions = [];
    }

    // Epic 2.1 Qualifying Examination Metrics
    $total_qualifying_exams = 0;
    $total_qualified_students = 0;
    $total_not_qualified_students = 0;
    $total_pending_qualifying = 0;

    try {
        $stmtQEx = $pdo->prepare("SELECT COUNT(*) FROM exams WHERE teacher_id = ? AND exam_category = 'qualifying'");
        $stmtQEx->execute([$teacher_id]);
        $total_qualifying_exams = (int)$stmtQEx->fetchColumn();

        $stmtQRes = $pdo->prepare("
            SELECT qualification_status, COUNT(*) as cnt 
            FROM exam_submissions es 
            JOIN exams e ON es.exam_id = e.id 
            WHERE e.teacher_id = ? AND e.exam_category = 'qualifying'
            GROUP BY qualification_status
        ");
        $stmtQRes->execute([$teacher_id]);
        while ($r = $stmtQRes->fetch(PDO::FETCH_ASSOC)) {
            if ($r['qualification_status'] === 'qualified') $total_qualified_students = (int)$r['cnt'];
            elseif ($r['qualification_status'] === 'not_qualified') $total_not_qualified_students = (int)$r['cnt'];
            elseif ($r['qualification_status'] === 'pending') $total_pending_qualifying = (int)$r['cnt'];
        }
    } catch (PDOException $e) {
        // Fail-safe defaults
    }

} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>QuestBank - Teacher Dashboard</title>
    
    <script src="https://cdn.tailwindcss.com"></script>
    
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; }
        .custom-scrollbar::-webkit-scrollbar { width: 4px; }
        .custom-scrollbar::-webkit-scrollbar-thumb { background: #444; border-radius: 10px; }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: scale(0.95); }
            to { opacity: 1; transform: scale(1); }
        }
        .animate-fadeIn { animation: fadeIn 0.2s ease-out; }
    </style>
</head>
<body class="bg-[#fffbf7] min-h-screen flex">

    
    <?php require_once __DIR__ . '/../includes/teacher_sidebar.php'; ?>

    
    <main class="flex-grow flex flex-col min-w-0 ml-16 lg:ml-64 min-h-screen">
        
        
        <header class="bg-white border-b border-stone-200 px-6 py-4 flex items-center justify-between flex-shrink-0">
            <div>
                <h2 class="text-lg font-bold text-stone-800">Faculty Dashboard Workspace</h2>
                <p class="text-xs text-stone-400">Welcome, Professor! Manage automatic test items, curriculum layers, and student rosters.</p>
            </div>
            
            <div class="flex items-center gap-4">
                <div class="flex items-center gap-3 pl-2 border-l border-stone-200">
                    <div class="w-9 h-9 rounded-xl bg-orange-100 text-orange-700 font-bold flex items-center justify-center shadow-inner">
                        <?php echo strtoupper(substr($teacher['fullname'] ?? 'Prof', 0, 2)); ?>
                    </div>
                    <div class="hidden sm:block text-left">
                        <p class="text-xs font-bold text-stone-800 leading-tight"><?php echo htmlspecialchars($teacher['fullname'] ?? 'Teacher'); ?></p>
                        <p class="text-[10px] text-stone-400 font-medium">Faculty Professor</p>
                    </div>
                </div>
            </div>
        </header>

        
        <div class="flex-grow overflow-y-auto p-6 space-y-6 custom-scrollbar">

            
            <?php if (!empty($request_msg)): ?>
                <div class="bg-emerald-50 border-l-4 border-emerald-500 p-4 rounded-xl text-xs font-semibold text-emerald-800 flex items-center justify-between shadow-sm animate-fadeIn">
                    <span class="flex items-center gap-2"><i class="fa-solid fa-circle-check text-emerald-600 text-sm"></i> <?php echo $request_msg; ?></span>
                    <button onclick="this.parentElement.remove();" class="text-emerald-500 hover:text-emerald-800"><i class="fa-solid fa-xmark"></i></button>
                </div>
            <?php endif; ?>

            
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                <div class="bg-white p-4 border border-stone-200 rounded-xl flex items-center justify-between shadow-sm hover:shadow-md transition-shadow">
                    <div>
                        <p class="text-[10px] uppercase tracking-wider font-bold text-stone-400">Handled Students</p>
                        <h3 class="text-2xl font-black text-stone-800 mt-1"><?php echo number_format($total_students); ?></h3>
                        <p class="text-[9px] text-emerald-600 font-semibold mt-1">
                            <i class="fa-solid fa-circle-check"></i> Active Roster
                        </p>
                    </div>
                    <div class="p-3 bg-orange-100 text-orange-600 rounded-xl"><i class="fa-solid fa-users text-lg"></i></div>
                </div>
                <div class="bg-white p-4 border border-stone-200 rounded-xl flex items-center justify-between shadow-sm hover:shadow-md transition-shadow">
                    <div>
                        <p class="text-[10px] uppercase tracking-wider font-bold text-stone-400">AI Exams Generated</p>
                        <h3 class="text-2xl font-black text-stone-800 mt-1"><?php echo number_format($total_exams); ?></h3>
                        <p class="text-[9px] text-blue-600 font-semibold mt-1">
                            <i class="fa-solid fa-wand-magic-sparkles"></i> Created
                        </p>
                    </div>
                    <div class="p-3 bg-amber-100 text-amber-600 rounded-xl"><i class="fa-solid fa-wand-magic-sparkles text-lg"></i></div>
                </div>
                <div class="bg-white p-4 border border-stone-200 rounded-xl flex items-center justify-between shadow-sm hover:shadow-md transition-shadow">
                    <div>
                        <p class="text-[10px] uppercase tracking-wider font-bold text-stone-400">OCR Scripts Checked</p>
                        <h3 class="text-2xl font-black text-stone-800 mt-1"><?php echo number_format($total_checked); ?></h3>
                        <p class="text-[9px] text-purple-600 font-semibold mt-1">
                            <i class="fa-solid fa-check-double"></i> Graded
                        </p>
                    </div>
                    <div class="p-3 bg-purple-100 text-purple-600 rounded-xl"><i class="fa-solid fa-print text-lg"></i></div>
                </div>
                <div class="bg-white p-4 border border-stone-200 rounded-xl flex items-center justify-between shadow-sm hover:shadow-md transition-shadow">
                    <div>
                        <p class="text-[10px] uppercase tracking-wider font-bold text-stone-400">Sections Average</p>
                        <h3 class="text-2xl font-black text-emerald-600 mt-1"><?php echo $avg_percentage; ?>%</h3>
                        <p class="text-[9px] text-stone-500 font-semibold mt-1">
                            <i class="fa-solid fa-chart-line"></i> Overall Score
                        </p>
                    </div>
                    <div class="p-3 bg-emerald-100 text-emerald-600 rounded-xl"><i class="fa-solid fa-chart-line text-lg"></i></div>
                </div>
            </div>

            <!-- Epic 2.1 Qualifying Examination Summary Cards -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                <div class="bg-gradient-to-br from-orange-500 to-amber-600 text-white p-4 rounded-xl shadow-sm flex items-center justify-between">
                    <div>
                        <p class="text-[10px] uppercase tracking-wider font-extrabold opacity-80">Qualifying Exams</p>
                        <h3 class="text-2xl font-black mt-1"><?php echo number_format($total_qualifying_exams); ?></h3>
                        <p class="text-[9px] font-semibold mt-1 opacity-90"><i class="fa-solid fa-award"></i> Active Modules</p>
                    </div>
                    <div class="p-3 bg-white/20 rounded-xl"><i class="fa-solid fa-award text-xl"></i></div>
                </div>

                <div class="bg-white p-4 border border-emerald-200 rounded-xl flex items-center justify-between shadow-sm">
                    <div>
                        <p class="text-[10px] uppercase tracking-wider font-bold text-emerald-700">Qualified Students</p>
                        <h3 class="text-2xl font-black text-emerald-600 mt-1"><?php echo number_format($total_qualified_students); ?></h3>
                        <p class="text-[9px] text-emerald-600 font-semibold mt-1"><i class="fa-solid fa-circle-check"></i> Passed Criteria</p>
                    </div>
                    <div class="p-3 bg-emerald-100 text-emerald-600 rounded-xl"><i class="fa-solid fa-user-check text-lg"></i></div>
                </div>

                <div class="bg-white p-4 border border-rose-200 rounded-xl flex items-center justify-between shadow-sm">
                    <div>
                        <p class="text-[10px] uppercase tracking-wider font-bold text-rose-700">Not Qualified</p>
                        <h3 class="text-2xl font-black text-rose-600 mt-1"><?php echo number_format($total_not_qualified_students); ?></h3>
                        <p class="text-[9px] text-rose-600 font-semibold mt-1"><i class="fa-solid fa-circle-xmark"></i> Below Benchmark</p>
                    </div>
                    <div class="p-3 bg-rose-100 text-rose-600 rounded-xl"><i class="fa-solid fa-user-xmark text-lg"></i></div>
                </div>

                <div class="bg-white p-4 border border-amber-200 rounded-xl flex items-center justify-between shadow-sm">
                    <div>
                        <p class="text-[10px] uppercase tracking-wider font-bold text-amber-700">Pending Results</p>
                        <h3 class="text-2xl font-black text-amber-600 mt-1"><?php echo number_format($total_pending_qualifying); ?></h3>
                        <p class="text-[9px] text-amber-600 font-semibold mt-1"><i class="fa-solid fa-clock"></i> Awaiting Review</p>
                    </div>
                    <div class="p-3 bg-amber-100 text-amber-600 rounded-xl"><i class="fa-solid fa-hourglass-half text-lg"></i></div>
                </div>
            </div>

            
            <div class="bg-white border border-stone-200 p-5 rounded-2xl shadow-sm space-y-4">
                <div class="flex items-center justify-between border-b border-stone-100 pb-3">
                    <div>
                        <h3 class="text-sm font-bold text-stone-800 flex items-center gap-2">
                            <i class="fa-solid fa-user-plus text-orange-600"></i> Student Subject Join Requests
                            <span class="bg-orange-100 text-orange-800 text-[10px] font-black px-2 py-0.5 rounded-full"><?php echo count($pending_requests); ?> Pending</span>
                        </h3>
                        <p class="text-[11px] text-stone-400">Review student enrollment applications for your assigned subjects and class sections (Thesis Docx Figure 10 & 13).</p>
                    </div>
                    <a href="manage_students.php" class="text-xs font-extrabold text-orange-600 hover:underline">Manage Student Roster →</a>
                </div>

                <?php if (!empty($pending_requests)): ?>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <?php foreach ($pending_requests as $req): ?>
                            <div class="p-4 border border-stone-200 rounded-xl bg-stone-50/60 flex items-center justify-between hover:border-orange-300 transition-all">
                                <div class="space-y-1">
                                    <div class="flex items-center gap-2">
                                        <span class="font-extrabold text-xs text-stone-800"><?php echo htmlspecialchars($req['student_name']); ?></span>
                                        <span class="text-[9px] bg-stone-200 text-stone-700 font-bold px-2 py-0.5 rounded"><?php echo htmlspecialchars($req['student_number']); ?></span>
                                    </div>
                                    <p class="text-[10px] text-stone-500 font-medium">Requested Subject: <strong class="text-stone-700"><?php echo htmlspecialchars($req['subject_name']); ?></strong></p>
                                    <span class="text-[9px] text-stone-400"><i class="fa-regular fa-clock mr-1"></i><?php echo date('M d, Y h:i A', strtotime($req['requested_at'])); ?></span>
                                </div>

                                <div class="flex items-center gap-2">
                                    <form action="dashboard.php" method="POST" class="inline">
                                        <?php echo csrfInputField(); ?>
                                        <input type="hidden" name="request_id" value="<?php echo $req['id']; ?>">
                                        <input type="hidden" name="action_type" value="accept">
                                        <button type="submit" name="handle_request" class="bg-emerald-600 hover:bg-emerald-700 text-white font-extrabold text-xs px-3 py-2 rounded-xl transition-all shadow-sm flex items-center gap-1">
                                            <i class="fa-solid fa-check"></i> Accept
                                        </button>
                                    </form>
                                    <form action="dashboard.php" method="POST" class="inline">
                                        <?php echo csrfInputField(); ?>
                                        <input type="hidden" name="request_id" value="<?php echo $req['id']; ?>">
                                        <input type="hidden" name="action_type" value="reject">
                                        <button type="submit" name="handle_request" class="bg-rose-100 hover:bg-rose-200 text-rose-700 font-extrabold text-xs px-3 py-2 rounded-xl transition-all flex items-center gap-1">
                                            <i class="fa-solid fa-xmark"></i> Delete
                                        </button>
                                    </form>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="text-center py-6 bg-stone-50/50 rounded-xl border border-dashed border-stone-200">
                        <p class="text-xs text-stone-400 font-semibold"><i class="fa-solid fa-circle-check text-emerald-500 mr-1"></i> No pending student join requests. All student rosters are up to date!</p>
                    </div>
                <?php endif; ?>
            </div>

            
            <div class="bg-white border border-stone-200 p-5 rounded-2xl shadow-sm">
                <div class="flex items-center justify-between mb-4 border-b pb-2">
                    <div>
                        <h3 class="text-sm font-bold text-stone-800"><i class="fa-solid fa-chart-column text-orange-500 mr-1.5"></i> Section Performance Comparison (Pass vs Fail)</h3>
                        <p class="text-[11px] text-stone-400">Real-time data: Passing (≥75%) and failing (<75%) student ratios per section.</p>
                    </div>
                </div>
                <div class="h-64 w-full">
                    <canvas id="sectionBarChart"></canvas>
                </div>
            </div>

            
            <div class="bg-white border border-stone-200 p-5 rounded-2xl shadow-sm space-y-4">
                <div class="flex items-center justify-between border-b pb-3">
                    <h3 class="text-sm font-bold text-stone-800"><i class="fa-solid fa-clock-rotate-left text-orange-500 mr-1.5"></i> Recent Evaluated Submissions</h3>
                    <a href="reports.php" class="text-xs font-bold text-orange-600 hover:underline">View All Reports →</a>
                </div>

                <?php if (!empty($recent_submissions)): ?>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-xs">
                            <thead class="bg-stone-50 text-stone-500 font-bold uppercase border-b">
                                <tr>
                                    <th class="p-3">Student Name</th>
                                    <th class="p-3">Exam Title</th>
                                    <th class="p-3">Score %</th>
                                    <th class="p-3">Status</th>
                                    <th class="p-3">Date Checked</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-stone-100">
                                <?php foreach ($recent_submissions as $sub): ?>
                                    <tr class="hover:bg-stone-50/60 transition-all">
                                        <td class="p-3 font-extrabold text-stone-800"><?php echo htmlspecialchars($sub['student_name']); ?></td>
                                        <td class="p-3 font-semibold text-stone-600"><?php echo htmlspecialchars($sub['exam_title']); ?></td>
                                        <td class="p-3 font-bold text-stone-800"><?php echo $sub['percentage']; ?>%</td>
                                        <td class="p-3">
                                            <?php if ($sub['status'] === 'Pass'): ?>
                                                <span class="bg-emerald-100 text-emerald-700 font-bold text-[10px] px-2 py-0.5 rounded-full">Pass</span>
                                            <?php else: ?>
                                                <span class="bg-rose-100 text-rose-700 font-bold text-[10px] px-2 py-0.5 rounded-full">Fail</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="p-3 text-stone-400 font-medium"><?php echo date('M d, Y', strtotime($sub['created_at'])); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <p class="text-xs text-stone-400 text-center py-4">No exam submissions recorded yet.</p>
                <?php endif; ?>
            </div>

        </div>
    </main>

    
    <script>
        const ctx = document.getElementById('sectionBarChart').getContext('2d');
        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: <?php echo json_encode($sections_labels); ?>,
                datasets: [
                    {
                        label: 'Passed Students',
                        data: <?php echo json_encode($sections_pass_rates); ?>,
                        backgroundColor: '#10b981',
                        borderRadius: 8
                    },
                    {
                        label: 'Failed Students',
                        data: <?php echo json_encode($sections_fail_rates); ?>,
                        backgroundColor: '#f43f5e',
                        borderRadius: 8
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { position: 'top', labels: { font: { family: 'Plus Jakarta Sans', weight: 'bold', size: 11 } } }
                },
                scales: {
                    y: { beginAtZero: true, grid: { color: '#f3f4f6' } },
                    x: { grid: { display: false } }
                }
            }
        });
    </script>
</body>
</html>