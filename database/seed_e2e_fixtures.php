<?php
/**
 * ==============================================================================
 * QUESTBANK E2E TEST FIXTURE SEEDER (CLI ONLY)
 * ==============================================================================
 * Seeds deterministic users, roles, and reference data for Playwright E2E testing.
 * Strictly CLI execution required (php_sapi_name() === 'cli').
 * Does NOT directly insert lesson_materials, forcing browser upload workflows.
 * ==============================================================================
 */

require_once __DIR__ . '/../app/bootstrap.php';

// 1. Environment Scoping Check
$appEnv = getenv('APP_ENV') ?: 'testing';
if ($appEnv !== 'testing') {
    if (php_sapi_name() !== 'cli' && !headers_sent()) {
        http_response_code(403);
    }
    echo json_encode([
        'error' => 'Forbidden: Database seeder is strictly restricted to testing environment.',
        'environment' => $appEnv
    ]);
    exit(1);
}

// 2. CLI Execution Enforcement
if (php_sapi_name() !== 'cli') {
    if (!headers_sent()) {
        http_response_code(403);
    }
    echo json_encode([
        'error' => 'Forbidden: Direct web access to database seeder is prohibited. Execution allowed via CLI only.'
    ]);
    exit(1);
}

$pdo = getDBConnection();
if (!$pdo) {
    echo json_encode(['error' => 'Database connection failed']);
    exit(1);
}

$hash = password_hash('Password123!', PASSWORD_DEFAULT);

// Seed / Update Teacher A (russel)
$stmtA = $pdo->prepare("SELECT id FROM users WHERE username = 'russel' LIMIT 1");
$stmtA->execute();
$teacherAId = $stmtA->fetchColumn();

if (!$teacherAId) {
    $pdo->prepare("INSERT INTO users (username, fullname, email, password, role) VALUES ('russel', 'Russel Gregorio', 'russel@questbank.edu.ph', ?, 'teacher')")->execute([$hash]);
    $teacherAId = $pdo->lastInsertId();
} else {
    $pdo->prepare("UPDATE users SET password = ?, email = 'russel@questbank.edu.ph', role = 'teacher' WHERE id = ?")->execute([$hash, $teacherAId]);
}

// Seed / Update Teacher B (prof_smith)
$stmtB = $pdo->prepare("SELECT id FROM users WHERE username = 'prof_smith' LIMIT 1");
$stmtB->execute();
$teacherBId = $stmtB->fetchColumn();

if (!$teacherBId) {
    $pdo->prepare("INSERT INTO users (username, fullname, email, password, role) VALUES ('prof_smith', 'Professor Smith', 'smith@questbank.edu.ph', ?, 'teacher')")->execute([$hash]);
    $teacherBId = $pdo->lastInsertId();
} else {
    $pdo->prepare("UPDATE users SET password = ?, email = 'smith@questbank.edu.ph', role = 'teacher' WHERE id = ?")->execute([$hash, $teacherBId]);
}

echo json_encode([
    'success' => true,
    'teacher_a_id' => (int)$teacherAId,
    'teacher_b_id' => (int)$teacherBId
]);

if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === realpath(__FILE__)) {
    exit(0);
}
return;
