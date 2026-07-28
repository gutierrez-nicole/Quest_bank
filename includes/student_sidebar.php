<?php
// includes/student_sidebar.php - Student Portal Navigation Partial
$currentPage = basename($_SERVER['PHP_SELF']);
?>
<aside class="w-64 bg-stone-950 text-stone-300 flex flex-col justify-between hidden lg:flex z-30 shadow-2xl fixed h-full">
    <div class="flex flex-col h-full overflow-hidden">
        <!-- Sidebar Header -->
        <div class="p-5 border-b border-stone-800 flex items-center justify-between bg-stone-900/80 flex-shrink-0">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 bg-orange-gradient rounded-2xl flex items-center justify-center font-black text-white shadow-lg shadow-orange-600/30">
                    <i class="fa-solid fa-graduation-cap text-lg"></i>
                </div>
                <div>
                    <h1 class="text-sm font-black tracking-tight text-white">Quest<span class="text-orange-500">Bank</span></h1>
                    <span class="text-[9px] uppercase font-bold text-orange-500 tracking-wider">Student Portal</span>
                </div>
            </div>
        </div>

        <!-- Navigation Links -->
        <nav class="p-4 space-y-1.5 overflow-y-auto custom-scrollbar flex-grow text-xs">
            <a href="dashboard.php" class="flex items-center gap-3 px-4 py-3 rounded-xl font-bold transition-all <?php echo $currentPage === 'dashboard.php' ? 'bg-orange-600 text-white shadow-md shadow-orange-500/20' : 'text-stone-400 hover:text-white hover:bg-stone-900'; ?>">
                <i class="fa-solid fa-chart-pie w-5 text-center text-sm"></i> Student Dashboard
            </a>
            <a href="export_pdf.php" target="_blank" class="flex items-center gap-3 px-4 py-3 rounded-xl font-bold transition-all text-stone-400 hover:text-white hover:bg-stone-900">
                <i class="fa-solid fa-file-pdf w-5 text-center text-sm text-rose-500"></i> Print PDF Transcript
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
