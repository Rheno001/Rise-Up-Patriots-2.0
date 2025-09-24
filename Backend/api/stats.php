<?php
/**
 * Statistics API Endpoint
 * 
 * This endpoint provides registration statistics and analytics
 */

require_once __DIR__ . '/../config/cors.php';
require_once __DIR__ . '/../models/Registration.php';

// Set CORS headers
CorsHandler::setCorsHeaders();

// Only allow GET requests
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    CorsHandler::sendErrorResponse('Method not allowed. Only GET requests are accepted.', 405);
}

try {
    // Initialize registration model
    $registration = new Registration();
    
    // Get statistics
    $stats = $registration->getStatistics();
    
    // Add additional computed statistics
    $stats['response_time'] = microtime(true) - $_SERVER['REQUEST_TIME_FLOAT'];
    $stats['last_updated'] = date('Y-m-d H:i:s');
    
    // Send success response
    CorsHandler::sendSuccessResponse($stats, 'Statistics retrieved successfully');

} catch (Exception $e) {
    // Log the error
    error_log("Statistics error: " . $e->getMessage());
    
    // Send error response
    CorsHandler::sendErrorResponse('Failed to retrieve statistics', 500);
}
?>