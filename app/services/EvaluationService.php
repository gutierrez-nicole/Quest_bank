<?php

require_once __DIR__ . '/../database.php';
require_once __DIR__ . '/../../includes/security.php';
require_once __DIR__ . '/ExamScoringService.php';

class EvaluationService {

    /**
     * Evaluate student submission server-side against stored answer keys and persist detailed item results.
     */
    public static function evaluateAndSaveSubmission($examId, $studentId, array $submittedAnswers, $uploadType = 'online', $ocrResult = null, $fileInfo = []) {
        $fileMeta = [
            'ocr_text' => $ocrResult['text'] ?? ($ocrResult['ocr_text'] ?? null),
            'ocr_confidence' => $ocrResult['confidence'] ?? 100.00,
            'ocr_status' => $ocrResult['status'] ?? 'completed',
            'suggested_manual_review' => !empty($ocrResult['suggested_manual_review']) ? 1 : 0,
            'page_count' => $ocrResult['page_count'] ?? 1,
            'file_path' => $fileInfo['file_path'] ?? null,
            'original_filename' => $fileInfo['original_filename'] ?? null
        ];

        try {
            $res = ExamScoringService::evaluateAndSaveSubmission(
                $examId,
                $studentId,
                $submittedAnswers,
                $fileInfo['teacher_id'] ?? null,
                $uploadType,
                $fileMeta
            );
            $res['total_score'] = $res['total_awarded_points'];
            $res['status'] = $res['pass_or_fail'];
            return $res;
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Evaluate a single answer against a question model
     */
    public static function evaluateSingleAnswer($question, $studentAnswer) {
        return ExamScoringService::evaluateSingleAnswer($question, $studentAnswer);
    }
}
