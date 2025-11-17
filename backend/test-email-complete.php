<?php
// test-email-complete.php - Complete email testing script

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<pre>";
echo "=== Sampay Email System Test ===\n";
echo "================================\n\n";

// Define constants first
define('APP_NAME', 'Sampay');
define('APP_URL', 'http://localhost/sampay');
define('APP_EMAIL', 'noreply@sampay.com');
define('DATA_DIR', __DIR__ . '/../data/');

// Test Composer autoloader
if (!file_exists(__DIR__ . '/vendor/autoload.php')) {
    die("❌ Composer autoloader not found. Run 'composer install' first.\n");
}

require_once 'phpmailer-composer.php';
require_once 'email-templates.php';

echo "✅ Composer autoloader loaded\n";
echo "✅ PHPMailer classes available\n";
echo "✅ Email templates loaded\n\n";

// Test SMTP connection
echo "Testing SMTP connection...\n";
$connectionTest = testSampayEmailConnection();

if ($connectionTest['success']) {
    echo "✅ SMTP Connection: SUCCESS - {$connectionTest['message']}\n\n";
} else {
    echo "❌ SMTP Connection: FAILED - {$connectionTest['error']}\n\n";
    echo "Please check your SMTP configuration in phpmailer-composer.php\n\n";
}

// Test data
$testEmail = "alfredozboajr.pvgma@gmail.com"; // CHANGE THIS
$testName = "John Doe";
$testLocation = "London";

// Generate test weather data
$testWeather = [
    'temperature' => 22,
    'humidity' => 65,
    'precipitation' => 0,
    'wind' => 12,
    'condition' => 'Partly Cloudy',
    'daily_high' => 25,
    'daily_low' => 18,
    'daily_precip' => 0
];

$testRecommendation = 'yes';

// Test 1: Welcome Email
echo "Test 1: Welcome Email\n";
echo "---------------------\n";

$welcomeTemplate = EmailTemplates::welcomeEmail($testName, $testLocation, APP_URL . '/backend/unsubscribe.php');
$welcomeResult = sendSampayEmail($testEmail, $welcomeTemplate['subject'], $welcomeTemplate['html'], $welcomeTemplate['text']);

if ($welcomeResult['success']) {
    echo "✅ Welcome Email: SENT successfully via {$welcomeResult['method']}\n";
} else {
    echo "❌ Welcome Email: FAILED - {$welcomeResult['error']}\n";
}

echo "\n";

// Test 2: Daily Update Email
echo "Test 2: Daily Update Email\n";
echo "--------------------------\n";

$dailyTemplate = EmailTemplates::dailyUpdateEmail($testName, $testLocation, $testWeather, $testRecommendation);
$dailyResult = sendSampayEmail($testEmail, $dailyTemplate['subject'], $dailyTemplate['html'], $dailyTemplate['text']);

if ($dailyResult['success']) {
    echo "✅ Daily Update Email: SENT successfully via {$dailyResult['method']}\n";
} else {
    echo "❌ Daily Update Email: FAILED - {$dailyResult['error']}\n";
}

echo "\n";

// Test 3: Batch Email (simulated)
echo "Test 3: Batch Email Simulation\n";
echo "------------------------------\n";

$testRecipients = [
    ['email' => $testEmail, 'name' => $testName],
    ['email' => 'alfredozboajr.pvgma@gmail.com', 'name' => 'Jane Smith']
];

$batchTemplate = EmailTemplates::dailyUpdateEmail('{{name}}', $testLocation, $testWeather, $testRecommendation);
$batchResult = sendSampayBatchEmail([$testRecipients[0]], $batchTemplate['subject'], $batchTemplate['html'], $batchTemplate['text']);

foreach ($batchResult as $email => $result) {
    if ($result['success']) {
        echo "✅ Batch Email to $email: SENT via {$result['method']}\n";
    } else {
        echo "❌ Batch Email to $email: FAILED - {$result['error']}\n";
    }
}

echo "\n";
echo "=== Test Summary ===\n";
echo "Emails sent to: $testEmail\n";
echo "Please check your inbox (and spam folder) for test emails.\n";
echo "If emails are not arriving, check:\n";
echo "1. SMTP credentials in phpmailer-composer.php\n";
echo "2. Gmail App Password configuration\n";
echo "3. Firewall/port 587 access\n";

echo "</pre>";
?>