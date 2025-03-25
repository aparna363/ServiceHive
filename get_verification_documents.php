<?php
// Make sure there's no whitespace before the opening PHP tag
// Enable error reporting for debugging but don't display errors
error_reporting(E_ALL);
ini_set('display_errors', 0);

session_start();
// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

// Database connection
require_once 'dbconnect.php';

// Check if provider_id is provided
if (!isset($_GET['provider_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Provider ID is required']);
    exit();
}

$provider_id = intval($_GET['provider_id']);

try {
    // Test database connection
    if (!$conn) {
        throw new Exception("Database connection failed");
    }
    
    // Build the query with corrected column names and JOIN conditions
    // This query allows finding provider by either provider_id OR user_id
    $query = "SELECT sp.provider_id, sp.user_id, sp.business_name, sp.status,
              u.username, u.email,
              vd.id_type, vd.id_number, vd.id_proof_front, vd.id_proof_back, vd.address_proof, vd.uploaded_at
              FROM service_providers sp
              JOIN users u ON sp.user_id = u.id
              LEFT JOIN verification_documents vd ON sp.provider_id = vd.provider_id
              WHERE sp.provider_id = ? OR sp.user_id = ?";
    
    // Prepare statement
    $stmt = $conn->prepare($query);
    if (!$stmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }
    
    // Bind parameter for both provider_id and user_id
    $stmt->bind_param('ii', $provider_id, $provider_id);
    
    // Execute query
    if (!$stmt->execute()) {
        throw new Exception("Execute failed: " . $stmt->error);
    }
    
    // Get results
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        throw new Exception("No provider found with ID: $provider_id");
    }
    
    // Fetch data
    $provider = $result->fetch_assoc();
    
    // Check if this provider has verification documents
    $has_documents = !is_null($provider['id_type']);
    
    // Prepare the response
    $response = [
        'success' => true,
        'details' => [
            'provider_id' => $provider['provider_id'],
            'user_id' => $provider['user_id'],
            'username' => $provider['username'],
            'email' => $provider['email'],
            'business_name' => $provider['business_name'],
            'status' => $provider['status'],
            'has_documents' => $has_documents
        ]
    ];
    
    // Add document details if they exist
    if ($has_documents) {
        // Map database field names to what the JavaScript expects
        $response['details'] = array_merge($response['details'], [
            'id_type' => $provider['id_type'],
            'id_number' => $provider['id_number'],
            // Map the field names to match what the JavaScript is expecting
            'id_front_path' => $provider['id_proof_front'],
            'id_back_path' => $provider['id_proof_back'],
            'address_proof_path' => $provider['address_proof'],
            'uploaded_at' => $provider['uploaded_at']
        ]);
    }
    
    // Return the response as JSON
    header('Content-Type: application/json');
    echo json_encode($response);
    
} catch (Exception $e) {
    // Return a proper JSON error response
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false, 
        'message' => $e->getMessage()
    ]);
}
?> 