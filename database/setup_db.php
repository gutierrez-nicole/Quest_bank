<?php
/**
 * QuestBank 1-Click Database Setup & Installer
 * Usage: php database/setup_db.php
 */

echo "=====================================================\n";
echo "       QUESTBANK 1-CLICK DATABASE INSTALLER          \n";
echo "=====================================================\n\n";

require_once __DIR__ . '/../app/config/config.php';

$host = getenv('DB_HOST') ?: (defined('DB_HOST') ? DB_HOST : '127.0.0.1');
$port = getenv('DB_PORT') ?: (defined('DB_PORT') ? DB_PORT : 3306);
$dbname = getenv('DB_NAME') ?: (defined('DB_NAME') ? DB_NAME : 'bankquest_db');
$user = getenv('DB_USER') ?: (defined('DB_USER') ? DB_USER : 'root');
$pass = getenv('DB_PASS') !== false ? getenv('DB_PASS') : (defined('DB_PASS') ? DB_PASS : '');

echo "[STEP 1] Connecting to MySQL Server ({$host}:{$port})...\n";

try {
    // Connect without selecting db first
    $rootPdo = new PDO("mysql:host={$host};port={$port};charset=utf8mb4", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
    echo "  [✓] Successfully connected to MySQL Server!\n\n";

    echo "[STEP 2] Creating Database `{$dbname}` if not exists...\n";
    $rootPdo->exec("CREATE DATABASE IF NOT EXISTS `{$dbname}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    echo "  [✓] Database `{$dbname}` is ready!\n\n";

    echo "[STEP 3] Importing Base Schema & Seed Data from `database/bankquest_db.sql`...\n";
    $sqlFile = __DIR__ . '/bankquest_db.sql';
    if (!file_exists($sqlFile)) {
        throw new Exception("SQL file not found at: {$sqlFile}");
    }

    $dbPdo = new PDO("mysql:host={$host};port={$port};dbname={$dbname};charset=utf8mb4", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::MYSQL_ATTR_LOCAL_INFILE => true
    ]);

    $sqlContent = file_get_contents($sqlFile);
    $dbPdo->exec($sqlContent);
    echo "  [✓] Successfully executed `bankquest_db.sql`!\n\n";

    echo "[STEP 4] Running Verification & Incremental Migrations...\n";
    require_once __DIR__ . '/migrate.php';

    echo "\n=====================================================\n";
    echo "       DATABASE SETUP COMPLETED SUCCESSFULLY!        \n";
    echo "=====================================================\n\n";
    echo "Default Login Accounts:\n";
    echo "  [ADMIN]   Username: Russel      | Email: russel@gmail.com        | Pass: Password123!\n";
    echo "  [TEACHER] Username: prof_smith  | Email: smith@questbank.edu.ph  | Pass: Password123!\n";
    echo "  [TEACHER] Username: lasjo       | Email: lasjo@gmail.com         | Pass: Password123!\n";
    echo "  [STUDENT] Username: Nicole      | Email: nikol@gmail.com         | Pass: Password123!\n\n";
    echo "You can now start using QuestBank!\n";
    exit(0);

} catch (PDOException $e) {
    echo "\n[ERROR] MySQL Connection / Execution Failed: " . $e->getMessage() . "\n";
    echo "Please ensure XAMPP/MySQL service is running on {$host}:{$port} with user '{$user}'.\n";
    exit(1);
} catch (Throwable $e) {
    echo "\n[ERROR] Setup Failed: " . $e->getMessage() . "\n";
    exit(1);
}
