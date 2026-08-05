<?php

require_once __DIR__ . '/../tests/helpers/test_runner.php';
requireAiPreflight();

require_once __DIR__ . '/../app/bootstrap.php';

$runner = new TestRunner('QuestBank Epic 2.2 No Mock AI Fallback Verification');

$sampleLessonText = "Soil mechanics is a branch of soil physics and applied mechanics that describes the behavior of soils. It differs from fluid mechanics and solid mechanics in that soils consist of a heterogeneous mixture of fluids (usually air and water) and particles (usually clay, silt, sand, and gravel).";

try {
    $pdo = getDBConnection();
    $runner->setSetupCompleted($pdo !== null, "Database connection established");

    // --- TEST 1: Missing API Key Returns Failure ---
    $res1 = GroqService::generateQuestions($sampleLessonText, 5, 'Soil Mechanics', 'Test Exam', 'Geotechnical', 'multiple_choice', 'medium', 'MISSING_KEY');
    $pass1 = (isset($res1['success']) && $res1['success'] === false) && 
             ($res1['error_code'] === 'MISSING_API_KEY') && 
             empty($res1['questions']);
    $runner->assertTrue("TEST 1: Missing API Key Returns Failure (No Production Mock)", $pass1, "error_code: " . ($res1['error_code'] ?? 'N/A'));

    // --- TEST 2: Invalid Key Format Returns Failure ---
    $res2 = GroqService::generateQuestions($sampleLessonText, 5, 'Soil Mechanics', 'Test Exam', 'Geotechnical', 'multiple_choice', 'medium', 'invalid_key_prefix');
    $pass2 = (isset($res2['success']) && $res2['success'] === false) && 
             ($res2['error_code'] === 'INVALID_API_KEY') && 
             empty($res2['questions']);
    $runner->assertTrue("TEST 2: Invalid Key Format (No gsk_ prefix) Returns Failure", $pass2, "error_code: " . ($res2['error_code'] ?? 'N/A'));

    // --- TEST 3: Invalid API Key HTTP 401 Returns Failure ---
    $res3 = GroqService::generateQuestions($sampleLessonText, 5, 'Soil Mechanics', 'Test Exam', 'Geotechnical', 'multiple_choice', 'medium', 'gsk_invalid_test_credentials_key_1234567890');
    $pass3 = (isset($res3['success']) && $res3['success'] === false) && 
             in_array($res3['error_code'], ['INVALID_API_KEY', 'PROVIDER_ERROR']) && 
             empty($res3['questions']);
    $runner->assertTrue("TEST 3: Invalid API Key Credentials Return Failure", $pass3, "error_code: " . ($res3['error_code'] ?? 'N/A') . ", status: " . ($res3['provider_status'] ?? 'N/A'));

    // --- TEST 4: Structured Failure Contract Structure ---
    $hasContractKeys = isset($res1['success']) && 
                        isset($res1['error_code']) && 
                        isset($res1['user_message']) && 
                        isset($res1['technical_message']) && 
                        isset($res1['retryable']) && 
                        isset($res1['provider_status']);
    $runner->assertTrue("TEST 4: Structured Failure Contract Keys Present", $hasContractKeys, "Contains error_code, user_message, technical_message, retryable, provider_status");

    // --- TEST 5: Explicit Test Mock Works ONLY in Testing Mode ---
    putenv('APP_ENV=testing');
    putenv('TEST_BOOTSTRAP_ACTIVE=1');
    $_ENV['APP_ENV'] = 'testing';
    $_ENV['TEST_BOOTSTRAP_ACTIVE'] = '1';
    $_SERVER['APP_ENV'] = 'testing';
    $_SERVER['TEST_BOOTSTRAP_ACTIVE'] = '1';
    require __DIR__ . '/../app/testing_bootstrap.php';
    $res5 = GroqService::generateQuestions("Lesson ID: 101\n" . $sampleLessonText, 5, 'Soil Mechanics', 'Test Exam', 'Geotechnical', 'multiple_choice', 'medium', 'TEST_MOCK_KEY');

    putenv('APP_ENV=production');
    putenv('TEST_BOOTSTRAP_ACTIVE=0');
    $_ENV['APP_ENV'] = 'production';
    $_ENV['TEST_BOOTSTRAP_ACTIVE'] = '0';
    $_SERVER['APP_ENV'] = 'production';
    $_SERVER['TEST_BOOTSTRAP_ACTIVE'] = '0';
    require __DIR__ . '/../app/testing_bootstrap.php';

    $pass5 = (isset($res5['success']) && $res5['success'] === true) && count($res5['questions'] ?? []) === 5;
    $runner->assertTrue("TEST 5: Explicit Test Mock Works ONLY in Testing Mode", $pass5, "Generated " . count($res5['questions'] ?? []) . " questions under testMode=true");

    // --- TEST 6: Zero Database Persistence on Generation Failure ---
    $stmtExamsBefore = $pdo->query("SELECT COUNT(*) FROM exams")->fetchColumn();
    $stmtQsBefore = $pdo->query("SELECT COUNT(*) FROM exam_questions")->fetchColumn();

    $resFail = GroqService::generateQuestions($sampleLessonText, 5, 'Soil Mechanics', 'Fail Exam', 'Geotechnical', 'multiple_choice', 'medium', 'invalid_key_prefix');

    $stmtExamsAfter = $pdo->query("SELECT COUNT(*) FROM exams")->fetchColumn();
    $stmtQsAfter = $pdo->query("SELECT COUNT(*) FROM exam_questions")->fetchColumn();

    $noPersistence = ($stmtExamsBefore === $stmtExamsAfter) && ($stmtQsBefore === $stmtQsAfter);
    $runner->assertTrue("TEST 6: Zero Database Records Persisted on AI Failure", $noPersistence, "Exams count unchanged: {$stmtExamsAfter}, Questions count: {$stmtQsAfter}");

} catch (Throwable $e) {
    $runner->recordException($e);
} finally {
    putenv('APP_ENV=testing');
    putenv('TEST_BOOTSTRAP_ACTIVE=1');
    $_ENV['APP_ENV'] = 'testing';
    $_ENV['TEST_BOOTSTRAP_ACTIVE'] = '1';
    $_SERVER['APP_ENV'] = 'testing';
    $_SERVER['TEST_BOOTSTRAP_ACTIVE'] = '1';
    GroqService::enableTestingModeFromBootstrap();
}

$runner->finish();
