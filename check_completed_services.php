<?php
require_once 'dbconnect.php';
session_start();

// For debugging, remove in production
header('Content-Type: application/json');
$response = ['success' => false, 'message' => 'Unknown error'];

// Log errors for debugging
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    $response = ['success' => false, 'message' => 'User not logged in'];
    echo json_encode($response);
    exit;
}

try {
    $user_id = $_SESSION['user_id'];
    
    // Find completed bookings without reviews
    $query = "SELECT 
        b.booking_id, 
        b.service_id, 
        b.provider_id,
        b.booking_date, 
        s.service_name,
        sp.business_name as provider_name
        FROM bookings b
        JOIN tbl_services s ON b.service_id = s.service_id
        JOIN service_providers sp ON b.provider_id = sp.provider_id
        LEFT JOIN reviews r ON b.booking_id = r.booking_id
        WHERE b.user_id = ? 
        AND b.status = 'completed' 
        AND b.payment_status = 'paid'
        AND r.id IS NULL
        ORDER BY b.booking_date DESC
        LIMIT 1";

    $stmt = $conn->prepare($query);
    
    if (!$stmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }
    
    $stmt->bind_param("i", $user_id);
    
    if (!$stmt->execute()) {
        throw new Exception("Execute failed: " . $stmt->error);
    }
    
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $booking = $result->fetch_assoc();
        $response = [
            'success' => true,
            'needsReview' => true,
            'booking_id' => $booking['booking_id'],
            'service_id' => $booking['service_id'],
            'provider_id' => $booking['provider_id'],
            'service_name' => $booking['service_name'],
            'provider_name' => $booking['provider_name'],
            'booking_date' => date('d M Y', strtotime($booking['booking_date'])),
            'debug_info' => 'Found completed service needing review'
        ];
    } else {
        // For testing purposes, you can force a review to appear by uncommenting these lines
        /*
        $response = [
            'success' => true,
            'needsReview' => true,
            'booking_id' => 123,
            'service_id' => 456,
            'provider_id' => 789,
            'service_name' => 'Test Service',
            'provider_name' => 'Test Provider',
            'booking_date' => date('d M Y'),
            'debug_info' => 'Using test data'
        ];
        */
        
        $response = [
            'success' => true,
            'needsReview' => false,
            'debug_info' => 'No completed services found or all already reviewed'
        ];
    }
} catch (Exception $e) {
    $response = [
        'success' => false, 
        'message' => $e->getMessage(),
        'debug_info' => 'Exception occurred in processing'
    ];
}

echo json_encode($response);
exit; 