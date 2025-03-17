<?php
// Include database connection
require_once 'dbconnect.php';
session_start();

// Initialize cart if it doesn't exist
if (!isset($_SESSION['guest_cart'])) {
    $_SESSION['guest_cart'] = [];
    $_SESSION['guest_cart_count'] = 0;
}

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

// Handle cart actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    
    if ($action === 'add_to_cart') {
        $sub_service_id = $_POST['sub_service_id'];
        $quantity = isset($_POST['quantity']) ? (int)$_POST['quantity'] : 1;
        $measurement = isset($_POST['measurement']) ? (float)$_POST['measurement'] : 0;
        $final_price = $_POST['final_price'];
        
        // Get service details from database
        $stmt = $conn->prepare("
            SELECT ss.sub_service_name, ss.price, s.pricing_type, s.service_name
            FROM tbl_sub_services ss
            JOIN tbl_services s ON ss.service_id = s.service_id
            WHERE ss.sub_service_id = ?
        ");
        $stmt->bind_param("i", $sub_service_id);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        
        if ($result) {
            // Add to guest cart
            $_SESSION['guest_cart'][$sub_service_id] = [
                'sub_service_id' => $sub_service_id,
                'name' => $result['sub_service_name'],
                'service_name' => $result['service_name'],
                'price' => $result['price'],
                'pricing_type' => $result['pricing_type'],
                'quantity' => $quantity,
                'measurement' => $measurement,
                'final_price' => $final_price
            ];
            
            // Update cart count
            $_SESSION['guest_cart_count'] = count($_SESSION['guest_cart']);
            
            echo json_encode(['success' => true, 'cart_count' => $_SESSION['guest_cart_count']]);
            exit;
        }
    } 
    elseif ($action === 'get_cart') {
        // Calculate totals
        $subtotal = 0;
        foreach ($_SESSION['guest_cart'] as $item) {
            $subtotal += $item['final_price'];
        }
        
        $convenience_fee = $subtotal * 0.05; // 5% fee
        $grand_total = $subtotal + $convenience_fee;
        
        echo json_encode([
            'success' => true,
            'cart_items' => $_SESSION['guest_cart'],
            'cart_count' => $_SESSION['guest_cart_count'],
            'subtotal' => $subtotal,
            'convenience_fee' => $convenience_fee,
            'grand_total' => $grand_total
        ]);
        exit;
    }
    elseif ($action === 'remove_from_cart') {
        $sub_service_id = $_POST['sub_service_id'];
        
        if (isset($_SESSION['guest_cart'][$sub_service_id])) {
            unset($_SESSION['guest_cart'][$sub_service_id]);
            $_SESSION['guest_cart_count'] = count($_SESSION['guest_cart']);
            
            echo json_encode(['success' => true, 'cart_count' => $_SESSION['guest_cart_count']]);
            exit;
        }
    }
}

// Update the place_booking handler with proper checks
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    
    // Check if action exists in POST data
    $action = $_POST['action'] ?? '';
    
    switch($action) {
        case 'add_to_cart':
            if (isset($_POST['sub_service_id'])) {
                $sub_service_id = intval($_POST['sub_service_id']);
                $quantity = isset($_POST['quantity']) ? intval($_POST['quantity']) : 1;
                $measurement = isset($_POST['measurement']) ? floatval($_POST['measurement']) : 0;
                
                // Fetch sub-service details
                $stmt = $conn->prepare("
                    SELECT 
                        ss.*,
                        s.service_name,
                        s.provider_id,
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
                    WHERE ss.sub_service_id = ?
                ");
                $stmt->bind_param("i", $sub_service_id);
                $stmt->execute();
                $result = $stmt->get_result();
                
                if ($sub_service = $result->fetch_assoc()) {
                    // Calculate final price based on pricing type
                    $final_price = $sub_service['price'];
                    if ($sub_service['pricing_type'] === 'quantity') {
                        $final_price *= $quantity;
                    } elseif ($sub_service['pricing_type'] === 'measurement') {
                        $final_price *= $measurement;
                    }
                    
                    // Initialize cart if not exists
                    if (!isset($_SESSION['cart'])) {
                        $_SESSION['cart'] = [];
                    }
                    
                    // Add or update cart item
                    $_SESSION['cart'][$sub_service_id] = [
                        'sub_service_id' => $sub_service_id,
                        'name' => $sub_service['sub_service_name'],
                        'service_name' => $sub_service['service_name'],
                        'price' => $sub_service['price'],
                        'quantity' => $quantity,
                        'measurement' => $measurement,
                        'pricing_type' => $sub_service['pricing_type'],
                        'final_price' => $final_price,
                        'provider_id' => $sub_service['provider_id']
                    ];
                    
                    echo json_encode([
                        'success' => true,
                        'message' => 'Item added to cart',
                        'cart_count' => count($_SESSION['cart']),
                        'cart_items' => $_SESSION['cart']
                    ]);
                } else {
                    echo json_encode([
                        'success' => false,
                        'message' => 'Service not found'
                    ]);
                }
                exit;
            }
            break;
            
        case 'remove_from_cart':
            if (isset($_POST['sub_service_id'])) {
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
            break;
            
        case 'update_quantity':
            if (isset($_POST['sub_service_id']) && isset($_POST['quantity'])) {
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
            break;
            
        case 'get_cart':
            $cart_total = 0;
            $cart_items = $_SESSION['cart'] ?? [];
            
            // Calculate total from cart items
            foreach ($cart_items as $item) {
                $cart_total += floatval($item['final_price']);
            }
            
            $convenience_fee = $cart_total * 0.05;
            $grand_total = $cart_total + $convenience_fee;
            
            echo json_encode([
                'success' => true,
                'cart_count' => count($cart_items),
                'cart_items' => $cart_items,
                'subtotal' => $cart_total,
                'convenience_fee' => $convenience_fee,
                'grand_total' => $grand_total
            ]);
            exit;
            
        case 'place_booking':
            try {
                // Debug: Log session and POST data
                error_log("DEBUG - Session Data: " . json_encode($_SESSION));
                error_log("DEBUG - POST Data: " . json_encode($_POST));

                // Check if user is logged in
                if (!isset($_SESSION['user_id'])) {
                    throw new Exception('Please login to continue booking.');
                }

                // Check cart
                if (!isset($_SESSION['cart']) || empty($_SESSION['cart'])) {
                    throw new Exception('Cart is empty. Please add services to cart.');
                }

                // Validate required fields
                $required_fields = [
                    'booking_date' => 'Booking date',
                    'booking_time' => 'Booking time',
                    'address' => 'Address',
                    'payment_method' => 'Payment method'
                ];

                foreach ($required_fields as $field => $label) {
                    if (!isset($_POST[$field]) || empty($_POST[$field])) {
                        throw new Exception($label . ' is required.');
                    }
                }

                // Start transaction
                $conn->begin_transaction();

                // Prepare booking data
                $booking_reference = generateBookingReference();
                $user_id = $_SESSION['user_id'];
                $booking_date = $_POST['booking_date'];
                $booking_time = $_POST['booking_time'];
                $address = $_POST['address'];
                $payment_method = 'COD'; // Simplified to COD only

                // Calculate totals
                $subtotal = 0;
                foreach ($_SESSION['cart'] as $item) {
                    $subtotal += floatval($item['final_price']);
                }
                $convenience_fee = $subtotal * 0.05;
                $total_amount = $subtotal + $convenience_fee;

                // Get provider_id from first cart item
                $first_item = reset($_SESSION['cart']);
                $provider_id = $first_item['provider_id'];

                // Insert booking
                $booking_query = "INSERT INTO bookings (
                    booking_reference, 
                    user_id, 
                    provider_id,
                    booking_date, 
                    booking_time, 
                    address,
                    subtotal,
                    convenience_fee,
                    total_amount,
                    payment_method,
                    payment_status,
                    status
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

                $stmt = $conn->prepare($booking_query);
                if (!$stmt) {
                    throw new Exception('Database Error: ' . $conn->error);
                }

                $payment_status = 'pending'; // For COD
                $booking_status = 'pending';

                $stmt->bind_param(
                    "siisssdddss",
                    $booking_reference,
                    $user_id,
                    $provider_id,
                    $booking_date,
                    $booking_time,
                    $address,
                    $subtotal,
                    $convenience_fee,
                    $total_amount,
                    $payment_method,
                    $payment_status,
                    $booking_status
                );

                if (!$stmt->execute()) {
                    throw new Exception('Error creating booking: ' . $stmt->error);
                }

                $booking_id = $conn->insert_id;

                // Insert booking items
                foreach ($_SESSION['cart'] as $item) {
                    $items_query = "INSERT INTO booking_items (
                        booking_id,
                        sub_service_id,
                        quantity,
                        measurement,
                        unit_price,
                        total_price
                    ) VALUES (?, ?, ?, ?, ?, ?)";

                    $stmt = $conn->prepare($items_query);
                    if (!$stmt) {
                        throw new Exception('Error preparing booking items: ' . $conn->error);
                    }

                    $quantity = isset($item['quantity']) ? floatval($item['quantity']) : 1;
                    $measurement = isset($item['measurement']) ? floatval($item['measurement']) : 0;
                    $unit_price = floatval($item['price']);
                    $total_price = floatval($item['final_price']);

                    $stmt->bind_param(
                        "iidddd",
                        $booking_id,
                        $item['sub_service_id'],
                        $quantity,
                        $measurement,
                        $unit_price,
                        $total_price
                    );

                    if (!$stmt->execute()) {
                        throw new Exception('Error adding booking item: ' . $stmt->error);
                    }
                }

                // Commit transaction and clear cart
                $conn->commit();
                $_SESSION['cart'] = [];

                echo json_encode([
                    'success' => true,
                    'booking_reference' => $booking_reference,
                    'message' => 'Booking placed successfully!'
                ]);

            } catch (Exception $e) {
                if (isset($conn) && $conn->ping()) {
                    $conn->rollback();
                }
                error_log("BOOKING ERROR: " . $e->getMessage());
                echo json_encode([
                    'success' => false,
                    'message' => $e->getMessage()
                ]);
            }
            break;
            
        default:
            // No action or unknown action
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                echo json_encode([
                    'success' => false,
                    'message' => 'Invalid action'
                ]);
                exit;
            }
    }
}

// Add this function to calculate total price
function calculateCartTotal($cart) {
    $total = 0;
    foreach ($cart as $item) {
        $total += $item['price'] * $item['quantity'];
    }
    return $total;
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

// Add this function near other functions
function generateBookingReference() {
    return 'BK' . date('Ymd') . substr(uniqid(), -6);
}

// Function to handle visit booking
function generateVisitReference() {
    return 'VT' . date('Ymd') . substr(uniqid(), -6);
}

// Add visit booking fee constant
define('VISIT_BOOKING_FEE', 99); // ₹99 fixed fee for visit booking

// Handle visit booking action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'book_visit') {
    try {
        // Debug: Log session and POST data
        error_log("DEBUG - Visit Booking - Session Data: " . json_encode($_SESSION));
        error_log("DEBUG - Visit Booking - POST Data: " . json_encode($_POST));

        // Remove login check
        // if (!isset($_SESSION['user_id'])) {
        //     throw new Exception('Please login to book a visit.');
        // }

        // Validate required fields
        $required_fields = [
            'visit_date' => 'Visit date',
            'visit_time' => 'Visit time',
            'visit_address' => 'Address',
            'category_id' => 'Service category',
            'visitor_name' => 'Your name',
            'visitor_phone' => 'Phone number',
            'visitor_email' => 'Email address'
        ];

        foreach ($required_fields as $field => $label) {
            if (!isset($_POST[$field]) || empty($_POST[$field])) {
                throw new Exception($label . ' is required.');
            }
        }

        // Start transaction
        $conn->begin_transaction();

        // Prepare visit booking data
        $visit_reference = generateVisitReference();
        $user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 0; // Use 0 for guest users
        $category_id = $_POST['category_id'];
        $visit_date = $_POST['visit_date'];
        $visit_time = $_POST['visit_time'];
        $visit_address = $_POST['visit_address'];
        $visit_notes = isset($_POST['visit_notes']) ? $_POST['visit_notes'] : '';
        $visitor_name = $_POST['visitor_name'];
        $visitor_phone = $_POST['visitor_phone'];
        $visitor_email = $_POST['visitor_email'];
        $visit_fee = VISIT_BOOKING_FEE;
        $payment_method = 'COD'; // Default to COD
        $payment_status = 'pending';
        $visit_status = 'scheduled';

        // Get a service provider for this category
        $provider_query = "SELECT provider_id FROM service_providers WHERE category_id = ? AND is_active = 1 LIMIT 1";
        $stmt = $conn->prepare($provider_query);
        $stmt->bind_param("i", $category_id);
        $stmt->execute();
        $provider_result = $stmt->get_result();
        
        if ($provider_result->num_rows === 0) {
            throw new Exception('No service provider available for this category.');
        }
        
        $provider = $provider_result->fetch_assoc();
        $provider_id = $provider['provider_id'];

        // Modify the visit_bookings table to include guest information
        $create_visit_bookings_table = "
        CREATE TABLE IF NOT EXISTS visit_bookings (
            visit_id INT AUTO_INCREMENT PRIMARY KEY,
            visit_reference VARCHAR(20) NOT NULL,
            user_id INT NOT NULL DEFAULT 0,
            provider_id INT NOT NULL,
            category_id INT NOT NULL,
            visit_date DATE NOT NULL,
            visit_time TIME NOT NULL,
            address TEXT NOT NULL,
            notes TEXT,
            visitor_name VARCHAR(100),
            visitor_phone VARCHAR(20),
            visitor_email VARCHAR(100),
            visit_fee DECIMAL(10,2) NOT NULL DEFAULT 99.00,
            payment_method VARCHAR(20) NOT NULL DEFAULT 'COD',
            payment_status VARCHAR(20) NOT NULL DEFAULT 'pending',
            status VARCHAR(20) NOT NULL DEFAULT 'scheduled',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (provider_id) REFERENCES service_providers(provider_id),
            FOREIGN KEY (category_id) REFERENCES tbl_categories(category_id)
        )";

        // Execute the create table statement if needed
        $conn->query($create_visit_bookings_table);

        // Insert visit booking with guest information
        $visit_query = "INSERT INTO visit_bookings (
            visit_reference, 
            user_id, 
            provider_id,
            category_id,
            visit_date, 
            visit_time, 
            address,
            notes,
            visitor_name,
            visitor_phone,
            visitor_email,
            visit_fee,
            payment_method,
            payment_status,
            status
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

        $stmt = $conn->prepare($visit_query);
        if (!$stmt) {
            throw new Exception('Database Error: ' . $conn->error);
        }

        $stmt->bind_param(
            "siissssssssdsss",
            $visit_reference,
            $user_id,
            $provider_id,
            $category_id,
            $visit_date,
            $visit_time,
            $visit_address,
            $visit_notes,
            $visitor_name,
            $visitor_phone,
            $visitor_email,
            $visit_fee,
            $payment_method,
            $payment_status,
            $visit_status
        );

        if (!$stmt->execute()) {
            throw new Exception('Error booking visit: ' . $stmt->error);
        }

        // Commit transaction
        $conn->commit();

        echo json_encode([
            'success' => true,
            'visit_reference' => $visit_reference,
            'message' => 'Visit booked successfully! A technician will visit you on the scheduled date.'
        ]);
        exit;

    } catch (Exception $e) {
        if (isset($conn) && $conn->ping()) {
            $conn->rollback();
        }
        error_log("VISIT BOOKING ERROR: " . $e->getMessage());
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
        exit;
    }
}

// Add emergency booking fee constant
define('EMERGENCY_BOOKING_FEE', 299); // ₹299 premium fee for emergency bookings

// Handle emergency booking action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'book_emergency') {
    try {
        // Debug: Log session and POST data
        error_log("DEBUG - Emergency Booking - Session Data: " . json_encode($_SESSION));
        error_log("DEBUG - Emergency Booking - POST Data: " . json_encode($_POST));

        // Validate required fields
        $required_fields = [
            'emergency_address' => 'Address',
            'category_id' => 'Service category',
            'emergency_issue' => 'Issue description',
            'emergency_name' => 'Your name',
            'emergency_phone' => 'Phone number',
            'emergency_email' => 'Email address'
        ];

        foreach ($required_fields as $field => $label) {
            if (!isset($_POST[$field]) || empty($_POST[$field])) {
                throw new Exception($label . ' is required.');
            }
        }

        // Start transaction
        $conn->begin_transaction();

        // Prepare emergency booking data
        $emergency_reference = 'EM' . date('Ymd') . substr(uniqid(), -6);
        $user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 0; // Use 0 for guest users
        $category_id = $_POST['category_id'];
        $emergency_address = $_POST['emergency_address'];
        $emergency_issue = $_POST['emergency_issue'];
        $emergency_name = $_POST['emergency_name'];
        $emergency_phone = $_POST['emergency_phone'];
        $emergency_email = $_POST['emergency_email'];
        $emergency_fee = EMERGENCY_BOOKING_FEE;
        $payment_method = 'COD'; // Default to COD
        $payment_status = 'pending';
        $emergency_status = 'urgent';

        // Get a service provider for this category
        $provider_query = "SELECT provider_id FROM service_providers WHERE category_id = ? AND is_active = 1 LIMIT 1";
        $stmt = $conn->prepare($provider_query);
        $stmt->bind_param("i", $category_id);
        $stmt->execute();
        $provider_result = $stmt->get_result();
        
        if ($provider_result->num_rows === 0) {
            throw new Exception('No service provider available for this category.');
        }
        
        $provider = $provider_result->fetch_assoc();
        $provider_id = $provider['provider_id'];

        // Insert emergency booking
        $emergency_query = "INSERT INTO emergency_bookings (
            emergency_reference, 
            user_id, 
            provider_id,
            category_id,
            address,
            issue_description,
            customer_name,
            customer_phone,
            customer_email,
            emergency_fee,
            payment_method,
            payment_status,
            status
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

        $stmt = $conn->prepare($emergency_query);
        if (!$stmt) {
            throw new Exception('Database Error: ' . $conn->error);
        }

        $stmt->bind_param(
            "siissssssdsss",
            $emergency_reference,
            $user_id,
            $provider_id,
            $category_id,
            $emergency_address,
            $emergency_issue,
            $emergency_name,
            $emergency_phone,
            $emergency_email,
            $emergency_fee,
            $payment_method,
            $payment_status,
            $emergency_status
        );

        if (!$stmt->execute()) {
            throw new Exception('Error booking emergency service: ' . $stmt->error);
        }

        // Commit transaction
        $conn->commit();

        echo json_encode([
            'success' => true,
            'emergency_reference' => $emergency_reference,
            'message' => 'Emergency service requested! A technician will contact you shortly.'
        ]);
        exit;

    } catch (Exception $e) {
        if (isset($conn) && $conn->ping()) {
            $conn->rollback();
        }
        error_log("EMERGENCY BOOKING ERROR: " . $e->getMessage());
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
        exit;
    }
}

// ... existing code ...
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
            display: flex;
            flex-direction: column;
            align-items: flex-start;
        }

        .category-title {
            font-size: 28px;
            color: #2d3748;
            margin-bottom: 10px;
        }

        .category-rating {
            margin-bottom: 15px;
        }

        .service-item {
            border-bottom: 1px solid #eee;
            padding: 20px 0;
        }

        .service-item:last-child {
            border-bottom: none;
        }

        .service-header {
            cursor: pointer;
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px;
            border-bottom: 1px solid #eee;
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

        .expand-btn i {
            font-size: 14px;
            transition: transform 0.2s;
        }

        .expand-btn:hover {
            background: #7e3af2;
            color: white;
        }

        .sub-services-panel {
            display: none;
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

        /* Add these new styles */
        .cart-float {
            position: fixed;
            bottom: 30px;
            right: 30px;
            z-index: 1000;
        }

        .cart-float button {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: #7e3af2;
            border: none;
            color: white;
            font-size: 24px;
            cursor: pointer;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }

        .cart-count {
            position: absolute;
            top: -8px;
            right: -8px;
            background: #e53e3e;
            color: white;
            border-radius: 50%;
            width: 24px;
            height: 24px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 14px;
            font-weight: bold;
        }

        .add-to-cart-btn {
            background: #7e3af2;
            color: white;
            border: none;
            padding: 8px 20px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 14px;
            transition: background 0.3s;
        }

        .add-to-cart-btn:hover {
            background: #6c2bd9;
        }

        .success-message {
            position: fixed;
            top: 20px;
            right: 20px;
            background-color: #48bb78;
            color: white;
            padding: 12px 24px;
            border-radius: 6px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.15);
            z-index: 1000;
            animation: slideIn 0.3s ease-out, fadeOut 0.5s ease-out 2s forwards;
        }

        @keyframes slideIn {
            from {
                transform: translateX(100%);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }

        @keyframes fadeOut {
            from {
                opacity: 1;
            }
            to {
                opacity: 0;
            }
        }

        /* Add styles for payment success modal */
        .payment-success-modal {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            display: none;
            justify-content: center;
            align-items: center;
            z-index: 1001;
        }

        .payment-success-modal .modal-content {
            background: white;
            padding: 40px;
            border-radius: 12px;
            text-align: center;
            max-width: 500px;
            width: 90%;
            position: relative;
            transform: scale(0.7);
            transition: transform 0.3s ease-out;
        }

        .payment-success-modal.show .modal-content {
            transform: scale(1);
        }

        .success-icon {
            color: #28a745;
            font-size: 64px;
            margin-bottom: 20px;
        }

        .modal-buttons {
            margin-top: 30px;
            display: flex;
            gap: 15px;
            justify-content: center;
        }

        .modal-buttons button {
            padding: 12px 24px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
            transition: background-color 0.3s;
        }

        .modal-buttons button:first-child {
            background: #7e3af2;
            color: white;
        }

        .modal-buttons button:last-child {
            background: #e2e8f0;
            color: #4a5568;
        }

        .modal-buttons button:hover {
            opacity: 0.9;
        }

        .empty-cart {
            text-align: center;
            padding: 20px;
            color: #666;
            font-style: italic;
        }

        .service-meta {
            color: #666;
            font-size: 14px;
            margin: 5px 0;
            display: flex;
            justify-content: space-between;
        }

        .price {
            font-weight: 600;
            color: #2d3748;
            margin: 5px 0;
            text-align: right;
        }

        .service-item {
            background: #f8fafc;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 10px;
        }

        .service-details h4 {
            margin: 0 0 10px 0;
            color: #2d3748;
        }

        #serviceCharge, #convenienceFee, #totalAmount {
            font-weight: 600;
            color: #2d3748;
        }

        .price-row {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            border-top: 1px solid #e2e8f0;
        }

        .price-row.total {
            font-size: 1.2em;
            font-weight: 600;
            border-top: 2px solid #e2e8f0;
            margin-top: 10px;
        }

        .login-prompt-modal {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.7);
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 1000;
        }

        .login-prompt-modal .modal-content {
            background: white;
            padding: 30px;
            border-radius: 10px;
            width: 90%;
            max-width: 500px;
            text-align: center;
        }

        .login-prompt-modal h3 {
            margin-bottom: 15px;
            color: #333;
        }

        .login-prompt-modal .button-group {
            display: flex;
            justify-content: center;
            gap: 15px;
            margin-top: 20px;
        }

        .login-prompt-modal button {
            padding: 12px 24px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-weight: 500;
            transition: all 0.3s;
        }

        .login-prompt-modal button:first-child {
            background: #099409;
            color: white;
        }

        .login-prompt-modal button:last-child {
            background: #f8f9fa;
            color: #333;
            border: 1px solid #ddd;
        }

        .login-prompt-modal button:hover {
            transform: translateY(-2px);
        }

        /* Book a Visit Button Styles */
        .book-visit-btn {
            background: #28a745;
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 6px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            margin-top: 15px;
            width: auto;
        }
        
        .book-visit-btn:hover {
            background: #218838;
        }
        
        /* Visit Booking Modal Styles */
        .visit-modal {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            display: none;
            justify-content: center;
            align-items: center;
            z-index: 1001;
        }
        
        .visit-modal .modal-content {
            background: white;
            padding: 30px;
            border-radius: 12px;
            width: 90%;
            max-width: 500px;
            position: relative;
        }
        
        .visit-modal .close-modal {
            position: absolute;
            top: 15px;
            right: 15px;
            font-size: 24px;
            cursor: pointer;
        }
        
        .visit-modal h3 {
            margin-bottom: 20px;
            color: #2d3748;
            font-size: 22px;
        }
        
        .visit-fee-notice {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            border-left: 4px solid #28a745;
        }
        
        .visit-fee-notice p {
            margin: 0;
            font-size: 15px;
        }
        
        .visit-fee-notice .fee {
            font-weight: 600;
            color: #28a745;
        }
        
        .visit-success-modal {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            display: none;
            justify-content: center;
            align-items: center;
            z-index: 1001;
        }
        
        .visit-success-modal .modal-content {
            background: white;
            padding: 40px;
            border-radius: 12px;
            text-align: center;
            max-width: 500px;
            width: 90%;
            position: relative;
            transform: scale(0.7);
            transition: transform 0.3s ease-out;
        }
        
        .visit-success-modal.show .modal-content {
            transform: scale(1);
        }

        /* Emergency Booking Button Styles */
        .emergency-btn {
            background: #e53e3e;
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 6px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            margin-top: 15px;
            margin-left: 15px;
            width: auto;
        }
        
        .emergency-btn:hover {
            background: #c53030;
        }
        
        /* Emergency Booking Modal Styles */
        .emergency-modal {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            display: none;
            justify-content: center;
            align-items: center;
            z-index: 1001;
            overflow-y: auto; /* Enable vertical scrolling */
            padding: 20px;
        }
        
        .emergency-modal .modal-content {
            background: white;
            padding: 30px;
            border-radius: 12px;
            width: 90%;
            max-width: 500px;
            position: relative;
            max-height: 90vh; /* Maximum height */
            overflow-y: auto; /* Enable scrolling within the modal content */
            margin: auto; /* Center the modal */
        }
        
        .emergency-modal .close-modal {
            position: absolute;
            top: 15px;
            right: 15px;
            font-size: 24px;
            cursor: pointer;
            z-index: 1002; /* Ensure it's above other content */
            background: #fff;
            width: 30px;
            height: 30px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 2px 5px rgba(0,0,0,0.2);
        }
        
        .emergency-modal .close-modal:hover {
            background: #f8f8f8;
        }
        
        .emergency-modal .modal-header {
            position: sticky;
            top: 0;
            background: white;
            padding-bottom: 15px;
            margin-bottom: 15px;
            border-bottom: 1px solid #eee;
            z-index: 1;
        }
        
        .emergency-modal .modal-footer {
            position: sticky;
            bottom: 0;
            background: white;
            padding-top: 15px;
            margin-top: 15px;
            border-top: 1px solid #eee;
        }
        
        /* Cancel button for mobile */
        .cancel-btn {
            display: none;
            width: 100%;
            padding: 12px;
            margin-top: 10px;
            background: #f1f1f1;
            border: none;
            border-radius: 6px;
            font-weight: 600;
            cursor: pointer;
        }
        
        @media (max-width: 768px) {
            .emergency-modal {
                align-items: flex-start;
                padding: 10px;
            }
            
            .emergency-modal .modal-content {
                padding: 20px;
                max-height: 85vh;
                margin-top: 30px;
            }
            
            .cancel-btn {
                display: block;
            }
        }
        
        .emergency-fee-notice {
            background: #fff5f5;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            border-left: 4px solid #e53e3e;
        }
        
        .emergency-fee-notice p {
            margin: 0;
            font-size: 15px;
        }
        
        .emergency-fee-notice .fee {
            font-weight: 600;
            color: #e53e3e;
        }
        
        .emergency-success-modal {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            display: none;
            justify-content: center;
            align-items: center;
            z-index: 1001;
        }
        
        .emergency-success-modal .modal-content {
            background: white;
            padding: 40px;
            border-radius: 12px;
            text-align: center;
            max-width: 500px;
            width: 90%;
            position: relative;
            transform: scale(0.7);
            transition: transform 0.3s ease-out;
        }
        
        .emergency-success-modal.show .modal-content {
            transform: scale(1);
        }
        
        .emergency-icon {
            color: #e53e3e;
            font-size: 64px;
            margin-bottom: 20px;
        }
        
        /* Action buttons container */
        .action-buttons {
            display: flex;
            gap: 10px;
            margin-top: 15px;
        }
        
        @media (max-width: 768px) {
            .action-buttons {
                flex-direction: column;
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
                
                <!-- Action buttons container -->
                <div class="action-buttons">
                    <!-- Book a Visit Button -->
                    <button class="book-visit-btn" onclick="showVisitModal()">
                        <i class="fas fa-calendar-check"></i> Book a Visit
                    </button>
                    
                    <!-- Emergency Service Button -->
                    <button class="emergency-btn" onclick="showEmergencyModal()">
                        <i class="fas fa-exclamation-triangle"></i> Emergency Service
                    </button>
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
                                                    
                                                    <button class="add-to-cart-btn" 
                                                            onclick="addToCart(<?php echo $sub['sub_service_id']; ?>, 
                                                             '<?php echo htmlspecialchars($sub['sub_service_name']); ?>', 
                                                             <?php echo $sub['price']; ?>,
                                                             '<?php echo $pricing_type; ?>')">
                                                        Add to Cart
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
                            <label>
                                <input type="radio" name="payment_method" value="COD"> Cash on Delivery
                            </label>
                        </div>
                        <button type="button" class="pay-btn" onclick="processPayment()">Pay Now</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Add a floating cart button -->
    <div id="cart-floating-button" class="cart-float">
        <span class="cart-count">0</span>
        <button onclick="showCart()">
            <i class="fas fa-shopping-cart"></i>
        </button>
    </div>

    <!-- Visit Booking Modal -->
    <div id="visitModal" class="visit-modal">
        <div class="modal-content">
            <span class="close-modal" onclick="closeVisitModal()">&times;</span>
            <h3>Book a Technical Visit</h3>
            
            <div class="visit-fee-notice">
                <p>A technician will visit your location to assess your requirements.</p>
                <p>Visit fee: <span class="fee">₹<?php echo VISIT_BOOKING_FEE; ?></span> (payable on visit)</p>
            </div>
            
            <form id="visitForm">
                <input type="hidden" name="action" value="book_visit">
                <input type="hidden" name="category_id" value="<?php echo $category_id; ?>">
                
                <div class="form-group">
                    <label>Visit Date</label>
                    <input type="date" name="visit_date" required min="<?php echo date('Y-m-d'); ?>">
                </div>
                
                <div class="form-group">
                    <label>Visit Time</label>
                    <select name="visit_time" required>
                        <?php for($i = 9; $i <= 17; $i++): ?>
                            <option value="<?php echo sprintf('%02d:00', $i); ?>">
                                <?php echo date('h:i A', strtotime(sprintf('%02d:00', $i))); ?>
                            </option>
                        <?php endfor; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>Address</label>
                    <textarea name="visit_address" required rows="3"></textarea>
                </div>
                
                <div class="form-group">
                    <label>Additional Notes (optional)</label>
                    <textarea name="visit_notes" rows="3" placeholder="Describe your requirements or issues"></textarea>
                </div>
                
                <button type="button" class="book-button" onclick="bookVisit()">Confirm Visit</button>
            </form>
        </div>
    </div>
    
    <!-- Visit Success Modal -->
    <div id="visitSuccessModal" class="visit-success-modal">
        <div class="modal-content">
            <i class="fas fa-check-circle success-icon"></i>
            <h2>Visit Scheduled!</h2>
            <p>Your technical visit has been successfully scheduled.</p>
            <p>Visit Reference: <strong id="visitReference"></strong></p>
            <p>A confirmation email has been sent to your registered email address.</p>
            <div class="modal-buttons">
                <button onclick="window.location.href='visits.php'">View My Visits</button>
                <button onclick="window.location.reload()">Continue Shopping</button>
            </div>
        </div>
    </div>

    <!-- Emergency Booking Modal -->
    <div id="emergencyModal" class="emergency-modal">
        <div class="modal-content">
            <span class="close-modal" onclick="closeEmergencyModal()">&times;</span>
            
            <div class="modal-header">
                <h3><i class="fas fa-exclamation-triangle"></i> Emergency Service Request</h3>
                
                <div class="emergency-fee-notice">
                    <p><strong>Need urgent help?</strong> Our technicians will prioritize your request.</p>
                    <p>Emergency service fee: <span class="fee">₹<?php echo EMERGENCY_BOOKING_FEE; ?></span> (additional to service charges)</p>
                    <p>Expected response time: <strong>Within 2 hours</strong></p>
                </div>
            </div>
            
            <form id="emergencyForm">
                <input type="hidden" name="action" value="book_emergency">
                <input type="hidden" name="category_id" value="<?php echo $category_id; ?>">
                
                <div class="form-group">
                    <label>Your Name*</label>
                    <input type="text" name="emergency_name" required>
                </div>
                
                <div class="form-group">
                    <label>Phone Number*</label>
                    <input type="tel" name="emergency_phone" required pattern="[0-9]{10}">
                </div>
                
                <div class="form-group">
                    <label>Email Address*</label>
                    <input type="email" name="emergency_email" required>
                </div>
                
                <div class="form-group">
                    <label>Address*</label>
                    <textarea name="emergency_address" required rows="3"></textarea>
                </div>
                
                <div class="form-group">
                    <label>Describe Your Emergency*</label>
                    <textarea name="emergency_issue" required rows="4" placeholder="Please provide details about your emergency situation"></textarea>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="book-button" style="background-color: #e53e3e;" onclick="bookEmergency()">Request Emergency Service</button>
                    <button type="button" class="cancel-btn" onclick="closeEmergencyModal()">Cancel</button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Emergency Success Modal -->
    <div id="emergencySuccessModal" class="emergency-success-modal">
        <div class="modal-content">
            <i class="fas fa-exclamation-circle emergency-icon"></i>
            <h2>Emergency Request Received!</h2>
            <p>Your emergency service request has been successfully submitted.</p>
            <p>Reference: <strong id="emergencyReference"></strong></p>
            <p>A technician will contact you shortly. Please keep your phone available.</p>
            <div class="modal-buttons">
                <button onclick="window.location.href='emergency.php'" style="background-color: #e53e3e;">View Request Status</button>
                <button onclick="window.location.reload()">Close</button>
            </div>
        </div>
    </div>

    <script>
     function toggleSubServices(serviceId) {
        const subServices = document.getElementById(`sub-services-${serviceId}`);
        const icon = document.getElementById(`icon-${serviceId}`);
        
        if (!subServices || !icon) {
            console.error('Elements not found:', { subServices, icon });
            return;
        }
        
        // Toggle display
        if (subServices.style.display === '' || subServices.style.display === 'none') {
            subServices.style.display = 'block';
            icon.className = 'fas fa-minus';
        } else {
            subServices.style.display = 'none';
            icon.className = 'fas fa-plus';
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

    // Close modal handlers
    document.querySelector('.close-modal').onclick = function() {
        document.getElementById('checkoutModal').style.display = 'none';
    }

    window.onclick = function(event) {
        if (event.target == document.getElementById('checkoutModal')) {
            document.getElementById('checkoutModal').style.display = 'none';
        }
    }

    // Add these new JavaScript functions
    let cart = [];

    function showSuccessMessage(message) {
        const messageDiv = document.createElement('div');
        messageDiv.className = 'success-message';
        messageDiv.textContent = message;
        document.body.appendChild(messageDiv);

        // Remove the message after animation completes
        setTimeout(() => {
            messageDiv.remove();
        }, 2500);
    }

    function addToCart(subServiceId, name, basePrice, pricingType) {
        let quantity = 1;
        let measurement = 0;
        let finalPrice = basePrice;

        if (pricingType === 'quantity') {
            quantity = parseInt(document.getElementById(`quantity-${subServiceId}`).value);
            finalPrice = basePrice * quantity;
        } else if (pricingType === 'measurement') {
            measurement = parseFloat(document.getElementById(`measurement-${subServiceId}`).value);
            if (!measurement) {
                alert('Please enter the measurement in meters');
                return;
            }
            finalPrice = basePrice * measurement;
        }

        fetch(window.location.href, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: new URLSearchParams({
                action: 'add_to_cart',
                sub_service_id: subServiceId,
                quantity: quantity,
                measurement: measurement,
                final_price: finalPrice
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                updateCartCount();
                showSuccessMessage('Item added to cart');
            } else {
                showPaymentError(data.message || 'Error adding item to cart');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showPaymentError('Error adding item to cart');
        });
    }

    function updateCartCount() {
        fetch(window.location.href, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: new URLSearchParams({
                action: 'get_cart'
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                document.querySelector('.cart-count').textContent = data.cart_count;
            }
        })
        .catch(error => console.error('Error:', error));
    }

    // Initialize cart count when page loads
    document.addEventListener('DOMContentLoaded', function() {
        updateCartCount();
    });

    function showCart() {
        fetch(window.location.href, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: new URLSearchParams({
                action: 'get_cart'
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                let cartItems = data.cart_items;
                
                // Update service summary with all cart items
                let summaryHTML = Object.values(cartItems).map(item => {
                    return `
                        <div class="service-item">
                            <div class="service-details">
                                <h4>${item.name}</h4>
                                <p class="service-meta">
                                    ${item.pricing_type === 'quantity' ? 
                                        `Quantity: ${item.quantity} units × ₹${parseFloat(item.price).toFixed(2)}` : 
                                        item.pricing_type === 'measurement' ? 
                                        `Measurement: ${item.measurement} meters × ₹${parseFloat(item.price).toFixed(2)}` : 
                                        `Fixed Price: ₹${parseFloat(item.price).toFixed(2)}`}
                                </p>
                                <p class="price">Subtotal: ₹${parseFloat(item.final_price).toFixed(2)}</p>
                            </div>
                            <button class="remove-item-btn" onclick="removeFromCart(${item.sub_service_id})">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    `;
                }).join('');

                // If cart is empty, show message
                if (Object.keys(cartItems).length === 0) {
                    summaryHTML = '<div class="empty-cart">Your cart is empty</div>';
                }

                document.getElementById('selectedServiceInfo').innerHTML = summaryHTML;
                document.getElementById('serviceCharge').textContent = `₹${parseFloat(data.subtotal).toFixed(2)}`;
                document.getElementById('convenienceFee').textContent = `₹${parseFloat(data.convenience_fee).toFixed(2)}`;
                document.getElementById('totalAmount').textContent = `₹${parseFloat(data.grand_total).toFixed(2)}`;

                // Show checkout modal
                showStep(1);
                document.getElementById('checkoutModal').style.display = 'block';
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showPaymentError('Error loading cart');
        });
    }

    function calculateTotal() {
        let subtotal = cart.reduce((sum, item) => sum + item.finalPrice, 0);
        return subtotal;
    }

    function showPaymentError(message) {
        const errorDiv = document.createElement('div');
        errorDiv.className = 'error-message';
        errorDiv.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            background-color: #f44336;
            color: white;
            padding: 12px 24px;
            border-radius: 6px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.15);
            z-index: 1000;
            animation: slideIn 0.3s ease-out;
        `;
        errorDiv.textContent = message;
        
        document.body.appendChild(errorDiv);
        
        setTimeout(() => {
            errorDiv.style.animation = 'fadeOut 0.3s ease-out forwards';
            setTimeout(() => errorDiv.remove(), 300);
        }, 3000);
    }

    function processPayment() {
        // Check if user is logged in
        <?php if (!isset($_SESSION['user_id'])): ?>
            // Show login prompt
            showLoginPrompt();
        <?php else: ?>
            // Proceed with payment for logged-in users
            placeBooking();
        <?php endif; ?>
    }

    function showLoginPrompt() {
        // Create modal for login/signup prompt
        const modalHTML = `
            <div class="login-prompt-modal">
                <div class="modal-content">
                    <h3>Login Required</h3>
                    <p>Please login or create an account to complete your booking.</p>
                    <div class="button-group">
                        <button onclick="window.location.href='login.php?redirect=services.php'">Login</button>
                        <button onclick="window.location.href='register.php?redirect=services.php'">Create Account</button>
                    </div>
                </div>
            </div>
        `;
        
        // Add modal to body
        const modalElement = document.createElement('div');
        modalElement.innerHTML = modalHTML;
        document.body.appendChild(modalElement.firstChild);
        
        // Add event listener to close when clicking outside
        document.querySelector('.login-prompt-modal').addEventListener('click', function(e) {
            if (e.target === this) {
                this.remove();
            }
        });
    }

    function placeBooking() {
        const formData = new FormData(document.getElementById('checkoutForm'));
        formData.append('action', 'place_booking');

        fetch(window.location.href, {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showSuccessMessage(data.message);
                // Clear cart and redirect to bookings page
                window.location.href = 'bookings.php';
            } else {
                showPaymentError(data.message || 'Error placing booking');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showPaymentError('Error placing booking');
        });
    }

    function removeFromCart(subServiceId) {
        fetch(window.location.href, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: new URLSearchParams({
                action: 'remove_from_cart',
                sub_service_id: subServiceId
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showSuccessMessage('Item removed from cart');
                updateCartCount();
                showCart();
            } else {
                showPaymentError(data.message || 'Error removing item from cart');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showPaymentError('Error removing item from cart');
        });
    }

    // Visit booking functions
    function showVisitModal() {
        // Show visit booking modal directly without any login check
        document.getElementById('visitModal').style.display = 'flex';
    }
    
    function closeVisitModal() {
        document.getElementById('visitModal').style.display = 'none';
    }
    
    // Remove or comment out the showLoginPrompt function
    /*
    function showLoginPrompt() {
        document.getElementById('loginPromptModal').style.display = 'flex';
    }
    */
    
    function bookVisit() {
        const formData = new FormData(document.getElementById('visitForm'));
        
        fetch(window.location.href, {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Close visit modal
                closeVisitModal();
                
                // Show success modal
                document.getElementById('visitReference').textContent = data.visit_reference;
                document.getElementById('visitSuccessModal').style.display = 'flex';
                
                // Add animation class
                setTimeout(() => {
                    document.getElementById('visitSuccessModal').classList.add('show');
                }, 10);
            } else {
                showPaymentError(data.message || 'Error booking visit');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showPaymentError('Error booking visit');
        });
    }

    // Emergency booking functions
    function showEmergencyModal() {
        const modal = document.getElementById('emergencyModal');
        modal.style.display = 'flex';
        
        // Prevent body scrolling when modal is open
        document.body.style.overflow = 'hidden';
        
        // Reset form if needed
        document.getElementById('emergencyForm').reset();
    }
    
    function closeEmergencyModal() {
        document.getElementById('emergencyModal').style.display = 'none';
        
        // Re-enable body scrolling
        document.body.style.overflow = '';
    }
    
    // Close modal if user clicks outside the modal content
    document.getElementById('emergencyModal').addEventListener('click', function(event) {
        if (event.target === this) {
            closeEmergencyModal();
        }
    });
    
    function bookEmergency() {
        const formData = new FormData(document.getElementById('emergencyForm'));
        
        fetch(window.location.href, {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Close emergency modal
                closeEmergencyModal();
                
                // Show success modal
                document.getElementById('emergencyReference').textContent = data.emergency_reference;
                document.getElementById('emergencySuccessModal').style.display = 'flex';
                
                // Add animation class
                setTimeout(() => {
                    document.getElementById('emergencySuccessModal').classList.add('show');
                }, 10);
            } else {
                showPaymentError(data.message || 'Error requesting emergency service');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showPaymentError('Error requesting emergency service');
        });
    }
    </script>
</body>
</html>