<?php

$currentPage = basename($_SERVER['PHP_SELF']);
?>
<aside class="w-16 lg:w-64 bg-stone-950 text-stone-300 flex flex-col justify-between z-30 shadow-2xl fixed h-full transition-all duration-300">
    <div class="flex flex-col h-full overflow-hidden">
        
        <div class="p-3 lg:p-5 border-b border-stone-800 flex items-center justify-center lg:justify-start bg-stone-900/80 flex-shrink-0">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 bg-gradient-to-br from-orange-500 to-orange-600 rounded-2xl flex items-center justify-center font-black text-white shadow-lg shadow-orange-600/30 flex-shrink-0">
                    <i class="fa-solid fa-user-shield text-lg"></i>
                </div>
                <div class="hidden lg:block">
                    <h1 class="text-sm font-black tracking-tight text-white">Quest<span class="text-orange-500">Bank</span></h1>
                    <span class="text-[9px] uppercase font-bold text-orange-500 tracking-wider">Admin Console</span>
                </div>
            </div>
        </div>

        
        <nav class="p-2 lg:p-4 space-y-1 overflow-y-auto custom-scrollbar flex-grow text-xs">
            <a href="dashboard.php" title="Dashboard Overview" class="flex items-center justify-center lg:justify-start gap-3 px-3 py-2.5 rounded-xl font-bold transition-all <?php echo $currentPage === 'dashboard.php' ? 'bg-orange-600 text-white shadow-lg shadow-orange-600/30' : 'text-stone-400 hover:text-white hover:bg-stone-900'; ?>">
                <i class="fa-solid fa-chart-line text-sm text-center"></i> <span class="hidden lg:inline-block">Dashboard Overview</span>
            </a>
            <a href="manage_teachers.php" title="Faculty Teachers" class="flex items-center justify-center lg:justify-start gap-3 px-3 py-2.5 rounded-xl font-bold transition-all <?php echo $currentPage === 'manage_teachers.php' ? 'bg-orange-600 text-white shadow-lg shadow-orange-600/30' : 'text-stone-400 hover:text-white hover:bg-stone-900'; ?>">
                <i class="fa-solid fa-chalkboard-user text-sm text-center"></i> <span class="hidden lg:inline-block">Faculty Teachers</span>
            </a>
            <a href="manage_students.php" title="Student Directory" class="flex items-center justify-center lg:justify-start gap-3 px-3 py-2.5 rounded-xl font-bold transition-all <?php echo $currentPage === 'manage_students.php' ? 'bg-orange-600 text-white shadow-lg shadow-orange-600/30' : 'text-stone-400 hover:text-white hover:bg-stone-900'; ?>">
                <i class="fa-solid fa-graduation-cap text-sm text-center"></i> <span class="hidden lg:inline-block">Student Directory</span>
            </a>
            <a href="manage_users.php" title="All User Accounts" class="flex items-center justify-center lg:justify-start gap-3 px-3 py-2.5 rounded-xl font-bold transition-all <?php echo $currentPage === 'manage_users.php' ? 'bg-orange-600 text-white shadow-lg shadow-orange-600/30' : 'text-stone-400 hover:text-white hover:bg-stone-900'; ?>">
                <i class="fa-solid fa-users-gear text-sm text-center"></i> <span class="hidden lg:inline-block">All User Accounts</span>
            </a>
            <a href="manage_subjects.php" title="Subjects Catalog" class="flex items-center justify-center lg:justify-start gap-3 px-3 py-2.5 rounded-xl font-bold transition-all <?php echo $currentPage === 'manage_subjects.php' ? 'bg-orange-600 text-white shadow-lg shadow-orange-600/30' : 'text-stone-400 hover:text-white hover:bg-stone-900'; ?>">
                <i class="fa-solid fa-book-bookmark text-sm text-center"></i> <span class="hidden lg:inline-block">Subjects Catalog</span>
            </a>
            <a href="manage_departments.php" title="Departments" class="flex items-center justify-center lg:justify-start gap-3 px-3 py-2.5 rounded-xl font-bold transition-all <?php echo $currentPage === 'manage_departments.php' ? 'bg-orange-600 text-white shadow-lg shadow-orange-600/30' : 'text-stone-400 hover:text-white hover:bg-stone-900'; ?>">
                <i class="fa-solid fa-building-columns text-sm text-center"></i> <span class="hidden lg:inline-block">Departments</span>
            </a>
            <a href="activity_logs.php" title="Activity Audit Logs" class="flex items-center justify-center lg:justify-start gap-3 px-3 py-2.5 rounded-xl font-bold transition-all <?php echo $currentPage === 'activity_logs.php' ? 'bg-orange-600 text-white shadow-lg shadow-orange-600/30' : 'text-stone-400 hover:text-white hover:bg-stone-900'; ?>">
                <i class="fa-solid fa-clipboard-list text-sm text-center"></i> <span class="hidden lg:inline-block">Activity Audit Logs</span>
            </a>
            <a href="iso_evaluation.php" title="ISO 25010 Quality Model" class="flex items-center justify-center lg:justify-start gap-3 px-3 py-2.5 rounded-xl font-bold transition-all <?php echo $currentPage === 'iso_evaluation.php' ? 'bg-orange-600 text-white shadow-lg shadow-orange-600/30' : 'text-stone-400 hover:text-white hover:bg-stone-900'; ?>">
                <i class="fa-solid fa-award text-sm text-center"></i> <span class="hidden lg:inline-block">ISO 25010 Quality Model</span>
            </a>
        </nav>

        
        <div class="p-2 lg:p-4 border-t border-stone-800 bg-stone-900/50 flex-shrink-0">
            <a href="../logout.php" title="Sign Out" class="flex items-center justify-center gap-2 w-full bg-rose-600/10 hover:bg-rose-600 text-rose-500 hover:text-white text-xs font-bold py-2.5 rounded-xl transition-all">
                <i class="fa-solid fa-right-from-bracket"></i> <span class="hidden lg:inline-block">Sign Out</span>
            </a>
        </div>
    </div>
</aside>
