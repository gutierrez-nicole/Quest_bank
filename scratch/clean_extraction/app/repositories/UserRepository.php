<?php

require_once __DIR__ . '/../database.php';

class UserRepository {

    public static function findById($id) {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("SELECT id, username, fullname, email, role, specialization, created_at FROM users WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public static function findByUsername($username) {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
        $stmt->execute([$username]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public static function getAllUsers() {
        $pdo = getDBConnection();
        return $pdo->query("SELECT id, username, fullname, email, role, specialization, created_at FROM users ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function getUsersByRole($role) {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("SELECT id, username, fullname, email, specialization, created_at FROM users WHERE role = ? ORDER BY id DESC");
        $stmt->execute([$role]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function createUser($username, $passwordHash, $fullname, $email, $role, $specialization = null) {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("INSERT INTO users (username, password, fullname, email, role, specialization) VALUES (?, ?, ?, ?, ?, ?)");
        return $stmt->execute([$username, $passwordHash, $fullname, $email, $role, $specialization]);
    }

    public static function updateUser($id, $fullname, $email, $specialization = null) {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("UPDATE users SET fullname = ?, email = ?, specialization = ? WHERE id = ?");
        return $stmt->execute([$fullname, $email, $specialization, $id]);
    }

    public static function updatePassword($id, $newPasswordHash) {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
        return $stmt->execute([$newPasswordHash, $id]);
    }

    public static function deleteUser($id) {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
        return $stmt->execute([$id]);
    }
}
