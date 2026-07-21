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

$teacher_id = $_SESSION['user_id'];

// Filter Handler
$selected_exam = $_GET['exam_title'] ?? 'all';

// Query Parameters
if ($selected_exam !== 'all') {
    $stmtStats = $pdo->prepare("
        SELECT 
            COUNT(*) as total_students,
            SUM(CASE WHEN status = 'Pass' THEN 1 ELSE 0 END) as total_pass,
            SUM(CASE WHEN status = 'Fail' THEN 1 ELSE 0 END) as total_fail,
            AVG(percentage) as avg_percentage,
            MAX(percentage) as max_percentage,
            MIN(percentage) as min_percentage
        FROM exam_submissions 
        WHERE teacher_id = ? AND exam_title = ?
    ");
    $stmtStats->execute([$teacher_id, $selected_exam]);

    $stmtList = $pdo->prepare("SELECT * FROM exam_submissions WHERE teacher_id = ? AND exam_title = ? ORDER BY id DESC");
    $stmtList->execute([$teacher_id, $selected_exam]);
} else {
    $stmtStats = $pdo->prepare("
        SELECT 
            COUNT(*) as total_students,
            SUM(CASE WHEN status = 'Pass' THEN 1 ELSE 0 END) as total_pass,
            SUM(CASE WHEN status = 'Fail' THEN 1 ELSE 0 END) as total_fail,
            AVG(percentage) as avg_percentage,
            MAX(percentage) as max_percentage,
            MIN(percentage) as min_percentage
        FROM exam_submissions 
        WHERE teacher_id = ?
    ");
    $stmtStats->execute([$teacher_id]);

    $stmtList = $pdo->prepare("SELECT * FROM exam_submissions WHERE teacher_id = ? ORDER BY id DESC");
    $stmtList->execute([$teacher_id]);
}

$stats = $stmtStats->fetch(PDO::FETCH_ASSOC);
$submissions = $stmtList->fetchAll(PDO::FETCH_ASSOC);

// Fetch Distinct Exam Titles for Filter Dropdown
$stmtExams = $pdo->prepare("SELECT DISTINCT exam_title FROM exam_submissions WHERE teacher_id = ?");
$stmtExams->execute([$teacher_id]);
$exam_options = $stmtExams->fetchAll(PDO::FETCH_COLUMN);

// Computations
$total = intval($stats['total_students'] ?? 0);
$pass = intval($stats['total_pass'] ?? 0);
$fail = intval($stats['total_fail'] ?? 0);
$avg = floatval($stats['avg_percentage'] ?? 0);
$max = floatval($stats['max_percentage'] ?? 0);
$min = floatval($stats['min_percentage'] ?? 0);
$pass_rate = $total > 0 ? ($pass / $total) * 100 : 0;
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
<body class="bg-[#fffbf7] min-h-screen p-6 md:p-12">

    <div class="max-w-6xl mx-auto space-y-6">
        
        <!-- HEADERBAR -->
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <a href="dashboard.php" class="text-xs font-bold text-orange-600 hover:underline"><i class="fa-solid fa-arrow-left mr-1"></i> Back to Dashboard</a>
                <h1 class="text-2xl font-extrabold text-stone-800 mt-2"><i class="fa-solid fa-chart-pie text-orange-600 mr-1"></i> Class Performance & Analytics</h1>
                <p class="text-xs text-stone-400">Statistical breakdown of OCR scanned papers and student scores.</p>
            </div>

            <!-- EXAM FILTER DROPDOWN -->
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

        <!-- STATISTICAL METRICS CARDS -->
        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-4">
            <div class="bg-white p-4 border border-stone-200 rounded-2xl shadow-sm text-center">
                <p class="text-[10px] font-bold uppercase text-stone-400">Total Scanned</p>
                <p class="text-2xl font-black text-stone-800 mt-1"><?php echo $total; ?></p>
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
                <p class="text-2xl font-black text-stone-800 mt-1"><?php echo number_format($avg, 1); ?>%</p>
            </div>
            <div class="bg-white p-4 border border-stone-200 rounded-2xl shadow-sm text-center">
                <p class="text-[10px] font-bold uppercase text-stone-400">Highest Score</p>
                <p class="text-2xl font-black text-emerald-600 mt-1"><?php echo number_format($max, 1); ?>%</p>
            </div>
        </div>

        <!-- DETAILED EVALUATION RECORDS TABLE -->
        <div class="bg-white border border-stone-200 rounded-2xl p-6 shadow-sm space-y-4">
            <div class="flex items-center justify-between border-b pb-3">
                <h3 class="text-sm font-bold uppercase tracking-wider text-stone-700">
                    <i class="fa-solid fa-list text-orange-500 mr-1"></i> Student Grade Submissions Master List
                </h3>
                <button onclick="window.print()" class="bg-stone-900 hover:bg-orange-600 text-white font-bold text-xs px-4 py-2 rounded-xl transition-all shadow-sm">
                    <i class="fa-solid fa-print mr-1"></i> Print / Export Report
                </button>
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
                                <th class="p-3 text-center">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-stone-100 font-medium text-stone-700">
                            <?php foreach ($submissions as $sub): ?>
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

</body>
</html>