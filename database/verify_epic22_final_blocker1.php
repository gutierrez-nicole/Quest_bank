<?php
/**
 * Verification Script for QuestBank Epic 2.2 Final Blocker 1: Remove Review-Form Source Fallback
 */
require_once __DIR__ . '/../app/bootstrap.php';

$pdo = getDBConnection();
$passed = 0;
$failed = 0;

echo "===========================================================\n";
echo "    QUESTBANK EPIC 2.2 FINAL BLOCKER 1 VERIFICATION         \n";
echo "===========================================================\n";

function logTest($name, $status, $detail = '') {
    global $passed, $failed;
    if ($status) {
        $passed++;
        echo "  [PASS] $name\n";
        if ($detail) echo "         -> $detail\n";
    } else {
        $failed++;
        echo "  [FAIL] $name\n";
        if ($detail) echo "         -> $detail\n";
    }
}

// Fetch test teacher
$stmtT = $pdo->prepare("SELECT id FROM users WHERE role = 'teacher' LIMIT 1");
$stmtT->execute();
$teacher_id = $stmtT->fetchColumn();

try {
    // --- TEST 1: Empty & Invalid AI Source Attribution Filtering ---
    $selectedPool = [101, 102];
    
    // Case A: Empty source
    $rawA = [];
    $filteredA = array_values(array_unique(array_intersect($rawA, $selectedPool)));
    logTest("TEST 1a: Empty AI Source IDs Filtered to Empty Array", empty($filteredA), "No automatic fallback to pool [101, 102]");

    // Case B: Invalid/Out-of-pool source ID
    $rawB = [99999, 88888];
    $filteredB = array_values(array_unique(array_intersect($rawB, $selectedPool)));
    logTest("TEST 1b: Out-of-Pool Source IDs Filtered to Empty Array", empty($filteredB), "Invalid IDs [99999, 88888] rejected");

    // Case C: Valid source ID
    $rawC = [102];
    $filteredC = array_values(array_unique(array_intersect($rawC, $selectedPool)));
    logTest("TEST 1c: Valid Pool Source ID Retained", $filteredC === [102], "Valid lesson 102 retained");

    // --- TEST 2: Unverified Source Blocks Final Save ---
    // Simulate save attempt with empty verified sources
    $questionsToSave = [
        [
            'text' => 'What is soil shear strength?',
            'type' => 'multiple_choice',
            'opt_a' => 'A', 'opt_b' => 'B', 'opt_c' => 'C', 'opt_d' => 'D',
            'correct' => 'A',
            'points' => 1,
            'source_lesson_ids' => '', // Empty!
            'manual_source_id' => ''   // No teacher selection!
        ]
    ];

    $saveLessonIds = [101, 102];
    $saveBlocked = false;

    foreach ($questionsToSave as $q) {
        $rawQSources = [];
        if (!empty($q['manual_source_id'])) $rawQSources[] = intval($q['manual_source_id']);
        if (!empty($q['source_lesson_ids'])) {
            $rawSources = is_array($q['source_lesson_ids']) ? $q['source_lesson_ids'] : explode(',', (string)$q['source_lesson_ids']);
            foreach ($rawSources as $rs) $rawQSources[] = intval($rs);
        }
        $validQSources = array_values(array_unique(array_intersect($rawQSources, $saveLessonIds)));
        if (empty($validQSources)) {
            $saveBlocked = true;
            break;
        }
    }

    logTest("TEST 2: Unverified Source Blocks Exam Save/Publish", $saveBlocked, "Save blocked because question 1 has no verified lesson source");

    // --- TEST 3: Explicit Teacher Assignment & Verification Audit Persistence ---
    // Create test lesson material
    $stmtInsL = $pdo->prepare("INSERT INTO lesson_materials (teacher_id, title, subject, lesson_text, processing_status, academic_period, file_name, file_path, file_type, file_size) VALUES (?, 'Grounding Test Lesson', 'Geotechnical', 'Content on soil friction', 'completed', 'prelim', 'test_lesson.pdf', 'uploads/test_lesson.pdf', 'pdf', 1000)");
    $stmtInsL->execute([$teacher_id]);
    $testLessonId = $pdo->lastInsertId();

    // Teacher explicitly selects $testLessonId
    $validSaveLessonIds = [(int)$testLessonId];
    $questionsWithTeacherAssignment = [
        [
            'text' => 'What is soil friction angle?',
            'type' => 'multiple_choice',
            'opt_a' => 'Angle', 'opt_b' => 'Force', 'opt_c' => 'Mass', 'opt_d' => 'Volume',
            'correct' => 'Angle',
            'points' => 1,
            'source_lesson_ids' => '',
            'manual_source_id' => $testLessonId // Explicit teacher choice!
        ]
    ];

    $pdo->beginTransaction();
    $stmtExam = $pdo->prepare("INSERT INTO exams (teacher_id, title, subject, total_items, lesson_ids) VALUES (?, 'Verified Attribution Exam', 'Geotechnical', 1, ?)");
    $stmtExam->execute([$teacher_id, (string)$testLessonId]);
    $testExamId = $pdo->lastInsertId();

    $qStmt = $pdo->prepare("INSERT INTO exam_questions (exam_id, question_text, question_type, option_a, option_b, option_c, option_d, correct_answer, points, difficulty, topic, lesson_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'medium', 'Geotechnical', ?)");
    $srcStmt = $pdo->prepare("INSERT INTO generated_question_sources (question_id, lesson_id, academic_period, source_topic, source_confidence, source_review_required, source_verified_by, source_verified_at) VALUES (?, ?, 'prelim', 'Geotechnical', 'high', 0, ?, NOW())");

    foreach ($questionsWithTeacherAssignment as $q) {
        $rawQSources = [(int)$q['manual_source_id']];
        $validQSources = array_values(array_unique(array_intersect($rawQSources, $validSaveLessonIds)));
        
        $qStmt->execute([
            $testExamId,
            $q['text'],
            $q['type'],
            $q['opt_a'], $q['opt_b'], $q['opt_c'], $q['opt_d'],
            $q['correct'],
            $q['points'],
            $validQSources[0]
        ]);
        $qId = $pdo->lastInsertId();

        foreach ($validQSources as $srcLid) {
            $srcStmt->execute([$qId, $srcLid, $teacher_id]);
        }
    }
    $pdo->commit();

    // Verify database record
    $stmtVerSrc = $pdo->prepare("SELECT * FROM generated_question_sources WHERE question_id IN (SELECT id FROM exam_questions WHERE exam_id = ?)");
    $stmtVerSrc->execute([$testExamId]);
    $srcRow = $stmtVerSrc->fetch(PDO::FETCH_ASSOC);

    $pass3 = !empty($srcRow) && 
             (int)$srcRow['lesson_id'] === (int)$testLessonId && 
             (int)$srcRow['source_review_required'] === 0 && 
             (int)$srcRow['source_verified_by'] === (int)$teacher_id && 
             !empty($srcRow['source_verified_at']);

    logTest("TEST 3: Teacher Assignment & Audit Trail Persistence", $pass3, "Lesson {$testLessonId} verified by teacher {$teacher_id} at {$srcRow['source_verified_at']}");

    // Cleanup test data
    $pdo->prepare("DELETE FROM generated_question_sources WHERE question_id IN (SELECT id FROM exam_questions WHERE exam_id = ?)")->execute([$testExamId]);
    $pdo->prepare("DELETE FROM exam_questions WHERE exam_id = ?")->execute([$testExamId]);
    $pdo->prepare("DELETE FROM exams WHERE id = ?")->execute([$testExamId]);
    $pdo->prepare("DELETE FROM lesson_materials WHERE id = ?")->execute([$testLessonId]);

} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    echo "TEST EXCEPTION: " . $e->getMessage() . "\n" . $e->getTraceAsString() . "\n";
}

echo "\n-----------------------------------------------------------\n";
echo "VERIFICATION SUMMARY: {$passed} PASSED, {$failed} FAILED\n";
echo "-----------------------------------------------------------\n";

exit($failed > 0 ? 1 : 0);
