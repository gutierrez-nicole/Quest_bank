<?php

require_once __DIR__ . '/../config/config.php';

class GroqService {

    private static function sendRequest($payload, $apiKey = null) {
        $key = $apiKey ?: GROQ_API_KEY;
        if (empty($key)) {
            return ['error' => 'Groq API Key is missing. Please configure GROQ_API_KEY in .env or app/config/config.php.'];
        }

        $ch = curl_init(GROQ_API_ENDPOINT);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $key,
            'Content-Type: application/json',
            'User-Agent: QuestBank/1.0 (Macintosh; PHP)'
        ]);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

        $response = curl_exec($ch);
        $error = curl_error($ch);

        if ($error) {
            return ['error' => 'API Connection Error (cURL): ' . $error];
        }

        $decoded = json_decode($response, true);
        if (isset($decoded['error'])) {
            return ['error' => $decoded['error']['message'] ?? 'API Error response'];
        }

        return ['success' => true, 'data' => $decoded];
    }

    public static function generateQuestions($lessonText, $numQuestions, $subject, $examTitle, $specialization = 'Structural Engineering', $questionType = 'multiple_choice', $difficulty = 'medium', $apiKey = null) {
        $startTime = microtime(true);

        $prompt = "You are an expert Civil Engineering professor specializing in {$specialization} and academic assessment creation. "
                . "Generate exactly {$numQuestions} high-quality Civil Engineering examination questions for the subject '{$subject}' (Specialization: {$specialization}) titled '{$examTitle}'. "
                . "Target Difficulty Level: '{$difficulty}'. "
                . "Target Question Type Format: '{$questionType}' (Supported types: multiple_choice, true_false, identification, fill_in_the_blank, matching_type, problem_solving, math_formula). "
                . "based on the following lesson content: \"{$lessonText}\". "
                . "Include a mix of theoretical concepts, formula applications, and engineering scenario items. "
                . "Format response strictly as a JSON array of objects without markdown fences or code blocks. "
                . "Each object MUST have: \"question\" (string), \"type\" (string), "
                . "\"opt_a\" (string or null), \"opt_b\" (string or null), \"opt_c\" (string or null), \"opt_d\" (string or null), "
                . "\"correct_answer\" (string), \"formula_latex\" (string or null), \"matching_pairs\" (object or null), "
                . "and \"explanation\" (string containing detailed step-by-step Civil Engineering formula/concept solution).";

        $payload = [
            'model' => GROQ_DEFAULT_MODEL,
            'messages' => [
                ['role' => 'user', 'content' => $prompt]
            ],
            'temperature' => 0.3
        ];

        $res = self::sendRequest($payload, $apiKey);
        if (isset($res['error'])) {
            return $res;
        }

        $executionTime = round((microtime(true) - $startTime) * 1000, 2);
        $usage = $res['data']['usage'] ?? ['total_tokens' => 0];

        $content = $res['data']['choices'][0]['message']['content'] ?? '';
        $cleanContent = preg_replace('/^```(?:json)?\s*|\s*```$/i', '', trim($content));
        $cleanJson = json_decode(trim($cleanContent), true);

        if (json_last_error() === JSON_ERROR_NONE && is_array($cleanJson)) {
            return [
                'success' => true,
                'questions' => $cleanJson,
                'metadata' => [
                    'model' => GROQ_DEFAULT_MODEL,
                    'generation_time_ms' => $executionTime,
                    'token_usage' => $usage['total_tokens'] ?? 0,
                    'prompt' => mb_substr($prompt, 0, 500),
                    'difficulty' => $difficulty
                ]
            ];
        }

        return ['error' => 'Failed to parse AI output into valid JSON questions schema. Raw response: ' . substr($content, 0, 200)];
    }

    public static function evaluateAnswerSheetDetailed($studentName, $examTitle, $uploadType, $answerKey, $ocrExtractedText, $apiKey = null) {
        $startTime = microtime(true);

        $prompt = "You are an advanced AI evaluation system for Civil Engineering exam grading. "
                . "Evaluate student exam paper for Student: '{$studentName}', Exam: '{$examTitle}'. "
                . "Answer Key provided: \"{$answerKey}\". "
                . "OCR Extracted Student Answers: \"{$ocrExtractedText}\". "
                . "Perform question-by-question comparative evaluation. "
                . "Classify each item status as 'correct', 'incorrect', or 'partially_correct'. "
                . "Assign confidence score (0-100%), detailed reason, and flag 'suggested_manual_review' (true if confidence < 75 or answer is partially correct). "
                . "Return ONLY a valid JSON object matching schema: "
                . "{"
                . "\"total_items\": 5, \"correct_count\": 4, \"wrong_count\": 1, \"percentage\": 80.0, \"status\": \"Pass\", \"overall_confidence\": 92.5, "
                . "\"items\": ["
                . "{\"num\": 1, \"student_answer\": \"Ans\", \"correct_answer\": \"Key\", \"result\": \"correct\", \"confidence\": 95.0, \"reason\": \"Exact match\", \"suggested_manual_review\": false}"
                . "]"
                . "}";

        $payload = [
            'model' => GROQ_DEFAULT_MODEL,
            'messages' => [
                ['role' => 'user', 'content' => $prompt]
            ],
            'temperature' => 0.2
        ];

        $res = self::sendRequest($payload, $apiKey);
        if (isset($res['error'])) {
            return $res;
        }

        $executionTime = round((microtime(true) - $startTime) * 1000, 2);

        $content = $res['data']['choices'][0]['message']['content'] ?? '';
        $cleanContent = preg_replace('/^```(?:json)?\s*|\s*```$/i', '', trim($content));
        $cleanJson = json_decode(trim($cleanContent), true);

        if (json_last_error() === JSON_ERROR_NONE && is_array($cleanJson)) {
            return [
                'success' => true,
                'evaluation' => $cleanJson,
                'execution_time_ms' => $executionTime
            ];
        }

        return ['error' => 'Failed to parse AI evaluation output into JSON. Raw output: ' . substr($content, 0, 200)];
    }
}
