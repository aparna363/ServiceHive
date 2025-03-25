<?php
require_once 'dbconnect.php';
session_start();

// Check if user is logged in as a service provider
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'service_provider') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

// Check if request is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

// Get data
$review_id = $_POST['review_id'] ?? 0;
$status = $_POST['status'] ?? '';

// Validate data
if (!$review_id || !in_array($status, ['approved', 'rejected'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid data provided']);
    exit;
}

// Get provider ID
$provider_id = 0;
$stmt = $conn->prepare("SELECT provider_id FROM service_providers WHERE user_id = ?");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$result = $stmt->get_result();
if ($row = $result->fetch_assoc()) {
    $provider_id = $row['provider_id'];
}

// Check if review belongs to the provider
$check_query = "SELECT id FROM reviews WHERE id = ? AND provider_id = ?";
$stmt = $conn->prepare($check_query);
$stmt->bind_param("ii", $review_id, $provider_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'You cannot update this review']);
    exit;
}

// Update review status
$update_query = "UPDATE reviews SET status = ? WHERE id = ?";
$stmt = $conn->prepare($update_query);
$stmt->bind_param("si", $status, $review_id);

if ($stmt->execute()) {
    // Update provider rating (only include approved reviews in the rating)
    $query = "SELECT AVG(rating) as avg_rating, COUNT(*) as total_reviews 
              FROM reviews 
              WHERE provider_id = ? AND status = 'approved'";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $provider_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    
    $avg_rating = $row['avg_rating'] ?? 0;
    $total_reviews = $row['total_reviews'] ?? 0;
    
    // Update provider table
    $update = "UPDATE service_providers 
               SET rating = ?, total_reviews = ? 
               WHERE provider_id = ?";
    $stmt = $conn->prepare($update);
    $stmt->bind_param("dii", $avg_rating, $total_reviews, $provider_id);
    $stmt->execute();
    
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to update review status']);
}
?> 