<?php 
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

require_once 'dbconnect.php';

// Fetch current user data
$user_id = $_SESSION['user_id'];
$stmt = $conn->prepare("SELECT username, email, mobile, address, city, state FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user_data = $result->fetch_assoc();

$error_message = "";
$success_message = "";

// Handle account deletion
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['delete_account'])) {
    // First, get user's current bookings
    $check_bookings = $conn->prepare("SELECT booking_id FROM bookings WHERE user_id = ? AND status IN ('pending', 'accepted')");
    $check_bookings->bind_param("i", $user_id);
    $check_bookings->execute();
    $active_bookings = $check_bookings->get_result();

    if ($active_bookings->num_rows > 0) {
        $error_message = "Cannot delete account. You have active bookings. Please cancel them first.";
    } else {
        // Start transaction
        $conn->begin_transaction();
        
        try {
            // Deactivate user account using correct column name 'active'
            $deactivate_sql = "UPDATE users SET is_active = 0 WHERE id = ?";
            $stmt = $conn->prepare($deactivate_sql);
            $stmt->bind_param("i", $user_id);
            
            if (!$stmt->execute()) {
                throw new Exception("Failed to deactivate account");
            }

            // Log the account deactivation
            $log_sql = "INSERT INTO login_logs (user_id, email, ip_address, status, notes) VALUES (?, ?, ?, 'ACCOUNT_DEACTIVATION', 'Account deactivated by user')";
            $log_stmt = $conn->prepare($log_sql);
            $ip = $_SERVER['REMOTE_ADDR'];
            $log_stmt->bind_param("iss", $user_id, $_SESSION['email'], $ip);
            
            if (!$log_stmt->execute()) {
                throw new Exception("Failed to log account deactivation");
            }

            // If everything is successful, commit the transaction
            $conn->commit();

            // Destroy session and redirect
            session_destroy();
            header("Location: login.php?deactivated=true");
            exit();

        } catch (Exception $e) {
            // If there's an error, rollback changes
            $conn->rollback();
            $error_message = "Error deactivating account: " . $e->getMessage();
        }
    }
}

// Handle profile update
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update'])) {
    $username = $_POST['username'];
    $email = $_POST['email'];
    $mobile = $_POST['mobile'];
    $address = $_POST['address'];
    $city = $_POST['city'];
    $state = $_POST['state'];
    $new_password = $_POST['new_password'];

    // Check if email is already in use by another user
    $email_check = $conn->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
    $email_check->bind_param("si", $email, $user_id);
    $email_check->execute();
    $email_result = $email_check->get_result();

    if ($email_result->num_rows > 0) {
        $error_message = "Email is already in use by another account.";
    } else {
        if (!empty($new_password)) {
            // Password update requested
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $sql = "UPDATE users SET username=?, email=?, mobile=?, address=?, city=?, state=?, password=? WHERE id=?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("sssssssi", $username, $email, $mobile, $address, $city, $state, $hashed_password, $user_id);
        } else {
            // Update without password change
            $sql = "UPDATE users SET username=?, email=?, mobile=?, address=?, city=?, state=? WHERE id=?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ssssssi", $username, $email, $mobile, $address, $city, $state, $user_id);
        }

        if ($stmt->execute()) {
            $success_message = "Profile updated successfully!";
            // Update session data
            $_SESSION['username'] = $username;
            // Update the displayed data
            $user_data = [
                'username' => $username,
                'email' => $email,
                'mobile' => $mobile,
                'address' => $address,
                'city' => $city,
                'state' => $state
            ];
        } else {
            $error_message = "Error updating profile: " . $stmt->error;
        }
    }
}

// Handle profile update
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update'])) {
    $username = $_POST['username'];
    $email = $_POST['email'];
    $mobile = $_POST['mobile'];
    $address = $_POST['address'];
    $city = $_POST['city'];
    $state = $_POST['state'];
    $new_password = $_POST['new_password'];

    // Check if email is already in use by another user
    $email_check = $conn->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
    $email_check->bind_param("si", $email, $user_id);
    $email_check->execute();
    $email_result = $email_check->get_result();

    if ($email_result->num_rows > 0) {
        $error_message = "Email is already in use by another account.";
    } else {
        if (!empty($new_password)) {
            // Password update requested
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $sql = "UPDATE users SET username=?, email=?, mobile=?, address=?, city=?, state=?, password=? WHERE id=?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("sssssssi", $username, $email, $mobile, $address, $city, $state, $hashed_password, $user_id);
        } else {
            // Update without password change
            $sql = "UPDATE users SET username=?, email=?, mobile=?, address=?, city=?, state=? WHERE id=?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ssssssi", $username, $email, $mobile, $address, $city, $state, $user_id);
        }

        if ($stmt->execute()) {
            $success_message = "Profile updated successfully!";
            // Update session data
            $_SESSION['username'] = $username;
            // Update the displayed data
            $user_data = [
                'username' => $username,
                'email' => $email,
                'mobile' => $mobile,
                'address' => $address,
                'city' => $city,
                'state' => $state
            ];
        } else {
            $error_message = "Error updating profile: " . $stmt->error;
        }
    }
}

// Kerala Districts array
$kerala_districts = [
    'ALP' => 'Alappuzha',
    'ERN' => 'Ernakulam',
    'IDK' => 'Idukki',
    'KNR' => 'Kannur',
    'KSR' => 'Kasaragod',
    'KLM' => 'Kollam',
    'KTM' => 'Kottayam',
    'KKD' => 'Kozhikode',
    'MLP' => 'Malappuram',
    'PKD' => 'Palakkad',
    'PTA' => 'Pathanamthitta',
    'TVM' => 'Thiruvananthapuram',
    'TSR' => 'Thrissur',
    'WYD' => 'Wayanad'
];
?>

<!DOCTYPE html>
<html>
<head>
    <title>Edit Profile</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        body {
            background-color: #f5f5f5;
            min-height: 100vh;
            display: flex;
            width: 100vw;
            overflow-x: hidden;
        }

        .container {
            display: flex;
            width: 100%;
            min-height: 100vh;
            background: white;
        }

        .sidebar {
            width: 280px;
            background: rgb(119, 35, 5);
            padding: 40px 30px;
            position: fixed;
            height: 100vh;
        }

        .main-content {
            flex: 1;
            padding: 40px;
            margin-left: 280px;
            background: white;
        }

        .sidebar-menu {
            list-style: none;
            margin-top: 40px;
        }

        .sidebar-menu li {
            margin-bottom: 20px;
        }

        .sidebar-menu a {
            color: white;
            text-decoration: none;
            display: flex;
            align-items: center;
            padding: 12px 20px;
            border-radius: 12px;
            transition: background 0.3s;
            font-size: 16px;
        }

        .sidebar-menu a:hover {
            background: rgba(255, 255, 255, 0.1);
        }

        .sidebar-menu .active {
            background: white;
            color: #ff6b35;
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 25px;
            max-width: 1200px;
            margin: 0 auto;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #333;
            font-size: 15px;
            font-weight: 500;
        }

        .form-group input,
        .form-group select {
            width: 100%;
            padding: 12px;
            border: 2px solid #e1e1e1;
            border-radius: 12px;
            font-size: 15px;
            transition: all 0.3s ease;
            background-color: #fff;
        }

        .form-group input:focus,
        .form-group select:focus {
            border-color: rgb(165, 51, 10);
            outline: none;
            box-shadow: 0 0 0 4px rgba(255, 107, 53, 0.1);
        }

        .button-group {
            display: flex;
            gap: 15px;
            margin-top: 30px;
            padding-bottom: 40px;
            grid-column: 1 / -1;
        }

        .btn {
            padding: 12px 24px;
            border-radius: 12px;
            border: none;
            font-size: 16px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s;
            min-width: 150px;
        }

        .btn-primary {
            background: rgb(23, 117, 6);
            color: white;
        }

        .btn-secondary {
            background: #f5f5f5;
            color: #666;
        }

        .btn-delete {
            background-color: #dc3545;
            color: white;
            margin-right: auto;
        }

        .btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }

        .profile-header {
            margin-bottom: 40px;
        }

        .profile-title {
            font-size: 28px;
            color: rgb(228, 108, 10);
            margin-bottom: 15px;
        }

        .error {
            color: #dc3545;
            padding: 12px;
            border-radius: 12px;
            background: #fde8e8;
            margin-bottom: 20px;
            font-size: 14px;
        }

        .success {
            color: #0f766e;
            padding: 12px;
            border-radius: 12px;
            background: #d1fae5;
            margin-bottom: 20px;
            font-size: 14px;
        }

        .feedback {
            margin-top: 6px;
            font-size: 13px;
            min-height: 18px;
        }

        /* Delete Modal Styles */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 1000;
        }

        .modal-content {
            position: relative;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: white;
            padding: 30px;
            border-radius: 12px;
            max-width: 400px;
            text-align: center;
        }

        .modal-title {
            color: #dc3545;
            margin-bottom: 20px;
            font-size: 24px;
        }

        .modal-text {
            margin-bottom: 20px;
            color: #666;
        }

        .modal-list {
            text-align: left;
            margin-bottom: 20px;
            color: #666;
            padding-left: 20px;
        }

        .modal-warning {
            margin-bottom: 20px;
            font-weight: bold;
            color: #333;
        }

        .modal-buttons {
            display: flex;
            justify-content: center;
            gap: 10px;
        }

        @media (max-width: 1200px) {
            .main-content {
                padding: 30px;
            }
            
            .form-grid {
                gap: 20px;
            }
        }

        @media (max-width: 992px) {
            .sidebar {
                width: 100%;
                height: auto;
                position: relative;
                padding: 20px;
            }

            .main-content {
                margin-left: 0;
                padding: 25px;
            }

            .container {
                flex-direction: column;
            }

            .form-grid {
                grid-template-columns: 1fr;
                gap: 20px;
            }

            .form-group {
                margin-bottom: 15px;
            }

            .button-group {
                margin-top: 25px;
                padding-bottom: 30px;
            }
        }

        @media (max-width: 576px) {
            .main-content {
                padding: 15px;
            }

            .button-group {
                flex-direction: column;
            }

            .btn {
                width: 100%;
                min-width: auto;
            }

            .profile-title {
                font-size: 24px;
            }

            .form-group label {
                font-size: 14px;
            }

            .form-group input,
            .form-group select {
                padding: 10px;
                font-size: 14px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="sidebar">
            <img src="images/logo2.png" alt="Logo" style="width: 200px; margin-bottom: auto;">
            <ul class="sidebar-menu">
                <li><a href="#" class="active">Edit Profile</a></li>
                <li><a href="#">Notifications</a></li>
                <li><a href="index.php">Home</a></li>
                <li><a href="#">Help</a></li>
            </ul>
        </div>

        <div class="main-content">
            <div class="profile-header">
                <h1 class="profile-title">Edit Profile</h1>
            </div>

            <?php if($error_message): ?>
                <div class="error"><?php echo $error_message; ?></div>
            <?php endif; ?>

            <?php if($success_message): ?>
                <div class="success"><?php echo $success_message; ?></div>
            <?php endif; ?>

            <form method="post" action="" id="edit-profile-form">
                <div class="form-grid">
                    <div class="form-group">
                        <label for="username">Username</label>
                        <input type="text" name="username" id="username" 
                               value="<?php echo htmlspecialchars($user_data['username']); ?>">
                        <div class="feedback"></div>
                    </div>

                    <div class="form-group">
                        <label for="email">Email</label>
                        
                        <input type="email" name="email" id="email" value="<?php echo htmlspecialchars($user_data['email']); ?>">
                        <div class="feedback"></div>
                    </div>

                    <div class="form-group">
                        <label for="mobile">Mobile</label>
                        <input type="text" name="mobile" id="mobile" 
                               value="<?php echo htmlspecialchars($user_data['mobile']); ?>">
                        <div class="feedback"></div>
                    </div>

                    <div class="form-group">
                        <label for="address">Address</label>
                        <input type="text" name="address" id="address" 
                               value="<?php echo htmlspecialchars($user_data['address']); ?>">
                        <div class="feedback"></div>
                    </div>

                    <div class="form-group">
                        <label for="city">City</label>
                        <input type="text" name="city" id="city" 
                               value="<?php echo htmlspecialchars($user_data['city']); ?>">
                        <div class="feedback"></div>
                    </div>

                    <div class="form-group">
                        <label for="state">District</label>
                        <select name="state" id="state">
                            <option value="">Select District</option>
                            <?php foreach($kerala_districts as $code => $name): ?>
                                <option value="<?php echo $code; ?>" 
                                    <?php echo ($user_data['state'] == $code) ? 'selected' : ''; ?>>
                                    <?php echo $name; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="feedback"></div>
                    </div>

                    <div class="form-group">
                        <label for="new_password">New Password</label>
                        <input type="password" name="new_password" id="new_password">
                        <div class="feedback"></div>
                    </div>

                    <div class="form-group">
                        <label for="confirm_password">Confirm New Password</label>
                        <input type="password" name="confirm_password" id="confirm_password">
                        <div class="feedback"></div>
                    </div>

                    <div class="button-group">
                        <button type="button" class="btn btn-secondary" onclick="window.location.href='index.php'">Cancel</button>
                        <button type="submit" name="update" class="btn btn-primary">Save Changes</button>
                        <button type="button" class="btn btn-delete" onclick="confirmDelete()">Delete Account</button>
                    </div>
                </div>
            </form>

            <!-- Delete Account Modal -->
            <div id="deleteModal" class="modal">
                <div class="modal-content">
                    <h2 class="modal-title">Delete Account</h2>
                    <p class="modal-text">Warning: This action cannot be undone. Your account will be permanently deactivated.</p>
                    <ul class="modal-list">
                        <li>All your active services will be cancelled</li>
                        <li>Your profile will be deactivated</li>
                        <li>You won't be able to log in with this account</li>
                    </ul>
                    <p class="modal-warning">Are you sure you want to proceed?</p>
                    <form method="post" action="">
                        <input type="hidden" name="delete_account" value="1">
                        <div class="modal-buttons">
                            <button type="button" class="btn btn-secondary" onclick="closeDeleteModal()">Cancel</button>
                            <button type="submit" class="btn btn-delete">Delete Account</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Form validation functions
        const validateUsername = (username) => {
            const minLength = username.length >= 3;
            const validChars = /^[a-zA-Z0-9_]+$/.test(username);
            return {
                isValid: minLength && validChars,
                message: !minLength ? 'Username must be at least 3 characters' :
                        !validChars ? 'Username can only contain letters, numbers, and underscores' : ''
            };
        };

        const validateEmail = (email) => {
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            return {
                isValid: emailRegex.test(email),
                message: 'Please enter a valid email address'
            };
        };

        const validateMobile = (mobile) => {
            const isValid = /^[6-9]\d{9}$/.test(mobile);
            return {
                isValid: isValid,
                message: 'Mobile number must start with 6-9 and be 10 digits'
            };
        };

        const validatePassword = (password) => {
            if (!password) return { isValid: true, message: '' }; // Empty password is valid (no change)
            
            const minLength = password.length >= 8;
            const hasUpperCase = /[A-Z]/.test(password);
            const hasLowerCase = /[a-z]/.test(password);
            const hasNumber = /[0-9]/.test(password);
            
            return {
                isValid: minLength && hasUpperCase && hasLowerCase && hasNumber,
                message: !minLength ? 'Password must be at least 8 characters' :
                        !hasUpperCase ? 'Password must contain an uppercase letter' :
                        !hasLowerCase ? 'Password must contain a lowercase letter' :
                        !hasNumber ? 'Password must contain a number' : ''
            };
        };

        const validateAddress = (address) => {
            return {
                isValid: address.trim().length >= 5,
                message: address.trim().length < 5 ? 'Address must be at least 5 characters long' : ''
            };
        };

        const validateCity = (city) => {
            return {
                isValid: /^[a-zA-Z\s]{2,}$/.test(city),
                message: 'City must contain only letters and spaces, and be at least 2 characters long'
            };
        };

        // Form elements
        const form = document.getElementById('edit-profile-form');
        const inputs = {
            username: document.getElementById('username'),
            email: document.getElementById('email'),
            mobile: document.getElementById('mobile'),
            address: document.getElementById('address'),
            city: document.getElementById('city'),
            state: document.getElementById('state'),
            new_password: document.getElementById('new_password'),
            confirm_password: document.getElementById('confirm_password')
        };

        // Show validation feedback
        const showFeedback = (input, isValid, message) => {
            const feedbackDiv = input.nextElementSibling;
            feedbackDiv.textContent = message;
            feedbackDiv.style.color = isValid ? '#2ecc71' : '#e74c3c';
            input.style.borderColor = isValid ? '#2ecc71' : '#e74c3c';
        };

        // Validate password match
        const validatePasswordMatch = (password, confirmPassword) => {
            if (!password) return { isValid: true, message: '' };
            return {
                isValid: password === confirmPassword,
                message: password !== confirmPassword ? 'Passwords do not match' : ''
            };
        };

        // Add input event listeners for real-time validation
        Object.entries(inputs).forEach(([key, input]) => {
            if (!input) return;

            input.addEventListener('input', () => {
                let result;
                switch (key) {
                    case 'username':
                        result = validateUsername(input.value);
                        break;
                    case 'email':
                        result = validateEmail(input.value);
                        break;
                    case 'mobile':
                        result = validateMobile(input.value);
                        break;
                    case 'address':
                        result = validateAddress(input.value);
                        break;
                    case 'city':
                        result = validateCity(input.value);
                        break;
                    case 'new_password':
                        result = validatePassword(input.value);
                        // Revalidate confirm password
                        if (inputs.confirm_password.value) {
                            const matchResult = validatePasswordMatch(input.value, inputs.confirm_password.value);
                            showFeedback(inputs.confirm_password, matchResult.isValid, matchResult.message);
                        }
                        break;
                    case 'confirm_password':
                        result = validatePasswordMatch(inputs.new_password.value, input.value);
                        break;
                }
                if (result) {
                    showFeedback(input, result.isValid, result.message);
                }
            });
        });

        // Form submission handler
        form.addEventListener('submit', (e) => {
            let isValid = true;
            
            // Validate all fields
            const validations = {
                username: validateUsername(inputs.username.value),
                email: validateEmail(inputs.email.value),
                mobile: validateMobile(inputs.mobile.value),
                address: validateAddress(inputs.address.value),
                city: validateCity(inputs.city.value)
            };

            // Show feedback for each field
            Object.entries(validations).forEach(([field, result]) => {
                showFeedback(inputs[field], result.isValid, result.message);
                isValid = isValid && result.isValid;
            });

            // Validate password if provided
            if (inputs.new_password.value) {
                const passwordResult = validatePassword(inputs.new_password.value);
                const passwordMatchResult = validatePasswordMatch(
                    inputs.new_password.value,
                    inputs.confirm_password.value
                );
                
                showFeedback(inputs.new_password, passwordResult.isValid, passwordResult.message);
                showFeedback(inputs.confirm_password, passwordMatchResult.isValid, passwordMatchResult.message);
                
                isValid = isValid && passwordResult.isValid && passwordMatchResult.isValid;
            }

            if (!isValid) {
                e.preventDefault();
            }
        });

        // Modal functions
        function confirmDelete() {
            document.getElementById('deleteModal').style.display = 'block';
            document.body.style.overflow = 'hidden';
        }

        function closeDeleteModal() {
            document.getElementById('deleteModal').style.display = 'none';
            document.body.style.overflow = 'auto';
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('deleteModal');
            if (event.target == modal) {
                closeDeleteModal();
            }
        }
    </script>
</body>
</html>