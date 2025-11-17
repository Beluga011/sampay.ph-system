<?php
// Sampay Subscription Handler - Complete Version with PHPMailer
header('Content-Type: application/json; charset=UTF-8');
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

// Load PHPMailer and email templates
require_once 'phpmailer-composer.php';
require_once 'email-templates.php';

// Simple configuration
define('APP_NAME', 'Sampay');
define('APP_URL', 'http://localhost/sampay');
define('APP_EMAIL', 'noreply@yoursite.com');
define('DATA_DIR', __DIR__ . '/../data/');

// Create data directory if it doesn't exist
if (!file_exists(DATA_DIR)) {
    mkdir(DATA_DIR, 0755, true);
}

// Don't display errors to users
ini_set('display_errors', 0);
ini_set('log_errors', 1);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Get input data
        $input = json_decode(file_get_contents('php://input'), true);
        
        // Support both JSON and form data
        if (json_last_error() === JSON_ERROR_NONE) {
            // JSON input
            $name = trim($input['name'] ?? '');
            $email = trim($input['email'] ?? '');
            $phone = trim($input['phone'] ?? '');
            $location = trim($input['location'] ?? '');
            $notifications = $input['notifications'] ?? ['email'];
        } else {
            // Form data input
            $name = trim($_POST['name'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $phone = trim($_POST['phone'] ?? '');
            $location = trim($_POST['location'] ?? '');
            $notifications = $_POST['notifications'] ?? ['email'];
        }
        
        // Validate required fields
        $errors = [];
        
        if (empty($name)) {
            $errors[] = 'Name is required';
        }
        
        if (empty($email)) {
            $errors[] = 'Email is required';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Please enter a valid email address';
        }
        
        if (empty($location)) {
            $errors[] = 'Location is required';
        }
        
        if (!empty($errors)) {
            http_response_code(400);
            echo json_encode([
                'success' => false, 
                'message' => implode(', ', $errors)
            ]);
            exit;
        }

        // Prepare subscription data
        $subscriptionData = [
            'name' => $name,
            'email' => $email,
            'phone' => $phone,
            'location' => $location,
            'notifications' => $notifications,
            'subscribed_at' => date('Y-m-d H:i:s'),
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
        ];

        // Save to file
        $saveResult = saveSubscription($subscriptionData);
        
        if (!$saveResult) {
            throw new Exception('Could not save subscription data');
        }

        // Send welcome email
        $emailResult = sendWelcomeEmail($name, $email, $location);
        
        // Success response
        http_response_code(200);
        echo json_encode([
            'success' => true, 
            'message' => 'Success! You have been subscribed to Sampay alerts.',
            'email_sent' => $emailResult['email_sent'],
            'email_method' => $emailResult['email_method'],
            'subscription_id' => md5($email . date('Y-m-d H:i:s'))
        ]);
        
    } catch (Exception $e) {
        // Log error for debugging
        error_log("Subscription error: " . $e->getMessage());
        
        http_response_code(500);
        echo json_encode([
            'success' => false, 
            'message' => 'An unexpected error occurred. Please try again.',
            'debug' => (ini_get('display_errors') ? $e->getMessage() : '')
        ]);
    }
} else if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // Show subscription form or info
    showSubscriptionInfo();
} else {
    http_response_code(405);
    echo json_encode([
        'success' => false, 
        'message' => 'Method not allowed. Please use POST.'
    ]);
}

function saveSubscription($data) {
    $filename = DATA_DIR . 'subscriptions.json';
    
    // Load existing subscriptions
    $subscriptions = [];
    if (file_exists($filename)) {
        $fileContent = file_get_contents($filename);
        if (!empty($fileContent)) {
            $subscriptions = json_decode($fileContent, true) ?: [];
        }
    }
    
    // Check if email already exists
    $emailExists = false;
    foreach ($subscriptions as $index => $subscription) {
        if ($subscription['email'] === $data['email']) {
            // Update existing subscription
            $subscriptions[$index] = array_merge($subscription, $data);
            $emailExists = true;
            break;
        }
    }
    
    // Add new subscription if email doesn't exist
    if (!$emailExists) {
        $subscriptions[] = $data;
    }
    
    // Save to file
    $result = file_put_contents($filename, json_encode($subscriptions, JSON_PRETTY_PRINT));
    
    // Also save to backup file
    $backupFile = DATA_DIR . 'subscriptions_backup_' . date('Y-m-d') . '.json';
    file_put_contents($backupFile, json_encode($subscriptions, JSON_PRETTY_PRINT));
    
    // Log subscription activity
    logSubscriptionActivity($data['email'], $emailExists ? 'updated' : 'created');
    
    return $result !== false;
}

function sendWelcomeEmail($name, $email, $location) {
    try {
        $unsubscribeLink = APP_URL . "/backend/unsubscribe.php?email=" . urlencode($email);
        $template = EmailTemplates::welcomeEmail($name, $location, $unsubscribeLink);
        
        $result = sendSampayEmail($email, $template['subject'], $template['html'], $template['text']);
        
        // Log email attempt
        logEmailActivity($email, 'welcome', $result['success'], $result['method']);
        
        return [
            'email_sent' => $result['success'],
            'email_method' => $result['method'],
            'email_error' => $result['success'] ? '' : ($result['error'] ?? 'Unknown error')
        ];
        
    } catch (Exception $e) {
        error_log("Welcome email error for $email: " . $e->getMessage());
        return [
            'email_sent' => false,
            'email_method' => 'error',
            'email_error' => $e->getMessage()
        ];
    }
}

function logSubscriptionActivity($email, $action) {
    $logFile = DATA_DIR . 'subscription_activity.log';
    $logEntry = date('Y-m-d H:i:s') . " - $action - $email - " . ($_SERVER['REMOTE_ADDR'] ?? 'unknown') . "\n";
    file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
}

function logEmailActivity($email, $type, $success, $method) {
    $logFile = DATA_DIR . 'email_activity.log';
    $status = $success ? 'SUCCESS' : 'FAILED';
    $logEntry = date('Y-m-d H:i:s') . " - $type - $email - $status - $method\n";
    file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
}

function showSubscriptionInfo() {
    header('Content-Type: text/html; charset=UTF-8');
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Sampay Subscription API</title>
        <style>
            body { 
                font-family: 'Inter', Arial, sans-serif; 
                background: linear-gradient(135deg, #f5f7fa 0%, #e4edf5 100%);
                margin: 0; padding: 20px; min-height: 100vh;
            }
            .container { 
                max-width: 800px; margin: 0 auto; background: white; 
                padding: 40px; border-radius: 12px; box-shadow: 0 10px 40px rgba(0,0,0,0.1);
            }
            .header { 
                background: linear-gradient(135deg, #4361ee, #4895ef); 
                color: white; padding: 30px; border-radius: 10px; margin-bottom: 30px;
            }
            .code-block { 
                background: #f8f9fa; padding: 20px; border-radius: 8px; 
                border-left: 4px solid #4361ee; margin: 20px 0; font-family: monospace;
                overflow-x: auto;
            }
            .endpoint { background: #e8f4fd; padding: 15px; border-radius: 6px; margin: 10px 0; }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="header">
                <h1>🧺 Sampay Subscription API</h1>
                <p>Smart Clothes Drying Assistant - Subscription Endpoint</p>
            </div>
            
            <h2>API Usage</h2>
            <p>This endpoint accepts POST requests with JSON or form data to subscribe users to Sampay alerts.</p>
            
            <div class="endpoint">
                <strong>Endpoint:</strong> POST <?php echo APP_URL; ?>/backend/subscribe.php
            </div>
            
            <h3>Request Format (JSON):</h3>
            <div class="code-block">
{
    "name": "John Doe",
    "email": "john@example.com",
    "phone": "+1234567890",
    "location": "London, UK",
    "notifications": ["email"]
}
            </div>
            
            <h3>Request Format (Form Data):</h3>
            <div class="code-block">
name=John+Doe&email=john@example.com&location=London+UK
            </div>
            
            <h3>Response Format:</h3>
            <div class="code-block">
{
    "success": true,
    "message": "Success! You have been subscribed to Sampay alerts.",
    "email_sent": true,
    "email_method": "phpmailer_smtp",
    "subscription_id": "abc123..."
}
            </div>
            
            <h3>Required Fields:</h3>
            <ul>
                <li><strong>name</strong> - User's full name</li>
                <li><strong>email</strong> - Valid email address</li>
                <li><strong>location</strong> - City or location for weather alerts</li>
            </ul>
            
            <h3>Optional Fields:</h3>
            <ul>
                <li><strong>phone</strong> - Phone number (currently not used)</li>
                <li><strong>notifications</strong> - Array of notification methods (default: ["email"])</li>
            </ul>
        </div>
    </body>
    </html>
    <?php
}

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}
?>