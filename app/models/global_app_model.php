<?php
// app/models/global_app_model.php

date_default_timezone_set('Asia/Manila');

// 1. DATABASE CONNECTION
require_once __DIR__ . '/../../db_connection.php';

// 2. EMAIL HANDLER (Now integrated)
// Adjust path if email_handler.php is in a different location relative to app/models/
require_once __DIR__ . '/../../helpers/email_handler.php'; 

// --------------------------------------------------------------------------
// --- HELPER: SEND EMAIL ALERT (Wrapper for your handler) ---
// --------------------------------------------------------------------------
function send_email_alert($pdo, $to_email, $subject, $html_body) {
    if (empty($to_email)) {
        return false;
    }
    
    // Convert HTML body to plain text for the non-HTML fallback
    $plain_body = strip_tags($html_body);

    // Call your centralized email_handler function
    // send_email($pdo, $recipient, $subject, $plain_body, $html_body)
    $status = send_email($pdo, $to_email, $subject, $plain_body, $html_body);
    
    // Return true only if actually sent
    return ($status === 'sent');
}

// --------------------------------------------------------------------------
// --- 1. SEND NOTIFICATION (Global Logic) ---
// --------------------------------------------------------------------------
/**
 * Inserts a notification into DB and triggers an email alert via PHPMailer.
 * * @param PDO $pdo Database connection
 * @param int|null $target_user_id The 'employee_id' (optional if broadcasting to role)
 * @param int $target_role 0=Superadmin, 1=Admin, 2=Employee
 * @param string $type Notification type (e.g., 'Leave Request', 'System')
 * @param string $message Short message
 * @param string $link URL relative to site root
 * @param string|null $sender_name Override sender name
 */
function send_notification($pdo, $target_user_id, $target_role, $type, $message, $link = '#', $sender_name = null) {
    
    $db_insert_success = false;
    
    try {
        // --- A. DETECT SENDER NAME ---
        if ($sender_name === null || $sender_name === '') {
            if (session_status() === PHP_SESSION_NONE) { session_start(); }

            if (isset($_SESSION['fullname'])) {
                $sender_name = $_SESSION['fullname'];
            } elseif (isset($_SESSION['employee_id'])) {
                $sender_name = "Employee " . $_SESSION['employee_id'];
            } else {
                $sender_name = "System";
            }
        }

        // --- B. INSERT INTO DATABASE ---
        $sql = "INSERT INTO tbl_notifications 
                (target_user_id, target_role, type, message, link, sender_name, created_at) 
                VALUES (:uid, :role, :type, :msg, :link, :sender, NOW())";
        
        $stmt = $pdo->prepare($sql);
        $db_insert_success = $stmt->execute([
            ':uid'    => $target_user_id, 
            ':role'   => $target_role,    
            ':type'   => $type,           
            ':msg'    => $message,
            ':link'   => $link,
            ':sender' => $sender_name
        ]);
        
        // --- C. SEND EMAIL VIA HANDLER ---
        if ($db_insert_success) {
            $recipient_emails = [];
            
            // 1. Target Specific Employee (by Employee ID)
            if (!empty($target_user_id)) {
                $stmt_email = $pdo->prepare("
                    SELECT t1.email 
                    FROM tbl_users t1
                    WHERE t1.employee_id = ? AND t1.status = 1
                ");
                $stmt_email->execute([$target_user_id]);
                $user_email = $stmt_email->fetchColumn();
                if ($user_email) $recipient_emails[] = $user_email;
            } 
            // 2. Target Role (Broadcast)
            elseif ($target_role === 0 || $target_role === 1) {
                // Get all active users of that role
                $stmt_admins = $pdo->prepare("SELECT email FROM tbl_users WHERE usertype = ? AND status = 1");
                $stmt_admins->execute([$target_role]);
                $recipient_emails = $stmt_admins->fetchAll(PDO::FETCH_COLUMN);
            }

            // Execute Send
            if (!empty($recipient_emails)) {
                // Send individually to ensure privacy (BCC effect) or loop through them
                // For simple notifications, comma-separated string in 'To' field works if list is small,
                // but looping is safer for PHPMailer to handle 'To' addresses correctly without exposing everyone.
                
                $subject = "LOPISv2 Alert: {$type}";
                $email_html = "
                    <div style='font-family: Arial, sans-serif; padding: 20px; border: 1px solid #e0e0e0; border-radius: 5px;'>
                        <h3 style='color: #4e73df;'>New Notification from {$sender_name}</h3>
                        <p><strong>Type:</strong> {$type}</p>
                        <p><strong>Message:</strong> {$message}</p>
                        <hr>
                        <p><a href='http://localhost/LOPISv2/{$link}' style='background-color: #4e73df; color: white; padding: 10px 15px; text-decoration: none; border-radius: 4px;'>View Details</a></p>
                        <p style='font-size: 12px; color: #888;'>This is an automated message. Please do not reply.</p>
                    </div>
                ";

                foreach ($recipient_emails as $email) {
                    // Call the helper which calls your email_handler.php
                    send_email_alert($pdo, $email, $subject, $email_html);
                }
            }
        }
        
        return $db_insert_success;

    } catch (PDOException $e) {
        error_log("Notification Error: " . $e->getMessage());
        return false;
    }
}

// --------------------------------------------------------------------------
// --- 2. FETCH NOTIFICATIONS (Global) ---
// --------------------------------------------------------------------------
function get_my_notifications($pdo, $my_role, $limit = 5, $target_id = null) {
    try {
        // Use passed target_id (Employee ID) or fallback to session
        $search_id = $target_id ?? ($_SESSION['employee_id'] ?? 0);

        if (empty($search_id) && $my_role == 2) return [];

        $sql = "SELECT * FROM tbl_notifications 
                WHERE is_read = 0 
                AND (
                    (target_user_id = :search_id) 
                    OR 
                    (target_role = :my_role)
                )
                ORDER BY created_at DESC 
                LIMIT :limit";

        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':search_id', $search_id);
        $stmt->bindValue(':my_role', $my_role, PDO::PARAM_INT);
        $stmt->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);

    } catch (PDOException $e) {
        error_log("Notification Fetch Error: " . $e->getMessage());
        return [];
    }
}

// --------------------------------------------------------------------------
// --- 3. TIME HELPER ---
// --------------------------------------------------------------------------
function time_elapsed_string($datetime, $full = false) {
    date_default_timezone_set('Asia/Manila');
    $now = new DateTime;
    $ago = new DateTime($datetime);
    $diff = $now->diff($ago);

    $diff->w = floor($diff->d / 7);
    $diff->d -= $diff->w * 7;

    $string = array('y' => 'year', 'm' => 'month', 'w' => 'week', 'd' => 'day', 'h' => 'hour', 'i' => 'minute', 's' => 'second');
    foreach ($string as $k => &$v) {
        if ($diff->$k) {
            $v = $diff->$k . ' ' . $v . ($diff->$k > 1 ? 's' : '');
        } else {
            unset($string[$k]);
        }
    }

    if (!$full) $string = array_slice($string, 0, 1);
    return $string ? implode(', ', $string) . ' ago' : 'just now';
}

// --------------------------------------------------------------------------
// --- 4. MARK READ ---
// --------------------------------------------------------------------------
function mark_notification_read($pdo, $id = null, $user_role = null) {
    try {
        if ($id === 'all' && $user_role !== null) {
            $sql = "UPDATE tbl_notifications SET is_read = 1 WHERE is_read = 0 AND target_role = :role";
            $stmt = $pdo->prepare($sql);
            $stmt->bindValue(':role', $user_role, PDO::PARAM_INT);
            return $stmt->execute();
        } else {
            $sql = "UPDATE tbl_notifications SET is_read = 1 WHERE id = :id";
            $stmt = $pdo->prepare($sql);
            $stmt->bindValue(':id', $id, PDO::PARAM_INT);
            return $stmt->execute();
        }
    } catch (PDOException $e) {
        return false;
    }
}
?>