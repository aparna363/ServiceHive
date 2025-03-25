<?php
// Enable error logging
ini_set('log_errors', 1);
ini_set('error_log', 'verification_error.log');
error_reporting(E_ALL);

session_start();
require_once 'dbconnect.php';

// Function to log debug information
function debug_log($message) {
    error_log("[" . date("Y-m-d H:i:s") . "] " . $message);
}

// Check if user is logged in and is a service provider
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'service_provider') {
    debug_log("Unauthorized access attempt: User ID: " . ($_SESSION['user_id'] ?? 'not set') . ", Role: " . ($_SESSION['role'] ?? 'not set'));
    header('Location: login.php');
    exit();
}

$user_id = $_SESSION['user_id'];

// Get provider ID
$stmt = $conn->prepare("SELECT provider_id FROM service_providers WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    debug_log("Provider not found for user ID: $user_id");
    header('Location: create_provider_profile.php');
    exit();
}

$provider_id = $result->fetch_assoc()['provider_id'];
debug_log("Processing verification for provider ID: $provider_id");

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_type = $_POST['id_type'] ?? '';
    $id_number = $_POST['id_number'] ?? '';
    
    debug_log("Form data: ID Type: $id_type, ID Number: $id_number");
    
    // Validate inputs
    if (empty($id_type) || empty($id_number)) {
        debug_log("Validation failed: Missing required fields");
        $_SESSION['verification_error'] = 'Please fill in all required fields.';
        header('Location: provider_dashboard.php');
        exit();
    }
    
    // Process file uploads
    $target_dir = "uploads/verification/";
    if (!file_exists($target_dir)) {
        mkdir($target_dir, 0777, true);
    }
    
    $upload_success = true;
    $file_paths = [];
    
    // Process ID front - Fix field name to match the form
    if (isset($_FILES['id_proof_front']) && $_FILES['id_proof_front']['error'] == 0) {
        $id_front_name = time() . '_front_' . basename($_FILES['id_proof_front']['name']);
        $id_front_path = $target_dir . $id_front_name;
        
        debug_log("Uploading ID front to: $id_front_path");
        
        if (!move_uploaded_file($_FILES['id_proof_front']['tmp_name'], $id_front_path)) {
            $upload_success = false;
            debug_log("Failed to upload ID front image");
            $_SESSION['verification_error'] = 'Failed to upload ID front image.';
        } else {
            $file_paths['id_front'] = $id_front_path;
        }
    } else {
        $upload_success = false;
        debug_log("ID front image is required. Error code: " . ($_FILES['id_proof_front']['error'] ?? 'not set'));
        $_SESSION['verification_error'] = 'ID front image is required.';
    }
    
    // Process ID back - Fix field name to match the form
    if ($upload_success && isset($_FILES['id_proof_back']) && $_FILES['id_proof_back']['error'] == 0) {
        $id_back_name = time() . '_back_' . basename($_FILES['id_proof_back']['name']);
        $id_back_path = $target_dir . $id_back_name;
        
        debug_log("Uploading ID back to: $id_back_path");
        
        if (!move_uploaded_file($_FILES['id_proof_back']['tmp_name'], $id_back_path)) {
            $upload_success = false;
            debug_log("Failed to upload ID back image");
            $_SESSION['verification_error'] = 'Failed to upload ID back image.';
        } else {
            $file_paths['id_back'] = $id_back_path;
        }
    } else {
        $upload_success = false;
        debug_log("ID back image is required. Error code: " . ($_FILES['id_proof_back']['error'] ?? 'not set'));
        $_SESSION['verification_error'] = 'ID back image is required.';
    }
    
    // Process address proof - Fix field name to match the form
    if ($upload_success && isset($_FILES['address_proof']) && $_FILES['address_proof']['error'] == 0) {
        $address_name = time() . '_address_' . basename($_FILES['address_proof']['name']);
        $address_path = $target_dir . $address_name;
        
        debug_log("Uploading address proof to: $address_path");
        
        if (!move_uploaded_file($_FILES['address_proof']['tmp_name'], $address_path)) {
            $upload_success = false;
            debug_log("Failed to upload address proof");
            $_SESSION['verification_error'] = 'Failed to upload address proof.';
        } else {
            $file_paths['address_proof'] = $address_path;
        }
    } else {
        $upload_success = false;
        debug_log("Address proof is required. Error code: " . ($_FILES['address_proof']['error'] ?? 'not set'));
        $_SESSION['verification_error'] = 'Address proof is required.';
    }
    
    // If all uploads successful, save to database
    if ($upload_success) {
        debug_log("All uploads successful, saving to database");
        
        // Check if table exists and get column information
        $check_table = $conn->query("SHOW TABLES LIKE 'verification_documents'");
        if ($check_table->num_rows == 0) {
            // Create table if it doesn't exist
            debug_log("Creating verification_documents table");
            $create_table = "CREATE TABLE verification_documents (
                doc_id INT AUTO_INCREMENT PRIMARY KEY,
                provider_id INT NOT NULL,
                id_type VARCHAR(50) NOT NULL,
                id_number VARCHAR(100) NOT NULL,
                id_proof_front VARCHAR(255) NOT NULL,
                id_proof_back VARCHAR(255) NOT NULL,
                address_proof VARCHAR(255) NOT NULL,
                uploaded_at DATETIME NOT NULL,
                status VARCHAR(20) DEFAULT 'pending',
                admin_notes TEXT,
                updated_at DATETIME
            )";
            $conn->query($create_table);
        }
        
        // Insert verification documents with the correct column names
        $stmt = $conn->prepare("
            INSERT INTO verification_documents 
            (provider_id, id_type, id_number, id_proof_front, id_proof_back, address_proof, uploaded_at) 
            VALUES (?, ?, ?, ?, ?, ?, NOW())
        ");
        $stmt->bind_param("isssss", $provider_id, $id_type, $id_number, $file_paths['id_front'], $file_paths['id_back'], $file_paths['address_proof']);
        
        if ($stmt->execute()) {
            debug_log("Verification documents saved successfully");
            
            // Update service provider status
            $stmt = $conn->prepare("
                UPDATE service_providers 
                SET status = 'pending' 
                WHERE provider_id = ?
            ");
            $stmt->bind_param("i", $provider_id);
            $stmt->execute();
            
            // Set session flags for verification pending
            $_SESSION['verification_submitted'] = true;
            $_SESSION['verification_just_submitted'] = true;
            
            // Redirect to dashboard
            header('Location: provider_dashboard.php');
            exit();
        } else {
            debug_log("Failed to save verification data: " . $conn->error);
            $_SESSION['verification_error'] = 'Failed to save verification data: ' . $conn->error;
            header('Location: provider_dashboard.php');
            exit();
        }
    } else {
        debug_log("Upload failed, redirecting back to dashboard");
        header('Location: provider_dashboard.php');
        exit();
    }
}
?> 