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

// Update user address
$update_query = "UPDATE users SET 
                address = ?,
                city = ?,
                state = ?
                WHERE id = ?";

$stmt = $conn->prepare($update_query);
if (!$stmt) {
    echo json_encode(['success' => false, 'error' => 'Database error']);
    exit;
}

$stmt->bind_param("sssi", $address, $city, $state, $user_id);
$success = $stmt->execute();

echo json_encode(['success' => $success]); 