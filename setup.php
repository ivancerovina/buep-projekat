<?php
// Setup script - Run this once to initialize the database
define('APP_RUNNING', true);
require_once '../config/config.php';

$db = Database::getInstance();

echo "<h2>Fuel Tracking System - Database Setup</h2>";

try {
    // Create admin user with default password
    $admin_password = 'Admin123!';
    $admin_hash = Security::hashPassword($admin_password);
    
    // Check if admin exists
    $sql = "SELECT id FROM users WHERE username = 'admin'";
    $existing = $db->fetchOne($sql);
    
    if (!$existing) {
        $sql = "INSERT INTO users (username, email, password_hash, first_name, last_name, role, is_active) 
                VALUES ('admin', 'admin@fueltracker.com', :password, 'System', 'Administrator', 'admin', 1)";
        $db->execute($sql, [':password' => $admin_hash]);
        echo "<p style='color: green;'>✓ Admin user created successfully</p>";
        echo "<p><strong>Admin Login:</strong><br>";
        echo "Username: admin<br>";
        echo "Password: Admin123!</p>";
    } else {
        echo "<p style='color: blue;'>ℹ Admin user already exists</p>";
    }
    
    // Create sample employee user
    $sql = "SELECT id FROM users WHERE username = 'john.doe'";
    $existing = $db->fetchOne($sql);
    
    if (!$existing) {
        $employee_password = 'Employee123!';
        $employee_hash = Security::hashPassword($employee_password);
        
        $sql = "INSERT INTO users (username, email, password_hash, first_name, last_name, role, is_active) 
                VALUES ('john.doe', 'john.doe@company.com', :password, 'John', 'Doe', 'employee', 1)";
        $db->execute($sql, [':password' => $employee_hash]);
        $employee_id = $db->lastInsertId();
        
        echo "<p style='color: green;'>✓ Sample employee user created</p>";
        echo "<p><strong>Employee Login:</strong><br>";
        echo "Username: john.doe<br>";
        echo "Password: Employee123!</p>";
        
        // Set a fuel limit for the employee
        $current_month = date('Y-m-01');
        $sql = "INSERT INTO fuel_limits (user_id, month_year, monthly_limit, created_by) 
                VALUES (:user_id, :month_year, :limit, 1)
                ON DUPLICATE KEY UPDATE monthly_limit = :limit";
        $db->execute($sql, [
            ':user_id' => $employee_id,
            ':month_year' => $current_month,
            ':limit' => 50000
        ]);
        echo "<p style='color: green;'>✓ Monthly fuel limit set for employee (50,000 RSD)</p>";
    } else {
        echo "<p style='color: blue;'>ℹ Sample employee already exists</p>";
    }
    
    // Create sample manager user
    $sql = "SELECT id FROM users WHERE username = 'jane.smith'";
    $existing = $db->fetchOne($sql);
    
    if (!$existing) {
        $manager_password = 'Manager123!';
        $manager_hash = Security::hashPassword($manager_password);
        
        $sql = "INSERT INTO users (username, email, password_hash, first_name, last_name, role, is_active) 
                VALUES ('jane.smith', 'jane.smith@company.com', :password, 'Jane', 'Smith', 'manager', 1)";
        $db->execute($sql, [':password' => $manager_hash]);
        
        echo "<p style='color: green;'>✓ Sample manager user created</p>";
        echo "<p><strong>Manager Login:</strong><br>";
        echo "Username: jane.smith<br>";
        echo "Password: Manager123!</p>";
    } else {
        echo "<p style='color: blue;'>ℹ Sample manager already exists</p>";
    }
    
    echo "<hr>";
    echo "<h3>Setup Complete!</h3>";
    echo "<p>You can now <a href='public/login.php'>login to the system</a></p>";
    echo "<p style='color: red;'><strong>Security Note:</strong> Please delete this setup.php file after running it!</p>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>✗ Error during setup: " . $e->getMessage() . "</p>";
    echo "<p>Please make sure:</p>";
    echo "<ul>";
    echo "<li>MySQL/MariaDB is running</li>";
    echo "<li>The database 'fuel_database' has been created</li>";
    echo "<li>The SQL schema has been imported from sql/schema.sql</li>";
    echo "</ul>";
}
?>
