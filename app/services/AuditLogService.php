<?php

require_once __DIR__ . '/../database.php';

class AuditLogService {

    public static function logAction($userId, $action, $details = '') {
        $pdo = getDBConnection();
        $userId = intval($userId);
        if ($userId <= 0) {
            $userId = (int)$pdo->query("SELECT id FROM users LIMIT 1")->fetchColumn() ?: null;
        } else {
            $stmtU = $pdo->prepare("SELECT id FROM users WHERE id = ?");
            $stmtU->execute([$userId]);
            if (!$stmtU->fetchColumn()) {
                $userId = (int)$pdo->query("SELECT id FROM users LIMIT 1")->fetchColumn() ?: null;
            }
        }

        $action = trim($action);
        $details = trim($details);
        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';

        $stmt = $pdo->prepare("
            INSERT INTO audit_logs (user_id, actor_id, action, details, entity_type, entity_id, ip_address, created_at)
            VALUES (?, ?, ?, ?, 'system', 0, ?, NOW())
        ");
        return $stmt->execute([$userId, $userId, $action, $details, $ip]);
    }

    public static function getLogs($filters = [], $limit = 100) {
        $pdo = getDBConnection();
        $limit = max(1, intval($limit));
        $where = "WHERE 1=1";
        $params = [];

        if (!empty($filters['action']) && $filters['action'] !== 'all') {
            $where .= " AND a.action LIKE ?";
            $params[] = "%" . $filters['action'] . "%";
        }
        if (!empty($filters['user_id']) && $filters['user_id'] !== 'all') {
            $where .= " AND (a.user_id = ? OR a.actor_id = ?)";
            $params[] = $filters['user_id'];
            $params[] = $filters['user_id'];
        }

        $stmt = $pdo->prepare("
            SELECT a.*, COALESCE(a.details, a.reason) as details, COALESCE(u.fullname, u2.fullname) as actor_name, COALESCE(u.role, u2.role) as actor_role
            FROM audit_logs a
            LEFT JOIN users u ON a.user_id = u.id
            LEFT JOIN users u2 ON a.actor_id = u2.id
            {$where}
            ORDER BY a.id DESC
            LIMIT {$limit}
        ");
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
