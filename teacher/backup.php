<?php
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    header("Location: ../index.php");
    exit();
}

$host = 'localhost';
$dbname = 'bankquest_db';
$db_user = 'root';
$db_pass = '';

$success_msg = "";
$error_msg = "";

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $db_user, $db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Database Connection Error: " . $e->getMessage());
}

// 1. GENERATE SQL BACKUP DOWNLOAD
if (isset($_GET['action']) && $_GET['action'] === 'download_backup') {
    $tables = [];
    $query = $pdo->query("SHOW TABLES");
    while ($row = $query->fetch(PDO::FETCH_NUM)) {
        $tables[] = $row[0];
    }

    $sqlScript = "-- QuestBank Database Backup\n-- Date: " . date('Y-m-d H:i:s') . "\n\n";
    $sqlScript .= "SET FOREIGN_KEY_CHECKS=0;\n\n";

    foreach ($tables as $table) {
        $query = $pdo->query("SHOW CREATE TABLE `$table`");
        $row = $query->fetch(PDO::FETCH_NUM);
        $sqlScript .= $row[1] . ";\n\n";

        $query = $pdo->query("SELECT * FROM `$table`");
        while ($row = $query->fetch(PDO::FETCH_ASSOC)) {
            $sqlScript .= "INSERT INTO `$table` VALUES(";
            $first = true;
            foreach ($row as $value) {
                if (!$first) $sqlScript .= ", ";
                $sqlScript .= isset($value) ? $pdo->quote($value) : "NULL";
                $first = false;
            }
            $sqlScript .= ");\n";
        }
        $sqlScript .= "\n";
    }

    $sqlScript .= "SET FOREIGN_KEY_CHECKS=1;\n";

    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="bankquest_backup_' . date('Y_m_d_His') . '.sql"');
    header('Content-Length: ' . strlen($sqlScript));
    echo $sqlScript;
    exit();
}

// 2. RESTORE SQL BACKUP FILE
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['restore_backup'])) {
    if (isset($_FILES['backup_file']) && $_FILES['backup_file']['error'] === UPLOAD_ERR_OK) {
        $file_tmp = $_FILES['backup_file']['tmp_name'];
        $sqlContent = file_get_contents($file_tmp);

        try {
            $pdo->exec($sqlContent);
            $success_msg = "Database restored successfully from backup file!";
        } catch (PDOException $e) {
            $error_msg = "Error restoring database: " . $e->getMessage();
        }
    } else {
        $error_msg = "Please upload a valid .sql backup file.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>QuestBank - System Backup & Restore</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght=400;600;700;800&display=swap" rel="stylesheet">
    <style> body { font-family: 'Plus Jakarta Sans', sans-serif; } </style>
</head>
<body class="bg-[#fffbf7] min-h-screen p-6 md:p-12">

    <div class="max-w-4xl mx-auto space-y-6">
        <div>
            <a href="dashboard.php" class="text-xs font-bold text-orange-600 hover:underline"><i class="fa-solid fa-arrow-left mr-1"></i> Back to Dashboard</a>
            <h1 class="text-2xl font-extrabold text-stone-800 mt-2"><i class="fa-solid fa-floppy-disk text-orange-600 mr-1"></i> System Backup & Restore</h1>
            <p class="text-xs text-stone-400">Download system database snapshots and recover data backups safely.</p>
        </div>

        <?php if (!empty($success_msg)): ?>
            <div class="bg-emerald-50 border-l-4 border-emerald-500 p-4 rounded-xl text-xs font-semibold text-emerald-700"><?php echo $success_msg; ?></div>
        <?php endif; ?>
        <?php if (!empty($error_msg)): ?>
            <div class="bg-red-50 border-l-4 border-red-500 p-4 rounded-xl text-xs font-semibold text-red-700"><?php echo $error_msg; ?></div>
        <?php endif; ?>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            
            <!-- DOWNLOAD BACKUP -->
            <div class="bg-white border border-stone-200 rounded-2xl p-6 shadow-sm space-y-4 flex flex-col justify-between">
                <div>
                    <div class="w-12 h-12 bg-orange-100 text-orange-600 rounded-2xl flex items-center justify-center font-bold text-xl mb-3">
                        <i class="fa-solid fa-cloud-arrow-down"></i>
                    </div>
                    <h3 class="text-base font-extrabold text-stone-800">Generate SQL Backup</h3>
                    <p class="text-xs text-stone-400 mt-1">Export all tables including users, exam keys, questions, student logs, and grades into a single downloadable .sql file.</p>
                </div>
                <a href="backup.php?action=download_backup" class="w-full bg-orange-600 hover:bg-orange-700 text-white font-bold text-xs py-3 rounded-xl transition-all shadow-md text-center block">
                    <i class="fa-solid fa-download mr-1"></i> Download .SQL Database Backup
                </a>
            </div>

            <!-- RESTORE BACKUP -->
            <div class="bg-white border border-stone-200 rounded-2xl p-6 shadow-sm space-y-4">
                <div>
                    <div class="w-12 h-12 bg-stone-100 text-stone-700 rounded-2xl flex items-center justify-center font-bold text-xl mb-3">
                        <i class="fa-solid fa-file-import"></i>
                    </div>
                    <h3 class="text-base font-extrabold text-stone-800">Restore Database Snapshot</h3>
                    <p class="text-xs text-stone-400 mt-1">Upload a previously exported QuestBank `.sql` backup file to recover your system state.</p>
                </div>
                
                <form action="backup.php" method="POST" enctype="multipart/form-data" class="space-y-3">
                    <input type="file" name="backup_file" accept=".sql" required class="block w-full text-xs text-stone-500 file:mr-4 file:py-2 file:px-4 file:rounded-xl file:border-0 file:text-xs file:font-bold file:bg-stone-900 file:text-white hover:file:bg-orange-600 cursor-pointer">
                    <button type="submit" name="restore_backup" onclick="return confirm('WARNING: Restoring will override existing database tables. Proceed?');" class="w-full bg-stone-900 hover:bg-orange-600 text-white font-bold text-xs py-3 rounded-xl transition-all shadow-sm">
                        <i class="fa-solid fa-trash-arrow-up mr-1"></i> Execute Database Restore
                    </button>
                </form>
            </div>

        </div>
    </div>

</body>
</html>