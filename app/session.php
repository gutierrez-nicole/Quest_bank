<?php
// app/session.php - Session & Role Authorization Helper

if (session_status() === PHP_SESSION_NONE) {
    session_start();
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
        // Redirect to their appropriate dashboard if logged in with wrong role
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
