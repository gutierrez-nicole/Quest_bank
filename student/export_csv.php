<?php
require_once __DIR__ . '/../app/bootstrap.php';

AuthService::enforceRole('student');
$pdo = getDBConnection();
$student_id = getCurrentUserId();

$selected_term = trim($_GET['term'] ?? 'All');
$selected_subject = trim($_GET['subject'] ?? 'all');

try {
    $where = ["es.student_id = ?", "es.review_status = 'published'"];
    $params = [$student_id];

    if (!empty($selected_term) && $selected_term !== 'All') {
        $where[] = "(es.term = ? OR e.academic_period = ?)";
        $params[] = $selected_term;
        $params[] = $selected_term;
    }

    if (!empty($selected_subject) && $selected_subject !== 'all') {
        $where[] = "(COALESCE(e.subject, 'Civil Engineering') = ? OR es.subject = ?)";
        $params[] = $selected_subject;
        $params[] = $selected_subject;
    }

    $whereClause = implode(" AND ", $where);

    $stmt = $pdo->prepare("
        SELECT 
            es.id AS submission_id,
            COALESCE(e.title, es.exam_title) AS exam_title,
            COALESCE(e.subject, 'Civil Engineering') AS subject,
            COALESCE(es.term, e.academic_period, 'General') AS academic_period,
            es.correct_count AS score,
            es.total_items,
            es.percentage,
            es.status,
            es.created_at AS date_taken,
            es.published_at AS published_date
        FROM exam_submissions es
        LEFT JOIN exams e ON es.exam_id = e.id
        WHERE {$whereClause}
        GROUP BY es.id
        ORDER BY es.created_at DESC
    ");
    $stmt->execute($params);
    $records = $stmt->fetchAll(PDO::FETCH_ASSOC);

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
