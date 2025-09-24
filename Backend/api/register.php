<?php
/**
 * Registration API Endpoint
 * 
 * This endpoint handles user registration form submissions
 * Accepts POST requests with registration data
 */

// Include required files
require_once __DIR__ . '/../config/cors.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../models/Registration.php';
require_once __DIR__ . '/../vendor/autoload.php';

// Load environment variables
use Dotenv\Dotenv;
$dotenv = Dotenv::createImmutable(__DIR__ . '/../../');
$dotenv->load();

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Set CORS headers
CorsHandler::setCorsHeaders();

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    CorsHandler::sendErrorResponse('Method not allowed. Only POST requests are accepted.', 405);
}

try {
    // Get JSON input
    $json_input = file_get_contents('php://input');
    $data = json_decode($json_input, true);

    // If JSON is empty, try to get form data
    if (empty($data)) {
        $data = $_POST;
    }

    // Log the received data for debugging (remove in production)
    error_log("Registration data received: " . json_encode($data));

    // Validate input data
    if (empty($data)) {
        CorsHandler::sendErrorResponse('No data received', 400);
    }

    // Initialize registration model
    $registration = new Registration();

    // Validate the data
    $validation_errors = $registration->validate($data);
    if (!empty($validation_errors)) {
        CorsHandler::sendErrorResponse('Validation failed', 400, $validation_errors);
    }

    // Map form data to registration properties
    $registration->title = $data['title'];
    $registration->gender = $data['gender'];
    $registration->first_name = $data['firstName'];
    $registration->last_name = $data['lastName'];
    $registration->phone = $data['phone'];
    $registration->email = $data['email'];
    $registration->age_range = $data['ageRange'];
    $registration->attendance_type = $data['attendanceType'];
    $registration->country_code = $data['country'];
    $registration->country_name = $data['countryName'] ?? '';
    $registration->state_of_origin = $data['stateOfOrigin'];
    $registration->how_did_you_hear = $data['howDidYouHear'];

    // Initialize database if needed
    $database = new Database();
    if (!$database->initializeDatabase()) {
        CorsHandler::sendErrorResponse('Database initialization failed', 500);
    }

    // Create the registration
    if ($registration->create()) {
        // Log successful registration
        $log_data = [
            'action' => 'registration_created',
            'registration_id' => $registration->id,
            'email' => $registration->email,
            'country' => $registration->country_name,
            'attendance_type' => $registration->attendance_type
        ];
        logActivity('registration_created', json_encode($log_data));

        // Send confirmation email
        $mail = new PHPMailer(true);
        try {
            //Server settings
            $mail->isSMTP();
            $mail->Host       = $_ENV['SMTP_HOST'];
            $mail->SMTPAuth   = true;
            $mail->Username   = $_ENV['SMTP_USERNAME'];
            $mail->Password   = $_ENV['SMTP_PASSWORD'];
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
            $mail->Port       = $_ENV['SMTP_PORT'];
            //Recipients
            $mail->setFrom($_ENV['SMTP_FROM'], $_ENV['SMTP_FROM_NAME']);
            $mail->addAddress($registration->email, $registration->first_name . ' ' . $registration->last_name);

            // Content
            $mail->isHTML(true);
            $mail->Subject = 'Registration Confirmation';
            $mail->Body    = 'Dear ' . htmlspecialchars($registration->first_name) . ',<br><br>Thank you for registering for the Rise Up Patriots Conference.<br>Your registration was successful!<br><br>Best regards,<br>Rise Up Patriots Team';

            $mail->send();
            $email_status = 'Confirmation email sent.';
        } catch (Exception $e) {
            error_log('PHPMailer Error: ' . $mail->ErrorInfo);
            $email_status = 'Confirmation email failed to send.';
        }

        // Send success response
        CorsHandler::sendSuccessResponse([
            'registration_id' => $registration->id,
            'email' => $registration->email,
            'full_name' => $registration->first_name . ' ' . $registration->last_name,
            'attendance_type' => $registration->attendance_type,
            'country' => $registration->country_name,
            'email_status' => $email_status
        ], 'Registration completed successfully!');
    } else {
        CorsHandler::sendErrorResponse('Failed to create registration. Please try again.', 500);
    }

} catch (Exception $e) {
    // Log the error
    error_log("Registration error: " . $e->getMessage());
    
    // Send error response
    CorsHandler::sendErrorResponse('An unexpected error occurred. Please try again later.', 500);
}

/**
 * Log activity to admin_logs table
 * 
 * @param string $action Action performed
 * @param string $details Action details
 */
function logActivity($action, $details = '') {
    try {
        $database = new Database();
        $conn = $database->getConnection();
        
        if ($conn) {
            $query = "INSERT INTO admin_logs (action, details, ip_address, user_agent) 
                      VALUES (:action, :details, :ip_address, :user_agent)";
            
            $stmt = $conn->prepare($query);
            $ip_address = $_SERVER['REMOTE_ADDR'] ?? '';
            $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
            
            $stmt->bindParam(':action', $action);
            $stmt->bindParam(':details', $details);
            $stmt->bindParam(':ip_address', $ip_address);
            $stmt->bindParam(':user_agent', $user_agent);
            
            $stmt->execute();
        }
    } catch (Exception $e) {
        error_log("Logging error: " . $e->getMessage());
    }
}
?>