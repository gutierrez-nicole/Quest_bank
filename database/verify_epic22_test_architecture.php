<?php
/**
 * QUESTBANK — EPIC 2.2 FINAL TEST-MODE ARCHITECTURE REPAIR VERIFICATION
 */

putenv('APP_ENV=testing');
putenv('TEST_BOOTSTRAP_ACTIVE=1');
require_once __DIR__ . '/../app/bootstrap.php';

function logTest($name, $passed, $detail = '') {
    $status = $passed ? "\033[32m[PASS]\033[0m" : "\033[31m[FAIL]\033[0m";
    echo "  {$status} {$name}\n";
    if ($detail) {
        echo "         -> {$detail}\n";
    }
    if (!$passed) {
        echo "\n\033[31mVERIFICATION FAILED AT: {$name}\033[0m\n";
        exit(1);
    }
}

echo "===========================================================\n";
echo " QUESTBANK EPIC 2.2 TEST-MODE ARCHITECTURE VERIFICATION     \n";
echo "===========================================================\n";

// -----------------------------------------------------------------
// TEST 1: Production APP_ENV never enables test mode
// -----------------------------------------------------------------
putenv('APP_ENV=production');
putenv('TEST_BOOTSTRAP_ACTIVE=1');
$_ENV['APP_ENV'] = 'production';
$_ENV['TEST_BOOTSTRAP_ACTIVE'] = '1';
$_SERVER['APP_ENV'] = 'production';
$_SERVER['TEST_BOOTSTRAP_ACTIVE'] = '1';
include __DIR__ . '/../app/testing_bootstrap.php';

$test1Pass = (GroqService::isTestModeActive() === false);
logTest("TEST 1: Production APP_ENV Never Enables Test Mode", $test1Pass, "APP_ENV=production resulting testMode: " . (GroqService::isTestModeActive() ? 'true' : 'false'));

// -----------------------------------------------------------------
// TEST 2: Development APP_ENV never enables test mode
// -----------------------------------------------------------------
putenv('APP_ENV=development');
putenv('TEST_BOOTSTRAP_ACTIVE=1');
$_ENV['APP_ENV'] = 'development';
$_ENV['TEST_BOOTSTRAP_ACTIVE'] = '1';
$_SERVER['APP_ENV'] = 'development';
$_SERVER['TEST_BOOTSTRAP_ACTIVE'] = '1';
include __DIR__ . '/../app/testing_bootstrap.php';

$test2Pass = (GroqService::isTestModeActive() === false);
logTest("TEST 2: Development APP_ENV Never Enables Test Mode", $test2Pass, "APP_ENV=development resulting testMode: " . (GroqService::isTestModeActive() ? 'true' : 'false'));

// -----------------------------------------------------------------
// TEST 3: Testing APP_ENV without explicit test bootstrap does not enable mock mode
// -----------------------------------------------------------------
putenv('APP_ENV=testing');
putenv('TEST_BOOTSTRAP_ACTIVE=0');
$_ENV['APP_ENV'] = 'testing';
$_ENV['TEST_BOOTSTRAP_ACTIVE'] = '0';
$_SERVER['APP_ENV'] = 'testing';
$_SERVER['TEST_BOOTSTRAP_ACTIVE'] = '0';
include __DIR__ . '/../app/testing_bootstrap.php';

$test3Pass = (GroqService::isTestModeActive() === false);
logTest("TEST 3: Testing APP_ENV Without Test Bootstrap Rejects Mock Mode", $test3Pass, "TEST_BOOTSTRAP_ACTIVE=0 resulting testMode: " . (GroqService::isTestModeActive() ? 'true' : 'false'));

// -----------------------------------------------------------------
// TEST 4: Testing bootstrap enables mock mode
// -----------------------------------------------------------------
putenv('APP_ENV=testing');
putenv('TEST_BOOTSTRAP_ACTIVE=1');
$_ENV['APP_ENV'] = 'testing';
$_ENV['TEST_BOOTSTRAP_ACTIVE'] = '1';
$_SERVER['APP_ENV'] = 'testing';
$_SERVER['TEST_BOOTSTRAP_ACTIVE'] = '1';
include __DIR__ . '/../app/testing_bootstrap.php';

$test4Pass = (GroqService::isTestModeActive() === true);
logTest("TEST 4: Testing Bootstrap Enables Mock Mode Cleanly", $test4Pass, "TEST_BOOTSTRAP_ACTIVE=1 resulting testMode: " . (GroqService::isTestModeActive() ? 'true' : 'false'));

// -----------------------------------------------------------------
// TEST 5: Production request containing MOCK_* title does NOT activate mock behavior
// -----------------------------------------------------------------
putenv('APP_ENV=production');
putenv('TEST_BOOTSTRAP_ACTIVE=0');
$_ENV['APP_ENV'] = 'production';
$_ENV['TEST_BOOTSTRAP_ACTIVE'] = '0';
$_SERVER['APP_ENV'] = 'production';
$_SERVER['TEST_BOOTSTRAP_ACTIVE'] = '0';
include __DIR__ . '/../app/testing_bootstrap.php';

$resProdMock = GroqService::generateQuestions(
    "Lesson ID: 101\nLesson content placeholder for production testing.",
    5,
    'Structural Engineering',
    'MOCK_MISSING_SOURCE Production Test',
    'Structural Engineering',
    'multiple_choice',
    'medium',
    'TEST_MOCK_KEY'
);

$test5Pass = isset($resProdMock['error']) && strpos($resProdMock['error'], 'Groq API Key') !== false;
logTest("TEST 5: Production Request with MOCK_* Title Rejects Mock Execution", $test5Pass, "Response returned expected API key error: " . ($resProdMock['error'] ?? 'None'));

// -----------------------------------------------------------------
// TEST 6: Test request under testing bootstrap activates deterministic scenarios
// -----------------------------------------------------------------
putenv('APP_ENV=testing');
putenv('TEST_BOOTSTRAP_ACTIVE=1');
$_ENV['APP_ENV'] = 'testing';
$_ENV['TEST_BOOTSTRAP_ACTIVE'] = '1';
$_SERVER['APP_ENV'] = 'testing';
$_SERVER['TEST_BOOTSTRAP_ACTIVE'] = '1';
include __DIR__ . '/../app/testing_bootstrap.php';

$resTestMock = GroqService::generateQuestions(
    "Lesson ID: 101\nLesson content placeholder for testing mode validation.",
    5,
    'Structural Engineering',
    'MOCK_MISSING_SOURCE Testing Workflow',
    'Structural Engineering',
    'multiple_choice',
    'medium',
    'TEST_MOCK_KEY'
);

$test6Pass = isset($resTestMock['success']) && $resTestMock['success'] === true && count($resTestMock['questions'] ?? []) === 5;
logTest("TEST 6: Test Request under Testing Bootstrap Generates Deterministic Mock Items", $test6Pass, "Generated " . count($resTestMock['questions'] ?? []) . " mock questions under testing bootstrap");

echo "\n-----------------------------------------------------------\n";
echo "VERIFICATION SUMMARY: 6 PASSED, 0 FAILED\n";
echo "-----------------------------------------------------------\n";
echo "RESULT: SUCCESS — All test-mode architecture rules verified cleanly.\n";
exit(0);
