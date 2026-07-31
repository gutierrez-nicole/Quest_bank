<?php

require_once __DIR__ . '/../app/fpdf.php';

$dir = __DIR__ . '/fixtures';
if (!is_dir($dir)) {
    mkdir($dir, 0755, true);
}

// 1. Printed Clear Image
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

// 2. Rotated Image
$imgRotated = imagerotate($imgClear, 15, $bg);
imagepng($imgRotated, $dir . '/rotated_image.png');

// 3. Low Resolution Image (150x150)
$imgLowRes = imagecreatetruecolor(150, 150);
imagefill($imgLowRes, 0, 0, $bg);
imagestring($imgLowRes, 2, 10, 10, "LowRes 1.A", $txtColor);
imagepng($imgLowRes, $dir . '/low_res.png');

// 4. Handwritten style image
$imgHandwritten = imagecreatetruecolor(800, 400);
imagefill($imgHandwritten, 0, 0, $bg);
imagestring($imgHandwritten, 4, 40, 50, "Student Answer: 1. B, 2. False, 3. Yield Stress = 420 MPa", $txtColor);
imagepng($imgHandwritten, $dir . '/handwritten.png');

// 5. Math expression image
$imgMath = imagecreatetruecolor(800, 400);
imagefill($imgMath, 0, 0, $bg);
imagestring($imgMath, 5, 40, 50, "Formula: sigma = P / A; M_u = phi * M_n", $txtColor);
imagepng($imgMath, $dir . '/math_expression.png');

// 6. Multi-page PDF
$pdfMulti = new FPDF();
$pdfMulti->AddPage();
$pdfMulti->SetFont('Arial', 'B', 14);
$pdfMulti->Cell(0, 10, 'Multi-Page Assessment - Page 1', 0, 1);
$pdfMulti->MultiCell(0, 8, "Structural Mechanics Question 1: Calculate shear stress tau = V * Q / (I * b).");
$pdfMulti->AddPage();
$pdfMulti->Cell(0, 10, 'Multi-Page Assessment - Page 2', 0, 1);
$pdfMulti->MultiCell(0, 8, "Geotechnical Mechanics Question 2: Calculate effective stress sigma_eff = sigma - u.");
$pdfMulti->Output('F', $dir . '/multipage.pdf');

// 7. Blank Image
$imgBlank = imagecreatetruecolor(800, 600);
imagefill($imgBlank, 0, 0, $bg);
imagepng($imgBlank, $dir . '/blank_page.png');

// 8. Corrupted PDF
file_put_contents($dir . '/corrupted.pdf', "NOT_A_VALID_PDF_HEADER_CONTENT_12345");

// 9. Empty DOCX (0 bytes)
file_put_contents($dir . '/empty.docx', "");

// 10. Fake MIME PDF (Plain text renamed with .pdf extension)
file_put_contents($dir . '/fake_mime.pdf', "This is plain text with a .pdf extension.");

echo "Extended test fixtures created in " . realpath($dir) . "\n";
