<?php
require_once __DIR__ . '/../app/bootstrap.php';
require_once __DIR__ . '/../app/services/NotificationService.php';

header('Content-Type: application/json');

if (!AuthService::isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized: Please log in.']);
    exit;
}

$userId = getCurrentUserId();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken()) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Invalid or expired CSRF token.']);
        exit;
    }

    $action = $_POST['action'] ?? '';
    $notificationId = intval($_POST['notification_id'] ?? 0);

    try {
        if ($action === 'mark_read') {
            $success = NotificationService::markAsRead($notificationId, $userId);
            echo json_encode(['success' => (bool)$success, 'unread_count' => NotificationService::getUnreadCount($userId)]);
        } elseif ($action === 'mark_all_read') {
            $success = NotificationService::markAllAsRead($userId);
            echo json_encode(['success' => (bool)$success, 'unread_count' => 0]);
        } elseif ($action === 'delete') {
            $success = NotificationService::deleteNotification($notificationId, $userId);
            echo json_encode(['success' => (bool)$success, 'unread_count' => NotificationService::getUnreadCount($userId)]);
        } else {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Unknown notification action.']);
        }
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

http_response_code(405);
echo json_encode(['success' => false, 'error' => 'Method not allowed.']);
