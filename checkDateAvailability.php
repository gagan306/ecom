<?php
// Database connection
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "cafe_db";

$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed: ' . $conn->connect_error]);
    exit;
}

// Get the posted data
$data = json_decode(file_get_contents("php://input"), true);
$date = isset($data['date']) ? $data['date'] : null;

if (!$date) {
    echo json_encode(['success' => false, 'message' => 'No date provided.']);
    exit;
}

// Query to check if the date exists in the reservation_date table
$query = "SELECT COUNT(*) AS count FROM reservation_dates WHERE reservation_date = ?";
$stmt = $conn->prepare($query);

if ($stmt) {
    $stmt->bind_param("s", $date);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();

    if ($row['count'] > 0) {
        echo json_encode(['success' => true, 'message' => 'Date is available.']);
    } else {
        echo json_encode(['success' => false, 'message' => 'The selected date is not available for reservations.']);
    }

    $stmt->close();
} else {
    echo json_encode(['success' => false, 'message' => 'Error preparing query: ' . $conn->error]);
}

$conn->close();
?>
