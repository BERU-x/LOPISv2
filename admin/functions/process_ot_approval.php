<?php
// functions/process_ot_approval.php

// --- Configuration and Database Connection ---
// Adjust path to db_connection.php as necessary
require_once '../../db_connection.php'; 

header('Content-Type: application/json');

// 🛑 SECURITY CHECK: Ensure user is logged in and has the necessary permissions (usertype 0 or 1)
// Assuming your checking.php handles starting the session.
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
    $status = '';
    $update_hours = 0;

    if ($action === 'approve') {
        // Validation: Approved hours must be numeric and non-negative
        $update_hours = is_numeric($approved_hours) ? (int)floor($approved_hours) : 0;
        $status = 'Approved';
        
        // Ensure approved hours are not negative
        if ($update_hours < 0) $update_hours = 0; 
        
        // Query to update status and set approved hours
        $sql = "UPDATE tbl_overtime SET status = ?, hours_approved = ? WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$status, $update_hours, $ot_id]);

        echo json_encode(['status' => 'success', 'message' => "Request ID $ot_id **Approved** for $update_hours hours."]);
        
    } elseif ($action === 'reject') {
        $status = 'Rejected';
        $update_hours = 0; // Set approved hours to 0 upon rejection
        
        // Query to update status and set approved hours to 0
        $sql = "UPDATE tbl_overtime SET status = ?, hours_approved = ? WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$status, $update_hours, $ot_id]);

        echo json_encode(['status' => 'success', 'message' => "Request ID $ot_id **Rejected**."]);
        
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Invalid action specified.']);
    }
    
} catch (PDOException $e) {
    error_log("OT Approval DB Error: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Database error occurred during processing.']);
}
exit;