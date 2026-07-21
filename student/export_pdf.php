<?php
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
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
    
    $stmt = $pdo->prepare("
        SELECT u.fullname, u.email, s.student_number, s.course, s.section 
        FROM users u 
        LEFT JOIN student_details s ON u.id = s.user_id 
        WHERE u.id = ?
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $student = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Database Error: " . $e->getMessage());
}

$title = $_GET['title'] ?? 'DevOps Principles Midterm Examination';
$score = $_GET['score'] ?? '9 / 10';
$percentage = $_GET['percentage'] ?? '90.0%';
$status = $_GET['status'] ?? 'Passed';
$date = date('F d, Y');
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>QuestBank - Examination Result Transcript</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght=400;600;700;800&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; }
        @media print {
            .no-print { display: none !important; }
            body { background-color: white; padding: 0; }
        }
    </style>
</head>
<body class="bg-stone-100 min-h-screen p-6 md:p-12 flex justify-center items-center">

    <div class="bg-white border border-stone-300 rounded-2xl max-w-2xl w-full p-8 shadow-xl space-y-6">
        
        <!-- OFFICIAL LETTERHEAD HEADER -->
        <div class="flex items-center justify-between border-b-2 border-stone-900 pb-4">
            <div>
                <h1 class="text-2xl font-black uppercase text-stone-900 tracking-tight">QUEST<span class="text-orange-600">BANK</span> ACADEMY</h1>
                <p class="text-xs font-bold text-stone-500 uppercase tracking-widest">Official Student Examination Evaluation Transcript</p>
            </div>
            <div class="text-right">
                <span class="px-3 py-1 rounded-md text-xs font-extrabold uppercase <?php echo ($status === 'Passed') ? 'bg-emerald-100 text-emerald-800 border border-emerald-300' : 'bg-rose-100 text-rose-800 border border-rose-300'; ?>">
                    <?php echo htmlspecialchars($status); ?>
                </span>
            </div>
        </div>

        <!-- STUDENT INFORMATION -->
        <div class="grid grid-cols-2 gap-4 text-xs font-semibold text-stone-700 bg-stone-50 p-4 rounded-xl border border-stone-200">
            <div>
                <p class="text-[10px] text-stone-400 uppercase font-bold">Student Name</p>
                <p class="text-sm font-extrabold text-stone-900"><?php echo htmlspecialchars($student['fullname'] ?? 'Student'); ?></p>
            </div>
            <div>
                <p class="text-[10px] text-stone-400 uppercase font-bold">ID Number</p>
                <p class="text-sm font-extrabold font-mono text-orange-600"><?php echo htmlspecialchars($student['student_number'] ?? '2026-STUDENT-001'); ?></p>
            </div>
            <div>
                <p class="text-[10px] text-stone-400 uppercase font-bold">Course & Section</p>
                <p><?php echo htmlspecialchars(($student['course'] ?? 'BSIT') . ' - ' . ($student['section'] ?? 'A')); ?></p>
            </div>
            <div>
                <p class="text-[10px] text-stone-400 uppercase font-bold">Date Evaluated</p>
                <p><?php echo $date; ?></p>
            </div>
        </div>

        <!-- EXAMINATION SUMMARY -->
        <div class="space-y-3">
            <h3 class="text-xs font-extrabold uppercase tracking-wider text-stone-500">Evaluation Metrics</h3>
            <div class="border rounded-xl p-4 space-y-2">
                <div class="flex justify-between text-xs">
                    <span class="font-bold text-stone-600">Exam Title:</span>
                    <span class="font-bold text-stone-900"><?php echo htmlspecialchars($title); ?></span>
                </div>
                <div class="flex justify-between text-xs">
                    <span class="font-bold text-stone-600">Verification Engine:</span>
                    <span class="font-bold text-purple-700">Groq AI Automated Optical Evaluator</span>
                </div>
                <div class="flex justify-between text-xs">
                    <span class="font-bold text-stone-600">Total Correct Score:</span>
                    <span class="font-mono font-bold text-stone-900"><?php echo htmlspecialchars($score); ?></span>
                </div>
                <div class="flex justify-between text-xs pt-2 border-t border-stone-100">
                    <span class="font-bold text-stone-600">Computed Final Percentage:</span>
                    <span class="text-base font-black text-orange-600"><?php echo htmlspecialchars($percentage); ?></span>
                </div>
            </div>
        </div>

        <!-- FOOTER & PRINT BUTTON -->
        <div class="pt-4 border-t border-stone-200 flex items-center justify-between">
            <p class="text-[10px] text-stone-400 italic">This transcript is automatically generated by QuestBank AI Assessment Engine.</p>
            <button onclick="window.print()" class="no-print bg-stone-900 hover:bg-orange-600 text-white font-bold text-xs px-5 py-2.5 rounded-xl shadow-md transition-all">
                Print or Save PDF
            </button>
        </div>

    </div>

</body>
</html>