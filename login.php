<?php
session_start();
require_once 'dbconnect.php';

$error_message = '';
$success_message = '';

// Handle login form submission

// Modify the login verification section in your existing code
if(isset($_POST['login'])) {
    $email = filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);
    
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_message = "Invalid email format!";
    } else {
        // Prepare statement to prevent SQL injection
        $stmt = $conn->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();

        if($result->num_rows > 0) {
            $user = $result->fetch_assoc();
            error_log("Retrieved user data: " . print_r($user, true));
            error_log("User role: " . $user['role']);
            
            $password = $_POST['password'];
            
            // First check if account is active
            if (!$user['is_active']) {
                $_SESSION['show_deactivated_modal'] = true;
                logLoginAttempt($conn, $user['id'], $email, 'failed', 'Account deactivated');
                $show_modal = true;
                
            }
            // Then check email verification status
            else if(!$user['email_verified']) {
                $error_message = "Please verify your email before logging in. Check your inbox for the verification link.";
                logLoginAttempt($conn, $user['id'], $email, 'failed', 'Email not verified');
            }
            // Finally verify password
            else if(password_verify($password, $user['password'])) {
                error_log("Password verified successfully");
                error_log("Role before switch: " . $user['role']);
                
                // Set session variables
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['email'] = $user['email'];
                $_SESSION['role'] = $user['role'];
                
                // Set session security measures
                $_SESSION['login_time'] = time();
                $_SESSION['ip_address'] = $_SERVER['REMOTE_ADDR'];
                session_regenerate_id(true);

                // Log successful login
                logLoginAttempt($conn, $user['id'], $email, 'success');

                // Redirect based on role
                switch(strtolower($user['role'])) {
                    case 'admin':
                        error_log("Admin case matched");
                        header("Location: admin.php");
                        exit();
                    case 'service_provider':
                        header("Location: provider_dashboard.php");
                        exit();
                    default:
                        header("Location: index.php");
                        exit();
                }
            } else {
                $error_message = "Invalid password!";
                logLoginAttempt($conn, $user['id'], $email, 'failed', 'Invalid password');
            }
        } else {
            $error_message = "User not found!";
            logLoginAttempt($conn, null, $email, 'failed', 'User not found');
        }
        $stmt->close();
    }
}

// Function to log login attempts
function logLoginAttempt($conn, $user_id, $email, $status, $notes = '') {
    try {
        $stmt = $conn->prepare("INSERT INTO login_logs (user_id, email, ip_address, status, notes) VALUES (?, ?, ?, ?, ?)");
        if (!$stmt) {
            throw new Exception("Prepare failed: " . $conn->error);
        }

        $ip = $_SERVER['REMOTE_ADDR'];
        $stmt->bind_param("issss", $user_id, $email, $ip, $status, $notes);

        if (!$stmt->execute()) {
            throw new Exception("Execute failed: " . $stmt->error);
        }

        $stmt->close();
    } catch (Exception $e) {
        error_log("Login log error: " . $e->getMessage());
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - ServiceHive</title>
    <style>
        :root {
            --primary-color: rgb(239, 114, 36);
            --primary-hover: rgb(220, 95, 17);
            --error-color: #e74c3c;
            --success-color: #2ecc71;
        }

        body {
            background: linear-gradient(rgba(0,0,0,0.5), rgba(0,0,0,0.5)), url('images/login.jpg');
            background-repeat: no-repeat;
            background-size: cover;
            background-position: center;
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

        .container {
            text-align: center;
        }

        .logo {
            margin-bottom: 30px;
            animation: fadeIn 1s ease-in;
        }

        .logo img {
            max-width: 300px;
            height: auto;
        }

        h2 {
            color: #333;
            text-align: center;
            margin-bottom: 30px;
            font-size: 28px;
            font-weight: 600;
        }

        .input-group {
            position: relative;
            margin-bottom: 20px;
        }

        input {
            width: 100%;
            padding: 12px;
            border: 2px solid #eee;
            border-radius: 8px;
            font-size: 16px;
            transition: all 0.3s ease;
            box-sizing: border-box;
            outline: none;
        }

        input:focus {
            border-color: var(--primary-color);
        }

        .validation-icon {
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            display: none;
        }

        .input-group.valid .validation-icon.valid {
            display: block;
            color: var(--success-color);
        }

        .input-group.invalid .validation-icon.invalid {
            display: block;
            color: var(--error-color);
        }

        .feedback {
            display: none;
            color: var(--error-color);
            font-size: 12px;
            margin-top: 5px;
            text-align: left;
        }

        .feedback.show {
            display: block;
            animation: fadeIn 0.3s ease-in-out;
        }

        .submit-btn {
            background: var(--primary-color);
            color: white;
            border: none;
            padding: 14px;
            cursor: pointer;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            width: 100%;
            transition: background 0.3s ease;
            margin-top: 10px;
        }

        .submit-btn:hover {
            background: var(--primary-hover);
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
            color: var(--error-color);
            background: #fdecea;
            padding: 12px;
            border-radius: 6px;
            font-size: 14px;
            text-align: center;
            animation: shakeError 0.5s ease-in-out;
        }

        .success-message {
            color: var(--success-color);
            background: #e8f8f5;
            padding: 12px;
            border-radius: 6px;
            margin-bottom: 15px;
            text-align: center;
            animation: fadeIn 0.3s ease-in-out;
        }

        .verification-notice {
            background: #fff3cd;
            color: #856404;
            padding: 10px;
            border-radius: 6px;
            margin-bottom: 15px;
            font-size: 14px;
            text-align: center;
        }

        
p {
    text-align: center;
    margin-top: 20px;
    color: #666;
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

        @keyframes shakeError {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-10px); }
            75% { transform: translateX(10px); }
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
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
                max-width: 150px;
            }
        }
        .modal {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0, 0, 0, 0.5);
    z-index: 1000;
    animation: fadeIn 0.3s ease-in-out;
}

.modal-content {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    background-color: white;
    padding: 30px;
    border-radius: 12px;
    text-align: center;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15);
    max-width: 400px;
    width: 90%;
}

.modal-icon {
    margin-bottom: 20px;
}

.modal h2 {
    color: #333;
    margin-bottom: 10px;
    font-size: 24px;
}

.modal p {
    color: #666;
    margin-bottom: 20px;
    font-size: 16px;
}

.modal-button {
    background-color: #6c5ce7;
    color: white;
    border: none;
    padding: 12px 30px;
    border-radius: 6px;
    font-size: 16px;
    cursor: pointer;
    transition: background-color 0.3s ease;
}

.modal-button:hover {
    background-color: #5b4bc4;
}

@keyframes fadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
}
    </style>
</head>
<body>
    <form method="post" action="" id="loginForm">
        <div class="container">
            <div class="logo">
                <img src="images/logo2.png" alt="ServiceHive Logo">
            </div>
            
            <?php if($error_message): ?>
            <div class="error-container show">
                <div class="error"><?php echo htmlspecialchars($error_message); ?></div>
            </div>
            <?php endif; ?>

            <?php if($success_message): ?>
            <div class="success-message">
                <?php echo htmlspecialchars($success_message); ?>
            </div>
            <?php endif; ?>
            
            <div class="input-group">
                <input type="email" name="email" id="email" placeholder="Email" required>
                <div class="validation-icon valid">✓</div>
                <div class="validation-icon invalid">✗</div>
                <div class="feedback" id="emailFeedback"></div>
            </div>
            
            <div class="input-group">
                <input type="password" name="password" id="password" placeholder="Password" required>
                <div class="feedback" id="passwordFeedback"></div>
            </div>
            
            <input type="submit" name="login" value="Login" class="submit-btn">
            
            <div class="links">
                <a href="forgot password.php"style="color:grey;">Forgot Password?</a>
                <p>Don't have an account? <a href="signup.php">Register here</a></p>
            </div>
        </div>
    </form>
    <div id="deactivatedModal" class="modal">
    <div class="modal-content">
        <div class="modal-icon">
            <svg viewBox="0 0 24 24" width="48" height="48">
                <circle cx="12" cy="12" r="11" fill="none" stroke="#ff6b6b" stroke-width="2"/>
                <path d="M6 6 L18 18 M6 18 L18 6" stroke="#ff6b6b" stroke-width="2"/>
            </svg>
        </div>
        <h2>Account Deactivated</h2>
        <p>Your account has been deactivated. Please contact the administrator.</p>
        <button class="modal-button" onclick="closeModal()">OK</button>
    </div>
</div>
    <script>
        const form = document.getElementById('loginForm');
        const emailInput = document.getElementById('email');
        const passwordInput = document.getElementById('password');
        const emailFeedback = document.getElementById('emailFeedback');
        const passwordFeedback = document.getElementById('passwordFeedback');

        // Email validation function
        const validateEmail = (email) => {
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            const isValid = emailRegex.test(email);
            
            const inputGroup = emailInput.closest('.input-group');
            inputGroup.classList.remove('valid', 'invalid');
            inputGroup.classList.add(isValid ? 'valid' : 'invalid');
            
            return isValid;
        };

        // Password validation function
        const validatePassword = (password) => {
            const minLength = password.length >= 8;
            const hasUpperCase = /[A-Z]/.test(password);
            const hasLowerCase = /[a-z]/.test(password);
            const hasNumber = /[0-9]/.test(password);
            
            return {
                isValid: minLength && hasUpperCase && hasLowerCase && hasNumber,
                requirements: {
                    minLength,
                    hasUpperCase,
                    hasLowerCase,
                    hasNumber
                }
            };
        };

        // Real-time email validation
        emailInput.addEventListener('input', () => {
            validateEmail(emailInput.value);
            emailFeedback.textContent = '';
            emailFeedback.classList.remove('show');
            emailInput.style.borderColor = '';
        });

        // Real-time password validation
        passwordInput.addEventListener('input', () => {
            passwordFeedback.textContent = '';
            passwordFeedback.classList.remove('show');
            passwordInput.style.borderColor = '';
        });

        // Form submission validation
        form.addEventListener('submit', (e) => {
            const emailValid = validateEmail(emailInput.value);
            const passwordValid = validatePassword(passwordInput.value).isValid;
            
            if (!emailValid || !passwordValid) {
                e.preventDefault();
                
                if (!emailValid) {
                    emailInput.style.borderColor = 'var(--error-color)';
                    emailFeedback.textContent = 'Please enter a valid email address';
                    emailFeedback.classList.add('show');
                }
                
                if (!passwordValid) {
                    passwordInput.style.borderColor = 'var(--error-color)';
                    passwordFeedback.textContent = 'Password must be at least 8 characters with uppercase, lowercase, and numbers';
                    passwordFeedback.classList.add('show');
                }
            }
        });

        // Handle verification success message
        document.addEventListener('DOMContentLoaded', function() {
            const urlParams = new URLSearchParams(window.location.search);
            if(urlParams.get('verified') === 'true') {
                const successMessage = document.createElement('div');
                successMessage.className = 'success-message';
                successMessage.textContent = 'Email verified successfully! You can now login.';
                document.querySelector('.container').insertBefore(successMessage, document.querySelector('.input-group'));
            }
        });
        function showDeactivatedModal() {
    document.getElementById('deactivatedModal').style.display = 'block';
    document.body.style.overflow = 'hidden'; // Prevent scrolling when modal is open
}

function closeModal() {
    document.getElementById('deactivatedModal').style.display = 'none';
    document.body.style.overflow = 'auto'; // Restore scrolling
}


// Check for deactivated account error and show modal
document.addEventListener('DOMContentLoaded', function() {
    <?php if(isset($show_modal) && $show_modal === true): ?>
    showDeactivatedModal();
    <?php endif; ?>
});

// Close modal when clicking outside
window.onclick = function(event) {
    const modal = document.getElementById('deactivatedModal');
    if (event.target === modal) {
        closeModal();
    }
}

    </script>
    
</body>
</html>