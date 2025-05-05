<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require __DIR__ . '/vendor/autoload.php'; // Updated path
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$servername = "localhost";
$username = "root";
$password = "";
$dbname = "cafe_db";

$conn = new mysqli($servername, $username, $password, $dbname);

// Check database connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Handle password reset request
if (isset($_POST['requestReset'])) {
    $username = $_POST['username'];

    // Retrieve email based on username
    $stmt = $conn->prepare("SELECT email FROM users WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();
        $email = $user['email'];
        $resetCode = rand(100000, 999999); // Generate a reset code

        // Update the database with the reset code
        $stmt = $conn->prepare("UPDATE users SET reset_code = ? WHERE username = ?");
        $stmt->bind_param("is", $resetCode, $username);
        $stmt->execute();

        // Send the email
        $mail = new PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host = 'smtp.gmail.com'; // Your SMTP server
            $mail->SMTPAuth = true;
            $mail->Username = 'thebatas2024@gmail.com'; // Your SMTP username
            $mail->Password = 'zmut czzb mdxo fntn'; // Your SMTP password
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = 587;

            $mail->setFrom('thebatas2024@gmail.com', 'The Batas');
            $mail->addAddress($email);
            $mail->isHTML(true);
            $mail->Subject = 'Password Reset Code';
            $mail->Body = "Your password reset code is: <b>$resetCode</b>";

            $mail->send();
            echo json_encode(['success' => true, 'message' => 'Reset code sent to your email.']);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Error sending email: ' . $mail->ErrorInfo]);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'User not found']);
    }

    $stmt->close();
    exit;
}

// Handle reset password submission
if (isset($_POST['resetCode']) && isset($_POST['newPassword'])) {
    $resetCode = $_POST['resetCode'];
    $newPassword = $_POST['newPassword'];

    // Verify reset code and update password
    $stmt = $conn->prepare("SELECT username FROM users WHERE reset_code = ?");
    $stmt->bind_param("i", $resetCode);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();
        $username = $user['username'];

        // Hash the new password
        $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);

        // Update the user's password
        $stmt = $conn->prepare("UPDATE users SET password = ?, reset_code = NULL WHERE username = ?");
        $stmt->bind_param("ss", $hashedPassword, $username);
        $stmt->execute();

        echo json_encode(['success' => true, 'message' => 'Password reset successful!']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid reset code']);
    }

    $stmt->close();
    exit;
}

$conn->close();
