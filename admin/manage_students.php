<?php
require_once __DIR__ . '/../app/database.php';
require_once __DIR__ . '/../app/session.php';
require_once __DIR__ . '/../includes/security.php';

requireRole('admin');
$pdo = getDBConnection();

$success_msg = "";
$error_msg = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_student'])) {
    validateCSRFToken();
    $student_id = intval($_POST['student_id']);
    $fullname = trim($_POST['fullname']);
    $student_number = trim($_POST['student_number']);
    $course = trim($_POST['course']);
    $year_level = intval($_POST['year_level']);
    $section = trim($_POST['section']);
    $email = trim($_POST['email']);

    if (!empty($student_id) && !empty($fullname) && !empty($email)) {
        try {
            $pdo->beginTransaction();

            $stmtUser = $pdo->prepare("UPDATE users SET fullname = ?, email = ? WHERE id = ? AND role = 'student'");
            $stmtUser->execute([$fullname, $email, $student_id]);

            $stmtCheck = $pdo->prepare("SELECT id FROM student_details WHERE user_id = ?");
            $stmtCheck->execute([$student_id]);
            
            if ($stmtCheck->fetch()) {
                $stmtDetails = $pdo->prepare("UPDATE student_details SET student_number = ?, course = ?, year_level = ?, section = ? WHERE user_id = ?");
                $stmtDetails->execute([$student_number, $course, $year_level, $section, $student_id]);
            } else {
                $stmtDetails = $pdo->prepare("INSERT INTO student_details (user_id, student_number, course, year_level, section) VALUES (?, ?, ?, ?, ?)");
                $stmtDetails->execute([$student_id, $student_number, $course, $year_level, $section]);
            }

            $pdo->commit();
            logActivity("Updated student directory profile and section assignment for '{$fullname}' ({$student_number}).");
            $success_msg = "Student record for '{$fullname}' updated successfully!";
        } catch (Exception $e) {
            $pdo->rollBack();
            $error_msg = "Failed to update student record: " . $e->getMessage();
        }
    } else {
        $error_msg = "Please fill in all required fields.";
    }
}

try {
    $students = $pdo->query("
        SELECT u.id, u.fullname, u.username, u.email, s.student_number, s.course, s.year_level, s.section 
        FROM users u 
        LEFT JOIN student_details s ON u.id = s.user_id 
        WHERE u.role = 'student' 
        ORDER BY u.id DESC
    ")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { 
    die("Database error: " . $e->getMessage()); 
}
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
<body class="bg-[#f3f4f6] min-h-screen flex">
    <?php require_once __DIR__ . '/../includes/admin_sidebar.php'; ?>
    <main class="flex-1 ml-16 lg:ml-64 p-6 md:p-12 overflow-y-auto min-h-screen">
        <div class="max-w-6xl mx-auto space-y-6">
            <div>
                <a href="dashboard.php" class="text-xs font-bold text-orange-600 hover:underline"><i class="fa-solid fa-arrow-left mr-1"></i> Back to Admin Dashboard</a>
                <h1 class="text-2xl font-extrabold text-stone-800 mt-2"><i class="fa-solid fa-graduation-cap text-orange-600 mr-1"></i> Student Directory Management</h1>
                <p class="text-xs text-stone-400">View and oversee registered student credentials, section assignments, and academic status.</p>
            </div>

            <?php if (!empty($success_msg)): ?>
                <div class="bg-emerald-50 border-l-4 border-emerald-500 p-4 rounded-xl text-xs font-semibold text-emerald-800 flex items-center justify-between shadow-sm">
                    <span class="flex items-center gap-2"><i class="fa-solid fa-circle-check text-emerald-600 text-sm"></i> <?php echo htmlspecialchars($success_msg); ?></span>
                    <button onclick="this.parentElement.remove();" class="text-emerald-500 hover:text-emerald-800"><i class="fa-solid fa-xmark"></i></button>
                </div>
            <?php endif; ?>
            <?php if (!empty($error_msg)): ?>
                <div class="bg-rose-50 border-l-4 border-rose-500 p-4 rounded-xl text-xs font-semibold text-rose-800 flex items-center justify-between shadow-sm">
                    <span class="flex items-center gap-2"><i class="fa-solid fa-circle-exclamation text-rose-600 text-sm"></i> <?php echo htmlspecialchars($error_msg); ?></span>
                    <button onclick="this.parentElement.remove();" class="text-rose-500 hover:text-rose-800"><i class="fa-solid fa-xmark"></i></button>
                </div>
            <?php endif; ?>

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
                                    <td class="p-3"><span class="bg-orange-50 text-orange-700 px-2 py-0.5 rounded text-[10px] font-bold"><?php echo htmlspecialchars(($s['course'] ?? 'BSCE') . ' - ' . ($s['section'] ?? 'A')); ?></span></td>
                                    <td class="p-3 text-stone-500"><?php echo htmlspecialchars($s['email']); ?></td>
                                    <td class="p-3 text-center">
                                        <button onclick="openEditStudentModal(<?php echo htmlspecialchars(json_encode($s), ENT_QUOTES, 'UTF-8'); ?>)" class="bg-stone-900 hover:bg-orange-600 text-white px-3 py-1.5 rounded-lg text-[10px] font-bold transition-all flex items-center justify-center gap-1 mx-auto shadow-xs">
                                            <i class="fa-solid fa-pen-to-square text-[9px]"></i> Edit Record
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </main>

    
    <div id="edit_student_modal" class="fixed inset-0 bg-stone-950/70 backdrop-blur-xs hidden items-center justify-center z-50 p-4">
        <div class="bg-white border border-stone-200 rounded-2xl max-w-md w-full p-6 space-y-4 shadow-2xl">
            <div class="flex items-center justify-between border-b pb-3">
                <h3 class="text-sm font-bold uppercase tracking-wider text-stone-800 flex items-center gap-2">
                    <i class="fa-solid fa-user-pen text-orange-600"></i> Edit Student Record
                </h3>
                <button onclick="closeEditStudentModal()" class="text-stone-400 hover:text-stone-700">
                    <i class="fa-solid fa-xmark text-sm"></i>
                </button>
            </div>

            <form action="manage_students.php" method="POST" class="space-y-3">
                <?php echo csrfInputField(); ?>
                <input type="hidden" name="student_id" id="edit_student_id">

                <div class="space-y-1">
                    <label class="text-[10px] font-bold uppercase text-stone-500">Full Name</label>
                    <input type="text" name="fullname" id="edit_fullname" required class="w-full bg-white border border-stone-200 rounded-xl p-2.5 text-xs outline-none focus:border-orange-500">
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div class="space-y-1">
                        <label class="text-[10px] font-bold uppercase text-stone-500">Student Number</label>
                        <input type="text" name="student_number" id="edit_student_number" required class="w-full bg-white border border-stone-200 rounded-xl p-2.5 text-xs outline-none focus:border-orange-500 font-mono">
                    </div>
                    <div class="space-y-1">
                        <label class="text-[10px] font-bold uppercase text-stone-500">Course / Degree</label>
                        <input type="text" name="course" id="edit_course" required class="w-full bg-white border border-stone-200 rounded-xl p-2.5 text-xs outline-none focus:border-orange-500">
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div class="space-y-1">
                        <label class="text-[10px] font-bold uppercase text-stone-500">Year Level</label>
                        <select name="year_level" id="edit_year_level" required class="w-full bg-white border border-stone-200 rounded-xl p-2.5 text-xs outline-none focus:border-orange-500">
                            <option value="1">1st Year</option>
                            <option value="2">2nd Year</option>
                            <option value="3">3rd Year</option>
                            <option value="4">4th Year</option>
                        </select>
                    </div>
                    <div class="space-y-1">
                        <label class="text-[10px] font-bold uppercase text-stone-500">Section</label>
                        <input type="text" name="section" id="edit_section" required class="w-full bg-white border border-stone-200 rounded-xl p-2.5 text-xs outline-none focus:border-orange-500">
                    </div>
                </div>

                <div class="space-y-1">
                    <label class="text-[10px] font-bold uppercase text-stone-500">Email Address</label>
                    <input type="email" name="email" id="edit_email" required class="w-full bg-white border border-stone-200 rounded-xl p-2.5 text-xs outline-none focus:border-orange-500">
                </div>

                <div class="flex justify-end gap-2 pt-3 border-t">
                    <button type="button" onclick="closeEditStudentModal()" class="px-4 py-2 bg-stone-200 text-stone-700 font-bold text-xs rounded-xl">Cancel</button>
                    <button type="submit" name="update_student" class="px-4 py-2 bg-orange-600 hover:bg-orange-700 text-white font-bold text-xs rounded-xl shadow-md transition-all">Save Changes</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openEditStudentModal(student) {
            document.getElementById('edit_student_id').value = student.id;
            document.getElementById('edit_fullname').value = student.fullname || '';
            document.getElementById('edit_student_number').value = student.student_number || '';
            document.getElementById('edit_course').value = student.course || 'BSCE';
            document.getElementById('edit_year_level').value = student.year_level || '4';
            document.getElementById('edit_section').value = student.section || 'A';
            document.getElementById('edit_email').value = student.email || '';

            document.getElementById('edit_student_modal').classList.remove('hidden');
            document.getElementById('edit_student_modal').classList.add('flex');
        }

        function closeEditStudentModal() {
            document.getElementById('edit_student_modal').classList.add('hidden');
            document.getElementById('edit_student_modal').classList.remove('flex');
        }
    </script>
</body>
</html>