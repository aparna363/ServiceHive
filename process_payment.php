<?php
require_once 'dbconnect.php';
session_start();

header('Content-Type: application/json');

// Get JSON data
$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['booking_id']) || !isset($data['payment_id']) || !isset($data['amount']) || !isset($data['payment_method'])) {
    echo json_encode(['success' => false, 'message' => 'Missing required data']);
    exit;
}

if (isset($data['action'])) {
    switch ($data['action']) {
        case 'book_visit':
            // Handle visit booking
            try {
                $conn->begin_transaction();
                
                // Insert visit booking
                $visit_query = "INSERT INTO visit_bookings (user_id, category_id, visit_date, visit_time, address, notes) 
                              VALUES (?, ?, ?, ?, ?, ?)";
                $stmt = $conn->prepare($visit_query);
                $stmt->bind_param("iissss", 
                    $_SESSION['user_id'],
                    $data['category_id'],
                    $data['visit_date'],
                    $data['visit_time'],
                    $data['visit_address'],
                    $data['visit_notes']
                );
                $stmt->execute();
                $visit_id = $conn->insert_id;
                
                $conn->commit();
                echo json_encode(['success' => true, 'visit_id' => $visit_id]);
            } catch (Exception $e) {
                $conn->rollback();
                echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            }
            break;
            
        case 'confirm_visit':
            // Handle visit payment confirmation
            try {
                $conn->begin_transaction();
                
                // Update visit booking status and add payment record
                $update_query = "UPDATE visit_bookings SET status = 'confirmed', payment_id = ? WHERE id = ?";
                $stmt = $conn->prepare($update_query);
                $stmt->bind_param("si", $data['payment_id'], $data['visit_id']);
                $stmt->execute();
                
                // Insert payment record
                $payment_query = "INSERT INTO payments (booking_type, booking_id, amount, transaction_id) 
                                VALUES ('visit', ?, ?, ?)";
                $stmt = $conn->prepare($payment_query);
                $stmt->bind_param("ids", $data['visit_id'], $data['amount'], $data['payment_id']);
                $stmt->execute();
                
                $conn->commit();
                echo json_encode(['success' => true, 'visit_reference' => 'V' . str_pad($data['visit_id'], 6, '0', STR_PAD_LEFT)]);
            } catch (Exception $e) {
                $conn->rollback();
                echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            }
            break;
            
        case 'book_emergency':
            // Similar structure for emergency bookings
            // ... implement emergency booking logic ...
            break;
            
        case 'confirm_emergency':
            // Similar structure for emergency payment confirmation
            // ... implement emergency confirmation logic ...
            break;
            
        default:
            // Handle regular service bookings
            try {
                // Start transaction
                $conn->begin_transaction();

                // Insert into payments table
                $payment_query = "INSERT INTO payments (booking_id, amount, payment_method, transaction_id, status) 
                                 VALUES (?, ?, ?, ?, 'completed')";
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
                                     total_amount = ?
                                 WHERE booking_id = ?";
                $stmt = $conn->prepare($booking_query);
                $stmt->bind_param("ssdi",
                    $data['payment_id'],
                    $data['payment_method'],
                    $data['amount'],
                    $data['booking_id']
                );
                $stmt->execute();

                // Create notification
                $notification_query = "INSERT INTO notifications (user_id, title, message, type, reference_id) 
                                     SELECT user_id,
                                            'Payment Successful',
                                            CONCAT('Payment of ₹', ?, ' received for booking #', ?) as message,
                                            'payment',
                                            booking_id
                                     FROM bookings 
                                     WHERE booking_id = ?";
                $stmt = $conn->prepare($notification_query);
                $stmt->bind_param("dii",
                    $data['amount'],
                    $data['booking_id'],
                    $data['booking_id']
                );
                $stmt->execute();

                // Commit transaction
                $conn->commit();

                echo json_encode(['success' => true]);

            } catch (Exception $e) {
                // Rollback on error
                $conn->rollback();
                error_log("Payment processing error: " . $e->getMessage());
                echo json_encode(['success' => false, 'message' => 'Payment processing failed']);
            }
            break;
    }
} else {
    // Handle regular service bookings
    try {
        // Start transaction
        $conn->begin_transaction();

        // Insert into payments table
        $payment_query = "INSERT INTO payments (booking_id, amount, payment_method, transaction_id, status) 
                         VALUES (?, ?, ?, ?, 'completed')";
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
                             total_amount = ?
                         WHERE booking_id = ?";
        $stmt = $conn->prepare($booking_query);
        $stmt->bind_param("ssdi",
            $data['payment_id'],
            $data['payment_method'],
            $data['amount'],
            $data['booking_id']
        );
        $stmt->execute();

        // Create notification
        $notification_query = "INSERT INTO notifications (user_id, title, message, type, reference_id) 
                             SELECT user_id,
                                    'Payment Successful',
                                    CONCAT('Payment of ₹', ?, ' received for booking #', ?) as message,
                                    'payment',
                                    booking_id
                             FROM bookings 
                             WHERE booking_id = ?";
        $stmt = $conn->prepare($notification_query);
        $stmt->bind_param("dii",
            $data['amount'],
            $data['booking_id'],
            $data['booking_id']
        );
        $stmt->execute();

        // Commit transaction
        $conn->commit();

        echo json_encode(['success' => true]);

    } catch (Exception $e) {
        // Rollback on error
        $conn->rollback();
        error_log("Payment processing error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Payment processing failed']);
    }
}

$conn->close(); 