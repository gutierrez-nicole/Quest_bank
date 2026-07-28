<?php
// app/services/GroqService.php - Service Layer for Groq Cloud API

require_once __DIR__ . '/../config/config.php';

class GroqService {
    
    /**
     * Send a request to Groq API endpoint
     */
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

    /**
     * Generate Exam Questions from Lesson Material and Civil Engineering Specialization
     */
    public static function generateQuestions($lessonText, $numQuestions, $subject, $examTitle, $specialization = 'Structural Engineering', $apiKey = null) {
        $prompt = "You are an expert Civil Engineering professor specializing in {$specialization} and academic assessment creation. "
                . "Generate exactly {$numQuestions} high-quality Civil Engineering examination questions for the subject '{$subject}' (Specialization: {$specialization}) titled '{$examTitle}' "
                . "based on the following lesson content: \"{$lessonText}\". "
                . "Include a mix of theoretical concepts, formula applications, and engineering scenario items relevant to {$specialization}. "
                . "Format response strictly as a JSON array of objects without markdown fences or code blocks. "
                . "Each object MUST have: \"question\" (string), \"type\" (\"multiple_choice\" or \"identification\"), "
                . "\"opt_a\" (string or null), \"opt_b\" (string or null), \"opt_c\" (string or null), \"opt_d\" (string or null), "
                . "and \"correct_answer\" (string).";

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

        $content = $res['data']['choices'][0]['message']['content'] ?? '';
        $cleanContent = preg_replace('/^```(?:json)?\s*|\s*```$/i', '', trim($content));
        $cleanJson = json_decode(trim($cleanContent), true);

        if (json_last_error() === JSON_ERROR_NONE && is_array($cleanJson)) {
            return ['success' => true, 'questions' => $cleanJson];
        }

        return ['error' => 'Failed to parse AI output into valid JSON questions schema. Raw response: ' . substr($content, 0, 200)];
    }

    /**
     * Evaluate Answer Sheet via OCR / Vision AI
     */
    public static function evaluateAnswerSheet($studentName, $examTitle, $uploadType, $answerKey, $simulatedOrExtractedText, $apiKey = null) {
        $prompt = "You are an advanced educational AI OCR and grading system for Civil Engineering assessments. "
                . "Analyze the student exam paper for student '{$studentName}', exam '{$examTitle}'. "
                . "Answer Key provided by Teacher: {$answerKey}. "
                . "Student Answers extracted: {$simulatedOrExtractedText}. "
                . "Calculate score parameters meticulously: Total items, Correct count, Wrong count, Percentage Grade (0-100), Status ('Pass' if percentage >= 75, else 'Fail'). "
                . "Return ONLY a valid JSON object string matching schema: "
                . "{\"correct\": 4, \"wrong\": 1, \"total_items\": 5, \"percentage\": 80, \"status\": \"Pass\", \"questions\": [{\"num\": 1, \"q\": \"Question text\", \"student_ans\": \"Ans\", \"key_ans\": \"Key\", \"is_correct\": true}]}";

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

        $content = $res['data']['choices'][0]['message']['content'] ?? '';
        $cleanContent = preg_replace('/^```(?:json)?\s*|\s*```$/i', '', trim($content));
        $cleanJson = json_decode(trim($cleanContent), true);

        if (json_last_error() === JSON_ERROR_NONE && is_array($cleanJson)) {
            return ['success' => true, 'evaluation' => $cleanJson];
        }

        return ['error' => 'Failed to parse AI grading output into JSON. Raw output: ' . substr($content, 0, 200)];
    }
}
