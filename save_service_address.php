<?php
require_once 'dbconnect.php';
session_start();

header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'User not logged in']);
    exit;
}

// Validate required fields
$required_fields = ['address', 'district', 'city', 'state', 'postal_code'];
foreach ($required_fields as $field) {
    if (!isset($_POST[$field]) || empty($_POST[$field])) {
        echo json_encode(['success' => false, 'message' => 'Missing required field: ' . $field]);
        exit;
    }
}

$user_id = $_SESSION['user_id'];
$address = $_POST['address'];
$district = $_POST['district'];
$city = $_POST['city'];
$state = $_POST['state'];
$postal_code = $_POST['postal_code'];
$is_default = isset($_POST['is_default']) ? 1 : 0;

try {
    // Start transaction
    $conn->begin_transaction();
    
    // If this address is set as default, unset any existing default addresses
    if ($is_default) {
        $unset_default_query = "UPDATE service_addresses SET is_default = 0 WHERE user_id = ?";
        $stmt = $conn->prepare($unset_default_query);
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
    }
    
    // Insert the new address
    $insert_query = "INSERT INTO service_addresses (user_id, address, district, city, state, postal_code, is_default) 
                    VALUES (?, ?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($insert_query);
    $stmt->bind_param("isssssi", $user_id, $address, $district, $city, $state, $postal_code, $is_default);
    $stmt->execute();
    
    $address_id = $conn->insert_id;
    
    // Commit transaction
    $conn->commit();
    
    // Store address in session for use during checkout
    $_SESSION['service_address'] = [
        'id' => $address_id,
        'address' => $address,
        'district' => $district,
        'city' => $city,
        'state' => $state,
        'postal_code' => $postal_code,
        'is_default' => $is_default
    ];
    
    // Get user details for display
    $user_query = "SELECT username, email, mobile FROM users WHERE id = ?";
    $stmt = $conn->prepare($user_query);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $user_result = $stmt->get_result();
    $user_data = $user_result->fetch_assoc();
    
    echo json_encode([
        'success' => true, 
        'address' => $_SESSION['service_address'],
        'username' => $user_data['username'],
        'email' => $user_data['email'],
        'mobile' => $user_data['mobile']
    ]);
    
} catch (Exception $e) {
    // Rollback on error
    $conn->rollback();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

$conn->close(); 