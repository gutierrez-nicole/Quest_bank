<?php

if (!defined('BASE_PATH')) {
    define('BASE_PATH', dirname(__DIR__, 2));
}

$envFile = BASE_PATH . '/.env';
if (file_exists($envFile)) {
    $envVars = parse_ini_file($envFile);
    if (is_array($envVars)) {
        foreach ($envVars as $key => $value) {
            if (!getenv($key)) {
                putenv("{$key}={$value}");
                $_ENV[$key] = $value;
            }
        }
    }
}

define('APP_ENV', getenv('APP_ENV') ?: 'production');
define('APP_DEBUG', getenv('APP_DEBUG') === 'true' || getenv('APP_DEBUG') === '1');

if (APP_ENV === 'production' && !APP_DEBUG) {
    ini_set('display_errors', 0);
    ini_set('display_startup_errors', 0);
    error_reporting(E_ALL & ~E_DEPRECATED);
} else {
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);
}

ini_set('log_errors', 1);
ini_set('error_log', BASE_PATH . '/app.log');

define('DB_HOST', getenv('DB_HOST') ?: '127.0.0.1');
define('DB_NAME', getenv('DB_NAME') ?: 'bankquest_db');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') ?: '');
define('DB_CHARSET', 'utf8mb4');

define('GROQ_API_KEY', getenv('GROQ_API_KEY') ?: '');
define('GROQ_API_ENDPOINT', 'https://api.groq.com/openai/v1/chat/completions');
define('GROQ_DEFAULT_MODEL', getenv('GROQ_DEFAULT_MODEL') ?: 'openai/gpt-oss-120b');
define('GROQ_FAST_MODEL', getenv('GROQ_FAST_MODEL') ?: 'openai/gpt-oss-20b');

define('AI_MAX_CONTEXT_TOKENS', 32000);
define('AI_SAFE_INPUT_TOKENS', 24000);
define('AI_CHUNK_SIZE', 12000);
define('AI_MAX_SELECTED_LESSONS', 20);

define('APP_NAME', 'QuestBank');
define('APP_INSTITUTION', 'Holy Cross College - Pampanga');

function getCivilEngineeringSpecializations() {
    return [
        'Structural Engineering' => '🏗️ Structural Engineering (Beams, Columns, Steel & Reinforced Concrete Design)',
        'Geotechnical Engineering' => '🧪 Geotechnical Engineering (Soil Mechanics, Foundations & Earth Structures)',
        'Construction Engineering & Management' => '🚧 Construction Engineering & Management (Project Planning, Estimating & Site Control)',
        'Environmental Engineering' => '🌿 Environmental Engineering (Water Resources, Wastewater & Hydrology)',
        'Transportation Engineering' => '🛣️ Transportation Engineering (Pavements, Highway Design & Traffic Flow)'
    ];
}
