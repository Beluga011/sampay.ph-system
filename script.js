// DOM Elements
const checkWeatherBtn = document.getElementById('check-weather-btn');
const subscribeBtn = document.getElementById('subscribe-btn');
const autoLocationOption = document.getElementById('auto-location');
const manualLocationOption = document.getElementById('manual-location');
const manualInputContainer = document.getElementById('manual-input-container');
const locationInput = document.getElementById('location-input');
const locationSubmit = document.getElementById('location-submit');
const subscriptionForm = document.getElementById('subscription-form');
const pushModal = document.getElementById('push-modal');
const allowPushBtn = document.getElementById('allow-push');
const denyPushBtn = document.getElementById('deny-push');
const successMessage = document.getElementById('success-message');
const toastText = document.getElementById('toast-text');
const hamburger = document.querySelector('.hamburger');
const navMenu = document.querySelector('.nav-menu');
const locationDisplay = document.getElementById('location-display');

// Weather display elements
const tempValue = document.getElementById('temp-value');
const humidityValue = document.getElementById('humidity-value');
const precipValue = document.getElementById('precip-value');
const windValue = document.getElementById('wind-value');
const weatherMainIcon = document.getElementById('weather-main-icon');
const recommendationBadge = document.getElementById('recommendation-badge');
const recommendationDetail = document.getElementById('recommendation-detail');

// App State
let userLocation = null;
let isSubscribed = false;
let currentWeatherData = null;
let locationPermissionDenied = false;

// Initialize the app
document.addEventListener('DOMContentLoaded', function() {
    // Set up event listeners
    setupEventListeners();
    
    // Check if geolocation is supported and not previously denied
    if (!locationPermissionDenied && navigator.geolocation) {
        // Try to get user's location automatically with a timeout
        getLocationWithTimeout();
    } else {
        // Default to manual location input
        setTimeout(() => {
            if (!userLocation) {
                showToast('Please enter your location manually for weather data', 'info');
                toggleManualLocation();
            }
        }, 1000);
    }
    
    // Add scroll effect to header
    window.addEventListener('scroll', handleHeaderScroll);
    
    // Set default manual location to a major city
    setDefaultLocation();
});

// Set up all event listeners
function setupEventListeners() {
    // Location selection
    autoLocationOption.addEventListener('click', toggleAutoLocation);
    manualLocationOption.addEventListener('click', toggleManualLocation);
    locationSubmit.addEventListener('click', submitManualLocation);
    locationInput.addEventListener('keypress', function(e) {
        if (e.key === 'Enter') {
            submitManualLocation();
        }
    });
    
    // Buttons
    checkWeatherBtn.addEventListener('click', checkWeather);
    subscribeBtn.addEventListener('click', showSubscriptionForm);
    
    // Form submission
    subscriptionForm.addEventListener('submit', handleSubscription);
    
    // Push notification modal
    allowPushBtn.addEventListener('click', allowPushNotifications);
    denyPushBtn.addEventListener('click', denyPushNotifications);
    
    // Mobile menu
    hamburger.addEventListener('click', toggleMobileMenu);
    
    // Close mobile menu when clicking on a link
    document.querySelectorAll('.nav-menu a').forEach(link => {
        link.addEventListener('click', closeMobileMenu);
    });
}

// Handle header scroll effect
function handleHeaderScroll() {
    const header = document.querySelector('.header');
    if (window.scrollY > 50) {
        header.style.boxShadow = '0 4px 20px rgba(0, 0, 0, 0.1)';
        header.style.background = 'rgba(255, 255, 255, 0.98)';
    } else {
        header.style.boxShadow = '0 1px 10px rgba(0, 0, 0, 0.05)';
        header.style.background = 'rgba(255, 255, 255, 0.95)';
    }
}

// Set default location based on user's language or timezone
function setDefaultLocation() {
    const timezone = Intl.DateTimeFormat().resolvedOptions().timeZone;
    let defaultCity = 'New York'; // Fallback default
    
    // Map timezones to major cities
    const timezoneMap = {
        'America/New_York': 'New York',
        'America/Chicago': 'Chicago',
        'America/Denver': 'Denver',
        'America/Los_Angeles': 'Los Angeles',
        'Europe/London': 'London',
        'Europe/Paris': 'Paris',
        'Europe/Berlin': 'Berlin',
        'Asia/Tokyo': 'Tokyo',
        'Asia/Shanghai': 'Shanghai',
        'Australia/Sydney': 'Sydney'
    };
    
    if (timezoneMap[timezone]) {
        defaultCity = timezoneMap[timezone];
    }
    
    // Set the default in the manual input
    locationInput.placeholder = `e.g., ${defaultCity}`;
}

// Toggle between auto and manual location
function toggleAutoLocation() {
    autoLocationOption.classList.add('active');
    manualLocationOption.classList.remove('active');
    manualInputContainer.classList.add('hidden');
    
    // Reset location permission flag and try again
    locationPermissionDenied = false;
    getLocationWithTimeout();
}

function toggleManualLocation() {
    manualLocationOption.classList.add('active');
    autoLocationOption.classList.remove('active');
    manualInputContainer.classList.remove('hidden');
    locationInput.focus();
}

function submitManualLocation() {
    const location = locationInput.value.trim();
    if (location) {
        userLocation = location;
        locationDisplay.textContent = location;
        showToast(`Location set to: ${location}`, 'success');
        checkWeather();
    } else {
        showToast('Please enter a location', 'warning');
    }
}

// Get user's location with timeout
function getLocationWithTimeout() {
    if (!navigator.geolocation) {
        showToast('Geolocation is not supported by your browser', 'error');
        toggleManualLocation();
        return;
    }
    
    const options = {
        enableHighAccuracy: false, // Faster response
        timeout: 10000, // 10 seconds
        maximumAge: 300000 // 5 minutes cache
    };
    
    // Show loading state
    locationDisplay.textContent = "Detecting location...";
    
    navigator.geolocation.getCurrentPosition(
        position => {
            const lat = position.coords.latitude;
            const lon = position.coords.longitude;
            userLocation = { lat, lon };
            locationDisplay.textContent = "Your Location";
            showToast('Location detected successfully!', 'success');
            checkWeather();
        },
        error => {
            handleLocationError(error);
        },
        options
    );
}

// Handle geolocation errors with specific messages
function handleLocationError(error) {
    console.error('Location error:', error);
    locationPermissionDenied = true;
    
    let errorMessage = 'Unable to get your location. ';
    
    switch(error.code) {
        case error.PERMISSION_DENIED:
            errorMessage += 'Location access was denied. Please enable location permissions or enter your location manually.';
            break;
        case error.POSITION_UNAVAILABLE:
            errorMessage += 'Location information is unavailable. Please enter your location manually.';
            break;
        case error.TIMEOUT:
            errorMessage += 'Location request timed out. Please enter your location manually.';
            break;
        default:
            errorMessage += 'Please enter your location manually.';
            break;
    }
    
    showToast(errorMessage, 'warning');
    toggleManualLocation();
    
    // Try to get location from IP as fallback
    getLocationFromIP();
}

// Get approximate location from IP address as fallback
async function getLocationFromIP() {
    try {
        const response = await fetch('https://ipapi.co/json/');
        const data = await response.json();
        
        if (data && data.city) {
            userLocation = data.city;
            locationDisplay.textContent = data.city;
            showToast(`Using approximate location: ${data.city}`, 'info');
            
            // Update the manual input with the detected city
            locationInput.value = data.city;
            
            // Auto-check weather for this location
            setTimeout(() => checkWeather(), 1000);
        }
    } catch (ipError) {
        console.log('IP-based location failed, using manual input');
        // Keep manual location as fallback
    }
}

// Check weather for current location
async function checkWeather() {
    if (!userLocation) {
        showToast('Please set your location first', 'warning');
        return;
    }
    
    try {
        // Show loading state
        checkWeatherBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Checking Weather...';
        checkWeatherBtn.disabled = true;
        
        // Update weather card to show loading
        tempValue.textContent = '--';
        humidityValue.textContent = '--';
        precipValue.textContent = '--';
        windValue.textContent = '--';
        weatherMainIcon.className = 'fas fa-spinner fa-spin';
        recommendationBadge.innerHTML = `
            <div class="badge-icon">
                <i class="fas fa-spinner fa-spin"></i>
            </div>
            <span class="badge-text">Checking Weather...</span>
        `;
        recommendationDetail.textContent = 'Fetching current weather conditions...';
        
        // Call our backend API
        const weatherData = await fetchWeatherFromAPI(userLocation);
        currentWeatherData = weatherData;
        
        // Update UI with weather data
        updateWeatherDisplay(weatherData);
        
        // Reset button
        checkWeatherBtn.innerHTML = '<i class="fas fa-cloud-sun-rain"></i> Check Weather Now';
        checkWeatherBtn.disabled = false;
        
    } catch (error) {
        console.error('Error fetching weather:', error);
        showToast('Failed to fetch weather data. Please try again.', 'error');
        
        // Reset UI elements
        resetWeatherDisplay();
        
        // Reset button
        checkWeatherBtn.innerHTML = '<i class="fas fa-cloud-sun-rain"></i> Check Weather Now';
        checkWeatherBtn.disabled = false;
    }
}

// Reset weather display to default state
function resetWeatherDisplay() {
    tempValue.textContent = '--';
    humidityValue.textContent = '--';
    precipValue.textContent = '--';
    windValue.textContent = '--';
    weatherMainIcon.className = 'fas fa-question';
    recommendationBadge.innerHTML = `
        <div class="badge-icon">
            <i class="fas fa-exclamation-triangle"></i>
        </div>
        <span class="badge-text">Weather Unavailable</span>
    `;
    recommendationDetail.textContent = 'Unable to fetch weather data. Please check your connection and try again.';
}

// Fetch weather data from our backend
async function fetchWeatherFromAPI(location) {
    try {
        const response = await fetch('backend/weather.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ location })
        });
        
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        
        const data = await response.json();
        
        if (data.success) {
            return data.data;
        } else {
            throw new Error(data.message || 'Unknown API error');
        }
    } catch (error) {
        console.error('API Error:', error);
        // Fallback to simulation
        return simulateWeatherAPI(location);
    }
}

// Simulate weather API call (fallback)
function simulateWeatherAPI(location) {
    return new Promise((resolve) => {
        // Simulate API delay
        setTimeout(() => {
            // Generate realistic weather data based on location type
            let temp, humidity, precipitation, wind;
            
            if (typeof location === 'string') {
                // For manual location (city name)
                const hash = location.split('').reduce((a, b) => {
                    a = ((a << 5) - a) + b.charCodeAt(0);
                    return a & a;
                }, 0);
                
                temp = 15 + (hash % 20);
                humidity = 30 + (hash % 60);
                precipitation = (hash % 100) < 20 ? (hash % 10) / 10 : 0;
                wind = 2 + (hash % 25);
            } else {
                // For coordinates
                temp = 10 + (Math.abs(location.lat) % 25);
                humidity = 40 + (Math.abs(location.lon) % 50);
                precipitation = (Math.abs(location.lat + location.lon)) % 100 < 25 ? 
                    (Math.abs(location.lat + location.lon) % 15) / 10 : 0;
                wind = 5 + (Math.abs(location.lat) % 20);
            }
            
            // Determine weather condition
            let condition, icon;
            if (precipitation > 0.5) {
                condition = 'Rainy';
                icon = 'cloud-rain';
            } else if (precipitation > 0.1) {
                condition = 'Drizzle';
                icon = 'cloud-drizzle';
            } else if (humidity > 80) {
                condition = 'Cloudy';
                icon = 'cloud';
            } else if (temp > 25) {
                condition = 'Sunny';
                icon = 'sun';
            } else {
                condition = 'Partly Cloudy';
                icon = 'cloud-sun';
            }
            
            resolve({
                temperature: Math.round(temp),
                humidity: Math.round(humidity),
                precipitation: Math.round(precipitation * 100) / 100,
                wind: Math.round(wind),
                condition: condition,
                icon: icon,
                city: typeof location === 'string' ? location : 'Current Location'
            });
        }, 1500);
    });
}

// Update weather display with data
function updateWeatherDisplay(weatherData) {
    tempValue.textContent = `${weatherData.temperature}°C`;
    humidityValue.textContent = `${weatherData.humidity}%`;
    precipValue.textContent = `${weatherData.precipitation} mm`;
    windValue.textContent = `${weatherData.wind} km/h`;
    
    // Update weather icon
    weatherMainIcon.className = `fas fa-${weatherData.icon}`;
    
    // Determine recommendation
    const isGoodForDrying = 
        weatherData.temperature >= 18 && 
        weatherData.humidity < 70 && 
        weatherData.precipitation === 0 &&
        weatherData.wind >= 5 && weatherData.wind <= 20;
    
    const isMaybeForDrying = 
        (weatherData.temperature >= 15 && weatherData.temperature < 18) || 
        (weatherData.humidity >= 70 && weatherData.humidity < 80) ||
        (weatherData.precipitation > 0 && weatherData.precipitation < 0.2) ||
        (weatherData.wind < 5 || weatherData.wind > 20);
    
    // Update recommendation badge
    recommendationBadge.className = 'recommendation-badge';
    let badgeIcon, badgeText, detailText;
    
    if (isGoodForDrying) {
        recommendationBadge.classList.add('yes');
        badgeIcon = 'fa-check-circle';
        badgeText = 'Yes - Perfect Drying Conditions!';
        detailText = `Ideal temperature (${weatherData.temperature}°C), low humidity (${weatherData.humidity}%), no rain, and a gentle breeze (${weatherData.wind} km/h) make this a perfect day for drying clothes outside.`;
    } else if (isMaybeForDrying) {
        recommendationBadge.classList.add('maybe');
        badgeIcon = 'fa-info-circle';
        badgeText = 'Maybe - Conditions Are Okay';
        detailText = `Drying is possible but not ideal. ${getMaybeReason(weatherData)} Consider indoor drying if possible.`;
    } else {
        recommendationBadge.classList.add('no');
        badgeIcon = 'fa-times-circle';
        badgeText = 'No - Not Recommended';
        detailText = `Current conditions (${weatherData.condition.toLowerCase()}, ${weatherData.humidity}% humidity) are not suitable for outdoor drying. Your clothes may not dry properly or could get wet.`;
    }
    
    recommendationBadge.innerHTML = `
        <div class="badge-icon">
            <i class="fas ${badgeIcon}"></i>
        </div>
        <span class="badge-text">${badgeText}</span>
    `;
    
    recommendationDetail.textContent = detailText;
}

// Helper function to generate reason for "maybe" recommendation
function getMaybeReason(weatherData) {
    const reasons = [];
    
    if (weatherData.temperature < 18) {
        reasons.push(`temperature is a bit cool (${weatherData.temperature}°C)`);
    }
    
    if (weatherData.humidity >= 70) {
        reasons.push(`humidity is somewhat high (${weatherData.humidity}%)`);
    }
    
    if (weatherData.precipitation > 0) {
        reasons.push(`there's a slight chance of precipitation`);
    }
    
    if (weatherData.wind < 5 || weatherData.wind > 20) {
        reasons.push(`wind conditions are not ideal (${weatherData.wind} km/h)`);
    }
    
    return reasons.length > 0 ? `Because ${reasons.join(' and ')}.` : 'Conditions are borderline for optimal drying.';
}

// Show subscription form (scroll to it)
function showSubscriptionForm() {
    document.getElementById('subscribe').scrollIntoView({ 
        behavior: 'smooth',
        block: 'start'
    });
}

// Handle subscription form submission
async function handleSubscription(e) {
    e.preventDefault();
    
    // Get form data
    const formData = new FormData(subscriptionForm);
    const data = {
        name: formData.get('name'),
        email: formData.get('email'),
        location: formData.get('location'),
        notifications: formData.getAll('notifications')
    };
    
    // Basic validation
    if (!data.name || !data.email || !data.location) {
        showToast('Please fill in all required fields', 'warning');
        return;
    }
    
    // Email validation
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    if (!emailRegex.test(data.email)) {
        showToast('Please enter a valid email address', 'warning');
        return;
    }
    
    try {
        // Show loading state
        const submitBtn = subscriptionForm.querySelector('button[type="submit"]');
        const originalText = submitBtn.innerHTML;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
        submitBtn.disabled = true;

        const response = await fetch('backend/subscribe.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: new URLSearchParams(data)
        });

        // First, get the response as text to check if it's valid JSON
        const responseText = await response.text();
        
        let result;
        try {
            result = JSON.parse(responseText);
        } catch (parseError) {
            console.error('JSON Parse Error:', parseError);
            console.error('Response was:', responseText);
            throw new Error('Server returned an invalid response. Please check the backend configuration.');
        }

        if (result.success) {
            showToast(result.message, 'success');
            subscriptionForm.reset();
            
            // If push notifications were selected, show permission modal
            if (data.notifications.includes('push')) {
                setTimeout(() => {
                    showPushModal();
                }, 1000);
            }
            
            isSubscribed = true;
        } else {
            showToast(result.message, 'error');
        }
        
    } catch (error) {
        console.error('Subscription error:', error);
        if (error.message.includes('backend configuration')) {
            showToast('Server configuration error. Please try again later.', 'error');
        } else if (error.message.includes('Failed to fetch')) {
            showToast('Network error. Please check your connection and try again.', 'error');
        } else {
            showToast('Failed to process subscription. Please try again.', 'error');
        }
    } finally {
        // Reset button state
        const submitBtn = subscriptionForm.querySelector('button[type="submit"]');
        if (submitBtn) {
            submitBtn.innerHTML = '<i class="fas fa-paper-plane"></i> Start Getting Alerts';
            submitBtn.disabled = false;
        }
    }
}

// Show push notification permission modal
function showPushModal() {
    pushModal.classList.remove('hidden');
}

// Handle push notification permission
function allowPushNotifications() {
    if ('Notification' in window && 'serviceWorker' in navigator) {
        Notification.requestPermission().then(permission => {
            if (permission === 'granted') {
                showToast('Push notifications enabled!', 'success');
                // In a real app, you would subscribe to push service here
            } else {
                showToast('Push notifications blocked', 'warning');
            }
            pushModal.classList.add('hidden');
        });
    } else {
        showToast('Push notifications not supported in your browser', 'warning');
        pushModal.classList.add('hidden');
    }
}

function denyPushNotifications() {
    pushModal.classList.add('hidden');
}

// Show toast message
function showToast(message, type = 'success') {
    toastText.textContent = message;
    
    // Set color based on type
    if (type === 'error') {
        successMessage.style.backgroundColor = 'var(--danger)';
    } else if (type === 'warning') {
        successMessage.style.backgroundColor = 'var(--warning)';
    } else if (type === 'info') {
        successMessage.style.backgroundColor = 'var(--primary)';
    } else {
        successMessage.style.backgroundColor = 'var(--success)';
    }
    
    successMessage.classList.remove('hidden');
    
    // Auto hide after 5 seconds
    setTimeout(() => {
        successMessage.classList.add('hidden');
    }, 5000);
}

// Mobile menu functionality
function toggleMobileMenu() {
    hamburger.classList.toggle('active');
    navMenu.classList.toggle('active');
}

function closeMobileMenu() {
    hamburger.classList.remove('active');
    navMenu.classList.remove('active');
}

// Add a function to manually request location permission
function requestLocationPermission() {
    if (!navigator.geolocation) {
        showToast('Geolocation is not supported by your browser', 'error');
        return;
    }
    
    showToast('Please allow location access when prompted', 'info');
    
    navigator.geolocation.getCurrentPosition(
        (position) => {
            const lat = position.coords.latitude;
            const lon = position.coords.longitude;
            userLocation = { lat, lon };
            locationDisplay.textContent = "Your Location";
            locationPermissionDenied = false;
            showToast('Location access granted!', 'success');
            checkWeather();
        },
        (error) => {
            handleLocationError(error);
        },
        { timeout: 10000 }
    );
}

// Add click handler to location display for manual permission request
locationDisplay.addEventListener('click', function() {
    if (locationPermissionDenied) {
        if (confirm('Location access was previously denied. Would you like to try again?')) {
            requestLocationPermission();
        }
    }
});

// Test function to check backend connectivity
async function testBackend() {
    try {
        const response = await fetch('backend/subscribe.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'name=Test&email=test@test.com&location=TestCity&notifications[]=email'
        });
        
        const text = await response.text();
        console.log('Backend test response:', text);
        
        try {
            const json = JSON.parse(text);
            console.log('Backend test JSON:', json);
            return json;
        } catch (e) {
            console.error('Backend returned non-JSON:', text);
            return null;
        }
    } catch (error) {
        console.error('Backend test failed:', error);
        return null;
    }
}