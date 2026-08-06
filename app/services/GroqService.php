<?php

require_once __DIR__ . '/../config/config.php';

class GroqService {

    private static $testMode = false;
    private static $testBootstrapActive = false;

    public static function enableTestingModeFromBootstrap(): void {
        $env = getenv('APP_ENV') ?: ($_ENV['APP_ENV'] ?? ($_SERVER['APP_ENV'] ?? ''));
        $currentEnv = (!empty($env)) ? $env : (defined('APP_ENV') ? APP_ENV : 'production');

        $isBootstrapActive = (getenv('TEST_BOOTSTRAP_ACTIVE') === '1') 
            || (defined('TEST_BOOTSTRAP_ACTIVE') && TEST_BOOTSTRAP_ACTIVE === true)
            || (isset($_SERVER['TEST_BOOTSTRAP_ACTIVE']) && $_SERVER['TEST_BOOTSTRAP_ACTIVE'] === '1');

        if ($currentEnv === 'testing' && $isBootstrapActive) {
            self::$testMode = true;
            self::$testBootstrapActive = true;
        } else {
            self::$testMode = false;
            self::$testBootstrapActive = false;
        }
    }

    public static function disableTestingMode(): void {
        self::$testMode = false;
        self::$testBootstrapActive = false;
    }

    public static function isTestModeActive(): bool {
        $env = getenv('APP_ENV') ?: ($_ENV['APP_ENV'] ?? ($_SERVER['APP_ENV'] ?? ''));
        $currentEnv = (!empty($env)) ? $env : (defined('APP_ENV') ? APP_ENV : 'production');
        return ($currentEnv === 'testing') && (self::$testMode === true) && (self::$testBootstrapActive === true);
    }

    private static function sendRequest($payload, $apiKey = null) {
        $isTestEnvMode = self::isTestModeActive();
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
                ['question' => 'Which coefficient represents pavement friction in SSD calculation?', 'type' => 'multiple_choice', 'opt_a' => 'f (coefficient of longitudinal friction)', 'opt_b' => 'CBR', 'opt_c' => 'V (velocity)', 'opt_d' => 't (time)', 'correct_answer' => 'A', 'explanation' => 'Friction coefficient f.', 'points' => 1, 'source_topic' => 'Highway Engineering', 'source_academic_period' => 'prelim', 'source_confidence' => 'high'],
                ['question' => 'What is the primary stress carrying mechanism in web elements of steel beams?', 'type' => 'multiple_choice', 'opt_a' => 'Shear stress', 'opt_b' => 'Flexural tension', 'opt_c' => 'Torsion', 'opt_d' => 'Bearing pressure', 'correct_answer' => 'A', 'explanation' => 'Beam webs carry shear stress.', 'points' => 1, 'source_topic' => 'Structural Steel', 'source_academic_period' => 'prelim', 'source_confidence' => 'high'],
                ['question' => 'The Modified Proctor compaction test determines maximum dry density.', 'type' => 'true_false', 'opt_a' => 'True', 'opt_b' => 'False', 'opt_c' => null, 'opt_d' => null, 'correct_answer' => 'True', 'explanation' => 'Proctor test yields optimum moisture content and max dry density.', 'points' => 1, 'source_topic' => 'Geotechnical Engineering', 'source_academic_period' => 'midterm', 'source_confidence' => 'high'],
                ['question' => 'Which method computes peak storm discharge in urban drainage design?', 'type' => 'multiple_choice', 'opt_a' => 'Rational Method Q=CIA', 'opt_b' => 'Manning Equation', 'opt_c' => 'Hazens formula', 'opt_d' => 'Darcy law', 'correct_answer' => 'A', 'explanation' => 'Rational method calculates peak discharge.', 'points' => 1, 'source_topic' => 'Hydrology', 'source_academic_period' => 'finals', 'source_confidence' => 'high'],
                ['question' => 'In differential leveling, back sight is taken on a point of known elevation.', 'type' => 'true_false', 'opt_a' => 'True', 'opt_b' => 'False', 'opt_c' => null, 'opt_d' => null, 'correct_answer' => 'True', 'explanation' => 'Back sight is measured on a benchmark or turning point.', 'points' => 1, 'source_topic' => 'Surveying', 'source_academic_period' => 'prelim', 'source_confidence' => 'high'],
                ['question' => 'Bernoulli equation expresses conservation of energy in fluid motion.', 'type' => 'true_false', 'opt_a' => 'True', 'opt_b' => 'False', 'opt_c' => null, 'opt_d' => null, 'correct_answer' => 'True', 'explanation' => 'Total energy head remains constant along a streamline.', 'points' => 1, 'source_topic' => 'Fluid Mechanics', 'source_academic_period' => 'midterm', 'source_confidence' => 'high'],
                ['question' => 'What type of active earth pressure theory assumes triangular stress distribution?', 'type' => 'multiple_choice', 'opt_a' => 'Rankine Theory', 'opt_b' => 'Coulomb Theory', 'opt_c' => 'Terzaghi Theory', 'opt_d' => 'Boussinesq Theory', 'correct_answer' => 'A', 'explanation' => 'Rankine assumes smooth vertical wall with triangular distribution.', 'points' => 1, 'source_topic' => 'Foundation Engineering', 'source_academic_period' => 'finals', 'source_confidence' => 'high'],
                ['question' => 'Zero force members in a truss carry load during wind loading.', 'type' => 'true_false', 'opt_a' => 'True', 'opt_b' => 'False', 'opt_c' => null, 'opt_d' => null, 'correct_answer' => 'False', 'explanation' => 'Zero force members carry zero axial force under current joint loads.', 'points' => 1, 'source_topic' => 'Structural Analysis', 'source_academic_period' => 'prelim', 'source_confidence' => 'high'],
                ['question' => 'Liquid limit and plastic limit define soil consistency boundaries.', 'type' => 'true_false', 'opt_a' => 'True', 'opt_b' => 'False', 'opt_c' => null, 'opt_d' => null, 'correct_answer' => 'True', 'explanation' => 'Atterberg limits mark moisture transitions.', 'points' => 1, 'source_topic' => 'Soil Mechanics', 'source_academic_period' => 'midterm', 'source_confidence' => 'high'],
                ['question' => 'Sedimentation tanks remove suspended solids by gravity settling.', 'type' => 'true_false', 'opt_a' => 'True', 'opt_b' => 'False', 'opt_c' => null, 'opt_d' => null, 'correct_answer' => 'True', 'explanation' => 'Gravitational settling clarifies water.', 'points' => 1, 'source_topic' => 'Environmental Engineering', 'source_academic_period' => 'finals', 'source_confidence' => 'high'],
                ['question' => 'What is Terzaghi ultimate bearing capacity equation for strip footing?', 'type' => 'multiple_choice', 'opt_a' => 'qu = c*Nc + q*Nq + 0.5*gamma*B*Ngamma', 'opt_b' => 'qu = c*Nc', 'opt_c' => 'qu = 1.3*c*Nc', 'opt_d' => 'None', 'correct_answer' => 'A', 'explanation' => 'General Terzaghi strip footing bearing capacity formula.', 'points' => 1, 'source_topic' => 'Foundation Engineering', 'source_academic_period' => 'finals', 'source_confidence' => 'high']
            ];


            preg_match_all('/Lesson ID:\s*(\d+)/i', $userPrompt, $lMatch);
            $promptLids = !empty($lMatch[1]) ? array_values(array_unique(array_map('intval', $lMatch[1]))) : [];
            if (empty($promptLids) && preg_match('/source_lesson_ids.*?\[([\d,\s]+)\]/i', $userPrompt, $eMatch)) {
                $promptLids = array_values(array_unique(array_map('intval', explode(',', $eMatch[1]))));
            }

            preg_match('/Period:\s*([^\r\n]+)/i', $userPrompt, $pMatch);
            $promptPeriod = !empty($pMatch[1]) ? strtolower(trim($pMatch[1])) : null;

            $isMissingSourceMock = (bool)preg_match('/MOCK_MISSING_SOURCE/i', $userPrompt);
            if ($isMissingSourceMock && preg_match('/chunk \((\d+) of/i', $userPrompt, $cm)) {
                if (intval($cm[1]) > 1) {
                    $isMissingSourceMock = false;
                }
            }
            $isIncompleteBatchMock = (bool)preg_match('/MOCK_INCOMPLETE_BATCH/i', $userPrompt);
            $isRefillMidtermMock = (bool)preg_match('/MOCK_REFILL_MIDTERM/i', $userPrompt);

            // Detect midterm CHUNK content via lesson period marker, not exam title
            // 'Period: midterm' appears in lesson content; avoid matching 'MOCK_REFILL_MIDTERM' in title
            $isMidtermChunk = (bool)preg_match('/Period:\s*midterm/i', $userPrompt);
            // For chunk prompts, also check the chunk index text for midterm period
            if (!$isMidtermChunk) {
                $isMidtermChunk = (bool)preg_match('/Midterm\s+(Module|Design|Chapter|Content|Section)/i', $userPrompt);
            }

            // Detect chunk index from prompt
            preg_match('/lesson chunk \((\d+) of/i', $userPrompt, $chunkNumMatch);
            $currentChunkNum = !empty($chunkNumMatch[1]) ? (intval($chunkNumMatch[1]) - 1) : null;

            if (preg_match('/MOCK_FAIL_CHUNK_0_2/i', $userPrompt) && ($currentChunkNum === 0 || $currentChunkNum === 2)) {
                return [
                    'success' => false,
                    'error' => "Simulated Chunk {$currentChunkNum} failure [MOCK_FAIL_CHUNK_0_2]",
                    'user_message' => "Simulated Chunk {$currentChunkNum} failure [MOCK_FAIL_CHUNK_0_2]",
                    'error_code' => 'MOCK_CHUNK_FAILURE',
                    'provider_status' => 500
                ];
            }

            if (preg_match('/MOCK_FAIL_CHUNK_0/i', $userPrompt) && $currentChunkNum === 0) {
                return [
                    'success' => false,
                    'error' => 'Simulated Chunk 0 failure [MOCK_FAIL_CHUNK_0]',
                    'user_message' => 'Simulated Chunk 0 failure [MOCK_FAIL_CHUNK_0]',
                    'error_code' => 'MOCK_CHUNK_FAILURE',
                    'provider_status' => 500
                ];
            }

            if (preg_match('/MOCK_FAIL_CHUNK_1/i', $userPrompt) && $currentChunkNum === 1) {
                return [
                    'success' => false,
                    'error' => 'Simulated Chunk 1 failure [MOCK_FAIL_CHUNK_1]',
                    'user_message' => 'Simulated Chunk 1 failure [MOCK_FAIL_CHUNK_1]',
                    'error_code' => 'MOCK_CHUNK_FAILURE',
                    'provider_status' => 500
                ];
            }

            if (preg_match('/MOCK_FAIL_CHUNK_2/i', $userPrompt) && $currentChunkNum === 2) {
                return [
                    'success' => false,
                    'error' => 'Simulated Chunk 2 failure [MOCK_FAIL_CHUNK_2]',
                    'user_message' => 'Simulated Chunk 2 failure [MOCK_FAIL_CHUNK_2]',
                    'error_code' => 'MOCK_CHUNK_FAILURE',
                    'provider_status' => 500
                ];
            }

            // Scenario 2: Deterministic Incomplete Batch failure for Midterm chunk
            if ($isIncompleteBatchMock && $isMidtermChunk) {
                return [
                    'success' => false,
                    'error' => 'Simulated Midterm chunk failure [MOCK_INCOMPLETE_BATCH]',
                    'user_message' => 'Simulated Midterm chunk failure [MOCK_INCOMPLETE_BATCH]',
                    'error_code' => 'MOCK_CHUNK_FAILURE',
                    'provider_status' => 500
                ];
            }

            // Scenario 3: Deterministic Initial Shortfall for Midterm chunk (fails on initial pass, succeeds on refill pass)
            if ($isRefillMidtermMock && $isMidtermChunk && !preg_match('/ADDITIONAL/i', $userPrompt)) {
                return [
                    'success' => false,
                    'error' => 'Simulated Midterm initial shortfall [MOCK_REFILL_MIDTERM]',
                    'user_message' => 'Simulated Midterm initial shortfall [MOCK_REFILL_MIDTERM]',
                    'error_code' => 'MOCK_CHUNK_FAILURE',
                    'provider_status' => 500
                ];
            }

            $mockQuestions = [];
            $baseOffset = ($currentChunkNum !== null) ? ($currentChunkNum * 5) : 0;
            for ($i = 0; $i < $targetCount; $i++) {
                $item = $basePool[($i + $baseOffset) % count($basePool)];
                if (!empty($promptLids)) {
                    $item['source_lesson_ids'] = $promptLids;
                } else {
                    $item['source_lesson_ids'] = [101];
                }
                if (!empty($promptPeriod)) {
                    $item['source_academic_period'] = $promptPeriod;
                }

                // Scenario 1: Missing Source test strips source_lesson_ids from Item #1 and sets unique marker
                if ($isMissingSourceMock && $i === 0) {
                    $item['question'] .= ' [SOURCE_REVIEW_REQUIRED_MARKER]';
                    $item['source_lesson_ids'] = [];
                    $item['source_confidence'] = 'review_required';
                }

                if (preg_match('/ADDITIONAL/i', $userPrompt)) {
                    $item['question'] = "Unique Refill Test Question #" . ($i + 1) . " on " . $item['source_topic'] . " " . substr(md5($userPrompt . $i), 0, 8);
                } elseif (preg_match('/lesson chunk \((\d+) of (\d+)\)/i', $userPrompt, $cm)) {
                    $item['question'] .= " (Chunk " . ($cm[1] ?? '1') . " Item " . ($i + 1) . " Key: " . sha1($userPrompt . $i . ($cm[1] ?? '1')) . ")";
                } else {
                    $item['question'] .= " (Item #" . ($i + 1) . ")";
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

    public static function normalizeQuestionText(string $text): string {
        $text = mb_strtolower($text, 'UTF-8');
        $text = preg_replace('/[^\w\s]/u', '', $text);
        $text = preg_replace('/\s+/', ' ', $text);
        return trim($text);
    }

    public static function validateAndCalculatePeriodWeights(string $mode, array $weights, array $selectedPeriods, int $totalQuestions): array {
        if ($totalQuestions <= 0) {
            throw new InvalidArgumentException("Total questions must be a positive integer.");
        }

        $selectedPeriods = array_values(array_unique(array_map(function($p) { return strtolower(trim($p)); }, $selectedPeriods)));
        if (empty($selectedPeriods)) {
            $selectedPeriods = ['general'];
        }

        $cleanWeights = [];
        foreach ($weights as $p => $w) {
            $pClean = strtolower(trim($p));
            if ($w !== null && $w !== '') {
                if (!is_numeric($w)) {
                    throw new InvalidArgumentException("Period weight for '{$p}' must be a valid non-negative number.");
                }
                $val = floatval($w);
                if ($val < 0) {
                    throw new InvalidArgumentException("Period weight for '{$p}' cannot be negative.");
                }
                $cleanWeights[$pClean] = $val;
            }
        }

        foreach ($cleanWeights as $p => $w) {
            if ($w > 0 && !in_array($p, $selectedPeriods, true)) {
                throw new InvalidArgumentException("Period '{$p}' has no selected lessons in the material pool and cannot receive a question allocation.");
            }
        }

        $requestedDistribution = [];
        $targetCounts = [];

        if ($mode === 'percentage') {
            $totalPct = 0.0;
            foreach ($selectedPeriods as $p) {
                $pct = isset($cleanWeights[$p]) ? round($cleanWeights[$p], 2) : 0.0;
                $requestedDistribution[$p] = $pct;
                $totalPct += $pct;
            }

            if ($totalPct <= 0 || abs($totalPct - 100.0) > 0.5) {
                throw new InvalidArgumentException("Period percentage distribution must total exactly 100% (got {$totalPct}%).");
            }

            $remainderList = [];
            $allocatedSum = 0;
            foreach ($selectedPeriods as $p) {
                $pct = $requestedDistribution[$p];
                $raw = ($pct / 100.0) * $totalQuestions;
                $cnt = (int)floor($raw);
                $targetCounts[$p] = $cnt;
                $allocatedSum += $cnt;
                $remainderList[$p] = $raw - $cnt;
            }

            $rem = $totalQuestions - $allocatedSum;
            arsort($remainderList);
            foreach ($remainderList as $p => $diff) {
                if ($rem <= 0) break;
                $targetCounts[$p]++;
                $rem--;
            }
        } elseif ($mode === 'fixed') {
            $totalFixed = 0;
            foreach ($selectedPeriods as $p) {
                $cnt = isset($cleanWeights[$p]) ? (int)$cleanWeights[$p] : 0;
                $requestedDistribution[$p] = $cnt;
                $targetCounts[$p] = $cnt;
                $totalFixed += $cnt;
            }
            if ($totalFixed !== $totalQuestions) {
                throw new InvalidArgumentException("Fixed period question counts must sum to the requested total questions ({$totalQuestions}, got {$totalFixed}).");
            }
        } else {
            $mode = 'equal';
            $base = (int)floor($totalQuestions / count($selectedPeriods));
            $rem = $totalQuestions % count($selectedPeriods);
            foreach ($selectedPeriods as $idx => $p) {
                $cnt = $base + ($idx < $rem ? 1 : 0);
                $requestedDistribution[$p] = round(100.0 / count($selectedPeriods), 2);
                $targetCounts[$p] = $cnt;
            }
        }

        // Invariant check: sum(target_counts) == total_questions
        $sumTarget = array_sum($targetCounts);
        if ($sumTarget !== $totalQuestions) {
            throw new InvalidArgumentException("Calculated period target counts sum ({$sumTarget}) does not equal requested total questions ({$totalQuestions}).");
        }

        return [
            'mode' => $mode,
            'requested_distribution' => $requestedDistribution,
            'target_counts' => $targetCounts
        ];
    }

    public static function validateAndCalculateBlueprint(array $blueprint, int $totalQuestions, string $fallbackType = 'multiple_choice'): array {
        $supportedTypes = ['multiple_choice', 'true_false', 'identification', 'fill_blank', 'matching', 'problem_solving', 'math_formula'];
        
        if (empty($blueprint) || array_sum(array_map('intval', $blueprint)) === 0) {
            if (!in_array($fallbackType, $supportedTypes, true)) {
                $fallbackType = 'multiple_choice';
            }
            return [
                'requested_blueprint' => [$fallbackType => $totalQuestions],
                'target_counts' => [$fallbackType => $totalQuestions]
            ];
        }

        $cleanBlueprint = [];
        $totalCount = 0;
        foreach ($blueprint as $type => $count) {
            $typeClean = strtolower(trim($type));
            if (!in_array($typeClean, $supportedTypes, true)) {
                throw new InvalidArgumentException("Unsupported question type '{$type}' in blueprint.");
            }
            $cnt = (int)$count;
            if ($cnt < 0) {
                throw new InvalidArgumentException("Question count for type '{$type}' cannot be negative.");
            }
            if ($cnt > 0) {
                $cleanBlueprint[$typeClean] = $cnt;
                $totalCount += $cnt;
            }
        }

        if ($totalCount !== $totalQuestions) {
            throw new InvalidArgumentException("Question blueprint totals must equal total requested questions ({$totalQuestions}, got {$totalCount}).");
        }

        $sumTarget = array_sum($cleanBlueprint);
        if ($sumTarget !== $totalQuestions) {
            throw new InvalidArgumentException("Calculated blueprint target counts sum ({$sumTarget}) does not equal requested total questions ({$totalQuestions}).");
        }

        return [
            'requested_blueprint' => $cleanBlueprint,
            'target_counts' => $cleanBlueprint
        ];
    }

    public static function validateQuestionItem(array $q): array {
        $supportedTypes = ['multiple_choice', 'true_false', 'identification', 'fill_blank', 'matching', 'problem_solving', 'math_formula'];
        
        $type = strtolower(trim($q['type'] ?? $q['question_type'] ?? ''));
        if ($type === 'fill_in_the_blank') $type = 'fill_blank';
        if ($type === 'matching_type') $type = 'matching';

        if (!in_array($type, $supportedTypes, true)) {
            throw new InvalidArgumentException("Unsupported question type '{$type}'.");
        }

        $text = trim($q['text'] ?? $q['question'] ?? $q['question_text'] ?? '');
        if (empty($text)) {
            throw new InvalidArgumentException("Question text cannot be empty.");
        }

        $correct = trim($q['correct'] ?? $q['correct_answer'] ?? '');
        if (empty($correct) && $type !== 'problem_solving' && $type !== 'math_formula') {
            throw new InvalidArgumentException("Answer key is required for type '{$type}'.");
        }

        $points = floatval($q['points'] ?? 1);
        if ($points <= 0) {
            throw new InvalidArgumentException("Question points must be a positive number.");
        }

        if ($type === 'multiple_choice') {
            $optA = trim($q['opt_a'] ?? $q['option_a'] ?? '');
            $optB = trim($q['opt_b'] ?? $q['option_b'] ?? '');
            if (empty($optA) || empty($optB)) {
                throw new InvalidArgumentException("Multiple choice questions require options A and B at minimum.");
            }
        }

        if ($type === 'matching') {
            $pairs = $q['matching_pairs'] ?? null;
            if (empty($pairs)) {
                throw new InvalidArgumentException("Matching questions require matching pairs metadata.");
            }
        }

        if ($type === 'math_formula') {
            $formula = trim($q['formula_latex'] ?? '');
            if (empty($formula) && empty($correct)) {
                throw new InvalidArgumentException("Math formula questions require formula metadata or answer expression.");
            }
        }

        return [
            'valid' => true,
            'type' => $type,
            'text' => $text,
            'correct' => $correct,
            'points' => $points
        ];
    }

    public static function validateAndCalculateDifficulty(string $mode, array $distribution, int $totalQuestions, string $fallbackDifficulty = 'medium'): array {
        $supportedDiffs = ['easy', 'medium', 'hard'];
        if (!in_array($fallbackDifficulty, $supportedDiffs, true)) {
            $fallbackDifficulty = 'medium';
        }

        if ($mode === 'percentage') {
            if (empty($distribution)) {
                throw new InvalidArgumentException("Difficulty percentage distribution cannot be empty.");
            }
            $clean = [];
            $sumPct = 0;
            foreach ($distribution as $d => $val) {
                $dClean = strtolower(trim($d));
                if (!in_array($dClean, $supportedDiffs, true)) continue;
                if (!is_numeric($val) || floatval($val) < 0) {
                    throw new InvalidArgumentException("Difficulty percentage value for '{$d}' must be a non-negative number.");
                }
                $clean[$dClean] = floatval($val);
                $sumPct += floatval($val);
            }
            if (abs($sumPct - 100.0) > 0.5 || $sumPct <= 0) {
                throw new InvalidArgumentException("Difficulty percentage distribution must total exactly 100% (got {$sumPct}%).");
            }
            $targetCounts = [];
            $allocatedSum = 0;
            $remList = [];
            foreach ($supportedDiffs as $d) {
                $pct = $clean[$d] ?? 0;
                $raw = ($pct / 100.0) * $totalQuestions;
                $cnt = (int)floor($raw);
                $targetCounts[$d] = $cnt;
                $allocatedSum += $cnt;
                $remList[$d] = $raw - $cnt;
            }
            $rem = $totalQuestions - $allocatedSum;
            arsort($remList);
            foreach ($remList as $d => $diff) {
                if ($rem <= 0) break;
                $targetCounts[$d]++;
                $rem--;
            }
            $sumTarget = array_sum($targetCounts);
            if ($sumTarget !== $totalQuestions) {
                throw new InvalidArgumentException("Calculated difficulty target counts sum ({$sumTarget}) does not equal requested total questions ({$totalQuestions}).");
            }
            return [
                'mode' => 'percentage',
                'requested_distribution' => $clean,
                'target_counts' => $targetCounts
            ];
        } elseif ($mode === 'fixed' && !empty($distribution)) {
            $clean = [];
            $sum = 0;
            foreach ($distribution as $d => $val) {
                $dClean = strtolower(trim($d));
                if (in_array($dClean, $supportedDiffs, true)) {
                    $cnt = (int)$val;
                    if ($cnt < 0) throw new InvalidArgumentException("Difficulty count for '{$d}' cannot be negative.");
                    $clean[$dClean] = $cnt;
                    $sum += $cnt;
                }
            }
            if ($sum !== $totalQuestions) {
                throw new InvalidArgumentException("Fixed difficulty counts must sum to requested total questions ({$totalQuestions}, got {$sum}).");
            }
            return [
                'mode' => 'fixed',
                'requested_distribution' => $clean,
                'target_counts' => $clean
            ];
        } else {
            return [
                'mode' => 'single',
                'requested_distribution' => [$fallbackDifficulty => $totalQuestions],
                'target_counts' => [$fallbackDifficulty => $totalQuestions]
            ];
        }
    }

    public static function generateQuestions($lessonText, $numQuestions, $subject, $examTitle, $specialization = 'Structural Engineering', $questionType = 'multiple_choice', $difficulty = 'medium', $apiKey = null, $options = []) {

        $startTime = microtime(true);

        if (empty(trim($lessonText)) || strlen(trim($lessonText)) < 20) {
            return ['error' => 'Selected lesson text is too short or empty for AI question generation.'];
        }

        if ($numQuestions <= 0) {
            return ['error' => 'Question count must be at least 1.'];
        }

        // 1. Period Weighting Validation
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
        if (empty($allSelectedPeriods)) {
            $allSelectedPeriods = ['general'];
        }

        $periodMode = $options['period_weighting_mode'] ?? 'equal';
        $periodWeights = $options['period_weights'] ?? [];
        try {
            $periodWeightInfo = self::validateAndCalculatePeriodWeights($periodMode, $periodWeights, $allSelectedPeriods, $numQuestions);
        } catch (InvalidArgumentException $e) {
            return ['error' => $e->getMessage()];
        }

        // 2. Blueprint Validation
        $blueprintInput = $options['question_blueprint'] ?? [];
        if (empty($blueprintInput) && is_string($questionType) && !empty($questionType)) {
            $blueprintInput = [$questionType => $numQuestions];
        }
        try {
            $blueprintInfo = self::validateAndCalculateBlueprint($blueprintInput, $numQuestions, is_string($questionType) ? $questionType : 'multiple_choice');
        } catch (InvalidArgumentException $e) {
            return ['error' => $e->getMessage()];
        }

        // 3. Difficulty Distribution Validation
        $diffMode = $options['difficulty_mode'] ?? 'single';
        $diffDistInput = $options['difficulty_distribution'] ?? [];
        try {
            $difficultyInfo = self::validateAndCalculateDifficulty($diffMode, $diffDistInput, $numQuestions, is_string($difficulty) ? $difficulty : 'medium');
        } catch (InvalidArgumentException $e) {
            return ['error' => $e->getMessage()];
        }

        $charLength = strlen($lessonText);
        $wordCount = str_word_count($lessonText);
        $estimatedTokens = (int)ceil($charLength / 4);

        $chunkLimit = (defined('TEST_CHUNK_LIMIT') && TEST_CHUNK_LIMIT > 0) ? TEST_CHUNK_LIMIT : (self::isTestModeActive() ? 200 : (defined('AI_SAFE_INPUT_TOKENS') ? (AI_SAFE_INPUT_TOKENS * 4) : 96000));
        $generationWarnings = [];
        $duplicateWarnings = [];
        $duplicateCount = 0;
        $replacementAttemptCount = 0;
        $rawChunkResponses = [];

        if ($charLength > $chunkLimit || self::isTestModeActive()) {
            preg_match_all('/(SOURCE LESSON \d+[\s\S]*?)(?=(?:SOURCE LESSON \d+|\z))/i', $lessonText, $matches);
            $initialBlocks = !empty($matches[1]) ? $matches[1] : preg_split('/\n{2,}/', $lessonText);

            $lessonBlocks = [];
            foreach ($initialBlocks as $blk) {
                if (strlen($blk) > $chunkLimit) {
                    $subBlocks = preg_split('/(?:\n(?=#+|\=+\s|-{3,}))|(?:\n{2,})/', $blk);
                    foreach ($subBlocks as $sblk) {
                        if (strlen($sblk) > $chunkLimit) {
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
            if (self::isTestModeActive() && !empty($matches[1]) && count($matches[1]) > 1) {
                $chunks = $matches[1];
            } else {
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
            }

            $validQuestions = [];
            $seenHashes = [];
            $seenAnswers = [];
            $seenQuestions = [];
            $totalChunks = count($chunks);
            $failedChunkCount = 0;
            $failedChunkIndexes = [];
            $failedChunksDetailed = [];
            $affectedLessonIds = [];
            $affectedPeriods = [];
            $chunkGenerationResults = [];
            $executedSimulatedScenario = null;

            // Calculate chunk allocations respecting period targets
            $chunkAllocations = [];
            $periodChunks = [];
            $activeLessonId = null;
            $activePeriod = 'general';

            foreach ($chunks as $cIdx => &$cContent) {
                preg_match('/Lesson ID:\s*(\d+)/i', $cContent, $cLMatch);
                if (!empty($cLMatch[1])) {
                    $activeLessonId = intval($cLMatch[1]);
                } elseif ($activeLessonId !== null) {
                    $cContent = "Lesson ID: {$activeLessonId}\n" . $cContent;
                }

                preg_match('/Period:\s*([^\r\n]+)/i', $cContent, $cPMatch);
                if (!empty($cPMatch[1])) {
                    $activePeriod = strtolower(trim($cPMatch[1]));
                } elseif ($activePeriod !== 'general') {
                    $cContent = "Period: {$activePeriod}\n" . $cContent;
                }

                $periodChunks[$activePeriod][] = $cIdx;
            }
            unset($cContent);


            foreach ($periodWeightInfo['target_counts'] as $p => $pTarget) {
                $cList = $periodChunks[$p] ?? [];
                if (!empty($cList)) {
                    $baseAlloc = (int)floor($pTarget / count($cList));
                    $remAlloc = $pTarget % count($cList);
                    foreach ($cList as $idxInList => $cIdx) {
                        $chunkAllocations[$cIdx] = $baseAlloc + ($idxInList < $remAlloc ? 1 : 0);
                    }
                }
            }
            for ($c = 0; $c < $totalChunks; $c++) {
                if (!isset($chunkAllocations[$c])) {
                    $chunkAllocations[$c] = max(1, (int)floor($numQuestions / $totalChunks));
                }
            }

            foreach ($chunks as $chunkIdx => $chunkContent) {
                $chunkShare = $chunkAllocations[$chunkIdx] ?? max(1, (int)round($numQuestions / $totalChunks));
                
                preg_match_all('/Lesson ID:\s*(\d+)/i', $chunkContent, $lIdMatches);
                $chunkLessonIds = !empty($lIdMatches[1]) ? array_values(array_unique(array_map('intval', $lIdMatches[1]))) : [];

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

                $blueprintStr = json_encode($blueprintInfo['target_counts']);
                $difficultyStr = json_encode($difficultyInfo['target_counts']);

                $chunkPrompt = "You are an expert Civil Engineering professor specializing in {$specialization} and academic assessment creation. "
                             . "Generate exactly {$chunkShare} high-quality Civil Engineering examination questions for the subject '{$subject}' (Specialization: {$specialization}) titled '{$examTitle}'. "
                             . "Target Difficulty Distribution: '{$difficultyStr}'. "
                             . "Target Question Type Blueprint: '{$blueprintStr}'. "
                             . "based strictly on the following lesson chunk (" . ($chunkIdx + 1) . " of {$totalChunks}): \"{$chunkContent}\". "
                             . "Do NOT invent facts outside the lesson content. "
                             . "Format response strictly as a JSON array of objects without markdown code blocks. "
                             . "Each object MUST have: \"question\" (string), \"type\" (string), \"opt_a\" (string or null), \"opt_b\" (string or null), \"opt_c\" (string or null), \"opt_d\" (string or null), "
                             . "\"correct_answer\" (string), \"difficulty\" (string: 'easy', 'medium', or 'hard'), \"formula_latex\" (string or null), \"matching_pairs\" (object or null), \"explanation\" (string), \"points\" (int), "
                             . "\"source_lesson_ids\" (array of integers, e.g. [" . implode(',', $chunkLessonIds) . "]), \"source_topic\" (string), \"source_academic_period\" (string), \"source_confidence\" (string: 'high', 'medium', or 'review_required').";

                $payload = [
                    'model' => GROQ_DEFAULT_MODEL,
                    'messages' => [['role' => 'user', 'content' => $chunkPrompt]],
                    'temperature' => 0.3
                ];

                $chunkCallFailed = false;
                $invalidQuestionCount = 0;
                $chunkDuplicateCount = 0;
                $acceptedFromChunk = 0;
                $rawGeneratedCount = 0;

                $res = self::sendRequest($payload, $apiKey);
                if (isset($res['success']) && $res['success'] === false && in_array($res['error_code'] ?? '', ['MISSING_API_KEY', 'INVALID_API_KEY'], true)) {
                    return $res;
                }

                if (isset($res['error']) || (isset($res['success']) && $res['success'] === false)) {
                    $chunkCallFailed = true;
                    $failedChunkIndexes[] = (int)$chunkIdx;
                    $affectedLessonIds = array_merge($affectedLessonIds, $chunkLessonIds);
                    $affectedPeriods = array_merge($affectedPeriods, $chunkPeriods);
                    $errMsg = $res['user_message'] ?? $res['error'] ?? 'Chunk generation failed';
                    $generationWarnings[] = "Chunk #" . $chunkIdx . " (of {$totalChunks}) failed: " . $errMsg;
                    $failedChunksDetailed[] = [
                        'chunk_index' => (int)$chunkIdx,
                        'lesson_ids' => $chunkLessonIds,
                        'periods' => $chunkPeriods,
                        'error_code' => $res['error_code'] ?? 'MOCK_CHUNK_FAILURE',
                        'message' => $errMsg
                    ];
                    if (self::isTestModeActive()) {
                        if (preg_match('/MOCK_INCOMPLETE_BATCH/i', $examTitle)) {
                            $executedSimulatedScenario = 'incomplete_midterm_chunk';
                        } elseif (preg_match('/MOCK_REFILL_MIDTERM/i', $examTitle)) {
                            $executedSimulatedScenario = 'midterm_refill';
                        }
                    }
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

                            // Practical Deduplication Check
                            $normText = self::normalizeQuestionText($qText);
                            $qKey = $normText . '|' . self::normalizeQuestionText($qCorrect);

                            $isDup = false;
                            $dupReason = '';
                            if (isset($seenHashes[$normText])) {
                                $isDup = true;
                                $dupReason = "Exact duplicate question text: \"{$qText}\"";
                            } elseif (isset($seenAnswers[$qKey])) {
                                $isDup = true;
                                $dupReason = "Duplicate question and answer key combination: \"{$qText}\"";
                            } else {
                                foreach ($seenQuestions as $sq) {
                                    $sqNorm = self::normalizeQuestionText($sq['question']);
                                    similar_text($normText, $sqNorm, $pct);
                                    if ($pct >= 85.0) {
                                        $isDup = true;
                                        $dupReason = "High text similarity (" . round($pct, 1) . "%) with: \"{$sq['question']}\"";
                                        break;
                                    }
                                }
                            }

                            if ($isDup) {
                                $chunkDuplicateCount++;
                                $duplicateCount++;
                                $duplicateWarnings[] = $dupReason;
                                $replacementAttemptCount++;
                                continue;
                            }

                            $seenHashes[$normText] = true;
                            $seenAnswers[$qKey] = true;
                            $seenQuestions[] = $q;

                            $srcLessonIds = is_array($q['source_lesson_ids'] ?? null) ? array_map('intval', $q['source_lesson_ids']) : [];
                            $srcConfidence = $q['source_confidence'] ?? 'high';
                            $sourceVerificationNote = null;

                            if (!empty($srcLessonIds) && !empty($chunkLessonIds)) {
                                $srcLessonIds = array_values(array_unique(array_intersect($srcLessonIds, $chunkLessonIds)));
                            }

                            if ($srcConfidence === 'review_required') {
                                $srcLessonIds = [];
                                $sourceVerificationNote = 'Refill question source ambiguous within multi-lesson chunk';
                                if (self::isTestModeActive() && preg_match('/MOCK_MISSING_SOURCE/i', $examTitle)) {
                                    $executedSimulatedScenario = 'missing_source';
                                }
                            } elseif (empty($srcLessonIds)) {
                                if (count($chunkLessonIds) === 1) {
                                    $srcLessonIds = $chunkLessonIds;
                                    $srcConfidence = 'high';
                                    $sourceVerificationNote = 'Server-derived single-source attribution';
                                } else {
                                    $srcLessonIds = [];
                                    $srcConfidence = 'review_required';
                                    $sourceVerificationNote = 'Refill question source ambiguous within multi-lesson chunk';
                                    if (self::isTestModeActive() && preg_match('/MOCK_MISSING_SOURCE/i', $examTitle)) {
                                        $executedSimulatedScenario = 'missing_source';
                                    }
                                }
                            }

                            $srcPeriod = strtolower(trim($q['source_academic_period'] ?? ''));
                            if (empty($srcPeriod) || $srcPeriod === 'general') {
                                $srcPeriod = !empty($chunkPeriods) ? $chunkPeriods[0] : 'general';
                            }

                            $retDiff = strtolower(trim($q['difficulty'] ?? ''));
                            if (!in_array($retDiff, ['easy', 'medium', 'hard'], true)) {
                                $retDiff = 'unclassified';
                            }

                            $validQuestions[] = [
                                'question' => $qText,
                                'type' => trim($q['type'] ?? (is_string($questionType) ? $questionType : 'multiple_choice')),
                                'opt_a' => $q['opt_a'] ?? null,
                                'opt_b' => $q['opt_b'] ?? null,
                                'opt_c' => $q['opt_c'] ?? null,
                                'opt_d' => $q['opt_d'] ?? null,
                                'correct_answer' => $qCorrect,
                                'formula_latex' => $q['formula_latex'] ?? null,
                                'matching_pairs' => $q['matching_pairs'] ?? null,
                                'explanation' => $q['explanation'] ?? '',
                                'points' => intval($q['points'] ?? 1),
                                'difficulty' => $retDiff,
                                'topic' => $q['source_topic'] ?? $subject,
                                'source_lesson_ids' => $srcLessonIds,
                                'source_topic' => $q['source_topic'] ?? $subject,
                                'source_academic_period' => $srcPeriod,
                                'source_confidence' => $srcConfidence,
                                'source_review_required' => ($srcConfidence === 'review_required'),
                                'source_verification_note' => $sourceVerificationNote,
                                'target_chunk_lesson_ids' => $chunkLessonIds
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
                    'duplicate_count' => $chunkDuplicateCount,
                    'failed_count' => $failedCount,
                    'final_accepted_count' => $acceptedFromChunk
                ];
            }

            // Shortfall Refill Pass
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

                $initialQuestionsPerLesson = $lessonCoverage;
                $initialQuestionsPerPeriod = $periodCoverage;
                $initialUncoveredLessonIds = $zeroLessons;
                $initialUncoveredPeriods = $zeroPeriods;

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

                $refillTargetChunkIndex = !empty($refillQueue) ? $refillQueue[0] : null;
                $refillTargetLessonIds = ($refillTargetChunkIndex !== null && isset($chunkGenerationResults[$refillTargetChunkIndex])) ? ($chunkGenerationResults[$refillTargetChunkIndex]['source_lesson_ids'] ?? []) : [];
                $refillTargetPeriods = ($refillTargetChunkIndex !== null && isset($chunkGenerationResults[$refillTargetChunkIndex])) ? ($chunkGenerationResults[$refillTargetChunkIndex]['academic_periods'] ?? []) : [];
                $refillGeneratedCount = 0;

                $qIndex = 0;
                $maxRefillAttempts = count($refillQueue) * 2;

                while (count($validQuestions) < $numQuestions && $refillAttemptCount < $maxRefillAttempts && !empty($refillQueue)) {
                    $targetChunkIdx = $refillQueue[$qIndex % count($refillQueue)];
                    $qIndex++;
                    $refillAttemptCount++;

                    $targetChunkContent = $chunks[$targetChunkIdx];
                    $targetChunkLessonIds = $chunkGenerationResults[$targetChunkIdx]['source_lesson_ids'] ?? [];
                    if (empty($targetChunkLessonIds)) {
                        preg_match_all('/Lesson ID:\s*(\d+)/i', $targetChunkContent, $tcLMatches);
                        $targetChunkLessonIds = !empty($tcLMatches[1]) ? array_values(array_unique(array_map('intval', $tcLMatches[1]))) : [];
                    }
                    $targetChunkPeriods = $chunkGenerationResults[$targetChunkIdx]['academic_periods'] ?? [];

                    $currentShortfall = $numQuestions - count($validQuestions);
                    $targetDeficit = max(1, ($chunkAllocations[$targetChunkIdx] ?? 1) - ($chunkGenerationResults[$targetChunkIdx]['final_accepted_count'] ?? 0));
                    $neededRefill = min($currentShortfall, max(1, $targetDeficit));

                    $refillPrompt = "You are an expert Civil Engineering professor specializing in {$specialization}. "
                                  . "Generate exactly {$neededRefill} ADDITIONAL non-duplicate examination questions for the subject '{$subject}' (Specialization: {$specialization}) titled '{$examTitle}'. "
                                  . "Target Difficulty Level: '{$difficulty}'. Target Question Type: '" . (is_string($questionType) ? $questionType : 'multiple_choice') . "'. "
                                  . "based strictly on the following lesson content chunk (" . ($targetChunkIdx + 1) . " of {$totalChunks}): \"{$targetChunkContent}\". "
                                  . "Do NOT invent facts outside the lesson content. "
                                  . "Format response strictly as a JSON array of objects without markdown code blocks. "
                                  . "Each object MUST have: \"question\" (string), \"type\" (string), \"opt_a\" (string or null), \"opt_b\" (string or null), \"opt_c\" (string or null), \"opt_d\" (string or null), "
                                  . "\"correct_answer\" (string), \"difficulty\" (string), \"formula_latex\" (string or null), \"matching_pairs\" (object or null), \"explanation\" (string), \"points\" (int), "
                                  . "\"source_lesson_ids\" (array of integers, e.g. [" . implode(',', $targetChunkLessonIds) . "]), \"source_topic\" (string), \"source_academic_period\" (string), \"source_confidence\" (string: 'high', 'medium', or 'review_required').";

                    $refillPayload = [
                        'model' => GROQ_DEFAULT_MODEL,
                        'messages' => [['role' => 'user', 'content' => $refillPrompt]],
                        'temperature' => 0.3
                    ];

                    $refillRes = self::sendRequest($refillPayload, $apiKey);
                    $replacementAttemptCount++;
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

                                $normText = self::normalizeQuestionText($rqText);
                                $qKey = $normText . '|' . self::normalizeQuestionText($rqCorrect);
                                $isDup = false;
                                if (isset($seenHashes[$normText]) || isset($seenAnswers[$qKey])) {
                                    $isDup = true;
                                } else {
                                    foreach ($seenQuestions as $sq) {
                                        $sqNorm = self::normalizeQuestionText($sq['question']);
                                        similar_text($normText, $sqNorm, $pct);
                                        if ($pct >= 85.0) { $isDup = true; break; }
                                    }
                                }
                                if ($isDup) {
                                    $duplicateCount++;
                                    continue;
                                }
                                $seenHashes[$normText] = true;
                                $seenAnswers[$qKey] = true;
                                $seenQuestions[] = $rq;

                                $srcLessonIds = is_array($rq['source_lesson_ids'] ?? null) ? array_map('intval', $rq['source_lesson_ids']) : [];
                                $srcConfidence = $rq['source_confidence'] ?? 'high';
                                $sourceVerificationNote = null;

                                if (!empty($srcLessonIds) && !empty($targetChunkLessonIds)) {
                                    $srcLessonIds = array_values(array_unique(array_intersect($srcLessonIds, $targetChunkLessonIds)));
                                }

                                if ($srcConfidence === 'review_required') {
                                    $srcLessonIds = [];
                                    $sourceVerificationNote = 'Refill question source ambiguous within multi-lesson chunk';
                                } elseif (empty($srcLessonIds)) {
                                    if (count($targetChunkLessonIds) === 1) {
                                        $srcLessonIds = $targetChunkLessonIds;
                                        $srcConfidence = 'high';
                                        $sourceVerificationNote = 'Server-derived single-source attribution';
                                    } else {
                                        $srcLessonIds = [];
                                        $srcConfidence = 'review_required';
                                        $sourceVerificationNote = 'Refill question source ambiguous within multi-lesson chunk';
                                    }
                                }

                                $srcPeriod = strtolower(trim($rq['source_academic_period'] ?? ''));
                                if (empty($srcPeriod) || $srcPeriod === 'general') {
                                    $srcPeriod = !empty($targetChunkPeriods) ? $targetChunkPeriods[0] : 'general';
                                }

                                $retDiff = strtolower(trim($rq['difficulty'] ?? ''));
                                if (!in_array($retDiff, ['easy', 'medium', 'hard'], true)) {
                                    $retDiff = 'unclassified';
                                }

                                $validQuestions[] = [
                                    'question' => $rqText,
                                    'type' => trim($rq['type'] ?? (is_string($questionType) ? $questionType : 'multiple_choice')),
                                    'opt_a' => $rq['opt_a'] ?? null,
                                    'opt_b' => $rq['opt_b'] ?? null,
                                    'opt_c' => $rq['opt_c'] ?? null,
                                    'opt_d' => $rq['opt_d'] ?? null,
                                    'correct_answer' => $rqCorrect,
                                    'formula_latex' => $rq['formula_latex'] ?? null,
                                    'matching_pairs' => $rq['matching_pairs'] ?? null,
                                    'explanation' => $rq['explanation'] ?? '',
                                    'points' => intval($rq['points'] ?? 1),
                                    'difficulty' => $retDiff,
                                    'topic' => $rq['source_topic'] ?? $subject,
                                    'source_lesson_ids' => $srcLessonIds,
                                    'source_topic' => $rq['source_topic'] ?? $subject,
                                    'source_academic_period' => $srcPeriod,
                                    'source_confidence' => $srcConfidence,
                                    'source_review_required' => ($srcConfidence === 'review_required'),
                                    'source_verification_note' => $sourceVerificationNote,
                                    'target_chunk_lesson_ids' => $targetChunkLessonIds
                                ];
                                $acceptedThisRefill++;
                                $replacementSuccessCount++;
                                $refillGeneratedCount++;
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

            $validQuestions = array_slice($validQuestions, 0, $numQuestions);
            $finalGeneratedCount = count($validQuestions);
            $shortfallCount = max(0, $numQuestions - $finalGeneratedCount);
            $unresolvedDuplicateCount = max(0, $duplicateCount - $replacementSuccessCount);

            preg_match_all('/Lesson ID:\s*(\d+)/i', $lessonText, $allLMatches);
            $allSelectedLessonIds = !empty($allLMatches[1]) ? array_values(array_unique(array_map('intval', $allLMatches[1]))) : [];

            $questionsPerLesson = array_fill_keys($allSelectedLessonIds, 0);
            $questionsPerPeriod = array_fill_keys($allSelectedPeriods, 0);
            $actualQuestionDistribution = [];
            $actualDifficultyDistribution = ['easy' => 0, 'medium' => 0, 'hard' => 0, 'unclassified' => 0];

            foreach ($validQuestions as $vq) {
                foreach ($vq['source_lesson_ids'] as $lId) {
                    if (isset($questionsPerLesson[(int)$lId])) {
                        $questionsPerLesson[(int)$lId]++;
                    }
                }
                $p = strtolower($vq['source_academic_period'] ?? 'general');
                if (isset($questionsPerPeriod[$p])) {
                    $questionsPerPeriod[$p]++;
                } else {
                    $questionsPerPeriod[$p] = 1;
                }

                $t = strtolower($vq['type'] ?? 'multiple_choice');
                if (!isset($actualQuestionDistribution[$t])) {
                    $actualQuestionDistribution[$t] = 0;
                }
                $actualQuestionDistribution[$t]++;

                $d = strtolower($vq['difficulty'] ?? 'unclassified');
                if (isset($actualDifficultyDistribution[$d])) {
                    $actualDifficultyDistribution[$d]++;
                } else {
                    $actualDifficultyDistribution[$d] = 1;
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

            $periodMismatch = false;
            foreach ($periodWeightInfo['target_counts'] as $per => $targetCnt) {
                if (($questionsPerPeriod[$per] ?? 0) !== $targetCnt) {
                    $periodMismatch = true;
                    break;
                }
            }

            $blueprintMismatch = false;
            foreach ($blueprintInfo['target_counts'] as $type => $targetCnt) {
                if (($actualQuestionDistribution[$type] ?? 0) !== $targetCnt) {
                    $blueprintMismatch = true;
                    break;
                }
            }

            $difficultyMismatch = false;
            foreach ($difficultyInfo['target_counts'] as $diff => $targetCnt) {
                if (($actualDifficultyDistribution[$diff] ?? 0) !== $targetCnt) {
                    $difficultyMismatch = true;
                    break;
                }
            }

            $isCustomBlueprint = !empty($questionBlueprint) && array_sum(array_map('intval', (array)$questionBlueprint)) > 0;
            $isCustomPeriodWeighting = ($periodWeightingMode === 'percentage' || $periodWeightingMode === 'fixed');
            $isCustomDifficulty = ($difficultyMode === 'percentage' || $difficultyMode === 'fixed');

            $batchStatus = ($shortfallCount > 0 || !empty($uncoveredLessonIds) || !empty($uncoveredPeriods) || ($isCustomPeriodWeighting && $periodMismatch) || ($isCustomBlueprint && $blueprintMismatch) || ($isCustomDifficulty && $difficultyMismatch)) ? 'incomplete' : 'completed';

            $unresolvedDifferences = [
                'period' => [],
                'blueprint' => [],
                'difficulty' => []
            ];

            if ($periodMismatch) {
                foreach ($periodWeightInfo['target_counts'] as $per => $targetCnt) {
                    $act = $questionsPerPeriod[$per] ?? 0;
                    if ($act !== $targetCnt) {
                        $unresolvedDifferences['period'][$per] = ['requested' => $targetCnt, 'actual' => $act, 'diff' => $targetCnt - $act];
                    }
                }
            }
            if ($blueprintMismatch) {
                foreach ($blueprintInfo['target_counts'] as $type => $targetCnt) {
                    $act = $actualQuestionDistribution[$type] ?? 0;
                    if ($act !== $targetCnt) {
                        $unresolvedDifferences['blueprint'][$type] = ['requested' => $targetCnt, 'actual' => $act, 'diff' => $targetCnt - $act];
                    }
                }
            }
            if ($difficultyMismatch) {
                foreach ($difficultyInfo['target_counts'] as $diff => $targetCnt) {
                    $act = $actualDifficultyDistribution[$diff] ?? 0;
                    if ($act !== $targetCnt) {
                        $unresolvedDifferences['difficulty'][$diff] = ['requested' => $targetCnt, 'actual' => $act, 'diff' => $targetCnt - $act];
                    }
                }
            }

            if (!empty($uncoveredLessonIds)) {
                $generationWarnings[] = "Selected lesson(s) with zero question coverage: " . implode(', ', $uncoveredLessonIds);
            }
            if (!empty($uncoveredPeriods)) {
                $generationWarnings[] = "Academic period(s) with zero question coverage: " . implode(', ', array_map('ucfirst', $uncoveredPeriods));
            }
            if ($shortfallCount > 0) {
                $generationWarnings[] = "Generation shortfall: Requested {$numQuestions} questions, but only {$finalGeneratedCount} unique valid items could be generated.";
            }
            if ($periodMismatch) {
                $generationWarnings[] = "Period distribution mismatch: Requested allocations not fully satisfied.";
            }
            if ($blueprintMismatch) {
                $generationWarnings[] = "Question blueprint mismatch: Requested question type allocations not fully satisfied.";
            }
            if ($difficultyMismatch) {
                $generationWarnings[] = "Difficulty distribution mismatch: Guidance levels differed from generated difficulty distribution.";
            }

            $executionTime = round((microtime(true) - $startTime) * 1000, 2);
            $failedChunkIndexes = array_values(array_unique(array_map('intval', $failedChunkIndexes)));
            $failedChunkCount = count($failedChunkIndexes);
            $firstFailedChunkIndex = !empty($failedChunkIndexes) ? $failedChunkIndexes[0] : null;

            $simulatedScenario = self::isTestModeActive() ? $executedSimulatedScenario : null;

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
                    'failed_chunk_indexes' => $failedChunkIndexes,
                    'first_failed_chunk_index' => $firstFailedChunkIndex,
                    'failed_chunk_index' => $firstFailedChunkIndex,
                    'failed_chunk_lesson_ids' => array_values(array_unique($affectedLessonIds)),
                    'failed_chunk_periods' => array_values(array_unique($affectedPeriods)),
                    'failed_chunks' => $failedChunksDetailed,
                    'requested_question_count' => $numQuestions,
                    'generated_question_count' => $finalGeneratedCount,
                    'failed_question_count' => max($failedChunkCount, $shortfallCount),
                    'shortfall_count' => $shortfallCount,
                    'affected_lesson_ids' => array_values(array_unique($affectedLessonIds)),
                    'affected_periods' => array_values(array_unique($affectedPeriods)),
                    'generation_warnings' => $generationWarnings,
                    'chunk_generation_results' => array_values($chunkGenerationResults),
                    'questions_per_lesson' => $questionsPerLesson,
                    'questions_per_period' => $questionsPerPeriod,
                    'uncovered_lesson_ids' => array_values(array_unique($uncoveredLessonIds)),
                    'uncovered_periods' => array_values(array_unique($uncoveredPeriods)),
                    'refill_attempt_count' => $refillAttemptCount,
                    'refill_generated_count' => $refillGeneratedCount ?? 0,
                    'refill_warnings' => $refillWarnings,
                    'simulated_scenario' => $simulatedScenario,
                    'simulated_test_scenario' => $simulatedScenario,
                    'refill_target_chunk_index' => $refillTargetChunkIndex ?? null,
                    'refill_target_lesson_ids' => $refillTargetLessonIds ?? [],
                    'refill_target_periods' => $refillTargetPeriods ?? [],
                    'initial_questions_per_lesson' => $initialQuestionsPerLesson ?? (object)[],
                    'initial_questions_per_period' => $initialQuestionsPerPeriod ?? (object)[],
                    'initial_uncovered_lesson_ids' => $initialUncoveredLessonIds ?? [],
                    'initial_uncovered_periods' => $initialUncoveredPeriods ?? [],
                    'final_questions_per_lesson' => $questionsPerLesson,
                    'final_questions_per_period' => $questionsPerPeriod,
                    'difficulty' => $difficulty,
                    'period_weighting_mode' => $periodWeightInfo['mode'],
                    'requested_period_distribution' => $periodWeightInfo['requested_distribution'],
                    'actual_period_distribution' => $questionsPerPeriod,
                    'period_target_counts' => $periodWeightInfo['target_counts'],
                    'requested_question_blueprint' => $blueprintInfo['requested_blueprint'],
                    'actual_question_distribution' => $actualQuestionDistribution,
                    'blueprint_target_counts' => $blueprintInfo['target_counts'],
                    'requested_difficulty_distribution' => $difficultyInfo['requested_distribution'],
                    'actual_difficulty_distribution' => $actualDifficultyDistribution,
                    'difficulty_target_counts' => $difficultyInfo['target_counts'],
                    'period_distribution_mismatch' => $periodMismatch,
                    'question_blueprint_mismatch' => $blueprintMismatch,
                    'difficulty_distribution_mismatch' => $difficultyMismatch,
                    'unresolved_differences' => $unresolvedDifferences,
                    'duplicate_count' => $duplicateCount,
                    'replacement_attempt_count' => $replacementAttemptCount,
                    'replacement_success_count' => $replacementSuccessCount,
                    'unresolved_duplicate_count' => $unresolvedDuplicateCount,
                    'duplicate_warnings' => $duplicateWarnings
                ]
            ];
        }

        // Direct single-pass generation
        $blueprintStr = json_encode($blueprintInfo['target_counts']);
        $difficultyStr = json_encode($difficultyInfo['target_counts']);

        $prompt = "You are an expert Civil Engineering professor specializing in {$specialization} and academic assessment creation. "
                . "Generate exactly {$numQuestions} high-quality Civil Engineering examination questions for the subject '{$subject}' (Specialization: {$specialization}) titled '{$examTitle}'. "
                . "Target Difficulty Distribution: '{$difficultyStr}'. "
                . "Target Question Type Blueprint: '{$blueprintStr}'. "
                . "based strictly on the following lesson content: \"{$lessonText}\". "
                . "Do NOT invent facts outside the lesson content. "
                . "Format response strictly as a JSON array of objects without markdown fences or code blocks. "
                . "Each object MUST have: \"question\" (string), \"type\" (string), "
                . "\"opt_a\" (string or null), \"opt_b\" (string or null), \"opt_c\" (string or null), \"opt_d\" (string or null), "
                . "\"correct_answer\" (string), \"difficulty\" (string: 'easy', 'medium', or 'hard'), \"formula_latex\" (string or null), \"matching_pairs\" (object or null), "
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
        $seenHashes = [];
        $seenAnswers = [];
        $seenQuestions = [];

        foreach ($cleanJson as $q) {
            if (!is_array($q)) continue;
            $qText = trim($q['question'] ?? '');
            $qCorrect = trim($q['correct_answer'] ?? '');
            $qType = trim($q['type'] ?? (is_string($questionType) ? $questionType : 'multiple_choice'));

            if (empty($qText) || empty($qCorrect)) {
                continue; 
            }

            $normText = self::normalizeQuestionText($qText);
            $qKey = $normText . '|' . self::normalizeQuestionText($qCorrect);
            $isDup = false;
            $dupReason = '';

            if (isset($seenHashes[$normText])) {
                $isDup = true;
                $dupReason = "Exact duplicate question text: \"{$qText}\"";
            } elseif (isset($seenAnswers[$qKey])) {
                $isDup = true;
                $dupReason = "Duplicate question and answer key combination: \"{$qText}\"";
            } else {
                foreach ($seenQuestions as $sq) {
                    $sqNorm = self::normalizeQuestionText($sq['question']);
                    similar_text($normText, $sqNorm, $pct);
                    if ($pct >= 85.0) {
                        $isDup = true;
                        $dupReason = "High text similarity (" . round($pct, 1) . "%) with: \"{$sq['question']}\"";
                        break;
                    }
                }
            }

            if ($isDup) {
                $duplicateCount++;
                $duplicateWarnings[] = $dupReason;
                $replacementAttemptCount++;
                continue; 
            }
            $seenHashes[$normText] = true;
            $seenAnswers[$qKey] = true;
            $seenQuestions[] = $q;

            $srcLessonIds = is_array($q['source_lesson_ids'] ?? null) ? array_map('intval', $q['source_lesson_ids']) : [];
            $retDiff = strtolower(trim($q['difficulty'] ?? ''));
            if (!in_array($retDiff, ['easy', 'medium', 'hard'], true)) {
                $retDiff = 'unclassified';
            }

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
                'difficulty' => $retDiff,
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

        $questionsPerLesson = array_fill_keys($allSelectedLessonIds, 0);
        $questionsPerPeriod = array_fill_keys($allSelectedPeriods, 0);
        $actualQuestionDistribution = [];
        $actualDifficultyDistribution = ['easy' => 0, 'medium' => 0, 'hard' => 0, 'unclassified' => 0];

        foreach ($validQuestions as $vq) {
            foreach ($vq['source_lesson_ids'] as $lId) {
                if (isset($questionsPerLesson[(int)$lId])) {
                    $questionsPerLesson[(int)$lId]++;
                }
            }
            $p = strtolower($vq['source_academic_period'] ?? '');
            if (isset($questionsPerPeriod[$p])) {
                $questionsPerPeriod[$p]++;
            } else {
                $questionsPerPeriod[$p] = 1;
            }

            $t = strtolower($vq['type'] ?? 'multiple_choice');
            if (!isset($actualQuestionDistribution[$t])) {
                $actualQuestionDistribution[$t] = 0;
            }
            $actualQuestionDistribution[$t]++;

            $d = strtolower($vq['difficulty'] ?? 'unclassified');
            if (isset($actualDifficultyDistribution[$d])) {
                $actualDifficultyDistribution[$d]++;
            } else {
                $actualDifficultyDistribution[$d] = 1;
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

        $periodMismatch = false;
        foreach ($periodWeightInfo['target_counts'] as $per => $targetCnt) {
            if (($questionsPerPeriod[$per] ?? 0) !== $targetCnt) {
                $periodMismatch = true;
                break;
            }
        }

        $blueprintMismatch = false;
        foreach ($blueprintInfo['target_counts'] as $type => $targetCnt) {
            if (($actualQuestionDistribution[$type] ?? 0) !== $targetCnt) {
                $blueprintMismatch = true;
                break;
            }
        }

        $difficultyMismatch = false;
        foreach ($difficultyInfo['target_counts'] as $diff => $targetCnt) {
            if (($actualDifficultyDistribution[$diff] ?? 0) !== $targetCnt) {
                $difficultyMismatch = true;
                break;
            }
        }

        $isCustomBlueprint = !empty($questionBlueprint) && array_sum(array_map('intval', (array)$questionBlueprint)) > 0;
        $isCustomPeriodWeighting = ($periodWeightingMode === 'percentage' || $periodWeightingMode === 'fixed');
        $isCustomDifficulty = ($difficultyMode === 'percentage' || $difficultyMode === 'fixed');

        $batchStatus = ($shortfallCount > 0 || !empty($uncoveredLessonIds) || !empty($uncoveredPeriods) || ($isCustomPeriodWeighting && $periodMismatch) || ($isCustomBlueprint && $blueprintMismatch) || ($isCustomDifficulty && $difficultyMismatch)) ? 'incomplete' : 'completed';

        $unresolvedDifferences = [
            'period' => [],
            'blueprint' => [],
            'difficulty' => []
        ];

        if ($periodMismatch) {
            foreach ($periodWeightInfo['target_counts'] as $per => $targetCnt) {
                $act = $questionsPerPeriod[$per] ?? 0;
                if ($act !== $targetCnt) {
                    $unresolvedDifferences['period'][$per] = ['requested' => $targetCnt, 'actual' => $act, 'diff' => $targetCnt - $act];
                }
            }
        }
        if ($blueprintMismatch) {
            foreach ($blueprintInfo['target_counts'] as $type => $targetCnt) {
                $act = $actualQuestionDistribution[$type] ?? 0;
                if ($act !== $targetCnt) {
                    $unresolvedDifferences['blueprint'][$type] = ['requested' => $targetCnt, 'actual' => $act, 'diff' => $targetCnt - $act];
                }
            }
        }
        if ($difficultyMismatch) {
            foreach ($difficultyInfo['target_counts'] as $diff => $targetCnt) {
                $act = $actualDifficultyDistribution[$diff] ?? 0;
                if ($act !== $targetCnt) {
                    $unresolvedDifferences['difficulty'][$diff] = ['requested' => $targetCnt, 'actual' => $act, 'diff' => $targetCnt - $act];
                }
            }
        }

        $singleChunkResult = [
            'chunk_id' => 0,
            'source_lesson_ids' => $allSelectedLessonIds,
            'academic_periods' => $allSelectedPeriods,
            'requested_question_allocation' => $numQuestions,
            'successfully_generated_count' => count($cleanJson),
            'invalid_question_count' => max(0, count($cleanJson) - count($seenHashes)),
            'duplicate_count' => $duplicateCount,
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
                'refill_generated_count' => 0,
                'refill_warnings' => [],
                'simulated_scenario' => null,
                'simulated_test_scenario' => null,
                'failed_chunk_index' => null,
                'refill_target_chunk_index' => null,
                'refill_target_lesson_ids' => [],
                'refill_target_periods' => [],
                'initial_questions_per_lesson' => $questionsPerLesson,
                'initial_questions_per_period' => $questionsPerPeriod,
                'initial_uncovered_lesson_ids' => array_values($uncoveredLessonIds),
                'initial_uncovered_periods' => array_values($uncoveredPeriods),
                'final_questions_per_lesson' => $questionsPerLesson,
                'final_questions_per_period' => $questionsPerPeriod,
                'affected_periods' => [],
                'difficulty' => $difficulty,
                'period_weighting_mode' => $periodWeightInfo['mode'],
                'requested_period_distribution' => $periodWeightInfo['requested_distribution'],
                'actual_period_distribution' => $questionsPerPeriod,
                'period_target_counts' => $periodWeightInfo['target_counts'],
                'requested_question_blueprint' => $blueprintInfo['requested_blueprint'],
                'actual_question_distribution' => $actualQuestionDistribution,
                'blueprint_target_counts' => $blueprintInfo['target_counts'],
                'requested_difficulty_distribution' => $difficultyInfo['requested_distribution'],
                'actual_difficulty_distribution' => $actualDifficultyDistribution,
                'difficulty_target_counts' => $difficultyInfo['target_counts'],
                'period_distribution_mismatch' => $periodMismatch,
                'question_blueprint_mismatch' => $blueprintMismatch,
                'difficulty_distribution_mismatch' => $difficultyMismatch,
                'unresolved_differences' => $unresolvedDifferences,
                'duplicate_count' => $duplicateCount,
                'replacement_attempt_count' => $replacementAttemptCount,
                'duplicate_warnings' => $duplicateWarnings
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
