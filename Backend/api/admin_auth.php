<?php
/**
 * Admin Authentication API
 * 
 * Handles admin login, logout, and session management
 */

require_once __DIR__ . '/../config/cors.php';
require_once __DIR__ . '/../config/database.php';

// Set CORS headers
CorsHandler::setCorsHeaders();

// Configure session for localhost development
if (!headers_sent() && session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_samesite', 'Lax');
    ini_set('session.cookie_secure', '0');
    ini_set('session.cookie_httponly', '1');
    ini_set('session.cookie_domain', '');
}

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Handle preflight requests
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Only execute main logic if this file is called directly
if (basename($_SERVER['SCRIPT_NAME'] ?? '') === 'admin_auth.php') {
    // Get request method and action
    $method = $_SERVER['REQUEST_METHOD'] ?? '';
    $action = $_GET['action'] ?? '';

    try {
        $database = new Database();
        $conn = $database->getConnection();
        
        if (!$conn) {
            throw new Exception('Database connection failed');
        }

        switch ($method) {
            case 'POST':
                if ($action === 'login') {
                    handleLogin($conn);
                } elseif ($action === 'logout') {
                    handleLogout();
                } else {
                    CorsHandler::sendErrorResponse('Invalid action', 400);
                }
                break;
                
            case 'GET':
                if ($action === 'check') {
                    checkAuthStatus();
                } else {
                    CorsHandler::sendErrorResponse('Invalid action', 400);
                }
                break;
                
            default:
                CorsHandler::sendErrorResponse('Method not allowed', 405);
                break;
        }

    } catch (Exception $e) {
        error_log("Admin Auth API Error: " . $e->getMessage());
        CorsHandler::sendErrorResponse('Internal server error', 500);
    }
}

/**
 * Handle admin login
 */
function handleLogin($conn) {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input || !isset($input['username']) || !isset($input['password'])) {
        CorsHandler::sendErrorResponse('Username and password are required', 400);
        return;
    }
    
    $username = trim($input['username']);
    $password = $input['password'];
    
    if (empty($username) || empty($password)) {
        CorsHandler::sendErrorResponse('Username and password cannot be empty', 400);
        return;
    }
    
    try {
        // Find admin user by username or email
        $stmt = $conn->prepare("
            SELECT id, username, email, password_hash, full_name, role, is_active 
            FROM admin_users 
            WHERE (username = ? OR email = ?) AND is_active = TRUE
        ");
        $stmt->execute([$username, $username]);
        $admin = $stmt->fetch();
        
        if (!$admin) {
            // Log failed login attempt
            logAdminActivity($conn, 'login_failed', "Failed login attempt for username: $username", $_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_USER_AGENT'] ?? '');
            CorsHandler::sendErrorResponse('Invalid credentials', 401);
            return;
        }
        
        // Verify password
        if (!password_verify($password, $admin['password_hash'])) {
            // Log failed login attempt
            logAdminActivity($conn, 'login_failed', "Failed login attempt for username: $username", $_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_USER_AGENT'] ?? '');
            CorsHandler::sendErrorResponse('Invalid credentials', 401);
            return;
        }
        
        // Update last login time
        $stmt = $conn->prepare("UPDATE admin_users SET last_login = NOW() WHERE id = ?");
        $stmt->execute([$admin['id']]);
        
        // Create session
        $_SESSION['admin_id'] = $admin['id'];
        $_SESSION['admin_username'] = $admin['username'];
        $_SESSION['admin_email'] = $admin['email'];
        $_SESSION['admin_full_name'] = $admin['full_name'];
        $_SESSION['admin_role'] = $admin['role'];
        $_SESSION['admin_logged_in'] = true;
        $_SESSION['login_time'] = time();
        
        // Log successful login
        logAdminActivity($conn, 'login_success', "Successful login for username: $username", $_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_USER_AGENT'] ?? '');
        
        // Return success response
        CorsHandler::sendSuccessResponse([
            'admin' => [
                'id' => $admin['id'],
                'username' => $admin['username'],
                'email' => $admin['email'],
                'full_name' => $admin['full_name'],
                'role' => $admin['role']
            ],
            'session_id' => session_id()
        ], 'Login successful');
        
    } catch (Exception $e) {
        error_log("Login error: " . $e->getMessage());
        CorsHandler::sendErrorResponse('Login failed', 500);
    }
}

/**
 * Handle admin logout
 */
function handleLogout() {
    if (isset($_SESSION['admin_username'])) {
        $username = $_SESSION['admin_username'];
        
        // Log logout
        try {
            $database = new Database();
            $conn = $database->getConnection();
            if ($conn) {
                logAdminActivity($conn, 'logout', "Logout for username: $username", $_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_USER_AGENT'] ?? '');
            }
        } catch (Exception $e) {
            error_log("Logout logging error: " . $e->getMessage());
        }
    }
    
    // Destroy session
    session_destroy();
    
    CorsHandler::sendSuccessResponse([], 'Logout successful');
}

/**
 * Check authentication status
 */
function checkAuthStatus() {
    if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
        // Check session timeout (24 hours)
        $session_timeout = 24 * 60 * 60; // 24 hours in seconds
        if (isset($_SESSION['login_time']) && (time() - $_SESSION['login_time']) > $session_timeout) {
            session_destroy();
            CorsHandler::sendErrorResponse('Session expired', 401);
            return;
        }
        
        CorsHandler::sendSuccessResponse([
            'authenticated' => true,
            'admin' => [
                'id' => $_SESSION['admin_id'],
                'username' => $_SESSION['admin_username'],
                'email' => $_SESSION['admin_email'],
                'full_name' => $_SESSION['admin_full_name'],
                'role' => $_SESSION['admin_role']
            ]
        ], 'Authenticated');
    } else {
        CorsHandler::sendSuccessResponse([
            'authenticated' => false
        ], 'Not authenticated');
    }
}

/**
 * Log admin activity
 */
function logAdminActivity($conn, $action, $details, $ip_address, $user_agent) {
    try {
        $stmt = $conn->prepare("
            INSERT INTO admin_logs (action, details, ip_address, user_agent) 
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([$action, $details, $ip_address, $user_agent]);
    } catch (Exception $e) {
        error_log("Failed to log admin activity: " . $e->getMessage());
    }
}

/**
 * Check if user is authenticated (helper function for other endpoints)
 */
function requireAdminAuth() {
    if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
        CorsHandler::sendErrorResponse('Authentication required', 401);
        exit();
    }
    
    // Check session timeout
    $session_timeout = 24 * 60 * 60; // 24 hours
    if (isset($_SESSION['login_time']) && (time() - $_SESSION['login_time']) > $session_timeout) {
        session_destroy();
        CorsHandler::sendErrorResponse('Session expired', 401);
        exit();
    }
}
?>