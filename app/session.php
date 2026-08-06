<?php

if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.cookie_samesite', 'Lax');
    ini_set('session.use_strict_mode', 1);
    
    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || ($_SERVER['SERVER_PORT'] ?? 80) == 443;
    if ($isHttps) {
        ini_set('session.cookie_secure', 1);
    }
    
    session_start();
}

require_once __DIR__ . '/services/SessionManagementService.php';

function regenerateSecureSession() {
    session_regenerate_id(true);
}

function verifySessionAndPolicy() {
    if (!isset($_SESSION['user_id'])) {
        return;
    }

    $userId = $_SESSION['user_id'];

    // 1. Session Status Enforcement
    if (!SessionManagementService::validateCurrentSession($userId)) {
        SessionManagementService::destroyCurrentSession('terminated');
        header("Location: /index.php?msg=session_ended");
        exit();
    }

    // 2. Mandatory Password Reset Policy Enforcement
    $script = basename($_SERVER['PHP_SELF'] ?? $_SERVER['SCRIPT_NAME'] ?? '');
    $isLogout = isset($_GET['action']) && $_GET['action'] === 'logout';
    if ($script !== 'force_password_reset.php' && !$isLogout && $script !== 'index.php') {
        try {
            $pdo = getDBConnection();
            $stmt = $pdo->prepare("SELECT force_password_reset FROM users WHERE id = ?");
            $stmt->execute([$userId]);
            $isForceReset = intval($stmt->fetchColumn());

            if ($isForceReset === 1) {
                header("Location: /force_password_reset.php");
                exit();
            }
        } catch (Exception $e) {}
    }
}

function requireLogin() {
    if (!isset($_SESSION['user_id'])) {
        header("Location: /index.php");
        exit();
    }
    verifySessionAndPolicy();
}

function requireRole($allowedRole) {
    requireLogin();
    if (($_SESSION['role'] ?? '') !== $allowedRole) {
        if ($_SESSION['role'] === 'student') {
            header("Location: /student/dashboard.php");
        } elseif ($_SESSION['role'] === 'teacher') {
            header("Location: /teacher/dashboard.php");
        } elseif ($_SESSION['role'] === 'admin') {
            header("Location: /admin/dashboard.php");
        } else {
            header("Location: /index.php");
        }
        exit();
    }
}

function getCurrentUserId() {
    return $_SESSION['user_id'] ?? null;
}

function getCurrentUserRole() {
    return $_SESSION['role'] ?? null;
}
