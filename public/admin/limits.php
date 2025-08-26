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

// Handle limit update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    // Verify CSRF token
    if (!isset($_POST[CSRF_TOKEN_NAME]) || !Security::verifyCSRFToken($_POST[CSRF_TOKEN_NAME])) {
        $error_message = "Invalid security token. Please refresh and try again.";
    } else {
        if ($_POST['action'] === 'set_limit') {
            $user_id = (int)$_POST['user_id'];
            $limit = (float)$_POST['limit'];
            $month_year = $_POST['month_year'] . '-01'; // Format: YYYY-MM-01
            
            if ($limit <= 0) {
                $error_message = "Limit must be a positive number.";
            } else {
                try {
                    // Check if limit already exists
                    $sql = "SELECT id FROM fuel_limits WHERE user_id = :user_id AND month_year = :month_year";
                    $existing = $db->fetchOne($sql, [':user_id' => $user_id, ':month_year' => $month_year]);
                    
                    if ($existing) {
                        // Update existing limit
                        $sql = "UPDATE fuel_limits SET monthly_limit = :limit WHERE user_id = :user_id AND month_year = :month_year";
                        $db->execute($sql, [
                            ':limit' => $limit,
                            ':user_id' => $user_id,
                            ':month_year' => $month_year
                        ]);
                    } else {
                        // Insert new limit
                        $sql = "INSERT INTO fuel_limits (user_id, month_year, monthly_limit, created_by) 
                                VALUES (:user_id, :month_year, :limit, :created_by)";
                        $db->execute($sql, [
                            ':user_id' => $user_id,
                            ':month_year' => $month_year,
                            ':limit' => $limit,
                            ':created_by' => $user['id']
                        ]);
                    }
                    
                    Security::logSecurityEvent('LIMIT_SET', "Admin set limit for user ID: $user_id", $user['id']);
                    $success_message = "Limit set successfully.";
                    
                } catch (Exception $e) {
                    error_log("Error setting limit: " . $e->getMessage());
                    $error_message = "An error occurred while setting the limit.";
                }
            }
        } elseif ($_POST['action'] === 'delete_limit') {
            $limit_id = (int)$_POST['limit_id'];
            
            try {
                $sql = "DELETE FROM fuel_limits WHERE id = :id";
                $db->execute($sql, [':id' => $limit_id]);
                
                Security::logSecurityEvent('LIMIT_DELETED', "Admin deleted limit ID: $limit_id", $user['id']);
                $success_message = "Limit deleted successfully.";
                
            } catch (Exception $e) {
                error_log("Error deleting limit: " . $e->getMessage());
                $error_message = "An error occurred while deleting the limit.";
            }
        }
    }
}

// Get month filter
$selected_month = isset($_GET['month']) ? $_GET['month'] : date('Y-m');

// Get all users with their limits for the selected month
$sql = "SELECT u.id, u.username, u.first_name, u.last_name, u.email, u.role,
        fl.id as limit_id, fl.monthly_limit,
        COALESCE(SUM(fr.total_cost), 0) as current_spending
        FROM users u
        LEFT JOIN fuel_limits fl ON u.id = fl.user_id AND fl.month_year = :month_year
        LEFT JOIN fuel_records fr ON u.id = fr.user_id AND DATE_FORMAT(fr.date, '%Y-%m') = :month
        WHERE u.role != 'admin'
        GROUP BY u.id, fl.id
        ORDER BY u.last_name, u.first_name";

$users = $db->fetchAll($sql, [
    ':month_year' => $selected_month . '-01',
    ':month' => $selected_month
]);

// Generate CSRF token
$csrf_token = Security::generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fuel Limits Management - <?php echo APP_NAME; ?></title>
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
            margin-bottom: 30px;
        }
        
        .page-title h1 {
            color: #333;
            margin-bottom: 5px;
        }
        
        .page-title p {
            color: #666;
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
        }
        
        .filter-group {
            flex: 1;
            max-width: 200px;
        }
        
        .filter-group label {
            display: block;
            margin-bottom: 5px;
            color: #666;
            font-size: 14px;
        }
        
        .filter-group input {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 5px;
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
        
        .btn-primary:hover {
            transform: translateY(-1px);
            box-shadow: 0 3px 10px rgba(102, 126, 234, 0.3);
        }
        
        .limits-table {
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
        
        tr:hover {
            background: #f9f9f9;
        }
        
        .user-info .name {
            font-weight: 500;
            color: #333;
        }
        
        .user-info .email {
            font-size: 12px;
            color: #666;
        }
        
        .role-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 500;
        }
        
        .role-badge.employee {
            background: #E3F2FD;
            color: #1976D2;
        }
        
        .role-badge.manager {
            background: #F3E5F5;
            color: #7B1FA2;
        }
        
        .limit-form {
            display: flex;
            gap: 10px;
            align-items: center;
        }
        
        .limit-form input {
            width: 120px;
            padding: 6px 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
        }
        
        .btn-sm {
            padding: 6px 12px;
            font-size: 12px;
        }
        
        .btn-success {
            background: #4CAF50;
            color: white;
        }
        
        .btn-danger {
            background: #f44336;
            color: white;
        }
        
        .progress-info {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }
        
        .progress-bar {
            width: 150px;
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
        
        .progress-text {
            font-size: 12px;
            color: #666;
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
        
        .no-limit {
            color: #999;
            font-style: italic;
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
                <a href="limits.php" class="active">Limits</a>
                <a href="reports.php">Reports</a>
                <a href="logs.php">Security Logs</a>
                <a href="../logout.php">Logout</a>
            </nav>
        </div>
    </header>
    
    <div class="container">
        <div class="page-header">
            <div class="page-title">
                <h1>Fuel Limits Management</h1>
                <p>Set and manage monthly fuel consumption limits for users</p>
            </div>
        </div>
        
        <?php if ($success_message): ?>
            <div class="alert alert-success"><?php echo Security::sanitizeOutput($success_message); ?></div>
        <?php endif; ?>
        
        <?php if ($error_message): ?>
            <div class="alert alert-error"><?php echo Security::sanitizeOutput($error_message); ?></div>
        <?php endif; ?>
        
        <div class="filters">
            <form method="GET" action="">
                <div class="filter-group">
                    <label for="month">Month</label>
                    <input type="month" 
                           id="month" 
                           name="month" 
                           value="<?php echo $selected_month; ?>"
                           max="<?php echo date('Y-m', strtotime('+1 year')); ?>">
                </div>
                <button type="submit" class="btn btn-primary">Apply Filter</button>
            </form>
        </div>
        
        <div class="limits-table">
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>User</th>
                            <th>Role</th>
                            <th>Monthly Limit (RSD)</th>
                            <th>Current Spending</th>
                            <th>Usage</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $u): ?>
                            <tr>
                                <td>
                                    <div class="user-info">
                                        <div class="name"><?php echo Security::sanitizeOutput($u['first_name'] . ' ' . $u['last_name']); ?></div>
                                        <div class="email"><?php echo Security::sanitizeOutput($u['email']); ?></div>
                                    </div>
                                </td>
                                <td>
                                    <span class="role-badge <?php echo $u['role']; ?>">
                                        <?php echo ucfirst($u['role']); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($u['monthly_limit']): ?>
                                        <?php echo formatCurrency($u['monthly_limit']); ?>
                                    <?php else: ?>
                                        <span class="no-limit">No limit set</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo formatCurrency($u['current_spending']); ?></td>
                                <td>
                                    <?php if ($u['monthly_limit'] && $u['monthly_limit'] > 0): ?>
                                        <?php 
                                        $usage_percentage = ($u['current_spending'] / $u['monthly_limit']) * 100;
                                        $progress_class = '';
                                        if ($usage_percentage >= 100) {
                                            $progress_class = 'danger';
                                        } elseif ($usage_percentage >= 80) {
                                            $progress_class = 'warning';
                                        }
                                        ?>
                                        <div class="progress-info">
                                            <div class="progress-bar">
                                                <div class="progress-fill <?php echo $progress_class; ?>" 
                                                     style="width: <?php echo min($usage_percentage, 100); ?>%"></div>
                                            </div>
                                            <span class="progress-text"><?php echo number_format($usage_percentage, 1); ?>%</span>
                                        </div>
                                    <?php else: ?>
                                        -
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <form method="POST" action="" class="limit-form">
                                        <input type="number" 
                                               name="limit" 
                                               value="<?php echo $u['monthly_limit'] ?? ''; ?>"
                                               placeholder="Enter limit"
                                               step="1000"
                                               min="0"
                                               required>
                                        <input type="hidden" name="user_id" value="<?php echo $u['id']; ?>">
                                        <input type="hidden" name="month_year" value="<?php echo $selected_month; ?>">
                                        <input type="hidden" name="action" value="set_limit">
                                        <?php echo Security::getCSRFTokenField(); ?>
                                        <button type="submit" class="btn btn-sm btn-success">Set</button>
                                        <?php if ($u['limit_id']): ?>
                                            <button type="submit" 
                                                    name="action" 
                                                    value="delete_limit"
                                                    formnovalidate
                                                    onclick="return confirm('Remove limit for this user?');"
                                                    class="btn btn-sm btn-danger">Remove</button>
                                            <input type="hidden" name="limit_id" value="<?php echo $u['limit_id']; ?>">
                                        <?php endif; ?>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</body>
</html>