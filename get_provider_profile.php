<?php
require_once 'dbconnect.php';
header('Content-Type: application/json');

// Enable error reporting for debugging
ini_set('display_errors', 1);
error_reporting(E_ALL);

try {
    if (!isset($_GET['provider_id']) || empty($_GET['provider_id'])) {
        throw new Exception('Provider ID is required');
    }

    $provider_id = (int)$_GET['provider_id'];
    
    // Log incoming request
    error_log("Fetching provider profile for ID: " . $provider_id);

    // Get provider details
    $provider_query = "
        SELECT 
            sp.*,
            u.email,
            u.mobile,
            u.username
        FROM service_providers sp
        JOIN users u ON sp.user_id = u.id
        WHERE sp.provider_id = ?";
    
    $stmt = $conn->prepare($provider_query);
    if (!$stmt) {
        throw new Exception("Query preparation failed: " . $conn->error);
    }
    
    $stmt->bind_param("i", $provider_id);
    $stmt->execute();
    $provider_result = $stmt->get_result();
    
    if ($provider_result->num_rows === 0) {
        throw new Exception('Provider not found');
    }
    
    $provider = $provider_result->fetch_assoc();
    error_log("Provider data fetched: " . json_encode($provider));
    
    // Get services offered by this provider
    $services_query = "
        SELECT service_id, service_name, price, description
        FROM tbl_services
        WHERE provider_id = ? AND is_active = TRUE";
    
    $stmt = $conn->prepare($services_query);
    if (!$stmt) {
        throw new Exception("Services query preparation failed: " . $conn->error);
    }
    
    $stmt->bind_param("i", $provider_id);
    $stmt->execute();
    $services_result = $stmt->get_result();
    $services = [];
    
    while ($service = $services_result->fetch_assoc()) {
        $services[] = $service;
    }
    
    error_log("Services fetched: " . count($services));
    
    // Get reviews for this provider
    $reviews_query = "
        SELECT r.rating, r.review_text, r.created_at, u.username, 
               s.service_name
        FROM reviews r
        JOIN users u ON r.user_id = u.id
        JOIN tbl_services s ON r.service_id = s.service_id
        WHERE r.provider_id = ? AND r.status = 'approved'
        ORDER BY r.created_at DESC
        LIMIT 5";
    
    $stmt = $conn->prepare($reviews_query);
    if (!$stmt) {
        throw new Exception("Reviews query preparation failed: " . $conn->error);
    }
    
    $stmt->bind_param("i", $provider_id);
    $stmt->execute();
    $reviews_result = $stmt->get_result();
    $reviews = [];
    
    while ($review = $reviews_result->fetch_assoc()) {
        $reviews[] = $review;
    }
    
    error_log("Reviews fetched: " . count($reviews));
    
    // Ensure all values are properly encoded to prevent JSON issues
    $safe_provider = array_map(function($value) {
        // Special handling for rating to ensure it's a number
        if (is_string($value) && is_numeric($value)) {
            return floatval($value);
        }
        return is_string($value) ? htmlspecialchars($value, ENT_QUOTES, 'UTF-8') : $value;
    }, $provider);

    // Ensure rating is explicitly handled as a float
    if (isset($safe_provider['rating'])) {
        $safe_provider['rating'] = floatval($safe_provider['rating']);
    }

    // Return all data
    $response = [
        'success' => true,
        'provider' => $safe_provider,
        'services' => $services,
        'reviews' => $reviews
    ];
    
    echo json_encode($response);
    error_log("Response sent: " . json_encode(['success' => true, 'data_size' => strlen(json_encode($response))]));
    
} catch (Exception $e) {
    error_log("Error in get_provider_profile.php: " . $e->getMessage());
    echo json_encode([
        'success' => false, 
        'message' => 'Error: ' . $e->getMessage()
    ]);
} 