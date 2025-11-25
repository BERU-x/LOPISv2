<?php
// functions/record_cash_advance.php

session_start();
require '../../db_connection.php'; 

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    $employee_id    = $_POST['employee_id'] ?? null;
    $amount         = $_POST['amount'] ?? 0;
    $date_requested = $_POST['date_requested'] ?? date('Y-m-d');
    $remarks         = $_POST['remarks'] ?? 'Requested by admin via dashboard.';

    // Validation
    if (!$employee_id || !is_numeric($amount) || floatval($amount) <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid employee ID or amount.']);
        exit;
    }

    try {
        $amount_float = floatval($amount);

        // SQL to insert the new Cash Advance record
        $sql = "INSERT INTO tbl_cash_advances 
                (employee_id, amount, date_requested, remarks, status)
                VALUES (:eid, :amount, :date_req, :remarks, 'Pending')";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':eid'      => $employee_id,
            ':amount'   => $amount_float,
            ':date_req' => $date_requested,
            ':remarks'   => $remarks
        ]);

        echo json_encode(['success' => true, 'message' => 'Cash Advance of ₱' . number_format($amount_float, 2) . ' recorded successfully.']);

    } catch (PDOException $e) {
        // Log the error (optional)
        error_log("CA Record Error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Database error during save.']);
    }

} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
}
?>