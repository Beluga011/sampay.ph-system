<?php
// Sampay Configuration - Open-Meteo API (Free, No Key Required)

// Application Settings
define('APP_NAME', 'Sampay');
define('APP_URL', 'http://localhost/sampay'); // Change to your domain
define('APP_EMAIL', 'noreply@yoursite.com');

// File paths
define('DATA_DIR', __DIR__ . '/../data/');

// Create required directories
if (!file_exists(DATA_DIR)) {
    mkdir(DATA_DIR, 0755, true);
}

// Open-Meteo API Configuration (Free, No API Key Required)
define('OPENMETEO_URL', 'https://api.open-meteo.com/v1/forecast');

// CORS headers
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

// Error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);
?>