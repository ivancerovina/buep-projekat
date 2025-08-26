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

// Get report parameters
$report_type = isset($_GET['type']) ? $_GET['type'] : 'monthly';
$month = isset($_GET['month']) ? $_GET['month'] : date('Y-m');
$year = isset($_GET['year']) ? $_GET['year'] : date('Y');

// Generate reports based on type
$report_data = [];

if ($report_type === 'monthly') {
    // Monthly consumption report
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
            WHERE u.role != 'admin'
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
    
} elseif ($report_type === 'yearly') {
    // Yearly consumption report
    $sql = "SELECT 
            MONTH(fr.date) as month_num,
            MONTHNAME(fr.date) as month_name,
            COUNT(DISTINCT fr.user_id) as active_users,
            COUNT(fr.id) as total_records,
            SUM(fr.liters) as total_liters,
            SUM(fr.total_cost) as total_cost,
            AVG(fr.price_per_liter) as avg_price
            FROM fuel_records fr
            WHERE YEAR(fr.date) = :year
            GROUP BY MONTH(fr.date)
            ORDER BY month_num";
    
    $report_data = $db->fetchAll($sql, [':year' => $year]);
    
    // Calculate yearly totals
    $totals = [
        'total_cost' => array_sum(array_column($report_data, 'total_cost')),
        'total_liters' => array_sum(array_column($report_data, 'total_liters')),
        'total_records' => array_sum(array_column($report_data, 'total_records'))
    ];
    
} elseif ($report_type === 'department') {
    // Department/Role based report
    $sql = "SELECT 
            u.role,
            COUNT(DISTINCT u.id) as user_count,
            COUNT(fr.id) as total_records,
            COALESCE(SUM(fr.liters), 0) as total_liters,
            COALESCE(SUM(fr.total_cost), 0) as total_cost,
            COALESCE(AVG(fr.price_per_liter), 0) as avg_price
            FROM users u
            LEFT JOIN fuel_records fr ON u.id = fr.user_id AND DATE_FORMAT(fr.date, '%Y-%m') = :month
            WHERE u.role != 'admin'
            GROUP BY u.role
            ORDER BY total_cost DESC";
    
    $report_data = $db->fetchAll($sql, [':month' => $month]);
}

// Get top consumers for the month
$sql = "SELECT 
        u.username, u.first_name, u.last_name,
        SUM(fr.total_cost) as total_cost
        FROM users u
        INNER JOIN fuel_records fr ON u.id = fr.user_id
        WHERE DATE_FORMAT(fr.date, '%Y-%m') = :month
        GROUP BY u.id
        ORDER BY total_cost DESC
        LIMIT 5";

$top_consumers = $db->fetchAll($sql, [':month' => $month]);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports - <?php echo APP_NAME; ?></title>
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
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
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
            background: linear-gradient(90deg, #4CAF50, #43A047);
            transition: width 0.3s;
        }
        
        .progress-fill.warning {
            background: linear-gradient(90deg, #FFA726, #FF7043);
        }
        
        .progress-fill.danger {
            background: linear-gradient(90deg, #EF5350, #E53935);
        }
        
        .top-consumers {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            padding: 20px;
        }
        
        .top-consumers h2 {
            color: #333;
            font-size: 18px;
            margin-bottom: 20px;
        }
        
        .consumer-list {
            list-style: none;
        }
        
        .consumer-list li {
            padding: 12px 0;
            border-bottom: 1px solid #f0f0f0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .consumer-list li:last-child {
            border-bottom: none;
        }
        
        .consumer-rank {
            width: 30px;
            height: 30px;
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 14px;
        }
        
        .consumer-info {
            flex: 1;
            margin-left: 15px;
        }
        
        .consumer-name {
            font-weight: 500;
            color: #333;
        }
        
        .consumer-amount {
            font-weight: bold;
            color: #667eea;
        }
        
        @media print {
            .header, .report-filters, .btn-export {
                display: none;
            }
            
            .report-content {
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
                <a href="dashboard.php">Dashboard</a>
                <a href="users.php">Users</a>
                <a href="limits.php">Limits</a>
                <a href="reports.php" class="active">Reports</a>
                <a href="logs.php">Security Logs</a>
                <a href="../logout.php">Logout</a>
            </nav>
        </div>
    </header>
    
    <div class="container">
        <div class="page-header">
            <div class="page-title">
                <h1>Reports & Analytics</h1>
                <p>Comprehensive fuel consumption analysis</p>
            </div>
            <a href="export.php?type=<?php echo $report_type; ?>&month=<?php echo $month; ?>&year=<?php echo $year; ?>" 
               class="btn btn-export">Export to CSV</a>
        </div>
        
        <div class="report-filters">
            <form method="GET" action="">
                <div class="filter-group">
                    <label for="type">Report Type</label>
                    <select name="type" id="type" onchange="toggleFilters(this.value)">
                        <option value="monthly" <?php echo $report_type === 'monthly' ? 'selected' : ''; ?>>Monthly Report</option>
                        <option value="yearly" <?php echo $report_type === 'yearly' ? 'selected' : ''; ?>>Yearly Report</option>
                        <option value="department" <?php echo $report_type === 'department' ? 'selected' : ''; ?>>Department Report</option>
                    </select>
                </div>
                
                <div class="filter-group" id="month-filter" style="<?php echo $report_type === 'yearly' ? 'display:none;' : ''; ?>">
                    <label for="month">Month</label>
                    <input type="month" 
                           name="month" 
                           id="month" 
                           value="<?php echo $month; ?>"
                           max="<?php echo date('Y-m'); ?>">
                </div>
                
                <div class="filter-group" id="year-filter" style="<?php echo $report_type === 'monthly' || $report_type === 'department' ? 'display:none;' : ''; ?>">
                    <label for="year">Year</label>
                    <select name="year" id="year">
                        <?php for ($y = date('Y'); $y >= date('Y') - 5; $y--): ?>
                            <option value="<?php echo $y; ?>" <?php echo $year == $y ? 'selected' : ''; ?>><?php echo $y; ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
                
                <button type="submit" class="btn btn-primary">Generate Report</button>
            </form>
        </div>
        
        <?php if (isset($totals)): ?>
            <div class="stats-summary">
                <div class="stat-card">
                    <h3>Total Spending</h3>
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
                    if ($report_type === 'monthly') {
                        echo 'Monthly Consumption Report - ' . date('F Y', strtotime($month . '-01'));
                    } elseif ($report_type === 'yearly') {
                        echo 'Yearly Consumption Report - ' . $year;
                    } else {
                        echo 'Department Report - ' . date('F Y', strtotime($month . '-01'));
                    }
                    ?>
                </h2>
                
                <div class="table-responsive">
                    <table>
                        <?php if ($report_type === 'monthly'): ?>
                            <thead>
                                <tr>
                                    <th>Employee</th>
                                    <th>Role</th>
                                    <th>Records</th>
                                    <th>Liters</th>
                                    <th>Total Cost</th>
                                    <th>Limit</th>
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
                                        <td><?php echo $row['monthly_limit'] ? formatCurrency($row['monthly_limit']) : '-'; ?></td>
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
                                                -
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                            
                        <?php elseif ($report_type === 'yearly'): ?>
                            <thead>
                                <tr>
                                    <th>Month</th>
                                    <th>Active Users</th>
                                    <th>Records</th>
                                    <th>Liters</th>
                                    <th>Total Cost</th>
                                    <th>Avg Price/L</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($report_data as $row): ?>
                                    <tr>
                                        <td><?php echo $row['month_name']; ?></td>
                                        <td><?php echo $row['active_users']; ?></td>
                                        <td><?php echo $row['total_records']; ?></td>
                                        <td><?php echo number_format($row['total_liters'], 2); ?> L</td>
                                        <td><strong><?php echo formatCurrency($row['total_cost']); ?></strong></td>
                                        <td><?php echo formatCurrency($row['avg_price']); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                            
                        <?php else: ?>
                            <thead>
                                <tr>
                                    <th>Department</th>
                                    <th>Users</th>
                                    <th>Records</th>
                                    <th>Liters</th>
                                    <th>Total Cost</th>
                                    <th>Avg Price/L</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($report_data as $row): ?>
                                    <tr>
                                        <td><?php echo ucfirst($row['role']); ?></td>
                                        <td><?php echo $row['user_count']; ?></td>
                                        <td><?php echo $row['total_records']; ?></td>
                                        <td><?php echo number_format($row['total_liters'], 2); ?> L</td>
                                        <td><strong><?php echo formatCurrency($row['total_cost']); ?></strong></td>
                                        <td><?php echo formatCurrency($row['avg_price']); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        <?php endif; ?>
                    </table>
                </div>
            </div>
            
            <div class="top-consumers">
                <h2>Top 5 Consumers This Month</h2>
                <ul class="consumer-list">
                    <?php foreach ($top_consumers as $index => $consumer): ?>
                        <li>
                            <div style="display: flex; align-items: center;">
                                <span class="consumer-rank"><?php echo $index + 1; ?></span>
                                <div class="consumer-info">
                                    <div class="consumer-name">
                                        <?php echo Security::sanitizeOutput($consumer['first_name'] . ' ' . $consumer['last_name']); ?>
                                    </div>
                                </div>
                            </div>
                            <span class="consumer-amount"><?php echo formatCurrency($consumer['total_cost']); ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>
    </div>
    
    <script>
        function toggleFilters(type) {
            const monthFilter = document.getElementById('month-filter');
            const yearFilter = document.getElementById('year-filter');
            
            if (type === 'yearly') {
                monthFilter.style.display = 'none';
                yearFilter.style.display = 'block';
            } else {
                monthFilter.style.display = 'block';
                yearFilter.style.display = 'none';
            }
        }
    </script>
</body>
</html>