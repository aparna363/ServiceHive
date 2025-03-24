<?php
require_once 'dbconnect.php';
session_start();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

function generateReference($prefix) {
    return $prefix . strtoupper(substr(uniqid(), -6));
}

try {
    $data = $_POST;
    $booking_type = isset($data['action']) ? $data['action'] : '';

    if ($booking_type === 'book_visit') {
        // Handle Visit Booking
        $visit_reference = generateReference('VST');
        $user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 0;
        $category_id = $data['category_id'];
        
        // Get available provider for the category
        $provider_query = "SELECT provider_id FROM service_providers 
                          WHERE category_id = ? AND status = 'approved' 
                          ORDER BY RAND() LIMIT 1";
        $stmt = $conn->prepare($provider_query);
        $stmt->bind_param("i", $category_id);
        $stmt->execute();
        $provider_result = $stmt->get_result()->fetch_assoc();
        
        if (!$provider_result) {
            throw new Exception('No service provider available for this category');
        }
        
        $provider_id = $provider_result['provider_id'];
        $visit_date = $data['visit_date'];
        $visit_time = $data['visit_time'];
        $address = $data['visit_address'];
        $notes = $data['visit_notes'] ?? '';
        $visit_fee = 99.00; // Default visit fee

        $query = "INSERT INTO visit_bookings 
                 (visit_reference, user_id, provider_id, category_id, visit_date, 
                  visit_time, address, notes, visit_fee) 
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = $conn->prepare($query);
        $stmt->bind_param("siiissssd", 
            $visit_reference, $user_id, $provider_id, $category_id,
            $visit_date, $visit_time, $address, $notes, $visit_fee
        );

        if ($stmt->execute()) {
            $visit_id = $conn->insert_id;
            
            // Create Razorpay order if online payment
            if (isset($data['payment_method']) && $data['payment_method'] === 'online') {
                $order = generateRazorpayOrder($conn, $visit_id, $visit_fee);
                if ($order['success']) {
                    echo json_encode([
                        'success' => true,
                        'visit_reference' => $visit_reference,
                        'requires_payment' => true,
                        'order_id' => $order['data']['id'],
                        'amount' => $visit_fee
                    ]);
                } else {
                    // Delete the visit booking if payment creation fails
                    $delete_query = "DELETE FROM visit_bookings WHERE visit_id = ?";
                    $delete_stmt = $conn->prepare($delete_query);
                    $delete_stmt->bind_param("i", $visit_id);
                    $delete_stmt->execute();
                    
                    throw new Exception($order['message'] ?? 'Payment initialization failed. Please try again.');
                }
            } else {
                echo json_encode([
                    'success' => true,
                    'visit_reference' => $visit_reference,
                    'requires_payment' => false
                ]);
            }
        } else {
            throw new Exception('Failed to book visit');
        }

    } elseif ($booking_type === 'book_emergency') {
        // Handle Emergency Booking
        $emergency_reference = generateReference('EMG');
        $category_id = $data['category_id'];
        
        // Get available provider
        $provider_query = "SELECT provider_id FROM service_providers 
                          WHERE category_id = ? AND status = 'approved' 
                          ORDER BY RAND() LIMIT 1";
        $stmt = $conn->prepare($provider_query);
        $stmt->bind_param("i", $category_id);
        $stmt->execute();
        $provider_result = $stmt->get_result()->fetch_assoc();
        
        if (!$provider_result) {
            throw new Exception('No service provider available for this category');
        }
        
        $provider_id = $provider_result['provider_id'];
        $customer_name = $data['emergency_name'];
        $customer_phone = $data['emergency_phone'];
        $customer_email = $data['emergency_email'];
        $address = $data['emergency_address'];
        $issue_description = $data['emergency_issue'];
        $emergency_fee = 299.00; // Default emergency fee

        $query = "INSERT INTO emergency_bookings 
                 (emergency_reference, provider_id, category_id, customer_name, 
                  customer_phone, customer_email, address, issue_description, emergency_fee) 
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = $conn->prepare($query);
        $stmt->bind_param("siisssssd", 
            $emergency_reference, $provider_id, $category_id, $customer_name,
            $customer_phone, $customer_email, $address, $issue_description, $emergency_fee
        );

        if ($stmt->execute()) {
            $emergency_id = $conn->insert_id;
            
            // Create Razorpay order if online payment
            if (isset($data['payment_method']) && $data['payment_method'] === 'online') {
                $order = generateRazorpayOrder($conn, $emergency_id, $emergency_fee);
                if ($order['success']) {
                    echo json_encode([
                        'success' => true,
                        'emergency_reference' => $emergency_reference,
                        'requires_payment' => true,
                        'order_id' => $order['data']['id'],
                        'amount' => $emergency_fee
                    ]);
                } else {
                    // Delete the emergency booking if payment creation fails
                    $delete_query = "DELETE FROM emergency_bookings WHERE emergency_id = ?";
                    $delete_stmt = $conn->prepare($delete_query);
                    $delete_stmt->bind_param("i", $emergency_id);
                    $delete_stmt->execute();
                    
                    throw new Exception($order['message'] ?? 'Payment initialization failed. Please try again.');
                }
            } else {
                echo json_encode([
                    'success' => true,
                    'emergency_reference' => $emergency_reference,
                    'requires_payment' => false
                ]);
            }
        } else {
            throw new Exception('Failed to book emergency service');
        }

    } else {
        throw new Exception('Invalid booking type');
    }

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>