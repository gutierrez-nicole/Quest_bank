<?php

require_once __DIR__ . '/../database.php';

class ActivityLogRepository {

    public static function log($actionDescription, $userId = null) {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("INSERT INTO activity_logs (user_id, action_description) VALUES (?, ?)");
        return $stmt->execute([$userId, $actionDescription]);
    }

    public static function getRecentLogs($limit = 10) {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("
            SELECT al.*, u.fullname, u.role 
            FROM activity_logs al 
            LEFT JOIN users u ON al.user_id = u.id 
            ORDER BY al.id DESC LIMIT " . intval($limit)
        );
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
