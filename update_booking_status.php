<?php
require_once 'dbconnect.php';
session_start();

header('Content-Type: application/json');

// Check if user is logged in and is a provider
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'service_provider') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

// Get provider ID
$user_id = $_SESSION['user_id'];
$stmt = $conn->prepare("SELECT provider_id FROM service_providers WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Provider profile not found']);
    exit;
}

$provider_id = $result->fetch_assoc()['provider_id'];

// Check if required parameters are provided
if (!isset($_POST['booking_id']) || !isset($_POST['status']) || !isset($_POST['booking_type'])) {
    echo json_encode(['success' => false, 'message' => 'Missing required parameters']);
    exit;
}

$booking_id = intval($_POST['booking_id']);
$status = $_POST['status'];
$booking_type = $_POST['booking_type'];

// Validate status
$valid_statuses = ['pending', 'accepted', 'rejected', 'completed'];
if (!in_array($status, $valid_statuses)) {
    echo json_encode(['success' => false, 'message' => 'Invalid status']);
    exit;
}

try {
    // Begin transaction
    $conn->begin_transaction();
    
    // Update booking status based on booking type
    if ($booking_type === 'regular') {
        // First verify this booking belongs to this provider
        $stmt = $conn->prepare("SELECT * FROM bookings WHERE booking_id = ? AND provider_id = ?");
        $stmt->bind_param("ii", $booking_id, $provider_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            throw new Exception('Booking not found or does not belong to you');
        }
        
        // Update booking status
        $stmt = $conn->prepare("UPDATE bookings SET status = ? WHERE booking_id = ?");
        $stmt->bind_param("si", $status, $booking_id);
        if (!$stmt->execute()) {
            throw new Exception('Failed to update booking status: ' . $stmt->error);
        }
    } 
    elseif ($booking_type === 'visit') {
        // Update visit booking status
        $stmt = $conn->prepare("UPDATE visit_bookings SET status = ? WHERE id = ? AND payment_status = 'paid'");
        $stmt->bind_param("si", $status, $booking_id);
        if (!$stmt->execute()) {
            throw new Exception('Failed to update visit booking status: ' . $stmt->error);
        }
        
        // Check if any rows were affected
        if ($stmt->affected_rows === 0) {
            throw new Exception('Visit booking not found or already processed');
        }
    } 
    elseif ($booking_type === 'emergency') {
        // Update emergency booking status
        $stmt = $conn->prepare("UPDATE emergency_bookings SET status = ? WHERE id = ? AND payment_status = 'paid'");
        $stmt->bind_param("si", $status, $booking_id);
        if (!$stmt->execute()) {
            throw new Exception('Failed to update emergency booking status: ' . $stmt->error);
        }
        
        // Check if any rows were affected
        if ($stmt->affected_rows === 0) {
            throw new Exception('Emergency booking not found or already processed');
        }
    } 
    else {
        throw new Exception('Invalid booking type');
    }
    
    // Create notification for the customer
    $notification_title = '';
    $notification_message = '';
    $user_to_notify = null;
    
    // Get booking details to create appropriate notification
    if ($booking_type === 'regular') {
        $stmt = $conn->prepare("SELECT user_id, service_id FROM bookings WHERE booking_id = ?");
        $stmt->bind_param("i", $booking_id);
        $stmt->execute();
        $booking_details = $stmt->get_result()->fetch_assoc();
        $user_to_notify = $booking_details['user_id'];
        
        // Get service name
        $stmt = $conn->prepare("SELECT service_name FROM tbl_services WHERE service_id = ?");
        $stmt->bind_param("i", $booking_details['service_id']);
        $stmt->execute();
        $service_name = $stmt->get_result()->fetch_assoc()['service_name'];
    } 
    elseif ($booking_type === 'visit') {
        $stmt = $conn->prepare("SELECT user_id, category_id, reference FROM visit_bookings WHERE id = ?");
        $stmt->bind_param("i", $booking_id);
        $stmt->execute();
        $booking_details = $stmt->get_result()->fetch_assoc();
        $user_to_notify = $booking_details['user_id'];
        $reference = $booking_details['reference'];
        
        // Get category name
        $stmt = $conn->prepare("SELECT category_name FROM tbl_categories WHERE category_id = ?");
        $stmt->bind_param("i", $booking_details['category_id']);
        $stmt->execute();
        $service_name = "Technical Visit: " . $stmt->get_result()->fetch_assoc()['category_name'];
    } 
    elseif ($booking_type === 'emergency') {
        $stmt = $conn->prepare("SELECT user_id, category_id, reference, email FROM emergency_bookings WHERE id = ?");
        $stmt->bind_param("i", $booking_id);
        $stmt->execute();
        $booking_details = $stmt->get_result()->fetch_assoc();
        $user_to_notify = $booking_details['user_id'];
        $reference = $booking_details['reference'];
        
        // Get category name
        $stmt = $conn->prepare("SELECT category_name FROM tbl_categories WHERE category_id = ?");
        $stmt->bind_param("i", $booking_details['category_id']);
        $stmt->execute();
        $service_name = "Emergency Service: " . $stmt->get_result()->fetch_assoc()['category_name'];
    }
    
    // Create notification based on status
    switch ($status) {
        case 'accepted':
            $notification_title = "Booking Accepted";
            $notification_message = "Your booking for $service_name has been accepted by the service provider.";
            break;
        case 'rejected':
            $notification_title = "Booking Rejected";
            $notification_message = "Your booking for $service_name has been rejected by the service provider.";
            break;
        case 'completed':
            $notification_title = "Service Completed";
            $notification_message = "Your booking for $service_name has been marked as completed.";
            break;
    }
    
    // Insert notification if user is registered
    if ($user_to_notify) {
        $stmt = $conn->prepare("INSERT INTO notifications (user_id, title, message) VALUES (?, ?, ?)");
        $stmt->bind_param("iss", $user_to_notify, $notification_title, $notification_message);
        $stmt->execute();
    }
    
    // Commit transaction
    $conn->commit();
    
    echo json_encode([
        'success' => true, 
        'message' => 'Booking status updated successfully to ' . ucfirst($status)
    ]);
    
} catch (Exception $e) {
    // Rollback transaction on error
    $conn->rollback();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
} finally {
    $conn->close();
} 