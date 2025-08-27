<?php
define('APP_RUNNING', true);
require_once 'config/config.php';

// Check if Faker is available
if (!class_exists('Faker\Factory')) {
    die("Faker library not found. Please install it using: composer require fakerphp/faker\n");
}

$db = Database::getInstance();
$faker = Faker\Factory::create();

echo "Starting data seeding...\n\n";

try {
    // Create some test users if they don't exist
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
        // Check if user already exists
        $sql = "SELECT id FROM users WHERE username = :username OR email = :email";
        $existing = $db->fetchOne($sql, [':username' => $user_data['username'], ':email' => $user_data['email']]);
        
        if (!$existing) {
            // Create user
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
    
    // Create fuel limits for the last 12 months
    foreach ($created_users as $user_id) {
        for ($i = 0; $i < 12; $i++) {
            $month_year = date('Y-m-01', strtotime("-$i months"));
            
            // Check if limit already exists
            $sql = "SELECT id FROM fuel_limits WHERE user_id = :user_id AND month_year = :month_year";
            $existing_limit = $db->fetchOne($sql, [':user_id' => $user_id, ':month_year' => $month_year]);
            
            if (!$existing_limit) {
                $monthly_limit = $faker->numberBetween(15000, 50000); // 15,000 - 50,000 RSD
                
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
    
    // Generate fuel records for each user
    $total_records = 0;
    $gas_stations = [
        'NIS Petrol', 'OMV', 'MOL', 'Lukoil', 'Gazprom', 'INA', 'Rompetrol', 
        'Shell', 'BP', 'EKO', 'Tesco Petrol', 'Mercator Petrol'
    ];
    
    $cities = [
        'Belgrade', 'Novi Sad', 'Niš', 'Kragujevac', 'Subotica', 'Pančevo', 
        'Čačak', 'Novi Pazar', 'Zrenjanin', 'Leskovac', 'Užice', 'Valjevo'
    ];

    foreach ($created_users as $user_id) {
        echo "Generating records for user ID: $user_id\n";
        
        $current_mileage = $faker->numberBetween(50000, 150000); // Starting mileage
        $records_for_user = $faker->numberBetween(25, 40); // 25-40 records per user
        
        // Generate records over the last 12 months
        for ($i = 0; $i < $records_for_user; $i++) {
            // Random date within last 12 months
            $date = $faker->dateTimeBetween('-12 months', 'now')->format('Y-m-d');
            
            // Increase mileage for each record (simulate driving)
            $current_mileage += $faker->numberBetween(200, 800);
            
            // Realistic fuel data
            $liters = $faker->randomFloat(2, 25, 65); // 25-65 liters
            $price_per_liter = $faker->randomFloat(2, 140, 180); // 140-180 RSD per liter
            $total_cost = $liters * $price_per_liter;
            
            // Location
            $city = $faker->randomElement($cities);
            $station = $faker->randomElement($gas_stations);
            $location = "$station, $city";
            
            // Optional notes (30% chance)
            $notes = '';
            if ($faker->boolean(30)) {
                $note_options = [
                    'Business trip', 'Regular commute', 'Weekend trip', 
                    'Full tank', 'Emergency refill', 'Highway travel',
                    'City driving', 'Long distance travel'
                ];
                $notes = $faker->randomElement($note_options);
            }
            
            // Insert fuel record
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
    
    // Generate some security log entries
    $security_events = [
        'LOGIN_SUCCESS', 'LOGIN_FAILED', 'LOGOUT', 'PASSWORD_CHANGED',
        'PROFILE_UPDATED', 'FUEL_RECORD_ADDED', 'FUEL_RECORD_UPDATED', 'FUEL_RECORD_DELETED'
    ];
    
    for ($i = 0; $i < 100; $i++) {
        $user_id = $faker->randomElement($created_users);
        $event_type = $faker->randomElement($security_events);
        $event_date = $faker->dateTimeBetween('-6 months', 'now')->format('Y-m-d H:i:s');
        
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
        
        $description = $descriptions[$event_type];
        $ip_address = $faker->ipv4();
        $user_agent = $faker->userAgent();
        
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
    
    echo "Test user credentials (all passwords: Password123!):\n";
    echo "Admin: admin / Admin123!\n";
    echo "Managers: manager1@company.com, manager2@company.com\n";
    echo "Employees: employee1@company.com through employee6@company.com\n\n";
    
} catch (Exception $e) {
    echo "❌ Error during seeding: " . $e->getMessage() . "\n";
    echo "Stack trace: " . $e->getTraceAsString() . "\n";
}
?>