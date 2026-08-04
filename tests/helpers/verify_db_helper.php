<?php
/**
 * Test Helper for Playwright E2E DB Verification
 */
putenv('APP_ENV=testing');
$_ENV['APP_ENV'] = 'testing';

require_once __DIR__ . '/../../app/bootstrap.php';

$action = $argv[1] ?? '';

$pdo = getDBConnection();
if (!$pdo) {
    echo json_encode(['error' => 'Database connection failed']);
    exit(1);
}

if ($action === 'seed') {
    // Run CLI seeder
    require_once __DIR__ . '/../../database/seed_e2e_fixtures.php';
    exit(0);
}

if ($action === 'verify_exam_saved') {
    $batchId = $argv[2] ?? '';
    
    $stmtBatch = $pdo->prepare("SELECT * FROM ai_generation_batches WHERE generation_batch_id = ?");
    $stmtBatch->execute([$batchId]);
    $batch = $stmtBatch->fetch(PDO::FETCH_ASSOC);

    if (!$batch || empty($batch['saved_exam_id'])) {
        echo json_encode(['success' => false, 'error' => 'Batch not found or not consumed']);
        exit(1);
    }

    $examId = $batch['saved_exam_id'];
    $stmtExam = $pdo->prepare("SELECT * FROM exams WHERE id = ?");
    $stmtExam->execute([$examId]);
    $exam = $stmtExam->fetch(PDO::FETCH_ASSOC);

    $stmtQuestions = $pdo->prepare("SELECT * FROM exam_questions WHERE exam_id = ?");
    $stmtQuestions->execute([$examId]);
    $questions = $stmtQuestions->fetchAll(PDO::FETCH_ASSOC);

    $stmtSources = $pdo->prepare("
        SELECT gqs.* 
        FROM generated_question_sources gqs 
        JOIN exam_questions eq ON gqs.question_id = eq.id 
        WHERE eq.exam_id = ?
    ");
    $stmtSources->execute([$examId]);
    $sources = $stmtSources->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'batch' => $batch,
        'exam' => $exam,
        'questions_count' => count($questions),
        'sources_count' => count($sources),
        'sources' => $sources
    ]);
    exit(0);
}

if ($action === 'get_uploaded_lessons') {
    $teacherId = intval($argv[2] ?? 10);
    $stmt = $pdo->prepare("SELECT id, title, academic_period, extracted_text FROM lesson_materials WHERE teacher_id = ? ORDER BY id DESC");
    $stmt->execute([$teacherId]);
    $lessons = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode(['success' => true, 'lessons' => $lessons]);
    exit(0);
}

echo json_encode(['error' => 'Unknown action']);
exit(1);
