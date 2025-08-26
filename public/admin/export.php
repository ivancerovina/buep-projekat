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

// Get export parameters
$type = isset($_GET['type']) ? $_GET['type'] : 'monthly';
$month = isset($_GET['month']) ? $_GET['month'] : date('Y-m');
$year = isset($_GET['year']) ? $_GET['year'] : date('Y');

// Set headers for CSV download
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="fuel_report_' . $type . '_' . date('Y-m-d') . '.csv"');

// Create output stream
$output = fopen('php://output', 'w');

// Log export action
Security::logSecurityEvent('DATA_EXPORT', "Admin exported $type report", $user['id']);

if ($type === 'monthly') {
    // Monthly report
    fputcsv($output, ['Monthly Fuel Consumption Report - ' . date('F Y', strtotime($month . '-01'))]);
    fputcsv($output, []); // Empty line
    
    fputcsv($output, [
        'Employee Name',
        'Username', 
        'Role',
        'Total Records',
        'Total Liters',
        'Total Cost (RSD)',
        'Monthly Limit (RSD)',
        'Usage Percentage'
    ]);
    
    $sql = "SELECT 
            u.username, u.first_name, u.last_name, u.role,
            COUNT(fr.id) as total_records,
            COALESCE(SUM(fr.liters), 0) as total_liters,
            COALESCE(SUM(fr.total_cost), 0) as total_cost,
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
    
    foreach ($report_data as $row) {
        fputcsv($output, [
            $row['first_name'] . ' ' . $row['last_name'],
            $row['username'],
            ucfirst($row['role']),
            $row['total_records'],
            number_format($row['total_liters'], 2),
            number_format($row['total_cost'], 2),
            $row['monthly_limit'] ? number_format($row['monthly_limit'], 2) : 'No limit',
            number_format($row['usage_percentage'], 2) . '%'
        ]);
    }
    
} elseif ($type === 'yearly') {
    // Yearly report
    fputcsv($output, ['Yearly Fuel Consumption Report - ' . $year]);
    fputcsv($output, []); // Empty line
    
    fputcsv($output, [
        'Month',
        'Active Users',
        'Total Records',
        'Total Liters',
        'Total Cost (RSD)',
        'Average Price per Liter (RSD)'
    ]);
    
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
    
    foreach ($report_data as $row) {
        fputcsv($output, [
            $row['month_name'],
            $row['active_users'],
            $row['total_records'],
            number_format($row['total_liters'], 2),
            number_format($row['total_cost'], 2),
            number_format($row['avg_price'], 2)
        ]);
    }
    
} elseif ($type === 'department') {
    // Department report
    fputcsv($output, ['Department Fuel Consumption Report - ' . date('F Y', strtotime($month . '-01'))]);
    fputcsv($output, []); // Empty line
    
    fputcsv($output, [
        'Department',
        'User Count',
        'Total Records',
        'Total Liters',
        'Total Cost (RSD)',
        'Average Price per Liter (RSD)'
    ]);
    
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
    
    foreach ($report_data as $row) {
        fputcsv($output, [
            ucfirst($row['role']),
            $row['user_count'],
            $row['total_records'],
            number_format($row['total_liters'], 2),
            number_format($row['total_cost'], 2),
            number_format($row['avg_price'], 2)
        ]);
    }
}

// Add export metadata
fputcsv($output, []);
fputcsv($output, ['Export Information:']);
fputcsv($output, ['Generated on', date('Y-m-d H:i:s')]);
fputcsv($output, ['Generated by', $user['username']]);
fputcsv($output, ['Report type', ucfirst($type) . ' Report']);

fclose($output);
?>