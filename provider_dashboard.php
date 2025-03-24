<?php
session_start();
require_once 'dbconnect.php';

// Debug logging
error_log("Provider dashboard accessed. Session data: " . print_r($_SESSION, true));

// Check if user is logged in and is a service provider
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'service_provider') {
    error_log("Access denied to provider dashboard. User ID: " . ($_SESSION['user_id'] ?? 'not set') . ", Role: " . ($_SESSION['role'] ?? 'not set'));
    header('Location: login.php');
    exit();
}

$user_id = $_SESSION['user_id'];

// Get provider data
$stmt = $conn->prepare("
    SELECT sp.*, u.username 
    FROM service_providers sp
    JOIN users u ON sp.user_id = u.id
    WHERE sp.user_id = ?
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    // Provider record not found, redirect to create provider profile
    header('Location: create_provider_profile.php');
    exit();
}

$provider_data = $result->fetch_assoc();
$provider_id = $provider_data['provider_id'];
$provider_name = $provider_data['username'];

// Debug output
error_log("Provider data: " . print_r($provider_data, true));

// Check verification status
$is_verified = $provider_data['verified_status'] == 1;
$verification_status = $provider_data['status'] ?? 'pending';

// Initialize documents_submitted variable
$documents_submitted = false;

// Check if documents have been uploaded
if (isset($provider_id)) {
    $stmt = $conn->prepare("
        SELECT * FROM verification_documents 
        WHERE provider_id = ?
        ORDER BY uploaded_at DESC
        LIMIT 1
    ");
    $stmt->bind_param("i", $provider_id);
    $stmt->execute();
    $doc_result = $stmt->get_result();
    $documents_submitted = $doc_result->num_rows > 0;
}

// Show verification popup only if documents are not uploaded and not verified
$show_verification_popup = !$documents_submitted && !$is_verified;

// Debug logging
error_log("Verification status: is_verified=$is_verified, documents_submitted=$documents_submitted, show_popup=$show_verification_popup");

// Get pending bookings count
$stmt = $conn->prepare("
    SELECT COUNT(*) as pending_count 
    FROM bookings 
    WHERE provider_id = ? AND status = 'pending'
");
$stmt->bind_param("i", $provider_id);
$stmt->execute();
$pending_count = $stmt->get_result()->fetch_assoc()['pending_count'];

// Automatically approve new bookings
$stmt = $conn->prepare("
    UPDATE bookings 
    SET status = 'accepted' 
    WHERE provider_id = ? AND status = 'pending'
");
$stmt->bind_param("i", $provider_id);
$stmt->execute();

// Get today's bookings
$today = date('Y-m-d');
$stmt = $conn->prepare("
    SELECT b.*, u.username, s.service_name
    FROM bookings b
    JOIN users u ON b.user_id = u.id
    JOIN tbl_services s ON b.service_id = s.service_id
    WHERE b.provider_id = ? AND DATE(b.booking_date) = ?
    ORDER BY b.booking_date, b.booking_time
");
$stmt->bind_param("is", $provider_id, $today);
$stmt->execute();
$today_bookings = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get rating statistics
$stmt = $conn->prepare("
    SELECT AVG(rating) as avg_rating, COUNT(*) as total_reviews
    FROM reviews
    WHERE provider_id = ?
");
$stmt->bind_param("i", $provider_id);
$stmt->execute();
$ratings = $stmt->get_result()->fetch_assoc();

// Set default values if no ratings exist
$avg_rating = $ratings['avg_rating'] ?? 0;
$total_reviews = $ratings['total_reviews'] ?? 0;

// Fetch unread notifications for the user
$stmt = $conn->prepare("
    SELECT id, title, message, created_at 
    FROM notifications 
    WHERE user_id = ? AND is_read = 0
    ORDER BY created_at DESC
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$notifications = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$notification_count = count($notifications);

// Check for verification submission
$verification_submitted = isset($_SESSION['verification_submitted']) && $_SESSION['verification_submitted'] === true;
if ($verification_submitted) {
    // Clear the flag
    unset($_SESSION['verification_submitted']);
    // Force documents_submitted to true to show the pending message
    $documents_submitted = true;
    $verification_status = 'pending';
}

// Check for verification error
$verification_error = isset($_SESSION['verification_error']) ? $_SESSION['verification_error'] : '';
if (!empty($verification_error)) {
    // Clear the error
    unset($_SESSION['verification_error']);
}
<<<<<<< HEAD

// Get all bookings for the provider
$stmt = $conn->prepare("
    SELECT 
        b.*,
        u.username as customer_name,
        u.mobile as customer_mobile,
        u.email as customer_email,
        s.service_name,
        COALESCE(b.total_amount, b.total_price) as final_price
    FROM bookings b
    JOIN users u ON b.user_id = u.id
    JOIN tbl_services s ON b.service_id = s.service_id
    WHERE b.provider_id = ?
    ORDER BY b.booking_date DESC, b.time_slot ASC
");
$stmt->bind_param("i", $provider_id);
$stmt->execute();
$bookings = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Update the counts query to remove paid and accepted counts
$counts_query = "
    SELECT 
        COUNT(*) as total_bookings,
        SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_bookings,
        SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_bookings,
        SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected_bookings
    FROM bookings 
    WHERE provider_id = ?";

$stmt = $conn->prepare($counts_query);
$stmt->bind_param("i", $provider_id);
$stmt->execute();
$counts = $stmt->get_result()->fetch_assoc();
=======
>>>>>>> c1f9cd25c0f9a1e185e0bae636b4d327ccfd1232
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Service Provider Dashboard - ServiceHive</title>
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
            width: 260px;  /* Fixed width for the logo */
            height: auto;  /* Maintain aspect ratio */
            max-width: 100%;  /* Ensure it doesn't overflow the sidebar */
            display: block;
            margin: 0 auto;
        }

        .sidebar .logo {
            font-size: 24px;
            margin-bottom: 30px;
            color: #ffffff;
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

        @keyframes pulse {
            0% {
                transform: scale(1);
            }
            50% {
                transform: scale(1.1);
            }
            100% {
                transform: scale(1);
            }
        }

        .dropdown-content {
            position: absolute;
            right: 0;
            top: 40px;
            background-color: white;
            min-width: 300px;
            max-width: 400px;
            box-shadow: 0 8px 16px rgba(0,0,0,0.2);
            z-index: 1000;
            border-radius: 8px;
            overflow: hidden;
            max-height: 400px;
            overflow-y: auto;
        }

        .dropdown-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px;
            background-color: #f8f9fa;
            border-bottom: 1px solid #eee;
        }

        .dropdown-header h3 {
            margin: 0;
            font-size: 16px;
        }

        .mark-all-read {
            color: #007bff;
            text-decoration: none;
            font-size: 14px;
        }

        .notification {
            background-color: #4CAF50;
            color: white;
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 5px;
        }

        .verified-badge {
            display: inline-flex;
            align-items: center;
            background-color: #28a745;
            color: white;
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 14px;
            margin-left: 10px;
        }

        .verified-badge i {
            margin-right: 5px;
        }

        .notification-item {
            padding: 10px 15px;
            border-bottom: 1px solid #eee;
            transition: background-color 0.2s;
        }

        .notification-item:hover {
            background-color: #f5f5f5;
        }

        .notification-title {
            font-weight: bold;
            margin-bottom: 5px;
        }

        .notification-message {
            color: #555;
            font-size: 14px;
        }

        .notification-time {
            color: #888;
            font-size: 12px;
            margin-top: 5px;
        }

        .empty-notification {
            padding: 20px;
            text-align: center;
            color: #888;
        }

        .stats-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background-color: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }

        .stat-card h3 {
            color: #666;
            margin-bottom: 10px;
        }

        .stat-card .value {
            font-size: 24px;
            font-weight: bold;
            color: #ff5722;
        }

        .bookings-container {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-top: 20px;
        }

        .table-responsive {
            overflow-x: auto;
        }

        .booking-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }

        .booking-table th,
        .booking-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }

        .booking-table th {
            background: #f8f9fa;
            font-weight: 600;
        }

        .customer-info {
            display: flex;
            flex-direction: column;
            gap: 2px;
        }

        .customer-info small {
            color: #666;
        }

        .status-badge {
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 500;
        }

        .status-pending { background: #fff3cd; color: #856404; }
        .status-accepted { background: #d4edda; color: #155724; }
        .status-completed { background: #cce5ff; color: #004085; }
        .status-rejected { background: #f8d7da; color: #721c24; }

        .payment-status {
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
        }

        .payment-status.pending { background: #fff3cd; color: #856404; }
        .payment-status.paid { background: #d4edda; color: #155724; }

        .action-buttons {
            display: flex;
            gap: 5px;
            flex-wrap: wrap;
        }

        .btn-action {
            padding: 4px 8px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 12px;
            transition: all 0.3s ease;
        }

        .btn-action.accept { background: #28a745; color: white; }
        .btn-action.reject { background: #dc3545; color: white; }
        .btn-action.complete { background: #007bff; color: white; }
        .btn-action.view { background: #6c757d; color: white; }

        .no-bookings {
            text-align: center;
            padding: 40px;
            color: #666;
        }

        /* Verification banner styles */
        .verification-banner {
            background-color: #ff7f50;
            color: white;
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
        }

        .verification-banner i {
            font-size: 24px;
            margin-right: 15px;
        }

        .verification-banner.pending {
            background-color: #ff7f50;
        }

        .verification-banner.approved {
            background-color: #4CAF50;
        }

        .verification-banner.rejected {
            background-color: #f44336;
        }

        .verification-details {
            background-color: #fff9e6;
            border-left: 4px solid #ffc107;
            padding: 20px;
            margin-bottom: 20px;
            border-radius: 0 8px 8px 0;
        }

        .verification-details h3 {
            color: #856404;
            margin-bottom: 10px;
        }

        .verification-details p {
            color: #666;
            margin-bottom: 10px;
        }

        /* Popup styles */
        .verification-popup {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.8);
            backdrop-filter: blur(8px);
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 1000;
        }

<<<<<<< HEAD
=======
        .status-rejected {
            background-color: #f8d7da;
            color: #721c24;
        }

        /* Verification banner styles */
        .verification-banner {
            background-color: #ff7f50;
            color: white;
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
        }

        .verification-banner i {
            font-size: 24px;
            margin-right: 15px;
        }

        .verification-banner.pending {
            background-color: #ff7f50;
        }

        .verification-banner.approved {
            background-color: #4CAF50;
        }

        .verification-banner.rejected {
            background-color: #f44336;
        }

        .verification-details {
            background-color: #fff9e6;
            border-left: 4px solid #ffc107;
            padding: 20px;
            margin-bottom: 20px;
            border-radius: 0 8px 8px 0;
        }

        .verification-details h3 {
            color: #856404;
            margin-bottom: 10px;
        }

        .verification-details p {
            color: #666;
            margin-bottom: 10px;
        }

        /* Popup styles */
        .verification-popup {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.8);
            backdrop-filter: blur(8px);
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 1000;
        }

>>>>>>> c1f9cd25c0f9a1e185e0bae636b4d327ccfd1232
        .popup-content {
            background: white;
            padding: 2rem;
            border-radius: 15px;
            width: 90%;
            max-width: 600px;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.2);
        }

        .popup-content h2 {
            color: #099409;
            text-align: center;
            margin-bottom: 1.5rem;
            font-size: 1.8rem;
            font-weight: bold;
        }

        .popup-content p {
            color: #666;
            margin-bottom: 1.5rem;
            text-align: center;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            color: #333;
            font-weight: 500;
        }

        .form-group select,
        .form-group input[type="text"] {
            width: 100%;
            padding: 0.8rem;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 1rem;
            transition: border-color 0.3s ease;
        }

        .form-group select:focus,
        .form-group input[type="text"]:focus {
            border-color: #099409;
            outline: none;
        }

        .form-group input[type="file"] {
            width: 100%;
            padding: 0.8rem;
            border: 2px dashed #ddd;
            border-radius: 8px;
            background: #f8f8f8;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .form-group input[type="file"]:hover {
            border-color: #099409;
            background: #fff;
        }

        .id-format-hint {
            display: block;
            color: #666;
            font-size: 0.8rem;
            margin-top: 0.4rem;
        }

        .terms-group {
            display: flex;
            align-items: center;
            gap: 0.8rem;
            margin: 1.5rem 0;
        }

        .terms-group input[type="checkbox"] {
            width: 18px;
            height: 18px;
            accent-color: #099409;
        }

        .button-group {
            text-align: center;
            margin-top: 2rem;
        }

        .btn-submit {
            background: #099409;
            color: white;
            border: none;
            padding: 1rem 2rem;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .btn-submit:hover {
            background: #067606;
            transform: translateY(-2px);
        }

        /* Scrollbar styling */
        .popup-content::-webkit-scrollbar {
            width: 8px;
        }

        .popup-content::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 4px;
        }

        .popup-content::-webkit-scrollbar-thumb {
            background: #099409;
            border-radius: 4px;
        }

        /* Responsive adjustments */
        @media (max-width: 768px) {
            .dashboard-container {
                flex-direction: column;
            }

            .sidebar {
                width: 100%;
            }

            .stats-container {
                grid-template-columns: 1fr;
            }

            .popup-content {
                padding: 1.5rem;
                width: 95%;
            }

            .popup-content h2 {
                font-size: 1.5rem;
            }
        }

        .accept-btn, .reject-btn {
            padding: 5px 10px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            margin-right: 5px;
            font-size: 12px;
        }

        .accept-btn {
            background-color: #4CAF50;
            color: white;
        }

        .reject-btn {
            background-color: #f44336;
            color: white;
        }

        /* Verification pending notification styles */
        .verification-pending-container {
            margin-bottom: 30px;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .verification-pending-header {
            background: linear-gradient(to right, #ff9966, #ff5e62);
            color: white;
            padding: 15px 20px;
            font-size: 18px;
            font-weight: 500;
            display: flex;
            align-items: center;
        }
        
        .verification-pending-header i {
            margin-right: 10px;
            font-size: 22px;
        }
        
        .verification-pending-content {
            background-color: #fff9e6;
            padding: 20px;
            border-left: 4px solid #ffc107;
        }
        
        .verification-pending-title {
            color: #8a6d3b;
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 15px;
        }
        
        .verification-pending-text {
            color: #555;
            margin-bottom: 10px;
            line-height: 1.5;
        }
        
        .verification-pending-icon {
            background-color: #ffc107;
            color: white;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 15px;
            float: left;
        }
        
        .verification-pending-message {
            overflow: hidden;
<<<<<<< HEAD
        }

        .dashboard-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            display: flex;
            align-items: center;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            transition: transform 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-5px);
        }

        .stat-icon {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 15px;
        }

        .stat-icon i {
            font-size: 24px;
            color: white;
        }

        .stat-details {
            flex-grow: 1;
        }

        .stat-details h3 {
            font-size: 14px;
            color: #666;
            margin: 0;
            margin-bottom: 5px;
        }

        .stat-details p {
            font-size: 24px;
            font-weight: bold;
            color: #333;
            margin: 0;
        }

        /* Stat icon colors */
        .stat-icon.total { background: #bb760e; }
        .stat-icon.pending { background: #ffc107; }
        .stat-icon.accepted { background: #28a745; }
        .stat-icon.completed { background: #007bff; }
        .stat-icon.rejected { background: #dc3545; }
        .stat-icon.paid { background: #20c997; }

        /* Responsive adjustments */
        @media (max-width: 768px) {
            .dashboard-stats {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 480px) {
            .dashboard-stats {
                grid-template-columns: 1fr;
            }
=======
>>>>>>> c1f9cd25c0f9a1e185e0bae636b4d327ccfd1232
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
                <li><a href="#"><i class="fas fa-star"></i> Reviews</a></li>
                <li><a href="profile.php"><i class="fas fa-user"></i> Profile</a></li>
                <li><a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
            </ul>
        </div>

        <div class="main-content">
            <div class="header">
                <h1>
                    Welcome, <?php echo htmlspecialchars($provider_name); ?>
                    <?php if ($is_verified): ?>
                        <span class="verified-badge"><i class="fas fa-check-circle"></i> Verified</span>
                    <?php endif; ?>
                </h1>
                <div class="notification-bell">
                    <i class="fas fa-bell" id="bellIcon"></i>
                    <?php if ($notification_count > 0): ?>
                        <span class="notification-badge"><?php echo $notification_count; ?></span>
                    <?php endif; ?>
                    <div id="notificationDropdown" class="dropdown-content" style="display: none;">
                        <div class="dropdown-header">
                            <h3>Notifications</h3>
                            <?php if ($notification_count > 0): ?>
                                <a href="mark_all_read.php" class="mark-all-read">Mark all as read</a>
                            <?php endif; ?>
                        </div>
                        <?php if (!empty($notifications)): ?>
                            <ul>
                                <?php foreach ($notifications as $notification): ?>
                                    <li class="notification-item">
                                        <div class="notification-title"><?php echo htmlspecialchars($notification['title']); ?></div>
                                        <div class="notification-message"><?php echo htmlspecialchars($notification['message']); ?></div>
                                        <div class="notification-time"><?php echo date('M d, Y H:i', strtotime($notification['created_at'])); ?></div>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php else: ?>
                            <div class="empty-notification">No new notifications</div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Verification error message -->
            <?php if (!empty($verification_error)): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($verification_error); ?>
            </div>
            <?php endif; ?>

            <!-- Verification pending notification -->
            <?php if ($documents_submitted && $verification_status == 'pending'): ?>
            <div class="verification-pending-container">
                <div class="verification-pending-header">
                    <i class="fas fa-user-check"></i> Verification Pending
<<<<<<< HEAD
                </div>
                <div class="verification-pending-content">
                    <div class="verification-pending-icon">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div class="verification-pending-message">
                        <div class="verification-pending-title">Your documents are under review</div>
                        <p class="verification-pending-text">
                            <strong>Thank you for submitting your verification documents.</strong> Our team is currently reviewing your information to ensure everything meets our platform standards.
                        </p>
                        <p class="verification-pending-text">
                            Your account verification is pending admin approval. You'll have limited access until your documents are verified.
                        </p>
                        <p class="verification-pending-text">
                            This usually takes 1-2 business days. We'll notify you once your account is approved.
                        </p>
                    </div>
=======
                </div>
                <div class="verification-pending-content">
                    <div class="verification-pending-icon">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div class="verification-pending-message">
                        <div class="verification-pending-title">Your documents are under review</div>
                        <p class="verification-pending-text">
                            <strong>Thank you for submitting your verification documents.</strong> Our team is currently reviewing your information to ensure everything meets our platform standards.
                        </p>
                        <p class="verification-pending-text">
                            Your account verification is pending admin approval. You'll have limited access until your documents are verified.
                        </p>
                        <p class="verification-pending-text">
                            This usually takes 1-2 business days. We'll notify you once your account is approved.
                        </p>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Notification for approval -->
            <?php if (isset($_SESSION['verification_status']) && $_SESSION['verification_status'] === 'success'): ?>
                <div class="notification">
                    <?php echo $_SESSION['verification_message']; ?>
                </div>
                <?php unset($_SESSION['verification_status'], $_SESSION['verification_message']); ?>
            <?php endif; ?>

            <div class="stats-container">
                <div class="stat-card">
                    <h3>Today's Bookings</h3>
                    <div class="value"><?php echo count($today_bookings); ?></div>
                </div>
                <div class="stat-card">
                    <h3>Average Rating</h3>
                    <div class="value"><?php echo number_format($avg_rating, 1); ?> ⭐</div>
                </div>
                <div class="stat-card">
                    <h3>Total Reviews</h3>
                    <div class="value"><?php echo $total_reviews; ?></div>
                </div>
                <div class="stat-card">
                    <h3>Pending Requests</h3>
                    <div class="value"><?php echo $pending_count; ?></div>
>>>>>>> c1f9cd25c0f9a1e185e0bae636b4d327ccfd1232
                </div>
            </div>
            <?php endif; ?>

            <!-- Notification for approval -->
            <?php if (isset($_SESSION['verification_status']) && $_SESSION['verification_status'] === 'success'): ?>
                <div class="notification">
                    <?php echo $_SESSION['verification_message']; ?>
                </div>
                <?php unset($_SESSION['verification_status'], $_SESSION['verification_message']); ?>
            <?php endif; ?>

            <div class="dashboard-stats">
                <div class="stat-card">
                    <div class="stat-icon total">
                        <i class="fas fa-calendar-check"></i>
                    </div>
                    <div class="stat-details">
                        <h3>Total Bookings</h3>
                        <p><?php echo $counts['total_bookings']; ?></p>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon pending">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div class="stat-details">
                        <h3>Pending</h3>
                        <p><?php echo $counts['pending_bookings']; ?></p>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon completed">
                        <i class="fas fa-flag-checkered"></i>
                    </div>
                    <div class="stat-details">
                        <h3>Completed</h3>
                        <p><?php echo $counts['completed_bookings']; ?></p>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon rejected">
                        <i class="fas fa-times-circle"></i>
                    </div>
                    <div class="stat-details">
                        <h3>Rejected</h3>
                        <p><?php echo $counts['rejected_bookings']; ?></p>
                    </div>
                </div>
            </div>

            <?php if (!$is_verified): ?>
            <div class="verification-banner" style="background-color: #fff3cd; padding: 15px; border-radius: 8px; margin-bottom: 20px; border-left: 5px solid #ffc107;">
                <h3 style="color: #856404; margin-top: 0;"><i class="fas fa-exclamation-triangle"></i> Verification Pending</h3>
                <?php if ($documents_submitted): ?>
                <p style="color: #856404; margin-bottom: 0;">Your documents are under review. We'll notify you once the verification is complete.</p>
                <?php else: ?>
                <p style="color: #856404; margin-bottom: 10px;">Please complete your verification to access all features.</p>
                <button id="showVerificationBtn" style="background-color: #ffc107; border: none; color: #856404; padding: 8px 15px; border-radius: 4px; cursor: pointer;">Complete Verification</button>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <?php if (!$is_verified): ?>
            <div class="verification-banner" style="background-color: #fff3cd; padding: 15px; border-radius: 8px; margin-bottom: 20px; border-left: 5px solid #ffc107;">
                <h3 style="color: #856404; margin-top: 0;"><i class="fas fa-exclamation-triangle"></i> Verification Pending</h3>
                <?php if ($documents_submitted): ?>
                <p style="color: #856404; margin-bottom: 0;">Your documents are under review. We'll notify you once the verification is complete.</p>
                <?php else: ?>
                <p style="color: #856404; margin-bottom: 10px;">Please complete your verification to access all features.</p>
                <button id="showVerificationBtn" style="background-color: #ffc107; border: none; color: #856404; padding: 8px 15px; border-radius: 4px; cursor: pointer;">Complete Verification</button>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <div class="bookings-container">
<<<<<<< HEAD
                <h2><i class="fas fa-calendar-check"></i> All Bookings</h2>
                
                <?php if (empty($bookings)): ?>
                    <div class="no-bookings">
                        <p>No bookings found.</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="booking-table">
                            <thead>
                                <tr>
                                    <th>Booking ID</th>
                                    <th>Customer</th>
                                    <th>Service</th>
                                    <th>Date & Time</th>
                                    <th>Amount</th>
                                    <th>Status</th>
                                    <th>Payment</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($bookings as $booking): ?>
                                    <tr>
                                        <td>#<?php echo $booking['booking_id']; ?></td>
                                        <td>
                                            <div class="customer-info">
                                                <strong><?php echo htmlspecialchars($booking['customer_name']); ?></strong>
                                                <small><?php echo htmlspecialchars($booking['customer_mobile']); ?></small>
                                                <small><?php echo htmlspecialchars($booking['customer_email']); ?></small>
                                            </div>
                                        </td>
                                        <td><?php echo htmlspecialchars($booking['service_name']); ?></td>
                                        <td>
                                            <?php 
                                            echo date('d M Y', strtotime($booking['booking_date'])) . '<br>';
                                            echo date('h:i A', strtotime($booking['time_slot']));
                                            ?>
                                        </td>
                                        <td>₹<?php echo number_format($booking['final_price'], 2); ?></td>
                                        <td>
                                            <span class="status-badge status-<?php echo strtolower($booking['status']); ?>">
                                                <?php echo ucfirst($booking['status']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="payment-status <?php echo strtolower($booking['payment_status']); ?>">
                                                <?php echo ucfirst($booking['payment_status']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="action-buttons">
                                                <?php if ($booking['status'] === 'pending'): ?>
                                                    <button onclick="updateBookingStatus(<?php echo $booking['booking_id']; ?>, 'accepted')" 
                                                            class="btn-action accept">
                                                        Accept
                                                    </button>
                                                    <button onclick="updateBookingStatus(<?php echo $booking['booking_id']; ?>, 'rejected')" 
                                                            class="btn-action reject">
                                                        Reject
                                                    </button>
                                                <?php elseif ($booking['status'] === 'accepted'): ?>
                                                    <button onclick="updateBookingStatus(<?php echo $booking['booking_id']; ?>, 'completed')" 
                                                            class="btn-action complete">
                                                        Complete
                                                    </button>
                                                <?php endif; ?>
                                                <button onclick="viewBookingDetails(<?php echo $booking['booking_id']; ?>)" 
                                                        class="btn-action view">
                                                    View
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
=======
                <h2>Today's Bookings</h2>
                <table class="booking-table">
                    <thead>
                        <tr>
                            <th>Time</th>
                            <th>Customer</th>
                            <th>Service</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($today_bookings) > 0): ?>
                            <?php foreach ($today_bookings as $booking): ?>
                                <tr>
                                    <td><?php echo date('H:i', strtotime($booking['booking_time'])); ?></td>
                                    <td><?php echo htmlspecialchars($booking['username']); ?></td>
                                    <td><?php echo htmlspecialchars($booking['service_name']); ?></td>
                                    <td>
                                        <span class="status-badge status-<?php echo $booking['status']; ?>">
                                            <?php echo ucfirst($booking['status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($booking['status'] === 'pending'): ?>
                                            <button onclick="updateBooking(<?php echo $booking['booking_id']; ?>, 'accepted')" class="accept-btn">Accept</button>
                                            <button onclick="updateBooking(<?php echo $booking['booking_id']; ?>, 'rejected')" class="reject-btn">Reject</button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5" style="text-align: center;">No bookings for today</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
>>>>>>> c1f9cd25c0f9a1e185e0bae636b4d327ccfd1232
            </div>
        </div>
    </div>

    <!-- Verification Popup -->
    <?php if ($show_verification_popup): ?>
        <div id="verificationPopup" class="verification-popup" style="display: flex;">
            <div class="popup-content">
                <h2>Complete Your Verification</h2>
                <p>Please complete these steps to activate your provider account:</p>
                
                <form id="verificationForm" action="process_verification.php" method="POST" enctype="multipart/form-data">
                    <div class="form-group">
                        <label for="popup_id_type">ID Type*</label>
                        <select name="id_type" id="popup_id_type" required>
                            <option value="">Select ID Type</option>
                            <option value="aadhar">Aadhar Card</option>
                            <option value="pan">PAN Card</option>
                            <option value="voter">Voter ID</option>
                            <option value="driving">Driving License</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="popup_id_number">ID Number*</label>
                        <input type="text" id="popup_id_number" name="id_number" required>
                        <small class="id-format-hint"></small>
                    </div>

                    <div class="form-group">
                        <label>ID Proof (Front)*</label>
                        <input type="file" name="id_proof_front" accept=".jpg,.jpeg,.png,.pdf" required>
                    </div>

                    <div class="form-group">
                        <label>ID Proof (Back)*</label>
                        <input type="file" name="id_proof_back" accept=".jpg,.jpeg,.png,.pdf" required>
                    </div>

                    <div class="form-group">
                        <label>Address Proof*</label>
                        <input type="file" name="address_proof" accept=".jpg,.jpeg,.png,.pdf" required>
                    </div>

                    <div class="terms-group">
                        <input type="checkbox" id="terms" name="terms" required>
                        <label for="terms">I agree to the verification terms and conditions</label>
                    </div>

                    <div class="button-group">
                        <button type="submit" class="btn-submit">Submit Verification</button>
                        <button type="button" id="closePopupBtn" style="background: #ccc; margin-left: 10px;">Cancel</button>
                    </div>
                </form>
            </div>
        </div>
    <?php endif; ?>

    <script>
        function updateBookingStatus(bookingId, status) {
            let confirmMessage = '';
            
            switch(status) {
                case 'accepted':
                    confirmMessage = 'Are you sure you want to accept this booking? You will be committed to provide this service.';
                    break;
                case 'rejected':
                    confirmMessage = 'Are you sure you want to reject this booking? This action cannot be undone.';
                    break;
                case 'completed':
                    confirmMessage = 'Are you sure you want to mark this booking as completed?';
                    break;
                default:
                    confirmMessage = 'Are you sure you want to update this booking status?';
            }

            if (confirm(confirmMessage)) {
                // Create form data
                const formData = new FormData();
                formData.append('booking_id', bookingId);
                formData.append('status', status);

                fetch('update_booking_status.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert(status === 'accepted' ? 
                            'Booking accepted successfully!' : 
                            status === 'rejected' ? 
                            'Booking rejected successfully!' :
                            'Booking status updated successfully!');
                        location.reload();
                    } else {
                        alert('Error: ' + (data.message || 'Failed to update booking status'));
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error updating booking status. Please try again.');
                });
            }
        }

<<<<<<< HEAD
        function viewBookingDetails(bookingId) {
            window.location.href = 'booking_details.php?id=' + bookingId;
        }

=======
>>>>>>> c1f9cd25c0f9a1e185e0bae636b4d327ccfd1232
        document.addEventListener('DOMContentLoaded', function() {
            const verificationPopup = document.getElementById('verificationPopup');
            const showVerificationBtn = document.getElementById('showVerificationBtn');
            const closePopupBtn = document.getElementById('closePopupBtn');
            const bellIcon = document.getElementById('bellIcon');
            const notificationDropdown = document.getElementById('notificationDropdown');

            if (showVerificationBtn) {
                showVerificationBtn.addEventListener('click', function() {
                    verificationPopup.style.display = 'flex';
                });
            }

            if (closePopupBtn) {
                closePopupBtn.addEventListener('click', function() {
                    verificationPopup.style.display = 'none';
                });
            }

            // Notification bell functionality
            if (bellIcon) {
                bellIcon.addEventListener('click', function(e) {
                    e.stopPropagation();
                    notificationDropdown.style.display = notificationDropdown.style.display === 'none' ? 'block' : 'none';
                    
                    // Mark notifications as read when opened
                    if (notificationDropdown.style.display === 'block') {
                        fetch('mark_notifications_read.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/x-www-form-urlencoded',
                            },
                            body: `user_id=<?php echo $user_id; ?>`
                        });
                    }
                });
            }

            // Close dropdown when clicking outside
            document.addEventListener('click', function(e) {
                if (notificationDropdown && notificationDropdown.style.display === 'block' && !notificationDropdown.contains(e.target) && e.target !== bellIcon) {
                    notificationDropdown.style.display = 'none';
                }
            });

            // Show notification popup if there are new notifications
            <?php if ($notification_count > 0): ?>
            const notificationBadge = document.querySelector('.notification-badge');
            if (notificationBadge) {
                notificationBadge.style.animation = 'pulse 2s infinite';
            }
            <?php endif; ?>
        });
    </script>
</body>
</html>