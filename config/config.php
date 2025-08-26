<?php
// Main configuration file

// Prevent direct access
if (!defined('APP_RUNNING')) {
    define('APP_RUNNING', true);
}

// Environment settings
define('DEBUG_MODE', true); // Set to false in production
define('APP_NAME', 'Fuel Expense Tracker');
define('APP_URL', 'http://localhost/buep-projekat');
define('APP_ROOT', dirname(__DIR__));

// Security settings
define('SESSION_LIFETIME', 1800); // 30 minutes
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOCKOUT_TIME', 60); // 1 minute in seconds (for testing)
define('PASSWORD_RESET_TOKEN_VALIDITY', 3600); // 1 hour
define('CSRF_TOKEN_NAME', 'csrf_token');
define('SECURE_COOKIE', false); // Set to true when using HTTPS
define('HTTPONLY_COOKIE', true);
define('SAMESITE_COOKIE', 'Strict');

// Password requirements
define('MIN_PASSWORD_LENGTH', 8);
define('REQUIRE_UPPERCASE', true);
define('REQUIRE_LOWERCASE', true);
define('REQUIRE_NUMBERS', true);
define('REQUIRE_SPECIAL_CHARS', true);

// Email settings (for password reset)
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_USERNAME', 'your-email@gmail.com');
define('SMTP_PASSWORD', 'your-app-password');
define('SMTP_FROM_EMAIL', 'noreply@fueltracker.com');
define('SMTP_FROM_NAME', 'Fuel Tracker System');

// Logging settings
define('LOG_PATH', APP_ROOT . '/logs/');
define('LOG_SECURITY_EVENTS', true);
define('LOG_FILE_PERMISSION', 0644);

// Timezone
date_default_timezone_set('Europe/Belgrade');

// Error reporting
if (DEBUG_MODE) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
    ini_set('log_errors', 1);
    ini_set('error_log', LOG_PATH . 'php_errors.log');
}

// Security headers function
function setSecurityHeaders() {
    // Prevent clickjacking
    header('X-Frame-Options: DENY');
    
    // Prevent MIME type sniffing
    header('X-Content-Type-Options: nosniff');
    
    // Enable XSS protection
    header('X-XSS-Protection: 1; mode=block');
    
    // Referrer policy
    header('Referrer-Policy: strict-origin-when-cross-origin');
    
    // Content Security Policy
    header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline'; img-src 'self' data:; font-src 'self'; connect-src 'self'; frame-ancestors 'none';");
    
    // Strict Transport Security (only for HTTPS)
    if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    }
    
    // Remove PHP version header
    header_remove('X-Powered-By');
}

// Auto-load includes
require_once APP_ROOT . '/config/database.php';
require_once APP_ROOT . '/includes/security.php';
require_once APP_ROOT . '/includes/functions.php';
require_once APP_ROOT . '/includes/validation.php';
require_once APP_ROOT . '/includes/auth.php';