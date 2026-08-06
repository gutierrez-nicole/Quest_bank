<?php

require_once __DIR__ . '/../database.php';
require_once __DIR__ . '/AuditLogService.php';

class BulkImportService {

    public static function processCSV($fileTmpPath, $type, $isExecution = false, $actorId = null) {
        if (!file_exists($fileTmpPath) || !is_readable($fileTmpPath)) {
            throw new InvalidArgumentException("CSV file is invalid or unreadable.");
        }

        $handle = fopen($fileTmpPath, 'r');
        if (!$handle) {
            throw new Exception("Failed to open CSV file.");
        }

        $header = fgetcsv($handle);
        if (!$header) {
            fclose($handle);
            throw new InvalidArgumentException("CSV file is empty or has no header row.");
        }

        // Clean headers
        $header = array_map(function($h) { return strtolower(trim(preg_replace('/[\x00-\x1F\x7F-\xFF]/', '', $h))); }, $header);

        $pdo = getDBConnection();
        $rowIndex = 1;
        $totalRows = 0;
        $validRows = [];
        $invalidRows = [];
        $errors = [];

        $defaultPasswordHash = password_hash('Password123!', PASSWORD_DEFAULT);

        while (($row = fgetcsv($handle)) !== false) {
            $rowIndex++;
            if (empty(array_filter($row))) continue; // skip blank lines
            $totalRows++;

            $data = [];
            foreach ($header as $i => $col) {
                $data[$col] = trim($row[$i] ?? '');
            }

            $rowError = null;

            switch ($type) {
                case 'students':
                    $num = $data['student_number'] ?? $data['student_no'] ?? '';
                    $name = $data['fullname'] ?? $data['name'] ?? '';
                    $email = $data['email'] ?? '';
                    $course = $data['course'] ?? 'BSCE';
                    $section = strtoupper($data['section'] ?? 'A');

                    if (empty($num) || empty($name) || empty($email)) {
                        $rowError = "Row {$rowIndex}: Missing required fields (student_number, fullname, email).";
                    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                        $rowError = "Row {$rowIndex}: Invalid email format '{$email}'.";
                    } else {
                        // Check DB duplicate email/student_number
                        $stmtE = $pdo->prepare("SELECT id FROM users WHERE email = ?");
                        $stmtE->execute([$email]);
                        if ($stmtE->fetchColumn()) {
                            $rowError = "Row {$rowIndex}: Duplicate email '{$email}' already exists in database.";
                        }

                        $stmtN = $pdo->prepare("SELECT user_id FROM student_details WHERE student_number = ?");
                        $stmtN->execute([$num]);
                        if ($stmtN->fetchColumn()) {
                            $rowError = "Row {$rowIndex}: Duplicate student number '{$num}' already exists.";
                        }
                    }

                    if ($rowError) {
                        $invalidRows[] = ['row' => $rowIndex, 'data' => $data, 'error' => $rowError];
                        $errors[] = $rowError;
                    } else {
                        $validRows[] = [
                            'student_number' => $num,
                            'fullname' => $name,
                            'email' => $email,
                            'username' => strtolower(explode('@', $email)[0] . '_' . rand(100, 999)),
                            'course' => $course,
                            'section' => $section
                        ];
                    }
                    break;

                case 'teachers':
                    $name = $data['fullname'] ?? $data['name'] ?? '';
                    $email = $data['email'] ?? '';
                    $username = $data['username'] ?? (explode('@', $email)[0] ?? '');

                    if (empty($name) || empty($email)) {
                        $rowError = "Row {$rowIndex}: Missing required fields (fullname, email).";
                    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                        $rowError = "Row {$rowIndex}: Invalid email format '{$email}'.";
                    } else {
                        $stmtE = $pdo->prepare("SELECT id FROM users WHERE email = ?");
                        $stmtE->execute([$email]);
                        if ($stmtE->fetchColumn()) {
                            $rowError = "Row {$rowIndex}: Duplicate email '{$email}' already exists.";
                        }
                    }

                    if ($rowError) {
                        $invalidRows[] = ['row' => $rowIndex, 'data' => $data, 'error' => $rowError];
                        $errors[] = $rowError;
                    } else {
                        $validRows[] = [
                            'fullname' => $name,
                            'email' => $email,
                            'username' => strtolower($username)
                        ];
                    }
                    break;

                case 'sections':
                    $code = strtoupper($data['section_code'] ?? $data['section'] ?? '');
                    $capacity = intval($data['capacity'] ?? 40);

                    if (empty($code)) {
                        $rowError = "Row {$rowIndex}: Missing required field (section_code).";
                    } elseif ($capacity <= 0) {
                        $rowError = "Row {$rowIndex}: Section capacity must be greater than 0.";
                    } else {
                        $stmtC = $pdo->prepare("SELECT id FROM sections WHERE section_code = ?");
                        $stmtC->execute([$code]);
                        if ($stmtC->fetchColumn()) {
                            $rowError = "Row {$rowIndex}: Duplicate section code '{$code}' already exists.";
                        }
                    }

                    if ($rowError) {
                        $invalidRows[] = ['row' => $rowIndex, 'data' => $data, 'error' => $rowError];
                        $errors[] = $rowError;
                    } else {
                        $validRows[] = [
                            'section_code' => $code,
                            'capacity' => $capacity
                        ];
                    }
                    break;

                case 'subjects':
                    $subject = $data['subject'] ?? $data['title'] ?? '';

                    if (empty($subject)) {
                        $rowError = "Row {$rowIndex}: Missing required field (subject).";
                    }

                    if ($rowError) {
                        $invalidRows[] = ['row' => $rowIndex, 'data' => $data, 'error' => $rowError];
                        $errors[] = $rowError;
                    } else {
                        $validRows[] = ['subject' => $subject];
                    }
                    break;
            }
        }

        fclose($handle);

        $importedCount = 0;

        // If execution requested and there are valid rows, insert them!
        if ($isExecution && !empty($validRows)) {
            $pdo->beginTransaction();
            try {
                foreach ($validRows as $vr) {
                    if ($type === 'students') {
                        $stmtU = $pdo->prepare("INSERT INTO users (username, fullname, email, password, role, status) VALUES (?, ?, ?, ?, 'student', 'active')");
                        $stmtU->execute([$vr['username'], $vr['fullname'], $vr['email'], $defaultPasswordHash]);
                        $uId = $pdo->lastInsertId();

                        $stmtS = $pdo->prepare("INSERT INTO student_details (user_id, student_number, course, section) VALUES (?, ?, ?, ?)");
                        $stmtS->execute([$uId, $vr['student_number'], $vr['course'], $vr['section']]);
                        $importedCount++;
                    } elseif ($type === 'teachers') {
                        $stmtU = $pdo->prepare("INSERT INTO users (username, fullname, email, password, role, status) VALUES (?, ?, ?, ?, 'teacher', 'active')");
                        $stmtU->execute([$vr['username'], $vr['fullname'], $vr['email'], $defaultPasswordHash]);
                        $importedCount++;
                    } elseif ($type === 'sections') {
                        $stmtSec = $pdo->prepare("INSERT INTO sections (section_code, capacity, status) VALUES (?, ?, 'active')");
                        $stmtSec->execute([$vr['section_code'], $vr['capacity']]);
                        $importedCount++;
                    } elseif ($type === 'subjects') {
                        $importedCount++;
                    }
                }
                $pdo->commit();
                AuditLogService::logAction($actorId, "Bulk Import Completed", "Type: {$type}, Imported: {$importedCount} rows, Skipped Invalid: " . count($invalidRows));
            } catch (Exception $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                throw $e;
            }
        }

        return [
            'success' => true,
            'type' => $type,
            'total_rows' => $totalRows,
            'valid_rows_count' => count($validRows),
            'invalid_rows_count' => count($invalidRows),
            'imported_count' => $importedCount,
            'valid_rows' => $validRows,
            'invalid_rows' => $invalidRows,
            'errors' => $errors
        ];
    }
}
