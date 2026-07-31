<?php
require_once __DIR__ . '/../app/bootstrap.php';
require_once __DIR__ . '/../app/services/DepartmentService.php';

AuthService::enforceRole('admin');

$success_msg = '';
$error_msg = '';
$currentUserId = getCurrentUserId();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validateCSRFToken();
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $dept_code = trim(sanitizeInput($_POST['dept_code'] ?? ''));
        $dept_name = trim(sanitizeInput($_POST['dept_name'] ?? ''));
        $programs = trim(sanitizeInput($_POST['programs'] ?? ''));
        $faculty_head = trim(sanitizeInput($_POST['faculty_head'] ?? ''));

        if (!empty($dept_code) && !empty($dept_name)) {
            if (DepartmentService::addDepartment($dept_code, $dept_name, $programs, $faculty_head, $currentUserId)) {
                $success_msg = "Department '{$dept_name}' created successfully!";
            } else {
                $error_msg = "Failed to create department.";
            }
        } else {
            $error_msg = "Please provide both Department Code and Department Name.";
        }
    } elseif ($action === 'edit') {
        $dept_id = intval($_POST['dept_id'] ?? 0);
        $dept_code = trim(sanitizeInput($_POST['dept_code'] ?? ''));
        $dept_name = trim(sanitizeInput($_POST['dept_name'] ?? ''));
        $programs = trim(sanitizeInput($_POST['programs'] ?? ''));
        $faculty_head = trim(sanitizeInput($_POST['faculty_head'] ?? ''));

        if ($dept_id > 0 && !empty($dept_code) && !empty($dept_name)) {
            if (DepartmentService::updateDepartment($dept_id, $dept_code, $dept_name, $programs, $faculty_head, $currentUserId)) {
                $success_msg = "Department '{$dept_name}' updated successfully!";
            } else {
                $error_msg = "Failed to update department.";
            }
        } else {
            $error_msg = "Invalid department record or missing required fields.";
        }
    } elseif ($action === 'delete') {
        $dept_id = intval($_POST['dept_id'] ?? 0);
        if ($dept_id > 0) {
            if (DepartmentService::deleteDepartment($dept_id, $currentUserId)) {
                $success_msg = "Department removed successfully!";
            } else {
                $error_msg = "Failed to delete department.";
            }
        }
    }
}

$departments = DepartmentService::getAllDepartments();
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
<body class="bg-[#f3f4f6] min-h-screen flex">
    <?php require_once __DIR__ . '/../includes/admin_sidebar.php'; ?>
    <main class="flex-1 ml-16 lg:ml-64 p-6 md:p-12 overflow-y-auto min-h-screen">
        <div class="max-w-5xl mx-auto space-y-6">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                <div>
                    <a href="dashboard.php" class="text-xs font-bold text-orange-600 hover:underline"><i class="fa-solid fa-arrow-left mr-1"></i> Back to Dashboard</a>
                    <h1 class="text-2xl font-extrabold text-stone-800 mt-2"><i class="fa-solid fa-building-columns text-orange-600 mr-1"></i> Institutional Departments</h1>
                    <p class="text-xs text-stone-400">Manage academic departments, engineering programs, and faculty heads.</p>
                </div>
                <button onclick="openAddDeptModal()" class="bg-orange-600 hover:bg-orange-700 text-white font-extrabold text-xs px-4 py-2.5 rounded-xl transition-all shadow-md flex items-center gap-2 self-start sm:self-auto">
                    <i class="fa-solid fa-plus"></i> Add New Department
                </button>
            </div>

            <?php if (!empty($success_msg)): ?>
                <div class="bg-emerald-50 border border-emerald-200 text-emerald-800 p-4 rounded-xl text-xs font-bold flex items-center gap-2 animate-fadeIn">
                    <i class="fa-solid fa-circle-check text-emerald-600 text-sm"></i>
                    <?php echo htmlspecialchars($success_msg); ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($error_msg)): ?>
                <div class="bg-rose-50 border border-rose-200 text-rose-800 p-4 rounded-xl text-xs font-bold flex items-center gap-2 animate-fadeIn">
                    <i class="fa-solid fa-triangle-exclamation text-rose-600 text-sm"></i>
                    <?php echo htmlspecialchars($error_msg); ?>
                </div>
            <?php endif; ?>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
                <?php if (!empty($departments)): ?>
                    <?php foreach ($departments as $dept): ?>
                        <div class="p-6 bg-white border border-stone-200 rounded-2xl shadow-sm space-y-4 hover:shadow-md transition-all flex flex-col justify-between">
                            <div class="space-y-3">
                                <div class="flex items-center justify-between">
                                    <div class="w-12 h-12 bg-orange-100 text-orange-600 rounded-xl flex items-center justify-center font-black text-xl shadow-inner">
                                        <i class="fa-solid fa-compass-drafting"></i>
                                    </div>
                                    <span class="bg-stone-100 text-stone-700 font-mono font-black px-2.5 py-1 rounded-md text-[11px] uppercase">
                                        <?php echo htmlspecialchars($dept['dept_code']); ?>
                                    </span>
                                </div>

                                <div>
                                    <h3 class="font-black text-base text-stone-800 leading-tight"><?php echo htmlspecialchars($dept['dept_name']); ?></h3>
                                    <p class="text-xs text-stone-500 font-semibold mt-1"><i class="fa-solid fa-user-tie text-orange-500 mr-1"></i> Head: <?php echo htmlspecialchars($dept['faculty_head']); ?></p>
                                </div>

                                <div class="bg-stone-50 p-3 rounded-xl border border-stone-100 space-y-1">
                                    <p class="text-[10px] font-extrabold uppercase text-stone-400">Programs & Specializations</p>
                                    <p class="text-xs text-stone-700 font-bold leading-relaxed"><?php echo htmlspecialchars($dept['programs']); ?></p>
                                </div>
                            </div>

                            <div class="pt-3 border-t border-stone-100 flex items-center justify-end gap-2 text-xs">
                                <button onclick="openEditDeptModal(<?php echo $dept['id']; ?>, '<?php echo addslashes($dept['dept_code']); ?>', '<?php echo addslashes($dept['dept_name']); ?>', '<?php echo addslashes($dept['programs']); ?>', '<?php echo addslashes($dept['faculty_head']); ?>')" class="px-3 py-1.5 bg-stone-100 hover:bg-orange-100 text-stone-700 hover:text-orange-700 font-bold rounded-lg transition-all flex items-center gap-1">
                                    <i class="fa-solid fa-pen-to-square"></i> Edit
                                </button>
                                <button onclick="openDeleteDeptModal(<?php echo $dept['id']; ?>, '<?php echo addslashes($dept['dept_name']); ?>')" class="px-3 py-1.5 bg-rose-50 hover:bg-rose-100 text-rose-600 font-bold rounded-lg transition-all flex items-center gap-1">
                                    <i class="fa-solid fa-trash-can"></i> Delete
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="col-span-full bg-white p-8 rounded-2xl border border-stone-200 text-center space-y-2">
                        <i class="fa-solid fa-building-circle-xmark text-4xl text-stone-300"></i>
                        <p class="text-sm font-extrabold text-stone-600">No departments configured yet.</p>
                        <p class="text-xs text-stone-400">Click "+ Add New Department" to set up academic divisions.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>

    
    <div id="add_dept_modal" class="fixed inset-0 bg-stone-950/70 backdrop-blur-sm hidden items-center justify-center z-50 p-4">
        <div class="bg-white p-6 rounded-2xl max-w-md w-full space-y-4 shadow-2xl animate-fadeIn border border-stone-200">
            <div class="flex items-center justify-between border-b pb-3">
                <h3 class="font-extrabold text-stone-800 text-base"><i class="fa-solid fa-building-columns text-orange-600 mr-1"></i> Add New Department</h3>
                <button onclick="closeAddDeptModal()" class="text-stone-400 hover:text-stone-700 font-bold"><i class="fa-solid fa-xmark text-lg"></i></button>
            </div>
            <form method="POST" class="space-y-4">
                <?php echo csrfInputField(); ?>
                <input type="hidden" name="action" value="add">
                
                <div>
                    <label class="block text-xs font-bold text-stone-600 uppercase mb-1">Department Code</label>
                    <input type="text" name="dept_code" placeholder="e.g. DCE" required class="w-full px-3.5 py-2.5 border rounded-xl text-xs font-bold focus:outline-none focus:border-orange-500">
                </div>

                <div>
                    <label class="block text-xs font-bold text-stone-600 uppercase mb-1">Department Name</label>
                    <input type="text" name="dept_name" placeholder="e.g. Department of Civil Engineering" required class="w-full px-3.5 py-2.5 border rounded-xl text-xs font-bold focus:outline-none focus:border-orange-500">
                </div>

                <div>
                    <label class="block text-xs font-bold text-stone-600 uppercase mb-1">Programs & Specializations</label>
                    <input type="text" name="programs" placeholder="e.g. BSCE (Structural, Geotechnical, Water Resources)" required class="w-full px-3.5 py-2.5 border rounded-xl text-xs font-bold focus:outline-none focus:border-orange-500">
                </div>

                <div>
                    <label class="block text-xs font-bold text-stone-600 uppercase mb-1">Faculty Head / Chairperson</label>
                    <input type="text" name="faculty_head" placeholder="e.g. Prof. Jolas Santos" required class="w-full px-3.5 py-2.5 border rounded-xl text-xs font-bold focus:outline-none focus:border-orange-500">
                </div>

                <div class="flex justify-end gap-2 pt-2 border-t">
                    <button type="button" onclick="closeAddDeptModal()" class="px-4 py-2.5 bg-stone-100 hover:bg-stone-200 text-stone-700 font-bold text-xs rounded-xl">Cancel</button>
                    <button type="submit" class="px-4 py-2.5 bg-orange-600 hover:bg-orange-700 text-white font-bold text-xs rounded-xl shadow-md">Create Department</button>
                </div>
            </form>
        </div>
    </div>

    
    <div id="edit_dept_modal" class="fixed inset-0 bg-stone-950/70 backdrop-blur-sm hidden items-center justify-center z-50 p-4">
        <div class="bg-white p-6 rounded-2xl max-w-md w-full space-y-4 shadow-2xl animate-fadeIn border border-stone-200">
            <div class="flex items-center justify-between border-b pb-3">
                <h3 class="font-extrabold text-stone-800 text-base"><i class="fa-solid fa-pen-to-square text-orange-600 mr-1"></i> Edit Department Details</h3>
                <button onclick="closeEditDeptModal()" class="text-stone-400 hover:text-stone-700 font-bold"><i class="fa-solid fa-xmark text-lg"></i></button>
            </div>
            <form method="POST" class="space-y-4">
                <?php echo csrfInputField(); ?>
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="dept_id" id="edit_dept_id">
                
                <div>
                    <label class="block text-xs font-bold text-stone-600 uppercase mb-1">Department Code</label>
                    <input type="text" name="dept_code" id="edit_dept_code" required class="w-full px-3.5 py-2.5 border rounded-xl text-xs font-bold focus:outline-none focus:border-orange-500">
                </div>

                <div>
                    <label class="block text-xs font-bold text-stone-600 uppercase mb-1">Department Name</label>
                    <input type="text" name="dept_name" id="edit_dept_name" required class="w-full px-3.5 py-2.5 border rounded-xl text-xs font-bold focus:outline-none focus:border-orange-500">
                </div>

                <div>
                    <label class="block text-xs font-bold text-stone-600 uppercase mb-1">Programs & Specializations</label>
                    <input type="text" name="programs" id="edit_programs" required class="w-full px-3.5 py-2.5 border rounded-xl text-xs font-bold focus:outline-none focus:border-orange-500">
                </div>

                <div>
                    <label class="block text-xs font-bold text-stone-600 uppercase mb-1">Faculty Head / Chairperson</label>
                    <input type="text" name="faculty_head" id="edit_faculty_head" required class="w-full px-3.5 py-2.5 border rounded-xl text-xs font-bold focus:outline-none focus:border-orange-500">
                </div>

                <div class="flex justify-end gap-2 pt-2 border-t">
                    <button type="button" onclick="closeEditDeptModal()" class="px-4 py-2.5 bg-stone-100 hover:bg-stone-200 text-stone-700 font-bold text-xs rounded-xl">Cancel</button>
                    <button type="submit" class="px-4 py-2.5 bg-orange-600 hover:bg-orange-700 text-white font-bold text-xs rounded-xl shadow-md">Save Changes</button>
                </div>
            </form>
        </div>
    </div>

    
    <div id="delete_dept_modal" class="fixed inset-0 bg-stone-950/70 backdrop-blur-sm hidden items-center justify-center z-50 p-4">
        <div class="bg-white p-6 rounded-2xl max-w-sm w-full space-y-4 shadow-2xl animate-fadeIn border border-stone-200">
            <div class="flex items-center justify-between border-b pb-3">
                <h3 class="font-extrabold text-rose-600 text-base"><i class="fa-solid fa-triangle-exclamation mr-1"></i> Delete Department</h3>
                <button onclick="closeDeleteDeptModal()" class="text-stone-400 hover:text-stone-700 font-bold"><i class="fa-solid fa-xmark text-lg"></i></button>
            </div>
            <form method="POST" class="space-y-4">
                <?php echo csrfInputField(); ?>
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="dept_id" id="delete_dept_id">
                
                <p class="text-xs text-stone-600 leading-relaxed font-semibold">
                    Are you sure you want to remove <span id="delete_dept_name" class="font-black text-stone-800"></span> from institutional departments?
                </p>

                <div class="flex justify-end gap-2 pt-2 border-t">
                    <button type="button" onclick="closeDeleteDeptModal()" class="px-4 py-2.5 bg-stone-100 hover:bg-stone-200 text-stone-700 font-bold text-xs rounded-xl">Cancel</button>
                    <button type="submit" class="px-4 py-2.5 bg-rose-600 hover:bg-rose-700 text-white font-bold text-xs rounded-xl shadow-md">Delete Department</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openAddDeptModal() {
            document.getElementById('add_dept_modal').classList.remove('hidden');
            document.getElementById('add_dept_modal').classList.add('flex');
        }
        function closeAddDeptModal() {
            document.getElementById('add_dept_modal').classList.add('hidden');
            document.getElementById('add_dept_modal').classList.remove('flex');
        }

        function openEditDeptModal(id, code, name, programs, head) {
            document.getElementById('edit_dept_id').value = id;
            document.getElementById('edit_dept_code').value = code;
            document.getElementById('edit_dept_name').value = name;
            document.getElementById('edit_programs').value = programs;
            document.getElementById('edit_faculty_head').value = head;
            document.getElementById('edit_dept_modal').classList.remove('hidden');
            document.getElementById('edit_dept_modal').classList.add('flex');
        }
        function closeEditDeptModal() {
            document.getElementById('edit_dept_modal').classList.add('hidden');
            document.getElementById('edit_dept_modal').classList.remove('flex');
        }

        function openDeleteDeptModal(id, name) {
            document.getElementById('delete_dept_id').value = id;
            document.getElementById('delete_dept_name').textContent = name;
            document.getElementById('delete_dept_modal').classList.remove('hidden');
            document.getElementById('delete_dept_modal').classList.add('flex');
        }
        function closeDeleteDeptModal() {
            document.getElementById('delete_dept_modal').classList.add('hidden');
            document.getElementById('delete_dept_modal').classList.remove('flex');
        }
    </script>
</body>
</html>