<?php

if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.cookie_samesite', 'Lax');
    ini_set('session.use_strict_mode', 1);
    session_start();
}

function regenerateSecureSession() {
    session_regenerate_id(true);
}

function requireLogin() {
    if (!isset($_SESSION['user_id'])) {
        header("Location: /index.php");
        exit();
    }
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
