<?php
// admin/models/global_model.php

date_default_timezone_set('Asia/Manila');

require_once __DIR__ . '/../../db_connection.php';

// --------------------------------------------------------------------------
// --- TEMPORARY EMAIL FUNCTION FOR TESTING (UPDATED) ---
// --------------------------------------------------------------------------
function send_email_alert($to_email, $subject, $body) {
    // ... (Existing checks remain the same) ...
    $recipient = $to_email; 

    if (empty($recipient)) {
        error_log("Email Alert Failed: Recipient list is empty.");
        return false;
    }
    
    $headers = 'From: LOPISv2 <no-reply@lendell.ph>' . "\r\n" .
               'Content-Type: text/html; charset=UTF-8';
    
    error_log("Attempting to send email to: $recipient | Subject: $subject");
    
    // -----------------------------------------------------------------
    // LOGGING MODE (For Testing)
    // -----------------------------------------------------------------
    $log_file = __DIR__ . '/../email_log.txt';
    $log_content = "\n\n--- EMAIL SENT @ " . date('Y-m-d H:i:s') . " ---\n"
                 . "To: " . $recipient . "\n"
                 . "Subject: " . $subject . "\n"
                 . "Headers: " . str_replace("\r\n", " | ", $headers) . "\n"
                 . "Body:\n" . strip_tags($body) . "\n" 
                 . "--------------------------------------\n";

    file_put_contents($log_file, $log_content, FILE_APPEND);
    
    return true; 
    
    // UNCOMMENT FOR PRODUCTION:
    // return mail($recipient, $subject, $body, $headers);
}

// --------------------------------------------------------------------------
// --- 1. CREATE NOTIFICATION (CORE FUNCTION) ---
// --------------------------------------------------------------------------
function send_notification($pdo, $target_user_id, $target_role, $type, $message, $link = '#', $sender_name = null) {
    
    $db_insert_success = false;
    
    try {
        // --- 1. SENDER NAME DETECTION (Existing Logic) ---
        if ($sender_name === null || $sender_name === '') {
            if (session_status() === PHP_SESSION_NONE) { session_start(); }

            if (isset($_SESSION['employee_id']) && !isset($_SESSION['user_id'])) {
                $stmt_emp = $pdo->prepare("SELECT firstname, lastname FROM tbl_employees WHERE employee_id = ?");
                $stmt_emp->execute([$_SESSION['employee_id']]);
                $emp_data = $stmt_emp->fetch(PDO::FETCH_ASSOC);

                if ($emp_data) {
                    $sender_name = $emp_data['firstname'] . ' ' . $emp_data['lastname'];
                } else {
                    $sender_name = "Employee " . $_SESSION['employee_id'];
                }
            } 
            elseif (isset($_SESSION['user_id'])) {
                $stmt_user = $pdo->prepare("SELECT usertype, employee_id FROM tbl_users WHERE id = ?");
                $stmt_user->execute([$_SESSION['user_id']]);
                $user_data = $stmt_user->fetch(PDO::FETCH_ASSOC);

                if ($user_data) {
                    if ($user_data['usertype'] == 1) {
                        $sender_name = 'Administrator';
                    } else {
                        $linked_emp_id = $user_data['employee_id'];
                        $stmt_details = $pdo->prepare("SELECT firstname, lastname FROM tbl_employees WHERE employee_id = ?");
                        $stmt_details->execute([$linked_emp_id]);
                        $real_person = $stmt_details->fetch(PDO::FETCH_ASSOC);
                        
                        $sender_name = $real_person ? ($real_person['firstname'] . ' ' . $real_person['lastname']) : 'Staff';
                    }
                } else {
                    $sender_name = 'System Admin';
                }
            } 
            else {
                $sender_name = 'System';
            }
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
            $recipient_email = null;
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
                    $recipient_email = $emp_data['email'];
                    $recipient_name = $emp_data['firstname'];
                }
            } 
            // B. ADMIN LOGIC (HARDCODED)
            elseif ($target_role == 'Admin') {
                
                // 🛑 MODIFICATION: Use hardcoded emails instead of DB lookup
                $admin_emails = [
                    'dennis.gayapa@lendell.ph',
                    'ella.sepe@lendell.ph'
                ];

                $recipient_email = implode(',', $admin_emails); 
                $recipient_name = 'Administrator';
            }
            
            // SEND EMAIL
            if ($recipient_email) {
                $subject = "HRIS ALERT: {$type} Notification Received";
                $email_body = "Hello {$recipient_name},<br><br>"
                            . "You have a new **{$type}** notification from {$sender_name}:<br>"
                            . "<strong>{$message}</strong><br><br>"
                            . "Please log in to the portal to view details:<br>"
                            . "<a href='http://lendell.ph/{$link}'>View Notification</a>";
                
                send_email_alert($recipient_email, $subject, $email_body);
            }
        }
        
        return $db_insert_success;

    } catch (PDOException $e) {
        error_log("Notification Error: " . $e->getMessage());
        return false;
    }
}

// --- 2. FETCH NOTIFICATIONS (Admin as Receiver) ---
function get_my_notifications($pdo, $my_role = 'Admin', $limit = 5) {
    try {
        $my_id = $_SESSION['user_id'] ?? 0;

        $sql = "SELECT * FROM tbl_notifications 
                WHERE is_read = 0 
                AND (
                    (target_user_id = :my_id) 
                    OR 
                    (target_role = 'Admin')
                )
                ORDER BY created_at DESC 
                LIMIT :limit";

        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':my_id', $my_id);
        $stmt->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);

    } catch (PDOException $e) {
        error_log("Notification Fetch Error: " . $e->getMessage());
        return [];
    }
}

// --- 3. TIME HELPER (Unchanged) ---
function time_elapsed_string($datetime, $full = false) {
    date_default_timezone_set('Asia/Manila');
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

// --- 4. MARK READ (Unchanged) ---
function mark_notification_read($pdo, $id = null) {
    try {
        if ($id === 'all') {
            $sql = "UPDATE tbl_notifications SET is_read = 1 WHERE is_read = 0 AND target_role = 'Admin'";
            $stmt = $pdo->prepare($sql);
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