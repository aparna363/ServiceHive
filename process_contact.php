<?php
session_start();
require_once 'dbconnect.php'; // Ensure this path is correct

// Check if the form was submitted via POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // --- Retrieve and Sanitize Form Data ---
    // Use filter_input for better security and validation
    $name = filter_input(INPUT_POST, 'name', FILTER_SANITIZE_SPECIAL_CHARS);
    $email = filter_input(INPUT_POST, 'email', FILTER_VALIDATE_EMAIL); // Validate email format
    $phone = filter_input(INPUT_POST, 'phone', FILTER_SANITIZE_SPECIAL_CHARS); // Sanitize phone
    $subject = filter_input(INPUT_POST, 'subject', FILTER_SANITIZE_SPECIAL_CHARS);
    $message = filter_input(INPUT_POST, 'message', FILTER_SANITIZE_SPECIAL_CHARS);

    // --- Basic Validation ---
    $errors = [];
    if (empty($name)) {
        $errors[] = "Full Name is required.";
    }
    if (empty($email)) {
        // filter_input already validated the format, check if it's empty or invalid
        $errors[] = "A valid Email Address is required.";
    }
    if (empty($subject)) {
        $errors[] = "Subject is required.";
    }
    if (empty($message)) {
        $errors[] = "Message is required.";
    }

    // --- Process Data if No Errors ---
    if (empty($errors)) {
        try {
            $conn->begin_transaction();
            
            // 1. Insert into contact_messages table
            $sql = "INSERT INTO contact_messages (name, email, phone, subject, message) VALUES (?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);

            if ($stmt === false) {
                throw new Exception("Database prepare failed: " . $conn->error);
            }

            $stmt->bind_param("sssss", $name, $email, $phone, $subject, $message);
            
            if (!$stmt->execute()) {
                throw new Exception("Database execute failed: " . $stmt->error);
            }
            
            $contact_id = $stmt->insert_id;
            $stmt->close();
            
            // 2. Create a guest user or find existing user by email
            $user_id = null;
            $user_stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
            $user_stmt->bind_param("s", $email);
            $user_stmt->execute();
            $user_result = $user_stmt->get_result();
            
            if ($user_result->num_rows > 0) {
                // User exists, use their ID
                $user_id = $user_result->fetch_assoc()['id'];
            } else {
                // Create a temporary user for this contact
                $temp_username = explode('@', $email)[0] . '_' . substr(md5(time()), 0, 6);
                $temp_password = password_hash(bin2hex(random_bytes(8)), PASSWORD_DEFAULT);
                
                $create_user = $conn->prepare("INSERT INTO users (username, email, password, role, status) VALUES (?, ?, ?, 'user', 'approved')");
                $create_user->bind_param("sss", $temp_username, $email, $temp_password);
                
                if (!$create_user->execute()) {
                    throw new Exception("Failed to create temporary user: " . $create_user->error);
                }
                
                $user_id = $create_user->insert_id;
                $create_user->close();
            }
            
            $user_stmt->close();
            
            // 3. Create a support ticket
            $ticket_sql = "INSERT INTO support_tickets (user_id, subject, message, status) VALUES (?, ?, ?, 'Open')";
            $ticket_stmt = $conn->prepare($ticket_sql);
            $ticket_stmt->bind_param("iss", $user_id, $subject, $message);
            
            if (!$ticket_stmt->execute()) {
                throw new Exception("Failed to create support ticket: " . $ticket_stmt->error);
            }
            
            $ticket_stmt->close();
            
            // Commit transaction
            $conn->commit();
            
            // Success
            $_SESSION['message'] = "Thank you for contacting us! Your message has been sent successfully.";

        } catch (Exception $e) {
            // Rollback transaction on error
            $conn->rollback();
            
            // Log the error
            error_log("Error processing contact form: " . $e->getMessage());
            $_SESSION['error'] = "Sorry, there was an error sending your message. Please try again later.";
        }

    } else {
        // Store validation errors in session to display on the contact page
        $_SESSION['error'] = "Please correct the following errors: <br>" . implode("<br>", $errors);
        // Optional: Store submitted data to repopulate the form
        $_SESSION['form_data'] = $_POST;
    }

    // Redirect back to the contact page
    header("Location: contact.php");
    exit();

} else {
    // If accessed directly without POST, redirect to contact page or show an error
    $_SESSION['error'] = "Invalid access method.";
    header("Location: contact.php");
    exit();
}

// Close the database connection (optional, PHP usually handles this at script end)
// $conn->close();
?> 