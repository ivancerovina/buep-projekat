<?php
define('APP_RUNNING', true);
require_once '../../config/config.php';

// Set security headers
setSecurityHeaders();

// Start session
Auth::startSecureSession();

// Require manager role
Auth::requireRole('manager');

// Get current user
$user = Auth::getCurrentUser();
$db = Database::getInstance();

// Get team statistics (all employees and managers for this example)
// In a real system, you might have departments or teams assigned to managers
$sql = "SELECT COUNT(*) as total FROM users WHERE role IN ('employee', 'manager') AND is_active = 1";
$team_count = $db->fetchOne($sql)['total'];

// Get current month's team consumption
$current_month = date('Y-m-01');
$sql = "SELECT 
        COUNT(DISTINCT fr.user_id) as active_users,
        COUNT(fr.id) as total_records,
        SUM(fr.total_cost) as total_spending,
        SUM(fr.liters) as total_liters,
        AVG(fr.price_per_liter) as avg_price
        FROM fuel_records fr
        INNER JOIN users u ON fr.user_id = u.id
        WHERE u.role IN ('employee', 'manager') 
        AND DATE_FORMAT(fr.date, '%Y-%m') = DATE_FORMAT(NOW(), '%Y-%m')";
$monthly_stats = $db->fetchOne($sql);

// Get team members with their consumption
$sql = "SELECT 
        u.id, u.username, u.first_name, u.last_name, u.role,
        COUNT(fr.id) as record_count,
        COALESCE(SUM(fr.total_cost), 0) as monthly_spending,
        COALESCE(SUM(fr.liters), 0) as monthly_liters,
        fl.monthly_limit,
        CASE 
            WHEN fl.monthly_limit > 0 THEN (COALESCE(SUM(fr.total_cost), 0) / fl.monthly_limit * 100)
            ELSE 0
        END as usage_percentage
        FROM users u
        LEFT JOIN fuel_records fr ON u.id = fr.user_id AND DATE_FORMAT(fr.date, '%Y-%m') = DATE_FORMAT(NOW(), '%Y-%m')
        LEFT JOIN fuel_limits fl ON u.id = fl.user_id AND fl.month_year = :current_month
        WHERE u.role IN ('employee', 'manager') AND u.is_active = 1
        GROUP BY u.id, fl.monthly_limit
        ORDER BY monthly_spending DESC
        LIMIT 10";
$team_members = $db->fetchAll($sql, [':current_month' => $current_month]);

// Get monthly consumption trend
$sql = "SELECT 
        DATE_FORMAT(fr.date, '%Y-%m') as month,
        SUM(fr.total_cost) as total_cost,
        COUNT(DISTINCT fr.user_id) as active_users
        FROM fuel_records fr
        INNER JOIN users u ON fr.user_id = u.id
        WHERE u.role IN ('employee', 'manager') 
        AND fr.date >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
        GROUP BY DATE_FORMAT(fr.date, '%Y-%m')
        ORDER BY month DESC
        LIMIT 6";
$monthly_trend = array_reverse($db->fetchAll($sql));

// Get users approaching limits
$sql = "SELECT 
        u.id, u.username, u.first_name, u.last_name,
        fl.monthly_limit,
        COALESCE(SUM(fr.total_cost), 0) as total_spent,
        (COALESCE(SUM(fr.total_cost), 0) / fl.monthly_limit * 100) as usage_percentage
        FROM users u
        INNER JOIN fuel_limits fl ON u.id = fl.user_id AND fl.month_year = :current_month
        LEFT JOIN fuel_records fr ON u.id = fr.user_id AND DATE_FORMAT(fr.date, '%Y-%m') = DATE_FORMAT(NOW(), '%Y-%m')
        WHERE u.role IN ('employee', 'manager') AND fl.monthly_limit > 0
        GROUP BY u.id, fl.monthly_limit
        HAVING usage_percentage >= 80
        ORDER BY usage_percentage DESC";
$users_approaching_limit = $db->fetchAll($sql, [':current_month' => $current_month]);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manager Dashboard - <?php echo APP_NAME; ?></title>
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
            background: linear-gradient(135deg, #4CAF50 0%, #45a049 100%);
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
        
        .stat-card.green .icon { background: linear-gradient(135deg, #4CAF50, #45a049); color: white; }
        .stat-card.blue .icon { background: linear-gradient(135deg, #2196F3, #1976D2); color: white; }
        .stat-card.orange .icon { background: linear-gradient(135deg, #FF9800, #F57C00); color: white; }
        .stat-card.purple .icon { background: linear-gradient(135deg, #9C27B0, #7B1FA2); color: white; }
        
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
        
        .content-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 30px;
            margin-bottom: 30px;
        }
        
        .panel {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        
        .panel h2 {
            background: #f9f9f9;
            padding: 20px;
            color: #333;
            font-size: 18px;
            border-bottom: 2px solid #e0e0e0;
        }
        
        .panel-content {
            padding: 20px;
        }
        
        .team-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .team-table th {
            text-align: left;
            padding: 12px;
            color: #666;
            font-size: 12px;
            text-transform: uppercase;
            font-weight: 600;
            border-bottom: 2px solid #e0e0e0;
        }
        
        .team-table td {
            padding: 12px;
            border-bottom: 1px solid #f0f0f0;
        }
        
        .team-table tr:hover {
            background: #f9f9f9;
        }
        
        .member-info .name {
            font-weight: 500;
            color: #333;
        }
        
        .member-info .role {
            font-size: 12px;
            color: #666;
            text-transform: uppercase;
        }
        
        .progress-bar {
            width: 100px;
            height: 8px;
            background: #e0e0e0;
            border-radius: 4px;
            overflow: hidden;
        }
        
        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, #4CAF50, #45a049);
            transition: width 0.3s;
        }
        
        .progress-fill.warning {
            background: linear-gradient(90deg, #FFA726, #FF7043);
        }
        
        .progress-fill.danger {
            background: linear-gradient(90deg, #EF5350, #E53935);
        }
        
        .alert-list {
            list-style: none;
        }
        
        .alert-list li {
            padding: 15px;
            border-left: 4px solid #FF7043;
            background: #FFF3E0;
            margin-bottom: 10px;
            border-radius: 5px;
        }
        
        .alert-list .user-name {
            font-weight: bold;
            color: #333;
        }
        
        .alert-list .usage {
            color: #666;
            font-size: 14px;
            margin-top: 5px;
        }
        
        .trend-chart {
            height: 200px;
            display: flex;
            align-items: end;
            gap: 10px;
            padding: 20px 0;
        }
        
        .trend-bar {
            flex: 1;
            background: linear-gradient(to top, #4CAF50, #81C784);
            border-radius: 4px 4px 0 0;
            position: relative;
            min-height: 20px;
        }
        
        .trend-bar .label {
            position: absolute;
            bottom: -25px;
            left: 50%;
            transform: translateX(-50%);
            font-size: 12px;
            color: #666;
        }
        
        .trend-bar .value {
            position: absolute;
            top: -20px;
            left: 50%;
            transform: translateX(-50%);
            font-size: 12px;
            color: #333;
            font-weight: bold;
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
        
        .actions-section {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
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
        
        .action-btn h4 {
            font-size: 16px;
            margin-bottom: 5px;
        }
        
        .action-btn p {
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
                <span class="badge">MANAGER</span>
            </div>
            <nav class="nav-menu">
                <span>Manager: <?php echo Security::sanitizeOutput($user['username']); ?></span>
                <a href="reports.php">Team Reports</a>
                <a href="../profile.php">Profile</a>
                <a href="../fuel-records.php">My Records</a>
                <a href="../logout.php">Logout</a>
            </nav>
        </div>
    </header>
    
    <div class="container">
        <div class="welcome-section">
            <h1>Manager Dashboard</h1>
            <p>Team overview and consumption monitoring for <?php echo getMonthName(date('n')) . ' ' . date('Y'); ?></p>
        </div>
        
        <div class="stats-grid">
            <div class="stat-card green">
                <div class="icon">👥</div>
                <h3>Team Members</h3>
                <div class="stat-value"><?php echo number_format($team_count); ?></div>
            </div>
            
            <div class="stat-card blue">
                <div class="icon">📊</div>
                <h3>Active Users</h3>
                <div class="stat-value"><?php echo number_format($monthly_stats['active_users'] ?? 0); ?></div>
            </div>
            
            <div class="stat-card orange">
                <div class="icon">💰</div>
                <h3>Team Spending</h3>
                <div class="stat-value"><?php echo formatCurrency($monthly_stats['total_spending'] ?? 0); ?></div>
            </div>
            
            <div class="stat-card purple">
                <div class="icon">⛽</div>
                <h3>Total Fuel</h3>
                <div class="stat-value"><?php echo number_format($monthly_stats['total_liters'] ?? 0, 0); ?> L</div>
            </div>
        </div>
        
        <div class="content-grid">
            <div class="panel">
                <h2>👥 Team Performance</h2>
                <div class="panel-content">
                    <?php if (!empty($team_members)): ?>
                        <table class="team-table">
                            <thead>
                                <tr>
                                    <th>Team Member</th>
                                    <th>Records</th>
                                    <th>Spending</th>
                                    <th>Limit Usage</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($team_members as $member): ?>
                                    <tr>
                                        <td>
                                            <div class="member-info">
                                                <div class="name"><?php echo Security::sanitizeOutput($member['first_name'] . ' ' . $member['last_name']); ?></div>
                                                <div class="role"><?php echo $member['role']; ?></div>
                                            </div>
                                        </td>
                                        <td><?php echo $member['record_count']; ?></td>
                                        <td><?php echo formatCurrency($member['monthly_spending']); ?></td>
                                        <td>
                                            <?php if ($member['monthly_limit'] > 0): ?>
                                                <?php 
                                                $progress_class = '';
                                                if ($member['usage_percentage'] >= 100) {
                                                    $progress_class = 'danger';
                                                } elseif ($member['usage_percentage'] >= 80) {
                                                    $progress_class = 'warning';
                                                }
                                                ?>
                                                <div style="display: flex; align-items: center; gap: 10px;">
                                                    <div class="progress-bar">
                                                        <div class="progress-fill <?php echo $progress_class; ?>" 
                                                             style="width: <?php echo min($member['usage_percentage'], 100); ?>%"></div>
                                                    </div>
                                                    <span><?php echo number_format($member['usage_percentage'], 1); ?>%</span>
                                                </div>
                                            <?php else: ?>
                                                No limit
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <div class="empty-state">
                            <div class="icon">👥</div>
                            <p>No team members found</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="panel">
                <h2>⚠️ Limit Alerts</h2>
                <div class="panel-content">
                    <?php if (!empty($users_approaching_limit)): ?>
                        <ul class="alert-list">
                            <?php foreach ($users_approaching_limit as $alert): ?>
                                <li>
                                    <div class="user-name">
                                        <?php echo Security::sanitizeOutput($alert['first_name'] . ' ' . $alert['last_name']); ?>
                                    </div>
                                    <div class="usage">
                                        <?php echo number_format($alert['usage_percentage'], 1); ?>% of limit used
                                        (<?php echo formatCurrency($alert['total_spent']); ?> / <?php echo formatCurrency($alert['monthly_limit']); ?>)
                                    </div>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php else: ?>
                        <div class="empty-state">
                            <div class="icon">✅</div>
                            <p>All team members within limits</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <?php if (!empty($monthly_trend)): ?>
            <div class="panel">
                <h2>📈 6-Month Trend</h2>
                <div class="panel-content">
                    <div class="trend-chart">
                        <?php 
                        $max_value = max(array_column($monthly_trend, 'total_cost'));
                        foreach ($monthly_trend as $month): 
                            $height = $max_value > 0 ? ($month['total_cost'] / $max_value) * 160 : 20;
                        ?>
                            <div class="trend-bar" style="height: <?php echo $height; ?>px;">
                                <div class="value"><?php echo formatCurrency($month['total_cost']); ?></div>
                                <div class="label"><?php echo date('M', strtotime($month['month'] . '-01')); ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>
        
        <div class="actions-section">
            <a href="reports.php" class="action-btn">
                <div class="icon">📊</div>
                <h4>Team Reports</h4>
                <p>Detailed consumption analysis</p>
            </a>
            
            <a href="../fuel-records.php" class="action-btn">
                <div class="icon">⛽</div>
                <h4>My Records</h4>
                <p>Manage your fuel records</p>
            </a>
            
            <a href="../add-fuel-record.php" class="action-btn">
                <div class="icon">➕</div>
                <h4>Add Record</h4>
                <p>Record new fuel purchase</p>
            </a>
            
            <a href="../profile.php" class="action-btn">
                <div class="icon">👤</div>
                <h4>My Profile</h4>
                <p>Update account settings</p>
            </a>
        </div>
    </div>
</body>
</html>