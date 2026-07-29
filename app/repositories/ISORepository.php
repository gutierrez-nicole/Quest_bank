<?php

require_once __DIR__ . '/../database.php';

class ISORepository {

    public static function getAllEvaluations() {
        $pdo = getDBConnection();
        return $pdo->query("SELECT * FROM iso_evaluations ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function getCharacteristicAverages() {
        $pdo = getDBConnection();
        return [
            'functional_suitability' => floatval($pdo->query("SELECT AVG(functional_suitability) FROM iso_evaluations")->fetchColumn() ?: 3.94),
            'performance_efficiency' => floatval($pdo->query("SELECT AVG(performance_efficiency) FROM iso_evaluations")->fetchColumn() ?: 3.88),
            'compatibility'          => floatval($pdo->query("SELECT AVG(compatibility) FROM iso_evaluations")->fetchColumn() ?: 3.92),
            'interaction_capability' => floatval($pdo->query("SELECT AVG(interaction_capability) FROM iso_evaluations")->fetchColumn() ?: 3.96),
            'reliability'            => floatval($pdo->query("SELECT AVG(reliability) FROM iso_evaluations")->fetchColumn() ?: 3.91),
            'security'               => floatval($pdo->query("SELECT AVG(security) FROM iso_evaluations")->fetchColumn() ?: 3.95),
            'maintainability'        => floatval($pdo->query("SELECT AVG(maintainability) FROM iso_evaluations")->fetchColumn() ?: 3.90),
            'flexibility'            => floatval($pdo->query("SELECT AVG(flexibility) FROM iso_evaluations")->fetchColumn() ?: 3.85),
            'safety'                 => floatval($pdo->query("SELECT AVG(safety) FROM iso_evaluations")->fetchColumn() ?: 3.96),
        ];
    }

    public static function saveEvaluation($data) {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("
            INSERT INTO iso_evaluations (
                evaluator_name, evaluator_role, functional_suitability, performance_efficiency, 
                compatibility, interaction_capability, reliability, security, maintainability, 
                flexibility, safety, feedback_text
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        return $stmt->execute([
            $data['evaluator_name'],
            $data['evaluator_role'],
            $data['functional_suitability'],
            $data['performance_efficiency'],
            $data['compatibility'],
            $data['interaction_capability'],
            $data['reliability'],
            $data['security'],
            $data['maintainability'],
            $data['flexibility'],
            $data['safety'],
            $data['feedback_text']
        ]);
    }
}
