<?php

/**
 * Shared Test Dependency Preflight Helper for QuestBank CLI Verification Scripts
 */

if (!function_exists('requirePhpExtensions')) {
    function requirePhpExtensions(array $extensions): void {
        $missing = [];
        foreach ($extensions as $ext) {
            if (!extension_loaded($ext)) {
                $missing[] = $ext;
            }
        }

        if (!empty($missing)) {
            fwrite(STDERR, "\n===========================================================\n");
            fwrite(STDERR, " DEPENDENCY PREFLIGHT FAILED: MISSING PHP EXTENSION(S)    \n");
            fwrite(STDERR, "===========================================================\n");
            foreach ($missing as $ext) {
                fwrite(STDERR, "  [REQUIRED] PHP Extension '{$ext}' is NOT loaded.\n");
            }
            fwrite(STDERR, "\nResolution: Please install/enable the required PHP extension(s) in php.ini.\n");
            fwrite(STDERR, "Exiting cleanly with exit code 1 to prevent unhandled exit code 255.\n\n");
            exit(1);
        }
    }
}

if (!function_exists('requireCommands')) {
    function requireCommands(array $commands): void {
        $missing = [];
        foreach ($commands as $cmd) {
            $which = (PHP_OS_FAMILY === 'Windows') ? "where {$cmd}" : "which {$cmd}";
            $output = shell_exec("{$which} 2>&1");
            if (empty($output) || strpos($output, 'not found') !== false || strpos($output, 'no ') !== false) {
                $missing[] = $cmd;
            }
        }

        if (!empty($missing)) {
            fwrite(STDERR, "\n===========================================================\n");
            fwrite(STDERR, " DEPENDENCY PREFLIGHT FAILED: MISSING EXTERNAL COMMAND(S)  \n");
            fwrite(STDERR, "===========================================================\n");
            foreach ($missing as $cmd) {
                fwrite(STDERR, "  [REQUIRED] Command '{$cmd}' is NOT available in PATH.\n");
            }
            fwrite(STDERR, "\nExiting cleanly with exit code 1.\n\n");
            exit(1);
        }
    }
}

if (!function_exists('runPreflightChecks')) {
    function runPreflightChecks(array $extensions = [], array $commands = []): void {
        $defaultExtensions = ['pdo', 'pdo_mysql', 'mbstring', 'curl', 'json', 'fileinfo', 'zip', 'xml'];
        $requiredExtensions = array_unique(array_merge($defaultExtensions, $extensions));
        requirePhpExtensions($requiredExtensions);
        if (!empty($commands)) {
            requireCommands($commands);
        }
    }
}
