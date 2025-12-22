<?php
/**
 * helpers/email_handler.php
 * Centralized logic for the Global Email Queue.
 */

// 1. Load Composer (Ensure path is correct)
require_once __DIR__ . '/../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

/**
 * 1. QUEUE LOGIC: Adds an email to the database queue.
 */
function queueEmail(PDO $pdo, int $user_id, string $recipient_email, string $subject, string $body, string $type = 'GENERAL', string $token = null): bool
{
    $sql = "INSERT INTO tbl_pending_emails (user_id, recipient_email, subject, body, email_type, token, is_sent, attempted_at) 
            VALUES (?, ?, ?, ?, ?, ?, 0, NOW())";
    try {
        $stmt = $pdo->prepare($sql);
        return $stmt->execute([$user_id, $recipient_email, $subject, $body, $type, $token]);
    } catch (PDOException $e) {
        error_log("EMAIL QUEUE ERROR: " . $e->getMessage());
        return false;
    }
}

/**
 * 2. BATCH PROCESSOR: Sends pending emails from the queue.
 */
function processEmailQueue(PDO $pdo, int $limit = 5) {
    $settings = getSMTPSettings($pdo);
    if (!$settings || $settings['enable_email_notifications'] != 1) return 'disabled';

    // LIMIT requires explicit integer binding in PDO
    $sql = "SELECT * FROM tbl_pending_emails WHERE is_sent = 0 ORDER BY attempted_at ASC LIMIT :limit";
    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
    $stmt->execute();
    $queue = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($queue)) return 'empty';

    $mail = new PHPMailer(true);
    try {
        configureSMTP($mail, $settings);
    } catch (Exception $e) {
        return 'error';
    }

    foreach ($queue as $email) {
        // FIXED: Only pass 2 arguments to match definition below
        if (sendSingleEmail($mail, $email)) {
            $pdo->prepare("UPDATE tbl_pending_emails SET is_sent = 1, error_message = NULL, last_attempt = NOW() WHERE id = ?")
                ->execute([$email['id']]);
        } else {
            $pdo->prepare("UPDATE tbl_pending_emails SET is_sent = 2, error_message = ?, last_attempt = NOW() WHERE id = ?")
                ->execute([$mail->ErrorInfo, $email['id']]);
        }
        usleep(200000); 
    }

    return 'processed';
}

/**
 * 3. IMMEDIATE SENDER: Forces a specific email to send NOW.
 */
function sendImmediateEmail(PDO $pdo, int $queue_id): string {
    $stmt = $pdo->prepare("SELECT * FROM tbl_pending_emails WHERE id = ?");
    $stmt->execute([$queue_id]);
    $email = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$email) return 'not_found';

    $settings = getSMTPSettings($pdo);
    if (!$settings || $settings['enable_email_notifications'] != 1) return 'disabled';

    $mail = new PHPMailer(true);
    try {
        configureSMTP($mail, $settings);
        // FIXED: Only pass 2 arguments
        if (sendSingleEmail($mail, $email)) {
            $pdo->prepare("UPDATE tbl_pending_emails SET is_sent = 1, error_message = NULL, last_attempt = NOW() WHERE id = ?")
                ->execute([$queue_id]);
            return 'sent';
        } else {
            $pdo->prepare("UPDATE tbl_pending_emails SET is_sent = 2, error_message = ?, last_attempt = NOW() WHERE id = ?")
                ->execute([$mail->ErrorInfo, $queue_id]);
            return 'failed';
        }
    } catch (Exception $e) {
        return 'failed';
    }
}

// --- INTERNAL HELPERS ---

function getSMTPSettings(PDO $pdo) {
    $stmt = $pdo->query("SELECT * FROM tbl_general_settings WHERE id = 1");
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function configureSMTP(PHPMailer $mail, array $settings) {
    $mail->isSMTP();
    $mail->Host       = $settings['smtp_host'];
    $mail->SMTPAuth   = true;
    $mail->Username   = $settings['smtp_username'];
    $mail->Password   = $settings['smtp_password'];
    $mail->Port       = $settings['smtp_port'];
    $mail->setFrom($settings['smtp_username'], $settings['email_sender_name'] ?? 'System');
    $mail->Timeout    = 10; 

    if ($settings['smtp_port'] == 465) {
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
    } elseif ($settings['smtp_port'] == 587) {
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    }
}

// FIXED: Definition now matches the calls above
function sendSingleEmail(PHPMailer $mail, array $emailData): bool {
    try {
        $mail->clearAddresses();
        $mail->addAddress($emailData['recipient_email']);
        $mail->Subject = $emailData['subject'];
        $mail->isHTML(true);
        $mail->Body    = $emailData['body'];
        $mail->AltBody = strip_tags($emailData['body']);
        $mail->send();
        return true;
    } catch (Exception $e) {
        return false;
    }
}
?>