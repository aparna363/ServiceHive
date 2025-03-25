<?php
require_once 'dbconnect.php';

// Pagination setup
$reviews_per_page = 12;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $reviews_per_page;

// Get total number of approved reviews
$total_query = "SELECT COUNT(*) as total FROM reviews WHERE status = 'approved'";
$total_result = $conn->query($total_query);
$total_reviews = $total_result->fetch_assoc()['total'];
$total_pages = ceil($total_reviews / $reviews_per_page);

// Fetch paginated reviews
$reviews_query = "
    SELECT r.*, 
           CONCAT(u.first_name, ' ', LEFT(u.last_name, 1), '.') as user_name,
           sp.business_name as provider_name,
           s.service_name
    FROM reviews r
    JOIN users u ON r.user_id = u.user_id
    JOIN service_providers sp ON r.provider_id = sp.provider_id
    JOIN tbl_services s ON r.service_id = s.service_id
    WHERE r.status = 'approved'
    ORDER BY r.created_at DESC
    LIMIT $offset, $reviews_per_page
";

$reviews_result = $conn->query($reviews_query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>All Reviews - ServiceHive</title>
    <link rel="stylesheet" href="css/styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Add your existing CSS here -->
</head>
<body>
    

    <div class="container" style="padding: 50px 20px;">
        <h1 style="text-align: center; color: #bc4f07; margin-bottom: 40px;">Customer Reviews</h1>
        
        <div class="reviews-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 30px; max-width: 1200px; margin: 0 auto;">
            <?php while($review = $reviews_result->fetch_assoc()): ?>
                <div class="review-card">
                    <div class="review-header" style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 15px;">
                        <div class="user-info" style="display: flex; align-items: center;">
                            <div class="user-avatar" style="width: 40px; height: 40px; background: #ee6e06; color: white; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin-right: 12px;">
                                <?php echo strtoupper(substr($review['user_name'], 0, 1)); ?>
                            </div>
                            <div>
                                <h4 style="margin: 0; color: #333;"><?php echo htmlspecialchars($review['user_name']); ?></h4>
                                <p style="margin: 5px 0 0; color: #666; font-size: 14px;"><?php echo htmlspecialchars($review['service_name']); ?></p>
                            </div>
                        </div>
                        <div class="rating" style="color: #ffc107;">
                            <?php
                            for($i = 1; $i <= 5; $i++) {
                                if($i <= $review['rating']) {
                                    echo '<i class="fas fa-star"></i>';
                                } else {
                                    echo '<i class="far fa-star"></i>';
                                }
                            }
                            ?>
                        </div>
                    </div>
                    
                    <div class="review-body" style="color: #555; line-height: 1.6;">
                        <p style="margin: 0;"><?php echo htmlspecialchars($review['review_text']); ?></p>
                    </div>
                    
                    <div class="review-footer" style="margin-top: 15px; padding-top: 15px; border-top: 1px solid #eee; font-size: 14px; color: #888;">
                        <span><?php echo date('F j, Y', strtotime($review['created_at'])); ?></span>
                        <span style="float: right;"><?php echo htmlspecialchars($review['provider_name']); ?></span>
                    </div>
                </div>
            <?php endwhile; ?>
        </div>

        <?php if($total_pages > 1): ?>
            <div class="pagination" style="margin-top: 40px; text-align: center;">
                <?php for($i = 1; $i <= $total_pages; $i++): ?>
                    <a href="?page=<?php echo $i; ?>" 
                       style="display: inline-block; padding: 8px 16px; margin: 0 5px; 
                              background: <?php echo $i == $page ? '#ee6e06' : '#f5f5f5'; ?>; 
                              color: <?php echo $i == $page ? 'white' : '#333'; ?>; 
                              text-decoration: none; border-radius: 4px;">
                        <?php echo $i; ?>
                    </a>
                <?php endfor; ?>
            </div>
        <?php endif; ?>
    </div>

</body>
</html> 