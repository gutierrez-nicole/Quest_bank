<?php
/**
 * QUESTBANK — EPIC 2.2 AUTHORITATIVE VERIFICATION SUITE RUNNER
 *
 * Loads its script list directly from database/epic22_verifiers.json.
 * Validates manifest integrity, discovers unclassified files, enforces non-blocking timeouts,
 * parses structured QUESTBANK_TEST_RESULT markers, and certifies Epic 2.2.
 *
 * Returns exit code 0 only when ALL authoritative verifiers pass and 0 unclassified scripts exist.
 * Returns exit code 1 if ANY check or verifier fails.
 */

require_once __DIR__ . '/../tests/helpers/test_runner.php';

$scriptDir = __DIR__;
$manifestPath = $scriptDir . '/epic22_verifiers.json';

echo "===================================================================\n";
echo "  QUESTBANK EPIC 2.2 — AUTHORITATIVE VERIFICATION SUITE RUNNER    \n";
echo "===================================================================\n";

if (!file_exists($manifestPath)) {
    echo "  [\033[31mFATAL\033[0m] Manifest file missing: database/epic22_verifiers.json\n";
    exit(1);
}

$manifestJson = file_get_contents($manifestPath);
$manifestData = json_decode($manifestJson, true);

if (json_last_error() !== JSON_ERROR_NONE || !is_array($manifestData) || !isset($manifestData['verifiers'])) {
    echo "  [\033[31mFATAL\033[0m] Manifest contains invalid JSON or missing 'verifiers' array\n";
    exit(1);
}

// Track verifier classification counts
$authoritativeVerifiers = [];
$supportingVerifiers = [];
$deprecatedVerifiers = [];
$manifestFilenames = [];
$manifestErrors = [];

foreach ($manifestData['verifiers'] as $idx => $v) {
    $fn = $v['filename'] ?? null;
    $label = $v['label'] ?? null;
    $class = $v['classification'] ?? null;
    $tier = $v['dependency_tier'] ?? null;
    $timeout = $v['timeout'] ?? null;
    $reason = $v['reason'] ?? null;
    $replacement = $v['replacement'] ?? null;

    if (empty($fn) || empty($label) || empty($class) || empty($tier) || empty($timeout) || empty($reason)) {
        $manifestErrors[] = "Entry #{$idx} missing required fields (filename, label, classification, dependency_tier, timeout, reason)";
        continue;
    }

    if (!in_array($class, ['authoritative', 'supporting', 'deprecated'], true)) {
        $manifestErrors[] = "Invalid classification '{$class}' for verifier '{$fn}'";
        continue;
    }

    if (in_array($fn, $manifestFilenames, true)) {
        $manifestErrors[] = "Duplicate filename '{$fn}' in manifest";
        continue;
    }
    $manifestFilenames[] = $fn;

    if ($class === 'deprecated') {
        if (empty($replacement)) {
            $manifestErrors[] = "Deprecated verifier '{$fn}' must specify a replacement script";
        }
        $deprecatedVerifiers[] = $v;
    } elseif ($class === 'authoritative') {
        $authoritativeVerifiers[] = $v;
    } elseif ($class === 'supporting') {
        $supportingVerifiers[] = $v;
    }
}

// Discover physical files on disk matching verify_epic22_*.php
$physicalFiles = [];
$finderPatterns = [
    $scriptDir . '/verify_epic22_*.php',
    $scriptDir . '/verification_archive/verify_epic22_*.php'
];

foreach ($finderPatterns as $pattern) {
    foreach (glob($pattern) as $pPath) {
        $relPath = str_replace($scriptDir . '/', '', $pPath);
        $physicalFiles[$relPath] = $pPath;
    }
}

// Find unclassified / omitted scripts
$unclassifiedFiles = [];
foreach ($physicalFiles as $relPath => $fullPath) {
    $baseName = basename($relPath);
    $inManifest = false;

    foreach ($manifestFilenames as $mFn) {
        if ($mFn === $relPath || $mFn === $baseName || basename($mFn) === $baseName) {
            $inManifest = true;
            break;
        }
    }

    if (!$inManifest) {
        $unclassifiedFiles[] = $relPath;
    }
}

// Check missing authoritative files
$missingAuthoritative = [];
foreach ($authoritativeVerifiers as $v) {
    $fn = $v['filename'];
    $fullPath = (strpos($fn, '/') !== false) ? $scriptDir . '/' . $fn : $scriptDir . '/' . $fn;
    if (!file_exists($fullPath)) {
        $missingAuthoritative[] = $fn;
    }
}

$authoritativeCount = count($authoritativeVerifiers);
$supportingCount = count($supportingVerifiers);
$deprecatedCount = count($deprecatedVerifiers);
$omittedUnclassifiedCount = count($unclassifiedFiles);

echo "  Manifest Loaded: " . count($manifestFilenames) . " verifiers listed\n";
echo "  Default Timeout: " . (getenv('EPIC22_VERIFIER_TIMEOUT_SECONDS') ?: 120) . "s\n";
echo "===================================================================\n\n";

if (!empty($manifestErrors)) {
    echo "  [\033[31mMANIFEST ERROR\033[0m] Manifest validation failed:\n";
    foreach ($manifestErrors as $err) {
        echo "           -> {$err}\n";
    }
    echo "\nRESULT: SUITE FAILURE — Invalid manifest file.\n";
    exit(1);
}

if (!empty($missingAuthoritative)) {
    echo "  [\033[31mMISSING FILE\033[0m] Required authoritative verifier missing from disk:\n";
    foreach ($missingAuthoritative as $mf) {
        echo "           -> {$mf}\n";
    }
    echo "\nRESULT: SUITE FAILURE — Missing authoritative verifiers.\n";
    exit(1);
}

if ($omittedUnclassifiedCount > 0) {
    echo "  [\033[31mUNCLASSIFIED FILE\033[0m] Unclassified verifier scripts detected on disk:\n";
    foreach ($unclassifiedFiles as $uf) {
        echo "           -> {$uf}\n";
    }
    echo "\nRESULT: SUITE FAILURE — {$omittedUnclassifiedCount} unclassified verifier(s) present.\n";
    exit(1);
}

// Execute authoritative verifiers
$executedCount = 0;
$passedScripts = 0;
$failedScripts = 0;
$failedNames = [];

foreach ($authoritativeVerifiers as $v) {
    $filename = $v['filename'];
    $label = $v['label'];
    $timeout = (int)($v['timeout'] ?? (getenv('EPIC22_VERIFIER_TIMEOUT_SECONDS') ?: 120));

    $fullPath = (strpos($filename, '/') !== false) ? $scriptDir . '/' . $filename : $scriptDir . '/' . $filename;
    $executedCount++;

    $run = runVerifierWithTimeout($fullPath, [], $timeout);

    $durationStr = number_format($run['duration'], 2) . 's';
    $marker = $run['result_marker'];

    if ($run['passed']) {
        $passedScripts++;
        $pCount = $marker['passed'] ?? 0;
        $fCount = $marker['failed'] ?? 0;
        $sCount = $marker['skipped'] ?? 0;
        $aCount = $marker['assertions'] ?? 0;

        echo "  [\033[32mPASS\033[0m]    {$label} ({$filename}) [{$durationStr}, Exit: 0]\n";
        echo "           -> {$pCount} PASSED, {$fCount} FAILED, {$sCount} SKIPPED ({$aCount} assertions)\n";
    } else {
        $failedScripts++;
        $failedNames[] = $filename;
        $reasonsStr = implode('; ', $run['fail_reasons']);

        $timeoutBadge = $run['timed_out'] ? ($run['forced_killed'] ? ' [TIMEOUT:FORCE_KILLED]' : ' [TIMEOUT:SIGTERM]') : '';
        echo "  [\033[31mFAIL\033[0m]    {$label} ({$filename}) [{$durationStr}, Exit: {$run['exit_code']}]{$timeoutBadge}\n";
        echo "           -> Reason: {$reasonsStr}\n";

        if ($marker !== null) {
            $p = $marker['passed'] ?? 0;
            $f = $marker['failed'] ?? 0;
            $s = $marker['skipped'] ?? 0;
            $a = $marker['assertions'] ?? 0;
            echo "           -> Totals: {$p} PASSED, {$f} FAILED, {$s} SKIPPED ({$a} assertions)\n";
        }

        if (!empty($run['stderr'])) {
            $stderrLines = array_slice(array_filter(explode("\n", trim($run['stderr']))), -3);
            foreach ($stderrLines as $line) {
                echo "           -> STDERR: " . trim($line) . "\n";
            }
        }
    }
}

echo "\n===================================================================\n";
echo "  EPIC 2.2 VERIFIER INVENTORY & CERTIFICATION SUMMARY\n";
echo "===================================================================\n";
echo "  Authoritative Verifiers : {$authoritativeCount}\n";
echo "  Supporting Verifiers    : {$supportingCount}\n";
echo "  Deprecated Verifiers    : {$deprecatedCount}\n";
echo "  Executed Verifiers      : {$executedCount}\n";
echo "  Unclassified/Omitted    : {$omittedUnclassifiedCount}\n";
echo "===================================================================\n";
echo "SUITE RESULTS: {$passedScripts}/{$executedCount} PASSED, {$failedScripts}/{$executedCount} FAILED\n";
echo "===================================================================\n";

if ($failedScripts > 0 || $omittedUnclassifiedCount > 0) {
    if ($failedScripts > 0) {
        echo "\nFailed Authoritative Scripts:\n";
        foreach ($failedNames as $fn) {
            echo "  - {$fn}\n";
        }
    }
    echo "\nRESULT: SUITE FAILURE — Certification failed.\n";
    exit(1);
} else {
    echo "\nRESULT: SUITE SUCCESS — All {$executedCount} authoritative verifiers passed cleanly with 0 unclassified scripts.\n";
    exit(0);
}
