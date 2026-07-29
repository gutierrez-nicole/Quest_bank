<?php

require_once __DIR__ . '/../repositories/DepartmentRepository.php';
require_once __DIR__ . '/../repositories/ActivityLogRepository.php';

class DepartmentService {

    public static function getAllDepartments() {
        return DepartmentRepository::getAllDepartments();
    }

    public static function addDepartment($deptCode, $deptName, $programs, $facultyHead, $currentUserId) {
        $res = DepartmentRepository::create($deptCode, $deptName, $programs, $facultyHead);
        if ($res) {
            ActivityLogRepository::log("Created new department '{$deptCode}' - {$deptName}.", $currentUserId);
        }
        return $res;
    }

    public static function updateDepartment($id, $deptCode, $deptName, $programs, $facultyHead, $currentUserId) {
        $res = DepartmentRepository::update($id, $deptCode, $deptName, $programs, $facultyHead);
        if ($res) {
            ActivityLogRepository::log("Updated department ID {$id} ('{$deptCode}').", $currentUserId);
        }
        return $res;
    }

    public static function deleteDepartment($id, $currentUserId) {
        $dept = DepartmentRepository::findById($id);
        $res = DepartmentRepository::delete($id);
        if ($res && $dept) {
            ActivityLogRepository::log("Deleted department '{$dept['dept_code']}'.", $currentUserId);
        }
        return $res;
    }
}
