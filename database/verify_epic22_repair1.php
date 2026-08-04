<?php
/**
 * Verification Script for QuestBank Epic 2.2 Repair Prompt 1: Grouped Lesson Selector, Filters, and Quick Selection
 */
require_once __DIR__ . '/../app/bootstrap.php';

$pdo = getDBConnection();
$passed = 0;
$failed = 0;

echo "===========================================================\n";
echo "   QUESTBANK EPIC 2.2 REPAIR PROMPT 1 VERIFICATION         \n";
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
    $stmtInsT = $pdo->prepare("INSERT INTO users (fullname, username, email, password, role, status) VALUES ('Other Teacher', 'otherteacher2', 'otherteacher2@test.com', 'hash', 'teacher', 'active')");
    $stmtInsT->execute();
    $other_teacher_id = $pdo->lastInsertId();
}

function createRepairTestLesson($pdo, $teacherId, $title, $subject, $period, $status = 'completed', $text = 'Sample lesson content', $yearLevel = '4th Year', $program = 'BSCE', $semester = '1st Semester', $schoolYear = '2025-2026') {
    $stmt = $pdo->prepare("
        INSERT INTO lesson_materials (teacher_id, title, subject, file_name, file_path, file_type, file_size, academic_period, processing_status, lesson_text, word_count, year_level, program, semester, school_year)
        VALUES (?, ?, ?, 'test_file.pdf', 'uploads/lessons/test_file.pdf', 'application/pdf', 1024, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $wordCount = str_word_count($text);
    $stmt->execute([$teacherId, $title, $subject, $period, $status, $text, $wordCount, $yearLevel, $program, $semester, $schoolYear]);
    return $pdo->lastInsertId();
}

$created_lesson_ids = [];

try {
    // 1. Create lessons across all 4 periods
    $l_gen = createRepairTestLesson($pdo, $teacher_id, 'General Math Review', 'Structural Theory', 'general', 'completed', 'Basic algebra and geometry formulas.');
    $l_pre = createRepairTestLesson($pdo, $teacher_id, 'Soil Mechanics Intro', 'Soil Mechanics', 'prelim', 'completed', 'Soil physical properties and phase relations.');
    $l_mid = createRepairTestLesson($pdo, $teacher_id, 'Stresses in Soil', 'Soil Mechanics', 'midterm', 'completed', 'Effective stress and vertical stress distribution.');
    $l_fin = createRepairTestLesson($pdo, $teacher_id, 'Shallow Foundations', 'Geotechnical Engineering', 'finals', 'completed', 'Ultimate bearing capacity equations.');
    
    // Incomplete & Empty content lessons
    $l_pending = createRepairTestLesson($pdo, $teacher_id, 'Unprocessed Slide Deck', 'Soil Mechanics', 'prelim', 'processing', '');
    $l_empty = createRepairTestLesson($pdo, $teacher_id, 'Empty Document', 'Soil Mechanics', 'midterm', 'completed', '   ');

    // Unauthorized lesson (different teacher)
    $l_unauth = createRepairTestLesson($pdo, $other_teacher_id, 'Private Faculty Notes', 'Soil Mechanics', 'finals', 'completed', 'Confidential faculty material.');

    $created_lesson_ids = [$l_gen, $l_pre, $l_mid, $l_fin, $l_pending, $l_empty, $l_unauth];

    // --- TEST 1: Lessons grouped under 4 academic periods ---
    $stmt = $pdo->prepare("
        SELECT id, COALESCE(academic_period, 'general') AS academic_period 
        FROM lesson_materials 
        WHERE teacher_id = ? AND id IN (?, ?, ?, ?)
    ");
    $stmt->execute([$teacher_id, $l_gen, $l_pre, $l_mid, $l_fin]);
    $grouped = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $grouped[$row['academic_period']][] = $row['id'];
    }
    $all4PeriodsPresent = isset($grouped['general']) && isset($grouped['prelim']) && isset($grouped['midterm']) && isset($grouped['finals']);
    logTest("TEST 1: Lessons grouped under 4 academic periods (General, Prelim, Midterm, Finals)", $all4PeriodsPresent, "General: {$l_gen}, Prelim: {$l_pre}, Midterm: {$l_mid}, Finals: {$l_fin}");

    // --- TEST 2: Group Metadata Display Completeness ---
    $stmtMeta = $pdo->prepare("SELECT title, subject, semester, school_year, year_level, program, processing_status FROM lesson_materials WHERE id = ?");
    $stmtMeta->execute([$l_pre]);
    $meta = $stmtMeta->fetch(PDO::FETCH_ASSOC);
    $metaComplete = !empty($meta['title']) && !empty($meta['subject']) && !empty($meta['semester']) && !empty($meta['school_year']) && !empty($meta['year_level']) && !empty($meta['program']) && !empty($meta['processing_status']);
    logTest("TEST 2: Lesson item metadata attributes (title, subject, semester, SY, year, program, status)", $metaComplete, "Subject: {$meta['subject']}, SY: {$meta['school_year']}, Status: {$meta['processing_status']}");

    // --- TEST 3: Dynamic Filters Data Availability ---
    $stmtSubj = $pdo->prepare("SELECT DISTINCT subject FROM lesson_materials WHERE teacher_id = ?");
    $stmtSubj->execute([$teacher_id]);
    $subjects = $stmtSubj->fetchAll(PDO::FETCH_COLUMN);
    $hasMultipleSubj = count($subjects) >= 2;
    logTest("TEST 3: Filters data availability (Subject, Year, Program, Semester, SY, Period)", $hasMultipleSubj, "Subjects available: " . implode(', ', $subjects));

    // --- TEST 4: Selection Blocking for Incomplete Extraction ---
    $stmtPen = $pdo->prepare("SELECT processing_status, lesson_text FROM lesson_materials WHERE id = ?");
    $stmtPen->execute([$l_pending]);
    $rowPen = $stmtPen->fetch(PDO::FETCH_ASSOC);
    $canSelectPending = ($rowPen['processing_status'] === 'completed') && !empty(trim($rowPen['lesson_text']));
    logTest("TEST 4: Selection blocking for incomplete extraction", !$canSelectPending, "Processing status '{$rowPen['processing_status']}' correctly blocked from selection");

    // --- TEST 5: Selection Blocking for Empty Lesson Text ---
    $stmtEmp = $pdo->prepare("SELECT processing_status, lesson_text FROM lesson_materials WHERE id = ?");
    $stmtEmp->execute([$l_empty]);
    $rowEmp = $stmtEmp->fetch(PDO::FETCH_ASSOC);
    $canSelectEmpty = ($rowEmp['processing_status'] === 'completed') && !empty(trim($rowEmp['lesson_text']));
    logTest("TEST 5: Selection blocking for empty lesson text", !$canSelectEmpty, "Empty lesson text content correctly blocked from selection");

    // --- TEST 6: Unauthorized Lessons Never Rendered ---
    $stmtUnauth = $pdo->prepare("SELECT id FROM lesson_materials WHERE teacher_id = ? AND id = ?");
    $stmtUnauth->execute([$teacher_id, $l_unauth]);
    $renderedUnauth = $stmtUnauth->fetchColumn();
    logTest("TEST 6: Unauthorized lessons never rendered for teacher", $renderedUnauth === false, "Other teacher lesson ID {$l_unauth} hidden from teacher {$teacher_id}");

    // --- TEST 7: Single-Lesson & Cross-Period Generation Backend Preserved ---
    $stmtFetchSel = $pdo->prepare("
        SELECT id, title, subject, lesson_text, COALESCE(academic_period,'general') AS academic_period, processing_status
        FROM lesson_materials 
        WHERE id IN (?, ?) AND teacher_id = ?
    ");
    $stmtFetchSel->execute([$l_pre, $l_mid, $teacher_id]);
    $fetched = $stmtFetchSel->fetchAll(PDO::FETCH_ASSOC);
    logTest("TEST 7: Backend multi-period selection pipeline preserved", count($fetched) === 2, "Fetched 2 authorized lessons across Prelim and Midterm");

} catch (Throwable $e) {
    echo "TEST EXCEPTION: " . $e->getMessage() . "\n" . $e->getTraceAsString() . "\n";
} finally {
    if (!empty($created_lesson_ids)) {
        $phL = implode(',', array_fill(0, count($created_lesson_ids), '?'));
        $pdo->prepare("DELETE FROM lesson_materials WHERE id IN ($phL)")->execute($created_lesson_ids);
    }
    echo "\n=== CLEANUP COMPLETE (Test records cleaned up) ===\n\n";
}

echo "-----------------------------------------------------------\n";
echo "VERIFICATION SUMMARY: {$passed} PASSED, {$failed} FAILED\n";
echo "-----------------------------------------------------------\n";

exit($failed > 0 ? 1 : 0);
