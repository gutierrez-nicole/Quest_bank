<?php

require_once __DIR__ . '/../app/database.php';

function migrateLegacyStudentNames() {
    $pdo = getDBConnection();

    echo "=== Legacy Student Name Migration Script ===\n";

    // Select all exam_submissions where student_id IS NULL or 0
    $stmt = $pdo->query("SELECT id, student_name FROM exam_submissions WHERE student_id IS NULL OR student_id = 0");
    $unlinked = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "Found " . count($unlinked) . " unlinked submission records.\n";

    $matched = 0;
    $unmatched = 0;

    $userStmt = $pdo->prepare("SELECT id FROM users WHERE role = 'student' AND (fullname = ? OR username = ?) LIMIT 1");
    $updateStmt = $pdo->prepare("UPDATE exam_submissions SET student_id = ? WHERE id = ?");

    foreach ($unlinked as $rec) {
        $name = trim($rec['student_name'] ?? '');
        if (empty($name)) {
            $unmatched++;
            continue;
        }

        $userStmt->execute([$name, $name]);
        $uid = $userStmt->fetchColumn();

        if ($uid) {
            $updateStmt->execute([$uid, $rec['id']]);
            $matched++;
        } else {
            $unmatched++;
        }
    }

    echo "Migration Complete: {$matched} matched & updated, {$unmatched} remaining unmatched.\n";
    return true;
}

if (basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'] ?? '')) {
    migrateLegacyStudentNames();
}
