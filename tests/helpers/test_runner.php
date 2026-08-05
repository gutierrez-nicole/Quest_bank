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
