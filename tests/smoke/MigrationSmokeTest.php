<?php
/**
 * QUESTBANK SMOKE SUITE — DATABASE MIGRATION INTEGRITY & PASSWORD SAFETY
 */

function test_migration_smoke($pdo) {
    echo "  [TEST] Database Migration Integrity & Password Safety...\n";

    // 1. Fetch current passwords
    $passBefore = $pdo->query("SELECT email, password FROM users")->fetchAll(PDO::FETCH_KEY_PAIR);

    // 2. Execute migration runner
    $out = [];
    exec("php " . escapeshellarg(__DIR__ . '/../../database/migrate.php') . " 2>&1", $out, $code);

    if ($code !== 0) {
        throw new Exception("Migration smoke test failed: migrate.php exited with code {$code}");
    }

    // 3. Verify passwords remain unchanged
    $passAfter = $pdo->query("SELECT email, password FROM users")->fetchAll(PDO::FETCH_KEY_PAIR);

    if ($passBefore !== $passAfter) {
        throw new Exception("Migration smoke test failed: Existing user passwords were altered by migration!");
    }

    echo "    [✓] Database migration smoke test passed (0 password modifications across migrations)\n";
    return true;
}
