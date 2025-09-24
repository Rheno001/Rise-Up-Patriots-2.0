<?php
/**
 * Health Check Endpoint
 * 
 * This endpoint checks the health status of the API and database connection
 */

require_once __DIR__ . '/../config/cors.php';
require_once __DIR__ . '/../config/database.php';

// Set CORS headers
CorsHandler::setCorsHeaders();

try {
    // Check database connection
    $database = new Database();
    $conn = $database->getConnection();
    
    $health_status = [
        'api' => 'healthy',
        'database' => $conn ? 'connected' : 'disconnected',
        'timestamp' => date('Y-m-d H:i:s'),
        'server_time' => time(),
        'php_version' => PHP_VERSION,
        'memory_usage' => memory_get_usage(true),
        'memory_peak' => memory_get_peak_usage(true)
    ];

    // Test database query if connected
    if ($conn) {
        try {
            $stmt = $conn->query("SELECT 1");
            $health_status['database_query'] = 'working';
        } catch (Exception $e) {
            $health_status['database_query'] = 'failed';
            $health_status['database_error'] = $e->getMessage();
        }
    }

    $status_code = ($conn && $health_status['database_query'] === 'working') ? 200 : 503;
    
    CorsHandler::sendJsonResponse($health_status, $status_code);

} catch (Exception $e) {
    CorsHandler::sendErrorResponse('Health check failed: ' . $e->getMessage(), 503);
}
?>