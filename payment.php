<?php
require_once 'dbconnect.php';

session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php?redirect=payment');
    exit;
}

$user_id = $_SESSION['user_id'];

// Initialize variables
$items = [];
$subtotal = 0;
$total = 0;
$tax = 0;

// Fetch cart items
$cart_query = "SELECT 
    c.*,
    ss.sub_service_name,
    ss.price,
    ts.service_name,
    ts.service_id,
    ts.description as service_description
FROM cart c 
JOIN tbl_sub_services ss ON c.sub_service_id = ss.sub_service_id 
JOIN tbl_services ts ON ss.service_id = ts.service_id
WHERE c.user_id = ? AND c.status = 'pending'";

$stmt = $conn->prepare($cart_query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$cart_result = $stmt->get_result();

// Check if cart is empty
if ($cart_result->num_rows === 0) {
    $_SESSION['error'] = "Your cart is empty. Please add services to continue.";
    header("Location: services.php");
    exit();
}

// Process cart items
while ($item = $cart_result->fetch_assoc()) {
    $items[] = $item;
    $subtotal += $item['final_price'];
}

// Calculate tax and total
$tax = round($subtotal * 0.05, 2);
$total = round($subtotal + $tax, 2);

// First try to get booking_id from GET parameter
if (isset($_GET['booking_id'])) {
    $booking_id = $_GET['booking_id'];
    
    // Fetch single booking details
    $booking_query = "SELECT 
                     b.booking_id,
                     b.service_id,
                     b.total_amount as amount,
                     s.service_name,
                     s.description as service_description,
                     s.price as service_price,
                     u.username, 
                     u.email, 
                     u.mobile,
                     u.address, 
                     u.city, 
                     u.state
                     FROM bookings b 
                     JOIN tbl_services s ON b.service_id = s.service_id 
                     JOIN users u ON b.user_id = u.id
                     WHERE b.booking_id = ? AND b.user_id = ?";

    $stmt = $conn->prepare($booking_query);
    $stmt->bind_param("ii", $booking_id, $user_id);
    $stmt->execute();
    $booking = $stmt->get_result()->fetch_assoc();

    if (!$booking) {
        header('Location: services.php');
        exit;
    }

    $subtotal = $booking['amount'];

} else {
    // Use the first service's details for display
    $booking = [
        'service_id' => $items[0]['service_id'],
        'service_name' => $items[0]['service_name'],
        'service_description' => $items[0]['service_description'],
        'amount' => $subtotal
    ];
}

// Fetch user details
$user_query = "SELECT username, email, mobile, address, city, state 
               FROM users WHERE id = ?";
$stmt = $conn->prepare($user_query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user_result = $stmt->get_result();
$user_data = $user_result->fetch_assoc();

// Merge user data into booking array
$booking = array_merge($booking, $user_data);

// Calculate tax and total (update the calculations to ensure consistency)
$subtotal = isset($booking['amount']) ? $booking['amount'] : $subtotal;
$tax = round($subtotal * 0.05, 2); // Round to 2 decimal places
$total = round($subtotal + $tax, 2); // Round the total as well

$category_query = "SELECT category_id FROM tbl_services WHERE service_id = ?";
$stmt = $conn->prepare($category_query);
$stmt->bind_param("i", $booking['service_id']);
$stmt->execute();
$category_result = $stmt->get_result()->fetch_assoc();
$category_id = $category_result ? $category_result['category_id'] : 1;

$razorpay_key_id = 'rzp_test_pM7XeD3uvgF2Or';

function getBookingSummary($conn, $booking_id) {
    // Query to fetch booking details with service and sub-service information
    $query = "SELECT 
                b.booking_id,
                b.booking_date,
                b.time_slot,
                b.total_price,
                b.status,
                b.payment_status,
                s.service_id,
                s.service_name,
                s.description as service_description,
                s.price as service_price,
                c.category_name,
                sp.business_name,
                u.username as customer_name,
                (SELECT GROUP_CONCAT(ss.sub_service_name SEPARATOR ', ') 
                 FROM tbl_sub_services ss 
                 WHERE ss.service_id = s.service_id) as sub_services
              FROM 
                bookings b
              JOIN 
                tbl_services s ON b.service_id = s.service_id
              JOIN 
                tbl_categories c ON s.category_id = c.category_id
              JOIN 
                service_providers sp ON b.provider_id = sp.provider_id
              JOIN 
                users u ON b.user_id = u.id
              WHERE 
                b.booking_id = ?";
    
    $stmt = $conn->prepare($query);
    if (!$stmt) {
        return ['success' => false, 'error' => 'Failed to prepare statement: ' . $conn->error];
    }
    
    $stmt->bind_param("i", $booking_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        return ['success' => true, 'data' => $result->fetch_assoc()];
    } else {
        return ['success' => false, 'error' => 'No booking found with ID: ' . $booking_id];
    }
}

// Add this near the top of the file to display error messages
if (isset($_SESSION['error'])) {
    echo '<div class="error-message">' . htmlspecialchars($_SESSION['error']) . '</div>';
    unset($_SESSION['error']);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment - ServiceHive</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="style.css">
    <script src="https://checkout.razorpay.com/v1/checkout.js"></script>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        }
        
        body {
            background-color: #f8f9fa;
            min-height: 100vh;
            color: #333;
            padding: 20px;
            display: block;
        }

        .main-wrapper {
            padding: 40px 20px;
            max-width: 1200px;
            margin: 80px auto 20px;
            background: #fff;
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.05);
            position: relative;
        }

        .payment-header {
            text-align: center;
            margin-bottom: 40px;
            padding: 20px;
            background: linear-gradient(135deg, rgba(236, 137, 8, 0.1), rgba(187, 118, 14, 0.1));
            border-radius: 10px;
        }

        .payment-header h1 {
            font-size: 28px;
            color: #333;
            margin-bottom: 10px;
        }

        .payment-header p {
            color: #6c757d;
            font-size: 16px;
        }

        .booking-status {
            display: flex;
            justify-content: center;
            margin-bottom: 30px;
            padding: 0 20px;
        }

        .status-step {
            display: flex;
            align-items: center;
            position: relative;
            z-index: 1;
        }

        .status-step:not(:last-child) {
            margin-right: 50px;
        }

        .status-step:not(:last-child)::after {
            content: '';
            position: absolute;
            right: -40px;
            top: 50%;
            width: 30px;
            height: 2px;
            background: #ddd;
            z-index: -1;
        }

        .step-number {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            background: rgb(236, 137, 8);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 10px;
            font-weight: bold;
        }

        .step-label {
            color: #666;
            font-size: 14px;
        }

        .step-active .step-number {
            background: rgb(39, 114, 10);
        }

        .step-active .step-label {
            color: #333;
            font-weight: 600;
        }

        .service-details {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 20px;
        }

        .service-name {
            font-size: 20px;
            font-weight: 600;
            color: rgb(187, 118, 14);
            margin-bottom: 10px;
        }

        .service-info {
            display: flex;
            gap: 20px;
            font-size: 14px;
            color: #666;
        }

        .payment-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
            margin-top: 30px;
        }

        @media (max-width: 768px) {
            .payment-grid {
                grid-template-columns: 1fr;
            }
            
            .main-wrapper {
                padding: 20px 15px;
                margin: 60px 10px 20px;
            }
            
            .service-info {
                flex-direction: column;
                gap: 10px;
            }
            
            .booking-status {
                overflow-x: auto;
                padding: 0 10px;
            }
        }

        .payment-left-column {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

        .payment-right-column {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

        .card {
            background: white;
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
            overflow: hidden;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            border: 1px solid #eee;
            max-height: 500px;
            display: flex;
            flex-direction: column;
        }

        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 15px rgba(0, 0, 0, 0.1);
        }

        .card-header {
            background: #fff;
            border-bottom: 1px solid #eee;
            color: rgb(187, 118, 14);
            padding: 15px 20px;
            font-weight: 600;
            font-size: 18px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .card-header i {
            font-size: 20px;
        }

        .card-body {
            padding: 0;
            overflow-y: auto;
            flex: 1;
        }

        .back-button {
            position: fixed;
            top: 20px;
            left: 20px;
            color: #666;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 5px;
            font-size: 14px;
            background: white;
            padding: 8px 15px;
            border-radius: 30px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            transition: transform 0.3s ease;
            z-index: 1000;
        }

        .back-button:hover {
            transform: translateX(-5px);
        }

        .info-item {
            margin-bottom: 15px;
            display: flex;
            justify-content: space-between;
        }

        .info-item:last-child {
            margin-bottom: 0;
        }

        .info-label {
            font-weight: 500;
            color: #6c757d;
        }

        .info-value {
            font-weight: 600;
            text-align: right;
        }

        .total-row {
            border-top: 1px solid #dee2e6;
            padding-top: 15px;
            margin-top: 15px;
            font-size: 18px;
        }

        .total-row .info-label {
            color: #333;
            font-weight: 600;
        }

        .address-content {
            line-height: 1.6;
        }

        .payment-method {
            padding: 16px;
            border-bottom: 1px solid #f0f0f0;
            display: flex;
            align-items: center;
            justify-content: space-between;
            cursor: pointer;
            transition: background 0.3s ease;
            background: white;
        }

        .payment-method:hover {
            background: #f8f9fa;
        }

        .payment-method:last-child {
            border-bottom: none;
        }

        .payment-method-left {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .payment-method-left i {
            font-size: 24px;
            color: rgb(187, 118, 14);
            width: 24px;
            text-align: center;
        }

        .payment-method-info {
            display: flex;
            flex-direction: column;
        }

        .payment-method-name {
            font-weight: 500;
            color: #333;
        }

        .payment-method-subtext {
            font-size: 12px;
            color: #666;
            margin-top: 2px;
        }

        .payment-method.active {
            background: #f8f9ff;
            border-left: 3px solid rgb(187, 118, 14);
        }

        .payment-method.active i {
            color: rgb(187, 118, 14);
        }

        .fa-chevron-right {
            color: #999;
            font-size: 14px;
        }

        .payment-summary {
            background: white;
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
            overflow: hidden;
        }

        .payment-summary-header {
            background: linear-gradient(135deg, rgb(187, 118, 14), rgb(236, 137, 8));
            color: white;
            padding: 15px 20px;
            font-weight: 600;
            font-size: 18px;
        }

        .payment-summary-body {
            padding: 20px;
        }

        .pay-button {
            background: linear-gradient(135deg, rgb(221, 129, 10), rgb(167, 102, 6));
            color: white;
            border: none;
            padding: 15px 30px;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            width: 100%;
            font-size: 16px;
            margin-top: 20px;
        }

        .pay-button:hover {
            background: linear-gradient(135deg, rgb(136, 212, 14), rgb(39, 114, 10));
            transform: translateY(-2px);
        }

        .pay-button:disabled {
            background: #ccc;
            cursor: not-allowed;
            transform: none;
        }
        
        .offers-title {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 15px;
        }
        
        .offers-title i {
            color: rgb(221, 129, 10);
        }
        
        .offer-item {
            display: flex;
            align-items: center;
            gap: 12px;
            color: #333;
            font-size: 14px;
            padding: 10px;
            background: #fff9f0;
            border-radius: 6px;
            border-left: 3px solid rgb(221, 129, 10);
        }

        .payment-icons {
            display: flex;
            gap: 10px;
            align-items: center;
            height: 24px;
        }

        .payment-icons img {
            height: 20px;
            width: auto;
            object-fit: contain;
            transition: transform 0.3s ease;
            max-width: 50px;
            vertical-align: middle;
        }

        .payment-method-left {
            gap: 10px;
            align-items: center;
        }

        .payment-method.active .payment-icons img {
            transform: scale(1.1);
        }

        .payment-method:hover .payment-icons img {
            transform: scale(1.1);
        }

        .edit-address-btn {
            margin-left: auto;
            padding: 5px 10px;
            background: transparent;
            border: 1px solid rgb(187, 118, 14);
            color: rgb(187, 118, 14);
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .edit-address-btn:hover {
            background: rgba(187, 118, 14, 0.1);
        }

        .address-edit-form {
            display: none;
        }

        .form-group {
            margin-bottom: 15px;
        }

        .form-group label {
            display: block;
            margin-bottom: 5px;
            color: #666;
            font-size: 14px;
        }

        .form-group input {
            width: 100%;
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }

        .form-buttons {
            display: flex;
            gap: 10px;
            margin-top: 20px;
        }

        .save-address-btn {
            padding: 8px 20px;
            background: rgb(187, 118, 14);
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
        }

        .cancel-btn {
            padding: 8px 20px;
            background: #f8f9fa;
            border: 1px solid #ddd;
            border-radius: 5px;
            cursor: pointer;
        }

        .form-group textarea {
            width: 100%;
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
            resize: vertical;
            min-height: 80px;
        }

        .payment-details-container {
            border-top: 1px solid #eee;
            max-height: 300px;
            overflow-y: auto;
        }

        .upi-options, .netbanking-options, .wallet-options {
            display: grid;
            gap: 10px;
        }

        .upi-option, .bank-option, .wallet-option {
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            cursor: pointer;
            background: white;
            transition: all 0.3s ease;
        }

        .upi-option:hover, .bank-option:hover, .wallet-option:hover {
            background: #f0f0f0;
            border-color: #bb760e;
        }

        .upi-details {
            font-size: 12px;
            color: #666;
            margin-top: 5px;
        }

        .upi-input-container {
            margin-top: 15px;
            padding: 15px;
            border-top: 1px solid #eee;
        }

        .upi-input-field {
            display: flex;
            gap: 10px;
            align-items: center;
            margin-top: 10px;
        }

        .upi-input-field input {
            flex: 1;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
        }

        .upi-input-field button {
            padding: 10px 20px;
            background: rgb(187, 118, 14);
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
        }

        .upi-option {
            padding: 15px;
            border-bottom: 1px solid #eee;
        }

        .upi-option:last-child {
            border-bottom: none;
        }

        .payment-methods-container {
            max-height: 400px;
            overflow-y: auto;
            padding-right: 10px;
            scrollbar-width: thin;
            scrollbar-color: #888 #f1f1f1;
        }

        .payment-methods-container::-webkit-scrollbar {
            width: 6px;
        }

        .payment-methods-container::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 3px;
        }

        .payment-methods-container::-webkit-scrollbar-thumb {
            background: #888;
            border-radius: 3px;
        }

        .payment-methods-container::-webkit-scrollbar-thumb:hover {
            background: #555;
        }

        .upi-details-container {
            display: none;
            padding: 15px;
            background: #f8f9fa;
            border-top: 1px solid #eee;
            margin-top: 10px;
        }

        .upi-option.selected + .upi-details-container {
            display: block;
        }

        .cart-items {
            max-height: 300px;
            overflow-y: auto;
            margin-bottom: 20px;
        }

        .cart-item {
            padding: 15px;
            border-bottom: 1px solid #eee;
        }

        .cart-item:last-child {
            border-bottom: none;
        }

        .item-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 5px;
        }

        .item-name {
            font-weight: 500;
            color: #333;
        }

        .item-price {
            font-weight: 600;
            color: rgb(187, 118, 14);
        }

        .item-details {
            display: flex;
            gap: 15px;
            font-size: 14px;
            color: #666;
        }

        .item-quantity, .item-measurement {
            display: flex;
            align-items: center;
            gap: 5px;
        }
    </style>
</head>
<body>
    <a href="javascript:history.back()" class="back-button">
        <i class="fas fa-arrow-left"></i> Back
    </a>

    <div class="main-wrapper">
        <div class="payment-header">
            <h1>Complete Your Booking</h1>
            <p>You're just one step away from confirming your service</p>
        </div>

        <div class="booking-status">
            <div class="status-step">
                <div class="step-number">1</div>
                <div class="step-label">Service Selected</div>
            </div>
            <div class="status-step">
                <div class="step-number">2</div>
                <div class="step-label">Details Added</div>
            </div>
            <div class="status-step step-active">
                <div class="step-number">3</div>
                <div class="step-label">Payment</div>
            </div>
            <div class="status-step">
                <div class="step-number">4</div>
                <div class="step-label">Confirmation</div>
            </div>
        </div>

        <div class="service-details">
            <div class="service-name">
                <i class="fas fa-shopping-cart"></i> 
                Multiple Services
            </div>
            <div class="service-info">
                <span><i class="fas fa-box"></i> <?php echo count($items); ?> Services</span>
                <span><i class="far fa-clock"></i> Total Time: <?php echo count($items) * 60; ?> mins</span>
                <span><i class="fas fa-user-clock"></i> Professional will arrive at your location</span>
            </div>
        </div>
        
        <div class="payment-grid">
            <div class="payment-left-column">
                <div class="card">
                    <div class="card-header">
                        <i class="fas fa-clipboard-list"></i> Booking Details
                    </div>
                    <div class="card-body">
                        <div class="cart-items">
                            <?php foreach ($items as $item): ?>
                            <div class="cart-item">
                                <div class="item-header">
                                    <span class="item-name"><?php echo htmlspecialchars($item['sub_service_name']); ?></span>
                                    <span class="item-price">₹<?php echo number_format($item['final_price'], 2); ?></span>
                                </div>
                                <div class="item-details">
                                    <span class="item-quantity">Quantity: <?php echo $item['quantity']; ?></span>
                                    <?php if ($item['measurement']): ?>
                                    <span class="item-measurement"><?php echo htmlspecialchars($item['measurement']); ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <div class="info-item total-row">
                            <span class="info-label">Total Amount</span>
                            <span class="info-value">₹<?php echo number_format($total, 2); ?></span>
                        </div>
                    </div>
                </div>
                
                <div class="card">
                    <div class="card-header">
                        <i class="fas fa-map-marker-alt"></i> Service Address
                        <button onclick="editAddress()" class="edit-address-btn">
                            <i class="fas fa-edit"></i> Enter Address
                        </button>
                    </div>
                    <div class="card-body">
                        <div class="address-content" id="address-display">
                            <?php if (isset($_SESSION['service_address'])): ?>
                                <p><strong><?php echo htmlspecialchars($booking['username']); ?></strong></p>
                                <p><?php echo htmlspecialchars($_SESSION['service_address']['address']); ?></p>
                                <p><?php echo htmlspecialchars($_SESSION['service_address']['district']); ?></p>
                                <p><?php echo htmlspecialchars($_SESSION['service_address']['city']); ?>, 
                                   <?php echo htmlspecialchars($_SESSION['service_address']['state']); ?> - 
                                   <?php echo htmlspecialchars($_SESSION['service_address']['postal_code']); ?></p>
                                <p>Mobile: <?php echo htmlspecialchars($booking['mobile']); ?></p>
                                <p>Email: <?php echo htmlspecialchars($booking['email']); ?></p>
                            <?php else: ?>
                                <p class="text-center">Please enter your service address</p>
                            <?php endif; ?>
                        </div>
                        
                        <form id="address-form" style="display: <?php echo isset($_SESSION['service_address']) ? 'none' : 'block'; ?>" class="address-edit-form">
                            <input type="hidden" name="user_id" value="<?php echo $user_id; ?>">
                            <div class="form-group">
                                <label>Complete Address*</label>
                                <textarea name="address" required rows="3"><?php echo isset($_SESSION['service_address']) ? htmlspecialchars($_SESSION['service_address']['address']) : ''; ?></textarea>
                            </div>
                            <div class="form-row">
                                <div class="form-group">
                                    <label>Postal Code*</label>
                                    <input type="text" name="postal_code" id="postal_code" required pattern="[0-9]{6}" 
                                           title="Please enter a valid 6-digit postal code" maxlength="6"
                                           value="<?php echo isset($_SESSION['service_address']) ? htmlspecialchars($_SESSION['service_address']['postal_code']) : ''; ?>">
                                    <small id="postal-code-loading" style="display:none;"><i class="fas fa-spinner fa-spin"></i> Fetching location data...</small>
                                    <small id="postal-code-error" class="text-danger" style="display:none;">Unable to find location for this postal code</small>
                                </div>
                            </div>
                            <div class="form-row">
                                <div class="form-group">
                                    <label>District*</label>
                                    <input type="text" name="district" id="district" required 
                                           value="<?php echo isset($_SESSION['service_address']) ? htmlspecialchars($_SESSION['service_address']['district']) : ''; ?>">
                                </div>
                                <div class="form-group">
                                    <label>City*</label>
                                    <input type="text" name="city" id="city" required 
                                           value="<?php echo isset($_SESSION['service_address']) ? htmlspecialchars($_SESSION['service_address']['city']) : ''; ?>">
                                </div>
                            </div>
                            <div class="form-group">
                                <label>State*</label>
                                <input type="text" name="state" id="state" required 
                                       value="<?php echo isset($_SESSION['service_address']) ? htmlspecialchars($_SESSION['service_address']['state']) : ''; ?>">
                            </div>
                            <div class="form-group">
                                <label class="checkbox-container">
                                    <input type="checkbox" name="is_default" <?php echo (isset($_SESSION['service_address']) && $_SESSION['service_address']['is_default']) ? 'checked' : ''; ?>>
                                    <span class="checkmark"></span>
                                    Save this address for future bookings
                                </label>
                            </div>
                            <div class="form-buttons">
                                <button type="submit" class="save-address-btn">Save Address</button>
                                <?php if (isset($_SESSION['service_address'])): ?>
                                <button type="button" onclick="cancelEdit()" class="cancel-btn">Cancel</button>
                                <?php endif; ?>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            
            <div class="payment-right-column">
                <div class="card">
                    <div class="card-header">
                        <i class="fas fa-credit-card"></i> Payment Methods
                    </div>
                    <div class="card-body" style="padding: 0;">
                        <div id="payment-methods">
                            <!-- Payment methods will be inserted here by JavaScript -->
                        </div>
                    </div>
                </div>
                
                <div class="card">
                    <div class="card-header">
                        <i class="fas fa-receipt"></i> Payment Summary
                    </div>
                    <div class="card-body">
                        <div class="info-item">
                            <span class="info-label">Subtotal</span>
                            <span class="info-value">₹<?php echo number_format($subtotal, 2); ?></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Tax</span>
                            <span class="info-value">₹<?php echo number_format($tax, 2); ?></span>
                        </div>
                        <div class="info-item total-row">
                            <span class="info-label">Total</span>
                            <span class="info-value">₹<?php echo number_format($total, 2); ?></span>
                        </div>
                        
                        <button class="pay-button" id="pay-button" disabled>
                            <i class="fas fa-lock"></i> Pay Securely Now
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
       const paymentMethods = [
    {
        id: 'card',
        name: 'Cards',
        icon: '<div class="payment-icons"><img src="https://upload.wikimedia.org/wikipedia/commons/5/5e/Visa_Inc._logo.svg" alt="Visa" height="24"><img src="https://upload.wikimedia.org/wikipedia/commons/2/2a/Mastercard-logo.svg" alt="Mastercard" height="24"><img src="https://upload.wikimedia.org/wikipedia/commons/d/d1/RuPay.svg" alt="RuPay" height="24"></div>',
        subtext: 'Credit & Debit Cards'
    },
    {
    id: 'upi',
    name: 'UPI',
    icon: '<div class="payment-icons"><img src="https://developers.google.com/pay/api/images/brand-guidelines/google-pay-mark.png" alt="Google Pay"><img src="https://download.logo.wine/logo/PhonePe/PhonePe-Logo.wine.png" alt="PhonePe"><img src="https://upload.wikimedia.org/wikipedia/commons/thumb/e/e1/UPI-Logo-vector.svg/1200px-UPI-Logo-vector.svg.png" alt="BHIM UPI"></div>',
    subtext: 'Google Pay, PhonePe, BHIM UPI'
},

{
                id: 'netbanking',
                name: 'Netbanking',
                icon: '<div class="payment-icons"><i class="fas fa-university" style="color: #0066B8"></i></div>',
                subtext: 'All Indian banks'
            },
    {
                id: 'wallet',
                name: 'Wallet',
                icon: '<div class="payment-icons"><i class="fas fa-wallet" style="color: #00BAF2"></i></div>',
                subtext: 'Paytm, PhonePe, Amazon Pay'
            }
];

        // Initialize payment methods container
        document.getElementById('payment-methods').innerHTML = `
            <div class="payment-methods-container">
                ${paymentMethods.map(method => `
                    <div class="payment-method" onclick="selectPaymentMethod('${method.id}')">
                        <div class="payment-method-left">
                            ${method.icon}
                            <div class="payment-method-info">
                                <span class="payment-method-name">${method.name}</span>
                                <span class="payment-method-subtext">${method.subtext}</span>
                            </div>
                        </div>
                        <i class="fas fa-chevron-right"></i>
                    </div>
                `).join('')}
            </div>
        `;

        let selectedMethod = null;
        let addressSaved = <?php echo isset($_SESSION['service_address']) ? 'true' : 'false'; ?>;
        
        // Initialize payment details object
        window.paymentDetails = {
            method: null
        };

        // Update pay button state based on address and payment method
        function updatePayButtonState() {
            const payButton = document.getElementById('pay-button');
            payButton.disabled = !addressSaved || !window.paymentDetails.method;
        }

        function selectPaymentMethod(method) {
            // Reset all payment methods
            document.querySelectorAll('.payment-method').forEach(el => {
                el.classList.remove('active');
            });
            
            // Set the selected method as active
            event.currentTarget.classList.add('active');
            
            // Update payment details
            window.paymentDetails.method = method;
            
            // Update pay button state
            updatePayButtonState();
        }

        function editAddress() {
            document.getElementById('address-display').style.display = 'none';
            document.getElementById('address-form').style.display = 'block';
        }

        function cancelEdit() {
            document.getElementById('address-display').style.display = 'block';
            document.getElementById('address-form').style.display = 'none';
        }

        document.getElementById('address-form').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            
            fetch('save_service_address.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    addressSaved = true;
                    updatePayButtonState();
                    
                    // Update the address display
                    const addressDisplay = document.getElementById('address-display');
                    addressDisplay.innerHTML = `
                        <p><strong>${data.username}</strong></p>
                        <p>${data.address.address}</p>
                        <p>${data.address.district}</p>
                        <p>${data.address.city}, ${data.address.state} - ${data.address.postal_code}</p>
                        <p>Mobile: ${data.mobile}</p>
                        <p>Email: ${data.email}</p>
                    `;
                    
                    // Show the address display and hide the form
                    addressDisplay.style.display = 'block';
                    document.getElementById('address-form').style.display = 'none';
                } else {
                    alert('Failed to save address: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred while saving the address. Please try again.');
            });
        });

        document.getElementById('pay-button').addEventListener('click', function() {
            if (!window.paymentDetails || !window.paymentDetails.method) {
                alert('Please select a payment method');
                return;
            }
            
            if (!addressSaved) {
                alert('Please enter a service address');
                return;
            }

            // Disable the pay button to prevent double clicks
            const payButton = document.getElementById('pay-button');
            payButton.disabled = true;
            payButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';

            const options = {
                key: "<?php echo $razorpay_key_id; ?>",
                amount: <?php echo round($total * 100); ?>,
                currency: "INR",
                name: "ServiceHive",
                description: "Booking #<?php echo $booking_id; ?>",
                prefill: {
                    name: "<?php echo htmlspecialchars($booking['username']); ?>",
                    email: "<?php echo htmlspecialchars($booking['email']); ?>",
                    contact: "<?php echo htmlspecialchars($booking['mobile']); ?>"
                },
                method: window.paymentDetails.method,
                handler: function(response) {
                    handlePaymentResponse(response, payButton);
                },
                modal: {
                    ondismiss: function() {
                        // Re-enable the pay button if modal is dismissed
                        payButton.disabled = false;
                        payButton.innerHTML = '<i class="fas fa-lock"></i> Pay Securely Now';
                    }
                },
                notes: {
                    booking_id: "<?php echo $booking_id; ?>"
                }
            };

            const rzp = new Razorpay(options);
            rzp.open();
        });

        function handlePaymentResponse(response, payButton) {
            // Check if we have a valid payment ID from Razorpay
            if (!response.razorpay_payment_id) {
                alert('Payment failed: No payment ID received from Razorpay');
                payButton.disabled = false;
                payButton.innerHTML = '<i class="fas fa-lock"></i> Pay Securely Now';
                return;
            }

            const paymentData = {
                booking_id: <?php echo isset($booking_id) ? $booking_id : 'null'; ?>,
                payment_id: response.razorpay_payment_id,
                amount: <?php echo $total; ?>,
                payment_method: window.paymentDetails.method,
                user_id: <?php echo $user_id; ?>,
                cart_items: <?php echo json_encode($items); ?>,
                address_id: <?php echo isset($_SESSION['service_address']) && isset($_SESSION['service_address']['id']) ? $_SESSION['service_address']['id'] : 'null'; ?>
            };

            // Show loading state
            document.body.style.cursor = 'wait';
            console.log('Sending payment data:', paymentData);

            fetch('process_payment.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(paymentData)
            })
            .then(response => {
                console.log('Response status:', response.status);
                if (!response.ok) {
                    return response.text().then(text => {
                        throw new Error('Server error: ' + response.status + ' ' + text);
                    });
                }
                return response.json();
            })
            .then(data => {
                console.log('Payment processing response:', data);
                if (data.success) {
                    window.location.href = 'payment_success.php?' + new URLSearchParams({
                        booking_id: data.booking_id || paymentData.booking_id,
                        payment_id: paymentData.payment_id,
                        amount: paymentData.amount,
                        status: 'success'
                    }).toString();
                } else {
                    throw new Error(data.message || 'Payment verification failed');
                }
            })
            .catch(error => {
                console.error('Error details:', error);
                alert('Payment processing failed: ' + error.message);
                
                // Re-enable the pay button
                payButton.disabled = false;
                payButton.innerHTML = '<i class="fas fa-lock"></i> Pay Securely Now';
            })
            .finally(() => {
                document.body.style.cursor = 'default';
            });
        }

        // Postal code lookup
        document.getElementById('postal_code').addEventListener('input', function(e) {
            const postalCode = e.target.value.trim();
            
            // Only proceed if we have a 6-digit postal code
            if (postalCode.length === 6 && /^\d{6}$/.test(postalCode)) {
                const loadingIndicator = document.getElementById('postal-code-loading');
                const errorMessage = document.getElementById('postal-code-error');
                
                // Show loading indicator
                loadingIndicator.style.display = 'inline';
                errorMessage.style.display = 'none';
                
                // Fetch location data from India Post API
                fetch(`https://api.postalpincode.in/pincode/${postalCode}`)
                    .then(response => response.json())
                    .then(data => {
                        loadingIndicator.style.display = 'none';
                        
                        if (data[0].Status === 'Success' && data[0].PostOffice && data[0].PostOffice.length > 0) {
                            const postOffice = data[0].PostOffice[0];
                            
                            // Fill in the form fields
                            document.getElementById('district').value = postOffice.District;
                            document.getElementById('city').value = postOffice.Block || postOffice.Name;
                            document.getElementById('state').value = postOffice.State;
                            
                            // Hide error message if it was previously shown
                            errorMessage.style.display = 'none';
                        } else {
                            // Show error message
                            errorMessage.style.display = 'inline';
                            
                            // Clear the fields
                            document.getElementById('district').value = '';
                            document.getElementById('city').value = '';
                            document.getElementById('state').value = '';
                        }
                    })
                    .catch(error => {
                        console.error('Error fetching postal code data:', error);
                        loadingIndicator.style.display = 'none';
                        errorMessage.style.display = 'inline';
                    });
            }
        });

        // Initialize pay button state
        updatePayButtonState();
    </script>
</body>
</html>