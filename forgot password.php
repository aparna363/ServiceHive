<?php
session_start();
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'PHPMailer-master/src/Exception.php';
require 'PHPMailer-master/src/PHPMailer.php';
require 'PHPMailer-master/src/SMTP.php';

// Initialize messages
$error_message = '';
$success_message = '';

// Generate CSRF token if not exists
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Handle form submission
if(isset($_POST['send_code'])) {
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $error_message = "Invalid security token. Please try again.";
    } else {
        $email = filter_var($_POST['email'] ?? '', FILTER_SANITIZE_EMAIL);
        
        if(empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error_message = "Please enter a valid email address.";
        } else {
            try {
                // Clear any existing reset sessions
                unset($_SESSION['reset_code']);
                unset($_SESSION['reset_email']);
                unset($_SESSION['reset_time']);
                
                $verificationCode = generateVerificationCode();
                if(sendVerificationEmail($email, $verificationCode)) {
                    // Use consistent session variable names with verify-code.php
                    $_SESSION['reset_code'] = $verificationCode;
                    $_SESSION['reset_email'] = $email;
                    $_SESSION['reset_time'] = time();
                    
                    // Regenerate session ID for security
                    session_regenerate_id(true);
                    
                    header("Location: verify-code.php");
                    exit();
                } else {
                    $error_message = "Failed to send verification code. Please try again.";
                }
            } catch (Exception $e) {
                $error_message = "An error occurred. Please try again later.";
                error_log("Error in forgot password: " . $e->getMessage());
            }
        }
    }
}

function generateVerificationCode($length = 6) {
    return sprintf('%06d', random_int(0, 999999));
}

function sendVerificationEmail($recipientEmail, $verificationCode) {
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
        $mail->addAddress($recipientEmail);
        
        $mail->Subject = 'Password Reset Verification Code';
        $mail->Body    = "Your verification code is: $verificationCode\n\nThis code will expire in 10 minutes.";

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Email sending failed: " . $mail->ErrorInfo);
        return false;
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Forgot Password</title>
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

        .success {
            color: #2ecc71;
            background: #e8f8f5;
            padding: 12px;
            border-radius: 6px;
            font-size: 14px;
            text-align: center;
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

        .back-to-login {
            text-align: center;
            margin-top: 20px;
        }

        a {
            color: rgb(239, 114, 36);
            text-decoration: none;
            font-weight: 500;
            transition: color 0.3s ease;
        }

        a:hover {
            color: rgb(220, 95, 17);
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
            <!-- Add CSRF token here -->
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
            
            <div class="logo">
                <img src="images/logo2.png" alt="Website Logo">
            </div>
            
            <h2>Forgot Password</h2>
            <p class="description">Enter your email address and we'll send you a verification code to reset your password.</p>
            
            <?php if($error_message): ?>
            <div class="error-container show">
                <div class="error"><?php echo htmlspecialchars($error_message); ?></div>
            </div>
            <?php endif; ?>
            
            <?php if($success_message): ?>
            <div class="error-container show">
                <div class="success"><?php echo htmlspecialchars($success_message); ?></div>
            </div>
            <?php endif; ?>
            
            <div class="input-group">
                <input type="email" name="email" placeholder="Enter your email address" required>
            </div>
            
            <input type="submit" name="send_code" value="Send Verification Code" class="submit-btn">
            
            <div class="back-to-login">
                <a href="login.php">Back to Login</a>
            </div>
        </div>
    </form>

    <script>
        const form = document.querySelector('form');
        const emailInput = document.querySelector('input[name="email"]');

        const validateEmail = (email) => {
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            return emailRegex.test(email);
        };

        form.addEventListener('submit', (e) => {
            if (!validateEmail(emailInput.value)) {
                e.preventDefault();
                const errorContainer = document.querySelector('.error-container') || document.createElement('div');
                errorContainer.className = 'error-container show';
                errorContainer.innerHTML = '<div class="error">Please enter a valid email address</div>';
                
                if (!document.querySelector('.error-container')) {
                    form.insertBefore(errorContainer, form.querySelector('.input-group'));
                }
                
                emailInput.style.borderColor = '#e74c3c';
            }
        });

        emailInput.addEventListener('input', () => {
            emailInput.style.borderColor = '#eee';
            const errorContainer = document.querySelector('.error-container');
            if (errorContainer) {
                errorContainer.classList.remove('show');
            }
        });
    </script>
</body>
</html>