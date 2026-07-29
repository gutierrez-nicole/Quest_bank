<?php

require_once __DIR__ . '/config/config.php';

function getDBConnection() {
    static $pdo = null;

    if ($pdo === null) {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ];
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            error_log("Database Connection Error: " . $e->getMessage());
            if (defined('APP_DEBUG') && APP_DEBUG) {
                die("Database Connection Error: " . $e->getMessage());
            }
            http_response_code(500);
            die("<div style='font-family:sans-serif;text-align:center;padding:50px;'><h2>Service Temporarily Unavailable</h2><p>Unable to connect to the database. Please contact the administrator.</p></div>");
        }
    }

    return $pdo;
}
