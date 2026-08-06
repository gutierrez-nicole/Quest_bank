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

function verifyCSRFToken($token = null) {
    if ($token === null) {
        $token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    }
    if (empty($token) || empty($_SESSION['csrf_token'])) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

function validateCSRFToken() {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!verifyCSRFToken()) {
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

function generatePartialToken($teacherId, array $originalLessonIds, array $validLessonIds, array $invalidLessonIds, $subject, $program, $yearLevel, $semester, $schoolYear, array $academicPeriods, array $warnings, $secretKey) {
    $timestamp = time();
    $nonce = bin2hex(random_bytes(16));
    $payloadData = [
        'teacher_id' => (int)$teacherId,
        'original_lesson_ids' => array_values(array_map('intval', $originalLessonIds)),
        'valid_ids' => array_values(array_map('intval', $validLessonIds)),
        'invalid_ids' => array_values(array_map('intval', $invalidLessonIds)),
        'subject' => (string)$subject,
        'program' => (string)$program,
        'year_level' => (string)$yearLevel,
        'semester' => (string)$semester,
        'school_year' => (string)$schoolYear,
        'academic_periods' => array_values($academicPeriods),
        'warnings' => array_values($warnings),
        'timestamp' => $timestamp,
        'nonce' => $nonce
    ];
    $json = json_encode($payloadData);
    $signature = hash_hmac('sha256', $json, $secretKey);
    return base64_encode(json_encode(['payload' => $payloadData, 'sig' => $signature]));
}

function verifyPartialToken($tokenString, $expectedTeacherId, $secretKey, $contextVerification = [], $maxAgeSeconds = 900) {
    if (empty($tokenString)) return false;
    $decoded = json_decode(base64_decode($tokenString), true);
    if (!is_array($decoded) || empty($decoded['payload']) || empty($decoded['sig'])) {
        return false;
    }
    $payload = $decoded['payload'];
    $sig = $decoded['sig'];
    
    // HMAC signature verification
    $expectedSig = hash_hmac('sha256', json_encode($payload), $secretKey);
    if (!hash_equals($expectedSig, $sig)) {
        return false;
    }

    // Teacher ID verification
    if (intval($payload['teacher_id']) !== intval($expectedTeacherId)) {
        return false;
    }

    // Expiry check (15 minutes)
    if (time() - intval($payload['timestamp']) > $maxAgeSeconds) {
        return false;
    }

    // Context binding verification if provided
    if (!empty($contextVerification)) {
        foreach (['subject', 'program', 'year_level', 'semester', 'school_year'] as $ctxKey) {
            if (isset($contextVerification[$ctxKey]) && strcasecmp(trim((string)$contextVerification[$ctxKey]), trim((string)($payload[$ctxKey] ?? ''))) !== 0) {
                return false; // Modified context!
            }
        }
    }

    // Nonce existence check
    if (empty($payload['nonce']) || !is_string($payload['nonce'])) {
        return false;
    }

    // Fail-Closed Replay prevention check via used_confirmation_tokens DB
    try {
        $pdo = getDBConnection();
        if (!$pdo) {
            return false; // DB unavailable -> FAIL CLOSED!
        }
        $tokenHash = hash('sha256', $tokenString);
        $stmtCheck = $pdo->prepare("SELECT COUNT(*) FROM used_confirmation_tokens WHERE token_hash = ?");
        $stmtCheck->execute([$tokenHash]);
        if ($stmtCheck->fetchColumn() > 0) {
            return false; // Token already used/replayed!
        }

        // Record token usage to prevent replay
        $stmtMark = $pdo->prepare("INSERT INTO used_confirmation_tokens (token_hash, teacher_id, used_at, expires_at) VALUES (?, ?, NOW(), FROM_UNIXTIME(?))");
        $stmtMark->execute([$tokenHash, $expectedTeacherId, intval($payload['timestamp']) + $maxAgeSeconds]);
    } catch (Throwable $e) {
        // FAIL CLOSED: If DB check or nonce persistence fails, reject token!
        return false;
    }

    return $payload;
}

function generateIncompleteAckToken($teacherId, $batchId, $failedChunkCount, array $affectedLessonIds, $requestedCount, $generatedCount, array $warnings, $secretKey) {
    $timestamp = time();
    $nonce = bin2hex(random_bytes(16));
    $payload = [
        'teacher_id' => (int)$teacherId,
        'generation_batch_id' => (string)$batchId,
        'failed_chunk_count' => (int)$failedChunkCount,
        'affected_lesson_ids' => array_values(array_map('intval', $affectedLessonIds)),
        'requested_question_count' => (int)$requestedCount,
        'generated_question_count' => (int)$generatedCount,
        'warnings' => array_values($warnings),
        'timestamp' => $timestamp,
        'nonce' => $nonce
    ];
    $sig = hash_hmac('sha256', json_encode($payload), $secretKey);
    return base64_encode(json_encode(['payload' => $payload, 'sig' => $sig]));
}

function verifyIncompleteAckToken($tokenString, $expectedTeacherId, $secretKey, $expectedBatchId = null, $maxAgeSeconds = 900) {
    if (empty($tokenString)) return false;
    $decoded = json_decode(base64_decode($tokenString), true);
    if (!is_array($decoded) || empty($decoded['payload']) || empty($decoded['sig'])) {
        return false;
    }
    $payload = $decoded['payload'];
    $sig = $decoded['sig'];

    // HMAC signature verification
    $expectedSig = hash_hmac('sha256', json_encode($payload), $secretKey);
    if (!hash_equals($expectedSig, $sig)) {
        return false;
    }

    if (intval($payload['teacher_id']) !== intval($expectedTeacherId)) {
        return false;
    }

    if (!empty($expectedBatchId) && strcasecmp(trim($payload['generation_batch_id']), trim($expectedBatchId)) !== 0) {
        return false;
    }

    if (time() - intval($payload['timestamp']) > $maxAgeSeconds) {
        return false;
    }

    if (empty($payload['nonce']) || !is_string($payload['nonce'])) {
        return false;
    }

    // Fail-Closed Replay check via used_confirmation_tokens
    try {
        $pdo = getDBConnection();
        if (!$pdo) return false;
        $tokenHash = hash('sha256', $tokenString);
        $stmtCheck = $pdo->prepare("SELECT COUNT(*) FROM used_confirmation_tokens WHERE token_hash = ?");
        $stmtCheck->execute([$tokenHash]);
        if ($stmtCheck->fetchColumn() > 0) {
            return false;
        }

        $stmtMark = $pdo->prepare("INSERT INTO used_confirmation_tokens (token_hash, teacher_id, used_at, expires_at) VALUES (?, ?, NOW(), FROM_UNIXTIME(?))");
        $stmtMark->execute([$tokenHash, $expectedTeacherId, intval($payload['timestamp']) + $maxAgeSeconds]);
    } catch (Throwable $e) {
        return false;
    }

    return $payload;
}