<?php

require_once __DIR__ . '/../database.php';
require_once __DIR__ . '/../session.php';
require_once __DIR__ . '/../../includes/security.php';

class AuthService {
    
    public static function getCurrentUser() {
        $userId = getCurrentUserId();
        if (!$userId) return null;

        $pdo = getDBConnection();
        $stmt = $pdo->prepare("SELECT id, username, fullname, email, role, specialization, created_at FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public static function enforceRole($requiredRole) {
        requireRole($requiredRole);
    }

    public static function logUserActivity($actionDescription, $userId = null) {
        logActivity($actionDescription, $userId);
    }
}
