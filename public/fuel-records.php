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

// Handle delete action
if (isset($_GET['delete']) && isset($_GET['token'])) {
    if (Security::verifyCSRFToken($_GET['token'])) {
        $record_id = (int)$_GET['delete'];
        
        // Verify the record belongs to the user
        $sql = "SELECT id FROM fuel_records WHERE id = :id AND user_id = :user_id";
        $record = $db->fetchOne($sql, [':id' => $record_id, ':user_id' => $user['id']]);
        
        if ($record) {
            $sql = "DELETE FROM fuel_records WHERE id = :id AND user_id = :user_id";
            $db->execute($sql, [':id' => $record_id, ':user_id' => $user['id']]);
            
            Security::logSecurityEvent('FUEL_RECORD_DELETED', "User deleted fuel record ID: $record_id", $user['id']);
            setAlert('Record deleted successfully.', 'success');
        } else {
            setAlert('Record not found or access denied.', 'error');
        }
    } else {
        setAlert('Invalid security token.', 'error');
    }
    redirect('/fuel-records.php');
}

// Pagination
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$records_per_page = 10;
$offset = ($page - 1) * $records_per_page;

// Get filter parameters
$month_filter = isset($_GET['month']) ? $_GET['month'] : '';
$year_filter = isset($_GET['year']) ? $_GET['year'] : date('Y');

// Build query
$where_conditions = ['user_id = :user_id'];
$params = [':user_id' => $user['id']];

if ($month_filter && $year_filter) {
    $where_conditions[] = "MONTH(date) = :month AND YEAR(date) = :year";
    $params[':month'] = $month_filter;
    $params[':year'] = $year_filter;
} elseif ($year_filter) {
    $where_conditions[] = "YEAR(date) = :year";
    $params[':year'] = $year_filter;
}

$where_clause = implode(' AND ', $where_conditions);

// Get total records count
$sql = "SELECT COUNT(*) as total FROM fuel_records WHERE $where_clause";
$count_result = $db->fetchOne($sql, $params);
$total_records = $count_result['total'];
$total_pages = ceil($total_records / $records_per_page);

// Get records
$sql = "SELECT * FROM fuel_records 
        WHERE $where_clause 
        ORDER BY date DESC, created_at DESC 
        LIMIT :limit OFFSET :offset";

$stmt = $db->getConnection()->prepare($sql);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->bindValue(':limit', $records_per_page, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$records = $stmt->fetchAll();

// Get statistics
$sql = "SELECT 
        COUNT(*) as total_records,
        SUM(total_cost) as total_spent,
        SUM(liters) as total_liters,
        AVG(price_per_liter) as avg_price,
        MIN(date) as first_record,
        MAX(date) as last_record
        FROM fuel_records WHERE $where_clause";
$stats = $db->fetchOne($sql, $params);

// Generate CSRF token
$csrf_token = Security::generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Fuel Records - <?php echo APP_NAME; ?></title>
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
        
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            font-size: 14px;
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
        
        .stats-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
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
        }
        
        .filters {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 30px;
        }
        
        .filters form {
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
        
        .filter-group select {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
        }
        
        .records-table {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            overflow: hidden;
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
            padding: 15px;
            color: #666;
            font-size: 12px;
            text-transform: uppercase;
            font-weight: 600;
            border-bottom: 2px solid #e0e0e0;
        }
        
        td {
            padding: 15px;
            border-bottom: 1px solid #f0f0f0;
        }
        
        tr:last-child td {
            border-bottom: none;
        }
        
        tr:hover {
            background: #f9f9f9;
        }
        
        .actions {
            display: flex;
            gap: 10px;
        }
        
        .btn-sm {
            padding: 5px 10px;
            font-size: 12px;
            border-radius: 3px;
            text-decoration: none;
            transition: opacity 0.2s;
        }
        
        .btn-edit {
            background: #4CAF50;
            color: white;
        }
        
        .btn-delete {
            background: #f44336;
            color: white;
        }
        
        .btn-sm:hover {
            opacity: 0.8;
        }
        
        .pagination {
            display: flex;
            justify-content: center;
            gap: 5px;
            margin-top: 30px;
        }
        
        .pagination a,
        .pagination span {
            padding: 8px 12px;
            background: white;
            border: 1px solid #ddd;
            color: #333;
            text-decoration: none;
            border-radius: 3px;
            transition: background 0.2s;
        }
        
        .pagination a:hover {
            background: #f0f0f0;
        }
        
        .pagination .active {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-color: transparent;
        }
        
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .empty-state .icon {
            font-size: 48px;
            margin-bottom: 10px;
        }
        
        .empty-state p {
            color: #999;
            margin-bottom: 20px;
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
    </style>
</head>
<body>
    <header class="header">
        <div class="header-content">
            <div class="logo"><?php echo APP_NAME; ?></div>
            <nav class="nav-menu">
                <a href="dashboard.php">Dashboard</a>
                <a href="fuel-records.php" style="background: rgba(255,255,255,0.1);">My Records</a>
                <a href="profile.php">Profile</a>
                <a href="logout.php">Logout</a>
            </nav>
        </div>
    </header>
    
    <div class="container">
        <?php displayAlert(); ?>
        
        <div class="page-header">
            <div class="page-title">
                <h1>My Fuel Records</h1>
                <p>View and manage your fuel consumption history</p>
            </div>
            <a href="add-fuel-record.php" class="btn btn-primary">+ Add New Record</a>
        </div>
        
        <?php if ($stats['total_records'] > 0): ?>
            <div class="stats-cards">
                <div class="stat-card">
                    <h3>Total Records</h3>
                    <div class="value"><?php echo number_format($stats['total_records']); ?></div>
                </div>
                <div class="stat-card">
                    <h3>Total Spent</h3>
                    <div class="value"><?php echo formatCurrency($stats['total_spent']); ?></div>
                </div>
                <div class="stat-card">
                    <h3>Total Fuel</h3>
                    <div class="value"><?php echo number_format($stats['total_liters'], 2); ?> L</div>
                </div>
                <div class="stat-card">
                    <h3>Avg Price/L</h3>
                    <div class="value"><?php echo formatCurrency($stats['avg_price']); ?></div>
                </div>
            </div>
        <?php endif; ?>
        
        <div class="filters">
            <form method="GET" action="">
                <div class="filter-group">
                    <label for="month">Month</label>
                    <select name="month" id="month">
                        <option value="">All Months</option>
                        <?php for ($m = 1; $m <= 12; $m++): ?>
                            <option value="<?php echo $m; ?>" <?php echo $month_filter == $m ? 'selected' : ''; ?>>
                                <?php echo getMonthName($m); ?>
                            </option>
                        <?php endfor; ?>
                    </select>
                </div>
                
                <div class="filter-group">
                    <label for="year">Year</label>
                    <select name="year" id="year">
                        <?php 
                        $current_year = date('Y');
                        for ($y = $current_year; $y >= $current_year - 5; $y--): 
                        ?>
                            <option value="<?php echo $y; ?>" <?php echo $year_filter == $y ? 'selected' : ''; ?>>
                                <?php echo $y; ?>
                            </option>
                        <?php endfor; ?>
                    </select>
                </div>
                
                <button type="submit" class="btn btn-primary">Filter</button>
                <a href="fuel-records.php" class="btn" style="background: #e0e0e0; color: #333;">Clear</a>
            </form>
        </div>
        
        <?php if (!empty($records)): ?>
            <div class="records-table">
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Mileage</th>
                                <th>Liters</th>
                                <th>Price/L</th>
                                <th>Total Cost</th>
                                <th>Location</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($records as $record): ?>
                                <tr>
                                    <td><?php echo formatDate($record['date'], 'd M Y'); ?></td>
                                    <td><?php echo number_format($record['mileage'], 0); ?> km</td>
                                    <td><?php echo number_format($record['liters'], 2); ?> L</td>
                                    <td><?php echo formatCurrency($record['price_per_liter']); ?></td>
                                    <td><strong><?php echo formatCurrency($record['total_cost']); ?></strong></td>
                                    <td><?php echo Security::sanitizeOutput($record['location'] ?? '-'); ?></td>
                                    <td>
                                        <div class="actions">
                                            <a href="edit-fuel-record.php?id=<?php echo $record['id']; ?>" class="btn-sm btn-edit">Edit</a>
                                            <a href="?delete=<?php echo $record['id']; ?>&token=<?php echo $csrf_token; ?>" 
                                               class="btn-sm btn-delete" 
                                               onclick="return confirm('Are you sure you want to delete this record?');">Delete</a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <?php if ($total_pages > 1): ?>
                <div class="pagination">
                    <?php if ($page > 1): ?>
                        <a href="?page=<?php echo $page - 1; ?>&month=<?php echo $month_filter; ?>&year=<?php echo $year_filter; ?>">← Previous</a>
                    <?php endif; ?>
                    
                    <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                        <?php if ($i == $page): ?>
                            <span class="active"><?php echo $i; ?></span>
                        <?php else: ?>
                            <a href="?page=<?php echo $i; ?>&month=<?php echo $month_filter; ?>&year=<?php echo $year_filter; ?>"><?php echo $i; ?></a>
                        <?php endif; ?>
                    <?php endfor; ?>
                    
                    <?php if ($page < $total_pages): ?>
                        <a href="?page=<?php echo $page + 1; ?>&month=<?php echo $month_filter; ?>&year=<?php echo $year_filter; ?>">Next →</a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        <?php else: ?>
            <div class="empty-state">
                <div class="icon">📝</div>
                <p>No fuel records found for the selected period</p>
                <a href="add-fuel-record.php" class="btn btn-primary">Add Your First Record</a>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>