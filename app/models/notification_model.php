<?php
// app/models/notification_model.php
// Formerly global_app_model.php

// 1. DEPENDENCIES
// We use require_once to prevent "function already declared" errors
require_once __DIR__ . '/../../db_connection.php';
require_once __DIR__ . '/../../helpers/email_handler.php'; 

// --------------------------------------------------------------------------
// --- HELPER: SEND EMAIL ALERT ---
// --------------------------------------------------------------------------
function send_email_alert($pdo, $to_email, $subject, $html_body) {
    if (empty($to_email)) return false;
    
    $plain_body = strip_tags($html_body);
    // Assumes send_email() exists in email_handler.php
    $status = send_email($pdo, $to_email, $subject, $plain_body, $html_body);
    return ($status === 'sent');
}

// --------------------------------------------------------------------------
// --- 1. SEND NOTIFICATION (Global Logic) ---
// --------------------------------------------------------------------------
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
        // Only send if DB insert worked AND we have email enabled in settings (optional check)
        if ($db_insert_success) {
            $recipient_emails = [];
            
            // 1. Target Specific Employee
            if (!empty($target_user_id)) {
                $stmt_email = $pdo->prepare("SELECT email FROM tbl_users WHERE employee_id = ? AND status = 1");
                $stmt_email->execute([$target_user_id]);
                $user_email = $stmt_email->fetchColumn();
                if ($user_email) $recipient_emails[] = $user_email;
            } 
            // 2. Target Role (Broadcast)
            elseif ($target_role === 0 || $target_role === 1) {
                $stmt_admins = $pdo->prepare("SELECT email FROM tbl_users WHERE usertype = ? AND status = 1");
                $stmt_admins->execute([$target_role]);
                $recipient_emails = $stmt_admins->fetchAll(PDO::FETCH_COLUMN);
            }

            if (!empty($recipient_emails)) {
                // Use the constant BASE_URL we defined in db_connection.php
                $full_link = defined('BASE_URL') ? BASE_URL . $link : $link;

                $subject = "LOPISv2 Alert: {$type}";
                $email_html = "
                    <div style='font-family: Arial, sans-serif; padding: 20px; border: 1px solid #e0e0e0; border-radius: 5px;'>
                        <h3 style='color: #4e73df;'>New Notification from {$sender_name}</h3>
                        <p><strong>Type:</strong> {$type}</p>
                        <p><strong>Message:</strong> {$message}</p>
                        <hr>
                        <p><a href='{$full_link}' style='background-color: #4e73df; color: white; padding: 10px 15px; text-decoration: none; border-radius: 4px;'>View Details</a></p>
                        <p style='font-size: 12px; color: #888;'>This is an automated message.</p>
                    </div>
                ";

                foreach ($recipient_emails as $email) {
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
// --- 2. FETCH NOTIFICATIONS (Updated for Hierarchy) ---
// --------------------------------------------------------------------------
function get_my_notifications($pdo, $my_role, $limit = 5, $target_id = null) {
    try {
        // 1. Identify the User (Employee ID)
        $search_id = $target_id ?? ($_SESSION['employee_id'] ?? 0);

        // 2. Define Which "Roles" This User Can See
        // Default: You only see your own role
        $allowed_roles = [$my_role];

        // LOGIC ADJUSTMENT: Superadmins (0) should also see Admin (1) alerts
        if ($my_role == 0) {
            $allowed_roles = [0, 1]; 
        }

        // Convert array to comma-separated string for SQL IN clause (Safe for integers)
        $roles_str = implode(',', $allowed_roles);

        // 3. The Query
        // We use FIND_IN_SET or specific IN clause logic
        $sql = "SELECT * FROM tbl_notifications 
                WHERE is_read = 0 
                AND (
                    (target_user_id = :search_id) 
                    OR 
                    (target_role IN ($roles_str)) 
                )
                ORDER BY created_at DESC 
                LIMIT :limit";

        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':search_id', $search_id);
        $stmt->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);

    } catch (PDOException $e) {
        // Ideally log this error to a file
        return [];
    }
}

// --------------------------------------------------------------------------
// --- 3. TIME HELPER (Cleaned) ---
// --------------------------------------------------------------------------
function time_elapsed_string($datetime, $full = false) {
    // REMOVED: date_default_timezone_set. 
    // We rely on checking.php (or the system default) to have set the correct timezone already.
    
    try {
        $now = new DateTime; // Uses current set timezone
        $ago = new DateTime($datetime); // Parses DB time
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
    } catch (Exception $e) {
        return $datetime;
    }
}
?>