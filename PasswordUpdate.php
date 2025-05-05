<?php
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "cafe_db";

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

if (isset($_POST['username'], $_POST['authCode'], $_POST['newPassword'])) {
    $username = $_POST['username'];
    $authCode = $_POST['authCode'];
    $newPassword = password_hash($_POST['newPassword'], PASSWORD_DEFAULT);

    // Verify reset code
    $stmt = $conn->prepare("SELECT reset_code FROM users WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        
        if ($row['reset_code'] == $authCode) {
            // Update the password
            $stmt = $conn->prepare("UPDATE users SET password = ?, reset_code = NULL WHERE username = ?");
            $stmt->bind_param("ss", $newPassword, $username);
            if ($stmt->execute()) {
                echo json_encode(['success' => true]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to update password.']);
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'Invalid authentication code.']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'User not found.']);
    }

    $stmt->close();
}

$conn->close();
?>
