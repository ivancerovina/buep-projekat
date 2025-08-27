<?php
define('APP_RUNNING', true);
require_once 'config/config.php';

$db = Database::getInstance();

echo "Starting simple data seeding (without Faker)...\n\n";

// Simple random data generators
function randomFloat($min, $max, $decimals = 2) {
    return round(($min + mt_rand() / mt_getrandmax() * ($max - $min)), $decimals);
}

function randomElement($array) {
    return $array[array_rand($array)];
}

function randomBetween($min, $max) {
    return mt_rand($min, $max);
}

function randomDate($start, $end) {
    $startTime = strtotime($start);
    $endTime = strtotime($end);
    $randomTime = mt_rand($startTime, $endTime);
    return date('Y-m-d', $randomTime);
}

try {
    // Create test users
    $users_to_create = [
        ['username' => 'manager1', 'email' => 'manager1@company.com', 'role' => 'manager', 'first_name' => 'John', 'last_name' => 'Smith'],
        ['username' => 'manager2', 'email' => 'manager2@company.com', 'role' => 'manager', 'first_name' => 'Sarah', 'last_name' => 'Johnson'],
        ['username' => 'employee1', 'email' => 'employee1@company.com', 'role' => 'employee', 'first_name' => 'Mike', 'last_name' => 'Wilson'],
        ['username' => 'employee2', 'email' => 'employee2@company.com', 'role' => 'employee', 'first_name' => 'Lisa', 'last_name' => 'Brown'],
        ['username' => 'employee3', 'email' => 'employee3@company.com', 'role' => 'employee', 'first_name' => 'David', 'last_name' => 'Davis'],
        ['username' => 'employee4', 'email' => 'employee4@company.com', 'role' => 'employee', 'first_name' => 'Anna', 'last_name' => 'Miller'],
        ['username' => 'employee5', 'email' => 'employee5@company.com', 'role' => 'employee', 'first_name' => 'Tom', 'last_name' => 'Garcia'],
        ['username' => 'employee6', 'email' => 'employee6@company.com', 'role' => 'employee', 'first_name' => 'Emma', 'last_name' => 'Martinez']
    ];

    $created_users = [];
    foreach ($users_to_create as $user_data) {
        $sql = "SELECT id FROM users WHERE username = :username OR email = :email";
        $existing = $db->fetchOne($sql, [':username' => $user_data['username'], ':email' => $user_data['email']]);
        
        if (!$existing) {
            $password_hash = Security::hashPassword('Password123!');
            $sql = "INSERT INTO users (username, email, password_hash, first_name, last_name, role, is_active) 
                    VALUES (:username, :email, :password_hash, :first_name, :last_name, :role, 1)";
            
            $db->execute($sql, [
                ':username' => $user_data['username'],
                ':email' => $user_data['email'],
                ':password_hash' => $password_hash,
                ':first_name' => $user_data['first_name'],
                ':last_name' => $user_data['last_name'],
                ':role' => $user_data['role']
            ]);
            
            $user_id = $db->getConnection()->lastInsertId();
            $created_users[] = $user_id;
            echo "Created user: {$user_data['username']} (ID: $user_id)\n";
        } else {
            $created_users[] = $existing['id'];
            echo "User {$user_data['username']} already exists (ID: {$existing['id']})\n";
        }
    }

    echo "\nCreating fuel consumption limits...\n";
    
    foreach ($created_users as $user_id) {
        for ($i = 0; $i < 12; $i++) {
            $month_year = date('Y-m-01', strtotime("-$i months"));
            
            $sql = "SELECT id FROM fuel_limits WHERE user_id = :user_id AND month_year = :month_year";
            $existing_limit = $db->fetchOne($sql, [':user_id' => $user_id, ':month_year' => $month_year]);
            
            if (!$existing_limit) {
                $monthly_limit = randomBetween(15000, 50000);
                
                $sql = "INSERT INTO fuel_limits (user_id, month_year, monthly_limit, created_by) 
                        VALUES (:user_id, :month_year, :monthly_limit, :created_by)";
                
                $db->execute($sql, [
                    ':user_id' => $user_id,
                    ':month_year' => $month_year,
                    ':monthly_limit' => $monthly_limit,
                    ':created_by' => 1  // Admin user ID (assuming admin is user ID 1)
                ]);
            }
        }
    }

    echo "Creating fuel records...\n";
    
    $total_records = 0;
    $gas_stations = [
        'NIS Petrol', 'OMV', 'MOL', 'Lukoil', 'Gazprom', 'INA', 'Rompetrol', 
        'Shell', 'BP', 'EKO', 'Tesco Petrol', 'Mercator Petrol'
    ];
    
    $cities = [
        'Belgrade', 'Novi Sad', 'Niš', 'Kragujevac', 'Subotica', 'Pančevo', 
        'Čačak', 'Novi Pazar', 'Zrenjanin', 'Leskovac', 'Užice', 'Valjevo'
    ];

    $note_options = [
        'Business trip', 'Regular commute', 'Weekend trip', 
        'Full tank', 'Emergency refill', 'Highway travel',
        'City driving', 'Long distance travel', ''
    ];

    foreach ($created_users as $user_id) {
        echo "Generating records for user ID: $user_id\n";
        
        $current_mileage = randomBetween(50000, 150000);
        $records_for_user = randomBetween(25, 40);
        
        // Generate records for the past 12 months
        $dates = [];
        for ($i = 0; $i < $records_for_user; $i++) {
            $dates[] = randomDate('-12 months', 'now');
        }
        sort($dates); // Sort dates chronologically
        
        foreach ($dates as $date) {
            $current_mileage += randomBetween(200, 800);
            
            $liters = randomFloat(25, 65, 2);
            $price_per_liter = randomFloat(140, 180, 2);
            $total_cost = $liters * $price_per_liter;
            
            $city = randomElement($cities);
            $station = randomElement($gas_stations);
            $location = "$station, $city";
            
            $notes = randomBetween(1, 10) <= 3 ? randomElement($note_options) : '';
            
            $sql = "INSERT INTO fuel_records (user_id, date, mileage, liters, price_per_liter, total_cost, location, notes, created_at) 
                    VALUES (:user_id, :date, :mileage, :liters, :price_per_liter, :total_cost, :location, :notes, NOW())";
            
            $db->execute($sql, [
                ':user_id' => $user_id,
                ':date' => $date,
                ':mileage' => $current_mileage,
                ':liters' => $liters,
                ':price_per_liter' => $price_per_liter,
                ':total_cost' => $total_cost,
                ':location' => $location,
                ':notes' => $notes
            ]);
            
            $total_records++;
        }
    }

    echo "\nGenerating security log entries...\n";
    
    $security_events = [
        'LOGIN_SUCCESS', 'LOGIN_FAILED', 'LOGOUT', 'PASSWORD_CHANGED',
        'PROFILE_UPDATED', 'FUEL_RECORD_ADDED', 'FUEL_RECORD_UPDATED', 'FUEL_RECORD_DELETED'
    ];
    
    $descriptions = [
        'LOGIN_SUCCESS' => 'User logged in successfully',
        'LOGIN_FAILED' => 'Failed login attempt',
        'LOGOUT' => 'User logged out',
        'PASSWORD_CHANGED' => 'User changed password',
        'PROFILE_UPDATED' => 'User updated profile information',
        'FUEL_RECORD_ADDED' => 'User added new fuel record',
        'FUEL_RECORD_UPDATED' => 'User updated fuel record',
        'FUEL_RECORD_DELETED' => 'User deleted fuel record'
    ];
    
    for ($i = 0; $i < 100; $i++) {
        $user_id = randomElement($created_users);
        $event_type = randomElement($security_events);
        $event_date = date('Y-m-d H:i:s', strtotime(randomDate('-6 months', 'now') . ' ' . randomBetween(0, 23) . ':' . randomBetween(0, 59) . ':' . randomBetween(0, 59)));
        
        $description = $descriptions[$event_type];
        $ip_address = randomBetween(1, 255) . '.' . randomBetween(1, 255) . '.' . randomBetween(1, 255) . '.' . randomBetween(1, 255);
        
        $user_agents = [
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
            'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36',
            'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36'
        ];
        $user_agent = randomElement($user_agents);
        
        $sql = "INSERT INTO security_logs (user_id, event_type, description, ip_address, user_agent, created_at) 
                VALUES (:user_id, :event_type, :description, :ip_address, :user_agent, :created_at)";
        
        $db->execute($sql, [
            ':user_id' => $user_id,
            ':event_type' => $event_type,
            ':description' => $description,
            ':ip_address' => $ip_address,
            ':user_agent' => $user_agent,
            ':created_at' => $event_date
        ]);
    }

    echo "\n✅ Data seeding completed successfully!\n\n";
    echo "Summary:\n";
    echo "- Users created/verified: " . count($created_users) . "\n";
    echo "- Fuel records generated: $total_records\n";
    echo "- Security logs generated: 100\n";
    echo "- Monthly limits set for 12 months per user\n\n";
    
    echo "Test user credentials:\n";
    echo "Admin: admin / Admin123!\n";
    echo "Managers: manager1@company.com, manager2@company.com / Password123!\n";
    echo "Employees: employee1@company.com through employee6@company.com / Password123!\n\n";
    
} catch (Exception $e) {
    echo "❌ Error during seeding: " . $e->getMessage() . "\n";
}
?>