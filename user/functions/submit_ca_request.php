<?php
// user/functions/submit_ca_request.php

// 1. SESSION & HEADERS
session_start();
header('Content-Type: application/json');

// 2. ERROR REPORTING (Turn off for production, keep on for dev)
// ini_set('display_errors', 0);
// error_reporting(0);

// 3. INCLUDES
require_once '../../db_connection.php'; 
require_once '../models/global_model.php'; 

date_default_timezone_set('Asia/Manila');

// 4. AUTH CHECK
if (!isset($_SESSION['employee_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Session expired. Please login again.']);
    exit;
}

// 5. INPUT PROCESSING
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    $employee_id = $_SESSION['employee_id'];
    $amount      = $_POST['amount'] ?? 0;
    $date_needed = $_POST['date_needed'] ?? '';
    $remarks     = trim($_POST['remarks'] ?? '');

    // Validation
    if ($amount <= 0) {
        echo json_encode(['status' => 'error', 'message' => 'Amount must be greater than 0.']);
        exit;
    }
    if (empty($date_needed)) {
        echo json_encode(['status' => 'error', 'message' => 'Date needed is required.']);
        exit;
    }

    try {
        // Prevent duplicate pending requests
        $stmtCheck = $pdo->prepare("SELECT COUNT(*) FROM tbl_cash_advances WHERE employee_id = ? AND status = 'Pending'");
        $stmtCheck->execute([$employee_id]);
        if ($stmtCheck->fetchColumn() > 0) {
            echo json_encode(['status' => 'error', 'message' => 'You already have a Pending request.']);
            exit;
        }

        // Insert
        $sql = "INSERT INTO tbl_cash_advances (employee_id, amount, date_requested, remarks, status) VALUES (?, ?, ?, ?, 'Pending')";
        $stmt = $pdo->prepare($sql);
        
        if ($stmt->execute([$employee_id, $amount, $date_needed, $remarks])) {
            
            // --- 6. NOTIFICATION LOGIC (SIMPLIFIED) ---
            
            // We fetch the name just for the message content itself
            // (Or we could even make the message generic, but let's keep it specific)
            $sender_name = $_SESSION['employee_id']; // Default
            
            // Quick fetch for the message string (Optional: if you want the name inside the text)
            $stmt_name = $pdo->prepare("SELECT firstname, lastname FROM tbl_employees WHERE employee_id = ?");
            $stmt_name->execute([$employee_id]);
            $user_info = $stmt_name->fetch(PDO::FETCH_ASSOC);

            if ($user_info) {
                $sender_name = $user_info['firstname'] . ' ' . $user_info['lastname'];
            }

            $formatted_amount = number_format($amount, 2);
            $notif_msg = "$sender_name has requested a Cash Advance of ₱$formatted_amount.";
            
            // Send to: NULL (All Admins) | Role: Admin | Type: warning
            // Note: We don't pass the last argument ($sender_name) because the function now handles it!
            if (function_exists('send_notification')) {
                send_notification($pdo, null, 'Admin', 'warning', $notif_msg, 'cashadv_approval.php');
            }

            echo json_encode(['status' => 'success', 'message' => 'Request submitted successfully! Admin has been notified.']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Database insert failed.']);
        }

    } catch (PDOException $e) {
        // Return actual database error for debugging
        echo json_encode(['status' => 'error', 'message' => 'DB Error: ' . $e->getMessage()]);
    }

} else {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method.']);
}
?>