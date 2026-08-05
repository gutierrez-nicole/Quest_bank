<?php

require_once __DIR__ . '/../tests/helpers/test_preflight.php';
requireAiPreflight();

require_once __DIR__ . '/../app/bootstrap.php';
require_once __DIR__ . '/../app/services/GroqService.php';

$passed = 0;
$failed = 0;
$skipped = 0;

function logTest($title, $condition, $details = '') {
    global $passed, $failed;
    if ($condition) {
        $passed++;
        echo "  [PASS] {$title}\n";
        if ($details) echo "         -> {$details}\n";
    } else {
        $failed++;
        echo "  [FAIL] {$title}\n";
        if ($details) echo "         -> {$details}\n";
    }
}

echo "===========================================================\n";
echo " QUESTBANK EPIC 2.2 MOCK-SCENARIO METADATA VERIFICATION    \n";
echo "===========================================================\n";

try {
    $pdo = getDBConnection();
    logTest("Setup: Database Connection Established", $pdo !== null, "Database handle active");

    $sampleLessonText = "
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
";

    // -----------------------------------------------------------
    // TEST 1: Exact Marker Activates in Secure Test Mode
    // -----------------------------------------------------------
    putenv('APP_ENV=testing');
    $_ENV['APP_ENV'] = 'testing';
    $_SERVER['APP_ENV'] = 'testing';
    putenv('TEST_BOOTSTRAP_ACTIVE=1');
    $_ENV['TEST_BOOTSTRAP_ACTIVE'] = '1';
    $_SERVER['TEST_BOOTSTRAP_ACTIVE'] = '1';
    GroqService::enableTestingModeFromBootstrap();

    $res1 = GroqService::generateQuestions(
        $sampleLessonText,
        5,
        "Civil Engineering",
        "MOCK_INCOMPLETE_BATCH Midterm Exam",
        "Structural Engineering",
        "multiple_choice",
        "medium",
        "TEST_MOCK_KEY"
    );

    $meta1 = $res1['metadata'] ?? [];
    $scen1 = $meta1['simulated_scenario'] ?? null;
    $status1 = $meta1['batch_status'] ?? null;

    logTest("TEST 1a: Exact marker MOCK_INCOMPLETE_BATCH activates mock scenario",
        $status1 === 'incomplete' && $scen1 === 'incomplete_midterm_chunk',
        "batch_status: {$status1}, simulated_scenario: {$scen1}"
    );

    $res1b = GroqService::generateQuestions(
        $sampleLessonText,
        5,
        "Civil Engineering",
        "MOCK_MISSING_SOURCE Highway Exam",
        "Structural Engineering",
        "multiple_choice",
        "medium",
        "TEST_MOCK_KEY"
    );

    $meta1b = $res1b['metadata'] ?? [];
    $scen1b = $meta1b['simulated_scenario'] ?? null;

    logTest("TEST 1b: Exact marker MOCK_MISSING_SOURCE activates missing_source scenario",
        $scen1b === 'missing_source',
        "simulated_scenario: {$scen1b}"
    );

    // -----------------------------------------------------------
    // TEST 2: Ordinary Phrase Does NOT Activate Scenario in Test Mode
    // -----------------------------------------------------------
    $res2 = GroqService::generateQuestions(
        $sampleLessonText,
        5,
        "Civil Engineering",
        "Incomplete Batch Review Exam",
        "Structural Engineering",
        "multiple_choice",
        "medium",
        "TEST_MOCK_KEY"
    );

    $meta2 = $res2['metadata'] ?? [];
    $scen2 = $meta2['simulated_scenario'] ?? null;
    $status2 = $meta2['batch_status'] ?? null;

    logTest("TEST 2: Ordinary phrase 'Incomplete Batch' does NOT trigger mock scenario",
        $status2 === 'completed' && $scen2 === null,
        "batch_status: {$status2}, simulated_scenario: " . json_encode($scen2)
    );

    $res2b = GroqService::generateQuestions(
        $sampleLessonText,
        5,
        "Civil Engineering",
        "Missing Source Review Exam",
        "Structural Engineering",
        "multiple_choice",
        "medium",
        "TEST_MOCK_KEY"
    );

    $meta2b = $res2b['metadata'] ?? [];
    $scen2b = $meta2b['simulated_scenario'] ?? null;

    logTest("TEST 2b: Ordinary phrase 'Missing Source' does NOT trigger missing_source scenario",
        $scen2b === null,
        "simulated_scenario: " . json_encode($scen2b)
    );

    // -----------------------------------------------------------
    // TEST 3: Title Marker Outside Testing Does NOT Activate
    // -----------------------------------------------------------
    putenv('APP_ENV=production');
    $_ENV['APP_ENV'] = 'production';
    $_SERVER['APP_ENV'] = 'production';
    GroqService::disableTestingMode();

    $res3 = GroqService::generateQuestions(
        $sampleLessonText,
        5,
        "Civil Engineering",
        "MOCK_INCOMPLETE_BATCH Midterm Exam",
        "Structural Engineering",
        "multiple_choice",
        "medium",
        "INVALID_KEY_XYZ"
    );

    $scen3 = $res3['metadata']['simulated_scenario'] ?? null;
    $success3 = $res3['success'] ?? null;
    $errCode3 = $res3['error_code'] ?? null;

    logTest("TEST 3: Title marker MOCK_INCOMPLETE_BATCH in production does NOT activate mock",
        $success3 === false && $errCode3 === 'INVALID_API_KEY' && $scen3 === null,
        "success: " . json_encode($success3) . ", error_code: {$errCode3}, simulated_scenario: " . json_encode($scen3)
    );

    // Re-enable test mode for remaining test assertions
    putenv('APP_ENV=testing');
    $_ENV['APP_ENV'] = 'testing';
    $_SERVER['APP_ENV'] = 'testing';
    putenv('TEST_BOOTSTRAP_ACTIVE=1');
    $_ENV['TEST_BOOTSTRAP_ACTIVE'] = '1';
    $_SERVER['TEST_BOOTSTRAP_ACTIVE'] = '1';
    GroqService::enableTestingModeFromBootstrap();

    // -----------------------------------------------------------
    // TEST 4: Simulated Scenario is NULL when No Scenario Executes
    // -----------------------------------------------------------
    $res4 = GroqService::generateQuestions(
        $sampleLessonText,
        5,
        "Civil Engineering",
        "Standard General Assessment",
        "Structural Engineering",
        "multiple_choice",
        "medium",
        "TEST_MOCK_KEY"
    );

    $scen4 = $res4['metadata']['simulated_scenario'] ?? null;
    logTest("TEST 4: Standard generation has simulated_scenario === null",
        $scen4 === null,
        "simulated_scenario: " . json_encode($scen4)
    );

    // -----------------------------------------------------------
    // TEST 5: Metadata Cannot Claim Scenario Solely from Title Text
    // -----------------------------------------------------------
    $res5 = GroqService::generateQuestions(
        $sampleLessonText,
        5,
        "Civil Engineering",
        "Refill Midterm Overview",
        "Structural Engineering",
        "multiple_choice",
        "medium",
        "TEST_MOCK_KEY"
    );

    $scen5 = $res5['metadata']['simulated_scenario'] ?? null;
    logTest("TEST 5: Title 'Refill Midterm Overview' without MOCK_ prefix cannot claim scenario",
        $scen5 === null,
        "simulated_scenario: " . json_encode($scen5)
    );

} catch (Throwable $e) {
    $failed++;
    fwrite(STDERR, "\nSETUP OR RUNTIME EXCEPTION: " . $e->getMessage() . "\n" . $e->getTraceAsString() . "\n");
}

echo "\n-----------------------------------------------------------\n";
echo "VERIFICATION SUMMARY: {$passed} PASSED, {$failed} FAILED, {$skipped} SKIPPED\n";
echo "-----------------------------------------------------------\n";

if ($failed > 0) {
    echo "RESULT: FAILURE — {$failed} assertions failed.\n";
    exit(1);
} else {
    echo "RESULT: SUCCESS — All mock scenario metadata assertions passed cleanly.\n";
    exit(0);
}
