<?php
// api/general_settings_action.php
header('Content-Type: application/json');
session_start();
require_once __DIR__ . '/../../db_connection.php'; 

$action = $_REQUEST['action'] ?? '';

try {

    // 1. GET SETTINGS (No changes here)
    if ($action === 'get_details') {
        $stmt = $pdo->query("SELECT * FROM tbl_general_settings WHERE id = 1");
        $data = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$data) {
            $data = [
                'system_timezone' => 'Asia/Manila',
                'session_timeout_minutes' => 30,
                'maintenance_mode' => 0,
                'enable_email_notifications' => 1
            ]; 
        }
        unset($data['smtp_password']); 
        echo json_encode(['status' => 'success', 'data' => $data]);
        exit;
    }

    // 2. UPDATE SETTINGS (*** FIXED SECTION ***)
    if ($action === 'update' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        
        // FIX: Use intval() or check equality. 
        // JavaScript sends "0" or "1", so we just cast it to an integer.
        // We use the null coalescing operator (?? 0) to default to 0 if it's missing entirely.
        
        $params = [
            $_POST['system_timezone'],
            $_POST['session_timeout_minutes'],
            intval($_POST['maintenance_mode'] ?? 0),         // <--- FIXED
            intval($_POST['allow_forgot_password'] ?? 0),    // <--- FIXED
            intval($_POST['enable_email_notifications'] ?? 0), // <--- FIXED
            $_POST['smtp_host'],
            $_POST['smtp_port'],
            $_POST['smtp_username'],
            $_POST['email_sender_name']
        ];

        // Conditional Password Update
        $password_sql = "";
        if (!empty($_POST['smtp_password'])) {
            $password_sql = ", smtp_password = ?";
            $params[] = $_POST['smtp_password']; 
        }

        $params[] = 1; // ID

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
            echo json_encode(['status' => 'success', 'message' => 'System configuration updated successfully.']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Failed to update database.']);
        }
        exit;
    }

} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>