<?php
session_start();
require_once 'dbconnect.php';

$error_message = '';
$success_message = '';

// Check if user has a reset code in session
if(!isset($_SESSION['reset_code']) || !isset($_SESSION['reset_email'])) {
    header("Location: forgot password.php");
    exit();
}

// Check if verification code has expired (10 minutes)
if(time() - $_SESSION['reset_time'] > 600) {
    unset($_SESSION['reset_code']);
    unset($_SESSION['reset_email']);
    unset($_SESSION['reset_time']);
    header("Location: forgot password.php");
    exit();
}

if(isset($_POST['verify_code'])) {
    $entered_code = $_POST['verification_code'];
    
    if($entered_code == $_SESSION['reset_code']) {
        // Code is correct, allow password reset
        $_SESSION['verified'] = true;
        header("Location: reset-password.php");
        exit();
    } else {
        $error_message = "Invalid verification code!";
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>serviceHive</title>
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
            border-color:rgb(239, 114, 36);;
        }

        .submit-btn {
            background:rgb(239, 114, 36);;
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

        .resend-code {
            text-align: center;
            margin-top: 15px;
            font-size: 14px;
        }

        .timer {
            color: #666;
            font-size: 14px;
            text-align: center;
            margin-top: 10px;
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

        .verification-code-input {
            letter-spacing: 8px;
            font-size: 20px;
            text-align: center;
            font-family: monospace;
        }

        .back-button {
            text-align: center;
            margin-top: 15px;
        }

        .back-button a {
            color: #666;
            text-decoration: none;
            font-size: 14px;
        }

        .back-button a:hover {
            color: rgb(239, 114, 36);
        }
        .success{
            color: green;
        }
    </style>
</head>
<body>
    <form method="post" action="">
        <div class="container">
            <div class="logo">
                <img src="images/logo2.png" alt="Website Logo">
            </div>
            
            <h2>Verify Code</h2>
            <p class="description">Please enter the verification code sent to<br><strong><?php echo htmlspecialchars($_SESSION['reset_email']); ?></strong></p>
            
            <?php if($error_message): ?>
            <div class="error-container show">
                <div class="error"><?php echo $error_message; ?></div>
            </div>
            <?php endif; ?>
            
            <div class="input-group">
                <input type="text" 
                       name="verification_code" 
                       class="verification-code-input" 
                       placeholder="Enter code" 
                       maxlength="6" 
                       pattern="[0-9]{6}" 
                       required>
            </div>
            
            <input type="submit" name="verify_code" value="Verify Code" class="submit-btn">
            
            <div class="timer">
                Code expires in: <span id="countdown">10:00</span>
            </div>
            
            <div class="resend-code">
                Didn't receive the code? 
                <a href="#" id="resend-link" style="display: none;">Resend Code</a>
                <span id="resend-timer">Wait <span id="resend-countdown">30</span>s to resend</span>
            </div>
            
            <div class="back-button">
                <a href="forgot password.php">← Back to Forgot Password</a>
            </div>
        </div>
    </form>

    <script>
        // Timer for code expiration
        function startExpirationTimer() {
            let timeLeft = 600; // 10 minutes in seconds
            const countdownElement = document.getElementById('countdown');
            
            const timer = setInterval(() => {
                const minutes = Math.floor(timeLeft / 60);
                const seconds = timeLeft % 60;
                
                countdownElement.textContent = `${minutes}:${seconds.toString().padStart(2, '0')}`;
                
                if (timeLeft <= 0) {
                    clearInterval(timer);
                    window.location.href = 'forgot password.php';
                }
                    timeLeft--;
            }, 1000);
        }
// Timer for resend button
        function startResendTimer() {
            let timeLeft = 30;
            const resendLink = document.getElementById('resend-link');
            const resendTimer = document.getElementById('resend-timer');
            const resendCountdown = document.getElementById('resend-countdown');
            
            resendLink.style.display = 'none';
            resendTimer.style.display = 'inline';
            
            const timer = setInterval(() => {
                resendCountdown.textContent = timeLeft;
                
                if (timeLeft <= 0) {
                    clearInterval(timer);
                    resendLink.style.display = 'inline';
                    resendTimer.style.display = 'none';
                }
                
                timeLeft--;
            }, 1000);
        }

        // Format verification code input
        const codeInput = document.querySelector('.verification-code-input');
        codeInput.addEventListener('input', (e) => {
            e.target.value = e.target.value.replace(/[^0-9]/g, '').slice(0, 6);
        });

        // Handle resend code
        document.getElementById('resend-link').addEventListener('click', async (e) => {
            e.preventDefault();
            
            try {
                const response = await fetch('resend-code.php', {
                    method: 'POST'
                });
                
                if (response.ok) {
                    startResendTimer();
                    // Show success message
                    const successDiv = document.createElement('div');
                    successDiv.className = 'error-container show';
                    successDiv.innerHTML = '<div class="success">New code has been sent!</div>';
                    document.querySelector('.input-group').before(successDiv);
                    
                    setTimeout(() => {
                        successDiv.remove();
                    }, 5000);
                }
            } catch (error) {
                console.error('Error resending code:', error);
            }
        });

        // Start timers when page loads
        document.addEventListener('DOMContentLoaded', () => {
            startExpirationTimer();
            startResendTimer();
        });
    </script>
</body>
</html>
