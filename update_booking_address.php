<?php
require_once 'dbconnect.php';
session_start();

if (!isset($_SESSION['user_id']) || !isset($_POST['address'])) {
    echo json_encode(['success' => false]);
    exit;
}

$booking_id = $_GET['booking_id'];
$user_id = $_SESSION['user_id'];

// Validate and sanitize inputs
$address = trim($_POST['address']);
$city = trim($_POST['city']);
$state = trim($_POST['state']);
$district = isset($_POST['district']) ? trim($_POST['district']) : '';
$postal_code = isset($_POST['postal_code']) ? trim($_POST['postal_code']) : '';

// Check if address already exists for this user
$check_query = "SELECT id FROM service_addresses 
                WHERE user_id = ? AND address = ? AND city = ? AND state = ?";
$stmt = $conn->prepare($check_query);
if (!$stmt) {
    echo json_encode(['success' => false, 'error' => 'Database error']);
    exit;
}

$stmt->bind_param("isss", $user_id, $address, $city, $state);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    // Address exists, use existing id
    $row = $result->fetch_assoc();
    $address_id = $row['id'];
} else {
    // Create new address
    $insert_query = "INSERT INTO service_addresses (user_id, address, district, city, state, postal_code) 
                    VALUES (?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($insert_query);
    if (!$stmt) {
        echo json_encode(['success' => false, 'error' => 'Database error']);
        exit;
    }
    
    $stmt->bind_param("isssss", $user_id, $address, $district, $city, $state, $postal_code);
    if (!$stmt->execute()) {
        echo json_encode(['success' => false, 'error' => 'Failed to save address']);
        exit;
    }
    
    $address_id = $conn->insert_id;
}

// Update booking with address_id
$update_booking = "UPDATE bookings SET address_id = ? WHERE booking_id = ? AND user_id = ?";
$stmt = $conn->prepare($update_booking);
if (!$stmt) {
    echo json_encode(['success' => false, 'error' => 'Database error']);
    exit;
}

$stmt->bind_param("iii", $address_id, $booking_id, $user_id);
$success = $stmt->execute();

echo json_encode(['success' => $success]); 