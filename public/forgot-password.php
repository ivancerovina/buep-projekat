<?php
define('APP_RUNNING', true);
require_once '../config/config.php';

// Set security headers
setSecurityHeaders();

// Start session
Auth::startSecureSession();

// Redirect if already logged in
if (Auth::isLoggedIn()) {
    redirect('/buep-projekat/public/dashboard.php');
}

$errors = [];
$success = false;
$email = '';

// Handle password reset request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify CSRF token
    if (!isset($_POST[CSRF_TOKEN_NAME]) || !Security::verifyCSRFToken($_POST[CSRF_TOKEN_NAME])) {
        $errors[] = "Invalid security token. Please refresh and try again.";
    } else {
        // Get and sanitize input
        $email = Security::sanitizeInput($_POST['email'] ?? '');
        
        // Validate email
        if (empty($email)) {
            $errors[] = "Email address is required.";
        } elseif (!Security::validateEmail($email)) {
            $errors[] = "Please enter a valid email address.";
        }
        
        // Check rate limiting for password reset
        $identifier = 'password_reset_' . Security::getClientIP();
        if (!Security::checkRateLimit($identifier, 3, 3600)) { // 3 attempts per hour
            $errors[] = "Too many password reset requests. Please try again later.";
            Security::logSecurityEvent('PASSWORD_RESET_RATE_LIMIT', "Rate limit exceeded for email: $email");
        }
        
        // Process password reset if no errors
        if (empty($errors)) {
            try {
                $db = Database::getInstance();
                
                // Check if user exists
                $sql = "SELECT id, username, first_name FROM users WHERE email = :email AND is_active = 1";
                $user = $db->fetchOne($sql, [':email' => $email]);
                
                if ($user) {
                    // Generate reset token
                    $token = Security::generateSecureToken();
                    $expires_at = date('Y-m-d H:i:s', time() + PASSWORD_RESET_TOKEN_VALIDITY);
                    
                    // Store token in database
                    $sql = "INSERT INTO password_reset_tokens (user_id, token, expires_at) 
                            VALUES (:user_id, :token, :expires_at)";
                    $db->execute($sql, [
                        ':user_id' => $user['id'],
                        ':token' => $token,
                        ':expires_at' => $expires_at
                    ]);
                    
                    // Send reset email
                    $reset_link = APP_URL . "/public/reset-password.php?token=$token";
                    $email_body = "
                        <h2>Password Reset Request</h2>
                        <p>Hello {$user['first_name']},</p>
                        <p>We received a request to reset your password for your Fuel Tracker account.</p>
                        <p>To reset your password, please click the link below:</p>
                        <p><a href='$reset_link'>Reset Password</a></p>
                        <p>Or copy and paste this URL into your browser:</p>
                        <p>$reset_link</p>
                        <p>This link will expire in 1 hour.</p>
                        <p>If you didn't request this password reset, please ignore this email.</p>
                        <p>Best regards,<br>Fuel Tracker Team</p>
                    ";
                    
                    sendEmail($email, 'Password Reset Request', $email_body);
                    
                    // Log the event
                    Security::logSecurityEvent('PASSWORD_RESET_REQUEST', "Password reset requested for email: $email", $user['id']);
                    
                    $success = true;
                } else {
                    // Don't reveal if email exists or not (security)
                    $success = true;
                    Security::logSecurityEvent('PASSWORD_RESET_INVALID', "Password reset requested for non-existent email: $email");
                }
                
            } catch (Exception $e) {
                error_log("Password reset error: " . $e->getMessage());
                $errors[] = "An error occurred. Please try again later.";
            }
        }
    }
}

// Generate CSRF token for form
$csrf_token = Security::generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - <?php echo APP_NAME; ?></title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .container {
            background: white;
            border-radius: 10px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            width: 100%;
            max-width: 400px;
        }
        
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 30px;
            text-align: center;
            color: white;
        }
        
        .header h1 {
            font-size: 24px;
            margin-bottom: 10px;
        }
        
        .header p {
            font-size: 14px;
            opacity: 0.9;
        }
        
        .form-container {
            padding: 30px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 5px;
            color: #333;
            font-weight: 500;
            font-size: 14px;
        }
        
        .form-group input {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
            transition: border-color 0.3s;
        }
        
        .form-group input:focus {
            outline: none;
            border-color: #667eea;
        }
        
        .error-messages {
            background: #fee;
            border: 1px solid #fcc;
            color: #c00;
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        
        .error-messages ul {
            list-style: none;
            margin: 0;
        }
        
        .error-messages li {
            font-size: 14px;
        }
        
        .success-message {
            background: #efe;
            border: 1px solid #cfc;
            color: #060;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            text-align: center;
        }
        
        .success-message h3 {
            margin-bottom: 10px;
        }
        
        .success-message p {
            font-size: 14px;
            margin: 5px 0;
        }
        
        .btn {
            width: 100%;
            padding: 12px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 5px;
            font-size: 16px;
            font-weight: 500;
            cursor: pointer;
            transition: transform 0.2s, box-shadow 0.2s;
        }
        
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(102, 126, 234, 0.4);
        }
        
        .btn:active {
            transform: translateY(0);
        }
        
        .form-footer {
            margin-top: 20px;
            text-align: center;
        }
        
        .form-footer a {
            color: #667eea;
            text-decoration: none;
            font-size: 14px;
            transition: color 0.3s;
        }
        
        .form-footer a:hover {
            color: #764ba2;
            text-decoration: underline;
        }
        
        .info-box {
            background: #f0f4ff;
            border: 1px solid #d0d9ff;
            padding: 12px;
            border-radius: 5px;
            margin-bottom: 20px;
            font-size: 14px;
            color: #666;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Password Reset</h1>
            <p>Recover your account access</p>
        </div>
        
        <div class="form-container">
            <?php if ($success): ?>
                <div class="success-message">
                    <h3>Check Your Email</h3>
                    <p>If an account exists with this email address, you will receive password reset instructions.</p>
                    <p>Please check your inbox and spam folder.</p>
                </div>
                <div class="form-footer">
                    <a href="login.php">Return to Login</a>
                </div>
            <?php else: ?>
                <form method="POST" action="">
                    <?php if (!empty($errors)): ?>
                        <div class="error-messages">
                            <ul>
                                <?php foreach ($errors as $error): ?>
                                    <li><?php echo Security::sanitizeOutput($error); ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>
                    
                    <div class="info-box">
                        Enter your email address and we'll send you instructions to reset your password.
                    </div>
                    
                    <div class="form-group">
                        <label for="email">Email Address</label>
                        <input type="email" 
                               id="email" 
                               name="email" 
                               value="<?php echo Security::sanitizeOutput($email); ?>"
                               required 
                               autocomplete="email"
                               maxlength="100"
                               placeholder="your.email@example.com">
                    </div>
                    
                    <?php echo Security::getCSRFTokenField(); ?>
                    
                    <button type="submit" class="btn">Send Reset Link</button>
                    
                    <div class="form-footer">
                        <a href="login.php">Back to Login</a>
                    </div>
                </form>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>