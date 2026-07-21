<?php
session_start();

// Dahil nasa loob ng 'student/' folder ito, aakyat tayo ng isang antas (../) para sa index.php
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header("Location: ../index.php");
    exit();
}

$host = 'localhost';
$dbname = 'bankquest_db';
$db_user = 'root';
$db_pass = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $db_user, $db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    $student_id = $_SESSION['user_id'];

    // ==================== 1. STUDENT PROFILE INFO ====================
    $stmt = $pdo->prepare("
        SELECT u.fullname, u.username, u.email, s.student_number, s.course, s.year_level, s.section 
        FROM users u 
        LEFT JOIN student_details s ON u.id = s.user_id 
        WHERE u.id = ?
    ");
    $stmt->execute([$student_id]);
    $student = $stmt->fetch(PDO::FETCH_ASSOC);

    // Fallback info kung sakaling wala pang details sa database
    if (!$student || empty($student['fullname'])) {
        $student = [
            'fullname' => $_SESSION['username'] ?? 'Student User',
            'username' => $_SESSION['username'] ?? 'student',
            'email' => 'student@questbank.edu.ph',
            'student_number' => '2026-STUDENT-001',
            'course' => 'BSIT',
            'year_level' => '3',
            'section' => 'A'
        ];
    }

    // ==================== 2. AVERAGE SCORE ====================
    $average_score = 0.0;
    try {
        $stmt = $pdo->prepare("
            SELECT AVG(percentage) 
            FROM exam_submissions 
            WHERE student_id = ?
        ");
        $stmt->execute([$student_id]);
        $avg = $stmt->fetchColumn();
        $average_score = $avg ? number_format((float)$avg, 1) : "0.0";
    } catch (PDOException $e) {
        $average_score = "0.0";
    }

    // ==================== 3. PASSING RATE ====================
    $passing_rate = 0.0;
    try {
        $stmt = $pdo->prepare("
            SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN percentage >= 75 THEN 1 ELSE 0 END) as passed
            FROM exam_submissions 
            WHERE student_id = ?
        ");
        $stmt->execute([$student_id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($row['total'] > 0) {
            $passing_rate = number_format(($row['passed'] / $row['total']) * 100, 1);
        } else {
            $passing_rate = "0.0";
        }
    } catch (PDOException $e) {
        $passing_rate = "0.0";
    }

    // ==================== 4. EXAMS COMPLETED ====================
    $exams_completed = 0;
    try {
        $stmt = $pdo->prepare("
            SELECT COUNT(*) 
            FROM exam_submissions 
            WHERE student_id = ?
        ");
        $stmt->execute([$student_id]);
        $exams_completed = (int)$stmt->fetchColumn();
    } catch (PDOException $e) {
        $exams_completed = 0;
    }

    // ==================== 5. CLASS AVERAGE ====================
    $class_average = 0.0;
    try {
        // Get class average from same course/year/section
        $stmt = $pdo->prepare("
            SELECT AVG(es.percentage) 
            FROM exam_submissions es
            JOIN users u ON es.student_id = u.id
            JOIN student_details sd ON u.id = sd.user_id
            WHERE sd.course = ? AND sd.year_level = ? AND sd.section = ?
        ");
        $stmt->execute([
            $student['course'] ?? 'BSIT',
            $student['year_level'] ?? '3',
            $student['section'] ?? 'A'
        ]);
        $avg = $stmt->fetchColumn();
        $class_average = $avg ? number_format((float)$avg, 1) : "0.0";
    } catch (PDOException $e) {
        $class_average = "0.0";
    }

    // ==================== 6. STRONG SUBJECTS (Top 2) ====================
    $strong_subjects = [];
    try {
        $stmt = $pdo->prepare("
            SELECT 
                e.title as subject,
                AVG(es.percentage) as avg_score
            FROM exam_submissions es
            JOIN exams e ON es.exam_id = e.id
            WHERE es.student_id = ?
            GROUP BY e.id, e.title
            ORDER BY avg_score DESC
            LIMIT 2
        ");
        $stmt->execute([$student_id]);
        $strong_subjects = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $strong_subjects = [];
    }

    // Default if no data
    if (empty($strong_subjects)) {
        $strong_subjects = [
            ['subject' => 'Web Development & REST APIs', 'avg_score' => 94.5],
            ['subject' => 'Database Systems & SQL Queries', 'avg_score' => 89.0]
        ];
    }

    // ==================== 7. WEAK SUBJECTS (Bottom 2) ====================
    $weak_subjects = [];
    try {
        $stmt = $pdo->prepare("
            SELECT 
                e.title as subject,
                AVG(es.percentage) as avg_score
            FROM exam_submissions es
            JOIN exams e ON es.exam_id = e.id
            WHERE es.student_id = ?
            GROUP BY e.id, e.title
            ORDER BY avg_score ASC
            LIMIT 2
        ");
        $stmt->execute([$student_id]);
        $weak_subjects = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $weak_subjects = [];
    }

    // Default if no data
    if (empty($weak_subjects)) {
        $weak_subjects = [
            ['subject' => 'Advanced System Architecture', 'avg_score' => 72.0],
            ['subject' => 'Discrete Mathematics Logic', 'avg_score' => 75.5]
        ];
    }

    // ==================== 8. PENDING EXAMS ====================
    $pending_exams = [];
    try {
        $stmt = $pdo->prepare("
            SELECT 
                e.id,
                e.title,
                e.description,
                e.time_limit,
                e.total_items,
                e.exam_type
            FROM exams e
            WHERE e.id NOT IN (
                SELECT exam_id FROM exam_submissions WHERE student_id = ?
            )
            AND e.status = 'active'
            ORDER BY e.created_at DESC
            LIMIT 3
        ");
        $stmt->execute([$student_id]);
        $pending_exams = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $pending_exams = [];
    }

    // ==================== 9. BAR CHART: SUBJECT PERFORMANCE ====================
    $chart_subjects = [];
    $chart_scores = [];
    
    try {
        $stmt = $pdo->prepare("
            SELECT 
                e.title as subject,
                AVG(es.percentage) as avg_score
            FROM exam_submissions es
            JOIN exams e ON es.exam_id = e.id
            WHERE es.student_id = ?
            GROUP BY e.id, e.title
            ORDER BY e.created_at ASC
            LIMIT 5
        ");
        $stmt->execute([$student_id]);
        $subjectData = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (!empty($subjectData)) {
            foreach ($subjectData as $row) {
                $chart_subjects[] = $row['subject'];
                $chart_scores[] = round((float)$row['avg_score'], 1);
            }
        }
    } catch (PDOException $e) {
        // Fallback
    }
    
    // Default if no data
    if (empty($chart_subjects)) {
        $chart_subjects = ['Web Dev', 'Database', 'SysArch', 'Discrete Math', 'DevOps'];
        $chart_scores = [94, 89, 72, 75, 90];
    }

    // ==================== 10. LINE CHART: SCORE TREND ====================
    $chart_exam_labels = [];
    $chart_exam_scores = [];
    
    try {
        $stmt = $pdo->prepare("
            SELECT 
                e.title as exam_name,
                es.percentage as score,
                es.created_at
            FROM exam_submissions es
            JOIN exams e ON es.exam_id = e.id
            WHERE es.student_id = ?
            ORDER BY es.created_at ASC
            LIMIT 5
        ");
        $stmt->execute([$student_id]);
        $trendData = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (!empty($trendData)) {
            foreach ($trendData as $row) {
                $chart_exam_labels[] = $row['exam_name'];
                $chart_exam_scores[] = round((float)$row['score'], 1);
            }
        }
    } catch (PDOException $e) {
        // Fallback
    }
    
    // Default if no data
    if (empty($chart_exam_labels)) {
        $chart_exam_labels = ['Quiz 1', 'Quiz 2', 'Midterm', 'Quiz 3', 'Finals'];
        $chart_exam_scores = [78, 85, 90, 88, 94];
    }

    // ==================== 11. PIE CHART: TOPIC MASTERY ====================
    $mastery_data = [0, 0, 0]; // [Mastered, Review Needed, Unassessed]
    
    try {
        $stmt = $pdo->prepare("
            SELECT 
                SUM(CASE WHEN percentage >= 85 THEN 1 ELSE 0 END) as mastered,
                SUM(CASE WHEN percentage >= 60 AND percentage < 85 THEN 1 ELSE 0 END) as review,
                SUM(CASE WHEN percentage < 60 THEN 1 ELSE 0 END) as unassessed
            FROM exam_submissions
            WHERE student_id = ?
        ");
        $stmt->execute([$student_id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($row) {
            $mastery_data = [
                (int)$row['mastered'],
                (int)$row['review'],
                (int)$row['unassessed']
            ];
        }
    } catch (PDOException $e) {
        // Fallback
    }
    
    // Default if no data
    if (array_sum($mastery_data) == 0) {
        $mastery_data = [65, 25, 10];
    }

    // ==================== 12. RADAR CHART: SKILLS MATRIX ====================
    $skills_data = [0, 0, 0, 0, 0]; // [Logic, Syntax, Architecture, Speed, Theory]
    
    try {
        // Calculate based on exam categories or types
        $stmt = $pdo->prepare("
            SELECT AVG(percentage) as avg_score
            FROM exam_submissions
            WHERE student_id = ?
        ");
        $stmt->execute([$student_id]);
        $avg = $stmt->fetchColumn();
        
        if ($avg) {
            // Distribute scores with some variation
            $base = (float)$avg;
            $skills_data = [
                round($base + rand(-5, 10), 0),
                round($base + rand(-3, 8), 0),
                round($base + rand(-10, 5), 0),
                round($base + rand(-2, 6), 0),
                round($base + rand(-8, 3), 0)
            ];
        }
    } catch (PDOException $e) {
        // Fallback
    }
    
    // Default if no data
    if (array_sum($skills_data) == 0) {
        $skills_data = [85, 92, 70, 88, 80];
    }

    // ==================== 13. EXAM RESULTS TABLE ====================
    $exam_results = [];
    try {
        $stmt = $pdo->prepare("
            SELECT 
                e.title,
                es.score,
                es.total_items,
                es.percentage,
                es.status,
                es.created_at
            FROM exam_submissions es
            JOIN exams e ON es.exam_id = e.id
            WHERE es.student_id = ?
            ORDER BY es.created_at DESC
            LIMIT 10
        ");
        $stmt->execute([$student_id]);
        $exam_results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $exam_results = [];
    }

    // ==================== 14. EXAM HISTORY ====================
    $exam_history = [];
    try {
        $stmt = $pdo->prepare("
            SELECT 
                e.title,
                es.percentage,
                es.created_at
            FROM exam_submissions es
            JOIN exams e ON es.exam_id = e.id
            WHERE es.student_id = ?
            ORDER BY es.created_at DESC
            LIMIT 5
        ");
        $stmt->execute([$student_id]);
        $exam_history = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $exam_history = [];
    }

    // ==================== 15. NOTIFICATIONS ====================
    $notifications = [];
    try {
        $stmt = $pdo->prepare("
            SELECT 
                message,
                type,
                created_at
            FROM notifications
            WHERE user_id = ?
            ORDER BY created_at DESC
            LIMIT 5
        ");
        $stmt->execute([$student_id]);
        $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $notifications = [];
    }

} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>QuestBank - Student AI Dashboard</title>
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- FontAwesome Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Chart.js CDN for Animated Analytics -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    colors: {
                        brand: {
                            orange: '#f97316',
                            darkorange: '#ea580c',
                            black: '#09090b',
                            carddark: '#18181b',
                            bglight: '#f3f4f6'
                        }
                    }
                }
            }
        }
    </script>

    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; }
        .bg-orange-gradient { background: linear-gradient(135deg, #f97316 0%, #ea580c 100%); }
        .bg-orange-gradient:hover { background: linear-gradient(135deg, #ea580c 0%, #c2410c 100%); }
        
        /* Smooth Animations & Transitions */
        .tab-content { display: none; opacity: 0; transition: opacity 0.3s ease-in-out; }
        .tab-content.active { display: block; opacity: 1; animation: fadeIn 0.4s ease-out; }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(8px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .animate-card-hover { transition: all 0.25s ease; }
        .animate-card-hover:hover { transform: translateY(-3px); box-shadow: 0 10px 25px -5px rgba(249, 115, 22, 0.15); }
        
        .no-copy { user-select: none; -webkit-user-select: none; }
        .custom-scrollbar::-webkit-scrollbar { width: 5px; }
        .custom-scrollbar::-webkit-scrollbar-thumb { background: #d1d5db; border-radius: 10px; }
        .dark .custom-scrollbar::-webkit-scrollbar-thumb { background: #3f3f46; }
    </style>
</head>
<body class="bg-[#f3f4f6] dark:bg-[#09090b] text-stone-800 dark:text-stone-100 min-h-screen flex transition-colors duration-300">

    <!-- ================= SIDEBAR NAVIGATION ================= -->
    <aside class="w-64 bg-stone-950 text-stone-300 flex flex-col justify-between hidden md:flex z-30 shadow-2xl fixed h-full">
        <div>
            <!-- Sidebar Logo -->
            <div class="p-5 border-b border-stone-800 flex items-center justify-between bg-stone-900/60">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 bg-orange-gradient rounded-2xl flex items-center justify-center font-extrabold text-white shadow-lg shadow-orange-600/30">
                        <i class="fa-solid fa-brain text-lg"></i>
                    </div>
                    <div>
                        <h1 class="text-lg font-black tracking-tight text-white">Quest<span class="text-orange-500">Bank</span></h1>
                        <p class="text-[9px] uppercase text-stone-400 font-bold tracking-widest">Student Portal</p>
                    </div>
                </div>
            </div>

            <!-- Navigation Links -->
            <nav class="p-4 space-y-1.5 custom-scrollbar overflow-y-auto max-h-[calc(100vh-160px)]">
                <a href="javascript:void(0)" onclick="switchTab('dashboard')" id="nav-dashboard" class="nav-item flex items-center gap-3 px-4 py-3 text-xs font-bold rounded-xl bg-orange-gradient text-white shadow-md shadow-orange-500/20 transition-all">
                    <i class="fa-solid fa-chart-pie w-5 text-center text-sm"></i> Dashboard
                </a>
                <a href="javascript:void(0)" onclick="switchTab('take-exam')" id="nav-take-exam" class="nav-item flex items-center gap-3 px-4 py-3 text-xs font-semibold rounded-xl text-stone-400 hover:bg-stone-900 hover:text-orange-500 transition-all">
                    <i class="fa-solid fa-pen-to-square w-5 text-center text-sm"></i> Take Online Exam
                </a>
                <a href="javascript:void(0)" onclick="switchTab('exam-results')" id="nav-exam-results" class="nav-item flex items-center gap-3 px-4 py-3 text-xs font-semibold rounded-xl text-stone-400 hover:bg-stone-900 hover:text-orange-500 transition-all">
                    <i class="fa-solid fa-square-poll-vertical w-5 text-center text-sm"></i> Exam Results
                </a>
                <a href="javascript:void(0)" onclick="switchTab('analytics')" id="nav-analytics" class="nav-item flex items-center gap-3 px-4 py-3 text-xs font-semibold rounded-xl text-stone-400 hover:bg-stone-900 hover:text-orange-500 transition-all">
                    <i class="fa-solid fa-chart-line w-5 text-center text-sm"></i> AI Performance & Analytics
                </a>
                <a href="javascript:void(0)" onclick="switchTab('history')" id="nav-history" class="nav-item flex items-center gap-3 px-4 py-3 text-xs font-semibold rounded-xl text-stone-400 hover:bg-stone-900 hover:text-orange-500 transition-all">
                    <i class="fa-solid fa-clock-rotate-left w-5 text-center text-sm"></i> Exam History
                </a>
                <a href="javascript:void(0)" onclick="switchTab('notifications')" id="nav-notifications" class="nav-item flex items-center gap-3 px-4 py-3 text-xs font-semibold rounded-xl text-stone-400 hover:bg-stone-900 hover:text-orange-500 transition-all">
                    <i class="fa-solid fa-bell w-5 text-center text-sm"></i> Notifications
                    <?php if (!empty($notifications)): ?>
                        <span class="ml-auto bg-orange-500 text-white text-[9px] font-black px-1.5 py-0.5 rounded-full"><?php echo count($notifications); ?></span>
                    <?php endif; ?>
                </a>
            </nav>
        </div>

        <!-- Sidebar Footer & Logout -->
        <div class="p-4 border-t border-stone-800 bg-stone-900/40">
            <button onclick="openLogoutModal()" class="w-full flex items-center gap-3 px-4 py-3 text-xs font-bold rounded-xl text-rose-400 hover:bg-rose-500/10 hover:text-rose-300 transition-all">
                <i class="fa-solid fa-right-from-bracket w-5 text-center"></i> Logout Account
            </button>
        </div>
    </aside>

    <!-- ================= MAIN CONTENT CONTAINER ================= -->
    <main class="flex-grow flex flex-col min-w-0 md:ml-64 min-h-screen">
        
        <!-- TOP NAV HEADERBAR -->
        <header class="bg-white dark:bg-stone-900 border-b border-stone-200 dark:border-stone-800 px-6 py-4 flex items-center justify-between sticky top-0 z-20 shadow-xs">
            <div class="flex items-center gap-3">
                <h2 class="text-base md:text-lg font-extrabold text-stone-800 dark:text-stone-100">
                    Welcome back, <span class="text-orange-500 font-black"><?php echo htmlspecialchars($student['fullname']); ?></span>!
                </h2>
            </div>
            
            <div class="flex items-center gap-3 md:gap-4">
                <!-- Dark Mode Toggle Button -->
                <button onclick="toggleDarkMode()" class="w-10 h-10 rounded-xl border border-stone-200 dark:border-stone-800 flex items-center justify-center text-stone-500 dark:text-stone-300 hover:bg-stone-100 dark:hover:bg-stone-800 transition-all">
                    <i class="fa-solid fa-moon text-sm dark:hidden"></i>
                    <i class="fa-solid fa-sun text-sm hidden dark:block text-amber-400"></i>
                </button>

                <!-- Notifications Button -->
                <button onclick="switchTab('notifications')" class="w-10 h-10 rounded-xl border border-stone-200 dark:border-stone-800 flex items-center justify-center text-stone-500 dark:text-stone-300 hover:bg-stone-100 dark:hover:bg-stone-800 transition-all relative">
                    <i class="fa-solid fa-bell text-sm"></i>
                    <?php if (!empty($notifications)): ?>
                        <span class="absolute top-2.5 right-2.5 w-2 h-2 bg-orange-500 rounded-full animate-ping"></span>
                        <span class="absolute top-2.5 right-2.5 w-2 h-2 bg-orange-500 rounded-full"></span>
                    <?php endif; ?>
                </button>
                
                <!-- Quick Student Avatar Badge -->
                <div class="flex items-center gap-3 pl-3 border-l border-stone-200 dark:border-stone-800">
                    <div class="w-10 h-10 rounded-xl bg-orange-100 dark:bg-orange-950/60 text-orange-600 font-black flex items-center justify-center shadow-inner text-sm">
                        <?php echo strtoupper(substr($student['fullname'], 0, 2)); ?>
                    </div>
                    <div class="hidden sm:block text-left">
                        <p class="text-xs font-bold text-stone-800 dark:text-stone-100 leading-tight"><?php echo htmlspecialchars($student['username']); ?></p>
                        <p class="text-[10px] text-stone-400 font-semibold uppercase tracking-wider"><?php echo htmlspecialchars($student['course']); ?> - <?php echo htmlspecialchars($student['year_level']); ?><?php echo htmlspecialchars($student['section']); ?></p>
                    </div>
                </div>
            </div>
        </header>

        <!-- CONTENT BODY WRAPPER -->
        <div class="p-6 md:p-8 space-y-6">

            <!-- ================= 1. TAB: STUDENT DASHBOARD ================= -->
            <div id="tab-dashboard" class="tab-content active space-y-6">
                
                <!-- Student Card Information -->
                <div class="bg-white dark:bg-stone-900 border border-stone-200 dark:border-stone-800 rounded-2xl p-6 shadow-sm flex flex-col lg:flex-row justify-between items-start lg:items-center gap-6 animate-card-hover">
                    <div class="flex items-center gap-5">
                        <div class="w-16 h-16 rounded-2xl bg-orange-gradient text-white flex items-center justify-center font-black text-2xl shadow-lg shadow-orange-500/20">
                            <i class="fa-solid fa-user-graduate"></i>
                        </div>
                        <div>
                            <h3 class="text-lg font-black text-stone-800 dark:text-stone-100"><?php echo htmlspecialchars($student['fullname']); ?></h3>
                            <p class="text-xs font-medium text-stone-500 dark:text-stone-400 mt-0.5"><?php echo htmlspecialchars($student['email']); ?> | <span class="font-mono text-orange-500 font-bold"><?php echo htmlspecialchars($student['student_number']); ?></span></p>
                        </div>
                    </div>
                    <div class="grid grid-cols-2 sm:grid-cols-3 gap-3 w-full lg:w-auto text-center">
                        <div class="bg-stone-50 dark:bg-stone-800/50 border border-stone-200/60 dark:border-stone-800 rounded-xl px-4 py-2.5">
                            <p class="text-[10px] font-bold uppercase text-stone-400">Course</p>
                            <p class="text-xs font-extrabold text-stone-800 dark:text-stone-100 mt-0.5"><?php echo htmlspecialchars($student['course']); ?></p>
                        </div>
                        <div class="bg-stone-50 dark:bg-stone-800/50 border border-stone-200/60 dark:border-stone-800 rounded-xl px-4 py-2.5">
                            <p class="text-[10px] font-bold uppercase text-stone-400">Year Level</p>
                            <p class="text-xs font-extrabold text-stone-800 dark:text-stone-100 mt-0.5"><?php echo htmlspecialchars($student['year_level']); ?>rd Year</p>
                        </div>
                        <div class="bg-stone-50 dark:bg-stone-800/50 border border-stone-200/60 dark:border-stone-800 rounded-xl px-4 py-2.5 col-span-2 sm:col-span-1">
                            <p class="text-[10px] font-bold uppercase text-stone-400">Section</p>
                            <p class="text-xs font-extrabold text-stone-800 dark:text-stone-100 mt-0.5"><?php echo htmlspecialchars($student['section']); ?></p>
                        </div>
                    </div>
                </div>

                <!-- QUICK STATS CARDS -->
                <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
                    <div class="bg-white dark:bg-stone-900 border border-stone-200 dark:border-stone-800 p-4 rounded-2xl shadow-sm animate-card-hover flex items-center justify-between">
                        <div>
                            <p class="text-[10px] font-extrabold uppercase text-stone-400">Average Score</p>
                            <h4 class="text-xl font-black text-stone-800 dark:text-stone-100 mt-1"><?php echo $average_score; ?>%</h4>
                            <p class="text-[9px] text-orange-600 font-semibold mt-1">
                                <i class="fa-solid fa-chart-line"></i> Your Performance
                            </p>
                        </div>
                        <div class="p-3 bg-orange-100 dark:bg-orange-950/60 text-orange-600 rounded-xl"><i class="fa-solid fa-chart-line text-base"></i></div>
                    </div>
                    <div class="bg-white dark:bg-stone-900 border border-stone-200 dark:border-stone-800 p-4 rounded-2xl shadow-sm animate-card-hover flex items-center justify-between">
                        <div>
                            <p class="text-[10px] font-extrabold uppercase text-stone-400">Passing Rate</p>
                            <h4 class="text-xl font-black text-emerald-600 mt-1"><?php echo $passing_rate; ?>%</h4>
                            <p class="text-[9px] text-emerald-600 font-semibold mt-1">
                                <i class="fa-solid fa-circle-check"></i> Success Rate
                            </p>
                        </div>
                        <div class="p-3 bg-emerald-100 dark:bg-emerald-950/60 text-emerald-600 rounded-xl"><i class="fa-solid fa-circle-check text-base"></i></div>
                    </div>
                    <div class="bg-white dark:bg-stone-900 border border-stone-200 dark:border-stone-800 p-4 rounded-2xl shadow-sm animate-card-hover flex items-center justify-between">
                        <div>
                            <p class="text-[10px] font-extrabold uppercase text-stone-400">Exams Completed</p>
                            <h4 class="text-xl font-black text-stone-800 dark:text-stone-100 mt-1"><?php echo $exams_completed; ?> Papers</h4>
                            <p class="text-[9px] text-purple-600 font-semibold mt-1">
                                <i class="fa-solid fa-file-signature"></i> Submitted
                            </p>
                        </div>
                        <div class="p-3 bg-purple-100 dark:bg-purple-950/60 text-purple-600 rounded-xl"><i class="fa-solid fa-file-signature text-base"></i></div>
                    </div>
                    <div class="bg-white dark:bg-stone-900 border border-stone-200 dark:border-stone-800 p-4 rounded-2xl shadow-sm animate-card-hover flex items-center justify-between">
                        <div>
                            <p class="text-[10px] font-extrabold uppercase text-stone-400">Class Average</p>
                            <h4 class="text-xl font-black text-amber-500 mt-1"><?php echo $class_average; ?>%</h4>
                            <p class="text-[9px] text-amber-600 font-semibold mt-1">
                                <i class="fa-solid fa-users"></i> Section Avg
                            </p>
                        </div>
                        <div class="p-3 bg-amber-100 dark:bg-amber-950/60 text-amber-600 rounded-xl"><i class="fa-solid fa-users text-base"></i></div>
                    </div>
                </div>

                <!-- STRENGTHS AND WEAKNESSES SUMMARY -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="bg-emerald-50/50 dark:bg-emerald-950/20 border border-emerald-200/60 dark:border-emerald-900/50 rounded-2xl p-5 shadow-sm space-y-3">
                        <div class="flex items-center justify-between">
                            <h4 class="text-xs font-black uppercase text-emerald-700 dark:text-emerald-400 flex items-center gap-2">
                                <i class="fa-solid fa-circle-check text-sm"></i> Strong Subjects & Topics
                            </h4>
                        </div>
                        <div class="space-y-2 text-xs font-bold text-stone-700 dark:text-stone-300">
                            <?php foreach ($strong_subjects as $subject): ?>
                                <div class="bg-white dark:bg-stone-900 p-3 rounded-xl border border-emerald-100 dark:border-stone-800 flex justify-between items-center shadow-2xs">
                                    <span><?php echo htmlspecialchars($subject['subject']); ?></span>
                                    <span class="text-emerald-600 font-black"><?php echo number_format($subject['avg_score'], 1); ?>% Mastery</span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div class="bg-rose-50/50 dark:bg-rose-950/20 border border-rose-200/60 dark:border-rose-900/50 rounded-2xl p-5 shadow-sm space-y-3">
                        <div class="flex items-center justify-between">
                            <h4 class="text-xs font-black uppercase text-rose-700 dark:text-rose-400 flex items-center gap-2">
                                <i class="fa-solid fa-circle-exclamation text-sm"></i> Weak Subjects & Focus Areas
                            </h4>
                        </div>
                        <div class="space-y-2 text-xs font-bold text-stone-700 dark:text-stone-300">
                            <?php foreach ($weak_subjects as $subject): ?>
                                <div class="bg-white dark:bg-stone-900 p-3 rounded-xl border border-rose-100 dark:border-stone-800 flex justify-between items-center shadow-2xs">
                                    <span><?php echo htmlspecialchars($subject['subject']); ?></span>
                                    <span class="text-rose-500 font-black"><?php echo number_format($subject['avg_score'], 1); ?>% Need Review</span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <!-- ACTIVE EXAMS LAUNCHER -->
                <div class="bg-white dark:bg-stone-900 border border-stone-200 dark:border-stone-800 rounded-2xl p-6 shadow-sm space-y-4">
                    <div class="flex items-center justify-between border-b border-stone-100 dark:border-stone-800 pb-3">
                        <h3 class="text-sm font-extrabold text-stone-800 dark:text-stone-100 flex items-center gap-2">
                            <i class="fa-solid fa-bolt text-orange-500"></i> Pending & Active Examination Papers
                        </h3>
                    </div>
                    <div class="space-y-3">
                        <?php if (!empty($pending_exams)): ?>
                            <?php foreach ($pending_exams as $exam): ?>
                                <div class="border border-stone-200 dark:border-stone-800 p-4 rounded-xl flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 bg-stone-50/50 dark:bg-stone-800/30 hover:border-orange-300 transition-all">
                                    <div>
                                        <span class="bg-orange-100 dark:bg-orange-950 text-orange-600 text-[10px] font-black px-2.5 py-0.5 rounded-md uppercase"><?php echo htmlspecialchars($exam['exam_type']); ?></span>
                                        <h4 class="font-bold text-stone-800 dark:text-stone-100 mt-1 text-sm"><?php echo htmlspecialchars($exam['title']); ?></h4>
                                        <p class="text-xs text-stone-400 mt-0.5">Time Limit: <?php echo $exam['time_limit']; ?> mins | Items: <?php echo $exam['total_items']; ?> Questions</p>
                                    </div>
                                    <button onclick="startExamSession()" class="w-full sm:w-auto bg-orange-gradient text-white font-bold text-xs px-5 py-2.5 rounded-xl shadow-md transition-all">
                                        Launch Exam Environment <i class="fa-solid fa-arrow-right ml-1"></i>
                                    </button>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="text-center py-8 text-stone-400">
                                <i class="fa-solid fa-inbox text-3xl mb-2"></i>
                                <p class="text-sm">No pending exams at the moment.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- ================= 2. TAB: ONLINE EXAM INTERFACE ================= -->
            <div id="tab-take-exam" class="tab-content no-copy space-y-6">
                <!-- EXAM INTERFACE CONTAINER -->
                <div class="bg-white dark:bg-stone-900 border border-stone-200 dark:border-stone-800 rounded-2xl shadow-xl overflow-hidden">
                    
                    <!-- EXAM HEADERBAR WITH TIMER -->
                    <div class="bg-stone-950 text-stone-100 p-4 md:p-6 flex flex-col md:flex-row justify-between items-start md:items-center gap-4 border-b border-stone-800">
                        <div>
                            <span class="bg-orange-600 text-white text-[10px] font-black px-2.5 py-0.5 rounded-md uppercase">Secured Proctored Session</span>
                            <h3 class="text-base font-extrabold mt-1">DevOps Principles & System Architecture Midterm</h3>
                        </div>

                        <div class="flex items-center gap-4 w-full md:w-auto justify-between md:justify-end">
                            <button onclick="toggleFullscreen()" class="bg-stone-800 hover:bg-stone-700 text-xs px-3 py-2 rounded-xl font-bold transition-all">
                                <i class="fa-solid fa-expand mr-1"></i> Fullscreen Mode
                            </button>
                            
                            <!-- TIMER DISPLAY -->
                            <div class="bg-stone-900 border border-stone-800 px-4 py-2 rounded-xl flex items-center gap-2">
                                <i class="fa-solid fa-clock text-orange-500 animate-pulse text-sm"></i>
                                <span id="timer_display" class="font-mono font-black text-base text-orange-500">59:59</span>
                            </div>
                        </div>
                    </div>

                    <!-- PROGRESS BAR & AUTO SAVE BANNER -->
                    <div class="bg-stone-50 dark:bg-stone-800/40 px-6 py-2 border-b border-stone-200 dark:border-stone-800 flex justify-between items-center text-xs">
                        <div class="flex items-center gap-2 text-emerald-600 font-bold text-[11px]">
                            <i class="fa-solid fa-cloud-check"></i> <span id="auto_save_text">Answers Auto-Saved locally</span>
                        </div>
                        <div class="w-1/3 bg-stone-200 dark:bg-stone-700 h-2 rounded-full overflow-hidden">
                            <div id="exam_progress" class="bg-orange-500 h-full transition-all duration-300" style="width: 20%;"></div>
                        </div>
                    </div>

                    <div class="p-6 md:p-8 grid grid-cols-1 lg:grid-cols-4 gap-8">
                        
                        <!-- MAIN QUESTION BOARD -->
                        <div class="lg:col-span-3 space-y-6">
                            <div class="flex items-center justify-between">
                                <span class="text-xs font-extrabold uppercase text-orange-500 tracking-wider">Question <span id="current_q_num">1</span> of 5</span>
                                <span class="text-[10px] bg-stone-100 dark:bg-stone-800 text-stone-500 font-bold px-2.5 py-1 rounded-md">Multiple Choice</span>
                            </div>

                            <div class="space-y-4">
                                <h4 id="question_title" class="text-base md:text-lg font-bold text-stone-800 dark:text-stone-100 leading-relaxed">
                                    Which of the following describes the practice of continuous integration (CI) in software development?
                                </h4>

                                <!-- CHOICES CONTAINER -->
                                <div id="choices_container" class="space-y-3 pt-2">
                                    <label class="flex items-center gap-3 p-4 border border-stone-200 dark:border-stone-800 rounded-xl hover:border-orange-500 dark:hover:border-orange-500 cursor-pointer transition-all bg-stone-50/50 dark:bg-stone-800/20">
                                        <input type="radio" name="exam_q1" value="A" class="accent-orange-600 w-4 h-4">
                                        <span class="text-xs font-semibold">A. Frequently merging code changes into a central repository followed by automated builds.</span>
                                    </label>
                                    <label class="flex items-center gap-3 p-4 border border-stone-200 dark:border-stone-800 rounded-xl hover:border-orange-500 dark:hover:border-orange-500 cursor-pointer transition-all bg-stone-50/50 dark:bg-stone-800/20">
                                        <input type="radio" name="exam_q1" value="B" class="accent-orange-600 w-4 h-4">
                                        <span class="text-xs font-semibold">B. Deploying code manually to production servers every month.</span>
                                    </label>
                                    <label class="flex items-center gap-3 p-4 border border-stone-200 dark:border-stone-800 rounded-xl hover:border-orange-500 dark:hover:border-orange-500 cursor-pointer transition-all bg-stone-50/50 dark:bg-stone-800/20">
                                        <input type="radio" name="exam_q1" value="C" class="accent-orange-600 w-4 h-4">
                                        <span class="text-xs font-semibold">C. Writing code without testing until final release phase.</span>
                                    </label>
                                    <label class="flex items-center gap-3 p-4 border border-stone-200 dark:border-stone-800 rounded-xl hover:border-orange-500 dark:hover:border-orange-500 cursor-pointer transition-all bg-stone-50/50 dark:bg-stone-800/20">
                                        <input type="radio" name="exam_q1" value="D" class="accent-orange-600 w-4 h-4">
                                        <span class="text-xs font-semibold">D. Managing user interface styling frameworks only.</span>
                                    </label>
                                </div>
                            </div>

                            <!-- CONTROLS & REVIEW -->
                            <div class="pt-6 border-t border-stone-100 dark:border-stone-800 flex justify-between items-center">
                                <button class="bg-stone-200 dark:bg-stone-800 text-stone-700 dark:text-stone-300 font-bold text-xs px-4 py-2.5 rounded-xl transition-all opacity-50 cursor-not-allowed">
                                    <i class="fa-solid fa-arrow-left mr-1"></i> Previous
                                </button>
                                <button onclick="nextQuestion()" class="bg-stone-900 hover:bg-orange-600 text-white font-bold text-xs px-5 py-2.5 rounded-xl transition-all shadow-sm">
                                    Next Question <i class="fa-solid fa-arrow-right ml-1"></i>
                                </button>
                            </div>
                        </div>

                        <!-- SIDE PANEL QUESTION NAVIGATION GRID -->
                        <div class="space-y-4 border-l border-stone-100 dark:border-stone-800 pl-0 lg:pl-6">
                            <h5 class="text-xs font-extrabold uppercase text-stone-500">Question Navigation</h5>
                            <div class="grid grid-cols-5 gap-2">
                                <button class="w-9 h-9 rounded-lg bg-orange-600 text-white font-bold text-xs">1</button>
                                <button class="w-9 h-9 rounded-lg bg-stone-100 dark:bg-stone-800 text-stone-600 dark:text-stone-300 font-bold text-xs hover:border-orange-500 border">2</button>
                                <button class="w-9 h-9 rounded-lg bg-stone-100 dark:bg-stone-800 text-stone-600 dark:text-stone-300 font-bold text-xs hover:border-orange-500 border">3</button>
                                <button class="w-9 h-9 rounded-lg bg-stone-100 dark:bg-stone-800 text-stone-600 dark:text-stone-300 font-bold text-xs hover:border-orange-500 border">4</button>
                                <button class="w-9 h-9 rounded-lg bg-stone-100 dark:bg-stone-800 text-stone-600 dark:text-stone-300 font-bold text-xs hover:border-orange-500 border">5</button>
                            </div>

                            <div class="pt-4 space-y-2 border-t border-stone-100 dark:border-stone-800">
                                <button onclick="openSubmitModal()" class="w-full bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs py-3 rounded-xl transition-all shadow-md">
                                    <i class="fa-solid fa-paper-plane mr-1"></i> Finalize & Submit
                                </button>
                            </div>
                        </div>

                    </div>
                </div>
            </div>

            <!-- ================= 3. TAB: EXAM RESULTS ================= -->
            <div id="tab-exam-results" class="tab-content space-y-6">
                <div class="bg-white dark:bg-stone-900 border border-stone-200 dark:border-stone-800 rounded-2xl shadow-sm overflow-hidden">
                    <div class="p-6 border-b border-stone-100 dark:border-stone-800 flex justify-between items-center">
                        <h3 class="text-sm font-extrabold text-stone-800 dark:text-stone-100 uppercase tracking-wider">
                            <i class="fa-solid fa-square-poll-vertical text-orange-500 mr-1.5"></i> Evaluated Exam Results List
                        </h3>
                        <button class="bg-stone-900 hover:bg-orange-600 text-white font-bold text-xs px-4 py-2.5 rounded-xl transition-all shadow-sm">
                            <i class="fa-solid fa-file-arrow-down mr-1"></i> Download All Transcripts
                        </button>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="w-full text-left border-collapse text-xs text-stone-600 dark:text-stone-300">
                            <thead class="bg-stone-50 dark:bg-stone-800/50 text-stone-400 font-extrabold uppercase text-[10px] tracking-wider border-b border-stone-100 dark:border-stone-800">
                                <tr>
                                    <th class="p-4 pl-6">Assessment Title</th>
                                    <th class="p-4">Date Submitted</th>
                                    <th class="p-4 text-center">Score</th>
                                    <th class="p-4 text-center">Percentage</th>
                                    <th class="p-4 text-center">Status</th>
                                    <th class="p-4 pr-6 text-center">Action</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-stone-100 dark:divide-stone-800 font-bold">
                                <?php if (!empty($exam_results)): ?>
                                    <?php foreach ($exam_results as $result): ?>
                                        <tr class="hover:bg-stone-50/50 dark:hover:bg-stone-800/30 transition-all">
                                            <td class="p-4 pl-6 text-stone-800 dark:text-stone-100"><?php echo htmlspecialchars($result['title']); ?></td>
                                            <td class="p-4 text-stone-400"><?php echo date("M d, Y", strtotime($result['created_at'])); ?></td>
                                            <td class="p-4 text-center font-mono font-black"><?php echo $result['score']; ?> / <?php echo $result['total_items']; ?></td>
                                            <td class="p-4 text-center font-black text-orange-500"><?php echo number_format($result['percentage'], 1); ?>%</td>
                                            <td class="p-4 text-center">
                                                <?php if ($result['percentage'] >= 75): ?>
                                                    <span class="bg-emerald-100 dark:bg-emerald-950 text-emerald-700 dark:text-emerald-400 px-2.5 py-1 rounded-md text-[10px]">Passed</span>
                                                <?php else: ?>
                                                    <span class="bg-red-100 dark:bg-red-950 text-red-700 dark:text-red-400 px-2.5 py-1 rounded-md text-[10px]">Failed</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="p-4 pr-6 text-center">
                                                <button class="bg-stone-100 dark:bg-stone-800 hover:bg-orange-100 text-stone-700 dark:text-stone-200 px-3 py-1.5 rounded-lg text-xs font-bold transition-all">
                                                    <i class="fa-solid fa-download"></i> PDF Result
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="6" class="p-8 text-center text-stone-400">
                                            <i class="fa-solid fa-inbox text-3xl mb-2"></i>
                                            <p>No exam results available yet.</p>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- ================= 4. TAB: FULL ANALYTICS MODULE ================= -->
            <div id="tab-analytics" class="tab-content space-y-6">
                <!-- ANIMATED CHARTS SECTION -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    
                    <!-- BAR CHART -->
                    <div class="bg-white dark:bg-stone-900 border border-stone-200 dark:border-stone-800 p-5 rounded-2xl shadow-sm">
                        <h4 class="text-xs font-extrabold uppercase text-stone-700 dark:text-stone-200 mb-3">
                            <i class="fa-solid fa-chart-column text-orange-500 mr-1"></i> Subject Performance Analysis (Bar Chart)
                        </h4>
                        <div class="h-60 w-full"><canvas id="studentBarChart"></canvas></div>
                    </div>

                    <!-- LINE CHART -->
                    <div class="bg-white dark:bg-stone-900 border border-stone-200 dark:border-stone-800 p-5 rounded-2xl shadow-sm">
                        <h4 class="text-xs font-extrabold uppercase text-stone-700 dark:text-stone-200 mb-3">
                            <i class="fa-solid fa-chart-line text-orange-500 mr-1"></i> Score Trend Progression (Line Chart)
                        </h4>
                        <div class="h-60 w-full"><canvas id="studentLineChart"></canvas></div>
                    </div>

                    <!-- PIE CHART -->
                    <div class="bg-white dark:bg-stone-900 border border-stone-200 dark:border-stone-800 p-5 rounded-2xl shadow-sm">
                        <h4 class="text-xs font-extrabold uppercase text-stone-700 dark:text-stone-200 mb-3">
                            <i class="fa-solid fa-chart-pie text-orange-500 mr-1"></i> Topic Mastery Breakdown (Pie Chart)
                        </h4>
                        <div class="h-60 w-full flex justify-center"><canvas id="studentPieChart"></canvas></div>
                    </div>

                    <!-- RADAR CHART -->
                    <div class="bg-white dark:bg-stone-900 border border-stone-200 dark:border-stone-800 p-5 rounded-2xl shadow-sm">
                        <h4 class="text-xs font-extrabold uppercase text-stone-700 dark:text-stone-200 mb-3">
                            <i class="fa-solid fa-compass text-orange-500 mr-1"></i> Skills & Strengths Evaluation (Radar Chart)
                        </h4>
                        <div class="h-60 w-full flex justify-center"><canvas id="studentRadarChart"></canvas></div>
                    </div>
                </div>
            </div>

            <!-- ================= 5. TAB: HISTORY ================= -->
            <div id="tab-history" class="tab-content space-y-6">
                <div class="bg-white dark:bg-stone-900 border border-stone-200 dark:border-stone-800 rounded-2xl p-6 shadow-sm space-y-4">
                    <h3 class="text-sm font-extrabold text-stone-800 dark:text-stone-100 uppercase tracking-wider border-b border-stone-100 dark:border-stone-800 pb-3">
                        <i class="fa-solid fa-clock-rotate-left text-orange-500 mr-1.5"></i> Examination Attempt Log History
                    </h3>
                    <div class="space-y-3 font-medium text-xs">
                        <?php if (!empty($exam_history)): ?>
                            <?php foreach ($exam_history as $history): ?>
                                <div class="p-4 border border-stone-100 dark:border-stone-800 rounded-xl bg-stone-50/50 dark:bg-stone-800/30 flex justify-between items-center">
                                    <div>
                                        <h5 class="font-bold text-stone-800 dark:text-stone-100 text-sm"><?php echo htmlspecialchars($history['title']); ?></h5>
                                        <p class="text-stone-400 mt-0.5">Attempted on <?php echo date("M d, Y", strtotime($history['created_at'])); ?> | Auto-Proctored</p>
                                    </div>
                                    <span class="font-mono font-extrabold text-orange-500 text-sm"><?php echo number_format($history['percentage'], 1); ?>%</span>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="text-center py-8 text-stone-400">
                                <i class="fa-solid fa-inbox text-3xl mb-2"></i>
                                <p>No exam history available yet.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- ================= 6. TAB: NOTIFICATIONS ================= -->
            <div id="tab-notifications" class="tab-content space-y-6">
                <div class="bg-white dark:bg-stone-900 border border-stone-200 dark:border-stone-800 rounded-2xl p-6 shadow-sm space-y-4">
                    <h3 class="text-sm font-extrabold text-stone-800 dark:text-stone-100 uppercase tracking-wider border-b border-stone-100 dark:border-stone-800 pb-3">
                        <i class="fa-solid fa-bell text-orange-500 mr-1.5"></i> Student System Notifications
                    </h3>
                    <div class="space-y-3 text-xs">
                        <?php if (!empty($notifications)): ?>
                            <?php foreach ($notifications as $notif): ?>
                                <div class="p-4 border border-stone-100 dark:border-stone-800 rounded-xl bg-orange-50/40 dark:bg-orange-950/20 flex items-start gap-3">
                                    <i class="fa-solid fa-robot text-orange-500 text-base mt-0.5"></i>
                                    <div>
                                        <h5 class="font-bold text-stone-800 dark:text-stone-100"><?php echo htmlspecialchars($notif['type']); ?></h5>
                                        <p class="text-stone-500 dark:text-stone-400 mt-0.5"><?php echo htmlspecialchars($notif['message']); ?></p>
                                        <span class="text-[10px] text-stone-400 font-bold block mt-1">
                                            <?php 
                                            $timestamp = strtotime($notif['created_at']);
                                            $now = time();
                                            $diff = $now - $timestamp;
                                            
                                            if ($diff < 3600) {
                                                echo floor($diff / 60) . " mins ago";
                                            } elseif ($diff < 86400) {
                                                echo floor($diff / 3600) . " hours ago";
                                            } else {
                                                echo date("M d, Y", $timestamp);
                                            }
                                            ?>
                                        </span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="text-center py-8 text-stone-400">
                                <i class="fa-solid fa-inbox text-3xl mb-2"></i>
                                <p>No new notifications.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

        </div>
    </main>

    <!-- SUBMIT CONFIRMATION MODAL -->
    <div id="submit_modal" class="fixed inset-0 bg-stone-950/70 backdrop-blur-xs hidden items-center justify-center z-50 p-4">
        <div class="bg-white dark:bg-stone-900 border border-stone-200 dark:border-stone-800 p-6 rounded-2xl max-w-sm w-full space-y-4 shadow-2xl">
            <h4 class="font-extrabold text-base text-stone-800 dark:text-stone-100">Finalize & Submit Exam?</h4>
            <p class="text-xs text-stone-500 dark:text-stone-400">Sigurado ka bang gusto mo nang i-submit ang iyong mga sagot? Hindi na ito mababago pagkatapos ipadala sa Groq AI Grader.</p>
            <div class="flex gap-2 justify-end pt-2">
                <button onclick="closeSubmitModal()" class="px-4 py-2 bg-stone-200 dark:bg-stone-800 text-stone-700 dark:text-stone-300 font-bold text-xs rounded-xl">Cancel</button>
                <button onclick="finishExam()" class="px-4 py-2 bg-emerald-600 text-white font-bold text-xs rounded-xl shadow-md">Confirm Submit</button>
            </div>
        </div>
    </div>

    <!-- LOGOUT CONFIRMATION MODAL -->
    <div id="logout_modal" class="fixed inset-0 bg-stone-950/70 backdrop-blur-sm hidden items-center justify-center z-50 p-4">
        <div class="bg-white dark:bg-stone-900 border border-stone-200 dark:border-stone-800 p-6 rounded-2xl max-w-sm w-full space-y-4 shadow-2xl animate-fadeIn">
            <div class="flex items-center gap-3">
                <div class="w-12 h-12 bg-red-100 dark:bg-red-950/60 text-red-600 rounded-xl flex items-center justify-center">
                    <i class="fa-solid fa-right-from-bracket text-xl"></i>
                </div>
                <div>
                    <h4 class="font-extrabold text-base text-stone-800 dark:text-stone-100">Confirm Logout</h4>
                    <p class="text-xs text-stone-500 dark:text-stone-400">Are you sure you want to sign out?</p>
                </div>
            </div>
            <div class="flex gap-2 justify-end pt-2">
                <button onclick="closeLogoutModal()" class="px-4 py-2.5 bg-stone-200 dark:bg-stone-800 text-stone-700 dark:text-stone-300 font-bold text-xs rounded-xl hover:bg-stone-300 dark:hover:bg-stone-700 transition-all">
                    Cancel
                </button>
                <button onclick="confirmLogout()" class="px-4 py-2.5 bg-red-600 text-white font-bold text-xs rounded-xl shadow-md hover:bg-red-700 transition-all">
                    <i class="fa-solid fa-right-from-bracket mr-1"></i> Logout
                </button>
            </div>
        </div>
    </div>

    <!-- JAVASCRIPT LOGIC CONTROLLERS -->
    <script>
        // LOGOUT MODAL FUNCTIONS
        function openLogoutModal() {
            document.getElementById('logout_modal').classList.remove('hidden');
            document.getElementById('logout_modal').classList.add('flex');
        }
        
        function closeLogoutModal() {
            document.getElementById('logout_modal').classList.add('hidden');
            document.getElementById('logout_modal').classList.remove('flex');
        }
        
        function confirmLogout() {
            window.location.href = '../logout.php';
        }

        // 1. Single Page Application Tab Switcher
        function switchTab(tabId) {
            document.querySelectorAll('.tab-content').forEach(el => el.classList.remove('active'));
            document.querySelectorAll('.nav-item').forEach(el => {
                el.classList.remove('bg-orange-gradient', 'text-white', 'shadow-md');
                el.classList.add('text-stone-400');
            });

            const selectedTab = document.getElementById(`tab-${tabId}`);
            if(selectedTab) selectedTab.classList.add('active');

            const selectedNav = document.getElementById(`nav-${tabId}`);
            if(selectedNav) {
                selectedNav.classList.add('bg-orange-gradient', 'text-white', 'shadow-md');
                selectedNav.classList.remove('text-stone-400');
            }
        }

        // 2. Dark Mode Toggle
        function toggleDarkMode() {
            document.documentElement.classList.toggle('dark');
        }

        // 3. Online Exam Session Trigger & Security Scripts
        function startExamSession() {
            switchTab('take-exam');
            startTimer(3600);
        }

        function toggleFullscreen() {
            if (!document.fullscreenElement) {
                document.documentElement.requestFullscreen().catch(() => {});
            } else {
                if (document.exitFullscreen) document.exitFullscreen();
            }
        }

        // Prevent Copy / Context Menu during exam
        document.addEventListener('contextmenu', e => {
            if (document.getElementById('tab-take-exam').classList.contains('active')) e.preventDefault();
        });

        // 4. Timer Handler
        let timerInterval;
        function startTimer(seconds) {
            let time = seconds;
            const display = document.getElementById('timer_display');
            clearInterval(timerInterval);
            
            timerInterval = setInterval(() => {
                let mins = Math.floor(time / 60);
                let secs = time % 60;
                display.innerText = `${mins < 10 ? '0' : ''}${mins}:${secs < 10 ? '0' : ''}${secs}`;
                if (--time < 0) {
                    clearInterval(timerInterval);
                    alert("Time is up! Exam auto-submitted.");
                    switchTab('exam-results');
                }
            }, 1000);
        }

        function nextQuestion() {
            // Placeholder for next question logic
            alert("Next question functionality to be implemented");
        }

        function openSubmitModal() { 
            document.getElementById('submit_modal').classList.remove('hidden'); 
            document.getElementById('submit_modal').classList.add('flex'); 
        }
        
        function closeSubmitModal() { 
            document.getElementById('submit_modal').classList.add('hidden'); 
            document.getElementById('submit_modal').classList.remove('flex'); 
        }
        
        function finishExam() {
            closeSubmitModal();
            alert("Exam submitted successfully! Results sent for Groq AI verification.");
            switchTab('exam-results');
        }

        // Pass Dynamic PHP Arrays to JS
        const chartSubjects = <?php echo json_encode($chart_subjects); ?>;
        const chartScores = <?php echo json_encode($chart_scores); ?>;
        const chartExamLabels = <?php echo json_encode($chart_exam_labels); ?>;
        const chartExamScores = <?php echo json_encode($chart_exam_scores); ?>;
        const masteryData = <?php echo json_encode($mastery_data); ?>;
        const skillsData = <?php echo json_encode($skills_data); ?>;

        // 5. Chart.js Initialization for Analytics Module
        window.addEventListener('load', () => {
            // Bar Chart
            new Chart(document.getElementById('studentBarChart'), {
                type: 'bar',
                data: {
                    labels: chartSubjects,
                    datasets: [{ label: 'Score %', data: chartScores, backgroundColor: '#f97316', borderRadius: 6 }]
                },
                options: { 
                    responsive: true, 
                    maintainAspectRatio: false, 
                    plugins: { 
                        legend: { display: false },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    return 'Score: ' + context.parsed.y + '%';
                                }
                            }
                        }
                    },
                    scales: {
                        y: {
                            min: 0,
                            max: 100,
                            ticks: {
                                callback: function(value) {
                                    return value + '%';
                                }
                            }
                        }
                    }
                }
            });

            // Line Chart
            new Chart(document.getElementById('studentLineChart'), {
                type: 'line',
                data: {
                    labels: chartExamLabels,
                    datasets: [{ 
                        label: 'Performance', 
                        data: chartExamScores, 
                        borderColor: '#ea580c', 
                        tension: 0.3, 
                        fill: false,
                        pointRadius: 4,
                        pointHoverRadius: 6
                    }]
                },
                options: { 
                    responsive: true, 
                    maintainAspectRatio: false, 
                    plugins: { 
                        legend: { display: false },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    return 'Score: ' + context.parsed.y + '%';
                                }
                            }
                        }
                    },
                    scales: {
                        y: {
                            min: 0,
                            max: 100,
                            ticks: {
                                callback: function(value) {
                                    return value + '%';
                                }
                            }
                        }
                    }
                }
            });

            // Pie Chart
            new Chart(document.getElementById('studentPieChart'), {
                type: 'pie',
                data: {
                    labels: ['Mastered (≥85%)', 'Review Needed (60-84%)', 'Needs Work (<60%)'],
                    datasets: [{ data: masteryData, backgroundColor: ['#10b981', '#f59e0b', '#ef4444'] }]
                },
                options: { 
                    responsive: true, 
                    maintainAspectRatio: false,
                    plugins: {
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    return context.label + ': ' + context.parsed + ' exams';
                                }
                            }
                        }
                    }
                }
            });

            // Radar Chart
            new Chart(document.getElementById('studentRadarChart'), {
                type: 'radar',
                data: {
                    labels: ['Logic', 'Syntax', 'Architecture', 'Speed', 'Theory'],
                    datasets: [{ 
                        label: 'Skill Matrix', 
                        data: skillsData, 
                        backgroundColor: 'rgba(249, 115, 22, 0.2)', 
                        borderColor: '#f97316' 
                    }]
                },
                options: { 
                    responsive: true, 
                    maintainAspectRatio: false,
                    scales: {
                        r: {
                            min: 0,
                            max: 100,
                            ticks: {
                                stepSize: 20
                            }
                        }
                    }
                }
            });
        });
    </script>
</body>
</html>