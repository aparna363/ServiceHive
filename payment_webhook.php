<?php
require_once 'dbconnect.php';

// Get the webhook payload
$webhookBody = file_get_contents('php://input');
$webhookData = json_decode($webhookBody, true);

// Verify webhook signature
$webhookSignature = $_SERVER['HTTP_X_RAZORPAY_SIGNATURE'];
$razorpaySecret = 'pjPyycAbpchrCl4tgwUqc7V6'; // Your Razorpay secret key

$expectedSignature = hash_hmac('sha256', $webhookBody, $razorpaySecret);

// Log webhook data for debugging
file_put_contents('webhook_log.txt', date('Y-m-d H:i:s') . " - Webhook received: " . $webhookBody . "\n", FILE_APPEND);

// Verify signature
if ($expectedSignature !== $webhookSignature) {
    http_response_code(400);
    exit('Invalid signature');
}

// Process the webhook based on event type
if (isset($webhookData['event'])) {
    switch ($webhookData['event']) {
        case 'payment.authorized':
            // Payment has been authorized but not yet captured
            handlePaymentAuthorized($conn, $webhookData['payload']['payment']['entity']);
            break;
            
        case 'payment.captured':
            // Payment has been captured (completed)
            handlePaymentCaptured($conn, $webhookData['payload']['payment']['entity']);
            break;
            
        case 'payment.failed':
            // Payment has failed
            handlePaymentFailed($conn, $webhookData['payload']['payment']['entity']);
            break;
    }
}

http_response_code(200);
echo 'Webhook processed successfully';

// Helper functions
function handlePaymentAuthorized($conn, $paymentData) {
    // Extract order ID and find the corresponding booking
    $orderId = $paymentData['order_id'];
    $paymentId = $paymentData['id'];
    
    // Log for debugging
    file_put_contents('webhook_log.txt', date('Y-m-d H:i:s') . " - Payment authorized: " . $paymentId . "\n", FILE_APPEND);
    
    // Find booking_id from receipt in orders table
    // This would require an API call to Razorpay to get order details
    // For simplicity, we'll assume the receipt format is 'rcpt_BOOKING_ID'
}

function handlePaymentCaptured($conn, $paymentData) {
    // Extract payment details
    $paymentId = $paymentData['id'];
    $orderId = $paymentData['order_id'];
    $amount = $paymentData['amount'] / 100; // Convert from paise to rupees
    
    // Log for debugging
    file_put_contents('webhook_log.txt', date('Y-m-d H:i:s') . " - Payment captured: " . $paymentId . "\n", FILE_APPEND);
    
    // Get order details from Razorpay to find the receipt (which contains booking_id)
    $orderDetails = getOrderDetails($orderId);
    
    if ($orderDetails && isset($orderDetails['receipt'])) {
        $receipt = $orderDetails['receipt'];
        $bookingId = str_replace('rcpt_', '', $receipt);
        
        // Update payment status
        updatePaymentStatus($conn, $bookingId, $paymentId, 'completed');
        
        // Update booking payment status
        $updateBooking = "UPDATE bookings SET payment_status = 'paid' WHERE booking_id = ?";
        $stmt = $conn->prepare($updateBooking);
        $stmt->bind_param("i", $bookingId);
        $stmt->execute();
        
        // Create notification
        $getUserId = "SELECT user_id, provider_id FROM bookings WHERE booking_id = ?";
        $stmt = $conn->prepare($getUserId);
        $stmt->bind_param("i", $bookingId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($row = $result->fetch_assoc()) {
            $userId = $row['user_id'];
            $providerId = $row['provider_id'];
            
            // Notify user
            $notifyUser = "INSERT INTO notifications (user_id, title, message, type, reference_id) 
                          VALUES (?, 'Payment Successful', 'Your payment for booking #$bookingId has been received.', 'payment', ?)";
            $stmt = $conn->prepare($notifyUser);
            $stmt->bind_param("ii", $userId, $bookingId);
            $stmt->execute();
            
            // Notify provider
            $notifyProvider = "INSERT INTO notifications (user_id, title, message, type, reference_id) 
                              SELECT u.id, 'New Booking Payment', 'Payment received for booking #$bookingId.', 'payment', ?
                              FROM service_providers sp
                              JOIN users u ON sp.user_id = u.id
                              WHERE sp.provider_id = ?";
            $stmt = $conn->prepare($notifyProvider);
            $stmt->bind_param("ii", $bookingId, $providerId);
            $stmt->execute();
        }
    }
}

function handlePaymentFailed($conn, $paymentData) {
    // Extract payment details
    $paymentId = $paymentData['id'];
    $orderId = $paymentData['order_id'];
    $errorCode = $paymentData['error_code'];
    $errorDescription = $paymentData['error_description'];
    
    // Log for debugging
    file_put_contents('webhook_log.txt', date('Y-m-d H:i:s') . " - Payment failed: " . $paymentId . " - " . $errorDescription . "\n", FILE_APPEND);
    
    // Get order details from Razorpay to find the receipt (which contains booking_id)
    $orderDetails = getOrderDetails($orderId);
    
    if ($orderDetails && isset($orderDetails['receipt'])) {
        $receipt = $orderDetails['receipt'];
        $bookingId = str_replace('rcpt_', '', $receipt);
        
        // Update payment status
        updatePaymentStatus($conn, $bookingId, $paymentId, 'failed');
        
        // Create notification
        $getUserId = "SELECT user_id FROM bookings WHERE booking_id = ?";
        $stmt = $conn->prepare($getUserId);
        $stmt->bind_param("i", $bookingId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($row = $result->fetch_assoc()) {
            $userId = $row['user_id'];
            
            // Notify user about failed payment
            $notifyUser = "INSERT INTO notifications (user_id, title, message, type, reference_id) 
                          VALUES (?, 'Payment Failed', 'Your payment for booking #$bookingId has failed. Reason: $errorDescription', 'payment', ?)";
            $stmt = $conn->prepare($notifyUser);
            $stmt->bind_param("ii", $userId, $bookingId);
            $stmt->execute();
        }
    }
}

function getOrderDetails($orderId) {
    $api_key = 'rzp_test_pM7XeD3uvgF2Or';
    $api_secret = 'pjPyycAbpchrCl4tgwUqc7V6';
    
    $url = "https://api.razorpay.com/v1/orders/{$orderId}";
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_USERPWD, $api_key . ':' . $api_secret);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    
    $response = curl_exec($ch);
    $err = curl_error($ch);
    curl_close($ch);
    
    if ($err) {
        file_put_contents('webhook_log.txt', date('Y-m-d H:i:s') . " - Error getting order details: " . $err . "\n", FILE_APPEND);
        return false;
    }
    
    return json_decode($response, true);
}