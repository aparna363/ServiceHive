<?php
require_once 'dbconnect.php';
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'User not logged in']);
    exit;
}

// Check if request is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

// Get form data
$booking_id = $_POST['booking_id'] ?? 0;
$provider_id = $_POST['provider_id'] ?? 0;
$service_id = $_POST['service_id'] ?? 0;
$rating = $_POST['rating'] ?? 0;
$review_text = $_POST['review_text'] ?? '';
$user_id = $_SESSION['user_id'];

// Validate data
if (!$booking_id || !$provider_id || !$service_id || !$rating) {
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
    exit;
}

if ($rating < 1 || $rating > 5) {
    echo json_encode(['success' => false, 'message' => 'Invalid rating']);
    exit;
}

// Check if booking belongs to the user
$check_query = "SELECT booking_id FROM bookings WHERE booking_id = ? AND user_id = ? AND status = 'completed'";
$stmt = $conn->prepare($check_query);
$stmt->bind_param("ii", $booking_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'You cannot review this booking']);
    exit;
}

// Check if review already exists
$check_review = "SELECT id FROM reviews WHERE booking_id = ?";
$stmt = $conn->prepare($check_review);
$stmt->bind_param("i", $booking_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    echo json_encode(['success' => false, 'message' => 'You have already reviewed this booking']);
    exit;
}

// Insert review with explicit status of 'pending'
$insert_query = "INSERT INTO reviews (booking_id, user_id, provider_id, service_id, rating, review_text, status) 
                VALUES (?, ?, ?, ?, ?, ?, 'pending')";
$stmt = $conn->prepare($insert_query);
$stmt->bind_param("iiiiss", $booking_id, $user_id, $provider_id, $service_id, $rating, $review_text);

if ($stmt->execute()) {
    // Update service provider rating (only include approved reviews in the rating)
    updateProviderRating($conn, $provider_id);
    
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to submit review']);
}

// Function to update provider rating
function updateProviderRating($conn, $provider_id) {
    // Get average rating for the provider (only approved reviews)
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