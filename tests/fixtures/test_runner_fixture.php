<?php
/**
 * QUESTBANK — TEST RUNNER FIXTURE
 *
 * A dedicated fixture script that uses the real TestRunner class and
 * supports controlled failure scenarios via environment flags.
 *
 * Supported environment flags:
 *   FORCE_ASSERT_FAIL=1           -> Triggers a real assertTrue(..., false)
 *   FORCE_RUNTIME_EXCEPTION=1     -> Throws a real exception, calls recordException
 *   FORCE_CLEANUP_FAILURE=1       -> Triggers a cleanup exception, calls recordCleanupFailure
 *   FORCE_NO_ASSERTIONS=1         -> Setup completes but no assertions execute
 *   FORCE_SETUP_FAILURE=1         -> Setup explicitly fails via setSetupCompleted(false)
 *   FORCE_TIMEOUT=1               -> Enters sleep loop exceeding standard timeout
 *   FORCE_SIGIGN_TIMEOUT=1        -> Ignores SIGTERM and enters sleep loop forcing SIGKILL
 *   FORCE_MISSING_MARKER=1        -> Exits 0 directly without outputting QUESTBANK_TEST_RESULT
 *   FORCE_MALFORMED_MARKER=1     -> Outputs malformed JSON in QUESTBANK_TEST_RESULT
 *   FORCE_EXCEPTION_TEXT_PASS=1   -> Emits EXCEPTION text in stderr/stdout but finishes with status pass
 *
 * Default (no flags): success mode — setup completes, assertions pass, exit 0.
 *
 * This fixture MUST NOT be used in production routes.
 * It is only invoked as a child process by meta-verification scripts.
 */

// --- FORCE_TIMEOUT: sleep for long duration to trigger suite timeout ---
if (getenv('FORCE_TIMEOUT') === '1') {
    sleep(10);
    exit(0);
}

// --- FORCE_SIGIGN_TIMEOUT: ignore SIGTERM and sleep infinitely to force SIGKILL ---
if (getenv('FORCE_SIGIGN_TIMEOUT') === '1') {
    if (function_exists('pcntl_async_signals') && function_exists('pcntl_signal')) {
        pcntl_async_signals(true);
        pcntl_signal(15, SIG_IGN); // Ignore SIGTERM
    }
    while (true) {
        sleep(1);
    }
}

// --- FORCE_MISSING_MARKER: exit without outputting QUESTBANK_TEST_RESULT marker ---
if (getenv('FORCE_MISSING_MARKER') === '1') {
    echo "Completed work without emitting result marker.\n";
    exit(0);
}

// --- FORCE_MALFORMED_MARKER: output invalid JSON in marker ---
if (getenv('FORCE_MALFORMED_MARKER') === '1') {
    echo "\nQUESTBANK_TEST_RESULT={invalid_json: true, status:\n";
    exit(0);
}

require_once __DIR__ . '/../helpers/test_runner.php';
requireCorePreflight();

// --- FORCE_EXCEPTION_TEXT_PASS: output EXCEPTION in stderr/stdout but complete as PASS ---
if (getenv('FORCE_EXCEPTION_TEXT_PASS') === '1') {
    fwrite(STDERR, "HANDLED EXCEPTION LOG: Simulated expected internal EXCEPTION notice\n");
    echo "Testing log output with EXCEPTION keyword...\n";
}

$runner = new TestRunner('TestRunner Contract Fixture');

try {
    // --- FORCE_SETUP_FAILURE: setup explicitly fails ---
    if (getenv('FORCE_SETUP_FAILURE') === '1') {
        $runner->setSetupCompleted(false, 'FORCE_SETUP_FAILURE=1: Simulated setup failure');
        $runner->finish();
    }

    $runner->setSetupCompleted(true, 'Fixture environment initialized');

    // --- FORCE_NO_ASSERTIONS: setup completes, no assertions execute ---
    if (getenv('FORCE_NO_ASSERTIONS') === '1') {
        $runner->finish();
    }

    // --- FORCE_ASSERT_FAIL: real assertion failure ---
    if (getenv('FORCE_ASSERT_FAIL') === '1') {
        $runner->assertTrue('Forced Assertion Failure', false, 'FORCE_ASSERT_FAIL=1');
        $runner->finish();
    }

    // --- FORCE_RUNTIME_EXCEPTION: throw and record a real exception ---
    if (getenv('FORCE_RUNTIME_EXCEPTION') === '1') {
        try {
            throw new RuntimeException('FORCE_RUNTIME_EXCEPTION=1: Simulated runtime failure');
        } catch (Throwable $e) {
            $runner->recordException($e, 'RUNTIME');
        }
        $runner->finish();
    }

    // --- Default success path ---
    $runner->assertTrue('Fixture Assertion 1: Basic truth', true, '1 === 1');
    $runner->assertTrue('Fixture Assertion 2: String comparison', 'hello' === 'hello', 'hello === hello');
    $runner->assertTrue('Fixture Assertion 3: Array check', is_array([1, 2, 3]), 'is_array returns true');

} catch (Throwable $e) {
    $runner->recordException($e);
}

// --- FORCE_CLEANUP_FAILURE: trigger after assertions ---
if (getenv('FORCE_CLEANUP_FAILURE') === '1') {
    try {
        throw new RuntimeException('FORCE_CLEANUP_FAILURE=1: Simulated cleanup failure');
    } catch (Throwable $e) {
        $runner->recordCleanupFailure('fixture_cleanup', $e);
    }
}

$runner->finish();
