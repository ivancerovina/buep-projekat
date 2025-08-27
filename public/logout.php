<?php
define('APP_RUNNING', true);
require_once '../config/config.php';

// Start session
Auth::startSecureSession();

// Log out the user
Auth::logout();

// Set success message
setAlert('You have been successfully logged out.', 'success');

// Redirect to login page
redirect('/buep-projekat/public/login.php');