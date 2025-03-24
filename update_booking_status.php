<?php
require_once 'dbconnect.php';
session_start();

header('Content-Type: application/json');

// Check if user is logged in and is a provider
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'service_provider') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

// Get POST data
$booking_id = isset($_POST['booking_id']) ? intval($_POST['booking_id']) : 0;
$status = isset($_POST['status']) ? $_POST['status'] : '';

// Validate inputs
if (!$booking_id || !in_array($status, ['accepted', 'rejected', 'completed'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid input data']);
    exit;
}

try {
    // Verify the booking belongs to this provider
    $provider_check = $conn->prepare("
        SELECT b.booking_id 
        FROM bookings b 
        JOIN service_providers sp ON b.provider_id = sp.provider_id 
        WHERE b.booking_id = ? AND sp.user_id = ?
    ");
    $provider_check->bind_param("ii", $booking_id, $_SESSION['user_id']);
    $provider_check->execute();
    $result = $provider_check->get_result();

    if ($result->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Unauthorized booking access']);
        exit;
    }

    // Update booking status
    $stmt = $conn->prepare("UPDATE bookings SET status = ?, updated_at = CURRENT_TIMESTAMP WHERE booking_id = ?");
    $stmt->bind_param("si", $status, $booking_id);
    
    if ($stmt->execute()) {
        // Create notification for the customer
        $notification_query = "
            INSERT INTO notifications (user_id, title, message, type, reference_id)
            SELECT 
                user_id,
                CASE 
                    WHEN ? = 'accepted' THEN 'Booking Accepted'
                    WHEN ? = 'rejected' THEN 'Booking Rejected'
                    ELSE 'Booking Status Updated'
                END,
                CASE 
                    WHEN ? = 'accepted' THEN 'Your booking has been accepted by the service provider'
                    WHEN ? = 'rejected' THEN 'Your booking has been rejected by the service provider'
                    ELSE 'Your booking status has been updated'
                END,
                'booking',
                booking_id
            FROM bookings
            WHERE booking_id = ?
        ";
        
        $notify = $conn->prepare($notification_query);
        $notify->bind_param("ssssi", $status, $status, $status, $status, $booking_id);
        $notify->execute();

        echo json_encode(['success' => true]);
    } else {
        throw new Exception("Failed to update booking status");
    }

} catch (Exception $e) {
    error_log("Booking status update error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}

$conn->close(); 