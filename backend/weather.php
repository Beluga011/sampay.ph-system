<?php
require_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $location = $input['location'] ?? '';
    
    if (empty($location)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Location is required']);
        exit;
    }

    // Get weather data from Open-Meteo API
    $weatherData = getOpenMeteoData($location);
    
    if ($weatherData) {
        http_response_code(200);
        echo json_encode([
            'success' => true, 
            'data' => $weatherData,
            'source' => 'open-meteo'
        ]);
    } else {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Unable to fetch weather data. Please try again later.'
        ]);
    }
} else {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
}

// Open-Meteo API (Free, No API Key Required)
function getOpenMeteoData($location) {
    $coords = getCoordinates($location);
    if (!$coords) return null;
    
    $url = OPENMETEO_URL . "?" .
           "latitude={$coords['lat']}&longitude={$coords['lon']}" .
           "&current=temperature_2m,relative_humidity_2m,precipitation,wind_speed_10m,weather_code,cloud_cover" .
           "&hourly=precipitation_probability" .
           "&forecast_days=1" .
           "&timezone=auto";
    
    $response = @file_get_contents($url);
    if (!$response) return null;
    
    $data = json_decode($response, true);
    if (!isset($data['current'])) return null;
    
    $weatherCode = $data['current']['weather_code'] ?? 0;
    $cloudCover = $data['current']['cloud_cover'] ?? 0;
    $precipProb = !empty($data['hourly']['precipitation_probability']) 
        ? $data['hourly']['precipitation_probability'][0] 
        : 0;
    
    return [
        'temperature' => round($data['current']['temperature_2m'], 1),
        'humidity' => round($data['current']['relative_humidity_2m']),
        'precipitation' => round($data['current']['precipitation'], 2),
        'wind' => round($data['current']['wind_speed_10m'] * 3.6, 1), // m/s to km/h
        'condition' => getOpenMeteoCondition($weatherCode, $cloudCover),
        'icon' => getOpenMeteoIcon($weatherCode, $cloudCover, isNightTime($coords)),
        'city' => $coords['city'] ?? 'Current Location',
        'cloud_cover' => $cloudCover,
        'precip_probability' => $precipProb
    ];
}

// Check if it's nighttime based on coordinates (simple and reliable)
function isNightTime($coords) {
    $lat = $coords['lat'];
    $lon = $coords['lon'];
    
    // Calculate UTC offset in hours from longitude (rough estimate)
    $utc_offset = round($lon / 15); // 15 degrees per timezone hour
    
    // Get current UTC time
    $utc_hour = (int)gmdate('G');
    
    // Calculate local hour (rough estimate)
    $local_hour = $utc_hour + $utc_offset;
    
    // Normalize to 0-23 range
    $local_hour = ($local_hour + 24) % 24;
    
    // Simple night check (7 PM - 6 AM)
    return $local_hour >= 19 || $local_hour < 6;
}

// Get coordinates from location name
function getCoordinates($location) {
    if (is_array($location)) {
        return $location + ['city' => 'Current Location'];
    }
    
    // Use free Nominatim geocoding (OpenStreetMap)
    $url = "https://nominatim.openstreetmap.org/search?format=json&q=" . urlencode($location) . "&limit=1";
    
    $context = stream_context_create([
        'http' => [
            'header' => "User-Agent: Sampay Weather App/1.0\r\n",
            'timeout' => 5
        ]
    ]);
    
    $response = @file_get_contents($url, false, $context);
    if (!$response) return null;
    
    $data = json_decode($response, true);
    if (empty($data)) return null;
    
    // Extract city name more cleanly
    $displayParts = explode(',', $data[0]['display_name']);
    $cityName = trim($displayParts[0]);
    
    return [
        'lat' => round($data[0]['lat'], 4),
        'lon' => round($data[0]['lon'], 4),
        'city' => $cityName
    ];
}

// Map Open-Meteo weather codes to conditions (calibrated)
function getOpenMeteoCondition($code, $cloudCover = 0) {
    $conditions = [
        0 => $cloudCover < 20 ? 'Clear sky' : 'Mostly clear',
        1 => $cloudCover < 40 ? 'Mainly clear' : 'Partly cloudy',
        2 => 'Partly cloudy',
        3 => 'Overcast',
        45 => 'Foggy',
        48 => 'Fog with rime',
        51 => 'Light drizzle',
        53 => 'Moderate drizzle',
        55 => 'Dense drizzle',
        56 => 'Light freezing drizzle',
        57 => 'Dense freezing drizzle',
        61 => 'Light rain',
        63 => 'Moderate rain',
        65 => 'Heavy rain',
        66 => 'Light freezing rain',
        67 => 'Heavy freezing rain',
        71 => 'Light snow',
        73 => 'Moderate snow',
        75 => 'Heavy snow',
        77 => 'Snow grains',
        80 => 'Light rain showers',
        81 => 'Moderate rain showers',
        82 => 'Heavy rain showers',
        85 => 'Light snow showers',
        86 => 'Heavy snow showers',
        95 => 'Thunderstorm',
        96 => 'Thunderstorm with hail',
        99 => 'Severe thunderstorm'
    ];
    
    return $conditions[$code] ?? 'Unknown';
}

// Map Open-Meteo weather codes to icons (calibrated with day/night)
function getOpenMeteoIcon($code, $cloudCover = 0, $isNight = false) {
    // Night icons
    if ($isNight) {
        $nightIcons = [
            0 => 'moon',
            1 => 'cloud-moon',
            2 => 'cloud-moon',
            3 => 'cloud'
        ];
        
        if (isset($nightIcons[$code])) {
            return $nightIcons[$code];
        }
    }
    
    // Day/general icons
    $icons = [
        0 => $cloudCover < 20 ? 'sun' : 'cloud-sun',
        1 => 'cloud-sun',
        2 => 'cloud-sun',
        3 => 'cloud',
        45 => 'smog',
        48 => 'smog',
        51 => 'cloud-drizzle',
        53 => 'cloud-drizzle',
        55 => 'cloud-drizzle',
        56 => 'cloud-drizzle',
        57 => 'cloud-drizzle',
        61 => 'cloud-rain',
        63 => 'cloud-rain',
        65 => 'cloud-showers-heavy',
        66 => 'cloud-rain',
        67 => 'cloud-showers-heavy',
        71 => 'snowflake',
        73 => 'snowflake',
        75 => 'snowflake',
        77 => 'snowflake',
        80 => 'cloud-rain',
        81 => 'cloud-rain',
        82 => 'cloud-showers-heavy',
        85 => 'snowflake',
        86 => 'snowflake',
        95 => 'cloud-bolt',
        96 => 'cloud-bolt',
        99 => 'cloud-bolt'
    ];
    
    return $icons[$code] ?? 'cloud-question';
}

?>