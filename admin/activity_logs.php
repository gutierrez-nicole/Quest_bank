<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') { header("Location: ../index.php"); exit(); }

$host = 'localhost'; $dbname = 'bankquest_db'; $db_user = 'root'; $db_pass = '';
try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $db_user, $db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    $logs = $pdo->query("SELECT * FROM exam_submissions ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { die("Database error: " . $e->getMessage()); }
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>QuestBank - Audit Trail & Activity Logs</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght=400;600;700;800&display=swap" rel="stylesheet">
    <style> body { font-family: 'Plus Jakarta Sans', sans-serif; } </style>
</head>
<body class="bg-[#f3f4f6] min-h-screen p-6 md:p-12">
    <div class="max-w-6xl mx-auto space-y-6">
        <div class="flex justify-between items-center">
            <div>
                <a href="dashboard.php" class="text-xs font-bold text-orange-600 hover:underline"><i class="fa-solid fa-arrow-left mr-1"></i> Back to Dashboard</a>
                <h1 class="text-2xl font-extrabold text-stone-800 mt-2"><i class="fa-solid fa-clipboard-list text-orange-600 mr-1"></i> Global System Activity Audit Log</h1>
            </div>
            <button onclick="window.print()" class="bg-stone-900 hover:bg-orange-600 text-white font-bold text-xs px-4 py-2.5 rounded-xl transition-all">
                <i class="fa-solid fa-print mr-1"></i> Print Audit Trail
            </button>
        </div>

        <div class="bg-white border border-stone-200 rounded-2xl p-6 shadow-sm">
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse text-xs text-stone-700">
                    <thead>
                        <tr class="bg-stone-50 border-b text-stone-500 font-bold uppercase text-[10px]">
                            <th class="p-3">Log Event ID</th>
                            <th class="p-3">Student Target Name</th>
                            <th class="p-3">Exam Action Processed</th>
                            <th class="p-3 text-center">Score Recorded</th>
                            <th class="p-3 text-center">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y font-semibold">
                        <?php foreach ($logs as $l): ?>
                            <tr class="hover:bg-stone-50/50">
                                <td class="p-3 font-mono text-orange-600">#LOG-00<?php echo $l['id']; ?></td>
                                <td class="p-3 font-bold text-stone-800"><?php echo htmlspecialchars($l['student_name']); ?></td>
                                <td class="p-3"><?php echo htmlspecialchars($l['exam_title']); ?></td>
                                <td class="p-3 text-center font-bold font-mono"><?php echo $l['correct_count'] . ' / ' . $l['total_items']; ?></td>
                                <td class="p-3 text-center"><span class="px-2 py-0.5 rounded text-[10px] font-bold <?php echo $l['status'] === 'Pass' ? 'bg-emerald-100 text-emerald-800' : 'bg-rose-100 text-rose-800'; ?>"><?php echo htmlspecialchars($l['status']); ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</body>
</html>