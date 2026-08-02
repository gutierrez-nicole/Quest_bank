<?php

require_once __DIR__ . '/../app/database.php';

function migrateLegacyStudentNames() {
    $pdo = getDBConnection();

    echo "=== Legacy Student Name Migration Script ===\n";

    $stmt = $pdo->query("SELECT id, student_name FROM exam_submissions WHERE student_id IS NULL OR student_id = 0");
    $unlinked = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "Found " . count($unlinked) . " unlinked submission records.\n";

    $matchedRows = [["submission_id", "student_name", "matched_student_id"]];
    $ambiguousRows = [["submission_id", "student_name", "candidate_count"]];
    $unmatchedRows = [["submission_id", "student_name"]];

    $matchedCount = 0;
    $ambiguousCount = 0;
    $unmatchedCount = 0;

    $userStmt = $pdo->prepare("SELECT id FROM users WHERE role = 'student' AND (fullname LIKE ? OR username LIKE ?)");
    $updateStmt = $pdo->prepare("UPDATE exam_submissions SET student_id = ? WHERE id = ?");

    foreach ($unlinked as $rec) {
        $name = trim($rec['student_name'] ?? '');
        $subId = $rec['id'];

        if (empty($name)) {
            $unmatchedRows[] = [$subId, "EMPTY_NAME"];
            $unmatchedCount++;
            continue;
        }

        $userStmt->execute(["%{$name}%", "%{$name}%"]);
        $matches = $userStmt->fetchAll(PDO::FETCH_COLUMN);

        if (count($matches) === 1) {
            $uid = $matches[0];
            $updateStmt->execute([$uid, $subId]);
            $matchedRows[] = [$subId, $name, $uid];
            $matchedCount++;
        } elseif (count($matches) > 1) {
            $ambiguousRows[] = [$subId, $name, count($matches)];
            $ambiguousCount++;
        } else {
            $unmatchedRows[] = [$subId, $name];
            $unmatchedCount++;
        }
    }

    // Write CSV Reports
    $dbDir = __DIR__;
    writeCsv("{$dbDir}/matched.csv", $matchedRows);
    writeCsv("{$dbDir}/ambiguous.csv", $ambiguousRows);
    writeCsv("{$dbDir}/unmatched.csv", $unmatchedRows);

    echo "Migration Complete: {$matchedCount} matched & updated, {$ambiguousCount} ambiguous, {$unmatchedCount} unmatched.\n";
    echo "Generated CSV reports: matched.csv, ambiguous.csv, unmatched.csv\n";
    return true;
}

function writeCsv($filepath, $rows) {
    $fp = fopen($filepath, 'w');
    foreach ($rows as $row) {
        fputcsv($fp, $row);
    }
    fclose($fp);
}

if (basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'] ?? '')) {
    migrateLegacyStudentNames();
}
