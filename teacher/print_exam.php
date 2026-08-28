<?php
require_once __DIR__ . '/../app/bootstrap.php';

AuthService::enforceRole('teacher');
$pdo = getDBConnection();
$teacher_id = getCurrentUserId();

$exam_id = intval($_GET['id'] ?? $_GET['exam_id'] ?? 0);
$show_answers = isset($_GET['with_answers']) && $_GET['with_answers'] == '1';

if ($exam_id <= 0) {
    die("Invalid Exam ID.");
}

$stmt = $pdo->prepare("SELECT * FROM exams WHERE id = ? AND teacher_id = ?");
$stmt->execute([$exam_id, $teacher_id]);
$exam = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$exam) {
    die("Unauthorized: Exam not found or does not belong to your account.");
}

$qStmt = $pdo->prepare("SELECT * FROM exam_questions WHERE exam_id = ? ORDER BY id ASC");
$qStmt->execute([$exam_id]);
$questions = $qStmt->fetchAll(PDO::FETCH_ASSOC);

$totalPoints = 0;
foreach ($questions as $q) {
    $totalPoints += floatval($q['points'] ?? 1);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Print Examination - <?php echo htmlspecialchars($exam['title']); ?></title>
    <script src="https://unpkg.com/@tailwindcss/browser@4"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @media print {
            .no-print {
                display: none !important;
            }
            body {
                background: white !important;
                color: black !important;
                font-size: 12pt;
            }
            .print-page {
                box-shadow: none !important;
                border: none !important;
                padding: 0 !important;
                margin: 0 !important;
                max-width: 100% !important;
                width: 100% !important;
            }
            .page-break {
                page-break-before: always;
            }
        }
    </style>
</head>
<body class="bg-stone-100 min-h-screen text-stone-900 font-sans antialiased p-4 sm:p-8">

    <!-- Top Action Bar (Hidden when printing) -->
    <div class="no-print max-w-4xl mx-auto mb-6 bg-stone-900 text-white p-4 rounded-2xl shadow-xl flex flex-wrap items-center justify-between gap-3">
        <div class="flex items-center gap-3">
            <div class="w-9 h-9 rounded-xl bg-orange-600 flex items-center justify-center text-white">
                <i class="fa-solid fa-print text-sm"></i>
            </div>
            <div>
                <h3 class="text-xs font-black uppercase tracking-wider text-white">Printable Examination Paper</h3>
                <p class="text-[11px] text-stone-400"><?php echo htmlspecialchars($exam['title']); ?> (<?php echo count($questions); ?> Items)</p>
            </div>
        </div>

        <div class="flex items-center gap-2">
            <label class="flex items-center gap-2 text-xs font-bold bg-stone-800 hover:bg-stone-700 px-3 py-2 rounded-xl cursor-pointer transition-all">
                <input type="checkbox" id="toggleAnswers" <?php echo $show_answers ? 'checked' : ''; ?> onchange="toggleAnswerKeys(this.checked)" class="accent-orange-500 rounded">
                <span>Teacher Answer Key Mode</span>
            </label>

            <button type="button" onclick="window.print()" class="bg-orange-600 hover:bg-orange-700 text-white font-extrabold text-xs px-5 py-2 rounded-xl shadow transition-all flex items-center gap-1.5">
                <i class="fa-solid fa-print"></i> Print Now
            </button>

            <button type="button" onclick="window.close()" class="bg-stone-800 hover:bg-stone-700 text-stone-300 font-bold text-xs px-3 py-2 rounded-xl transition-all">
                Close Tab
            </button>
        </div>
    </div>

    <!-- Official Printable Examination Document -->
    <div class="print-page max-w-4xl mx-auto bg-white border border-stone-300 rounded-2xl p-8 sm:p-12 shadow-sm space-y-6">
        
        <!-- Institutional Header -->
        <div class="text-center border-b-2 border-stone-900 pb-5 space-y-1">
            <h2 class="text-sm font-bold uppercase tracking-widest text-stone-600">Holy Cross College</h2>
            <h1 class="text-base font-black uppercase tracking-wider text-stone-900">Department of Civil Engineering</h1>
            <h3 class="text-lg font-extrabold text-orange-600 uppercase tracking-tight pt-1"><?php echo htmlspecialchars($exam['title']); ?></h3>
            <div class="flex items-center justify-center gap-4 text-xs font-semibold text-stone-600 pt-1">
                <span><strong>Subject:</strong> <?php echo htmlspecialchars($exam['subject']); ?></span>
                <span>•</span>
                <span><strong>Total Items:</strong> <?php echo count($questions); ?> Items</span>
                <span>•</span>
                <span><strong>Total Points:</strong> <?php echo $totalPoints; ?> pts</span>
                <span>•</span>
                <span><strong>Time Limit:</strong> <?php echo intval($exam['time_limit'] ?? 60); ?> mins</span>
            </div>
        </div>

        <!-- Student Examination Header Form Fields -->
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 py-2 border-b border-stone-300 text-xs font-bold">
            <div class="col-span-2">
                <span class="text-stone-500 uppercase text-[10px] block">Student Full Name</span>
                <div class="border-b border-stone-800 h-6"></div>
            </div>
            <div>
                <span class="text-stone-500 uppercase text-[10px] block">Section / Year</span>
                <div class="border-b border-stone-800 h-6"></div>
            </div>
            <div>
                <span class="text-stone-500 uppercase text-[10px] block">Score</span>
                <div class="border-b border-stone-800 h-6"></div>
            </div>
        </div>

        <!-- General Directions -->
        <div class="bg-stone-50 p-3.5 rounded-xl border border-stone-200 text-xs text-stone-700 space-y-1">
            <p class="font-extrabold uppercase text-[11px] text-stone-900"><i class="fa-solid fa-circle-info mr-1 text-orange-500"></i> General Instructions:</p>
            <p>Read each question carefully before answering. Write or shade your answers clearly on this answer sheet. Avoid unnecessary erasures or alterations.</p>
        </div>

        <!-- Questions List -->
        <div class="space-y-6 pt-2">
            <?php foreach ($questions as $idx => $q): 
                $qNum = $idx + 1;
                $qType = strtolower(trim($q['question_type'] ?? 'multiple_choice'));
                $pts = floatval($q['points'] ?? 1);
            ?>
                <div class="question-block space-y-2 pb-4 border-b border-stone-100 last:border-0">
                    <div class="flex items-start justify-between gap-3">
                        <div class="flex items-start gap-2 font-bold text-xs sm:text-sm text-stone-900 leading-relaxed">
                            <span class="text-orange-600 font-extrabold"><?php echo $qNum; ?>.</span>
                            <div>
                                <span><?php echo nl2br(htmlspecialchars($q['question_text'])); ?></span>
                                <?php if (!empty($q['formula_latex'])): ?>
                                    <div class="mt-1.5 p-2 bg-stone-100 rounded text-xs font-mono font-bold text-stone-800">
                                        Formula: <?php echo htmlspecialchars($q['formula_latex']); ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <span class="text-[10px] font-bold text-stone-400 bg-stone-100 px-2 py-0.5 rounded flex-shrink-0">
                            <?php echo $pts; ?> <?php echo $pts > 1 ? 'pts' : 'pt'; ?>
                        </span>
                    </div>

                    <!-- Multiple Choice Choices -->
                    <?php if ($qType === 'multiple_choice'): ?>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 pl-6 pt-1 text-xs font-medium text-stone-800">
                            <?php if (!empty($q['option_a'])): ?>
                                <div class="flex items-center gap-2">
                                    <span class="font-bold text-stone-500">(A)</span>
                                    <span><?php echo htmlspecialchars($q['option_a']); ?></span>
                                </div>
                            <?php endif; ?>
                            <?php if (!empty($q['option_b'])): ?>
                                <div class="flex items-center gap-2">
                                    <span class="font-bold text-stone-500">(B)</span>
                                    <span><?php echo htmlspecialchars($q['option_b']); ?></span>
                                </div>
                            <?php endif; ?>
                            <?php if (!empty($q['option_c'])): ?>
                                <div class="flex items-center gap-2">
                                    <span class="font-bold text-stone-500">(C)</span>
                                    <span><?php echo htmlspecialchars($q['option_c']); ?></span>
                                </div>
                            <?php endif; ?>
                            <?php if (!empty($q['option_d'])): ?>
                                <div class="flex items-center gap-2">
                                    <span class="font-bold text-stone-500">(D)</span>
                                    <span><?php echo htmlspecialchars($q['option_d']); ?></span>
                                </div>
                            <?php endif; ?>
                        </div>

                    <!-- True / False Choices -->
                    <?php elseif ($qType === 'true_false'): ?>
                        <div class="flex items-center gap-6 pl-6 pt-1 text-xs font-bold text-stone-800">
                            <span class="flex items-center gap-2">
                                <span class="w-4 h-4 rounded-full border-2 border-stone-400 inline-block"></span> True
                            </span>
                            <span class="flex items-center gap-2">
                                <span class="w-4 h-4 rounded-full border-2 border-stone-400 inline-block"></span> False
                            </span>
                        </div>

                    <!-- Identification / Fill in Blank Blank Line -->
                    <?php elseif ($qType === 'identification' || $qType === 'fill_blank'): ?>
                        <div class="pl-6 pt-1 text-xs">
                            <span class="text-stone-400 font-semibold mr-2">Answer:</span>
                            <span class="inline-block border-b border-stone-800 w-64"></span>
                        </div>

                    <!-- Problem Solving / Math Formula Work Area -->
                    <?php elseif ($qType === 'problem_solving' || $qType === 'math_formula'): ?>
                        <div class="pl-6 pt-2 text-xs">
                            <div class="border border-dashed border-stone-300 rounded-lg p-4 h-24 bg-stone-50/50 flex items-start text-stone-400 text-[11px] italic">
                                Show complete solution and final boxed answer here:
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- Teacher Answer Key Display (Toggleable) -->
                    <div class="answer-key-box <?php echo $show_answers ? '' : 'hidden'; ?> ml-6 mt-2 p-2.5 rounded-lg bg-emerald-50 border border-emerald-300 text-emerald-900 text-xs font-bold space-y-1">
                        <div class="flex items-center gap-1.5">
                            <i class="fa-solid fa-key text-emerald-600"></i>
                            <span>Correct Answer Key: <span class="font-black underline"><?php echo htmlspecialchars($q['correct_answer']); ?></span></span>
                        </div>
                        <?php if (!empty($q['explanation'])): ?>
                            <p class="text-[11px] font-normal text-emerald-800">
                                <strong>Explanation / Solution:</strong> <?php echo htmlspecialchars($q['explanation']); ?>
                            </p>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Document Footer -->
        <div class="pt-6 border-t border-stone-300 text-center text-[10px] text-stone-400 font-bold uppercase tracking-widest">
            <span>QuestBank Assessment Paper • Holy Cross College Civil Engineering</span>
        </div>

    </div>

    <script>
        function toggleAnswerKeys(show) {
            document.querySelectorAll('.answer-key-box').forEach(el => {
                if (show) {
                    el.classList.remove('hidden');
                } else {
                    el.classList.add('hidden');
                }
            });
        }
    </script>
</body>
</html>
