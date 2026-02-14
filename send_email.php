<?php
require 'vendor/autoload.php';
require 'email_config.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

function isBlockedEmailDomain($email) {
    $domain = strtolower((string)substr(strrchr($email, "@"), 1));
    if ($domain === '') {
        return true;
    }

    $blocked = array_filter(array_map('trim', explode(',', (string)EMAIL_BLOCKED_DOMAINS)));
    $blocked = array_map('strtolower', $blocked);

    return in_array($domain, $blocked, true);
}

function validateEmailForSending($email) {
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return false;
    }

    if (EMAIL_VALIDATE_STRICT && isBlockedEmailDomain($email)) {
        return false;
    }

    return true;
}

/**
 * Send email using PHPMailer with Gmail SMTP
 * 
 * @param string $to Recipient email address
 * @param string $subject Email subject
 * @param string $body Email body content
 * @param bool $isHTML Whether body is HTML (default: true)
 * @param string|null $fromEmail Optional sender email
 * @param string|null $fromName Optional sender name
 * @param string|null $replyToEmail Optional reply-to email
 * @param string|null $replyToName Optional reply-to name
 * @return array ['success' => bool, 'error' => string|null]
 */
function sendEmail($to, $subject, $body, $isHTML = true, $fromEmail = null, $fromName = null, $replyToEmail = null, $replyToName = null) {
    $mail = new PHPMailer(true);
    
    try {
        if (empty(SMTP_USERNAME) || empty(SMTP_PASSWORD)) {
            return ['success' => false, 'error' => 'SMTP credentials are not configured. Set SMTP_USERNAME and SMTP_PASSWORD in email_config.local.php or environment variables.'];
        }

        if (!validateEmailForSending($to)) {
            return ['success' => false, 'error' => 'Recipient email is invalid or blocked by policy.'];
        }

        if (!empty($replyToEmail) && !validateEmailForSending($replyToEmail)) {
            return ['success' => false, 'error' => 'Reply-to email is invalid or blocked by policy.'];
        }

        // Server settings
        $mail->isSMTP();
        $mail->Host = SMTP_HOST;
        $mail->SMTPAuth = SMTP_AUTH;
        $mail->Username = SMTP_USERNAME;
        $mail->Password = SMTP_PASSWORD;
        $mail->Timeout = SMTP_TIMEOUT;

        $secureMode = strtolower((string)SMTP_SECURE);
        if ($secureMode === 'ssl') {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        } elseif ($secureMode === 'none') {
            $mail->SMTPSecure = '';
            $mail->SMTPAutoTLS = false;
        } else {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        }
        $mail->Port = SMTP_PORT;

        // Recipients
        $mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
        $mail->Sender = SMTP_BOUNCE_EMAIL;

        if (!empty($fromEmail) && validateEmailForSending($fromEmail)) {
            $mail->addCustomHeader('X-Original-Sender', $fromEmail);
        }

        if (!empty($replyToEmail)) {
            $mail->addReplyTo($replyToEmail, $replyToName ?: $replyToEmail);
        }

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
 * @param string $senderEmail Sender's email
 * @param string $senderName Sender's display name
 * @param string $subject Email subject
 * @param string $body Email body
 * @param int|null $postId Related post ID (optional)
 * @return array ['success' => bool, 'error' => string|null]
 */
function sendReplyEmail($conn, $senderId, $recipientId, $recipientEmail, $senderEmail, $senderName, $subject, $body, $postId = null) {
    // Send the email
    $result = sendEmail(
        $recipientEmail,
        $subject,
        $body,
        true,
        $senderEmail,
        $senderName,
        $senderEmail,
        $senderName
    );
    
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
