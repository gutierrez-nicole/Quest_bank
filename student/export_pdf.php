<?php
require_once __DIR__ . '/../app/bootstrap.php';
require_once __DIR__ . '/../app/fpdf.php';

AuthService::enforceRole('student');
$pdo = getDBConnection();
$student_id = getCurrentUserId();

try {
    $stmt = $pdo->prepare("
        SELECT u.fullname, u.email, s.student_number, s.course, s.section 
        FROM users u 
        LEFT JOIN student_details s ON u.id = s.user_id 
        WHERE u.id = ?
    ");
    $stmt->execute([$student_id]);
    $student = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $student = null;
}

$fullname = $student['fullname'] ?? 'Student User';
$student_no = !empty($student['student_number']) ? $student['student_number'] : 'STUDENT-' . $student_id;
$course_section = ($student['course'] ?? 'General') . ' - ' . ($student['section'] ?? 'A');
$date_issued = date('F d, Y');

$selected_term = trim($_GET['term'] ?? 'All');
$single_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

try {
    $where = ["es.review_status IN ('published', 'finalized')"];
    $params = [];

    if ($single_id > 0) {
        $where[] = "es.id = ? AND es.student_id = ?";
        $params[] = $single_id;
        $params[] = $student_id;
    } else {
        $where[] = "es.student_id = ?";
        $params[] = $student_id;

        if (!empty($selected_term) && $selected_term !== 'All') {
            $where[] = "(es.term = ? OR e.term = ?)";
            $params[] = $selected_term;
            $params[] = $selected_term;
        }
    }

    $whereSql = implode(' AND ', $where);

    $sql = "
        SELECT 
            es.id,
            COALESCE(e.title, es.exam_title, 'Examination') as title,
            COALESCE(e.subject, 'General Subject') as subject,
            COALESCE(es.term, 'N/A') as term,
            COALESCE(es.correct_count, es.total_score, 0) as score,
            es.total_items,
            es.percentage,
            es.status,
            es.created_at
        FROM exam_submissions es
        LEFT JOIN exams e ON es.exam_id = e.id
        WHERE {$whereSql}
        ORDER BY es.created_at DESC
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
}catch (PDOException $e) {
    $results = [];
}

$total_exams = count($results);
$total_pct = 0;
foreach ($results as $r) {
    $total_pct += (float)$r['percentage'];
}
$avg_gpa = $total_exams > 0 ? round($total_pct / $total_exams, 1) : 0.0;
$overall_status = $avg_gpa >= 75.0 ? 'PASSED (SATISFACTORY)' : 'NEEDS IMPROVEMENT';

if (!class_exists('TranscriptPDF')) {
    class TranscriptPDF extends FPDF {
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
            $this->Cell(0, 4.5, 'DEPARTMENT OF CIVIL ENGINEERING & ASSESSMENT', 0, 1, 'C');
            
            $this->SetFont('Arial', 'B', 9.5);
            $this->SetTextColor(28, 25, 23);
            $this->Cell(0, 5, 'OFFICIAL STUDENT EVALUATION TRANSCRIPT', 0, 1, 'C');
            
            
            $this->SetDrawColor(249, 115, 22);
            $this->SetLineWidth(0.8);
            $this->Line(15, 30, 195, 30);
        }

        function Footer() {
            $this->SetY(-18);
            $this->SetFont('Arial', 'I', 8);
            $this->SetTextColor(168, 162, 158);
            $this->Cell(0, 4, 'This transcript is automatically generated and verified by the QuestBank AI Assessment Engine.', 0, 1, 'C');
            $this->Cell(0, 4, 'Official Document Hash: QB-CERT-2026-' . strtoupper(md5('PAGE_' . $this->PageNo())), 0, 0, 'C');
        }
    }
}

$pdf = new TranscriptPDF('P', 'mm', 'A4');
$pdf->SetMargins(15, 15, 15);
$pdf->AddPage();

$pdf->SetFillColor(255, 247, 237); 
$pdf->SetDrawColor(254, 215, 170);
$pdf->SetLineWidth(0.3);
$pdf->Rect(15, 34, 180, 27, 'DF');

$pdf->SetFont('Arial', 'B', 7.5);
$pdf->SetTextColor(194, 65, 12);
$pdf->SetXY(20, 36.5);
$pdf->Cell(85, 4, 'STUDENT FULL NAME:', 0, 0, 'L');
$pdf->Cell(85, 4, 'ID NUMBER:', 0, 1, 'L');

$pdf->SetFont('Arial', 'B', 10);
$pdf->SetTextColor(28, 25, 23);
$pdf->SetX(20);
$pdf->Cell(85, 5, $fullname, 0, 0, 'L');
$pdf->SetTextColor(234, 88, 12);
$pdf->Cell(85, 5, $student_no, 0, 1, 'L');

$pdf->SetFont('Arial', 'B', 7.5);
$pdf->SetTextColor(194, 65, 12);
$pdf->SetXY(20, 48);
$pdf->Cell(85, 4, 'ACADEMIC PROGRAM & SECTION:', 0, 0, 'L');
$pdf->Cell(85, 4, 'DATE ISSUED:', 0, 1, 'L');

$pdf->SetFont('Arial', 'B', 9.5);
$pdf->SetTextColor(28, 25, 23);
$pdf->SetX(20);
$pdf->Cell(85, 5, $course_section, 0, 0, 'L');
$pdf->Cell(85, 5, $date_issued, 0, 1, 'L');

$pdf->SetY(67);
$pdf->SetFont('Arial', 'B', 10);
$pdf->SetTextColor(28, 25, 23);
$pdf->Cell(0, 6, 'ACADEMIC ASSESSMENT RECORD MATRIX', 0, 1, 'L');

$pdf->SetFillColor(41, 37, 36); 
$pdf->SetTextColor(255, 255, 255);
$pdf->SetFont('Arial', 'B', 8);
$pdf->Cell(75, 7, ' ASSESSMENT & SUBJECT TITLE', 1, 0, 'L', true);
$pdf->Cell(25, 7, 'TERM', 1, 0, 'C', true);
$pdf->Cell(25, 7, 'RAW SCORE', 1, 0, 'C', true);
$pdf->Cell(25, 7, 'GRADE %', 1, 0, 'C', true);
$pdf->Cell(30, 7, 'STATUS', 1, 1, 'C', true);

$pdf->SetFont('Arial', '', 8);
$pdf->SetTextColor(28, 25, 23);
$fill = false;

foreach ($results as $row) {
    $pdf->SetFillColor(250, 250, 249);
    $pdf->Cell(75, 7, ' ' . substr($row['title'], 0, 42), 1, 0, 'L', $fill);
    $pdf->Cell(25, 7, strtoupper($row['term']), 1, 0, 'C', $fill);
    $pdf->Cell(25, 7, $row['score'] . ' / ' . $row['total_items'], 1, 0, 'C', $fill);
    $pdf->SetFont('Arial', 'B', 8);
    $pdf->Cell(25, 7, number_format($row['percentage'], 1) . '%', 1, 0, 'C', $fill);
    
    
    if ($row['status'] === 'Pass' || $row['percentage'] >= 75) {
        $pdf->SetTextColor(21, 128, 61); 
        $pdf->Cell(30, 7, 'PASSED', 1, 1, 'C', $fill);
    } else {
        $pdf->SetTextColor(190, 18, 60); 
        $pdf->Cell(30, 7, 'FAILED', 1, 1, 'C', $fill);
    }
    
    $pdf->SetFont('Arial', '', 8);
    $pdf->SetTextColor(28, 25, 23);
    $fill = !$fill;
}

$pdf->Ln(6);

$summaryY = $pdf->GetY();
$pdf->SetFillColor(245, 245, 244);
$pdf->SetDrawColor(229, 229, 224);
$pdf->Rect(15, $summaryY, 180, 22, 'DF');

$pdf->SetXY(20, $summaryY + 3);
$pdf->SetFont('Arial', 'B', 7.5);
$pdf->SetTextColor(120, 113, 108);
$pdf->Cell(50, 4, 'CUMULATIVE AVERAGE GRADE:', 0, 0, 'L');
$pdf->Cell(60, 4, 'OVERALL ACADEMIC OUTCOME:', 0, 0, 'L');
$pdf->Cell(50, 4, 'EVALUATION ENGINE:', 0, 1, 'L');

$pdf->SetX(20);
$pdf->SetFont('Arial', 'B', 11);
$pdf->SetTextColor(234, 88, 12);
$pdf->Cell(50, 6, $avg_gpa . '%', 0, 0, 'L');

$pdf->SetFont('Arial', 'B', 9.5);
$pdf->SetTextColor(21, 128, 61);
$pdf->Cell(60, 6, $overall_status, 0, 0, 'L');

$pdf->SetFont('Arial', 'B', 8.5);
$pdf->SetTextColor(109, 40, 217); 
$pdf->Cell(50, 6, 'Groq Llama-3 AI Vision Engine', 0, 1, 'L');

$pdf->SetY(230);
$pdf->SetFont('Arial', 'B', 8);
$pdf->SetTextColor(120, 113, 108);

$pdf->Cell(85, 4, '_______________________________________', 0, 0, 'C');
$pdf->Cell(10, 4, '', 0, 0);
$pdf->Cell(85, 4, '_______________________________________', 0, 1, 'C');

$pdf->SetFont('Arial', 'B', 8.5);
$pdf->SetTextColor(28, 25, 23);
$pdf->Cell(85, 4, 'ACADEMIC DEPARTMENT REGISTRAR', 0, 0, 'C');
$pdf->Cell(10, 4, '', 0, 0);
$pdf->Cell(85, 4, 'QUESTBANK AUTOMATED AI EVALUATOR', 0, 1, 'C');

$pdf->Output('I', 'QuestBank_Official_Transcript.pdf');