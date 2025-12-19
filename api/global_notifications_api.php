<?php
// api/global_notifications_api.php
header('Content-Type: application/json');
session_start();

// --- 1. PATH ADJUSTMENTS ---
// We now point to the new GLOBAL APP MODEL location
require_once __DIR__ . '/../app/models/global_app_model.php'; 
require_once __DIR__ . '/../db_connection.php'; 

// --- 2. AUTHENTICATION CHECK ---
if (!isset($_SESSION['user_id'])) {
    http_response_code(401); 
    echo json_encode(['status' => 'error', 'message' => 'Authentication required.']);
    exit;
}

$user_id = $_SESSION['user_id'];
// Default to 99 (Guest) if not set, preventing errors
$user_usertype = isset($_SESSION['usertype']) ? (int)$_SESSION['usertype'] : 99; 
$action = $_REQUEST['action'] ?? '';

// --- 3. ROLE MAPPING (INTEGERS) ---
// The Global Model now expects Integers (0, 1, 2), not Strings ('Admin').
// We pass the raw usertype directly because it matches our DB schema.
// 0 = Superadmin, 1 = Admin, 2 = Employee
$my_role = $user_usertype; 

try {
    // =========================================================
    // ACTION 1: FETCH NOTIFICATIONS
    // =========================================================
    if ($action === 'fetch') {

        // Determine the target ID (Employee ID vs User ID)
        $target_id_for_model = null;

        // If user is an Employee (2), we MUST find their employee_id
        if ($my_role === 2) {
            if (isset($_SESSION['employee_id'])) {
                $target_id_for_model = $_SESSION['employee_id'];
            } else {
                // Fallback DB lookup if session is missing it
                $stmt = $pdo->prepare("SELECT employee_id FROM tbl_users WHERE id = ?");
                $stmt->execute([$user_id]);
                $target_id_for_model = $stmt->fetchColumn();
            }
        } 
        // If Admin/Superadmin, we generally don't filter by target_user_id unless they have one
        else {
             $target_id_for_model = $_SESSION['employee_id'] ?? null;
        }

        // Call Model Function
        $notifications = get_my_notifications($pdo, $my_role, 10, $target_id_for_model);
        
        // Count Unread
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
    // ACTION 2: MARK ALL READ
    // =========================================================
    if ($action === 'mark_all_read') {
        // Pass 'all' as ID, and the current user's role integer
        if (mark_notification_read($pdo, 'all', $my_role)) {
            echo json_encode(['status' => 'success', 'message' => 'All notifications marked as read.']);
        } else {
            http_response_code(500);
            echo json_encode(['status' => 'error', 'message' => 'Failed to update database.']);
        }
        exit;
    }

    // =========================================================
    // ACTION 3: MARK SINGLE READ
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
            echo json_encode(['status' => 'error', 'message' => 'Failed to mark as read.']);
        }
        exit;
    }

    // Invalid Action Fallback
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid action specified.']);

} catch (Exception $e) {
    error_log("Global Notification API Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Server error.']);
}
?>