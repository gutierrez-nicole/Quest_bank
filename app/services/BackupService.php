<?php

require_once __DIR__ . '/../database.php';
require_once __DIR__ . '/AuditLogService.php';

class BackupService {

    private static function getBackupDir() {
        $dir = __DIR__ . '/../../database/backups';
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        @file_put_contents($dir . '/.htaccess', "Deny from all\n");
        return realpath($dir) ?: $dir;
    }

    public static function createBackup($actorId = null) {
        $pdo = getDBConnection();
        $backupDir = self::getBackupDir();
        
        $dbName = $pdo->query("SELECT DATABASE()")->fetchColumn();
        $filename = 'qb_backup_' . date('Y-m-d_His') . '_' . bin2hex(random_bytes(3)) . '.sql';
        $filePath = $backupDir . '/' . $filename;

        $sqlHeader = "-- QuestBank Database Dump & Backup\n";
        $sqlHeader .= "-- Generated: " . date('Y-m-d H:i:s') . "\n";
        $sqlHeader .= "-- Database: {$dbName}\n";
        $sqlHeader .= "-- Application Version: 2.2-PROD\n\n";
        $sqlHeader .= "SET FOREIGN_KEY_CHECKS=0;\n";
        $sqlHeader .= "SET SQL_MODE = \"NO_AUTO_VALUE_ON_ZERO\";\n";
        $sqlHeader .= "START TRANSACTION;\n\n";

        $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
        $sqlContent = $sqlHeader;

        foreach ($tables as $table) {
            // Skip views or temporary tables
            $createTable = $pdo->query("SHOW CREATE TABLE `{$table}`")->fetch(PDO::FETCH_NUM);
            if (!isset($createTable[1])) continue;

            $sqlContent .= "-- --------------------------------------------------------\n";
            $sqlContent .= "-- Table structure for `{$table}`\n";
            $sqlContent .= "-- --------------------------------------------------------\n\n";
            $sqlContent .= "DROP TABLE IF EXISTS `{$table}`;\n";
            $sqlContent .= $createTable[1] . ";\n\n";

            // Export table data
            $rows = $pdo->query("SELECT * FROM `{$table}`")->fetchAll(PDO::FETCH_ASSOC);
            if (!empty($rows)) {
                $sqlContent .= "-- Dumping data for `{$table}`\n\n";
                $columns = array_keys($rows[0]);
                $colList = implode('`, `', $columns);

                foreach (array_chunk($rows, 100) as $chunk) {
                    $insertSql = "INSERT INTO `{$table}` (`{$colList}`) VALUES \n";
                    $valueRows = [];
                    foreach ($chunk as $row) {
                        $vals = [];
                        foreach ($row as $val) {
                            if ($val === null) {
                                $vals[] = "NULL";
                            } else {
                                $vals[] = $pdo->quote($val);
                            }
                        }
                        $valueRows[] = "(" . implode(', ', $vals) . ")";
                    }
                    $insertSql .= implode(",\n", $valueRows) . ";\n";
                    $sqlContent .= $insertSql;
                }
                $sqlContent .= "\n";
            }
        }

        $sqlContent .= "SET FOREIGN_KEY_CHECKS=1;\n";
        $sqlContent .= "COMMIT;\n";

        if (file_put_contents($filePath, $sqlContent) === false) {
            throw new Exception("Failed to write backup file to disk.");
        }

        if ($actorId) {
            AuditLogService::logAction($actorId, "Created Database Backup", "File: {$filename}, Size: " . self::formatBytes(filesize($filePath)));
        }

        return [
            'filename' => $filename,
            'file_path' => $filePath,
            'size' => filesize($filePath),
            'size_formatted' => self::formatBytes(filesize($filePath)),
            'created_at' => date('Y-m-d H:i:s')
        ];
    }

    public static function listBackups() {
        $backupDir = self::getBackupDir();
        $files = glob($backupDir . '/qb_backup_*.sql');
        $backups = [];

        foreach ($files as $file) {
            $name = basename($file);
            $size = filesize($file);
            $mtime = filemtime($file);

            $backups[] = [
                'filename' => $name,
                'file_path' => $file,
                'size' => $size,
                'size_formatted' => self::formatBytes($size),
                'created_at' => date('Y-m-d H:i:s', $mtime),
                'timestamp' => $mtime
            ];
        }

        usort($backups, function($a, $b) {
            return $b['timestamp'] - $a['timestamp'];
        });

        return $backups;
    }

    public static function restoreBackup($filename, $actorId) {
        $backupDir = self::getBackupDir();
        $safeName = basename($filename);
        $filePath = $backupDir . '/' . $safeName;

        if (!file_exists($filePath) || !is_readable($filePath)) {
            throw new InvalidArgumentException("Backup file '{$safeName}' not found or unreadable.");
        }

        // Validate file integrity (must contain QuestBank header and valid SQL structure)
        $headerChunk = file_get_contents($filePath, false, null, 0, 1024);
        if (strpos($headerChunk, "-- QuestBank Database Dump") === false && strpos($headerChunk, "CREATE TABLE") === false) {
            throw new InvalidArgumentException("Backup file integrity validation failed: Not a recognized QuestBank SQL backup.");
        }

        $sql = file_get_contents($filePath);
        if (empty($sql)) {
            throw new InvalidArgumentException("Backup file is empty.");
        }

        $pdo = getDBConnection();
        $pdo->exec("SET FOREIGN_KEY_CHECKS=0");

        try {
            $pdo->exec($sql);
            $pdo->exec("SET FOREIGN_KEY_CHECKS=1");

            AuditLogService::logAction($actorId, "Restored Database Backup", "Restored from file: {$safeName}");
            return true;
        } catch (Exception $e) {
            $pdo->exec("SET FOREIGN_KEY_CHECKS=1");
            throw new Exception("Database restore failed: " . $e->getMessage());
        }
    }

    public static function deleteBackup($filename, $actorId) {
        $backupDir = self::getBackupDir();
        $safeName = basename($filename);
        $filePath = $backupDir . '/' . $safeName;

        if (file_exists($filePath)) {
            @unlink($filePath);
            AuditLogService::logAction($actorId, "Deleted Database Backup", "Deleted backup: {$safeName}");
            return true;
        }
        return false;
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
