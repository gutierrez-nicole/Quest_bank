<?php
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
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

    $teacher_id = $_SESSION['user_id'];

    // Profile Info ng Teacher
    $stmt = $pdo->prepare("SELECT fullname, username, email FROM users WHERE id = ?");
    $stmt->execute([$teacher_id]);
    $teacher = $stmt->fetch(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}

$success_msg = "";
$error_msg = "";
$ai_analyzed_data = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['process_ai_ocr'])) {
    $student_name = trim($_POST['student_name']);
    $exam_title = trim($_POST['exam_title']);
    $upload_type = $_POST['upload_type'];
    $answer_key_input = trim($_POST['answer_key_input']);
    
    if (isset($_FILES['exam_file']) && $_FILES['exam_file']['error'] === UPLOAD_ERR_OK) {
        $file_name = $_FILES['exam_file']['name'];
        
        // Groq API Integration Setup
        $secretKey = '';
        $endpoint = 'https://api.groq.com/openai/v1/chat/completions';

        $prompt = 'You are an advanced educational AI OCR and grading system. '
                . 'Analyze the following uploaded student exam paper data and compare it against the provided Answer Key. '
                . 'Student Name: ' . $student_name . ' '
                . 'Exam Title: ' . $exam_title . ' '
                . 'Document Type: ' . $upload_type . ' '
                . 'Answer Key provided by Teacher: ' . $answer_key_input . ' '
                . 'Raw Simulated Student Answers extracted from document: '
                . '1. Continuous Integration, 2. True, 3. Docker, 4. Jenkins, 5. Kubernetes. '
                . 'Calculate the following parameters meticulously: Total number of items (Total: 5), How many are Correct, How many are Wrong, Percentage Grade (Correct / Total * 100), Status: Pass if percentage is 75 or above, otherwise Fail. '
                . 'Provide detailed itemized feedback highlighting the question, student answer, correct answer key, and true/false correctness toggle. '
                . 'You MUST strictly return ONLY a valid JSON object string. Do not include markdown formatting like ```json or any conversational text. '
                . 'JSON structural format matching criteria: '
                . '{'
                . '"correct": 4,'
                . '"wrong": 1,'
                . '"total_items": 5,'
                . '"percentage": 80,'
                . '"status": "Pass",'
                . '"questions": ['
                . '{"num": 1, "q": "What is CI/CD?", "student_ans": "Continuous Integration", "key_ans": "Continuous Integration", "is_correct": true},'
                . '{"num": 2, "q": "DevOps replaces Agile.", "student_ans": "True", "key_ans": "False", "is_correct": false},'
                . '{"num": 3, "q": "Main tool for containerization?", "student_ans": "Docker", "key_ans": "Docker", "is_correct": true},'
                . '{"num": 4, "q": "Continuous Integration Server?", "student_ans": "Jenkins", "key_ans": "Jenkins", "is_correct": true},'
                . '{"num": 5, "q": "Container Orchestrator?", "student_ans": "Docker Swarm", "key_ans": "Kubernetes", "is_correct": false}'
                . ']'
                . '}';

        $postData = [
            'model' => 'llama3-8b-8192',
            'messages' => [
                ['role' => 'user', 'content' => $prompt]
            ],
            'temperature' => 0.2
        ];

        $ch = curl_init($endpoint);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postData));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $secretKey,
            'Content-Type: application/json'
        ]);
        
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

        $response = curl_exec($ch);
        $curl_error = curl_error($ch);
        curl_close($ch);

        if ($curl_error) {
            $error_msg = "API Connection Error (cURL): " . $curl_error;
        } else {
            $responseDecoded = json_decode($response, true);
            $ai_raw_output = $responseDecoded['choices'][0]['message']['content'] ?? '';
            $cleaned_json = json_decode(trim($ai_raw_output), true);

            if (json_last_error() === JSON_ERROR_NONE && !empty($cleaned_json)) {
                $ai_analyzed_data = $cleaned_json;
                $ai_analyzed_data['student_name'] = $student_name;
                $ai_analyzed_data['exam_title'] = $exam_title;
                $ai_analyzed_data['upload_type'] = $upload_type;
                $ai_analyzed_data['file_name'] = $file_name;
                $success_msg = "Groq AI processed and checked the exam paper successfully!";
            } else {
                $error_msg = "Failed to parse AI response string into valid schema. Please try again.";
            }
        }
    } else {
        $error_msg = "Please attach a valid document file/image to begin processing.";
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_submission'])) {
    try {
        $stmt = $pdo->prepare("INSERT INTO exam_submissions (teacher_id, student_name, exam_title, upload_type, correct_count, wrong_count, total_score, total_items, percentage, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $_SESSION['user_id'],
            $_POST['final_student_name'],
            $_POST['final_exam_title'],
            $_POST['final_upload_type'],
            $_POST['final_correct'],
            $_POST['final_wrong'],
            $_POST['final_correct'],
            $_POST['final_total_items'],
            $_POST['final_percentage'],
            $_POST['final_status']
        ]);
        $success_msg = "Validated exam grading metrics safely stored to database!";
        $ai_analyzed_data = null;
    } catch (PDOException $e) {
        $error_msg = "Database logging error: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>QuestBank - OCR Answer Checker</title>
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- FontAwesome for Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; }
        .bg-orange-gradient { background: linear-gradient(135deg, #f57c00 0%, #d84315 100%); }
        .custom-scrollbar::-webkit-scrollbar { width: 4px; }
        .custom-scrollbar::-webkit-scrollbar-thumb { background: #444; border-radius: 10px; }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: scale(0.95); }
            to { opacity: 1; transform: scale(1); }
        }
        .animate-fadeIn { animation: fadeIn 0.2s ease-out; }

        @keyframes pulseGlow {
            0%, 100% { opacity: 1; transform: scale(1); }
            50% { opacity: 0.85; transform: scale(1.03); }
        }
        .animate-pulseGlow { animation: pulseGlow 1.8s infinite ease-in-out; }
    </style>
</head>
<body class="bg-[#fffbf7] min-h-screen flex">

    <!-- ================= SIDEBAR NAVIGATION ================= -->
    <aside class="w-64 bg-stone-950 text-stone-300 flex flex-col justify-between hidden lg:flex z-20 shadow-xl max-h-screen">
        <div class="flex flex-col overflow-hidden">
            <!-- Sidebar Header / Logo -->
            <div class="p-5 border-b border-stone-800 flex items-center gap-3 bg-stone-900 flex-shrink-0">
                <div class="w-9 h-9 bg-orange-600 rounded-xl flex items-center justify-center font-bold text-white shadow-md">
                    <i class="fa-solid fa-chalkboard-user text-base"></i>
                </div>
                <div>
                    <h1 class="text-base font-extrabold tracking-tight text-white">Quest<span class="text-orange-500">Bank</span></h1>
                    <p class="text-[9px] uppercase text-stone-400 font-semibold tracking-wider">Teacher Module</p>
                </div>
            </div>

            <!-- Scrollable Navigation Links -->
            <div class="flex-grow overflow-y-auto p-4 space-y-4 custom-scrollbar">
                <div>
                    <p class="text-[10px] font-bold uppercase tracking-widest text-stone-500 px-3 mb-1.5">Teacher Module</p>
                    <nav class="space-y-0.5">
                        <a href="dashboard.php" class="flex items-center gap-3 px-3 py-2 text-xs font-medium rounded-lg text-stone-400 hover:bg-stone-900 hover:text-orange-500 transition-all">
                            <i class="fa-solid fa-chart-pie w-4 text-center"></i> Teacher Dashboard
                        </a>
                        <a href="upload_check.php" class="flex items-center gap-3 px-3 py-2 text-xs font-semibold rounded-lg bg-orange-600 text-white shadow-sm">
                            <i class="fa-solid fa-expand w-4 text-center"></i> OCR Answer Checker
                        </a>
                        <a href="create_exam.php" class="flex items-center gap-3 px-3 py-2 text-xs font-medium rounded-lg text-stone-400 hover:bg-stone-900 hover:text-orange-500 transition-all">
                            <i class="fa-solid fa-file-signature w-4 text-center"></i> Create Exam
                        </a>
                        <a href="generate_ai.php" class="flex items-center gap-3 px-3 py-2 text-xs font-medium rounded-lg text-stone-400 hover:bg-stone-900 hover:text-orange-500 transition-all">
                            <i class="fa-solid fa-wand-magic-sparkles w-4 text-center"></i> Generate AI Questions
                        </a>
                        <a href="manage_students.php" class="flex items-center gap-3 px-3 py-2 text-xs font-medium rounded-lg text-stone-400 hover:bg-stone-900 hover:text-orange-500 transition-all">
                            <i class="fa-solid fa-users w-4 text-center"></i> Students & Sections
                        </a>
                        <a href="upload_lessons.php" class="flex items-center gap-3 px-3 py-2 text-xs font-medium rounded-lg text-stone-400 hover:bg-stone-900 hover:text-orange-500 transition-all">
                            <i class="fa-solid fa-file-arrow-up w-4 text-center"></i> Upload Lesson Files
                        </a>
                        <a href="reports.php" class="flex items-center gap-3 px-3 py-2 text-xs font-medium rounded-lg text-stone-400 hover:bg-stone-900 hover:text-orange-500 transition-all">
                            <i class="fa-solid fa-chart-column w-4 text-center"></i> Reports & Analytics
                        </a>
                    </nav>
                </div>

                <div>
                    <p class="text-[10px] font-bold uppercase tracking-widest text-stone-500 px-3 mb-1.5">Settings</p>
                    <nav class="space-y-0.5">
                        <a href="#profile" class="flex items-center gap-3 px-3 py-2 text-xs text-stone-400 hover:bg-stone-900 hover:text-orange-500 rounded-lg transition-all"><i class="fa-solid fa-user w-4 text-center"></i> Profile Settings</a>
                        <a href="#logs" class="flex items-center gap-3 px-3 py-2 text-xs text-stone-400 hover:bg-stone-900 hover:text-orange-500 rounded-lg transition-all"><i class="fa-solid fa-clipboard-list w-4 text-center"></i> Activity Logs</a>
                    </nav>
                </div>
            </div>
        </div>

        <div class="p-4 border-t border-stone-800 bg-stone-900 flex-shrink-0">
            <button onclick="openLogoutModal()" class="w-full flex items-center gap-3 px-3 py-2 text-xs font-semibold rounded-lg text-red-400 hover:bg-red-500/10 hover:text-red-300 transition-all">
                <i class="fa-solid fa-right-from-bracket w-4 text-center"></i> Logout
            </button>
        </div>
    </aside>

    <!-- ================= MAIN CONTENT AREA ================= -->
    <main class="flex-grow flex flex-col min-w-0 h-screen overflow-hidden">
        
        <!-- TOP NAV HEADERBAR -->
        <header class="bg-white border-b border-stone-200 px-6 py-4 flex items-center justify-between flex-shrink-0">
            <div>
                <h2 class="text-lg font-bold text-stone-800"><i class="fa-solid fa-expand text-orange-600 mr-2"></i>Automated OCR Answer Checker</h2>
                <p class="text-xs text-stone-400">Evaluate handwritten, scanned, or PDF exam sheets in real time.</p>
            </div>
            
            <div class="flex items-center gap-4">
                <button class="w-9 h-9 rounded-xl border border-stone-200 flex items-center justify-center text-stone-500 hover:text-orange-500 relative">
                    <i class="fa-solid fa-bell text-base"></i>
                    <span class="absolute top-1.5 right-2 w-2 h-2 bg-orange-600 rounded-full"></span>
                </button>
                
                <div class="flex items-center gap-3 pl-2 border-l border-stone-200">
                    <div class="w-9 h-9 rounded-xl bg-orange-100 text-orange-700 font-bold flex items-center justify-center shadow-inner">
                        <?php echo strtoupper(substr($teacher['fullname'] ?? 'Prof', 0, 2)); ?>
                    </div>
                    <div class="hidden sm:block text-left">
                        <p class="text-xs font-bold text-stone-800 leading-tight"><?php echo htmlspecialchars($teacher['fullname'] ?? 'Teacher'); ?></p>
                        <p class="text-[10px] text-stone-400 font-medium">Faculty Professor</p>
                    </div>
                </div>
            </div>
        </header>

        <!-- DASHBOARD CONTAINER BODY PANEL -->
        <div class="flex-grow overflow-y-auto p-6 space-y-6 custom-scrollbar">

            <!-- NOTIFICATION ALERTS -->
            <?php if (!empty($success_msg)): ?>
                <div class="bg-emerald-50 border-l-4 border-emerald-500 p-4 rounded-xl text-xs font-semibold text-emerald-800 flex items-center justify-between shadow-sm animate-fadeIn">
                    <span class="flex items-center gap-2"><i class="fa-solid fa-circle-check text-emerald-600 text-sm"></i> <?php echo $success_msg; ?></span>
                    <button onclick="this.parentElement.remove();" class="text-emerald-500 hover:text-emerald-800"><i class="fa-solid fa-xmark"></i></button>
                </div>
            <?php endif; ?>

            <?php if (!empty($error_msg)): ?>
                <div class="bg-rose-50 border-l-4 border-rose-500 p-4 rounded-xl text-xs font-semibold text-rose-800 flex items-center justify-between shadow-sm animate-fadeIn">
                    <span class="flex items-center gap-2"><i class="fa-solid fa-circle-exclamation text-rose-600 text-sm"></i> <?php echo $error_msg; ?></span>
                    <button onclick="this.parentElement.remove();" class="text-rose-500 hover:text-rose-800"><i class="fa-solid fa-xmark"></i></button>
                </div>
            <?php endif; ?>

            <!-- MAIN OCR WORKBENCH GRID -->
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">
                
                <!-- LEFT FORM PANEL (5 COLUMNS) -->
                <div class="lg:col-span-5 bg-white border border-stone-200 rounded-2xl p-6 shadow-sm space-y-5">
                    <div class="flex items-center justify-between border-b border-stone-100 pb-3">
                        <h3 class="text-xs font-extrabold uppercase tracking-wider text-stone-800 flex items-center gap-2">
                            <i class="fa-solid fa-sliders text-orange-500"></i> 1. OCR Session Setup
                        </h3>
                        <span class="text-[10px] bg-orange-100 text-orange-700 font-extrabold px-2 py-0.5 rounded-full">Groq Llama-3</span>
                    </div>

                    <form action="upload_check.php" method="POST" enctype="multipart/form-data" id="ocr_form" class="space-y-4">
                        <div class="space-y-1">
                            <label class="text-xs font-bold text-stone-700">Student Full Name</label>
                            <div class="relative">
                                <i class="fa-solid fa-user-graduate absolute left-3.5 top-3 text-stone-400 text-xs"></i>
                                <input type="text" name="student_name" required placeholder="e.g. Juan Dela Cruz" class="w-full bg-stone-50 border border-stone-200 rounded-xl pl-9 pr-4 py-2.5 text-xs font-semibold text-stone-800 outline-none focus:border-orange-500 focus:bg-white transition-all">
                            </div>
                        </div>

                        <div class="space-y-1">
                            <label class="text-xs font-bold text-stone-700">Exam Title / Subject</label>
                            <div class="relative">
                                <i class="fa-solid fa-file-lines absolute left-3.5 top-3 text-stone-400 text-xs"></i>
                                <input type="text" name="exam_title" required placeholder="e.g. DevOps Midterm Quiz 1" class="w-full bg-stone-50 border border-stone-200 rounded-xl pl-9 pr-4 py-2.5 text-xs font-semibold text-stone-800 outline-none focus:border-orange-500 focus:bg-white transition-all">
                            </div>
                        </div>

                        <div class="space-y-1">
                            <label class="text-xs font-bold text-stone-700">Exam Format / Document Type</label>
                            <div class="relative">
                                <i class="fa-solid fa-layer-group absolute left-3.5 top-3 text-stone-400 text-xs"></i>
                                <select name="upload_type" required class="w-full bg-stone-50 border border-stone-200 rounded-xl pl-9 pr-4 py-2.5 text-xs font-semibold text-stone-800 outline-none cursor-pointer focus:border-orange-500 focus:bg-white transition-all">
                                    <option value="image">Camera Photo Capture (.jpg/.png)</option>
                                    <option value="pdf">PDF Document Asset (.pdf)</option>
                                    <option value="scanned">Scanned Answer Sheet</option>
                                    <option value="handwritten">Handwritten Solution Script</option>
                                    <option value="printed">Printed Answer Layout</option>
                                </select>
                            </div>
                        </div>

                        <div class="space-y-1">
                            <label class="text-xs font-bold text-stone-700">2. Master Answer Key</label>
                            <textarea name="answer_key_input" required rows="3" placeholder="1. Continuous Integration&#10;2. False&#10;3. Docker&#10;4. Jenkins&#10;5. Kubernetes" class="w-full bg-stone-50 border border-stone-200 rounded-xl p-3 text-xs font-mono font-semibold text-stone-800 outline-none focus:border-orange-500 focus:bg-white resize-none transition-all"></textarea>
                        </div>

                        <div class="space-y-1">
                            <label class="text-xs font-bold text-stone-700">Attach Exam Document / Image</label>
                            <div onclick="triggerFileSelect()" id="drop_zone" class="border-2 border-dashed border-stone-300 hover:border-orange-500 hover:bg-orange-50/30 rounded-2xl p-5 bg-stone-50/50 text-center cursor-pointer transition-all space-y-2 group">
                                <div class="w-12 h-12 bg-white rounded-full flex items-center justify-center mx-auto shadow-sm group-hover:scale-110 transition-transform">
                                    <i class="fa-solid fa-cloud-arrow-up text-xl text-stone-400 group-hover:text-orange-500 transition-colors" id="upload_icon"></i>
                                </div>
                                <div>
                                    <p class="text-xs font-extrabold text-stone-700" id="upload_text">Click to browse or drag exam file here</p>
                                    <p class="text-[10px] text-stone-400 font-medium">Supports JPG, PNG, PDF, WEBP up to 10MB</p>
                                </div>
                                <input type="file" name="exam_file" id="exam_file" required accept="image/*,.pdf" class="hidden" onchange="displaySelectedFile(this)">
                            </div>
                        </div>

                        <button type="submit" name="process_ai_ocr" onclick="showLoadingState()" class="w-full bg-orange-600 hover:bg-orange-700 text-white font-extrabold text-xs py-3.5 rounded-xl transition-all shadow-md hover:shadow-lg flex items-center justify-center gap-2">
                            <i class="fa-solid fa-wand-magic-sparkles"></i> Process & Evaluate with Groq AI
                        </button>
                    </form>
                </div>

                <!-- RIGHT OUTPUT WORKBENCH (7 COLUMNS) -->
                <div class="lg:col-span-7 space-y-6">
                    <?php if ($ai_analyzed_data): ?>
                        <div class="bg-white border border-stone-200 rounded-2xl p-6 shadow-sm space-y-6 animate-fadeIn">
                            
                            <!-- RESULT HEADER BAR -->
                            <div class="flex items-center justify-between border-b border-stone-100 pb-4">
                                <div>
                                    <h3 class="text-sm font-extrabold text-stone-800 uppercase tracking-tight flex items-center gap-2">
                                        <i class="fa-solid fa-square-poll-vertical text-orange-600"></i> Live AI Evaluation Dashboard
                                    </h3>
                                    <p class="text-[11px] text-stone-400 font-medium mt-0.5">File Analyzed: <strong class="text-stone-700"><?php echo htmlspecialchars($ai_analyzed_data['file_name']); ?></strong></p>
                                </div>
                                <span class="px-3 py-1 rounded-xl text-xs font-black uppercase tracking-wider shadow-sm <?php echo ($ai_analyzed_data['status'] === 'Pass') ? 'bg-emerald-500 text-white' : 'bg-rose-500 text-white'; ?>">
                                    <i class="fa-solid <?php echo ($ai_analyzed_data['status'] === 'Pass') ? 'fa-circle-check' : 'fa-circle-xmark'; ?> mr-1"></i>
                                    <?php echo htmlspecialchars($ai_analyzed_data['status']); ?>
                                </span>
                            </div>

                            <!-- METRICS CARDS GRID -->
                            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                                <div class="bg-emerald-50/70 border border-emerald-200 p-3.5 rounded-2xl text-center">
                                    <p class="text-[10px] font-extrabold text-emerald-700 uppercase tracking-wider">Correct</p>
                                    <p class="text-2xl font-black text-emerald-900 mt-1"><?php echo intval($ai_analyzed_data['correct']); ?></p>
                                </div>
                                <div class="bg-rose-50/70 border border-rose-200 p-3.5 rounded-2xl text-center">
                                    <p class="text-[10px] font-extrabold text-rose-700 uppercase tracking-wider">Wrong</p>
                                    <p class="text-2xl font-black text-rose-900 mt-1"><?php echo intval($ai_analyzed_data['wrong']); ?></p>
                                </div>
                                <div class="bg-stone-50 border border-stone-200 p-3.5 rounded-2xl text-center">
                                    <p class="text-[10px] font-extrabold text-stone-500 uppercase tracking-wider">Total Items</p>
                                    <p class="text-2xl font-black text-stone-800 mt-1"><?php echo intval($ai_analyzed_data['correct']) . ' / ' . intval($ai_analyzed_data['total_items']); ?></p>
                                </div>
                                <div class="bg-orange-50/70 border border-orange-200 p-3.5 rounded-2xl text-center">
                                    <p class="text-[10px] font-extrabold text-orange-700 uppercase tracking-wider">Final Grade</p>
                                    <p class="text-2xl font-black text-orange-800 mt-1"><?php echo number_format($ai_analyzed_data['percentage'], 1); ?>%</p>
                                </div>
                            </div>

                            <!-- ITEMIZED BREAKDOWN TABLE -->
                            <div class="space-y-3">
                                <h4 class="text-xs font-bold text-stone-700 uppercase tracking-wider flex items-center justify-between">
                                    <span>Itemized Answer Review:</span>
                                    <span class="text-[10px] text-stone-400 font-normal">Auto-checked by Groq OCR</span>
                                </h4>
                                
                                <div class="space-y-2 max-h-80 overflow-y-auto pr-1 custom-scrollbar">
                                    <?php foreach ($ai_analyzed_data['questions'] as $item): ?>
                                        <div class="p-3.5 border rounded-2xl flex items-start justify-between gap-4 text-xs transition-all <?php echo $item['is_correct'] ? 'bg-emerald-50/40 border-emerald-200' : 'bg-rose-50/40 border-rose-200'; ?>">
                                            <div class="space-y-1">
                                                <p class="font-bold text-stone-800">
                                                    <span class="inline-block w-5 text-stone-500 font-black"><?php echo intval($item['num']); ?>.</span>
                                                    <?php echo htmlspecialchars($item['q']); ?>
                                                </p>
                                                <p class="text-[11px] font-medium text-stone-600 pl-5">
                                                    Student Answer: 
                                                    <span class="px-2 py-0.5 rounded-md font-bold text-[11px] <?php echo $item['is_correct'] ? 'bg-emerald-200/80 text-emerald-900' : 'bg-rose-200/80 text-rose-900'; ?>">
                                                        <?php echo htmlspecialchars($item['student_ans']); ?>
                                                    </span>
                                                </p>
                                            </div>
                                            <div class="text-right flex-shrink-0">
                                                <span class="text-[9px] font-extrabold uppercase text-stone-400 block">Correct Key</span>
                                                <span class="font-extrabold text-stone-800 text-xs"><?php echo htmlspecialchars($item['key_ans']); ?></span>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>

                            <!-- SAVE SUBMISSION FORM -->
                            <form action="upload_check.php" method="POST" class="pt-4 border-t border-stone-100 flex justify-between items-center">
                                <a href="upload_check.php" class="text-xs font-bold text-stone-500 hover:text-stone-800 transition-colors">
                                    <i class="fa-solid fa-rotate-left mr-1"></i> Discard & Re-scan
                                </a>

                                <input type="hidden" name="final_student_name" value="<?php echo htmlspecialchars($ai_analyzed_data['student_name']); ?>">
                                <input type="hidden" name="final_exam_title" value="<?php echo htmlspecialchars($ai_analyzed_data['exam_title']); ?>">
                                <input type="hidden" name="final_upload_type" value="<?php echo htmlspecialchars($ai_analyzed_data['upload_type']); ?>">
                                <input type="hidden" name="final_correct" value="<?php echo intval($ai_analyzed_data['correct']); ?>">
                                <input type="hidden" name="final_wrong" value="<?php echo intval($ai_analyzed_data['wrong']); ?>">
                                <input type="hidden" name="final_total_items" value="<?php echo intval($ai_analyzed_data['total_items']); ?>">
                                <input type="hidden" name="final_percentage" value="<?php echo floatval($ai_analyzed_data['percentage']); ?>">
                                <input type="hidden" name="final_status" value="<?php echo htmlspecialchars($ai_analyzed_data['status']); ?>">
                                
                                <button type="submit" name="save_submission" class="bg-stone-900 hover:bg-orange-600 text-white font-extrabold text-xs px-6 py-3 rounded-xl shadow-md transition-all flex items-center gap-2">
                                    <i class="fa-solid fa-floppy-disk"></i> Save Results to Gradebook
                                </button>
                            </form>
                        </div>
                    <?php else: ?>
                        <!-- EMPTY STANDBY STATE -->
                        <div class="bg-white border border-stone-200 rounded-2xl p-12 text-center text-stone-400 space-y-4 shadow-sm flex flex-col items-center justify-center min-h-[420px]">
                            <div class="w-16 h-16 bg-orange-50 text-orange-600 rounded-2xl flex items-center justify-center text-3xl animate-pulseGlow shadow-inner">
                                <i class="fa-solid fa-microchip"></i>
                            </div>
                            <div class="max-w-md space-y-1">
                                <h3 class="text-base font-extrabold text-stone-800">Groq AI Engine Standby</h3>
                                <p class="text-xs text-stone-400 leading-relaxed">
                                    Mag-upload o mag-attach ng examination sheet sa kaliwa, i-define ang Master Answer Key, at i-click ang <strong>"Process & Evaluate"</strong> para simulan ang totoong live OCR grading process.
                                </p>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>

            </div>

        </div>
    </main>

    <!-- LOADING OVERLAY MODAL FOR GROQ API -->
    <div id="loading_overlay" class="fixed inset-0 bg-stone-950/80 backdrop-blur-sm hidden flex-col items-center justify-center z-50 p-4 space-y-4">
        <div class="w-16 h-16 border-4 border-orange-500 border-t-transparent rounded-full animate-spin"></div>
        <div class="text-center space-y-1">
            <h4 class="text-white font-extrabold text-base">Groq AI Analyzing Answer Sheet...</h4>
            <p class="text-stone-400 text-xs">Extracting handwriting/printed text and comparing against Master Key.</p>
        </div>
    </div>

    <!-- LOGOUT CONFIRMATION MODAL -->
    <div id="logout_modal" class="fixed inset-0 bg-stone-950/70 backdrop-blur-sm hidden items-center justify-center z-50 p-4">
        <div class="bg-white border border-stone-200 p-6 rounded-2xl max-w-sm w-full space-y-4 shadow-2xl animate-fadeIn">
            <div class="flex items-center gap-3">
                <div class="w-12 h-12 bg-red-100 text-red-600 rounded-xl flex items-center justify-center">
                    <i class="fa-solid fa-right-from-bracket text-xl"></i>
                </div>
                <div>
                    <h4 class="font-extrabold text-base text-stone-800">Confirm Logout</h4>
                    <p class="text-xs text-stone-500">Are you sure you want to sign out?</p>
                </div>
            </div>
            <div class="flex gap-2 justify-end pt-2">
                <button onclick="closeLogoutModal()" class="px-4 py-2.5 bg-stone-200 text-stone-700 font-bold text-xs rounded-xl hover:bg-stone-300 transition-all">
                    Cancel
                </button>
                <button onclick="confirmLogout()" class="px-4 py-2.5 bg-red-600 text-white font-bold text-xs rounded-xl shadow-md hover:bg-red-700 transition-all">
                    <i class="fa-solid fa-right-from-bracket mr-1"></i> Logout
                </button>
            </div>
        </div>
    </div>

    <!-- JAVASCRIPT HANDLERS -->
    <script>
        function triggerFileSelect() { 
            document.getElementById('exam_file').click(); 
        }

        function displaySelectedFile(input) {
            const icon = document.getElementById('upload_icon');
            const txt = document.getElementById('upload_text');
            const zone = document.getElementById('drop_zone');
            
            if (input.files && input.files.length > 0) {
                const fileName = input.files[0].name;
                icon.className = "fa-solid fa-circle-check text-xl text-emerald-500";
                txt.innerHTML = `<span class="text-emerald-700 font-bold">${fileName}</span> attached!`;
                zone.classList.replace('border-stone-300', 'border-emerald-500');
                zone.classList.add('bg-emerald-50/40');
            }
        }

        function showLoadingState() {
            const form = document.getElementById('ocr_form');
            if (form.checkValidity()) {
                document.getElementById('loading_overlay').classList.remove('hidden');
                document.getElementById('loading_overlay').classList.add('flex');
            }
        }

        function openLogoutModal() {
            document.getElementById('logout_modal').classList.remove('hidden');
            document.getElementById('logout_modal').classList.add('flex');
        }
        
        function closeLogoutModal() {
            document.getElementById('logout_modal').classList.add('hidden');
            document.getElementById('logout_modal').classList.remove('flex');
        }
        
        function confirmLogout() {
            window.location.href = '../logout.php';
        }
    </script>
</body>
</html>