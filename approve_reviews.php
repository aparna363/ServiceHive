<?php
session_start();
require_once 'dbconnect.php';

// Check if user is logged in as provider
if (!isset($_SESSION['provider_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

// Get the review ID
$review_id = $_POST['review_id'] ?? 0;
$provider_id = $_SESSION['provider_id'];

// Verify the review belongs to this provider
$stmt = $conn->prepare("
    SELECT id FROM reviews 
    WHERE id = ? AND provider_id = ?
");
$stmt->bind_param("ii", $review_id, $provider_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Review not found or unauthorized']);
    exit;
}

// Update the review status
$stmt = $conn->prepare("UPDATE reviews SET status = 'approved' WHERE id = ?");
$stmt->bind_param("i", $review_id);

if ($stmt->execute()) {
    // Update provider rating
    updateProviderRating($conn, $provider_id);
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to approve review']);
}

// Function to update provider rating
function updateProviderRating($conn, $provider_id) {
    // Get average rating for the provider
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
}
?>