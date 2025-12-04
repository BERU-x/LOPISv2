<?php
// admin/functions/process_leave_approval.php

// 1. SESSION & HEADERS
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
header('Content-Type: application/json');

// 2. INCLUDES
require_once '../../db_connection.php'; 
require_once '../models/global_model.php'; 

date_default_timezone_set('Asia/Manila');

// 3. AUTH CHECK
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized access.']);
    exit;
}

// 4. INPUT VALIDATION
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method.']);
    exit;
}

$leave_id = $_POST['id'] ?? null;
$action   = $_POST['action'] ?? null;

if (!$leave_id || !$action) {
    echo json_encode(['status' => 'error', 'message' => 'Missing ID or Action.']);
    exit;
}

try {
    // 5. FETCH LEAVE DETAILS (Need Employee ID and Dates for notification)
    $stmt = $pdo->prepare("SELECT employee_id, leave_type, start_date FROM tbl_leave WHERE id = ?");
    $stmt->execute([$leave_id]);
    $leave_data = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$leave_data) {
        echo json_encode(['status' => 'error', 'message' => 'Leave request not found.']);
        exit;
    }

    $status_code = ($action === 'approve') ? 1 : 2; 
    $status_text = ($status_code === 1) ? "APPROVED" : "REJECTED";

    // 6. UPDATE STATUS
    $update_sql = "UPDATE tbl_leave SET status = ? WHERE id = ?";
    $stmt_update = $pdo->prepare($update_sql);
    
    if ($stmt_update->execute([$status_code, $leave_id])) {
        
        // 7. SEND NOTIFICATION
        $s_date = date("M d", strtotime($leave_data['start_date']));
        $msg = "Your {$leave_data['leave_type']} ({$s_date}) has been {$status_text}.";

        // PASS NULL for sender name (Auto-detects Administrator)
        send_notification(
            $pdo, 
            $leave_data['employee_id'], 
            'Employee', 
            'leave', 
            $msg, 
            'request_leave.php', 
            null
        );

        echo json_encode(['status' => 'success', 'message' => "Leave request has been $status_text."]);

    } else {
        echo json_encode(['status' => 'error', 'message' => 'Database update failed.']);
    }

} catch (PDOException $e) {
    error_log("Leave Approval Error: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Database error occurred.']);
}
?>