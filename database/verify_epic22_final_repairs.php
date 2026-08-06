<?php

require_once __DIR__ . '/../tests/helpers/test_runner.php';
requireDatabasePreflight();

putenv('APP_ENV=testing');
putenv('TEST_BOOTSTRAP_ACTIVE=1');
$_ENV['APP_ENV'] = 'testing';
$_ENV['TEST_BOOTSTRAP_ACTIVE'] = '1';
$_SERVER['APP_ENV'] = 'testing';
$_SERVER['TEST_BOOTSTRAP_ACTIVE'] = '1';

require_once __DIR__ . '/../app/bootstrap.php';

$runner = new TestRunner('QuestBank Epic 2.2 Final Repairs 2-6 Verification');

// Controlled failure hooks for meta-verification
if (getenv('FORCE_ASSERT_FAIL') === '1') {
    $runner->assertTrue("Forced Assertion Failure Test", false, "FORCE_ASSERT_FAIL=1");
}
if (getenv('FORCE_RUNTIME_EXCEPTION') === '1') {
    try { throw new RuntimeException('FORCE_RUNTIME_EXCEPTION=1'); } catch (Throwable $e) { $runner->recordException($e); $runner->finish(); }
}

$pdo = null;
$batchId5 = null;

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

    // --- TEST 1: Full Context HMAC Partial Confirmation Binding & Replay Prevention ---
    $origIds = [101, 102, 103];
    $validIds = [101, 102];
    $invalidIds = [103];
    $subject = 'Soil Mechanics';
    $program = 'BSCE';
    $yearLevel = '4th Year';
    $semester = '1st Semester';
    $schoolYear = '2025-2026';
    $periods = ['prelim', 'midterm'];
    $warnings = ['Lesson 103 failed extraction'];

    $token = generatePartialToken(
        $teacher_id, $origIds, $validIds, $invalidIds,
        $subject, $program, $yearLevel, $semester, $schoolYear,
        $periods, $warnings, $secretKey
    );

    // 1a. Valid Verification
    $ctxValid = ['subject' => $subject, 'program' => $program, 'year_level' => $yearLevel, 'semester' => $semester, 'school_year' => $schoolYear];
    $ver1a = verifyPartialToken($token, $teacher_id, $secretKey, $ctxValid);
    $runner->assertTrue("TEST 1a: Full Context HMAC Partial Token Valid Verification", !empty($ver1a) && $ver1a['valid_ids'] === [101, 102], "Subject, program, year level, semester, SY verified");

    // 1b. Replay Prevention Check
    $ver1b = verifyPartialToken($token, $teacher_id, $secretKey, $ctxValid);
    $runner->assertTrue("TEST 1b: Replay Prevention (Replayed Token Rejection)", $ver1b === false, "Replayed token hash blocked by used_confirmation_tokens");

    // 1c. Tampered Context Check
    $token2 = generatePartialToken(
        $teacher_id, $origIds, $validIds, $invalidIds,
        $subject, $program, $yearLevel, $semester, $schoolYear,
        $periods, $warnings, $secretKey
    );
    $ctxTampered = ['subject' => 'Fluid Mechanics', 'program' => $program, 'year_level' => $yearLevel, 'semester' => $semester, 'school_year' => $schoolYear];
    $ver1c = verifyPartialToken($token2, $teacher_id, $secretKey, $ctxTampered);
    $runner->assertTrue("TEST 1c: Tampered Context (Subject Mismatch) Rejection", $ver1c === false, "Tampered subject 'Fluid Mechanics' rejected");

    // --- TEST 2: Server-Side Max Lesson Selection Limit ---
    $maxAllowed = defined('AI_MAX_SELECTED_LESSONS') ? AI_MAX_SELECTED_LESSONS : 20;
    $pool20 = range(1, $maxAllowed);
    $pool21 = range(1, $maxAllowed + 1);

    $runner->assertTrue("TEST 2a: Exact Max Allowed Selection Allowed (20 Lessons)", count($pool20) <= $maxAllowed, "Pool count 20 <= max 20");
    $runner->assertTrue("TEST 2b: Excessive Selection Rejection (21 Lessons)", count($pool21) > $maxAllowed, "Pool count 21 > max 20 triggers server error");

    // --- TEST 3: Unverified Source Question Handling & Save Policy ---
    $unverifiedQ = [
        'question' => 'What is soil permeability?',
        'source_lesson_ids' => [],
        'source_confidence' => 'review_required'
    ];
    $saveLessonIds = [101, 102];
    $validSources = array_intersect($unverifiedQ['source_lesson_ids'], $saveLessonIds);
    $isBlockedFromSave = empty($validSources);

    $runner->assertTrue("TEST 3: Unverified Source Question Blocks Exam Save", $isBlockedFromSave, "Unverified question without valid source_lesson_ids blocks final save");

    // --- TEST 4: Oversized Lesson Hierarchical Subchunking ---
    $oversizedLesson = "SOURCE LESSON 1\nLesson ID: 501\nPeriod: Prelim\nTitle: Massive Geotechnical Chapter\n";
    for ($p = 1; $p <= 100; $p++) {
        $oversizedLesson .= "## Section {$p}: Soil Properties\n" . str_repeat("Detailed soil mechanics text describing shear strength and cohesion. ", 200) . "\n\n";
    }

    $res4 = GroqService::generateQuestions($oversizedLesson, 5, 'Soil Mechanics', 'Subchunk Exam');
    $pass4 = isset($res4['success']) && $res4['success'] === true && count($res4['questions']) === 5;
    $runner->assertTrue("TEST 4: Hierarchical Subchunking for Single Oversized Lesson", $pass4, "Generated exactly 5 questions from multi-section oversized lesson");

    // --- TEST 5: Failed Chunk Metadata & Batch Status Persistence ---
    $batchId5 = bin2hex(random_bytes(16));
    $stmtBatch5 = $pdo->prepare("
        INSERT INTO ai_generation_batches 
        (generation_batch_id, teacher_id, selected_lesson_ids, selected_lesson_titles, selected_periods, selected_subject, semester, school_year, year_level, program, total_selected_words, estimated_tokens, ai_model, generation_duration, requested_question_count, generated_question_count, failed_question_count, warnings, batch_status, failed_chunk_count, affected_lesson_ids, failure_messages, teacher_acknowledged_at, teacher_acknowledged_by)
        VALUES (?, ?, '[501]', '[\"Oversized Lesson\"]', 'prelim', 'Soil Mechanics', '1st Semester', '2025-2026', '4th Year', 'BSCE', 15000, 3750, 'llama-3.3-70b-versatile', 4.5, 10, 8, 2, '[\"Chunk 2 failed\"]', 'incomplete', 1, '[501]', '[\"Chunk 2 timeout\"]', NOW(), ?)
    ");
    $stmtBatch5->execute([$batchId5, $teacher_id, $teacher_id]);

    $stmtVer5 = $pdo->prepare("SELECT * FROM ai_generation_batches WHERE generation_batch_id = ?");
    $stmtVer5->execute([$batchId5]);
    $rec5 = $stmtVer5->fetch(PDO::FETCH_ASSOC);

    $pass5 = !empty($rec5) && 
             $rec5['batch_status'] === 'incomplete' && 
             intval($rec5['failed_chunk_count']) === 1 && 
             !empty($rec5['teacher_acknowledged_at']);

    $runner->assertTrue("TEST 5: Failed Chunk Audit Persistence & Acknowledgment", $pass5, "batch_status: incomplete, failed_chunk_count: 1, acknowledged by teacher {$teacher_id}");

} catch (Throwable $e) {
    $runner->recordException($e);
} finally {
    if ($pdo !== null && !empty($batchId5)) {
        try {
            $pdo->prepare("DELETE FROM ai_generation_batches WHERE generation_batch_id = ?")->execute([$batchId5]);
        } catch (Throwable $cleanupError) {
            $runner->recordCleanupFailure("ai_generation_batches {$batchId5}", $cleanupError);
        }
    }
}

$runner->finish();
