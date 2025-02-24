<?php
require_once 'dbconnect.php';

$service_id = isset($_GET['service_id']) ? (int)$_GET['service_id'] : 0;

// Fetch service details
$query = "
    SELECT 
        s.*,
        sp.business_name
    FROM tbl_services s
    JOIN service_providers sp ON s.provider_id = sp.provider_id
    WHERE s.service_id = ?";

$stmt = $conn->prepare($query);
$stmt->bind_param("i", $service_id);
$stmt->execute();
$service = $stmt->get_result()->fetch_assoc();

// Fetch sub-services
$sub_query = "
    SELECT * 
    FROM tbl_sub_services 
    WHERE service_id = ?
    ORDER BY sub_service_name";

$stmt = $conn->prepare($sub_query);
$stmt->bind_param("i", $service_id);
$stmt->execute();
$sub_services = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Combine data
$service['sub_services'] = $sub_services;

// Return JSON response
header('Content-Type: application/json');
echo json_encode($service); 