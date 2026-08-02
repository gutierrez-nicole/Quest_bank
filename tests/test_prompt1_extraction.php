<?php
/**
 * Automated Test Suite for PROMPT 1 — Lesson File Extraction Engine
 */

require_once __DIR__ . '/../app/bootstrap.php';
require_once __DIR__ . '/../app/services/LessonExtractionService.php';

$pdo = getDBConnection();
$tempDir = __DIR__ . '/fixtures_p1';
if (!is_dir($tempDir)) {
    mkdir($tempDir, 0777, true);
}

$teacherStmt = $pdo->query("SELECT id FROM users WHERE role = 'teacher' LIMIT 1");
$teacherId = $teacherStmt->fetchColumn() ?: 1;

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

// -------------------------------------------------------------
// Helper to insert lesson record into DB
// -------------------------------------------------------------
function createTestLessonRecord($pdo, $teacherId, $title, $filename, $filePath, $fileType, $fileSize) {
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = file_exists($filePath) ? finfo_file($finfo, $filePath) : 'application/octet-stream';
    finfo_close($finfo);

    $stmt = $pdo->prepare("
        INSERT INTO lesson_materials 
        (teacher_id, subject, title, file_name, file_path, file_type, file_size, processing_status, original_filename, stored_filename, mime_type) 
        VALUES (?, 'Test Subject', ?, ?, ?, ?, ?, 'pending', ?, ?, ?)
    ");
    $stmt->execute([
        $teacherId,
        $title,
        $filename,
        $filePath,
        strtoupper($fileType),
        $fileSize,
        $filename,
        basename($filePath),
        $mimeType
    ]);
    return $pdo->lastInsertId();
}

// 1. Valid TXT Test
runTestCase("Valid TXT File Extraction", function() use ($pdo, $teacherId, $tempDir) {
    $filePath = $tempDir . '/valid_lesson.txt';
    $content = "Civil Engineering Lesson 1: Fundamentals of Structural Analysis.\n" .
               "1. Beam deflection is proportional to applied load.\n" .
               "2. Concrete has high compressive strength.";
    file_put_contents($filePath, $content);

    $id = createTestLessonRecord($pdo, $teacherId, "Valid TXT Lesson", "valid_lesson.txt", "tests/fixtures_p1/valid_lesson.txt", "txt", filesize($filePath));
    $res = LessonExtractionService::extractAndSave($id);

    if (!$res['success']) return "Extraction failed: " . ($res['error'] ?? '');

    $stmt = $pdo->prepare("SELECT lesson_text, processing_status, word_count FROM lesson_materials WHERE id = ?");
    $stmt->execute([$id]);
    $rec = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($rec['processing_status'] !== 'completed') return "Expected status completed, got " . $rec['processing_status'];
    if (strpos($rec['lesson_text'], "Fundamentals of Structural Analysis") === false) return "Extracted text missing expected content";
    if ($rec['word_count'] < 10) return "Word count incorrect";

    return true;
});

// 2. Valid DOCX Test
runTestCase("Valid DOCX File Extraction", function() use ($pdo, $teacherId, $tempDir) {
    $filePath = $tempDir . '/valid_lesson.docx';
    $zip = new ZipArchive();
    if ($zip->open($filePath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        return "Could not create docx zip";
    }

    $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
    <w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
        <w:body>
            <w:p><w:r><w:t>Geotechnical Engineering Lesson on Soil Mechanics.</w:t></w:r></w:p>
            <w:p><w:r><w:t>Permeability coefficient depends on soil grain size.</w:t></w:r></w:p>
        </w:body>
    </w:document>';

    $zip->addFromString('word/document.xml', $xml);
    $zip->addFromString('[Content_Types].xml', '<?xml version="1.0"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/></Types>');
    $zip->close();

    $id = createTestLessonRecord($pdo, $teacherId, "Valid DOCX Lesson", "valid_lesson.docx", "tests/fixtures_p1/valid_lesson.docx", "docx", filesize($filePath));
    $res = LessonExtractionService::extractAndSave($id);

    if (!$res['success']) return "DOCX Extraction failed: " . ($res['error'] ?? '');

    $stmt = $pdo->prepare("SELECT lesson_text, processing_status FROM lesson_materials WHERE id = ?");
    $stmt->execute([$id]);
    $rec = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($rec['processing_status'] !== 'completed') return "Expected status completed";
    if (strpos($rec['lesson_text'], "Soil Mechanics") === false) return "Extracted text missing expected content";

    return true;
});

// 3. Valid PPTX Test
runTestCase("Valid PPTX File Extraction", function() use ($pdo, $teacherId, $tempDir) {
    $filePath = $tempDir . '/valid_lesson.pptx';
    $zip = new ZipArchive();
    if ($zip->open($filePath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        return "Could not create pptx zip";
    }

    $slideXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
    <p:sld xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main" xmlns:p="http://schemas.openxmlformats.org/presentationml/2006/main">
        <p:cSld><p:spTree><p:sp><p:txBody>
            <a:p><a:r><a:t>Transportation Engineering Slide 1: Highway Capacity Manual.</a:t></a:r></a:p>
        </p:txBody></p:sp></p:spTree></p:cSld>
    </p:sld>';

    $zip->addFromString('ppt/slides/slide1.xml', $slideXml);
    $zip->close();

    $id = createTestLessonRecord($pdo, $teacherId, "Valid PPTX Lesson", "valid_lesson.pptx", "tests/fixtures_p1/valid_lesson.pptx", "pptx", filesize($filePath));
    $res = LessonExtractionService::extractAndSave($id);

    if (!$res['success']) return "PPTX Extraction failed: " . ($res['error'] ?? '');

    $stmt = $pdo->prepare("SELECT lesson_text, processing_status FROM lesson_materials WHERE id = ?");
    $stmt->execute([$id]);
    $rec = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($rec['processing_status'] !== 'completed') return "Expected status completed";
    if (strpos($rec['lesson_text'], "Highway Capacity Manual") === false) return "PPTX extracted text missing expected content";

    return true;
});

// 4. Valid PDF Test
runTestCase("Valid PDF File Extraction", function() use ($pdo, $teacherId, $tempDir) {
    $filePath = $tempDir . '/valid_lesson.pdf';
    $pdfData = "%PDF-1.4\n" .
               "1 0 obj <</Type /Catalog /Pages 2 0 R>> endobj\n" .
               "2 0 obj <</Type /Pages /Kids [3 0 R] /Count 1>> endobj\n" .
               "3 0 obj <</Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Contents 4 0 R>> endobj\n" .
               "4 0 obj <</Length 65>> stream\n" .
               "BT /F1 12 Tf 50 700 Td (Hydraulics Lesson: Fluid Mechanics Principles) Tj ET\n" .
               "endstream endobj\n" .
               "xref\n0 5\n0000000000 65535 f\n0000000009 00000 n\n0000000056 00000 n\n0000000111 00000 n\n0000000212 00000 n\n" .
               "trailer <</Size 5 /Root 1 0 R>>\nstartxref\n325\n%%EOF";
    file_put_contents($filePath, $pdfData);

    $id = createTestLessonRecord($pdo, $teacherId, "Valid PDF Lesson", "valid_lesson.pdf", "tests/fixtures_p1/valid_lesson.pdf", "pdf", filesize($filePath));
    $res = LessonExtractionService::extractAndSave($id);

    if (!$res['success']) return "PDF Extraction failed: " . ($res['error'] ?? '');

    $stmt = $pdo->prepare("SELECT lesson_text, processing_status FROM lesson_materials WHERE id = ?");
    $stmt->execute([$id]);
    $rec = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($rec['processing_status'] !== 'completed') return "Expected status completed";
    if (strpos($rec['lesson_text'], "Fluid Mechanics Principles") === false) return "PDF extracted text missing expected content";

    return true;
});

// 5. Empty File Test
runTestCase("Empty File Handling", function() use ($pdo, $teacherId, $tempDir) {
    $filePath = $tempDir . '/empty.txt';
    file_put_contents($filePath, '');

    $id = createTestLessonRecord($pdo, $teacherId, "Empty Lesson", "empty.txt", "tests/fixtures_p1/empty.txt", "txt", 0);
    $res = LessonExtractionService::extractAndSave($id);

    if ($res['success'] !== false) return "Expected extraction failure for empty file";

    $stmt = $pdo->prepare("SELECT processing_status, processing_error FROM lesson_materials WHERE id = ?");
    $stmt->execute([$id]);
    $rec = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($rec['processing_status'] !== 'failed') return "Expected status failed";
    if (strpos($rec['processing_error'], "empty") === false) return "Expected 'empty' error message";

    return true;
});

// 6. Corrupted File Test
runTestCase("Corrupted DOCX Handling", function() use ($pdo, $teacherId, $tempDir) {
    $filePath = $tempDir . '/corrupt.docx';
    file_put_contents($filePath, "NOT A REAL ZIP ARCHIVE CORRUPTED DATA");

    $id = createTestLessonRecord($pdo, $teacherId, "Corrupt DOCX", "corrupt.docx", "tests/fixtures_p1/corrupt.docx", "docx", filesize($filePath));
    $res = LessonExtractionService::extractAndSave($id);

    if ($res['success'] !== false) return "Expected extraction failure for corrupt file";

    $stmt = $pdo->prepare("SELECT processing_status FROM lesson_materials WHERE id = ?");
    $stmt->execute([$id]);
    $rec = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($rec['processing_status'] !== 'failed') return "Expected status failed";

    return true;
});

// 7. Fake PDF Test
runTestCase("Fake PDF Header Handling", function() use ($pdo, $teacherId, $tempDir) {
    $filePath = $tempDir . '/fake.pdf';
    file_put_contents($filePath, "This is plain text pretending to be a PDF.");

    $id = createTestLessonRecord($pdo, $teacherId, "Fake PDF", "fake.pdf", "tests/fixtures_p1/fake.pdf", "pdf", filesize($filePath));
    $res = LessonExtractionService::extractAndSave($id);

    if ($res['success'] !== false) return "Expected extraction failure for fake PDF";

    $stmt = $pdo->prepare("SELECT processing_status FROM lesson_materials WHERE id = ?");
    $stmt->execute([$id]);
    $rec = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($rec['processing_status'] !== 'failed') return "Expected status failed";

    return true;
});

// 8. Scanned PDF (No Text Layer) Test
runTestCase("Scanned PDF Without Text Layer Handling", function() use ($pdo, $teacherId, $tempDir) {
    $filePath = $tempDir . '/scanned.pdf';
    $pdfData = "%PDF-1.4\n" .
               "1 0 obj <</Type /Catalog /Pages 2 0 R>> endobj\n" .
               "2 0 obj <</Type /Pages /Kids [3 0 R] /Count 1>> endobj\n" .
               "3 0 obj <</Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Contents 4 0 R>> endobj\n" .
               "4 0 obj <</Length 10>> stream\n" .
               "1 2 3 4 5 6 7 8 9\n" .
               "endstream endobj\n" .
               "xref\n0 5\n0000000000 65535 f\n" .
               "trailer <</Size 5 /Root 1 0 R>>\nstartxref\n200\n%%EOF";
    file_put_contents($filePath, $pdfData);

    $id = createTestLessonRecord($pdo, $teacherId, "Scanned PDF", "scanned.pdf", "tests/fixtures_p1/scanned.pdf", "pdf", filesize($filePath));
    $res = LessonExtractionService::extractAndSave($id);

    if ($res['success'] !== false) return "Expected extraction failure for scanned PDF without text layer";

    $stmt = $pdo->prepare("SELECT processing_status, processing_error FROM lesson_materials WHERE id = ?");
    $stmt->execute([$id]);
    $rec = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($rec['processing_status'] !== 'failed') return "Expected status failed";

    return true;
});

// Summary
echo "\n=========================================\n";
echo "PROMPT 1 TEST RESULTS: Passed {$passed}, Failed {$failed}\n";
echo "=========================================\n";

if ($failed > 0) {
    exit(1);
} else {
    exit(0);
}
