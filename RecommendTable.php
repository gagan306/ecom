<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json');
ob_clean();

// Database connection
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "cafe_db";

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed: ' . $conn->connect_error]);
    exit();
}

$data = json_decode(file_get_contents('php://input'), true);

error_log('Received Guest Count: ' . $data['guestCount']);
error_log('Received Reservation Date: ' . $data['reservationDate']);

if (!isset($data['guestCount']) || !is_numeric($data['guestCount']) || $data['guestCount'] < 1) {
    echo json_encode(['success' => false, 'message' => 'Invalid guest count']);
    exit();
}

if (!isset($data['reservationDate']) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $data['reservationDate'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid reservation date']);
    exit();
}

$guestCount = intval($data['guestCount']);
$reservationDate = $data['reservationDate'];

$tables = [];
$sql = "SELECT id, name, capacity, x, y FROM tables ORDER BY capacity ASC";
$result = $conn->query($sql);
if ($result === false) {
    echo json_encode(['success' => false, 'message' => 'SQL Query failed: ' . $conn->error]);
    exit();
}
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $tables[] = $row;
    }
} else {
    error_log('No tables found in the database');
    echo json_encode(['success' => false, 'message' => 'No tables found in the database.']);
    exit();
}

$timeSlotRanges = [
    'morning'   => ['08:00:00', '10:00:00'],
    'afternoon' => ['13:00:00', '15:00:00'],
    'night'     => ['20:00:00', '22:00:00']
];

function calculateDistance($table1, $table2) {
    return sqrt(pow($table1['x'] - $table2['x'], 2) + pow($table1['y'] - $table2['y'], 2));
}

function isTableAvailableForSlot($tableName, $reservationDate, $startTime, $endTime, $conn) {
    $startDateTime = $reservationDate . ' ' . $startTime;
    $endDateTime   = $reservationDate . ' ' . $endTime;
    
    $sql = "SELECT * FROM reservations WHERE table_name = ? 
            AND time_from < ? AND time_to > ?";
    
    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param('sss', $tableName, $endDateTime, $startDateTime);
        $stmt->execute();
        $result = $stmt->get_result();
        $stmt->close();
        return ($result->num_rows === 0);
    } else {
        error_log('SQL prepare failed: ' . $conn->error);
        echo json_encode(['success' => false, 'message' => 'SQL prepare failed: ' . $conn->error]);
        exit();
    }
}

function findBestCombinations($availableTables, $guestCount, $maxDistance = 100) {
    $validCombinations = [];
    $capacityBuffer = max(2, $guestCount * 0.7);
    $maxCapacity = $guestCount + $capacityBuffer;

    foreach ($availableTables as $table) {
        if ($table['capacity'] >= $guestCount && $table['capacity'] <= $maxCapacity) {
            $validCombinations[] = [$table];
        }
    }

    for ($i = 0; $i < count($availableTables); $i++) {
        for ($j = $i + 1; $j < count($availableTables); $j++) {
            $totalCapacity = $availableTables[$i]['capacity'] + $availableTables[$j]['capacity'];
            $distance = calculateDistance($availableTables[$i], $availableTables[$j]);
            if ($totalCapacity >= $guestCount && $totalCapacity <= $maxCapacity && $distance <= $maxDistance) {
                $validCombinations[] = [$availableTables[$i], $availableTables[$j]];
            }
        }
    }

    if (empty($validCombinations)) {
        for ($i = 0; $i < count($availableTables); $i++) {
            for ($j = $i + 1; $j < count($availableTables); $j++) {
                for ($k = $j + 1; $k < count($availableTables); $k++) {
                    $totalCapacity = $availableTables[$i]['capacity'] + $availableTables[$j]['capacity'] + $availableTables[$k]['capacity'];
                    $distance1 = calculateDistance($availableTables[$i], $availableTables[$j]);
                    $distance2 = calculateDistance($availableTables[$j], $availableTables[$k]);
                    $distance3 = calculateDistance($availableTables[$i], $availableTables[$k]);
                    $maxPairDistance = max($distance1, $distance2, $distance3);
                    if ($totalCapacity >= $guestCount && $totalCapacity <= $maxCapacity && $maxPairDistance <= $maxDistance) {
                        $validCombinations[] = [$availableTables[$i], $availableTables[$j], $availableTables[$k]];
                    }
                }
            }
        }
    }

    usort($validCombinations, function($a, $b) use ($guestCount) {
        $capA = array_sum(array_column($a, 'capacity'));
        $capB = array_sum(array_column($b, 'capacity'));
        $excessA = $capA - $guestCount;
        $excessB = $capB - $guestCount;
        if ($excessA == $excessB) {
            $distA = 0;
            for ($i = 1; $i < count($a); $i++) {
                $distA += calculateDistance($a[$i-1], $a[$i]);
            }
            $distB = 0;
            for ($i = 1; $i < count($b); $i++) {
                $distB += calculateDistance($b[$i-1], $b[$i]);
            }
            return $distA <=> $distB;
        }
        return $excessA <=> $excessB;
    });

    return array_slice($validCombinations, 0, 1);
}

// Track used table IDs to prevent reuse across slots
$usedTableIds = [];
$recommendedTables = [];

foreach (['morning', 'afternoon', 'night'] as $timeSlot) {
    $startTime = $timeSlotRanges[$timeSlot][0];
    $endTime   = $timeSlotRanges[$timeSlot][1];
    $availableTables = [];

    foreach ($tables as $table) {
        if (!in_array($table['id'], $usedTableIds) && isTableAvailableForSlot($table['name'], $reservationDate, $startTime, $endTime, $conn)) {
            $availableTables[] = $table;
        }
    }

    $combination = findBestCombinations($availableTables, $guestCount, 100);
    if (empty($combination)) {
        $combination = findBestCombinations($availableTables, $guestCount, 150);
    }
    $recommendedTables[$timeSlot] = $combination;

    // Mark tables as used if a combination is found
    if (!empty($combination)) {
        foreach ($combination[0] as $table) {
            $usedTableIds[] = $table['id'];
        }
    }
}

echo json_encode(['success' => true, 'recommendedTables' => $recommendedTables]);
$conn->close();
?>