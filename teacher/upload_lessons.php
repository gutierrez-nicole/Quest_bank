<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: ../index.php");
    exit();
}

if ($_SESSION['role'] !== 'teacher') {
    header("Location: ../index.php");
    exit();
}

$host = 'localhost';
$dbname = 'bankquest_db';
$db_user = 'root';
$db_pass = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $db_user, $db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}

$success_msg = "";
$error_msg = "";

// Upload File Logic
if ($_SERVER['REQUEST_METHOD'] === 'POST' and isset($_POST['upload_material'])) {
    $title = trim($_POST['title']);
    $subject = trim($_POST['subject']);

    if (isset($_FILES['lesson_file']) and $_FILES['lesson_file']['error'] === UPLOAD_ERR_OK) {
        $file_tmp = $_FILES['lesson_file']['tmp_name'];
        $file_name = $_FILES['lesson_file']['name'];
        $file_size = $_FILES['lesson_file']['size'];
        $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));

        $allowed_exts = ['pdf', 'docx', 'pptx', 'txt'];

        if (in_array($file_ext, $allowed_exts)) {
            $upload_dir = __DIR__ . '/uploads/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }

            $new_file_name = uniqid('lesson_') . '.' . $file_ext;
            $target_path = $upload_dir . $new_file_name;

            if (move_uploaded_file($file_tmp, $target_path)) {
                try {
                    $stmt = $pdo->prepare("INSERT INTO lesson_materials (teacher_id, subject, title, file_name, file_path, file_type, file_size) VALUES (?, ?, ?, ?, ?, ?, ?)");
                    $stmt->execute([
                        $_SESSION['user_id'],
                        $subject,
                        $title,
                        $file_name,
                        'uploads/' . $new_file_name,
                        strtoupper($file_ext),
                        $file_size
                    ]);
                    $success_msg = "Lesson material uploaded successfully!";
                } catch (PDOException $e) {
                    $error_msg = "Database record failed: " . $e->getMessage();
                }
            } else {
                $error_msg = "Failed to move uploaded file to server directory.";
            }
        } else {
            $error_msg = "Invalid file type. Allowed formats: PDF, DOCX, PPTX, TXT.";
        }
    } else {
        $error_msg = "Please select a valid file to upload.";
    }
}

// Delete File Logic
if (isset($_GET['delete_id'])) {
    $delete_id = intval($_GET['delete_id']);
    $stmtFind = $pdo->prepare("SELECT file_path FROM lesson_materials WHERE id = ? AND teacher_id = ?");
    $stmtFind->execute([$delete_id, $_SESSION['user_id']]);
    $material = $stmtFind->fetch(PDO::FETCH_ASSOC);

    if ($material) {
        $full_path = __DIR__ . '/' . $material['file_path'];
        if (file_exists($full_path)) {
            unlink($full_path);
        }
        $stmtDel = $pdo->prepare("DELETE FROM lesson_materials WHERE id = ?");
        $stmtDel->execute([$delete_id]);
        $success_msg = "Lesson material removed successfully!";
    }
}

// Fetch Teacher's Uploaded Materials
$stmtMat = $pdo->prepare("SELECT * FROM lesson_materials WHERE teacher_id = ? ORDER BY id DESC");
$stmtMat->execute([$_SESSION['user_id']]);
$materials = $stmtMat->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>QuestBank - Upload Lesson Materials</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght=400;600;700;800&display=swap" rel="stylesheet">
    <style> body { font-family: 'Plus Jakarta Sans', sans-serif; } </style>
</head>
<body class="bg-[#fffbf7] min-h-screen p-6 md:p-12">

    <div class="max-w-6xl mx-auto space-y-6">
        <div class="flex items-center justify-between">
            <div>
                <a href="dashboard.php" class="text-xs font-bold text-orange-600 hover:underline"><i class="fa-solid fa-arrow-left mr-1"></i> Back to Dashboard</a>
                <h1 class="text-2xl font-extrabold text-stone-800 mt-2"><i class="fa-solid fa-file-arrow-up text-orange-600 mr-1"></i> Upload Lesson Materials</h1>
                <p class="text-xs text-stone-400">Store class reviewers, syllabi, and reading resources for learning management.</p>
            </div>
        </div>

        <?php if (!empty($success_msg)): ?>
            <div class="bg-emerald-50 border-l-4 border-emerald-500 p-4 rounded-xl text-xs font-semibold text-emerald-700"><?php echo $success_msg; ?></div>
        <?php endif; ?>
        <?php if (!empty($error_msg)): ?>
            <div class="bg-red-50 border-l-4 border-red-500 p-4 rounded-xl text-xs font-semibold text-red-700"><?php echo $error_msg; ?></div>
        <?php endif; ?>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            
            <!-- LEFT COLUMN: UPLOAD FORM -->
            <div class="bg-white border border-stone-200 rounded-2xl p-5 shadow-sm space-y-4 h-fit">
                <h3 class="text-sm font-bold uppercase tracking-wider text-stone-700 border-b pb-2"><i class="fa-solid fa-cloud-arrow-up text-orange-500 mr-1"></i> Upload New Material</h3>
                
                <form action="upload_lessons.php" method="POST" enctype="multipart/form-data" class="space-y-4">
                    <div class="space-y-1">
                        <label class="text-xs font-bold text-stone-600">Document Title</label>
                        <input type="text" name="title" required placeholder="e.g. CI/CD Pipeline Fundamentals" class="w-full bg-stone-50 border border-stone-200 rounded-xl px-4 py-2 text-xs outline-none focus:border-orange-500">
                    </div>

                    <div class="space-y-1">
                        <label class="text-xs font-bold text-stone-600">Subject / Category</label>
                        <input type="text" name="subject" required placeholder="e.g. DevOps Principles" class="w-full bg-stone-50 border border-stone-200 rounded-xl px-4 py-2 text-xs outline-none focus:border-orange-500">
                    </div>

                    <div class="space-y-1">
                        <label class="text-xs font-bold text-stone-600">Select File Document</label>
                        <div onclick="triggerFileSelect()" class="border-2 border-dashed border-stone-300 hover:border-orange-400 rounded-xl p-5 bg-stone-50 text-center cursor-pointer transition-all" id="drop_zone">
                            <i class="fa-solid fa-file-pdf text-2xl text-stone-400 mb-1" id="upload_icon"></i>
                            <p class="text-[11px] text-stone-700 font-bold" id="upload_text">Choose file or drag here</p>
                            <p class="text-[9px] text-stone-400 mt-0.5" id="file_details">PDF, DOCX, PPTX, or TXT (Max 10MB)</p>
                            <input type="file" name="lesson_file" id="lesson_file" required class="hidden" onchange="displaySelectedFile()">
                        </div>
                    </div>

                    <button type="submit" name="upload_material" class="w-full bg-orange-600 hover:bg-orange-700 text-white font-bold text-xs py-2.5 rounded-xl transition-all shadow-md flex items-center justify-center gap-2">
                        <i class="fa-solid fa-upload"></i> Upload Material
                    </button>
                </form>
            </div>

            <!-- RIGHT COLUMN: MATERIALS REPOSITORY LIST -->
            <div class="lg:col-span-2 bg-white border border-stone-200 rounded-2xl p-6 shadow-sm space-y-4">
                <div class="flex items-center justify-between border-b pb-3">
                    <h3 class="text-sm font-bold uppercase tracking-wider text-stone-700"><i class="fa-solid fa-folder-open text-orange-500 mr-1"></i> Lesson Repository</h3>
                    <span class="bg-stone-100 text-stone-700 text-xs font-bold px-3 py-1 rounded-full"><?php echo count($materials); ?> Files Saved</span>
                </div>

                <?php if (!empty($materials)): ?>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <?php foreach ($materials as $m): ?>
                            <div class="p-4 border border-stone-200 rounded-xl bg-stone-50/40 hover:border-orange-300 transition-all flex flex-col justify-between space-y-3">
                                <div class="flex items-start gap-3">
                                    <div class="p-3 bg-orange-100 text-orange-700 rounded-lg flex-shrink-0">
                                        <i class="fa-solid fa-file-lines text-xl"></i>
                                    </div>
                                    <div class="min-w-0">
                                        <h4 class="font-bold text-xs text-stone-800 truncate"><?php echo htmlspecialchars($m['title']); ?></h4>
                                        <p class="text-[10px] text-stone-400 font-semibold mt-0.5"><?php echo htmlspecialchars($m['subject']); ?></p>
                                        <span class="inline-block mt-1 text-[9px] font-extrabold uppercase bg-stone-200 text-stone-700 px-1.5 py-0.5 rounded">
                                            <?php echo htmlspecialchars($m['file_type']); ?> • <?php echo number_format($m['file_size'] / 1024, 1); ?> KB
                                        </span>
                                    </div>
                                </div>

                                <div class="flex items-center justify-between pt-2 border-t border-stone-100 text-xs font-bold">
                                    <a href="<?php echo htmlspecialchars($m['file_path']); ?>" download class="text-orange-600 hover:underline flex items-center gap-1">
                                        <i class="fa-solid fa-download"></i> Download
                                    </a>
                                    <a href="upload_lessons.php?delete_id=<?php echo $m['id']; ?>" onclick="return confirm('Sigurado ka bang gusto mong burahin ang file na ito?');" class="text-rose-500 hover:underline flex items-center gap-1">
                                        <i class="fa-solid fa-trash"></i> Delete
                                    </a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="text-center py-12 text-stone-400">
                        <i class="fa-solid fa-folder-open text-4xl mb-3 text-stone-300"></i>
                        <p class="text-sm font-bold">No lesson materials uploaded yet</p>
                        <p class="text-xs mt-1">Mag-upload ng reviewers o reading materials sa kaliwa para sa iyong mga klase.</p>
                    </div>
                <?php endif; ?>
            </div>

        </div>
    </div>

    <script>
        function triggerFileSelect() { document.getElementById('lesson_file').click(); }
        function displaySelectedFile() {
            const input = document.getElementById('lesson_file');
            const icon = document.getElementById('upload_icon');
            const txt = document.getElementById('upload_text');
            const zone = document.getElementById('drop_zone');
            if (input.files.length > 0) {
                icon.className = "fa-solid fa-file-circle-check text-2xl text-emerald-500 mb-1";
                txt.innerText = input.files[0].name;
                txt.className = "text-[11px] text-emerald-600 font-bold";
                zone.classList.replace('border-stone-300', 'border-emerald-500');
            }
        }
    </script>
</body>
</html>