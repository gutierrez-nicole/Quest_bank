<?php
/**
 * QUESTBANK — EPIC 2.2 PREFLIGHT CONSOLIDATION & META-VERIFICATION
 *
 * Tests all retained authoritative verifiers under multiple failure conditions:
 * 1. Normal successful environment (exit 0)
 * 2. Forced assertion failure via FORCE_ASSERT_FAIL=1 (exit 1)
 * 3. Missing dependency simulation (exit 1)
 * 4. Obsolete scripts removal check
 * 5. Exception-leak detection: no script prints EXCEPTION with exit 0
 */

require_once __DIR__ . '/../tests/helpers/test_runner.php';
requireCorePreflight();

$runner = new TestRunner('QuestBank Epic 2.2 Preflight Consolidation & Meta-Verification');

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
];

$obsoleteScripts = [
    'verify_epic22_repair1.php',
    'verify_epic22_repair1_context.php',
    'verify_epic22_final_blocker1.php',
];

function runScript($path, $envOverrides = []) {
    $env = array_merge(getenv(), [
        'APP_ENV' => 'testing',
        'TEST_BOOTSTRAP_ACTIVE' => '1',
    ], $envOverrides);

    $descriptorspec = [
        0 => ["pipe", "r"],
        1 => ["pipe", "w"],
        2 => ["pipe", "w"]
    ];

    $cmd = 'php ' . escapeshellarg($path);
    $process = proc_open($cmd, $descriptorspec, $pipes, dirname(dirname($path)), $env);

    if (!is_resource($process)) {
        return ['code' => -1, 'stdout' => '', 'stderr' => 'Failed to spawn'];
    }

    fclose($pipes[0]);
    $stdout = stream_get_contents($pipes[1]);
    fclose($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[2]);

    return ['code' => proc_close($process), 'stdout' => $stdout, 'stderr' => $stderr];
}

try {
    $runner->setSetupCompleted(true, "Meta-verification environment initialized");

    $scriptDir = __DIR__;

    // --- MATRIX 1: Successful Setup & Assertions (Exit 0) ---
    echo "\n--- TEST MATRIX 1: Successful Setup & Assertions (Exit 0) ---\n";
    foreach ($authoritativeScripts as $script) {
        $path = $scriptDir . '/' . $script;
        $res = runScript($path);
        $hasException = (strpos($res['stdout'], 'EXCEPTION') !== false || strpos($res['stderr'], 'EXCEPTION') !== false);
        $runner->assertTrue(
            "Setup Success: {$script}",
            $res['code'] === 0 && !$hasException,
            "Exit Code: {$res['code']}" . ($hasException ? " (WARNING: exception output detected!)" : "")
        );
    }

    // --- MATRIX 2: Missing Required Dependency (Exit 1) ---
    echo "\n--- TEST MATRIX 2: Missing Required Dependency (Exit 1) ---\n";
    $missingExtCmd = 'php -r "require \'tests/helpers/test_preflight.php\'; requirePhpExtensions([\'fake_nonexistent_ext\']);" 2>&1; echo "EXIT:$?"';
    $missingOut = shell_exec("cd " . escapeshellarg(dirname($scriptDir)) . " && " . $missingExtCmd);
    $missingExitCode = 1;
    if (preg_match('/EXIT:(\d+)/', $missingOut, $m)) {
        $missingExitCode = intval($m[1]);
    }
    $hasPreflight = strpos($missingOut, 'PREFLIGHT FAILED') !== false || strpos($missingOut, 'is NOT loaded') !== false;
    $runner->assertTrue(
        "Missing Extension Behavior: Clean Exit 1",
        $missingExitCode === 1 && $hasPreflight,
        "Exit Code: {$missingExitCode}, Stderr contains PREFLIGHT FAILED: " . ($hasPreflight ? 'YES' : 'NO')
    );

    // --- MATRIX 3: Unavailable Database Connection (Exit 1) ---
    echo "\n--- TEST MATRIX 3: Unavailable Database Connection (Exit 1) ---\n";
    $dbTestScript = $scriptDir . '/verify_epic22_cross_period.php';
    $dbRes = runScript($dbTestScript, ['DB_HOST' => '127.0.0.1:99999']);
    $runner->assertTrue(
        "Unavailable DB Connection: Clean Exit 1",
        $dbRes['code'] === 1,
        "Exit Code: {$dbRes['code']}"
    );

    // --- MATRIX 4: Forced Assertion Failure (Exit 1) ---
    echo "\n--- TEST MATRIX 4: Forced Assertion Failure (Exit 1) ---\n";
    $forceFailScript = $scriptDir . '/verify_epic22_cli_exit_code_repair.php';
    $forceRes = runScript($forceFailScript, ['FORCE_ASSERT_FAIL' => '1']);
    $runner->assertTrue(
        "Forced Assertion Failure: Clean Exit 1",
        $forceRes['code'] === 1,
        "Exit Code: {$forceRes['code']}"
    );

    // --- MATRIX 5: Exception-Leak Detection ---
    echo "\n--- TEST MATRIX 5: Exception-Leak Detection ---\n";
    $leaksDetected = 0;
    foreach ($authoritativeScripts as $script) {
        $path = $scriptDir . '/' . $script;
        $res = runScript($path);
        if ($res['code'] === 0) {
            $hasException = (strpos($res['stdout'], 'EXCEPTION') !== false);
            if ($hasException) {
                $leaksDetected++;
                echo "  [LEAK] {$script}: printed EXCEPTION but exited 0\n";
            }
        }
    }
    $runner->assertTrue(
        "No Exception-Leak in Any Passing Script",
        $leaksDetected === 0,
        "Scripts with exception leaks: {$leaksDetected}"
    );

    // --- MATRIX 6: Obsolete Scripts Removal ---
    echo "\n--- TEST MATRIX 6: Obsolete Scripts Removal ---\n";
    foreach ($obsoleteScripts as $obs) {
        $obsPath = $scriptDir . '/' . $obs;
        $runner->assertTrue(
            "Obsolete Script Removed: {$obs}",
            !file_exists($obsPath),
            "File absent from repository"
        );
    }

} catch (Throwable $e) {
    $runner->recordException($e);
}

$runner->finish();
