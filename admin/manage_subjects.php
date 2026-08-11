<?php
require_once __DIR__ . '/../app/bootstrap.php';

AuthService::enforceRole('admin');
$pdo = getDBConnection();
$success_msg = "";
$error_msg = "";

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        validateCSRFToken();

        if (isset($_POST['add_subject'])) {
            $subject_code = trim(sanitizeInput($_POST['subject_code'] ?? ''));
            $subject_title = trim(sanitizeInput($_POST['subject_title'] ?? ''));
            $description = trim(sanitizeInput($_POST['description'] ?? ''));

            if (!empty($subject_code) && !empty($subject_title)) {
                $stmt = $pdo->prepare("
                    INSERT INTO subjects (subject_code, code, subject_title, title, description) 
                    VALUES (?, ?, ?, ?, ?) 
                    ON DUPLICATE KEY UPDATE subject_title = VALUES(subject_title), title = VALUES(title), description = VALUES(description)
                ");
                $stmt->execute([$subject_code, $subject_code, $subject_title, $subject_title, $description]);
                
                // Backwards compatibility sync with lesson_materials catalog
                $stmtCat = $pdo->prepare("INSERT INTO lesson_materials (teacher_id, subject, title, file_name, file_path, file_type, file_size) VALUES (?, ?, ?, 'System Master Subject', '#', 'CATALOG', 0)");
                $stmtCat->execute([getCurrentUserId(), $subject_code, $subject_title]);

                logActivity("Added new academic subject '{$subject_code}' ({$subject_title}) to curriculum catalog.");
                $success_msg = "Subject '{$subject_code}' successfully added to academic curriculum!";
            } else {
                $error_msg = "Subject code and title are required.";
            }
        } elseif (isset($_POST['update_subject'])) {
            $subject_id = intval($_POST['subject_id'] ?? 0);
            $subject_code = trim(sanitizeInput($_POST['subject_code'] ?? ''));
            $subject_title = trim(sanitizeInput($_POST['subject_title'] ?? ''));
            $description = trim(sanitizeInput($_POST['description'] ?? ''));

            if ($subject_id > 0 && !empty($subject_code) && !empty($subject_title)) {
                $stmt = $pdo->prepare("UPDATE subjects SET subject_code = ?, code = ?, subject_title = ?, title = ?, description = ? WHERE id = ?");
                $stmt->execute([$subject_code, $subject_code, $subject_title, $subject_title, $description, $subject_id]);
                
                logActivity("Updated academic subject ID {$subject_id} to '{$subject_code}' ({$subject_title}).");
                $success_msg = "Subject '{$subject_code}' successfully updated!";
            } else {
                $error_msg = "Please provide valid subject details for update.";
            }
        } elseif (isset($_POST['delete_subject'])) {
            $subject_id = intval($_POST['delete_id'] ?? 0);
            $subject_code = trim($_POST['subject_code'] ?? '');
            
            if ($subject_id > 0) {
                $stmt = $pdo->prepare("DELETE FROM subjects WHERE id = ?");
                $stmt->execute([$subject_id]);
            }
            if (!empty($subject_code)) {
                $stmtLegacy = $pdo->prepare("DELETE FROM lesson_materials WHERE subject = ? AND file_type = 'CATALOG'");
                $stmtLegacy->execute([$subject_code]);
            }
            logActivity("Removed academic subject '{$subject_code}' from curriculum catalog.");
            $success_msg = "Subject successfully removed from catalog!";
        }
    }

    $search = trim($_GET['search'] ?? '');
    if ($search !== '') {
        $stmtSubjects = $pdo->prepare("
            SELECT id, 
                   COALESCE(subject_code, code) AS subject_code, 
                   COALESCE(subject_title, title) AS subject_title, 
                   description, created_at 
            FROM subjects 
            WHERE (subject_code LIKE ? OR code LIKE ? OR subject_title LIKE ? OR title LIKE ?) 
            ORDER BY id DESC
        ");
        $stmtSubjects->execute(['%' . $search . '%', '%' . $search . '%', '%' . $search . '%', '%' . $search . '%']);
    } else {
        $stmtSubjects = $pdo->prepare("
            SELECT id, 
                   COALESCE(subject_code, code) AS subject_code, 
                   COALESCE(subject_title, title) AS subject_title, 
                   description, created_at 
            FROM subjects 
            ORDER BY id DESC
        ");
        $stmtSubjects->execute();
    }
    $subjects = $stmtSubjects->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log("Manage subjects error: " . $e->getMessage());
    die("Database error occurred while accessing subjects.");
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>QuestBank - Manage Academic Subjects Catalog</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet">
    <style> body { font-family: 'Plus Jakarta Sans', sans-serif; } </style>
</head>
<body class="bg-[#f3f4f6] min-h-screen flex">
    <?php require_once __DIR__ . '/../includes/admin_sidebar.php'; ?>
    <main class="flex-1 ml-16 lg:ml-64 p-6 md:p-12 overflow-y-auto min-h-screen">
        <div class="max-w-6xl mx-auto space-y-6">
        <div>
            <a href="dashboard.php" class="text-xs font-bold text-orange-600 hover:underline"><i class="fa-solid fa-arrow-left mr-1"></i> Back to Dashboard</a>
            <h1 class="text-2xl font-extrabold text-stone-800 mt-2"><i class="fa-solid fa-book-bookmark text-orange-600 mr-1"></i> Subject & Course Curriculum Catalog</h1>
            <p class="text-xs text-stone-400">Manage master Civil Engineering subjects, course descriptions, and curriculum catalog entries.</p>
        </div>

        <?php if (!empty($success_msg)): ?>
            <div class="bg-emerald-50 border-l-4 border-emerald-500 p-4 rounded-xl text-xs font-bold text-emerald-700 shadow-sm">
                <i class="fa-solid fa-circle-check mr-1"></i> <?php echo htmlspecialchars($success_msg); ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($error_msg)): ?>
            <div class="bg-rose-50 border-l-4 border-rose-500 p-4 rounded-xl text-xs font-bold text-rose-700 shadow-sm">
                <i class="fa-solid fa-triangle-exclamation mr-1"></i> <?php echo htmlspecialchars($error_msg); ?>
            </div>
        <?php endif; ?>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            
            <div class="bg-white p-6 border border-stone-200 rounded-2xl space-y-4 shadow-sm h-fit">
                <h3 class="text-xs font-extrabold uppercase tracking-wider text-stone-700 border-b pb-3">
                    <i class="fa-solid fa-plus text-orange-500 mr-1"></i> Add Master Subject
                </h3>
                <form action="manage_subjects.php" method="POST" class="space-y-3">
                    <?php echo csrfInputField(); ?>
                    <div>
                        <label class="text-[10px] font-bold text-stone-500 uppercase">Subject Code</label>
                        <input type="text" name="subject_code" required placeholder="e.g. CE 412" class="w-full bg-stone-50 border border-stone-200 rounded-xl p-2.5 text-xs font-bold outline-none focus:border-orange-500">
                    </div>
                    <div>
                        <label class="text-[10px] font-bold text-stone-500 uppercase">Subject Title</label>
                        <input type="text" name="subject_title" required placeholder="e.g. Structural Theory & Design" class="w-full bg-stone-50 border border-stone-200 rounded-xl p-2.5 text-xs outline-none focus:border-orange-500">
                    </div>
                    <div>
                        <label class="text-[10px] font-bold text-stone-500 uppercase">Description (Optional)</label>
                        <textarea name="description" placeholder="Short course description..." rows="2" class="w-full bg-stone-50 border border-stone-200 rounded-xl p-2.5 text-xs outline-none focus:border-orange-500"></textarea>
                    </div>
                    <button type="submit" name="add_subject" class="w-full bg-orange-600 hover:bg-orange-700 text-white font-bold text-xs py-3 rounded-xl transition-all shadow-sm flex items-center justify-center gap-1.5">
                        <i class="fa-solid fa-folder-plus"></i> Add Subject to Catalog
                    </button>
                </form>
            </div>

            
            <div class="lg:col-span-2 bg-white p-6 border border-stone-200 rounded-2xl shadow-sm space-y-4">
                <div class="flex flex-col sm:flex-row justify-between items-center gap-3 border-b pb-3">
                    <h3 class="text-xs font-extrabold uppercase tracking-wider text-stone-700">
                        <i class="fa-solid fa-list text-orange-500 mr-1"></i> Active Curriculum Catalog (<?php echo count($subjects); ?>)
                    </h3>
                    <form method="GET" action="manage_subjects.php" class="flex gap-2 w-full sm:w-auto">
                        <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Search subject code or title..." class="bg-stone-50 border border-stone-200 rounded-xl px-3 py-1.5 text-xs outline-none focus:border-orange-500 w-full sm:w-56">
                        <button type="submit" class="bg-stone-900 text-white px-3 py-1.5 rounded-xl text-xs font-bold hover:bg-orange-600 transition-all"><i class="fa-solid fa-magnifying-glass"></i></button>
                        <?php if ($search !== ''): ?>
                            <a href="manage_subjects.php" class="bg-stone-200 text-stone-700 px-3 py-1.5 rounded-xl text-xs font-bold hover:bg-stone-300">Clear</a>
                        <?php endif; ?>
                    </form>
                </div>
                
                <div class="space-y-3 max-h-[550px] overflow-y-auto pr-1 custom-scrollbar">
                    <?php if (!empty($subjects)): ?>
                        <?php foreach ($subjects as $sb): ?>
                            <div class="p-4 border border-stone-200 rounded-xl flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3 bg-stone-50/60 hover:bg-stone-100/80 transition-all">
                                <div class="space-y-1">
                                    <div class="flex items-center gap-2">
                                        <span class="font-bold text-orange-600 uppercase font-mono bg-orange-50 border border-orange-200 px-2.5 py-0.5 rounded-lg text-xs">
                                            <?php echo htmlspecialchars($sb['subject_code']); ?>
                                        </span>
                                        <span class="font-extrabold text-stone-800 text-xs"><?php echo htmlspecialchars($sb['subject_title']); ?></span>
                                    </div>
                                    <?php if (!empty($sb['description'])): ?>
                                        <p class="text-[11px] text-stone-500"><?php echo htmlspecialchars($sb['description']); ?></p>
                                    <?php endif; ?>
                                </div>
                                <div class="flex items-center gap-2 self-end sm:self-center">
                                    <button onclick="openEditModal(<?php echo $sb['id']; ?>, '<?php echo htmlspecialchars(addslashes($sb['subject_code'])); ?>', '<?php echo htmlspecialchars(addslashes($sb['subject_title'])); ?>', '<?php echo htmlspecialchars(addslashes($sb['description'] ?? '')); ?>')" class="bg-amber-100 text-amber-800 hover:bg-amber-200 px-3 py-1.5 rounded-lg text-xs font-bold transition-all flex items-center gap-1">
                                        <i class="fa-solid fa-pen-to-square"></i> Edit
                                    </button>
                                    <form method="POST" action="manage_subjects.php" onsubmit="return confirm('Are you sure you want to delete subject <?php echo htmlspecialchars($sb['subject_code']); ?>?');" class="inline">
                                        <?php echo csrfInputField(); ?>
                                        <input type="hidden" name="delete_id" value="<?php echo $sb['id']; ?>">
                                        <input type="hidden" name="subject_code" value="<?php echo htmlspecialchars($sb['subject_code']); ?>">
                                        <button type="submit" name="delete_subject" class="bg-rose-50 text-rose-600 hover:bg-rose-600 hover:text-white px-3 py-1.5 rounded-lg text-xs font-bold transition-all flex items-center gap-1" title="Delete Subject">
                                            <i class="fa-solid fa-trash-can"></i> Delete
                                        </button>
                                    </form>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="p-8 text-center text-stone-400 text-xs font-semibold">No subjects found matching catalog query.</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    </main>

    
    <div id="edit_subject_modal" class="hidden fixed inset-0 bg-black/50 backdrop-blur-sm z-50 flex items-center justify-center p-4">
        <div class="bg-white border border-stone-200 rounded-2xl max-w-md w-full p-6 space-y-4 shadow-2xl animate-fadeIn">
            <div class="flex items-center justify-between border-b pb-3">
                <h3 class="font-extrabold text-stone-800 text-sm flex items-center gap-2">
                    <i class="fa-solid fa-pen-to-square text-orange-600"></i> Edit Subject Details
                </h3>
                <button onclick="closeEditModal()" class="text-stone-400 hover:text-stone-600"><i class="fa-solid fa-xmark"></i></button>
            </div>
            <form action="manage_subjects.php" method="POST" class="space-y-3">
                <?php echo csrfInputField(); ?>
                <input type="hidden" id="edit_subject_id" name="subject_id" value="">
                <div>
                    <label class="text-[10px] font-bold text-stone-500 uppercase">Subject Code</label>
                    <input type="text" id="edit_subject_code" name="subject_code" required class="w-full bg-stone-50 border border-stone-200 rounded-xl p-2.5 text-xs font-bold outline-none focus:border-orange-500">
                </div>
                <div>
                    <label class="text-[10px] font-bold text-stone-500 uppercase">Subject Title</label>
                    <input type="text" id="edit_subject_title" name="subject_title" required class="w-full bg-stone-50 border border-stone-200 rounded-xl p-2.5 text-xs outline-none focus:border-orange-500">
                </div>
                <div>
                    <label class="text-[10px] font-bold text-stone-500 uppercase">Description</label>
                    <textarea id="edit_description" name="description" rows="3" class="w-full bg-stone-50 border border-stone-200 rounded-xl p-2.5 text-xs outline-none focus:border-orange-500"></textarea>
                </div>
                <div class="flex items-center justify-end gap-2 pt-2 border-t">
                    <button type="button" onclick="closeEditModal()" class="bg-stone-100 hover:bg-stone-200 text-stone-700 font-bold text-xs px-4 py-2 rounded-xl">Cancel</button>
                    <button type="submit" name="update_subject" class="bg-orange-600 hover:bg-orange-700 text-white font-bold text-xs px-4 py-2 rounded-xl transition-all shadow-sm">Save Changes</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openEditModal(id, code, title, desc) {
            document.getElementById('edit_subject_id').value = id;
            document.getElementById('edit_subject_code').value = code;
            document.getElementById('edit_subject_title').value = title;
            document.getElementById('edit_description').value = desc;
            
            const modal = document.getElementById('edit_subject_modal');
            modal.classList.remove('hidden');
            modal.classList.add('flex');
        }
        function closeEditModal() {
            const modal = document.getElementById('edit_subject_modal');
            modal.classList.add('hidden');
            modal.classList.remove('flex');
        }
    </script>
</body>
</html>