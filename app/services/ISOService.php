<?php

require_once __DIR__ . '/../repositories/ISORepository.php';
require_once __DIR__ . '/../../includes/security.php';

class ISOService {

    public static function getAllEvaluations() {
        return ISORepository::getAllEvaluations();
    }

    public static function getCharacteristicMeans() {
        return ISORepository::getCharacteristicAverages();
    }

    public static function getOverallWeightedMean() {
        $means = self::getCharacteristicMeans();
        $filtered = array_filter($means, function($v) { return $v > 0; });
        if (empty($filtered)) {
            return 0.0;
        }
        return round(array_sum($filtered) / count($filtered), 2);
    }

    public static function submitEvaluation($data) {
        return ISORepository::saveEvaluation($data);
    }
}
