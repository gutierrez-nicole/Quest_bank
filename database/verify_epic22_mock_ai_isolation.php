<?php
/**
 * QUESTBANK — EPIC 2.2 CRITICAL MOCK-AI ISOLATION VERIFICATION
 *
 * Verifies strict boundary enforcing mock AI provider activation ONLY under:
 *   APP_ENV === 'testing' && self::$testMode === true && self::$testBootstrapActive === true
 */

require_once __DIR__ . '/../tests/helpers/test_runner.php';
requireAiPreflight();

require_once __DIR__ . '/../app/bootstrap.php';
require_once __DIR__ . '/../app/services/GroqService.php';

$runner = new TestRunner('Epic 2.2 Critical Mock-AI Isolation Verification');

// Controlled failure hooks for meta-verification
if (getenv('FORCE_ASSERT_FAIL') === '1') {
    $runner->assertTrue("Forced Assertion Failure Test", false, "FORCE_ASSERT_FAIL=1");
}
if (getenv('FORCE_RUNTIME_EXCEPTION') === '1') {
    try { throw new RuntimeException('FORCE_RUNTIME_EXCEPTION=1'); } catch (Throwable $e) { $runner->recordException($e); $runner->finish(); }
}

function setEnvVars($env, $bootstrapActive) {
    putenv("APP_ENV={$env}");
    putenv("TEST_BOOTSTRAP_ACTIVE={$bootstrapActive}");
    $_ENV['APP_ENV'] = $env;
    $_ENV['TEST_BOOTSTRAP_ACTIVE'] = (string)$bootstrapActive;
    $_SERVER['APP_ENV'] = $env;
    $_SERVER['TEST_BOOTSTRAP_ACTIVE'] = (string)$bootstrapActive;
    GroqService::enableTestingModeFromBootstrap();
}

try {
    $runner->setSetupCompleted(true, "Mock-AI isolation environment initialized");
    $sampleContent = "Lesson ID: 101\nPeriod: prelim\nTitle: Highway Engineering\nStopping sight distance calculation.";

    // 1. Production + normal title → real provider path
    setEnvVars('production', '0');
    $r1 = GroqService::generateQuestions($sampleContent, 5, 'Highway Engineering', 'Standard Production Exam', 'Transportation', 'multiple_choice', 'medium', 'TEST_MOCK_KEY');
    $p1 = (isset($r1['success']) && $r1['success'] === false && in_array($r1['error_code'] ?? '', ['MISSING_API_KEY', 'INVALID_API_KEY', 'PROVIDER_ERROR'], true));
    $runner->assertTrue("1. Production + normal title -> real provider path", $p1, "Result success=" . json_encode($r1['success'] ?? null) . ", error_code=" . ($r1['error_code'] ?? 'none'));

    // 2. Production + MOCK_MISSING_SOURCE title → mock not activated
    setEnvVars('production', '0');
    $r2 = GroqService::generateQuestions($sampleContent, 5, 'Highway Engineering', 'MOCK_MISSING_SOURCE Exam', 'Transportation', 'multiple_choice', 'medium', 'TEST_MOCK_KEY');
    $p2 = (isset($r2['success']) && $r2['success'] === false && in_array($r2['error_code'] ?? '', ['MISSING_API_KEY', 'INVALID_API_KEY', 'PROVIDER_ERROR'], true));
    $runner->assertTrue("2. Production + MOCK_MISSING_SOURCE title -> mock not activated", $p2, "Result success=" . json_encode($r2['success'] ?? null) . ", error_code=" . ($r2['error_code'] ?? 'none'));

    // 3. Production + MOCK_INCOMPLETE_BATCH title → mock not activated
    setEnvVars('production', '0');
    $r3 = GroqService::generateQuestions($sampleContent, 5, 'Highway Engineering', 'MOCK_INCOMPLETE_BATCH Exam', 'Transportation', 'multiple_choice', 'medium', 'TEST_MOCK_KEY');
    $p3 = (isset($r3['success']) && $r3['success'] === false && in_array($r3['error_code'] ?? '', ['MISSING_API_KEY', 'INVALID_API_KEY', 'PROVIDER_ERROR'], true));
    $runner->assertTrue("3. Production + MOCK_INCOMPLETE_BATCH title -> mock not activated", $p3, "Result success=" . json_encode($r3['success'] ?? null) . ", error_code=" . ($r3['error_code'] ?? 'none'));

    // 4. Production + MOCK_REFILL_MIDTERM title → mock not activated
    setEnvVars('production', '0');
    $r4 = GroqService::generateQuestions($sampleContent, 5, 'Highway Engineering', 'MOCK_REFILL_MIDTERM Exam', 'Transportation', 'multiple_choice', 'medium', 'TEST_MOCK_KEY');
    $p4 = (isset($r4['success']) && $r4['success'] === false && in_array($r4['error_code'] ?? '', ['MISSING_API_KEY', 'INVALID_API_KEY', 'PROVIDER_ERROR'], true));
    $runner->assertTrue("4. Production + MOCK_REFILL_MIDTERM title -> mock not activated", $p4, "Result success=" . json_encode($r4['success'] ?? null) . ", error_code=" . ($r4['error_code'] ?? 'none'));

    // 5. Production + Authoritative title → mock not activated
    setEnvVars('production', '0');
    $r5 = GroqService::generateQuestions($sampleContent, 5, 'Highway Engineering', 'Authoritative Final Assessment', 'Transportation', 'multiple_choice', 'medium', 'TEST_MOCK_KEY');
    $p5 = (isset($r5['success']) && $r5['success'] === false && in_array($r5['error_code'] ?? '', ['MISSING_API_KEY', 'INVALID_API_KEY', 'PROVIDER_ERROR'], true));
    $runner->assertTrue("5. Production + Authoritative title -> mock not activated", $p5, "Result success=" . json_encode($r5['success'] ?? null) . ", error_code=" . ($r5['error_code'] ?? 'none'));

    // 6. Development environment + marker → mock not activated
    setEnvVars('development', '1');
    $r6 = GroqService::generateQuestions($sampleContent, 5, 'Highway Engineering', 'MOCK_MISSING_SOURCE Dev Exam', 'Transportation', 'multiple_choice', 'medium', 'TEST_MOCK_KEY');
    $p6 = (isset($r6['success']) && $r6['success'] === false && in_array($r6['error_code'] ?? '', ['MISSING_API_KEY', 'INVALID_API_KEY', 'PROVIDER_ERROR'], true));
    $runner->assertTrue("6. Development environment + marker -> mock not activated", $p6, "Result success=" . json_encode($r6['success'] ?? null) . ", error_code=" . ($r6['error_code'] ?? 'none'));

    // 7. Testing without active bootstrap + marker → mock not activated
    setEnvVars('testing', '0');
    $r7 = GroqService::generateQuestions($sampleContent, 5, 'Highway Engineering', 'MOCK_MISSING_SOURCE Test Exam No Bootstrap', 'Transportation', 'multiple_choice', 'medium', 'TEST_MOCK_KEY');
    $p7 = (isset($r7['success']) && $r7['success'] === false && in_array($r7['error_code'] ?? '', ['MISSING_API_KEY', 'INVALID_API_KEY', 'PROVIDER_ERROR'], true));
    $runner->assertTrue("7. Testing without active bootstrap + marker -> mock not activated", $p7, "Result success=" . json_encode($r7['success'] ?? null) . ", error_code=" . ($r7['error_code'] ?? 'none'));

    // 8. Testing with active bootstrap + marker → deterministic scenario works
    setEnvVars('testing', '1');
    $r8 = GroqService::generateQuestions($sampleContent, 5, 'Highway Engineering', 'MOCK_MISSING_SOURCE Valid Test Exam', 'Transportation', 'multiple_choice', 'medium', 'TEST_MOCK_KEY');
    $p8 = (isset($r8['success']) && $r8['success'] === true && count($r8['questions'] ?? []) === 5);
    $runner->assertTrue("8. Testing with active bootstrap + marker -> deterministic scenario works", $p8, "Generated " . count($r8['questions'] ?? []) . " mock questions cleanly");

    // 9. Direct mutation attempt from a production route cannot activate mock mode
    setEnvVars('production', '0');
    GroqService::enableTestingModeFromBootstrap();
    $r9 = GroqService::generateQuestions($sampleContent, 5, 'Highway Engineering', 'MOCK_MISSING_SOURCE Direct Mutation Attempt', 'Transportation', 'multiple_choice', 'medium', 'TEST_MOCK_KEY');
    $p9 = (GroqService::isTestModeActive() === false && isset($r9['success']) && $r9['success'] === false);
    $runner->assertTrue("9. Direct mutation attempt from production route cannot activate mock mode", $p9, "isTestModeActive=" . (GroqService::isTestModeActive() ? 'true' : 'false'));

    // 10. Missing/invalid real API key still returns failure in production rather than mock questions
    setEnvVars('production', '0');
    $r10 = GroqService::generateQuestions($sampleContent, 5, 'Highway Engineering', 'MOCK_MISSING_SOURCE Production Invalid Key Test', 'Transportation', 'multiple_choice', 'medium', 'invalid_gsk_key_xyz_12345');
    $p10 = (isset($r10['success']) && $r10['success'] === false && !empty($r10['error_code']));
    $runner->assertTrue("10. Invalid real API key returns failure in production rather than mock questions", $p10, "error_code=" . ($r10['error_code'] ?? 'none'));

} catch (Throwable $e) {
    $runner->recordException($e);
} finally {
    setEnvVars('testing', '1');
}

$runner->finish();
