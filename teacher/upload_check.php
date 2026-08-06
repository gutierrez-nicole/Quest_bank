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
    } elseif (!isset($_FILES['exam_file']) || $_FILES['exam_file']['error'] !== UPLOAD_ERR_OK) {
        $error_msg = "Please attach a valid answer sheet file or camera capture (JPG, PNG, PDF).";
    } else {
        $file_name = $_FILES['exam_file']['name'];
        $file_tmp = $_FILES['exam_file']['tmp_name'];

        $validation = FileValidationService::validateFile($file_tmp, $file_name, 10485760);
        if (!$validation['success']) {
            $error_msg = "Security Validation Failed: " . $validation['error'];
        } else {
            $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));

            if (in_array($file_ext, ['jpg', 'jpeg', 'png'], true)) {
                $imgInfo = @getimagesize($file_tmp);
                if (!$imgInfo || $imgInfo[0] < 10 || $imgInfo[1] < 10) {
                    $error_msg = "Security Validation Failed: Invalid or corrupted image file format.";
                }
            }

            if (empty($error_msg)) {
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
                        <label class="block text-xs font-bold text-stone-700 mb-1">Answer Sheet Source</label>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <button type="button" id="openCameraButton" data-testid="scan-using-camera-btn" onclick="openCameraScanner()" class="w-full bg-orange-600 hover:bg-orange-700 text-white font-bold text-xs py-3 px-4 rounded-xl shadow-sm transition-all flex items-center justify-center gap-2">
                                <i class="fa-solid fa-camera text-sm"></i> Scan Using Camera
                            </button>

                            <label for="examFileInput" class="w-full bg-stone-100 hover:bg-stone-200 border border-stone-300 text-stone-700 font-bold text-xs py-3 px-4 rounded-xl cursor-pointer transition-all flex items-center justify-center gap-2 text-center">
                                <i class="fa-solid fa-upload text-sm"></i> Upload Image or PDF
                            </label>
                        </div>

                        <!-- Selected File / Camera Capture Status Box -->
                        <div class="p-3 bg-stone-50 border border-stone-200 rounded-xl flex items-center justify-between gap-2" id="fileStatusContainer">
                            <div class="flex items-center gap-2 overflow-hidden">
                                <i class="fa-solid fa-file-image text-orange-500 text-base flex-shrink-0" id="fileIcon"></i>
                                <span class="text-xs font-semibold text-stone-700 truncate" id="fileNameDisplay">No answer sheet selected yet</span>
                            </div>
                            <input type="file" name="exam_file" id="examFileInput" required accept=".jpg,.jpeg,.png,.pdf" onchange="onFileSelected(event)" class="hidden">
                            <span class="text-[10px] font-bold text-stone-400 uppercase flex-shrink-0" id="fileSizeDisplay"></span>
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

    <!-- Camera Answer Sheet Scanner Modal -->
    <div id="cameraModal" class="fixed inset-0 z-50 bg-black/80 backdrop-blur-sm hidden flex flex-col items-center justify-center p-3 sm:p-6" data-testid="camera-modal">
        <div class="bg-white border border-stone-200 rounded-2xl shadow-2xl w-full max-w-2xl flex flex-col max-h-[95vh] overflow-hidden">
            
            <!-- Modal Header -->
            <div class="bg-stone-900 text-white px-5 py-3.5 flex items-center justify-between flex-shrink-0">
                <div class="flex items-center gap-2.5">
                    <div class="w-8 h-8 rounded-lg bg-orange-500/20 text-orange-400 flex items-center justify-center">
                        <i class="fa-solid fa-camera text-sm"></i>
                    </div>
                    <div>
                        <h4 class="text-sm font-extrabold text-stone-100 leading-tight">Camera Answer Sheet Scanner</h4>
                        <p class="text-[10px] text-stone-400">Position student answer sheet inside the framing box</p>
                    </div>
                </div>
                <button type="button" data-testid="close-camera-btn" onclick="closeCameraScanner()" class="w-8 h-8 text-stone-400 hover:text-white rounded-lg flex items-center justify-center hover:bg-stone-800 transition-all" aria-label="Close Camera Scanner">
                    <i class="fa-solid fa-xmark text-lg"></i>
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
                        Position the complete answer sheet inside the frame. Avoid shadows and keep camera steady.
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

                <!-- Error & Permission Banner -->
                <div id="cameraErrorBanner" data-testid="camera-error-message" class="hidden absolute inset-4 bg-rose-900/95 border border-rose-500 text-rose-100 p-5 rounded-2xl flex flex-col items-center justify-center text-center space-y-3">
                    <i class="fa-solid fa-triangle-exclamation text-3xl text-rose-300"></i>
                    <p id="cameraErrorText" class="text-xs font-bold leading-relaxed max-w-md">Camera permission was denied. You may upload an image instead.</p>
                    <button type="button" onclick="closeCameraScanner()" class="bg-white text-rose-900 font-bold text-xs py-2 px-4 rounded-xl hover:bg-stone-100 transition-all">
                        Close Scanner
                    </button>
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
                        <i class="fa-solid fa-circle text-rose-500 animate-pulse text-xs"></i> Capture Answer Sheet
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

                    <div class="flex items-center gap-2 ms-auto">
                        <button type="button" data-testid="retake-btn" onclick="retakePhoto()" class="bg-stone-200 hover:bg-stone-300 text-stone-700 font-bold text-xs py-2 px-4 rounded-xl transition-all flex items-center gap-1.5" aria-label="Retake Photo">
                            <i class="fa-solid fa-arrows-rotate"></i> Retake
                        </button>

                        <button type="button" data-testid="use-photo-btn" onclick="confirmCapturedPhoto()" class="bg-emerald-600 hover:bg-emerald-700 text-white font-extrabold text-xs py-2 px-5 rounded-xl shadow transition-all flex items-center gap-1.5" aria-label="Use Photo and Process OCR">
                            <i class="fa-solid fa-check"></i> Use Photo & Attach
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

        let capturedCameraBlob = null;
        let capturedCameraFilename = null;

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

            resetCapturedState();

            if (!window.isSecureContext && location.hostname !== 'localhost' && location.hostname !== '127.0.0.1') {
                showCameraError("Camera access requires HTTPS. Use the image-upload option or open the secured site.");
                return;
            }

            if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
                showCameraError("Camera API is not supported on this browser. You may upload an image file instead.");
                return;
            }

            startCameraStream();
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
            document.getElementById('framingOverlay').classList.add('hidden');
            document.getElementById('previewContainer').classList.remove('hidden');
            document.getElementById('streamControls').classList.add('hidden');
            document.getElementById('adjustmentControls').classList.remove('hidden');
            document.getElementById('imageMetaBar').classList.remove('hidden');
        }

        function updatePreviewDisplay() {
            if (!activeCanvas) return;

            const imgPreview = document.getElementById('capturedImagePreview');
            const dimDisplay = document.getElementById('imageDimensionsDisplay');
            const lowResWarning = document.getElementById('lowResWarning');

            imgPreview.src = activeCanvas.toDataURL('image/jpeg', 0.90);
            dimDisplay.textContent = `Dimensions: ${activeCanvas.width} x ${activeCanvas.height} px`;

            if (activeCanvas.width < 800 || activeCanvas.height < 600) {
                lowResWarning.classList.remove('hidden');
            } else {
                lowResWarning.classList.add('hidden');
            }
        }

        function rotateImage(deg) {
            if (!activeCanvas) return;
            currentRotation = (currentRotation + deg) % 360;

            const tempCanvas = document.createElement('canvas');
            const tempCtx = tempCanvas.getContext('2d');

            if (Math.abs(deg) === 90 || Math.abs(deg) === 270) {
                tempCanvas.width = activeCanvas.height;
                tempCanvas.height = activeCanvas.width;
            } else {
                tempCanvas.width = activeCanvas.width;
                tempCanvas.height = activeCanvas.height;
            }

            tempCtx.translate(tempCanvas.width / 2, tempCanvas.height / 2);
            tempCtx.rotate((deg * Math.PI) / 180);
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
            if (video) video.play();
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

        function confirmCapturedPhoto() {
            if (!activeCanvas) return;

            activeCanvas.toBlob((blob) => {
                if (!blob) {
                    alert("Failed to generate JPEG image from camera capture.");
                    return;
                }

                capturedCameraBlob = blob;
                capturedCameraFilename = `camera_scan_${Date.now()}.jpg`;

                const fileInput = document.getElementById('examFileInput');
                if (fileInput) fileInput.value = '';

                if (fileInput && window.DataTransfer) {
                    try {
                        const dt = new DataTransfer();
                        const file = new File([blob], capturedCameraFilename, { type: 'image/jpeg' });
                        dt.items.add(file);
                        fileInput.files = dt.files;
                    } catch(e) {}
                }

                updateFileStatusDisplay(capturedCameraFilename, blob.size, true);
                closeCameraScanner();
            }, 'image/jpeg', 0.88);
        }

        function closeCameraScanner() {
            stopCameraStream();
            const modal = document.getElementById('cameraModal');
            modal.classList.add('hidden');
            document.body.classList.remove('overflow-hidden');
        }

        function onFileSelected(event) {
            const input = event.target;
            if (input.files && input.files[0]) {
                capturedCameraBlob = null;
                capturedCameraFilename = null;
                updateFileStatusDisplay(input.files[0].name, input.files[0].size, false);
            } else if (!capturedCameraBlob) {
                updateFileStatusDisplay("No answer sheet selected yet", 0, false);
            }
        }

        function updateFileStatusDisplay(name, sizeBytes, isCamera) {
            const nameDisplay = document.getElementById('fileNameDisplay');
            const sizeDisplay = document.getElementById('fileSizeDisplay');
            const fileIcon = document.getElementById('fileIcon');

            nameDisplay.textContent = name;
            if (sizeBytes > 0) {
                sizeDisplay.textContent = `${(sizeBytes / (1024 * 1024)).toFixed(2)} MB`;
            } else {
                sizeDisplay.textContent = '';
            }

            if (isCamera) {
                fileIcon.className = "fa-solid fa-camera text-orange-600 text-base flex-shrink-0";
            } else {
                fileIcon.className = "fa-solid fa-file-image text-orange-500 text-base flex-shrink-0";
            }
        }

        document.getElementById('ocrUploadForm').addEventListener('submit', async function(e) {
            if (!capturedCameraBlob) {
                return;
            }

            e.preventDefault();

            const form = this;
            const examSelect = document.getElementById('examSelect');
            const studentSelect = document.getElementById('studentSelect');

            if (!examSelect.value || !studentSelect.value) {
                alert("Please select a valid Exam and Enrolled Student.");
                return;
            }

            const submitBtn = form.querySelector('button[type="submit"]');
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin mr-2"></i> Submitting & Processing OCR...';

            const formData = new FormData(form);
            formData.delete('exam_file');
            formData.append('exam_file', capturedCameraBlob, capturedCameraFilename);
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
                console.error("Camera upload submission error:", err);
                alert("Network or camera upload error occurred. Please try again.");
                submitBtn.disabled = false;
                submitBtn.innerHTML = '<i class="fa-solid fa-microchip mr-2"></i> Process & Grade Server-Side';
            }
        });
    </script>
</body>
</html>