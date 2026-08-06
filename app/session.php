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
    $scriptPath = $_SERVER['PHP_SELF'] ?? $_SERVER['SCRIPT_NAME'] ?? '';
    $rel = preg_match('#/(admin|teacher|student|api|tests)/#', $scriptPath) ? '../' : '';

    // 1. Session Status Enforcement
    if (!SessionManagementService::validateCurrentSession($userId)) {
        SessionManagementService::destroyCurrentSession('terminated');
        header("Location: " . $rel . "index.php?msg=session_ended");
        exit();
    }

    // 2. Mandatory Password Reset Policy Enforcement
    $script = basename($scriptPath);
    $isLogout = isset($_GET['action']) && $_GET['action'] === 'logout';
    if ($script !== 'force_password_reset.php' && !$isLogout && $script !== 'index.php') {
        try {
            $pdo = getDBConnection();
            $stmt = $pdo->prepare("SELECT force_password_reset FROM users WHERE id = ?");
            $stmt->execute([$userId]);
            $isForceReset = intval($stmt->fetchColumn());

            if ($isForceReset === 1) {
                header("Location: " . $rel . "force_password_reset.php");
                exit();
            }
        } catch (Exception $e) {}
    }
}

function requireLogin() {
    $scriptPath = $_SERVER['PHP_SELF'] ?? $_SERVER['SCRIPT_NAME'] ?? '';
    $rel = preg_match('#/(admin|teacher|student|api|tests)/#', $scriptPath) ? '../' : '';
    if (!isset($_SESSION['user_id'])) {
        header("Location: " . $rel . "index.php");
        exit();
    }
    verifySessionAndPolicy();
}

function requireRole($allowedRole) {
    requireLogin();
    $scriptPath = $_SERVER['PHP_SELF'] ?? $_SERVER['SCRIPT_NAME'] ?? '';
    $rel = preg_match('#/(admin|teacher|student|api|tests)/#', $scriptPath) ? '../' : '';
    if (($_SESSION['role'] ?? '') !== $allowedRole) {
        if ($_SESSION['role'] === 'student') {
            header("Location: " . $rel . "student/dashboard.php");
        } elseif ($_SESSION['role'] === 'teacher') {
            header("Location: " . $rel . "teacher/dashboard.php");
        } elseif ($_SESSION['role'] === 'admin') {
            header("Location: " . $rel . "admin/dashboard.php");
        } else {
            header("Location: " . $rel . "index.php");
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
