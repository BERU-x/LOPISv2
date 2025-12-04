<?php
// functions/process_ot_approval.php

// 1. SESSION & HEADERS
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
header('Content-Type: application/json');

// 2. ERROR REPORTING (Disable for production)
// ini_set('display_errors', 0);
// error_reporting(0);

// 3. INCLUDES
require_once '../../db_connection.php'; 
require_once '../models/global_model.php'; 

date_default_timezone_set('Asia/Manila');

// 4. SECURITY CHECK
// Ensure user is logged in and is an Admin (usertype 1) or Super Admin (0)
if (!isset($_SESSION['logged_in']) || ($_SESSION['usertype'] != 0 && $_SESSION['usertype'] != 1)) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized access.']);
    exit;
}

// 5. DATA INPUT
$ot_id = $_POST['id'] ?? null;
$action = $_POST['action'] ?? null; // 'approve' or 'reject'
$approved_hours = $_POST['hours'] ?? null; // Only required for 'approve'

if (!$ot_id || !$action) {
    echo json_encode(['status' => 'error', 'message' => 'Missing ID or Action.']);
    exit;
}

try {
    // 6. FETCH OT DETAILS (Needed to know WHO to notify)
    $stmt_info = $pdo->prepare("SELECT employee_id, ot_date FROM tbl_overtime WHERE id = ?");
    $stmt_info->execute([$ot_id]);
    $ot_data = $stmt_info->fetch(PDO::FETCH_ASSOC);

    if (!$ot_data) {
        echo json_encode(['status' => 'error', 'message' => 'Overtime request not found.']);
        exit;
    }

    $emp_id = $ot_data['employee_id'];
    $ot_date_formatted = date("M d, Y", strtotime($ot_data['ot_date']));

    $status = '';
    $update_hours = 0;

    // 7. PROCESS ACTION
    if ($action === 'approve') {
        // Validation: Approved hours must be numeric and non-negative
        $update_hours = is_numeric($approved_hours) ? max(0, floatval($approved_hours)) : 0.00;
        
        // Format to 2 decimal places
        $update_hours = number_format($update_hours, 2, '.', '');
        $status = 'Approved';
        
        // Update DB
        $sql = "UPDATE tbl_overtime SET status = ?, hours_approved = ? WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $result = $stmt->execute([$status, $update_hours, $ot_id]);

        // --- SEND NOTIFICATION (APPROVED) ---
        if ($result) {
            $msg = "Your Overtime request for {$ot_date_formatted} has been APPROVED ({$update_hours} hrs).";
            
            // PASS NULL for sender to auto-detect 'Administrator'
            send_notification($pdo, $emp_id, 'Employee', 'info', $msg, 'file_overtime.php', null);
        }

        echo json_encode(['status' => 'success', 'message' => "Request ID $ot_id **Approved** for $update_hours hours."]);
        
    } elseif ($action === 'reject') {
        $status = 'Rejected';
        $update_hours = 0; 
        
        // Update DB
        $sql = "UPDATE tbl_overtime SET status = ?, hours_approved = ? WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $result = $stmt->execute([$status, $update_hours, $ot_id]);

        // --- SEND NOTIFICATION (REJECTED) ---
        if ($result) {
            $msg = "Your Overtime request for {$ot_date_formatted} has been REJECTED.";
            
            // PASS NULL for sender to auto-detect 'Administrator'
            send_notification($pdo, $emp_id, 'Employee', 'warning', $msg, 'file_overtime.php', null);
        }

        echo json_encode(['status' => 'success', 'message' => "Request ID $ot_id **Rejected**."]);
        
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Invalid action specified.']);
    }
    
} catch (PDOException $e) {
    // Log error and return generic message
    error_log("OT Approval DB Error: " . $e->getMessage()); 
    echo json_encode(['status' => 'error', 'message' => 'A database error occurred.']);
}
exit;
?>