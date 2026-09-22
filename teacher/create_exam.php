<?php
require_once __DIR__ . '/../app/bootstrap.php';

AuthService::enforceRole('teacher');
$pdo = getDBConnection();

$success_msg = "";
$error_msg = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_exam'])) {
    validateCSRFToken();
    $delete_exam_id = intval($_POST['delete_exam_id'] ?? 0);
    if ($delete_exam_id > 0) {
        $stmtCheck = $pdo->prepare("SELECT id, title, teacher_id FROM exams WHERE id = ?");
        $stmtCheck->execute([$delete_exam_id]);
        $examToDelete = $stmtCheck->fetch(PDO::FETCH_ASSOC);
        if ($examToDelete && ($examToDelete['teacher_id'] == $_SESSION['user_id'] || ($_SESSION['role'] ?? '') === 'admin')) {
            ExamService::archiveExam($delete_exam_id, getCurrentUserId());
            logActivity("Archived exam '{$examToDelete['title']}' (ID: {$delete_exam_id}) from active question bank; history preserved.");
            $success_msg = "Exam '{$examToDelete['title']}' archived successfully. Completed student records are preserved.";
        } else {
            $error_msg = "Unauthorized: You can only delete exams created by your account.";
        }
    } else {
        $error_msg = "Invalid exam ID for deletion.";
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_exam'])) {
    validateCSRFToken();
    $title = trim($_POST['title']);
    $subject = trim($_POST['subject']);
    $specialization = trim($_POST['specialization'] ?? 'Structural Engineering');
    $exam_category = trim($_POST['exam_category'] ?? 'regular');
    $qualifying_passing_percentage = floatval($_POST['qualifying_passing_percentage'] ?? 80.00);
    $qualifying_max_attempts = intval($_POST['qualifying_max_attempts'] ?? 1);
    $qualifying_year_level = trim($_POST['qualifying_year_level'] ?? 'All Year Levels');
    $qualifying_program = trim($_POST['qualifying_program'] ?? 'All Programs');
    $qualifying_is_required = intval($_POST['qualifying_is_required'] ?? 1);
    $qualifying_unlock_date = !empty($_POST['qualifying_unlock_date']) ? $_POST['qualifying_unlock_date'] : null;
    $qualifying_deadline = !empty($_POST['qualifying_deadline']) ? $_POST['qualifying_deadline'] : null;
    $time_limit = max(1, intval($_POST['time_limit'] ?? 60));
    
    $questions = $_POST['questions'] ?? [];

    if (!empty($title) && !empty($subject) && !empty($questions)) {
        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare("
                INSERT INTO exams (
                    teacher_id, title, subject, specialization, time_limit, total_items,
                    exam_category, qualifying_passing_percentage, qualifying_max_attempts,
                    qualifying_year_level, qualifying_program, qualifying_is_required,
                    qualifying_unlock_date, qualifying_deadline
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $_SESSION['user_id'], $title, $subject, $specialization, $time_limit, count($questions),
                $exam_category, $qualifying_passing_percentage, $qualifying_max_attempts,
                $qualifying_year_level, $qualifying_program, $qualifying_is_required,
                $qualifying_unlock_date, $qualifying_deadline
            ]);
            $exam_id = $pdo->lastInsertId();
            $examTerm = StudentResultService::normalizeTerm($exam_category) ?? StudentResultService::normalizeTerm($_POST['term'] ?? '');
            if ($examTerm === null) throw new InvalidArgumentException('Select a valid examination term.');
            $pdo->prepare("UPDATE exams SET term = ? WHERE id = ?")->execute([$examTerm, $exam_id]);

            
            $qStmt = $pdo->prepare("
                INSERT INTO exam_questions 
                (exam_id, question_text, question_type, option_a, option_b, option_c, option_d, correct_answer, formula_latex, matching_pairs, points) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            foreach ($questions as $q) {
                // Server-side validation before saving
                GroqService::validateQuestionItem($q);

                $qType = strtolower(trim($q['type'] ?? 'multiple_choice'));
                if ($qType === 'fill_in_the_blank') $qType = 'fill_blank';
                if ($qType === 'matching_type') $qType = 'matching';

                $cleanMatchingPairs = null;
                if (!empty($q['matching_pairs'])) {
                    if (is_array($q['matching_pairs'])) {
                        $cleanMatchingPairs = json_encode($q['matching_pairs']);
                    } elseif (is_string($q['matching_pairs'])) {
                        $trimmed = trim($q['matching_pairs']);
                        if ($trimmed !== '' && $trimmed !== 'null') {
                            $dec = json_decode($trimmed, true);
                            if (json_last_error() === JSON_ERROR_NONE && is_array($dec) && !empty($dec)) {
                                $cleanMatchingPairs = json_encode($dec);
                            }
                        }
                    }
                }

                $cleanFormula = (!empty($q['formula_latex']) && trim((string)$q['formula_latex']) !== '') ? trim((string)$q['formula_latex']) : null;

                $qStmt->execute([
                    $exam_id,
                    trim(sanitizeInput($q['text'] ?? '')),
                    $qType,
                    $q['opt_a'] ?? null,
                    $q['opt_b'] ?? null,
                    $q['opt_c'] ?? null,
                    $q['opt_d'] ?? null,
                    trim(sanitizeInput($q['correct'] ?? '')),
                    $cleanFormula,
                    $cleanMatchingPairs,
                    max(1, intval($q['points'] ?? 1))
                ]);
            }

            $pdo->commit();
            logActivity("Created exam paper '{$title}' (" . count($questions) . " items) under {$specialization}.");
            $success_msg = "Exam and Answer Key for '{$specialization}' created successfully!";
        } catch (Exception $e) {
            $pdo->rollBack();
            $error_msg = "Failed to save exam: " . $e->getMessage();
        }
    } else {
        $error_msg = "Please fill in all details and add at least one question.";
    }
}

$stmtExams = $pdo->prepare("
    SELECT e.*, u.fullname as teacher_name 
    FROM exams e 
    LEFT JOIN users u ON e.teacher_id = u.id 
    WHERE e.status <> 'archived' AND (e.teacher_id = ? OR e.teacher_id IN (SELECT id FROM users WHERE role IN ('teacher', 'admin')) OR e.is_demo = 1)
    ORDER BY (e.teacher_id = ?) DESC, e.id DESC
");
$stmtExams->execute([$_SESSION['user_id'], $_SESSION['user_id']]);
$existing_exams = $stmtExams->fetchAll(PDO::FETCH_ASSOC);

$qStmt = $pdo->prepare("SELECT * FROM exam_questions WHERE exam_id = ? ORDER BY id ASC");
foreach ($existing_exams as &$ex) {
    $qStmt->execute([$ex['id']]);
    $ex['questions'] = $qStmt->fetchAll(PDO::FETCH_ASSOC);
}
unset($ex);

// Question Bank: Accumulated questions with usage count
$bank_questions = [];
try {
    $stmtBank = $pdo->prepare("
        SELECT 
            MIN(q.id) as sample_id,
            q.question_text,
            q.question_type,
            MAX(q.option_a) as option_a,
            MAX(q.option_b) as option_b,
            MAX(q.option_c) as option_c,
            MAX(q.option_d) as option_d,
            MAX(q.correct_answer) as correct_answer,
            MAX(q.formula_latex) as formula_latex,
            MAX(q.points) as points,
            COUNT(DISTINCT q.exam_id) as usage_count,
            MAX(e.subject) as latest_subject,
            MAX(e.title) as latest_exam_title
        FROM exam_questions q
        JOIN exams e ON q.exam_id = e.id
        WHERE e.status <> 'archived' AND (e.teacher_id = ? OR e.teacher_id IN (SELECT id FROM users WHERE role IN ('teacher', 'admin')) OR e.is_demo = 1)
        GROUP BY q.question_text, q.question_type
        ORDER BY usage_count DESC, sample_id DESC
    ");
    $stmtBank->execute([$_SESSION['user_id']]);
    $bank_questions = $stmtBank->fetchAll(PDO::FETCH_ASSOC) ?: [];
} catch (Exception $e) {
    $bank_questions = [];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>QuestBank - Create Exam & Question Bank</title>
    
    <script src="https://cdn.tailwindcss.com"></script>
    
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
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
                    <h1 class="text-2xl font-extrabold text-stone-800 mt-2"><i class="fa-solid fa-file-circle-plus text-orange-600 mr-1"></i> Create Civil Engineering Exam</h1>
                    <p class="text-xs text-stone-400">Design tests, assign answer keys, and organize items by Civil Engineering specialization.</p>
                </div>
            </div>

            <?php if (!empty($success_msg)): ?>
                <div class="bg-emerald-50 border-l-4 border-emerald-500 p-4 rounded-xl text-xs font-semibold text-emerald-700"><?php echo $success_msg; ?></div>
            <?php endif; ?>
            <?php if (!empty($error_msg)): ?>
                <div class="bg-red-50 border-l-4 border-red-500 p-4 rounded-xl text-xs font-semibold text-red-700"><?php echo $error_msg; ?></div>
            <?php endif; ?>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 items-start">
                
                
                <div class="lg:col-span-2 bg-white border border-stone-200 rounded-2xl p-6 shadow-sm space-y-6 h-fit">
                    <form action="create_exam.php" method="POST" id="examForm" class="space-y-6">
                        <?php echo csrfInputField(); ?>

                        <div class="border-b pb-4">
                            <h3 class="text-sm font-bold uppercase tracking-wider text-stone-700"><i class="fa-solid fa-sliders text-orange-500 mr-1"></i> Exam Parameters</h3>
                        </div>

                        <?php
                            $stmtT = $pdo->prepare("SELECT handled_subject FROM users WHERE id = ?");
                            $stmtT->execute([getCurrentUserId()]);
                            $teacher_handled_subject = $stmtT->fetchColumn() ?: 'CE 412 - Structural Theory & Design';
                        ?>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div class="space-y-1">
                                <label class="text-xs font-bold text-stone-600">Exam Title</label>
                                <input type="text" name="title" required placeholder="e.g. Midterm Assessment" class="w-full bg-stone-50 border border-stone-200 rounded-xl px-4 py-2 text-xs outline-none focus:border-orange-500">
                            </div>
                            <div class="space-y-1">
                                <label class="text-xs font-bold text-stone-600">Subject</label>
                                <input type="text" name="subject" value="<?php echo htmlspecialchars($teacher_handled_subject); ?>" required placeholder="e.g. CE 412 - Structural Theory" class="w-full bg-stone-50 border border-stone-200 rounded-xl px-4 py-2 text-xs font-bold outline-none focus:border-orange-500">
                            </div>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div class="space-y-1">
                                <label class="text-xs font-bold text-stone-600">Civil Engineering Branch</label>
                                <select name="specialization" required class="w-full bg-stone-50 border border-stone-200 rounded-xl px-4 py-2 text-xs outline-none focus:border-orange-500">
                                    <?php foreach (getCivilEngineeringSpecializations() as $key => $label): ?>
                                        <option value="<?php echo htmlspecialchars($key); ?>"><?php echo htmlspecialchars($label); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="space-y-1">
                                <label class="text-xs font-bold text-stone-600">Time Limit (Minutes)</label>
                                <input type="number" name="time_limit" value="60" required class="w-full bg-stone-50 border border-stone-200 rounded-xl px-4 py-2 text-xs outline-none focus:border-orange-500">
                            </div>
                        </div>

                        <div class="space-y-1">
                            <label class="text-xs font-bold text-stone-600">Examination Term</label>
                            <select name="term" required class="w-full bg-stone-50 border border-stone-200 rounded-xl px-4 py-2 text-xs">
                                <option value="">Select term</option><option>Prelim</option><option>Midterm</option><option>Finals</option>
                            </select>
                        </div>
                        <div class="space-y-1">
                            <label class="text-xs font-bold text-stone-600">Exam Category</label>
                            <select name="exam_category" id="exam_category_select" onchange="toggleQualifyingFields(this.value)" class="w-full bg-stone-50 border border-stone-200 rounded-xl px-4 py-2 text-xs outline-none focus:border-orange-500 font-semibold text-stone-800">
                                <option value="regular">Regular Exam</option>
                                <option value="quiz">Quiz</option>
                                <option value="prelim">Prelim</option>
                                <option value="midterm">Midterm</option>
                                <option value="finals">Finals</option>
                                <option value="qualifying">Qualifying Exam</option>
                            </select>
                        </div>

                        
                        <div id="qualifying_config_panel" class="hidden p-4 bg-orange-50/60 border border-orange-200 rounded-xl space-y-4">
                            <div class="flex items-center gap-2 border-b border-orange-200 pb-2">
                                <i class="fa-solid fa-award text-orange-600"></i>
                                <h4 class="text-xs font-extrabold text-orange-900 uppercase">Qualifying Examination Configuration</h4>
                            </div>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div>
                                    <label class="text-xs font-bold text-stone-700">Qualifying Passing Score (%)</label>
                                    <input type="number" step="0.01" name="qualifying_passing_percentage" value="80.00" min="1" max="100" class="w-full bg-white border border-stone-200 rounded-xl px-3 py-1.5 text-xs font-semibold">
                                </div>
                                <div>
                                    <label class="text-xs font-bold text-stone-700">Maximum Allowed Attempts</label>
                                    <input type="number" name="qualifying_max_attempts" value="1" min="1" max="10" class="w-full bg-white border border-stone-200 rounded-xl px-3 py-1.5 text-xs font-semibold">
                                </div>
                                <div>
                                    <label class="text-xs font-bold text-stone-700">Eligible Year Level</label>
                                    <select name="qualifying_year_level" class="w-full bg-white border border-stone-200 rounded-xl px-3 py-1.5 text-xs font-semibold">
                                        <option value="All Year Levels">All Year Levels</option>
                                        <option value="1st Year">1st Year</option>
                                        <option value="2nd Year">2nd Year</option>
                                        <option value="3rd Year">3rd Year</option>
                                        <option value="4th Year">4th Year</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="text-xs font-bold text-stone-700">Eligible Program</label>
                                     <select name="qualifying_program" class="w-full bg-white border border-stone-200 rounded-xl px-3 py-1.5 text-xs font-semibold">
                                         <option value="BSCE" selected>BSCE (Civil Engineering)</option>
                                     </select>
                                </div>
                                <div>
                                    <label class="text-xs font-bold text-stone-700">Requirement Status</label>
                                    <select name="qualifying_is_required" class="w-full bg-white border border-stone-200 rounded-xl px-3 py-1.5 text-xs font-semibold">
                                        <option value="1">Mandatory / Required</option>
                                        <option value="0">Optional / Voluntary</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="text-xs font-bold text-stone-700">Start / Unlock Date</label>
                                    <input type="datetime-local" name="qualifying_unlock_date" class="w-full bg-white border border-stone-200 rounded-xl px-3 py-1.5 text-xs">
                                </div>
                                <div class="sm:col-span-2">
                                    <label class="text-xs font-bold text-stone-700">Deadline Date</label>
                                    <input type="datetime-local" name="qualifying_deadline" class="w-full bg-white border border-stone-200 rounded-xl px-3 py-1.5 text-xs">
                                </div>
                            </div>
                        </div>

                        
                        <!-- Question Items Section with Recycling & Question Bank -->
                        <div class="space-y-4 pt-4 border-t">
                            <div class="flex items-center justify-between flex-wrap gap-2">
                                <div>
                                    <h3 class="text-sm font-bold uppercase tracking-wider text-stone-700 flex items-center gap-1.5">
                                        <i class="fa-solid fa-list-check text-orange-500"></i> Question Items
                                        <span id="form_items_count_badge" class="ml-1 text-[10px] bg-orange-100 text-orange-800 font-extrabold px-2.5 py-0.5 rounded-full">1 Item</span>
                                    </h3>
                                    <p class="text-[11px] text-stone-400">Design items manually or recycle questions directly from your past exams & Question Bank.</p>
                                </div>
                                <div class="flex items-center gap-2">
                                    <button type="button" onclick="openRecycleModal()" class="bg-orange-600 hover:bg-orange-700 text-white font-extrabold text-xs px-3.5 py-2 rounded-xl transition-all shadow-xs flex items-center gap-1.5 cursor-pointer" title="Recycle questions from previous exams">
                                        <i class="fa-solid fa-recycle"></i> Recycle from Past Exams / Bank
                                    </button>
                                    <button type="button" onclick="addQuestion()" class="bg-stone-900 hover:bg-stone-800 text-white font-bold text-xs px-3.5 py-2 rounded-xl transition-all cursor-pointer flex items-center gap-1">
                                        <i class="fa-solid fa-plus"></i> Blank Item
                                    </button>
                                </div>
                            </div>

                            <div id="questions_container" class="space-y-4">
                                
                            </div>
                        </div>

                        <button type="submit" name="save_exam" class="w-full bg-orange-600 hover:bg-orange-700 text-white font-bold text-xs py-3 rounded-xl shadow-md transition-all cursor-pointer">
                            <i class="fa-solid fa-floppy-disk mr-1"></i> Save Exam to Question Bank
                        </button>
                    </form>
                </div>

                <!-- Right Column: Saved Exams & Central Question Bank with Usage Counts -->
                <div class="bg-white border border-stone-200 rounded-2xl p-6 shadow-sm space-y-4 h-fit">
                    <!-- Tab Switcher -->
                    <div class="flex items-center gap-1.5 border-b border-stone-100 pb-3">
                        <button type="button" id="tab_btn_saved_exams" onclick="switchRightPanelTab('exams')" class="text-xs font-black uppercase tracking-wider px-3 py-1.5 rounded-xl bg-orange-600 text-white shadow-xs transition-all cursor-pointer">
                            <i class="fa-solid fa-folder-open mr-1"></i> Past Exams (<?php echo count($existing_exams); ?>)
                        </button>
                        <button type="button" id="tab_btn_question_bank" onclick="switchRightPanelTab('bank')" class="text-xs font-bold uppercase tracking-wider px-3 py-1.5 rounded-xl bg-stone-100 text-stone-600 hover:bg-stone-200 transition-all cursor-pointer">
                            <i class="fa-solid fa-database mr-1"></i> Question Bank (<?php echo count($bank_questions); ?>)
                        </button>
                    </div>

                    <!-- Panel 1: Saved Past Exams -->
                    <div id="panel_saved_exams" class="space-y-3">
                        <?php if (!empty($existing_exams)): ?>
                            <div class="space-y-3 max-h-[650px] overflow-y-auto pr-1 custom-scrollbar">
                                <?php foreach ($existing_exams as $ex): ?>
                                    <div class="p-3.5 border border-stone-200 rounded-xl bg-stone-50/50 hover:border-orange-500 hover:bg-orange-50/30 hover:shadow-md transition-all space-y-1.5 group relative">
                                        <div onclick="openExamPreviewModal(<?php echo htmlspecialchars(json_encode($ex), ENT_QUOTES, 'UTF-8'); ?>)" data-testid="saved-exam-item" data-exam-title="<?php echo htmlspecialchars($ex['title']); ?>" class="cursor-pointer">
                                            <div class="flex items-center justify-between">
                                                <h4 class="font-extrabold text-xs text-stone-800 group-hover:text-orange-600 transition-colors flex items-center gap-1.5">
                                                    <i class="fa-solid fa-folder-open text-orange-500"></i>
                                                    <?php echo htmlspecialchars($ex['title']); ?>
                                                </h4>
                                                <span class="text-[9px] bg-orange-100 text-orange-700 font-extrabold px-2.5 py-0.5 rounded-full shadow-2xs">
                                                    <?php echo $ex['total_items']; ?> Items
                                                </span>
                                            </div>
                                            <p class="text-[10px] text-stone-400 font-semibold mt-1.5"><?php echo htmlspecialchars($ex['subject']); ?></p>
                                        </div>
                                        <div class="flex items-center justify-between pt-1 border-t border-stone-100/80 mt-2 flex-wrap gap-1">
                                            <span class="inline-block text-[9px] font-bold text-orange-600 bg-orange-50 px-2 py-0.5 rounded border border-orange-200">
                                                <i class="fa-solid fa-compass-drafting mr-1"></i><?php echo htmlspecialchars($ex['specialization'] ?? 'Structural Engineering'); ?>
                                            </span>
                                            <div class="flex items-center gap-2">
                                                <button type="button" onclick="event.stopPropagation(); recycleAllFromExam(<?php echo htmlspecialchars(json_encode($ex), ENT_QUOTES, 'UTF-8'); ?>)" class="text-[10px] text-orange-600 hover:text-orange-700 font-extrabold transition-colors flex items-center gap-0.5" title="Recycle all questions into current form">
                                                    <i class="fa-solid fa-recycle text-[9px]"></i> Recycle
                                                </button>
                                                <a href="print_exam.php?id=<?php echo $ex['id']; ?>&amp;download=1" onclick="event.stopPropagation();" class="text-[10px] text-emerald-700 font-bold" title="Download student examination PDF">Download PDF</a>
                                                <a href="print_exam.php?id=<?php echo $ex['id']; ?>" target="_blank" onclick="event.stopPropagation();" class="text-[10px] text-stone-500 hover:text-orange-600 font-bold transition-colors flex items-center gap-1" title="Print Exam Paper">
                                                    <i class="fa-solid fa-print text-[9px]"></i> Print
                                                </a>
                                                <button type="button" onclick="event.stopPropagation(); deleteExam(<?php echo $ex['id']; ?>, '<?php echo htmlspecialchars(addslashes($ex['title']), ENT_QUOTES, 'UTF-8'); ?>')" class="text-[10px] text-rose-400 hover:text-rose-600 font-bold transition-colors flex items-center gap-1 opacity-0 group-hover:opacity-100" title="Delete Exam" aria-label="Delete <?php echo htmlspecialchars($ex['title']); ?>">
                                                    <i class="fa-solid fa-trash-can text-[9px]"></i> Delete
                                                </button>
                                                <span onclick="openExamPreviewModal(<?php echo htmlspecialchars(json_encode($ex), ENT_QUOTES, 'UTF-8'); ?>)" class="text-[10px] text-orange-600 font-bold group-hover:translate-x-0.5 transition-transform flex items-center gap-1 cursor-pointer">
                                                    View <i class="fa-solid fa-arrow-right text-[9px]"></i>
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <p class="text-xs text-stone-400 text-center py-6">No exams created yet.</p>
                        <?php endif; ?>
                    </div>

                    <!-- Panel 2: Central Question Bank / Word Bank Repository with Usage Frequency -->
                    <div id="panel_question_bank" class="space-y-3 hidden">
                        <div class="p-2.5 bg-orange-50/70 border border-orange-200 rounded-xl text-[11px] text-orange-950 flex items-center justify-between">
                            <span class="font-bold"><i class="fa-solid fa-database text-orange-600 mr-1"></i> Question Bank Repository</span>
                            <span class="text-[10px] font-black text-orange-700 uppercase">Usage Tracking</span>
                        </div>

                        <?php if (!empty($bank_questions)): ?>
                            <div class="space-y-2.5 max-h-[650px] overflow-y-auto pr-1 custom-scrollbar">
                                <?php foreach ($bank_questions as $bq): ?>
                                    <div class="p-3 border border-stone-200 rounded-xl bg-stone-50/50 hover:border-orange-400 hover:bg-orange-50/20 transition-all space-y-2">
                                        <div class="flex items-center justify-between gap-1">
                                            <span class="bg-amber-100 text-amber-900 border border-amber-300 text-[10px] font-black px-2 py-0.5 rounded-full flex items-center gap-1">
                                                <i class="fa-solid fa-repeat text-amber-700"></i> Used <?php echo intval($bq['usage_count']); ?>x in exams
                                            </span>
                                            <span class="text-[9px] bg-stone-200 text-stone-700 font-bold px-2 py-0.5 rounded uppercase">
                                                <?php echo htmlspecialchars(str_replace('_', ' ', $bq['question_type'])); ?>
                                            </span>
                                        </div>
                                        <p class="text-xs font-bold text-stone-800 leading-snug"><?php echo htmlspecialchars($bq['question_text']); ?></p>
                                        <div class="flex items-center justify-between pt-1 border-t border-stone-100 text-[10px]">
                                            <span class="text-emerald-700 font-bold truncate max-w-[150px]">
                                                Key: <?php echo htmlspecialchars($bq['correct_answer']); ?>
                                            </span>
                                            <button type="button" onclick="importRecycledQuestion(<?php echo htmlspecialchars(json_encode($bq), ENT_QUOTES, 'UTF-8'); ?>)" class="bg-orange-600 hover:bg-orange-700 text-white font-extrabold px-2.5 py-1 rounded-lg transition-all shadow-2xs flex items-center gap-1 cursor-pointer">
                                                <i class="fa-solid fa-plus text-[9px]"></i> Add to Form
                                            </button>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <p class="text-xs text-stone-400 text-center py-6">No questions accumulated in question bank yet.</p>
                        <?php endif; ?>
                    </div>

                </div>

            </div>
        </div>
    </main>

    
    <div id="qb_preview_modal" class="fixed inset-0 bg-stone-950/70 backdrop-blur-xs hidden items-center justify-center z-50 p-4">
        <div class="bg-white border border-stone-200 rounded-2xl max-w-2xl w-full p-6 space-y-5 shadow-2xl max-h-[90vh] overflow-y-auto custom-scrollbar">
            
            <div class="flex items-start justify-between border-b pb-4">
                <div>
                    <span id="modal_exam_badge" class="bg-orange-100 text-orange-700 text-[10px] font-black px-2.5 py-0.5 rounded-md uppercase">Structural Engineering</span>
                    <h3 id="modal_exam_title" class="text-lg font-black text-stone-800 mt-1">Exam Title</h3>
                    <p id="modal_exam_subtitle" class="text-xs text-stone-400 font-medium">Subject | 5 Questions | 60 mins</p>
                </div>
                <button onclick="closeExamPreviewModal()" class="w-8 h-8 rounded-xl bg-stone-100 hover:bg-stone-200 text-stone-500 font-bold flex items-center justify-center text-xs transition-all">
                    <i class="fa-solid fa-xmark text-sm"></i>
                </button>
            </div>

            <div id="modal_questions_list" class="space-y-4">
                
            </div>

            <div class="flex justify-between items-center pt-4 border-t border-stone-100 flex-wrap gap-2">
                <div class="flex items-center gap-2">
                    <button id="modal_recycle_all_btn" type="button" class="px-4 py-2 bg-orange-600 hover:bg-orange-700 text-white font-bold text-xs rounded-xl shadow-xs transition-all flex items-center gap-1.5 cursor-pointer">
                        <i class="fa-solid fa-recycle text-xs"></i> Recycle All Questions
                    </button>
                    <button id="modal_delete_btn" type="button" onclick="" class="px-4 py-2 bg-rose-50 hover:bg-rose-100 text-rose-600 border border-rose-200 font-bold text-xs rounded-xl shadow-xs transition-all flex items-center gap-1.5">
                        <i class="fa-solid fa-trash-can text-xs"></i> Delete Exam
                    </button>
                    <a id="modal_download_btn" href="#" class="px-4 py-2 bg-emerald-50 text-emerald-700 border border-emerald-200 font-bold text-xs rounded-xl">Download PDF</a>
                    <a id="modal_print_btn" href="#" target="_blank" class="px-4 py-2 bg-orange-50 hover:bg-orange-100 text-orange-600 border border-orange-200 font-bold text-xs rounded-xl shadow-xs transition-all flex items-center gap-1.5">
                        <i class="fa-solid fa-print text-xs"></i> Print Exam Paper
                    </a>
                </div>
                <button onclick="closeExamPreviewModal()" class="px-5 py-2.5 bg-stone-900 hover:bg-orange-600 text-white font-bold text-xs rounded-xl shadow-sm transition-all">
                    Close Preview
                </button>
            </div>

        </div>
    </div>

    <!-- Central Recycle Questions Modal (Browse by Exam / Bank with Usage Frequency) -->
    <div id="recycle_modal" class="fixed inset-0 bg-stone-950/70 backdrop-blur-xs hidden items-center justify-center z-50 p-4">
        <div class="bg-white border border-stone-200 rounded-2xl max-w-3xl w-full p-6 space-y-4 shadow-2xl max-h-[90vh] overflow-y-auto custom-scrollbar flex flex-col">
            <!-- Modal Header -->
            <div class="flex items-start justify-between border-b pb-3">
                <div>
                    <h3 class="text-base font-extrabold text-stone-800 flex items-center gap-2">
                        <i class="fa-solid fa-recycle text-orange-600"></i> Recycle Questions from Past Exams & Bank
                    </h3>
                    <p class="text-xs text-stone-400 mt-0.5">Import past exam questions with verified answer keys, formulas, and track how many times each question has been used.</p>
                </div>
                <button type="button" onclick="closeRecycleModal()" class="w-8 h-8 rounded-xl bg-stone-100 hover:bg-stone-200 text-stone-500 font-bold flex items-center justify-center text-xs transition-all cursor-pointer">
                    <i class="fa-solid fa-xmark text-sm"></i>
                </button>
            </div>

            <!-- Controls: Search + Exam Filter -->
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 bg-stone-50 p-3 rounded-xl border border-stone-200">
                <div class="sm:col-span-2 relative">
                    <i class="fa-solid fa-magnifying-glass absolute left-3 top-2.5 text-stone-400 text-xs"></i>
                    <input type="text" id="recycle_search_input" oninput="filterRecycleQuestions()" placeholder="Search questions by topic, formula, or keywords..." class="w-full bg-white border border-stone-200 rounded-lg pl-8 pr-3 py-1.5 text-xs outline-none focus:border-orange-500 font-medium">
                </div>
                <div>
                    <select id="recycle_exam_filter" onchange="filterRecycleQuestions()" class="w-full bg-white border border-stone-200 rounded-lg px-2.5 py-1.5 text-xs font-semibold text-stone-700 outline-none focus:border-orange-500">
                        <option value="all">All Past Exams & Bank</option>
                        <?php foreach ($existing_exams as $ex): ?>
                            <option value="<?php echo $ex['id']; ?>">Exam: <?php echo htmlspecialchars($ex['title']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <!-- Selection Action Header -->
            <div class="flex items-center justify-between text-xs px-1">
                <label class="flex items-center gap-2 font-bold text-stone-600 cursor-pointer">
                    <input type="checkbox" id="recycle_select_all" onchange="toggleSelectAllRecycle(this.checked)" class="accent-orange-600 rounded">
                    <span>Select All Visible</span>
                </label>
                <span id="recycle_selected_count" class="font-extrabold text-orange-600">0 questions selected</span>
            </div>

            <!-- Questions List Container -->
            <div id="recycle_modal_items_container" class="space-y-3 overflow-y-auto max-h-[380px] pr-1 custom-scrollbar">
                <!-- Populated dynamically via JS -->
            </div>

            <!-- Modal Footer -->
            <div class="flex items-center justify-between pt-3 border-t border-stone-100 mt-auto">
                <button type="button" onclick="closeRecycleModal()" class="px-4 py-2 bg-stone-100 hover:bg-stone-200 text-stone-600 font-bold text-xs rounded-xl transition-all cursor-pointer">
                    Cancel
                </button>
                <button type="button" onclick="importSelectedRecycleQuestions()" class="px-5 py-2.5 bg-orange-600 hover:bg-orange-700 text-white font-extrabold text-xs rounded-xl shadow-sm transition-all flex items-center gap-1.5 cursor-pointer">
                    <i class="fa-solid fa-cloud-arrow-down"></i> Import Selected Questions (<span id="btn_selected_badge">0</span>)
                </button>
            </div>
        </div>
    </div>

    <!-- Hidden form for deleting an exam from Saved Question Bank -->
    <form id="deleteExamForm" method="POST" action="create_exam.php" class="hidden">
        <?php echo csrfInputField(); ?>
        <input type="hidden" name="delete_exam" value="1">
        <input type="hidden" name="delete_exam_id" id="delete_exam_id" value="">
    </form>

    <script>
        let questionCount = 0;
        const allBankQuestions = <?php echo json_encode($bank_questions); ?>;
        const allExistingExams = <?php echo json_encode($existing_exams); ?>;

        function switchRightPanelTab(tab) {
            const panelExams = document.getElementById('panel_saved_exams');
            const panelBank = document.getElementById('panel_question_bank');
            const btnExams = document.getElementById('tab_btn_saved_exams');
            const btnBank = document.getElementById('tab_btn_question_bank');

            if (tab === 'exams') {
                panelExams.classList.remove('hidden');
                panelBank.classList.add('hidden');
                btnExams.className = 'text-xs font-black uppercase tracking-wider px-3 py-1.5 rounded-xl bg-orange-600 text-white shadow-xs transition-all cursor-pointer';
                btnBank.className = 'text-xs font-bold uppercase tracking-wider px-3 py-1.5 rounded-xl bg-stone-100 text-stone-600 hover:bg-stone-200 transition-all cursor-pointer';
            } else {
                panelExams.classList.add('hidden');
                panelBank.classList.remove('hidden');
                btnBank.className = 'text-xs font-black uppercase tracking-wider px-3 py-1.5 rounded-xl bg-orange-600 text-white shadow-xs transition-all cursor-pointer';
                btnExams.className = 'text-xs font-bold uppercase tracking-wider px-3 py-1.5 rounded-xl bg-stone-100 text-stone-600 hover:bg-stone-200 transition-all cursor-pointer';
            }
        }

        function updateFormItemsCountBadge() {
            const container = document.getElementById('questions_container');
            const total = container ? container.querySelectorAll('[id^="q_block_"]').length : 0;
            const badge = document.getElementById('form_items_count_badge');
            if (badge) {
                badge.innerText = `${total} Item${total === 1 ? '' : 's'}`;
            }
        }

        function toggleQualifyingFields(val) {
            const panel = document.getElementById('qualifying_config_panel');
            if (panel) {
                if (val === 'qualifying') {
                    panel.classList.remove('hidden');
                } else {
                    panel.classList.add('hidden');
                }
            }
        }

        function addQuestion() {
            questionCount++;
            const container = document.getElementById('questions_container');
            
            const qBlock = document.createElement('div');
            qBlock.id = `q_block_${questionCount}`;
            qBlock.className = "p-4 border border-stone-200 rounded-xl bg-stone-50/50 space-y-3.5 relative group";
            
            qBlock.innerHTML = `
                <div class="flex items-center justify-between">
                    <span class="text-xs font-black uppercase tracking-wider text-orange-600">Question Item #${questionCount}</span>
                    <button type="button" onclick="removeQuestion(${questionCount})" class="text-stone-400 hover:text-rose-500 text-xs font-bold transition-colors">
                        <i class="fa-solid fa-trash-can mr-1"></i> Remove
                    </button>
                </div>
                <div>
                    <label class="block text-[10px] font-bold uppercase text-stone-500 mb-1">Question Prompt / Description</label>
                    <input type="text" name="questions[${questionCount}][text]" required placeholder="Enter Question Prompt / Description..." class="w-full bg-white border border-stone-200 rounded-lg p-2.5 text-xs outline-none focus:border-orange-500 font-medium shadow-2xs">
                </div>
                <div class="grid grid-cols-2 gap-3 pt-1">
                    <div class="space-y-1">
                        <label class="text-[10px] font-bold uppercase text-stone-500">Question Type</label>
                        <select name="questions[${questionCount}][type]" onchange="onQuestionTypeChanged(${questionCount}, this.value)" class="w-full bg-white border border-stone-200 rounded-lg p-2 text-xs font-semibold outline-none focus:border-orange-500">
                            <option value="multiple_choice">Multiple Choice (4 Options)</option>
                            <option value="true_false">True or False (2 Options)</option>
                            <option value="identification">Identification (1 Answer)</option>
                            <option value="fill_blank">Fill in the Blank / Enumeration</option>
                        </select>
                    </div>
                    <div class="space-y-1">
                        <label class="text-[10px] font-bold uppercase text-stone-500">Correct Answer Key</label>
                        <input type="text" name="questions[${questionCount}][correct]" required placeholder="e.g. A or Correct Text" class="w-full bg-white border border-stone-200 rounded-lg p-2 text-xs font-bold outline-none focus:border-orange-500">
                    </div>
                </div>
                <div id="q_options_container_${questionCount}" class="pt-1">
                    <label class="block text-[10px] font-bold uppercase text-stone-500 mb-1.5">Answer Options (4 Choices)</label>
                    <div class="grid grid-cols-2 gap-2.5 text-xs">
                        <input type="text" name="questions[${questionCount}][opt_a]" placeholder="Option A" class="bg-white border rounded-lg p-2 outline-none focus:border-orange-500">
                        <input type="text" name="questions[${questionCount}][opt_b]" placeholder="Option B" class="bg-white border rounded-lg p-2 outline-none focus:border-orange-500">
                        <input type="text" name="questions[${questionCount}][opt_c]" placeholder="Option C" class="bg-white border rounded-lg p-2 outline-none focus:border-orange-500">
                        <input type="text" name="questions[${questionCount}][opt_d]" placeholder="Option D" class="bg-white border rounded-lg p-2 outline-none focus:border-orange-500">
                    </div>
                </div>
            `;
            
            container.appendChild(qBlock);
            updateFormItemsCountBadge();
            return questionCount;
        }

        function onQuestionTypeChanged(id, type) {
            const optContainer = document.getElementById(`q_options_container_${id}`);
            if (!optContainer) return;

            if (type === 'multiple_choice') {
                optContainer.classList.remove('hidden');
                optContainer.innerHTML = `
                    <label class="block text-[10px] font-bold uppercase text-stone-500 mb-1.5">Answer Options (4 Choices)</label>
                    <div class="grid grid-cols-2 gap-2.5 text-xs">
                        <input type="text" name="questions[${id}][opt_a]" placeholder="Option A" class="bg-white border rounded-lg p-2 outline-none focus:border-orange-500">
                        <input type="text" name="questions[${id}][opt_b]" placeholder="Option B" class="bg-white border rounded-lg p-2 outline-none focus:border-orange-500">
                        <input type="text" name="questions[${id}][opt_c]" placeholder="Option C" class="bg-white border rounded-lg p-2 outline-none focus:border-orange-500">
                        <input type="text" name="questions[${id}][opt_d]" placeholder="Option D" class="bg-white border rounded-lg p-2 outline-none focus:border-orange-500">
                    </div>
                `;
            } else if (type === 'true_false') {
                optContainer.classList.remove('hidden');
                optContainer.innerHTML = `
                    <label class="block text-[10px] font-bold uppercase text-stone-500 mb-1.5">True / False Choices (2 Options)</label>
                    <div class="grid grid-cols-2 gap-2.5 text-xs">
                        <div class="bg-white border border-stone-200 rounded-lg p-2 font-bold text-stone-700 flex items-center gap-2">
                            <span class="w-3 h-3 rounded-full border border-stone-400"></span> True
                            <input type="hidden" name="questions[${id}][opt_a]" value="True">
                        </div>
                        <div class="bg-white border border-stone-200 rounded-lg p-2 font-bold text-stone-700 flex items-center gap-2">
                            <span class="w-3 h-3 rounded-full border border-stone-400"></span> False
                            <input type="hidden" name="questions[${id}][opt_b]" value="False">
                        </div>
                    </div>
                `;
            } else {
                // Identification or Enumeration: No multiple choice options
                optContainer.classList.add('hidden');
                optContainer.innerHTML = '';
            }
        }

        function removeQuestion(id) {
            const elem = document.getElementById(`q_block_${id}`);
            if (elem) elem.remove();
            updateFormItemsCountBadge();
        }

        // Import a single recycled question object into form
        function importRecycledQuestion(q) {
            // Check if there is only 1 blank initial question item
            const container = document.getElementById('questions_container');
            const blocks = container.querySelectorAll('[id^="q_block_"]');
            let targetId = questionCount + 1;

            if (blocks.length === 1) {
                const firstInput = blocks[0].querySelector('input[name*="[text]"]');
                if (firstInput && !firstInput.value.trim()) {
                    // Reuse first empty slot
                    const match = blocks[0].id.match(/q_block_(\d+)/);
                    if (match) targetId = parseInt(match[1]);
                } else {
                    targetId = addQuestion();
                }
            } else {
                targetId = addQuestion();
            }

            const qType = (q.question_type || 'multiple_choice').toLowerCase().replace('matching_type', 'matching');
            const qBlock = document.getElementById(`q_block_${targetId}`);
            if (!qBlock) return;

            const textInput = qBlock.querySelector(`input[name="questions[${targetId}][text]"]`);
            if (textInput) textInput.value = q.question_text || '';

            const typeSelect = qBlock.querySelector(`select[name="questions[${targetId}][type]"]`);
            if (typeSelect) {
                typeSelect.value = qType;
                onQuestionTypeChanged(targetId, qType);
            }

            const correctInput = qBlock.querySelector(`input[name="questions[${targetId}][correct]"]`);
            if (correctInput) correctInput.value = q.correct_answer || '';

            if (qType === 'multiple_choice') {
                const optA = qBlock.querySelector(`input[name="questions[${targetId}][opt_a]"]`);
                const optB = qBlock.querySelector(`input[name="questions[${targetId}][opt_b]"]`);
                const optC = qBlock.querySelector(`input[name="questions[${targetId}][opt_c]"]`);
                const optD = qBlock.querySelector(`input[name="questions[${targetId}][opt_d]"]`);
                if (optA) optA.value = q.option_a || '';
                if (optB) optB.value = q.option_b || '';
                if (optC) optC.value = q.option_c || '';
                if (optD) optD.value = q.option_d || '';
            }

            updateFormItemsCountBadge();
            // Highlight imported block briefly
            qBlock.classList.add('ring-2', 'ring-orange-500');
            setTimeout(() => {
                qBlock.classList.remove('ring-2', 'ring-orange-500');
            }, 1200);
        }

        // Recycle all questions from an exam object
        function recycleAllFromExam(exam) {
            if (!exam || !exam.questions || exam.questions.length === 0) {
                alert('No questions found in this exam to recycle.');
                return;
            }
            if (confirm(`Recycle all ${exam.questions.length} questions from '${exam.title}' into your current exam paper?`)) {
                exam.questions.forEach(q => {
                    importRecycledQuestion(q);
                });
                alert(`Successfully imported ${exam.questions.length} question(s) from '${exam.title}'!`);
            }
        }

        // Recycle all questions from modal preview
        let currentModalExam = null;
        function openExamPreviewModal(exam) {
            currentModalExam = exam;
            document.getElementById('modal_exam_badge').innerText = exam.specialization || 'Civil Engineering';
            document.getElementById('modal_exam_title').innerText = exam.title;
            document.getElementById('modal_exam_subtitle').innerText = `${exam.subject} | ${exam.total_items || 5} Questions | ${exam.time_limit || 60} mins`;

            const recycleBtn = document.getElementById('modal_recycle_all_btn');
            if (recycleBtn) {
                recycleBtn.onclick = function() {
                    recycleAllFromExam(exam);
                    closeExamPreviewModal();
                };
            }

            const delBtn = document.getElementById('modal_delete_btn');
            if (delBtn) {
                delBtn.onclick = function() {
                    deleteExam(exam.id, exam.title);
                };
            }

            const printBtn = document.getElementById('modal_print_btn');
            document.getElementById('modal_download_btn').href = `print_exam.php?id=${exam.id}&download=1`;
            if (printBtn) {
                printBtn.href = `print_exam.php?id=${exam.id}`;
            }

            const listContainer = document.getElementById('modal_questions_list');
            listContainer.innerHTML = '';

            if (exam.questions && exam.questions.length > 0) {
                exam.questions.forEach((q, idx) => {
                    const qItem = document.createElement('div');
                    qItem.className = 'p-4 border border-stone-200 rounded-xl bg-stone-50/60 space-y-3';
                    
                    let optionsHtml = '';
                    const correctKey = (q.correct_answer || '').trim().toLowerCase();

                    if (q.option_a) {
                        const isA = correctKey === 'a' || correctKey === (q.option_a || '').trim().toLowerCase() || correctKey === 'true';
                        const isB = correctKey === 'b' || correctKey === (q.option_b || '').trim().toLowerCase() || correctKey === 'false';
                        const isC = correctKey === 'c' || correctKey === (q.option_c || '').trim().toLowerCase();
                        const isD = correctKey === 'd' || correctKey === (q.option_d || '').trim().toLowerCase();

                        optionsHtml = `
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 text-xs font-semibold pt-1">
                                <div class="p-2.5 rounded-lg border ${isA ? 'bg-emerald-100 border-emerald-300 text-emerald-900 font-bold' : 'bg-white border-stone-200 text-stone-600'}">
                                    A. ${q.option_a} ${isA ? '<i class="fa-solid fa-circle-check text-emerald-600 ml-1"></i> (Correct Key)' : ''}
                                </div>
                                <div class="p-2.5 rounded-lg border ${isB ? 'bg-emerald-100 border-emerald-300 text-emerald-900 font-bold' : 'bg-white border-stone-200 text-stone-600'}">
                                    B. ${q.option_b} ${isB ? '<i class="fa-solid fa-circle-check text-emerald-600 ml-1"></i> (Correct Key)' : ''}
                                </div>
                                ${q.option_c ? `
                                <div class="p-2.5 rounded-lg border ${isC ? 'bg-emerald-100 border-emerald-300 text-emerald-900 font-bold' : 'bg-white border-stone-200 text-stone-600'}">
                                    C. ${q.option_c} ${isC ? '<i class="fa-solid fa-circle-check text-emerald-600 ml-1"></i> (Correct Key)' : ''}
                                </div>` : ''}
                                ${q.option_d ? `
                                <div class="p-2.5 rounded-lg border ${isD ? 'bg-emerald-100 border-emerald-300 text-emerald-900 font-bold' : 'bg-white border-stone-200 text-stone-600'}">
                                    D. ${q.option_d} ${isD ? '<i class="fa-solid fa-circle-check text-emerald-600 ml-1"></i> (Correct Key)' : ''}
                                </div>` : ''}
                            </div>
                        `;
                    } else {
                        optionsHtml = `
                            <div class="p-2.5 rounded-lg border bg-emerald-50 border-emerald-200 text-emerald-800 text-xs font-bold flex items-center gap-1.5">
                                <i class="fa-solid fa-circle-check text-emerald-600"></i> Correct Answer Key: ${q.correct_answer}
                            </div>
                        `;
                    }

                    qItem.innerHTML = `
                        <div class="flex items-center justify-between">
                            <span class="text-xs font-black uppercase text-orange-600">Question Item #${idx + 1}</span>
                            <div class="flex items-center gap-2">
                                <span class="text-[10px] bg-stone-200 text-stone-700 font-bold px-2 py-0.5 rounded uppercase">${(q.question_type || 'multiple_choice').replace('_', ' ')}</span>
                                <button type="button" onclick="importRecycledQuestion(${JSON.stringify(q).replace(/"/g, '&quot;')}); closeExamPreviewModal();" class="text-[10px] bg-orange-600 text-white font-bold px-2.5 py-1 rounded hover:bg-orange-700 transition-colors flex items-center gap-1">
                                    <i class="fa-solid fa-plus text-[8px]"></i> Recycle This
                                </button>
                            </div>
                        </div>
                        <h5 class="text-xs font-bold text-stone-800 leading-relaxed">${q.question_text}</h5>
                        ${optionsHtml}
                    `;

                    listContainer.appendChild(qItem);
                });
            } else {
                listContainer.innerHTML = '<p class="text-xs text-stone-400 text-center py-6">No question items recorded for this exam paper.</p>';
            }

            document.getElementById('qb_preview_modal').classList.remove('hidden');
            document.getElementById('qb_preview_modal').classList.add('flex');
        }

        function closeExamPreviewModal() {
            document.getElementById('qb_preview_modal').classList.add('hidden');
            document.getElementById('qb_preview_modal').classList.remove('flex');
        }

        function deleteExam(id, title) {
            if (confirm(`Are you sure you want to delete '${title}' from your Saved Question Bank? This archives the exam and removes student availability. Completed attempts and question records are preserved.`)) {
                document.getElementById('delete_exam_id').value = id;
                document.getElementById('deleteExamForm').submit();
            }
        }

        // Recycle Modal Functions
        let selectedRecycleItems = new Set();

        function openRecycleModal() {
            renderRecycleQuestions();
            document.getElementById('recycle_modal').classList.remove('hidden');
            document.getElementById('recycle_modal').classList.add('flex');
        }

        function closeRecycleModal() {
            document.getElementById('recycle_modal').classList.add('hidden');
            document.getElementById('recycle_modal').classList.remove('flex');
        }

        function getUnifiedRecycleList() {
            // Combine bank questions and questions from existing exams with usage metadata
            const items = [];
            const seenKeys = new Set();

            allBankQuestions.forEach(bq => {
                const key = (bq.question_text || '').trim().toLowerCase();
                seenKeys.add(key);
                items.push({
                    id: bq.sample_id,
                    question_text: bq.question_text,
                    question_type: bq.question_type,
                    option_a: bq.option_a,
                    option_b: bq.option_b,
                    option_c: bq.option_c,
                    option_d: bq.option_d,
                    correct_answer: bq.correct_answer,
                    formula_latex: bq.formula_latex,
                    points: bq.points || 1,
                    usage_count: parseInt(bq.usage_count) || 1,
                    source_title: bq.latest_exam_title || 'Question Bank',
                    exam_id: null
                });
            });

            allExistingExams.forEach(ex => {
                if (ex.questions && ex.questions.length > 0) {
                    ex.questions.forEach(q => {
                        const key = (q.question_text || '').trim().toLowerCase();
                        items.push({
                            id: q.id,
                            question_text: q.question_text,
                            question_type: q.question_type,
                            option_a: q.option_a,
                            option_b: q.option_b,
                            option_c: q.option_c,
                            option_d: q.option_d,
                            correct_answer: q.correct_answer,
                            formula_latex: q.formula_latex,
                            points: q.points || 1,
                            usage_count: 1,
                            source_title: ex.title,
                            exam_id: ex.id
                        });
                    });
                }
            });

            return items;
        }

        let currentFilteredRecycleItems = [];

        function filterRecycleQuestions() {
            const query = (document.getElementById('recycle_search_input').value || '').trim().toLowerCase();
            const examFilter = document.getElementById('recycle_exam_filter').value;
            const allItems = getUnifiedRecycleList();

            currentFilteredRecycleItems = allItems.filter(item => {
                const matchQuery = !query || 
                    (item.question_text && item.question_text.toLowerCase().includes(query)) ||
                    (item.source_title && item.source_title.toLowerCase().includes(query)) ||
                    (item.correct_answer && item.correct_answer.toLowerCase().includes(query));

                let matchExam = true;
                if (examFilter !== 'all') {
                    matchExam = item.exam_id == examFilter;
                }
                return matchQuery && matchExam;
            });

            renderRecycleItemsList(currentFilteredRecycleItems);
        }

        function renderRecycleQuestions() {
            currentFilteredRecycleItems = getUnifiedRecycleList();
            renderRecycleItemsList(currentFilteredRecycleItems);
        }

        function renderRecycleItemsList(items) {
            const container = document.getElementById('recycle_modal_items_container');
            container.innerHTML = '';
            selectedRecycleItems.clear();
            updateRecycleSelectedBadge();

            if (!items || items.length === 0) {
                container.innerHTML = '<p class="text-xs text-stone-400 text-center py-8">No past questions matched your filter criteria.</p>';
                return;
            }

            items.forEach((item, idx) => {
                const card = document.createElement('div');
                card.className = "p-3.5 border border-stone-200 rounded-xl bg-stone-50/60 hover:bg-orange-50/20 hover:border-orange-300 transition-all flex items-start gap-3 group";
                
                card.innerHTML = `
                    <input type="checkbox" onchange="toggleRecycleItem(${idx}, this.checked)" class="recycle-checkbox mt-1 accent-orange-600 rounded cursor-pointer">
                    <div class="flex-1 space-y-1.5">
                        <div class="flex items-center justify-between flex-wrap gap-1">
                            <div class="flex items-center gap-1.5">
                                <span class="bg-amber-100 text-amber-900 border border-amber-300 text-[9px] font-black px-2 py-0.5 rounded-full">
                                    <i class="fa-solid fa-repeat text-amber-700"></i> Used ${item.usage_count}x
                                </span>
                                <span class="bg-stone-200 text-stone-700 text-[9px] font-bold px-2 py-0.5 rounded uppercase">
                                    ${(item.question_type || 'multiple_choice').replace('_', ' ')}
                                </span>
                            </div>
                            <span class="text-[10px] text-stone-400 font-semibold truncate max-w-[200px]">
                                ${item.source_title}
                            </span>
                        </div>
                        <p class="text-xs font-bold text-stone-800 leading-relaxed">${item.question_text}</p>
                        <div class="flex items-center justify-between pt-1 border-t border-stone-100 text-[10px]">
                            <span class="text-emerald-700 font-bold">
                                Key: ${item.correct_answer}
                            </span>
                            <button type="button" onclick="importRecycledQuestion(${JSON.stringify(item).replace(/"/g, '&quot;')}); closeRecycleModal();" class="text-orange-600 hover:text-orange-700 font-extrabold flex items-center gap-1">
                                <i class="fa-solid fa-plus text-[9px]"></i> Add this item
                            </button>
                        </div>
                    </div>
                `;

                container.appendChild(card);
            });
        }

        function toggleRecycleItem(idx, checked) {
            if (checked) {
                selectedRecycleItems.add(idx);
            } else {
                selectedRecycleItems.delete(idx);
            }
            updateRecycleSelectedBadge();
        }

        function toggleSelectAllRecycle(checked) {
            const checkboxes = document.querySelectorAll('.recycle-checkbox');
            checkboxes.forEach((cb, idx) => {
                cb.checked = checked;
                if (checked) {
                    selectedRecycleItems.add(idx);
                } else {
                    selectedRecycleItems.delete(idx);
                }
            });
            updateRecycleSelectedBadge();
        }

        function updateRecycleSelectedBadge() {
            const count = selectedRecycleItems.size;
            document.getElementById('recycle_selected_count').innerText = `${count} question${count === 1 ? '' : 's'} selected`;
            document.getElementById('btn_selected_badge').innerText = count;
        }

        function importSelectedRecycleQuestions() {
            if (selectedRecycleItems.size === 0) {
                alert('Please select at least one question to recycle.');
                return;
            }

            let imported = 0;
            selectedRecycleItems.forEach(idx => {
                const item = currentFilteredRecycleItems[idx];
                if (item) {
                    importRecycledQuestion(item);
                    imported++;
                }
            });

            closeRecycleModal();
            alert(`Successfully recycled ${imported} question(s) into your current exam!`);
        }

        // Initialize first blank question
        addQuestion();
    </script>
</body>
</html>
