<?php
// Security functions for the application

// Prevent direct access
if (!defined('APP_RUNNING')) {
    http_response_code(403);
    die('Direct access not permitted');
}

class Security {
    
    // Hash password using bcrypt
    public static function hashPassword($password) {
        return password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
    }
    
    // Verify password against hash
    public static function verifyPassword($password, $hash) {
        return password_verify($password, $hash);
    }
    
    // Generate CSRF token
    public static function generateCSRFToken() {
        if (!isset($_SESSION[CSRF_TOKEN_NAME])) {
            $_SESSION[CSRF_TOKEN_NAME] = bin2hex(random_bytes(32));
        }
        return $_SESSION[CSRF_TOKEN_NAME];
    }
    
    // Verify CSRF token
    public static function verifyCSRFToken($token) {
        if (!isset($_SESSION[CSRF_TOKEN_NAME])) {
            return false;
        }
        return hash_equals($_SESSION[CSRF_TOKEN_NAME], $token);
    }
    
    // Get CSRF token field HTML
    public static function getCSRFTokenField() {
        $token = self::generateCSRFToken();
        return '<input type="hidden" name="' . CSRF_TOKEN_NAME . '" value="' . htmlspecialchars($token) . '">';
    }
    
    // Sanitize input data
    public static function sanitizeInput($data) {
        $data = trim($data);
        $data = stripslashes($data);
        $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
        return $data;
    }
    
    // Sanitize output for HTML
    public static function sanitizeOutput($data) {
        return htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    }
    
    // Validate password strength
    public static function validatePasswordStrength($password) {
        $errors = [];
        
        if (strlen($password) < MIN_PASSWORD_LENGTH) {
            $errors[] = "Password must be at least " . MIN_PASSWORD_LENGTH . " characters long";
        }
        
        if (REQUIRE_UPPERCASE && !preg_match('/[A-Z]/', $password)) {
            $errors[] = "Password must contain at least one uppercase letter";
        }
        
        if (REQUIRE_LOWERCASE && !preg_match('/[a-z]/', $password)) {
            $errors[] = "Password must contain at least one lowercase letter";
        }
        
        if (REQUIRE_NUMBERS && !preg_match('/[0-9]/', $password)) {
            $errors[] = "Password must contain at least one number";
        }
        
        if (REQUIRE_SPECIAL_CHARS && !preg_match('/[!@#$%^&*(),.?":{}|<>]/', $password)) {
            $errors[] = "Password must contain at least one special character";
        }
        
        return $errors;
    }
    
    // Generate secure random token
    public static function generateSecureToken($length = 32) {
        return bin2hex(random_bytes($length));
    }
    
    // Get client IP address
    public static function getClientIP() {
        $ip_keys = ['HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'];
        foreach ($ip_keys as $key) {
            if (array_key_exists($key, $_SERVER) === true) {
                $ip = trim($_SERVER[$key]);
                if (filter_var($ip, FILTER_VALIDATE_IP, 
                    FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false) {
                    return $ip;
                }
            }
        }
        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }
    
    // Get user agent
    public static function getUserAgent() {
        return $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
    }
    
    // Rate limiting check
    public static function checkRateLimit($identifier, $max_attempts = 5, $time_window = 300) {
        $cache_file = LOG_PATH . 'rate_limit_' . md5($identifier) . '.tmp';
        
        $attempts = [];
        if (file_exists($cache_file)) {
            $attempts = json_decode(file_get_contents($cache_file), true) ?? [];
        }
        
        // Remove old attempts outside the time window
        $current_time = time();
        $attempts = array_filter($attempts, function($timestamp) use ($current_time, $time_window) {
            return ($current_time - $timestamp) < $time_window;
        });
        
        if (count($attempts) >= $max_attempts) {
            return false; // Rate limit exceeded
        }
        
        // Add current attempt
        $attempts[] = $current_time;
        file_put_contents($cache_file, json_encode($attempts));
        
        return true; // Within rate limit
    }
    
    // Log security event
    public static function logSecurityEvent($event_type, $description, $user_id = null) {
        if (!LOG_SECURITY_EVENTS) {
            return;
        }
        
        try {
            $db = Database::getInstance();
            $sql = "INSERT INTO security_logs (user_id, event_type, event_description, ip_address, user_agent) 
                    VALUES (:user_id, :event_type, :description, :ip, :user_agent)";
            
            $params = [
                ':user_id' => $user_id,
                ':event_type' => $event_type,
                ':description' => $description,
                ':ip' => self::getClientIP(),
                ':user_agent' => self::getUserAgent()
            ];
            
            $db->execute($sql, $params);
            
            // Also write to file log
            $log_message = date('Y-m-d H:i:s') . " | " . $event_type . " | " . 
                          ($user_id ? "User: $user_id | " : "") . 
                          $description . " | IP: " . self::getClientIP() . PHP_EOL;
            
            $log_file = LOG_PATH . 'security_' . date('Y-m-d') . '.log';
            file_put_contents($log_file, $log_message, FILE_APPEND | LOCK_EX);
            chmod($log_file, LOG_FILE_PERMISSION);
            
        } catch (Exception $e) {
            error_log("Failed to log security event: " . $e->getMessage());
        }
    }
    
    // Validate email format
    public static function validateEmail($email) {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }
    
    // Generate safe filename
    public static function sanitizeFilename($filename) {
        // Remove any directory traversal attempts
        $filename = basename($filename);
        // Remove special characters
        $filename = preg_replace('/[^a-zA-Z0-9._-]/', '', $filename);
        return $filename;
    }
    
    // Check for SQL injection patterns (additional layer of security)
    public static function detectSQLInjection($input) {
        $patterns = [
            '/(\bunion\b.*\bselect\b|\bselect\b.*\bunion\b)/i',
            '/(\bdrop\b.*\btable\b|\bdelete\b.*\bfrom\b)/i',
            '/(\binsert\b.*\binto\b|\bupdate\b.*\bset\b)/i',
            '/(\bscript\b.*\b\/script\b)/i',
            '/(--|\#|\/\*|\*\/)/i'
        ];
        
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $input)) {
                return true;
            }
        }
        return false;
    }
}