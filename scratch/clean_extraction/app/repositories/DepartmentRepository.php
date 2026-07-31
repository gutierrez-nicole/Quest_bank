<?php

require_once __DIR__ . '/../database.php';

class DepartmentRepository {

    public static function getAllDepartments() {
        $pdo = getDBConnection();
        return $pdo->query("SELECT * FROM departments ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function findById($id) {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("SELECT * FROM departments WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public static function create($deptCode, $deptName, $programs, $facultyHead) {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("INSERT INTO departments (dept_code, dept_name, programs, faculty_head) VALUES (?, ?, ?, ?)");
        return $stmt->execute([$deptCode, $deptName, $programs, $facultyHead]);
    }

    public static function update($id, $deptCode, $deptName, $programs, $facultyHead) {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("UPDATE departments SET dept_code = ?, dept_name = ?, programs = ?, faculty_head = ? WHERE id = ?");
        return $stmt->execute([$deptCode, $deptName, $programs, $facultyHead, $id]);
    }

    public static function delete($id) {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("DELETE FROM departments WHERE id = ?");
        return $stmt->execute([$id]);
    }
}
