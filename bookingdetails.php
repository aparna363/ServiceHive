<?php
session_start();
require_once 'dbconnect.php';

// Check if user is logged in and is a service provider
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'service_provider') {
    header('Location: login.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$booking_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Get provider ID
$stmt = $conn->prepare("SELECT provider_id FROM service_providers WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$provider_result = $stmt->get_result();

if ($provider_result->num_rows === 0) {
    header('Location: create_provider_profile.php');
    exit();
}

$provider_id = $provider_result->fetch_assoc()['provider_id'];

// Get booking details
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
    WHERE b.booking_id = ? AND b.provider_id = ?
");
$stmt->bind_param("ii", $booking_id, $provider_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    // Booking not found or doesn't belong to this provider
    header('Location: provider_dashboard.php');
    exit();
}

$booking = $result->fetch_assoc();

// Now fetch the address separately using the user_id from the booking
$address_stmt = $conn->prepare("
    SELECT id, address, district, city, state, postal_code, is_default
    FROM service_addresses
    WHERE user_id = ?
    ORDER BY is_default DESC, id DESC
    LIMIT 1
");
$address_stmt->bind_param("i", $booking['user_id']);
$address_stmt->execute();
$address_result = $address_stmt->get_result();
$address_data = $address_result->fetch_assoc();

// Calculate provider earnings (70% of total)
$provider_earnings = $booking['final_price'] * 0.7;
$admin_commission = $booking['final_price'] * 0.3;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Booking Details - ServiceHive</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Poppins', sans-serif;
        }
        
        body {
            background-color: #f8f9fa;
            color: #333;
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
        
        .back-link {
            display: inline-flex;
            align-items: center;
            margin-bottom: 25px;
            color: #4a6cf7;
            text-decoration: none;
            font-weight: 500;
            transition: all 0.3s ease;
        }
        
        .back-link:hover {
            color: #2a4df5;
        }
        
        .back-link i {
            margin-right: 8px;
            font-size: 14px;
        }
        
        .booking-details-card {
            background-color: white;
            border-radius: 12px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.05);
            overflow: hidden;
            margin-bottom: 30px;
        }
        
        .booking-header {
            background: linear-gradient(135deg, #bb760e, rgb(171, 46, 8));
            color: white;
            padding: 25px 30px;
            position: relative;
        }
        
        .booking-header h1 {
            font-size: 24px;
            font-weight: 600;
            margin-bottom: 8px;
        }
        
        .booking-id {
            font-size: 15px;
            opacity: 0.9;
            font-weight: 400;
        }
        
        .status-badge {
            position: absolute;
            top: 25px;
            right: 30px;
            padding: 8px 16px;
            border-radius: 30px;
            font-weight: 500;
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .status-pending { background: #fff8e1; color: #f57c00; }
        .status-accepted { background: #e8f5e9; color: #2e7d32; }
        .status-completed { background: #e3f2fd; color: #1565c0; }
        .status-rejected { background: #ffebee; color: #c62828; }
        
        .booking-body {
            padding: 30px;
        }
        
        .info-section {
            margin-bottom: 35px;
        }
        
        .info-section h2 {
            font-size: 18px;
            color: #333;
            margin-bottom: 20px;
            padding-bottom: 12px;
            border-bottom: 1px solid #eee;
            font-weight: 600;
        }
        
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 25px;
        }
        
        .info-item {
            margin-bottom: 15px;
        }
        
        .info-label {
            font-size: 14px;
            color: #666;
            margin-bottom: 8px;
            font-weight: 500;
        }
        
        .info-value {
            font-size: 16px;
            color: #333;
            font-weight: 500;
        }
        
        .customer-details {
            background-color: #f8f9fa;
            padding: 25px;
            border-radius: 10px;
            margin-bottom: 35px;
            border: 1px solid #eee;
        }
        
        .customer-details h3 {
            font-size: 17px;
            margin-bottom: 20px;
            color: #333;
            font-weight: 600;
        }
        
        .customer-info {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 25px;
        }
        
        .services-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0,0,0,0.03);
        }
        
        .services-table th,
        .services-table td {
            padding: 15px 20px;
            text-align: left;
        }
        
        .services-table th {
            background-color: #f8f9fa;
            font-weight: 600;
            color: #333;
            border-bottom: 1px solid #eee;
        }
        
        .services-table td {
            border-bottom: 1px solid #eee;
        }
        
        .services-table tr:last-child td {
            border-bottom: none;
        }
        
        .price-breakdown {
            margin-top: 30px;
            background-color: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
        }
        
        .price-item {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px dashed #ddd;
        }
        
        .price-item:last-child {
            border-bottom: none;
        }
        
        .price-label {
            font-weight: 500;
            color: #555;
        }
        
        .price-value {
            font-weight: 600;
        }
        
        .total-price {
            margin-top: 10px;
            padding-top: 15px;
            border-top: 2px solid #ddd;
            font-size: 18px;
            font-weight: 600;
            color: #333;
        }
        
        .commission-info {
            color: #e53935;
        }
        
        .earnings-info {
            color: #43a047;
        }
        
        .action-buttons {
            display: flex;
            gap: 15px;
            margin-top: 35px;
        }
        
        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            font-size: 15px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }
        
        .btn i {
            margin-right: 8px;
        }
        
        .btn-primary {
            background-color: #bb760e;
            color: white;
        }
        
        .btn-success {
            background-color: #43a047;
            color: white;
        }
        
        .btn-danger {
            background-color: #e53935;
            color: white;
        }
        
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        .payment-badge {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 6px;
            font-size: 14px;
            font-weight: 500;
        }
        
        .payment-paid {
            background-color: #e8f5e9;
            color: #2e7d32;
        }
        
        .payment-pending {
            background-color: #fff8e1;
            color: #f57c00;
        }
        
        @media (max-width: 768px) {
            .dashboard-container {
                flex-direction: column;
            }
            
            .sidebar {
                width: 100%;
            }
            
            .container {
                padding: 15px;
                margin: 20px auto;
            }
            
            .booking-header {
                padding: 20px;
            }
            
            .booking-body {
                padding: 20px;
            }
            
            .info-grid, .customer-info {
                grid-template-columns: 1fr;
                gap: 15px;
            }
            
            .action-buttons {
                flex-direction: column;
            }
            
            .btn {
                width: 100%;
            }
            
            .status-badge {
                position: static;
                display: inline-block;
                margin-top: 10px;
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
                <li><a href="provider-review.php"><i class="fas fa-star"></i> Reviews</a></li>
                <li><a href="contact_support.php"><i class="fas fa-headset"></i> Help</a></li>
                <li><a href="profile.php"><i class="fas fa-user"></i> Profile</a></li>
                <li><a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
            </ul>
        </div>
        
        <div class="main-content">
            <div class="header">
                <h1>Booking Details</h1>
            </div>
            
            <a href="provider_dashboard.php" class="back-link"><i class="fas fa-arrow-left"></i> Back to Dashboard</a>
            
            <div class="booking-details-card">
                <div class="booking-header">
                    <h1>Booking Details</h1>
                    <div class="booking-id">Booking #<?php echo $booking['booking_id']; ?></div>
                    <span class="status-badge status-<?php echo strtolower($booking['status']); ?>">
                        <?php echo ucfirst($booking['status']); ?>
                    </span>
                </div>
                
                <div class="booking-body">
                    <div class="info-section">
                        <h2>Service Information</h2>
                        <div class="info-grid">
                            <div class="info-item">
                                <div class="info-label">Service</div>
                                <div class="info-value"><?php echo htmlspecialchars($booking['service_name']); ?></div>
                            </div>
                            
                            <div class="info-item">
                                <div class="info-label">Date</div>
                                <div class="info-value"><?php echo date('F d, Y', strtotime($booking['booking_date'])); ?></div>
                            </div>
                            
                            <div class="info-item">
                                <div class="info-label">Payment Status</div>
                                <div class="info-value">
                                    <span class="payment-badge payment-<?php echo strtolower($booking['payment_status']); ?>">
                                        <?php echo ucfirst($booking['payment_status']); ?>
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="customer-details">
                        <h3>Customer Information</h3>
                        <div class="customer-info">
                            <div class="info-item">
                                <div class="info-label">Name</div>
                                <div class="info-value"><?php echo htmlspecialchars($booking['customer_name']); ?></div>
                            </div>
                            
                            <div class="info-item">
                                <div class="info-label">Phone</div>
                                <div class="info-value"><?php echo htmlspecialchars($booking['customer_mobile']); ?></div>
                            </div>
                            
                            <div class="info-item">
                                <div class="info-label">Email</div>
                                <div class="info-value"><?php echo htmlspecialchars($booking['customer_email']); ?></div>
                            </div>
                            
                            <div class="info-item">
                                <div class="info-label">Address</div>
                                <div class="info-value">
                                    <?php 
                                    if ($address_data) {
                                        $address_parts = [];
                                        if (!empty($address_data['address'])) $address_parts[] = htmlspecialchars($address_data['address']);
                                        if (!empty($address_data['district'])) $address_parts[] = htmlspecialchars($address_data['district']);
                                        if (!empty($address_data['city'])) $address_parts[] = htmlspecialchars($address_data['city']);
                                        if (!empty($address_data['state'])) $address_parts[] = htmlspecialchars($address_data['state']);
                                        if (!empty($address_data['postal_code'])) $address_parts[] = htmlspecialchars($address_data['postal_code']);
                                        
                                        echo !empty($address_parts) ? implode(', ', $address_parts) : 'No address provided';
                                    } else {
                                        echo 'No address provided';
                                    }
                                    ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="info-section">
                        <h2>Service Details</h2>
                        
                        <table class="services-table">
                            <thead>
                                <tr>
                                    <th>Service</th>
                                    <th>Price</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td><?php echo htmlspecialchars($booking['service_name']); ?></td>
                                    <td>₹<?php echo number_format($booking['final_price'], 2); ?></td>
                                </tr>
                            </tbody>
                        </table>
                        
                        <div class="price-breakdown">
                            <div class="price-item">
                                <span class="price-label">Subtotal</span>
                                <span class="price-value">₹<?php echo number_format($booking['final_price'], 2); ?></span>
                            </div>
                            
                            <?php if ($booking['status'] === 'completed' && $booking['payment_status'] === 'paid'): ?>
                                <div class="price-item commission-info">
                                    <span class="price-label">Admin Commission (30%)</span>
                                    <span class="price-value">-₹<?php echo number_format($admin_commission, 2); ?></span>
                                </div>
                                
                                <div class="price-item earnings-info">
                                    <span class="price-label">Your Earnings (70%)</span>
                                    <span class="price-value">₹<?php echo number_format($provider_earnings, 2); ?></span>
                                </div>
                            <?php endif; ?>
                            
                            <div class="price-item total-price">
                                <span class="price-label">Total Amount</span>
                                <span class="price-value">₹<?php echo number_format($booking['final_price'], 2); ?></span>
                            </div>
                        </div>
                    </div>
                    
                    <?php if ($booking['status'] === 'pending'): ?>
                        <div class="action-buttons">
                            <button onclick="updateBookingStatus(<?php echo $booking['booking_id']; ?>, 'accepted')" class="btn btn-success">
                                <i class="fas fa-check"></i> Accept Booking
                            </button>
                            <button onclick="updateBookingStatus(<?php echo $booking['booking_id']; ?>, 'rejected')" class="btn btn-danger">
                                <i class="fas fa-times"></i> Reject Booking
                            </button>
                        </div>
                    <?php elseif ($booking['status'] === 'accepted'): ?>
                        <div class="action-buttons">
                            <button onclick="updateBookingStatus(<?php echo $booking['booking_id']; ?>, 'completed')" class="btn btn-primary">
                                <i class="fas fa-flag-checkered"></i> Mark as Completed
                            </button>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
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
                formData.append('booking_type', 'regular');

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
                        window.location.href = 'provider_dashboard.php';
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
    </script>
</body>
</html>