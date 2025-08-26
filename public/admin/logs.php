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

// Pagination
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$logs_per_page = 50;
$offset = ($page - 1) * $logs_per_page;

// Filters
$event_type_filter = isset($_GET['event_type']) ? Security::sanitizeInput($_GET['event_type']) : '';
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : date('Y-m-d', strtotime('-7 days'));
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : date('Y-m-d');
$user_filter = isset($_GET['user']) ? Security::sanitizeInput($_GET['user']) : '';

// Build query
$where_conditions = ["sl.created_at >= :date_from", "sl.created_at <= :date_to"];
$params = [
    ':date_from' => $date_from . ' 00:00:00',
    ':date_to' => $date_to . ' 23:59:59'
];

if ($event_type_filter) {
    $where_conditions[] = "sl.event_type = :event_type";
    $params[':event_type'] = $event_type_filter;
}

if ($user_filter) {
    $where_conditions[] = "(u.username LIKE :user OR u.email LIKE :user)";
    $params[':user'] = "%$user_filter%";
}

$where_clause = implode(' AND ', $where_conditions);

// Get total logs count
$sql = "SELECT COUNT(*) as total 
        FROM security_logs sl 
        LEFT JOIN users u ON sl.user_id = u.id 
        WHERE $where_clause";

$stmt = $db->getConnection()->prepare($sql);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->execute();
$count_result = $stmt->fetch();
$total_logs = $count_result['total'];
$total_pages = ceil($total_logs / $logs_per_page);

// Get logs
$sql = "SELECT sl.*, u.username, u.first_name, u.last_name 
        FROM security_logs sl 
        LEFT JOIN users u ON sl.user_id = u.id 
        WHERE $where_clause 
        ORDER BY sl.created_at DESC 
        LIMIT :limit OFFSET :offset";

$stmt = $db->getConnection()->prepare($sql);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->bindValue(':limit', $logs_per_page, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$logs = $stmt->fetchAll();

// Get distinct event types for filter
$sql = "SELECT DISTINCT event_type FROM security_logs ORDER BY event_type";
$event_types = $db->fetchAll($sql);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Security Logs - <?php echo APP_NAME; ?></title>
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
        
        .stats-row {
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
        
        .filter-group input,
        .filter-group select {
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
        
        .logs-table {
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
            padding: 12px 15px;
            border-bottom: 1px solid #f0f0f0;
            font-size: 14px;
        }
        
        tr:hover {
            background: #f9f9f9;
        }
        
        .event-type {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 11px;
            font-weight: bold;
            text-transform: uppercase;
        }
        
        .event-type.success { background: #C8E6C9; color: #2E7D32; }
        .event-type.failed { background: #FFCDD2; color: #C62828; }
        .event-type.warning { background: #FFF9C4; color: #F57C00; }
        .event-type.info { background: #E1F5FE; color: #0277BD; }
        .event-type.danger { background: #FFCDD2; color: #C62828; }
        
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
        
        .ip-address {
            font-family: monospace;
            font-size: 12px;
            color: #666;
        }
        
        .user-info {
            color: #333;
            font-weight: 500;
        }
        
        .no-user {
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
                <a href="limits.php">Limits</a>
                <a href="reports.php">Reports</a>
                <a href="logs.php" class="active">Security Logs</a>
                <a href="../logout.php">Logout</a>
            </nav>
        </div>
    </header>
    
    <div class="container">
        <div class="page-header">
            <div class="page-title">
                <h1>Security Logs</h1>
                <p>Monitor system security events and user activities</p>
            </div>
        </div>
        
        <div class="stats-row">
            <?php
            // Get stats for today
            $today_sql = "SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN event_type LIKE '%SUCCESS%' THEN 1 ELSE 0 END) as success,
                SUM(CASE WHEN event_type LIKE '%FAILED%' THEN 1 ELSE 0 END) as failed,
                SUM(CASE WHEN event_type LIKE '%LOCKED%' OR event_type LIKE '%INJECTION%' THEN 1 ELSE 0 END) as security
                FROM security_logs 
                WHERE DATE(created_at) = CURDATE()";
            $today_stats = $db->fetchOne($today_sql);
            ?>
            
            <div class="stat-card">
                <h3>Today's Events</h3>
                <div class="value"><?php echo number_format($today_stats['total']); ?></div>
            </div>
            
            <div class="stat-card">
                <h3>Successful Logins</h3>
                <div class="value"><?php echo number_format($today_stats['success']); ?></div>
            </div>
            
            <div class="stat-card">
                <h3>Failed Attempts</h3>
                <div class="value"><?php echo number_format($today_stats['failed']); ?></div>
            </div>
            
            <div class="stat-card">
                <h3>Security Alerts</h3>
                <div class="value"><?php echo number_format($today_stats['security']); ?></div>
            </div>
        </div>
        
        <div class="filters">
            <form method="GET" action="">
                <div class="filter-group">
                    <label for="date_from">From Date</label>
                    <input type="date" 
                           id="date_from" 
                           name="date_from" 
                           value="<?php echo $date_from; ?>">
                </div>
                
                <div class="filter-group">
                    <label for="date_to">To Date</label>
                    <input type="date" 
                           id="date_to" 
                           name="date_to" 
                           value="<?php echo $date_to; ?>">
                </div>
                
                <div class="filter-group">
                    <label for="event_type">Event Type</label>
                    <select name="event_type" id="event_type">
                        <option value="">All Events</option>
                        <?php foreach ($event_types as $type): ?>
                            <option value="<?php echo $type['event_type']; ?>" 
                                    <?php echo $event_type_filter === $type['event_type'] ? 'selected' : ''; ?>>
                                <?php echo $type['event_type']; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="filter-group">
                    <label for="user">User</label>
                    <input type="text" 
                           id="user" 
                           name="user" 
                           value="<?php echo Security::sanitizeOutput($user_filter); ?>"
                           placeholder="Username or email">
                </div>
                
                <button type="submit" class="btn btn-primary">Filter</button>
                <a href="logs.php" class="btn" style="background: #e0e0e0; color: #333;">Clear</a>
            </form>
        </div>
        
        <div class="logs-table">
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>Date & Time</th>
                            <th>Event Type</th>
                            <th>User</th>
                            <th>Description</th>
                            <th>IP Address</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($logs as $log): ?>
                            <tr>
                                <td><?php echo formatDate($log['created_at'], 'd M Y H:i:s'); ?></td>
                                <td>
                                    <?php
                                    $event_class = 'info';
                                    if (strpos($log['event_type'], 'SUCCESS') !== false) {
                                        $event_class = 'success';
                                    } elseif (strpos($log['event_type'], 'FAILED') !== false || strpos($log['event_type'], 'LOCKED') !== false) {
                                        $event_class = 'failed';
                                    } elseif (strpos($log['event_type'], 'INJECTION') !== false || strpos($log['event_type'], 'HIJACK') !== false) {
                                        $event_class = 'danger';
                                    } elseif (strpos($log['event_type'], 'DELETED') !== false || strpos($log['event_type'], 'CHANGED') !== false) {
                                        $event_class = 'warning';
                                    }
                                    ?>
                                    <span class="event-type <?php echo $event_class; ?>">
                                        <?php echo Security::sanitizeOutput($log['event_type']); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($log['username']): ?>
                                        <span class="user-info">
                                            <?php echo Security::sanitizeOutput($log['first_name'] . ' ' . $log['last_name']); ?>
                                            <br>
                                            <small><?php echo Security::sanitizeOutput($log['username']); ?></small>
                                        </span>
                                    <?php else: ?>
                                        <span class="no-user">Guest</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo Security::sanitizeOutput($log['event_description']); ?></td>
                                <td><span class="ip-address"><?php echo Security::sanitizeOutput($log['ip_address']); ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <?php if ($total_pages > 1): ?>
            <div class="pagination">
                <?php if ($page > 1): ?>
                    <a href="?page=<?php echo $page - 1; ?>&date_from=<?php echo $date_from; ?>&date_to=<?php echo $date_to; ?>&event_type=<?php echo urlencode($event_type_filter); ?>&user=<?php echo urlencode($user_filter); ?>">← Previous</a>
                <?php endif; ?>
                
                <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                    <?php if ($i == $page): ?>
                        <span class="active"><?php echo $i; ?></span>
                    <?php else: ?>
                        <a href="?page=<?php echo $i; ?>&date_from=<?php echo $date_from; ?>&date_to=<?php echo $date_to; ?>&event_type=<?php echo urlencode($event_type_filter); ?>&user=<?php echo urlencode($user_filter); ?>"><?php echo $i; ?></a>
                    <?php endif; ?>
                <?php endfor; ?>
                
                <?php if ($page < $total_pages): ?>
                    <a href="?page=<?php echo $page + 1; ?>&date_from=<?php echo $date_from; ?>&date_to=<?php echo $date_to; ?>&event_type=<?php echo urlencode($event_type_filter); ?>&user=<?php echo urlencode($user_filter); ?>">Next →</a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>