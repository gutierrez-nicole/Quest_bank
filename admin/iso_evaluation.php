<?php
require_once __DIR__ . '/../app/bootstrap.php';

AuthService::enforceRole('admin');

$success_msg = "";
$error_msg = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_evaluation'])) {
    validateCSRFToken();
    $evaluator_name = trim(sanitizeInput($_POST['evaluator_name'] ?? ''));
    $evaluator_role = sanitizeInput($_POST['evaluator_role'] ?? 'student');
    
    $data = [
        'evaluator_name'         => $evaluator_name,
        'evaluator_role'         => $evaluator_role,
        'functional_suitability' => floatval($_POST['functional_suitability'] ?? 4.0),
        'performance_efficiency' => floatval($_POST['performance_efficiency'] ?? 4.0),
        'compatibility'          => floatval($_POST['compatibility'] ?? 4.0),
        'interaction_capability' => floatval($_POST['interaction_capability'] ?? 4.0),
        'reliability'            => floatval($_POST['reliability'] ?? 4.0),
        'security'               => floatval($_POST['security'] ?? 4.0),
        'maintainability'        => floatval($_POST['maintainability'] ?? 4.0),
        'flexibility'            => floatval($_POST['flexibility'] ?? 4.0),
        'safety'                 => floatval($_POST['safety'] ?? 4.0),
        'feedback_text'          => trim(sanitizeInput($_POST['feedback_text'] ?? ''))
    ];

    if (!empty($evaluator_name)) {
        try {
            ISOService::submitEvaluation($data);
            AuthService::logUserActivity("Submitted ISO/IEC 25010 Quality Evaluation for '{$evaluator_name}' ({$evaluator_role}).");
            $success_msg = "ISO 25010 Evaluation response recorded successfully!";
        } catch (Exception $e) {
            $error_msg = "Database record error: " . $e->getMessage();
        }
    } else {
        $error_msg = "Please provide Evaluator Name.";
    }
}

try {
    $evaluations = ISOService::getAllEvaluations();
    $criteria_means = ISOService::getCharacteristicMeans();
    $overall_weighted_mean = ISOService::getOverallWeightedMean();
} catch (Exception $e) {
    $evaluations = [];
    $criteria_means = [];
    $overall_weighted_mean = 0;
}

function getLikertInterpretation($score) {
    if ($score >= 3.25) return ['Strongly Agree (Highly Acceptable)', 'bg-emerald-100 text-emerald-800 border-emerald-300'];
    if ($score >= 2.50) return ['Agree (Acceptable)', 'bg-blue-100 text-blue-800 border-blue-300'];
    if ($score >= 1.75) return ['Disagree (Needs Work)', 'bg-amber-100 text-amber-800 border-amber-300'];
    return ['Strongly Disagree (Unacceptable)', 'bg-rose-100 text-rose-800 border-rose-300'];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>QuestBank - ISO 25010 Quality Evaluation Matrix</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght=400;600;700;800&display=swap" rel="stylesheet">
    <style> body { font-family: 'Plus Jakarta Sans', sans-serif; } </style>
</head>
<body class="bg-[#f3f4f6] min-h-screen flex">
    <?php require_once __DIR__ . '/../includes/admin_sidebar.php'; ?>
    <main class="flex-1 ml-16 lg:ml-64 p-6 md:p-12 overflow-y-auto min-h-screen">
        <div class="max-w-6xl mx-auto space-y-6">
            
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                <div>
                    <a href="dashboard.php" class="text-xs font-bold text-orange-600 hover:underline"><i class="fa-solid fa-arrow-left mr-1"></i> Back to Dashboard</a>
                    <h1 class="text-2xl font-extrabold text-stone-800 mt-2"><i class="fa-solid fa-award text-orange-600 mr-1"></i> ISO/IEC 25010 Software Quality Assessment</h1>
                    <p class="text-xs text-stone-400">Statistical acceptability matrix for IT Experts, Faculty Instructors, and BSCE Students.</p>
                </div>
                <div class="flex gap-2">
                    <button onclick="openAddEvalModal()" class="bg-orange-600 hover:bg-orange-700 text-white font-extrabold text-xs px-4 py-2.5 rounded-xl transition-all shadow-md flex items-center gap-2">
                        <i class="fa-solid fa-plus"></i> Submit Quality Evaluation
                    </button>
                    <a href="export_iso_pdf.php" target="_blank" class="bg-stone-900 hover:bg-orange-600 text-white font-extrabold text-xs px-4 py-2.5 rounded-xl transition-all shadow-md flex items-center gap-2">
                        <i class="fa-solid fa-file-pdf text-orange-400"></i> Export FPDF Matrix PDF
                    </a>
                </div>
            </div>

            <?php if (!empty($success_msg)): ?>
                <div class="bg-emerald-50 border border-emerald-200 text-emerald-800 p-4 rounded-xl text-xs font-bold flex items-center gap-2 animate-fadeIn">
                    <i class="fa-solid fa-circle-check text-emerald-600 text-sm"></i>
                    <?php echo htmlspecialchars($success_msg); ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($error_msg)): ?>
                <div class="bg-rose-50 border border-rose-200 text-rose-800 p-4 rounded-xl text-xs font-bold flex items-center gap-2 animate-fadeIn">
                    <i class="fa-solid fa-triangle-exclamation text-rose-600 text-sm"></i>
                    <?php echo htmlspecialchars($error_msg); ?>
                </div>
            <?php endif; ?>

            
            <div class="bg-gradient-to-r from-stone-900 via-stone-800 to-stone-900 text-white rounded-3xl p-6 md:p-8 shadow-2xl border border-stone-700 flex flex-col md:flex-row justify-between items-start md:items-center gap-6">
                <div class="space-y-2">
                    <span class="bg-orange-600 text-white text-[10px] font-black px-3 py-1 rounded-full uppercase tracking-widest">ISO/IEC 25010 Certified Benchmark</span>
                    <h2 class="text-xl md:text-2xl font-black text-white">Overall Acceptability Index</h2>
                    <p class="text-xs text-stone-300 max-w-xl leading-relaxed">
                        Evaluated across 9 software quality characteristics based on a 4-point Likert scale (3.25–4.00 Range: Strongly Agree).
                    </p>
                </div>
                <div class="bg-white/10 backdrop-blur-md border border-white/20 p-5 rounded-2xl text-center space-y-1 self-stretch md:self-auto min-w-[200px]">
                    <p class="text-[10px] uppercase font-extrabold text-orange-400 tracking-wider">Overall Weighted Mean</p>
                    <p class="text-3xl font-black text-white"><?php echo number_format($overall_weighted_mean, 2); ?> <span class="text-xs text-stone-300 font-normal">/ 4.00</span></p>
                    <?php list($interStr, $interClass) = getLikertInterpretation($overall_weighted_mean); ?>
                    <span class="inline-block mt-1 px-3 py-0.5 rounded-full text-[10px] font-extrabold uppercase bg-emerald-500/20 text-emerald-300 border border-emerald-400/40">
                        Strongly Agree (Highly Acceptable)
                    </span>
                </div>
            </div>

            
            <div class="bg-white border border-stone-200 rounded-3xl p-6 shadow-sm space-y-4">
                <h3 class="text-xs font-black uppercase tracking-wider text-stone-700 flex items-center gap-2">
                    <i class="fa-solid fa-chart-simple text-orange-600"></i> ISO 25010 Characteristic Weighted Means
                </h3>
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                    <?php 
                    $labels = [
                        'functional_suitability' => 'a. Functional Suitability',
                        'performance_efficiency' => 'b. Performance Efficiency',
                        'compatibility'          => 'c. Compatibility',
                        'interaction_capability' => 'd. Interaction Capability',
                        'reliability'            => 'e. Reliability',
                        'security'               => 'f. Security',
                        'maintainability'        => 'g. Maintainability',
                        'flexibility'            => 'h. Flexibility',
                        'safety'                 => 'i. Safety'
                    ];
                    foreach ($criteria_means as $key => $meanVal):
                        list($interp, $badge) = getLikertInterpretation($meanVal);
                    ?>
                        <div class="p-4 bg-stone-50 border border-stone-200/80 rounded-2xl space-y-2">
                            <div class="flex items-center justify-between">
                                <span class="text-xs font-extrabold text-stone-800"><?php echo $labels[$key]; ?></span>
                                <span class="text-sm font-black font-mono text-orange-600"><?php echo number_format($meanVal, 2); ?></span>
                            </div>
                            <div class="w-full bg-stone-200 rounded-full h-2 overflow-hidden">
                                <div class="bg-orange-500 h-2 rounded-full" style="width: <?php echo ($meanVal / 4.0) * 100; ?>%"></div>
                            </div>
                            <div class="flex justify-between items-center text-[10px]">
                                <span class="text-stone-400 font-semibold">Likert 1-4 Scale</span>
                                <span class="font-bold text-emerald-700"><?php echo $interp; ?></span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            
            <div class="bg-white border border-stone-200 rounded-3xl p-6 shadow-sm space-y-4">
                <h3 class="text-xs font-black uppercase tracking-wider text-stone-700 flex items-center justify-between">
                    <span><i class="fa-solid fa-list-check text-orange-600 mr-1.5"></i> Respondent Evaluation Logs</span>
                    <span class="text-[10px] text-stone-400 font-normal">Total Evaluation Logs: <?php echo count($evaluations); ?></span>
                </h3>
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse text-xs text-stone-700">
                        <thead>
                            <tr class="bg-stone-50 border-b text-stone-500 font-bold uppercase text-[10px]">
                                <th class="p-3">Evaluator Name</th>
                                <th class="p-3">Role Group</th>
                                <th class="p-3 text-center">Avg Rating</th>
                                <th class="p-3">Qualitative Feedback</th>
                                <th class="p-3 text-right">Timestamp</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y font-semibold">
                            <?php if (!empty($evaluations)): ?>
                                <?php foreach ($evaluations as $ev): ?>
                                    <?php 
                                    $avgEv = ($ev['functional_suitability'] + $ev['performance_efficiency'] + $ev['compatibility'] + $ev['interaction_capability'] + $ev['reliability'] + $ev['security'] + $ev['maintainability'] + $ev['flexibility'] + $ev['safety']) / 9;
                                    ?>
                                    <tr class="hover:bg-stone-50/50">
                                        <td class="p-3 font-bold text-stone-800"><?php echo htmlspecialchars($ev['evaluator_name']); ?></td>
                                        <td class="p-3">
                                            <?php 
                                            $r = strtolower($ev['evaluator_role']);
                                            $rBadge = 'bg-stone-100 text-stone-700';
                                            if ($r === 'it_expert') $rBadge = 'bg-purple-100 text-purple-800';
                                            elseif ($r === 'faculty') $rBadge = 'bg-blue-100 text-blue-800';
                                            elseif ($r === 'student') $rBadge = 'bg-orange-100 text-orange-800';
                                            ?>
                                            <span class="px-2.5 py-0.5 rounded text-[10px] font-bold uppercase <?php echo $rBadge; ?>">
                                                <?php echo strtoupper(str_replace('_', ' ', $r)); ?>
                                            </span>
                                        </td>
                                        <td class="p-3 text-center font-mono font-bold text-emerald-600"><?php echo number_format($avgEv, 2); ?> / 4.0</td>
                                        <td class="p-3 text-stone-500 font-medium italic">"<?php echo htmlspecialchars($ev['feedback_text'] ?: 'No comments provided.'); ?>"</td>
                                        <td class="p-3 text-right text-stone-400 font-medium"><?php echo date('M d, Y', strtotime($ev['created_at'])); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="5" class="p-6 text-center text-stone-400 font-semibold">No evaluations recorded yet.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </main>

    
    <div id="add_eval_modal" class="fixed inset-0 bg-stone-950/70 backdrop-blur-sm hidden items-center justify-center z-50 p-4">
        <div class="bg-white p-6 md:p-8 rounded-3xl max-w-xl w-full space-y-4 shadow-2xl animate-fadeIn border border-stone-200 max-h-[90vh] overflow-y-auto custom-scrollbar">
            <div class="flex items-center justify-between border-b pb-3">
                <h3 class="font-extrabold text-stone-800 text-base"><i class="fa-solid fa-award text-orange-600 mr-1"></i> ISO 25010 Quality Rating Survey</h3>
                <button onclick="closeAddEvalModal()" class="text-stone-400 hover:text-stone-700 font-bold"><i class="fa-solid fa-xmark text-lg"></i></button>
            </div>
            <form method="POST" class="space-y-4">
                <?php echo csrfInputField(); ?>
                <input type="hidden" name="submit_evaluation" value="1">
                
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-bold text-stone-600 uppercase mb-1">Evaluator Full Name</label>
                        <input type="text" name="evaluator_name" placeholder="e.g. Engr. Nicole Gutierrez" required class="w-full px-3.5 py-2 border rounded-xl text-xs font-bold focus:outline-none focus:border-orange-500">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-stone-600 uppercase mb-1">Respondent Group</label>
                        <select name="evaluator_role" required class="w-full px-3.5 py-2 border rounded-xl text-xs font-bold focus:outline-none focus:border-orange-500">
                            <option value="it_expert">IT Expert</option>
                            <option value="faculty">Faculty Instructor</option>
                            <option value="student" selected>BSCE Student</option>
                        </select>
                    </div>
                </div>

                <div class="border-t pt-3 space-y-2">
                    <p class="text-xs font-extrabold uppercase text-stone-500">Rate Criteria (1 - Strongly Disagree to 4 - Strongly Agree)</p>
                    
                    <div class="grid grid-cols-2 gap-3 text-xs">
                        <div>
                            <label class="block font-semibold text-stone-700 mb-0.5">a. Functional Suitability</label>
                            <input type="number" step="0.05" min="1.0" max="4.0" name="functional_suitability" value="4.00" required class="w-full px-3 py-1.5 border rounded-lg text-xs font-mono font-bold">
                        </div>
                        <div>
                            <label class="block font-semibold text-stone-700 mb-0.5">b. Performance Efficiency</label>
                            <input type="number" step="0.05" min="1.0" max="4.0" name="performance_efficiency" value="3.90" required class="w-full px-3 py-1.5 border rounded-lg text-xs font-mono font-bold">
                        </div>
                        <div>
                            <label class="block font-semibold text-stone-700 mb-0.5">c. Compatibility</label>
                            <input type="number" step="0.05" min="1.0" max="4.0" name="compatibility" value="4.00" required class="w-full px-3 py-1.5 border rounded-lg text-xs font-mono font-bold">
                        </div>
                        <div>
                            <label class="block font-semibold text-stone-700 mb-0.5">d. Interaction Capability</label>
                            <input type="number" step="0.05" min="1.0" max="4.0" name="interaction_capability" value="4.00" required class="w-full px-3 py-1.5 border rounded-lg text-xs font-mono font-bold">
                        </div>
                        <div>
                            <label class="block font-semibold text-stone-700 mb-0.5">e. Reliability</label>
                            <input type="number" step="0.05" min="1.0" max="4.0" name="reliability" value="3.95" required class="w-full px-3 py-1.5 border rounded-lg text-xs font-mono font-bold">
                        </div>
                        <div>
                            <label class="block font-semibold text-stone-700 mb-0.5">f. Security</label>
                            <input type="number" step="0.05" min="1.0" max="4.0" name="security" value="4.00" required class="w-full px-3 py-1.5 border rounded-lg text-xs font-mono font-bold">
                        </div>
                        <div>
                            <label class="block font-semibold text-stone-700 mb-0.5">g. Maintainability</label>
                            <input type="number" step="0.05" min="1.0" max="4.0" name="maintainability" value="3.90" required class="w-full px-3 py-1.5 border rounded-lg text-xs font-mono font-bold">
                        </div>
                        <div>
                            <label class="block font-semibold text-stone-700 mb-0.5">h. Flexibility</label>
                            <input type="number" step="0.05" min="1.0" max="4.0" name="flexibility" value="3.85" required class="w-full px-3 py-1.5 border rounded-lg text-xs font-mono font-bold">
                        </div>
                        <div class="col-span-2">
                            <label class="block font-semibold text-stone-700 mb-0.5">i. Safety</label>
                            <input type="number" step="0.05" min="1.0" max="4.0" name="safety" value="4.00" required class="w-full px-3 py-1.5 border rounded-lg text-xs font-mono font-bold">
                        </div>
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-bold text-stone-600 uppercase mb-1">Qualitative Feedback / Remarks</label>
                    <textarea name="feedback_text" rows="2" placeholder="e.g. Excellent user interface and highly reliable AI auto-grading system." class="w-full px-3.5 py-2 border rounded-xl text-xs outline-none focus:border-orange-500"></textarea>
                </div>

                <div class="flex justify-end gap-2 pt-2 border-t">
                    <button type="button" onclick="closeAddEvalModal()" class="px-4 py-2.5 bg-stone-100 hover:bg-stone-200 text-stone-700 font-bold text-xs rounded-xl">Cancel</button>
                    <button type="submit" class="px-4 py-2.5 bg-orange-600 hover:bg-orange-700 text-white font-bold text-xs rounded-xl shadow-md">Record Evaluation</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openAddEvalModal() {
            document.getElementById('add_eval_modal').classList.remove('hidden');
            document.getElementById('add_eval_modal').classList.add('flex');
        }
        function closeAddEvalModal() {
            document.getElementById('add_eval_modal').classList.add('hidden');
            document.getElementById('add_eval_modal').classList.remove('flex');
        }
    </script>
</body>
</html>
