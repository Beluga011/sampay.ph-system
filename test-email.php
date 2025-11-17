<?php
// test-email.php - Fixed version with correct paths
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Define the base path - FIXED: Remove the extra /sampay/backend/
define('BASE_DIR', __DIR__ . '/backend/');

// Include the config file with correct path
require_once BASE_DIR . 'config.php';

// Include the daily-updates functions
require_once BASE_DIR . 'daily-updates.php';

echo "Testing Sampay Email System...\n";
echo "================================\n";

// Test email sending
$testUser = [
    'name' => 'Test User',
    'email' => 'alfredozboajr.pvgma@gmail.com',
    'location' => 'London'
];

echo "Testing with location: " . $testUser['location'] . "\n";

// Get weather data
$weatherData = getWeatherData($testUser['location']);
if (!$weatherData) {
    die("❌ Failed to get weather data\n");
}

echo "✅ Weather data retrieved:\n";
echo "   Temperature: " . $weatherData['temperature'] . "°C\n";
echo "   Humidity: " . $weatherData['humidity'] . "%\n";
echo "   Condition: " . $weatherData['condition'] . "\n";

// Get drying recommendation
$recommendation = getDryingRecommendation($weatherData);
echo "   Drying Recommendation: " . $recommendation . "\n\n";

// Test email sending
echo "Sending test email to: " . $testUser['email'] . "\n";
$result = sendDailyEmail($testUser['email'], $testUser['name'], $testUser['location'], $weatherData, $recommendation);

if ($result['success']) {
    echo "✅ Test email sent successfully!\n";
    echo "   Method used: " . ($result['method'] ?? 'php_mail') . "\n";
} else {
    echo "❌ Email failed: " . $result['error'] . "\n";
    echo "   Method used: " . ($result['method'] ?? 'unknown') . "\n";
    
    // Additional troubleshooting info
    echo "\nTroubleshooting:\n";
    echo "1. Check if PHP mail() function is enabled\n";
    echo "2. Verify email address format\n";
    echo "3. Check server mail logs\n";
}
?>