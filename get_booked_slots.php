<?php
require_once 'dbconnect.php';
session_start();

header('Content-Type: application/json');

try {
    // Get and validate input
    $input = json_decode(file_get_contents('php://input'), true);
    $date = $input['date'] ?? '';
    $providerId = $input['provider_id'] ?? 0;

    if (empty($date)) {
        throw new Exception('Date is required');
    }

    if (!$providerId) {
        throw new Exception('Provider ID is required');
    }

    // Validate date format and ensure it's not in the past
    $bookingDate = new DateTime($date);
    $today = new DateTime('today');
    
    if ($bookingDate < $today) {
        throw new Exception('Cannot book dates in the past');
    }

    // Get all bookings for this date and provider with detailed status check
    $query = "SELECT 
                TIME_FORMAT(time_slot, '%H:%i:%s') as time_slot, 
                COUNT(*) as count,
                GROUP_CONCAT(status) as slot_statuses
              FROM bookings 
              WHERE DATE(booking_date) = ? 
              AND provider_id = ? 
              AND status IN ('pending', 'confirmed', 'in_progress') 
              GROUP BY time_slot";
              
    $stmt = $conn->prepare($query);
    $stmt->bind_param("si", $date, $providerId);
    
    if (!$stmt->execute()) {
        throw new Exception('Error checking bookings: ' . $stmt->error);
    }
    
    $result = $stmt->get_result();

    $bookedSlots = [];
    $unavailableSlots = [];
    while ($row = $result->fetch_assoc()) {
        $count = intval($row['count']);
        $statuses = explode(',', $row['slot_statuses']);
        
        // Mark slot as unavailable if it has any active bookings
        if ($count > 0) {
            $unavailableSlots[$row['time_slot']] = true;
        }
        
        $bookedSlots[$row['time_slot']] = $count;
    }

    // Get total bookings for the day with status check
    $dailyQuery = "SELECT COUNT(*) as total 
                   FROM bookings 
                   WHERE DATE(booking_date) = ? 
                   AND provider_id = ? 
                   AND status IN ('pending', 'confirmed', 'in_progress')";
                   
    $dailyStmt = $conn->prepare($dailyQuery);
    $dailyStmt->bind_param("si", $date, $providerId);
    
    if (!$dailyStmt->execute()) {
        throw new Exception('Error checking daily bookings: ' . $dailyStmt->error);
    }
    
    $dailyResult = $dailyStmt->get_result();
    $dailyCount = intval($dailyResult->fetch_assoc()['total']);

    // Use default working hours
    $workingHoursStart = '09:00:00';
    $workingHoursEnd = '17:00:00';

    echo json_encode([
        'success' => true,
        'booked_slots' => $bookedSlots,
        'unavailable_slots' => $unavailableSlots,
        'daily_count' => $dailyCount,
        'working_hours' => [
            'start' => $workingHoursStart,
            'end' => $workingHoursEnd
        ]
    ]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

$conn->close();