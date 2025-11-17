<?php
// phpmailer-composer.php - Complete PHPMailer implementation

// Load Composer's autoloader
require_once __DIR__ . '/vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

class SampayMailer {
    private $mailer;
    private $config;
    
    public function __construct($config = null) {
        $this->mailer = new PHPMailer(true);
        $this->config = $config ?: $this->getDefaultConfig();
        $this->configure();
    }
    
    private function getDefaultConfig() {
        return [
            'smtp' => [
                'host' => 'smtp.gmail.com',
                'port' => 587,
                'secure' => 'tls',
                'auth' => true,
                'username' => 'g0329498@gmail.com', // UPDATE THIS
                'password' => 'nzez dktr oche hunr',    // UPDATE THIS
                'debug' => false
            ],
            'from' => [
                'email' => 'noreply@sampay.com',
                'name' => 'Sampay Alerts'
            ],
            'reply_to' => [
                'email' => 'support@sampay.com',
                'name' => 'Sampay Support'
            ]
        ];
    }
    
    private function configure() {
        try {
            // Server settings
            $this->mailer->isSMTP();
            $this->mailer->Host       = $this->config['smtp']['host'];
            $this->mailer->SMTPAuth   = $this->config['smtp']['auth'];
            $this->mailer->Username   = $this->config['smtp']['username'];
            $this->mailer->Password   = $this->config['smtp']['password'];
            $this->mailer->SMTPSecure = $this->config['smtp']['secure'];
            $this->mailer->Port       = $this->config['smtp']['port'];
            
            // Debugging
            if ($this->config['smtp']['debug']) {
                $this->mailer->SMTPDebug = SMTP::DEBUG_SERVER;
            }
            
            // Sender settings
            $this->mailer->setFrom(
                $this->config['from']['email'], 
                $this->config['from']['name']
            );
            
            $this->mailer->addReplyTo(
                $this->config['reply_to']['email'],
                $this->config['reply_to']['name']
            );
            
            // Content settings
            $this->mailer->isHTML(true);
            $this->mailer->CharSet = 'UTF-8';
            $this->mailer->Encoding = 'base64';
            
        } catch (Exception $e) {
            error_log("PHPMailer Configuration Error: " . $e->getMessage());
            throw $e;
        }
    }
    
    public function send($to, $subject, $htmlContent, $textContent = null, $attachments = []) {
        try {
            // Clear previous recipients and attachments
            $this->mailer->clearAllRecipients();
            $this->mailer->clearAttachments();
            $this->mailer->clearCustomHeaders();
            
            // Validate email
            if (!$this->validateEmail($to)) {
                throw new Exception("Invalid email address: $to");
            }
            
            // Add recipient
            $this->mailer->addAddress($to);
            
            // Subject and content
            $this->mailer->Subject = $subject;
            $this->mailer->Body    = $htmlContent;
            $this->mailer->AltBody = $textContent ?: $this->htmlToText($htmlContent);
            
            // Add attachments if any
            foreach ($attachments as $attachment) {
                if (isset($attachment['path']) && file_exists($attachment['path'])) {
                    $this->mailer->addAttachment(
                        $attachment['path'],
                        $attachment['name'] ?? basename($attachment['path'])
                    );
                }
            }
            
            // Send email
            $this->mailer->send();
            
            return [
                'success' => true,
                'message' => 'Email sent successfully',
                'method' => 'phpmailer_smtp',
                'to' => $to,
                'subject' => $subject
            ];
            
        } catch (Exception $e) {
            $error = "PHPMailer Error: {$this->mailer->ErrorInfo}";
            error_log($error);
            
            // Fallback to PHP mail()
            return $this->sendFallback($to, $subject, $htmlContent, $textContent);
        }
    }
    
    public function sendBatch($recipients, $subject, $htmlContent, $textContent = null) {
        $results = [];
        
        foreach ($recipients as $recipient) {
            if (is_array($recipient)) {
                $email = $recipient['email'];
                $name = $recipient['name'] ?? '';
                
                // Personalize content
                $personalizedHtml = $this->personalizeContent($htmlContent, $name);
                $personalizedText = $textContent ? $this->personalizeContent($textContent, $name) : null;
                
                $results[$email] = $this->send($email, $subject, $personalizedHtml, $personalizedText);
            } else {
                $results[$recipient] = $this->send($recipient, $subject, $htmlContent, $textContent);
            }
            
            // Small delay between emails to avoid rate limiting
            usleep(100000); // 0.1 seconds
        }
        
        return $results;
    }
    
    private function sendFallback($to, $subject, $htmlContent, $textContent = null) {
        $headers = "MIME-Version: 1.0\r\n";
        $headers .= "Content-type:text/html;charset=UTF-8\r\n";
        $headers .= "From: {$this->config['from']['name']} <{$this->config['from']['email']}>\r\n";
        $headers .= "Reply-To: {$this->config['reply_to']['email']}\r\n";
        $headers .= "X-Mailer: PHP/" . phpversion();
        
        $success = mail($to, $subject, $htmlContent, $headers);
        
        return [
            'success' => $success,
            'method' => 'php_mail_fallback',
            'message' => $success ? 'Email sent via fallback' : 'Fallback also failed',
            'error' => $success ? '' : 'PHP mail() function failed',
            'to' => $to,
            'subject' => $subject
        ];
    }
    
    private function validateEmail($email) {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }
    
    private function htmlToText($html) {
        // Simple HTML to text conversion
        $text = strip_tags($html);
        $text = html_entity_decode($text, ENT_QUOTES, 'UTF-8');
        $text = preg_replace('/\s+/', ' ', $text);
        $text = preg_replace('/\n\s*\n/', "\n\n", $text);
        return trim($text);
    }
    
    private function personalizeContent($content, $name) {
        return str_replace('{{name}}', $name, $content);
    }
    
    public function testConnection() {
        try {
            $this->mailer->smtpConnect();
            $this->mailer->smtpClose();
            return ['success' => true, 'message' => 'SMTP connection successful'];
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
}

// Global helper functions
function sendSampayEmail($to, $subject, $htmlContent, $textContent = null) {
    static $mailer = null;
    
    if ($mailer === null) {
        $mailer = new SampayMailer();
    }
    
    return $mailer->send($to, $subject, $htmlContent, $textContent);
}

function sendSampayBatchEmail($recipients, $subject, $htmlContent, $textContent = null) {
    $mailer = new SampayMailer();
    return $mailer->sendBatch($recipients, $subject, $htmlContent, $textContent);
}

function testSampayEmailConnection() {
    $mailer = new SampayMailer();
    return $mailer->testConnection();
}
?>