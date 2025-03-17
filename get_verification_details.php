<?php
session_start();
require_once 'dbconnect.php';

// Enable error reporting for debugging
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', 'verification_error.log');

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    die(json_encode(['success' => false, 'message' => 'Unauthorized access']));
}

// Check if provider_id is provided
if (!isset($_GET['provider_id']) || empty($_GET['provider_id'])) {
    die(json_encode(['success' => false, 'message' => 'Provider ID is required']));
}

$provider_id = intval($_GET['provider_id']);

try {
    // First, check if the provider exists
    $stmt = $conn->prepare("
        SELECT sp.provider_id, sp.business_name, sp.status, sp.verified_status, u.username, u.email 
        FROM service_providers sp
        JOIN users u ON sp.user_id = u.id
        WHERE sp.provider_id = ?
    ");
    $stmt->bind_param("i", $provider_id);
    $stmt->execute();
    $provider_result = $stmt->get_result();

    if ($provider_result->num_rows === 0) {
        throw new Exception('Provider not found');
    }

    $provider_data = $provider_result->fetch_assoc();

    // Now, check the structure of the verification_documents table
    $table_check = $conn->query("SHOW COLUMNS FROM verification_documents");
    $columns = [];
    while ($column = $table_check->fetch_assoc()) {
        $columns[] = $column['Field'];
    }

    // Build a dynamic query based on the actual columns
    $select_fields = [];
    $expected_fields = ['id_type', 'id_number', 'id_proof_front', 'id_proof_back', 'address_proof', 'uploaded_at'];
    
    foreach ($expected_fields as $field) {
        if (in_array($field, $columns)) {
            $select_fields[] = $field;
        }
    }
    
    if (empty($select_fields)) {
        throw new Exception('No valid fields found in verification_documents table');
    }
    
    $fields_str = implode(', ', $select_fields);
    $query = "SELECT $fields_str FROM verification_documents WHERE provider_id = ? ORDER BY uploaded_at DESC LIMIT 1";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $provider_id);
    $stmt->execute();
    $doc_result = $stmt->get_result();

    // Combine the data
    $details = $provider_data;
    $details['has_documents'] = ($doc_result->num_rows > 0);

    if ($details['has_documents']) {
        $doc_data = $doc_result->fetch_assoc();
        $details = array_merge($details, $doc_data);
        
        // Map field names if needed
        if (isset($doc_data['id_proof_front']) && !isset($details['id_front_path'])) {
            $details['id_front_path'] = $doc_data['id_proof_front'];
        }
        if (isset($doc_data['id_proof_back']) && !isset($details['id_back_path'])) {
            $details['id_back_path'] = $doc_data['id_proof_back'];
        }
        if (isset($doc_data['address_proof']) && !isset($details['address_proof_path'])) {
            $details['address_proof_path'] = $doc_data['address_proof'];
        }
    }

    // Debug output to server log
    error_log("Verification details for provider $provider_id: " . print_r($details, true));

    // Return verification details
    echo json_encode([
        'success' => true,
        'details' => $details
    ]);
    
} catch (Exception $e) {
    error_log("Error in get_verification_details.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?> 