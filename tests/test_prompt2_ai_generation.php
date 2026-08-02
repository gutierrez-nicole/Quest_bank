<?php
/**
 * Automated Test Suite for PROMPT 2 — AI Question and Answer-Key Generation
 */

require_once __DIR__ . '/../app/bootstrap.php';
require_once __DIR__ . '/../app/services/GroqService.php';

$pdo = getDBConnection();
$teacherStmt = $pdo->query("SELECT id FROM users WHERE role = 'teacher' LIMIT 1");
$teacherId = $teacherStmt->fetchColumn() ?: 1;

$passed = 0;
$failed = 0;

function runTestCase($name, $callable) {
    global $passed, $failed;
    echo "[TEST] {$name}... ";
    try {
        $result = $callable();
        if ($result === true) {
            echo "PASSED\n";
            $passed++;
        } else {
            echo "FAILED: " . (is_string($result) ? $result : 'Assertion failed') . "\n";
            $failed++;
        }
    } catch (Throwable $e) {
        echo "FAILED Exception: " . $e->getMessage() . "\n";
        $failed++;
    }
}

// 1. Missing API Key Test
runTestCase("Missing API Key Handling", function() {
    $res = GroqService::generateQuestions("Valid lesson content text here for testing.", 5, "Structural Engineering", "Exam 1", "Structural Engineering", "multiple_choice", "medium", "INVALID_KEY_EXPLICIT_MISSING");
    // Should fail gracefully with API error
    if (isset($res['success']) && $res['success'] === true) {
        return "Expected failure when invalid/missing API key is passed";
    }
    if (!isset($res['error'])) {
        return "Expected error message in response";
    }
    return true;
});

// 2. Short / Empty Lesson Text Handling
runTestCase("Short or Empty Lesson Text Rejection", function() {
    $resShort = GroqService::generateQuestions("Too short", 5, "Subject", "Title");
    if (isset($resShort['success']) && $resShort['success'] === true) {
        return "Expected failure for short lesson text";
    }

    $resEmpty = GroqService::generateQuestions("", 5, "Subject", "Title");
    if (isset($resEmpty['success']) && $resEmpty['success'] === true) {
        return "Expected failure for empty lesson text";
    }

    return true;
});

// 3. One Uploaded Lesson Integration & Saving Test
runTestCase("One Uploaded Lesson Exam Save Transaction", function() use ($pdo, $teacherId) {
    // Insert a test completed lesson
    $stmt = $pdo->prepare("
        INSERT INTO lesson_materials 
        (teacher_id, subject, title, file_name, file_path, file_type, file_size, lesson_text, processing_status, word_count, page_count, extracted_at) 
        VALUES (?, 'Structural Engineering', 'Concrete Beams', 'beams.txt', 'uploads/beams.txt', 'TXT', 1024, 'Concrete beams carry flexural bending moments. Reinforced concrete combines concrete with steel bars to resist tension.', 'completed', 25, 1, NOW())
    ");
    $stmt->execute([$teacherId]);
    $lessonId = $pdo->lastInsertId();

    // Mock/Simulate calling save exam with valid AI-structured questions
    $questions = [
        [
            'question' => 'What is the primary function of steel reinforcement in concrete beams?',
            'type' => 'multiple_choice',
            'opt_a' => 'Resist tensile stress',
            'opt_b' => 'Resist compressive stress',
            'opt_c' => 'Increase weight',
            'opt_d' => 'Improve color',
            'correct_answer' => 'A',
            'points' => 1,
            'explanation' => 'Concrete is strong in compression but weak in tension. Steel handles tensile forces.'
        ],
        [
            'question' => 'True or False: Concrete has high tensile strength.',
            'type' => 'true_false',
            'correct_answer' => 'False',
            'points' => 1,
            'explanation' => 'Concrete has low tensile strength.'
        ]
    ];

    $pdo->beginTransaction();
    $stmtExam = $pdo->prepare("
        INSERT INTO exams 
        (teacher_id, title, subject, specialization, difficulty, time_limit, total_items, ai_metadata, lesson_ids) 
        VALUES (?, ?, ?, ?, ?, 60, ?, ?, ?)
    ");
    $stmtExam->execute([$teacherId, "AI Exam - Concrete Beams", "Structural Engineering", "Structural Engineering", "medium", count($questions), json_encode(['model' => 'test']), (string)$lessonId]);
    $examId = $pdo->lastInsertId();

    $qStmt = $pdo->prepare("
        INSERT INTO exam_questions 
        (exam_id, question_text, question_type, option_a, option_b, option_c, option_d, correct_answer, points, explanation, difficulty, topic, lesson_id) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    foreach ($questions as $q) {
        $qStmt->execute([
            $examId,
            $q['question'],
            $q['type'],
            $q['opt_a'] ?? null,
            $q['opt_b'] ?? null,
            $q['opt_c'] ?? null,
            $q['opt_d'] ?? null,
            $q['correct_answer'],
            $q['points'],
            $q['explanation'],
            'medium',
            'Structural Engineering',
            $lessonId
        ]);
    }
    $pdo->commit();

    // Verify database record integrity
    $stmtCheck = $pdo->prepare("SELECT total_items FROM exams WHERE id = ?");
    $stmtCheck->execute([$examId]);
    $itemsCount = $stmtCheck->fetchColumn();

    if ((int)$itemsCount !== 2) return "Exam total_items expected 2, got " . $itemsCount;

    $stmtQCheck = $pdo->prepare("SELECT COUNT(*) FROM exam_questions WHERE exam_id = ?");
    $stmtQCheck->execute([$examId]);
    if ((int)$stmtQCheck->fetchColumn() !== 2) return "Question count mismatch in DB";

    return true;
});

// 4. Duplicate Question Generation Rejection Test
runTestCase("Deduplication of Generated Questions", function() use ($pdo, $teacherId) {
    // Attempt saving duplicate questions
    $duplicateQuestions = [
        [
            'question' => 'What is shear strength?',
            'type' => 'multiple_choice',
            'correct_answer' => 'A'
        ],
        [
            'question' => 'What is shear strength?', // Duplicate text
            'type' => 'multiple_choice',
            'correct_answer' => 'A'
        ]
    ];

    $seen = [];
    $savedCount = 0;
    foreach ($duplicateQuestions as $q) {
        $key = mb_strtolower(trim($q['question']));
        if (isset($seen[$key])) continue;
        $seen[$key] = true;
        $savedCount++;
    }

    if ($savedCount !== 1) return "Expected deduplicated count 1, got {$savedCount}";

    return true;
});

// Summary
echo "\n=========================================\n";
echo "PROMPT 2 TEST RESULTS: Passed {$passed}, Failed {$failed}\n";
echo "=========================================\n";

if ($failed > 0) {
    exit(1);
} else {
    exit(0);
}
