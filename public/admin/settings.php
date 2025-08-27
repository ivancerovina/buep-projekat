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

$success_message = '';
$error_message = '';

// Handle settings update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify CSRF token
    if (!isset($_POST[CSRF_TOKEN_NAME]) || !Security::verifyCSRFToken($_POST[CSRF_TOKEN_NAME])) {
        $error_message = "Invalid security token. Please refresh and try again.";
    } else {
        $action = $_POST['action'] ?? '';
        
        if ($action === 'cleanup_logs') {
            // Clean up old security logs
            $days = (int)($_POST['days'] ?? 30);
            if ($days < 7) $days = 7; // Minimum 7 days
            
            try {
                $sql = "DELETE FROM security_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL :days DAY)";
                $stmt = $db->execute($sql, [':days' => $days]);
                
                Security::logSecurityEvent('LOGS_CLEANUP', "Admin cleaned logs older than $days days", $user['id']);
                $success_message = "Security logs cleaned successfully.";
                
            } catch (Exception $e) {
                error_log("Error cleaning logs: " . $e->getMessage());
                $error_message = "An error occurred while cleaning logs.";
            }
            
        } elseif ($action === 'cleanup_sessions') {
            // Clean up expired sessions
            try {
                Auth::cleanExpiredSessions();
                Security::logSecurityEvent('SESSIONS_CLEANUP', "Admin cleaned expired sessions", $user['id']);
                $success_message = "Expired sessions cleaned successfully.";
                
            } catch (Exception $e) {
                error_log("Error cleaning sessions: " . $e->getMessage());
                $error_message = "An error occurred while cleaning sessions.";
            }
            
        } elseif ($action === 'reset_failed_logins') {
            // Reset all failed login attempts
            try {
                $sql = "UPDATE users SET failed_login_attempts = 0, locked_until = NULL";
                $db->execute($sql);
                
                Security::logSecurityEvent('FAILED_LOGINS_RESET', "Admin reset all failed login attempts", $user['id']);
                $success_message = "All failed login attempts have been reset.";
                
            } catch (Exception $e) {
                error_log("Error resetting failed logins: " . $e->getMessage());
                $error_message = "An error occurred while resetting failed logins.";
            }
        }
    }
}

// Get system statistics
$stats = [];

// Database size
try {
    $sql = "SELECT 
            SUM(data_length + index_length) / 1024 / 1024 AS db_size_mb
            FROM information_schema.tables 
            WHERE table_schema = 'fuel_database'";
    $result = $db->fetchOne($sql);
    $stats['db_size'] = $result['db_size_mb'] ?? 0;
} catch (Exception $e) {
    $stats['db_size'] = 'Unknown';
}

// Log counts
$sql = "SELECT COUNT(*) as total FROM security_logs";
$stats['total_logs'] = $db->fetchOne($sql)['total'];

$sql = "SELECT COUNT(*) as total FROM security_logs WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
$stats['recent_logs'] = $db->fetchOne($sql)['total'];

// Session counts
$sql = "SELECT COUNT(*) as total FROM user_sessions";
$stats['total_sessions'] = $db->fetchOne($sql)['total'];

$sql = "SELECT COUNT(*) as total FROM user_sessions WHERE expires_at > NOW()";
$stats['active_sessions'] = $db->fetchOne($sql)['total'];

// User counts
$sql = "SELECT 
        COUNT(*) as total_users,
        SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as active_users,
        SUM(CASE WHEN locked_until IS NOT NULL AND locked_until > NOW() THEN 1 ELSE 0 END) as locked_users
        FROM users WHERE role != 'admin'";
$user_stats = $db->fetchOne($sql);
$stats = array_merge($stats, $user_stats);

// Generate CSRF token
$csrf_token = Security::generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Settings - <?php echo APP_NAME; ?></title>
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
            max-width: 1200px;
            margin: 30px auto;
            padding: 0 20px;
        }
        
        .page-header {
            margin-bottom: 30px;
        }
        
        .page-title h1 {
            color: #333;
            margin-bottom: 5px;
        }
        
        .page-title p {
            color: #666;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .stat-card h3 {
            color: #666;
            font-size: 12px;
            margin-bottom: 5px;
            text-transform: uppercase;
        }
        
        .stat-card .value {
            font-size: 28px;
            font-weight: bold;
            color: #333;
            margin-bottom: 5px;
        }
        
        .stat-card .detail {
            color: #999;
            font-size: 14px;
        }
        
        .settings-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
            gap: 30px;
        }
        
        .settings-section {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        
        .settings-section h2 {
            background: #f9f9f9;
            padding: 20px;
            border-bottom: 2px solid #e0e0e0;
            color: #333;
            font-size: 18px;
        }
        
        .settings-content {
            padding: 20px;
        }
        
        .setting-item {
            margin-bottom: 20px;
            padding-bottom: 20px;
            border-bottom: 1px solid #f0f0f0;
        }
        
        .setting-item:last-child {
            border-bottom: none;
            margin-bottom: 0;
        }
        
        .setting-item h4 {
            color: #333;
            margin-bottom: 5px;
        }
        
        .setting-item p {
            color: #666;
            font-size: 14px;
            margin-bottom: 15px;
        }
        
        .setting-form {
            display: flex;
            gap: 10px;
            align-items: end;
        }
        
        .setting-form input {
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
        }
        
        .setting-form label {
            display: block;
            margin-bottom: 5px;
            color: #666;
            font-size: 14px;
        }
        
        .btn {
            padding: 8px 20px;
            border: none;
            border-radius: 5px;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s;
            text-decoration: none;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        
        .btn-warning {
            background: #FFA726;
            color: white;
        }
        
        .btn-danger {
            background: #EF5350;
            color: white;
        }
        
        .btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 3px 10px rgba(0,0,0,0.2);
        }
        
        .alert {
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        
        .alert-success {
            background: #efe;
            border: 1px solid #cfc;
            color: #060;
        }
        
        .alert-error {
            background: #fee;
            border: 1px solid #fcc;
            color: #c00;
        }
        
        .config-info {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        
        .config-info h4 {
            color: #333;
            margin-bottom: 10px;
        }
        
        .config-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .config-table th,
        .config-table td {
            text-align: left;
            padding: 8px;
            border-bottom: 1px solid #e0e0e0;
            font-size: 14px;
        }
        
        .config-table th {
            color: #666;
            font-weight: 500;
        }
        
        .config-table td {
            color: #333;
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
                <a href="users.php">Users</a>
                <a href="limits.php">Limits</a>
                <a href="reports.php">Reports</a>
                <a href="logs.php">Security Logs</a>
                <a href="settings.php" class="active">Settings</a>
                <a href="../logout.php">Logout</a>
            </nav>
        </div>
    </header>
    
    <div class="container">
        <div class="page-header">
            <div class="page-title">
                <h1>System Settings</h1>
                <p>Configure system parameters and maintenance</p>
            </div>
        </div>
        
        <?php if ($success_message): ?>
            <div class="alert alert-success"><?php echo Security::sanitizeOutput($success_message); ?></div>
        <?php endif; ?>
        
        <?php if ($error_message): ?>
            <div class="alert alert-error"><?php echo Security::sanitizeOutput($error_message); ?></div>
        <?php endif; ?>
        
        <div class="stats-grid">
            <div class="stat-card">
                <h3>Database Size</h3>
                <div class="value"><?php echo is_numeric($stats['db_size']) ? number_format($stats['db_size'], 2) . ' MB' : $stats['db_size']; ?></div>
                <div class="detail">Total database storage</div>
            </div>
            
            <div class="stat-card">
                <h3>Security Logs</h3>
                <div class="value"><?php echo number_format($stats['total_logs']); ?></div>
                <div class="detail"><?php echo number_format($stats['recent_logs']); ?> in last 7 days</div>
            </div>
            
            <div class="stat-card">
                <h3>User Sessions</h3>
                <div class="value"><?php echo number_format($stats['active_sessions']); ?></div>
                <div class="detail"><?php echo number_format($stats['total_sessions']); ?> total sessions</div>
            </div>
            
            <div class="stat-card">
                <h3>System Users</h3>
                <div class="value"><?php echo number_format($stats['active_users']); ?></div>
                <div class="detail"><?php echo number_format($stats['locked_users']); ?> locked accounts</div>
            </div>
        </div>
        
        <div class="settings-grid">
            <div class="settings-section">
                <h2>🛠️ System Maintenance</h2>
                <div class="settings-content">
                    <div class="setting-item">
                        <h4>Clean Security Logs</h4>
                        <p>Remove old security log entries to free up database space.</p>
                        <form method="POST" action="" class="setting-form">
                            <div>
                                <label for="days">Keep logs for (days):</label>
                                <input type="number" id="days" name="days" value="30" min="7" max="365">
                            </div>
                            <input type="hidden" name="action" value="cleanup_logs">
                            <?php echo Security::getCSRFTokenField(); ?>
                            <button type="submit" class="btn btn-warning" onclick="return confirm('Are you sure you want to delete old log entries?');">Clean Logs</button>
                        </form>
                    </div>
                    
                    <div class="setting-item">
                        <h4>Clean Expired Sessions</h4>
                        <p>Remove expired user sessions from the database.</p>
                        <form method="POST" action="" class="setting-form">
                            <input type="hidden" name="action" value="cleanup_sessions">
                            <?php echo Security::getCSRFTokenField(); ?>
                            <button type="submit" class="btn btn-primary">Clean Sessions</button>
                        </form>
                    </div>
                    
                    <div class="setting-item">
                        <h4>Reset Failed Login Attempts</h4>
                        <p>Clear all failed login attempts and unlock all accounts.</p>
                        <form method="POST" action="" class="setting-form">
                            <input type="hidden" name="action" value="reset_failed_logins">
                            <?php echo Security::getCSRFTokenField(); ?>
                            <button type="submit" class="btn btn-danger" onclick="return confirm('This will unlock all locked accounts. Continue?');">Reset Failed Logins</button>
                        </form>
                    </div>
                </div>
            </div>
            
            <div class="settings-section">
                <h2>⚙️ System Configuration</h2>
                <div class="settings-content">
                    <div class="config-info">
                        <h4>Current Settings</h4>
                        <table class="config-table">
                            <tr>
                                <th>Setting</th>
                                <th>Value</th>
                            </tr>
                            <tr>
                                <td>Session Lifetime</td>
                                <td><?php echo SESSION_LIFETIME / 60; ?> minutes</td>
                            </tr>
                            <tr>
                                <td>Max Login Attempts</td>
                                <td><?php echo MAX_LOGIN_ATTEMPTS; ?></td>
                            </tr>
                            <tr>
                                <td>Lockout Time</td>
                                <td><?php echo LOCKOUT_TIME / 60; ?> minutes</td>
                            </tr>
                            <tr>
                                <td>Min Password Length</td>
                                <td><?php echo MIN_PASSWORD_LENGTH; ?> characters</td>
                            </tr>
                            <tr>
                                <td>Debug Mode</td>
                                <td><?php echo DEBUG_MODE ? 'Enabled' : 'Disabled'; ?></td>
                            </tr>
                            <tr>
                                <td>Security Logging</td>
                                <td><?php echo LOG_SECURITY_EVENTS ? 'Enabled' : 'Disabled'; ?></td>
                            </tr>
                        </table>
                    </div>
                    
                    <p style="color: #666; font-size: 14px;">
                        <strong>Note:</strong> System configuration settings are defined in <code>config/config.php</code>. 
                        To modify these settings, update the configuration file and restart the application.
                    </p>
                </div>
            </div>
        </div>
    </div>
</body>
</html>