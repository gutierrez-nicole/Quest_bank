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
        return array_sum($means) / count($means);
    }

    public static function submitEvaluation($data) {
        return ISORepository::saveEvaluation($data);
    }
}
