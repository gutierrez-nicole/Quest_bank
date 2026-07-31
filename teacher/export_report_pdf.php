<?php
require_once __DIR__ . '/../app/bootstrap.php';
require_once __DIR__ . '/../app/fpdf.php';

AuthService::enforceRole('teacher');
$pdo = getDBConnection();
$teacher_id = $_SESSION['user_id'];

try {
    $stmtT = $pdo->prepare("SELECT fullname, email FROM users WHERE id = ?");
    $stmtT->execute([$teacher_id]);
    $teacher = $stmtT->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $teacher = null;
}
$teacher_name = $teacher['fullname'] ?? 'Prof. Jolas';
$date_issued = date('F d, Y');

$selected_exam = trim($_GET['exam_title'] ?? 'all');

if ($selected_exam !== 'all') {
    $stmtStats = $pdo->prepare("
        SELECT 
            COUNT(*) as total_students,
            SUM(CASE WHEN status = 'Pass' THEN 1 ELSE 0 END) as total_pass,
            SUM(CASE WHEN status = 'Fail' THEN 1 ELSE 0 END) as total_fail,
            AVG(percentage) as avg_percentage,
            MAX(percentage) as max_percentage,
            MIN(percentage) as min_percentage
        FROM exam_submissions 
        WHERE teacher_id = ? AND exam_title = ?
    ");
    $stmtStats->execute([$teacher_id, $selected_exam]);

    $stmtList = $pdo->prepare("SELECT * FROM exam_submissions WHERE teacher_id = ? AND exam_title = ? ORDER BY id DESC");
    $stmtList->execute([$teacher_id, $selected_exam]);
    $report_title = "EXAM PERFORMANCE REPORT: " . strtoupper($selected_exam);
} else {
    $stmtStats = $pdo->prepare("
        SELECT 
            COUNT(*) as total_students,
            SUM(CASE WHEN status = 'Pass' THEN 1 ELSE 0 END) as total_pass,
            SUM(CASE WHEN status = 'Fail' THEN 1 ELSE 0 END) as total_fail,
            AVG(percentage) as avg_percentage,
            MAX(percentage) as max_percentage,
            MIN(percentage) as min_percentage
        FROM exam_submissions 
        WHERE teacher_id = ?
    ");
    $stmtStats->execute([$teacher_id]);

    $stmtList = $pdo->prepare("SELECT * FROM exam_submissions WHERE teacher_id = ? ORDER BY id DESC");
    $stmtList->execute([$teacher_id]);
    $report_title = "FACULTY MASTER CLASS PERFORMANCE & ANALYTICS REPORT";
}

$stats = $stmtStats->fetch(PDO::FETCH_ASSOC);
$submissions = $stmtList->fetchAll(PDO::FETCH_ASSOC);

$total = intval($stats['total_students'] ?? 0);
$pass = intval($stats['total_pass'] ?? 0);
$fail = intval($stats['total_fail'] ?? 0);
$pass_rate = $total > 0 ? round(($pass / $total) * 100, 1) : 0.0;
$avg = round(floatval($stats['avg_percentage'] ?? 0), 1);
$max = round(floatval($stats['max_percentage'] ?? 0), 1);
$min = round(floatval($stats['min_percentage'] ?? 0), 1);

if (!class_exists('FacultyReportPDF')) {
    class FacultyReportPDF extends FPDF {
        function Header() {
            
            $this->Rect(5, 5, 200, 287);
            $this->SetLineWidth(0.5);
            $this->Rect(7, 7, 196, 283);

            
            $this->SetY(11);
            $this->SetFont('Arial', 'B', 18);
            $this->SetTextColor(234, 88, 12); 
            $this->Cell(0, 7, 'QUESTBANK ACADEMY', 0, 1, 'C');
            
            $this->SetFont('Arial', 'B', 8.5);
            $this->SetTextColor(120, 113, 108);
            $this->Cell(0, 4.5, 'DEPARTMENT OF CIVIL ENGINEERING & FACULTY ASSESSMENT', 0, 1, 'C');
            
            $this->SetFont('Arial', 'B', 9.5);
            $this->SetTextColor(28, 25, 23);
            $this->Cell(0, 5, 'CLASS PERFORMANCE & STATISTICAL ANALYTICS REPORT', 0, 1, 'C');
            
            
            $this->SetDrawColor(249, 115, 22);
            $this->SetLineWidth(0.8);
            $this->Line(15, 30, 195, 30);
        }

        function Footer() {
            $this->SetY(-18);
            $this->SetFont('Arial', 'I', 8);
            $this->SetTextColor(168, 162, 158);
            $this->Cell(0, 4, 'This faculty report is generated & verified by QuestBank AI Optical Answer Sheet Evaluator Engine.', 0, 1, 'C');
            $this->Cell(0, 4, 'Report Audit Code: QB-FACULTY-2026-' . strtoupper(md5('PAGE_' . $this->PageNo())), 0, 0, 'C');
        }
    }
}

$pdf = new FacultyReportPDF('P', 'mm', 'A4');
$pdf->SetMargins(15, 15, 15);
$pdf->AddPage();

$pdf->SetFillColor(255, 247, 237); 
$pdf->SetDrawColor(254, 215, 170);
$pdf->SetLineWidth(0.3);
$pdf->Rect(15, 34, 180, 27, 'DF');

$pdf->SetFont('Arial', 'B', 7.5);
$pdf->SetTextColor(194, 65, 12);
$pdf->SetXY(20, 36.5);
$pdf->Cell(85, 4, 'FACULTY INSTRUCTOR:', 0, 0, 'L');
$pdf->Cell(85, 4, 'REPORT CATEGORY / FILTER:', 0, 1, 'L');

$pdf->SetFont('Arial', 'B', 10);
$pdf->SetTextColor(28, 25, 23);
$pdf->SetX(20);
$pdf->Cell(85, 5, $teacher_name, 0, 0, 'L');
$pdf->SetTextColor(234, 88, 12);
$pdf->Cell(85, 5, $selected_exam === 'all' ? 'All Evaluated Exams' : $selected_exam, 0, 1, 'L');

$pdf->SetFont('Arial', 'B', 7.5);
$pdf->SetTextColor(194, 65, 12);
$pdf->SetXY(20, 48);
$pdf->Cell(85, 4, 'ACADEMIC DEPARTMENT:', 0, 0, 'L');
$pdf->Cell(85, 4, 'DATE ISSUED:', 0, 1, 'L');

$pdf->SetFont('Arial', 'B', 9.5);
$pdf->SetTextColor(28, 25, 23);
$pdf->SetX(20);
$pdf->Cell(85, 5, 'BS Civil Engineering', 0, 0, 'L');
$pdf->Cell(85, 5, $date_issued, 0, 1, 'L');

$pdf->SetY(67);
$pdf->SetFont('Arial', 'B', 9.5);
$pdf->SetTextColor(28, 25, 23);
$pdf->Cell(0, 5, 'STATISTICAL PERFORMANCE METRICS SUMMARY', 0, 1, 'L');
$pdf->Ln(2);

$kpiY = $pdf->GetY();
$pdf->SetFillColor(250, 250, 249);
$pdf->SetDrawColor(229, 229, 224);

$boxes = [
    ['title' => 'TOTAL SCANNED', 'val' => (string)$total, 'color' => [28, 25, 23]],
    ['title' => 'PASSED', 'val' => (string)$pass, 'color' => [21, 128, 61]],
    ['title' => 'FAILED', 'val' => (string)$fail, 'color' => [190, 18, 60]],
    ['title' => 'PASS RATE', 'val' => number_format($pass_rate, 1) . '%', 'color' => [234, 88, 12]],
    ['title' => 'CLASS AVERAGE', 'val' => number_format($avg, 1) . '%', 'color' => [28, 25, 23]],
    ['title' => 'HIGHEST SCORE', 'val' => number_format($max, 1) . '%', 'color' => [21, 128, 61]]
];

$boxW = 28;
$gap = 2.4;
$startX = 15;

foreach ($boxes as $idx => $b) {
    $xPos = $startX + ($idx * ($boxW + $gap));
    $pdf->Rect($xPos, $kpiY, $boxW, 15, 'DF');
    
    
    $pdf->SetXY($xPos, $kpiY + 2);
    $pdf->SetFont('Arial', 'B', 6);
    $pdf->SetTextColor(120, 113, 108);
    $pdf->Cell($boxW, 3, $b['title'], 0, 0, 'C');
    
    
    $pdf->SetXY($xPos, $kpiY + 6.5);
    $pdf->SetFont('Arial', 'B', 10);
    $pdf->SetTextColor($b['color'][0], $b['color'][1], $b['color'][2]);
    $pdf->Cell($boxW, 6, $b['val'], 0, 0, 'C');
}

$pdf->SetY($kpiY + 20);

$pdf->SetFont('Arial', 'B', 9.5);
$pdf->SetTextColor(28, 25, 23);
$pdf->Cell(0, 5, 'STUDENT GRADE SUBMISSIONS MASTER LIST', 0, 1, 'L');
$pdf->Ln(2);

$pdf->SetFillColor(41, 37, 36); 
$pdf->SetTextColor(255, 255, 255);
$pdf->SetFont('Arial', 'B', 7.5);
$pdf->Cell(50, 7, ' STUDENT NAME', 1, 0, 'L', true);
$pdf->Cell(65, 7, 'EXAM TITLE', 1, 0, 'L', true);
$pdf->Cell(20, 7, 'FORMAT', 1, 0, 'C', true);
$pdf->Cell(15, 7, 'SCORE', 1, 0, 'C', true);
$pdf->Cell(15, 7, 'GRADE %', 1, 0, 'C', true);
$pdf->Cell(15, 7, 'STATUS', 1, 1, 'C', true);

$pdf->SetFont('Arial', '', 7.5);
$pdf->SetTextColor(28, 25, 23);
$fill = false;

foreach ($submissions as $row) {
    $pdf->SetFillColor(250, 250, 249);
    $pdf->Cell(50, 7, ' ' . substr($row['student_name'], 0, 28), 1, 0, 'L', $fill);
    $pdf->Cell(65, 7, substr($row['exam_title'], 0, 36), 1, 0, 'L', $fill);
    $pdf->Cell(20, 7, strtoupper($row['upload_type'] ?? 'SCANNED'), 1, 0, 'C', $fill);
    $pdf->Cell(15, 7, ($row['correct_count'] ?? 0) . ' / ' . ($row['total_items'] ?? 10), 1, 0, 'C', $fill);
    
    $pdf->SetFont('Arial', 'B', 7.5);
    $pdf->Cell(15, 7, number_format((float)($row['percentage'] ?? 0), 1) . '%', 1, 0, 'C', $fill);
    
    if (($row['status'] ?? '') === 'Pass' || ($row['percentage'] ?? 0) >= 75) {
        $pdf->SetTextColor(21, 128, 61); 
        $pdf->Cell(15, 7, 'PASSED', 1, 1, 'C', $fill);
    } else {
        $pdf->SetTextColor(190, 18, 60); 
        $pdf->Cell(15, 7, 'FAILED', 1, 1, 'C', $fill);
    }
    
    $pdf->SetFont('Arial', '', 7.5);
    $pdf->SetTextColor(28, 25, 23);
    $fill = !$fill;
}

$pdf->SetY(232);
$pdf->SetFont('Arial', 'B', 8);
$pdf->SetTextColor(120, 113, 108);

$pdf->Cell(85, 4, '_______________________________________', 0, 0, 'C');
$pdf->Cell(10, 4, '', 0, 0);
$pdf->Cell(85, 4, '_______________________________________', 0, 1, 'C');

$pdf->SetFont('Arial', 'B', 8.5);
$pdf->SetTextColor(28, 25, 23);
$pdf->Cell(85, 4, 'FACULTY INSTRUCTOR / PROFESSOR', 0, 0, 'C');
$pdf->Cell(10, 4, '', 0, 0);
$pdf->Cell(85, 4, 'CIVIL ENGINEERING DEPARTMENT CHAIR', 0, 1, 'C');

$pdf->Output('I', 'QuestBank_Faculty_Analytics_Report.pdf');
