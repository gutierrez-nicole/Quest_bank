<?php

require_once __DIR__ . '/../database.php';
require_once __DIR__ . '/../../includes/security.php';

class LessonExtractionService {

    public static function ensureSchema($pdo = null) {
        static $ensured = false;
        if ($ensured) return;
        if (!$pdo) {
            $pdo = getDBConnection();
        }
        try {
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS `lesson_materials` (
                  `id` int NOT NULL AUTO_INCREMENT,
                  `teacher_id` int NOT NULL,
                  `subject` varchar(100) NOT NULL,
                  `title` varchar(150) NOT NULL,
                  `file_name` varchar(255) NOT NULL,
                  `file_path` varchar(255) NOT NULL,
                  `file_type` varchar(50) NOT NULL,
                  `file_size` int NOT NULL DEFAULT 0,
                  `lesson_text` longtext DEFAULT NULL,
                  `processing_status` enum('pending','processing','completed','failed') NOT NULL DEFAULT 'completed',
                  `processing_error` text DEFAULT NULL,
                  `word_count` int NOT NULL DEFAULT 0,
                  `page_count` int NOT NULL DEFAULT 1,
                  `extracted_at` timestamp NULL DEFAULT NULL,
                  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
                  `mime_type` varchar(100) DEFAULT NULL,
                  `original_filename` varchar(255) DEFAULT NULL,
                  `stored_filename` varchar(255) DEFAULT NULL,
                  `is_demo` tinyint(1) NOT NULL DEFAULT 0,
                  `academic_period` varchar(20) NOT NULL DEFAULT 'general',
                  `semester` varchar(20) DEFAULT NULL,
                  `school_year` varchar(20) DEFAULT NULL,
                  `year_level` varchar(50) DEFAULT NULL,
                  `program` varchar(100) DEFAULT NULL,
                  PRIMARY KEY (`id`),
                  KEY `idx_lessons_teacher` (`teacher_id`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
            ");

            $cols = $pdo->query("SHOW COLUMNS FROM lesson_materials")->fetchAll(PDO::FETCH_COLUMN);
            $colMap = array_flip($cols);

            $missingCols = [
                'lesson_text' => "LONGTEXT DEFAULT NULL",
                'processing_status' => "ENUM('pending','processing','completed','failed') NOT NULL DEFAULT 'completed'",
                'processing_error' => "TEXT DEFAULT NULL",
                'word_count' => "INT(11) NOT NULL DEFAULT 0",
                'page_count' => "INT(11) NOT NULL DEFAULT 1",
                'extracted_at' => "TIMESTAMP NULL DEFAULT NULL",
                'mime_type' => "VARCHAR(100) DEFAULT NULL",
                'original_filename' => "VARCHAR(255) DEFAULT NULL",
                'stored_filename' => "VARCHAR(255) DEFAULT NULL",
                'is_demo' => "TINYINT(1) NOT NULL DEFAULT 0",
                'academic_period' => "VARCHAR(20) NOT NULL DEFAULT 'general'",
                'semester' => "VARCHAR(20) DEFAULT NULL",
                'school_year' => "VARCHAR(20) DEFAULT NULL",
                'year_level' => "VARCHAR(50) DEFAULT NULL",
                'program' => "VARCHAR(100) DEFAULT NULL"
            ];

            foreach ($missingCols as $c => $def) {
                if (!isset($colMap[$c])) {
                    $pdo->exec("ALTER TABLE `lesson_materials` ADD COLUMN `{$c}` {$def}");
                }
            }
        } catch (Throwable $e) {
            error_log("Failed ensuring lesson_materials schema: " . $e->getMessage());
        }
        $ensured = true;
    }

    public static function extractAndSave($materialId) {
        @ini_set('max_execution_time', '300');
        @set_time_limit(300);
        @ini_set('memory_limit', '256M');

        $startTime = microtime(true);
        $pdo = getDBConnection();
        self::ensureSchema($pdo);

        try {
            $stmt = $pdo->prepare("SELECT * FROM lesson_materials WHERE id = ?");
            $stmt->execute([$materialId]);
            $material = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$material) {
                return ['success' => false, 'error' => 'Lesson material record not found.'];
            }

            $filePath = __DIR__ . '/../../teacher/' . $material['file_path'];
            if (!file_exists($filePath)) {
                $filePath = __DIR__ . '/../../' . $material['file_path'];
            }

            if (!file_exists($filePath)) {
                self::markFailed($pdo, $materialId, "File not found on server.");
                return ['success' => false, 'error' => 'File not found on server.'];
            }

            $fileSize = (int)@filesize($filePath);
            if ($fileSize === 0) {
                self::markFailed($pdo, $materialId, "File is empty (0 bytes).");
                return ['success' => false, 'error' => 'File is empty (0 bytes).'];
            }

            if ($fileSize > 10485760) { 
                self::markFailed($pdo, $materialId, "File exceeds maximum size limit of 10MB.");
                return ['success' => false, 'error' => 'File exceeds maximum size limit of 10MB.'];
            }

            try {
                $stmtUpdate = $pdo->prepare("UPDATE lesson_materials SET processing_status = 'processing' WHERE id = ?");
                $stmtUpdate->execute([$materialId]);
            } catch (Throwable $ignore) {}

            $fileExt = strtolower(pathinfo($material['file_name'], PATHINFO_EXTENSION));

            $detectedMime = 'application/octet-stream';
            if (function_exists('finfo_open')) {
                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                if ($finfo) {
                    $detected = finfo_file($finfo, $filePath);
                    if (!empty($detected)) {
                        $detectedMime = $detected;
                    }
                    if (PHP_VERSION_ID < 80500) {
                        @finfo_close($finfo);
                    }
                }
            } elseif (function_exists('mime_content_type')) {
                $detectedMime = @mime_content_type($filePath) ?: 'application/octet-stream';
            }

            $allowedMimes = [
                'pdf' => ['application/pdf', 'application/x-pdf', 'application/octet-stream'],
                'docx' => [
                    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                    'application/zip',
                    'application/x-zip',
                    'application/x-zip-compressed',
                    'application/octet-stream'
                ],
                'pptx' => [
                    'application/vnd.openxmlformats-officedocument.presentationml.presentation',
                    'application/zip',
                    'application/x-zip',
                    'application/x-zip-compressed',
                    'application/octet-stream'
                ],
                'txt' => ['text/plain', 'text/x-gettext-translation', 'text/csv', 'application/octet-stream']
            ];

            if (!isset($allowedMimes[$fileExt])) {
                self::markFailed($pdo, $materialId, "Unsupported file extension: .{$fileExt}");
                return ['success' => false, 'error' => "Unsupported file extension: .{$fileExt}"];
            }

            if (!in_array($detectedMime, $allowedMimes[$fileExt])) {
                self::markFailed($pdo, $materialId, "File content type does not match extension .{$fileExt} (Detected: {$detectedMime}).");
                return ['success' => false, 'error' => "File content type does not match extension .{$fileExt} (Detected: {$detectedMime})."];
            }

            $extractedText = '';
            $pageCount = 1;

            switch ($fileExt) {
                case 'txt':
                    $extractedText = (string)@file_get_contents($filePath);
                    $pageCount = max(1, (int)ceil(strlen($extractedText) / 3000));
                    break;

                case 'docx':
                    $extractedText = self::extractFromDocx($filePath);
                    $pageCount = max(1, (int)ceil(strlen($extractedText) / 2500));
                    break;

                case 'pptx':
                    $res = self::extractFromPptx($filePath);
                    $extractedText = $res['text'] ?? '';
                    $pageCount = max(1, intval($res['slides'] ?? 1));
                    break;

                case 'pdf':
                    $res = self::extractFromPdf($filePath);
                    $extractedText = $res['text'] ?? '';
                    $pageCount = max(1, intval($res['pages'] ?? 1));
                    break;

                default:
                    throw new Exception("Unsupported file format: .{$fileExt}");
            }

            $cleanText = self::cleanExtractedText($extractedText);

            if (empty(trim($cleanText))) {
                throw new Exception("Extraction resulted in empty text. The file might contain scanned images without text layers or password protection.");
            }

            $wordCount = str_word_count($cleanText);
            $executionTime = round((microtime(true) - $startTime) * 1000, 2);

            try {
                $stmtSave = $pdo->prepare("
                    UPDATE lesson_materials 
                    SET lesson_text = ?, 
                        processing_status = 'completed', 
                        processing_error = NULL, 
                        word_count = ?, 
                        page_count = ?, 
                        mime_type = ?,
                        file_size = ?,
                        extracted_at = NOW() 
                    WHERE id = ?
                ");
                $stmtSave->execute([$cleanText, $wordCount, $pageCount, $detectedMime, $fileSize, $materialId]);
            } catch (Throwable $dbEx) {
                error_log("Failed saving extracted text to DB: " . $dbEx->getMessage());
            }

            try {
                logActivity("Successfully extracted lesson content for '{$material['title']}' ({$wordCount} words, {$pageCount} pages, {$executionTime}ms).", $material['teacher_id'] ?? null);
            } catch (Throwable $ignore) {}

            return [
                'success' => true,
                'word_count' => $wordCount,
                'page_count' => $pageCount,
                'execution_time_ms' => $executionTime,
                'text_snippet' => mb_substr($cleanText, 0, 300)
            ];

        } catch (Throwable $e) {
            $errorMsg = $e->getMessage();
            self::markFailed($pdo, $materialId, $errorMsg);
            error_log("LessonExtraction Error [ID {$materialId}]: " . $errorMsg);
            try {
                $title = $material['title'] ?? ('Material #' . $materialId);
                $tId = $material['teacher_id'] ?? null;
                logActivity("Failed to extract lesson '{$title}': {$errorMsg}", $tId);
            } catch (Throwable $ignore) {}
            return ['success' => false, 'error' => $errorMsg];
        }
    }

    private static function markFailed($pdo, $materialId, $errorMessage) {
        try {
            $stmt = $pdo->prepare("
                UPDATE lesson_materials 
                SET processing_status = 'failed', 
                    processing_error = ? 
                WHERE id = ?
            ");
            $stmt->execute([$errorMessage, $materialId]);
        } catch (Throwable $e) {
            error_log("Failed updating markFailed in lesson_materials: " . $e->getMessage());
        }
    }

    private static function cleanExtractedText($text) {
        if (!is_string($text)) return '';

        $text = str_replace("\0", '', $text);
        $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $text);
        $text = str_replace("\r\n", "\n", $text);
        $text = str_replace("\r", "\n", $text);
        $text = preg_replace('/Page \d+ of \d+/i', '', $text);
        $text = preg_replace('/^\s*\d+\s*$/m', '', $text);
        $text = preg_replace("/\n{3,}/", "\n\n", $text);
        $text = preg_replace('/[ \t]+/', ' ', $text);

        return trim($text);
    }

    private static function extractFromDocx($filePath) {
        if (!class_exists('ZipArchive')) {
            throw new Exception("The PHP ZipArchive extension is required on the server to read DOCX files.");
        }

        $zip = new ZipArchive();
        if ($zip->open($filePath) !== true) {
            throw new Exception("Unable to open DOCX file archive. Document may be corrupted.");
        }

        $documentXml = $zip->getFromName('word/document.xml');
        $zip->close();

        if (!$documentXml) {
            throw new Exception("Invalid DOCX format: word/document.xml missing.");
        }

        // Use DOMDocument if available
        if (class_exists('DOMDocument')) {
            $dom = new DOMDocument();
            libxml_use_internal_errors(true);
            $dom->loadXML($documentXml);
            libxml_clear_errors();

            $xpath = new DOMXPath($dom);
            $xpath->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');

            $paragraphs = $xpath->query('//w:p');
            $extractedLines = [];

            if ($paragraphs) {
                foreach ($paragraphs as $paragraph) {
                    $texts = $xpath->query('.//w:t', $paragraph);
                    $lineText = '';
                    if ($texts) {
                        foreach ($texts as $textNode) {
                            $lineText .= $textNode->nodeValue;
                        }
                    }
                    if (trim($lineText) !== '') {
                        $extractedLines[] = trim($lineText);
                    }
                }
            }

            if (!empty($extractedLines)) {
                return implode("\n", $extractedLines);
            }
        }

        // Fast regex fallback if DOMDocument not present or xpath empty
        preg_match_all('/<w:t[^>]*>(.*?)<\/w:t>/is', $documentXml, $matches);
        if (!empty($matches[1])) {
            return html_entity_decode(implode(' ', $matches[1]), ENT_QUOTES, 'UTF-8');
        }

        $plain = strip_tags(str_replace(['<w:p>', '</w:p>'], ["\n", "\n"], $documentXml));
        return trim(html_entity_decode($plain, ENT_QUOTES, 'UTF-8'));
    }

    private static function extractFromPptx($filePath) {
        if (!class_exists('ZipArchive')) {
            throw new Exception("The PHP ZipArchive extension is required on the server to read PPTX presentations.");
        }

        $zip = new ZipArchive();
        if ($zip->open($filePath) !== true) {
            throw new Exception("Unable to open PPTX file archive. Presentation may be corrupted.");
        }

        $slideCount = 0;
        $extractedTextLines = [];

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $filename = $zip->getNameIndex($i);
            if (preg_match('/^ppt\/slides\/slide\d+\.xml$/i', $filename)) {
                $slideCount++;
                $slideXml = $zip->getFromName($filename);
                if (!$slideXml) continue;

                if (class_exists('DOMDocument')) {
                    $dom = new DOMDocument();
                    libxml_use_internal_errors(true);
                    $dom->loadXML($slideXml);
                    libxml_clear_errors();

                    $xpath = new DOMXPath($dom);
                    $xpath->registerNamespace('a', 'http://schemas.openxmlformats.org/drawingml/2006/main');

                    $textNodes = $xpath->query('//a:t');
                    $slideLines = [];
                    if ($textNodes) {
                        foreach ($textNodes as $node) {
                            $val = trim($node->nodeValue);
                            if ($val !== '') {
                                $slideLines[] = $val;
                            }
                        }
                    }
                    if (!empty($slideLines)) {
                        $extractedTextLines[] = "--- Slide {$slideCount} ---\n" . implode(" ", $slideLines);
                        continue;
                    }
                }

                // Fallback regex for slides
                preg_match_all('/<a:t[^>]*>(.*?)<\/a:t>/is', $slideXml, $sMatches);
                if (!empty($sMatches[1])) {
                    $extractedTextLines[] = "--- Slide {$slideCount} ---\n" . html_entity_decode(implode(' ', $sMatches[1]), ENT_QUOTES, 'UTF-8');
                }
            }
        }

        $zip->close();

        if ($slideCount === 0) {
            throw new Exception("No slides found in PPTX presentation.");
        }

        return [
            'text' => implode("\n\n", $extractedTextLines),
            'slides' => $slideCount
        ];
    }

    private static function extractFromPdf($filePath) {
        // Method 1: Check for pdftotext utility if exec() is allowed and not on Windows
        $isWindows = (DIRECTORY_SEPARATOR === '\\');
        if (!$isWindows && function_exists('exec')) {
            try {
                $pdftotextPath = @exec('which pdftotext 2>/dev/null');
                if (!empty($pdftotextPath) && is_executable($pdftotextPath)) {
                    $outputFile = tempnam(sys_get_temp_dir(), 'pdf_txt_');
                    $command = escapeshellcmd("{$pdftotextPath} -enc UTF-8 " . escapeshellarg($filePath) . " " . escapeshellarg($outputFile));
                    @exec($command, $output, $returnVar);

                    if ($returnVar === 0 && file_exists($outputFile)) {
                        $text = @file_get_contents($outputFile);
                        @unlink($outputFile);
                        if (!empty(trim((string)$text))) {
                            $pages = preg_match_all('/\f/', $text) + 1;
                            return ['text' => $text, 'pages' => max(1, $pages)];
                        }
                    }
                    @unlink($outputFile);
                }
            } catch (Throwable $e) {
                // Fall back to native stream parser
            }
        }

        // Method 2: Native PHP stream parsing without catastrophic regex backtracking
        $content = @file_get_contents($filePath);
        if (!$content) {
            throw new Exception("Unable to read PDF file.");
        }

        if (strpos($content, '%PDF-') !== 0) {
            throw new Exception("File header does not match valid PDF format.");
        }

        if (strpos($content, '/Encrypt') !== false) {
            throw new Exception("PDF is encrypted or password-protected.");
        }

        // Estimate page count
        $pages = 1;
        if (preg_match_all('/\/Type\s*\/Page\b/i', $content, $pageMatches)) {
            $pages = max(1, count($pageMatches[0]));
        }

        // Locate streams via string offsets (immune to PCRE backtrack limit)
        $extractedText = '';
        $offset = 0;
        $maxStreams = 600;
        $streamCount = 0;

        while (($streamPos = stripos($content, 'stream', $offset)) !== false && $streamCount < $maxStreams) {
            $streamCount++;
            $dataStart = $streamPos + 6;
            // Advance past line breaks after 'stream'
            if (isset($content[$dataStart]) && ($content[$dataStart] === "\r" || $content[$dataStart] === "\n")) {
                $dataStart += ($content[$dataStart] === "\r" && isset($content[$dataStart + 1]) && $content[$dataStart + 1] === "\n") ? 2 : 1;
            }

            $endStreamPos = stripos($content, 'endstream', $dataStart);
            if ($endStreamPos === false) break;

            $streamLen = $endStreamPos - $dataStart;
            if ($streamLen > 0 && $streamLen < 15728640) { // Safety: max 15MB stream chunk
                $stream = substr($content, $dataStart, $streamLen);
                
                $decompressed = @gzuncompress($stream);
                if ($decompressed === false) {
                    $decompressed = @gzinflate($stream);
                }
                $data = ($decompressed !== false && strlen($decompressed) > 0) ? $decompressed : $stream;

                // Extract PDF text literals: (text) Tj or [(text)] TJ
                if (preg_match_all('/(?:\((.*?)\)|\[(.*?)\])\s*(?:Tj|TJ|\'|\")/is', $data, $textMatches)) {
                    foreach ($textMatches[1] as $tm) {
                        if (!empty($tm)) {
                            $cleaned = preg_replace('/[^\x20-\x7E\n]/', '', $tm);
                            if (strlen($cleaned) > 1) {
                                $extractedText .= $cleaned . " ";
                            }
                        }
                    }
                    foreach ($textMatches[2] as $tm) {
                        if (!empty($tm)) {
                            preg_match_all('/\((.*?)\)/', $tm, $subMatches);
                            if (!empty($subMatches[1])) {
                                foreach ($subMatches[1] as $sub) {
                                    $cleaned = preg_replace('/[^\x20-\x7E\n]/', '', $sub);
                                    if (strlen($cleaned) > 1) {
                                        $extractedText .= $cleaned . " ";
                                    }
                                }
                            }
                        }
                    }
                }
            }

            $offset = $endStreamPos + 9;
        }

        $finalText = trim($extractedText);

        // Fallback: If no streams decoded into text, search for raw printable strings
        if (empty($finalText)) {
            if (preg_match_all('/[a-zA-Z0-9\s.,?!;:()\-_"\'\/]{8,}/', $content, $rawMatches)) {
                $candidateLines = [];
                foreach ($rawMatches[0] as $match) {
                    $m = trim($match);
                    if (strlen($m) > 15 && !preg_match('/^(obj|endobj|stream|endstream|xref|trailer|startxref)/', $m)) {
                        $candidateLines[] = $m;
                    }
                }
                if (count($candidateLines) >= 3) {
                    $finalText = implode("\n", array_slice($candidateLines, 0, 500));
                }
            }
        }

        return [
            'text' => $finalText,
            'pages' => max(1, $pages)
        ];
    }
}
