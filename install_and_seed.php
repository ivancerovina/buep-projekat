<?php
echo "=== Fuel Tracking Application - Installation & Data Seeding ===\n\n";

// Check if composer is available
$composerExists = false;
exec('composer --version 2>&1', $output, $return_code);
if ($return_code === 0) {
    $composerExists = true;
    echo "✅ Composer found\n";
} else {
    echo "❌ Composer not found. Please install Composer first.\n";
    echo "Download from: https://getcomposer.org/download/\n\n";
}

if ($composerExists) {
    echo "Installing dependencies...\n";
    exec('composer install 2>&1', $install_output, $install_code);
    
    if ($install_code === 0) {
        echo "✅ Dependencies installed successfully\n\n";
        
        // Include the autoloader
        if (file_exists('vendor/autoload.php')) {
            require_once 'vendor/autoload.php';
            echo "✅ Autoloader included\n\n";
            
            // Run the seeder
            echo "Running data seeder...\n";
            echo "----------------------------\n";
            include 'seed_data.php';
            
        } else {
            echo "❌ Autoloader not found\n";
        }
    } else {
        echo "❌ Failed to install dependencies\n";
        print_r($install_output);
    }
} else {
    echo "\nAlternative: Manual Faker installation\n";
    echo "1. Download Faker from: https://github.com/FakerPHP/Faker/releases\n";
    echo "2. Extract to vendor/fakerphp/faker/\n";
    echo "3. Run seed_data.php manually\n";
}
?>