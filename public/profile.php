<?php
define('APP_RUNNING', true);
require_once '../config/config.php';

// Set security headers
setSecurityHeaders();

// Start session
Auth::startSecureSession();

// Require login
Auth::requireLogin();

// Get current user
$user = Auth::getCurrentUser();
$db = Database::getInstance();

// Get user's full information
$sql = "SELECT * FROM users WHERE id = :id";
$user_info = $db->fetchOne($sql, [':id' => $user['id']]);

$errors = [];
$success = false;

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify CSRF token
    if (!isset($_POST[CSRF_TOKEN_NAME]) || !Security::verifyCSRFToken($_POST[CSRF_TOKEN_NAME])) {
        $errors[] = "Invalid security token. Please refresh and try again.";
    } else {
        $action = $_POST['action'] ?? '';
        
        if ($action === 'update_profile') {
            // Update profile information
            $first_name = Security::sanitizeInput($_POST['first_name'] ?? '');
            $last_name = Security::sanitizeInput($_POST['last_name'] ?? '');
            $email = Security::sanitizeInput($_POST['email'] ?? '');
            
            // Validate input
            if (empty($first_name)) {
                $errors[] = "First name is required.";
            }
            
            if (empty($last_name)) {
                $errors[] = "Last name is required.";
            }
            
            if (empty($email)) {
                $errors[] = "Email is required.";
            } elseif (!Security::validateEmail($email)) {
                $errors[] = "Invalid email format.";
            } elseif ($email !== $user_info['email']) {
                // Check if new email exists
                $sql = "SELECT id FROM users WHERE email = :email AND id != :id";
                $existing = $db->fetchOne($sql, [':email' => $email, ':id' => $user['id']]);
                if ($existing) {
                    $errors[] = "Email already registered.";
                }
            }
            
            // Update if no errors
            if (empty($errors)) {
                try {
                    $sql = "UPDATE users SET first_name = :first_name, last_name = :last_name, email = :email WHERE id = :id";
                    $db->execute($sql, [
                        ':first_name' => $first_name,
                        ':last_name' => $last_name,
                        ':email' => $email,
                        ':id' => $user['id']
                    ]);
                    
                    // Update session email
                    $_SESSION['email'] = $email;
                    
                    // Update user_info for display
                    $user_info['first_name'] = $first_name;
                    $user_info['last_name'] = $last_name;
                    $user_info['email'] = $email;
                    
                    Security::logSecurityEvent('PROFILE_UPDATED', "User updated profile information", $user['id']);
                    $success = true;
                    
                } catch (Exception $e) {
                    error_log("Error updating profile: " . $e->getMessage());
                    $errors[] = "An error occurred while updating your profile.";
                }
            }
            
        } elseif ($action === 'change_password') {
            // Change password
            $current_password = $_POST['current_password'] ?? '';
            $new_password = $_POST['new_password'] ?? '';
            $confirm_password = $_POST['confirm_password'] ?? '';
            
            // Validate input
            if (empty($current_password)) {
                $errors[] = "Current password is required.";
            } elseif (!Security::verifyPassword($current_password, $user_info['password_hash'])) {
                $errors[] = "Current password is incorrect.";
            }
            
            if (empty($new_password)) {
                $errors[] = "New password is required.";
            } else {
                $password_errors = Security::validatePasswordStrength($new_password);
                if (!empty($password_errors)) {
                    $errors = array_merge($errors, $password_errors);
                }
            }
            
            if ($new_password !== $confirm_password) {
                $errors[] = "New passwords do not match.";
            }
            
            // Update password if no errors
            if (empty($errors)) {
                try {
                    $password_hash = Security::hashPassword($new_password);
                    $sql = "UPDATE users SET password_hash = :password_hash WHERE id = :id";
                    $db->execute($sql, [
                        ':password_hash' => $password_hash,
                        ':id' => $user['id']
                    ]);
                    
                    Security::logSecurityEvent('PASSWORD_CHANGED', "User changed password", $user['id']);
                    $success = true;
                    
                } catch (Exception $e) {
                    error_log("Error changing password: " . $e->getMessage());
                    $errors[] = "An error occurred while changing your password.";
                }
            }
        }
    }
}

// Get user's statistics
$sql = "SELECT 
        COUNT(*) as total_records,
        SUM(total_cost) as total_spent,
        SUM(liters) as total_liters,
        MIN(date) as first_record,
        MAX(date) as last_record
        FROM fuel_records WHERE user_id = :user_id";
$user_stats = $db->fetchOne($sql, [':user_id' => $user['id']]);

// Get current month's limit
$current_month = date('Y-m-01');
$sql = "SELECT monthly_limit FROM fuel_limits WHERE user_id = :user_id AND month_year = :month_year";
$limit = $db->fetchOne($sql, [':user_id' => $user['id'], ':month_year' => $current_month]);
$monthly_limit = $limit ? $limit['monthly_limit'] : null;

// Generate CSRF token
$csrf_token = Security::generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - <?php echo APP_NAME; ?></title>
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
            max-width: 1200px;
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
            max-width: 1000px;
            margin: 30px auto;
            padding: 0 20px;
        }
        
        .profile-header {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 30px;
            display: flex;
            align-items: center;
            gap: 30px;
        }
        
        .profile-avatar {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 32px;
            font-weight: bold;
        }
        
        .profile-info h1 {
            color: #333;
            margin-bottom: 5px;
        }
        
        .profile-info .role {
            color: #666;
            text-transform: uppercase;
            font-size: 14px;
            margin-bottom: 10px;
        }
        
        .profile-info .stats {
            display: flex;
            gap: 20px;
            font-size: 14px;
        }
        
        .profile-info .stats span {
            color: #999;
        }
        
        .profile-content {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
        }
        
        .profile-section {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        
        .profile-section h2 {
            background: #f9f9f9;
            padding: 20px;
            color: #333;
            font-size: 18px;
            border-bottom: 2px solid #e0e0e0;
        }
        
        .profile-section-content {
            padding: 25px;
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
        
        .form-group input:disabled {
            background: #f5f5f5;
            color: #999;
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
        
        .btn {
            padding: 12px 30px;
            border: none;
            border-radius: 5px;
            font-size: 16px;
            font-weight: 500;
            cursor: pointer;
            transition: transform 0.2s, box-shadow 0.2s;
            width: 100%;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(102, 126, 234, 0.4);
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px 0;
            border-bottom: 1px solid #f0f0f0;
        }
        
        .stat-item:last-child {
            border-bottom: none;
        }
        
        .stat-label {
            color: #666;
            font-size: 14px;
        }
        
        .stat-value {
            font-weight: bold;
            color: #333;
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
            <div class="logo"><?php echo APP_NAME; ?></div>
            <nav class="nav-menu">
                <a href="dashboard.php">Dashboard</a>
                <a href="fuel-records.php">My Records</a>
                <a href="profile.php" class="active">Profile</a>
                <a href="logout.php">Logout</a>
            </nav>
        </div>
    </header>
    
    <div class="container">
        <div class="profile-header">
            <div class="profile-avatar">
                <?php echo strtoupper(substr($user_info['first_name'], 0, 1) . substr($user_info['last_name'], 0, 1)); ?>
            </div>
            <div class="profile-info">
                <h1><?php echo Security::sanitizeOutput($user_info['first_name'] . ' ' . $user_info['last_name']); ?></h1>
                <div class="role"><?php echo ucfirst($user_info['role']); ?></div>
                <div class="stats">
                    <span>Member since: <?php echo formatDate($user_info['created_at'], 'M Y'); ?></span>
                    <span>•</span>
                    <span>Last login: <?php echo $user_info['last_login'] ? formatDate($user_info['last_login'], 'd M Y') : 'Never'; ?></span>
                </div>
            </div>
        </div>
        
        <?php if ($success): ?>
            <div class="success-message">
                <strong>Success!</strong> Your changes have been saved successfully.
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
        
        <div class="profile-content">
            <div class="profile-section">
                <h2>👤 Personal Information</h2>
                <div class="profile-section-content">
                    <form method="POST" action="">
                        <div class="form-group">
                            <label for="username">Username</label>
                            <input type="text" 
                                   id="username" 
                                   value="<?php echo Security::sanitizeOutput($user_info['username']); ?>"
                                   disabled>
                        </div>
                        
                        <div class="form-group">
                            <label for="first_name">First Name</label>
                            <input type="text" 
                                   id="first_name" 
                                   name="first_name" 
                                   value="<?php echo Security::sanitizeOutput($user_info['first_name']); ?>"
                                   required 
                                   maxlength="50">
                        </div>
                        
                        <div class="form-group">
                            <label for="last_name">Last Name</label>
                            <input type="text" 
                                   id="last_name" 
                                   name="last_name" 
                                   value="<?php echo Security::sanitizeOutput($user_info['last_name']); ?>"
                                   required 
                                   maxlength="50">
                        </div>
                        
                        <div class="form-group">
                            <label for="email">Email Address</label>
                            <input type="email" 
                                   id="email" 
                                   name="email" 
                                   value="<?php echo Security::sanitizeOutput($user_info['email']); ?>"
                                   required 
                                   maxlength="100">
                        </div>
                        
                        <input type="hidden" name="action" value="update_profile">
                        <?php echo Security::getCSRFTokenField(); ?>
                        
                        <button type="submit" class="btn btn-primary">Update Profile</button>
                    </form>
                </div>
            </div>
            
            <div class="profile-section">
                <h2>🔒 Change Password</h2>
                <div class="profile-section-content">
                    <form method="POST" action="">
                        <div class="form-group">
                            <label for="current_password">Current Password</label>
                            <input type="password" 
                                   id="current_password" 
                                   name="current_password" 
                                   required>
                        </div>
                        
                        <div class="form-group">
                            <label for="new_password">New Password</label>
                            <input type="password" 
                                   id="new_password" 
                                   name="new_password" 
                                   required>
                            <div class="password-requirements">
                                Password must contain at least <?php echo MIN_PASSWORD_LENGTH; ?> characters, 
                                including uppercase, lowercase, numbers, and special characters.
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="confirm_password">Confirm New Password</label>
                            <input type="password" 
                                   id="confirm_password" 
                                   name="confirm_password" 
                                   required>
                        </div>
                        
                        <input type="hidden" name="action" value="change_password">
                        <?php echo Security::getCSRFTokenField(); ?>
                        
                        <button type="submit" class="btn btn-primary">Change Password</button>
                    </form>
                </div>
            </div>
        </div>
        
        <div class="profile-section" style="margin-top: 30px;">
            <h2>📊 Account Statistics</h2>
            <div class="profile-section-content">
                <div class="stats-grid">
                    <div class="stat-item">
                        <span class="stat-label">Total Fuel Records</span>
                        <span class="stat-value"><?php echo number_format($user_stats['total_records'] ?? 0); ?></span>
                    </div>
                    
                    <div class="stat-item">
                        <span class="stat-label">Total Amount Spent</span>
                        <span class="stat-value"><?php echo formatCurrency($user_stats['total_spent'] ?? 0); ?></span>
                    </div>
                    
                    <div class="stat-item">
                        <span class="stat-label">Total Fuel Consumed</span>
                        <span class="stat-value"><?php echo number_format($user_stats['total_liters'] ?? 0, 2); ?> L</span>
                    </div>
                    
                    <div class="stat-item">
                        <span class="stat-label">Current Monthly Limit</span>
                        <span class="stat-value"><?php echo $monthly_limit ? formatCurrency($monthly_limit) : 'No limit set'; ?></span>
                    </div>
                    
                    <?php if ($user_stats['first_record']): ?>
                        <div class="stat-item">
                            <span class="stat-label">First Record</span>
                            <span class="stat-value"><?php echo formatDate($user_stats['first_record'], 'd M Y'); ?></span>
                        </div>
                        
                        <div class="stat-item">
                            <span class="stat-label">Most Recent Record</span>
                            <span class="stat-value"><?php echo formatDate($user_stats['last_record'], 'd M Y'); ?></span>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</body>
</html>