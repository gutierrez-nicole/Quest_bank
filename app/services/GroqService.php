<?php

require_once __DIR__ . '/../config/config.php';

class GroqService {

    public static $testMode = false;

    private static function sendRequest($payload, $apiKey = null) {
        // Final Blocker 2: Mock provider executes ONLY IF APP_ENV === 'testing' AND self::$testMode === true
        $currentEnv = getenv('APP_ENV') ?: (defined('APP_ENV') ? APP_ENV : 'production');
        $isTestEnvMode = ($currentEnv === 'testing') && (self::$testMode === true);
        if ($isTestEnvMode && ($apiKey === 'TEST_MOCK_KEY' || empty($apiKey) || $apiKey === 'YOUR_GROQ_API_KEY_HERE')) {
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

            preg_match_all('/Lesson ID:\s*(\d+)/i', $userPrompt, $lMatch);
            $promptLids = !empty($lMatch[1]) ? array_values(array_unique(array_map('intval', $lMatch[1]))) : [];
            if (empty($promptLids) && preg_match('/source_lesson_ids.*?\[([\d,\s]+)\]/i', $userPrompt, $eMatch)) {
                $promptLids = array_values(array_unique(array_map('intval', explode(',', $eMatch[1]))));
            }

            preg_match('/Period:\s*([^\r\n]+)/i', $userPrompt, $pMatch);
            $promptPeriod = !empty($pMatch[1]) ? strtolower(trim($pMatch[1])) : null;

            $isMissingSourceMock = (preg_match('/Missing Source/i', $userPrompt) || preg_match('/MOCK_MISSING_SOURCE/i', $userPrompt));

            $mockQuestions = [];
            for ($i = 0; $i < $targetCount; $i++) {
                $item = $basePool[$i % count($basePool)];
                if (!empty($promptLids)) {
                    $item['source_lesson_ids'] = $promptLids;
                }
                if (!empty($promptPeriod)) {
                    $item['source_academic_period'] = $promptPeriod;
                }

                // If deterministic missing source test is requested, strip source_lesson_ids from Item #1
                if ($isMissingSourceMock && $i === 0) {
                    $item['source_lesson_ids'] = [];
                    $item['source_confidence'] = 'review_required';
                }

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
            $chunkGenerationResults = [];

            // Calculate exact integer question allocation per chunk
            $baseAlloc = (int)floor($numQuestions / $totalChunks);
            $remainder = $numQuestions % $totalChunks;
            $chunkAllocations = [];
            for ($c = 0; $c < $totalChunks; $c++) {
                $chunkAllocations[$c] = $baseAlloc + ($c < $remainder ? 1 : 0);
            }

            foreach ($chunks as $chunkIdx => $chunkContent) {
                $chunkShare = $chunkAllocations[$chunkIdx] ?? max(1, (int)round($numQuestions / $totalChunks));
                
                // Extract lesson IDs in current chunk for failure and coverage tracking
                preg_match_all('/Lesson ID:\s*(\d+)/i', $chunkContent, $lIdMatches);
                $chunkLessonIds = !empty($lIdMatches[1]) ? array_values(array_unique(array_map('intval', $lIdMatches[1]))) : [];

                // Extract academic periods in current chunk
                preg_match_all('/Period:\s*([^\r\n]+)/i', $chunkContent, $pMatches);
                $chunkPeriods = [];
                if (!empty($pMatches[1])) {
                    foreach ($pMatches[1] as $pm) {
                        $pm = strtolower(trim($pm));
                        if (!empty($pm) && !in_array($pm, $chunkPeriods, true)) {
                            $chunkPeriods[] = $pm;
                        }
                    }
                }

                $chunkPrompt = "You are an expert Civil Engineering professor specializing in {$specialization} and academic assessment creation. "
                             . "Generate exactly {$chunkShare} high-quality Civil Engineering examination questions for the subject '{$subject}' (Specialization: {$specialization}) titled '{$examTitle}'. "
                             . "Target Difficulty Level: '{$difficulty}'. "
                             . "Target Question Type Format: '{$questionType}'. "
                             . "based strictly on the following lesson chunk (" . ($chunkIdx + 1) . " of {$totalChunks}): \"{$chunkContent}\". "
                             . "Do NOT invent facts outside the lesson content. "
                             . "Format response strictly as a JSON array of objects without markdown code blocks. "
                             . "Each object MUST have: \"question\" (string), \"type\" (string), \"opt_a\" (string or null), \"opt_b\" (string or null), \"opt_c\" (string or null), \"opt_d\" (string or null), "
                             . "\"correct_answer\" (string), \"formula_latex\" (string or null), \"matching_pairs\" (object or null), \"explanation\" (string), \"points\" (int), "
                             . "\"source_lesson_ids\" (array of integers, e.g. [" . implode(',', $chunkLessonIds) . "]), \"source_topic\" (string), \"source_academic_period\" (string), \"source_confidence\" (string: 'high', 'medium', or 'review_required').";

                $payload = [
                    'model' => GROQ_DEFAULT_MODEL,
                    'messages' => [['role' => 'user', 'content' => $chunkPrompt]],
                    'temperature' => 0.3
                ];

                $chunkCallFailed = false;
                $invalidQuestionCount = 0;
                $duplicateCount = 0;
                $acceptedFromChunk = 0;
                $rawGeneratedCount = 0;

                $res = self::sendRequest($payload, $apiKey);
                if (isset($res['error']) || (isset($res['success']) && $res['success'] === false)) {
                    $chunkCallFailed = true;
                    $failedChunkCount++;
                    $affectedLessonIds = array_merge($affectedLessonIds, $chunkLessonIds);
                    $errMsg = $res['user_message'] ?? $res['error'] ?? 'Chunk generation failed';
                    $generationWarnings[] = "Chunk " . ($chunkIdx + 1) . " of {$totalChunks} failed: " . $errMsg;
                } else {
                    $content = $res['data']['choices'][0]['message']['content'] ?? '';
                    $cleanContent = preg_replace('/^```(?:json)?\s*|\s*```$/i', '', trim($content));
                    $cleanJson = json_decode(trim($cleanContent), true);

                    if (is_array($cleanJson)) {
                        foreach ($cleanJson as $q) {
                            if (!is_array($q)) continue;
                            $rawGeneratedCount++;
                            $qText = trim($q['question'] ?? '');
                            $qCorrect = trim($q['correct_answer'] ?? '');
                            if (empty($qText) || empty($qCorrect)) {
                                $invalidQuestionCount++;
                                continue;
                            }

                            $dedupKey = mb_strtolower(preg_replace('/\s+/', ' ', $qText));
                            if (isset($seen[$dedupKey])) {
                                $duplicateCount++;
                                continue;
                            }
                            $seen[$dedupKey] = true;

                            $srcLessonIds = is_array($q['source_lesson_ids'] ?? null) ? array_map('intval', $q['source_lesson_ids']) : [];
                            $srcConfidence = $q['source_confidence'] ?? 'high';

                            if (empty($srcLessonIds)) {
                                if (count($chunkLessonIds) === 1) {
                                    $srcLessonIds = $chunkLessonIds;
                                } else {
                                    $srcLessonIds = [];
                                    $srcConfidence = 'review_required';
                                }
                            }

                            $srcPeriod = strtolower(trim($q['source_academic_period'] ?? ''));
                            if (empty($srcPeriod) || $srcPeriod === 'general') {
                                $srcPeriod = !empty($chunkPeriods) ? $chunkPeriods[0] : 'general';
                            }

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
                                'source_academic_period' => $srcPeriod,
                                'source_confidence' => $q['source_confidence'] ?? 'high'
                            ];
                            $acceptedFromChunk++;
                        }
                    } else {
                        $invalidQuestionCount += $chunkShare;
                    }
                }

                $failedCount = $chunkCallFailed ? $chunkShare : max(0, $chunkShare - $acceptedFromChunk);

                $chunkGenerationResults[$chunkIdx] = [
                    'chunk_id' => $chunkIdx,
                    'source_lesson_ids' => $chunkLessonIds,
                    'academic_periods' => $chunkPeriods,
                    'requested_question_allocation' => $chunkShare,
                    'successfully_generated_count' => $rawGeneratedCount,
                    'invalid_question_count' => $invalidQuestionCount,
                    'duplicate_count' => $duplicateCount,
                    'failed_count' => $failedCount,
                    'final_accepted_count' => $acceptedFromChunk
                ];
            }

            // --- COVERAGE-AWARE SHORTFALL REFILL ---
            $refillAttemptCount = 0;
            $refillWarnings = [];
            $shortfall = $numQuestions - count($validQuestions);

            if ($shortfall > 0) {
                preg_match_all('/Lesson ID:\s*(\d+)/i', $lessonText, $allLMatches);
                $allSelectedLessonIds = !empty($allLMatches[1]) ? array_values(array_unique(array_map('intval', $allLMatches[1]))) : [];

                preg_match_all('/Period:\s*([^\r\n]+)/i', $lessonText, $allPMatches);
                $allSelectedPeriods = [];
                if (!empty($allPMatches[1])) {
                    foreach ($allPMatches[1] as $pm) {
                        $pm = strtolower(trim($pm));
                        if (!empty($pm) && !in_array($pm, $allSelectedPeriods, true)) {
                            $allSelectedPeriods[] = $pm;
                        }
                    }
                }

                // Determine refill queue based on priority order:
                // 1. Failed chunks
                // 2. Chunks below allocated question count
                // 3. Selected lessons with zero coverage
                // 4. Academic periods with zero coverage
                // 5. Other underrepresented source content
                $p1_failed = [];
                $p2_underfilled = [];
                $p3_uncovered_lessons = [];
                $p4_uncovered_periods = [];
                $p5_others = [];

                $lessonCoverage = array_fill_keys($allSelectedLessonIds, 0);
                $periodCoverage = array_fill_keys($allSelectedPeriods, 0);
                foreach ($validQuestions as $vq) {
                    foreach ($vq['source_lesson_ids'] as $lId) {
                        if (isset($lessonCoverage[$lId])) $lessonCoverage[$lId]++;
                    }
                    $p = strtolower($vq['source_academic_period'] ?? '');
                    if (isset($periodCoverage[$p])) $periodCoverage[$p]++;
                }

                $zeroLessons = array_keys(array_filter($lessonCoverage, function($cnt) { return $cnt === 0; }));
                $zeroPeriods = array_keys(array_filter($periodCoverage, function($cnt) { return $cnt === 0; }));

                for ($c = 0; $c < $totalChunks; $c++) {
                    $cRes = $chunkGenerationResults[$c];
                    $deficit = $cRes['requested_question_allocation'] - $cRes['final_accepted_count'];
                    
                    if ($cRes['failed_count'] > 0 || $cRes['final_accepted_count'] === 0) {
                        $p1_failed[] = $c;
                    } elseif ($deficit > 0) {
                        $p2_underfilled[$c] = $deficit;
                    }

                    foreach ($cRes['source_lesson_ids'] as $lId) {
                        if (in_array($lId, $zeroLessons, true)) {
                            $p3_uncovered_lessons[] = $c;
                        }
                    }

                    foreach ($cRes['academic_periods'] as $per) {
                        if (in_array($per, $zeroPeriods, true)) {
                            $p4_uncovered_periods[] = $c;
                        }
                    }

                    $p5_others[] = $c;
                }

                arsort($p2_underfilled);
                $p2_chunks = array_keys($p2_underfilled);

                $refillQueue = [];
                foreach (array_merge($p1_failed, $p2_chunks, $p3_uncovered_lessons, $p4_uncovered_periods, $p5_others) as $idx) {
                    if (!in_array($idx, $refillQueue, true)) {
                        $refillQueue[] = $idx;
                    }
                }

                $qIndex = 0;
                $maxRefillAttempts = count($refillQueue) * 2;

                while (count($validQuestions) < $numQuestions && $refillAttemptCount < $maxRefillAttempts && !empty($refillQueue)) {
                    $targetChunkIdx = $refillQueue[$qIndex % count($refillQueue)];
                    $qIndex++;
                    $refillAttemptCount++;

                    $targetChunkContent = $chunks[$targetChunkIdx];
                    $targetChunkLessonIds = $chunkGenerationResults[$targetChunkIdx]['source_lesson_ids'] ?? [];
                    $targetChunkPeriods = $chunkGenerationResults[$targetChunkIdx]['academic_periods'] ?? [];

                    $currentShortfall = $numQuestions - count($validQuestions);
                    $targetDeficit = max(1, ($chunkAllocations[$targetChunkIdx] ?? 1) - ($chunkGenerationResults[$targetChunkIdx]['final_accepted_count'] ?? 0));
                    $neededRefill = min($currentShortfall, max(1, $targetDeficit));

                    $refillPrompt = "You are an expert Civil Engineering professor specializing in {$specialization}. "
                                  . "Generate exactly {$neededRefill} ADDITIONAL non-duplicate examination questions for the subject '{$subject}' (Specialization: {$specialization}) titled '{$examTitle}'. "
                                  . "Target Difficulty Level: '{$difficulty}'. Target Question Type: '{$questionType}'. "
                                  . "based strictly on the following lesson content chunk (" . ($targetChunkIdx + 1) . " of {$totalChunks}): \"{$targetChunkContent}\". "
                                  . "Do NOT invent facts outside the lesson content. "
                                  . "Format response strictly as a JSON array of objects without markdown code blocks. "
                                  . "Each object MUST have: \"question\" (string), \"type\" (string), \"opt_a\" (string or null), \"opt_b\" (string or null), \"opt_c\" (string or null), \"opt_d\" (string or null), "
                                  . "\"correct_answer\" (string), \"formula_latex\" (string or null), \"matching_pairs\" (object or null), \"explanation\" (string), \"points\" (int), "
                                  . "\"source_lesson_ids\" (array of integers, e.g. [" . implode(',', $targetChunkLessonIds) . "]), \"source_topic\" (string), \"source_academic_period\" (string), \"source_confidence\" (string: 'high', 'medium', or 'review_required').";

                    $refillPayload = [
                        'model' => GROQ_DEFAULT_MODEL,
                        'messages' => [['role' => 'user', 'content' => $refillPrompt]],
                        'temperature' => 0.3
                    ];

                    $refillRes = self::sendRequest($refillPayload, $apiKey);
                    $acceptedThisRefill = 0;

                    if (isset($refillRes['data']['choices'][0]['message']['content'])) {
                        $refillContent = preg_replace('/^```(?:json)?\s*|\s*```$/i', '', trim($refillRes['data']['choices'][0]['message']['content']));
                        $refillJson = json_decode(trim($refillContent), true);

                        if (is_array($refillJson)) {
                            foreach ($refillJson as $rq) {
                                if (count($validQuestions) >= $numQuestions) break;
                                if (!is_array($rq)) continue;
                                $rqText = trim($rq['question'] ?? '');
                                $rqCorrect = trim($rq['correct_answer'] ?? '');
                                if (empty($rqText) || empty($rqCorrect)) continue;

                                $dedupKey = mb_strtolower(preg_replace('/\s+/', ' ', $rqText));
                                if (isset($seen[$dedupKey])) continue;
                                $seen[$dedupKey] = true;

                                $srcLessonIds = is_array($rq['source_lesson_ids'] ?? null) ? array_map('intval', $rq['source_lesson_ids']) : [];
                                $srcConfidence = $rq['source_confidence'] ?? 'high';

                                if (empty($srcLessonIds)) {
                                    if (count($targetChunkLessonIds) === 1) {
                                        $srcLessonIds = $targetChunkLessonIds;
                                    } else {
                                        $srcLessonIds = [];
                                        $srcConfidence = 'review_required';
                                    }
                                }

                                $srcPeriod = strtolower(trim($rq['source_academic_period'] ?? ''));
                                if (empty($srcPeriod) || $srcPeriod === 'general') {
                                    $srcPeriod = !empty($targetChunkPeriods) ? $targetChunkPeriods[0] : 'general';
                                }

                                $validQuestions[] = [
                                    'question' => $rqText,
                                    'type' => trim($rq['type'] ?? $questionType),
                                    'opt_a' => $rq['opt_a'] ?? null,
                                    'opt_b' => $rq['opt_b'] ?? null,
                                    'opt_c' => $rq['opt_c'] ?? null,
                                    'opt_d' => $rq['opt_d'] ?? null,
                                    'correct_answer' => $rqCorrect,
                                    'formula_latex' => $rq['formula_latex'] ?? null,
                                    'matching_pairs' => $rq['matching_pairs'] ?? null,
                                    'explanation' => $rq['explanation'] ?? '',
                                    'points' => intval($rq['points'] ?? 1),
                                    'difficulty' => $difficulty,
                                    'topic' => $rq['source_topic'] ?? $subject,
                                    'source_lesson_ids' => $srcLessonIds,
                                    'source_topic' => $rq['source_topic'] ?? $subject,
                                    'source_academic_period' => $srcPeriod,
                                    'source_confidence' => $rq['source_confidence'] ?? 'high'
                                ];
                                $acceptedThisRefill++;
                                $chunkGenerationResults[$targetChunkIdx]['final_accepted_count']++;
                            }
                        }
                    }

                    if ($acceptedThisRefill === 0) {
                        $refillWarnings[] = "Refill attempt {$refillAttemptCount} on Chunk " . ($targetChunkIdx + 1) . " produced 0 new questions.";
                    }
                }
            }

            if (empty($validQuestions)) {
                return ['error' => 'Chunked AI generation produced no valid questions. Warnings: ' . implode('; ', $generationWarnings)];
            }

            // Enforce exact question count ceiling (never return more than requested)
            $validQuestions = array_slice($validQuestions, 0, $numQuestions);
            $finalGeneratedCount = count($validQuestions);
            $shortfallCount = max(0, $numQuestions - $finalGeneratedCount);

            // Compute post-generation coverage metrics per lesson & period
            preg_match_all('/Lesson ID:\s*(\d+)/i', $lessonText, $allLMatches);
            $allSelectedLessonIds = !empty($allLMatches[1]) ? array_values(array_unique(array_map('intval', $allLMatches[1]))) : [];

            preg_match_all('/Period:\s*([^\r\n]+)/i', $lessonText, $allPMatches);
            $allSelectedPeriods = [];
            if (!empty($allPMatches[1])) {
                foreach ($allPMatches[1] as $pm) {
                    $pm = strtolower(trim($pm));
                    if (!empty($pm) && !in_array($pm, $allSelectedPeriods, true)) {
                        $allSelectedPeriods[] = $pm;
                    }
                }
            }

            $questionsPerLesson = array_fill_keys($allSelectedLessonIds, 0);
            $questionsPerPeriod = array_fill_keys($allSelectedPeriods, 0);

            foreach ($validQuestions as $vq) {
                foreach ($vq['source_lesson_ids'] as $lId) {
                    if (isset($questionsPerLesson[(int)$lId])) {
                        $questionsPerLesson[(int)$lId]++;
                    }
                }
                $p = strtolower($vq['source_academic_period'] ?? '');
                if (isset($questionsPerPeriod[$p])) {
                    $questionsPerPeriod[$p]++;
                }
            }

            $uncoveredLessonIds = [];
            foreach ($questionsPerLesson as $lId => $cnt) {
                if ($cnt === 0) {
                    $uncoveredLessonIds[] = (int)$lId;
                }
            }

            $uncoveredPeriods = [];
            foreach ($questionsPerPeriod as $per => $cnt) {
                if ($cnt === 0) {
                    $uncoveredPeriods[] = $per;
                }
            }

            $batchStatus = ($failedChunkCount > 0 || $shortfallCount > 0 || !empty($uncoveredLessonIds) || !empty($uncoveredPeriods)) ? 'incomplete' : 'completed';

            if (!empty($uncoveredLessonIds)) {
                $generationWarnings[] = "Selected lesson(s) with zero question coverage: " . implode(', ', $uncoveredLessonIds);
            }
            if (!empty($uncoveredPeriods)) {
                $generationWarnings[] = "Academic period(s) with zero question coverage: " . implode(', ', array_map('ucfirst', $uncoveredPeriods));
            }
            if ($shortfallCount > 0) {
                $generationWarnings[] = "Generation shortfall: Requested {$numQuestions} questions, but only {$finalGeneratedCount} unique valid items could be generated.";
            }

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
                    'batch_status' => $batchStatus,
                    'failed_chunk_count' => $failedChunkCount,
                    'requested_question_count' => $numQuestions,
                    'generated_question_count' => $finalGeneratedCount,
                    'failed_question_count' => max($failedChunkCount, $shortfallCount),
                    'shortfall_count' => $shortfallCount,
                    'affected_lesson_ids' => array_values(array_unique($affectedLessonIds)),
                    'generation_warnings' => $generationWarnings,
                    'chunk_generation_results' => array_values($chunkGenerationResults),
                    'questions_per_lesson' => $questionsPerLesson,
                    'questions_per_period' => $questionsPerPeriod,
                    'uncovered_lesson_ids' => array_values($uncoveredLessonIds),
                    'uncovered_periods' => array_values($uncoveredPeriods),
                    'refill_attempt_count' => $refillAttemptCount,
                    'refill_warnings' => $refillWarnings,
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

        $validQuestions = array_slice($validQuestions, 0, $numQuestions);
        $finalGeneratedCount = count($validQuestions);
        $shortfallCount = max(0, $numQuestions - $finalGeneratedCount);

        preg_match_all('/Lesson ID:\s*(\d+)/i', $lessonText, $allLMatches);
        $allSelectedLessonIds = !empty($allLMatches[1]) ? array_values(array_unique(array_map('intval', $allLMatches[1]))) : [];

        preg_match_all('/Period:\s*([^\r\n]+)/i', $lessonText, $allPMatches);
        $allSelectedPeriods = [];
        if (!empty($allPMatches[1])) {
            foreach ($allPMatches[1] as $pm) {
                $pm = strtolower(trim($pm));
                if (!empty($pm) && !in_array($pm, $allSelectedPeriods, true)) {
                    $allSelectedPeriods[] = $pm;
                }
            }
        }

        $questionsPerLesson = array_fill_keys($allSelectedLessonIds, 0);
        $questionsPerPeriod = array_fill_keys($allSelectedPeriods, 0);

        foreach ($validQuestions as $vq) {
            foreach ($vq['source_lesson_ids'] as $lId) {
                if (isset($questionsPerLesson[(int)$lId])) {
                    $questionsPerLesson[(int)$lId]++;
                }
            }
            $p = strtolower($vq['source_academic_period'] ?? '');
            if (isset($questionsPerPeriod[$p])) {
                $questionsPerPeriod[$p]++;
            }
        }

        $uncoveredLessonIds = [];
        foreach ($questionsPerLesson as $lId => $cnt) {
            if ($cnt === 0) $uncoveredLessonIds[] = (int)$lId;
        }

        $uncoveredPeriods = [];
        foreach ($questionsPerPeriod as $per => $cnt) {
            if ($cnt === 0) $uncoveredPeriods[] = $per;
        }

        $batchStatus = ($shortfallCount > 0 || !empty($uncoveredLessonIds) || !empty($uncoveredPeriods)) ? 'incomplete' : 'completed';

        $singleChunkResult = [
            'chunk_id' => 0,
            'source_lesson_ids' => $allSelectedLessonIds,
            'academic_periods' => $allSelectedPeriods,
            'requested_question_allocation' => $numQuestions,
            'successfully_generated_count' => count($cleanJson),
            'invalid_question_count' => max(0, count($cleanJson) - count($seen)),
            'duplicate_count' => max(0, count($cleanJson) - count($validQuestions)),
            'failed_count' => $shortfallCount,
            'final_accepted_count' => $finalGeneratedCount
        ];

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
                'batch_status' => $batchStatus,
                'failed_chunk_count' => 0,
                'requested_question_count' => $numQuestions,
                'generated_question_count' => $finalGeneratedCount,
                'failed_question_count' => $shortfallCount,
                'shortfall_count' => $shortfallCount,
                'affected_lesson_ids' => [],
                'generation_warnings' => [],
                'chunk_generation_results' => [$singleChunkResult],
                'questions_per_lesson' => $questionsPerLesson,
                'questions_per_period' => $questionsPerPeriod,
                'uncovered_lesson_ids' => array_values($uncoveredLessonIds),
                'uncovered_periods' => array_values($uncoveredPeriods),
                'refill_attempt_count' => 0,
                'refill_warnings' => [],
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
