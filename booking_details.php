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

// Function to check if user has already reviewed this booking
function hasReview($conn, $booking_id) {
    $query = "SELECT id FROM reviews WHERE booking_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $booking_id);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->num_rows > 0;
}

// Function to get review details
function getReview($conn, $booking_id) {
    $query = "SELECT * FROM reviews WHERE booking_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $booking_id);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->fetch_assoc();
}
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

        .review-form {
            background: #f9f9f9;
            padding: 20px;
            border-radius: 8px;
            margin-top: 15px;
        }

        .rating-container {
            display: flex;
            align-items: center;
            margin-bottom: 20px;
        }

        .rating-label {
            margin-right: 15px;
            font-weight: 500;
        }

        .star-rating {
            display: flex;
            flex-direction: row-reverse;
            justify-content: flex-end;
        }

        .star-rating input {
            display: none;
        }

        .star-rating label {
            cursor: pointer;
            width: 30px;
            height: 30px;
            background-image: url('data:image/svg+xml;charset=UTF-8,<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="%23ddd" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>');
            background-repeat: no-repeat;
            background-position: center;
            background-size: 80%;
        }

        .star-rating label:hover,
        .star-rating label:hover ~ label,
        .star-rating input:checked ~ label {
            background-image: url('data:image/svg+xml;charset=UTF-8,<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="%23ffc107" stroke="%23ffc107" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>');
        }

        .user-review {
            background: #fff;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
            margin-top: 15px;
        }

        .review-rating {
            margin-bottom: 15px;
        }

        .review-rating .fas.fa-star {
            color: #ddd;
            margin-right: 3px;
        }

        .review-rating .fas.fa-star.filled {
            color: #ffc107;
        }

        .review-text {
            font-size: 15px;
            line-height: 1.6;
            margin-bottom: 15px;
        }

        .review-date {
            color: #777;
            font-size: 12px;
            text-align: right;
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
                        <span class="status-badge status-<?php echo strtolower($booking['payment_status'] == 'paid' ? 'accepted' : $booking['status']); ?>">
                            <?php 
                            if ($booking['payment_status'] == 'paid' && $booking['status'] == 'pending') {
                                echo 'Accepted';
                            } else {
                                echo ucfirst($booking['status']);
                            }
                            ?>
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

            <?php if ($booking['status'] === 'accepted' && $booking['payment_status'] === 'paid'): ?>
            <div class="section">
                <h2 class="section-title">
                    <i class="fas fa-check-circle"></i> Service Status
                </h2>
                <p style="margin-bottom: 15px;">The service provider will complete your service as scheduled.</p>
                <div class="info-grid">
                    <div class="info-item">
                        <span class="info-label">Scheduled Date</span>
                        <span class="info-value"><?php echo date('d M Y', strtotime($booking['booking_date'])); ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Scheduled Time</span>
                        <span class="info-value"><?php echo date('h:i A', strtotime($booking['time_slot'])); ?></span>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <?php if ($booking['status'] === 'completed' && !hasReview($conn, $booking['booking_id'])): ?>
            <div class="section">
                <h2 class="section-title">
                    <i class="fas fa-star"></i> Rate Your Experience
                </h2>
                <div class="review-form">
                    <form id="reviewForm">
                        <input type="hidden" name="booking_id" value="<?php echo $booking['booking_id']; ?>">
                        <input type="hidden" name="provider_id" value="<?php echo $booking['provider_id']; ?>">
                        <input type="hidden" name="service_id" value="<?php echo $booking['service_id']; ?>">
                        
                        <div class="rating-container">
                            <div class="rating-label">Your Rating:</div>
                            <div class="star-rating">
                                <input type="radio" id="star5" name="rating" value="5" required><label for="star5"></label>
                                <input type="radio" id="star4" name="rating" value="4"><label for="star4"></label>
                                <input type="radio" id="star3" name="rating" value="3"><label for="star3"></label>
                                <input type="radio" id="star2" name="rating" value="2"><label for="star2"></label>
                                <input type="radio" id="star1" name="rating" value="1"><label for="star1"></label>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="review_text">Your Review:</label>
                            <textarea id="review_text" name="review_text" rows="4" placeholder="Share your experience with this service..." required style="width: 100%; padding: 10px; margin-top: 10px; border: 1px solid #ddd; border-radius: 4px;"></textarea>
                        </div>
                        
                        <button type="submit" class="btn btn-primary" style="margin-top: 15px;">Submit Review</button>
                    </form>
                </div>
            </div>
            <?php elseif (hasReview($conn, $booking['booking_id'])): ?>
            <div class="section">
                <h2 class="section-title">
                    <i class="fas fa-star"></i> Your Review
                </h2>
                <div class="user-review">
                    <?php $review = getReview($conn, $booking['booking_id']); ?>
                    <div class="review-rating">
                        <?php for($i = 1; $i <= 5; $i++): ?>
                            <i class="fas fa-star <?php echo ($i <= $review['rating']) ? 'filled' : ''; ?>"></i>
                        <?php endfor; ?>
                    </div>
                    <div class="review-text"><?php echo htmlspecialchars($review['review_text']); ?></div>
                    <div class="review-date">Submitted on <?php echo date('d M Y', strtotime($review['created_at'])); ?></div>
                </div>
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

        document.addEventListener('DOMContentLoaded', function() {
            const reviewForm = document.getElementById('reviewForm');
            
            if (reviewForm) {
                reviewForm.addEventListener('submit', function(e) {
                    e.preventDefault();
                    
                    const formData = new FormData(reviewForm);
                    
                    fetch('submit_review.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            alert('Thank you for your review!');
                            location.reload();
                        } else {
                            alert('Failed to submit review: ' + data.message);
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        alert('An error occurred while submitting your review');
                    });
                });
            }
        });
    </script>
</body>
</html> 