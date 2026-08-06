<?php

/**
 * Standardized Test Runner for QuestBank CLI Verification Scripts
 */

require_once __DIR__ . '/test_preflight.php';

class TestRunner {
    private string $title;
    private int $passed = 0;
    private int $failed = 0;
    private int $skipped = 0;
    private int $assertionsCount = 0;
    private bool $setupCompleted = false;
    private ?Throwable $runtimeException = null;
    private ?Throwable $cleanupException = null;

    public function __construct(string $title) {
        $this->title = $title;
        echo "===========================================================\n";
        echo " " . str_pad($this->title, 57, " ", STR_PAD_BOTH) . " \n";
        echo "===========================================================\n";
    }

    public function setSetupCompleted(bool $status = true, string $details = ''): void {
        $this->setupCompleted = $status;
        if ($status) {
            $this->logTest("Setup: Environment & Database Connection Established", true, $details);
        } else {
            $this->failed++;
            $this->logTest("Setup Failed", false, $details);
        }
    }

    public function assertTrue(string $description, bool $condition, string $details = ''): bool {
        $this->assertionsCount++;
        if ($condition) {
            $this->passed++;
            $this->logTest($description, true, $details);
            return true;
        } else {
            $this->failed++;
            $this->logTest($description, false, $details);
            return false;
        }
    }

    public function assertEquals($expected, $actual, string $description): bool {
        $condition = ($expected === $actual);
        $details = "Expected: " . json_encode($expected) . ", Got: " . json_encode($actual);
        return $this->assertTrue($description, $condition, $details);
    }

    public function skip(string $description, string $reason = ''): void {
        $this->skipped++;
        echo "  [SKIP] {$description}\n";
        if (!empty($reason)) {
            echo "         -> {$reason}\n";
        }
    }

    public function recordException(Throwable $e, string $stage = 'RUNTIME'): void {
        $this->failed++;
        $this->runtimeException = $e;
        fwrite(STDERR, "\n{$stage} EXCEPTION: " . $e->getMessage() . "\n" . $e->getTraceAsString() . "\n");
        echo "  [FAIL] {$stage} Exception caught: " . $e->getMessage() . "\n";
    }

    public function recordCleanupFailure(string $target, Throwable $e): void {
        $this->failed++;
        $this->cleanupException = $e;
        fwrite(STDERR, "\nCLEANUP FAILURE [{$target}]: " . $e->getMessage() . "\n" . $e->getTraceAsString() . "\n");
        echo "  [FAIL] Cleanup Failed for {$target}: " . $e->getMessage() . "\n";
    }

    public function getPassedCount(): int {
        return $this->passed;
    }

    public function getFailedCount(): int {
        return $this->failed;
    }

    public function getSkippedCount(): int {
        return $this->skipped;
    }

    public function getAssertionsCount(): int {
        return $this->assertionsCount;
    }

    private function logTest(string $name, bool $success, string $detail = ''): void {
        if ($success) {
            echo "  [PASS] {$name}\n";
            if (!empty($detail)) echo "         -> {$detail}\n";
        } else {
            echo "  [FAIL] {$name}\n";
            if (!empty($detail)) echo "         -> {$detail}\n";
        }
    }

    public function finish(): void {
        echo "\n-----------------------------------------------------------\n";
        echo "VERIFICATION SUMMARY: {$this->passed} PASSED, {$this->failed} FAILED, {$this->skipped} SKIPPED\n";
        echo "-----------------------------------------------------------\n";

        $isSuccess = ($this->setupCompleted === true)
            && ($this->assertionsCount > 0)
            && ($this->failed === 0)
            && ($this->runtimeException === null)
            && ($this->cleanupException === null);

        $resultPayload = [
            'status' => $isSuccess ? 'pass' : 'fail',
            'passed' => $this->passed,
            'failed' => $this->failed,
            'skipped' => $this->skipped,
            'assertions' => $this->assertionsCount,
            'setup_completed' => $this->setupCompleted,
            'runtime_exception' => ($this->runtimeException !== null),
            'cleanup_exception' => ($this->cleanupException !== null),
        ];
        echo "\nQUESTBANK_TEST_RESULT=" . json_encode($resultPayload, JSON_UNESCAPED_SLASHES) . "\n";

        if ($isSuccess) {
            echo "RESULT: SUCCESS — All assertions passed cleanly.\n";
            exit(0);
        } else {
            $reasons = [];
            if (!$this->setupCompleted) $reasons[] = "Setup failed or uncompleted";
            if ($this->assertionsCount === 0) $reasons[] = "No assertions executed";
            if ($this->failed > 0) $reasons[] = "{$this->failed} assertion(s) or exception(s) failed";
            if ($this->runtimeException !== null) $reasons[] = "Runtime exception occurred";
            if ($this->cleanupException !== null) $reasons[] = "Cleanup failure occurred";

            echo "RESULT: FAILURE — " . implode(', ', $reasons) . ".\n";
            exit(1);
        }
    }
}

/**
 * Executes a child process with non-blocking pipe reads and a timeout limit.
 */
function runVerifierWithTimeout(string $scriptPath, array $envOverrides = [], int $timeoutSeconds = 120): array {
    $env = array_merge(getenv(), [
        'APP_ENV' => 'testing',
        'TEST_BOOTSTRAP_ACTIVE' => '1',
    ], $envOverrides);

    $descriptorspec = [
        0 => ["pipe", "r"], // stdin
        1 => ["pipe", "w"], // stdout
        2 => ["pipe", "w"]  // stderr
    ];

    $cmd = 'php ' . escapeshellarg($scriptPath);
    $startTime = microtime(true);

    $process = proc_open($cmd, $descriptorspec, $pipes, dirname(dirname($scriptPath)), $env);

    if (!is_resource($process)) {
        return [
            'exit_code' => -1,
            'stdout' => '',
            'stderr' => 'Failed to spawn process',
            'duration' => 0.0,
            'timed_out' => false,
            'forced_killed' => false,
            'result_marker' => null,
            'marker_error' => 'SPAWN_FAILED',
            'passed' => false,
            'fail_reasons' => ['Failed to spawn child process']
        ];
    }

    fclose($pipes[0]);

    stream_set_blocking($pipes[1], false);
    stream_set_blocking($pipes[2], false);

    $stdout = '';
    $stderr = '';
    $timedOut = false;
    $forcedKilled = false;

    while (true) {
        $read = [$pipes[1], $pipes[2]];
        $write = null;
        $except = null;

        $changed = stream_select($read, $write, $except, 0, 100000);

        if ($changed !== false && $changed > 0) {
            foreach ($read as $pipe) {
                $content = fread($pipe, 8192);
                if ($content !== false && strlen($content) > 0) {
                    if ($pipe === $pipes[1]) {
                        $stdout .= $content;
                    } else if ($pipe === $pipes[2]) {
                        $stderr .= $content;
                    }
                }
            }
        }

        $status = proc_get_status($process);
        $elapsed = microtime(true) - $startTime;

        if (!$status['running']) {
            while (($content = fread($pipes[1], 8192)) !== false && strlen($content) > 0) {
                $stdout .= $content;
            }
            while (($content = fread($pipes[2], 8192)) !== false && strlen($content) > 0) {
                $stderr .= $content;
            }
            $exitCode = $status['exitcode'];
            break;
        }

        if ($elapsed >= $timeoutSeconds) {
            $timedOut = true;
            proc_terminate($process, 15);
            $graceStart = microtime(true);
            $terminatedGracefully = false;

            while ((microtime(true) - $graceStart) < 2.0) {
                usleep(50000);
                $st = proc_get_status($process);
                if (!$st['running']) {
                    $terminatedGracefully = true;
                    $exitCode = $st['exitcode'];
                    break;
                }
            }

            if (!$terminatedGracefully) {
                proc_terminate($process, 9);
                $forcedKilled = true;
                usleep(50000);
                $st = proc_get_status($process);
                $exitCode = $st['exitcode'];
            }

            while (($content = fread($pipes[1], 8192)) !== false && strlen($content) > 0) {
                $stdout .= $content;
            }
            while (($content = fread($pipes[2], 8192)) !== false && strlen($content) > 0) {
                $stderr .= $content;
            }
            break;
        }
    }

    fclose($pipes[1]);
    fclose($pipes[2]);
    proc_close($process);

    $duration = round(microtime(true) - $startTime, 2);

    $resultMarker = null;
    $markerError = null;

    if (preg_match('/QUESTBANK_TEST_RESULT=(.+)/', $stdout, $matches)) {
        $jsonStr = trim($matches[1]);
        $decoded = json_decode($jsonStr, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            $resultMarker = $decoded;
        } else {
            $markerError = 'MALFORMED_MARKER_JSON';
        }
    } else {
        $markerError = 'MISSING_MARKER';
    }

    $failReasons = [];
    if ($timedOut) {
        $failReasons[] = 'Timeout exceeded (' . $timeoutSeconds . 's limit' . ($forcedKilled ? ', force-killed' : '') . ')';
    }
    if ($exitCode !== 0) {
        $failReasons[] = "Exit code non-zero ({$exitCode})";
    }
    if ($resultMarker === null) {
        $failReasons[] = ($markerError === 'MALFORMED_MARKER_JSON') ? 'Malformed result marker JSON' : 'Missing QUESTBANK_TEST_RESULT marker';
    } else {
        if (($resultMarker['status'] ?? '') !== 'pass') {
            $failReasons[] = 'Structured result status is fail';
        }
        if (($resultMarker['failed'] ?? 0) > 0) {
            $failReasons[] = 'Failed assertions/exceptions count > 0 (' . $resultMarker['failed'] . ')';
        }
        if (($resultMarker['assertions'] ?? 0) <= 0) {
            $failReasons[] = 'No assertions executed (assertions count <= 0)';
        }
    }

    $passed = (count($failReasons) === 0);

    return [
        'exit_code' => $exitCode,
        'stdout' => $stdout,
        'stderr' => $stderr,
        'duration' => $duration,
        'timed_out' => $timedOut,
        'forced_killed' => $forcedKilled,
        'result_marker' => $resultMarker,
        'marker_error' => $markerError,
        'passed' => $passed,
        'fail_reasons' => $failReasons
    ];
}

