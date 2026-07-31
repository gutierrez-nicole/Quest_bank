<?php

require_once __DIR__ . '/../app/fpdf.php';

$dir = __DIR__ . '/fixtures';
if (!is_dir($dir)) {
    mkdir($dir, 0755, true);
}

// 1. TXT Fixture
$txtContent = "Flexural resistance of reinforced concrete beams under Ultimate Limit State (ULS) flexural moment M_u = 250 kN-m. Shear capacity V_c = 0.17 * sqrt(f_c) * b_w * d. Steel ratio rho = A_s / (b * d). Yield strength f_y = 420 MPa.";
file_put_contents($dir . '/sample_lesson.txt', $txtContent);

// 2. DOCX Fixture
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

// 3. PPTX Fixture
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

// 4. PDF Fixture
$pdf = new FPDF();
$pdf->AddPage();
$pdf->SetFont('Arial', 'B', 14);
$pdf->Cell(0, 10, 'Hydraulics and Fluid Mechanics Lesson Material', 0, 1);
$pdf->SetFont('Arial', '', 11);
$pdf->MultiCell(0, 8, "Manning friction factor n = 0.013. Open channel flow velocity V = (1/n) * R^(2/3) * S^(1/2). Hydraulic jump energy loss calculation and critical depth equation.");
$pdf->Output('F', $dir . '/sample_lesson.pdf');

// 5. Printed Clear Image
$imgClear = imagecreatetruecolor(800, 600);
$bg = imagecolorallocate($imgClear, 255, 255, 255);
$txtColor = imagecolorallocate($imgClear, 0, 0, 0);
imagefill($imgClear, 0, 0, $bg);
imagestring($imgClear, 5, 50, 50, "CIVIL ENGINEERING TEST SHEET", $txtColor);
imagestring($imgClear, 5, 50, 100, "1. A", $txtColor);
imagestring($imgClear, 5, 50, 140, "2. True", $txtColor);
imagestring($imgClear, 5, 50, 180, "3. Beam Web Width", $txtColor);
imagestring($imgClear, 5, 50, 220, "4. 0.17", $txtColor);
imagestring($imgClear, 5, 50, 260, "5. 250 kN-m", $txtColor);
imagepng($imgClear, $dir . '/printed_clear.png');
imagepng($imgClear, $dir . '/sample_answersheet.png');

// 6. Rotated Image
$imgRotated = imagerotate($imgClear, 15, $bg);
imagepng($imgRotated, $dir . '/rotated_image.png');

// 7. Low Resolution Image (150x150)
$imgLowRes = imagecreatetruecolor(150, 150);
imagefill($imgLowRes, 0, 0, $bg);
imagestring($imgLowRes, 2, 10, 10, "LowRes 1.A", $txtColor);
imagepng($imgLowRes, $dir . '/low_res.png');

// 8. Handwritten style image
$imgHandwritten = imagecreatetruecolor(800, 400);
imagefill($imgHandwritten, 0, 0, $bg);
imagestring($imgHandwritten, 4, 40, 50, "Student Answer: 1. B, 2. False, 3. Yield Stress = 420 MPa", $txtColor);
imagepng($imgHandwritten, $dir . '/handwritten.png');

// 9. Math expression image
$imgMath = imagecreatetruecolor(800, 400);
imagefill($imgMath, 0, 0, $bg);
imagestring($imgMath, 5, 40, 50, "Formula: sigma = P / A; M_u = phi * M_n", $txtColor);
imagepng($imgMath, $dir . '/math_expression.png');

// 10. Multi-page PDF
$pdfMulti = new FPDF();
$pdfMulti->AddPage();
$pdfMulti->SetFont('Arial', 'B', 14);
$pdfMulti->Cell(0, 10, 'Multi-Page Assessment - Page 1', 0, 1);
$pdfMulti->MultiCell(0, 8, "Structural Mechanics Question 1: Calculate shear stress tau = V * Q / (I * b).");
$pdfMulti->AddPage();
$pdfMulti->Cell(0, 10, 'Multi-Page Assessment - Page 2', 0, 1);
$pdfMulti->MultiCell(0, 8, "Geotechnical Mechanics Question 2: Calculate effective stress sigma_eff = sigma - u.");
$pdfMulti->Output('F', $dir . '/multipage.pdf');
$pdfMulti->Output('F', $dir . '/sample_answersheet.pdf');

// 11. Blank Image
$imgBlank = imagecreatetruecolor(800, 600);
imagefill($imgBlank, 0, 0, $bg);
imagepng($imgBlank, $dir . '/blank_page.png');

// 12. Corrupted PDF
file_put_contents($dir . '/corrupted.pdf', "NOT_A_VALID_PDF_HEADER_CONTENT_12345");

// 13. Empty DOCX (0 bytes)
file_put_contents($dir . '/empty.docx', "");

// 14. Fake MIME PDF (Plain text renamed with .pdf extension)
file_put_contents($dir . '/fake_mime.pdf', "This is plain text with a .pdf extension.");

echo "All test fixtures generated successfully in " . realpath($dir) . "\n";
