<?php
/**
 * System Initialization File
 * This file should be included at the start of every PHP file
 */

// Include configuration files FIRST
require_once __DIR__ . '/constants.php';
require_once __DIR__ . '/database.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_name(SESSION_NAME ?? 'KIU_SESSION');
    session_start();
}

// Set error reporting based on environment
if (defined('DEBUG_MODE') && DEBUG_MODE) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
}

// Set timezone
date_default_timezone_set('Africa/Kampala');

// Include core classes
require_once dirname(__DIR__) . '/includes/functions.php';
require_once dirname(__DIR__) . '/includes/Auth.php';
require_once dirname(__DIR__) . '/includes/Session.php';
require_once dirname(__DIR__) . '/includes/Validator.php';
require_once dirname(__DIR__) . '/includes/FileUpload.php';
require_once dirname(__DIR__) . '/includes/Encryption.php';
require_once dirname(__DIR__) . '/includes/AuditLog.php';
require_once dirname(__DIR__) . '/includes/NotificationService.php';
require_once dirname(__DIR__) . '/includes/GreenCardService.php';

// Create upload directories if they don't exist
$upload_dirs = [
    UPLOAD_DIR,
    ADMISSION_LETTER_DIR,
    AWARD_LETTER_DIR,
    BANK_SLIP_DIR,
    ID_PHOTO_DIR,
    GREEN_CARD_DIR,
    QR_CODE_DIR,
    LOG_DIR
];

foreach ($upload_dirs as $dir) {
    if (!file_exists($dir)) {
        mkdir($dir, 0755, true);
    }
}

// Initialize database connection
try {
    $database = new Database();
    $db = $database->getConnection();
} catch (Exception $e) {
    error_log("Database initialization failed: " . $e->getMessage());
    die("System temporarily unavailable. Please try again later.");
}

// Set global error handler
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    error_log("Error: [$errno] $errstr in $errfile on line $errline");
    if (defined('DEBUG_MODE') && DEBUG_MODE) {
        echo "<b>Error:</b> [$errno] $errstr in $errfile on line $errline<br>";
    }
});

// Set exception handler
set_exception_handler(function($exception) {
    error_log("Uncaught Exception: " . $exception->getMessage());
    if (defined('DEBUG_MODE') && DEBUG_MODE) {
        echo "<b>Exception:</b> " . $exception->getMessage() . "<br>";
        echo "<pre>" . $exception->getTraceAsString() . "</pre>";
    } else {
        echo "An error occurred. Please contact the administrator.";
    }
});
