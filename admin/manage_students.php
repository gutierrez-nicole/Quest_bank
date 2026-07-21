<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') { header("Location: ../index.php"); exit(); }

$host = 'localhost'; $dbname = 'bankquest_db'; $db_user = 'root'; $db_pass = '';
try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $db_user, $db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    $students = $pdo->query("
        SELECT u.id, u.fullname, u.username, u.email, s.student_number, s.course, s.year_level, s.section 
        FROM users u 
        LEFT JOIN student_details s ON u.id = s.user_id 
        WHERE u.role = 'student' 
        ORDER BY u.id DESC
    ")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { die("Database error: " . $e->getMessage()); }
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>QuestBank - Manage Students</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght=400;600;700;800&display=swap" rel="stylesheet">
    <style> body { font-family: 'Plus Jakarta Sans', sans-serif; } </style>
</head>
<body class="bg-[#f3f4f6] min-h-screen p-6 md:p-12">
    <div class="max-w-6xl mx-auto space-y-6">
        <div>
            <a href="dashboard.php" class="text-xs font-bold text-orange-600 hover:underline"><i class="fa-solid fa-arrow-left mr-1"></i> Back to Admin Dashboard</a>
            <h1 class="text-2xl font-extrabold text-stone-800 mt-2"><i class="fa-solid fa-graduation-cap text-orange-600 mr-1"></i> Student Directory Management</h1>
            <p class="text-xs text-stone-400">View and oversee registered student credentials, section assignments, and academic status.</p>
        </div>

        <div class="bg-white border border-stone-200 rounded-2xl p-6 shadow-sm">
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse text-xs text-stone-700">
                    <thead>
                        <tr class="bg-stone-50 border-b text-stone-500 font-bold uppercase text-[10px]">
                            <th class="p-3">Student Number</th>
                            <th class="p-3">Full Name</th>
                            <th class="p-3">Course & Section</th>
                            <th class="p-3">Email Address</th>
                            <th class="p-3 text-center">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y font-semibold">
                        <?php foreach ($students as $s): ?>
                            <tr class="hover:bg-stone-50/50 transition-all">
                                <td class="p-3 font-mono font-bold text-orange-600"><?php echo htmlspecialchars($s['student_number'] ?? '2026-N/A'); ?></td>
                                <td class="p-3 font-bold text-stone-800"><?php echo htmlspecialchars($s['fullname']); ?></td>
                                <td class="p-3"><span class="bg-orange-50 text-orange-700 px-2 py-0.5 rounded text-[10px] font-bold"><?php echo htmlspecialchars(($s['course'] ?? 'BSIT') . ' - ' . ($s['section'] ?? 'A')); ?></span></td>
                                <td class="p-3 text-stone-500"><?php echo htmlspecialchars($s['email']); ?></td>
                                <td class="p-3 text-center">
                                    <button onclick="alert('Student record updated!');" class="bg-stone-900 hover:bg-orange-600 text-white px-3 py-1.5 rounded-lg text-[10px] font-bold transition-all">Edit Record</button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</body>
</html>