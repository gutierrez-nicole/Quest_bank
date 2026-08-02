<?php

require_once __DIR__ . '/../database.php';
require_once __DIR__ . '/../../includes/security.php';

class EvaluationService {

    /**
     * Evaluate student submission server-side against stored answer keys and persist detailed item results.
     */
    public static function evaluateAndSaveSubmission($examId, $studentId, array $submittedAnswers, $uploadType = 'online', $ocrResult = null, $fileInfo = []) {
        $pdo = getDBConnection();

        // 1. Load Exam
        $stmtExam = $pdo->prepare("SELECT * FROM exams WHERE id = ?");
        $stmtExam->execute([$examId]);
        $exam = $stmtExam->fetch(PDO::FETCH_ASSOC);

        if (!$exam) {
            return ['success' => false, 'error' => 'Exam not found.'];
        }

        // 2. Load Student User
        $studentName = 'Student User';
        if ($studentId > 0) {
            $stmtUser = $pdo->prepare("SELECT fullname FROM users WHERE id = ?");
            $stmtUser->execute([$studentId]);
            $u = $stmtUser->fetch(PDO::FETCH_ASSOC);
            if ($u && !empty($u['fullname'])) {
                $studentName = $u['fullname'];
            }
        }

        // 3. Load Exam Questions & Stored Answer Keys
        $stmtQ = $pdo->prepare("SELECT * FROM exam_questions WHERE exam_id = ? ORDER BY id ASC");
        $stmtQ->execute([$examId]);
        $questions = $stmtQ->fetchAll(PDO::FETCH_ASSOC);

        if (empty($questions)) {
            return ['success' => false, 'error' => 'Exam has no stored questions or answer keys.'];
        }

        // 4. Evaluate each item server-side
        $itemEvaluations = [];
        $totalAwardedPoints = 0.0;
        $totalPossiblePoints = 0.0;
        $correctCount = 0;
        $wrongCount = 0;
        $requiresReview = false;

        $processedQuestionIds = [];

        foreach ($questions as $index => $q) {
            $qId = (int)$q['id'];
            $maxPoints = (float)($q['points'] ?? 1.0);
            $totalPossiblePoints += $maxPoints;

            // Extract student answer for this question ID (or index if keyed by 1-based index)
            $rawStudentAnswer = '';
            if (isset($submittedAnswers[$qId])) {
                $rawStudentAnswer = $submittedAnswers[$qId];
            } elseif (isset($submittedAnswers[$index + 1])) {
                $rawStudentAnswer = $submittedAnswers[$index + 1];
            } elseif (isset($submittedAnswers[(string)($index + 1)])) {
                $rawStudentAnswer = $submittedAnswers[(string)($index + 1)];
            }

            // Deduplication check
            if (in_array($qId, $processedQuestionIds)) {
                continue;
            }
            $processedQuestionIds[] = $qId;

            $eval = self::evaluateSingleAnswer($q, $rawStudentAnswer);

            $awarded = $eval['is_correct'] ? $maxPoints : ($eval['is_partial'] ? round($maxPoints * 0.5, 2) : 0.0);
            $totalAwardedPoints += $awarded;

            if ($eval['is_correct']) {
                $correctCount++;
            } else {
                $wrongCount++;
            }

            if ($eval['requires_review']) {
                $requiresReview = true;
            }

            $itemEvaluations[] = [
                'question_id' => $qId,
                'question_text' => $q['question_text'],
                'question_type' => $q['question_type'],
                'student_answer' => $eval['normalized_student_answer'],
                'correct_answer' => $q['correct_answer'],
                'awarded_points' => $awarded,
                'max_points' => $maxPoints,
                'evaluation_status' => $eval['status'],
                'evaluation_reason' => $eval['reason'],
                'confidence' => $eval['confidence'],
                'requires_review' => $eval['requires_review']
            ];
        }

        // 5. Server-side score computation
        $percentage = $totalPossiblePoints > 0 ? round(($totalAwardedPoints / $totalPossiblePoints) * 100, 2) : 0.00;
        $passingPercentage = (float)($exam['passing_percentage'] ?? 75.00);
        $passFailStatus = ($percentage >= $passingPercentage) ? 'Pass' : 'Fail';

        $ocrStatus = $ocrResult['status'] ?? 'pending';
        $ocrConfidence = (float)($ocrResult['confidence'] ?? 100.00);
        $ocrText = $ocrResult['ocr_text'] ?? null;
        $ocrError = $ocrResult['ocr_error'] ?? null;

        // If low OCR confidence or manual review flagged, set review status
        $reviewStatus = ($uploadType === 'ocr' || $requiresReview || $ocrConfidence < 75.0) ? 'pending_review' : 'finalized';

        try {
            $pdo->beginTransaction();

            // Insert into exam_submissions
            $stmtSub = $pdo->prepare("
                INSERT INTO exam_submissions 
                (teacher_id, student_id, exam_id, student_name, exam_title, upload_type, correct_count, wrong_count, total_score, total_possible_score, total_items, percentage, status, review_status, ocr_text, ocr_confidence, ocr_status, ocr_error, suggested_manual_review, file_path, original_filename, uploaded_file_hash, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            $stmtSub->execute([
                $exam['teacher_id'],
                $studentId ?: null,
                $examId,
                $studentName,
                $exam['title'],
                $uploadType,
                $correctCount,
                $wrongCount,
                $totalAwardedPoints,
                $totalPossiblePoints,
                count($questions),
                $percentage,
                $passFailStatus,
                $reviewStatus,
                $ocrText,
                $ocrConfidence,
                $ocrStatus,
                $ocrError,
                $requiresReview ? 1 : 0,
                $fileInfo['file_path'] ?? null,
                $fileInfo['original_filename'] ?? null,
                $fileInfo['file_hash'] ?? null
            ]);
            $submissionId = $pdo->lastInsertId();

            // Insert item-level records into submission_answers
            $stmtAns = $pdo->prepare("
                INSERT INTO submission_answers 
                (submission_id, exam_id, student_id, question_id, student_answer, correct_answer, awarded_points, max_points, evaluation_status, evaluation_reason, confidence, requires_review, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ");

            foreach ($itemEvaluations as $item) {
                $stmtAns->execute([
                    $submissionId,
                    $examId,
                    $studentId ?: null,
                    $item['question_id'],
                    $item['student_answer'],
                    $item['correct_answer'],
                    $item['awarded_points'],
                    $item['max_points'],
                    $item['evaluation_status'],
                    $item['evaluation_reason'],
                    $item['confidence'],
                    $item['requires_review'] ? 1 : 0
                ]);
            }

            $pdo->commit();

            logActivity("Evaluated and saved submission #{$submissionId} for student '{$studentName}' on exam '{$exam['title']}' (Score: {$percentage}%, Status: {$passFailStatus}).", $studentId ?: null);

            return [
                'success' => true,
                'submission_id' => $submissionId,
                'exam_id' => $examId,
                'student_id' => $studentId,
                'correct_count' => $correctCount,
                'wrong_count' => $wrongCount,
                'total_score' => $totalAwardedPoints,
                'total_possible' => $totalPossiblePoints,
                'percentage' => $percentage,
                'status' => $passFailStatus,
                'review_status' => $reviewStatus,
                'item_evaluations' => $itemEvaluations
            ];

        } catch (PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            return ['success' => false, 'error' => 'Database error while saving evaluation: ' . $e->getMessage()];
        }
    }

    /**
     * Evaluate single student answer against question definition and correct answer.
     */

    public static function evaluateSingleAnswer(array $question, $studentAnswer) {
        $qType = strtolower(trim($question['question_type'] ?? 'multiple_choice'));
        $correctRaw = trim($question['correct_answer'] ?? '');
        $studentRaw = trim((string)$studentAnswer);

        if ($studentRaw === '') {
            return [
                'is_correct' => false,
                'is_partial' => false,
                'status' => 'unanswered',
                'reason' => 'No answer submitted by student.',
                'confidence' => 100.0,
                'requires_review' => false,
                'normalized_student_answer' => ''
            ];
        }

        switch ($qType) {
            case 'multiple_choice':
                return self::evaluateMultipleChoice($question, $studentRaw, $correctRaw);

            case 'true_false':
                return self::evaluateTrueFalse($studentRaw, $correctRaw);

            case 'identification':
                return self::evaluateIdentification($studentRaw, $correctRaw);

            default:
                // Case-insensitive trimmed string match for other types
                $normStudent = mb_strtolower(preg_replace('/\s+/', ' ', $studentRaw));
                $normCorrect = mb_strtolower(preg_replace('/\s+/', ' ', $correctRaw));
                $isMatch = ($normStudent === $normCorrect);
                return [
                    'is_correct' => $isMatch,
                    'is_partial' => false,
                    'status' => $isMatch ? 'correct' : 'incorrect',
                    'reason' => $isMatch ? 'Exact match.' : "Submitted '{$studentRaw}' does not match key '{$correctRaw}'.",
                    'confidence' => 100.0,
                    'requires_review' => !$isMatch,
                    'normalized_student_answer' => $studentRaw
                ];
        }
    }

    private static function evaluateMultipleChoice($question, $studentAns, $correctKey) {
        $normStudent = mb_strtoupper(trim($studentAns));
        $normCorrect = mb_strtoupper(trim($correctKey));

        // Check single letter match (A, B, C, D)
        if ($normStudent === $normCorrect) {
            return [
                'is_correct' => true,
                'is_partial' => false,
                'status' => 'correct',
                'reason' => 'Exact option choice match.',
                'confidence' => 100.0,
                'requires_review' => false,
                'normalized_student_answer' => $normStudent
            ];
        }

        // Check if student submitted full option text matching option A, B, C, or D
        $options = [
            'A' => trim($question['option_a'] ?? ''),
            'B' => trim($question['option_b'] ?? ''),
            'C' => trim($question['option_c'] ?? ''),
            'D' => trim($question['option_d'] ?? '')
        ];

        foreach ($options as $key => $optText) {
            if ($optText !== '' && mb_strtolower($studentAns) === mb_strtolower($optText)) {
                $isMatch = ($key === $normCorrect);
                return [
                    'is_correct' => $isMatch,
                    'is_partial' => false,
                    'status' => $isMatch ? 'correct' : 'incorrect',
                    'reason' => $isMatch ? 'Option text match.' : "Selected option '{$key}' does not match correct option '{$normCorrect}'.",
                    'confidence' => 100.0,
                    'requires_review' => false,
                    'normalized_student_answer' => $key
                ];
            }
        }

        return [
            'is_correct' => false,
            'is_partial' => false,
            'status' => 'incorrect',
            'reason' => "Selected choice '{$studentAns}' does not match correct choice '{$correctKey}'.",
            'confidence' => 100.0,
            'requires_review' => false,
            'normalized_student_answer' => $studentAns
        ];
    }

    private static function evaluateTrueFalse($studentAns, $correctKey) {
        $trueVariants = ['true', 't', '1', 'yes'];
        $falseVariants = ['false', 'f', '0', 'no'];

        $sNorm = mb_strtolower(trim($studentAns));
        $cNorm = mb_strtolower(trim($correctKey));

        $sBool = in_array($sNorm, $trueVariants) ? 'true' : (in_array($sNorm, $falseVariants) ? 'false' : $sNorm);
        $cBool = in_array($cNorm, $trueVariants) ? 'true' : (in_array($cNorm, $falseVariants) ? 'false' : $cNorm);

        $isMatch = ($sBool === $cBool);

        return [
            'is_correct' => $isMatch,
            'is_partial' => false,
            'status' => $isMatch ? 'correct' : 'incorrect',
            'reason' => $isMatch ? 'Boolean value match.' : "Submitted '{$studentAns}' does not match correct boolean '{$correctKey}'.",
            'confidence' => 100.0,
            'requires_review' => false,
            'normalized_student_answer' => ucfirst($sBool)
        ];
    }

    private static function evaluateIdentification($studentAns, $correctKey) {
        // Normalize: lowercase, collapse whitespace, strip edge punctuation
        $cleanStudent = trim(preg_replace('/[^\w\s\.-]/u', '', mb_strtolower($studentAns)));
        $cleanCorrect = trim(preg_replace('/[^\w\s\.-]/u', '', mb_strtolower($correctKey)));
        $cleanStudent = preg_replace('/\s+/', ' ', $cleanStudent);
        $cleanCorrect = preg_replace('/\s+/', ' ', $cleanCorrect);

        // Support accepted alternative answers separated by | or comma in key
        $acceptedAnswers = array_map('trim', preg_split('/[|;]/', $cleanCorrect));

        $isMatch = in_array($cleanStudent, $acceptedAnswers);

        // Check partial string similarity / levenshtein distance for slight typos
        $isPartial = false;
        if (!$isMatch && strlen($cleanStudent) > 3) {
            foreach ($acceptedAnswers as $acc) {
                if (levenshtein($cleanStudent, $acc) <= 2) {
                    $isPartial = true;
                    break;
                }
            }
        }

        return [
            'is_correct' => $isMatch,
            'is_partial' => $isPartial,
            'status' => $isMatch ? 'correct' : ($isPartial ? 'partially_correct' : 'incorrect'),
            'reason' => $isMatch ? 'Identification text match.' : ($isPartial ? 'Near-match/spelling variation detected. Flagged for review.' : "Submitted text does not match expected key '{$correctKey}'."),
            'confidence' => $isMatch ? 100.0 : ($isPartial ? 70.0 : 95.0),
            'requires_review' => $isPartial,
            'normalized_student_answer' => trim($studentAns)
        ];
    }

    /**
     * Parse OCR line items (e.g., "1. A\n2. B\n3. Flexural Beam") into an array of question answers.
     */
    public static function parseOcrTextToAnswers($ocrText) {
        $lines = explode("\n", (string)$ocrText);
        $parsed = [];

        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) continue;

            if (preg_match('/^(\d+)[\.\)\:\-]\s*(.+)$/i', $line, $m)) {
                $num = intval($m[1]);
                $ans = trim($m[2]);
                $parsed[$num] = $ans;
            }
        }

        return $parsed;
    }
}
