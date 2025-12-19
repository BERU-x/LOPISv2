<?php
// api/update_payslip.php

header('Content-Type: application/json');
session_start();

// --- 1. SECURITY & AUTHORIZATION ---
// Adjust the relative path to your database connection file
require_once '../models/db.php'; 

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized access.']);
    exit;
}

// Check User Type: Only Superadmin (0) and Admin (1) can update
// User Type 2 (Employee) is strictly forbidden
$user_type = $_SESSION['user_type'] ?? 2; 
if ($user_type == 2) {
    echo json_encode(['status' => 'error', 'message' => 'Permission denied. Employees cannot edit payslips.']);
    exit;
}

// --- 2. VALIDATE INPUTS ---
$payroll_id = $_POST['payroll_id'] ?? null;
$items      = $_POST['items'] ?? []; // Array of [item_id => amount]

if (!$payroll_id || !is_numeric($payroll_id)) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid Payroll ID.']);
    exit;
}

if (empty($items)) {
    echo json_encode(['status' => 'error', 'message' => 'No changes detected.']);
    exit;
}

try {
    // Start Transaction (Vital for financial data)
    $pdo->beginTransaction();

    // --- 3. UPDATE LINE ITEMS ---
    // We update each item specifically belonging to this payroll_id for security
    $sql_update_item = "UPDATE tbl_payroll_items 
                        SET amount = :amount 
                        WHERE id = :id AND payroll_id = :pid";
    $stmt_update = $pdo->prepare($sql_update_item);

    foreach ($items as $item_id => $amount) {
        // Sanitize: ensure amount is a float, non-negative
        $amount = floatval($amount);
        if ($amount < 0) $amount = 0;

        $stmt_update->execute([
            ':amount' => $amount,
            ':id'     => $item_id,
            ':pid'    => $payroll_id
        ]);
    }

    // --- 4. RECALCULATE TOTALS ---
    // Don't trust the frontend math. Re-sum everything from the database.
    
    // A. Calculate Gross Pay (Sum of all 'earning' types)
    $stmt_gross = $pdo->prepare("SELECT SUM(amount) FROM tbl_payroll_items WHERE payroll_id = :pid AND item_type = 'earning'");
    $stmt_gross->execute([':pid' => $payroll_id]);
    $new_gross = floatval($stmt_gross->fetchColumn() ?: 0);

    // B. Calculate Total Deductions (Sum of all 'deduction' types)
    $stmt_deduct = $pdo->prepare("SELECT SUM(amount) FROM tbl_payroll_items WHERE payroll_id = :pid AND item_type = 'deduction'");
    $stmt_deduct->execute([':pid' => $payroll_id]);
    $new_deductions = floatval($stmt_deduct->fetchColumn() ?: 0);

    // C. Calculate Net Pay
    $new_net_pay = $new_gross - $new_deductions;

    // --- 5. UPDATE MAIN HEADER RECORD ---
    $sql_header = "UPDATE tbl_payroll 
                   SET gross_pay = :gross, 
                       total_deductions = :deduct, 
                       net_pay = :net 
                   WHERE id = :id";
    $stmt_header = $pdo->prepare($sql_header);
    $stmt_header->execute([
        ':gross'  => $new_gross,
        ':deduct' => $new_deductions,
        ':net'    => $new_net_pay,
        ':id'     => $payroll_id
    ]);

    // Commit changes
    $pdo->commit();

    echo json_encode([
        'status'  => 'success',
        'message' => 'Payslip updated successfully!',
        'data'    => [
            'gross' => $new_gross,
            'net'   => $new_net_pay
        ]
    ]);

} catch (Exception $e) {
    // If anything fails, roll back everything so data isn't corrupted
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("Update Payslip Error: " . $e->getMessage()); // Log error to server file
    echo json_encode(['status' => 'error', 'message' => 'Database error occurred.']);
}
?>