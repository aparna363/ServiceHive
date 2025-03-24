<?php
require_once 'dbconnect.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Verify parameters
if (!isset($_GET['booking_id']) || !isset($_GET['payment_id'])) {
    header('Location: services.php');
    exit;
}

$booking_id = $_GET['booking_id'];
$payment_id = $_GET['payment_id'];

// Fetch booking and payment details
$query = "SELECT b.*, p.*, s.service_name 
          FROM bookings b 
          JOIN payments p ON b.booking_id = p.booking_id 
          JOIN tbl_services s ON b.service_id = s.service_id 
          WHERE b.booking_id = ? AND p.transaction_id = ?";

$stmt = $conn->prepare($query);
$stmt->bind_param("is", $booking_id, $payment_id);
$stmt->execute();
$result = $stmt->get_result();
$payment_details = $result->fetch_assoc();

if (!$payment_details) {
    header('Location: services.php');
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Success - ServiceHive</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/canvas-confetti@1.6.0/dist/confetti.browser.min.js"></script>
</head>
<body>
    <div class="success-container">
        <div class="success-card">
            <div class="success-icon">
                <i class="fas fa-check-circle"></i>
            </div>
            <h1>Payment Successful!</h1>
            <p>Your booking has been confirmed.</p>
            
            <div class="payment-details">
                <div class="detail-row">
                    <span>Booking ID:</span>
                    <span>#<?php echo $booking_id; ?></span>
                </div>
                <div class="detail-row">
                    <span>Payment ID:</span>
                    <span><?php echo $payment_id; ?></span>
                </div>
                <div class="detail-row">
                    <span>Amount Paid:</span>
                    <span>₹<?php echo number_format($payment_details['amount'], 2); ?></span>
                </div>
                <div class="detail-row">
                    <span>Service:</span>
                    <span><?php echo htmlspecialchars($payment_details['service_name']); ?></span>
                </div>
            </div>

            <div class="success-actions">
                <a href="booking.php" class="primary-button">View Bookings</a>
            </div>
        </div>
    </div>

    <style>
        .success-container {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            background: #f8f9fa;
        }

        .success-card {
            background: white;
            padding: 40px;
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.05);
            text-align: center;
            max-width: 500px;
            width: 100%;
        }

        .success-icon {
            font-size: 64px;
            color: #28a745;
            margin-bottom: 20px;
        }

        .payment-details {
            margin: 30px 0;
            text-align: left;
        }

        .detail-row {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px solid #eee;
        }

        .success-actions {
            display: flex;
            gap: 15px;
            justify-content: center;
            margin-top: 30px;
        }

        .primary-button, .secondary-button {
            padding: 12px 24px;
            border-radius: 5px;
            text-decoration: none;
            font-weight: 500;
        }

        .primary-button {
            background: #bb760e;
            color: white;
        }

        .secondary-button {
            background: #f8f9fa;
            color: #333;
            border: 1px solid #ddd;
        }
    </style>

    <script>
        window.onload = function() {
            var duration = 3000;
            var end = Date.now() + duration;

            confetti({
                particleCount: 100,
                spread: 70,
                origin: { y: 0.6 }
            });

            (function frame() {
                confetti({
                    particleCount: 2,
                    angle: 60,
                    spread: 55,
                    origin: { x: 0 }
                });
                
                confetti({
                    particleCount: 2,
                    angle: 120,
                    spread: 55,
                    origin: { x: 1 }
                });

                if (Date.now() < end) {
                    requestAnimationFrame(frame);
                }
            }());
        };
    </script>
</body>
</html> 