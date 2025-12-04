<?php
// admin/functions/create_leave_admin.php
session_start();
require_once '../../db_connection.php';
require_once '../models/leave_model.php'; // Kept for credit balance check function
require_once '../models/global_model.php';

if (isset($_POST['apply_leave'])) {
    $emp_id = $_POST['employee_id'];
    $l_type = $_POST['leave_type'];
    $s_date = $_POST['start_date'];
    $e_date = $_POST['end_date'];
    $reason = trim($_POST['reason']);

    // A. Date Validation
    $start = new DateTime($s_date);
    $end = new DateTime($e_date);
    
    if ($end < $start) {
        $_SESSION['error'] = "End date cannot be before start date.";
        header("Location: ../leave_management.php");
        exit;
    }

    $diff = $start->diff($end);
    $days_requested = $diff->days + 1;

    // B. Credit Validation
    // We use the model function just to check balances, but we handle the insert manually below
    $balances = get_leave_balance($pdo, $emp_id);
    $error = null;

    if ($l_type != 'Unpaid Leave' && $l_type != 'Maternity/Paternity') {
        if (isset($balances[$l_type])) {
            $remaining = $balances[$l_type]['remaining'];
            if ($days_requested > $remaining) {
                $error = "Insufficient credits! Requested $days_requested days, but only $remaining days remaining for $l_type.";
            }
        }
    }

    if ($error) {
        $_SESSION['error'] = $error;
    } else {
        
        try {
            // C. DIRECT INSERT AS APPROVED (Status = 1)
            // We write the SQL directly here to ensure it goes in as '1' (Approved) 
            // instead of relying on the shared model which might default to '0' (Pending).
            
            $sql = "INSERT INTO tbl_leave 
                    (employee_id, leave_type, start_date, end_date, days_count, reason, status, created_on) 
                    VALUES (?, ?, ?, ?, ?, ?, 1, NOW())"; // <--- Note the '1' for status
            
            $stmt = $pdo->prepare($sql);
            
            if ($stmt->execute([$emp_id, $l_type, $s_date, $e_date, $days_requested, $reason])) {
                
                $_SESSION['message'] = "Leave request successfully filed and APPROVED.";

                // --- NOTIFY THE EMPLOYEE ---
                // Notification now reflects that it is already approved
                $msg = "Admin filed a {$l_type} for you ({$s_date}). It has been automatically APPROVED.";
                
                // Pass NULL for sender (Auto-detects Administrator)
                send_notification($pdo, $emp_id, 'Employee', 'success', $msg, 'my_leaves.php', null);
                
            } else {
                $_SESSION['error'] = "Failed to submit leave request.";
            }

        } catch (PDOException $e) {
            $_SESSION['error'] = "Database Error: " . $e->getMessage();
        }
    }
    
    header("Location: ../leave_management.php");
    exit;
}
?>