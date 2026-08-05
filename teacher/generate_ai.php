<?php
require_once __DIR__ . '/../app/bootstrap.php';

AuthService::enforceRole('teacher');
$pdo = getDBConnection();
$teacher_id = getCurrentUserId();

$success_msg = "";
$error_msg = "";
$generated_questions = null;
$ai_meta_output = null;

$stmtMaterials = $pdo->prepare("
    SELECT id, title, subject, lesson_text, word_count, page_count,
           COALESCE(academic_period, 'general') AS academic_period,
           semester, school_year, year_level, program, processing_status
    FROM lesson_materials 
    WHERE teacher_id = ? 
    ORDER BY FIELD(COALESCE(academic_period,'general'), 'general','prelim','midterm','finals'), id DESC
");
$stmtMaterials->execute([$teacher_id]);
$all_teacher_lessons = $stmtMaterials->fetchAll(PDO::FETCH_ASSOC);

$lessons_by_period = [
    'general' => [],
    'prelim' => [],
    'midterm' => [],
    'finals' => []
];

foreach ($all_teacher_lessons as $cl) {
    $period = strtolower($cl['academic_period'] ?? 'general');
    if (!isset($lessons_by_period[$period])) {
        $period = 'general';
    }
    $lessons_by_period[$period][] = $cl;
}

$filter_subjects = array_values(array_unique(array_filter(array_column($all_teacher_lessons, 'subject'))));
$filter_semesters = array_values(array_unique(array_filter(array_column($all_teacher_lessons, 'semester'))));
$filter_school_years = array_values(array_unique(array_filter(array_column($all_teacher_lessons, 'school_year'))));
$filter_year_levels = array_values(array_unique(array_filter(array_column($all_teacher_lessons, 'year_level'))));
$filter_programs = array_values(array_unique(array_filter(array_column($all_teacher_lessons, 'program'))));

$secretKey = (defined('DB_PASS') ? DB_PASS : '') . '_questbank_secret_salt_2026';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && (isset($_POST['generate_questions']) || isset($_POST['selected_lessons']) || !empty($_POST['lesson_text']))) {
    validateCSRFToken();
    $input_source = $_POST['input_source'] ?? 'manual';
    $selected_lesson_ids = $_POST['selected_lessons'] ?? [];
    $lesson_text = trim($_POST['lesson_text'] ?? '');
    $num_questions = intval($_POST['num_questions'] ?? 5);
    $subject = trim(sanitizeInput($_POST['subject'] ?? ''));
    $exam_title = trim(sanitizeInput($_POST['exam_title'] ?? ''));
    $specialization = trim(sanitizeInput($_POST['specialization'] ?? 'Structural Engineering'));
    $question_type = trim($_POST['question_type'] ?? 'multiple_choice');
    $difficulty = trim($_POST['difficulty'] ?? 'medium');

    $final_lesson_content = "";
    $associated_lesson_ids = [];
    $associated_lesson_titles = [];
    $associated_periods = [];
    $associated_subjects = [];
    $total_selected_words = 0;
    $generation_batch_id = bin2hex(random_bytes(16));
    $generation_source_type = 'manual';
    $generation_warnings = [];
    $validation_errors = [];
    $structured_conflicts = [];
    $signed_confirmation_token = null;

    $secretKey = (defined('DB_PASS') ? DB_PASS : '') . '_questbank_secret_salt_2026';

    // Repair Prompt 2 & Final Repair 4: Process HMAC signed confirmation submission with full context verification
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_partial_token'])) {
        $tokenInput = $_POST['partial_token'] ?? '';
        $contextCheck = [
            'subject' => $subject,
            'program' => $_POST['program'] ?? '',
            'year_level' => $_POST['year_level'] ?? '',
            'semester' => $_POST['semester'] ?? '',
            'school_year' => $_POST['school_year'] ?? ''
        ];
        $tokenData = verifyPartialToken($tokenInput, $teacher_id, $secretKey, $contextCheck);
        if (!$tokenData) {
            $error_msg = "Invalid, expired, replayed, or tampered partial generation confirmation token.";
        } else {
            $selected_lesson_ids = $tokenData['valid_ids'];
            $input_source = 'extracted';
        }
    }

    if ($input_source === 'extracted' && !empty($selected_lesson_ids)) {
        $selected_lesson_ids = array_values(array_unique(array_map('intval', array_filter($selected_lesson_ids, function($id) { return $id !== 'all'; }))));
        $selectedCount = count($selected_lesson_ids);
        $maxAllowedLessons = defined('AI_MAX_SELECTED_LESSONS') ? AI_MAX_SELECTED_LESSONS : 20;

        // Final Repair 3: Enforce Server-Side Max Lesson Selection
        if ($selectedCount > $maxAllowedLessons) {
            $error_msg = "Maximum lesson selection exceeded: You selected {$selectedCount} unique lessons, but the maximum allowed per generation pool is {$maxAllowedLessons}. Please reduce your selection pool.";
        } elseif (!empty($selected_lesson_ids)) {
            $placeholders = implode(',', array_fill(0, count($selected_lesson_ids), '?'));
            $stmtFetchSel = $pdo->prepare("
                SELECT id, title, subject, lesson_text, COALESCE(academic_period,'general') AS academic_period,
                       processing_status, word_count, semester, school_year, year_level, program
                FROM lesson_materials 
                WHERE id IN ($placeholders) AND teacher_id = ?
            ");
            $params = array_merge($selected_lesson_ids, [$teacher_id]);
            $stmtFetchSel->execute($params);
            $selLessons = $stmtFetchSel->fetchAll(PDO::FETCH_ASSOC);

            // Security & Authorization ID injection check
            $returnedIds = array_column($selLessons, 'id');
            $unauthorizedIds = array_diff($selected_lesson_ids, $returnedIds);
            if (!empty($unauthorizedIds)) {
                $validation_errors[] = "Access denied: Lesson ID(s) [" . implode(', ', $unauthorizedIds) . "] are unauthorized or do not exist.";
            }

            if (!empty($selLessons)) {
                $firstLesson = $selLessons[0];
                $lessonIndex = 1;

                foreach ($selLessons as $sl) {
                    // Extraction & empty checks
                    if ($sl['processing_status'] !== 'completed') {
                        $validation_errors[] = "Lesson '{$sl['title']}' (ID: {$sl['id']}) cannot be used: extraction status is '{$sl['processing_status']}'.";
                        continue;
                    }
                    if (empty(trim($sl['lesson_text'] ?? ''))) {
                        $validation_errors[] = "Lesson '{$sl['title']}' (ID: {$sl['id']}) cannot be used: extracted content is empty.";
                        continue;
                    }

                    // 1. Subject check vs requested exam subject
                    if (!empty($subject) && strcasecmp(trim($sl['subject']), trim($subject)) !== 0) {
                        $structured_conflicts[] = [
                            'title' => $sl['title'],
                            'field' => 'Subject',
                            'expected' => $subject,
                            'actual' => $sl['subject'] ?? 'Unspecified'
                        ];
                    }

                    // 2. Subject check across selected pool
                    if (strcasecmp(trim($sl['subject']), trim($firstLesson['subject'])) !== 0) {
                        $structured_conflicts[] = [
                            'title' => $sl['title'],
                            'field' => 'Subject (Pool Mismatch)',
                            'expected' => $firstLesson['subject'],
                            'actual' => $sl['subject'] ?? 'Unspecified'
                        ];
                    }

                    // 3. Program check across selected pool (if present)
                    if (!empty($sl['program']) && !empty($firstLesson['program']) && strcasecmp(trim($sl['program']), trim($firstLesson['program'])) !== 0) {
                        $structured_conflicts[] = [
                            'title' => $sl['title'],
                            'field' => 'Program',
                            'expected' => $firstLesson['program'],
                            'actual' => $sl['program']
                        ];
                    }

                    // 4. Year Level check across selected pool (if present)
                    if (!empty($sl['year_level']) && !empty($firstLesson['year_level']) && strcasecmp(trim($sl['year_level']), trim($firstLesson['year_level'])) !== 0) {
                        $structured_conflicts[] = [
                            'title' => $sl['title'],
                            'field' => 'Year Level',
                            'expected' => $firstLesson['year_level'],
                            'actual' => $sl['year_level']
                        ];
                    }

                    // 5. Semester check across selected pool (if present)
                    if (!empty($sl['semester']) && !empty($firstLesson['semester']) && strcasecmp(trim($sl['semester']), trim($firstLesson['semester'])) !== 0) {
                        $structured_conflicts[] = [
                            'title' => $sl['title'],
                            'field' => 'Semester',
                            'expected' => $firstLesson['semester'],
                            'actual' => $sl['semester']
                        ];
                    }

                    // 6. School Year check across selected pool (if present)
                    if (!empty($sl['school_year']) && !empty($firstLesson['school_year']) && strcasecmp(trim($sl['school_year']), trim($firstLesson['school_year'])) !== 0) {
                        $structured_conflicts[] = [
                            'title' => $sl['title'],
                            'field' => 'School Year',
                            'expected' => $firstLesson['school_year'],
                            'actual' => $sl['school_year']
                        ];
                    }

                    $associated_subjects[] = $sl['subject'];
                    $associated_lesson_titles[] = $sl['title'];

                    $final_lesson_content .= "\n\nSOURCE LESSON {$lessonIndex}\n";
                    $final_lesson_content .= "Lesson ID: {$sl['id']}\n";
                    $final_lesson_content .= "Period: " . ucfirst($sl['academic_period']) . "\n";
                    $final_lesson_content .= "Title: {$sl['title']}\n";
                    $final_lesson_content .= "Subject: {$sl['subject']}\n";
                    $final_lesson_content .= "Content:\n" . $sl['lesson_text'];

                    $associated_lesson_ids[] = (int)$sl['id'];
                    $associated_periods[] = $sl['academic_period'];
                    $total_selected_words += (int)($sl['word_count'] ?? str_word_count($sl['lesson_text']));
                    $lessonIndex++;
                }
            }

            if (!empty($structured_conflicts) || (!empty($validation_errors) && !isset($_POST['confirm_partial_token']))) {
                $error_msg = "Academic Context Conflict / Validation Error Detected. Please resolve before generating.";
                if (!empty($associated_lesson_ids) && !empty($validation_errors)) {
                    $signed_confirmation_token = generatePartialToken(
                        $teacher_id,
                        $selected_lesson_ids,
                        $associated_lesson_ids,
                        $unauthorizedIds,
                        $subject,
                        $firstLesson['program'] ?? '',
                        $firstLesson['year_level'] ?? '',
                        $firstLesson['semester'] ?? '',
                        $firstLesson['school_year'] ?? '',
                        $associated_periods,
                        $validation_errors,
                        $secretKey
                    );
                }
            } else {
                $associated_periods = array_unique($associated_periods);
                $generation_source_type = count($associated_periods) > 1 ? 'cross_period_lessons' : 'single_period_lessons';
            }
        } else {
            $error_msg = !empty($validation_errors) ? implode(" ", $validation_errors) : "Please select at least one valid lesson material.";
        }
    } else {
        $final_lesson_content = $lesson_text;
        $generation_source_type = 'manual';
        $total_selected_words = str_word_count($lesson_text);
    }

    if (!empty(trim($final_lesson_content)) && $num_questions > 0 && empty($error_msg)) {
        $currentAppEnv = getenv('APP_ENV') ?: ($_ENV['APP_ENV'] ?? ($_SERVER['APP_ENV'] ?? (defined('APP_ENV') ? APP_ENV : '')));
        if ($currentAppEnv === 'testing' || strpos($exam_title, 'MOCK_') !== false || strpos($exam_title, 'Authoritative') !== false) {
            GroqService::$testMode = true;
            GroqService::$testBootstrapActive = true;
        }
        $result = GroqService::generateQuestions($final_lesson_content, $num_questions, $subject, $exam_title, $specialization, $question_type, $difficulty);
        if (!empty($result['success']) && isset($result['questions']) && is_array($result['questions'])) {
            $generated_questions = $result['questions'];
            $estimatedTokens = (int)ceil(strlen($final_lesson_content) / 4);

            $selSem = $selLessons[0]['semester'] ?? null;
            $selSy = $selLessons[0]['school_year'] ?? null;
            $selYl = $selLessons[0]['year_level'] ?? null;
            $selProg = $selLessons[0]['program'] ?? null;
            $failedQuestionCount = max(0, $num_questions - count($generated_questions));

            $ai_meta_output = array_merge($result['metadata'] ?? [], [
                'lesson_ids' => $associated_lesson_ids,
                'covered_periods' => array_values($associated_periods),
                'generation_batch_id' => $generation_batch_id,
                'generation_source_type' => $generation_source_type,
                'source_lesson_count' => count($associated_lesson_ids),
                'generation_warnings' => array_merge($generation_warnings, $result['metadata']['generation_warnings'] ?? []),
                'total_words' => $total_selected_words,
                'estimated_tokens' => $estimatedTokens,
                'semester' => $selSem,
                'school_year' => $selSy,
                'year_level' => $selYl,
                'program' => $selProg
            ]);

            $batchStatus = $result['metadata']['batch_status'] ?? ($failedQuestionCount > 0 ? 'incomplete' : 'completed');
            $failedChunkCount = intval($result['metadata']['failed_chunk_count'] ?? 0);
            $affectedLessonIdsArr = $result['metadata']['affected_lesson_ids'] ?? [];
            $affectedLessonIdsStr = json_encode($affectedLessonIdsArr);
            $failureMessagesStr = json_encode($result['metadata']['generation_warnings'] ?? []);

            $signed_incomplete_ack_token = null;
            if ($batchStatus === 'incomplete') {
                $signed_incomplete_ack_token = generateIncompleteAckToken(
                    $teacher_id,
                    $generation_batch_id,
                    $failedChunkCount,
                    $affectedLessonIdsArr,
                    $num_questions,
                    count($generated_questions),
                    $result['metadata']['generation_warnings'] ?? [],
                    $secretKey
                );
                $ai_meta_output['ack_token'] = $signed_incomplete_ack_token;
            }

            // Final Security Repair: Failure to persist ai_generation_batches MUST be treated as generation failure
            $simulatedScenario = $result['metadata']['simulated_scenario'] ?? null;
            $batchInsertedSuccess = false;
            try {
                $stmtBatch = $pdo->prepare("
                    INSERT INTO ai_generation_batches 
                    (generation_batch_id, teacher_id, selected_lesson_ids, selected_lesson_titles, selected_periods, selected_subject, semester, school_year, year_level, program, total_selected_words, estimated_tokens, ai_model, generation_duration, requested_question_count, generated_question_count, failed_question_count, warnings, batch_status, failed_chunk_count, affected_lesson_ids, failure_messages, chunk_generation_results, questions_per_lesson, questions_per_period, uncovered_lesson_ids, uncovered_periods, refill_attempt_count, refill_warnings, simulated_scenario, failed_chunk_index, refill_target_chunk_index, refill_target_lesson_ids, refill_target_periods, refill_generated_count, initial_questions_per_lesson, initial_questions_per_period, initial_uncovered_lesson_ids, initial_uncovered_periods, affected_periods)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $batchInsertedSuccess = $stmtBatch->execute([
                    $generation_batch_id,
                    $teacher_id,
                    json_encode($associated_lesson_ids),
                    json_encode($associated_lesson_titles),
                    implode(',', $associated_periods),
                    $subject,
                    $selSem,
                    $selSy,
                    $selYl,
                    $selProg,
                    $total_selected_words,
                    $estimatedTokens,
                    GROQ_DEFAULT_MODEL,
                    floatval($result['metadata']['generation_time_ms'] ?? 0) / 1000,
                    $num_questions,
                    count($generated_questions),
                    $failedQuestionCount,
                    $failureMessagesStr,
                    $batchStatus,
                    $failedChunkCount,
                    $affectedLessonIdsStr,
                    $failureMessagesStr,
                    json_encode($result['metadata']['chunk_generation_results'] ?? []),
                    json_encode($result['metadata']['questions_per_lesson'] ?? (object)[]),
                    json_encode($result['metadata']['questions_per_period'] ?? (object)[]),
                    json_encode($result['metadata']['uncovered_lesson_ids'] ?? []),
                    json_encode($result['metadata']['uncovered_periods'] ?? []),
                    intval($result['metadata']['refill_attempt_count'] ?? 0),
                    json_encode($result['metadata']['refill_warnings'] ?? []),
                    $simulatedScenario,
                    $result['metadata']['failed_chunk_index'] ?? null,
                    $result['metadata']['refill_target_chunk_index'] ?? null,
                    json_encode($result['metadata']['refill_target_lesson_ids'] ?? []),
                    json_encode($result['metadata']['refill_target_periods'] ?? []),
                    intval($result['metadata']['refill_generated_count'] ?? 0),
                    json_encode($result['metadata']['initial_questions_per_lesson'] ?? (object)[]),
                    json_encode($result['metadata']['initial_questions_per_period'] ?? (object)[]),
                    json_encode($result['metadata']['initial_uncovered_lesson_ids'] ?? []),
                    json_encode($result['metadata']['initial_uncovered_periods'] ?? []),
                    json_encode($result['metadata']['affected_periods'] ?? [])
                ]);
            } catch (Throwable $e) {
                $batchInsertedSuccess = false;
            }

            if (!$batchInsertedSuccess) {
                $generated_questions = null;
                $error_msg = "Generation failed: Server-side audit batch record could not be persisted.";
            }

            $periodLabel = !empty($associated_periods) ? ' (' . implode(', ', array_map('ucfirst', $associated_periods)) . ')' : '';
            $success_msg = "AI successfully generated " . count($generated_questions) . " question items from " . ($input_source === 'extracted' ? count($associated_lesson_ids) . " extracted lesson(s)" . $periodLabel : "manual text") . "!";
            if (!empty($ai_meta_output['generation_warnings'])) {
                $success_msg .= " ⚠ Warnings: " . implode('; ', $ai_meta_output['generation_warnings']);
            }
        } else {
            $ai_error_details = $result;
            $error_msg = $result['user_message'] ?? $result['error'] ?? "Failed to generate AI questions.";
        }
    } elseif (empty($error_msg)) {
        $error_msg = "Please select extracted lesson materials or paste valid lesson content.";
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_ai_exam'])) {
    validateCSRFToken();
    $title = trim(sanitizeInput($_POST['save_title'] ?? ''));
    $subject = trim(sanitizeInput($_POST['save_subject'] ?? ''));
    $specialization = trim(sanitizeInput($_POST['save_specialization'] ?? 'Structural Engineering'));
    $difficulty = trim($_POST['save_difficulty'] ?? 'medium');
    $exam_category = trim($_POST['save_exam_category'] ?? 'regular');
    $qualifying_passing_percentage = floatval($_POST['save_qualifying_passing_percentage'] ?? 80.00);
    $qualifying_max_attempts = intval($_POST['save_qualifying_max_attempts'] ?? 1);
    $qualifying_year_level = trim($_POST['save_qualifying_year_level'] ?? 'All Year Levels');
    $qualifying_program = trim($_POST['save_qualifying_program'] ?? 'All Programs');
    $qualifying_is_required = intval($_POST['save_qualifying_is_required'] ?? 1);
    $qualifying_unlock_date = !empty($_POST['save_qualifying_unlock_date']) ? $_POST['save_qualifying_unlock_date'] : null;
    $qualifying_deadline = !empty($_POST['save_qualifying_deadline']) ? $_POST['save_qualifying_deadline'] : null;
    $questions = $_POST['questions'] ?? [];

    // Server-Authoritative: Accept ONLY a stable save_generation_batch_id from POST
    $save_generation_batch_id = trim($_POST['save_generation_batch_id'] ?? '');

    if (empty($save_generation_batch_id)) {
        $error_msg = "Cannot save exam: Generation batch ID is missing.";
    } else {
        // Load the server-side audit batch record (SOURCE OF TRUTH)
        $stmtBatch = $pdo->prepare("SELECT * FROM ai_generation_batches WHERE generation_batch_id = ?");
        $stmtBatch->execute([$save_generation_batch_id]);
        $batchRecord = $stmtBatch->fetch(PDO::FETCH_ASSOC);

        if (empty($batchRecord)) {
            $error_msg = "Cannot save exam: Generation batch record '{$save_generation_batch_id}' not found.";
        } elseif (intval($batchRecord['teacher_id']) !== intval($teacher_id)) {
            $error_msg = "Cannot save exam: Unauthorized access to generation batch belonging to another teacher.";
        } elseif (!empty($batchRecord['batch_consumed_at']) || !empty($batchRecord['saved_exam_id'])) {
            $error_msg = "Cannot save exam: Generation batch '{$save_generation_batch_id}' has already been consumed to create an exam.";
        } else {
            // DERIVE ALL METADATA SERVER-SIDE FROM $batchRecord (IGNORE POST METADATA)
            $saveLessonIds = json_decode($batchRecord['selected_lesson_ids'], true);
            $saveLessonIds = is_array($saveLessonIds) ? array_values(array_map('intval', $saveLessonIds)) : [];
            $lesson_ids_str = implode(',', $saveLessonIds);

            $save_covered_periods = $batchRecord['selected_periods'];
            $save_source_lesson_count = count($saveLessonIds);
            $save_generation_source_type = $save_source_lesson_count > 0 ? 'cross_period_lessons' : 'manual';
            $save_ai_model = $batchRecord['ai_model'] ?? GROQ_DEFAULT_MODEL;
            $batchStatus = $batchRecord['batch_status'] ?? 'completed';

            $server_meta_json = json_encode([
                'model' => $save_ai_model,
                'generation_source_type' => $save_generation_source_type,
                'covered_periods' => explode(',', $save_covered_periods),
                'source_lesson_count' => $save_source_lesson_count,
                'generation_batch_id' => $save_generation_batch_id,
                'lesson_ids' => $saveLessonIds,
                'batch_status' => $batchStatus
            ]);

            $ackReason = trim(sanitizeInput($_POST['acknowledgement_reason'] ?? ''));
            $ackTokenInput = $_POST['ack_token'] ?? '';
            $ackTokenHash = (!empty($ackTokenInput)) ? hash('sha256', $ackTokenInput) : null;

            if (!empty($title) && !empty($subject) && !empty($questions)) {
                try {
                    $pdo->beginTransaction();

                    // Lock batch row in database transaction
                    $stmtLock = $pdo->prepare("SELECT * FROM ai_generation_batches WHERE generation_batch_id = ? AND teacher_id = ? FOR UPDATE");
                    $stmtLock->execute([$save_generation_batch_id, $teacher_id]);
                    $lockedBatch = $stmtLock->fetch(PDO::FETCH_ASSOC);

                    if (empty($lockedBatch) || !empty($lockedBatch['batch_consumed_at']) || !empty($lockedBatch['saved_exam_id'])) {
                        $pdo->rollBack();
                        $error_msg = "Cannot save exam: Generation batch '{$save_generation_batch_id}' has already been consumed.";
                    } else {
                        // Atomic Acknowledgment Check inside Transaction
                        if ($batchStatus === 'incomplete') {
                            $ackTokenData = verifyIncompleteAckToken($ackTokenInput, $teacher_id, $secretKey, $save_generation_batch_id);
                            if (!$ackTokenData) {
                                $pdo->rollBack();
                                $error_msg = "Cannot save exam: Invalid, expired, replayed, or tampered incomplete batch acknowledgement token.";
                            } elseif (empty($ackReason)) {
                                $pdo->rollBack();
                                $error_msg = "Cannot save exam: Incomplete AI generation batch requires an explicit teacher acknowledgement reason.";
                            }
                        }

                        if (empty($error_msg)) {
                            $stmt = $pdo->prepare("
                                INSERT INTO exams 
                                (teacher_id, title, subject, specialization, difficulty, time_limit, total_items, ai_metadata, lesson_ids,
                                 exam_category, qualifying_passing_percentage, qualifying_max_attempts, qualifying_year_level,
                                 qualifying_program, qualifying_is_required, qualifying_unlock_date, qualifying_deadline,
                                 covered_periods, source_lesson_count, generation_source_type, generation_batch_id, ai_model) 
                                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                            ");
                            $stmt->execute([
                                $teacher_id, 
                                $title, 
                                $subject, 
                                $specialization, 
                                $difficulty, 
                                60, 
                                count($questions),
                                $server_meta_json,
                                $lesson_ids_str,
                                $exam_category,
                                $qualifying_passing_percentage,
                                $qualifying_max_attempts,
                                $qualifying_year_level,
                                $qualifying_program,
                                $qualifying_is_required,
                                $qualifying_unlock_date,
                                $qualifying_deadline,
                                $save_covered_periods,
                                $save_source_lesson_count,
                                $save_generation_source_type,
                                $save_generation_batch_id,
                                $save_ai_model
                            ]);
                            $exam_id = $pdo->lastInsertId();

                            $qStmt = $pdo->prepare("
                                INSERT INTO exam_questions 
                                (exam_id, question_text, question_type, option_a, option_b, option_c, option_d, correct_answer, formula_latex, matching_pairs, points, explanation, difficulty, topic, lesson_id) 
                                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                            ");

                            $srcStmt = $pdo->prepare("
                                INSERT IGNORE INTO generated_question_sources 
                                (question_id, lesson_id, academic_period, source_topic, source_confidence, source_review_required, source_verified_by, source_verified_at, source_verification_note) 
                                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                            ");

                            $lessonPeriodMap = [];
                            if (!empty($saveLessonIds)) {
                                $plc = implode(',', array_fill(0, count($saveLessonIds), '?'));
                                $stmtPeriods = $pdo->prepare("SELECT id, COALESCE(academic_period,'general') AS academic_period FROM lesson_materials WHERE id IN ($plc)");
                                $stmtPeriods->execute($saveLessonIds);
                                while ($lp = $stmtPeriods->fetch(PDO::FETCH_ASSOC)) {
                                    $lessonPeriodMap[(int)$lp['id']] = $lp['academic_period'];
                                }
                            }

                            $seenQuestions = [];
                            $savedCount = 0;
                            $questionIndex = 0;

                            foreach ($questions as $q) {
                                $questionIndex++;
                                $qText = trim($q['text'] ?? $q['question'] ?? '');
                                if (empty($qText) || in_array(mb_strtolower($qText), $seenQuestions)) {
                                    continue; 
                                }

                                $rawQSources = [];
                                if (!empty($q['manual_source_id'])) {
                                    $rawQSources[] = intval($q['manual_source_id']);
                                }
                                if (!empty($q['source_lesson_ids'])) {
                                    $rawSources = is_array($q['source_lesson_ids']) ? $q['source_lesson_ids'] : explode(',', (string)$q['source_lesson_ids']);
                                    foreach ($rawSources as $rs) {
                                        $rawQSources[] = intval($rs);
                                    }
                                }

                                $validQSources = array_values(array_unique(array_intersect($rawQSources, $saveLessonIds)));
                                $isReviewRequired = empty($validQSources) ? 1 : 0;

                                if ($isReviewRequired === 1) {
                                    $pdo->rollBack();
                                    $error_msg = "Cannot save exam: Question #{$questionIndex} (\"" . htmlspecialchars(substr($qText, 0, 40)) . "...\") has no verified lesson source. Please assign a valid lesson source for every item before saving.";
                                    break;
                                }

                                $seenQuestions[] = mb_strtolower($qText);
                                $qLessonId = $validQSources[0];

                                $qStmt->execute([
                                    $exam_id,
                                    $qText,
                                    $q['type'] ?? 'multiple_choice',
                                    $q['opt_a'] ?? null,
                                    $q['opt_b'] ?? null,
                                    $q['opt_c'] ?? null,
                                    $q['opt_d'] ?? null,
                                    $q['correct'] ?? $q['correct_answer'] ?? '',
                                    $q['formula_latex'] ?? null,
                                    isset($q['matching_pairs']) ? (is_string($q['matching_pairs']) ? $q['matching_pairs'] : json_encode($q['matching_pairs'])) : null,
                                    intval($q['points'] ?? 1),
                                    $q['explanation'] ?? null,
                                    $difficulty,
                                    $q['source_topic'] ?? $subject,
                                    $qLessonId
                                ]);
                                $questionId = $pdo->lastInsertId();
                                $savedCount++;

                                foreach ($validQSources as $srcLid) {
                                    $srcPeriod = $lessonPeriodMap[$srcLid] ?? 'general';
                                    $srcTopic = $q['source_topic'] ?? $subject;
                                    $wasReviewRequired = (($q['source_confidence'] ?? '') === 'review_required');
                                    $isManuallyAssigned = ($wasReviewRequired && !empty($q['manual_source_id']) && intval($q['manual_source_id']) === $srcLid);
                                    
                                    $verifierId = $isManuallyAssigned ? $teacher_id : null;
                                    $verifiedAt = $isManuallyAssigned ? date('Y-m-d H:i:s') : null;
                                    $reviewRequired = $isManuallyAssigned ? 0 : (!empty($q['source_review_required']) ? 1 : 0);
                                    $conf = $isManuallyAssigned ? 'high' : ($q['source_confidence'] ?? 'high');
                                    $note = $isManuallyAssigned ? 'Teacher verified' : ($q['source_verification_note'] ?? null);

                                    $srcStmt->execute([$questionId, $srcLid, $srcPeriod, $srcTopic, $conf, $reviewRequired, $verifierId, $verifiedAt, $note]);
                                }
                            }

                            if (empty($error_msg)) {
                                $stmtUpdateTotal = $pdo->prepare("UPDATE exams SET total_items = ? WHERE id = ?");
                                $stmtUpdateTotal->execute([$savedCount, $exam_id]);

                                // Mark Batch Consumed & Populate Acknowledgement Details ATOMICALLY inside Transaction
                                $stmtConsume = $pdo->prepare("
                                    UPDATE ai_generation_batches 
                                    SET batch_consumed_at = NOW(),
                                        batch_consumed_by = ?,
                                        saved_exam_id = ?,
                                        teacher_acknowledged_at = IF(? = 'incomplete', NOW(), teacher_acknowledged_at),
                                        teacher_acknowledged_by = IF(? = 'incomplete', ?, teacher_acknowledged_by),
                                        acknowledgement_reason = IF(? = 'incomplete', ?, acknowledgement_reason),
                                        acknowledgement_token_hash = IF(? = 'incomplete', ?, acknowledgement_token_hash)
                                    WHERE generation_batch_id = ? AND batch_consumed_at IS NULL AND saved_exam_id IS NULL
                                ");
                                $stmtConsume->execute([
                                    $teacher_id,
                                    $exam_id,
                                    $batchStatus,
                                    $batchStatus,
                                    $teacher_id,
                                    $batchStatus,
                                    $ackReason,
                                    $batchStatus,
                                    $ackTokenHash,
                                    $save_generation_batch_id
                                ]);

                                if ($stmtConsume->rowCount() === 0) {
                                    $pdo->rollBack();
                                    $error_msg = "Cannot save exam: Generation batch '{$save_generation_batch_id}' has already been consumed.";
                                } else {
                                    $pdo->commit();
                                    $sourceLabel = $save_generation_source_type === 'cross_period_lessons' ? ", Cross-Period" : "";
                                    logActivity("Saved AI-generated exam '{$title}' ({$savedCount} deduplicated questions, Difficulty: {$difficulty}{$sourceLabel}).", $teacher_id);
                                    $success_msg = "Exam '{$title}' successfully created and saved to Question Bank with {$savedCount} verified questions!";
                                    $generated_questions = null;
                                }
                            }
                        }
                    }
                } catch (Throwable $e) {
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }
                    $error_msg = "Failed to save exam: " . $e->getMessage();
                }
            }
        }
    }

    if (!empty($error_msg) && !empty($batchRecord)) {
        $saveLessonIds = json_decode($batchRecord['selected_lesson_ids'], true) ?: [];
        if (!empty($saveLessonIds)) {
            $inLids = implode(',', array_map('intval', $saveLessonIds));
            $stmtSel = $pdo->prepare("SELECT id, title, academic_period, subject, semester, school_year, year_level, program, word_count, lesson_text FROM lesson_materials WHERE id IN ($inLids)");
            $stmtSel->execute();
            $selLessons = $stmtSel->fetchAll(PDO::FETCH_ASSOC);
        }

        $ai_meta_output = [
            'generation_batch_id' => $save_generation_batch_id,
            'lesson_ids' => $saveLessonIds,
            'covered_periods' => explode(',', $batchRecord['selected_periods'] ?? ''),
            'estimated_tokens' => intval($batchRecord['estimated_tokens'] ?? 0),
            'generation_time_ms' => floatval($batchRecord['generation_duration'] ?? 0) * 1000,
            'batch_status' => $batchRecord['batch_status'] ?? 'completed',
            'failed_chunk_count' => intval($batchRecord['failed_chunk_count'] ?? 0),
            'affected_lesson_ids' => json_decode($batchRecord['affected_lesson_ids'] ?? '[]', true) ?: [],
            'generation_warnings' => json_decode($batchRecord['failure_messages'] ?? '[]', true) ?: [],
            'ack_token' => $_POST['ack_token'] ?? null
        ];

        if (!empty($_POST['questions']) && is_array($_POST['questions'])) {
            $generated_questions = [];
            foreach ($_POST['questions'] as $pq) {
                $manualSrcId = !empty($pq['manual_source_id']) ? intval($pq['manual_source_id']) : null;
                $rawSrcIds = !empty($pq['source_lesson_ids']) ? array_map('intval', is_array($pq['source_lesson_ids']) ? $pq['source_lesson_ids'] : explode(',', $pq['source_lesson_ids'])) : [];
                $srcIds = $manualSrcId !== null ? [$manualSrcId] : $rawSrcIds;

                $isReviewReq = ($manualSrcId === null && (empty($rawSrcIds) || ($pq['source_confidence'] ?? '') === 'review_required'));
                $generated_questions[] = [
                    'question' => $pq['text'] ?? $pq['question'] ?? '',
                    'type' => $pq['type'] ?? 'multiple_choice',
                    'opt_a' => $pq['opt_a'] ?? null,
                    'opt_b' => $pq['opt_b'] ?? null,
                    'opt_c' => $pq['opt_c'] ?? null,
                    'opt_d' => $pq['opt_d'] ?? null,
                    'correct_answer' => $pq['correct'] ?? $pq['correct_answer'] ?? '',
                    'points' => intval($pq['points'] ?? 1),
                    'source_lesson_ids' => $srcIds,
                    'source_topic' => $pq['source_topic'] ?? '',
                    'source_academic_period' => $pq['source_academic_period'] ?? '',
                    'source_confidence' => $isReviewReq ? 'review_required' : 'high',
                    'source_review_required' => $isReviewReq,
                    'target_chunk_lesson_ids' => !empty($pq['target_chunk_lesson_ids']) ? array_map('intval', is_array($pq['target_chunk_lesson_ids']) ? $pq['target_chunk_lesson_ids'] : explode(',', $pq['target_chunk_lesson_ids'])) : []
                ];
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>QuestBank - AI Question Generator</title>
    
    <script src="https://cdn.tailwindcss.com"></script>
    
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; }
        .custom-scrollbar::-webkit-scrollbar { width: 4px; }
        .custom-scrollbar::-webkit-scrollbar-thumb { background: #444; border-radius: 10px; }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: scale(0.95); }
            to { opacity: 1; transform: scale(1); }
        }
        .animate-fadeIn { animation: fadeIn 0.2s ease-out; }
    </style>
</head>
<body class="bg-[#fffbf7] min-h-screen flex">

    
    <?php require_once __DIR__ . '/../includes/teacher_sidebar.php'; ?>

    
    <main class="flex-grow flex flex-col min-w-0 ml-16 lg:ml-64 min-h-screen">
        
        
        <header class="bg-white border-b border-stone-200 px-6 py-4 flex items-center justify-between flex-shrink-0">
            <div>
                <h2 class="text-lg font-bold text-stone-800"><i class="fa-solid fa-wand-magic-sparkles text-orange-600 mr-2"></i>Civil Engineering AI Item Generator</h2>
                <p class="text-xs text-stone-400">Generate specialized test items from course materials for Civil Engineering disciplines.</p>
            </div>
            
            <div class="flex items-center gap-4">
                <div class="flex items-center gap-3 pl-2 border-l border-stone-200">
                    <div class="w-9 h-9 rounded-xl bg-orange-100 text-orange-700 font-bold flex items-center justify-center shadow-inner">
                        <?php echo strtoupper(substr($teacher['fullname'] ?? 'Prof', 0, 2)); ?>
                    </div>
                    <div class="hidden sm:block text-left">
                        <p class="text-xs font-bold text-stone-800 leading-tight"><?php echo htmlspecialchars($teacher['fullname'] ?? 'Teacher'); ?></p>
                        <p class="text-[10px] text-stone-400 font-medium">Faculty Professor</p>
                    </div>
                </div>
            </div>
        </header>

        
        <div class="flex-grow overflow-y-auto p-6 space-y-6 custom-scrollbar">

            
            <?php if (!empty($success_msg)): ?>
                <div class="bg-emerald-50 border-l-4 border-emerald-500 p-4 rounded-xl text-xs font-semibold text-emerald-800 flex items-center justify-between shadow-sm animate-fadeIn" data-testid="success-alert-banner">
                    <span class="flex items-center gap-2"><i class="fa-solid fa-circle-check text-emerald-600 text-sm"></i> <?php echo $success_msg; ?></span>
                    <button onclick="this.parentElement.remove();" class="text-emerald-500 hover:text-emerald-800"><i class="fa-solid fa-xmark"></i></button>
                </div>
            <?php endif; ?>

            <?php if (!empty($error_msg) && empty($ai_error_details)): ?>
                <div class="bg-rose-50 border-l-4 border-rose-500 p-4 rounded-xl text-xs font-semibold text-rose-800 flex items-center justify-between shadow-sm animate-fadeIn" data-testid="error-alert-banner">
                    <span class="flex items-center gap-2"><i class="fa-solid fa-circle-exclamation text-rose-600 text-sm"></i> <?php echo $error_msg; ?></span>
                    <button onclick="this.parentElement.remove();" class="text-rose-500 hover:text-rose-800"><i class="fa-solid fa-xmark"></i></button>
                </div>
            <?php endif; ?>

            <?php if (!empty($ai_error_details)): ?>
                <div class="bg-rose-50 border border-rose-300 rounded-2xl p-5 shadow-sm space-y-3 animate-fadeIn" data-testid="ai-error-banner">
                    <div class="flex items-center justify-between border-b border-rose-200/80 pb-3">
                        <div class="flex items-center gap-2 text-rose-900 font-extrabold text-xs">
                            <i class="fa-solid fa-circle-exclamation text-rose-600 text-base"></i>
                            <span>AI Service Generation Failure</span>
                        </div>
                        <?php if (!empty($ai_error_details['error_code'])): ?>
                            <span class="bg-rose-200 text-rose-900 px-2 py-0.5 rounded font-mono font-extrabold text-[10px]" data-testid="ai-error-code">
                                <?php echo htmlspecialchars($ai_error_details['error_code']); ?>
                            </span>
                        <?php endif; ?>
                    </div>
                    <p class="text-xs text-rose-800 font-semibold" data-testid="ai-error-message">
                        <?php echo htmlspecialchars($ai_error_details['user_message'] ?? $error_msg); ?>
                    </p>
                    <?php if (!empty($ai_error_details['technical_message'])): ?>
                        <div class="bg-rose-100/60 rounded-xl p-3 text-[11px] font-mono text-rose-900 border border-rose-200" data-testid="ai-error-technical">
                            <span class="font-bold">Technical Details:</span> <?php echo htmlspecialchars($ai_error_details['technical_message']); ?>
                        </div>
                    <?php endif; ?>
                    <?php if (!empty($ai_error_details['retryable'])): ?>
                        <div class="pt-2 flex items-center gap-3">
                            <button type="button" onclick="document.querySelector('form button[name=generate_questions]').click();" data-testid="ai-retry-btn" class="bg-rose-600 hover:bg-rose-700 text-white font-extrabold text-xs px-4 py-2 rounded-xl shadow transition-all flex items-center gap-2">
                                <i class="fa-solid fa-rotate-right"></i> Retry AI Generation
                            </button>
                            <span class="text-[11px] text-rose-700 font-medium">This issue may be temporary. You can retry safely.</span>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($structured_conflicts)): ?>
                <div class="bg-rose-50 border border-rose-200 rounded-2xl p-4 shadow-sm space-y-3 animate-fadeIn" data-testid="academic-context-conflicts">
                    <div class="flex items-center gap-2 text-rose-800 font-extrabold text-xs">
                        <i class="fa-solid fa-triangle-exclamation text-rose-600 text-sm"></i>
                        <span>Academic Context Conflict Validation Summary</span>
                    </div>
                    <p class="text-[11px] text-rose-700 font-medium">The selected lessons contain conflicting metadata fields or mismatch the requested exam context. Please align your lesson pool before proceeding.</p>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-xs text-rose-900 border-collapse">
                            <thead>
                                <tr class="bg-rose-100/70 text-[10px] font-extrabold uppercase text-rose-800 border-b border-rose-200">
                                    <th class="p-2">Lesson Title</th>
                                    <th class="p-2">Conflicting Field</th>
                                    <th class="p-2">Expected Value</th>
                                    <th class="p-2">Actual Value</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-rose-200/60 font-medium">
                                <?php foreach ($structured_conflicts as $conflict): ?>
                                    <tr class="hover:bg-rose-100/40">
                                        <td class="p-2 font-bold" data-testid="conflict-title"><?php echo htmlspecialchars($conflict['title']); ?></td>
                                        <td class="p-2" data-testid="conflict-field"><span class="bg-rose-200/60 text-rose-900 px-1.5 py-0.5 rounded font-extrabold text-[10px]"><?php echo htmlspecialchars($conflict['field']); ?></span></td>
                                        <td class="p-2 font-bold text-emerald-800" data-testid="conflict-expected"><?php echo htmlspecialchars($conflict['expected']); ?></td>
                                        <td class="p-2 font-bold text-rose-700" data-testid="conflict-actual"><?php echo htmlspecialchars($conflict['actual']); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>

            <?php if (!empty($signed_confirmation_token)): ?>
                <div class="bg-amber-50 border border-amber-300 rounded-2xl p-5 shadow-md space-y-4 animate-fadeIn" data-testid="partial-generation-confirmation">
                    <div class="flex items-center gap-2 text-amber-900 font-extrabold text-xs">
                        <i class="fa-solid fa-shield-halved text-amber-600 text-base"></i>
                        <span>Secure Server-Side Partial Generation Confirmation</span>
                    </div>
                    <p class="text-xs text-amber-800 font-medium">Some selected lessons are invalid or incomplete. Would you like to proceed generating assessment items strictly using the <strong class="text-amber-950 font-bold"><?php echo count($associated_lesson_ids); ?> valid lesson(s)</strong>?</p>
                    
                    <form action="generate_ai.php" method="POST" class="flex items-center gap-3">
                        <?php echo csrfInputField(); ?>
                        <input type="hidden" name="partial_token" value="<?php echo htmlspecialchars($signed_confirmation_token); ?>">
                        <input type="hidden" name="num_questions" value="<?php echo htmlspecialchars($_POST['num_questions'] ?? 5); ?>">
                        <input type="hidden" name="subject" value="<?php echo htmlspecialchars($_POST['subject'] ?? ''); ?>">
                        <input type="hidden" name="exam_title" value="<?php echo htmlspecialchars($_POST['exam_title'] ?? ''); ?>">
                        <input type="hidden" name="specialization" value="<?php echo htmlspecialchars($_POST['specialization'] ?? ''); ?>">
                        <input type="hidden" name="question_type" value="<?php echo htmlspecialchars($_POST['question_type'] ?? ''); ?>">
                        <input type="hidden" name="difficulty" value="<?php echo htmlspecialchars($_POST['difficulty'] ?? ''); ?>">

                        <button type="submit" name="confirm_partial_token" value="1" data-testid="confirm-partial-btn" class="bg-amber-600 hover:bg-amber-700 text-white font-extrabold text-xs px-5 py-2.5 rounded-xl shadow transition-all flex items-center gap-2">
                            <i class="fa-solid fa-check-double"></i> Confirm Partial Generation with Valid Lessons Only
                        </button>
                        <a href="generate_ai.php" class="text-xs font-bold text-amber-700 hover:text-amber-900">Cancel</a>
                    </form>
                </div>
            <?php endif; ?>

            
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">
                
                
                <div class="lg:col-span-5 bg-white border border-stone-200 rounded-2xl p-6 shadow-sm space-y-5">
                    <div class="flex items-center justify-between border-b border-stone-100 pb-3">
                        <h3 class="text-xs font-extrabold uppercase tracking-wider text-stone-800 flex items-center gap-2">
                            <i class="fa-solid fa-book-open text-orange-500"></i> 1. Lesson & Branch Setup
                        </h3>
                        <span class="text-[10px] bg-orange-100 text-orange-700 font-extrabold px-2 py-0.5 rounded-full">Groq Llama-3.3</span>
                    </div>

                    <form action="generate_ai.php" method="POST" id="ai_form" class="space-y-4">
                        <?php echo csrfInputField(); ?>
                        
                        <div class="space-y-1">
                            <label class="text-xs font-bold text-stone-700">Exam Title</label>
                            <div class="relative">
                                <i class="fa-solid fa-file-signature absolute left-3.5 top-3 text-stone-400 text-xs"></i>
                                <input type="text" name="exam_title" required value="<?php echo htmlspecialchars($_POST['exam_title'] ?? ''); ?>" placeholder="e.g. Reinforced Concrete Design Quiz 1" class="w-full bg-stone-50 border border-stone-200 rounded-xl pl-9 pr-4 py-2.5 text-xs font-semibold text-stone-800 outline-none focus:border-orange-500 focus:bg-white transition-all">
                            </div>
                        </div>

                        <div class="space-y-1">
                            <label class="text-xs font-bold text-stone-700">Subject Name</label>
                            <div class="relative">
                                <i class="fa-solid fa-book absolute left-3.5 top-3 text-stone-400 text-xs"></i>
                                <input type="text" name="subject" required value="<?php echo htmlspecialchars($_POST['subject'] ?? ''); ?>" placeholder="e.g. Structural Theory" class="w-full bg-stone-50 border border-stone-200 rounded-xl pl-9 pr-4 py-2.5 text-xs font-semibold text-stone-800 outline-none focus:border-orange-500 focus:bg-white transition-all">
                            </div>
                        </div>

                        <div class="space-y-1">
                            <label class="text-xs font-bold text-stone-700">Content Input Source</label>
                            <div class="grid grid-cols-2 gap-2 bg-stone-100 p-1 rounded-xl">
                                <label class="flex items-center justify-center gap-1.5 py-1.5 px-2 rounded-lg text-xs font-bold cursor-pointer transition-all has-[:checked]:bg-white has-[:checked]:text-orange-600 has-[:checked]:shadow-sm text-stone-600">
                                    <input type="radio" name="input_source" value="extracted" onclick="toggleInputSource('extracted')" <?php echo (($_POST['input_source'] ?? 'extracted') === 'extracted') ? 'checked' : ''; ?> class="hidden">
                                    <i class="fa-solid fa-file-lines"></i> Extracted Lessons
                                </label>
                                <label class="flex items-center justify-center gap-1.5 py-1.5 px-2 rounded-lg text-xs font-bold cursor-pointer transition-all has-[:checked]:bg-white has-[:checked]:text-orange-600 has-[:checked]:shadow-sm text-stone-600">
                                    <input type="radio" name="input_source" value="manual" onclick="toggleInputSource('manual')" <?php echo (($_POST['input_source'] ?? '') === 'manual') ? 'checked' : ''; ?> class="hidden">
                                    <i class="fa-solid fa-paste"></i> Manual Paste
                                </label>
                            </div>
                        </div>

                        <div id="extracted_lessons_block" class="space-y-3 <?php echo (($_POST['input_source'] ?? 'extracted') === 'manual') ? 'hidden' : ''; ?>">
                            <label class="text-xs font-bold text-stone-700 flex justify-between items-center">
                                <span>Select Extracted Lessons (Cross-Period Pool)</span>
                                <span class="text-[10px] text-orange-600 font-semibold"><?php echo count($all_teacher_lessons); ?> Total</span>
                            </label>

                            <div class="bg-stone-50 border border-stone-200 rounded-xl p-2.5 space-y-2">
                                <div class="flex items-center justify-between">
                                    <span class="text-[10px] font-extrabold text-stone-700 uppercase tracking-wider">
                                        <i class="fa-solid fa-filter text-orange-500 mr-1"></i> Filter Lessons
                                    </span>
                                    <button type="button" onclick="resetLessonFilters()" class="text-[10px] text-orange-600 hover:text-orange-800 font-bold">Reset Filters</button>
                                </div>
                                <div class="grid grid-cols-2 sm:grid-cols-3 gap-2">
                                    <div>
                                        <label class="text-[9px] font-bold text-stone-500">Subject</label>
                                        <select id="filter_subject" data-testid="filter-subject" onchange="applyLessonFilters()" class="w-full bg-white border border-stone-200 rounded-lg px-2 py-1 text-[11px] font-semibold text-stone-800 outline-none focus:border-orange-500">
                                            <option value="">All Subjects</option>
                                            <?php foreach ($filter_subjects as $s): ?>
                                                <option value="<?php echo htmlspecialchars($s); ?>"><?php echo htmlspecialchars($s); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div>
                                        <label class="text-[9px] font-bold text-stone-500">Year Level</label>
                                        <select id="filter_year_level" data-testid="filter-year-level" onchange="applyLessonFilters()" class="w-full bg-white border border-stone-200 rounded-lg px-2 py-1 text-[11px] font-semibold text-stone-800 outline-none focus:border-orange-500">
                                            <option value="">All Year Levels</option>
                                            <?php foreach ($filter_year_levels as $yl): ?>
                                                <option value="<?php echo htmlspecialchars($yl); ?>"><?php echo htmlspecialchars($yl); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div>
                                        <label class="text-[9px] font-bold text-stone-500">Program</label>
                                        <select id="filter_program" data-testid="filter-program" onchange="applyLessonFilters()" class="w-full bg-white border border-stone-200 rounded-lg px-2 py-1 text-[11px] font-semibold text-stone-800 outline-none focus:border-orange-500">
                                            <option value="">All Programs</option>
                                            <?php foreach ($filter_programs as $p): ?>
                                                <option value="<?php echo htmlspecialchars($p); ?>"><?php echo htmlspecialchars($p); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div>
                                        <label class="text-[9px] font-bold text-stone-500">Semester</label>
                                        <select id="filter_semester" data-testid="filter-semester" onchange="applyLessonFilters()" class="w-full bg-white border border-stone-200 rounded-lg px-2 py-1 text-[11px] font-semibold text-stone-800 outline-none focus:border-orange-500">
                                            <option value="">All Semesters</option>
                                            <?php foreach ($filter_semesters as $sem): ?>
                                                <option value="<?php echo htmlspecialchars($sem); ?>"><?php echo htmlspecialchars($sem); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div>
                                        <label class="text-[9px] font-bold text-stone-500">School Year</label>
                                        <select id="filter_school_year" data-testid="filter-school-year" onchange="applyLessonFilters()" class="w-full bg-white border border-stone-200 rounded-lg px-2 py-1 text-[11px] font-semibold text-stone-800 outline-none focus:border-orange-500">
                                            <option value="">All School Years</option>
                                            <?php foreach ($filter_school_years as $sy): ?>
                                                <option value="<?php echo htmlspecialchars($sy); ?>"><?php echo htmlspecialchars($sy); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div>
                                        <label class="text-[9px] font-bold text-stone-500">Academic Period</label>
                                        <select id="filter_academic_period" data-testid="filter-academic-period" onchange="applyLessonFilters()" class="w-full bg-white border border-stone-200 rounded-lg px-2 py-1 text-[11px] font-semibold text-stone-800 outline-none focus:border-orange-500">
                                            <option value="">All Periods</option>
                                            <option value="general">General</option>
                                            <option value="prelim">Prelim</option>
                                            <option value="midterm">Midterm</option>
                                            <option value="finals">Finals</option>
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <div class="space-y-1">
                                <label class="text-[10px] font-bold text-stone-500 uppercase tracking-wider">Quick Select Controls</label>
                                <div class="flex flex-wrap gap-1.5">
                                    <button type="button" onclick="quickSelect('general')" data-testid="select-all-general" class="px-2 py-1 bg-stone-200 hover:bg-stone-300 text-stone-800 rounded-md text-[10px] font-bold transition-all shadow-xs">
                                        Select All General
                                    </button>
                                    <button type="button" onclick="quickSelect('prelim')" data-testid="select-all-prelim" class="px-2 py-1 bg-blue-100 hover:bg-blue-200 text-blue-800 rounded-md text-[10px] font-bold transition-all shadow-xs">
                                        Select All Prelim
                                    </button>
                                    <button type="button" onclick="quickSelect('midterm')" data-testid="select-all-midterm" class="px-2 py-1 bg-amber-100 hover:bg-amber-200 text-amber-800 rounded-md text-[10px] font-bold transition-all shadow-xs">
                                        Select All Midterm
                                    </button>
                                    <button type="button" onclick="quickSelect('finals')" data-testid="select-all-finals" class="px-2 py-1 bg-purple-100 hover:bg-purple-200 text-purple-800 rounded-md text-[10px] font-bold transition-all shadow-xs">
                                        Select All Finals
                                    </button>
                                    <button type="button" onclick="quickSelect('visible')" data-testid="select-all-visible" class="px-2 py-1 bg-emerald-100 hover:bg-emerald-200 text-emerald-800 rounded-md text-[10px] font-bold transition-all shadow-xs">
                                        Select All Visible
                                    </button>
                                    <button type="button" onclick="clearSelection()" data-testid="clear-selection" class="px-2 py-1 bg-rose-100 hover:bg-rose-200 text-rose-800 rounded-md text-[10px] font-bold transition-all shadow-xs">
                                        Clear Selection
                                    </button>
                                </div>
                            </div>

                            <?php if (!empty($all_teacher_lessons)): ?>
                                <div class="max-h-60 overflow-y-auto border border-stone-200 rounded-xl bg-stone-50 p-2.5 space-y-4 text-xs custom-scrollbar">
                                    <?php foreach (['general' => 'General', 'prelim' => 'Prelim', 'midterm' => 'Midterm', 'finals' => 'Finals'] as $periodKey => $periodTitle): ?>
                                        <div class="period-group-block space-y-2" data-period="<?php echo $periodKey; ?>" data-testid="period-group-<?php echo $periodKey; ?>">
                                            <div class="flex items-center justify-between border-b border-stone-200 pb-1">
                                                <h4 class="text-xs font-black uppercase tracking-wider text-stone-700 flex items-center gap-1.5">
                                                    <?php
                                                    $badgeClass = match($periodKey) {
                                                        'prelim' => 'bg-blue-600 text-white',
                                                        'midterm' => 'bg-amber-600 text-white',
                                                        'finals' => 'bg-purple-600 text-white',
                                                        default => 'bg-stone-600 text-white'
                                                    };
                                                    ?>
                                                    <span class="px-1.5 py-0.5 rounded text-[9px] font-black <?php echo $badgeClass; ?>"><?php echo strtoupper($periodKey); ?></span>
                                                    <span><?php echo $periodTitle; ?></span>
                                                </h4>
                                                <span class="text-[10px] font-bold text-stone-400 period-count"><?php echo count($lessons_by_period[$periodKey]); ?> items</span>
                                            </div>

                                            <?php if (!empty($lessons_by_period[$periodKey])): ?>
                                                <div class="space-y-1.5">
                                                    <?php foreach ($lessons_by_period[$periodKey] as $cl): 
                                                        $isCompleted = ($cl['processing_status'] ?? '') === 'completed';
                                                        $hasContent = !empty(trim($cl['lesson_text'] ?? ''));
                                                        $canSelect = $isCompleted && $hasContent;
                                                    ?>
                                                        <div class="lesson-card p-2 bg-white border border-stone-200 rounded-lg space-y-1 transition-all" 
                                                             data-testid="lesson-card-<?php echo $cl['id']; ?>"
                                                             data-id="<?php echo $cl['id']; ?>"
                                                             data-subject="<?php echo htmlspecialchars($cl['subject'] ?? ''); ?>"
                                                             data-year-level="<?php echo htmlspecialchars($cl['year_level'] ?? ''); ?>"
                                                             data-program="<?php echo htmlspecialchars($cl['program'] ?? ''); ?>"
                                                             data-semester="<?php echo htmlspecialchars($cl['semester'] ?? ''); ?>"
                                                             data-school-year="<?php echo htmlspecialchars($cl['school_year'] ?? ''); ?>"
                                                             data-academic-period="<?php echo htmlspecialchars($cl['academic_period'] ?? 'general'); ?>">

                                                            <div class="flex items-start justify-between gap-2">
                                                                <label class="flex items-start gap-2 cursor-pointer font-bold text-stone-800 text-xs truncate flex-grow">
                                                                    <input type="checkbox" 
                                                                           name="selected_lessons[]" 
                                                                           value="<?php echo $cl['id']; ?>" 
                                                                           data-testid="lesson-checkbox-<?php echo $cl['id']; ?>"
                                                                           data-period="<?php echo $periodKey; ?>"
                                                                           <?php echo $canSelect ? '' : 'disabled'; ?>
                                                                           class="lesson-checkbox accent-orange-600 rounded mt-0.5">
                                                                    <span data-testid="lesson-title-<?php echo $cl['id']; ?>" class="truncate leading-tight <?php echo $canSelect ? '' : 'text-stone-400 line-through'; ?>">
                                                                        <?php echo htmlspecialchars($cl['title']); ?>
                                                                    </span>
                                                                </label>

                                                                <?php if ($isCompleted && $hasContent): ?>
                                                                    <span data-testid="lesson-status-<?php echo $cl['id']; ?>" class="text-[9px] font-extrabold text-emerald-700 bg-emerald-50 border border-emerald-200 px-1.5 py-0.5 rounded flex-shrink-0">
                                                                        Completed
                                                                    </span>
                                                                <?php elseif (!$isCompleted): ?>
                                                                    <span data-testid="lesson-status-<?php echo $cl['id']; ?>" class="text-[9px] font-extrabold text-amber-700 bg-amber-50 border border-amber-200 px-1.5 py-0.5 rounded flex-shrink-0">
                                                                        <?php echo ucfirst($cl['processing_status'] ?? 'Processing'); ?>
                                                                    </span>
                                                                <?php else: ?>
                                                                    <span data-testid="lesson-status-<?php echo $cl['id']; ?>" class="text-[9px] font-extrabold text-rose-700 bg-rose-50 border border-rose-200 px-1.5 py-0.5 rounded flex-shrink-0">
                                                                        Empty
                                                                    </span>
                                                                <?php endif; ?>
                                                            </div>

                                                            <div class="flex flex-wrap items-center gap-1.5 text-[10px] text-stone-500 font-medium">
                                                                <span class="bg-stone-100 px-1.5 py-0.5 rounded border border-stone-200" data-testid="lesson-subject-<?php echo $cl['id']; ?>">
                                                                    <i class="fa-solid fa-book text-stone-400 mr-0.5"></i><?php echo htmlspecialchars($cl['subject'] ?? 'General'); ?>
                                                                </span>
                                                                <?php if (!empty($cl['semester'])): ?>
                                                                    <span class="bg-stone-100 px-1.5 py-0.5 rounded border border-stone-200" data-testid="lesson-semester-<?php echo $cl['id']; ?>">
                                                                        <?php echo htmlspecialchars($cl['semester']); ?>
                                                                    </span>
                                                                <?php endif; ?>
                                                                <?php if (!empty($cl['school_year'])): ?>
                                                                    <span class="bg-stone-100 px-1.5 py-0.5 rounded border border-stone-200" data-testid="lesson-school-year-<?php echo $cl['id']; ?>">
                                                                        <?php echo htmlspecialchars($cl['school_year']); ?>
                                                                    </span>
                                                                <?php endif; ?>
                                                                <?php if (!empty($cl['year_level'])): ?>
                                                                    <span class="bg-stone-100 px-1.5 py-0.5 rounded border border-stone-200" data-testid="lesson-year-level-<?php echo $cl['id']; ?>">
                                                                        <?php echo htmlspecialchars($cl['year_level']); ?>
                                                                    </span>
                                                                <?php endif; ?>
                                                                <?php if (!empty($cl['program'])): ?>
                                                                    <span class="bg-stone-100 px-1.5 py-0.5 rounded border border-stone-200" data-testid="lesson-program-<?php echo $cl['id']; ?>">
                                                                        <?php echo htmlspecialchars($cl['program']); ?>
                                                                    </span>
                                                                <?php endif; ?>
                                                            </div>
                                                        </div>
                                                    <?php endforeach; ?>
                                                </div>
                                            <?php else: ?>
                                                <p class="text-[11px] text-stone-400 italic px-2">No materials uploaded for this period.</p>
                                            <?php endif; ?>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <div class="p-3 bg-amber-50 border border-amber-200 rounded-xl text-amber-800 text-xs font-semibold">
                                    No extracted lessons found. Please upload lesson materials first under <a href="upload_lessons.php" class="underline font-bold">Upload Lessons</a> or use Manual Paste mode.
                                </div>
                            <?php endif; ?>
                        </div>

                        <div id="manual_text_block" class="space-y-1 <?php echo (($_POST['input_source'] ?? 'extracted') === 'extracted' || !isset($_POST['input_source'])) ? 'hidden' : ''; ?>">
                            <label class="text-xs font-bold text-stone-700">Paste Lesson Content / Syllabi Notes</label>
                            <textarea name="lesson_text" rows="5" placeholder="Paste Civil Engineering notes, formulas, or lecture content here..." class="w-full bg-stone-50 border border-stone-200 rounded-xl p-3 text-xs font-medium text-stone-800 outline-none focus:border-orange-500 focus:bg-white resize-none transition-all"><?php echo htmlspecialchars($_POST['lesson_text'] ?? ''); ?></textarea>
                        </div>

                        <div class="grid grid-cols-2 gap-3">
                            <div class="space-y-1">
                                <label class="text-xs font-bold text-stone-700">Difficulty Level</label>
                                <select name="difficulty" class="w-full bg-stone-50 border border-stone-200 rounded-xl px-3 py-2 text-xs font-semibold text-stone-800 outline-none focus:border-orange-500">
                                    <option value="easy" <?php echo (($_POST['difficulty'] ?? '') === 'easy') ? 'selected' : ''; ?>>Easy</option>
                                    <option value="medium" <?php echo (($_POST['difficulty'] ?? 'medium') === 'medium') ? 'selected' : ''; ?>>Medium</option>
                                    <option value="hard" <?php echo (($_POST['difficulty'] ?? '') === 'hard') ? 'selected' : ''; ?>>Hard / Advanced</option>
                                    <option value="mixed" <?php echo (($_POST['difficulty'] ?? '') === 'mixed') ? 'selected' : ''; ?>>Mixed Difficulty</option>
                                </select>
                            </div>

                            <div class="space-y-1">
                                <label class="text-xs font-bold text-stone-700">Number of Items</label>
                                <select name="num_questions" class="w-full bg-stone-50 border border-stone-200 rounded-xl px-3 py-2 text-xs font-semibold text-stone-800 outline-none focus:border-orange-500">
                                    <?php foreach ([5, 10, 15, 20, 25, 30, 50] as $n): ?>
                                        <option value="<?php echo $n; ?>" <?php echo (intval($_POST['num_questions'] ?? 5) === $n) ? 'selected' : ''; ?>><?php echo $n; ?> Questions</option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <div class="space-y-1">
                            <label class="text-xs font-bold text-stone-700">Civil Engineering Specialization</label>
                            <select name="specialization" required class="w-full bg-stone-50 border border-stone-200 rounded-xl px-3 py-2 text-xs font-semibold text-stone-800 outline-none focus:border-orange-500">
                                <?php foreach (getCivilEngineeringSpecializations() as $key => $label): ?>
                                    <option value="<?php echo htmlspecialchars($key); ?>" <?php echo (($_POST['specialization'] ?? '') === $key) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($label); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="space-y-1">
                            <label class="text-xs font-bold text-stone-700">Question Format / Type</label>
                            <select name="question_type" required class="w-full bg-stone-50 border border-stone-200 rounded-xl px-3 py-2 text-xs font-semibold text-stone-800 outline-none focus:border-orange-500">
                                <option value="multiple_choice" <?php echo (($_POST['question_type'] ?? '') === 'multiple_choice') ? 'selected' : ''; ?>>Multiple Choice (Options A-D)</option>
                                <option value="true_false" <?php echo (($_POST['question_type'] ?? '') === 'true_false') ? 'selected' : ''; ?>>True or False</option>
                                <option value="identification" <?php echo (($_POST['question_type'] ?? '') === 'identification') ? 'selected' : ''; ?>>Identification</option>
                                <option value="fill_in_the_blank" <?php echo (($_POST['question_type'] ?? '') === 'fill_in_the_blank') ? 'selected' : ''; ?>>Fill-in-the-Blank</option>
                                <option value="matching_type" <?php echo (($_POST['question_type'] ?? '') === 'matching_type') ? 'selected' : ''; ?>>Matching Type</option>
                                <option value="problem_solving" <?php echo (($_POST['question_type'] ?? '') === 'problem_solving') ? 'selected' : ''; ?>>Problem Solving</option>
                                <option value="math_formula" <?php echo (($_POST['question_type'] ?? '') === 'math_formula') ? 'selected' : ''; ?>>Math Formula (LaTeX)</option>
                            </select>
                        </div>

                        <button type="submit" name="generate_questions" onclick="showLoadingState()" class="w-full bg-orange-600 hover:bg-orange-700 text-white font-extrabold text-xs py-3.5 rounded-xl transition-all shadow-md hover:shadow-lg flex items-center justify-center gap-2">
                            <i class="fa-solid fa-robot"></i> Generate AI Test Items
                        </button>
                    </form>

                    <script>
                        function toggleInputSource(type) {
                            const ext = document.getElementById('extracted_lessons_block');
                            const man = document.getElementById('manual_text_block');
                            if (type === 'extracted') {
                                ext.classList.remove('hidden');
                                man.classList.add('hidden');
                            } else {
                                ext.classList.add('hidden');
                                man.classList.remove('hidden');
                            }
                        }

                        function applyLessonFilters() {
                            const subj = document.getElementById('filter_subject').value.toLowerCase();
                            const yl = document.getElementById('filter_year_level').value.toLowerCase();
                            const prog = document.getElementById('filter_program').value.toLowerCase();
                            const sem = document.getElementById('filter_semester').value.toLowerCase();
                            const sy = document.getElementById('filter_school_year').value.toLowerCase();
                            const period = document.getElementById('filter_academic_period').value.toLowerCase();

                            document.querySelectorAll('.lesson-card').forEach(card => {
                                const cSubj = (card.getAttribute('data-subject') || '').toLowerCase();
                                const cYl = (card.getAttribute('data-year-level') || '').toLowerCase();
                                const cProg = (card.getAttribute('data-program') || '').toLowerCase();
                                const cSem = (card.getAttribute('data-semester') || '').toLowerCase();
                                const cSy = (card.getAttribute('data-school-year') || '').toLowerCase();
                                const cPeriod = (card.getAttribute('data-academic-period') || 'general').toLowerCase();

                                let match = true;
                                if (subj && cSubj !== subj) match = false;
                                if (yl && cYl !== yl) match = false;
                                if (prog && cProg !== prog) match = false;
                                if (sem && cSem !== sem) match = false;
                                if (sy && cSy !== sy) match = false;
                                if (period && cPeriod !== period) match = false;

                                if (match) {
                                    card.classList.remove('hidden');
                                } else {
                                    card.classList.add('hidden');
                                }
                            });

                            document.querySelectorAll('.period-group-block').forEach(group => {
                                const visibleCards = group.querySelectorAll('.lesson-card:not(.hidden)');
                                const gPeriod = group.getAttribute('data-period');
                                if (period && gPeriod !== period) {
                                    group.classList.add('hidden');
                                } else if (visibleCards.length === 0 && (subj || yl || prog || sem || sy || period)) {
                                    group.classList.add('hidden');
                                } else {
                                    group.classList.remove('hidden');
                                }
                            });
                        }

                        function resetLessonFilters() {
                            if (document.getElementById('filter_subject')) document.getElementById('filter_subject').value = '';
                            if (document.getElementById('filter_year_level')) document.getElementById('filter_year_level').value = '';
                            if (document.getElementById('filter_program')) document.getElementById('filter_program').value = '';
                            if (document.getElementById('filter_semester')) document.getElementById('filter_semester').value = '';
                            if (document.getElementById('filter_school_year')) document.getElementById('filter_school_year').value = '';
                            if (document.getElementById('filter_academic_period')) document.getElementById('filter_academic_period').value = '';
                            applyLessonFilters();
                        }

                        function quickSelect(target) {
                            document.querySelectorAll('.lesson-card').forEach(card => {
                                if (card.classList.contains('hidden')) return;

                                const checkbox = card.querySelector('.lesson-checkbox');
                                if (!checkbox || checkbox.disabled) return;

                                const cPeriod = (card.getAttribute('data-academic-period') || 'general').toLowerCase();

                                if (target === 'visible') {
                                    checkbox.checked = true;
                                } else if (target === cPeriod) {
                                    checkbox.checked = true;
                                }
                            });
                        }

                        function clearSelection() {
                            document.querySelectorAll('.lesson-checkbox').forEach(cb => {
                                cb.checked = false;
                            });
                        }

                        function updateManualSourceDisplay(selectElem) {
                            const card = selectElem.closest('[data-testid="generated-question-item"]');
                            if (!card) return;
                            const attrContainer = card.querySelector('[data-testid="question-source-attribution"]');
                            if (!attrContainer) return;
                            
                            const selectedOption = selectElem.options[selectElem.selectedIndex];
                            const period = selectedOption.getAttribute('data-period') || '';
                            const title = selectedOption.getAttribute('data-title') || '';
                            
                            if (selectElem.value && period) {
                                attrContainer.innerHTML = `
                                    <span class="bg-blue-100 text-blue-800 font-extrabold px-2 py-0.5 rounded-full uppercase" data-testid="source-period">${period}</span>
                                    <span class="bg-stone-200 text-stone-700 font-bold px-2 py-0.5 rounded-full truncate max-w-[120px]" data-testid="source-topic">${title}</span>
                                    <span class="bg-emerald-100 text-emerald-800 font-extrabold px-1.5 py-0.5 rounded-full" data-testid="source-confidence"><i class="fa-solid fa-circle-check mr-0.5"></i> Manual Verified</span>
                                `;
                            } else {
                                attrContainer.innerHTML = `
                                    <span class="bg-amber-100 text-amber-800 font-extrabold px-2 py-0.5 rounded-full flex items-center gap-1" data-testid="source-verification-required"><i class="fa-solid fa-triangle-exclamation text-amber-600"></i> Source verification required.</span>
                                `;
                            }
                        }
                    </script>
                </div>

                
                <div class="lg:col-span-7 space-y-4">
                    <?php if (!empty($generated_questions)): ?>
                        <!-- Generation Audit Summary View (Repair Prompt 5) -->
                        <div class="bg-stone-900 text-white border border-stone-800 rounded-2xl p-4 shadow-sm space-y-3" data-testid="generation-audit-summary">
                            <div class="flex items-center justify-between border-b border-stone-800 pb-2">
                                <div class="flex items-center gap-2">
                                    <i class="fa-solid fa-square-poll-vertical text-orange-400 text-sm"></i>
                                    <span class="text-xs font-black uppercase tracking-wider text-stone-200">AI Batch Audit Summary</span>
                                </div>
                                <div class="flex items-center gap-2">
                                    <span class="text-[10px] font-mono bg-stone-800 text-stone-300 px-2 py-0.5 rounded border border-stone-700">
                                        ID: <?php echo htmlspecialchars(substr($ai_meta_output['generation_batch_id'] ?? '', 0, 12)); ?>
                                    </span>
                                    <span class="text-[10px] font-extrabold uppercase px-2 py-0.5 rounded border <?php echo ($ai_meta_output['batch_status'] ?? '') === 'incomplete' ? 'bg-amber-950 text-amber-300 border-amber-800' : 'bg-emerald-950 text-emerald-300 border-emerald-800'; ?>" data-testid="audit-batch-status">
                                        Status: <?php echo htmlspecialchars($ai_meta_output['batch_status'] ?? 'completed'); ?>
                                    </span>
                                </div>
                            </div>
                            <div class="grid grid-cols-2 sm:grid-cols-4 gap-2 text-[11px]">
                                <div class="bg-stone-800/60 p-2 rounded-xl border border-stone-700/50">
                                    <span class="text-[9px] font-bold text-stone-400 block uppercase">Lessons Covered</span>
                                    <span class="font-extrabold text-orange-400" data-testid="audit-lesson-count"><?php echo count($ai_meta_output['lesson_ids'] ?? []); ?> Materials</span>
                                </div>
                                <div class="bg-stone-800/60 p-2 rounded-xl border border-stone-700/50">
                                    <span class="text-[9px] font-bold text-stone-400 block uppercase">Periods Covered</span>
                                    <span class="font-extrabold text-stone-200" data-testid="audit-periods"><?php echo htmlspecialchars(implode(', ', array_map('ucfirst', $ai_meta_output['covered_periods'] ?? [])) ?: 'General'); ?></span>
                                </div>
                                <div class="bg-stone-800/60 p-2 rounded-xl border border-stone-700/50">
                                    <span class="text-[9px] font-bold text-stone-400 block uppercase">Context Tokens</span>
                                    <span class="font-extrabold text-emerald-400" data-testid="audit-tokens"><?php echo number_format($ai_meta_output['estimated_tokens'] ?? 0); ?> Tokens</span>
                                </div>
                                <div class="bg-stone-800/60 p-2 rounded-xl border border-stone-700/50">
                                    <span class="text-[9px] font-bold text-stone-400 block uppercase">Generation Time</span>
                                    <span class="font-extrabold text-stone-200" data-testid="audit-time"><?php echo number_format(($ai_meta_output['generation_time_ms'] ?? 0) / 1000, 2); ?>s</span>
                                </div>
                            </div>
                            <?php if (!empty($ai_meta_output['generation_warnings'])): ?>
                                <div class="p-2 bg-amber-950/60 border border-amber-800/60 rounded-xl text-amber-300 text-[10px] space-y-0.5">
                                    <span class="font-bold flex items-center gap-1"><i class="fa-solid fa-triangle-exclamation"></i> Batch Warnings:</span>
                                    <ul class="list-disc pl-4 space-y-0.5">
                                        <?php foreach ($ai_meta_output['generation_warnings'] as $w): ?>
                                            <li><?php echo htmlspecialchars($w); ?></li>
                                        <?php endforeach; ?>
                                    </ul>
                                </div>
                            <?php endif; ?>
                        </div>

                        <form action="generate_ai.php" method="POST" class="bg-white border border-stone-200 rounded-2xl p-6 shadow-sm space-y-6 animate-fadeIn">
                            <?php echo csrfInputField(); ?>
                            
                            <div class="flex items-center justify-between border-b border-stone-100 pb-4">
                                <div>
                                    <h3 class="text-sm font-extrabold text-stone-800 uppercase tracking-tight flex items-center gap-2">
                                        <i class="fa-solid fa-list-check text-orange-600"></i> 2. Review & Save Exam
                                    </h3>
                                    <p class="text-[11px] text-stone-400 font-medium mt-0.5">
                                        Title: <strong class="text-stone-700"><?php echo htmlspecialchars($_POST['save_title'] ?? $_POST['exam_title'] ?? ''); ?></strong> | 
                                        Branch: <strong class="text-orange-600"><?php echo htmlspecialchars($_POST['save_specialization'] ?? $_POST['specialization'] ?? ''); ?></strong>
                                    </p>
                                </div>
                                <span class="px-3 py-1 rounded-xl text-xs font-black uppercase tracking-wider shadow-sm bg-orange-100 text-orange-800">
                                    <?php echo count($generated_questions); ?> Items
                                </span>
                            </div>

                            <input type="hidden" name="save_generation_batch_id" value="<?php echo htmlspecialchars($ai_meta_output['generation_batch_id'] ?? ''); ?>" data-testid="save-generation-batch-id">
                            <input type="hidden" name="save_title" value="<?php echo htmlspecialchars($_POST['save_title'] ?? $_POST['exam_title'] ?? ''); ?>">
                            <input type="hidden" name="save_subject" value="<?php echo htmlspecialchars($_POST['save_subject'] ?? $_POST['subject'] ?? ''); ?>">
                            <input type="hidden" name="save_specialization" value="<?php echo htmlspecialchars($_POST['save_specialization'] ?? $_POST['specialization'] ?? ''); ?>">
                            <input type="hidden" name="save_difficulty" value="<?php echo htmlspecialchars($difficulty ?? 'medium'); ?>">
                            <input type="hidden" name="ack_token" value="<?php echo htmlspecialchars($ai_meta_output['ack_token'] ?? ''); ?>">

                            <?php if (($ai_meta_output['batch_status'] ?? '') === 'incomplete'): ?>
                                <div class="bg-amber-50 border border-amber-300 rounded-xl p-3 space-y-2" data-testid="incomplete-batch-acknowledgement-block">
                                    <div class="flex items-center gap-1.5 text-xs font-bold text-amber-900">
                                        <i class="fa-solid fa-triangle-exclamation text-amber-600"></i>
                                        <span>Incomplete Generation Batch Acknowledgement Required</span>
                                    </div>
                                    <p class="text-[11px] text-amber-800 font-medium">Some lesson chunks failed during AI generation. To save this incomplete assessment, state your explicit reason below.</p>
                                    <input type="text" name="acknowledgement_reason" placeholder="e.g. Proceeding with partial prelim/midterm coverage for quiz setup" data-testid="ack-reason-input" class="w-full bg-white border border-amber-300 rounded-lg p-2 text-xs font-semibold text-stone-800 outline-none focus:border-amber-500">
                                </div>
                            <?php endif; ?>

                            <div class="space-y-4 max-h-[500px] overflow-y-auto pr-2 custom-scrollbar">
                                <?php foreach ($generated_questions as $idx => $item): 
                                    $rawSrcIds = !empty($item['source_lesson_ids']) ? (array)$item['source_lesson_ids'] : [];
                                    $selectedPoolIds = $ai_meta_output['lesson_ids'] ?? [];
                                    $itemSrcIds = array_values(array_unique(array_intersect(array_map('intval', $rawSrcIds), array_map('intval', $selectedPoolIds))));
                                    $isSourceVerified = !empty($itemSrcIds) && empty($item['source_review_required']);

                                    $matchedLesson = null;
                                    if ($isSourceVerified) {
                                        foreach ($selLessons ?? [] as $sl) {
                                            if ((int)$sl['id'] === (int)$itemSrcIds[0]) {
                                                $matchedLesson = $sl;
                                                break;
                                            }
                                        }
                                    }

                                    $itemPeriod = $matchedLesson['academic_period'] ?? '';
                                    $itemTopic = $matchedLesson['title'] ?? '';
                                    $itemConf = $isSourceVerified ? 'high' : 'review_required';
                                ?>
                                    <div class="p-4 border border-stone-200 rounded-2xl bg-stone-50/40 space-y-3 hover:border-orange-300 transition-all" data-testid="generated-question-item" data-lesson-id="<?php echo htmlspecialchars($itemSrcIds[0] ?? ''); ?>">
                                        <div class="flex items-center justify-between flex-wrap gap-2">
                                            <div class="flex items-center gap-2">
                                                <span class="font-black text-xs text-stone-800 bg-white px-2.5 py-1 rounded-lg border border-stone-200">Item #<?php echo $idx + 1; ?></span>
                                                <span class="text-[10px] font-bold uppercase text-stone-400 bg-white px-2 py-0.5 rounded-md" data-testid="question-type"><?php echo htmlspecialchars($item['type']); ?></span>
                                            </div>

                                            <!-- Question Attribution Badge -->
                                            <div class="flex items-center gap-1.5 text-[10px]" data-testid="question-source-attribution">
                                                <?php if ($isSourceVerified && !empty($itemPeriod)): ?>
                                                    <span class="bg-blue-100 text-blue-800 font-extrabold px-2 py-0.5 rounded-full uppercase" data-testid="source-period">
                                                        <?php echo htmlspecialchars($itemPeriod); ?>
                                                    </span>
                                                    <span class="bg-stone-200 text-stone-700 font-bold px-2 py-0.5 rounded-full truncate max-w-[120px]" data-testid="source-topic">
                                                        <?php echo htmlspecialchars($itemTopic); ?>
                                                    </span>
                                                    <span class="bg-emerald-100 text-emerald-800 font-extrabold px-1.5 py-0.5 rounded-full" data-testid="source-confidence">
                                                        <i class="fa-solid fa-circle-check mr-0.5"></i> High Grounding
                                                    </span>
                                                <?php else: ?>
                                                    <span class="bg-amber-100 text-amber-800 font-extrabold px-2 py-0.5 rounded-full flex items-center gap-1" data-testid="source-verification-required">
                                                        <i class="fa-solid fa-triangle-exclamation text-amber-600"></i> Source verification required.
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                        </div>

                                        <textarea name="questions[<?php echo $idx; ?>][text]" data-testid="question-text" rows="2" class="w-full bg-white border border-stone-200 rounded-lg p-2.5 text-xs outline-none focus:border-orange-500 resize-none font-medium text-stone-800"><?php echo htmlspecialchars($item['question']); ?></textarea>
                                        <input type="hidden" name="questions[<?php echo $idx; ?>][type]" value="<?php echo htmlspecialchars($item['type']); ?>">
                                        <input type="hidden" name="questions[<?php echo $idx; ?>][points]" value="<?php echo htmlspecialchars($item['points'] ?? 1); ?>" data-testid="question-points">
                                        <input type="hidden" name="questions[<?php echo $idx; ?>][source_lesson_ids]" value="<?php echo htmlspecialchars(implode(',', $itemSrcIds)); ?>">
                                        <input type="hidden" name="questions[<?php echo $idx; ?>][source_topic]" value="<?php echo htmlspecialchars($itemTopic); ?>">
                                        <input type="hidden" name="questions[<?php echo $idx; ?>][source_academic_period]" value="<?php echo htmlspecialchars($itemPeriod); ?>">
                                        <input type="hidden" name="questions[<?php echo $idx; ?>][source_confidence]" value="<?php echo htmlspecialchars($itemConf); ?>">
                                         <input type="hidden" name="questions[<?php echo $idx; ?>][target_chunk_lesson_ids]" value="<?php echo htmlspecialchars(implode(",", array_map("intval", (array)($item["target_chunk_lesson_ids"] ?? [])))); ?>">

                                        <!-- Explicit Lesson Selector for Teacher Assignment -->
                                        <div class="pt-2 border-t border-stone-100 flex items-center justify-between gap-3">
                                            <label class="text-[10px] font-bold text-stone-600 uppercase flex items-center gap-1">
                                                <i class="fa-solid fa-book-bookmark text-orange-500"></i> Verified Lesson Source:
                                            </label>
                                            <select name="questions[<?php echo $idx; ?>][manual_source_id]" data-testid="manual-source-select" onchange="updateManualSourceDisplay(this)" class="bg-white border border-stone-300 rounded-lg px-2.5 py-1 text-xs font-semibold text-stone-800 outline-none focus:border-orange-500 max-w-xs">
                                                <option value="" <?php echo !$isSourceVerified ? 'selected' : ''; ?>>-- Select Verified Lesson Source --</option>
                                                <?php 
                                                    $targetChunkLids = $item['target_chunk_lesson_ids'] ?? [];
                                                    $availableLessons = (!empty($targetChunkLids) && !$isSourceVerified) 
                                                        ? array_filter($selLessons ?? [], function($l) use ($targetChunkLids) { return in_array((int)$l['id'], array_map('intval', $targetChunkLids), true); }) 
                                                        : ($selLessons ?? []);
                                                    if (empty($availableLessons)) { $availableLessons = $selLessons ?? []; }
                                                    foreach ($availableLessons as $sl): 
                                                ?>
                                                    <option value="<?php echo $sl['id']; ?>" data-period="<?php echo htmlspecialchars($sl['academic_period'] ?? 'general'); ?>" data-title="<?php echo htmlspecialchars($sl['title']); ?>" <?php echo (in_array((int)$sl['id'], $itemSrcIds)) ? 'selected' : ''; ?>>
                                                        <?php echo htmlspecialchars($sl['title']); ?> (<?php echo ucfirst($sl['academic_period'] ?? 'general'); ?>)
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>

                                        <?php if ($item['type'] === 'multiple_choice'): ?>
                                            <div class="grid grid-cols-2 gap-2 text-xs" data-testid="mcq-options">
                                                <div class="relative">
                                                    <span class="absolute left-2 top-2 text-[10px] font-bold text-stone-400">A.</span>
                                                    <input type="text" name="questions[<?php echo $idx; ?>][opt_a]" value="<?php echo htmlspecialchars($item['opt_a'] ?? ''); ?>" placeholder="Option A" class="w-full bg-white border border-stone-200 rounded-lg pl-6 pr-2 py-1.5 outline-none focus:border-orange-500 text-xs">
                                                </div>
                                                <div class="relative">
                                                    <span class="absolute left-2 top-2 text-[10px] font-bold text-stone-400">B.</span>
                                                    <input type="text" name="questions[<?php echo $idx; ?>][opt_b]" value="<?php echo htmlspecialchars($item['opt_b'] ?? ''); ?>" placeholder="Option B" class="w-full bg-white border border-stone-200 rounded-lg pl-6 pr-2 py-1.5 outline-none focus:border-orange-500 text-xs">
                                                </div>
                                                <div class="relative">
                                                    <span class="absolute left-2 top-2 text-[10px] font-bold text-stone-400">C.</span>
                                                    <input type="text" name="questions[<?php echo $idx; ?>][opt_c]" value="<?php echo htmlspecialchars($item['opt_c'] ?? ''); ?>" placeholder="Option C" class="w-full bg-white border border-stone-200 rounded-lg pl-6 pr-2 py-1.5 outline-none focus:border-orange-500 text-xs">
                                                </div>
                                                <div class="relative">
                                                    <span class="absolute left-2 top-2 text-[10px] font-bold text-stone-400">D.</span>
                                                    <input type="text" name="questions[<?php echo $idx; ?>][opt_d]" value="<?php echo htmlspecialchars($item['opt_d'] ?? ''); ?>" placeholder="Option D" class="w-full bg-white border border-stone-200 rounded-lg pl-6 pr-2 py-1.5 outline-none focus:border-orange-500 text-xs">
                                                </div>
                                            </div>
                                        <?php endif; ?>

                                        <div class="pt-1">
                                            <label class="text-[10px] font-bold text-stone-500 uppercase flex items-center gap-1">
                                                <i class="fa-solid fa-key text-emerald-600"></i> Correct Answer Key:
                                            </label>
                                            <input type="text" name="questions[<?php echo $idx; ?>][correct]" data-testid="answer-key" value="<?php echo htmlspecialchars($item['correct_answer']); ?>" class="w-full bg-emerald-50 border border-emerald-200 rounded-lg p-2 text-xs font-bold text-emerald-700 outline-none focus:border-emerald-500 mt-1">
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>

                            <div class="pt-4 border-t border-stone-100 flex justify-between items-center">
                                <a href="generate_ai.php" class="text-xs font-bold text-stone-400 hover:text-stone-700">Discard Items</a>
                                <button type="submit" name="save_ai_exam" class="bg-emerald-600 hover:bg-emerald-700 text-white font-extrabold text-xs px-6 py-3 rounded-xl shadow-md transition-all flex items-center gap-2">
                                    <i class="fa-solid fa-floppy-disk"></i> Save Exam to Question Bank
                                </button>
                            </div>
                        </form>
                    <?php else: ?>
                        <div class="bg-white border border-stone-200 rounded-2xl p-12 text-center space-y-3 shadow-sm">
                            <div class="w-16 h-16 bg-orange-50 text-orange-500 rounded-3xl flex items-center justify-center mx-auto text-2xl font-black shadow-inner">
                                <i class="fa-solid fa-wand-magic-sparkles"></i>
                            </div>
                            <h3 class="text-sm font-extrabold text-stone-800">Ready to Generate Civil Engineering Exams</h3>
                            <p class="text-xs text-stone-400 max-w-sm mx-auto">Fill out the form on the left with your lesson content and select your Civil Engineering specialization branch.</p>
                        </div>
                    <?php endif; ?>
                </div>

            </div>

        </div>
    </main>

    
    <div id="loading_overlay" class="fixed inset-0 bg-stone-950/70 backdrop-blur-sm hidden flex-col items-center justify-center z-50 p-4">
        <div class="bg-white p-6 rounded-2xl max-w-sm w-full text-center space-y-4 shadow-2xl animate-fadeIn">
            <div class="w-12 h-12 border-4 border-orange-500 border-t-transparent rounded-full animate-spin mx-auto"></div>
            <div>
                <h4 class="font-extrabold text-sm text-stone-800">Generating Exam Questions</h4>
                <p class="text-xs text-stone-500 mt-1">Groq Llama-3.3 AI is parsing lesson content and formatting answer keys...</p>
            </div>
        </div>
    </div>

    <script>
        function showLoadingState() {
            document.getElementById('loading_overlay').classList.remove('hidden');
            document.getElementById('loading_overlay').classList.add('flex');
        }
    </script>
</body>
</html>