<?php
require_once 'dbconnect.php';
session_start();
header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'User not logged in']);
    exit;
}

// Get the request data
$data = json_decode(file_get_contents('php://input'), true);
$booking_id = $data['booking_id'] ?? null;

if (!$booking_id) {
    echo json_encode(['success' => false, 'message' => 'Booking ID is required']);
    exit;
}

// Get the booking details to check if it's eligible for cancellation
$query = "SELECT * FROM bookings WHERE booking_id = ? AND user_id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("ii", $booking_id, $_SESSION['user_id']);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Booking not found or not authorized']);
    exit;
}

$booking = $result->fetch_assoc();

// Check if booking is already cancelled
if ($booking['status'] === 'cancelled') {
    echo json_encode(['success' => false, 'message' => 'Booking is already cancelled']);
    exit;
}

// Check if booking is eligible for cancellation (e.g., not completed)
if ($booking['status'] === 'completed') {
    echo json_encode(['success' => false, 'message' => 'Completed bookings cannot be cancelled']);
    exit;
}

// Begin transaction
$conn->begin_transaction();

try {
    // Update booking status to cancelled
    $update_query = "UPDATE bookings SET status = 'cancelled' WHERE booking_id = ?";
    $update_stmt = $conn->prepare($update_query);
    $update_stmt->bind_param("i", $booking_id);
    $update_result = $update_stmt->execute();

    // Handle payment refund if payment was made
    if ($booking['payment_status'] === 'paid') {
        // Calculate refund amount based on your policy
        // For example: full refund if cancelled 24+ hours before appointment
        $booking_time = strtotime($booking['booking_date'] . ' ' . $booking['time_slot']);
        $current_time = time();
        $hours_difference = ($booking_time - $current_time) / 3600;
        
        $refund_amount = $booking['total_amount']; // Default to full refund
        $refund_status = 'full';
        
        // If less than 24 hours before appointment, partial refund (e.g., 50%)
        if ($hours_difference < 24) {
            $refund_amount = $booking['total_amount'] * 0.5;
            $refund_status = 'partial';
        }
        
        // If less than 2 hours before appointment, no refund
        if ($hours_difference < 2) {
            $refund_amount = 0;
            $refund_status = 'none';
        }
        
        // Update payment status to refunded
        if ($refund_amount > 0) {
            $payment_update = "UPDATE bookings SET payment_status = 'refunded', refund_amount = ? WHERE booking_id = ?";
            $payment_stmt = $conn->prepare($payment_update);
            $payment_stmt->bind_param("di", $refund_amount, $booking_id);
            $payment_stmt->execute();
            
            // Here you would integrate with your payment gateway to process the actual refund
            // This is a placeholder for the actual payment gateway integration
            // processRefund($booking['payment_id'], $refund_amount);
        }
    }
    
    // Commit transaction
    $conn->commit();
    
    // Return success response with refund information if applicable
    $response = ['success' => true, 'message' => 'Booking cancelled successfully'];
    
    if ($booking['payment_status'] === 'paid') {
        $response['refund_status'] = $refund_status;
        $response['refund_amount'] = $refund_amount;
    }
    
    echo json_encode($response);
    
} catch (Exception $e) {
    // Rollback transaction on error
    $conn->rollback();
    echo json_encode(['success' => false, 'message' => 'Error cancelling booking: ' . $e->getMessage()]);
} 