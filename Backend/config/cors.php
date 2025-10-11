<?php
/**
 * CORS Configuration
 * 
 * Handles Cross-Origin Resource Sharing (CORS) settings
 * to allow the frontend to communicate with the backend API.
 */

class CorsHandler {
    
    /**
     * Set CORS headers for API requests
     */
    public static function setCorsHeaders() {
        // Detect current protocol and host
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $current_origin = "$protocol://$host";

        // Define allowed frontend origins
        $allowed_origins = [
            'http://localhost:8000',
            'http://127.0.0.1:8000',
            'http://localhost:3000',
            'http://localhost:8080',
            'http://localhost:5000',
            'http://localhost',
            'http://127.0.0.1',
            // ✅ Production domain
            'https://riseup.unveilingnigeria.ng',
            'https://www.riseup.unveilingnigeria.ng',
            // ✅ Also allow backend domain itself (same-origin)
            $current_origin,
        ];

        $origin = $_SERVER['HTTP_ORIGIN'] ?? $current_origin;

        // Match allowed origins dynamically
        if (in_array($origin, $allowed_origins, true)) {
            header("Access-Control-Allow-Origin: $origin");
        } else {
            // Fallback to same-origin policy
            header("Access-Control-Allow-Origin: $current_origin");
        }

        // Allow credentials and headers
        header("Access-Control-Allow-Credentials: true");
        header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
        header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
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
     */
    public static function sendJsonResponse($data, $status_code = 200) {
        http_response_code($status_code);
        self::setJsonHeaders();
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit();
    }

    /**
     * Send error response
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
