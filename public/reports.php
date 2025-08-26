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

// Get report parameters
$report_type = isset($_GET['type']) ? $_GET['type'] : 'monthly';
$month = isset($_GET['month']) ? $_GET['month'] : date('Y-m');
$year = isset($_GET['year']) ? $_GET['year'] : date('Y');

// Generate reports based on type
$report_data = [];

if ($report_type === 'monthly') {
    // Monthly consumption report
    $sql = "SELECT 
            DATE_FORMAT(date, '%Y-%m') as month,
            COUNT(*) as record_count,
            SUM(liters) as total_liters,
            SUM(total_cost) as total_cost,
            AVG(price_per_liter) as avg_price,
            MAX(mileage) - MIN(mileage) as distance_traveled
            FROM fuel_records 
            WHERE user_id = :user_id
            AND date >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
            GROUP BY DATE_FORMAT(date, '%Y-%m')
            ORDER BY month DESC";
    
    $report_data = $db->fetchAll($sql, [':user_id' => $user['id']]);
    
} elseif ($report_type === 'yearly') {
    // Yearly consumption report
    $sql = "SELECT 
            YEAR(date) as year,
            COUNT(*) as record_count,
            SUM(liters) as total_liters,
            SUM(total_cost) as total_cost,
            AVG(price_per_liter) as avg_price,
            MAX(mileage) - MIN(mileage) as distance_traveled
            FROM fuel_records 
            WHERE user_id = :user_id
            GROUP BY YEAR(date)
            ORDER BY year DESC";
    
    $report_data = $db->fetchAll($sql, [':user_id' => $user['id']]);
    
} elseif ($report_type === 'efficiency') {
    // Monthly efficiency report
    $sql = "SELECT 
            DATE_FORMAT(date, '%Y-%m') as month,
            COUNT(*) as record_count,
            SUM(liters) as total_liters,
            SUM(total_cost) as total_cost,
            MAX(mileage) - MIN(mileage) as distance_traveled,
            CASE 
                WHEN (MAX(mileage) - MIN(mileage)) > 0 
                THEN SUM(total_cost) / (MAX(mileage) - MIN(mileage))
                ELSE 0
            END as cost_per_km,
            CASE 
                WHEN (MAX(mileage) - MIN(mileage)) > 0 
                THEN (MAX(mileage) - MIN(mileage)) / SUM(liters)
                ELSE 0
            END as km_per_liter
            FROM fuel_records 
            WHERE user_id = :user_id
            AND date >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
            GROUP BY DATE_FORMAT(date, '%Y-%m')
            HAVING record_count > 1 AND distance_traveled > 0
            ORDER BY month DESC";
    
    $report_data = $db->fetchAll($sql, [':user_id' => $user['id']]);
    
} elseif ($report_type === 'custom') {
    // Custom date range report
    $start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01');
    $end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-t');
    
    $sql = "SELECT 
            date,
            mileage,
            liters,
            price_per_liter,
            total_cost,
            location,
            notes
            FROM fuel_records 
            WHERE user_id = :user_id
            AND date BETWEEN :start_date AND :end_date
            ORDER BY date DESC";
    
    $report_data = $db->fetchAll($sql, [
        ':user_id' => $user['id'],
        ':start_date' => $start_date,
        ':end_date' => $end_date
    ]);
    
    // Calculate summary for custom range
    $sql = "SELECT 
            COUNT(*) as record_count,
            SUM(liters) as total_liters,
            SUM(total_cost) as total_cost,
            AVG(price_per_liter) as avg_price,
            MAX(mileage) - MIN(mileage) as distance_traveled
            FROM fuel_records 
            WHERE user_id = :user_id
            AND date BETWEEN :start_date AND :end_date";
    
    $summary = $db->fetchOne($sql, [
        ':user_id' => $user['id'],
        ':start_date' => $start_date,
        ':end_date' => $end_date
    ]);
}

// Get overall statistics
$sql = "SELECT 
        COUNT(*) as total_records,
        SUM(total_cost) as total_spent,
        SUM(liters) as total_liters,
        AVG(price_per_liter) as avg_price,
        MIN(date) as first_record,
        MAX(date) as last_record,
        MAX(mileage) - MIN(mileage) as total_distance
        FROM fuel_records WHERE user_id = :user_id";
$overall_stats = $db->fetchOne($sql, [':user_id' => $user['id']]);

// Get current month limit
$current_month = date('Y-m-01');
$sql = "SELECT monthly_limit FROM fuel_limits WHERE user_id = :user_id AND month_year = :month_year";
$limit = $db->fetchOne($sql, [':user_id' => $user['id'], ':month_year' => $current_month]);
$monthly_limit = $limit ? $limit['monthly_limit'] : null;

// Get current month spending
$sql = "SELECT SUM(total_cost) as total_spent FROM fuel_records 
        WHERE user_id = :user_id AND DATE_FORMAT(date, '%Y-%m') = DATE_FORMAT(NOW(), '%Y-%m')";
$current_spending = $db->fetchOne($sql, [':user_id' => $user['id']]);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Reports - <?php echo APP_NAME; ?></title>
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
            max-width: 1200px;
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
        
        .stats-overview {
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
            font-size: 24px;
            font-weight: bold;
            color: #333;
            margin-bottom: 5px;
        }
        
        .stat-card .detail {
            color: #999;
            font-size: 14px;
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
        
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        
        .btn-primary:hover {
            transform: translateY(-1px);
        }
        
        .report-content {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        
        .report-content h2 {
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
        
        .efficiency-indicator {
            padding: 2px 8px;
            border-radius: 3px;
            font-size: 12px;
            font-weight: bold;
        }
        
        .efficiency-excellent {
            background: #e8f5e8;
            color: #4caf50;
        }
        
        .efficiency-good {
            background: #fff3e0;
            color: #ff9800;
        }
        
        .efficiency-poor {
            background: #ffeaea;
            color: #f44336;
        }
        
        .chart-container {
            padding: 20px;
            height: 300px;
            display: flex;
            align-items: end;
            gap: 10px;
        }
        
        .chart-bar {
            flex: 1;
            background: linear-gradient(to top, #667eea, #8a94ff);
            border-radius: 4px 4px 0 0;
            position: relative;
            min-height: 20px;
        }
        
        .chart-bar .label {
            position: absolute;
            bottom: -25px;
            left: 50%;
            transform: translateX(-50%);
            font-size: 12px;
            color: #666;
            writing-mode: horizontal-tb;
        }
        
        .chart-bar .value {
            position: absolute;
            top: -20px;
            left: 50%;
            transform: translateX(-50%);
            font-size: 11px;
            color: #333;
            font-weight: bold;
            white-space: nowrap;
        }
        
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #999;
        }
        
        .empty-state .icon {
            font-size: 48px;
            margin-bottom: 15px;
        }
        
        .progress-bar {
            width: 100%;
            height: 8px;
            background: #e0e0e0;
            border-radius: 4px;
            overflow: hidden;
            margin-bottom: 10px;
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
    </style>
</head>
<body>
    <header class="header">
        <div class="header-content">
            <div class="logo"><?php echo APP_NAME; ?></div>
            <nav class="nav-menu">
                <a href="dashboard.php">Dashboard</a>
                <a href="fuel-records.php">My Records</a>
                <a href="reports.php" class="active">My Reports</a>
                <a href="profile.php">Profile</a>
                <a href="logout.php">Logout</a>
            </nav>
        </div>
    </header>
    
    <div class="container">
        <div class="page-header">
            <div class="page-title">
                <h1>My Fuel Reports</h1>
                <p>Analyze your fuel consumption patterns and efficiency</p>
            </div>
        </div>
        
        <div class="stats-overview">
            <div class="stat-card">
                <h3>Total Records</h3>
                <div class="value"><?php echo number_format($overall_stats['total_records'] ?? 0); ?></div>
                <div class="detail">All time fuel purchases</div>
            </div>
            
            <div class="stat-card">
                <h3>Total Spent</h3>
                <div class="value"><?php echo formatCurrency($overall_stats['total_spent'] ?? 0); ?></div>
                <div class="detail">Lifetime fuel costs</div>
            </div>
            
            <div class="stat-card">
                <h3>Total Distance</h3>
                <div class="value"><?php echo number_format($overall_stats['total_distance'] ?? 0, 0); ?> km</div>
                <div class="detail">Distance tracked</div>
            </div>
            
            <div class="stat-card">
                <h3>This Month</h3>
                <div class="value"><?php echo formatCurrency($current_spending['total_spent'] ?? 0); ?></div>
                <?php if ($monthly_limit): ?>
                    <?php 
                    $usage_percentage = ($current_spending['total_spent'] / $monthly_limit) * 100;
                    $progress_class = '';
                    if ($usage_percentage >= 100) {
                        $progress_class = 'danger';
                    } elseif ($usage_percentage >= 80) {
                        $progress_class = 'warning';
                    }
                    ?>
                    <div class="progress-bar">
                        <div class="progress-fill <?php echo $progress_class; ?>" 
                             style="width: <?php echo min($usage_percentage, 100); ?>%"></div>
                    </div>
                    <div class="detail"><?php echo number_format($usage_percentage, 1); ?>% of <?php echo formatCurrency($monthly_limit); ?> limit</div>
                <?php else: ?>
                    <div class="detail">No limit set</div>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="report-filters">
            <form method="GET" action="">
                <div class="filter-group">
                    <label for="type">Report Type</label>
                    <select name="type" id="type" onchange="toggleFilters(this.value)">
                        <option value="monthly" <?php echo $report_type === 'monthly' ? 'selected' : ''; ?>>Monthly Trend</option>
                        <option value="yearly" <?php echo $report_type === 'yearly' ? 'selected' : ''; ?>>Yearly Summary</option>
                        <option value="efficiency" <?php echo $report_type === 'efficiency' ? 'selected' : ''; ?>>Efficiency Analysis</option>
                        <option value="custom" <?php echo $report_type === 'custom' ? 'selected' : ''; ?>>Custom Date Range</option>
                    </select>
                </div>
                
                <div class="filter-group" id="start-date-filter" style="<?php echo $report_type === 'custom' ? '' : 'display: none;'; ?>">
                    <label for="start_date">Start Date</label>
                    <input type="date" 
                           name="start_date" 
                           id="start_date" 
                           value="<?php echo $_GET['start_date'] ?? date('Y-m-01'); ?>">
                </div>
                
                <div class="filter-group" id="end-date-filter" style="<?php echo $report_type === 'custom' ? '' : 'display: none;'; ?>">
                    <label for="end_date">End Date</label>
                    <input type="date" 
                           name="end_date" 
                           id="end_date" 
                           value="<?php echo $_GET['end_date'] ?? date('Y-m-t'); ?>"
                           max="<?php echo date('Y-m-d'); ?>">
                </div>
                
                <button type="submit" class="btn btn-primary">Generate Report</button>
            </form>
        </div>
        
        <div class="report-content">
            <h2>
                <?php 
                if ($report_type === 'monthly') {
                    echo '📊 Monthly Consumption Trend';
                } elseif ($report_type === 'yearly') {
                    echo '📅 Yearly Summary Report';
                } elseif ($report_type === 'efficiency') {
                    echo '⚡ Fuel Efficiency Analysis';
                } else {
                    echo '📋 Custom Date Range Report';
                }
                ?>
            </h2>
            
            <?php if (!empty($report_data)): ?>
                <?php if ($report_type === 'monthly' || $report_type === 'yearly'): ?>
                    <div class="chart-container">
                        <?php 
                        $max_value = max(array_column($report_data, 'total_cost'));
                        foreach ($report_data as $row): 
                            $height = $max_value > 0 ? ($row['total_cost'] / $max_value) * 240 : 20;
                        ?>
                            <div class="chart-bar" style="height: <?php echo $height; ?>px;">
                                <div class="value"><?php echo formatCurrency($row['total_cost']); ?></div>
                                <div class="label">
                                    <?php 
                                    if ($report_type === 'monthly') {
                                        echo date('M Y', strtotime($row['month'] . '-01')); 
                                    } else {
                                        echo $row['year'];
                                    }
                                    ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                
                <div class="table-responsive">
                    <table>
                        <?php if ($report_type === 'monthly'): ?>
                            <thead>
                                <tr>
                                    <th>Month</th>
                                    <th>Records</th>
                                    <th>Total Cost</th>
                                    <th>Liters</th>
                                    <th>Avg Price/L</th>
                                    <th>Distance</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($report_data as $row): ?>
                                    <tr>
                                        <td><?php echo date('F Y', strtotime($row['month'] . '-01')); ?></td>
                                        <td><?php echo $row['record_count']; ?></td>
                                        <td><strong><?php echo formatCurrency($row['total_cost']); ?></strong></td>
                                        <td><?php echo number_format($row['total_liters'], 2); ?> L</td>
                                        <td><?php echo formatCurrency($row['avg_price']); ?></td>
                                        <td><?php echo number_format($row['distance_traveled'], 0); ?> km</td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                            
                        <?php elseif ($report_type === 'yearly'): ?>
                            <thead>
                                <tr>
                                    <th>Year</th>
                                    <th>Records</th>
                                    <th>Total Cost</th>
                                    <th>Liters</th>
                                    <th>Avg Price/L</th>
                                    <th>Distance</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($report_data as $row): ?>
                                    <tr>
                                        <td><?php echo $row['year']; ?></td>
                                        <td><?php echo $row['record_count']; ?></td>
                                        <td><strong><?php echo formatCurrency($row['total_cost']); ?></strong></td>
                                        <td><?php echo number_format($row['total_liters'], 2); ?> L</td>
                                        <td><?php echo formatCurrency($row['avg_price']); ?></td>
                                        <td><?php echo number_format($row['distance_traveled'], 0); ?> km</td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                            
                        <?php elseif ($report_type === 'efficiency'): ?>
                            <thead>
                                <tr>
                                    <th>Month</th>
                                    <th>Distance (km)</th>
                                    <th>Cost/km</th>
                                    <th>km/L</th>
                                    <th>Efficiency</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($report_data as $row): ?>
                                    <tr>
                                        <td><?php echo date('F Y', strtotime($row['month'] . '-01')); ?></td>
                                        <td><?php echo number_format($row['distance_traveled'], 0); ?></td>
                                        <td><?php echo formatCurrency($row['cost_per_km']); ?></td>
                                        <td><?php echo number_format($row['km_per_liter'], 2); ?></td>
                                        <td>
                                            <?php 
                                            if ($row['km_per_liter'] >= 10) {
                                                echo '<span class="efficiency-indicator efficiency-excellent">Excellent</span>';
                                            } elseif ($row['km_per_liter'] >= 8) {
                                                echo '<span class="efficiency-indicator efficiency-good">Good</span>';
                                            } else {
                                                echo '<span class="efficiency-indicator efficiency-poor">Poor</span>';
                                            }
                                            ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                            
                        <?php else: ?>
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
                                <?php foreach ($report_data as $row): ?>
                                    <tr>
                                        <td><?php echo formatDate($row['date'], 'd M Y'); ?></td>
                                        <td><?php echo number_format($row['mileage'], 0); ?> km</td>
                                        <td><?php echo number_format($row['liters'], 2); ?> L</td>
                                        <td><?php echo formatCurrency($row['price_per_liter']); ?></td>
                                        <td><strong><?php echo formatCurrency($row['total_cost']); ?></strong></td>
                                        <td><?php echo Security::sanitizeOutput($row['location']); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                            
                            <?php if (isset($summary)): ?>
                                <tfoot style="background: #f9f9f9; border-top: 2px solid #e0e0e0;">
                                    <tr>
                                        <th>Summary</th>
                                        <th><?php echo number_format($summary['distance_traveled'], 0); ?> km</th>
                                        <th><?php echo number_format($summary['total_liters'], 2); ?> L</th>
                                        <th><?php echo formatCurrency($summary['avg_price']); ?></th>
                                        <th><strong><?php echo formatCurrency($summary['total_cost']); ?></strong></th>
                                        <th><?php echo $summary['record_count']; ?> records</th>
                                    </tr>
                                </tfoot>
                            <?php endif; ?>
                        <?php endif; ?>
                    </table>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <div class="icon">📊</div>
                    <h3>No Data Available</h3>
                    <p>No fuel records found for the selected period.</p>
                    <p><a href="add-fuel-record.php" style="color: #667eea;">Add your first record</a> to start seeing reports.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <script>
        function toggleFilters(type) {
            const startDateFilter = document.getElementById('start-date-filter');
            const endDateFilter = document.getElementById('end-date-filter');
            
            if (type === 'custom') {
                startDateFilter.style.display = 'block';
                endDateFilter.style.display = 'block';
            } else {
                startDateFilter.style.display = 'none';
                endDateFilter.style.display = 'none';
            }
        }
        
        // Initialize filters on page load
        document.addEventListener('DOMContentLoaded', function() {
            const reportType = document.getElementById('type').value;
            toggleFilters(reportType);
        });
    </script>
</body>
</html>