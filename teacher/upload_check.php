<?php
require_once __DIR__ . '/../app/bootstrap.php';
require_once __DIR__ . '/../app/services/OcrService.php';
require_once __DIR__ . '/../app/services/AnswerSheetParser.php';
require_once __DIR__ . '/../app/services/ExamScoringService.php';
require_once __DIR__ . '/../app/services/FileValidationService.php';
require_once __DIR__ . '/../app/services/ExamService.php';

AuthService::enforceRole('teacher');
$pdo = getDBConnection();
$teacher_id = getCurrentUserId();

if (isset($_GET['action']) && $_GET['action'] === 'get_eligible_students') {
    header('Content-Type: application/json');
    $exam_id = intval($_GET['exam_id'] ?? 0);
    $students = ExamService::getEligibleStudentsForExam($pdo, $exam_id, $teacher_id);
    echo json_encode(['success' => true, 'students' => $students]);
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT fullname, username, email FROM users WHERE id = ?");
    $stmt->execute([$teacher_id]);
    $teacher = $stmt->fetch(PDO::FETCH_ASSOC);

    $stmtExams = $pdo->prepare("SELECT id, title, subject, passing_percentage FROM exams WHERE teacher_id = ? OR created_by = ? ORDER BY id DESC");
    $stmtExams->execute([$teacher_id, $teacher_id]);
    $available_exams = $stmtExams->fetchAll(PDO::FETCH_ASSOC);

    $eligible_students_map = [];
    foreach ($available_exams as $ex) {
        $eligible_students_map[$ex['id']] = ExamService::getEligibleStudentsForExam($pdo, $ex['id'], $teacher_id);
    }

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

    $stmtCheck = $pdo->prepare("SELECT * FROM exams WHERE id = ? AND (teacher_id = ? OR created_by = ?)");
    $stmtCheck->execute([$exam_id, $teacher_id, $teacher_id]);
    $examObj = $stmtCheck->fetch(PDO::FETCH_ASSOC);

    $eligibleStudents = ExamService::getEligibleStudentsForExam($pdo, $exam_id, $teacher_id);
    $eligibleIds = array_map('intval', array_column($eligibleStudents, 'id'));

    if (empty($exam_id) || empty($student_id)) {
        $error_msg = "Please select a valid Exam and Enrolled Student.";
    } elseif (!$examObj) {
        $error_msg = "Unauthorized: Selected exam does not belong to your account.";
    } elseif (!in_array($student_id, $eligibleIds, true)) {
        $error_msg = "Unauthorized: Selected student is not eligible for this exam.";
    } else {
        // Normalize incoming files (support exam_files[] multiple or single exam_file)
        $rawUploadedFiles = [];
        if (isset($_FILES['exam_files']) && is_array($_FILES['exam_files']['name'])) {
            foreach ($_FILES['exam_files']['name'] as $i => $name) {
                if ($_FILES['exam_files']['error'][$i] === UPLOAD_ERR_OK && !empty($name)) {
                    $rawUploadedFiles[] = [
                        'name' => $name,
                        'tmp_name' => $_FILES['exam_files']['tmp_name'][$i],
                        'size' => $_FILES['exam_files']['size'][$i]
                    ];
                }
            }
        } elseif (isset($_FILES['exam_file']) && $_FILES['exam_file']['error'] === UPLOAD_ERR_OK && !empty($_FILES['exam_file']['name'])) {
            $rawUploadedFiles[] = [
                'name' => $_FILES['exam_file']['name'],
                'tmp_name' => $_FILES['exam_file']['tmp_name'],
                'size' => $_FILES['exam_file']['size']
            ];
        }

        if (empty($rawUploadedFiles)) {
            $error_msg = "Please attach at least one valid answer sheet page or camera capture (JPG, PNG, PDF).";
        } else {
            $upload_dir = __DIR__ . '/../uploads/ocr_sheets/';
            if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);

            $processedFileEntries = [];
            $primaryFilename = $rawUploadedFiles[0]['name'];

            foreach ($rawUploadedFiles as $f) {
                $validation = FileValidationService::validateFile($f['tmp_name'], $f['name'], 10485760);
                if (!$validation['success']) {
                    $error_msg = "Security Validation Failed for {$f['name']}: " . $validation['error'];
                    break;
                }

                $file_ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
                if (in_array($file_ext, ['jpg', 'jpeg', 'png'], true)) {
                    $imgInfo = @getimagesize($f['tmp_name']);
                    if (!$imgInfo || $imgInfo[0] < 10 || $imgInfo[1] < 10) {
                        $error_msg = "Security Validation Failed for {$f['name']}: Invalid or corrupted image format.";
                        break;
                    }
                }

                $target_file = $upload_dir . uniqid('ocr_') . '.' . $file_ext;
                if (move_uploaded_file($f['tmp_name'], $target_file)) {
                    $processedFileEntries[] = [
                        'path' => $target_file,
                        'ext' => $file_ext,
                        'filename' => $f['name']
                    ];
                } else {
                    $error_msg = "Failed to store uploaded page '{$f['name']}' on server.";
                    break;
                }
            }

            if (empty($error_msg) && !empty($processedFileEntries)) {
                $ocrRes = OcrService::processMultipleAnswerSheets($processedFileEntries);
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
                    $pageCount = max(count($processedFileEntries), intval($ocrRes['page_count'] ?? 1));
                    $fileMeta = [
                        'ocr_text' => $ocrText,
                        'ocr_confidence' => $ocrRes['confidence'],
                        'ocr_status' => $ocrRes['status'],
                        'suggested_manual_review' => ($ocrRes['confidence'] < 75.0 || $parsedOcr['requires_review']) ? 1 : 0,
                        'page_count' => $pageCount,
                        'file_path' => 'uploads/ocr_sheets/' . basename($processedFileEntries[0]['path']),
                        'original_filename' => (count($processedFileEntries) > 1) 
                            ? count($processedFileEntries) . " Scanned Pages (" . $primaryFilename . " et al.)"
                            : $primaryFilename
                    ];

                    $evalRes = ExamScoringService::evaluateAndSaveSubmission(
                        $exam_id,
                        $student_id,
                        $submittedAnswers,
                        $teacher_id,
                        'scanned',
                        $fileMeta
                    );

                    logActivity("Processed multi-page OCR grading ({$pageCount} pages) for submission #{$evalRes['submission_id']} (Exam #{$exam_id}).", $teacher_id);
                    $success_msg = "Answer sheet ({$pageCount} page(s)) processed & scored server-side! Submission #{$evalRes['submission_id']} saved as Pending Review.";
                    $evaluation_summary = $evalRes;

                } catch (Exception $e) {
                    $error_msg = "Scoring Error: " . $e->getMessage();
                }
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
                <p class="text-xs text-stone-400">Scan using device camera or upload student answer sheets for automated server-side answer key comparison.</p>
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

                <form action="upload_check.php" method="POST" enctype="multipart/form-data" class="space-y-4" id="ocrUploadForm">
                    <?php echo csrfInputField(); ?>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-bold text-stone-700 mb-1">Select Stored Exam</label>
                            <select name="exam_id" id="examSelect" required onchange="onExamChanged()" class="w-full bg-stone-50 border border-stone-200 rounded-xl px-3 py-2.5 text-xs font-semibold text-stone-800 outline-none focus:border-orange-500">
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
                            <select name="student_id" id="studentSelect" required disabled class="w-full bg-stone-50 border border-stone-200 rounded-xl px-3 py-2.5 text-xs font-semibold text-stone-800 outline-none focus:border-orange-500 disabled:opacity-50 disabled:bg-stone-100">
                                <option value="">-- Choose Exam First --</option>
                            </select>
                            <p id="studentNotice" class="text-[11px] font-semibold text-amber-700 hidden mt-1 flex items-center gap-1">
                                <i class="fa-solid fa-circle-info"></i> No eligible students found for this exam.
                            </p>
                        </div>
                    </div>

                    <!-- Input Methods (Scan vs Upload) -->
                    <div class="space-y-3">
                        <div class="flex items-center justify-between">
                            <label class="block text-xs font-bold text-stone-700">Answer Sheet Source (Supports Multi-Page Test Papers)</label>
                            <span class="text-[10px] text-stone-400 font-semibold">1 or more pages</span>
                        </div>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <button type="button" id="openCameraButton" data-testid="scan-using-camera-btn" onclick="openCameraScanner()" class="w-full bg-orange-600 hover:bg-orange-700 text-white font-bold text-xs py-3 px-4 rounded-xl shadow-sm transition-all flex items-center justify-center gap-2">
                                <i class="fa-solid fa-camera text-sm"></i> Scan Pages Using Camera
                            </button>

                            <label for="examFileInput" class="w-full bg-stone-100 hover:bg-stone-200 border border-stone-300 text-stone-700 font-bold text-xs py-3 px-4 rounded-xl cursor-pointer transition-all flex items-center justify-center gap-2 text-center">
                                <i class="fa-solid fa-upload text-sm"></i> Upload Images or PDF
                            </label>
                        </div>

                        <!-- Selected Files / Multi-Page Scan Status Tray -->
                        <div class="p-3.5 bg-stone-50 border border-stone-200 rounded-xl space-y-2.5" id="fileStatusContainer">
                            <div class="flex items-center justify-between gap-2">
                                <div class="flex items-center gap-2 overflow-hidden">
                                    <i class="fa-solid fa-file-lines text-orange-500 text-base flex-shrink-0" id="fileIcon"></i>
                                    <span class="text-xs font-bold text-stone-800 truncate" id="fileNameDisplay">No answer sheet pages selected yet</span>
                                </div>
                                <span class="text-[10px] font-extrabold text-orange-700 bg-orange-100 border border-orange-200 px-2.5 py-0.5 rounded-full flex-shrink-0" id="pageCountDisplay">0 Pages</span>
                            </div>
                            
                            <!-- Multi-page Badges Tray -->
                            <div id="pagesListTray" class="flex flex-wrap gap-2 pt-1 hidden"></div>

                            <input type="file" name="exam_files[]" id="examFileInput" multiple accept=".jpg,.jpeg,.png,.pdf" onchange="onFileSelected(event)" class="hidden">
                            <input type="file" id="mobileDirectCameraInput" accept="image/*" capture="environment" onchange="onDirectCameraCapture(event)" class="hidden">
                        </div>
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

    <!-- Camera Answer Sheet Scanner Modal (Multi-Page Supported) -->
    <div id="cameraModal" class="fixed inset-0 z-50 bg-black/80 backdrop-blur-sm hidden flex flex-col items-center justify-center p-3 sm:p-6" data-testid="camera-modal">
        <div class="bg-white border border-stone-200 rounded-2xl shadow-2xl w-full max-w-2xl flex flex-col max-h-[95vh] overflow-hidden">
            
            <!-- Modal Header -->
            <div class="bg-stone-900 text-white px-5 py-3.5 flex items-center justify-between flex-shrink-0">
                <div class="flex items-center gap-2.5">
                    <div class="w-8 h-8 rounded-lg bg-orange-500/20 text-orange-400 flex items-center justify-center">
                        <i class="fa-solid fa-camera text-sm"></i>
                    </div>
                    <div>
                        <div class="flex items-center gap-2">
                            <h4 class="text-sm font-extrabold text-stone-100 leading-tight">Answer Sheet Scanner</h4>
                            <span id="modalPageBadge" class="text-[10px] bg-orange-500/30 text-orange-200 border border-orange-400/40 px-2.5 py-0.5 rounded-full font-bold">Scanning Page 1</span>
                        </div>
                        <p class="text-[10px] text-stone-400">Position student test paper page inside the framing box</p>
                    </div>
                </div>
                <button type="button" data-testid="close-camera-btn" onclick="closeCameraScanner()" class="w-8 h-8 text-stone-400 hover:text-white rounded-lg flex items-center justify-center hover:bg-stone-800 transition-all" aria-label="Close Camera Scanner">
                    <i class="fa-solid fa-xmark text-lg"></i>
                </button>
            </div>

            <!-- Multi-Page Banner in Modal (shows if 1 or more pages already scanned) -->
            <div id="modalPagesTrayBanner" class="hidden bg-stone-800 px-5 py-2 flex items-center justify-between border-b border-stone-700 text-xs text-stone-300">
                <div class="flex items-center gap-2">
                    <i class="fa-solid fa-layer-group text-orange-400"></i>
                    <span id="modalScannedPagesCountText" class="font-bold text-white">0 Pages Scanned</span>
                </div>
                <button type="button" onclick="confirmCapturedPhoto(true)" class="text-[11px] bg-emerald-600 hover:bg-emerald-700 text-white font-bold px-3 py-1 rounded-lg transition-all flex items-center gap-1">
                    <i class="fa-solid fa-check"></i> Finish & Attach All
                </button>
            </div>

            <!-- Camera Viewfinder & Preview Container -->
            <div class="relative bg-black flex-grow flex items-center justify-center min-h-[300px] max-h-[60vh] overflow-hidden select-none">
                
                <!-- Live Video Feed -->
                <video id="cameraVideo" autoplay playsinline class="w-full h-full object-contain" data-testid="camera-video-preview"></video>

                <!-- Document Framing Overlay Guide -->
                <div id="framingOverlay" data-testid="document-framing-overlay" class="absolute inset-0 pointer-events-none flex flex-col items-center justify-between p-4">
                    <div class="w-full text-center bg-black/70 backdrop-blur-xs text-white text-[11px] font-semibold py-1.5 px-3 rounded-full border border-white/20 shadow">
                        <i class="fa-solid fa-circle-info text-orange-400 mr-1.5"></i>
                        Position the complete page inside the frame. Avoid shadows and keep camera steady.
                    </div>
                    <div class="w-full h-full max-w-md max-h-[75%] border-2 border-dashed border-orange-400/90 rounded-2xl relative shadow-[0_0_0_9999px_rgba(0,0,0,0.35)] transition-all">
                        <div class="absolute top-2 left-2 w-4 h-4 border-t-2 border-l-2 border-orange-400"></div>
                        <div class="absolute top-2 right-2 w-4 h-4 border-t-2 border-r-2 border-orange-400"></div>
                        <div class="absolute bottom-2 left-2 w-4 h-4 border-b-2 border-l-2 border-orange-400"></div>
                        <div class="absolute bottom-2 right-2 w-4 h-4 border-b-2 border-r-2 border-orange-400"></div>
                    </div>
                    <div class="text-[10px] text-stone-300 bg-black/60 px-3 py-1 rounded-full">
                        Ensure adequate lighting for optimal OCR accuracy
                    </div>
                </div>

                <!-- Captured Image Preview Container -->
                <div id="previewContainer" class="hidden absolute inset-0 bg-black flex flex-col items-center justify-center">
                    <img id="capturedImagePreview" data-testid="captured-image-preview" class="w-full h-full object-contain" alt="Captured Answer Sheet Preview">
                </div>

                <!-- Error & Permission Banner with Direct Mobile Camera Fallback -->
                <div id="cameraErrorBanner" data-testid="camera-error-message" class="hidden absolute inset-4 bg-stone-900/95 border border-stone-700 text-stone-100 p-5 rounded-2xl flex flex-col items-center justify-center text-center space-y-4 shadow-2xl">
                    <div class="w-12 h-12 rounded-2xl bg-orange-500/20 text-orange-400 flex items-center justify-center text-2xl">
                        <i class="fa-solid fa-camera"></i>
                    </div>
                    <div class="space-y-1 max-w-md">
                        <h5 class="text-sm font-extrabold text-white">Direct Phone Camera Capture</h5>
                        <p id="cameraErrorText" class="text-xs text-stone-300 leading-relaxed">Live browser stream requires HTTPS over network IP. You can take a photo directly using your phone's native camera below.</p>
                    </div>
                    <div class="flex flex-col sm:flex-row items-center gap-2.5 w-full max-w-xs">
                        <button type="button" onclick="launchNativeCameraCapture()" class="w-full bg-orange-600 hover:bg-orange-700 text-white font-extrabold text-xs py-3 px-4 rounded-xl shadow-lg transition-all flex items-center justify-center gap-2">
                            <i class="fa-solid fa-camera"></i> Open Phone Camera
                        </button>
                        <button type="button" onclick="closeCameraScanner()" class="w-full bg-stone-800 hover:bg-stone-700 text-stone-300 font-bold text-xs py-3 px-4 rounded-xl transition-all">
                            Close Scanner
                        </button>
                    </div>
                </div>
            </div>

            <!-- Meta Display Bar -->
            <div id="imageMetaBar" class="hidden bg-stone-100 border-t border-stone-200 px-5 py-2 flex flex-wrap items-center justify-between text-xs text-stone-700 gap-2">
                <span id="imageDimensionsDisplay" data-testid="image-dimensions" class="font-mono font-bold text-[11px] text-stone-600">Dimensions: 0 x 0 px</span>
                <div id="lowResWarning" data-testid="low-resolution-warning" class="hidden text-amber-800 bg-amber-100 border border-amber-300 px-2.5 py-0.5 rounded-md font-bold text-[10px] flex items-center gap-1">
                    <i class="fa-solid fa-triangle-exclamation"></i> Low resolution (< 800px width). OCR accuracy may decrease.
                </div>
            </div>

            <!-- Controls Bar -->
            <div class="bg-stone-50 border-t border-stone-200 p-4 flex-shrink-0">
                <!-- Stream Controls (Before Capture) -->
                <div id="streamControls" class="flex items-center justify-between gap-3">
                    <button type="button" id="switchCameraBtn" data-testid="switch-camera-btn" onclick="switchCamera()" class="hidden bg-stone-200 hover:bg-stone-300 text-stone-700 font-bold text-xs py-2.5 px-3.5 rounded-xl transition-all flex items-center gap-1.5" aria-label="Switch Camera">
                        <i class="fa-solid fa-camera-rotate"></i> Switch Camera
                    </button>

                    <button type="button" id="captureBtn" data-testid="capture-btn" onclick="capturePhoto()" class="mx-auto bg-orange-gradient text-white font-extrabold text-xs py-3 px-6 rounded-xl shadow hover:opacity-95 transition-all flex items-center gap-2" aria-label="Capture Answer Sheet">
                        <i class="fa-solid fa-circle text-rose-500 animate-pulse text-xs"></i> Snap Current Page
                    </button>

                    <button type="button" onclick="closeCameraScanner()" class="bg-stone-200 hover:bg-stone-300 text-stone-700 font-bold text-xs py-2.5 px-3.5 rounded-xl transition-all">
                        Cancel
                    </button>
                </div>

                <!-- Adjustment & Confirmation Controls (After Capture) -->
                <div id="adjustmentControls" class="hidden flex flex-wrap items-center justify-between gap-2">
                    <div class="flex items-center gap-1.5">
                        <button type="button" data-testid="rotate-left-btn" onclick="rotateImage(-90)" class="bg-stone-200 hover:bg-stone-300 text-stone-700 text-xs font-bold py-2 px-3 rounded-lg transition-all flex items-center gap-1" title="Rotate Left 90°" aria-label="Rotate Left 90 Degrees">
                            <i class="fa-solid fa-rotate-left"></i> Left
                        </button>
                        <button type="button" data-testid="rotate-right-btn" onclick="rotateImage(90)" class="bg-stone-200 hover:bg-stone-300 text-stone-700 text-xs font-bold py-2 px-3 rounded-lg transition-all flex items-center gap-1" title="Rotate Right 90°" aria-label="Rotate Right 90 Degrees">
                            <i class="fa-solid fa-rotate-right"></i> Right
                        </button>
                        <button type="button" data-testid="crop-btn" onclick="cropImageBoundary()" class="bg-stone-200 hover:bg-stone-300 text-stone-700 text-xs font-bold py-2 px-3 rounded-lg transition-all flex items-center gap-1" title="Crop Boundary" aria-label="Crop Boundary Margins">
                            <i class="fa-solid fa-crop-simple"></i> Crop
                        </button>
                        <button type="button" data-testid="reset-adjustments-btn" onclick="resetImageAdjustments()" class="bg-stone-200 hover:bg-stone-300 text-stone-700 text-xs font-bold py-2 px-3 rounded-lg transition-all flex items-center gap-1" title="Reset Image" aria-label="Reset Image Adjustments">
                            <i class="fa-solid fa-arrow-rotate-left"></i> Reset
                        </button>
                    </div>

                    <div class="flex items-center gap-2 ms-auto flex-wrap sm:flex-nowrap">
                        <button type="button" data-testid="retake-btn" onclick="retakePhoto()" class="bg-stone-200 hover:bg-stone-300 text-stone-700 font-bold text-xs py-2 px-3 rounded-xl transition-all flex items-center gap-1.5" aria-label="Retake Current Page">
                            <i class="fa-solid fa-arrows-rotate"></i> Retake
                        </button>

                        <button type="button" onclick="addAndScanNextPage()" class="bg-orange-600 hover:bg-orange-700 text-white font-extrabold text-xs py-2 px-4 rounded-xl shadow transition-all flex items-center gap-1.5" title="Add this page and scan page 2, 3, etc.">
                            <i class="fa-solid fa-plus"></i> + Add Next Page
                        </button>

                        <button type="button" data-testid="use-photo-btn" onclick="confirmCapturedPhoto(true)" class="bg-emerald-600 hover:bg-emerald-700 text-white font-extrabold text-xs py-2 px-4 rounded-xl shadow transition-all flex items-center gap-1.5" aria-label="Use Photo and Process OCR">
                            <i class="fa-solid fa-check-double"></i> Finish & Attach
                        </button>
                    </div>
                </div>
            </div>

        </div>
    </div>

    <!-- Camera Scanner Interactive JS -->
    <script>
        const ELIGIBLE_STUDENTS_MAP = <?php echo json_encode($eligible_students_map, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
        let mediaStream = null;
        let currentFacingMode = 'environment';
        let rawCapturedCanvas = null;
        let activeCanvas = null;
        let currentRotation = 0;

        let capturedPages = [];
        let selectedFileObjects = [];

        function onExamChanged() {
            const examSelect = document.getElementById('examSelect');
            const studentSelect = document.getElementById('studentSelect');
            const studentNotice = document.getElementById('studentNotice');
            const examId = parseInt(examSelect.value, 10);

            studentSelect.innerHTML = '';
            studentNotice.classList.add('hidden');

            if (!examId) {
                studentSelect.disabled = true;
                studentSelect.innerHTML = '<option value="">-- Choose Exam First --</option>';
                return;
            }

            const students = ELIGIBLE_STUDENTS_MAP[examId] || [];
            if (students.length === 0) {
                studentSelect.disabled = true;
                studentSelect.innerHTML = '<option value="">-- No Eligible Students Found --</option>';
                studentNotice.classList.remove('hidden');
            } else {
                studentSelect.disabled = false;
                studentSelect.innerHTML = '<option value="">-- Choose Student --</option>';
                students.forEach(st => {
                    const opt = document.createElement('option');
                    opt.value = st.id;
                    opt.textContent = `${st.fullname} (${st.email})`;
                    studentSelect.appendChild(opt);
                });
            }
        }

        async function openCameraScanner() {
            const examSelect = document.getElementById('examSelect');
            const studentSelect = document.getElementById('studentSelect');

            if (!examSelect.value) {
                alert("Please select a Stored Exam first before launching the camera scanner.");
                examSelect.focus();
                return;
            }

            if (!studentSelect.value || studentSelect.disabled) {
                alert("Please select an Enrolled Student first before launching the camera scanner.");
                studentSelect.focus();
                return;
            }

            const modal = document.getElementById('cameraModal');
            const errorBanner = document.getElementById('cameraErrorBanner');

            errorBanner.classList.add('hidden');
            modal.classList.remove('hidden');
            document.body.classList.add('overflow-hidden');

            updateModalPageCounter();
            resetCapturedState();

            // If accessing over network HTTP on mobile/cellphone, launch native camera directly or show direct capture banner
            const isMobileDevice = /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent);
            const isUnsecureNetworkContext = !window.isSecureContext && location.hostname !== 'localhost' && location.hostname !== '127.0.0.1';

            if (isMobileDevice || isUnsecureNetworkContext) {
                // Check if navigator.mediaDevices.getUserMedia exists; if blocked by HTTP, provide seamless native phone camera
                if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia || isUnsecureNetworkContext) {
                    showCameraError("Live browser stream requires HTTPS over network IP. Tap below to capture directly with your phone's native camera.");
                    // Auto-trigger native camera capture on mobile for 1-tap experience
                    if (isMobileDevice) {
                        launchNativeCameraCapture();
                    }
                    return;
                }
            }

            if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
                showCameraError("Live Camera API is not supported on this browser. You can take a photo with your phone camera directly below.");
                return;
            }

            startCameraStream();
        }

        function updateModalPageCounter() {
            const currentNum = capturedPages.length + 1;
            const badge = document.getElementById('modalPageBadge');
            if (badge) badge.textContent = `Scanning Page ${currentNum}`;

            const trayBanner = document.getElementById('modalPagesTrayBanner');
            const countText = document.getElementById('modalScannedPagesCountText');
            if (trayBanner && countText) {
                if (capturedPages.length > 0) {
                    trayBanner.classList.remove('hidden');
                    countText.textContent = `${capturedPages.length} Page(s) Scanned`;
                } else {
                    trayBanner.classList.add('hidden');
                }
            }
        }

        function launchNativeCameraCapture() {
            const mobileInput = document.getElementById('mobileDirectCameraInput');
            if (mobileInput) {
                mobileInput.value = '';
                mobileInput.click();
            }
        }

        function onDirectCameraCapture(event) {
            const input = event.target;
            if (!input.files || !input.files[0]) return;

            const file = input.files[0];
            const reader = new FileReader();

            reader.onload = function(e) {
                const img = new Image();
                img.onload = function() {
                    rawCapturedCanvas = document.createElement('canvas');
                    rawCapturedCanvas.width = img.width;
                    rawCapturedCanvas.height = img.height;
                    const ctx = rawCapturedCanvas.getContext('2d');
                    ctx.drawImage(img, 0, 0);

                    activeCanvas = document.createElement('canvas');
                    activeCanvas.width = img.width;
                    activeCanvas.height = img.height;
                    activeCanvas.getContext('2d').drawImage(rawCapturedCanvas, 0, 0);

                    currentRotation = 0;

                    // Hide error banner and show preview in modal for review & enhancements
                    const errorBanner = document.getElementById('cameraErrorBanner');
                    if (errorBanner) errorBanner.classList.add('hidden');
                    
                    updatePreviewDisplay();
                };
                img.src = e.target.result;
            };
            reader.readAsDataURL(file);
        }

        async function startCameraStream() {
            stopCameraStream();

            const video = document.getElementById('cameraVideo');
            const switchBtn = document.getElementById('switchCameraBtn');

            let constraints = {
                video: {
                    facingMode: { ideal: currentFacingMode },
                    width: { ideal: 1920 },
                    height: { ideal: 1080 }
                },
                audio: false
            };

            try {
                mediaStream = await navigator.mediaDevices.getUserMedia(constraints);
            } catch (firstErr) {
                console.warn("First camera attempt failed with constraints, trying simple video stream:", firstErr);
                try {
                    mediaStream = await navigator.mediaDevices.getUserMedia({ video: true, audio: false });
                } catch (err) {
                    console.error("Camera access error:", err);
                    let msg = "Camera initialization error. You may upload an image file instead.";
                    if (err.name === 'NotAllowedError' || err.name === 'PermissionDeniedError') {
                        msg = "Camera permission was denied. You may upload an image instead.";
                    } else if (err.name === 'NotFoundError' || err.name === 'DevicesNotFoundError') {
                        msg = "No camera hardware device found. You may upload an image file instead.";
                    } else if (err.name === 'NotReadableError' || err.name === 'TrackStartError') {
                        msg = "Camera is already in use by another application. Please release the camera or upload an image file instead.";
                    } else if (err.name === 'TypeError' || !navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
                        msg = "Camera API is not supported on this browser. You may upload an image file instead.";
                    }
                    showCameraError(msg);
                    return;
                }
            }

            if (mediaStream) {
                video.srcObject = mediaStream;
                try {
                    await video.play();
                } catch(e) {}

                try {
                    const devices = await navigator.mediaDevices.enumerateDevices();
                    const videoDevices = devices.filter(d => d.kind === 'videoinput');
                    if (videoDevices.length > 1) {
                        switchBtn.classList.remove('hidden');
                    } else {
                        switchBtn.classList.add('hidden');
                    }
                } catch(e) {}
            }
        }

        function showCameraError(msg) {
            const errorBanner = document.getElementById('cameraErrorBanner');
            const errorText = document.getElementById('cameraErrorText');
            errorText.textContent = msg;
            errorBanner.classList.remove('hidden');
        }

        function stopCameraStream() {
            if (mediaStream) {
                mediaStream.getTracks().forEach(t => t.stop());
                mediaStream = null;
            }
            const video = document.getElementById('cameraVideo');
            if (video) video.srcObject = null;
        }

        function switchCamera() {
            currentFacingMode = (currentFacingMode === 'environment') ? 'user' : 'environment';
            startCameraStream();
        }

        function capturePhoto() {
            const video = document.getElementById('cameraVideo');
            if (!video || !video.videoWidth) {
                showCameraError("Camera video feed is not active yet. Please wait a moment and try again.");
                return;
            }

            rawCapturedCanvas = document.createElement('canvas');
            rawCapturedCanvas.width = video.videoWidth;
            rawCapturedCanvas.height = video.videoHeight;

            const ctx = rawCapturedCanvas.getContext('2d');
            ctx.drawImage(video, 0, 0, rawCapturedCanvas.width, rawCapturedCanvas.height);

            activeCanvas = document.createElement('canvas');
            activeCanvas.width = rawCapturedCanvas.width;
            activeCanvas.height = rawCapturedCanvas.height;
            activeCanvas.getContext('2d').drawImage(rawCapturedCanvas, 0, 0);

            currentRotation = 0;
            updatePreviewDisplay();

            video.pause();
        }

        function updatePreviewDisplay() {
            if (!activeCanvas) return;

            const previewImg = document.getElementById('capturedImagePreview');
            const previewContainer = document.getElementById('previewContainer');
            const framingOverlay = document.getElementById('framingOverlay');
            const streamControls = document.getElementById('streamControls');
            const adjustmentControls = document.getElementById('adjustmentControls');
            const metaBar = document.getElementById('imageMetaBar');
            const dimensionsDisplay = document.getElementById('imageDimensionsDisplay');
            const lowResWarning = document.getElementById('lowResWarning');

            previewImg.src = activeCanvas.toDataURL('image/jpeg', 0.9);
            dimensionsDisplay.textContent = `Page Dimensions: ${activeCanvas.width} x ${activeCanvas.height} px`;

            if (activeCanvas.width < 800) {
                lowResWarning.classList.remove('hidden');
            } else {
                lowResWarning.classList.add('hidden');
            }

            framingOverlay.classList.add('hidden');
            previewContainer.classList.remove('hidden');
            streamControls.classList.add('hidden');
            adjustmentControls.classList.remove('hidden');
            metaBar.classList.remove('hidden');
        }

        function rotateImage(degrees) {
            if (!activeCanvas) return;

            currentRotation = (currentRotation + degrees) % 360;

            const tempCanvas = document.createElement('canvas');
            if (Math.abs(degrees) === 90 || Math.abs(degrees) === 270) {
                tempCanvas.width = activeCanvas.height;
                tempCanvas.height = activeCanvas.width;
            } else {
                tempCanvas.width = activeCanvas.width;
                tempCanvas.height = activeCanvas.height;
            }

            const tempCtx = tempCanvas.getContext('2d');
            tempCtx.translate(tempCanvas.width / 2, tempCanvas.height / 2);
            tempCtx.rotate((degrees * Math.PI) / 180);
            tempCtx.drawImage(activeCanvas, -activeCanvas.width / 2, -activeCanvas.height / 2);

            activeCanvas = tempCanvas;
            updatePreviewDisplay();
        }

        function cropImageBoundary() {
            if (!activeCanvas) return;

            const cropX = Math.round(activeCanvas.width * 0.1);
            const cropY = Math.round(activeCanvas.height * 0.1);
            const cropW = Math.round(activeCanvas.width * 0.8);
            const cropH = Math.round(activeCanvas.height * 0.8);

            const tempCanvas = document.createElement('canvas');
            tempCanvas.width = cropW;
            tempCanvas.height = cropH;

            const tempCtx = tempCanvas.getContext('2d');
            tempCtx.drawImage(activeCanvas, cropX, cropY, cropW, cropH, 0, 0, cropW, cropH);

            activeCanvas = tempCanvas;
            updatePreviewDisplay();
        }

        function resetImageAdjustments() {
            if (!rawCapturedCanvas) return;
            activeCanvas = document.createElement('canvas');
            activeCanvas.width = rawCapturedCanvas.width;
            activeCanvas.height = rawCapturedCanvas.height;
            activeCanvas.getContext('2d').drawImage(rawCapturedCanvas, 0, 0);
            currentRotation = 0;
            updatePreviewDisplay();
        }

        function retakePhoto() {
            resetCapturedState();
            const video = document.getElementById('cameraVideo');
            if (video && mediaStream) video.play();
            else {
                const isMobile = /Android|iPhone|iPad/i.test(navigator.userAgent);
                if (isMobile) launchNativeCameraCapture();
            }
        }

        function resetCapturedState() {
            rawCapturedCanvas = null;
            activeCanvas = null;
            currentRotation = 0;

            document.getElementById('framingOverlay').classList.remove('hidden');
            document.getElementById('previewContainer').classList.add('hidden');
            document.getElementById('streamControls').classList.remove('hidden');
            document.getElementById('adjustmentControls').classList.add('hidden');
            document.getElementById('imageMetaBar').classList.add('hidden');
            document.getElementById('cameraErrorBanner').classList.add('hidden');
        }

        function addAndScanNextPage() {
            if (!activeCanvas) return;

            activeCanvas.toBlob((blob) => {
                if (!blob) {
                    alert("Failed to capture page.");
                    return;
                }

                const pageNum = capturedPages.length + 1;
                const filename = `page_${pageNum}_scan_${Date.now()}.jpg`;

                capturedPages.push({
                    id: Date.now(),
                    blob: blob,
                    filename: filename,
                    size: blob.size,
                    dataUrl: activeCanvas.toDataURL('image/jpeg', 0.85)
                });

                selectedFileObjects = [];
                updateModalPageCounter();
                resetCapturedState();

                // Prepare next page capture
                const isMobile = /Android|iPhone|iPad/i.test(navigator.userAgent);
                const isUnsecure = !window.isSecureContext && location.hostname !== 'localhost' && location.hostname !== '127.0.0.1';
                if (isMobile && isUnsecure) {
                    launchNativeCameraCapture();
                } else if (mediaStream) {
                    const video = document.getElementById('cameraVideo');
                    if (video) video.play();
                }
            }, 'image/jpeg', 0.88);
        }

        function confirmCapturedPhoto(isFinish = true) {
            if (activeCanvas) {
                activeCanvas.toBlob((blob) => {
                    if (blob) {
                        const pageNum = capturedPages.length + 1;
                        const filename = `page_${pageNum}_scan_${Date.now()}.jpg`;

                        capturedPages.push({
                            id: Date.now(),
                            blob: blob,
                            filename: filename,
                            size: blob.size,
                            dataUrl: activeCanvas.toDataURL('image/jpeg', 0.85)
                        });
                    }

                    finalizeScannerAttachment();
                }, 'image/jpeg', 0.88);
            } else {
                finalizeScannerAttachment();
            }
        }

        function finalizeScannerAttachment() {
            if (capturedPages.length === 0) {
                alert("Please snap at least one page before attaching.");
                return;
            }

            selectedFileObjects = [];
            closeCameraScanner();
            renderPagesTray();
        }

        function closeCameraScanner() {
            stopCameraStream();
            const modal = document.getElementById('cameraModal');
            modal.classList.add('hidden');
            document.body.classList.remove('overflow-hidden');
        }

        function onFileSelected(event) {
            const input = event.target;
            if (input.files && input.files.length > 0) {
                capturedPages = [];
                selectedFileObjects = Array.from(input.files);
                renderPagesTray();
            }
        }

        function removeCapturedPage(id) {
            capturedPages = capturedPages.filter(p => p.id !== id);
            renderPagesTray();
        }

        function removeSelectedFile(index) {
            selectedFileObjects.splice(index, 1);
            renderPagesTray();
        }

        function renderPagesTray() {
            const nameDisplay = document.getElementById('fileNameDisplay');
            const pageCountDisplay = document.getElementById('pageCountDisplay');
            const fileIcon = document.getElementById('fileIcon');
            const tray = document.getElementById('pagesListTray');
            const fileInput = document.getElementById('examFileInput');

            tray.innerHTML = '';

            if (capturedPages.length > 0) {
                nameDisplay.textContent = `Scanned Test Paper (${capturedPages.length} Page(s))`;
                pageCountDisplay.textContent = `${capturedPages.length} Page(s)`;
                fileIcon.className = "fa-solid fa-camera text-orange-600 text-base flex-shrink-0";
                tray.classList.remove('hidden');

                capturedPages.forEach((p, idx) => {
                    const badge = document.createElement('div');
                    badge.className = "flex items-center gap-1.5 bg-white border border-stone-200 shadow-2xs rounded-lg px-2.5 py-1 text-xs text-stone-800 font-semibold";
                    badge.innerHTML = `
                        <i class="fa-solid fa-file-image text-orange-500 text-xs"></i>
                        <span>Page ${idx + 1} (${(p.size / (1024 * 1024)).toFixed(2)} MB)</span>
                        <button type="button" onclick="removeCapturedPage(${p.id})" class="text-stone-400 hover:text-rose-600 font-bold ml-1 transition-colors" title="Remove Page">
                            <i class="fa-solid fa-xmark text-xs"></i>
                        </button>
                    `;
                    tray.appendChild(badge);
                });

                const addMoreBtn = document.createElement('button');
                addMoreBtn.type = "button";
                addMoreBtn.onclick = openCameraScanner;
                addMoreBtn.className = "flex items-center gap-1 bg-orange-50 hover:bg-orange-100 border border-orange-200 text-orange-700 rounded-lg px-2.5 py-1 text-xs font-bold transition-all";
                addMoreBtn.innerHTML = `<i class="fa-solid fa-plus text-xs"></i> + Add More Pages`;
                tray.appendChild(addMoreBtn);

            } else if (selectedFileObjects.length > 0) {
                nameDisplay.textContent = (selectedFileObjects.length === 1) 
                    ? selectedFileObjects[0].name 
                    : `Uploaded Files (${selectedFileObjects.length} Files)`;
                pageCountDisplay.textContent = `${selectedFileObjects.length} Page(s)`;
                fileIcon.className = "fa-solid fa-file-pdf text-orange-600 text-base flex-shrink-0";
                tray.classList.remove('hidden');

                selectedFileObjects.forEach((f, idx) => {
                    const badge = document.createElement('div');
                    badge.className = "flex items-center gap-1.5 bg-white border border-stone-200 shadow-2xs rounded-lg px-2.5 py-1 text-xs text-stone-800 font-semibold";
                    badge.innerHTML = `
                        <i class="fa-solid fa-file-lines text-stone-500 text-xs"></i>
                        <span class="truncate max-w-[150px]">${f.name}</span>
                        <span class="text-[10px] text-stone-400">(${(f.size / (1024 * 1024)).toFixed(2)}MB)</span>
                        <button type="button" onclick="removeSelectedFile(${idx})" class="text-stone-400 hover:text-rose-600 font-bold ml-1 transition-colors" title="Remove File">
                            <i class="fa-solid fa-xmark text-xs"></i>
                        </button>
                    `;
                    tray.appendChild(badge);
                });
            } else {
                nameDisplay.textContent = "No answer sheet pages selected yet";
                pageCountDisplay.textContent = "0 Pages";
                fileIcon.className = "fa-solid fa-file-lines text-stone-400 text-base flex-shrink-0";
                tray.classList.add('hidden');
                if (fileInput) fileInput.value = '';
            }
        }

        document.getElementById('ocrUploadForm').addEventListener('submit', async function(e) {
            const examSelect = document.getElementById('examSelect');
            const studentSelect = document.getElementById('studentSelect');

            if (!examSelect.value || !studentSelect.value) {
                alert("Please select a valid Exam and Enrolled Student.");
                e.preventDefault();
                return;
            }

            if (capturedPages.length === 0 && selectedFileObjects.length === 0) {
                alert("Please scan or upload at least one test paper page first.");
                e.preventDefault();
                return;
            }

            // If we have captured camera pages or multiple selected files, submit via AJAX FormData
            e.preventDefault();

            const form = this;
            const submitBtn = form.querySelector('button[type="submit"]');
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin mr-2"></i> Submitting & Processing Multi-Page OCR...';

            const formData = new FormData(form);
            formData.delete('exam_file');
            formData.delete('exam_files[]');

            if (capturedPages.length > 0) {
                capturedPages.forEach((p) => {
                    formData.append('exam_files[]', p.blob, p.filename);
                });
            } else if (selectedFileObjects.length > 0) {
                selectedFileObjects.forEach((f) => {
                    formData.append('exam_files[]', f, f.name);
                });
            }

            formData.append('process_ocr_grading', '1');

            try {
                const response = await fetch('upload_check.php', {
                    method: 'POST',
                    body: formData,
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                });

                const html = await response.text();
                document.open();
                document.write(html);
                document.close();
            } catch (err) {
                console.error("Multi-page upload submission error:", err);
                alert("Network or upload error occurred. Please try again.");
                submitBtn.disabled = false;
                submitBtn.innerHTML = '<i class="fa-solid fa-microchip mr-2"></i> Process & Grade Server-Side';
            }
        });
    </script>
</body>
</html>