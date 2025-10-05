<?php
session_start();
require_once '../config/database.php';
require_once 'admin_auth.php';

// Set JSON header
header('Content-Type: application/json');

// Enable CORS if needed
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Access-Control-Allow-Credentials: true');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit();
}

try {
    // Check admin authentication
    requireAdminAuth();

    // Get JSON input
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        throw new Exception('Invalid JSON input');
    }

    // Validate required fields
    if (!isset($input['registration_id']) || !isset($input['status'])) {
        throw new Exception('Missing required fields: registration_id and status');
    }

    $registration_id = (int)$input['registration_id'];
    $status = trim($input['status']);

    // Validate status
    if (!in_array($status, ['present', 'absent', 'pending'])) {
        throw new Exception('Invalid status. Must be: present, absent, or pending');
    }

    // Validate registration_id
    if ($registration_id <= 0) {
        throw new Exception('Invalid registration ID');
    }

    // Get database connection
    $database = new Database();
    $conn = $database->getConnection();
    
    if (!$conn) {
        throw new Exception('Database connection failed');
    }

    // First, check if the registration exists
    $checkStmt = $conn->prepare("SELECT id, first_name, last_name FROM registrations WHERE id = ?");
    $checkStmt->execute([$registration_id]);
    $registration = $checkStmt->fetch(PDO::FETCH_ASSOC);

    if (!$registration) {
        throw new Exception('Registration not found');
    }

    // Update the venue attendance status
    $updateStmt = $conn->prepare("
        UPDATE registrations 
        SET venue_attendance_status = ?, 
            venue_attendance_updated_at = NOW() 
        WHERE id = ?
    ");

    $result = $updateStmt->execute([$status, $registration_id]);

    if (!$result) {
        throw new Exception('Failed to update attendance status');
    }

    // Log the admin activity
    $adminInfo = $_SESSION['admin_info'] ?? null;
    $adminName = $adminInfo ? $adminInfo['username'] : 'Unknown Admin';
    
    $activityDetails = "Updated venue attendance for {$registration['first_name']} {$registration['last_name']} (ID: {$registration_id}) to: {$status}";

    logAdminActivity(
        $conn,
        'attendance_update',
        $activityDetails,
        $_SERVER['REMOTE_ADDR'] ?? 'Unknown',
        $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown'
    );

    // Return success response
    echo json_encode([
        'success' => true,
        'message' => 'Attendance status updated successfully',
        'data' => [
            'registration_id' => $registration_id,
            'status' => $status,
            'updated_at' => date('Y-m-d H:i:s')
        ]
    ]);

} catch (Exception $e) {
    error_log("Attendance API Error: " . $e->getMessage() . " in " . $e->getFile() . " on line " . $e->getLine());
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Failed to update attendance status: ' . $e->getMessage()
    ]);
}
?>