<?php
require 'vendor/autoload.php';
require 'email_config.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

/**
 * Send email using PHPMailer with Gmail SMTP
 * 
 * @param string $to Recipient email address
 * @param string $subject Email subject
 * @param string $body Email body content
 * @param bool $isHTML Whether body is HTML (default: true)
 * @return array ['success' => bool, 'error' => string|null]
 */
function sendEmail($to, $subject, $body, $isHTML = true) {
    $mail = new PHPMailer(true);
    
    try {
        // Server settings
        $mail->isSMTP();
        $mail->Host = SMTP_HOST;
        $mail->SMTPAuth = true;
        $mail->Username = SMTP_USERNAME;
        $mail->Password = SMTP_PASSWORD;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = SMTP_PORT;

        // Recipients
        $mail->setFrom(SMTP_USERNAME, SMTP_FROM_NAME);
        $mail->addAddress($to);

        // Content
        $mail->isHTML($isHTML);
        $mail->Subject = $subject;
        $mail->Body = $body;
        
        // Plain text alternative for non-HTML clients
        if ($isHTML) {
            $mail->AltBody = strip_tags($body);
        }

        $mail->send();
        return ['success' => true, 'error' => null];
        
    } catch (Exception $e) {
        return ['success' => false, 'error' => $mail->ErrorInfo];
    }
}

/**
 * Send reply email and log to database
 * 
 * @param mysqli $conn Database connection
 * @param int $senderId Sender's user ID
 * @param int $recipientId Recipient's user ID
 * @param string $recipientEmail Recipient's email
 * @param string $subject Email subject
 * @param string $body Email body
 * @param int|null $postId Related post ID (optional)
 * @return array ['success' => bool, 'error' => string|null]
 */
function sendReplyEmail($conn, $senderId, $recipientId, $recipientEmail, $subject, $body, $postId = null) {
    // Send the email
    $result = sendEmail($recipientEmail, $subject, $body);
    
    // Log to messages table
    $status = $result['success'] ? 'sent' : 'failed';
    
    $stmt = $conn->prepare("INSERT INTO messages (sender_id, recipient_id, recipient_email, post_id, subject, body, status) 
                            VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("iisisss", $senderId, $recipientId, $recipientEmail, $postId, $subject, $body, $status);
    $stmt->execute();
    $stmt->close();
    
    return $result;
}
?>
