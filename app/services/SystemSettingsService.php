<?php

require_once __DIR__ . '/../database.php';

class SystemSettingsService {

    private static $defaults = [
        'school_name' => 'QuestBank University',
        'passing_percentage' => '75.00',
        'ocr_threshold' => '75.00',
        'timezone' => 'Asia/Manila',
        'maintenance_mode' => 'off',
        'ai_generation_defaults' => '{"default_items": 10, "default_type": "multiple_choice"}'
    ];

    public static function getSetting($key, $default = '') {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("SELECT setting_value FROM system_settings WHERE setting_key = ?");
        $stmt->execute([$key]);
        $val = $stmt->fetchColumn();

        if ($val !== false && $val !== null) {
            return $val;
        }

        return self::$defaults[$key] ?? $default;
    }

    public static function setSetting($key, $value) {
        $key = trim($key);
        $value = trim($value);

        if ($key === 'passing_percentage' || $key === 'ocr_threshold') {
            $num = floatval($value);
            if ($num < 0 || $num > 100) {
                throw new InvalidArgumentException("Percentage threshold must be between 0 and 100.");
            }
        }

        if ($key === 'maintenance_mode' && !in_array($value, ['on', 'off'], true)) {
            throw new InvalidArgumentException("Maintenance mode must be 'on' or 'off'.");
        }

        $pdo = getDBConnection();
        $stmt = $pdo->prepare("
            INSERT INTO system_settings (setting_key, setting_value)
            VALUES (?, ?)
            ON DUPLICATE KEY UPDATE setting_value = ?
        ");
        return $stmt->execute([$key, $value, $value]);
    }

    public static function getAllSettings() {
        $pdo = getDBConnection();
        $stmt = $pdo->query("SELECT setting_key, setting_value FROM system_settings");
        $dbSettings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR) ?: [];

        return array_merge(self::$defaults, $dbSettings);
    }
}
