<?php
session_start();
require_once 'dbconnect.php';

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

// Handle request
$response = ['success' => false, 'message' => 'Invalid request'];

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['provider_id'])) {
        $provider_id = intval($_POST['provider_id']);
        
        if (isset($_POST['approve_provider'])) {
            // Include your approveServiceProvider function or redefine it here
            require_once 'admin.php'; // This includes the function if it's defined there
            
            if (approveServiceProvider($conn, $provider_id)) {
                $response = ['success' => true, 'message' => 'Service provider approved successfully'];
            } else {
                $response = ['success' => false, 'message' => 'Failed to approve service provider'];
            }
        } elseif (isset($_POST['reject_provider'])) {
            // Include your rejectServiceProvider function or redefine it here
            require_once 'admin.php'; // This includes the function if it's defined there
            
            if (rejectServiceProvider($conn, $provider_id)) {
                $response = ['success' => true, 'message' => 'Service provider rejected successfully'];
            } else {
                $response = ['success' => false, 'message' => 'Failed to reject service provider'];
            }
        }
    }
}

// Send response
header('Content-Type: application/json');
echo json_encode($response);
exit(); 