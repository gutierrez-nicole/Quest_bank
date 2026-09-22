<?php
require_once __DIR__ . '/../database.php';

/** Shared definitions for student results, filters and analytics. */
class StudentResultService {
    public const MASTERY_THRESHOLD = 85.0; // Existing dashboard mastery threshold.

    public static function publishedSql(string $alias = 'es'): string {
        if (!preg_match('/^[a-z_]+$/i', $alias)) throw new InvalidArgumentException('Invalid SQL alias.');
        // Publication is an explicit workflow transition; finalized alone is private.
        return "$alias.review_status = 'published' AND $alias.percentage IS NOT NULL AND $alias.total_score IS NOT NULL"
            . " AND COALESCE($alias.suggested_manual_review, 0) = 0"
            . " AND NOT EXISTS (SELECT 1 FROM submission_answers pending_item WHERE pending_item.submission_id = $alias.id AND (pending_item.requires_review = 1 OR pending_item.evaluation_status = 'requires_review'))";
    }

    public static function normalizeTerm(?string $term): ?string {
        return ['prelim'=>'Prelim', 'preliminary'=>'Prelim', 'midterm'=>'Midterm', 'midterms'=>'Midterm', 'final'=>'Finals', 'finals'=>'Finals'][strtolower(trim($term ?? ''))] ?? null;
    }

    public static function termSql(string $alias = 'e'): string {
        if (!preg_match('/^[a-z_]+$/i', $alias)) throw new InvalidArgumentException('Invalid SQL alias.');
        return "CASE LOWER(TRIM($alias.term)) WHEN 'prelim' THEN 'Prelim' WHEN 'preliminary' THEN 'Prelim' WHEN 'midterm' THEN 'Midterm' WHEN 'midterms' THEN 'Midterm' WHEN 'final' THEN 'Finals' WHEN 'finals' THEN 'Finals' ELSE NULL END";
    }

    public static function results(int $studentId, ?string $term = null, ?string $subject = null): array {
        $where = 'es.student_id = ? AND '.self::publishedSql();
        $params = [$studentId];
        $termExpr = self::termSql();
        if ($term !== null && !in_array(strtolower(trim($term)), ['', 'all', 'all terms'], true)) {
            $where .= " AND $termExpr = ?";
            $params[] = self::normalizeTerm($term) ?? '__unknown__';
        }
        if ($subject !== null && strtolower($subject) !== 'all') {
            $where .= ' AND COALESCE(e.subject, es.exam_title) = ?';
            $params[] = $subject;
        }
        $stmt = getDBConnection()->prepare("SELECT es.*, COALESCE(e.title, es.exam_title) AS title,
            COALESCE(e.subject, es.exam_title) AS subject, $termExpr AS term,
            es.total_score AS score, COALESCE(es.total_possible_score, es.total_items) AS total_items
            FROM exam_submissions es LEFT JOIN exams e ON e.id = es.exam_id
            WHERE $where ORDER BY es.created_at DESC, es.id DESC");
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public static function subjectPerformance(int $studentId): array {
        $where = self::publishedSql();
        $stmt = getDBConnection()->prepare("SELECT COALESCE(NULLIF(e.subject,''), es.exam_title) AS subject,
            COUNT(*) AS exams_completed, AVG(es.percentage) AS avg_score,
            MAX(es.percentage) AS highest_score, MIN(es.percentage) AS lowest_score,
            AVG(CASE WHEN es.status = 'Pass' THEN 100 ELSE 0 END) AS pass_rate
            FROM exam_submissions es LEFT JOIN exams e ON e.id = es.exam_id
            WHERE es.student_id = ? AND $where
            GROUP BY COALESCE(NULLIF(e.subject,''), es.exam_title) ORDER BY avg_score DESC, subject");
        $stmt->execute([$studentId]);
        return $stmt->fetchAll();
    }
}
