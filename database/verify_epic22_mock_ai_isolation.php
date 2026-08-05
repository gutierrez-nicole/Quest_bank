<?php
/**
 * QUESTBANK — EPIC 2.2 CRITICAL MOCK-AI ISOLATION VERIFICATION
 *
 * Verifies strict boundary enforcing mock AI provider activation ONLY under:
 *   APP_ENV === 'testing' && self::$testMode === true && self::$testBootstrapActive === true
 *
 * Verification Checklist (10 Tests):
 * 1. Production + normal title → real provider path (rejects mock).
 * 2. Production + MOCK_MISSING_SOURCE title → mock not activated.
 * 3. Production + MOCK_INCOMPLETE_BATCH title → mock not activated.
 * 4. Production + MOCK_REFILL_MIDTERM title → mock not activated.
 * 5. Production + Authoritative title → mock not activated.
 * 6. Development environment + marker → mock not activated.
 * 7. Testing without active bootstrap + marker → mock not activated.
 * 8. Testing with active bootstrap + marker → deterministic scenario works.
 * 9. Direct mutation attempt from a production route cannot activate mock mode.
 * 10. Missing/invalid real API key still returns failure in production rather than mock questions.
 */

require_once __DIR__ . '/../app/bootstrap.php';
require_once __DIR__ . '/../app/services/GroqService.php';

$passed = 0;
$failed = 0;

function logTest($name, $success, $detail = '') {
    global $passed, $failed;
    if ($success) {
        $passed++;
        echo "  \033[32m[PASS]\033[0m {$name}\n";
        if ($detail) echo "         -> {$detail}\n";
    } else {
        $failed++;
        echo "  \033[31m[FAIL]\033[0m {$name}\n";
        if ($detail) echo "         -> {$detail}\n";
    }
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

echo "===========================================================\n";
echo " QUESTBANK EPIC 2.2 CRITICAL MOCK-AI ISOLATION VERIFICATION \n";
echo "===========================================================\n\n";

$sampleContent = "Lesson ID: 101\nPeriod: prelim\nTitle: Highway Engineering\nStopping sight distance calculation.";

// 1. Production + normal title → real provider path
setEnvVars('production', '0');
$r1 = GroqService::generateQuestions($sampleContent, 5, 'Highway Engineering', 'Standard Production Exam', 'Transportation', 'multiple_choice', 'medium', 'TEST_MOCK_KEY');
$p1 = (isset($r1['success']) && $r1['success'] === false && in_array($r1['error_code'] ?? '', ['MISSING_API_KEY', 'INVALID_API_KEY', 'PROVIDER_ERROR'], true));
logTest("1. Production + normal title -> real provider path", $p1, "Result success=" . json_encode($r1['success'] ?? null) . ", error_code=" . ($r1['error_code'] ?? 'none'));

// 2. Production + MOCK_MISSING_SOURCE title → mock not activated
setEnvVars('production', '0');
$r2 = GroqService::generateQuestions($sampleContent, 5, 'Highway Engineering', 'MOCK_MISSING_SOURCE Exam', 'Transportation', 'multiple_choice', 'medium', 'TEST_MOCK_KEY');
$p2 = (isset($r2['success']) && $r2['success'] === false && in_array($r2['error_code'] ?? '', ['MISSING_API_KEY', 'INVALID_API_KEY', 'PROVIDER_ERROR'], true));
logTest("2. Production + MOCK_MISSING_SOURCE title -> mock not activated", $p2, "Result success=" . json_encode($r2['success'] ?? null) . ", error_code=" . ($r2['error_code'] ?? 'none'));

// 3. Production + MOCK_INCOMPLETE_BATCH title → mock not activated
setEnvVars('production', '0');
$r3 = GroqService::generateQuestions($sampleContent, 5, 'Highway Engineering', 'MOCK_INCOMPLETE_BATCH Exam', 'Transportation', 'multiple_choice', 'medium', 'TEST_MOCK_KEY');
$p3 = (isset($r3['success']) && $r3['success'] === false && in_array($r3['error_code'] ?? '', ['MISSING_API_KEY', 'INVALID_API_KEY', 'PROVIDER_ERROR'], true));
logTest("3. Production + MOCK_INCOMPLETE_BATCH title -> mock not activated", $p3, "Result success=" . json_encode($r3['success'] ?? null) . ", error_code=" . ($r3['error_code'] ?? 'none'));

// 4. Production + MOCK_REFILL_MIDTERM title → mock not activated
setEnvVars('production', '0');
$r4 = GroqService::generateQuestions($sampleContent, 5, 'Highway Engineering', 'MOCK_REFILL_MIDTERM Exam', 'Transportation', 'multiple_choice', 'medium', 'TEST_MOCK_KEY');
$p4 = (isset($r4['success']) && $r4['success'] === false && in_array($r4['error_code'] ?? '', ['MISSING_API_KEY', 'INVALID_API_KEY', 'PROVIDER_ERROR'], true));
logTest("4. Production + MOCK_REFILL_MIDTERM title -> mock not activated", $p4, "Result success=" . json_encode($r4['success'] ?? null) . ", error_code=" . ($r4['error_code'] ?? 'none'));

// 5. Production + Authoritative title → mock not activated
setEnvVars('production', '0');
$r5 = GroqService::generateQuestions($sampleContent, 5, 'Highway Engineering', 'Authoritative Final Assessment', 'Transportation', 'multiple_choice', 'medium', 'TEST_MOCK_KEY');
$p5 = (isset($r5['success']) && $r5['success'] === false && in_array($r5['error_code'] ?? '', ['MISSING_API_KEY', 'INVALID_API_KEY', 'PROVIDER_ERROR'], true));
logTest("5. Production + Authoritative title -> mock not activated", $p5, "Result success=" . json_encode($r5['success'] ?? null) . ", error_code=" . ($r5['error_code'] ?? 'none'));

// 6. Development environment + marker → mock not activated
setEnvVars('development', '1');
$r6 = GroqService::generateQuestions($sampleContent, 5, 'Highway Engineering', 'MOCK_MISSING_SOURCE Dev Exam', 'Transportation', 'multiple_choice', 'medium', 'TEST_MOCK_KEY');
$p6 = (isset($r6['success']) && $r6['success'] === false && in_array($r6['error_code'] ?? '', ['MISSING_API_KEY', 'INVALID_API_KEY', 'PROVIDER_ERROR'], true));
logTest("6. Development environment + marker -> mock not activated", $p6, "Result success=" . json_encode($r6['success'] ?? null) . ", error_code=" . ($r6['error_code'] ?? 'none'));

// 7. Testing without active bootstrap + marker → mock not activated
setEnvVars('testing', '0');
$r7 = GroqService::generateQuestions($sampleContent, 5, 'Highway Engineering', 'MOCK_MISSING_SOURCE Test Exam No Bootstrap', 'Transportation', 'multiple_choice', 'medium', 'TEST_MOCK_KEY');
$p7 = (isset($r7['success']) && $r7['success'] === false && in_array($r7['error_code'] ?? '', ['MISSING_API_KEY', 'INVALID_API_KEY', 'PROVIDER_ERROR'], true));
logTest("7. Testing without active bootstrap + marker -> mock not activated", $p7, "Result success=" . json_encode($r7['success'] ?? null) . ", error_code=" . ($r7['error_code'] ?? 'none'));

// 8. Testing with active bootstrap + marker → deterministic scenario works
setEnvVars('testing', '1');
$r8 = GroqService::generateQuestions($sampleContent, 5, 'Highway Engineering', 'MOCK_MISSING_SOURCE Valid Test Exam', 'Transportation', 'multiple_choice', 'medium', 'TEST_MOCK_KEY');
$p8 = (isset($r8['success']) && $r8['success'] === true && count($r8['questions'] ?? []) === 5);
logTest("8. Testing with active bootstrap + marker -> deterministic scenario works", $p8, "Generated " . count($r8['questions'] ?? []) . " mock questions cleanly");

// 9. Direct mutation attempt from a production route cannot activate mock mode
setEnvVars('production', '0');
// Attempt mutation by calling enableTestingModeFromBootstrap while in production
GroqService::enableTestingModeFromBootstrap();
$r9 = GroqService::generateQuestions($sampleContent, 5, 'Highway Engineering', 'MOCK_MISSING_SOURCE Direct Mutation Attempt', 'Transportation', 'multiple_choice', 'medium', 'TEST_MOCK_KEY');
$p9 = (GroqService::isTestModeActive() === false && isset($r9['success']) && $r9['success'] === false);
logTest("9. Direct mutation attempt from production route cannot activate mock mode", $p9, "isTestModeActive=" . (GroqService::isTestModeActive() ? 'true' : 'false'));

// 10. Missing/invalid real API key still returns failure in production rather than mock questions
setEnvVars('production', '0');
$r10 = GroqService::generateQuestions($sampleContent, 5, 'Highway Engineering', 'MOCK_MISSING_SOURCE Production Invalid Key Test', 'Transportation', 'multiple_choice', 'medium', 'invalid_gsk_key_xyz_12345');
$p10 = (isset($r10['success']) && $r10['success'] === false && !empty($r10['error_code']));
logTest("10. Invalid real API key returns failure in production rather than mock questions", $p10, "error_code=" . ($r10['error_code'] ?? 'none'));

// Reset back to testing bootstrap for subsequent suite runs
setEnvVars('testing', '1');

echo "\n-----------------------------------------------------------\n";
echo "VERIFICATION SUMMARY: {$passed} PASSED, {$failed} FAILED\n";
echo "-----------------------------------------------------------\n";

if ($passed === 10 && $failed === 0) {
    echo "RESULT: SUCCESS — All 10 critical mock-AI isolation assertions passed cleanly.\n";
    exit(0);
} else {
    echo "RESULT: FAILURE — Mock-AI isolation verification failed.\n";
    exit(1);
}
