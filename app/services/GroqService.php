<?php

require_once __DIR__ . '/../config/config.php';

class GroqService {

    public static $testMode = false;

    private static function sendRequest($payload, $apiKey = null) {
        // Test-only Mock check (Strictly scoped to testMode, APP_ENV === 'testing', or explicit TEST_MOCK_KEY)
        $isTestEnv = self::$testMode || (defined('APP_ENV') && APP_ENV === 'testing') || ($apiKey === 'TEST_MOCK_KEY');
        if ($isTestEnv && ($apiKey === 'TEST_MOCK_KEY' || empty($apiKey) || $apiKey === 'YOUR_GROQ_API_KEY_HERE')) {
            $userPrompt = $payload['messages'][0]['content'] ?? '';
            $targetCount = 5;
            if (preg_match('/Generate exactly (\d+)/i', $userPrompt, $pm)) {
                $targetCount = intval($pm[1]);
            }

            $basePool = [
                ['question' => 'What is the formula for Stopping Sight Distance (SSD)?', 'type' => 'multiple_choice', 'opt_a' => '0.278*V*t + V^2/(254*f)', 'opt_b' => 'V^2 / 254', 'opt_c' => '0.278*V*t', 'opt_d' => 'None of the above', 'correct_answer' => 'A', 'explanation' => 'Standard SSD formula accounting for reaction time and braking.', 'points' => 1, 'source_topic' => 'Highway Engineering', 'source_academic_period' => 'prelim', 'source_confidence' => 'high'],
                ['question' => 'Flexible pavement design uses CBR structural number for traffic load calculation.', 'type' => 'true_false', 'opt_a' => 'True', 'opt_b' => 'False', 'opt_c' => null, 'opt_d' => null, 'correct_answer' => 'True', 'explanation' => 'CBR determines subgrade strength.', 'points' => 1, 'source_topic' => 'Pavement Design', 'source_academic_period' => 'midterm', 'source_confidence' => 'high'],
                ['question' => 'What structural component resists bending moments in reinforced concrete?', 'type' => 'multiple_choice', 'opt_a' => 'Steel rebar', 'opt_b' => 'Aggregates', 'opt_c' => 'Water', 'opt_d' => 'Sand', 'correct_answer' => 'A', 'explanation' => 'Steel rebar provides tensile capacity.', 'points' => 1, 'source_topic' => 'Structural Concrete', 'source_academic_period' => 'finals', 'source_confidence' => 'high'],
                ['question' => 'Pavement markings guide traffic flow and lane discipline.', 'type' => 'true_false', 'opt_a' => 'True', 'opt_b' => 'False', 'opt_c' => null, 'opt_d' => null, 'correct_answer' => 'True', 'explanation' => 'Visual guidance for drivers.', 'points' => 1, 'source_topic' => 'Traffic Engineering', 'source_academic_period' => 'general', 'source_confidence' => 'high'],
                ['question' => 'Which coefficient represents pavement friction in SSD calculation?', 'type' => 'multiple_choice', 'opt_a' => 'f (coefficient of longitudinal friction)', 'opt_b' => 'CBR', 'opt_c' => 'V (velocity)', 'opt_d' => 't (time)', 'correct_answer' => 'A', 'explanation' => 'Friction coefficient f.', 'points' => 1, 'source_topic' => 'Highway Engineering', 'source_academic_period' => 'prelim', 'source_confidence' => 'high']
            ];

            $mockQuestions = [];
            for ($i = 0; $i < $targetCount; $i++) {
                $item = $basePool[$i % count($basePool)];
                if (preg_match('/lesson chunk \((\d+) of (\d+)\)/i', $userPrompt, $cm)) {
                    $item['question'] .= " [Chunk {$cm[1]}-Item #" . ($i + 1) . "]";
                } elseif ($i >= count($basePool)) {
                    $item['question'] .= " (Item Variant #" . ($i + 1) . ")";
                }
                $mockQuestions[] = $item;
            }

            return [
                'success' => true,
                'data' => [
                    'choices' => [
                        [
                            'message' => [
                                'content' => json_encode($mockQuestions)
                            ]
                        ]
                    ],
                    'usage' => ['total_tokens' => 250]
                ]
            ];
        }

        // Production Credential & Error Enforcement (NO production mock fallbacks!)
        $key = ($apiKey !== null && $apiKey !== '') ? $apiKey : GROQ_API_KEY;
        if (empty($key) || $key === 'YOUR_GROQ_API_KEY_HERE' || $key === 'MISSING_KEY') {
            return [
                'success' => false,
                'error_code' => 'MISSING_API_KEY',
                'user_message' => 'Groq API Key is not configured. Please set GROQ_API_KEY in your server configuration.',
                'technical_message' => 'GROQ_API_KEY constant is empty or set to default placeholder.',
                'retryable' => false,
                'provider_status' => 401,
                'request_id' => null,
                'error' => 'Groq API Key is not configured.'
            ];
        }

        if (strpos($key, 'gsk_') === false) {
            return [
                'success' => false,
                'error_code' => 'INVALID_API_KEY',
                'user_message' => 'Groq API Key format is invalid. Key must begin with gsk_.',
                'technical_message' => 'Provided API key prefix check failed.',
                'retryable' => false,
                'provider_status' => 401,
                'request_id' => null,
                'error' => 'Groq API Key format is invalid.'
            ];
        }

        $ch = curl_init(GROQ_API_ENDPOINT);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
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
        $curlErrNo = curl_errno($ch);
        $curlError = curl_error($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($curlErrNo) {
            if ($curlErrNo === CURLE_OPERATION_TIMEDOUT) {
                return [
                    'success' => false,
                    'error_code' => 'TIMEOUT',
                    'user_message' => 'The AI question generation service timed out. Please try again.',
                    'technical_message' => "cURL error {$curlErrNo}: {$curlError}",
                    'retryable' => true,
                    'provider_status' => 504,
                    'request_id' => null,
                    'error' => 'AI question generation service timed out.'
                ];
            }
            return [
                'success' => false,
                'error_code' => 'PROVIDER_ERROR',
                'user_message' => 'Network error connecting to Groq AI service.',
                'technical_message' => "cURL error {$curlErrNo}: {$curlError}",
                'retryable' => true,
                'provider_status' => $httpCode ?: 500,
                'request_id' => null,
                'error' => 'Network error connecting to Groq AI service.'
            ];
        }

        if ($httpCode === 429) {
            return [
                'success' => false,
                'error_code' => 'RATE_LIMIT_EXCEEDED',
                'user_message' => 'Groq AI service rate limit reached. Please wait a moment before retrying.',
                'technical_message' => 'HTTP 429 Rate Limit Exceeded.',
                'retryable' => true,
                'provider_status' => 429,
                'request_id' => null,
                'error' => 'Groq AI service rate limit reached.'
            ];
        }

        if ($httpCode === 401 || $httpCode === 403) {
            return [
                'success' => false,
                'error_code' => 'INVALID_API_KEY',
                'user_message' => 'Groq API rejected credentials. Authentication failed.',
                'technical_message' => "HTTP {$httpCode} Unauthorized.",
                'retryable' => false,
                'provider_status' => $httpCode,
                'request_id' => null,
                'error' => 'Groq API authentication failed.'
            ];
        }

        if ($httpCode >= 400) {
            return [
                'success' => false,
                'error_code' => 'PROVIDER_ERROR',
                'user_message' => 'Groq AI service encountered an operational error (HTTP ' . $httpCode . ').',
                'technical_message' => "HTTP {$httpCode} status returned from Groq.",
                'retryable' => true,
                'provider_status' => $httpCode,
                'request_id' => null,
                'error' => 'Groq AI service error (HTTP ' . $httpCode . ').'
            ];
        }

        $decoded = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($decoded) || !isset($decoded['choices'][0]['message']['content'])) {
            return [
                'success' => false,
                'error_code' => 'MALFORMED_RESPONSE',
                'user_message' => 'Received malformed JSON payload from AI provider.',
                'technical_message' => 'JSON parse error: ' . json_last_error_msg(),
                'retryable' => true,
                'provider_status' => 502,
                'request_id' => null,
                'error' => 'Received malformed JSON payload from AI provider.'
            ];
        }

        return ['success' => true, 'data' => $decoded];
    }

    public static function generateQuestions($lessonText, $numQuestions, $subject, $examTitle, $specialization = 'Structural Engineering', $questionType = 'multiple_choice', $difficulty = 'medium', $apiKey = null) {
        $startTime = microtime(true);

        if (empty(trim($lessonText)) || strlen(trim($lessonText)) < 20) {
            return ['error' => 'Selected lesson text is too short or empty for AI question generation.'];
        }

        if ($numQuestions <= 0) {
            return ['error' => 'Question count must be at least 1.'];
        }

        $charLength = strlen($lessonText);
        $wordCount = str_word_count($lessonText);
        $estimatedTokens = (int)ceil($charLength / 4);

        $chunkLimit = defined('AI_SAFE_INPUT_TOKENS') ? (AI_SAFE_INPUT_TOKENS * 4) : 96000;
        $generationWarnings = [];
        $rawChunkResponses = [];

        if ($charLength > $chunkLimit) {
            // Final Repair 5: Second-level hierarchical splitting for single oversized lessons
            // Preferred boundaries: 1. Source lesson boundaries 2. Headings 3. Paragraphs 4. Sentences
            preg_match_all('/(SOURCE LESSON \d+[\s\S]*?)(?=(?:SOURCE LESSON \d+|\z))/i', $lessonText, $matches);
            $initialBlocks = !empty($matches[1]) ? $matches[1] : preg_split('/\n{2,}/', $lessonText);

            $lessonBlocks = [];
            foreach ($initialBlocks as $blk) {
                if (strlen($blk) > $chunkLimit) {
                    // Subsplit oversized block by headings/sections or paragraphs
                    $subBlocks = preg_split('/(?:\n(?=#+|\=+\s|-{3,}))|(?:\n{2,})/', $blk);
                    foreach ($subBlocks as $sblk) {
                        if (strlen($sblk) > $chunkLimit) {
                            // Subsplit by sentences if paragraph is still oversized
                            $sentences = preg_split('/(?<=[.!?])\s+/', $sblk);
                            $tempSub = "";
                            foreach ($sentences as $st) {
                                if (strlen($tempSub) + strlen($st) > $chunkLimit && !empty($tempSub)) {
                                    $lessonBlocks[] = $tempSub;
                                    $tempSub = $st;
                                } else {
                                    $tempSub .= ($tempSub ? " " : "") . $st;
                                }
                            }
                            if (!empty($tempSub)) $lessonBlocks[] = $tempSub;
                        } else {
                            $lessonBlocks[] = $sblk;
                        }
                    }
                } else {
                    $lessonBlocks[] = $blk;
                }
            }

            $chunks = [];
            $currentChunk = "";
            foreach ($lessonBlocks as $block) {
                if (strlen($currentChunk) + strlen($block) > $chunkLimit && !empty($currentChunk)) {
                    $chunks[] = $currentChunk;
                    $currentChunk = $block;
                } else {
                    $currentChunk .= ($currentChunk ? "\n\n" : "") . $block;
                }
            }
            if (!empty($currentChunk)) {
                $chunks[] = $currentChunk;
            }

            $validQuestions = [];
            $seen = [];
            $totalChunks = count($chunks);
            $failedChunkCount = 0;
            $affectedLessonIds = [];

            // Repair Prompt 4: Calculate exact integer question allocation per chunk
            $baseAlloc = (int)floor($numQuestions / $totalChunks);
            $remainder = $numQuestions % $totalChunks;
            $chunkAllocations = [];
            for ($c = 0; $c < $totalChunks; $c++) {
                $chunkAllocations[$c] = $baseAlloc + ($c < $remainder ? 1 : 0);
            }

            foreach ($chunks as $chunkIdx => $chunkContent) {
                $chunkShare = $chunkAllocations[$chunkIdx] ?? max(1, (int)round($numQuestions / $totalChunks));
                
                // Extract lesson IDs in current chunk for failure tracking
                preg_match_all('/Lesson ID:\s*(\d+)/i', $chunkContent, $lIdMatches);
                $chunkLessonIds = !empty($lIdMatches[1]) ? array_map('intval', $lIdMatches[1]) : [];

                $chunkPrompt = "You are an expert Civil Engineering professor specializing in {$specialization} and academic assessment creation. "
                             . "Generate exactly {$chunkShare} high-quality Civil Engineering examination questions for the subject '{$subject}' (Specialization: {$specialization}) titled '{$examTitle}'. "
                             . "Target Difficulty Level: '{$difficulty}'. "
                             . "Target Question Type Format: '{$questionType}'. "
                             . "based strictly on the following lesson chunk (" . ($chunkIdx + 1) . " of {$totalChunks}): \"{$chunkContent}\". "
                             . "Do NOT invent facts outside the lesson content. "
                             . "Format response strictly as a JSON array of objects without markdown code blocks. "
                             . "Each object MUST have: \"question\" (string), \"type\" (string), \"opt_a\" (string or null), \"opt_b\" (string or null), \"opt_c\" (string or null), \"opt_d\" (string or null), "
                             . "\"correct_answer\" (string), \"formula_latex\" (string or null), \"matching_pairs\" (object or null), \"explanation\" (string), \"points\" (int), "
                             . "\"source_lesson_ids\" (array of integers, e.g. [12]), \"source_topic\" (string), \"source_academic_period\" (string), \"source_confidence\" (string: 'high', 'medium', or 'review_required').";

                $payload = [
                    'model' => GROQ_DEFAULT_MODEL,
                    'messages' => [['role' => 'user', 'content' => $chunkPrompt]],
                    'temperature' => 0.3
                ];

                $res = self::sendRequest($payload, $apiKey);
                if (isset($res['error']) || (isset($res['success']) && $res['success'] === false)) {
                    $failedChunkCount++;
                    $affectedLessonIds = array_merge($affectedLessonIds, $chunkLessonIds);
                    $errMsg = $res['user_message'] ?? $res['error'] ?? 'Chunk generation failed';
                    $generationWarnings[] = "Chunk " . ($chunkIdx + 1) . " of {$totalChunks} failed: " . $errMsg;
                    continue;
                }

                $content = $res['data']['choices'][0]['message']['content'] ?? '';
                $cleanContent = preg_replace('/^```(?:json)?\s*|\s*```$/i', '', trim($content));
                $cleanJson = json_decode(trim($cleanContent), true);

                if (is_array($cleanJson)) {
                    foreach ($cleanJson as $q) {
                        if (!is_array($q)) continue;
                        $qText = trim($q['question'] ?? '');
                        $qCorrect = trim($q['correct_answer'] ?? '');
                        if (empty($qText) || empty($qCorrect)) continue;

                        $dedupKey = mb_strtolower(preg_replace('/\s+/', ' ', $qText));
                        if (isset($seen[$dedupKey])) continue;
                        $seen[$dedupKey] = true;

                        $srcLessonIds = is_array($q['source_lesson_ids'] ?? null) ? array_map('intval', $q['source_lesson_ids']) : [];

                        $validQuestions[] = [
                            'question' => $qText,
                            'type' => trim($q['type'] ?? $questionType),
                            'opt_a' => $q['opt_a'] ?? null,
                            'opt_b' => $q['opt_b'] ?? null,
                            'opt_c' => $q['opt_c'] ?? null,
                            'opt_d' => $q['opt_d'] ?? null,
                            'correct_answer' => $qCorrect,
                            'formula_latex' => $q['formula_latex'] ?? null,
                            'matching_pairs' => $q['matching_pairs'] ?? null,
                            'explanation' => $q['explanation'] ?? '',
                            'points' => intval($q['points'] ?? 1),
                            'difficulty' => $difficulty,
                            'topic' => $q['source_topic'] ?? $subject,
                            'source_lesson_ids' => $srcLessonIds,
                            'source_topic' => $q['source_topic'] ?? $subject,
                            'source_academic_period' => strtolower($q['source_academic_period'] ?? 'general'),
                            'source_confidence' => $q['source_confidence'] ?? 'high'
                        ];
                    }
                }
            }

            if (empty($validQuestions)) {
                return ['error' => 'Chunked AI generation produced no valid questions. Warnings: ' . implode('; ', $generationWarnings)];
            }

            // Repair Prompt 4: Enforce exact question count ceiling (never return more than requested)
            $validQuestions = array_slice($validQuestions, 0, $numQuestions);

            $executionTime = round((microtime(true) - $startTime) * 1000, 2);

            return [
                'success' => true,
                'questions' => $validQuestions,
                'metadata' => [
                    'model' => GROQ_DEFAULT_MODEL,
                    'generation_time_ms' => $executionTime,
                    'token_usage' => $estimatedTokens,
                    'word_count' => $wordCount,
                    'estimated_tokens' => $estimatedTokens,
                    'chunked' => true,
                    'chunk_count' => count($chunks),
                    'batch_status' => $failedChunkCount > 0 ? 'incomplete' : 'completed',
                    'failed_chunk_count' => $failedChunkCount,
                    'affected_lesson_ids' => array_values(array_unique($affectedLessonIds)),
                    'generation_warnings' => $generationWarnings,
                    'difficulty' => $difficulty
                ]
            ];
        }

        // Direct single-pass generation
        $prompt = "You are an expert Civil Engineering professor specializing in {$specialization} and academic assessment creation. "
                . "Generate exactly {$numQuestions} high-quality Civil Engineering examination questions for the subject '{$subject}' (Specialization: {$specialization}) titled '{$examTitle}'. "
                . "Target Difficulty Level: '{$difficulty}'. "
                . "Target Question Type Format: '{$questionType}' (Supported types: multiple_choice, true_false, identification). "
                . "based strictly on the following lesson content: \"{$lessonText}\". "
                . "Do NOT invent facts outside the lesson content. "
                . "Format response strictly as a JSON array of objects without markdown fences or code blocks. "
                . "Each object MUST have: \"question\" (string), \"type\" (string), "
                . "\"opt_a\" (string or null), \"opt_b\" (string or null), \"opt_c\" (string or null), \"opt_d\" (string or null), "
                . "\"correct_answer\" (string), \"formula_latex\" (string or null), \"matching_pairs\" (object or null), "
                . "and \"explanation\" (string containing detailed solution/concept explanation), "
                . "\"source_lesson_ids\" (array of integers, e.g. [12]), \"source_topic\" (string), \"source_academic_period\" (string), \"source_confidence\" (string: 'high', 'medium', or 'review_required').";

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
        $usage = $res['data']['usage'] ?? ['total_tokens' => $estimatedTokens];

        $content = $res['data']['choices'][0]['message']['content'] ?? '';
        $cleanContent = preg_replace('/^```(?:json)?\s*|\s*```$/i', '', trim($content));
        $cleanJson = json_decode(trim($cleanContent), true);

        if (json_last_error() !== JSON_ERROR_NONE || !is_array($cleanJson)) {
            return ['error' => 'Failed to parse AI response as JSON: ' . json_last_error_msg()];
        }

        $validQuestions = [];
        $seen = [];

        foreach ($cleanJson as $q) {
            if (!is_array($q)) continue;
            $qText = trim($q['question'] ?? '');
            $qCorrect = trim($q['correct_answer'] ?? '');
            $qType = trim($q['type'] ?? $questionType);

            if (empty($qText) || empty($qCorrect)) {
                continue; 
            }

            $dedupKey = mb_strtolower(preg_replace('/\s+/', ' ', $qText));
            if (isset($seen[$dedupKey])) {
                continue; 
            }
            $seen[$dedupKey] = true;

            $srcLessonIds = is_array($q['source_lesson_ids'] ?? null) ? array_map('intval', $q['source_lesson_ids']) : [];

            $validQuestions[] = [
                'question' => $qText,
                'type' => $qType,
                'opt_a' => $q['opt_a'] ?? null,
                'opt_b' => $q['opt_b'] ?? null,
                'opt_c' => $q['opt_c'] ?? null,
                'opt_d' => $q['opt_d'] ?? null,
                'correct_answer' => $qCorrect,
                'formula_latex' => $q['formula_latex'] ?? null,
                'matching_pairs' => $q['matching_pairs'] ?? null,
                'explanation' => $q['explanation'] ?? '',
                'points' => intval($q['points'] ?? 1),
                'difficulty' => $difficulty,
                'topic' => $q['source_topic'] ?? $subject,
                'source_lesson_ids' => $srcLessonIds,
                'source_topic' => $q['source_topic'] ?? $subject,
                'source_academic_period' => strtolower($q['source_academic_period'] ?? 'general'),
                'source_confidence' => $q['source_confidence'] ?? 'high'
            ];
        }

        if (empty($validQuestions)) {
            return ['error' => 'AI generation produced no valid questions after schema validation.'];
        }

        return [
            'success' => true,
            'questions' => $validQuestions,
            'metadata' => [
                'model' => GROQ_DEFAULT_MODEL,
                'generation_time_ms' => $executionTime,
                'token_usage' => $usage['total_tokens'] ?? $estimatedTokens,
                'word_count' => $wordCount,
                'estimated_tokens' => $estimatedTokens,
                'chunked' => false,
                'prompt' => mb_substr($prompt, 0, 500),
                'difficulty' => $difficulty
            ]
        ];
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
