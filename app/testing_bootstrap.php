<?php
/**
 * QuestBank Testing Environment Bootstrap
 * 
 * Loaded strictly under testing mode when invoked by the dedicated test runner/server.
 * Ensures mock AI provider behavior cannot be enabled in production, staging, or development.
 */

if (!class_exists('GroqService')) {
    require_once __DIR__ . '/services/GroqService.php';
}

$env = getenv('APP_ENV') ?: ($_ENV['APP_ENV'] ?? ($_SERVER['APP_ENV'] ?? ''));
$currentEnv = (!empty($env)) ? $env : (defined('APP_ENV') ? APP_ENV : 'production');

$isBootstrapActive = (getenv('TEST_BOOTSTRAP_ACTIVE') === '1') 
    || (defined('TEST_BOOTSTRAP_ACTIVE') && TEST_BOOTSTRAP_ACTIVE === true)
    || (isset($_SERVER['TEST_BOOTSTRAP_ACTIVE']) && $_SERVER['TEST_BOOTSTRAP_ACTIVE'] === '1');

if ($currentEnv === 'testing' && $isBootstrapActive) {
    GroqService::$testMode = true;
    GroqService::$testBootstrapActive = true;
} else {
    GroqService::$testMode = false;
    GroqService::$testBootstrapActive = false;
}
