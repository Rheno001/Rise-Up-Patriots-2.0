<?php
/**
 * API Index - Main API Entry Point
 * 
 * This file provides information about available API endpoints
 */

require_once __DIR__ . '/../config/cors.php';

// Set CORS headers
CorsHandler::setCorsHeaders();

// API Information
$api_info = [
    'name' => 'Rise-Up Patriots Registration API',
    'version' => '1.0.0',
    'description' => 'API for handling conference registrations',
    'endpoints' => [
        'POST /api/register.php' => 'Submit a new registration',
        'GET /api/stats.php' => 'Get registration statistics',
        'GET /api/registrations.php' => 'Get all registrations (admin)',
        'GET /api/health.php' => 'Check API health status'
    ],
    'status' => 'active',
    'timestamp' => date('Y-m-d H:i:s')
];

CorsHandler::sendSuccessResponse($api_info, 'API is running successfully');
?>