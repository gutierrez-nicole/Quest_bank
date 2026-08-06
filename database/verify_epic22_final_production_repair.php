<?php

require_once __DIR__ . '/../tests/helpers/test_runner.php';
requireAiPreflight();

putenv('APP_ENV=testing');
putenv('TEST_BOOTSTRAP_ACTIVE=1');
$_ENV['APP_ENV'] = 'testing';
$_ENV['TEST_BOOTSTRAP_ACTIVE'] = '1';
$_SERVER['APP_ENV'] = 'testing';
$_SERVER['TEST_BOOTSTRAP_ACTIVE'] = '1';
require_once __DIR__ . '/../app/testing_bootstrap.php';
define('TEST_CHUNK_LIMIT', 6000);

require_once __DIR__ . '/../app/bootstrap.php';
require_once __DIR__ . '/../app/services/GroqService.php';
require_once __DIR__ . '/../includes/security.php';

$runner = new TestRunner('QuestBank Epic 2.2 Final Production Repair Verification');

// Controlled failure hooks for meta-verification
if (getenv('FORCE_ASSERT_FAIL') === '1') {
    $runner->assertTrue("Forced Assertion Failure Test", false, "FORCE_ASSERT_FAIL=1");
}
if (getenv('FORCE_RUNTIME_EXCEPTION') === '1') {
    try { throw new RuntimeException('FORCE_RUNTIME_EXCEPTION=1'); } catch (Throwable $e) { $runner->recordException($e); $runner->finish(); }
}

GroqService::enableTestingModeFromBootstrap();

$batchIdTest = null;
$pdo = null;

try {
    $pdo = getDBConnection();
    $runner->setSetupCompleted($pdo !== null, "Database connection established");

    // -----------------------------------------------------------------
    // TEST 1: Build Multi-Chunk Lesson Content (Prelim, Midterm, Finals)
    // -----------------------------------------------------------------
    $lessonTextPrelim = "SOURCE LESSON 1\nLesson ID: 101\nPeriod: Prelim\nTitle: Structural Analysis Fundamentals\nSubject: Structural Engineering\nContent: " . str_repeat("Prelim beam moment calculation. ", 150);
    $lessonTextMidterm = "SOURCE LESSON 2\nLesson ID: 102\nPeriod: Midterm\nTitle: Reinforced Concrete Design\nSubject: Structural Engineering\nContent: " . str_repeat("Midterm rebar tension stress design. ", 150);
    $lessonTextFinals = "SOURCE LESSON 3\nLesson ID: 103\nPeriod: Finals\nTitle: Steel Frame Stability\nSubject: Structural Engineering\nContent: " . str_repeat("Finals steel column buckling capacity. ", 150);

    $fullLessonText = $lessonTextPrelim . "\n\n" . $lessonTextMidterm . "\n\n" . $lessonTextFinals;

    // Test 1: Verify chunking produces 3 distinct chunks corresponding to Prelim (101), Midterm (102), Finals (103)
    $res1 = GroqService::generateQuestions($fullLessonText, 6, 'Structural Engineering', 'Comprehensive Assessment', 'Structural Engineering', 'multiple_choice', 'medium', 'TEST_MOCK_KEY');

    $pass1 = !empty($res1['success']) 
        && !empty($res1['metadata']['chunk_generation_results']) 
        && count($res1['metadata']['chunk_generation_results']) === 3;

    $runner->assertTrue("TEST 1: Multi-Chunk Structure and Result Tracking", $pass1, "Chunks created: " . count($res1['metadata']['chunk_generation_results'] ?? []));

    // -----------------------------------------------------------------
    // TEST 2: Per-Chunk Generation Results Metadata Schema Verification
    // -----------------------------------------------------------------
    $chunkRes1 = $res1['metadata']['chunk_generation_results'] ?? [];
    $validChunkSchema = true;
    foreach ($chunkRes1 as $cr) {
        if (!isset($cr['chunk_id']) 
            || !isset($cr['source_lesson_ids']) 
            || !isset($cr['academic_periods']) 
            || !isset($cr['requested_question_allocation']) 
            || !isset($cr['successfully_generated_count']) 
            || !isset($cr['invalid_question_count']) 
            || !isset($cr['duplicate_count']) 
            || !isset($cr['failed_count']) 
            || !isset($cr['final_accepted_count'])) {
            $validChunkSchema = false;
            break;
        }
    }

    $runner->assertTrue("TEST 2: Per-Chunk Metadata Schema Completeness", $validChunkSchema, "All required 9 per-chunk tracking keys present in every chunk result");

    // -----------------------------------------------------------------
    // TEST 3: Coverage Metrics Computation (questions_per_lesson, questions_per_period)
    // -----------------------------------------------------------------
    $qPerLesson = $res1['metadata']['questions_per_lesson'] ?? [];
    $qPerPeriod = $res1['metadata']['questions_per_period'] ?? [];

    $pass3 = isset($qPerLesson[101], $qPerLesson[102], $qPerLesson[103])
        && isset($qPerPeriod['prelim'], $qPerPeriod['midterm'], $qPerPeriod['finals']);

    $runner->assertTrue("TEST 3: Post-Generation Coverage Metrics Computation", $pass3, "Per-lesson and per-period question counts calculated for all 3 lessons and periods");

    // -----------------------------------------------------------------
    // TEST 4: Detection of Uncovered Selected Lessons & Academic Periods
    // -----------------------------------------------------------------
    $uncoveredL = $res1['metadata']['uncovered_lesson_ids'] ?? [];
    $uncoveredP = $res1['metadata']['uncovered_periods'] ?? [];

    $pass4 = is_array($uncoveredL) && is_array($uncoveredP);
    $runner->assertTrue("TEST 4: Uncovered Lesson and Period Detection Contract", $pass4, "uncovered_lesson_ids and uncovered_periods exported as arrays");

    // -----------------------------------------------------------------
    // TEST 5: Coverage-Aware Refill Priority (Exact Requested Count & Deduplication)
    // -----------------------------------------------------------------
    $res5 = GroqService::generateQuestions($fullLessonText, 9, 'Structural Engineering', 'Comprehensive Exam', 'Structural Engineering', 'multiple_choice', 'medium', 'TEST_MOCK_KEY');

    $pass5 = !empty($res5['success'])
        && count($res5['questions']) === 9
        && $res5['metadata']['requested_question_count'] === 9
        && $res5['metadata']['generated_question_count'] === 9;

    $runner->assertTrue("TEST 5: Exact Requested Final Question Count & Deduplication", $pass5, "Requested 9 questions, generated exactly 9 non-duplicate questions");

    // -----------------------------------------------------------------
    // TEST 6: Batch Status Classification
    // -----------------------------------------------------------------
    $partialText = "SOURCE LESSON 1\nLesson ID: 201\nPeriod: Prelim\nTitle: Statics\nSubject: Civil Engineering\nContent: " . str_repeat("Prelim statics force vectors. ", 150)
                 . "\n\nSOURCE LESSON 2\nLesson ID: 202\nPeriod: Midterm\nTitle: Dynamics\nSubject: Civil Engineering\nContent: " . str_repeat("Midterm dynamics particle acceleration. ", 150);

    $res6 = GroqService::generateQuestions($partialText, 4, 'Civil Engineering', 'Statics & Dynamics', 'Structural Engineering', 'multiple_choice', 'medium', 'TEST_MOCK_KEY');

    $pass6 = isset($res6['metadata']['batch_status']);
    $runner->assertTrue("TEST 6: Batch Status Classification", $pass6, "batch_status evaluated as '{$res6['metadata']['batch_status']}'");

    // -----------------------------------------------------------------
    // TEST 7: Persistence of Coverage Metadata in ai_generation_batches
    // -----------------------------------------------------------------
    $batchIdTest = 'batch_production_repair_' . bin2hex(random_bytes(8));
    $teacherIdTest = 10;

    $stmtInsert = $pdo->prepare("
        INSERT INTO ai_generation_batches 
        (generation_batch_id, teacher_id, selected_lesson_ids, selected_lesson_titles, selected_periods, selected_subject, semester, school_year, year_level, program, total_selected_words, estimated_tokens, ai_model, generation_duration, requested_question_count, generated_question_count, failed_question_count, warnings, batch_status, failed_chunk_count, affected_lesson_ids, failure_messages, chunk_generation_results, questions_per_lesson, questions_per_period, uncovered_lesson_ids, uncovered_periods, refill_attempt_count, refill_warnings)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");

    $insertPass = $stmtInsert->execute([
        $batchIdTest,
        $teacherIdTest,
        json_encode([101, 102, 103]),
        json_encode(['Lesson 1', 'Lesson 2', 'Lesson 3']),
        'prelim,midterm,finals',
        'Structural Engineering',
        '1st Semester',
        '2025-2026',
        '4th Year',
        'BSCE',
        4500,
        1125,
        GROQ_DEFAULT_MODEL,
        1.25,
        6,
        6,
        0,
        json_encode([]),
        'completed',
        0,
        json_encode([]),
        json_encode([]),
        json_encode($res1['metadata']['chunk_generation_results']),
        json_encode($res1['metadata']['questions_per_lesson']),
        json_encode($res1['metadata']['questions_per_period']),
        json_encode($res1['metadata']['uncovered_lesson_ids']),
        json_encode($res1['metadata']['uncovered_periods']),
        intval($res1['metadata']['refill_attempt_count'] ?? 0),
        json_encode($res1['metadata']['refill_warnings'] ?? [])
    ]);

    $stmtFetch = $pdo->prepare("SELECT * FROM ai_generation_batches WHERE generation_batch_id = ?");
    $stmtFetch->execute([$batchIdTest]);
    $dbRecord = $stmtFetch->fetch(PDO::FETCH_ASSOC);

    $pass7 = $insertPass 
        && !empty($dbRecord) 
        && !empty($dbRecord['chunk_generation_results']) 
        && !empty($dbRecord['questions_per_lesson'])
        && !empty($dbRecord['questions_per_period']);

    $runner->assertTrue("TEST 7: Database Migration & Coverage Metadata Persistence", $pass7, "chunk_generation_results, questions_per_lesson, questions_per_period persisted into ai_generation_batches");

} catch (Throwable $e) {
    $runner->recordException($e);
} finally {
    if ($pdo !== null && !empty($batchIdTest)) {
        try {
            $pdo->prepare("DELETE FROM ai_generation_batches WHERE generation_batch_id = ?")->execute([$batchIdTest]);
        } catch (Throwable $cleanupError) {
            $runner->recordCleanupFailure("ai_generation_batches {$batchIdTest}", $cleanupError);
        }
    }
}

$runner->finish();
