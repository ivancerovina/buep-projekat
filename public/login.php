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
$username = '';

// Handle login form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify CSRF token
    if (!isset($_POST[CSRF_TOKEN_NAME]) || !Security::verifyCSRFToken($_POST[CSRF_TOKEN_NAME])) {
        $errors[] = "Invalid security token. Please refresh and try again.";
    } else {
        // Get input - don't sanitize username/password as it may alter the actual credentials
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        
        // Validate input
        if (empty($username)) {
            $errors[] = "Username or email is required.";
        }
        if (empty($password)) {
            $errors[] = "Password is required.";

        }
        
        // Attempt login if no validation errors
        if (empty($errors)) {
            $result = Auth::login($username, $password);
            
            if ($result['success']) {
                // Redirect based on role
                switch ($result['role']) {
                    case 'admin':
                        redirect('/buep-projekat/public/admin/dashboard.php');
                        break;
                    case 'manager':
                        redirect('/buep-projekat/public/manager/dashboard.php');
                        break;
                    default:
                        redirect('/buep-projekat/public/dashboard.php');
                }
            } else {
                $errors[] = $result['message'];
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
    <title>Login - <?php echo APP_NAME; ?></title>
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
        
        .login-container {
            background: white;
            border-radius: 10px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            width: 100%;
            max-width: 400px;
        }
        
        .login-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 30px;
            text-align: center;
            color: white;
        }
        
        .login-header h1 {
            font-size: 24px;
            margin-bottom: 10px;
        }
        
        .login-header p {
            font-size: 14px;
            opacity: 0.9;
        }
        
        .login-form {
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
        
        .security-notice {
            background: #f0f4ff;
            border: 1px solid #d0d9ff;
            padding: 10px;
            border-radius: 5px;
            margin-top: 20px;
            font-size: 12px;
            color: #666;
            text-align: center;
        }
        
        .security-notice svg {
            width: 16px;
            height: 16px;
            vertical-align: middle;
            margin-right: 5px;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-header">
            <h1><?php echo APP_NAME; ?></h1>
            <p>Secure Login Portal</p>
        </div>
        
        <form class="login-form" method="POST" action="" autocomplete="off">
            <?php if (!empty($errors)): ?>
                <div class="error-messages">
                    <ul>
                        <?php foreach ($errors as $error): ?>
                            <li><?php echo Security::sanitizeOutput($error); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
            
            <div class="form-group">
                <label for="username">Username or Email</label>
                <input type="text" 
                       id="username" 
                       name="username" 
                       value="<?php echo Security::sanitizeOutput($username); ?>"
                       required 
                       autocomplete="username"
                       maxlength="100">
            </div>
            
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" 
                       id="password" 
                       name="password" 
                       required 
                       autocomplete="current-password">
            </div>
            
            <?php echo Security::getCSRFTokenField(); ?>
            
            <button type="submit" class="btn">Sign In</button>
            
            <div class="form-footer">
                <a href="forgot-password.php">Forgot your password?</a>
            </div>
            
            <div class="security-notice">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                </svg>
                Your connection is secure and encrypted
            </div>
        </form>
    </div>
    
    <script>
        // Prevent form resubmission on page refresh
        if (window.history.replaceState) {
            window.history.replaceState(null, null, window.location.href);
        }
    </script>
</body>
</html>