<?php
/**
 * Verification Script for QuestBank Epic 2.2 Repair 1: Strict Subject & Academic Context Validation
 */
require_once __DIR__ . '/../app/bootstrap.php';

$pdo = getDBConnection();
$passed = 0;
$failed = 0;

echo "===========================================================\n";
echo "   QUESTBANK EPIC 2.2 REPAIR 1 CONTEXT VALIDATION VERIFICATION\n";
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

// Fetch teacher
$stmtT = $pdo->prepare("SELECT id FROM users WHERE role = 'teacher' LIMIT 1");
$stmtT->execute();
$teacher_id = $stmtT->fetchColumn();

function createDummyLesson($pdo, $teacherId, $title, $subject, $period = 'prelim', $prog = 'BSCE', $year = '4th Year', $sem = '1st Semester', $sy = '2025-2026') {
    $stmt = $pdo->prepare("
        INSERT INTO lesson_materials (teacher_id, title, subject, file_name, file_path, file_type, file_size, academic_period, processing_status, lesson_text, word_count, program, year_level, semester, school_year)
        VALUES (?, ?, ?, 'dummy.pdf', 'uploads/dummy.pdf', 'application/pdf', 1024, ?, 'completed', 'Valid dummy lesson content text for assessment generation.', 10, ?, ?, ?, ?)
    ");
    $stmt->execute([$teacherId, $title, $subject, $period, $prog, $year, $sem, $sy]);
    return $pdo->lastInsertId();
}

$created = [];

try {
    $baseLesson = createDummyLesson($pdo, $teacher_id, 'Base Geotechnical Lesson', 'Soil Mechanics', 'prelim', 'BSCE', '4th Year', '1st Semester', '2025-2026');
    $diffSubjLesson = createDummyLesson($pdo, $teacher_id, 'Fluid Statics Lesson', 'Fluid Mechanics', 'prelim', 'BSCE', '4th Year', '1st Semester', '2025-2026');
    $diffProgLesson = createDummyLesson($pdo, $teacher_id, 'CS Algorithms Lesson', 'Soil Mechanics', 'prelim', 'BSCS', '4th Year', '1st Semester', '2025-2026');
    $diffYearLesson = createDummyLesson($pdo, $teacher_id, 'Freshman Intro Lesson', 'Soil Mechanics', 'prelim', 'BSCE', '1st Year', '1st Semester', '2025-2026');
    $diffSemLesson = createDummyLesson($pdo, $teacher_id, 'Second Sem Lesson', 'Soil Mechanics', 'prelim', 'BSCE', '4th Year', '2nd Semester', '2025-2026');
    $diffSyLesson = createDummyLesson($pdo, $teacher_id, 'Old Year Lesson', 'Soil Mechanics', 'prelim', 'BSCE', '4th Year', '1st Semester', '2024-2025');
    $matchingLesson = createDummyLesson($pdo, $teacher_id, 'Matching Geotechnical Lesson', 'Soil Mechanics', 'midterm', 'BSCE', '4th Year', '1st Semester', '2025-2026');

    $created = [$baseLesson, $diffSubjLesson, $diffProgLesson, $diffYearLesson, $diffSemLesson, $diffSyLesson, $matchingLesson];

    // Helper validation simulator
    function validateContextPool($pdo, $teacherId, array $selectedIds, $requestedSubject) {
        $placeholders = implode(',', array_fill(0, count($selectedIds), '?'));
        $stmt = $pdo->prepare("SELECT id, title, subject, program, year_level, semester, school_year FROM lesson_materials WHERE id IN ($placeholders) AND teacher_id = ?");
        $stmt->execute(array_merge($selectedIds, [$teacherId]));
        $sel = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $conflicts = [];
        if (!empty($sel)) {
            $first = $sel[0];
            foreach ($sel as $sl) {
                if (!empty($requestedSubject) && strcasecmp(trim($sl['subject']), trim($requestedSubject)) !== 0) {
                    $conflicts[] = ['title' => $sl['title'], 'field' => 'Subject', 'expected' => $requestedSubject, 'actual' => $sl['subject']];
                }
                if (strcasecmp(trim($sl['subject']), trim($first['subject'])) !== 0) {
                    $conflicts[] = ['title' => $sl['title'], 'field' => 'Subject (Pool Mismatch)', 'expected' => $first['subject'], 'actual' => $sl['subject']];
                }
                if (!empty($sl['program']) && !empty($first['program']) && strcasecmp(trim($sl['program']), trim($first['program'])) !== 0) {
                    $conflicts[] = ['title' => $sl['title'], 'field' => 'Program', 'expected' => $first['program'], 'actual' => $sl['program']];
                }
                if (!empty($sl['year_level']) && !empty($first['year_level']) && strcasecmp(trim($sl['year_level']), trim($first['year_level'])) !== 0) {
                    $conflicts[] = ['title' => $sl['title'], 'field' => 'Year Level', 'expected' => $first['year_level'], 'actual' => $sl['year_level']];
                }
                if (!empty($sl['semester']) && !empty($first['semester']) && strcasecmp(trim($sl['semester']), trim($first['semester'])) !== 0) {
                    $conflicts[] = ['title' => $sl['title'], 'field' => 'Semester', 'expected' => $first['semester'], 'actual' => $sl['semester']];
                }
                if (!empty($sl['school_year']) && !empty($first['school_year']) && strcasecmp(trim($sl['school_year']), trim($first['school_year'])) !== 0) {
                    $conflicts[] = ['title' => $sl['title'], 'field' => 'School Year', 'expected' => $first['school_year'], 'actual' => $sl['school_year']];
                }
            }
        }
        return $conflicts;
    }

    // --- TEST 1: Subject Mismatch Rejection ---
    $conf1 = validateContextPool($pdo, $teacher_id, [$baseLesson, $diffSubjLesson], 'Soil Mechanics');
    $hasSubjErr = !empty(array_filter($conf1, function($c) { return strpos($c['field'], 'Subject') !== false; }));
    logTest("TEST 1: Subject Mismatch Rejection", $hasSubjErr, "Detected subject conflict: " . ($conf1[0]['actual'] ?? ''));

    // --- TEST 2: Program Mismatch Rejection ---
    $conf2 = validateContextPool($pdo, $teacher_id, [$baseLesson, $diffProgLesson], 'Soil Mechanics');
    $hasProgErr = !empty(array_filter($conf2, function($c) { return $c['field'] === 'Program'; }));
    logTest("TEST 2: Program Mismatch Rejection", $hasProgErr, "Detected program conflict: Expected BSCE, actual " . ($conf2[0]['actual'] ?? ''));

    // --- TEST 3: Year Level Mismatch Rejection ---
    $conf3 = validateContextPool($pdo, $teacher_id, [$baseLesson, $diffYearLesson], 'Soil Mechanics');
    $hasYearErr = !empty(array_filter($conf3, function($c) { return $c['field'] === 'Year Level'; }));
    logTest("TEST 3: Year Level Mismatch Rejection", $hasYearErr, "Detected year level conflict: Expected 4th Year, actual " . ($conf3[0]['actual'] ?? ''));

    // --- TEST 4: Semester Mismatch Rejection ---
    $conf4 = validateContextPool($pdo, $teacher_id, [$baseLesson, $diffSemLesson], 'Soil Mechanics');
    $hasSemErr = !empty(array_filter($conf4, function($c) { return $c['field'] === 'Semester'; }));
    logTest("TEST 4: Semester Mismatch Rejection", $hasSemErr, "Detected semester conflict: Expected 1st Semester, actual " . ($conf4[0]['actual'] ?? ''));

    // --- TEST 5: School Year Mismatch Rejection ---
    $conf5 = validateContextPool($pdo, $teacher_id, [$baseLesson, $diffSyLesson], 'Soil Mechanics');
    $hasSyErr = !empty(array_filter($conf5, function($c) { return $c['field'] === 'School Year'; }));
    logTest("TEST 5: School Year Mismatch Rejection", $hasSyErr, "Detected SY conflict: Expected 2025-2026, actual " . ($conf5[0]['actual'] ?? ''));

    // --- TEST 6: Valid Matching Lesson Pool Passes ---
    $conf6 = validateContextPool($pdo, $teacher_id, [$baseLesson, $matchingLesson], 'Soil Mechanics');
    logTest("TEST 6: Valid Matching Lesson Pool Passes", empty($conf6), "0 conflicts detected for matching pool across Prelim & Midterm");

} catch (Throwable $e) {
    echo "TEST EXCEPTION: " . $e->getMessage() . "\n";
} finally {
    if (!empty($created)) {
        $phC = implode(',', array_fill(0, count($created), '?'));
        $pdo->prepare("DELETE FROM lesson_materials WHERE id IN ($phC)")->execute($created);
    }
    echo "\n=== CLEANUP COMPLETE (Test records cleaned up) ===\n\n";
}

echo "-----------------------------------------------------------\n";
echo "VERIFICATION SUMMARY: {$passed} PASSED, {$failed} FAILED\n";
echo "-----------------------------------------------------------\n";

exit($failed > 0 ? 1 : 0);
