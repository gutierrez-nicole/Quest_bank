<?php
/**
 * QUESTBANK MAINTAINER SMOKE TEST SUITE RUNNER
 *
 * Lightweight maintenance test suite verifying core application subsystems.
 * Command: php tests/run_smoke_tests.php
 */

require_once __DIR__ . '/../app/bootstrap.php';

echo "===================================================================\n";
echo "   QUESTBANK v2.2-RC1 — MAINTAINER PRACTICAL SMOKE TEST SUITE    \n";
echo "===================================================================\n\n";

$pdo = getDBConnection();

require_once __DIR__ . '/smoke/AuthSmokeTest.php';
require_once __DIR__ . '/smoke/ExamScoringTest.php';
require_once __DIR__ . '/smoke/ResultWorkflowTest.php';
require_once __DIR__ . '/smoke/MigrationSmokeTest.php';
require_once __DIR__ . '/smoke/OcrCameraUploadTest.php';

$tests = [
    'test_auth_smoke',
    'test_exam_scoring_smoke',
    'test_result_workflow_smoke',
    'test_migration_smoke',
    'test_ocr_camera_upload_smoke'
];

$passed = 0;
$failed = 0;

foreach ($tests as $t) {
    try {
        if ($t($pdo)) {
            $passed++;
        }
    } catch (Throwable $e) {
        $failed++;
        echo "    [✘] FAILED: " . $e->getMessage() . "\n";
    }
}

echo "\n===================================================================\n";
echo "RESULTS: {$passed}/" . count($tests) . " PASSED, {$failed}/" . count($tests) . " FAILED\n";
echo "===================================================================\n";

if ($failed > 0) {
    exit(1);
}

echo "\n[SUCCESS] All QuestBank core subsystem smoke tests passed cleanly!\n";
exit(0);
