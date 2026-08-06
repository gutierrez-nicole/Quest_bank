<?php

require_once __DIR__ . '/../database.php';
require_once __DIR__ . '/../fpdf.php';
require_once __DIR__ . '/AuditLogService.php';

class BulkExportService {

    public static function exportCSV($type, $actorId = null) {
        $pdo = getDBConnection();
        $filename = "export_{$type}_" . date('Ymd_His') . ".csv";

        header('Content-Type: text/csv; charset=utf-8');
        header("Content-Disposition: attachment; filename={$filename}");
        $output = fopen('php://output', 'w');

        switch ($type) {
            case 'students':
                fputcsv($output, ['ID', 'Student Number', 'Full Name', 'Email', 'Course', 'Year Level', 'Section', 'Status']);
                $stmt = $pdo->query("
                    SELECT u.id, sd.student_number, u.fullname, u.email, sd.course, sd.year_level, sd.section, u.status
                    FROM users u
                    LEFT JOIN student_details sd ON u.id = sd.user_id
                    WHERE u.role = 'student'
                    ORDER BY u.id ASC
                ");
                while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    fputcsv($output, $row);
                }
                break;

            case 'teachers':
                fputcsv($output, ['ID', 'Username', 'Full Name', 'Email', 'Status', 'Created At']);
                $stmt = $pdo->query("SELECT id, username, fullname, email, status, created_at FROM users WHERE role = 'teacher' ORDER BY id ASC");
                while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    fputcsv($output, $row);
                }
                break;

            case 'sections':
                fputcsv($output, ['ID', 'Section Code', 'Adviser', 'Capacity', 'Status', 'Created At']);
                $stmt = $pdo->query("
                    SELECT s.id, s.section_code, COALESCE(u.fullname, 'None') as adviser, s.capacity, s.status, s.created_at
                    FROM sections s
                    LEFT JOIN users u ON s.adviser_id = u.id
                    ORDER BY s.id ASC
                ");
                while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    fputcsv($output, $row);
                }
                break;

            case 'exams':
                fputcsv($output, ['ID', 'Exam Title', 'Subject', 'Teacher', 'Category', 'Time Limit', 'Total Items', 'Status', 'Created At']);
                $stmt = $pdo->query("
                    SELECT e.id, e.title, e.subject, u.fullname as teacher, e.exam_category, e.time_limit, e.total_items, e.status, e.created_at
                    FROM exams e
                    LEFT JOIN users u ON e.teacher_id = u.id
                    ORDER BY e.id ASC
                ");
                while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    fputcsv($output, $row);
                }
                break;

            case 'schedules':
                fputcsv($output, ['ID', 'Exam Title', 'Subject', 'Teacher', 'Section', 'Exam Date', 'Start Time', 'End Time', 'Room', 'Status']);
                $stmt = $pdo->query("
                    SELECT es.id, e.title, e.subject, u.fullname as teacher, es.section, es.exam_date, es.start_time, es.end_time, es.room, es.status
                    FROM exam_schedules es
                    JOIN exams e ON es.exam_id = e.id
                    JOIN users u ON es.teacher_id = u.id
                    ORDER BY es.exam_date ASC
                ");
                while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    fputcsv($output, $row);
                }
                break;
        }

        fclose($output);
        AuditLogService::logAction($actorId, "Bulk Export CSV", "Type: {$type}");
        exit;
    }

    public static function exportSchedulesPDF($actorId = null) {
        $pdo = getDBConnection();
        $stmt = $pdo->query("
            SELECT es.*, e.title as exam_title, e.subject, u.fullname as teacher_name
            FROM exam_schedules es
            JOIN exams e ON es.exam_id = e.id
            JOIN users u ON es.teacher_id = u.id
            ORDER BY es.exam_date ASC
        ");
        $schedules = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $pdf = new FPDF('P', 'mm', 'A4');
        $pdf->AddPage();
        $pdf->SetFont('Arial', 'B', 16);
        $pdf->Cell(190, 10, 'QuestBank - Official Exam Schedules Report', 0, 1, 'C');
        $pdf->SetFont('Arial', '', 10);
        $pdf->Cell(190, 6, 'Generated: ' . date('F j, Y, g:i a'), 0, 1, 'C');
        $pdf->Ln(5);

        // Header Table
        $pdf->SetFont('Arial', 'B', 9);
        $pdf->SetFillColor(230, 230, 230);
        $pdf->Cell(10, 7, '#', 1, 0, 'C', true);
        $pdf->Cell(45, 7, 'Exam Title', 1, 0, 'L', true);
        $pdf->Cell(30, 7, 'Subject', 1, 0, 'L', true);
        $pdf->Cell(35, 7, 'Teacher', 1, 0, 'L', true);
        $pdf->Cell(15, 7, 'Sec', 1, 0, 'C', true);
        $pdf->Cell(25, 7, 'Date', 1, 0, 'C', true);
        $pdf->Cell(30, 7, 'Time', 1, 1, 'C', true);

        $pdf->SetFont('Arial', '', 8);
        $i = 1;
        foreach ($schedules as $s) {
            $pdf->Cell(10, 6, $i++, 1, 0, 'C');
            $pdf->Cell(45, 6, substr($s['exam_title'], 0, 25), 1, 0, 'L');
            $pdf->Cell(30, 6, substr($s['subject'], 0, 18), 1, 0, 'L');
            $pdf->Cell(35, 6, substr($s['teacher_name'], 0, 20), 1, 0, 'L');
            $pdf->Cell(15, 6, $s['section'], 1, 0, 'C');
            $pdf->Cell(25, 6, $s['exam_date'], 1, 0, 'C');
            $pdf->Cell(30, 6, substr($s['start_time'], 0, 5) . '-' . substr($s['end_time'], 0, 5), 1, 1, 'C');
        }

        AuditLogService::logAction($actorId, "Bulk Export PDF", "Type: schedules");
        $pdf->Output('I', 'exam_schedules_report.pdf');
        exit;
    }
}
