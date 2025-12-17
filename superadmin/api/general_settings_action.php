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
                'allow_forgot_password' => 1, // Added fallback for this field
                'enable_email_notifications' => 1
            ]; 
        }
        unset($data['smtp_password']); 
        echo json_encode(['status' => 'success', 'data' => $data]);
        exit;
    }

    // 2. UPDATE SETTINGS (*** FINAL FIXED SECTION ***)
    if ($action === 'update' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        
        // --- FIX APPLIED HERE: Safely retrieve all POST variables ---
        // Use ?? '' or ?? default_value for all fields that are not checkboxes/toggles
        
        $params = [
            $_POST['system_timezone'] ?? 'Asia/Manila', // Added safety check
            $_POST['session_timeout_minutes'] ?? 30,     // Added safety check
            intval($_POST['maintenance_mode'] ?? 0),           
            intval($_POST['allow_forgot_password'] ?? 0),      
            intval($_POST['enable_email_notifications'] ?? 0), 
            
            // CRITICAL FIX FOR SMTP WARNINGS (Lines 42-45 in your original context)
            $_POST['smtp_host'] ?? '',         // Fix: Access safely
            $_POST['smtp_port'] ?? 587,        // Fix: Access safely (use 587 as default)
            $_POST['smtp_username'] ?? '',     // Fix: Access safely
            $_POST['email_sender_name'] ?? ''  // Fix: Access safely
        ];

        // Conditional Password Update
        $password_sql = "";
        if (!empty($_POST['smtp_password'])) {
            $password_sql = ", smtp_password = ?";
            $params[] = $_POST['smtp_password']; 
        }

        $params[] = 1; // ID

        // Construct the SQL query string
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