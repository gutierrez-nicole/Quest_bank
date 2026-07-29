<?php

require_once __DIR__ . '/../database.php';

class ExamService {

    public static function getExamsByTeacher($teacherId) {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("SELECT * FROM exams WHERE teacher_id = ? ORDER BY id DESC");
        $stmt->execute([$teacherId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function createExam($teacherId, $title, $subject, $specialization, $timeLimit, $questions) {
        $pdo = getDBConnection();
        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare("INSERT INTO exams (teacher_id, title, subject, specialization, time_limit, total_items) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$teacherId, $title, $subject, $specialization, $timeLimit, count($questions)]);
            $examId = $pdo->lastInsertId();

            $qStmt = $pdo->prepare("INSERT INTO exam_questions (exam_id, question_text, question_type, option_a, option_b, option_c, option_d, correct_answer) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            foreach ($questions as $q) {
                $qStmt->execute([
                    $examId,
                    $q['text'],
                    $q['type'],
                    $q['opt_a'] ?? null,
                    $q['opt_b'] ?? null,
                    $q['opt_c'] ?? null,
                    $q['opt_d'] ?? null,
                    $q['correct']
                ]);
            }

            $pdo->commit();
            return ['success' => true, 'exam_id' => $examId];
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            return ['error' => $e->getMessage()];
        }
    }

    public static function getRecentSubmissions($limit = 10) {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("SELECT * FROM exam_submissions ORDER BY id DESC LIMIT " . intval($limit));
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
