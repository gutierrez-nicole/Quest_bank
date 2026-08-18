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
                'extraction_mode' => 'image_ocr',
                'text' => '',
                'ocr_text' => '',
                'confidence' => 0.00,
                'suggested_manual_review' => true,
                'error' => 'File not found on server.',
                'ocr_error' => 'File not found on server.',
                'page_count' => 0,
                'pages' => [],
                'execution_time_ms' => 0.00
            ];
        }

        $fileSize = filesize($filePath);
        if ($fileSize === 0) {
            return [
                'success' => false,
                'status' => 'failed',
                'extraction_mode' => 'image_ocr',
                'text' => '',
                'ocr_text' => '',
                'confidence' => 0.00,
                'suggested_manual_review' => true,
                'error' => 'Uploaded answer sheet file is empty (0 bytes).',
                'ocr_error' => 'Uploaded answer sheet file is empty (0 bytes).',
                'page_count' => 1,
                'pages' => [],
                'execution_time_ms' => 0.00
            ];
        }

        if ($fileSize > 20971520) { 
            return [
                'success' => false,
                'status' => 'failed',
                'extraction_mode' => 'image_ocr',
                'text' => '',
                'ocr_text' => '',
                'confidence' => 0.00,
                'suggested_manual_review' => true,
                'error' => 'Uploaded answer sheet file exceeds maximum size limit of 20MB.',
                'ocr_error' => 'Uploaded answer sheet file exceeds maximum size limit of 20MB.',
                'page_count' => 1,
                'pages' => [],
                'execution_time_ms' => 0.00
            ];
        }

        
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $detectedMime = finfo_file($finfo, $filePath);
        finfo_close($finfo);

        
        $headerBytes = @file_get_contents($filePath, false, null, 0, 16);
        $isPdfBytes = (strpos($headerBytes, '%PDF-') === 0);
        $isPngBytes = (substr($headerBytes, 0, 4) === "\x89PNG");
        $isJpegBytes = (substr($headerBytes, 0, 3) === "\xFF\xD8\xFF");

        $isValidMagicBytes = ($isPdfBytes || $isPngBytes || $isJpegBytes);

        if (!$isValidMagicBytes) {
            return [
                'success' => false,
                'status' => 'failed',
                'extraction_mode' => 'image_ocr',
                'text' => '',
                'ocr_text' => '',
                'confidence' => 0.00,
                'suggested_manual_review' => true,
                'error' => "Security check failed: File header magic bytes do not match valid PDF, PNG, or JPEG format.",
                'ocr_error' => "Security check failed: File header magic bytes do not match valid PDF, PNG, or JPEG format.",
                'page_count' => 1,
                'pages' => [],
                'execution_time_ms' => 0.00
            ];
        }

        try {
            $extractedText = '';
            $confidence = 0.00;
            $pageCount = 1;
            $status = 'completed';
            $extractionMode = ($fileExt === 'pdf') ? 'native_pdf_text' : 'image_ocr';
            $suggestedManualReview = false;
            $errorMessage = null;
            $pagesData = [];

            if ($fileExt === 'pdf') {
                $pdfRes = self::processPdfFile($filePath);
                $extractedText = $pdfRes['text'];
                $pageCount = $pdfRes['pages'];
                $confidence = $pdfRes['confidence'];
                $status = $pdfRes['status'];
                $extractionMode = $pdfRes['extraction_mode'];
                $suggestedManualReview = $pdfRes['suggested_manual_review'];
                $errorMessage = $pdfRes['error'];
                $pagesData = $pdfRes['pages_data'] ?? [];
            } else {
                $imageRes = self::processImageFile($filePath, $fileExt);
                $extractedText = $imageRes['text'];
                $confidence = $imageRes['confidence'];
                $status = $imageRes['status'];
                $extractionMode = 'image_ocr';
                $suggestedManualReview = $imageRes['suggested_manual_review'];
                $errorMessage = $imageRes['error'];
                $pagesData = [$imageRes];
            }

            $cleanText = self::cleanOcrText($extractedText);
            $executionTime = round((microtime(true) - $startTime) * 1000, 2);

            
            if ($status === 'failed') {
                return [
                    'success' => false,
                    'status' => 'failed',
                    'extraction_mode' => $extractionMode,
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

            
            if (empty(trim($cleanText))) {
                return [
                    'success' => true,
                    'status' => 'manual_review_required',
                    'extraction_mode' => $extractionMode,
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

            if (($confidence !== null && $confidence < self::OCR_REVIEW_THRESHOLD) || $suggestedManualReview) {
                $status = 'manual_review_required';
                $suggestedManualReview = true;
            }

            return [
                'success' => true,
                'status' => $status,
                'extraction_mode' => $extractionMode,
                'text' => $cleanText,
                'ocr_text' => $cleanText,
                'confidence' => ($confidence !== null) ? round($confidence, 2) : null,
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
                'extraction_mode' => 'image_ocr',
                'text' => '',
                'ocr_text' => '',
                'confidence' => 0.00,
                'suggested_manual_review' => true,
                'error' => $e->getMessage(),
                'ocr_error' => $e->getMessage(),
                'page_count' => 1,
                'pages' => [],
                'execution_time_ms' => 0.00
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
                        'status' => 'completed',
                        'suggested_manual_review' => false,
                        'error' => 'Blank image page detected.'
                    ];
                }
            }
        }

        
        $tesseractPath = '';
        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            $winWhere = @exec('where tesseract 2>NUL');
            if (!empty($winWhere) && file_exists(trim($winWhere))) {
                $tesseractPath = trim($winWhere);
            } elseif (file_exists('C:\\Program Files\\Tesseract-OCR\\tesseract.exe')) {
                $tesseractPath = 'C:\\Program Files\\Tesseract-OCR\\tesseract.exe';
            }
        } else {
            $tesseractPath = @exec('which tesseract 2>/dev/null');
        }

        if (!empty($tesseractPath) && (is_executable($tesseractPath) || file_exists($tesseractPath))) {
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

        
        $groqRes = self::processImageWithGroqVision($filePath, $fileExt);
        if ($groqRes['success'] && !empty(trim($groqRes['text']))) {
            return [
                'text' => $groqRes['text'],
                'confidence' => 88.50,
                'status' => 'completed',
                'suggested_manual_review' => false,
                'error' => null
            ];
        }

        
        return [
            'text' => '',
            'confidence' => 0.00,
            'status' => 'manual_review_required',
            'suggested_manual_review' => true,
            'error' => 'Unclear scan or OCR engine unavailable for automatic image parsing. Teacher manual review required.'
        ];
    }

    private static function processImageWithGroqVision($filePath, $fileExt) {
        $apiKey = defined('GROQ_API_KEY') ? GROQ_API_KEY : getenv('GROQ_API_KEY');
        if (empty($apiKey) || !file_exists($filePath)) {
            return ['success' => false, 'text' => ''];
        }

        try {
            $imageData = base64_encode(file_get_contents($filePath));
            $mimeType = ($fileExt === 'png') ? 'image/png' : 'image/jpeg';
            $endpoint = defined('GROQ_API_ENDPOINT') ? GROQ_API_ENDPOINT : 'https://api.groq.com/openai/v1/chat/completions';

            $payload = [
                'model' => 'llama-3.2-11b-vision-preview',
                'messages' => [
                    [
                        'role' => 'user',
                        'content' => [
                            [
                                'type' => 'text',
                                'text' => 'You are an OCR scanner for an examination answer sheet. Extract all question numbers and student answers line by line (e.g. 1. A, 2. B, 3. C). Return raw extracted text.'
                            ],
                            [
                                'type' => 'image_url',
                                'image_url' => [
                                    'url' => "data:{$mimeType};base64,{$imageData}"
                                ]
                            ]
                        ]
                    ]
                ],
                'temperature' => 0.1,
                'max_tokens' => 1024
            ];

            $apiKey = trim(trim((string)$apiKey), "\"' \t\n\r\0\x0B");
            $jsonPayload = json_encode($payload, JSON_INVALID_UTF8_SUBSTITUTE | JSON_UNESCAPED_UNICODE);
            $ch = curl_init($endpoint);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => $jsonPayload,
                CURLOPT_HTTPHEADER => [
                    'Content-Type: application/json',
                    'Authorization: Bearer ' . $apiKey
                ],
                CURLOPT_TIMEOUT => 20,
                CURLOPT_SSL_VERIFYPEER => false
            ]);

            $response = curl_exec($ch);
            $err = curl_error($ch);
            curl_close($ch);

            if ($err || !$response) {
                return ['success' => false, 'text' => ''];
            }

            $json = json_decode($response, true);
            $extractedText = $json['choices'][0]['message']['content'] ?? '';
            if (!empty(trim($extractedText))) {
                return ['success' => true, 'text' => trim($extractedText)];
            }
        } catch (Throwable $e) {
            
        }

        return ['success' => false, 'text' => ''];
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
                'extraction_mode' => 'scanned_pdf_ocr',
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
                'extraction_mode' => 'scanned_pdf_ocr',
                'suggested_manual_review' => true,
                'error' => 'PDF document is encrypted or password-protected.'
            ];
        }

        $pages = preg_match_all('/\/Type\s*\/Page[^s]/i', $content);
        if ($pages === 0) $pages = 1;

        
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
            return [
                'text' => trim($extractedText),
                'pages' => max(1, $pages),
                'confidence' => 100.00, 
                'status' => 'completed',
                'extraction_mode' => 'native_pdf_text',
                'suggested_manual_review' => false,
                'error' => null
            ];
        }

        
        $pdftoppmPath = exec('which pdftoppm 2>/dev/null');
        $tesseractPath = exec('which tesseract 2>/dev/null');

        if (!empty($pdftoppmPath) && !empty($tesseractPath)) {
            $tmpDir = sys_get_temp_dir() . '/pdf_pages_' . uniqid();
            mkdir($tmpDir, 0777, true);
            $cmdConvert = sprintf("%s -png %s %s/page", escapeshellcmd($pdftoppmPath), escapeshellarg($filePath), escapeshellarg($tmpDir));
            exec($cmdConvert);

            $pageImages = glob("{$tmpDir}/page-*.png");
            sort($pageImages);

            if (!empty($pageImages)) {
                $combinedText = '';
                $allConfidences = [];
                $pagesData = [];

                foreach ($pageImages as $idx => $imgFile) {
                    $imgRes = self::processImageFile($imgFile, 'png');
                    $combinedText .= "\n--- Page " . ($idx + 1) . " ---\n" . $imgRes['text'];
                    if ($imgRes['confidence'] > 0) {
                        $allConfidences[] = $imgRes['confidence'];
                    }
                    $pagesData[] = $imgRes;
                    @unlink($imgFile);
                }
                @rmdir($tmpDir);

                $avgConf = !empty($allConfidences) ? array_sum($allConfidences) / count($allConfidences) : 0.00;
                return [
                    'text' => trim($combinedText),
                    'pages' => count($pageImages),
                    'confidence' => round($avgConf, 2),
                    'status' => ($avgConf >= self::OCR_REVIEW_THRESHOLD) ? 'completed' : 'manual_review_required',
                    'extraction_mode' => 'scanned_pdf_ocr',
                    'suggested_manual_review' => ($avgConf < self::OCR_REVIEW_THRESHOLD),
                    'pages_data' => $pagesData,
                    'error' => null
                ];
            }
        }

        
        return [
            'text' => '',
            'pages' => max(1, $pages),
            'confidence' => 0.00,
            'status' => 'manual_review_required',
            'extraction_mode' => 'scanned_pdf_ocr',
            'suggested_manual_review' => true,
            'error' => 'Scanned PDF does not contain a readable text layer. Page conversion tools (pdftoppm/tesseract) unavailable. Manual teacher review required.'
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
