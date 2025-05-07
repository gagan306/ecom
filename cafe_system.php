<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: application/json'); 
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "cafe_db";

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed: ' . $conn->connect_error]);
    exit();
}

// Fetch tables based on the selected date
if (isset($_GET['action']) && $_GET['action'] === 'get_tables') {
    $date = $_GET['date']; // Get the selected date

    // Here you would typically check for reservations on the selected date
    // For simplicity, we will just fetch all tables without checking reservations
    $sql = "SELECT id, name, capacity FROM tables"; // Fetch all tables
    $result = $conn->query($sql);
    
    if ($result) {
        $tables = [];
        while ($row = $result->fetch_assoc()) {
            $tables[] = [
                'id' => $row['id'],
                'name' => $row['name'],
                'capacity' => (int)$row['capacity'] // Ensure capacity is an integer
            ];
        }
        echo json_encode(['success' => true, 'tables' => $tables]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error fetching tables: ' . $conn->error]);
    }
    exit();
}

// Close the database connection
$conn->close();
?>
<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: application/json'); // Ensure output is JSON

$servername = "localhost";
$username = "root";
$password = "";
$dbname = "cafe_db";

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed: ' . $conn->connect_error]);
    exit();
}

// Allowed time slots
$time_slots = [
    'morning' => ['08:00:00', '10:00:00'],
    'afternoon' => ['13:00:00', '15:00:00'],
    'night' => ['20:00:00', '22:00:00']
];

// Fetch tables for a specific date
if (isset($_GET['action']) && $_GET['action'] == 'get_tables') {
    $date = $_GET['date'];
    $tables = [];

    for ($i = 1; $i <= 10; $i++) {
        $tables[$i - 1]['id'] = $i; // Add the table ID
        $tables[$i - 1]['number'] = $i;
        $tables[$i - 1]['is_reserved'] = false;

        // Check if the table is reserved for the selected date
        $sql = "SELECT * FROM reservations WHERE table_number = ? AND DATE(time_from) = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("is", $i, $date);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $tables[$i - 1]['is_reserved'] = true;
        }
    }

    echo json_encode(['success' => true, 'tables' => $tables]); // Return success and tables
    exit();
}

// Fetch reservation details for a specific table
if (isset($_GET['action']) && $_GET['action'] == 'get_reservation') {
    $table_number = $_GET['table_number'];
    $date = $_GET['date'];

    $sql = "SELECT * FROM reservations WHERE table_number = ? AND DATE(time_from) = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("is", $table_number, $date);
    $stmt->execute();
    $result = $stmt->get_result();

    $reservations = [];
    while ($row = $result->fetch_assoc()) {
        $reservations[] = $row;
    }

    echo json_encode(['success' => true, 'reservations' => $reservations]); // Return success and reservations
    exit();
}

// Make a reservation
if (isset($_GET['action']) && $_GET['action'] == 'make_reservation' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents("php://input"), true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        echo json_encode(['success' => false, 'message' => 'Invalid JSON input.']);
        exit();
    }

    $name = $input['name'];
    $mobile = $input['mobile'];
    $table_number = $input['table_number']; // This should now be an array of table numbers
    $time_from = $input['time_from'];
    $time_to = $input['time_to'];
    
    // New functionality: Disallow reservations for past times
    if (strtotime($time_from) < time()) {
        echo json_encode(['success' => false, 'message' => 'Cannot make reservation for past times.']);
        exit();
    }

    // Validate if the reservation falls within a valid time slot
    $time_from_time = date('H:i:s', strtotime($time_from));
    $time_to_time = date('H:i:s', strtotime($time_to));

    $valid_slot = false;
    foreach ($time_slots as $slot) {
        if ($time_from_time >= $slot[0] && $time_to_time <= $slot[1]) {
            $valid_slot = true;
            break;
        }
    }

    if (!$valid_slot) {
        echo json_encode(['success' => false, 'message' => 'Reservation time must be within allowed slots: Morning (8-10 AM), Afternoon (1-3 PM), Night (8-10 PM).']);
        exit();
    }

    $table_numbers = explode(',', $table_number); // Split the table numbers if multiple
    foreach ($table_numbers as $table_num) {
        $sql = "SELECT * FROM reservations 
                WHERE table_number = ? 
                AND status IN ('pending', 'approved') 
                AND (
                    (time_from <= ? AND time_to > ?) OR 
                    (time_from < ? AND time_to >= ?)
                )";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("issss", $table_num, $time_from, $time_from, $time_to, $time_to);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            echo json_encode(['success' => false, 'message' => 'The selected time slot is already reserved.']);
            exit();
        }
    }

    // Insert reservations into the database for each selected table
    foreach ($table_numbers as $table_num) {
        $sql = "INSERT INTO reservations (name, mobile, table_number, time_from, time_to) VALUES (?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssiss", $name, $mobile, $table_num, $time_from, $time_to);
        if (!$stmt->execute()) {
            echo json_encode(['success' => false, 'message' => 'Error making reservation for table ' . $table_num . ': ' . $stmt->error]);
            exit();
        }
    }

    echo json_encode(['success' => true, 'message' => 'Reservation successful']);
    exit();
}

echo json_encode(['success' => false, 'message' => 'Invalid action or method.']);

$conn->close();
?>
