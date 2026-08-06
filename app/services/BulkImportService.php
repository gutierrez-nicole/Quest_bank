<?php

require_once __DIR__ . '/../database.php';
require_once __DIR__ . '/AuditLogService.php';
require_once __DIR__ . '/AcademicStructureService.php';

class BulkImportService {

    public static function processCSV($fileTmpPath, $type, $isExecution = false, $actorId = null) {
        $allowedTypes = ['students', 'teachers', 'sections', 'subjects'];
        if (!in_array($type, $allowedTypes, true)) {
            throw new InvalidArgumentException("Unsupported import type '{$type}'.");
        }

        if (!file_exists($fileTmpPath) || !is_readable($fileTmpPath)) {
            throw new InvalidArgumentException("CSV file is invalid or unreadable.");
        }

        if (filesize($fileTmpPath) > 5 * 1024 * 1024) {
            throw new InvalidArgumentException("CSV file size exceeds maximum allowed limit (5MB).");
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

        // In-memory duplicate trackers for current CSV file
        $seenEmails = [];
        $seenUsernames = [];
        $seenStudentNumbers = [];
        $seenSectionCodes = [];
        $seenSubjectTitles = [];
        $seenSubjectCodes = [];

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
                    $email = strtolower($data['email'] ?? '');
                    $course = $data['course'] ?? 'BSCE';
                    $section = strtoupper($data['section'] ?? 'A');

                    if (empty($num) || empty($name) || empty($email)) {
                        $rowError = "Row {$rowIndex}: Missing required fields (student_number, fullname, email).";
                    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                        $rowError = "Row {$rowIndex}: Invalid email format '{$email}'.";
                    } elseif (in_array($email, $seenEmails, true)) {
                        $rowError = "Row {$rowIndex}: Duplicate email '{$email}' within the uploaded CSV.";
                    } elseif (in_array($num, $seenStudentNumbers, true)) {
                        $rowError = "Row {$rowIndex}: Duplicate student number '{$num}' within the uploaded CSV.";
                    } else {
                        // DB duplicate check
                        $stmtE = $pdo->prepare("SELECT id FROM users WHERE email = ?");
                        $stmtE->execute([$email]);
                        if ($stmtE->fetchColumn()) {
                            $rowError = "Row {$rowIndex}: Duplicate email '{$email}' already exists in database.";
                        }

                        $stmtN = $pdo->prepare("SELECT user_id FROM student_details WHERE student_number = ?");
                        $stmtN->execute([$num]);
                        if ($stmtN->fetchColumn()) {
                            $rowError = "Row {$rowIndex}: Duplicate student number '{$num}' already exists in database.";
                        }
                    }

                    if ($rowError) {
                        $invalidRows[] = ['row' => $rowIndex, 'data' => $data, 'error' => $rowError];
                        $errors[] = $rowError;
                    } else {
                        $seenEmails[] = $email;
                        $seenStudentNumbers[] = $num;
                        $baseUser = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', explode('@', $email)[0]));
                        $username = self::generateUniqueUsername($pdo, $baseUser, $seenUsernames);
                        $tempPass = self::generateTempPassword();

                        $validRows[] = [
                            'student_number' => $num,
                            'fullname' => $name,
                            'email' => $email,
                            'username' => $username,
                            'temp_password' => $tempPass,
                            'course' => $course,
                            'section' => $section
                        ];
                    }
                    break;

                case 'teachers':
                    $name = $data['fullname'] ?? $data['name'] ?? '';
                    $email = strtolower($data['email'] ?? '');
                    $inputUsername = strtolower($data['username'] ?? '');

                    if (empty($name) || empty($email)) {
                        $rowError = "Row {$rowIndex}: Missing required fields (fullname, email).";
                    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                        $rowError = "Row {$rowIndex}: Invalid email format '{$email}'.";
                    } elseif (in_array($email, $seenEmails, true)) {
                        $rowError = "Row {$rowIndex}: Duplicate email '{$email}' within the uploaded CSV.";
                    } else {
                        $stmtE = $pdo->prepare("SELECT id FROM users WHERE email = ?");
                        $stmtE->execute([$email]);
                        if ($stmtE->fetchColumn()) {
                            $rowError = "Row {$rowIndex}: Duplicate email '{$email}' already exists in database.";
                        }
                    }

                    if ($rowError) {
                        $invalidRows[] = ['row' => $rowIndex, 'data' => $data, 'error' => $rowError];
                        $errors[] = $rowError;
                    } else {
                        $seenEmails[] = $email;
                        $baseUser = $inputUsername ?: strtolower(preg_replace('/[^a-zA-Z0-9]/', '', explode('@', $email)[0]));
                        $username = self::generateUniqueUsername($pdo, $baseUser, $seenUsernames);
                        $tempPass = self::generateTempPassword();

                        $validRows[] = [
                            'fullname' => $name,
                            'email' => $email,
                            'username' => $username,
                            'temp_password' => $tempPass
                        ];
                    }
                    break;

                case 'sections':
                    $code = strtoupper($data['section_code'] ?? $data['section'] ?? '');
                    $course = trim($data['course'] ?? $data['program'] ?? 'BSCE');
                    $adviserId = !empty($data['adviser_id']) ? intval($data['adviser_id']) : null;
                    $capacity = intval($data['capacity'] ?? 40);

                    if (empty($code)) {
                        $rowError = "Row {$rowIndex}: Missing required field (section_code).";
                    } elseif ($capacity <= 0) {
                        $rowError = "Row {$rowIndex}: Section capacity must be greater than 0.";
                    } elseif (in_array($code, $seenSectionCodes, true)) {
                        $rowError = "Row {$rowIndex}: Duplicate section code '{$code}' within the uploaded CSV.";
                    } else {
                        $stmtC = $pdo->prepare("SELECT id FROM sections WHERE section_code = ? OR section_name = ?");
                        $stmtC->execute([$code, $code]);
                        if ($stmtC->fetchColumn()) {
                            $rowError = "Row {$rowIndex}: Duplicate section code '{$code}' already exists in database.";
                        }

                        if ($adviserId) {
                            $stmtAdv = $pdo->prepare("SELECT role, status FROM users WHERE id = ?");
                            $stmtAdv->execute([$adviserId]);
                            $usr = $stmtAdv->fetch(PDO::FETCH_ASSOC);
                            if (!$usr || $usr['role'] !== 'teacher' || $usr['status'] !== 'active') {
                                $rowError = "Row {$rowIndex}: Section adviser ID #{$adviserId} is not an active teacher.";
                            }
                        }
                    }

                    if ($rowError) {
                        $invalidRows[] = ['row' => $rowIndex, 'data' => $data, 'error' => $rowError];
                        $errors[] = $rowError;
                    } else {
                        $seenSectionCodes[] = $code;
                        $validRows[] = [
                            'section_code' => $code,
                            'course' => $course,
                            'adviser_id' => $adviserId,
                            'capacity' => $capacity
                        ];
                    }
                    break;

                case 'subjects':
                    $title = trim($data['title'] ?? $data['subject'] ?? $data['subject_title'] ?? '');
                    $code = strtoupper(trim($data['code'] ?? $data['subject_code'] ?? ''));

                    if (empty($code) && !empty($title)) {
                        // Generate subject code if missing
                        $words = explode(' ', preg_replace('/[^a-zA-Z0-9 ]/', '', $title));
                        $initials = '';
                        foreach ($words as $w) {
                            if (!empty($w)) $initials .= strtoupper($w[0]);
                        }
                        $code = 'SUBJ-' . (substr($initials, 0, 4) ?: 'GEN') . '-' . rand(100, 999);
                    }

                    if (empty($title)) {
                        $rowError = "Row {$rowIndex}: Missing required field (subject title).";
                    } elseif (in_array(strtolower($title), $seenSubjectTitles, true)) {
                        $rowError = "Row {$rowIndex}: Duplicate subject title '{$title}' within the uploaded CSV.";
                    } elseif (in_array(strtolower($code), $seenSubjectCodes, true)) {
                        $rowError = "Row {$rowIndex}: Duplicate subject code '{$code}' within the uploaded CSV.";
                    } else {
                        $stmtSub = $pdo->prepare("SELECT id FROM subjects WHERE LOWER(title) = ? OR LOWER(code) = ?");
                        $stmtSub->execute([strtolower($title), strtolower($code)]);
                        if ($stmtSub->fetchColumn()) {
                            $rowError = "Row {$rowIndex}: Subject '{$title}' (Code: {$code}) already exists in database.";
                        }
                    }

                    if ($rowError) {
                        $invalidRows[] = ['row' => $rowIndex, 'data' => $data, 'error' => $rowError];
                        $errors[] = $rowError;
                    } else {
                        $seenSubjectTitles[] = strtolower($title);
                        $seenSubjectCodes[] = strtolower($code);
                        $validRows[] = [
                            'code' => $code,
                            'title' => $title
                        ];
                    }
                    break;
            }
        }

        fclose($handle);

        $importedCount = 0;
        $credentials = [];

        // If execution requested and there are valid rows, insert them in a single transaction!
        if ($isExecution && !empty($validRows)) {
            $pdo->beginTransaction();
            try {
                foreach ($validRows as $vr) {
                    if ($type === 'students') {
                        $passHash = password_hash($vr['temp_password'], PASSWORD_DEFAULT);
                        $stmtU = $pdo->prepare("INSERT INTO users (username, fullname, email, password, role, status) VALUES (?, ?, ?, ?, 'student', 'active')");
                        $stmtU->execute([$vr['username'], $vr['fullname'], $vr['email'], $passHash]);
                        $uId = $pdo->lastInsertId();

                        $stmtS = $pdo->prepare("INSERT INTO student_details (user_id, student_number, course, section) VALUES (?, ?, ?, ?)");
                        $stmtS->execute([$uId, $vr['student_number'], $vr['course'], $vr['section']]);
                        $importedCount++;

                        $credentials[] = [
                            'username' => $vr['username'],
                            'fullname' => $vr['fullname'],
                            'email' => $vr['email'],
                            'temp_password' => $vr['temp_password'],
                            'role' => 'student'
                        ];
                    } elseif ($type === 'teachers') {
                        $passHash = password_hash($vr['temp_password'], PASSWORD_DEFAULT);
                        $stmtU = $pdo->prepare("INSERT INTO users (username, fullname, email, password, role, status) VALUES (?, ?, ?, ?, 'teacher', 'active')");
                        $stmtU->execute([$vr['username'], $vr['fullname'], $vr['email'], $passHash]);
                        $importedCount++;

                        $credentials[] = [
                            'username' => $vr['username'],
                            'fullname' => $vr['fullname'],
                            'email' => $vr['email'],
                            'temp_password' => $vr['temp_password'],
                            'role' => 'teacher'
                        ];
                    } elseif ($type === 'sections') {
                        AcademicStructureService::createSection($vr['section_code'], $vr['course'], $vr['adviser_id'], $vr['capacity']);
                        $importedCount++;
                    } elseif ($type === 'subjects') {
                        $stmtInsSub = $pdo->prepare("INSERT INTO subjects (code, title, created_at) VALUES (?, ?, NOW())");
                        $stmtInsSub->execute([$vr['code'], $vr['title']]);
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
            'credentials' => $credentials,
            'errors' => $errors
        ];
    }

    private static function generateUniqueUsername($pdo, $baseUsername, &$seenUsernames) {
        $base = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $baseUsername));
        if (empty($base)) $base = 'user';

        $counter = 1;
        while (true) {
            $candidate = $base . ($counter > 1 ? $counter : '');
            if (!in_array($candidate, $seenUsernames, true)) {
                $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
                $stmt->execute([$candidate]);
                if (!$stmt->fetchColumn()) {
                    $seenUsernames[] = $candidate;
                    return $candidate;
                }
            }
            $counter++;
        }
    }

    private static function generateTempPassword() {
        return substr(bin2hex(random_bytes(6)), 0, 10);
    }
}
