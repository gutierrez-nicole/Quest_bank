<?php
/**
 * Deterministic Database & Lesson Seeder for E2E Tests
 */
require_once __DIR__ . '/../app/bootstrap.php';

$pdo = getDBConnection();

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

header('Content-Type: application/json');
echo json_encode([
    'success' => true,
    'teacher_id' => (int)$teacherId,
    'lesson_ids' => $createdIds
]);
