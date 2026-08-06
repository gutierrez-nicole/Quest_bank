<?php

require_once __DIR__ . '/../database.php';
require_once __DIR__ . '/AuditLogService.php';

class StorageManagementService {

    public static function getStorageOverview() {
        $pdo = getDBConnection();

        // 1. Lesson Materials Storage
        $stmtLessons = $pdo->query("SELECT COUNT(*) as count, SUM(file_size) as total_bytes FROM lesson_materials");
        $lessonData = $stmtLessons->fetch(PDO::FETCH_ASSOC);

        // 2. Exam Submissions & OCR Storage
        $stmtSubmissions = $pdo->query("SELECT COUNT(*) as count FROM exam_submissions WHERE file_path IS NOT NULL AND file_path != ''");
        $subCount = intval($stmtSubmissions->fetchColumn());

        $submissionsDir = __DIR__ . '/../../uploads/submissions';
        $subBytes = self::getDirectorySize($submissionsDir);

        // 3. Backups Storage
        $backupDir = __DIR__ . '/../../database/backups';
        $backupBytes = self::getDirectorySize($backupDir);
        $backupCount = count(glob($backupDir . '/qb_backup_*.sql'));

        // 4. Temporary Files Storage
        $sysTempDir = sys_get_temp_dir();
        $tempFiles = array_merge(
            glob($sysTempDir . '/qb_batch_*.csv'),
            glob($sysTempDir . '/test_*'),
            glob(__DIR__ . '/../../scratch/*')
        );

        $tempBytes = 0;
        foreach ($tempFiles as $tf) {
            if (is_file($tf)) $tempBytes += filesize($tf);
        }

        return [
            'lessons' => [
                'count' => intval($lessonData['count'] ?? 0),
                'size_bytes' => intval($lessonData['total_bytes'] ?? 0),
                'size_formatted' => self::formatBytes(intval($lessonData['total_bytes'] ?? 0))
            ],
            'submissions' => [
                'count' => $subCount,
                'size_bytes' => $subBytes,
                'size_formatted' => self::formatBytes($subBytes)
            ],
            'backups' => [
                'count' => $backupCount,
                'size_bytes' => $backupBytes,
                'size_formatted' => self::formatBytes($backupBytes)
            ],
            'temporary' => [
                'count' => count($tempFiles),
                'size_bytes' => $tempBytes,
                'size_formatted' => self::formatBytes($tempBytes)
            ]
        ];
    }

    public static function cleanTemporaryFiles($actorId = null) {
        $sysTempDir = sys_get_temp_dir();
        $targets = array_merge(
            glob($sysTempDir . '/qb_batch_*.csv'),
            glob($sysTempDir . '/test_subj_*.csv'),
            glob($sysTempDir . '/test_stud_*.csv'),
            glob($sysTempDir . '/test_students_*.csv')
        );

        $cleanedCount = 0;
        $freedBytes = 0;

        foreach ($targets as $file) {
            if (is_file($file)) {
                $freedBytes += filesize($file);
                if (@unlink($file)) {
                    $cleanedCount++;
                }
            }
        }

        if ($actorId) {
            AuditLogService::logAction($actorId, "Cleaned Temporary Files", "Removed {$cleanedCount} temporary files, freed " . self::formatBytes($freedBytes));
        }

        return [
            'cleaned_count' => $cleanedCount,
            'freed_bytes' => $freedBytes,
            'freed_formatted' => self::formatBytes($freedBytes)
        ];
    }

    public static function listOrphanedFiles() {
        $pdo = getDBConnection();

        // Active files in database
        $dbLessonFiles = $pdo->query("SELECT stored_filename FROM lesson_materials WHERE stored_filename IS NOT NULL AND stored_filename != ''")->fetchAll(PDO::FETCH_COLUMN);
        $dbSubmissionFiles = $pdo->query("SELECT file_path FROM exam_submissions WHERE file_path IS NOT NULL AND file_path != ''")->fetchAll(PDO::FETCH_COLUMN);

        $referencedBasenames = array_map('basename', array_merge($dbLessonFiles, $dbSubmissionFiles));

        // Disk files in uploads & teacher uploads
        $diskFiles = array_merge(
            glob(__DIR__ . '/../../teacher/uploads/*'),
            glob(__DIR__ . '/../../uploads/submissions/*')
        );

        $orphaned = [];
        foreach ($diskFiles as $file) {
            if (!is_file($file)) continue;
            $base = basename($file);
            if ($base === '.htaccess' || strpos($base, 'demo_') === 0) continue;

            if (!in_array($base, $referencedBasenames, true)) {
                $orphaned[] = [
                    'filename' => $base,
                    'file_path' => $file,
                    'size' => filesize($file),
                    'size_formatted' => self::formatBytes(filesize($file)),
                    'modified_at' => date('Y-m-d H:i:s', filemtime($file))
                ];
            }
        }

        return $orphaned;
    }

    public static function getQuestBankTempDir() {
        $dir = __DIR__ . '/../../storage/tmp';
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        @file_put_contents($dir . '/.htaccess', "Deny from all\n");
        return realpath($dir) ?: $dir;
    }

    public static function deleteOrphanedFiles($filePaths, $actorId = null) {
        $pdo = getDBConnection();

        $qbTempDir = self::getQuestBankTempDir();
        $sysTempDir = realpath(sys_get_temp_dir());

        $approvedRoots = [
            realpath(__DIR__ . '/../../teacher/uploads'),
            realpath(__DIR__ . '/../../uploads/submissions'),
            realpath(__DIR__ . '/../../uploads/exports'),
            $qbTempDir
        ];
        $approvedRoots = array_values(array_filter($approvedRoots));

        $requestedCount = count($filePaths);
        $deletedCount = 0;
        $rejectedCount = 0;
        $freedBytes = 0;
        $rejections = [];

        $safeSysTempPrefixes = ['qb_batch_', 'qb_preview_', 'qb_export_', 'test_subj_', 'test_stud_', 'test_students_'];

        foreach ($filePaths as $path) {
            if (is_link($path)) {
                $rejectedCount++;
                $rejections[] = ['file' => $path, 'reason' => 'Symlinks are strictly rejected for security reasons.'];
                continue;
            }

            $realPath = realpath($path);
            if (!$realPath || !file_exists($realPath)) {
                $rejectedCount++;
                $rejections[] = ['file' => $path, 'reason' => 'File does not exist or invalid path.'];
                continue;
            }

            if (is_dir($realPath)) {
                $rejectedCount++;
                $rejections[] = ['file' => $path, 'reason' => 'Directories cannot be deleted.'];
                continue;
            }

            $base = basename($realPath);
            $ext = strtolower(pathinfo($realPath, PATHINFO_EXTENSION));
            $blacklistedExts = ['php', 'sql', 'env', 'json', 'htaccess', 'sh', 'exe', 'bat', 'cmd', 'py', 'pl'];
            if (in_array($ext, $blacklistedExts, true) || strpos($base, '.') === 0) {
                $rejectedCount++;
                $rejections[] = ['file' => $path, 'reason' => "Protected system/source file type '.{$ext}' cannot be deleted."];
                continue;
            }

            $dirOfFile = realpath(dirname($realPath));

            // System temp directory legacy handling
            if ($sysTempDir && $dirOfFile === $sysTempDir) {
                $isSafeSysTemp = false;
                foreach ($safeSysTempPrefixes as $pref) {
                    if (strpos($base, $pref) === 0) {
                        $isSafeSysTemp = true;
                        break;
                    }
                }
                if (!$isSafeSysTemp) {
                    $rejectedCount++;
                    $rejections[] = ['file' => $path, 'reason' => 'Unrelated OS system temporary file cannot be deleted.'];
                    continue;
                }
            } else {
                // Approved boundary check for non-system-temp paths
                $inApprovedRoot = false;
                foreach ($approvedRoots as $root) {
                    if ($root && (strpos($realPath, $root . DIRECTORY_SEPARATOR) === 0 || $realPath === $root)) {
                        $inApprovedRoot = true;
                        break;
                    }
                }
                if (!$inApprovedRoot) {
                    $rejectedCount++;
                    $rejections[] = ['file' => $path, 'reason' => 'File path is outside approved QuestBank upload/storage directories.'];
                    continue;
                }
            }

            $base = basename($realPath);
            $stmtL = $pdo->prepare("SELECT COUNT(*) FROM lesson_materials WHERE stored_filename = ? OR original_filename = ?");
            $stmtL->execute([$base, $base]);
            $isLessonRef = ($stmtL->fetchColumn() > 0);

            $stmtS = $pdo->prepare("SELECT COUNT(*) FROM exam_submissions WHERE file_path LIKE ?");
            $stmtS->execute(['%' . $base]);
            $isSubRef = ($stmtS->fetchColumn() > 0);

            if ($isLessonRef || $isSubRef) {
                $rejectedCount++;
                $rejections[] = ['file' => $path, 'reason' => 'File is referenced in active database records and cannot be deleted.'];
                continue;
            }

            $fileSize = filesize($realPath);
            if (@unlink($realPath)) {
                $deletedCount++;
                $freedBytes += $fileSize;
            } else {
                $rejectedCount++;
                $rejections[] = ['file' => $path, 'reason' => 'Permission denied while deleting file from disk.'];
            }
        }

        if ($actorId && $deletedCount > 0) {
            AuditLogService::logAction($actorId, "Deleted Orphaned Files", "Requested: {$requestedCount}, Deleted: {$deletedCount}, Rejected: {$rejectedCount}, Freed: " . self::formatBytes($freedBytes));
        }

        return [
            'requested_count' => $requestedCount,
            'deleted_count' => $deletedCount,
            'rejected_count' => $rejectedCount,
            'freed_bytes' => $freedBytes,
            'freed_formatted' => self::formatBytes($freedBytes),
            'rejections' => $rejections
        ];
    }

    private static function getDirectorySize($dir) {
        $size = 0;
        if (!is_dir($dir)) return 0;

        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($files as $file) {
            if ($file->isFile()) {
                $size += $file->getSize();
            }
        }
        return $size;
    }

    private static function formatBytes($bytes, $precision = 2) {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= (1 << (10 * $pow));
        return round($bytes, $precision) . ' ' . $units[$pow];
    }
}
