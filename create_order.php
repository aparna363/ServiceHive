<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Enable error logging to a file
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_log("", 3, "payment_error.log"); // Clear the log first
ini_set('error_log', 'payment_error.log');

// Include database connection
require_once 'dbconnect.php';

// Set content type to JSON
header('Content-Type: application/json');

// Function to log debug info
function debug_log($message, $data = null) {
    $log = date('Y-m-d H:i:s') . ' - ' . $message;
    if ($data !== null) {
        $log .= ' - ' . (is_array($data) || is_object($data) ? json_encode($data) : $data);
    }
    error_log($log);
}

// Helper function to return JSON response
function return_json($success, $message, $data = []) {
    file_put_contents('debug.log', "About to send response: " . json_encode(array_merge(
        ['success' => $success, 'message' => $message],
        $data
    )) . "\n", FILE_APPEND);
    echo json_encode(array_merge(
        ['success' => $success, 'message' => $message],
        $data
    ));
    exit;
}

try {
    file_put_contents('debug.log', "Request received: " . file_get_contents('php://input') . "\n", FILE_APPEND);

    debug_log('Payment request received');

    // Get the raw POST data
    $jsonData = file_get_contents('php://input');
    debug_log('Raw input data', $jsonData);

    if (empty($jsonData)) {
        debug_log('No POST data received');
        return_json(false, 'No data received');
    }

    $data = json_decode($jsonData, true);
    if ($data === null && json_last_error() !== JSON_ERROR_NONE) {
        debug_log('JSON decode error', json_last_error_msg());
        return_json(false, 'Invalid JSON data: ' . json_last_error_msg());
    }

    debug_log('Decoded data', $data);

    // Razorpay API keys (store these securely, ideally in environment variables)
    $key_id = 'rzp_test_pM7XeD3uvgF2Or';
    $key_secret = 'pjPyycAbpchrCl4tgwUqc7V6';

    // Check if user is logged in
    $user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
    $user_name = isset($_SESSION['username']) ? $_SESSION['username'] : null;
    $user_email = isset($_SESSION['email']) ? $_SESSION['email'] : null;
    $user_phone = isset($_SESSION['phone']) ? $_SESSION['phone'] : null;

    debug_log('Session data', [
        'user_id' => $user_id,
        'user_name' => $user_name,
        'user_email' => $user_email,
        'user_phone' => $user_phone
    ]);

    // If emergency booking and user is not logged in, get details from form
    if (isset($data['type']) && $data['type'] === 'emergency' && !$user_id) {
        $user_name = $data['formData']['emergency_name'] ?? null;
        $user_email = $data['formData']['emergency_email'] ?? null;
        $user_phone = $data['formData']['emergency_phone'] ?? null;
        debug_log('Using emergency form data for user details');
    }

    // Validate request
    if (!isset($data['amount'])) {
        debug_log('Missing amount field');
        return_json(false, 'Amount is required');
    }
    
    if (!isset($data['type'])) {
        debug_log('Missing type field');
        return_json(false, 'Booking type is required');
    }
    
    if (!isset($data['formData'])) {
        debug_log('Missing formData field');
        return_json(false, 'Form data is required');
    }

    // Check connection before proceeding
    if ($conn->connect_error) {
        debug_log('Database connection failed', $conn->connect_error);
        return_json(false, 'Database connection failed');
    }

    // Prepare booking data based on type
    $booking_reference = generateReference($data['type']);
    $amount = $data['amount']; // In paisa
    debug_log('Booking reference generated', $booking_reference);

    // Create booking record in database
    debug_log('Processing booking type', $data['type']);
    
    if ($data['type'] === 'visit') {
        // For visit booking
        $category_id = isset($data['formData']['category_id']) ? intval($data['formData']['category_id']) : null;
        $visit_date = $data['formData']['visit_date'] ?? null;
        $visit_time = $data['formData']['visit_time'] ?? null;
        $visit_address = $data['formData']['visit_address'] ?? null;
        $visit_notes = $data['formData']['visit_notes'] ?? '';
        
        debug_log('Visit booking data', [
            'category_id' => $category_id,
            'visit_date' => $visit_date,
            'visit_time' => $visit_time,
            'visit_address' => $visit_address
        ]);
        
        // Validate required fields
        if (!$category_id || $category_id <= 0) {
            debug_log('Invalid category_id', $category_id);
            return_json(false, 'Please select a valid service category');
        }
        
        if (empty($visit_date)) {
            debug_log('Missing visit_date');
            return_json(false, 'Visit date is required');
        }
        
        if (empty($visit_time)) {
            debug_log('Missing visit_time');
            return_json(false, 'Visit time is required');
        }
        
        if (empty($visit_address)) {
            debug_log('Missing visit_address');
            return_json(false, 'Visit address is required');
        }
        
        // Insert into database - use safe_query helper
        debug_log('Preparing to insert visit booking');
        $sql = "INSERT INTO visit_bookings (user_id, category_id, visit_date, visit_time, address, notes, reference, status, amount) 
                VALUES (?, ?, ?, ?, ?, ?, ?, 'pending', ?)";
                
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            debug_log('Prepare statement failed', $conn->error);
            return_json(false, 'Database error: ' . $conn->error);
        }
        
        $stmt->bind_param("iissssi", $user_id, $category_id, $visit_date, $visit_time, $visit_address, $visit_notes, $booking_reference, $amount);
        
    } else if ($data['type'] === 'emergency') {
        // For emergency booking
        $category_id = isset($data['formData']['category_id']) ? intval($data['formData']['category_id']) : null;
        $emergency_address = $data['formData']['emergency_address'] ?? null;
        $emergency_issue = $data['formData']['emergency_issue'] ?? null;
        
        debug_log('Emergency booking data', [
            'category_id' => $category_id,
            'emergency_address' => $emergency_address,
            'emergency_issue' => $emergency_issue
        ]);
        
        // Validate required fields
        if (!$category_id || $category_id <= 0) {
            debug_log('Invalid category_id for emergency', $category_id);
            return_json(false, 'Please select a valid service category');
        }
        
        if (empty($emergency_address)) {
            debug_log('Missing emergency_address');
            return_json(false, 'Emergency address is required');
        }
        
        if (empty($emergency_issue)) {
            debug_log('Missing emergency_issue');
            return_json(false, 'Emergency issue description is required');
        }
        
        // Insert into database
        debug_log('Preparing to insert emergency booking');
        $sql = "INSERT INTO emergency_bookings (user_id, category_id, address, issue_description, name, email, phone, reference, status, amount) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending', ?)";
                
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            debug_log('Prepare statement failed for emergency', $conn->error);
            return_json(false, 'Database error: ' . $conn->error);
        }
        
        $stmt->bind_param("iisssssi", $user_id, $category_id, $emergency_address, $emergency_issue, $user_name, $user_email, $user_phone, $booking_reference, $amount);
    } else {
        debug_log('Invalid booking type', $data['type']);
        return_json(false, 'Invalid booking type');
    }

    // Execute database insertion
    debug_log('Executing database insertion');
    if (!$stmt->execute()) {
        debug_log('Database insertion failed', $stmt->error);
        return_json(false, 'Failed to create booking: ' . $stmt->error);
    }

    $booking_id = $conn->insert_id;
    debug_log('Booking created with ID', $booking_id);

    // Create Razorpay order
    debug_log('Creating Razorpay order');
    $curl = curl_init();

    $razorpay_payload = [
        'amount' => $amount,
        'currency' => 'INR',
        'receipt' => $booking_reference,
        'notes' => [
            'booking_id' => $booking_id,
            'type' => $data['type']
        ]
    ];
    
    debug_log('Razorpay payload', $razorpay_payload);

    curl_setopt_array($curl, array(
        CURLOPT_URL => 'https://api.razorpay.com/v1/orders',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => 'POST',
        CURLOPT_POSTFIELDS => json_encode($razorpay_payload),
        CURLOPT_HTTPHEADER => array(
            'Authorization: Basic ' . base64_encode($key_id . ':' . $key_secret),
            'Content-Type: application/json'
        ),
    ));

    debug_log('Executing curl request to Razorpay');
    $response = curl_exec($curl);
    $err = curl_error($curl);
    $info = curl_getinfo($curl);
    
    debug_log('Curl response info', $info);
    debug_log('Curl response', $response);
    
    if ($err) {
        debug_log('Curl error', $err);
    }

    curl_close($curl);

    if ($err) {
        debug_log('Razorpay API error', $err);
        return_json(false, 'Error creating Razorpay order: ' . $err);
    }

    $order = json_decode($response, true);
    debug_log('Razorpay order response', $order);

    if (!isset($order['id'])) {
        debug_log('No order ID in response', $order);
        $error_desc = isset($order['error']) ? $order['error']['description'] : 'Unknown error';
        return_json(false, 'Failed to create Razorpay order: ' . $error_desc);
    }

    // Update booking with Razorpay order ID
    debug_log('Updating booking with order ID', $order['id']);
    $table_name = $data['type'] === 'visit' ? 'visit_bookings' : 'emergency_bookings';
    $update_sql = "UPDATE $table_name SET razorpay_order_id = ? WHERE id = ?";
    $update_stmt = $conn->prepare($update_sql);
    
    if (!$update_stmt) {
        debug_log('Prepare update statement failed', $conn->error);
        return_json(false, 'Database error: ' . $conn->error);
    }
    
    $update_stmt->bind_param("si", $order['id'], $booking_id);
    
    if (!$update_stmt->execute()) {
        debug_log('Update booking failed', $update_stmt->error);
        return_json(false, 'Failed to update booking with order ID: ' . $update_stmt->error);
    }

    // Return success response
    debug_log('Payment order created successfully', $order['id']);
    return_json(true, 'Order created successfully', [
        'key_id' => $key_id,
        'amount' => $amount,
        'order_id' => $order['id'],
        'booking_id' => $booking_id,
        'user_name' => $user_name,
        'user_email' => $user_email,
        'user_phone' => $user_phone
    ]);

} catch (Exception $e) {
    // Return error in proper JSON format
    debug_log('Exception caught', $e->getMessage());
    debug_log('Exception trace', $e->getTraceAsString());
    file_put_contents('debug.log', "Error: " . $e->getMessage() . "\n" . $e->getTraceAsString() . "\n", FILE_APPEND);
    return_json(false, $e->getMessage());
}

// Function to generate a unique reference number
function generateReference($type) {
    $prefix = $type === 'visit' ? 'VIS' : 'EMG';
    return $prefix . '-' . strtoupper(substr(md5(time() . rand(1000, 9999)), 0, 8));
}
?>