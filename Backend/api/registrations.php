<?php
/**
 * Admin Registrations API
 * 
 * Provides admin access to view and manage registrations
 */

// Configure session for localhost development BEFORE any output
if (!headers_sent()) {
    ini_set('session.cookie_samesite', 'Lax');
    ini_set('session.cookie_secure', '0');
    ini_set('session.cookie_httponly', '1');
    ini_set('session.cookie_domain', '');
}

// Start session BEFORE any output
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../config/cors.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/admin_auth.php';

// Set CORS headers
CorsHandler::setCorsHeaders();

// Handle preflight requests
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Log request for debugging
$method = $_SERVER['REQUEST_METHOD'] ?? 'UNKNOWN';
$query = $_SERVER['QUERY_STRING'] ?? '';
error_log("Registrations API called - Method: $method, Query: $query");

// Require admin authentication
requireAdminAuth();

// Get request method
$method = $_SERVER['REQUEST_METHOD'] ?? '';

try {
    $database = new Database();
    $conn = $database->getConnection();
    
    if (!$conn) {
        throw new Exception('Database connection failed');
    }

    switch ($method) {
        case 'GET':
            if (isset($_GET['export']) && $_GET['export'] === 'csv') {
                handleExportCSV($conn);
            } else {
                handleGetRegistrations($conn);
            }
            break;
            
        case 'DELETE':
            handleDeleteRegistration($conn);
            break;
            
        default:
            CorsHandler::sendErrorResponse('Method not allowed', 405);
            break;
    }

} catch (Exception $e) {
    error_log("Registrations API Error: " . $e->getMessage());
    CorsHandler::sendErrorResponse('Internal server error', 500);
}

/**
 * Handle getting registrations with filtering and pagination
 */
function handleGetRegistrations($conn) {
    try {
        // Get query parameters
        $page = max(1, intval($_GET['page'] ?? 1));
        $limit = min(100, max(10, intval($_GET['limit'] ?? 50)));
        $offset = ($page - 1) * $limit;
        
        $search = $_GET['search'] ?? '';
        $status = $_GET['status'] ?? '';
        $country = $_GET['country'] ?? '';
        $attendance_type = $_GET['attendance_type'] ?? '';
        $date_from = $_GET['date_from'] ?? '';
        $date_to = $_GET['date_to'] ?? '';
        
        // Build WHERE clause
        $where_conditions = [];
        $params = [];
        
        if (!empty($search)) {
            $where_conditions[] = "(first_name LIKE ? OR last_name LIKE ? OR email LIKE ? OR phone LIKE ?)";
            $search_param = "%$search%";
            $params = array_merge($params, [$search_param, $search_param, $search_param, $search_param]);
        }
        
        if (!empty($status)) {
            $where_conditions[] = "status = ?";
            $params[] = $status;
        }
        
        if (!empty($country)) {
            $where_conditions[] = "country_code = ?";
            $params[] = $country;
        }
        
        if (!empty($attendance_type)) {
            $where_conditions[] = "attendance_type = ?";
            $params[] = $attendance_type;
        }
        
        if (!empty($date_from)) {
            $where_conditions[] = "DATE(registration_date) >= ?";
            $params[] = $date_from;
        }
        
        if (!empty($date_to)) {
            $where_conditions[] = "DATE(registration_date) <= ?";
            $params[] = $date_to;
        }
        
        $where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';
        
        // Get total count
        $count_sql = "SELECT COUNT(*) FROM registrations $where_clause";
        $count_stmt = $conn->prepare($count_sql);
        $count_stmt->execute($params);
        $total_count = $count_stmt->fetchColumn();
        
        // Get registrations
        $sql = "SELECT * FROM registrations $where_clause ORDER BY registration_date DESC LIMIT $limit OFFSET $offset";
        $stmt = $conn->prepare($sql);
        $stmt->execute($params);
        $registrations = $stmt->fetchAll();
        
        // Get summary statistics
        $stats_sql = "SELECT 
            COUNT(*) as total,
            COUNT(CASE WHEN status = 'active' THEN 1 END) as active,
            COUNT(CASE WHEN status = 'cancelled' THEN 1 END) as cancelled,
            COUNT(CASE WHEN attendance_type = 'Physical' THEN 1 END) as physical,
            COUNT(CASE WHEN attendance_type = 'Virtual' THEN 1 END) as `virtual`,
            COUNT(CASE WHEN DATE(registration_date) = CURDATE() THEN 1 END) as today,
            COUNT(CASE WHEN DATE(registration_date) >= DATE_SUB(CURDATE(), INTERVAL 7 DAY) THEN 1 END) as this_week
            FROM registrations";
        $stats_stmt = $conn->prepare($stats_sql);
        $stats_stmt->execute();
        $stats = $stats_stmt->fetch();
        
        // Log admin activity
        logAdminActivity($conn, 'view_registrations', "Viewed registrations page $page", $_SERVER['REMOTE_ADDR'] ?? '', $_SERVER['HTTP_USER_AGENT'] ?? '');
        
        CorsHandler::sendSuccessResponse([
            'registrations' => $registrations,
            'pagination' => [
                'current_page' => $page,
                'per_page' => $limit,
                'total' => $total_count,
                'total_pages' => ceil($total_count / $limit)
            ],
            'statistics' => $stats,
            'filters' => [
                'search' => $search,
                'status' => $status,
                'country' => $country,
                'attendance_type' => $attendance_type,
                'date_from' => $date_from,
                'date_to' => $date_to
            ]
        ], 'Registrations retrieved successfully');
        
    } catch (Exception $e) {
        $error_message = "Error retrieving registrations: " . $e->getMessage() . " | File: " . $e->getFile() . " | Line: " . $e->getLine();
        error_log($error_message);
        logAdminActivity($conn, 'error', 'Failed to retrieve registrations: ' . $e->getMessage(), $_SERVER['REMOTE_ADDR'] ?? '', $_SERVER['HTTP_USER_AGENT'] ?? '');
        CorsHandler::sendErrorResponse('Failed to retrieve registrations: ' . $e->getMessage(), 500);
    }
}

/**
 * Handle deleting a registration
 */
function handleDeleteRegistration($conn) {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input || !isset($input['id'])) {
        CorsHandler::sendErrorResponse('Registration ID is required', 400);
        return;
    }
    
    $registration_id = intval($input['id']);
    
    try {
        // Get registration details before deletion for logging
        $stmt = $conn->prepare("SELECT email, first_name, last_name FROM registrations WHERE id = ?");
        $stmt->execute([$registration_id]);
        $registration = $stmt->fetch();
        
        if (!$registration) {
            CorsHandler::sendErrorResponse('Registration not found', 404);
            return;
        }
        
        // Delete registration
        $stmt = $conn->prepare("DELETE FROM registrations WHERE id = ?");
        $stmt->execute([$registration_id]);
        
        if ($stmt->rowCount() > 0) {
            // Log admin activity
            $details = "Deleted registration for {$registration['first_name']} {$registration['last_name']} ({$registration['email']})";
            logAdminActivity($conn, 'delete_registration', $details, $_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_USER_AGENT'] ?? '');
            
            CorsHandler::sendSuccessResponse([], 'Registration deleted successfully');
        } else {
            CorsHandler::sendErrorResponse('Failed to delete registration', 500);
        }
        
    } catch (Exception $e) {
        error_log("Delete registration error: " . $e->getMessage());
        CorsHandler::sendErrorResponse('Failed to delete registration', 500);
    }
}

function handleExportCSV($conn) {
    try {
        // Avoid timeouts for large exports
        if (function_exists('set_time_limit')) { @set_time_limit(0); }
        
        // Allowed and default fields for export (safeguard against SQL injection)
        $allowedFields = [
            'id', 'title', 'gender', 'first_name', 'last_name', 'email', 'phone', 'age_range',
            'attendance_type', 'country_name', 'state_of_origin', 'how_did_you_hear', 'registration_date', 'status'
        ];

        $fieldsParam = isset($_GET['fields']) ? $_GET['fields'] : '';
        $requested = array_filter(array_map('trim', explode(',', $fieldsParam)));
        $selectedFields = array_values(array_intersect($allowedFields, $requested));
        if (empty($selectedFields)) {
            $selectedFields = $allowedFields;
        }

        // Filters (mirror GET endpoint behavior)
        $search = isset($_GET['search']) ? trim($_GET['search']) : '';
        $status = isset($_GET['status']) ? trim($_GET['status']) : '';
        $country = isset($_GET['country']) ? trim($_GET['country']) : '';
        $attendanceType = isset($_GET['attendance_type']) ? trim($_GET['attendance_type']) : '';
        $dateFrom = isset($_GET['date_from']) ? trim($_GET['date_from']) : '';
        $dateTo = isset($_GET['date_to']) ? trim($_GET['date_to']) : '';

        $whereClauses = [];
        $params = [];

        if ($search !== '') {
            $whereClauses[] = "(first_name LIKE ? OR last_name LIKE ? OR email LIKE ? OR phone LIKE ?)";
            $searchLike = "%$search%";
            array_push($params, $searchLike, $searchLike, $searchLike, $searchLike);
        }
        if ($status !== '') {
            $whereClauses[] = "status = ?";
            $params[] = $status;
        }
        if ($country !== '') {
            // Assuming country_code exists; if country_name is used, adjust accordingly
            $whereClauses[] = "country_code = ?";
            $params[] = $country;
        }
        if ($attendanceType !== '') {
            $whereClauses[] = "attendance_type = ?";
            $params[] = $attendanceType;
        }
        if ($dateFrom !== '') {
            $whereClauses[] = "DATE(registration_date) >= ?";
            $params[] = $dateFrom;
        }
        if ($dateTo !== '') {
            $whereClauses[] = "DATE(registration_date) <= ?";
            $params[] = $dateTo;
        }

        $whereSql = !empty($whereClauses) ? ('WHERE ' . implode(' AND ', $whereClauses)) : '';

        // Prepare CSV streaming response
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="registrations.csv"');
        // Allow download in browsers while authenticated
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Pragma: no-cache');

        $out = fopen('php://output', 'w');
        if ($out === false) {
            CorsHandler::sendErrorResponse('Failed to open output stream', 500);
            return;
        }

        // Write header row
        fputcsv($out, $selectedFields);

        // Build and execute streaming query
        $sql = 'SELECT ' . implode(', ', $selectedFields) . ' FROM registrations ' . $whereSql . ' ORDER BY registration_date DESC';
        $stmt = $conn->prepare($sql);
        $stmt->execute($params);

        // Stream rows out to CSV
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $line = [];
            foreach ($selectedFields as $f) {
                $line[] = isset($row[$f]) ? $row[$f] : '';
            }
            fputcsv($out, $line);
        }

        // Optional: log admin export action if such a utility exists
        if (function_exists('logAdminActivity')) {
            $ip = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '';
            $ua = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '';
            @logAdminActivity($conn, 'export_registrations_csv', 'Exported registrations CSV', $ip, $ua);
        }

        fclose($out);
        exit();
    } catch (Throwable $e) {
        error_log('CSV Export Error: ' . $e->getMessage());
        CorsHandler::sendErrorResponse('Failed to export registrations', 500);
    }
}

?>