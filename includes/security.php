<?php
// includes/security.php - QuestBank Security & Anti-CSRF Helper

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Generate CSRF Token
function generateCSRFToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

// Render CSRF Hidden Input Field for Forms
function csrfInputField() {
    $token = generateCSRFToken();
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($token) . '">';
}

// Validate CSRF Token on POST requests
function validateCSRFToken() {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'])) {
            die("Security Error: Invalid or expired CSRF token. Please refresh and try again.");
        }
    }
}

// Prevent Direct Script Execution without Login Session
function enforceRoleSession($allowed_role) {
    if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== $allowed_role) {
        header("Location: ../index.php");
        exit();
    }
}
?>