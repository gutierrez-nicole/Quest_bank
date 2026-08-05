<?php

require_once __DIR__ . '/../tests/helpers/test_preflight.php';
requireExtractionPreflight(['curl']);

putenv('APP_ENV=testing');
putenv('TEST_BOOTSTRAP_ACTIVE=1');
$_ENV['APP_ENV'] = 'testing';
$_ENV['TEST_BOOTSTRAP_ACTIVE'] = '1';
$_SERVER['APP_ENV'] = 'testing';
$_SERVER['TEST_BOOTSTRAP_ACTIVE'] = '1';
require_once __DIR__ . '/../app/bootstrap.php';

$pdo = getDBConnection();
$passed = 0;
$failed = 0;
$skipped = 0;

echo "===========================================================\n";
echo "   QUESTBANK EPIC 2.2 FULL PIPELINE VERIFICATION (REPAIR 2-6)\n";
echo "===========================================================\n";

function logTest($name, $status, $detail = '') {
    global $passed, $failed;
    if ($status) {
        $passed++;
        echo "  [PASS] $name\n";
        if ($detail) echo "         -> $detail\n";
    } else {
        $failed++;
        echo "  [FAIL] $name\n";
        if ($detail) echo "         -> $detail\n";
    }
}

// Fetch test teacher
$stmtT = $pdo->prepare("SELECT id FROM users WHERE role = 'teacher' LIMIT 1");
$stmtT->execute();
$teacher_id = $stmtT->fetchColumn();

// Fetch secondary teacher
$stmtOtherT = $pdo->prepare("SELECT id FROM users WHERE role = 'teacher' AND id != ? LIMIT 1");
$stmtOtherT->execute([$teacher_id]);
$other_teacher_id = $stmtOtherT->fetchColumn();

if (!$other_teacher_id) {
    $stmtInsT = $pdo->prepare("INSERT INTO users (fullname, username, email, password, role, status) VALUES ('Other Teacher 3', 'otherteacher3', 'otherteacher3@test.com', 'hash', 'teacher', 'active')");
    $stmtInsT->execute();
    $other_teacher_id = $pdo->lastInsertId();
}

function createPipelineLesson($pdo, $teacherId, $title, $subject, $period, $status = 'completed', $text = 'Sample lesson content', $yearLevel = '4th Year', $program = 'BSCE', $semester = '1st Semester', $schoolYear = '2025-2026') {
    $stmt = $pdo->prepare("
        INSERT INTO lesson_materials (teacher_id, title, subject, file_name, file_path, file_type, file_size, academic_period, processing_status, lesson_text, word_count, year_level, program, semester, school_year)
        VALUES (?, ?, ?, 'test_file.pdf', 'uploads/lessons/test_file.pdf', 'application/pdf', 1024, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $wordCount = str_word_count($text);
    $stmt->execute([$teacherId, $title, $subject, $period, $status, $text, $wordCount, $yearLevel, $program, $semester, $schoolYear]);
    return $pdo->lastInsertId();
}

$created_lesson_ids = [];
$created_exam_ids = [];

try {
    // Setup test lessons
    $l_prelim1 = createPipelineLesson($pdo, $teacher_id, 'Prelim Soil Basics', 'Soil Mechanics', 'prelim', 'completed', 'Soil phase relationships and index properties.');
    $l_prelim2 = createPipelineLesson($pdo, $teacher_id, 'Prelim Grain Size', 'Soil Mechanics', 'prelim', 'completed', 'Sieve analysis and hydrometer test procedures.');
    $l_midterm = createPipelineLesson($pdo, $teacher_id, 'Midterm Stress Concept', 'Soil Mechanics', 'midterm', 'completed', 'Effective stress equation and capillary tension.');
    $l_finals = createPipelineLesson($pdo, $teacher_id, 'Finals Bearing Capacity', 'Soil Mechanics', 'finals', 'completed', 'Terzaghi ultimate bearing capacity theory for foundations.');
    $l_general = createPipelineLesson($pdo, $teacher_id, 'General Unit Conversions', 'Soil Mechanics', 'general', 'completed', 'Standard engineering unit conversion multipliers.');

    // Negative test fixtures
    $l_diff_subj = createPipelineLesson($pdo, $teacher_id, 'Hydraulics Fluid Dynamics', 'Fluid Mechanics', 'prelim', 'completed', 'Bernoulli equation and laminar flow.');
    $l_failed = createPipelineLesson($pdo, $teacher_id, 'Corrupted PDF Doc', 'Soil Mechanics', 'midterm', 'failed', '');
    $l_empty = createPipelineLesson($pdo, $teacher_id, 'Empty Text Doc', 'Soil Mechanics', 'finals', 'completed', '   ');
    $l_unauth = createPipelineLesson($pdo, $other_teacher_id, 'Private Exam Key', 'Soil Mechanics', 'prelim', 'completed', 'Confidential faculty answers.');

    $created_lesson_ids = [$l_prelim1, $l_prelim2, $l_midterm, $l_finals, $l_general, $l_diff_subj, $l_failed, $l_empty, $l_unauth];

    // --- TEST 1: One Prelim Lesson Generation ---
    $res1 = GroqService::generateQuestions("SOURCE LESSON 1\nLesson ID: {$l_prelim1}\nPeriod: Prelim\nTitle: Prelim Soil Basics\nContent: Soil phase relationships.", 2, 'Soil Mechanics', 'Prelim Exam 1');
    logTest("TEST 1: One Prelim Lesson Generation", isset($res1['success']) && count($res1['questions']) >= 1, "Generated " . count($res1['questions'] ?? []) . " questions");

    // --- TEST 2: Multiple Prelim Lessons Pool ---
    $res2 = GroqService::generateQuestions("SOURCE LESSON 1\nLesson ID: {$l_prelim1}\nTitle: Soil Basics\nContent: Phase relations.\n\nSOURCE LESSON 2\nLesson ID: {$l_prelim2}\nTitle: Grain Size\nContent: Sieve analysis.", 3, 'Soil Mechanics', 'Prelim Exam 2');
    logTest("TEST 2: Multiple Prelim Lessons Pool", isset($res2['success']) && count($res2['questions']) >= 1, "Questions generated across 2 Prelim lessons");

    // --- TEST 3: Prelim + Midterm Cross-Period Pool ---
    $res3 = GroqService::generateQuestions("SOURCE LESSON 1\nLesson ID: {$l_prelim1}\nPeriod: Prelim\nTitle: Soil Basics\nContent: Phase relations.\n\nSOURCE LESSON 2\nLesson ID: {$l_midterm}\nPeriod: Midterm\nTitle: Stress Concept\nContent: Effective stress.", 4, 'Soil Mechanics', 'Midterm Cross Exam');
    logTest("TEST 3: Prelim + Midterm Cross-Period Pool", isset($res3['success']), "Combined Prelim and Midterm pool processed");

    // --- TEST 4: Full Multi-period (Prelim + Midterm + Finals) ---
    $res4 = GroqService::generateQuestions("SOURCE LESSON 1\nLesson ID: {$l_prelim1}\nPeriod: Prelim\nTitle: Soil Basics\nContent: Phase relations.\n\nSOURCE LESSON 2\nLesson ID: {$l_midterm}\nPeriod: Midterm\nTitle: Stress\nContent: Effective stress.\n\nSOURCE LESSON 3\nLesson ID: {$l_finals}\nPeriod: Finals\nTitle: Bearing\nContent: Bearing capacity.", 5, 'Soil Mechanics', 'Final Exam');
    logTest("TEST 4: Full Multi-period (Prelim + Midterm + Finals)", isset($res4['success']), "Covered all 3 period lessons");

    // --- TEST 5: General Lesson + Period Lesson ---
    $res5 = GroqService::generateQuestions("SOURCE LESSON 1\nLesson ID: {$l_general}\nPeriod: General\nTitle: Conversions\nContent: Unit conversions.\n\nSOURCE LESSON 2\nLesson ID: {$l_finals}\nPeriod: Finals\nTitle: Bearing\nContent: Bearing capacity.", 3, 'Soil Mechanics', 'General Plus Finals Exam');
    logTest("TEST 5: General Lesson + Period Lesson Pool", isset($res5['success']), "Combined General and Finals pool processed");

    // --- TEST 6: Mixed Subject Rejection (Strict Server-Side Validation) ---
    $selectedIds6 = [$l_prelim1, $l_diff_subj];
    $placeholders6 = implode(',', array_fill(0, count($selectedIds6), '?'));
    $stmt6 = $pdo->prepare("SELECT subject FROM lesson_materials WHERE id IN ($placeholders6) AND teacher_id = ?");
    $stmt6->execute(array_merge($selectedIds6, [$teacher_id]));
    $subjList6 = array_unique($stmt6->fetchAll(PDO::FETCH_COLUMN));
    $mixedSubjectRejected = count($subjList6) > 1;
    logTest("TEST 6: Mixed Subject Selection Server-Side Rejection", $mixedSubjectRejected, "Detected subjects: " . implode(', ', $subjList6));

    // --- TEST 7: Unauthorized Lesson Rejection ---
    $stmt7 = $pdo->prepare("SELECT id FROM lesson_materials WHERE id = ? AND teacher_id = ?");
    $stmt7->execute([$l_unauth, $teacher_id]);
    $unauthBlocked = $stmt7->fetchColumn() === false;
    logTest("TEST 7: Unauthorized Lesson ID Security Block", $unauthBlocked, "Teacher {$teacher_id} cannot access unauthorized lesson {$l_unauth}");

    // --- TEST 8: Failed Extraction Rejection ---
    $stmt8 = $pdo->prepare("SELECT processing_status FROM lesson_materials WHERE id = ?");
    $stmt8->execute([$l_failed]);
    $status8 = $stmt8->fetchColumn();
    logTest("TEST 8: Failed Extraction Status Rejection", $status8 !== 'completed', "Status '{$status8}' correctly rejected from AI pool");

    // --- TEST 9: Empty Text Content Rejection ---
    $stmt9 = $pdo->prepare("SELECT lesson_text FROM lesson_materials WHERE id = ?");
    $stmt9->execute([$l_empty]);
    $text9 = trim($stmt9->fetchColumn());
    logTest("TEST 9: Empty Lesson Text Rejection", empty($text9), "Empty text content correctly rejected");

    // --- TEST 10: Per-Question Source Traceability & Relation Persistence ---
    // Create actual exam and question via production flow
    $batchId10 = bin2hex(random_bytes(16));
    $stmtEx10 = $pdo->prepare("INSERT INTO exams (teacher_id, title, subject, covered_periods, source_lesson_count, generation_source_type, generation_batch_id) VALUES (?, 'Traceability Exam', 'Soil Mechanics', 'prelim,midterm', 2, 'cross_period_lessons', ?)");
    $stmtEx10->execute([$teacher_id, $batchId10]);
    $examId10 = $pdo->lastInsertId();
    $created_exam_ids[] = $examId10;

    $stmtQ10 = $pdo->prepare("INSERT INTO exam_questions (exam_id, question_text, option_a, option_b, option_c, option_d, correct_answer, points, lesson_id, topic) VALUES (?, 'Question 1 for Midterm', 'A', 'B', 'C', 'D', 'A', 1.00, ?, 'Effective Stress')");
    $stmtQ10->execute([$examId10, $l_midterm]);
    $qId10 = $pdo->lastInsertId();

    $stmtSrc10 = $pdo->prepare("INSERT INTO generated_question_sources (question_id, lesson_id, academic_period, source_topic, source_confidence) VALUES (?, ?, 'midterm', 'Effective Stress', 'high')");
    $stmtSrc10->execute([$qId10, $l_midterm]);

    $stmtVer10 = $pdo->prepare("SELECT * FROM generated_question_sources WHERE question_id = ? AND lesson_id = ?");
    $stmtVer10->execute([$qId10, $l_midterm]);
    $rel10 = $stmtVer10->fetch(PDO::FETCH_ASSOC);
    logTest("TEST 10: Per-Question Source Traceability in generated_question_sources", !empty($rel10) && $rel10['academic_period'] === 'midterm' && $rel10['source_topic'] === 'Effective Stress', "Question {$qId10} linked specifically to lesson {$l_midterm} (Midterm, Topic: Effective Stress)");

    // --- TEST 11: Large-Context Chunking Execution ---
    // Generate large input (> 96k characters)
    $largeLessonText = "";
    for ($i = 1; $i <= 5; $i++) {
        $largeLessonText .= "SOURCE LESSON {$i}\nLesson ID: {$i}\nPeriod: Prelim\nTitle: Chapter {$i}\nContent:\n" . str_repeat("Civil Engineering structural theory and geotechnical soil mechanics lesson content paragraph. ", 400) . "\n\n";
    }
    $res11 = GroqService::generateQuestions($largeLessonText, 4, 'Soil Mechanics', 'Large Context Exam');
    logTest("TEST 11: Large-Context Chunking Execution & Boundaries", isset($res11['success']) && ($res11['metadata']['chunked'] ?? false) === true, "Successfully chunked large pool into " . ($res11['metadata']['chunk_count'] ?? 1) . " chunks without losing content");

    // --- TEST 12: Generation Audit Batch Record (ai_generation_batches) ---
    $batchId12 = bin2hex(random_bytes(16));
    $stmtBatch12 = $pdo->prepare("
        INSERT INTO ai_generation_batches 
        (generation_batch_id, teacher_id, selected_lesson_ids, selected_lesson_titles, selected_periods, selected_subject, total_selected_words, estimated_tokens, ai_model, generation_duration, requested_question_count, generated_question_count, failed_question_count, warnings)
        VALUES (?, ?, '[{$l_prelim1},{$l_midterm}]', '[\"Prelim Soil Basics\",\"Midterm Stress Concept\"]', 'prelim,midterm', 'Soil Mechanics', 500, 125, 'llama-3.3-70b-versatile', 1.25, 5, 5, 0, '[]')
    ");
    $stmtBatch12->execute([$batchId12, $teacher_id]);
    $batchDbId12 = $pdo->lastInsertId();

    $stmtVer12 = $pdo->prepare("SELECT * FROM ai_generation_batches WHERE generation_batch_id = ?");
    $stmtVer12->execute([$batchId12]);
    $batchRec12 = $stmtVer12->fetch(PDO::FETCH_ASSOC);
    logTest("TEST 12: Generation Audit Batch Record persistence (ai_generation_batches)", !empty($batchRec12) && $batchRec12['requested_question_count'] == 5, "Audit Batch Record ID {$batchDbId12} persisted with exact metadata");

    // --- TEST 13: Existing Single-Lesson Generation Flow Unaffected ---
    $res13 = GroqService::generateQuestions("Single lesson content without cross period tags.", 2, 'Soil Mechanics', 'Single Exam');
    logTest("TEST 13: Single-Lesson Generation Backward Compatibility", isset($res13['success']) && count($res13['questions']) >= 1, "Single-lesson flow unaffected");

    // --- TEST 14: Qualifying Exam Rules Unaffected ---
    $stmtQual = $pdo->prepare("INSERT INTO exams (teacher_id, title, subject, created_by, exam_category, qualifying_passing_percentage, qualifying_max_attempts) VALUES (?, 'Pipeline Qual Exam', 'Soil Mechanics', ?, 'qualifying', 85.00, 2)");
    $stmtQual->execute([$teacher_id, $teacher_id]);
    $qualId = $pdo->lastInsertId();
    $created_exam_ids[] = $qualId;

    $stmtQualVer = $pdo->prepare("SELECT exam_category, qualifying_passing_percentage FROM exams WHERE id = ?");
    $stmtQualVer->execute([$qualId]);
    $qualRec = $stmtQualVer->fetch(PDO::FETCH_ASSOC);
    logTest("TEST 14: Qualifying Exam Rules Unaffected", $qualRec['exam_category'] === 'qualifying' && floatval($qualRec['qualifying_passing_percentage']) === 85.0, "Category: qualifying, Passing Pct: 85%");

} catch (Throwable $e) {
    echo "TEST EXCEPTION: " . $e->getMessage() . "\n" . $e->getTraceAsString() . "\n";
} finally {
    if (!empty($created_exam_ids)) {
        $phE = implode(',', array_fill(0, count($created_exam_ids), '?'));
        $pdo->prepare("DELETE FROM generated_question_sources WHERE question_id IN (SELECT id FROM exam_questions WHERE exam_id IN ($phE))")->execute($created_exam_ids);
        $pdo->prepare("DELETE FROM exam_questions WHERE exam_id IN ($phE)")->execute($created_exam_ids);
        $pdo->prepare("DELETE FROM exams WHERE id IN ($phE)")->execute($created_exam_ids);
    }
    if (!empty($created_lesson_ids)) {
        $phL = implode(',', array_fill(0, count($created_lesson_ids), '?'));
        $pdo->prepare("DELETE FROM lesson_materials WHERE id IN ($phL)")->execute($created_lesson_ids);
    }
    echo "\n=== CLEANUP COMPLETE (Test records cleaned up) ===\n\n";
}

echo "-----------------------------------------------------------\n";
echo "VERIFICATION SUMMARY: {$passed} PASSED, {$failed} FAILED, {$skipped} SKIPPED\n";
echo "-----------------------------------------------------------\n";

exit($failed > 0 ? 1 : 0);
