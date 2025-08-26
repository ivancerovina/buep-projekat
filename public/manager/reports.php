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

// Get report parameters
$report_type = isset($_GET['type']) ? $_GET['type'] : 'team_monthly';
$month = isset($_GET['month']) ? $_GET['month'] : date('Y-m');
$year = isset($_GET['year']) ? $_GET['year'] : date('Y');

// Generate reports based on type
$report_data = [];

if ($report_type === 'team_monthly') {
    // Team monthly report
    $sql = "SELECT 
            u.id, u.username, u.first_name, u.last_name, u.role,
            COUNT(fr.id) as total_records,
            COALESCE(SUM(fr.liters), 0) as total_liters,
            COALESCE(SUM(fr.total_cost), 0) as total_cost,
            COALESCE(AVG(fr.price_per_liter), 0) as avg_price,
            fl.monthly_limit,
            CASE 
                WHEN fl.monthly_limit > 0 THEN (COALESCE(SUM(fr.total_cost), 0) / fl.monthly_limit * 100)
                ELSE 0
            END as usage_percentage
            FROM users u
            LEFT JOIN fuel_records fr ON u.id = fr.user_id AND DATE_FORMAT(fr.date, '%Y-%m') = :month
            LEFT JOIN fuel_limits fl ON u.id = fl.user_id AND fl.month_year = :month_year
            WHERE u.role IN ('employee', 'manager') AND u.is_active = 1
            GROUP BY u.id, fl.monthly_limit
            ORDER BY total_cost DESC";
    
    $report_data = $db->fetchAll($sql, [
        ':month' => $month,
        ':month_year' => $month . '-01'
    ]);
    
    // Calculate totals
    $totals = [
        'total_cost' => array_sum(array_column($report_data, 'total_cost')),
        'total_liters' => array_sum(array_column($report_data, 'total_liters')),
        'total_records' => array_sum(array_column($report_data, 'total_records'))
    ];
    
} elseif ($report_type === 'team_comparison') {
    // Team comparison by role
    $sql = "SELECT 
            u.role,
            COUNT(DISTINCT u.id) as user_count,
            COUNT(fr.id) as total_records,
            COALESCE(SUM(fr.liters), 0) as total_liters,
            COALESCE(SUM(fr.total_cost), 0) as total_cost,
            COALESCE(AVG(fr.price_per_liter), 0) as avg_price,
            COALESCE(AVG(fr.total_cost), 0) as avg_cost_per_user
            FROM users u
            LEFT JOIN fuel_records fr ON u.id = fr.user_id AND DATE_FORMAT(fr.date, '%Y-%m') = :month
            WHERE u.role IN ('employee', 'manager') AND u.is_active = 1
            GROUP BY u.role
            ORDER BY total_cost DESC";
    
    $report_data = $db->fetchAll($sql, [':month' => $month]);
    
} elseif ($report_type === 'efficiency') {
    // Efficiency report (cost per kilometer)
    $sql = "SELECT 
            u.id, u.username, u.first_name, u.last_name,
            COUNT(fr.id) as total_records,
            MIN(fr.mileage) as min_mileage,
            MAX(fr.mileage) as max_mileage,
            (MAX(fr.mileage) - MIN(fr.mileage)) as distance_traveled,
            SUM(fr.total_cost) as total_cost,
            SUM(fr.liters) as total_liters,
            CASE 
                WHEN (MAX(fr.mileage) - MIN(fr.mileage)) > 0 
                THEN SUM(fr.total_cost) / (MAX(fr.mileage) - MIN(fr.mileage))
                ELSE 0
            END as cost_per_km,
            CASE 
                WHEN (MAX(fr.mileage) - MIN(fr.mileage)) > 0 
                THEN (MAX(fr.mileage) - MIN(fr.mileage)) / SUM(fr.liters)
                ELSE 0
            END as km_per_liter
            FROM users u
            INNER JOIN fuel_records fr ON u.id = fr.user_id
            WHERE u.role IN ('employee', 'manager') 
            AND DATE_FORMAT(fr.date, '%Y-%m') = :month
            GROUP BY u.id
            HAVING total_records > 1 AND distance_traveled > 0
            ORDER BY cost_per_km ASC";
    
    $report_data = $db->fetchAll($sql, [':month' => $month]);
}

// Get top performers
$sql = "SELECT 
        u.username, u.first_name, u.last_name,
        COUNT(fr.id) as record_count,
        SUM(fr.total_cost) as total_cost
        FROM users u
        INNER JOIN fuel_records fr ON u.id = fr.user_id
        WHERE u.role IN ('employee', 'manager')
        AND DATE_FORMAT(fr.date, '%Y-%m') = :month
        GROUP BY u.id
        ORDER BY record_count DESC
        LIMIT 5";

$top_performers = $db->fetchAll($sql, [':month' => $month]);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Team Reports - <?php echo APP_NAME; ?></title>
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
        
        .nav-menu a:hover,
        .nav-menu a.active {
            background: rgba(255,255,255,0.1);
        }
        
        .container {
            max-width: 1400px;
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
        
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s;
            text-decoration: none;
            display: inline-block;
        }
        
        .btn-export {
            background: #4CAF50;
            color: white;
        }
        
        .btn-export:hover {
            background: #45a049;
        }
        
        .report-filters {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 30px;
        }
        
        .report-filters form {
            display: flex;
            gap: 15px;
            align-items: end;
            flex-wrap: wrap;
        }
        
        .filter-group {
            flex: 1;
            min-width: 150px;
        }
        
        .filter-group label {
            display: block;
            margin-bottom: 5px;
            color: #666;
            font-size: 14px;
        }
        
        .filter-group select,
        .filter-group input {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #4CAF50 0%, #45a049 100%);
            color: white;
        }
        
        .stats-summary {
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
        }
        
        .report-content {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 30px;
        }
        
        .report-table {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        
        .report-table h2 {
            padding: 20px;
            background: #f9f9f9;
            border-bottom: 2px solid #e0e0e0;
            color: #333;
            font-size: 18px;
        }
        
        .table-responsive {
            overflow-x: auto;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
        }
        
        th {
            background: #f9f9f9;
            text-align: left;
            padding: 12px 15px;
            color: #666;
            font-size: 12px;
            text-transform: uppercase;
            font-weight: 600;
            border-bottom: 2px solid #e0e0e0;
        }
        
        td {
            padding: 12px 15px;
            border-bottom: 1px solid #f0f0f0;
        }
        
        tr:hover {
            background: #f9f9f9;
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
        
        .performers-list {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            padding: 20px;
        }
        
        .performers-list h2 {
            color: #333;
            font-size: 18px;
            margin-bottom: 20px;
        }
        
        .performer-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px 0;
            border-bottom: 1px solid #f0f0f0;
        }
        
        .performer-item:last-child {
            border-bottom: none;
        }
        
        .performer-rank {
            width: 30px;
            height: 30px;
            background: linear-gradient(135deg, #4CAF50, #45a049);
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 14px;
        }
        
        .performer-info {
            flex: 1;
            margin-left: 15px;
        }
        
        .performer-name {
            font-weight: 500;
            color: #333;
        }
        
        .performer-stats {
            font-size: 12px;
            color: #666;
        }
        
        .performer-value {
            font-weight: bold;
            color: #4CAF50;
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
                <a href="dashboard.php">Dashboard</a>
                <a href="reports.php" class="active">Team Reports</a>
                <a href="../profile.php">Profile</a>
                <a href="../logout.php">Logout</a>
            </nav>
        </div>
    </header>
    
    <div class="container">
        <div class="page-header">
            <div class="page-title">
                <h1>Team Reports</h1>
                <p>Comprehensive team fuel consumption analysis</p>
            </div>
        </div>
        
        <div class="report-filters">
            <form method="GET" action="">
                <div class="filter-group">
                    <label for="type">Report Type</label>
                    <select name="type" id="type" onchange="toggleFilters(this.value)">
                        <option value="team_monthly" <?php echo $report_type === 'team_monthly' ? 'selected' : ''; ?>>Team Monthly</option>
                        <option value="team_comparison" <?php echo $report_type === 'team_comparison' ? 'selected' : ''; ?>>Role Comparison</option>
                        <option value="efficiency" <?php echo $report_type === 'efficiency' ? 'selected' : ''; ?>>Efficiency Report</option>
                    </select>
                </div>
                
                <div class="filter-group" id="month-filter">
                    <label for="month">Month</label>
                    <input type="month" 
                           name="month" 
                           id="month" 
                           value="<?php echo $month; ?>"
                           max="<?php echo date('Y-m'); ?>">
                </div>
                
                <button type="submit" class="btn btn-primary">Generate Report</button>
            </form>
        </div>
        
        <?php if (isset($totals)): ?>
            <div class="stats-summary">
                <div class="stat-card">
                    <h3>Total Team Spending</h3>
                    <div class="value"><?php echo formatCurrency($totals['total_cost']); ?></div>
                </div>
                
                <div class="stat-card">
                    <h3>Total Fuel Consumed</h3>
                    <div class="value"><?php echo number_format($totals['total_liters'], 2); ?> L</div>
                </div>
                
                <div class="stat-card">
                    <h3>Total Records</h3>
                    <div class="value"><?php echo number_format($totals['total_records']); ?></div>
                </div>
            </div>
        <?php endif; ?>
        
        <div class="report-content">
            <div class="report-table">
                <h2>
                    <?php 
                    if ($report_type === 'team_monthly') {
                        echo 'Team Monthly Report - ' . date('F Y', strtotime($month . '-01'));
                    } elseif ($report_type === 'team_comparison') {
                        echo 'Role Comparison - ' . date('F Y', strtotime($month . '-01'));
                    } else {
                        echo 'Efficiency Report - ' . date('F Y', strtotime($month . '-01'));
                    }
                    ?>
                </h2>
                
                <div class="table-responsive">
                    <table>
                        <?php if ($report_type === 'team_monthly'): ?>
                            <thead>
                                <tr>
                                    <th>Team Member</th>
                                    <th>Role</th>
                                    <th>Records</th>
                                    <th>Liters</th>
                                    <th>Total Cost</th>
                                    <th>Usage</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($report_data as $row): ?>
                                    <tr>
                                        <td><?php echo Security::sanitizeOutput($row['first_name'] . ' ' . $row['last_name']); ?></td>
                                        <td><?php echo ucfirst($row['role']); ?></td>
                                        <td><?php echo $row['total_records']; ?></td>
                                        <td><?php echo number_format($row['total_liters'], 2); ?> L</td>
                                        <td><strong><?php echo formatCurrency($row['total_cost']); ?></strong></td>
                                        <td>
                                            <?php if ($row['monthly_limit'] > 0): ?>
                                                <?php 
                                                $progress_class = '';
                                                if ($row['usage_percentage'] >= 100) {
                                                    $progress_class = 'danger';
                                                } elseif ($row['usage_percentage'] >= 80) {
                                                    $progress_class = 'warning';
                                                }
                                                ?>
                                                <div style="display: flex; align-items: center; gap: 10px;">
                                                    <div class="progress-bar">
                                                        <div class="progress-fill <?php echo $progress_class; ?>" 
                                                             style="width: <?php echo min($row['usage_percentage'], 100); ?>%"></div>
                                                    </div>
                                                    <span><?php echo number_format($row['usage_percentage'], 1); ?>%</span>
                                                </div>
                                            <?php else: ?>
                                                No limit
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                            
                        <?php elseif ($report_type === 'team_comparison'): ?>
                            <thead>
                                <tr>
                                    <th>Role</th>
                                    <th>Team Members</th>
                                    <th>Records</th>
                                    <th>Total Cost</th>
                                    <th>Avg per Person</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($report_data as $row): ?>
                                    <tr>
                                        <td><?php echo ucfirst($row['role']); ?></td>
                                        <td><?php echo $row['user_count']; ?></td>
                                        <td><?php echo $row['total_records']; ?></td>
                                        <td><strong><?php echo formatCurrency($row['total_cost']); ?></strong></td>
                                        <td><?php echo formatCurrency($row['avg_cost_per_user']); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                            
                        <?php else: ?>
                            <thead>
                                <tr>
                                    <th>Team Member</th>
                                    <th>Distance (km)</th>
                                    <th>Cost/km</th>
                                    <th>km/L</th>
                                    <th>Efficiency</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($report_data as $row): ?>
                                    <tr>
                                        <td><?php echo Security::sanitizeOutput($row['first_name'] . ' ' . $row['last_name']); ?></td>
                                        <td><?php echo number_format($row['distance_traveled'], 0); ?></td>
                                        <td><?php echo formatCurrency($row['cost_per_km']); ?></td>
                                        <td><?php echo number_format($row['km_per_liter'], 2); ?></td>
                                        <td>
                                            <?php 
                                            if ($row['km_per_liter'] >= 10) {
                                                echo '<span style="color: #4CAF50;">Excellent</span>';
                                            } elseif ($row['km_per_liter'] >= 8) {
                                                echo '<span style="color: #FFA726;">Good</span>';
                                            } else {
                                                echo '<span style="color: #EF5350;">Needs Improvement</span>';
                                            }
                                            ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        <?php endif; ?>
                    </table>
                </div>
            </div>
            
            <div class="performers-list">
                <h2>🏆 Top Performers</h2>
                <?php if (!empty($top_performers)): ?>
                    <?php foreach ($top_performers as $index => $performer): ?>
                        <div class="performer-item">
                            <div style="display: flex; align-items: center;">
                                <span class="performer-rank"><?php echo $index + 1; ?></span>
                                <div class="performer-info">
                                    <div class="performer-name">
                                        <?php echo Security::sanitizeOutput($performer['first_name'] . ' ' . $performer['last_name']); ?>
                                    </div>
                                    <div class="performer-stats">
                                        <?php echo $performer['record_count']; ?> records
                                    </div>
                                </div>
                            </div>
                            <span class="performer-value"><?php echo formatCurrency($performer['total_cost']); ?></span>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p style="color: #999; text-align: center; padding: 20px;">No data available for this period</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <script>
        function toggleFilters(type) {
            // All report types use month filter for now
        }
    </script>
</body>
</html>