<?php
require_once __DIR__ . '/../app/bootstrap.php';

AuthService::enforceRole('teacher');
$pdo = getDBConnection();
$teacher_id = getCurrentUserId();

$selected_exam = $_GET['exam_title'] ?? 'all';
$selected_category = $_GET['exam_category'] ?? 'all';
$selected_qual_status = $_GET['qualification_status'] ?? 'all';
$selected_subject = $_GET['subject'] ?? 'all';
$selected_section = $_GET['section'] ?? 'all';

try {
    $where = ["es.teacher_id = ?", "es.review_status = 'published'"];
    $params = [$teacher_id];

    if ($selected_exam !== 'all') {
        $where[] = "es.exam_title = ?";
        $params[] = $selected_exam;
    }

    if ($selected_category !== 'all') {
        $where[] = "e.exam_category = ?";
        $params[] = $selected_category;
    }

    if ($selected_qual_status !== 'all') {
        $where[] = "es.qualification_status = ?";
        $params[] = $selected_qual_status;
    }

    if ($selected_subject !== 'all') {
        $where[] = "(e.subject = ? OR es.subject = ?)";
        $params[] = $selected_subject;
        $params[] = $selected_subject;
    }

    if ($selected_section !== 'all') {
        $where[] = "(es.section = ? OR sd.section = ?)";
        $params[] = $selected_section;
        $params[] = $selected_section;
    }

    $whereClause = implode(" AND ", $where);

    $stmt = $pdo->prepare("
        SELECT 
            es.id AS submission_id,
            u.fullname AS student_name,
            sd.student_number,
            sd.section,
            COALESCE(e.title, es.exam_title) AS exam_title,
            COALESCE(e.subject, 'Civil Engineering') AS subject,
            COALESCE(e.exam_category, 'regular') AS exam_category,
            es.correct_count AS score,
            es.total_items,
            es.percentage,
            es.status,
            es.qualification_status,
            es.created_at AS date_taken,
            es.published_at
        FROM exam_submissions es
        LEFT JOIN exams e ON es.exam_id = e.id
        LEFT JOIN users u ON es.student_id = u.id
        LEFT JOIN student_details sd ON es.student_id = sd.user_id
        WHERE {$whereClause}
        GROUP BY es.id
        ORDER BY es.id DESC
    ");
    $stmt->execute($params);
    $records = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $filename = "teacher_published_report_" . date('Ymd_His') . ".csv";

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');

    $output = fopen('php://output', 'w');
    fputcsv($output, ['Submission ID', 'Student Name', 'Student No', 'Section', 'Exam Title', 'Subject', 'Category', 'Score', 'Total Items', 'Percentage', 'Status', 'Qualifying Status', 'Date Taken', 'Published Date']);

    foreach ($records as $r) {
        fputcsv($output, [
            $r['submission_id'],
            $r['student_name'] ?? 'Student User',
            $r['student_number'] ?? 'N/A',
            $r['section'] ?? 'N/A',
            $r['exam_title'],
            $r['subject'],
            ucfirst($r['exam_category']),
            $r['score'],
            $r['total_items'],
            number_format((float)$r['percentage'], 1) . '%',
            $r['status'],
            ucfirst($r['qualification_status'] ?? 'N/A'),
            $r['date_taken'],
            $r['published_at'] ?? 'N/A'
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
