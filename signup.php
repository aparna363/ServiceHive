<?php
session_start();

if (!isset($_SESSION['userType'])) {
    header("Location: select-type.php");
    exit();
}

require_once 'dbconnect.php';
require 'PHPMailer-master/src/Exception.php';
require 'PHPMailer-master/src/PHPMailer.php';
require 'PHPMailer-master/src/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

function sendVerificationEmail($email, $username, $verification_token) {
    try {
        $mail = new PHPMailer(true);
        
        // Server settings
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'aparnaprasad363@gmail.com';
        $mail->Password = 'wbnh wldc yeqo sqzi';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;
        
        // Recipients
        $mail->setFrom('aparnaprasad363@gmail.com', 'ServiceHive');
        $mail->addAddress($email);
        
        // Content
        $mail->isHTML(true);
        $mail->Subject = "Verify Your Email Address";
        
        $verification_link = "http://" . $_SERVER['HTTP_HOST'] . 
            dirname($_SERVER['PHP_SELF']) . 
            "/verify.php?token=" . urlencode($verification_token);
        
        $mail->Body = "
        <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;'>
            <h2>Welcome to ServiceHive, " . htmlspecialchars($username) . "!</h2>
            <p>Thank you for registering with ServiceHive. Please click the link below to verify your email address:</p>
            <p><a href='" . $verification_link . "'>" . $verification_link . "</a></p>
            <p>This link will expire in 24 hours.</p>
            <p>If you didn't create an account, you can safely ignore this email.</p>
            <br>
            <p>Best regards,</p>
            <p>The ServiceHive Team</p>
        </div>";
        
        $mail->AltBody = "Welcome to ServiceHive! Please verify your email by clicking: " . $verification_link;
        
        return $mail->send();
    } catch (Exception $e) {
        error_log("Email sending failed: " . $mail->ErrorInfo);
        return false;
    }
}

function createAdminUser($conn) {
    try {
        // Begin transaction
        $conn->begin_transaction();

        // Check if admin exists - Add error logging
        $check_admin = $conn->prepare("SELECT id FROM users WHERE email = ? AND role = 'admin'");
        if (!$check_admin) {
            error_log("Failed to prepare check admin statement: " . $conn->error);
            throw new Exception("Failed to prepare check admin statement: " . $conn->error);
        }

        $admin_email = "aparnaprasad363@gmail.com";
        if (!$check_admin->bind_param("s", $admin_email)) {
            error_log("Failed to bind parameter: " . $check_admin->error);
            throw new Exception("Failed to bind parameter: " . $check_admin->error);
        }

        if (!$check_admin->execute()) {
            error_log("Failed to execute check: " . $check_admin->error);
            throw new Exception("Failed to execute check: " . $check_admin->error);
        }

        $result = $check_admin->get_result();

        if ($result->num_rows == 0) {
            // Admin user details
            $admin_username = "aparna";
            $admin_password = "Admin@123";
            $admin_mobile = "9876543210";
            $admin_role = "admin";
            $hashed_password = password_hash($admin_password, PASSWORD_DEFAULT);
            
            // Generate verification token
            $verification_token = bin2hex(random_bytes(50));
            $token_expiry = date('Y-m-d H:i:s', strtotime('+24 hours'));
            
            // Modified insert statement with explicit column names
            $stmt = $conn->prepare(
                "INSERT INTO users (username, email, mobile, password, role, email_verified, verification_token, token_expiry, status) 
                VALUES (?, ?, ?, ?, ?, 1, ?, ?, 'approved')"
            );
            
            if (!$stmt) {
                error_log("Failed to prepare insert statement: " . $conn->error);
                throw new Exception("Failed to prepare insert statement: " . $conn->error);
            }

            // Bind parameters
            if (!$stmt->bind_param("sssssss", 
                $admin_username, 
                $admin_email, 
                $admin_mobile, 
                $hashed_password, 
                $admin_role,
                $verification_token,
                $token_expiry
            )) {
                error_log("Failed to bind parameters: " . $stmt->error);
                throw new Exception("Failed to bind parameters: " . $stmt->error);
            }

            // Execute insert with error logging
            if (!$stmt->execute()) {
                error_log("Failed to insert admin: " . $stmt->error);
                throw new Exception("Failed to insert admin: " . $stmt->error);
            }

            error_log("Admin user created successfully");
            $stmt->close();
        } else {
            error_log("Admin user already exists");
        }

        $check_admin->close();
        
        // Commit transaction
        $conn->commit();
        return true;

    } catch (Exception $e) {
        // Rollback transaction on error
        $conn->rollback();
        error_log("Error in admin creation process: " . $e->getMessage());
        return false;
    }
}

// Ensure admin user is created
createAdminUser($conn);

// Get categories for service provider signup
$categories = [];
if ($_SESSION['userType'] === 'professional') {
    $query = "SELECT category_id, category_name FROM tbl_categories WHERE is_active = TRUE ORDER BY category_name";
    $result = $conn->query($query);
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $categories[] = $row;
        }
    }
}

$error_message = "";

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['register'])) {
    $username = $_POST['username'];
    $email = $_POST['email'];
    $mobile = $_POST['mobile'];
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $category_id = isset($_POST['category']) ? $_POST['category'] : null;
    $business_name = isset($_POST['business_name']) ? $_POST['business_name'] : null;

    if ($password !== $confirm_password) {
        $error_message = "Passwords do not match.";
    } else {
        try {
            $conn->begin_transaction();

            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $verification_token = bin2hex(random_bytes(50));
            $token_expiry = date('Y-m-d H:i:s', strtotime('+24 hours'));
            $role = ($_SESSION['userType'] === 'professional') ? 'service_provider' : 'user';

            // Check if email exists
            $check_email = $conn->prepare("SELECT id FROM users WHERE email = ?");
            $check_email->bind_param("s", $email);
            $check_email->execute();
            if($check_email->get_result()->num_rows > 0) {
                throw new Exception("Email already registered");
            }
            $check_email->close();

            // Insert into users table
            $sql = "INSERT INTO users (username, email, mobile, password, verification_token, token_expiry, role) 
                   VALUES (?, ?, ?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            
            if (!$stmt) {
                throw new Exception("Prepare failed: " . $conn->error);
            }

            $stmt->bind_param("sssssss", 
                $username, 
                $email, 
                $mobile, 
                $hashed_password, 
                $verification_token, 
                $token_expiry, 
                $role
            );

            if (!$stmt->execute()) {
                throw new Exception("Error executing statement: " . $stmt->error);
            }

            $user_id = $stmt->insert_id;
            $stmt->close();

            // If service provider, insert into service_providers table
            if ($_SESSION['userType'] === 'professional') {
                $provider_sql = "INSERT INTO service_providers (user_id, business_name, category_id) VALUES (?, ?, ?)";
                $provider_stmt = $conn->prepare($provider_sql);
                
                if (!$provider_stmt) {
                    throw new Exception("Prepare failed for provider insert: " . $conn->error);
                }

                $provider_stmt->bind_param("isi", $user_id, $business_name, $category_id);
                
                if (!$provider_stmt->execute()) {
                    throw new Exception("Error executing provider statement: " . $provider_stmt->error);
                }
                
                $provider_stmt->close();
            }

            // Send verification email
            if (sendVerificationEmail($email, $username, $verification_token)) {
                $conn->commit();
                
                // Create success message for verification pending page
                $_SESSION['registration_email'] = $email;
                
                header("Location: verification-pending.php");
                exit();
            } else {
                throw new Exception("Failed to send verification email");
            }

        } catch (Exception $e) {
            $conn->rollback();
            $error_message = "Registration failed: " . $e->getMessage();
            error_log("Registration error: " . $e->getMessage());
        }
    }
}

?>

<!DOCTYPE html>
<html>
<head>
    <title>Sign Up - ServiceHive</title>
    <style>
    /* Your existing CSS styles */
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

    .logo1 {
        margin-bottom: 20px;
        animation: fadeIn 1s ease-in;
    }

    .logo1 img {
        max-width: 300px;
        height: auto;
    }

    .form-group {
        margin-bottom: 15px;
    }

    input[type="text"],
    input[type="email"],
    input[type="password"],
    select.category-select {
        width: 100%;
        padding: 10px;
        border: 1px solid #ddd;
        border-radius: 6px;
        font-size: 14px;
    }

    input:focus, select:focus {
        border-color: rgb(220, 95, 17);
        outline: none;
        box-shadow: 0 0 0 2px rgba(220, 95, 17, 0.2);
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
        transition: background 0.3s ease;
    }

    .submit-btn:hover {
        background: rgb(200, 85, 15);
    }

    .error {
        color: #e74c3c;
        padding: 8px;
        margin-bottom: 15px;
        font-size: 13px;
        background: #fdf0ed;
        border-radius: 4px;
    }

    .switch-form {
        margin-top: 15px;
        color: #666;
        font-size: 14px;
    }

    .switch-form a {
        color: rgb(220, 95, 17);
        text-decoration: none;
    }
    .category-select{
        color:grey;
        
    }


    @keyframes fadeIn {
        from { opacity: 0; transform: translateY(-20px); }
        to { opacity: 1; transform: translateY(0); }
    }

    @media (max-width: 480px) {
        .logo1 img {
            max-width: 200px;
        }
    }
    </style>
</head>
<body>
    <div class="container">
        <form method="post" action="">
            <div class="container3">
                <div class="logo1">
                    <img src="images/logo2.png" alt="Website Logo">
                </div>
                
                <?php if($error_message != ""): ?>
                    <div class="error"><?php echo $error_message; ?></div>
                <?php endif; ?>

                <div class="form-group">
                    <input type="text" name="username" placeholder="Username" required>
                </div>
                <div class="form-group">
                    <input type="email" name="email" placeholder="Email" required>
                </div>
                <div class="form-group">
                    <input type="text" name="mobile" placeholder="Mobile Number" required>
                </div>

                <?php if ($_SESSION['userType'] === 'professional'): ?>
                    <!-- <div class="form-group">
                        <input type="text" name="business_name" placeholder="Business Name" required>
                    </div> -->
                    <div class="form-group">
                        <select name="category" class="category-select" required>
                            <option value="">Select Service Category</option>
                            <?php foreach ($categories as $category): ?>
                                <option value="<?php echo $category['category_id']; ?>">
                                    <?php echo htmlspecialchars($category['category_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                <?php endif; ?>

                <div class="form-group">
                    <input type="password" name="password" placeholder="Password" required>
                </div>
                <div class="form-group">
                    <input type="password" name="confirm_password" placeholder="Confirm Password" required>
                </div>
                <input type="submit" name="register" value="Register" class="submit-btn">
                <div class="switch-form">
                    <p>Already have an account? <a href="login.php">Login here</a></p>
                </div>
            </div>
        </form>
    </div>

    
        <script>

            // Get form elements
const form = document.querySelector('form');
const usernameInput = document.querySelector('input[name="username"]');
const emailInput = document.querySelector('input[name="email"]');
const passwordInput = document.querySelector('input[name="password"]');
const confirmPasswordInput = document.querySelector('input[name="confirm_password"]');

// Create feedback elements
const createFeedbackElement = () => {
    const div = document.createElement('div');
    div.style.fontSize = '12px';
    div.style.marginTop = '4px';
    div.style.textAlign = 'left';
    div.style.transition = 'all 0.3s ease';
    return div;
};

// Create and insert feedback elements
const usernameFeedback = createFeedbackElement();
const emailFeedback = createFeedbackElement();
const passwordFeedback = createFeedbackElement();
const confirmPasswordFeedback = createFeedbackElement();

// Insert feedback elements after each input
const insertFeedback = (input, feedback) => {
    input.parentNode.insertBefore(feedback, input.nextSibling);
};

insertFeedback(usernameInput, usernameFeedback);
insertFeedback(emailInput, emailFeedback);
insertFeedback(passwordInput, passwordFeedback);
insertFeedback(confirmPasswordInput, confirmPasswordFeedback);

// Validation functions
const validateUsername = (username) => {
    const minLength = username.length >= 3;
    const validChars = /^[a-zA-Z0-9_]+$/.test(username);
    return {
        isValid: minLength && validChars,
        requirements: {
            minLength,
            validChars
        }
    };
};

const validateEmail = (email) => {
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return emailRegex.test(email);
};

const mobileInput = document.querySelector('input[name="mobile"]');

const mobileFeedback = createFeedbackElement();
const roleFeedback = createFeedbackElement();

insertFeedback(mobileInput, mobileFeedback);


mobileInput.addEventListener('input', () => {
    const isValid = /^[6-9]\d{9}$/.test(mobileInput.value);
    updateFeedback(
        mobileFeedback,
        isValid,
        isValid ? 'Valid mobile number' : 'Must start with 6-9 and be 10 digits'
    );
});




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

const validateConfirmPassword = (password, confirmPassword) => {
    return password === confirmPassword;
};

// Update UI based on validation
const updateFeedback = (element, isValid, message, requirements = null) => {
    element.style.color = isValid ? '#2ecc71' : '#e74c3c';
    
    
    
    if (requirements) {
        let feedbackHtml = `${message}<br>`;
        if ('validChars' in requirements) {  // Check for username-specific property
            // Username requirements
            feedbackHtml += `${requirements.minLength ? '✓' : '✗'} At least 3 characters<br>`;
            feedbackHtml += `${requirements.validChars ? '✓' : '✗'} Only letters, numbers, and underscores`;
        } else if ('hasUpperCase' in requirements) {  // Check for password-specific property
            // Password requirements
            feedbackHtml += `${requirements.minLength ? '✓' : '✗'} At least 8 characters<br>`;
            feedbackHtml += `${requirements.hasUpperCase ? '✓' : '✗'} One uppercase letter<br>`;
            feedbackHtml += `${requirements.hasLowerCase ? '✓' : '✗'} One lowercase letter<br>`;
            feedbackHtml += `${requirements.hasNumber ? '✓' : '✗'} One number`;
        }
        element.innerHTML = feedbackHtml;
    } else {
        element.textContent = message;
    }
};
// Add event listeners for live validation
usernameInput.addEventListener('input', () => {
    const validation = validateUsername(usernameInput.value);
    updateFeedback(
        usernameFeedback,
        validation.isValid,
        'Username requirements:',
        validation.requirements
    );
});

emailInput.addEventListener('input', () => {
    const isValid = validateEmail(emailInput.value);
    updateFeedback(
        emailFeedback,
        isValid,
        isValid ? 'Valid email format' : 'Please enter a valid email address'
    );
});

passwordInput.addEventListener('input', () => {
    const validation = validatePassword(passwordInput.value);
    updateFeedback(
        passwordFeedback,
        validation.isValid,
        'Password requirements:',
        validation.requirements
    );
    
    // Also check confirm password if it has a value
    if (confirmPasswordInput.value) {
        const isMatch = validateConfirmPassword(passwordInput.value, confirmPasswordInput.value);
        updateFeedback(
            confirmPasswordFeedback,
            isMatch,
            isMatch ? 'Passwords match' : 'Passwords do not match'
        );
    }
});

confirmPasswordInput.addEventListener('input', () => {
    const isMatch = validateConfirmPassword(passwordInput.value, confirmPasswordInput.value);
    updateFeedback(
        confirmPasswordFeedback,
        isMatch,
        isMatch ? 'Passwords match' : 'Passwords do not match'
    );
});

// Form submission validation
form.addEventListener('submit', (e) => {
    const usernameValid = validateUsername(usernameInput.value).isValid;
    const emailValid = validateEmail(emailInput.value);
    const passwordValid = validatePassword(passwordInput.value).isValid;
    const confirmPasswordValid = validateConfirmPassword(
        passwordInput.value,
        confirmPasswordInput.value
    );
    
    if (!usernameValid || !emailValid || !passwordValid || !confirmPasswordValid) {
        e.preventDefault();
        
        const inputs = [
            { element: usernameInput, valid: usernameValid },
            { element: emailInput, valid: emailValid },
            { element: passwordInput, valid: passwordValid },
            { element: confirmPasswordInput, valid: confirmPasswordValid }
        ];
        
        inputs.forEach(({ element, valid }) => {
            if (!valid) {
                element.style.borderColor = '#e74c3c';
            }
        });
    }
});

// Reset border colors on input focus
const inputs = [usernameInput, emailInput, passwordInput, confirmPasswordInput];
inputs.forEach(input => {
    input.addEventListener('focus', () => {
        input.style.borderColor = '';
    });
});


            </script>

          
    </body>
</html>