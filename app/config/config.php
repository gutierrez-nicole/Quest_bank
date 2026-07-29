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

define('DB_HOST', getenv('DB_HOST') ?: '127.0.0.1');
define('DB_NAME', getenv('DB_NAME') ?: 'bankquest_db');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') ?: '');
define('DB_CHARSET', 'utf8mb4');

define('GROQ_API_KEY', getenv('GROQ_API_KEY') ?: '');
define('GROQ_API_ENDPOINT', 'https://api.groq.com/openai/v1/chat/completions');
define('GROQ_DEFAULT_MODEL', 'llama-3.3-70b-versatile');
define('GROQ_FAST_MODEL', 'llama-3.1-8b-instant');

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
