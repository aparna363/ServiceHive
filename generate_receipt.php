<?php
require_once 'dbconnect.php';
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Check if booking ID is provided
if (!isset($_GET['booking_id'])) {
    header('Location: booking.php');
    exit;
}

$booking_id = $_GET['booking_id'];
$user_id = $_SESSION['user_id'];

// Fetch booking details
$query = "SELECT 
    b.*,
    s.service_name,
    s.price,
    sp.business_name as provider_name,
    sp.provider_id,
    c.category_name,
    u.username as user_name,
    u.email as user_email,
    u.mobile as user_mobile,
    COALESCE(b.total_amount, b.total_price) as final_price
    FROM bookings b
    JOIN tbl_services s ON b.service_id = s.service_id
    JOIN service_providers sp ON b.provider_id = sp.provider_id
    JOIN tbl_categories c ON s.category_id = c.category_id
    JOIN users u ON b.user_id = u.id
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

// Generate receipt download URL
$receiptUrl = "https://" . $_SERVER['HTTP_HOST'] . "/ServiceHive/generate_receipt.php?booking_id=" . $booking_id;

// Generate QR code data - include the direct download link
$qrData = $receiptUrl;

// Use a reliable QR code API
$qrCodeUrl = "https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=" . urlencode($qrData);

// Clear all output buffers
while (ob_get_level()) {
    ob_end_clean();
}

// Get current date and time in the correct format
$currentDateTime = date('d M Y, h:i A');

// Generate HTML receipt
echo '<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Payment Receipt - ServiceHive</title>
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
        <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
        <style>
            :root {
                --primary-color: #bb760e;
                --secondary-color: #ec8908;
                --heading-color: #1288ab;
                --receipt-color: #2c3e50;
                --text-color: #333;
                --light-text: #666;
                --border-color: #ddd;
                --background-color: #f9f9f9;
                --shadow: 0 4px 12px rgba(0,0,0,0.1);
            }
            
            * {
                margin: 0;
                padding: 0;
                box-sizing: border-box;
            }
            
            body {
                font-family: "Poppins", sans-serif;
                background-color: var(--background-color);
                color: var(--text-color);
                padding: 20px;
                display: flex;
                justify-content: center;
                align-items: center;
                min-height: 100vh;
            }
            
            .receipt-container {
                background: white;
                border-radius: 12px;
                box-shadow: var(--shadow);
                width: 100%;
                max-width: 800px;
                margin: 0 auto;
                position: relative;
                overflow: hidden;
                page-break-inside: avoid;
            }
            
            .receipt-header {
                background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
                color: white;
                padding: 20px 30px;
                display: flex;
                align-items: center;
            }
            
            .company-logo {
                max-width: 130px;
                margin-right: 20px;
            }
            
            .header-content {
                flex: 1;
                text-align: left;
                padding-left: 20px;
            }
            
            .header-content h1 {
                font-size: 28px;
                font-weight: 600;
                margin-bottom: 5px;
                color: #2c3e50;
            }
            
            .header-content p {
                font-size: 16px;
                opacity: 0.9;
            }
            
            .receipt-body {
                padding: 20px 30px;
                position: relative;
            }
            
            .receipt-number {
                font-size: 16px;
                font-weight: 500;
                margin-bottom: 8px;
                text-align: center;
            }
            
            .receipt-date {
                font-size: 14px;
                color: var(--light-text);
                margin-bottom: 20px;
                text-align: center;
            }
            
            .section {
                margin-bottom: 15px;
                border-bottom: 1px solid var(--border-color);
                padding-bottom: 15px;
            }
            
            .section:last-of-type {
                border-bottom: none;
                margin-bottom: 0;
                padding-bottom: 0;
            }
            
            .section-title {
                font-size: 18px;
                color: var(--heading-color);
                margin-bottom: 10px;
                display: flex;
                align-items: center;
                gap: 8px;
            }
            
            .info-grid {
                display: grid;
                grid-template-columns: repeat(2, 1fr);
                gap: 10px;
            }
            
            .info-item {
                display: flex;
                flex-direction: column;
                gap: 4px;
            }
            
            .info-label {
                color: var(--light-text);
                font-size: 13px;
            }
            
            .info-value {
                font-weight: 500;
                font-size: 14px;
            }
            
            .amount {
                color: var(--primary-color);
                font-weight: 600;
            }
            
            .receipt-footer {
                background-color: var(--background-color);
                padding: 15px 30px;
                text-align: center;
                font-size: 14px;
                color: var(--light-text);
                border-top: 1px solid var(--border-color);
            }
            
            .print-btn {
                padding: 15px 30px;
                display: flex;
                justify-content: center;
                gap: 10px;
            }
            
            .btn {
                background: var(--primary-color);
                color: white;
                border: none;
                padding: 8px 16px;
                border-radius: 6px;
                cursor: pointer;
                transition: all 0.3s ease;
                display: inline-flex;
                align-items: center;
                justify-content: center;
                gap: 8px;
                font-weight: 500;
                text-decoration: none;
                font-size: 14px;
            }
            
            .btn:hover {
                background: var(--secondary-color);
                transform: translateY(-2px);
                box-shadow: 0 4px 8px rgba(0,0,0,0.1);
            }
            
            .btn i {
                font-size: 16px;
            }
            
            .payment-status {
                display: inline-block;
                padding: 3px 8px;
                border-radius: 20px;
                font-size: 12px;
                font-weight: 500;
                text-transform: uppercase;
                background-color: #e6f7e6;
                color: #28a745;
            }
            
            .payment-status.pending {
                background-color: #fff3cd;
                color: #856404;
            }
            
            .qr-container {
                display: inline-block;
                float: right;
                margin-top: -10px;
                margin-bottom: 10px;
            }
            
            .qr-code-wrapper {
                background: white;
                padding: 8px;
                border-radius: 5px;
                box-shadow: var(--shadow);
                display: flex;
                flex-direction: column;
                align-items: center;
            }
            
            .qr-code {
                width: 80px;
                height: 80px;
            }
            
            .qr-code-wrapper p {
                font-size: 10px;
                color: #666;
                margin-top: 5px;
                text-align: center;
            }
            
            .clearfix::after {
                content: "";
                clear: both;
                display: table;
            }
            
            @media print {
                body {
                    background-color: white;
                    padding: 0;
                    margin: 0;
                }
                
                .receipt-container {
                    box-shadow: none;
                    margin: 0;
                    max-width: 100%;
                }
                
                .print-btn {
                    display: none;
                }
                
                .qr-container {
                    position: static;
                    float: right;
                    margin-top: 0;
                }
            }
            
            @media (max-width: 768px) {
                .receipt-container {
                    margin: 20px;
                    width: auto;
                }
                
                .receipt-body {
                    padding: 15px;
                }
                
                .info-grid {
                    grid-template-columns: 1fr;
                }
                
                .receipt-header {
                    padding: 15px;
                    flex-direction: column;
                    text-align: center;
                }
                
                .company-logo {
                    margin: 0 auto 15px;
                }
                
                .header-content {
                    text-align: center;
                    padding-left: 0;
                }
                
                .qr-container {
                    float: none;
                    display: block;
                    margin: 15px auto;
                    text-align: center;
                }
            }
        </style>
    </head>
    <body>
        <div class="receipt-container">
            <div class="receipt-header">
                <img src="images/logo2.png" alt="ServiceHive Logo" class="company-logo">
                <div class="header-content">
                    <h1>Payment Receipt</h1>
                    <p>Your booking has been confirmed</p>
                </div>
            </div>
            
            <div class="receipt-body">
                <div class="receipt-number">
                    Receipt #<strong>' . $booking_id . '</strong>
                </div>
                
                <div class="receipt-date">
                    Generated on: ' . $currentDateTime . '
                </div>
                
                <div class="qr-container">
                    <div class="qr-code-wrapper">
                        <img src="' . $qrCodeUrl . '" alt="QR Code" class="qr-code">
                        <p>Scan to download</p>
                    </div>
                </div>
                
                <div class="clearfix"></div>
                
                <div class="section">
                    <h2 class="section-title">
                        <i class="fas fa-user"></i> Customer Details
                    </h2>
                    <div class="info-grid">
                        <div class="info-item">
                            <span class="info-label">Name</span>
                            <span class="info-value">' . htmlspecialchars($booking['user_name']) . '</span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Email</span>
                            <span class="info-value">' . htmlspecialchars($booking['user_email']) . '</span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Mobile</span>
                            <span class="info-value">' . htmlspecialchars($booking['user_mobile']) . '</span>
                        </div>
                    </div>
                </div>
                
                <div class="section">
                    <h2 class="section-title">
                        <i class="fas fa-briefcase"></i> Service Details
                    </h2>
                    <div class="info-grid">
                        <div class="info-item">
                            <span class="info-label">Service</span>
                            <span class="info-value">' . htmlspecialchars($booking['service_name']) . '</span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Category</span>
                            <span class="info-value">' . htmlspecialchars($booking['category_name']) . '</span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Provider</span>
                            <span class="info-value">' . htmlspecialchars($booking['provider_name']) . '</span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Date</span>
                            <span class="info-value">' . date('d M Y', strtotime($booking['booking_date'])) . '</span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Time</span>
                            <span class="info-value">' . date('h:i A', strtotime($booking['time_slot'])) . '</span>
                        </div>
                    </div>
                </div>
                
                <div class="section">
                    <h2 class="section-title">
                        <i class="fas fa-credit-card"></i> Payment Details
                    </h2>
                    <div class="info-grid">
                        <div class="info-item">
                            <span class="info-label">Amount</span>
                            <span class="info-value amount">₹' . number_format(floatval($booking['final_price']), 2) . '</span>
                        </div>';
                
    if (isset($booking['payment_id']) && !empty($booking['payment_id'])) {
        echo '<div class="info-item">
                <span class="info-label">Payment ID</span>
                <span class="info-value">' . htmlspecialchars($booking['payment_id']) . '</span>
              </div>';
    }
    
    if (isset($booking['payment_method']) && !empty($booking['payment_method'])) {
        echo '<div class="info-item">
                <span class="info-label">Payment Method</span>
                <span class="info-value">' . ucfirst(htmlspecialchars($booking['payment_method'])) . '</span>
              </div>';
    }
    
    if (isset($booking['payment_status']) && !empty($booking['payment_status'])) {
        $statusClass = ($booking['payment_status'] == 'paid') ? '' : 'pending';
        echo '<div class="info-item">
                <span class="info-label">Payment Status</span>
                <span class="info-value">
                    <span class="payment-status ' . $statusClass . '">' . ucfirst(htmlspecialchars($booking['payment_status'])) . '</span>
                </span>
              </div>';
    }
    
    echo '</div>
                </div>
            </div>
            
            <div class="receipt-footer">
                Thank you for choosing ServiceHive! We appreciate your business.
            </div>
            
            <div class="print-btn">
                <a href="javascript:void(0)" class="btn" style="background-color: #28a745; margin: 0 auto; display: block; width: fit-content;" onclick="downloadReceipt()">
                    <i class="fas fa-download"></i> Download Receipt
                </a>
            </div>
        </div>
        
        <script>
            function downloadReceipt() {
                window.print();
            }
        </script>
    </body>
    </html>';

exit;
?> 