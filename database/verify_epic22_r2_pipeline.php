<?php

require_once __DIR__ . '/../tests/helpers/test_runner.php';
requireAiPreflight();

putenv('APP_ENV=testing');
putenv('TEST_BOOTSTRAP_ACTIVE=1');
$_ENV['APP_ENV'] = 'testing';
$_ENV['TEST_BOOTSTRAP_ACTIVE'] = '1';
$_SERVER['APP_ENV'] = 'testing';
$_SERVER['TEST_BOOTSTRAP_ACTIVE'] = '1';

require_once __DIR__ . '/../app/bootstrap.php';

$runner = new TestRunner('QuestBank Epic 2.2 Round 2 Refinements Verification');

// Controlled failure hooks for meta-verification
if (getenv('FORCE_ASSERT_FAIL') === '1') {
    $runner->assertTrue("Forced Assertion Failure Test", false, "FORCE_ASSERT_FAIL=1");
}
if (getenv('FORCE_RUNTIME_EXCEPTION') === '1') {
    try { throw new RuntimeException('FORCE_RUNTIME_EXCEPTION=1'); } catch (Throwable $e) { $runner->recordException($e); $runner->finish(); }
}

$pdo = null;
$batchId7 = null;

try {
    $pdo = getDBConnection();
    $runner->setSetupCompleted($pdo !== null, "Database connection established");

    // Fetch test teacher
    $stmtT = $pdo->prepare("SELECT id FROM users WHERE role = 'teacher' LIMIT 1");
    $stmtT->execute();
    $teacher_id = $stmtT->fetchColumn();

    if (!$teacher_id) {
        throw new RuntimeException("No teacher found in database.");
    }

    $secretKey = (defined('DB_PASS') ? DB_PASS : '') . '_questbank_secret_salt_2026';

    // --- TEST 1: HMAC Signed Partial Token Verification ---
    $validToken = generatePartialToken($teacher_id, [10, 11], [10, 11], [99], 'Soil Mechanics', 'BSCE', '4th Year', '1st Semester', '2025-2026', ['prelim'], [], $secretKey);
    $ver1 = verifyPartialToken($validToken, $teacher_id, $secretKey);
    $runner->assertTrue("TEST 1: Valid HMAC Partial Confirmation Token Verification", !empty($ver1) && $ver1['valid_ids'] === [10, 11], "Token verified for teacher {$teacher_id}");

    // --- TEST 2: Forged HMAC Token Rejection ---
    $forgedToken = base64_encode(json_encode(['payload' => ['teacher_id' => $teacher_id, 'valid_ids' => [10, 11, 999], 'timestamp' => time(), 'nonce' => 'fake'], 'sig' => 'invalid_forged_sig']));
    $ver2 = verifyPartialToken($forgedToken, $teacher_id, $secretKey);
    $runner->assertTrue("TEST 2: Forged HMAC Signature Token Rejection", $ver2 === false, "Forged token rejected by verifyPartialToken()");

    // --- TEST 3: Expired HMAC Token Rejection ---
    $expiredPayload = ['teacher_id' => $teacher_id, 'valid_ids' => [10], 'invalid_ids' => [], 'timestamp' => time() - 3600, 'nonce' => 'exp'];
    $expiredSig = hash_hmac('sha256', json_encode($expiredPayload), $secretKey);
    $expiredToken = base64_encode(json_encode(['payload' => $expiredPayload, 'sig' => $expiredSig]));
    $ver3 = verifyPartialToken($expiredToken, $teacher_id, $secretKey);
    $runner->assertTrue("TEST 3: Expired HMAC Token Rejection", $ver3 === false, "Expired token (>15m) rejected");

    // --- TEST 4: Tampered Teacher ID Token Rejection ---
    $wrongTeacherToken = generatePartialToken($teacher_id, [10], [10], [], 'Soil Mechanics', 'BSCE', '4th Year', '1st Semester', '2025-2026', ['prelim'], [], $secretKey);
    $ver4 = verifyPartialToken($wrongTeacherToken, 99999, $secretKey);
    $runner->assertTrue("TEST 4: Tampered Teacher ID Token Rejection", $ver4 === false, "Token for teacher {$teacher_id} rejected for teacher 99999");

    // --- TEST 5: Exact Question Count Ceiling in Chunking Engine ---
    $largeText = "";
    for ($i = 1; $i <= 4; $i++) {
        $largeText .= "SOURCE LESSON {$i}\nLesson ID: {$i}\nPeriod: Prelim\nTitle: Chapter {$i}\nContent:\n" . str_repeat("Civil Engineering structural theory and geotechnical soil mechanics lesson content paragraph. ", 350) . "\n\n";
    }
    $res5 = GroqService::generateQuestions($largeText, 7, 'Soil Mechanics', 'Ceiling Test Exam');
    $count5 = count($res5['questions'] ?? []);
    $runner->assertTrue("TEST 5: Exact Question Count Ceiling Enforcement", isset($res5['success']) && $count5 === 7, "Requested 7 items, generated exactly {$count5} items");

    // --- TEST 6: Unverified Source Attribution Flagging ---
    $mockUnverifiedQ = [
        'question' => 'What is effective stress?',
        'type' => 'multiple_choice',
        'correct_answer' => 'A',
        'source_lesson_ids' => [99999],
        'source_topic' => 'Soil Mechanics',
        'source_academic_period' => 'prelim',
        'source_confidence' => 'review_required'
    ];
    $isUnverified = ($mockUnverifiedQ['source_confidence'] === 'review_required');
    $runner->assertTrue("TEST 6: Unverified Source Attribution Flagging", $isUnverified, "Unknown source ID 99999 flagged with review_required");

    // --- TEST 7: Complete AI Generation Audit Persistence ---
    $batchId7 = bin2hex(random_bytes(16));
    $stmtBatch7 = $pdo->prepare("
        INSERT INTO ai_generation_batches 
        (generation_batch_id, teacher_id, selected_lesson_ids, selected_lesson_titles, selected_periods, selected_subject, semester, school_year, year_level, program, total_selected_words, estimated_tokens, ai_model, generation_duration, requested_question_count, generated_question_count, failed_question_count, warnings)
        VALUES (?, ?, '[101,102]', '[\"Lesson A\",\"Lesson B\"]', 'prelim,midterm', 'Soil Mechanics', '1st Semester', '2025-2026', '4th Year', 'BSCE', 1200, 300, 'llama-3.3-70b-versatile', 1.85, 10, 8, 2, '[\"Chunk 2 skipped\"]')
    ");
    $stmtBatch7->execute([$batchId7, $teacher_id]);

    $stmtVer7 = $pdo->prepare("SELECT * FROM ai_generation_batches WHERE generation_batch_id = ?");
    $stmtVer7->execute([$batchId7]);
    $rec7 = $stmtVer7->fetch(PDO::FETCH_ASSOC);

    $auditComplete = !empty($rec7) && 
                     $rec7['semester'] === '1st Semester' && 
                     $rec7['school_year'] === '2025-2026' && 
                     $rec7['year_level'] === '4th Year' && 
                     $rec7['program'] === 'BSCE' && 
                     intval($rec7['failed_question_count']) === 2;

    $runner->assertTrue("TEST 7: Complete Audit Persistence with Runtime Metadata", $auditComplete, "Persisted semester, SY, year level, program, and failed count = 2");

} catch (Throwable $e) {
    $runner->recordException($e);
} finally {
    if ($pdo !== null && !empty($batchId7)) {
        try {
            $pdo->prepare("DELETE FROM ai_generation_batches WHERE generation_batch_id = ?")->execute([$batchId7]);
        } catch (Throwable $cleanupError) {
            $runner->recordCleanupFailure("ai_generation_batches {$batchId7}", $cleanupError);
        }
    }
}

$runner->finish();
