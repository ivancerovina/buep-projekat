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
$admin_user = Auth::getCurrentUser();
$db = Database::getInstance();

// Get user ID from URL
$user_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$user_id) {
    setAlert('Invalid user ID.', 'error');
    redirect('/buep-projekat/public/admin/users.php');
}

// Get user data
$sql = "SELECT * FROM users WHERE id = :id";
$user = $db->fetchOne($sql, [':id' => $user_id]);

if (!$user) {
    setAlert('User not found.', 'error');
    redirect('/buep-projekat/public/admin/users.php');
}

// Prevent editing admin users (except self)
if ($user['role'] === 'admin' && $user['id'] !== $admin_user['id']) {
    setAlert('Cannot edit other administrator accounts.', 'error');
    redirect('/buep-projekat/public/admin/users.php');
}

$errors = [];
$success = false;

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify CSRF token
    if (!isset($_POST[CSRF_TOKEN_NAME]) || !Security::verifyCSRFToken($_POST[CSRF_TOKEN_NAME])) {
        $errors[] = "Invalid security token. Please refresh and try again.";
    } else {
        // Get form data
        $username = Security::sanitizeInput($_POST['username'] ?? '');
        $email = Security::sanitizeInput($_POST['email'] ?? '');
        $first_name = Security::sanitizeInput($_POST['first_name'] ?? '');
        $last_name = Security::sanitizeInput($_POST['last_name'] ?? '');
        $role = Security::sanitizeInput($_POST['role'] ?? '');
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        $password = $_POST['password'] ?? '';
        
        // Validate input
        if (empty($username)) {
            $errors[] = "Username is required.";
        } elseif ($username !== $user['username']) {
            // Check if new username exists
            $sql = "SELECT id FROM users WHERE username = :username AND id != :id";
            $existing = $db->fetchOne($sql, [':username' => $username, ':id' => $user_id]);
            if ($existing) {
                $errors[] = "Username already exists.";
            }
        }
        
        if (empty($email)) {
            $errors[] = "Email is required.";
        } elseif (!Security::validateEmail($email)) {
            $errors[] = "Invalid email format.";
        } elseif ($email !== $user['email']) {
            // Check if new email exists
            $sql = "SELECT id FROM users WHERE email = :email AND id != :id";
            $existing = $db->fetchOne($sql, [':email' => $email, ':id' => $user_id]);
            if ($existing) {
                $errors[] = "Email already registered.";
            }
        }
        
        if (empty($first_name)) {
            $errors[] = "First name is required.";
        }
        
        if (empty($last_name)) {
            $errors[] = "Last name is required.";
        }
        
        if (!in_array($role, ['employee', 'manager', 'admin'])) {
            $errors[] = "Invalid role selected.";
        }
        
        // Prevent role changes that could lock out admin
        if ($user['role'] === 'admin' && $role !== 'admin') {
            $sql = "SELECT COUNT(*) as count FROM users WHERE role = 'admin' AND is_active = 1";
            $admin_count = $db->fetchOne($sql)['count'];
            if ($admin_count <= 1) {
                $errors[] = "Cannot change role - at least one active admin must exist.";
            }
        }
        
        // Validate password if provided
        if (!empty($password)) {
            $password_errors = Security::validatePasswordStrength($password);
            if (!empty($password_errors)) {
                $errors = array_merge($errors, $password_errors);
            }
        }
        
        // Update user if no errors
        if (empty($errors)) {
            try {
                if (!empty($password)) {
                    // Update with password
                    $password_hash = Security::hashPassword($password);
                    $sql = "UPDATE users SET 
                            username = :username, 
                            email = :email, 
                            password_hash = :password_hash,
                            first_name = :first_name, 
                            last_name = :last_name, 
                            role = :role,
                            is_active = :is_active
                            WHERE id = :id";
                    
                    $params = [
                        ':username' => $username,
                        ':email' => $email,
                        ':password_hash' => $password_hash,
                        ':first_name' => $first_name,
                        ':last_name' => $last_name,
                        ':role' => $role,
                        ':is_active' => $is_active,
                        ':id' => $user_id
                    ];
                } else {
                    // Update without password
                    $sql = "UPDATE users SET 
                            username = :username, 
                            email = :email, 
                            first_name = :first_name, 
                            last_name = :last_name, 
                            role = :role,
                            is_active = :is_active
                            WHERE id = :id";
                    
                    $params = [
                        ':username' => $username,
                        ':email' => $email,
                        ':first_name' => $first_name,
                        ':last_name' => $last_name,
                        ':role' => $role,
                        ':is_active' => $is_active,
                        ':id' => $user_id
                    ];
                }
                
                $db->execute($sql, $params);
                
                Security::logSecurityEvent('USER_UPDATED', "Admin updated user: $username", $admin_user['id']);
                
                // Update user array for form display
                $user['username'] = $username;
                $user['email'] = $email;
                $user['first_name'] = $first_name;
                $user['last_name'] = $last_name;
                $user['role'] = $role;
                $user['is_active'] = $is_active;
                
                $success = true;
                
            } catch (Exception $e) {
                error_log("Error updating user: " . $e->getMessage());
                $errors[] = "An error occurred while updating the user.";
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
    <title>Edit User - <?php echo APP_NAME; ?></title>
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
        
        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .checkbox-group input[type="checkbox"] {
            width: auto;
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
        
        .password-note {
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
                <h1>Edit User</h1>
                <p>Update user account information</p>
            </div>
        </div>
        
        <div class="form-container">
            <?php if ($success): ?>
                <div class="success-message">
                    <strong>Success!</strong> User has been updated successfully.
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
                           value="<?php echo Security::sanitizeOutput($user['username']); ?>"
                           required 
                           maxlength="50">
                </div>
                
                <div class="form-group">
                    <label for="email">Email Address</label>
                    <input type="email" 
                           id="email" 
                           name="email" 
                           value="<?php echo Security::sanitizeOutput($user['email']); ?>"
                           required 
                           maxlength="100">
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="first_name">First Name</label>
                        <input type="text" 
                               id="first_name" 
                               name="first_name" 
                               value="<?php echo Security::sanitizeOutput($user['first_name']); ?>"
                               required 
                               maxlength="50">
                    </div>
                    
                    <div class="form-group">
                        <label for="last_name">Last Name</label>
                        <input type="text" 
                               id="last_name" 
                               name="last_name" 
                               value="<?php echo Security::sanitizeOutput($user['last_name']); ?>"
                               required 
                               maxlength="50">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="role">Role</label>
                        <select id="role" name="role" required>
                            <option value="employee" <?php echo $user['role'] === 'employee' ? 'selected' : ''; ?>>Employee</option>
                            <option value="manager" <?php echo $user['role'] === 'manager' ? 'selected' : ''; ?>>Manager</option>
                            <option value="admin" <?php echo $user['role'] === 'admin' ? 'selected' : ''; ?>>Administrator</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>&nbsp;</label>
                        <div class="checkbox-group">
                            <input type="checkbox" 
                                   id="is_active" 
                                   name="is_active" 
                                   value="1"
                                   <?php echo $user['is_active'] ? 'checked' : ''; ?>>
                            <label for="is_active">Account is active</label>
                        </div>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="password">New Password (Optional)</label>
                    <input type="password" 
                           id="password" 
                           name="password">
                    <div class="password-note">
                        Leave blank to keep current password. Password must contain at least <?php echo MIN_PASSWORD_LENGTH; ?> characters, 
                        including uppercase, lowercase, numbers, and special characters.
                    </div>
                </div>
                
                <?php echo Security::getCSRFTokenField(); ?>
                
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">Update User</button>
                    <a href="users.php" class="btn btn-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</body>
</html>