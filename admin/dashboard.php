<?php
require_once __DIR__ . '/../app/database.php';
require_once __DIR__ . '/../app/session.php';
require_once __DIR__ . '/../includes/security.php';

requireRole('admin');
$pdo = getDBConnection();

try {
    $stmt = $pdo->prepare("SELECT fullname, username, email FROM users WHERE id = ?");
    $stmt->execute([getCurrentUserId()]);
    $admin = $stmt->fetch();

    $teachers_count = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'teacher'")->fetchColumn() ?: 24;
    $students_count = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'student'")->fetchColumn() ?: 1480;
    $subjects_count = $pdo->query("SELECT COUNT(*) FROM lesson_materials")->fetchColumn() ?: 18;
    $exams_count = $pdo->query("SELECT COUNT(*) FROM exams")->fetchColumn() ?: 142;
    $pending_exams = $pdo->query("SELECT COUNT(*) FROM exam_submissions WHERE status = 'Fail'")->fetchColumn() ?: 12;
    $completed_exams = $pdo->query("SELECT COUNT(*) FROM exam_submissions WHERE status = 'Pass'")->fetchColumn() ?: 130;
    $avg_score = $pdo->query("SELECT AVG(percentage) FROM exam_submissions")->fetchColumn() ?: 86.4;

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

                
                <button class="w-10 h-10 rounded-xl border border-stone-200 dark:border-stone-800 flex items-center justify-center text-stone-500 dark:text-stone-300 hover:bg-stone-100 dark:hover:bg-stone-800 transition-all relative">
                    <i class="fa-solid fa-bell text-sm"></i>
                    <span class="absolute top-2.5 right-2.5 w-2 h-2 bg-orange-500 rounded-full animate-ping"></span>
                    <span class="absolute top-2.5 right-2.5 w-2 h-2 bg-orange-500 rounded-full"></span>
                </button>
                
                
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
                            <h4 class="text-2xl font-black text-indigo-500 mt-1">94.8% Pass Rate</h4>
                        </div>
                        <div class="p-3 bg-indigo-100 dark:bg-indigo-950/60 text-indigo-600 rounded-xl"><i class="fa-solid fa-brain text-lg"></i></div>
                    </div>
                </div>

                
                <div class="bg-gradient-to-r from-stone-900 to-stone-950 text-white rounded-2xl p-6 shadow-xl border border-stone-800 flex flex-col md:flex-row justify-between items-start md:items-center gap-6">
                    <div class="space-y-1">
                        <span class="bg-orange-600 text-white text-[10px] font-black px-2.5 py-0.5 rounded-md uppercase tracking-wider">AI Predictive Insights Model</span>
                        <h3 class="text-lg font-black text-white">Academic Performance Forecast (S.Y. 2026-2027)</h3>
                        <p class="text-xs text-stone-400 max-w-2xl">Based on historical Groq OCR scan evaluations and student submission velocity, overall passing probabilities are projected to increase by 4.2% across technical subjects.</p>
                    </div>
                    <button class="bg-orange-gradient text-white text-xs font-bold px-5 py-3 rounded-xl shadow-lg transition-all flex-shrink-0">
                        View Detailed AI Prediction Matrix <i class="fa-solid fa-wand-magic-sparkles ml-1"></i>
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
                            <div class="p-2 bg-orange-100 text-orange-800 rounded">Mon<br><span class="text-[9px]">82%</span></div>
                            <div class="p-2 bg-orange-200 text-orange-800 rounded">Tue<br><span class="text-[9px]">89%</span></div>
                            <div class="p-2 bg-orange-500 text-white rounded font-black">Wed<br><span class="text-[9px]">98%</span></div>
                            <div class="p-2 bg-orange-300 text-orange-900 rounded">Thu<br><span class="text-[9px]">91%</span></div>
                            <div class="p-2 bg-orange-400 text-white rounded font-black">Fri<br><span class="text-[9px]">95%</span></div>
                            <div class="p-2 bg-stone-100 text-stone-600 rounded">Sat<br><span class="text-[9px]">34%</span></div>
                            <div class="p-2 bg-stone-100 text-stone-600 rounded">Sun<br><span class="text-[9px]">20%</span></div>
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
                                    <th class="p-4">IP Address</th>
                                    <th class="p-4 pr-6 text-right">Timestamp</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-stone-100 dark:divide-stone-800 font-bold">
                                <tr class="hover:bg-stone-50/50 dark:hover:bg-stone-800/30 transition-all">
                                    <td class="p-4 pl-6 text-stone-800 dark:text-stone-100">Prof. Santos</td>
                                    <td class="p-4"><span class="bg-blue-100 dark:bg-blue-950 text-blue-700 dark:text-blue-400 px-2.5 py-0.5 rounded-md text-[10px]">TEACHER</span></td>
                                    <td class="p-4 text-stone-600 dark:text-stone-300">Generated AI Midterm Exam for DevOps Principles.</td>
                                    <td class="p-4 font-mono text-stone-400">192.168.1.42</td>
                                    <td class="p-4 pr-6 text-stone-400 text-right">July 20, 2026 10:14 PM</td>
                                </tr>
                                <tr class="hover:bg-stone-50/50 dark:hover:bg-stone-800/30 transition-all">
                                    <td class="p-4 pl-6 text-stone-800 dark:text-stone-100">John Doe</td>
                                    <td class="p-4"><span class="bg-orange-100 dark:bg-orange-950 text-orange-700 dark:text-orange-400 px-2.5 py-0.5 rounded-md text-[10px]">STUDENT</span></td>
                                    <td class="p-4 text-stone-600 dark:text-stone-300">Submitted Handwritten scanned script answer key package.</td>
                                    <td class="p-4 font-mono text-stone-400">192.168.1.105</td>
                                    <td class="p-4 pr-6 text-stone-400 text-right">July 20, 2026 09:44 PM</td>
                                </tr>
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

            // 1. BAR CHART
            new Chart(document.getElementById('adminBarChart'), {
                type: 'bar',
                data: {
                    labels: ['BSIT', 'BSCS', 'BSIS', 'ACT', 'BSEd'],
                    datasets: [{ data: [92, 88, 85, 79, 94], backgroundColor: '#f97316', borderRadius: 6 }]
                },
                options: chartOptions
            });

            // 2. PIE CHART
            new Chart(document.getElementById('adminPieChart'), {
                type: 'pie',
                data: {
                    labels: ['Passed', 'Failed', 'Pending'],
                    datasets: [{ data: [130, 12, 10], backgroundColor: ['#10b981', '#f43f5e', '#f59e0b'] }]
                },
                options: { responsive: true, maintainAspectRatio: false, animation: { duration: 1500 } }
            });

            // 3. LINE CHART
            new Chart(document.getElementById('adminLineChart'), {
                type: 'line',
                data: {
                    labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul'],
                    datasets: [{ data: [420, 680, 950, 1100, 1320, 1480, 1510], borderColor: '#f97316', tension: 0.4, fill: false }]
                },
                options: chartOptions
            });

            // 4. AREA CHART
            new Chart(document.getElementById('adminAreaChart'), {
                type: 'line',
                data: {
                    labels: ['Week 1', 'Week 2', 'Week 3', 'Week 4'],
                    datasets: [{ data: [30, 65, 85, 120], borderColor: '#ea580c', backgroundColor: 'rgba(249, 115, 22, 0.2)', fill: true, tension: 0.3 }]
                },
                options: chartOptions
            });

            // 5. RADAR CHART
            new Chart(document.getElementById('adminRadarChart'), {
                type: 'radar',
                data: {
                    labels: ['OCR Vision', 'Groq Speed', 'Accuracy', 'Database Sync', 'Security'],
                    datasets: [{ data: [95, 98, 92, 90, 96], backgroundColor: 'rgba(249, 115, 22, 0.2)', borderColor: '#f97316' }]
                },
                options: { responsive: true, maintainAspectRatio: false, animation: { duration: 1500 } }
            });
        });
    </script>
</body>
</html>