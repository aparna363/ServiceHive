<?php
session_start();
require_once 'dbconnect.php'; // For database connection

// Check if user is logged in (provider or regular user)
if (!isset($_SESSION['user_id'])) {
    $_SESSION['error'] = "You must be logged in to contact support.";
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['role'] ?? 'user'; // Get user role

// --- Handle Form Submission ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['submit_ticket'])) {
    $subject = filter_input(INPUT_POST, 'subject', FILTER_SANITIZE_SPECIAL_CHARS);
    $message = filter_input(INPUT_POST, 'message', FILTER_SANITIZE_SPECIAL_CHARS);

    // Basic validation
    if (empty($subject) || empty($message)) {
        $_SESSION['error'] = "Please provide both a subject and a message.";
    } elseif (strlen($subject) > 255) {
         $_SESSION['error'] = "Subject cannot be longer than 255 characters.";
    } else {
        // Insert into the database
        $stmt = $conn->prepare("INSERT INTO support_tickets (user_id, subject, message, status) VALUES (?, ?, ?, 'Open')");
        if ($stmt) {
            $stmt->bind_param("iss", $user_id, $subject, $message);
            if ($stmt->execute()) {
                $_SESSION['message'] = "Your support request has been submitted successfully. We will get back to you soon.";
                // Redirect based on role to avoid form resubmission
                if ($user_role === 'service_provider') {
                    header("Location: provider_dashboard.php"); // Or stay on contact_support.php
                } else {
                     header("Location: user_dashboard.php"); // Or stay on contact_support.php
                }
                exit();
            } else {
                $_SESSION['error'] = "Failed to submit your request. Please try again. Error: " . $stmt->error;
                error_log("Failed to insert support ticket for user ID $user_id: " . $stmt->error);
            }
            $stmt->close();
        } else {
             $_SESSION['error'] = "Database error. Could not prepare statement.";
             error_log("Failed to prepare statement for support ticket insertion: " . $conn->error);
        }
    }
    // If there was an error, redirect back to the form page
    header("Location: contact_support.php");
    exit();
}

// Determine the dashboard link based on role
$dashboard_link = ($user_role === 'service_provider') ? 'provider_dashboard.php' : 'user_dashboard.php'; // Adjust user dashboard link if needed

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contact Support - ServiceHive</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <!-- Link your common CSS file or use styles similar to provider_dashboard.php -->
    <style>
        /* Basic styles - Adapt from your dashboard styles */
        body { font-family: 'Arial', sans-serif; background-color: #f4f6f9; margin: 0; display: flex; min-height: 100vh; }
        .sidebar { width: 250px; background-color: rgb(104, 35, 3); color: white; position: fixed; height: 100vh; left: 0; top: 0; padding-top: 20px; }
        .sidebar .logo-container { text-align: center; padding: 20px 0; }
        .sidebar .logo-container img { width: 180px; }
        .sidebar-menu a { display: block; color: white; padding: 12px 20px; text-decoration: none; transition: background-color 0.3s; }
        .sidebar-menu a:hover, .sidebar-menu a.active { background-color: rgb(171, 46, 8); }
        .sidebar-menu i { margin-right: 10px; }
        .main-content { margin-left: 250px; padding: 30px; width: calc(100% - 250px); }
        .header { background-color: white; padding: 20px; margin-bottom: 25px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); border-radius: 8px; }
        .header h1 { margin: 0; font-size: 26px; color: #333; }

        .form-container { background-color: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); max-width: 700px; margin: 0 auto; }
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; margin-bottom: 8px; font-weight: bold; color: #555; }
        .form-group input[type="text"],
        .form-group textarea {
            width: 100%;
            padding: 12px;
            border: 1px solid #ccc;
            border-radius: 5px;
            box-sizing: border-box;
            font-size: 16px;
        }
        .form-group textarea { min-height: 150px; resize: vertical; }
        .btn-submit {
            background-color: rgb(104, 35, 3);
            color: white;
            padding: 12px 25px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 16px;
            transition: background-color 0.3s;
        }
        .btn-submit:hover { background-color: rgb(171, 46, 8); }

        /* Flash Messages */
        .alert { padding: 15px; margin-bottom: 20px; border: 1px solid transparent; border-radius: 4px; }
        .alert-success { color: #155724; background-color: #d4edda; border-color: #c3e6cb; }
        .alert-danger { color: #721c24; background-color: #f8d7da; border-color: #f5c6cb; }
    </style>
</head>
<body>
    <!-- Sidebar (Include appropriate sidebar based on user role) -->
    <div class="sidebar">
        <div class="logo-container">
            <img src="images/logo2.png" alt="ServiceHive Logo">
        </div>
        <div class="sidebar-menu">
            <?php if ($user_role === 'service_provider'): ?>
                <!-- Provider Sidebar Links -->
                <li><a href="provider_dashboard.php"><i class="fas fa-home"></i> Dashboard</a></li>
                <li><a href="index.php"><i class="fas fa-globe"></i> Home </a></li>
                <li><a href="#"><i class="fas fa-calendar"></i> Bookings</a></li> <!-- Add actual link -->
                <li><a href="service-management.php"><i class="fas fa-tools"></i> Services</a></li>
                <li><a href="subservice-management.php"><i class="fas fa-tools"></i> Sub Services</a></li>
                <li><a href="provider-review.php"><i class="fas fa-star"></i> Reviews</a></li>
                <li><a href="contact_support.php" class="active"><i class="fas fa-headset"></i> Contact Support</a></li>
                <li><a href="profile.php"><i class="fas fa-user"></i> Profile</a></li>
                <li><a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
            <?php else: ?>
                <!-- User Sidebar Links (Example) -->
                <li><a href="user_dashboard.php"><i class="fas fa-home"></i> Dashboard</a></li>
                <li><a href="index.php"><i class="fas fa-globe"></i> Home </a></li>
                <li><a href="my_bookings.php"><i class="fas fa-calendar-check"></i> My Bookings</a></li>
                <li><a href="contact_support.php" class="active"><i class="fas fa-headset"></i> Contact Support</a></li>
                <li><a href="profile.php"><i class="fas fa-user"></i> Profile</a></li>
                <li><a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
            <?php endif; ?>
        </div>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <div class="header">
            <h1>Contact Support</h1>
        </div>

        <!-- Flash Messages -->
        <?php if (isset($_SESSION['message'])): ?>
            <div class="alert alert-success"><?php echo $_SESSION['message']; unset($_SESSION['message']); ?></div>
        <?php endif; ?>
        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger"><?php echo $_SESSION['error']; unset($_SESSION['error']); ?></div>
        <?php endif; ?>

        <div class="form-container">
            <p>If you need assistance or have any questions, please fill out the form below. Our support team will get back to you as soon as possible.</p>
            <br>
            <form action="contact_support.php" method="POST">
                <div class="form-group">
                    <label for="subject">Subject:</label>
                    <input type="text" id="subject" name="subject" required maxlength="255">
                </div>
                <div class="form-group">
                    <label for="message">Message:</label>
                    <textarea id="message" name="message" required></textarea>
                </div>
                <div class="form-group">
                    <button type="submit" name="submit_ticket" class="btn-submit">
                        <i class="fas fa-paper-plane"></i> Submit Request
                    </button>
                </div>
            </form>
        </div>
    </div><!-- /main-content -->

</body>
</html> 