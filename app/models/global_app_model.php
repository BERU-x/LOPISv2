<?php
// app/models/global_app_model.php
// Centralized model file for all user roles (Super Admin, Admin, Employee).

// --- PATH ADJUSTMENTS ---
// Assuming this file is placed in a structure like /app/models/
require_once __DIR__ . '/../../db_connection.php'; 
require_once __DIR__ . '/../../email_handler.php'; // <-- INCLUDED: The robust email handler

// --- DYNAMIC TIMEZONE FETCH ---
try {
    $stmt_tz = $pdo->query("SELECT system_timezone FROM tbl_general_settings WHERE id = 1");
    $timezone = $stmt_tz->fetchColumn() ?? 'Asia/Manila';
    date_default_timezone_set($timezone); 
} catch (PDOException $e) {
    date_default_timezone_set('Asia/Manila');
}
// -----------------------------

// --------------------------------------------------------------------------
// --- 1. AUTHENTICATION & SESSION HELPERS ---
// --------------------------------------------------------------------------

/**
 * Checks if a user is logged in and belongs to an authorized role.
 * @param array $allowed_usertypes Array of allowed roles.
 * @return bool True if authorized, False otherwise.
 */
function is_user_authorized($allowed_usertypes = [0, 1, 2]) {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
        return false;
    }

    $usertype = $_SESSION['usertype'] ?? 99;
    
    return in_array($usertype, $allowed_usertypes);
}

/**
 * Redirects unauthorized users to the login page.
 */
function require_authorization($allowed_usertypes = [0, 1, 2], $redirect_path = '/index.php') {
    if (!is_user_authorized($allowed_usertypes)) {
        header("Location: " . $redirect_path);
        exit;
    }
}


// --------------------------------------------------------------------------
// --- 2. NOTIFICATION CORE FUNCTIONS ---
// --------------------------------------------------------------------------

// send_email_alert is now deprecated/removed as send_email is used directly.


/**
 * Inserts a new notification into the database and attempts to send an email alert.
 * (UPDATED to use send_email())
 */
function send_notification($pdo, $target_user_id, $target_role, $type, $message, $link = '#', $sender_name = null) {
    
    // Ensure the global email function exists
    if (!function_exists('send_email')) {
        error_log("FATAL: send_email function is missing. Check email_handler.php path.");
        return false;
    }
    
    $db_insert_success = false;
    
    try {
        // --- 1. SENDER NAME DETECTION (Placeholder) ---
        if ($sender_name === null || $sender_name === '') {
            // NOTE: Insert your full sender name logic here if needed.
            $sender_name = 'System'; 
        }

        // --- 2. DATABASE INSERTION ---
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
        
        // --- 3. EMAIL SENDING LOGIC ---
        if ($db_insert_success) {
            $recipient_email_string = null;
            $recipient_name = 'User';

            // A. EMPLOYEE LOGIC
            if ($target_role == 'Employee' && $target_user_id) {
                $stmt_email = $pdo->prepare("
                    SELECT t1.email, t2.firstname 
                    FROM tbl_users t1
                    JOIN tbl_employees t2 ON t1.employee_id = t2.employee_id
                    WHERE t1.employee_id = ?");
                $stmt_email->execute([$target_user_id]);
                $emp_data = $stmt_email->fetch(PDO::FETCH_ASSOC);
                
                if ($emp_data && !empty($emp_data['email'])) {
                    $recipient_email_string = $emp_data['email'];
                    $recipient_name = $emp_data['firstname'];
                }
            } 
            // B. ADMIN LOGIC 
            elseif ($target_role == 'Admin') {
                // Fetch emails of all users designated as admin (usertype 0 or 1)
                $stmt_emails = $pdo->query("SELECT GROUP_CONCAT(email) FROM tbl_users WHERE usertype IN (0, 1) AND email IS NOT NULL AND email != ''");
                $recipient_email_string = $stmt_emails->fetchColumn(); 
                $recipient_name = 'Administrator';
            }
            
            // SEND EMAIL
            if ($recipient_email_string) {
                $subject = "LOPISv2 ALERT: {$type} Notification Received";
                
                $html_body = "Hello {$recipient_name},<br><br>"
                             . "You have a new <strong>{$type}</strong> notification from {$sender_name}:<br>"
                             . "<strong>{$message}</strong><br><br>"
                             . "Please log in to the portal to view details:<br>"
                             . "<a href='http://lendell.ph/LOPISv2/{$link}'>View Notification</a>";
                
                // Plain text fallback
                $text_body = strip_tags(str_replace('<br>', "\n", $html_body));
                
                // CRITICAL CHANGE: Use the robust PHPMailer function
                $mail_status = send_email($pdo, $recipient_email_string, $subject, $text_body, $html_body);
                
                // Optional logging of mail_status:
                if ($mail_status != 'sent') {
                    error_log("Notification Email Status: {$mail_status} for {$recipient_email_string}");
                }
            }
        }
        
        return $db_insert_success;

    } catch (PDOException $e) {
        error_log("Notification Error: " . $e->getMessage());
        return false;
    }
}

/**
 * Fetches unread notifications for the current user/role.
 */
function get_my_notifications($pdo, $my_role, $limit = 5, $target_id_override = null) {
    try {
        $my_id = $target_id_override ?? ($_SESSION['user_id'] ?? 0);

        $sql = "SELECT * FROM tbl_notifications 
                WHERE is_read = 0 
                AND (
                    (target_role = 'Admin' AND (:my_role_param IN ('Admin', 'Super Admin'))) 
                    OR 
                    (target_role = 'Employee' AND target_user_id = :my_id)
                )
                ORDER BY created_at DESC 
                LIMIT :limit";

        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':my_id', $my_id);
        $stmt->bindValue(':my_role_param', $my_role);
        $stmt->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);

    } catch (PDOException $e) {
        error_log("Notification Fetch Error: " . $e->getMessage());
        return [];
    }
}

/**
 * Marks one or all notifications as read for the current user/role.
 */
function mark_notification_read($pdo, $id = null, $user_id = null, $user_role = null) {
    try {
        if ($id === 'all') {
            if ($user_role === 'Admin' || $user_role === 'Super Admin') {
                $sql = "UPDATE tbl_notifications SET is_read = 1 WHERE is_read = 0 AND target_role = :role";
                $stmt = $pdo->prepare($sql);
                $stmt->bindValue(':role', 'Admin');
            } elseif ($user_role === 'Employee' && $user_id !== null) {
                $sql = "UPDATE tbl_notifications SET is_read = 1 WHERE is_read = 0 AND target_user_id = :user_id";
                $stmt = $pdo->prepare($sql);
                $stmt->bindValue(':user_id', $user_id);
            } else {
                return false;
            }
            return $stmt->execute();
            
        } else {
            $sql = "UPDATE tbl_notifications SET is_read = 1 WHERE id = :id";
            $stmt = $pdo->prepare($sql);
            $stmt->bindValue(':id', $id, PDO::PARAM_INT);
            return $stmt->execute();
        }
    } catch (PDOException $e) {
        error_log("Notification Mark Read Error: " . $e->getMessage());
        return false;
    }
}


// --------------------------------------------------------------------------
// --- 3. TIME HELPER ---
// --------------------------------------------------------------------------

/**
 * Converts a datetime string to a human-readable "time ago" string.
 */
function time_elapsed_string($datetime, $full = false) {
    $now = new DateTime;
    $ago = new DateTime($datetime);
    $diff = $now->diff($ago);

    $weeks = floor($diff->d / 7);
    $days = $diff->d - ($weeks * 7);

    $string = array('y' => 'year', 'm' => 'month', 'w' => $weeks, 'd' => $days, 'h' => 'hour', 'i' => 'minute', 's' => 'second');
    $labels = array('y' => 'year', 'm' => 'month', 'w' => 'week', 'd' => 'day', 'h' => 'hour', 'i' => 'minute', 's' => 'second');
    
    $result = array();
    foreach ($string as $k => $v) {
        if (in_array($k, ['w', 'd']) || $diff->$k) {
            $label = $labels[$k];
            $count = in_array($k, ['w', 'd']) ? $v : $diff->$k;
            if ($count > 0) {
                $result[] = $count . ' ' . $label . ($count > 1 ? 's' : '');
            }
        }
    }

    if (!$full) $result = array_slice($result, 0, 1);
    return $result ? implode(', ', $result) . ' ago' : 'just now';
}
?>