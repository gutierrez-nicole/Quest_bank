<?php
require_once __DIR__ . '/../app/bootstrap.php';

AuthService::enforceRole('admin');
$pdo = getDBConnection();

$success_msg = "";
$error_msg = "";

try {
    // Fetch available subjects from subjects catalog for dropdowns
    $stmtSubList = $pdo->query("SELECT id, COALESCE(subject_code, code) AS subject_code, COALESCE(subject_title, title) AS subject_title FROM subjects ORDER BY id ASC");
    $catalog_subjects = $stmtSubList->fetchAll(PDO::FETCH_ASSOC);

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_teacher'])) {
        validateCSRFToken();
        $fullname = trim(sanitizeInput($_POST['fullname'] ?? ''));
        $username = trim(sanitizeInput($_POST['username'] ?? ''));
        $email = trim(sanitizeInput($_POST['email'] ?? ''));
        $password = password_hash($_POST['password'] ?? '', PASSWORD_DEFAULT);
        $handled_subject = trim(sanitizeInput($_POST['handled_subject'] ?? ''));

        if (!empty($fullname) && !empty($username) && !empty($_POST['password'])) {
            try {
                $stmt = $pdo->prepare("INSERT INTO users (fullname, username, email, password, role, handled_subject) VALUES (?, ?, ?, ?, 'teacher', ?)");
                $stmt->execute([$fullname, $username, $email, $password, $handled_subject]);
                logActivity("Created new teacher account '{$fullname}' (@{$username}) with handled subject '{$handled_subject}'.");
                $success_msg = "New teacher account successfully created and assigned to '{$handled_subject}'!";
            } catch (PDOException $e) {
                $error_msg = "Error adding teacher (Username or Email may already exist): " . $e->getMessage();
            }
        } else {
            $error_msg = "Please fill in all required fields.";
        }
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_teacher'])) {
        validateCSRFToken();
        $teacher_id = intval($_POST['teacher_id'] ?? 0);
        $fullname = trim(sanitizeInput($_POST['fullname'] ?? ''));
        $email = trim(sanitizeInput($_POST['email'] ?? ''));
        $handled_subject = trim(sanitizeInput($_POST['handled_subject'] ?? ''));

        if ($teacher_id > 0 && !empty($fullname)) {
            try {
                $stmt = $pdo->prepare("UPDATE users SET fullname = ?, email = ?, handled_subject = ? WHERE id = ? AND role = 'teacher'");
                $stmt->execute([$fullname, $email, $handled_subject, $teacher_id]);
                logActivity("Updated teacher account ID {$teacher_id} handled subject to '{$handled_subject}'.");
                $success_msg = "Teacher account details and assigned subject successfully updated!";
            } catch (PDOException $e) {
                $error_msg = "Failed to update teacher account: " . $e->getMessage();
            }
        }
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_teacher'])) {
        validateCSRFToken();
        $delete_id = intval($_POST['delete_id'] ?? 0);
        try {
            $stmtTeacher = $pdo->prepare("SELECT fullname, username FROM users WHERE id = ? AND role = 'teacher'");
            $stmtTeacher->execute([$delete_id]);
            $t = $stmtTeacher->fetch();

            $stmt = $pdo->prepare("DELETE FROM users WHERE id = ? AND role = 'teacher'");
            $stmt->execute([$delete_id]);
            if ($t) {
                logActivity("Deleted teacher account '{$t['fullname']}' (@{$t['username']}).");
            }
            $success_msg = "Teacher account successfully removed!";
        } catch (PDOException $e) {
            $error_msg = "Failed to delete teacher account: " . $e->getMessage();
        }
    }

    $stmtTeachers = $pdo->query("SELECT id, fullname, username, email, handled_subject, created_at FROM users WHERE role = 'teacher' ORDER BY id DESC");
    $teachers = $stmtTeachers->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log("Manage teachers error: " . $e->getMessage());
    die("Database Connection Error.");
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>QuestBank - Manage Faculty Teachers & Handled Subjects</title>
    
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; }
        .bg-orange-gradient { background: linear-gradient(135deg, #f97316 0%, #ea580c 100%); }
    </style>
</head>
<body class="bg-[#f3f4f6] min-h-screen flex">
    <?php require_once __DIR__ . '/../includes/admin_sidebar.php'; ?>
    <main class="flex-1 ml-16 lg:ml-64 p-6 md:p-12 overflow-y-auto min-h-screen">
        <div class="max-w-6xl mx-auto space-y-6">
        
        
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <a href="dashboard.php" class="text-xs font-bold text-orange-600 hover:underline flex items-center gap-1">
                    <i class="fa-solid fa-arrow-left"></i> Back to Admin Dashboard
                </a>
                <h1 class="text-2xl font-black text-stone-800 mt-2 flex items-center gap-2">
                    <i class="fa-solid fa-chalkboard-user text-orange-600"></i> Faculty Teachers Directory & Subject Assignment
                </h1>
                <p class="text-xs text-stone-400">Add instructor accounts, assign handled Civil Engineering subjects, and manage faculty credentials.</p>
            </div>
        </div>

        
        <?php if (!empty($success_msg)): ?>
            <div class="bg-emerald-50 border-l-4 border-emerald-500 p-4 rounded-xl text-xs font-semibold text-emerald-700 shadow-sm">
                <i class="fa-solid fa-circle-check mr-1"></i> <?php echo htmlspecialchars($success_msg); ?>
            </div>
        <?php endif; ?>
        <?php if (!empty($error_msg)): ?>
            <div class="bg-rose-50 border-l-4 border-rose-500 p-4 rounded-xl text-xs font-semibold text-rose-700 shadow-sm">
                <i class="fa-solid fa-triangle-exclamation mr-1"></i> <?php echo htmlspecialchars($error_msg); ?>
            </div>
        <?php endif; ?>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            
            
            <div class="bg-white border border-stone-200 rounded-2xl p-6 shadow-sm space-y-4 h-fit">
                <h3 class="text-sm font-extrabold uppercase tracking-wider text-stone-800 border-b pb-3">
                    <i class="fa-solid fa-user-plus text-orange-500 mr-1"></i> Register Faculty Member
                </h3>
                
                <form action="manage_teachers.php" method="POST" class="space-y-4">
                    <?php echo csrfInputField(); ?>
                    <div class="space-y-1">
                        <label class="text-xs font-bold text-stone-600">Full Name</label>
                        <input type="text" name="fullname" required placeholder="Prof. Juan Dela Cruz" class="w-full bg-stone-50 border border-stone-200 rounded-xl px-4 py-2.5 text-xs outline-none focus:border-orange-500">
                    </div>

                    <div class="space-y-1">
                        <label class="text-xs font-bold text-stone-600">Username</label>
                        <input type="text" name="username" required placeholder="prof_juandelacruz" class="w-full bg-stone-50 border border-stone-200 rounded-xl px-4 py-2.5 text-xs outline-none focus:border-orange-500 font-mono">
                    </div>

                    <div class="space-y-1">
                        <label class="text-xs font-bold text-stone-600">Email Address</label>
                        <input type="email" name="email" required placeholder="juandelacruz@questbank.edu.ph" class="w-full bg-stone-50 border border-stone-200 rounded-xl px-4 py-2.5 text-xs outline-none focus:border-orange-500">
                    </div>

                    <div class="space-y-1">
                        <label class="text-xs font-bold text-stone-600">Assigned Handled Subject</label>
                        <select name="handled_subject" required class="w-full bg-stone-50 border border-stone-200 rounded-xl px-4 py-2.5 text-xs outline-none focus:border-orange-500 font-bold text-stone-800">
                            <option value="">-- Select Handled Subject --</option>
                            <?php foreach ($catalog_subjects as $sub): ?>
                                <option value="<?php echo htmlspecialchars($sub['subject_code'] . ' - ' . $sub['subject_title']); ?>">
                                    <?php echo htmlspecialchars($sub['subject_code'] . ' - ' . $sub['subject_title']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="space-y-1">
                        <label class="text-xs font-bold text-stone-600">Account Password</label>
                        <input type="password" name="password" required placeholder="••••••••" class="w-full bg-stone-50 border border-stone-200 rounded-xl px-4 py-2.5 text-xs outline-none focus:border-orange-500">
                    </div>

                    <button type="submit" name="add_teacher" class="w-full bg-orange-gradient text-white font-bold text-xs py-3 rounded-xl transition-all shadow-md flex items-center justify-center gap-2">
                        <i class="fa-solid fa-plus"></i> Add Faculty Account
                    </button>
                </form>
            </div>

            
            <div class="lg:col-span-2 bg-white border border-stone-200 rounded-2xl p-6 shadow-sm space-y-4">
                <div class="flex items-center justify-between border-b pb-3">
                    <h3 class="text-sm font-extrabold uppercase tracking-wider text-stone-800">
                        <i class="fa-solid fa-users text-orange-500 mr-1"></i> Active Faculty Accounts Roster
                    </h3>
                    <span class="bg-orange-100 text-orange-700 text-xs font-black px-3 py-1 rounded-full">
                        <?php echo count($teachers); ?> Instructors
                    </span>
                </div>

                <?php if (!empty($teachers)): ?>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left border-collapse text-xs text-stone-700">
                            <thead>
                                <tr class="bg-stone-50 border-b border-stone-200 text-stone-400 font-extrabold uppercase text-[10px] tracking-wider">
                                    <th class="p-3">Faculty Name</th>
                                    <th class="p-3">Handled Subject</th>
                                    <th class="p-3">Username & Email</th>
                                    <th class="p-3 text-center">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-stone-100 font-semibold">
                                <?php foreach ($teachers as $teacher): ?>
                                    <tr class="hover:bg-stone-50/50 transition-all">
                                        <td class="p-3 font-bold text-stone-800">
                                            <div class="flex items-center gap-2">
                                                <div class="w-8 h-8 rounded-lg bg-orange-100 text-orange-700 flex items-center justify-center font-black text-xs">
                                                    <?php echo strtoupper(substr($teacher['fullname'], 0, 2)); ?>
                                                </div>
                                                <div>
                                                    <p class="font-extrabold text-stone-800 text-xs"><?php echo htmlspecialchars($teacher['fullname']); ?></p>
                                                    <p class="text-[10px] text-stone-400 font-medium">Faculty Professor</p>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="p-3">
                                            <span class="bg-orange-50 border border-orange-200 text-orange-800 font-extrabold text-[11px] px-2.5 py-1 rounded-lg inline-block">
                                                <i class="fa-solid fa-book-open text-orange-600 mr-1"></i>
                                                <?php echo htmlspecialchars(!empty($teacher['handled_subject']) ? $teacher['handled_subject'] : 'CE 412 - Structural Theory & Design'); ?>
                                            </span>
                                        </td>
                                        <td class="p-3 space-y-0.5">
                                            <p class="font-mono font-bold text-stone-800"><?php echo htmlspecialchars($teacher['username']); ?></p>
                                            <p class="text-stone-400 text-[10px]"><?php echo htmlspecialchars($teacher['email']); ?></p>
                                        </td>
                                        <td class="p-3 text-center">
                                            <div class="flex items-center justify-center gap-1.5">
                                                <button onclick="openEditTeacherModal(<?php echo $teacher['id']; ?>, '<?php echo htmlspecialchars(addslashes($teacher['fullname'])); ?>', '<?php echo htmlspecialchars(addslashes($teacher['email'])); ?>', '<?php echo htmlspecialchars(addslashes($teacher['handled_subject'] ?? '')); ?>')" class="bg-amber-100 text-amber-800 hover:bg-amber-200 px-2.5 py-1.5 rounded-lg text-[10px] font-bold transition-all flex items-center gap-1">
                                                    <i class="fa-solid fa-pen-to-square"></i> Edit
                                                </button>
                                                <form method="POST" style="display:inline" onsubmit="return confirm('Are you sure you want to delete teacher account <?php echo htmlspecialchars($teacher['fullname']); ?>?');">
                                                    <?php echo csrfInputField(); ?>
                                                    <input type="hidden" name="delete_teacher" value="1">
                                                    <input type="hidden" name="delete_id" value="<?php echo $teacher['id']; ?>">
                                                    <button type="submit" class="bg-rose-50 text-rose-600 hover:bg-rose-600 hover:text-white px-2.5 py-1.5 rounded-lg text-[10px] font-bold transition-all flex items-center gap-1">
                                                        <i class="fa-solid fa-trash"></i> Remove
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="text-center py-12 text-stone-400">
                        <i class="fa-solid fa-chalkboard-user text-4xl mb-3 text-stone-300"></i>
                        <p class="text-sm font-bold">No Teacher accounts registered yet</p>
                        <p class="text-xs mt-1">Use the form on the left to add a new faculty account.</p>
                    </div>
                <?php endif; ?>
            </div>

        </div>
    </div>
    </main>

    
    <div id="edit_teacher_modal" class="hidden fixed inset-0 bg-black/50 backdrop-blur-sm z-50 flex items-center justify-center p-4">
        <div class="bg-white border border-stone-200 rounded-2xl max-w-md w-full p-6 space-y-4 shadow-2xl animate-fadeIn">
            <div class="flex items-center justify-between border-b pb-3">
                <h3 class="font-extrabold text-stone-800 text-sm flex items-center gap-2">
                    <i class="fa-solid fa-pen-to-square text-orange-600"></i> Edit Teacher Account & Handled Subject
                </h3>
                <button onclick="closeEditTeacherModal()" class="text-stone-400 hover:text-stone-600"><i class="fa-solid fa-xmark"></i></button>
            </div>
            <form action="manage_teachers.php" method="POST" class="space-y-3">
                <?php echo csrfInputField(); ?>
                <input type="hidden" id="edit_teacher_id" name="teacher_id" value="">
                <div>
                    <label class="text-[10px] font-bold text-stone-500 uppercase">Full Name</label>
                    <input type="text" id="edit_teacher_fullname" name="fullname" required class="w-full bg-stone-50 border border-stone-200 rounded-xl p-2.5 text-xs font-bold outline-none focus:border-orange-500">
                </div>
                <div>
                    <label class="text-[10px] font-bold text-stone-500 uppercase">Email Address</label>
                    <input type="email" id="edit_teacher_email" name="email" required class="w-full bg-stone-50 border border-stone-200 rounded-xl p-2.5 text-xs outline-none focus:border-orange-500">
                </div>
                <div>
                    <label class="text-[10px] font-bold text-stone-500 uppercase">Assigned Handled Subject</label>
                    <select id="edit_teacher_handled_subject" name="handled_subject" required class="w-full bg-stone-50 border border-stone-200 rounded-xl p-2.5 text-xs font-bold text-stone-800 outline-none focus:border-orange-500">
                        <option value="">-- Select Handled Subject --</option>
                        <?php foreach ($catalog_subjects as $sub): ?>
                            <option value="<?php echo htmlspecialchars($sub['subject_code'] . ' - ' . $sub['subject_title']); ?>">
                                <?php echo htmlspecialchars($sub['subject_code'] . ' - ' . $sub['subject_title']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="flex items-center justify-end gap-2 pt-2 border-t">
                    <button type="button" onclick="closeEditTeacherModal()" class="bg-stone-100 hover:bg-stone-200 text-stone-700 font-bold text-xs px-4 py-2 rounded-xl">Cancel</button>
                    <button type="submit" name="update_teacher" class="bg-orange-600 hover:bg-orange-700 text-white font-bold text-xs px-4 py-2 rounded-xl transition-all shadow-sm">Save Changes</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openEditTeacherModal(id, name, email, subject) {
            document.getElementById('edit_teacher_id').value = id;
            document.getElementById('edit_teacher_fullname').value = name;
            document.getElementById('edit_teacher_email').value = email;
            
            const selectEl = document.getElementById('edit_teacher_handled_subject');
            if (selectEl) {
                selectEl.value = subject;
            }
            
            const modal = document.getElementById('edit_teacher_modal');
            modal.classList.remove('hidden');
            modal.classList.add('flex');
        }
        function closeEditTeacherModal() {
            const modal = document.getElementById('edit_teacher_modal');
            modal.classList.add('hidden');
            modal.classList.remove('flex');
        }
    </script>
</body>
</html>