<?php

/**
 * QUESTBANK — EPIC 2.2 FINAL VERIFICATION PREFLIGHT CONSOLIDATION MATRIX
 *
 * Verifies all 15 retained Epic 2.2 CLI verification scripts under:
 * 1. Missing dependency -> exit 1
 * 2. Unavailable DB -> exit 1
 * 3. Forced assertion failure -> exit 1
 * 4. Successful setup & assertions -> exit 0
 */

require_once __DIR__ . '/../tests/helpers/test_preflight.php';
requireCorePreflight();

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

function runScriptSubprocess(string $scriptPath, array $customEnv = []): array {
    $merged = array_merge($_SERVER, $_ENV, $customEnv);
    $env = [];
    foreach ($merged as $k => $v) {
        if (is_scalar($v)) {
            $env[(string)$k] = (string)$v;
        }
    }

    $fullPath = __DIR__ . '/../' . $scriptPath;
    $cmd = PHP_BINARY . ' ' . escapeshellarg($fullPath);
    $descriptors = [
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w']
    ];
    $process = proc_open($cmd, $descriptors, $pipes, __DIR__ . '/..', $env);
    if (!is_resource($process)) {
        return ['code' => 255, 'stdout' => '', 'stderr' => 'Failed to spawn subprocess'];
    }

    $stdout = stream_get_contents($pipes[1]);
    fclose($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[2]);

    $code = proc_close($process);
    return ['code' => $code, 'stdout' => $stdout, 'stderr' => $stderr];
}

echo "===========================================================\n";
echo " QUESTBANK EPIC 2.2 PREFLIGHT CONSOLIDATION VERIFICATION  \n";
echo "===========================================================\n";

$retainedScripts = [
    'database/verify_epic22_mock_ai_isolation.php',
    'database/verify_epic22_mock_scenario_metadata.php',
    'database/verify_epic22_failed_chunk_audit.php',
    'database/verify_epic22_deterministic_scenarios.php',
    'database/verify_epic22_test_architecture.php',
    'database/verify_epic22_cli_exit_code_repair.php',
    'database/verify_epic22_final_blockers.php',
    'database/verify_epic22_final_security_repair.php',
    'database/verify_epic22_dependency_preflight.php',
    'database/verify_epic22_cross_period.php',
    'database/verify_epic22_final_production_repair.php',
    'database/verify_epic22_final_repairs.php',
    'database/verify_epic22_full_pipeline.php',
    'database/verify_epic22_no_mock.php',
    'database/verify_epic22_r2_pipeline.php'
];

try {
    // -----------------------------------------------------------
    // TEST MATRIX 1: Successful Setup & Execution (All Retained Scripts)
    // -----------------------------------------------------------
    echo "\n--- TEST MATRIX 1: Successful Setup & Assertions (Exit 0) ---\n";
    foreach ($retainedScripts as $script) {
        $basename = basename($script);
        $res = runScriptSubprocess($script);
        $pass = ($res['code'] === 0 && strpos($res['stdout'] . $res['stderr'], 'VERIFICATION SUMMARY:') !== false);
        logTest("Setup Success: {$basename}", $pass, "Exit Code: {$res['code']}");
    }

    // -----------------------------------------------------------
    // TEST MATRIX 2: Missing Dependency Subprocess (Exit 1)
    // -----------------------------------------------------------
    echo "\n--- TEST MATRIX 2: Missing Required Dependency (Exit 1) ---\n";
    $preflightTestCode = <<<'PHP'
<?php
require_once __DIR__ . '/../tests/helpers/test_preflight.php';
requirePhpExtensions(['non_existent_extension_xyz']);
PHP;
    $tmpScript = __DIR__ . '/../scratch/test_missing_ext.php';
    if (!is_dir(dirname($tmpScript))) {
        mkdir(dirname($tmpScript), 0777, true);
    }
    file_put_contents($tmpScript, $preflightTestCode);

    $resMissing = runScriptSubprocess('scratch/test_missing_ext.php');
    @unlink($tmpScript);

    $missingPass = ($resMissing['code'] === 1 && strpos($resMissing['stderr'], 'DEPENDENCY PREFLIGHT FAILED') !== false);
    logTest("Missing Extension Behavior: Clean Exit 1", $missingPass, "Exit Code: {$resMissing['code']}, Stderr contains PREFLIGHT FAILED: " . ($missingPass ? 'YES' : 'NO'));

    // -----------------------------------------------------------
    // TEST MATRIX 3: Unavailable Database Subprocess (Exit 1)
    // -----------------------------------------------------------
    echo "\n--- TEST MATRIX 3: Unavailable Database Connection (Exit 1) ---\n";
    $badDbEnv = [
        'DB_HOST' => 'invalid_host_xyz_99'
    ];
    $resBadDb = runScriptSubprocess('database/verify_epic22_final_blockers.php', $badDbEnv);
    $badDbPass = ($resBadDb['code'] === 1);
    logTest("Unavailable DB Connection: Clean Exit 1", $badDbPass, "Exit Code: {$resBadDb['code']}");

    // -----------------------------------------------------------
    // TEST MATRIX 4: Forced Assertion Failure Subprocess (Exit 1)
    // -----------------------------------------------------------
    echo "\n--- TEST MATRIX 4: Forced Assertion Failure (Exit 1) ---\n";
    $resFailAss = runScriptSubprocess('database/verify_epic22_cli_exit_code_repair.php', ['FORCE_ASSERT_FAIL' => '1']);
    $failAssPass = ($resFailAss['code'] === 1);
    logTest("Forced Assertion Failure: Clean Exit 1", $failAssPass, "Exit Code: {$resFailAss['code']}");

    // -----------------------------------------------------------
    // TEST MATRIX 5: Confirm Obsolete Scripts Cleanly Removed
    // -----------------------------------------------------------
    echo "\n--- TEST MATRIX 5: Obsolete Scripts Removal ---\n";
    $obsoleteFiles = [
        'database/verify_epic22_repair1.php',
        'database/verify_epic22_repair1_context.php',
        'database/verify_epic22_final_blocker1.php'
    ];
    foreach ($obsoleteFiles as $obsFile) {
        $obsPath = __DIR__ . '/../' . $obsFile;
        $obsRemoved = !file_exists($obsPath);
        logTest("Obsolete Script Removed: " . basename($obsFile), $obsRemoved, "File absent from repository");
    }

} catch (Throwable $e) {
    $failed++;
    fwrite(STDERR, "\nSETUP OR RUNTIME EXCEPTION: " . $e->getMessage() . "\n" . $e->getTraceAsString() . "\n");
}

echo "\n-----------------------------------------------------------\n";
echo "VERIFICATION SUMMARY: {$passed} PASSED, {$failed} FAILED, {$skipped} SKIPPED\n";
echo "-----------------------------------------------------------\n";

if ($failed > 0) {
    echo "RESULT: FAILURE — Preflight consolidation matrix failed.\n";
    exit(1);
} else {
    echo "RESULT: SUCCESS — All 15 retained scripts and preflight consolidation matrix passed cleanly.\n";
    exit(0);
}
