<?php
session_start();
require_once 'dbconnect.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Check if booking_id is provided
if (!isset($_GET['booking_id']) || empty($_GET['booking_id'])) {
    header("Location: bookings.php");
    exit();
}

$booking_id = $_GET['booking_id'];
$user_id = $_SESSION['user_id'];
$error = isset($_GET['error']) ? $_GET['error'] : 'Payment could not be processed';

// Verify that the booking belongs to the user
$query = "SELECT b.*, s.service_name 
          FROM bookings b
          JOIN tbl_services s ON b.service_id = s.service_id
          WHERE b.booking_id = ? AND b.user_id = ?";

$stmt = $conn->prepare($query);
$stmt->bind_param("ii", $booking_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    // Booking not found or doesn't belong to this user
    header("Location: bookings.php");
    exit();
}

$booking = $result->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Failed - ServicesHive</title>
    <link rel="stylesheet" href="css/style.css">
    <style>
        .failed-container {
            max-width: 800px;
            margin: 50px auto;
            padding: 30px;
            background-color: #fff;
            border-radius: 10px;
            box-shadow: 0 0 20px rgba(0, 0, 0, 0.1);
            text-align: center;
        }
        .failed-icon {
            font-size: 80px;
            color: #f44336;
            margin-bottom: 20px;
        }
        .failed-message {
            margin-bottom: 30px;
        }
        .failed-message h2 {
            color: #f44336;
            margin-bottom: 15px;
        }
        .booking-details {
            margin: 30px auto;
            max-width: 500px;
            text-align: left;
            padding: 20px;
            background-color: #f9f9f9;
            border-radius: 5px;
        }
        .detail-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
            padding-bottom: 10px;
            border-bottom: 1px solid #eee;
        }
        .detail-row:last-child {
            border-bottom: none;
        }
        .detail-label {
            font-weight: bold;
            color: #555;
        }
        .action-buttons {
            margin-top: 30px;
        }
        .btn {
            display: inline-block;
            padding: 12px 25px;
            margin: 0 10px;
            border-radius: 5px;
            text-decoration: none;
            font-weight: bold;
            transition: background-color 0.3s;
        }
        .btn-primary {
            background-color: #4CAF50;
            color: white;
        }
        .btn-secondary {
            background-color: #f1f1f1;
            color: #333;
        }
        .btn:hover {
            opacity: 0.9;
        }
        .error-details {
            margin-top: 20px;
            color: #777;
            font-size: 14px;
        }
    </style>
</head>
<body>
    <?php include 'header.php'; ?>

    <div class="failed-container">
        <div class="failed-icon">✗</div>
        
        <div class="failed-message">
            <h2>Payment Failed</h2>
            <p>We couldn't process your payment. Please try again.</p>
        </div>
        
        <div class="booking-details">
            <div class="detail-row">
                <span class="detail-label">Booking ID:</span>
                <span>#<?php echo $booking_id; ?></span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Service:</span>
                <span><?php echo htmlspecialchars($booking['service_name']); ?></span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Amount:</span>
                <span>₹<?php echo number_format($booking['total_price'], 2); ?></span>
            </div>
        </div>
        
        <div class="error-details">
            <p>Error: <?php echo htmlspecialchars($error); ?></p>
        </div>
        
        <div class="action-buttons">
            <a href="payment.php?booking_id=<?php echo $booking_id; ?>" class="btn btn-primary">Try Again</a>
            <a href="bookings.php" class="btn btn-secondary">View My Bookings</a>
        </div>
    </div>

    <?php include 'footer.php'; ?>
</body>
</html> 