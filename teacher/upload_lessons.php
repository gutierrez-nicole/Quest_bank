<?php
require_once __DIR__ . '/../app/bootstrap.php';

AuthService::enforceRole('teacher');
$pdo = getDBConnection();

$success_msg = "";
$error_msg = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_material'])) {
    validateCSRFToken();
    $title = trim(sanitizeInput($_POST['title'] ?? ''));
    $subject = trim(sanitizeInput($_POST['subject'] ?? ''));
    
    $academic_period = trim(sanitizeInput($_POST['academic_period'] ?? 'general'));
    if (!in_array($academic_period, ['general', 'prelim', 'midterm', 'finals'])) {
        $academic_period = 'general';
    }
    $semester = trim(sanitizeInput($_POST['semester'] ?? ''));
    $school_year = trim(sanitizeInput($_POST['school_year'] ?? ''));
    $year_level_input = trim(sanitizeInput($_POST['year_level'] ?? ''));
    $program_input = trim(sanitizeInput($_POST['program'] ?? ''));

    if (isset($_FILES['lesson_file']) && $_FILES['lesson_file']['error'] === UPLOAD_ERR_OK) {
        $file_tmp = $_FILES['lesson_file']['tmp_name'];
        $file_name = $_FILES['lesson_file']['name'];
        $file_size = $_FILES['lesson_file']['size'];
        $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));

        $allowed_exts = ['pdf', 'docx', 'pptx', 'txt'];
        $allowed_mimes = [
            'application/pdf',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            'text/plain'
        ];

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime_type = finfo_file($finfo, $file_tmp);
        finfo_close($finfo);

        
        $clean_original_filename = basename($file_name);
        $file_parts = explode('.', $clean_original_filename);
        $forbidden_exts = ['php', 'phtml', 'php3', 'php4', 'php5', 'phps', 'phar', 'exe', 'sh', 'bat', 'cmd', 'js', 'pl', 'py', 'cgi'];
        $has_forbidden = false;
        foreach ($file_parts as $part) {
            if (in_array(strtolower($part), $forbidden_exts)) {
                $has_forbidden = true;
                break;
            }
        }

        if (!$has_forbidden && $file_size > 0 && $file_size <= 10485760 && in_array($file_ext, $allowed_exts) && in_array($mime_type, $allowed_mimes)) {
            require_once __DIR__ . '/../app/services/FileValidationService.php';
            $validationResult = FileValidationService::validateFile($file_tmp, $file_name);
            
            if (!$validationResult['success']) {
                $error_msg = $validationResult['error'];
            } else {
                $upload_dir = __DIR__ . '/uploads/';
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0755, true);
                }

                $new_file_name = uniqid('lesson_') . '.' . $file_ext;
                $target_path = $upload_dir . $new_file_name;

                if (move_uploaded_file($file_tmp, $target_path)) {
                    try {
                        $stmt = $pdo->prepare("
                            INSERT INTO lesson_materials 
                            (teacher_id, subject, title, academic_period, semester, school_year, year_level, program, file_name, file_path, file_type, file_size, processing_status, original_filename, stored_filename, mime_type) 
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', ?, ?, ?)
                        ");
                        $stmt->execute([
                            getCurrentUserId(),
                            $subject,
                            $title,
                            $academic_period,
                            $semester,
                            $school_year,
                            $year_level_input,
                            $program_input,
                            $clean_original_filename,
                            'uploads/' . $new_file_name,
                            strtoupper($file_ext),
                            $file_size,
                            $clean_original_filename,
                            $new_file_name,
                            $mime_type
                        ]);
                        $material_id = $pdo->lastInsertId();
                        logActivity("Uploaded new lesson material '{$title}' ({$clean_original_filename}) for subject '{$subject}'.");

                        
                        $extractRes = LessonExtractionService::extractAndSave($material_id);
                        if ($extractRes['success']) {
                            $success_msg = "Lesson material uploaded and content extracted successfully! ({$extractRes['word_count']} words, {$extractRes['page_count']} pages) <a href='generate_ai.php?material_id={$material_id}' class='underline font-black text-orange-700 ml-2 inline-flex items-center gap-1'><i class='fa-solid fa-wand-magic-sparkles'></i> Generate AI Exam Now &rarr;</a>";
                        } else {
                            $error_msg = "Lesson uploaded, but text extraction encountered an issue: " . $extractRes['error'];
                        }
                    } catch (PDOException $e) {
                        $error_msg = "Database record failed: " . $e->getMessage();
                    }
                } else {
                    $error_msg = "Failed to move uploaded file to server directory.";
                }
            }
        } else {
            $error_msg = "Invalid file type. Allowed formats: PDF, DOCX, PPTX, TXT.";
        }
    } else {
        $error_msg = "Please select a valid file to upload.";
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['retry_extraction'])) {
    validateCSRFToken();
    $material_id = intval($_POST['material_id'] ?? 0);
    $res = LessonExtractionService::extractAndSave($material_id);
    if ($res['success']) {
        $success_msg = "Extraction retried successfully! ({$res['word_count']} words extracted)";
    } else {
        $error_msg = "Extraction retry failed: " . $res['error'];
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_material'])) {
    validateCSRFToken();
    $delete_id = intval($_POST['delete_id'] ?? 0);
    $stmtFindMaterial = $pdo->prepare("SELECT file_path, title FROM lesson_materials WHERE id = ? AND teacher_id = ?");
    $stmtFindMaterial->execute([$delete_id, getCurrentUserId()]);
    $material = $stmtFindMaterial->fetch(PDO::FETCH_ASSOC);

    if ($material) {
        $full_path = __DIR__ . '/' . $material['file_path'];
        if (file_exists($full_path)) {
            unlink($full_path);
        }
        $stmtDeleteMaterial = $pdo->prepare("DELETE FROM lesson_materials WHERE id = ?");
        $stmtDeleteMaterial->execute([$delete_id]);
        logActivity("Deleted lesson material '{$material['title']}'.");
        $success_msg = "Lesson material removed successfully!";
    }
}

$stmtMaterials = $pdo->prepare("SELECT * FROM lesson_materials WHERE teacher_id = ? ORDER BY id DESC");
$stmtMaterials->execute([getCurrentUserId()]);
$materials = $stmtMaterials->fetchAll(PDO::FETCH_ASSOC);
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
<body class="bg-[#fffbf7] min-h-screen flex">
    <?php require_once __DIR__ . '/../includes/teacher_sidebar.php'; ?>
    <main class="flex-1 ml-16 lg:ml-64 p-6 md:p-12 overflow-y-auto min-h-screen">
        <div class="max-w-6xl mx-auto space-y-6">
        <div class="flex items-center justify-between">
            <div>
                <a href="dashboard.php" class="text-xs font-bold text-orange-600 hover:underline"><i class="fa-solid fa-arrow-left mr-1"></i> Back to Dashboard</a>
                <h1 class="text-2xl font-extrabold text-stone-800 mt-2"><i class="fa-solid fa-file-arrow-up text-orange-600 mr-1"></i> Upload Lesson Materials</h1>
                <p class="text-xs text-stone-400">Store class reviewers, syllabi, and reading resources for learning management.</p>
            </div>
        </div>

        <?php if (!empty($success_msg)): ?>
            <div class="bg-emerald-50 border-l-4 border-emerald-500 p-4 rounded-xl text-xs font-semibold text-emerald-700" data-testid="success-alert-banner"><?php echo $success_msg; ?></div>
        <?php endif; ?>
        <?php if (!empty($error_msg)): ?>
            <div class="bg-red-50 border-l-4 border-red-500 p-4 rounded-xl text-xs font-semibold text-red-700" data-testid="error-alert-banner"><?php echo $error_msg; ?></div>
        <?php endif; ?>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            
            
            <div class="bg-white border border-stone-200 rounded-2xl p-5 shadow-sm space-y-4 h-fit">
                <h3 class="text-sm font-bold uppercase tracking-wider text-stone-700 border-b pb-2"><i class="fa-solid fa-cloud-arrow-up text-orange-500 mr-1"></i> Upload New Material</h3>
                
                <form action="upload_lessons.php" method="POST" enctype="multipart/form-data" class="space-y-4">
                    <?php echo csrfInputField(); ?>
                    <div class="space-y-1">
                        <label class="text-xs font-bold text-stone-600">Document Title</label>
                        <input type="text" name="title" required placeholder="e.g. CI/CD Pipeline Fundamentals" class="w-full bg-stone-50 border border-stone-200 rounded-xl px-4 py-2 text-xs outline-none focus:border-orange-500">
                    </div>

                    <?php
                        $stmtT = $pdo->prepare("SELECT handled_subject FROM users WHERE id = ?");
                        $stmtT->execute([$teacher_id]);
                        $teacher_handled_subject = $stmtT->fetchColumn() ?: 'CE 412 - Structural Theory & Design';
                    ?>
                    <div class="space-y-1">
                        <label class="text-xs font-bold text-stone-600">Subject / Category</label>
                        <input type="text" name="subject" value="<?php echo htmlspecialchars($teacher_handled_subject); ?>" required placeholder="e.g. CE 412 - Structural Theory" class="w-full bg-stone-50 border border-stone-200 rounded-xl px-4 py-2 text-xs font-bold outline-none focus:border-orange-500">
                    </div>

                    <div class="space-y-1">
                        <label class="text-xs font-bold text-stone-600">Academic Period</label>
                        <select name="academic_period" class="w-full bg-stone-50 border border-stone-200 rounded-xl px-4 py-2 text-xs outline-none focus:border-orange-500">
                            <option value="general">General</option>
                            <option value="prelim">Prelim</option>
                            <option value="midterm">Midterm</option>
                            <option value="finals">Finals</option>
                        </select>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div class="space-y-1">
                            <label class="text-xs font-bold text-stone-600">Semester</label>
                            <select name="semester" class="w-full bg-stone-50 border border-stone-200 rounded-xl px-4 py-2 text-xs outline-none focus:border-orange-500">
                                <option value="">Select Semester</option>
                                <option value="1st Semester">1st Semester</option>
                                <option value="2nd Semester">2nd Semester</option>
                                <option value="Summer">Summer</option>
                            </select>
                        </div>
                        <div class="space-y-1">
                            <label class="text-xs font-bold text-stone-600">School Year</label>
                            <input type="text" name="school_year" placeholder="e.g. 2025-2026" class="w-full bg-stone-50 border border-stone-200 rounded-xl px-4 py-2 text-xs outline-none focus:border-orange-500">
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div class="space-y-1">
                            <label class="text-xs font-bold text-stone-600">Year Level</label>
                            <select name="year_level" class="w-full bg-stone-50 border border-stone-200 rounded-xl px-4 py-2 text-xs outline-none focus:border-orange-500">
                                <option value="All Year Levels">All Year Levels</option>
                                <option value="1st Year">1st Year</option>
                                <option value="2nd Year">2nd Year</option>
                                <option value="3rd Year">3rd Year</option>
                                <option value="4th Year">4th Year</option>
                            </select>
                        </div>
                        <div class="space-y-1">
                            <label class="text-xs font-bold text-stone-600">Program</label>
                            <select name="program" class="w-full bg-stone-50 border border-stone-200 rounded-xl px-4 py-2 text-xs outline-none focus:border-orange-500 font-bold">
                                <option value="BSCE" selected>BSCE (Civil Engineering)</option>
                            </select>
                        </div>
                    </div>

                    <div class="space-y-1">
                        <label class="text-xs font-bold text-stone-600">Select File Document</label>
                        <div onclick="triggerFileSelect()" class="border-2 border-dashed border-stone-300 hover:border-orange-400 rounded-xl p-5 bg-stone-50 text-center cursor-pointer transition-all" id="drop_zone">
                            <i class="fa-solid fa-file-pdf text-2xl text-stone-400 mb-1" id="upload_icon"></i>
                            <p class="text-[11px] text-stone-700 font-bold" id="upload_text">Choose file or drag here</p>
                            <p class="text-[9px] text-stone-400 mt-0.5" id="file_details">PDF, DOCX, PPTX, or TXT (Max 10MB)</p>
                            <input type="file" name="lesson_file" id="lesson_file" accept=".pdf,.docx,.pptx,.txt" required class="hidden" onchange="displaySelectedFile()">
                        </div>
                    </div>

                    <button type="submit" name="upload_material" class="w-full bg-orange-600 hover:bg-orange-700 text-white font-bold text-xs py-2.5 rounded-xl transition-all shadow-md flex items-center justify-center gap-2">
                        <i class="fa-solid fa-upload"></i> Upload Material
                    </button>
                </form>
            </div>

            
            <div class="lg:col-span-2 bg-white border border-stone-200 rounded-2xl p-6 shadow-sm space-y-4">
                <div class="flex items-center justify-between border-b pb-3">
                    <h3 class="text-sm font-bold uppercase tracking-wider text-stone-700"><i class="fa-solid fa-folder-open text-orange-500 mr-1"></i> Lesson Repository</h3>
                    <span class="bg-stone-100 text-stone-700 text-xs font-bold px-3 py-1 rounded-full"><?php echo count($materials); ?> Files Saved</span>
                </div>

                <?php if (!empty($materials)): ?>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <?php foreach ($materials as $m): ?>
                                <?php 
                                    $status = $m['processing_status'] ?? 'pending';
                                    $statusBadgeClass = 'bg-stone-100 text-stone-700';
                                    if ($status === 'completed') $statusBadgeClass = 'bg-emerald-100 text-emerald-800 border-emerald-200';
                                    elseif ($status === 'failed') $statusBadgeClass = 'bg-rose-100 text-rose-800 border-rose-200';
                                    elseif ($status === 'processing') $statusBadgeClass = 'bg-amber-100 text-amber-800 border-amber-200';
                                    
                                    $period = $m['academic_period'] ?? 'general';
                                    $periodClass = 'bg-stone-100 text-stone-600 border-stone-200';
                                    if ($period === 'prelim') $periodClass = 'bg-blue-100 text-blue-700 border-blue-200';
                                    elseif ($period === 'midterm') $periodClass = 'bg-amber-100 text-amber-700 border-amber-200';
                                    elseif ($period === 'finals') $periodClass = 'bg-purple-100 text-purple-700 border-purple-200';
                                ?>
                                <div class="p-4 border border-stone-200 rounded-xl bg-stone-50/40 hover:border-orange-300 transition-all flex flex-col justify-between space-y-3">
                                    <div class="flex items-start gap-3">
                                        <div class="p-3 bg-orange-100 text-orange-700 rounded-lg flex-shrink-0">
                                            <i class="fa-solid fa-file-lines text-xl"></i>
                                        </div>
                                        <div class="min-w-0 flex-1">
                                            <div class="flex items-center justify-between gap-1">
                                                <h4 class="font-bold text-xs text-stone-800 truncate"><?php echo htmlspecialchars($m['title']); ?></h4>
                                                <div class="flex gap-1">
                                                    <span class="text-[9px] font-extrabold uppercase px-2 py-0.5 rounded-full border <?php echo $periodClass; ?>">
                                                        <?php echo ucfirst($period); ?>
                                                    </span>
                                                    <span class="text-[9px] font-extrabold uppercase px-2 py-0.5 rounded-full border <?php echo $statusBadgeClass; ?>">
                                                        <i class="fa-solid fa-circle text-[7px] mr-1"></i><?php echo ucfirst($status); ?>
                                                    </span>
                                                </div>
                                            </div>
                                            <div class="text-[10px] text-stone-400 font-semibold mt-0.5 flex gap-2">
                                                <span><?php echo htmlspecialchars($m['subject']); ?></span>
                                                <?php if (!empty($m['semester']) || !empty($m['school_year'])): ?>
                                                    <span>• <?php echo htmlspecialchars(trim(($m['semester'] ?? '') . ' ' . ($m['school_year'] ?? ''))); ?></span>
                                                <?php endif; ?>
                                            </div>
                                        
                                        <div class="flex flex-wrap items-center gap-1.5 mt-2">
                                            <span class="text-[9px] font-extrabold uppercase bg-stone-200 text-stone-700 px-1.5 py-0.5 rounded">
                                                <?php echo htmlspecialchars($m['file_type']); ?> • <?php echo number_format($m['file_size'] / 1024, 1); ?> KB
                                            </span>
                                            <?php if ($status === 'completed'): ?>
                                                <span class="text-[9px] font-bold bg-blue-50 text-blue-700 px-1.5 py-0.5 rounded border border-blue-200">
                                                    <i class="fa-solid fa-font mr-0.5"></i><?php echo number_format($m['word_count']); ?> words
                                                </span>
                                                <span class="text-[9px] font-bold bg-purple-50 text-purple-700 px-1.5 py-0.5 rounded border border-purple-200">
                                                    <i class="fa-solid fa-book-open mr-0.5"></i><?php echo $m['page_count']; ?> pages
                                                </span>
                                            <?php endif; ?>
                                        </div>

                                        <?php if ($status === 'failed' && !empty($m['processing_error'])): ?>
                                            <p class="text-[10px] text-rose-600 font-bold mt-2 bg-rose-50 p-2 rounded-lg border border-rose-100">
                                                <i class="fa-solid fa-circle-exclamation mr-1"></i><?php echo htmlspecialchars($m['processing_error']); ?>
                                            </p>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <?php if (!empty($m['lesson_text'])): ?>
                                    <details class="text-xs text-stone-600 bg-white p-2.5 rounded-lg border border-stone-200">
                                        <summary class="cursor-pointer font-bold text-[10px] uppercase text-orange-600 hover:underline flex items-center gap-1">
                                            <i class="fa-solid fa-eye"></i> Preview Extracted Content
                                        </summary>
                                        <div class="mt-2 text-[11px] font-mono text-stone-700 max-h-36 overflow-y-auto whitespace-pre-wrap p-2 bg-stone-50 rounded border">
                                            <?php echo htmlspecialchars(mb_substr($m['lesson_text'], 0, 1000)); ?>
                                            <?php if (mb_strlen($m['lesson_text']) > 1000): ?>... [Truncated preview]<?php endif; ?>
                                        </div>
                                    </details>
                                <?php endif; ?>

                                <div class="flex items-center justify-between pt-2 border-t border-stone-100 text-xs font-bold">
                                    <div class="flex items-center gap-2">
                                        <a href="<?php echo htmlspecialchars($m['file_path']); ?>" download class="text-orange-600 hover:underline flex items-center gap-1">
                                            <i class="fa-solid fa-download"></i> Download
                                        </a>
                                        <?php if ($status === 'completed'): ?>
                                            <a href="generate_ai.php?material_id=<?php echo $m['id']; ?>" class="bg-orange-600 hover:bg-orange-700 text-white font-extrabold text-[10px] px-2.5 py-1 rounded-lg transition-all flex items-center gap-1 shadow-sm">
                                                <i class="fa-solid fa-wand-magic-sparkles"></i> Generate AI Exam
                                            </a>
                                        <?php endif; ?>
                                        <?php if ($status === 'failed' || $status === 'pending'): ?>
                                            <form method="POST" action="upload_lessons.php" class="inline">
                                                <?php echo csrfInputField(); ?>
                                                <input type="hidden" name="material_id" value="<?php echo $m['id']; ?>">
                                                <button type="submit" name="retry_extraction" class="text-amber-600 hover:underline text-[11px] font-bold flex items-center gap-1">
                                                    <i class="fa-solid fa-rotate-right"></i> Retry Extract
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <form method="POST" action="upload_lessons.php" onsubmit="return confirm('Are you sure you want to delete this file?');">
                                        <?php echo csrfInputField(); ?>
                                        <input type="hidden" name="delete_id" value="<?php echo $m['id']; ?>">
                                        <button type="submit" name="delete_material" class="text-rose-500 hover:underline flex items-center gap-1 text-xs">
                                            <i class="fa-solid fa-trash"></i> Delete
                                        </button>
                                    </form>
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
    </main>
</body>
</html>