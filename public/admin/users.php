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

// Handle user status change
if (isset($_GET['toggle']) && isset($_GET['token'])) {
    if (Security::verifyCSRFToken($_GET['token'])) {
        $user_id = (int)$_GET['toggle'];
        
        // Get current status
        $sql = "SELECT is_active FROM users WHERE id = :id AND role != 'admin'";
        $target_user = $db->fetchOne($sql, [':id' => $user_id]);
        
        if ($target_user) {
            $new_status = !$target_user['is_active'];
            $sql = "UPDATE users SET is_active = :status WHERE id = :id";
            $db->execute($sql, [':status' => $new_status, ':id' => $user_id]);
            
            $action = $new_status ? 'activated' : 'deactivated';
            Security::logSecurityEvent('USER_STATUS_CHANGED', "Admin $action user ID: $user_id", $user['id']);
            setAlert("User $action successfully.", 'success');
        }
    } else {
        setAlert('Invalid security token.', 'error');
    }
    redirect('/admin/users.php');
}

// Handle user deletion
if (isset($_GET['delete']) && isset($_GET['token'])) {
    if (Security::verifyCSRFToken($_GET['token'])) {
        $user_id = (int)$_GET['delete'];
        
        // Verify not admin
        $sql = "SELECT role FROM users WHERE id = :id";
        $target_user = $db->fetchOne($sql, [':id' => $user_id]);
        
        if ($target_user && $target_user['role'] !== 'admin') {
            $sql = "DELETE FROM users WHERE id = :id";
            $db->execute($sql, [':id' => $user_id]);
            
            Security::logSecurityEvent('USER_DELETED', "Admin deleted user ID: $user_id", $user['id']);
            setAlert('User deleted successfully.', 'success');
        } else {
            setAlert('Cannot delete admin users.', 'error');
        }
    } else {
        setAlert('Invalid security token.', 'error');
    }
    redirect('/admin/users.php');
}

// Pagination and search
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$search = isset($_GET['search']) ? Security::sanitizeInput($_GET['search']) : '';
$role_filter = isset($_GET['role']) ? Security::sanitizeInput($_GET['role']) : '';
$users_per_page = 15;
$offset = ($page - 1) * $users_per_page;

// Build query
$where_conditions = ["role != 'admin'"];
$params = [];

if ($search) {
    $where_conditions[] = "(username LIKE :search OR email LIKE :search OR first_name LIKE :search OR last_name LIKE :search)";
    $params[':search'] = "%$search%";
}

if ($role_filter) {
    $where_conditions[] = "role = :role";
    $params[':role'] = $role_filter;
}

$where_clause = implode(' AND ', $where_conditions);

// Get total users count
$sql = "SELECT COUNT(*) as total FROM users WHERE $where_clause";
$stmt = $db->getConnection()->prepare($sql);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->execute();
$count_result = $stmt->fetch();
$total_users = $count_result['total'];
$total_pages = ceil($total_users / $users_per_page);

// Get users
$sql = "SELECT u.*, 
        (SELECT SUM(total_cost) FROM fuel_records WHERE user_id = u.id AND DATE_FORMAT(date, '%Y-%m') = DATE_FORMAT(NOW(), '%Y-%m')) as monthly_spending,
        (SELECT monthly_limit FROM fuel_limits WHERE user_id = u.id AND month_year = DATE_FORMAT(NOW(), '%Y-%m-01') LIMIT 1) as monthly_limit
        FROM users u 
        WHERE $where_clause 
        ORDER BY u.created_at DESC 
        LIMIT :limit OFFSET :offset";

$stmt = $db->getConnection()->prepare($sql);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->bindValue(':limit', $users_per_page, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$users = $stmt->fetchAll();

// Generate CSRF token
$csrf_token = Security::generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Management - <?php echo APP_NAME; ?></title>
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
        
        .search-filters {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 30px;
        }
        
        .search-filters form {
            display: flex;
            gap: 15px;
            align-items: end;
            flex-wrap: wrap;
        }
        
        .filter-group {
            flex: 1;
            min-width: 200px;
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
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
        }
        
        .users-table {
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
        
        .user-info {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
        }
        
        .user-details .name {
            font-weight: 500;
            color: #333;
        }
        
        .user-details .email {
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
        
        .status-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 500;
        }
        
        .status-badge.active {
            background: #C8E6C9;
            color: #2E7D32;
        }
        
        .status-badge.inactive {
            background: #FFCDD2;
            color: #C62828;
        }
        
        .spending-info {
            font-size: 13px;
        }
        
        .spending-info .spent {
            color: #333;
            font-weight: 500;
        }
        
        .spending-info .limit {
            color: #666;
        }
        
        .actions {
            display: flex;
            gap: 8px;
        }
        
        .btn-sm {
            padding: 5px 10px;
            font-size: 12px;
            border-radius: 3px;
            text-decoration: none;
            border: none;
            cursor: pointer;
            transition: opacity 0.2s;
        }
        
        .btn-edit {
            background: #4CAF50;
            color: white;
        }
        
        .btn-toggle {
            background: #FF9800;
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
            <div class="logo">
                <?php echo APP_NAME; ?>
                <span class="badge">ADMIN</span>
            </div>
            <nav class="nav-menu">
                <a href="dashboard.php">Dashboard</a>
                <a href="users.php" class="active">Users</a>
                <a href="limits.php">Limits</a>
                <a href="reports.php">Reports</a>
                <a href="logs.php">Security Logs</a>
                <a href="../logout.php">Logout</a>
            </nav>
        </div>
    </header>
    
    <div class="container">
        <?php displayAlert(); ?>
        
        <div class="page-header">
            <div class="page-title">
                <h1>User Management</h1>
                <p>Manage system users and their permissions</p>
            </div>
            <a href="add-user.php" class="btn btn-primary">+ Add New User</a>
        </div>
        
        <div class="search-filters">
            <form method="GET" action="">
                <div class="filter-group">
                    <label for="search">Search</label>
                    <input type="text" 
                           id="search" 
                           name="search" 
                           value="<?php echo Security::sanitizeOutput($search); ?>"
                           placeholder="Search by name, username, or email...">
                </div>
                
                <div class="filter-group">
                    <label for="role">Role</label>
                    <select name="role" id="role">
                        <option value="">All Roles</option>
                        <option value="employee" <?php echo $role_filter === 'employee' ? 'selected' : ''; ?>>Employee</option>
                        <option value="manager" <?php echo $role_filter === 'manager' ? 'selected' : ''; ?>>Manager</option>
                    </select>
                </div>
                
                <button type="submit" class="btn btn-primary">Search</button>
                <a href="users.php" class="btn" style="background: #e0e0e0; color: #333;">Clear</a>
            </form>
        </div>
        
        <div class="users-table">
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>User</th>
                            <th>Username</th>
                            <th>Role</th>
                            <th>Status</th>
                            <th>Monthly Spending</th>
                            <th>Last Login</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $u): ?>
                            <tr>
                                <td>
                                    <div class="user-info">
                                        <div class="user-avatar">
                                            <?php echo strtoupper(substr($u['first_name'], 0, 1) . substr($u['last_name'], 0, 1)); ?>
                                        </div>
                                        <div class="user-details">
                                            <div class="name"><?php echo Security::sanitizeOutput($u['first_name'] . ' ' . $u['last_name']); ?></div>
                                            <div class="email"><?php echo Security::sanitizeOutput($u['email']); ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td><?php echo Security::sanitizeOutput($u['username']); ?></td>
                                <td>
                                    <span class="role-badge <?php echo $u['role']; ?>">
                                        <?php echo ucfirst($u['role']); ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="status-badge <?php echo $u['is_active'] ? 'active' : 'inactive'; ?>">
                                        <?php echo $u['is_active'] ? 'Active' : 'Inactive'; ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="spending-info">
                                        <span class="spent"><?php echo formatCurrency($u['monthly_spending'] ?? 0); ?></span>
                                        <?php if ($u['monthly_limit']): ?>
                                            <span class="limit">/ <?php echo formatCurrency($u['monthly_limit']); ?></span>
                                        <?php else: ?>
                                            <span class="limit">(No limit)</span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td>
                                    <?php echo $u['last_login'] ? formatDate($u['last_login'], 'd M Y H:i') : 'Never'; ?>
                                </td>
                                <td>
                                    <div class="actions">
                                        <a href="edit-user.php?id=<?php echo $u['id']; ?>" class="btn-sm btn-edit">Edit</a>
                                        <a href="?toggle=<?php echo $u['id']; ?>&token=<?php echo $csrf_token; ?>" 
                                           class="btn-sm btn-toggle">
                                            <?php echo $u['is_active'] ? 'Disable' : 'Enable'; ?>
                                        </a>
                                        <a href="?delete=<?php echo $u['id']; ?>&token=<?php echo $csrf_token; ?>" 
                                           class="btn-sm btn-delete" 
                                           onclick="return confirm('Are you sure you want to delete this user? This action cannot be undone.');">
                                            Delete
                                        </a>
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
                    <a href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>&role=<?php echo $role_filter; ?>">← Previous</a>
                <?php endif; ?>
                
                <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                    <?php if ($i == $page): ?>
                        <span class="active"><?php echo $i; ?></span>
                    <?php else: ?>
                        <a href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&role=<?php echo $role_filter; ?>"><?php echo $i; ?></a>
                    <?php endif; ?>
                <?php endfor; ?>
                
                <?php if ($page < $total_pages): ?>
                    <a href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>&role=<?php echo $role_filter; ?>">Next →</a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>