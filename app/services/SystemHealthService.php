<?php

require_once __DIR__ . '/../database.php';
require_once __DIR__ . '/SystemSettingsService.php';
require_once __DIR__ . '/AcademicStructureService.php';

class SystemHealthService {

    public static function getHealthDiagnostics() {
        $diagnostics = [];

        // 1. Database Connection
        try {
            $pdo = getDBConnection();
            $version = $pdo->query("SELECT VERSION()")->fetchColumn();
            $dbName = $pdo->query("SELECT DATABASE()")->fetchColumn();
            $diagnostics['database'] = [
                'status' => 'PASS',
                'label' => 'Database Connection',
                'details' => "Connected to MySQL/MariaDB v{$version} (DB: {$dbName})",
                'mysql_version' => $version
            ];
        } catch (Exception $e) {
            $diagnostics['database'] = [
                'status' => 'FAIL',
                'label' => 'Database Connection',
                'details' => "Database Connection Error: " . $e->getMessage(),
                'mysql_version' => 'Unknown'
            ];
        }

        // 2. Storage & Writable Directories
        $uploadDir = __DIR__ . '/../../uploads';
        $teacherUploadDir = __DIR__ . '/../../teacher/uploads';
        $backupDir = __DIR__ . '/../../database/backups';

        $isUploadWritable = is_writable($uploadDir);
        $isTeacherUploadWritable = is_writable($teacherUploadDir);
        $isBackupWritable = is_writable($backupDir);

        if ($isUploadWritable && $isTeacherUploadWritable && $isBackupWritable) {
            $diagnostics['storage_permissions'] = [
                'status' => 'PASS',
                'label' => 'Storage Permissions',
                'details' => 'All upload, lesson, and backup directories are writable.'
            ];
        } else {
            $diagnostics['storage_permissions'] = [
                'status' => 'WARNING',
                'label' => 'Storage Permissions',
                'details' => 'One or more storage directories require write permissions.'
            ];
        }

        // Disk space
        $freeDisk = @disk_free_space(__DIR__);
        $totalDisk = @disk_total_space(__DIR__);
        $usedPercent = ($totalDisk > 0) ? round((($totalDisk - $freeDisk) / $totalDisk) * 100, 1) : 0;

        $diagnostics['disk_usage'] = [
            'status' => $usedPercent > 90 ? 'WARNING' : 'PASS',
            'label' => 'Disk Usage',
            'details' => self::formatBytes($freeDisk) . " free of " . self::formatBytes($totalDisk) . " ({$usedPercent}% used)"
        ];

        // 3. Groq AI Configuration
        $aiKey = getenv('GROQ_API_KEY') ?: (defined('GROQ_API_KEY') ? GROQ_API_KEY : '');
        if (!empty($aiKey) && strlen($aiKey) > 10 && strpos($aiKey, 'gsk_') === 0) {
            $diagnostics['groq_ai'] = [
                'status' => 'PASS',
                'label' => 'Groq AI Service',
                'details' => 'Groq API Key configured and active.'
            ];
        } else {
            $diagnostics['groq_ai'] = [
                'status' => 'WARNING',
                'label' => 'Groq AI Service',
                'details' => 'Groq API Key missing or unconfigured (Using deterministic offline fallback generators).'
            ];
        }

        // 4. OCR Availability
        $tesseractPath = exec('which tesseract 2>/dev/null');
        if (!empty($tesseractPath) || function_exists('imagecreatefrompng')) {
            $diagnostics['ocr_engine'] = [
                'status' => 'PASS',
                'label' => 'OCR Engine',
                'details' => !empty($tesseractPath) ? "Tesseract CLI available at {$tesseractPath}" : 'PHP GD image processing available for OCR parser.'
            ];
        } else {
            $diagnostics['ocr_engine'] = [
                'status' => 'WARNING',
                'label' => 'OCR Engine',
                'details' => 'OCR dependencies missing; offline fallback scoring active.'
            ];
        }

        // 5. PHP Version & Extensions
        $requiredExts = ['pdo', 'pdo_mysql', 'gd', 'curl', 'mbstring', 'fileinfo', 'json'];
        $missingExts = [];
        foreach ($requiredExts as $ext) {
            if (!extension_loaded($ext)) {
                $missingExts[] = $ext;
            }
        }

        if (empty($missingExts)) {
            $diagnostics['php_extensions'] = [
                'status' => 'PASS',
                'label' => 'PHP Version & Extensions',
                'details' => "PHP v" . PHP_VERSION . " (" . implode(', ', $requiredExts) . " loaded)"
            ];
        } else {
            $diagnostics['php_extensions'] = [
                'status' => 'WARNING',
                'label' => 'PHP Extensions',
                'details' => "Missing PHP extensions: " . implode(', ', $missingExts)
            ];
        }

        // 6. Academic Configuration
        $activeSy = AcademicStructureService::getActiveSchoolYear();
        $activeSem = AcademicStructureService::getActiveSemester();
        $mMode = SystemSettingsService::getSetting('maintenance_mode', 'off');

        if ($activeSy && $activeSem && intval($activeSem['school_year_id']) === intval($activeSy['id'])) {
            $diagnostics['academic_config'] = [
                'status' => 'PASS',
                'label' => 'Academic Configuration',
                'details' => "Active SY: {$activeSy['school_year']} | Semester: {$activeSem['semester_name']} | Maintenance: " . strtoupper($mMode)
            ];
        } else {
            $diagnostics['academic_config'] = [
                'status' => 'WARNING',
                'label' => 'Academic Configuration',
                'details' => "Academic setup notice: SY or Semester misaligned or missing."
            ];
        }

        return $diagnostics;
    }

    public static function getDeploymentChecklist() {
        $diag = self::getHealthDiagnostics();
        $checklist = [];

        foreach ($diag as $key => $item) {
            $checklist[] = [
                'key' => $key,
                'label' => $item['label'],
                'status' => $item['status'],
                'details' => $item['details']
            ];
        }

        return $checklist;
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
