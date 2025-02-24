<?php
// resend-verification.php - Handle resending verification email
session_start();
require_once 'dbconnect.php';

if(isset($_POST['resend'])) {
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    
    // Check if user exists and is not verified
    $sql = "SELECT * FROM users WHERE email = ? AND is_verified = 0";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if($result->num_rows > 0) {
        $user = $result->fetch_assoc();
        
        // Generate new verification token
        $verification_token = bin2hex(random_bytes(32));
        $token_expiry = date('Y-m-d H:i:s', strtotime('+24 hours'));
        
        // Update user with new token
        $update_sql = "UPDATE users SET verification_token = ?, token_expiry = ? WHERE email = ?";
        $update_stmt = $conn->prepare($update_sql);
        $update_stmt->bind_param("sss", $verification_token, $token_expiry, $email);
        
        if($update_stmt->execute()) {
            // Send new verification email
            $verification_link = "http://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) . "/verify.php?token=" . $verification_token;
            
            $to = $email;
            $subject = "New Verification Link - ServiceHive";
            $message = "
            <html>
            <head>
                <title>New Email Verification Link</title>
            </head>
            <body>
                <h2>New Verification Link</h2>
                <p>You requested a new verification link. Please click below to verify your email:</p>
                <p><a href='" . $verification_link . "'>" . $verification_link . "</a></p>
                <p>This link will expire in 24 hours.</p>
            </body>
            </html>
            ";
            
            $headers = "MIME-Version: 1.0" . "\r\n";
            $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
            $headers .= 'From: ServiceHive <noreply@servicehive.com>' . "\r\n";
            
            if(mail($to, $subject, $message, $headers)) {
                $_SESSION['success_message'] = "New verification link has been sent to your email.";
                header("Location: verification-pending.php");
                exit();
            }
        }
    }
    
    $_SESSION['error_message'] = "Email not found or already verified.";
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Resend Verification - ServiceHive</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: system-ui, sans-serif;
        }

        body {
            min-height: 100vh;
            background: linear-gradient(rgba(0,0,0,0.5), rgba(0,0,0,0.5)),url('images/login.jpg');
            background-repeat: no-repeat;
            background-size: cover;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 15px;
        }

        .container {
            background: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
            width: 100%;
            max-width: 400px;
            text-align: center;
        }

        .logo1 img {
            max-width: 300px;
            height: auto;
            margin-bottom: 20px;
        }

        h2 {
            color: rgb(220, 95, 17);
            margin-bottom: 15px;
        }

        .form-group {
            margin-bottom: 15px;
        }

        input[type="email"] {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 14px;
        }

        .submit-btn {
            width: 100%;
            padding: 10px;
            background: rgb(220, 95, 17);
            color: white;
            border: none;
            border-radius: 6px;
            font-size: 14px;
            cursor: pointer;
        }

        .submit-btn:hover {
            background: rgb(200, 85, 15);
        }

        .error {
            color: #e74c3c;
            margin-bottom: 15px;
        }

        .success {
            color: #2ecc71;
            margin-bottom: 15px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="logo1">
            <img src="images/logo2.png" alt="Website Logo">
        </div>
        <h2>Resend Verification Email</h2>
        
        <?php if(isset($_SESSION['error_message'])): ?>
            <div class="error"><?php echo $_SESSION['error_message']; unset($_SESSION['error_message']); ?></div>
        <?php endif; ?>
        
        <?php if(isset($_SESSION['success_message'])): ?>
            <div class="success"><?php echo $_SESSION['success_message']; unset($_SESSION['success_message']); ?></div>
        <?php endif; ?>
        
        <form method="post" action="">
            <div class="form-group">
                <input type="email" name="email" placeholder="Enter your email" required>
            </div>
            <input type="submit" name="resend" value="Resend Verification Email" class="submit-btn">
        </form>
    </div>
</body>
</html>