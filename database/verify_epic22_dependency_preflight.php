<?php

require_once __DIR__ . '/../tests/helpers/test_preflight.php';
require_once __DIR__ . '/../app/bootstrap.php';

// Execute preflight checks for standard extensions
runPreflightChecks(['pdo', 'pdo_mysql', 'mbstring', 'curl', 'json', 'fileinfo', 'zip', 'xml']);

$passed = 0;
$failed = 0;

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

echo "===========================================================\n";
echo " QUESTBANK EPIC 2.2 TEST DEPENDENCY PREFLIGHT VERIFICATION \n";
echo "===========================================================\n";

try {
    // -----------------------------------------------------------
    // TEST 1: Missing mbstring (simulated via child process)
    // -----------------------------------------------------------
    $cmd1 = 'php -r "require \'tests/helpers/test_preflight.php\'; requirePhpExtensions([\'fake_mbstring_ext\']);" 2>&1';
    $out1 = shell_exec($cmd1);
    $exitCode1 = null;
    exec($cmd1 . '; echo $?', $rawOut1, $code1);
    $exitCode1 = intval(end($rawOut1));

    logTest("TEST 1: Missing Extension -> Clear Error Message & Exit Code 1",
        $exitCode1 === 1 && strpos($out1, "[REQUIRED] PHP Extension 'fake_mbstring_ext' is NOT loaded.") !== false,
        "ExitCode: {$exitCode1}, Stderr contains expected requirement warning"
    );

    // -----------------------------------------------------------
    // TEST 2: Missing pdo_mysql (simulated via child process)
    // -----------------------------------------------------------
    $cmd2 = 'php -r "require \'tests/helpers/test_preflight.php\'; requirePhpExtensions([\'fake_pdo_mysql_ext\']);" 2>&1';
    $out2 = shell_exec($cmd2);
    exec($cmd2 . '; echo $?', $rawOut2, $code2);
    $exitCode2 = intval(end($rawOut2));

    logTest("TEST 2: Missing pdo_mysql -> Clear Error Message & Exit Code 1",
        $exitCode2 === 1 && strpos($out2, "[REQUIRED] PHP Extension 'fake_pdo_mysql_ext' is NOT loaded.") !== false,
        "ExitCode: {$exitCode2}, Stderr contains expected requirement warning"
    );

    // -----------------------------------------------------------
    // TEST 3: Missing curl (simulated via child process)
    // -----------------------------------------------------------
    $cmd3 = 'php -r "require \'tests/helpers/test_preflight.php\'; requirePhpExtensions([\'fake_curl_ext\']);" 2>&1';
    $out3 = shell_exec($cmd3);
    exec($cmd3 . '; echo $?', $rawOut3, $code3);
    $exitCode3 = intval(end($rawOut3));

    logTest("TEST 3: Missing curl -> Clear Error Message & Exit Code 1",
        $exitCode3 === 1 && strpos($out3, "[REQUIRED] PHP Extension 'fake_curl_ext' is NOT loaded.") !== false,
        "ExitCode: {$exitCode3}, Stderr contains expected requirement warning"
    );

    // -----------------------------------------------------------
    // TEST 4: All Standard Extensions Available -> Preflight Passes
    // -----------------------------------------------------------
    $cmd4 = 'php -r "require \'tests/helpers/test_preflight.php\'; runPreflightChecks(); echo \'PREFLIGHT_PASS\';" 2>&1';
    $out4 = shell_exec($cmd4);
    exec($cmd4 . '; echo $?', $rawOut4, $code4);
    $exitCode4 = intval(end($rawOut4));

    logTest("TEST 4: All Dependencies Available -> Preflight Passes & Exit Code 0",
        $exitCode4 === 0 && strpos($out4, 'PREFLIGHT_PASS') !== false,
        "ExitCode: {$exitCode4}, Output: " . trim($out4)
    );

    // -----------------------------------------------------------
    // TEST 5: Missing External Command -> Clear Error Message & Exit Code 1
    // -----------------------------------------------------------
    $cmd5 = 'php -r "require \'tests/helpers/test_preflight.php\'; requireCommands([\'nonexistent_cmd_xyz123\']);" 2>&1';
    $out5 = shell_exec($cmd5);
    exec($cmd5 . '; echo $?', $rawOut5, $code5);
    $exitCode5 = intval(end($rawOut5));

    logTest("TEST 5: Missing External Command -> Clear Error Message & Exit Code 1",
        $exitCode5 === 1 && strpos($out5, "[REQUIRED] Command 'nonexistent_cmd_xyz123' is NOT available") !== false,
        "ExitCode: {$exitCode5}, Stderr contains expected command warning"
    );

} catch (Throwable $e) {
    $failed++;
    fwrite(STDERR, "\nSETUP OR RUNTIME EXCEPTION: " . $e->getMessage() . "\n" . $e->getTraceAsString() . "\n");
}

echo "\n-----------------------------------------------------------\n";
echo "VERIFICATION SUMMARY: {$passed} PASSED, {$failed} FAILED\n";
echo "-----------------------------------------------------------\n";

if ($failed > 0) {
    echo "RESULT: FAILURE — {$failed} assertions failed.\n";
    exit(1);
} else {
    echo "RESULT: SUCCESS — All test dependency preflight assertions passed cleanly.\n";
    exit(0);
}
