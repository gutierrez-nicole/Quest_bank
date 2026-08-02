<?php
/**
 * Automated Test Suite for PROMPT 3 — Real Answer-Sheet OCR Pipeline
 */

require_once __DIR__ . '/../app/bootstrap.php';
require_once __DIR__ . '/../app/services/OcrService.php';

$tempDir = __DIR__ . '/fixtures_p3';
if (!is_dir($tempDir)) {
    mkdir($tempDir, 0777, true);
}

$passed = 0;
$failed = 0;

function runTestCase($name, $callable) {
    global $passed, $failed;
    echo "[TEST] {$name}... ";
    try {
        $result = $callable();
        if ($result === true) {
            echo "PASSED\n";
            $passed++;
        } else {
            echo "FAILED: " . (is_string($result) ? $result : 'Assertion failed') . "\n";
            $failed++;
        }
    } catch (Throwable $e) {
        echo "FAILED Exception: " . $e->getMessage() . "\n";
        $failed++;
    }
}

// 1. Valid PDF Answer Sheet Test
runTestCase("Valid PDF Answer Sheet Processing", function() use ($tempDir) {
    $filePath = $tempDir . '/valid_answersheet.pdf';
    $pdfData = "%PDF-1.4\n" .
               "1 0 obj <</Type /Catalog /Pages 2 0 R>> endobj\n" .
               "2 0 obj <</Type /Pages /Kids [3 0 R] /Count 1>> endobj\n" .
               "3 0 obj <</Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Contents 4 0 R>> endobj\n" .
               "4 0 obj <</Length 80>> stream\n" .
               "BT /F1 12 Tf 50 700 Td (1. A 2. B 3. C 4. True 5. Flexural Stress) Tj ET\n" .
               "endstream endobj\n" .
               "xref\n0 5\n0000000000 65535 f\n" .
               "trailer <</Size 5 /Root 1 0 R>>\nstartxref\n250\n%%EOF";
    file_put_contents($filePath, $pdfData);

    $res = OcrService::processAnswerSheet($filePath, 'pdf');

    if (!$res['success']) return "Processing failed: " . ($res['error'] ?? '');
    if (strpos($res['ocr_text'], "1. A") === false) return "OCR text missing expected text";
    if ($res['confidence'] < 75) return "Confidence too low for clear PDF";

    return true;
});

// 2. Blank Image Page Test
runTestCase("Blank Image Page Handling", function() use ($tempDir) {
    $filePath = $tempDir . '/blank.png';
    $img = imagecreatetruecolor(200, 200);
    $bg = imagecolorallocate($img, 255, 255, 255);
    imagefill($img, 0, 0, $bg);
    imagepng($img, $filePath);
    imagedestroy($img);

    $res = OcrService::processAnswerSheet($filePath, 'png');

    if ($res['status'] !== 'completed') return "Expected completed status for blank image, got " . $res['status'];
    if (!empty($res['ocr_text'])) return "Blank image should produce empty text, got: " . $res['ocr_text'];

    return true;
});

// 3. Corrupted Image File Test
runTestCase("Corrupted Image File Handling", function() use ($tempDir) {
    $filePath = $tempDir . '/corrupt.png';
    file_put_contents($filePath, "NOT A REAL PNG FILE DATA");

    $res = OcrService::processAnswerSheet($filePath, 'png');

    if ($res['success'] !== false) return "Expected failure for corrupt image";
    if ($res['status'] !== 'failed') return "Expected status failed, got " . $res['status'];
    if ($res['suggested_manual_review'] !== true) return "Expected manual review flag set";

    return true;
});

// 4. Encrypted PDF Test
runTestCase("Encrypted PDF Handling", function() use ($tempDir) {
    $filePath = $tempDir . '/encrypted.pdf';
    $pdfData = "%PDF-1.4\n1 0 obj <</Type /Catalog /Pages 2 0 R /Encrypt 3 0 R>> endobj\n%%EOF";
    file_put_contents($filePath, $pdfData);

    $res = OcrService::processAnswerSheet($filePath, 'pdf');

    if ($res['success'] !== false) return "Expected failure for encrypted PDF";
    if (strpos($res['error'], "encrypted") === false) return "Expected encrypted error message";

    return true;
});

// 5. Scanned PDF Without Text Layer Test
runTestCase("Scanned PDF Without Text Layer Handling", function() use ($tempDir) {
    $filePath = $tempDir . '/scanned.pdf';
    $pdfData = "%PDF-1.4\n" .
               "1 0 obj <</Type /Catalog /Pages 2 0 R>> endobj\n" .
               "2 0 obj <</Type /Pages /Kids [3 0 R] /Count 1>> endobj\n" .
               "3 0 obj <</Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Contents 4 0 R>> endobj\n" .
               "4 0 obj <</Length 10>> stream\n1 2 3 4\nendstream endobj\n" .
               "xref\n0 5\n0000000000 65535 f\n" .
               "trailer <</Size 5 /Root 1 0 R>>\nstartxref\n200\n%%EOF";
    file_put_contents($filePath, $pdfData);

    $res = OcrService::processAnswerSheet($filePath, 'pdf');

    if ($res['suggested_manual_review'] !== true) return "Scanned PDF without text layer must flag manual review";
    if ($res['status'] !== 'manual_review_required') return "Expected status manual_review_required, got " . $res['status'];

    return true;
});

// 6. No Fabricated Answers Test
runTestCase("No Fabricated Answers on Failed/Unclear OCR", function() use ($tempDir) {
    $filePath = $tempDir . '/empty.txt';
    file_put_contents($filePath, '');

    $res = OcrService::processAnswerSheet($filePath, 'png');
    if (!empty($res['ocr_text'])) return "Failed/Empty OCR must never fabricate sample text";

    return true;
});

// Summary
echo "\n=========================================\n";
echo "PROMPT 3 TEST RESULTS: Passed {$passed}, Failed {$failed}\n";
echo "=========================================\n";

if ($failed > 0) {
    exit(1);
} else {
    exit(0);
}
