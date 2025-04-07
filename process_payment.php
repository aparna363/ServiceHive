<?php
require_once 'dbconnect.php';
session_start();

header('Content-Type: application/json');

// Get JSON data
$data = json_decode(file_get_contents('php://input'), true);

// Log incoming data for debugging
error_log("Payment data received: " . json_encode($data));

// Check if we have the minimum required data
if (!isset($data['payment_id']) || !isset($data['amount']) || !isset($data['payment_method']) || !isset($data['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Missing required data']);
    exit;
}

try {
    // Start transaction
    $conn->begin_transaction();
    
    // If booking_id is null, we need to create a new booking from cart items
    if (!isset($data['booking_id']) || $data['booking_id'] === null) {
        if (!isset($data['cart_items']) || empty($data['cart_items'])) {
            throw new Exception('No cart items provided for new booking');
        }
        
        // Get the first cart item to determine service details
        $first_item = $data['cart_items'][0];
        $service_id = $first_item['service_id'];
        
        // Create a new booking
        $create_booking_query = "INSERT INTO bookings (user_id, service_id, booking_date, time_slot, status, 
                                payment_status, payment_id, payment_method, total_amount, address_id, created_at) 
                                VALUES (?, ?, CURDATE(), '09:00-11:00', 'pending', 'paid', ?, ?, ?, ?, NOW())";
        $stmt = $conn->prepare($create_booking_query);
        $stmt->bind_param("iissdi", 
            $data['user_id'],
            $service_id,
            $data['payment_id'],
            $data['payment_method'],
            $data['amount'],
            $data['address_id']
        );
        $stmt->execute();
        
        // Get the newly created booking ID
        $booking_id = $conn->insert_id;
        
        // Add booking details for each cart item
        foreach ($data['cart_items'] as $item) {
            $booking_detail_query = "INSERT INTO booking_details (booking_id, sub_service_id, quantity, price, measurement) 
                                    VALUES (?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($booking_detail_query);
            $stmt->bind_param("iidds", 
                $booking_id,
                $item['sub_service_id'],
                $item['quantity'],
                $item['final_price'],
                $item['measurement']
            );
            $stmt->execute();
        }
        
        // Update data array with the new booking ID
        $data['booking_id'] = $booking_id;
    }
    
    // Insert into payments table
    $payment_query = "INSERT INTO payments (booking_id, amount, payment_method, transaction_id, status, created_at) 
                     VALUES (?, ?, ?, ?, 'completed', NOW())";
    $stmt = $conn->prepare($payment_query);
    $stmt->bind_param("idss", 
        $data['booking_id'],
        $data['amount'],
        $data['payment_method'],
        $data['payment_id']
    );
    $stmt->execute();

    // Update booking payment status
    $booking_query = "UPDATE bookings 
                     SET payment_status = 'paid',
                         payment_id = ?,
                         payment_method = ?,
                         total_amount = ?,
                         address_id = ?
                     WHERE booking_id = ?";
    $stmt = $conn->prepare($booking_query);
    $stmt->bind_param("ssdii",
        $data['payment_id'],
        $data['payment_method'],
        $data['amount'],
        $data['address_id'],
        $data['booking_id']
    );
    $stmt->execute();

    // Create notification
    $notification_query = "INSERT INTO notifications (user_id, title, message, type, reference_id, created_at) 
                         VALUES (?, 'Payment Successful', ?, 'payment', ?, NOW())";
    $message = 'Payment of ₹' . number_format($data['amount'], 2) . ' received for booking #' . $data['booking_id'];
    $stmt = $conn->prepare($notification_query);
    $stmt->bind_param("isi",
        $data['user_id'],
        $message,
        $data['booking_id']
    );
    $stmt->execute();

    // Update cart status to completed
    $cart_query = "UPDATE cart SET status = 'completed' WHERE user_id = ? AND status = 'pending'";
    $stmt = $conn->prepare($cart_query);
    $stmt->bind_param("i", $data['user_id']);
    $stmt->execute();

    // Commit transaction
    $conn->commit();

    echo json_encode(['success' => true, 'booking_id' => $data['booking_id']]);

} catch (Exception $e) {
    // Rollback on error
    $conn->rollback();
    error_log("Payment processing error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Payment processing failed: ' . $e->getMessage()]);
}

$conn->close(); 