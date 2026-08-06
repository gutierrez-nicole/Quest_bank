<?php

require_once __DIR__ . '/../database.php';
require_once __DIR__ . '/AuditLogService.php';

class SessionManagementService {

    public static function trackSession($userId) {
        if (session_status() !== PHP_SESSION_ACTIVE) return false;
        
        $pdo = getDBConnection();
        $sessId = session_id();
        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        $ua = substr($_SERVER['HTTP_USER_AGENT'] ?? 'Unknown Browser', 0, 255);

        $stmt = $pdo->prepare("
            INSERT INTO user_sessions (session_id, user_id, ip_address, user_agent, login_time, last_activity, status)
            VALUES (?, ?, ?, ?, NOW(), NOW(), 'active')
            ON DUPLICATE KEY UPDATE 
                user_id = VALUES(user_id),
                ip_address = VALUES(ip_address),
                user_agent = VALUES(user_agent),
                last_activity = NOW(),
                status = 'active'
        ");
        return $stmt->execute([$sessId, intval($userId), $ip, $ua]);
    }

    public static function validateCurrentSession($userId) {
        if (session_status() !== PHP_SESSION_ACTIVE) return false;

        $sessId = session_id();
        if (empty($sessId)) return false;

        $pdo = getDBConnection();
        $stmt = $pdo->prepare("SELECT user_id, status, last_activity FROM user_sessions WHERE session_id = ?");
        $stmt->execute([$sessId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            // Track newly established session if missing
            self::trackSession($userId);
            return true;
        }

        if ($row['status'] !== 'active' || intval($row['user_id']) !== intval($userId)) {
            return false; // Session terminated, logged out, or user mismatch!
        }

        // Throttled last_activity update (once every 120 seconds)
        $lastActTime = strtotime($row['last_activity']);
        if ((time() - $lastActTime) > 120) {
            $stmtUpd = $pdo->prepare("UPDATE user_sessions SET last_activity = NOW() WHERE session_id = ?");
            $stmtUpd->execute([$sessId]);
        }

        return true;
    }

    public static function destroyCurrentSession($status = 'logged_out') {
        if (session_status() === PHP_SESSION_ACTIVE) {
            $sessId = session_id();
            if (!empty($sessId)) {
                try {
                    $pdo = getDBConnection();
                    $stmt = $pdo->prepare("UPDATE user_sessions SET status = ? WHERE session_id = ?");
                    $stmt->execute([$status, $sessId]);
                } catch (Exception $e) {}
            }
            $_SESSION = [];
            if (ini_get("session.use_cookies")) {
                $params = session_get_cookie_params();
                setcookie(session_name(), '', time() - 42000,
                    $params["path"], $params["domain"],
                    $params["secure"], $params["httponly"]
                );
            }
            session_destroy();
        }
    }

    public static function getActiveSessions() {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("
            SELECT us.id, us.session_id, us.user_id, us.ip_address, us.user_agent, us.login_time, us.last_activity, u.username, u.fullname, u.role
            FROM user_sessions us
            JOIN users u ON us.user_id = u.id
            WHERE us.status = 'active' AND us.last_activity >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
            ORDER BY us.last_activity DESC
        ");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function terminateSession($sessionId, $actorId = null) {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("UPDATE user_sessions SET status = 'terminated' WHERE session_id = ? OR id = ?");
        $stmt->execute([$sessionId, intval($sessionId)]);

        if ($actorId) {
            AuditLogService::logAction($actorId, "Terminated Active Session", "Session ID/DB ID: {$sessionId}");
        }
        return true;
    }

    public static function terminateUserSessions($userId, $actorId = null) {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("UPDATE user_sessions SET status = 'terminated' WHERE user_id = ?");
        $stmt->execute([intval($userId)]);

        if ($actorId) {
            AuditLogService::logAction($actorId, "Force Logged Out User", "Target User ID: {$userId}");
        }
        return true;
    }
}
