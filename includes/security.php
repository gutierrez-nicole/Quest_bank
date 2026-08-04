<?php
require_once __DIR__ . '/../app/session.php';

function setSecurityHeaders() {
    if (!headers_sent()) {
        header("X-Content-Type-Options: nosniff");
        header("X-Frame-Options: SAMEORIGIN");
        header("X-XSS-Protection: 1; mode=block");
        header("Referrer-Policy: strict-origin-when-cross-origin");
        header("Permissions-Policy: camera=(), microphone=(), geolocation=()");
    }
}

setSecurityHeaders();

function generateCSRFToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrfInputField() {
    $token = generateCSRFToken();
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($token, ENT_QUOTES, 'UTF-8') . '">';
}

function validateCSRFToken() {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $token = $_POST['csrf_token'] ?? '';
        if (empty($token) || !hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
            http_response_code(403);
            die("<div style='font-family:sans-serif;text-align:center;padding:50px;'><h2>403 Forbidden - Security Error</h2><p>Invalid or expired CSRF token. Please go back, refresh the page, and try again.</p></div>");
        }
    }
}

function sanitizeInput($data) {
    if (is_array($data)) {
        return array_map('sanitizeInput', $data);
    }
    return htmlspecialchars(trim((string)$data), ENT_QUOTES, 'UTF-8');
}

function logActivity($action_description, $user_id = null) {
    if (!$user_id && isset($_SESSION['user_id'])) {
        $user_id = $_SESSION['user_id'];
    }
    if (!$user_id) return;

    try {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("INSERT INTO activity_logs (user_id, action_description, created_at) VALUES (?, ?, NOW())");
        $stmt->execute([$user_id, $action_description]);
    } catch (Exception $e) {
        error_log("Failed to log activity: " . $e->getMessage());
    }
}

class SecurityException extends Exception {}

function generatePartialToken($teacherId, array $validLessonIds, array $invalidLessonIds, $secretKey) {
    $timestamp = time();
    $nonce = bin2hex(random_bytes(8));
    $payloadData = [
        'teacher_id' => (int)$teacherId,
        'valid_ids' => array_values(array_map('intval', $validLessonIds)),
        'invalid_ids' => array_values(array_map('intval', $invalidLessonIds)),
        'timestamp' => $timestamp,
        'nonce' => $nonce
    ];
    $json = json_encode($payloadData);
    $signature = hash_hmac('sha256', $json, $secretKey);
    return base64_encode(json_encode(['payload' => $payloadData, 'sig' => $signature]));
}

function verifyPartialToken($tokenString, $expectedTeacherId, $secretKey, $maxAgeSeconds = 900) {
    if (empty($tokenString)) return false;
    $decoded = json_decode(base64_decode($tokenString), true);
    if (!is_array($decoded) || empty($decoded['payload']) || empty($decoded['sig'])) {
        return false;
    }
    $payload = $decoded['payload'];
    $sig = $decoded['sig'];
    
    $expectedSig = hash_hmac('sha256', json_encode($payload), $secretKey);
    if (!hash_equals($expectedSig, $sig)) {
        return false;
    }

    if (intval($payload['teacher_id']) !== intval($expectedTeacherId)) {
        return false;
    }

    if (time() - intval($payload['timestamp']) > $maxAgeSeconds) {
        return false;
    }

    return $payload;
}