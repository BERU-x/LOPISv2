<?php
// models/global_model.php

// Ensure database connection is available
require_once __DIR__ . '/../../db_connection.php';

// --------------------------------------------------------------------------
// --- HARDCODED ADMIN EMAIL CONSTANT ---
// --------------------------------------------------------------------------
// Only these specific administrators will receive email alerts.
const ADMIN_EMAIL_RECIPIENTS = 'dennis.gayapa@lendell.ph,ella.sepe@lendell.ph';
// --------------------------------------------------------------------------


// --------------------------------------------------------------------------
// --- EMAIL FUNCTION FOR LOCAL TESTING: LOGS TO FILE ---
// --------------------------------------------------------------------------
function send_email_alert($to_email, $subject, $body) {
    // We now trust that the caller provided a valid email address or the comma-separated list of Admin emails.
    $recipient = $to_email; 

    if (empty($recipient)) {
        error_log("Email Alert Failed: Recipient list is empty.");
        return false;
    }
    
    $headers = 'From: LOPISv2 <no-reply@lendell.ph>' . "\r\n" .
               'Content-Type: text/html; charset=UTF-8';
    
    error_log("Attempting to log email to: $recipient | Subject: $subject");
    
    // --- LOGGING IMPLEMENTATION ---
    // Define the log file path relative to the current directory (models/global_model.php)
    // This assumes the log file should be in the directory above 'models'
    $log_file = __DIR__ . '/../email_log.txt'; 
    $log_content = "\n\n--- EMAIL SENT @ " . date('Y-m-d H:i:s') . " ---\n"
                 . "To: " . $recipient . "\n"
                 . "Subject: " . $subject . "\n"
                 . "Headers: " . str_replace("\r\n", " | ", $headers) . "\n"
                 . "Body (Stripped HTML):\n" . strip_tags($body) . "\n"
                 . "--------------------------------------\n";

    // Append to the log file
    $log_success = file_put_contents($log_file, $log_content, FILE_APPEND);

    if (!$log_success) {
        error_log("CRITICAL: Failed to write email log to $log_file");
        return false;
    }
    
    // Always return true to simulate a successful send for testing purposes
    return true; 
}
// --------------------------------------------------------------------------


// --- 1. CREATE NOTIFICATION (Updated to use hardcoded Admin emails) ---
function send_notification($pdo, $target_user_id, $target_role, $type, $message, $link = '#', $sender_name = null) {
    $db_insert_success = false;

    try {
        // AUTOMATIC NAME FETCHING LOGIC: (Unchanged)
        if ($sender_name === null || $sender_name === '') {
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }

            if (isset($_SESSION['employee_id'])) {
                $stmt_sender = $pdo->prepare("SELECT firstname, lastname FROM tbl_employees WHERE employee_id = ?");
                $stmt_sender->execute([$_SESSION['employee_id']]);
                $sender_info = $stmt_sender->fetch(PDO::FETCH_ASSOC);

                if ($sender_info) {
                    $sender_name = $sender_info['firstname'] . ' ' . $sender_info['lastname'];
                } else {
                    $sender_name = $_SESSION['employee_id'];
                }
            } else {
                $sender_name = 'System';
            }
        }

        // DATABASE INSERTION: (Unchanged)
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

        // EMAIL ALERT LOGIC:
        if ($db_insert_success) {
            $recipient_email = null;
            $recipient_name = 'User';

            if ($target_role == 'Employee' && $target_user_id) {
                // Fetch specific Employee Email
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
            } elseif ($target_role == 'Admin' || $target_role == 'All') {
                // >>> MODIFIED: Use the hardcoded list for Admin notifications <<<
                $recipient_email = ADMIN_EMAIL_RECIPIENTS;
                $recipient_name = 'Administrator';
                // No DB query needed here for admin emails
            }
            
            if ($recipient_email) {
                $subject = "HRIS ALERT: {$type} Notification Received";
                // Note: Recipient name is 'Administrator' for the hardcoded list
                $email_body = "Hello {$recipient_name},<br><br>"
                            . "You have a new **{$type}** notification from {$sender_name}:<br>"
                            . "<strong>{$message}</strong><br><br>"
                            . "Please log in to the portal to view details:<br>"
                            . "<a href='http://lendell.ph/{$link}'>View Notification</a>";
                
                // Execute the email function (the logging function defined above)
                send_email_alert($recipient_email, $subject, $email_body);
            }
        }
        
        return $db_insert_success;
    } catch (PDOException $e) {
        error_log("Notification Error: " . $e->getMessage());
        return false;
    }
}

// --- 2. FETCH NOTIFICATIONS (The "Receiver" Logic) --- 
function get_my_notifications($pdo, $my_id, $my_role, $limit = 5) {
    try {
        $sql = "SELECT 
                    t1.*,
                    t2.firstname,
                    t2.lastname,
                    t2.photo
                FROM tbl_notifications t1
                LEFT JOIN tbl_employees t2 ON t1.target_user_id = t2.employee_id
                WHERE t1.is_read = 0 
                AND (
                    (t1.target_user_id = :my_id) 
                    OR 
                    (t1.target_user_id IS NULL AND t1.target_role = :my_role)
                    OR 
                    (t1.target_role = 'All')
                )
                ORDER BY t1.created_at DESC 
                LIMIT :limit";

        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':my_id', $my_id);
        $stmt->bindValue(':my_role', $my_role);
        $stmt->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);

    } catch (PDOException $e) {
        error_log("Notification Fetch Error: " . $e->getMessage());
        return [];
    }
}

// 2. Time Elapsed Helper
function time_elapsed_string($datetime, $full = false) {
    date_default_timezone_set('Asia/Manila');
    $now = new DateTime;
    $ago = new DateTime($datetime);
    $diff = $now->diff($ago);

    $diff->w = floor($diff->d / 7);
    $diff->d -= $diff->w * 7;

    $string = array(
        'y' => 'year',
        'm' => 'month',
        'w' => 'week',
        'd' => 'day',
        'h' => 'hour',
        'i' => 'minute',
        's' => 'second',
    );
    
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

// Fetch ALL notifications (Read and Unread) for the full list page
function get_all_notifications($pdo, $limit = 50) {
    try {
        $sql = "SELECT 
                    t1.*,
                    t2.firstname,
                    t2.lastname,
                    t2.photo
                FROM tbl_notifications t1
                LEFT JOIN tbl_employees t2 ON t1.target_user_id = t2.employee_id
                ORDER BY t1.created_at DESC 
                LIMIT :limit";

        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
        
    } catch (PDOException $e) {
        error_log("All Notifications Fetch Error: " . $e->getMessage());
        return [];
    }
}

// Mark a single notification (or all) as read
function mark_notification_read($pdo, $id = null) {
    try {
        if ($id === 'all') {
            $sql = "UPDATE tbl_notifications SET is_read = 1 WHERE is_read = 0";
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