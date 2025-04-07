<?php
// Include PHPMailer classes directly
require_once __DIR__ . '/PHPMailer-master/src/Exception.php';
require_once __DIR__ . '/PHPMailer-master/src/PHPMailer.php';
require_once __DIR__ . '/PHPMailer-master/src/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Email configuration settings (Consider moving these to a config file or environment variables)
$EMAIL_HOST = 'smtp.gmail.com';
$EMAIL_PORT = 587;
$EMAIL_USERNAME = 'aparnaprasad363@gmail.com';
$EMAIL_PASSWORD = 'wbnh wldc yeqo sqzi'; // Use an App Password for Gmail
$EMAIL_FROM = 'aparnaprasad363@gmail.com';
$EMAIL_FROM_NAME = 'ServiceHive Support';

/**
 * Sends an email using PHPMailer and logs failures to a database queue.
 *
 * @param mysqli $conn Database connection object.
 * @param string $recipientEmail The email address of the recipient.
 * @param string $recipientName The name of the recipient.
 * @param string $subject The subject of the email.
 * @param string $message The HTML or plain text body of the email.
 * @param bool $isHtml Whether the message body is HTML. Defaults to false.
 * @return bool True if the email was sent successfully, false otherwise.
 */
function sendServiceHiveEmail($conn, $recipientEmail, $recipientName, $subject, $message, $isHtml = false) {
    global $EMAIL_HOST, $EMAIL_PORT, $EMAIL_USERNAME, $EMAIL_PASSWORD, $EMAIL_FROM, $EMAIL_FROM_NAME;

    $mail = new PHPMailer(true);

    try {
        // Server settings
        $mail->isSMTP();
        $mail->Host       = $EMAIL_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = $EMAIL_USERNAME;
        $mail->Password   = $EMAIL_PASSWORD;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = $EMAIL_PORT;

        // Recipients
        $mail->setFrom($EMAIL_FROM, $EMAIL_FROM_NAME);
        $mail->addAddress($recipientEmail, $recipientName);

        // Content
        $mail->isHTML($isHtml);
        $mail->Subject = $subject;
        $mail->Body    = $message;
        if (!$isHtml) {
            $mail->AltBody = strip_tags($message); // Provide a plain text alternative
        }

        $mail->send();
        error_log("Email sent successfully to $recipientEmail with subject: $subject");
        return true;
    } catch (Exception $e) {
        error_log("Email sending failed to $recipientEmail: " . $mail->ErrorInfo);

        // Store the email in a database table for later sending (optional fallback)
        // Ensure 'email_queue' table exists if you use this
        /*
        if ($conn) {
            $stmt = $conn->prepare("INSERT INTO email_queue (recipient, subject, message, created_at) VALUES (?, ?, ?, NOW())");
            if ($stmt) {
                $stmt->bind_param("sss", $recipientEmail, $subject, $message);
                $stmt->execute();
                $stmt->close();
            } else {
                error_log("Failed to prepare statement for email queue: " . $conn->error);
            }
        } else {
            error_log("Database connection not available for email queue.");
        }
        */
        return false;
    }
}
?> 