<?php

require_once __DIR__ . '/app/database.php';
require_once __DIR__ . '/app/session.php';
require_once __DIR__ . '/includes/security.php';

if (isset($_SESSION['user_id']) && isset($_SESSION['role'])) {
    if ($_SESSION['role'] === 'student') header("Location: student/dashboard.php");
    elseif ($_SESSION['role'] === 'teacher') header("Location: teacher/dashboard.php");
    elseif ($_SESSION['role'] === 'admin') header("Location: admin/dashboard.php");
    exit();
}

$pdo = getDBConnection();
$error_msg = "";
$success_msg = "";
$active_form = "login";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validateCSRFToken();
    
    
    if (isset($_POST['action_login'])) {
        $email = trim($_POST['email'] ?? '');
        $password = trim($_POST['password'] ?? '');

        if (!empty($email) && !empty($password)) {
            $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? OR username = ?");
            $stmt->execute([$email, $email]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password'])) {
                regenerateSecureSession();
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['role'] = $user['role'];
                
                if ($user['role'] === 'student') header("Location: student/dashboard.php");
                elseif ($user['role'] === 'teacher') header("Location: teacher/dashboard.php");
                elseif ($user['role'] === 'admin') header("Location: admin/dashboard.php");
                exit();
            } else {
                $error_msg = "Invalid email address or password.";
            }
        } else {
            $error_msg = "Please fill in all required fields.";
        }
    }

    
    if (isset($_POST['action_register'])) {
        $active_form = "register"; 
        
        $fullname = trim($_POST['fullname'] ?? '');
        $username = trim($_POST['username'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $role = $_POST['role'] ?? '';
        $password = $_POST['password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';

        if ($password !== $confirm_password) {
            $error_msg = "Passwords do not match!";
        } elseif (empty($role)) {
            $error_msg = "Please select a role (Student, Teacher, or Admin).";
        } else {
            $hashed_password = password_hash($password, PASSWORD_BCRYPT);

            try {
                $pdo->beginTransaction();

                $check_stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? OR username = ?");
                $check_stmt->execute([$email, $username]);
                if ($check_stmt->fetch()) {
                    throw new Exception("Username or Email already exists.");
                }

                $stmt = $pdo->prepare("INSERT INTO users (fullname, username, email, password, role) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$fullname, $username, $email, $hashed_password, $role]);
                $user_id = $pdo->lastInsertId();

                if ($role === 'student') {
                    $student_number = trim($_POST['student_number'] ?? '');
                    $course = trim($_POST['course'] ?? '');
                    $year_level = intval($_POST['year_level'] ?? 0);
                    $section = trim($_POST['section'] ?? '');

                    if (empty($student_number) || empty($course) || empty($year_level) || empty($section)) {
                        throw new Exception("All student details are required.");
                    }

                    $stmt_student = $pdo->prepare("INSERT INTO student_details (user_id, student_number, course, year_level, section) VALUES (?, ?, ?, ?, ?)");
                    $stmt_student->execute([$user_id, $student_number, $course, $year_level, $section]);
                }

                $pdo->commit();
                $success_msg = "Account registered successfully! You can now sign in.";
                $active_form = "login"; 
            } catch (Exception $e) {
                $pdo->rollBack();
                $error_msg = $e->getMessage();
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en" class="h-full scroll-none">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>QuestBank - Login & Portal Access</title>
    
    <script src="https://cdn.tailwindcss.com"></script>
    
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght=300;400;500;600;700;800&family=Playfair+Display:ital,wght=0,600;1,600&display=swap" rel="stylesheet">

    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    colors: {
                        brand: {
                            orange: '#f97316',
                            darkorange: '#ea580c',
                            black: '#09090b',
                            bglight: '#f3f4f6'
                        }
                    }
                }
            }
        }
    </script>

    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; }
        .serif-title { font-family: 'Playfair Display', serif; }
        .bg-orange-gradient { background: linear-gradient(135deg, #f97316 0%, #ea580c 100%); }
        .bg-orange-gradient:hover { background: linear-gradient(135deg, #ea580c 0%, #c2410c 100%); }
        .custom-scrollbar::-webkit-scrollbar { width: 4px; }
        .custom-scrollbar::-webkit-scrollbar-thumb { background: #ea580c; border-radius: 10px; }
        .form-fade { transition: all 0.35s cubic-bezier(0.4, 0, 0.2, 1); }
        .skeleton-loader {
            background: linear-gradient(90deg, rgba(229, 231, 235, 0.2) 25%, rgba(243, 244, 246, 0.5) 50%, rgba(229, 231, 235, 0.2) 75%);
            background-size: 200% 100%;
            animation: skeleton-loading 1.5s infinite;
        }
        @keyframes skeleton-loading {
            0% { background-position: 200% 0; }
            100% { background-position: -200% 0; }
        }
    </style>
</head>
<body class="bg-[#f3f4f6] dark:bg-[#09090b] text-stone-800 dark:text-stone-100 h-screen overflow-hidden flex flex-col md:flex-row transition-colors duration-300">

    
    <div class="bg-stone-950 text-white w-full md:w-5/12 lg:w-1/2 p-6 md:p-12 flex flex-col justify-between relative overflow-hidden h-1/3 md:h-full flex-shrink-0 border-b md:border-b-0 md:border-r border-stone-800">
        <div class="absolute inset-0 opacity-20 pointer-events-none">
            <div class="absolute -top-10 -left-10 w-96 h-96 rounded-full border border-orange-500/30 blur-xl"></div>
            <div class="absolute top-1/2 -right-20 w-[450px] h-[450px] rounded-full border border-orange-600/20 blur-2xl"></div>
        </div>

        <div class="flex items-center justify-between z-10">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 bg-orange-gradient rounded-2xl flex items-center justify-center font-black text-white shadow-lg shadow-orange-600/30">
                    <i class="fa-solid fa-brain text-lg"></i>
                </div>
                <div>
                    <p class="text-[9px] tracking-widest uppercase text-stone-400 font-bold"><?php echo APP_INSTITUTION; ?></p>
                    <h1 class="text-lg font-black tracking-tight text-white">Quest<span class="text-orange-500">Bank</span></h1>
                </div>
            </div>

            <button onclick="toggleDarkMode()" class="w-9 h-9 rounded-xl border border-stone-800 bg-stone-900/80 flex items-center justify-center text-stone-400 hover:text-orange-500 transition-all">
                <i class="fa-solid fa-moon text-xs dark:hidden"></i>
                <i class="fa-solid fa-sun text-xs hidden dark:block text-amber-400"></i>
            </button>
        </div>

        <div class="z-10 my-auto hidden md:block space-y-4">
            <span class="bg-orange-500/10 text-orange-500 border border-orange-500/20 px-3 py-1 rounded-full text-[10px] font-black uppercase tracking-widest">
                AI Automated Examination System
            </span>
            <h2 class="serif-title text-3xl lg:text-4xl font-extrabold tracking-tight text-white leading-tight">
                Empowering Civil Engineering & Academic Assessments with Intelligent AI.
            </h2>
            <p class="text-xs text-stone-400 leading-relaxed max-w-lg">
                Upload course materials, generate exam items, and grade handwritten student test papers using optical evaluation technology.
            </p>
        </div>

        <div class="z-10 text-[10px] text-stone-600 dark:text-stone-500 hidden md:block">
            &copy; <?php echo date('Y'); ?> <?php echo APP_NAME; ?>. All rights reserved.
        </div>
    </div>

    
    <div class="w-full md:w-7/12 lg:w-1/2 flex flex-col justify-center items-center p-6 md:p-12 relative overflow-y-auto custom-scrollbar h-2/3 md:h-full">
        
        
        <div id="skeleton-overlay" class="hidden absolute inset-0 bg-white/90 dark:bg-stone-950/90 z-50 flex-col items-center justify-center space-y-4 p-8">
            <div class="w-12 h-12 border-4 border-orange-500 border-t-transparent rounded-full animate-spin"></div>
            <p class="text-xs font-bold text-stone-600 dark:text-stone-300">Authenticating credentials...</p>
        </div>

        
        <div class="w-full max-w-md mb-4 space-y-2">
            <?php if (!empty($error_msg)): ?>
                <div class="bg-rose-500/10 border border-rose-500/30 p-3 rounded-xl text-xs text-rose-600 dark:text-rose-400 font-semibold flex items-center gap-2">
                    <i class="fa-solid fa-triangle-exclamation text-sm"></i> <?php echo htmlspecialchars($error_msg); ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($success_msg)): ?>
                <div class="bg-emerald-500/10 border border-emerald-500/30 p-3 rounded-xl text-xs text-emerald-600 dark:text-emerald-400 font-semibold flex items-center gap-2">
                    <i class="fa-solid fa-circle-check text-sm"></i> <?php echo htmlspecialchars($success_msg); ?>
                </div>
            <?php endif; ?>
        </div>

        
        <div id="login-box" class="w-full max-w-md space-y-5 form-fade <?php echo ($active_form === 'login') ? '' : 'hidden'; ?>">
            <div>
                <span class="text-[10px] font-black tracking-widest text-orange-500 uppercase">Faculty & Student Gateway</span>
                <h2 class="serif-title text-3xl font-bold text-stone-900 dark:text-stone-100 mt-0.5">Sign in to Portal</h2>
                <p class="text-xs text-stone-500 dark:text-stone-400 mt-1">Provide your credentials to access examination dashboards.</p>
            </div>

            <form action="index.php" method="POST" onsubmit="showSkeleton()" class="space-y-4">
                <?php echo csrfInputField(); ?>
                <input type="hidden" name="action_login" value="1">
                
                <div class="space-y-1">
                    <label class="text-[10px] font-bold uppercase tracking-wider text-stone-600 dark:text-stone-400">Email Address</label>
                    <div class="flex items-center bg-white dark:bg-stone-900 border border-stone-200 dark:border-stone-800 rounded-xl px-3.5 py-2.5 focus-within:border-orange-500 transition-all">
                        <i class="fa-solid fa-envelope text-stone-400 text-xs mr-3"></i>
                        <input type="text" name="email" id="login_email" required placeholder="you@questbank.edu.ph or username" class="w-full bg-transparent outline-none text-stone-800 dark:text-stone-100 text-xs">
                    </div>
                </div>

                <div class="space-y-1">
                    <label class="text-[10px] font-bold uppercase tracking-wider text-stone-600 dark:text-stone-400">Password</label>
                    <div class="flex items-center bg-white dark:bg-stone-900 border border-stone-200 dark:border-stone-800 rounded-xl px-3.5 py-2.5 focus-within:border-orange-500 transition-all">
                        <i class="fa-solid fa-lock text-stone-400 text-xs mr-3"></i>
                        <input type="password" name="password" id="login_password" required placeholder="••••••••" class="w-full bg-transparent outline-none text-stone-800 dark:text-stone-100 text-xs">
                    </div>
                </div>

                <div class="flex items-center justify-between text-xs">
                    <label class="flex items-center text-stone-600 dark:text-stone-400 cursor-pointer">
                        <input type="checkbox" class="rounded border-stone-300 text-orange-600 mr-2 h-3.5 w-3.5 accent-orange-600"> Remember me
                    </label>
                    <a href="javascript:void(0)" onclick="alert('Please contact System Administrator to reset password.');" class="text-orange-500 hover:underline font-semibold">Forgot password?</a>
                </div>

                <button type="submit" class="w-full bg-orange-gradient text-white font-bold text-xs py-3 px-4 rounded-xl transition-all shadow-md flex items-center justify-center gap-2">
                    <i class="fa-solid fa-right-to-bracket"></i> SIGN IN TO DASHBOARD
                </button>
            </form>

            <div class="pt-2 text-center text-xs text-stone-500 dark:text-stone-400">
                Don't have an account yet? <button onclick="toggleForms('register')" class="text-orange-500 font-extrabold hover:underline">Register here</button>
            </div>
        </div>

        
        <div id="register-box" class="w-full max-w-md space-y-4 form-fade overflow-y-auto max-h-[calc(100vh-100px)] custom-scrollbar pr-1 <?php echo ($active_form === 'register') ? '' : 'hidden'; ?>">
            <div>
                <span class="text-[10px] font-black tracking-widest text-orange-500 uppercase">Create Account</span>
                <h2 class="serif-title text-2xl font-bold text-stone-900 dark:text-stone-100 mt-0.5">Register for QuestBank</h2>
            </div>

            <form action="index.php" method="POST" onsubmit="showSkeleton()" class="space-y-3">
                <?php echo csrfInputField(); ?>
                <input type="hidden" name="action_register" value="1">
                
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div class="space-y-1">
                        <label class="text-[10px] font-bold uppercase tracking-wider text-stone-600 dark:text-stone-400">Full Name</label>
                        <div class="flex items-center bg-white dark:bg-stone-900 border border-stone-200 dark:border-stone-800 rounded-xl px-3 py-2">
                            <input type="text" id="reg_fullname" name="fullname" placeholder="Juan Dela Cruz" class="w-full bg-transparent outline-none text-stone-800 dark:text-stone-100 text-xs">
                        </div>
                    </div>
                    <div class="space-y-1">
                        <label class="text-[10px] font-bold uppercase tracking-wider text-stone-600 dark:text-stone-400">Username</label>
                        <div class="flex items-center bg-white dark:bg-stone-900 border border-stone-200 dark:border-stone-800 rounded-xl px-3 py-2">
                            <input type="text" id="reg_username" name="username" placeholder="juan2026" class="w-full bg-transparent outline-none text-stone-800 dark:text-stone-100 text-xs">
                        </div>
                    </div>
                </div>

                <div class="space-y-1">
                    <label class="text-[10px] font-bold uppercase tracking-wider text-stone-600 dark:text-stone-400">Email Address</label>
                    <div class="flex items-center bg-white dark:bg-stone-900 border border-stone-200 dark:border-stone-800 rounded-xl px-3 py-2">
                        <input type="email" id="reg_email" name="email" placeholder="juan@questbank.edu.ph" class="w-full bg-transparent outline-none text-stone-800 dark:text-stone-100 text-xs">
                    </div>
                </div>

                <div class="space-y-1">
                    <label class="text-[10px] font-bold uppercase tracking-wider text-stone-600 dark:text-stone-400">System Role</label>
                    <div class="flex items-center bg-white dark:bg-stone-900 border border-stone-200 dark:border-stone-800 rounded-xl px-3 py-2">
                        <select id="reg_role" name="role" onchange="toggleStudentFields()" class="w-full bg-transparent outline-none text-stone-800 dark:text-stone-100 text-xs cursor-pointer">
                            <option value="" disabled selected>Select Role</option>
                            <option value="student">Student</option>
                            <option value="teacher">Teacher</option>
                            <option value="admin">Admin</option>
                        </select>
                    </div>
                </div>

                
                <div id="student-fields" class="hidden space-y-3 border-l-2 border-orange-500 pl-3 py-1">
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="text-[10px] font-bold uppercase text-stone-500">Student ID</label>
                            <input type="text" id="student_number" name="student_number" placeholder="2026-00123" class="w-full bg-white dark:bg-stone-900 border rounded-lg p-2 text-xs">
                        </div>
                        <div>
                            <label class="text-[10px] font-bold uppercase text-stone-500">Course</label>
                            <input type="text" id="course" name="course" placeholder="BSIT" class="w-full bg-white dark:bg-stone-900 border rounded-lg p-2 text-xs">
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="text-[10px] font-bold uppercase text-stone-500">Year Level</label>
                            <select id="year_level" name="year_level" class="w-full bg-white dark:bg-stone-900 border rounded-lg p-2 text-xs">
                                <option value="1">1st Year</option>
                                <option value="2">2nd Year</option>
                                <option value="3" selected>3rd Year</option>
                                <option value="4">4th Year</option>
                            </select>
                        </div>
                        <div>
                            <label class="text-[10px] font-bold uppercase text-stone-500">Section</label>
                            <input type="text" id="section" name="section" placeholder="A" class="w-full bg-white dark:bg-stone-900 border rounded-lg p-2 text-xs">
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div class="space-y-1">
                        <label class="text-[10px] font-bold uppercase tracking-wider text-stone-600 dark:text-stone-400">Password</label>
                        <input type="password" id="reg_password" name="password" placeholder="••••••••" class="w-full bg-white dark:bg-stone-900 border border-stone-200 dark:border-stone-800 rounded-xl p-2 text-xs">
                    </div>
                    <div class="space-y-1">
                        <label class="text-[10px] font-bold uppercase tracking-wider text-stone-600 dark:text-stone-400">Confirm Password</label>
                        <input type="password" id="confirm_password" name="confirm_password" placeholder="••••••••" class="w-full bg-white dark:bg-stone-900 border border-stone-200 dark:border-stone-800 rounded-xl p-2 text-xs">
                    </div>
                </div>

                <button type="submit" class="w-full bg-stone-900 dark:bg-orange-600 hover:bg-orange-600 text-white font-bold text-xs py-3 rounded-xl transition-all shadow-md">
                    REGISTER ACCOUNT
                </button>
            </form>

            <div class="text-center text-xs text-stone-500">
                Already registered? <button onclick="toggleForms('login')" class="text-orange-500 font-bold hover:underline">Sign in here</button>
            </div>
        </div>
    </div>

    
    <script>
        function toggleDarkMode() {
            document.documentElement.classList.toggle('dark');
        }

        function showSkeleton() {
            document.getElementById('skeleton-overlay').classList.remove('hidden');
            document.getElementById('skeleton-overlay').classList.add('flex');
        }

        function toggleForms(view) {
            const loginBox = document.getElementById('login-box');
            const registerBox = document.getElementById('register-box');
            
            if (view === 'register') {
                loginBox.classList.add('hidden');
                registerBox.classList.remove('hidden');
            } else {
                loginBox.classList.remove('hidden');
                registerBox.classList.add('hidden');
            }
            toggleStudentFields();
        }

        function toggleStudentFields() {
            const roleSelect = document.getElementById('reg_role');
            const studentFields = document.getElementById('student-fields');
            const isRegisterVisible = !document.getElementById('register-box').classList.contains('hidden');
            
            const basicFields = ['reg_fullname', 'reg_username', 'reg_email', 'reg_role', 'reg_password', 'confirm_password'];
            const studentInputs = studentFields.querySelectorAll('input, select');

            if (isRegisterVisible) {
                basicFields.forEach(id => {
                    const el = document.getElementById(id);
                    if (el) el.setAttribute('required', 'true');
                });
                
                if (roleSelect.value === 'student') {
                    studentFields.classList.remove('hidden');
                    studentInputs.forEach(input => input.setAttribute('required', 'true'));
                } else {
                    studentFields.classList.add('hidden');
                    studentInputs.forEach(input => {
                        input.removeAttribute('required');
                        input.value = '';
                    });
                }
            } else {
                basicFields.forEach(id => {
                    const el = document.getElementById(id);
                    if (el) el.removeAttribute('required');
                });
                studentInputs.forEach(input => input.removeAttribute('required'));
                
                document.getElementById('login_email').setAttribute('required', 'true');
                document.getElementById('login_password').setAttribute('required', 'true');
            }
        }

        window.onload = toggleStudentFields;
    </script>
</body>
</html>