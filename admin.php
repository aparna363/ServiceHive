<?php
session_start();
// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

// Database connection
require_once 'dbconnect.php';

// Include PHPMailer classes directly
 require 'PHPMailer-master/src/Exception.php';
    require 'PHPMailer-master/src/PHPMailer.php';
    require 'PHPMailer-master/src/SMTP.php';
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Get counts for dashboard stats
$userCount = $conn->query("SELECT COUNT(*) as count FROM users WHERE role != 'admin'")->fetch_assoc()['count'];
$providerCount = $conn->query("SELECT COUNT(*) as count FROM users WHERE role = 'service_provider'")->fetch_assoc()['count'];
$bookingCount = $conn->query("SELECT COUNT(*) as count FROM bookings")->fetch_assoc()['count'];

// Get pending verification requests
$stmt = $conn->prepare("
    SELECT sp.provider_id, sp.user_id, sp.status, sp.created_at, u.username, u.email
    FROM service_providers sp
    JOIN users u ON sp.user_id = u.id
    WHERE sp.status = 'pending'
    ORDER BY sp.created_at DESC
");
$stmt->execute();
$pending_verifications = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Email configuration settings
// These should be placed at the top of your file after database connection
// In a production environment, consider using environment variables instead
$EMAIL_HOST = 'smtp.gmail.com';
$EMAIL_PORT = 587;
$EMAIL_USERNAME = 'aparnaprasad363@gmail.com'; 
$EMAIL_PASSWORD = 'wbnh wldc yeqo sqzi';  // Consider using app password for Gmail
$EMAIL_FROM = 'aparnaprasad363@gmail.com';
$EMAIL_FROM_NAME = 'ServiceHive';

// Functions for database operations
function getServiceProviders($db) {
    $query = "SELECT u.id, u.username, u.email, u.status, 
              sp.verified_status, sp.rating, sp.total_reviews, sp.business_name, 
              CASE
                WHEN u.status = 'approved' THEN 'completed'
                WHEN v.documents_uploaded IS NOT NULL THEN v.documents_uploaded
                ELSE 'pending'
              END as verification_status
              FROM users u 
              LEFT JOIN service_providers sp ON u.id = sp.user_id 
              LEFT JOIN verification_status v ON u.id = v.provider_id
              WHERE u.role = 'service_provider'";
    return $db->query($query);
}

function getUsers($db) {
    $query = "SELECT id, username, email, role, status, is_active, mobile, city, state 
              FROM users 
              WHERE role != 'admin'";
    return $db->query($query);
}

function getBookings($db) {
    $query = "SELECT b.booking_id, b.booking_date, b.booking_time, b.status, 
              b.priority, b.notes, b.payment_status,
              u.username as client_name, 
              sp.business_name as provider_name,
              s.service_name,
              p.amount as total_price
              FROM bookings b 
              JOIN users u ON b.user_id = u.id 
              JOIN service_providers sp ON b.provider_id = sp.provider_id
              JOIN tbl_services s ON b.service_id = s.service_id
              LEFT JOIN payments p ON b.booking_id = p.booking_id
              ORDER BY b.booking_date DESC, b.booking_time DESC";
    return $db->query($query);
}

// Update the getServices function to use the correct table structure
function getServices($db) {
    $query = "SELECT s.service_id, s.service_name, s.description, c.category_name
              FROM tbl_services s
              LEFT JOIN tbl_categories c ON s.category_id = c.category_id
              ORDER BY c.category_name, s.service_name";
    return $db->query($query);
}

// Update the generateReport function to include services
function generateReport($db, $type, $format = 'csv') {
    switch ($type) {
        case 'users':
            $data = getUsers($db);
            $headers = ['ID', 'Username', 'Email', 'Role', 'Status', 'Active', 'Mobile', 'City', 'State'];
            $filename = 'users_report_' . date('Y-m-d') . '.' . $format;
            break;
        case 'providers':
            $data = getServiceProviders($db);
            $headers = ['ID', 'Username', 'Email', 'Status', 'Verification', 'Rating', 'Reviews', 'Business Name'];
            $filename = 'providers_report_' . date('Y-m-d') . '.' . $format;
            break;
        case 'bookings':
            $data = getBookings($db);
            $headers = ['ID', 'Client', 'Provider', 'Service', 'Date', 'Time', 'Status', 'Payment Status', 'Price'];
            $filename = 'bookings_report_' . date('Y-m-d') . '.' . $format;
            break;
        case 'services':
            $data = getServices($db);
            $headers = ['ID', 'Service Name', 'Category', 'Description'];
            $filename = 'services_report_' . date('Y-m-d') . '.' . $format;
            break;
        default:
            return false;
    }
    
    if ($format === 'csv') {
        return generateCSVReport($data, $headers, $filename);
    } elseif ($format === 'pdf') {
        // PDF generation would be added here
        return false;
    }
    
    return false;
}

function generateCSVReport($data, $headers, $filename) {
    if (!$data) return false;
    
    $output = fopen('php://temp', 'w');
    
    // Add headers
    fputcsv($output, $headers);
    
    // Add data rows
    while ($row = $data->fetch_assoc()) {
        fputcsv($output, $row);
    }
    
    // Reset pointer
    rewind($output);
    
    // Get content
    $content = stream_get_contents($output);
    fclose($output);
    
    return [
        'filename' => $filename,
        'content' => $content,
        'type' => 'text/csv'
    ];
}

// Handle report downloads
if (isset($_GET['report'])) {
    $reportType = $_GET['report'];
    $format = isset($_GET['format']) ? $_GET['format'] : 'csv';
    
    $report = generateReport($conn, $reportType, $format);
    
    if ($report) {
        header('Content-Type: ' . $report['type']);
        header('Content-Disposition: attachment; filename="' . $report['filename'] . '"');
        echo $report['content'];
        exit;
    } else {
        $_SESSION['error'] = "Failed to generate report";
        header("Location: " . $_SERVER['PHP_SELF']);
        exit;
    }
}

// Create a helper function for sending emails
function sendServiceHiveEmail($recipientEmail, $recipientName, $subject, $message) {
    global $conn, $EMAIL_HOST, $EMAIL_PORT, $EMAIL_USERNAME, $EMAIL_PASSWORD, $EMAIL_FROM, $EMAIL_FROM_NAME;
    
    // Use PHPMailer
    $mail = new PHPMailer(true);

    try {
        $mail->isSMTP();
        $mail->Host       = $EMAIL_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = $EMAIL_USERNAME;
        $mail->Password   = $EMAIL_PASSWORD;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = $EMAIL_PORT;

        $mail->setFrom($EMAIL_FROM, $EMAIL_FROM_NAME);
        $mail->addAddress($recipientEmail, $recipientName);
        $mail->Subject = $subject;
        $mail->Body    = $message;

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Email sending failed: " . $mail->ErrorInfo);
        
        // Store the email in a database table for later sending
        $emailMessage = $conn->prepare("INSERT INTO email_queue (recipient, subject, message, created_at) 
                                       VALUES (?, ?, ?, NOW())");
        $emailMessage->bind_param("sss", $recipientEmail, $subject, $message);
        $emailMessage->execute();
        return false;
    }
}

function approveServiceProvider($db, $id) {
    $db->begin_transaction();
    try {
        // Get provider email and username
        $query0 = "SELECT email, username FROM users WHERE id = ? AND role = 'service_provider'";
        $stmt0 = $db->prepare($query0);
        $stmt0->bind_param("i", $id);
        $stmt0->execute();
        $result = $stmt0->get_result();
        $user = $result->fetch_assoc();
        
        // Update user status in the users table
        $query1 = "UPDATE users SET status = 'approved' WHERE id = ? AND role = 'service_provider'";
        $stmt1 = $db->prepare($query1);
        $stmt1->bind_param("i", $id);
        $result1 = $stmt1->execute();
        
        // Update service_provider verified_status and status
        $query2 = "UPDATE service_providers SET verified_status = TRUE, status = 'approved' WHERE user_id = ?";
        $stmt2 = $db->prepare($query2);
        $stmt2->bind_param("i", $id);
        $result2 = $stmt2->execute();
        
        // Update verification_status
        $query3 = "UPDATE verification_status SET documents_uploaded = 'completed' WHERE provider_id = ?";
        $stmt3 = $db->prepare($query3);
        $stmt3->bind_param("i", $id);
        $result3 = $stmt3->execute();
        
        // Create notification
        $query4 = "INSERT INTO notifications (user_id, title, message, type) 
                  VALUES (?, 'Account Approved', 'Your service provider account has been approved. You can now start offering services.', 'system')";
        $stmt4 = $db->prepare($query4);
        $stmt4->bind_param("i", $id);
        $stmt4->execute();
        
        // Send email notification using PHPMailer
        if (!empty($user['email'])) {
            $subject = "Your ServiceHive Account Has Been Approved";
            $message = "Dear " . htmlspecialchars($user['username']) . ",\n\n";
            $message .= "Congratulations! Your ServiceHive account has been approved by our administration team.\n\n";
            $message .= "You can now log in to your account and start offering your services to customers.\n\n";
            $message .= "Thank you for joining ServiceHive!\n\n";
            $message .= "Best regards,\nThe ServiceHive Team";
            
            // Use the helper function to send email
            sendServiceHiveEmail($user['email'], $user['username'], $subject, $message);
        }
        
        $db->commit();
        
        // Log the approval for debugging
        error_log("Service provider ID $id approved. Users update: " . ($result1 ? "success" : "failed") . 
                 ", service_providers update: " . ($result2 ? "success" : "failed") . 
                 ", verification_status update: " . ($result3 ? "success" : "failed"));
        
        return true;
    } catch (Exception $e) {
        $db->rollback();
        error_log("Error in approveServiceProvider: " . $e->getMessage());
        return false;
    }
}

function rejectServiceProvider($db, $id) {
    $db->begin_transaction();
    try {
        // Get provider email and username
        $query0 = "SELECT email, username FROM users WHERE id = ? AND role = 'service_provider'";
        $stmt0 = $db->prepare($query0);
        $stmt0->bind_param("i", $id);
        $stmt0->execute();
        $result = $stmt0->get_result();
        $user = $result->fetch_assoc();
        
        // Update user status in the users table
        $query1 = "UPDATE users SET status = 'rejected' WHERE id = ? AND role = 'service_provider'";
        $stmt1 = $db->prepare($query1);
        $stmt1->bind_param("i", $id);
        $result1 = $stmt1->execute();
        
        // Update service_provider verified_status and status
        $query2 = "UPDATE service_providers SET verified_status = FALSE, status = 'rejected' WHERE user_id = ?";
        $stmt2 = $db->prepare($query2);
        $stmt2->bind_param("i", $id);
        $result2 = $stmt2->execute();
        
        // Update verification status if it exists
        $query3 = "UPDATE verification_status SET documents_uploaded = 'rejected' WHERE provider_id = ?";
        $stmt3 = $db->prepare($query3);
        $stmt3->bind_param("i", $id);
        $result3 = $stmt3->execute();

        // Create notification
        $query4 = "INSERT INTO notifications (user_id, title, message, type) 
                  VALUES (?, 'Account Rejected', 'Your service provider application has been rejected. Please contact support for more information.', 'system')";
        $stmt4 = $db->prepare($query4);
        $stmt4->bind_param("i", $id);
        $stmt4->execute();

        // Send email notification using PHPMailer
        if (!empty($user['email'])) {
            $subject = "Your ServiceHive Application Status";
            $message = "Dear " . htmlspecialchars($user['username']) . ",\n\n";
            $message .= "We regret to inform you that your ServiceHive service provider application has been rejected.\n\n";
            $message .= "If you believe this is an error or would like to understand the reason, please contact our support team at support@servicehive.com.\n\n";
            $message .= "You may reapply after addressing any issues with your application.\n\n";
            $message .= "Best regards,\nThe ServiceHive Team";
            
            // Use the helper function to send email
            sendServiceHiveEmail($user['email'], $user['username'], $subject, $message);
        }

        $db->commit();
        
        // Log the rejection for debugging
        error_log("Service provider ID $id rejected. Users update: " . ($result1 ? "success" : "failed") . 
                 ", service_providers update: " . ($result2 ? "success" : "failed") . 
                 ", verification_status update: " . ($result3 ? "success" : "failed"));
        
        return true;
    } catch (Exception $e) {
        $db->rollback();
        error_log("Error in rejectServiceProvider: " . $e->getMessage());
        return false;
    }
}

function deleteUser($db, $id) {
    $query = "DELETE FROM users WHERE id = ? AND role != 'admin'";
    $stmt = $db->prepare($query);
    $stmt->bind_param("i", $id);
    return $stmt->execute();
}

// Handle POST requests
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['approve_provider'])) {
        if (approveServiceProvider($conn, $_POST['provider_id'])) {
            $_SESSION['message'] = "Service provider approved successfully";
        } else {
            $_SESSION['error'] = "Failed to approve service provider";
        }
        // Force a redirect to refresh the page and show the updated status
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    } elseif (isset($_POST['reject_provider'])) {
        if (rejectServiceProvider($conn, $_POST['provider_id'])) {
            $_SESSION['message'] = "Service provider rejected successfully";
        } else {
            $_SESSION['error'] = "Failed to reject service provider";
        }
        // Force a redirect to refresh the page and show the updated status
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    } elseif (isset($_POST['delete_user'])) {
        if (deleteUser($conn, $_POST['user_id'])) {
            $_SESSION['message'] = "User deleted successfully";
        } else {
            $_SESSION['error'] = "Failed to delete user";
        }
    } elseif (isset($_POST['toggle_status'])) {
        $userId = $_POST['user_id'];
        $is_active = $_POST['action'] === 'activate' ? 1 : 0;
        
        $query = "UPDATE users SET is_active = ? WHERE id = ? AND role != 'admin'";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("ii", $is_active, $userId);
        
        if ($stmt->execute()) {
            $_SESSION['message'] = "User status updated successfully";
        } else {
            $_SESSION['error'] = "Failed to update user status";
        }
    }
    
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

// Fetch data
$serviceProviders = getServiceProviders($conn);
$users = getUsers($conn);
$bookings = getBookings($conn);

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - ServiceHive</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Arial', sans-serif;
        }

        body {
            background-color: #f4f6f9;
            min-height: 100vh;
        }

        .container {
            display: flex;
            min-height: 100vh;
        }

        /* Top Header Styles */
        .top-header {
            position: fixed;
            top: 0;
            right: 0;
            width: calc(100% - 250px);
            height: 70px;
            background-color: white;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            display: flex;
            justify-content: flex-end;
            align-items: center;
            padding: 0 30px;
            z-index: 100;
        }

        .profile-container {
            display: flex;
            align-items: center;
            gap: 15px;
            cursor: pointer;
            padding: 8px 15px;
            border-radius: 25px;
            transition: background-color 0.3s;
        }

        .profile-container:hover {
            background-color: #f5f5f5;
        }

        .profile-info {
            text-align: right;
        }

        .profile-name {
            font-weight: 600;
            color: #333;
            font-size: 14px;
        }

        .profile-role {
            color: #666;
            font-size: 12px;
        }

        .profile-image {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid rgb(104, 35, 3);
        }

        /* Sidebar Styles */
        .sidebar {
            width: 250px;
            background-color: rgb(104, 35, 3);
            color: white;
            position: fixed;
            height: 100vh;
            left: 0;
            top: 0;
        }

        .logo-container {
            padding: 20px;
            text-align: center;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }

        .company-logo {
            width: 200px;
            height: auto;
            margin-bottom: 10px;
        }

        .company-name {
            font-size: 20px;
            font-weight: bold;
            color: white;
            margin-bottom: 5px;
        }

        .sidebar-menu {
            padding: 20px 0;
            height: calc(100vh - 200px);
            display: flex;
            flex-direction: column;
        }

        .menu-item {
            padding: 15px 20px;
            display: flex;
            align-items: center;
            color: white;
            text-decoration: none;
            transition: 0.3s;
        }

        .menu-item:hover {
            background-color: rgba(255,255,255,0.1);
        }

        .menu-item i {
            margin-right: 12px;
            width: 20px;
            text-align: center;
        }

        .menu-item.active {
            background-color: rgba(255,255,255,0.1);
            border-left: 4px solid #4CAF50;
        }

        .logout-btn {
            margin-top: 270px;
            background-color: rgb(133, 36, 3);
        }

        /* Main Content Styles */
        .main-content {
            flex: 1;
            margin-left: 250px;
            padding: 100px 30px 30px 30px;
        }

        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 40px;
            margin: 40px 0;
            padding: 0 20px;
        }

        .stat-card {
            background: white;
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            position: relative;
            overflow: hidden;
            transition: transform 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-5px);
        }

        .stat-value {
            font-size: 32px;
            font-weight: bold;
            color: #333;
            margin-bottom: 12px;
            position: relative;
            z-index: 1;
        }

        .stat-label {
            color: #666;
            font-size: 16px;
            position: relative;
            z-index: 1;
            font-weight: 500;
        }

        .stat-icon {
            position: absolute;
            right: 25px;
            top: 50%;
            transform: translateY(-50%);
            font-size: 54px;
            color: rgba(104, 35, 3, 0.1);
        }

        /* Table Sections */
        .section {
            background: white;
            padding: 25px;
            border-radius: 10px;
            margin-bottom: 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
        }

        .section h2 {
            color: #333;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid rgb(5, 7, 37);
            display: flex;
            align-items: center;
        }

        .section h2 i {
            margin-right: 10px;
            color: rgb(10, 14, 50);
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        table th, table td {
            padding: 15px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }

        table th {
            background-color: #f8f9fa;
            color: #333;
            font-weight: 600;
        }

        table tr:hover {
            background-color: #f8f9fa;
        }

        .btn {
            padding: 8px 16px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
            transition: 0.3s;
            margin-right: 5px;
        }

        .btn:hover {
            opacity: 0.9;
        }

        .btn-approve {
            background-color: #4CAF50;
            color: white;
        }

        .btn-reject {
            background-color: #f44336;
            color: white;
        }

        .btn-delete {
            background-color: #ff9800;
            color: white;
        }

        .status-badge {
            padding: 5px 10px;
            border-radius: 15px;
            font-size: 12px;
            font-weight: 500;
        }

        .status-pending {
            background-color: #ffeeba;
            color: #856404;
        }

        .status-approved {
            background-color: #d4edda;
            color: #155724;
        }

        .status-rejected {
            background-color: #f8d7da;
            color: #721c24;
        }

        .btn-activate {
            background-color: #4CAF50;
            color: white;
        }

        .btn-deactivate {
            background-color: #f44336;
            color: white;
        }

        .status-active {
            background-color: #d4edda;
            color: #155724;
        }

        .status-inactive {
            background-color: #f8d7da;
            color: #721c24;
        }

        /* Modal styles */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0,0,0,0.7);
        }
        
        .modal-content {
            background-color: #fefefe;
            margin: 5% auto;
            padding: 20px;
            border-radius: 10px;
            width: 80%;
            max-width: 900px;
            max-height: 80vh;
            overflow-y: auto;
        }
        
        .close {
            color: #aaa;
            float: right;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
        }
        
        .close:hover {
            color: black;
        }
        
        .verification-documents {
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
            margin-top: 20px;
        }
        
        .document-preview {
            flex: 1;
            min-width: 250px;
            border: 1px solid #ddd;
            padding: 15px;
            border-radius: 5px;
        }
        
        .document-preview h3 {
            margin-bottom: 10px;
            color: #333;
        }
        
        .document-preview img {
            width: 100%;
            height: auto;
            max-height: 300px;
            object-fit: contain;
            margin-bottom: 10px;
            border: 1px solid #eee;
        }
        
        .btn-view {
            background-color: #2196F3;
            color: white;
            margin-left: 10px;
        }

        /* Verification requests styles */
        .verification-requests {
            margin-top: 30px;
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            padding: 20px;
        }
        
        .verification-requests h2 {
            color: #333;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
        }
        
        .verification-requests h2 i {
            margin-right: 10px;
            color: #007bff;
        }
        
        .verification-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        
        .verification-table th, 
        .verification-table td {
            padding: 15px;
            text-align: left;
            border-bottom: 1px solid #e0e0e0;
        }
        
        .verification-table th {
            background-color: #f8f9fa;
            font-weight: 600;
            color: #495057;
        }
        
        .verification-table tr:hover {
            background-color: #f8f9fa;
        }
        
        .verification-table .badge {
            padding: 6px 12px;
            border-radius: 50px;
            font-size: 12px;
            font-weight: 600;
        }
        
        .verification-table .badge-pending {
            background-color: #fff3cd;
            color: #856404;
        }
        
        .verification-table .btn {
            padding: 6px 12px;
            border-radius: 4px;
            font-size: 14px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            margin-right: 5px;
            border: none;
        }
        
        .verification-table .btn-view {
            background-color: #007bff;
            color: white;
        }
        
        .verification-table .btn-view:hover {
            background-color: #0069d9;
        }
        
        .empty-state {
            text-align: center;
            padding: 30px;
            color: #6c757d;
        }
        
        /* Verification modal styles */
        .verification-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            overflow-y: auto;
        }
        
        .verification-modal-content {
            background-color: white;
            margin: 50px auto;
            padding: 30px;
            width: 80%;
            max-width: 900px;
            border-radius: 8px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.3);
            position: relative;
        }
        
        .verification-modal-close {
            position: absolute;
            top: 15px;
            right: 20px;
            font-size: 24px;
            cursor: pointer;
            color: #aaa;
        }
        
        .verification-modal-close:hover {
            color: #333;
        }
        
        .verification-details {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .verification-detail-item {
            margin-bottom: 15px;
        }
        
        .verification-detail-label {
            font-weight: 600;
            color: #495057;
            margin-bottom: 5px;
            display: block;
        }
        
        .verification-detail-value {
            color: #212529;
        }
        
        .verification-documents {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .verification-document {
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            overflow: hidden;
        }
        
        .verification-document-label {
            background-color: #f8f9fa;
            padding: 10px;
            font-weight: 600;
            color: #495057;
            border-bottom: 1px solid #e0e0e0;
        }
        
        .verification-document-image {
            padding: 10px;
            text-align: center;
        }
        
        .verification-document-image img {
            max-width: 100%;
            max-height: 300px;
            object-fit: contain;
        }
        
        .verification-actions {
            display: flex;
            justify-content: flex-end;
            gap: 15px;
            margin-top: 20px;
        }
        
        .verification-actions .btn {
            padding: 10px 20px;
            border-radius: 4px;
            font-size: 16px;
            cursor: pointer;
            border: none;
        }
        
        .verification-actions .btn-approve {
            background-color: #28a745;
            color: white;
        }
        
        .verification-actions .btn-reject {
            background-color: #dc3545;
            color: white;
        }
        
        .verification-actions .btn-approve:hover {
            background-color: #218838;
        }
        
        .verification-actions .btn-reject:hover {
            background-color: #c82333;
        }
        
        .loading {
            text-align: center;
            padding: 20px;
            color: #6c757d;
        }
        
        .error {
            color: #dc3545;
            padding: 15px;
            background-color: #f8d7da;
            border-radius: 4px;
            margin-bottom: 20px;
        }

        .btn-report {
            background-color: #007bff;
            color: white;
            padding: 6px 12px;
            border-radius: 4px;
            font-size: 14px;
            float: right;
            text-decoration: none;
            margin-right: 10px;
        }
        
        .btn-report:hover {
            background-color: #0056b3;
            color: white;
        }
        
        .section h2 {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Sidebar -->
        <div class="sidebar">
            <!-- Logo Section -->
            <div class="logo-container">
                <img src="images/logo2.png" alt="ServiceHive Logo" class="company-logo">
            </div>

            <div class="sidebar-menu">
                <a href="#dashboard" class="menu-item active">
                    <i class="fas fa-home"></i>
                    <span>Dashboard</span>
                </a>
                <a href="index.php" class="menu-item">
                    <i class="fas fa-home"></i>
                    <span>Home</span>
                </a>
                <a href="#providers" class="menu-item">
                    <i class="fas fa-user-tie"></i>
                    <span>Service Providers</span>
                </a>
                <a href="category-management.php" class="menu-item">
                    <i class="fas fa-cogs"></i>
                    <span>Service Management</span>
                </a>
                <a href="#bookings" class="menu-item">
                    <i class="fas fa-calendar-check"></i>
                    <span>Bookings</span>
                </a>
                <a href="#users" class="menu-item">
                    <i class="fas fa-users"></i>
                    <span>Users</span>
                </a>
               
                
                <a href="logout.php" class="menu-item logout-btn">
                    <i class="fas fa-sign-out-alt"></i>
                    <span>Logout</span>
                </a>
            </div>
        </div>

        <!-- Top Header -->
        <div class="top-header">
            <div class="profile-container">
                <div class="profile-info">
                    <div class="profile-name"><?php echo htmlspecialchars($_SESSION['username']); ?></div>
                    <div class="profile-role">Administrator</div>
                </div>
                <img src="./images/admin.png" alt="Profile" class="profile-image">
            </div>
        </div>

        <!-- Main Content -->
        <div class="main-content">
            <!-- Stats Grid -->
            <div class="stats-grid" id="dashboard">
                <div class="stat-card">
                    <div class="stat-value"><?php echo $userCount; ?></div>
                    <div class="stat-label">Total Users</div>
                    <i class="fas fa-users stat-icon"></i>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?php echo $providerCount; ?></div>
                    <div class="stat-label">Service Providers</div>
                    <i class="fas fa-user-tie stat-icon"></i>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?php echo $bookingCount; ?></div>
                    <div class="stat-label">Total Bookings</div>
                    <i class="fas fa-calendar-check stat-icon"></i>
                </div>
            </div>

            <!-- Verification requests section -->
            <div class="verification-requests">
                <h2><i class="fas fa-user-check"></i> Pending Verification Requests</h2>
                
                <?php if (count($pending_verifications) > 0): ?>
                    <table class="verification-table">
                        <thead>
                            <tr>
                                <th>Provider</th>
                                <th>Email</th>
                                <th>Submitted On</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($pending_verifications as $verification): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($verification['username']); ?></td>
                                    <td><?php echo htmlspecialchars($verification['email']); ?></td>
                                    <td><?php echo date('M d, Y', strtotime($verification['created_at'])); ?></td>
                                    <td><span class="badge badge-pending">Pending</span></td>
                                    <td>
                                        <button class="btn btn-view" onclick="viewVerification(<?php echo $verification['provider_id']; ?>)">
                                            View Details
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div class="empty-state">
                        <p>No pending verification requests at this time.</p>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Service Providers Section -->
            <div class="section" id="providers">
                <h2>
                    <i class="fas fa-user-tie"></i> Service Providers
                    <a href="?report=providers" class="btn btn-report">
                        <i class="fas fa-download"></i> Download Report
                    </a>
                </h2>
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Status</th>
                            <th>Verification</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($serviceProviders && $serviceProviders->num_rows > 0): ?>
                            <?php while ($provider = $serviceProviders->fetch_assoc()): ?>
                                <tr id="provider-row-<?php echo $provider['id']; ?>">
                                    <td><?php echo htmlspecialchars($provider['id']); ?></td>
                                    <td><?php echo htmlspecialchars($provider['username']); ?></td>
                                    <td><?php echo htmlspecialchars($provider['email']); ?></td>
                                    <td class="provider-status">
                                        <span class="status-badge status-<?php echo strtolower($provider['status'] ?? 'pending'); ?>">
                                            <?php echo ucfirst($provider['status'] ?? 'pending'); ?>
                                        </span>
                                    </td>
                                    <td class="verification-status">
                                        <span class="status-badge status-<?php echo $provider['verification_status'] === 'completed' || $provider['status'] === 'approved' ? 'approved' : 'pending'; ?>">
                                            <?php echo $provider['verification_status'] === 'completed' || $provider['status'] === 'approved' ? 'Completed' : 'Pending'; ?>
                                        </span>
                                        <?php if ($provider['verification_status'] === 'completed' || $provider['status'] === 'approved'): ?>
                                            <button class="btn btn-view" onclick="viewVerificationDetails(<?php echo $provider['id']; ?>)">
                                                <i class="fas fa-eye"></i> View
                                            </button>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <button type="button" class="btn btn-approve" onclick="updateProviderStatus(<?php echo $provider['id']; ?>, 'approved')">Approve</button>
                                        <button type="button" class="btn btn-reject" onclick="updateProviderStatus(<?php echo $provider['id']; ?>, 'rejected')">Reject</button>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr><td colspan="6">No service providers found.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Bookings Section -->
            <div class="section" id="bookings">
                <h2>
                    <i class="fas fa-calendar-check"></i> Bookings
                    <a href="?report=bookings" class="btn btn-report">
                        <i class="fas fa-download"></i> Download Report
                    </a>
                </h2>
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Client</th>
                            <th>Service</th>
                            <th>Date</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($bookings && $bookings->num_rows > 0): ?>
                            <?php while ($booking = $bookings->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($booking['booking_id']); ?></td>
                                <td><?php echo htmlspecialchars($booking['client_name']); ?></td>
                                <td><?php echo htmlspecialchars($booking['service_name']); ?></td>
                                <td><?php echo htmlspecialchars($booking['booking_date']); ?></td>
                                <td>
                                    <span class="status-badge status-<?php echo strtolower($booking['status']); ?>">
                                        <?php echo ucfirst($booking['status']); ?>
                                    </span>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr><td colspan="5">No bookings found.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Users Section -->
            <div class="section" id="users">
                <h2>
                    <i class="fas fa-users"></i> Users
                    <a href="?report=users" class="btn btn-report">
                        <i class="fas fa-download"></i> Download Report
                    </a>
                </h2>
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Role</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($users && $users->num_rows > 0): ?>
                            <?php while ($user = $users->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($user['id']); ?></td>
                                    <td><?php echo htmlspecialchars($user['username']); ?></td>
                                    <td><?php echo htmlspecialchars($user['email']); ?></td>
                                    <td>
                                        <span class="status-badge status-<?php echo $user['role'] === 'service_provider' ? 'approved' : 'pending'; ?>">
                                            <?php echo ucfirst(str_replace('_', ' ', $user['role'])); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="status-badge <?php echo $user['is_active'] ? 'status-approved' : 'status-rejected'; ?>">
                                            <?php echo $user['is_active'] ? 'Active' : 'Inactive'; ?>
                                        </span>
                                    </td>
                                    <td>
                                    <form method="POST" style="display:inline;">
    <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
    <input type="hidden" name="action" value="<?php echo $user['is_active'] ? 'deactivate' : 'activate'; ?>">
    <button type="submit" name="toggle_status" class="btn <?php echo $user['is_active'] ? 'btn-reject' : 'btn-approve'; ?>">
        <i class="fas <?php echo $user['is_active'] ? 'fa-user-slash' : 'fa-user-check'; ?>"></i>
        <?php echo $user['is_active'] ? 'Deactivate' : 'Activate'; ?>
    </button>
    <button type="submit" name="delete_user" class="btn btn-delete" 
            onclick="return confirm('Are you sure you want to delete this user?')">
        <i class="fas fa-trash"></i> Delete
    </button>
</form>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr><td colspan="6">No users found.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
    <!-- Verification details modal -->
    <div id="verificationModal" class="verification-modal">
        <div class="verification-modal-content">
            <span class="verification-modal-close" onclick="closeVerificationModal()">&times;</span>
            <h2>Verification Details</h2>
            <div id="verificationDetails">
                <!-- Details will be loaded here via AJAX -->
                <div class="loading">Loading...</div>
            </div>
        </div>
    </div>
    
    <script>
        // Add active class to current menu item
        document.addEventListener('DOMContentLoaded', function() {
            const menuItems = document.querySelectorAll('.menu-item');
            const sections = document.querySelectorAll('.section');
            
            // Handle menu item clicks
            menuItems.forEach(item => {
                item.addEventListener('click', function(e) {
                    if (this.getAttribute('href').startsWith('#')) {
                        e.preventDefault();
                        
                        // Remove active class from all items
                        menuItems.forEach(i => i.classList.remove('active'));
                        
                        // Add active class to clicked item
                        this.classList.add('active');
                        
                        // Scroll to section
                        const targetId = this.getAttribute('href').substring(1);
                        const targetSection = document.getElementById(targetId);
                        if (targetSection) {
                            targetSection.scrollIntoView({ behavior: 'smooth' });
                        }
                    }
                });
            });

            // Handle scroll to update active menu item
            window.addEventListener('scroll', function() {
                let current = '';
                
                sections.forEach(section => {
                    const sectionTop = section.offsetTop;
                    const sectionHeight = section.clientHeight;
                    if (pageYOffset >= sectionTop - 200) {
                        current = section.getAttribute('id');
                    }
                });

                menuItems.forEach(item => {
                    item.classList.remove('active');
                    if (item.getAttribute('href') === `#${current}`) {
                        item.classList.add('active');
                    }
                });
            });
        });

        // Confirm delete actions
        document.querySelectorAll('.btn-delete').forEach(button => {
            button.addEventListener('click', function(e) {
                if (!confirm('Are you sure you want to delete this item?')) {
                    e.preventDefault();
                }
            });
        });

        // Function to view verification details
        function viewVerificationDetails(providerId) {
            // Show loading state
            document.getElementById('verificationModal').style.display = 'block';
            document.getElementById('verificationDetails').innerHTML = '<p class="text-center">Loading verification details...</p>';
            
            fetch(`get_verification_documents.php?provider_id=${providerId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const details = data.details;
                        const verificationDetails = document.getElementById('verificationDetails');
                        
                        // Create content based on whether documents exist
                        if (details.has_documents) {
                            let content = `
                                <div class="verification-info">
                                    <p><strong>Business Name:</strong> ${details.business_name || 'N/A'}</p>
                                    <p><strong>ID Type:</strong> ${details.id_type || 'N/A'}</p>
                                    <p><strong>ID Number:</strong> ${details.id_number || 'N/A'}</p>
                                    <p><strong>Uploaded:</strong> ${details.uploaded_at ? new Date(details.uploaded_at).toLocaleString() : 'N/A'}</p>
                                </div>
                                <div class="verification-images">
                                    <div class="image-container">
                                        <h4>ID Front</h4>
                                        ${details.id_front_path ? 
                                            `<img src="${details.id_front_path}" alt="ID Front" class="verification-image">` : 
                                            '<p class="text-center">No image uploaded</p>'}
                                    </div>
                                    <div class="image-container">
                                        <h4>ID Back</h4>
                                        ${details.id_back_path ? 
                                            `<img src="${details.id_back_path}" alt="ID Back" class="verification-image">` : 
                                            '<p class="text-center">No image uploaded</p>'}
                                    </div>
                                    <div class="image-container">
                                        <h4>Address Proof</h4>
                                        ${details.address_proof_path ? 
                                            `<img src="${details.address_proof_path}" alt="Address Proof" class="verification-image">` : 
                                            '<p class="text-center">No image uploaded</p>'}
                                    </div>
                                </div>
                            `;
                            verificationDetails.innerHTML = content;
                        } else {
                            verificationDetails.innerHTML = `
                                <div class="alert alert-warning">
                                    <p>No verification documents have been submitted by this provider.</p>
                                    <p><strong>Business Name:</strong> ${details.business_name || 'N/A'}</p>
                                    <p><strong>Status:</strong> ${details.status || 'N/A'}</p>
                                </div>
                            `;
                        }
                    } else {
                        document.getElementById('verificationDetails').innerHTML = `
                            <div class="alert alert-danger">
                                <p>Error: ${data.message}</p>
                            </div>
                        `;
                    }
                })
                .catch(error => {
                    console.error('Error fetching verification details:', error);
                    document.getElementById('verificationDetails').innerHTML = `
                        <div class="alert alert-danger">
                            <p>Error loading verification details. Please try again.</p>
                            <p>Technical details: ${error.message}</p>
                        </div>
                    `;
                });
        }
        
        // Close modal when clicking the X
        document.querySelector('.close').addEventListener('click', function() {
            document.getElementById('verificationModal').style.display = 'none';
        });
        
        // Close modal when clicking outside of it
        window.addEventListener('click', function(event) {
            if (event.target == document.getElementById('verificationModal')) {
                document.getElementById('verificationModal').style.display = 'none';
            }
        });

        function viewVerification(providerId) {
            // Show modal
            document.getElementById('verificationModal').style.display = 'block';
            document.getElementById('verificationDetails').innerHTML = '<div class="loading">Loading verification details...</div>';
            
            console.log('Fetching verification details for provider ID:', providerId);
            
            // Load verification details
            fetch('get_verification_details.php?provider_id=' + providerId)
                .then(response => {
                    console.log('Response status:', response.status);
                    if (!response.ok) {
                        return response.text().then(text => {
                            throw new Error('Network response was not ok: ' + text);
                        });
                    }
                    return response.text().then(text => {
                        console.log('Raw response:', text);
                        try {
                            return JSON.parse(text);
                        } catch (e) {
                            throw new Error('Invalid JSON response: ' + e.message + '\nRaw response: ' + text);
                        }
                    });
                })
                .then(data => {
                    console.log('Parsed data:', data);
                    if (data.success) {
                        displayVerificationDetails(data.details, providerId);
                    } else {
                        document.getElementById('verificationDetails').innerHTML = 
                            '<div class="error">Error: ' + data.message + '</div>';
                    }
                })
                .catch(error => {
                    console.error('Error fetching verification details:', error);
                    document.getElementById('verificationDetails').innerHTML = 
                        '<div class="error">Error loading verification details: ' + error.message + '</div>';
                });
        }
        
        function displayVerificationDetails(details, providerId) {
            const html = `
                <div class="verification-details">
                    <div class="verification-detail-item">
                        <span class="verification-detail-label">Provider Name</span>
                        <span class="verification-detail-value">${details.username}</span>
                    </div>
                    <div class="verification-detail-item">
                        <span class="verification-detail-label">Email</span>
                        <span class="verification-detail-value">${details.email}</span>
                    </div>
                    <div class="verification-detail-item">
                        <span class="verification-detail-label">ID Type</span>
                        <span class="verification-detail-value">${details.id_type}</span>
                    </div>
                    <div class="verification-detail-item">
                        <span class="verification-detail-label">ID Number</span>
                        <span class="verification-detail-value">${details.id_number}</span>
                    </div>
                </div>
                
                <h3>Verification Documents</h3>
                <div class="verification-documents">
                    <div class="verification-document">
                        <div class="verification-document-label">ID Proof (Front)</div>
                        <div class="verification-document-image">
                            <img src="${details.id_proof_front}" alt="ID Proof Front">
                        </div>
                    </div>
                    <div class="verification-document">
                        <div class="verification-document-label">ID Proof (Back)</div>
                        <div class="verification-document-image">
                            <img src="${details.id_proof_back}" alt="ID Proof Back">
                        </div>
                    </div>
                    <div class="verification-document">
                        <div class="verification-document-label">Address Proof</div>
                        <div class="verification-document-image">
                            <img src="${details.address_proof}" alt="Address Proof">
                        </div>
                    </div>
                </div>
                
                <div class="verification-actions">
                    <button class="btn btn-reject" onclick="updateVerificationStatus(${providerId}, 'rejected')">
                        Reject Verification
                    </button>
                    <button class="btn btn-approve" onclick="updateVerificationStatus(${providerId}, 'approved')">
                        Approve Verification
                    </button>
                </div>
            `;
            
            document.getElementById('verificationDetails').innerHTML = html;
        }
        
        function closeVerificationModal() {
            document.getElementById('verificationModal').style.display = 'none';
        }
        
        function updateVerificationStatus(providerId, status) {
            console.log(`Updating verification status for provider ${providerId} to ${status}`);
            
            // Create form data
            const formData = new FormData();
            formData.append('provider_id', providerId);
            formData.append('status', status);
            
            fetch('update_verification_status.php', {
                method: 'POST',
                body: formData
            })
            .then(response => {
                console.log('Response status:', response.status);
                return response.text().then(text => {
                    console.log('Raw response:', text);
                    try {
                        return JSON.parse(text);
                    } catch (e) {
                        throw new Error('Invalid JSON response: ' + e.message + '\nRaw response: ' + text);
                    }
                });
            })
            .then(data => {
                console.log('Parsed data:', data);
                if (data.success) {
                    alert(data.message);
                    closeVerificationModal();
                    // Reload the page to update the verification list
                    location.reload();
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error updating verification status:', error);
                alert('Error updating verification status: ' + error.message);
            });
        }
        
        // Close modal when clicking outside of it
        window.onclick = function(event) {
            const modal = document.getElementById('verificationModal');
            if (event.target == modal) {
                closeVerificationModal();
            }
        }

        // Updated function to correctly target elements in your table
        function updateProviderStatus(providerId, status) {
            console.log(`Updating provider ${providerId} status to ${status}`);
            
            // Create form data
            const formData = new FormData();
            formData.append('provider_id', providerId);
            formData.append(status === 'approved' ? 'approve_provider' : 'reject_provider', 'true');
            
            fetch('update_provider_status.php', {
                method: 'POST',
                body: formData
            })
            .then(response => {
                console.log('Response status:', response.status);
                return response.json();
            })
            .then(data => {
                console.log('Response data:', data);
                if (data.success) {
                    // Force page reload to show updated status
                    // This is the simplest solution if the dynamic updates aren't working
                    location.reload();
                    
                    // Show success message after reload is triggered
                    alert(data.message);
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error updating provider status:', error);
                alert('Error updating provider status: ' + error.message);
            });
        }

        // Helper to find elements by text content
        // Add this utility function
        Document.prototype.querySelector = Document.prototype.querySelector || function() {
            return this.querySelector.apply(this, arguments);
        };
        
        Element.prototype.contains = function(text) {
            return this.textContent.includes(text);
        };
    </script>
</body>
</html>