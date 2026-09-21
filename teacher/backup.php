<?php
require_once __DIR__ . '/../app/bootstrap.php';
require_once __DIR__ . '/../app/services/LessonExtractionService.php';

AuthService::enforceRole('teacher');
$pdo = getDBConnection();
$teacher_id = getCurrentUserId();

LessonExtractionService::ensureSchema($pdo);

$success_msg = "";
$error_msg = "";

// 1. Restore a single deleted lesson
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['restore_lesson'])) {
    validateCSRFToken();
    $lesson_id = intval($_POST['lesson_id'] ?? 0);
    if ($lesson_id > 0) {
        $stmtFind = $pdo->prepare("SELECT title FROM lesson_materials WHERE id = ? AND teacher_id = ? AND deleted_at IS NOT NULL");
        $stmtFind->execute([$lesson_id, $teacher_id]);
        $lesson = $stmtFind->fetch(PDO::FETCH_ASSOC);

        if ($lesson) {
            $stmtRestore = $pdo->prepare("UPDATE lesson_materials SET deleted_at = NULL WHERE id = ? AND teacher_id = ?");
            $stmtRestore->execute([$lesson_id, $teacher_id]);
            logActivity("Restored deleted lesson material '{$lesson['title']}' (ID: {$lesson_id}).");
            $success_msg = "Lesson material '{$lesson['title']}' was successfully restored! It is now accessible in Upload Lessons and Exam Generation.";
        } else {
            $error_msg = "Deleted lesson material not found or already restored.";
        }
    } else {
        $error_msg = "Invalid lesson ID for restoration.";
    }
}

// 2. Restore all deleted lessons in bulk
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['restore_all_lessons'])) {
    validateCSRFToken();
    try {
        $stmtRestoreAll = $pdo->prepare("UPDATE lesson_materials SET deleted_at = NULL WHERE teacher_id = ? AND deleted_at IS NOT NULL");
        $stmtRestoreAll->execute([$teacher_id]);
        $restoredCount = $stmtRestoreAll->rowCount();
        if ($restoredCount > 0) {
            logActivity("Restored all {$restoredCount} deleted lesson materials from Recycle Bin.");
            $success_msg = "Successfully restored all {$restoredCount} lesson material(s) from the Recycle Bin!";
        } else {
            $success_msg = "No deleted lessons were pending restoration.";
        }
    } catch (Exception $e) {
        $error_msg = "Failed to restore lessons: " . $e->getMessage();
    }
}

// 3. Permanent delete from Recycle Bin
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['permanent_delete_lesson'])) {
    validateCSRFToken();
    $lesson_id = intval($_POST['lesson_id'] ?? 0);
    if ($lesson_id > 0) {
        $stmtFind = $pdo->prepare("SELECT title, file_path FROM lesson_materials WHERE id = ? AND teacher_id = ? AND deleted_at IS NOT NULL");
        $stmtFind->execute([$lesson_id, $teacher_id]);
        $lesson = $stmtFind->fetch(PDO::FETCH_ASSOC);

        if ($lesson) {
            if (!empty($lesson['file_path'])) {
                $full_path = __DIR__ . '/' . $lesson['file_path'];
                if (file_exists($full_path)) {
                    @unlink($full_path);
                }
            }
            $stmtDel = $pdo->prepare("DELETE FROM lesson_materials WHERE id = ? AND teacher_id = ?");
            $stmtDel->execute([$lesson_id, $teacher_id]);
            logActivity("Permanently deleted lesson material '{$lesson['title']}' (ID: {$lesson_id}).");
            $success_msg = "Lesson material '{$lesson['title']}' was permanently removed.";
        } else {
            $error_msg = "Lesson material not found in Recycle Bin.";
        }
    }
}

// 4. Export active lessons as portable JSON backup package
if (isset($_GET['action']) && $_GET['action'] === 'export_lessons_json') {
    $stmtExp = $pdo->prepare("
        SELECT subject, title, file_name, file_type, file_size, lesson_text, 
               word_count, page_count, academic_period, semester, school_year, year_level, program
        FROM lesson_materials 
        WHERE teacher_id = ? AND deleted_at IS NULL
        ORDER BY id ASC
    ");
    $stmtExp->execute([$teacher_id]);
    $activeLessons = $stmtExp->fetchAll(PDO::FETCH_ASSOC);

    $backupPayload = [
        'app' => 'QuestBank',
        'export_type' => 'teacher_lessons_backup',
        'version' => '2.0',
        'teacher_id' => $teacher_id,
        'exported_at' => date('c'),
        'total_lessons' => count($activeLessons),
        'lessons' => $activeLessons
    ];

    $jsonOutput = json_encode($backupPayload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    $filename = "questbank_lessons_backup_" . date('Y_m_d_His') . ".json";

    header('Content-Type: application/json; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . strlen($jsonOutput));
    echo $jsonOutput;
    exit();
}

// 5. Restore lessons from uploaded JSON backup package
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['restore_lessons_json'])) {
    validateCSRFToken();
    if (isset($_FILES['backup_json']) && $_FILES['backup_json']['error'] === UPLOAD_ERR_OK) {
        $file_tmp = $_FILES['backup_json']['tmp_name'];
        $file_ext = strtolower(pathinfo($_FILES['backup_json']['name'], PATHINFO_EXTENSION));

        if ($file_ext !== 'json') {
            $error_msg = "Invalid file type. Please upload a valid .json lesson backup file.";
        } else {
            $rawContent = file_get_contents($file_tmp);
            $parsed = json_decode($rawContent, true);

            if (!is_array($parsed) || empty($parsed['lessons']) || !is_array($parsed['lessons'])) {
                $error_msg = "Invalid or corrupted lesson backup file format. Expected 'lessons' array.";
            } else {
                $importedCount = 0;
                $stmtInsert = $pdo->prepare("
                    INSERT INTO lesson_materials (
                        teacher_id, subject, title, file_name, file_path, file_type,
                        file_size, lesson_text, processing_status, word_count, page_count,
                        extracted_at, academic_period, semester, school_year, year_level, program
                    ) VALUES (
                        ?, ?, ?, ?, ?, ?,
                        ?, ?, 'completed', ?, ?,
                        NOW(), ?, ?, ?, ?, ?
                    )
                ");

                foreach ($parsed['lessons'] as $les) {
                    $lesTitle = trim($les['title'] ?? '');
                    $lesSubject = trim($les['subject'] ?? 'General Engineering');
                    if (empty($lesTitle)) continue;

                    $stmtInsert->execute([
                        $teacher_id,
                        $lesSubject,
                        $lesTitle,
                        $les['file_name'] ?? ($lesTitle . '.txt'),
                        '', // Internal stored path empty for imported JSON
                        $les['file_type'] ?? 'text/plain',
                        intval($les['file_size'] ?? 0),
                        $les['lesson_text'] ?? '',
                        intval($les['word_count'] ?? str_word_count($les['lesson_text'] ?? '')),
                        intval($les['page_count'] ?? 1),
                        $les['academic_period'] ?? 'general',
                        $les['semester'] ?? null,
                        $les['school_year'] ?? null,
                        $les['year_level'] ?? null,
                        $les['program'] ?? 'BSCE'
                    ]);
                    $importedCount++;
                }

                logActivity("Imported {$importedCount} lessons from JSON backup.");
                $success_msg = "Successfully restored {$importedCount} lesson material(s) from JSON backup package!";
            }
        }
    } else {
        $error_msg = "Please choose a valid .json backup file to restore.";
    }
}

// Fetch deleted lessons (Recycle Bin)
$stmtDeleted = $pdo->prepare("
    SELECT * FROM lesson_materials 
    WHERE teacher_id = ? AND deleted_at IS NOT NULL 
    ORDER BY deleted_at DESC
");
$stmtDeleted->execute([$teacher_id]);
$deletedLessons = $stmtDeleted->fetchAll(PDO::FETCH_ASSOC) ?: [];

// Fetch counts for summary cards
$stmtCountActive = $pdo->prepare("SELECT COUNT(*) FROM lesson_materials WHERE teacher_id = ? AND deleted_at IS NULL");
$stmtCountActive->execute([$teacher_id]);
$activeCount = (int)$stmtCountActive->fetchColumn();

$deletedCount = count($deletedLessons);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>QuestBank - Lesson Backup & Restore</title>
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
        <div class="max-w-5xl mx-auto space-y-6">

            <!-- Header -->
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div>
                    <a href="dashboard.php" class="text-xs font-bold text-orange-600 hover:underline"><i class="fa-solid fa-arrow-left mr-1"></i> Back to Dashboard</a>
                    <h1 class="text-2xl font-extrabold text-stone-800 mt-2 flex items-center gap-2">
                        <i class="fa-solid fa-trash-can-arrow-up text-orange-600"></i> Lesson Backup & Restore
                    </h1>
                    <p class="text-xs text-stone-400">Recover deleted lesson materials from the Recycle Bin and manage course backups safely.</p>
                </div>
                <div class="flex items-center gap-2">
                    <a href="upload_lessons.php" class="text-xs font-bold text-stone-700 bg-white hover:bg-stone-50 border border-stone-200 px-4 py-2.5 rounded-xl transition-all shadow-xs flex items-center gap-1.5">
                        <i class="fa-solid fa-file-arrow-up text-orange-500"></i> Active Lessons (<?php echo $activeCount; ?>)
                    </a>
                </div>
            </div>

            <?php if (!empty($success_msg)): ?>
                <div class="bg-emerald-50 border-l-4 border-emerald-500 p-4 rounded-xl text-xs font-semibold text-emerald-700 flex items-center gap-2" data-testid="backup-success-alert">
                    <i class="fa-solid fa-circle-check text-emerald-600 text-sm"></i>
                    <span><?php echo htmlspecialchars($success_msg); ?></span>
                </div>
            <?php endif; ?>

            <?php if (!empty($error_msg)): ?>
                <div class="bg-red-50 border-l-4 border-red-500 p-4 rounded-xl text-xs font-semibold text-red-700 flex items-center gap-2" data-testid="backup-error-alert">
                    <i class="fa-solid fa-circle-xmark text-red-600 text-sm"></i>
                    <span><?php echo htmlspecialchars($error_msg); ?></span>
                </div>
            <?php endif; ?>

            <!-- Metrics Cards -->
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div class="bg-white border border-stone-200 rounded-2xl p-5 shadow-sm space-y-1">
                    <div class="flex items-center justify-between">
                        <span class="text-[10px] font-extrabold uppercase tracking-wider text-stone-400">Active Lessons</span>
                        <div class="w-8 h-8 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center font-bold text-xs">
                            <i class="fa-solid fa-book-open"></i>
                        </div>
                    </div>
                    <p class="text-2xl font-black text-stone-800"><?php echo $activeCount; ?></p>
                    <p class="text-[11px] text-stone-500">Available for AI exam generation</p>
                </div>

                <div class="bg-white border border-stone-200 rounded-2xl p-5 shadow-sm space-y-1">
                    <div class="flex items-center justify-between">
                        <span class="text-[10px] font-extrabold uppercase tracking-wider text-stone-400">Deleted in Recycle Bin</span>
                        <div class="w-8 h-8 rounded-xl bg-amber-50 text-amber-600 flex items-center justify-center font-bold text-xs">
                            <i class="fa-solid fa-trash-can"></i>
                        </div>
                    </div>
                    <p class="text-2xl font-black text-amber-600"><?php echo $deletedCount; ?></p>
                    <p class="text-[11px] text-stone-500">Ready for instant 1-click restoration</p>
                </div>

                <div class="bg-white border border-stone-200 rounded-2xl p-5 shadow-sm space-y-1">
                    <div class="flex items-center justify-between">
                        <span class="text-[10px] font-extrabold uppercase tracking-wider text-stone-400">Safe Backup System</span>
                        <div class="w-8 h-8 rounded-xl bg-blue-50 text-blue-600 flex items-center justify-center font-bold text-xs">
                            <i class="fa-solid fa-shield-halved"></i>
                        </div>
                    </div>
                    <p class="text-sm font-black text-stone-800 mt-1">Non-Destructive</p>
                    <p class="text-[11px] text-stone-500">No raw SQL required for teachers</p>
                </div>
            </div>

            <!-- Main Recycle Bin / Deleted Lessons Panel -->
            <div class="bg-white border border-stone-200 rounded-2xl p-6 shadow-sm space-y-5">
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 border-b border-stone-100 pb-4">
                    <div>
                        <h3 class="text-sm font-extrabold uppercase tracking-wider text-stone-800 flex items-center gap-2">
                            <i class="fa-solid fa-trash-arrow-up text-orange-500"></i> Deleted Lessons Recycle Bin
                        </h3>
                        <p class="text-xs text-stone-400 mt-0.5">Lessons deleted from the upload manager can be restored back to your account anytime.</p>
                    </div>

                    <?php if ($deletedCount > 0): ?>
                        <form method="POST" action="backup.php" onsubmit="return confirm('Restore all <?php echo $deletedCount; ?> deleted lesson materials?');">
                            <?php echo csrfInputField(); ?>
                            <button type="submit" name="restore_all_lessons" class="bg-emerald-600 hover:bg-emerald-700 text-white font-extrabold text-xs px-4 py-2 rounded-xl transition-all shadow-sm flex items-center gap-1.5 cursor-pointer">
                                <i class="fa-solid fa-rotate-left"></i> Restore All Lessons (<?php echo $deletedCount; ?>)
                            </button>
                        </form>
                    <?php endif; ?>
                </div>

                <?php if ($deletedCount > 0): ?>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-xs border-collapse">
                            <thead>
                                <tr class="bg-stone-50 border-b border-stone-200 text-stone-500 uppercase font-bold text-[10px]">
                                    <th class="p-3">Lesson Title & Material</th>
                                    <th class="p-3">Subject & Period</th>
                                    <th class="p-3 text-center">Extracted Words</th>
                                    <th class="p-3 text-center">Date Deleted</th>
                                    <th class="p-3 text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-stone-100 font-medium text-stone-700">
                                <?php foreach ($deletedLessons as $dl): ?>
                                    <tr class="hover:bg-stone-50/60 transition-colors">
                                        <td class="p-3 font-bold text-stone-800">
                                            <div class="flex items-center gap-2.5">
                                                <div class="w-7 h-7 rounded-lg bg-orange-100 text-orange-600 flex items-center justify-center font-bold text-xs flex-shrink-0">
                                                    <i class="fa-solid fa-file-lines"></i>
                                                </div>
                                                <div>
                                                    <span class="font-black text-stone-900 block"><?php echo htmlspecialchars($dl['title']); ?></span>
                                                    <span class="text-[10px] text-stone-400 font-medium"><?php echo htmlspecialchars($dl['original_filename'] ?? $dl['file_name'] ?? 'Uploaded Document'); ?></span>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="p-3">
                                            <div class="space-y-0.5">
                                                <span class="font-bold text-stone-800 block"><?php echo htmlspecialchars($dl['subject']); ?></span>
                                                <span class="bg-orange-50 text-orange-700 font-extrabold text-[9px] px-2 py-0.5 rounded-full uppercase border border-orange-200 inline-block">
                                                    <?php echo htmlspecialchars($dl['academic_period'] ?? 'General'); ?>
                                                </span>
                                            </div>
                                        </td>
                                        <td class="p-3 text-center font-mono font-bold text-stone-700">
                                            <?php echo number_format($dl['word_count'] ?? 0); ?> words
                                        </td>
                                        <td class="p-3 text-center text-stone-500 font-medium text-[11px]">
                                            <?php echo !empty($dl['deleted_at']) ? date('M d, Y h:i A', strtotime($dl['deleted_at'])) : 'Recently'; ?>
                                        </td>
                                        <td class="p-3 text-right">
                                            <div class="flex items-center justify-end gap-2">
                                                <form method="POST" action="backup.php" class="inline">
                                                    <?php echo csrfInputField(); ?>
                                                    <input type="hidden" name="lesson_id" value="<?php echo $dl['id']; ?>">
                                                    <button type="submit" name="restore_lesson" class="bg-emerald-600 hover:bg-emerald-700 text-white font-extrabold text-[11px] px-3 py-1.5 rounded-xl transition-all shadow-xs flex items-center gap-1 cursor-pointer">
                                                        <i class="fa-solid fa-rotate-left"></i> Restore
                                                    </button>
                                                </form>
                                                <form method="POST" action="backup.php" class="inline" onsubmit="return confirm('Permanently delete \'<?php echo htmlspecialchars(addslashes($dl['title'])); ?>\'? This action cannot be undone.');">
                                                    <?php echo csrfInputField(); ?>
                                                    <input type="hidden" name="lesson_id" value="<?php echo $dl['id']; ?>">
                                                    <button type="submit" name="permanent_delete_lesson" class="bg-stone-100 hover:bg-rose-50 text-stone-400 hover:text-rose-600 font-bold text-[11px] px-2.5 py-1.5 rounded-xl transition-all border border-stone-200 cursor-pointer" title="Permanent Delete">
                                                        <i class="fa-solid fa-trash-can"></i>
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="text-center py-12 px-4 space-y-3 bg-stone-50/50 rounded-2xl border border-dashed border-stone-200">
                        <div class="w-12 h-12 rounded-2xl bg-emerald-50 text-emerald-600 flex items-center justify-center font-bold text-xl mx-auto">
                            <i class="fa-solid fa-box-archive"></i>
                        </div>
                        <h4 class="text-sm font-extrabold text-stone-800">Recycle Bin is Empty</h4>
                        <p class="text-xs text-stone-400 max-w-md mx-auto">
                            You currently have no deleted lessons. Any materials you remove in the Upload Lessons section will safely appear here for 1-click restoration.
                        </p>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Portable Backup & Restore (JSON Package) -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- Export Lessons -->
                <div class="bg-white border border-stone-200 rounded-2xl p-6 shadow-sm space-y-4 flex flex-col justify-between">
                    <div class="space-y-2">
                        <div class="w-10 h-10 bg-orange-100 text-orange-600 rounded-xl flex items-center justify-center font-bold text-lg mb-2">
                            <i class="fa-solid fa-file-export"></i>
                        </div>
                        <h3 class="text-sm font-extrabold text-stone-800 uppercase tracking-wide">Export Lessons Backup</h3>
                        <p class="text-xs text-stone-400 leading-relaxed">
                            Download all your active lesson materials and extracted syllabus texts as a clean, portable JSON package. You can store this as your personal backup or transfer to another semester.
                        </p>
                    </div>
                    <a href="backup.php?action=export_lessons_json" class="w-full bg-stone-900 hover:bg-orange-600 text-white font-extrabold text-xs py-3 rounded-xl transition-all shadow-sm text-center block cursor-pointer">
                        <i class="fa-solid fa-download mr-1.5"></i> Export Lessons Archive (.JSON)
                    </a>
                </div>

                <!-- Import / Restore Lessons -->
                <div class="bg-white border border-stone-200 rounded-2xl p-6 shadow-sm space-y-4">
                    <div class="space-y-2">
                        <div class="w-10 h-10 bg-blue-100 text-blue-600 rounded-xl flex items-center justify-center font-bold text-lg mb-2">
                            <i class="fa-solid fa-file-import"></i>
                        </div>
                        <h3 class="text-sm font-extrabold text-stone-800 uppercase tracking-wide">Restore Lessons Archive</h3>
                        <p class="text-xs text-stone-400 leading-relaxed">
                            Upload a previously exported QuestBank `.json` lesson backup package to restore and re-index lesson materials directly into your teaching pool.
                        </p>
                    </div>

                    <form action="backup.php" method="POST" enctype="multipart/form-data" class="space-y-3">
                        <?php echo csrfInputField(); ?>
                        <input type="file" name="backup_json" accept=".json" required class="block w-full text-xs text-stone-500 file:mr-3 file:py-2 file:px-3 file:rounded-xl file:border-0 file:text-xs file:font-bold file:bg-stone-900 file:text-white hover:file:bg-orange-600 cursor-pointer">
                        <button type="submit" name="restore_lessons_json" onclick="return confirm('Restore lessons from this backup file? Existing lessons will be preserved.');" class="w-full bg-orange-600 hover:bg-orange-700 text-white font-extrabold text-xs py-3 rounded-xl transition-all shadow-sm cursor-pointer">
                            <i class="fa-solid fa-upload mr-1.5"></i> Restore Lessons from Backup
                        </button>
                    </form>
                </div>
            </div>

        </div>
    </main>

</body>
</html>