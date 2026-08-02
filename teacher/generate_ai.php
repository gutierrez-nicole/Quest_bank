<?php
require_once __DIR__ . '/../app/bootstrap.php';

AuthService::enforceRole('teacher');
$pdo = getDBConnection();
$teacher_id = getCurrentUserId();

$success_msg = "";
$error_msg = "";
$generated_questions = null;
$ai_meta_output = null;

// Fetch teacher's completed lesson materials
$stmtMaterials = $pdo->prepare("SELECT id, title, subject, lesson_text, word_count, page_count FROM lesson_materials WHERE teacher_id = ? AND processing_status = 'completed' ORDER BY id DESC");
$stmtMaterials->execute([$teacher_id]);
$completed_lessons = $stmtMaterials->fetchAll(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['generate_questions'])) {
    validateCSRFToken();
    $input_source = $_POST['input_source'] ?? 'manual';
    $selected_lesson_ids = $_POST['selected_lessons'] ?? [];
    $lesson_text = trim($_POST['lesson_text'] ?? '');
    $num_questions = intval($_POST['num_questions'] ?? 5);
    $subject = trim(sanitizeInput($_POST['subject'] ?? ''));
    $exam_title = trim(sanitizeInput($_POST['exam_title'] ?? ''));
    $specialization = trim(sanitizeInput($_POST['specialization'] ?? 'Structural Engineering'));
    $question_type = trim($_POST['question_type'] ?? 'multiple_choice');
    $difficulty = trim($_POST['difficulty'] ?? 'medium');

    $final_lesson_content = "";
    $associated_lesson_ids = [];

    if ($input_source === 'extracted' && !empty($selected_lesson_ids)) {
        if (in_array('all', $selected_lesson_ids)) {
            foreach ($completed_lessons as $cl) {
                $final_lesson_content .= "\n\n=== Lesson: {$cl['title']} ({$cl['subject']}) ===\n" . $cl['lesson_text'];
                $associated_lesson_ids[] = $cl['id'];
            }
        } else {
            $placeholders = implode(',', array_fill(0, count($selected_lesson_ids), '?'));
            $stmtFetchSel = $pdo->prepare("SELECT id, title, subject, lesson_text FROM lesson_materials WHERE id IN ($placeholders) AND teacher_id = ?");
            $params = array_merge(array_map('intval', $selected_lesson_ids), [$teacher_id]);
            $stmtFetchSel->execute($params);
            $selLessons = $stmtFetchSel->fetchAll(PDO::FETCH_ASSOC);

            foreach ($selLessons as $sl) {
                $final_lesson_content .= "\n\n=== Lesson: {$sl['title']} ({$sl['subject']}) ===\n" . $sl['lesson_text'];
                $associated_lesson_ids[] = $sl['id'];
            }
        }
    } else {
        $final_lesson_content = $lesson_text;
    }

    if (!empty(trim($final_lesson_content)) && $num_questions > 0) {
        $result = GroqService::generateQuestions($final_lesson_content, $num_questions, $subject, $exam_title, $specialization, $question_type, $difficulty);
        if (isset($result['success'])) {
            $generated_questions = $result['questions'];
            $ai_meta_output = array_merge($result['metadata'] ?? [], ['lesson_ids' => $associated_lesson_ids]);
            $success_msg = "AI successfully generated " . count($generated_questions) . " question items from " . ($input_source === 'extracted' ? count($associated_lesson_ids) . " extracted lesson(s)" : "manual text") . "!";
        } else {
            $error_msg = $result['error'] ?? "Failed to generate AI questions.";
        }
    } else {
        $error_msg = "Please select extracted lesson materials or paste valid lesson content.";
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_ai_exam'])) {
    validateCSRFToken();
    $title = trim(sanitizeInput($_POST['save_title'] ?? ''));
    $subject = trim(sanitizeInput($_POST['save_subject'] ?? ''));
    $specialization = trim(sanitizeInput($_POST['save_specialization'] ?? 'Structural Engineering'));
    $difficulty = trim($_POST['save_difficulty'] ?? 'medium');
    $questions = $_POST['questions'] ?? [];
    $meta_json = $_POST['save_ai_metadata'] ?? '{}';
    $lesson_ids_str = $_POST['save_lesson_ids'] ?? '';

    if (!empty($title) && !empty($subject) && !empty($questions)) {
        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare("
                INSERT INTO exams 
                (teacher_id, title, subject, specialization, difficulty, time_limit, total_items, ai_metadata, lesson_ids) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $teacher_id, 
                $title, 
                $subject, 
                $specialization, 
                $difficulty, 
                60, 
                count($questions),
                $meta_json,
                $lesson_ids_str
            ]);
            $exam_id = $pdo->lastInsertId();

            $qStmt = $pdo->prepare("
                INSERT INTO exam_questions 
                (exam_id, question_text, question_type, option_a, option_b, option_c, option_d, correct_answer, formula_latex, matching_pairs, points, explanation, difficulty, topic, lesson_id) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $seenQuestions = [];
            $savedCount = 0;
            $primaryLessonId = !empty($associated_lesson_ids[0]) ? intval($associated_lesson_ids[0]) : null;

            foreach ($questions as $q) {
                $qText = trim($q['text'] ?? $q['question'] ?? '');
                if (empty($qText) || in_array(mb_strtolower($qText), $seenQuestions)) {
                    continue; // Skip duplicate question text
                }
                $seenQuestions[] = mb_strtolower($qText);

                $qStmt->execute([
                    $exam_id,
                    $qText,
                    $q['type'] ?? 'multiple_choice',
                    $q['opt_a'] ?? null,
                    $q['opt_b'] ?? null,
                    $q['opt_c'] ?? null,
                    $q['opt_d'] ?? null,
                    $q['correct'] ?? $q['correct_answer'] ?? '',
                    $q['formula_latex'] ?? null,
                    isset($q['matching_pairs']) ? (is_string($q['matching_pairs']) ? $q['matching_pairs'] : json_encode($q['matching_pairs'])) : null,
                    intval($q['points'] ?? 1),
                    $q['explanation'] ?? null,
                    $difficulty,
                    $subject,
                    $q['lesson_id'] ?? $primaryLessonId
                ]);
                $savedCount++;
            }

            // Update total items count
            $stmtUpdateTotal = $pdo->prepare("UPDATE exams SET total_items = ? WHERE id = ?");
            $stmtUpdateTotal->execute([$savedCount, $exam_id]);

            $pdo->commit();
            logActivity("Saved AI-generated exam '{$title}' ({$savedCount} deduplicated questions, Difficulty: {$difficulty}).", $teacher_id);
            $success_msg = "AI-generated exam '{$title}' saved to Question Bank successfully!";
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
    
    <script src="https://cdn.tailwindcss.com"></script>
    
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
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

    
    <?php require_once __DIR__ . '/../includes/teacher_sidebar.php'; ?>

    
    <main class="flex-grow flex flex-col min-w-0 ml-16 lg:ml-64 min-h-screen">
        
        
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

        
        <div class="flex-grow overflow-y-auto p-6 space-y-6 custom-scrollbar">

            
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

            
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">
                
                
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

                        <div class="space-y-1">
                            <label class="text-xs font-bold text-stone-700">Content Input Source</label>
                            <div class="grid grid-cols-2 gap-2 bg-stone-100 p-1 rounded-xl">
                                <label class="flex items-center justify-center gap-1.5 py-1.5 px-2 rounded-lg text-xs font-bold cursor-pointer transition-all has-[:checked]:bg-white has-[:checked]:text-orange-600 has-[:checked]:shadow-sm text-stone-600">
                                    <input type="radio" name="input_source" value="extracted" onclick="toggleInputSource('extracted')" <?php echo (($_POST['input_source'] ?? 'extracted') === 'extracted') ? 'checked' : ''; ?> class="hidden">
                                    <i class="fa-solid fa-file-lines"></i> Extracted Lessons
                                </label>
                                <label class="flex items-center justify-center gap-1.5 py-1.5 px-2 rounded-lg text-xs font-bold cursor-pointer transition-all has-[:checked]:bg-white has-[:checked]:text-orange-600 has-[:checked]:shadow-sm text-stone-600">
                                    <input type="radio" name="input_source" value="manual" onclick="toggleInputSource('manual')" <?php echo (($_POST['input_source'] ?? '') === 'manual') ? 'checked' : ''; ?> class="hidden">
                                    <i class="fa-solid fa-paste"></i> Manual Paste
                                </label>
                            </div>
                        </div>

                        <div id="extracted_lessons_block" class="space-y-2 <?php echo (($_POST['input_source'] ?? 'extracted') === 'manual') ? 'hidden' : ''; ?>">
                            <label class="text-xs font-bold text-stone-700 flex justify-between items-center">
                                <span>Select Extracted Lessons</span>
                                <span class="text-[10px] text-orange-600 font-semibold"><?php echo count($completed_lessons); ?> Available</span>
                            </label>
                            <?php if (!empty($completed_lessons)): ?>
                                <div class="max-h-40 overflow-y-auto border border-stone-200 rounded-xl bg-stone-50 p-2 space-y-1.5 text-xs">
                                    <label class="flex items-center gap-2 p-1.5 rounded hover:bg-white cursor-pointer font-extrabold text-orange-700">
                                        <input type="checkbox" name="selected_lessons[]" value="all" class="accent-orange-600 rounded">
                                        <span>Select All Module Lessons</span>
                                    </label>
                                    <?php foreach ($completed_lessons as $cl): ?>
                                        <label class="flex items-center justify-between p-1.5 rounded hover:bg-white cursor-pointer text-stone-800 font-medium">
                                            <div class="flex items-center gap-2 truncate">
                                                <input type="checkbox" name="selected_lessons[]" value="<?php echo $cl['id']; ?>" class="accent-orange-600 rounded">
                                                <span class="truncate"><?php echo htmlspecialchars($cl['title']); ?></span>
                                            </div>
                                            <span class="text-[9px] font-bold text-stone-400 bg-white px-1.5 py-0.5 rounded border flex-shrink-0">
                                                <?php echo number_format($cl['word_count']); ?> words
                                            </span>
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <div class="p-3 bg-amber-50 border border-amber-200 rounded-xl text-amber-800 text-xs font-semibold">
                                    No extracted lessons found. Please upload lesson materials first under <a href="upload_lessons.php" class="underline font-bold">Upload Lessons</a> or use Manual Paste mode.
                                </div>
                            <?php endif; ?>
                        </div>

                        <div id="manual_text_block" class="space-y-1 <?php echo (($_POST['input_source'] ?? 'extracted') === 'extracted' || !isset($_POST['input_source'])) ? 'hidden' : ''; ?>">
                            <label class="text-xs font-bold text-stone-700">Paste Lesson Content / Syllabi Notes</label>
                            <textarea name="lesson_text" rows="5" placeholder="Paste Civil Engineering notes, formulas, or lecture content here..." class="w-full bg-stone-50 border border-stone-200 rounded-xl p-3 text-xs font-medium text-stone-800 outline-none focus:border-orange-500 focus:bg-white resize-none transition-all"><?php echo htmlspecialchars($_POST['lesson_text'] ?? ''); ?></textarea>
                        </div>

                        <div class="grid grid-cols-2 gap-3">
                            <div class="space-y-1">
                                <label class="text-xs font-bold text-stone-700">Difficulty Level</label>
                                <select name="difficulty" class="w-full bg-stone-50 border border-stone-200 rounded-xl px-3 py-2 text-xs font-semibold text-stone-800 outline-none focus:border-orange-500">
                                    <option value="easy" <?php echo (($_POST['difficulty'] ?? '') === 'easy') ? 'selected' : ''; ?>>Easy</option>
                                    <option value="medium" <?php echo (($_POST['difficulty'] ?? 'medium') === 'medium') ? 'selected' : ''; ?>>Medium</option>
                                    <option value="hard" <?php echo (($_POST['difficulty'] ?? '') === 'hard') ? 'selected' : ''; ?>>Hard / Advanced</option>
                                    <option value="mixed" <?php echo (($_POST['difficulty'] ?? '') === 'mixed') ? 'selected' : ''; ?>>Mixed Difficulty</option>
                                </select>
                            </div>

                            <div class="space-y-1">
                                <label class="text-xs font-bold text-stone-700">Number of Items</label>
                                <select name="num_questions" class="w-full bg-stone-50 border border-stone-200 rounded-xl px-3 py-2 text-xs font-semibold text-stone-800 outline-none focus:border-orange-500">
                                    <?php foreach ([5, 10, 15, 20, 25, 30, 50] as $n): ?>
                                        <option value="<?php echo $n; ?>" <?php echo (intval($_POST['num_questions'] ?? 5) === $n) ? 'selected' : ''; ?>><?php echo $n; ?> Questions</option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <div class="space-y-1">
                            <label class="text-xs font-bold text-stone-700">Civil Engineering Specialization</label>
                            <select name="specialization" required class="w-full bg-stone-50 border border-stone-200 rounded-xl px-3 py-2 text-xs font-semibold text-stone-800 outline-none focus:border-orange-500">
                                <?php foreach (getCivilEngineeringSpecializations() as $key => $label): ?>
                                    <option value="<?php echo htmlspecialchars($key); ?>" <?php echo (($_POST['specialization'] ?? '') === $key) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($label); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="space-y-1">
                            <label class="text-xs font-bold text-stone-700">Question Format / Type</label>
                            <select name="question_type" required class="w-full bg-stone-50 border border-stone-200 rounded-xl px-3 py-2 text-xs font-semibold text-stone-800 outline-none focus:border-orange-500">
                                <option value="multiple_choice" <?php echo (($_POST['question_type'] ?? '') === 'multiple_choice') ? 'selected' : ''; ?>>Multiple Choice (Options A-D)</option>
                                <option value="true_false" <?php echo (($_POST['question_type'] ?? '') === 'true_false') ? 'selected' : ''; ?>>True or False</option>
                                <option value="identification" <?php echo (($_POST['question_type'] ?? '') === 'identification') ? 'selected' : ''; ?>>Identification</option>
                                <option value="fill_in_the_blank" <?php echo (($_POST['question_type'] ?? '') === 'fill_in_the_blank') ? 'selected' : ''; ?>>Fill-in-the-Blank</option>
                                <option value="matching_type" <?php echo (($_POST['question_type'] ?? '') === 'matching_type') ? 'selected' : ''; ?>>Matching Type</option>
                                <option value="problem_solving" <?php echo (($_POST['question_type'] ?? '') === 'problem_solving') ? 'selected' : ''; ?>>Problem Solving</option>
                                <option value="math_formula" <?php echo (($_POST['question_type'] ?? '') === 'math_formula') ? 'selected' : ''; ?>>Math Formula (LaTeX)</option>
                            </select>
                        </div>

                        <button type="submit" name="generate_questions" onclick="showLoadingState()" class="w-full bg-orange-600 hover:bg-orange-700 text-white font-extrabold text-xs py-3.5 rounded-xl transition-all shadow-md hover:shadow-lg flex items-center justify-center gap-2">
                            <i class="fa-solid fa-robot"></i> Generate AI Test Items
                        </button>
                    </form>

                    <script>
                        function toggleInputSource(type) {
                            const ext = document.getElementById('extracted_lessons_block');
                            const man = document.getElementById('manual_text_block');
                            if (type === 'extracted') {
                                ext.classList.remove('hidden');
                                man.classList.add('hidden');
                            } else {
                                ext.classList.add('hidden');
                                man.classList.remove('hidden');
                            }
                        }
                    </script>
                </div>

                
                <div class="lg:col-span-7 space-y-4">
                    <?php if (!empty($generated_questions)): ?>
                        <form action="generate_ai.php" method="POST" class="bg-white border border-stone-200 rounded-2xl p-6 shadow-sm space-y-6 animate-fadeIn">
                            <?php echo csrfInputField(); ?>
                            
                            
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
                            <input type="hidden" name="save_difficulty" value="<?php echo htmlspecialchars($difficulty ?? 'medium'); ?>">
                            <input type="hidden" name="save_ai_metadata" value="<?php echo htmlspecialchars(json_encode($ai_meta_output ?? [])); ?>">
                            <input type="hidden" name="save_lesson_ids" value="<?php echo htmlspecialchars(implode(',', $ai_meta_output['lesson_ids'] ?? [])); ?>">

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