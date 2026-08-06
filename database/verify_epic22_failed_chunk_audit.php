<?php
require_once __DIR__ . '/../tests/helpers/test_runner.php';
requireAiPreflight();

require_once __DIR__ . '/../app/bootstrap.php';
require_once __DIR__ . '/../app/services/GroqService.php';

putenv('APP_ENV=testing');
$_ENV['APP_ENV'] = 'testing';
$_SERVER['APP_ENV'] = 'testing';
putenv('TEST_BOOTSTRAP_ACTIVE=1');
$_ENV['TEST_BOOTSTRAP_ACTIVE'] = '1';
$_SERVER['TEST_BOOTSTRAP_ACTIVE'] = '1';

GroqService::enableTestingModeFromBootstrap();

$runner = new TestRunner('QuestBank Epic 2.2 Failed Chunk Audit Accuracy Verification');

// Controlled failure hooks for meta-verification
if (getenv('FORCE_ASSERT_FAIL') === '1') {
    $runner->assertTrue("Forced Assertion Failure Test", false, "FORCE_ASSERT_FAIL=1");
}
if (getenv('FORCE_RUNTIME_EXCEPTION') === '1') {
    try { throw new RuntimeException('FORCE_RUNTIME_EXCEPTION=1'); } catch (Throwable $e) { $runner->recordException($e); $runner->finish(); }
}

try {
    $pdo = getDBConnection();
    $runner->setSetupCompleted($pdo !== null, "Database connection established");

    // Build 3-chunk lesson prompt text containing 3 distinct lessons & periods
    $chunkLessonText = "
=== LESSON 101 ===
Lesson ID: 101
Title: Highway Engineering Prelims
Period: prelim
Content: Stopping sight distance formulas and reaction time design.

=== LESSON 102 ===
Lesson ID: 102
Title: Pavement Design Midterm
Period: midterm
Content: Flexible pavement structural numbers and CBR values.

=== LESSON 103 ===
Lesson ID: 103
Title: Structural Concrete Finals
Period: finals
Content: Reinforced concrete flexural beam design and rebar spacing.
";

    // -----------------------------------------------------------
    // TEST 1: CHUNK 0 FAILURE
    // -----------------------------------------------------------
    $res0 = GroqService::generateQuestions(
        $chunkLessonText,
        6,
        "Civil Engineering",
        "MOCK_FAIL_CHUNK_0 Highway Exam",
        "Structural Engineering",
        "multiple_choice",
        "medium",
        "TEST_MOCK_KEY"
    );

    $meta0 = $res0['metadata'] ?? [];
    $failedIdxs0 = $meta0['failed_chunk_indexes'] ?? [];
    $failedCnt0 = $meta0['failed_chunk_count'] ?? null;
    $firstIdx0 = $meta0['first_failed_chunk_index'] ?? null;
    $legacyIdx0 = $meta0['failed_chunk_index'] ?? null;
    $refillTarget0 = $meta0['refill_target_chunk_index'] ?? null;
    $affectedLids0 = $meta0['affected_lesson_ids'] ?? [];
    $affectedPeriods0 = $meta0['affected_periods'] ?? [];

    $runner->assertTrue("TEST 1a: Chunk 0 failure -> failed_chunk_indexes === [0]", $failedIdxs0 === [0], "Got: " . json_encode($failedIdxs0));
    $runner->assertTrue("TEST 1b: Chunk 0 failure -> failed_chunk_count === 1", $failedCnt0 === 1, "Got: " . json_encode($failedCnt0));
    $runner->assertTrue("TEST 1c: Chunk 0 failure -> first_failed_chunk_index === 0", $firstIdx0 === 0, "Got: " . json_encode($firstIdx0));
    $runner->assertTrue("TEST 1d: Chunk 0 failure -> legacy failed_chunk_index === 0 (not 1)", $legacyIdx0 === 0, "Got: " . json_encode($legacyIdx0));
    $runner->assertTrue("TEST 1e: Chunk 0 failure -> failed_chunk_count == count(failed_chunk_indexes)", $failedCnt0 === count($failedIdxs0), "Count: {$failedCnt0}");
    $runner->assertTrue("TEST 1f: Chunk 0 failure -> affected lessons include 101", in_array(101, $affectedLids0, true), "Affected: " . json_encode($affectedLids0));
    $runner->assertTrue("TEST 1g: Chunk 0 failure -> affected periods include prelim", in_array('prelim', $affectedPeriods0, true), "Periods: " . json_encode($affectedPeriods0));

    // -----------------------------------------------------------
    // TEST 2: CHUNK 1 FAILURE
    // -----------------------------------------------------------
    $res1 = GroqService::generateQuestions(
        $chunkLessonText,
        6,
        "Civil Engineering",
        "MOCK_FAIL_CHUNK_1 Highway Exam",
        "Structural Engineering",
        "multiple_choice",
        "medium",
        "TEST_MOCK_KEY"
    );

    $meta1 = $res1['metadata'] ?? [];
    $failedIdxs1 = $meta1['failed_chunk_indexes'] ?? [];
    $failedCnt1 = $meta1['failed_chunk_count'] ?? null;
    $firstIdx1 = $meta1['first_failed_chunk_index'] ?? null;
    $legacyIdx1 = $meta1['failed_chunk_index'] ?? null;
    $affectedLids1 = $meta1['affected_lesson_ids'] ?? [];
    $affectedPeriods1 = $meta1['affected_periods'] ?? [];

    $runner->assertTrue("TEST 2a: Chunk 1 failure -> failed_chunk_indexes === [1]", $failedIdxs1 === [1], "Got: " . json_encode($failedIdxs1));
    $runner->assertTrue("TEST 2b: Chunk 1 failure -> failed_chunk_count === 1", $failedCnt1 === 1, "Got: " . json_encode($failedCnt1));
    $runner->assertTrue("TEST 2c: Chunk 1 failure -> first_failed_chunk_index === 1", $firstIdx1 === 1, "Got: " . json_encode($firstIdx1));
    $runner->assertTrue("TEST 2d: Chunk 1 failure -> legacy failed_chunk_index === 1", $legacyIdx1 === 1, "Got: " . json_encode($legacyIdx1));
    $runner->assertTrue("TEST 2e: Chunk 1 failure -> affected lessons include 102", in_array(102, $affectedLids1, true), "Affected: " . json_encode($affectedLids1));
    $runner->assertTrue("TEST 2f: Chunk 1 failure -> affected periods include midterm", in_array('midterm', $affectedPeriods1, true), "Periods: " . json_encode($affectedPeriods1));

    // -----------------------------------------------------------
    // TEST 3: CHUNK 2 FAILURE
    // -----------------------------------------------------------
    $res2 = GroqService::generateQuestions(
        $chunkLessonText,
        6,
        "Civil Engineering",
        "MOCK_FAIL_CHUNK_2 Highway Exam",
        "Structural Engineering",
        "multiple_choice",
        "medium",
        "TEST_MOCK_KEY"
    );

    $meta2 = $res2['metadata'] ?? [];
    $failedIdxs2 = $meta2['failed_chunk_indexes'] ?? [];
    $failedCnt2 = $meta2['failed_chunk_count'] ?? null;
    $firstIdx2 = $meta2['first_failed_chunk_index'] ?? null;
    $legacyIdx2 = $meta2['failed_chunk_index'] ?? null;
    $affectedLids2 = $meta2['affected_lesson_ids'] ?? [];
    $affectedPeriods2 = $meta2['affected_periods'] ?? [];

    $runner->assertTrue("TEST 3a: Chunk 2 failure -> failed_chunk_indexes === [2]", $failedIdxs2 === [2], "Got: " . json_encode($failedIdxs2));
    $runner->assertTrue("TEST 3b: Chunk 2 failure -> failed_chunk_count === 1", $failedCnt2 === 1, "Got: " . json_encode($failedCnt2));
    $runner->assertTrue("TEST 3c: Chunk 2 failure -> first_failed_chunk_index === 2", $firstIdx2 === 2, "Got: " . json_encode($firstIdx2));
    $runner->assertTrue("TEST 3d: Chunk 2 failure -> legacy failed_chunk_index === 2 (not 1)", $legacyIdx2 === 2, "Got: " . json_encode($legacyIdx2));
    $runner->assertTrue("TEST 3e: Chunk 2 failure -> affected lessons include 103", in_array(103, $affectedLids2, true), "Affected: " . json_encode($affectedLids2));
    $runner->assertTrue("TEST 3f: Chunk 2 failure -> affected periods include finals", in_array('finals', $affectedPeriods2, true), "Periods: " . json_encode($affectedPeriods2));

    // -----------------------------------------------------------
    // TEST 4: MULTIPLE FAILURES (CHUNKS 0 AND 2)
    // -----------------------------------------------------------
    $res02 = GroqService::generateQuestions(
        $chunkLessonText,
        6,
        "Civil Engineering",
        "MOCK_FAIL_CHUNK_0_2 Highway Exam",
        "Structural Engineering",
        "multiple_choice",
        "medium",
        "TEST_MOCK_KEY"
    );

    $meta02 = $res02['metadata'] ?? [];
    $failedIdxs02 = $meta02['failed_chunk_indexes'] ?? [];
    $failedCnt02 = $meta02['failed_chunk_count'] ?? null;
    $firstIdx02 = $meta02['first_failed_chunk_index'] ?? null;
    $failedChunks02 = $meta02['failed_chunks'] ?? [];

    $runner->assertTrue("TEST 4a: Multiple failures -> failed_chunk_indexes === [0, 2]", $failedIdxs02 === [0, 2], "Got: " . json_encode($failedIdxs02));
    $runner->assertTrue("TEST 4b: Multiple failures -> failed_chunk_count === 2", $failedCnt02 === 2, "Got: " . json_encode($failedCnt02));
    $runner->assertTrue("TEST 4c: Multiple failures -> first_failed_chunk_index === 0", $firstIdx02 === 0, "Got: " . json_encode($firstIdx02));
    $runner->assertTrue("TEST 4d: Multiple failures -> failed_chunks detailed count === 2", count($failedChunks02) === 2, "Detailed count: " . count($failedChunks02));

    // -----------------------------------------------------------
    // TEST 5: NO HARDCODED INDEX REMAINS
    // -----------------------------------------------------------
    $runner->assertTrue("TEST 5: Dynamic failed_chunk_index varies per chunk failure",
        $legacyIdx0 === 0 && $legacyIdx1 === 1 && $legacyIdx2 === 2,
        "Indexes: chunk0=>{$legacyIdx0}, chunk1=>{$legacyIdx1}, chunk2=>{$legacyIdx2}"
    );

} catch (Throwable $e) {
    $runner->recordException($e);
}

$runner->finish();
