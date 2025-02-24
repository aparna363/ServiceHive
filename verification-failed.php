<?php
// verification-failed.php - Show verification failure message
session_start();
?>
<!DOCTYPE html>
<html>
<head>
    <title>Verification Failed - ServiceHive</title>
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
            color: #e74c3c;
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
        <h2>Verification Failed</h2>
        <p><?php echo isset($_SESSION['error_message']) ? $_SESSION['error_message'] : "The verification link is invalid or has expired."; ?></p>
        <p>If you need a new verification link, please try registering again or contact support.</p>
        <div>
            <a href="signup.php" class="button">Register Again</a>
            <a href="index.php" class="button">Go to Homepage</a>
        </div>
    </div>
</body>
</html>