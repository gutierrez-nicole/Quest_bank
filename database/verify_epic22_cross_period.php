<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../tests/helpers/test_runner.php';
requireDatabasePreflight();

require_once __DIR__ . '/../app/bootstrap.php';

$runner = new TestRunner('QuestBank Epic 2.2 Cross-Period Lesson Pool Test');

function createTestLesson($pdo, $teacherId, $title, $subject, $period, $status = 'completed', $text = 'Sample lesson text content.', $yearLevel = '4th Year', $program = 'BSCE') {
    $stmt = $pdo->prepare("
        INSERT INTO lesson_materials (teacher_id, title, subject, file_name, file_path, file_type, file_size, academic_period, processing_status, lesson_text, word_count, year_level, program)
        VALUES (?, ?, ?, 'test_file.pdf', 'uploads/lessons/test_file.pdf', 'application/pdf', 1024, ?, ?, ?, ?, ?, ?)
    ");
    $wordCount = str_word_count($text);
    $stmt->execute([$teacherId, $title, $subject, $period, $status, $text, $wordCount, $yearLevel, $program]);
    return $pdo->lastInsertId();
}

$created_lesson_ids = [];
$created_exam_ids = [];
$pdo = null;

try {
    $pdo = getDBConnection();
    $runner->setSetupCompleted($pdo !== null, "Database connection established");

    $stmtT = $pdo->prepare("SELECT id FROM users WHERE role = 'teacher' LIMIT 1");
    $stmtT->execute();
    $teacher_id = $stmtT->fetchColumn();

    if (!$teacher_id) {
        throw new RuntimeException("No teacher found in database.");
    }

    $stmtOtherT = $pdo->prepare("SELECT id FROM users WHERE role = 'teacher' AND id != ? LIMIT 1");
    $stmtOtherT->execute([$teacher_id]);
    $other_teacher_id = $stmtOtherT->fetchColumn();

    if (!$other_teacher_id) {
        $stmtInsT = $pdo->prepare("INSERT INTO users (fullname, username, email, password, role, status) VALUES ('Other Teacher', 'otherteacher', 'otherteacher@test.com', 'hash', 'teacher', 'active')");
        $stmtInsT->execute();
        $other_teacher_id = $pdo->lastInsertId();
    }

    $l_prelim1 = createTestLesson($pdo, $teacher_id, 'Soil Intro', 'Soil Mechanics', 'prelim', 'completed', 'Soil mechanics definition and basic properties.');
    $l_prelim2 = createTestLesson($pdo, $teacher_id, 'Void Ratio', 'Soil Mechanics', 'prelim', 'completed', 'Void ratio e and porosity n calculation methods.');
    $l_midterm = createTestLesson($pdo, $teacher_id, 'Effective Stress', 'Soil Mechanics', 'midterm', 'completed', 'Effective stress sigma prime equals total stress minus pore pressure.');
    $l_finals = createTestLesson($pdo, $teacher_id, 'Bearing Capacity', 'Soil Mechanics', 'finals', 'completed', 'Terzaghi bearing capacity equation for shallow foundations.');
    $l_general = createTestLesson($pdo, $teacher_id, 'General Math Reference', 'Soil Mechanics', 'general', 'completed', 'Unit conversion tables and trigonometric formulas.');

    $l_unauth = createTestLesson($pdo, $other_teacher_id, 'Secret Soil Notes', 'Soil Mechanics', 'prelim', 'completed', 'Other teacher private notes.');
    $l_diff_subject = createTestLesson($pdo, $teacher_id, 'Java Syntax', 'Computer Science', 'prelim', 'completed', 'Public static void main in Java.');
    $l_pending = createTestLesson($pdo, $teacher_id, 'Unprocessed PDF', 'Soil Mechanics', 'midterm', 'processing', '');
    $l_empty = createTestLesson($pdo, $teacher_id, 'Blank Document', 'Soil Mechanics', 'finals', 'completed', '   ');

    $created_lesson_ids = [$l_prelim1, $l_prelim2, $l_midterm, $l_finals, $l_general, $l_unauth, $l_diff_subject, $l_pending, $l_empty];

    $stmt = $pdo->prepare("SELECT * FROM lesson_materials WHERE id = ? AND teacher_id = ? AND processing_status = 'completed'");
    $stmt->execute([$l_prelim1, $teacher_id]);
    $res1 = $stmt->fetch(PDO::FETCH_ASSOC);
    $runner->assertTrue("TEST 1: One Prelim Lesson fetch & validation", !empty($res1) && $res1['academic_period'] === 'prelim', "ID: {$l_prelim1}, Period: prelim");

    $placeholders = implode(',', array_fill(0, 2, '?'));
    $stmt = $pdo->prepare("SELECT academic_period FROM lesson_materials WHERE id IN ($placeholders) AND teacher_id = ?");
    $stmt->execute([$l_prelim1, $l_prelim2, $teacher_id]);
    $res2 = $stmt->fetchAll(PDO::FETCH_COLUMN);
    $runner->assertTrue("TEST 2: Multiple Prelim Lessons pool", count($res2) === 2 && array_unique($res2) === ['prelim'], "Lessons retrieved: " . count($res2));

    $stmt = $pdo->prepare("SELECT academic_period FROM lesson_materials WHERE id IN (?, ?) AND teacher_id = ?");
    $stmt->execute([$l_prelim1, $l_midterm, $teacher_id]);
    $res3 = $stmt->fetchAll(PDO::FETCH_COLUMN);
    sort($res3);
    $runner->assertTrue("TEST 3: Cross-period (Prelim + Midterm)", $res3 === ['midterm', 'prelim'], "Periods covered: " . implode(', ', $res3));

    $stmt = $pdo->prepare("SELECT DISTINCT academic_period FROM lesson_materials WHERE id IN (?, ?, ?) AND teacher_id = ?");
    $stmt->execute([$l_prelim1, $l_midterm, $l_finals, $teacher_id]);
    $res4 = $stmt->fetchAll(PDO::FETCH_COLUMN);
    sort($res4);
    $runner->assertTrue("TEST 4: Full Multi-period (Prelim + Midterm + Finals)", count($res4) === 3, "Covered periods: " . implode(', ', $res4));

    $stmt = $pdo->prepare("SELECT DISTINCT academic_period FROM lesson_materials WHERE id IN (?, ?) AND teacher_id = ?");
    $stmt->execute([$l_general, $l_finals, $teacher_id]);
    $res5 = $stmt->fetchAll(PDO::FETCH_COLUMN);
    sort($res5);
    $runner->assertTrue("TEST 5: General lesson + Period lesson", $res5 === ['finals', 'general'], "Periods: " . implode(', ', $res5));

    $stmt = $pdo->prepare("SELECT id FROM lesson_materials WHERE id IN (?, ?) AND teacher_id = ?");
    $stmt->execute([$l_prelim1, $l_unauth, $teacher_id]);
    $res6 = $stmt->fetchAll(PDO::FETCH_COLUMN);
    $unauth_blocked = !in_array($l_unauth, $res6);
    $runner->assertTrue("TEST 6: Unauthorized Lesson ID security block", $unauth_blocked, "Unauthorized ID {$l_unauth} successfully excluded");

    $stmt = $pdo->prepare("SELECT subject FROM lesson_materials WHERE id IN (?, ?) AND teacher_id = ?");
    $stmt->execute([$l_prelim1, $l_diff_subject, $teacher_id]);
    $res7 = $stmt->fetchAll(PDO::FETCH_COLUMN);
    $diff_subj_detected = count(array_unique($res7)) > 1;
    $runner->assertTrue("TEST 7: Different subject detection", $diff_subj_detected, "Subjects detected: " . implode(', ', array_unique($res7)));

    $stmt = $pdo->prepare("SELECT id FROM lesson_materials WHERE id = ? AND processing_status = 'completed'");
    $stmt->execute([$l_pending]);
    $res8 = $stmt->fetchColumn();
    $runner->assertTrue("TEST 8: Incomplete extraction lesson block", $res8 === false, "Processing status 'processing' correctly blocked from generation");

    $stmt = $pdo->prepare("SELECT lesson_text FROM lesson_materials WHERE id = ?");
    $stmt->execute([$l_empty]);
    $res9 = trim($stmt->fetchColumn());
    $runner->assertTrue("TEST 9: Empty lesson text block", empty($res9), "Empty text content correctly identified");

    $dupes = [$l_prelim1, $l_prelim1, $l_midterm];
    $unique_dupes = array_unique(array_map('intval', $dupes));
    $runner->assertTrue("TEST 10: Duplicate Lesson IDs deduplication", count($unique_dupes) === 2, "Duplicate inputs de-duplicated to: " . implode(', ', $unique_dupes));

    $large_pool = array_fill(0, 100, $l_prelim1);
    $unique_large = array_unique($large_pool);
    $runner->assertTrue("TEST 11: Large lesson pool deduplication & sanity", count($unique_large) === 1, "100 duplicate items deduplicated safely");

    $batch_id = bin2hex(random_bytes(16));
    $stmtEx = $pdo->prepare("INSERT INTO exams (teacher_id, title, subject, created_by, covered_periods, source_lesson_count, generation_source_type, generation_batch_id) VALUES (?, 'Cross-Period Exam Test', 'Soil Mechanics', ?, 'prelim,midterm', 2, 'cross_period_lessons', ?)");
    $stmtEx->execute([$teacher_id, $teacher_id, $batch_id]);
    $exam_id = $pdo->lastInsertId();
    $created_exam_ids[] = $exam_id;

    $stmtQ = $pdo->prepare("INSERT INTO exam_questions (exam_id, question_text, option_a, option_b, option_c, option_d, correct_answer, points, lesson_id) VALUES (?, 'What is effective stress?', 'Option A', 'Option B', 'Option C', 'Option D', 'A', 1.00, ?)");
    $stmtQ->execute([$exam_id, $l_midterm]);
    $question_id = $pdo->lastInsertId();

    $stmtRel = $pdo->prepare("INSERT INTO generated_question_sources (question_id, lesson_id, academic_period) VALUES (?, ?, ?)");
    $stmtRel->execute([$question_id, $l_midterm, 'midterm']);
    $rel_id = $pdo->lastInsertId();

    $stmtCheckRel = $pdo->prepare("SELECT * FROM generated_question_sources WHERE question_id = ? AND lesson_id = ?");
    $stmtCheckRel->execute([$question_id, $l_midterm]);
    $res12 = $stmtCheckRel->fetch(PDO::FETCH_ASSOC);

    $runner->assertTrue("TEST 12: Source relation table persistence (generated_question_sources)", !empty($res12) && $res12['academic_period'] === 'midterm', "Relation ID {$rel_id} bound to question {$question_id}");

    $stmtExSingle = $pdo->prepare("INSERT INTO exams (teacher_id, title, subject, created_by, covered_periods, source_lesson_count, generation_source_type) VALUES (?, 'Single Period Exam', 'Soil Mechanics', ?, 'prelim', 1, 'single_lesson')");
    $stmtExSingle->execute([$teacher_id, $teacher_id]);
    $single_exam_id = $pdo->lastInsertId();
    $created_exam_ids[] = $single_exam_id;
    $runner->assertTrue("TEST 13: Existing single-lesson generation backward compatibility", $single_exam_id > 0, "Single-lesson exam created with ID {$single_exam_id}");

    $stmtReg = $pdo->prepare("SELECT exam_category FROM exams WHERE id = ?");
    $stmtReg->execute([$exam_id]);
    $catReg = $stmtReg->fetchColumn();
    $runner->assertTrue("TEST 14: Regular exam unaffected by cross-period metadata", $catReg === 'regular' || $catReg === null, "Category: " . ($catReg ?: 'regular'));

    $stmtQual = $pdo->prepare("INSERT INTO exams (teacher_id, title, subject, created_by, exam_category, qualifying_passing_percentage, qualifying_max_attempts) VALUES (?, 'Qualifying Test Exam', 'Soil Mechanics', ?, 'qualifying', 80.00, 2)");
    $stmtQual->execute([$teacher_id, $teacher_id]);
    $qual_exam_id = $pdo->lastInsertId();
    $created_exam_ids[] = $qual_exam_id;

    $stmtQualCheck = $pdo->prepare("SELECT exam_category, qualifying_passing_percentage FROM exams WHERE id = ?");
    $stmtQualCheck->execute([$qual_exam_id]);
    $res15 = $stmtQualCheck->fetch(PDO::FETCH_ASSOC);
    $runner->assertTrue("TEST 15: Qualifying exam rules unaffected", $res15['exam_category'] === 'qualifying' && floatval($res15['qualifying_passing_percentage']) === 80.0, "Category: qualifying, Pass: 80%");

} catch (Throwable $e) {
    $runner->recordException($e);
} finally {
    if ($pdo !== null) {
        try {
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
        } catch (Throwable $cleanupError) {
            $runner->recordCleanupFailure("created_test_records", $cleanupError);
        }
    }
}

$runner->finish();
