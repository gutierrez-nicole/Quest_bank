<?php
/**
 * QUESTBANK — EPIC 2.2 TEST RUNNER CONTRACT & PROTOCOL META-VERIFICATION
 *
 * Proves that the standardized TestRunner, structured QUESTBANK_TEST_RESULT marker,
 * timeout handling, process control, and every authoritative Epic 2.2 verifier
 * return truthful status and exit codes under all required failure modes.
 *
 * Tests:
 *   PART A: Fixture-based contract & result protocol verification (12 scenarios)
 *   PART B: Authoritative verifier forced-failure injection (2 × N scripts)
 *   PART C: Structured marker schema & accuracy verification
 */

require_once __DIR__ . '/../tests/helpers/test_runner.php';
requireCorePreflight();

$runner = new TestRunner('QuestBank Epic 2.2 TestRunner Contract & Protocol Meta-Verification');

// Controlled failure hooks for meta-verification self-testing
if (getenv('FORCE_ASSERT_FAIL') === '1') {
    $runner->assertTrue("Forced Assertion Failure Test", false, "FORCE_ASSERT_FAIL=1");
    $runner->finish();
}
if (getenv('FORCE_RUNTIME_EXCEPTION') === '1') {
    try { throw new RuntimeException('FORCE_RUNTIME_EXCEPTION=1'); } catch (Throwable $e) { $runner->recordException($e); $runner->finish(); }
}


// Authoritative verifiers
$authoritativeScripts = [
    'verify_epic22_dependency_preflight.php',
    'verify_epic22_cli_exit_code_repair.php',
    'verify_epic22_mock_ai_isolation.php',
    'verify_epic22_mock_scenario_metadata.php',
    'verify_epic22_test_architecture.php',
    'verify_epic22_cross_period.php',
    'verify_epic22_no_mock.php',
    'verify_epic22_failed_chunk_audit.php',
    'verify_epic22_deterministic_scenarios.php',
    'verify_epic22_r2_pipeline.php',
    'verify_epic22_final_repairs.php',
    'verify_epic22_final_blockers.php',
    'verify_epic22_final_security_repair.php',
    'verify_epic22_final_production_repair.php',
    'verify_epic22_full_pipeline.php',
    'verify_epic22_test_runner_contract.php',
];


$fixturePath = __DIR__ . '/../tests/fixtures/test_runner_fixture.php';
$scriptDir = __DIR__;

try {
    $runner->setSetupCompleted(true, 'Meta-verification environment initialized');

    // =========================================================================
    // PART A: Fixture-based TestRunner Contract & Protocol Verification
    // =========================================================================
    echo "\n--- PART A: TestRunner Contract & Result Protocol (12 Scenarios) ---\n";

    // A1: Success mode -> pass, exit 0
    $a1 = runVerifierWithTimeout($fixturePath, [], 10);
    $runner->assertTrue(
        "A1: Normal Child Success -> Passed & Exit 0",
        $a1['passed'] === true
            && $a1['exit_code'] === 0
            && ($a1['result_marker']['status'] ?? '') === 'pass'
            && ($a1['result_marker']['passed'] ?? 0) === 3,
        "Exit: {$a1['exit_code']}, Status: " . ($a1['result_marker']['status'] ?? 'NONE')
    );

    // A2: Assertion failure -> fail, exit 1
    $a2 = runVerifierWithTimeout($fixturePath, ['FORCE_ASSERT_FAIL' => '1'], 10);
    $runner->assertTrue(
        "A2: Assertion Failure -> Failed & Exit 1",
        $a2['passed'] === false
            && $a2['exit_code'] === 1
            && ($a2['result_marker']['status'] ?? '') === 'fail'
            && ($a2['result_marker']['failed'] ?? 0) >= 1,
        "Exit: {$a2['exit_code']}, Status: " . ($a2['result_marker']['status'] ?? 'NONE')
    );

    // A3: Runtime exception -> fail, exit 1
    $a3 = runVerifierWithTimeout($fixturePath, ['FORCE_RUNTIME_EXCEPTION' => '1'], 10);
    $runner->assertTrue(
        "A3: Runtime Exception -> Failed & Exit 1",
        $a3['passed'] === false
            && $a3['exit_code'] === 1
            && ($a3['result_marker']['runtime_exception'] ?? false) === true,
        "Exit: {$a3['exit_code']}, Exception Flag: " . var_export($a3['result_marker']['runtime_exception'] ?? null, true)
    );

    // A4: Cleanup failure -> fail, exit 1
    $a4 = runVerifierWithTimeout($fixturePath, ['FORCE_CLEANUP_FAILURE' => '1'], 10);
    $runner->assertTrue(
        "A4: Cleanup Failure -> Failed & Exit 1",
        $a4['passed'] === false
            && $a4['exit_code'] === 1
            && ($a4['result_marker']['cleanup_exception'] ?? false) === true,
        "Exit: {$a4['exit_code']}, Cleanup Exception Flag: " . var_export($a4['result_marker']['cleanup_exception'] ?? null, true)
    );

    // A5: No assertions -> fail, exit 1
    $a5 = runVerifierWithTimeout($fixturePath, ['FORCE_NO_ASSERTIONS' => '1'], 10);
    $runner->assertTrue(
        "A5: No Assertions Executed -> Failed & Exit 1",
        $a5['passed'] === false
            && $a5['exit_code'] === 1
            && ($a5['result_marker']['assertions'] ?? -1) === 0,
        "Exit: {$a5['exit_code']}, Assertions: " . ($a5['result_marker']['assertions'] ?? '?')
    );

    // A6: Setup failure -> fail, exit 1
    $a6 = runVerifierWithTimeout($fixturePath, ['FORCE_SETUP_FAILURE' => '1'], 10);
    $runner->assertTrue(
        "A6: Setup Failure -> Failed & Exit 1",
        $a6['passed'] === false
            && $a6['exit_code'] === 1
            && ($a6['result_marker']['setup_completed'] ?? true) === false,
        "Exit: {$a6['exit_code']}, Setup Completed: " . var_export($a6['result_marker']['setup_completed'] ?? null, true)
    );

    // A7: Exception text in output with structured pass -> passed === true
    $a7 = runVerifierWithTimeout($fixturePath, ['FORCE_EXCEPTION_TEXT_PASS' => '1'], 10);
    $runner->assertTrue(
        "A7: Exception Text in Output + Structured Pass -> Passed",
        $a7['passed'] === true
            && $a7['exit_code'] === 0
            && ($a7['result_marker']['status'] ?? '') === 'pass'
            && strpos($a7['stderr'], 'EXCEPTION') !== false,
        "Passed: " . ($a7['passed'] ? 'YES' : 'NO') . ", EXCEPTION text in STDERR: YES"
    );

    // A8: Malformed result marker JSON -> fail
    $a8 = runVerifierWithTimeout($fixturePath, ['FORCE_MALFORMED_MARKER' => '1'], 10);
    $runner->assertTrue(
        "A8: Malformed Result Marker JSON -> Failed",
        $a8['passed'] === false
            && $a8['marker_error'] === 'MALFORMED_MARKER_JSON'
            && in_array('Malformed result marker JSON', $a8['fail_reasons']),
        "Error: {$a8['marker_error']}"
    );

    // A9: Missing result marker -> fail
    $a9 = runVerifierWithTimeout($fixturePath, ['FORCE_MISSING_MARKER' => '1'], 10);
    $runner->assertTrue(
        "A9: Missing Result Marker -> Failed",
        $a9['passed'] === false
            && $a9['marker_error'] === 'MISSING_MARKER'
            && in_array('Missing QUESTBANK_TEST_RESULT marker', $a9['fail_reasons']),
        "Error: {$a9['marker_error']}"
    );

    // A10: Child timeout handling -> timed_out === true
    $a10 = runVerifierWithTimeout($fixturePath, ['FORCE_TIMEOUT' => '1'], 1);
    $runner->assertTrue(
        "A10: Child Timeout Handling -> Marked Timed Out",
        $a10['passed'] === false
            && $a10['timed_out'] === true,
        "Timed Out: " . ($a10['timed_out'] ? 'YES' : 'NO') . ", Duration: {$a10['duration']}s"
    );

    // A11: Child process ignoring SIGTERM -> forced_killed === true
    $a11 = runVerifierWithTimeout($fixturePath, ['FORCE_SIGIGN_TIMEOUT' => '1'], 1);
    $runner->assertTrue(
        "A11: Process Ignoring SIGTERM -> Force Killed (SIGKILL)",
        $a11['passed'] === false
            && $a11['timed_out'] === true
            && $a11['forced_killed'] === true,
        "Forced Killed: " . ($a11['forced_killed'] ? 'YES' : 'NO') . ", Duration: {$a11['duration']}s"
    );

    // A12: Suite continues after one child fails -> sequential resilience
    $a12Fail = runVerifierWithTimeout($fixturePath, ['FORCE_ASSERT_FAIL' => '1'], 5);
    $a12Pass = runVerifierWithTimeout($fixturePath, [], 5);
    $runner->assertTrue(
        "A12: Suite Continues Execution After Child Failure",
        $a12Fail['passed'] === false && $a12Pass['passed'] === true,
        "First script failed, second script passed cleanly"
    );

    // =========================================================================
    // PART B: Authoritative verifier forced-failure injection
    // =========================================================================
    echo "\n--- PART B: Authoritative Verifier Forced-Failure Injection ---\n";

    foreach ($authoritativeScripts as $script) {
        $scriptPath = $scriptDir . '/' . $script;

        if (!file_exists($scriptPath)) {
            $runner->assertTrue("B-FAIL: {$script} -> File exists", false, "Script not found");
            continue;
        }

        // B.x.1: FORCE_ASSERT_FAIL -> exit 1
        $bAssert = runVerifierWithTimeout($scriptPath, ['FORCE_ASSERT_FAIL' => '1'], 10);
        $runner->assertTrue(
            "B-ASSERT: {$script} FORCE_ASSERT_FAIL -> Failed & Exit 1",
            $bAssert['passed'] === false && $bAssert['exit_code'] === 1,
            "Passed: " . ($bAssert['passed'] ? 'YES' : 'NO') . ", Exit: {$bAssert['exit_code']}"
        );

        // B.x.2: FORCE_RUNTIME_EXCEPTION -> exit 1
        $bRuntime = runVerifierWithTimeout($scriptPath, ['FORCE_RUNTIME_EXCEPTION' => '1'], 10);
        $runner->assertTrue(
            "B-RUNTIME: {$script} FORCE_RUNTIME_EXCEPTION -> Failed & Exit 1",
            $bRuntime['passed'] === false && $bRuntime['exit_code'] === 1,
            "Passed: " . ($bRuntime['passed'] ? 'YES' : 'NO') . ", Exit: {$bRuntime['exit_code']}"
        );
    }

    // =========================================================================
    // PART C: Structured marker schema & accuracy verification
    // =========================================================================
    echo "\n--- PART C: Structured Marker Schema & Accuracy ---\n";

    $c1 = runVerifierWithTimeout($fixturePath, [], 10);
    $marker = $c1['result_marker'];
    $runner->assertTrue(
        "C1: Structured marker contains required schema keys",
        is_array($marker)
            && isset($marker['status'])
            && isset($marker['passed'])
            && isset($marker['failed'])
            && isset($marker['skipped'])
            && isset($marker['assertions'])
            && isset($marker['setup_completed']),
        "Keys present: " . implode(', ', array_keys($marker ?? []))
    );

    $runner->assertTrue(
        "C2: Structured marker passed count matches actual assertions",
        ($marker['passed'] ?? -1) === 3
            && ($marker['failed'] ?? -1) === 0
            && ($marker['assertions'] ?? -1) === 3,
        "Passed: " . ($marker['passed'] ?? '?') . ", Assertions: " . ($marker['assertions'] ?? '?')
    );

    // =========================================================================
    // PART D: Verifier Inventory & Manifest Validation
    // =========================================================================
    echo "\n--- PART D: Verifier Inventory & Manifest Validation ---\n";

    $manifestFile = __DIR__ . '/epic22_verifiers.json';
    $runner->assertTrue(
        "D1: Manifest file epic22_verifiers.json exists",
        file_exists($manifestFile),
        "Path: {$manifestFile}"
    );

    $mData = json_decode(file_get_contents($manifestFile), true);
    $runner->assertTrue(
        "D2: Manifest is valid JSON with verifiers array",
        json_last_error() === JSON_ERROR_NONE && is_array($mData) && isset($mData['verifiers']),
        "Verifiers count: " . count($mData['verifiers'] ?? [])
    );

    $dFnList = [];
    $dDeprecatedValid = true;
    $dValidClassifications = true;

    foreach (($mData['verifiers'] ?? []) as $entry) {
        $fn = $entry['filename'] ?? '';
        $cls = $entry['classification'] ?? '';
        if (in_array($fn, $dFnList, true)) {
            $runner->assertTrue("D3: Unique filenames in manifest", false, "Duplicate: {$fn}");
        }
        $dFnList[] = $fn;

        if (!in_array($cls, ['authoritative', 'supporting', 'deprecated'], true)) {
            $dValidClassifications = false;
        }
        if ($cls === 'deprecated' && empty($entry['replacement'])) {
            $dDeprecatedValid = false;
        }
    }

    $runner->assertTrue(
        "D3: All manifest verifiers have valid classifications",
        $dValidClassifications,
        "Classifications must be authoritative, supporting, or deprecated"
    );

    $runner->assertTrue(
        "D4: All deprecated verifiers specify a replacement script",
        $dDeprecatedValid,
        "Deprecated entries must contain non-empty replacement"
    );

} catch (Throwable $e) {
    $runner->recordException($e);
}

$runner->finish();

