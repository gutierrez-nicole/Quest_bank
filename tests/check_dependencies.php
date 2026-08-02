<?php

function checkSystemDependencies() {
    echo "=== QuestBank System & PHP Dependency Checker ===\n\n";

    $requiredExtensions = ['pdo', 'pdo_mysql', 'mbstring', 'fileinfo', 'zip'];
    $optionalExtensions = ['gd', 'xml'];
    $missingRequired = [];

    foreach ($requiredExtensions as $ext) {
        if (extension_loaded($ext)) {
            echo "   [OK] Extension loaded: {$ext}\n";
        } else {
            echo "   [FAIL] Missing required PHP extension: {$ext}\n";
            $missingRequired[] = $ext;
        }
    }

    foreach ($optionalExtensions as $ext) {
        if (extension_loaded($ext)) {
            echo "   [OK] Optional extension loaded: {$ext}\n";
        } else {
            echo "   [WARN] Optional extension missing: {$ext} (Manual image fallback enabled)\n";
        }
    }

    echo "\n--- CLI Binaries Check ---\n";
    $binaries = ['tesseract', 'pdftoppm'];
    foreach ($binaries as $bin) {
        $path = exec("which {$bin} 2>/dev/null");
        if (!empty($path) && is_executable($path)) {
            echo "   [OK] Binary found: {$bin} ({$path})\n";
        } else {
            echo "   [WARN] Binary missing: {$bin} (Manual review fallback enabled)\n";
        }
    }

    echo "\n-----------------------------------------\n";
    if (empty($missingRequired)) {
        echo "ALL REQUIRED PHP EXTENSIONS ARE PRESENT.\n";
        return true;
    } else {
        echo "DEPENDENCY CHECK FAILED: Please install missing extensions: " . implode(', ', $missingRequired) . "\n";
        return false;
    }
}

if (basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'] ?? '')) {
    $ok = checkSystemDependencies();
    exit($ok ? 0 : 1);
}
