<?php
// Ensure error reporting is helpful for debugging BUT doesn't break JSON output
ini_set('display_errors', 0); // <-- Turn OFF direct display
ini_set('log_errors', 1); // Ensure errors are logged (configure error_log in php.ini if needed)
error_reporting(E_ALL); // Report all errors for logging purposes

// Start session only if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// --- Rest of your create_order.php code starts here ---
require_once 'dbconnect.php';
// REMOVED duplicate session_start();

// Set content type to JSON *after* potential session start output/errors are handled
header('Content-Type: application/json');

// Function to log debug info
function debug_log($message, $data = null) {
    $log_message = date('Y-m-d H:i:s') . ' - ' . $message;
    if ($data !== null) {
        // Use print_r for better readability of arrays/objects in logs
        $log_message .= ' - Data: ' . print_r($data, true);
    }
    error_log($log_message); // Log to the configured PHP error log
}

// Helper function to return JSON response
function return_json($success, $message, $data = []) {
    $response_data = array_merge(
        ['success' => $success, 'message' => $message],
        $data
    );
    // Log the response *before* sending it
    debug_log('Sending JSON Response', $response_data);
    echo json_encode($response_data);
    exit;
}

// Function to generate a unique reference number (defined before use)
function generateReference($type) {
    $prefix = $type === 'visit' ? 'VIS' : 'EMG';
    // Using time() and rand() is okay, but consider more robust unique ID generation if needed
    return $prefix . '-' . strtoupper(substr(md5(uniqid(rand(), true)), 0, 8));
}


try {
    // Log the start of the request processing
    debug_log('Request received', ['Method' => $_SERVER['REQUEST_METHOD'], 'Input' => file_get_contents('php://input')]);

    // Get the raw POST data
    $jsonData = file_get_contents('php://input');

    if (empty($jsonData)) {
        debug_log('No POST data received');
        return_json(false, 'No data received');
    }

    $data = json_decode($jsonData, true);
    if ($data === null && json_last_error() !== JSON_ERROR_NONE) {
        debug_log('JSON decode error', ['Error' => json_last_error_msg(), 'RawData' => $jsonData]);
        return_json(false, 'Invalid JSON data: ' . json_last_error_msg());
    }

    debug_log('Decoded input data', $data);

    // Razorpay API keys (store these securely, ideally in environment variables)
    $key_id = 'rzp_test_pM7XeD3uvgF2Or';
    $key_secret = 'pjPyycAbpchrCl4tgwUqc7V6';

    // Check if user is logged in (using session data)
    $user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
    $user_name = isset($_SESSION['username']) ? $_SESSION['username'] : null;
    $user_email = isset($_SESSION['email']) ? $_SESSION['email'] : null;
    $user_phone = isset($_SESSION['phone']) ? $_SESSION['phone'] : null; // Assuming 'phone' is stored in session

    debug_log('Session data used', [
        'user_id' => $user_id,
        'user_name' => $user_name,
        'user_email' => $user_email,
        'user_phone' => $user_phone
    ]);

    // If emergency booking and user is not logged in, get details from form data
    // Important: Ensure these form fields actually exist in the $data['formData'] array
    if (isset($data['type']) && $data['type'] === 'emergency' && !$user_id) {
        $user_name = $data['formData']['emergency_name'] ?? null;
        $user_email = $data['formData']['emergency_email'] ?? null;
        $user_phone = $data['formData']['emergency_phone'] ?? null;
        debug_log('Using emergency form data for non-logged-in user details', ['name' => $user_name, 'email' => $user_email, 'phone' => $user_phone]);
    }

    // Validate essential request data
    if (!isset($data['amount']) || !is_numeric($data['amount']) || $data['amount'] <= 0) {
        debug_log('Invalid or missing amount field', ['amount' => $data['amount'] ?? 'Not set']);
        return_json(false, 'A valid amount is required');
    }

    if (!isset($data['type']) || !in_array($data['type'], ['visit', 'emergency'])) { // Validate type
        debug_log('Invalid or missing type field', ['type' => $data['type'] ?? 'Not set']);
        return_json(false, 'A valid booking type (visit/emergency) is required');
    }

    if (!isset($data['formData']) || !is_array($data['formData'])) {
        debug_log('Missing or invalid formData field', ['formData' => $data['formData'] ?? 'Not set']);
        return_json(false, 'Form data is required');
    }

    // Check database connection ($conn should be available from dbconnect.php)
    if (!$conn || $conn->connect_error) {
        $error_msg = $conn ? $conn->connect_error : 'Database connection object not found';
        debug_log('Database connection failed', $error_msg);
        return_json(false, 'Database connection failed');
    }
    debug_log('Database connection successful');


    // Prepare booking data based on type
    $booking_reference = generateReference($data['type']);
    $amount = intval($data['amount']); // Ensure amount is integer (paisa)
    debug_log('Booking reference generated', $booking_reference);
    debug_log('Processing booking type', $data['type']);

    // Start transaction
    $conn->begin_transaction();
    debug_log('Database transaction started');

    $booking_id = null; // Initialize booking_id

    if ($data['type'] === 'visit') {
        // Extract and validate visit booking data
        $category_id = isset($data['formData']['category_id']) ? filter_var($data['formData']['category_id'], FILTER_VALIDATE_INT) : null;
        $visit_date = $data['formData']['visit_date'] ?? null; // Add validation (is valid date?)
        $visit_time = $data['formData']['visit_time'] ?? null; // Add validation (is valid time?)
        $visit_address = $data['formData']['visit_address'] ?? null;
        $visit_notes = $data['formData']['visit_notes'] ?? '';

        debug_log('Visit booking form data', [
            'category_id' => $category_id, 'visit_date' => $visit_date, 'visit_time' => $visit_time, 'visit_address' => $visit_address
        ]);

        // Validate required fields for visit
        if (!$user_id) { return_json(false, 'User must be logged in to book a visit.'); } // Visits require login
        if (!$category_id || $category_id <= 0) { return_json(false, 'Please select a valid service category'); }
        if (empty($visit_date)) { return_json(false, 'Visit date is required'); }
        if (empty($visit_time)) { return_json(false, 'Visit time is required'); }
        if (empty($visit_address)) { return_json(false, 'Visit address is required'); }
        // Add more validation for date/time format if needed

        // Insert into database
        $sql = "INSERT INTO visit_bookings (user_id, category_id, visit_date, visit_time, address, notes, reference, status, amount, payment_status)
                VALUES (?, ?, ?, ?, ?, ?, ?, 'pending', ?, 'pending')"; // Added payment_status

        $stmt = $conn->prepare($sql);
        if (!$stmt) { throw new Exception("Prepare failed (visit): " . $conn->error); }
        // Amount should be in Rupees for DECIMAL(10,2), convert paisa from request
        $amount_decimal = $amount / 100.0;
        $stmt->bind_param("iisssssd", $user_id, $category_id, $visit_date, $visit_time, $visit_address, $visit_notes, $booking_reference, $amount_decimal);

    } elseif ($data['type'] === 'emergency') {
        // Extract and validate emergency booking data
        $category_id = isset($data['formData']['category_id']) ? filter_var($data['formData']['category_id'], FILTER_VALIDATE_INT) : null;
        $emergency_address = $data['formData']['emergency_address'] ?? null;
        $emergency_issue = $data['formData']['emergency_issue'] ?? null;

        // Get name, email, phone: Prioritize form data for emergency, fallback to session
        // Use ?? to take the form data if present, otherwise use the value from session (which might be null)
        $e_name = $data['formData']['emergency_name'] ?? $user_name;
        $e_email = $data['formData']['emergency_email'] ?? $user_email;
        $e_phone = $data['formData']['emergency_phone'] ?? $user_phone; // <-- Changed: Prioritize form data

        debug_log('Emergency booking details source', [
            'form_name' => $data['formData']['emergency_name'] ?? 'N/A',
            'form_email' => $data['formData']['emergency_email'] ?? 'N/A',
            'form_phone' => $data['formData']['emergency_phone'] ?? 'N/A',
            'session_name' => $user_name,
            'session_email' => $user_email,
            'session_phone' => $user_phone,
            'final_name' => $e_name,
            'final_email' => $e_email,
            'final_phone' => $e_phone // Log the final value being used
        ]);

        // Validate required fields for emergency using the potentially updated $e_ variables
        if (!$category_id || $category_id <= 0) { return_json(false, 'Please select a valid service category'); }
        if (empty($emergency_address)) { return_json(false, 'Emergency address is required'); }
        if (empty($emergency_issue)) { return_json(false, 'Emergency issue description is required'); }
        if (empty($e_name)) { return_json(false, 'Name is required for emergency booking'); }
        if (empty($e_email) || !filter_var($e_email, FILTER_VALIDATE_EMAIL)) { return_json(false, 'A valid email address is required for emergency booking'); }
        if (empty($e_phone)) { // This check now uses the potentially form-derived phone
             return_json(false, 'Phone number is required for emergency booking');
        } // Add phone format validation if needed

        // Insert into database
        $sql = "INSERT INTO emergency_bookings (user_id, category_id, address, issue_description, name, email, phone, reference, status, amount, payment_status)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending', ?, 'pending')"; // Added payment_status, user_id can be NULL if not logged in

        $stmt = $conn->prepare($sql);
        if (!$stmt) { throw new Exception("Prepare failed (emergency): " . $conn->error); }
        // Amount should be in Rupees for DECIMAL(10,2), convert paisa from request
        $amount_decimal = $amount / 100.0;
        // Use null for user_id if not logged in
        $actual_user_id = $user_id ? $user_id : null;
        // Use the potentially updated $e_name, $e_email, $e_phone here
        $stmt->bind_param("iissssssd", $actual_user_id, $category_id, $emergency_address, $emergency_issue, $e_name, $e_email, $e_phone, $booking_reference, $amount_decimal);

    } else {
        // This case should not be reached due to earlier validation, but good to have
        throw new Exception('Invalid booking type specified');
    }

    // Execute database insertion
    debug_log('Executing database insertion', ['sql' => $sql]); // Log SQL for debugging
    if (!$stmt->execute()) {
        throw new Exception("Database insertion failed: " . $stmt->error);
    }

    $booking_id = $conn->insert_id;
    if (!$booking_id) {
        throw new Exception("Failed to get booking ID after insertion.");
    }
    debug_log('Booking created successfully', ['booking_id' => $booking_id]);
    $stmt->close(); // Close statement

    // Create Razorpay order
    debug_log('Creating Razorpay order', ['booking_id' => $booking_id, 'amount_paisa' => $amount]);
    $curl = curl_init();

    $razorpay_payload = [
        'amount' => $amount, // Amount MUST be in paisa for Razorpay API
        'currency' => 'INR',
        'receipt' => $booking_reference, // Use unique reference
        'notes' => [
            'booking_id' => $booking_id,
            'booking_type' => $data['type'], // Changed key name for clarity
            'user_id' => $user_id ?? 'guest' // Add user ID for reference
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

    $response = curl_exec($curl);
    $err = curl_error($curl);
    $info = curl_getinfo($curl);
    $http_code = $info['http_code']; // Get HTTP status code

    debug_log('Curl response info', $info);
    debug_log('Curl raw response', $response);
    if ($err) { debug_log('Curl error', $err); }

    curl_close($curl);

    if ($err || $http_code >= 400) { // Check for curl error or non-2xx HTTP status
        $error_message = $err ? 'Curl error: ' . $err : 'Razorpay API error (HTTP ' . $http_code . ')';
        $response_data = json_decode($response, true);
        if (isset($response_data['error']['description'])) {
            $error_message .= ': ' . $response_data['error']['description'];
        }
        debug_log('Razorpay order creation failed', ['message' => $error_message, 'response' => $response]);
        throw new Exception('Error creating Razorpay order: ' . $error_message); // Throw exception to trigger rollback
    }

    $order = json_decode($response, true);
    debug_log('Razorpay order response decoded', $order);

    if (!isset($order['id'])) {
        $error_desc = isset($order['error']['description']) ? $order['error']['description'] : 'Unknown error (Invalid Response Structure)';
        debug_log('No order ID in Razorpay response', ['response' => $order]);
        throw new Exception('Failed to create Razorpay order: ' . $error_desc); // Throw exception
    }

    // Update booking with Razorpay order ID
    $razorpay_order_id = $order['id'];
    debug_log('Updating booking with Razorpay order ID', ['booking_id' => $booking_id, 'order_id' => $razorpay_order_id]);
    $table_name = $data['type'] === 'visit' ? 'visit_bookings' : 'emergency_bookings';
    $update_sql = "UPDATE $table_name SET razorpay_order_id = ? WHERE id = ?";
    $update_stmt = $conn->prepare($update_sql);

    if (!$update_stmt) { throw new Exception("Prepare update statement failed: " . $conn->error); }

    $update_stmt->bind_param("si", $razorpay_order_id, $booking_id);

    if (!$update_stmt->execute()) { throw new Exception("Failed to update booking with order ID: " . $update_stmt->error); }

    $update_stmt->close(); // Close statement
    debug_log('Booking updated successfully with Razorpay order ID');

    // Commit transaction
    $conn->commit();
    debug_log('Database transaction committed');

    // Return success response with necessary details for Razorpay checkout.js
    return_json(true, 'Order created successfully', [
        'key_id' => $key_id,
        'amount' => $amount, // Amount in paisa for Razorpay.js
        'order_id' => $razorpay_order_id,
        'booking_id' => $booking_id,
        'booking_type' => $data['type'], // Send back booking type
        'user_name' => $user_name, // Prefill info
        'user_email' => $user_email, // Prefill info
        'user_phone' => $user_phone // Prefill info
    ]);

} catch (Exception $e) {
    // Rollback transaction on error
    if ($conn && $conn->ping()) { // Check if connection exists before rollback
         $conn->rollback();
         debug_log('Database transaction rolled back due to exception');
    }
    // Log the exception details
    debug_log('Exception caught', ['message' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
    // Return error in proper JSON format
    return_json(false, 'An error occurred: ' . $e->getMessage()); // Provide a user-friendly error
} finally {
    // Close connection if it was opened and is still open
    if (isset($conn) && $conn instanceof mysqli && $conn->ping()) {
        $conn->close();
        debug_log('Database connection closed');
    }
}
?>