<?php

class FileValidationService {
    const MAX_FILE_SIZE = 10485760; 

    public static function validateFile($fileTmpPath, $originalFilename, $maxSize = self::MAX_FILE_SIZE) {
        
        if (strpos($originalFilename, '../') !== false || strpos($originalFilename, '..\\') !== false || strpos($originalFilename, "\0") !== false) {
            return ['success' => false, 'error' => 'Invalid filename: Path traversal characters detected.'];
        }

        
        $fileSize = filesize($fileTmpPath);
        if ($fileSize === 0 || $fileSize > $maxSize) {
            return ['success' => false, 'error' => 'Invalid file size.'];
        }

        
        $clean_original_filename = basename($originalFilename);
        $file_parts = explode('.', $clean_original_filename);
        $forbidden_exts = ['php', 'phtml', 'php3', 'php4', 'php5', 'phps', 'phar', 'exe', 'sh', 'bat', 'cmd', 'js', 'pl', 'py', 'cgi'];
        
        foreach ($file_parts as $part) {
            if (in_array(strtolower($part), $forbidden_exts)) {
                return ['success' => false, 'error' => 'Invalid filename: Forbidden extension detected.'];
            }
        }

        $file_ext = strtolower(pathinfo($clean_original_filename, PATHINFO_EXTENSION));

        
        $content = file_get_contents($fileTmpPath, false, null, 0, 8);
        if ($content === false) {
            return ['success' => false, 'error' => 'Could not read file.'];
        }

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime_type = finfo_file($finfo, $fileTmpPath);
        finfo_close($finfo);

        $identified = false;

        
        if ($file_ext === 'pdf') {
            if (strpos($content, '%PDF-') !== 0) {
                return ['success' => false, 'error' => 'Invalid PDF: Magic bytes do not match.'];
            }
            $identified = true;
        } elseif ($file_ext === 'png') {
            if (strpos($content, "\x89PNG\x0D\x0A\x1A\x0A") !== 0) {
                return ['success' => false, 'error' => 'Invalid PNG: Magic bytes do not match.'];
            }
            $identified = true;
        } elseif ($file_ext === 'jpg' || $file_ext === 'jpeg') {
            if (strpos($content, "\xFF\xD8\xFF") !== 0) {
                return ['success' => false, 'error' => 'Invalid JPEG: Magic bytes do not match.'];
            }
            $identified = true;
        } elseif ($file_ext === 'docx' || $file_ext === 'pptx') {
            if (strpos($content, "PK\x03\x04") !== 0) {
                return ['success' => false, 'error' => 'Invalid Office document: Not a ZIP container.'];
            }
            
            $zip = new ZipArchive();
            if ($zip->open($fileTmpPath) === true) {
                $valid = false;
                if ($file_ext === 'docx') {
                    if ($zip->getFromName('word/document.xml') !== false) {
                        $valid = true;
                    }
                } elseif ($file_ext === 'pptx') {
                    for ($i = 0; $i < $zip->numFiles; $i++) {
                        $filename = $zip->getNameIndex($i);
                        if (preg_match('/^ppt\/slides\/slide\d+\.xml$/i', $filename)) {
                            $valid = true;
                            break;
                        }
                    }
                }
                $zip->close();
                if (!$valid) {
                    return ['success' => false, 'error' => "Invalid {$file_ext}: Missing required internal structure."];
                }
                $identified = true;
            } else {
                return ['success' => false, 'error' => 'Invalid Office document: Could not open ZIP container.'];
            }
        } elseif ($file_ext === 'txt') {
            
            $fullContent = file_get_contents($fileTmpPath);
            if (!mb_check_encoding($fullContent, 'UTF-8')) {
                return ['success' => false, 'error' => 'Invalid TXT: File must be valid UTF-8.'];
            }
            if (preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', $fullContent)) {
                return ['success' => false, 'error' => 'Invalid TXT: Binary characters detected.'];
            }
            $identified = true;
        } else {
            return ['success' => false, 'error' => 'Unsupported file extension.'];
        }

        if ($mime_type === 'application/octet-stream' && !$identified) {
            return ['success' => false, 'error' => 'Invalid file: application/octet-stream without positive magic byte identification.'];
        }

        return ['success' => true];
    }
}
