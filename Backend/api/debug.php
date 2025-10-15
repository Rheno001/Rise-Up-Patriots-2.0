<?php
/**
 * Debug Script for Registration API
 * 
 * This script helps identify issues with the registration endpoint
 */

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);

// Include required files
require_once __DIR__ . '/../config/cors.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../models/Registration.php';
require_once __DIR__ . '/../utils/EmailTemplate.php';

// Set CORS headers
CorsHandler::setCorsHeaders();

$debug_info = [];

try {
    // Test 1: Database connection
    $debug_info['database_test'] = 'Starting database test...';
    $database = new Database();
    $conn = $database->getConnection();
    
    if ($conn) {
        $debug_info['database_connection'] = 'SUCCESS';
        
        // Test database initialization
        if ($database->initializeDatabase()) {
            $debug_info['database_initialization'] = 'SUCCESS';
        } else {
            $debug_info['database_initialization'] = 'FAILED';
        }
    } else {
        $debug_info['database_connection'] = 'FAILED';
    }
    
} catch (Exception $e) {
    $debug_info['database_error'] = $e->getMessage();
}

try {
    // Test 2: Registration model
    $debug_info['registration_test'] = 'Starting registration model test...';
    $registration = new Registration();
    $debug_info['registration_model'] = 'SUCCESS';
    
} catch (Exception $e) {
    $debug_info['registration_error'] = $e->getMessage();
}

try {
    // Test 3: Email template
    $debug_info['email_template_test'] = 'Starting email template test...';
    $emailTemplate = new EmailTemplate();
    $debug_info['email_template_model'] = 'SUCCESS';
    
    // Test template loading
    $templateVariables = [
        'first_name' => 'Test',
        'last_name' => 'User',
        'full_name' => 'Test User'
    ];
    
    $htmlBody = $emailTemplate->loadTemplateForEmail('registration_email', $templateVariables, 'hosted');
    $debug_info['email_template_loading'] = 'SUCCESS';
    
} catch (Exception $e) {
    $debug_info['email_template_error'] = $e->getMessage();
}

// Test 4: Environment variables
$debug_info['environment_variables'] = [
    'DB_HOST' => $_ENV['DB_HOST'] ?? 'NOT SET',
    'DB_NAME' => $_ENV['DB_NAME'] ?? 'NOT SET',
    'DB_USERNAME' => $_ENV['DB_USERNAME'] ?? 'NOT SET',
    'DB_PASSWORD' => isset($_ENV['DB_PASSWORD']) ? 'SET' : 'NOT SET',
    'SMTP_HOST' => $_ENV['SMTP_HOST'] ?? 'NOT SET',
    'SMTP_USERNAME' => $_ENV['SMTP_USERNAME'] ?? 'NOT SET',
    'SMTP_PASSWORD' => isset($_ENV['SMTP_PASSWORD']) ? 'SET' : 'NOT SET',
    'APP_URL' => $_ENV['APP_URL'] ?? 'NOT SET'
];

// Test 5: File permissions and paths
$debug_info['file_paths'] = [
    'template_path' => __DIR__ . '/../templates/registration_email.html',
    'template_exists' => file_exists(__DIR__ . '/../templates/registration_email.html') ? 'YES' : 'NO',
    'env_file_path' => __DIR__ . '/../../.env',
    'env_file_exists' => file_exists(__DIR__ . '/../../.env') ? 'YES' : 'NO'
];

// Test 6: PHP configuration
$debug_info['php_config'] = [
    'php_version' => phpversion(),
    'pdo_mysql' => extension_loaded('pdo_mysql') ? 'LOADED' : 'NOT LOADED',
    'openssl' => extension_loaded('openssl') ? 'LOADED' : 'NOT LOADED',
    'curl' => extension_loaded('curl') ? 'LOADED' : 'NOT LOADED',
    'memory_limit' => ini_get('memory_limit'),
    'max_execution_time' => ini_get('max_execution_time')
];

// Send response
header('Content-Type: application/json');
echo json_encode([
    'status' => 'debug_complete',
    'timestamp' => date('Y-m-d H:i:s'),
    'debug_info' => $debug_info
], JSON_PRETTY_PRINT);
?>