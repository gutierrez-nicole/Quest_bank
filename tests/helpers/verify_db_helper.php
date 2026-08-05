<?php
/**
 * Test Helper for Playwright E2E DB Verification
 */
putenv('APP_ENV=testing');
$_ENV['APP_ENV'] = 'testing';

require_once __DIR__ . '/../../app/bootstrap.php';

$action = $argv[1] ?? '';

try {
    $pdo = getDBConnection();
} catch (Throwable $e) {
    fwrite(STDERR, "SETUP FAILED: Database unavailable.\n");
    echo json_encode(['success' => false, 'error' => 'Database connection failed: ' . $e->getMessage()]);
    exit(1);
}

if ($action === 'seed') {
    require_once __DIR__ . '/../../database/seed_e2e_fixtures.php';
    exit(0);
}

if ($action === 'verify_exam_saved') {
    $batchId = $argv[2] ?? '';
    
    if (empty($batchId)) {
        echo json_encode(['success' => false, 'error' => 'Batch ID parameter missing']);
        exit(1);
    }

    $stmtBatch = $pdo->prepare("SELECT * FROM ai_generation_batches WHERE generation_batch_id = ?");
    $stmtBatch->execute([$batchId]);
    $batch = $stmtBatch->fetch(PDO::FETCH_ASSOC);

    if (!$batch) {
        echo json_encode(['success' => false, 'error' => "Batch '{$batchId}' not found in database"]);
        exit(1);
    }

    if (empty($batch['saved_exam_id']) || empty($batch['batch_consumed_at'])) {
        echo json_encode(['success' => false, 'error' => "Batch '{$batchId}' has not been consumed or saved"]);
        exit(1);
    }

    $examId = intval($batch['saved_exam_id']);
    $stmtExam = $pdo->prepare("SELECT * FROM exams WHERE id = ?");
    $stmtExam->execute([$examId]);
    $exam = $stmtExam->fetch(PDO::FETCH_ASSOC);

    if (!$exam) {
        echo json_encode(['success' => false, 'error' => "Exam ID '{$examId}' associated with batch not found"]);
        exit(1);
    }

    // Validate batch and exam metadata agreement
    if ($exam['generation_batch_id'] !== $batchId) {
        echo json_encode(['success' => false, 'error' => "Exam generation_batch_id mismatch"]);
        exit(1);
    }

    $stmtQuestions = $pdo->prepare("SELECT * FROM exam_questions WHERE exam_id = ? ORDER BY id ASC");
    $stmtQuestions->execute([$examId]);
    $questions = $stmtQuestions->fetchAll(PDO::FETCH_ASSOC);

    if (empty($questions)) {
        echo json_encode(['success' => false, 'error' => "No questions found for saved exam ID '{$examId}'"]);
        exit(1);
    }

    $stmtSources = $pdo->prepare("
        SELECT gqs.* 
        FROM generated_question_sources gqs 
        JOIN exam_questions eq ON gqs.question_id = eq.id 
        WHERE eq.exam_id = ?
    ");
    $stmtSources->execute([$examId]);
    $sources = $stmtSources->fetchAll(PDO::FETCH_ASSOC);

    // Decode batch selected lessons
    $decodedLids = json_decode($batch['selected_lesson_ids'] ?? '', true);
    if (is_array($decodedLids)) {
        $batchLessonIds = array_map('intval', $decodedLids);
    } else {
        $batchLessonIds = !empty($batch['selected_lesson_ids']) ? array_map('intval', explode(',', $batch['selected_lesson_ids'])) : [];
    }

    $detailedQuestions = [];
    $seenQuestionLessonPairs = [];

    foreach ($questions as $q) {
        $qId = intval($q['id']);
        
        // Fetch source relations for this question
        $stmtQSources = $pdo->prepare("SELECT * FROM generated_question_sources WHERE question_id = ?");
        $stmtQSources->execute([$qId]);
        $qSources = $stmtQSources->fetchAll(PDO::FETCH_ASSOC);

        $qRelationCount = count($qSources);

        if ($qRelationCount === 0) {
            echo json_encode(['success' => false, 'error' => "Question ID '{$qId}' has zero source relations in generated_question_sources"]);
            exit(1);
        }

        $qExactLessonIds = [];
        $qPeriods = [];

        foreach ($qSources as $qs) {
            $lId = intval($qs['lesson_id'] ?? $qs['source_lesson_id'] ?? 0);
            
            // Check for duplicate question-lesson pair
            $pairKey = "{$qId}_{$lId}";
            if (isset($seenQuestionLessonPairs[$pairKey])) {
                echo json_encode(['success' => false, 'error' => "Duplicate question-lesson source relation detected for question '{$qId}' and lesson '{$lId}'"]);
                exit(1);
            }
            $seenQuestionLessonPairs[$pairKey] = true;

            // Check if source lesson belongs to batch selected lessons
            if (!empty($batchLessonIds) && !in_array($lId, $batchLessonIds, true)) {
                echo json_encode(['success' => false, 'error' => "Unexpected source lesson ID '{$lId}' for question '{$qId}' not in batch selected lessons"]);
                exit(1);
            }

            $qExactLessonIds[] = $lId;
            if (!empty($qs['academic_period'])) {
                $qPeriods[] = $qs['academic_period'];
            }
        }

        $mainLessonId = intval($q['lesson_id'] ?? $q['source_lesson_id'] ?? 0);

        $detailedQuestions[] = [
            'id' => $qId,
            'question' => $q['question_text'] ?? $q['question'] ?? null,
            'type' => $q['question_type'] ?? $q['type'] ?? null,
            'points' => intval($q['points'] ?? 1),
            'source_topic' => $q['topic'] ?? $q['source_topic'] ?? null,
            'source_lesson_id' => $mainLessonId,
            'source_relations_count' => $qRelationCount,
            'exact_source_lesson_ids' => array_values(array_unique($qExactLessonIds)),
            'source_academic_periods' => array_values(array_unique($qPeriods)),
            'is_review_required' => empty($mainLessonId) ? 1 : 0,
            'source_verified_by' => $qSources[0]['source_verified_by'] ?? null,
            'source_verified_at' => $qSources[0]['source_verified_at'] ?? null
        ];
    }

    $responseBatch = [
        'generation_batch_id' => $batch['generation_batch_id'],
        'teacher_id' => intval($batch['teacher_id']),
        'selected_lesson_ids' => $batch['selected_lesson_ids'],
        'selected_periods' => $batch['selected_periods'],
        'selected_subject' => $batch['selected_subject'],
        'semester' => $batch['semester'],
        'school_year' => $batch['school_year'],
        'year_level' => $batch['year_level'],
        'program' => $batch['program'],
        'batch_status' => $batch['batch_status'],
        'requested_question_count' => intval($batch['requested_question_count']),
        'generated_question_count' => intval($batch['generated_question_count']),
        'failed_question_count' => intval($batch['failed_question_count']),
        'source_lesson_count' => intval($batch['source_lesson_count'] ?? 0),
        'batch_consumed_at' => $batch['batch_consumed_at'],
        'batch_consumed_by' => intval($batch['batch_consumed_by']),
        'saved_exam_id' => intval($batch['saved_exam_id']),
        'teacher_acknowledged_by' => !empty($batch['teacher_acknowledged_by']) ? intval($batch['teacher_acknowledged_by']) : null,
        'teacher_acknowledged_at' => $batch['teacher_acknowledged_at'] ?? null,
        'acknowledgement_reason' => $batch['acknowledgement_reason'] ?? null,
        'acknowledgement_token_hash' => $batch['acknowledgement_token_hash'] ?? null,
        'failed_chunk_count' => intval($batch['failed_chunk_count'] ?? 0),
        'affected_lesson_ids' => json_decode($batch['affected_lesson_ids'] ?? '[]', true) ?: [],
        'affected_periods' => json_decode($batch['affected_periods'] ?? '[]', true) ?: [],
        'failure_messages' => json_decode($batch['failure_messages'] ?? '[]', true) ?: [],
        'chunk_generation_results' => json_decode($batch['chunk_generation_results'] ?? '[]', true) ?: [],
        'questions_per_lesson' => json_decode($batch['questions_per_lesson'] ?? '{}', true) ?: [],
        'questions_per_period' => json_decode($batch['questions_per_period'] ?? '{}', true) ?: [],
        'uncovered_lesson_ids' => json_decode($batch['uncovered_lesson_ids'] ?? '[]', true) ?: [],
        'uncovered_periods' => json_decode($batch['uncovered_periods'] ?? '[]', true) ?: [],
        'refill_attempt_count' => intval($batch['refill_attempt_count'] ?? 0),
        'refill_generated_count' => intval($batch['refill_generated_count'] ?? 0),
        'refill_warnings' => json_decode($batch['refill_warnings'] ?? '[]', true) ?: [],
        'simulated_scenario' => $batch['simulated_scenario'] ?? null,
        'simulated_test_scenario' => $batch['simulated_scenario'] ?? null,
        'failed_chunk_index' => isset($batch['failed_chunk_index']) ? intval($batch['failed_chunk_index']) : null,
        'refill_target_chunk_index' => isset($batch['refill_target_chunk_index']) ? intval($batch['refill_target_chunk_index']) : null,
        'refill_target_lesson_ids' => json_decode($batch['refill_target_lesson_ids'] ?? '[]', true) ?: [],
        'refill_target_periods' => json_decode($batch['refill_target_periods'] ?? '[]', true) ?: [],
        'initial_questions_per_lesson' => json_decode($batch['initial_questions_per_lesson'] ?? '{}', true) ?: [],
        'initial_questions_per_period' => json_decode($batch['initial_questions_per_period'] ?? '{}', true) ?: [],
        'initial_uncovered_lesson_ids' => json_decode($batch['initial_uncovered_lesson_ids'] ?? '[]', true) ?: [],
        'initial_uncovered_periods' => json_decode($batch['initial_uncovered_periods'] ?? '[]', true) ?: [],
        'final_questions_per_lesson' => json_decode($batch['questions_per_lesson'] ?? '{}', true) ?: [],
        'final_questions_per_period' => json_decode($batch['questions_per_period'] ?? '{}', true) ?: []
    ];

    $responseExam = [
        'id' => intval($exam['id']),
        'title' => $exam['title'],
        'subject' => $exam['subject'],
        'covered_periods' => $exam['covered_periods'],
        'source_lesson_count' => intval($exam['source_lesson_count']),
        'generation_source_type' => $exam['generation_source_type'],
        'generation_batch_id' => $exam['generation_batch_id'],
        'total_items' => intval($exam['total_items'])
    ];

    echo json_encode([
        'success' => true,
        'batch' => $responseBatch,
        'exam' => $responseExam,
        'questions_count' => count($questions),
        'questions' => $detailedQuestions,
        'sources_count' => count($sources),
        'sources' => $sources
    ]);
    exit(0);
}

if ($action === 'get_batch') {
    $batchId = $argv[2] ?? '';
    if (empty($batchId)) {
        echo json_encode(['success' => false, 'error' => 'Batch ID parameter missing']);
        exit(1);
    }
    $stmtBatch = $pdo->prepare("SELECT * FROM ai_generation_batches WHERE generation_batch_id = ?");
    $stmtBatch->execute([$batchId]);
    $batch = $stmtBatch->fetch(PDO::FETCH_ASSOC);
    if (!$batch) {
        echo json_encode(['success' => false, 'error' => "Batch '{$batchId}' not found"]);
        exit(1);
    }
    $responseBatch = [
        'generation_batch_id' => $batch['generation_batch_id'],
        'teacher_id' => intval($batch['teacher_id']),
        'batch_status' => $batch['batch_status'],
        'requested_question_count' => intval($batch['requested_question_count']),
        'generated_question_count' => intval($batch['generated_question_count']),
        'failed_question_count' => intval($batch['failed_question_count']),
        'failed_chunk_count' => intval($batch['failed_chunk_count'] ?? 0),
        'affected_lesson_ids' => json_decode($batch['affected_lesson_ids'] ?? '[]', true) ?: [],
        'affected_periods' => json_decode($batch['affected_periods'] ?? '[]', true) ?: [],
        'failure_messages' => json_decode($batch['failure_messages'] ?? '[]', true) ?: [],
        'simulated_scenario' => $batch['simulated_scenario'] ?? null,
        'failed_chunk_index' => isset($batch['failed_chunk_index']) ? intval($batch['failed_chunk_index']) : null,
        'refill_target_chunk_index' => isset($batch['refill_target_chunk_index']) ? intval($batch['refill_target_chunk_index']) : null,
        'refill_target_lesson_ids' => json_decode($batch['refill_target_lesson_ids'] ?? '[]', true) ?: [],
        'refill_target_periods' => json_decode($batch['refill_target_periods'] ?? '[]', true) ?: [],
        'initial_questions_per_lesson' => json_decode($batch['initial_questions_per_lesson'] ?? '{}', true) ?: [],
        'initial_questions_per_period' => json_decode($batch['initial_questions_per_period'] ?? '{}', true) ?: [],
        'initial_uncovered_lesson_ids' => json_decode($batch['initial_uncovered_lesson_ids'] ?? '[]', true) ?: [],
        'initial_uncovered_periods' => json_decode($batch['initial_uncovered_periods'] ?? '[]', true) ?: [],
        'final_questions_per_lesson' => json_decode($batch['questions_per_lesson'] ?? '{}', true) ?: [],
        'final_questions_per_period' => json_decode($batch['questions_per_period'] ?? '{}', true) ?: [],
        'refill_attempt_count' => intval($batch['refill_attempt_count'] ?? 0),
        'refill_generated_count' => intval($batch['refill_generated_count'] ?? 0),
        'uncovered_periods' => json_decode($batch['uncovered_periods'] ?? '[]', true) ?: [],
        'batch_consumed_at' => $batch['batch_consumed_at']
    ];
    echo json_encode(['success' => true, 'batch' => $responseBatch]);
    exit(0);
}

if ($action === 'get_uploaded_lessons') {
    $identifier = $argv[2] ?? '';
    if (numeric_check($identifier)) {
        $stmt = $pdo->prepare("SELECT id, title, academic_period, lesson_text, processing_status, subject, teacher_id FROM lesson_materials WHERE teacher_id = ? ORDER BY id DESC");
        $stmt->execute([intval($identifier)]);
    } else {
        $stmt = $pdo->prepare("
            SELECT lm.id, lm.title, lm.academic_period, lm.lesson_text, lm.processing_status, lm.subject, lm.teacher_id 
            FROM lesson_materials lm
            JOIN users u ON lm.teacher_id = u.id
            WHERE u.username = ? OR u.email = ?
            ORDER BY lm.id DESC
        ");
        $stmt->execute([$identifier, $identifier]);
    }
    
    $lessons = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode(['success' => true, 'lessons' => $lessons]);
    exit(0);
}

if ($action === 'get_teacher_id') {
    $username = $argv[2] ?? 'russel';
    $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
    $stmt->execute([$username, $username]);
    $tid = $stmt->fetchColumn();
    echo json_encode(['success' => true, 'teacher_id' => intval($tid)]);
    exit(0);
}

function numeric_check($val) {
    return is_numeric($val);
}

echo json_encode(['success' => false, 'error' => 'Unknown action']);
exit(1);
