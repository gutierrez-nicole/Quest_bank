<?php

require_once __DIR__ . '/config/config.php';

function getDBConnection() {
    static $pdo = null;

    if ($pdo === null) {
        $host = getenv('DB_HOST') ?: (defined('DB_HOST') ? DB_HOST : '127.0.0.1');
        $dbname = getenv('DB_NAME') ?: (defined('DB_NAME') ? DB_NAME : 'bankquest_db');
        $user = getenv('DB_USER') ?: (defined('DB_USER') ? DB_USER : 'root');
        $pass = getenv('DB_PASS') !== false ? getenv('DB_PASS') : (defined('DB_PASS') ? DB_PASS : '');
        $charset = defined('DB_CHARSET') ? DB_CHARSET : 'utf8mb4';

        try {
            $dsn = "mysql:host={$host};dbname={$dbname};charset={$charset}";
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ];
            $pdo = new PDO($dsn, $user, $pass, $options);
        } catch (PDOException $e) {
            error_log("Database Connection Error: " . $e->getMessage());

            if (PHP_SAPI === 'cli') {
                throw new RuntimeException('Unable to connect to the database.', 1, $e);
            }

            if (!headers_sent()) {
                http_response_code(500);
            }
            die("<div style='font-family:sans-serif;text-align:center;padding:50px;'><h2>Service Temporarily Unavailable</h2><p>Unable to connect to the database. Please contact the administrator.</p></div>");
        }
    }

    return $pdo;
}

$pdo = getDBConnection();
