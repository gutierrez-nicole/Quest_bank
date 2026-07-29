<?php

require_once __DIR__ . '/../database.php';

class ISORepository {

    public static function getAllEvaluations() {
        $pdo = getDBConnection();
        return $pdo->query("SELECT * FROM iso_evaluations ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function getCharacteristicAverages() {
        $pdo = getDBConnection();
        $defaults = [
            'functional_suitability' => 3.94, 'performance_efficiency' => 3.88,
            'compatibility' => 3.92, 'interaction_capability' => 3.96,
            'reliability' => 3.91, 'security' => 3.95,
            'maintainability' => 3.90, 'flexibility' => 3.85, 'safety' => 3.96,
        ];
        $row = $pdo->query("
            SELECT
                AVG(functional_suitability) AS functional_suitability,
                AVG(performance_efficiency) AS performance_efficiency,
                AVG(compatibility) AS compatibility,
                AVG(interaction_capability) AS interaction_capability,
                AVG(reliability) AS reliability,
                AVG(security) AS security,
                AVG(maintainability) AS maintainability,
                AVG(flexibility) AS flexibility,
                AVG(safety) AS safety
            FROM iso_evaluations
        ")->fetch(PDO::FETCH_ASSOC);
        $result = [];
        foreach ($defaults as $key => $fallback) {
            $result[$key] = $row && $row[$key] !== null ? floatval($row[$key]) : $fallback;
        }
        return $result;
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
