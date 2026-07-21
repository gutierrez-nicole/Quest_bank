<?php
session_start();

// Siguraduhing naka-login at isang Admin ang nakapasok
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
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

    // 1. ADD NEW USER
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_user'])) {
        $fullname = trim($_POST['fullname']);
        $username = trim($_POST['username']);
        $email = trim($_POST['email']);
        $role = $_POST['role'];
        $password = password_hash($_POST['password'], PASSWORD_DEFAULT);

        if (!empty($fullname) && !empty($username) && !empty($email) && !empty($_POST['password'])) {
            try {
                $stmt = $pdo->prepare("INSERT INTO users (fullname, username, email, password, role) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$fullname, $username, $email, $password, $role]);
                $success_msg = "New user account ($role) successfully registered!";
            } catch (PDOException $e) {
                $error_msg = "Error registering user (Username or Email may already exist): " . $e->getMessage();
            }
        } else {
            $error_msg = "Please fill in all required fields.";
        }
    }

    // 2. DELETE USER
    if (isset($_GET['delete_id'])) {
        $delete_id = intval($_GET['delete_id']);
        
        // Huwag payagang i-delete ang sariling admin account habang naka-login
        if ($delete_id == $_SESSION['user_id']) {
            $error_msg = "You cannot delete your own active administrator account!";
        } else {
            try {
                $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
                $stmt->execute([$delete_id]);
                $success_msg = "User account successfully removed!";
            } catch (PDOException $e) {
                $error_msg = "Failed to delete user account: " . $e->getMessage();
            }
        }
    }

    // 3. FETCH ALL USERS
    $stmtUsers = $pdo->query("SELECT id, fullname, username, email, role, created_at FROM users ORDER BY id DESC");
    $users = $stmtUsers->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("Database Connection Error: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>QuestBank - System User Management</title>
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- FontAwesome Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght=300;400;500;600;700;800&display=swap" rel="stylesheet">

    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; }
        .bg-orange-gradient { background: linear-gradient(135deg, #f97316 0%, #ea580c 100%); }
    </style>
</head>
<body class="bg-[#f3f4f6] min-h-screen p-6 md:p-12">

    <div class="max-w-6xl mx-auto space-y-6">
        
        <!-- HEADER -->
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <a href="dashboard.php" class="text-xs font-bold text-orange-600 hover:underline flex items-center gap-1">
                    <i class="fa-solid fa-arrow-left"></i> Back to Admin Dashboard
                </a>
                <h1 class="text-2xl font-black text-stone-800 mt-2 flex items-center gap-2">
                    <i class="fa-solid fa-users-gear text-orange-600"></i> Global User Management
                </h1>
                <p class="text-xs text-stone-400">Oversee all system accounts, manage roles (Student, Teacher, Admin), and update user permissions.</p>
            </div>
        </div>

        <!-- ALERTS -->
        <?php if (!empty($success_msg)): ?>
            <div class="bg-emerald-50 border-l-4 border-emerald-500 p-4 rounded-xl text-xs font-semibold text-emerald-700">
                <i class="fa-solid fa-circle-check mr-1"></i> <?php echo $success_msg; ?>
            </div>
        <?php endif; ?>
        <?php if (!empty($error_msg)): ?>
            <div class="bg-rose-50 border-l-4 border-rose-500 p-4 rounded-xl text-xs font-semibold text-rose-700">
                <i class="fa-solid fa-triangle-exclamation mr-1"></i> <?php echo $error_msg; ?>
            </div>
        <?php endif; ?>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            
            <!-- LEFT FORM: CREATE NEW USER -->
            <div class="bg-white border border-stone-200 rounded-2xl p-6 shadow-sm space-y-4 h-fit">
                <h3 class="text-sm font-extrabold uppercase tracking-wider text-stone-800 border-b pb-3">
                    <i class="fa-solid fa-user-plus text-orange-500 mr-1"></i> Add System Account
                </h3>
                
                <form action="manage_users.php" method="POST" class="space-y-4">
                    <div class="space-y-1">
                        <label class="text-xs font-bold text-stone-600">Full Name</label>
                        <input type="text" name="fullname" required placeholder="Juan Dela Cruz" class="w-full bg-stone-50 border border-stone-200 rounded-xl px-4 py-2.5 text-xs outline-none focus:border-orange-500">
                    </div>

                    <div class="space-y-1">
                        <label class="text-xs font-bold text-stone-600">Username</label>
                        <input type="text" name="username" required placeholder="juandelacruz" class="w-full bg-stone-50 border border-stone-200 rounded-xl px-4 py-2.5 text-xs outline-none focus:border-orange-500 font-mono">
                    </div>

                    <div class="space-y-1">
                        <label class="text-xs font-bold text-stone-600">Email Address</label>
                        <input type="email" name="email" required placeholder="juan@questbank.edu.ph" class="w-full bg-stone-50 border border-stone-200 rounded-xl px-4 py-2.5 text-xs outline-none focus:border-orange-500">
                    </div>

                    <div class="space-y-1">
                        <label class="text-xs font-bold text-stone-600">System Role</label>
                        <select name="role" required class="w-full bg-stone-50 border border-stone-200 rounded-xl px-4 py-2.5 text-xs outline-none focus:border-orange-500 cursor-pointer">
                            <option value="student">Student</option>
                            <option value="teacher">Teacher</option>
                            <option value="admin">Administrator</option>
                        </select>
                    </div>

                    <div class="space-y-1">
                        <label class="text-xs font-bold text-stone-600">Account Password</label>
                        <input type="password" name="password" required placeholder="••••••••" class="w-full bg-stone-50 border border-stone-200 rounded-xl px-4 py-2.5 text-xs outline-none focus:border-orange-500">
                    </div>

                    <button type="submit" name="add_user" class="w-full bg-orange-gradient text-white font-bold text-xs py-3 rounded-xl transition-all shadow-md flex items-center justify-center gap-2">
                        <i class="fa-solid fa-plus"></i> Register User Account
                    </button>
                </form>
            </div>

            <!-- RIGHT TABLE: ALL USERS DIRECTORY -->
            <div class="lg:col-span-2 bg-white border border-stone-200 rounded-2xl p-6 shadow-sm space-y-4">
                <div class="flex items-center justify-between border-b pb-3">
                    <h3 class="text-sm font-extrabold uppercase tracking-wider text-stone-800">
                        <i class="fa-solid fa-users text-orange-500 mr-1"></i> Registered System Users
                    </h3>
                    <span class="bg-orange-100 text-orange-700 text-xs font-black px-3 py-1 rounded-full">
                        <?php echo count($users); ?> Total Users
                    </span>
                </div>

                <?php if (!empty($users)): ?>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left border-collapse text-xs text-stone-700">
                            <thead>
                                <tr class="bg-stone-50 border-b border-stone-200 text-stone-400 font-extrabold uppercase text-[10px] tracking-wider">
                                    <th class="p-3">User</th>
                                    <th class="p-3">Username</th>
                                    <th class="p-3">Role</th>
                                    <th class="p-3 text-center">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-stone-100 font-semibold">
                                <?php foreach ($users as $u): ?>
                                    <tr class="hover:bg-stone-50/50 transition-all">
                                        <td class="p-3 font-bold text-stone-800">
                                            <div>
                                                <p class="text-stone-900"><?php echo htmlspecialchars($u['fullname']); ?></p>
                                                <p class="text-[10px] text-stone-400 font-normal"><?php echo htmlspecialchars($u['email']); ?></p>
                                            </div>
                                        </td>
                                        <td class="p-3 font-mono font-bold text-orange-600"><?php echo htmlspecialchars($u['username']); ?></td>
                                        <td class="p-3">
                                            <?php if ($u['role'] === 'admin'): ?>
                                                <span class="bg-purple-100 text-purple-700 text-[10px] font-extrabold px-2.5 py-0.5 rounded-full uppercase">Admin</span>
                                            <?php elseif ($u['role'] === 'teacher'): ?>
                                                <span class="bg-orange-100 text-orange-700 text-[10px] font-extrabold px-2.5 py-0.5 rounded-full uppercase">Teacher</span>
                                            <?php else: ?>
                                                <span class="bg-blue-100 text-blue-700 text-[10px] font-extrabold px-2.5 py-0.5 rounded-full uppercase">Student</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="p-3 text-center">
                                            <?php if ($u['id'] != $_SESSION['user_id']): ?>
                                                <a href="manage_users.php?delete_id=<?php echo $u['id']; ?>" onclick="return confirm('Are you sure you want to delete this account?');" class="bg-rose-50 text-rose-600 hover:bg-rose-600 hover:text-white px-3 py-1.5 rounded-lg text-[10px] font-bold transition-all inline-flex items-center gap-1">
                                                    <i class="fa-solid fa-trash"></i> Delete
                                                </a>
                                            <?php else: ?>
                                                <span class="text-[10px] text-stone-400 italic">Current Session</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="text-center py-12 text-stone-400">
                        <i class="fa-solid fa-users text-4xl mb-3 text-stone-300"></i>
                        <p class="text-sm font-bold">No users registered yet</p>
                    </div>
                <?php endif; ?>
            </div>

        </div>
    </div>

</body>
</html>