<?php
// fetch/fetch_navbar_notifs.php

date_default_timezone_set('Asia/Manila');

session_start();

// 1. ADJUST PATHS
require_once '../../db_connection.php'; 
require_once '../models/global_model.php'; 

header('Content-Type: application/json');

// 2. IDENTIFY USER ROLE (UPDATED LOGIC)
$my_id = null;
$my_role = '';

// Priority 1: Check for Admin (usertype = 1)
if (isset($_SESSION['usertype']) && $_SESSION['usertype'] == 0) {
    // Admin ID comes from tbl_users
    $my_id = $_SESSION['user_id'] ?? null; 
    $my_role = 'Admin';
} 
// Priority 2: Check for Standard Employee (logged in via employee_id)
elseif (isset($_SESSION['employee_id'])) {
    $my_id = $_SESSION['employee_id'];
    $my_role = 'Employee';
} 
// Priority 3: Check for Non-Admin Staff (logged in via user_id, but usertype != 1)
elseif (isset($_SESSION['user_id'])) {
    // Treat any other logged-in staff user as an Employee for notification targeting
    $my_id = $_SESSION['user_id']; 
    $my_role = 'Employee'; 
}

if (!$my_id) {
    // No session found
    echo json_encode(['count' => 0, 'html' => '<a class="dropdown-item text-center small text-gray-500" href="#">Login required</a>']);
    exit;
}

try {
    // ---------------------------------------------------------
    // 3. FETCH UNREAD COUNT
    // ---------------------------------------------------------
    
    // Prepare the SQL and Parameters based on Role to ensure they MATCH
    if ($my_role === 'Admin') {
        // ADMIN QUERY: target_user_id (from tbl_users) OR target_role = 'Admin'
        $sql_count = "SELECT COUNT(*) FROM tbl_notifications 
                      WHERE is_read = 0 
                      AND (target_user_id = :my_id OR target_role = 'Admin')";
        
        $params_count = [':my_id' => $my_id];
        
    } else {
        // EMPLOYEE/STAFF QUERY: target_user_id (from tbl_employees) OR target_role = 'Employee' OR 'All'
        $sql_count = "SELECT COUNT(*) FROM tbl_notifications 
                      WHERE is_read = 0 
                      AND (
                          target_user_id = :my_id 
                          OR target_role = :my_role 
                          OR target_role = 'All'
                      )";
        
        $params_count = [
            ':my_id'   => $my_id,
            ':my_role' => 'Employee' // Ensure we use the literal role name for the query
        ];
    }

    $stmt = $pdo->prepare($sql_count);
    $stmt->execute($params_count); 
    $unread_count = $stmt->fetchColumn();

    // ---------------------------------------------------------
    // 4. FETCH RECENT NOTIFICATIONS LIST
    // ---------------------------------------------------------

    if ($my_role === 'Admin') {
        $sql_list = "SELECT * FROM tbl_notifications 
                     WHERE (target_user_id = :my_id OR target_role = 'Admin')
                     ORDER BY created_at DESC LIMIT 5";
                     
        $params_list = [':my_id' => $my_id];

    } else {
        $sql_list = "SELECT * FROM tbl_notifications 
                     WHERE (
                         target_user_id = :my_id 
                         OR target_role = :my_role 
                         OR target_role = 'All'
                     )
                     ORDER BY created_at DESC LIMIT 5";
                     
        $params_list = [
            ':my_id'   => $my_id,
            ':my_role' => 'Employee'
        ];
    }

    $stmt_list = $pdo->prepare($sql_list);
    $stmt_list->execute($params_list);
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

            $time_str = date('M d, H:i', strtotime($n['created_at']));
            if(function_exists('time_elapsed_string')) {
                $time_str = time_elapsed_string($n['created_at']);
            }

            $click_url = "notifications.php?action=mark_read&id={$n['id']}";

            $html .= '
            <a class="dropdown-item d-flex align-items-center '.$bg_class.'" href="'.$click_url.'">
                <div class="w-100 py-2">
                    <div class="small text-gray-500 mb-1">'.$time_str.'</div>
                    <span class="'.$read_class.'">'.htmlspecialchars(substr($n['message'], 0, 80)).(strlen($n['message'])>80?'...':'').'</span>
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
    echo json_encode(['count' => 0, 'html' => 'DB Error: ' . $e->getMessage()]);
}
?>