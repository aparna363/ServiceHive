<?php
session_start();
require_once 'dbconnect.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

// Check if notification ID is provided
if (!isset($_POST['id']) && !isset($_GET['id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Missing notification ID']);
    exit();
}

$notification_id = isset($_POST['id']) ? intval($_POST['id']) : intval($_GET['id']);
$user_id = $_SESSION['user_id'];

try {
    // Mark notification as read
    $stmt = $conn->prepare("
        UPDATE notifications 
        SET is_read = 1 
        WHERE id = ? AND user_id = ?
    ");
    $stmt->bind_param("ii", $notification_id, $user_id);
    $stmt->execute();
    
    if ($stmt->affected_rows > 0) {
        header('Content-Type: application/json');
        echo json_encode(['success' => true]);
    } else {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Notification not found or already read']);
    }
} catch (Exception $e) {
    error_log("Error in mark_notification_read.php: " . $e->getMessage());
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?> 