<?php
session_start();
require_once 'dbconnect.php';
require_once 'email_helper.php'; // Include the email helper

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

$admin_user_id = $_SESSION['user_id']; // Or fetch admin details if needed

// --- Handle Reply Submission ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['reply_ticket'])) {
    $ticket_id = filter_input(INPUT_POST, 'ticket_id', FILTER_VALIDATE_INT);
    $reply_message = filter_input(INPUT_POST, 'reply_message', FILTER_SANITIZE_SPECIAL_CHARS);
    $user_email = filter_input(INPUT_POST, 'user_email', FILTER_VALIDATE_EMAIL);
    $user_name = filter_input(INPUT_POST, 'user_name', FILTER_SANITIZE_SPECIAL_CHARS);
    $original_subject = filter_input(INPUT_POST, 'original_subject', FILTER_SANITIZE_SPECIAL_CHARS);

    // Debug information - add this to see what's being received
    error_log("Reply form data: " . 
              "ticket_id: $ticket_id, " . 
              "user_email: $user_email, " . 
              "user_name: $user_name, " . 
              "subject: $original_subject, " . 
              "message length: " . strlen($reply_message));

    if ($ticket_id && $reply_message && $user_email && $user_name && $original_subject) {
        $conn->begin_transaction();
        try {
            // 1. Send Email Reply
            $email_subject = "Re: " . $original_subject;
            $email_body = "Dear " . htmlspecialchars($user_name) . ",<br><br>";
            $email_body .= "Thank you for contacting ServiceHive support. Here is our response regarding your query:<br><br>";
            $email_body .= "--------------------<br>";
            $email_body .= nl2br(htmlspecialchars($reply_message)); // Format reply
            $email_body .= "<br>--------------------<br><br>";
            $email_body .= "If you have further questions, please reply to this email or submit a new support request.<br><br>";
            $email_body .= "Best regards,<br>The ServiceHive Support Team";

            // Use the helper function (pass $conn for potential DB logging in helper)
            $email_sent = sendServiceHiveEmail($conn, $user_email, $user_name, $email_subject, $email_body, true); // Send as HTML

            if ($email_sent) {
                // 2. Update Ticket Status in Database
                $stmt = $conn->prepare("UPDATE support_tickets SET status = 'Replied', admin_reply = ?, replied_at = NOW(), updated_at = NOW() WHERE ticket_id = ?");
                $stmt->bind_param("si", $reply_message, $ticket_id);
                $stmt->execute();

                if ($stmt->affected_rows > 0) {
                    $conn->commit();
                    $_SESSION['message'] = "Reply sent successfully and ticket updated.";
                } else {
                    $conn->rollback(); // Rollback if DB update failed
                    $_SESSION['error'] = "Email sent, but failed to update ticket status in the database.";
                    error_log("Failed to update support ticket ID $ticket_id after sending email reply.");
                }
                $stmt->close();
            } else {
                $conn->rollback(); // Rollback if email failed
                $_SESSION['error'] = "Failed to send email reply. Please check email configuration and try again.";
            }

        } catch (Exception $e) {
            $conn->rollback();
            $_SESSION['error'] = "An error occurred: " . $e->getMessage();
            error_log("Error processing support reply for ticket ID $ticket_id: " . $e->getMessage());
        }

    } else {
        $_SESSION['error'] = "Invalid data submitted for reply. Please ensure all fields are filled correctly.";
        // Add more detailed error information
        error_log("Missing reply form data: " . 
                 "ticket_id: " . ($ticket_id ? "OK" : "MISSING") . ", " . 
                 "user_email: " . ($user_email ? "OK" : "MISSING") . ", " . 
                 "user_name: " . ($user_name ? "OK" : "MISSING") . ", " . 
                 "subject: " . ($original_subject ? "OK" : "MISSING") . ", " . 
                 "message: " . ($reply_message ? "OK" : "MISSING"));
    }

    // Redirect to avoid form resubmission
    header("Location: support_tickets.php");
    exit();
}

// --- Fetch Support Tickets ---
$stmt = $conn->prepare("
    SELECT st.*, u.username as user_name, u.email as user_email, u.role /* Fetch user role */
    FROM support_tickets st
    JOIN users u ON st.user_id = u.id
    ORDER BY u.role, st.status = 'Open' DESC, st.created_at DESC /* Optional: Group by role */
");
$stmt->execute();
$tickets_result = $stmt->get_result();
$all_tickets = $tickets_result->fetch_all(MYSQLI_ASSOC); // Fetch all tickets first
$stmt->close();

// --- Filter Tickets by Role ---
$provider_tickets = [];
$user_tickets = [];
foreach ($all_tickets as $ticket) {
    if ($ticket['role'] === 'service_provider') {
        $provider_tickets[] = $ticket;
    } else {
        // Assume any other role (like 'user' or even 'admin' if they can submit) goes here
        $user_tickets[] = $ticket;
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Support Tickets - Admin Dashboard</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <!-- You can link your existing admin CSS or add specific styles -->
    <style>
        /* Basic styles - Adapt from your admin.php styles */
        body { font-family: 'Arial', sans-serif; background-color: #f4f6f9; margin: 0; }
        .container { display: flex; min-height: 100vh; }
        .sidebar { width: 250px; background-color: rgb(104, 35, 3); color: white; position: fixed; height: 100vh; left: 0; top: 0; padding-top: 20px; }
        .sidebar-menu a { display: block; color: white; padding: 12px 20px; text-decoration: none; transition: background-color 0.3s; }
        .sidebar-menu a:hover, .sidebar-menu a.active { background-color: rgb(171, 46, 8); }
        .sidebar-menu i { margin-right: 10px; }
        .main-content { margin-left: 250px; padding: 20px; width: calc(100% - 250px); }
        .header { background-color: white; padding: 15px 20px; margin-bottom: 20px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); border-radius: 5px; }
        .header h1 { margin: 0; font-size: 24px; }

        table { width: 100%; border-collapse: collapse; background-color: white; box-shadow: 0 2px 4px rgba(0,0,0,0.1); border-radius: 5px; overflow: hidden; }
        th, td { padding: 12px 15px; text-align: left; border-bottom: 1px solid #eee; }
        th { background-color: #f8f9fa; font-weight: 600; }
        tr:last-child td { border-bottom: none; }
        tr:hover { background-color: #f1f1f1; }

        .status-badge { padding: 4px 8px; border-radius: 12px; font-size: 12px; font-weight: 500; text-transform: capitalize; }
        .status-Open { background-color: #ffc107; color: #333; }
        .status-Replied { background-color: #17a2b8; color: white; }
        .status-Closed { background-color: #6c757d; color: white; }

        .btn { padding: 6px 12px; border: none; border-radius: 4px; cursor: pointer; font-size: 14px; text-decoration: none; display: inline-block; margin-right: 5px; }
        .btn-primary { background-color: #007bff; color: white; }
        .btn-info { background-color: #17a2b8; color: white; }
        .btn-secondary { background-color: #6c757d; color: white; }
        .btn:hover { opacity: 0.9; }

        /* Modal Styles */
        .modal { display: none; position: fixed; z-index: 1050; left: 0; top: 0; width: 100%; height: 100%; overflow: auto; background-color: rgba(0,0,0,0.5); }
        .modal-content { background-color: #fefefe; margin: 10% auto; padding: 25px; border: 1px solid #888; width: 80%; max-width: 700px; border-radius: 8px; position: relative; box-shadow: 0 5px 15px rgba(0,0,0,0.2); }
        .modal-close { color: #aaa; position: absolute; top: 10px; right: 20px; font-size: 28px; font-weight: bold; cursor: pointer; }
        .modal-close:hover, .modal-close:focus { color: black; text-decoration: none; }
        .modal h2 { margin-top: 0; border-bottom: 1px solid #eee; padding-bottom: 10px; margin-bottom: 20px; }
        .modal label { display: block; margin-bottom: 8px; font-weight: bold; }
        .modal textarea { width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 4px; min-height: 150px; box-sizing: border-box; margin-bottom: 15px; }
        .modal .message-display { background-color: #f9f9f9; border: 1px solid #eee; padding: 15px; margin-bottom: 20px; border-radius: 4px; max-height: 200px; overflow-y: auto; }
        .modal .message-display p { margin: 0 0 10px 0; }
        .modal .message-display strong { display: block; margin-bottom: 5px; color: #555; }

        /* Flash Messages */
        .alert { padding: 15px; margin-bottom: 20px; border: 1px solid transparent; border-radius: 4px; }
        .alert-success { color: #155724; background-color: #d4edda; border-color: #c3e6cb; }
        .alert-danger { color: #721c24; background-color: #f8d7da; border-color: #f5c6cb; }
    </style>
</head>
<body>
    <div class="container">
        <!-- Sidebar (Include your admin sidebar structure here) -->
        <div class="sidebar">
             <div class="logo-container" style="text-align: center; padding: 20px 0;">
                <img src="images/logo2.png" alt="ServiceHive Logo" style="width: 180px;">
            </div>
            <div class="sidebar-menu">
                <a href="admin.php#dashboard"><i class="fas fa-home"></i> Dashboard</a>
                <a href="index.php"><i class="fas fa-globe"></i> Home</a>
                <a href="admin.php#providers"><i class="fas fa-user-tie"></i> Service Providers</a>
                <a href="category-management.php"><i class="fas fa-cogs"></i> Service Management</a>
                <a href="admin.php#bookings"><i class="fas fa-calendar-check"></i> Bookings</a>
                <a href="admin.php#users"><i class="fas fa-users"></i> Users</a>
                <a href="support_tickets.php" class="active"><i class="fas fa-inbox"></i> Support Messages</a>
                <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
            </div>
        </div>

        <!-- Main Content -->
        <div class="main-content">
             <div class="header">
                 <h1>Support Tickets</h1>
             </div>

             <!-- Flash Messages -->
             <?php if (isset($_SESSION['message'])): ?>
                 <div class="alert alert-success"><?php echo $_SESSION['message']; unset($_SESSION['message']); ?></div>
             <?php endif; ?>
             <?php if (isset($_SESSION['error'])): ?>
                 <div class="alert alert-danger"><?php echo $_SESSION['error']; unset($_SESSION['error']); ?></div>
             <?php endif; ?>

             <!-- Service Provider Tickets Table -->
             <h2 style="margin-top: 30px; margin-bottom: 15px; border-bottom: 1px solid #ccc; padding-bottom: 5px;">Service Provider Tickets</h2>
             <table>
                 <thead>
                     <tr>
                         <th>ID</th>
                         <th>Provider</th>
                         <th>Subject</th>
                         <th>Status</th>
                         <th>Received</th>
                         <th>Actions</th>
                     </tr>
                 </thead>
                 <tbody>
                     <?php if (!empty($provider_tickets)): ?>
                         <?php foreach ($provider_tickets as $ticket): ?>
                             <tr>
                                 <td><?php echo $ticket['ticket_id']; ?></td>
                                 <td><?php echo htmlspecialchars($ticket['user_name']); ?> (<?php echo htmlspecialchars($ticket['user_email']); ?>)</td>
                                 <td><?php echo htmlspecialchars($ticket['subject']); ?></td>
                                 <td><span class="status-badge status-<?php echo $ticket['status']; ?>"><?php echo $ticket['status']; ?></span></td>
                                 <td><?php echo date('M d, Y H:i', strtotime($ticket['created_at'])); ?></td>
                                 <td>
                                     <!-- The same modal function works for both tables -->
                                     <button class="btn btn-info btn-sm" onclick="openReplyModal(<?php echo htmlspecialchars(json_encode($ticket), ENT_QUOTES, 'UTF-8'); ?>)">
                                         <i class="fas fa-eye"></i> View / Reply
                                     </button>
                                     <?php if ($ticket['status'] !== 'Closed'): ?>
                                     <!-- <button class="btn btn-secondary btn-sm" onclick="closeTicket(<?php echo $ticket['ticket_id']; ?>)">Close</button> -->
                                     <?php endif; ?>
                                 </td>
                             </tr>
                         <?php endforeach; ?>
                     <?php else: ?>
                         <tr>
                             <td colspan="6" style="text-align: center; padding: 20px;">No support tickets found from service providers.</td>
                         </tr>
                     <?php endif; ?>
                 </tbody>
             </table>

             <!-- Regular User Tickets Table -->
             <h2 style="margin-top: 30px; margin-bottom: 15px; border-bottom: 1px solid #ccc; padding-bottom: 5px;">User Tickets</h2>
             <table>
                 <thead>
                     <tr>
                         <th>ID</th>
                         <th>User</th>
                         <th>Subject</th>
                         <th>Status</th>
                         <th>Received</th>
                         <th>Actions</th>
                     </tr>
                 </thead>
                 <tbody>
                     <?php if (!empty($user_tickets)): ?>
                         <?php foreach ($user_tickets as $ticket): ?>
                             <tr>
                                 <td><?php echo $ticket['ticket_id']; ?></td>
                                 <td><?php echo htmlspecialchars($ticket['user_name']); ?> (<?php echo htmlspecialchars($ticket['user_email']); ?>)</td>
                                 <td><?php echo htmlspecialchars($ticket['subject']); ?></td>
                                 <td><span class="status-badge status-<?php echo $ticket['status']; ?>"><?php echo $ticket['status']; ?></span></td>
                                 <td><?php echo date('M d, Y H:i', strtotime($ticket['created_at'])); ?></td>
                                 <td>
                                     <!-- The same modal function works for both tables -->
                                     <button class="btn btn-info btn-sm" onclick="openReplyModal(<?php echo htmlspecialchars(json_encode($ticket), ENT_QUOTES, 'UTF-8'); ?>)">
                                         <i class="fas fa-eye"></i> View / Reply
                                     </button>
                                     <?php if ($ticket['status'] !== 'Closed'): ?>
                                     <!-- <button class="btn btn-secondary btn-sm" onclick="closeTicket(<?php echo $ticket['ticket_id']; ?>)">Close</button> -->
                                     <?php endif; ?>
                                 </td>
                             </tr>
                         <?php endforeach; ?>
                     <?php else: ?>
                         <tr>
                             <td colspan="6" style="text-align: center; padding: 20px;">No support tickets found from regular users.</td>
                         </tr>
                     <?php endif; ?>
                 </tbody>
             </table>

         </div><!-- /main-content -->
     </div><!-- /container -->

    <!-- Reply Modal -->
    <div id="replyModal" class="modal">
        <div class="modal-content">
            <span class="modal-close" onclick="closeReplyModal()">&times;</span>
            <h2>Reply to Support Ticket <span id="modal_ticket_id"></span></h2>

            <div class="message-info">
                <p><strong>From:</strong> <span id="modal_user_name"></span> (<span id="modal_user_email"></span>)</p>
                <p><strong>Subject:</strong> <span id="modal_subject"></span></p>
                <p><strong>Received:</strong> <span id="modal_received_date"></span></p>
            </div>

            <hr>

            <label>Original Message:</label>
            <div class="message-display" id="modal_original_message"></div>

            <label>Previous Reply (if any):</label>
            <div class="message-display" id="modal_admin_reply" style="display: none;"></div>

            <form id="replyForm" method="POST" action="support_tickets.php">
                <input type="hidden" name="ticket_id" id="form_ticket_id">
                <input type="hidden" name="user_email" id="form_user_email">
                <input type="hidden" name="user_name" id="form_user_name">
                <input type="hidden" name="original_subject" id="form_original_subject">

                <div id="reply_section">
                    <label for="reply_message">Your Reply:</label>
                    <textarea name="reply_message" id="reply_message" required></textarea>
                    <button type="submit" name="reply_ticket" class="btn btn-primary"><i class="fas fa-paper-plane"></i> Send Reply via Email</button>
                </div>
                <div id="already_replied_message" style="display: none; margin-top: 15px; color: #17a2b8;">
                    <i class="fas fa-info-circle"></i> This ticket has already been replied to. Sending a new reply will overwrite the previous one in the system log and send a new email.
                </div>
            </form>
        </div>
    </div>

    <script>
        const modal = document.getElementById('replyModal');

        function openReplyModal(ticketData) {
            console.log("Opening modal with ticket data:", ticketData); // Debug log
            
            // Populate modal fields
            document.getElementById('modal_ticket_id').textContent = '#' + ticketData.ticket_id;
            document.getElementById('modal_user_name').textContent = ticketData.user_name;
            document.getElementById('modal_user_email').textContent = ticketData.user_email;
            document.getElementById('modal_subject').textContent = ticketData.subject;
            document.getElementById('modal_received_date').textContent = new Date(ticketData.created_at).toLocaleString();
            
            // Display original message safely
            const originalMessageDiv = document.getElementById('modal_original_message');
            originalMessageDiv.textContent = ticketData.message;

            // Populate form hidden fields
            document.getElementById('form_ticket_id').value = ticketData.ticket_id;
            document.getElementById('form_user_email').value = ticketData.user_email;
            document.getElementById('form_user_name').value = ticketData.user_name;
            document.getElementById('form_original_subject').value = ticketData.subject;

            // Handle display of previous reply
            const adminReplyDiv = document.getElementById('modal_admin_reply');
            const alreadyRepliedMsg = document.getElementById('already_replied_message');
            if (ticketData.admin_reply && ticketData.status === 'Replied') {
                adminReplyDiv.textContent = ticketData.admin_reply;
                adminReplyDiv.style.display = 'block';
                alreadyRepliedMsg.style.display = 'block'; // Show warning
            } else {
                adminReplyDiv.style.display = 'none';
                adminReplyDiv.textContent = '';
                alreadyRepliedMsg.style.display = 'none'; // Hide warning
            }

            // Clear previous reply text
            document.getElementById('reply_message').value = '';

            // Show modal
            modal.style.display = 'block';
            
            // Debug check - verify form fields are populated
            console.log("Form fields populated:", {
                ticket_id: document.getElementById('form_ticket_id').value,
                user_email: document.getElementById('form_user_email').value,
                user_name: document.getElementById('form_user_name').value,
                subject: document.getElementById('form_original_subject').value
            });
        }

        function closeReplyModal() {
            modal.style.display = 'none';
        }

        // Close modal if clicked outside of it
        window.onclick = function(event) {
            if (event.target == modal) {
                closeReplyModal();
            }
        }
        
        // Add form validation before submission
        document.getElementById('replyForm').addEventListener('submit', function(e) {
            const replyMessage = document.getElementById('reply_message').value.trim();
            if (!replyMessage) {
                e.preventDefault();
                alert('Please enter a reply message before sending.');
                return false;
            }
            
            // Check hidden fields are populated
            const ticketId = document.getElementById('form_ticket_id').value;
            const userEmail = document.getElementById('form_user_email').value;
            const userName = document.getElementById('form_user_name').value;
            const subject = document.getElementById('form_original_subject').value;
            
            if (!ticketId || !userEmail || !userName || !subject) {
                e.preventDefault();
                console.error("Missing required hidden fields:", {
                    ticket_id: ticketId,
                    user_email: userEmail,
                    user_name: userName,
                    subject: subject
                });
                alert('Error: Some required information is missing. Please try reopening the reply form.');
                return false;
            }
            
            return true;
        });
    </script>

</body>
</html> 