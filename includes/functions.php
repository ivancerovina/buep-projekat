<?php
// General helper functions

// Prevent direct access
if (!defined('APP_RUNNING')) {
    http_response_code(403);
    die('Direct access not permitted');
}

// Redirect to URL
function redirect($url) {
    // Check if headers have already been sent
    if (headers_sent($file, $line)) {
        // If headers were sent, use JavaScript redirect as fallback
        echo "<script>window.location.href='$url';</script>";
        echo "<noscript><meta http-equiv='refresh' content='0;url=$url'></noscript>";
        exit();
    }
    
    // Send redirect header
    header("Location: $url");
    exit();
}

// Display alert message
function setAlert($message, $type = 'info') {
    $_SESSION['alert'] = [
        'message' => $message,
        'type' => $type
    ];
}

// Get and clear alert message
function getAlert() {
    if (isset($_SESSION['alert'])) {
        $alert = $_SESSION['alert'];
        unset($_SESSION['alert']);
        return $alert;
    }
    return null;
}

// Display alert HTML
function displayAlert() {
    $alert = getAlert();
    if ($alert) {
        $type_class = [
            'success' => 'alert-success',
            'error' => 'alert-danger',
            'warning' => 'alert-warning',
            'info' => 'alert-info'
        ];
        
        $class = $type_class[$alert['type']] ?? 'alert-info';
        
        echo '<div class="alert ' . $class . ' alert-dismissible fade show" role="alert">';
        echo Security::sanitizeOutput($alert['message']);
        echo '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>';
        echo '</div>';
    }
}

// Format date
function formatDate($date, $format = 'Y-m-d H:i:s') {
    return date($format, strtotime($date));
}

// Format currency
function formatCurrency($amount) {
    return number_format($amount, 2, '.', ',') . ' RSD';
}

// Get base URL
function getBaseUrl() {
    return APP_URL;
}

// Get asset URL
function asset($path) {
    return getBaseUrl() . '/assets/' . ltrim($path, '/');
}

// Check if current page
function isCurrentPage($page) {
    $current = basename($_SERVER['PHP_SELF']);
    return $current === $page;
}

// Generate pagination
function generatePagination($total_items, $items_per_page, $current_page, $url) {
    $total_pages = ceil($total_items / $items_per_page);
    
    if ($total_pages <= 1) {
        return '';
    }
    
    $html = '<nav><ul class="pagination">';
    
    // Previous button
    if ($current_page > 1) {
        $html .= '<li class="page-item">';
        $html .= '<a class="page-link" href="' . $url . '?page=' . ($current_page - 1) . '">Previous</a>';
        $html .= '</li>';
    }
    
    // Page numbers
    for ($i = 1; $i <= $total_pages; $i++) {
        $active = ($i == $current_page) ? 'active' : '';
        $html .= '<li class="page-item ' . $active . '">';
        $html .= '<a class="page-link" href="' . $url . '?page=' . $i . '">' . $i . '</a>';
        $html .= '</li>';
    }
    
    // Next button
    if ($current_page < $total_pages) {
        $html .= '<li class="page-item">';
        $html .= '<a class="page-link" href="' . $url . '?page=' . ($current_page + 1) . '">Next</a>';
        $html .= '</li>';
    }
    
    $html .= '</ul></nav>';
    
    return $html;
}

// Send email
function sendEmail($to, $subject, $body, $is_html = true) {
    // Include PHPMailer
    require_once APP_ROOT . '/vendor/autoload.php';
    
    try {
        $mail = new PHPMailer\PHPMailer\PHPMailer(true);
        
        // Server settings
        $mail->isSMTP();
        $mail->Host = SMTP_HOST;
        $mail->SMTPAuth = true;
        $mail->Username = SMTP_USERNAME;
        $mail->Password = SMTP_PASSWORD;
        $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = SMTP_PORT;
        
        // Recipients
        $mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
        $mail->addAddress($to);
        $mail->addReplyTo(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
        
        // Content
        $mail->isHTML($is_html);
        $mail->Subject = $subject;
        $mail->Body = $body;
        if ($is_html) {
            $mail->AltBody = strip_tags($body);
        }
        
        // Send email
        $mail->send();
        
        // Log success in debug mode
        if (DEBUG_MODE) {
            $log_message = date('Y-m-d H:i:s') . " | SUCCESS | Email to: $to | Subject: $subject" . PHP_EOL;
            @file_put_contents(LOG_PATH . 'emails.log', $log_message, FILE_APPEND);
        }
        
        return true;
        
    } catch (PHPMailer\PHPMailer\Exception $e) {
        // Log error
        $error_message = date('Y-m-d H:i:s') . " | ERROR | Email to: $to | Subject: $subject | Error: " . $e->getMessage() . PHP_EOL;
        @file_put_contents(LOG_PATH . 'emails.log', $error_message, FILE_APPEND);
        
        if (DEBUG_MODE) {
            error_log("Email sending failed: " . $e->getMessage());
        }
        
        return false;
    }
}

// Generate random string
function generateRandomString($length = 10) {
    $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $charactersLength = strlen($characters);
    $randomString = '';
    
    for ($i = 0; $i < $length; $i++) {
        $randomString .= $characters[rand(0, $charactersLength - 1)];
    }
    
    return $randomString;
}

// Get month name
function getMonthName($month_number) {
    $months = [
        1 => 'January', 2 => 'February', 3 => 'March',
        4 => 'April', 5 => 'May', 6 => 'June',
        7 => 'July', 8 => 'August', 9 => 'September',
        10 => 'October', 11 => 'November', 12 => 'December'
    ];
    
    return $months[$month_number] ?? '';
}

// Calculate percentage
function calculatePercentage($value, $total) {
    if ($total == 0) {
        return 0;
    }
    return round(($value / $total) * 100, 2);
}

// Check if request is AJAX
function isAjaxRequest() {
    return !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
           strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';
}

// JSON response
function jsonResponse($data, $status_code = 200) {
    http_response_code($status_code);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit();
}

// Get file size in human readable format
function humanFileSize($bytes, $decimals = 2) {
    $size = ['B', 'KB', 'MB', 'GB', 'TB'];
    $factor = floor((strlen($bytes) - 1) / 3);
    return sprintf("%.{$decimals}f", $bytes / pow(1024, $factor)) . ' ' . $size[$factor];
}

// Debug function (only works in debug mode)
function debug($data) {
    if (DEBUG_MODE) {
        echo '<pre>';
        var_dump($data);
        echo '</pre>';
    }
}