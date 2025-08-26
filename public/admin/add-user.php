<?php
define('APP_RUNNING', true);
require_once '../../config/config.php';

// Set security headers
setSecurityHeaders();

// Start session
Auth::startSecureSession();

// Require admin role
Auth::requireAdmin();

// Get current user
$user = Auth::getCurrentUser();
$db = Database::getInstance();

$errors = [];
$success = false;
$form_data = [
    'username' => '',
    'email' => '',
    'first_name' => '',
    'last_name' => '',
    'role' => 'employee'
];

// Handle form submission
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
        $form_data['role'] = Security::sanitizeInput($_POST['role'] ?? 'employee');
        $password = $_POST['password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';
        
        // Validate input
        if (empty($form_data['username'])) {
            $errors[] = "Username is required.";
        } elseif (strlen($form_data['username']) < 3) {
            $errors[] = "Username must be at least 3 characters long.";
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
        
        if (!in_array($form_data['role'], ['employee', 'manager', 'admin'])) {
            $errors[] = "Invalid role selected.";
        }
        
        // Check if username already exists
        if (empty($errors)) {
            $sql = "SELECT id FROM users WHERE username = :username";
            $existing = $db->fetchOne($sql, [':username' => $form_data['username']]);
            
            if ($existing) {
                $errors[] = "Username already exists.";
            }
            
            // Check if email already exists
            $sql = "SELECT id FROM users WHERE email = :email";
            $existing = $db->fetchOne($sql, [':email' => $form_data['email']]);
            
            if ($existing) {
                $errors[] = "Email already registered.";
            }
        }
        
        // If no errors, create user
        if (empty($errors)) {
            try {
                $password_hash = Security::hashPassword($password);
                
                $sql = "INSERT INTO users (username, email, password_hash, first_name, last_name, role, is_active) 
                        VALUES (:username, :email, :password_hash, :first_name, :last_name, :role, 1)";
                
                $db->execute($sql, [
                    ':username' => $form_data['username'],
                    ':email' => $form_data['email'],
                    ':password_hash' => $password_hash,
                    ':first_name' => $form_data['first_name'],
                    ':last_name' => $form_data['last_name'],
                    ':role' => $form_data['role']
                ]);
                
                Security::logSecurityEvent('USER_CREATED', "Admin created new user: " . $form_data['username'], $user['id']);
                
                $success = true;
                
                // Clear form data
                $form_data = [
                    'username' => '',
                    'email' => '',
                    'first_name' => '',
                    'last_name' => '',
                    'role' => 'employee'
                ];
                
            } catch (Exception $e) {
                error_log("Error creating user: " . $e->getMessage());
                $errors[] = "An error occurred while creating the user.";
            }
        }
    }
}

// Generate CSRF token
$csrf_token = Security::generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add User - <?php echo APP_NAME; ?></title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            background: #f5f5f5;
            min-height: 100vh;
        }
        
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px 0;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .header-content {
            max-width: 1400px;
            margin: 0 auto;
            padding: 0 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .logo {
            font-size: 24px;
            font-weight: bold;
        }
        
        .logo .badge {
            background: rgba(255,255,255,0.2);
            padding: 2px 8px;
            border-radius: 3px;
            font-size: 12px;
            margin-left: 10px;
        }
        
        .nav-menu {
            display: flex;
            gap: 20px;
            align-items: center;
        }
        
        .nav-menu a {
            color: white;
            text-decoration: none;
            padding: 8px 16px;
            border-radius: 5px;
            transition: background 0.3s;
        }
        
        .nav-menu a:hover,
        .nav-menu a.active {
            background: rgba(255,255,255,0.1);
        }
        
        .container {
            max-width: 800px;
            margin: 30px auto;
            padding: 0 20px;
        }
        
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
        }
        
        .page-title h1 {
            color: #333;
            margin-bottom: 5px;
        }
        
        .page-title p {
            color: #666;
        }
        
        .form-container {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
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
        
        .form-group input,
        .form-group select {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
            transition: border-color 0.3s;
        }
        
        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: #667eea;
        }
        
        .error-messages {
            background: #fee;
            border: 1px solid #fcc;
            color: #c00;
            padding: 15px;
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
        }
        
        .form-actions {
            display: flex;
            gap: 10px;
            margin-top: 30px;
        }
        
        .btn {
            padding: 12px 30px;
            border: none;
            border-radius: 5px;
            font-size: 16px;
            font-weight: 500;
            cursor: pointer;
            transition: transform 0.2s, box-shadow 0.2s;
            text-decoration: none;
            display: inline-block;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(102, 126, 234, 0.4);
        }
        
        .btn-secondary {
            background: #e0e0e0;
            color: #333;
        }
        
        .btn-secondary:hover {
            background: #d0d0d0;
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
    </style>
</head>
<body>
    <header class="header">
        <div class="header-content">
            <div class="logo">
                <?php echo APP_NAME; ?>
                <span class="badge">ADMIN</span>
            </div>
            <nav class="nav-menu">
                <a href="dashboard.php">Dashboard</a>
                <a href="users.php" class="active">Users</a>
                <a href="limits.php">Limits</a>
                <a href="reports.php">Reports</a>
                <a href="logs.php">Security Logs</a>
                <a href="../logout.php">Logout</a>
            </nav>
        </div>
    </header>
    
    <div class="container">
        <div class="page-header">
            <div class="page-title">
                <h1>Add New User</h1>
                <p>Create a new user account</p>
            </div>
        </div>
        
        <div class="form-container">
            <?php if ($success): ?>
                <div class="success-message">
                    <strong>Success!</strong> User has been created successfully.
                    <a href="users.php" style="color: #060; text-decoration: underline;">View all users</a>
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
            
            <form method="POST" action="">
                <div class="form-group">
                    <label for="username">Username</label>
                    <input type="text" 
                           id="username" 
                           name="username" 
                           value="<?php echo Security::sanitizeOutput($form_data['username']); ?>"
                           required 
                           maxlength="50">
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
                    <label for="role">Role</label>
                    <select id="role" name="role" required>
                        <option value="employee" <?php echo $form_data['role'] === 'employee' ? 'selected' : ''; ?>>Employee</option>
                        <option value="manager" <?php echo $form_data['role'] === 'manager' ? 'selected' : ''; ?>>Manager</option>
                        <option value="admin" <?php echo $form_data['role'] === 'admin' ? 'selected' : ''; ?>>Administrator</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" 
                           id="password" 
                           name="password" 
                           required>
                    <div class="password-requirements">
                        Password must contain at least <?php echo MIN_PASSWORD_LENGTH; ?> characters, 
                        including uppercase, lowercase, numbers, and special characters.
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
                
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">Create User</button>
                    <a href="users.php" class="btn btn-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</body>
</html>