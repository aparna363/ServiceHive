<?php
require 'dbconnect.php'; // Ensure this file is included to use the database connection

function generateRazorpayOrder($conn, $booking_id, $amount) {
    $api_key = 'rzp_test_pM7XeD3uvgF2Or';
    $api_secret = 'pjPyycAbpchrCl4tgwUqc7V6';
    
    $url = 'https://api.razorpay.com/v1/orders';
    $data = [
        'amount' => $amount * 100, // Amount in paise
        'currency' => 'INR',
        'receipt' => 'rcpt_' . $booking_id,
        'payment_capture' => 1
    ];
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_USERPWD, $api_key . ':' . $api_secret);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    
    $response = curl_exec($ch);
    $err = curl_error($ch);
    curl_close($ch);
    
    if ($err) {
        return ['success' => false, 'error' => $err];
    }
    
    return ['success' => true, 'data' => json_decode($response, true)];
}

function updatePaymentStatus($conn, $booking_id, $payment_id, $status) {
    $query = "UPDATE payments SET 
              transaction_id = ?, 
              status = ?,
              updated_at = CURRENT_TIMESTAMP 
              WHERE booking_id = ?";
              
    $stmt = $conn->prepare($query);
    if (!$stmt) {
        return false;
    }
    
    $stmt->bind_param("ssi", $payment_id, $status, $booking_id);
    return $stmt->execute();
}

// Example usage
$booking_id = $_GET['booking_id']; // Get booking ID from request
$amount = 299.00; // Example amount for emergency booking

$order = generateRazorpayOrder($conn, $booking_id, $amount);

if ($order['success']) {
    $order_id = $order['data']['id'];
    // Redirect to Razorpay checkout page or render Razorpay checkout form
    echo "Order created successfully. Order ID: " . $order_id;
    // You can now use this order ID to initiate the payment on the client side using Razorpay's checkout script
} else {
    echo "Error creating order: " . $order['error'];
}
?> 