<?php
require_once 'dbconnect.php';
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Check if booking ID is provided
if (!isset($_GET['id'])) {
    header('Location: booking.php');
    exit;
}

$booking_id = $_GET['id'];
$user_id = $_SESSION['user_id'];

// Update the query to properly fetch the price
$query = "SELECT 
    b.*,
    s.service_name,
    s.price,
    sp.business_name as provider_name,
    sp.provider_id,
    c.category_name,
    u.mobile as provider_mobile,
    COALESCE(b.total_amount, b.total_price) as final_price  -- Use total_amount if available, otherwise use total_price
    FROM bookings b
    JOIN tbl_services s ON b.service_id = s.service_id
    JOIN service_providers sp ON b.provider_id = sp.provider_id
    JOIN tbl_categories c ON s.category_id = c.category_id
    JOIN users u ON sp.user_id = u.id
    WHERE b.booking_id = ? AND b.user_id = ?";

$stmt = $conn->prepare($query);
$stmt->bind_param("ii", $booking_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    header('Location: bookings.php');
    exit;
}

$booking = $result->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Booking Details - ServiceHive</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: Arial, sans-serif;
        }

        body {
            background-color: #f8f9fa;
            padding: 20px;
        }

        .container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            border-radius: 15px;
            box-shadow: 0 2px 15px rgba(0,0,0,0.1);
            overflow: hidden;
        }

        .booking-header {
            background: linear-gradient(135deg, #bb760e, #ec8908);
            color: white;
            padding: 30px;
            text-align: center;
        }

        .booking-id {
            font-size: 24px;
            font-weight: bold;
            margin-bottom: 10px;
        }

        .booking-date {
            font-size: 16px;
            opacity: 0.9;
        }

        .booking-content {
            padding: 30px;
        }

        .section {
            margin-bottom: 30px;
            border-bottom: 1px solid #eee;
            padding-bottom: 20px;
        }

        .section:last-child {
            border-bottom: none;
            margin-bottom: 0;
            padding-bottom: 0;
        }

        .section-title {
            font-size: 18px;
            color: #2c3e50;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .info-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 15px;
        }

        .info-item {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }

        .info-label {
            color: #666;
            font-size: 14px;
        }

        .info-value {
            color: #2c3e50;
            font-weight: 500;
        }

        .status-badge {
            display: inline-block;
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: 600;
        }

        .status-pending {
            background: #fff3cd;
            color: #856404;
        }

        .status-accepted {
            background: #d4edda;
            color: #155724;
        }

        .status-completed {
            background: #cce5ff;
            color: #004085;
        }

        .status-cancelled {
            background: #f8d7da;
            color: #721c24;
        }

        .payment-pending {
            background: #fff3cd;
            color: #856404;
        }

        .payment-paid {
            background: #d4edda;
            color: #155724;
        }

        .payment-refunded {
            background: #f8d7da;
            color: #721c24;
        }

        .action-buttons {
            display: flex;
            gap: 15px;
            margin-top: 30px;
        }

        .btn {
            flex: 1;
            padding: 12px;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            text-align: center;
            text-decoration: none;
            transition: all 0.3s ease;
        }

        .btn-primary {
            background: #bb760e;
            color: white;
        }

        .btn-danger {
            background: #dc3545;
            color: white;
        }

        .btn:hover {
            opacity: 0.9;
            transform: translateY(-2px);
        }

        .back-button {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            color: #666;
            text-decoration: none;
            margin-bottom: 20px;
            font-size: 14px;
        }

        .back-button:hover {
            color: #333;
        }

        @media (max-width: 768px) {
            .info-grid {
                grid-template-columns: 1fr;
            }
            
            .container {
                margin: 10px;
            }
            
            .booking-header {
                padding: 20px;
            }
            
            .booking-content {
                padding: 20px;
            }
        }

        .receipt-link {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            color: #bb760e;
            text-decoration: none;
            font-weight: 500;
            transition: all 0.2s ease;
        }
        
        .receipt-link:hover {
            color: #ec8908;
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="container">
        <a href="booking.php" class="back-button">
            <i class="fas fa-arrow-left"></i> Back to Bookings
        </a>

        <div class="booking-header">
            <div class="booking-id">Booking #<?php echo $booking['booking_id']; ?></div>
            <div class="booking-date">
                <?php echo date('d M Y, h:i A', strtotime($booking['created_at'])); ?>
            </div>
        </div>

        <div class="booking-content">
            <div class="section">
                <h2 class="section-title">
                    <i class="fas fa-tools"></i> Service Details
                </h2>
                <div class="info-grid">
                    <div class="info-item">
                        <span class="info-label">Service</span>
                        <span class="info-value"><?php echo htmlspecialchars($booking['service_name']); ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Category</span>
                        <span class="info-value"><?php echo htmlspecialchars($booking['category_name']); ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Provider</span>
                        <span class="info-value"><?php echo htmlspecialchars($booking['provider_name']); ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Provider Contact</span>
                        <span class="info-value"><?php echo htmlspecialchars($booking['provider_mobile']); ?></span>
                    </div>
                </div>
            </div>

            <div class="section">
                <h2 class="section-title">
                    <i class="fas fa-calendar-alt"></i> Schedule
                </h2>
                <div class="info-grid">
                    <div class="info-item">
                        <span class="info-label">Date</span>
                        <span class="info-value">
                            <?php echo date('d M Y', strtotime($booking['booking_date'])); ?>
                        </span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Time</span>
                        <span class="info-value">
                            <?php 
                            // Convert time to 12-hour format with AM/PM
                            echo date('h:i A', strtotime($booking['time_slot'])); 
                            ?>
                        </span>
                    </div>
                </div>
            </div>

            <div class="section">
                <h2 class="section-title">
                    <i class="fas fa-info-circle"></i> Status Information
                </h2>
                <div class="info-grid">
                    <div class="info-item">
                        <span class="info-label">Booking Status</span>
                        <span class="status-badge status-<?php echo strtolower($booking['status']); ?>">
                            <?php echo ucfirst($booking['status']); ?>
                        </span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Payment Status</span>
                        <span class="status-badge payment-<?php echo strtolower($booking['payment_status']); ?>">
                            <?php echo ucfirst($booking['payment_status']); ?>
                        </span>
                    </div>
                </div>
            </div>

            <div class="section">
                <h2 class="section-title">
                    <i class="fas fa-receipt"></i> Payment Details
                </h2>
                <div class="info-grid">
                    <div class="info-item">
                        <span class="info-label">Amount</span>
                        <span class="info-value">₹<?php echo number_format(floatval($booking['final_price']), 2); ?></span>
                    </div>
                    <?php if ($booking['payment_id']): ?>
                    <div class="info-item">
                        <span class="info-label">Payment ID</span>
                        <span class="info-value"><?php echo htmlspecialchars($booking['payment_id']); ?></span>
                    </div>
                    <?php endif; ?>
                    <?php if ($booking['payment_method']): ?>
                    <div class="info-item">
                        <span class="info-label">Payment Method</span>
                        <span class="info-value"><?php echo ucfirst(htmlspecialchars($booking['payment_method'])); ?></span>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($booking['payment_status'] === 'paid'): ?>
                    <div class="info-item">
                        <span class="info-label">Receipt</span>
                        <span class="info-value">
                            <a href="generate_receipt.php?booking_id=<?php echo $booking['booking_id']; ?>" class="receipt-link" target="_blank">
                                <i class="fas fa-download"></i> Download Receipt
                            </a>
                        </span>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <?php if ($booking['status'] === 'pending'): ?>
            <div class="action-buttons">
                <button onclick="cancelBooking(<?php echo $booking['booking_id']; ?>)" class="btn btn-danger">
                    Cancel Booking
                </button>
                <?php if ($booking['payment_status'] === 'pending'): ?>
                <a href="payment.php?booking_id=<?php echo $booking['booking_id']; ?>" class="btn btn-primary">
                    Make Payment
                </a>
                <?php endif; ?>
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