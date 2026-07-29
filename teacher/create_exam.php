<?php
require_once __DIR__ . '/../app/database.php';
require_once __DIR__ . '/../app/session.php';
require_once __DIR__ . '/../includes/security.php';

requireRole('teacher');
$pdo = getDBConnection();

$success_msg = "";
$error_msg = "";


if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_exam'])) {
    validateCSRFToken();
    $title = trim($_POST['title']);
    $subject = trim($_POST['subject']);
    $specialization = trim($_POST['specialization'] ?? 'Structural Engineering');
    $time_limit = intval($_POST['time_limit']);
    
    $questions = $_POST['questions'] ?? [];

    if (!empty($title) && !empty($subject) && !empty($questions)) {
        try {
            $pdo->beginTransaction();

            
            $stmt = $pdo->prepare("INSERT INTO exams (teacher_id, title, subject, specialization, time_limit, total_items) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$_SESSION['user_id'], $title, $subject, $specialization, $time_limit, count($questions)]);
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
            $success_msg = "Exam and Answer Key for '{$specialization}' created successfully!";
        } catch (Exception $e) {
            $pdo->rollBack();
            $error_msg = "Failed to save exam: " . $e->getMessage();
        }
    } else {
        $error_msg = "Please fill in all details and add at least one question.";
    }
}


$stmtExams = $pdo->prepare("SELECT * FROM exams WHERE teacher_id = ? ORDER BY id DESC");
$stmtExams->execute([$_SESSION['user_id']]);
$existing_exams = $stmtExams->fetchAll(PDO::FETCH_ASSOC);

$qStmt = $pdo->prepare("SELECT * FROM exam_questions WHERE exam_id = ? ORDER BY id ASC");
foreach ($existing_exams as &$ex) {
    $qStmt->execute([$ex['id']]);
    $ex['questions'] = $qStmt->fetchAll(PDO::FETCH_ASSOC);
}
unset($ex);
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

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                
                
                <div class="lg:col-span-2 bg-white border border-stone-200 rounded-2xl p-6 shadow-sm space-y-6">
                    <form action="create_exam.php" method="POST" id="examForm" class="space-y-6">
                        <?php echo csrfInputField(); ?>

                        <div class="border-b pb-4">
                            <h3 class="text-sm font-bold uppercase tracking-wider text-stone-700"><i class="fa-solid fa-sliders text-orange-500 mr-1"></i> Exam Parameters</h3>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div class="space-y-1">
                                <label class="text-xs font-bold text-stone-600">Exam Title</label>
                                <input type="text" name="title" required placeholder="e.g. Midterm Assessment" class="w-full bg-stone-50 border border-stone-200 rounded-xl px-4 py-2 text-xs outline-none focus:border-orange-500">
                            </div>
                            <div class="space-y-1">
                                <label class="text-xs font-bold text-stone-600">Subject</label>
                                <input type="text" name="subject" required placeholder="e.g. Structural Theory 1" class="w-full bg-stone-50 border border-stone-200 rounded-xl px-4 py-2 text-xs outline-none focus:border-orange-500">
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

                        
                        <div class="space-y-4 pt-4 border-t">
                            <div class="flex items-center justify-between">
                                <h3 class="text-sm font-bold uppercase tracking-wider text-stone-700"><i class="fa-solid fa-list-check text-orange-500 mr-1"></i> Question Items</h3>
                                <button type="button" onclick="addQuestion()" class="bg-stone-900 hover:bg-orange-600 text-white font-bold text-xs px-3 py-1.5 rounded-xl transition-all">
                                    <i class="fa-solid fa-plus mr-1"></i> Add Item
                                </button>
                            </div>

                            <div id="questions_container" class="space-y-4">
                                
                            </div>
                        </div>

                        <button type="submit" name="save_exam" class="w-full bg-orange-600 hover:bg-orange-700 text-white font-bold text-xs py-3 rounded-xl shadow-md transition-all">
                            <i class="fa-solid fa-floppy-disk mr-1"></i> Save Exam to Question Bank
                        </button>
                    </form>
                </div>

                
                <div class="bg-white border border-stone-200 rounded-2xl p-6 shadow-sm space-y-4 h-fit">
                    <h3 class="text-sm font-bold uppercase tracking-wider text-stone-700 border-b pb-3"><i class="fa-solid fa-database text-orange-500 mr-1"></i> Saved Question Bank</h3>
                    
                    <?php if (!empty($existing_exams)): ?>
                        <div class="space-y-3">
                            <?php foreach ($existing_exams as $ex): ?>
                                <div onclick="openExamPreviewModal(<?php echo htmlspecialchars(json_encode($ex), ENT_QUOTES, 'UTF-8'); ?>)" class="p-3.5 border border-stone-200 rounded-xl bg-stone-50/50 hover:border-orange-500 hover:bg-orange-50/30 hover:shadow-md cursor-pointer transition-all space-y-1.5 group">
                                    <div class="flex items-center justify-between">
                                        <h4 class="font-extrabold text-xs text-stone-800 group-hover:text-orange-600 transition-colors flex items-center gap-1.5">
                                            <i class="fa-solid fa-folder-open text-orange-500"></i>
                                            <?php echo htmlspecialchars($ex['title']); ?>
                                        </h4>
                                        <span class="text-[9px] bg-orange-100 text-orange-700 font-extrabold px-2.5 py-0.5 rounded-full shadow-2xs">
                                            <?php echo $ex['total_items']; ?> Items
                                        </span>
                                    </div>
                                    <p class="text-[10px] text-stone-400 font-semibold"><?php echo htmlspecialchars($ex['subject']); ?></p>
                                    <div class="flex items-center justify-between pt-1 border-t border-stone-100/80 mt-2">
                                        <span class="inline-block text-[9px] font-bold text-orange-600 bg-orange-50 px-2 py-0.5 rounded border border-orange-200">
                                            <i class="fa-solid fa-compass-drafting mr-1"></i><?php echo htmlspecialchars($ex['specialization'] ?? 'Structural Engineering'); ?>
                                        </span>
                                        <span class="text-[10px] text-orange-600 font-bold group-hover:translate-x-0.5 transition-transform flex items-center gap-1">
                                            View Items <i class="fa-solid fa-arrow-right text-[9px]"></i>
                                        </span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <p class="text-xs text-stone-400 text-center py-6">No exams created yet.</p>
                    <?php endif; ?>
                </div>

            </div>
        </div>
    </main>

    <!-- QUESTION BANK PREVIEW MODAL -->
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
                <!-- Dynamic Question Items -->
            </div>

            <div class="flex justify-between items-center pt-4 border-t border-stone-100">
                <span class="text-xs text-stone-400 font-semibold"><i class="fa-solid fa-shield-halved text-emerald-500 mr-1"></i> QuestBank AI Verified Answer Keys</span>
                <button onclick="closeExamPreviewModal()" class="px-5 py-2.5 bg-stone-900 hover:bg-orange-600 text-white font-bold text-xs rounded-xl shadow-sm transition-all">
                    Close Preview
                </button>
            </div>

        </div>
    </div>

            </div>
        </div>
    </main>

    
    <script>
        let questionCount = 0;

        function addQuestion() {
            questionCount++;
            const container = document.getElementById('questions_container');
            
            const qBlock = document.createElement('div');
            qBlock.className = 'p-4 border border-stone-200 rounded-xl bg-stone-50/50 space-y-3 relative';
            qBlock.id = `q_block_${questionCount}`;

            qBlock.innerHTML = `
                <div class="flex items-center justify-between">
                    <span class="text-xs font-bold text-orange-600">Item #${questionCount}</span>
                    <button type="button" onclick="removeQuestion(${questionCount})" class="text-stone-400 hover:text-red-500 text-xs"><i class="fa-solid fa-trash"></i></button>
                </div>
                <div class="space-y-1">
                    <label class="text-[10px] font-bold uppercase text-stone-500">Question Text</label>
                    <textarea name="questions[${questionCount}][text]" required rows="2" placeholder="Enter question..." class="w-full bg-white border border-stone-200 rounded-lg p-2 text-xs outline-none focus:border-orange-500 resize-none"></textarea>
                </div>
                <div class="grid grid-cols-2 gap-2 text-xs">
                    <div class="space-y-1">
                        <label class="text-[10px] font-bold uppercase text-stone-500">Question Type</label>
                        <select name="questions[${questionCount}][type]" class="w-full bg-white border rounded p-1.5 outline-none">
                            <option value="multiple_choice">Multiple Choice</option>
                            <option value="identification">Identification</option>
                            <option value="true_false">True / False</option>
                        </select>
                    </div>
                    <div class="space-y-1">
                        <label class="text-[10px] font-bold uppercase text-stone-500">Correct Answer</label>
                        <input type="text" name="questions[${questionCount}][correct]" required placeholder="Correct Answer Key" class="w-full bg-white border border-stone-200 rounded p-1.5 text-xs outline-none">
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-2 text-xs">
                    <input type="text" name="questions[${questionCount}][opt_a]" placeholder="Option A (Optional)" class="bg-white border rounded p-1.5 outline-none">
                    <input type="text" name="questions[${questionCount}][opt_b]" placeholder="Option B (Optional)" class="bg-white border rounded p-1.5 outline-none">
                    <input type="text" name="questions[${questionCount}][opt_c]" placeholder="Option C (Optional)" class="bg-white border rounded p-1.5 outline-none">
                    <input type="text" name="questions[${questionCount}][opt_d]" placeholder="Option D (Optional)" class="bg-white border rounded p-1.5 outline-none">
                </div>
            `;
            
            container.appendChild(qBlock);
        }

        function removeQuestion(id) {
            const elem = document.getElementById(`q_block_${id}`);
            if (elem) elem.remove();
        }

        // Add first question item by default
        addQuestion();

        function openExamPreviewModal(exam) {
            document.getElementById('modal_exam_badge').innerText = exam.specialization || 'Civil Engineering';
            document.getElementById('modal_exam_title').innerText = exam.title;
            document.getElementById('modal_exam_subtitle').innerText = `${exam.subject} | ${exam.total_items || 5} Questions | ${exam.time_limit || 60} mins`;

            const listContainer = document.getElementById('modal_questions_list');
            listContainer.innerHTML = '';

            if (exam.questions && exam.questions.length > 0) {
                exam.questions.forEach((q, idx) => {
                    const qItem = document.createElement('div');
                    qItem.className = 'p-4 border border-stone-200 rounded-xl bg-stone-50/60 space-y-3';
                    
                    let optionsHtml = '';
                    const correctKey = (q.correct_answer || '').trim().toLowerCase();

                    if (q.option_a) {
                        const isA = correctKey === 'a' || correctKey === (q.option_a || '').trim().toLowerCase();
                        const isB = correctKey === 'b' || correctKey === (q.option_b || '').trim().toLowerCase();
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
                                <div class="p-2.5 rounded-lg border ${isC ? 'bg-emerald-100 border-emerald-300 text-emerald-900 font-bold' : 'bg-white border-stone-200 text-stone-600'}">
                                    C. ${q.option_c} ${isC ? '<i class="fa-solid fa-circle-check text-emerald-600 ml-1"></i> (Correct Key)' : ''}
                                </div>
                                <div class="p-2.5 rounded-lg border ${isD ? 'bg-emerald-100 border-emerald-300 text-emerald-900 font-bold' : 'bg-white border-stone-200 text-stone-600'}">
                                    D. ${q.option_d} ${isD ? '<i class="fa-solid fa-circle-check text-emerald-600 ml-1"></i> (Correct Key)' : ''}
                                </div>
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
                            <span class="text-[10px] bg-stone-200 text-stone-700 font-bold px-2 py-0.5 rounded uppercase">${(q.question_type || 'multiple_choice').replace('_', ' ')}</span>
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
    </script>
</body>
</html>