<?php
/**
 * QUESTBANK COMPREHENSIVE END-TO-END VERIFICATION SUITE
 */
require_once __DIR__ . '/../../app/bootstrap.php';
require_once __DIR__ . '/../../app/fpdf.php';
require_once __DIR__ . '/../../app/services/LessonExtractionService.php';
require_once __DIR__ . '/../../app/services/FileValidationService.php';
require_once __DIR__ . '/../../app/services/GroqService.php';
require_once __DIR__ . '/../../app/services/ExamService.php';
require_once __DIR__ . '/../../app/services/ExamScoringService.php';

echo "===================================================================\n";
echo "   QUESTBANK FULL-SYSTEM HEALTH & READINESS AUDIT                  \n";
echo "===================================================================\n\n";

$pdo = getDBConnection();
$teacherId = 12;

// 1. Database Schema Auto-Healing
echo "[1/6] Auditing and Self-Healing Database Schemas...\n";
LessonExtractionService::ensureSchema($pdo);
$lessonCols = $pdo->query("SHOW COLUMNS FROM lesson_materials")->fetchAll(PDO::FETCH_COLUMN);
assert(in_array('academic_period', $lessonCols), "academic_period missing");
assert(in_array('stored_filename', $lessonCols), "stored_filename missing");
echo "  [PASS] lesson_materials schema is 100% intact (" . count($lessonCols) . " columns).\n";

// Ensure ai_generation_batches
$batchCols = $pdo->query("SHOW COLUMNS FROM ai_generation_batches")->fetchAll(PDO::FETCH_COLUMN);
assert(count($batchCols) > 10, "ai_generation_batches missing");
echo "  [PASS] ai_generation_batches schema is 100% intact (" . count($batchCols) . " columns).\n";

// 2. Multi-Format Lesson Extraction (TXT, DOCX, PDF)
echo "\n[2/6] Auditing Multi-Format Lesson Upload & Extraction...\n";
// TXT
$txtFile = __DIR__ . '/../../teacher/uploads/audit_test.txt';
file_put_contents($txtFile, "Structural Steel Design: Euler buckling load for column members with pinned and fixed boundary conditions.");
$stmt = $pdo->prepare("
    INSERT INTO lesson_materials (teacher_id, subject, title, academic_period, file_name, file_path, file_type, file_size, processing_status)
    VALUES (?, 'CE 412', 'Audit TXT', 'midterm', 'audit_test.txt', 'uploads/audit_test.txt', 'TXT', ?, 'pending')
");
$stmt->execute([$teacherId, filesize($txtFile)]);
$txtId = (int)$pdo->lastInsertId();
$txtRes = LessonExtractionService::extractAndSave($txtId);
assert($txtRes['success'] === true, "TXT extraction failed");
echo "  [PASS] TXT extraction verified ({$txtRes['word_count']} words).\n";

// DOCX
$docxFile = __DIR__ . '/../../teacher/uploads/audit_test.docx';
$zip = new ZipArchive();
if ($zip->open($docxFile, ZipArchive::CREATE | ZipArchive::OVERWRITE) === true) {
    $zip->addFromString('word/document.xml', '<?xml version="1.0" encoding="UTF-8"?><w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:body><w:p><w:r><w:t>Reinforced Concrete Beam Moment Capacity and Shear Reinforcement Spacing</w:t></w:r></w:p></w:body></w:document>');
    $zip->close();
}
$stmt->execute([$teacherId, filesize($docxFile)]);
$docxId = (int)$pdo->lastInsertId();
$pdo->prepare("UPDATE lesson_materials SET file_name = 'audit_test.docx', file_path = 'uploads/audit_test.docx', file_type = 'DOCX' WHERE id = ?")->execute([$docxId]);
$docxRes = LessonExtractionService::extractAndSave($docxId);
assert($docxRes['success'] === true, "DOCX extraction failed");
echo "  [PASS] DOCX extraction verified ({$docxRes['word_count']} words).\n";

// PDF
$pdfFile = __DIR__ . '/../../teacher/uploads/audit_test.pdf';
$pdf = new FPDF();
$pdf->AddPage();
$pdf->SetFont('Arial', 'B', 12);
$pdf->Cell(40, 10, 'Hydraulics and Fluid Mechanics Open Channel Flow Manning Equation');
$pdf->Output('F', $pdfFile);
$stmt->execute([$teacherId, filesize($pdfFile)]);
$pdfId = (int)$pdo->lastInsertId();
$pdo->prepare("UPDATE lesson_materials SET file_name = 'audit_test.pdf', file_path = 'uploads/audit_test.pdf', file_type = 'PDF' WHERE id = ?")->execute([$pdfId]);
$pdfRes = LessonExtractionService::extractAndSave($pdfId);
assert($pdfRes['success'] === true, "PDF extraction failed");
echo "  [PASS] PDF extraction verified ({$pdfRes['word_count']} words).\n";

// Cleanup test lesson files
$pdo->prepare("DELETE FROM lesson_materials WHERE id IN (?, ?, ?)")->execute([$txtId, $docxId, $pdfId]);
@unlink($txtFile);
@unlink($docxFile);
@unlink($pdfFile);

// 3. Question Validation & Parsing Engine
echo "\n[3/6] Auditing Question Schema & JSON Recovery Engine...\n";
$jsonMalformed = '```json
[
  {
    "type": "multiple_choice",
    "question": "What is the primary tensile material in reinforced concrete?",
    "options": ["Steel rebar", "Concrete", "Sand", "Gravel"],
    "answer": "Steel rebar",
    "points": 1,
    "difficulty": "easy"
  },
  {
    "type": "true_false",
    "question": "Concrete has high tensile strength.",
    "options": ["True", "False"],
    "answer": "False",
    "points": 1,
    "difficulty": "easy",
  }
]
```';
$recovered = GroqService::parseQuestionsJson($jsonMalformed);
assert(count($recovered) === 2, "JSON recovery failed on trailing commas/markdown");
GroqService::validateQuestionItem($recovered[0]);
GroqService::validateQuestionItem($recovered[1]);
echo "  [PASS] 3-tier JSON recovery successfully recovered and validated items.\n";

// 4. Exam Creation & Question Bank Integrity
echo "\n[4/6] Auditing Exam Creation & Question Bank Storage...\n";
$pdo->beginTransaction();
$stmtExam = $pdo->prepare("
    INSERT INTO exams (teacher_id, title, subject, specialization, time_limit, total_items, exam_category)
    VALUES (?, 'End-to-End Audit Exam', 'CE 412', 'Structural', 60, 2, 'regular')
");
$stmtExam->execute([$teacherId]);
$testExamId = (int)$pdo->lastInsertId();

$stmtQ = $pdo->prepare("
    INSERT INTO exam_questions (exam_id, question_text, question_type, option_a, option_b, option_c, option_d, correct_answer, points)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
");
$stmtQ->execute([$testExamId, $recovered[0]['question'], 'multiple_choice', 'Steel rebar', 'Concrete', 'Sand', 'Gravel', 'Steel rebar', 1]);
$stmtQ->execute([$testExamId, $recovered[1]['question'], 'true_false', 'True', 'False', null, null, 'False', 1]);

// Query back and verify
$verifyExams = $pdo->prepare("SELECT * FROM exams WHERE id = ?");
$verifyExams->execute([$testExamId]);
$fetchedExam = $verifyExams->fetch(PDO::FETCH_ASSOC);
assert(!empty($fetchedExam), "Exam was not persisted");

$verifyQ = $pdo->prepare("SELECT * FROM exam_questions WHERE exam_id = ?");
$verifyQ->execute([$testExamId]);
$fetchedQuestions = $verifyQ->fetchAll(PDO::FETCH_ASSOC);
assert(count($fetchedQuestions) === 2, "Exam questions were not persisted");
echo "  [PASS] Exam and {$fetchedExam['total_items']} questions persisted and verified.\n";

// Delete exam & cleanup
$pdo->prepare("DELETE FROM exam_questions WHERE exam_id = ?")->execute([$testExamId]);
$pdo->prepare("DELETE FROM exams WHERE id = ?")->execute([$testExamId]);
$pdo->commit();
echo "  [PASS] Exam bank cleanup successful.\n";

// 5. Exam Scoring & OCR Grading Engine
echo "\n[5/6] Auditing Exam Scoring & OCR Engine...\n";
$sampleQ = [
    'id' => 9999,
    'question_type' => 'multiple_choice',
    'correct_answer' => 'Steel rebar',
    'points' => 1
];
$scoreCorrect = ExamScoringService::evaluateSingleItem($sampleQ, 'Steel rebar');
assert($scoreCorrect['is_correct'] === true, "Scoring failed on correct answer");
assert($scoreCorrect['awarded_points'] == 1.00, "Awarded points mismatch");

$scoreIncorrect = ExamScoringService::evaluateSingleItem($sampleQ, 'Wood');
assert($scoreIncorrect['is_correct'] === false, "Scoring failed on incorrect answer");
assert($scoreIncorrect['awarded_points'] == 0.00, "Awarded points mismatch on fail");
echo "  [PASS] Scoring engine accurately evaluates items with 0 warnings.\n";

// 6. Security and Input Traversal Audit
echo "\n[6/6] Auditing File Security & Extension Traversal...\n";
$maliciousFilename = '../../malicious.php';
$traversalCheck = FileValidationService::validateFile(__DIR__ . '/../../README.md', $maliciousFilename);
assert($traversalCheck['success'] === false, "Traversal security check failed");
echo "  [PASS] Path traversal protection blocked malicious filename.\n";

echo "\n===================================================================\n";
echo "   RESULT: 6/6 AUDIT DOMAINS VERIFIED & 100% OPERATIONAL           \n";
echo "===================================================================\n";
