<?php

require_once __DIR__ . '/../database.php';

class ExamService {

    public static function getExamsByTeacher($teacherId) {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("SELECT * FROM exams WHERE teacher_id = ? ORDER BY id DESC");
        $stmt->execute([$teacherId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function createExam($teacherId, $title, $subject, $specialization, $timeLimit, $questions, $qualifyingOptions = []) {
        $pdo = getDBConnection();
        try {
            $pdo->beginTransaction();

            $category = $qualifyingOptions['exam_category'] ?? 'regular';
            $passingPct = floatval($qualifyingOptions['qualifying_passing_percentage'] ?? 75.00);
            $maxAttempts = intval($qualifyingOptions['qualifying_max_attempts'] ?? 1);
            $yearLevel = $qualifyingOptions['qualifying_year_level'] ?? 'All Year Levels';
            $program = $qualifyingOptions['qualifying_program'] ?? 'All Programs';
            $isRequired = isset($qualifyingOptions['qualifying_is_required']) ? intval($qualifyingOptions['qualifying_is_required']) : 1;
            $unlockDate = !empty($qualifyingOptions['qualifying_unlock_date']) ? $qualifyingOptions['qualifying_unlock_date'] : null;
            $deadline = !empty($qualifyingOptions['qualifying_deadline']) ? $qualifyingOptions['qualifying_deadline'] : null;

            $stmt = $pdo->prepare("
                INSERT INTO exams (
                    teacher_id, title, subject, specialization, time_limit, total_items,
                    exam_category, qualifying_passing_percentage, qualifying_max_attempts,
                    qualifying_year_level, qualifying_program, qualifying_is_required,
                    qualifying_unlock_date, qualifying_deadline, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([
                $teacherId, $title, $subject, $specialization, $timeLimit, count($questions),
                $category, $passingPct, $maxAttempts, $yearLevel, $program, $isRequired,
                $unlockDate, $deadline
            ]);
            $examId = $pdo->lastInsertId();

            $qStmt = $pdo->prepare("INSERT INTO exam_questions (exam_id, question_text, question_type, option_a, option_b, option_c, option_d, correct_answer, points) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            foreach ($questions as $q) {
                $qStmt->execute([
                    $examId,
                    $q['text'] ?? ($q['question_text'] ?? ''),
                    $q['type'] ?? ($q['question_type'] ?? 'multiple_choice'),
                    $q['opt_a'] ?? ($q['option_a'] ?? null),
                    $q['opt_b'] ?? ($q['option_b'] ?? null),
                    $q['opt_c'] ?? ($q['option_c'] ?? null),
                    $q['opt_d'] ?? ($q['option_d'] ?? null),
                    $q['correct'] ?? ($q['correct_answer'] ?? ''),
                    intval($q['points'] ?? 1)
                ]);
            }

            $pdo->commit();
            return ['success' => true, 'exam_id' => $examId];
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            return ['error' => $e->getMessage()];
        }
    }

    public static function checkStudentEligibility($studentId, $examId) {
        $pdo = getDBConnection();
        
        $stmtEx = $pdo->prepare("SELECT * FROM exams WHERE id = ?");
        $stmtEx->execute([$examId]);
        $exam = $stmtEx->fetch(PDO::FETCH_ASSOC);

        if (!$exam) {
            return ['eligible' => false, 'reason' => 'Exam not found.'];
        }

        if (($exam['status'] ?? 'active') !== 'active') {
            return ['eligible' => false, 'reason' => 'This examination is currently inactive.'];
        }

        
        $stmtAtt = $pdo->prepare("SELECT COUNT(*) FROM exam_submissions WHERE student_id = ? AND exam_id = ?");
        $stmtAtt->execute([$studentId, $examId]);
        $attemptCount = intval($stmtAtt->fetchColumn());

        
        $category = $exam['exam_category'] ?? 'regular';
        if ($category === 'qualifying') {
            $maxAttempts = intval($exam['qualifying_max_attempts'] ?? 1);
            if ($attemptCount >= $maxAttempts) {
                return [
                    'eligible' => false,
                    'reason' => "You have reached the maximum allowed attempts ({$maxAttempts}) for this qualifying examination.",
                    'attempt_count' => $attemptCount,
                    'max_attempts' => $maxAttempts,
                    'remaining_attempts' => 0
                ];
            }

            
            $stmtDetails = $pdo->prepare("SELECT course, year_level FROM student_details WHERE user_id = ?");
            $stmtDetails->execute([$studentId]);
            $details = $stmtDetails->fetch(PDO::FETCH_ASSOC) ?: ['course' => 'BSCE', 'year_level' => '4th Year'];

            $eligibleProg = $exam['qualifying_program'] ?? 'All Programs';
            if ($eligibleProg !== 'All Programs' && $eligibleProg !== 'all') {
                if (stripos($details['course'], $eligibleProg) === false && stripos($eligibleProg, $details['course']) === false) {
                    return [
                        'eligible' => false,
                        'reason' => "This qualifying examination is restricted to {$eligibleProg} students."
                    ];
                }
            }

            $eligibleYear = $exam['qualifying_year_level'] ?? 'All Year Levels';
            if ($eligibleYear !== 'All Year Levels' && $eligibleYear !== 'all') {
                
                $stuYearStr = (string)$details['year_level'];
                if (stripos($stuYearStr, (string)$eligibleYear) === false && stripos((string)$eligibleYear, $stuYearStr) === false) {
                    return [
                        'eligible' => false,
                        'reason' => "This qualifying examination is restricted to {$eligibleYear} students."
                    ];
                }
            }

            
            if (!empty($exam['qualifying_unlock_date'])) {
                if (time() < strtotime($exam['qualifying_unlock_date'])) {
                    return [
                        'eligible' => false,
                        'reason' => "This qualifying examination unlocks on " . date('M d, Y h:i A', strtotime($exam['qualifying_unlock_date']))
                    ];
                }
            }

            
            if (!empty($exam['qualifying_deadline'])) {
                if (time() > strtotime($exam['qualifying_deadline'])) {
                    return [
                        'eligible' => false,
                        'reason' => "The deadline for this qualifying examination passed on " . date('M d, Y h:i A', strtotime($exam['qualifying_deadline']))
                    ];
                }
            }

            $remaining = max(0, $maxAttempts - $attemptCount);
            return [
                'eligible' => true,
                'attempt_count' => $attemptCount,
                'max_attempts' => $maxAttempts,
                'remaining_attempts' => $remaining
            ];
        }

        return ['eligible' => true, 'attempt_count' => $attemptCount, 'remaining_attempts' => 999];
    }

    public static function getEligibleStudentsForExam($pdo, $examId, $teacherId) {
        $stmtEx = $pdo->prepare("SELECT * FROM exams WHERE id = ? AND (teacher_id = ? OR created_by = ?)");
        $stmtEx->execute([$examId, $teacherId, $teacherId]);
        $exam = $stmtEx->fetch(PDO::FETCH_ASSOC);
        if (!$exam) {
            return [];
        }

        $examCategory = $exam['exam_category'] ?? 'regular';
        $qProgram = $exam['qualifying_program'] ?? 'All Programs';
        $qYearLevel = $exam['qualifying_year_level'] ?? 'All Year Levels';
        $examSec = $exam['section'] ?? null;

        $sql = "
            SELECT DISTINCT u.id, u.fullname, u.email, sd.student_number, sd.section, sd.course, sd.year_level
            FROM users u
            JOIN student_details sd ON u.id = sd.user_id
            WHERE u.role = 'student' AND (
                u.id IN (SELECT student_id FROM exam_assignments WHERE exam_id = ? AND student_id IS NOT NULL)
                OR sd.section COLLATE utf8mb4_general_ci IN (SELECT s.section_name COLLATE utf8mb4_general_ci FROM sections s JOIN exam_assignments ea ON ea.section_id = s.id WHERE ea.exam_id = ?)
                OR sd.section COLLATE utf8mb4_general_ci IN (SELECT s.section_code COLLATE utf8mb4_general_ci FROM sections s JOIN exam_assignments ea ON ea.section_id = s.id WHERE ea.exam_id = ?)
                OR sd.section COLLATE utf8mb4_general_ci IN (SELECT section COLLATE utf8mb4_general_ci FROM exam_schedules WHERE exam_id = ?)
                OR (? IS NOT NULL AND ? != '' AND LOWER(sd.section COLLATE utf8mb4_general_ci) = LOWER(? COLLATE utf8mb4_general_ci))
                OR (? = 'qualifying' AND 
                    (? = 'All Programs' OR LOWER(sd.course COLLATE utf8mb4_general_ci) = LOWER(? COLLATE utf8mb4_general_ci)) AND 
                    (? = 'All Year Levels' OR LOWER(sd.year_level COLLATE utf8mb4_general_ci) = LOWER(? COLLATE utf8mb4_general_ci))
                )
                OR (
                    NOT EXISTS (SELECT 1 FROM exam_assignments WHERE exam_id = ?) AND
                    NOT EXISTS (SELECT 1 FROM exam_schedules WHERE exam_id = ?) AND
                    (? IS NULL OR ? = '') AND
                    ? != 'qualifying'
                )
            )
            ORDER BY u.fullname ASC
        ";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $examId,
            $examId,
            $examId,
            $examId,
            $examSec, $examSec, $examSec,
            $examCategory,
            $qProgram, $qProgram,
            $qYearLevel, $qYearLevel,
            $examId,
            $examId,
            $examSec, $examSec,
            $examCategory
        ]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function getRecentSubmissions($limit = 10) {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("SELECT * FROM exam_submissions ORDER BY id DESC LIMIT " . intval($limit));
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
