<?php
// email-templates.php - Email templates for Sampay

class EmailTemplates {
    
    public static function welcomeEmail($name, $location, $unsubscribeLink) {
        // Define APP_URL if not already defined
        if (!defined('APP_URL')) {
            define('APP_URL', 'http://localhost/sampay');
        }
        if (!defined('APP_NAME')) {
            define('APP_NAME', 'Sampay');
        }
        
        $subject = "Welcome to Sampay - Smart Clothes Drying Assistant";
        
        $html = "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <style>
                body { 
                    font-family: 'Inter', Arial, sans-serif; 
                    line-height: 1.6; 
                    color: #333; 
                    margin: 0; 
                    padding: 0; 
                    background: #f5f7fa;
                }
                .container { 
                    max-width: 600px; 
                    margin: 0 auto; 
                    background: white;
                    border-radius: 12px;
                    overflow: hidden;
                    box-shadow: 0 4px 20px rgba(0,0,0,0.1);
                }
                .header { 
                    background: linear-gradient(135deg, #4361ee, #4895ef); 
                    color: white; 
                    padding: 40px 30px; 
                    text-align: center; 
                }
                .header h1 {
                    margin: 0 0 10px 0;
                    font-size: 28px;
                }
                .content { 
                    padding: 40px 30px; 
                }
                .footer { 
                    background: #2b2d42; 
                    color: white; 
                    padding: 30px; 
                    text-align: center; 
                    font-size: 14px;
                }
                .button { 
                    display: inline-block; 
                    padding: 14px 28px; 
                    background: #4361ee; 
                    color: white; 
                    text-decoration: none; 
                    border-radius: 8px; 
                    margin: 20px 0; 
                    font-weight: 600;
                }
                .features {
                    background: #f8f9fa;
                    padding: 25px;
                    border-radius: 8px;
                    margin: 25px 0;
                }
                .feature-item {
                    display: flex;
                    align-items: center;
                    margin: 12px 0;
                }
                .feature-icon {
                    width: 24px;
                    margin-right: 12px;
                    color: #4361ee;
                }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>Welcome to Sampay! 🌞</h1>
                    <p>Smart Clothes Drying Assistant</p>
                </div>
                <div class='content'>
                    <h2>Hello $name,</h2>
                    <p>Thank you for subscribing to Sampay alerts for <strong>$location</strong>.</p>
                    <p>You'll now receive smart notifications about the perfect times to dry your clothes outside.</p>
                    
                    <div class='features'>
                        <h3 style='margin-top: 0;'>What to expect:</h3>
                        <div class='feature-item'>
                            <span class='feature-icon'>📅</span>
                            <span>Daily drying condition forecasts</span>
                        </div>
                        <div class='feature-item'>
                            <span class='feature-icon'>🌧️</span>
                            <span>Rain alert warnings</span>
                        </div>
                        <div class='feature-item'>
                            <span class='feature-icon'>⏰</span>
                            <span>Optimal drying time suggestions</span>
                        </div>
                        <div class='feature-item'>
                            <span class='feature-icon'>📱</span>
                            <span>Email notifications</span>
                        </div>
                    </div>
                    
                    <p>We'll help you save time, energy, and never get caught in the rain again!</p>
                    
                    <center>
                        <a href='" . APP_URL . "' class='button'>Check Weather Now</a>
                    </center>
                </div>
                <div class='footer'>
                    <p>&copy; " . date('Y') . " " . APP_NAME . ". All rights reserved.</p>
                    <p>
                        <a href='$unsubscribeLink' style='color: #ccc; text-decoration: none;'>
                            Unsubscribe from alerts
                        </a>
                    </p>
                </div>
            </div>
        </body>
        </html>
        ";
        
        $text = "Welcome to " . APP_NAME . ", $name!\n\n" .
                "Thank you for subscribing to " . APP_NAME . " alerts for $location.\n\n" .
                "You'll now receive smart notifications about the perfect times to dry your clothes outside.\n\n" .
                "We'll help you save time, energy, and never get caught in the rain again!\n\n" .
                "Visit: " . APP_URL . "\n\n" .
                "Unsubscribe: $unsubscribeLink";
        
        return [
            'subject' => $subject,
            'html' => $html,
            'text' => $text
        ];
    }
    
    public static function dailyUpdateEmail($name, $location, $weatherData, $recommendation) {
        // Define constants if not already defined
        if (!defined('APP_URL')) {
            define('APP_URL', 'http://localhost/sampay');
        }
        if (!defined('APP_NAME')) {
            define('APP_NAME', 'Sampay');
        }
        
        $icon = self::getRecommendationIcon($recommendation);
        $title = self::getRecommendationTitle($recommendation);
        $color = self::getRecommendationColor($recommendation);
        $date = date('l, F j, Y');
        $advice = self::getDailyAdvice($recommendation, $weatherData);
        
        $subject = self::getDailyEmailSubject($recommendation, $location);
        
        $html = "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <style>
                body { 
                    font-family: 'Inter', Arial, sans-serif; 
                    line-height: 1.6; 
                    color: #333; 
                    margin: 0; 
                    padding: 0; 
                    background: #f5f7fa;
                }
                .container { 
                    max-width: 600px; 
                    margin: 0 auto; 
                    background: white;
                    border-radius: 12px;
                    overflow: hidden;
                    box-shadow: 0 4px 20px rgba(0,0,0,0.1);
                }
                .header { 
                    background: linear-gradient(135deg, #4361ee, #4895ef); 
                    color: white; 
                    padding: 40px 30px; 
                    text-align: center; 
                }
                .date { 
                    background: rgba(255,255,255,0.1); 
                    display: inline-block; 
                    padding: 8px 16px; 
                    border-radius: 20px; 
                    font-size: 14px; 
                    margin-bottom: 10px;
                }
                .recommendation-banner {
                    background: $color;
                    color: white;
                    padding: 25px;
                    text-align: center;
                    font-size: 24px;
                    font-weight: bold;
                }
                .weather-grid {
                    display: grid;
                    grid-template-columns: 1fr 1fr;
                    gap: 15px;
                    margin: 20px;
                }
                .weather-card {
                    background: #f8f9fa;
                    padding: 20px;
                    border-radius: 10px;
                    text-align: center;
                }
                .weather-icon {
                    font-size: 48px;
                    margin-bottom: 10px;
                }
                .stat-value {
                    font-size: 28px;
                    font-weight: bold;
                    color: #4361ee;
                    margin: 10px 0;
                }
                .stat-label {
                    font-size: 14px;
                    color: #666;
                }
                .daily-outlook {
                    background: #e8f4fd;
                    margin: 20px;
                    padding: 20px;
                    border-radius: 10px;
                }
                .outlook-grid {
                    display: grid;
                    grid-template-columns: 1fr 1fr 1fr;
                    gap: 15px;
                    margin-top: 15px;
                }
                .outlook-item {
                    text-align: center;
                    padding: 15px;
                    background: white;
                    border-radius: 8px;
                }
                .footer { 
                    background: #2b2d42; 
                    color: white; 
                    padding: 30px; 
                    text-align: center; 
                    font-size: 14px;
                }
                .button { 
                    display: inline-block; 
                    padding: 14px 28px; 
                    background: #4361ee; 
                    color: white; 
                    text-decoration: none; 
                    border-radius: 8px; 
                    margin: 20px 0; 
                    font-weight: 600;
                }
                .advice-box {
                    background: #fff3cd;
                    border-left: 4px solid #ffc107;
                    margin: 20px;
                    padding: 20px;
                    border-radius: 0 8px 8px 0;
                }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <div class='date'>$date</div>
                    <h1>🧺 Your Daily Drying Forecast</h1>
                    <p>Smart weather intelligence for $location</p>
                </div>
                
                <div class='recommendation-banner'>
                    <div style='font-size: 48px; margin-bottom: 10px;'>$icon</div>
                    $title
                </div>
                
                <div style='padding: 20px;'>
                    <h2>Hello $name,</h2>
                    <p>Here's your personalized drying forecast for <strong>$location</strong>:</p>
                    
                    <div class='weather-grid'>
                        <div class='weather-card'>
                            <div class='weather-icon'>🌡️</div>
                            <div class='stat-value'>{$weatherData['temperature']}°C</div>
                            <div class='stat-label'>Temperature</div>
                        </div>
                        <div class='weather-card'>
                            <div class='weather-icon'>💧</div>
                            <div class='stat-value'>{$weatherData['humidity']}%</div>
                            <div class='stat-label'>Humidity</div>
                        </div>
                        <div class='weather-card'>
                            <div class='weather-icon'>🌧️</div>
                            <div class='stat-value'>{$weatherData['precipitation']} mm</div>
                            <div class='stat-label'>Precipitation</div>
                        </div>
                        <div class='weather-card'>
                            <div class='weather-icon'>💨</div>
                            <div class='stat-value'>{$weatherData['wind']} km/h</div>
                            <div class='stat-label'>Wind Speed</div>
                        </div>
                    </div>
                    
                    <div class='daily-outlook'>
                        <h3>📅 Today's Outlook</h3>
                        <div class='outlook-grid'>
                            <div class='outlook-item'>
                                <div style='font-size: 24px; color: #e74c3c;'>🔥</div>
                                <div class='stat-value'>{$weatherData['daily_high']}°C</div>
                                <div class='stat-label'>High</div>
                            </div>
                            <div class='outlook-item'>
                                <div style='font-size: 24px; color: #3498db;'>❄️</div>
                                <div class='stat-value'>{$weatherData['daily_low']}°C</div>
                                <div class='stat-label'>Low</div>
                            </div>
                            <div class='outlook-item'>
                                <div style='font-size: 24px; color: #9b59b6;'>💦</div>
                                <div class='stat-value'>{$weatherData['daily_precip']} mm</div>
                                <div class='stat-label'>Total Rain</div>
                            </div>
                        </div>
                    </div>
                    
                    <div class='advice-box'>
                        <h3>🧺 Drying Advice</h3>
                        <p>$advice</p>
                    </div>
                    
                    <center>
                        <a href='" . APP_URL . "' class='button'>View Detailed Forecast</a>
                    </center>
                </div>
                
                <div class='footer'>
                    <p>&copy; " . date('Y') . " " . APP_NAME . ". Smart weather intelligence for perfect clothes drying.</p>
                    <p>
                        <a href='" . APP_URL . "/backend/unsubscribe.php' style='color: #ccc; text-decoration: none;'>
                            Unsubscribe from daily alerts
                        </a>
                    </p>
                </div>
            </div>
        </body>
        </html>
        ";
        
        $text = APP_NAME . " Daily Update for $location\n\n" .
                "Hello $name,\n\n" .
                "Current Weather:\n" .
                "- Temperature: {$weatherData['temperature']}°C\n" .
                "- Humidity: {$weatherData['humidity']}%\n" .
                "- Precipitation: {$weatherData['precipitation']} mm\n" .
                "- Wind: {$weatherData['wind']} km/h\n" .
                "- Condition: {$weatherData['condition']}\n\n" .
                "Today's Outlook:\n" .
                "- High: {$weatherData['daily_high']}°C\n" .
                "- Low: {$weatherData['daily_low']}°C\n" .
                "- Total Rain: {$weatherData['daily_precip']} mm\n\n" .
                "Drying Recommendation: $title\n\n" .
                "Advice: $advice\n\n" .
                "View detailed forecast: " . APP_URL . "\n\n" .
                "Unsubscribe: " . APP_URL . "/backend/unsubscribe.php";
        
        return [
            'subject' => $subject,
            'html' => $html,
            'text' => $text
        ];
    }
    
    private static function getRecommendationIcon($recommendation) {
        $icons = [
            'yes' => '🌞',
            'maybe' => '⛅', 
            'no' => '🌧️'
        ];
        return $icons[$recommendation] ?? '🧺';
    }
    
    private static function getRecommendationTitle($recommendation) {
        $titles = [
            'yes' => 'YES - Perfect Day for Drying!',
            'maybe' => 'MAYBE - Okay for Drying',
            'no' => 'NO - Poor Drying Conditions'
        ];
        return $titles[$recommendation] ?? 'Check Weather Conditions';
    }
    
    private static function getRecommendationColor($recommendation) {
        $colors = [
            'yes' => '#28a745',
            'maybe' => '#ffc107', 
            'no' => '#dc3545'
        ];
        return $colors[$recommendation] ?? '#4361ee';
    }
    
    private static function getDailyEmailSubject($recommendation, $location) {
        $titles = [
            'yes' => "🌞 Perfect Drying Day in $location!",
            'maybe' => "⛅ Okay Drying Day in $location",
            'no' => "🌧️ Poor Drying Day in $location"
        ];
        return $titles[$recommendation] ?? "Your Daily Drying Forecast for $location";
    }
    
    private static function getDailyAdvice($recommendation, $weatherData) {
        $temp = $weatherData['temperature'];
        $humidity = $weatherData['humidity'];
        
        if ($recommendation === 'yes') {
            return "Excellent conditions for outdoor drying! With {$temp}°C temperature and {$humidity}% humidity, your clothes will dry quickly and smell fresh. Perfect day to hang your laundry outside!";
        } elseif ($recommendation === 'maybe') {
            return "Drying is possible today, but conditions aren't ideal. Clothes may take longer to dry due to {$humidity}% humidity. Consider indoor drying or be patient if drying outside.";
        } else {
            $reason = $weatherData['precipitation'] > 0 ? "rain expected" : "high humidity ({$humidity}%)";
            return "Today's conditions aren't suitable for outdoor drying. Due to $reason, your clothes may not dry properly or could get wet. We recommend indoor drying alternatives.";
        }
    }
}
?>