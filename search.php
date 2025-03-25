<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Start session
session_start();

// Database connection
require_once 'dbconnect.php';

// Get search query
$query = isset($_GET['query']) ? trim($_GET['query']) : '';
$exact = isset($_GET['exact']) && $_GET['exact'] == '1';
$response = ['results' => []];

try {
    if (!empty($query)) {
        // Check for exact match with category if exact flag is set
        if ($exact) {
            $exactQuery = "SELECT category_id FROM tbl_categories 
                          WHERE LOWER(category_name) = LOWER(?) AND is_active = TRUE 
                          LIMIT 1";
            $stmt = $conn->prepare($exactQuery);
            $stmt->bind_param("s", $query);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($row = $result->fetch_assoc()) {
                $response['exact_match'] = $row['category_id'];
                header('Content-Type: application/json');
                echo json_encode($response);
                exit;
            }
        }
        
        // Search in categories
        $categoriesQuery = "SELECT c.*, 
                           (SELECT COUNT(*) FROM tbl_services WHERE category_id = c.category_id AND is_active = TRUE) as service_count 
                           FROM tbl_categories c 
                           WHERE c.category_name LIKE ? AND c.is_active = TRUE 
                           ORDER BY c.category_name 
                           LIMIT 5";
        
        $stmt = $conn->prepare($categoriesQuery);
        $searchParam = "%{$query}%";
        $stmt->bind_param("s", $searchParam);
        $stmt->execute();
        $categoriesResult = $stmt->get_result();
        
        $categories = [];
        while ($row = $categoriesResult->fetch_assoc()) {
            $categories[] = $row;
        }
        
        // Search in services
        $servicesQuery = "SELECT s.*, c.category_name,
                         (SELECT AVG(rating) FROM tbl_reviews WHERE service_id = s.service_id) as avg_rating
                         FROM tbl_services s
                         JOIN tbl_categories c ON s.category_id = c.category_id
                         WHERE (s.service_name LIKE ? OR s.description LIKE ?) 
                              AND s.is_active = TRUE 
                         ORDER BY s.service_name
                         LIMIT 5";
        
        $stmt = $conn->prepare($servicesQuery);
        $stmt->bind_param("ss", $searchParam, $searchParam);
        $stmt->execute();
        $servicesResult = $stmt->get_result();
        
        $services = [];
        while ($row = $servicesResult->fetch_assoc()) {
            // Format avg_rating to one decimal place if not null
            if ($row['avg_rating'] !== null) {
                $row['avg_rating'] = number_format($row['avg_rating'], 1);
            }
            $services[] = $row;
        }
        
        // Combine results
        $response['results'] = [
            'categories' => $categories,
            'services' => $services
        ];
    }
} catch (Exception $e) {
    $response['error'] = "Error searching: " . $e->getMessage();
}

// Return JSON response
header('Content-Type: application/json');
echo json_encode($response);
?> 