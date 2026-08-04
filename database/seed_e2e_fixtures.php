<?php
/**
 * ==============================================================================
 * QUESTBANK SECURITY NOTICE & DATABASE SEEDER INSTRUCTIONS
 * ==============================================================================
 * WARNING: THIS SCRIPT IS A TEST-ONLY FIXTURE SEEDER FOR E2E & INTEGRATION TESTS.
 * IT MUST NEVER BE EXECUTED ON PRODUCTION DATABASES OR exposed TO THE PUBLIC WEB.
 * 
 * SECURITY RESTRICTIONS ENFORCED AT RUNTIME:
 * 1. APP_ENV MUST BE SET TO 'testing' (getenv or config constant).
 * 2. EXECUTION IS RESTRICTED TO CLI (php_sapi_name() === 'cli') OR AUTHORIZED INTERNAL TEST REQUESTS.
 * 3. DIRECT BROWSER / PUBLIC WEB ACCESS RETURNS HTTP 403 FORBIDDEN.
 * ==============================================================================
 */

require_once __DIR__ . '/../app/bootstrap.php';

// Helper function for exiting safely when included vs CLI main script
if (!function_exists('terminateSeeder')) {
    function terminateSeeder($statusCode = 1) {
        if (basename($_SERVER['PHP_SELF'] ?? '') === 'seed_e2e_fixtures.php' || php_sapi_name() !== 'cli') {
            exit($statusCode);
        }
        return;
    }
}

// 1. Environment Scoping Check
$appEnv = getenv('APP_ENV') ?: (defined('APP_ENV') ? APP_ENV : 'production');
if ($appEnv !== 'testing') {
    if (php_sapi_name() !== 'cli' && !headers_sent()) {
        http_response_code(403);
    }
    echo json_encode([
        'error' => 'Forbidden: Database seeder is strictly restricted to testing environment.',
        'environment' => $appEnv
    ]);
    return;
}

// 2. CLI Execution Enforcement (Strict CLI-only access)
$isCLI = (php_sapi_name() === 'cli');

if (!$isCLI) {
    if (!headers_sent()) {
        http_response_code(403);
    }
    echo json_encode([
        'error' => 'Forbidden: Direct web access to database seeder is prohibited. Execution allowed via CLI only.'
    ]);
    return;
}

$pdo = getDBConnection();
if (!$pdo) {
    if (php_sapi_name() !== 'cli') {
        http_response_code(500);
    }
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Database connection failed']);
    exit(1);
}

// Fetch teacher russel ID
$stmtT = $pdo->prepare("SELECT id FROM users WHERE username = 'russel' LIMIT 1");
$stmtT->execute();
$teacherId = $stmtT->fetchColumn();

if (!$teacherId) {
    $hash = password_hash('Password123!', PASSWORD_DEFAULT);
    $pdo->prepare("INSERT INTO users (username, fullname, email, password, role) VALUES ('russel', 'Russel Gregorio', 'russel@gmail.com', ?, 'teacher')")->execute([$hash]);
    $teacherId = $pdo->lastInsertId();
}

// Clean previous E2E test lessons for deterministic run
$pdo->prepare("DELETE FROM lesson_materials WHERE teacher_id = ? AND file_name LIKE 'e2e_%'")->execute([$teacherId]);

$periods = ['general', 'prelim', 'midterm', 'finals'];
$createdIds = [];

foreach ($periods as $p) {
    $stmtIns = $pdo->prepare("
        INSERT INTO lesson_materials 
        (teacher_id, title, subject, lesson_text, processing_status, academic_period, semester, school_year, year_level, program, file_name, file_path, file_type, file_size) 
        VALUES (?, ?, 'Soil Mechanics', ?, 'completed', ?, '1st Semester', '2025-2026', '4th Year', 'BSCE', ?, ?, 'pdf', 2048)
    ");
    $title = "E2E " . ucfirst($p) . " Soil Mechanics Chapter";
    $text = "Comprehensive lecture content covering soil mechanics, effective stress, shear strength, and foundation design for " . ucfirst($p) . " exam preparation.";
    $fileName = "e2e_{$p}.pdf";
    $filePath = "uploads/{$fileName}";
    
    $stmtIns->execute([$teacherId, $title, $text, $p, $fileName, $filePath]);
    $createdIds[$p] = (int)$pdo->lastInsertId();
}

if (!headers_sent()) {
    header('Content-Type: application/json');
}
echo json_encode([
    'success' => true,
    'teacher_id' => (int)$teacherId,
    'lesson_ids' => $createdIds
]);
exit(0);
