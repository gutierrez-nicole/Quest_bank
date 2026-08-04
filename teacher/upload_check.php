<?php
require_once __DIR__ . '/../app/bootstrap.php';
require_once __DIR__ . '/../app/services/OcrService.php';
require_once __DIR__ . '/../app/services/AnswerSheetParser.php';
require_once __DIR__ . '/../app/services/ExamScoringService.php';

AuthService::enforceRole('teacher');
$pdo = getDBConnection();

try {
    $teacher_id = getCurrentUserId();
    $stmt = $pdo->prepare("SELECT fullname, username, email FROM users WHERE id = ?");
    $stmt->execute([$teacher_id]);
    $teacher = $stmt->fetch(PDO::FETCH_ASSOC);

    
    $stmtExams = $pdo->prepare("SELECT id, title, subject, passing_percentage FROM exams WHERE teacher_id = ? OR created_by = ? ORDER BY id DESC");
    $stmtExams->execute([$teacher_id, $teacher_id]);
    $available_exams = $stmtExams->fetchAll(PDO::FETCH_ASSOC);

    
    $stmtStudents = $pdo->query("SELECT id, fullname, email FROM users WHERE role = 'student' ORDER BY fullname ASC");
    $available_students = $stmtStudents->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}

$success_msg = "";
$error_msg = "";
$evaluation_summary = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['process_ocr_grading'])) {
    validateCSRFToken();

    $exam_id = intval($_POST['exam_id'] ?? 0);
    $student_id = intval($_POST['student_id'] ?? 0);

    if (empty($exam_id) || empty($student_id)) {
        $error_msg = "Please select a valid Exam and Enrolled Student.";
    } elseif (!isset($_FILES['exam_file']) || $_FILES['exam_file']['error'] !== UPLOAD_ERR_OK) {
        $error_msg = "Please attach a valid answer sheet file (JPG, PNG, PDF).";
    } else {
        
        $stmtCheck = $pdo->prepare("SELECT * FROM exams WHERE id = ? AND (teacher_id = ? OR created_by = ?)");
        $stmtCheck->execute([$exam_id, $teacher_id, $teacher_id]);
        $examObj = $stmtCheck->fetch(PDO::FETCH_ASSOC);

        if (!$examObj) {
            $error_msg = "Unauthorized: Selected exam does not belong to your account.";
        } else {
            $file_name = $_FILES['exam_file']['name'];
            $file_tmp = $_FILES['exam_file']['tmp_name'];
            $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));

            
            $upload_dir = __DIR__ . '/../uploads/ocr_sheets/';
            if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
            $target_file = $upload_dir . uniqid('ocr_') . '.' . $file_ext;

            if (move_uploaded_file($file_tmp, $target_file)) {
                
                $ocrRes = OcrService::processAnswerSheet($target_file, $file_ext);
                $ocrText = $ocrRes['text'] ?? '';

                
                $qStmt = $pdo->prepare("SELECT * FROM exam_questions WHERE exam_id = ? ORDER BY id ASC");
                $qStmt->execute([$exam_id]);
                $examQuestions = $qStmt->fetchAll(PDO::FETCH_ASSOC);

                
                $parsedOcr = AnswerSheetParser::parseAnswerSheet($ocrText, $examQuestions);
                $submittedAnswers = $parsedOcr['answers'];

                
                if (!empty($_POST['corrected_ocr_text'])) {
                    $correctedText = trim(sanitizeInput($_POST['corrected_ocr_text']));
                    $parsedCorr = AnswerSheetParser::parseAnswerSheet($correctedText, $examQuestions);
                    $submittedAnswers = $parsedCorr['answers'];
                }

                
                try {
                    $fileMeta = [
                        'ocr_text' => $ocrText,
                        'ocr_confidence' => $ocrRes['confidence'],
                        'ocr_status' => $ocrRes['status'],
                        'suggested_manual_review' => ($ocrRes['confidence'] < 75.0 || $parsedOcr['requires_review']) ? 1 : 0,
                        'page_count' => $ocrRes['page_count'],
                        'file_path' => 'uploads/ocr_sheets/' . basename($target_file),
                        'original_filename' => $file_name
                    ];

                    $evalRes = ExamScoringService::evaluateAndSaveSubmission(
                        $exam_id,
                        $student_id,
                        $submittedAnswers,
                        $teacher_id,
                        'scanned',
                        $fileMeta
                    );

                    logActivity("Processed OCR grading for submission #{$evalRes['submission_id']} (Exam #{$exam_id}).", $teacher_id);
                    $success_msg = "Answer sheet processed & scored server-side! Submission #{$evalRes['submission_id']} saved as Pending Review.";
                    $evaluation_summary = $evalRes;

                } catch (Exception $e) {
                    $error_msg = "Scoring Error: " . $e->getMessage();
                }
            } else {
                $error_msg = "Failed to store uploaded file on server.";
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>QuestBank - OCR Answer Sheet Checker</title>
    
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; }
        .bg-orange-gradient { background: linear-gradient(135deg, #f57c00 0%, #d84315 100%); }
    </style>
</head>
<body class="bg-[#fffbf7] min-h-screen flex">

    <?php require_once __DIR__ . '/../includes/teacher_sidebar.php'; ?>

    <main class="flex-grow flex flex-col min-w-0 ml-16 lg:ml-64 min-h-screen">
        <header class="bg-white border-b border-stone-200 px-6 py-4 flex items-center justify-between flex-shrink-0">
            <div>
                <h2 class="text-lg font-bold text-stone-800"><i class="fa-solid fa-camera-retro text-orange-600 mr-2"></i>Optical Answer Sheet Evaluator</h2>
                <p class="text-xs text-stone-400">Scan or upload student answer sheets for automated server-side answer key comparison.</p>
            </div>
            
            <div class="flex items-center gap-3 pl-2 border-l border-stone-200">
                <div class="w-9 h-9 rounded-xl bg-orange-100 text-orange-700 font-bold flex items-center justify-center">
                    <?php echo strtoupper(substr($teacher['fullname'] ?? 'Prof', 0, 2)); ?>
                </div>
                <div class="hidden sm:block text-left">
                    <p class="text-xs font-bold text-stone-800"><?php echo htmlspecialchars($teacher['fullname'] ?? 'Teacher'); ?></p>
                    <p class="text-[10px] text-stone-400">Faculty Professor</p>
                </div>
            </div>
        </header>

        <div class="flex-grow overflow-y-auto p-6 space-y-6">

            <?php if (!empty($success_msg)): ?>
                <div class="bg-emerald-50 border-l-4 border-emerald-500 p-4 rounded-xl text-xs font-semibold text-emerald-800 flex items-center justify-between">
                    <span class="flex items-center gap-2"><i class="fa-solid fa-circle-check text-emerald-600 text-sm"></i> <?php echo $success_msg; ?></span>
                    <button onclick="this.parentElement.remove();" class="text-emerald-500 hover:text-emerald-800"><i class="fa-solid fa-xmark"></i></button>
                </div>
            <?php endif; ?>

            <?php if (!empty($error_msg)): ?>
                <div class="bg-rose-50 border-l-4 border-rose-500 p-4 rounded-xl text-xs font-semibold text-rose-800 flex items-center justify-between">
                    <span class="flex items-center gap-2"><i class="fa-solid fa-circle-exclamation text-rose-600 text-sm"></i> <?php echo $error_msg; ?></span>
                    <button onclick="this.parentElement.remove();" class="text-rose-500 hover:text-rose-800"><i class="fa-solid fa-xmark"></i></button>
                </div>
            <?php endif; ?>

            <div class="bg-white border border-stone-200 rounded-2xl p-6 shadow-sm max-w-3xl space-y-6">
                <h3 class="text-sm font-extrabold text-stone-800 border-b border-stone-100 pb-3 flex items-center gap-2">
                    <i class="fa-solid fa-file-arrow-up text-orange-600"></i> Process Student Answer Sheet
                </h3>

                <form action="upload_check.php" method="POST" enctype="multipart/form-data" class="space-y-4">
                    <?php echo csrfInputField(); ?>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-bold text-stone-700 mb-1">Select Stored Exam</label>
                            <select name="exam_id" required class="w-full bg-stone-50 border border-stone-200 rounded-xl px-3 py-2.5 text-xs font-semibold text-stone-800 outline-none focus:border-orange-500">
                                <option value="">-- Choose Exam --</option>
                                <?php foreach ($available_exams as $ex): ?>
                                    <option value="<?php echo $ex['id']; ?>">
                                        <?php echo htmlspecialchars($ex['title']); ?> (<?php echo htmlspecialchars($ex['subject']); ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-stone-700 mb-1">Select Enrolled Student</label>
                            <select name="student_id" required class="w-full bg-stone-50 border border-stone-200 rounded-xl px-3 py-2.5 text-xs font-semibold text-stone-800 outline-none focus:border-orange-500">
                                <option value="">-- Choose Student --</option>
                                <?php foreach ($available_students as $st): ?>
                                    <option value="<?php echo $st['id']; ?>">
                                        <?php echo htmlspecialchars($st['fullname']); ?> (<?php echo htmlspecialchars($st['email']); ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-stone-700 mb-1">Upload Answer Sheet (JPG, PNG, PDF)</label>
                        <input type="file" name="exam_file" required accept=".jpg,.jpeg,.png,.pdf" class="w-full bg-stone-50 border border-stone-200 rounded-xl p-2 text-xs font-semibold text-stone-800">
                    </div>

                    <button type="submit" name="process_ocr_grading" class="w-full bg-orange-gradient text-white font-bold text-xs py-3 rounded-xl shadow hover:opacity-95 transition-all flex items-center justify-center gap-2">
                        <i class="fa-solid fa-microchip"></i> Process & Grade Server-Side
                    </button>
                </form>
            </div>

            <?php if ($evaluation_summary): ?>
                <div class="bg-white border border-stone-200 rounded-2xl p-6 shadow-sm space-y-4 max-w-3xl animate-fadeIn">
                    <div class="flex items-center justify-between border-b border-stone-100 pb-3">
                        <h4 class="text-sm font-extrabold text-stone-800">Server-Calculated Submission Summary</h4>
                        <span class="px-3 py-1 rounded-xl text-xs font-black uppercase bg-orange-100 text-orange-800">
                            Status: <?php echo htmlspecialchars($evaluation_summary['status']); ?>
                        </span>
                    </div>

                    <div class="grid grid-cols-3 gap-4 text-center">
                        <div class="p-3 bg-stone-50 rounded-xl border border-stone-200">
                            <p class="text-[10px] uppercase font-bold text-stone-400">Awarded Points</p>
                            <p class="text-lg font-black text-stone-800"><?php echo number_format($evaluation_summary['total_awarded_points'], 2); ?> / <?php echo number_format($evaluation_summary['total_possible_points'], 2); ?></p>
                        </div>
                        <div class="p-3 bg-stone-50 rounded-xl border border-stone-200">
                            <p class="text-[10px] uppercase font-bold text-stone-400">Score Percentage</p>
                            <p class="text-lg font-black text-orange-600"><?php echo number_format($evaluation_summary['percentage'], 2); ?>%</p>
                        </div>
                        <div class="p-3 bg-stone-50 rounded-xl border border-stone-200">
                            <p class="text-[10px] uppercase font-bold text-stone-400">Correct / Wrong</p>
                            <p class="text-lg font-black text-emerald-600"><?php echo $evaluation_summary['correct_count']; ?> <span class="text-stone-300">/</span> <span class="text-rose-600"><?php echo $evaluation_summary['incorrect_count']; ?></span></p>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

        </div>
    </main>
</body>
</html>