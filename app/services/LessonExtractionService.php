<?php

require_once __DIR__ . '/../database.php';
require_once __DIR__ . '/../../includes/security.php';

class LessonExtractionService {

    public static function extractAndSave($materialId) {
        $startTime = microtime(true);
        $pdo = getDBConnection();

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

        $fileSize = filesize($filePath);
        if ($fileSize === 0) {
            self::markFailed($pdo, $materialId, "File is empty (0 bytes).");
            return ['success' => false, 'error' => 'File is empty (0 bytes).'];
        }

        if ($fileSize > 10485760) { 
            self::markFailed($pdo, $materialId, "File exceeds maximum size limit of 10MB.");
            return ['success' => false, 'error' => 'File exceeds maximum size limit of 10MB.'];
        }

        $stmtUpdate = $pdo->prepare("UPDATE lesson_materials SET processing_status = 'processing' WHERE id = ?");
        $stmtUpdate->execute([$materialId]);

        $fileExt = strtolower(pathinfo($material['file_name'], PATHINFO_EXTENSION));

        
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $detectedMime = finfo_file($finfo, $filePath);
        finfo_close($finfo);

        $allowedMimes = [
            'pdf' => ['application/pdf'],
            'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/zip'],
            'pptx' => ['application/vnd.openxmlformats-officedocument.presentationml.presentation', 'application/zip'],
            'txt' => ['text/plain', 'text/x-gettext-translation']
        ];

        if (!isset($allowedMimes[$fileExt])) {
            self::markFailed($pdo, $materialId, "Unsupported file extension: .{$fileExt}");
            return ['success' => false, 'error' => "Unsupported file extension: .{$fileExt}"];
        }

        if (!in_array($detectedMime, $allowedMimes[$fileExt])) {
            self::markFailed($pdo, $materialId, "File content type does not match extension .{$fileExt} (Detected: {$detectedMime}).");
            return ['success' => false, 'error' => "File content type does not match extension .{$fileExt} (Detected: {$detectedMime})."];
        }

        try {
            $extractedText = '';
            $pageCount = 1;

            switch ($fileExt) {
                case 'txt':
                    $extractedText = file_get_contents($filePath);
                    $pageCount = max(1, (int)ceil(strlen($extractedText) / 3000));
                    break;

                case 'docx':
                    $extractedText = self::extractFromDocx($filePath);
                    $pageCount = max(1, (int)ceil(strlen($extractedText) / 2500));
                    break;

                case 'pptx':
                    $res = self::extractFromPptx($filePath);
                    $extractedText = $res['text'];
                    $pageCount = max(1, $res['slides']);
                    break;

                case 'pdf':
                    $res = self::extractFromPdf($filePath);
                    $extractedText = $res['text'];
                    $pageCount = max(1, $res['pages']);
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

            logActivity("Successfully extracted lesson content for '{$material['title']}' ({$wordCount} words, {$pageCount} pages, {$executionTime}ms).", $material['teacher_id'] ?? null);

            return [
                'success' => true,
                'word_count' => $wordCount,
                'page_count' => $pageCount,
                'execution_time_ms' => $executionTime,
                'text_snippet' => mb_substr($cleanText, 0, 300)
            ];

        } catch (Exception $e) {
            $errorMsg = $e->getMessage();
            self::markFailed($pdo, $materialId, $errorMsg);
            error_log("LessonExtraction Error [ID {$materialId}]: " . $errorMsg);
            logActivity("Failed to extract lesson '{$material['title']}': {$errorMsg}", $material['teacher_id'] ?? null);
            return ['success' => false, 'error' => $errorMsg];
        }
    }

    private static function markFailed($pdo, $materialId, $errorMessage) {
        $stmt = $pdo->prepare("
            UPDATE lesson_materials 
            SET processing_status = 'failed', 
                processing_error = ? 
            WHERE id = ?
        ");
        $stmt->execute([$errorMessage, $materialId]);
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
        $zip = new ZipArchive();
        if ($zip->open($filePath) !== true) {
            throw new Exception("Unable to open DOCX file archive. Document may be corrupted.");
        }

        $documentXml = $zip->getFromName('word/document.xml');
        $zip->close();

        if (!$documentXml) {
            throw new Exception("Invalid DOCX format: word/document.xml missing.");
        }

        
        $dom = new DOMDocument();
        libxml_use_internal_errors(true);
        $dom->loadXML($documentXml);
        libxml_clear_errors();

        $xpath = new DOMXPath($dom);
        $xpath->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');

        $paragraphs = $xpath->query('//w:p');
        $extractedLines = [];

        foreach ($paragraphs as $paragraph) {
            $texts = $xpath->query('.//w:t', $paragraph);
            $lineText = '';
            foreach ($texts as $textNode) {
                $lineText .= $textNode->nodeValue;
            }
            if (trim($lineText) !== '') {
                $extractedLines[] = trim($lineText);
            }
        }

        return implode("\n", $extractedLines);
    }

    private static function extractFromPptx($filePath) {
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

                $dom = new DOMDocument();
                libxml_use_internal_errors(true);
                $dom->loadXML($slideXml);
                libxml_clear_errors();

                $xpath = new DOMXPath($dom);
                $xpath->registerNamespace('a', 'http://schemas.openxmlformats.org/drawingml/2006/main');

                $textNodes = $xpath->query('//a:t');
                $slideLines = [];
                foreach ($textNodes as $node) {
                    $val = trim($node->nodeValue);
                    if ($val !== '') {
                        $slideLines[] = $val;
                    }
                }
                if (!empty($slideLines)) {
                    $extractedTextLines[] = "--- Slide {$slideCount} ---\n" . implode(" ", $slideLines);
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
        
        $pdftotextPath = exec('which pdftotext 2>/dev/null');
        if (!empty($pdftotextPath) && is_executable($pdftotextPath)) {
            $outputFile = tempnam(sys_get_temp_dir(), 'pdf_txt_');
            $command = escapeshellcmd("{$pdftotextPath} -enc UTF-8 " . escapeshellarg($filePath) . " " . escapeshellarg($outputFile));
            exec($command, $output, $returnVar);

            if ($returnVar === 0 && file_exists($outputFile)) {
                $text = file_get_contents($outputFile);
                @unlink($outputFile);
                if (trim($text) !== '') {
                    $pages = preg_match_all('/\f/', $text) + 1;
                    return ['text' => $text, 'pages' => $pages];
                }
            }
            @unlink($outputFile);
        }

        
        $content = file_get_contents($filePath);
        if (!$content) {
            throw new Exception("Unable to read PDF file.");
        }

        if (strpos($content, '%PDF-') !== 0) {
            throw new Exception("File header does not match valid PDF format.");
        }

        if (strpos($content, '/Encrypt') !== false) {
            throw new Exception("PDF is encrypted or password-protected.");
        }

        $pages = preg_match_all('/\/Type\s*\/Page[^s]/i', $content);
        if ($pages === 0) $pages = 1;

        
        preg_match_all('/stream[\r\n]+(.*?)[\r\n]+endstream/is', $content, $matches);
        $extractedText = '';

        foreach ($matches[1] as $stream) {
            
            $decompressed = @gzuncompress($stream);
            if (!$decompressed) {
                $decompressed = @gzinflate($stream);
            }
            $data = $decompressed ? $decompressed : $stream;

            
            preg_match_all('/(?:[\(\[\<])([^\)\>\]]*)(?:[\)\]\>])\s*(?:Tj|TJ|\')/i', $data, $textMatches);
            foreach ($textMatches[1] as $tm) {
                $cleaned = preg_replace('/[^\x20-\x7E\n]/', '', $tm);
                if (strlen($cleaned) > 1) {
                    $extractedText .= $cleaned . " ";
                }
            }
        }

        return [
            'text' => trim($extractedText),
            'pages' => max(1, $pages)
        ];
    }
}
