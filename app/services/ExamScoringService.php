<?php

require_once __DIR__ . '/../database.php';
require_once __DIR__ . '/../../includes/security.php';

class ExamScoringService {

    

    public static function evaluateSingleAnswer($question, $studentAnswerRaw) {
        $qType = strtolower($question['type'] ?? strtolower($question['question_type'] ?? 'multiple_choice'));
        $correctAnswer = trim($question['correct_answer'] ?? '');
        $maxPoints = floatval($question['points'] ?? 1.00);

        $studentAnswerStr = is_array($studentAnswerRaw) ? json_encode($studentAnswerRaw) : trim((string)$studentAnswerRaw);

        if ($studentAnswerStr === '' || $studentAnswerRaw === null) {
            return [
                'question_id' => $question['id'],
                'question_type' => $qType,
                'student_answer' => '',
                'stored_correct_answer' => $correctAnswer,
                'awarded_points' => 0.00,
                'maximum_points' => $maxPoints,
                'evaluation_status' => 'unanswered',
                'evaluation_reason' => 'No answer provided by student.',
                'requires_review' => false,
                'confidence' => 100.00
            ];
        }

        $isCorrect = false;
        $reason = 'Incorrect answer.';
        $requiresReview = false;

        switch ($qType) {
            case 'multiple_choice':
                $normStudent = strtolower(trim($studentAnswerStr));
                $normCorrect = strtolower(trim($correctAnswer));

                $optToLetter = ['opt_a' => 'a', 'opt_b' => 'b', 'opt_c' => 'c', 'opt_d' => 'd'];
                $sLetter = $optToLetter[$normStudent] ?? $normStudent;
                $cLetter = $optToLetter[$normCorrect] ?? $normCorrect;

                if ($sLetter === $cLetter || $normStudent === $normCorrect) {
                    $isCorrect = true;
                    $reason = 'Correct option selected.';
                } else {
                    $reason = "Selected '{$studentAnswerStr}', correct option is '{$correctAnswer}'.";
                }
                break;

            case 'true_false':
                $normStudent = strtolower(trim($studentAnswerStr));
                $normCorrect = strtolower(trim($correctAnswer));

                $tfMap = [
                    'true' => 'true', 't' => 'true', '1' => 'true', 'yes' => 'true',
                    'false' => 'false', 'f' => 'false', '0' => 'false', 'no' => 'false'
                ];

                $stdVal = $tfMap[$normStudent] ?? $normStudent;
                $corVal = $tfMap[$normCorrect] ?? $normCorrect;

                if ($stdVal === $corVal) {
                    $isCorrect = true;
                    $reason = 'Correct True/False response.';
                } else {
                    $reason = "Submitted '{$studentAnswerStr}', correct value is '{$correctAnswer}'.";
                }
                break;

            case 'identification':
            case 'fill_blank':
            case 'fill_in_the_blank':
            case 'short_answer':
                $normStudent = strtolower(preg_replace('/\s+/', ' ', trim($studentAnswerStr)));
                $normCorrect = strtolower(preg_replace('/\s+/', ' ', trim($correctAnswer)));

                if ($normStudent === $normCorrect) {
                    $isCorrect = true;
                    $reason = 'Exact match on identification term.';
                } else {
                    $acceptedList = array_map('trim', preg_split('/[,|]/', $normCorrect));
                    if (in_array($normStudent, $acceptedList, true)) {
                        $isCorrect = true;
                        $reason = 'Match found in accepted answer list.';
                    } else {
                        $reason = "Submitted '{$studentAnswerStr}', expected '{$correctAnswer}'.";
                    }
                }
                break;

            case 'matching':
            case 'matching_type':
                $normStudent = strtolower(preg_replace('/\s+/', '', trim($studentAnswerStr)));
                $normCorrect = strtolower(preg_replace('/\s+/', '', trim($correctAnswer)));
                $matchingPairsRaw = $question['matching_pairs'] ?? null;
                $normPairs = $matchingPairsRaw ? strtolower(preg_replace('/\s+/', '', is_array($matchingPairsRaw) ? json_encode($matchingPairsRaw) : $matchingPairsRaw)) : '';

                if (($normCorrect !== '' && $normStudent === $normCorrect) || ($normPairs !== '' && $normStudent === $normPairs)) {
                    $isCorrect = true;
                    $reason = 'Correct matching pairs submitted.';
                } else {
                    $stdDec = json_decode($studentAnswerStr, true);
                    $corDec = json_decode($correctAnswer, true) ?: (is_array($matchingPairsRaw) ? $matchingPairsRaw : json_decode($matchingPairsRaw ?? '', true));
                    if (is_array($stdDec) && is_array($corDec) && $stdDec == $corDec) {
                        $isCorrect = true;
                        $reason = 'Matching pair selections match answer key.';
                    } else {
                        $reason = "Submitted matching pairs do not match answer key.";
                    }
                }
                break;

            case 'problem_solving':
                $requiresReview = true;
                $reason = 'Manual teacher evaluation required for problem solving item.';
                break;

            case 'math_formula':
                $normStudent = strtolower(preg_replace('/\s+/', '', trim($studentAnswerStr)));
                $normCorrect = strtolower(preg_replace('/\s+/', '', trim($correctAnswer)));
                if (!empty($normCorrect) && $normStudent === $normCorrect) {
                    $isCorrect = true;
                    $reason = 'Exact formula expression match.';
                } else {
                    $requiresReview = true;
                    $reason = 'Manual teacher evaluation required for math formula item.';
                }
                break;

            default:
                $normStudent = strtolower(trim($studentAnswerStr));
                $normCorrect = strtolower(trim($correctAnswer));
                if ($normStudent === $normCorrect) {
                    $isCorrect = true;
                    $reason = 'Correct response.';
                }
                break;
        }

        $awardedPoints = $isCorrect ? $maxPoints : 0.00;
        $evalStatus = $requiresReview ? 'requires_review' : ($isCorrect ? 'correct' : 'incorrect');

        return [
            'question_id' => $question['id'],
            'question_type' => $qType,
            'student_answer' => $studentAnswerStr,
            'stored_correct_answer' => $correctAnswer,
            'awarded_points' => round($awardedPoints, 2),
            'maximum_points' => round($maxPoints, 2),
            'evaluation_status' => $evalStatus,
            'evaluation_reason' => $reason,
            'requires_review' => $requiresReview,
            'confidence' => 100.00
        ];
    }

    

    public static function evaluateAndSaveSubmission($examId, $studentId, $submittedAnswers, $teacherId = null, $uploadType = 'online', $fileMeta = []) {
        $pdo = getDBConnection();

        if (empty($examId)) {
            throw new InvalidArgumentException("Exam ID is required.");
        }

        
        $stmt = $pdo->prepare("SELECT * FROM exams WHERE id = ?");
        $stmt->execute([$examId]);
        $exam = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$exam) {
            throw new Exception("Exam #{$examId} not found in database.");
        }

        $passingThreshold = floatval($exam['passing_percentage'] ?? 75.00);

        
        $stmt = $pdo->prepare("SELECT * FROM exam_questions WHERE exam_id = ? ORDER BY id ASC");
        $stmt->execute([$examId]);
        $questions = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($questions)) {
            throw new Exception("Exam #{$examId} has no questions configured.");
        }

        
        $indexedQuestions = [];
        foreach ($questions as $q) {
            $indexedQuestions[$q['id']] = $q;
        }

        $resolvedTeacherId = $teacherId ?: ($exam['teacher_id'] ?? ($exam['created_by'] ?? 1));
        $studentIdDb = ($studentId && $studentId > 0) ? $studentId : null;

        
        $pdo->beginTransaction();

        try {
            $totalAwardedPoints = 0.00;
            $totalPossiblePoints = 0.00;
            $correctCount = 0;
            $wrongCount = 0;
            $reviewRequiredCount = 0;
            $itemResults = [];

            
            foreach ($indexedQuestions as $qId => $q) {
                $maxPoints = floatval($q['points'] ?? 1.00);
                $totalPossiblePoints += $maxPoints;

                $studentAnswerRaw = $submittedAnswers[$qId] ?? null;

                $itemEval = self::evaluateSingleAnswer($q, $studentAnswerRaw);

                if ($itemEval['evaluation_status'] === 'correct') {
                    $correctCount++;
                } elseif ($itemEval['requires_review']) {
                    $reviewRequiredCount++;
                } else {
                    $wrongCount++;
                }

                $totalAwardedPoints += $itemEval['awarded_points'];
                $itemResults[] = $itemEval;
            }

            $percentage = ($totalPossiblePoints > 0) ? round(($totalAwardedPoints / $totalPossiblePoints) * 100, 2) : 0.00;
            $passOrFail = ($percentage >= $passingThreshold) ? 'Pass' : 'Fail';

            $ocrConf = floatval($fileMeta['ocr_confidence'] ?? 100.00);
            $manualRev = intval($fileMeta['suggested_manual_review'] ?? 0);
            $reviewStatus = ($reviewRequiredCount > 0 || $ocrConf < 75.00 || $manualRev === 1) ? 'pending_review' : 'finalized';

            $studentName = 'Guest Student';
            if ($studentIdDb) {
                $uStmt = $pdo->prepare("SELECT fullname FROM users WHERE id = ?");
                $uStmt->execute([$studentIdDb]);
                $uName = $uStmt->fetchColumn();
                if ($uName) $studentName = $uName;
            }

            
            $attemptNumber = 1;
            if ($studentIdDb) {
                $stmtCount = $pdo->prepare("SELECT COUNT(*) FROM exam_submissions WHERE exam_id = ? AND student_id = ?");
                $stmtCount->execute([$examId, $studentIdDb]);
                $attemptNumber = intval($stmtCount->fetchColumn()) + 1;
            }

            $qualificationStatus = 'pending';
            $examCategory = $exam['exam_category'] ?? 'regular';
            if ($examCategory === 'qualifying') {
                $qualThreshold = floatval($exam['qualifying_passing_percentage'] ?? 75.00);
                $qualificationStatus = ($percentage >= $qualThreshold) ? 'qualified' : 'not_qualified';
            }

            $stmt = $pdo->prepare("
                INSERT INTO exam_submissions (
                    exam_id, student_id, teacher_id, student_name, exam_title, upload_type,
                    correct_count, wrong_count, total_score, total_possible_score, total_items,
                    percentage, status, ocr_text, ocr_confidence, ocr_status, suggested_manual_review,
                    page_count, evaluation_result, review_status, file_path, original_filename,
                    qualification_status, attempt_number, created_at
                ) VALUES (
                    ?, ?, ?, ?, ?, ?,
                    ?, ?, ?, ?, ?,
                    ?, ?, ?, ?, ?, ?,
                    ?, ?, ?, ?, ?,
                    ?, ?, NOW()
                )
            ");

            $stmt->execute([
                $examId,
                $studentIdDb,
                $resolvedTeacherId,
                $studentName,
                $exam['title'],
                $uploadType,
                $correctCount,
                $wrongCount,
                $totalAwardedPoints,
                $totalPossiblePoints,
                count($questions),
                $percentage,
                $passOrFail,
                $fileMeta['ocr_text'] ?? null,
                $fileMeta['ocr_confidence'] ?? 100.00,
                $fileMeta['ocr_status'] ?? 'completed',
                $fileMeta['suggested_manual_review'] ?? 0,
                $fileMeta['page_count'] ?? 1,
                json_encode($itemResults),
                $reviewStatus,
                $fileMeta['file_path'] ?? null,
                $fileMeta['original_filename'] ?? null,
                $qualificationStatus,
                $attemptNumber
            ]);

            $submissionId = $pdo->lastInsertId();

            $stmtAnswer = $pdo->prepare("
                INSERT INTO submission_answers (
                    submission_id, exam_id, student_id, question_id, student_answer,
                    correct_answer, awarded_points, max_points, evaluation_status, evaluation_reason,
                    confidence, requires_review, created_at
                ) VALUES (
                    ?, ?, ?, ?, ?,
                    ?, ?, ?, ?, ?,
                    ?, ?, NOW()
                )
                ON DUPLICATE KEY UPDATE
                    student_answer = VALUES(student_answer),
                    awarded_points = VALUES(awarded_points),
                    evaluation_status = VALUES(evaluation_status),
                    evaluation_reason = VALUES(evaluation_reason)
            ");

            foreach ($itemResults as $item) {
                $stmtAnswer->execute([
                    $submissionId,
                    $examId,
                    $studentIdDb,
                    $item['question_id'],
                    $item['student_answer'],
                    $item['stored_correct_answer'],
                    $item['awarded_points'],
                    $item['maximum_points'],
                    $item['evaluation_status'],
                    $item['evaluation_reason'],
                    $item['confidence'],
                    $item['requires_review'] ? 1 : 0
                ]);
            }

            $pdo->commit();

            return [
                'success' => true,
                'submission_id' => $submissionId,
                'total_score' => $totalAwardedPoints,
                'total_awarded_points' => $totalAwardedPoints,
                'total_possible_points' => $totalPossiblePoints,
                'correct_count' => $correctCount,
                'wrong_count' => $wrongCount,
                'incorrect_count' => $wrongCount,
                'review_required_count' => $reviewRequiredCount,
                'percentage' => $percentage,
                'status' => $passOrFail,
                'pass_or_fail' => $passOrFail,
                'review_status' => $reviewStatus,
                'item_results' => $itemResults
            ];

        } catch (Exception $e) {
            $pdo->rollBack();
            throw $e;
        }
    }
}
