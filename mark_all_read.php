<?php
session_start();
require_once 'dbconnect.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$user_id = $_SESSION['user_id'];

// Mark all notifications as read
$stmt = $conn->prepare("
    UPDATE notifications 
    SET is_read = 1 
    WHERE user_id = ?
");
$stmt->bind_param("i", $user_id);
$stmt->execute();

// Redirect back to previous page
header('Location: ' . $_SERVER['HTTP_REFERER']);
exit();
?> 