<?php
// api/superadmin/security_settings_action.php
// Handles Password Complexity, Expiry, and Account Lockout Policies
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
    // ACTION 1: GET SECURITY DETAILS
    // =========================================================================
    if ($action === 'get_details') {
        $stmt = $pdo->query("SELECT * FROM tbl_security_settings WHERE id = 1");
        $data = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Defaults if table is empty
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

    // =========================================================================
    // ACTION 2: UPDATE SECURITY SETTINGS
    // =========================================================================
    if ($action === 'update' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        
        $sql = "UPDATE tbl_security_settings 
                SET min_password_length=?, 
                    require_uppercase=?, 
                    require_numbers=?, 
                    require_special_chars=?, 
                    password_expiry_days=?, 
                    max_login_attempts=?, 
                    lockout_duration_mins=?,
                    updated_at = NOW()
                WHERE id=?";
        
        $stmt = $pdo->prepare($sql);
        
        // Sanitize and handle boolean toggles
        $params = [
            intval($_POST['min_password_length'] ?? 8),
            intval($_POST['require_uppercase'] ?? 0),
            intval($_POST['require_numbers'] ?? 0),
            intval($_POST['require_special_chars'] ?? 0),
            intval($_POST['password_expiry_days'] ?? 90),
            intval($_POST['max_login_attempts'] ?? 5),
            intval($_POST['lockout_duration_mins'] ?? 15),
            1 // Record ID 1
        ];
        
        if ($stmt->execute($params)) {
            // ⭐ LOG AUDIT TRAIL
            logAudit(
                $pdo, 
                $_SESSION['user_id'], 
                $_SESSION['usertype'], 
                'UPDATE_SECURITY_POLICY', 
                "Updated password complexity and lockout rules."
            );

            echo json_encode(['status' => 'success', 'message' => 'Security protocols updated successfully.']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Failed to update database.']);
        }
        exit;
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Server Error: ' . $e->getMessage()]);
}
?>