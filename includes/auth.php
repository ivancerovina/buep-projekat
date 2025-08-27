<?php
// Authentication functions

// Prevent direct access
if (!defined('APP_RUNNING')) {
    http_response_code(403);
    die('Direct access not permitted');
}

class Auth {
    
    // Start secure session
    public static function startSecureSession() {
        if (session_status() === PHP_SESSION_NONE) {
            // Session configuration
            ini_set('session.use_only_cookies', 1);
            ini_set('session.use_strict_mode', 1);
            ini_set('session.cookie_httponly', 1);
            ini_set('session.cookie_secure', SECURE_COOKIE ? 1 : 0);
            ini_set('session.cookie_samesite', SAMESITE_COOKIE);
            ini_set('session.gc_maxlifetime', SESSION_LIFETIME);
            
            // Set session name
            session_name('FUEL_TRACKER_SESSION');
            
            // Start session
            session_start();
            
            // Regenerate session ID periodically (preserve CSRF token)
            if (!isset($_SESSION['last_regeneration'])) {
                $_SESSION['last_regeneration'] = time();
            } elseif (time() - $_SESSION['last_regeneration'] > 300) { // 5 minutes
                // Preserve CSRF token during regeneration
                $csrf_token = $_SESSION[CSRF_TOKEN_NAME] ?? null;
                session_regenerate_id(true);
                $_SESSION['last_regeneration'] = time();
                // Restore CSRF token
                if ($csrf_token) {
                    $_SESSION[CSRF_TOKEN_NAME] = $csrf_token;
                }
            }
        }
    }
    
    // Login user
    public static function login($username, $password) {
        $db = Database::getInstance();
        
        // Check rate limiting
        $identifier = 'login_' . Security::getClientIP();
        if (!Security::checkRateLimit($identifier, MAX_LOGIN_ATTEMPTS, LOCKOUT_TIME)) {
            Security::logSecurityEvent('LOGIN_RATE_LIMIT', "Rate limit exceeded for IP: " . Security::getClientIP());
            return ['success' => false, 'message' => 'Too many login attempts. Please try again later.'];
        }
        
        // Validate input
        if (empty($username) || empty($password)) {
            return ['success' => false, 'message' => 'Username and password are required.'];
        }
        
        // Check for SQL injection attempts
        if (Security::detectSQLInjection($username) || Security::detectSQLInjection($password)) {
            Security::logSecurityEvent('SQL_INJECTION_ATTEMPT', "Possible SQL injection in login attempt");
            return ['success' => false, 'message' => 'Invalid input detected.'];
        }
        
        try {
            // Get user from database
            $sql = "SELECT id, username, email, password_hash, role, is_active, failed_login_attempts, locked_until 
                    FROM users WHERE username = :username OR email = :email LIMIT 1";
            
            $user = $db->fetchOne($sql, [':username' => $username, ':email' => $username]);
            
            if (!$user) {
                Security::logSecurityEvent('LOGIN_FAILED', "Invalid username: $username");
                return ['success' => false, 'message' => 'Invalid username or password.'];
            }
            
            // Check if account is locked
            if ($user['locked_until'] && strtotime($user['locked_until']) > time()) {
                Security::logSecurityEvent('LOGIN_LOCKED', "Attempted login to locked account", $user['id']);
                return ['success' => false, 'message' => 'Account is temporarily locked. Please try again later.'];
            }
            
            // Check if account is active
            if (!$user['is_active']) {
                Security::logSecurityEvent('LOGIN_INACTIVE', "Attempted login to inactive account", $user['id']);
                return ['success' => false, 'message' => 'Account is inactive. Please contact administrator.'];
            }
            
            // Verify password
            if (!Security::verifyPassword($password, $user['password_hash'])) {
                // Increment failed login attempts
                $failed_attempts = $user['failed_login_attempts'] + 1;
                $locked_until = null;
                
                if ($failed_attempts >= MAX_LOGIN_ATTEMPTS) {
                    $locked_until = date('Y-m-d H:i:s', time() + LOCKOUT_TIME);
                    Security::logSecurityEvent('ACCOUNT_LOCKED', "Account locked after $failed_attempts failed attempts", $user['id']);
                }
                
                $sql = "UPDATE users SET failed_login_attempts = :attempts, locked_until = :locked 
                        WHERE id = :id";
                $db->execute($sql, [
                    ':attempts' => $failed_attempts,
                    ':locked' => $locked_until,
                    ':id' => $user['id']
                ]);
                
                Security::logSecurityEvent('LOGIN_FAILED', "Invalid password for user: " . $user['username'], $user['id']);
                return ['success' => false, 'message' => 'Invalid username or password.'];
            }
            
            // Successful login - reset failed attempts and update last login
            $sql = "UPDATE users SET failed_login_attempts = 0, locked_until = NULL, last_login = NOW() 
                    WHERE id = :id";
            $db->execute($sql, [':id' => $user['id']]);
            
            // Create session
            self::createUserSession($user);
            
            // Log successful login
            Security::logSecurityEvent('LOGIN_SUCCESS', "User logged in successfully", $user['id']);
            
            return ['success' => true, 'message' => 'Login successful.', 'role' => $user['role']];
            
        } catch (Exception $e) {
            error_log("Login error: " . $e->getMessage() . " | File: " . $e->getFile() . " | Line: " . $e->getLine());
            // In debug mode, show the actual error
            if (defined('DEBUG_MODE') && DEBUG_MODE === true) {
                return ['success' => false, 'message' => 'Login error: ' . $e->getMessage()];
            }
            return ['success' => false, 'message' => 'An error occurred during login.'];
        }
    }
    
    // Create user session
    private static function createUserSession($user) {
        // Ensure session is started and no output has been sent
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        // Only regenerate session ID if headers haven't been sent (preserve CSRF token)
        if (!headers_sent()) {
            // Preserve CSRF token during regeneration
            $csrf_token = $_SESSION[CSRF_TOKEN_NAME] ?? null;
            session_regenerate_id(true);
            // Restore CSRF token
            if ($csrf_token) {
                $_SESSION[CSRF_TOKEN_NAME] = $csrf_token;
            }
        }
        
        // Store user data in session
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['email'] = $user['email'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['logged_in'] = true;
        $_SESSION['login_time'] = time();
        $_SESSION['last_activity'] = time();
        $_SESSION['ip_address'] = Security::getClientIP();
        $_SESSION['user_agent'] = Security::getUserAgent();
        
        // Store session in database for better tracking
        try {
            $db = Database::getInstance();
            $session_id = session_id();
            $expires_at = date('Y-m-d H:i:s', time() + SESSION_LIFETIME);
            
            $sql = "INSERT INTO user_sessions (user_id, session_id, ip_address, user_agent, expires_at) 
                    VALUES (:user_id, :session_id, :ip, :user_agent, :expires)";
            
            $db->execute($sql, [
                ':user_id' => $user['id'],
                ':session_id' => $session_id,
                ':ip' => Security::getClientIP(),
                ':user_agent' => Security::getUserAgent(),
                ':expires' => $expires_at
            ]);
        } catch (Exception $e) {
            error_log("Failed to store session: " . $e->getMessage());
        }
    }
    
    // Check if user is logged in
    public static function isLoggedIn() {
        if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
            return false;
        }
        
        // Check session timeout
        if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > SESSION_LIFETIME) {
            self::logout();
            return false;
        }
        
        // Check if IP or User Agent changed (possible session hijacking)
        if ($_SESSION['ip_address'] !== Security::getClientIP() || 
            $_SESSION['user_agent'] !== Security::getUserAgent()) {
            Security::logSecurityEvent('SESSION_HIJACK_ATTEMPT', "Session hijacking detected", $_SESSION['user_id']);
            self::logout();
            return false;
        }
        
        // Update last activity
        $_SESSION['last_activity'] = time();
        
        return true;
    }
    
    // Check if user has specific role
    public static function hasRole($role) {
        return self::isLoggedIn() && $_SESSION['role'] === $role;
    }
    
    // Check if user is admin
    public static function isAdmin() {
        return self::hasRole('admin');
    }
    
    // Check if user is manager
    public static function isManager() {
        return self::hasRole('manager');
    }
    
    // Check if user is employee
    public static function isEmployee() {
        return self::hasRole('employee');
    }
    
    // Get current user ID
    public static function getUserId() {
        return $_SESSION['user_id'] ?? null;
    }
    
    // Get current user data
    public static function getCurrentUser() {
        if (!self::isLoggedIn()) {
            return null;
        }
        
        return [
            'id' => $_SESSION['user_id'],
            'username' => $_SESSION['username'],
            'email' => $_SESSION['email'],
            'role' => $_SESSION['role']
        ];
    }
    
    // Logout user
    public static function logout() {
        if (isset($_SESSION['user_id'])) {
            // Remove session from database
            try {
                $db = Database::getInstance();
                $sql = "DELETE FROM user_sessions WHERE session_id = :session_id";
                $db->execute($sql, [':session_id' => session_id()]);
                
                Security::logSecurityEvent('LOGOUT', "User logged out", $_SESSION['user_id']);
            } catch (Exception $e) {
                error_log("Failed to remove session: " . $e->getMessage());
            }
        }
        
        // Destroy session
        $_SESSION = [];
        
        // Delete session cookie
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params["path"], $params["domain"],
                $params["secure"], $params["httponly"]
            );
        }
        
        // Destroy session
        session_destroy();
    }
    
    // Require login - redirect if not logged in
    public static function requireLogin() {
        if (!self::isLoggedIn()) {
            header('Location: /login.php');
            exit();
        }
    }
    
    // Require specific role
    public static function requireRole($role) {
        self::requireLogin();
        
        if (!self::hasRole($role)) {
            Security::logSecurityEvent('UNAUTHORIZED_ACCESS', "Attempted to access restricted area", self::getUserId());
            http_response_code(403);
            die('Access denied. Insufficient permissions.');
        }
    }
    
    // Require admin role
    public static function requireAdmin() {
        self::requireRole('admin');
    }
    
    // Clean expired sessions
    public static function cleanExpiredSessions() {
        try {
            $db = Database::getInstance();
            $sql = "DELETE FROM user_sessions WHERE expires_at < NOW()";
            $db->execute($sql);
        } catch (Exception $e) {
            error_log("Failed to clean expired sessions: " . $e->getMessage());
        }
    }
}