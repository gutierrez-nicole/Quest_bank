<?php

require_once __DIR__ . '/../app/database.php';

function verifyCanonicalSchema() {
    $pdo = getDBConnection();
    $dbName = DB_NAME;

    $requiredTables = [
        'users' => ['id', 'username', 'email', 'password', 'role'],
        'student_details' => ['user_id', 'student_number', 'course', 'year_level', 'section'],
        'subjects' => ['id', 'code', 'title'],
        'lesson_materials' => ['id', 'teacher_id', 'lesson_text', 'processing_status'],
        'exams' => ['id', 'teacher_id', 'title', 'passing_percentage', 'status'],
        'exam_questions' => ['id', 'exam_id', 'question_text', 'question_type', 'correct_answer', 'points'],
        'exam_submissions' => [
            'id', 'exam_id', 'student_id', 'teacher_id', 'total_score', 'total_possible_score',
            'percentage', 'status', 'ocr_text', 'original_ocr_text', 'corrected_ocr_text',
            'extraction_mode', 'ocr_confidence', 'ocr_status', 'suggested_manual_review',
            'review_status', 'reviewed_by', 'published_at'
        ],
        'submission_answers' => [
            'id', 'submission_id', 'question_id', 'student_answer', 'correct_answer',
            'awarded_points', 'max_points', 'evaluation_status', 'requires_review'
        ],
        'submission_score_overrides' => ['id', 'submission_id', 'old_score', 'new_score', 'reviewer_id', 'reason'],
        'activity_logs' => ['id', 'user_id', 'action_description', 'created_at']
    ];

    echo "=== QuestBank Schema Verification ===\n";
    echo "Database: {$dbName}\n\n";

    $missingCount = 0;

    foreach ($requiredTables as $table => $columns) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = ? AND table_name = ?");
        $stmt->execute([$dbName, $table]);
        $tableExists = (int)$stmt->fetchColumn() > 0;

        if (!$tableExists) {
            echo "[FAIL] Table missing: {$table}\n";
            $missingCount++;
            continue;
        }

        echo "[OK] Table exists: {$table}\n";

        foreach ($columns as $col) {
            $colStmt = $pdo->prepare("SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = ? AND table_name = ? AND column_name = ?");
            $colStmt->execute([$dbName, $table, $col]);
            $colExists = (int)$colStmt->fetchColumn() > 0;

            if (!$colExists) {
                echo "   [FAIL] Column missing: {$table}.{$col}\n";
                $missingCount++;
            }
        }
    }

    echo "\n-----------------------------------------\n";
    if ($missingCount === 0) {
        echo "VERIFICATION PASSED: All 10 core tables and required columns exist cleanly.\n";
        return true;
    } else {
        echo "VERIFICATION FAILED: {$missingCount} missing tables/columns detected.\n";
        return false;
    }
}

if (basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'] ?? '')) {
    $success = verifyCanonicalSchema();
    exit($success ? 0 : 1);
}
