<?php
session_start();

// Initialize cart if it doesn't exist
if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

// Get POST data
$data = json_decode(file_get_contents('php://input'), true);

if ($data['action'] === 'add') {
    $sub_service_id = $data['sub_service_id'];
    $_SESSION['cart'][$sub_service_id] = [
        'sub_service_id' => $sub_service_id,
        'name' => $data['name'],
        'price' => $data['price']
    ];
} elseif ($data['action'] === 'remove') {
    unset($_SESSION['cart'][$data['sub_service_id']]);
}

// Calculate totals
$subtotal = 0;
foreach ($_SESSION['cart'] as $item) {
    $subtotal += $item['price'];
}
$service_fee = $subtotal > 0 ? 99 : 0;
$total = $subtotal + $service_fee;

// Return updated cart and totals
header('Content-Type: application/json');
echo json_encode([
    'cart' => $_SESSION['cart'],
    'totals' => [
        'subtotal' => $subtotal,
        'service_fee' => $service_fee,
        'total' => $total
    ]
]); 