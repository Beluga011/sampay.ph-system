<?php
require_once 'config.php';
require_once 'weather.php';

// Simple notification system using file-based storage
function checkAndSendNotifications() {
    $subscriptions = getSubscriptions();
    
    foreach ($subscriptions as $subscriber) {
        $weatherData = getOpenMeteoData($subscriber['location']);
        if ($weatherData && isGoodForDrying($weatherData)) {
            sendDryingAlert($subscriber, $weatherData);
        }
    }
    
    // Log the notification check
    file_put_contents(DATA_DIR . 'notification_log.txt', 
        date('Y-m-d H:i:s') . " - Notifications checked\n", 
        FILE_APPEND
    );
}

function getSubscriptions() {
    $filename = DATA_DIR . 'subscriptions_backup.json';
    if (file_exists($filename)) {
        return json_decode(file_get_contents($filename), true) ?: [];
    }
    
    return [];
}

function isGoodForDrying($weatherData) {
    return $weatherData['temperature'] >= 18 && 
           $weatherData['humidity'] < 70 && 
           $weatherData['precipitation'] === 0 &&
           $weatherData['wind'] >= 5 && $weatherData['wind'] <= 20;
}

function sendDryingAlert($subscriber, $weatherData) {
    $notifications = is_string($subscriber['notifications']) ? 
        json_decode($subscriber['notifications'], true) : 
        ($subscriber['notifications'] ?? []);
    
    $message = "🌞 Perfect drying conditions today in {$subscriber['location']}! ";
    $message .= "Temp: {$weatherData['temperature']}°C, Humidity: {$weatherData['humidity']}%, ";
    $message .= "Wind: {$weatherData['wind']} km/h, No rain expected.";
    
    if (in_array('email', $notifications)) {
        sendEmailAlert($subscriber['email'], $subscriber['name'], $message, $weatherData);
    }
    
    // Log SMS alerts (since SMS requires paid service)
    if (in_array('sms', $notifications) && !empty($subscriber['phone'])) {
        logSMSAlert($subscriber['phone'], $message);
    }
}

function sendEmailAlert($email, $name, $message, $weatherData) {
    $subject = "Sampay Alert: Perfect Drying Conditions Today!";
    
    $htmlMessage = "
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; }
            .alert { background: linear-gradient(135deg, #4cc9f0, #4895ef); color: white; padding: 25px; border-radius: 10px; }
            .weather-info { background: white; color: #333; padding: 20px; margin: 15px 0; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
            .button { display: inline-block; padding: 12px 24px; background: #4361ee; color: white; text-decoration: none; border-radius: 5px; margin: 15px 0; }
        </style>
    </head>
    <body>
        <div style='max-width: 600px; margin: 0 auto;'>
            <div class='alert'>
                <h2>Great News, $name! 🧺</h2>
                <p style='font-size: 18px;'>$message</p>
            </div>
            
            <div class='weather-info'>
                <h3>Today's Weather Details:</h3>
                <p>🌡️ Temperature: {$weatherData['temperature']}°C</p>
                <p>💧 Humidity: {$weatherData['humidity']}%</p>
                <p>💨 Wind: {$weatherData['wind']} km/h</p>
                <p>☁️ Condition: {$weatherData['condition']}</p>
            </div>
            
            <p>It's the perfect day to hang your laundry outside! 🎉</p>
            
            <a href='" . APP_URL . "' class='button'>Check Detailed Forecast</a>
            
            <div style='margin-top: 30px; padding-top: 20px; border-top: 1px solid #eee; color: #666; font-size: 14px;'>
                <p>You received this alert because you subscribed to Sampay drying recommendations.</p>
                <p><a href='" . APP_URL . "/backend/unsubscribe.php?email=$email'>Unsubscribe from alerts</a></p>
            </div>
        </div>
    </body>
    </html>
    ";
    
    $headers = "MIME-Version: 1.0" . "\r\n";
    $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
    $headers .= "From: " . APP_NAME . " Alerts <" . APP_EMAIL . ">" . "\r\n";
    
    return mail($email, $subject, $htmlMessage, $headers);
}

function logSMSAlert($phone, $message) {
    $log = [
        'phone' => $phone,
        'message' => $message,
        'timestamp' => date('Y-m-d H:i:s')
    ];
    
    $filename = DATA_DIR . 'sms_alerts_log.json';
    $currentLogs = [];
    
    if (file_exists($filename)) {
        $currentLogs = json_decode(file_get_contents($filename), true) ?: [];
    }
    
    $currentLogs[] = $log;
    file_put_contents($filename, json_encode($currentLogs, JSON_PRETTY_PRINT));
}

// Manual trigger for testing
if (isset($_GET['test'])) {
    checkAndSendNotifications();
    echo "Notifications checked and sent!";
}

// Cron job endpoint
if (isset($_GET['cron']) && $_GET['cron'] === 'run') {
    checkAndSendNotifications();
    echo "Cron job executed successfully.";
}
?>