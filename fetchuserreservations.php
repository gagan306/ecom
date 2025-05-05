<?php
// Enable error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Database connection parameters
$host = 'localhost'; // Change if necessary
$dbname = 'cafe_db'; // Replace with your database name
$username = 'root'; // Replace with your database username
$password = ''; // Replace with your database password

try {
    // Create a new PDO instance
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Start session to access session variables
    session_start();
    
    // Check if the username is set in the session
    if (!isset($_SESSION['username'])) {
        throw new Exception('User not logged in.');
    }
    
    $username = $_SESSION['username']; // Get the username from session

    // Prepare and execute the SQL query to fetch reservations directly
    $stmt = $pdo->prepare("SELECT  table_name, time_from, time_to, table_number, name ,status
                            FROM reservations 
                            WHERE SUBSTRING_INDEX(name, '(', 1) = :username"); // Use substring to filter by name
    $stmt->execute(['username' => trim($username)]); // Execute with the username

    // Fetch all reservations
    $reservations = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Organize reservations by time of day
    $organizedReservations = [
        'morning' => [],
        'afternoon' => [],
        'night' => []
    ];

    foreach ($reservations as $reservation) {
        $timeFrom = new DateTime($reservation['time_from']);
        $timeTo = new DateTime($reservation['time_to']);

        // Determine the time of day
        if ($timeFrom->format('H') < 12) {
            $organizedReservations['morning'][] = $reservation;
        } elseif ($timeFrom->format('H') < 18) {
            $organizedReservations['afternoon'][] = $reservation;
        } else {
            $organizedReservations['night'][] = $reservation;
        }
    }

    // Prepare the response
    $response = [
        'success' => true,
        'reservations' => $organizedReservations
    ];

} catch (PDOException $e) {
    // Handle any database errors
    $response = [
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ];
} catch (Exception $e) {
    // Handle other errors
    $response = [
        'success' => false,
        'message' => $e->getMessage()
    ];
}

// Set the content type to JSON and output the response
header('Content-Type: application/json');
echo json_encode($response);
?>