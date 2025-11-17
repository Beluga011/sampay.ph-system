<?php
// email-config.php
// Better email configuration for Sampay

class SampayMailer {
    private $method;
    
    public function __construct() {
        $this->method = $this->detectBestMethod();
    }
    
    private function detectBestMethod() {
        // Check if SMTP is configured
        if (defined('SMTP_HOST') && SMTP_HOST) {
            return 'smtp';
        }
        
        // Check if sendmail is available
        if (function_exists('mail')) {
            return 'mail';
        }
        
        return 'none';
    }
    
    public function send($to, $subject, $htmlContent, $textContent = '') {
        switch ($this->method) {
            case 'smtp':
                return $this->sendSMTP($to, $subject, $htmlContent, $textContent);
            case 'mail':
                return $this->sendPHPmail($to, $subject, $htmlContent, $textContent);
            default:
                return ['success' => false, 'error' => 'No email method available'];
        }
    }
    
    private function sendPHPmail($to, $subject, $htmlContent, $textContent) {
        $headers = "MIME-Version: 1.0\r\n";
        $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
        $headers .= "From: " . APP_NAME . " <" . APP_EMAIL . ">\r\n";
        $headers .= "Reply-To: " . APP_EMAIL . "\r\n";
        $headers .= "X-Mailer: PHP/" . phpversion();
        
        $success = mail($to, $subject, $htmlContent, $headers);
        
        return [
            'success' => $success,
            'method' => 'php_mail',
            'error' => $success ? '' : 'Mail function failed'
        ];
    }
    
    private function sendSMTP($to, $subject, $htmlContent, $textContent) {
        // Implement SMTP sending here
        // You can use PHPMailer or similar library
        return ['success' => false, 'error' => 'SMTP not implemented', 'method' => 'smtp'];
    }
}

// Helper function
function sendSampayEmail($to, $subject, $htmlContent, $textContent = '') {
    $mailer = new SampayMailer();
    return $mailer->send($to, $subject, $htmlContent, $textContent);
}
?>