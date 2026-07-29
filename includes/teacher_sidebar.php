<?php

$currentPage = basename($_SERVER['PHP_SELF']);
?>
<aside class="w-16 lg:w-64 bg-stone-950 text-stone-300 flex flex-col justify-between z-30 shadow-2xl fixed h-full transition-all duration-300">
    <div class="flex flex-col h-full overflow-hidden">
        
        <div class="p-3 lg:p-5 border-b border-stone-800 flex items-center justify-center lg:justify-start bg-stone-900/80 flex-shrink-0">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 bg-gradient-to-br from-orange-500 to-orange-600 rounded-2xl flex items-center justify-center font-black text-white shadow-lg shadow-orange-600/30 flex-shrink-0">
                    <i class="fa-solid fa-chalkboard-user text-lg"></i>
                </div>
                <div class="hidden lg:block">
                    <h1 class="text-sm font-black tracking-tight text-white">Quest<span class="text-orange-500">Bank</span></h1>
                    <span class="text-[9px] uppercase font-bold text-orange-500 tracking-wider">Faculty Portal</span>
                </div>
            </div>
        </div>

        
        <nav class="p-2 lg:p-4 space-y-1 overflow-y-auto custom-scrollbar flex-grow text-xs">
            <a href="dashboard.php" title="Dashboard Overview" class="flex items-center justify-center lg:justify-start gap-3 px-3 py-2.5 rounded-xl font-bold transition-all <?php echo $currentPage === 'dashboard.php' ? 'bg-orange-600 text-white shadow-lg shadow-orange-600/30' : 'text-stone-400 hover:text-white hover:bg-stone-900'; ?>">
                <i class="fa-solid fa-chart-line text-sm text-center"></i> <span class="hidden lg:inline-block">Dashboard Overview</span>
            </a>
            <a href="create_exam.php" title="Create Exam Bank" class="flex items-center justify-center lg:justify-start gap-3 px-3 py-2.5 rounded-xl font-bold transition-all <?php echo $currentPage === 'create_exam.php' ? 'bg-orange-600 text-white shadow-lg shadow-orange-600/30' : 'text-stone-400 hover:text-white hover:bg-stone-900'; ?>">
                <i class="fa-solid fa-file-circle-plus text-sm text-center"></i> <span class="hidden lg:inline-block">Create Exam Bank</span>
            </a>
            <a href="generate_ai.php" title="AI Exam Generator" class="flex items-center justify-center lg:justify-start gap-3 px-3 py-2.5 rounded-xl font-bold transition-all <?php echo $currentPage === 'generate_ai.php' ? 'bg-orange-600 text-white shadow-lg shadow-orange-600/30' : 'text-stone-400 hover:text-white hover:bg-stone-900'; ?>">
                <i class="fa-solid fa-wand-magic-sparkles text-sm text-center"></i> <span class="hidden lg:inline-block">AI Exam Generator</span>
            </a>
            <a href="upload_check.php" title="AI Exam OCR Checker" class="flex items-center justify-center lg:justify-start gap-3 px-3 py-2.5 rounded-xl font-bold transition-all <?php echo $currentPage === 'upload_check.php' ? 'bg-orange-600 text-white shadow-lg shadow-orange-600/30' : 'text-stone-400 hover:text-white hover:bg-stone-900'; ?>">
                <i class="fa-solid fa-expand text-sm text-center"></i> <span class="hidden lg:inline-block">AI Exam OCR Checker</span>
            </a>
            <a href="upload_lessons.php" title="Upload Lessons" class="flex items-center justify-center lg:justify-start gap-3 px-3 py-2.5 rounded-xl font-bold transition-all <?php echo $currentPage === 'upload_lessons.php' ? 'bg-orange-600 text-white shadow-lg shadow-orange-600/30' : 'text-stone-400 hover:text-white hover:bg-stone-900'; ?>">
                <i class="fa-solid fa-cloud-arrow-up text-sm text-center"></i> <span class="hidden lg:inline-block">Upload Lessons</span>
            </a>
            <a href="manage_students.php" title="Student Roster" class="flex items-center justify-center lg:justify-start gap-3 px-3 py-2.5 rounded-xl font-bold transition-all <?php echo $currentPage === 'manage_students.php' ? 'bg-orange-600 text-white shadow-lg shadow-orange-600/30' : 'text-stone-400 hover:text-white hover:bg-stone-900'; ?>">
                <i class="fa-solid fa-users-rectangle text-sm text-center"></i> <span class="hidden lg:inline-block">Student Roster</span>
            </a>
            <a href="reports.php" title="Reports & Analytics" class="flex items-center justify-center lg:justify-start gap-3 px-3 py-2.5 rounded-xl font-bold transition-all <?php echo $currentPage === 'reports.php' ? 'bg-orange-600 text-white shadow-lg shadow-orange-600/30' : 'text-stone-400 hover:text-white hover:bg-stone-900'; ?>">
                <i class="fa-solid fa-square-poll-vertical text-sm text-center"></i> <span class="hidden lg:inline-block">Reports & Analytics</span>
            </a>
            <a href="backup.php" title="Backup & Restore" class="flex items-center justify-center lg:justify-start gap-3 px-3 py-2.5 rounded-xl font-bold transition-all <?php echo $currentPage === 'backup.php' ? 'bg-orange-600 text-white shadow-lg shadow-orange-600/30' : 'text-stone-400 hover:text-white hover:bg-stone-900'; ?>">
                <i class="fa-solid fa-database text-sm text-center"></i> <span class="hidden lg:inline-block">Backup & Restore</span>
            </a>
            <a href="profile_settings.php" title="Profile Settings" class="flex items-center justify-center lg:justify-start gap-3 px-3 py-2.5 rounded-xl font-bold transition-all <?php echo $currentPage === 'profile_settings.php' ? 'bg-orange-600 text-white shadow-lg shadow-orange-600/30' : 'text-stone-400 hover:text-white hover:bg-stone-900'; ?>">
                <i class="fa-solid fa-user-gear text-sm text-center"></i> <span class="hidden lg:inline-block">Profile Settings</span>
            </a>
        </nav>

        
        <div class="p-2 lg:p-4 border-t border-stone-800 bg-stone-900/50 flex-shrink-0">
            <a href="../logout.php" title="Sign Out" class="flex items-center justify-center gap-2 w-full bg-rose-600/10 hover:bg-rose-600 text-rose-500 hover:text-white text-xs font-bold py-2.5 rounded-xl transition-all">
                <i class="fa-solid fa-right-from-bracket"></i> <span class="hidden lg:inline-block">Sign Out</span>
            </a>
        </div>
    </div>
</aside>
