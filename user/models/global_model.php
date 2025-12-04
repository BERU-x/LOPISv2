<?php
// models/global_model.php

// Ensure database connection is available
// (Adjust path if your config is in a different folder)
require_once __DIR__ . '/../../db_connection.php';

// --- 1. CREATE NOTIFICATION (The "Sender" Logic) ---
function send_notification($pdo, $target_user_id, $target_role, $type, $message, $link = '#', $sender_name = 'System') {
    try {
        // $target_user_id: Specific Employee ID (or NULL for group message)
        // $target_role: 'Admin', 'Employee', or 'All'
        
        $sql = "INSERT INTO tbl_notifications 
                (target_user_id, target_role, type, message, link, sender_name, created_at) 
                VALUES (:uid, :role, :type, :msg, :link, :sender, NOW())";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':uid'    => $target_user_id, // NULL if sending to a whole group
            ':role'   => $target_role,
            ':type'   => $type,
            ':msg'    => $message,
            ':link'   => $link,
            ':sender' => $sender_name
        ]);
        return true;
    } catch (PDOException $e) {
        error_log("Notification Error: " . $e->getMessage());
        return false;
    }
}

// --- 2. FETCH NOTIFICATIONS (The "Receiver" Logic) ---
// We now pass the current user's ID and Role to filter what they see
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

// 2. Time Elapsed Helper (e.g., "2 hours ago")
function time_elapsed_string($datetime, $full = false) {
    // Set correct timezone if needed, e.g., date_default_timezone_set('Asia/Manila');
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