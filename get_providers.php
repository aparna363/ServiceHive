<?php
// get_providers.php
session_start();
require_once 'dbconnect.php';

// Get category ID from request
$category_id = isset($_GET['category_id']) ? intval($_GET['category_id']) : 0;

if (!$category_id) {
    echo json_encode(['success' => false, 'message' => 'Invalid category ID']);
    exit;
}

// Check if providers exist for this category
$check_query = "
    SELECT COUNT(*) as provider_count
    FROM service_providers sp
    WHERE sp.category_id = ? AND sp.status = 'approved'
";

try {
    $check_stmt = $conn->prepare($check_query);
    if (!$check_stmt) {
        throw new Exception("Prepare check query failed: " . $conn->error);
    }
    
    $check_stmt->bind_param('i', $category_id);
    
    if (!$check_stmt->execute()) {
        throw new Exception("Execute check query failed: " . $check_stmt->error);
    }
    
    $check_result = $check_stmt->get_result();
    $check_row = $check_result->fetch_assoc();
    
    // If no providers exist, return early
    if ($check_row['provider_count'] == 0) {
        echo json_encode([
            'success' => true,
            'providers' => [],
            'debug' => 'No providers found for this category'
        ]);
        exit;
    }
    
    // If providers exist, fetch them with user details
    $query = "
        SELECT 
            sp.provider_id,
            u.username as name,
            sp.rating,
            COALESCE((SELECT COUNT(*) FROM bookings b WHERE b.provider_id = sp.provider_id AND b.status = 'completed'), 0) as completed_jobs
        FROM 
            service_providers sp
        JOIN 
            users u ON sp.user_id = u.id
        WHERE 
            sp.category_id = ? AND sp.status = 'approved'
        ORDER BY 
            sp.rating DESC, completed_jobs DESC
        LIMIT 10
    ";
    
    $stmt = $conn->prepare($query);
    if (!$stmt) {
        throw new Exception("Prepare query failed: " . $conn->error);
    }
    
    $stmt->bind_param('i', $category_id);
    
    if (!$stmt->execute()) {
        throw new Exception("Execute query failed: " . $stmt->error);
    }
    
    $result = $stmt->get_result();
    
    $providers = [];
    while ($row = $result->fetch_assoc()) {
        // Format the rating to one decimal place if it's not zero
        if ($row['rating'] > 0) {
            $row['rating'] = number_format($row['rating'], 1);
        } else {
            $row['rating'] = "New";
        }
        $providers[] = $row;
    }
    
    echo json_encode([
        'success' => true,
        'providers' => $providers
    ]);
    
} catch (Exception $e) {
    // Log the error
    error_log("Provider fetch error: " . $e->getMessage());
    
    echo json_encode([
        'success' => false,
        'message' => 'Error fetching providers: ' . $e->getMessage()
    ]);
}
?>
