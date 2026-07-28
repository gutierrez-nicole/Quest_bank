<?php
// includes/teacher_sidebar.php - Teacher Portal Navigation Partial
$currentPage = basename($_SERVER['PHP_SELF']);
?>
<aside class="w-64 bg-stone-950 text-stone-300 flex flex-col justify-between hidden lg:flex z-30 shadow-2xl fixed h-full">
    <div class="flex flex-col h-full overflow-hidden">
        <!-- Sidebar Header -->
        <div class="p-5 border-b border-stone-800 flex items-center justify-between bg-stone-900/80 flex-shrink-0">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 bg-gradient-to-br from-orange-500 to-orange-600 rounded-2xl flex items-center justify-center font-black text-white shadow-lg shadow-orange-600/30">
                    <i class="fa-solid fa-chalkboard-user text-lg"></i>
                </div>
                <div>
                    <h1 class="text-sm font-black tracking-tight text-white">Quest<span class="text-orange-500">Bank</span></h1>
                    <span class="text-[9px] uppercase font-bold text-orange-500 tracking-wider">Faculty Portal</span>
                </div>
            </div>
        </div>

        <!-- Navigation Links -->
        <nav class="p-4 space-y-1 overflow-y-auto custom-scrollbar flex-grow text-xs">
            <a href="dashboard.php" class="flex items-center gap-3 px-3 py-2.5 rounded-xl font-bold transition-all <?php echo $currentPage === 'dashboard.php' ? 'bg-orange-600 text-white shadow-lg shadow-orange-600/30' : 'text-stone-400 hover:text-white hover:bg-stone-900'; ?>">
                <i class="fa-solid fa-chart-line w-4"></i> Dashboard Overview
            </a>
            <a href="create_exam.php" class="flex items-center gap-3 px-3 py-2.5 rounded-xl font-bold transition-all <?php echo $currentPage === 'create_exam.php' ? 'bg-orange-600 text-white shadow-lg shadow-orange-600/30' : 'text-stone-400 hover:text-white hover:bg-stone-900'; ?>">
                <i class="fa-solid fa-file-circle-plus w-4"></i> Create Exam Bank
            </a>
            <a href="generate_ai.php" class="flex items-center gap-3 px-3 py-2.5 rounded-xl font-bold transition-all <?php echo $currentPage === 'generate_ai.php' ? 'bg-orange-600 text-white shadow-lg shadow-orange-600/30' : 'text-stone-400 hover:text-white hover:bg-stone-900'; ?>">
                <i class="fa-solid fa-wand-magic-sparkles w-4"></i> AI Exam Generator
            </a>
            <a href="upload_check.php" class="flex items-center gap-3 px-3 py-2.5 rounded-xl font-bold transition-all <?php echo $currentPage === 'upload_check.php' ? 'bg-orange-600 text-white shadow-lg shadow-orange-600/30' : 'text-stone-400 hover:text-white hover:bg-stone-900'; ?>">
                <i class="fa-solid fa-expand w-4"></i> AI Exam OCR Checker
            </a>
            <a href="upload_lessons.php" class="flex items-center gap-3 px-3 py-2.5 rounded-xl font-bold transition-all <?php echo $currentPage === 'upload_lessons.php' ? 'bg-orange-600 text-white shadow-lg shadow-orange-600/30' : 'text-stone-400 hover:text-white hover:bg-stone-900'; ?>">
                <i class="fa-solid fa-cloud-arrow-up w-4"></i> Upload Lessons
            </a>
            <a href="manage_students.php" class="flex items-center gap-3 px-3 py-2.5 rounded-xl font-bold transition-all <?php echo $currentPage === 'manage_students.php' ? 'bg-orange-600 text-white shadow-lg shadow-orange-600/30' : 'text-stone-400 hover:text-white hover:bg-stone-900'; ?>">
                <i class="fa-solid fa-users-rectangle w-4"></i> Student Roster
            </a>
            <a href="reports.php" class="flex items-center gap-3 px-3 py-2.5 rounded-xl font-bold transition-all <?php echo $currentPage === 'reports.php' ? 'bg-orange-600 text-white shadow-lg shadow-orange-600/30' : 'text-stone-400 hover:text-white hover:bg-stone-900'; ?>">
                <i class="fa-solid fa-square-poll-vertical w-4"></i> Reports & Analytics
            </a>
            <a href="backup.php" class="flex items-center gap-3 px-3 py-2.5 rounded-xl font-bold transition-all <?php echo $currentPage === 'backup.php' ? 'bg-orange-600 text-white shadow-lg shadow-orange-600/30' : 'text-stone-400 hover:text-white hover:bg-stone-900'; ?>">
                <i class="fa-solid fa-database w-4"></i> Backup & Restore
            </a>
            <a href="profile_settings.php" class="flex items-center gap-3 px-3 py-2.5 rounded-xl font-bold transition-all <?php echo $currentPage === 'profile_settings.php' ? 'bg-orange-600 text-white shadow-lg shadow-orange-600/30' : 'text-stone-400 hover:text-white hover:bg-stone-900'; ?>">
                <i class="fa-solid fa-user-gear w-4"></i> Profile Settings
            </a>
        </nav>

        <!-- Sidebar Footer -->
        <div class="p-4 border-t border-stone-800 bg-stone-900/50 flex-shrink-0">
            <a href="../logout.php" class="flex items-center justify-center gap-2 w-full bg-rose-600/10 hover:bg-rose-600 text-rose-500 hover:text-white text-xs font-bold py-2.5 rounded-xl transition-all">
                <i class="fa-solid fa-right-from-bracket"></i> Sign Out
            </a>
        </div>
    </div>
</aside>
