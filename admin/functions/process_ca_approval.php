<?php
// functions/process_ca_approval.php

require_once '../../db_connection.php'; 
// 1. INCLUDE NOTIFICATION MODEL
require_once '../models/global_model.php'; 

session_start();

header('Content-Type: application/json');

// --- DEBUG CHECK ---
if (!isset($_SESSION['usertype'])) {
    echo json_encode([
        'status' => 'error', 
        'message' => 'Unauthorized. Active Session Variables: ' . json_encode($_SESSION)
    ]);
    exit;
}

// 2. INPUT VALIDATION
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method.']);
    exit;
}

// Get POST data
$id = $_POST['id'] ?? null;
$action = $_POST['action'] ?? null;
$approved_amount = $_POST['amount'] ?? 0;

if (!$id || !$action) {
    echo json_encode(['status' => 'error', 'message' => 'Missing required parameters.']);
    exit;
}

try {
    // 3. CHECK CURRENT STATUS & FETCH EMPLOYEE DETAILS
    // Added 'employee_id' and 'date_requested' to the select query so we can notify them
    $stmt = $pdo->prepare("SELECT id, status, amount, employee_id, date_requested FROM tbl_cash_advances WHERE id = ?");
    $stmt->execute([$id]);
    $request = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$request) {
        echo json_encode(['status' => 'error', 'message' => 'Request not found.']);
        exit;
    }

    if ($request['status'] !== 'Pending') {
        echo json_encode(['status' => 'error', 'message' => 'This request has already been processed (Status: ' . $request['status'] . ').']);
        exit;
    }

    // Prepare Notification Data
    $emp_id = $request['employee_id'];
    $req_date = date("M d, Y", strtotime($request['date_requested']));

    // 4. PROCESS LOGIC
    if ($action === 'approve') {
        
        // Validation: Ensure amount is valid
        if (!is_numeric($approved_amount) || $approved_amount <= 0) {
            echo json_encode(['status' => 'error', 'message' => 'Invalid approval amount.']);
            exit;
        }

        // Logic: Update status to 'Deducted' (or Approved)
        $new_status = 'Deducted'; 
        // Note: In some systems 'Deducted' means it's taken from salary. If this is just approval, maybe 'Approved'. 
        // Keeping 'Deducted' as per your original code.
        
        $update_sql = "UPDATE tbl_cash_advances SET status = :status, amount = :amount WHERE id = :id";
        $update_stmt = $pdo->prepare($update_sql);
        $result = $update_stmt->execute([
            ':status' => $new_status,
            ':amount' => $approved_amount, 
            ':id'     => $id
        ]);
        
        $msg = "Cash Advance approved successfully with amount ₱" . number_format($approved_amount, 2);

        // --- SEND NOTIFICATION (APPROVED) ---
        if ($result) {
            $notif_msg = "Your Cash Advance request for {$req_date} has been APPROVED (₱" . number_format($approved_amount, 2) . ").";
            send_notification($pdo, $emp_id, 'Employee', 'info', $notif_msg, 'request_ca.php', 'Admin');
        }

    } elseif ($action === 'reject') {
        
        // Logic: Update status to 'Cancelled'
        $new_status = 'Cancelled';
        
        $update_sql = "UPDATE tbl_cash_advances SET status = :status WHERE id = :id";
        $update_stmt = $pdo->prepare($update_sql);
        $result = $update_stmt->execute([
            ':status' => $new_status,
            ':id'     => $id
        ]);
        
        $msg = "Cash Advance request has been rejected.";

        // --- SEND NOTIFICATION (REJECTED) ---
        if ($result) {
            $notif_msg = "Your Cash Advance request for {$req_date} has been REJECTED.";
            send_notification($pdo, $emp_id, 'Employee', 'warning', $notif_msg, 'cash_advance.php', 'Finance Admin');
        }

    } else {
        echo json_encode(['status' => 'error', 'message' => 'Unknown action type.']);
        exit;
    }

    // 5. SUCCESS RESPONSE
    if ($result) {
        echo json_encode(['status' => 'success', 'message' => $msg]);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Database update failed.']);
    }

} catch (PDOException $e) {
    error_log("Database Error in process_ca_approval: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'A database error occurred.']);
}
?>