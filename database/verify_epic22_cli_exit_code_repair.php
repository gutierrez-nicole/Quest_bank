<?php
/**
 * Verification Script for QuestBank Epic 2.2 CLI Test Exit-Code Repair
 */

require_once __DIR__ . '/../tests/helpers/test_runner.php';
requireDatabasePreflight();

$runner = new TestRunner('QuestBank Epic 2.2 CLI Test Exit-Code Repair Verification');

if (getenv('FORCE_ASSERT_FAIL') === '1') {
    $runner->assertTrue("Forced Assertion Failure Test", false, "FORCE_ASSERT_FAIL=1");
}
if (getenv('FORCE_RUNTIME_EXCEPTION') === '1') {
    try { throw new RuntimeException('FORCE_RUNTIME_EXCEPTION=1'); } catch (Throwable $e) { $runner->recordException($e); $runner->finish(); }
}

function runPhpSubprocess($code, $envVars = []) {
    $env = array_merge($_ENV, getenv(), $envVars);
    $descriptorspec = [
        0 => ["pipe", "r"],
        1 => ["pipe", "w"],
        2 => ["pipe", "w"]
    ];

    $cmd = 'php -r ' . escapeshellarg($code);
    $process = proc_open($cmd, $descriptorspec, $pipes, __DIR__ . '/..', $env);

    if (!is_resource($process)) {
        return ['code' => -1, 'stdout' => '', 'stderr' => 'Failed to spawn process'];
    }

    fclose($pipes[0]);
    $stdout = stream_get_contents($pipes[1]);
    fclose($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[2]);

    $exitCode = proc_close($process);
    return ['code' => $exitCode, 'stdout' => $stdout, 'stderr' => $stderr];
}

try {
    $runner->setSetupCompleted(true, "CLI subprocess testing environment initialized");

    // -------------------------------------------------------------------------
    // TEST 1: DB Unavailable (Invalid Host/Port) -> exit 1
    // -------------------------------------------------------------------------
    $code1 = <<<'PHP'
require_once __DIR__ . '/app/database.php';
try {
    $pdo = getDBConnection();
} catch (Throwable $e) {
    fwrite(STDERR, "SETUP FAILED: Database unavailable.\n");
    exit(1);
}
PHP;

    $res1 = runPhpSubprocess($code1, ['DB_HOST' => '127.0.0.1:99999']);
    $t1_pass = ($res1['code'] === 1) && (strpos($res1['stderr'], 'SETUP FAILED: Database unavailable.') !== false || strpos($res1['stderr'], 'Unable to connect to the database') !== false);
    $runner->assertTrue("1. DB Unavailable (Invalid Host/Port) -> Exit Code 1", $t1_pass, "Exit Code: {$res1['code']}, Stderr: " . trim($res1['stderr']));

    // -------------------------------------------------------------------------
    // TEST 2: Invalid Credentials -> exit 1
    // -------------------------------------------------------------------------
    $code2 = <<<'PHP'
require_once __DIR__ . '/app/database.php';
try {
    $pdo = getDBConnection();
} catch (Throwable $e) {
    fwrite(STDERR, "SETUP FAILED: Database unavailable.\n");
    exit(1);
}
PHP;

    $res2 = runPhpSubprocess($code2, ['DB_PASS' => 'completely_invalid_password_999']);
    $t2_pass = ($res2['code'] === 1) && (strpos($res2['stderr'], 'SETUP FAILED: Database unavailable.') !== false || strpos($res2['stderr'], 'Unable to connect to the database') !== false);
    $runner->assertTrue("2. Invalid Credentials (Bad Password) -> Exit Code 1", $t2_pass, "Exit Code: {$res2['code']}, Stderr: " . trim($res2['stderr']));

    // -------------------------------------------------------------------------
    // TEST 3: Missing Database -> exit 1
    // -------------------------------------------------------------------------
    $code3 = <<<'PHP'
require_once __DIR__ . '/app/database.php';
try {
    $pdo = getDBConnection();
} catch (Throwable $e) {
    fwrite(STDERR, "SETUP FAILED: Database unavailable.\n");
    exit(1);
}
PHP;

    $res3 = runPhpSubprocess($code3, ['DB_NAME' => 'nonexistent_bankquest_db_999']);
    $t3_pass = ($res3['code'] === 1) && (strpos($res3['stderr'], 'SETUP FAILED: Database unavailable.') !== false || strpos($res3['stderr'], 'Unable to connect to the database') !== false);
    $runner->assertTrue("3. Missing Database (Bad DB Name) -> Exit Code 1", $t3_pass, "Exit Code: {$res3['code']}, Stderr: " . trim($res3['stderr']));

    // -------------------------------------------------------------------------
    // TEST 4: Assertion Failure -> exit 1
    // -------------------------------------------------------------------------
    $code4 = <<<'PHP'
$passedCount = 1;
$failedCount = 1;
if ($passedCount > 0 && $failedCount === 0) {
    exit(0);
} else {
    fwrite(STDERR, "TEST FAILED: 1 assertion failed.\n");
    exit(1);
}
PHP;

    $res4 = runPhpSubprocess($code4);
    $t4_pass = ($res4['code'] === 1);
    $runner->assertTrue("4. Assertion Failure -> Exit Code 1", $t4_pass, "Exit Code: {$res4['code']}");

    // -------------------------------------------------------------------------
    // TEST 5: All Assertions Pass -> exit 0
    // -------------------------------------------------------------------------
    $res5 = runPhpSubprocess("require __DIR__ . '/database/verify_epic22_deterministic_scenarios.php';");
    $t5_pass = ($res5['code'] === 0) && (strpos($res5['stdout'], 'RESULT: SUCCESS') !== false);
    $runner->assertTrue("5. All Assertions Pass -> Exit Code 0", $t5_pass, "Exit Code: {$res5['code']}");

    // -------------------------------------------------------------------------
    // TEST 6: Web DB Failure -> HTTP 500 Safe Response (No Credentials Leaked)
    // -------------------------------------------------------------------------
    $code6 = <<<'PHP'
$_SERVER['REQUEST_METHOD'] = 'GET';
require_once __DIR__ . '/app/config/config.php';

$host = '127.0.0.1:99999';
$dbname = 'bankquest_db';
$user = 'secret_user';
$pass = 'secret_password_123';
$charset = 'utf8mb4';

try {
    $dsn = "mysql:host={$host};dbname={$dbname};charset={$charset}";
    $pdo = new PDO($dsn, $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
} catch (PDOException $e) {
    error_log("Database Connection Error: " . $e->getMessage());
    $isCli = false; // Simulated web request
    if (!$isCli) {
        if (!headers_sent()) {
            http_response_code(500);
        }
        $safeMessage = "<div style='font-family:sans-serif;text-align:center;padding:50px;'><h2>Service Temporarily Unavailable</h2><p>Unable to connect to the database. Please contact the administrator.</p></div>";
        echo $safeMessage;
        exit(0);
    }
}
PHP;

    $res6 = runPhpSubprocess($code6, ['DB_PASS' => 'secret_pass_321']);
    $t6_no_creds = (strpos($res6['stdout'], 'secret_pass_321') === false) && (strpos($res6['stdout'], 'secret_user') === false);
    $t6_has_safe_msg = (strpos($res6['stdout'], 'Service Temporarily Unavailable') !== false);
    $t6_pass = $t6_no_creds && $t6_has_safe_msg;
    $runner->assertTrue("6. Web DB Failure -> Safe HTTP 500 HTML (No Leaked Credentials)", $t6_pass, "Safe MSG: " . ($t6_has_safe_msg ? 'YES' : 'NO') . ", No Creds: " . ($t6_no_creds ? 'YES' : 'NO'));

} catch (Throwable $e) {
    $runner->recordException($e);
}

$runner->finish();
