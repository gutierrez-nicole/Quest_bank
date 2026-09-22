<?php

require_once __DIR__ . '/../database.php';
require_once __DIR__ . '/../../includes/security.php';

class ExamScoringService {

    

    /** Normalize for comparison only: never replace the recorded student answer. */
    public static function normalizeAnswer(string $answer): string {
        $text = html_entity_decode(strip_tags($answer), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        return mb_strtolower(trim(preg_replace('/[\s\x{00a0}]+/u', ' ', $text)), 'UTF-8');
    }

    public static function numericAnswer(string $answer): ?float {
        $text = self::normalizeAnswer($answer);
        // Full-string match: no substring search or extraction from worked solutions.
        if (!preg_match('/^(?:[a-z]\s*=\s*)?([+-]?(?:\d+(?:\.\d*)?|\.\d+)(?:e[+-]?\d+)?)$/i', $text, $m)) return null;
        $value = (float)$m[1];
        return is_finite($value) ? $value : null;
    }

    public static function answersMatch(string $student, string $key, array $question = []): bool {
        if (self::normalizeAnswer($key) === '') return false;
        $a = self::numericAnswer($student);
        $b = self::numericAnswer($key);
        if ($a !== null && $b !== null && empty($question['expected_unit'])) {
            $tolerance = max(0.0, (float)($question['tolerance'] ?? 0));
            if ($tolerance > 0 && is_finite($tolerance)) return abs($a - $b) <= $tolerance;
            // Exact decimal normalization avoids float rounding accepting adjacent large integers
            // or treating a tiny nonzero answer as zero when no tolerance was configured.
            $canonicalStudent = self::canonicalDecimal($student);
            $canonicalKey = self::canonicalDecimal($key);
            return $canonicalStudent !== null && $canonicalStudent === $canonicalKey;
        }
        return self::normalizeAnswer($student) === self::normalizeAnswer($key);
    }

    private static function canonicalDecimal(string $answer): ?string {
        $text = preg_replace('/^[a-z]\s*=\s*/', '', self::normalizeAnswer($answer));
        if (!preg_match('/^([+-]?)(\d*\.?\d+|\d+\.)(?:e([+-]?\d+))?$/', $text, $m)) return null;
        $exponentText = $m[3] ?? '0';
        if (strlen(ltrim($exponentText, '+-0')) > 4) return null;
        $exponent = (int)$exponentText;
        $decimal = strpos($m[2], '.');
        if ($decimal !== false) $exponent -= strlen($m[2]) - $decimal - 1;
        $digits = ltrim(str_replace('.', '', $m[2]), '0');
        if ($digits === '') return '0';
        $trimmed = rtrim($digits, '0');
        $exponent += strlen($digits) - strlen($trimmed);
        return ($m[1] === '-' ? '-' : '') . $trimmed . 'e' . $exponent;
    }

    private static function choiceLetter(string $answer, array $question): ?string {
        $answer = self::normalizeAnswer($answer);
        foreach (['a','b','c','d'] as $letter) {
            $option = self::normalizeAnswer((string)($question['option_'.$letter] ?? $question['opt_'.$letter] ?? ''));
            if ($answer === $letter || $answer === 'opt_'.$letter || ($option !== '' && ($answer === $option || $answer === $letter.'. '.$option || $answer === $letter.') '.$option))) return $letter;
        }
        return null;
    }

    public static function evaluateSingleAnswer($question, $studentAnswerRaw) {
        $qType = strtolower(trim($question['question_type'] ?? $question['type'] ?? 'multiple_choice'));
        $correctAnswer = trim($question['correct_answer'] ?? '');
        $maxPoints = floatval($question['points'] ?? 1.00);

        $studentAnswerStr = is_array($studentAnswerRaw) ? json_encode($studentAnswerRaw) : trim((string)$studentAnswerRaw);

        if ($studentAnswerStr === '' || $studentAnswerRaw === null) {
            return [
                'question_id' => $question['id'] ?? ($question['question_id'] ?? 0),
                'question_type' => $qType,
                'student_answer' => '',
                'stored_correct_answer' => $correctAnswer,
                'awarded_points' => 0.00,
                'maximum_points' => $maxPoints,
                'is_correct' => false,
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
                $sLetter = self::choiceLetter($studentAnswerStr, $question);
                $cLetter = self::choiceLetter($correctAnswer, $question);
                $isCorrect = ($sLetter !== null && $cLetter !== null) ? $sLetter === $cLetter : self::normalizeAnswer($studentAnswerStr) === self::normalizeAnswer($correctAnswer);
                $isCorrect = $correctAnswer !== '' && $isCorrect;
                $reason = $isCorrect ? 'Correct option selected.' : 'Selected option does not match the answer key.';
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

                if (self::answersMatch($studentAnswerStr, $correctAnswer, $question)) {
                    $isCorrect = true;
                    $reason = 'Answer matches the key after normalization.';
                } else {
                    $acceptedList = array_map('trim', preg_split('/[,|]/', $normCorrect));
                    if (count(array_filter($acceptedList, fn($key) => self::answersMatch($studentAnswerStr, $key, $question))) > 0) {
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
                if (empty($question['rubric_json']) && empty($question['partial_credit_enabled']) && empty($question['expected_unit']) && self::numericAnswer($correctAnswer) !== null && self::numericAnswer($studentAnswerStr) !== null) {
                    $isCorrect = self::answersMatch($studentAnswerStr, $correctAnswer, $question);
                    $reason = $isCorrect ? 'Numeric final answer matches the key.' : 'Numeric final answer does not match the key.';
                } else {
                    $requiresReview = true;
                    $reason = 'Teacher evaluation required for worked solutions, rubrics or non-numeric responses.';
                }
                break;

            case 'math_formula':
                $normStudent = strtolower(preg_replace('/\s+/', '', trim($studentAnswerStr)));
                $normCorrect = strtolower(preg_replace('/\s+/', '', trim($correctAnswer)));
                if ($normCorrect !== '' && (self::answersMatch($studentAnswerStr, $correctAnswer, $question) || $normStudent === $normCorrect)) {
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
            'question_id' => $question['id'] ?? ($question['question_id'] ?? 0),
            'question_text' => $question['question_text'] ?? '',
            'question_type' => $qType,
            'student_answer' => $studentAnswerStr,
            'stored_correct_answer' => $correctAnswer,
            'explanation' => $question['explanation'] ?? '',
            'awarded_points' => round($awardedPoints, 2),
            'maximum_points' => round($maxPoints, 2),
            'is_correct' => ($evalStatus === 'correct'),
            'evaluation_status' => $evalStatus,
            'evaluation_reason' => $reason,
            'requires_review' => $requiresReview,
            'confidence' => 100.00
        ];
    }

    public static function evaluateSingleItem($question, $studentAnswerRaw) {
        return self::evaluateSingleAnswer($question, $studentAnswerRaw);
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

            $isOcr = $uploadType !== 'online';
            $ocrConf = $isOcr && isset($fileMeta['ocr_confidence']) ? (float)$fileMeta['ocr_confidence'] : null;
            if ($ocrConf !== null && (!is_finite($ocrConf) || $ocrConf < 0 || $ocrConf > 100)) $ocrConf = null;
            $ocrIncomplete = $isOcr && (trim($fileMeta['ocr_text'] ?? '') === '' || ($fileMeta['ocr_status'] ?? 'failed') !== 'completed' || ($ocrConf !== null && $ocrConf < 75.0) || ($ocrConf === null && ($fileMeta['extraction_mode'] ?? '') !== 'native_pdf_text'));
            $manualRev = (int)($ocrIncomplete || !empty($fileMeta['suggested_manual_review']));
            $reviewStatus = ($reviewRequiredCount > 0 || $manualRev === 1) ? 'pending_review' : 'finalized';

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
                $ocrConf,
                $isOcr ? ($fileMeta['ocr_status'] ?? 'failed') : 'pending',
                $manualRev,
                $fileMeta['page_count'] ?? 1,
                json_encode($itemResults),
                $reviewStatus,
                $fileMeta['file_path'] ?? null,
                $fileMeta['original_filename'] ?? null,
                $qualificationStatus,
                $attemptNumber
            ]);

            $submissionId = $pdo->lastInsertId();
            $pdo->prepare("UPDATE exam_submissions SET original_ocr_text = ?, corrected_ocr_text = ?, extraction_mode = ?, per_page_ocr_metadata = ?, ocr_error = ? WHERE id = ?")
                ->execute([$isOcr ? ($fileMeta['ocr_text'] ?? null) : null, $fileMeta['corrected_ocr_text'] ?? null,
                    $isOcr ? ($fileMeta['extraction_mode'] ?? 'unknown') : 'not_applicable',
                    isset($fileMeta['pages']) ? json_encode($fileMeta['pages']) : null, $fileMeta['ocr_error'] ?? null, $submissionId]);


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
