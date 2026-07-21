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
$generated_questions = null;

// Handle AI Question Generation Request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['generate_questions'])) {
    $lesson_text = trim($_POST['lesson_text']);
    $num_questions = intval($_POST['num_questions']);
    $subject = trim($_POST['subject']);
    $exam_title = trim($_POST['exam_title']);

    if (!empty($lesson_text) && $num_questions > 0) {
        $secretKey = '';
        $endpoint = 'https://api.groq.com/openai/v1/chat/completions';

        $prompt = 'You are an educational AI assistant. Generate exactly ' . $num_questions . ' high-quality exam questions based on the following lesson text: '
                . '"' . $lesson_text . '". '
                . 'Format the response as a JSON array of objects. Each object MUST have: '
                . '"question" (string), "type" ("multiple_choice" or "identification"), '
                . '"opt_a" (string or null), "opt_b" (string or null), "opt_c" (string or null), "opt_d" (string or null), '
                . 'and "correct_answer" (string). '
                . 'Return ONLY the JSON array string, without any markdown formatting like ```json or additional text.';

        $postData = [
            'model' => 'llama3-8b-8192',
            'messages' => [
                ['role' => 'user', 'content' => $prompt]
            ],
            'temperature' => 0.3
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
            $error_msg = "API Error: " . $curl_error;
        } else {
            $responseDecoded = json_decode($response, true);
            $ai_raw_output = $responseDecoded['choices'][0]['message']['content'] ?? '';
            $cleaned_questions = json_decode(trim($ai_raw_output), true);

            if (json_last_error() === JSON_ERROR_NONE && is_array($cleaned_questions)) {
                $generated_questions = $cleaned_questions;
                $success_msg = "AI generated " . count($cleaned_questions) . " question items successfully!";
            } else {
                $error_msg = "Failed to parse AI output. Please try providing more detailed lesson text.";
            }
        }
    } else {
        $error_msg = "Please enter lesson material text and set valid question parameters.";
    }
}

// Save AI Generated Exam to Database
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_ai_exam'])) {
    $title = trim($_POST['save_title']);
    $subject = trim($_POST['save_subject']);
    $questions = $_POST['questions'] ?? [];

    if (!empty($title) && !empty($subject) && !empty($questions)) {
        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare("INSERT INTO exams (teacher_id, title, subject, time_limit, total_items) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$_SESSION['user_id'], $title, $subject, 60, count($questions)]);
            $exam_id = $pdo->lastInsertId();

            $qStmt = $pdo->prepare("INSERT INTO exam_questions (exam_id, question_text, question_type, option_a, option_b, option_c, option_d, correct_answer) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            
            foreach ($questions as $q) {
                $qStmt->execute([
                    $exam_id,
                    $q['text'],
                    $q['type'],
                    $q['opt_a'] ?? null,
                    $q['opt_b'] ?? null,
                    $q['opt_c'] ?? null,
                    $q['opt_d'] ?? null,
                    $q['correct']
                ]);
            }

            $pdo->commit();
            $success_msg = "AI-generated exam saved to Question Bank successfully!";
            $generated_questions = null;
        } catch (Exception $e) {
            $pdo->rollBack();
            $error_msg = "Failed to save generated exam: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>QuestBank - AI Question Generator</title>
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
                        <a href="upload_check.php" class="flex items-center gap-3 px-3 py-2 text-xs font-medium rounded-lg text-stone-400 hover:bg-stone-900 hover:text-orange-500 transition-all">
                            <i class="fa-solid fa-expand w-4 text-center"></i> OCR Answer Checker
                        </a>
                        <a href="create_exam.php" class="flex items-center gap-3 px-3 py-2 text-xs font-medium rounded-lg text-stone-400 hover:bg-stone-900 hover:text-orange-500 transition-all">
                            <i class="fa-solid fa-file-signature w-4 text-center"></i> Create Exam
                        </a>
                        <a href="generate_ai.php" class="flex items-center gap-3 px-3 py-2 text-xs font-semibold rounded-lg bg-orange-600 text-white shadow-sm">
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
                <h2 class="text-lg font-bold text-stone-800"><i class="fa-solid fa-wand-magic-sparkles text-orange-600 mr-2"></i>AI Exam Item Generator</h2>
                <p class="text-xs text-stone-400">Paste lesson materials, notes, or syllabi to automatically produce exam questions .</p>
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

            <!-- MAIN AI GENERATOR GRID -->
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">
                
                <!-- LEFT INPUT FORM PANEL (5 COLUMNS) -->
                <div class="lg:col-span-5 bg-white border border-stone-200 rounded-2xl p-6 shadow-sm space-y-5">
                    <div class="flex items-center justify-between border-b border-stone-100 pb-3">
                        <h3 class="text-xs font-extrabold uppercase tracking-wider text-stone-800 flex items-center gap-2">
                            <i class="fa-solid fa-book-open text-orange-500"></i> 1. Lesson Input Setup
                        </h3>
                        <span class="text-[10px] bg-orange-100 text-orange-700 font-extrabold px-2 py-0.5 rounded-full">Groq Llama-3</span>
                    </div>

                    <form action="generate_ai.php" method="POST" id="ai_form" class="space-y-4">
                        <div class="space-y-1">
                            <label class="text-xs font-bold text-stone-700">Exam Title</label>
                            <div class="relative">
                                <i class="fa-solid fa-file-signature absolute left-3.5 top-3 text-stone-400 text-xs"></i>
                                <input type="text" name="exam_title" required placeholder="e.g. CI/CD & DevOps Quiz" class="w-full bg-stone-50 border border-stone-200 rounded-xl pl-9 pr-4 py-2.5 text-xs font-semibold text-stone-800 outline-none focus:border-orange-500 focus:bg-white transition-all">
                            </div>
                        </div>

                        <div class="space-y-1">
                            <label class="text-xs font-bold text-stone-700">Subject Name</label>
                            <div class="relative">
                                <i class="fa-solid fa-book absolute left-3.5 top-3 text-stone-400 text-xs"></i>
                                <input type="text" name="subject" required placeholder="e.g. System Architecture" class="w-full bg-stone-50 border border-stone-200 rounded-xl pl-9 pr-4 py-2.5 text-xs font-semibold text-stone-800 outline-none focus:border-orange-500 focus:bg-white transition-all">
                            </div>
                        </div>

                        <div class="space-y-1">
                            <label class="text-xs font-bold text-stone-700">Number of Questions</label>
                            <div class="relative">
                                <i class="fa-solid fa-hashtag absolute left-3.5 top-3 text-stone-400 text-xs"></i>
                                <input type="number" name="num_questions" value="5" min="1" max="20" required class="w-full bg-stone-50 border border-stone-200 rounded-xl pl-9 pr-4 py-2.5 text-xs font-semibold text-stone-800 outline-none focus:border-orange-500 focus:bg-white transition-all">
                            </div>
                        </div>

                        <div class="space-y-1">
                            <label class="text-xs font-bold text-stone-700">Paste Lesson Notes / Text Content</label>
                            <textarea name="lesson_text" required rows="8" placeholder="Paste your lesson, article, or reviewer notes here...&#10;&#10;Example:&#10;Continuous Integration (CI) is a development practice where developers integrate code into a shared repository frequently. Each integration is verified by automated builds and tests..." class="w-full bg-stone-50 border border-stone-200 rounded-xl p-3 text-xs font-medium text-stone-800 outline-none focus:border-orange-500 focus:bg-white resize-none transition-all"></textarea>
                        </div>

                        <button type="submit" name="generate_questions" onclick="showLoadingState()" class="w-full bg-orange-600 hover:bg-orange-700 text-white font-extrabold text-xs py-3.5 rounded-xl transition-all shadow-md hover:shadow-lg flex items-center justify-center gap-2">
                            <i class="fa-solid fa-robot"></i> Generate Questions with AI
                        </button>
                    </form>
                </div>

                <!-- RIGHT REVIEW AND SAVE PANEL (7 COLUMNS) -->
                <div class="lg:col-span-7 space-y-6">
                    <?php if ($generated_questions): ?>
                        <form action="generate_ai.php" method="POST" class="bg-white border border-stone-200 rounded-2xl p-6 shadow-sm space-y-6 animate-fadeIn">
                            
                            <!-- RESULT HEADER BAR -->
                            <div class="flex items-center justify-between border-b border-stone-100 pb-4">
                                <div>
                                    <h3 class="text-sm font-extrabold text-stone-800 uppercase tracking-tight flex items-center gap-2">
                                        <i class="fa-solid fa-list-check text-orange-600"></i> 2. Review Generated Items
                                    </h3>
                                    <p class="text-[11px] text-stone-400 font-medium mt-0.5">Title: <strong class="text-stone-700"><?php echo htmlspecialchars($_POST['exam_title']); ?></strong> | Subject: <strong class="text-stone-700"><?php echo htmlspecialchars($_POST['subject']); ?></strong></p>
                                </div>
                                <span class="px-3 py-1 rounded-xl text-xs font-black uppercase tracking-wider shadow-sm bg-orange-100 text-orange-800">
                                    <?php echo count($generated_questions); ?> Items
                                </span>
                            </div>

                            <input type="hidden" name="save_title" value="<?php echo htmlspecialchars($_POST['exam_title']); ?>">
                            <input type="hidden" name="save_subject" value="<?php echo htmlspecialchars($_POST['subject']); ?>">

                            <div class="space-y-4 max-h-[500px] overflow-y-auto pr-2 custom-scrollbar">
                                <?php foreach ($generated_questions as $idx => $item): ?>
                                    <div class="p-4 border border-stone-200 rounded-2xl bg-stone-50/40 space-y-3 hover:border-orange-300 transition-all">
                                        <div class="flex items-center justify-between">
                                            <span class="font-black text-xs text-stone-800 bg-white px-2.5 py-1 rounded-lg border border-stone-200">Item #<?php echo $idx + 1; ?></span>
                                            <span class="text-[10px] font-bold uppercase text-stone-400 bg-white px-2 py-0.5 rounded-md"><?php echo htmlspecialchars($item['type']); ?></span>
                                        </div>

                                        <textarea name="questions[<?php echo $idx; ?>][text]" rows="2" class="w-full bg-white border border-stone-200 rounded-lg p-2.5 text-xs outline-none focus:border-orange-500 resize-none font-medium text-stone-800"><?php echo htmlspecialchars($item['question']); ?></textarea>
                                        <input type="hidden" name="questions[<?php echo $idx; ?>][type]" value="<?php echo htmlspecialchars($item['type']); ?>">

                                        <?php if ($item['type'] === 'multiple_choice'): ?>
                                            <div class="grid grid-cols-2 gap-2 text-xs">
                                                <div class="relative">
                                                    <span class="absolute left-2 top-2 text-[10px] font-bold text-stone-400">A.</span>
                                                    <input type="text" name="questions[<?php echo $idx; ?>][opt_a]" value="<?php echo htmlspecialchars($item['opt_a'] ?? ''); ?>" placeholder="Option A" class="w-full bg-white border border-stone-200 rounded-lg pl-6 pr-2 py-1.5 outline-none focus:border-orange-500 text-xs">
                                                </div>
                                                <div class="relative">
                                                    <span class="absolute left-2 top-2 text-[10px] font-bold text-stone-400">B.</span>
                                                    <input type="text" name="questions[<?php echo $idx; ?>][opt_b]" value="<?php echo htmlspecialchars($item['opt_b'] ?? ''); ?>" placeholder="Option B" class="w-full bg-white border border-stone-200 rounded-lg pl-6 pr-2 py-1.5 outline-none focus:border-orange-500 text-xs">
                                                </div>
                                                <div class="relative">
                                                    <span class="absolute left-2 top-2 text-[10px] font-bold text-stone-400">C.</span>
                                                    <input type="text" name="questions[<?php echo $idx; ?>][opt_c]" value="<?php echo htmlspecialchars($item['opt_c'] ?? ''); ?>" placeholder="Option C" class="w-full bg-white border border-stone-200 rounded-lg pl-6 pr-2 py-1.5 outline-none focus:border-orange-500 text-xs">
                                                </div>
                                                <div class="relative">
                                                    <span class="absolute left-2 top-2 text-[10px] font-bold text-stone-400">D.</span>
                                                    <input type="text" name="questions[<?php echo $idx; ?>][opt_d]" value="<?php echo htmlspecialchars($item['opt_d'] ?? ''); ?>" placeholder="Option D" class="w-full bg-white border border-stone-200 rounded-lg pl-6 pr-2 py-1.5 outline-none focus:border-orange-500 text-xs">
                                                </div>
                                            </div>
                                        <?php endif; ?>

                                        <div class="pt-1">
                                            <label class="text-[10px] font-bold text-stone-500 uppercase flex items-center gap-1">
                                                <i class="fa-solid fa-key text-emerald-600"></i> Correct Answer Key:
                                            </label>
                                            <input type="text" name="questions[<?php echo $idx; ?>][correct]" value="<?php echo htmlspecialchars($item['correct_answer']); ?>" class="w-full bg-emerald-50 border border-emerald-200 rounded-lg p-2 text-xs font-bold text-emerald-700 outline-none focus:border-emerald-500 mt-1">
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>

                            <div class="pt-4 border-t border-stone-100 flex justify-between items-center">
                                <a href="generate_ai.php" class="text-xs font-bold text-stone-500 hover:text-stone-800 transition-colors">
                                    <i class="fa-solid fa-rotate-left mr-1"></i> Discard & Generate New
                                </a>

                                <button type="submit" name="save_ai_exam" class="bg-stone-900 hover:bg-orange-600 text-white font-extrabold text-xs px-6 py-3 rounded-xl shadow-md transition-all flex items-center gap-2">
                                    <i class="fa-solid fa-floppy-disk"></i> Approve & Save to Question Bank
                                </button>
                            </div>
                        </form>
                    <?php else: ?>
                        <!-- EMPTY STANDBY STATE -->
                        <div class="bg-white border border-stone-200 rounded-2xl p-12 text-center text-stone-400 space-y-4 shadow-sm flex flex-col items-center justify-center min-h-[420px]">
                            <div class="w-16 h-16 bg-orange-50 text-orange-600 rounded-2xl flex items-center justify-center text-3xl animate-pulseGlow shadow-inner">
                                <i class="fa-solid fa-robot"></i>
                            </div>
                            <div class="max-w-md space-y-1">
                                <h3 class="text-base font-extrabold text-stone-800">AI Generator Standby</h3>
                                <p class="text-xs text-stone-400 leading-relaxed">
                                    I-paste ang lesson material sa kaliwa, i-set ang parameters, at i-click ang <strong>"Generate Questions"</strong> button para bumuo ng awtomatikong mga tanong gamit ang Groq AI.
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
            <h4 class="text-white font-extrabold text-base">Groq AI Generating Questions...</h4>
            <p class="text-stone-400 text-xs">Analyzing lesson content and creating exam items.</p>
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
        function showLoadingState() {
            const form = document.getElementById('ai_form');
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