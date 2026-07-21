<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') { header("Location: ../index.php"); exit(); }

$host = 'localhost'; $dbname = 'bankquest_db'; $db_user = 'root'; $db_pass = '';
$success_msg = "";

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $db_user, $db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_subject'])) {
        $subject_code = trim($_POST['subject_code']);
        $subject_title = trim($_POST['subject_title']);
        $department = trim($_POST['department']);

        $stmt = $pdo->prepare("INSERT INTO lesson_materials (teacher_id, subject, title, file_name, file_path, file_type, file_size) VALUES (?, ?, ?, 'System Master Subject', '#', 'CATALOG', 0)");
        $stmt->execute([$_SESSION['user_id'], $subject_code, $subject_title]);
        $success_msg = "Subject successfully added to academic curriculum!";
    }

    $subjects = $pdo->query("SELECT DISTINCT subject, title FROM lesson_materials ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { die("Database error: " . $e->getMessage()); }
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
<body class="bg-[#f3f4f6] min-h-screen p-6 md:p-12">
    <div class="max-w-6xl mx-auto space-y-6">
        <div>
            <a href="dashboard.php" class="text-xs font-bold text-orange-600 hover:underline"><i class="fa-solid fa-arrow-left mr-1"></i> Back to Dashboard</a>
            <h1 class="text-2xl font-extrabold text-stone-800 mt-2"><i class="fa-solid fa-book-bookmark text-orange-600 mr-1"></i> Subject & Course Management</h1>
        </div>

        <?php if ($success_msg): ?>
            <div class="bg-emerald-50 border-l-4 border-emerald-500 p-4 rounded-xl text-xs font-bold text-emerald-700"><?php echo $success_msg; ?></div>
        <?php endif; ?>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <div class="bg-white p-6 border rounded-2xl space-y-4 shadow-sm h-fit">
                <h3 class="text-xs font-extrabold uppercase text-stone-700 border-b pb-2">Add New Subject</h3>
                <form action="manage_subjects.php" method="POST" class="space-y-3">
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

            <div class="lg:col-span-2 bg-white p-6 border rounded-2xl shadow-sm">
                <h3 class="text-xs font-extrabold uppercase text-stone-700 border-b pb-3 mb-3">Active Subjects Catalog</h3>
                <div class="space-y-2">
                    <?php foreach ($subjects as $sb): ?>
                        <div class="p-3 border rounded-xl flex justify-between items-center text-xs bg-stone-50">
                            <div>
                                <span class="font-bold text-orange-600 uppercase font-mono mr-2"><?php echo htmlspecialchars($sb['subject']); ?></span>
                                <span class="font-extrabold text-stone-800"><?php echo htmlspecialchars($sb['title']); ?></span>
                            </div>
                            <span class="bg-emerald-100 text-emerald-800 px-2 py-0.5 rounded text-[10px] font-bold">Active</span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</body>
</html>