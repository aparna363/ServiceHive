<?php
session_start();
require_once 'dbconnect.php';
error_log("Verification attempt - Token: " . $_GET['token']);
error_log("Current time: " . date('Y-m-d H:i:s'));

if (isset($_GET['token'])) {
    $token = $_GET['token']; // Token is already URL-decoded by PHP
    
    // Check if the token exists and the user is not already verified
    $sql = "SELECT * FROM users WHERE verification_token = ? AND email_verified = 0";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();
        
        // Check if the token is expired
        if (strtotime($user['token_expiry']) < time()) {
            $_SESSION['error_message'] = "Verification link has expired. Please request a new one.";
            header("Location: verification-failed.php");
            exit();
        }
        
        // Update the user as verified
        $update_sql = "UPDATE users SET email_verified = 1, verification_token = NULL WHERE verification_token = ?";
        $update_stmt = $conn->prepare($update_sql);
        $update_stmt->bind_param("s", $token);
        
        if ($update_stmt->execute()) {
            $_SESSION['success_message'] = "Email verified successfully! You can now login.";
            header("Location: login.php");
            exit();
        } else {
            $_SESSION['error_message'] = "Failed to update verification status.";
            header("Location: verification-failed.php");
            exit();
        }
    } else {
        $_SESSION['error_message'] = "Invalid verification token or account already verified.";
        header("Location: verification-failed.php");
        exit();
    }
} else {
    $_SESSION['error_message'] = "No verification token provided.";
    header("Location: verification-failed.php");
    exit();
}
?>