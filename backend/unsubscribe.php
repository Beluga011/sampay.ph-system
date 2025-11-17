<?php
require_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['email'])) {
    $email = filter_var($_GET['email'], FILTER_SANITIZE_EMAIL);
    
    if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
        // Remove from backup file
        removeFromBackup($email);
        
        $message = "You have been unsubscribed from Sampay alerts. Sorry to see you go!";
    } else {
        $message = "Invalid email address.";
    }
} else {
    $message = "No email specified for unsubscribe.";
}

// Show confirmation page
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Unsubscribe - Sampay</title>
    <style>
        body { 
            font-family: 'Inter', sans-serif; 
            background: linear-gradient(135deg, #f5f7fa 0%, #e4edf5 100%);
            margin: 0; padding: 20px; min-height: 100vh; display: flex; align-items: center; justify-content: center;
        }
        .container { 
            background: white; padding: 40px; border-radius: 12px; box-shadow: 0 10px 40px rgba(0,0,0,0.1); 
            text-align: center; max-width: 500px; width: 100%;
        }
        .icon { 
            font-size: 4rem; color: #4361ee; margin-bottom: 20px; 
        }
        h1 { color: #333; margin-bottom: 20px; }
        p { color: #666; line-height: 1.6; margin-bottom: 30px; }
        .btn { 
            display: inline-block; padding: 12px 24px; background: #4361ee; color: white; 
            text-decoration: none; border-radius: 6px; transition: all 0.3s ease;
        }
        .btn:hover { background: #3a56d4; transform: translateY(-2px); }
    </style>
</head>
<body>
    <div class="container">
        <div class="icon">👋</div>
        <h1>Unsubscribe Complete</h1>
        <p><?php echo htmlspecialchars($message); ?></p>
        <a href="<?php echo APP_URL; ?>" class="btn">Return to Sampay</a>
    </div>
</body>
</html>

<?php
function removeFromBackup($email) {
    $filename = DATA_DIR . 'subscriptions_backup.json';
    if (file_exists($filename)) {
        $subscriptions = json_decode(file_get_contents($filename), true) ?: [];
        $subscriptions = array_filter($subscriptions, function($sub) use ($email) {
            return $sub['email'] !== $email;
        });
        file_put_contents($filename, json_encode(array_values($subscriptions), JSON_PRETTY_PRINT));
    }
}
?>