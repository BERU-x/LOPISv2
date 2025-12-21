<?php
// api/global_notifications_api.php
header('Content-Type: application/json');
session_start();

// 1. DEPENDENCIES
require_once __DIR__ . '/../db_connection.php';
// We use the new model name we agreed upon
require_once __DIR__ . '/../app/models/notification_model.php';

// 2. AUTHENTICATION CHECK
// We check for 'logged_in' which checking.php sets to true
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

// 3. GET USER CONTEXT
// We use the standard session variables set by checking.php
$my_id   = $_SESSION['employee_id'] ?? 0;
$my_role = $_SESSION['usertype'] ?? 2; // 0=SA, 1=Admin, 2=Emp
$action  = $_REQUEST['action'] ?? '';

try {
    // =========================================================
    // ACTION 1: FETCH NOTIFICATIONS (For Polling)
    // =========================================================
    if ($action === 'fetch') {
        
        // A. Fetch the actual list (Limit 10 for dropdown)
        $notifications = [];
        if (function_exists('get_my_notifications')) {
            $notifications = get_my_notifications($pdo, $my_role, 10, $my_id);
        }

        // B. Get the precise "Unread Count" badge number
        // We do a separate lightweight count query for accuracy
        $allowed_roles = [$my_role];
        if ($my_role == 0) $allowed_roles = [0, 1]; // SA sees Admin alerts too
        $roles_str = implode(',', $allowed_roles);

        $sql_count = "SELECT COUNT(*) FROM tbl_notifications 
                      WHERE is_read = 0 
                      AND ((target_user_id = ?) OR (target_role IN ($roles_str)))";
        $stmt = $pdo->prepare($sql_count);
        $stmt->execute([$my_id]);
        $unread_count = $stmt->fetchColumn();

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
        
        // Define hierarchy again for security
        $allowed_roles = [$my_role];
        if ($my_role == 0) $allowed_roles = [0, 1];
        $roles_str = implode(',', $allowed_roles);

        // Update all unread matching this user
        $sql = "UPDATE tbl_notifications 
                SET is_read = 1 
                WHERE is_read = 0 
                AND ((target_user_id = :uid) OR (target_role IN ($roles_str)))";
        
        $stmt = $pdo->prepare($sql);
        $result = $stmt->execute([':uid' => $my_id]);

        if ($result) {
            echo json_encode(['status' => 'success', 'message' => 'All notifications marked as read.']);
        } else {
            throw new Exception("Database update failed.");
        }
        exit;
    }

    // =========================================================
    // ACTION 3: MARK SINGLE READ
    // =========================================================
    if ($action === 'mark_single_read') {
        $id = $_POST['id'] ?? null;
        
        if (!$id) {
            echo json_encode(['status' => 'error', 'message' => 'Missing ID']);
            exit;
        }

        // We don't need complex role checks here; if they click it, they can read it.
        $sql = "UPDATE tbl_notifications SET is_read = 1 WHERE id = :id";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':id' => $id]);

        echo json_encode(['status' => 'success']);
        exit;
    }

    // Fallback
    echo json_encode(['status' => 'error', 'message' => 'Invalid action']);

} catch (Exception $e) {
    error_log("API Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Server error']);
}
?>