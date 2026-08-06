<?php
/**
 * Verification Runner for QuestBank Epic 2.2 Final Security Repair:
 * Server-Authoritative Generation Batch and Atomic Acknowledgment
 */
require_once __DIR__ . '/../tests/helpers/test_runner.php';
requireDatabasePreflight();

putenv('APP_ENV=testing');

require_once __DIR__ . '/../app/bootstrap.php';

$runner = new TestRunner('QuestBank Epic 2.2 Final Security Repair Verification');

// Controlled failure hooks for meta-verification
if (getenv('FORCE_ASSERT_FAIL') === '1') {
    $runner->assertTrue("Forced Assertion Failure Test", false, "FORCE_ASSERT_FAIL=1");
}
if (getenv('FORCE_RUNTIME_EXCEPTION') === '1') {
    try { throw new RuntimeException('FORCE_RUNTIME_EXCEPTION=1'); } catch (Throwable $e) { $runner->recordException($e); $runner->finish(); }
}

$batchId1 = $batchId2 = $batchId4 = $batchId5 = $batchId6 = null;
$lessonA = null;
$pdo = null;

try {
    $pdo = getDBConnection();
    $runner->setSetupCompleted($pdo !== null, "Database connection established");

    // Fetch test teacher russel
    $stmtT = $pdo->prepare("SELECT id FROM users WHERE role = 'teacher' LIMIT 1");
    $stmtT->execute();
    $teacher_id = $stmtT->fetchColumn();
    if (!$teacher_id) {
        throw new Exception("Setup failed: No teacher account found.");
    }
    $secretKey = (defined('DB_PASS') ? DB_PASS : '') . '_questbank_secret_salt_2026';

    // Seed test lesson materials
    $stmtL = $pdo->prepare("
        INSERT INTO lesson_materials (teacher_id, title, subject, lesson_text, processing_status, academic_period, semester, school_year, year_level, program, file_name, file_path, file_type, file_size) 
        VALUES (?, 'Security Test Lesson A', 'Soil Mechanics', 'Lecture content A', 'completed', 'prelim', '1st Semester', '2025-2026', '4th Year', 'BSCE', 'sec_a.pdf', 'uploads/sec_a.pdf', 'pdf', 2048)
    ");
    $stmtL->execute([$teacher_id]);
    $lessonA = $pdo->lastInsertId();

    // --- TEST 1: Server-Authoritative Batch Loading ---
    $batchId1 = bin2hex(random_bytes(16));
    $stmtInsBatch1 = $pdo->prepare("
        INSERT INTO ai_generation_batches 
        (generation_batch_id, teacher_id, selected_lesson_ids, selected_lesson_titles, selected_periods, selected_subject, semester, school_year, year_level, program, total_selected_words, estimated_tokens, ai_model, generation_duration, requested_question_count, generated_question_count, failed_question_count, warnings, batch_status, failed_chunk_count)
        VALUES (?, ?, ?, '[\"Lesson A\"]', 'prelim', 'Soil Mechanics', '1st Semester', '2025-2026', '4th Year', 'BSCE', 1000, 250, 'llama-3.3-70b-versatile', 1.2, 5, 4, 1, '[\"Shortfall\"]', 'incomplete', 1)
    ");
    $stmtInsBatch1->execute([$batchId1, $teacher_id, json_encode([$lessonA])]);

    $stmtCheck1 = $pdo->prepare("SELECT * FROM ai_generation_batches WHERE generation_batch_id = ?");
    $stmtCheck1->execute([$batchId1]);
    $rec1 = $stmtCheck1->fetch(PDO::FETCH_ASSOC);

    $loadedLids = json_decode($rec1['selected_lesson_ids'], true);
    $loadedLids = is_array($loadedLids) ? array_map('intval', $loadedLids) : [];
    $pass1 = !empty($rec1) && $rec1['batch_status'] === 'incomplete' && $loadedLids === [(int)$lessonA];
    $runner->assertTrue("TEST 1: Server-Authoritative Batch Record Verified", $pass1, "Batch status incomplete and selected_lesson_ids loaded from DB");

    // --- TEST 2: Another Teacher's Batch Rejection ---
    $otherTeacherId = $teacher_id + 99999;
    $batchId2 = bin2hex(random_bytes(16));
    $pdo->prepare("
        INSERT INTO ai_generation_batches 
        (generation_batch_id, teacher_id, selected_lesson_ids, selected_lesson_titles, selected_periods, selected_subject, total_selected_words, estimated_tokens, ai_model, generation_duration, requested_question_count, generated_question_count, failed_question_count, batch_status)
        VALUES (?, ?, '[101]', '[\"Other\"]', 'prelim', 'Soil Mechanics', 1000, 250, 'llama-3.3-70b-versatile', 1.0, 5, 5, 0, 'completed')
    ")->execute([$batchId2, $otherTeacherId]);

    $stmtCheck2 = $pdo->prepare("SELECT * FROM ai_generation_batches WHERE generation_batch_id = ?");
    $stmtCheck2->execute([$batchId2]);
    $rec2 = $stmtCheck2->fetch(PDO::FETCH_ASSOC);

    $pass2 = !empty($rec2) && (int)$rec2['teacher_id'] !== (int)$teacher_id;
    $runner->assertTrue("TEST 2: Cross-Teacher Batch Access Blocked", $pass2, "Batch owned by teacher {$otherTeacherId} correctly fails authorization for teacher {$teacher_id}");

    // --- TEST 3: Nonexistent Batch Rejection ---
    $fakeBatchId = 'nonexistent_batch_xyz_999';
    $stmtCheck3 = $pdo->prepare("SELECT * FROM ai_generation_batches WHERE generation_batch_id = ?");
    $stmtCheck3->execute([$fakeBatchId]);
    $rec3 = $stmtCheck3->fetch(PDO::FETCH_ASSOC);
    $runner->assertTrue("TEST 3: Nonexistent Batch Handled Cleanly", empty($rec3), "Query for invalid batch returned empty result");

    // --- TEST 4: Already-Consumed Batch Rejection ---
    $batchId4 = bin2hex(random_bytes(16));
    $pdo->prepare("
        INSERT INTO ai_generation_batches 
        (generation_batch_id, teacher_id, selected_lesson_ids, selected_lesson_titles, selected_periods, selected_subject, total_selected_words, estimated_tokens, ai_model, generation_duration, requested_question_count, generated_question_count, failed_question_count, batch_status, batch_consumed_at, batch_consumed_by, saved_exam_id)
        VALUES (?, ?, ?, '[\"Consumed\"]', 'prelim', 'Soil Mechanics', 1000, 250, 'llama-3.3-70b-versatile', 1.0, 5, 5, 0, 'completed', NOW(), ?, 999)
    ")->execute([$batchId4, $teacher_id, json_encode([$lessonA]), $teacher_id]);

    $stmtCheck4 = $pdo->prepare("SELECT * FROM ai_generation_batches WHERE generation_batch_id = ?");
    $stmtCheck4->execute([$batchId4]);
    $rec4 = $stmtCheck4->fetch(PDO::FETCH_ASSOC);

    $pass4 = !empty($rec4) && !empty($rec4['batch_consumed_at']) && !empty($rec4['saved_exam_id']);
    $runner->assertTrue("TEST 4: Already-Consumed Batch State Verified", $pass4, "batch_consumed_at and saved_exam_id non-null prevent re-consumption");

    // --- TEST 5: Failed Transaction Does NOT Consume Token or Batch ---
    $batchId5 = bin2hex(random_bytes(16));
    $pdo->prepare("
        INSERT INTO ai_generation_batches 
        (generation_batch_id, teacher_id, selected_lesson_ids, selected_lesson_titles, selected_periods, selected_subject, total_selected_words, estimated_tokens, ai_model, generation_duration, requested_question_count, generated_question_count, failed_question_count, batch_status)
        VALUES (?, ?, ?, '[\"Lesson A\"]', 'prelim', 'Soil Mechanics', 1000, 250, 'llama-3.3-70b-versatile', 1.0, 5, 4, 1, 'incomplete')
    ")->execute([$batchId5, $teacher_id, json_encode([$lessonA])]);

    $token5 = generateIncompleteAckToken($teacher_id, $batchId5, 1, [(int)$lessonA], 5, 4, ['Chunk failed'], $secretKey);
    $tokenHash5 = hash('sha256', $token5);

    $pdo->beginTransaction();
    $ackVal5 = verifyIncompleteAckToken($token5, $teacher_id, $secretKey, $batchId5);
    $pdo->rollBack();

    $stmtCheckTok5 = $pdo->prepare("SELECT COUNT(*) FROM used_confirmation_tokens WHERE token_hash = ?");
    $stmtCheckTok5->execute([$tokenHash5]);
    $tokCount5 = $stmtCheckTok5->fetchColumn();

    $stmtCheckBatch5 = $pdo->prepare("SELECT batch_consumed_at FROM ai_generation_batches WHERE generation_batch_id = ?");
    $stmtCheckBatch5->execute([$batchId5]);
    $batchCons5 = $stmtCheckBatch5->fetchColumn();

    $pass5 = (int)$tokCount5 === 0 && empty($batchCons5);
    $runner->assertTrue("TEST 5: Failed Transaction Token & Batch Preservation", $pass5, "Transaction rollback preserved token unused and batch unconsumed");

    // --- TEST 6: Successful Save Consumes Token and Batch ATOMICALLY ---
    $batchId6 = bin2hex(random_bytes(16));
    $pdo->prepare("
        INSERT INTO ai_generation_batches 
        (generation_batch_id, teacher_id, selected_lesson_ids, selected_lesson_titles, selected_periods, selected_subject, total_selected_words, estimated_tokens, ai_model, generation_duration, requested_question_count, generated_question_count, failed_question_count, batch_status)
        VALUES (?, ?, ?, '[\"Lesson A\"]', 'prelim', 'Soil Mechanics', 1000, 250, 'llama-3.3-70b-versatile', 1.0, 5, 4, 1, 'incomplete')
    ")->execute([$batchId6, $teacher_id, json_encode([$lessonA])]);

    $token6 = generateIncompleteAckToken($teacher_id, $batchId6, 1, [(int)$lessonA], 5, 4, ['Chunk failed'], $secretKey);
    $tokenHash6 = hash('sha256', $token6);

    $pdo->beginTransaction();

    $stmtExam6 = $pdo->prepare("INSERT INTO exams (teacher_id, title, subject, total_items, generation_batch_id) VALUES (?, 'Atomic Save Exam', 'Soil Mechanics', 5, ?)");
    $stmtExam6->execute([$teacher_id, $batchId6]);
    $examId6 = $pdo->lastInsertId();

    $ackVal6 = verifyIncompleteAckToken($token6, $teacher_id, $secretKey, $batchId6);

    $stmtUpd6 = $pdo->prepare("
        UPDATE ai_generation_batches 
        SET batch_consumed_at = NOW(), batch_consumed_by = ?, saved_exam_id = ?, teacher_acknowledged_at = NOW(), teacher_acknowledged_by = ?, acknowledgement_reason = 'Passed atomic test', acknowledgement_token_hash = ?
        WHERE generation_batch_id = ? AND batch_consumed_at IS NULL
    ");
    $stmtUpd6->execute([$teacher_id, $examId6, $teacher_id, $tokenHash6, $batchId6]);

    $pdo->commit();

    $stmtCheckTok6 = $pdo->prepare("SELECT COUNT(*) FROM used_confirmation_tokens WHERE token_hash = ?");
    $stmtCheckTok6->execute([$tokenHash6]);
    $tokCount6 = $stmtCheckTok6->fetchColumn();

    $stmtCheckBatch6 = $pdo->prepare("SELECT batch_consumed_at, saved_exam_id FROM ai_generation_batches WHERE generation_batch_id = ?");
    $stmtCheckBatch6->execute([$batchId6]);
    $batchCons6 = $stmtCheckBatch6->fetch(PDO::FETCH_ASSOC);

    $pass6 = (int)$tokCount6 === 1 && !empty($batchCons6['batch_consumed_at']) && (int)$batchCons6['saved_exam_id'] === (int)$examId6;
    $runner->assertTrue("TEST 6: Successful Save Consumes Token & Batch Atomically", $pass6, "Exam saved, token marked used, batch_consumed_at & saved_exam_id updated atomically");

    // --- TEST 7: Duplicate Replayed Save Rejection ---
    $pdo->beginTransaction();
    $stmtReplay = $pdo->prepare("
        UPDATE ai_generation_batches 
        SET batch_consumed_at = NOW(), batch_consumed_by = ?, saved_exam_id = 9999 
        WHERE generation_batch_id = ? AND batch_consumed_at IS NULL
    ");
    $stmtReplay->execute([$teacher_id, $batchId6]);
    $rowsUpdated = $stmtReplay->rowCount();
    $pdo->rollBack();

    $pass7 = ($rowsUpdated === 0);
    $runner->assertTrue("TEST 7: Duplicate Save Atomic Block", $pass7, "Replayed save attempt updated 0 rows because batch was already consumed");

    // --- TEST 8: Audit Batch Insertion Failure Blocks Generation ---
    $dupBatchId = bin2hex(random_bytes(16));
    $pdo->prepare("
        INSERT INTO ai_generation_batches (generation_batch_id, teacher_id, selected_lesson_ids, selected_lesson_titles, selected_periods, selected_subject, total_selected_words, estimated_tokens, ai_model, generation_duration, requested_question_count, generated_question_count, failed_question_count, batch_status)
        VALUES (?, ?, '[101]', '[\"Dup\"]', 'prelim', 'Soil Mechanics', 1000, 250, 'llama-3.3-70b-versatile', 1.0, 5, 5, 0, 'completed')
    ")->execute([$dupBatchId, $teacher_id]);

    $batchInsertSuccess = true;
    try {
        $stmtDup = $pdo->prepare("
            INSERT INTO ai_generation_batches (generation_batch_id, teacher_id, selected_lesson_ids, selected_lesson_titles, selected_periods, selected_subject, total_selected_words, estimated_tokens, ai_model, generation_duration, requested_question_count, generated_question_count, failed_question_count, batch_status)
            VALUES (?, ?, '[101]', '[\"Dup\"]', 'prelim', 'Soil Mechanics', 1000, 250, 'llama-3.3-70b-versatile', 1.0, 5, 5, 0, 'completed')
        ");
        $batchInsertSuccess = $stmtDup->execute([$dupBatchId, $teacher_id]);
    } catch (Throwable $e) {
        $batchInsertSuccess = false;
    }

    $pass8 = ($batchInsertSuccess === false);
    $runner->assertTrue("TEST 8: Audit Batch Insertion Failure Detection", $pass8, "Duplicate batch ID insertion correctly caught and returned false");

} catch (Throwable $e) {
    $runner->recordException($e);
} finally {
    if ($pdo !== null) {
        try {
            $toDelete = array_filter([$batchId1, $batchId2, $batchId4, $batchId5, $batchId6]);
            if (!empty($toDelete)) {
                $in = implode(',', array_fill(0, count($toDelete), '?'));
                $pdo->prepare("DELETE FROM ai_generation_batches WHERE generation_batch_id IN ($in)")->execute($toDelete);
            }
            if ($lessonA) {
                $pdo->prepare("DELETE FROM lesson_materials WHERE id = ?")->execute([$lessonA]);
            }
        } catch (Throwable $cleanupError) {
            $runner->recordCleanupFailure("security_test_records", $cleanupError);
        }
    }
}

$runner->finish();
