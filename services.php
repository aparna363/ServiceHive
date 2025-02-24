<?php
// Include database connection
require_once 'dbconnect.php';
session_start();

// Get category_id from the URL
$category_id = isset($_GET['category_id']) ? (int)$_GET['category_id'] : 0;

// Debug: Check category ID
error_log("Category ID: " . $category_id);

// Fetch category details
$category_query = "SELECT category_name FROM tbl_categories WHERE category_id = ?";
$stmt = $conn->prepare($category_query);
$stmt->bind_param("i", $category_id);
$stmt->execute();
$category_result = $stmt->get_result();
$category = $category_result->fetch_assoc();
$category_name = $category ? $category['category_name'] : 'All Services';

// Debug: Check category details
error_log("Category Name: " . $category_name);

// Debug: Print category_id
error_log("Category ID received: " . $category_id);

// Modify services query to be simpler first to ensure we're getting data
$services_query = "
    SELECT 
        s.*,
        sp.business_name as provider_name,
        CASE 
            WHEN s.service_name LIKE '%wiring%' THEN 'measurement'
            WHEN s.service_name LIKE '%installation%' OR 
                 s.service_name LIKE '%repair%' OR 
                 s.service_name LIKE '%fan%' OR 
                 s.service_name LIKE '%switch%' THEN 'quantity'
            ELSE 'fixed'
        END as pricing_type
    FROM tbl_services s
    INNER JOIN service_providers sp ON s.provider_id = sp.provider_id
    WHERE s.category_id = ? AND s.is_active = TRUE";

$stmt = $conn->prepare($services_query);
$stmt->bind_param("i", $category_id);
$stmt->execute();
$services_result = $stmt->get_result();

// Debug: Print query results
error_log("SQL Query: " . str_replace('?', $category_id, $services_query));
error_log("Number of services found: " . $services_result->num_rows);

$services = [];
while ($row = $services_result->fetch_assoc()) {
    $services[] = $row;
    // Debug: Print service details
    error_log("Service found: " . json_encode($row));
}

// If no services found, let's check what's in the database
if (empty($services)) {
    // Check all services in the database
    $check_query = "SELECT * FROM tbl_services";
    $check_result = $conn->query($check_query);
    error_log("Total services in database: " . $check_result->num_rows);
    
    // Check service providers
    $provider_query = "SELECT * FROM service_providers";
    $provider_result = $conn->query($provider_query);
    error_log("Total service providers in database: " . $provider_result->num_rows);
    
    // Check categories
    $category_query = "SELECT * FROM tbl_categories";
    $category_result = $conn->query($category_query);
    error_log("Total categories in database: " . $category_result->num_rows);
}

// Let's also verify the data in your tables directly
$verify_data = "
    SELECT 
        c.category_id, c.category_name,
        sp.provider_id, sp.business_name,
        s.service_id, s.service_name,
        ss.sub_service_id, ss.sub_service_name
    FROM tbl_categories c
    LEFT JOIN service_providers sp ON sp.category_id = c.category_id
    LEFT JOIN tbl_services s ON s.provider_id = sp.provider_id
    LEFT JOIN tbl_sub_services ss ON ss.service_id = s.service_id
    WHERE c.category_id = ?";

$stmt = $conn->prepare($verify_data);
$stmt->bind_param("i", $category_id);
$stmt->execute();
$verify_result = $stmt->get_result();
error_log("Verification query results: " . json_encode($verify_result->fetch_all(MYSQLI_ASSOC)));

// Fetch sub-services
$allSubServices = [];
if (!empty($services)) {
    $service_ids = array_column($services, 'service_id');
    $placeholders = str_repeat('?,', count($service_ids) - 1) . '?';
    $sub_services_query = "
        SELECT 
            ss.*,
            s.service_name,
            CASE 
                WHEN s.service_name LIKE '%wiring%' THEN 'measurement'
                WHEN s.service_name LIKE '%installation%' OR 
                     s.service_name LIKE '%repair%' OR 
                     s.service_name LIKE '%fan%' OR 
                     s.service_name LIKE '%switch%' THEN 'quantity'
                ELSE 'fixed'
            END as pricing_type
        FROM tbl_sub_services ss
        JOIN tbl_services s ON ss.service_id = s.service_id
        WHERE ss.service_id IN ($placeholders)
        ORDER BY ss.service_id, ss.sub_service_name";
    
    $stmt = $conn->prepare($sub_services_query);
    $types = str_repeat('i', count($service_ids));
    $stmt->bind_param($types, ...$service_ids);
    $stmt->execute();
    $sub_services_result = $stmt->get_result();
    
    // Debug: Check sub-services
    if ($sub_services_result->num_rows > 0) {
        while ($row = $sub_services_result->fetch_assoc()) {
            if (!isset($allSubServices[$row['service_id']])) {
                $allSubServices[$row['service_id']] = [];
            }
            $allSubServices[$row['service_id']][] = $row;
        }
        error_log("Found sub-services for " . count($allSubServices) . " services");
    } else {
        error_log("No sub-services found");
    }
}

// Add sub-services to each service
foreach ($services as &$service) {
    $service['sub_services'] = isset($allSubServices[$service['service_id']]) 
        ? $allSubServices[$service['service_id']] 
        : [];
}
unset($service);

// Debug: Final data check
error_log("Final services array: " . json_encode(array_slice($services, 0, 2)));

// Function to get all categories for navigation
function getAllCategories() {
    global $conn;
    
    $query = "
        SELECT 
            category_id,
            category_name,
            description,
            icon
        FROM tbl_categories
        WHERE is_active = 1
        ORDER BY category_name
    ";
    
    $stmt = $conn->prepare($query);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $categories = [];
    while ($row = $result->fetch_assoc()) {
        $categories[] = [
            'category_id' => $row['category_id'],
            'category_name' => $row['category_name'],
            'description' => $row['description'],
            'icon' => $row['icon']
        ];
    }
    
    return $categories;
}

// Make sure we have the categories for navigation
try {
    $categories = getAllCategories();
} catch (Exception $e) {
    error_log("Error getting categories: " . $e->getMessage());
    $categories = [];
}

// Let's verify if we have data in the session cart
if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

// Debug: Check session cart
error_log("Cart items: " . count($_SESSION['cart']));

// Handle AJAX requests to add/remove items from cart
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    if ($_POST['action'] === 'add_to_cart' && isset($_POST['sub_service_id'])) {
        $sub_service_id = intval($_POST['sub_service_id']);
        
        // Check if item already exists in cart
        if (isset($_SESSION['cart'][$sub_service_id])) {
            $_SESSION['cart'][$sub_service_id]['quantity']++;
        } else {
            // Fetch sub-service details
            $stmt = $conn->prepare("
                SELECT 
                    ss.sub_service_id,
                    ss.service_id,
                    ss.sub_service_name,
                    ss.price,
                    s.service_name
                FROM tbl_sub_services ss
                JOIN tbl_services s ON ss.service_id = s.service_id
                WHERE ss.sub_service_id = ?
            ");
            $stmt->bind_param("i", $sub_service_id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($sub_service = $result->fetch_assoc()) {
                $_SESSION['cart'][$sub_service_id] = [
                    'sub_service_id' => $sub_service['sub_service_id'],
                    'service_id' => $sub_service['service_id'],
                    'name' => $sub_service['sub_service_name'],
                    'service_name' => $sub_service['service_name'],
                    'price' => $sub_service['price'],
                    'quantity' => 1
                ];
            }
        }
        
        echo json_encode([
            'success' => true,
            'message' => 'Item added to cart',
            'cart_count' => count($_SESSION['cart']),
            'cart_items' => $_SESSION['cart']
        ]);
        exit;
    } 
    elseif ($_POST['action'] === 'remove_from_cart' && isset($_POST['sub_service_id'])) {
        $sub_service_id = intval($_POST['sub_service_id']);
        
        if (isset($_SESSION['cart'][$sub_service_id])) {
            unset($_SESSION['cart'][$sub_service_id]);
        }
        
        echo json_encode([
            'success' => true,
            'message' => 'Item removed from cart',
            'cart_count' => count($_SESSION['cart']),
            'cart_items' => $_SESSION['cart']
        ]);
        exit;
    }
    elseif ($_POST['action'] === 'update_quantity' && isset($_POST['sub_service_id']) && isset($_POST['quantity'])) {
        $sub_service_id = intval($_POST['sub_service_id']);
        $quantity = intval($_POST['quantity']);
        
        if ($quantity <= 0) {
            if (isset($_SESSION['cart'][$sub_service_id])) {
                unset($_SESSION['cart'][$sub_service_id]);
            }
        } else {
            if (isset($_SESSION['cart'][$sub_service_id])) {
                $_SESSION['cart'][$sub_service_id]['quantity'] = $quantity;
            }
        }
        
        echo json_encode([
            'success' => true,
            'message' => 'Cart updated',
            'cart_count' => count($_SESSION['cart']),
            'cart_items' => $_SESSION['cart']
        ]);
        exit;
    }
    elseif ($_POST['action'] === 'get_cart') {
        echo json_encode([
            'success' => true,
            'cart_count' => count($_SESSION['cart']),
            'cart_items' => $_SESSION['cart']
        ]);
        exit;
    }
}

// Handle the upload
if(isset($_FILES['service_image']) && $_FILES['service_image']['error'] === 0) {
    $upload_dir = 'uploads/';
    $file_name = uniqid() . '_' . basename($_FILES['service_image']['name']);
    $target_path = $upload_dir . $file_name;
    
    if(move_uploaded_file($_FILES['service_image']['tmp_name'], $target_path)) {
        // Save the path to database
        $image_path = $target_path;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($category_name); ?> - Book a Service</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        /* Base styles */
        body {
            font-family: 'Arial', sans-serif;
            background: #f5f5f5;
            color: #333;
            line-height: 1.6;
        }

        .booking-container {
            max-width: 1200px;
            margin: 30px auto;
            padding: 0 20px;
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 30px;
        }

        /* Service List Styles */
        .services-list {
            background: white;
            border-radius: 12px;
            padding: 25px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .category-header {
            margin-bottom: 25px;
        }

        .category-title {
            font-size: 28px;
            color: #2d3748;
            margin-bottom: 10px;
        }

        .service-item {
            border-bottom: 1px solid #eee;
            padding: 20px 0;
        }

        .service-item:last-child {
            border-bottom: none;
        }

        .service-header {
            display: flex;
            align-items: center;
            gap: 20px;
            margin-bottom: 15px;
        }

        .service-image {
            width: 80px;
            height: 80px;
            border-radius: 8px;
            object-fit: cover;
        }

        .service-info h3 {
            font-size: 20px;
            margin-bottom: 5px;
        }

        .service-price {
            font-size: 18px;
            font-weight: 600;
            color: #2d3748;
        }

        /* Booking Form Styles */
        .booking-form {
            background: white;
            border-radius: 12px;
            padding: 25px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            position: sticky;
            top: 30px;
        }

        .booking-form h2 {
            font-size: 24px;
            margin-bottom: 20px;
            color: #2d3748;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
        }

        .form-group input,
        .form-group select {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 16px;
        }

        .book-button {
            background: #7e3af2;
            color: white;
            border: none;
            padding: 15px 30px;
            border-radius: 6px;
            font-size: 16px;
            font-weight: 600;
            width: 100%;
            cursor: pointer;
            transition: background 0.3s;
        }

        .book-button:hover {
            background: #6c2bd9;
        }

        /* Service Features */
        .service-features {
            margin-top: 25px;
            padding-top: 20px;
            border-top: 1px solid #eee;
        }

        .feature-item {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 12px;
            color: #4a5568;
        }

        .feature-item i {
            color: #7e3af2;
        }

        /* Add these new styles while keeping existing ones */
        .expand-btn {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            border: 2px solid #7e3af2;
            background: white;
            color: #7e3af2;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.3s;
            margin-left: auto;
        }

        .expand-btn.active {
            background: #7e3af2;
            color: white;
            transform: rotate(45deg);
        }

        .sub-services-panel {
            display: none;
            padding: 15px;
            background: #f8f9fa;
            border-top: 1px solid #eee;
        }

        .sub-service-option {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px;
            border-bottom: 1px solid #eee;
            background: white;
            border-radius: 8px;
            margin-bottom: 10px;
        }

        .sub-service-option:last-child {
            margin-bottom: 0;
            border-bottom: none;
        }

        .sub-service-info h4 {
            margin: 0 0 8px 0;
            color: #2d3748;
        }

        .service-meta {
            display: flex;
            gap: 20px;
            color: #666;
            font-size: 14px;
        }

        .duration i {
            margin-right: 5px;
        }

        .price {
            font-weight: 600;
            color: #2d3748;
        }

        .book-now-btn {
            background: #7e3af2;
            color: white;
            border: none;
            padding: 8px 20px;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 500;
            transition: background 0.3s;
        }

        .book-now-btn:hover {
            background: #6c2bd9;
        }

        .service-icon {
            width: 40px;
            height: 40px;
            object-fit: cover;
            border-radius: 4px;
            margin-right: 15px;
        }

        .service-title-wrap {
            display: flex;
            align-items: center;
        }

        /* Checkout Modal Styles */
        .checkout-steps {
            display: flex;
            justify-content: space-between;
            margin-bottom: 30px;
            padding: 20px 0;
            border-bottom: 1px solid #eee;
        }

        .step {
            color: #666;
            position: relative;
            padding-bottom: 5px;
        }

        .step.active {
            color: #7e3af2;
            font-weight: 600;
        }

        .step.active::after {
            content: '';
            position: absolute;
            bottom: -21px;
            left: 0;
            width: 100%;
            height: 2px;
            background: #7e3af2;
        }

        .service-summary {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }

        .price-summary {
            border-top: 1px solid #eee;
            padding-top: 15px;
        }

        .price-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
        }

        .price-row.total {
            border-top: 1px solid #eee;
            padding-top: 10px;
            font-weight: 600;
            font-size: 18px;
        }

        .payment-methods {
            margin: 20px 0;
        }

        .payment-option {
            display: block;
            padding: 15px;
            border: 1px solid #ddd;
            border-radius: 8px;
            margin-bottom: 10px;
            cursor: pointer;
        }

        .next-btn, .pay-btn {
            background: #7e3af2;
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 6px;
            width: 100%;
            margin-top: 20px;
            cursor: pointer;
        }

        .pay-btn {
            background: #28a745;
        }

        .quantity-selector {
            display: flex;
            align-items: center;
            margin-bottom: 10px;
        }

        .quantity-selector button {
            width: 30px;
            height: 30px;
            border: 1px solid #ddd;
            background: #f8f9fa;
            cursor: pointer;
            border-radius: 4px;
        }

        .quantity-selector input,
        .measurement-input input {
            width: 60px;
            text-align: center;
            margin: 0 8px;
            padding: 5px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }

        .measurement-input {
            margin-bottom: 10px;
        }

        .service-action {
            display: flex;
            flex-direction: column;
            align-items: flex-end;
        }

        .quantity-details {
            margin-top: 10px;
            padding-top: 10px;
            border-top: 1px dashed #ddd;
        }

        /* Update existing styles */
        .sub-services-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0 15px;
            table-layout: fixed;
        }

        .sub-service-row td {
            padding: 20px;
            vertical-align: middle;
        }

        .service-name {
            width: 30%;
            color: #333;
            font-weight: 500;
            font-size: 16px;
        }

        .service-action {
            width: 70%;
        }

        .action-wrapper {
            display: flex;
            align-items: center;
            gap: 25px;  /* Increased gap between elements */
            justify-content: flex-start;  /* Align items from start */
        }

        .price-display {
            color: #333;
            font-weight: 600;
            font-size: 15px;
            white-space: nowrap;
        }

        .quantity-selector {
            display: flex;
            align-items: center;
            margin: 0;
        }

        .quantity-selector button {
            width: 32px;
            height: 32px;
            border: 1px solid #ddd;
            background: #f8f9fa;
            cursor: pointer;
            border-radius: 4px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .quantity-selector input {
            width: 50px;
            text-align: center;
            margin: 0 8px;
            padding: 6px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }

        .book-now-btn {
            background: #7e3af2;
            color: white;
            border: none;
            padding: 8px 24px;
            border-radius: 6px;
            cursor: pointer;
            white-space: nowrap;
        }

        @media (max-width: 768px) {
            .service-name {
                width: 25%;
            }
            .service-action {
                width: 75%;
            }
            .action-wrapper {
                gap: 15px;
            }
        }

        /* Update existing styles */
        .action-wrapper {
            display: flex;
            align-items: center;
            gap: 20px;
            justify-content: flex-end;
        }

        .price-display {
            font-weight: 600;
            color: #2d3748;
            font-size: 15px;
            white-space: nowrap;
        }

        .quantity-selector {
            display: flex;
            align-items: center;
            margin: 0;  /* Remove margin */
        }

        .quantity-selector button {
            width: 32px;
            height: 32px;
            border: 1px solid #ddd;
            background: #f8f9fa;
            cursor: pointer;
            border-radius: 4px;
            font-size: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .quantity-selector input,
        .measurement-input input {
            width: 60px;
            text-align: center;
            margin: 0 8px;
            padding: 6px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
        }

        .measurement-input {
            margin: 0;  /* Remove margin */
        }

        .book-now-btn {
            background: #7e3af2;
            color: white;
            border: none;
            padding: 8px 20px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 14px;
            transition: background 0.3s;
            white-space: nowrap;
        }

        .book-now-btn:hover {
            background: #6c2bd9;
        }

        /* Responsive adjustments */
        @media (max-width: 768px) {
            .action-wrapper {
                flex-direction: column;
                gap: 10px;
                align-items: flex-end;
            }

            .quantity-selector button {
                width: 28px;
                height: 28px;
                font-size: 14px;
            }

            .quantity-selector input,
            .measurement-input input {
                width: 50px;
                font-size: 13px;
            }

            .book-now-btn {
                padding: 6px 16px;
                font-size: 13px;
            }
        }
    </style>
</head>
<body>
    <div class="booking-container">
        <!-- Services List Section -->
        <div class="services-list">
            <div class="category-header">
                <h1 class="category-title">
                    <i class="fas fa-tools"></i> <?php echo htmlspecialchars($category_name); ?>
                </h1>
                <div class="category-rating">
                    <i class="fas fa-star" style="color: #ffc107;"></i>
                    <?php 
                        // Fetch average rating and booking count from service_providers
                        $rating_query = "
                            SELECT 
                                COALESCE(AVG(sp.rating), 0) as avg_rating,
                                COALESCE(SUM(sp.total_reviews), 0) as booking_count
                            FROM service_providers sp
                            WHERE sp.category_id = ?";
                        $stmt = $conn->prepare($rating_query);
                        $stmt->bind_param("i", $category_id);
                        $stmt->execute();
                        $rating_result = $stmt->get_result()->fetch_assoc();
                        $avg_rating = number_format($rating_result['avg_rating'], 2);
                        $booking_count = $rating_result['booking_count'];
                    ?>
                    <span><?php echo $avg_rating; ?> (<?php echo number_format($booking_count/1000, 1); ?>K bookings)</span>
                </div>
            </div>

            <?php foreach ($services as $service): ?>
                <div class="service-item">
                    <div class="service-header" onclick="toggleSubServices(<?php echo $service['service_id']; ?>)">
                        <div class="service-title-wrap">
                            <img src="<?php echo htmlspecialchars($service['image_path'] ?? 'images/default-service.png'); ?>" 
                                 alt="<?php echo htmlspecialchars($service['service_name']); ?>" 
                                 class="service-icon">
                            <h3><?php echo htmlspecialchars($service['service_name']); ?></h3>
                        </div>
                        <button class="expand-btn">
                            <i class="fas fa-plus" id="icon-<?php echo $service['service_id']; ?>"></i>
                        </button>
                    </div>
                    
                    <?php if (!empty($service['sub_services'])): ?>
                        <div class="sub-services-panel" id="sub-services-<?php echo $service['service_id']; ?>">
                            <table class="sub-services-table">
                                <tbody>
                                    <?php foreach ($service['sub_services'] as $sub): ?>
                                        <tr class="sub-service-row">
                                            <td class="service-name">
                                                <?php echo htmlspecialchars($sub['sub_service_name']); ?>
                                            </td>
                                            <td class="service-action">
                                                <div class="action-wrapper">
                                                    <div class="price-display">
                                                        <?php 
                                                            $pricing_type = isset($sub['pricing_type']) ? $sub['pricing_type'] : 'fixed';
                                                            if ($pricing_type === 'quantity'): 
                                                        ?>
                                                            ₹<?php echo number_format($sub['price'], 2); ?> per unit
                                                        <?php elseif ($pricing_type === 'measurement'): ?>
                                                            ₹<?php echo number_format($sub['price'], 2); ?> per meter
                                                        <?php else: ?>
                                                            ₹<?php echo number_format($sub['price'], 2); ?>
                                                        <?php endif; ?>
                                                    </div>
                                                    
                                                    <?php if ($pricing_type === 'quantity'): ?>
                                                        <div class="quantity-selector">
                                                            <button type="button" onclick="updateQuantity(<?php echo $sub['sub_service_id']; ?>, 'decrease')">-</button>
                                                            <input type="number" id="quantity-<?php echo $sub['sub_service_id']; ?>" 
                                                                   value="1" min="1" max="50" 
                                                                   onchange="updatePrice(<?php echo $sub['sub_service_id']; ?>, <?php echo $sub['price']; ?>)">
                                                            <button type="button" onclick="updateQuantity(<?php echo $sub['sub_service_id']; ?>, 'increase')">+</button>
                                                        </div>
                                                    <?php elseif ($pricing_type === 'measurement'): ?>
                                                        <div class="measurement-input">
                                                            <input type="number" id="measurement-<?php echo $sub['sub_service_id']; ?>" 
                                                                   placeholder="Enter meters" min="1"
                                                                   onchange="updatePrice(<?php echo $sub['sub_service_id']; ?>, <?php echo $sub['price']; ?>)">
                                                        </div>
                                                    <?php endif; ?>
                                                    
                                                    <button class="book-now-btn" 
                                                            onclick="proceedToCheckout(<?php echo $sub['sub_service_id']; ?>, 
                                                             '<?php echo htmlspecialchars($sub['sub_service_name']); ?>', 
                                                             <?php echo $sub['price']; ?>,
                                                             '<?php echo $pricing_type; ?>')">
                                                        Book
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Checkout Modal -->
        <div id="checkoutModal" class="modal">
            <div class="modal-content">
                <span class="close-modal">&times;</span>
                <div class="checkout-steps">
                    <div class="step active" id="step1">1. Service Details</div>
                    <div class="step" id="step2">2. Schedule</div>
                    <div class="step" id="step3">3. Payment</div>
                </div>

                <form id="checkoutForm" action="process_booking.php" method="POST">
                    <div class="checkout-section" id="serviceDetails">
                        <h3>Service Summary</h3>
                        <div id="selectedServiceInfo" class="service-summary"></div>
                        <div class="price-summary">
                            <div class="price-row">
                                <span>Service Charge</span>
                                <span id="serviceCharge"></span>
                            </div>
                            <div class="price-row">
                                <span>Convenience Fee</span>
                                <span id="convenienceFee"></span>
                            </div>
                            <div class="price-row total">
                                <span>Total Amount</span>
                                <span id="totalAmount"></span>
                            </div>
                        </div>
                        <button type="button" class="next-btn" onclick="showStep(2)">Continue</button>
                    </div>

                    <div class="checkout-section" id="scheduleSection" style="display: none;">
                        <h3>Schedule Service</h3>
                        <div class="form-group">
                            <label>Preferred Date</label>
                            <input type="date" name="booking_date" required min="<?php echo date('Y-m-d'); ?>">
                        </div>
                        <div class="form-group">
                            <label>Preferred Time</label>
                            <select name="booking_time" required>
                                <?php for($i = 9; $i <= 17; $i++): ?>
                                    <option value="<?php echo sprintf('%02d:00', $i); ?>">
                                        <?php echo date('h:i A', strtotime(sprintf('%02d:00', $i))); ?>
                                    </option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Service Address</label>
                            <textarea name="address" required></textarea>
                        </div>
                        <button type="button" class="next-btn" onclick="showStep(3)">Proceed to Payment</button>
                    </div>

                    <div class="checkout-section" id="paymentSection" style="display: none;">
                        <h3>Payment</h3>
                        <div class="payment-methods">
                            <label class="payment-option">
                                <input type="radio" name="payment_method" value="razorpay" checked>
                                <span>G Pay</span>
                                
                            </label>
                            <label class="payment-option">
                                <input type="radio" name="payment_method" value="razorpay" checked>
                                <span>Credit card</span>
                                
                            </label>
                            <label class="payment-option">
                                <input type="radio" name="payment_method" value="razorpay" checked>
                                <span>COD</span>
                                
                            </label>
                        </div>
                        <button type="button" class="pay-btn" onclick="processPayment()">Pay Now</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
    function toggleSubServices(serviceId) {
        const subServices = document.getElementById(`sub-services-${serviceId}`);
        const icon = document.getElementById(`icon-${serviceId}`);
        const expandBtn = icon.parentElement;
        
        if (subServices.style.display === 'block') {
            subServices.style.display = 'none';
            expandBtn.classList.remove('active');
            icon.classList.remove('fa-minus');
            icon.classList.add('fa-plus');
        } else {
            subServices.style.display = 'block';
            expandBtn.classList.add('active');
            icon.classList.remove('fa-plus');
            icon.classList.add('fa-minus');
        }
    }

    function updateQuantity(serviceId, action) {
        const input = document.getElementById(`quantity-${serviceId}`);
        let value = parseInt(input.value);
        
        if (action === 'increase' && value < 50) {
            input.value = value + 1;
        } else if (action === 'decrease' && value > 1) {
            input.value = value - 1;
        }
        
        updatePrice(serviceId, parseFloat(input.dataset.price));
    }

    function updatePrice(serviceId, basePrice) {
        const quantityInput = document.getElementById(`quantity-${serviceId}`);
        const measurementInput = document.getElementById(`measurement-${serviceId}`);
        
        if (quantityInput) {
            return parseInt(quantityInput.value) * basePrice;
        } else if (measurementInput) {
            return parseFloat(measurementInput.value || 0) * basePrice;
        }
        return basePrice;
    }

    function proceedToCheckout(subServiceId, name, basePrice, pricingType) {
        let quantity = 1;
        let measurement = 0;
        let finalPrice = basePrice;
        let summaryDetails = '';

        if (pricingType === 'quantity') {
            quantity = parseInt(document.getElementById(`quantity-${subServiceId}`).value);
            finalPrice = basePrice * quantity;
            summaryDetails = `<div class="quantity-details">Quantity: ${quantity} units</div>`;
        } else if (pricingType === 'measurement') {
            measurement = parseFloat(document.getElementById(`measurement-${subServiceId}`).value);
            if (!measurement) {
                alert('Please enter the measurement in meters');
                return;
            }
            finalPrice = basePrice * measurement;
            summaryDetails = `<div class="quantity-details">Measurement: ${measurement} meters</div>`;
        }

        // Calculate fees
        const convenienceFee = finalPrice * 0.05; // 5% convenience fee
        const total = finalPrice + convenienceFee;

        // Update service summary
        document.getElementById('selectedServiceInfo').innerHTML = `
            <h4>${name}</h4>
            <p>Service ID: ${subServiceId}</p>
            ${summaryDetails}
        `;

        // Update price summary
        document.getElementById('serviceCharge').textContent = `₹${finalPrice.toFixed(2)}`;
        document.getElementById('convenienceFee').textContent = `₹${convenienceFee.toFixed(2)}`;
        document.getElementById('totalAmount').textContent = `₹${total.toFixed(2)}`;

        // Add to form data
        let formData = document.getElementById('checkoutForm');
        formData.innerHTML += `
            <input type="hidden" name="quantity" value="${quantity}">
            <input type="hidden" name="measurement" value="${measurement}">
            <input type="hidden" name="final_price" value="${finalPrice}">
        `;

        // Show first step of checkout
        showStep(1);
        document.getElementById('checkoutModal').style.display = 'block';
    }

    function showStep(stepNumber) {
        // Update steps indicator
        document.querySelectorAll('.step').forEach((step, index) => {
            step.classList.toggle('active', index + 1 <= stepNumber);
        });

        // Hide all sections
        document.querySelectorAll('.checkout-section').forEach(section => {
            section.style.display = 'none';
        });

        // Show current section
        switch(stepNumber) {
            case 1:
                document.getElementById('serviceDetails').style.display = 'block';
                break;
            case 2:
                document.getElementById('scheduleSection').style.display = 'block';
                break;
            case 3:
                document.getElementById('paymentSection').style.display = 'block';
                break;
        }
    }

    function processPayment() {
        // Implement your payment gateway integration here
        // For example, Razorpay integration
        alert('Redirecting to payment gateway...');
    }

    // Close modal handlers
    document.querySelector('.close-modal').onclick = function() {
        document.getElementById('checkoutModal').style.display = 'none';
    }

    window.onclick = function(event) {
        if (event.target == document.getElementById('checkoutModal')) {
            document.getElementById('checkoutModal').style.display = 'none';
        }
    }
    </script>
</body>
</html>
