<?php
session_start();
require_once 'dbconnect.php';

// Check if user is logged in and is a service provider
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'service_provider') {
    header('Location: login.php');
    exit();
}

$user_id = $_SESSION['user_id'];

// Get provider ID
$stmt = $conn->prepare("SELECT provider_id FROM service_providers WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows === 0) {
    header('Location: create_provider_profile.php');
    exit();
}
$provider_data = $result->fetch_assoc();
$provider_id = $provider_data['provider_id'];

// Get filter parameters
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'all';
$rating_filter = isset($_GET['rating']) ? (int)$_GET['rating'] : 0;
$sort_by = isset($_GET['sort']) ? $_GET['sort'] : 'newest';

// Prepare base query
$query = "SELECT r.*, u.username, b.booking_date, b.booking_time, s.service_name 
          FROM reviews r
          JOIN bookings b ON r.booking_id = b.booking_id
          JOIN users u ON r.user_id = u.id  
          JOIN tbl_services s ON b.service_id = s.service_id
          WHERE r.provider_id = ?";

// Add this line to automatically update pending reviews to approved
$update_query = "UPDATE reviews SET status = 'approved' WHERE provider_id = ? AND status = 'pending'";
$update_stmt = $conn->prepare($update_query);
$update_stmt->bind_param("i", $provider_id);
$update_stmt->execute();

// Add filters
$params = [$provider_id];
$types = "i";

if ($status_filter !== 'all') {
    $query .= " AND r.status = ?";
    $params[] = $status_filter;
    $types .= "s";
}

if ($rating_filter > 0) {
    $query .= " AND r.rating = ?";
    $params[] = $rating_filter;
    $types .= "i";
}

// Add sorting
switch ($sort_by) {
    case 'newest':
        $query .= " ORDER BY b.booking_date DESC, b.booking_time DESC";
        break;
    case 'oldest':
        $query .= " ORDER BY b.booking_date ASC, b.booking_time ASC";
        break;
    case 'highest':
        $query .= " ORDER BY r.rating DESC, b.booking_date DESC";
        break;
    case 'lowest':
        $query .= " ORDER BY r.rating ASC, b.booking_date DESC";
        break;
    default:
        $query .= " ORDER BY b.booking_date DESC, b.booking_time DESC";
}

// Get total reviews count (for stats)
$total_stmt = $conn->prepare("SELECT COUNT(*) as total, 
                                     SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved,
                                     SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
                                     SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected,
                                     AVG(CASE WHEN status = 'approved' THEN rating ELSE NULL END) as avg_rating
                              FROM reviews WHERE provider_id = ?");
$total_stmt->bind_param("i", $provider_id);
$total_stmt->execute();
$stats = $total_stmt->get_result()->fetch_assoc();

// Get rating distribution
$rating_dist_stmt = $conn->prepare("SELECT rating, COUNT(*) as count 
                                   FROM reviews 
                                   WHERE provider_id = ? AND status = 'approved'
                                   GROUP BY rating 
                                   ORDER BY rating DESC");
$rating_dist_stmt->bind_param("i", $provider_id);
$rating_dist_stmt->execute();
$rating_dist_result = $rating_dist_stmt->get_result();
$rating_distribution = [];
while ($row = $rating_dist_result->fetch_assoc()) {
    $rating_distribution[$row['rating']] = $row['count'];
}

// Execute main query
$stmt = $conn->prepare($query);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$reviews = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Calculate total reviews by status for filter counts
$pending_count = $stats['pending'] ?? 0;
$approved_count = $stats['approved'] ?? 0;
$rejected_count = $stats['rejected'] ?? 0;
$total_count = $stats['total'] ?? 0;
$avg_rating = round($stats['avg_rating'] ?? 0, 1);

// Get pending notifications count
$stmt = $conn->prepare("SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = 0");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$notification_count = $stmt->get_result()->fetch_assoc()['count'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Review Management - ServiceHive</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Arial', sans-serif;
        }

        .dashboard-container {
            display: flex;
            min-height: 100vh;
        }

        .sidebar {
            width: 250px;
            background-color: rgb(104, 35, 3);
            color: white;
            padding: 20px;
        }

        .logo-container {
            text-align: center;
            margin-bottom: 30px;
        }

        .company-logo {
            width: 260px;
            height: auto;
            max-width: 100%;
            display: block;
            margin: 0 auto;
        }

        .sidebar-menu {
            list-style: none;
        }

        .sidebar-menu li {
            margin-bottom: 15px;
        }

        .sidebar-menu a {
            color: white;
            text-decoration: none;
            display: flex;
            align-items: center;
            padding: 10px;
            transition: 0.3s;
        }

        .sidebar-menu a:hover {
            background-color: rgb(171, 46, 8);
            border-radius: 5px;
        }

        .sidebar-menu a.active {
            background-color: rgb(171, 46, 8);
            border-radius: 5px;
        }

        .sidebar-menu i {
            margin-right: 10px;
        }

        .main-content {
            flex: 1;
            padding: 20px;
            background-color: #f5f5f5;
        }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 1px solid #ddd;
        }

        .notification-bell {
            position: relative;
            cursor: pointer;
            display: inline-block;
            padding: 10px;
            background-color: #fff;
            border-radius: 50%;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            margin-left: 15px;
        }

        .notification-bell i {
            font-size: 24px;
            color: #333;
        }

        .notification-badge {
            position: absolute;
            top: -5px;
            right: -5px;
            background-color: #ff4757;
            color: white;
            border-radius: 50%;
            width: 20px;
            height: 20px;
            font-size: 12px;
            display: flex;
            justify-content: center;
            align-items: center;
            font-weight: bold;
            box-shadow: 0 2px 4px rgba(0,0,0,0.2);
        }

        .reviews-overview {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 20px;
            margin-bottom: 30px;
        }

        .reviews-stats {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }

        .stat-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 20px;
        }

        .avg-rating {
            font-size: 48px;
            font-weight: bold;
            color: #ff5722;
        }

        .rating-label {
            font-size: 14px;
            color: #666;
        }

        .rating-stars {
            color: #ffc107;
            font-size: 24px;
            margin-top: 5px;
        }

        .rating-count {
            color: #666;
            font-size: 14px;
            margin-top: 5px;
        }

        .rating-bars {
            margin-top: 20px;
        }

        .rating-bar {
            display: flex;
            align-items: center;
            margin-bottom: 10px;
        }

        .star-label {
            width: 60px;
            font-size: 14px;
            color: #666;
        }

        .progress-container {
            flex: 1;
            height: 10px;
            background-color: #eee;
            border-radius: 5px;
            overflow: hidden;
            margin: 0 10px;
        }

        .progress-fill {
            height: 100%;
            background-color: #ff5722;
        }

        .bar-count {
            width: 40px;
            text-align: right;
            font-size: 14px;
            color: #666;
        }

        .reviews-filter {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }

        .filter-section {
            margin-bottom: 20px;
        }

        .filter-section h3 {
            margin-bottom: 10px;
            color: #333;
            font-size: 16px;
        }

        .filter-options {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
        }

        .filter-tag {
            padding: 8px 15px;
            background-color: #f5f5f5;
            border-radius: 30px;
            font-size: 14px;
            color: #333;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
        }

        .filter-tag:hover {
            background-color: #e0e0e0;
        }

        .filter-tag.active {
            background-color: #ff5722;
            color: white;
        }

        .filter-tag .count {
            display: inline-block;
            background-color: rgba(0,0,0,0.1);
            padding: 2px 6px;
            border-radius: 10px;
            margin-left: 5px;
            font-size: 12px;
        }

        .reviews-list {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }

        .reviews-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .reviews-header h2 {
            color: #333;
        }

        .sort-dropdown {
            position: relative;
            display: inline-block;
        }

        .sort-btn {
            display: flex;
            align-items: center;
            background-color: #f5f5f5;
            border: none;
            padding: 8px 15px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
        }

        .sort-btn i {
            margin-left: 5px;
        }

        .sort-dropdown-content {
            display: none;
            position: absolute;
            right: 0;
            top: 100%;
            background-color: white;
            min-width: 160px;
            box-shadow: 0 8px 16px rgba(0,0,0,0.1);
            z-index: 1;
            border-radius: 5px;
            overflow: hidden;
        }

        .sort-dropdown-content a {
            color: #333;
            padding: 10px 15px;
            text-decoration: none;
            display: block;
            font-size: 14px;
            transition: background-color 0.3s;
        }

        .sort-dropdown-content a:hover {
            background-color: #f5f5f5;
        }

        .sort-dropdown:hover .sort-dropdown-content {
            display: block;
        }

        .review-card {
            border: 1px solid #eee;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
            transition: all 0.3s ease;
        }

        .review-card:hover {
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
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
        }

        .review-rating .stars {
            color: #ffc107;
            font-size: 18px;
            margin-bottom: 5px;
        }

        .rating-value {
            font-size: 14px;
            color: #666;
        }

        .review-date {
            font-size: 14px;
            color: #888;
        }

        .status-badge {
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 500;
        }

        .status-pending { background: #fff3cd; color: #856404; }
        .status-approved { background: #d4edda; color: #155724; }
        .status-rejected { background: #f8d7da; color: #721c24; }

        .review-info {
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
            margin-bottom: 15px;
            padding-bottom: 15px;
            border-bottom: 1px solid #eee;
        }

        .info-item {
            display: flex;
            flex-direction: column;
        }

        .info-label {
            font-size: 12px;
            color: #888;
            margin-bottom: 3px;
        }

        .info-value {
            font-size: 14px;
            color: #333;
            font-weight: 500;
        }

        .review-body {
            color: #555;
            line-height: 1.6;
            margin-bottom: 15px;
        }

        .review-actions {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
        }

        .btn-action {
            padding: 6px 12px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            transition: all 0.3s ease;
        }

        .btn-action.approve {
            background-color: #28a745;
            color: white;
        }

        .btn-action.reject {
            background-color: #dc3545;
            color: white;
        }

        .empty-reviews {
            text-align: center;
            padding: 40px 0;
            color: #888;
        }

        .empty-reviews i {
            font-size: 48px;
            margin-bottom: 15px;
            color: #ddd;
        }

        @media (max-width: 768px) {
            .dashboard-container {
                flex-direction: column;
            }

            .sidebar {
                width: 100%;
            }

            .reviews-overview {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <div class="sidebar">
            <div class="logo-container">
                <img src="images/logo2.png" alt="ServiceHive Logo" class="company-logo">
            </div>
            <ul class="sidebar-menu">
                <li><a href="provider_dashboard.php"><i class="fas fa-home"></i> Dashboard</a></li>
                <li><a href="index.php"><i class="fas fa-globe"></i> Home </a></li>
                <li><a href="#"><i class="fas fa-calendar"></i> Bookings</a></li>
                <li><a href="service-management.php"><i class="fas fa-tools"></i> Services</a></li>
                <li><a href="subservice-management.php"><i class="fas fa-tools"></i> Sub Services</a></li>
                <li><a href="#" class="active"><i class="fas fa-star"></i> Reviews</a></li>
                <li><a href="profile.php"><i class="fas fa-user"></i> Profile</a></li>
                <li><a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
            </ul>
        </div>

        <div class="main-content">
            <div class="header">
                <h1>Review Management</h1>
                <div class="notification-bell">
                    <i class="fas fa-bell" id="bellIcon"></i>
                    <?php if ($notification_count > 0): ?>
                        <span class="notification-badge"><?php echo $notification_count; ?></span>
                    <?php endif; ?>
                </div>
            </div>

            <div class="reviews-overview">
                <div class="reviews-stats">
                    <div class="stat-header">
                        <div>
                            <div class="avg-rating"><?php echo $avg_rating; ?></div>
                            <div class="rating-label">out of 5</div>
                        </div>
                        <div>
                            <div class="rating-stars">
                                <?php
                                $full_stars = floor($avg_rating);
                                $half_star = $avg_rating - $full_stars >= 0.5;
                                $empty_stars = 5 - $full_stars - ($half_star ? 1 : 0);
                                
                                for ($i = 0; $i < $full_stars; $i++) {
                                    echo '<i class="fas fa-star"></i>';
                                }
                                if ($half_star) {
                                    echo '<i class="fas fa-star-half-alt"></i>';
                                }
                                for ($i = 0; $i < $empty_stars; $i++) {
                                    echo '<i class="far fa-star"></i>';
                                }
                                ?>
                            </div>
                            <div class="rating-count"><?php echo $approved_count; ?> approved reviews</div>
                        </div>
                    </div>
                    
                    <div class="rating-bars">
                        <?php 
                        for ($i = 5; $i >= 1; $i--) {
                            $count = $rating_distribution[$i] ?? 0;
                            $percentage = $approved_count > 0 ? ($count / $approved_count) * 100 : 0;
                        ?>
                        <div class="rating-bar">
                            <div class="star-label"><?php echo $i; ?> stars</div>
                            <div class="progress-container">
                                <div class="progress-fill" style="width: <?php echo $percentage; ?>%"></div>
                            </div>
                            <div class="bar-count"><?php echo $count; ?></div>
                        </div>
                        <?php } ?>
                    </div>
                </div>

                <div class="reviews-filter">
                    <div class="filter-section">
                        <h3>Filter by Status</h3>
                        <div class="filter-options">
                            <a href="?status=all<?php echo $rating_filter ? '&rating='.$rating_filter : ''; ?>&sort=<?php echo $sort_by; ?>" 
                               class="filter-tag <?php echo $status_filter === 'all' ? 'active' : ''; ?>">
                                All <span class="count"><?php echo $total_count; ?></span>
                            </a>
                            <a href="?status=pending<?php echo $rating_filter ? '&rating='.$rating_filter : ''; ?>&sort=<?php echo $sort_by; ?>" 
                               class="filter-tag <?php echo $status_filter === 'pending' ? 'active' : ''; ?>">
                                Pending <span class="count"><?php echo $pending_count; ?></span>
                            </a>
                            <a href="?status=approved<?php echo $rating_filter ? '&rating='.$rating_filter : ''; ?>&sort=<?php echo $sort_by; ?>" 
                               class="filter-tag <?php echo $status_filter === 'approved' ? 'active' : ''; ?>">
                                Approved <span class="count"><?php echo $approved_count; ?></span>
                            </a>
                            <a href="?status=rejected<?php echo $rating_filter ? '&rating='.$rating_filter : ''; ?>&sort=<?php echo $sort_by; ?>" 
                               class="filter-tag <?php echo $status_filter === 'rejected' ? 'active' : ''; ?>">
                                Rejected <span class="count"><?php echo $rejected_count; ?></span>
                            </a>
                        </div>
                    </div>

                    <div class="filter-section">
                        <h3>Filter by Rating</h3>
                        <div class="filter-options">
                            <a href="?<?php echo $status_filter !== 'all' ? 'status='.$status_filter.'&' : ''; ?>sort=<?php echo $sort_by; ?>" 
                               class="filter-tag <?php echo $rating_filter === 0 ? 'active' : ''; ?>">
                                All Ratings
                            </a>
                            <?php for ($i = 5; $i >= 1; $i--): ?>
                            <a href="?<?php echo $status_filter !== 'all' ? 'status='.$status_filter.'&' : ''; ?>rating=<?php echo $i; ?>&sort=<?php echo $sort_by; ?>" 
                               class="filter-tag <?php echo $rating_filter === $i ? 'active' : ''; ?>">
                                <?php echo $i; ?> Stars
                            </a>
                            <?php endfor; ?>
                        </div>
                    </div>
                </div>
            </div>

            <div class="reviews-list">
                <div class="reviews-header">
                    <h2><?php 
                        echo $status_filter === 'all' ? 'All Reviews' : 
                             ($status_filter === 'pending' ? 'Pending Reviews' : 
                             ($status_filter === 'approved' ? 'Approved Reviews' : 
                             'Rejected Reviews')); 
                        ?>
                    </h2>
                    <div class="sort-dropdown">
                        <button class="sort-btn">
                            <?php 
                            echo $sort_by === 'newest' ? 'Newest First' : 
                                 ($sort_by === 'oldest' ? 'Oldest First' : 
                                 ($sort_by === 'highest' ? 'Highest Rating' : 
                                 'Lowest Rating')); 
                            ?>
                            <i class="fas fa-chevron-down"></i>
                        </button>
                        <div class="sort-dropdown-content">
                            <a href="?<?php echo $status_filter !== 'all' ? 'status='.$status_filter.'&' : ''; ?><?php echo $rating_filter ? 'rating='.$rating_filter.'&' : ''; ?>sort=newest">Newest First</a>
                            <a href="?<?php echo $status_filter !== 'all' ? 'status='.$status_filter.'&' : ''; ?><?php echo $rating_filter ? 'rating='.$rating_filter.'&' : ''; ?>sort=oldest">Oldest First</a>
                            <a href="?<?php echo $status_filter !== 'all' ? 'status='.$status_filter.'&' : ''; ?><?php echo $rating_filter ? 'rating='.$rating_filter.'&' : ''; ?>sort=highest">Highest Rating</a>
                            <a href="?<?php echo $status_filter !== 'all' ? 'status='.$status_filter.'&' : ''; ?><?php echo $rating_filter ? 'rating='.$rating_filter.'&' : ''; ?>sort=lowest">Lowest Rating</a>
                        </div>
                    </div>
                </div>

                <?php if (empty($reviews)): ?>
                    <div class="empty-reviews">
                        <i class="far fa-comment-alt"></i>
                        <p>No reviews found matching your filters.</p>
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
                                </div>
                                <div>
                                    <span class="status-badge status-<?php echo $review['status']; ?>">
                                        <?php echo ucfirst($review['status']); ?>
                                    </span>
                                    <span class="review-date">
                                        <?php echo date('F j, Y', strtotime($review['booking_date'])); ?>
                                    </span>
                                </div>
                            </div>
                            
                            <div class="review-info">
                                <div class="info-item">
                                    <span class="info-label">Reviewed By</span>
                                    <span class="info-value"><?php echo htmlspecialchars($review['username']); ?></span>
                                </div>
                                
                                <div class="info-item">
                                    <span class="info-label">Service</span>
                                    <span class="info-value"><?php echo htmlspecialchars($review['service_name']); ?></span>
                                </div>
                                
                                <div class="info-item">
                                    <span class="info-label">Booking ID</span>
                                    <span class="info-value">#<?php echo $review['booking_id']; ?></span>
                                </div>
                                
                                
                            </div>
                            
                            <div class="review-body">
                                <?php if (empty(trim($review['review_text']))): ?>
                                    <em>No written feedback provided.</em>
                                <?php else: ?>
                                    <p><?php echo htmlspecialchars($review['review_text']); ?></p>
                                <?php endif; ?>
                            </div>
                            
                            <?php if ($review['status'] == 'pending'): ?>
                            <div class="review-actions">
                                <p><em>Reviews are automatically approved in the system.</em></p>
                            </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const bellIcon = document.getElementById('bellIcon');
            
            if (bellIcon) {
                bellIcon.addEventListener('click', function() {
                    // Implement notification dropdown functionality
                    console.log('Notification bell clicked');
                });
            }
        });
    </script>
</body>
</html>