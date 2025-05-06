<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require __DIR__ . '/vendor/autoload.php';
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

try {
    // Get POST data
    $jsonInput = file_get_contents('php://input');
    if (!$jsonInput) {
        throw new Exception('No input data received');
    }

    $data = json_decode($jsonInput, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('Invalid JSON data received');
    }

    if (!isset($data['username']) || !isset($data['reservationDetails'])) {
        throw new Exception('Missing required data');
    }

    // Database connection
    $conn = new mysqli("localhost", "root", "", "cafe_db");
    if ($conn->connect_error) {
        throw new Exception("Connection failed: " . $conn->connect_error);
    }

    // Get user's email from database
    $stmt = $conn->prepare("SELECT email FROM users WHERE username = ?");
    if (!$stmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }

    $stmt->bind_param("s", $data['username']);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();

    if (!$user) {
        throw new Exception('User not found in database');
    }

    // Create new PHPMailer instance
    $mail = new PHPMailer(true);

    // Server settings
    $mail->SMTPDebug = 2;
    $mail->Debugoutput = function($str, $level) {
        error_log("SMTP DEBUG: $str");
    };
    $mail->isSMTP();
    $mail->Host = 'smtp.gmail.com';
    $mail->SMTPAuth = true;
    $mail->Username = '';
    $mail->Password = '';
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
    $mail->Port = 465;

    // Recipients
    $mail->setFrom('thebatas2024@gmail.com', 'BATAS Cafe');
    $mail->addAddress($user['email']);

    // Format the date and time
    $timeFrom = new DateTime($data['reservationDetails']['time_from']);
    $timeTo = new DateTime($data['reservationDetails']['time_to']);

    // Content
    $mail->isHTML(true);
    $mail->Subject = 'BATAS Cafe - Reservation Confirmation';
    
    // Email template
    $emailBody = "
    <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px; background-color: #f9f9f9;'>
        <div style='background-color: #13131a; padding: 20px; text-align: center; border-radius: 5px;'>
            <h1 style='color: #d3ad7f; margin: 0;'>BATAS Cafe</h1>
        </div>
        
        <div style='background-color: white; padding: 20px; margin-top: 20px; border-radius: 5px; box-shadow: 0 2px 5px rgba(0,0,0,0.1);'>
            <h2 style='color: #13131a; margin-top: 0;'>Reservation Confirmation</h2>
            
            <p style='color: #666;'>Dear {$data['reservationDetails']['name']},</p>
            
            <p style='color: #666;'>Your table reservation has been confirmed. Here are your reservation details:</p>
            
            <div style='background-color: #f5f5f5; padding: 15px; border-radius: 5px; margin: 20px 0;'>
                <p style='margin: 5px 0;'><strong>Table Number:</strong> {$data['reservationDetails']['table_number']}</p>
                <p style='margin: 5px 0;'><strong>Date:</strong> {$timeFrom->format('F j, Y')}</p>
                <p style='margin: 5px 0;'><strong>Time:</strong> {$timeFrom->format('g:i A')} - {$timeTo->format('g:i A')}</p>
                <p style='margin: 5px 0;'><strong>Contact Number:</strong> {$data['reservationDetails']['mobile']}</p>
            </div>
            
            <p style='color: #666;'>Please arrive 5 minutes before your reservation time. If you need to cancel or modify your reservation, please contact us.</p>
            
            <p style='color: #666;'>We look forward to serving you!</p>
        </div>
        
        <div style='text-align: center; margin-top: 20px; color: #666; font-size: 12px;'>
            <p>BATAS Cafe | Babarmahal, Kathmandu</p>
            <p>Phone: +977-9841234567 | Email: thebatas2024@gmail.com</p>
        </div>
    </div>
    ";

    $mail->Body = $emailBody;
    $mail->AltBody = strip_tags(str_replace(['<br>', '</p>'], ["\n", "\n\n"], $emailBody));

    $mail->send();
    
    echo json_encode(['success' => true, 'message' => 'Email sent successfully']);

} catch (Exception $e) {
    error_log($e->getMessage());
    echo json_encode([
        'success' => false, 
        'message' => "Email could not be sent. Error: " . $e->getMessage()
    ]);
}

if (isset($conn)) {
    $conn->close();
}
?> 