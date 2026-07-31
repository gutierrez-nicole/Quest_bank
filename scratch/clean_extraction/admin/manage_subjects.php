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

            if (!empty($subject_code) && !empty($subject_title)) {
                $stmt = $pdo->prepare("INSERT INTO lesson_materials (teacher_id, subject, title, file_name, file_path, file_type, file_size) VALUES (?, ?, ?, 'System Master Subject', '#', 'CATALOG', 0)");
                $stmt->execute([getCurrentUserId(), $subject_code, $subject_title]);
                logActivity("Added new academic subject '{$subject_code}' ({$subject_title}) to curriculum catalog.");
                $success_msg = "Subject successfully added to academic curriculum!";
            } else {
                $error_msg = "Subject code and title are required.";
            }
        } elseif (isset($_POST['delete_subject'])) {
            $subject_code = trim($_POST['subject_code'] ?? '');
            if (!empty($subject_code)) {
                $stmt = $pdo->prepare("DELETE FROM lesson_materials WHERE subject = ? AND file_type = 'CATALOG'");
                $stmt->execute([$subject_code]);
                logActivity("Removed academic subject '{$subject_code}' from curriculum catalog.");
                $success_msg = "Subject '{$subject_code}' removed successfully!";
            }
        }
    }

    $search = trim($_GET['search'] ?? '');
    if ($search !== '') {
        $stmtSubjects = $pdo->prepare("SELECT MAX(id) AS id, subject, title FROM lesson_materials WHERE file_type = 'CATALOG' AND (subject LIKE ? OR title LIKE ?) GROUP BY subject, title ORDER BY id DESC");
        $stmtSubjects->execute(['%' . $search . '%', '%' . $search . '%']);
    } else {
        $stmtSubjects = $pdo->prepare("SELECT MAX(id) AS id, subject, title FROM lesson_materials WHERE file_type = 'CATALOG' GROUP BY subject, title ORDER BY id DESC");
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
    <title>QuestBank - Manage Academic Subjects</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght=400;600;700;800&display=swap" rel="stylesheet">
    <style> body { font-family: 'Plus Jakarta Sans', sans-serif; } </style>
</head>
<body class="bg-[#f3f4f6] min-h-screen flex">
    <?php require_once __DIR__ . '/../includes/admin_sidebar.php'; ?>
    <main class="flex-1 ml-16 lg:ml-64 p-6 md:p-12 overflow-y-auto min-h-screen">
        <div class="max-w-6xl mx-auto space-y-6">
        <div>
            <a href="dashboard.php" class="text-xs font-bold text-orange-600 hover:underline"><i class="fa-solid fa-arrow-left mr-1"></i> Back to Dashboard</a>
            <h1 class="text-2xl font-extrabold text-stone-800 mt-2"><i class="fa-solid fa-book-bookmark text-orange-600 mr-1"></i> Subject & Course Management</h1>
        </div>

        <?php if (!empty($success_msg)): ?>
            <div class="bg-emerald-50 border-l-4 border-emerald-500 p-4 rounded-xl text-xs font-bold text-emerald-700"><?php echo htmlspecialchars($success_msg); ?></div>
        <?php endif; ?>

        <?php if (!empty($error_msg)): ?>
            <div class="bg-rose-50 border-l-4 border-rose-500 p-4 rounded-xl text-xs font-bold text-rose-700"><?php echo htmlspecialchars($error_msg); ?></div>
        <?php endif; ?>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <div class="bg-white p-6 border rounded-2xl space-y-4 shadow-sm h-fit">
                <h3 class="text-xs font-extrabold uppercase text-stone-700 border-b pb-2">Add New Subject</h3>
                <form action="manage_subjects.php" method="POST" class="space-y-3">
                    <?php echo csrfInputField(); ?>
                    <div>
                        <label class="text-[10px] font-bold text-stone-500 uppercase">Subject Code</label>
                        <input type="text" name="subject_code" required placeholder="e.g. IT 312" class="w-full border rounded-xl p-2.5 text-xs outline-none">
                    </div>
                    <div>
                        <label class="text-[10px] font-bold text-stone-500 uppercase">Subject Title</label>
                        <input type="text" name="subject_title" required placeholder="e.g. System Architecture" class="w-full border rounded-xl p-2.5 text-xs outline-none">
                    </div>
                    <button type="submit" name="add_subject" class="w-full bg-orange-600 hover:bg-orange-700 text-white font-bold text-xs py-3 rounded-xl transition-all">Add Subject Item</button>
                </form>
            </div>

            <div class="lg:col-span-2 bg-white p-6 border rounded-2xl shadow-sm space-y-4">
                <div class="flex flex-col sm:flex-row justify-between items-center gap-3 border-b pb-3">
                    <h3 class="text-xs font-extrabold uppercase text-stone-700">Active Subjects Catalog (<?php echo count($subjects); ?>)</h3>
                    <form method="GET" action="manage_subjects.php" class="flex gap-2 w-full sm:w-auto">
                        <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Search subject..." class="border rounded-xl px-3 py-1.5 text-xs outline-none w-full sm:w-48">
                        <button type="submit" class="bg-stone-800 text-white px-3 py-1.5 rounded-xl text-xs font-bold hover:bg-stone-900 transition-all"><i class="fa-solid fa-magnifying-glass"></i></button>
                        <?php if ($search !== ''): ?>
                            <a href="manage_subjects.php" class="bg-stone-200 text-stone-700 px-3 py-1.5 rounded-xl text-xs font-bold hover:bg-stone-300">Clear</a>
                        <?php endif; ?>
                    </form>
                </div>
                
                <div class="space-y-2 max-h-[500px] overflow-y-auto pr-1">
                    <?php if (!empty($subjects)): ?>
                        <?php foreach ($subjects as $sb): ?>
                            <div class="p-3 border rounded-xl flex justify-between items-center text-xs bg-stone-50 hover:bg-stone-100/80 transition-all">
                                <div class="flex items-center gap-3">
                                    <span class="font-bold text-orange-600 uppercase font-mono bg-orange-50 px-2 py-1 rounded-lg border border-orange-200"><?php echo htmlspecialchars($sb['subject']); ?></span>
                                    <span class="font-extrabold text-stone-800"><?php echo htmlspecialchars($sb['title']); ?></span>
                                </div>
                                <div class="flex items-center gap-2">
                                    <span class="bg-emerald-100 text-emerald-800 px-2 py-0.5 rounded text-[10px] font-bold">Active</span>
                                    <form method="POST" action="manage_subjects.php" onsubmit="return confirm('Are you sure you want to delete this subject?');">
                                        <?php echo csrfInputField(); ?>
                                        <input type="hidden" name="subject_code" value="<?php echo htmlspecialchars($sb['subject']); ?>">
                                        <button type="submit" name="delete_subject" class="text-rose-500 hover:text-rose-700 px-2 py-1 rounded text-xs transition-all" title="Delete Subject">
                                            <i class="fa-solid fa-trash-can"></i>
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
</body>
</html>