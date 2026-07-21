<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: ../index.php");
    exit();
}

if ($_SESSION['role'] !== 'teacher') {
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
} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}

$success_msg = "";
$error_msg = "";

// Save New Exam Header and Questions
if ($_SERVER['REQUEST_METHOD'] === 'POST' and isset($_POST['save_exam'])) {
    $title = trim($_POST['title']);
    $subject = trim($_POST['subject']);
    $time_limit = intval($_POST['time_limit']);
    
    $questions = $_POST['questions'] ?? [];

    if (!empty($title) and !empty($subject) and !empty($questions)) {
        try {
            $pdo->beginTransaction();

            // Insert Exam Record
            $stmt = $pdo->prepare("INSERT INTO exams (teacher_id, title, subject, time_limit, total_items) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$_SESSION['user_id'], $title, $subject, $time_limit, count($questions)]);
            $exam_id = $pdo->lastInsertId();

            // Insert Question Items
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
            $success_msg = "Exam and Answer Key created successfully!";
        } catch (Exception $e) {
            $pdo->rollBack();
            $error_msg = "Failed to save exam: " . $e->getMessage();
        }
    } else {
        $error_msg = "Please fill in all details and add at least one question.";
    }
}

// Fetch Existing Exams
$stmtExams = $pdo->prepare("SELECT * FROM exams WHERE teacher_id = ? ORDER BY id DESC");
$stmtExams->execute([$_SESSION['user_id']]);
$existing_exams = $stmtExams->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>QuestBank - Create Exam & Question Bank</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght=400;600;700;800&display=swap" rel="stylesheet">
    <style> body { font-family: 'Plus Jakarta Sans', sans-serif; } </style>
</head>
<body class="bg-[#fffbf7] min-h-screen p-6 md:p-12">

    <div class="max-w-6xl mx-auto space-y-6">
        <div class="flex items-center justify-between">
            <div>
                <a href="dashboard.php" class="text-xs font-bold text-orange-600 hover:underline"><i class="fa-solid fa-arrow-left mr-1"></i> Back to Dashboard</a>
                <h1 class="text-2xl font-extrabold text-stone-800 mt-2"><i class="fa-solid fa-file-circle-plus text-orange-600 mr-1"></i> Create Exam & Question Bank</h1>
                <p class="text-xs text-stone-400">Design tests, assign answer keys, and save items for automated scoring.</p>
            </div>
        </div>

        <?php if (!empty($success_msg)): ?>
            <div class="bg-emerald-50 border-l-4 border-emerald-500 p-4 rounded-xl text-xs font-semibold text-emerald-700"><?php echo $success_msg; ?></div>
        <?php endif; ?>
        <?php if (!empty($error_msg)): ?>
            <div class="bg-red-50 border-l-4 border-red-500 p-4 rounded-xl text-xs font-semibold text-red-700"><?php echo $error_msg; ?></div>
        <?php endif; ?>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            
            <!-- LEFT FORM: EXAM CREATOR -->
            <div class="lg:col-span-2 bg-white border border-stone-200 rounded-2xl p-6 shadow-sm space-y-6">
                <form action="create_exam.php" method="POST" id="examForm" class="space-y-6">
                    <div class="border-b pb-4">
                        <h3 class="text-sm font-bold uppercase tracking-wider text-stone-700"><i class="fa-solid fa-sliders text-orange-500 mr-1"></i> Exam Parameters</h3>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                        <div class="space-y-1">
                            <label class="text-xs font-bold text-stone-600">Exam Title</label>
                            <input type="text" name="title" required placeholder="e.g. Midterm Assessment" class="w-full bg-stone-50 border border-stone-200 rounded-xl px-4 py-2 text-xs outline-none focus:border-orange-500">
                        </div>
                        <div class="space-y-1">
                            <label class="text-xs font-bold text-stone-600">Subject</label>
                            <input type="text" name="subject" required placeholder="e.g. DevOps" class="w-full bg-stone-50 border border-stone-200 rounded-xl px-4 py-2 text-xs outline-none focus:border-orange-500">
                        </div>
                        <div class="space-y-1">
                            <label class="text-xs font-bold text-stone-600">Time Limit (Mins)</label>
                            <input type="number" name="time_limit" value="60" required class="w-full bg-stone-50 border border-stone-200 rounded-xl px-4 py-2 text-xs outline-none focus:border-orange-500">
                        </div>
                    </div>

                    <!-- DYNAMIC QUESTIONS CONTAINER -->
                    <div class="space-y-4 pt-4 border-t">
                        <div class="flex items-center justify-between">
                            <h3 class="text-sm font-bold uppercase tracking-wider text-stone-700"><i class="fa-solid fa-list-check text-orange-500 mr-1"></i> Question Items</h3>
                            <button type="button" onclick="addQuestion()" class="bg-stone-900 hover:bg-orange-600 text-white font-bold text-xs px-3 py-1.5 rounded-xl transition-all">
                                <i class="fa-solid fa-plus mr-1"></i> Add Item
                            </button>
                        </div>

                        <div id="questions_container" class="space-y-4">
                            <!-- Items will be dynamically added via JavaScript -->
                        </div>
                    </div>

                    <button type="submit" name="save_exam" class="w-full bg-orange-600 hover:bg-orange-700 text-white font-bold text-xs py-3 rounded-xl shadow-md transition-all">
                        <i class="fa-solid fa-floppy-disk mr-1"></i> Save Exam to Question Bank
                    </button>
                </form>
            </div>

            <!-- RIGHT PANEL: EXISTING EXAMS LIST -->
            <div class="bg-white border border-stone-200 rounded-2xl p-6 shadow-sm space-y-4 h-fit">
                <h3 class="text-sm font-bold uppercase tracking-wider text-stone-700 border-b pb-3"><i class="fa-solid fa-database text-orange-500 mr-1"></i> Saved Question Bank</h3>
                
                <?php if (!empty($existing_exams)): ?>
                    <div class="space-y-3">
                        <?php foreach ($existing_exams as $ex): ?>
                            <div class="p-3 border border-stone-100 rounded-xl bg-stone-50/50 hover:border-orange-300 transition-all space-y-1">
                                <div class="flex items-center justify-between">
                                    <h4 class="font-bold text-xs text-stone-800"><?php echo htmlspecialchars($ex['title']); ?></h4>
                                    <span class="text-[10px] bg-orange-100 text-orange-700 font-bold px-2 py-0.5 rounded"><?php echo htmlspecialchars($ex['subject']); ?></span>
                                </div>
                                <p class="text-[11px] text-stone-500">Items: <strong><?php echo $ex['total_items']; ?></strong> | Time: <strong><?php echo $ex['time_limit']; ?> mins</strong></p>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p class="text-xs text-stone-400 text-center py-6">No exams created yet.</p>
                <?php endif; ?>
            </div>

        </div>
    </div>

    <script>
        let questionCount = 0;

        function addQuestion() {
            questionCount++;
            const container = document.getElementById('questions_container');
            
            const qBlock = document.createElement('div');
            qBlock.className = 'p-4 border border-stone-200 rounded-xl bg-stone-50/30 space-y-3';
            qBlock.id = `q_block_${questionCount}`;
            
            qBlock.innerHTML = `
                <div class="flex items-center justify-between">
                    <span class="font-bold text-xs text-stone-700">Question #${questionCount}</span>
                    <button type="button" onclick="removeQuestion(${questionCount})" class="text-xs text-rose-500 hover:underline font-bold">Remove</button>
                </div>

                <textarea name="questions[${questionCount}][text]" required rows="2" placeholder="Type question here..." class="w-full bg-white border border-stone-200 rounded-lg p-2.5 text-xs outline-none focus:border-orange-500 resize-none"></textarea>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="text-[10px] font-bold text-stone-500 uppercase">Question Type</label>
                        <select name="questions[${questionCount}][type]" class="w-full bg-white border border-stone-200 rounded-lg p-2 text-xs outline-none">
                            <option value="multiple_choice">Multiple Choice</option>
                            <option value="identification">Identification / Direct Text</option>
                        </select>
                    </div>
                    <div>
                        <label class="text-[10px] font-bold text-stone-500 uppercase">Correct Answer Key</label>
                        <input type="text" name="questions[${questionCount}][correct]" required placeholder="Expected Answer" class="w-full bg-white border border-stone-200 rounded-lg p-2 text-xs outline-none focus:border-orange-500 font-bold text-emerald-700">
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
    </script>
</body>
</html>