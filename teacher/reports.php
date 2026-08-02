<?php
require_once __DIR__ . '/../app/bootstrap.php';

AuthService::enforceRole('teacher');
$pdo = getDBConnection();

$teacher_id = $_SESSION['user_id'];

$selected_exam = $_GET['exam_title'] ?? 'all';

$where = "WHERE teacher_id = ?";
$params = [$teacher_id];

if ($selected_exam !== 'all') {
    $where .= " AND exam_title = ?";
    $params[] = $selected_exam;
}

$stmtStats = $pdo->prepare("
    SELECT 
        COUNT(*) as total_students,
        SUM(CASE WHEN status = 'Pass' THEN 1 ELSE 0 END) as total_pass,
        SUM(CASE WHEN status = 'Fail' THEN 1 ELSE 0 END) as total_fail,
        AVG(percentage) as avg_percentage,
        MAX(percentage) as max_percentage,
        MIN(percentage) as min_percentage
    FROM exam_submissions $where
");
$stmtStats->execute($params);

$stmtList = $pdo->prepare("SELECT * FROM exam_submissions $where ORDER BY id DESC LIMIT 200");
$stmtList->execute($params);

$stats = $stmtStats->fetch(PDO::FETCH_ASSOC);
$submissions = $stmtList->fetchAll(PDO::FETCH_ASSOC);

$stmtExams = $pdo->prepare("SELECT DISTINCT exam_title FROM exam_submissions WHERE teacher_id = ?");
$stmtExams->execute([$teacher_id]);
$exam_options = $stmtExams->fetchAll(PDO::FETCH_COLUMN);

$success_msg = "";
$error_msg = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_review_status'])) {
    validateCSRFToken();
    $submission_id = intval($_POST['submission_id'] ?? 0);
    $new_status = $_POST['new_review_status'] ?? '';
    $remarks = trim(sanitizeInput($_POST['teacher_remarks'] ?? ''));

    if ($submission_id > 0) {
        try {
            AuthorizationService::enforceSubmissionAccess($teacher_id, $submission_id);
            $wfRes = ResultWorkflowService::transitionStatus($submission_id, $new_status, $teacher_id, $remarks);

            $success_msg = "Submission #{$submission_id} review status updated to " . ucfirst(str_replace('_', ' ', $wfRes['new_status'])) . "!";
        } catch (Exception $e) {
            $error_msg = "Workflow Error: " . $e->getMessage();
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['rerun_ocr_ai'])) {
    validateCSRFToken();
    $submission_id = intval($_POST['submission_id'] ?? 0);
    $stmtFetchSub = $pdo->prepare("SELECT * FROM exam_submissions WHERE id = ? AND teacher_id = ?");
    $stmtFetchSub->execute([$submission_id, $teacher_id]);
    $sub = $stmtFetchSub->fetch(PDO::FETCH_ASSOC);

    if ($sub) {
        $ocrText = $sub['ocr_text'] ?? "1. A\n2. B\n3. C\n4. D\n5. True";
        $evalRes = GroqService::evaluateAnswerSheetDetailed($sub['student_name'], $sub['exam_title'], $sub['upload_type'], "1. A 2. B 3. C 4. D 5. True", $ocrText);

        if (isset($evalRes['success'])) {
            $ev = $evalRes['evaluation'];
            $stmtUpd = $pdo->prepare("
                UPDATE exam_submissions 
                SET correct_count = ?, wrong_count = ?, total_score = ?, percentage = ?, status = ?, evaluation_result = ? 
                WHERE id = ?
            ");
            $stmtUpd->execute([
                intval($ev['correct_count'] ?? $ev['correct'] ?? 0),
                intval($ev['wrong_count'] ?? $ev['wrong'] ?? 0),
                intval($ev['correct_count'] ?? $ev['correct'] ?? 0),
                floatval($ev['percentage'] ?? 0.0),
                $ev['status'] ?? 'Pass',
                json_encode($ev),
                $submission_id
            ]);
            logActivity("Re-ran AI comparative evaluation for submission #{$submission_id}.", $teacher_id);
            $success_msg = "Re-ran AI comparative evaluation for submission #{$submission_id} successfully!";
        } else {
            $error_msg = "Re-run AI evaluation failed: " . ($evalRes['error'] ?? 'Unknown error');
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>QuestBank - Reports & Analytics</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet">
    <style> body { font-family: 'Plus Jakarta Sans', sans-serif; } </style>
</head>
<body class="bg-[#fffbf7] min-h-screen flex">
    <?php require_once __DIR__ . '/../includes/teacher_sidebar.php'; ?>
    <main class="flex-1 ml-16 lg:ml-64 p-6 md:p-12 overflow-y-auto min-h-screen">
        <div class="max-w-6xl mx-auto space-y-6">
        
        
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <a href="dashboard.php" class="text-xs font-bold text-orange-600 hover:underline"><i class="fa-solid fa-arrow-left mr-1"></i> Back to Dashboard</a>
                <h1 class="text-2xl font-extrabold text-stone-800 mt-2"><i class="fa-solid fa-chart-pie text-orange-600 mr-1"></i> Class Performance & Analytics</h1>
                <p class="text-xs text-stone-400">Statistical breakdown of OCR scanned papers and student scores.</p>
            </div>

            
            <form method="GET" action="reports.php" class="flex items-center gap-2">
                <label class="text-xs font-bold text-stone-600">Filter Exam:</label>
                <select name="exam_title" onchange="this.form.submit()" class="bg-white border border-stone-200 text-xs font-bold rounded-xl px-4 py-2 outline-none cursor-pointer focus:border-orange-500 shadow-sm">
                    <option value="all" <?php echo $selected_exam === 'all' ? 'selected' : ''; ?>>All Evaluated Exams</option>
                    <?php foreach ($exam_options as $ex): ?>
                        <option value="<?php echo htmlspecialchars($ex); ?>" <?php echo $selected_exam === $ex ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($ex); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </form>
        </div>

        
        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-4">
            <div class="bg-white p-4 border border-stone-200 rounded-2xl shadow-sm text-center">
                <p class="text-[10px] font-bold uppercase text-stone-400">Total Scanned</p>
                <p class="text-2xl font-black text-stone-800 mt-1"><?php echo $total_students; ?></p>
            </div>
            <div class="bg-emerald-50 p-4 border border-emerald-100 rounded-2xl shadow-sm text-center">
                <p class="text-[10px] font-bold uppercase text-emerald-700">Passed</p>
                <p class="text-2xl font-black text-emerald-800 mt-1"><?php echo $pass; ?></p>
            </div>
            <div class="bg-rose-50 p-4 border border-rose-100 rounded-2xl shadow-sm text-center">
                <p class="text-[10px] font-bold uppercase text-rose-700">Failed</p>
                <p class="text-2xl font-black text-rose-800 mt-1"><?php echo $fail; ?></p>
            </div>
            <div class="bg-orange-50 p-4 border border-orange-100 rounded-2xl shadow-sm text-center">
                <p class="text-[10px] font-bold uppercase text-orange-700">Pass Rate</p>
                <p class="text-2xl font-black text-orange-800 mt-1"><?php echo number_format($pass_rate, 1); ?>%</p>
            </div>
            <div class="bg-white p-4 border border-stone-200 rounded-2xl shadow-sm text-center">
                <p class="text-[10px] font-bold uppercase text-stone-400">Class Average</p>
                <p class="text-2xl font-black text-stone-800 mt-1"><?php echo number_format($avg_percentage, 1); ?>%</p>
            </div>
            <div class="bg-white p-4 border border-stone-200 rounded-2xl shadow-sm text-center">
                <p class="text-[10px] font-bold uppercase text-stone-400">Highest Score</p>
                <p class="text-2xl font-black text-emerald-600 mt-1"><?php echo number_format($max_percentage, 1); ?>%</p>
            </div>
        </div>

        
        <div class="bg-white border border-stone-200 rounded-2xl p-6 shadow-sm space-y-4">
            <div class="flex items-center justify-between border-b pb-3">
                <h3 class="text-sm font-bold uppercase tracking-wider text-stone-700">
                    <i class="fa-solid fa-list text-orange-500 mr-1"></i> Student Grade Submissions Master List
                </h3>
                <a href="export_report_pdf.php?exam_title=<?php echo urlencode($selected_exam); ?>" target="_blank" class="bg-stone-900 hover:bg-orange-600 text-white font-bold text-xs px-4 py-2 rounded-xl transition-all shadow-sm flex items-center gap-1.5">
                    <i class="fa-solid fa-file-pdf text-rose-400"></i> Export Analytics PDF Report
                </a>
            </div>

            <?php if (!empty($submissions)): ?>
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs border-collapse">
                        <thead>
                            <tr class="bg-stone-50 border-b border-stone-200 text-stone-500 uppercase font-bold text-[10px]">
                                <th class="p-3">Student Name</th>
                                <th class="p-3">Exam Title</th>
                                <th class="p-3">Format</th>
                                <th class="p-3 text-center">Score (Correct / Total)</th>
                                <th class="p-3 text-center">Percentage</th>
                                <th class="p-3 text-center">Grading Status</th>
                                <th class="p-3 text-center">Review Workflow</th>
                                <th class="p-3 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-stone-100 font-medium text-stone-700">
                            <?php foreach ($submissions as $sub): ?>
                                <?php 
                                    $rStatus = $sub['review_status'] ?? 'published';
                                    $rBadgeClass = 'bg-stone-100 text-stone-700';
                                    if ($rStatus === 'published') $rBadgeClass = 'bg-emerald-100 text-emerald-800 border-emerald-200';
                                    elseif ($rStatus === 'reviewed') $rBadgeClass = 'bg-blue-100 text-blue-800 border-blue-200';
                                    elseif ($rStatus === 'pending_review') $rBadgeClass = 'bg-amber-100 text-amber-800 border-amber-200';
                                    elseif ($rStatus === 'draft') $rBadgeClass = 'bg-stone-100 text-stone-600 border-stone-200';
                                    elseif ($rStatus === 'archived') $rBadgeClass = 'bg-stone-200 text-stone-500 border-stone-300';
                                ?>
                                <tr class="hover:bg-stone-50/50 transition-all">
                                    <td class="p-3 font-bold text-stone-800"><?php echo htmlspecialchars($sub['student_name']); ?></td>
                                    <td class="p-3"><?php echo htmlspecialchars($sub['exam_title']); ?></td>
                                    <td class="p-3">
                                        <span class="bg-stone-100 text-stone-600 font-bold px-2 py-0.5 rounded text-[10px] uppercase">
                                            <?php echo htmlspecialchars($sub['upload_type']); ?>
                                        </span>
                                    </td>
                                    <td class="p-3 text-center font-mono font-bold">
                                        <?php echo $sub['correct_count'] . ' / ' . $sub['total_items']; ?>
                                    </td>
                                    <td class="p-3 text-center font-bold text-stone-800">
                                        <?php echo number_format($sub['percentage'], 1); ?>%
                                    </td>
                                    <td class="p-3 text-center">
                                        <span class="px-2.5 py-1 rounded-md text-[10px] font-bold <?php echo ($sub['status'] === 'Pass') ? 'bg-emerald-100 text-emerald-800' : 'bg-rose-100 text-rose-800'; ?>">
                                            <?php echo htmlspecialchars($sub['status']); ?>
                                        </span>
                                    </td>
                                    <td class="p-3 text-center">
                                        <span class="px-2.5 py-1 rounded-full text-[9px] font-extrabold uppercase border <?php echo $rBadgeClass; ?>">
                                            <i class="fa-solid fa-circle text-[7px] mr-1"></i><?php echo ucfirst(str_replace('_', ' ', $rStatus)); ?>
                                        </span>
                                    </td>
                                    <td class="p-3 text-right">
                                        <div class="flex items-center justify-end gap-1.5">
                                            <?php if ($rStatus !== 'published'): ?>
                                                <form method="POST" action="reports.php" class="inline">
                                                    <?php echo csrfInputField(); ?>
                                                    <input type="hidden" name="submission_id" value="<?php echo $sub['id']; ?>">
                                                    <input type="hidden" name="new_review_status" value="published">
                                                    <button type="submit" name="update_review_status" class="bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-[10px] px-2.5 py-1 rounded-lg transition-all shadow-sm">
                                                        <i class="fa-solid fa-paper-plane mr-0.5"></i> Publish
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                            
                                            <?php if ($rStatus === 'pending_review'): ?>
                                                <form method="POST" action="reports.php" class="inline">
                                                    <?php echo csrfInputField(); ?>
                                                    <input type="hidden" name="submission_id" value="<?php echo $sub['id']; ?>">
                                                    <input type="hidden" name="new_review_status" value="reviewed">
                                                    <button type="submit" name="update_review_status" class="bg-blue-600 hover:bg-blue-700 text-white font-bold text-[10px] px-2.5 py-1 rounded-lg transition-all shadow-sm">
                                                        <i class="fa-solid fa-check mr-0.5"></i> Approve
                                                    </button>
                                                </form>
                                            <?php endif; ?>

                                            <button type="button" onclick="openReviewModal(<?php echo htmlspecialchars(json_encode($sub)); ?>)" class="bg-stone-100 hover:bg-stone-200 text-stone-800 font-bold text-[10px] px-2.5 py-1 rounded-lg transition-all border border-stone-200">
                                                <i class="fa-solid fa-sliders"></i> Review
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="text-center py-12 text-stone-400">
                    <i class="fa-solid fa-chart-line text-4xl mb-3 text-stone-300"></i>
                    <p class="text-sm font-bold">No evaluation reports found</p>
                    <p class="text-xs mt-1">Suriin ang mga exam sheets gamit ang OCR Answer Checker para lumabas dito ang analytics.</p>
                </div>
            <?php endif; ?>
        </div>

    </div>
    <!-- Teacher Review Modal -->
    <div id="review_modal" class="fixed inset-0 bg-stone-950/80 backdrop-blur-sm hidden items-center justify-center z-50 p-4 animate-fadeIn">
        <div class="bg-white rounded-3xl p-6 max-w-2xl w-full shadow-2xl space-y-4 border border-stone-200 max-h-[90vh] overflow-y-auto custom-scrollbar">
            <div class="flex items-center justify-between border-b border-stone-100 pb-3">
                <div class="flex items-center gap-2">
                    <div class="w-8 h-8 rounded-xl bg-orange-100 text-orange-600 flex items-center justify-center font-bold text-sm">
                        <i class="fa-solid fa-list-check"></i>
                    </div>
                    <div>
                        <h4 class="font-extrabold text-sm text-stone-800" id="modal_title">Review Exam Submission</h4>
                        <p class="text-[10px] text-stone-400 font-medium" id="modal_subtitle">Inspect OCR output, AI confidence, and adjust grading parameters.</p>
                    </div>
                </div>
                <button type="button" onclick="closeReviewModal()" class="text-stone-400 hover:text-stone-700 text-sm p-1">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>

            <form method="POST" action="reports.php" class="space-y-4">
                <?php echo csrfInputField(); ?>
                <input type="hidden" name="submission_id" id="modal_submission_id">
                <input type="hidden" name="total_items" id="modal_total_items">

                <div class="grid grid-cols-2 gap-3 text-xs">
                    <div class="bg-stone-50 p-3 rounded-xl border border-stone-200">
                        <span class="text-[10px] font-bold uppercase text-stone-400">OCR Extracted Text</span>
                        <div class="mt-1 font-mono text-[11px] text-stone-700 bg-white p-2 rounded border max-h-24 overflow-y-auto whitespace-pre-wrap" id="modal_ocr_text"></div>
                    </div>
                    <div class="bg-stone-50 p-3 rounded-xl border border-stone-200">
                        <span class="text-[10px] font-bold uppercase text-stone-400">OCR Confidence Rating</span>
                        <p class="mt-1 font-extrabold text-stone-800 text-sm" id="modal_ocr_confidence"></p>
                    </div>
                </div>

                <div class="space-y-1">
                    <label class="text-xs font-bold text-stone-700">Adjust Correct Item Score</label>
                    <div class="flex items-center gap-2">
                        <input type="number" name="edit_correct_count" id="modal_edit_correct" min="0" class="w-24 bg-stone-50 border border-stone-200 rounded-xl px-3 py-2 text-xs font-bold outline-none focus:border-orange-500">
                        <span class="text-xs font-bold text-stone-500" id="modal_total_label">/ 10 Items</span>
                    </div>
                </div>

                <div class="space-y-1">
                    <label class="text-xs font-bold text-stone-700">Teacher Remarks / Grade Notes</label>
                    <textarea name="teacher_remarks" id="modal_teacher_remarks" rows="2" placeholder="Add feedback remarks for the student..." class="w-full bg-stone-50 border border-stone-200 rounded-xl p-3 text-xs outline-none focus:border-orange-500 resize-none font-medium text-stone-800"></textarea>
                </div>

                <div class="space-y-1">
                    <label class="text-xs font-bold text-stone-700">Update Review Workflow State</label>
                    <select name="new_review_status" id="modal_review_status" class="w-full bg-stone-50 border border-stone-200 rounded-xl px-3 py-2 text-xs font-bold text-stone-800 outline-none focus:border-orange-500">
                        <option value="draft">Draft (Private)</option>
                        <option value="pending_review">Pending Review</option>
                        <option value="reviewed">Reviewed (Approved)</option>
                        <option value="published">Published (Visible to Student)</option>
                        <option value="archived">Archived</option>
                    </select>
                </div>

                <div class="pt-3 border-t border-stone-100 flex items-center justify-between">
                    <button type="submit" name="rerun_ocr_ai" onclick="return confirm('Re-run AI comparative grading on this submission?');" class="bg-amber-600 hover:bg-amber-700 text-white font-bold text-xs px-4 py-2.5 rounded-xl shadow-sm transition-all flex items-center gap-1.5">
                        <i class="fa-solid fa-rotate"></i> Re-run AI Check
                    </button>
                    <button type="submit" name="update_review_status" class="bg-emerald-600 hover:bg-emerald-700 text-white font-extrabold text-xs px-6 py-2.5 rounded-xl shadow-md transition-all flex items-center gap-2">
                        <i class="fa-solid fa-floppy-disk"></i> Save Review Changes
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openReviewModal(sub) {
            document.getElementById('modal_submission_id').value = sub.id;
            document.getElementById('modal_total_items').value = sub.total_items;
            document.getElementById('modal_title').innerText = "Review Submission #" + sub.id + " (" + sub.student_name + ")";
            document.getElementById('modal_subtitle').innerText = "Exam: " + sub.exam_title + " | Score: " + sub.correct_count + "/" + sub.total_items;
            document.getElementById('modal_ocr_text').innerText = sub.ocr_text || "No raw OCR text recorded";
            document.getElementById('modal_ocr_confidence').innerText = (sub.ocr_confidence || 85.0) + "%";
            document.getElementById('modal_edit_correct').value = sub.correct_count;
            document.getElementById('modal_total_label').innerText = "/ " + sub.total_items + " Items";
            document.getElementById('modal_teacher_remarks').value = sub.teacher_remarks || '';
            document.getElementById('modal_review_status').value = sub.review_status || 'published';

            document.getElementById('review_modal').classList.remove('hidden');
            document.getElementById('review_modal').classList.add('flex');
        }

        function closeReviewModal() {
            document.getElementById('review_modal').classList.add('hidden');
            document.getElementById('review_modal').classList.remove('flex');
        }
    </script>
</body>
</html>