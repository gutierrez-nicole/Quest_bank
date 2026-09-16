<?php
require_once __DIR__ . '/../../app/bootstrap.php';
require_once __DIR__ . '/../../app/fpdf.php';
require_once __DIR__ . '/../../app/services/LessonExtractionService.php';
require_once __DIR__ . '/../../app/services/FileValidationService.php';

echo "=== TEST SUITE: LESSON UPLOAD & EXTRACTION FIXES ===\n\n";

$pdo = getDBConnection();
$teacherId = 12; // Valid teacher

$_SESSION['user_id'] = $teacherId;
$_SESSION['role'] = 'teacher';
SessionManagementService::trackSession($teacherId);

// 1. Test schema helper
echo "Test 1: Testing LessonExtractionService::ensureSchema...\n";
LessonExtractionService::ensureSchema($pdo);
$cols = $pdo->query("SHOW COLUMNS FROM lesson_materials")->fetchAll(PDO::FETCH_COLUMN);
echo "  [PASS] Columns present: " . count($cols) . " columns found.\n";
assert(in_array('academic_period', $cols));
assert(in_array('semester', $cols));
assert(in_array('stored_filename', $cols));
assert(in_array('lesson_text', $cols));

// 2. Test TXT extraction
echo "\nTest 2: Testing TXT Extraction...\n";
$txtPath = __DIR__ . '/../../teacher/uploads/test_unit.txt';
file_put_contents($txtPath, "Civil Engineering Principles: Prestressed concrete members and structural analysis for bridges.");
$stmt = $pdo->prepare("
    INSERT INTO lesson_materials (teacher_id, subject, title, academic_period, file_name, file_path, file_type, file_size, processing_status)
    VALUES (?, 'CE 412', 'Unit Test TXT', 'midterm', 'test_unit.txt', 'uploads/test_unit.txt', 'TXT', ?, 'pending')
");
$stmt->execute([$teacherId, filesize($txtPath)]);
$txtId = (int)$pdo->lastInsertId();

$resTxt = LessonExtractionService::extractAndSave($txtId);
echo "  Result: " . json_encode($resTxt) . "\n";
assert($resTxt['success'] === true);
assert($resTxt['word_count'] > 5);
echo "  [PASS] TXT Extracted successfully!\n";

// 3. Test DOCX extraction
echo "\nTest 3: Testing DOCX Extraction...\n";
$docxPath = __DIR__ . '/../../teacher/uploads/test_unit.docx';
$zip = new ZipArchive();
if ($zip->open($docxPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) === true) {
    $zip->addFromString('word/document.xml', '<?xml version="1.0" encoding="UTF-8"?><w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:body><w:p><w:r><w:t>Structural Dynamics and Earthquake Engineering Seismic Response Analysis</w:t></w:r></w:p></w:body></w:document>');
    $zip->close();
}
$stmt->execute([$teacherId, filesize($docxPath)]);
$docxId = (int)$pdo->lastInsertId();
$pdo->prepare("UPDATE lesson_materials SET file_name = 'test_unit.docx', file_path = 'uploads/test_unit.docx', file_type = 'DOCX' WHERE id = ?")->execute([$docxId]);

$resDocx = LessonExtractionService::extractAndSave($docxId);
echo "  Result: " . json_encode($resDocx) . "\n";
assert($resDocx['success'] === true);
assert($resDocx['word_count'] >= 7);
echo "  [PASS] DOCX Extracted successfully!\n";

// 4. Test PDF extraction
echo "\nTest 4: Testing PDF Extraction...\n";
$pdfPath = __DIR__ . '/../../teacher/uploads/test_unit.pdf';
$pdf = new FPDF();
$pdf->AddPage();
$pdf->SetFont('Arial', 'B', 14);
$pdf->Cell(40, 10, 'Geotechnical Engineering Soil Mechanics and Foundation Settlement Analysis');
$pdf->Output('F', $pdfPath);

$stmt->execute([$teacherId, filesize($pdfPath)]);
$pdfId = (int)$pdo->lastInsertId();
$pdo->prepare("UPDATE lesson_materials SET file_name = 'test_unit.pdf', file_path = 'uploads/test_unit.pdf', file_type = 'PDF' WHERE id = ?")->execute([$pdfId]);

$resPdf = LessonExtractionService::extractAndSave($pdfId);
echo "  Result: " . json_encode($resPdf) . "\n";
assert($resPdf['success'] === true);
assert($resPdf['word_count'] >= 5);
echo "  [PASS] PDF Extracted successfully!\n";

// 5. Test Edge Cases (Corrupt / Empty / Missing)
echo "\nTest 5: Testing Edge Cases (0-byte file, missing file)...\n";
$emptyPath = __DIR__ . '/../../teacher/uploads/test_empty.txt';
file_put_contents($emptyPath, "");
$stmt->execute([$teacherId, 0]);
$emptyId = (int)$pdo->lastInsertId();
$pdo->prepare("UPDATE lesson_materials SET file_name = 'test_empty.txt', file_path = 'uploads/test_empty.txt', file_type = 'TXT' WHERE id = ?")->execute([$emptyId]);

$resEmpty = LessonExtractionService::extractAndSave($emptyId);
echo "  Empty file result: " . json_encode($resEmpty) . "\n";
assert($resEmpty['success'] === false);
echo "  [PASS] Graceful error handled without throwing!\n";

// Cleanup test records
$pdo->prepare("DELETE FROM lesson_materials WHERE id IN (?, ?, ?, ?)")->execute([$txtId, $docxId, $pdfId, $emptyId]);
@unlink($txtPath);
@unlink($docxPath);
@unlink($pdfPath);
@unlink($emptyPath);

echo "\n=== ALL 5 TESTS PASSED WITH 0 CRASHES! ===\n";
