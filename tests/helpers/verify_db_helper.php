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
        'questions' => $questions,
        'sources_count' => count($sources),
        'sources' => $sources
    ]);
    exit(0);
}

if ($action === 'get_uploaded_lessons') {
    $identifier = $argv[2] ?? '';
    if (numeric_check($identifier)) {
        $stmt = $pdo->prepare("SELECT id, title, academic_period, lesson_text, processing_status, subject, teacher_id FROM lesson_materials WHERE teacher_id = ? ORDER BY id DESC");
        $stmt->execute([intval($identifier)]);
    } else {
        $stmt = $pdo->prepare("
            SELECT lm.id, lm.title, lm.academic_period, lm.lesson_text, lm.processing_status, lm.subject, lm.teacher_id 
            FROM lesson_materials lm
            JOIN users u ON lm.teacher_id = u.id
            WHERE u.username = ? OR u.email = ?
            ORDER BY lm.id DESC
        ");
        $stmt->execute([$identifier, $identifier]);
    }
    
    $lessons = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode(['success' => true, 'lessons' => $lessons]);
    exit(0);
}

if ($action === 'get_teacher_id') {
    $username = $argv[2] ?? 'russel';
    $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
    $stmt->execute([$username, $username]);
    $tid = $stmt->fetchColumn();
    echo json_encode(['success' => true, 'teacher_id' => intval($tid)]);
    exit(0);
}

function numeric_check($val) {
    return is_numeric($val);
}

echo json_encode(['error' => 'Unknown action']);
exit(1);
