<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../tests/helpers/test_runner.php';
requireDatabasePreflight();

require_once __DIR__ . '/../app/bootstrap.php';
require_once __DIR__ . '/../app/services/GroqService.php';

$runner = new TestRunner('QuestBank Priority 2 Final Distribution Validation Verification');

$pdo = null;

try {
    $pdo = getDBConnection();
    $runner->setSetupCompleted($pdo !== null, "Database connection established");

    // TEST 1: Omitted selected-period percentage becomes zero (NOT equal distribution)
    $selectedPeriods = ['general', 'prelim'];
    $submittedWeights = ['prelim' => 100]; // 'general' is omitted
    $res1 = GroqService::validateAndCalculatePeriodWeights('percentage', $submittedWeights, $selectedPeriods, 10);
    
    $omittedIsZero = ($res1['requested_distribution']['general'] === 0.0 && $res1['requested_distribution']['prelim'] === 100.0);
    $targetCountsZero = ($res1['target_counts']['general'] === 0 && $res1['target_counts']['prelim'] === 10);
    $runner->assertTrue(
        "TEST 1: Omitted selected-period percentage becomes zero",
        $omittedIsZero && $targetCountsZero,
        "Requested: " . json_encode($res1['requested_distribution']) . ", Targets: " . json_encode($res1['target_counts'])
    );

    // TEST 2: Percentages cannot total more than 100%
    $overPctCaught = false;
    try {
        GroqService::validateAndCalculatePeriodWeights('percentage', ['general' => 60, 'prelim' => 50], $selectedPeriods, 10);
    } catch (InvalidArgumentException $e) {
        $overPctCaught = true;
    }
    $runner->assertTrue("TEST 2: Reject period percentage sum exceeding 100%", $overPctCaught, "InvalidArgumentException thrown on 110% total");

    // TEST 3: Target counts sum invariant check
    $res3 = GroqService::validateAndCalculatePeriodWeights('percentage', ['general' => 33.33, 'prelim' => 66.67], $selectedPeriods, 7);
    $sum3 = array_sum($res3['target_counts']);
    $runner->assertTrue("TEST 3: Target counts sum always equals requested total questions", $sum3 === 7, "Sum of target_counts = {$sum3} (Requested: 7)");

    // TEST 4: Zero percentage total rejected
    $zeroPctCaught = false;
    try {
        GroqService::validateAndCalculatePeriodWeights('percentage', ['general' => 0, 'prelim' => 0], $selectedPeriods, 10);
    } catch (InvalidArgumentException $e) {
        $zeroPctCaught = true;
    }
    $runner->assertTrue("TEST 4: Reject zero percentage total", $zeroPctCaught, "InvalidArgumentException thrown on 0% total");

    // TEST 5: Fixed count mismatch rejected
    $fixedMismatchCaught = false;
    try {
        GroqService::validateAndCalculatePeriodWeights('fixed', ['general' => 3, 'prelim' => 5], $selectedPeriods, 10); // sum is 8 != 10
    } catch (InvalidArgumentException $e) {
        $fixedMismatchCaught = true;
    }
    $runner->assertTrue("TEST 5: Reject fixed period count sum mismatch", $fixedMismatchCaught, "InvalidArgumentException thrown when fixed sum (8) != total (10)");

    // TEST 6: Difficulty percentage validation and calculation
    $diffDist = ['easy' => 40, 'medium' => 40, 'hard' => 20];
    $diffRes = GroqService::validateAndCalculateDifficulty('percentage', $diffDist, 10);
    $diffSum = array_sum($diffRes['target_counts']);
    $diffValid = ($diffRes['target_counts']['easy'] === 4 && $diffRes['target_counts']['medium'] === 4 && $diffRes['target_counts']['hard'] === 2);
    $runner->assertTrue("TEST 6: Difficulty percentage allocation and invariant check", $diffValid && $diffSum === 10, "Target counts: " . json_encode($diffRes['target_counts']));

    // TEST 7: Negative / non-numeric weight rejected
    $negativeWeightCaught = false;
    try {
        GroqService::validateAndCalculatePeriodWeights('percentage', ['general' => -10, 'prelim' => 110], $selectedPeriods, 10);
    } catch (InvalidArgumentException $e) {
        $negativeWeightCaught = true;
    }
    $runner->assertTrue("TEST 7: Reject negative period weight", $negativeWeightCaught, "InvalidArgumentException thrown on negative weight");

    // TEST 8: Simple equal weighting remains unchanged and satisfied
    $res8 = GroqService::validateAndCalculatePeriodWeights('equal', [], ['prelim', 'midterm', 'finals'], 9);
    $eqSum = array_sum($res8['target_counts']);
    $eqValid = ($res8['target_counts']['prelim'] === 3 && $res8['target_counts']['midterm'] === 3 && $res8['target_counts']['finals'] === 3);
    $runner->assertTrue("TEST 8: Equal period weighting produces balanced distribution", $eqValid && $eqSum === 9, "Target counts: " . json_encode($res8['target_counts']));

} catch (Throwable $e) {
    $runner->recordException($e);
} finally {
    $runner->finish();
}
