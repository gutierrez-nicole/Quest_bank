<?php

require_once __DIR__ . '/../database.php';
require_once __DIR__ . '/AuditLogService.php';
require_once __DIR__ . '/SystemSettingsService.php';

class BackupService {

    public static function getBackupDir() {
        $dir = __DIR__ . '/../../database/backups';
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        $htaccess = $dir . '/.htaccess';
        if (!file_exists($htaccess)) {
            @file_put_contents($htaccess, "Deny from all\n");
        }
        return realpath($dir) ?: $dir;
    }

    public static function isValidBackupFilename(string $filename): bool {
        $base = basename($filename);
        if ($base !== $filename || empty($filename)) {
            return false; // Traversal or path segment attempts rejected
        }
        if (strpos($base, '.') === 0) {
            return false; // Dotfiles (.htaccess, .env) rejected
        }
        if (strtolower(pathinfo($base, PATHINFO_EXTENSION)) !== 'sql') {
            return false; // Non-SQL files rejected
        }
        // Allow ONLY qb_backup_<timestamp>_<random>.sql and qb_safety_backup_<timestamp>_<random>.sql
        if (preg_match('/^qb_(safety_)?backup_\d{4}-\d{2}-\d{2}_\d{6}_[a-f0-9]+\.sql$/i', $base)) {
            return true;
        }
        return false;
    }

    public static function createBackup($actorId = null, $prefix = 'qb_backup_') {
        $pdo = getDBConnection();
        $backupDir = self::getBackupDir();
        
        $dbName = $pdo->query("SELECT DATABASE()")->fetchColumn();
        $filename = $prefix . date('Y-m-d_His') . '_' . bin2hex(random_bytes(3)) . '.sql';
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
            $createTable = $pdo->query("SHOW CREATE TABLE `{$table}`")->fetch(PDO::FETCH_NUM);
            if (!isset($createTable[1])) continue;

            $sqlContent .= "-- --------------------------------------------------------\n";
            $sqlContent .= "-- Table structure for `{$table}`\n";
            $sqlContent .= "-- --------------------------------------------------------\n\n";
            $sqlContent .= "DROP TABLE IF EXISTS `{$table}`;\n";
            $sqlContent .= $createTable[1] . ";\n\n";

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

        $sha256 = hash_file('sha256', $filePath);

        if ($actorId) {
            AuditLogService::logAction($actorId, "Created Database Backup", "File: {$filename}, SHA-256: {$sha256}, Size: " . self::formatBytes(filesize($filePath)));
        }

        return [
            'filename' => $filename,
            'file_path' => $filePath,
            'size' => filesize($filePath),
            'size_formatted' => self::formatBytes(filesize($filePath)),
            'sha256' => $sha256,
            'created_at' => date('Y-m-d H:i:s')
        ];
    }

    public static function listBackups() {
        $backupDir = self::getBackupDir();
        $files = array_merge(
            glob($backupDir . '/qb_backup_*.sql'),
            glob($backupDir . '/qb_safety_backup_*.sql')
        );
        $backups = [];

        foreach ($files as $file) {
            $name = basename($file);
            if (!self::isValidBackupFilename($name)) continue;

            $size = filesize($file);
            $mtime = filemtime($file);
            $tableCount = substr_count(file_get_contents($file), "CREATE TABLE");

            $backups[] = [
                'filename' => $name,
                'file_path' => $file,
                'size' => $size,
                'size_formatted' => self::formatBytes($size),
                'sha256' => hash_file('sha256', $file),
                'table_count' => $tableCount,
                'version' => '2.2-PROD',
                'is_safety' => (strpos($name, 'qb_safety_backup_') === 0),
                'created_at' => date('Y-m-d H:i:s', $mtime),
                'timestamp' => $mtime
            ];
        }

        usort($backups, function($a, $b) {
            return $b['timestamp'] - $a['timestamp'];
        });

        return $backups;
    }

    public static function restoreBackup($filename, $actorId, $confirmationPhrase = 'RESTORE') {
        if (!self::isValidBackupFilename($filename)) {
            throw new InvalidArgumentException("Invalid or untrusted backup filename '{$filename}'. Operations permitted on valid QuestBank SQL backups only.");
        }

        $backupDir = self::getBackupDir();
        $safeName = basename($filename);
        $filePath = $backupDir . '/' . $safeName;
        $realPath = realpath($filePath);

        if (!$realPath || strpos($realPath, $backupDir) !== 0 || !file_exists($realPath) || !is_readable($realPath)) {
            throw new InvalidArgumentException("Backup file '{$safeName}' not found or path traversal detected.");
        }

        if ($confirmationPhrase !== 'RESTORE') {
            throw new InvalidArgumentException("Restore confirmation failed. Please type the explicit phrase 'RESTORE'.");
        }

        $headerChunk = file_get_contents($realPath, false, null, 0, 1024);
        if (strpos($headerChunk, "-- QuestBank Database Dump") === false && strpos($headerChunk, "CREATE TABLE") === false) {
            throw new InvalidArgumentException("Backup file integrity validation failed: File is not a recognized QuestBank SQL backup.");
        }

        $sql = file_get_contents($realPath);
        if (empty($sql)) {
            throw new InvalidArgumentException("Backup file is empty.");
        }

        $sourceSha256 = hash_file('sha256', $realPath);

        // 1. Create a fresh Safety Backup of current DB before attempting restore
        $safetyBackup = self::createBackup($actorId, 'qb_safety_backup_');
        $safetyName = $safetyBackup['filename'];

        // 2. Prevent concurrent restore executions using a lock file
        $lockFile = sys_get_temp_dir() . '/qb_restore.lock';
        if (file_exists($lockFile) && (time() - filemtime($lockFile)) < 300) {
            throw new Exception("Restore in progress by another request. Please wait.");
        }
        touch($lockFile);

        // 3. Put system into temporary maintenance mode
        $prevMMode = SystemSettingsService::getSetting('maintenance_mode', 'off');
        SystemSettingsService::setSetting('maintenance_mode', 'on');

        $pdo = getDBConnection();
        $pdo->exec("SET FOREIGN_KEY_CHECKS=0");

        try {
            $pdo->exec($sql);

            SystemSettingsService::setSetting('maintenance_mode', $prevMMode);
            AuditLogService::logAction($actorId, "Restored Database Backup", "Source File: {$safeName}, SHA-256: {$sourceSha256}, Safety Backup: {$safetyName}");
            return [
                'status' => 'success',
                'source_file' => $safeName,
                'source_sha256' => $sourceSha256,
                'safety_backup' => $safetyName
            ];
        } catch (Exception $e) {
            AuditLogService::logAction($actorId, "Database Restore Failed", "Source File: {$safeName}, Error: " . $e->getMessage() . ", Safety Backup Available: {$safetyName}");
            throw new Exception("Database restore failed: " . $e->getMessage() . ". Your data before restore was preserved in safety backup: '{$safetyName}'. You can restore from '{$safetyName}' to recover.");
        } finally {
            $pdo->exec("SET FOREIGN_KEY_CHECKS=1");
            @unlink($lockFile);
        }
    }

    public static function deleteBackup($filename, $actorId) {
        if (!self::isValidBackupFilename($filename)) {
            return false;
        }

        $backupDir = self::getBackupDir();
        $safeName = basename($filename);
        $filePath = $backupDir . '/' . $safeName;
        $realPath = realpath($filePath);

        if ($realPath && strpos($realPath, $backupDir) === 0 && file_exists($realPath)) {
            @unlink($realPath);
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
