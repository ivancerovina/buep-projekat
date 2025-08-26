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

// Get current month's fuel consumption
$current_month = date('Y-m-01');
$sql = "SELECT SUM(total_cost) as total_spent, COUNT(*) as record_count, SUM(liters) as total_liters 
        FROM fuel_records 
        WHERE user_id = :user_id AND date >= :month_start";
$monthly_stats = $db->fetchOne($sql, [
    ':user_id' => $user['id'],
    ':month_start' => $current_month
]);

// Get current month's limit
$sql = "SELECT monthly_limit FROM fuel_limits 
        WHERE user_id = :user_id AND month_year = :month_year";
$limit = $db->fetchOne($sql, [
    ':user_id' => $user['id'],
    ':month_year' => $current_month
]);

$monthly_limit = $limit ? $limit['monthly_limit'] : 0;
$total_spent = $monthly_stats['total_spent'] ?? 0;
$usage_percentage = $monthly_limit > 0 ? calculatePercentage($total_spent, $monthly_limit) : 0;

// Get recent fuel records
$sql = "SELECT * FROM fuel_records 
        WHERE user_id = :user_id 
        ORDER BY date DESC, created_at DESC 
        LIMIT 5";
$recent_records = $db->fetchAll($sql, [':user_id' => $user['id']]);

// Generate CSRF token
$csrf_token = Security::generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - <?php echo APP_NAME; ?></title>
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
        
        .nav-menu a:hover {
            background: rgba(255,255,255,0.1);
        }
        
        .nav-menu .btn-logout {
            background: rgba(255,255,255,0.2);
            border: 1px solid rgba(255,255,255,0.3);
        }
        
        .container {
            max-width: 1200px;
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
        }
        
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
            margin-bottom: 10px;
        }
        
        .stat-info {
            color: #999;
            font-size: 14px;
        }
        
        .progress-bar {
            width: 100%;
            height: 10px;
            background: #e0e0e0;
            border-radius: 5px;
            overflow: hidden;
            margin-top: 10px;
        }
        
        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, #667eea, #764ba2);
            transition: width 0.3s;
        }
        
        .progress-fill.warning {
            background: linear-gradient(90deg, #FFA726, #FF7043);
        }
        
        .progress-fill.danger {
            background: linear-gradient(90deg, #EF5350, #E53935);
        }
        
        .actions-section {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .action-btn {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            text-align: center;
            text-decoration: none;
            color: #333;
            transition: transform 0.2s, box-shadow 0.2s;
        }
        
        .action-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 20px rgba(0,0,0,0.15);
        }
        
        .action-btn .icon {
            font-size: 32px;
            margin-bottom: 10px;
        }
        
        .action-btn h3 {
            font-size: 16px;
            margin-bottom: 5px;
        }
        
        .action-btn p {
            font-size: 14px;
            color: #666;
        }
        
        .recent-records {
            background: white;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .recent-records h2 {
            color: #333;
            margin-bottom: 20px;
        }
        
        .records-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .records-table th {
            text-align: left;
            padding: 10px;
            border-bottom: 2px solid #e0e0e0;
            color: #666;
            font-size: 14px;
            text-transform: uppercase;
        }
        
        .records-table td {
            padding: 15px 10px;
            border-bottom: 1px solid #f0f0f0;
        }
        
        .records-table tr:last-child td {
            border-bottom: none;
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
    </style>
</head>
<body>
    <header class="header">
        <div class="header-content">
            <div class="logo"><?php echo APP_NAME; ?></div>
            <nav class="nav-menu">
                <span>Welcome, <?php echo Security::sanitizeOutput($user_info['first_name'] . ' ' . $user_info['last_name']); ?></span>
                <a href="profile.php">Profile</a>
                <a href="fuel-records.php">My Records</a>
                <a href="logout.php" class="btn-logout">Logout</a>
            </nav>
        </div>
    </header>
    
    <div class="container">
        <div class="welcome-section">
            <h1>Welcome back, <?php echo Security::sanitizeOutput($user_info['first_name']); ?>!</h1>
            <p>Here's your fuel consumption overview for <?php echo getMonthName(date('n')) . ' ' . date('Y'); ?></p>
        </div>
        
        <div class="stats-grid">
            <div class="stat-card">
                <h3>This Month's Spending</h3>
                <div class="stat-value"><?php echo formatCurrency($total_spent); ?></div>
                <div class="stat-info">
                    <?php if ($monthly_limit > 0): ?>
                        Limit: <?php echo formatCurrency($monthly_limit); ?>
                    <?php else: ?>
                        No limit set
                    <?php endif; ?>
                </div>
                <?php if ($monthly_limit > 0): ?>
                    <div class="progress-bar">
                        <div class="progress-fill <?php 
                            echo $usage_percentage >= 100 ? 'danger' : 
                                ($usage_percentage >= 80 ? 'warning' : ''); 
                        ?>" style="width: <?php echo min($usage_percentage, 100); ?>%"></div>
                    </div>
                    <div class="stat-info" style="margin-top: 5px;">
                        <?php echo $usage_percentage; ?>% of limit used
                    </div>
                <?php endif; ?>
            </div>
            
            <div class="stat-card">
                <h3>Total Fuel Consumed</h3>
                <div class="stat-value"><?php echo number_format($monthly_stats['total_liters'] ?? 0, 2); ?> L</div>
                <div class="stat-info">This month</div>
            </div>
            
            <div class="stat-card">
                <h3>Records Added</h3>
                <div class="stat-value"><?php echo $monthly_stats['record_count'] ?? 0; ?></div>
                <div class="stat-info">This month</div>
            </div>
        </div>
        
        <div class="actions-section">
            <a href="add-fuel-record.php" class="action-btn">
                <div class="icon">⛽</div>
                <h3>Add Fuel Record</h3>
                <p>Record new fuel purchase</p>
            </a>
            
            <a href="fuel-records.php" class="action-btn">
                <div class="icon">📊</div>
                <h3>View All Records</h3>
                <p>See your fuel history</p>
            </a>
            
            <a href="reports.php" class="action-btn">
                <div class="icon">📈</div>
                <h3>Reports</h3>
                <p>Analyze your consumption</p>
            </a>
            
            <a href="profile.php" class="action-btn">
                <div class="icon">👤</div>
                <h3>My Profile</h3>
                <p>Update your information</p>
            </a>
        </div>
        
        <div class="recent-records">
            <h2>Recent Fuel Records</h2>
            <?php if (!empty($recent_records)): ?>
                <table class="records-table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Mileage</th>
                            <th>Liters</th>
                            <th>Price/L</th>
                            <th>Total Cost</th>
                            <th>Location</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recent_records as $record): ?>
                            <tr>
                                <td><?php echo formatDate($record['date'], 'd M Y'); ?></td>
                                <td><?php echo number_format($record['mileage'], 0); ?> km</td>
                                <td><?php echo number_format($record['liters'], 2); ?> L</td>
                                <td><?php echo formatCurrency($record['price_per_liter']); ?></td>
                                <td><strong><?php echo formatCurrency($record['total_cost']); ?></strong></td>
                                <td><?php echo Security::sanitizeOutput($record['location'] ?? '-'); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <div style="text-align: center; margin-top: 20px;">
                    <a href="fuel-records.php" style="color: #667eea; text-decoration: none;">View all records →</a>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <div class="icon">📝</div>
                    <p>No fuel records yet</p>
                    <p style="margin-top: 10px;">
                        <a href="add-fuel-record.php" style="color: #667eea; text-decoration: none;">Add your first record</a>
                    </p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>