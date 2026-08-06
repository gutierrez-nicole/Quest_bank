<?php

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/session.php';
require_once __DIR__ . '/../includes/security.php';

require_once __DIR__ . '/services/AuthService.php';
require_once __DIR__ . '/services/AuthorizationService.php';

require_once __DIR__ . '/services/GroqService.php';
require_once __DIR__ . '/services/ISOService.php';
require_once __DIR__ . '/services/StudentService.php';
require_once __DIR__ . '/services/ExamService.php';
require_once __DIR__ . '/services/ExamScoringService.php';
require_once __DIR__ . '/services/LessonExtractionService.php';
require_once __DIR__ . '/services/OcrService.php';
require_once __DIR__ . '/services/ResultWorkflowService.php';
require_once __DIR__ . '/services/SystemSettingsService.php';
require_once __DIR__ . '/testing_bootstrap.php';

// Enforce Maintenance Mode for Web Requests
if (PHP_SAPI !== 'cli') {
    try {
        $mMode = SystemSettingsService::getSetting('maintenance_mode', 'off');
        if ($mMode === 'on') {
            $isAdmin = (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin') || (isset($_SESSION['role']) && $_SESSION['role'] === 'admin');
            if (!$isAdmin) {
                $scriptName = basename($_SERVER['SCRIPT_NAME'] ?? '');
                $allowedScripts = ['login.php', 'logout.php', 'maintenance.php'];
                if (!in_array($scriptName, $allowedScripts, true)) {
                    header('Location: /maintenance.php');
                    exit;
                }
            }
        }
    } catch (Exception $e) {
        // Fallback gracefully if database is uninitialized
    }
}

