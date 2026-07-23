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
            $mail->Host       = $_ENV['SMTP_HOST'] ?? getenv('SMTP_HOST') ?: 'smtp.gmail.com';
            $mail->SMTPAuth   = true;                                  // Enable SMTP authentication
            $mail->Username   = $_ENV['SMTP_USERNAME'] ?? getenv('SMTP_USERNAME') ?: '';
            $mail->Password   = $_ENV['SMTP_PASSWORD'] ?? getenv('SMTP_PASSWORD') ?: '';
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;        // Use STARTTLS encryption
            $mail->Port       = $_ENV['SMTP_PORT'] ?? getenv('SMTP_PORT') ?: 587;
            //Recipients
            $mail->setFrom(
                $_ENV['SMTP_FROM'] ?? getenv('SMTP_FROM') ?: 'unveilnigeria@gmail.com',
                $_ENV['SMTP_FROM_NAME'] ?? getenv('SMTP_FROM_NAME') ?: 'Rise Up Patriots'
            );
            $mail->addAddress($registration->email, $registration->first_name . ' ' . $registration->last_name);

            // Load and process email template using CID inline-image mode
            // (images are attached directly to the email — no external hosting needed)
            $emailTemplate = new EmailTemplate();
            $templateVariables = [
                'first_name' => $registration->first_name,
                'last_name' => $registration->last_name,
                'full_name' => $registration->first_name . ' ' . $registration->last_name
            ];
            
            $htmlBody = $emailTemplate->loadTemplateForEmail('registration_email', $templateVariables, 'cid');

            // Attach each image as an inline/embedded part via its Content-ID
            foreach ($emailTemplate->getCidImages() as $cid => $filePath) {
                $mail->addEmbeddedImage($filePath, $cid, basename($filePath));
            }

            // Content
            $mail->isHTML(true);
            $mail->Subject = 'Rise Up Patriots 3.0 - Registration Confirmation';
            $mail->Body = $htmlBody;
            
            // Add plain text alternative for email clients that don't support HTML
            $mail->AltBody =
                'Congratulations!' . "\n\n" .
                'Dear ' . $registration->first_name . ',' . "\n\n" .
                'Your registration for the URNI Rise Up Patriots Conference 3.0 has been successfully confirmed. We are excited to welcome you to this transformative gathering of citizens, leaders, innovators, and change-makers committed to building a better Nigeria.' . "\n\n" .
                'Theme: WHEN PATRIOTS RISE, NATIONS TRANSFORM' . "\n" .
                'Date: Saturday, 31st October 2026' . "\n" .
                'Time: 10:00 AM' . "\n" .
                "Venue: Shehu Musa Yar'Adua Center, FCT Abuja" . "\n\n" .
                'As Nigeria prepares for the 2027 elections, this conference will inspire meaningful conversations on patriotism, responsible citizenship, youth leadership, and nation-building.' . "\n\n" .
                'For virtual participants:' . "\n" .
                'If you registered to attend virtually, your access link and participation guide will be sent to your email 48 hours before the event. Kindly keep an eye on your inbox (and spam folder).' . "\n\n" .
                'Thank you for choosing to be part of this movement. We look forward to hosting you at Rise Up Patriots Conference 3.0.' . "\n\n" .
                'See you on October 31st!' . "\n\n" .
                'Warm regards,' . "\n" .
                'The URNI Team';

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