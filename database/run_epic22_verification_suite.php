<?php
/**
 * QUESTBANK — EPIC 2.2 AUTHORITATIVE VERIFICATION SUITE RUNNER
 *
 * Discovers and runs all authoritative Epic 2.2 verification scripts.
 * Returns exit code 0 only when ALL scripts pass.
 * Returns exit code 1 if ANY script fails.
 */

echo "===================================================================\n";
echo "  QUESTBANK EPIC 2.2 — AUTHORITATIVE VERIFICATION SUITE RUNNER    \n";
echo "===================================================================\n\n";

// Authoritative scripts list (ordered by dependency depth)
$authoritativeScripts = [
    'verify_epic22_dependency_preflight.php'      => 'Dependency Preflight',
    'verify_epic22_cli_exit_code_repair.php'       => 'CLI Exit-Code Repair',
    'verify_epic22_mock_ai_isolation.php'           => 'Mock-AI Isolation',
    'verify_epic22_mock_scenario_metadata.php'      => 'Mock Scenario Metadata',
    'verify_epic22_test_architecture.php'           => 'Test-Mode Architecture',
    'verify_epic22_cross_period.php'                => 'Cross-Period Lesson Pool',
    'verify_epic22_no_mock.php'                     => 'No Mock AI Fallback',
    'verify_epic22_failed_chunk_audit.php'          => 'Failed Chunk Audit Accuracy',
    'verify_epic22_deterministic_scenarios.php'     => 'Deterministic Mock Scenarios',
    'verify_epic22_r2_pipeline.php'                 => 'Round 2 Refinements',
    'verify_epic22_final_repairs.php'               => 'Final Repairs 2-6',
    'verify_epic22_final_blockers.php'              => 'Final Repairs 9-12',
    'verify_epic22_final_security_repair.php'       => 'Final Security Repair',
    'verify_epic22_final_production_repair.php'     => 'Final Production Repair',
    'verify_epic22_full_pipeline.php'               => 'Full Pipeline',
];

$totalScripts = count($authoritativeScripts);
$passedScripts = 0;
$failedScripts = 0;
$failedNames = [];
$results = [];

$scriptDir = __DIR__;

foreach ($authoritativeScripts as $filename => $label) {
    $scriptPath = $scriptDir . '/' . $filename;

    if (!file_exists($scriptPath)) {
        echo "  [\033[31mMISSING\033[0m] {$label} ({$filename})\n";
        echo "           -> Script file not found at {$scriptPath}\n";
        $failedScripts++;
        $failedNames[] = $filename;
        $results[] = ['script' => $filename, 'label' => $label, 'exit_code' => -1, 'status' => 'MISSING'];
        continue;
    }

    // Run as child process to isolate state
    $descriptorspec = [
        0 => ["pipe", "r"],
        1 => ["pipe", "w"],
        2 => ["pipe", "w"]
    ];

    $env = array_merge(getenv(), [
        'APP_ENV' => 'testing',
        'TEST_BOOTSTRAP_ACTIVE' => '1',
    ]);

    $cmd = 'php ' . escapeshellarg($scriptPath);
    $process = proc_open($cmd, $descriptorspec, $pipes, dirname($scriptDir), $env);

    if (!is_resource($process)) {
        echo "  [\033[31mERROR\033[0m]   {$label} ({$filename})\n";
        echo "           -> Failed to spawn child process\n";
        $failedScripts++;
        $failedNames[] = $filename;
        $results[] = ['script' => $filename, 'label' => $label, 'exit_code' => -1, 'status' => 'SPAWN_FAILED'];
        continue;
    }

    fclose($pipes[0]);
    $stdout = stream_get_contents($pipes[1]);
    fclose($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[2]);
    $exitCode = proc_close($process);

    // Extract summary line from stdout
    $summaryLine = '';
    if (preg_match('/VERIFICATION SUMMARY: (.+)/', $stdout, $matches)) {
        $summaryLine = trim($matches[1]);
    }

    // Check for exception leaks (exception printed but exit 0)
    $hasExceptionOutput = (strpos($stdout, 'EXCEPTION') !== false || strpos($stderr, 'EXCEPTION') !== false);

    if ($exitCode === 0 && !$hasExceptionOutput) {
        $passedScripts++;
        echo "  [\033[32mPASS\033[0m]    {$label}\n";
        echo "           -> {$summaryLine}\n";
        $results[] = ['script' => $filename, 'label' => $label, 'exit_code' => $exitCode, 'status' => 'PASS', 'summary' => $summaryLine];
    } else {
        $failedScripts++;
        $failedNames[] = $filename;

        if ($exitCode === 0 && $hasExceptionOutput) {
            echo "  [\033[31mFAIL\033[0m]    {$label} (exception leaked with exit 0!)\n";
            $results[] = ['script' => $filename, 'label' => $label, 'exit_code' => $exitCode, 'status' => 'EXCEPTION_LEAK'];
        } else {
            echo "  [\033[31mFAIL\033[0m]    {$label}\n";
            $results[] = ['script' => $filename, 'label' => $label, 'exit_code' => $exitCode, 'status' => 'FAIL', 'summary' => $summaryLine];
        }

        echo "           -> Exit Code: {$exitCode}, Summary: {$summaryLine}\n";

        // Print last few lines of stderr if present
        if (!empty($stderr)) {
            $stderrLines = array_slice(explode("\n", trim($stderr)), -3);
            foreach ($stderrLines as $line) {
                echo "           -> STDERR: {$line}\n";
            }
        }
    }
}

echo "\n===================================================================\n";
echo "SUITE RESULTS: {$passedScripts}/{$totalScripts} PASSED, {$failedScripts}/{$totalScripts} FAILED\n";
echo "===================================================================\n";

if ($failedScripts > 0) {
    echo "\nFailed Scripts:\n";
    foreach ($failedNames as $fn) {
        echo "  - {$fn}\n";
    }
    echo "\nRESULT: SUITE FAILURE — {$failedScripts} script(s) failed.\n";
    exit(1);
} else {
    echo "\nRESULT: SUITE SUCCESS — All {$totalScripts} authoritative Epic 2.2 verification scripts passed.\n";
    exit(0);
}
