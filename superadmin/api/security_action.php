<?php
// api/security_action.php
header('Content-Type: application/json');
session_start();
require_once __DIR__ . '/../../db_connection.php'; 

$action = $_REQUEST['action'] ?? '';

try {

    // 1. GET DETAILS
    if ($action === 'get_details') {
        $stmt = $pdo->query("SELECT * FROM tbl_security_settings WHERE id = 1");
        $data = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Defaults if empty
        if (!$data) {
            $data = [
                'min_password_length' => 8,
                'require_uppercase' => 1,
                'require_numbers' => 1,
                'require_special_chars' => 0,
                'password_expiry_days' => 90,
                'max_login_attempts' => 5,
                'lockout_duration_mins' => 15
            ]; 
        }
        
        echo json_encode(['status' => 'success', 'data' => $data]);
        exit;
    }

    // 2. UPDATE SETTINGS
    if ($action === 'update' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        
        $sql = "UPDATE tbl_security_settings 
                SET min_password_length=?, 
                    require_uppercase=?, 
                    require_numbers=?, 
                    require_special_chars=?, 
                    password_expiry_days=?, 
                    max_login_attempts=?, 
                    lockout_duration_mins=?
                WHERE id=?";
        
        $stmt = $pdo->prepare($sql);
        
        // Use intval(?? 0) to handle unchecked boxes
        $params = [
            $_POST['min_password_length'],
            intval($_POST['require_uppercase'] ?? 0),
            intval($_POST['require_numbers'] ?? 0),
            intval($_POST['require_special_chars'] ?? 0),
            $_POST['password_expiry_days'],
            $_POST['max_login_attempts'],
            $_POST['lockout_duration_mins'],
            1 // ID
        ];
        
        if ($stmt->execute($params)) {
            echo json_encode(['status' => 'success', 'message' => 'Security protocols updated successfully.']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Failed to update database.']);
        }
        exit;
    }

} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>