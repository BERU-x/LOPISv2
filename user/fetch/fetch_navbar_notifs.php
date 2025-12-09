<?php
// fetch/fetch_navbar_notifs.php

date_default_timezone_set('Asia/Manila');
session_start();

require_once '../../db_connection.php'; 

header('Content-Type: application/json');

// 2. IDENTIFY USER ROLE
$my_id = null;
$my_role = '';

if (isset($_SESSION['employee_id'])) {
    $my_id = $_SESSION['employee_id'];
    $my_role = 'Employee';
} elseif (isset($_SESSION['user_id'])) {
    $my_id = $_SESSION['user_id'];
    $my_role = 'Admin';
} else {
    echo json_encode(['count' => 0, 'html' => '<a class="dropdown-item">Login required</a>']);
    exit;
}

try {
    // ---------------------------------------------------------
    // 3. FETCH UNREAD COUNT
    // ---------------------------------------------------------
    
    if ($my_role === 'Admin') {
        // ADMIN: Gets specific Admin messages OR General Admin broadcasts
        $sql_count = "SELECT COUNT(*) FROM tbl_notifications 
                      WHERE is_read = 0 
                      AND (target_user_id = :my_id OR target_role = 'Admin')";
        $params = [':my_id' => $my_id];
        
    } else {
        // EMPLOYEE: 
        // 1. Exact match on Employee ID
        // 2. OR Role is 'Employee'/'All' AND User ID is NULL (Broadcasts)
        $sql_count = "SELECT COUNT(*) FROM tbl_notifications 
                      WHERE is_read = 0 
                      AND (
                          target_user_id = :my_id 
                          OR (target_user_id IS NULL AND target_role IN ('Employee', 'All'))
                      )";
        
        $params = [':my_id' => $my_id];
    }

    $stmt = $pdo->prepare($sql_count);
    $stmt->execute($params);
    $unread_count = $stmt->fetchColumn();

    // ---------------------------------------------------------
    // 4. FETCH RECENT NOTIFICATIONS LIST
    // ---------------------------------------------------------

    if ($my_role === 'Admin') {
        $sql_list = "SELECT * FROM tbl_notifications 
                     WHERE (target_user_id = :my_id OR target_role = 'Admin')
                     ORDER BY created_at DESC LIMIT 5";
    } else {
        // Same logic as count, but fetching columns
        $sql_list = "SELECT * FROM tbl_notifications 
                     WHERE (
                          target_user_id = :my_id 
                          OR (target_user_id IS NULL AND target_role IN ('Employee', 'All'))
                     )
                     ORDER BY created_at DESC LIMIT 5";
    }

    $stmt_list = $pdo->prepare($sql_list);
    $stmt_list->execute($params);
    $notifs = $stmt_list->fetchAll(PDO::FETCH_ASSOC);

    // ---------------------------------------------------------
    // 5. BUILD HTML OUTPUT
    // ---------------------------------------------------------
    $html = '';
    
    if (count($notifs) > 0) {
        $html .= '<h6 class="dropdown-header" style="background-color: #0CC0DF !important; border:none;">Notifications</h6>';
        
        foreach ($notifs as $n) {
            $read_class = $n['is_read'] ? 'text-gray-500' : 'font-weight-bold text-gray-900';
            $bg_class = $n['is_read'] ? '' : 'bg-light'; 

            // Simple time formatting
            $time_str = date('M d, H:i', strtotime($n['created_at']));

            // Construct link (ensure you have this logic in notifications.php)
            $click_url = "notifications.php?action=read&id={$n['id']}";

            $html .= '
            <a class="dropdown-item d-flex align-items-center '.$bg_class.'" href="'.$click_url.'">
                <div>
                    <div class="small text-gray-500">'.$time_str.'</div>
                    <span class="'.$read_class.'">'.htmlspecialchars($n['message']).'</span>
                </div>
            </a>';
        }
        $html .= '<a class="dropdown-item text-center small text-gray-500" href="notifications.php">Show All Alerts</a>';
    } else {
        $html .= '<h6 class="dropdown-header" style="background-color: #0CC0DF !important; border:none;">Notifications</h6>';
        $html .= '<a class="dropdown-item text-center small text-gray-500 py-3" href="#">No new notifications</a>';
    }

    echo json_encode([
        'count' => $unread_count, 
        'html' => $html
    ]);

} catch (PDOException $e) {
    echo json_encode(['count' => 0, 'html' => 'DB Error']);
}
?>