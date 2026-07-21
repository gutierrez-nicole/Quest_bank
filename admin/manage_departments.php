<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') { header("Location: ../index.php"); exit(); }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>QuestBank - Department Management</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght=400;600;700;800&display=swap" rel="stylesheet">
    <style> body { font-family: 'Plus Jakarta Sans', sans-serif; } </style>
</head>
<body class="bg-[#f3f4f6] min-h-screen p-6 md:p-12">
    <div class="max-w-5xl mx-auto space-y-6">
        <div>
            <a href="dashboard.php" class="text-xs font-bold text-orange-600 hover:underline"><i class="fa-solid fa-arrow-left mr-1"></i> Back to Dashboard</a>
            <h1 class="text-2xl font-extrabold text-stone-800 mt-2"><i class="fa-solid fa-building-columns text-orange-600 mr-1"></i> Institutional Departments</h1>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-4">
            <div class="p-5 bg-white border rounded-2xl shadow-sm space-y-2">
                <div class="w-10 h-10 bg-orange-100 text-orange-600 rounded-xl flex items-center justify-center font-bold text-lg"><i class="fa-solid fa-laptop-code"></i></div>
                <h3 class="font-extrabold text-sm text-stone-800">College of Information Technology</h3>
                <p class="text-xs text-stone-400">Programs: BSIT, BSCS, BSIS</p>
            </div>
            <div class="p-5 bg-white border rounded-2xl shadow-sm space-y-2">
                <div class="w-10 h-10 bg-blue-100 text-blue-600 rounded-xl flex items-center justify-center font-bold text-lg"><i class="fa-solid fa-compass-drafting"></i></div>
                <h3 class="font-extrabold text-sm text-stone-800">College of Engineering</h3>
                <p class="text-xs text-stone-400">Programs: BSCpE, BSEE</p>
            </div>
            <div class="p-5 bg-white border rounded-2xl shadow-sm space-y-2">
                <div class="w-10 h-10 bg-purple-100 text-purple-600 rounded-xl flex items-center justify-center font-bold text-lg"><i class="fa-solid fa-user-ninja"></i></div>
                <h3 class="font-extrabold text-sm text-stone-800">College of Education</h3>
                <p class="text-xs text-stone-400">Programs: BSEd Major in ICT</p>
            </div>
        </div>
    </div>
</body>
</html>