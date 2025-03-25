<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Suppress PHP errors from being displayed in the output
ini_set('display_errors', 0);
error_reporting(0);

// Include database connection
require_once 'dbconnect.php';

// Set content type to JSON
header('Content-Type: application/json');

// Get the raw POST data
$jsonData = file_get_contents('php://input');
$data = json_decode($jsonData, true);

// Razorpay API keys (store these securely, ideally in environment variables)
$key_id = 'rzp_test_pM7XeD3uvgF2Or';
$key_secret = 'pjPyycAbpchrCl4tgwUqc7V6';

try {
    // Check if required data is provided
    if (!isset($data['razorpay_payment_id']) || !isset($data['razorpay_order_id']) || 
        !isset($data['razorpay_signature']) || !isset($data['booking_id']) || !isset($data['type'])) {
        throw new Exception('Missing payment verification data');
    }

    // Verify signature
    $generated_signature = hash_hmac('sha256', $data['razorpay_order_id'] . '|' . $data['razorpay_payment_id'], $key_secret);

    if ($generated_signature !== $data['razorpay_signature']) {
        throw new Exception('Invalid payment signature');
    }

    // Get the booking reference from the database
    $table_name = $data['type'] === 'visit' ? 'visit_bookings' : 'emergency_bookings';
    $stmt = $conn->prepare("SELECT reference FROM $table_name WHERE id = ?");
    $stmt->bind_param("i", $data['booking_id']);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        throw new Exception('Booking not found');
    }

    $booking = $result->fetch_assoc();
    $reference = $booking['reference'];

    // Update booking status and payment details
    $update_stmt = $conn->prepare("UPDATE $table_name SET 
        status = 'confirmed', 
        payment_status = 'paid', 
        razorpay_payment_id = ?, 
        payment_date = NOW() 
        WHERE id = ?");
    $update_stmt->bind_param("si", $data['razorpay_payment_id'], $data['booking_id']);

    if (!$update_stmt->execute()) {
        throw new Exception('Failed to update booking: ' . $update_stmt->error);
    }

    // Return success response
    echo json_encode([
        'success' => true,
        'message' => 'Payment verified successfully',
        'reference' => $reference
    ]);

} catch (Exception $e) {
    // Return error in proper JSON format
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>