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
$form_data = [
    'username' => '',
    'email' => '',
    'first_name' => '',
    'last_name' => ''
];

// Handle registration form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify CSRF token
    if (!isset($_POST[CSRF_TOKEN_NAME]) || !Security::verifyCSRFToken($_POST[CSRF_TOKEN_NAME])) {
        $errors[] = "Invalid security token. Please refresh and try again.";
    } else {
        // Get and sanitize input
        $form_data['username'] = Security::sanitizeInput($_POST['username'] ?? '');
        $form_data['email'] = Security::sanitizeInput($_POST['email'] ?? '');
        $form_data['first_name'] = Security::sanitizeInput($_POST['first_name'] ?? '');
        $form_data['last_name'] = Security::sanitizeInput($_POST['last_name'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';
        
        // Validate input
        if (empty($form_data['username'])) {
            $errors[] = "Username is required.";
        } elseif (strlen($form_data['username']) < 3) {
            $errors[] = "Username must be at least 3 characters long.";
        } elseif (!preg_match('/^[a-zA-Z0-9_]+$/', $form_data['username'])) {
            $errors[] = "Username can only contain letters, numbers, and underscores.";
        }
        
        if (empty($form_data['email'])) {
            $errors[] = "Email is required.";
        } elseif (!Security::validateEmail($form_data['email'])) {
            $errors[] = "Invalid email format.";
        }
        
        if (empty($form_data['first_name'])) {
            $errors[] = "First name is required.";
        }
        
        if (empty($form_data['last_name'])) {
            $errors[] = "Last name is required.";
        }
        
        if (empty($password)) {
            $errors[] = "Password is required.";
        } else {
            $password_errors = Security::validatePasswordStrength($password);
            if (!empty($password_errors)) {
                $errors = array_merge($errors, $password_errors);
            }
        }
        
        if ($password !== $confirm_password) {
            $errors[] = "Passwords do not match.";
        }
        
        // Check for SQL injection attempts
        if (Security::detectSQLInjection($form_data['username']) || 
            Security::detectSQLInjection($form_data['email'])) {
            Security::logSecurityEvent('SQL_INJECTION_ATTEMPT', "Possible SQL injection in registration");
            $errors[] = "Invalid input detected.";
        }
        
        // If no errors, proceed with registration
        if (empty($errors)) {
            try {
                $db = Database::getInstance();
                
                // Check if username already exists
                $sql = "SELECT id FROM users WHERE username = :username";
                $existing = $db->fetchOne($sql, [':username' => $form_data['username']]);
                
                if ($existing) {
                    $errors[] = "Username already exists.";
                } else {
                    // Check if email already exists
                    $sql = "SELECT id FROM users WHERE email = :email";
                    $existing = $db->fetchOne($sql, [':email' => $form_data['email']]);
                    
                    if ($existing) {
                        $errors[] = "Email already registered.";
                    } else {
                        // Hash password
                        $password_hash = Security::hashPassword($password);
                        
                        // Insert new user
                        $sql = "INSERT INTO users (username, email, password_hash, first_name, last_name, role) 
                                VALUES (:username, :email, :password_hash, :first_name, :last_name, 'employee')";
                        
                        $db->execute($sql, [
                            ':username' => $form_data['username'],
                            ':email' => $form_data['email'],
                            ':password_hash' => $password_hash,
                            ':first_name' => $form_data['first_name'],
                            ':last_name' => $form_data['last_name']
                        ]);
                        
                        // Log registration
                        Security::logSecurityEvent('REGISTRATION_SUCCESS', "New user registered: " . $form_data['username']);
                        
                        $success = true;
                        
                        // Clear form data
                        $form_data = [
                            'username' => '',
                            'email' => '',
                            'first_name' => '',
                            'last_name' => ''
                        ];
                    }
                }
            } catch (Exception $e) {
                error_log("Registration error: " . $e->getMessage());
                $errors[] = "An error occurred during registration. Please try again.";
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
    <title>Register - <?php echo APP_NAME; ?></title>
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
        
        .register-container {
            background: white;
            border-radius: 10px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            width: 100%;
            max-width: 500px;
        }
        
        .register-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 30px;
            text-align: center;
            color: white;
        }
        
        .register-header h1 {
            font-size: 24px;
            margin-bottom: 10px;
        }
        
        .register-header p {
            font-size: 14px;
            opacity: 0.9;
        }
        
        .register-form {
            padding: 30px;
        }
        
        .form-row {
            display: flex;
            gap: 15px;
        }
        
        .form-group {
            margin-bottom: 20px;
            flex: 1;
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
            margin-bottom: 5px;
        }
        
        .error-messages li:last-child {
            margin-bottom: 0;
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
            margin-top: 5px;
            font-size: 12px;
            color: #666;
        }
        
        .password-requirements ul {
            list-style: none;
            margin: 0;
        }
        
        .password-requirements li {
            margin-bottom: 3px;
        }
        
        .password-requirements li:before {
            content: "✓ ";
            color: #4CAF50;
        }
    </style>
</head>
<body>
    <div class="register-container">
        <div class="register-header">
            <h1><?php echo APP_NAME; ?></h1>
            <p>Create Your Account</p>
        </div>
        
        <form class="register-form" method="POST" action="" autocomplete="off">
            <?php if ($success): ?>
                <div class="success-message">
                    <strong>Registration successful!</strong><br>
                    You can now <a href="login.php">login to your account</a>.
                </div>
            <?php endif; ?>
            
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
                <label for="username">Username</label>
                <input type="text" 
                       id="username" 
                       name="username" 
                       value="<?php echo Security::sanitizeOutput($form_data['username']); ?>"
                       required 
                       maxlength="50"
                       pattern="[a-zA-Z0-9_]+"
                       title="Username can only contain letters, numbers, and underscores">
            </div>
            
            <div class="form-group">
                <label for="email">Email Address</label>
                <input type="email" 
                       id="email" 
                       name="email" 
                       value="<?php echo Security::sanitizeOutput($form_data['email']); ?>"
                       required 
                       maxlength="100">
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label for="first_name">First Name</label>
                    <input type="text" 
                           id="first_name" 
                           name="first_name" 
                           value="<?php echo Security::sanitizeOutput($form_data['first_name']); ?>"
                           required 
                           maxlength="50">
                </div>
                
                <div class="form-group">
                    <label for="last_name">Last Name</label>
                    <input type="text" 
                           id="last_name" 
                           name="last_name" 
                           value="<?php echo Security::sanitizeOutput($form_data['last_name']); ?>"
                           required 
                           maxlength="50">
                </div>
            </div>
            
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" 
                       id="password" 
                       name="password" 
                       required>
                <div class="password-requirements">
                    <strong>Password must contain:</strong>
                    <ul>
                        <li>At least <?php echo MIN_PASSWORD_LENGTH; ?> characters</li>
                        <li>At least one uppercase letter</li>
                        <li>At least one lowercase letter</li>
                        <li>At least one number</li>
                        <li>At least one special character</li>
                    </ul>
                </div>
            </div>
            
            <div class="form-group">
                <label for="confirm_password">Confirm Password</label>
                <input type="password" 
                       id="confirm_password" 
                       name="confirm_password" 
                       required>
            </div>
            
            <?php echo Security::getCSRFTokenField(); ?>
            
            <button type="submit" class="btn">Create Account</button>
            
            <div class="form-footer">
                Already have an account? <a href="login.php">Sign in here</a>
            </div>
        </form>
    </div>
    
    <script>
        // Password strength indicator
        document.getElementById('password').addEventListener('input', function() {
            const password = this.value;
            const requirements = document.querySelector('.password-requirements');
            
            if (password.length > 0) {
                requirements.style.display = 'block';
            } else {
                requirements.style.display = 'none';
            }
        });
        
        // Prevent form resubmission on page refresh
        if (window.history.replaceState) {
            window.history.replaceState(null, null, window.location.href);
        }
    </script>
</body>
</html>