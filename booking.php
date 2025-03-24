<?php
require_once 'dbconnect.php';
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['user_id'];

// Fetch user's bookings with service and provider details
$booking_query = "SELECT 
    b.*,
    s.service_name,
    s.price,
    sp.business_name as provider_name,
    c.category_name,
    COALESCE(b.total_amount, b.total_price) as final_price
    FROM bookings b
    JOIN tbl_services s ON b.service_id = s.service_id
    JOIN service_providers sp ON b.provider_id = sp.provider_id
    JOIN tbl_categories c ON s.category_id = c.category_id
    WHERE b.user_id = ?
    ORDER BY b.created_at DESC";

$stmt = $conn->prepare($booking_query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Bookings - ServiceHive</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Poppins', sans-serif;
        }

        body {
            background-color: #f5f7fa;
            color: #333;
            line-height: 1.6;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 30px 20px;
        }

        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 40px;
            border-bottom: 1px solid #e0e0e0;
            padding-bottom: 15px;
        }

        .page-title {
            font-size: 28px;
            font-weight: 600;
            color: #2c3e50;
        }

        .bookings-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 25px;
        }

        .booking-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
            overflow: hidden;
            transition: all 0.3s ease;
            border: 1px solid #eaeaea;
        }

        .booking-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
        }

        .booking-header {
            background: linear-gradient(135deg, #3a7bd5, #00d2ff);
            color: white;
            padding: 18px 20px;
            font-size: 18px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .booking-body {
            padding: 25px;
        }

        .booking-info {
            margin-bottom: 20px;
        }

        .info-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 12px;
            font-size: 14px;
            align-items: center;
        }

        .info-label {
            color: #6c757d;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .info-value {
            color: #2c3e50;
            font-weight: 600;
            text-align: right;
        }

        .booking-status, .payment-status {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 30px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .status-pending {
            background: #fff8e1;
            color: #f57c00;
        }

        .status-accepted {
            background: #e8f5e9;
            color: #2e7d32;
        }

        .status-completed {
            background: #e3f2fd;
            color: #1565c0;
        }

        .status-cancelled {
            background: #ffebee;
            color: #c62828;
        }

        .payment-pending {
            background: #fff8e1;
            color: #f57c00;
        }

        .payment-paid {
            background: #e8f5e9;
            color: #2e7d32;
        }

        .payment-refunded {
            background: #ffebee;
            color: #c62828;
        }

        .booking-actions {
            margin-top: 25px;
            display: flex;
            gap: 15px;
        }

        .action-button {
            padding: 10px 18px;
            border-radius: 8px;
            border: none;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            text-decoration: none;
            text-align: center;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .view-details {
            background: #3a7bd5;
            color: white;
            flex: 1;
        }

        .cancel-booking {
            background: #f44336;
            color: white;
            flex: 1;
        }

        .action-button:hover {
            opacity: 0.9;
            transform: translateY(-2px);
        }

        .no-bookings {
            text-align: center;
            padding: 80px 0;
            color: #6c757d;
            background: white;
            border-radius: 12px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
        }

        .no-bookings i {
            font-size: 60px;
            color: #d1d1d1;
            margin-bottom: 20px;
        }

        .no-bookings p {
            font-size: 18px;
            margin-bottom: 20px;
        }

        .no-bookings .action-button {
            display: inline-flex;
            margin-top: 15px;
        }

        .divider {
            height: 1px;
            background-color: #eaeaea;
            margin: 15px 0;
        }

        .price-row {
            font-size: 18px;
            font-weight: 700;
            color: #2c3e50;
            margin-top: 15px;
            display: flex;
            justify-content: space-between;
        }

        .badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 10px;
            font-weight: 600;
            text-transform: uppercase;
            margin-left: 8px;
            vertical-align: middle;
        }

        .badge-new {
            background: #e3f2fd;
            color: #1565c0;
        }

        @media (max-width: 768px) {
            .bookings-grid {
                grid-template-columns: 1fr;
            }
            
            .booking-card {
                margin: 10px 0;
            }

            .page-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 10px;
            }
        }

        /* Add Google Font */
        @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap');

        .back-button {
            background: #071642;
            color: white;
            padding: 8px 16px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s ease;
        }

        .back-button:hover {
            background: #e64a19;
            transform: translateY(-2px);
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="page-header">
            <h1 class="page-title"><i class="fas fa-calendar-check"></i> My Bookings</h1>
            <a href="index.php" class="back-button"><i class="fas fa-arrow-left"></i> Back to Home</a>
        </div>

        <div class="bookings-grid">
            <?php if ($result->num_rows > 0): ?>
                <?php while ($booking = $result->fetch_assoc()): ?>
                    <div class="booking-card">
                        <div class="booking-header">
                            <i class="fas fa-tools"></i> 
                            <?php echo htmlspecialchars($booking['service_name']); ?>
                            <?php if (strtotime($booking['created_at']) > strtotime('-3 days')): ?>
                                <span class="badge badge-new">New</span>
                            <?php endif; ?>
                        </div>
                        
                        <div class="booking-body">
                            <div class="booking-info">
                                <div class="info-row">
                                    <span class="info-label"><i class="fas fa-hashtag"></i> Booking ID:</span>
                                    <span class="info-value">#<?php echo $booking['booking_id']; ?></span>
                                </div>
                                
                                <div class="info-row">
                                    <span class="info-label"><i class="fas fa-tag"></i> Category:</span>
                                    <span class="info-value"><?php echo htmlspecialchars($booking['category_name']); ?></span>
                                </div>
                                
                                <div class="info-row">
                                    <span class="info-label"><i class="fas fa-store"></i> Provider:</span>
                                    <span class="info-value"><?php echo htmlspecialchars($booking['provider_name']); ?></span>
                                </div>
                                
                                <div class="info-row">
                                    <span class="info-label"><i class="far fa-calendar-alt"></i> Date:</span>
                                    <span class="info-value">
                                        <?php echo date('D, d M Y', strtotime($booking['booking_date'])); ?>
                                    </span>
                                </div>
                                
                                <div class="info-row">
                                    <span class="info-label"><i class="far fa-clock"></i> Time:</span>
                                    <span class="info-value">
                                        <?php 
                                        // Direct approach without using strtotime()
                                        $time_parts = explode(':', $booking['time_slot']);
                                        $hour = (int)$time_parts[0];
                                        $minute = isset($time_parts[1]) ? $time_parts[1] : '00';
                                        
                                        // Determine if it's AM or PM
                                        $ampm = ($hour >= 12) ? 'PM' : 'AM';
                                        
                                        // Convert to 12-hour format
                                        $hour_12 = ($hour > 12) ? $hour - 12 : ($hour == 0 ? 12 : $hour);
                                        
                                        // Format with leading zeros
                                        $hour_display = sprintf('%02d', $hour_12);
                                        
                                        echo $hour_display . ':' . $minute . ' ' . $ampm;
                                        ?>
                                    </span>
                                </div>
                                
                                <div class="divider"></div>
                                
                                <div class="info-row">
                                    <span class="info-label"><i class="fas fa-tasks"></i> Status:</span>
                                    <span class="booking-status status-<?php echo strtolower($booking['status']); ?>">
                                        <?php echo ucfirst($booking['status']); ?>
                                    </span>
                                </div>
                                
                                <div class="info-row">
                                    <span class="info-label"><i class="fas fa-money-bill-wave"></i> Payment:</span>
                                    <span class="payment-status payment-<?php echo strtolower($booking['payment_status']); ?>">
                                        <?php echo ucfirst($booking['payment_status']); ?>
                                    </span>
                                </div>
                                
                                <div class="price-row">
                                    <span>Total Amount:</span>
                                    <span>₹<?php echo number_format(floatval($booking['final_price']), 2); ?></span>
                                </div>
                            </div>
                            
                            <div class="booking-actions">
                                <a href="booking_details.php?id=<?php echo $booking['booking_id']; ?>" 
                                   class="action-button view-details">
                                    <i class="fas fa-eye"></i> View Details
                                </a>
                                
                                <?php if ($booking['status'] == 'pending' || $booking['status'] == 'accepted'): ?>
                                    <button onclick="cancelBooking(<?php echo $booking['booking_id']; ?>)" 
                                            class="action-button cancel-booking">
                                        <i class="fas fa-times"></i> Cancel
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="no-bookings">
                    <i class="fas fa-calendar-times"></i>
                    <p>You don't have any bookings yet.</p>
                    <p>Book a service to get started with ServiceHive!</p>
                    <a href="services.php" class="action-button view-details">
                        <i class="fas fa-search"></i> Browse Services
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        function cancelBooking(bookingId) {
            if (confirm('Are you sure you want to cancel this booking?')) {
                fetch('cancel_booking.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        booking_id: bookingId
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Booking cancelled successfully');
                        location.reload();
                    } else {
                        alert('Failed to cancel booking: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('An error occurred while cancelling the booking');
                });
            }
        }
    </script>
</body>
</html> 