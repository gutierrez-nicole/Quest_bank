<?php
/**
 * QUESTBANK SMOKE SUITE — AUTHENTICATION & ROLE AUTHORIZATION
 */

function test_auth_smoke($pdo) {
    echo "  [TEST] Authentication & Role Authorization...\n";

    // 1. Verify demo user accounts exist and passwords verify correctly
    $users = [
        'admin@questbank.edu.ph' => 'admin',
        'russel@questbank.edu.ph' => 'teacher',
        'smith@questbank.edu.ph' => 'teacher',
        'lasjo@gmail.com' => 'teacher',
        'nikol@gmail.com' => 'student',
        'jmsantos@holycross.edu.ph' => 'student',
        'mreyes@holycross.edu.ph' => 'student'
    ];

    foreach ($users as $email => $expectedRole) {
        $stmt = $pdo->prepare("SELECT id, role, password, force_password_reset FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            throw new Exception("Auth smoke test failed: Missing demo user {$email}");
        }
        if ($user['role'] !== $expectedRole) {
            throw new Exception("Auth smoke test failed: User {$email} role is {$user['role']}, expected {$expectedRole}");
        }
        if (!password_verify('Password123!', $user['password'])) {
            throw new Exception("Auth smoke test failed: Password verification failed for {$email}");
        }
        if ((int)$user['force_password_reset'] !== 1) {
            throw new Exception("Auth smoke test failed: User {$email} does not have force_password_reset = 1");
        }
    }

    echo "    [✓] All 7 demo accounts verified (Role + Password Hash + Reset Flag)\n";
    return true;
}
