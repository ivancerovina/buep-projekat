<?php
define('APP_RUNNING', true);
require_once '../config/config.php';

// Set security headers
setSecurityHeaders();

// Start session
Auth::startSecureSession();

// Redirect if already logged in
if (Auth::isLoggedIn()) {
    redirect('/dashboard.php');
}

$errors = [];
$success = false;
$token_valid = false;
$token = '';

// Check if token is provided
if (isset($_GET['token'])) {
    $token = Security::sanitizeInput($_GET['token']);
    
    try {
        $db = Database::getInstance();
        
        // Verify token
        $sql = "SELECT prt.*, u.username, u.email, u.first_name 
                FROM password_reset_tokens prt 
                JOIN users u ON prt.user_id = u.id 
                WHERE prt.token = :token 
                AND prt.expires_at > NOW() 
                AND prt.used = 0 
                LIMIT 1";
        
        $reset_data = $db->fetchOne($sql, [':token' => $token]);
        
        if ($reset_data) {
            $token_valid = true;
            
            // Handle password reset form submission
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                // Verify CSRF token
                if (!isset($_POST[CSRF_TOKEN_NAME]) || !Security::verifyCSRFToken($_POST[CSRF_TOKEN_NAME])) {
                    $errors[] = "Invalid security token. Please refresh and try again.";
                } else {
                    $new_password = $_POST['new_password'] ?? '';
                    $confirm_password = $_POST['confirm_password'] ?? '';
                    
                    // Validate passwords
                    if (empty($new_password)) {
                        $errors[] = "New password is required.";
                    } else {
                        // Check password strength
                        $password_errors = Security::validatePasswordStrength($new_password);
                        if (!empty($password_errors)) {
                            $errors = array_merge($errors, $password_errors);
                        }
                    }
                    
                    if (empty($confirm_password)) {
                        $errors[] = "Password confirmation is required.";
                    } elseif ($new_password !== $confirm_password) {
                        $errors[] = "Passwords do not match.";
                    }
                    
                    // Reset password if no errors
                    if (empty($errors)) {
                        try {
                            $db->beginTransaction();
                            
                            // Hash new password
                            $password_hash = Security::hashPassword($new_password);
                            
                            // Update user password
                            $sql = "UPDATE users SET password_hash = :password, 
                                    failed_login_attempts = 0, locked_until = NULL 
                                    WHERE id = :user_id";
                            $db->execute($sql, [
                                ':password' => $password_hash,
                                ':user_id' => $reset_data['user_id']
                            ]);
                            
                            // Mark token as used
                            $sql = "UPDATE password_reset_tokens SET used = 1 WHERE id = :id";
                            $db->execute($sql, [':id' => $reset_data['id']]);
                            
                            // Invalidate all other reset tokens for this user
                            $sql = "UPDATE password_reset_tokens SET used = 1 
                                    WHERE user_id = :user_id AND id != :id";
                            $db->execute($sql, [
                                ':user_id' => $reset_data['user_id'],
                                ':id' => $reset_data['id']
                            ]);
                            
                            $db->commit();
                            
                            // Log the event
                            Security::logSecurityEvent('PASSWORD_RESET_SUCCESS', 
                                "Password successfully reset for user: " . $reset_data['username'], 
                                $reset_data['user_id']);
                            
                            $success = true;
                            
                        } catch (Exception $e) {
                            $db->rollback();
                            error_log("Password reset error: " . $e->getMessage());
                            $errors[] = "An error occurred. Please try again.";
                        }
                    }
                }
            }
        } else {
            Security::logSecurityEvent('PASSWORD_RESET_INVALID_TOKEN', "Invalid or expired token used: $token");
        }
        
    } catch (Exception $e) {
        error_log("Token verification error: " . $e->getMessage());
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
    <title>Reset Password - <?php echo APP_NAME; ?></title>
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
            margin: 2px 0;
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
        
        .password-requirements {
            background: #f9f9f9;
            border: 1px solid #e0e0e0;
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 20px;
            font-size: 12px;
            color: #666;
        }
        
        .password-requirements h4 {
            margin-bottom: 8px;
            color: #333;
            font-size: 13px;
        }
        
        .password-requirements ul {
            list-style: none;
            margin: 0;
        }
        
        .password-requirements li {
            padding: 2px 0;
            padding-left: 20px;
            position: relative;
        }
        
        .password-requirements li:before {
            content: "✓";
            position: absolute;
            left: 0;
            color: #999;
        }
        
        .error-box {
            background: #fff3cd;
            border: 1px solid #ffc107;
            color: #856404;
            padding: 15px;
            border-radius: 5px;
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Reset Your Password</h1>
            <p>Create a new secure password</p>
        </div>
        
        <div class="form-container">
            <?php if ($success): ?>
                <div class="success-message">
                    <h3>Password Reset Successful!</h3>
                    <p>Your password has been successfully reset.</p>
                    <p>You can now login with your new password.</p>
                </div>
                <div class="form-footer">
                    <a href="login.php">Go to Login</a>
                </div>
            <?php elseif ($token_valid): ?>
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
                    
                    <div class="password-requirements">
                        <h4>Password Requirements:</h4>
                        <ul>
                            <li>At least <?php echo MIN_PASSWORD_LENGTH; ?> characters long</li>
                            <li>Contains uppercase and lowercase letters</li>
                            <li>Contains at least one number</li>
                            <li>Contains at least one special character</li>
                        </ul>
                    </div>
                    
                    <div class="form-group">
                        <label for="new_password">New Password</label>
                        <input type="password" 
                               id="new_password" 
                               name="new_password" 
                               required 
                               autocomplete="new-password">
                    </div>
                    
                    <div class="form-group">
                        <label for="confirm_password">Confirm New Password</label>
                        <input type="password" 
                               id="confirm_password" 
                               name="confirm_password" 
                               required 
                               autocomplete="new-password">
                    </div>
                    
                    <input type="hidden" name="token" value="<?php echo Security::sanitizeOutput($token); ?>">
                    <?php echo Security::getCSRFTokenField(); ?>
                    
                    <button type="submit" class="btn">Reset Password</button>
                    
                    <div class="form-footer">
                        <a href="login.php">Back to Login</a>
                    </div>
                </form>
            <?php else: ?>
                <div class="error-box">
                    <h3>Invalid or Expired Link</h3>
                    <p>This password reset link is invalid or has expired.</p>
                    <p>Please request a new password reset.</p>
                </div>
                <div class="form-footer">
                    <a href="forgot-password.php">Request New Reset Link</a> | 
                    <a href="login.php">Back to Login</a>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <script>
        // Password strength indicator
        document.addEventListener('DOMContentLoaded', function() {
            const passwordInput = document.getElementById('new_password');
            const confirmInput = document.getElementById('confirm_password');
            
            if (passwordInput && confirmInput) {
                // Check password match in real-time
                confirmInput.addEventListener('input', function() {
                    if (this.value && passwordInput.value !== this.value) {
                        this.style.borderColor = '#fcc';
                    } else if (this.value && passwordInput.value === this.value) {
                        this.style.borderColor = '#cfc';
                    } else {
                        this.style.borderColor = '#ddd';
                    }
                });
            }
        });
    </script>
</body>
</html>