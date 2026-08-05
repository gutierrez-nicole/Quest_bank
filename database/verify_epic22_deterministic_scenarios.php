<?php
/**
 * Service-Level Verification Runner for QuestBank Epic 2.2 Deterministic Mock Scenarios
 *
 * Checks:
 * 1. Scenario 1 (MOCK_MISSING_SOURCE): exact question count, item #1 missing source_lesson_ids & review_required
 * 2. Scenario 2 (MOCK_INCOMPLETE_BATCH): exact failed Midterm chunk, incomplete batch status, affected lesson IDs & uncovered periods
 * 3. Scenario 3 (MOCK_REFILL_MIDTERM): initial Midterm shortfall, successful refill targeting Midterm, restored coverage & completed status
 * 4. Production Security Boundary: Title markers in production mode NEVER trigger mock execution
 *
 * Strict Exit Code Rules: Exits 0 ONLY IF all setup, connection, and assertions pass.
 */

putenv('APP_ENV=testing');
putenv('TEST_BOOTSTRAP_ACTIVE=1');
$_ENV['APP_ENV'] = 'testing';
$_ENV['TEST_BOOTSTRAP_ACTIVE'] = '1';
$_SERVER['APP_ENV'] = 'testing';
$_SERVER['TEST_BOOTSTRAP_ACTIVE'] = '1';

require_once __DIR__ . '/../app/testing_bootstrap.php';
if (!defined('TEST_CHUNK_LIMIT')) {
    define('TEST_CHUNK_LIMIT', 6000);
}
require_once __DIR__ . '/../app/bootstrap.php';
require_once __DIR__ . '/../app/services/GroqService.php';

$passedCount = 0;
$failedCount = 0;

function logTest($title, $condition, $details = '') {
    global $passedCount, $failedCount;
    if ($condition) {
        $passedCount++;
        echo "  [PASS] {$title}\n";
        if (!empty($details)) {
            echo "         -> {$details}\n";
        }
    } else {
        $failedCount++;
        echo "  [FAIL] {$title}\n";
        if (!empty($details)) {
            echo "         -> {$details}\n";
        }
    }
}

echo "===========================================================\n";
echo " QUESTBANK EPIC 2.2 DETERMINISTIC MOCK SCENARIO VERIFICATION\n";
echo "===========================================================\n";

try {
    $pdo = getDBConnection();
    logTest("Setup: Database Connection Established", true, "Database handle active");
} catch (Throwable $e) {
    fwrite(STDERR, "SETUP FAILED: Database unavailable.\n");
    exit(1);
}

// Prepare 2 lesson materials: Lesson 101 (Prelim) and Lesson 102 (Midterm)
$prelimText = "Lesson ID: 101\nPeriod: prelim\nTitle: Structural Analysis Prelim Basics\n" . str_repeat("Structural engineering deals with analysis of beams and columns under load. ", 100);
$midtermText = "Lesson ID: 102\nPeriod: midterm\nTitle: Reinforced Concrete Midterm Design\n" . str_repeat("Reinforced concrete requires flexural steel design and shear reinforcement. ", 100);
$combinedText = $prelimText . "\n\n" . $midtermText;

// -------------------------------------------------------------------------
// TEST 1: Scenario 1 — Missing Source (MOCK_MISSING_SOURCE)
// -------------------------------------------------------------------------
GroqService::enableTestingModeFromBootstrap();
$res1 = GroqService::generateQuestions($combinedText, 5, 'Structural Engineering', 'MOCK_MISSING_SOURCE Structural Exam', 'Structural', 'multiple_choice', 'medium');

$t1_pass = false;
$t1_details = '';
if ($res1['success'] && count($res1['questions']) === 5) {
    $q0 = $res1['questions'][0];
    $q1 = $res1['questions'][1];
    $q0_no_source = empty($q0['source_lesson_ids']) && ($q0['source_confidence'] ?? '') === 'review_required';
    $q1_has_source = !empty($q1['source_lesson_ids']);
    if ($q0_no_source && $q1_has_source) {
        $t1_pass = true;
        $t1_details = "Generated 5 questions. Item 0 has empty source_lesson_ids & review_required status; Item 1 has valid sources " . json_encode($q1['source_lesson_ids']);
    } else {
        $t1_details = "Question 0 sources: " . json_encode($q0['source_lesson_ids']) . ", confidence: " . ($q0['source_confidence'] ?? 'none');
    }
} else {
    $t1_details = "Generation failed or returned incorrect question count";
}
logTest("TEST 1: Scenario 1 (MOCK_MISSING_SOURCE) - Item 0 Missing Source", $t1_pass, $t1_details);

// -------------------------------------------------------------------------
// TEST 2: Scenario 2 — Incomplete Batch (MOCK_INCOMPLETE_BATCH)
// -------------------------------------------------------------------------
$res2 = GroqService::generateQuestions($combinedText, 5, 'Structural Engineering', 'MOCK_INCOMPLETE_BATCH Structural Exam', 'Structural', 'multiple_choice', 'medium');

$t2_pass = false;
$t2_details = '';
if ($res2['success']) {
    $meta2 = $res2['metadata'];
    $status2 = $meta2['batch_status'] ?? '';
    $failedChunks2 = intval($meta2['failed_chunk_count'] ?? 0);
    $affectedLids2 = $meta2['affected_lesson_ids'] ?? [];
    $uncoveredP2 = $meta2['uncovered_periods'] ?? [];

    if ($status2 === 'incomplete' && $failedChunks2 >= 1 && in_array(102, $affectedLids2) && in_array('midterm', $uncoveredP2)) {
        $t2_pass = true;
        $t2_details = "batch_status: 'incomplete', failed_chunk_count: {$failedChunks2}, affected_lesson_ids: " . json_encode($affectedLids2) . ", uncovered_periods: " . json_encode($uncoveredP2);
    } else {
        $t2_details = "status: {$status2}, failedChunks: {$failedChunks2}, affectedLids: " . json_encode($affectedLids2) . ", uncoveredPeriods: " . json_encode($uncoveredP2);
    }
} else {
    $t2_details = "Generation call failed";
}
logTest("TEST 2: Scenario 2 (MOCK_INCOMPLETE_BATCH) - Deterministic Midterm Chunk Failure", $t2_pass, $t2_details);

// -------------------------------------------------------------------------
// TEST 3: Scenario 3 — Coverage-Aware Refill (MOCK_REFILL_MIDTERM)
// -------------------------------------------------------------------------
$res3 = GroqService::generateQuestions($combinedText, 5, 'Structural Engineering', 'MOCK_REFILL_MIDTERM Structural Exam', 'Structural', 'multiple_choice', 'medium');

$t3_pass = false;
$t3_details = '';
if ($res3['success'] && count($res3['questions']) === 5) {
    $meta3 = $res3['metadata'];
    $status3 = $meta3['batch_status'] ?? '';
    $refillAttempts3 = intval($meta3['refill_attempt_count'] ?? 0);
    $perPeriod3 = $meta3['questions_per_period'] ?? [];
    $simScenario3 = $meta3['simulated_scenario'] ?? '';
    $refillGenCount3 = intval($meta3['refill_generated_count'] ?? 0);
    $failedChunkIdx3 = $meta3['failed_chunk_index'] ?? null;
    $refillTargetIdx3 = $meta3['refill_target_chunk_index'] ?? null;
    $refillTargetLids3 = $meta3['refill_target_lesson_ids'] ?? [];
    $refillTargetPeriods3 = $meta3['refill_target_periods'] ?? [];
    $initialPerPeriod3 = $meta3['initial_questions_per_period'] ?? [];
    $initialUncoveredP3 = $meta3['initial_uncovered_periods'] ?? [];

    if ($status3 === 'completed' && $refillAttempts3 >= 1 && ($perPeriod3['midterm'] ?? 0) > 0) {
        $t3_pass = true;
        $t3_details = "Refill attempts: {$refillAttempts3}, batch_status: 'completed', midterm_questions: " . ($perPeriod3['midterm'] ?? 0) . ", final_questions: " . count($res3['questions']);
        $t3_details .= ", simulated_scenario: {$simScenario3}, refill_generated_count: {$refillGenCount3}";
        $t3_details .= ", refill_target_periods: " . json_encode($refillTargetPeriods3);
        $t3_details .= ", initial_uncovered_periods: " . json_encode($initialUncoveredP3);
    } else {
        $t3_details = "status: {$status3}, refillAttempts: {$refillAttempts3}, perPeriod: " . json_encode($perPeriod3);
    }
} else {
    $t3_details = "Generation call failed or count != 5. Success: " . json_encode($res3['success'] ?? false) . ", count: " . (is_array($res3['questions'] ?? null) ? count($res3['questions']) : 'null') . ", error: " . ($res3['user_message'] ?? $res3['error'] ?? 'none');
}
logTest("TEST 3: Scenario 3 (MOCK_REFILL_MIDTERM) - Midterm Coverage Restored via Refill", $t3_pass, $t3_details);

// Additional refill metadata assertions
if ($res3['success'] && count($res3['questions']) === 5) {
    $meta3x = $res3['metadata'];
    logTest("TEST 3a: simulated_scenario = 'midterm_refill'", ($meta3x['simulated_scenario'] ?? '') === 'midterm_refill', "Got: " . ($meta3x['simulated_scenario'] ?? 'null'));
    logTest("TEST 3b: refill_generated_count > 0", intval($meta3x['refill_generated_count'] ?? 0) > 0, "Got: " . ($meta3x['refill_generated_count'] ?? 0));
    logTest("TEST 3c: failed_chunk_index set", ($meta3x['failed_chunk_index'] ?? null) !== null, "Got: " . json_encode($meta3x['failed_chunk_index'] ?? null));
    logTest("TEST 3d: refill_target_chunk_index set", ($meta3x['refill_target_chunk_index'] ?? null) !== null, "Got: " . json_encode($meta3x['refill_target_chunk_index'] ?? null));
    logTest("TEST 3e: refill_target_lesson_ids contains midterm lesson", in_array(102, $meta3x['refill_target_lesson_ids'] ?? []), "Got: " . json_encode($meta3x['refill_target_lesson_ids'] ?? []));
    logTest("TEST 3f: refill_target_periods contains midterm", in_array('midterm', $meta3x['refill_target_periods'] ?? []), "Got: " . json_encode($meta3x['refill_target_periods'] ?? []));
    logTest("TEST 3g: initial midterm count = 0", intval($meta3x['initial_questions_per_period']['midterm'] ?? -1) === 0, "Got: " . json_encode($meta3x['initial_questions_per_period'] ?? []));
    logTest("TEST 3h: initial_uncovered_periods contains midterm", in_array('midterm', $meta3x['initial_uncovered_periods'] ?? []), "Got: " . json_encode($meta3x['initial_uncovered_periods'] ?? []));
    logTest("TEST 3i: final midterm count > 0", ($meta3x['questions_per_period']['midterm'] ?? 0) > 0, "Got: " . json_encode($meta3x['questions_per_period'] ?? []));
    logTest("TEST 3j: final uncovered_periods does NOT contain midterm", !in_array('midterm', $meta3x['uncovered_periods'] ?? ['midterm']), "Got: " . json_encode($meta3x['uncovered_periods'] ?? []));

    // No duplicate questions
    $qTexts3 = array_map(function($q) { return $q['question']; }, $res3['questions']);
    $uniqueTexts3 = array_unique($qTexts3);
    logTest("TEST 3k: No duplicate questions", count($qTexts3) === count($uniqueTexts3), "Total: " . count($qTexts3) . ", Unique: " . count($uniqueTexts3));

    // Source attribution check
    $allSourced3 = true;
    foreach ($res3['questions'] as $q3) {
        if (empty($q3['source_lesson_ids'])) {
            $allSourced3 = false;
            break;
        }
    }
    logTest("TEST 3l: All questions have source attribution", $allSourced3, "Checked " . count($res3['questions']) . " questions");
}

// -------------------------------------------------------------------------
// TEST 4: Production Security Boundary (No Mock in Production)
// -------------------------------------------------------------------------
putenv('APP_ENV=production');
$_ENV['APP_ENV'] = 'production';
$_SERVER['APP_ENV'] = 'production';
GroqService::enableTestingModeFromBootstrap();

$res4 = GroqService::generateQuestions($combinedText, 5, 'Structural Engineering', 'MOCK_INCOMPLETE_BATCH Production Security Test', 'Structural', 'multiple_choice', 'medium');

$t4_pass = false;
$t4_details = '';
if (isset($res4['success']) && $res4['success'] === false) {
    $errCode4 = $res4['error_code'] ?? '';
    if (in_array($errCode4, ['MISSING_API_KEY', 'INVALID_API_KEY'], true)) {
        $t4_pass = true;
        $t4_details = "Production request cleanly rejected mock trigger with error_code: {$errCode4}";
    } else {
        $t4_details = "Unexpected error code: {$errCode4}";
    }
} else {
    $t4_details = "Production request unexpectedly returned mock questions!";
}
logTest("TEST 4: Production Security Boundary - Title Markers Do Not Trigger Mock", $t4_pass, $t4_details);

// Restore testing env for report
putenv('APP_ENV=testing');
putenv('TEST_BOOTSTRAP_ACTIVE=1');
$_ENV['APP_ENV'] = 'testing';
$_ENV['TEST_BOOTSTRAP_ACTIVE'] = '1';
$_SERVER['APP_ENV'] = 'testing';
$_SERVER['TEST_BOOTSTRAP_ACTIVE'] = '1';
GroqService::enableTestingModeFromBootstrap();

echo "\n-----------------------------------------------------------\n";
echo "VERIFICATION SUMMARY: {$passedCount} PASSED, {$failedCount} FAILED\n";
echo "-----------------------------------------------------------\n";

if ($passedCount > 0 && $failedCount === 0) {
    echo "RESULT: SUCCESS — All deterministic mock scenario assertions passed cleanly.\n";
    exit(0);
} else {
    echo "RESULT: FAILURE — {$failedCount} test(s) failed or no assertions ran.\n";
    exit(1);
}
