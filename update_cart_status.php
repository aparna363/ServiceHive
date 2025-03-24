<?php
require_once 'dbconnect.php';
session_start();

header('Content-Type: application/json');

try {
    // Get POST data
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($data['user_id']) || !isset($data['status']) || !isset($data['booking_id'])) {
        throw new Exception('Missing required parameters');
    }

    $user_id = filter_var($data['user_id'], FILTER_VALIDATE_INT);
    $status = filter_var($data['status'], FILTER_SANITIZE_STRING);
    $booking_id = filter_var($data['booking_id'], FILTER_VALIDATE_INT);

    if (!$user_id || !$booking_id) {
        throw new Exception('Invalid user ID or booking ID');
    }

    // Begin transaction
    $conn->begin_transaction();

    // 1. Update cart status
    $update_cart_query = "UPDATE cart 
                         SET status = ?, updated_at = CURRENT_TIMESTAMP 
                         WHERE user_id = ? AND status = 'pending'";
    
    $stmt = $conn->prepare($update_cart_query);
    if (!$stmt) {
        throw new Exception("Prepare failed (cart): " . $conn->error);
    }

    $stmt->bind_param("si", $status, $user_id);
    
    if (!$stmt->execute()) {
        throw new Exception("Execute failed (cart): " . $stmt->error);
    }

    // 2. Update booking status
    // Try different possible column names for status
    $update_success = false;
    
    // First attempt - most common column names
    try {
        $update_booking_query = "UPDATE bookings 
                               SET status = ?, 
                                   payment_status = 'paid',
                                   updated_at = CURRENT_TIMESTAMP 
                               WHERE booking_id = ? AND user_id = ?";
        
        $stmt = $conn->prepare($update_booking_query);
        if ($stmt) {
            $stmt->bind_param("sii", $status, $booking_id, $user_id);
            if ($stmt->execute()) {
                $update_success = true;
            }
        }
    } catch (Exception $e) {
        error_log("First booking update attempt failed: " . $e->getMessage());
    }

    // Second attempt - alternative column names
    if (!$update_success) {
        try {
            $update_booking_query = "UPDATE bookings 
                                   SET booking_status = ?, 
                                       payment_status = 'paid',
                                       modified_at = CURRENT_TIMESTAMP 
                                   WHERE id = ? AND customer_id = ?";
            
            $stmt = $conn->prepare($update_booking_query);
            if ($stmt) {
                $stmt->bind_param("sii", $status, $booking_id, $user_id);
                if ($stmt->execute()) {
                    $update_success = true;
                }
            }
        } catch (Exception $e) {
            error_log("Second booking update attempt failed: " . $e->getMessage());
        }
    }

    // If both attempts failed, throw exception
    if (!$update_success) {
        throw new Exception("Failed to update booking status");
    }

    // 3. Create notification for successful payment
    try {
        $notification_query = "INSERT INTO notifications (
            user_id, 
            title, 
            message, 
            type, 
            reference_id
        ) VALUES (?, ?, ?, 'payment', ?)";

        $title = "Payment Successful";
        $message = "Your payment for booking #" . $booking_id . " has been completed successfully.";
        
        $stmt = $conn->prepare($notification_query);
        if ($stmt) {
            $stmt->bind_param("issi", $user_id, $title, $message, $booking_id);
            $stmt->execute();
        }
    } catch (Exception $e) {
        // Log but don't fail if notification creation fails
        error_log("Failed to create notification: " . $e->getMessage());
    }

    // Commit transaction
    $conn->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Payment processed and statuses updated successfully'
    ]);

} catch (Exception $e) {
    // Rollback transaction on error
    if ($conn->connect_errno === 0) {
        $conn->rollback();
    }
    
    error_log("Update status error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
} 