<?php
require_once __DIR__ . '/../app/bootstrap.php';

AuthService::enforceRole('student');
$pdo = getDBConnection();
$student_id = getCurrentUserId();

$selected_term = trim($_GET['term'] ?? 'All');
$selected_subject = trim($_GET['subject'] ?? 'all');

try {
    $records = array_map(function ($r) {
        return $r + ['submission_id' => $r['id'], 'academic_period' => $r['term'], 'date_taken' => $r['created_at'], 'published_date' => $r['published_at']];
    }, StudentResultService::results((int)$student_id, $selected_term, $selected_subject));

    $filename = "student_published_history_" . date('Ymd_His') . ".csv";

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');

    $output = fopen('php://output', 'w');
    fputcsv($output, ['Submission ID', 'Exam Title', 'Subject', 'Academic Period', 'Score', 'Total Items', 'Percentage', 'Status', 'Date Taken', 'Published Date']);

    foreach ($records as $r) {
        fputcsv($output, [
            $r['submission_id'],
            $r['exam_title'],
            $r['subject'],
            $r['academic_period'],
            $r['score'],
            $r['total_items'],
            number_format((float)$r['percentage'], 1) . '%',
            $r['status'],
            $r['date_taken'],
            $r['published_date'] ?? 'N/A'
        ]);
    }
    fclose($output);
    exit;

} catch (Exception $e) {
    http_response_code(500);
    header('Content-Type: text/plain');
    echo "Export Error: " . $e->getMessage();
    exit;
}
