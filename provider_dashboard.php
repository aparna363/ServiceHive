<?php
session_start();
require_once 'dbconnect.php';

// Check if service provider is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'service_provider') {
    header('Location: login.php');
    exit();
}

// Get service provider details
$provider_id = $_SESSION['user_id'];
$stmt = $conn->prepare("SELECT * FROM service_providers WHERE user_id = ?");
$stmt->bind_param("i", $provider_id);
$stmt->execute();
$provider = $stmt->get_result()->fetch_assoc();

// Get pending bookings count
$stmt = $conn->prepare("
    SELECT COUNT(*) as pending_count 
    FROM bookings 
    WHERE provider_id = ? AND status = 'pending'
");
$stmt->bind_param("i", $provider_id);
$stmt->execute();
$pending_count = $stmt->get_result()->fetch_assoc()['pending_count'];

// Get today's bookings
$today = date('Y-m-d');
$stmt = $conn->prepare("
    SELECT b.*, u.username, s.service_name
    FROM bookings b
    JOIN users u ON b.user_id = u.id
    JOIN tbl_services s ON b.service_id = s.service_id
    WHERE b.provider_id = ? AND DATE(b.booking_date) = ?
    ORDER BY b.time_slot
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
            background-color: #E67E22;  /* Lighter orange for hover effect */
            border-radius: 5px;
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
            background-color:rgb(171, 46, 8);
            border-radius: 5px;
        }

        .sidebar-menu i {
            margin-right: 10px;
        }

        .main-content {
            flex: 1;
            padding: 20px;
            background-color: #f4f4f4;
        }

        .dashboard-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
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
            background-color: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 5px rgba(193, 67, 9, 0.1);
        }

        .bookings-container h2 {
            margin-bottom: 20px;
            color: #333;
        }

        .booking-table {
            width: 100%;
            border-collapse: collapse;
        }

        .booking-table th, .booking-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }

        .booking-table th {
            background-color: #f8f8f8;
            color: #333;
        }

        .status-badge {
            padding: 5px 10px;
            border-radius: 15px;
            font-size: 12px;
        }

        .status-pending {
            background-color: #ffeeba;
            color: #856404;
        }

        .status-accepted {
            background-color: #d4edda;
            color: #155724;
        }

        .status-completed {
            background-color: #cce5ff;
            color: #004085;
        }

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
                <li><a href="index.php"><i class="fas fa-home"></i> Dashboard</a></li>
                <li><a href="#"><i class="fas fa-calendar"></i> Bookings</a></li>
                <li><a href="service-management.php"><i class="fas fa-tools"></i> Services</a></li>
                <li><a href="subservice-management.php"><i class="fas fa-tools"></i> Sub Services</a></li>
                <li><a href="#"><i class="fas fa-star"></i> Reviews</a></li>
                <li><a href="profile.php"><i class="fas fa-user"></i> Profile</a></li>
                <li><a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
            </ul>
        </div>

        <div class="main-content">
            <div class="dashboard-header">
            <h1 style="color:rgb(215, 79, 6);">Welcome, <?php echo htmlspecialchars($_SESSION['username']); ?></h1>
                <div class="notification-icon">
                    <i class="fas fa-bell"></i>
                    <?php if ($pending_count > 0): ?>
                        <span class="badge"><?php echo $pending_count; ?></span>
                    <?php endif; ?>
                </div>
            </div>

            <div class="stats-container">
                <div class="stat-card">
                    <h3>Today's Bookings</h3>
                    <div class="value"><?php echo count($today_bookings); ?></div>
                </div>
                <div class="stat-card">
                    <h3>Average Rating</h3>
                    <div class="value"><?php echo number_format($ratings['avg_rating'], 1); ?> ⭐</div>
                </div>
                <div class="stat-card">
                    <h3>Total Reviews</h3>
                    <div class="value"><?php echo $ratings['total_reviews']; ?></div>
                </div>
                <div class="stat-card">
                    <h3>Pending Requests</h3>
                    <div class="value"><?php echo $pending_count; ?></div>
                </div>
            </div>

            <div class="bookings-container">
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
                        <?php foreach ($today_bookings as $booking): ?>
                            <tr>
                                <td><?php echo date('H:i', strtotime($booking['time_slot'])); ?></td>
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
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script>
        function updateBooking(bookingId, status) {
            if (confirm('Are you sure you want to ' + status + ' this booking?')) {
                fetch('update_booking.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `booking_id=${bookingId}&status=${status}`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        location.reload();
                    } else {
                        alert('Error updating booking status');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error updating booking status');
                });
            }
        }
    </script>
</body>
</html>