<?php
require_once 'dbconnect.php';
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id']) && !isset($_SESSION['provider_id'])) {
    header("Location: login.php");
    exit;
}

$is_provider = isset($_SESSION['provider_id']);
$user_id = $_SESSION['user_id'] ?? 0;
$provider_id = $_SESSION['provider_id'] ?? 0;

// Common function to get user name
function getUserName($conn, $user_id) {
    $query = "SELECT CONCAT(first_name, ' ', last_name) as name FROM users WHERE user_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        return $row['name'];
    }
    return "Unknown User";
}

// Simplified function that doesn't require database lookup
function getServiceName($conn, $service_id) {
    return "Service #" . $service_id;
}

// Common function to get provider name
function getProviderName($conn, $provider_id) {
    $query = "SELECT business_name FROM service_providers WHERE provider_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $provider_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        return $row['business_name'];
    }
    return "Unknown Provider";
}

// Get reviews based on user type
if ($is_provider) {
    // Provider view - get reviews about this provider
    $query = "SELECT r.*, b.booking_date, b.service_id, u.first_name, u.last_name 
              FROM reviews r 
              JOIN bookings b ON r.booking_id = b.booking_id 
              JOIN users u ON r.user_id = u.user_id 
              WHERE r.provider_id = ? 
              ORDER BY r.status ASC, b.booking_date DESC";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $provider_id);
} else {
    // User view - get reviews submitted by this user
    $query = "SELECT r.*, b.booking_date, b.service_id 
              FROM reviews r 
              JOIN bookings b ON r.booking_id = b.booking_id 
              WHERE r.user_id = ? 
              ORDER BY b.booking_date DESC";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $user_id);
}

$stmt->execute();
$result = $stmt->get_result();
$reviews = [];
while ($row = $result->fetch_assoc()) {
    $reviews[] = $row;
}

// Insert review with explicit status of 'pending'
$insert_query = "INSERT INTO reviews (booking_id, user_id, provider_id, service_id, rating, review_text, status) 
                VALUES (?, ?, ?, ?, ?, ?, 'pending')";

// Get average rating for the provider (only approved reviews)
$query = "SELECT AVG(rating) as avg_rating, COUNT(*) as total_reviews 
          FROM reviews 
          WHERE provider_id = ? AND status = 'approved'";

// Get recent reviews (limit to 2)
$stmt = $conn->prepare("
    SELECT r.*, u.username, b.booking_date, r.status,
           s.service_name
    FROM reviews r
    JOIN bookings b ON r.booking_id = b.booking_id
    JOIN users u ON r.user_id = u.id  
    JOIN tbl_services s ON b.service_id = s.service_id
    WHERE r.provider_id = ?
    ORDER BY b.booking_date DESC
    LIMIT 2
");
$stmt->bind_param("i", $provider_id);
$stmt->execute();
$recent_reviews = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get count of pending reviews
$stmt = $conn->prepare("
    SELECT COUNT(*) as pending_reviews
    FROM reviews
    WHERE provider_id = ? AND (status IS NULL OR status = 'pending')
");
$stmt->bind_param("i", $provider_id);
$stmt->execute();
$pending_reviews_result = $stmt->get_result()->fetch_assoc();
$pending_reviews_count = $pending_reviews_result['pending_reviews'] ?? 0;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $is_provider ? 'Provider Reviews' : 'My Reviews'; ?></title>
    <link rel="stylesheet" href="css/styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .reviews-container {
            max-width: 900px;
            margin: 30px auto;
            padding: 0 20px;
        }
        
        .page-title {
            font-size: 2rem;
            color: #333;
            margin-bottom: 30px;
            padding-bottom: 15px;
            border-bottom: 2px solid #f0f0f0;
            text-align: center;
        }
        
        .review-card {
            background-color: #fff;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
            padding: 25px;
            margin-bottom: 25px;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            position: relative;
            overflow: hidden;
        }
        
        .review-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
        }
        
        .review-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 15px;
        }
        
        .review-rating {
            display: flex;
            flex-direction: column;
            align-items: flex-start;
        }
        
        .stars {
            color: #ffc107;
            font-size: 24px;
            letter-spacing: 3px;
            margin-bottom: 5px;
        }
        
        .rating-value {
            font-weight: bold;
            font-size: 14px;
            color: #666;
        }
        
        .review-info {
            display: flex;
            justify-content: space-between;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 1px solid #f0f0f0;
            flex-wrap: wrap;
        }
        
        .info-item {
            margin-right: 20px;
            margin-bottom: 10px;
        }
        
        .info-label {
            font-size: 13px;
            color: #888;
            margin-bottom: 4px;
            display: block;
        }
        
        .info-value {
            font-weight: 500;
            color: #444;
            font-size: 15px;
        }
        
        .review-body {
            color: #555;
            line-height: 1.6;
            font-size: 16px;
        }
        
        .empty-state {
            text-align: center;
            padding: 50px 20px;
            color: #888;
        }
        
        .empty-state i {
            font-size: 50px;
            color: #ddd;
            margin-bottom: 20px;
        }
        
        .empty-state p {
            font-size: 18px;
            margin-bottom: 20px;
        }
        
        @media (max-width: 768px) {
            .review-header {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .review-rating {
                margin-bottom: 15px;
            }
            
            .info-item {
                flex-basis: 100%;
                margin-right: 0;
            }
        }
        
        .status-badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: bold;
            margin-left: 10px;
        }
        .status-pending {
            background-color: #fff3cd;
            color: #856404;
        }
        .status-approved {
            background-color: #d4edda;
            color: #155724;
        }
    </style>
</head>
<body>
 
    
    <div class="reviews-container">
        <h1 class="page-title"><?php echo $is_provider ? 'Customer Reviews' : 'My Reviews'; ?></h1>
        
        <?php if (empty($reviews)): ?>
            <div class="empty-state">
                <i class="fa fa-star-half-alt"></i>
                <p>No reviews found</p>
                <?php if (!$is_provider): ?>
                    <a href="bookings.php" class="btn btn-primary">Review a Service</a>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <?php foreach ($reviews as $review): ?>
                <div class="review-card">
                    <div class="review-header">
                        <div class="review-rating">
                            <div class="stars">
                                <?php 
                                // Display stars for rating
                                for ($i = 1; $i <= 5; $i++) {
                                    if ($i <= $review['rating']) {
                                        echo '<i class="fas fa-star"></i>';
                                    } else {
                                        echo '<i class="far fa-star"></i>';
                                    }
                                }
                                ?>
                            </div>
                            <span class="rating-value"><?php echo $review['rating']; ?> out of 5</span>
                            <?php if ($is_provider && isset($review['status'])): ?>
                                <span class="status-badge status-<?php echo $review['status']; ?>">
                                    <?php echo ucfirst($review['status']); ?>
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="review-info">
                        <?php if ($is_provider): ?>
                            <div class="info-item">
                                <span class="info-label">Reviewed By</span>
                                <span class="info-value"><?php echo $review['first_name'] . ' ' . $review['last_name']; ?></span>
                            </div>
                        <?php else: ?>
                            <div class="info-item">
                                <span class="info-label">Service Provider</span>
                                <span class="info-value"><?php echo getProviderName($conn, $review['provider_id']); ?></span>
                            </div>
                        <?php endif; ?>
                        
                        <div class="info-item">
                            <span class="info-label">Service</span>
                            <span class="info-value"><?php echo getServiceName($conn, $review['service_id']); ?></span>
                        </div>
                        
                        <div class="info-item">
                            <span class="info-label">Date</span>
                            <span class="info-value"><?php echo date('F j, Y', strtotime($review['booking_date'])); ?></span>
                        </div>
                    </div>
                    
                    <div class="review-body">
                        <?php if (empty(trim($review['review_text']))): ?>
                            <em>No written feedback provided.</em>
                        <?php else: ?>
                            <p><?php echo htmlspecialchars($review['review_text']); ?></p>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
    
   
    
    <script>
        // Optional JavaScript for any interactive elements
        document.addEventListener('DOMContentLoaded', function() {
            // Add any JS functionality here if needed
        });
    </script>
</body>
</html> 