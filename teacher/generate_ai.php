<?php
require_once __DIR__ . '/../app/bootstrap.php';

AuthService::enforceRole('teacher');
$pdo = getDBConnection();
$teacher_id = getCurrentUserId();

$success_msg = "";
$error_msg = "";
$generated_questions = null;
$ai_meta_output = null;

$stmtMaterials = $pdo->prepare("
    SELECT id, title, subject, lesson_text, word_count, page_count,
           COALESCE(academic_period, 'general') AS academic_period,
           semester, school_year, year_level, program, processing_status
    FROM lesson_materials 
    WHERE teacher_id = ? 
    ORDER BY FIELD(COALESCE(academic_period,'general'), 'general','prelim','midterm','finals'), id DESC
");
$stmtMaterials->execute([$teacher_id]);
$all_teacher_lessons = $stmtMaterials->fetchAll(PDO::FETCH_ASSOC);

$lessons_by_period = [
    'general' => [],
    'prelim' => [],
    'midterm' => [],
    'finals' => []
];

foreach ($all_teacher_lessons as $cl) {
    $period = strtolower($cl['academic_period'] ?? 'general');
    if (!isset($lessons_by_period[$period])) {
        $period = 'general';
    }
    $lessons_by_period[$period][] = $cl;
}

$filter_subjects = array_values(array_unique(array_filter(array_column($all_teacher_lessons, 'subject'))));
$filter_semesters = array_values(array_unique(array_filter(array_column($all_teacher_lessons, 'semester'))));
$filter_school_years = array_values(array_unique(array_filter(array_column($all_teacher_lessons, 'school_year'))));
$filter_year_levels = array_values(array_unique(array_filter(array_column($all_teacher_lessons, 'year_level'))));
$filter_programs = array_values(array_unique(array_filter(array_column($all_teacher_lessons, 'program'))));

if ($_SERVER['REQUEST_METHOD'] === 'POST' && (isset($_POST['generate_questions']) || isset($_POST['selected_lessons']) || !empty($_POST['lesson_text']))) {
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
    $allow_partial = isset($_POST['allow_partial']) && $_POST['allow_partial'] == '1';

    $final_lesson_content = "";
    $associated_lesson_ids = [];
    $associated_lesson_titles = [];
    $associated_periods = [];
    $associated_subjects = [];
    $total_selected_words = 0;
    $generation_batch_id = bin2hex(random_bytes(16));
    $generation_source_type = 'manual';
    $generation_warnings = [];
    $validation_errors = [];

    if ($input_source === 'extracted' && !empty($selected_lesson_ids)) {
        $selected_lesson_ids = array_filter($selected_lesson_ids, function($id) { return $id !== 'all'; });
        $selected_lesson_ids = array_unique(array_map('intval', $selected_lesson_ids));

        if (!empty($selected_lesson_ids)) {
            $placeholders = implode(',', array_fill(0, count($selected_lesson_ids), '?'));
            $stmtFetchSel = $pdo->prepare("
                SELECT id, title, subject, lesson_text, COALESCE(academic_period,'general') AS academic_period,
                       processing_status, word_count, semester, school_year, year_level, program
                FROM lesson_materials 
                WHERE id IN ($placeholders) AND teacher_id = ?
            ");
            $params = array_merge($selected_lesson_ids, [$teacher_id]);
            $stmtFetchSel->execute($params);
            $selLessons = $stmtFetchSel->fetchAll(PDO::FETCH_ASSOC);

            // Repair Prompt 2: Security & Authorization ID injection check
            $returnedIds = array_column($selLessons, 'id');
            $unauthorizedIds = array_diff($selected_lesson_ids, $returnedIds);
            if (!empty($unauthorizedIds)) {
                $validation_errors[] = "Access denied: Lesson ID(s) [" . implode(', ', $unauthorizedIds) . "] are unauthorized or do not exist.";
            }

            $lessonIndex = 1;
            foreach ($selLessons as $sl) {
                // Strict validation checks
                if ($sl['processing_status'] !== 'completed') {
                    $validation_errors[] = "Lesson '{$sl['title']}' (ID: {$sl['id']}) cannot be used: extraction status is '{$sl['processing_status']}'.";
                    continue;
                }
                if (empty(trim($sl['lesson_text'] ?? ''))) {
                    $validation_errors[] = "Lesson '{$sl['title']}' (ID: {$sl['id']}) cannot be used: extracted content is empty.";
                    continue;
                }

                $associated_subjects[] = $sl['subject'];
                $associated_lesson_titles[] = $sl['title'];

                $final_lesson_content .= "\n\nSOURCE LESSON {$lessonIndex}\n";
                $final_lesson_content .= "Lesson ID: {$sl['id']}\n";
                $final_lesson_content .= "Period: " . ucfirst($sl['academic_period']) . "\n";
                $final_lesson_content .= "Title: {$sl['title']}\n";
                $final_lesson_content .= "Subject: {$sl['subject']}\n";
                $final_lesson_content .= "Content:\n" . $sl['lesson_text'];

                $associated_lesson_ids[] = (int)$sl['id'];
                $associated_periods[] = $sl['academic_period'];
                $total_selected_words += (int)($sl['word_count'] ?? str_word_count($sl['lesson_text']));
                $lessonIndex++;
            }

            // Repair Prompt 2: Check mixed subject consistency
            $uniqueSubjects = array_unique(array_filter($associated_subjects));
            if (count($uniqueSubjects) > 1) {
                $validation_errors[] = "Conflicting subjects selected: [" . implode(', ', $uniqueSubjects) . "]. Lessons must match one target subject.";
            }

            if (!empty($validation_errors) && !$allow_partial) {
                $error_msg = "Validation failed for selected lesson pool:\n• " . implode("\n• ", $validation_errors);
            } else {
                $associated_periods = array_unique($associated_periods);
                $generation_source_type = count($associated_periods) > 1 ? 'cross_period_lessons' : 'single_period_lessons';
            }
        } else {
            $error_msg = "Please select at least one valid lesson material.";
        }
    } else {
        $final_lesson_content = $lesson_text;
        $generation_source_type = 'manual';
        $total_selected_words = str_word_count($lesson_text);
    }

    if (!empty(trim($final_lesson_content)) && $num_questions > 0 && empty($error_msg)) {
        $result = GroqService::generateQuestions($final_lesson_content, $num_questions, $subject, $exam_title, $specialization, $question_type, $difficulty);
        if (isset($result['success'])) {
            $generated_questions = $result['questions'];
            $estimatedTokens = (int)ceil(strlen($final_lesson_content) / 4);

            $ai_meta_output = array_merge($result['metadata'] ?? [], [
                'lesson_ids' => $associated_lesson_ids,
                'covered_periods' => array_values($associated_periods),
                'generation_batch_id' => $generation_batch_id,
                'generation_source_type' => $generation_source_type,
                'source_lesson_count' => count($associated_lesson_ids),
                'generation_warnings' => array_merge($generation_warnings, $result['metadata']['generation_warnings'] ?? []),
                'total_words' => $total_selected_words,
                'estimated_tokens' => $estimatedTokens
            ]);

            // Repair Prompt 5: Persist generation audit record in ai_generation_batches
            try {
                $stmtBatch = $pdo->prepare("
                    INSERT INTO ai_generation_batches 
                    (generation_batch_id, teacher_id, selected_lesson_ids, selected_lesson_titles, selected_periods, selected_subject, total_selected_words, estimated_tokens, ai_model, generation_duration, requested_question_count, generated_question_count, failed_question_count, warnings)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmtBatch->execute([
                    $generation_batch_id,
                    $teacher_id,
                    json_encode($associated_lesson_ids),
                    json_encode($associated_lesson_titles),
                    implode(',', $associated_periods),
                    $subject,
                    $total_selected_words,
                    $estimatedTokens,
                    GROQ_DEFAULT_MODEL,
                    floatval($result['metadata']['generation_time_ms'] ?? 0) / 1000,
                    $num_questions,
                    count($generated_questions),
                    0,
                    json_encode($ai_meta_output['generation_warnings'])
                ]);
            } catch (PDOException $e) {
                // Keep generation working even if batch audit logging encounters non-fatal error
            }

            $periodLabel = !empty($associated_periods) ? ' (' . implode(', ', array_map('ucfirst', $associated_periods)) . ')' : '';
            $success_msg = "AI successfully generated " . count($generated_questions) . " question items from " . ($input_source === 'extracted' ? count($associated_lesson_ids) . " extracted lesson(s)" . $periodLabel : "manual text") . "!";
            if (!empty($ai_meta_output['generation_warnings'])) {
                $success_msg .= " ⚠ Warnings: " . implode('; ', $ai_meta_output['generation_warnings']);
            }
        } else {
            $error_msg = $result['error'] ?? "Failed to generate AI questions.";
        }
    } elseif (empty($error_msg)) {
        $error_msg = "Please select extracted lesson materials or paste valid lesson content.";
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && (isset($_POST['save_ai_exam']) || isset($_POST['save_title']))) {
    validateCSRFToken();
    $title = trim(sanitizeInput($_POST['save_title'] ?? ''));
    $subject = trim(sanitizeInput($_POST['save_subject'] ?? ''));
    $specialization = trim(sanitizeInput($_POST['save_specialization'] ?? 'Structural Engineering'));
    $difficulty = trim($_POST['save_difficulty'] ?? 'medium');
    $exam_category = trim($_POST['save_exam_category'] ?? 'regular');
    $qualifying_passing_percentage = floatval($_POST['save_qualifying_passing_percentage'] ?? 80.00);
    $qualifying_max_attempts = intval($_POST['save_qualifying_max_attempts'] ?? 1);
    $qualifying_year_level = trim($_POST['save_qualifying_year_level'] ?? 'All Year Levels');
    $qualifying_program = trim($_POST['save_qualifying_program'] ?? 'All Programs');
    $qualifying_is_required = intval($_POST['save_qualifying_is_required'] ?? 1);
    $qualifying_unlock_date = !empty($_POST['save_qualifying_unlock_date']) ? $_POST['save_qualifying_unlock_date'] : null;
    $qualifying_deadline = !empty($_POST['save_qualifying_deadline']) ? $_POST['save_qualifying_deadline'] : null;
    $questions = $_POST['questions'] ?? [];
    $meta_json = $_POST['save_ai_metadata'] ?? '{}';
    $lesson_ids_str = $_POST['save_lesson_ids'] ?? '';

    $metaData = json_decode($meta_json, true) ?? [];
    $save_covered_periods = !empty($metaData['covered_periods']) ? implode(',', $metaData['covered_periods']) : null;
    $save_source_lesson_count = intval($metaData['source_lesson_count'] ?? 0);
    $save_generation_source_type = $metaData['generation_source_type'] ?? null;
    $save_generation_batch_id = $metaData['generation_batch_id'] ?? null;
    $save_ai_model = $metaData['model'] ?? null;

    if (!empty($title) && !empty($subject) && !empty($questions)) {
        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare("
                INSERT INTO exams 
                (teacher_id, title, subject, specialization, difficulty, time_limit, total_items, ai_metadata, lesson_ids,
                 exam_category, qualifying_passing_percentage, qualifying_max_attempts, qualifying_year_level,
                 qualifying_program, qualifying_is_required, qualifying_unlock_date, qualifying_deadline,
                 covered_periods, source_lesson_count, generation_source_type, generation_batch_id, ai_model) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
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
                $lesson_ids_str,
                $exam_category,
                $qualifying_passing_percentage,
                $qualifying_max_attempts,
                $qualifying_year_level,
                $qualifying_program,
                $qualifying_is_required,
                $qualifying_unlock_date,
                $qualifying_deadline,
                $save_covered_periods,
                $save_source_lesson_count,
                $save_generation_source_type,
                $save_generation_batch_id,
                $save_ai_model
            ]);
            $exam_id = $pdo->lastInsertId();

            $saveLessonIds = array_map('intval', array_filter(explode(',', $lesson_ids_str)));

            $qStmt = $pdo->prepare("
                INSERT INTO exam_questions 
                (exam_id, question_text, question_type, option_a, option_b, option_c, option_d, correct_answer, formula_latex, matching_pairs, points, explanation, difficulty, topic, lesson_id) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");

            // Repair Prompt 3: Normalized generated_question_sources relation table
            $srcStmt = $pdo->prepare("
                INSERT IGNORE INTO generated_question_sources (question_id, lesson_id, academic_period, source_topic, source_confidence) VALUES (?, ?, ?, ?, ?)
            ");

            $lessonPeriodMap = [];
            if (!empty($saveLessonIds)) {
                $plc = implode(',', array_fill(0, count($saveLessonIds), '?'));
                $stmtPeriods = $pdo->prepare("SELECT id, COALESCE(academic_period,'general') AS academic_period FROM lesson_materials WHERE id IN ($plc)");
                $stmtPeriods->execute($saveLessonIds);
                while ($lp = $stmtPeriods->fetch(PDO::FETCH_ASSOC)) {
                    $lessonPeriodMap[(int)$lp['id']] = $lp['academic_period'];
                }
            }
            
            $seenQuestions = [];
            $savedCount = 0;
            $primaryLessonId = !empty($saveLessonIds[0]) ? $saveLessonIds[0] : null;

            foreach ($questions as $q) {
                $qText = trim($q['text'] ?? $q['question'] ?? '');
                if (empty($qText) || in_array(mb_strtolower($qText), $seenQuestions)) {
                    continue; 
                }
                $seenQuestions[] = mb_strtolower($qText);

                // Repair Prompt 3: Extract question-specific source lesson IDs
                $rawQSources = $q['source_lesson_ids'] ?? [];
                if (is_string($rawQSources)) {
                    $rawQSources = array_filter(array_map('intval', explode(',', $rawQSources)));
                }
                $validQSources = array_intersect($rawQSources, $saveLessonIds);

                $qLessonId = !empty($validQSources) ? reset($validQSources) : (intval($q['lesson_id'] ?? 0) ?: $primaryLessonId);

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
                    $q['source_topic'] ?? $subject,
                    $qLessonId
                ]);
                $questionId = $pdo->lastInsertId();
                $savedCount++;

                // Repair Prompt 3: Link question ONLY to its specific valid source lesson(s)
                $targetSourceIds = !empty($validQSources) ? $validQSources : ($qLessonId ? [$qLessonId] : $saveLessonIds);
                foreach ($targetSourceIds as $srcLid) {
                    $srcPeriod = $lessonPeriodMap[$srcLid] ?? 'general';
                    $srcTopic = $q['source_topic'] ?? $subject;
                    $srcConf = $q['source_confidence'] ?? (!empty($validQSources) ? 'high' : 'review_required');
                    $srcStmt->execute([$questionId, $srcLid, $srcPeriod, $srcTopic, $srcConf]);
                }
            }

            $stmtUpdateTotal = $pdo->prepare("UPDATE exams SET total_items = ? WHERE id = ?");
            $stmtUpdateTotal->execute([$savedCount, $exam_id]);

            $pdo->commit();
            $sourceLabel = $save_generation_source_type === 'cross_period_lessons' ? ", Cross-Period" : "";
            logActivity("Saved AI-generated exam '{$title}' ({$savedCount} deduplicated questions, Difficulty: {$difficulty}{$sourceLabel}).", $teacher_id);
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

                        <div id="extracted_lessons_block" class="space-y-3 <?php echo (($_POST['input_source'] ?? 'extracted') === 'manual') ? 'hidden' : ''; ?>">
                            <label class="text-xs font-bold text-stone-700 flex justify-between items-center">
                                <span>Select Extracted Lessons (Cross-Period Pool)</span>
                                <span class="text-[10px] text-orange-600 font-semibold"><?php echo count($all_teacher_lessons); ?> Total</span>
                            </label>

                            <div class="bg-stone-50 border border-stone-200 rounded-xl p-2.5 space-y-2">
                                <div class="flex items-center justify-between">
                                    <span class="text-[10px] font-extrabold text-stone-700 uppercase tracking-wider">
                                        <i class="fa-solid fa-filter text-orange-500 mr-1"></i> Filter Lessons
                                    </span>
                                    <button type="button" onclick="resetLessonFilters()" class="text-[10px] text-orange-600 hover:text-orange-800 font-bold">Reset Filters</button>
                                </div>
                                <div class="grid grid-cols-2 sm:grid-cols-3 gap-2">
                                    <div>
                                        <label class="text-[9px] font-bold text-stone-500">Subject</label>
                                        <select id="filter_subject" data-testid="filter-subject" onchange="applyLessonFilters()" class="w-full bg-white border border-stone-200 rounded-lg px-2 py-1 text-[11px] font-semibold text-stone-800 outline-none focus:border-orange-500">
                                            <option value="">All Subjects</option>
                                            <?php foreach ($filter_subjects as $s): ?>
                                                <option value="<?php echo htmlspecialchars($s); ?>"><?php echo htmlspecialchars($s); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div>
                                        <label class="text-[9px] font-bold text-stone-500">Year Level</label>
                                        <select id="filter_year_level" data-testid="filter-year-level" onchange="applyLessonFilters()" class="w-full bg-white border border-stone-200 rounded-lg px-2 py-1 text-[11px] font-semibold text-stone-800 outline-none focus:border-orange-500">
                                            <option value="">All Year Levels</option>
                                            <?php foreach ($filter_year_levels as $yl): ?>
                                                <option value="<?php echo htmlspecialchars($yl); ?>"><?php echo htmlspecialchars($yl); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div>
                                        <label class="text-[9px] font-bold text-stone-500">Program</label>
                                        <select id="filter_program" data-testid="filter-program" onchange="applyLessonFilters()" class="w-full bg-white border border-stone-200 rounded-lg px-2 py-1 text-[11px] font-semibold text-stone-800 outline-none focus:border-orange-500">
                                            <option value="">All Programs</option>
                                            <?php foreach ($filter_programs as $p): ?>
                                                <option value="<?php echo htmlspecialchars($p); ?>"><?php echo htmlspecialchars($p); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div>
                                        <label class="text-[9px] font-bold text-stone-500">Semester</label>
                                        <select id="filter_semester" data-testid="filter-semester" onchange="applyLessonFilters()" class="w-full bg-white border border-stone-200 rounded-lg px-2 py-1 text-[11px] font-semibold text-stone-800 outline-none focus:border-orange-500">
                                            <option value="">All Semesters</option>
                                            <?php foreach ($filter_semesters as $sem): ?>
                                                <option value="<?php echo htmlspecialchars($sem); ?>"><?php echo htmlspecialchars($sem); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div>
                                        <label class="text-[9px] font-bold text-stone-500">School Year</label>
                                        <select id="filter_school_year" data-testid="filter-school-year" onchange="applyLessonFilters()" class="w-full bg-white border border-stone-200 rounded-lg px-2 py-1 text-[11px] font-semibold text-stone-800 outline-none focus:border-orange-500">
                                            <option value="">All School Years</option>
                                            <?php foreach ($filter_school_years as $sy): ?>
                                                <option value="<?php echo htmlspecialchars($sy); ?>"><?php echo htmlspecialchars($sy); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div>
                                        <label class="text-[9px] font-bold text-stone-500">Academic Period</label>
                                        <select id="filter_academic_period" data-testid="filter-academic-period" onchange="applyLessonFilters()" class="w-full bg-white border border-stone-200 rounded-lg px-2 py-1 text-[11px] font-semibold text-stone-800 outline-none focus:border-orange-500">
                                            <option value="">All Periods</option>
                                            <option value="general">General</option>
                                            <option value="prelim">Prelim</option>
                                            <option value="midterm">Midterm</option>
                                            <option value="finals">Finals</option>
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <div class="space-y-1">
                                <label class="text-[10px] font-bold text-stone-500 uppercase tracking-wider">Quick Select Controls</label>
                                <div class="flex flex-wrap gap-1.5">
                                    <button type="button" onclick="quickSelect('general')" data-testid="select-all-general" class="px-2 py-1 bg-stone-200 hover:bg-stone-300 text-stone-800 rounded-md text-[10px] font-bold transition-all shadow-xs">
                                        Select All General
                                    </button>
                                    <button type="button" onclick="quickSelect('prelim')" data-testid="select-all-prelim" class="px-2 py-1 bg-blue-100 hover:bg-blue-200 text-blue-800 rounded-md text-[10px] font-bold transition-all shadow-xs">
                                        Select All Prelim
                                    </button>
                                    <button type="button" onclick="quickSelect('midterm')" data-testid="select-all-midterm" class="px-2 py-1 bg-amber-100 hover:bg-amber-200 text-amber-800 rounded-md text-[10px] font-bold transition-all shadow-xs">
                                        Select All Midterm
                                    </button>
                                    <button type="button" onclick="quickSelect('finals')" data-testid="select-all-finals" class="px-2 py-1 bg-purple-100 hover:bg-purple-200 text-purple-800 rounded-md text-[10px] font-bold transition-all shadow-xs">
                                        Select All Finals
                                    </button>
                                    <button type="button" onclick="quickSelect('visible')" data-testid="select-all-visible" class="px-2 py-1 bg-emerald-100 hover:bg-emerald-200 text-emerald-800 rounded-md text-[10px] font-bold transition-all shadow-xs">
                                        Select All Visible
                                    </button>
                                    <button type="button" onclick="clearSelection()" data-testid="clear-selection" class="px-2 py-1 bg-rose-100 hover:bg-rose-200 text-rose-800 rounded-md text-[10px] font-bold transition-all shadow-xs">
                                        Clear Selection
                                    </button>
                                </div>
                            </div>

                            <?php if (!empty($all_teacher_lessons)): ?>
                                <div class="max-h-60 overflow-y-auto border border-stone-200 rounded-xl bg-stone-50 p-2.5 space-y-4 text-xs custom-scrollbar">
                                    <?php foreach (['general' => 'General', 'prelim' => 'Prelim', 'midterm' => 'Midterm', 'finals' => 'Finals'] as $periodKey => $periodTitle): ?>
                                        <div class="period-group-block space-y-2" data-period="<?php echo $periodKey; ?>" data-testid="period-group-<?php echo $periodKey; ?>">
                                            <div class="flex items-center justify-between border-b border-stone-200 pb-1">
                                                <h4 class="text-xs font-black uppercase tracking-wider text-stone-700 flex items-center gap-1.5">
                                                    <?php
                                                    $badgeClass = match($periodKey) {
                                                        'prelim' => 'bg-blue-600 text-white',
                                                        'midterm' => 'bg-amber-600 text-white',
                                                        'finals' => 'bg-purple-600 text-white',
                                                        default => 'bg-stone-600 text-white'
                                                    };
                                                    ?>
                                                    <span class="px-1.5 py-0.5 rounded text-[9px] font-black <?php echo $badgeClass; ?>"><?php echo strtoupper($periodKey); ?></span>
                                                    <span><?php echo $periodTitle; ?></span>
                                                </h4>
                                                <span class="text-[10px] font-bold text-stone-400 period-count"><?php echo count($lessons_by_period[$periodKey]); ?> items</span>
                                            </div>

                                            <?php if (!empty($lessons_by_period[$periodKey])): ?>
                                                <div class="space-y-1.5">
                                                    <?php foreach ($lessons_by_period[$periodKey] as $cl): 
                                                        $isCompleted = ($cl['processing_status'] ?? '') === 'completed';
                                                        $hasContent = !empty(trim($cl['lesson_text'] ?? ''));
                                                        $canSelect = $isCompleted && $hasContent;
                                                    ?>
                                                        <div class="lesson-card p-2 bg-white border border-stone-200 rounded-lg space-y-1 transition-all" 
                                                             data-testid="lesson-card-<?php echo $cl['id']; ?>"
                                                             data-id="<?php echo $cl['id']; ?>"
                                                             data-subject="<?php echo htmlspecialchars($cl['subject'] ?? ''); ?>"
                                                             data-year-level="<?php echo htmlspecialchars($cl['year_level'] ?? ''); ?>"
                                                             data-program="<?php echo htmlspecialchars($cl['program'] ?? ''); ?>"
                                                             data-semester="<?php echo htmlspecialchars($cl['semester'] ?? ''); ?>"
                                                             data-school-year="<?php echo htmlspecialchars($cl['school_year'] ?? ''); ?>"
                                                             data-academic-period="<?php echo htmlspecialchars($cl['academic_period'] ?? 'general'); ?>">

                                                            <div class="flex items-start justify-between gap-2">
                                                                <label class="flex items-start gap-2 cursor-pointer font-bold text-stone-800 text-xs truncate flex-grow">
                                                                    <input type="checkbox" 
                                                                           name="selected_lessons[]" 
                                                                           value="<?php echo $cl['id']; ?>" 
                                                                           data-testid="lesson-checkbox-<?php echo $cl['id']; ?>"
                                                                           data-period="<?php echo $periodKey; ?>"
                                                                           <?php echo $canSelect ? '' : 'disabled'; ?>
                                                                           class="lesson-checkbox accent-orange-600 rounded mt-0.5">
                                                                    <span data-testid="lesson-title-<?php echo $cl['id']; ?>" class="truncate leading-tight <?php echo $canSelect ? '' : 'text-stone-400 line-through'; ?>">
                                                                        <?php echo htmlspecialchars($cl['title']); ?>
                                                                    </span>
                                                                </label>

                                                                <?php if ($isCompleted && $hasContent): ?>
                                                                    <span data-testid="lesson-status-<?php echo $cl['id']; ?>" class="text-[9px] font-extrabold text-emerald-700 bg-emerald-50 border border-emerald-200 px-1.5 py-0.5 rounded flex-shrink-0">
                                                                        Completed
                                                                    </span>
                                                                <?php elseif (!$isCompleted): ?>
                                                                    <span data-testid="lesson-status-<?php echo $cl['id']; ?>" class="text-[9px] font-extrabold text-amber-700 bg-amber-50 border border-amber-200 px-1.5 py-0.5 rounded flex-shrink-0">
                                                                        <?php echo ucfirst($cl['processing_status'] ?? 'Processing'); ?>
                                                                    </span>
                                                                <?php else: ?>
                                                                    <span data-testid="lesson-status-<?php echo $cl['id']; ?>" class="text-[9px] font-extrabold text-rose-700 bg-rose-50 border border-rose-200 px-1.5 py-0.5 rounded flex-shrink-0">
                                                                        Empty
                                                                    </span>
                                                                <?php endif; ?>
                                                            </div>

                                                            <div class="flex flex-wrap items-center gap-1.5 text-[10px] text-stone-500 font-medium">
                                                                <span class="bg-stone-100 px-1.5 py-0.5 rounded border border-stone-200" data-testid="lesson-subject-<?php echo $cl['id']; ?>">
                                                                    <i class="fa-solid fa-book text-stone-400 mr-0.5"></i><?php echo htmlspecialchars($cl['subject'] ?? 'General'); ?>
                                                                </span>
                                                                <?php if (!empty($cl['semester'])): ?>
                                                                    <span class="bg-stone-100 px-1.5 py-0.5 rounded border border-stone-200" data-testid="lesson-semester-<?php echo $cl['id']; ?>">
                                                                        <?php echo htmlspecialchars($cl['semester']); ?>
                                                                    </span>
                                                                <?php endif; ?>
                                                                <?php if (!empty($cl['school_year'])): ?>
                                                                    <span class="bg-stone-100 px-1.5 py-0.5 rounded border border-stone-200" data-testid="lesson-school-year-<?php echo $cl['id']; ?>">
                                                                        <?php echo htmlspecialchars($cl['school_year']); ?>
                                                                    </span>
                                                                <?php endif; ?>
                                                                <?php if (!empty($cl['year_level'])): ?>
                                                                    <span class="bg-stone-100 px-1.5 py-0.5 rounded border border-stone-200" data-testid="lesson-year-level-<?php echo $cl['id']; ?>">
                                                                        <?php echo htmlspecialchars($cl['year_level']); ?>
                                                                    </span>
                                                                <?php endif; ?>
                                                                <?php if (!empty($cl['program'])): ?>
                                                                    <span class="bg-stone-100 px-1.5 py-0.5 rounded border border-stone-200" data-testid="lesson-program-<?php echo $cl['id']; ?>">
                                                                        <?php echo htmlspecialchars($cl['program']); ?>
                                                                    </span>
                                                                <?php endif; ?>
                                                            </div>
                                                        </div>
                                                    <?php endforeach; ?>
                                                </div>
                                            <?php else: ?>
                                                <p class="text-[11px] text-stone-400 italic px-2">No materials uploaded for this period.</p>
                                            <?php endif; ?>
                                        </div>
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

                        function applyLessonFilters() {
                            const subj = document.getElementById('filter_subject').value.toLowerCase();
                            const yl = document.getElementById('filter_year_level').value.toLowerCase();
                            const prog = document.getElementById('filter_program').value.toLowerCase();
                            const sem = document.getElementById('filter_semester').value.toLowerCase();
                            const sy = document.getElementById('filter_school_year').value.toLowerCase();
                            const period = document.getElementById('filter_academic_period').value.toLowerCase();

                            document.querySelectorAll('.lesson-card').forEach(card => {
                                const cSubj = (card.getAttribute('data-subject') || '').toLowerCase();
                                const cYl = (card.getAttribute('data-year-level') || '').toLowerCase();
                                const cProg = (card.getAttribute('data-program') || '').toLowerCase();
                                const cSem = (card.getAttribute('data-semester') || '').toLowerCase();
                                const cSy = (card.getAttribute('data-school-year') || '').toLowerCase();
                                const cPeriod = (card.getAttribute('data-academic-period') || 'general').toLowerCase();

                                let match = true;
                                if (subj && cSubj !== subj) match = false;
                                if (yl && cYl !== yl) match = false;
                                if (prog && cProg !== prog) match = false;
                                if (sem && cSem !== sem) match = false;
                                if (sy && cSy !== sy) match = false;
                                if (period && cPeriod !== period) match = false;

                                if (match) {
                                    card.classList.remove('hidden');
                                } else {
                                    card.classList.add('hidden');
                                }
                            });

                            document.querySelectorAll('.period-group-block').forEach(group => {
                                const visibleCards = group.querySelectorAll('.lesson-card:not(.hidden)');
                                const gPeriod = group.getAttribute('data-period');
                                if (period && gPeriod !== period) {
                                    group.classList.add('hidden');
                                } else if (visibleCards.length === 0 && (subj || yl || prog || sem || sy || period)) {
                                    group.classList.add('hidden');
                                } else {
                                    group.classList.remove('hidden');
                                }
                            });
                        }

                        function resetLessonFilters() {
                            if (document.getElementById('filter_subject')) document.getElementById('filter_subject').value = '';
                            if (document.getElementById('filter_year_level')) document.getElementById('filter_year_level').value = '';
                            if (document.getElementById('filter_program')) document.getElementById('filter_program').value = '';
                            if (document.getElementById('filter_semester')) document.getElementById('filter_semester').value = '';
                            if (document.getElementById('filter_school_year')) document.getElementById('filter_school_year').value = '';
                            if (document.getElementById('filter_academic_period')) document.getElementById('filter_academic_period').value = '';
                            applyLessonFilters();
                        }

                        function quickSelect(target) {
                            document.querySelectorAll('.lesson-card').forEach(card => {
                                if (card.classList.contains('hidden')) return;

                                const checkbox = card.querySelector('.lesson-checkbox');
                                if (!checkbox || checkbox.disabled) return;

                                const cPeriod = (card.getAttribute('data-academic-period') || 'general').toLowerCase();

                                if (target === 'visible') {
                                    checkbox.checked = true;
                                } else if (target === cPeriod) {
                                    checkbox.checked = true;
                                }
                            });
                        }

                        function clearSelection() {
                            document.querySelectorAll('.lesson-checkbox').forEach(cb => {
                                cb.checked = false;
                            });
                        }
                    </script>
                </div>

                
                <div class="lg:col-span-7 space-y-4">
                    <?php if (!empty($generated_questions)): ?>
                        <!-- Generation Audit Summary View (Repair Prompt 5) -->
                        <div class="bg-stone-900 text-white border border-stone-800 rounded-2xl p-4 shadow-sm space-y-3" data-testid="generation-audit-summary">
                            <div class="flex items-center justify-between border-b border-stone-800 pb-2">
                                <div class="flex items-center gap-2">
                                    <i class="fa-solid fa-square-poll-vertical text-orange-400 text-sm"></i>
                                    <span class="text-xs font-black uppercase tracking-wider text-stone-200">AI Batch Audit Summary</span>
                                </div>
                                <span class="text-[10px] font-mono bg-stone-800 text-stone-300 px-2 py-0.5 rounded border border-stone-700">
                                    ID: <?php echo htmlspecialchars(substr($ai_meta_output['generation_batch_id'] ?? '', 0, 12)); ?>
                                </span>
                            </div>
                            <div class="grid grid-cols-2 sm:grid-cols-4 gap-2 text-[11px]">
                                <div class="bg-stone-800/60 p-2 rounded-xl border border-stone-700/50">
                                    <span class="text-[9px] font-bold text-stone-400 block uppercase">Lessons Covered</span>
                                    <span class="font-extrabold text-orange-400" data-testid="audit-lesson-count"><?php echo count($ai_meta_output['lesson_ids'] ?? []); ?> Materials</span>
                                </div>
                                <div class="bg-stone-800/60 p-2 rounded-xl border border-stone-700/50">
                                    <span class="text-[9px] font-bold text-stone-400 block uppercase">Periods Covered</span>
                                    <span class="font-extrabold text-stone-200" data-testid="audit-periods"><?php echo htmlspecialchars(implode(', ', array_map('ucfirst', $ai_meta_output['covered_periods'] ?? [])) ?: 'General'); ?></span>
                                </div>
                                <div class="bg-stone-800/60 p-2 rounded-xl border border-stone-700/50">
                                    <span class="text-[9px] font-bold text-stone-400 block uppercase">Context Tokens</span>
                                    <span class="font-extrabold text-emerald-400" data-testid="audit-tokens"><?php echo number_format($ai_meta_output['estimated_tokens'] ?? 0); ?> Tokens</span>
                                </div>
                                <div class="bg-stone-800/60 p-2 rounded-xl border border-stone-700/50">
                                    <span class="text-[9px] font-bold text-stone-400 block uppercase">Generation Time</span>
                                    <span class="font-extrabold text-stone-200" data-testid="audit-time"><?php echo number_format(($ai_meta_output['generation_time_ms'] ?? 0) / 1000, 2); ?>s</span>
                                </div>
                            </div>
                            <?php if (!empty($ai_meta_output['generation_warnings'])): ?>
                                <div class="p-2 bg-amber-950/60 border border-amber-800/60 rounded-xl text-amber-300 text-[10px] space-y-0.5">
                                    <span class="font-bold flex items-center gap-1"><i class="fa-solid fa-triangle-exclamation"></i> Batch Warnings:</span>
                                    <ul class="list-disc pl-4 space-y-0.5">
                                        <?php foreach ($ai_meta_output['generation_warnings'] as $w): ?>
                                            <li><?php echo htmlspecialchars($w); ?></li>
                                        <?php endforeach; ?>
                                    </ul>
                                </div>
                            <?php endif; ?>
                        </div>

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
                                <?php foreach ($generated_questions as $idx => $item): 
                                    $itemSrcIds = !empty($item['source_lesson_ids']) ? (array)$item['source_lesson_ids'] : ($ai_meta_output['lesson_ids'] ?? []);
                                    $itemPeriod = $item['source_academic_period'] ?? ($ai_meta_output['covered_periods'][0] ?? 'general');
                                    $itemTopic = $item['source_topic'] ?? $_POST['subject'];
                                    $itemConf = $item['source_confidence'] ?? 'high';
                                ?>
                                    <div class="p-4 border border-stone-200 rounded-2xl bg-stone-50/40 space-y-3 hover:border-orange-300 transition-all" data-testid="generated-question-item" data-lesson-id="<?php echo htmlspecialchars($itemSrcIds[0] ?? ''); ?>">
                                        <div class="flex items-center justify-between flex-wrap gap-2">
                                            <div class="flex items-center gap-2">
                                                <span class="font-black text-xs text-stone-800 bg-white px-2.5 py-1 rounded-lg border border-stone-200">Item #<?php echo $idx + 1; ?></span>
                                                <span class="text-[10px] font-bold uppercase text-stone-400 bg-white px-2 py-0.5 rounded-md" data-testid="question-type"><?php echo htmlspecialchars($item['type']); ?></span>
                                            </div>

                                            <!-- Repair Prompt 3: Question Attribution Badge -->
                                            <div class="flex items-center gap-1.5 text-[10px]" data-testid="question-source-attribution">
                                                <span class="bg-blue-100 text-blue-800 font-extrabold px-2 py-0.5 rounded-full uppercase" data-testid="source-period">
                                                    <?php echo htmlspecialchars($itemPeriod); ?>
                                                </span>
                                                <span class="bg-stone-200 text-stone-700 font-bold px-2 py-0.5 rounded-full truncate max-w-[120px]" data-testid="source-topic">
                                                    <?php echo htmlspecialchars($itemTopic); ?>
                                                </span>
                                                <?php if ($itemConf === 'high'): ?>
                                                    <span class="bg-emerald-100 text-emerald-800 font-extrabold px-1.5 py-0.5 rounded-full" data-testid="source-confidence">
                                                        <i class="fa-solid fa-circle-check mr-0.5"></i> High Grounding
                                                    </span>
                                                <?php else: ?>
                                                    <span class="bg-amber-100 text-amber-800 font-extrabold px-1.5 py-0.5 rounded-full" data-testid="source-confidence">
                                                        <i class="fa-solid fa-triangle-exclamation mr-0.5"></i> Review Required
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                        </div>

                                        <textarea name="questions[<?php echo $idx; ?>][text]" data-testid="question-text" rows="2" class="w-full bg-white border border-stone-200 rounded-lg p-2.5 text-xs outline-none focus:border-orange-500 resize-none font-medium text-stone-800"><?php echo htmlspecialchars($item['question']); ?></textarea>
                                        <input type="hidden" name="questions[<?php echo $idx; ?>][type]" value="<?php echo htmlspecialchars($item['type']); ?>">
                                        <input type="hidden" name="questions[<?php echo $idx; ?>][points]" value="<?php echo htmlspecialchars($item['points'] ?? 1); ?>" data-testid="question-points">
                                        <input type="hidden" name="questions[<?php echo $idx; ?>][source_lesson_ids]" value="<?php echo htmlspecialchars(implode(',', $itemSrcIds)); ?>">
                                        <input type="hidden" name="questions[<?php echo $idx; ?>][source_topic]" value="<?php echo htmlspecialchars($itemTopic); ?>">
                                        <input type="hidden" name="questions[<?php echo $idx; ?>][source_academic_period]" value="<?php echo htmlspecialchars($itemPeriod); ?>">
                                        <input type="hidden" name="questions[<?php echo $idx; ?>][source_confidence]" value="<?php echo htmlspecialchars($itemConf); ?>">

                                        <?php if ($item['type'] === 'multiple_choice'): ?>
                                            <div class="grid grid-cols-2 gap-2 text-xs" data-testid="mcq-options">
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
                                            <input type="text" name="questions[<?php echo $idx; ?>][correct]" data-testid="answer-key" value="<?php echo htmlspecialchars($item['correct_answer']); ?>" class="w-full bg-emerald-50 border border-emerald-200 rounded-lg p-2 text-xs font-bold text-emerald-700 outline-none focus:border-emerald-500 mt-1">
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