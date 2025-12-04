<?php
// user/functions/submit_ca_request.php

// 1. ENABLE ERROR REPORTING FOR DEBUGGING
// (Remove these two lines after it works)
ini_set('display_errors', 1);
error_reporting(E_ALL);

// 2. DATABASE CONNECTION & NOTIFICATION MODEL
// Assuming structure: root/user/functions/submit_ca_request.php
// We need to go back to root: ../../

$root_path = '../../'; // Default assumption based on your path

if (file_exists($root_path . 'db_connection.php')) {
    require_once $root_path . 'db_connection.php';
    // Include Notification Model relative to DB connection
    require_once $root_path . 'models/global_model.php'; 
} elseif (file_exists('../db_connection.php')) {
    require_once '../db_connection.php';
    require_once '../models/global_model.php';
} else {
    // Return JSON error if DB file is missing
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'Database file not found. Check paths.']);
    exit;
}

session_start();
header('Content-Type: application/json');

// 3. AUTH CHECK
if (!isset($_SESSION['employee_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Session expired. Please login again.']);
    exit;
}

// 4. INPUT PROCESSING
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
        // Optional: Prevent duplicate pending requests
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
            
            // --- 5. SEND NOTIFICATION TO ADMIN ---
            // Helper to get sender name safely
            $sender_name = (isset($_SESSION['firstname'])) ? $_SESSION['firstname'] . ' ' . $_SESSION['lastname'] : "Employee " . $employee_id;
            
            $formatted_amount = number_format($amount, 2);
            $notif_msg = "$sender_name has requested a Cash Advance of ₱$formatted_amount.";
            
            // Send to: NULL (All Admins) | Role: Admin | Type: warning (Financial request)
            // Link: 'cash_advance.php' (Admin page to approve CA)
            if (function_exists('send_notification')) {
                send_notification($pdo, null, 'Admin', 'warning', $notif_msg, 'cashadv_approval.php', $sender_name);
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