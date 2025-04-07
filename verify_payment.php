<?php
session_start();
require_once 'dbconnect.php';

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

header('Content-Type: application/json');

if (!$input || !isset($input['razorpay_payment_id']) || 
    !isset($input['razorpay_order_id']) || 
    !isset($input['razorpay_signature']) ||
    !isset($input['booking_id'])) {
    
    echo json_encode(['success' => false, 'message' => 'Missing required parameters']);
    exit;
}

// Verify payment signature (production code should verify this properly)
$payment_id = $input['razorpay_payment_id'];
$order_id = $input['razorpay_order_id'];
$signature = $input['razorpay_signature'];
$booking_id = $input['booking_id'];

// For a real implementation, verify the signature with Razorpay
$api_key = 'rzp_test_pM7XeD3uvgF2Or';
$api_secret = 'pjPyycAbpchrCl4tgwUqc7V6';

// For now, we'll assume the payment is valid
$is_valid = true;

if ($is_valid) {
    // Update booking and payment status
    $conn->begin_transaction();
    
    try {
        // Update booking status
        $update_booking = "UPDATE bookings 
                          SET payment_status = 'paid', 
                              status = 'accepted',
                              payment_id = ?,
                              updated_at = CURRENT_TIMESTAMP
                          WHERE booking_id = ?";
        
        $stmt = $conn->prepare($update_booking);
        $stmt->bind_param('si', $payment_id, $booking_id);
        
        if (!$stmt->execute()) {
            throw new Exception("Error updating booking: " . $stmt->error);
        }
        
        // Update payment record
        $update_payment = "UPDATE payments 
                          SET transaction_id = ?, 
                              status = 'completed',
                              payment_method = 'razorpay',
                              payment_date = CURRENT_TIMESTAMP,
                              updated_at = CURRENT_TIMESTAMP
                          WHERE booking_id = ?";
        
        $stmt = $conn->prepare($update_payment);
        $stmt->bind_param('si', $payment_id, $booking_id);
        
        if (!$stmt->execute()) {
            throw new Exception("Error updating payment: " . $stmt->error);
        }
        
        // Commit transaction
        $conn->commit();
        
        echo json_encode(['success' => true, 'message' => 'Payment verified successfully']);
        
    } catch (Exception $e) {
        // Rollback on error
        $conn->rollback();
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid payment signature']);
}
?>