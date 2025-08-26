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

$errors = [];
$success = false;
$form_data = [
    'date' => date('Y-m-d'),
    'mileage' => '',
    'liters' => '',
    'price_per_liter' => '',
    'location' => '',
    'notes' => ''
];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify CSRF token
    if (!isset($_POST[CSRF_TOKEN_NAME]) || !Security::verifyCSRFToken($_POST[CSRF_TOKEN_NAME])) {
        $errors[] = "Invalid security token. Please refresh and try again.";
    } else {
        // Get and sanitize input
        $form_data['date'] = Security::sanitizeInput($_POST['date'] ?? '');
        $form_data['mileage'] = Security::sanitizeInput($_POST['mileage'] ?? '');
        $form_data['liters'] = Security::sanitizeInput($_POST['liters'] ?? '');
        $form_data['price_per_liter'] = Security::sanitizeInput($_POST['price_per_liter'] ?? '');
        $form_data['location'] = Security::sanitizeInput($_POST['location'] ?? '');
        $form_data['notes'] = Security::sanitizeInput($_POST['notes'] ?? '');
        
        // Validate input
        if (empty($form_data['date'])) {
            $errors[] = "Date is required.";
        } elseif (strtotime($form_data['date']) > time()) {
            $errors[] = "Date cannot be in the future.";
        }
        
        if (empty($form_data['mileage'])) {
            $errors[] = "Mileage is required.";
        } elseif (!is_numeric($form_data['mileage']) || $form_data['mileage'] <= 0) {
            $errors[] = "Mileage must be a positive number.";
        }
        
        if (empty($form_data['liters'])) {
            $errors[] = "Fuel amount is required.";
        } elseif (!is_numeric($form_data['liters']) || $form_data['liters'] <= 0) {
            $errors[] = "Fuel amount must be a positive number.";
        }
        
        if (empty($form_data['price_per_liter'])) {
            $errors[] = "Price per liter is required.";
        } elseif (!is_numeric($form_data['price_per_liter']) || $form_data['price_per_liter'] <= 0) {
            $errors[] = "Price per liter must be a positive number.";
        }
        
        // Calculate total cost
        $total_cost = 0;
        if (is_numeric($form_data['liters']) && is_numeric($form_data['price_per_liter'])) {
            $total_cost = $form_data['liters'] * $form_data['price_per_liter'];
        }
        
        // Check for previous mileage (new mileage should be higher)
        if (empty($errors)) {
            $sql = "SELECT MAX(mileage) as last_mileage FROM fuel_records WHERE user_id = :user_id";
            $last_record = $db->fetchOne($sql, [':user_id' => $user['id']]);
            
            if ($last_record && $last_record['last_mileage'] && $form_data['mileage'] <= $last_record['last_mileage']) {
                $errors[] = "Mileage must be higher than your last record (" . number_format($last_record['last_mileage'], 0) . " km).";
            }
        }
        
        // Check monthly limit
        if (empty($errors)) {
            $month_start = date('Y-m-01', strtotime($form_data['date']));
            
            // Get current month's spending
            $sql = "SELECT SUM(total_cost) as total_spent FROM fuel_records 
                    WHERE user_id = :user_id AND date >= :month_start";
            $monthly_spending = $db->fetchOne($sql, [
                ':user_id' => $user['id'],
                ':month_start' => $month_start
            ]);
            
            // Get monthly limit
            $sql = "SELECT monthly_limit FROM fuel_limits 
                    WHERE user_id = :user_id AND month_year = :month_year";
            $limit = $db->fetchOne($sql, [
                ':user_id' => $user['id'],
                ':month_year' => $month_start
            ]);
            
            if ($limit) {
                $current_spent = $monthly_spending['total_spent'] ?? 0;
                $new_total = $current_spent + $total_cost;
                
                if ($new_total > $limit['monthly_limit']) {
                    $errors[] = "This purchase would exceed your monthly limit. Current spending: " . 
                               formatCurrency($current_spent) . ", Limit: " . 
                               formatCurrency($limit['monthly_limit']);
                }
            }
        }
        
        // If no errors, save the record
        if (empty($errors)) {
            try {
                $sql = "INSERT INTO fuel_records (user_id, date, mileage, liters, price_per_liter, total_cost, location, notes) 
                        VALUES (:user_id, :date, :mileage, :liters, :price_per_liter, :total_cost, :location, :notes)";
                
                $db->execute($sql, [
                    ':user_id' => $user['id'],
                    ':date' => $form_data['date'],
                    ':mileage' => $form_data['mileage'],
                    ':liters' => $form_data['liters'],
                    ':price_per_liter' => $form_data['price_per_liter'],
                    ':total_cost' => $total_cost,
                    ':location' => $form_data['location'],
                    ':notes' => $form_data['notes']
                ]);
                
                // Log the action
                Security::logSecurityEvent('FUEL_RECORD_ADDED', "User added fuel record: " . formatCurrency($total_cost), $user['id']);
                
                $success = true;
                
                // Clear form data
                $form_data = [
                    'date' => date('Y-m-d'),
                    'mileage' => '',
                    'liters' => '',
                    'price_per_liter' => '',
                    'location' => '',
                    'notes' => ''
                ];
                
            } catch (Exception $e) {
                error_log("Error adding fuel record: " . $e->getMessage());
                $errors[] = "An error occurred while saving the record. Please try again.";
            }
        }
    }
}

// Generate CSRF token
$csrf_token = Security::generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Fuel Record - <?php echo APP_NAME; ?></title>
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
            max-width: 800px;
            margin: 30px auto;
            padding: 0 20px;
        }
        
        .page-title {
            background: white;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 30px;
        }
        
        .page-title h1 {
            color: #333;
            margin-bottom: 10px;
        }
        
        .page-title p {
            color: #666;
        }
        
        .form-container {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 5px;
            color: #333;
            font-weight: 500;
            font-size: 14px;
        }
        
        .form-group input,
        .form-group textarea {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
            transition: border-color 0.3s;
        }
        
        .form-group textarea {
            resize: vertical;
            min-height: 100px;
        }
        
        .form-group input:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #667eea;
        }
        
        .form-group .help-text {
            font-size: 12px;
            color: #999;
            margin-top: 5px;
        }
        
        .error-messages {
            background: #fee;
            border: 1px solid #fcc;
            color: #c00;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        
        .error-messages ul {
            list-style: none;
            margin: 0;
        }
        
        .error-messages li {
            font-size: 14px;
            margin-bottom: 5px;
        }
        
        .error-messages li:last-child {
            margin-bottom: 0;
        }
        
        .success-message {
            background: #efe;
            border: 1px solid #cfc;
            color: #060;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        
        .form-actions {
            display: flex;
            gap: 10px;
            margin-top: 30px;
        }
        
        .btn {
            padding: 12px 30px;
            border: none;
            border-radius: 5px;
            font-size: 16px;
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
        
        .btn-secondary {
            background: #e0e0e0;
            color: #333;
        }
        
        .btn-secondary:hover {
            background: #d0d0d0;
        }
        
        .total-cost-display {
            background: #f9f9f9;
            padding: 15px;
            border-radius: 5px;
            text-align: center;
            margin-top: 20px;
        }
        
        .total-cost-display .label {
            color: #666;
            font-size: 14px;
            margin-bottom: 5px;
        }
        
        .total-cost-display .value {
            font-size: 24px;
            font-weight: bold;
            color: #333;
        }
    </style>
</head>
<body>
    <header class="header">
        <div class="header-content">
            <div class="logo"><?php echo APP_NAME; ?></div>
            <nav class="nav-menu">
                <a href="dashboard.php">Dashboard</a>
                <a href="fuel-records.php">My Records</a>
                <a href="profile.php">Profile</a>
                <a href="logout.php">Logout</a>
            </nav>
        </div>
    </header>
    
    <div class="container">
        <div class="page-title">
            <h1>Add Fuel Record</h1>
            <p>Record your fuel purchase details</p>
        </div>
        
        <div class="form-container">
            <?php if ($success): ?>
                <div class="success-message">
                    <strong>Success!</strong> Your fuel record has been added successfully.
                    <a href="fuel-records.php" style="color: #060; text-decoration: underline;">View all records</a>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($errors)): ?>
                <div class="error-messages">
                    <ul>
                        <?php foreach ($errors as $error): ?>
                            <li><?php echo Security::sanitizeOutput($error); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="">
                <div class="form-row">
                    <div class="form-group">
                        <label for="date">Date</label>
                        <input type="date" 
                               id="date" 
                               name="date" 
                               value="<?php echo Security::sanitizeOutput($form_data['date']); ?>"
                               max="<?php echo date('Y-m-d'); ?>"
                               required>
                    </div>
                    
                    <div class="form-group">
                        <label for="mileage">Current Mileage (km)</label>
                        <input type="number" 
                               id="mileage" 
                               name="mileage" 
                               value="<?php echo Security::sanitizeOutput($form_data['mileage']); ?>"
                               step="0.01"
                               min="0"
                               required>
                        <div class="help-text">Your vehicle's current odometer reading</div>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="liters">Fuel Amount (Liters)</label>
                        <input type="number" 
                               id="liters" 
                               name="liters" 
                               value="<?php echo Security::sanitizeOutput($form_data['liters']); ?>"
                               step="0.01"
                               min="0"
                               required>
                    </div>
                    
                    <div class="form-group">
                        <label for="price_per_liter">Price per Liter (RSD)</label>
                        <input type="number" 
                               id="price_per_liter" 
                               name="price_per_liter" 
                               value="<?php echo Security::sanitizeOutput($form_data['price_per_liter']); ?>"
                               step="0.01"
                               min="0"
                               required>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="location">Location</label>
                    <input type="text" 
                           id="location" 
                           name="location" 
                           value="<?php echo Security::sanitizeOutput($form_data['location']); ?>"
                           maxlength="255"
                           placeholder="e.g., Petrol Station Name, City">
                </div>
                
                <div class="form-group">
                    <label for="notes">Notes (Optional)</label>
                    <textarea id="notes" 
                              name="notes" 
                              placeholder="Any additional information..."><?php echo Security::sanitizeOutput($form_data['notes']); ?></textarea>
                </div>
                
                <div class="total-cost-display" id="totalCostDisplay" style="display: none;">
                    <div class="label">Total Cost</div>
                    <div class="value" id="totalCostValue">0.00 RSD</div>
                </div>
                
                <?php echo Security::getCSRFTokenField(); ?>
                
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">Save Record</button>
                    <a href="dashboard.php" class="btn btn-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        // Calculate and display total cost in real-time
        function calculateTotal() {
            const liters = parseFloat(document.getElementById('liters').value) || 0;
            const pricePerLiter = parseFloat(document.getElementById('price_per_liter').value) || 0;
            const total = liters * pricePerLiter;
            
            const displayDiv = document.getElementById('totalCostDisplay');
            const valueDiv = document.getElementById('totalCostValue');
            
            if (liters > 0 && pricePerLiter > 0) {
                displayDiv.style.display = 'block';
                valueDiv.textContent = total.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ",") + ' RSD';
            } else {
                displayDiv.style.display = 'none';
            }
        }
        
        document.getElementById('liters').addEventListener('input', calculateTotal);
        document.getElementById('price_per_liter').addEventListener('input', calculateTotal);
        
        // Prevent form resubmission on page refresh
        if (window.history.replaceState) {
            window.history.replaceState(null, null, window.location.href);
        }
    </script>
</body>
</html>