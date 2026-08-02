<?php

require_once __DIR__ . '/../database.php';
require_once __DIR__ . '/../../includes/security.php';

class OcrService {

    const OCR_REVIEW_THRESHOLD = 75.0;

    public static function processAnswerSheet($filePath, $fileExt = 'png') {
        $startTime = microtime(true);
        $fileExt = strtolower($fileExt);

        if (!file_exists($filePath)) {
            return [
                'success' => false,
                'status' => 'failed',
                'text' => '',
                'ocr_text' => '',
                'confidence' => 0.00,
                'suggested_manual_review' => true,
                'error' => 'File not found on server.',
                'ocr_error' => 'File not found on server.',
                'page_count' => 0,
                'pages' => []
            ];
        }

        $fileSize = filesize($filePath);
        if ($fileSize === 0) {
            return [
                'success' => false,
                'status' => 'failed',
                'text' => '',
                'ocr_text' => '',
                'confidence' => 0.00,
                'suggested_manual_review' => true,
                'error' => 'Uploaded answer sheet file is empty (0 bytes).',
                'ocr_error' => 'Uploaded answer sheet file is empty (0 bytes).',
                'page_count' => 1,
                'pages' => []
            ];
        }

        if ($fileSize > 20971520) { // 20MB
            return [
                'success' => false,
                'status' => 'failed',
                'text' => '',
                'ocr_text' => '',
                'confidence' => 0.00,
                'suggested_manual_review' => true,
                'error' => 'Uploaded answer sheet file exceeds maximum size limit of 20MB.',
                'ocr_error' => 'Uploaded answer sheet file exceeds maximum size limit of 20MB.',
                'page_count' => 1,
                'pages' => []
            ];
        }

        // MIME Inspection
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $detectedMime = finfo_file($finfo, $filePath);
        finfo_close($finfo);

        $allowedMimes = ['image/jpeg', 'image/png', 'image/jpg', 'application/pdf'];
        if (!in_array($detectedMime, $allowedMimes) && $detectedMime !== 'application/octet-stream') {
            return [
                'success' => false,
                'status' => 'failed',
                'text' => '',
                'ocr_text' => '',
                'confidence' => 0.00,
                'suggested_manual_review' => true,
                'error' => "File content type does not match supported formats JPG, PNG, PDF (Detected: {$detectedMime}).",
                'ocr_error' => "File content type does not match supported formats JPG, PNG, PDF (Detected: {$detectedMime}).",
                'page_count' => 1,
                'pages' => []
            ];
        }

        try {
            $extractedText = '';
            $confidence = 0.00;
            $pageCount = 1;
            $status = 'completed';
            $suggestedManualReview = false;
            $errorMessage = null;
            $pagesData = [];

            if ($fileExt === 'pdf') {
                $pdfRes = self::processPdfFile($filePath);
                $extractedText = $pdfRes['text'];
                $pageCount = $pdfRes['pages'];
                $confidence = $pdfRes['confidence'];
                $status = $pdfRes['status'];
                $suggestedManualReview = $pdfRes['suggested_manual_review'];
                $errorMessage = $pdfRes['error'];
                $pagesData = $pdfRes['pages_data'] ?? [];
            } else {
                $imageRes = self::processImageFile($filePath, $fileExt);
                $extractedText = $imageRes['text'];
                $confidence = $imageRes['confidence'];
                $status = $imageRes['status'];
                $suggestedManualReview = $imageRes['suggested_manual_review'];
                $errorMessage = $imageRes['error'];
                $pagesData = [$imageRes];
            }

            $cleanText = self::cleanOcrText($extractedText);
            $executionTime = round((microtime(true) - $startTime) * 1000, 2);

            // Preserved failed status
            if ($status === 'failed') {
                return [
                    'success' => false,
                    'status' => 'failed',
                    'text' => '',
                    'ocr_text' => '',
                    'confidence' => 0.00,
                    'page_count' => $pageCount,
                    'pages' => $pagesData,
                    'suggested_manual_review' => true,
                    'error' => $errorMessage ?: 'OCR processing failed.',
                    'ocr_error' => $errorMessage ?: 'OCR processing failed.',
                    'execution_time_ms' => $executionTime
                ];
            }

            // Empty/Blank output handling
            if (empty(trim($cleanText))) {
                return [
                    'success' => true,
                    'status' => 'manual_review_required',
                    'text' => '',
                    'ocr_text' => '',
                    'confidence' => 0.00,
                    'page_count' => $pageCount,
                    'pages' => $pagesData,
                    'suggested_manual_review' => true,
                    'error' => $errorMessage ?: 'No readable text content found in uploaded sheet. Manual teacher review required.',
                    'ocr_error' => $errorMessage ?: 'No readable text content found in uploaded sheet. Manual teacher review required.',
                    'execution_time_ms' => $executionTime
                ];
            }

            if ($confidence < self::OCR_REVIEW_THRESHOLD || $suggestedManualReview) {
                $status = 'manual_review_required';
                $suggestedManualReview = true;
            }

            return [
                'success' => true,
                'status' => $status,
                'text' => $cleanText,
                'ocr_text' => $cleanText,
                'confidence' => round($confidence, 2),
                'page_count' => $pageCount,
                'pages' => $pagesData,
                'suggested_manual_review' => $suggestedManualReview,
                'error' => $errorMessage,
                'ocr_error' => $errorMessage,
                'execution_time_ms' => $executionTime
            ];

        } catch (Exception $e) {
            error_log("OCR Processing Exception: " . $e->getMessage());
            return [
                'success' => false,
                'status' => 'failed',
                'text' => '',
                'ocr_text' => '',
                'confidence' => 0.00,
                'suggested_manual_review' => true,
                'error' => $e->getMessage(),
                'ocr_error' => $e->getMessage(),
                'page_count' => 1,
                'pages' => []
            ];
        }
    }

    private static function processImageFile($filePath, $fileExt) {
        $imageInfo = @getimagesize($filePath);
        if (!$imageInfo) {
            return [
                'text' => '',
                'confidence' => 0.00,
                'status' => 'failed',
                'suggested_manual_review' => true,
                'error' => 'Corrupted or invalid image file.'
            ];
        }

        list($width, $height) = $imageInfo;
        if ($width < 50 || $height < 50) {
            return [
                'text' => '',
                'confidence' => 0.00,
                'status' => 'failed',
                'suggested_manual_review' => true,
                'error' => 'Extremely low-resolution image file.'
            ];
        }

        // Check for blank image using GD brightness variance
        if (function_exists('imagecreatefromstring')) {
            $imgData = @file_get_contents($filePath);
            $gdImg = @imagecreatefromstring($imgData);
            if ($gdImg) {
                $isBlank = self::isImageBlank($gdImg, $width, $height);
                imagedestroy($gdImg);
                if ($isBlank) {
                    return [
                        'text' => '',
                        'confidence' => 0.00,
                        'status' => 'manual_review_required',
                        'suggested_manual_review' => true,
                        'error' => 'Blank image page detected.'
                    ];
                }
            }
        }

        // Attempt Tesseract OCR CLI with TSV output for word confidence
        $tesseractPath = exec('which tesseract 2>/dev/null');
        if (!empty($tesseractPath) && is_executable($tesseractPath)) {
            $tmpOutputBase = tempnam(sys_get_temp_dir(), 'ocr_tsv_');
            $command = sprintf(
                "%s %s %s --oem 1 -l eng tsv 2>/dev/null",
                escapeshellcmd($tesseractPath),
                escapeshellarg($filePath),
                escapeshellarg($tmpOutputBase)
            );
            exec($command, $output, $returnVar);

            $tmpTsvFile = $tmpOutputBase . '.tsv';
            if (file_exists($tmpTsvFile)) {
                $tsvContent = file_get_contents($tmpTsvFile);
                @unlink($tmpTsvFile);
                @unlink($tmpOutputBase);

                $parsed = self::parseTesseractTsv($tsvContent);
                if (!empty(trim($parsed['text']))) {
                    return [
                        'text' => $parsed['text'],
                        'confidence' => $parsed['confidence'],
                        'status' => ($parsed['confidence'] >= self::OCR_REVIEW_THRESHOLD) ? 'completed' : 'manual_review_required',
                        'suggested_manual_review' => ($parsed['confidence'] < self::OCR_REVIEW_THRESHOLD),
                        'error' => null
                    ];
                }
            }
        }

        // Default safe failure response when OCR engine unavailable or scan unreadable
        return [
            'text' => '',
            'confidence' => 0.00,
            'status' => 'manual_review_required',
            'suggested_manual_review' => true,
            'error' => 'Unclear scan or OCR engine unavailable for automatic image parsing. Teacher manual review required.'
        ];
    }

    private static function parseTesseractTsv($tsvContent) {
        $lines = explode("\n", trim($tsvContent));
        if (count($lines) <= 1) {
            return ['text' => '', 'confidence' => 0.00];
        }

        $header = explode("\t", array_shift($lines));
        $confIdx = array_search('conf', $header);
        $textIdx = array_search('text', $header);

        if ($confIdx === false || $textIdx === false) {
            return ['text' => '', 'confidence' => 0.00];
        }

        $words = [];
        $confidences = [];

        foreach ($lines as $line) {
            $cols = explode("\t", $line);
            if (count($cols) <= max($confIdx, $textIdx)) continue;

            $conf = floatval($cols[$confIdx]);
            $word = trim($cols[$textIdx]);

            if ($conf > 0 && !empty($word)) {
                $words[] = $word;
                $confidences[] = $conf;
            }
        }

        if (empty($words)) {
            return ['text' => '', 'confidence' => 0.00];
        }

        $avgConfidence = array_sum($confidences) / count($confidences);
        return [
            'text' => implode(' ', $words),
            'confidence' => round($avgConfidence, 2)
        ];
    }

    private static function processPdfFile($filePath) {
        $content = file_get_contents($filePath);
        if (strpos($content, '%PDF-') !== 0) {
            return [
                'text' => '',
                'pages' => 1,
                'confidence' => 0.00,
                'status' => 'failed',
                'suggested_manual_review' => true,
                'error' => 'File is not a valid PDF document.'
            ];
        }

        if (strpos($content, '/Encrypt') !== false) {
            return [
                'text' => '',
                'pages' => 1,
                'confidence' => 0.00,
                'status' => 'failed',
                'suggested_manual_review' => true,
                'error' => 'PDF document is encrypted or password-protected.'
            ];
        }

        $pages = preg_match_all('/\/Type\s*\/Page[^s]/i', $content);
        if ($pages === 0) $pages = 1;

        // Extract text stream objects
        preg_match_all('/stream[\r\n]+(.*?)[\r\n]+endstream/is', $content, $matches);
        $extractedText = '';

        foreach ($matches[1] as $stream) {
            $decompressed = @gzuncompress($stream);
            if (!$decompressed) $decompressed = @gzinflate($stream);
            $data = $decompressed ? $decompressed : $stream;

            preg_match_all('/(?:[\(\[\<])([^\)\>\]]*)(?:[\)\]\>])\s*(?:Tj|TJ|\')/i', $data, $textMatches);
            foreach ($textMatches[1] as $tm) {
                $cleaned = preg_replace('/[^\x20-\x7E\n]/', '', $tm);
                if (strlen($cleaned) > 0) {
                    $extractedText .= $cleaned . " ";
                }
            }
        }

        if (trim($extractedText) !== '') {
            $wordCount = count(explode(' ', trim($extractedText)));
            $computedConf = min(98.00, max(75.00, 70.00 + ($wordCount * 1.5)));
            return [
                'text' => trim($extractedText),
                'pages' => max(1, $pages),
                'confidence' => round($computedConf, 2),
                'status' => 'completed',
                'suggested_manual_review' => false,
                'error' => null
            ];
        }

        // Scanned PDF without text layer
        return [
            'text' => '',
            'pages' => max(1, $pages),
            'confidence' => 0.00,
            'status' => 'manual_review_required',
            'suggested_manual_review' => true,
            'error' => 'Scanned PDF does not contain a readable text layer. Teacher manual review required.'
        ];
    }

    private static function isImageBlank($gdImg, $width, $height) {
        $sampleCount = 100;
        $luminances = [];
        for ($i = 0; $i < $sampleCount; $i++) {
            $x = rand(0, $width - 1);
            $y = rand(0, $height - 1);
            $rgb = imagecolorat($gdImg, $x, $y);
            $r = ($rgb >> 16) & 0xFF;
            $g = ($rgb >> 8) & 0xFF;
            $b = $rgb & 0xFF;
            $luminances[] = 0.299 * $r + 0.587 * $g + 0.114 * $b;
        }

        $mean = array_sum($luminances) / count($luminances);
        $variance = 0.0;
        foreach ($luminances as $l) {
            $variance += pow($l - $mean, 2);
        }
        $stdDev = sqrt($variance / count($luminances));

        return ($stdDev < 4.0);
    }

    private static function cleanOcrText($text) {
        if (!is_string($text)) return '';
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $text);
        $text = preg_replace("/\n{3,}/", "\n\n", $text);
        return trim($text);
    }
}
