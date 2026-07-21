<?php
session_start();

// Siguraduhing naka-login at isang Teacher ang nakapasok
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    header("Location: ../index.php");
    exit();
}

// Database Connection
$host = 'localhost';
$dbname = 'bankquest_db';
$db_user = 'root';
$db_pass = '';

$success_msg = "";
$error_msg = "";

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $db_user, $db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $teacher_id = $_SESSION['user_id'];

    // 1. UPDATE PERSONAL INFORMATION
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
        $fullname = trim($_POST['fullname']);
        $username = trim($_POST['username']);
        $email = trim($_POST['email']);

        if (!empty($fullname) && !empty($username) && !empty($email)) {
            // Check kung may kaparehong username o email ang ibang user
            $stmtCheck = $pdo->prepare("SELECT id FROM users WHERE (username = ? OR email = ?) AND id != ?");
            $stmtCheck->execute([$username, $email, $teacher_id]);
            
            if ($stmtCheck->rowCount() > 0) {
                $error_msg = "Username or Email is already taken by another account.";
            } else {
                $stmtUpdate = $pdo->prepare("UPDATE users SET fullname = ?, username = ?, email = ? WHERE id = ?");
                $stmtUpdate->execute([$fullname, $username, $email, $teacher_id]);
                $success_msg = "Profile details updated successfully!";
            }
        } else {
            $error_msg = "All fields are required.";
        }
    }

    // 2. CHANGE PASSWORD
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
        $current_pass = $_POST['current_password'];
        $new_pass = $_POST['new_password'];
        $confirm_pass = $_POST['confirm_password'];

        // Kunin ang lumang password hash
        $stmtPass = $pdo->prepare("SELECT password FROM users WHERE id = ?");
        $stmtPass->execute([$teacher_id]);
        $userRow = $stmtPass->fetch(PDO::FETCH_ASSOC);

        if ($userRow && password_verify($current_pass, $userRow['password'])) {
            if ($new_pass === $confirm_pass) {
                if (strlen($new_pass) >= 6) {
                    $new_hash = password_hash($new_pass, PASSWORD_BCRYPT);
                    $stmtUpdatePass = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
                    $stmtUpdatePass->execute([$new_hash, $teacher_id]);
                    $success_msg = "Password updated successfully!";
                } else {
                    $error_msg = "New password must be at least 6 characters long.";
                }
            } else {
                $error_msg = "New password and Confirm password do not match.";
            }
        } else {
            $error_msg = "Incorrect current password.";
        }
    }

    // Fetch Profile Info para sa form values
    $stmt = $pdo->prepare("SELECT fullname, username, email, created_at FROM users WHERE id = ?");
    $stmt->execute([$teacher_id]);
    $teacher = $stmt->fetch(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>QuestBank - Profile Settings</title>
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- FontAwesome for Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; }
        .custom-scrollbar::-webkit-scrollbar { width: 4px; }
        .custom-scrollbar::-webkit-scrollbar-thumb { background: #444; border-radius: 10px; }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: scale(0.95); }
            to { opacity: 1; transform: scale(1); }
        }
        .animate-fadeIn { animation: fadeIn 0.2s ease-out; }
    </style>
</head>
<body class="bg-[#fffbf7] min-h-screen flex">

    <!-- ================= SIDEBAR NAVIGATION ================= -->
    <aside class="w-64 bg-stone-950 text-stone-300 flex flex-col justify-between hidden lg:flex z-20 shadow-xl max-h-screen">
        <div class="flex flex-col overflow-hidden">
            <!-- Sidebar Header / Logo -->
            <div class="p-5 border-b border-stone-800 flex items-center gap-3 bg-stone-900 flex-shrink-0">
                <div class="w-9 h-9 bg-orange-600 rounded-xl flex items-center justify-center font-bold text-white shadow-md">
                    <i class="fa-solid fa-chalkboard-user text-base"></i>
                </div>
                <div>
                    <h1 class="text-base font-extrabold tracking-tight text-white">Quest<span class="text-orange-500">Bank</span></h1>
                    <p class="text-[9px] uppercase text-stone-400 font-semibold tracking-wider">Teacher Module</p>
                </div>
            </div>

            <!-- Scrollable Navigation Links -->
            <div class="flex-grow overflow-y-auto p-4 space-y-4 custom-scrollbar">
                <div>
                    <p class="text-[10px] font-bold uppercase tracking-widest text-stone-500 px-3 mb-1.5">Teacher Module</p>
                    <nav class="space-y-0.5">
                        <a href="dashboard.php" class="flex items-center gap-3 px-3 py-2 text-xs font-medium rounded-lg text-stone-400 hover:bg-stone-900 hover:text-orange-500 transition-all">
                            <i class="fa-solid fa-chart-pie w-4 text-center"></i> Teacher Dashboard
                        </a>
                        <a href="upload_check.php" class="flex items-center gap-3 px-3 py-2 text-xs font-medium rounded-lg text-stone-400 hover:bg-stone-900 hover:text-orange-500 transition-all">
                            <i class="fa-solid fa-expand w-4 text-center"></i> OCR Answer Checker
                        </a>
                        <a href="create_exam.php" class="flex items-center gap-3 px-3 py-2 text-xs font-medium rounded-lg text-stone-400 hover:bg-stone-900 hover:text-orange-500 transition-all">
                            <i class="fa-solid fa-file-signature w-4 text-center"></i> Create Exam
                        </a>
                        <a href="generate_ai.php" class="flex items-center gap-3 px-3 py-2 text-xs font-medium rounded-lg text-stone-400 hover:bg-stone-900 hover:text-orange-500 transition-all">
                            <i class="fa-solid fa-wand-magic-sparkles w-4 text-center"></i> Generate AI Questions
                        </a>
                        <a href="manage_students.php" class="flex items-center gap-3 px-3 py-2 text-xs font-medium rounded-lg text-stone-400 hover:bg-stone-900 hover:text-orange-500 transition-all">
                            <i class="fa-solid fa-users w-4 text-center"></i> Students & Sections
                        </a>
                        <a href="upload_lessons.php" class="flex items-center gap-3 px-3 py-2 text-xs font-medium rounded-lg text-stone-400 hover:bg-stone-900 hover:text-orange-500 transition-all">
                            <i class="fa-solid fa-file-arrow-up w-4 text-center"></i> Upload Lesson Files
                        </a>
                        <a href="reports.php" class="flex items-center gap-3 px-3 py-2 text-xs font-medium rounded-lg text-stone-400 hover:bg-stone-900 hover:text-orange-500 transition-all">
                            <i class="fa-solid fa-chart-column w-4 text-center"></i> Reports & Analytics
                        </a>
                    </nav>
                </div>

                <div>
                    <p class="text-[10px] font-bold uppercase tracking-widest text-stone-500 px-3 mb-1.5">Settings</p>
                    <nav class="space-y-0.5">
                        <a href="profile_settings.php" class="flex items-center gap-3 px-3 py-2 text-xs font-semibold rounded-lg bg-orange-600 text-white shadow-sm">
                            <i class="fa-solid fa-user w-4 text-center"></i> Profile Settings
                        </a>
                    </nav>
                </div>
            </div>
        </div>

        <div class="p-4 border-t border-stone-800 bg-stone-900 flex-shrink-0">
            <button onclick="openLogoutModal()" class="w-full flex items-center gap-3 px-3 py-2 text-xs font-semibold rounded-lg text-red-400 hover:bg-red-500/10 hover:text-red-300 transition-all">
                <i class="fa-solid fa-right-from-bracket w-4 text-center"></i> Logout
            </button>
        </div>
    </aside>

    <!-- ================= MAIN CONTENT AREA ================= -->
    <main class="flex-grow flex flex-col min-w-0 h-screen overflow-hidden">
        
        <!-- TOP NAV HEADERBAR -->
        <header class="bg-white border-b border-stone-200 px-6 py-4 flex items-center justify-between flex-shrink-0">
            <div>
                <h2 class="text-lg font-bold text-stone-800"><i class="fa-solid fa-user-gear text-orange-600 mr-2"></i>Profile & Account Settings</h2>
                <p class="text-xs text-stone-400">Manage your faculty credentials, update personal details, and security passwords.</p>
            </div>
            
            <div class="flex items-center gap-4">
                <div class="flex items-center gap-3 pl-2 border-l border-stone-200">
                    <div class="w-9 h-9 rounded-xl bg-orange-100 text-orange-700 font-bold flex items-center justify-center shadow-inner">
                        <?php echo strtoupper(substr($teacher['fullname'] ?? 'Prof', 0, 2)); ?>
                    </div>
                    <div class="hidden sm:block text-left">
                        <p class="text-xs font-bold text-stone-800 leading-tight"><?php echo htmlspecialchars($teacher['fullname'] ?? 'Teacher'); ?></p>
                        <p class="text-[10px] text-stone-400 font-medium">Faculty Professor</p>
                    </div>
                </div>
            </div>
        </header>

        <!-- DASHBOARD CONTAINER BODY PANEL -->
        <div class="flex-grow overflow-y-auto p-6 space-y-6 custom-scrollbar">

            <!-- NOTIFICATION ALERTS -->
            <?php if (!empty($success_msg)): ?>
                <div class="bg-emerald-50 border-l-4 border-emerald-500 p-4 rounded-xl text-xs font-semibold text-emerald-800 flex items-center justify-between shadow-sm animate-fadeIn">
                    <span class="flex items-center gap-2"><i class="fa-solid fa-circle-check text-emerald-600 text-sm"></i> <?php echo $success_msg; ?></span>
                    <button onclick="this.parentElement.remove();" class="text-emerald-500 hover:text-emerald-800"><i class="fa-solid fa-xmark"></i></button>
                </div>
            <?php endif; ?>

            <?php if (!empty($error_msg)): ?>
                <div class="bg-rose-50 border-l-4 border-rose-500 p-4 rounded-xl text-xs font-semibold text-rose-800 flex items-center justify-between shadow-sm animate-fadeIn">
                    <span class="flex items-center gap-2"><i class="fa-solid fa-circle-exclamation text-rose-600 text-sm"></i> <?php echo $error_msg; ?></span>
                    <button onclick="this.parentElement.remove();" class="text-rose-500 hover:text-rose-800"><i class="fa-solid fa-xmark"></i></button>
                </div>
            <?php endif; ?>

            <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">
                
                <!-- PROFILE AVATAR SUMMARY CARD (4 COLUMNS) -->
                <div class="lg:col-span-4 bg-white border border-stone-200 rounded-2xl p-6 shadow-sm text-center space-y-4">
                    <div class="relative w-24 h-24 bg-orange-100 text-orange-600 rounded-2xl flex items-center justify-center text-3xl font-black mx-auto shadow-inner border-2 border-orange-200">
                        <?php echo strtoupper(substr($teacher['fullname'] ?? 'Prof', 0, 2)); ?>
                        <span class="absolute bottom-1 right-1 w-4 h-4 bg-emerald-500 border-2 border-white rounded-full"></span>
                    </div>

                    <div>
                        <h3 class="text-base font-extrabold text-stone-800"><?php echo htmlspecialchars($teacher['fullname'] ?? 'Faculty'); ?></h3>
                        <p class="text-xs text-orange-600 font-bold mt-0.5">@<?php echo htmlspecialchars($teacher['username'] ?? 'username'); ?></p>
                        <span class="inline-block mt-2 px-3 py-1 bg-stone-100 text-stone-600 font-semibold text-[10px] rounded-full uppercase tracking-wider">Faculty Professor</span>
                    </div>

                    <div class="pt-4 border-t border-stone-100 text-left space-y-2 text-xs">
                        <div class="flex items-center justify-between text-stone-500">
                            <span><i class="fa-solid fa-envelope mr-2 text-stone-400"></i>Email:</span>
                            <span class="font-bold text-stone-800 truncate max-w-[140px]"><?php echo htmlspecialchars($teacher['email']); ?></span>
                        </div>
                        <div class="flex items-center justify-between text-stone-500">
                            <span><i class="fa-solid fa-calendar-day mr-2 text-stone-400"></i>Member Since:</span>
                            <span class="font-bold text-stone-800"><?php echo date("M Y", strtotime($teacher['created_at'] ?? 'now')); ?></span>
                        </div>
                    </div>
                </div>

                <!-- EDIT FORMS SECTION (8 COLUMNS) -->
                <div class="lg:col-span-8 space-y-6">
                    
                    <!-- 1. GENERAL INFORMATION FORM -->
                    <div class="bg-white border border-stone-200 rounded-2xl p-6 shadow-sm space-y-4">
                        <div class="border-b border-stone-100 pb-3">
                            <h3 class="text-sm font-extrabold text-stone-800 uppercase tracking-wider flex items-center gap-2">
                                <i class="fa-solid fa-id-card text-orange-500"></i> Personal Information
                            </h3>
                            <p class="text-xs text-stone-400">Update your full name, username, and account email address.</p>
                        </div>

                        <form action="profile_settings.php" method="POST" class="space-y-4">
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div class="space-y-1">
                                    <label class="text-xs font-bold text-stone-700">Full Name</label>
                                    <input type="text" name="fullname" required value="<?php echo htmlspecialchars($teacher['fullname']); ?>" class="w-full bg-stone-50 border border-stone-200 rounded-xl px-4 py-2.5 text-xs font-semibold text-stone-800 outline-none focus:border-orange-500 focus:bg-white transition-all">
                                </div>

                                <div class="space-y-1">
                                    <label class="text-xs font-bold text-stone-700">Username</label>
                                    <input type="text" name="username" required value="<?php echo htmlspecialchars($teacher['username']); ?>" class="w-full bg-stone-50 border border-stone-200 rounded-xl px-4 py-2.5 text-xs font-semibold text-stone-800 outline-none focus:border-orange-500 focus:bg-white transition-all">
                                </div>
                            </div>

                            <div class="space-y-1">
                                <label class="text-xs font-bold text-stone-700">Email Address</label>
                                <input type="email" name="email" required value="<?php echo htmlspecialchars($teacher['email']); ?>" class="w-full bg-stone-50 border border-stone-200 rounded-xl px-4 py-2.5 text-xs font-semibold text-stone-800 outline-none focus:border-orange-500 focus:bg-white transition-all">
                            </div>

                            <div class="pt-2 flex justify-end">
                                <button type="submit" name="update_profile" class="bg-orange-600 hover:bg-orange-700 text-white font-extrabold text-xs px-6 py-2.5 rounded-xl shadow-md transition-all flex items-center gap-2">
                                    <i class="fa-solid fa-floppy-disk"></i> Save Profile Updates
                                </button>
                            </div>
                        </form>
                    </div>

                    <!-- 2. CHANGE PASSWORD FORM -->
                    <div class="bg-white border border-stone-200 rounded-2xl p-6 shadow-sm space-y-4">
                        <div class="border-b border-stone-100 pb-3">
                            <h3 class="text-sm font-extrabold text-stone-800 uppercase tracking-wider flex items-center gap-2">
                                <i class="fa-solid fa-key text-orange-500"></i> Security & Password
                            </h3>
                            <p class="text-xs text-stone-400">Ensure your account uses a strong password to protect your test materials.</p>
                        </div>

                        <form action="profile_settings.php" method="POST" class="space-y-4">
                            <div class="space-y-1">
                                <label class="text-xs font-bold text-stone-700">Current Password</label>
                                <input type="password" name="current_password" required placeholder="••••••••" class="w-full bg-stone-50 border border-stone-200 rounded-xl px-4 py-2.5 text-xs font-semibold text-stone-800 outline-none focus:border-orange-500 focus:bg-white transition-all">
                            </div>

                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div class="space-y-1">
                                    <label class="text-xs font-bold text-stone-700">New Password</label>
                                    <input type="password" name="new_password" required placeholder="Minimum 6 characters" class="w-full bg-stone-50 border border-stone-200 rounded-xl px-4 py-2.5 text-xs font-semibold text-stone-800 outline-none focus:border-orange-500 focus:bg-white transition-all">
                                </div>

                                <div class="space-y-1">
                                    <label class="text-xs font-bold text-stone-700">Confirm New Password</label>
                                    <input type="password" name="confirm_password" required placeholder="Re-type new password" class="w-full bg-stone-50 border border-stone-200 rounded-xl px-4 py-2.5 text-xs font-semibold text-stone-800 outline-none focus:border-orange-500 focus:bg-white transition-all">
                                </div>
                            </div>

                            <div class="pt-2 flex justify-end">
                                <button type="submit" name="change_password" class="bg-stone-900 hover:bg-orange-600 text-white font-extrabold text-xs px-6 py-2.5 rounded-xl shadow-md transition-all flex items-center gap-2">
                                    <i class="fa-solid fa-shield-halved"></i> Change Password
                                </button>
                            </div>
                        </form>
                    </div>

                </div>

            </div>

        </div>
    </main>

    <!-- LOGOUT CONFIRMATION MODAL -->
    <div id="logout_modal" class="fixed inset-0 bg-stone-950/70 backdrop-blur-sm hidden items-center justify-center z-50 p-4">
        <div class="bg-white border border-stone-200 p-6 rounded-2xl max-w-sm w-full space-y-4 shadow-2xl animate-fadeIn">
            <div class="flex items-center gap-3">
                <div class="w-12 h-12 bg-red-100 text-red-600 rounded-xl flex items-center justify-center">
                    <i class="fa-solid fa-right-from-bracket text-xl"></i>
                </div>
                <div>
                    <h4 class="font-extrabold text-base text-stone-800">Confirm Logout</h4>
                    <p class="text-xs text-stone-500">Are you sure you want to sign out?</p>
                </div>
            </div>
            <div class="flex gap-2 justify-end pt-2">
                <button onclick="closeLogoutModal()" class="px-4 py-2.5 bg-stone-200 text-stone-700 font-bold text-xs rounded-xl hover:bg-stone-300 transition-all">
                    Cancel
                </button>
                <button onclick="confirmLogout()" class="px-4 py-2.5 bg-red-600 text-white font-bold text-xs rounded-xl shadow-md hover:bg-red-700 transition-all">
                    <i class="fa-solid fa-right-from-bracket mr-1"></i> Logout
                </button>
            </div>
        </div>
    </div>

    <!-- JAVASCRIPT HANDLERS -->
    <script>
        function openLogoutModal() {
            document.getElementById('logout_modal').classList.remove('hidden');
            document.getElementById('logout_modal').classList.add('flex');
        }
        
        function closeLogoutModal() {
            document.getElementById('logout_modal').classList.add('hidden');
            document.getElementById('logout_modal').classList.remove('flex');
        }
        
        function confirmLogout() {
            window.location.href = '../logout.php';
        }
    </script>
</body>
</html>