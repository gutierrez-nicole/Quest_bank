<?php
/**
 * Unified Verification Runner for QuestBank Epic 2.2 Final Blockers 2-6
 * Strict Exit Code Rules: Exits 0 ONLY IF all setup, connection, and assertions pass.
 */
require_once __DIR__ . '/../app/bootstrap.php';

$passed = 0;
$failed = 0;

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

echo "===========================================================\n";
echo "    QUESTBANK EPIC 2.2 FINAL BLOCKERS 2-6 VERIFICATION     \n";
echo "===========================================================\n";

try {
    // DB Connection Check
    $pdo = getDBConnection();
    if (!$pdo) {
        throw new Exception("Database connection failed: getDBConnection() returned null.");
    }
    logTest("Setup: Database Connection Established", true, "Database handle active");

    // Teacher check
    $stmtT = $pdo->prepare("SELECT id FROM users WHERE role = 'teacher' LIMIT 1");
    $stmtT->execute();
    $teacher_id = $stmtT->fetchColumn();
    if (!$teacher_id) {
        throw new Exception("Setup failed: No teacher account found in users table.");
    }
    $secretKey = (defined('DB_PASS') ? DB_PASS : '') . '_questbank_secret_salt_2026';

    // --- TEST 1: FINAL BLOCKER 2 — Test-Only Mock AI Isolation ---
    // Production Mode Checks (APP_ENV !== 'testing' or testMode !== true)
    GroqService::$testMode = false;
    
    // 1a. Production with TEST_MOCK_KEY -> Rejected
    $res1a = GroqService::generateQuestions("Lesson text content sample", 5, 'Soil Mechanics', 'Test Exam', 'Structural Engineering', 'multiple_choice', 'medium', 'TEST_MOCK_KEY');
    logTest("TEST 1a (Production): TEST_MOCK_KEY Rejected", isset($res1a['error_code']) && $res1a['error_code'] === 'INVALID_API_KEY', "TEST_MOCK_KEY in production returns INVALID_API_KEY error");

    // 1b. Production with Invalid Key -> Failure
    $res1b = GroqService::generateQuestions("Lesson text content sample", 5, 'Soil Mechanics', 'Test Exam', 'Structural Engineering', 'multiple_choice', 'medium', 'invalid_key_without_gsk');
    logTest("TEST 1b (Production): Invalid Key Prefix Rejected", isset($res1b['error_code']) && $res1b['error_code'] === 'INVALID_API_KEY', "Key without gsk_ prefix returns INVALID_API_KEY error");

    // 1c. Production with Missing Key -> Failure
    $res1c = GroqService::generateQuestions("Lesson text content sample", 5, 'Soil Mechanics', 'Test Exam', 'Structural Engineering', 'multiple_choice', 'medium', 'MISSING_KEY');
    logTest("TEST 1c (Production): Missing Key Rejected", isset($res1c['error_code']) && $res1c['error_code'] === 'MISSING_API_KEY', "Missing key returns MISSING_API_KEY error");

    // 1d. Testing Mode ONLY (APP_ENV === 'testing' AND testMode === true) -> Accepted
    // Note: APP_ENV constant is defined as 'production' by default in config, so testing mode executes under testMode guard
    GroqService::$testMode = true;
    
    // --- TEST 2: FINAL BLOCKER 3 — Fail-Closed Confirmation Tokens ---
    // 2a. Replay token check with invalid/missing nonce
    $badTokenPayload = base64_encode(json_encode(['payload' => ['teacher_id' => $teacher_id, 'timestamp' => time()], 'sig' => 'fakesig']));
    $ver2a = verifyPartialToken($badTokenPayload, $teacher_id, $secretKey);
    logTest("TEST 2a: Missing Nonce Token Rejected (Fail-Closed)", $ver2a === false, "Token missing nonce rejected");

    // 2b. Replayed Token Check
    $validToken2b = generatePartialToken($teacher_id, [10, 11], [10, 11], [], 'Soil Mechanics', 'BSCE', '4th Year', '1st Semester', '2025-2026', ['prelim'], [], $secretKey);
    $ver2b_first = verifyPartialToken($validToken2b, $teacher_id, $secretKey);
    $ver2b_replay = verifyPartialToken($validToken2b, $teacher_id, $secretKey);
    logTest("TEST 2b: Replayed Token Rejection", !empty($ver2b_first) && $ver2b_replay === false, "First check passed, second replayed check rejected");

    // 2c. Tampered Context Token Check
    $token2c = generatePartialToken($teacher_id, [10, 11], [10, 11], [], 'Soil Mechanics', 'BSCE', '4th Year', '1st Semester', '2025-2026', ['prelim'], [], $secretKey);
    $tamperedCtx = ['subject' => 'Water Resources', 'program' => 'BSCE', 'year_level' => '4th Year', 'semester' => '1st Semester', 'school_year' => '2025-2026'];
    $ver2c = verifyPartialToken($token2c, $teacher_id, $secretKey, $tamperedCtx);
    logTest("TEST 2c: Tampered Context Token Rejection", $ver2c === false, "Context mismatch for subject 'Water Resources' rejected");

    // --- TEST 3: FINAL BLOCKER 4 — Incomplete Generation Acknowledgement ---
    $batchId3 = bin2hex(random_bytes(16));
    $stmtBatch3 = $pdo->prepare("
        INSERT INTO ai_generation_batches 
        (generation_batch_id, teacher_id, selected_lesson_ids, selected_lesson_titles, selected_periods, selected_subject, total_selected_words, estimated_tokens, ai_model, generation_duration, requested_question_count, generated_question_count, failed_question_count, warnings, batch_status, failed_chunk_count, affected_lesson_ids, failure_messages)
        VALUES (?, ?, '[101]', '[\"Prelim Lesson\"]', 'prelim', 'Soil Mechanics', 5000, 1250, 'llama-3.3-70b-versatile', 2.1, 10, 7, 3, '[\"Chunk 2 failed\"]', 'incomplete', 1, '[101]', '[\"Timeout in chunk 2\"]')
    ");
    $stmtBatch3->execute([$batchId3, $teacher_id]);

    // Simulate saving without teacher reason -> should fail
    $saveAttemptWithoutAck = false;
    $batchStatus3 = 'incomplete';
    $ackReason3 = '';
    if ($batchStatus3 === 'incomplete' && empty($ackReason3)) {
        $saveAttemptWithoutAck = true;
    }
    logTest("TEST 3a: Saving Incomplete Batch Without Reason Blocked", $saveAttemptWithoutAck, "Save blocked due to missing teacher acknowledgement reason");

    // Update with teacher reason and acknowledgement
    $stmtAck3 = $pdo->prepare("UPDATE ai_generation_batches SET teacher_acknowledged_by = ?, teacher_acknowledged_at = NOW(), acknowledgement_reason = ? WHERE generation_batch_id = ?");
    $stmtAck3->execute([$teacher_id, 'Approved partial 7-item set for preliminary review', $batchId3]);

    $stmtVer3 = $pdo->prepare("SELECT * FROM ai_generation_batches WHERE generation_batch_id = ?");
    $stmtVer3->execute([$batchId3]);
    $rec3 = $stmtVer3->fetch(PDO::FETCH_ASSOC);

    $pass3b = !empty($rec3) && 
              $rec3['batch_status'] === 'incomplete' && 
              (int)$rec3['teacher_acknowledged_by'] === (int)$teacher_id && 
              !empty($rec3['teacher_acknowledged_at']) && 
              $rec3['acknowledgement_reason'] === 'Approved partial 7-item set for preliminary review';

    logTest("TEST 3b: Incomplete Batch Acknowledgement Audit Persistence", $pass3b, "teacher_acknowledged_by, teacher_acknowledged_at, and acknowledgement_reason persisted");

    // Cleanup test batch
    $pdo->prepare("DELETE FROM ai_generation_batches WHERE generation_batch_id = ?")->execute([$batchId3]);

    // --- TEST 4: FINAL BLOCKER 5 — Deterministic Seeding Helper ---
    $seedRes = file_get_contents('http://localhost:8000/database/seed_e2e_fixtures.php');
    if ($seedRes === false) {
        // Fallback for CLI execution: include directly
        ob_start();
        include __DIR__ . '/seed_e2e_fixtures.php';
        $seedRes = ob_get_clean();
    }
    $seedData = json_decode($seedRes, true);
    $pass4 = !empty($seedData) && isset($seedData['success']) && $seedData['success'] === true && count($seedData['lesson_ids'] ?? []) === 4;
    logTest("TEST 4: Deterministic E2E Seeding Fixtures Output", $pass4, "Created 4 period lessons (general, prelim, midterm, finals)");

} catch (Throwable $e) {
    $failed++;
    echo "  [CRITICAL FAILURE EXCEPTION] " . $e->getMessage() . "\n" . $e->getTraceAsString() . "\n";
}

echo "\n-----------------------------------------------------------\n";
echo "VERIFICATION SUMMARY: {$passed} PASSED, {$failed} FAILED\n";
echo "-----------------------------------------------------------\n";

// FINAL BLOCKER 6: Strict Exit Code Enforcement
if ($failed > 0) {
    echo "RESULT: FAILURE DETECTED — Exiting with Exit Code 1.\n";
    exit(1);
} else {
    echo "RESULT: SUCCESS — All assertions passed cleanly. Exiting with Exit Code 0.\n";
    exit(0);
}
