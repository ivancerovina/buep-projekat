<?php
define('APP_RUNNING', true);
require_once '../config/config.php';

// Set security headers
setSecurityHeaders();

// Start session
Auth::startSecureSession();

// Check if user is logged in
if (Auth::isLoggedIn()) {
    // Redirect based on role
    switch ($_SESSION['role']) {
        case 'admin':
            redirect('/buep-projekat/public/admin/dashboard.php');
            break;
        case 'manager':
            redirect('/buep-projekat/public/manager/dashboard.php');
            break;
        default:
            redirect('/buep-projekat/public/dashboard.php');
    }
} else {
    // Redirect to login page
    redirect('/buep-projekat/public/login.php');
}