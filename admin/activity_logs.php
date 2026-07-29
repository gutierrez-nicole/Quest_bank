<?php
require_once __DIR__ . '/../app/bootstrap.php';

AuthService::enforceRole('admin');
$pdo = getDBConnection();

$page = max(1, intval($_GET['page'] ?? 1));
$per_page = 50;
$offset = ($page - 1) * $per_page;

try {
    $total_logs = intval($pdo->query("SELECT COUNT(*) FROM activity_logs")->fetchColumn());
    $total_pages = max(1, ceil($total_logs / $per_page));

    $stmt = $pdo->prepare("
        SELECT al.id, al.action_description, al.created_at, u.fullname, u.role, u.email 
        FROM activity_logs al 
        JOIN users u ON al.user_id = u.id 
        ORDER BY al.id DESC
        LIMIT ? OFFSET ?
    ");
    $stmt->bindValue(1, $per_page, PDO::PARAM_INT);
    $stmt->bindValue(2, $offset, PDO::PARAM_INT);
    $stmt->execute();
    $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { 
    $logs = []; 
    $total_pages = 1;
    $total_logs = 0;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>QuestBank - Audit Trail & Activity Logs</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght=400;600;700;800&display=swap" rel="stylesheet">
    <style> body { font-family: 'Plus Jakarta Sans', sans-serif; } </style>
</head>
<body class="bg-[#f3f4f6] min-h-screen flex">
    <?php require_once __DIR__ . '/../includes/admin_sidebar.php'; ?>
    <main class="flex-1 ml-16 lg:ml-64 p-6 md:p-12 overflow-y-auto min-h-screen">
        <div class="max-w-6xl mx-auto space-y-6">
            <div class="flex justify-between items-center">
                <div>
                    <a href="dashboard.php" class="text-xs font-bold text-orange-600 hover:underline"><i class="fa-solid fa-arrow-left mr-1"></i> Back to Dashboard</a>
                    <h1 class="text-2xl font-extrabold text-stone-800 mt-2"><i class="fa-solid fa-clipboard-list text-orange-600 mr-1"></i> Global System Activity Audit Log</h1>
                    <p class="text-xs text-stone-400">Comprehensive real-time telemetry logging for Faculty Teachers, Students, and Administrators.</p>
                </div>
                <button onclick="window.print()" class="bg-stone-900 hover:bg-orange-600 text-white font-bold text-xs px-4 py-2.5 rounded-xl transition-all shadow-sm">
                    <i class="fa-solid fa-print mr-1"></i> Print Audit Trail
                </button>
            </div>

            <div class="bg-white border border-stone-200 rounded-2xl p-6 shadow-sm">
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse text-xs text-stone-700">
                        <thead>
                            <tr class="bg-stone-50 border-b text-stone-500 font-bold uppercase text-[10px]">
                                <th class="p-3">Log Event ID</th>
                                <th class="p-3">Operator User</th>
                                <th class="p-3">System Role</th>
                                <th class="p-3">Action Event Description</th>
                                <th class="p-3 text-right">Timestamp</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y font-semibold">
                            <?php if (!empty($logs)): ?>
                                <?php foreach ($logs as $log): ?>
                                    <tr class="hover:bg-stone-50/50">
                                        <td class="p-3 font-mono text-orange-600">#LOG-00<?php echo $log['id']; ?></td>
                                        <td class="p-3 font-bold text-stone-800"><?php echo htmlspecialchars($log['fullname']); ?></td>
                                        <td class="p-3">
                                            <?php 
                                            $role = strtolower($log['role']);
                                            $badgeClass = 'bg-stone-100 text-stone-700';
                                            if ($role === 'teacher') $badgeClass = 'bg-blue-100 text-blue-800';
                                            elseif ($role === 'student') $badgeClass = 'bg-orange-100 text-orange-800';
                                            elseif ($role === 'admin') $badgeClass = 'bg-purple-100 text-purple-800';
                                            ?>
                                            <span class="px-2.5 py-0.5 rounded text-[10px] font-bold uppercase <?php echo $badgeClass; ?>">
                                                <?php echo htmlspecialchars($log['role']); ?>
                                            </span>
                                        </td>
                                        <td class="p-3 text-stone-600 font-medium"><?php echo htmlspecialchars($log['action_description']); ?></td>
                                        <td class="p-3 text-right text-stone-400 font-medium"><?php echo date('M d, Y h:i A', strtotime($log['created_at'])); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="5" class="p-6 text-center text-stone-400 font-semibold">No activity logs recorded yet.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                <?php if ($total_pages > 1): ?>
                <div class="flex items-center justify-between mt-6 pt-4 border-t border-stone-200">
                    <p class="text-xs text-stone-400 font-semibold">Showing page <?php echo $page; ?> of <?php echo $total_pages; ?> (<?php echo $total_logs; ?> total logs)</p>
                    <div class="flex gap-2">
                        <?php if ($page > 1): ?>
                        <a href="?page=<?php echo $page - 1; ?>" class="px-3 py-1.5 bg-stone-100 hover:bg-orange-100 text-stone-600 hover:text-orange-700 rounded-lg text-xs font-bold transition-all"><i class="fa-solid fa-chevron-left mr-1"></i>Previous</a>
                        <?php endif; ?>
                        <?php if ($page < $total_pages): ?>
                        <a href="?page=<?php echo $page + 1; ?>" class="px-3 py-1.5 bg-stone-900 hover:bg-orange-600 text-white rounded-lg text-xs font-bold transition-all">Next<i class="fa-solid fa-chevron-right ml-1"></i></a>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </main>
</body>
</html>