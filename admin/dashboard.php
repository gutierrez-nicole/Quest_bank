<?php
require_once __DIR__ . '/../app/bootstrap.php';

AuthService::enforceRole('admin');
$pdo = getDBConnection();

try {
    $stmt = $pdo->prepare("SELECT fullname, username, email FROM users WHERE id = ?");
    $stmt->execute([getCurrentUserId()]);
    $admin = $stmt->fetch();

    $stats = $pdo->query("
        SELECT
            (SELECT COUNT(*) FROM users WHERE role = 'teacher') AS teachers_count,
            (SELECT COUNT(*) FROM users WHERE role = 'student') AS students_count,
            (SELECT COUNT(DISTINCT subject) FROM exams) AS subjects_count,
            (SELECT COUNT(*) FROM exams) AS exams_count,
            (SELECT COUNT(*) FROM exam_submissions WHERE review_status = 'published' AND (status = 'Pass' OR percentage >= 75)) AS completed_exams,
            (SELECT COUNT(*) FROM exam_submissions WHERE review_status = 'published' AND (status = 'Fail' OR percentage < 75)) AS pending_exams,
            (SELECT COUNT(*) FROM exam_submissions WHERE review_status = 'published') AS total_submissions,
            (SELECT AVG(percentage) FROM exam_submissions WHERE review_status = 'published') AS avg_score,
            (SELECT COUNT(*) FROM exam_submissions WHERE review_status = 'pending_review') AS pie_pending
    ")->fetch(PDO::FETCH_ASSOC);

    $teachers_count = intval($stats['teachers_count'] ?? 0);
    $students_count = intval($stats['students_count'] ?? 0);
    $subjects_count = intval($stats['subjects_count'] ?? 0);
    $exams_count = intval($stats['exams_count'] ?? 0);
    $completed_exams = intval($stats['completed_exams'] ?? 0);
    $pending_exams = intval($stats['pending_exams'] ?? 0);
    $total_submissions = intval($stats['total_submissions'] ?? 0);
    $avg_score = $stats['avg_score'] !== null ? floatval($stats['avg_score']) : 0.0;
    $pie_pending = intval($stats['pie_pending'] ?? 0);

    $pass_rate = $total_submissions > 0 ? round(($completed_exams / $total_submissions) * 100, 1) : 0.0;
    $ai_prediction = number_format($pass_rate, 1) . '% Pass Rate';
    $pie_passed = $completed_exams;
    $pie_failed = $pending_exams;

    // Real Civil Engineering specialization average scores from live database
    $ce_stmt = $pdo->query("
        SELECT COALESCE(e.specialization, 'General') AS spec, AVG(es.percentage) AS avg_pct 
        FROM exam_submissions es 
        LEFT JOIN exams e ON es.exam_id = e.id 
        WHERE es.review_status = 'published'
        GROUP BY spec 
        LIMIT 5
    ");
    $ce_scores_raw = $ce_stmt->fetchAll(PDO::FETCH_ASSOC);
    $ce_scores = array_map(function($r) { return round(floatval($r['avg_pct']), 1); }, $ce_scores_raw);
    if (empty($ce_scores)) {
        $ce_scores = [0, 0, 0, 0, 0];
    }

    $latest_activities = [];
    try {
        $stmtLogs = $pdo->query("
            SELECT al.id, al.action_description, al.created_at, u.fullname, u.role, u.email 
            FROM activity_logs al 
            JOIN users u ON al.user_id = u.id 
            ORDER BY al.id DESC 
            LIMIT 5
        ");
        $latest_activities = $stmtLogs->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $latest_activities = [];
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
    <title>QuestBank - Admin AI Console</title>
    
    <script src="https://cdn.tailwindcss.com"></script>
    
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght=300;400;500;600;700;800&display=swap" rel="stylesheet">

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
        
        .custom-scrollbar::-webkit-scrollbar { width: 4px; }
        .custom-scrollbar::-webkit-scrollbar-thumb { background: #3f3f46; border-radius: 10px; }

        /* Animations & Skeleton Styles */
        .tab-content { display: none; opacity: 0; transition: opacity 0.3s ease-in-out; }
        .tab-content.active { display: block; opacity: 1; animation: fadeIn 0.4s ease-out; }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(8px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .animate-card-hover { transition: all 0.25s ease; }
        .animate-card-hover:hover { transform: translateY(-4px); box-shadow: 0 12px 24px -6px rgba(249, 115, 22, 0.15); }

        .skeleton {
            background: linear-gradient(90deg, #e5e7eb 25%, #f3f4f6 50%, #e5e7eb 75%);
            background-size: 200% 100%;
            animation: skeleton-loading 1.5s infinite;
        }
        .dark .skeleton {
            background: linear-gradient(90deg, #27272a 25%, #3f3f46 50%, #27272a 75%);
            background-size: 200% 100%;
        }

        @keyframes skeleton-loading {
            0% { background-position: 200% 0; }
            100% { background-position: -200% 0; }
        }
    </style>
</head>
<body class="bg-[#f3f4f6] dark:bg-[#09090b] text-stone-800 dark:text-stone-100 min-h-screen flex transition-colors duration-300">

    
    <?php require_once __DIR__ . '/../includes/admin_sidebar.php'; ?>

    
    <main class="flex-grow flex flex-col min-w-0 ml-16 lg:ml-64 min-h-screen">
        
        
        <header class="bg-white dark:bg-stone-900 border-b border-stone-200 dark:border-stone-800 px-6 py-4 flex items-center justify-between sticky top-0 z-20 shadow-xs">
            <div>
                <h2 class="text-base md:text-lg font-extrabold text-stone-800 dark:text-stone-100">Administrator Command Console</h2>
                <p class="text-xs text-stone-400">System infrastructure tracking, AI performance models, and active audit telemetry.</p>
            </div>
            
            <div class="flex items-center gap-3 md:gap-4">
                
                <button onclick="toggleDarkMode()" class="w-10 h-10 rounded-xl border border-stone-200 dark:border-stone-800 flex items-center justify-center text-stone-500 dark:text-stone-300 hover:bg-stone-100 dark:hover:bg-stone-800 transition-all">
                    <i class="fa-solid fa-moon text-sm dark:hidden"></i>
                    <i class="fa-solid fa-sun text-sm hidden dark:block text-amber-400"></i>
                </button>

                
                <div class="relative">
                    <button id="admin_notif_btn" onclick="toggleAdminNotifDropdown(event)" class="w-10 h-10 rounded-xl border border-stone-200 dark:border-stone-800 flex items-center justify-center text-stone-500 dark:text-stone-300 hover:bg-stone-100 dark:hover:bg-stone-800 transition-all relative">
                        <i class="fa-solid fa-bell text-sm"></i>
                        <span class="absolute top-2.5 right-2.5 w-2 h-2 bg-orange-500 rounded-full animate-ping"></span>
                        <span class="absolute top-2.5 right-2.5 w-2 h-2 bg-orange-500 rounded-full"></span>
                    </button>

                    <div id="admin_notif_dropdown" class="absolute right-0 mt-3 w-80 sm:w-96 bg-white dark:bg-stone-900 border border-stone-200 dark:border-stone-800 rounded-2xl shadow-2xl p-4 hidden z-50 space-y-3 animate-fadeIn">
                        <div class="flex items-center justify-between border-b border-stone-100 dark:border-stone-800 pb-2.5">
                            <h4 class="text-xs font-black uppercase text-stone-800 dark:text-stone-100 flex items-center gap-1.5">
                                <i class="fa-solid fa-bell text-orange-500"></i> Admin System Telemetry Alerts
                            </h4>
                            <span class="text-[9px] bg-orange-100 dark:bg-orange-950 text-orange-700 dark:text-orange-400 font-extrabold px-2 py-0.5 rounded-full">Live Alerts</span>
                        </div>

                        <div class="space-y-2.5 text-xs max-h-72 overflow-y-auto custom-scrollbar">
                            <?php if (!empty($latest_activities)): ?>
                                <?php foreach (array_slice($latest_activities, 0, 4) as $notif): ?>
                                    <div class="p-3 rounded-xl border border-stone-100 dark:border-stone-800 bg-stone-50/60 dark:bg-stone-800/40 space-y-1 hover:border-orange-300 transition-all">
                                        <div class="flex items-center justify-between">
                                            <span class="font-extrabold text-stone-800 dark:text-stone-100 text-[11px]"><?php echo htmlspecialchars($notif['fullname']); ?></span>
                                            <span class="text-[9px] text-stone-400 font-medium"><?php echo date('h:i A', strtotime($notif['created_at'])); ?></span>
                                        </div>
                                        <p class="text-[11px] text-stone-500 dark:text-stone-400 leading-snug"><?php echo htmlspecialchars($notif['action_description']); ?></p>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="text-center py-6 text-stone-400 text-xs">
                                    <i class="fa-solid fa-inbox text-2xl mb-1 block"></i>
                                    No new system notifications.
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="pt-2 border-t border-stone-100 dark:border-stone-800 flex justify-between items-center text-xs">
                            <span class="text-[10px] text-stone-400 font-semibold"><i class="fa-solid fa-shield-halved text-emerald-500 mr-1"></i> Multi-Role Telemetry Active</span>
                            <a href="activity_logs.php" class="text-orange-600 dark:text-orange-400 font-bold hover:underline text-[11px]">View All Logs →</a>
                        </div>
                    </div>
                </div>
                
                
                <div class="flex items-center gap-3 pl-3 border-l border-stone-200 dark:border-stone-800">
                    <div class="w-10 h-10 rounded-xl bg-orange-100 dark:bg-orange-950/60 text-orange-600 font-black flex items-center justify-center shadow-inner text-sm">
                        AD
                    </div>
                    <div class="hidden sm:block text-left">
                        <p class="text-xs font-bold text-stone-800 dark:text-stone-100 leading-tight"><?php echo htmlspecialchars($admin['fullname'] ?? 'Global Administrator'); ?></p>
                        <p class="text-[10px] text-stone-400 font-semibold uppercase tracking-wider">System Administrator</p>
                    </div>
                </div>
            </div>
        </header>

        
        <div class="p-6 md:p-8 space-y-6">

            
            <div id="skeleton-container" class="grid grid-cols-2 lg:grid-cols-4 gap-4">
                <div class="h-24 skeleton rounded-2xl"></div>
                <div class="h-24 skeleton rounded-2xl"></div>
                <div class="h-24 skeleton rounded-2xl"></div>
                <div class="h-24 skeleton rounded-2xl"></div>
            </div>

            
            <div id="tab-dashboard" class="tab-content active space-y-6">
                
                
                <div class="grid grid-cols-2 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                    <div class="bg-white dark:bg-stone-900 border border-stone-200 dark:border-stone-800 p-4 rounded-2xl shadow-sm animate-card-hover flex items-center justify-between">
                        <div>
                            <p class="text-[10px] font-extrabold uppercase text-stone-400">Total Teachers</p>
                            <h4 class="text-2xl font-black text-stone-800 dark:text-stone-100 mt-1"><?php echo $teachers_count; ?></h4>
                        </div>
                        <div class="p-3 bg-orange-100 dark:bg-orange-950/60 text-orange-600 rounded-xl"><i class="fa-solid fa-chalkboard-user text-lg"></i></div>
                    </div>

                    <div class="bg-white dark:bg-stone-900 border border-stone-200 dark:border-stone-800 p-4 rounded-2xl shadow-sm animate-card-hover flex items-center justify-between">
                        <div>
                            <p class="text-[10px] font-extrabold uppercase text-stone-400">Total Students</p>
                            <h4 class="text-2xl font-black text-stone-800 dark:text-stone-100 mt-1"><?php echo number_format($students_count); ?></h4>
                        </div>
                        <div class="p-3 bg-amber-100 dark:bg-amber-950/60 text-amber-600 rounded-xl"><i class="fa-solid fa-graduation-cap text-lg"></i></div>
                    </div>

                    <div class="bg-white dark:bg-stone-900 border border-stone-200 dark:border-stone-800 p-4 rounded-2xl shadow-sm animate-card-hover flex items-center justify-between">
                        <div>
                            <p class="text-[10px] font-extrabold uppercase text-stone-400">Total Subjects</p>
                            <h4 class="text-2xl font-black text-stone-800 dark:text-stone-100 mt-1"><?php echo $subjects_count; ?></h4>
                        </div>
                        <div class="p-3 bg-purple-100 dark:bg-purple-950/60 text-purple-600 rounded-xl"><i class="fa-solid fa-book-bookmark text-lg"></i></div>
                    </div>

                    <div class="bg-white dark:bg-stone-900 border border-stone-200 dark:border-stone-800 p-4 rounded-2xl shadow-sm animate-card-hover flex items-center justify-between">
                        <div>
                            <p class="text-[10px] font-extrabold uppercase text-stone-400">Total Exams Created</p>
                            <h4 class="text-2xl font-black text-stone-800 dark:text-stone-100 mt-1"><?php echo $exams_count; ?></h4>
                        </div>
                        <div class="p-3 bg-blue-100 dark:bg-blue-950/60 text-blue-600 rounded-xl"><i class="fa-solid fa-file-signature text-lg"></i></div>
                    </div>

                    <div class="bg-white dark:bg-stone-900 border border-stone-200 dark:border-stone-800 p-4 rounded-2xl shadow-sm animate-card-hover flex items-center justify-between">
                        <div>
                            <p class="text-[10px] font-extrabold uppercase text-stone-400">Pending Review Exams</p>
                            <h4 class="text-2xl font-black text-rose-500 mt-1"><?php echo $pending_exams; ?></h4>
                        </div>
                        <div class="p-3 bg-rose-100 dark:bg-rose-950/60 text-rose-600 rounded-xl"><i class="fa-solid fa-clock-rotate-left text-lg"></i></div>
                    </div>

                    <div class="bg-white dark:bg-stone-900 border border-stone-200 dark:border-stone-800 p-4 rounded-2xl shadow-sm animate-card-hover flex items-center justify-between">
                        <div>
                            <p class="text-[10px] font-extrabold uppercase text-stone-400">Completed Graded Exams</p>
                            <h4 class="text-2xl font-black text-emerald-600 mt-1"><?php echo $completed_exams; ?></h4>
                        </div>
                        <div class="p-3 bg-emerald-100 dark:bg-emerald-950/60 text-emerald-600 rounded-xl"><i class="fa-solid fa-circle-check text-lg"></i></div>
                    </div>

                    <div class="bg-white dark:bg-stone-900 border border-stone-200 dark:border-stone-800 p-4 rounded-2xl shadow-sm animate-card-hover flex items-center justify-between">
                        <div>
                            <p class="text-[10px] font-extrabold uppercase text-stone-400">Average Class Score</p>
                            <h4 class="text-2xl font-black text-orange-500 mt-1"><?php echo number_format($avg_score, 1); ?>%</h4>
                        </div>
                        <div class="p-3 bg-orange-100 dark:bg-orange-950/60 text-orange-600 rounded-xl"><i class="fa-solid fa-chart-line text-lg"></i></div>
                    </div>

                    <div class="bg-white dark:bg-stone-900 border border-stone-200 dark:border-stone-800 p-4 rounded-2xl shadow-sm animate-card-hover flex items-center justify-between">
                        <div>
                            <p class="text-[10px] font-extrabold uppercase text-stone-400">AI Prediction Metric</p>
                            <h4 class="text-2xl font-black text-indigo-500 mt-1"><?php echo $ai_prediction; ?></h4>
                        </div>
                        <div class="p-3 bg-indigo-100 dark:bg-indigo-950/60 text-indigo-600 rounded-xl"><i class="fa-solid fa-brain text-lg"></i></div>
                    </div>
                </div>

                
                <div class="bg-gradient-to-r from-stone-900 to-stone-950 text-white rounded-2xl p-6 shadow-xl border border-stone-800 flex flex-col md:flex-row justify-between items-start md:items-center gap-6">
                    <div class="space-y-1">
                        <span class="bg-orange-600 text-white text-[10px] font-black px-2.5 py-0.5 rounded-md uppercase tracking-wider">AI Predictive Insights Model</span>
                        <h3 class="text-lg font-black text-white">Academic Performance Forecast (S.Y. 2026-2027)</h3>
                        <p class="text-xs text-stone-400 max-w-2xl">Based on historical OCR scan evaluations and student submission velocity, overall student performance reflects an active pass rate of <?php echo $pass_rate; ?>% across evaluation submissions.</p>
                    </div>
                    <button onclick="openAiMatrixModal()" class="bg-orange-600 hover:bg-orange-700 text-white text-xs font-extrabold px-5 py-3 rounded-xl shadow-lg transition-all flex-shrink-0 flex items-center gap-2">
                        View Detailed AI Prediction Matrix <i class="fa-solid fa-wand-magic-sparkles"></i>
                    </button>
                </div>

                
                <div class="grid grid-cols-1 lg:grid-cols-2 xl:grid-cols-3 gap-6">
                    
                    
                    <div class="bg-white dark:bg-stone-900 border border-stone-200 dark:border-stone-800 p-5 rounded-2xl shadow-sm">
                        <h4 class="text-xs font-extrabold uppercase text-stone-700 dark:text-stone-200 mb-3">
                            <i class="fa-solid fa-chart-column text-orange-500 mr-1.5"></i> Department Passing Rates (Bar Chart)
                        </h4>
                        <div class="h-56 w-full"><canvas id="adminBarChart"></canvas></div>
                    </div>

                    
                    <div class="bg-white dark:bg-stone-900 border border-stone-200 dark:border-stone-800 p-5 rounded-2xl shadow-sm">
                        <h4 class="text-xs font-extrabold uppercase text-stone-700 dark:text-stone-200 mb-3">
                            <i class="fa-solid fa-chart-pie text-orange-500 mr-1.5"></i> Exam Submissions Status (Pie Chart)
                        </h4>
                        <div class="h-56 w-full flex justify-center"><canvas id="adminPieChart"></canvas></div>
                    </div>

                    
                    <div class="bg-white dark:bg-stone-900 border border-stone-200 dark:border-stone-800 p-5 rounded-2xl shadow-sm">
                        <h4 class="text-xs font-extrabold uppercase text-stone-700 dark:text-stone-200 mb-3">
                            <i class="fa-solid fa-chart-line text-orange-500 mr-1.5"></i> Monthly Active Users (Line Chart)
                        </h4>
                        <div class="h-56 w-full"><canvas id="adminLineChart"></canvas></div>
                    </div>

                    
                    <div class="bg-white dark:bg-stone-900 border border-stone-200 dark:border-stone-800 p-5 rounded-2xl shadow-sm">
                        <h4 class="text-xs font-extrabold uppercase text-stone-700 dark:text-stone-200 mb-3">
                            <i class="fa-solid fa-chart-area text-orange-500 mr-1.5"></i> System Storage & OCR Traffic (Area Chart)
                        </h4>
                        <div class="h-56 w-full"><canvas id="adminAreaChart"></canvas></div>
                    </div>

                    
                    <div class="bg-white dark:bg-stone-900 border border-stone-200 dark:border-stone-800 p-5 rounded-2xl shadow-sm">
                        <h4 class="text-xs font-extrabold uppercase text-stone-700 dark:text-stone-200 mb-3">
                            <i class="fa-solid fa-compass text-orange-500 mr-1.5"></i> AI Accuracy Metrics (Radar Chart)
                        </h4>
                        <div class="h-56 w-full flex justify-center"><canvas id="adminRadarChart"></canvas></div>
                    </div>

                    
                    <div class="bg-white dark:bg-stone-900 border border-stone-200 dark:border-stone-800 p-5 rounded-2xl shadow-sm space-y-3">
                        <h4 class="text-xs font-extrabold uppercase text-stone-700 dark:text-stone-200">
                            <i class="fa-solid fa-fire text-orange-500 mr-1.5"></i> Peak System Load Density (Heatmap Grid)
                        </h4>
                        <div class="grid grid-cols-7 gap-2 pt-2 text-[10px] font-bold text-center">
                            <div class="p-2 bg-orange-100 text-orange-800 rounded">Mon<br><span class="text-[9px]">0%</span></div>
                            <div class="p-2 bg-orange-200 text-orange-800 rounded">Tue<br><span class="text-[9px]">0%</span></div>
                            <div class="p-2 bg-orange-500 text-white rounded font-black">Wed<br><span class="text-[9px]">0%</span></div>
                            <div class="p-2 bg-orange-300 text-orange-900 rounded">Thu<br><span class="text-[9px]">0%</span></div>
                            <div class="p-2 bg-orange-400 text-white rounded font-black">Fri<br><span class="text-[9px]">0%</span></div>
                            <div class="p-2 bg-stone-100 text-stone-600 rounded">Sat<br><span class="text-[9px]">0%</span></div>
                            <div class="p-2 bg-stone-100 text-stone-600 rounded">Sun<br><span class="text-[9px]">0%</span></div>
                        </div>
                    </div>

                </div>

                
                <div class="bg-white dark:bg-stone-900 border border-stone-200 dark:border-stone-800 rounded-2xl shadow-sm overflow-hidden">
                    <div class="p-6 border-b border-stone-100 dark:border-stone-800 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
                        <div>
                            <h3 class="text-base font-extrabold text-stone-800 dark:text-stone-100 flex items-center gap-2">
                                <i class="fa-solid fa-clipboard-list text-orange-500"></i> Latest System Audit Trail Activities
                            </h3>
                        </div>
                        <a href="activity_logs.php" class="bg-stone-900 hover:bg-orange-600 text-white font-bold text-xs px-4 py-2.5 rounded-xl transition-all shadow-sm">
                            View All Activity Logs
                        </a>
                    </div>
                    
                    <div class="overflow-x-auto">
                        <table class="w-full text-left border-collapse text-xs text-stone-600 dark:text-stone-300">
                            <thead class="bg-stone-50 dark:bg-stone-800/50 text-stone-400 font-extrabold uppercase text-[10px] tracking-wider border-b border-stone-100 dark:border-stone-800">
                                <tr>
                                    <th class="p-4 pl-6">Operator User</th>
                                    <th class="p-4">Role</th>
                                    <th class="p-4">Action Event</th>
                                    <th class="p-4 pr-6 text-right">Timestamp</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-stone-100 dark:divide-stone-800 font-bold">
                                <?php if (!empty($latest_activities)): ?>
                                    <?php foreach ($latest_activities as $act): ?>
                                        <?php 
                                        $role = strtolower($act['role']);
                                        $badgeClass = 'bg-stone-100 dark:bg-stone-800 text-stone-700 dark:text-stone-300';
                                        if ($role === 'teacher') $badgeClass = 'bg-blue-100 dark:bg-blue-950 text-blue-700 dark:text-blue-400';
                                        elseif ($role === 'student') $badgeClass = 'bg-orange-100 dark:bg-orange-950 text-orange-700 dark:text-orange-400';
                                        elseif ($role === 'admin') $badgeClass = 'bg-purple-100 dark:bg-purple-950 text-purple-700 dark:text-purple-400';
                                        ?>
                                        <tr class="hover:bg-stone-50/50 dark:hover:bg-stone-800/30 transition-all">
                                            <td class="p-4 pl-6 text-stone-800 dark:text-stone-100 font-extrabold"><?php echo htmlspecialchars($act['fullname']); ?></td>
                                            <td class="p-4"><span class="px-2.5 py-0.5 rounded-md text-[10px] uppercase font-bold <?php echo $badgeClass; ?>"><?php echo htmlspecialchars($act['role']); ?></span></td>
                                            <td class="p-4 text-stone-600 dark:text-stone-300 font-semibold"><?php echo htmlspecialchars($act['action_description']); ?></td>
                                            <td class="p-4 pr-6 text-stone-400 text-right font-medium"><?php echo date('M d, Y h:i A', strtotime($act['created_at'])); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="4" class="p-6 text-center text-stone-400 font-semibold">No audit trail records found in database.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

            </div>

        </div>
    </main>

    
    <div id="logout_modal" class="fixed inset-0 bg-stone-950/70 backdrop-blur-sm hidden items-center justify-center z-50 p-4">
        <div class="bg-white dark:bg-stone-900 border border-stone-200 dark:border-stone-800 p-6 rounded-2xl max-w-sm w-full space-y-4 shadow-2xl animate-fadeIn">
            <div class="flex items-center gap-3">
                <div class="w-12 h-12 bg-red-100 dark:bg-red-950/60 text-red-600 rounded-xl flex items-center justify-center">
                    <i class="fa-solid fa-right-from-bracket text-xl"></i>
                </div>
                <div>
                    <h4 class="font-extrabold text-base text-stone-800 dark:text-stone-100">Confirm Admin Logout</h4>
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

    
    <div id="ai_matrix_modal" class="fixed inset-0 bg-stone-950/70 backdrop-blur-sm hidden items-center justify-center z-50 p-4">
        <div class="bg-white dark:bg-stone-900 border border-stone-200 dark:border-stone-800 p-6 md:p-8 rounded-3xl max-w-3xl w-full space-y-6 shadow-2xl animate-fadeIn max-h-[90vh] overflow-y-auto custom-scrollbar">
            
            <div class="flex items-center justify-between border-b border-stone-100 dark:border-stone-800 pb-4">
                <div class="flex items-center gap-3">
                    <div class="w-12 h-12 rounded-2xl bg-orange-100 dark:bg-orange-950/70 text-orange-600 dark:text-orange-400 flex items-center justify-center text-xl font-black shadow-inner">
                        <i class="fa-solid fa-brain"></i>
                    </div>
                    <div>
                        <h3 class="text-lg font-black text-stone-800 dark:text-stone-100 flex items-center gap-2">
                            Groq AI Predictive Intelligence Matrix
                            <span class="bg-orange-100 dark:bg-orange-950 text-orange-700 dark:text-orange-400 text-[10px] uppercase font-extrabold px-2.5 py-0.5 rounded-full">S.Y. 2026-2027</span>
                        </h3>
                        <p class="text-xs text-stone-400">Real-time analytical forecast generated from student answer sheet evaluations.</p>
                    </div>
                </div>
                <button onclick="closeAiMatrixModal()" class="text-stone-400 hover:text-stone-700 dark:hover:text-stone-200 font-bold p-2"><i class="fa-solid fa-xmark text-xl"></i></button>
            </div>

            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                <div class="bg-stone-50 dark:bg-stone-800/40 p-3.5 rounded-2xl border border-stone-100 dark:border-stone-800 text-center space-y-1">
                    <p class="text-[9px] font-extrabold uppercase text-stone-400">Predicted Pass Rate</p>
                    <p class="text-xl font-black text-emerald-600 dark:text-emerald-400">Insufficient data</p>
                </div>
                <div class="bg-stone-50 dark:bg-stone-800/40 p-3.5 rounded-2xl border border-stone-100 dark:border-stone-800 text-center space-y-1">
                    <p class="text-[9px] font-extrabold uppercase text-stone-400">OCR Grading Precision</p>
                    <p class="text-xl font-black text-orange-500">Insufficient data</p>
                </div>
                <div class="bg-stone-50 dark:bg-stone-800/40 p-3.5 rounded-2xl border border-stone-100 dark:border-stone-800 text-center space-y-1">
                    <p class="text-[9px] font-extrabold uppercase text-stone-400">Student Risk Factor</p>
                    <p class="text-xl font-black text-blue-600 dark:text-blue-400">Insufficient data</p>
                </div>
                <div class="bg-stone-50 dark:bg-stone-800/40 p-3.5 rounded-2xl border border-stone-100 dark:border-stone-800 text-center space-y-1">
                    <p class="text-[9px] font-extrabold uppercase text-stone-400">Forecast Gain</p>
                    <p class="text-xl font-black text-purple-600 dark:text-purple-400">N/A</p>
                </div>
            </div>

            <div class="space-y-3">
                <h4 class="text-xs font-black uppercase text-stone-700 dark:text-stone-200 tracking-wider">Civil Engineering Subject Performance Projections</h4>
                <div class="overflow-x-auto border border-stone-100 dark:border-stone-800 rounded-2xl">
                    <table class="w-full text-left text-xs">
                        <thead class="bg-stone-50 dark:bg-stone-800/60 text-stone-400 font-extrabold uppercase text-[10px] border-b border-stone-100 dark:border-stone-800">
                            <tr>
                                <th class="p-3.5">Subject / Specialization</th>
                                <th class="p-3.5 text-center">Historical Avg</th>
                                <th class="p-3.5 text-center">AI Projected Rate</th>
                                <th class="p-3.5 text-right">Mastery Index</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-stone-100 dark:divide-stone-800 font-semibold text-stone-700 dark:text-stone-300">
                            <tr class="hover:bg-stone-50/50 dark:hover:bg-stone-800/30">
                                <td colspan="4" class="p-3.5 text-center font-bold text-stone-500">No data available</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="bg-orange-50 dark:bg-orange-950/30 border border-orange-200 dark:border-orange-900/50 p-4 rounded-2xl space-y-1.5 text-xs text-orange-900 dark:text-orange-300">
                <h5 class="font-extrabold flex items-center gap-1.5 text-orange-700 dark:text-orange-400">
                    <i class="fa-solid fa-lightbulb"></i> Groq AI Faculty Remediation Insight
                </h5>
                <p class="leading-relaxed">
                    Insufficient data
                </p>
            </div>

            <div class="pt-3 border-t border-stone-100 dark:border-stone-800 flex justify-end">
                <button onclick="closeAiMatrixModal()" class="px-5 py-2.5 bg-stone-900 hover:bg-orange-600 text-white font-bold text-xs rounded-xl transition-all shadow-md">
                    Close Prediction Matrix
                </button>
            </div>
        </div>
    </div>

    <script>
        function openAiMatrixModal() {
            const modal = document.getElementById('ai_matrix_modal');
            if (modal) {
                modal.classList.remove('hidden');
                modal.classList.add('flex');
            }
        }
        function closeAiMatrixModal() {
            const modal = document.getElementById('ai_matrix_modal');
            if (modal) {
                modal.classList.add('hidden');
                modal.classList.remove('flex');
            }
        }
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

        // Hide Skeleton Loading Container
        window.addEventListener('load', () => {
            const sk = document.getElementById('skeleton-container');
            if (sk) sk.style.display = 'none';
        });

        function toggleDarkMode() {
            document.documentElement.classList.toggle('dark');
        }

        // Initialize Animated Chart.js Graphs
        window.addEventListener('load', () => {
            const chartOptions = {
                responsive: true,
                maintainAspectRatio: false,
                animation: { duration: 1500, easing: 'easeOutQuart' },
                plugins: { legend: { display: false } }
            };

            // 1. BAR CHART (Civil Engineering Specializations)
            new Chart(document.getElementById('adminBarChart'), {
                type: 'bar',
                data: {
                    labels: ['Structural', 'Geotechnical', 'Water Res.', 'Transportation', 'Construction'],
                    datasets: [{ label: 'Specialization Pass Rate (%)', data: <?php echo json_encode($ce_scores); ?>, backgroundColor: '#f97316', borderRadius: 6 }]
                },
                options: chartOptions
            });

            // 2. PIE CHART (Dynamic Submissions Status Ratio)
            new Chart(document.getElementById('adminPieChart'), {
                type: 'pie',
                data: {
                    labels: ['Passed', 'Failed', 'Pending'],
                    datasets: [{ data: [<?php echo "{$pie_passed}, {$pie_failed}, {$pie_pending}"; ?>], backgroundColor: ['#10b981', '#f43f5e', '#f59e0b'] }]
                },
                options: { responsive: true, maintainAspectRatio: false, animation: { duration: 1500 } }
            });

            // 3. LINE CHART
            new Chart(document.getElementById('adminLineChart'), {
                type: 'line',
                data: {
                    labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul'],
                    datasets: [{ label: 'Active Telemetry', data: [], borderColor: '#f97316', tension: 0.4, fill: false }]
                },
                options: chartOptions
            });

            // 4. AREA CHART
            new Chart(document.getElementById('adminAreaChart'), {
                type: 'line',
                data: {
                    labels: ['Week 1', 'Week 2', 'Week 3', 'Week 4'],
                    datasets: [{ label: 'Groq OCR Traffic', data: [], borderColor: '#ea580c', backgroundColor: 'rgba(249, 115, 22, 0.2)', fill: true, tension: 0.3 }]
                },
                options: chartOptions
            });

            // 5. RADAR CHART (Fixed legend undefined bug)
            new Chart(document.getElementById('adminRadarChart'), {
                type: 'radar',
                data: {
                    labels: ['OCR Vision', 'Groq Speed', 'Accuracy', 'Database Sync', 'Security'],
                    datasets: [{ label: 'Engine Health Index %', data: [0, 0, 0, 0, 0], backgroundColor: 'rgba(249, 115, 22, 0.2)', borderColor: '#f97316' }]
                },
                options: { 
                    responsive: true, 
                    maintainAspectRatio: false, 
                    animation: { duration: 1500 },
                    plugins: {
                        legend: {
                            display: false
                        }
                    }
                }
            });
        });

        function toggleAdminNotifDropdown(event) {
            if (event) event.stopPropagation();
            const dropdown = document.getElementById('admin_notif_dropdown');
            if (dropdown) {
                dropdown.classList.toggle('hidden');
            }
        }

        document.addEventListener('click', function(e) {
            const dropdown = document.getElementById('admin_notif_dropdown');
            const btn = document.getElementById('admin_notif_btn');
            if (dropdown && btn && !dropdown.contains(e.target) && !btn.contains(e.target)) {
                dropdown.classList.add('hidden');
            }
        });
    </script>
</body>
</html>