<?php
// api/superadmin/general_settings_action.php
header('Content-Type: application/json');
session_start();

// --- 1. AUTHENTICATION (Super Admin Only) ---
if (!isset($_SESSION['usertype']) || $_SESSION['usertype'] != 0) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized access.']);
    exit;
}

// --- 2. DEPENDENCIES ---
require_once __DIR__ . '/../../db_connection.php'; 
require_once __DIR__ . '/../../helpers/audit_helper.php';

$action = $_REQUEST['action'] ?? '';

try {

    // =========================================================================
    // ACTION 1: GET SETTINGS
    // =========================================================================
    if ($action === 'get_details') {
        $stmt = $pdo->query("SELECT * FROM tbl_general_settings WHERE id = 1");
        $data = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$data) {
            $data = [
                'system_timezone' => 'Asia/Manila',
                'session_timeout_minutes' => 30,
                'maintenance_mode' => 0,
                'allow_forgot_password' => 1,
                'enable_email_notifications' => 1
            ]; 
        }
        // Never return the password to the frontend for security
        unset($data['smtp_password']); 
        echo json_encode(['status' => 'success', 'data' => $data]);
        exit;
    }

    // =========================================================================
    // ACTION 2: UPDATE SETTINGS
    // =========================================================================
    if ($action === 'update' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        
        $params = [
            $_POST['system_timezone'] ?? 'Asia/Manila',
            $_POST['session_timeout_minutes'] ?? 30,
            intval($_POST['maintenance_mode'] ?? 0),
            intval($_POST['allow_forgot_password'] ?? 0),
            intval($_POST['enable_email_notifications'] ?? 0),
            $_POST['smtp_host'] ?? '',
            $_POST['smtp_port'] ?? 587,
            $_POST['smtp_username'] ?? '',
            $_POST['email_sender_name'] ?? ''
        ];

        // Conditional Password Update (Only change if user typed something)
        $password_sql = "";
        if (!empty($_POST['smtp_password'])) {
            $password_sql = ", smtp_password = ?";
            $params[] = $_POST['smtp_password']; 
        }

        $params[] = 1; // WHERE id = 1

        $sql = "UPDATE tbl_general_settings 
                SET system_timezone=?, 
                    session_timeout_minutes=?, 
                    maintenance_mode=?, 
                    allow_forgot_password=?, 
                    enable_email_notifications=?, 
                    smtp_host=?, 
                    smtp_port=?, 
                    smtp_username=?, 
                    email_sender_name=? 
                    $password_sql
                WHERE id=?";
        
        $stmt = $pdo->prepare($sql);
        
        if ($stmt->execute($params)) {
        // 1. LOG AUDIT TRAIL
            logAudit($pdo, $_SESSION['user_id'], $_SESSION['usertype'], 'UPDATE_SETTINGS', "Updated system config.");

            // 2. [NEW] CLEAR CACHED SESSION DATA
            // This forces checking.php to re-fetch the new Timezone & Timeout on the next page load.
            unset($_SESSION['system_timezone']);
            unset($_SESSION['session_timeout']); // We will use this in checking.php next

            echo json_encode(['status' => 'success', 'message' => 'System configuration updated successfully.']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Failed to update database.']);
        }
        exit;
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>