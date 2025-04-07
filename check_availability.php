<?php
// Prevent any HTML error output
error_reporting(0);
ini_set('display_errors', 0);

// Start output buffering to catch any unexpected output
ob_start();

header('Content-Type: application/json');

try {
    require_once 'config.php';
    
    // Validate request method
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Invalid request method');
    }

    // Validate required parameters
    if (empty($_POST['date']) || empty($_POST['provider_id'])) {
        throw new Exception('Missing required parameters');
    }

    $booking_date = $_POST['date'];
    $provider_id = (int)$_POST['provider_id'];

    // Basic validation
    if ($provider_id <= 0) {
        throw new Exception('Invalid provider ID');
    }

    if (!strtotime($booking_date)) {
        throw new Exception('Invalid date format');
    }

    // Define available time slots
    $available_slots = [
        ['value' => '09:00:00', 'display_time' => '9:00 AM'],
        ['value' => '10:00:00', 'display_time' => '10:00 AM'],
        ['value' => '11:00:00', 'display_time' => '11:00 AM'],
        ['value' => '12:00:00', 'display_time' => '12:00 PM'],
        ['value' => '13:00:00', 'display_time' => '1:00 PM'],
        ['value' => '14:00:00', 'display_time' => '2:00 PM'],
        ['value' => '15:00:00', 'display_time' => '3:00 PM'],
        ['value' => '16:00:00', 'display_time' => '4:00 PM'],
        ['value' => '17:00:00', 'display_time' => '5:00 PM']
    ];

    // Get booked slots
    $query = "SELECT booking_time FROM bookings 
              WHERE provider_id = ? AND booking_date = ? AND status != 'cancelled'";
    
    $stmt = $conn->prepare($query);
    if (!$stmt) {
        throw new Exception('Database error: ' . $conn->error);
    }

    $stmt->bind_param("is", $provider_id, $booking_date);
    if (!$stmt->execute()) {
        throw new Exception('Query error: ' . $stmt->error);
    }

    $result = $stmt->get_result();
    $booked_times = [];
    while ($row = $result->fetch_assoc()) {
        $booked_times[] = $row['booking_time'];
    }

    // Mark booked slots
    foreach ($available_slots as &$slot) {
        $slot['booked'] = in_array($slot['value'], $booked_times);
    }

    // Clear any output buffer
    ob_clean();

    // Return success response
    echo json_encode([
        'success' => true,
        'available_slots' => array_values($available_slots),
        'date' => $booking_date
    ]);

} catch (Exception $e) {
    // Clear any output buffer
    ob_clean();

    // Return error response
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

// End output buffering
ob_end_flush();
?> 