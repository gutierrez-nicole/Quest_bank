<?php

require_once __DIR__ . '/../app/fpdf.php';

$dir = __DIR__ . '/fixtures';
if (!is_dir($dir)) {
    mkdir($dir, 0755, true);
}

// 1. TXT Fixture
$txtContent = "Flexural resistance of reinforced concrete beams under Ultimate Limit State (ULS) flexural moment M_u = 250 kN-m. Shear capacity V_c = 0.17 * sqrt(f_c) * b_w * d. Steel ratio rho = A_s / (b * d). Yield strength f_y = 420 MPa.";
file_put_contents($dir . '/sample_lesson.txt', $txtContent);

// 2. DOCX Fixture (Creates a minimal valid .docx zip with word/document.xml)
$zip = new ZipArchive();
$docxPath = $dir . '/sample_lesson.docx';
if ($zip->open($docxPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) === true) {
    $xmlContent = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">'
        . '<w:body>'
        . '<w:p><w:r><w:t>Geotechnical Soil Mechanics and Foundation Settlement calculation under Terzaghi bearing capacity q_ult = c * N_c + q * N_q + 0.5 * gamma * B * N_gamma.</w:t></w:r></w:p>'
        . '</w:body>'
        . '</w:document>';
    $zip->addFromString('word/document.xml', $xmlContent);
    $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="xml" ContentType="application/xml"/></Types>');
    $zip->close();
}

// 3. PPTX Fixture (Creates a minimal valid .pptx zip with ppt/slides/slide1.xml)
$zipP = new ZipArchive();
$pptxPath = $dir . '/sample_lesson.pptx';
if ($zipP->open($pptxPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) === true) {
    $slideXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<p:sld xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main" xmlns:p="http://schemas.openxmlformats.org/presentationml/2006/main">'
        . '<p:cSld><p:spTree><p:sp><p:txBody><a:p><a:r><a:t>Structural Dynamics and Earthquake Engineering: Fundamental natural period T = 2 * pi * sqrt(m / k). Seismic response spectrum analysis for high-rise frames.</a:t></a:r></a:p></p:txBody></p:sp></p:spTree></p:cSld>'
        . '</p:sld>';
    $zipP->addFromString('ppt/slides/slide1.xml', $slideXml);
    $zipP->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="xml" ContentType="application/xml"/></Types>');
    $zipP->close();
}

// 4. PDF Fixture (Creates a valid PDF file using FPDF)
$pdf = new FPDF();
$pdf->AddPage();
$pdf->SetFont('Arial', 'B', 14);
$pdf->Cell(0, 10, 'Hydraulics and Fluid Mechanics Lesson Material', 0, 1);
$pdf->SetFont('Arial', '', 11);
$pdf->MultiCell(0, 8, "Manning friction factor n = 0.013. Open channel flow velocity V = (1/n) * R^(2/3) * S^(1/2). Hydraulic jump energy loss calculation and critical depth equation.");
$pdf->Output('F', $dir . '/sample_lesson.pdf');

// 5. Answer Sheet PNG Image
$img = imagecreatetruecolor(800, 1000);
$bg = imagecolorallocate($img, 255, 255, 255);
$text_color = imagecolorallocate($img, 30, 30, 30);
imagefill($img, 0, 0, $bg);
imagestring($img, 5, 50, 50, "CIVIL ENGINEERING EXAMINATION ANSWER SHEET", $text_color);
imagestring($img, 5, 50, 100, "Student Name: QA Student Alpha", $text_color);
imagestring($img, 5, 50, 150, "Exam Title: Structural Theory Final Exam", $text_color);
imagestring($img, 5, 50, 220, "1. A", $text_color);
imagestring($img, 5, 50, 260, "2. True", $text_color);
imagestring($img, 5, 50, 300, "3. Stress is force per area", $text_color);
imagestring($img, 5, 50, 340, "4. 250 kN-m", $text_color);
imagestring($img, 5, 50, 380, "5. A-2, B-1", $text_color);
imagepng($img, $dir . '/sample_answersheet.png');
imagedestroy($img);

// 6. Answer Sheet PDF
$pdfAns = new FPDF();
$pdfAns->AddPage();
$pdfAns->SetFont('Arial', 'B', 14);
$pdfAns->Cell(0, 10, 'CIVIL ENGINEERING ANSWER SHEET', 0, 1);
$pdfAns->SetFont('Arial', '', 11);
$pdfAns->MultiCell(0, 8, "Student: QA Student Alpha\nExam: Reinforced Concrete Quiz 1\n1. B\n2. False\n3. Ultimate Limit State\n4. 420 MPa\n5. True");
$pdfAns->Output('F', $dir . '/sample_answersheet.pdf');

echo "Fixtures created successfully in " . realpath($dir) . "\n";
