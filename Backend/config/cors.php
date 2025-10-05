<?php
/**
 * CORS Configuration
 * 
 * This file handles Cross-Origin Resource Sharing (CORS) settings
 * to allow the frontend to communicate with the backend API
 */

class CorsHandler {
    
    /**
     * Set CORS headers for API requests
     */
    public static function setCorsHeaders() {
        // Allow requests from the frontend domain
        $allowed_origins = [
            'http://localhost:8000',
            'http://127.0.0.1:8000',
            'http://localhost',
            'http://127.0.0.1',
            'http://localhost:3000',
            'http://localhost:8080',
            'http://localhost:5000',
            // Add your production domain here
            // 'https://yourdomain.com'
        ];

        $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
        
        if (in_array($origin, $allowed_origins)) {
            header("Access-Control-Allow-Origin: $origin");
        } else {
            // For development, default to localhost:8000 when no origin header
            header("Access-Control-Allow-Origin: http://localhost:8000");
        }

        // Allow specific HTTP methods
        header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
        
        // Allow specific headers
        header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
        
        // Allow credentials
        header("Access-Control-Allow-Credentials: true");
        
        // Set max age for preflight requests
        header("Access-Control-Max-Age: 86400"); // 24 hours

        // Handle preflight OPTIONS request
        if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
            http_response_code(200);
            exit();
        }
    }

    /**
     * Set JSON response headers
     */
    public static function setJsonHeaders() {
        header('Content-Type: application/json; charset=utf-8');
    }

    /**
     * Send JSON response
     * 
     * @param array $data Response data
     * @param int $status_code HTTP status code
     */
    public static function sendJsonResponse($data, $status_code = 200) {
        http_response_code($status_code);
        self::setJsonHeaders();
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit();
    }

    /**
     * Send error response
     * 
     * @param string $message Error message
     * @param int $status_code HTTP status code
     * @param array $details Additional error details
     */
    public static function sendErrorResponse($message, $status_code = 400, $details = []) {
        $response = [
            'success' => false,
            'error' => $message,
            'timestamp' => date('Y-m-d H:i:s')
        ];

        if (!empty($details)) {
            $response['details'] = $details;
        }

        self::sendJsonResponse($response, $status_code);
    }

    /**
     * Send success response
     * 
     * @param array $data Response data
     * @param string $message Success message
     */
    public static function sendSuccessResponse($data = [], $message = 'Success') {
        $response = [
            'success' => true,
            'message' => $message,
            'data' => $data,
            'timestamp' => date('Y-m-d H:i:s')
        ];

        self::sendJsonResponse($response, 200);
    }
}
?>