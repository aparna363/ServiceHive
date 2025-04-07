<?php
// Start session if not already started
if (session_status() === PHP_SESSION_INACTIVE) {
    session_start();
}

// Include database connection
require_once 'dbconnect.php';

// Get search query
$query = isset($_GET['query']) ? trim($_GET['query']) : '';

// Initialize arrays for results
$categories = [];
$services = [];

if (!empty($query) && strlen($query) >= 2) {
    // Search for categories
    $categoryQuery = "SELECT c.category_id, c.category_name, c.description, c.image_path,
                     (SELECT COUNT(*) FROM tbl_services s WHERE s.category_id = c.category_id AND s.is_active = TRUE) as service_count
                     FROM tbl_categories c
                     WHERE (c.category_name LIKE ? OR c.description LIKE ?) AND c.is_active = TRUE
                     ORDER BY 
                        CASE 
                            WHEN c.category_name LIKE ? THEN 0
                            ELSE 1
                        END,
                        c.category_name";

    $stmt = $conn->prepare($categoryQuery);
    $searchParam = "%$query%";
    $startParam = "$query%";
    $stmt->bind_param("sss", $searchParam, $searchParam, $startParam);
    $stmt->execute();
    $categoryResults = $stmt->get_result();

    while ($row = $categoryResults->fetch_assoc()) {
        $categories[] = $row;
    }

    // Search for services
    $serviceQuery = "SELECT s.service_id, s.service_name, s.description, s.price, s.image_path,
                    c.category_id, c.category_name,
                    (SELECT AVG(rating) FROM tbl_reviews r WHERE r.service_id = s.service_id) as avg_rating,
                    (SELECT COUNT(*) FROM tbl_reviews r WHERE r.service_id = s.service_id) as review_count
                    FROM tbl_services s
                    JOIN tbl_categories c ON s.category_id = c.category_id
                    WHERE (s.service_name LIKE ? OR s.description LIKE ?) AND s.is_active = TRUE AND c.is_active = TRUE
                    ORDER BY 
                        CASE 
                            WHEN s.service_name LIKE ? THEN 0
                            ELSE 1
                        END,
                        s.service_name";

    $stmt = $conn->prepare($serviceQuery);
    $stmt->bind_param("sss", $searchParam, $searchParam, $startParam);
    $stmt->execute();
    $serviceResults = $stmt->get_result();

    while ($row = $serviceResults->fetch_assoc()) {
        $services[] = $row;
    }
}

// Get total results count
$totalResults = count($categories) + count($services);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Search Results for "<?php echo htmlspecialchars($query); ?>" - ServiceHive</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <script src="script.js"></script>
</head>
<body>
    <?php include 'header.php'; ?>

    <div class="container" style="padding: 40px 20px;">
        <div class="search-results-page">
            <h1>Search Results for "<?php echo htmlspecialchars($query); ?>"</h1>
            <p><?php echo $totalResults; ?> results found</p>

            <?php if (empty($categories) && empty($services)): ?>
                <div class="no-results-container">
                    <div class="no-results">
                        <i class="fas fa-search" style="font-size: 48px; color: #ccc; margin-bottom: 20px;"></i>
                        <h2>No results found</h2>
                        <p>We couldn't find any matches for "<?php echo htmlspecialchars($query); ?>"</p>
                        <p>Try different keywords or check out our popular categories below.</p>
                    </div>

                    <div class="popular-searches">
                        <h3>Popular Categories</h3>
                        <div class="popular-tags">
                            <?php
                            // Fetch popular categories
                            $popularQuery = "SELECT category_id, category_name FROM tbl_categories 
                                           WHERE is_active = TRUE ORDER BY RAND() LIMIT 6";
                            $popularResult = $conn->query($popularQuery);
                            
                            while ($row = $popularResult->fetch_assoc()) {
                                echo '<a href="services.php?category_id=' . $row['category_id'] . '" class="popular-tag">' . 
                                    htmlspecialchars($row['category_name']) . '</a>';
                            }
                            ?>
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <!-- Categories Section -->
                <?php if (!empty($categories)): ?>
                    <section class="search-results-section">
                        <h2>Categories (<?php echo count($categories); ?>)</h2>
                        <div class="category-grid">
                            <?php foreach ($categories as $category): ?>
                                <div class="category-card">
                                    <div class="category-image">
                                        <?php 
                                        $imagePath = "images/placeholder.jpg";
                                        if (!empty($category['image_path'])) {
                                            $imagePath = htmlspecialchars($category['image_path']);
                                        }
                                        ?>
                                        <img src="<?php echo $imagePath; ?>" alt="<?php echo htmlspecialchars($category['category_name']); ?>">
                                    </div>
                                    <div class="category-info">
                                        <h3><?php echo htmlspecialchars($category['category_name']); ?></h3>
                                        <p><?php echo htmlspecialchars($category['description']); ?></p>
                                        <div class="category-meta">
                                            <span><?php echo $category['service_count']; ?> services available</span>
                                        </div>
                                        <a href="services.php?category_id=<?php echo $category['category_id']; ?>" class="view-button">View Services</a>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </section>
                <?php endif; ?>

                <!-- Services Section -->
                <?php if (!empty($services)): ?>
                    <section class="search-results-section">
                        <h2>Services (<?php echo count($services); ?>)</h2>
                        <div class="service-grid">
                            <?php foreach ($services as $service): ?>
                                <div class="service-card">
                                    <div class="service-image">
                                        <?php 
                                        $imagePath = "images/service-placeholder.jpg";
                                        if (!empty($service['image_path'])) {
                                            $imagePath = htmlspecialchars($service['image_path']);
                                        }
                                        ?>
                                        <img src="<?php echo $imagePath; ?>" alt="<?php echo htmlspecialchars($service['service_name']); ?>">
                                    </div>
                                    <div class="service-info">
                                        <h3><?php echo htmlspecialchars($service['service_name']); ?></h3>
                                        <p class="service-category"><?php echo htmlspecialchars($service['category_name']); ?></p>
                                        <p class="service-description"><?php echo htmlspecialchars($service['description']); ?></p>
                                        <div class="service-meta">
                                            <span class="service-price">₹<?php echo number_format($service['price'], 2); ?></span>
                                            <?php if ($service['avg_rating']): ?>
                                                <span class="service-rating">
                                                    <i class="fas fa-star"></i> 
                                                    <?php echo number_format($service['avg_rating'], 1); ?>
                                                    (<?php echo $service['review_count']; ?> reviews)
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                        <a href="service-details.php?service_id=<?php echo $service['service_id']; ?>" class="book-button">View Details</a>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </section>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>

    <?php include 'footer.php'; ?>
</body>
</html>