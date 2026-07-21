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

// 1. Add New Section
if ($_SERVER['REQUEST_METHOD'] === 'POST' and isset($_POST['add_section'])) {
    $section_name = trim($_POST['section_name']);
    $course_name = trim($_POST['course_name']);
    $academic_year = trim($_POST['academic_year']);

    if (!empty($section_name) and !empty($course_name)) {
        try {
            $stmt = $pdo->prepare("INSERT INTO sections (teacher_id, section_name, course_name, academic_year) VALUES (?, ?, ?, ?)");
            $stmt->execute([$_SESSION['user_id'], $section_name, $course_name, $academic_year]);
            $success_msg = "New section successfully created!";
        } catch (PDOException $e) {
            $error_msg = "Failed to add section: " . $e->getMessage();
        }
    } else {
        $error_msg = "Please fill in all required section details.";
    }
}

// 2. Add New Student
if ($_SERVER['REQUEST_METHOD'] === 'POST' and isset($_POST['add_student'])) {
    $student_number = trim($_POST['student_number']);
    $fullname = trim($_POST['fullname']);
    $email = trim($_POST['email']);
    $section_id = intval($_POST['section_id']);

    if (!empty($student_number) and !empty($fullname) and $section_id > 0) {
        try {
            $stmt = $pdo->prepare("INSERT INTO students (teacher_id, section_id, student_number, fullname, email) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$_SESSION['user_id'], $section_id, $student_number, $fullname, $email]);
            $success_msg = "Student enrolled successfully!";
        } catch (PDOException $e) {
            $error_msg = "Error adding student (Student Number might already exist): " . $e->getMessage();
        }
    } else {
        $error_msg = "Please complete all student fields and select a valid section.";
    }
}

// Fetch Teacher's Sections
$stmtSec = $pdo->prepare("SELECT * FROM sections WHERE teacher_id = ? ORDER BY id DESC");
$stmtSec->execute([$_SESSION['user_id']]);
$sections = $stmtSec->fetchAll(PDO::FETCH_ASSOC);

// Fetch All Enrolled Students with Section Info
$stmtStud = $pdo->prepare("
    SELECT s.*, sec.section_name, sec.course_name 
    FROM students s 
    JOIN sections sec ON s.section_id = sec.id 
    WHERE s.teacher_id = ? 
    ORDER BY s.id DESC
");
$stmtStud->execute([$_SESSION['user_id']]);
$students = $stmtStud->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>QuestBank - Manage Students & Sections</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet">
    <style> body { font-family: 'Plus Jakarta Sans', sans-serif; } </style>
</head>
<body class="bg-[#fffbf7] min-h-screen p-6 md:p-12">

    <div class="max-w-6xl mx-auto space-y-6">
        <div class="flex items-center justify-between">
            <div>
                <a href="dashboard.php" class="text-xs font-bold text-orange-600 hover:underline"><i class="fa-solid fa-arrow-left mr-1"></i> Back to Dashboard</a>
                <h1 class="text-2xl font-extrabold text-stone-800 mt-2"><i class="fa-solid fa-graduation-cap text-orange-600 mr-1"></i> Manage Students & Sections</h1>
                <p class="text-xs text-stone-400">Organize class sections, enroll students, and manage class rosters.</p>
            </div>
        </div>

        <?php if (!empty($success_msg)): ?>
            <div class="bg-emerald-50 border-l-4 border-emerald-500 p-4 rounded-xl text-xs font-semibold text-emerald-700"><?php echo $success_msg; ?></div>
        <?php endif; ?>
        <?php if (!empty($error_msg)): ?>
            <div class="bg-red-50 border-l-4 border-red-500 p-4 rounded-xl text-xs font-semibold text-red-700"><?php echo $error_msg; ?></div>
        <?php endif; ?>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            
            <!-- LEFT COLUMN: FORMS (SECTION & STUDENT) -->
            <div class="space-y-6">
                
                <!-- 1. ADD SECTION FORM -->
                <div class="bg-white border border-stone-200 rounded-2xl p-5 shadow-sm space-y-4">
                    <h3 class="text-sm font-bold uppercase tracking-wider text-stone-700 border-b pb-2"><i class="fa-solid fa-users-rectangle text-orange-500 mr-1"></i> Create New Section</h3>
                    
                    <form action="manage_students.php" method="POST" class="space-y-3">
                        <div class="space-y-1">
                            <label class="text-xs font-bold text-stone-600">Section Name</label>
                            <input type="text" name="section_name" required placeholder="e.g. BSIT 3-A" class="w-full bg-stone-50 border border-stone-200 rounded-xl px-4 py-2 text-xs outline-none focus:border-orange-500">
                        </div>
                        <div class="space-y-1">
                            <label class="text-xs font-bold text-stone-600">Course / Program</label>
                            <input type="text" name="course_name" required placeholder="e.g. BS Information Technology" class="w-full bg-stone-50 border border-stone-200 rounded-xl px-4 py-2 text-xs outline-none focus:border-orange-500">
                        </div>
                        <div class="space-y-1">
                            <label class="text-xs font-bold text-stone-600">Academic Year</label>
                            <input type="text" name="academic_year" value="2025-2026" required class="w-full bg-stone-50 border border-stone-200 rounded-xl px-4 py-2 text-xs outline-none focus:border-orange-500">
                        </div>
                        <button type="submit" name="add_section" class="w-full bg-stone-900 hover:bg-orange-600 text-white font-bold text-xs py-2.5 rounded-xl transition-all shadow-sm">
                            <i class="fa-solid fa-plus mr-1"></i> Add Section
                        </button>
                    </form>
                </div>

                <!-- 2. ENROLL STUDENT FORM -->
                <div class="bg-white border border-stone-200 rounded-2xl p-5 shadow-sm space-y-4">
                    <h3 class="text-sm font-bold uppercase tracking-wider text-stone-700 border-b pb-2"><i class="fa-solid fa-user-plus text-orange-500 mr-1"></i> Enroll Student</h3>
                    
                    <form action="manage_students.php" method="POST" class="space-y-3">
                        <div class="space-y-1">
                            <label class="text-xs font-bold text-stone-600">Assign Section</label>
                            <select name="section_id" required class="w-full bg-stone-50 border border-stone-200 rounded-xl px-4 py-2 text-xs outline-none focus:border-orange-500 cursor-pointer">
                                <option value="">-- Select Section --</option>
                                <?php foreach ($sections as $sec): ?>
                                    <option value="<?php echo $sec['id']; ?>"><?php echo htmlspecialchars($sec['section_name'] . ' (' . $sec['course_name'] . ')'); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="space-y-1">
                            <label class="text-xs font-bold text-stone-600">Student ID Number</label>
                            <input type="text" name="student_number" required placeholder="e.g. 2026-00123" class="w-full bg-stone-50 border border-stone-200 rounded-xl px-4 py-2 text-xs outline-none focus:border-orange-500">
                        </div>
                        <div class="space-y-1">
                            <label class="text-xs font-bold text-stone-600">Full Name</label>
                            <input type="text" name="fullname" required placeholder="e.g. Juan Dela Cruz" class="w-full bg-stone-50 border border-stone-200 rounded-xl px-4 py-2 text-xs outline-none focus:border-orange-500">
                        </div>
                        <div class="space-y-1">
                            <label class="text-xs font-bold text-stone-600">Email Address (Optional)</label>
                            <input type="email" name="email" placeholder="student@school.edu.ph" class="w-full bg-stone-50 border border-stone-200 rounded-xl px-4 py-2 text-xs outline-none focus:border-orange-500">
                        </div>
                        <button type="submit" name="add_student" class="w-full bg-orange-600 hover:bg-orange-700 text-white font-bold text-xs py-2.5 rounded-xl transition-all shadow-md">
                            <i class="fa-solid fa-user-check mr-1"></i> Enroll Student
                        </button>
                    </form>
                </div>

            </div>

            <!-- RIGHT COLUMN: ENROLLED STUDENTS TABLE -->
            <div class="lg:col-span-2 bg-white border border-stone-200 rounded-2xl p-6 shadow-sm space-y-4">
                <div class="flex items-center justify-between border-b pb-3">
                    <h3 class="text-sm font-bold uppercase tracking-wider text-stone-700"><i class="fa-solid fa-users text-orange-500 mr-1"></i> Class Roster List</h3>
                    <span class="bg-stone-100 text-stone-700 text-xs font-bold px-3 py-1 rounded-full"><?php echo count($students); ?> Total Enrolled</span>
                </div>

                <?php if (!empty($students)): ?>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-xs border-collapse">
                            <thead>
                                <tr class="bg-stone-50 border-b border-stone-200 text-stone-500 uppercase font-bold text-[10px]">
                                    <th class="p-3">ID Number</th>
                                    <th class="p-3">Full Name</th>
                                    <th class="p-3">Section</th>
                                    <th class="p-3">Email</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-stone-100 font-medium text-stone-700">
                                <?php foreach ($students as $st): ?>
                                    <tr class="hover:bg-stone-50/50 transition-all">
                                        <td class="p-3 font-mono font-bold text-orange-600"><?php echo htmlspecialchars($st['student_number']); ?></td>
                                        <td class="p-3 font-bold text-stone-800"><?php echo htmlspecialchars($st['fullname']); ?></td>
                                        <td class="p-3">
                                            <span class="bg-orange-50 text-orange-700 font-bold px-2 py-0.5 rounded text-[10px]">
                                                <?php echo htmlspecialchars($st['section_name']); ?>
                                            </span>
                                        </td>
                                        <td class="p-3 text-stone-400"><?php echo htmlspecialchars($st['email'] ?: 'N/A'); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="text-center py-12 text-stone-400">
                        <i class="fa-solid fa-users text-4xl mb-3 text-stone-300"></i>
                        <p class="text-sm font-bold">No students enrolled yet</p>
                        <p class="text-xs mt-1">Gumawa muna ng Section sa kaliwa at mag-enroll ng mga estudyante.</p>
                    </div>
                <?php endif; ?>
            </div>

        </div>
    </div>

</body>
</html>