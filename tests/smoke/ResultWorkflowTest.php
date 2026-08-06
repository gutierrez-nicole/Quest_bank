<?php
/**
 * QUESTBANK SMOKE SUITE — RESULT PUBLICATION & PRIVACY BOUNDARIES
 */

require_once __DIR__ . '/../../app/services/ResultWorkflowService.php';

function test_result_workflow_smoke($pdo) {
    echo "  [TEST] Result Publication & Student Privacy Boundaries...\n";

    // 1. Verify published submission (ID 500) passes privacy check for owner student (ID 11)
    $subPublished = ResultWorkflowService::enforceStudentPrivacy(500, 11);
    if (empty($subPublished['allowed']) || (int)$subPublished['submission']['id'] !== 500) {
        throw new Exception("Result workflow test failed: Published submission #500 not accessible to owner student #11 (" . ($subPublished['error'] ?? 'Unknown error') . ")");
    }

    // 2. Verify pending review submission (ID 501) is denied for student (ID 20)
    $subPending = ResultWorkflowService::enforceStudentPrivacy(501, 20);
    if (!empty($subPending['allowed'])) {
        throw new Exception("Result workflow test failed: Pending review submission #501 SHOULD NOT be visible to student before publication");
    }

    // 3. Verify cross-student privacy: Student #21 attempting to view Student #11's submission #500 is denied
    $subCross = ResultWorkflowService::enforceStudentPrivacy(500, 21);
    if (!empty($subCross['allowed'])) {
        throw new Exception("Result workflow test failed: Cross-student submission access allowed!");
    }

    echo "    [✓] Result publication and student privacy boundaries verified\n";
    return true;
}
