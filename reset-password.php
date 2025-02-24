<?php
session_start();
require_once 'dbconnect.php';

// Check if user is verified
if(!isset($_SESSION['verified']) || !isset($_SESSION['reset_email'])) {
    header("Location: forgot password.php");
    exit();
}

$error_message = '';
$success_message = '';

if(isset($_POST['reset_password'])) {
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    
    if($password !== $confirm_password) {
        $error_message = "Passwords do not match!";
    } elseif(strlen($password) < 8) {
        $error_message = "Password must be at least 8 characters long!";
    } else {
        $email = $_SESSION['reset_email'];
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        
        $sql = "UPDATE users SET password = ? WHERE email = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ss", $hashed_password, $email);
        
        if($stmt->execute()) {
            // Clear all session variables
            session_unset();
            session_destroy();
            
            // Start new session for success message
            session_start();
            $_SESSION['password_reset_success'] = true;
            
            header("Location: login.php");
            exit();
        } else {
            $error_message = "Failed to update password. Please try again.";
        }
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Reset Password</title>
    <style>
        body {
            background: linear-gradient(rgba(0,0,0,0.5), rgba(0,0,0,0.5)),url('images/login.jpg');
            background-repeat: no-repeat;
            background-size: cover;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        form {
            background: white;
            max-width: 400px;
            width: 90%;
            margin: 20px auto;
            padding: 40px;
            border-radius: 12px;
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s ease;
        }

        form:hover {
            transform: translateY(-5px);
        }

        h2 {
            color: #333;
            text-align: center;
            margin-bottom: 30px;
            font-size: 28px;
            font-weight: 600;
        }

        .description {
            text-align: center;
            color: #666;
            margin-bottom: 20px;
            font-size: 14px;
        }

        input {
            width: 100%;
            padding: 12px;
            margin: 12px 0;
            border: 2px solid #eee;
            border-radius: 8px;
            font-size: 16px;
            transition: border-color 0.3s ease;
            box-sizing: border-box;
            outline: none;
        }

        input:focus {
            border-color: rgb(239, 114, 36);
        }

        .submit-btn {
            background: rgb(239, 114, 36);
            color: white;
            border: none;
            padding: 14px;
            cursor: pointer;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            width: 100%;
            transition: background 0.3s ease;
            margin-top: 20px;
        }

        .submit-btn:hover {
            background: rgb(220, 95, 17);
        }

        .error-container {
            display: none;
            margin: 10px 0;
        }

        .error-container.show {
            display: block;
            animation: fadeIn 0.3s ease-in-out;
        }

        .error {
            color: #e74c3c;
            background: #fdecea;
            padding: 12px;
            border-radius: 6px;
            font-size: 14px;
            text-align: center;
            animation: shakeError 0.5s ease-in-out;
        }

        .password-info {
            color: #666;
            font-size: 13px;
            text-align: center;
            margin: 10px 0;
            padding: 10px;
            background: #f8f9fa;
            border-radius: 6px;
        }

        @keyframes shakeError {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-10px); }
            75% { transform: translateX(10px); }
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        .container {
            text-align: center;
        }

        .logo {
            margin-bottom: 20px;
            animation: fadeIn 1s ease-in;
        }

        .logo img {
            max-width: 300px;
            height: auto;
        }

        @media (max-width: 480px) {
            form {
                padding: 30px 20px;
            }
            
            h2 {
                font-size: 24px;
            }
            
            input, .submit-btn {
                padding: 10px;
                font-size: 14px;
            }

            .logo img {
                max-width: 200px;
            }
        }
    </style>
</head>
<body>
    <form method="post" action="">
        <div class="container">
            <div class="logo">
                <img src="images/logo2.png" alt="Website Logo">
            </div>
            
            <h2>Reset Password</h2>
            <!-- <p class="description">Please enter your new password</p> -->
            
            <?php if($error_message): ?>
            <div class="error-container show">
                <div class="error"><?php echo $error_message; ?></div>
            </div>
            <?php endif; ?>
            
            <!-- <div class="password-info">
                Password must be at least 8 characters long
            </div> -->
            
            <div class="input-group">
                <input type="password" name="password" placeholder="New Password" required>
            </div>
            
            <div class="input-group">
                <input type="password" name="confirm_password" placeholder="Confirm Password" required>
            </div>
            
            <input type="submit" name="reset_password" value="Reset Password" class="submit-btn">
        </div>
    </form>
</body>
</html>