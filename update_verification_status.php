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
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

// Check if required parameters are provided
if (!isset($_POST['provider_id']) || !isset($_POST['status'])) {
    echo json_encode(['success' => false, 'message' => 'Missing required parameters']);
    exit();
}

$provider_id = intval($_POST['provider_id']);
$status = $_POST['status'];

// Validate status
if (!in_array($status, ['approved', 'rejected'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid status']);
    exit();
}

// Log the request
error_log("Updating verification status for provider $provider_id to $status");

// Email configuration settings
$EMAIL_HOST = 'smtp.gmail.com';
$EMAIL_PORT = 587;
$EMAIL_USERNAME = 'aparnaprasad363@gmail.com'; 
$EMAIL_PASSWORD = 'wbnh wldc yeqo sqzi';  // Consider using app password for Gmail
$EMAIL_FROM = 'aparnaprasad363@gmail.com';
$EMAIL_FROM_NAME = 'ServiceHive';

// Function to send email
function sendEmail($recipientEmail, $recipientName, $subject, $htmlMessage, $plainTextMessage = '') {
    global $EMAIL_HOST, $EMAIL_PORT, $EMAIL_USERNAME, $EMAIL_PASSWORD, $EMAIL_FROM, $EMAIL_FROM_NAME;
    
    $mail = new PHPMailer(true);
    
    try {
        // Server settings
        $mail->SMTPDebug = 0;                      // Enable verbose debug output (set to 2 for debugging)
        $mail->isSMTP();                           // Send using SMTP
        $mail->Host       = $EMAIL_HOST;           // SMTP server
        $mail->SMTPAuth   = true;                  // Enable SMTP authentication
        $mail->Username   = $EMAIL_USERNAME;       // SMTP username
        $mail->Password   = $EMAIL_PASSWORD;       // SMTP password
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS; // Enable TLS encryption
        $mail->Port       = $EMAIL_PORT;           // TCP port to connect to
        
        // Recipients
        $mail->setFrom($EMAIL_FROM, $EMAIL_FROM_NAME);
        $mail->addAddress($recipientEmail, $recipientName);
        
        // Content
        $mail->isHTML(true);                       // Set email format to HTML
        $mail->Subject = $subject;
        $mail->Body    = $htmlMessage;             // HTML body
        
        // If plain text message is not provided, create one from HTML
        if (empty($plainTextMessage)) {
            $plainTextMessage = strip_tags(str_replace('<br>', "\n", $htmlMessage));
        }
        
        $mail->AltBody = $plainTextMessage;        // Plain text body
        
        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Email sending failed: " . $mail->ErrorInfo);
        return false;
    }
}

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
    
    // Send email notification
    if (!empty($provider['email'])) {
        $subject = $status === 'approved' 
            ? "Your ServiceHive Account Has Been Approved" 
            : "Your ServiceHive Application Status";
        
        // Create HTML email with CSS styling
        if ($status === 'approved') {
            $emailMessage = '
            <div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #e0e0e0; border-radius: 5px; background-color: #ffffff;">
                <div style="text-align: center; margin-bottom: 20px;">
                    <h1 style="color: #4CAF50; margin-bottom: 5px;">Congratulations!</h1>
                    <div style="width: 100px; height: 5px; background-color: #4CAF50; margin: 0 auto;"></div>
                </div>
                
                <p style="font-size: 16px; line-height: 1.5; color: #333333;">Dear ' . htmlspecialchars($provider['username']) . ',</p>
                
                <div style="background-color: #f9f9f9; border-left: 4px solid #4CAF50; padding: 15px; margin: 20px 0;">
                    <p style="font-size: 16px; margin: 0; color: #333333;">Your ServiceHive account has been <strong style="color: #4CAF50;">approved</strong> by our administration team!</p>
                </div>
                
                <p style="font-size: 16px; line-height: 1.5; color: #333333;">You can now log in to your account and start offering your services to customers.</p>
                
                <div style="text-align: center; margin: 30px 0;">
                    <a href="localhost/serviceHive/login.php" style="background-color: #4CAF50; color: white; padding: 12px 25px; text-decoration: none; border-radius: 4px; font-weight: bold; display: inline-block;">Login to Your Account</a>
                </div>
                
                <p style="font-size: 16px; line-height: 1.5; color: #333333;">Thank you for joining ServiceHive!</p>
                
                <div style="margin-top: 30px; padding-top: 20px; border-top: 1px solid #e0e0e0;">
                    <p style="font-size: 14px; color: #777777; margin: 0;">Best regards,<br>The ServiceHive Team</p>
                </div>
                
                <div style="text-align: center; margin-top: 20px;">
                    <p style="font-size: 12px; color: #999999;">© ' . date('Y') . ' ServiceHive. All rights reserved.</p>
                    <div style="margin-top: 10px;">
                        <a href="#" style="color: #4CAF50; text-decoration: none; margin: 0 10px;">Website</a>
                        <a href="#" style="color: #4CAF50; text-decoration: none; margin: 0 10px;">Privacy Policy</a>
                        <a href="#" style="color: #4CAF50; text-decoration: none; margin: 0 10px;">Contact Us</a>
                    </div>
                </div>
            </div>';
        } else {
            $emailMessage = '
            <div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #e0e0e0; border-radius: 5px; background-color: #ffffff;">
                <div style="text-align: center; margin-bottom: 20px;">
                    <h1 style="color: #F44336; margin-bottom: 5px;">Application Status Update</h1>
                    <div style="width: 100px; height: 5px; background-color: #F44336; margin: 0 auto;"></div>
                </div>
                
                <p style="font-size: 16px; line-height: 1.5; color: #333333;">Dear ' . htmlspecialchars($provider['username']) . ',</p>
                
                <div style="background-color: #f9f9f9; border-left: 4px solid #F44336; padding: 15px; margin: 20px 0;">
                    <p style="font-size: 16px; margin: 0; color: #333333;">We regret to inform you that your ServiceHive service provider application has been <strong style="color: #F44336;">rejected</strong>.</p>
                </div>
                
                <p style="font-size: 16px; line-height: 1.5; color: #333333;">If you believe this is an error or would like to understand the reason, please contact our support team.</p>
                
                <div style="text-align: center; margin: 30px 0;">
                    <a href="mailto:support@servicehive.com" style="background-color: #F44336; color: white; padding: 12px 25px; text-decoration: none; border-radius: 4px; font-weight: bold; display: inline-block;">Contact Support</a>
                </div>
                
                <p style="font-size: 16px; line-height: 1.5; color: #333333;">You may reapply after addressing any issues with your application.</p>
                
                <div style="margin-top: 30px; padding-top: 20px; border-top: 1px solid #e0e0e0;">
                    <p style="font-size: 14px; color: #777777; margin: 0;">Best regards,<br>The ServiceHive Team</p>
                </div>
                
                <div style="text-align: center; margin-top: 20px;">
                    <p style="font-size: 12px; color: #999999;">© ' . date('Y') . ' ServiceHive. All rights reserved.</p>
                    <div style="margin-top: 10px;">
                        <a href="#" style="color: #F44336; text-decoration: none; margin: 0 10px;">Website</a>
                        <a href="#" style="color: #F44336; text-decoration: none; margin: 0 10px;">Privacy Policy</a>
                        <a href="#" style="color: #F44336; text-decoration: none; margin: 0 10px;">Contact Us</a>
                    </div>
                </div>
            </div>';
        }
        
        // Create plain text version for email clients that don't support HTML
        $plainTextMessage = $status === 'approved' 
            ? "Dear " . $provider['username'] . ",\n\nCongratulations! Your ServiceHive account has been approved by our administration team.\n\nYou can now log in to your account and start offering your services to customers.\n\nThank you for joining ServiceHive!\n\nBest regards,\nThe ServiceHive Team"
            : "Dear " . $provider['username'] . ",\n\nWe regret to inform you that your ServiceHive service provider application has been rejected.\n\nIf you believe this is an error or would like to understand the reason, please contact our support team at support@servicehive.com.\n\nYou may reapply after addressing any issues with your application.\n\nBest regards,\nThe ServiceHive Team";
        
        // Send the email
        $emailSent = sendEmail($provider['email'], $provider['username'], $subject, $emailMessage, $plainTextMessage);
        
        // Log email status
        error_log("Email to {$provider['email']} " . ($emailSent ? "sent successfully" : "failed to send"));
        
        // If email fails, store in queue for later sending
        if (!$emailSent) {
            $query5 = "INSERT INTO email_queue (recipient, subject, message, created_at) VALUES (?, ?, ?, NOW())";
            $stmt5 = $conn->prepare($query5);
            $stmt5->bind_param("sss", $provider['email'], $subject, $emailMessage);
            $stmt5->execute();
        }
    }
    
    // Commit transaction
    $conn->commit();
    
    // Return success response
    echo json_encode([
        'success' => true, 
        'message' => "Provider verification has been $status successfully",
        'email_sent' => isset($emailSent) ? $emailSent : false
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

// Close connection
$conn->close();
?> 