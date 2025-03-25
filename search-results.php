<?php
// Start session
session_start();
if (!isset($_SESSION)) {
    @session_destroy();
    session_start();
    session_regenerate_id(true);
}

// Database connection
require_once 'dbconnect.php';

// Get search query
$query = isset($_GET['query']) ? trim($_GET['query']) : '';
$hasResults = false;

// Include header (navigation)
include 'header.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Search Results - ServiceHive</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
</head>
<style>
    .search-results-container {
        max-width: 1200px;
        margin: 30px auto;
        padding: 20px;
    }
    
    .search-header {
        margin-bottom: 30px;
        padding-bottom: 10px;
        border-bottom: 1px solid #eee;
    }
    
    .search-header h1 {
        font-size: 28px;
        color: #333;
        margin-bottom: 5px;
    }
    
    .search-header p {
        color: #666;
        font-size: 16px;
    }
    
    .search-section {
        margin-bottom: 40px;
    }
    
    .search-section h2 {
        font-size: 22px;
        color: #333;
        margin-bottom: 15px;
        padding-bottom: 10px;
        border-bottom: 1px solid #eee;
    }
    
    .result-cards {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
        gap: 20px;
    }
    
    .result-card {
        border: 1px solid #eee;
        border-radius: 8px;
        padding: 15px;
        transition: all 0.3s ease;
        box-shadow: 0 2px 5px rgba(0,0,0,0.05);
    }
    
    .result-card:hover {
        box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        transform: translateY(-3px);
    }
    
    .result-card h3 {
        font-size: 18px;
        margin-bottom: 10px;
        color: #333;
    }
    
    .result-meta {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-top: 10px;
    }
    
    .result-category {
        font-size: 14px;
        color: #666;
        background: #f5f5f5;
        padding: 4px 8px;
        border-radius: 4px;
    }
    
    .result-rating {
        color: #ff9800;
        font-weight: 500;
    }
    
    .view-button {
        display: block;
        text-align: center;
        background: #ee6e06;
        color: white;
        padding: 8px 15px;
        border-radius: 4px;
        text-decoration: none;
        margin-top: 15px;
        transition: background 0.3s;
    }
    
    .view-button:hover {
        background: #d66000;
    }
    
    .no-results {
        text-align: center;
        padding: 50px 20px;
        background: #f9f9f9;
        border-radius: 8px;
    }
    
    .no-results h2 {
        font-size: 24px;
        color: #666;
        margin-bottom: 15px;
    }
    
    .no-results p {
        color: #888;
        margin-bottom: 20px;
    }
    
    .popular-searches {
        margin-top: 30px;
    }
    
    .popular-searches h3 {
        font-size: 18px;
        margin-bottom: 15px;
        color: #555;
    }
    
    .popular-tags {
        display: flex;
        flex-wrap: wrap;
        gap: 10px;
        justify-content: center;
    }
    
    .popular-tag {
        background: #f0f0f0;
        padding: 8px 15px;
        border-radius: 20px;
        font-size: 14px;
        color: #555;
        cursor: pointer;
        transition: all 0.3s;
    }
    
    .popular-tag:hover {
        background: #ee6e06;
        color: white;
    }
</style>
<body>
    <div class="search-results-container">
        <div class="search-header">
            <h1>Search Results</h1>
            <p>Showing results for: <strong>"<?php echo htmlspecialchars($query); ?>"</strong></p>
        </div>
        
        <?php
        if (!empty($query)) {
            // Search in categories
            $categoriesQuery = "SELECT c.*, 
                               (SELECT COUNT(*) FROM tbl_services WHERE category_id = c.category_id AND is_active = TRUE) as service_count 
                               FROM tbl_categories c 
                               WHERE c.category_name LIKE ? AND c.is_active = TRUE 
                               ORDER BY c.category_name";
            
            $stmt = $conn->prepare($categoriesQuery);
            $searchParam = "%{$query}%";
            $stmt->bind_param("s", $searchParam);
            $stmt->execute();
            $categoriesResult = $stmt->get_result();
            
            $hasCategories = $categoriesResult->num_rows > 0;
            
            // Search in services
            $servicesQuery = "SELECT s.*, c.category_name,
                             (SELECT AVG(rating) FROM tbl_reviews WHERE service_id = s.service_id) as avg_rating
                             FROM tbl_services s
                             JOIN tbl_categories c ON s.category_id = c.category_id
                             WHERE (s.service_name LIKE ? OR s.description LIKE ?) 
                                  AND s.is_active = TRUE 
                             ORDER BY s.service_name";
            
            $stmt = $conn->prepare($servicesQuery);
            $stmt->bind_param("ss", $searchParam, $searchParam);
            $stmt->execute();
            $servicesResult = $stmt->get_result();
            
            $hasServices = $servicesResult->num_rows > 0;
            
            $hasResults = $hasCategories || $hasServices;
            
            if ($hasResults) {
                // Display categories
                if ($hasCategories) {
                    echo '<div class="search-section">';
                    echo '<h2>Categories</h2>';
                    echo '<div class="result-cards">';
                    
                    while ($category = $categoriesResult->fetch_assoc()) {
                        echo '<div class="result-card">';
                        echo '<h3>' . htmlspecialchars($category['category_name']) . '</h3>';
                        echo '<p>' . (empty($category['description']) ? 'View our services in this category' : htmlspecialchars(substr($category['description'], 0, 80)) . '...') . '</p>';
                        echo '<div class="result-meta">';
                        echo '<span class="result-category">' . $category['service_count'] . ' services</span>';
                        echo '</div>';
                        echo '<a href="services.php?category_id=' . $category['category_id'] . '" class="view-button">View Category</a>';
                        echo '</div>';
                    }
                    
                    echo '</div>'; // end result-cards
                    echo '</div>'; // end search-section
                }
                
                // Display services
                if ($hasServices) {
                    echo '<div class="search-section">';
                    echo '<h2>Services</h2>';
                    echo '<div class="result-cards">';
                    
                    while ($service = $servicesResult->fetch_assoc()) {
                        echo '<div class="result-card">';
                        echo '<h3>' . htmlspecialchars($service['service_name']) . '</h3>';
                        echo '<p>' . (empty($service['description']) ? 'No description available' : htmlspecialchars(substr($service['description'], 0, 80)) . '...') . '</p>';
                        echo '<div class="result-meta">';
                        echo '<span class="result-category">' . htmlspecialchars($service['category_name']) . '</span>';
                        
                        if (!is_null($service['avg_rating'])) {
                            $rating = number_format($service['avg_rating'], 1);
                            echo '<span class="result-rating">★ ' . $rating . '</span>';
                        }
                        
                        echo '</div>';
                        echo '<a href="service-details.php?service_id=' . $service['service_id'] . '" class="view-button">View Details</a>';
                        echo '</div>';
                    }
                    
                    echo '</div>'; // end result-cards
                    echo '</div>'; // end search-section
                }
            } else {
                // No results found
                echo '<div class="no-results">';
                echo '<h2>No results found</h2>';
                echo '<p>We couldn\'t find any matches for "' . htmlspecialchars($query) . '". Please try another search term.</p>';
                
                echo '<div class="popular-searches">';
                echo '<h3>Popular Searches</h3>';
                echo '<div class="popular-tags">';
                echo '<a href="search-results.php?query=Cleaning" class="popular-tag">Cleaning</a>';
                echo '<a href="search-results.php?query=Plumbing" class="popular-tag">Plumbing</a>';
                echo '<a href="search-results.php?query=Electrical" class="popular-tag">Electrical</a>';
                echo '<a href="search-results.php?query=Painting" class="popular-tag">Painting</a>';
                echo '<a href="search-results.php?query=Repair" class="popular-tag">Repair</a>';
                echo '<a href="search-results.php?query=Installation" class="popular-tag">Installation</a>';
                echo '</div>'; // end popular-tags
                echo '</div>'; // end popular-searches
                
                echo '</div>'; // end no-results
            }
        } else {
            // Empty search query
            echo '<div class="no-results">';
            echo '<h2>Please enter a search term</h2>';
            echo '<p>Try searching for services or categories you\'re interested in.</p>';
            
            echo '<div class="popular-searches">';
            echo '<h3>Popular Searches</h3>';
            echo '<div class="popular-tags">';
            echo '<a href="search-results.php?query=Cleaning" class="popular-tag">Cleaning</a>';
            echo '<a href="search-results.php?query=Plumbing" class="popular-tag">Plumbing</a>';
            echo '<a href="search-results.php?query=Electrical" class="popular-tag">Electrical</a>';
            echo '<a href="search-results.php?query=Painting" class="popular-tag">Painting</a>';
            echo '<a href="search-results.php?query=Repair" class="popular-tag">Repair</a>';
            echo '<a href="search-results.php?query=Installation" class="popular-tag">Installation</a>';
            echo '</div>'; // end popular-tags
            echo '</div>'; // end popular-searches
            
            echo '</div>'; // end no-results
        }
        ?>
    </div>

 
    
</body>
</html> 