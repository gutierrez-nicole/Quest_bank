<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../tests/helpers/test_runner.php';
requireDatabasePreflight();

require_once __DIR__ . '/../app/bootstrap.php';

$runner = new TestRunner('QuestBank Epic 2.2 Priority 2 Enhancements Test');

// Controlled failure hooks for meta-verification
if (getenv('FORCE_ASSERT_FAIL') === '1') {
    $runner->assertTrue("Forced Assertion Failure Test", false, "FORCE_ASSERT_FAIL=1");
}
if (getenv('FORCE_RUNTIME_EXCEPTION') === '1') {
    try { throw new RuntimeException('FORCE_RUNTIME_EXCEPTION=1'); } catch (Throwable $e) { $runner->recordException($e); $runner->finish(); }
}

$created_batch_ids = [];
$pdo = null;

try {
    $pdo = getDBConnection();
    $runner->setSetupCompleted($pdo !== null, "Database connection established");

    // TEST 1: Database Schema Columns on ai_generation_batches
    $requiredColumns = [
        'period_weighting_mode',
        'requested_period_distribution',
        'actual_period_distribution',
        'requested_question_blueprint',
        'actual_question_distribution',
        'requested_difficulty_distribution',
        'actual_difficulty_distribution',
        'duplicate_count',
        'replacement_attempt_count',
        'duplicate_warnings'
    ];

    $stmtCols = $pdo->query("SHOW COLUMNS FROM ai_generation_batches");
    $existingCols = $stmtCols->fetchAll(PDO::FETCH_COLUMN);
    $missingCols = array_diff($requiredColumns, $existingCols);
    $runner->assertTrue("TEST 1: Database schema columns on ai_generation_batches", empty($missingCols), empty($missingCols) ? "All 10 Priority 2 columns exist" : "Missing: " . implode(', ', $missingCols));

    // TEST 2: Equal Period Weighting Calculation
    $allPeriods = ['prelim', 'midterm', 'finals'];
    $eqWeight = GroqService::validateAndCalculatePeriodWeights('equal', [], $allPeriods, 9);
    $eqValid = ($eqWeight['mode'] === 'equal' && $eqWeight['target_counts']['prelim'] === 3 && $eqWeight['target_counts']['midterm'] === 3 && $eqWeight['target_counts']['finals'] === 3);
    $runner->assertTrue("TEST 2: Equal Period Weighting Calculation", $eqValid, "Target counts: " . json_encode($eqWeight['target_counts']));

    // TEST 3: Percentage Period Weighting Calculation
    $pctWeights = ['prelim' => 20, 'midterm' => 30, 'finals' => 50];
    $pctResult = GroqService::validateAndCalculatePeriodWeights('percentage', $pctWeights, $allPeriods, 10);
    $pctValid = ($pctResult['target_counts']['prelim'] === 2 && $pctResult['target_counts']['midterm'] === 3 && $pctResult['target_counts']['finals'] === 5);
    $runner->assertTrue("TEST 3: Percentage Period Weighting Calculation", $pctValid, "Target counts: " . json_encode($pctResult['target_counts']));

    // TEST 4: Reject Invalid Percentage Sum
    $badPctCaught = false;
    try {
        GroqService::validateAndCalculatePeriodWeights('percentage', ['prelim' => 50, 'midterm' => 40], $allPeriods, 10);
    } catch (InvalidArgumentException $e) {
        $badPctCaught = true;
    }
    $runner->assertTrue("TEST 4: Reject Invalid Percentage Sum (!= 100%)", $badPctCaught, "InvalidArgumentException caught as expected");

    // TEST 5: Fixed Count Period Weighting Calculation
    $fixedWeights = ['prelim' => 2, 'midterm' => 3, 'finals' => 5];
    $fixedResult = GroqService::validateAndCalculatePeriodWeights('fixed', $fixedWeights, $allPeriods, 10);
    $fixedValid = ($fixedResult['target_counts']['prelim'] === 2 && $fixedResult['target_counts']['midterm'] === 3 && $fixedResult['target_counts']['finals'] === 5);
    $runner->assertTrue("TEST 5: Fixed Count Period Weighting Calculation", $fixedValid, "Target counts: " . json_encode($fixedResult['target_counts']));

    // TEST 6: Reject Fixed Count Sum Mismatch
    $badFixedCaught = false;
    try {
        GroqService::validateAndCalculatePeriodWeights('fixed', ['prelim' => 2, 'midterm' => 3], $allPeriods, 10);
    } catch (InvalidArgumentException $e) {
        $badFixedCaught = true;
    }
    $runner->assertTrue("TEST 6: Reject Fixed Count Sum Mismatch", $badFixedCaught, "InvalidArgumentException caught as expected");

    // TEST 7: Question Blueprint Validation
    $blueprintInput = ['multiple_choice' => 5, 'true_false' => 3, 'identification' => 2];
    $bpResult = GroqService::validateAndCalculateBlueprint($blueprintInput, 10, 'multiple_choice');
    $bpValid = ($bpResult['target_counts']['multiple_choice'] === 5 && $bpResult['target_counts']['true_false'] === 3 && $bpResult['target_counts']['identification'] === 2);
    $runner->assertTrue("TEST 7: Multi-Type Question Blueprint Allocation", $bpValid, "Target counts: " . json_encode($bpResult['target_counts']));

    // TEST 8: Reject Invalid Blueprint Total
    $badBpCaught = false;
    try {
        GroqService::validateAndCalculateBlueprint(['multiple_choice' => 5, 'true_false' => 2], 10, 'multiple_choice');
    } catch (InvalidArgumentException $e) {
        $badBpCaught = true;
    }
    $runner->assertTrue("TEST 8: Reject Invalid Blueprint Total Count", $badBpCaught, "InvalidArgumentException caught as expected");

    // TEST 9: Difficulty Distribution Validation
    $diffInput = ['easy' => 3, 'medium' => 5, 'hard' => 2];
    $diffResult = GroqService::validateAndCalculateDifficulty('fixed', $diffInput, 10, 'medium');
    $diffValid = ($diffResult['target_counts']['easy'] === 3 && $diffResult['target_counts']['medium'] === 5 && $diffResult['target_counts']['hard'] === 2);
    $runner->assertTrue("TEST 9: Custom Difficulty Distribution Allocation", $diffValid, "Target counts: " . json_encode($diffResult['target_counts']));

    // TEST 10: Practical Deduplication Normalization
    $t1 = "What is the compressive strength of C30 concrete?";
    $t2 = "what is the compressive strength of c30 concrete?";
    $norm1 = GroqService::normalizeQuestionText($t1);
    $norm2 = GroqService::normalizeQuestionText($t2);
    similar_text($norm1, $norm2, $pct);
    $runner->assertTrue("TEST 10: Practical Deduplication Text Normalization & Similarity", ($norm1 === $norm2 && $pct >= 85.0), "Normalized string similarity: {$pct}%");

    // TEST 11: Metadata Persistence in ai_generation_batches
    $testBatchId = 'p2_verify_batch_' . bin2hex(random_bytes(8));
    $teacherId = 1;

    $stmtInsert = $pdo->prepare("
        INSERT INTO ai_generation_batches 
        (generation_batch_id, teacher_id, selected_lesson_ids, selected_lesson_titles, selected_periods, selected_subject,
         total_selected_words, estimated_tokens, ai_model, generation_duration, requested_question_count, generated_question_count,
         failed_question_count, warnings, batch_status, failed_chunk_count, affected_lesson_ids, failure_messages,
         period_weighting_mode, requested_period_distribution, actual_period_distribution, requested_question_blueprint,
         actual_question_distribution, requested_difficulty_distribution, actual_difficulty_distribution, duplicate_count,
         replacement_attempt_count, duplicate_warnings)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");

    $stmtInsert->execute([
        $testBatchId,
        $teacherId,
        json_encode([101, 102]),
        json_encode(['Prelim Structural', 'Midterm Geotechnical']),
        'prelim,midterm',
        'Structural Engineering',
        1500,
        375,
        'llama-3.3-70b-versatile',
        1.45,
        10,
        10,
        0,
        '[]',
        'completed',
        0,
        '[]',
        '[]',
        'percentage',
        json_encode(['prelim' => 40, 'midterm' => 60]),
        json_encode(['prelim' => 4, 'midterm' => 6]),
        json_encode(['multiple_choice' => 6, 'true_false' => 4]),
        json_encode(['multiple_choice' => 6, 'true_false' => 4]),
        json_encode(['easy' => 3, 'medium' => 5, 'hard' => 2]),
        json_encode(['easy' => 3, 'medium' => 5, 'hard' => 2, 'unclassified' => 0]),
        2,
        2,
        json_encode(['Filtered 2 duplicate items'])
    ]);
    $created_batch_ids[] = $testBatchId;

    $stmtFetch = $pdo->prepare("SELECT * FROM ai_generation_batches WHERE generation_batch_id = ?");
    $stmtFetch->execute([$testBatchId]);
    $batchRow = $stmtFetch->fetch(PDO::FETCH_ASSOC);

    $persistValid = ($batchRow && $batchRow['period_weighting_mode'] === 'percentage' && intval($batchRow['duplicate_count']) === 2 && intval($batchRow['replacement_attempt_count']) === 2);
    $runner->assertTrue("TEST 11: Priority 2 Metadata Persistence into DB", $persistValid, "Persisted batch ID: {$testBatchId}");

} catch (Throwable $e) {
    $runner->recordException($e);
} finally {
    if ($pdo !== null && !empty($created_batch_ids)) {
        try {
            $phB = implode(',', array_fill(0, count($created_batch_ids), '?'));
            $pdo->prepare("DELETE FROM ai_generation_batches WHERE generation_batch_id IN ($phB)")->execute($created_batch_ids);
        } catch (Throwable $cleanupError) {
            $runner->recordCleanupFailure("created_batch_ids", $cleanupError);
        }
    }
}

$runner->finish();
