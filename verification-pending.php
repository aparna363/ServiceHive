<?php
// verification-pending.php - Show pending verification message
session_start();
?>
<!DOCTYPE html>
<html>
<head>
    <title>Verification Pending - ServiceHive</title>
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
            max-width: 500px;
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

        p {
            color: #666;
            line-height: 1.6;
            margin-bottom: 15px;
        }

        .button {
            display: inline-block;
            padding: 10px 20px;
            background: rgb(220, 95, 17);
            color: white;
            text-decoration: none;
            border-radius: 6px;
            margin-top: 15px;
        }

        .button:hover {
            background: rgb(200, 85, 15);
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="logo1">
            <img src="images/logo2.png" alt="Website Logo">
        </div>
        <h2>Verification Email Sent!</h2>
        <p>We've sent a verification link to your email address. Please check your inbox and click the link to complete your registration.</p>
        <p>If you don't see the email, please check your spam folder.</p>
        <p>The verification link will expire in 24 hours.</p>
        <a href="login.php" class="button">Return to Login</a>
    </div>
</body>
</html>