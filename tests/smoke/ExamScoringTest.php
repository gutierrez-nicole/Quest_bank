<?php
/**
 * QUESTBANK SMOKE SUITE — EXAM SCORING LOGIC
 */

require_once __DIR__ . '/../../app/services/ExamScoringService.php';

function test_exam_scoring_smoke($pdo) {
    echo "  [TEST] Server-Side Exam Scoring Engine...\n";

    $question = [
        'question_type' => 'multiple_choice',
        'correct_answer' => '75 mm',
        'points' => 1.00
    ];

    $evalCorrect = ExamScoringService::evaluateSingleAnswer($question, '75 mm');
    if ($evalCorrect['evaluation_status'] !== 'correct' || (float)$evalCorrect['awarded_points'] !== 1.0) {
        throw new Exception("Scoring smoke test failed for correct answer evaluation");
    }

    $evalIncorrect = ExamScoringService::evaluateSingleAnswer($question, '25 mm');
    if ($evalIncorrect['evaluation_status'] !== 'incorrect' || (float)$evalIncorrect['awarded_points'] !== 0.0) {
        throw new Exception("Scoring smoke test failed for incorrect answer evaluation");
    }

    echo "    [✓] Scoring engine calculation verified (Single item evaluation correct & incorrect)\n";
    return true;
}
