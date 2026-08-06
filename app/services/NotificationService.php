<?php

require_once __DIR__ . '/../database.php';

class NotificationService {

    public static function sendNotification($userId, $type, $message) {
        $userId = intval($userId);
        $type = trim($type);
        $message = trim($message);

        if ($userId <= 0 || empty($message)) {
            return false;
        }

        $pdo = getDBConnection();
        $stmt = $pdo->prepare("
            INSERT INTO notifications (user_id, type, message, is_read, created_at)
            VALUES (?, ?, ?, 0, NOW())
        ");
        return $stmt->execute([$userId, $type, $message]);
    }

    public static function getUserNotifications($userId, $limit = 20) {
        $pdo = getDBConnection();
        $limit = max(1, intval($limit));
        $stmt = $pdo->prepare("
            SELECT id, user_id, type, message, is_read, created_at
            FROM notifications
            WHERE user_id = ?
            ORDER BY created_at DESC
            LIMIT {$limit}
        ");
        $stmt->execute([intval($userId)]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function getUnreadCount($userId) {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("
            SELECT COUNT(*) FROM notifications
            WHERE user_id = ? AND (is_read = 0 OR is_read IS NULL)
        ");
        $stmt->execute([intval($userId)]);
        return intval($stmt->fetchColumn());
    }

    public static function markAsRead($notificationId, $userId) {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("
            UPDATE notifications SET is_read = 1
            WHERE id = ? AND user_id = ?
        ");
        return $stmt->execute([intval($notificationId), intval($userId)]);
    }

    public static function markAllAsRead($userId) {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("
            UPDATE notifications SET is_read = 1
            WHERE user_id = ?
        ");
        return $stmt->execute([intval($userId)]);
    }

    public static function deleteNotification($notificationId, $userId) {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("
            DELETE FROM notifications
            WHERE id = ? AND user_id = ?
        ");
        return $stmt->execute([intval($notificationId), intval($userId)]);
    }
}
