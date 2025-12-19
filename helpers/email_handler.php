<?php
// email_handler.php
// Centralized file for all SMTP configuration and email sending logic.

// Assuming PHPMailer is installed via Composer in the root directory
require __DIR__ . '/../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// =================================================================================
// NOTE: TEMPORARY EMAIL BYPASS FLAG HAS BEEN REMOVED FOR PRODUCTION READINESS.
// =================================================================================


/**
 * Sends a structured email using PHPMailer and dynamic database settings.
 *
 * @param object $pdo PDO database connection object.
 * @param string $recipient_email The email address of the recipient.
 * @param string $subject The subject line of the email.
 * @param string $body The plain text content of the email.
 * @param string|null $html_body Optional HTML content (defaults to plain text if null).
 * @return string 'sent', 'disabled', or 'failed'.
 */
function send_email($pdo, $recipient_email, $subject, $body, $html_body = null) {
    
    // --- 1. FETCH EMAIL SETTINGS ---
    $settings = [];
    try {
        $stmt_settings = $pdo->query("
            SELECT smtp_host, smtp_port, smtp_username, smtp_password, email_sender_name, enable_email_notifications
            FROM tbl_general_settings 
            WHERE id = 1
        ");
        $settings = $stmt_settings->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("EMAIL ERROR: Failed to fetch SMTP settings: " . $e->getMessage());
        return 'failed'; // Database error
    }

    // --- 2. CHECK STATUS ---
    
    // ⭐ DISABLED CHECK - Returns specific code 'disabled' ⭐
    if (!isset($settings['enable_email_notifications']) || $settings['enable_email_notifications'] != 1) {
        error_log("EMAIL: Notifications are disabled in settings. Email skipped.");
        return 'disabled';
    }

    if (!class_exists('PHPMailer\PHPMailer\PHPMailer')) {
        error_log("EMAIL FATAL: PHPMailer class not found. Check autoloader/composer.");
        return 'failed';
    }
    
    // --- Defense against empty sender (as seen in previous debugging) ---
    $smtp_user = $settings['smtp_username'] ?? '';
    $sender_name = $settings['email_sender_name'] ?? 'System Notifications'; 
    if (empty($smtp_user)) {
        error_log("EMAIL FAILED: Cannot set sender address. smtp_username is empty in settings.");
        return 'failed';
    }


    // --- 3. CONFIGURE AND SEND EMAIL ---
    $mail = new PHPMailer(true);
    
    try {
        // Server settings
        $mail->isSMTP();
        $mail->Host       = $settings['smtp_host'];
        $mail->SMTPAuth   = true;
        $mail->Username   = $smtp_user; // Use the sanitized variable
        $mail->Password   = $settings['smtp_password'];
        $mail->Port       = $settings['smtp_port'];

        // Dynamic encryption based on Port
        if ($settings['smtp_port'] == 465) {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        } elseif ($settings['smtp_port'] == 587) {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        } else {
            $mail->SMTPSecure = false;
            $mail->SMTPAutoTLS = false;
        }

        // Recipients
        // Uses the sanitized variable $smtp_user
        $mail->setFrom($smtp_user, $sender_name); 
        $mail->addAddress($recipient_email);

        // Content
        $mail->isHTML($html_body !== null); 
        $mail->Subject = $subject;
        $mail->Body    = $html_body ?? $body;
        $mail->AltBody = $body; 

        $mail->send();
        return 'sent'; // <-- Success
    } catch (Exception $e) {
        error_log("EMAIL FAILED: Mailer Error to {$recipient_email}: {$mail->ErrorInfo}");
        return 'failed'; // <-- SMTP Failure
    }
}