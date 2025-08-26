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

// Get statistics
$sql = "SELECT COUNT(*) as total FROM users WHERE role != 'admin'";
$users_count = $db->fetchOne($sql)['total'];

$sql = "SELECT COUNT(*) as total FROM fuel_records";
$records_count = $db->fetchOne($sql)['total'];

$sql = "SELECT SUM(total_cost) as total FROM fuel_records WHERE DATE_FORMAT(date, '%Y-%m') = DATE_FORMAT(NOW(), '%Y-%m')";
$monthly_spending = $db->fetchOne($sql)['total'] ?? 0;

$sql = "SELECT COUNT(*) as total FROM fuel_limits WHERE month_year = DATE_FORMAT(NOW(), '%Y-%m-01')";
$limits_set = $db->fetchOne($sql)['total'];

// Get recent security events
$sql = "SELECT sl.*, u.username 
        FROM security_logs sl 
        LEFT JOIN users u ON sl.user_id = u.id 
        ORDER BY sl.created_at DESC 
        LIMIT 10";
$recent_events = $db->fetchAll($sql);

// Get users exceeding limits
$sql = "SELECT u.id, u.username, u.first_name, u.last_name, 
        fl.monthly_limit,
        COALESCE(SUM(fr.total_cost), 0) as total_spent,
        (COALESCE(SUM(fr.total_cost), 0) / fl.monthly_limit * 100) as usage_percentage
        FROM users u
        INNER JOIN fuel_limits fl ON u.id = fl.user_id AND fl.month_year = DATE_FORMAT(NOW(), '%Y-%m-01')
        LEFT JOIN fuel_records fr ON u.id = fr.user_id AND DATE_FORMAT(fr.date, '%Y-%m') = DATE_FORMAT(NOW(), '%Y-%m')
        WHERE u.role != 'admin'
        GROUP BY u.id, fl.monthly_limit
        HAVING usage_percentage >= 80
        ORDER BY usage_percentage DESC";
$users_exceeding = $db->fetchAll($sql);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - <?php echo APP_NAME; ?></title>
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
        
        .nav-menu a:hover {
            background: rgba(255,255,255,0.1);
        }
        
        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .welcome-section {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 30px;
        }
        
        .welcome-section h1 {
            color: #333;
            margin-bottom: 10px;
        }
        
        .welcome-section p {
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
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            transition: transform 0.2s;
        }
        
        .stat-card:hover {
            transform: translateY(-2px);
        }
        
        .stat-card .icon {
            width: 50px;
            height: 50px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            margin-bottom: 15px;
        }
        
        .stat-card.blue .icon { background: linear-gradient(135deg, #667eea, #764ba2); color: white; }
        .stat-card.green .icon { background: linear-gradient(135deg, #66BB6A, #43A047); color: white; }
        .stat-card.orange .icon { background: linear-gradient(135deg, #FFA726, #FF7043); color: white; }
        .stat-card.red .icon { background: linear-gradient(135deg, #EF5350, #E53935); color: white; }
        
        .stat-card h3 {
            color: #666;
            font-size: 14px;
            margin-bottom: 10px;
            text-transform: uppercase;
        }
        
        .stat-value {
            font-size: 32px;
            font-weight: bold;
            color: #333;
        }
        
        .quick-actions {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .action-card {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            text-align: center;
            text-decoration: none;
            color: #333;
            transition: transform 0.2s, box-shadow 0.2s;
        }
        
        .action-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 20px rgba(0,0,0,0.15);
        }
        
        .action-card .icon {
            font-size: 32px;
            margin-bottom: 10px;
        }
        
        .action-card h4 {
            font-size: 16px;
            margin-bottom: 5px;
        }
        
        .action-card p {
            font-size: 12px;
            color: #666;
        }
        
        .content-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 30px;
        }
        
        .panel {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            padding: 25px;
        }
        
        .panel h2 {
            color: #333;
            margin-bottom: 20px;
            font-size: 18px;
        }
        
        .warning-list {
            list-style: none;
        }
        
        .warning-list li {
            padding: 15px;
            border-left: 4px solid #FF7043;
            background: #FFF3E0;
            margin-bottom: 10px;
            border-radius: 5px;
        }
        
        .warning-list .user-name {
            font-weight: bold;
            color: #333;
        }
        
        .warning-list .usage {
            color: #666;
            font-size: 14px;
            margin-top: 5px;
        }
        
        .progress-bar {
            width: 100%;
            height: 8px;
            background: #e0e0e0;
            border-radius: 4px;
            overflow: hidden;
            margin-top: 5px;
        }
        
        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, #FFA726, #FF7043);
            transition: width 0.3s;
        }
        
        .progress-fill.danger {
            background: linear-gradient(90deg, #EF5350, #E53935);
        }
        
        .event-log {
            list-style: none;
            max-height: 400px;
            overflow-y: auto;
        }
        
        .event-log li {
            padding: 10px;
            border-bottom: 1px solid #f0f0f0;
            font-size: 14px;
        }
        
        .event-log li:last-child {
            border-bottom: none;
        }
        
        .event-type {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 3px;
            font-size: 11px;
            font-weight: bold;
            text-transform: uppercase;
            margin-right: 10px;
        }
        
        .event-type.success { background: #C8E6C9; color: #2E7D32; }
        .event-type.warning { background: #FFF9C4; color: #F57C00; }
        .event-type.error { background: #FFCDD2; color: #C62828; }
        .event-type.info { background: #E1F5FE; color: #0277BD; }
        
        .event-time {
            color: #999;
            font-size: 12px;
            float: right;
        }
        
        .empty-state {
            text-align: center;
            padding: 40px;
            color: #999;
        }
        
        .empty-state .icon {
            font-size: 48px;
            margin-bottom: 10px;
        }
        
        @media (max-width: 768px) {
            .content-grid {
                grid-template-columns: 1fr;
            }
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
                <span>Admin: <?php echo Security::sanitizeOutput($user['username']); ?></span>
                <a href="users.php">Users</a>
                <a href="limits.php">Limits</a>
                <a href="reports.php">Reports</a>
                <a href="logs.php">Security Logs</a>
                <a href="../logout.php">Logout</a>
            </nav>
        </div>
    </header>
    
    <div class="container">
        <div class="welcome-section">
            <h1>Admin Dashboard</h1>
            <p>System overview and management tools</p>
        </div>
        
        <div class="stats-grid">
            <div class="stat-card blue">
                <div class="icon">👥</div>
                <h3>Total Users</h3>
                <div class="stat-value"><?php echo number_format($users_count); ?></div>
            </div>
            
            <div class="stat-card green">
                <div class="icon">⛽</div>
                <h3>Total Records</h3>
                <div class="stat-value"><?php echo number_format($records_count); ?></div>
            </div>
            
            <div class="stat-card orange">
                <div class="icon">💰</div>
                <h3>Monthly Spending</h3>
                <div class="stat-value"><?php echo formatCurrency($monthly_spending); ?></div>
            </div>
            
            <div class="stat-card red">
                <div class="icon">🎯</div>
                <h3>Limits Set</h3>
                <div class="stat-value"><?php echo number_format($limits_set); ?></div>
            </div>
        </div>
        
        <div class="quick-actions">
            <a href="add-user.php" class="action-card">
                <div class="icon">➕</div>
                <h4>Add User</h4>
                <p>Create new user account</p>
            </a>
            
            <a href="limits.php" class="action-card">
                <div class="icon">📊</div>
                <h4>Set Limits</h4>
                <p>Manage fuel limits</p>
            </a>
            
            <a href="reports.php" class="action-card">
                <div class="icon">📈</div>
                <h4>Reports</h4>
                <p>View system reports</p>
            </a>
            
            <a href="logs.php" class="action-card">
                <div class="icon">🔐</div>
                <h4>Security Logs</h4>
                <p>Monitor system security</p>
            </a>
            
            <a href="export.php" class="action-card">
                <div class="icon">💾</div>
                <h4>Export Data</h4>
                <p>Download reports</p>
            </a>
            
            <a href="settings.php" class="action-card">
                <div class="icon">⚙️</div>
                <h4>Settings</h4>
                <p>System configuration</p>
            </a>
        </div>
        
        <div class="content-grid">
            <div class="panel">
                <h2>⚠️ Users Approaching/Exceeding Limits</h2>
                <?php if (!empty($users_exceeding)): ?>
                    <ul class="warning-list">
                        <?php foreach ($users_exceeding as $user_warning): ?>
                            <li>
                                <div class="user-name">
                                    <?php echo Security::sanitizeOutput($user_warning['first_name'] . ' ' . $user_warning['last_name']); ?>
                                    (<?php echo Security::sanitizeOutput($user_warning['username']); ?>)
                                </div>
                                <div class="usage">
                                    Spent: <?php echo formatCurrency($user_warning['total_spent']); ?> / 
                                    Limit: <?php echo formatCurrency($user_warning['monthly_limit']); ?>
                                    (<?php echo number_format($user_warning['usage_percentage'], 1); ?>%)
                                </div>
                                <div class="progress-bar">
                                    <div class="progress-fill <?php echo $user_warning['usage_percentage'] >= 100 ? 'danger' : ''; ?>" 
                                         style="width: <?php echo min($user_warning['usage_percentage'], 100); ?>%"></div>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php else: ?>
                    <div class="empty-state">
                        <div class="icon">✅</div>
                        <p>All users are within their limits</p>
                    </div>
                <?php endif; ?>
            </div>
            
            <div class="panel">
                <h2>🔐 Recent Security Events</h2>
                <?php if (!empty($recent_events)): ?>
                    <ul class="event-log">
                        <?php foreach ($recent_events as $event): ?>
                            <li>
                                <?php
                                $event_class = 'info';
                                if (strpos($event['event_type'], 'FAILED') !== false || strpos($event['event_type'], 'LOCKED') !== false) {
                                    $event_class = 'error';
                                } elseif (strpos($event['event_type'], 'SUCCESS') !== false) {
                                    $event_class = 'success';
                                } elseif (strpos($event['event_type'], 'ATTEMPT') !== false) {
                                    $event_class = 'warning';
                                }
                                ?>
                                <span class="event-type <?php echo $event_class; ?>">
                                    <?php echo Security::sanitizeOutput($event['event_type']); ?>
                                </span>
                                <span class="event-time">
                                    <?php echo formatDate($event['created_at'], 'H:i'); ?>
                                </span>
                                <div style="margin-top: 5px; color: #666; font-size: 13px;">
                                    <?php 
                                    if ($event['username']) {
                                        echo 'User: ' . Security::sanitizeOutput($event['username']) . ' - ';
                                    }
                                    echo Security::sanitizeOutput($event['event_description']);
                                    ?>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                    <div style="text-align: center; margin-top: 15px;">
                        <a href="logs.php" style="color: #667eea; text-decoration: none; font-size: 14px;">View all logs →</a>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <div class="icon">📋</div>
                        <p>No recent events</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>