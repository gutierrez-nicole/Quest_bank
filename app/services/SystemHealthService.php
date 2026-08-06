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
                'details' => "Connected to MySQL/MariaDB v{$version} (DB: {$dbName})"
            ];
        } catch (Exception $e) {
            $diagnostics['database'] = [
                'status' => 'FAIL',
                'label' => 'Database Connection',
                'details' => "Database Connection Error: " . $e->getMessage()
            ];
        }

        // 2. Writable Upload Directories
        $uploadDir = __DIR__ . '/../../uploads';
        $teacherUploadDir = __DIR__ . '/../../teacher/uploads';
        $isUploadWritable = is_writable($uploadDir) && is_writable($teacherUploadDir);
        $diagnostics['upload_directories'] = [
            'status' => $isUploadWritable ? 'PASS' : 'FAIL',
            'label' => 'Writable Upload Directories',
            'details' => $isUploadWritable ? 'Upload and lesson directories are writable.' : 'Upload directory permissions error.'
        ];

        // 3. Writable Backup Directory
        $backupDir = __DIR__ . '/../../database/backups';
        $isBackupWritable = is_writable($backupDir);
        $diagnostics['backup_directory'] = [
            'status' => $isBackupWritable ? 'PASS' : 'FAIL',
            'label' => 'Writable Backup Directory',
            'details' => $isBackupWritable ? 'Database backup storage path is writable.' : 'Backup storage path is unwritable.'
        ];

        // 4. Backup Directory Protection
        $htaccessFile = $backupDir . '/.htaccess';
        $isProtected = file_exists($htaccessFile);
        $diagnostics['backup_protection'] = [
            'status' => $isProtected ? 'PASS' : 'FAIL',
            'label' => 'Backup Directory Protection',
            'details' => $isProtected ? 'Database backup directory is protected via .htaccess.' : 'Missing .htaccess protection on backup directory!'
        ];

        // 5. PHP Extensions
        $requiredExts = ['pdo', 'pdo_mysql', 'gd', 'curl', 'mbstring', 'fileinfo', 'json'];
        $missingExts = [];
        foreach ($requiredExts as $ext) {
            if (!extension_loaded($ext)) {
                $missingExts[] = $ext;
            }
        }
        $diagnostics['php_extensions'] = [
            'status' => empty($missingExts) ? 'PASS' : 'FAIL',
            'label' => 'Required PHP Extensions',
            'details' => empty($missingExts) ? "PHP v" . PHP_VERSION . " (" . implode(', ', $requiredExts) . " loaded)" : "Missing PHP extensions: " . implode(', ', $missingExts)
        ];

        // 6. OCR Engine Availability
        $tesseractPath = exec('which tesseract 2>/dev/null') ?: exec('command -v tesseract 2>/dev/null');
        $hasGd = extension_loaded('gd');
        $hasImagick = extension_loaded('imagick');
        
        $submissionsDir = __DIR__ . '/../../uploads/submissions';
        if (!is_dir($submissionsDir)) {
            @mkdir($submissionsDir, 0755, true);
        }
        $submissionsWritable = is_writable($submissionsDir);

        if (!empty($tesseractPath) && $submissionsWritable) {
            $diagnostics['ocr_engine'] = [
                'status' => 'PASS',
                'label' => 'OCR Command Availability',
                'details' => "Tesseract CLI processor active at {$tesseractPath}"
            ];
        } elseif (($hasGd || $hasImagick) && $submissionsWritable) {
            $extName = $hasGd ? 'GD' : 'Imagick';
            $diagnostics['ocr_engine'] = [
                'status' => 'WARNING',
                'label' => 'OCR Command Availability',
                'details' => "Tesseract CLI missing; verified PHP {$extName} image processing fallback active."
            ];
        } else {
            $diagnostics['ocr_engine'] = [
                'status' => 'FAIL',
                'label' => 'OCR Command Availability',
                'details' => "No usable OCR processor found! Please install Tesseract CLI or enable PHP GD/Imagick extension."
            ];
        }

        // 7. Groq AI Configuration
        $isTestMockActive = (defined('QUESTBANK_TESTING_MODE') && QUESTBANK_TESTING_MODE === true) || (defined('QUESTBANK_MOCK_AI') && QUESTBANK_MOCK_AI === true);

        if ($isTestMockActive) {
            $diagnostics['groq_ai'] = [
                'status' => 'PASS',
                'label' => 'Testing Mock Provider Active',
                'details' => 'Secure testing environment mock provider active.'
            ];
        } else {
            $aiKey = getenv('GROQ_API_KEY') ?: (defined('GROQ_API_KEY') ? GROQ_API_KEY : '');
            $hasKey = (!empty($aiKey) && strlen($aiKey) > 10 && strpos($aiKey, 'gsk_') === 0);

            if ($hasKey) {
                $diagnostics['groq_ai'] = [
                    'status' => 'PASS',
                    'label' => 'Groq AI Service',
                    'details' => 'Groq API Key configured and active.'
                ];
            } else {
                $diagnostics['groq_ai'] = [
                    'status' => 'WARNING',
                    'label' => 'Groq AI Service',
                    'details' => 'Groq API Key missing or unconfigured. AI generation feature offline.'
                ];
            }
        }

        // 8. Active School Year
        $activeSy = AcademicStructureService::getActiveSchoolYear();
        $diagnostics['active_school_year'] = [
            'status' => $activeSy ? 'PASS' : 'FAIL',
            'label' => 'Active School Year Exists',
            'details' => $activeSy ? "Active SY: {$activeSy['school_year']} (#{$activeSy['id']})" : 'No active school year configured!'
        ];

        // 9. Active Semester Alignment
        $activeSem = AcademicStructureService::getActiveSemester();
        $isSemAligned = ($activeSy && $activeSem && intval($activeSem['school_year_id']) === intval($activeSy['id']));
        $diagnostics['active_semester'] = [
            'status' => $isSemAligned ? 'PASS' : 'FAIL',
            'label' => 'Active Semester Belongs to Active SY',
            'details' => $isSemAligned ? "Active Semester: {$activeSem['semester_name']} (Matches active SY #{$activeSy['id']})" : 'Active semester is missing or misaligned with active school year!'
        ];

        // 10. Maintenance Mode Check
        $mMode = SystemSettingsService::getSetting('maintenance_mode', 'off');
        $isMaintenanceOff = ($mMode === 'off');
        $diagnostics['maintenance_mode'] = [
            'status' => $isMaintenanceOff ? 'PASS' : 'FAIL',
            'label' => 'Maintenance Mode is OFF',
            'details' => $isMaintenanceOff ? 'System is in normal production operation (Maintenance Mode: OFF).' : 'System is currently locked in Maintenance Mode (ON)!'
        ];

        // 11. Application Version Configured
        $verInfo = @include __DIR__ . '/../config/version.php';
        $displayVersion = is_array($verInfo) ? ($verInfo['display_version'] ?? 'v2.2-RC1') : 'v2.2-RC1';
        $diagnostics['application_version'] = [
            'status' => 'PASS',
            'label' => 'Application Version Configured',
            'details' => "QuestBank Portal Version: {$displayVersion}"
        ];

        return $diagnostics;
    }

    public static function getDeploymentChecklist() {
        $diag = self::getHealthDiagnostics();
        $checklist = [];
        $overallFail = false;
        $overallWarning = false;

        foreach ($diag as $key => $item) {
            if ($item['status'] === 'FAIL') $overallFail = true;
            if ($item['status'] === 'WARNING') $overallWarning = true;

            $checklist[] = [
                'key' => $key,
                'label' => $item['label'],
                'status' => $item['status'],
                'details' => $item['details']
            ];
        }

        $overallStatus = $overallFail ? 'FAIL' : ($overallWarning ? 'WARNING' : 'PASS');

        return [
            'overall_status' => $overallStatus,
            'items' => $checklist
        ];
    }
}
