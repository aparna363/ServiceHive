<?php
session_start();
require_once 'dbconnect.php';
require 'PHPMailer-master/src/Exception.php';
require 'PHPMailer-master/src/PHPMailer.php';
require 'PHPMailer-master/src/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Enable error logging
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', 'verification_error.log');

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    die(json_encode(['success' => false, 'message' => 'Unauthorized access']));
}

// Check if required parameters are provided
if (!isset($_POST['provider_id']) || !isset($_POST['status'])) {
    die(json_encode(['success' => false, 'message' => 'Missing required parameters']));
}

$provider_id = intval($_POST['provider_id']);
$status = $_POST['status'];

// Validate status
if (!in_array($status, ['approved', 'rejected'])) {
    die(json_encode(['success' => false, 'message' => 'Invalid status']));
}

// Log the request
error_log("Updating verification status for provider $provider_id to $status");

try {
    // Begin transaction
    $conn->begin_transaction();
    
    // Get provider details
    $stmt = $conn->prepare("
        SELECT sp.provider_id, sp.user_id, u.email, u.username
        FROM service_providers sp
        JOIN users u ON sp.user_id = u.id
        WHERE sp.provider_id = ?
    ");
    $stmt->bind_param("i", $provider_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        throw new Exception("Provider not found");
    }
    
    $provider = $result->fetch_assoc();
    $user_id = $provider['user_id'];
    
    // Update provider status in service_providers table
    $verified_status = ($status === 'approved') ? 1 : 0;
    
    // Log the update
    error_log("Updating service_providers table: status=$status, verified_status=$verified_status for provider_id=$provider_id");
    
    $stmt = $conn->prepare("
        UPDATE service_providers 
        SET status = ?, 
            verified_status = ?
        WHERE provider_id = ?
    ");
    $stmt->bind_param("sii", $status, $verified_status, $provider_id);
    
    if (!$stmt->execute()) {
        throw new Exception("Failed to update provider status: " . $stmt->error);
    }
    
    // Log the affected rows
    error_log("Affected rows: " . $stmt->affected_rows);
    
    if ($stmt->affected_rows === 0) {
        // Try to diagnose the issue
        $check_stmt = $conn->prepare("SELECT * FROM service_providers WHERE provider_id = ?");
        $check_stmt->bind_param("i", $provider_id);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows === 0) {
            throw new Exception("Provider record not found in database");
        } else {
            $provider_record = $check_result->fetch_assoc();
            error_log("Provider record exists but wasn't updated: " . print_r($provider_record, true));
        }
    }
    
    // Create notification for the provider
    $title = $status === 'approved' ? 'Verification Approved' : 'Verification Rejected';
    $message = $status === 'approved' 
        ? 'Your account verification has been approved. You now have full access to the platform.'
        : 'Your account verification has been rejected. Please check your email for details.';
    
    $stmt = $conn->prepare("
        INSERT INTO notifications (user_id, title, message, type, created_at, is_read)
        VALUES (?, ?, ?, 'verification', NOW(), 0)
    ");
    $stmt->bind_param("iss", $user_id, $title, $message);
    
    if (!$stmt->execute()) {
        throw new Exception("Failed to create notification: " . $stmt->error);
    }
    
    // Commit transaction
    $conn->commit();
    
    // Return success response
    echo json_encode([
        'success' => true, 
        'message' => "Provider verification has been $status successfully"
    ]);
    
} catch (Exception $e) {
    // Rollback transaction on error
    if (isset($conn) && $conn->ping()) {
        $conn->rollback();
    }
    
    // Log the error
    error_log("Error in update_verification_status.php: " . $e->getMessage());
    
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

// Function to send verification email
function sendVerificationEmail($recipientEmail, $recipientName, $status) {
    $mail = new PHPMailer(true);

    try {
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'aparnaprasad363@gmail.com';
        $mail->Password   = 'wbnh wldc yeqo sqzi';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;

        $mail->setFrom('aparnaprasad363@gmail.com', 'ServiceHive');
        $mail->addAddress($recipientEmail, $recipientName);
        
        if ($status === 'approved') {
            $mail->Subject = 'ServiceHive: Your Account Verification is Approved';
            $mail->Body = "Dear $recipientName,\n\n"
                . "Great news! Your account verification has been approved.\n\n"
                . "You now have full access to the ServiceHive platform and can start providing services to customers.\n\n"
                . "Here's what you can do now:\n"
                . "- Update your service offerings\n"
                . "- Set your availability\n"
                . "- Receive and manage bookings\n"
                . "- Communicate with customers\n\n"
                . "Log in to your account: http://" . $_SERVER['HTTP_HOST'] . "/ServiceHive/login.php\n\n"
                . "If you have any questions or need assistance, please contact our support team at aparnaprasad363@gmail.com.\n\n"
                . "Thank you for choosing ServiceHive!\n\n"
                . "Best regards,\nThe ServiceHive Team";
        } else {
            $mail->Subject = 'ServiceHive: Your Account Verification is Rejected';
            $mail->Body = "Dear $recipientName,\n\n"
                . "Unfortunately, your account verification has been rejected.\n\n"
                . "This could be due to one of the following reasons:\n"
                . "- The documents provided were unclear or unreadable\n"
                . "- The information provided did not match our requirements\n"
                . "- The identification documents appear to be invalid or expired\n\n"
                . "Please log in to your account and resubmit your verification documents. Make sure that:\n"
                . "- All documents are clear and readable\n"
                . "- All information matches your registration details\n"
                . "- All documents are valid and not expired\n\n"
                . "Log in to your account: http://" . $_SERVER['HTTP_HOST'] . "/ServiceHive/login.php\n\n"
                . "If you have any questions or need assistance, please contact our support team at aparnaprasad363@gmail.com.\n\n"
                . "Thank you for choosing ServiceHive!\n\n"
                . "Best regards,\nThe ServiceHive Team";
        }

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Email sending failed: " . $mail->ErrorInfo);
        return false;
    }
}
?> 