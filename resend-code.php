<?php
session_start();
require_once 'config.php';
require 'PHPMailer-master/src/Exception.php';
require 'PHPMailer-master/src/PHPMailer.php';
require 'PHPMailer-master/src/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

header('Content-Type: application/json');

if (!isset($_SESSION['reset_email'])) {
    http_response_code(400);
    echo json_encode(['error' => 'No email in session']);
    exit();
}

function generateVerificationCode($length = 6) {
    $digits = '0123456789';
    $code = '';
    for ($i = 0; $i < $length; $i++) {
        $code .= $digits[random_int(0, strlen($digits) - 1)];
    }
    return $code;
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
        $mail->Subject = 'New Password Reset Verification Code';
        $mail->Body    = "Your new verification code is: $verificationCode\n\nThis code will expire in 10 minutes.";

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Email sending failed: " . $mail->ErrorInfo);
        return false;
    }
}

try {
    $email = $_SESSION['reset_email'];
    $newVerificationCode = generateVerificationCode();
    
    if (sendVerificationEmail($email, $newVerificationCode)) {
        $_SESSION['reset_code'] = $newVerificationCode;
        $_SESSION['reset_time'] = time();
        
        echo json_encode(['success' => true]);
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to send email']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>


<!DOCTYPE html>
<html>
<head>
    <title>Verify Code</title>
    <style>
    </style>
</head>
<body>
    <form method="post" action="" id="verify-form">
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
    </form>

    <script>
        function startExpirationTimer() {
            let timeLeft = 600;
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

        const codeInput = document.querySelector('.verification-code-input');
        codeInput.addEventListener('input', (e) => {
            e.target.value = e.target.value.replace(/[^0-9]/g, '').slice(0, 6);
        });

        document.getElementById('resend-link').addEventListener('click', async (e) => {
            e.preventDefault();
            
            try {
                const response = await fetch('verify-code.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'resend_code=true'
                });
                
                const data = await response.json();
                
                if (data.success) {
                    startResendTimer();
                    // Show success message
                    const successDiv = document.createElement('div');
                    successDiv.className = 'error-container show';
                    successDiv.innerHTML = '<div class="success">New code has been sent!</div>';
                    document.querySelector('.input-group').before(successDiv);
                    
                    // Reset expiration timer
                    startExpirationTimer();
                    
                    setTimeout(() => {
                        successDiv.remove();
                    }, 5000);
                } else {
                    throw new Error(data.message || 'Failed to resend code');
                }
            } catch (error) {
                console.error('Error resending code:', error);
                
                // Show error message
                const errorDiv = document.createElement('div');
                errorDiv.className = 'error-container show';
                errorDiv.innerHTML = `<div class="error">${error.message || 'Failed to resend code. Please try again.'}</div>`;
                document.querySelector('.input-group').before(errorDiv);
                
                setTimeout(() => {
                    errorDiv.remove();
                }, 5000);
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