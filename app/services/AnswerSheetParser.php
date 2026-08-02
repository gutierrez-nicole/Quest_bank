<?php

class AnswerSheetParser {

    /**
     * Parse raw OCR text into structured question-number to answer mappings
     */
    public static function parseAnswerSheet($ocrText, array $examQuestions = []) {
        if (empty(trim($ocrText))) {
            return [
                'answers' => [],
                'warnings' => ['OCR text is empty.'],
                'missing_numbers' => [],
                'duplicate_numbers' => [],
                'unmatched_text' => [],
                'requires_review' => true
            ];
        }

        $lines = explode("\n", str_replace(["\r\n", "\r"], "\n", $ocrText));
        $parsedAnswers = [];
        $rawAnswers = [];
        $duplicateNumbers = [];
        $unmatchedText = [];
        $warnings = [];

        // Match numbered lines like "1. A", "2) B", "Q3: True", "4 - Shear Wall", "5. Option C"
        $pattern = '/^(?:Q|q|Question|\#)?\s*(\d+)[\.\)\:\-]\s*(.+)$/i';

        foreach ($lines as $line) {
            $lineClean = trim($line);
            if (empty($lineClean)) continue;

            if (preg_match($pattern, $lineClean, $matches)) {
                $num = intval($matches[1]);
                $ansVal = trim($matches[2]);

                if (isset($rawAnswers[$num])) {
                    $duplicateNumbers[] = $num;
                    $warnings[] = "Duplicate answer found for question #{$num}: '{$ansVal}'";
                } else {
                    $rawAnswers[$num] = $ansVal;
                }
            } else {
                $unmatchedText[] = $lineClean;
            }
        }

        // Map parsed numbers to question IDs
        $missingNumbers = [];
        $requiresReview = !empty($duplicateNumbers) || !empty($unmatchedText);

        if (!empty($examQuestions)) {
            // Sort exam questions by ID or sequence
            $qCount = count($examQuestions);
            $index = 1;
            foreach ($examQuestions as $q) {
                $qId = $q['id'];
                if (isset($rawAnswers[$index])) {
                    $parsedAnswers[$qId] = $rawAnswers[$index];
                } else {
                    $missingNumbers[] = $index;
                    $parsedAnswers[$qId] = '';
                }
                $index++;
            }

            if (!empty($missingNumbers)) {
                $warnings[] = "Missing answer markings for question numbers: " . implode(', ', $missingNumbers);
                $requiresReview = true;
            }
        } else {
            $parsedAnswers = $rawAnswers;
        }

        return [
            'answers' => $parsedAnswers,
            'raw_answers' => $rawAnswers,
            'warnings' => $warnings,
            'missing_numbers' => $missingNumbers,
            'duplicate_numbers' => array_unique($duplicateNumbers),
            'unmatched_text' => $unmatchedText,
            'requires_review' => $requiresReview
        ];
    }
}
