<?php
// Sampay Daily Weather Updates - Complete Version with PHPMailer
// Run via cron job: 0 8 * * * /usr/bin/php /path/to/daily-updates.php

// Load PHPMailer and email templates
require_once 'phpmailer-composer.php';
require_once 'email-templates.php';

// Configuration
define('APP_NAME', 'Sampay');
define('APP_URL', 'http://localhost/sampay');
define('APP_EMAIL', 'noreply@yoursite.com');
define('DATA_DIR', __DIR__ . '/../data/');

// Create data directory if needed
if (!file_exists(DATA_DIR)) {
    mkdir(DATA_DIR, 0755, true);
}

// Don't display errors in cron
ini_set('display_errors', 0);
ini_set('log_errors', 1);

/**
 * Main function to send daily updates
 */
function sendDailyUpdates() {
    $log = [];
    $log[] = "[" . date('Y-m-d H:i:s') . "] Starting daily weather updates";
    
    // Load subscriptions
    $subscriptions = loadSubscriptions();
    $log[] = "Found " . count($subscriptions) . " subscriptions";
    
    if (empty($subscriptions)) {
        $log[] = "No subscriptions found. Exiting.";
        saveDailyLog($log, 0, 0, 0);
        return ['emails_sent' => 0, 'errors' => 0, 'log' => $log];
    }
    
    $emailCount = 0;
    $errors = [];
    
    foreach ($subscriptions as $subscription) {
        try {
            $email = $subscription['email'] ?? '';
            $name = $subscription['name'] ?? 'User';
            $location = $subscription['location'] ?? '';
            
            if (empty($email) || empty($location)) {
                $errors[] = "Invalid subscription data for: " . ($email ?: 'unknown');
                continue;
            }
            
            // Check if user wants email notifications
            $notifications = $subscription['notifications'] ?? [];
            if (!in_array('email', $notifications)) {
                $log[] = "Skipping $email - email notifications disabled";
                continue;
            }
            
            // Get weather data for user's location
            $weatherData = getWeatherData($location);
            if (!$weatherData) {
                $errors[] = "Failed to get weather data for: $location ($email)";
                continue;
            }
            
            // Determine drying recommendation
            $recommendation = getDryingRecommendation($weatherData);
            
            // Send email notification
            $emailResult = sendDailyEmail($email, $name, $location, $weatherData, $recommendation);
            
            if ($emailResult['success']) {
                $emailCount++;
                $log[] = "✅ Email sent to: $email";
                
                // Log successful email
                logEmailActivity($email, 'daily_update', true, $emailResult['method']);
            } else {
                $errorMsg = "Email failed for $email: " . ($emailResult['error'] ?? 'Unknown error');
                $errors[] = $errorMsg;
                $log[] = "❌ $errorMsg";
                
                // Log failed email
                logEmailActivity($email, 'daily_update', false, $emailResult['method']);
            }
            
            // Small delay to avoid rate limits
            usleep(500000); // 0.5 seconds
            
        } catch (Exception $e) {
            $errorMsg = "Error processing " . ($subscription['email'] ?? 'unknown') . ": " . $e->getMessage();
            $errors[] = $errorMsg;
            $log[] = "❌ $errorMsg";
        }
    }
    
    // Summary
    $log[] = "=== DAILY UPDATE SUMMARY ===";
    $log[] = "Total subscriptions: " . count($subscriptions);
    $log[] = "Emails sent: " . $emailCount;
    $log[] = "Errors: " . count($errors);
    
    if (!empty($errors)) {
        $log[] = "ERROR DETAILS:";
        foreach ($errors as $error) {
            $log[] = "  - $error";
        }
    }
    
    $log[] = "[" . date('Y-m-d H:i:s') . "] Daily updates completed";
    
    // Save log
    saveDailyLog($log, $emailCount, count($errors), count($subscriptions));
    
    return [
        'emails_sent' => $emailCount,
        'total_subscriptions' => count($subscriptions),
        'errors' => count($errors),
        'log' => $log
    ];
}

/**
 * Load subscriptions from file
 */
function loadSubscriptions() {
    $filename = DATA_DIR . 'subscriptions.json';
    
    if (!file_exists($filename)) {
        return [];
    }
    
    $content = file_get_contents($filename);
    $subscriptions = json_decode($content, true) ?: [];
    
    // Filter only subscriptions with email and location
    return array_filter($subscriptions, function($sub) {
        return !empty($sub['email']) && !empty($sub['location']);
    });
}

/**
 * Get weather data using Open-Meteo API with fallback
 */
function getWeatherData($location) {
    $coords = getCoordinates($location);
    if (!$coords) {
        return generateSimulatedWeather($location);
    }
    
    $url = "https://api.open-meteo.com/v1/forecast?" .
           "latitude={$coords['lat']}&longitude={$coords['lon']}" .
           "&current=temperature_2m,relative_humidity_2m,precipitation,wind_speed_10m,weather_code" .
           "&daily=temperature_2m_max,temperature_2m_min,precipitation_sum,weather_code" .
           "&timezone=auto";
    
    $context = stream_context_create([
        'http' => [
            'timeout' => 10,
            'header' => "User-Agent: Sampay Weather App/1.0\r\n"
        ]
    ]);
    
    $response = @file_get_contents($url, false, $context);
    if (!$response) {
        return generateSimulatedWeather($location);
    }
    
    $data = json_decode($response, true);
    if (!isset($data['current'])) {
        return generateSimulatedWeather($location);
    }
    
    $weatherCode = $data['current']['weather_code'] ?? 0;
    
    return [
        'temperature' => round($data['current']['temperature_2m']),
        'humidity' => $data['current']['relative_humidity_2m'],
        'precipitation' => $data['current']['precipitation'],
        'wind' => round($data['current']['wind_speed_10m'] * 3.6), // Convert to km/h
        'condition' => getWeatherCondition($weatherCode),
        'icon' => getWeatherIcon($weatherCode),
        'city' => $coords['city'] ?? $location,
        'daily_high' => round($data['daily']['temperature_2m_max'][0] ?? $data['current']['temperature_2m']),
        'daily_low' => round($data['daily']['temperature_2m_min'][0] ?? $data['current']['temperature_2m']),
        'daily_precip' => $data['daily']['precipitation_sum'][0] ?? 0,
        'weather_code' => $weatherCode
    ];
}

/**
 * Send daily email using PHPMailer
 */
function sendDailyEmail($email, $name, $location, $weatherData, $recommendation) {
    try {
        $template = EmailTemplates::dailyUpdateEmail($name, $location, $weatherData, $recommendation);
        return sendSampayEmail($email, $template['subject'], $template['html'], $template['text']);
    } catch (Exception $e) {
        error_log("Daily email error for $email: " . $e->getMessage());
        return [
            'success' => false,
            'method' => 'error',
            'error' => $e->getMessage()
        ];
    }
}

/**
 * Get coordinates from location name
 */
function getCoordinates($location) {
    if (is_array($location)) {
        return $location;
    }
    
    // Use free Nominatim geocoding
    $url = "https://nominatim.openstreetmap.org/search?format=json&q=" . urlencode($location) . "&limit=1";
    
    $context = stream_context_create([
        'http' => [
            'header' => "User-Agent: Sampay Weather App/1.0\r\n",
            'timeout' => 5
        ]
    ]);
    
    $response = @file_get_contents($url, false, $context);
    if (!$response) {
        return null;
    }
    
    $data = json_decode($response, true);
    if (empty($data)) {
        return null;
    }
    
    // Extract city name
    $displayParts = explode(',', $data[0]['display_name']);
    $cityName = trim($displayParts[0]);
    
    return [
        'lat' => round($data[0]['lat'], 4),
        'lon' => round($data[0]['lon'], 4),
        'city' => $cityName
    ];
}

/**
 * Map weather codes to conditions
 */
function getWeatherCondition($code) {
    $conditions = [
        0 => 'Clear sky', 1 => 'Mainly clear', 2 => 'Partly cloudy', 3 => 'Overcast',
        45 => 'Fog', 48 => 'Fog', 51 => 'Light drizzle', 53 => 'Moderate drizzle',
        61 => 'Slight rain', 63 => 'Moderate rain', 65 => 'Heavy rain',
        80 => 'Rain showers', 81 => 'Heavy showers', 95 => 'Thunderstorm'
    ];
    return $conditions[$code] ?? 'Unknown';
}

/**
 * Map weather codes to icons
 */
function getWeatherIcon($code) {
    $icons = [
        0 => 'sun', 1 => 'cloud-sun', 2 => 'cloud-sun', 3 => 'cloud',
        45 => 'smog', 48 => 'smog', 51 => 'cloud-drizzle', 53 => 'cloud-drizzle',
        61 => 'cloud-rain', 63 => 'cloud-rain', 65 => 'cloud-rain',
        80 => 'cloud-rain', 81 => 'cloud-rain', 95 => 'bolt'
    ];
    return $icons[$code] ?? 'question';
}

/**
 * Determine drying recommendation based on weather conditions
 */
function getDryingRecommendation($weatherData) {
    $temp = $weatherData['temperature'];
    $humidity = $weatherData['humidity'];
    $precip = $weatherData['precipitation'];
    $wind = $weatherData['wind'];
    
    // Scoring system
    $score = 0;
    
    // Temperature (ideal: 18-30°C)
    if ($temp >= 18 && $temp <= 30) $score += 3;
    elseif ($temp >= 15 && $temp < 18) $score += 2;
    elseif ($temp >= 10 && $temp < 15) $score += 1;
    
    // Humidity (ideal: < 70%)
    if ($humidity < 70) $score += 3;
    elseif ($humidity < 80) $score += 2;
    elseif ($humidity < 90) $score += 1;
    
    // Precipitation (ideal: 0mm)
    if ($precip == 0) $score += 3;
    elseif ($precip < 0.5) $score += 1;
    
    // Wind (ideal: 5-20 km/h)
    if ($wind >= 5 && $wind <= 20) $score += 2;
    elseif ($wind > 0 && $wind < 30) $score += 1;
    
    // Determine recommendation
    if ($score >= 10) return 'yes';
    if ($score >= 7) return 'maybe';
    return 'no';
}

/**
 * Generate simulated weather data as fallback
 */
function generateSimulatedWeather($location) {
    $seed = crc32($location . date('Y-m-d'));
    srand($seed);
    
    $temp = 10 + (rand() % 25);
    $humidity = 30 + (rand() % 60);
    $precip = (rand() % 100) < 30 ? (rand() % 50) / 10 : 0;
    $wind = 2 + (rand() % 25);
    
    $condition = $precip > 0 ? 'Rainy' : ($humidity > 80 ? 'Cloudy' : 'Partly Cloudy');
    $icon = $precip > 0 ? 'cloud-rain' : ($humidity > 80 ? 'cloud' : 'cloud-sun');
    
    return [
        'temperature' => $temp,
        'humidity' => $humidity,
        'precipitation' => $precip,
        'wind' => $wind,
        'condition' => $condition,
        'icon' => $icon,
        'city' => $location,
        'daily_high' => $temp + rand(2, 8),
        'daily_low' => $temp - rand(2, 8),
        'daily_precip' => $precip + (rand() % 10) / 10,
        'weather_code' => $precip > 0 ? 63 : ($humidity > 80 ? 3 : 2)
    ];
}

/**
 * Save execution log
 */
function saveDailyLog($log, $emailCount, $errorCount, $totalSubscriptions) {
    $filename = DATA_DIR . 'daily_updates_log.json';
    $logEntry = [
        'date' => date('Y-m-d'),
        'timestamp' => date('Y-m-d H:i:s'),
        'emails_sent' => $emailCount,
        'total_subscriptions' => $totalSubscriptions,
        'errors' => $errorCount,
        'details' => $log
    ];
    
    $logs = [];
    if (file_exists($filename)) {
        $logs = json_decode(file_get_contents($filename), true) ?: [];
    }
    
    $logs[] = $logEntry;
    
    // Keep only last 30 days of logs
    if (count($logs) > 30) {
        $logs = array_slice($logs, -30);
    }
    
    file_put_contents($filename, json_encode($logs, JSON_PRETTY_PRINT));
    
    // Also save as text log for easy reading
    $textLog = DATA_DIR . 'daily_updates.txt';
    file_put_contents($textLog, implode("\n", $log) . "\n\n", FILE_APPEND);
}

/**
 * Log email activity
 */
function logEmailActivity($email, $type, $success, $method) {
    $logFile = DATA_DIR . 'email_activity.log';
    $status = $success ? 'SUCCESS' : 'FAILED';
    $logEntry = date('Y-m-d H:i:s') . " - $type - $email - $status - $method\n";
    file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
}

/**
 * Manual trigger for testing
 */
if (php_sapi_name() === 'cli') {
    // Command line execution (cron)
    $result = sendDailyUpdates();
    echo implode("\n", $result['log']) . "\n";
} elseif (isset($_GET['run']) && $_GET['run'] === 'test') {
    // Web execution for testing
    header('Content-Type: text/plain; charset=UTF-8');
    $result = sendDailyUpdates();
    echo implode("\n", $result['log']);
} elseif (isset($_GET['run']) && $_GET['run'] === 'single') {
    // Test with single user
    header('Content-Type: text/plain; charset=UTF-8');
    
    $testUser = [
        'name' => 'Test User',
        'email' => 'test@example.com', // Change this
        'location' => 'London',
        'notifications' => ['email']
    ];
    
    echo "Testing Single User Daily Update\n";
    echo "================================\n\n";
    
    $weatherData = getWeatherData($testUser['location']);
    $recommendation = getDryingRecommendation($weatherData);
    
    echo "User: " . $testUser['name'] . "\n";
    echo "Email: " . $testUser['email'] . "\n";
    echo "Location: " . $testUser['location'] . "\n";
    echo "Weather: " . $weatherData['temperature'] . "°C, " . $weatherData['humidity'] . "% humidity\n";
    echo "Condition: " . $weatherData['condition'] . "\n";
    echo "Recommendation: " . $recommendation . "\n\n";
    
    // Test email sending
    $result = sendDailyEmail($testUser['email'], $testUser['name'], $testUser['location'], $weatherData, $recommendation);
    
    if ($result['success']) {
        echo "✅ Email sent successfully via: " . $result['method'] . "\n";
    } else {
        echo "❌ Email failed: " . $result['error'] . "\n";
    }
} elseif (isset($_GET['run']) && $_GET['run'] === 'stats') {
    // Show statistics
    header('Content-Type: application/json; charset=UTF-8');
    
    $subscriptions = loadSubscriptions();
    $stats = [
        'total_subscriptions' => count($subscriptions),
        'last_updated' => date('Y-m-d H:i:s'),
        'subscriptions' => $subscriptions
    ];
    
    echo json_encode($stats, JSON_PRETTY_PRINT);
} else {
    // Show info page
    header('Content-Type: text/html; charset=UTF-8');
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Sampay Daily Updates</title>
        <style>
            body { font-family: 'Inter', Arial, sans-serif; background: #f5f7fa; margin: 0; padding: 20px; }
            .container { max-width: 800px; margin: 0 auto; background: white; padding: 30px; border-radius: 10px; box-shadow: 0 4px 20px rgba(0,0,0,0.1); }
            .header { background: linear-gradient(135deg, #4361ee, #4895ef); color: white; padding: 30px; border-radius: 8px; text-align: center; margin-bottom: 30px; }
            .btn { display: inline-block; padding: 12px 24px; background: #4361ee; color: white; text-decoration: none; border-radius: 6px; margin: 10px; transition: all 0.3s ease; }
            .btn:hover { background: #3a56d4; transform: translateY(-2px); }
            .endpoints { background: #f8f9fa; padding: 20px; border-radius: 8px; margin: 20px 0; }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="header">
                <h1>🧺 Sampay Daily Updates</h1>
                <p>Smart Clothes Drying Assistant - Daily Email Service</p>
            </div>
            
            <h2>Daily Weather Update Service</h2>
            <p>This service sends daily weather updates and drying recommendations to all subscribed users.</p>
            
            <div class="endpoints">
                <h3>Available Endpoints:</h3>
                <p>
                    <a href="?run=test" class="btn">Test Daily Updates</a>
                    <a href="?run=single" class="btn">Test Single User</a>
                    <a href="?run=stats" class="btn">View Statistics</a>
                </p>
                
                <h4>URL Parameters:</h4>
                <ul>
                    <li><code>?run=test</code> - Run full daily updates (sends emails to all subscribers)</li>
                    <li><code>?run=single</code> - Test with a single user</li>
                    <li><code>?run=stats</code> - View subscription statistics</li>
                </ul>
            </div>
            
            <h3>Cron Job Setup:</h3>
            <div style="background: #e8f4fd; padding: 15px; border-radius: 6px; font-family: monospace;">
                0 8 * * * /usr/bin/php <?php echo __FILE__; ?>
            </div>
            
            <p><em>This will run daily at 8:00 AM and send weather updates to all subscribers.</em></p>
        </div>
    </body>
    </html>
    <?php
}
?>