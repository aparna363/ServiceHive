<?php
// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Include database connection
require_once 'dbconnect.php';

// Set headers
header('Content-Type: application/json');

// Initialize response array
$response = [
    'success' => false,
    'results' => [],
    'error' => null
];

try {
    // Get search query
    $query = isset($_GET['query']) ? trim($_GET['query']) : '';

    if (empty($query)) {
        // Return popular services if no query
        $sql = "SELECT 
                s.service_id,
                s.service_name,
                s.description,
                s.price,
                c.category_name,
                COALESCE(AVG(r.rating), 0) as avg_rating
            FROM tbl_services s
            JOIN tbl_categories c ON s.category_id = c.category_id
            LEFT JOIN tbl_reviews r ON s.service_id = r.service_id
            WHERE s.is_active = TRUE
            GROUP BY s.service_id
            ORDER BY avg_rating DESC
            LIMIT 10";
        
        $result = $conn->query($sql);
        
        if ($result === false) {
            throw new Exception($conn->error);
        }
        
        while ($row = $result->fetch_assoc()) {
            $response['results'][] = [
                'service_id' => $row['service_id'],
                'service_name' => $row['service_name'],
                'description' => $row['description'],
                'price' => $row['price'],
                'category_name' => $row['category_name'],
                'avg_rating' => number_format($row['avg_rating'], 1)
            ];
        }
    } else {
        // Search query
        $sql = "SELECT 
                s.service_id,
                s.service_name,
                s.description,
                s.price,
                c.category_name,
                COALESCE(AVG(r.rating), 0) as avg_rating
            FROM tbl_services s
            JOIN tbl_categories c ON s.category_id = c.category_id
            LEFT JOIN tbl_reviews r ON s.service_id = r.service_id
            WHERE s.is_active = TRUE 
            AND (
                LOWER(s.service_name) LIKE LOWER(?) OR
                LOWER(s.description) LIKE LOWER(?) OR
                LOWER(c.category_name) LIKE LOWER(?)
            )
            GROUP BY s.service_id
            ORDER BY 
                CASE 
                    WHEN LOWER(s.service_name) LIKE LOWER(?) THEN 1
                    WHEN LOWER(c.category_name) LIKE LOWER(?) THEN 2
                    ELSE 3
                END,
                avg_rating DESC
            LIMIT 20";

        $searchTerm = "%{$query}%";
        $stmt = $conn->prepare($sql);
        
        if ($stmt === false) {
            throw new Exception($conn->error);
        }
        
        $stmt->bind_param('sssss', $searchTerm, $searchTerm, $searchTerm, $searchTerm, $searchTerm);
        
        if (!$stmt->execute()) {
            throw new Exception($stmt->error);
        }
        
        $result = $stmt->get_result();
        
        while ($row = $result->fetch_assoc()) {
            $response['results'][] = [
                'service_id' => $row['service_id'],
                'service_name' => $row['service_name'],
                'description' => $row['description'],
                'price' => $row['price'],
                'category_name' => $row['category_name'],
                'avg_rating' => number_format($row['avg_rating'], 1)
            ];
        }
    }
    
    $response['success'] = true;

} catch (Exception $e) {
    $response['error'] = 'An error occurred while searching. Please try again.';
    error_log("Search error: " . $e->getMessage());
}

echo json_encode($response);
exit; 