<?php
require_once 'config.php';

header('Content-Type: application/json');

$health = [
    'status' => 'healthy',
    'timestamp' => date('c'),
    'services' => []
];

// Check file system
if (is_writable(DATA_DIR)) {
    $health['services']['filesystem'] = 'healthy';
} else {
    $health['services']['filesystem'] = 'unhealthy';
    $health['status'] = 'degraded';
}

// Check Open-Meteo API
$testResponse = @file_get_contents('https://api.open-meteo.com/v1/forecast?latitude=52.52&longitude=13.41&current=temperature_2m');
if ($testResponse !== false) {
    $health['services']['openmeteo_api'] = 'healthy';
} else {
    $health['services']['openmeteo_api'] = 'unhealthy';
    $health['status'] = 'degraded';
}

// Check subscriptions file
if (file_exists(DATA_DIR . 'subscriptions_backup.json')) {
    $health['services']['subscriptions'] = 'healthy';
} else {
    $health['services']['subscriptions'] = 'not_found';
}

echo json_encode($health, JSON_PRETTY_PRINT);
?>