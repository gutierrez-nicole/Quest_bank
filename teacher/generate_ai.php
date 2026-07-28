<?php
require_once __DIR__ . '/../app/database.php';
require_once __DIR__ . '/../app/session.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../app/services/GroqService.php';

requireRole('teacher');
$pdo = getDBConnection();

try {
    $teacher_id = getCurrentUserId();
    $stmt = $pdo->prepare("SELECT fullname, username, email FROM users WHERE id = ?");
    $stmt->execute([$teacher_id]);
    $teacher = $stmt->fetch();
} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}

$success_msg = "";
$error_msg = "";
$generated_questions = null;

// Handle AI Question Generation Request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['generate_questions'])) {
    validateCSRFToken();
    $lesson_text = trim($_POST['lesson_text'] ?? '');
    $num_questions = intval($_POST['num_questions'] ?? 5);
    $subject = trim($_POST['subject'] ?? '');
    $exam_title = trim($_POST['exam_title'] ?? '');
    $specialization = trim($_POST['specialization'] ?? 'Structural Engineering');

    if (!empty($lesson_text) && $num_questions > 0) {
        $result = GroqService::generateQuestions($lesson_text, $num_questions, $subject, $exam_title, $specialization);
        if (isset($result['success'])) {
            $generated_questions = $result['questions'];
            $success_msg = "AI generated " . count($generated_questions) . " question items for {$specialization} successfully!";
        } else {
            $error_msg = $result['error'] ?? "Failed to generate AI questions.";
        }
    } else {
        $error_msg = "Please enter lesson material text and set valid question parameters.";
    }
}

// Save AI Generated Exam to Database
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_ai_exam'])) {
    validateCSRFToken();
    $title = trim($_POST['save_title']);
    $subject = trim($_POST['save_subject']);
    $specialization = trim($_POST['save_specialization'] ?? 'Structural Engineering');
    $questions = $_POST['questions'] ?? [];

    if (!empty($title) && !empty($subject) && !empty($questions)) {
        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare("INSERT INTO exams (teacher_id, title, subject, specialization, time_limit, total_items) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$_SESSION['user_id'], $title, $subject, $specialization, 60, count($questions)]);
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
            $success_msg = "AI-generated exam saved to Question Bank under '{$specialization}' successfully!";
            $generated_questions = null;
        } catch (PDOException $e) {
            $pdo->rollBack();
            $error_msg = "Failed to save exam: " . $e->getMessage();
        }
    } else {
        $error_msg = "Exam parameters or question items are missing.";
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
    <!-- FontAwesome Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; }
        .custom-scrollbar::-webkit-scrollbar { width: 4px; }
        .custom-scrollbar::-webkit-scrollbar-thumb { background: #444; border-radius: 10px; }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: scale(0.95); }
            to { opacity: 1; transform: scale(1); }
        }
        .animate-fadeIn { animation: fadeIn 0.2s ease-out; }
    </style>
</head>
<body class="bg-[#fffbf7] min-h-screen flex">

    <!-- ================= SIDEBAR NAVIGATION ================= -->
    <?php require_once __DIR__ . '/../includes/teacher_sidebar.php'; ?>

    <!-- ================= MAIN CONTENT AREA ================= -->
    <main class="flex-grow flex flex-col min-w-0 lg:ml-64 min-h-screen">
        
        <!-- TOP NAV HEADERBAR -->
        <header class="bg-white border-b border-stone-200 px-6 py-4 flex items-center justify-between flex-shrink-0">
            <div>
                <h2 class="text-lg font-bold text-stone-800"><i class="fa-solid fa-wand-magic-sparkles text-orange-600 mr-2"></i>Civil Engineering AI Item Generator</h2>
                <p class="text-xs text-stone-400">Generate specialized test items from course materials for Civil Engineering disciplines.</p>
            </div>
            
            <div class="flex items-center gap-4">
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
                            <i class="fa-solid fa-book-open text-orange-500"></i> 1. Lesson & Branch Setup
                        </h3>
                        <span class="text-[10px] bg-orange-100 text-orange-700 font-extrabold px-2 py-0.5 rounded-full">Groq Llama-3.3</span>
                    </div>

                    <form action="generate_ai.php" method="POST" id="ai_form" class="space-y-4">
                        <?php echo csrfInputField(); ?>
                        
                        <div class="space-y-1">
                            <label class="text-xs font-bold text-stone-700">Exam Title</label>
                            <div class="relative">
                                <i class="fa-solid fa-file-signature absolute left-3.5 top-3 text-stone-400 text-xs"></i>
                                <input type="text" name="exam_title" required value="<?php echo htmlspecialchars($_POST['exam_title'] ?? ''); ?>" placeholder="e.g. Reinforced Concrete Design Quiz 1" class="w-full bg-stone-50 border border-stone-200 rounded-xl pl-9 pr-4 py-2.5 text-xs font-semibold text-stone-800 outline-none focus:border-orange-500 focus:bg-white transition-all">
                            </div>
                        </div>

                        <div class="space-y-1">
                            <label class="text-xs font-bold text-stone-700">Subject Name</label>
                            <div class="relative">
                                <i class="fa-solid fa-book absolute left-3.5 top-3 text-stone-400 text-xs"></i>
                                <input type="text" name="subject" required value="<?php echo htmlspecialchars($_POST['subject'] ?? ''); ?>" placeholder="e.g. Structural Theory" class="w-full bg-stone-50 border border-stone-200 rounded-xl pl-9 pr-4 py-2.5 text-xs font-semibold text-stone-800 outline-none focus:border-orange-500 focus:bg-white transition-all">
                            </div>
                        </div>

                        <!-- Civil Engineering Specialization Selector (Docx Figure 11) -->
                        <div class="space-y-1">
                            <label class="text-xs font-bold text-stone-700">Civil Engineering Specialization Branch</label>
                            <div class="relative">
                                <i class="fa-solid fa-compass-drafting absolute left-3.5 top-3 text-orange-500 text-xs"></i>
                                <select name="specialization" required class="w-full bg-stone-50 border border-stone-200 rounded-xl pl-9 pr-4 py-2.5 text-xs font-semibold text-stone-800 outline-none focus:border-orange-500 focus:bg-white transition-all">
                                    <?php foreach (getCivilEngineeringSpecializations() as $key => $label): ?>
                                        <option value="<?php echo htmlspecialchars($key); ?>" <?php echo (($_POST['specialization'] ?? '') === $key) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($label); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <div class="space-y-1">
                            <label class="text-xs font-bold text-stone-700">Number of Questions</label>
                            <div class="relative">
                                <i class="fa-solid fa-hashtag absolute left-3.5 top-3 text-stone-400 text-xs"></i>
                                <input type="number" name="num_questions" value="<?php echo htmlspecialchars($_POST['num_questions'] ?? 5); ?>" min="1" max="20" required class="w-full bg-stone-50 border border-stone-200 rounded-xl pl-9 pr-4 py-2.5 text-xs font-semibold text-stone-800 outline-none focus:border-orange-500 focus:bg-white transition-all">
                            </div>
                        </div>

                        <div class="space-y-1">
                            <label class="text-xs font-bold text-stone-700">Paste Lesson Content / Syllabi Notes</label>
                            <textarea name="lesson_text" required rows="7" placeholder="Paste Civil Engineering notes, formulas, or lecture content here...&#10;&#10;Example:&#10;Stress is defined as internal resistance per unit area (sigma = P / A). Reinforced concrete beams must satisfy flexural capacity requirements under ultimate load design..." class="w-full bg-stone-50 border border-stone-200 rounded-xl p-3 text-xs font-medium text-stone-800 outline-none focus:border-orange-500 focus:bg-white resize-none transition-all"><?php echo htmlspecialchars($_POST['lesson_text'] ?? ''); ?></textarea>
                        </div>

                        <button type="submit" name="generate_questions" onclick="showLoadingState()" class="w-full bg-orange-600 hover:bg-orange-700 text-white font-extrabold text-xs py-3.5 rounded-xl transition-all shadow-md hover:shadow-lg flex items-center justify-center gap-2">
                            <i class="fa-solid fa-robot"></i> Generate AI Test Items
                        </button>
                    </form>
                </div>

                <!-- RIGHT OUTPUT REVIEW PANEL (7 COLUMNS) -->
                <div class="lg:col-span-7 space-y-4">
                    <?php if (!empty($generated_questions)): ?>
                        <form action="generate_ai.php" method="POST" class="bg-white border border-stone-200 rounded-2xl p-6 shadow-sm space-y-6 animate-fadeIn">
                            <?php echo csrfInputField(); ?>
                            
                            <!-- RESULT HEADER BAR -->
                            <div class="flex items-center justify-between border-b border-stone-100 pb-4">
                                <div>
                                    <h3 class="text-sm font-extrabold text-stone-800 uppercase tracking-tight flex items-center gap-2">
                                        <i class="fa-solid fa-list-check text-orange-600"></i> 2. Review & Save Exam
                                    </h3>
                                    <p class="text-[11px] text-stone-400 font-medium mt-0.5">
                                        Title: <strong class="text-stone-700"><?php echo htmlspecialchars($_POST['exam_title']); ?></strong> | 
                                        Branch: <strong class="text-orange-600"><?php echo htmlspecialchars($_POST['specialization']); ?></strong>
                                    </p>
                                </div>
                                <span class="px-3 py-1 rounded-xl text-xs font-black uppercase tracking-wider shadow-sm bg-orange-100 text-orange-800">
                                    <?php echo count($generated_questions); ?> Items
                                </span>
                            </div>

                            <input type="hidden" name="save_title" value="<?php echo htmlspecialchars($_POST['exam_title']); ?>">
                            <input type="hidden" name="save_subject" value="<?php echo htmlspecialchars($_POST['subject']); ?>">
                            <input type="hidden" name="save_specialization" value="<?php echo htmlspecialchars($_POST['specialization']); ?>">

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
                                <a href="generate_ai.php" class="text-xs font-bold text-stone-400 hover:text-stone-700">Discard Items</a>
                                <button type="submit" name="save_ai_exam" class="bg-emerald-600 hover:bg-emerald-700 text-white font-extrabold text-xs px-6 py-3 rounded-xl shadow-md transition-all flex items-center gap-2">
                                    <i class="fa-solid fa-floppy-disk"></i> Save Exam to Question Bank
                                </button>
                            </div>
                        </form>
                    <?php else: ?>
                        <div class="bg-white border border-stone-200 rounded-2xl p-12 text-center space-y-3 shadow-sm">
                            <div class="w-16 h-16 bg-orange-50 text-orange-500 rounded-3xl flex items-center justify-center mx-auto text-2xl font-black shadow-inner">
                                <i class="fa-solid fa-wand-magic-sparkles"></i>
                            </div>
                            <h3 class="text-sm font-extrabold text-stone-800">Ready to Generate Civil Engineering Exams</h3>
                            <p class="text-xs text-stone-400 max-w-sm mx-auto">Fill out the form on the left with your lesson content and select your Civil Engineering specialization branch.</p>
                        </div>
                    <?php endif; ?>
                </div>

            </div>

        </div>
    </main>

    <!-- LOADING OVERLAY -->
    <div id="loading_overlay" class="fixed inset-0 bg-stone-950/70 backdrop-blur-sm hidden flex-col items-center justify-center z-50 p-4">
        <div class="bg-white p-6 rounded-2xl max-w-sm w-full text-center space-y-4 shadow-2xl animate-fadeIn">
            <div class="w-12 h-12 border-4 border-orange-500 border-t-transparent rounded-full animate-spin mx-auto"></div>
            <div>
                <h4 class="font-extrabold text-sm text-stone-800">Generating Exam Questions</h4>
                <p class="text-xs text-stone-500 mt-1">Groq Llama-3.3 AI is parsing lesson content and formatting answer keys...</p>
            </div>
        </div>
    </div>

    <script>
        function showLoadingState() {
            document.getElementById('loading_overlay').classList.remove('hidden');
            document.getElementById('loading_overlay').classList.add('flex');
        }
    </script>
</body>
</html>