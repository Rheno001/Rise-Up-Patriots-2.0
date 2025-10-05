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
require_once __DIR__ . '/../utils/EmailTemplate.php';
require_once __DIR__ . '/../vendor/autoload.php';

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
            $mail->isSMTP();                                           // Send using SMTP
            $mail->Host       = 'smtp.gmail.com';                    // Set the SMTP server to send through
            $mail->SMTPAuth   = true;                                  // Enable SMTP authentication
            $mail->Username   = 'unveilnigeria@gmail.com';              // SMTP username
            $mail->Password   = 'irmr josu xfbh znmc';                 // SMTP password
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;          // Use SSL/TLS encryption
            $mail->Port       = 465;
            //Recipients
            $mail->setFrom('unveilnigeria@gmail.com', 'Rise Up Patriots');
            $mail->addAddress($registration->email, $registration->first_name . ' ' . $registration->last_name);

            // Load and process email template with hosted images
            $emailTemplate = new EmailTemplate();
            $templateVariables = [
                'first_name' => $registration->first_name,
                'last_name' => $registration->last_name,
                'full_name' => $registration->first_name . ' ' . $registration->last_name
            ];
            
            $htmlBody = $emailTemplate->loadTemplateForEmail('registration_email', $templateVariables, 'hosted');

            // Content
            $mail->isHTML(true);
            $mail->Subject = 'Rise Up Patriots 2.0 - Registration Confirmation';
            $mail->Body = $htmlBody;
            
            // Add plain text alternative for email clients that don't support HTML
            $mail->AltBody = 'Dear ' . $registration->first_name . ',\n\n' .
                           'Thank you for registering for the Rise Up Patriots 2.0 event, organized by the Unveiling and Rebranding Nigeria Initiative (URNI). ' .
                           'We have received your details and your spot is confirmed. Further event information and updates will be shared with you shortly.\n\n' .
                           'Your participation means a lot. By joining us, you are standing with fellow patriots to showcase the brighter side of Nigeria and drive positive change.\n\n' .
                           '…Rediscovering Nigerians by Nigerians\n\n' .
                           'With appreciation\n' .
                           'Unveiling and Rebranding Nigeria Initiative';

            $mail->send();
            $email_status = 'Confirmation email sent successfully.';
        } catch (Exception $e) {
            error_log('PHPMailer Error: ' . $mail->ErrorInfo);
            $email_status = 'Confirmation email failed to send: ' . $e->getMessage();
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