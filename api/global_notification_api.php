<?php
// api/global_notifications_api.php
header('Content-Type: application/json');
session_start();

// --- PATH ADJUSTMENTS ---
// Adjust these paths relative to where this API file is placed (e.g., /api/global_notifications_api.php)
require_once __DIR__ . '/../admin/models/global_model.php'; 
require_once __DIR__ . '/../../db_connection.php'; 

// --- AUTHENTICATION AND CONTEXT CHECK ---
if (!isset($_SESSION['user_id'])) {
    http_response_code(401); // Unauthorized
    echo json_encode(['status' => 'error', 'message' => 'Authentication required.']);
    exit;
}

$user_id = $_SESSION['user_id'];
$user_usertype = $_SESSION['usertype'] ?? 99; // Default to 99 if usertype is missing
$action = $_REQUEST['action'] ?? '';
$my_role = '';

// Map usertype (0=Super Admin, 1=Admin, 2=Employee) to the role strings used in global_model.php
switch ($user_usertype) {
    case 0:
    case 1:
        $my_role = 'Admin'; 
        break;
    case 2:
        $my_role = 'Employee';
        break;
    default:
        http_response_code(403);
        echo json_encode(['status' => 'error', 'message' => 'Invalid user role defined.']);
        exit;
}

try {
    // =========================================================
    // ACTION 1: FETCH NOTIFICATIONS (GET Request)
    // =========================================================
    if ($action === 'fetch') {

        // Determine the target ID for the get_my_notifications function
        $target_id_for_model = $user_id;

        if ($my_role === 'Employee') {
            // If the user is an Employee, the model expects the employee_id, 
            // not the user_id (which is the primary key of tbl_users).
            
            // Fetch the corresponding employee_id from tbl_users
            $stmt_emp_id = $pdo->prepare("SELECT employee_id FROM tbl_users WHERE id = ?");
            $stmt_emp_id->execute([$user_id]);
            $employee_data = $stmt_emp_id->fetch(PDO::FETCH_ASSOC);

            if ($employee_data && $employee_data['employee_id']) {
                $target_id_for_model = $employee_data['employee_id'];
            } else {
                // Defensive check: Employee not linked to an employee_id
                $target_id_for_model = null; 
            }
        }
        
        // Pass the determined ID to the model function
        // Note: The model function must be updated to accept the target ID if it doesn't already. 
        // Based on your existing model, get_my_notifications should be modified to accept $user_id or $employee_id.
        
        // *Assuming we modify get_my_notifications to accept the specific ID for employee logic:*
        // However, based on the original model logic, get_my_notifications only requires $my_role and $limit.
        // It relies on $_SESSION['user_id'] internally. Let's adjust the call to pass the necessary ID if your model needs it.

        $notifications = get_my_notifications($pdo, $my_role, 10, $target_id_for_model); // <-- Pass the correct target ID
        $unread_count = 0;

        foreach ($notifications as $notif) {
            if ($notif['is_read'] == 0) {
                $unread_count++;
            }
        }

        echo json_encode([
            'status' => 'success',
            'count' => $unread_count,
            'notifications' => $notifications
        ]);
        exit;
    }

    // =========================================================
    // ACTION 2: MARK ALL READ (POST Request)
    // =========================================================
    if ($action === 'mark_all_read') {
        // The mark_notification_read function needs to know the target role if marking all
        // It should handle marking notifications targeted at the current user/role.
        
        if (mark_notification_read($pdo, 'all', $user_id, $my_role)) {
            echo json_encode(['status' => 'success', 'message' => 'All notifications marked as read.']);
        } else {
            http_response_code(500);
            echo json_encode(['status' => 'error', 'message' => 'Failed to execute database update.']);
        }
        exit;
    }

    // =========================================================
    // ACTION 3: MARK SINGLE READ (POST Request)
    // =========================================================
    if ($action === 'mark_single_read') {
        $id = $_POST['id'] ?? null;
        if (!$id) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Missing notification ID.']);
            exit;
        }

        if (mark_notification_read($pdo, $id)) {
            echo json_encode(['status' => 'success', 'message' => 'Notification marked as read.']);
        } else {
            http_response_code(500);
            echo json_encode(['status' => 'error', 'message' => 'Failed to mark notification as read.']);
        }
        exit;
    }


    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid action specified.']);

} catch (Exception $e) {
    error_log("Global Notification API Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Server error.']);
}
?>