<?php
// functions/process_ot_approval.php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);   
// --- Configuration and Database Connection ---
// Adjust path to db_connection.php as necessary
require_once '../../db_connection.php'; 

// 1. INCLUDE THE GLOBAL MODEL FOR NOTIFICATIONS
// Adjust this path if your models folder is elsewhere (e.g., '../models/global_model.php')
require_once '../models/global_model.php'; 

header('Content-Type: application/json');

// 🛑 SECURITY CHECK: Ensure user is logged in and has the necessary permissions (usertype 0 or 1)
if (empty(session_id())) {
    session_start();
}
if (!isset($_SESSION['logged_in']) || ($_SESSION['usertype'] != 0 && $_SESSION['usertype'] != 1)) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized access.']);
    exit;
}

// --- Data Input ---
$ot_id = $_POST['id'] ?? null;
$action = $_POST['action'] ?? null; // 'approve' or 'reject'
$approved_hours = $_POST['hours'] ?? null; // Only required for 'approve'

if (!$ot_id || !$action) {
    echo json_encode(['status' => 'error', 'message' => 'Missing ID or Action.']);
    exit;
}

try {
    // 2. FETCH OT DETAILS (Needed to know WHO to notify)
    // 2. FETCH OT DETAILS (Needed to know WHO to notify)
    // FIX: Changed 'date' to 'ot_date'
    $stmt_info = $pdo->prepare("SELECT employee_id, ot_date FROM tbl_overtime WHERE id = ?");    $stmt_info->execute([$ot_id]);
    $ot_data = $stmt_info->fetch(PDO::FETCH_ASSOC);

    if (!$ot_data) {
        echo json_encode(['status' => 'error', 'message' => 'Overtime request not found.']);
        exit;
    }

    $emp_id = $ot_data['employee_id'];
    $ot_date_formatted = date("M d, Y", strtotime($ot_data['ot_date']));

    $status = '';
    $update_hours = 0;

    if ($action === 'approve') {
        // Validation: Approved hours must be numeric and non-negative
        // Use floatval to ensure it's a floating point number suitable for DOUBLE/DECIMAL fields.
        // We also use max(0, ...) just to ensure no negative values leak in.
        $update_hours = is_numeric($approved_hours) ? max(0, floatval($approved_hours)) : 0.00;

        // Since your column is DOUBLE(4, 2), we explicitly format the number to 2 decimal places 
        // to prevent PDO from receiving too many digits, although typically binding as float is sufficient.
        $update_hours = number_format($update_hours, 2, '.', '');
        $status = 'Approved';
        
        // Ensure approved hours are not negative
        if ($update_hours < 0) $update_hours = 0; 
        
        // Query to update status and set approved hours
        $sql = "UPDATE tbl_overtime SET status = ?, hours_approved = ? WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$status, $update_hours, $ot_id]);

        // 3. SEND NOTIFICATION (APPROVED)
        $msg = "Your Overtime request for {$ot_date_formatted} has been APPROVED ({$update_hours} hrs).";
        // Arguments: $pdo, $target_user, $target_role, $type, $message, $link, $sender
        send_notification($pdo, $emp_id, 'Employee', 'info', $msg, 'file_overtime.php', 'Admin');

        echo json_encode(['status' => 'success', 'message' => "Request ID $ot_id **Approved** for $update_hours hours."]);
        
    } elseif ($action === 'reject') {
        $status = 'Rejected';
        $update_hours = 0; // Set approved hours to 0 upon rejection
        
        // Query to update status and set approved hours to 0
        $sql = "UPDATE tbl_overtime SET status = ?, hours_approved = ? WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$status, $update_hours, $ot_id]);

        // 3. SEND NOTIFICATION (REJECTED)
        $msg = "Your Overtime request for {$ot_date_formatted} has been REJECTED.";
        send_notification($pdo, $emp_id, 'Employee', 'warning', $msg, 'file_overtime.php', 'Admin');

        echo json_encode(['status' => 'success', 'message' => "Request ID $ot_id **Rejected**."]);
        
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Invalid action specified.']);
    }
    
} catch (PDOException $e) {
    // 🛑 USE THIS TEMPORARILY 🛑
    echo json_encode(['status' => 'error', 'message' => 'CRITICAL SQL ERROR: ' . $e->getMessage()]);
    // 🛑 REMOVE THIS LINE AFTER DEBUGGING 🛑
    // error_log("OT Approval DB Error: " . $e->getMessage()); // Keep this line for server logging
}
exit;
?>